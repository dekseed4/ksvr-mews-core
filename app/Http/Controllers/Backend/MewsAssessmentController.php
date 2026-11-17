<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\MewsAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\AuditLog;
use Illuminate\Support\Facades\Cache;
use App\Events\EmergencyMewsAlert;
use App\Events\PatientMewsUpdated;

class MewsAssessmentController extends Controller
{
    // 🌟 ฟังก์ชันส่วนตัวสำหรับรวม Validation Rules
    private function getValidationRules()
    {
        return [
            'sbp'                  => 'required|numeric',
            'hr'                   => 'required|numeric',
            'rr'                   => 'required|numeric',
            'temp'                 => 'required|numeric',
            'o2sat'                => 'required|numeric',
            'avpu'                 => 'required|string',
            'emergency_conditions' => 'nullable|array',
            'symptoms_note'        => 'nullable|string|max:1000',
            'total_score'          => 'required|numeric',
            'risk_level'           => 'required|string'
        ];
    }

    // 🌟 ฟังก์ชันดึง URL ของ Discord Webhook แบบกันเหนียว 100% (ทำงานได้แม้ติด Config Cache)
    private function getDiscordWebhookUrl(): ?string
    {
        $url = config('services.discord.mews_webhook_url')
            ?: env('DISCORD_MEWS_WEBHOOK_URL')
            ?: env('DISCORD_WEBHOOK_URL');

        // กรณีเซิร์ฟเวอร์ทำ config:cache ไว้ทำให้ env() เป็น null ให้ดึงจากไฟล์ .env โดยตรง
        if (empty($url) && file_exists(base_path('.env'))) {
            $envContent = @file_get_contents(base_path('.env'));
            if ($envContent) {
                if (preg_match('/^DISCORD_MEWS_WEBHOOK_URL\s*=\s*([^\r\n]+)/m', $envContent, $matches)) {
                    $url = trim($matches[1], " \t\n\r\0\x0B\"'");
                } elseif (preg_match('/^DISCORD_WEBHOOK_URL\s*=\s*([^\r\n]+)/m', $envContent, $matches)) {
                    $url = trim($matches[1], " \t\n\r\0\x0B\"'");
                }
            }
        }

        return !empty($url) ? trim($url) : null;
    }

    // 🌟 ฟังก์ชันจัดเรียงลำดับหมวดหมู่อาการฉุกเฉินแยกตามบทบาท (เสนารักษ์ vs พยาบาล รพ.)
    private function getSortedActiveSymptomsForUser($user)
    {
        $roles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        
        // ตรวจสอบว่าเป็น "เสนารักษ์" (ที่ไม่ได้เป็นพยาบาล ER/OPD หรือ Admin)
        $isMedic = in_array('medic', $roles) && !in_array('er_nurse', $roles) && !in_array('opd_nurse', $roles) && !in_array('admin', $roles);

        $grouped = \App\Models\EmergencySymptom::where('is_active', true)
            ->get()
            ->groupBy('category');

        if ($isMedic) {
            // 🎖️ ลำดับความสำคัญสำหรับ "เสนารักษ์ภาคสนาม" (เอาโรคลมร้อนและอุบัติเหตุขึ้นบนสุด!)
            $priorityOrder = [
                'Heat Injury',
                'Trauma',
                'Anaphylaxis',
                'MI',
                'Sepsis',
                'Appendicitis',
                'Stroke',
            ];

            // จัดเรียงรายการย่อยในหมวด Heat Injury ให้ไล่ตามลำดับที่เสนารักษ์พบบ่อยหน้างาน
            if ($grouped->has('Heat Injury')) {
                $heatItemOrder = [
                    'heat_fatigue',   // 1. อ่อนเพลียไม่มีแรง เหงื่อออกมาก (พบบ่อยที่สุด)
                    'heat_pale',      // 2. หน้าซีดหน้ามืด เป็นลม
                    'heat_cramp',     // 3. ตะคริว หายใจหอบเร็วลึก
                    'heat_numbness',  // 4. ชาปลายมือ/เท้า
                    'heat_confusion', // 5. ซึมสับสน ชัก หมดสติ (วิกฤต Heat Stroke)
                    'heat_urine',     // 6. ปัสสาวะสีเข้ม (กล้ามเนื้อสลาย)
                ];
                $grouped['Heat Injury'] = $grouped['Heat Injury']->sortBy(function ($item) use ($heatItemOrder) {
                    $pos = array_search($item->code, $heatItemOrder);
                    return $pos === false ? 99 : $pos;
                })->values();
            }
        } else {
            // 🏥 ลำดับมาตรฐานสำหรับพยาบาล ER / OPD / Admin
            $priorityOrder = [
                'MI',
                'Stroke',
                'Heat Injury',
                'Anaphylaxis',
                'Appendicitis',
                'Sepsis',
                'Trauma',
            ];
        }

        return $grouped->sortBy(function ($items, $categoryKey) use ($priorityOrder) {
            $pos = array_search($categoryKey, $priorityOrder);
            return $pos === false ? 999 : $pos;
        });
    }
    
    public function create($patient_id)
    {
        $user = auth()->user();
        $userRoles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        $isClinical = in_array('admin', $userRoles) 
                        || in_array('er_nurse', $userRoles) 
                        || in_array('opd_nurse', $userRoles) 
                        || in_array('medic', $userRoles)
                        || in_array('medical_staff', $userRoles);

        if (!$isClinical) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงหน้าจอประเมินทางการแพทย์ครับ');
        }

        $patient = \App\Models\Patient::with(['latestMews', 'recruitScreening', 'militaryDischarge', 'upcomingAppointment'])->findOrFail($patient_id);

        // 🚫 ป้องกันชั้นที่ 0: ตรวจสอบว่าพลทหารนายนี้ถูกจำหน่าย/ปลดทหารแล้วหรือไม่
        if ($patient->militaryDischarge) {
            $dx = $patient->militaryDischarge->diagnosis_text ?: 'ไม่ระบุ';
            return redirect()->route('backend.patients.show', $patient->id)
                ->with('error', "ไม่สามารถประเมิน MEWS ได้ เนื่องจาก {$patient->rank}{$patient->first_name} อยู่ในสถานะปลดทหาร/นำปลดแล้ว (โรควินิจฉัย: {$dx})");
        }

        // 1. ดึงใบล่าสุดมาเช็คสถานะการจบเคส
        $latestMews = \App\Models\MewsAssessment::where('patient_id', $patient_id)->orderBy('id', 'desc')->first();

        // ป้องกันชั้นที่ 1: ตรวจสอบว่าเป็นเคสค้าง (Pending) และผู้ใช้ไม่ใช่ ER หรือไม่?
        $isERorAdmin = in_array('admin', $userRoles) || in_array('er_nurse', $userRoles);
        if ($latestMews && $latestMews->status === 'pending' && !$isERorAdmin) {
            return redirect()->route('backend.patients.index')->with('error', 'ผู้ป่วยรายนี้ถูกส่งตัวเข้า ER ไปแล้วครับ การประเมินซ้ำ (ครั้งที่ 2) ต้องเป็นหน้าที่ของพยาบาล ER เท่านั้นครับ');
        }
        
        // 2. นับว่าคนไข้คนนี้มีกี่แฟ้ม (Case/VN) มาแล้วในระบบ
        $totalCases = \App\Models\MewsAssessment::where('patient_id', $patient_id)->distinct('case_id')->count('case_id');

        // 3. ลอจิกแยก "รอบการรักษา (Case)" และ "ครั้งที่ประเมิน (Assessment)"
        if (!$latestMews || in_array($latestMews->status, ['completed', 'cancelled'])) {
            $isReassessment = false;
            $previousCount = 0;
            $currentCaseNumber = $totalCases + 1;
        } else {
            $isReassessment = true;
            $previousCount = \App\Models\MewsAssessment::where('case_id', $latestMews->case_id)->count();
            $currentCaseNumber = $totalCases;
        }

        $activeSymptoms = $this->getSortedActiveSymptomsForUser($user);

        $symptomsDict = \App\Models\EmergencySymptom::pluck('name', 'code')->toArray();
        $activeTemplate = \App\Models\MewsTemplate::where('is_active', true)->first();

        return view('backend.mews.create', compact('patient', 'previousCount', 'isReassessment', 'currentCaseNumber', 'activeSymptoms', 'symptomsDict', 'activeTemplate'));
    }

    public function store(Request $request)
    {
        $rules = $this->getValidationRules();
        $rules['patient_id'] = 'required|exists:patients,id'; 
        $request->validate($rules);

        $lockKey = 'mews_store_lock_' . auth()->id() . '_' . $request->patient_id;

        return Cache::lock($lockKey, 5)->get(function () use ($request) {

            // 1. ดึงข้อมูลคนไข้พร้อมสถานะการปลดทหาร
            $patient = \App\Models\Patient::with(['militaryUnit', 'militaryDischarge'])->findOrFail($request->patient_id);

            // 🚫 บล็อกการบันทึก MEWS ทันทีหากพลทหารอยู่ในสถานะปลดทหารแล้ว
            if ($patient->militaryDischarge) {
                return redirect()->route('backend.patients.show', $patient->id)
                    ->with('error', "ระบบไม่อนุญาตให้บันทึก MEWS เนื่องจาก {$patient->rank}{$patient->first_name} ปลดทหาร/อยู่ในทะเบียนนำปลดแล้วครับ");
            }

            $latestMews = MewsAssessment::where('patient_id', $request->patient_id)->orderBy('id', 'desc')->first();

            // 2. เช็คสิทธิ์ผู้ประเมิน
            $userRoles = is_string(auth()->user()->roles) ? json_decode(auth()->user()->roles, true) : (array)(auth()->user()->roles ?? auth()->user()->role);
            $isERorAdmin = in_array('admin', $userRoles) || in_array('er_nurse', $userRoles);

            // 3. เช็คว่าเป็นการ "เปิดแฟ้มใหม่ (ประเมินครั้งที่ 1)" หรือ "ประเมินซ้ำในแฟ้มเดิม (ครั้งที่ 2+)"
            $isNewCase = (!$latestMews || in_array($latestMews->status, ['completed', 'cancelled']));
            $isSoldier = ($patient->rank === 'พลฯ' || !empty($patient->military_unit_id) || !empty($patient->intake_batch));

            if ($isNewCase) {
                $caseId = 'VN-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $realAssessmentType = ($isSoldier || !$isERorAdmin) ? 'field' : 'er';
            } else {
                if (!$isERorAdmin) {
                    return back()->withInput()->with('error', 'ระบบไม่อนุญาตให้บันทึกข้อมูลซ้ำซ้อน ในแฟ้มประวัติที่ส่งเข้าห้องฉุกเฉินไปแล้วครับ');
                }
                $caseId = $latestMews->case_id;
                $realAssessmentType = 'er';
            }

            // 4. ดึงเกณฑ์ MEWS ที่กำลังเปิดใช้งานอยู่
            $activeTemplate = \App\Models\MewsTemplate::where('is_active', true)->first();
            
            try {
                DB::beginTransaction();

                $assessment = MewsAssessment::create([
                    'patient_id'       => $request->patient_id,
                    'case_id'          => $caseId,
                    'evaluator_id'     => auth()->id(),
                    'mews_template_id' => $activeTemplate ? $activeTemplate->id : null,
                    'assessment_type'  => $realAssessmentType, 
                    
                    'systolic_bp'      => $request->sbp,
                    'heart_rate'       => $request->hr,
                    'respiratory_rate' => $request->rr,
                    'temperature'      => $request->temp,
                    'o2sat'            => $request->o2sat,
                    'consciousness'    => $request->avpu,
                    'symptoms'         => $request->emergency_conditions,
                    'symptoms_note'    => $request->symptoms_note,
                    'total_score'      => $request->total_score,
                    'risk_level'       => $request->risk_level, 
                    'status'           => 'pending',
                ]);

                AuditLog::record('create_mews', $assessment, [
                    'patient_id'      => $assessment->patient_id,
                    'total_score'     => $assessment->total_score,
                    'risk_level'      => $assessment->risk_level,
                    'assessment_type' => $assessment->assessment_type
                ]);

                DB::commit();

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('MEWS Store Error: ' . $e->getMessage());

                return back()->withInput()->with('error', 'ระบบขัดข้อง ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบ');
            }

            // ยิงแจ้งเตือนเข้า Discord เฉพาะ "ประเมินครั้งที่ 1 จากหน่วย (field)" เท่านั้น
            if ($isNewCase && $realAssessmentType === 'field') {
                try {
                    $this->sendDiscordAlert($assessment, false, false);
                    $this->checkAndSendOutbreakAlert($assessment, $patient);
                } catch (\Throwable $discordError) {
                    Log::warning('MEWS Discord Notification Warning: ' . $discordError->getMessage());
                }
            }

            // ยิงสัญญาณ Real-time WebSocket (Reverb) บนหน้าเว็บ
            try {
                broadcast(new PatientMewsUpdated($request->patient_id));

                $hasEmergencyConditions = !empty($request->emergency_conditions) && count($request->emergency_conditions) > 0;
                $isHighRiskMews = in_array($request->risk_level, ['observe', 'emergency']);
                $isRouteToER = $isHighRiskMews || $hasEmergencyConditions;

                if ($isRouteToER && $isNewCase && $realAssessmentType === 'field') {
                    $displayRisk = $request->risk_level;
                    if ($request->risk_level === 'normal' && $hasEmergencyConditions) {
                        $displayRisk = 'emergency_condition';
                    }

                    $patientData = [
                        'mews_id'      => $assessment->id, 
                        'name'         => $patient->rank . $patient->first_name . ' ' . $patient->last_name,
                        'unit'         => $patient->militaryUnit->name ?? $patient->company ?? '-', 
                        'score'        => $request->total_score, 
                        'risk_level'   => $displayRisk,
                        'evaluator_id' => auth()->id() 
                    ];
                    broadcast(new EmergencyMewsAlert($patientData));

                    // 🚀 ยิง Background Web Push ปลุกมือถือ/คอมพิวเตอร์ของพยาบาล ER ทันทีแม้ปิดหน้าจออยู่
                    \App\Services\WebPushService::sendToHospitalRoles(
                        ['er_nurse', 'admin'],
                        "🚨 เคสใหม่ส่งเข้า ER (MEWS: {$request->total_score} คะแนน)",
                        "ผู้ป่วย: {$patient->rank}{$patient->first_name} {$patient->last_name} (" . ($patient->militaryUnit->short_name ?? 'ไม่ระบุหน่วย') . ")",
                        route('backend.mews.show', $assessment->id),
                        "mews-er-{$assessment->id}",
                        true
                    );
                }
            } catch (\Throwable $broadcastError) {
                Log::warning('MEWS WebSocket Broadcast Warning: ' . $broadcastError->getMessage());
            }

            return redirect()->route('backend.patients.show', $request->patient_id)->with('success', 'บันทึกผลการประเมิน MEWS เรียบร้อยแล้ว!');

        }) ?: back()->withInput()->with('error', 'ระบบกำลังบันทึกข้อมูล กรุณาอย่ากดส่งซ้ำครับ!');
    }

    public function show($id)
    {
        $currentMews = MewsAssessment::findOrFail($id);
        
        $patient = Patient::with([
            'mewsAssessments' => function($q) {
                $q->orderBy('created_at', 'desc')->with(['evaluator', 'template']);
            },
            'upcomingAppointment'
        ])->findOrFail($currentMews->patient_id);

        $activeTemplate = \App\Models\MewsTemplate::where('is_active', true)->first();

        return view('backend.mews.show', compact('patient', 'currentMews', 'activeTemplate'));
    }

    public function edit($id)
    {
        $mews = MewsAssessment::with('patient')->findOrFail($id);
        $user = auth()->user();

        $roles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        $isAdmin = in_array('admin', $roles);
        $isCreator = $user->id === $mews->evaluator_id;
        $isSameUnit = $user->military_unit_id !== null && $user->military_unit_id === $mews->patient->military_unit_id;

        if (!$isAdmin && !$isCreator && !$isSameUnit) {
            return back()->with('error', 'คุณไม่มีสิทธิ์แก้ไขข้อมูลประเมินของหน่วยอื่นครับ');
        }

        $activeSymptoms = $this->getSortedActiveSymptomsForUser($user);

        $symptomsDict = \App\Models\EmergencySymptom::pluck('name', 'code')->toArray();

        return view('backend.mews.edit', compact('mews', 'activeSymptoms', 'symptomsDict'));
    }

    public function update(Request $request, $id)
    {
        $request->validate($this->getValidationRules());

        $lockKey = 'mews_update_lock_' . auth()->id() . '_' . $id;

        return Cache::lock($lockKey, 5)->get(function () use ($request, $id) {

            $mews = MewsAssessment::findOrFail($id);
            
            try {
                DB::beginTransaction();

                $mews->update([
                    'systolic_bp'      => $request->sbp,
                    'heart_rate'       => $request->hr,
                    'respiratory_rate' => $request->rr,
                    'temperature'      => $request->temp,
                    'o2sat'            => $request->o2sat,
                    'consciousness'    => $request->avpu,
                    'symptoms'         => $request->emergency_conditions,
                    'symptoms_note'    => $request->symptoms_note,
                    'total_score'      => $request->total_score,
                    'risk_level'       => $request->risk_level,
                ]);

                AuditLog::record('update_mews', $mews, [
                    'patient_id'      => $mews->patient_id,
                    'new_total_score' => $mews->total_score,
                    'new_risk_level'  => $mews->risk_level
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('MEWS Update Error: ' . $e->getMessage());
                return back()->withInput()->with('error', 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล กรุณาลองใหม่อีกครั้ง');
            }

            // (ปิดการยิง Discord ตอนแก้ไข เพื่อให้แจ้งเตือนเฉพาะการประเมินครั้งที่ 1 ตอนเปิดเคสเท่านั้น)

            return redirect()->route('backend.patients.show', $mews->patient_id)->with('success', 'แก้ไขผลการประเมิน MEWS สำเร็จแล้ว!'); 

        }) ?: back()->withInput()->with('error', 'ระบบกำลังอัปเดตข้อมูล กรุณาอย่ากดส่งซ้ำครับ!');
    }

    // 🌟 ฟังก์ชันยกเลิกเคส
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'cancel_reason' => 'required|string|max:255'
        ]);

        $lockKey = 'mews_cancel_lock_' . auth()->id() . '_' . $id;

        return \Illuminate\Support\Facades\Cache::lock($lockKey, 5)->get(function () use ($request, $id) {
            $mews = MewsAssessment::findOrFail($id);
            
            try {
                DB::beginTransaction();

                \App\Models\MewsAssessment::where('case_id', $mews->case_id)->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => $request->cancel_reason
                ]);

                $mews->refresh();

                AuditLog::record('cancel_mews', $mews, [
                    'patient_id'    => $mews->patient_id,
                    'cancel_reason' => $request->cancel_reason,
                    'cancelled_by'  => auth()->user()->name
                ]);
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('MEWS Cancel Error: ' . $e->getMessage());
                return back()->with('error', 'เกิดข้อผิดพลาดในการยกเลิกเคส กรุณาลองใหม่อีกครั้ง');
            }

            try {
                broadcast(new PatientMewsUpdated($mews->patient_id));
            } catch (\Throwable $e) {}

            return redirect()->route('backend.patients.index')->with('success', 'ยกเลิกเคสการประเมินนี้เรียบร้อยแล้ว');

        }) ?: back()->with('error', 'ระบบกำลังประมวลผล กรุณาอย่ากดซ้ำครับ!');
    }

    public function destroy($id)
    {
        $mews = MewsAssessment::with('patient')->findOrFail($id);
        
        try {
            DB::beginTransaction();

            $patientId = $mews->patient_id;
            $deletedScore = $mews->total_score;

            $mews->delete();

            AuditLog::record('delete_mews', null, [
                'deleted_patient_id' => $patientId,
                'deleted_score'      => $deletedScore
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MEWS Delete Error: ' . $e->getMessage());
            return back()->with('error', 'ไม่สามารถลบข้อมูลได้ในขณะนี้');
        }

        try {
            broadcast(new PatientMewsUpdated($patientId));
        } catch (\Throwable $e) {}

        return redirect()->route('backend.patients.index')->with('success', 'ลบข้อมูลการประเมินเรียบร้อยแล้ว');
    }

    // 🌟 ฟังก์ชันจบการรักษา (Discharge: จำหน่ายกลับ / Admit / Refer) — ซิงค์อัตโนมัติกับรายงานเจ็บป่วยประจำวัน 100%
    public function discharge(Request $request, $id)
    {
        $request->validate([
            'discharge_type'    => 'required|in:recovered,refer,admitted',
            'discharge_details' => 'nullable|string|max:255'
        ]);

        $mews = MewsAssessment::with('patient')->findOrFail($id);
        
        try {
            DB::beginTransaction();

            $updatePayload = [
                'status'            => 'completed',
                'discharge_type'    => $request->discharge_type,
                'discharge_details' => $request->discharge_details
            ];

            // หากเลือกเป็น "จำหน่ายกลับกองร้อย (recovered)" ให้ล้างค่าติดตาม Admit เดิมออก
            if ($request->discharge_type === 'recovered') {
                $updatePayload['returned_to_unit_at'] = null;
                $updatePayload['return_note']         = null;
            }

            MewsAssessment::where('case_id', $mews->case_id)->update($updatePayload);

            // 🔄 ซิงค์ข้อมูลไปยังตารางรายงานอาการเจ็บป่วยประจำวัน (RecruitMorbidity) ทันที!
            if (class_exists('\App\Models\RecruitMorbidity')) {
                if ($request->discharge_type === 'recovered') {
                    // ลบเฉพาะรายการนอน รพ. (other_ipd) ที่เกิดจาก MEWS และปรับเฉพาะเคสความร้อน (heat) เป็น recovered
                    // 🛡️ ห้ามเปลี่ยนสถานะหมวด respiratory (โควิด/ไข้หวัดใหญ่) เพราะทหารยังแยกกักอยู่ในห้องพยาบาลหน่วยฝึก (CI)!
                    \App\Models\RecruitMorbidity::where('patient_id', $mews->patient_id)
                        ->where('category', 'other_ipd')
                        ->where('status', 'treating')
                        ->whereNull('recorded_by')
                        ->delete();

                    \App\Models\RecruitMorbidity::where('patient_id', $mews->patient_id)
                        ->where('category', 'heat')
                        ->where('status', 'treating')
                        ->update([
                            'status'         => 'recovered',
                            'recovered_date' => now()->format('Y-m-d'),
                        ]);
                } else {
                    // กรณีเลือกเป็น admitted หรือ refer ให้อัปเดตประเภทโรงพยาบาลในรายงานประจำวันให้ตรงกัน
                    $hospSubType = $request->discharge_type === 'refer' ? 'public_hospital' : 'camp_hospital';
                    \App\Models\RecruitMorbidity::where('patient_id', $mews->patient_id)
                        ->where('category', 'other_ipd')
                        ->where('status', 'treating')
                        ->update([
                            'sub_type' => $hospSubType,
                        ]);
                }
            }

            AuditLog::record('discharge_patient', $mews, [
                'patient_id'        => $mews->patient_id,
                'discharge_type'    => $request->discharge_type,
                'discharge_details' => $request->discharge_details,
                'final_mews_score'  => $mews->total_score
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MEWS Discharge Error: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการบันทึกผลการรักษา กรุณาลองใหม่อีกครั้ง');
        }

        $labels = [
            'recovered' => 'จำหน่ายกลับกองร้อย (อาการปกติ)',
            'refer'     => 'ส่งตัวรักษาต่อ (Refer)',
            'admitted'  => 'รับไว้รักษาตัวในโรงพยาบาล (Admit)'
        ];
        
        $dischargeText = $labels[$request->discharge_type] ?? 'จบการรักษา';

        if (!empty($request->discharge_details)) {
            if ($request->discharge_type === 'admitted') {
                $dischargeText .= " [เตียง/หอผู้ป่วย: " . $request->discharge_details . "]";
            } elseif ($request->discharge_type === 'refer') {
                $dischargeText .= " [รพ.ปลายทาง: " . $request->discharge_details . "]";
            }
        }

        try {
            broadcast(new PatientMewsUpdated($mews->patient_id));

            $patient = $mews->patient;
            $targetUnitId = $patient->military_unit_id ?? null;
            $statusJS = $request->discharge_type === 'admitted' ? 'admit' : 'discharge';

            // 🚀 ยิง Background Web Push แจ้งผลการรักษาเข้ามือถือของเสนารักษ์ประจำหน่วยฝึกทันที
            if ($targetUnitId) {
                \App\Services\WebPushService::sendToUnitMedics(
                    $targetUnitId,
                    $patient->intake_batch,
                    "🏥 อัปเดตผลรักษาจาก ER: {$patient->rank}{$patient->first_name}",
                    $dischargeText,
                    route('backend.mews.show', $mews->id),
                    "mews-discharge-{$mews->id}"
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Discharge Broadcast Warning: ' . $e->getMessage());
        }
        
        return back()->with('success', 'บันทึกข้อมูลการจำหน่าย/ปิดเคสเรียบร้อย: ' . $dischargeText);
    }

    // =========================================================================
    // 🏠 ฟังก์ชันบันทึก/แก้ไข "กลับหน่วยฝึกแล้ว" หรือ "ย้อนสถานะกลับเป็นกำลังนอน รพ."
    // =========================================================================
    public function returnToUnit(Request $request,$id)
    {
        $mews = MewsAssessment::with('patient')->findOrFail($id);

        // 🔄 กรณีที่ 1: เจ้าหน้าที่กดปุ่ม "ย้อนสถานะกลับเป็น กำลังนอน รพ. (ยังไม่กลับหน่วยฝึก)"
        if ($request->boolean('revert_to_admit')) {
            try {
                DB::beginTransaction();

                MewsAssessment::where('case_id', $mews->case_id)->update([
                    'returned_to_unit_at' => null,
                    'return_note'         => null,
                ]);

                // ซิงค์ตารางรายงานเจ็บป่วยประจำวันให้กลับมาเป็น "กำลังรักษา (treating)"
                if (class_exists('\App\Models\RecruitMorbidity')) {
                    \App\Models\RecruitMorbidity::where('patient_id', $mews->patient_id)
                        ->where('category', 'other_ipd')
                        ->orderBy('id', 'desc')
                        ->limit(1)
                        ->update([
                            'status'         => 'treating',
                            'recovered_date' => null,
                        ]);
                }

                AuditLog::record('revert_return_to_unit', $mews, [
                    'patient_id' => $mews->patient_id,
                    'case_id'    => $mews->case_id,
                ]);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('MEWS Revert Return to Unit Error: ' . $e->getMessage());
                return back()->with('error', 'ไม่สามารถย้อนสถานะได้ กรุณาลองใหม่อีกครั้ง');
            }

            try {
                broadcast(new PatientMewsUpdated($mews->patient_id));
            } catch (\Throwable $e) {}

            return back()->with('success', 'ย้อนสถานะกลับเป็น "กำลังนอนรักษาตัวใน รพ. (ยังไม่กลับหน่วยฝึก)" เรียบร้อยแล้ว');
        }

        // 📝 กรณีที่ 2: บันทึกครั้งแรก หรือ แก้ไขวันที่/เวลา/หมายเหตุการกลับหน่วยฝึก
        $request->validate([
            'returned_date' => 'nullable|date',
            'returned_time' => 'nullable|string|max:10',
            'return_note'   => 'nullable|string|max:255',
        ]);

        $timePart = $request->filled('returned_time') ?$request->returned_time . ':00' : now()->format('H:i:s');
        $returnedTimestamp =$request->filled('returned_date')
            ? \Carbon\Carbon::parse($request->returned_date . ' ' .$timePart)
            : now();

        try {
            DB::beginTransaction();

            MewsAssessment::where('case_id', $mews->case_id)->update([
                'returned_to_unit_at' => $returnedTimestamp,
                'return_note'         => $request->return_note ?: 'อาการดีขึ้น จำหน่ายกลับหน่วยฝึกเรียบร้อยแล้ว',
            ]);

            if (class_exists('\App\Models\RecruitMorbidity')) {
                \App\Models\RecruitMorbidity::where('patient_id', $mews->patient_id)
                    ->where('status', 'treating')
                    ->update([
                        'status'         => 'recovered',
                        'recovered_date' => $returnedTimestamp->format('Y-m-d'),
                    ]);
            }

            AuditLog::record('return_to_unit_after_admit', $mews, [
                'patient_id'          => $mews->patient_id,
                'case_id'             => $mews->case_id,
                'returned_to_unit_at' => $returnedTimestamp->toDateTimeString(),
                'return_note'         => $request->return_note,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('MEWS Return to Unit Error: ' . $e->getMessage());
            return back()->with('error', 'ไม่สามารถบันทึกสถานะการกลับหน่วยฝึกได้ กรุณาลองใหม่อีกครั้ง');
        }

        try {
            broadcast(new PatientMewsUpdated($mews->patient_id));

            $patient =$mews->patient;
            if ($patient &&$patient->military_unit_id) {
                event(new \App\Events\MewsStatusUpdated([
                    'name'        => ($patient->rank ?? '') . ' ' . ($patient->first_name ?? ''),
                    'status'      => 'discharge',
                    'status_text' => 'ออกจาก รพ. กลับถึงหน่วยฝึกเรียบร้อยแล้ว' . ($request->return_note ? " ({$request->return_note})" : ''),
                    'case_id'     => $mews->case_id,
                ], $patient->military_unit_id));
            }
        } catch (\Throwable $e) {
            Log::warning('Return to Unit Broadcast Warning: ' . $e->getMessage());
        }

        return back()->with('success', 'บันทึกข้อมูลสถานะ "กลับถึงหน่วยฝึกแล้ว" เรียบร้อยแล้วครับ!');
    }

    

    // =========================================================================
    // 🌟 Private Functions: ระบบส่งแจ้งเตือนเข้า Discord Webhook
    // (ล็อกให้ส่งเฉพาะการประเมินครั้งที่ 1 จากหน่วยฝึกเท่านั้น)
    // =========================================================================
    private function sendDiscordAlert($assessment, bool $isReassessment = false, bool $isEdited = false)
    {
        // 🔒 ป้องกันชั้นที่ 2: หากเป็นการประเมินซ้ำ (ครั้งที่ 2+), การแก้ไข หรือไม่ได้มาจากภาคสนาม/หน่วยฝึก ให้หยุดส่งทันที
        if ($isReassessment || $isEdited || $assessment->assessment_type !== 'field') {
            return;
        }

        $rawSymptoms = is_string($assessment->symptoms) ? json_decode($assessment->symptoms, true) : ($assessment->symptoms ?? []);
        $hasEmergencyConditions = is_array($rawSymptoms) && count($rawSymptoms) > 0;
        
        // 🛑 ป้องกันชั้นที่ 3 (ใหม่!): บล็อกไม่ให้ส่งเคส Normal ล้วนเข้า Discord (ยกเว้นสีเขียวแต่มีอาการเฉพาะโรค)
        if ($assessment->risk_level === 'normal' && !$hasEmergencyConditions) {
            return; // จบการทำงานทันที ไม่ยิง Discord ให้รกห้องฉุกเฉิน
        }

        $webhookUrl = $this->getDiscordWebhookUrl();
        
        if (!$webhookUrl) {
            Log::warning('Discord Alert: ไม่พบ DISCORD_MEWS_WEBHOOK_URL หรือ DISCORD_WEBHOOK_URL ในไฟล์ .env');
            return;
        }

        $patient = $assessment->patient()->with('militaryUnit')->first() ?? $assessment->patient;
        if (!$patient) return;
        
        $symptomLabels = [
            'mi_chest_pain'           => 'เจ็บแน่นหน้าอกเหมือนโดนกดทับ (MI)',
            'mi_dyspnea'              => 'เหนื่อย หายใจไม่อิ่ม ใจสั่น (MI)',
            'mi_referred_pain'        => 'เจ็บร้าวไปไหล่ซ้าย กราม แขน (MI)',
            'stroke_dizzy'            => 'เวียนศีรษะบ้านหมุน เดินเซ (Stroke)',
            'stroke_vision'           => 'ตามัว มองเห็นภาพซ้อน (Stroke)',
            'stroke_facial_weakness'  => 'หน้าเบี้ยว ปากเบี้ยวครึ่งซีก (Stroke)',
            'stroke_arm_leg_weakness' => 'แขนขาอ่อนแรงครึ่งซีก (Stroke)',
            'stroke_speech'           => 'พูดไม่ชัด สื่อสารไม่เข้าใจ (Stroke)',
            'heat_confusion'          => 'ซึมสับสน ชัก หมดสติ (Heat Injury)',
            'heat_fatigue'            => 'อ่อนเพลียไม่มีแรง เหงื่อออกมาก (Heat Injury)',
            'heat_pale'               => 'หน้าซีดหน้ามืด เป็นลม (Heat Injury)',
            'heat_cramp'              => 'ตะคริว หายใจหอบเร็วลึก (Heat Injury)',
            'heat_numbness'           => 'ชาปลายมือ/เท้า (Heat Injury)',
            'heat_urine'              => 'ปัสสาวะสีเข้ม (Heat Injury)',
            'ana_rash'                => 'ผื่นแดง ลมพิษ ปากบวม (Anaphylaxis)',
            'ana_resp'                => 'หายใจไม่สะดวก หอบเหนื่อย (Anaphylaxis)',
            'ana_sys'                 => 'กล้ามเนื้ออ่อนแรง เป็นลม (Anaphylaxis)',
            'ana_gi'                  => 'ปวดท้อง คลื่นไส้ อาเจียน (Anaphylaxis)',
            'ana_chest'               => 'แน่นหน้าอก (Anaphylaxis)',
            'appendicitis'            => 'ปวดท้อง RLQ (Appendicitis)',
            'sepsis_drowsy'           => 'ซึม (Sepsis)',
            'sepsis_tachypnea'        => 'หายใจหอบ (Sepsis)',
            'trauma'                  => 'เกิดอุบัติเหตุภายใน 24 ชม. (Trauma)'
        ];

        $rawSymptoms = is_string($assessment->symptoms) ? json_decode($assessment->symptoms, true) : ($assessment->symptoms ?? []);
        
        $translatedSymptoms = [];
        if (is_array($rawSymptoms)) {
            foreach ($rawSymptoms as $key) {
                $translatedSymptoms[] = $symptomLabels[$key] ?? $key; 
            }
        }

        $symptomsText = (count($translatedSymptoms) > 0) 
                        ? "• " . implode("\n• ", $translatedSymptoms) 
                        : "✅ ไม่มีอาการฉุกเฉินเฉพาะโรค";

        if (!empty(trim((string)$assessment->symptoms_note))) {
            $symptomsText .= "\n\n📝 **อาการสำคัญ / บันทึกเพิ่มเติม:**\n" . trim($assessment->symptoms_note);
        }

        $mewsUrl = route('backend.mews.show', $assessment->id);
        
        $riskLevel = $assessment->risk_level;
        $hasEmergencyConditions = is_array($rawSymptoms) && count($rawSymptoms) > 0;
        
        $embedColor = 3066993; // สีเขียว (Normal)
        $alertIcon = "✅";
        $riskText = "ปกติ (Normal) -> ส่ง OPD";
        $contentMsg = ""; 

        if ($riskLevel === 'emergency') {
            $embedColor = 15158332; // สีแดง
            $alertIcon = "🚨";
            $riskText = "ฉุกเฉิน (EMERGENCY) -> ส่ง ER";
            $contentMsg = "@here 🚨 **มีเคสฉุกเฉินสีแดงส่งมาจากหน่วยฝึก!** กรุณาตรวจสอบทันที!"; 
        } elseif ($hasEmergencyConditions) {
            $embedColor = 15158332; // สีแดง
            $alertIcon = "🚨";
            $riskText = "มีอาการฉุกเฉินเฉพาะโรค -> ส่ง ER";
            $contentMsg = "@here 🚨 **มีผู้ป่วยพบอาการฉุกเฉินเฉพาะโรคส่งมาจากหน่วยฝึก!** ส่งตัวเข้า ER ด่วน!"; 
        } elseif ($riskLevel === 'observe') {
            $embedColor = 16753920; // สีเหลืองส้ม
            $alertIcon = "⚠️";
            $riskText = "เฝ้าระวัง (Observe) -> ส่ง ER";
            $contentMsg = "⚠️ **มีเคสเฝ้าระวังใหม่ (MEWS: {$assessment->total_score}) ส่งตัวเข้า ER**";
        }

        $hnText = $patient->hn ?: 'ยังไม่มี HN';
        $pttypeText = $patient->pttype_name ?: 'รอซิงค์สิทธิ';
        $unitDisplay = ($patient->militaryUnit->short_name ?? $patient->militaryUnit->name ?? $patient->company ?? '-') 
                     . ($patient->battalion ? " ({$patient->battalion})" : "");

        // ข้อมูลโรคประจำตัว
        $chronicText = 'ไม่มีโรคประจำตัว';
        if (($patient->chronic_disease_status ?? 'none') === 'yes') {
            $chronicText = "⚠️ มีโรคประจำตัว: " . ($patient->chronic_disease_detail ?: 'ระบุ');
        } elseif (($patient->chronic_disease_status ?? 'none') === 'other') {
            $chronicText = "📌 อื่นๆ: " . ($patient->chronic_disease_detail ?: 'ระบุ');
        }

        $embed = [
            "title"       => "{$alertIcon} [เคสใหม่จากหน่วย] ผลประเมิน MEWS: {$assessment->total_score} คะแนน [{$riskText}]",
            "url"         => $mewsUrl,
            "description" => "**ผู้ป่วย:** {$patient->rank}{$patient->first_name} {$patient->last_name}\n"
                           . "**เลข ปชช.:** `{$patient->cid}` | **HN:** `{$hnText}`\n"
                           . "**สิทธิการรักษา:** {$pttypeText}\n"
                           . "**โรคประจำตัว:** {$chronicText}\n\n"
                           . "👉 **[คลิกที่นี่เพื่อเปิดดูรายละเอียดเคส]({$mewsUrl})**",
            "color"       => $embedColor,
            "fields"      => [
                [
                    "name"   => "🏢 สังกัด / หน่วยฝึกทหาร",
                    "value"  => $unitDisplay,
                    "inline" => true
                ],
                [
                    "name"   => "🎖️ ผลัดที่",
                    "value"  => "` ผลัด " . ($patient->intake_batch ?? 'ไม่ระบุ') . " `", 
                    "inline" => true
                ],
                [
                    "name"   => "❤️ สัญญาณชีพแรกรับ (Vital Signs)",
                    "value"  => "BP: **{$assessment->systolic_bp}** | HR: **{$assessment->heart_rate}** | RR: **{$assessment->respiratory_rate}** | Temp: **{$assessment->temperature}°C** | O2: **{$assessment->o2sat}%** | AVPU: **{$assessment->consciousness}**",
                    "inline" => false
                ],
                [
                    "name"   => "🏥 อาการที่พบ (Emergency Conditions)",
                    "value"  => $symptomsText,
                    "inline" => false
                ]
            ],
            "footer" => [
                "text" => "ประเมินส่งตัวโดย: " . (auth()->user()->name ?? 'เจ้าหน้าที่หน่วยฝึก') . " | VN: " . ($assessment->case_id ?? '-')
            ],
            "timestamp" => now()->toIso8601String()
        ];

        $payload = ['embeds' => [$embed]];
        if (!empty($contentMsg)) {
            $payload['content'] = $contentMsg;
        }

        try {
            $response = Http::withoutVerifying()->timeout(10)->post($webhookUrl, $payload);
            if ($response->failed()) {
                Log::error('KSVR Discord Webhook HTTP Error: ' . $response->status() . ' | ' . $response->body());
            }
        } catch (\Throwable $e) {            
            Log::error('KSVR Discord Webhook Exception: ' . $e->getMessage());
        }
    }

    public function printForm($id)
    {
        $mews = \App\Models\MewsAssessment::with(['patient.militaryUnit', 'evaluator', 'template'])->findOrFail($id);
        $symptomCodes = is_array($mews->symptoms) ? $mews->symptoms : [];

        return view('backend.mews.print_form', compact('mews', 'symptomCodes'));
    }

    private function checkAndSendOutbreakAlert($assessment, $patient)
    {
        $temp = (float) $assessment->temperature;
        if ($temp < 38.0) {
            return;
        }

        $patient->loadMissing(['recruitScreening', 'militaryUnit']);
        $screening = $patient->recruitScreening;

        $isRedScreening = $screening && $screening->color_group === 'red';
        $hasFluNote = $screening && !empty($screening->symptoms_note) && (
            str_contains($screening->symptoms_note, 'ไข้หวัดใหญ่') || 
            str_contains(strtolower($screening->symptoms_note), 'flu') ||
            str_contains($screening->symptoms_note, 'ติดเชื้อ')
        );

        $unitName = $patient->militaryUnit->short_name ?? $patient->company ?? 'ไม่ระบุหน่วย';
        $clusterQuery = \App\Models\MewsAssessment::where('status', '!=', 'cancelled')
            ->where('temperature', '>=', 38.0)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereHas('patient', function ($q) use ($patient) {
                if ($patient->military_unit_id) {
                    $q->where('military_unit_id', $patient->military_unit_id);
                } else {
                    $q->where('company', $patient->company);
                }
            });

        $feverCountInUnit = $clusterQuery->distinct('patient_id')->count('patient_id');
        $isClusterOutbreak = $feverCountInUnit >= 3;

        if ($isRedScreening || $hasFluNote || $isClusterOutbreak) {
            $webhookUrl = $this->getDiscordWebhookUrl();
            if (!$webhookUrl) return;

            $mewsUrl = route('backend.mews.show', $assessment->id);
            $alertReasons = [];
            if ($isRedScreening) $alertReasons[] = "🔴 ผู้ป่วยอยู่ใน **กลุ่มคัดกรองสีแดง (เสี่ยงสูง/พบเชื้อ)**";
            if ($hasFluNote) $alertReasons[] = "🦠 ประวัติคัดกรองระบุ: **{$screening->symptoms_note}**";
            if ($isClusterOutbreak) $alertReasons[] = "📈 พบกลุ่มก้อนผู้ป่วยไข้สูง (≥ 38°C) ในหน่วย **{$unitName}** สะสม **{$feverCountInUnit} นาย** ภายใน 24 ชม.";

            $embed = [
                "title"       => "🦠 แจ้งเตือนผู้บริหาร: เฝ้าระวังโรคติดต่อ/การระบาดในหน่วยฝึก!",
                "url"         => $mewsUrl,
                "description" => "**ผู้ป่วยล่าสุด:** {$patient->rank}{$patient->first_name} {$patient->last_name}\n**อุณหภูมิร่างกาย:** ` {$temp} °C ` | **MEWS Score:** ` {$assessment->total_score} คะแนน `\n\n**สาเหตุการแจ้งเตือน:**\n• " . implode("\n• ", $alertReasons),
                "color"       => 10181046,
                "fields"      => [
                    [
                        "name"   => "🏕️ หน่วยฝึกที่ต้องเฝ้าระวัง",
                        "value"  => $unitName . " (ผลัด " . ($patient->intake_batch ?? '-') . ")",
                        "inline" => true
                    ],
                    [
                        "name"   => "🛡️ คำแนะนำเบื้องต้นสำหรับหน่วย",
                        "value"  => "พิจารณาแยกกักตัว (Isolation) สวมหน้ากากอนามัย และคัดกรองทหารเตียงข้างเคียง",
                        "inline" => true
                    ]
                ],
                "footer" => [
                    "text" => "KSVR AI Outbreak Surveillance | รายงานโดย: " . (auth()->user()->name ?? 'เจ้าหน้าที่')
                ],
                "timestamp" => now()->toIso8601String()
            ];

            try {
                Http::withoutVerifying()->timeout(10)->post($webhookUrl, [
                    'content' => "@here 🦠 **[รายงานพิเศษถึงผู้บริหาร]** พบสัญญาณเฝ้าระวังโรคติดต่อ/กลุ่มก้อนไข้สูงในหน่วยฝึก **{$unitName}**!",
                    'embeds'  => [$embed]
                ]);
            } catch (\Throwable $e) {
                Log::error('Outbreak Webhook Error: ' . $e->getMessage());
            }
        }
    }
}