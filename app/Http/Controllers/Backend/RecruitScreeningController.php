<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Patient;

class RecruitScreeningController extends Controller
{
    public function dashboard(Request $request)
    {
        // 1. รับค่าจาก Dropdown ที่ผู้ใช้เลือก
        $selectedBatch = $request->input('intake_batch', '2/69');
        $viewBy = $request->input('view_by', 'battalion');

        // 2. ดึงรายชื่อผลัดทั้งหมดที่มีในระบบ
        $batches = Patient::whereNotNull('intake_batch')
            ->distinct()
            ->pluck('intake_batch')
            ->sortDesc();

        // 🌟 3. Query รวมข้อมูลระหว่างตาราง patients และ recruit_screenings (เพิ่มยอดผู้มีโรคประจำตัวแยกตามหน่วย)
        $dashboardData = DB::table('patients')
            ->where('patients.intake_batch', $selectedBatch)
            ->whereNotNull("patients.{$viewBy}")
            ->leftJoin('recruit_screenings', function($join) use ($selectedBatch) {
                $join->on('patients.id', '=', 'recruit_screenings.patient_id')
                     ->where('recruit_screenings.intake_batch', '=', $selectedBatch);
            })
            ->select(
                "patients.{$viewBy} as unit_name",
                DB::raw('COUNT(patients.id) as total_recruits'),
                DB::raw("SUM(CASE WHEN recruit_screenings.color_group = 'green' THEN 1 ELSE 0 END) as green_count"),
                DB::raw("SUM(CASE WHEN recruit_screenings.color_group = 'yellow' THEN 1 ELSE 0 END) as yellow_count"),
                DB::raw("SUM(CASE WHEN recruit_screenings.color_group = 'red' THEN 1 ELSE 0 END) as red_count"),
                DB::raw("SUM(CASE WHEN patients.chronic_disease_status = 'yes' THEN 1 ELSE 0 END) as chronic_yes_count"),
                DB::raw("SUM(CASE WHEN patients.chronic_disease_status = 'other' THEN 1 ELSE 0 END) as chronic_other_count")
            )
            ->groupBy("patients.{$viewBy}")
            ->get();

        // 4. คำนวณภาพรวม (Executive Summary)
        $summary = [
            'total_recruits' => $dashboardData->sum('total_recruits'),
            'total_green'    => $dashboardData->sum('green_count'),
            'total_yellow'   => $dashboardData->sum('yellow_count'),
            'total_red'      => $dashboardData->sum('red_count'),
        ];
        
        $summary['total_screened'] = $summary['total_green'] + $summary['total_yellow'] + $summary['total_red'];
        $summary['total_pending']  = $summary['total_recruits'] - $summary['total_screened'];
        
        $summary['progress_percent'] = $summary['total_recruits'] > 0 
            ? round(($summary['total_screened'] / $summary['total_recruits']) * 100) 
            : 0;

        $chartCategories = $dashboardData->pluck('unit_name')->toArray();
        $chartScreenedData = $dashboardData->map(function($item) {
            return $item->green_count + $item->yellow_count + $item->red_count;
        })->toArray();

        // 5. ดึงข้อมูลสถิติภูมิลำเนา
        $geographicStats = \App\Models\Patient::where('patients.intake_batch', $selectedBatch)
            ->leftJoin('recruit_screenings', 'patients.id', '=', 'recruit_screenings.patient_id')
            ->select(
                'patients.province',
                'patients.district',
                \Illuminate\Support\Facades\DB::raw('count(patients.id) as total_recruits'),
                \Illuminate\Support\Facades\DB::raw('count(recruit_screenings.id) as reported_count')
            )
            ->groupBy('patients.province', 'patients.district')
            ->orderBy('patients.province', 'asc')
            ->orderBy('total_recruits', 'desc')
            ->get()
            ->groupBy('province');

        // =====================================================================
        // 🌟 6. ดึงข้อมูลพลทหารในผลัด พร้อมสิทธิการรักษา, โรคประจำตัว และผลคัดกรอง
        // =====================================================================
        $batchPatients = \App\Models\Patient::with(['militaryUnit', 'recruitScreening'])
            ->where('intake_batch', $selectedBatch)
            ->orderBy('military_unit_id')
            ->orderBy('first_name')
            ->get([
                'id', 'cid', 'hn', 'pttype_code', 'pttype_name', 
                'chronic_disease_status', 'chronic_disease_detail',
                'rank', 'first_name', 'last_name', 'battalion', 'company', 'military_unit_id', 'province', 'district'
            ]);

        $allBatchPatientIds = $batchPatients->pluck('id')->toArray();
        $totalBatchCount = $batchPatients->count();
        $syncedRightsCount = $batchPatients->filter(fn($p) => !empty($p->pttype_name))->count();
        $unsyncedRightsCount = $totalBatchCount - $syncedRightsCount;

        $pttypeStats = [];
        $pttypePatientsMap = [];

        // 🌟 ตัวแปรสำหรับสรุปข้อมูลโรคประจำตัว (Chronic Disease Summary) และข้อมูลสำหรับ Export
        $chronicPatientsMap = [
            'none'  => [],
            'yes'   => [],
            'other' => [],
        ];
        $exportRecruitsList = [];

        foreach ($batchPatients as $pt) {
            $rightLabel = !empty($pt->pttype_name) ? $pt->pttype_name : 'ยังไม่ซิงค์สิทธิการรักษา (รอซิงค์ HosXP)';
            $isSyncedRight = !empty($pt->pttype_name);
            $unitText = $pt->battalion ?? ($pt->militaryUnit->short_name ?? $pt->company ?? 'ไม่ระบุหน่วย');
            $companyText = $pt->company ?? ($pt->militaryUnit->name ?? 'ไม่ระบุหน่วยฝึก');
            $fullName = ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');

            // 6.1 จัดกลุ่มตามสิทธิการรักษา
            if (!isset($pttypeStats[$rightLabel])) {
                $pttypeStats[$rightLabel] = [
                    'name'       => $rightLabel,
                    'code'       => $pt->pttype_code ?? null,
                    'count'      => 0,
                    'is_synced'  => $isSyncedRight,
                    'percentage' => 0,
                ];
                $pttypePatientsMap[$rightLabel] = [];
            }

            $pttypeStats[$rightLabel]['count']++;
            $pttypePatientsMap[$rightLabel][] = [
                'id'        => $pt->id,
                'full_name' => $fullName,
                'cid'       => $pt->cid ?? '-',
                'hn'        => $pt->hn ?: 'ยังไม่มี HN',
                'unit'      => $unitText,
                'pttype'    => $rightLabel,
            ];

            // 6.2 จัดกลุ่มตามข้อมูลโรคประจำตัว / ประวัติสุขภาพ
            $sc = $pt->recruitScreening;
            $cStatus = in_array($pt->chronic_disease_status, ['yes', 'other']) ? $pt->chronic_disease_status : 'none';
            $cDetail = $pt->chronic_disease_detail ?: ($cStatus === 'none' ? 'ไม่มีโรคประจำตัว' : 'ไม่ได้ระบุรายละเอียด');

            $colorLabel = 'รอรายงานตัว';
            if ($sc) {
                $colorLabel = $sc->color_group === 'red' ? 'พบเชื้อ (สีแดง)' : ($sc->color_group === 'yellow' ? 'กลุ่มเสี่ยง (สีเหลือง)' : 'กลุ่มปกติ (สีเขียว)');
            }

            // เก็บเข้ากลุ่มโรคประจำตัว (สำหรับกลุ่มไม่มีโรคประจำตัว จะนับเฉพาะคนที่มารายงานตัวแล้ว หรือถ้ามีโรคประจำตัวให้นับทันที)
            if ($cStatus === 'yes' || $cStatus === 'other' || $sc) {
                $chronicPatientsMap[$cStatus][] = [
                    'id'          => $pt->id,
                    'full_name'   => $fullName,
                    'cid'         => $pt->cid ?? '-',
                    'hn'          => $pt->hn ?: 'ยังไม่มี HN',
                    'unit'        => $unitText,
                    'company'     => $companyText,
                    'pttype'      => $pt->pttype_name ?: 'รอซิงค์สิทธิ',
                    'status'      => $cStatus,
                    'detail'      => $cDetail,
                    'color_group' => $sc->color_group ?? 'pending',
                    'color_label' => $colorLabel,
                    'symptoms'    => $sc->symptoms_note ?? '-',
                    'screened_at' => $sc ? $sc->created_at->addYears(543)->format('d/m/Y H:i') : '-',
                ];
            }

            // 6.3 เตรียมข้อมูลรายบุคคลสำหรับ Export CSV/Excel
            $exportRecruitsList[] = [
                'full_name'      => $fullName,
                'cid'            => $pt->cid ?? '-',
                'hn'             => $pt->hn ?: '-',
                'pttype'         => $pt->pttype_name ?: 'รอซิงค์สิทธิ',
                'company'        => $companyText,
                'battalion'      => $pt->battalion ?: '-',
                'province'       => $pt->province ?: '-',
                'district'       => $pt->district ?: '-',
                'is_screened'    => $sc ? 'มารายงานตัวแล้ว' : 'รอรายงานตัว',
                'color_group'    => $sc->color_group ?? 'pending',
                'color_label'    => $colorLabel,
                'symptoms_note'  => $sc->symptoms_note ?? '-',
                'chronic_status' => $cStatus,
                'chronic_label'  => $cStatus === 'yes' ? 'มีโรคประจำตัว' : ($cStatus === 'other' ? 'อื่นๆ' : 'ไม่มีโรคประจำตัว'),
                'chronic_detail' => $pt->chronic_disease_detail ?: '-',
                'screened_time'  => $sc ? $sc->created_at->addYears(543)->format('d/m/Y H:i น.') : '-',
            ];
        }

        foreach ($pttypeStats as $k => $v) {
            $pttypeStats[$k]['percentage'] = $totalBatchCount > 0 ? round(($v['count'] / $totalBatchCount) * 100, 1) : 0;
        }

        uasort($pttypeStats, function ($a, $b) {
            if ($a['is_synced'] !== $b['is_synced']) {
                return $a['is_synced'] ? -1 : 1;
            }
            return $b['count'] <=> $a['count'];
        });

        $treatmentRightsSummary = [
            'total_patients'   => $totalBatchCount,
            'synced_count'     => $syncedRightsCount,
            'unsynced_count'   => $unsyncedRightsCount,
            'synced_percent'   => $totalBatchCount > 0 ? round(($syncedRightsCount / $totalBatchCount) * 100, 1) : 0,
            'rights_breakdown' => array_values($pttypeStats),
        ];

        $chronicSummary = [
            'none_count'    => count($chronicPatientsMap['none']),
            'yes_count'     => count($chronicPatientsMap['yes']),
            'other_count'   => count($chronicPatientsMap['other']),
            'special_total' => count($chronicPatientsMap['yes']) + count($chronicPatientsMap['other']),
        ];

        return view('backend.recruit_screenings.dashboard', compact(
            'dashboardData', 'batches', 'selectedBatch', 'viewBy', 
            'summary', 'chartCategories', 'chartScreenedData', 'geographicStats',
            'treatmentRightsSummary', 'pttypePatientsMap', 'allBatchPatientIds',
            'chronicSummary', 'chronicPatientsMap', 'exportRecruitsList'
        ));
    }

    public function scanIndex()
    {
        return view('backend.recruit_screenings.scan');
    }

    public function searchPatient(Request $request)
    {
        $request->validate([
            'cid'          => 'required|string|min:13',
            'scanned_name' => 'nullable|string|max:255',
            'is_scanned'   => 'nullable|boolean',
        ]);

        $cleanCid    = preg_replace('/[^0-9]/', '', $request->cid);
        $scannedName = trim((string) $request->input('scanned_name', ''));
        $isScanned   = $request->boolean('is_scanned');

        $patient = Patient::with('militaryUnit')->where('cid', $cleanCid)->first();

        // 🔍 กรณีไม่พบใน KSVR: ตรวจสูตร Mod-11 + ยืนยันชื่อจาก HosXP (Read-Only) หรือใช้ชื่อจาก OCR
        if (!$patient) {
            $isCidValid = false;
            if (preg_match('/^[1-8]\d{12}$/', $cleanCid)) {
                $sum = 0;
                for ($i = 0; $i < 12; $i++) {
                    $sum += ((int) $cleanCid[$i]) * (13 - $i);
                }
                $isCidValid = ((11 - ($sum % 11)) % 10) === (int) $cleanCid[12];
            }

            $resolvedName = $scannedName !== '' ? $scannedName : null;
            $nameSource   = $resolvedName ? 'ocr' : null;
            $hosxpHn      = null;

            if (strlen($cleanCid) === 13) {
                try {
                    // ดึงแบบ Read-Only ด้วย Exact Match บนคอลัมน์ cid (ใช้ Index ไม่กระทบเซิร์ฟเวอร์ HosXP)
                    $hosxpPatient = \App\Models\HosxpPatient::select('hn', 'cid', 'pname', 'fname', 'lname')
                        ->where('cid', $cleanCid)
                        ->first();

                    if ($hosxpPatient) {
                        $cleanUTF8 = function ($str): string {
                            if (empty($str)) {
                                return '';
                            }
                            $raw = (string) $str;
                            if (mb_check_encoding($raw, 'UTF-8') && !preg_match('/[\x{00C0}-\x{024F}]/u', $raw)) {
                                return trim($raw);
                            }
                            $utf8 = @iconv('TIS-620', 'UTF-8//IGNORE', $raw);
                            return trim(mb_convert_encoding($utf8 ?: $raw, 'UTF-8', 'UTF-8'));
                        };

                        $fullHosxpName = trim(
                            $cleanUTF8($hosxpPatient->pname ?? '') .
                            $cleanUTF8($hosxpPatient->fname ?? '') . ' ' .
                            $cleanUTF8($hosxpPatient->lname ?? '')
                        );

                        if ($fullHosxpName !== '') {
                            $resolvedName = $fullHosxpName;
                            $nameSource   = 'hosxp';
                            $hosxpHn      = $cleanUTF8($hosxpPatient->hn ?? '');
                        }
                    }
                } catch (\Throwable $e) {
                    // หากเชื่อมต่อ HosXP ไม่ได้ชั่วคราว ให้ใช้ชื่อที่กล้องอ่าน (OCR) แทนโดยไม่ให้ค้าง
                }
            }

            return response()->json([
                'status'  => 'not_found',
                'message' => 'ไม่พบข้อมูลทหารใหม่จากเลขบัตรประชาชนนี้ครับ',
                'not_found_detail' => [
                    'cid'           => $cleanCid,
                    'formatted_cid' => strlen($cleanCid) === 13
                        ? preg_replace('/^(\d)(\d{4})(\d{5})(\d{2})(\d)$/', '$1-$2-$3-$4-$5', $cleanCid)
                        : $cleanCid,
                    'is_cid_valid'  => $isCidValid,
                    'full_name'     => $resolvedName,
                    'name_source'   => $nameSource, // 'hosxp' | 'ocr' | null
                    'hosxp_hn'      => $hosxpHn,
                    'is_scanned'    => $isScanned,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $existingScreening = \App\Models\RecruitScreening::where('patient_id', $patient->id)
            ->where('intake_batch', $patient->intake_batch)
            ->first();

        if ($existingScreening) {
            $colorLabels = [
                'green'  => 'กลุ่มปกติ (สีเขียว)',
                'yellow' => 'กลุ่มเสี่ยง (สีเหลือง)',
                'red'    => 'กลุ่มพบเชื้อ (สีแดง)',
            ];
            return response()->json([
                'status'      => 'already_screened',
                'message'     => "พลฯ {$patient->first_name} {$patient->last_name} ถูกคัดกรองไปแล้วเมื่อ " . $existingScreening->created_at->copy()->addYears(543)->format('d/m/Y H:i น.'),
                'fullname'    => $patient->rank . $patient->first_name . ' ' . $patient->last_name,
                'cid'         => preg_replace('/^(\d)(\d{4})(\d{5})(\d{2})(\d)$/', '$1-$2-$3-$4-$5', $patient->cid),
                'color_group' => $existingScreening->color_group,
                'color_label' => $colorLabels[$existingScreening->color_group] ?? $existingScreening->color_group,
                'screened_at' => $existingScreening->created_at->copy()->addYears(543)->format('d/m/Y H:i น.'),
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $displayUnit = ($patient->battalion ?? 'ไม่ระบุหน่วย') . ($patient->company ? ' (' . $patient->company . ')' : '');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                     => $patient->id,
                'fullname'               => $patient->rank . $patient->first_name . ' ' . $patient->last_name,
                'cid'                    => $patient->cid,
                'hn'                     => $patient->hn,
                'pttype_name'            => $patient->pttype_name ?? 'ยังไม่ซิงค์สิทธิ HosXP',
                'unit'                   => $displayUnit,
                'batch'                  => $patient->intake_batch,
                'chronic_disease_status' => $patient->chronic_disease_status ?? 'none',
                'chronic_disease_detail' => $patient->chronic_disease_detail ?? '',
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function storeScreening(Request $request)
    {
        $request->validate([
            'patient_id'             => 'required|exists:patients,id',
            'color_group'            => 'required|in:green,yellow,red',
            'symptoms_note'          => 'nullable|string|max:255',
            'chronic_disease_status' => 'nullable|in:none,yes,other',
            'chronic_disease_detail' => 'nullable|string|max:255',
        ]);

        $patient = Patient::findOrFail($request->patient_id);

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $chronicStatus = $request->input('chronic_disease_status', 'none');
            $chronicDetail = ($chronicStatus === 'none') ? null : trim((string)$request->input('chronic_disease_detail'));

            $patient->forceFill([
                'chronic_disease_status' => $chronicStatus,
                'chronic_disease_detail' => $chronicDetail ?: null,
            ])->save();

            \App\Models\RecruitScreening::updateOrCreate(
                [
                    'patient_id'   => $patient->id,
                    'intake_batch' => $patient->intake_batch,
                ],
                [
                    'military_unit_id' => $patient->military_unit_id,
                    'color_group'      => $request->color_group,
                    'symptoms_note'    => $request->symptoms_note,
                    'screener_id'      => auth()->id()
                ]
            );

            \Illuminate\Support\Facades\DB::commit();

            broadcast(new \App\Events\RecruitScreened($patient->intake_batch));

            return response()->json(['status' => 'success', 'message' => "บันทึกคัดกรอง พลฯ {$patient->first_name} เรียบร้อยแล้ว"]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Recruit Screening Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'ระบบขัดข้อง ไม่สามารถบันทึกข้อมูลได้']);
        }
    }

    public function getPatientsByColor(Request $request)
    {
        $color = $request->get('color');
        $batch = $request->get('batch');

        if (!in_array($color, ['green', 'yellow', 'red']) || !$batch) {
            return response()->json(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
        }

        $patients = \App\Models\RecruitScreening::with(['patient.militaryUnit', 'patient.latestMews'])
            ->where('intake_batch', $batch)
            ->where('color_group', $color)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($screening) {
                $patient = $screening->patient;
                $latestMews = $patient->latestMews;

                return [
                    'screening_id'           => $screening->id,
                    'patient_id'             => $patient->id,   
                    'cid'                    => $patient->cid,  
                    'name'                   => $patient->rank . $patient->first_name . ' ' . $patient->last_name,
                    'unit'                   => $patient->battalion ?? $patient->company ?? $patient->militaryUnit->name ?? 'ไม่ระบุ',
                    'pttype_name'            => $patient->pttype_name ?? 'ยังไม่ซิงค์สิทธิ',
                    'chronic_disease_status' => $patient->chronic_disease_status ?? 'none',
                    'chronic_disease_detail' => $patient->chronic_disease_detail ?? null,
                    'note'                   => $screening->symptoms_note ?? 'ไม่มีบันทึกอาการเพิ่มเติม',
                    'time'                   => $screening->created_at->format('H:i น.'),
                    'has_mews'               => $latestMews ? true : false,
                    'mews_score'             => $latestMews ? $latestMews->total_score : null,
                    'mews_status'            => $latestMews ? $latestMews->status : null,
                ];
            });

        return response()->json(['status' => 'success', 'data' => $patients]);
    }

    public function destroyScreening($id)
    {
        try {
            $screening = \App\Models\RecruitScreening::findOrFail($id);
            $batch = $screening->intake_batch;
            $screening->delete();

            broadcast(new \App\Events\RecruitScreened($batch));

            return response()->json(['status' => 'success', 'message' => 'ยกเลิกข้อมูลการคัดกรองเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Delete Screening Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'ระบบขัดข้อง ไม่สามารถลบข้อมูลได้']);
        }
    }
}