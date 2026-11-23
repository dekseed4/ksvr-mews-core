<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CovidVitalLog;
use App\Models\MilitaryDischarge;
use App\Models\MilitaryUnit;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientMedicationLog;
use App\Models\RecruitAppointment;
use App\Models\RecruitInfirmaryStay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ReportExportController extends Controller
{
    private function getUserRoles($user): array
    {
        if (!$user) {
            return [];
        }
        $raw = $user->roles ?? $user->role ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [$raw];
        }
        return is_array($raw) ? $raw : (array) $raw;
    }

    private function canViewAllUnits($user): bool
    {
        $roles = $this->getUserRoles($user);
        return !empty(array_intersect(
            ['admin', 'er_nurse', 'opd_nurse', 'executive', 'registration', 'medical_staff', 'screening'],
            $roles
        ));
    }

    private function applyMedicScope($query, $user): void
    {
        if ($this->canViewAllUnits($user)) {
            return;
        }
        $query->where('military_unit_id', $user->military_unit_id);
        if (!empty($user->intake_batch)) {
            $query->where('intake_batch', $user->intake_batch);
        }
    }

    private function authorizePatient(Patient $patient, $user): void
    {
        if ($this->canViewAllUnits($user)) {
            return;
        }
        if ((int) $patient->military_unit_id !== (int) $user->military_unit_id) {
            abort(403, 'คุณไม่มีสิทธิ์ส่งออกข้อมูลของหน่วยฝึกอื่นครับ');
        }
        if (!empty($user->intake_batch) && !empty($patient->intake_batch) && $patient->intake_batch !== $user->intake_batch) {
            abort(403, 'คุณไม่มีสิทธิ์ส่งออกข้อมูลทหารใหม่ต่างผลัดครับ');
        }
    }

    private function formatThaiDate($date, bool $withTime = false): string
    {
        if (empty($date)) {
            return '-';
        }
        try {
            $dt = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
            $dt->locale('th');
            $yearTh = $dt->year + 543;
            $base = $dt->day . ' ' . $dt->translatedFormat('M') . ' ' . $yearTh;
            return $withTime ? ($base . ' ' . $dt->format('H:i') . ' น.') : $base;
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    private function resolveUnitLabel($unitId): string
    {
        if (empty($unitId)) {
            return 'ทุกหน่วยฝึก';
        }
        $unit = MilitaryUnit::find($unitId);
        return $unit ? ($unit->short_name ?? $unit->name) : 'ทุกหน่วยฝึก';
    }

    // =========================================================================
    // 📦 API ส่งข้อมูล JSON สำหรับสร้างไฟล์ Excel (.xlsx) ตรงจากปุ่มในแต่ละหน้า
    // =========================================================================
    public function exportData(Request $request, string $module)
    {
        $payload = $this->buildReportPayload($request, $module);
        return response()->json([
            'status' => 'success',
            'data'   => $payload,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    // =========================================================================
    // 🖨️ หน้าแบบฟอร์มรายงานทางการ A4 (รองรับ Print PDF / Direct PDF / รูปภาพ PNG / Excel)
    // =========================================================================
    public function printSheet(Request $request, string $module)
    {
        $report = $this->buildReportPayload($request, $module);
        $autoAction = $request->query('auto', ''); // '', 'print', 'pdf', 'png', 'excel'

        return view('backend.reports.export_sheet', compact('report', 'autoAction'));
    }

    private function buildReportPayload(Request $request, string $module): array
    {
        return match ($module) {
            'care_chart'   => $this->buildCareChartReport($request),
            'infirmary'    => $this->buildInfirmaryReport($request),
            'appointments' => $this->buildAppointmentsReport($request),
            'discharges'   => $this->buildDischargesReport($request),
            default        => abort(404, 'ไม่พบประเภทรายงานที่ต้องการส่งออก'),
        };
    }

    // =========================================================================
    // 1️⃣ โมดูล: ใบบันทึกสัญญาณชีพ (06:00/18:00) & ตารางยา (e-MAR)
    // รองรับ section=all (รวม 2 ชีต), section=vitals (เฉพาะสัญญาณชีพ), section=emar (เฉพาะตารางยา)
    // =========================================================================
    private function buildCareChartReport(Request $request): array
    {
        $patientId = $request->query('patient_id');
        if (empty($patientId)) {
            abort(422, 'กรุณาระบุรหัสผู้ป่วย (patient_id)');
        }

        $section = (string) $request->query('section', 'all');
        if (!in_array($section, ['all', 'vitals', 'emar'], true)) {
            $section = 'all';
        }

        $includeVitals = in_array($section, ['all', 'vitals'], true);
        $includeEmar   = in_array($section, ['all', 'emar'], true);

        $user = auth()->user();
        $patient = Patient::with(['militaryUnit', 'activeInfirmaryStay'])->findOrFail($patientId);
        $this->authorizePatient($patient, $user);

        $days = (int) $request->query('days', 10);
        if (!in_array($days, [7, 10, 14, 30], true)) {
            $days = 10;
        }

        $defaultStart = ($patient->activeInfirmaryStay && $patient->activeInfirmaryStay->admitted_at)
            ? $patient->activeInfirmaryStay->admitted_at->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        try {
            $startDate = Carbon::parse((string) $request->query('start_date', $defaultStart))->startOfDay();
        } catch (\Throwable $e) {
            $startDate = Carbon::parse($defaultStart)->startOfDay();
        }
        $endDate = $startDate->copy()->addDays($days - 1)->endOfDay();

        $dateCols = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $startDate->copy()->addDays($i);
            $dTh = $d->copy()->locale('th');
            $dateCols[] = [
                'date_str'   => $d->format('Y-m-d'),
                'thai_text'  => $dTh->copy()->addYears(543)->translatedFormat('j M Y'),
                'short_thai' => $dTh->translatedFormat('j M'),
            ];
        }

        $sheets = [];
        $recordedVitalRounds = 0;
        $medicationsCount    = 0;

        // ---------------------------------------------------------------------
        // ส่วนที่ 1: บันทึกสัญญาณชีพ 06:00 / 18:00 (คิวรีเฉพาะเมื่อต้องการ)
        // ---------------------------------------------------------------------
        if ($includeVitals) {
            $vitalDaysLimit = $section === 'vitals' ? $days : min(14, $days);
            $vitalDateCols  = array_slice($dateCols, 0, $vitalDaysLimit);
            $vitalEndStr    = $vitalDateCols[count($vitalDateCols) - 1]['date_str'];

            $vitalLogs = CovidVitalLog::where('patient_id', $patient->id)
                ->whereBetween('record_date', [$startDate->format('Y-m-d'), $vitalEndStr])
                ->get()
                ->groupBy(fn ($item) => $item->record_date instanceof Carbon ? $item->record_date->format('Y-m-d') : substr((string) $item->record_date, 0, 10));

            $vitalHeaders = ['วัน/เดือน/ปี', 'รอบเวลา', 'อุณหภูมิ (°C)', 'ชีพจร (/นาที)', 'ความดันโลหิต (mmHg)', 'หายใจ (/นาที)', 'O2 Sat (%)', 'MEWS Score', 'หมายเหตุ'];
            $vitalRows = [];

            foreach ($vitalDateCols as $dCol) {
                $dayGroup = $vitalLogs->get($dCol['date_str'], collect());
                foreach (['06:00' => '06.00 น.', '18:00' => '18.00 น.'] as $rKey => $rLabel) {
                    $log = $dayGroup->firstWhere('round_time', $rKey);
                    if ($log) {
                        $recordedVitalRounds++;
                    }
                    $vitalRows[] = [
                        $dCol['thai_text'],
                        $rLabel,
                        $log ? number_format((float) $log->temperature, 1) : '-',
                        $log->heart_rate ?? '-',
                        ($log && $log->systolic_bp) ? ($log->systolic_bp . '/' . ($log->diastolic_bp ?? '-')) : '-',
                        $log->respiratory_rate ?? '-',
                        $log ? ($log->o2sat . '%') : '-',
                        $log !== null ? (string) $log->mews_score : '-',
                        $log->note ?? '-',
                    ];
                }
            }

            $sheets[] = [
                'name'    => 'บันทึกสัญญาณชีพ',
                'title'   => $section === 'vitals'
                    ? "ตารางบันทึกสัญญาณชีพประจำวัน (06.00 น. / 18.00 น.) — ช่วง {$vitalDaysLimit} วัน"
                    : 'ส่วนที่ 1: บันทึกสัญญาณชีพประจำวัน (06.00 น. / 18.00 น.)',
                'headers' => $vitalHeaders,
                'rows'    => $vitalRows,
                'cid_col' => null,
            ];
        }

        // ---------------------------------------------------------------------
        // ส่วนที่ 2: ตารางการรับประทานยา e-MAR (คิวรีเฉพาะเมื่อต้องการ)
        // ---------------------------------------------------------------------
        if ($includeEmar) {
            $medications = PatientMedication::where('patient_id', $patient->id)
                ->where('is_active', true)
                ->orderBy('id', 'asc')
                ->get();

            $medicationsCount = $medications->count();

            $medLogs = PatientMedicationLog::where('patient_id', $patient->id)
                ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->keyBy(function ($log) {
                    $dStr = $log->log_date instanceof Carbon ? $log->log_date->format('Y-m-d') : substr((string) $log->log_date, 0, 10);
                    return $log->patient_medication_id . '_' . $dStr . '_' . $log->time_slot;
                });

            $marHeaders = array_merge(
                ['ชื่อยา', 'ปริมาณ / วิธีใช้', 'ระยะเวลา', 'มื้อยา'],
                array_map(fn ($c) => $c['short_thai'], $dateCols)
            );
            $marRows = [];

            foreach ($medications as $med) {
                $hasStd = $med->take_morning || $med->take_noon || $med->take_evening || $med->take_bedtime;
                $hasPrn = $med->take_prn || !empty($med->custom_slot_text) || !$hasStd || in_array($med->meal_timing, ['prn', 'external'], true);

                $slots = [];
                if ($med->take_morning) $slots['morning'] = 'เช้า';
                if ($med->take_noon)    $slots['noon']    = 'เที่ยง';
                if ($med->take_evening) $slots['evening'] = 'เย็น';
                if ($med->take_bedtime) $slots['bedtime'] = 'ก่อนนอน';
                if ($hasPrn) {
                    $slots['prn'] = !empty($med->custom_slot_text)
                        ? $med->custom_slot_text
                        : ($med->meal_timing === 'external' ? 'ทา/นวดตามอาการ' : 'เวลามีอาการ (PRN)');
                }

                $durationText = empty($med->end_date)
                    ? 'ต่อเนื่องทุกวัน'
                    : ($this->formatThaiDate($med->start_date) . ' – ' . $this->formatThaiDate($med->end_date));

                foreach ($slots as $slotKey => $slotLabel) {
                    $row = [
                        $med->drug_name,
                        trim(($med->dosage ?: 'ตามแพทย์สั่ง') . ' (' . ($med->meal_timing_label ?? $med->meal_timing) . ')'),
                        $durationText,
                        $slotLabel,
                    ];
                    foreach ($dateCols as $dCol) {
                        $cell = $medLogs->get($med->id . '_' . $dCol['date_str'] . '_' . $slotKey);
                        if ($cell && $cell->status === 'taken') {
                            $row[] = '✓';
                        } elseif ($cell && $cell->status === 'missed') {
                            $row[] = '✕';
                        } else {
                            $row[] = '';
                        }
                    }
                    $marRows[] = $row;
                }
            }

            $sheets[] = [
                'name'    => 'ตารางยา e-MAR',
                'title'   => $section === 'emar'
                    ? "ตารางบันทึกการรับประทานยา / ใช้ยา e-MAR (จำนวน {$days} วัน) — ✓ = ใช้แล้ว, ✕ = ขาดยา"
                    : "ส่วนที่ 2: ตารางบันทึกการรับประทานยา / ใช้ยา e-MAR (จำนวน {$days} วัน) — ✓ = ใช้แล้ว, ✕ = ขาดยา",
                'headers' => $marHeaders,
                'rows'    => $marRows,
                'cid_col' => null,
            ];
        }

        $fullName  = trim(($patient->rank ?? '') . ($patient->first_name ?? '') . ' ' . ($patient->last_name ?? ''));
        $unitName  = $patient->militaryUnit?->short_name ?? $patient->company ?? 'ไม่ระบุหน่วย';
        $rangeText = $dateCols[0]['thai_text'] . ' – ' . $dateCols[count($dateCols) - 1]['thai_text'];
        $idTag     = $patient->hn ?: $patient->id;

        $metaBySection = [
            'vitals' => [
                'title'    => 'ใบบันทึกสัญญาณชีพประจำวัน (06:00 น. / 18:00 น.)',
                'filename' => "VitalSigns_{$idTag}_" . date('Ymd'),
                'stat_lbl' => 'จำนวนรอบที่บันทึกแล้ว',
                'stat_val' => "{$recordedVitalRounds} รอบ (ช่วง {$days} วัน)",
            ],
            'emar' => [
                'title'    => 'ตารางบันทึกการรับประทานยา / การใช้ยา (e-MAR)',
                'filename' => "eMAR_{$idTag}_" . date('Ymd'),
                'stat_lbl' => 'รายการยาที่ใช้งาน',
                'stat_val' => "{$medicationsCount} รายการ (ช่วง {$days} วัน)",
            ],
            'all' => [
                'title'    => 'ใบบันทึกสัญญาณชีพ (06:00/18:00) และตารางการรับประทานยา (e-MAR)',
                'filename' => "CareChart_eMAR_{$idTag}_" . date('Ymd'),
                'stat_lbl' => 'รายการยา / รอบ V/S',
                'stat_val' => "{$medicationsCount} ยา • {$recordedVitalRounds} รอบ V/S ({$days} วัน)",
            ],
        ];
        $meta = $metaBySection[$section];

        return [
            'module'      => 'care_chart',
            'title'       => $meta['title'],
            'subtitle'    => "ผู้ป่วย: {$fullName} | HN: " . ($patient->hn ?: '-') . " | สังกัด: {$unitName} (ผลัด " . ($patient->intake_batch ?: '-') . ") | ช่วงวันที่: {$rangeText}",
            'filename'    => $meta['filename'],
            'orientation' => 'landscape',
            'printed_at'  => $this->formatThaiDate(now(), true),
            'printed_by'  => auth()->user()->name ?? 'เจ้าหน้าที่',
            'summary'     => [
                ['label' => 'ชื่อ-สกุล', 'value' => $fullName],
                ['label' => 'HN / เลข ปชช.', 'value' => ($patient->hn ?: '-') . ' / ' . ($patient->cid ?: '-')],
                ['label' => 'หน่วยฝึก / ผลัด', 'value' => "{$unitName} (ผลัด " . ($patient->intake_batch ?: '-') . ')'],
                ['label' => $meta['stat_lbl'], 'value' => $meta['stat_val']],
            ],
            'sheets' => $sheets,
        ];
    }

    // =========================================================================
    // 2️⃣ โมดูล: ระบบจัดการนอนห้องพยาบาลหน่วยฝึก & แยกโรงนอน
    // =========================================================================
    private function buildInfirmaryReport(Request $request): array
    {
        $user = auth()->user();
        $batch    = $request->query('intake_batch', 'all');
        $unitId   = $request->query('unit_id', '');
        $location = $request->query('location_type', '');
        $reason   = $request->query('reason_category', '');
        $status   = $request->query('status', 'staying');

        $query = RecruitInfirmaryStay::with(['patient.militaryUnit', 'militaryUnit'])
            ->whereHas('patient');
        $this->applyMedicScope($query, $user);

        if ($batch !== '' && $batch !== 'all') $query->where('intake_batch', $batch);
        if (!empty($unitId)) $query->where('military_unit_id', $unitId);
        if (!empty($location)) {
            $query->where('location_type', $location);
            if ($location === 'unit_infirmary' && empty($reason)) {
                $query->where('reason_category', '!=', 'respiratory');
            }
        }
        if (!empty($reason)) $query->where('reason_category', $reason);
        if ($status !== '' && $status !== 'all') $query->where('status', $status);

        $records = $query->orderBy('intake_batch', 'desc')
            ->orderByRaw('COALESCE(military_unit_id, 9999) ASC')
            ->orderBy('admitted_at', 'desc')
            ->get();

        $reasonLabels = [
            'respiratory'     => 'โรคทางเดินหายใจ / CI',
            'post_admit'      => 'พักฟื้นหลังออก รพ.',
            'musculoskeletal' => 'บาดเจ็บกล้ามเนื้อ/แผล',
            'other'           => 'สาเหตุอื่นๆ',
        ];

        $headers = ['ลำดับ', 'ยศ ชื่อ-นามสกุล', 'เลข ปชช.', 'HN', 'หน่วยฝึก', 'ผลัด', 'สถานที่แยกนอน', 'โซน/เตียง', 'หมวดสาเหตุ', 'อาการ / Dx.', 'คำแนะนำการดูแล', 'วันที่เริ่มพัก', 'จำนวนวัน', 'สถานะ'];
        $rows = [];
        $stayingCnt = 0;
        $unitInfCnt = 0;
        $campIsoCnt = 0;

        foreach ($records as $idx => $stay) {
            $pt = $stay->patient;
            if ($stay->status === 'staying') {
                $stayingCnt++;
                if ($stay->location_type === 'unit_infirmary') $unitInfCnt++;
                else $campIsoCnt++;
            }

            $daysStay = max(1, (int) $stay->admitted_at->copy()->startOfDay()->diffInDays(($stay->discharged_at ?? now())->copy()->startOfDay()) + 1);
            $rows[] = [
                $idx + 1,
                trim(($pt?->rank ?? '') . ($pt?->first_name ?? '') . ' ' . ($pt?->last_name ?? '')),
                $pt?->cid ?: '-',
                $pt?->hn ?: '-',
                $stay->militaryUnit?->short_name ?? $pt?->militaryUnit?->short_name ?? '-',
                $stay->intake_batch ?: ($pt?->intake_batch ?: '-'),
                $stay->location_type === 'unit_infirmary' ? 'ห้องพยาบาลหน่วยฝึก' : 'ฝากนอน รพ.ค่ายฯ',
                $stay->bed_or_zone ?: '-',
                $reasonLabels[$stay->reason_category] ?? 'อื่นๆ',
                $stay->diagnosis_text ?: '-',
                $stay->care_instruction ?: '-',
                $this->formatThaiDate($stay->admitted_at, true),
                $daysStay . ' วัน',
                $stay->status === 'staying' ? 'กำลังแยกนอนพัก' : 'กลับโรงนอนปกติแล้ว',
            ];
        }

        return [
            'module'      => 'infirmary',
            'title'       => 'รายงานทะเบียนพลทหารนอนห้องพยาบาลหน่วยฝึก & แยกโรงนอน',
            'subtitle'    => 'ผลัด: ' . ($batch === 'all' ? 'ทุกผลัด' : $batch) . ' | หน่วยฝึก: ' . $this->resolveUnitLabel($unitId),
            'filename'    => 'รายงานห้องพยาบาลหน่วยฝึก_' . date('Ymd'),
            'orientation' => 'landscape',
            'printed_at'  => $this->formatThaiDate(now(), true),
            'printed_by'  => auth()->user()->name ?? 'เจ้าหน้าที่',
            'summary'     => [
                ['label' => 'รายการทั้งหมดที่ส่งออก', 'value' => count($rows) . ' นาย'],
                ['label' => 'กำลังแยกนอนอยู่', 'value' => $stayingCnt . ' นาย'],
                ['label' => 'ห้องพยาบาลหน่วยฝึก', 'value' => $unitInfCnt . ' นาย'],
                ['label' => 'ฝากนอน รพ.ค่ายฯ', 'value' => $campIsoCnt . ' นาย'],
            ],
            'sheets' => [[
                'name'    => 'ห้องพยาบาลหน่วยฝึก',
                'title'   => 'รายชื่อพลทหารแยกนอนห้องพยาบาลหน่วยฝึก และฝากนอนแยกกัก รพ.ค่ายฯ',
                'headers' => $headers,
                'rows'    => $rows,
                'cid_col' => 2, // Index คอลัมน์เลข ปชช. (0-based)
            ]],
        ];
    }

    // =========================================================================
    // 3️⃣ โมดูล: ระบบการนัดพบแพทย์ของทหารกองประจำการ
    // =========================================================================
    private function buildAppointmentsReport(Request $request): array
    {
        $user = auth()->user();
        $today    = Carbon::today()->format('Y-m-d');
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $batch     = $request->query('intake_batch', 'all');
        $unitId    = $request->query('unit_id', '');
        $hospType  = $request->query('hospital_type', '');
        $patientId = $request->query('patient_id', '');
        $scope     = $request->query('date_scope', !empty($patientId) ? 'all' : 'upcoming');

        $query = RecruitAppointment::with(['patient.militaryUnit', 'militaryUnit'])
            ->whereHas('patient');
        $this->applyMedicScope($query, $user);

        if ($batch !== '' && $batch !== 'all') $query->where('intake_batch', $batch);
        if (!empty($unitId)) $query->where('military_unit_id', $unitId);
        if (!empty($patientId)) $query->where('patient_id', $patientId);
        if (!empty($hospType)) $query->where('hospital_type', $hospType);

        if ($scope === 'today') {
            $query->whereDate('appointment_date', $today);
        } elseif ($scope === 'tomorrow') {
            $query->whereDate('appointment_date', $tomorrow);
        } elseif ($scope === 'upcoming') {
            $query->whereDate('appointment_date', '>=', $today);
        } elseif ($scope === 'past') {
            $query->whereDate('appointment_date', '<', $today);
        }

        $records = $query->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->orderByRaw('COALESCE(military_unit_id, 9999) ASC')
            ->get();

        $statusLabels = [
            'scheduled' => 'รอนัดหมาย',
            'completed' => 'ไปพบแพทย์แล้ว',
            'postponed' => 'เลื่อนนัด',
            'cancelled' => 'ยกเลิกนัด',
        ];

        $headers = ['ลำดับ', 'วันที่นัด', 'เวลา', 'ยศ ชื่อ-นามสกุล', 'เลข ปชช.', 'HN', 'หน่วยฝึก', 'ผลัด', 'สถานพยาบาล', 'คลินิก/แผนก', 'สาเหตุการนัด', 'การเตรียมตัว/หมายเหตุ', 'สถานะ'];
        $rows = [];
        $campCnt = 0;
        $pubCnt = 0;

        foreach ($records as $idx => $app) {
            $pt = $app->patient;
            if ($app->hospital_type === 'public_hospital') $pubCnt++;
            else $campCnt++;

            $hospName = $app->hospital_name ?: ($app->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายกฤษณ์สีวะรา');
            $rows[] = [
                $idx + 1,
                $this->formatThaiDate($app->appointment_date),
                ($app->appointment_time ?: '08:30') . ' น.',
                trim(($pt?->rank ?? '') . ($pt?->first_name ?? '') . ' ' . ($pt?->last_name ?? '')),
                $pt?->cid ?: '-',
                $pt?->hn ?: '-',
                $app->militaryUnit?->short_name ?? $pt?->militaryUnit?->short_name ?? '-',
                $app->intake_batch ?: ($pt?->intake_batch ?: '-'),
                $hospName,
                $app->clinic_name ?: '-',
                $app->app_cause ?: '-',
                $app->note ?: '-',
                $statusLabels[$app->status] ?? $app->status,
            ];
        }

        return [
            'module'      => 'appointments',
            'title'       => 'รายงานตารางนัดพบแพทย์ทหารกองประจำการ',
            'subtitle'    => 'ผลัด: ' . ($batch === 'all' ? 'ทุกผลัด' : $batch) . ' | หน่วยฝึก: ' . $this->resolveUnitLabel($unitId),
            'filename'    => 'รายงานนัดพบแพทย์พลทหาร_' . date('Ymd'),
            'orientation' => 'landscape',
            'printed_at'  => $this->formatThaiDate(now(), true),
            'printed_by'  => auth()->user()->name ?? 'เจ้าหน้าที่',
            'summary'     => [
                ['label' => 'จำนวนนัดหมายทั้งหมด', 'value' => count($rows) . ' รายการ'],
                ['label' => 'นัดที่ รพ.ค่ายกฤษณ์สีวะรา', 'value' => $campCnt . ' รายการ'],
                ['label' => 'นัดส่งต่อ รพ.สาธารณสุข', 'value' => $pubCnt . ' รายการ'],
            ],
            'sheets' => [[
                'name'    => 'ตารางนัดพบแพทย์',
                'title'   => 'บัญชีรายชื่อพลทหารที่มีกำหนดนัดพบแพทย์',
                'headers' => $headers,
                'rows'    => $rows,
                'cid_col' => 4,
            ]],
        ];
    }

    // =========================================================================
    // 4️⃣ โมดูล: ระบบติดตามเอกสารนำปลดทหารกองประจำการ
    // =========================================================================
    private function buildDischargesReport(Request $request): array
    {
        $user = auth()->user();
        $batch   = $request->query('intake_batch', 'all');
        $unitId  = $request->query('unit_id', '');
        $pStatus = $request->query('process_status', $request->query('status', ''));

        $query = MilitaryDischarge::with(['patient.militaryUnit', 'militaryUnit'])
            ->whereHas('patient');
        $this->applyMedicScope($query, $user);

        if ($batch !== '' && $batch !== 'all') $query->where('intake_batch', $batch);
        if (!empty($unitId)) $query->where('military_unit_id', $unitId);
        if (!empty($pStatus) &&$pStatus !== 'all') $query->where('process_status',$pStatus);

        $records =$query->orderBy('intake_batch', 'desc')
            ->orderByRaw('COALESCE(military_unit_id, 9999) ASC')
            ->orderBy('id', 'asc')
            ->get();

        $statusLabels = [
            'preparing'   => 'จัดเตรียมเอกสาร',
            'in_progress' => 'กำลังดำเนินการนำปลด',
            'submitted'   => 'ส่งเอกสารแล้ว / รอผล',
            'completed'   => 'ปลดประจำการแล้ว (ส่ง ศปน. ครบ)',
        ];

        $headers = ['ลำดับ', 'ยศ ชื่อ-นามสกุล', 'เลข ปชช.', 'HN', 'หน่วยฝึก', 'ผลัด', 'ประเภทการนำปลด', 'โรควินิจฉัยนำปลด (Dx.)', 'สถานะเอกสาร', 'วันที่ขึ้นทะเบียน'];
        $rows = [];$physCnt = 0;
        $mentalCnt = 0;
        $doneCnt = 0;

        foreach ($records as $idx =>$dc) {
            $pt =$dc->patient;
            $dxRaw = (string) ($dc->diagnosis_text ?? '');
            $dxLower = mb_strtolower($dxRaw);$isMental = str_contains($dxLower, 'สุขภาพจิต') || str_contains($dxLower, 'จิต') || str_contains($dxLower, 'psych') || str_contains($dxLower, 'depress');

            if ($isMental)$mentalCnt++;
            else $physCnt++;

            if ($dc->process_status === 'completed')$doneCnt++;

            $cleanDx = trim(str_replace(['[ปลดทางสุขภาพจิต]', '[ปลดทางกาย]'], '', $dxRaw));

            $rows[] = [$idx + 1,
                trim(($pt?->rank ?? '') . ($pt?->first_name ?? '') . ' ' . ($pt?->last_name ?? '')),
                $pt?->cid ?: '-',
                $pt?->hn ?: '-',
                $dc->militaryUnit?->short_name ?? $pt?->militaryUnit?->short_name ?? '-',
                $dc->intake_batch ?: ($pt?->intake_batch ?: '-'),
                $isMental ? 'ปลดทางสุขภาพจิต' : 'ปลดทางกาย',
                $cleanDx ?: '-',
                $statusLabels[$dc->process_status] ?? ($dc->process_status ?: 'กำลังดำเนินการ'),
                $this->formatThaiDate($dc->created_at),
            ];
        }

        return [
            'module'      => 'discharges',
            'title'       => 'รายงานติดตามเอกสารนำปลดทหารกองประจำการ',
            'subtitle'    => 'ผลัด: ' . ($batch === 'all' ? 'ทุกผลัด' : $batch) . ' | หน่วยฝึก: ' . $this->resolveUnitLabel($unitId),
            'filename'    => 'รายงานเอกสารนำปลดทหาร_' . date('Ymd'),
            'orientation' => 'landscape',
            'printed_at'  => $this->formatThaiDate(now(), true),
            'printed_by'  => auth()->user()->name ?? 'เจ้าหน้าที่',
            'summary'     => [
                ['label' => 'ยอดนำปลดทั้งหมด', 'value' => count($rows) . ' นาย'],
                ['label' => 'ปลดทางกาย', 'value' => $physCnt . ' นาย'],
                ['label' => 'ปลดทางสุขภาพจิต', 'value' => $mentalCnt . ' นาย'],
                ['label' => 'ปลดประจำการแล้ว', 'value' => $doneCnt . ' นาย'],
            ],
            'sheets' => [[
                'name'    => 'เอกสารนำปลดทหาร',
                'title'   => 'บัญชีรายชื่อติดตามเอกสารนำปลดทหารกองประจำการ',
                'headers' => $headers,
                'rows'    => $rows,
                'cid_col' => 2,
            ]],
        ];
    }
}