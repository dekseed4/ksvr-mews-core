<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AiController extends Controller
{
    // 🌟 ฟังก์ชันดึง GEMINI_API_KEY แบบปลอดภัย (ทำงานได้แม้เซิร์ฟเวอร์เปิด config:cache)
    private function getGeminiApiKey(): ?string
    {
        $key = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');

        if (empty($key) && file_exists(base_path('.env'))) {
            $envContent = @file_get_contents(base_path('.env'));
            if ($envContent && preg_match('/^GEMINI_API_KEY\s*=\s*([^\r\n]+)/m', $envContent, $matches)) {
                $key = trim($matches[1], " \t\n\r\0\x0B\"'");
            }
        }

        return !empty($key) ? trim($key) : null;
    }

    // =========================================================================
    // 🎙️ 1. AI สกัดสัญญาณชีพ อาการฉุกเฉิน และโน้ตหัตถการทั่วไป (Voice/Text to Form)
    // =========================================================================
    public function extractVitals(Request $request)
    {
        $request->validate([
            'text' => 'required|string'
        ]);

        $apiKey = $this->getGeminiApiKey();
        if (empty($apiKey)) {
            return response()->json(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า GEMINI_API_KEY ในระบบ'], 400);
        }

        $symptoms = \App\Models\EmergencySymptom::where('is_active', true)->get(['code', 'name']);
        $symptomsListText = $symptoms->map(function($s) {
            return "'{$s->code}'=\"{$s->name}\"";
        })->implode(", ");

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key={$apiKey}";

        // 🌟 อัปเกรด Prompt: ป้องกันการติ๊กอาการฉุกเฉินผิดพลาดในเคสทั่วไป (OPD)
        $prompt = "
            คุณคือผู้ช่วยแพทย์ทหาร Triage ในระบบ M-CARE & KSVR MEWS ประมวลผลข้อความภาษาไทยจากเสนารักษ์/พยาบาล และแปลงเป็น JSON เท่านั้น
            กฎการทำงาน:
            1. ตัวเลข SBP, HR, RR, Temp, O2Sat: สกัดเฉพาะ 'ตัวเลข' หากในข้อความไม่พบตัวเลขระบุชัดเจน ให้ใส่ค่า null (ห้ามเดาเองเด็ดขาด)
               - หากพูดความดันเป็นคู่ เช่น '120/80' หรือ '120 80' ให้ดึงเฉพาะค่าตัวบน (120) มาใส่ใน sbp
               - หากพูดตัวเลขเป็นคำอ่านภาษาไทย เช่น 'สามสิบแปดจุดห้า' ให้แปลงเป็นตัวเลข 38.5
            2. AVPU: แปลงระดับความรู้สึกตัวเป็นอักษรภาษาอังกฤษ 1 ตัว:
               - A = รู้สึกตัวดี / ตื่นตัวดี / พูดคุยรู้เรื่องปกติ
               - C = สับสน / ซึมสับสน / พูดจาวกวน (Confused)
               - V = เรียกถึงลืมตา / เรียกถึงรู้สึกตัว (Voice)
               - P = กระตุ้นด้วยความเจ็บปวดถึงตอบสนอง (Pain)
               - U = ไม่รู้สึกตัว / หมดสติ / ไม่ตอบสนอง (Unresponsive)
            3. Conditions (สำคัญมาก): วิเคราะห์ว่ามีอาการวิกฤตฉุกเฉินที่ตรงกับลิสต์นี้ชัดเจนหรือไม่: [ {$symptomsListText} ]
               - ให้ใส่รหัส (code) เฉพาะกรณีเป็นอาการฉุกเฉินรุนแรงที่ต้องเข้าห้อง ER เท่านั้น
               - หากเป็นอาการเจ็บป่วยทั่วไปสำหรับผู้ป่วยนอก (OPD) เช่น มาทำแผล, ล้างแผล, ตัดไหม, รับยาต่อเนื่อง, มีไข้ไอเจ็บคอทั่วไป, ปวดเมื่อยกล้ามเนื้อปกติ ห้ามใส่รหัสใน conditions เด็ดขาด (ให้ใส่ [] เท่านั้น แล้วนำข้อความอาการไปใส่ในช่อง note แทน)
            4. Note (อาการสำคัญ/บันทึกเพิ่มเติม): สกัดอาการสำคัญ หรือหัตถการทั่วไปที่ผู้ป่วยมารับบริการ เช่น 'มาทำแผล', 'ล้างแผลต่อเนื่อง', 'นัดตัดไหม', 'ขอรับยาต่อเนื่อง', 'ปวดเมื่อยกล้ามเนื้อ', 'มีไข้ ไอ เจ็บคอ' สรุปเป็นข้อความสั้นๆ ชัดเจน (หากไม่มีระบุให้ใส่ \"\")
            5. First Aid Advice: ให้คำแนะนำการปฐมพยาบาลหรือการดูแลเบื้องต้นสำหรับเสนารักษ์/พยาบาล 3 ข้อสั้นๆ กระชับ ปฏิบัติตามได้ทันที สอดคล้องกับสัญญาณชีพและอาการที่พบ

            ข้อความที่ได้รับ: '{$request->text}'
            
            รูปแบบ JSON ที่ต้องตอบกลับ (ห้ามมี Markdown คลุม):
            {
                \"sbp\": (ตัวเลข หรือ null),
                \"hr\": (ตัวเลข หรือ null),
                \"rr\": (ตัวเลข หรือ null),
                \"temp\": (ตัวเลข หรือ null),
                \"o2sat\": (ตัวเลข หรือ null),
                \"avpu\": \"(A, C, V, P, U หรือ null)\",
                \"conditions\": [],
                \"note\": \"สรุปอาการสำคัญ หรือหัตถการ เช่น มาทำแผลที่เข่าขวา\",
                \"first_aid_advice\": [\"คำแนะนำข้อที่ 1\", \"คำแนะนำข้อที่ 2\", \"คำแนะนำข้อที่ 3\"]
            }
        ";

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(3)
                ->timeout(12)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ]
                ]);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                preg_match('/\{.*\}/s', $rawText, $matches);
                $jsonString = $matches[0] ?? $rawText;
                $data = json_decode(trim($jsonString), true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return response()->json(['status' => 'success', 'data' => $data]);
                }
            }

            Log::error("Gemini API Error: " . $response->body());
            return response()->json(['status' => 'error', 'message' => 'API ขัดข้อง'], 500);

        } catch (\Exception $e) {
            Log::error("Gemini Exception: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'เชื่อมต่อ AI ไม่สำเร็จ'], 500);
        }
    }

    // =========================================================================
    // 🩺 2. AI Clinical Triage Copilot (วิเคราะห์แนวทางปฐมพยาบาลจาก V/S + โน้ต + ยาประจำตัว + ประวัติแยกนอน)
    // =========================================================================
    public function triageCopilot(Request $request)
    {
        $apiKey = $this->getGeminiApiKey();
        if (empty($apiKey)) {
            return response()->json(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า API Key'], 400);
        }

        $mewsId        = $request->input('mews_id');
        $clinicalNote  = '';
        $chronicInfo   = 'ไม่มีโรคประจำตัว';
        $medsInfo      = 'ไม่มีรายการยาต่อเนื่อง';
        $infirmaryInfo = 'อยู่ในโรงนอนปกติ';

        if ($mewsId) {
            $cachedAdvice = Cache::get("copilot_advice_mews_{$mewsId}");
            if ($cachedAdvice) {
                return response()->json(['status' => 'success', 'data' => $cachedAdvice]);
            }

            $mews = \App\Models\MewsAssessment::with(['patient.recruitScreening', 'patient.activeInfirmaryStay'])->find($mewsId);
            if (!$mews) {
                return response()->json(['status' => 'error', 'message' => 'ไม่พบข้อมูลใบประเมิน MEWS'], 404);
            }

            $sbp           = $mews->systolic_bp;
            $hr            = $mews->heart_rate;
            $rr            = $mews->respiratory_rate;
            $temp          = $mews->temperature;
            $o2sat         = $mews->o2sat;
            $avpu          = $mews->consciousness;
            $totalScore    = $mews->total_score;
            $selectedCodes = is_string($mews->symptoms) ? json_decode($mews->symptoms, true) : ($mews->symptoms ?? []);
            $clinicalNote  = $mews->symptoms_note ?: 'ไม่ได้ระบุ';
            $screeningInfo = $mews->patient->recruitScreening->symptoms_note ?? 'ไม่มีประวัติเสี่ยง';

            if ($mews->patient) {
                if (in_array($mews->patient->chronic_disease_status, ['yes', 'other'])) {
                    $chronicInfo = $mews->patient->chronic_disease_detail ?: 'มีโรคประจำตัว';
                }
                if ($mews->patient->activeInfirmaryStay) {
                    $st = $mews->patient->activeInfirmaryStay;
                    $infirmaryInfo = ($st->location_type === 'unit_infirmary' ? 'กำลังนอนห้องพยาบาลหน่วยฝึก' : 'ฝากนอน รพ.ค่ายฯ') . " (Dx: {$st->diagnosis_text})";
                }
                if (class_exists('\App\Models\PatientMedication') && Schema::hasTable('patient_medications')) {
                    $activeMeds = \App\Models\PatientMedication::where('patient_id', $mews->patient_id)->where('is_active', true)->pluck('drug_name')->toArray();
                    if (!empty($activeMeds)) {
                        $medsInfo = implode(', ', $activeMeds);
                    }
                }
            }
        } else {
            $sbp           = $request->sbp;
            $hr            = $request->hr;
            $rr            = $request->rr;
            $temp          = $request->temp;
            $o2sat         = $request->o2sat;
            $avpu          = $request->avpu;
            $totalScore    = $request->total_score ?? '0';
            $selectedCodes = $request->input('conditions', []);
            $clinicalNote  = $request->input('symptoms_note', 'ไม่ได้ระบุ');
            $screeningInfo = $request->input('screening_note', 'ไม่มีประวัติเสี่ยง');
        }

        $symptomsMap = \App\Models\EmergencySymptom::pluck('name', 'code')->toArray();
        $symptomNames = [];
        if (is_array($selectedCodes)) {
            foreach ($selectedCodes as $code) {
                $symptomNames[] = $symptomsMap[$code] ?? $code;
            }
        }
        $symptomText = !empty($symptomNames) ? implode(', ', $symptomNames) : 'ไม่มีอาการฉุกเฉินเฉพาะโรค';

        $prompt = "
            คุณคือแพทย์เวชศาสตร์ฉุกเฉินทหาร (KSVR & M-CARE Clinical Triage Copilot)
            จงให้คำแนะนำการปฐมพยาบาลและการดูแลเบื้องต้นสำหรับ 'เสนารักษ์ภาคสนาม/พยาบาล' ระหว่างดูแลหรือรอส่งต่อผู้ป่วย

            ข้อมูลสัญญาณชีพและประวัติของพลทหาร:
            - ความดันตัวบน (SBP): " . ($sbp ?? 'ไม่ระบุ') . " mmHg
            - ชีพจร (HR): " . ($hr ?? 'ไม่ระบุ') . " ครั้ง/นาที
            - อัตราการหายใจ (RR): " . ($rr ?? 'ไม่ระบุ') . " ครั้ง/นาที
            - อุณหภูมิร่างกาย (Temp): " . ($temp ?? 'ไม่ระบุ') . " °C
            - ออกซิเจนในเลือด (O2 Sat): " . ($o2sat ?? 'ไม่ระบุ') . " %
            - ระดับความรู้สึกตัว (AVPU): " . ($avpu ?? 'ไม่ระบุ') . " (A=ตื่นดี, C=สับสน, V=เรียกถึงลืมตา, P=ตอบสนองต่อความเจ็บ, U=ไม่ตอบสนอง)
            - คะแนน MEWS รวม: " . ($totalScore ?? '0') . " คะแนน
            - อาการฉุกเฉินเฉพาะโรคที่พบ: {$symptomText}
            - อาการสำคัญ / หัตถการทั่วไป (Note): {$clinicalNote}
            - ประวัติโรคประจำตัว/แพ้ยา: {$chronicInfo}
            - รายการยาที่รับประทานอยู่ปัจจุบัน (e-MAR): {$medsInfo}
            - สถานะการแยกนอนพัก: {$infirmaryInfo}
            - ประวัติคัดกรองแรกรับ: {$screeningInfo}

            ตอบกลับเป็น JSON เท่านั้น:
            {
                \"summary\": \"ประเมินความเสี่ยงและแนวทางการส่งต่อ (OPD หรือ ER) สั้นๆ 1 ประโยค\",
                \"advice\": [
                    \"ขั้นตอนการปฏิบัติข้อที่ 1 (สิ่งที่ต้องทำทันที หรือการดูแลแผล/อาการเบื้องต้น)\",
                    \"ขั้นตอนการปฏิบัติข้อที่ 2 (การจัดท่า การลดไข้/ความร้อน หรือการให้ออกซิเจน)\",
                    \"ขั้นตอนการปฏิบัติข้อที่ 3 (สิ่งที่ต้องเฝ้าระวังซ้ำ การติดตามการกินยา หรือข้อห้ามสำคัญ)\"
                ]
            }
        ";

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key={$apiKey}";
            $response = Http::withoutVerifying()->timeout(10)->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ]
            ]);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text') ?? '';
                preg_match('/\{[\s\S]*\}/', $rawText, $matches);
                $data = json_decode($matches[0] ?? '{}', true);
                if (is_array($data) && isset($data['advice'])) {
                    if ($mewsId) {
                        Cache::put("copilot_advice_mews_{$mewsId}", $data, 3600);
                    }
                    return response()->json(['status' => 'success', 'data' => $data]);
                }
            }
            return response()->json(['status' => 'error', 'message' => 'ไม่สามารถสร้างคำแนะนำได้ในขณะนี้'], 500);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'เชื่อมต่อ AI ไม่สำเร็จ'], 500);
        }
    }

    // =========================================================================
    // 🤖 3. AI Data Analyzer สำหรับผู้บริหารและแอดมิน (คลิกชื่อเปิดหน้าโปรไฟล์ได้ทุกโหมด 100%)
    // =========================================================================
    public function askExecutive(Request $request)
    {
        $request->validate(['text' => 'required|string|max:500']);
        $promptText = trim($request->text);

        $apiKey = $this->getGeminiApiKey();
        if (empty($apiKey)) {
            return response()->json(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า GEMINI_API_KEY'], 400);
        }

        $user = auth()->user();
        $rawRoles = $user->roles ?? $user->role ?? [];
        $userRoles = is_string($rawRoles) ? json_decode($rawRoles, true) : (array) $rawRoles;
        $isAdmin = in_array('admin', $userRoles);

        // รองรับสิทธิ์เจ้าหน้าที่เวชกรรม (medical_staff) ให้สามารถถามข้อมูลภาพรวมได้ทุกหน่วยฝึก
        $canViewAll = $isAdmin
            || in_array('executive', $userRoles)
            || in_array('er_nurse', $userRoles)
            || in_array('opd_nurse', $userRoles)
            || in_array('registration', $userRoles)
            || in_array('medical_staff', $userRoles);

        // 🛡️ Closure สำหรับจำกัดสิทธิ์กรณีผู้ใช้เป็นเสนารักษ์ (เห็นเฉพาะหน่วยและผลัดของตนเอง)
        $applyPatientScope = function ($q) use ($canViewAll, $user) {
            if (!$canViewAll) {
                $q->where('military_unit_id', $user->military_unit_id);
                if (!empty($user->intake_batch)) {
                    $q->where('intake_batch', $user->intake_batch);
                }
            }
        };

        // 🔗 Closure ตัวช่วยสร้างลิงก์ชื่อพลทหารที่คลิกเปิดไปหน้าแฟ้มประวัติผู้ป่วย (patients/show) ได้ทุกโหมด
        $renderSoldierProfileLink = function ($patient, ?string $customUnitName = null, string $extraHtml = '') {
            if (!$patient) {
                return '';
            }
            $profileUrl = route('backend.patients.show', $patient->id);
            $fullName   = e(($patient->rank ?? '') . ($patient->first_name ?? '') . ' ' . ($patient->last_name ?? ''));
            $unitText   = e($customUnitName ?: ($patient->militaryUnit->short_name ?? $patient->company ?? 'ไม่ระบุหน่วย'));

            return "
                <div class='flex flex-wrap items-center gap-1.5'>
                    <a href='{$profileUrl}' class='font-black text-slate-900 hover:text-indigo-600 text-base transition-colors inline-flex items-center gap-1.5 group' title='คลิกเพื่อเปิดแฟ้มประวัติของ {$fullName}'>
                        <span class='group-hover:underline'>{$fullName}</span>
                        <i class='fa-solid fa-arrow-up-right-from-square text-[11px] text-slate-400 group-hover:text-indigo-600 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5'></i>
                    </a>
                    <span class='text-xs font-bold text-indigo-600'>({$unitText})</span>
                    {$extraHtml}
                </div>
            ";
        };

        $symptoms = \App\Models\EmergencySymptom::where('is_active', true)->get();
        $symptomsListText = $symptoms->map(fn($s) => "'{$s->code}'=\"{$s->name}\"")->implode(", ");

        $prompt = "
            คุณคือ 'KSVR & M-CARE AI Assistant' ผู้ช่วยอัจฉริยะระดับผู้บริหารของโรงพยาบาลค่ายกฤษณ์สีวะรา
            หน้าที่ของคุณคือ: วิเคราะห์คำถามภาษาไทย และสกัดเงื่อนไขการค้นหาออกมาเป็น JSON เท่านั้น

            [1. กฎการเลือกตารางเป้าหมาย (target_table) ทั้ง 9 โหมด]
            - 'system_info': เมื่อถามถึง 'รายชื่อโรคฉุกเฉินในระบบ', 'เกณฑ์ประเมิน MEWS', 'คะแนน MEWS คิดยังไง'
            - 'recruit_infirmary': เมื่อถามถึง 'ห้องพยาบาลหน่วยฝึก', 'แยกโรงนอน', 'ฝากนอน รพ.', 'ฝากนอนระหว่างรอปลด', 'ใครนอนห้องพยาบาลบ้าง'
            - 'recruit_appointments': เมื่อถามถึง 'นัดพบแพทย์', 'คิวนัดวันนี้', 'นัดพรุ่งนี้', 'นัดคลินิก', 'ไปตรวจตามนัด'
            - 'military_discharges': เมื่อถามถึง 'นำปลดทหาร', 'เอกสารนำปลด', 'รอปลดประจำการ', 'ปลดทหารแล้ว', 'ยื่นนำปลด'
            - 'care_chart_emar': เมื่อถามถึง 'บันทึกการรับประทานยา', 'กินยาต่อเนื่อง', 'ยาจิตเวช', 'e-MAR', 'วัดสัญญาณชีพ 06:00 / 18:00', 'V/S โควิด'
            - 'morbidity_reports': เมื่อถามถึง 'รายงานเจ็บป่วยประจำวัน', 'โควิด', 'ไข้หวัดใหญ่', 'แยกกัก CI', 'บาดเจ็บจากความร้อน/โรคลมร้อน/ฮีทสโตรก/ตะคริวแดด', 'นอน รพ.ค่ายฯ', 'นอน รพ.สาธารณสุข (นอก รพ.)', หรือถามหาการวินิจฉัยโรค 'Dx.'
            - 'recruit_screenings': เมื่อถามถึง 'คัดกรองทหารใหม่', 'คัดกรองแรกรับ', 'กลุ่มสีเขียว/สีเหลือง/สีแดงตอนคัดกรอง'
            - 'patients': เมื่อถามข้อมูลส่วนตัวกำลังพล, ยอดทหารทั้งหมด, ตำหนิ/แผลเป็น, กรุ๊ปเลือด, BMI, ที่อยู่/จังหวัด, บิดามารดา, 'สิทธิการรักษา/บัตรทอง/ประกันสังคม', 'ใครยังไม่มี HN / ยังไม่ซิงค์ HosXP', 'โรคประจำตัว/แพ้ยา', หรือสถานะกำลังพล ('ไม่มารายงานตัว/ขาดหนี', 'ต้องโทษ/เรือนจำ', 'เสียชีวิต')
            - 'mews': เมื่อถามถึงการประเมินสัญญาณชีพ (MEWS), คิวรอตรวจ OPD, คิวห้องฉุกเฉิน ER, คนไข้มีไข้/ความดัน/ชีพจร, อาการฉุกเฉิน, คนที่มาทำแผล/ตัดไหม/รับยา, หรือสถานะการรักษา (กำลังรักษา, จำหน่ายกลับ, Admit, Refer)

            [2. ค่าของ requested_info]
            - 'scar_details', 'blood_group', 'bmi_info', 'pttype_info', 'chronic_disease', 'duty_status_info', 'education', 'religion', 'contact', 'parents', 'military_info', 'symptoms_list', 'mews_criteria'

            [พจนานุกรมรหัสอาการฉุกเฉิน]
            { {$symptomsListText} }

            คำถามจากผู้บริหาร: '{$promptText}'
            
            รูปแบบ JSON ที่ต้องตอบกลับเท่านั้น:
            {
                \"target_table\": \"('patients', 'mews', 'system_info', 'recruit_screenings', 'morbidity_reports', 'recruit_infirmary', 'recruit_appointments', 'military_discharges', หรือ 'care_chart_emar')\",
                \"requested_info\": \"(ค่าตามข้อ 2 หรือ null)\",
                \"name\": \"(ชื่อหรือนามสกุล หรือ null)\",
                \"unit\": \"(ชื่อหน่วยฝึก/กองพัน เช่น 'มทบ.29', 'ร.3 พัน.1' หรือ null)\",
                \"intake_batch\": \"(เช่น '2/69' หรือ null)\",
                \"hn_status\": \"('synced', 'unsynced' หรือ null)\",
                \"duty_status\": \"('active', 'absent', 'imprisoned', 'deceased' หรือ null)\",
                \"chronic_filter\": \"('yes', 'other' หรือ null)\",
                \"risk_level\": \"('normal', 'observe', 'emergency' หรือ null)\",
                \"queue_type\": \"('er', 'opd' หรือ null)\",
                \"color_group\": \"('green', 'yellow', 'red' หรือ null)\",
                \"status\": \"('pending', 'completed', 'cancelled', 'staying', 'returned', 'scheduled' หรือ null)\",
                \"discharge_type\": \"('recovered', 'admitted', 'refer' หรือ null)\",
                \"morbidity_category\": \"('respiratory', 'heat', 'other_ipd' หรือ null)\",
                \"infirmary_location\": \"('unit_infirmary', 'camp_hospital' หรือ null)\",
                \"medication_group\": \"('ci_isolation', 'psychiatric', 'chronic' หรือ null)\",
                \"min_temperature\": (ตัวเลข หรือ null),
                \"min_mews_score\": (ตัวเลข หรือ null),
                \"keyword\": \"(คำค้นหาในโน้ต/ชื่อยา/คลินิก/โรค/มาทำแผล/สิทธิ/จังหวัด หรือ null)\",
                \"date_scope\": \"(today, tomorrow, yesterday, this_month, all_time)\"
            }
        ";

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key={$apiKey}";
            $response = Http::withoutVerifying()->timeout(12)->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.1, 
                    'responseMimeType' => 'application/json',
                ]
            ]);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text') ?? '';
                $cleanText = str_replace(['```json', '```', '`'], '', $rawText);
                preg_match('/\{[\s\S]*\}/', $cleanText, $matches);
                $filters = json_decode(trim($matches[0] ?? '{}'), true);
                if (!is_array($filters)) $filters = [];

                // 🧠 Rule-based Override เสริมความแม่นยำ 100% สำหรับคำค้นหายอดฮิต
                if (str_contains($promptText, 'ในระบบ') || str_contains($promptText, 'ทั้งหมด') || str_contains($promptText, 'สะสม')) {
                    $filters['date_scope'] = 'all_time';
                }
                if (str_contains($promptText, 'ห้องพยาบาล') || str_contains($promptText, 'แยกโรงนอน') || str_contains($promptText, 'ฝากนอน')) {
                    $filters['target_table'] = 'recruit_infirmary';
                }
                if (str_contains($promptText, 'นัดพบแพทย์') || str_contains($promptText, 'คิวนัด') || str_contains($promptText, 'นัดวันนี้') || str_contains($promptText, 'นัดพรุ่งนี้')) {
                    $filters['target_table'] = 'recruit_appointments';
                }
                if (str_contains($promptText, 'นำปลด') || str_contains($promptText, 'ปลดทหาร') || str_contains($promptText, 'รอปลด')) {
                    $filters['target_table'] = 'military_discharges';
                }
                if (str_contains($promptText, 'กินยา') || str_contains($promptText, 'รับประทานยา') || str_contains($promptText, 'จิตเวช') || str_contains(strtolower($promptText), 'emar') || str_contains(strtolower($promptText), 'e-mar')) {
                    $filters['target_table'] = 'care_chart_emar';
                }
                if (str_contains($promptText, 'ไม่มารายงานตัว') || str_contains($promptText, 'ขาดหนี')) {
                    $filters['target_table'] = 'patients';
                    $filters['duty_status']  = 'absent';
                    $filters['date_scope']   = 'all_time';
                }
                if (str_contains($promptText, 'ต้องโทษ') || str_contains($promptText, 'เรือนจำ') || str_contains($promptText, 'ดำเนินคดี')) {
                    $filters['target_table'] = 'patients';
                    $filters['duty_status']  = 'imprisoned';
                    $filters['date_scope']   = 'all_time';
                }
                if (str_contains($promptText, 'โรคประจำตัว')) {
                    $filters['target_table']   = 'patients';
                    $filters['requested_info'] = 'chronic_disease';
                    $filters['chronic_filter'] = $filters['chronic_filter'] ?? 'yes';
                    $filters['date_scope']     = 'all_time';
                }
                if (str_contains($promptText, 'สิทธิการรักษา') || str_contains($promptText, 'บัตรทอง') || str_contains($promptText, 'ยังไม่ซิงค์') || str_contains($promptText, 'ไม่มี HN')) {
                    $filters['target_table']   = 'patients';
                    $filters['requested_info'] = 'pttype_info';
                    $filters['date_scope']     = 'all_time';
                }
                if (str_contains($promptText, 'ทำแผล') || str_contains($promptText, 'ตัดไหม') || str_contains($promptText, 'ล้างแผล')) {
                    $filters['target_table'] = 'mews';
                    $filters['keyword']      = $filters['keyword'] ?: 'แผล';
                }
                if (str_contains($promptText, 'โควิด') || str_contains($promptText, 'ไข้หวัดใหญ่') || str_contains($promptText, 'ฮีทสโตรก') || str_contains($promptText, 'ความร้อน') || str_contains($promptText, 'เจ็บป่วยประจำวัน') || str_contains($promptText, 'สาธารณสุข')) {
                    $filters['target_table'] = 'morbidity_reports';
                }

                $targetTable = $filters['target_table'] ?? 'mews';
                $reqInfo     = $filters['requested_info'] ?? null;

                if (($reqInfo === 'chronic_disease' || !empty($filters['chronic_filter'])) && !$isAdmin) {
                    return response()->json([
                        'status' => 'success',
                        'html'   => "<div class='p-5 bg-rose-50 border border-rose-200 rounded-2xl text-center text-rose-700 font-bold shadow-sm'><i class='fa-solid fa-lock text-2xl mb-2 block text-rose-500'></i> ข้อมูลโรคประจำตัวและประวัติสุขภาพเชิงลึก สงวนสิทธิ์การเข้าถึงเฉพาะผู้ดูแลระบบ (Admin) เท่านั้นครับ</div>"
                    ]);
                }

                // =================================================================
                // 🟣 โหมด 1: ข้อมูลระบบ (System Master Data & MEWS Criteria)
                // =================================================================
                if ($targetTable === 'system_info') {
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-purple-100 border-dashed text-[11px] text-purple-400 font-mono text-left'><i class='fa-solid fa-server mr-1'></i> AI ค้นหาจาก: <b class='text-purple-600'>System Master Data</b></div>";
                    if ($reqInfo === 'mews_criteria' || str_contains(strtolower($promptText), 'mews')) {
                        $template = \App\Models\MewsTemplate::where('is_active', true)->first();
                        if (!$template) {
                            $htmlMsg = "<div class='text-rose-600 bg-rose-50 p-4 rounded-xl text-center shadow-sm font-bold'>ไม่พบเกณฑ์ MEWS ที่เปิดใช้งานในระบบขณะนี้ครับ</div>" . $filterDebug;
                        } else {
                            $rules = is_string($template->rules) ? json_decode($template->rules, true) : $template->rules;
                            $htmlMsg = "<div class='text-left mb-3'><span class='text-indigo-600 font-bold text-lg'><i class='fa-solid fa-calculator'></i> เกณฑ์ MEWS ปัจจุบัน: <span class='text-slate-700'>" . e($template->name) . "</span></span></div>";
                            $htmlMsg .= "<div class='text-left text-sm space-y-3 max-h-[50vh] overflow-y-auto pr-2'>";
                            $paramNames = ['sbp' => 'ความดันโลหิต (SBP)', 'hr' => 'ชีพจร (HR)', 'rr' => 'การหายใจ (RR)', 'temp' => 'อุณหภูมิร่างกาย (Temp)', 'o2sat' => 'ออกซิเจนในเลือด (O2 Sat)', 'avpu' => 'ระดับความรู้สึกตัว (AVPU)'];
                            foreach ($paramNames as $key => $title) {
                                if (isset($rules[$key]) && count($rules[$key]) > 0) {
                                    $htmlMsg .= "<div class='bg-white p-3 rounded-xl border border-indigo-100 shadow-sm mb-2'><div class='font-bold text-indigo-800 mb-2 border-b border-indigo-50 pb-1'>{$title}</div><div class='grid grid-cols-1 gap-1'>";
                                    foreach ($rules[$key] as $rule) {
                                        $score = (int) $rule['score'];
                                        $color = $score == 3 ? 'text-rose-700 bg-rose-100 border-rose-200' : ($score == 2 ? 'text-orange-700 bg-orange-100 border-orange-200' : ($score == 1 ? 'text-yellow-700 bg-yellow-100 border-yellow-200' : 'text-emerald-700 bg-emerald-100 border-emerald-200'));
                                        $condition = $key === 'avpu' ? "ประเมินได้: <b>" . e($rule['value']) . "</b>" : "ตั้งแต่ <b>" . e($rule['min']) . "</b> ถึง <b>" . e($rule['max']) . "</b>";
                                        $htmlMsg .= "<div class='flex justify-between items-center py-1.5 border-b border-slate-50 last:border-0 text-xs'><span class='text-slate-600 font-medium'>{$condition}</span><span class='font-bold px-2 py-0.5 rounded border {$color} shadow-sm'>{$score} คะแนน</span></div>";
                                    }
                                    $htmlMsg .= "</div></div>";
                                }
                            }
                            $htmlMsg .= "</div>" . $filterDebug;
                        }
                    } else {
                        $groupedSymptoms = $symptoms->groupBy('category');
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-purple-600 font-bold text-lg'><i class='fa-solid fa-book-medical'></i> รายการอาการฉุกเฉินในระบบ:</span></div><div class='text-left text-sm space-y-3 max-h-[50vh] overflow-y-auto pr-2'>";
                        foreach ($groupedSymptoms as $category => $items) {
                            $htmlMsg .= "<div class='bg-purple-50/50 p-3 rounded-xl border border-purple-100 shadow-sm'><div class='font-bold text-purple-800 mb-2 border-b border-purple-200/60 pb-1'>หมวดหมู่: " . e($category) . "</div><ul class='list-disc list-inside text-slate-700 space-y-1 ml-1'>";
                            foreach ($items as $item) { $htmlMsg .= "<li>" . e($item->name) . "</li>"; }
                            $htmlMsg .= "</ul></div>";
                        }
                        $htmlMsg .= "</div>" . $filterDebug;
                    }
                }

                // =================================================================
                // 🛌 โหมด 2: ห้องพยาบาลหน่วยฝึก & แยกโรงนอน / ฝากนอน รพ.
                // =================================================================
                else if ($targetTable === 'recruit_infirmary' && class_exists('\App\Models\RecruitInfirmaryStay')) {
                    $query = \App\Models\RecruitInfirmaryStay::with(['patient.militaryUnit', 'patient.militaryDischarge', 'militaryUnit'])->orderByDesc('admitted_at');
                    $query->whereHas('patient', $applyPatientScope);

                    $infStatus = $filters['status'] ?? (str_contains($promptText, 'ประวัติ') || str_contains($promptText, 'ทั้งหมด') ? 'all' : 'staying');
                    if (in_array($infStatus, ['staying', 'returned'])) {
                        $query->where('status', $infStatus);
                    }
                    if (!empty($filters['infirmary_location'])) {
                        $query->where('location_type', $filters['infirmary_location']);
                    }
                    if (!empty($filters['intake_batch'])) {
                        $query->where('intake_batch', $filters['intake_batch']);
                    }
                    if (!empty($filters['keyword'])) {
                        $kw = $filters['keyword'];
                        $query->where(function ($q) use ($kw) {
                            $q->where('diagnosis_text', 'LIKE', "%{$kw}%")
                              ->orWhere('bed_or_zone', 'LIKE', "%{$kw}%")
                              ->orWhere('care_instruction', 'LIKE', "%{$kw}%");
                        });
                    }

                    $results = $query->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-teal-100 border-dashed text-[11px] text-teal-600 font-mono text-left'><i class='fa-solid fa-bed-pulse mr-1'></i> AI ค้นหาจาก: <b class='text-teal-700'>ทะเบียนห้องพยาบาลหน่วยฝึก & ฝากนอน รพ. (M-CARE)</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ขณะนี้ไม่มีรายชื่อพลทหารที่แยกนอนในห้องพยาบาลหน่วยฝึกหรือฝากนอน รพ. ตามเงื่อนไขที่ถามครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-teal-700 font-bold text-lg'><i class='fa-solid fa-house-medical-flag'></i> พบข้อมูลแยกโรงนอน / ห้องพยาบาล {$results->count()} รายการ (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2.5 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($results as $stay) {
                            $p = $stay->patient;
                            if (!$p) continue;
                            $uName      = $stay->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-';
                            $daysStay   = max(1, (int) $stay->admitted_at->copy()->startOfDay()->diffInDays(($stay->discharged_at ?? now())->copy()->startOfDay()) + 1);
                            $locBadge   = $stay->location_type === 'unit_infirmary'
                                ? "<span class='bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded text-[11px] font-bold border border-indigo-200'>🏕️ ห้องพยาบาลหน่วยฝึก</span>"
                                : "<span class='bg-amber-100 text-amber-800 px-2 py-0.5 rounded text-[11px] font-bold border border-amber-200'>🏥 ฝากนอน รพ.ค่ายฯ</span>";
                            $dcTag = $p->militaryDischarge
                                ? "<span class='bg-purple-100 text-purple-800 px-2 py-0.5 rounded text-[10px] font-black border border-purple-200'>📁 อยู่ระหว่างรอปลด</span>"
                                : "";
                            $careChartUrl = route('backend.care_chart.show', $p->id);
                            $nameLinkHtml = $renderSoldierProfileLink($p, $uName, $dcTag);

                            $htmlMsg .= "<li class='bg-teal-50/40 hover:bg-white p-3.5 rounded-xl border border-teal-100 transition-colors shadow-sm space-y-1.5'>";
                            $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}{$locBadge}</div>";
                            $htmlMsg .= "<div class='text-xs text-slate-700'><b>สาเหตุ/Dx.:</b> <span class='text-indigo-700 font-bold'>" . e($stay->diagnosis_text) . "</span>" . ($stay->bed_or_zone ? " | <b>โซน/เตียง:</b> " . e($stay->bed_or_zone) : "") . "</div>";
                            $htmlMsg .= "<div class='flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500 pt-1 border-t border-teal-100/60'>";
                            $htmlMsg .= "<span>เริ่มเข้าพัก: " . $stay->admitted_at->addYears(543)->format('d/m/Y H:i') . " น. <b>(รวม {$daysStay} วัน)</b></span>";
                            $htmlMsg .= "<a href='{$careChartUrl}' class='px-2.5 py-0.5 bg-sky-600 hover:bg-sky-700 text-white rounded-md font-bold text-[10px] transition-colors'><i class='fa-solid fa-pills mr-1'></i>ตาราง V/S & ยา</a>";
                            $htmlMsg .= "</div></li>";
                        }
                        $htmlMsg .= "</ul>" . $filterDebug;
                    }
                }

                // =================================================================
                // 📅 โหมด 3: ตารางนัดพบแพทย์ (Recruit Appointments & HosXP OAPP)
                // =================================================================
                else if ($targetTable === 'recruit_appointments' && class_exists('\App\Models\RecruitAppointment')) {
                    $query = \App\Models\RecruitAppointment::with(['patient.militaryUnit', 'militaryUnit'])->orderBy('appointment_date', 'asc')->orderBy('appointment_time', 'asc');
                    $query->whereHas('patient', $applyPatientScope);

                    if (str_contains($promptText, 'วันนี้') || ($filters['date_scope'] ?? '') === 'today') {
                        $query->whereDate('appointment_date', Carbon::today());
                    } elseif (str_contains($promptText, 'พรุ่งนี้') || ($filters['date_scope'] ?? '') === 'tomorrow') {
                        $query->whereDate('appointment_date', Carbon::tomorrow());
                    } else {
                        $query->where('status', 'scheduled')->whereDate('appointment_date', '>=', Carbon::today());
                    }

                    if (!empty($filters['keyword'])) {
                        $kw = $filters['keyword'];
                        $query->where(function ($q) use ($kw) {
                            $q->where('clinic_name', 'LIKE', "%{$kw}%")
                              ->orWhere('hospital_name', 'LIKE', "%{$kw}%")
                              ->orWhere('app_cause', 'LIKE', "%{$kw}%");
                        });
                    }

                    $results = $query->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-sky-100 border-dashed text-[11px] text-sky-500 font-mono text-left'><i class='fa-solid fa-calendar-check mr-1'></i> AI ค้นหาจาก: <b class='text-sky-700'>ตารางนัดพบแพทย์ทหารใหม่ (M-CARE Appointments)</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ไม่พบคิวนัดพบแพทย์ในช่วงเวลาที่สอบถามครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-sky-600 font-bold text-lg'><i class='fa-solid fa-calendar-day'></i> พบคิวนัดพบแพทย์ {$results->count()} รายการ (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2.5 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($results as $app) {
                            $p = $app->patient;
                            if (!$p) continue;
                            $uName        = $app->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-';
                            $hospName     = e($app->hospital_name ?: ($app->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายกฤษณ์สีวะรา'));
                            $dateTh       = $app->appointment_date->addYears(543)->format('d/m/Y');
                            $nameLinkHtml = $renderSoldierProfileLink($p, $uName);

                            $htmlMsg .= "<li class='bg-sky-50/50 hover:bg-white p-3.5 rounded-xl border border-sky-100 transition-colors shadow-sm space-y-1'>";
                            $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}<span class='bg-sky-600 text-white px-2.5 py-0.5 rounded-lg text-xs font-black'>{$dateTh} (" . e($app->appointment_time ?: '08:30') . " น.)</span></div>";
                            $htmlMsg .= "<div class='text-xs text-slate-700'><b>สถานที่:</b> {$hospName} | <b>คลินิก:</b> <span class='text-indigo-700 font-bold'>" . e($app->clinic_name ?: 'ตรวจตามนัด') . "</span></div>";
                            if ($app->note) {
                                $htmlMsg .= "<div class='text-[11px] text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200'><b>การเตรียมตัว:</b> " . e($app->note) . "</div>";
                            }
                            $htmlMsg .= "</li>";
                        }
                        $htmlMsg .= "</ul>" . $filterDebug;
                    }
                }

                // =================================================================
                // 🎖️ โหมด 4: ทะเบียนเอกสารนำปลดทหาร (Military Discharges)
                // =================================================================
                else if ($targetTable === 'military_discharges' && class_exists('\App\Models\MilitaryDischarge')) {
                    $query = \App\Models\MilitaryDischarge::with(['patient.militaryUnit', 'militaryUnit'])->orderByDesc('id');
                    $query->whereHas('patient', $applyPatientScope);

                    if (!empty($filters['intake_batch'])) {
                        $query->where('intake_batch', $filters['intake_batch']);
                    }
                    if (!empty($filters['keyword'])) {
                        $query->where('diagnosis_text', 'LIKE', '%' . $filters['keyword'] . '%');
                    }

                    $results = $query->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-purple-100 border-dashed text-[11px] text-purple-500 font-mono text-left'><i class='fa-solid fa-folder-tree mr-1'></i> AI ค้นหาจาก: <b class='text-purple-700'>ทะเบียนเอกสารนำปลดทหาร (military_discharges)</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ไม่พบรายชื่อในทะเบียนเอกสารนำปลดทหารครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-purple-700 font-bold text-lg'><i class='fa-solid fa-user-shield'></i> พบรายชื่อในทะเบียนนำปลดทหาร {$results->count()} นาย (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2.5 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($results as $dc) {
                            $p = $dc->patient;
                            if (!$p) continue;
                            $uName    = $dc->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-';
                            $stBadge  = $dc->process_status === 'completed'
                                ? "<span class='bg-slate-800 text-white px-2 py-0.5 rounded text-[11px] font-bold'>ปลดประจำการแล้ว</span>"
                                : "<span class='bg-amber-100 text-amber-800 border border-amber-300 px-2 py-0.5 rounded text-[11px] font-bold'>กำลังดำเนินการนำปลด</span>";
                            $nameLinkHtml = $renderSoldierProfileLink($p, $uName);

                            $htmlMsg .= "<li class='bg-purple-50/40 hover:bg-white p-3.5 rounded-xl border border-purple-100 transition-colors shadow-sm'>";
                            $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}{$stBadge}</div>";
                            $htmlMsg .= "<div class='text-xs text-slate-700 mt-1'><b>โรควินิจฉัยนำปลด:</b> <span class='text-rose-600 font-bold'>" . e($dc->diagnosis_text) . "</span></div>";
                            $htmlMsg .= "</li>";
                        }
                        $htmlMsg .= "</ul>" . $filterDebug;
                    }
                }

                // =================================================================
                // 💊 โหมด 5: ตารางการรับประทานยา (e-MAR: จิตเวช / เรื้อรัง / CI)
                // =================================================================
                else if ($targetTable === 'care_chart_emar' && class_exists('\App\Models\PatientMedication') && Schema::hasTable('patient_medications')) {
                    $query = \App\Models\PatientMedication::with('patient.militaryUnit')->where('is_active', true)->orderByDesc('id');
                    $query->whereHas('patient', $applyPatientScope);

                    if (str_contains($promptText, 'จิตเวช') || ($filters['medication_group'] ?? '') === 'psychiatric') {
                        $query->where('care_group', 'psychiatric');
                    } elseif (!empty($filters['medication_group'])) {
                        $query->where('care_group', $filters['medication_group']);
                    }
                    if (!empty($filters['keyword'])) {
                        $query->where('drug_name', 'LIKE', '%' . $filters['keyword'] . '%');
                    }

                    $results = $query->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-cyan-100 border-dashed text-[11px] text-cyan-600 font-mono text-left'><i class='fa-solid fa-pills mr-1'></i> AI ค้นหาจาก: <b class='text-cyan-700'>ระบบบันทึกการรับประทานยา (e-MAR & Care Chart)</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ยังไม่มีรายการยาที่กำลังเปิดใช้งานในหมวดที่ค้นหาครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-cyan-700 font-bold text-lg'><i class='fa-solid fa-pills'></i> พบรายการยาที่ต้องรับประทานต่อเนื่อง {$results->count()} รายการ (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2.5 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($results as $med) {
                            $p = $med->patient;
                            if (!$p) continue;
                            $uName = $p->militaryUnit->short_name ?? $p->company ?? '-';
                            $slots = [];
                            if ($med->take_morning) $slots[] = 'เช้า';
                            if ($med->take_noon)    $slots[] = 'เที่ยง';
                            if ($med->take_evening) $slots[] = 'เย็น';
                            if ($med->take_bedtime) $slots[] = 'ก่อนนอน';
                            $slotStr      = implode(', ', $slots);
                            $careChartUrl = route('backend.care_chart.show', $p->id);
                            $nameLinkHtml = $renderSoldierProfileLink($p, $uName);

                            $htmlMsg .= "<li class='bg-cyan-50/40 hover:bg-white p-3.5 rounded-xl border border-cyan-100 transition-colors shadow-sm space-y-1.5'>";
                            $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}<span class='bg-white border border-cyan-200 text-cyan-800 px-2 py-0.5 rounded text-[11px] font-bold'>" . e($med->care_group_label) . "</span></div>";
                            $htmlMsg .= "<div class='text-xs text-slate-700'><b>ชื่อยา:</b> <span class='text-teal-700 font-black'>" . e($med->drug_name) . "</span> | <b>ขนาด:</b> " . e($med->dosage) . " (" . e($med->meal_timing_label) . ")</div>";
                            $htmlMsg .= "<div class='flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500 pt-1 border-t border-cyan-100/60'>";
                            $htmlMsg .= "<span><b>มื้อเวลาที่ต้องกิน:</b> {$slotStr}</span>";
                            $htmlMsg .= "<a href='{$careChartUrl}' class='px-2.5 py-0.5 bg-teal-600 hover:bg-teal-700 text-white rounded-md font-bold text-[10px] transition-colors'><i class='fa-solid fa-table-list mr-1'></i>เปิดตารางเช็คยา</a>";
                            $htmlMsg .= "</div></li>";
                        }
                        $htmlMsg .= "</ul>" . $filterDebug;
                    }
                }

                // =================================================================
                // 🩺 โหมด 6: รายงานการเจ็บป่วยประจำวัน (โควิด / ไข้หวัดใหญ่ / ลมร้อน / นอน รพ.)
                // =================================================================
                else if ($targetTable === 'morbidity_reports' && class_exists('\App\Models\RecruitMorbidity')) {
                    $query = \App\Models\RecruitMorbidity::with(['patient.militaryUnit', 'militaryUnit'])->latest('onset_date');
                    $query->whereHas('patient', $applyPatientScope);

                    if (!empty($filters['morbidity_category'])) $query->where('category', $filters['morbidity_category']);
                    if (!empty($filters['intake_batch'])) $query->where('intake_batch', $filters['intake_batch']);
                    if (!empty($filters['keyword'])) {
                        $kw = $filters['keyword'];
                        $query->where(function($q) use ($kw) {
                            $q->where('diagnosis_text', 'LIKE', "%{$kw}%")->orWhere('sub_type', 'LIKE', "%{$kw}%");
                        });
                    }

                    $results = $query->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-rose-100 border-dashed text-[11px] text-rose-400 font-mono text-left'><i class='fa-solid fa-database mr-1'></i> AI ค้นหาจาก: <b class='text-rose-600'>รายงานอาการเจ็บป่วยประจำวัน</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ไม่พบรายการในทะเบียนรายงานการเจ็บป่วยประจำวันที่ตรงกับคำถามครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-rose-600 font-bold text-lg'><i class='fa-solid fa-file-waveform'></i> พบข้อมูลในรายงานการเจ็บป่วย {$results->count()} รายการ (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($results as $r) {
                            $p = $r->patient;
                            if (!$p) continue;
                            $uName = $r->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? $p->company ?? '-';
                            $statusBadge = $r->status === 'treating' 
                                ? "<span class='bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs font-bold border border-rose-200'>กำลังรักษา</span>" 
                                : "<span class='bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold border border-emerald-200'>หายป่วยแล้ว</span>";
                            $nameLinkHtml = $renderSoldierProfileLink($p, $uName);

                            $htmlMsg .= "<li class='bg-rose-50/40 hover:bg-white p-3.5 rounded-xl border border-rose-100 transition-colors shadow-sm'>";
                            $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}{$statusBadge}</div>";
                            $htmlMsg .= "<div class='text-xs text-slate-600 mt-1'><b>ประเภท:</b> " . e($r->sub_type) . " | <b>Dx.:</b> <span class='text-indigo-700 font-bold'>" . e($r->diagnosis_text ?: '-') . "</span></div>";
                            $htmlMsg .= "</li>";
                        }
                        $htmlMsg .= "</ul>" . $filterDebug;
                    }
                }

                // =================================================================
                // 🟠 โหมด 7: คัดกรองทหารใหม่แรกรับ (Recruit Screenings)
                // =================================================================
                else if ($targetTable === 'recruit_screenings' && class_exists('\App\Models\RecruitScreening')) {
                    $query = \App\Models\RecruitScreening::with('patient.militaryUnit')->latest();
                    $query->whereHas('patient', $applyPatientScope);

                    if (!empty($filters['intake_batch'])) $query->where('intake_batch', $filters['intake_batch']);
                    if (!empty($filters['color_group'])) $query->where('color_group', $filters['color_group']);
                    if (!empty($filters['keyword'])) $query->where('symptoms_note', 'LIKE', '%' . $filters['keyword'] . '%');

                    $results = $query->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-orange-100 border-dashed text-[11px] text-orange-400 font-mono text-left'><i class='fa-solid fa-database mr-1'></i> AI ค้นหาจากตาราง: <b class='text-orange-500'>recruit_screenings</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>จากข้อมูลคัดกรองพบ <span class='text-2xl font-black text-slate-800 mx-1'>0</span> นาย ที่ตรงตามคำถามครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-orange-600 font-bold text-lg'><i class='fa-solid fa-clipboard-list'></i> พบข้อมูลคัดกรองทหารใหม่ {$results->count()} นาย (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2 max-h-[40vh] overflow-y-auto pr-2'>";
                        foreach ($results as $item) {
                            $p = $item->patient;
                            if (!$p) continue;
                            $unitName  = $p->militaryUnit->short_name ?? $p->company ?? 'ไม่ระบุสังกัด';
                            $groupName = $item->color_group === 'red' ? 'กลุ่มสีแดง (พบเชื้อ/เสี่ยงสูง)' : ($item->color_group === 'yellow' ? 'กลุ่มสีเหลือง (เฝ้าระวัง)' : 'กลุ่มสีเขียว (ปกติ)');
                            $batchTag  = "<span class='text-[11px] text-slate-400 font-semibold'>ผลัด " . e($item->intake_batch) . "</span>";
                            $nameLinkHtml = $renderSoldierProfileLink($p, $unitName, $batchTag);

                            $htmlMsg .= "<li class='bg-amber-50/40 hover:bg-white p-3 rounded-xl border border-amber-100 transition-colors shadow-sm'>";
                            $htmlMsg .= "<div class='mb-1'>{$nameLinkHtml}</div>";
                            $htmlMsg .= "<div class='text-slate-600 text-xs mb-1'>ผลคัดกรอง: <b>{$groupName}</b></div>";
                            if (!empty($item->symptoms_note)) {
                                $htmlMsg .= "<div class='text-xs text-slate-600 font-medium'>บันทึกคัดกรอง: " . e($item->symptoms_note) . "</div>";
                            }
                            $htmlMsg .= "</li>";
                        }
                        $htmlMsg .= "</ul>" . $filterDebug;
                    }
                }

                // =================================================================
                // 🔵 โหมด 8: ข้อมูลกำลังพล (Patients, สิทธิ HosXP, โรคประจำตัว, สถานะกำลังพล)
                // =================================================================
                else if ($targetTable === 'patients') {
                    $query = \App\Models\Patient::with('militaryUnit');
                    $applyPatientScope($query);

                    if (!empty($filters['name'])) {
                        $query->where(function($q) use ($filters) {
                            $q->where('first_name', 'LIKE', '%' . $filters['name'] . '%')
                              ->orWhere('last_name', 'LIKE', '%' . $filters['name'] . '%');
                        });
                    }
                    if (!empty($filters['intake_batch'])) $query->where('intake_batch', $filters['intake_batch']);
                    if (!empty($filters['duty_status'])) {
                        $query->where('duty_status', $filters['duty_status']);
                    }
                    if (($filters['hn_status'] ?? null) === 'unsynced') {
                        $query->where(function($q) {
                            $q->whereNull('hn')->orWhere('hn', '')->orWhereNull('pttype_name')->orWhere('pttype_name', '');
                        });
                    }
                    if ($isAdmin && !empty($filters['chronic_filter'])) {
                        $query->where('chronic_disease_status', $filters['chronic_filter']);
                    } elseif ($isAdmin && $reqInfo === 'chronic_disease' && empty($filters['name'])) {
                        $query->whereIn('chronic_disease_status', ['yes', 'other']);
                    }
                    if (!empty($filters['keyword'])) {
                        $kw = $filters['keyword'];
                        $query->where(function($q) use ($kw, $isAdmin) {
                            $q->where('pttype_name', 'LIKE', "%{$kw}%")
                              ->orWhere('scar_details', 'LIKE', "%{$kw}%")
                              ->orWhere('duty_status_note', 'LIKE', "%{$kw}%")
                              ->orWhere('province', 'LIKE', "%{$kw}%");
                            if ($isAdmin) $q->orWhere('chronic_disease_detail', 'LIKE', "%{$kw}%");
                        });
                    }

                    $results = $query->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-indigo-50 border-dashed text-[11px] text-indigo-400 font-mono text-left'><i class='fa-solid fa-database mr-1'></i> AI ค้นหาจากตาราง: <b class='text-indigo-500'>patients</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>พบข้อมูลกำลังพล <span class='text-2xl font-black text-slate-800 mx-1'>0</span> นาย ที่ตรงตามคำถามครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-blue-600 font-bold text-lg'><i class='fa-solid fa-users'></i> พบข้อมูลกำลังพล {$results->count()} นาย (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($results as $p) {
                            $unitName     = $p->militaryUnit->short_name ?? $p->company ?? 'ไม่ระบุสังกัด';
                            $hnBadge      = $p->hn ? "<span class='bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-bold'>HN: " . e($p->hn) . "</span>" : "<span class='bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs font-bold'>ยังไม่มี HN</span>";
                            $nameLinkHtml = $renderSoldierProfileLink($p, $unitName);

                            $htmlMsg .= "<li class='bg-blue-50/50 hover:bg-white p-4 rounded-xl border border-blue-100 transition-colors shadow-sm'>";
                            $htmlMsg .= "<div class='flex flex-wrap justify-between items-center gap-2 mb-1'>{$nameLinkHtml}{$hnBadge}</div>";
                            $htmlMsg .= "<div class='text-slate-500 text-xs'>ผลัดที่: <b>" . e($p->intake_batch ?: '-') . "</b> | สิทธิ: <b class='text-emerald-700'>" . e($p->pttype_name ?: 'รอซิงค์สิทธิ HosXP') . "</b></div>";
                            if (($p->duty_status ?? 'active') !== 'active') {
                                $htmlMsg .= "<div class='mt-1.5 text-xs font-bold text-orange-700'>สถานะกำลังพล: " . e($p->duty_status_label) . ($p->duty_status_note ? " (" . e($p->duty_status_note) . ")" : "") . "</div>";
                            }
                            if ($isAdmin && $reqInfo === 'chronic_disease') {
                                $htmlMsg .= "<div class='mt-2 pt-2 border-t border-blue-100 text-xs'><span class='font-bold text-rose-700'>โรคประจำตัว:</span> <b>" . e($p->chronic_disease_detail ?: 'ไม่มี') . "</b></div>";
                            }
                            $htmlMsg .= "</li>";
                        }
                        $htmlMsg .= "</ul>" . $filterDebug;
                    }
                }

                // =================================================================
                // 🟢 โหมด 9: ประวัติการประเมิน MEWS & คิวตรวจ OPD / ER
                // =================================================================
                else {
                    $query = \App\Models\MewsAssessment::with('patient.militaryUnit')->latest();
                    $query->whereHas('patient', $applyPatientScope);

                    if (isset($filters['date_scope']) && $filters['date_scope'] !== 'all_time') {
                        if ($filters['date_scope'] === 'today') $query->whereDate('created_at', today());
                        elseif ($filters['date_scope'] === 'yesterday') $query->whereDate('created_at', today()->subDay());
                        elseif ($filters['date_scope'] === 'this_month') $query->whereMonth('created_at', today()->month);
                    }
                    if (!empty($filters['min_temperature'])) $query->where('temperature', '>=', $filters['min_temperature']);
                    if (!empty($filters['min_mews_score'])) $query->where('total_score', '>=', $filters['min_mews_score']);
                    if (!empty($filters['risk_level'])) $query->where('risk_level', $filters['risk_level']);
                    if (!empty($filters['status'])) $query->where('status', $filters['status']);
                    if (!empty($filters['discharge_type'])) $query->where('discharge_type', $filters['discharge_type']);
                    if (!empty($filters['keyword'])) {
                        $kw = $filters['keyword'];
                        $query->where(function($q) use ($kw) {
                            $q->where('symptoms_note', 'LIKE', "%{$kw}%")->orWhere('discharge_details', 'LIKE', "%{$kw}%");
                        });
                    }

                    $results = $query->take(100)->get()->unique('patient_id')->values();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-indigo-50 border-dashed text-[11px] text-indigo-400 font-mono text-left'><i class='fa-solid fa-database mr-1'></i> AI ค้นหาจากตาราง: <b class='text-indigo-500'>mews_assessments</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>พบข้อมูลผู้ป่วย <span class='text-2xl font-black text-slate-800 mx-1'>0</span> นาย ที่ตรงตามคำถามครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-emerald-600 font-bold text-lg'><i class='fa-solid fa-check-circle'></i> พบข้อมูลผู้ป่วย {$results->count()} นาย (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($results->take(50) as $item) {
                            $p = $item->patient;
                            if (!$p) continue;
                            $unitName = $p->militaryUnit->short_name ?? $p->company ?? 'ไม่ระบุสังกัด';

                            $rawSyms   = is_string($item->symptoms) ? json_decode($item->symptoms, true) : ($item->symptoms ?? []);
                            $isErRoute = ($item->assessment_type === 'er') || in_array($item->risk_level, ['observe', 'emergency']) || (!empty($rawSyms));
                            $mewsUrl   = route('backend.mews.show', $item->id);
                            $queueTag  = $item->status === 'pending'
                                ? ($isErRoute ? "<a href='{$mewsUrl}' class='bg-rose-100 hover:bg-rose-200 text-rose-700 px-2.5 py-0.5 rounded-lg text-[11px] font-bold transition-colors'>🚨 กำลังรักษา (ER)</a>" : "<a href='{$mewsUrl}' class='bg-teal-100 hover:bg-teal-200 text-teal-700 px-2.5 py-0.5 rounded-lg text-[11px] font-bold transition-colors'>🩺 รอตรวจ (OPD)</a>")
                                : "<a href='{$mewsUrl}' class='bg-slate-100 hover:bg-slate-200 text-slate-600 px-2.5 py-0.5 rounded-lg text-[11px] font-bold transition-colors'>ดูใบประเมิน MEWS</a>";

                            $hnSmallTag   = "<span class='text-xs text-slate-400 font-normal'>HN: " . e($p->hn ?: '-') . "</span>";
                            $nameLinkHtml = $renderSoldierProfileLink($p, $unitName, $hnSmallTag);

                            $htmlMsg .= "<li class='bg-emerald-50/50 hover:bg-white p-3.5 rounded-xl border border-emerald-100 transition-colors shadow-sm'>";
                            $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2 mb-1'>{$nameLinkHtml}{$queueTag}</div>";
                            $htmlMsg .= "<div class='text-slate-500 text-xs mb-1'>คะแนน MEWS: <b>" . e($item->total_score) . "</b> | Temp: <b>" . e($item->temperature ?? '-') . "°C</b> | BP: <b>" . e($item->systolic_bp ?? '-') . "</b></div>";
                            if (!empty($item->symptoms_note)) {
                                $htmlMsg .= "<div class='text-xs text-indigo-700 font-semibold'><i class='fa-solid fa-notes-medical mr-1'></i> โน้ตอาการ/หัตถการ: " . e($item->symptoms_note) . "</div>";
                            }
                            $htmlMsg .= "</li>";
                        }
                        $htmlMsg .= "</ul>" . $filterDebug;
                    }
                }

                return response()->json(['status' => 'success', 'html' => $htmlMsg]);
            }
            
            return response()->json(['status' => 'error', 'message' => 'API ขัดข้อง หรือเชื่อมต่อไม่สำเร็จ'], 500);

        } catch (\Exception $e) {
            Log::error('AI Executive Query Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'ไม่สามารถประมวลผลคำถามได้ในขณะนี้ กรุณาลองใหม่อีกครั้งครับ'], 500);
        }
    }
}