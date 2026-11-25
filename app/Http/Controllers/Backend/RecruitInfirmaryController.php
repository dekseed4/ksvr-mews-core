<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\MilitaryUnit;
use App\Models\RecruitInfirmaryStay;
use App\Models\RecruitMorbidity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class RecruitInfirmaryController extends Controller
{
    private function canViewAllUnits($user): bool
    {
        $roles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        return in_array('admin', $roles)
            || in_array('er_nurse', $roles)
            || in_array('opd_nurse', $roles)
            || in_array('executive', $roles)
            || in_array('registration', $roles)
            || in_array('medical_staff', $roles);
    }

    private function applyMedicScope($query, $user)
    {
        if ($this->canViewAllUnits($user)) {
            return $query;
        }
        $query->where('military_unit_id', $user->military_unit_id);
        if (!empty($user->intake_batch)) {
            $query->where('intake_batch', $user->intake_batch);
        }
        return $query;
    }

    // =========================================================================
    // 🛡️ ตรวจสอบและอัปเกรดตารางฐานข้อมูล Care Chart & e-MAR อัตโนมัติ
    // =========================================================================
    public static function ensureCareChartSchema(): void
    {
        try {
            if (!Schema::hasTable('patient_vital_logs')) {
                DB::statement("
                    CREATE TABLE patient_vital_logs (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        patient_id BIGINT UNSIGNED NOT NULL,
                        record_date DATE NOT NULL,
                        round_time VARCHAR(10) NOT NULL DEFAULT '06:00',
                        temperature DECIMAL(4,1) NULL,
                        heart_rate INT NULL,
                        respiratory_rate INT NULL,
                        systolic_bp INT NULL,
                        diastolic_bp INT NULL,
                        o2sat INT NULL,
                        mews_score INT NOT NULL DEFAULT 0,
                        note VARCHAR(255) NULL,
                        recorded_by BIGINT UNSIGNED NULL,
                        created_at TIMESTAMP NULL,
                        updated_at TIMESTAMP NULL,
                        INDEX idx_pvl_pt_date (patient_id, record_date)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            if (!Schema::hasTable('patient_medications')) {
                DB::statement("
                    CREATE TABLE patient_medications (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        patient_id BIGINT UNSIGNED NOT NULL,
                        care_group VARCHAR(30) NOT NULL DEFAULT 'ci_isolation',
                        drug_name VARCHAR(255) NOT NULL,
                        dosage VARCHAR(100) NULL,
                        meal_timing VARCHAR(50) NOT NULL DEFAULT 'after_meal',
                        take_morning TINYINT(1) NOT NULL DEFAULT 0,
                        take_noon TINYINT(1) NOT NULL DEFAULT 0,
                        take_evening TINYINT(1) NOT NULL DEFAULT 0,
                        take_bedtime TINYINT(1) NOT NULL DEFAULT 0,
                        take_prn TINYINT(1) NOT NULL DEFAULT 0,
                        custom_slot_text VARCHAR(255) NULL,
                        start_date DATE NOT NULL,
                        end_date DATE NULL,
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        instruction_note VARCHAR(255) NULL,
                        recorded_by BIGINT UNSIGNED NULL,
                        created_at TIMESTAMP NULL,
                        updated_at TIMESTAMP NULL,
                        INDEX idx_pm_pt_active (patient_id, is_active)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } else {
                if (!Schema::hasColumn('patient_medications', 'take_prn')) {
                    DB::statement("ALTER TABLE patient_medications ADD COLUMN take_prn TINYINT(1) NOT NULL DEFAULT 0 AFTER take_bedtime");
                }
                if (!Schema::hasColumn('patient_medications', 'custom_slot_text')) {
                    DB::statement("ALTER TABLE patient_medications ADD COLUMN custom_slot_text VARCHAR(255) NULL AFTER take_prn");
                }
                if (!Schema::hasColumn('patient_medications', 'instruction_note')) {
                    DB::statement("ALTER TABLE patient_medications ADD COLUMN instruction_note VARCHAR(255) NULL");
                }
                DB::statement("ALTER TABLE patient_medications MODIFY COLUMN meal_timing VARCHAR(50) NOT NULL DEFAULT 'after_meal'");
            }

            if (!Schema::hasTable('patient_medication_logs')) {
                DB::statement("
                    CREATE TABLE patient_medication_logs (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        patient_medication_id BIGINT UNSIGNED NOT NULL,
                        patient_id BIGINT UNSIGNED NULL,
                        log_date DATE NOT NULL,
                        time_slot VARCHAR(30) NOT NULL,
                        status VARCHAR(20) NOT NULL DEFAULT 'taken',
                        recorded_by BIGINT UNSIGNED NULL,
                        created_at TIMESTAMP NULL,
                        updated_at TIMESTAMP NULL,
                        INDEX idx_pml_lookup (patient_medication_id, log_date, time_slot)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } else {
                DB::statement("ALTER TABLE patient_medication_logs MODIFY COLUMN time_slot VARCHAR(30) NOT NULL");
            }
        } catch (\Throwable $e) {
            // ข้ามกรณีโครงสร้างตารางพร้อมแล้ว
        }
    }

    // =========================================================================
    // 🔄 ฟังก์ชันทำความสะอาดและซิงค์ข้อมูลห้องพยาบาลหน่วยฝึกอัตโนมัติ (Self-Healing)
    // =========================================================================
    private function reconcileInfirmaryData(string $selectedBatch): void
    {
        RecruitInfirmaryStay::whereDoesntHave('patient')->delete();

        $inactiveStays = RecruitInfirmaryStay::with(['patient.militaryDischarge'])
            ->where('status', 'staying')
            ->when($selectedBatch !== 'all', fn($q) => $q->where('intake_batch', $selectedBatch))
            ->get();

        foreach ($inactiveStays as $stay) {
            $p = $stay->patient;
            if (!$p) continue;

            $isInactiveDuty = in_array($p->duty_status ?? 'active', ['absent', 'imprisoned', 'deceased']);
            $isCompletedDc  = $p->militaryDischarge && $p->militaryDischarge->process_status === 'completed';

            if ($isInactiveDuty || $isCompletedDc) {
                $stay->update([
                    'status'         => 'returned',
                    'discharged_at'  => now(),
                    'discharge_note' => 'ปรับสถานะอัตโนมัติตามสถานะกำลังพล (' . $p->duty_status_label . ')',
                ]);
            }
        }

        $activeGroups = RecruitInfirmaryStay::where('status', 'staying')
            ->when($selectedBatch !== 'all', fn($q) => $q->where('intake_batch', $selectedBatch))
            ->orderBy('admitted_at', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('patient_id');

        foreach ($activeGroups as $patientId => $group) {
            if ($group->count() > 1) {
                $duplicateIds = $group->slice(1)->pluck('id')->toArray();
                RecruitInfirmaryStay::whereIn('id', $duplicateIds)->delete();
            }
        }
    }

    // =========================================================================
    // 🏕️ 1. หน้าตารางหลัก: ระบบจัดการนอนห้องพยาบาลหน่วยฝึก & แยกโรงนอน
    // =========================================================================
    public function index(Request $request)
    {
        self::ensureCareChartSchema();
        $user = auth()->user();

        $batchesQuery = Patient::whereNotNull('intake_batch')->where('intake_batch', '!=', '');
        $this->applyMedicScope($batchesQuery, $user);
        $batches = $batchesQuery->distinct()->pluck('intake_batch')->sortDesc();

        $selectedBatch    = $request->input('intake_batch', $batches->first() ?? '2/69');
        $selectedUnit     = $request->input('unit_id', '');
        $selectedLocation = $request->input('location_type', '');
        $selectedReason   = $request->input('reason_category', '');
        $selectedStatus   = $request->input('status', 'staying');

        $this->reconcileInfirmaryData($selectedBatch);

        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 20, 50, 100])) {
            $perPage = 10;
        }

        $unitsQuery = MilitaryUnit::where('is_active', true)->orderBy('name');
        if (!$this->canViewAllUnits($user)) {
            $unitsQuery->where('id', $user->military_unit_id);
        }
        $units = $unitsQuery->get();

        $baseQuery = RecruitInfirmaryStay::with(['patient.militaryUnit', 'patient.militaryDischarge', 'militaryUnit'])
            ->whereHas('patient');
        $this->applyMedicScope($baseQuery, $user);

        if ($selectedBatch !== 'all') {
            $baseQuery->where('intake_batch', $selectedBatch);
        }
        if (!empty($selectedUnit)) {
            $baseQuery->where('military_unit_id', $selectedUnit);
        }

        $rawSummary = (clone $baseQuery)->selectRaw("
            COUNT(DISTINCT CASE WHEN status = 'staying' THEN patient_id END) as active_total,
            COUNT(DISTINCT CASE WHEN status = 'staying' AND location_type = 'unit_infirmary' AND reason_category != 'respiratory' THEN patient_id END) as unit_infirmary,
            COUNT(DISTINCT CASE WHEN status = 'staying' AND location_type = 'camp_hospital' THEN patient_id END) as camp_hospital,
            COUNT(DISTINCT CASE WHEN status = 'staying' AND reason_category = 'respiratory' THEN patient_id END) as respiratory,
            COUNT(DISTINCT CASE WHEN status = 'returned' THEN patient_id END) as returned_total
        ")->first();

        $summary = [
            'active_total'   => (int) ($rawSummary->active_total ?? 0),
            'unit_infirmary' => (int) ($rawSummary->unit_infirmary ?? 0),
            'camp_hospital'  => (int) ($rawSummary->camp_hospital ?? 0),
            'respiratory'    => (int) ($rawSummary->respiratory ?? 0),
            'returned_total' => (int) ($rawSummary->returned_total ?? 0),
        ];

        $listQuery = clone $baseQuery;
        if (!empty($selectedLocation)) {
            $listQuery->where('location_type', $selectedLocation);
            if ($selectedLocation === 'unit_infirmary' && empty($selectedReason)) {
                $listQuery->where('reason_category', '!=', 'respiratory');
            }
        }
        if (!empty($selectedReason)) {
            $listQuery->where('reason_category', $selectedReason);
        }
        if ($selectedStatus !== 'all') {
            $listQuery->where('status', $selectedStatus);
        }

        $stays = $listQuery->orderBy('intake_batch', 'desc')
            ->orderByRaw('COALESCE(military_unit_id, 9999) ASC')
            ->orderByRaw("CASE WHEN status = 'staying' THEN 0 ELSE 1 END")
            ->orderBy('admitted_at', 'desc')
            ->paginate($perPage)
            ->appends($request->query());

        // 🌟 ดึงสรุปสถานะวัดสัญญาณชีพวันนี้ (06:00 / 18:00) และจำนวนรายการยา e-MAR ของพลทหารในหน้านี้
        $stayPatientIds = $stays->pluck('patient_id')->filter()->unique()->values()->toArray();
        $todayVitalsByPatient = collect();
        $activeMedsCountByPatient = collect();

        if (!empty($stayPatientIds)) {
            if (class_exists('\App\Models\CovidVitalLog')) {
                $todayVitalsByPatient = \App\Models\CovidVitalLog::whereIn('patient_id', $stayPatientIds)
                    ->whereDate('record_date', Carbon::today()->format('Y-m-d'))
                    ->get()
                    ->groupBy('patient_id');
            }
            if (Schema::hasTable('patient_medications')) {
                $activeMedsCountByPatient = DB::table('patient_medications')
                    ->whereIn('patient_id', $stayPatientIds)
                    ->where('is_active', true)
                    ->select('patient_id', DB::raw('COUNT(*) as total_meds'))
                    ->groupBy('patient_id')
                    ->pluck('total_meds', 'patient_id');
            }
        }

        $patientsQuery = Patient::with(['militaryUnit', 'militaryDischarge'])
            ->whereDoesntHave('militaryDischarge', function ($q) {
                $q->where('process_status', 'completed');
            })
            ->where(function ($q) {
                $q->where('duty_status', 'active')->orWhereNull('duty_status');
            })
            ->orderBy('first_name');

        $this->applyMedicScope($patientsQuery, $user);
        if ($selectedBatch !== 'all') {
            $patientsQuery->where('intake_batch', $selectedBatch);
        }
        $batchPatients = $patientsQuery->get(['id', 'cid', 'hn', 'rank', 'first_name', 'last_name', 'military_unit_id', 'intake_batch']);

        return view('backend.recruit_infirmary.index', compact(
            'batches', 'selectedBatch', 'units', 'selectedUnit',
            'selectedLocation', 'selectedReason', 'selectedStatus', 'perPage',
            'summary', 'stays', 'batchPatients',
            'todayVitalsByPatient', 'activeMedsCountByPatient'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'patient_id'       => 'required|exists:patients,id',
            'location_type'    => 'required|in:unit_infirmary,camp_hospital',
            'bed_or_zone'      => 'nullable|string|max:100',
            'reason_category'  => 'required|in:respiratory,post_admit,musculoskeletal,other',
            'diagnosis_text'   => 'required|string|max:255',
            'care_instruction' => 'nullable|string|max:500',
            'admitted_date'    => 'required|date',
            'admitted_time'    => 'required|string|max:10',
        ], [
            'patient_id.required'      => 'กรุณาค้นหาและคลิกเลือกรายชื่อพลทหารจากรายการ',
            'location_type.required'   => 'กรุณาเลือกสถานที่แยกนอนพัก',
            'reason_category.required' => 'กรุณาเลือกสาเหตุที่ต้องแยกโรงนอน',
            'diagnosis_text.required'  => 'กรุณาระบุอาการสำคัญ หรือการวินิจฉัยโรค (Dx.)',
            'admitted_date.required'   => 'กรุณาระบุวันที่เริ่มแยกนอน',
            'admitted_time.required'   => 'กรุณาระบุเวลาที่เริ่มแยกนอน',
        ]);

        $patient = Patient::with('militaryDischarge')->findOrFail($data['patient_id']);

        $dutyStat = $patient->duty_status ?? 'active';
        $isCompletedDischarge = $patient->militaryDischarge && $patient->militaryDischarge->process_status === 'completed';

        if (in_array($dutyStat, ['absent', 'imprisoned', 'deceased']) || $isCompletedDischarge) {
            return back()->with('error', "ไม่สามารถบันทึกแยกโรงนอนได้ เนื่องจากพลทหารอยู่ในสถานะ \"{$patient->duty_status_label}\" ครับ");
        }

        if (!$this->canViewAllUnits($user) && $patient->military_unit_id !== $user->military_unit_id) {
            abort(403, 'คุณสามารถบันทึกข้อมูลเฉพาะพลทหารในหน่วยฝึกของคุณเท่านั้นครับ');
        }

        $existingActive = RecruitInfirmaryStay::where('patient_id', $patient->id)
            ->where('status', 'staying')
            ->first();

        $timeStr = !empty($data['admitted_time']) ? substr($data['admitted_time'], 0, 5) . ':00' : now()->format('H:i:s');
        $admittedAt = Carbon::parse($data['admitted_date'] . ' ' . $timeStr);

        DB::transaction(function () use ($existingActive, $patient, $data, $admittedAt, $user) {
            if ($existingActive) {
                $existingActive->update([
                    'location_type'    => $data['location_type'],
                    'bed_or_zone'      => $data['bed_or_zone'] ?? null,
                    'reason_category'  => $data['reason_category'],
                    'diagnosis_text'   => $data['diagnosis_text'],
                    'care_instruction' => $data['care_instruction'] ?? null,
                    'admitted_at'      => $admittedAt,
                ]);
            } else {
                RecruitInfirmaryStay::create([
                    'patient_id'       => $patient->id,
                    'military_unit_id' => $patient->military_unit_id,
                    'intake_batch'     => $patient->intake_batch ?: '2/69',
                    'location_type'    => $data['location_type'],
                    'bed_or_zone'      => $data['bed_or_zone'] ?? null,
                    'reason_category'  => $data['reason_category'],
                    'diagnosis_text'   => $data['diagnosis_text'],
                    'care_instruction' => $data['care_instruction'] ?? null,
                    'admitted_at'      => $admittedAt,
                    'status'           => 'staying',
                    'recorded_by'      => $user->id,
                ]);
            }

            $dxLower = strtolower($data['diagnosis_text']);
            $isCovid = str_contains($data['diagnosis_text'], 'โควิด') || str_contains($data['diagnosis_text'], 'โควิค') || str_contains($dxLower, 'covid');
            $isFlu   = !$isCovid && (str_contains($data['diagnosis_text'], 'ไข้หวัดใหญ่') || str_contains($data['diagnosis_text'], 'สายพันธุ์') || preg_match('/\b(flu|influenza)\b/i', $dxLower));

            if (($isCovid || $isFlu) && class_exists('\App\Models\RecruitMorbidity')) {
                $morbLocation = $data['location_type'] === 'camp_hospital' ? 'hospital' : 'ci';
                $subType      = $isCovid ? 'covid' : 'flu';

                $existingMorb = RecruitMorbidity::where('patient_id', $patient->id)
                    ->where('category', 'respiratory')
                    ->first();

                if ($existingMorb) {
                    $existingMorb->update([
                        'treatment_location' => $morbLocation,
                        'diagnosis_text'     => $data['diagnosis_text'],
                        'status'             => 'treating',
                        'recovered_date'     => null,
                    ]);
                } else {
                    RecruitMorbidity::create([
                        'patient_id'         => $patient->id,
                        'military_unit_id'   => $patient->military_unit_id,
                        'intake_batch'       => $patient->intake_batch ?: '2/69',
                        'category'           => 'respiratory',
                        'sub_type'           => $subType,
                        'treatment_location' => $morbLocation,
                        'diagnosis_text'     => $data['diagnosis_text'],
                        'onset_date'         => $admittedAt->format('Y-m-d'),
                        'status'             => 'treating',
                        'recorded_by'        => $user->id,
                    ]);
                }
            }
        });

        $locLabel = $data['location_type'] === 'camp_hospital' ? 'ฝากนอนแยกกักที่ รพ.ค่ายฯ' : 'นอนห้องพยาบาลหน่วยฝึก (แยกโรงนอน)';
        return back()->with('success', "บันทึกข้อมูล {$patient->rank}{$patient->first_name} เข้าสู่ระบบ \"{$locLabel}\" เรียบร้อยแล้ว!");
    }

    public function update(Request $request, $id)
    {
        $stay = RecruitInfirmaryStay::with('patient')->findOrFail($id);
        $user = auth()->user();

        if (!$this->canViewAllUnits($user) && $stay->military_unit_id !== $user->military_unit_id) {
            abort(403, 'ไม่มีสิทธิ์แก้ไขข้อมูลของหน่วยฝึกอื่นครับ');
        }

        $data = $request->validate([
            'location_type'    => 'required|in:unit_infirmary,camp_hospital',
            'bed_or_zone'      => 'nullable|string|max:100',
            'reason_category'  => 'required|in:respiratory,post_admit,musculoskeletal,other',
            'diagnosis_text'   => 'required|string|max:255',
            'care_instruction' => 'nullable|string|max:500',
            'admitted_date'    => 'required|date',
            'admitted_time'    => 'required|string|max:10',
        ]);

        $timeStr = !empty($data['admitted_time']) ? substr($data['admitted_time'], 0, 5) . ':00' : '08:30:00';
        $admittedAt = Carbon::parse($data['admitted_date'] . ' ' . $timeStr);

        DB::transaction(function () use ($stay, $data, $admittedAt) {
            $stay->update([
                'location_type'    => $data['location_type'],
                'bed_or_zone'      => $data['bed_or_zone'] ?: null,
                'reason_category'  => $data['reason_category'],
                'diagnosis_text'   => $data['diagnosis_text'],
                'care_instruction' => $data['care_instruction'] ?: null,
                'admitted_at'      => $admittedAt,
            ]);

            if ($data['reason_category'] === 'respiratory' && class_exists('\App\Models\RecruitMorbidity')) {
                $morbLocation = $data['location_type'] === 'camp_hospital' ? 'hospital' : 'ci';
                RecruitMorbidity::where('patient_id', $stay->patient_id)
                    ->where('category', 'respiratory')
                    ->where('status', 'treating')
                    ->update([
                        'treatment_location' => $morbLocation,
                        'diagnosis_text'     => $data['diagnosis_text'],
                    ]);
            }
        });

        $ptName = ($stay->patient->rank ?? '') . ($stay->patient->first_name ?? '');
        return back()->with('success', "อัปเดตแก้ไขข้อมูลการแยกนอนของ {$ptName} เรียบร้อยแล้ว!");
    }

    public function dischargeToBarracks(Request $request, $id)
    {
        $stay = RecruitInfirmaryStay::with('patient')->findOrFail($id);
        $user = auth()->user();

        if (!$this->canViewAllUnits($user) && $stay->military_unit_id !== $user->military_unit_id) {
            abort(403, 'ไม่มีสิทธิ์จัดการข้อมูลของหน่วยฝึกอื่นครับ');
        }

        if ($request->boolean('revert_to_staying')) {
            $stay->update([
                'status'         => 'staying',
                'discharged_at'  => null,
                'discharge_note' => null,
            ]);
            return back()->with('success', 'ย้อนสถานะกลับเป็น "กำลังแยกนอนพัก" เรียบร้อยแล้ว');
        }

        $request->validate([
            'discharged_date' => 'nullable|date',
            'discharged_time' => 'nullable|string|max:10',
            'discharge_note'  => 'nullable|string|max:255',
        ]);

        $timePart = $request->filled('discharged_time') ? substr($request->discharged_time, 0, 5) . ':00' : now()->format('H:i:s');
        $dischargedAt = $request->filled('discharged_date')
            ? Carbon::parse($request->discharged_date . ' ' . $timePart)
            : now();

        DB::transaction(function () use ($stay, $dischargedAt, $request) {
            $stay->update([
                'status'         => 'returned',
                'discharged_at'  => $dischargedAt,
                'discharge_note' => $request->discharge_note ?: 'อาการปกติ กลับเข้าโรงนอนรวมของกองร้อยเรียบร้อยแล้ว',
            ]);

            if ($stay->reason_category === 'respiratory' && class_exists('\App\Models\RecruitMorbidity')) {
                RecruitMorbidity::where('patient_id', $stay->patient_id)
                    ->where('category', 'respiratory')
                    ->where('status', 'treating')
                    ->update([
                        'status'         => 'recovered',
                        'recovered_date' => $dischargedAt->format('Y-m-d'),
                        'severity'       => 'manual_recovered',
                    ]);
            }

            if (Schema::hasTable('patient_medications')) {
                DB::table('patient_medications')
                    ->where('patient_id', $stay->patient_id)
                    ->where('care_group', 'ci_isolation')
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'end_date'  => $dischargedAt->format('Y-m-d'),
                    ]);
            }
        });

        return back()->with('success', "จำหน่าย {$stay->patient->rank}{$stay->patient->first_name} กลับเข้าโรงนอนปกติเรียบร้อยแล้ว!");
    }

    public function destroy($id)
    {
        $stay = RecruitInfirmaryStay::findOrFail($id);
        $user = auth()->user();

        if (!$this->canViewAllUnits($user) && $stay->military_unit_id !== $user->military_unit_id) {
            abort(403, 'ไม่มีสิทธิ์ลบรายการของหน่วยฝึกอื่นครับ');
        }

        DB::transaction(function () use ($stay) {
            if ($stay->reason_category === 'respiratory' && class_exists('\App\Models\RecruitMorbidity')) {
                RecruitMorbidity::where('patient_id', $stay->patient_id)
                    ->where('category', 'respiratory')
                    ->where('status', 'treating')
                    ->delete();
            }
            $stay->delete();
        });

        return back()->with('success', 'ลบรายการแยกโรงนอนและซิงค์ยอดรายงานประจำวันเรียบร้อยแล้ว');
    }

    // =========================================================================
    // 📋 2. ระบบใบบันทึกสัญญาณชีพ (06:00/18:00) & ตารางรับประทานยา (e-MAR)
    // (เชื่อมกับไฟล์ resources/views/backend/recruit_infirmary/care_chart.blade.php)
    // =========================================================================
    public function showCareChart(Request $request, $patientId)
    {
        self::ensureCareChartSchema();

        $user = auth()->user();
        $patient = Patient::with(['militaryUnit', 'activeInfirmaryStay', 'militaryDischarge'])->findOrFail($patientId);

        if (!$this->canViewAllUnits($user) && $patient->military_unit_id !== $user->military_unit_id) {
            abort(403, 'คุณไม่มีสิทธิ์ดูข้อมูลของหน่วยฝึกอื่นครับ');
        }

        $defaultStart = $patient->activeInfirmaryStay
            ? $patient->activeInfirmaryStay->admitted_at->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        $startDateStr = $request->input('start_date', $defaultStart);
        $startDate    = Carbon::parse($startDateStr)->startOfDay();

        $dateColumns = [];
        for ($i = 0; $i < 10; $i++) {
            $d = $startDate->copy()->addDays($i);
            $dateColumns[] = [
                'date_str'  => $d->format('Y-m-d'),
                'thai_text' => $d->locale('th')->day . ' ' . $d->locale('th')->translatedFormat('M') . ' ' . ($d->year + 543),
                'is_today'  => $d->isToday(),
            ];
        }

        $endDateStr = $startDate->copy()->addDays(9)->format('Y-m-d');

        // ดึงบันทึกสัญญาณชีพ (06:00 / 18:00)
        $vitalLogs = DB::table('patient_vital_logs')
            ->where('patient_id', $patient->id)
            ->whereBetween('record_date', [$startDate->format('Y-m-d'), $endDateStr])
            ->get()
            ->groupBy(fn($item) => substr((string)$item->record_date, 0, 10));

        // ดึงรายการยา (e-MAR)
        $medications = \App\Models\PatientMedication::where('patient_id', $patient->id)
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();

        // ดึงประวัติการติ๊กกินยา/ทายา
        $medIds = $medications->pluck('id')->toArray();
        $medLogs = collect();
        if (!empty($medIds)) {
            $rawLogs = DB::table('patient_medication_logs')
                ->whereIn('patient_medication_id', $medIds)
                ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDateStr])
                ->get();

            foreach ($rawLogs as $lg) {
                $dKey = substr((string)$lg->log_date, 0, 10);
                $medLogs->put($lg->patient_medication_id . '_' . $dKey . '_' . $lg->time_slot, $lg);
            }
        }

        // รายชื่อพลทหารที่กำลังแยกนอนในห้องพยาบาลหน่วยเดียวกัน เพื่อให้สลับคนตรวจได้ทันที
        $infirmaryPeersQuery = RecruitInfirmaryStay::with(['patient.militaryUnit'])
            ->where('status', 'staying')
            ->whereHas('patient');
        $this->applyMedicScope($infirmaryPeersQuery, $user);
        if (!empty($patient->intake_batch)) {
            $infirmaryPeersQuery->where('intake_batch', $patient->intake_batch);
        }
        $infirmaryPeers = $infirmaryPeersQuery->orderBy('military_unit_id')->orderBy('admitted_at', 'desc')->get();

        return view('backend.recruit_infirmary.care_chart', compact(
            'patient',
            'startDateStr',
            'dateColumns',
            'vitalLogs',
            'medications',
            'medLogs',
            'infirmaryPeers'
        ));
    }

    public function storeVital(Request $request)
    {
        self::ensureCareChartSchema();

        $data = $request->validate([
            'patient_id'       => 'required|exists:patients,id',
            'record_date'      => 'required|date',
            'round_time'       => 'required|in:06:00,18:00',
            'temperature'      => 'required|numeric',
            'heart_rate'       => 'required|integer',
            'respiratory_rate' => 'required|integer',
            'systolic_bp'      => 'required|integer',
            'diastolic_bp'     => 'required|integer',
            'o2sat'            => 'required|integer',
            'note'             => 'nullable|string|max:255',
        ]);

        $temp = (float) $data['temperature'];
        $hr   = (int) $data['heart_rate'];
        $sbp  = (int) $data['systolic_bp'];
        $rr   = (int) $data['respiratory_rate'];
        $o2   = (int) $data['o2sat'];

        $score = 0;
        if ($temp < 35.0 || $temp > 38.5) $score += 2;
        elseif ($temp <= 36.0 || ($temp >= 38.1 && $temp <= 38.5)) $score += 1;

        if ($hr <= 40) $score += 2;
        elseif ($hr <= 50 || ($hr >= 101 && $hr <= 110)) $score += 1;
        elseif ($hr >= 111 && $hr <= 129) $score += 2;
        elseif ($hr >= 130) $score += 3;

        if ($sbp <= 70) $score += 3;
        elseif ($sbp <= 80 || $sbp >= 200) $score += 2;
        elseif ($sbp <= 100) $score += 1;

        if ($rr < 9 || ($rr >= 21 && $rr <= 29)) $score += 2;
        elseif ($rr >= 15 && $rr <= 20) $score += 1;
        elseif ($rr >= 30) $score += 3;

        if ($o2 < 90) $score += 3;
        elseif ($o2 <= 94) $score += 2;

        DB::table('patient_vital_logs')->updateOrInsert(
            [
                'patient_id'  => $data['patient_id'],
                'record_date' => $data['record_date'],
                'round_time'  => $data['round_time'],
            ],
            [
                'temperature'      => $temp,
                'heart_rate'       => $hr,
                'respiratory_rate' => $rr,
                'systolic_bp'      => $sbp,
                'diastolic_bp'     => (int) $data['diastolic_bp'],
                'o2sat'            => $o2,
                'mews_score'       => $score,
                'note'             => $data['note'] ?? null,
                'recorded_by'      => auth()->id(),
                'updated_at'       => now(),
                'created_at'       => now(),
            ]
        );

        return back()->with('success', "บันทึกสัญญาณชีพรอบ {$data['round_time']} น. (MEWS: {$score} คะแนน) เรียบร้อยแล้ว!");
    }

    // 🌟 บันทึกหรือแก้ไขรายการยา (รองรับทั้งมื้อปกติ, เวลามีอาการ PRN, ยาทา และยานวด โดยไม่บังคับติ๊ก 4 มื้อหลัก)
    public function storeMedication(Request $request)
    {
        self::ensureCareChartSchema();

        $data = $request->validate([
            'medication_id'    => 'nullable|integer',
            'patient_id'       => 'required|exists:patients,id',
            'care_group'       => 'required|in:ci_isolation,psychiatric,chronic',
            'drug_name'        => 'required|string|max:255',
            'dosage'           => 'nullable|string|max:100',
            'meal_timing'      => 'required|string|max:50',
            'slots'            => 'nullable|array',
            'custom_slot_text' => 'nullable|string|max:255',
            'start_date'       => 'required|date',
            'instruction_note' => 'nullable|string|max:255',
        ]);

        $slots       = is_array($data['slots'] ?? null) ? $data['slots'] : [];
        $takeMorning = in_array('morning', $slots);
        $takeNoon    = in_array('noon', $slots);
        $takeEvening = in_array('evening', $slots);
        $takeBedtime = in_array('bedtime', $slots);
        $takePrn     = in_array('prn', $slots);
        $customText  = trim((string)($data['custom_slot_text'] ?? ''));

        // หากไม่ได้ติ๊กมื้อ เช้า-เที่ยง-เย็น-ก่อนนอน เลย หรือมีการพิมพ์ช่องว่าง/เลือกยาทา/เวลามีอาการ ให้เปิดใช้แถว PRN อัตโนมัติ
        if ((!$takeMorning && !$takeNoon && !$takeEvening && !$takeBedtime) || $customText !== '' || in_array($data['meal_timing'], ['prn', 'external'])) {
            if (!$takeMorning && !$takeNoon && !$takeEvening && !$takeBedtime) {
                $takePrn = true;
            }
            if ($takePrn && $customText === '') {
                $customText = $data['meal_timing'] === 'external'
                    ? 'ทา/นวดบริเวณที่มีอาการ'
                    : 'กินเวลามีอาการ (PRN)';
            }
        }

        $payload = [
            'patient_id'       => $data['patient_id'],
            'care_group'       => $data['care_group'],
            'drug_name'        => $data['drug_name'],
            'dosage'           => !empty($data['dosage']) ? $data['dosage'] : ($data['meal_timing'] === 'external' ? 'ทาบางๆ บริเวณที่มีอาการ' : 'ครั้งละ 1 เม็ด'),
            'meal_timing'      => $data['meal_timing'],
            'take_morning'     => $takeMorning,
            'take_noon'        => $takeNoon,
            'take_evening'     => $takeEvening,
            'take_bedtime'     => $takeBedtime,
            'take_prn'         => $takePrn,
            'custom_slot_text' => $customText !== '' ? $customText : null,
            'start_date'       => $data['start_date'],
            'is_active'        => true,
            'instruction_note' => $data['instruction_note'] ?? null,
            'recorded_by'      => auth()->id(),
        ];

        if (!empty($data['medication_id'])) {
            \App\Models\PatientMedication::where('id', $data['medication_id'])
                ->where('patient_id', $data['patient_id'])
                ->update($payload);
            return back()->with('success', "อัปเดตแก้ไขรายการยา \"{$data['drug_name']}\" เรียบร้อยแล้ว!");
        }

        \App\Models\PatientMedication::create($payload);
        return back()->with('success', "เพิ่มรายการยา \"{$data['drug_name']}\" เรียบร้อยแล้ว!");
    }

    public function destroyMedication($id)
    {
        self::ensureCareChartSchema();

        DB::table('patient_medication_logs')->where('patient_medication_id', $id)->delete();
        DB::table('patient_medications')->where('id', $id)->delete();

        return back()->with('success', 'ลบรายการยาเรียบร้อยแล้ว');
    }

    public function toggleMedLog(Request $request)
    {
        self::ensureCareChartSchema();

        $medId    = $request->input('patient_medication_id');
        $logDate  = $request->input('log_date');
        $timeSlot = $request->input('time_slot');

        $existing = DB::table('patient_medication_logs')
            ->where('patient_medication_id', $medId)
            ->whereDate('log_date', $logDate)
            ->where('time_slot', $timeSlot)
            ->first();

        $nextState = 'taken';
        if (!$existing) {
            $med = DB::table('patient_medications')->where('id', $medId)->first();
            DB::table('patient_medication_logs')->insert([
                'patient_medication_id' => $medId,
                'patient_id'            => $med->patient_id ?? null,
                'log_date'              => $logDate,
                'time_slot'             => $timeSlot,
                'status'                => 'taken',
                'recorded_by'           => auth()->id(),
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
            $nextState = 'taken';
        } elseif ($existing->status === 'taken') {
            DB::table('patient_medication_logs')->where('id', $existing->id)->update([
                'status'      => 'missed',
                'recorded_by' => auth()->id(),
                'updated_at'  => now(),
            ]);
            $nextState = 'missed';
        } else {
            DB::table('patient_medication_logs')->where('id', $existing->id)->delete();
            $nextState = 'none';
        }

        return response()->json([
            'status' => 'success',
            'state'  => $nextState,
        ]);
    }

    // 🏥 ดึงรายการยาล่าสุดจาก HosXP อย่างปลอดภัย (Two-Step Indexed Query ด้วย HN -> VN)
    public function hosxpDrugs($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        if (empty($patient->hn)) {
            return response()->json(['status' => 'error', 'message' => 'พลทหารนายนี้ยังไม่มีรหัส HN ครับ']);
        }

        try {
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

            $latestVisit = DB::connection('hosxp')
                ->table('ovst')
                ->where('hn', $patient->hn)
                ->orderBy('vstdate', 'desc')
                ->orderBy('vsttime', 'desc')
                ->select('vn', 'vstdate')
                ->first();

            if (!$latestVisit) {
                return response()->json(['status' => 'not_found', 'message' => 'ไม่พบประวัติการมารับบริการใน HosXP']);
            }

            $drugs = DB::connection('hosxp')
                ->table('opitemrece as o')
                ->join('drugitems as d', 'o.icode', '=', 'd.icode')
                ->leftJoin('drugusage as u', 'o.drugusage', '=', 'u.drugusage')
                ->where('o.vn', $latestVisit->vn)
                ->where('o.qty', '>', 0)
                ->select('d.name as drug_name', 'd.strength', 'u.shortlist', 'u.name1', 'u.name2')
                ->get()
                ->map(function ($item) use ($cleanUTF8) {
                    $name  = trim($cleanUTF8($item->drug_name) . ' ' . $cleanUTF8($item->strength));
                    $usage = trim($cleanUTF8($item->name1) . ' ' . $cleanUTF8($item->name2) . ' ' . $cleanUTF8($item->shortlist));
                    return [
                        'drug_name' => $name,
                        'usage'     => $usage ?: 'ครั้งละ 1 เม็ด',
                    ];
                })->values();

            if ($drugs->isEmpty()) {
                return response()->json(['status' => 'not_found', 'message' => 'ไม่พบรายการสั่งจ่ายยาใน Visit ล่าสุด']);
            }

            return response()->json(['status' => 'success', 'data' => $drugs]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล HosXP ได้ในขณะนี้']);
        }
    }
}