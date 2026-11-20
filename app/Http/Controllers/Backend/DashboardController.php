<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\MewsAssessment;
use Illuminate\Support\Facades\DB;
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
        if ($requestedView === 'registration' && $isRegistration) return $this->registrationDashboard();
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
    // 📇 1. แดชบอร์ดทะเบียน (Registration Dashboard)
    // =================================================================
    private function registrationDashboard()
    {
        $totalPatients = \App\Models\Patient::count();
        
        // 🌟 นับเฉพาะคนที่ยังไม่มีรหัส HN (NULL, ค่าว่าง, หรือ '-')
        $unsyncedQuery = \App\Models\Patient::where(function($q) {
            $q->whereNull('hn')->orWhere('hn', '')->orWhere('hn', '-');
        });
        $unsyncedPatients = (clone $unsyncedQuery)->count();

        // 1. หา ID ของใบล่าสุดของแต่ละแฟ้มประวัติ (Case)
        $latestMewsIds = \App\Models\MewsAssessment::select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
            ->groupBy('case_id')
            ->pluck('id');

        // 2. ดึงเฉพาะเคสที่ "ใบล่าสุด" มีสถานะเป็น pending พร้อมดึงข้อมูลคนไข้มาด้วย
        $activePatientsList = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $latestMewsIds)
            ->where('status', 'pending')
            ->orderBy('updated_at', 'desc')
            ->get();

        $activeCases = $activePatientsList->count();

        // 🌟 3. ดึง 5 รายการที่ยังไม่มี HN พร้อม Eager Load หน่วยทหาร
        $actionNeededPatients = (clone $unsyncedQuery)
            ->with('militaryUnit')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('backend.dashboards.registration', compact(
            'totalPatients', 'unsyncedPatients', 'activeCases', 'actionNeededPatients', 'activePatientsList'
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

        // 1. หากประเมินหรือรับเข้าห้องฉุกเฉินแล้ว (assessment_type === 'er') ถือเป็นเคส ER
        if ($mews->assessment_type === 'er') {
            return true;
        }

        // 2. หากคะแนนความเสี่ยงเป็น สีเหลือง (observe) หรือ สีแดง (emergency) ถือเป็นเคส ER
        if (in_array($mews->risk_level, ['observe', 'emergency'], true)) {
            return true;
        }

        // 3. หากเป็นสีเขียว (normal) แต่มีการติ๊กอาการฉุกเฉินเฉพาะโรค ถือเป็นเคส ER
        $rawSyms = is_string($mews->symptoms) ? json_decode($mews->symptoms, true) : ($mews->symptoms ?? []);
        if (is_array($rawSyms)) {
            $filteredSyms = array_filter($rawSyms, fn($v) => !empty(trim((string)$v)));
            if (count($filteredSyms) > 0) {
                return true;
            }
        }

        // หากไม่เข้าเกณฑ์ข้างต้นเลย (คือ ประเมินจากหน่วยฝึก + สีเขียวปกติ + ไม่มีอาการฉุกเฉิน) ถือเป็นเคส OPD 100%
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

        // ดึงใบประเมินล่าสุดของแต่ละแฟ้มที่ยัง Pending อยู่
        $pendingLatestList = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $latestIds)
            ->where('status', 'pending')
            ->get();

        // 🔴 1. คิวรอรับเข้า ER (ส่งมาจากภาคสนาม และต้องเข้าเกณฑ์ ER เท่านั้น ห้ามเคส OPD หลุดเข้ามา)
        $incomingCases = $pendingLatestList
            ->filter(fn($item) => $item->assessment_type === 'field' && $this->isErCase($item))
            ->sortBy([
                ['total_score', 'desc'],
                ['created_at', 'asc'],
            ])
            ->values();

        // 🟡 2. กำลังติดตามอาการในห้องฉุกเฉิน (Active in ER)
        $activeCases = $pendingLatestList
            ->filter(fn($item) => $item->assessment_type === 'er')
            ->sortByDesc('updated_at')
            ->values();

        // 🗄️ 3. ประวัติการจำหน่ายผู้ป่วย / ยกเลิก (ประจำวัน) — กรองเฉพาะเคสของ ER เท่านั้น! (ไม่เอาเคส OPD มาปน)
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

        // 🌟 1. สถิติผลงานวันนี้ (นับเฉพาะใบที่ "ตัวฉัน" เป็นคนประเมินจริงๆ)
        $myStats = [
            'evaluated_today' => \App\Models\MewsAssessment::where('evaluator_id', $user->id)
                                    ->whereDate('created_at', $today)
                                    ->where('status', '!=', 'cancelled')
                                    ->distinct('patient_id')
                                    ->count('patient_id'),
        ];

        // 🌟 2. หาคนไข้ที่อยู่ในความดูแล (ถ้าไม่ใช่ Admin ต้องตรงทั้ง "หน่วยฝึก" และ "ประจำผลัด")
        $unitPatientIdsQuery = \App\Models\Patient::query();
        if (!$isAdmin) {
            $unitPatientIdsQuery->where('military_unit_id', $user->military_unit_id)
                                ->where('intake_batch', $user->intake_batch); // 🌟 เพิ่มกรองผลัดตรงนี้!
        }
        $unitPatientIds = $unitPatientIdsQuery->pluck('id');

        // 🌟 3. หา ID ใบล่าสุดของคนไข้ในหน่วยและผลัดนั้น
        $absoluteLatestIds = \App\Models\MewsAssessment::whereIn('patient_id', $unitPatientIds)
            ->select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
            ->groupBy('patient_id')
            ->pluck('id');

        // 🌟 4. ดึงข้อมูลใบล่าสุดมาแสดงผล
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
    // =================================================================
    private function executiveDashboard(Request $request)
    {
        // 1. รับค่าจากตัวกรอง
        $filterBatch    = $request->query('batch', 'all');
        $filterMode     = $request->query('filter_mode', 'month'); // month, year, custom
        $filterMonth    = $request->query('month', date('m'));
        $filterYear     = $request->query('year', date('Y'));
        $startDateInput = $request->query('start_date', date('Y-m-d'));
        $endDateInput   = $request->query('end_date', date('Y-m-d'));

        $thaiMonthsShort = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];

        // 🌟 2. คำนวณช่วงเวลา (Date Range) & สร้างข้อความภาษาไทยอัตโนมัติ
        if ($filterMode === 'custom') {
            $startDate = Carbon::parse($startDateInput)->startOfDay();
            $endDate   = Carbon::parse($endDateInput)->endOfDay();
            
            // ป้องกันกรณีเลือกวันสิ้นสุดน้อยกว่าวันเริ่มต้น
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
            $startDate = Carbon::createFromDate($filterYear, 1, 1)->startOfDay();
            $endDate   = Carbon::createFromDate($filterYear, 12, 31)->endOfDay();
            $prevStartDate = $startDate->copy()->subYear();
            $prevEndDate   = $endDate->copy()->subYear();

            $periodText = '(ตลอดทั้งปี ' . ($filterYear + 543) . ')';
            $trendText  = 'ตลอดทั้งปี ' . ($filterYear + 543);
            $badgeText  = 'ตลอดทั้งปี ' . ($filterYear + 543);
        } else {
            // โหมด Month (Default)
            $startDate = Carbon::createFromDate($filterYear, $filterMonth, 1)->startOfMonth();
            $endDate   = $startDate->copy()->endOfMonth();
            $prevStartDate = $startDate->copy()->subMonth()->startOfMonth();
            $prevEndDate   = $startDate->copy()->subMonth()->endOfMonth();

            $monthName  = $startDate->locale('th')->translatedFormat('F');
            $periodText = '(ในรอบเดือน' . $monthName . ')';
            $trendText  = 'ตลอดทั้งเดือน' . $monthName;
            $badgeText  = 'ประจำเดือน ' . $monthName . ' ' . ($filterYear + 543);
        }

        // 🌟 3. สร้าง Query Optimizer (กรองวันที่ + ตัดเคสยกเลิก + กรองผลัดทหาร)
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

        // ==========================================
        // 📊 4. คำนวณภาพรวมปฏิบัติการ (KPI Cards)
        // ==========================================
        $patientsPeriod     = \App\Models\MewsAssessment::where($applyBaseFilters)->distinct('patient_id')->count('patient_id');
        $patientsPrevPeriod = \App\Models\MewsAssessment::where($applyPrevFilters)->distinct('patient_id')->count('patient_id');
        $growthPercent      = $patientsPrevPeriod > 0 ? round((($patientsPeriod - $patientsPrevPeriod) / $patientsPrevPeriod) * 100, 1) : ($patientsPeriod > 0 ? 100 : 0);

        $stats = [
            'patients_today'    => $patientsPeriod,
            'growth_percent'    => $growthPercent,
            'assessments_today' => \App\Models\MewsAssessment::where($applyBaseFilters)->count(),
            'critical_today'    => \App\Models\MewsAssessment::where($applyBaseFilters)->where('risk_level', 'emergency')->distinct('patient_id')->count('patient_id'),
            'admit_refer'       => \App\Models\MewsAssessment::where($applyBaseFilters)->whereIn('discharge_type', ['admitted', 'refer'])->distinct('patient_id')->count('patient_id'),
        ];

        // ==========================================
        // 🏥 5. ผลลัพธ์การรักษา (Discharge Status)
        // ==========================================
        $dischargeStats = \App\Models\MewsAssessment::where($applyBaseFilters)
                                ->where('status', 'completed')
                                ->select('discharge_type', \Illuminate\Support\Facades\DB::raw('count(DISTINCT patient_id) as total'))
                                ->groupBy('discharge_type')
                                ->pluck('total', 'discharge_type')
                                ->toArray();

        // ==========================================
        // ⭕ 6. กราฟวงแหวน: ระดับความเสี่ยงล่าสุดของแต่ละคน (Risk Levels)
        // ==========================================
        $riskLevels = ['normal' => 0, 'observe' => 0, 'emergency' => 0];
        $latestAssessmentsIds = \App\Models\MewsAssessment::where($applyBaseFilters)
            ->select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
            ->groupBy('patient_id')
            ->pluck('id');

        $latestAssessments = \App\Models\MewsAssessment::whereIn('id', $latestAssessmentsIds)->get();
        foreach ($latestAssessments as $assessment) {
            if (array_key_exists($assessment->risk_level, $riskLevels)) {
                $riskLevels[$assessment->risk_level]++;
            }
        }

        // ==========================================
        // 📊 7. กราฟแท่ง: 5 อันดับอาการฉุกเฉิน (ไม่ซ้ำคน - ครบทั้ง 23 อาการ)
        // ==========================================
        $allAssessments = \App\Models\MewsAssessment::where($applyBaseFilters)->whereNotNull('symptoms')->get();
                                
        $symptomPatients = []; 
        foreach ($allAssessments as $mews) {
            $syms = is_string($mews->symptoms) ? json_decode($mews->symptoms, true) : $mews->symptoms;
            if (is_array($syms)) {
                foreach ($syms as $sym) { $symptomPatients[$sym][] = $mews->patient_id; }
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

        // 🌟 พจนานุกรมอาการฉุกเฉินฉบับเต็มครบทุกโรค
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
            'trauma'                  => 'Trauma (อุบัติเหตุ)'
        ];

        $top5Labels = []; $top5Data = [];
        foreach ($top5Raw as $key => $count) {
            $top5Labels[] = $dict[$key] ?? $key;
            $top5Data[]   = $count;
        }

        // ==========================================
        // 📈 8. แนวโน้มผู้ป่วย (Trend Chart)
        // ==========================================
        $trendLabels = []; 
        $trendData   = [];
        $diffDays    = $startDate->diffInDays($endDate);

        if ($filterMode === 'year' || $diffDays > 60) {
            // กรณีดูทั้งปี หรือช่วงวันเกิน 60 วัน -> จัดกลุ่มเป็น "รายเดือน"
            $trendRaw = \App\Models\MewsAssessment::where($applyBaseFilters)
                ->select(\Illuminate\Support\Facades\DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month_year'), \Illuminate\Support\Facades\DB::raw('count(DISTINCT patient_id) as total'))
                ->groupBy('month_year')->pluck('total', 'month_year')->toArray();

            $current = $startDate->copy()->startOfMonth();
            while ($current->lte($endDate)) {
                $key = $current->format('Y-m');
                $trendLabels[] = $thaiMonthsShort[$current->month] . ' ' . substr(($current->year + 543), 2);
                $trendData[]   = $trendRaw[$key] ?? 0;
                $current->addMonth();
            }
        } elseif ($filterMode === 'month') {
            // กรณีดูรายเดือน -> แสดงเลขวันที่ 1 ถึง 30/31 เหมือนต้นฉบับเป๊ะ
            $trendRaw = \App\Models\MewsAssessment::where($applyBaseFilters)
                ->select(\Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'), \Illuminate\Support\Facades\DB::raw('count(DISTINCT patient_id) as total'))
                ->groupBy('date')->pluck('total', 'date')->toArray();

            for ($i = 1; $i <= $endDate->daysInMonth; $i++) {
                $dateStr = $startDate->copy()->day($i)->format('Y-m-d');
                $trendLabels[] = $i;
                $trendData[]   = $trendRaw[$dateStr] ?? 0;
            }
        } else {
            // กรณี Custom Date Range (ไม่เกิน 60 วัน) -> แสดงเป็น "22 ต.ค."
            $trendRaw = \App\Models\MewsAssessment::where($applyBaseFilters)
                ->select(\Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'), \Illuminate\Support\Facades\DB::raw('count(DISTINCT patient_id) as total'))
                ->groupBy('date')->pluck('total', 'date')->toArray();

            $current = $startDate->copy()->startOfDay();
            while ($current->lte($endDate)) {
                $key = $current->format('Y-m-d');
                $trendLabels[] = $current->day . ' ' . $thaiMonthsShort[$current->month]; 
                $trendData[]   = $trendRaw[$key] ?? 0;
                $current->addDay();
            }
        }

        // ==========================================
        // 🏕️ 9. 5 อันดับหน่วยที่มีผู้ป่วยสูงสุด (Top 5 Units)
        // ==========================================
        $topUnitsQuery = \Illuminate\Support\Facades\DB::table('mews_assessments')
            ->join('patients', 'mews_assessments.patient_id', '=', 'patients.id')
            ->whereBetween('mews_assessments.created_at', [$startDate, $endDate])
            ->where('mews_assessments.status', '!=', 'cancelled');

        if ($filterBatch !== 'all') {
            $topUnitsQuery->where('patients.intake_batch', $filterBatch);
        }

        $topUnits = $topUnitsQuery->select('patients.company', \Illuminate\Support\Facades\DB::raw('count(DISTINCT patients.id) as total'))
            ->whereNotNull('patients.company')->where('patients.company', '!=', '')
            ->groupBy('patients.company')->orderByDesc('total')->limit(5)->get();

        // ==========================================
        // 🚦 10. จำนวนเคสจำแนกตามสี + ข้อมูลเจาะลึกสำหรับผู้บริหาร (สีเหลือง & สีแดง)
        // ==========================================
        $mewsLevelsToday = \App\Models\MewsAssessment::where($applyBaseFilters)
            ->select('risk_level', \Illuminate\Support\Facades\DB::raw('count(DISTINCT patient_id) as total'))
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level')
            ->toArray();

        // 🌟 ดึงใบประเมินล่าสุดของแต่ละคนครบทั้ง 3 กลุ่ม (สีเขียว normal, สีเหลือง observe, สีแดง emergency)
        $executivePatientDetails = ['normal' => [], 'observe' => [], 'emergency' => []];

        foreach (['normal', 'observe', 'emergency'] as $targetLevel) {
            $levelLatestIds = \App\Models\MewsAssessment::where($applyBaseFilters)
                ->where('risk_level', $targetLevel)
                ->select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
                ->groupBy('patient_id')
                ->pluck('id');

            $levelCases = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
                ->whereIn('id', $levelLatestIds)
                ->orderByDesc('total_score')
                ->orderByDesc('updated_at')
                ->get();

            foreach ($levelCases as $c) {
                $p = $c->patient;
                if (!$p) continue;

                $unitName = $p->militaryUnit->short_name ?? $p->militaryUnit->name ?? $p->company ?? 'ไม่ระบุหน่วย';
                $battalion = $p->militaryUnit->battalion ?? $p->battalion ?? '';
                if ($battalion) $unitName .= " ({$battalion})";

                // แปลรหัสอาการฉุกเฉินให้เป็นภาษาไทย
                $rawSyms = is_string($c->symptoms) ? json_decode($c->symptoms, true) : ($c->symptoms ?? []);
                $symNames = [];
                if (is_array($rawSyms)) {
                    foreach ($rawSyms as $sKey) {
                        $symNames[] = $dict[$sKey] ?? $sKey;
                    }
                }

                // สรุปสถานะการรักษา / การส่งต่อ (Disposition) ให้ตรงกับสถานะการกลับหน่วยฝึกจริง
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
                    $isRoutedToER = ($c->assessment_type === 'er') || in_array($c->risk_level, ['observe', 'emergency']) || !empty($symNames);
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
                    'vitals'            => "BP: " . ($c->systolic_bp ?? '-') . " | HR: " . ($c->heart_rate ?? '-') . " | T: " . ($c->temperature ?? '-') . "°C | O2: " . ($c->o2sat ?? '-') . "%",
                    'symptoms'          => $symNames,
                    'disposition_text'  => $dispositionText,
                    'disposition_badge' => $dispositionBadge,
                    'disposition_icon'  => $dispositionIcon,
                    'updated_time'      => $c->updated_at->addYears(543)->format('d/m/Y H:i น.'),
                ];
            }
        }

       

        // ==========================================
        // 🦠 11. ระบบตรวจจับกลุ่มเสี่ยงระบาด 48 ชม. ล่าสุด (Outbreak & Influenza Radar)
        // ==========================================
        // 11.1 ค้นหาหน่วยฝึกที่มีทหารไข้สูง (>= 38.0°C) ในรอบ 48 ชม.
        $outbreakUnits = \Illuminate\Support\Facades\DB::table('mews_assessments')
            ->join('patients', 'mews_assessments.patient_id', '=', 'patients.id')
            ->leftJoin('recruit_screenings', 'patients.id', '=', 'recruit_screenings.patient_id')
            ->where('mews_assessments.created_at', '>=', now()->subHours(48))
            ->where('mews_assessments.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('mews_assessments.temperature', '>=', 38.0)
                  ->orWhere('recruit_screenings.color_group', 'red');
            })
            ->select(
                'patients.company',
                'patients.intake_batch',
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT CASE WHEN mews_assessments.temperature >= 38.0 THEN patients.id END) as high_fever_count'),
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT CASE WHEN recruit_screenings.color_group = "red" THEN patients.id END) as red_group_count')
            )
            ->whereNotNull('patients.company')
            ->groupBy('patients.company', 'patients.intake_batch')
            ->havingRaw('high_fever_count >= 2 OR red_group_count >= 1')
            ->orderByDesc('high_fever_count')
            ->get();

        return view('backend.dashboards.executive', compact(
            'stats', 'dischargeStats', 'riskLevels', 'top5Labels', 'top5Data', 'trendLabels', 'trendData', 'topUnits', 'mewsLevelsToday',
            'filterMode', 'filterMonth', 'filterYear', 'filterBatch', 'startDateInput', 'endDateInput',
            'periodText', 'trendText', 'badgeText', 'executivePatientDetails', 'outbreakUnits'
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

        // 🟢 1. ดึงข้อมูลคิวรอตรวจ OPD (เฉพาะใบล่าสุดที่ Pending และเป็นเคส OPD คือไม่เข้าเกณฑ์ ER)
        $opdQueue = \App\Models\MewsAssessment::with(['patient.militaryUnit'])
            ->whereIn('id', $latestIds)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get()
            ->filter(fn($item) => !$this->isErCase($item))
            ->values();

        // 🗄️ 2. ดึงประวัติการจำหน่ายผู้ป่วยนอก / ยกเลิก (เฉพาะเคส OPD ของวันนี้)
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

        // 1. ดึงเคสที่กำลังรอคัดกรอง/ดำเนินการ (Pending) ทั้งหมด แล้วเรียง MEWS สูงสุดขึ้นอันดับ 1
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
                // ลำดับที่ 1: คะแนน MEWS สูงสุดขึ้นก่อนเสมอ
                if ((int)$a->total_score !== (int)$b->total_score) {
                    return (int)$b->total_score <=> (int)$a->total_score;
                }
                // ลำดับที่ 2: หาก MEWS เท่ากัน เอาคนที่มีอาการฉุกเฉินเฉพาะโรคขึ้นก่อน
                if ($a->has_emergency_symptom !== $b->has_emergency_symptom) {
                    return $b->has_emergency_symptom <=> $a->has_emergency_symptom;
                }
                // ลำดับที่ 3: เอาคนที่ส่งมาก่อน (รอคิวนานกว่า) ขึ้นก่อน
                return $a->created_at <=> $b->created_at;
            })
            ->values();

        // 2. สรุปยอด KPI จุดคัดกรอง
        $stats = [
            'total_pending' => $screeningQueue->count(),
            'emergency'     => $screeningQueue->filter(fn($c) => $c->risk_level === 'emergency' || $c->has_emergency_symptom)->count(),
            'observe'       => $screeningQueue->filter(fn($c) => $c->risk_level === 'observe' && !$c->has_emergency_symptom)->count(),
            'normal'        => $screeningQueue->filter(fn($c) => $c->risk_level === 'normal' && !$c->has_emergency_symptom)->count(),
        ];

        // 3. ประวัติที่ปิดเคส/จำหน่ายแล้ววันนี้
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