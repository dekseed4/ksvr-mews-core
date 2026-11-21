<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MilitaryUnit;
use App\Models\Patient;
use App\Models\RecruitAppointment;
use App\Services\AuditLog;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecruitAppointmentController extends Controller
{
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

    // ตรวจสอบสิทธิ์บุคลากรส่วนกลางของ รพ. (เห็นได้ทุกหน่วยฝึก)
    private function canViewAllUnits($user): bool
    {
        $roles = $this->getUserRoles($user);
        return !empty(array_intersect(
            ['admin', 'er_nurse', 'opd_nurse', 'executive', 'registration', 'medical_staff', 'screening'],
            $roles
        ));
    }

    // จำกัดขอบเขตข้อมูลสำหรับ "เสนารักษ์" ให้เห็นและจัดการเฉพาะหน่วยฝึกและผลัดของตนเอง
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

    private function authorizeUnitAndBatch($user, $unitId, $batch = null): void
    {
        if ($this->canViewAllUnits($user)) {
            return;
        }

        if ((int) $unitId !== (int) $user->military_unit_id) {
            abort(403, 'คุณสามารถจัดการรายการนัดพบแพทย์เฉพาะทหารในหน่วยฝึกของคุณเท่านั้นครับ');
        }

        if (!empty($user->intake_batch) && !empty($batch) && $batch !== $user->intake_batch) {
            abort(403, 'คุณไม่มีสิทธิ์จัดการรายการของทหารใหม่ต่างผลัดครับ');
        }
    }

    // 🛡️ HosXP Safety Helpers
    private function getDefaultActiveBatch(): string
    {
        return class_exists(SettingService::class)
            ? (trim((string) SettingService::get('active_intake_batch', '2/69')) ?: '2/69')
            : '2/69';
    }

    private function isHosxpSyncPaused(): bool
    {
        return class_exists(SettingService::class)
            ? (bool) SettingService::get('hosxp_sync_paused', false)
            : false;
    }

    private function getHosxpMaxBatchSize(): int
    {
        $size = class_exists(SettingService::class)
            ? (int) SettingService::get('hosxp_max_batch_size', 50)
            : 50;

        return max(10, min(200, $size));
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

    private function cleanHosxpUtf8($str): string
    {
        if (empty($str)) {
            return '';
        }
        $utf8 = @iconv('TIS-620', 'UTF-8//IGNORE', (string) $str);
        return trim(mb_convert_encoding($utf8 ?: (string) $str, 'UTF-8', 'UTF-8'));
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $today = Carbon::today()->format('Y-m-d');
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        // จำนวนวันแจ้งเตือนล่วงหน้าจากหน้าตั้งค่าระบบ (ค่าเริ่มต้น 1 วัน)
        $alertDays = class_exists(SettingService::class)
            ? max(1, min(7, (int) SettingService::get('appointment_alert_days', 1)))
            : 1;
        $alertEndDate = Carbon::today()->addDays($alertDays)->format('Y-m-d');

        // 1. ดึงรายชื่อผลัดและหน่วยฝึกตามสิทธิ์
        $batchesQuery = Patient::whereNotNull('intake_batch')->where('intake_batch', '!=', '');
        $this->applyMedicScope($batchesQuery, $user);
        $batches = $batchesQuery->distinct()->pluck('intake_batch')->sortDesc();

        $defaultBatch = $batches->contains($this->getDefaultActiveBatch())
            ? $this->getDefaultActiveBatch()
            : ($batches->first() ?? $this->getDefaultActiveBatch());

        $selectedBatch     = $request->input('intake_batch', $defaultBatch);
        $selectedUnit      = $request->input('unit_id', '');
        $selectedType      = $request->input('hospital_type', '');
        $selectedPatientId = $request->input('patient_id', '');
        $selectedScope     = $request->input('date_scope', !empty($selectedPatientId) ? 'all' : 'upcoming');

        // รับค่าจำนวนรายชื่อต่อหน้า (รองรับ 10, 20, 50, 100 — ค่าเริ่มต้น 10 รายชื่อ)
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 10;
        }

        $focusedPatient = null;
        if (!empty($selectedPatientId)) {
            $focusedPatient = Patient::with('militaryUnit')->find($selectedPatientId);
            if ($focusedPatient && !empty($focusedPatient->intake_batch) && !$request->has('intake_batch')) {
                $selectedBatch = $focusedPatient->intake_batch;
            }
        }

        $unitsQuery = MilitaryUnit::where('is_active', true)->orderBy('name');
        if (!$this->canViewAllUnits($user)) {
            $unitsQuery->where('id', $user->military_unit_id);
        }
        $units = $unitsQuery->get();

        // 2. Query ฐานข้อมูลนัดพบแพทย์
        $baseQuery = RecruitAppointment::with(['patient.militaryUnit', 'militaryUnit']);
        $this->applyMedicScope($baseQuery, $user);

        if ($selectedBatch !== 'all') {
            $baseQuery->where('intake_batch', $selectedBatch);
        }
        if (!empty($selectedUnit)) {
            $baseQuery->where('military_unit_id', $selectedUnit);
        }

        // คำนวณตัวเลข KPI ภาพรวม
        $summary = [
            'tomorrow_count' => (clone $baseQuery)->where('status', 'scheduled')->whereDate('appointment_date', $tomorrow)->count(),
            'today_count'    => (clone $baseQuery)->where('status', 'scheduled')->whereDate('appointment_date', $today)->count(),
            'camp_count'     => (clone $baseQuery)->where('status', 'scheduled')->where('hospital_type', 'camp_hospital')->whereDate('appointment_date', '>=', $today)->count(),
            'public_count'   => (clone $baseQuery)->where('status', 'scheduled')->where('hospital_type', 'public_hospital')->whereDate('appointment_date', '>=', $today)->count(),
        ];

        // 🔔 ดึงรายการแจ้งเตือนนัดวันนี้ และล่วงหน้าตามค่า appointment_alert_days (ใช้ whereBetween เพื่อให้ MySQL ใช้ Index)
        $alertAppointments = (clone $baseQuery)
            ->where('status', 'scheduled')
            ->whereBetween('appointment_date', [$today, $alertEndDate])
            ->orderBy('military_unit_id', 'asc')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get()
            ->map(function ($app) use ($today, $tomorrow) {
                $pt = $app->patient;
                $appDateStr = $app->appointment_date->format('Y-m-d');
                $isToday = $appDateStr === $today;
                $isTomorrow = $appDateStr === $tomorrow;
                $daysAhead = Carbon::today()->diffInDays($app->appointment_date->copy()->startOfDay(), false);

                $dayBadge = $isToday
                    ? '🚨 นัดวันนี้!'
                    : ($isTomorrow ? '⏰ พรุ่งนี้ (ล่วงหน้า 1 วัน)' : "📅 อีก {$daysAhead} วัน");

                return [
                    'id'            => $app->id,
                    'full_name'     => ($pt->rank ?? 'พลฯ') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? ''),
                    'hn'            => $pt->hn ?: 'ยังไม่มี HN',
                    'unit'          => $app->militaryUnit->short_name ?? $pt->militaryUnit->short_name ?? '-',
                    'day_badge'     => $dayBadge,
                    'is_today'      => $isToday,
                    'date_thai'     => $app->appointment_date->copy()->addYears(543)->format('d/m/Y'),
                    'time'          => $app->appointment_time ?: '08:30 น.',
                    'hospital_type' => $app->hospital_type,
                    'hospital_name' => $app->hospital_name ?: ($app->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายกฤษณ์สีวะรา'),
                    'clinic'        => $app->clinic_name ?: 'ไม่ระบุคลินิก',
                    'cause'         => $app->app_cause ?: ($app->note ?: 'พบแพทย์ตามนัด'),
                ];
            });

        // 3. กรองรายการในตารางตามแท็บ หรือตามพลทหารรายบุคคล (patient_id)
        $listQuery = clone $baseQuery;
        if (!empty($selectedPatientId)) {
            $listQuery->where('patient_id', $selectedPatientId);
        }
        if (!empty($selectedType)) {
            $listQuery->where('hospital_type', $selectedType);
        }

        if ($selectedScope === 'tomorrow') {
            $listQuery->whereDate('appointment_date', $tomorrow);
        } elseif ($selectedScope === 'today') {
            $listQuery->whereDate('appointment_date', $today);
        } elseif ($selectedScope === 'upcoming') {
            $listQuery->whereDate('appointment_date', '>=', $today);
        } elseif ($selectedScope === 'past') {
            $listQuery->whereDate('appointment_date', '<', $today);
        }

        // เรียงลำดับตาม ผลัด -> หน่วยฝึก -> สถานะ -> วันเวลานัด
        $appointments = $listQuery->orderBy('intake_batch', 'desc')
            ->orderByRaw('COALESCE(military_unit_id, 9999) ASC')
            ->orderByRaw("CASE WHEN status = 'scheduled' THEN 0 ELSE 1 END")
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->paginate($perPage)
            ->appends($request->query());

        // 4. รายชื่อพลทหารสำหรับเลือกตอนเพิ่มวันนัดใหม่
        $patientsQuery = Patient::with('militaryUnit')
            ->doesntHave('militaryDischarge')
            ->orderBy('first_name');
        $this->applyMedicScope($patientsQuery, $user);
        if ($selectedBatch !== 'all') {
            $patientsQuery->where('intake_batch', $selectedBatch);
        }
        $batchPatients = $patientsQuery->get(['id', 'cid', 'hn', 'rank', 'first_name', 'last_name', 'military_unit_id', 'intake_batch']);

        $hosxpSyncPaused = $this->isHosxpSyncPaused();

        return view('backend.recruit_appointments.index', compact(
            'batches', 'selectedBatch', 'units', 'selectedUnit', 'selectedType', 'selectedScope',
            'summary', 'alertAppointments', 'appointments', 'batchPatients', 'focusedPatient', 'perPage',
            'hosxpSyncPaused', 'alertDays'
        ));
    }

    // 🌟 บันทึกการนัดพบแพทย์ใหม่
    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'patient_id'       => 'required|exists:patients,id',
            'hospital_type'    => 'required|in:camp_hospital,public_hospital',
            'hospital_name'    => 'required_if:hospital_type,public_hospital|nullable|string|max:150',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|string|max:20',
            'clinic_name'      => 'required|string|max:150',
            'doctor_name'      => 'nullable|string|max:150',
            'app_cause'        => 'required|string|max:255',
            'note'             => 'nullable|string|max:500',
        ], [
            'patient_id.required'       => 'กรุณาค้นหาและคลิกเลือกรายชื่อพลทหารจากรายการ',
            'patient_id.exists'         => 'ไม่พบข้อมูลพลทหารที่เลือกในระบบ',
            'hospital_type.required'    => 'กรุณาเลือกประเภทสถานพยาบาลที่นัด',
            'hospital_name.required_if' => 'กรุณาระบุชื่อโรงพยาบาลสาธารณสุขปลายทาง',
            'appointment_date.required' => 'กรุณาระบุวันที่นัดพบแพทย์',
            'appointment_date.date'     => 'รูปแบบวันที่นัดพบแพทย์ไม่ถูกต้อง',
            'appointment_time.required' => 'กรุณาระบุเวลานัดพบแพทย์',
            'clinic_name.required'      => 'กรุณาระบุคลินิกหรือแผนกที่นัด (เช่น อายุรกรรม, ห้องฉุกเฉิน, ตรวจโรคทั่วไป)',
            'app_cause.required'        => 'กรุณาระบุสาเหตุการนัดหรืออาการที่ติดตาม',
        ]);

        $patient = Patient::with('militaryDischarge')->findOrFail($data['patient_id']);

        if ($patient->militaryDischarge || in_array($patient->duty_status ?? 'active', ['absent', 'imprisoned', 'deceased'], true)) {
            return back()->with('error', "ไม่สามารถลงนัดได้ เนื่องจาก {$patient->rank}{$patient->first_name} อยู่ในสถานะ: " . ($patient->duty_status_label ?? 'ปลดทหารแล้ว'));
        }

        // ตรวจสอบสิทธิ์เสนารักษ์ว่าตรงหน่วยฝึกและผลัดหรือไม่
        $this->authorizeUnitAndBatch($user, $patient->military_unit_id, $patient->intake_batch);

        $defaultHospName = $data['hospital_type'] === 'camp_hospital' ? 'รพ.ค่ายกฤษณ์สีวะรา' : 'รพ.สาธารณสุข (ส่งต่อ)';

        RecruitAppointment::create([
            'patient_id'       => $patient->id,
            'military_unit_id' => $patient->military_unit_id,
            'intake_batch'     => $patient->intake_batch ?: $this->getDefaultActiveBatch(),
            'hospital_type'    => $data['hospital_type'],
            'hospital_name'    => ($data['hospital_type'] === 'camp_hospital') ? 'รพ.ค่ายกฤษณ์สีวะรา' : ($data['hospital_name'] ?: $defaultHospName),
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'] ?: '08:30',
            'clinic_name'      => $data['clinic_name'],
            'doctor_name'      => $data['doctor_name'] ?? null,
            'app_cause'        => $data['app_cause'],
            'note'             => $data['note'] ?? null,
            'status'           => 'scheduled',
            'recorded_by'      => $user->id,
        ]);

        return back()->with('success', "บันทึกวันนัดพบแพทย์ของ {$patient->rank}{$patient->first_name} เรียบร้อยแล้ว");
    }

    // 🌟 อัปเดตสถานะการไปตามนัด (ไปพบแพทย์แล้ว / เลื่อนนัด / ยกเลิก)
    public function updateStatus(Request $request, $id)
    {
        $appointment = RecruitAppointment::findOrFail($id);
        $this->authorizeUnitAndBatch(auth()->user(), $appointment->military_unit_id, $appointment->intake_batch);

        $request->validate([
            'status'           => 'required|in:scheduled,completed,postponed,cancelled',
            'appointment_date' => 'nullable|date',
        ]);

        $updateData = ['status' => $request->status];
        if ($request->filled('appointment_date')) {
            $updateData['appointment_date'] = $request->appointment_date;
        }

        $appointment->update($updateData);

        return back()->with('success', 'อัปเดตสถานะการนัดพบแพทย์เรียบร้อยแล้ว');
    }

    public function destroy($id)
    {
        $appointment = RecruitAppointment::findOrFail($id);
        $this->authorizeUnitAndBatch(auth()->user(), $appointment->military_unit_id, $appointment->intake_batch);

        $appointment->delete();
        return back()->with('success', 'ลบรายการนัดพบแพทย์เรียบร้อยแล้ว');
    }

    // =========================================================================
    // 🔄 ดึงข้อมูลวันนัดพบแพทย์จากตาราง `oapp` ของ HosXP อัตโนมัติ (Read-Only + Chunk Safety)
    // =========================================================================
    public function syncFromHosxp(Request $request)
    {
        // 🛡️ 1. เช็กสวิตช์พักการซิงค์ HosXP ชั่วคราวก่อนยิง Query
        if ($this->isHosxpSyncPaused()) {
            return response()->json([
                'status'  => 'error',
                'paused'  => true,
                'message' => 'ขณะนี้ผู้ดูแลระบบพักการเชื่อมต่อ/ซิงค์ข้อมูล HosXP ชั่วคราว กรุณาลองใหม่ภายหลังครับ',
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $user = auth()->user();
        $batch = $request->input('intake_batch', $this->getDefaultActiveBatch());
        $singlePatientId = $request->input('patient_id');

        try {
            $patientsQuery = Patient::whereNotNull('hn')
                ->where('hn', '!=', '')
                ->where('hn', '!=', '-')
                ->doesntHave('militaryDischarge');

            $this->applyMedicScope($patientsQuery, $user);

            if (!empty($singlePatientId)) {
                $patientsQuery->where('id', $singlePatientId);
            } elseif ($batch !== 'all') {
                $patientsQuery->where('intake_batch', $batch);
            }

            $patients = $patientsQuery->get(['id', 'hn', 'military_unit_id', 'intake_batch', 'rank', 'first_name', 'last_name']);
            if ($patients->isEmpty()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'ไม่พบรายชื่อพลทหารที่มีรหัส HN สำหรับซิงค์ข้อมูลวันนัดจาก HosXP ครับ',
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $hnMap = [];
            foreach ($patients as $pt) {
                $cleanHn = trim((string) $pt->hn);
                if ($cleanHn !== '') {
                    $hnMap[$cleanHn] = $pt;
                }
            }

            // 🛡️ 2. ตั้ง Connection Timeout และแบ่งยิง Query เป็น Batch ย่อยตาม hosxp_max_batch_size เพื่อไม่ให้หนักเครื่อง HIS
            $this->applyHosxpTimeout();
            $todayStr = Carbon::today()->format('Y-m-d');
            $maxBatchSize = $this->getHosxpMaxBatchSize();
            $hnChunks = array_chunk(array_keys($hnMap), $maxBatchSize);

            $syncedCount = 0;
            $defaultActiveBatch = $this->getDefaultActiveBatch();

            foreach ($hnChunks as $hnChunk) {
                // ใช้ where('oapp.nextdate', '>=', ...) แทน whereDate เพื่อให้ MySQL ใช้ Index บน nextdate ได้เต็มที่
                $hosxpAppointments = DB::connection('hosxp')
                    ->table('oapp')
                    ->leftJoin('clinic', 'oapp.clinic', '=', 'clinic.clinic')
                    ->leftJoin('doctor', 'oapp.doctor', '=', 'doctor.code')
                    ->whereIn('oapp.hn', $hnChunk)
                    ->where('oapp.nextdate', '>=', $todayStr)
                    ->select(
                        'oapp.oapp_id',
                        'oapp.hn',
                        'oapp.nextdate',
                        'oapp.nexttime',
                        'oapp.app_cause',
                        'oapp.note',
                        'oapp.contact_point',
                        'clinic.name as clinic_name',
                        'doctor.name as doctor_name'
                    )
                    ->orderBy('oapp.nextdate', 'asc')
                    ->get();

                foreach ($hosxpAppointments as $oa) {
                    $hn = $this->cleanHosxpUtf8($oa->hn);
                    if (!isset($hnMap[$hn]) || empty($oa->nextdate)) {
                        continue;
                    }

                    $pt = $hnMap[$hn];
                    $timeStr    = !empty($oa->nexttime) ? substr($this->cleanHosxpUtf8($oa->nexttime), 0, 5) : '08:30';
                    $clinicName = $this->cleanHosxpUtf8($oa->clinic_name);
                    $doctorName = $this->cleanHosxpUtf8($oa->doctor_name);
                    $appCause   = $this->cleanHosxpUtf8($oa->app_cause);
                    $noteText   = $this->cleanHosxpUtf8($oa->note);
                    $contactPt  = $this->cleanHosxpUtf8($oa->contact_point);

                    $fullNote = implode(' | ', array_filter([$noteText, $contactPt ? "จุดติดต่อ: {$contactPt}" : '']));

                    RecruitAppointment::updateOrCreate(
                        ['hosxp_oapp_id' => $oa->oapp_id],
                        [
                            'patient_id'       => $pt->id,
                            'military_unit_id' => $pt->military_unit_id,
                            'intake_batch'     => $pt->intake_batch ?: $defaultActiveBatch,
                            'hospital_type'    => 'camp_hospital',
                            'hospital_name'    => 'รพ.ค่ายกฤษณ์สีวะรา',
                            'appointment_date' => $oa->nextdate,
                            'appointment_time' => $timeStr,
                            'clinic_name'      => mb_substr($clinicName, 0, 150) ?: 'OPD รพ.ค่ายฯ',
                            'doctor_name'      => mb_substr($doctorName, 0, 150) ?: null,
                            'app_cause'        => mb_substr($appCause ?: $noteText, 0, 250) ?: 'นัดตรวจติดตามอาการ',
                            'note'             => $fullNote ?: null,
                            'recorded_by'      => $user->id,
                        ]
                    );
                    $syncedCount++;
                }
            }

            if (class_exists(AuditLog::class)) {
                AuditLog::record('sync_hosxp_appointments', null, [
                    'batch'         => $batch,
                    'patient_count' => count($hnMap),
                    'synced_count'  => $syncedCount,
                    'chunk_size'    => $maxBatchSize,
                ]);
            }

            return response()->json([
                'status'       => 'success',
                'synced_count' => $syncedCount,
                'message'      => $syncedCount > 0
                    ? "ดึงข้อมูลวันนัดพบแพทย์จาก HosXP สำเร็จ {$syncedCount} รายการ!"
                    : 'ตรวจสอบกับ HosXP เรียบร้อยแล้ว ไม่พบรายการนัดพบแพทย์ล่วงหน้าเพิ่มเติมครับ',
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Log::warning('HosXP Appointment Sync Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'ไม่สามารถเชื่อมต่อตาราง oapp ของ HosXP ได้ในขณะนี้ กรุณาลองใหม่อีกครั้งครับ',
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }
}