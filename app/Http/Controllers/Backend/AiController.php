<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CovidVitalLog;
use App\Models\EmergencySymptom;
use App\Models\MewsAssessment;
use App\Models\MewsTemplate;
use App\Models\MilitaryDischarge;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\RecruitAppointment;
use App\Models\RecruitInfirmaryStay;
use App\Models\RecruitMorbidity;
use App\Models\RecruitScreening;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiController extends Controller
{
    // 🌟 ดึง GEMINI_API_KEY แบบปลอดภัย (ทำงานได้แม้เซิร์ฟเวอร์เปิด config:cache)
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

    // 🌟 ชื่อโมเดล Gemini ส่วนกลาง (ปรับเปลี่ยนจุดเดียวได้ทั้งไฟล์)
    private function getGeminiModel(): string
    {
        return (string) (config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-flash-lite-latest'));
    }

    // 🌟 เรียก Gemini API อย่างปลอดภัย (ส่งคีย์ผ่าน Header กันคีย์รั่วลง Log + บังคับ IPv4 ลด DNS Timeout)
    private function callGeminiJson(string $prompt, float $temperature = 0.1, int $timeout = 20, ?array $responseSchema = null): ?array
    {
        $apiKey = $this->getGeminiApiKey();
        if (empty($apiKey)) {
            Log::warning('Gemini API Key is missing');
            return null;
        }

        $model = $this->getGeminiModel();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $generationConfig = [
            'temperature'      => $temperature,
            'responseMimeType' => 'application/json',
        ];
        if ($responseSchema !== null) {
            $generationConfig['responseSchema'] = $responseSchema;
        }

        $response = Http::withoutVerifying()
            ->connectTimeout(6)
            ->timeout($timeout)
            ->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->withHeaders([
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
            ->post($url, [
                'contents'         => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => $generationConfig,
            ]);

        if (!$response->successful()) {
            Log::error('Gemini API HTTP Error: ' . $response->status() . ' | ' . $response->body());
            return null;
        }

        $rawText = (string) ($response->json('candidates.0.content.parts.0.text') ?? '');
        if (trim($rawText) === '') {
            Log::warning('Gemini Empty Response Body', ['body' => $response->json()]);
            return null;
        }

        $cleanText = str_replace(['```json', '```'], '', $rawText);
        preg_match('/\{[\s\S]*\}/', $cleanText, $matches);
        $jsonString = trim($matches[0] ?? $cleanText);

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $jsonString);
            $decoded = json_decode((string) $sanitized, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            Log::warning('Gemini JSON Parse Failed: ' . json_last_error_msg(), ['raw' => mb_substr($rawText, 0, 800)]);
            return null;
        }

        return $decoded;
    }

    // 🌟 แปลงสิทธิ์ผู้ใช้เป็น Array มาตรฐาน
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

    // 🛡️ ตรวจสอบสิทธิ์เข้าถึงข้อมูลโรคประจำตัวตามนโยบาย PDPA จาก SettingService
    private function canViewChronicData($user): bool
    {
        $roles = $this->getUserRoles($user);
        if (in_array('admin', $roles, true)) {
            return true;
        }

        $allowedRoles = class_exists(SettingService::class)
            ? (array) SettingService::get('pdpa_chronic_roles', ['admin'])
            : ['admin'];

        return !empty(array_intersect($roles, $allowedRoles));
    }

    private function canViewAllUnits($user): bool
    {
        $roles = $this->getUserRoles($user);
        return !empty(array_intersect(
            ['admin', 'executive', 'er_nurse', 'opd_nurse', 'registration', 'medical_staff', 'screening'],
            $roles
        ));
    }

    // =========================================================================
    // 🎙️ 1. AI สกัดสัญญาณชีพ อาการฉุกเฉิน และโน้ตหัตถการทั่วไป (Voice/Text to Form)
    // =========================================================================
    public function extractVitals(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        if (empty($this->getGeminiApiKey())) {
            return response()->json(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า GEMINI_API_KEY ในระบบ'], 400);
        }

        $symptoms = EmergencySymptom::where('is_active', true)->get(['code', 'name']);
        $validCodes = $symptoms->pluck('code')->all();
        $symptomsListText = $symptoms->map(fn ($s) => "'{$s->code}'=\"{$s->name}\"")->implode(', ');

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
            $data = $this->callGeminiJson($prompt, 0.1, 12);
            if ($data === null) {
                return response()->json(['status' => 'error', 'message' => 'API ขัดข้อง กรุณาลองใหม่อีกครั้ง'], 500);
            }

            // 🛡️ ทำความสะอาดข้อมูลที่ AI ตอบกลับก่อนส่งเข้าหน้าฟอร์ม
            $allowedAvpu = ['A', 'C', 'V', 'P', 'U'];
            $avpuVal = isset($data['avpu']) ? strtoupper(trim((string) $data['avpu'])) : null;
            $data['avpu'] = in_array($avpuVal, $allowedAvpu, true) ? $avpuVal : null;

            $rawConds = is_array($data['conditions'] ?? null) ? $data['conditions'] : [];
            $data['conditions'] = array_values(array_intersect($rawConds, $validCodes));
            $data['note'] = trim((string) ($data['note'] ?? ''));
            $data['first_aid_advice'] = is_array($data['first_aid_advice'] ?? null)
                ? array_values(array_slice($data['first_aid_advice'], 0, 4))
                : [];

            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('Gemini extractVitals Exception: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'เชื่อมต่อ AI ไม่สำเร็จ'], 500);
        }
    }

    // =========================================================================
    // 🩺 2. AI Clinical Triage Copilot (วิเคราะห์แนวทางปฐมพยาบาลจาก V/S + ยา e-MAR + แยกนอน)
    // =========================================================================
    public function triageCopilot(Request $request)
    {
        if (empty($this->getGeminiApiKey())) {
            return response()->json(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า API Key'], 400);
        }

        $user          = auth()->user();
        $mewsId        = $request->input('mews_id');
        $cacheKey      = null;
        $clinicalNote  = '';
        $chronicInfo   = 'ไม่มีโรคประจำตัว';
        $medsInfo      = 'ไม่มีรายการยาต่อเนื่อง';
        $infirmaryInfo = 'อยู่ในโรงนอนปกติ';

        if ($mewsId) {
            $mews = MewsAssessment::with(['patient.recruitScreening', 'patient.activeInfirmaryStay'])->find($mewsId);
            if (!$mews) {
                return response()->json(['status' => 'error', 'message' => 'ไม่พบข้อมูลใบประเมิน MEWS'], 404);
            }

            // 🛡️ ตรวจสิทธิ์หน่วยฝึกหากผู้ใช้เป็นเสนารักษ์
            if ($mews->patient && !$this->canViewAllUnits($user)) {
                if ((int) $mews->patient->military_unit_id !== (int) $user->military_unit_id) {
                    return response()->json(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูลเคสของหน่วยอื่นครับ'], 403);
                }
            }

            // ผูก Cache กับ updated_at เพื่อให้เมื่อมีการแก้ไขค่า V/S ระบบสร้างคำแนะนำใหม่ทันที
            $cacheKey = 'copilot_advice_mews_' . $mews->id . '_' . ($mews->updated_at?->timestamp ?? 0);
            $cachedAdvice = Cache::get($cacheKey);
            if ($cachedAdvice) {
                return response()->json(['status' => 'success', 'data' => $cachedAdvice]);
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
                if (in_array($mews->patient->chronic_disease_status, ['yes', 'other'], true)) {
                    $chronicInfo = $mews->patient->chronic_disease_detail ?: 'มีโรคประจำตัว';
                }
                if ($mews->patient->activeInfirmaryStay) {
                    $st = $mews->patient->activeInfirmaryStay;
                    $infirmaryInfo = ($st->location_type === 'unit_infirmary' ? 'กำลังนอนห้องพยาบาลหน่วยฝึก' : 'ฝากนอน รพ.ค่ายฯ') . " (Dx: {$st->diagnosis_text})";
                }
                if (class_exists(PatientMedication::class) && Schema::hasTable('patient_medications')) {
                    $activeMeds = PatientMedication::where('patient_id', $mews->patient_id)
                        ->where('is_active', true)
                        ->pluck('drug_name')
                        ->toArray();
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

        $symptomsMap = EmergencySymptom::pluck('name', 'code')->toArray();
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
            $data = $this->callGeminiJson($prompt, 0.2, 10);
            if (is_array($data) && isset($data['advice'])) {
                if ($cacheKey) {
                    Cache::put($cacheKey, $data, 3600);
                }
                return response()->json(['status' => 'success', 'data' => $data]);
            }

            return response()->json(['status' => 'error', 'message' => 'ไม่สามารถสร้างคำแนะนำได้ในขณะนี้'], 500);
        } catch (\Throwable $e) {
            Log::error('Gemini triageCopilot Exception: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'เชื่อมต่อ AI ไม่สำเร็จ'], 500);
        }
    }

    // =========================================================================
    // 🤖 3. AI Data Analyzer สำหรับผู้บริหารและเจ้าหน้าที่ (ครบ 11 โหมด + รองรับตัวกรองทุกมิติ)
    // =========================================================================
    public function askExecutive(Request $request)
    {
        $request->validate(['text' => 'required|string|max:500']);
        $promptText = trim($request->text);

        if (empty($this->getGeminiApiKey())) {
            return response()->json(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า GEMINI_API_KEY'], 400);
        }

        $user           = auth()->user();
        $userRoles      = $this->getUserRoles($user);
        $isAdmin        = in_array('admin', $userRoles, true);
        $canViewAll     = $this->canViewAllUnits($user);
        $canViewChronic = $this->canViewChronicData($user);

        // 🛡️ Closure จำกัดสิทธิ์กรณีผู้ใช้เป็นเสนารักษ์ (เห็นเฉพาะหน่วยและผลัดของตนเอง)
        $applyPatientScope = function ($q) use ($canViewAll, $user) {
            if (!$canViewAll) {
                $q->where('military_unit_id', $user->military_unit_id);
                if (!empty($user->intake_batch)) {
                    $q->where('intake_batch', $user->intake_batch);
                }
            }
        };

        // 🔎 Closure กรองตามชื่อคน (name) และชื่อหน่วยฝึก (unit) ให้ใช้งานได้จริงครบทุกโหมด
        $applyUnitAndNameFilter = function ($q, array $filters) use ($applyPatientScope) {
            $applyPatientScope($q);

            if (!empty($filters['name'])) {
                $nameKw = trim((string) $filters['name']);
                $nameNoSpace = str_replace(' ', '', $nameKw);
                $q->where(function ($nq) use ($nameKw, $nameNoSpace) {
                    $nq->where('first_name', 'LIKE', "%{$nameKw}%")
                       ->orWhere('last_name', 'LIKE', "%{$nameKw}%")
                       ->orWhereRaw("CONCAT(IFNULL(first_name,''), IFNULL(last_name,'')) LIKE ?", ["%{$nameNoSpace}%"]);
                });
            }

            if (!empty($filters['unit'])) {
                $unitKw = trim((string) $filters['unit']);
                $q->where(function ($uq) use ($unitKw) {
                    $uq->where('company', 'LIKE', "%{$unitKw}%")
                       ->orWhere('battalion', 'LIKE', "%{$unitKw}%")
                       ->orWhereHas('militaryUnit', function ($mu) use ($unitKw) {
                           $mu->where('name', 'LIKE', "%{$unitKw}%")
                              ->orWhere('short_name', 'LIKE', "%{$unitKw}%");
                       });
                });
            }

            if (!empty($filters['intake_batch'])) {
                $q->where('intake_batch', $filters['intake_batch']);
            }
        };

        // 🔗 Closure สร้างลิงก์ชื่อพลทหารที่คลิกเปิดไปหน้าแฟ้มประวัติ (patients/show) ได้ทุกโหมด
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

        $symptoms = EmergencySymptom::where('is_active', true)->get();
        $symptomsListText = $symptoms->map(fn ($s) => "'{$s->code}'=\"{$s->name}\"")->implode(', ');

        $prompt = "
            คุณคือ 'KSVR & M-CARE AI Assistant' ผู้ช่วยอัจฉริยะระดับผู้บริหารของโรงพยาบาลค่ายกฤษณ์สีวะรา
            หน้าที่ของคุณคือ: วิเคราะห์คำถามภาษาไทย และสกัดเงื่อนไขการค้นหาออกมาเป็น JSON เท่านั้น

            [1. กฎการเลือกตารางเป้าหมาย (target_table) ทั้ง 11 โหมด]
            - 'outbreak_radar': เมื่อถามถึง 'หน่วยไหนเสี่ยงโรคระบาด', 'เรดาร์โรคระบาด', 'กลุ่มก้อนไข้สูง', 'คลัสเตอร์ไข้'
            - 'online_users': เมื่อถามถึง 'ใครออนไลน์อยู่บ้าง', 'สถานะผู้ใช้งานออนไลน์', 'วันนี้มีการลบข้อมูลอะไรในระบบไหม'
            - 'system_info': เมื่อถามถึง 'รายชื่อโรคฉุกเฉินในระบบ', 'เกณฑ์ประเมิน MEWS', 'คะแนน MEWS คิดยังไง'
            - 'recruit_infirmary': เมื่อถามถึง 'ห้องพยาบาลหน่วยฝึก', 'แยกโรงนอน', 'ฝากนอน รพ.', 'ฝากนอนระหว่างรอปลด', 'ใครนอนห้องพยาบาลบ้าง'
            - 'recruit_appointments': เมื่อถามถึง 'นัดพบแพทย์', 'คิวนัดวันนี้', 'นัดพรุ่งนี้', 'นัดคลินิก', 'ไปตรวจตามนัด'
            - 'military_discharges': เมื่อถามถึง 'นำปลดทหาร', 'เอกสารนำปลด', 'รอปลดประจำการ', 'ปลดทหารแล้ว', 'ปลดทางกาย', 'ปลดทางสุขภาพจิต'
            - 'care_chart_emar': เมื่อถามถึง 'บันทึกการรับประทานยา', 'กินยาต่อเนื่อง', 'ยาจิตเวช', 'e-MAR', 'วัดสัญญาณชีพ 06:00 / 18:00', 'V/S โควิด'
            - 'morbidity_reports': เมื่อถามถึง 'รายงานเจ็บป่วยประจำวัน', 'โควิด', 'ไข้หวัดใหญ่', 'แยกกัก CI', 'บาดเจ็บจากความร้อน/โรคลมร้อน/ฮีทสโตรก/ตะคริวแดด', 'เคสสุขภาพจิต/ซึมเศร้า', 'นอน รพ.ค่ายฯ', 'นอน รพ.สาธารณสุข', หรือถามหาการวินิจฉัยโรค 'Dx.'
            - 'recruit_screenings': เมื่อถามถึง 'คัดกรองทหารใหม่', 'คัดกรองแรกรับ', 'กลุ่มสีเขียว/สีเหลือง/สีแดงตอนคัดกรอง'
            - 'patients': เมื่อถามข้อมูลส่วนตัวกำลังพล, ยอดทหารทั้งหมด, ตำหนิ/แผลเป็น, กรุ๊ปเลือด, BMI, ที่อยู่/จังหวัด, บิดามารดา, 'สิทธิการรักษา/บัตรทอง/ประกันสังคม', 'ใครยังไม่มี HN / ยังไม่ซิงค์ HosXP', 'โรคประจำตัว/แพ้ยา', หรือสถานะกำลังพล ('ไม่มารายงานตัว/ขาดหนี', 'ต้องโทษ/เรือนจำ', 'เสียชีวิต')
            - 'mews': เมื่อถามถึงการประเมินสัญญาณชีพ (MEWS), คิวรอตรวจ OPD, คิวห้องฉุกเฉิน ER, คนไข้มีไข้/ความดัน/ชีพจร, อาการฉุกเฉิน, คนที่มาทำแผล/ตัดไหม/รับยา, หรือสถานะการรักษา (กำลังรักษา, จำหน่ายกลับ, Admit, Refer)

            [2. ค่าของ requested_info]
            - 'scar_details', 'blood_group', 'bmi_info', 'pttype_info', 'chronic_disease', 'duty_status_info', 'education', 'religion', 'contact', 'parents', 'military_info', 'symptoms_list', 'mews_criteria', 'vital_logs'

            [พจนานุกรมรหัสอาการฉุกเฉิน]
            { {$symptomsListText} }

            คำถามจากผู้บริหาร: '{$promptText}'

            รูปแบบ JSON ที่ต้องตอบกลับเท่านั้น:
            {
                \"target_table\": \"('patients', 'mews', 'system_info', 'recruit_screenings', 'morbidity_reports', 'recruit_infirmary', 'recruit_appointments', 'military_discharges', 'care_chart_emar', 'outbreak_radar', หรือ 'online_users')\",
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
                \"discharge_type\": \"('recovered', 'admitted', 'refer', 'admitted_or_refer' หรือ null)\",
                \"morbidity_category\": \"('respiratory', 'heat', 'mental', 'other_ipd' หรือ null)\",
                \"infirmary_location\": \"('unit_infirmary', 'camp_hospital' หรือ null)\",
                \"medication_group\": \"('ci_isolation', 'psychiatric', 'chronic' หรือ null)\",
                \"min_temperature\": (ตัวเลข หรือ null),
                \"min_mews_score\": (ตัวเลข หรือ null),
                \"keyword\": \"(คำค้นหาในโน้ต/ชื่อยา/คลินิก/โรค/มาทำแผล/สิทธิ/จังหวัด หรือ null)\",
                \"date_scope\": \"(today, tomorrow, yesterday, this_month, all_time)\"
            }
        ";

        try {
            $filters = $this->callGeminiJson($prompt, 0.1, 12);
            if ($filters === null) {
                return response()->json(['status' => 'error', 'message' => 'API ขัดข้อง หรือเชื่อมต่อไม่สำเร็จ'], 500);
            }

            $lowerPrompt = strtolower($promptText);

            // 🧠 Rule-based Override เรียงลำดับความชัดเจนเพื่อไม่ให้คำค้นหาทับซ้อนกัน
            if (str_contains($promptText, 'ในระบบ') || str_contains($promptText, 'ทั้งหมด') || str_contains($promptText, 'สะสม')) {
                $filters['date_scope'] = 'all_time';
            }

            if (str_contains($promptText, 'ออนไลน์') || str_contains($promptText, 'ลบข้อมูลอะไร') || str_contains($promptText, 'ประวัติการใช้งาน')) {
                $filters['target_table'] = 'online_users';
            } elseif (str_contains($promptText, 'เสี่ยงระบาด') || str_contains($promptText, 'โรคระบาด') || str_contains($promptText, 'เรดาร์') || str_contains($promptText, 'กลุ่มก้อนไข้')) {
                $filters['target_table'] = 'outbreak_radar';
            } elseif (str_contains($promptText, 'นำปลด') || str_contains($promptText, 'ปลดทหาร') || str_contains($promptText, 'รอปลด')) {
                $filters['target_table'] = 'military_discharges';
            } elseif (str_contains($promptText, 'ห้องพยาบาล') || str_contains($promptText, 'แยกโรงนอน') || str_contains($promptText, 'ฝากนอน')) {
                $filters['target_table'] = 'recruit_infirmary';
            } elseif (str_contains($promptText, 'นัดพบแพทย์') || str_contains($promptText, 'คิวนัด') || str_contains($promptText, 'นัดวันนี้') || str_contains($promptText, 'นัดพรุ่งนี้')) {
                $filters['target_table'] = 'recruit_appointments';
            } elseif (str_contains($promptText, 'กินยา') || str_contains($promptText, 'รับประทานยา') || str_contains($lowerPrompt, 'emar') || str_contains($lowerPrompt, 'e-mar') || str_contains($promptText, '06:00') || str_contains($promptText, '18:00')) {
                $filters['target_table'] = 'care_chart_emar';
                if (str_contains($promptText, '06:00') || str_contains($promptText, '18:00') || str_contains($lowerPrompt, 'v/s')) {
                    $filters['requested_info'] = 'vital_logs';
                }
            } elseif (str_contains($promptText, 'ไม่มารายงานตัว') || str_contains($promptText, 'ขาดหนี')) {
                $filters['target_table'] = 'patients';
                $filters['duty_status']  = 'absent';
                $filters['date_scope']   = 'all_time';
            } elseif (str_contains($promptText, 'ต้องโทษ') || str_contains($promptText, 'เรือนจำ') || str_contains($promptText, 'ดำเนินคดี')) {
                $filters['target_table'] = 'patients';
                $filters['duty_status']  = 'imprisoned';
                $filters['date_scope']   = 'all_time';
            } elseif (str_contains($promptText, 'โรคประจำตัว')) {
                $filters['target_table']   = 'patients';
                $filters['requested_info'] = 'chronic_disease';
                $filters['chronic_filter'] = $filters['chronic_filter'] ?? 'yes';
                $filters['date_scope']     = 'all_time';
            } elseif (str_contains($promptText, 'สิทธิการรักษา') || str_contains($promptText, 'บัตรทอง') || str_contains($promptText, 'ยังไม่ซิงค์') || str_contains($promptText, 'ไม่มี HN')) {
                $filters['target_table']   = 'patients';
                $filters['requested_info'] = 'pttype_info';
                $filters['date_scope']     = 'all_time';
                if (str_contains($promptText, 'ยังไม่ซิงค์') || str_contains($promptText, 'ไม่มี HN')) {
                    $filters['hn_status'] = 'unsynced';
                }
            } elseif (str_contains($promptText, 'ทำแผล') || str_contains($promptText, 'ตัดไหม') || str_contains($promptText, 'ล้างแผล')) {
                $filters['target_table'] = 'mews';
                $filters['keyword']      = $filters['keyword'] ?: 'แผล';
            } elseif (str_contains($promptText, 'โควิด') || str_contains($promptText, 'ไข้หวัดใหญ่') || str_contains($promptText, 'ฮีทสโตรก') || str_contains($promptText, 'ลมร้อน') || str_contains($promptText, 'ความร้อน') || str_contains($promptText, 'เจ็บป่วยประจำวัน') || str_contains($promptText, 'สาธารณสุข') || str_contains($promptText, 'สุขภาพจิต')) {
                $filters['target_table'] = 'morbidity_reports';
                if (str_contains($promptText, 'สุขภาพจิต') || str_contains($promptText, 'ซึมเศร้า') || str_contains($promptText, 'จิตเวช')) {
                    $filters['morbidity_category'] = 'mental';
                }
            }

            // เสริมกรณีถาม Admit และ ส่งต่อ (Refer) พร้อมกันในโหมด MEWS
            if (($filters['target_table'] ?? 'mews') === 'mews'
                && (str_contains($lowerPrompt, 'admit') || str_contains($promptText, 'นอน รพ'))
                && (str_contains($lowerPrompt, 'refer') || str_contains($promptText, 'ส่งต่อ'))
            ) {
                $filters['discharge_type'] = 'admitted_or_refer';
            }

            $targetTable = $filters['target_table'] ?? 'mews';
            $reqInfo     = $filters['requested_info'] ?? null;

            // 🛡️ ตรวจสิทธิ์ข้อมูลโรคประจำตัวตามนโยบาย PDPA จาก SettingService
            if (($reqInfo === 'chronic_disease' || !empty($filters['chronic_filter'])) && !$canViewChronic) {
                return response()->json([
                    'status' => 'success',
                    'html'   => "<div class='p-5 bg-rose-50 border border-rose-200 rounded-2xl text-center text-rose-700 font-bold shadow-sm'><i class='fa-solid fa-lock text-2xl mb-2 block text-rose-500'></i> ข้อมูลโรคประจำตัวและประวัติสุขภาพเชิงลึก สงวนสิทธิ์การเข้าถึงตามนโยบาย PDPA ของโรงพยาบาลครับ</div>",
                ]);
            }

            // =================================================================
            // 🔴 โหมดใหม่ 10: เรดาร์เฝ้าระวังโรคระบาด (ดึงเกณฑ์จริงจาก SettingService)
            // =================================================================
            if ($targetTable === 'outbreak_radar') {
                $tempThreshold = class_exists(SettingService::class) ? (float) SettingService::get('outbreak_temp_threshold', 38.0) : 38.0;
                $minCases      = class_exists(SettingService::class) ? (int) SettingService::get('outbreak_min_cases', 3) : 3;
                $windowHours   = class_exists(SettingService::class) ? (int) SettingService::get('outbreak_window_hours', 24) : 24;

                $feverMews = MewsAssessment::with('patient.militaryUnit')
                    ->where('status', '!=', 'cancelled')
                    ->where('temperature', '>=', $tempThreshold)
                    ->where('created_at', '>=', now()->subHours($windowHours))
                    ->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters))
                    ->get()
                    ->unique('patient_id');

                $feverByUnit = $feverMews->groupBy(fn ($m) => $m->patient->militaryUnit->short_name ?? $m->patient->company ?? 'ไม่ระบุหน่วย');

                $activeResp = collect();
                if (class_exists(RecruitMorbidity::class)) {
                    $activeResp = RecruitMorbidity::with(['patient.militaryUnit', 'militaryUnit'])
                        ->where('category', 'respiratory')
                        ->where('status', 'treating')
                        ->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters))
                        ->get();
                }

                $filterDebug = "<div class='mt-4 pt-3 border-t border-rose-100 border-dashed text-[11px] text-rose-500 font-mono text-left'><i class='fa-solid fa-radar mr-1'></i> เกณฑ์เรดาร์ปัจจุบัน (System Settings): <b>ไข้ ≥ {$tempThreshold}°C | ≥ {$minCases} นาย ภายใน {$windowHours} ชม.</b></div>";

                $htmlMsg = "<div class='text-left mb-3'><span class='text-rose-600 font-bold text-lg'><i class='fa-solid fa-virus-covid'></i> สรุปเรดาร์เฝ้าระวังโรคระบาด (Outbreak Radar)</span></div>";
                $htmlMsg .= "<div class='space-y-3 text-left text-sm max-h-[45vh] overflow-y-auto pr-1'>";

                if ($feverByUnit->isEmpty()) {
                    $htmlMsg .= "<div class='p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 font-bold'><i class='fa-solid fa-shield-check mr-1.5'></i>ไม่พบกลุ่มก้อนผู้ป่วยไข้สูง (≥ {$tempThreshold}°C) ในรอบ {$windowHours} ชั่วโมงที่ผ่านมาครับ</div>";
                } else {
                    foreach ($feverByUnit as $uLabel => $list) {
                        $cnt = $list->count();
                        $isAlert = $cnt >= $minCases;
                        $boxCls = $isAlert ? 'bg-rose-50 border-rose-300' : 'bg-amber-50 border-amber-200';
                        $badge = $isAlert
                            ? "<span class='px-2 py-0.5 rounded bg-rose-600 text-white text-[11px] font-black'>🚨 เกินเกณฑ์เฝ้าระวัง ({$cnt} นาย)</span>"
                            : "<span class='px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[11px] font-bold'>เฝ้าระวัง ({$cnt} นาย)</span>";

                        $htmlMsg .= "<div class='p-3.5 rounded-xl border {$boxCls} space-y-1.5'>";
                        $htmlMsg .= "<div class='flex flex-wrap justify-between items-center gap-2'><span class='font-black text-slate-800'>🏕️ " . e($uLabel) . "</span>{$badge}</div>";
                        foreach ($list as $item) {
                            $p = $item->patient;
                            $htmlMsg .= "<div class='text-xs text-slate-600 flex justify-between items-center'>" . $renderSoldierProfileLink($p, $uLabel) . "<span class='font-mono font-bold text-rose-600'>" . e($item->temperature) . "°C</span></div>";
                        }
                        $htmlMsg .= '</div>';
                    }
                }

                if ($activeResp->isNotEmpty()) {
                    $covidCnt = $activeResp->where('sub_type', 'covid')->count();
                    $fluCnt   = $activeResp->where('sub_type', 'flu')->count();
                    $htmlMsg .= "<div class='p-3.5 rounded-xl bg-sky-50 border border-sky-200 text-xs text-sky-900'><b>ยอดแยกกักโรคทางเดินหายใจปัจจุบัน:</b> โควิด-19 จำนวน <b>{$covidCnt}</b> นาย | ไข้หวัดใหญ่ จำนวน <b>{$fluCnt}</b> นาย (รวม <b>{$activeResp->count()}</b> นาย)</div>";
                }

                $htmlMsg .= '</div>' . $filterDebug;
            }

            // =================================================================
            // 🟢 โหมดใหม่ 11: สถานะเจ้าหน้าที่ออนไลน์ & สรุป Audit Log วันนี้ (เฉพาะ Admin)
            // =================================================================
            elseif ($targetTable === 'online_users') {
                if (!$isAdmin) {
                    return response()->json([
                        'status' => 'success',
                        'html'   => "<div class='p-5 bg-rose-50 border border-rose-200 rounded-2xl text-center text-rose-700 font-bold shadow-sm'><i class='fa-solid fa-lock text-2xl mb-2 block text-rose-500'></i> ข้อมูลสถานะผู้ใช้งานออนไลน์และประวัติระบบ (Audit Log) สงวนสิทธิ์เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นครับ</div>",
                    ]);
                }

                $onlineUsers = Schema::hasColumn('users', 'last_seen_at')
                    ? User::with('militaryUnit')->where('last_seen_at', '>=', now()->subMinutes(5))->orderByDesc('last_seen_at')->get()
                    : collect();

                $todayDeletes = SystemLog::with('user')
                    ->whereDate('created_at', Carbon::today())
                    ->where(function ($q) {
                        $q->where('action', 'deleted')->orWhere('action', 'like', '%delete%');
                    })
                    ->latest()
                    ->take(10)
                    ->get();

                $filterDebug = "<div class='mt-4 pt-3 border-t border-emerald-100 border-dashed text-[11px] text-emerald-600 font-mono text-left'><i class='fa-solid fa-server mr-1'></i> AI ตรวจสอบจาก: <b>Online Activity Cache & System Audit Logs</b></div>";

                $htmlMsg = "<div class='text-left mb-3'><span class='text-emerald-700 font-bold text-lg'><i class='fa-solid fa-signal'></i> ผู้ใช้งานที่กำลังออนไลน์ ({$onlineUsers->count()} คน)</span></div>";
                $htmlMsg .= "<div class='space-y-3 text-left text-sm max-h-[45vh] overflow-y-auto pr-1'>";

                if ($onlineUsers->isEmpty()) {
                    $htmlMsg .= "<div class='p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 text-center'>ยังไม่มีผู้ใช้งานรายอื่นออนไลน์ในรอบ 5 นาทีที่ผ่านมาครับ</div>";
                } else {
                    $htmlMsg .= "<ul class='space-y-1.5'>";
                    foreach ($onlineUsers as $ou) {
                        $uUnit = $ou->militaryUnit->short_name ?? 'ส่วนกลาง รพ.';
                        $seen  = $ou->last_seen_at ? Carbon::parse($ou->last_seen_at)->locale('th')->diffForHumans() : 'เมื่อสักครู่';
                        $htmlMsg .= "<li class='p-2.5 rounded-xl bg-emerald-50/60 border border-emerald-200 flex items-center justify-between gap-2 text-xs'>";
                        $htmlMsg .= "<div><span class='inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1.5'></span><b class='text-slate-800'>" . e(trim(($ou->rank ?? '') . ' ' . $ou->name)) . "</b> <span class='text-indigo-600 font-semibold'>(" . e($uUnit) . ")</span></div>";
                        $htmlMsg .= "<span class='text-slate-500 font-mono'>" . e($seen) . '</span></li>';
                    }
                    $htmlMsg .= '</ul>';
                }

                $htmlMsg .= "<div class='pt-2 border-t border-slate-200'><div class='font-bold text-xs text-rose-700 mb-1.5'><i class='fa-solid fa-trash-can mr-1'></i>ประวัติการลบข้อมูลวันนี้ ({$todayDeletes->count()} รายการ):</div>";
                if ($todayDeletes->isEmpty()) {
                    $htmlMsg .= "<div class='text-xs text-emerald-700 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-100'>✅ วันนี้ยังไม่มีรายการลบข้อมูลในระบบครับ</div>";
                } else {
                    foreach ($todayDeletes as $del) {
                        $summary = is_array($del->payload) ? ($del->payload['summary'] ?? $del->action) : $del->action;
                        $actor = $del->user->name ?? 'System';
                        $htmlMsg .= "<div class='text-xs text-slate-700 py-1 border-b border-slate-100 last:border-0'>• <b>" . e($actor) . '</b>: ' . e($summary) . " <span class='text-slate-400 font-mono'>(" . $del->created_at->format('H:i') . ' น.)</span></div>';
                    }
                }
                $htmlMsg .= '</div></div>' . $filterDebug;
            }

            // =================================================================
            // 🟣 โหมด 1: ข้อมูลระบบ (System Master Data & MEWS Criteria)
            // =================================================================
            elseif ($targetTable === 'system_info') {
                $filterDebug = "<div class='mt-4 pt-3 border-t border-purple-100 border-dashed text-[11px] text-purple-400 font-mono text-left'><i class='fa-solid fa-server mr-1'></i> AI ค้นหาจาก: <b class='text-purple-600'>System Master Data</b></div>";
                if ($reqInfo === 'mews_criteria' || str_contains($lowerPrompt, 'mews')) {
                    $template = MewsTemplate::where('is_active', true)->first();
                    if (!$template) {
                        $htmlMsg = "<div class='text-rose-600 bg-rose-50 p-4 rounded-xl text-center shadow-sm font-bold'>ไม่พบเกณฑ์ MEWS ที่เปิดใช้งานในระบบขณะนี้ครับ</div>" . $filterDebug;
                    } else {
                        $rules = is_string($template->rules) ? json_decode($template->rules, true) : $template->rules;
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-indigo-600 font-bold text-lg'><i class='fa-solid fa-calculator'></i> เกณฑ์ MEWS ปัจจุบัน: <span class='text-slate-700'>" . e($template->name) . '</span></span></div>';
                        $htmlMsg .= "<div class='text-left text-sm space-y-3 max-h-[50vh] overflow-y-auto pr-2'>";
                        $paramNames = ['sbp' => 'ความดันโลหิต (SBP)', 'hr' => 'ชีพจร (HR)', 'rr' => 'การหายใจ (RR)', 'temp' => 'อุณหภูมิร่างกาย (Temp)', 'o2sat' => 'ออกซิเจนในเลือด (O2 Sat)', 'avpu' => 'ระดับความรู้สึกตัว (AVPU)'];
                        foreach ($paramNames as $key => $title) {
                            if (isset($rules[$key]) && count($rules[$key]) > 0) {
                                $htmlMsg .= "<div class='bg-white p-3 rounded-xl border border-indigo-100 shadow-sm mb-2'><div class='font-bold text-indigo-800 mb-2 border-b border-indigo-50 pb-1'>{$title}</div><div class='grid grid-cols-1 gap-1'>";
                                foreach ($rules[$key] as $rule) {
                                    $score = (int) $rule['score'];
                                    $color = $score == 3 ? 'text-rose-700 bg-rose-100 border-rose-200' : ($score == 2 ? 'text-orange-700 bg-orange-100 border-orange-200' : ($score == 1 ? 'text-yellow-700 bg-yellow-100 border-yellow-200' : 'text-emerald-700 bg-emerald-100 border-emerald-200'));
                                    $condition = $key === 'avpu' ? 'ประเมินได้: <b>' . e($rule['value']) . '</b>' : 'ตั้งแต่ <b>' . e($rule['min']) . '</b> ถึง <b>' . e($rule['max']) . '</b>';
                                    $htmlMsg .= "<div class='flex justify-between items-center py-1.5 border-b border-slate-50 last:border-0 text-xs'><span class='text-slate-600 font-medium'>{$condition}</span><span class='font-bold px-2 py-0.5 rounded border {$color} shadow-sm'>{$score} คะแนน</span></div>";
                                }
                                $htmlMsg .= '</div></div>';
                            }
                        }
                        $htmlMsg .= '</div>' . $filterDebug;
                    }
                } else {
                    $groupedSymptoms = $symptoms->groupBy('category');
                    $htmlMsg = "<div class='text-left mb-3'><span class='text-purple-600 font-bold text-lg'><i class='fa-solid fa-book-medical'></i> รายการอาการฉุกเฉินในระบบ:</span></div><div class='text-left text-sm space-y-3 max-h-[50vh] overflow-y-auto pr-2'>";
                    foreach ($groupedSymptoms as $category => $items) {
                        $htmlMsg .= "<div class='bg-purple-50/50 p-3 rounded-xl border border-purple-100 shadow-sm'><div class='font-bold text-purple-800 mb-2 border-b border-purple-200/60 pb-1'>หมวดหมู่: " . e($category) . "</div><ul class='list-disc list-inside text-slate-700 space-y-1 ml-1'>";
                        foreach ($items as $item) {
                            $htmlMsg .= '<li>' . e($item->name) . '</li>';
                        }
                        $htmlMsg .= '</ul></div>';
                    }
                    $htmlMsg .= '</div>' . $filterDebug;
                }
            }

            // =================================================================
            // 🛌 โหมด 2: ห้องพยาบาลหน่วยฝึก & แยกโรงนอน / ฝากนอน รพ.
            // =================================================================
            elseif ($targetTable === 'recruit_infirmary' && class_exists(RecruitInfirmaryStay::class)) {
                $query = RecruitInfirmaryStay::with(['patient.militaryUnit', 'patient.militaryDischarge', 'militaryUnit'])->orderByDesc('admitted_at');
                $query->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters));

                $infStatus = $filters['status'] ?? (str_contains($promptText, 'ประวัติ') || str_contains($promptText, 'ทั้งหมด') ? 'all' : 'staying');
                if (in_array($infStatus, ['staying', 'returned'], true)) {
                    $query->where('status', $infStatus);
                }
                if (!empty($filters['infirmary_location'])) {
                    $query->where('location_type', $filters['infirmary_location']);
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
                $filterDebug = "<div class='mt-4 pt-3 border-t border-teal-100 border-dashed text-[11px] text-teal-600 font-mono text-left'><i class='fa-solid fa-bed-pulse mr-1'></i> AI ค้นหาจาก: <b class='text-teal-700'>ทะเบียนห้องพยาบาลหน่วยฝึก &amp; ฝากนอน รพ. (M-CARE)</b></div>";

                if ($results->isEmpty()) {
                    $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ขณะนี้ไม่มีรายชื่อพลทหารที่แยกนอนในห้องพยาบาลหน่วยฝึกหรือฝากนอน รพ. ตามเงื่อนไขที่ถามครับ</div>" . $filterDebug;
                } else {
                    $htmlMsg = "<div class='text-left mb-3'><span class='text-teal-700 font-bold text-lg'><i class='fa-solid fa-house-medical-flag'></i> พบข้อมูลแยกโรงนอน / ห้องพยาบาล {$results->count()} รายการ (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2.5 max-h-[42vh] overflow-y-auto pr-2'>";
                    foreach ($results as $stay) {
                        $p = $stay->patient;
                        if (!$p) {
                            continue;
                        }
                        $uName    = $stay->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-';
                        $daysStay = max(1, (int) $stay->admitted_at->copy()->startOfDay()->diffInDays(($stay->discharged_at ?? now())->copy()->startOfDay()) + 1);
                        $locBadge = $stay->location_type === 'unit_infirmary'
                            ? "<span class='bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded text-[11px] font-bold border border-indigo-200'>🏕️ ห้องพยาบาลหน่วยฝึก</span>"
                            : "<span class='bg-amber-100 text-amber-800 px-2 py-0.5 rounded text-[11px] font-bold border border-amber-200'>🏥 ฝากนอน รพ.ค่ายฯ</span>";
                        $dcTag = $p->militaryDischarge
                            ? "<span class='bg-purple-100 text-purple-800 px-2 py-0.5 rounded text-[10px] font-black border border-purple-200'>📁 อยู่ระหว่างรอปลด</span>"
                            : '';
                        $careChartUrl = route('backend.care_chart.show', $p->id);
                        $nameLinkHtml = $renderSoldierProfileLink($p, $uName, $dcTag);

                        $htmlMsg .= "<li class='bg-teal-50/40 hover:bg-white p-3.5 rounded-xl border border-teal-100 transition-colors shadow-sm space-y-1.5'>";
                        $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}{$locBadge}</div>";
                        $htmlMsg .= "<div class='text-xs text-slate-700'><b>สาเหตุ/Dx.:</b> <span class='text-indigo-700 font-bold'>" . e($stay->diagnosis_text) . '</span>' . ($stay->bed_or_zone ? ' | <b>โซน/เตียง:</b> ' . e($stay->bed_or_zone) : '') . '</div>';
                        $htmlMsg .= "<div class='flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500 pt-1 border-t border-teal-100/60'>";
                        $htmlMsg .= '<span>เริ่มเข้าพัก: ' . $stay->admitted_at->copy()->addYears(543)->format('d/m/Y H:i') . " น. <b>(รวม {$daysStay} วัน)</b></span>";
                        $htmlMsg .= "<a href='{$careChartUrl}' class='px-2.5 py-0.5 bg-sky-600 hover:bg-sky-700 text-white rounded-md font-bold text-[10px] transition-colors'><i class='fa-solid fa-pills mr-1'></i>ตาราง V/S &amp; ยา</a>";
                        $htmlMsg .= '</div></li>';
                    }
                    $htmlMsg .= '</ul>' . $filterDebug;
                }
            }

            // =================================================================
            // 📅 โหมด 3: ตารางนัดพบแพทย์ (Recruit Appointments & HosXP OAPP)
            // =================================================================
            elseif ($targetTable === 'recruit_appointments' && class_exists(RecruitAppointment::class)) {
                $query = RecruitAppointment::with(['patient.militaryUnit', 'militaryUnit'])->orderBy('appointment_date', 'asc')->orderBy('appointment_time', 'asc');
                $query->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters));

                if (str_contains($promptText, 'วันนี้') && str_contains($promptText, 'พรุ่งนี้')) {
                    $query->where('status', 'scheduled')->whereBetween('appointment_date', [Carbon::today()->format('Y-m-d'), Carbon::tomorrow()->format('Y-m-d')]);
                } elseif (str_contains($promptText, 'วันนี้') || ($filters['date_scope'] ?? '') === 'today') {
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
                        if (!$p) {
                            continue;
                        }
                        $uName        = $app->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-';
                        $hospName     = e($app->hospital_name ?: ($app->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายกฤษณ์สีวะรา'));
                        $dateTh       = $app->appointment_date->copy()->addYears(543)->format('d/m/Y');
                        $nameLinkHtml = $renderSoldierProfileLink($p, $uName);

                        $htmlMsg .= "<li class='bg-sky-50/50 hover:bg-white p-3.5 rounded-xl border border-sky-100 transition-colors shadow-sm space-y-1'>";
                        $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}<span class='bg-sky-600 text-white px-2.5 py-0.5 rounded-lg text-xs font-black'>{$dateTh} (" . e($app->appointment_time ?: '08:30') . ' น.)</span></div>';
                        $htmlMsg .= "<div class='text-xs text-slate-700'><b>สถานที่:</b> {$hospName} | <b>คลินิก:</b> <span class='text-indigo-700 font-bold'>" . e($app->clinic_name ?: 'ตรวจตามนัด') . '</span></div>';
                        if ($app->note) {
                            $htmlMsg .= "<div class='text-[11px] text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200'><b>การเตรียมตัว:</b> " . e($app->note) . '</div>';
                        }
                        $htmlMsg .= '</li>';
                    }
                    $htmlMsg .= '</ul>' . $filterDebug;
                }
            }

            // =================================================================
            // 🎖️ โหมด 4: ทะเบียนเอกสารนำปลดทหาร (รองรับกรอง ปลดทางกาย / ปลดทางสุขภาพจิต)
            // =================================================================
            elseif ($targetTable === 'military_discharges' && class_exists(MilitaryDischarge::class)) {
                $query = MilitaryDischarge::with(['patient.militaryUnit', 'militaryUnit'])->orderByDesc('id');
                $query->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters));

                if (str_contains($promptText, 'สุขภาพจิต') || str_contains($promptText, 'จิตเวช')) {
                    $query->where('diagnosis_text', 'LIKE', '%จิต%');
                } elseif (!empty($filters['keyword'])) {
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
                        if (!$p) {
                            continue;
                        }
                        $uName   = $dc->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-';
                        $stBadge = $dc->process_status === 'completed'
                            ? "<span class='bg-slate-800 text-white px-2 py-0.5 rounded text-[11px] font-bold'>ปลดประจำการแล้ว</span>"
                            : "<span class='bg-amber-100 text-amber-800 border border-amber-300 px-2 py-0.5 rounded text-[11px] font-bold'>กำลังดำเนินการนำปลด</span>";
                        $nameLinkHtml = $renderSoldierProfileLink($p, $uName);

                        $htmlMsg .= "<li class='bg-purple-50/40 hover:bg-white p-3.5 rounded-xl border border-purple-100 transition-colors shadow-sm'>";
                        $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}{$stBadge}</div>";
                        $htmlMsg .= "<div class='text-xs text-slate-700 mt-1'><b>โรควินิจฉัยนำปลด:</b> <span class='text-rose-600 font-bold'>" . e($dc->diagnosis_text) . '</span></div>';
                        $htmlMsg .= '</li>';
                    }
                    $htmlMsg .= '</ul>' . $filterDebug;
                }
            }

            // =================================================================
            // 💊 โหมด 5: ตารางการรับประทานยา (e-MAR + PRN/ยาทา) และสัญญาณชีพ 06:00/18:00
            // =================================================================
            elseif ($targetTable === 'care_chart_emar' && class_exists(PatientMedication::class) && Schema::hasTable('patient_medications')) {
                // โหมดย่อย 5A: ถามหาสัญญาณชีพรายวัน 06:00 / 18:00 (CovidVitalLog)
                if ($reqInfo === 'vital_logs' && class_exists(CovidVitalLog::class) && Schema::hasTable('covid_vital_logs')) {
                    $vQuery = CovidVitalLog::with('patient.militaryUnit')->orderByDesc('record_date')->orderByDesc('round_time');
                    $vQuery->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters));

                    if (($filters['date_scope'] ?? '') === 'today' || str_contains($promptText, 'วันนี้')) {
                        $vQuery->whereDate('record_date', Carbon::today());
                    }
                    if (!empty($filters['min_temperature'])) {
                        $vQuery->where('temperature', '>=', $filters['min_temperature']);
                    }

                    $vResults = $vQuery->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-cyan-100 border-dashed text-[11px] text-cyan-600 font-mono text-left'><i class='fa-solid fa-temperature-half mr-1'></i> AI ค้นหาจาก: <b class='text-cyan-700'>บันทึกสัญญาณชีพรายวัน 06:00/18:00 (covid_vital_logs)</b></div>";

                    if ($vResults->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ไม่พบบันทึกสัญญาณชีพ 06:00 / 18:00 ตามเงื่อนไขที่ถามครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-cyan-700 font-bold text-lg'><i class='fa-solid fa-heart-pulse'></i> พบบันทึกสัญญาณชีพรายวัน {$vResults->count()} รายการ:</span></div><ul class='text-left text-sm space-y-2 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($vResults as $vl) {
                            $p = $vl->patient;
                            if (!$p) {
                                continue;
                            }
                            $uName = $p->militaryUnit->short_name ?? $p->company ?? '-';
                            $dateTh = $vl->record_date ? $vl->record_date->copy()->addYears(543)->format('d/m/Y') : '-';
                            $htmlMsg .= "<li class='bg-cyan-50/40 p-3 rounded-xl border border-cyan-100 text-xs space-y-1'>";
                            $htmlMsg .= "<div class='flex justify-between items-center'>" . $renderSoldierProfileLink($p, $uName) . "<span class='font-mono font-bold text-cyan-700'>{$dateTh} ({$vl->round_time} น.)</span></div>";
                            $htmlMsg .= "<div class='text-slate-600'>Temp: <b>" . e($vl->temperature) . '°C</b> | HR: <b>' . e($vl->heart_rate) . '</b> | BP: <b>' . e($vl->systolic_bp) . '/' . e($vl->diastolic_bp) . '</b> | O2: <b>' . e($vl->o2sat) . '%</b> | MEWS: <b>' . e($vl->mews_score) . '</b></div>';
                            $htmlMsg .= '</li>';
                        }
                        $htmlMsg .= '</ul>' . $filterDebug;
                    }
                } else {
                    // โหมดย่อย 5B: รายการยา e-MAR (รองรับ PRN และ custom_slot_text)
                    $query = PatientMedication::with('patient.militaryUnit')->where('is_active', true)->orderByDesc('id');
                    $query->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters));

                    if (str_contains($promptText, 'จิตเวช') || ($filters['medication_group'] ?? '') === 'psychiatric') {
                        $query->where('care_group', 'psychiatric');
                    } elseif (!empty($filters['medication_group'])) {
                        $query->where('care_group', $filters['medication_group']);
                    }
                    if (!empty($filters['keyword'])) {
                        $query->where('drug_name', 'LIKE', '%' . $filters['keyword'] . '%');
                    }

                    $results = $query->take(50)->get();
                    $filterDebug = "<div class='mt-4 pt-3 border-t border-cyan-100 border-dashed text-[11px] text-cyan-600 font-mono text-left'><i class='fa-solid fa-pills mr-1'></i> AI ค้นหาจาก: <b class='text-cyan-700'>ระบบบันทึกการรับประทานยา (e-MAR &amp; Care Chart)</b></div>";

                    if ($results->isEmpty()) {
                        $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ยังไม่มีรายการยาที่กำลังเปิดใช้งานในหมวดที่ค้นหาครับ</div>" . $filterDebug;
                    } else {
                        $htmlMsg = "<div class='text-left mb-3'><span class='text-cyan-700 font-bold text-lg'><i class='fa-solid fa-pills'></i> พบรายการยาที่ต้องรับประทานต่อเนื่อง {$results->count()} รายการ (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2.5 max-h-[42vh] overflow-y-auto pr-2'>";
                        foreach ($results as $med) {
                            $p = $med->patient;
                            if (!$p) {
                                continue;
                            }
                            $uName = $p->militaryUnit->short_name ?? $p->company ?? '-';
                            $slots = [];
                            if ($med->take_morning) $slots[] = 'เช้า';
                            if ($med->take_noon)    $slots[] = 'เที่ยง';
                            if ($med->take_evening) $slots[] = 'เย็น';
                            if ($med->take_bedtime) $slots[] = 'ก่อนนอน';
                            if ($med->take_prn)     $slots[] = $med->custom_slot_text ?: 'เวลามีอาการ (PRN)';
                            $slotStr      = !empty($slots) ? implode(', ', $slots) : ($med->custom_slot_text ?: 'ตามแพทย์สั่ง');
                            $careChartUrl = route('backend.care_chart.show', $p->id);
                            $nameLinkHtml = $renderSoldierProfileLink($p, $uName);

                            $htmlMsg .= "<li class='bg-cyan-50/40 hover:bg-white p-3.5 rounded-xl border border-cyan-100 transition-colors shadow-sm space-y-1.5'>";
                            $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}<span class='bg-white border border-cyan-200 text-cyan-800 px-2 py-0.5 rounded text-[11px] font-bold'>" . e($med->care_group_label) . '</span></div>';
                            $htmlMsg .= "<div class='text-xs text-slate-700'><b>ชื่อยา:</b> <span class='text-teal-700 font-black'>" . e($med->drug_name) . '</span> | <b>ขนาด:</b> ' . e($med->dosage) . ' (' . e($med->meal_timing_label) . ')</div>';
                            $htmlMsg .= "<div class='flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500 pt-1 border-t border-cyan-100/60'>";
                            $htmlMsg .= '<span><b>มื้อเวลา/วิธีใช้:</b> ' . e($slotStr) . '</span>';
                            $htmlMsg .= "<a href='{$careChartUrl}' class='px-2.5 py-0.5 bg-teal-600 hover:bg-teal-700 text-white rounded-md font-bold text-[10px] transition-colors'><i class='fa-solid fa-table-list mr-1'></i>เปิดตารางเช็คยา</a>";
                            $htmlMsg .= '</div></li>';
                        }
                        $htmlMsg .= '</ul>' . $filterDebug;
                    }
                }
            }

            // =================================================================
            // 🩺 โหมด 6: รายงานการเจ็บป่วยประจำวัน (โควิด / ไข้หวัดใหญ่ / ลมร้อน / สุขภาพจิต / นอน รพ.)
            // =================================================================
            elseif ($targetTable === 'morbidity_reports' && class_exists(RecruitMorbidity::class)) {
                $query = RecruitMorbidity::with(['patient.militaryUnit', 'militaryUnit'])->latest('onset_date');
                $query->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters));

                if (!empty($filters['morbidity_category'])) {
                    $query->where('category', $filters['morbidity_category']);
                }
                if (!empty($filters['keyword'])) {
                    $kw = $filters['keyword'];
                    $query->where(function ($q) use ($kw) {
                        $q->where('diagnosis_text', 'LIKE', "%{$kw}%")->orWhere('sub_type', 'LIKE', "%{$kw}%");
                    });
                }

                $results = $query->take(50)->get();
                $filterDebug = "<div class='mt-4 pt-3 border-t border-rose-100 border-dashed text-[11px] text-rose-400 font-mono text-left'><i class='fa-solid fa-database mr-1'></i> AI ค้นหาจาก: <b class='text-rose-600'>รายงานอาการเจ็บป่วยประจำวัน (ครบ 4 หมวดรวมสุขภาพจิต)</b></div>";

                if ($results->isEmpty()) {
                    $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>ไม่พบรายการในทะเบียนรายงานการเจ็บป่วยประจำวันที่ตรงกับคำถามครับ</div>" . $filterDebug;
                } else {
                    $htmlMsg = "<div class='text-left mb-3'><span class='text-rose-600 font-bold text-lg'><i class='fa-solid fa-file-waveform'></i> พบข้อมูลในรายงานการเจ็บป่วย {$results->count()} รายการ (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2 max-h-[42vh] overflow-y-auto pr-2'>";
                    foreach ($results as $r) {
                        $p = $r->patient;
                        if (!$p) {
                            continue;
                        }
                        $uName = $r->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? $p->company ?? '-';
                        $statusBadge = $r->status === 'treating'
                            ? "<span class='bg-rose-100 text-rose-700 px-2 py-0.5 rounded text-xs font-bold border border-rose-200'>กำลังรักษา</span>"
                            : "<span class='bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold border border-emerald-200'>หายป่วยแล้ว</span>";
                        $nameLinkHtml = $renderSoldierProfileLink($p, $uName);

                        $htmlMsg .= "<li class='bg-rose-50/40 hover:bg-white p-3.5 rounded-xl border border-rose-100 transition-colors shadow-sm'>";
                        $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2'>{$nameLinkHtml}{$statusBadge}</div>";
                        $htmlMsg .= "<div class='text-xs text-slate-600 mt-1'><b>หมวด/ประเภท:</b> " . e($r->category . ' / ' . $r->sub_type) . " | <b>Dx.:</b> <span class='text-indigo-700 font-bold'>" . e($r->diagnosis_text ?: '-') . '</span></div>';
                        $htmlMsg .= '</li>';
                    }
                    $htmlMsg .= '</ul>' . $filterDebug;
                }
            }

            // =================================================================
            // 🟠 โหมด 7: คัดกรองทหารใหม่แรกรับ (Recruit Screenings)
            // =================================================================
            elseif ($targetTable === 'recruit_screenings' && class_exists(RecruitScreening::class)) {
                $query = RecruitScreening::with('patient.militaryUnit')->latest();
                $query->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters));

                if (!empty($filters['color_group'])) {
                    $query->where('color_group', $filters['color_group']);
                } elseif (str_contains($promptText, 'เหลือง') && str_contains($promptText, 'แดง')) {
                    $query->whereIn('color_group', ['yellow', 'red']);
                }
                if (!empty($filters['keyword'])) {
                    $query->where('symptoms_note', 'LIKE', '%' . $filters['keyword'] . '%');
                }

                $results = $query->take(50)->get();
                $filterDebug = "<div class='mt-4 pt-3 border-t border-orange-100 border-dashed text-[11px] text-orange-400 font-mono text-left'><i class='fa-solid fa-database mr-1'></i> AI ค้นหาจากตาราง: <b class='text-orange-500'>recruit_screenings</b></div>";

                if ($results->isEmpty()) {
                    $htmlMsg = "<div class='text-slate-600 font-medium bg-slate-50 p-5 rounded-2xl border border-slate-200 text-center shadow-sm'>จากข้อมูลคัดกรองพบ <span class='text-2xl font-black text-slate-800 mx-1'>0</span> นาย ที่ตรงตามคำถามครับ</div>" . $filterDebug;
                } else {
                    $htmlMsg = "<div class='text-left mb-3'><span class='text-orange-600 font-bold text-lg'><i class='fa-solid fa-clipboard-list'></i> พบข้อมูลคัดกรองทหารใหม่ {$results->count()} นาย (คลิกชื่อเพื่อดูโปรไฟล์):</span></div><ul class='text-left text-sm space-y-2 max-h-[40vh] overflow-y-auto pr-2'>";
                    foreach ($results as $item) {
                        $p = $item->patient;
                        if (!$p) {
                            continue;
                        }
                        $unitName     = $p->militaryUnit->short_name ?? $p->company ?? 'ไม่ระบุสังกัด';
                        $groupName    = $item->color_group === 'red' ? 'กลุ่มสีแดง (พบเชื้อ/เสี่ยงสูง)' : ($item->color_group === 'yellow' ? 'กลุ่มสีเหลือง (เฝ้าระวัง)' : 'กลุ่มสีเขียว (ปกติ)');
                        $batchTag     = "<span class='text-[11px] text-slate-400 font-semibold'>ผลัด " . e($item->intake_batch) . '</span>';
                        $nameLinkHtml = $renderSoldierProfileLink($p, $unitName, $batchTag);

                        $htmlMsg .= "<li class='bg-amber-50/40 hover:bg-white p-3 rounded-xl border border-amber-100 transition-colors shadow-sm'>";
                        $htmlMsg .= "<div class='mb-1'>{$nameLinkHtml}</div>";
                        $htmlMsg .= "<div class='text-slate-600 text-xs mb-1'>ผลคัดกรอง: <b>{$groupName}</b></div>";
                        if (!empty($item->symptoms_note)) {
                            $htmlMsg .= "<div class='text-xs text-slate-600 font-medium'>บันทึกคัดกรอง: " . e($item->symptoms_note) . '</div>';
                        }
                        $htmlMsg .= '</li>';
                    }
                    $htmlMsg .= '</ul>' . $filterDebug;
                }
            }

            // =================================================================
            // 🔵 โหมด 8: ข้อมูลกำลังพล (แสดง requested_info ครบทุกมิติ + เชื่อม PDPA)
            // =================================================================
            elseif ($targetTable === 'patients') {
                $query = Patient::with('militaryUnit');
                $applyUnitAndNameFilter($query, $filters);

                if (!empty($filters['duty_status'])) {
                    $query->where('duty_status', $filters['duty_status']);
                }
                if (($filters['hn_status'] ?? null) === 'unsynced') {
                    $query->where(function ($q) {
                        $q->whereNull('hn')->orWhere('hn', '')->orWhere('hn', '-')->orWhereNull('pttype_name')->orWhere('pttype_name', '');
                    });
                } elseif (($filters['hn_status'] ?? null) === 'synced') {
                    $query->whereNotNull('hn')->where('hn', '!=', '')->where('hn', '!=', '-');
                }
                if ($canViewChronic && !empty($filters['chronic_filter'])) {
                    $query->where('chronic_disease_status', $filters['chronic_filter']);
                } elseif ($canViewChronic && $reqInfo === 'chronic_disease' && empty($filters['name'])) {
                    $query->whereIn('chronic_disease_status', ['yes', 'other']);
                }
                if (!empty($filters['keyword'])) {
                    $kw = $filters['keyword'];
                    $query->where(function ($q) use ($kw, $canViewChronic) {
                        $q->where('pttype_name', 'LIKE', "%{$kw}%")
                          ->orWhere('scar_details', 'LIKE', "%{$kw}%")
                          ->orWhere('blood_group', 'LIKE', "%{$kw}%")
                          ->orWhere('duty_status_note', 'LIKE', "%{$kw}%")
                          ->orWhere('province', 'LIKE', "%{$kw}%")
                          ->orWhere('district', 'LIKE', "%{$kw}%");
                        if ($canViewChronic) {
                            $q->orWhere('chronic_disease_detail', 'LIKE', "%{$kw}%");
                        }
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
                        $hnBadge      = $p->hn ? "<span class='bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-bold'>HN: " . e($p->hn) . '</span>' : "<span class='bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs font-bold'>ยังไม่มี HN</span>";
                        $nameLinkHtml = $renderSoldierProfileLink($p, $unitName);

                        $htmlMsg .= "<li class='bg-blue-50/50 hover:bg-white p-4 rounded-xl border border-blue-100 transition-colors shadow-sm'>";
                        $htmlMsg .= "<div class='flex flex-wrap justify-between items-center gap-2 mb-1'>{$nameLinkHtml}{$hnBadge}</div>";
                        $htmlMsg .= "<div class='text-slate-500 text-xs'>ผลัดที่: <b>" . e($p->intake_batch ?: '-') . "</b> | สิทธิ: <b class='text-emerald-700'>" . e($p->pttype_name ?: 'รอซิงค์สิทธิ HosXP') . '</b></div>';

                        // แสดงรายละเอียดเฉพาะตาม requested_info ที่ผู้บริหารถามจริง
                        if ($reqInfo === 'blood_group') {
                            $htmlMsg .= "<div class='mt-1.5 text-xs text-slate-700'>🩸 <b>กรุ๊ปเลือด:</b> " . e($p->blood_group ?: 'ไม่ระบุ') . '</div>';
                        } elseif ($reqInfo === 'bmi_info') {
                            $htmlMsg .= "<div class='mt-1.5 text-xs text-slate-700'>⚖️ <b>ส่วนสูง/น้ำหนัก/BMI:</b> สูง " . e($p->height ?: '-') . ' ซม. | หนัก ' . e($p->weight ?: '-') . ' กก. | BMI: <b>' . e($p->bmi ?: '-') . '</b></div>';
                        } elseif ($reqInfo === 'scar_details') {
                            $htmlMsg .= "<div class='mt-1.5 text-xs text-slate-700'>📌 <b>ตำหนิ/แผลเป็น:</b> " . e($p->scar_details ?: 'ไม่ระบุ') . '</div>';
                        } elseif ($reqInfo === 'contact') {
                            $htmlMsg .= "<div class='mt-1.5 text-xs text-slate-700'>📞 <b>เบอร์โทร:</b> " . e($p->phone ?: '-') . ' | <b>ภูมิลำเนา:</b> อ.' . e($p->district ?: '-') . ' จ.' . e($p->province ?: '-') . '</div>';
                        } elseif ($reqInfo === 'parents') {
                            $htmlMsg .= "<div class='mt-1.5 text-xs text-slate-700'>👨‍👩‍👦 <b>บิดา:</b> " . e($p->father_name ?: '-') . ' | <b>มารดา:</b> ' . e($p->mother_name ?: '-') . '</div>';
                        } elseif ($reqInfo === 'education' || $reqInfo === 'religion') {
                            $htmlMsg .= "<div class='mt-1.5 text-xs text-slate-700'>🎓 <b>การศึกษา:</b> " . e($p->education ?: '-') . ' | <b>ศาสนา:</b> ' . e($p->religion ?: '-') . '</div>';
                        }

                        if (($p->duty_status ?? 'active') !== 'active') {
                            $htmlMsg .= "<div class='mt-1.5 text-xs font-bold text-orange-700'>สถานะกำลังพล: " . e($p->duty_status_label) . '</div>';
                        }
                        if ($canViewChronic && ($reqInfo === 'chronic_disease' || !empty($filters['chronic_filter']))) {
                            $htmlMsg .= "<div class='mt-2 pt-2 border-t border-blue-100 text-xs'><span class='font-bold text-rose-700'>โรคประจำตัว:</span> <b>" . e($p->chronic_disease_detail ?: 'ไม่มี') . '</b></div>';
                        }
                        $htmlMsg .= '</li>';
                    }
                    $htmlMsg .= '</ul>' . $filterDebug;
                }
            }

            // =================================================================
            // 🟢 โหมด 9: ประวัติการประเมิน MEWS & คิวตรวจ OPD / ER (รองรับ queue_type & admit+refer)
            // =================================================================
            else {
                $query = MewsAssessment::with('patient.militaryUnit')->latest();
                $query->whereHas('patient', fn ($q) => $applyUnitAndNameFilter($q, $filters));

                if (isset($filters['date_scope']) && $filters['date_scope'] !== 'all_time') {
                    if ($filters['date_scope'] === 'today') {
                        $query->whereDate('created_at', today());
                    } elseif ($filters['date_scope'] === 'yesterday') {
                        $query->whereDate('created_at', today()->subDay());
                    } elseif ($filters['date_scope'] === 'this_month') {
                        $query->whereYear('created_at', today()->year)->whereMonth('created_at', today()->month);
                    }
                }
                if (!empty($filters['min_temperature'])) {
                    $query->where('temperature', '>=', $filters['min_temperature']);
                }
                if (!empty($filters['min_mews_score'])) {
                    $query->where('total_score', '>=', $filters['min_mews_score']);
                }
                if (!empty($filters['risk_level'])) {
                    $query->where('risk_level', $filters['risk_level']);
                }
                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                // รองรับตัวกรองคิว ER vs OPD
                if (($filters['queue_type'] ?? null) === 'er') {
                    $query->where(function ($sub) {
                        $sub->where('assessment_type', 'er')
                            ->orWhereIn('risk_level', ['observe', 'emergency'])
                            ->orWhere(function ($symQ) {
                                $symQ->whereNotNull('symptoms')
                                     ->where('symptoms', '!=', '[]')
                                     ->where('symptoms', '!=', '')
                                     ->where('symptoms', '!=', 'null');
                            });
                    });
                } elseif (($filters['queue_type'] ?? null) === 'opd') {
                    $query->where('assessment_type', 'field')
                        ->where('risk_level', 'normal')
                        ->where(function ($symQ) {
                            $symQ->whereNull('symptoms')
                                 ->orWhere('symptoms', '[]')
                                 ->orWhere('symptoms', '')
                                 ->orWhere('symptoms', 'null');
                        });
                }

                if (!empty($filters['discharge_type'])) {
                    if ($filters['discharge_type'] === 'admitted_or_refer') {
                        $query->whereIn('discharge_type', ['admitted', 'refer']);
                    } else {
                        $query->where('discharge_type', $filters['discharge_type']);
                    }
                }
                if (!empty($filters['keyword'])) {
                    $kw = $filters['keyword'];
                    $query->where(function ($q) use ($kw) {
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
                        if (!$p) {
                            continue;
                        }
                        $unitName  = $p->militaryUnit->short_name ?? $p->company ?? 'ไม่ระบุสังกัด';
                        $rawSyms   = is_string($item->symptoms) ? json_decode($item->symptoms, true) : ($item->symptoms ?? []);
                        $isErRoute = ($item->assessment_type === 'er') || in_array($item->risk_level, ['observe', 'emergency'], true) || (!empty($rawSyms));
                        $mewsUrl   = route('backend.mews.show', $item->id);
                        $queueTag  = $item->status === 'pending'
                            ? ($isErRoute ? "<a href='{$mewsUrl}' class='bg-rose-100 hover:bg-rose-200 text-rose-700 px-2.5 py-0.5 rounded-lg text-[11px] font-bold transition-colors'>🚨 กำลังรักษา (ER)</a>" : "<a href='{$mewsUrl}' class='bg-teal-100 hover:bg-teal-200 text-teal-700 px-2.5 py-0.5 rounded-lg text-[11px] font-bold transition-colors'>🩺 รอตรวจ (OPD)</a>")
                            : "<a href='{$mewsUrl}' class='bg-slate-100 hover:bg-slate-200 text-slate-600 px-2.5 py-0.5 rounded-lg text-[11px] font-bold transition-colors'>ดูใบประเมิน MEWS</a>";

                        $hnSmallTag   = "<span class='text-xs text-slate-400 font-normal'>HN: " . e($p->hn ?: '-') . '</span>';
                        $nameLinkHtml = $renderSoldierProfileLink($p, $unitName, $hnSmallTag);

                        $htmlMsg .= "<li class='bg-emerald-50/50 hover:bg-white p-3.5 rounded-xl border border-emerald-100 transition-colors shadow-sm'>";
                        $htmlMsg .= "<div class='flex flex-wrap justify-between items-start gap-2 mb-1'>{$nameLinkHtml}{$queueTag}</div>";
                        $htmlMsg .= "<div class='text-slate-500 text-xs mb-1'>คะแนน MEWS: <b>" . e($item->total_score) . '</b> | Temp: <b>' . e($item->temperature ?? '-') . '°C</b> | BP: <b>' . e($item->systolic_bp ?? '-') . '</b></div>';
                        if (!empty($item->symptoms_note)) {
                            $htmlMsg .= "<div class='text-xs text-indigo-700 font-semibold'><i class='fa-solid fa-notes-medical mr-1'></i> โน้ตอาการ/หัตถการ: " . e($item->symptoms_note) . '</div>';
                        }
                        $htmlMsg .= '</li>';
                    }
                    $htmlMsg .= '</ul>' . $filterDebug;
                }
            }

            return response()->json(['status' => 'success', 'html' => $htmlMsg]);

        } catch (\Throwable $e) {
            Log::error('AI Executive Query Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'ไม่สามารถประมวลผลคำถามได้ในขณะนี้ กรุณาลองใหม่อีกครั้งครับ'], 500);
        }
    }

    // =========================================================================
    // 📋 4. AI SBAR Handover (สรุปส่งเวร / ส่งต่อ Refer มาตรฐาน SBAR 1 คลิก + Smart Fallback)
    // =========================================================================
    public function sbarHandover(Request $request, $mews_id)
    {
        $user = auth()->user();
        $mews = MewsAssessment::with([
            'patient.militaryUnit',
            'patient.recruitScreening',
            'patient.activeInfirmaryStay',
            'evaluator',
        ])->findOrFail($mews_id);

        $patient = $mews->patient;
        if (!$patient) {
            return response()->json(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ป่วยของใบประเมินนี้'], 404);
        }

        // 🛡️ ตรวจสิทธิ์หน่วยฝึกหากผู้ใช้เป็นเสนารักษ์
        if (!$this->canViewAllUnits($user) && (int) $patient->military_unit_id !== (int) $user->military_unit_id) {
            return response()->json(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงเคสของหน่วยทหารอื่นครับ'], 403);
        }

        // ใช้ Cache ผูกกับ updated_at เพื่อไม่ต้องเรียก Gemini ซ้ำเมื่อกดเปิดดูหลายครั้ง
        $cacheKey = 'sbar_handover_mews_' . $mews->id . '_' . ($mews->updated_at?->timestamp ?? 0);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json(['status' => 'success', 'data' => $cached]);
        }

        // ดึงแนวโน้มการประเมินทั้งหมดในแฟ้มการรักษา (case_id) เดียวกัน (Query เฉพาะฝั่ง KSVR)
        $caseHistory = MewsAssessment::where('case_id', $mews->case_id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('id', 'asc')
            ->get();

        $trendLines = $caseHistory->map(function ($row, $idx) {
            $t = $row->created_at ? $row->created_at->format('H:i') : '-';
            return 'ครั้งที่ ' . ($idx + 1) . " ({$t} น.): MEWS={$row->total_score} ({$row->risk_level}), BP={$row->systolic_bp}, HR={$row->heart_rate}, RR={$row->respiratory_rate}, Temp={$row->temperature}°C, O2={$row->o2sat}%, AVPU={$row->consciousness}";
        })->implode("\n");

        $symptomsMap = EmergencySymptom::pluck('name', 'code')->toArray();
        $rawSyms = is_string($mews->symptoms) ? json_decode($mews->symptoms, true) : ($mews->symptoms ?? []);
        $symNames = [];
        if (is_array($rawSyms)) {
            foreach ($rawSyms as $code) {
                $symNames[] = $symptomsMap[$code] ?? $code;
            }
        }
        $symptomText = !empty($symNames) ? implode(', ', $symNames) : 'ไม่มีอาการฉุกเฉินเฉพาะโรค';

        $chronicText = 'ไม่มีโรคประจำตัว';
        if (in_array($patient->chronic_disease_status, ['yes', 'other'], true)) {
            $chronicText = $patient->chronic_disease_detail ?: 'มีโรคประจำตัว';
        }

        $activeMedsText = 'ไม่มีรายการยาต่อเนื่อง';
        if (class_exists(PatientMedication::class) && Schema::hasTable('patient_medications')) {
            $medList = PatientMedication::where('patient_id', $patient->id)
                ->where('is_active', true)
                ->pluck('drug_name')
                ->toArray();
            if (!empty($medList)) {
                $activeMedsText = implode(', ', $medList);
            }
        }

        $fullName = trim(($patient->rank ?? '') . ($patient->first_name ?? '') . ' ' . ($patient->last_name ?? ''));
        $unitName = $patient->militaryUnit->short_name ?? $patient->militaryUnit->name ?? $patient->company ?? 'ไม่ระบุหน่วย';
        $ageText = '-';
        if (!empty($patient->dob)) {
            try {
                $ageText = Carbon::parse($patient->dob)->age . ' ปี';
            } catch (\Throwable $e) {
            }
        }

        $screeningNote = $patient->recruitScreening->symptoms_note ?? 'ปกติ/ไม่มีข้อมูลเสี่ยง';
        $symptomNote = trim((string) ($mews->symptoms_note ?? '')) ?: 'ไม่ได้ระบุ';

        $sbarSchema = [
            'type'       => 'OBJECT',
            'properties' => [
                'situation'      => ['type' => 'STRING'],
                'background'     => ['type' => 'STRING'],
                'assessment'     => ['type' => 'STRING'],
                'recommendation' => ['type' => 'STRING'],
            ],
            'required'   => ['situation', 'background', 'assessment', 'recommendation'],
        ];

        $prompt = "
            คุณคือแพทย์และพยาบาลเวชศาสตร์ฉุกเฉิน โรงพยาบาลค่ายกฤษณ์สีวะรา (KSVR Hospital)
            จงสรุปรายงานส่งเวรหรือส่งต่อผู้ป่วย (Refer) ตามมาตรฐานสากล SBAR เป็นภาษาไทยที่กระชับ ชัดเจน อ่านเข้าใจได้ใน 15 วินาที (ห้ามใช้ Markdown)

            ข้อมูลผู้ป่วย:
            - ชื่อ-สกุล: {$fullName} (อายุ {$ageText}) | HN: " . ($patient->hn ?: 'ยังไม่มี HN') . " | สิทธิ: " . ($patient->pttype_name ?: 'ไม่ระบุ') . "
            - สังกัด: {$unitName} | ผลัด: " . ($patient->intake_batch ?: '-') . "
            - โรคประจำตัว/แพ้ยา: {$chronicText}
            - รายการยา e-MAR ที่ใช้อยู่: {$activeMedsText}
            - ประวัติคัดกรองแรกรับ: {$screeningNote}
            - อาการฉุกเฉินที่พบ: {$symptomText}
            - อาการสำคัญ/บันทึกเพิ่มเติม: {$symptomNote}
            - สถานะปัจจุบัน: {$mews->status}" . ($mews->discharge_type ? " ({$mews->discharge_type}: {$mews->discharge_details})" : '') . "
            - แนวโน้มสัญญาณชีพในแฟ้มนี้ (VN: {$mews->case_id}):
            {$trendLines}
        ";

        $aiResult = null;
        if (!empty($this->getGeminiApiKey())) {
            try {
                $rawAi = $this->callGeminiJson($prompt, 0.2, 20, $sbarSchema);
                if (is_array($rawAi)) {
                    $normalized = array_change_key_case($rawAi, CASE_LOWER);
                    if (!empty($normalized['situation'])) {
                        $aiResult = [
                            'patient_name'   => $fullName,
                            'situation'      => trim((string) ($normalized['situation'] ?? '-')),
                            'background'     => trim((string) ($normalized['background'] ?? '-')),
                            'assessment'     => trim((string) ($normalized['assessment'] ?? '-')),
                            'recommendation' => trim((string) ($normalized['recommendation'] ?? '-')),
                            'is_fallback'    => false,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('AI SBAR Handover Exception (switching to fallback): ' . $e->getMessage());
            }
        }

        // 🛡️ Smart Clinical Fallback: หาก Gemini API สะดุด/Timeout ให้สรุป SBAR จากข้อมูลจริงในระบบทันที (งาน ER ไม่สะดุด)
        if ($aiResult === null) {
            $riskLabel = match ($mews->risk_level) {
                'emergency' => 'ฉุกเฉินสีแดง (EMERGENCY)',
                'observe'   => 'เฝ้าระวังสีเหลือง (OBSERVE)',
                default     => 'ปกติสีเขียว (NORMAL)',
            };

            $recAction = ($mews->risk_level === 'emergency' || !empty($symNames))
                ? 'ประสานห้องฉุกเฉิน (ER) เตรียมรับตัว ประเมินอาการซ้ำทุก 15 นาที และเฝ้าระวังสัญญาณชีพใกล้ชิด'
                : ($mews->risk_level === 'observe'
                    ? 'เฝ้าระวังอาการและวัดสัญญาณชีพซ้ำทุก 30 นาที หากคะแนน MEWS เพิ่มขึ้นให้รายงานแพทย์เวรทันที'
                    : 'ดูแลตามอาการและติดตามประเมินซ้ำตามแผนการรักษา (OPD/หน่วยฝึก)');

            $aiResult = [
                'patient_name'   => $fullName,
                'situation'      => "{$fullName} อายุ {$ageText} สังกัด {$unitName} (ผลัด " . ($patient->intake_batch ?: '-') . ") อาการสำคัญ: {$symptomNote} | อาการเฉพาะโรค: {$symptomText} [ระดับความเสี่ยง: {$riskLabel}]",
                'background'     => "โรคประจำตัว: {$chronicText} | ยาต่อเนื่อง (e-MAR): {$activeMedsText} | คัดกรองแรกรับ: {$screeningNote}",
                'assessment'     => "คะแนน MEWS ล่าสุด {$mews->total_score} คะแนน (ประเมินในแฟ้มนี้ทั้งหมด {$caseHistory->count()} ครั้ง) | V/S ล่าสุด: BP {$mews->systolic_bp} mmHg, HR {$mews->heart_rate}/min, RR {$mews->respiratory_rate}/min, Temp {$mews->temperature}°C, O2Sat {$mews->o2sat}%, AVPU: {$mews->consciousness}",
                'recommendation' => "{$recAction} (สรุปอัตโนมัติจากฐานข้อมูล KSVR)",
                'is_fallback'    => true,
            ];
        }

        $aiResult['copy_text'] = implode("\n", [
            '📋 [SBAR ส่งเวร/ส่งต่อ — KSVR Hospital]',
            "ผู้ป่วย: {$fullName} ({$unitName}) | HN: " . ($patient->hn ?: '-'),
            "S (Situation): {$aiResult['situation']}",
            "B (Background): {$aiResult['background']}",
            "A (Assessment): {$aiResult['assessment']}",
            "R (Recommendation): {$aiResult['recommendation']}",
            'เวลาสรุป: ' . now()->addYears(543)->format('d/m/Y H:i') . ' น.',
        ]);

        // เก็บลง Cache เฉพาะกรณีที่ AI สรุปสำเร็จ เพื่อให้กรณี Fallback สามารถกดลองเรียก AI ใหม่ได้ในครั้งถัดไป
        if (empty($aiResult['is_fallback'])) {
            Cache::put($cacheKey, $aiResult, 3600);
        }

        return response()->json(['status' => 'success', 'data' => $aiResult]);
    }
}