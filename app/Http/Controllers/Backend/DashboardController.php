<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\MewsAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $userRoles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        $isAdmin = in_array('admin', $userRoles);
        
        $isExecutive    = $isAdmin || in_array('executive', $userRoles);
        $isER           = $isAdmin || in_array('er_nurse', $userRoles);
        $isMedic        = $isAdmin || in_array('medic', $userRoles);
        $isRegistration = $isAdmin || in_array('registration', $userRoles); 
        $isOPD          = $isAdmin || in_array('opd_nurse', $userRoles);
        $isScreening    = $isAdmin || in_array('screening', $userRoles);

        $requestedView = $request->query('view');

        if ($requestedView === 'executive' && $isExecutive) return $this->executiveDashboard($request);
        if ($requestedView === 'er' && $isER) return $this->erDashboard();
        if ($requestedView === 'screening' && $isScreening) return $this->screeningDashboard();
        if ($requestedView === 'medic' && $isMedic) return $this->medicDashboard();
        if ($requestedView === 'registration' && $isRegistration) return $this->registrationDashboard($request);
        if ($requestedView === 'opd' && $isOPD) return $this->opdDashboard();

        if ($isExecutive) {
            return redirect()->to($request->fullUrlWithQuery(['view' => 'executive']));
        } elseif ($isER) {
            return redirect()->to($request->fullUrlWithQuery(['view' => 'er']));
        } elseif ($isScreening) {
            return redirect()->to($request->fullUrlWithQuery(['view' => 'screening']));
        } elseif ($isRegistration && !$isMedic) {
            return redirect()->to($request->fullUrlWithQuery(['view' => 'registration']));
        } elseif ($isOPD) {
            return redirect()->to($request->fullUrlWithQuery(['view' => 'opd']));
        } else {
            return redirect()->to($request->fullUrlWithQuery(['view' => 'medic']));
        }
    }

    // =================================================================
    // 🛡️ ตัวช่วยตรวจเลขบัตรประชาชน 13 หลักตามสูตร Mod-11
    // =================================================================
    private function isValidThaiCid(?string $cid): bool
    {
        $clean = preg_replace('/\D/', '', (string) $cid);
        if (strlen($clean) !== 13) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $clean[$i] * (13 - $i);
        }
        return ((11 - ($sum % 11)) % 10) === (int) $clean[12];
    }

    // =================================================================
    // 🎨 ตัวช่วยจำแนกหมวดสิทธิการรักษาพยาบาลตามรหัสสีมาตรฐาน KSVR
    // =================================================================
    private function classifyHealthCoverage(?string $pttypeName): string
    {
        $text = trim((string) $pttypeName);
        if ($text === '' || $text === '-') {
            return 'unsynced';
        }
        $n = mb_strtolower($text);
        if (str_contains($n, 'บัตรทอง') || str_contains($n, 'หลักประกันสุขภาพ') || str_contains($n, 'uc') || str_contains($n, 'ถ้วนหน้า')) {
            return 'uc';
        }
        if (str_contains($n, 'ประกันสังคม')) {
            return 'sss';
        }
        if (str_contains($n, 'ข้าราชการพลเรือน') || (str_contains($n, 'ข้าราชการ') && !str_contains($n, 'ทหาร'))) {
            return 'civil';
        }
        if (str_contains($n, 'ครอบครัว')) {
            return 'family';
        }
        if (str_contains($n, 'ทหาร') || str_contains($n, 'กองทัพ') || str_contains($n, 'กลาโหม') || str_contains($n, 'พลฯ')) {
            return 'military';
        }
        return 'other';
    }

    // =================================================================
    // 📇 1. แดชบอร์ดทะเบียน (Registration Dashboard — Full Control Edition)
    // คำนวณทุกสถิติจากฐานข้อมูล KSVR 100% ไม่รบกวน HosXP ตอนเปิดหน้า
    // =================================================================
    private function registrationDashboard(Request $request)
    {
        $selectedBatch = $request->query('batch', 'all');
        $hasDutyStatus = Schema::hasColumn('patients', 'duty_status');
        $hasPttypeName = Schema::hasColumn('patients', 'pttype_name');
        $hasDob        = Schema::hasColumn('patients', 'dob');
        $hasPhone      = Schema::hasColumn('patients', 'phone');

        // 1. ดึงรายการผลัดทั้งหมดที่มีในระบบสำหรับ Dropdown กรองผลัด
        $availableBatches = Patient::whereNotNull('intake_batch')
            ->where('intake_batch', '!=', '')
            ->distinct()
            ->orderByDesc('intake_batch')
            ->pluck('intake_batch')
            ->values();

        // 2. Query หลักสำหรับกำลังพล (กรองตามผลัด และตัดกลุ่มไม่มารายงานตัว/ต้องโทษ/เสียชีวิต ออกจากงานรอซิงค์)
        $basePatientQuery = Patient::query();
        if ($selectedBatch !== 'all' && $selectedBatch !== '') {
            $basePatientQuery->where('intake_batch', $selectedBatch);
        }

        // ยอดกำลังพลทั้งหมดในผลัดที่เลือก (รวมทุกสถานะ)
        $totalPatients = (clone $basePatientQuery)->count();

        // ยอดกลุ่มไม่ได้ประจำการ (ไม่มารายงานตัว / ต้องโทษ / เสียชีวิต)
        $inactiveExcludedCount = 0;
        $activeDutyQuery = clone $basePatientQuery;
        if ($hasDutyStatus) {
            $inactiveExcludedCount = (clone $basePatientQuery)
                ->whereIn('duty_status', ['absent', 'imprisoned', 'deceased'])
                ->count();

            $activeDutyQuery->where(function ($q) {
                $q->whereNull('duty_status')
                  ->orWhereNotIn('duty_status', ['absent', 'imprisoned', 'deceased']);
            });
        }

        // 3. นับยอดมี HN แล้ว vs รอซิงค์ HN (เฉพาะผู้ประจำการปกติ เพื่อไม่ให้มีงานค้างหลอกตา)
        $activeDutyTotal = (clone $activeDutyQuery)->count();
        $unsyncedQuery = (clone $activeDutyQuery)->where(function ($q) {
            $q->whereNull('hn')->orWhere('hn', '')->orWhere('hn', '-');
        });
        $unsyncedPatients = (clone $unsyncedQuery)->count();
        $syncedPatients   = max(0, $activeDutyTotal - $unsyncedPatients);
        $hnSyncPercent    = $activeDutyTotal > 0 ? round(($syncedPatients / $activeDutyTotal) * 100, 1) : 100;

        // 4. ดึงเคส MEWS ที่กำลังเปิดรักษาอยู่ (Pending) และคัดแยก "เคสด่วนที่ยังไม่มี HN"
        $latestMewsIds = MewsAssessment::select(DB::raw('MAX(id) as id'))
            ->groupBy('case_id')
            ->pluck('id');

        $activePatientsList = MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $latestMewsIds)
            ->where('status', 'pending')
            ->whereHas('patient', function ($q) use ($selectedBatch) {
                if ($selectedBatch !== 'all' && $selectedBatch !== '') {
                    $q->where('intake_batch', $selectedBatch);
                }
            })
            ->orderByDesc('total_score')
            ->orderByDesc('updated_at')
            ->get();

        $activeCases = $activePatientsList->count();

        // 🚨 เคสด่วน: กำลังส่งเข้า ER/OPD แต่ยังไม่มี HN (ต้องเปิดบัตรใน HosXP ด่วนที่สุด!)
        $urgentNoHnCases = $activePatientsList->filter(function ($mews) {
            $hn = trim((string) ($mews->patient?->hn ?? ''));
            return $mews->patient && ($hn === '' || $hn === '-');
        })->values();

        // 5. ดึงรายการรอซิงค์ HN สูงสุด 10 รายการล่าสุด (ไม่นับรวมเคสไม่มารายงานตัว/ต้องโทษ/เสียชีวิต)
        $actionNeededPatients = (clone $unsyncedQuery)
            ->with('militaryUnit')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        // 6. ศูนย์ตรวจสอบคุณภาพข้อมูล (Data Quality Audit) + สรุปสิทธิการรักษา (Query เบาเฉพาะคอลัมน์จำเป็น)
        $selectCols = ['id', 'rank', 'first_name', 'last_name', 'cid', 'hn', 'intake_batch', 'military_unit_id', 'company'];
        if ($hasPttypeName) $selectCols[] = 'pttype_name';
        if ($hasDob)        $selectCols[] = 'dob';
        if ($hasPhone)      $selectCols[] = 'phone';

        $auditRecords = (clone $activeDutyQuery)
            ->select($selectCols)
            ->with('militaryUnit:id,short_name,name')
            ->get();

        $invalidCidList     = [];
        $missingContactList = [];
        $missingPttypeCount = 0;
        $cidGroups          = [];

        $coverageSummary = [
            'uc'       => ['label' => 'บัตรทอง (UC)',       'count' => 0, 'badge' => 'bg-amber-100 text-amber-900 border-amber-400',     'bar' => 'bg-amber-500'],
            'sss'      => ['label' => 'ประกันสังคม',        'count' => 0, 'badge' => 'bg-blue-600 text-white border-blue-700',           'bar' => 'bg-blue-600'],
            'civil'    => ['label' => 'ข้าราชการพลเรือน',   'count' => 0, 'badge' => 'bg-sky-100 text-sky-800 border-sky-300',           'bar' => 'bg-sky-400'],
            'military' => ['label' => 'ทหาร',               'count' => 0, 'badge' => 'bg-emerald-800 text-white border-emerald-900',     'bar' => 'bg-emerald-800'],
            'family'   => ['label' => 'ครอบครัว',           'count' => 0, 'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-300','bar' => 'bg-emerald-400'],
            'other'    => ['label' => 'อื่นๆ',              'count' => 0, 'badge' => 'bg-yellow-100 text-yellow-900 border-yellow-300',  'bar' => 'bg-yellow-400'],
            'unsynced' => ['label' => 'ยังไม่ซิงค์สิทธิ',    'count' => 0, 'badge' => 'bg-slate-100 text-slate-600 border-slate-300',     'bar' => 'bg-slate-400'],
        ];

        foreach ($auditRecords as $p) {
            $fullName = trim(($p->rank ?? '') . ($p->first_name ?? '') . ' ' . ($p->last_name ?? ''));
            $unitShort = $p->militaryUnit?->short_name ?? $p->militaryUnit?->name ?? $p->company ?? '-';
            $cleanCid = preg_replace('/\D/', '', (string) ($p->cid ?? ''));
            $hasHn = !empty(trim((string) ($p->hn ?? ''))) && trim((string) $p->hn) !== '-';

            // 6.1 ตรวจ Mod-11
            if (!$this->isValidThaiCid($p->cid)) {
                $invalidCidList[] = [
                    'id'    => $p->id,
                    'name'  => $fullName,
                    'cid'   => $p->cid ?: '(ว่าง)',
                    'hn'    => $p->hn ?: 'ไม่มี HN',
                    'unit'  => $unitShort,
                    'batch' => $p->intake_batch ?: '-',
                ];
            }

            // 6.2 เก็บกลุ่มตรวจเลข ปชช. ซ้ำ
            if ($cleanCid !== '') {
                $cidGroups[$cleanCid][] = [
                    'id'    => $p->id,
                    'name'  => $fullName,
                    'cid'   => $p->cid,
                    'hn'    => $p->hn ?: 'ไม่มี HN',
                    'unit'  => $unitShort,
                    'batch' => $p->intake_batch ?: '-',
                ];
            }

            // 6.3 มี HN แล้วแต่ยังไม่มีสิทธิการรักษา + นับกลุ่มสีสิทธิ
            $pttypeText = $hasPttypeName ? trim((string) ($p->pttype_name ?? '')) : '';
            if ($hasHn && ($pttypeText === '' || $pttypeText === '-')) {
                $missingPttypeCount++;
            }
            $covKey = $this->classifyHealthCoverage($pttypeText);
            $coverageSummary[$covKey]['count']++;

            // 6.4 ตรวจข้อมูลวันเกิดหรือเบอร์โทรว่าง
            $missDob   = $hasDob && empty($p->dob);
            $missPhone = $hasPhone && empty(trim((string) ($p->phone ?? '')));
            if ($missDob || $missPhone) {
                $missingContactList[] = [
                    'id'      => $p->id,
                    'name'    => $fullName,
                    'cid'     => $p->cid ?: '-',
                    'hn'      => $p->hn ?: 'ไม่มี HN',
                    'unit'    => $unitShort,
                    'batch'   => $p->intake_batch ?: '-',
                    'missing' => trim(($missDob ? 'วันเกิด' : '') . ($missDob && $missPhone ? ', ' : '') . ($missPhone ? 'เบอร์โทร' : '')),
                ];
            }
        }

        // คัดเฉพาะเลข ปชช. ที่ซ้ำตั้งแต่ 2 รายการขึ้นไป
        $duplicateCidList = [];
        foreach ($cidGroups as $dupCid => $items) {
            if (count($items) > 1) {
                foreach ($items as $it) {
                    $duplicateCidList[] = $it;
                }
            }
        }

        $dataAudit = [
            'invalid_cid_count'     => count($invalidCidList),
            'invalid_cid_list'      => array_slice($invalidCidList, 0, 100),
            'duplicate_cid_count'   => count($duplicateCidList),
            'duplicate_cid_list'    => array_slice($duplicateCidList, 0, 100),
            'missing_pttype_count'  => $missingPttypeCount,
            'missing_contact_count' => count($missingContactList),
            'missing_contact_list'  => array_slice($missingContactList, 0, 100),
        ];

        return view('backend.dashboards.registration', compact(
            'totalPatients',
            'activeDutyTotal',
            'syncedPatients',
            'unsyncedPatients',
            'hnSyncPercent',
            'inactiveExcludedCount',
            'activeCases',
            'actionNeededPatients',
            'activePatientsList',
            'urgentNoHnCases',
            'availableBatches',
            'selectedBatch',
            'dataAudit',
            'coverageSummary'
        ));
    }

    // =================================================================
    // 🛡️ ฟังก์ชันตัวช่วยตรวจสอบว่าเป็นเคสของ "ห้องฉุกเฉิน (ER)" หรือ "ผู้ป่วยนอก (OPD)"
    // =================================================================
    private function isErCase($mews): bool
    {
        if (!$mews) {
            return false;
        }

        if ($mews->assessment_type === 'er') {
            return true;
        }

        if (in_array($mews->risk_level, ['observe', 'emergency'], true)) {
            return true;
        }

        $rawSyms = is_string($mews->symptoms) ? json_decode($mews->symptoms, true) : ($mews->symptoms ?? []);
        if (is_array($rawSyms)) {
            $filteredSyms = array_filter($rawSyms, fn($v) => !empty(trim((string)$v)));
            if (count($filteredSyms) > 0) {
                return true;
            }
        }

        return false;
    }

    // =================================================================
    // 👨‍⚕️ 2. แดชบอร์ดห้องฉุกเฉิน (ER Dashboard) — แสดงเฉพาะเคส ER 100%
    // =================================================================
    private function erDashboard()
    {
        $today = \Carbon\Carbon::today();
        $latestIds = \App\Models\MewsAssessment::select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
            ->groupBy('case_id')
            ->pluck('id');

        $pendingLatestList = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $latestIds)
            ->where('status', 'pending')
            ->get();

        $incomingCases = $pendingLatestList
            ->filter(fn($item) => $item->assessment_type === 'field' && $this->isErCase($item))
            ->sortBy([
                ['total_score', 'desc'],
                ['created_at', 'asc'],
            ])
            ->values();

        $activeCases = $pendingLatestList
            ->filter(fn($item) => $item->assessment_type === 'er')
            ->sortByDesc('updated_at')
            ->values();

        $completedCasesToday = \App\Models\MewsAssessment::with('patient')
            ->whereIn('id', $latestIds)
            ->whereIn('status', ['completed', 'cancelled'])
            ->whereDate('updated_at', $today)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(fn($item) => $this->isErCase($item))
            ->values();

        $symptomDict = [
            'mi_chest_pain' => 'MI (เจ็บหน้าอก)', 'mi_dyspnea' => 'MI (เหนื่อยหอบ)', 'mi_referred_pain' => 'MI (เจ็บร้าว)',
            'stroke_dizzy' => 'Stroke (เวียนศีรษะ)', 'stroke_vision' => 'Stroke (ตามัว)', 'stroke_facial_weakness' => 'Stroke (หน้าเบี้ยว)', 'stroke_arm_leg_weakness' => 'Stroke (แขนขาอ่อนแรง)', 'stroke_speech' => 'Stroke (พูดไม่ชัด)',
            'heat_confusion' => 'Heat (ซึมสับสน)', 'heat_fatigue' => 'Heat (อ่อนเพลีย)', 'heat_pale' => 'Heat (หน้าซีด)', 'heat_cramp' => 'Heat (ตะคริว)', 'heat_numbness' => 'Heat (ชาปลายมือเท้า)', 'heat_urine' => 'Heat (ปัสสาวะสีเข้ม)',
            'ana_rash' => 'Anaphylaxis (ผื่นลมพิษ)', 'ana_resp' => 'Anaphylaxis (หอบเหนื่อย)', 'ana_sys' => 'Anaphylaxis (ระบบล้มเหลว)', 'ana_gi' => 'Anaphylaxis (ปวดท้องอาเจียน)', 'ana_chest' => 'Anaphylaxis (แน่นหน้าอก)',
            'appendicitis' => 'Appendicitis', 'sepsis_drowsy' => 'Sepsis (ซึม)', 'sepsis_tachypnea' => 'Sepsis (หายใจหอบ)', 'trauma' => 'Trauma (อุบัติเหตุ)'
        ];

        return view('backend.dashboards.er', compact('incomingCases', 'activeCases', 'completedCasesToday', 'symptomDict'));
    }

    // =================================================================
    // 🎒 3. แดชบอร์ดเสนารักษ์ (Medic Dashboard)
    // =================================================================
    private function medicDashboard()
    {
        $today = \Carbon\Carbon::today();
        $user = auth()->user();
        
        $userRoles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        $isAdmin = in_array('admin', $userRoles);

        $myStats = [
            'evaluated_today' => \App\Models\MewsAssessment::where('evaluator_id', $user->id)
                                    ->whereDate('created_at', $today)
                                    ->where('status', '!=', 'cancelled')
                                    ->distinct('patient_id')
                                    ->count('patient_id'),
        ];

        $unitPatientIdsQuery = \App\Models\Patient::query();
        if (!$isAdmin) {
            $unitPatientIdsQuery->where('military_unit_id', $user->military_unit_id)
                                ->where('intake_batch', $user->intake_batch);
        }
        $unitPatientIds = $unitPatientIdsQuery->pluck('id');

        $absoluteLatestIds = \App\Models\MewsAssessment::whereIn('patient_id', $unitPatientIds)
            ->select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
            ->groupBy('patient_id')
            ->pluck('id');

        $myPatients = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $absoluteLatestIds)
            ->where(function($q) use ($today) {
                $q->where('status', 'pending')
                  ->orWhereDate('updated_at', $today);
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach($myPatients as $case) {
            $case->is_completed = ($case->status === 'completed');
            $case->is_cancelled = ($case->status === 'cancelled'); 
            $case->is_accepted_by_er = ($case->assessment_type === 'er' && $case->status === 'pending');
            $case->final_discharge = $case->discharge_type; 
        }

        return view('backend.dashboards.medic', compact('myStats', 'myPatients'));
    }

    // =================================================================
    // 👔 4. แดชบอร์ดผู้บริหาร (Executive Dashboard) - Full Enterprise Edition
    // คำนวณทุกมิติจากฐานข้อมูล KSVR 100% (ไม่รบกวนฐานข้อมูล HosXP)
    // =================================================================
    private function executiveDashboard(Request $request)
    {
        $filterBatch    = $request->query('batch', 'all');
        $filterMode     = $request->query('filter_mode', 'month');
        $filterMonth    = $request->query('month', date('m'));
        $filterYear     = $request->query('year', date('Y'));
        $startDateInput = $request->query('start_date', date('Y-m-d'));
        $endDateInput   = $request->query('end_date', date('Y-m-d'));

        $thaiMonthsShort = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
        ];

        // -----------------------------------------------------------------
        // 1. คำนวณช่วงเวลาปัจจุบันและช่วงก่อนหน้า (สำหรับ % เปรียบเทียบแนวโน้ม)
        // -----------------------------------------------------------------
        if ($filterMode === 'custom') {
            $startDate = Carbon::parse($startDateInput)->startOfDay();
            $endDate   = Carbon::parse($endDateInput)->endOfDay();

            if ($endDate->lt($startDate)) {
                $endDate = $startDate->copy()->endOfDay();
                $endDateInput = $startDateInput;
            }

            $diffDays = $startDate->diffInDays($endDate);
            $prevStartDate = $startDate->copy()->subDays($diffDays + 1)->startOfDay();
            $prevEndDate   = $prevStartDate->copy()->addDays($diffDays)->endOfDay();

            $startThai = $startDate->day . ' ' . $thaiMonthsShort[$startDate->month] . ' ' . ($startDate->year + 543);
            $endThai   = $endDate->day . ' ' . $thaiMonthsShort[$endDate->month] . ' ' . ($endDate->year + 543);

            $periodText = ($startDateInput === $endDateInput)
                ? '(วันที่ ' . $startThai . ')'
                : '(ช่วงวันที่ ' . $startThai . ' - ' . $endThai . ')';
            $trendText  = 'ตามช่วงเวลาที่กำหนด';
            $badgeText  = ($startDateInput === $endDateInput)
                ? 'ประจำวันที่ ' . $startThai
                : 'ช่วงวันที่ ' . $startThai . ' - ' . $endThai;
        } elseif ($filterMode === 'year') {
            $startDate     = Carbon::createFromDate($filterYear, 1, 1)->startOfDay();
            $endDate       = Carbon::createFromDate($filterYear, 12, 31)->endOfDay();
            $prevStartDate = $startDate->copy()->subYear();
            $prevEndDate   = $endDate->copy()->subYear();

            $periodText = '(ตลอดทั้งปี ' . ($filterYear + 543) . ')';
            $trendText  = 'ตลอดทั้งปี ' . ($filterYear + 543);
            $badgeText  = 'ตลอดทั้งปี ' . ($filterYear + 543);
        } else {
            $startDate     = Carbon::createFromDate($filterYear, $filterMonth, 1)->startOfMonth();
            $endDate       = $startDate->copy()->endOfMonth();
            $prevStartDate = $startDate->copy()->subMonth()->startOfMonth();
            $prevEndDate   = $startDate->copy()->subMonth()->endOfMonth();

            $monthName  = $startDate->copy()->locale('th')->translatedFormat('F');
            $periodText = '(ในรอบเดือน' . $monthName . ')';
            $trendText  = 'ตลอดทั้งเดือน' . $monthName;
            $badgeText  = 'ประจำเดือน ' . $monthName . ' ' . ($filterYear + 543);
        }

        // Closure ตัวกรองฐานข้อมูลกลางสำหรับตาราง mews_assessments
        $applyBaseFilters = function ($query) use ($startDate, $endDate, $filterBatch) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                  ->where('status', '!=', 'cancelled');

            if ($filterBatch !== 'all') {
                $query->whereHas('patient', function ($q) use ($filterBatch) {
                    $q->where('intake_batch', $filterBatch);
                });
            }
        };

        $applyPrevFilters = function ($query) use ($prevStartDate, $prevEndDate, $filterBatch) {
            $query->whereBetween('created_at', [$prevStartDate, $prevEndDate])
                  ->where('status', '!=', 'cancelled');

            if ($filterBatch !== 'all') {
                $query->whereHas('patient', function ($q) use ($filterBatch) {
                    $q->where('intake_batch', $filterBatch);
                });
            }
        };

        // -----------------------------------------------------------------
        // 2. สรุปยอดปฏิบัติการ + ยอด Admit / Refer แบบซิงค์ตรงกับรายงานประจำวัน
        // -----------------------------------------------------------------
        $patientsPeriod     = MewsAssessment::where($applyBaseFilters)->distinct('patient_id')->count('patient_id');
        $patientsPrevPeriod = MewsAssessment::where($applyPrevFilters)->distinct('patient_id')->count('patient_id');
        $growthPercent      = $patientsPrevPeriod > 0
            ? round((($patientsPeriod - $patientsPrevPeriod) / $patientsPrevPeriod) * 100, 1)
            : ($patientsPeriod > 0 ? 100 : 0);

        // 2.1 ดึง patient_id ที่ Admit/Refer จาก MEWS ในช่วงเวลาที่เลือก
        $mewsAdmitIds = MewsAssessment::where($applyBaseFilters)
            ->whereIn('discharge_type', ['admitted', 'refer'])
            ->distinct()
            ->pluck('patient_id')
            ->all();

        // 2.2 ดึงรายการนอน รพ. (other_ipd) จากรายงานประจำวันในช่วงเวลาเดียวกัน (รวมเคสที่บันทึกมือ)
        $morbAdmitRows = collect();
        if (class_exists(\App\Models\RecruitMorbidity::class) && Schema::hasTable('recruit_morbidities')) {
            $morbAdmitQuery = \App\Models\RecruitMorbidity::with(['patient.militaryUnit', 'militaryUnit'])
                ->where('category', 'other_ipd')
                ->whereBetween('onset_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($filterBatch !== 'all') {
                $morbAdmitQuery->where(function ($q) use ($filterBatch) {
                    $q->where('intake_batch', $filterBatch)
                      ->orWhereHas('patient', fn ($pq) => $pq->where('intake_batch', $filterBatch));
                });
            }

            $morbAdmitRows = $morbAdmitQuery->orderByDesc('onset_date')->get();
        }

        // รวมรายชื่อแบบไม่ซ้ำคน (Unique Patients) และนับจำนวนครั้ง (Cases)
        $allAdmitPatientIds = collect($mewsAdmitIds)
            ->merge($morbAdmitRows->pluck('patient_id'))
            ->filter()
            ->unique()
            ->values();

        $stats = [
            'patients_today'    => $patientsPeriod,
            'growth_percent'    => $growthPercent,
            'assessments_today' => MewsAssessment::where($applyBaseFilters)->count(),
            'critical_today'    => MewsAssessment::where($applyBaseFilters)->where('risk_level', 'emergency')->distinct('patient_id')->count('patient_id'),
            'admit_refer'       => $allAdmitPatientIds->count(),
            'admit_refer_cases' => max($allAdmitPatientIds->count(), $morbAdmitRows->count()),
        ];

        // 2.3 รายละเอียดรายชื่อสำหรับ Modal เมื่อผู้บริหารคลิกการ์ด "Admit รพ. หรือ ส่งต่อ"
        $admitReferDetails = [];
        if ($allAdmitPatientIds->isNotEmpty()) {
            $latestAdmitMewsByPt = MewsAssessment::where($applyBaseFilters)
                ->whereIn('patient_id', $allAdmitPatientIds)
                ->whereIn('discharge_type', ['admitted', 'refer'])
                ->orderByDesc('id')
                ->get()
                ->unique('patient_id')
                ->keyBy('patient_id');

            $morbByPt = $morbAdmitRows->groupBy('patient_id');
            $admitPatientsMap = Patient::with('militaryUnit')
                ->whereIn('id', $allAdmitPatientIds)
                ->get()
                ->keyBy('id');

            foreach ($allAdmitPatientIds as $pid) {
                $p = $admitPatientsMap->get($pid);
                if (!$p) {
                    continue;
                }

                $mRec       = $latestAdmitMewsByPt->get($pid);
                $rGroup     = $morbByPt->get($pid, collect());
                $latestMorb = $rGroup->firstWhere('status', 'treating') ?? $rGroup->first();

                $isPublic = ($latestMorb && $latestMorb->sub_type === 'public_hospital')
                    || ($mRec && $mRec->discharge_type === 'refer');

                $isActiveNow = ($latestMorb && $latestMorb->status === 'treating')
                    || ($mRec && $mRec->status === 'completed' && empty($mRec->returned_to_unit_at) && !$latestMorb);

                $morbOnset = !empty($latestMorb?->onset_date) ? Carbon::parse($latestMorb->onset_date) : null;
                $sortDate  = $morbOnset
                    ? $morbOnset->format('Y-m-d')
                    : ($mRec?->created_at?->format('Y-m-d') ?? '0000-00-00');

                $admitReferDetails[] = [
                    'patient_id'  => $p->id,
                    'mews_id'     => $mRec?->id,
                    'full_name'   => trim(($p->rank ?? '') . ($p->first_name ?? '') . ' ' . ($p->last_name ?? '')),
                    'hn'          => $p->hn ?: 'ยังไม่มี HN',
                    'unit'        => $p->militaryUnit->short_name ?? $p->militaryUnit->name ?? $p->company ?? 'ไม่ระบุหน่วย',
                    'batch'       => $p->intake_batch ?: '-',
                    'hosp_label'  => $isPublic ? 'ส่งต่อ รพ.สาธารณสุข' : 'Admit รพ.ค่ายฯ',
                    'is_public'   => $isPublic,
                    'is_active'   => $isActiveNow,
                    'dx'          => $latestMorb?->diagnosis_text ?: ($mRec?->discharge_details ?: 'รอระบุ Dx.'),
                    'admit_count' => max(1, $rGroup->count()),
                    'source'      => ($mRec && $latestMorb)
                        ? 'MEWS + รายงานประจำวัน'
                        : ($mRec ? 'ใบประเมิน MEWS' : 'บันทึกตรงในรายงานประจำวัน'),
                    'date_text'   => $morbOnset
                        ? $morbOnset->copy()->addYears(543)->format('d/m/Y')
                        : ($mRec?->created_at?->copy()->addYears(543)->format('d/m/Y') ?? '-'),
                    '_sort_date'  => $sortDate,
                ];
            }

            // เรียงเคสที่ "กำลังนอน รพ." ขึ้นด้านบน ตามด้วยวันที่ล่าสุด
            usort($admitReferDetails, function ($a, $b) {
                if ($a['is_active'] !== $b['is_active']) {
                    return $b['is_active'] <=> $a['is_active'];
                }
                return strcmp($b['_sort_date'], $a['_sort_date']);
            });
        }

        // -----------------------------------------------------------------
        // 3. สถิติการจำหน่าย & สัดส่วนความเสี่ยง (Donut Chart)
        // -----------------------------------------------------------------
        $dischargeStats = MewsAssessment::where($applyBaseFilters)
            ->where('status', 'completed')
            ->select('discharge_type', DB::raw('count(DISTINCT patient_id) as total'))
            ->groupBy('discharge_type')
            ->pluck('total', 'discharge_type')
            ->toArray();

        $riskLevels = ['normal' => 0, 'observe' => 0, 'emergency' => 0];
        $latestAssessmentsIds = MewsAssessment::where($applyBaseFilters)
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('patient_id')
            ->pluck('id');

        $latestAssessments = MewsAssessment::whereIn('id', $latestAssessmentsIds)->get();
        foreach ($latestAssessments as $assessment) {
            if (array_key_exists($assessment->risk_level, $riskLevels)) {
                $riskLevels[$assessment->risk_level]++;
            }
        }

        // -----------------------------------------------------------------
        // 4. 5 อันดับอาการฉุกเฉินสะสม (Top 5 Symptoms Bar Chart)
        // -----------------------------------------------------------------
        $allAssessments = MewsAssessment::where($applyBaseFilters)->whereNotNull('symptoms')->get();

        $symptomPatients = [];
        foreach ($allAssessments as $mews) {
            $syms = is_string($mews->symptoms) ? json_decode($mews->symptoms, true) : $mews->symptoms;
            if (is_array($syms)) {
                foreach ($syms as $sym) {
                    if (!empty(trim((string) $sym))) {
                        $symptomPatients[$sym][] = $mews->patient_id;
                    }
                }
            }
        }

        $symptomCounts = [];
        foreach ($symptomPatients as $sym => $patientIds) {
            $symptomCounts[$sym] = count(array_unique($patientIds));
        }

        $top5Raw = [];
        if (!empty($symptomCounts)) {
            arsort($symptomCounts);
            $top5Raw = array_slice($symptomCounts, 0, 5, true);
        }

        $dict = [
            'mi_chest_pain'           => 'MI (เจ็บหน้าอก)',
            'mi_dyspnea'              => 'MI (เหนื่อยหอบ)',
            'mi_referred_pain'        => 'MI (เจ็บร้าว)',
            'stroke_dizzy'            => 'Stroke (เวียนศีรษะ)',
            'stroke_vision'           => 'Stroke (ตามัว)',
            'stroke_facial_weakness'  => 'Stroke (หน้าเบี้ยว)',
            'stroke_arm_leg_weakness' => 'Stroke (แขนขาอ่อนแรง)',
            'stroke_speech'           => 'Stroke (พูดไม่ชัด)',
            'heat_confusion'          => 'Heat (ซึมสับสน)',
            'heat_fatigue'            => 'Heat (อ่อนเพลีย)',
            'heat_pale'               => 'Heat (หน้าซีด)',
            'heat_cramp'              => 'Heat (ตะคริว)',
            'heat_numbness'           => 'Heat (ชาปลายมือเท้า)',
            'heat_urine'              => 'Heat (ปัสสาวะสีเข้ม)',
            'ana_rash'                => 'Anaphylaxis (ผื่นลมพิษ)',
            'ana_resp'                => 'Anaphylaxis (หอบเหนื่อย)',
            'ana_sys'                 => 'Anaphylaxis (ระบบล้มเหลว)',
            'ana_gi'                  => 'Anaphylaxis (ปวดท้องอาเจียน)',
            'ana_chest'               => 'Anaphylaxis (แน่นหน้าอก)',
            'appendicitis'            => 'Appendicitis',
            'sepsis_drowsy'           => 'Sepsis (ซึม)',
            'sepsis_tachypnea'        => 'Sepsis (หายใจหอบ)',
            'trauma'                  => 'Trauma (อุบัติเหตุ)',
        ];

        $top5Labels = [];
        $top5Data   = [];
        foreach ($top5Raw as $key => $count) {
            $top5Labels[] = $dict[$key] ?? $key;
            $top5Data[]   = $count;
        }

        // -----------------------------------------------------------------
        // 5. กราฟเส้นแนวโน้มผู้ป่วย (Trend Area Chart)
        // -----------------------------------------------------------------
        $trendLabels = [];
        $trendData   = [];
        $diffDays    = $startDate->diffInDays($endDate);

        if ($filterMode === 'year' || $diffDays > 60) {
            $trendRaw = MewsAssessment::where($applyBaseFilters)
                ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month_year'), DB::raw('count(DISTINCT patient_id) as total'))
                ->groupBy('month_year')
                ->pluck('total', 'month_year')
                ->toArray();

            $current = $startDate->copy()->startOfMonth();
            while ($current->lte($endDate)) {
                $key = $current->format('Y-m');
                $trendLabels[] = $thaiMonthsShort[$current->month] . ' ' . substr((string) ($current->year + 543), 2);
                $trendData[]   = $trendRaw[$key] ?? 0;
                $current->addMonth();
            }
        } elseif ($filterMode === 'month') {
            $trendRaw = MewsAssessment::where($applyBaseFilters)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(DISTINCT patient_id) as total'))
                ->groupBy('date')
                ->pluck('total', 'date')
                ->toArray();

            for ($i = 1; $i <= $endDate->daysInMonth; $i++) {
                $dateStr = $startDate->copy()->day($i)->format('Y-m-d');
                $trendLabels[] = $i;
                $trendData[]   = $trendRaw[$dateStr] ?? 0;
            }
        } else {
            $trendRaw = MewsAssessment::where($applyBaseFilters)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(DISTINCT patient_id) as total'))
                ->groupBy('date')
                ->pluck('total', 'date')
                ->toArray();

            $current = $startDate->copy()->startOfDay();
            while ($current->lte($endDate)) {
                $key = $current->format('Y-m-d');
                $trendLabels[] = $current->day . ' ' . $thaiMonthsShort[$current->month];
                $trendData[]   = $trendRaw[$key] ?? 0;
                $current->addDay();
            }
        }

        // -----------------------------------------------------------------
        // 6. Top 5 กองร้อย/หน่วยฝึกที่มีผู้ป่วยสูงสุด (จับคู่ชื่อหน่วยใน PHP ปลอดปัญหา Collation/GROUP BY 100%)
        // -----------------------------------------------------------------
        $unitNameMap = Schema::hasTable('military_units')
            ? DB::table('military_units')->get(['id', 'short_name', 'name'])->keyBy('id')
            : collect();

        $topUnitsRaw = DB::table('mews_assessments')
            ->join('patients', 'mews_assessments.patient_id', '=', 'patients.id')
            ->whereBetween('mews_assessments.created_at', [$startDate, $endDate])
            ->where('mews_assessments.status', '!=', 'cancelled')
            ->when($filterBatch !== 'all', fn ($q) => $q->where('patients.intake_batch', $filterBatch))
            ->select('patients.id as patient_id', 'patients.military_unit_id', 'patients.company')
            ->distinct()
            ->get();

        $unitPatientSets = [];
        foreach ($topUnitsRaw as $row) {
            $uObj  = $row->military_unit_id ? $unitNameMap->get($row->military_unit_id) : null;
            $label = trim((string) ($uObj->short_name ?? $uObj->name ?? $row->company ?? ''));
            if ($label === '') {
                continue;
            }
            $unitPatientSets[$label][$row->patient_id] = true;
        }

        $topUnits = collect($unitPatientSets)
            ->map(fn ($ptSet, $label) => (object) ['company' => $label, 'total' => count($ptSet)])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        // -----------------------------------------------------------------
        // 7. จำแนกตามระดับความรุนแรง MEWS (สีเขียว / สีเหลือง / สีแดง) + Modal รายชื่อ
        // -----------------------------------------------------------------
        $mewsLevelsToday = MewsAssessment::where($applyBaseFilters)
            ->select('risk_level', DB::raw('count(DISTINCT patient_id) as total'))
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level')
            ->toArray();

        $executivePatientDetails = ['normal' => [], 'observe' => [], 'emergency' => []];

        foreach (['normal', 'observe', 'emergency'] as $targetLevel) {
            $levelLatestIds = MewsAssessment::where($applyBaseFilters)
                ->where('risk_level', $targetLevel)
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('patient_id')
                ->pluck('id');

            $levelCases = MewsAssessment::with(['patient.militaryUnit'])
                ->whereIn('id', $levelLatestIds)
                ->orderByDesc('total_score')
                ->orderByDesc('updated_at')
                ->get();

            foreach ($levelCases as $c) {
                $p = $c->patient;
                if (!$p) {
                    continue;
                }

                $unitName  = $p->militaryUnit->short_name ?? $p->militaryUnit->name ?? $p->company ?? 'ไม่ระบุหน่วย';
                $battalion = $p->militaryUnit->battalion ?? $p->battalion ?? '';
                if ($battalion) {
                    $unitName .= " ({$battalion})";
                }

                $rawSyms  = is_string($c->symptoms) ? json_decode($c->symptoms, true) : ($c->symptoms ?? []);
                $symNames = [];
                if (is_array($rawSyms)) {
                    foreach ($rawSyms as $sKey) {
                        if (!empty(trim((string) $sKey))) {
                            $symNames[] = $dict[$sKey] ?? $sKey;
                        }
                    }
                }

                $hasReturnedExec = !empty($c->returned_to_unit_at);
                if ($c->status === 'completed') {
                    if ($c->discharge_type === 'admitted') {
                        if ($hasReturnedExec) {
                            $dispositionText  = 'Admit รพ.ค่ายฯ (จำหน่ายกลับหน่วยฝึกแล้ว)';
                            $dispositionBadge = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                            $dispositionIcon  = 'fa-house-chimney-check';
                        } else {
                            $dispositionText  = 'Admit รพ.ค่ายฯ' . ($c->discharge_details ? " ({$c->discharge_details})" : '') . ' [กำลังนอน รพ.]';
                            $dispositionBadge = 'bg-amber-100 text-amber-800 border-amber-300';
                            $dispositionIcon  = 'fa-bed-pulse';
                        }
                    } elseif ($c->discharge_type === 'refer') {
                        if ($hasReturnedExec) {
                            $dispositionText  = 'ส่งต่อ Refer (จำหน่ายกลับหน่วยฝึกแล้ว)';
                            $dispositionBadge = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                            $dispositionIcon  = 'fa-house-chimney-check';
                        } else {
                            $dispositionText  = 'ส่งต่อ รพ.อื่น' . ($c->discharge_details ? " ({$c->discharge_details})" : '') . ' [ยังไม่กลับหน่วย]';
                            $dispositionBadge = 'bg-blue-100 text-blue-800 border-blue-300';
                            $dispositionIcon  = 'fa-truck-medical';
                        }
                    } else {
                        $dispositionText  = 'จำหน่ายกลับหน่วยฝึก (อาการดีขึ้น)';
                        $dispositionBadge = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                        $dispositionIcon  = 'fa-house-medical-circle-check';
                    }
                } else {
                    $isRoutedToER = ($c->assessment_type === 'er') || in_array($c->risk_level, ['observe', 'emergency'], true) || !empty($symNames);
                    $dispositionText  = ($c->assessment_type === 'er')
                        ? 'กำลังรักษาและติดตามอาการในห้อง ER'
                        : ($isRoutedToER ? 'รอรับตัวเข้าห้องฉุกเฉิน (ER)' : 'รอตรวจแผนกผู้ป่วยนอก (OPD)');
                    $dispositionBadge = !$isRoutedToER
                        ? 'bg-teal-100 text-teal-800 border-teal-200'
                        : 'bg-rose-100 text-rose-700 border-rose-300 animate-pulse';
                    $dispositionIcon  = !$isRoutedToER ? 'fa-stethoscope' : 'fa-heart-pulse';
                }

                $executivePatientDetails[$targetLevel][] = [
                    'mews_id'           => $c->id,
                    'patient_id'        => $p->id,
                    'full_name'         => ($p->rank ?? '') . ($p->first_name ?? '') . ' ' . ($p->last_name ?? ''),
                    'hn'                => $p->hn ?: 'ยังไม่มี HN',
                    'unit'              => $unitName,
                    'batch'             => $p->intake_batch ?: '-',
                    'score'             => $c->total_score,
                    'vitals'            => 'BP: ' . ($c->systolic_bp ?? '-') . ' | HR: ' . ($c->heart_rate ?? '-') . ' | T: ' . ($c->temperature ?? '-') . '°C | O2: ' . ($c->o2sat ?? '-') . '%',
                    'symptoms'          => $symNames,
                    'disposition_text'  => $dispositionText,
                    'disposition_badge' => $dispositionBadge,
                    'disposition_icon'  => $dispositionIcon,
                    'updated_time'      => $c->updated_at ? $c->updated_at->copy()->addYears(543)->format('d/m/Y H:i น.') : '-',
                ];
            }
        }

        // -----------------------------------------------------------------
        // 8. เรดาร์เฝ้าระวังโรคติดต่อและการระบาดในหน่วยฝึก (ในรอบ 48 ชม. — ปลอด Collation Error)
        // -----------------------------------------------------------------
        $outbreakRaw = DB::table('mews_assessments')
            ->join('patients', 'mews_assessments.patient_id', '=', 'patients.id')
            ->leftJoin('recruit_screenings', 'patients.id', '=', 'recruit_screenings.patient_id')
            ->where('mews_assessments.created_at', '>=', now()->subHours(48))
            ->where('mews_assessments.status', '!=', 'cancelled')
            ->when($filterBatch !== 'all', fn ($q) => $q->where('patients.intake_batch', $filterBatch))
            ->where(function ($q) {
                $q->where('mews_assessments.temperature', '>=', 38.0)
                  ->orWhere('recruit_screenings.color_group', 'red');
            })
            ->select(
                'patients.id as patient_id',
                'patients.military_unit_id',
                'patients.company',
                'patients.intake_batch',
                'mews_assessments.temperature',
                'recruit_screenings.color_group'
            )
            ->get();

        $outbreakBuckets = [];
        foreach ($outbreakRaw as $row) {
            $uObj  = $row->military_unit_id ? $unitNameMap->get($row->military_unit_id) : null;
            $label = trim((string) ($uObj->short_name ?? $uObj->name ?? $row->company ?? ''));
            if ($label === '') {
                continue;
            }
            $batchKey  = (string) ($row->intake_batch ?? '-');
            $bucketKey = $label . '||' . $batchKey;

            if (!isset($outbreakBuckets[$bucketKey])) {
                $outbreakBuckets[$bucketKey] = [
                    'company'      => $label,
                    'intake_batch' => $row->intake_batch,
                    'fever_pts'    => [],
                    'red_pts'      => [],
                ];
            }
            if ((float) ($row->temperature ?? 0) >= 38.0) {
                $outbreakBuckets[$bucketKey]['fever_pts'][$row->patient_id] = true;
            }
            if (($row->color_group ?? '') === 'red') {
                $outbreakBuckets[$bucketKey]['red_pts'][$row->patient_id] = true;
            }
        }

        $outbreakUnits = collect($outbreakBuckets)
            ->map(fn ($b) => (object) [
                'company'          => $b['company'],
                'intake_batch'     => $b['intake_batch'],
                'high_fever_count' => count($b['fever_pts']),
                'red_group_count'  => count($b['red_pts']),
            ])
            ->filter(fn ($item) => $item->high_fever_count >= 2 || $item->red_group_count >= 1)
            ->sortByDesc('high_fever_count')
            ->values();

        return view('backend.dashboards.executive', compact(
            'stats',
            'dischargeStats',
            'riskLevels',
            'top5Labels',
            'top5Data',
            'trendLabels',
            'trendData',
            'topUnits',
            'mewsLevelsToday',
            'filterMode',
            'filterMonth',
            'filterYear',
            'filterBatch',
            'startDateInput',
            'endDateInput',
            'periodText',
            'trendText',
            'badgeText',
            'executivePatientDetails',
            'admitReferDetails',
            'outbreakUnits'
        ));
    }

    // =================================================================
    // 🩺 5. แดชบอร์ดผู้ป่วยนอก (OPD Dashboard) — แยกคิวรอตรวจและประวัติจำหน่าย OPD
    // =================================================================
    private function opdDashboard()
    {
        $today = \Carbon\Carbon::today();
        $latestIds = \App\Models\MewsAssessment::select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
            ->groupBy('case_id')
            ->pluck('id');

        $opdQueue = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $latestIds)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get()
            ->filter(fn($item) => !$this->isErCase($item))
            ->values();

        $completedOpdToday = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $latestIds)
            ->whereIn('status', ['completed', 'cancelled'])
            ->whereDate('updated_at', $today)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(fn($item) => !$this->isErCase($item))
            ->values();

        return view('backend.dashboards.opd', compact('opdQueue', 'completedOpdToday'));
    }

    // =================================================================
    // 📋 6. แดชบอร์ดจุดคัดกรอง (Screening / Triage Dashboard)
    // เรียงลำดับตามคะแนน MEWS สูงสุดขึ้นลำดับแรกเสมอ!
    // =================================================================
    private function screeningDashboard()
    {
        $today = \Carbon\Carbon::today();
        $latestIds = \App\Models\MewsAssessment::select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
            ->groupBy('case_id')
            ->pluck('id');

        $screeningQueue = \App\Models\MewsAssessment::with(['patient.militaryUnit', 'evaluator'])
            ->whereIn('id', $latestIds)
            ->where('status', 'pending')
            ->get()
            ->map(function ($item) {
                $rawSyms = is_string($item->symptoms) ? json_decode($item->symptoms, true) : ($item->symptoms ?? []);
                $cleanSyms = is_array($rawSyms) ? array_values(array_filter($rawSyms, fn($v) => !empty(trim((string)$v)))) : [];
                $item->parsed_symptoms = $cleanSyms;
                $item->has_emergency_symptom = count($cleanSyms) > 0;
                $item->assessment_round = \App\Models\MewsAssessment::where('case_id', $item->case_id)->count();
                return $item;
            })
            ->sort(function ($a, $b) {
                if ((int)$a->total_score !== (int)$b->total_score) {
                    return (int)$b->total_score <=> (int)$a->total_score;
                }
                if ($a->has_emergency_symptom !== $b->has_emergency_symptom) {
                    return $b->has_emergency_symptom <=> $a->has_emergency_symptom;
                }
                return $a->created_at <=> $b->created_at;
            })
            ->values();

        $stats = [
            'total_pending' => $screeningQueue->count(),
            'emergency'     => $screeningQueue->filter(fn($c) => $c->risk_level === 'emergency' || $c->has_emergency_symptom)->count(),
            'observe'       => $screeningQueue->filter(fn($c) => $c->risk_level === 'observe' && !$c->has_emergency_symptom)->count(),
            'normal'        => $screeningQueue->filter(fn($c) => $c->risk_level === 'normal' && !$c->has_emergency_symptom)->count(),
        ];

        $completedToday = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $latestIds)
            ->whereIn('status', ['completed', 'cancelled'])
            ->whereDate('updated_at', $today)
            ->orderByDesc('total_score')
            ->orderByDesc('updated_at')
            ->get();

        $symptomDict = [
            'mi_chest_pain' => 'MI (เจ็บหน้าอก)', 'mi_dyspnea' => 'MI (เหนื่อยหอบ)', 'mi_referred_pain' => 'MI (เจ็บร้าว)',
            'stroke_dizzy' => 'Stroke (เวียนศีรษะ)', 'stroke_vision' => 'Stroke (ตามัว)', 'stroke_facial_weakness' => 'Stroke (หน้าเบี้ยว)',
            'stroke_arm_leg_weakness' => 'Stroke (แขนขาอ่อนแรง)', 'stroke_speech' => 'Stroke (พูดไม่ชัด)',
            'heat_confusion' => 'Heat (ซึมสับสน)', 'heat_fatigue' => 'Heat (อ่อนเพลีย)', 'heat_pale' => 'Heat (หน้าซีด)',
            'heat_cramp' => 'Heat (ตะคริว)', 'heat_numbness' => 'Heat (ชาปลายมือเท้า)', 'heat_urine' => 'Heat (ปัสสาวะสีเข้ม)',
            'ana_rash' => 'Anaphylaxis (ผื่นลมพิษ)', 'ana_resp' => 'Anaphylaxis (หอบเหนื่อย)', 'ana_sys' => 'Anaphylaxis (ระบบล้มเหลว)',
            'ana_gi' => 'Anaphylaxis (ปวดท้องอาเจียน)', 'ana_chest' => 'Anaphylaxis (แน่นหน้าอก)',
            'appendicitis' => 'Appendicitis', 'sepsis_drowsy' => 'Sepsis (ซึม)', 'sepsis_tachypnea' => 'Sepsis (หายใจหอบ)', 'trauma' => 'Trauma (อุบัติเหตุ)'
        ];

        return view('backend.dashboards.screening', compact('screeningQueue', 'stats', 'completedToday', 'symptomDict'));
    }
}