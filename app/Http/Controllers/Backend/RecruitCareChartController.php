<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\MilitaryUnit;
use App\Models\CovidVitalLog;
use App\Models\PatientMedication;
use App\Models\PatientMedicationLog;
use App\Models\RecruitInfirmaryStay;
use App\Models\RecruitMorbidity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class RecruitCareChartController extends Controller
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

    // 🛡️ ตรวจสอบและเพิ่มคอลัมน์สำหรับยาเวลามีอาการ (take_prn) และช่องพิมพ์วิธีใช้เอง (custom_slot_text) อัตโนมัติ
    public static function ensureCareChartSchema(): void
    {
        try {
            if (Schema::hasTable('patient_medications')) {
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

            if (Schema::hasTable('patient_medication_logs')) {
                DB::statement("ALTER TABLE patient_medication_logs MODIFY COLUMN time_slot VARCHAR(30) NOT NULL");
            }
        } catch (\Throwable $e) {
            // ข้ามกรณีที่อัปเดตโครงสร้างตารางเรียบร้อยแล้ว
        }
    }

    // 🧮 ฟังก์ชันคำนวณคะแนน MEWS อัตโนมัติจากสัญญาณชีพทั้ง 5 ตัว
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

    // 📋 หน้าแสดงใบบันทึกสัญญาณชีพ (06:00/18:00) และตารางบันทึกการรับประทานยา 10 วัน
    public function showChart(Request $request, $patient_id)
    {
        self::ensureCareChartSchema();

        $user = auth()->user();
        $patient = Patient::with(['militaryUnit', 'activeInfirmaryStay', 'militaryDischarge'])->findOrFail($patient_id);

        if (!$this->canViewAllUnits($user) && $patient->military_unit_id !== $user->military_unit_id) {
            abort(403, 'คุณสามารถดูและจัดการเฉพาะข้อมูลทหารในหน่วยฝึกของคุณเท่านั้นครับ');
        }

        $defaultStartDate = $patient->activeInfirmaryStay
            ? $patient->activeInfirmaryStay->admitted_at->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        $startDateStr = $request->input('start_date', $defaultStartDate);
        $startDate = Carbon::parse($startDateStr)->startOfDay();
        $endDate   = $startDate->copy()->addDays(9)->endOfDay();

        $dateColumns = [];
        for ($i = 0; $i < 10; $i++) {
            $d = $startDate->copy()->addDays($i);
            $dateColumns[] = [
                'date_str'  => $d->format('Y-m-d'),
                'thai_text' => $d->locale('th')->addYears(543)->translatedFormat('j M Y'),
                'is_today'  => $d->isToday(),
            ];
        }

        // 1. ดึงข้อมูลสัญญาณชีพ 06:00 น. และ 18:00 น. จาก CovidVitalLog
        $vitalLogs = CovidVitalLog::where('patient_id', $patient->id)
            ->whereBetween('record_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->groupBy(fn($item) => $item->record_date instanceof Carbon ? $item->record_date->format('Y-m-d') : substr((string)$item->record_date, 0, 10));

        // 2. ดึงรายการยาทั้งหมดของผู้ป่วยคนนี้ พร้อมประวัติการกดรับประทานยาในรอบ 10 วัน
        $medications = PatientMedication::where('patient_id', $patient->id)
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();

        $medLogs = PatientMedicationLog::where('patient_id', $patient->id)
            ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(function ($log) {
                $dStr = $log->log_date instanceof Carbon ? $log->log_date->format('Y-m-d') : substr((string)$log->log_date, 0, 10);
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

        return view('backend.recruit_infirmary.care_chart', compact(
            'patient', 'startDate', 'startDateStr', 'dateColumns', 'vitalLogs', 'medications', 'medLogs', 'infirmaryPeers'
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

        $mewsScore = self::calculateMewsScore(
            $data['temperature'],
            $data['heart_rate'],
            $data['systolic_bp'],
            $data['respiratory_rate'],
            $data['o2sat']
        );

        CovidVitalLog::updateOrCreate(
            [
                'patient_id'  => $data['patient_id'],
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

    // 💊 เพิ่มหรือแก้ไขรายการยา (รองรับทั้งมื้อปกติ, เวลามีอาการ PRN, ยานวด และยาทา โดยไม่บังคับติ๊ก 4 มื้อหลัก)
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
            'instruction_note' => 'nullable|string|max:255',
        ], [
            'drug_name.required' => 'กรุณาระบุชื่อยา (เช่น Paracetamol 500 mg., ยานวดคลายกล้ามเนื้อ)',
        ]);

        $slots       = is_array($data['slots'] ?? null) ? $data['slots'] : [];
        $takeMorning = in_array('morning', $slots);
        $takeNoon    = in_array('noon', $slots);
        $takeEvening = in_array('evening', $slots);
        $takeBedtime = in_array('bedtime', $slots);
        $takePrn     = in_array('prn', $slots);
        $customText  = trim((string)($data['custom_slot_text'] ?? ''));

        // หากไม่ได้ติ๊กมื้อ เช้า-เที่ยง-เย็น-ก่อนนอน เลย หรือพิมพ์วิธีใช้ในช่องว่างไว้ ให้เปิดใช้แถว PRN อัตโนมัติ
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
            'is_active'        => true,
            'instruction_note' => $data['instruction_note'] ?? null,
            'note'             => $data['instruction_note'] ?? null,
            'created_by'       => auth()->id(),
        ];

        if (!empty($data['medication_id'])) {
            PatientMedication::where('id', $data['medication_id'])
                ->where('patient_id', $data['patient_id'])
                ->update($payload);
            return back()->with('success', "อัปเดตแก้ไขรายการยา \"{$data['drug_name']}\" เรียบร้อยแล้ว!");
        }

        PatientMedication::create($payload);
        return back()->with('success', "เพิ่มรายการยา \"{$data['drug_name']}\" เรียบร้อยแล้ว!");
    }

    // ✅ กดติ๊กเช็คชื่อการรับประทานยา/ทายาแต่ละมื้อ (เช้า / เที่ยง / เย็น / ก่อนนอน / เวลามีอาการ prn)
    public function toggleMedicationLog(Request $request)
    {
        self::ensureCareChartSchema();

        $data = $request->validate([
            'patient_medication_id' => 'required|exists:patient_medications,id',
            'log_date'              => 'required|date',
            'time_slot'             => 'required|in:morning,noon,evening,bedtime,prn',
        ]);

        $med = PatientMedication::findOrFail($data['patient_medication_id']);

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
        $med = PatientMedication::findOrFail($id);
        PatientMedicationLog::where('patient_medication_id', $med->id)->delete();
        $med->delete();
        return back()->with('success', 'ลบรายการยาเรียบร้อยแล้ว');
    }

    // 🏥 ดึงรายการยาล่าสุดจากฐานข้อมูล HosXP อัตโนมัติ (Read-Only ความเร็วสูง ไม่กระทบเซิร์ฟเวอร์หลัก)
    public function fetchHosxpMedications(Request $request, $patient_id)
    {
        $patient = Patient::findOrFail($patient_id);
        if (empty($patient->hn)) {
            return response()->json(['status' => 'error', 'message' => 'พลทหารนายนี้ยังไม่มีรหัส HN ในระบบ']);
        }

        try {
            $cleanUTF8 = function ($str) {
                if (empty($str)) return '';
                $utf8 = @iconv('TIS-620', 'UTF-8//IGNORE', (string)$str);
                return trim(mb_convert_encoding($utf8 ?: (string)$str, 'UTF-8', 'UTF-8'));
            };

            $latestVn = DB::connection('hosxp')
                ->table('ovst')
                ->where('hn', $patient->hn)
                ->whereDate('vstdate', '>=', Carbon::today()->subDays(60)->format('Y-m-d'))
                ->orderBy('vstdate', 'desc')
                ->orderBy('vsttime', 'desc')
                ->value('vn');

            if (!$latestVn) {
                return response()->json(['status' => 'not_found', 'message' => 'ไม่พบประวัติการรับยาล่าสุดใน HosXP']);
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
            return response()->json(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อตารางยา HosXP ได้ในขณะนี้']);
        }
    }
}