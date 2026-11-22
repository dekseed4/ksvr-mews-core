<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CovidVitalLog;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientMedicationLog;
use App\Models\RecruitInfirmaryStay;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RecruitCareChartController extends Controller
{
    private const SCHEMA_CACHE_KEY = 'ksvr_care_chart_schema_verified_v2';
    private bool $hosxpTimeoutApplied = false;

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

    private function authorizePatientAccess(Patient $patient, $user): void
    {
        if ($this->canViewAllUnits($user)) {
            return;
        }

        if ((int) $patient->military_unit_id !== (int) $user->military_unit_id) {
            abort(403, 'คุณสามารถดูและจัดการเฉพาะข้อมูลทหารในหน่วยฝึกของคุณเท่านั้นครับ');
        }

        if (!empty($user->intake_batch) && !empty($patient->intake_batch) && $patient->intake_batch !== $user->intake_batch) {
            abort(403, 'คุณไม่มีสิทธิ์จัดการข้อมูลทหารใหม่ต่างผลัดครับ');
        }
    }

    private function isHosxpSyncPaused(): bool
    {
        return class_exists(SettingService::class)
            ? (bool) SettingService::get('hosxp_sync_paused', false)
            : false;
    }

    private function applyHosxpTimeout(): void
    {
        if ($this->hosxpTimeoutApplied) {
            return;
        }

        $timeout = class_exists(SettingService::class)
            ? (int) SettingService::get('hosxp_timeout_seconds', 5)
            : 5;
        $timeout = max(2, min(30, $timeout));

        $options = config('database.connections.hosxp.options', []);
        if (!is_array($options)) {
            $options = [];
        }
        $options[\PDO::ATTR_TIMEOUT] = $timeout;

        config(['database.connections.hosxp.options' => $options]);
        DB::purge('hosxp');

        $this->hosxpTimeoutApplied = true;
    }

    // 🛡️ ตรวจสอบและอัปเดตคอลัมน์เพียงครั้งเดียวแล้วจำใน Cache (รองรับ end_date, take_prn, custom_slot_text)
    public static function ensureCareChartSchema(): void
    {
        if (Cache::get(self::SCHEMA_CACHE_KEY)) {
            return;
        }

        try {
            if (Schema::hasTable('patient_medications')) {
                if (!Schema::hasColumn('patient_medications', 'take_prn')) {
                    DB::statement('ALTER TABLE patient_medications ADD COLUMN take_prn TINYINT(1) NOT NULL DEFAULT 0 AFTER take_bedtime');
                }
                if (!Schema::hasColumn('patient_medications', 'custom_slot_text')) {
                    DB::statement('ALTER TABLE patient_medications ADD COLUMN custom_slot_text VARCHAR(255) NULL AFTER take_prn');
                }
                if (!Schema::hasColumn('patient_medications', 'end_date')) {
                    DB::statement('ALTER TABLE patient_medications ADD COLUMN end_date DATE NULL AFTER start_date');
                }
                if (!Schema::hasColumn('patient_medications', 'instruction_note')) {
                    DB::statement('ALTER TABLE patient_medications ADD COLUMN instruction_note VARCHAR(255) NULL');
                }
                DB::statement("ALTER TABLE patient_medications MODIFY COLUMN meal_timing VARCHAR(50) NOT NULL DEFAULT 'after_meal'");
            }

            if (Schema::hasTable('patient_medication_logs')) {
                DB::statement('ALTER TABLE patient_medication_logs MODIFY COLUMN time_slot VARCHAR(30) NOT NULL');
            }

            Cache::forever(self::SCHEMA_CACHE_KEY, true);
        } catch (\Throwable $e) {
            // ข้ามกรณีโครงสร้างตารางพร้อมใช้งานแล้ว
        }
    }

    // 🧮 คำนวณคะแนน MEWS อัตโนมัติจากสัญญาณชีพทั้ง 5 ตัว
    public static function calculateMewsScore($temp, $hr, $sbp, $rr, $o2sat): int
    {
        $score = 0;

        if ($temp !== null && $temp !== '') {
            $t = (float) $temp;
            if ($t < 35.0) $score += 2;
            elseif ($t >= 35.0 && $t <= 36.0) $score += 1;
            elseif ($t >= 38.1 && $t <= 38.5) $score += 1;
            elseif ($t > 38.5) $score += 2;
        }

        if ($hr !== null && $hr !== '') {
            $h = (int) $hr;
            if ($h <= 40) $score += 2;
            elseif ($h >= 41 && $h <= 50) $score += 1;
            elseif ($h >= 101 && $h <= 110) $score += 1;
            elseif ($h >= 111 && $h <= 129) $score += 2;
            elseif ($h >= 130) $score += 3;
        }

        if ($sbp !== null && $sbp !== '') {
            $bp = (int) $sbp;
            if ($bp <= 70) $score += 3;
            elseif ($bp >= 71 && $bp <= 80) $score += 2;
            elseif ($bp >= 81 && $bp <= 100) $score += 1;
            elseif ($bp >= 200) $score += 2;
        }

        if ($rr !== null && $rr !== '') {
            $r = (int) $rr;
            if ($r < 9) $score += 2;
            elseif ($r >= 15 && $r <= 20) $score += 1;
            elseif ($r >= 21 && $r <= 29) $score += 2;
            elseif ($r >= 30) $score += 3;
        }

        if ($o2sat !== null && $o2sat !== '') {
            $o2 = (int) $o2sat;
            if ($o2 < 90) $score += 3;
            elseif ($o2 >= 90 && $o2 <= 94) $score += 2;
        }

        return $score;
    }

    // 📋 หน้าแสดงใบบันทึกสัญญาณชีพ (06:00/18:00) และตาราง e-MAR (รองรับ 10 / 14 / 30 วัน + เลื่อนวันไม่จำกัด)
    public function showChart(Request $request, $patient_id)
    {
        self::ensureCareChartSchema();

        $user = auth()->user();
        $patient = Patient::with(['militaryUnit', 'activeInfirmaryStay', 'militaryDischarge'])->findOrFail($patient_id);

        $this->authorizePatientAccess($patient, $user);

        // จำนวนวันที่แสดงในตาราง e-MAR (รองรับ 10, 14, 30 วัน — ค่าเริ่มต้น 10 วัน)
        $days = (int) $request->input('days', 10);
        if (!in_array($days, [7, 10, 14, 30], true)) {
            $days = 10;
        }

        // คำนวณวันเริ่มต้นอย่างฉลาด: หากพักห้องพยาบาลยังไม่เกินช่วงวันที่เลือก ให้เริ่มจากวันแรกรับ แต่ถ้าเลยมาแล้วให้ขยับมาให้เห็น "วันนี้"
        $today = Carbon::today();
        if ($patient->activeInfirmaryStay && $patient->activeInfirmaryStay->admitted_at) {
            $admitDay = $patient->activeInfirmaryStay->admitted_at->copy()->startOfDay();
            $daysSinceAdmit = $admitDay->diffInDays($today, false);
            $defaultStartDate = ($daysSinceAdmit >= 0 && $daysSinceAdmit < $days)
                ? $admitDay->format('Y-m-d')
                : $today->copy()->subDays(min(2, $days - 1))->format('Y-m-d');
        } else {
            $defaultStartDate = $today->format('Y-m-d');
        }

        $startDateStr = (string) $request->input('start_date', $defaultStartDate);
        try {
            $startDate = Carbon::parse($startDateStr)->startOfDay();
            $startDateStr = $startDate->format('Y-m-d');
        } catch (\Throwable $e) {
            $startDate = Carbon::parse($defaultStartDate)->startOfDay();
            $startDateStr = $defaultStartDate;
        }

        $endDate       = $startDate->copy()->addDays($days - 1)->endOfDay();
        $prevStartDate = $startDate->copy()->subDays($days)->format('Y-m-d');
        $nextStartDate = $startDate->copy()->addDays($days)->format('Y-m-d');
        $todayDateStr  = $today->format('Y-m-d');

        $dateColumns = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $startDate->copy()->addDays($i);
            $dTh = $d->copy()->locale('th');
            $dateColumns[] = [
                'date_str'   => $d->format('Y-m-d'),
                'thai_text'  => $dTh->copy()->addYears(543)->translatedFormat('j M Y'),
                'short_thai' => $dTh->translatedFormat('j M'),
                'day_num'    => $d->day,
                'is_today'   => $d->isToday(),
            ];
        }

        // 1. ดึงข้อมูลสัญญาณชีพ 06:00 น. และ 18:00 น. จาก CovidVitalLog
        $vitalLogs = CovidVitalLog::where('patient_id', $patient->id)
            ->whereBetween('record_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->groupBy(fn ($item) => $item->record_date instanceof Carbon ? $item->record_date->format('Y-m-d') : substr((string) $item->record_date, 0, 10));

        // 2. ดึงรายการยาทั้งหมดของผู้ป่วยคนนี้ พร้อมประวัติการกดรับประทานยาในช่วงวันที่เลือก
        $medications = PatientMedication::where('patient_id', $patient->id)
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();

        $medLogs = PatientMedicationLog::where('patient_id', $patient->id)
            ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(function ($log) {
                $dStr = $log->log_date instanceof Carbon ? $log->log_date->format('Y-m-d') : substr((string) $log->log_date, 0, 10);
                return $log->patient_medication_id . '_' . $dStr . '_' . $log->time_slot;
            });

        // 3. รายชื่อเพื่อนพลทหารที่กำลังแยกนอนในห้องพยาบาลเดียวกัน สำหรับปุ่มสลับคนตรวจด่วน
        $infirmaryPeersQuery = RecruitInfirmaryStay::with(['patient.militaryUnit'])
            ->where('status', 'staying')
            ->whereHas('patient');
        $this->applyMedicScope($infirmaryPeersQuery, $user);
        if (!empty($patient->intake_batch)) {
            $infirmaryPeersQuery->where('intake_batch', $patient->intake_batch);
        }
        $infirmaryPeers = $infirmaryPeersQuery->orderBy('military_unit_id')->orderBy('admitted_at', 'desc')->get();

        $hosxpSyncPaused = $this->isHosxpSyncPaused();

        return view('backend.recruit_infirmary.care_chart', compact(
            'patient',
            'startDate',
            'startDateStr',
            'days',
            'prevStartDate',
            'nextStartDate',
            'todayDateStr',
            'dateColumns',
            'vitalLogs',
            'medications',
            'medLogs',
            'infirmaryPeers',
            'hosxpSyncPaused'
        ));
    }

    // 🌡️ บันทึกสัญญาณชีพ 06:00 น. / 18:00 น. พร้อมคำนวณ MEWS Score อัตโนมัติ
    public function storeVital(Request $request)
    {
        $data = $request->validate([
            'patient_id'       => 'required|exists:patients,id',
            'record_date'      => 'required|date',
            'round_time'       => 'required|in:06:00,18:00',
            'temperature'      => 'required|numeric|between:30,45',
            'heart_rate'       => 'required|numeric|between:20,250',
            'systolic_bp'      => 'required|numeric|between:40,260',
            'diastolic_bp'     => 'required|numeric|between:20,180',
            'respiratory_rate' => 'required|numeric|between:5,80',
            'o2sat'            => 'required|numeric|between:50,100',
            'note'             => 'nullable|string|max:255',
        ], [
            'temperature.required'      => 'กรุณากรอกอุณหภูมิ',
            'heart_rate.required'       => 'กรุณากรอกชีพจร',
            'systolic_bp.required'      => 'กรุณากรอกความดันตัวบน',
            'diastolic_bp.required'     => 'กรุณากรอกความดันตัวล่าง',
            'respiratory_rate.required' => 'กรุณากรอกอัตราการหายใจ',
            'o2sat.required'            => 'กรุณากรอกออกซิเจนปลายนิ้ว',
        ]);

        $patient = Patient::findOrFail($data['patient_id']);
        $this->authorizePatientAccess($patient, auth()->user());

        $mewsScore = self::calculateMewsScore(
            $data['temperature'],
            $data['heart_rate'],
            $data['systolic_bp'],
            $data['respiratory_rate'],
            $data['o2sat']
        );

        CovidVitalLog::updateOrCreate(
            [
                'patient_id'  => $patient->id,
                'record_date' => $data['record_date'],
                'round_time'  => $data['round_time'],
            ],
            [
                'temperature'      => $data['temperature'],
                'heart_rate'       => $data['heart_rate'],
                'systolic_bp'      => $data['systolic_bp'],
                'diastolic_bp'     => $data['diastolic_bp'],
                'respiratory_rate' => $data['respiratory_rate'],
                'o2sat'            => $data['o2sat'],
                'mews_score'       => $mewsScore,
                'note'             => $data['note'] ?? null,
                'recorded_by'      => auth()->id(),
            ]
        );

        return back()->with('success', "บันทึกสัญญาณชีพรอบ {$data['round_time']} น. สำเร็จ (MEWS Score: {$mewsScore} คะแนน)");
    }

    // 💊 เพิ่มหรือแก้ไขรายการยา (รองรับยาต่อเนื่องทุกวันไม่มีกำหนด + กำหนดวันสิ้นสุด end_date)
    public function storeMedication(Request $request)
    {
        self::ensureCareChartSchema();

        $data = $request->validate([
            'medication_id'    => 'nullable|integer',
            'patient_id'       => 'required|exists:patients,id',
            'care_group'       => 'required|in:ci_isolation,psychiatric,chronic',
            'drug_name'        => 'required|string|max:200',
            'dosage'           => 'nullable|string|max:100',
            'meal_timing'      => 'required|in:before_meal,after_meal,with_meal,bedtime_only,prn,external',
            'slots'            => 'nullable|array',
            'slots.*'          => 'in:morning,noon,evening,bedtime,prn',
            'custom_slot_text' => 'nullable|string|max:255',
            'start_date'       => 'required|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
            'is_continuous'    => 'nullable|boolean',
            'instruction_note' => 'nullable|string|max:255',
        ], [
            'drug_name.required'      => 'กรุณาระบุชื่อยา (เช่น Paracetamol 500 mg., ยานวดคลายกล้ามเนื้อ)',
            'end_date.after_or_equal' => 'วันที่สิ้นสุดการให้ยาต้องไม่น้อยกว่าวันที่เริ่มให้ยาครับ',
        ]);

        $patient = Patient::findOrFail($data['patient_id']);
        $this->authorizePatientAccess($patient, auth()->user());

        $slots       = is_array($data['slots'] ?? null) ? $data['slots'] : [];
        $takeMorning = in_array('morning', $slots, true);
        $takeNoon    = in_array('noon', $slots, true);
        $takeEvening = in_array('evening', $slots, true);
        $takeBedtime = in_array('bedtime', $slots, true);
        $takePrn     = in_array('prn', $slots, true);
        $customText  = trim((string) ($data['custom_slot_text'] ?? ''));

        if ((!$takeMorning && !$takeNoon && !$takeEvening && !$takeBedtime) || $customText !== '' || in_array($data['meal_timing'], ['prn', 'external'], true)) {
            if (!$takeMorning && !$takeNoon && !$takeEvening && !$takeBedtime) {
                $takePrn = true;
            }
            if ($takePrn && $customText === '') {
                $customText = $data['meal_timing'] === 'external'
                    ? 'ทา/นวดบริเวณที่มีอาการ'
                    : 'กินเวลามีอาการ (PRN)';
            }
        }

        // หากเลือกกินต่อเนื่องทุกวัน (is_continuous = 1) ให้บันทึก end_date เป็น null
        $resolvedEndDate = $request->boolean('is_continuous')
            ? null
            : (!empty($data['end_date']) ? $data['end_date'] : null);

        $payload = [
            'patient_id'       => $patient->id,
            'care_group'       => $data['care_group'],
            'drug_name'        => trim($data['drug_name']),
            'dosage'           => !empty($data['dosage']) ? trim($data['dosage']) : ($data['meal_timing'] === 'external' ? 'ทาบางๆ บริเวณที่มีอาการ' : 'ตามแพทย์สั่ง'),
            'meal_timing'      => $data['meal_timing'],
            'take_morning'     => $takeMorning,
            'take_noon'        => $takeNoon,
            'take_evening'     => $takeEvening,
            'take_bedtime'     => $takeBedtime,
            'take_prn'         => $takePrn,
            'custom_slot_text' => $customText !== '' ? $customText : null,
            'start_date'       => $data['start_date'],
            'end_date'         => $resolvedEndDate,
            'is_active'        => true,
            'instruction_note' => $data['instruction_note'] ?? null,
            'created_by'       => auth()->id(),
        ];

        if (!empty($data['medication_id'])) {
            $med = PatientMedication::where('id', $data['medication_id'])
                ->where('patient_id', $patient->id)
                ->firstOrFail();
            $med->update($payload);
            return back()->with('success', "อัปเดตแก้ไขรายการยา \"{$data['drug_name']}\" เรียบร้อยแล้ว!");
        }

        PatientMedication::create($payload);
        return back()->with('success', "เพิ่มรายการยา \"{$data['drug_name']}\" เรียบร้อยแล้ว!");
    }

    // ✅ กดติ๊กเช็กชื่อการรับประทานยา/ทายาแต่ละมื้อ (เช้า / เที่ยง / เย็น / ก่อนนอน / prn)
    public function toggleMedicationLog(Request $request)
    {
        self::ensureCareChartSchema();

        $data = $request->validate([
            'patient_medication_id' => 'required|exists:patient_medications,id',
            'log_date'              => 'required|date',
            'time_slot'             => 'required|in:morning,noon,evening,bedtime,prn',
        ]);

        $med = PatientMedication::with('patient')->findOrFail($data['patient_medication_id']);
        if ($med->patient) {
            $this->authorizePatientAccess($med->patient, auth()->user());
        }

        $existing = PatientMedicationLog::where('patient_medication_id', $med->id)
            ->whereDate('log_date', $data['log_date'])
            ->where('time_slot', $data['time_slot'])
            ->first();

        if (!$existing) {
            PatientMedicationLog::create([
                'patient_medication_id' => $med->id,
                'patient_id'            => $med->patient_id,
                'log_date'              => $data['log_date'],
                'time_slot'             => $data['time_slot'],
                'status'                => 'taken',
                'given_by'              => auth()->id(),
            ]);
            return response()->json(['status' => 'success', 'state' => 'taken', 'giver' => auth()->user()->name]);
        } elseif ($existing->status === 'taken') {
            $existing->update([
                'status'   => 'missed',
                'given_by' => auth()->id(),
            ]);
            return response()->json(['status' => 'success', 'state' => 'missed', 'giver' => auth()->user()->name]);
        } else {
            $existing->delete();
            return response()->json(['status' => 'success', 'state' => 'empty']);
        }
    }

    public function destroyMedication($id)
    {
        $med = PatientMedication::with('patient')->findOrFail($id);
        if ($med->patient) {
            $this->authorizePatientAccess($med->patient, auth()->user());
        }

        PatientMedicationLog::where('patient_medication_id', $med->id)->delete();
        $med->delete();
        return back()->with('success', 'ลบรายการยาเรียบร้อยแล้ว');
    }

    // 🏥 ดึงรายการยาล่าสุดจากฐานข้อมูล HosXP อัตโนมัติ (Read-Only + Index Optimized + HIS Safety)
    public function fetchHosxpMedications(Request $request, $patient_id)
    {
        if ($this->isHosxpSyncPaused()) {
            return response()->json([
                'status'  => 'error',
                'paused'  => true,
                'message' => 'ขณะนี้ผู้ดูแลระบบพักการเชื่อมต่อฐานข้อมูล HosXP ชั่วคราว กรุณาลองใหม่ภายหลังครับ',
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $patient = Patient::findOrFail($patient_id);
        $this->authorizePatientAccess($patient, auth()->user());

        if (empty($patient->hn)) {
            return response()->json(['status' => 'error', 'message' => 'พลทหารนายนี้ยังไม่มีรหัส HN ในระบบ']);
        }

        try {
            $this->applyHosxpTimeout();

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

            $latestVn = DB::connection('hosxp')
                ->table('ovst')
                ->where('hn', trim((string) $patient->hn))
                ->where('vstdate', '>=', Carbon::today()->subDays(60)->format('Y-m-d'))
                ->orderBy('vstdate', 'desc')
                ->orderBy('vsttime', 'desc')
                ->value('vn');

            if (!$latestVn) {
                return response()->json(['status' => 'not_found', 'message' => 'ไม่พบประวัติการรับยาล่าสุดในรอบ 60 วันจาก HosXP']);
            }

            $drugs = DB::connection('hosxp')
                ->table('opitemrece')
                ->join('drugitems', 'opitemrece.icode', '=', 'drugitems.icode')
                ->leftJoin('drugusage', 'opitemrece.drugusage', '=', 'drugusage.drugusage')
                ->where('opitemrece.vn', $latestVn)
                ->select(
                    'drugitems.name as drug_name',
                    'drugitems.strength',
                    'opitemrece.qty',
                    'drugusage.name1',
                    'drugusage.name2',
                    'drugusage.name3'
                )
                ->limit(15)
                ->get();

            if ($drugs->isEmpty()) {
                return response()->json(['status' => 'not_found', 'message' => 'ไม่พบรายการยาใน Visit ล่าสุดของ HosXP']);
            }

            $list = $drugs->map(function ($d) use ($cleanUTF8) {
                $name = trim($cleanUTF8($d->drug_name) . ' ' . $cleanUTF8($d->strength));
                $usage = trim($cleanUTF8($d->name1) . ' ' . $cleanUTF8($d->name2) . ' ' . $cleanUTF8($d->name3));
                return [
                    'drug_name' => $name,
                    'usage'     => $usage ?: "จำนวนรวม {$d->qty}",
                ];
            });

            return response()->json(['status' => 'success', 'data' => $list], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Log::warning('HosXP Medications Fetch Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อตารางยา HosXP ได้ในขณะนี้']);
        }
    }
}