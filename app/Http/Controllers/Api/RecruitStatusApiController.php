<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MilitaryUnit;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class RecruitStatusApiController extends Controller
{
    /**
     * GET /api/v1/recruits/training-status
     * ตัวเลือก Query Params:
     * - intake_batch (เช่น 2/69 หรือ all)
     * - unit_id (กรองเฉพาะหน่วยฝึก)
     */
    public function trainingStatus(Request $request)
    {
        $batch  = trim((string) $request->input('intake_batch', ''));
        $unitId = $request->input('unit_id');

        // หากไม่ระบุผลัด ให้ดึงผลัดล่าสุดในระบบอัตโนมัติ
        if ($batch === '') {
            $batch = Patient::whereNotNull('intake_batch')
                ->where('intake_batch', '!=', '')
                ->orderByDesc('intake_batch')
                ->value('intake_batch') ?? '2/69';
        }

        $cacheKey = 'ksvr_api_training_status_' . md5($batch . '_' . ($unitId ?? 'all'));

        // แคชผลลัพธ์ 60 วินาที เพื่อลดภาระฐานข้อมูลเมื่อเว็บภายนอกยิงเข้ามาถี่ๆ
        $payload = Cache::remember($cacheKey, 60, function () use ($batch, $unitId) {
            return $this->buildStatusData($batch, $unitId);
        });

        return response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function buildStatusData(string $batch, $unitId = null): array
    {
        $today = Carbon::today()->format('Y-m-d');

        $unitsQuery = MilitaryUnit::where('is_active', true)->orderBy('id');
        if (!empty($unitId)) {
            $unitsQuery->where('id', $unitId);
        }
        $units = $unitsQuery->get();

        // ดึงรายการ patient_id + military_unit_id + duty_status ในขอบเขตผลัดที่เลือกครั้งเดียว (ลด N+1 Query)
        $patientsQuery = Patient::query()->select('id', 'military_unit_id', 'duty_status');
        if ($batch !== 'all') {
            $patientsQuery->where('intake_batch', $batch);
        }
        if (!empty($unitId)) {
            $patientsQuery->where('military_unit_id', $unitId);
        }
        $patients = $patientsQuery->get();

        $patientIds = $patients->pluck('id')->all();
        $patientUnitMap = $patients->pluck('military_unit_id', 'id')->all();

        // เตรียมโครงสร้างตัวนับแยกรายหน่วย
        $unitStats = [];
        foreach ($units as $u) {
            $unitStats[$u->id] = [
                'unit_id'                 => $u->id,
                'unit_name'               => $u->short_name ?: $u->name,
                'unit_full_name'          => $u->name,
                'hospital_name'           => 'รพ.ค่ายกฤษณ์สีวะรา',
                'army_area'               => 'ทภ.2',
                'deceased'                => 0, // 1. เสียชีวิต
                'absent'                  => 0, // 2. หนีราชการ
                'disability_discharge'    => 0, // 3. ปลดพิการ / นำปลด
                'hospitalized'            => 0, // 4. พักรักษาตัวใน รพ.
                'unit_infirmary'          => 0, // 5. เจ็บป่วยพักที่หน่วยฝึก
                'medical_cert_excused'    => 0, // 6. ใบรับรองแพทย์ให้งดฝึก
                'reduced_training'        => 0, // 7. ลดปริมาณการฝึก
                'hospital_checkup_today'  => 0, // 8. ไปตรวจที่ รพ. (วันนี้)
                'other_mission'           => 0, // 9. ไปปฏิบัติภารกิจอื่น
                'ever_hospitalized'       => 0, // 10. เคยป่วยรักษาใน รพ.
            ];
        }

        if (!empty($patientIds)) {
            // 1 & 2. เสียชีวิต และ หนีราชการ (จากตาราง patients)
            foreach ($patients as $p) {
                $uid = $p->military_unit_id;
                if (!isset($unitStats[$uid])) continue;

                if ($p->duty_status === 'deceased') {
                    $unitStats[$uid]['deceased']++;
                } elseif ($p->duty_status === 'absent') {
                    $unitStats[$uid]['absent']++;
                }
            }

            // 3. ปลดพิการ / นำปลด (จากตาราง military_discharges)
            if (Schema::hasTable('military_discharges')) {
                $dcPatientIds = DB::table('military_discharges')
                    ->whereIn('patient_id', $patientIds)
                    ->distinct()
                    ->pluck('patient_id');

                foreach ($dcPatientIds as $pid) {
                    $uid = $patientUnitMap[$pid] ?? null;
                    if ($uid && isset($unitStats[$uid])) {
                        $unitStats[$uid]['disability_discharge']++;
                    }
                }
            }

            // 4. พักรักษาตัวใน รพ. (รวมจาก MEWS Admit/Refer ที่ยังไม่กลับหน่วย + Morbidity IPD + ฝากนอน รพ.)
            $hospitalizedSet = [];

            if (Schema::hasTable('mews_assessments')) {
                $admitMewsPids = DB::table('mews_assessments')
                    ->whereIn('patient_id', $patientIds)
                    ->where('status', 'completed')
                    ->whereIn('discharge_type', ['admitted', 'refer'])
                    ->whereNull('returned_to_unit_at')
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('patient_id');
                foreach ($admitMewsPids as $pid) {
                    $hospitalizedSet[$pid] = true;
                }
            }

            if (Schema::hasTable('recruit_morbidities')) {
                $ipdMorbPids = DB::table('recruit_morbidities')
                    ->whereIn('patient_id', $patientIds)
                    ->where('category', 'other_ipd')
                    ->where('status', 'treating')
                    ->distinct()
                    ->pluck('patient_id');
                foreach ($ipdMorbPids as $pid) {
                    $hospitalizedSet[$pid] = true;
                }
            }

            if (Schema::hasTable('recruit_infirmary_stays')) {
                $campHospStays = DB::table('recruit_infirmary_stays')
                    ->whereIn('patient_id', $patientIds)
                    ->where('status', 'staying')
                    ->where('location_type', 'camp_hospital')
                    ->distinct()
                    ->pluck('patient_id');
                foreach ($campHospStays as $pid) {
                    $hospitalizedSet[$pid] = true;
                }
            }

            foreach (array_keys($hospitalizedSet) as $pid) {
                $uid = $patientUnitMap[$pid] ?? null;
                if ($uid && isset($unitStats[$uid])) {
                    $unitStats[$uid]['hospitalized']++;
                }
            }

            // 5, 6, 7. เจ็บป่วยพักที่หน่วยฝึก + วิเคราะห์คำสั่งงดฝึก/ลดปริมาณการฝึก
            if (Schema::hasTable('recruit_infirmary_stays')) {
                $unitStays = DB::table('recruit_infirmary_stays')
                    ->whereIn('patient_id', $patientIds)
                    ->where('status', 'staying')
                    ->where('location_type', 'unit_infirmary')
                    ->select('patient_id', 'care_instruction')
                    ->get();

                $unitInfSet = [];
                $medCertSet = [];
                $reducedSet = [];

                foreach ($unitStays as $stay) {
                    $pid = $stay->patient_id;
                    $unitInfSet[$pid] = true;

                    $note = (string) ($stay->care_instruction ?? '');
                    if (str_contains($note, 'ใบรับรองแพทย์') || str_contains($note, 'งดฝึกทั้งหมด')) {
                        $medCertSet[$pid] = true;
                    } elseif (str_contains($note, 'งดฝึก') || str_contains($note, 'ลดการฝึก') || str_contains($note, 'งดวิ่ง')) {
                        $reducedSet[$pid] = true;
                    }
                }

                foreach (array_keys($unitInfSet) as $pid) {
                    $uid = $patientUnitMap[$pid] ?? null;
                    if ($uid && isset($unitStats[$uid])) $unitStats[$uid]['unit_infirmary']++;
                }
                foreach (array_keys($medCertSet) as $pid) {
                    $uid = $patientUnitMap[$pid] ?? null;
                    if ($uid && isset($unitStats[$uid])) $unitStats[$uid]['medical_cert_excused']++;
                }
                foreach (array_keys($reducedSet) as $pid) {
                    $uid = $patientUnitMap[$pid] ?? null;
                    if ($uid && isset($unitStats[$uid])) $unitStats[$uid]['reduced_training']++;
                }
            }

            // 8. ไปตรวจที่ รพ. (คิวนัดพบแพทย์วันนี้ หรือ คิวรอตรวจ MEWS วันนี้)
            $checkupTodaySet = [];
            if (Schema::hasTable('recruit_appointments')) {
                $todayApps = DB::table('recruit_appointments')
                    ->whereIn('patient_id', $patientIds)
                    ->where('status', 'scheduled')
                    ->whereDate('appointment_date', $today)
                    ->distinct()
                    ->pluck('patient_id');
                foreach ($todayApps as $pid) {
                    $checkupTodaySet[$pid] = true;
                }
            }
            if (Schema::hasTable('mews_assessments')) {
                $pendingToday = DB::table('mews_assessments')
                    ->whereIn('patient_id', $patientIds)
                    ->where('status', 'pending')
                    ->whereDate('created_at', $today)
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('patient_id');
                foreach ($pendingToday as $pid) {
                    $checkupTodaySet[$pid] = true;
                }
            }
            foreach (array_keys($checkupTodaySet) as $pid) {
                $uid = $patientUnitMap[$pid] ?? null;
                if ($uid && isset($unitStats[$uid])) {
                    $unitStats[$uid]['hospital_checkup_today']++;
                }
            }

            // 10. เคยป่วยรักษาใน รพ. (ยอดสะสมผู้ที่เคย Admit / Refer)
            $everHospSet = [];
            if (Schema::hasTable('mews_assessments')) {
                $everMews = DB::table('mews_assessments')
                    ->whereIn('patient_id', $patientIds)
                    ->where('status', 'completed')
                    ->whereIn('discharge_type', ['admitted', 'refer'])
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('patient_id');
                foreach ($everMews as $pid) {
                    $everHospSet[$pid] = true;
                }
            }
            if (Schema::hasTable('recruit_morbidities')) {
                $everMorb = DB::table('recruit_morbidities')
                    ->whereIn('patient_id', $patientIds)
                    ->where('category', 'other_ipd')
                    ->distinct()
                    ->pluck('patient_id');
                foreach ($everMorb as $pid) {
                    $everHospSet[$pid] = true;
                }
            }
            foreach (array_keys($everHospSet) as $pid) {
                $uid = $patientUnitMap[$pid] ?? null;
                if ($uid && isset($unitStats[$uid])) {
                    $unitStats[$uid]['ever_hospitalized']++;
                }
            }
        }

        // รวมยอดสรุปทั้งหมดสำหรับแสดงบนการ์ดด้านบน 10 ใบ
        $metricKeys = [
            'deceased', 'absent', 'disability_discharge', 'hospitalized',
            'unit_infirmary', 'medical_cert_excused', 'reduced_training',
            'hospital_checkup_today', 'other_mission', 'ever_hospitalized',
        ];

        $summary = array_fill_keys($metricKeys, 0);
        foreach ($unitStats as $row) {
            foreach ($metricKeys as $key) {
                $summary[$key] += $row[$key];
            }
        }

        return [
            'status'       => 'success',
            'source'       => 'KSVR Hospital System (M-CARE)',
            'intake_batch' => $batch,
            'updated_at'   => now()->toIso8601String(),
            'summary'      => $summary,
            'by_unit'      => array_values($unitStats),
        ];
    }
}