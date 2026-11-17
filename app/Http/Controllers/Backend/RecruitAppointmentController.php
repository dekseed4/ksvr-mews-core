<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\MilitaryUnit;
use App\Models\RecruitAppointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecruitAppointmentController extends Controller
{
    // ตรวจสอบสิทธิ์บุคลากรส่วนกลางของ รพ. (เห็นได้ทุกหน่วยฝึก)
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

    public function index(Request $request)
    {
        $user = auth()->user();
        $today = Carbon::today()->format('Y-m-d');
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        // 1. ดึงรายชื่อผลัดและหน่วยฝึกตามสิทธิ์
        $batchesQuery = Patient::whereNotNull('intake_batch')->where('intake_batch', '!=', '');
        $this->applyMedicScope($batchesQuery, $user);
        $batches = $batchesQuery->distinct()->pluck('intake_batch')->sortDesc();

        $selectedBatch     = $request->input('intake_batch', $batches->first() ?? '2/69');
        $selectedUnit      = $request->input('unit_id', '');
        $selectedType      = $request->input('hospital_type', '');
        $selectedPatientId = $request->input('patient_id', '');
        $selectedScope     = $request->input('date_scope', !empty($selectedPatientId) ? 'all' : 'upcoming');

        // 🌟 รับค่าจำนวนรายชื่อต่อหน้า (รองรับ 10, 20, 50, 100 — ค่าเริ่มต้น 10 รายชื่อ)
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 20, 50, 100])) {
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

        // 🔔 ดึงรายการแจ้งเตือนล่วงหน้า 1 วัน (นัดพรุ่งนี้) และนัดวันนี้
        $alertAppointments = (clone $baseQuery)
            ->where('status', 'scheduled')
            ->whereIn(DB::raw('DATE(appointment_date)'), [$today, $tomorrow])
            ->orderBy('military_unit_id', 'asc')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get()
            ->map(function ($app) use ($today) {
                $pt = $app->patient;
                $isToday = $app->appointment_date->format('Y-m-d') === $today;
                return [
                    'id'            => $app->id,
                    'full_name'     => ($pt->rank ?? 'พลฯ') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? ''),
                    'hn'            => $pt->hn ?: 'ยังไม่มี HN',
                    'unit'          => $app->militaryUnit->short_name ?? $pt->militaryUnit->short_name ?? '-',
                    'day_badge'     => $isToday ? '🚨 นัดวันนี้!' : '⏰ พรุ่งนี้ (ล่วงหน้า 1 วัน)',
                    'is_today'      => $isToday,
                    'date_thai'     => $app->appointment_date->addYears(543)->format('d/m/Y'),
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

        // 🌟 เรียงลำดับตาม ผลัด -> หน่วยฝึก -> สถานะ -> วันเวลานัด เพื่อให้ตารางจัดกลุ่มแยกตามหน่วยได้อย่างสวยงาม
        $appointments = $listQuery->orderBy('intake_batch', 'desc')
            ->orderByRaw('COALESCE(military_unit_id, 9999) ASC')
            ->orderByRaw("CASE WHEN status = 'scheduled' THEN 0 ELSE 1 END")
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->paginate($perPage)
            ->appends($request->query());

        // 4. รายชื่อพลทหารสำหรับเลือกตอนเพิ่มวันนัดใหม่
        $patientsQuery = Patient::with('militaryUnit')->doesntHave('militaryDischarge')->orderBy('first_name');
        $this->applyMedicScope($patientsQuery, $user);
        if ($selectedBatch !== 'all') {
            $patientsQuery->where('intake_batch', $selectedBatch);
        }
        $batchPatients = $patientsQuery->get(['id', 'cid', 'hn', 'rank', 'first_name', 'last_name', 'military_unit_id', 'intake_batch']);

        return view('backend.recruit_appointments.index', compact(
            'batches', 'selectedBatch', 'units', 'selectedUnit', 'selectedType', 'selectedScope',
            'summary', 'alertAppointments', 'appointments', 'batchPatients', 'focusedPatient', 'perPage'
        ));
    }
    
    // 🌟 บันทึกการนัดพบแพทย์ใหม่ (พร้อมระบบตรวจสอบข้อมูลครบทุกช่องและข้อความแจ้งเตือนภาษาไทย)
    public function store(Request $request)
    {
        $user = auth()->user();

        $data =$request->validate([
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

        if ($patient->militaryDischarge) {
            return back()->with('error', "ไม่สามารถลงนัดได้ เนื่องจาก {$patient->rank}{$patient->first_name} อยู่ในทะเบียนปลดทหารแล้วครับ");
        }

        // ตรวจสอบสิทธิ์เสนารักษ์ว่าตรงหน่วยหรือไม่
        if (!$this->canViewAllUnits($user) && $patient->military_unit_id !==$user->military_unit_id) {
            abort(403, 'คุณสามารถลงนัดพบแพทย์เฉพาะทหารในหน่วยฝึกของคุณเท่านั้นครับ');
        }

        $defaultHospName =$data['hospital_type'] === 'camp_hospital' ? 'รพ.ค่ายกฤษณ์สีวะรา' : 'รพ.สาธารณสุข (ส่งต่อ)';

        RecruitAppointment::create([
            'patient_id'       => $patient->id,
            'military_unit_id' => $patient->military_unit_id,
            'intake_batch'     => $patient->intake_batch ?: '2/69',
            'hospital_type'    => $data['hospital_type'],
            'hospital_name'    => ($data['hospital_type'] === 'camp_hospital') ? 'รพ.ค่ายกฤษณ์สีวะรา' : ($data['hospital_name'] ?:$defaultHospName),
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'] ?: '08:30', 'clinic_name'      =>$data['clinic_name'],
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
        $user = auth()->user();

        if (!$this->canViewAllUnits($user) && $appointment->military_unit_id !== $user->military_unit_id) {
            abort(403, 'ไม่มีสิทธิ์จัดการรายการของหน่วยอื่นครับ');
        }

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
        $user = auth()->user();

        if (!$this->canViewAllUnits($user) && $appointment->military_unit_id !== $user->military_unit_id) {
            abort(403, 'ไม่มีสิทธิ์ลบรายการของหน่วยอื่นครับ');
        }

        $appointment->delete();
        return back()->with('success', 'ลบรายการนัดพบแพทย์เรียบร้อยแล้ว');
    }

    // =========================================================================
    // 🔄 ฟังก์ชันดึงข้อมูลวันนัดพบแพทย์จากตาราง `oapp` ของ HosXP อัตโนมัติ (Read-Only & Fast)
    // =========================================================================
    public function syncFromHosxp(Request $request)
    {
        $user = auth()->user();
        $batch = $request->input('intake_batch', '2/69');
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
                    'message' => 'ไม่พบรายชื่อพลทหารที่มีรหัส HN สำหรับซิงค์ข้อมูลวันนัดจาก HosXP ครับ'
                ]);
            }

            $hnMap = [];
            foreach ($patients as $pt) {
                $hnMap[trim($pt->hn)] = $pt;
            }

            // ดึงข้อมูลจากตาราง oapp ของ HosXP เฉพาะนัดตั้งแต่วันนี้เป็นต้นไป (ใช้ Index hn และ nextdate เร็วมาก)
            $hosxpAppointments = DB::connection('hosxp')
                ->table('oapp')
                ->leftJoin('clinic', 'oapp.clinic', '=', 'clinic.clinic')
                ->leftJoin('doctor', 'oapp.doctor', '=', 'doctor.code')
                ->whereIn('oapp.hn', array_keys($hnMap))
                ->whereDate('oapp.nextdate', '>=', Carbon::today()->format('Y-m-d'))
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

            $cleanUTF8 = function ($str) {
                if (empty($str)) return '';
                $utf8 = @iconv('TIS-620', 'UTF-8//IGNORE', (string)$str);
                return trim(mb_convert_encoding($utf8 ?: (string)$str, 'UTF-8', 'UTF-8'));
            };

            $syncedCount = 0;
            foreach ($hosxpAppointments as $oa) {
                $hn = $cleanUTF8($oa->hn);
                if (!isset($hnMap[$hn]) || empty($oa->nextdate)) continue;

                $pt = $hnMap[$hn];
                $timeStr = !empty($oa->nexttime) ? substr($cleanUTF8($oa->nexttime), 0, 5) : '08:30';
                $clinicName = $cleanUTF8($oa->clinic_name);
                $doctorName = $cleanUTF8($oa->doctor_name);
                $appCause   = $cleanUTF8($oa->app_cause);
                $noteText   = $cleanUTF8($oa->note);
                $contactPt  = $cleanUTF8($oa->contact_point);

                $fullNote = implode(' | ', array_filter([$noteText, $contactPt ? "จุดติดต่อ: {$contactPt}" : '']));

                RecruitAppointment::updateOrCreate(
                    ['hosxp_oapp_id' => $oa->oapp_id],
                    [
                        'patient_id'       => $pt->id,
                        'military_unit_id' => $pt->military_unit_id,
                        'intake_batch'     => $pt->intake_batch ?: '2/69',
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

            return response()->json([
                'status'       => 'success',
                'synced_count' => $syncedCount,
                'message'      => $syncedCount > 0
                    ? "ดึงข้อมูลวันนัดพบแพทย์จาก HosXP สำเร็จ {$syncedCount} รายการ!"
                    : "ตรวจสอบกับ HosXP เรียบร้อยแล้ว ไม่พบรายการนัดพบแพทย์ล่วงหน้าเพิ่มเติมครับ",
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'ไม่สามารถเชื่อมต่อตาราง oapp ของ HosXP ได้: ' . $e->getMessage()
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }
}