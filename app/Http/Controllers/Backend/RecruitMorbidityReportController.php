<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MewsAssessment;
use App\Models\MilitaryDischarge;
use App\Models\MilitaryUnit;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\RecruitInfirmaryStay;
use App\Models\RecruitMorbidity;
use App\Models\RecruitScreening;
use App\Services\AuditLog;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RecruitMorbidityReportController extends Controller
{
    private const CATEGORY_SCHEMA_CACHE_KEY = 'ksvr_morbidity_category_varchar_v1';
    private bool $hosxpTimeoutApplied = false;

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

    public function index(Request $request)
    {
        // 1. ดึงรายชื่อผลัดทั้งหมด
        $batches = Patient::whereNotNull('intake_batch')
            ->where('intake_batch', '!=', '')
            ->distinct()
            ->pluck('intake_batch')
            ->sortDesc();

        $configuredBatch = $this->getDefaultActiveBatch();
        $defaultBatch = $batches->contains($configuredBatch)
            ? $configuredBatch
            : ($batches->first() ?? $configuredBatch);

        $selectedBatch = $request->input('intake_batch', $defaultBatch);
        $rawReportDate = (string) $request->input('report_date', Carbon::today()->format('Y-m-d'));

        try {
            $dt = Carbon::parse($rawReportDate)->locale('th');
            $reportDate = $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            $dt = Carbon::today()->locale('th');
            $reportDate = $dt->format('Y-m-d');
        }

        // 🔄 1.1 ซิงค์และซ่อมข้อมูลจากตารางคัดกรอง, ห้องพยาบาลหน่วยฝึก, ยาจิตเวช (e-MAR) และ MEWS อัตโนมัติ
        $this->syncFromExistingSystemData($selectedBatch);

        // แปลงวันที่เป็นรูปแบบภาษาไทยสำหรับหัวสไลด์ (เช่น "วันที่ 16 พ.ย. 69")
        $thaiYearShort = substr((string) ($dt->year + 543), -2);
        $thaiDateHeader = "วันที่ {$dt->day} {$dt->translatedFormat('M')} {$thaiYearShort}";

        // 2. ดึงหน่วยฝึกและนับยอดทหารใหม่ (จัดสรร)
        $units = MilitaryUnit::where('is_active', true)->orderBy('id')->get();
        $allocatedQuery = Patient::query();
        if ($selectedBatch !== 'all') {
            $allocatedQuery->where('intake_batch', $selectedBatch);
        }
        $allocatedByUnit = $allocatedQuery->select('military_unit_id', DB::raw('COUNT(*) as total'))
            ->groupBy('military_unit_id')
            ->pluck('total', 'military_unit_id');

        // 3. ดึงข้อมูลการเจ็บป่วยของผลัดนี้ทั้งหมดที่เริ่มป่วยตั้งแต่วันแรกจนถึง $reportDate
        $recordsQuery = RecruitMorbidity::with(['patient.militaryUnit', 'militaryUnit'])
            ->whereDate('onset_date', '<=', $reportDate);

        if ($selectedBatch !== 'all') {
            $recordsQuery->where('intake_batch', $selectedBatch);
        }

        $records = $recordsQuery->orderBy('onset_date', 'asc')->get();

        $defaultUnitId = $units->first()->id ?? null;
        foreach ($records as $rec) {
            if (empty($rec->military_unit_id)) {
                $rec->military_unit_id = $rec->patient->military_unit_id ?? $defaultUnitId;
            }
        }

        // ฟังก์ชันเช็กสถานะ ณ วันที่เลือก ($reportDate)
        $isNewOnDate = fn ($r) => $r->onset_date && $r->onset_date->format('Y-m-d') === $reportDate;
        $isOldActiveOnDate = fn ($r) => $r->onset_date && $r->onset_date->format('Y-m-d') < $reportDate
            && ($r->status === 'treating' || ($r->recovered_date && $r->recovered_date->format('Y-m-d') > $reportDate));
        $isTreatingOnDate = fn ($r) => $r->status === 'treating' || ($r->recovered_date && $r->recovered_date->format('Y-m-d') > $reportDate);
        $isRecoveredByDate = fn ($r) => $r->status === 'recovered' && (!$r->recovered_date || $r->recovered_date->format('Y-m-d') <= $reportDate);

        // =====================================================================
        // 🟦 หมวดที่ 1: โรคระบบทางเดินหายใจ (โควิด-19 & ไข้หวัดใหญ่ — ล็อกนับไม่ซ้ำคน 100%)
        // =====================================================================
        $respRecords = $records->where('category', 'respiratory')
            ->unique(fn ($r) => $r->patient_id . '_' . $r->sub_type)
            ->values();

        $respSummary = [
            'covid_new'      => $respRecords->where('sub_type', 'covid')->filter($isNewOnDate)->count(),
            'flu_new'        => $respRecords->where('sub_type', 'flu')->filter($isNewOnDate)->count(),
            'covid_treating' => $respRecords->where('sub_type', 'covid')->filter($isTreatingOnDate)->count(),
            'flu_treating'   => $respRecords->where('sub_type', 'flu')->filter($isTreatingOnDate)->count(),
            'covid_cum'      => $respRecords->where('sub_type', 'covid')->count(),
            'flu_cum'        => $respRecords->where('sub_type', 'flu')->count(),
        ];

        $respTable = [];
        foreach ($units as $u) {
            $uRecs = $respRecords->where('military_unit_id', $u->id);
            $allocated = $allocatedByUnit[$u->id] ?? 0;

            $covidNew = $uRecs->where('sub_type', 'covid')->filter($isNewOnDate)->count();
            $covidOld = $uRecs->where('sub_type', 'covid')->filter($isOldActiveOnDate)->count();
            $covidCum = $uRecs->where('sub_type', 'covid')->count();

            $fluNew   = $uRecs->where('sub_type', 'flu')->filter($isNewOnDate)->count();
            $fluOld   = $uRecs->where('sub_type', 'flu')->filter($isOldActiveOnDate)->count();
            $fluCum   = $uRecs->where('sub_type', 'flu')->count();

            $totalOutbreakUnit = $covidCum + $fluCum;
            $outbreakPercent = $allocated > 0 ? round(($totalOutbreakUnit / $allocated) * 100, 1) : 0;

            $recoveredCount = $uRecs->filter($isRecoveredByDate)->count();
            $activeTreating = $uRecs->filter($isTreatingOnDate);

            $respTable[] = [
                'unit_name'        => $u->short_name ?? $u->name,
                'allocated'        => $allocated,
                'covid_new'        => $covidNew,
                'covid_old'        => $covidOld,
                'covid_cum'        => $covidCum,
                'flu_new'          => $fluNew,
                'flu_old'          => $fluOld,
                'flu_cum'          => $fluCum,
                'outbreak_percent' => $outbreakPercent,
                'recovered'        => $recoveredCount,
                'ci_count'         => $activeTreating->where('treatment_location', 'ci')->count(),
                'hosp_count'       => $activeTreating->where('treatment_location', 'hospital')->count(),
            ];
        }

        // =====================================================================
        // 🟧 หมวดที่ 2: การบาดเจ็บจากความร้อน (Heat Injury 9 ชนิด)
        // =====================================================================
        $heatRecords = $records->where('category', 'heat');
        $mildTypes   = ['heat_rash', 'heat_edema', 'heat_cramps', 'heat_tetany', 'heat_syncope', 'mild_rhabdo'];
        $severeTypes = ['severe_rhabdo', 'heat_exhaustion', 'heat_stroke'];

        $heatSummary = [
            'mild_new'        => $heatRecords->whereIn('sub_type', $mildTypes)->filter($isNewOnDate)->count(),
            'severe_new'      => $heatRecords->whereIn('sub_type', $severeTypes)->filter($isNewOnDate)->count(),
            'mild_treating'   => $heatRecords->whereIn('sub_type', $mildTypes)->filter($isTreatingOnDate)->count(),
            'severe_treating' => $heatRecords->whereIn('sub_type', $severeTypes)->filter($isTreatingOnDate)->count(),
            'mild_cum'        => $heatRecords->whereIn('sub_type', $mildTypes)->count(),
            'severe_cum'      => $heatRecords->whereIn('sub_type', $severeTypes)->count(),
        ];

        $heatTable = [];
        $allHeatSubTypes = array_merge($mildTypes, $severeTypes);
        foreach ($units as $u) {
            $uHeat = $heatRecords->where('military_unit_id', $u->id);
            $rowCounts = [];
            $rowTotal = 0;
            foreach ($allHeatSubTypes as $st) {
                $cnt = $uHeat->where('sub_type', $st)->count();
                $rowCounts[$st] = $cnt;
                $rowTotal += $cnt;
            }
            $heatTable[] = [
                'unit_name' => $u->short_name ?? $u->name,
                'counts'    => $rowCounts,
                'total'     => $rowTotal,
            ];
        }

        // =====================================================================
        // 🟪 หมวดที่ 3: เคสสุขภาพจิต / จิตเวช (Mental Health Surveillance)
        // =====================================================================
        $mentalRecords = $records->where('category', 'mental')
            ->unique('patient_id')
            ->values();

        $mentalSummary = [
            'new_today' => $mentalRecords->filter($isNewOnDate)->count(),
            'treating'  => $mentalRecords->filter($isTreatingOnDate)->count(),
            'unit_care' => $mentalRecords->filter($isTreatingOnDate)->where('treatment_location', '!=', 'hospital')->count(),
            'hosp_care' => $mentalRecords->filter($isTreatingOnDate)->where('treatment_location', 'hospital')->count(),
            'cum'       => $mentalRecords->count(),
            'recovered' => $mentalRecords->filter($isRecoveredByDate)->count(),
        ];

        $mentalTable = [];
        foreach ($units as $u) {
            $uMental = $mentalRecords->where('military_unit_id', $u->id);
            $activeMentalUnit = $uMental->filter($isTreatingOnDate);

            $mentalTable[] = [
                'unit_name' => $u->short_name ?? $u->name,
                'new'       => $uMental->filter($isNewOnDate)->count(),
                'old'       => $uMental->filter($isOldActiveOnDate)->count(),
                'unit_care' => $activeMentalUnit->where('treatment_location', '!=', 'hospital')->count(),
                'hosp_care' => $activeMentalUnit->where('treatment_location', 'hospital')->count(),
                'cum'       => $uMental->count(),
                'recovered' => $uMental->filter($isRecoveredByDate)->count(),
            ];
        }

        $activeMentalList = $mentalRecords->filter($isTreatingOnDate)->map(function ($r) {
            $p = $r->patient;
            $uShort = $r->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-';
            return [
                'id'        => $r->id,
                'full_name' => ($p->rank ?? 'พลฯ') . ' ' . ($p->first_name ?? '') . ' ' . ($p->last_name ?? ''),
                'unit_name' => $uShort,
                'dx'        => $r->diagnosis_text ?: 'ติดตามอาการทางสุขภาพจิต',
                'care_loc'  => $r->treatment_location === 'hospital' ? 'รักษาที่ รพ.' : 'ติดตาม/รับยาที่หน่วยฝึก',
                'is_hosp'   => $r->treatment_location === 'hospital',
            ];
        })->values();

        // =====================================================================
        // 🟨 หมวดที่ 4: การเจ็บป่วยอื่นๆ (นอน รพ.ค่ายฯ vs นอน รพ.สาธารณสุข)
        // =====================================================================
        $otherRecords = $records->where('category', 'other_ipd');

        $campHospTable   = [];
        $publicHospTable = [];

        foreach ($units as $u) {
            $uCamp = $otherRecords->where('military_unit_id', $u->id)->where('sub_type', 'camp_hospital');
            $campHospTable[] = [
                'unit_name' => $u->short_name ?? $u->name,
                'new'       => $uCamp->filter($isNewOnDate)->count(),
                'old'       => $uCamp->filter($isOldActiveOnDate)->count(),
                'cum'       => $uCamp->count(),
                'recovered' => $uCamp->filter($isRecoveredByDate)->count(),
            ];

            $uPub = $otherRecords->where('military_unit_id', $u->id)->where('sub_type', 'public_hospital');
            $publicHospTable[] = [
                'unit_name' => $u->short_name ?? $u->name,
                'new'       => $uPub->filter($isNewOnDate)->count(),
                'old'       => $uPub->filter($isOldActiveOnDate)->count(),
                'cum'       => $uPub->count(),
                'recovered' => $uPub->filter($isRecoveredByDate)->count(),
            ];
        }

        $activeIpdList = $otherRecords->filter($isTreatingOnDate)->map(function ($r) {
            $p = $r->patient;
            $admitDateTh = $r->onset_date->copy()->locale('th');
            $admitStr = "{$admitDateTh->day} {$admitDateTh->translatedFormat('M')} " . ($admitDateTh->year + 543);
            return [
                'id'         => $r->id,
                'full_name'  => ($p->rank ?? 'พลฯ') . ' ' . ($p->first_name ?? '') . ' ' . ($p->last_name ?? ''),
                'dx'         => $r->diagnosis_text ?: 'รอระบุ Dx.',
                'admit_date' => $admitStr,
                'hosp_type'  => $r->sub_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายฯ',
            ];
        });

        // =====================================================================
        // 🎖️ หมวดที่ 5: ตารางการนำปลดทหารใหม่ (แยก "ปลดทางกาย" กับ "ปลดทางสุขภาพจิต")
        // =====================================================================
        $dischargeTable = [];
        $dischargeList  = collect();
        $dischargeSummary = [
            'physical_total' => 0,
            'mental_total'   => 0,
            'grand_total'    => 0,
            'completed'      => 0,
            'in_progress'    => 0,
        ];

        if (class_exists(MilitaryDischarge::class) && Schema::hasTable('military_discharges')) {
            $dcQuery = MilitaryDischarge::with(['patient.militaryUnit', 'militaryUnit']);
            if ($selectedBatch !== 'all') {
                $dcQuery->where('intake_batch', $selectedBatch);
            }
            $allDischarges = $dcQuery->orderBy('id', 'asc')->get();

            foreach ($allDischarges as $dc) {
                $dc->resolved_unit_id = $dc->military_unit_id ?? $dc->patient->military_unit_id ?? $defaultUnitId;
                $dc->discharge_group  = $this->isPsychiatricDiagnosis($dc->diagnosis_text, $dc->patient_id) ? 'mental' : 'physical';
            }

            $dischargeSummary['physical_total'] = $allDischarges->where('discharge_group', 'physical')->count();
            $dischargeSummary['mental_total']   = $allDischarges->where('discharge_group', 'mental')->count();
            $dischargeSummary['grand_total']    = $allDischarges->count();
            $dischargeSummary['completed']      = $allDischarges->where('process_status', 'completed')->count();
            $dischargeSummary['in_progress']    = $allDischarges->where('process_status', '!=', 'completed')->count();

            foreach ($units as $u) {
                $uDc = $allDischarges->where('resolved_unit_id', $u->id);
                $physCount   = $uDc->where('discharge_group', 'physical')->count();
                $mentalCount = $uDc->where('discharge_group', 'mental')->count();

                $dischargeTable[] = [
                    'unit_name'      => $u->short_name ?? $u->name,
                    'physical_count' => $physCount,
                    'mental_count'   => $mentalCount,
                    'total_count'    => $physCount + $mentalCount,
                    'in_progress'    => $uDc->where('process_status', '!=', 'completed')->count(),
                    'completed'      => $uDc->where('process_status', 'completed')->count(),
                ];
            }

            $dischargeList = $allDischarges->map(function ($dc) {
                $p = $dc->patient;
                $uShort = $dc->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-';
                $cleanDx = str_replace(['[ปลดทางสุขภาพจิต]', '[ปลดทางกาย]'], '', (string) $dc->diagnosis_text);
                return [
                    'id'              => $dc->id,
                    'patient_id'      => $dc->patient_id,
                    'full_name'       => ($p->rank ?? 'พลฯ') . ' ' . ($p->first_name ?? '') . ' ' . ($p->last_name ?? ''),
                    'unit_name'       => $uShort,
                    'dx'              => trim($cleanDx) ?: 'ไม่ระบุ Dx.',
                    'discharge_group' => $dc->discharge_group,
                    'group_label'     => $dc->discharge_group === 'mental' ? 'ปลดทางสุขภาพจิต' : 'ปลดทางกาย',
                    'status_label'    => $dc->process_status === 'completed' ? 'ปลดแล้ว' : 'กำลังดำเนินการ',
                    'is_completed'    => $dc->process_status === 'completed',
                ];
            })->values();
        }

        // จัดกลุ่มรายการที่กำลังรักษาอยู่ตาม "รายบุคคล (patient_id)" สำหรับ Modal จัดการเคส
        $subTypeThaiDict = [
            'covid'           => '🟦 โควิด-19',
            'flu'             => '🟦 ไข้หวัดใหญ่',
            'heat_rash'       => '🟧 ความร้อน: ผื่นแดด',
            'heat_edema'      => '🟧 ความร้อน: บวมแดด',
            'heat_cramps'     => '🟧 ความร้อน: ตะคริวแดด',
            'heat_tetany'     => '🟧 ความร้อน: เกร็งแดด',
            'heat_syncope'    => '🟧 ความร้อน: ลมแดด',
            'mild_rhabdo'     => '🟧 ความร้อน: กล้ามเนื้อสลายไม่รุนแรง',
            'severe_rhabdo'   => '🟥 ความร้อนรุนแรง: กล้ามเนื้อสลายรุนแรง',
            'heat_exhaustion' => '🟥 ความร้อนรุนแรง: ฮีทเอ็กซอสชั่น',
            'heat_stroke'     => '🟥 ความร้อนรุนแรง: ฮีทสโตรก',
            'camp_hospital'   => '🏥 นอน รพ.ค่ายฯ',
            'public_hospital' => '🚑 นอน รพ.สาธารณสุข (ส่งต่อ)',
            'mental_opd'      => '🟪 สุขภาพจิต (ติดตาม/รับยาที่หน่วย)',
            'mental_ipd'      => '🟪 สุขภาพจิต (รักษาใน รพ.)',
            'adjustment'      => '🟪 สุขภาพจิต (ภาวะการปรับตัว/ซึมเศร้า)',
            'substance'       => '🟪 สุขภาพจิต (สารเสพติด/ถอนพิษยา)',
        ];

        $activeCasesGrouped = $records->filter($isTreatingOnDate)
            ->groupBy('patient_id')
            ->map(function ($group) use ($subTypeThaiDict) {
                $primary = $group->firstWhere('category', 'other_ipd')
                    ?? $group->firstWhere('category', 'mental')
                    ?? $group->first();
                $badges = $group->map(fn ($item) => $subTypeThaiDict[$item->sub_type] ?? $item->sub_type)->unique()->values()->all();

                return (object) [
                    'primary_id'     => $primary->id,
                    'patient_id'     => $primary->patient_id,
                    'patient'        => $primary->patient,
                    'militaryUnit'   => $primary->militaryUnit ?? $primary->patient->militaryUnit,
                    'onset_date'     => $primary->onset_date,
                    'diagnosis_text' => $primary->diagnosis_text,
                    'badges'         => $badges,
                ];
            })->values();

        // รายชื่อพลทหารสำหรับเลือกตอนเพิ่มเคสใหม่
        $patientsQuery = Patient::with('militaryUnit')->orderBy('first_name');
        if ($selectedBatch !== 'all') {
            $patientsQuery->where('intake_batch', $selectedBatch);
        }
        $batchPatients = $patientsQuery->get(['id', 'cid', 'hn', 'rank', 'first_name', 'last_name', 'military_unit_id', 'battalion', 'intake_batch']);

        $hosxpSyncPaused = $this->isHosxpSyncPaused();

        return view('backend.reports.daily_morbidity', compact(
            'batches', 'selectedBatch', 'reportDate', 'thaiDateHeader',
            'respSummary', 'respTable',
            'heatSummary', 'heatTable',
            'mentalSummary', 'mentalTable', 'activeMentalList',
            'campHospTable', 'publicHospTable', 'activeIpdList',
            'dischargeSummary', 'dischargeTable', 'dischargeList',
            'activeCasesGrouped', 'batchPatients', 'hosxpSyncPaused'
        ));
    }

    // =========================================================================
    // 🧠 ตรวจสอบว่าการวินิจฉัยโรค (Dx.) เป็นกลุ่ม "สุขภาพจิต / จิตเวช" หรือไม่
    // =========================================================================
    private function isPsychiatricDiagnosis(?string $dxText, ?int $patientId = null): bool
    {
        $text = strtolower(trim((string) $dxText));

        if (str_contains($text, '[ปลดทางกาย]')) {
            return false;
        }
        if (str_contains($text, '[ปลดทางสุขภาพจิต]')) {
            return true;
        }

        $psychKeywords = [
            'จิต', 'ซึมเศร้า', 'เครียด', 'ปรับตัว', 'สติปัญญา', 'สมาธิสั้น', 'ออทิสติก',
            'สารเสพติด', 'ถอนพิษ', 'ทำร้ายตัวเอง', 'ประสาทหลอน', 'หลงผิด', 'ไบโพลาร์',
            'อารมณ์สองขั้ว', 'วิตกกังวล', 'แพนิค', 'นอนไม่หลับเรื้อรัง',
            'psych', 'depress', 'schizo', 'bipolar', 'anxiety', 'adjustment',
            'mental', 'intellectual', 'autis', 'adhd', 'substance', 'sedative',
            'hypnotic', 'amphet', 'cannabis', 'alcohol', 'delusion', 'hallucin',
            'mania', 'manic', 'panic', 'ptsd', 'suicid', 'paranoi', 'retardation',
        ];

        foreach ($psychKeywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        // เช็กรหัส ICD-10 หมวด F00 - F99
        if (preg_match('/\bf[0-9]{2}(\.[0-9]+)?\b/i', $text)) {
            return true;
        }

        if ($patientId) {
            $hasMentalMorb = RecruitMorbidity::where('patient_id', $patientId)
                ->where('category', 'mental')
                ->exists();
            if ($hasMentalMorb) {
                return true;
            }

            if (class_exists(PatientMedication::class) && Schema::hasTable('patient_medications')) {
                $hasPsychMed = PatientMedication::where('patient_id', $patientId)
                    ->where('care_group', 'psychiatric')
                    ->exists();
                if ($hasPsychMed) {
                    return true;
                }
            }
        }

        return false;
    }

    // =========================================================================
    // 🧹 สกัดเฉพาะการวินิจฉัยโรค (Dx.) ภาษาอังกฤษล้วน
    // =========================================================================
    private function cleanEnglishDx(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }

        $text = preg_replace('/[ก-๙]+/u', '', $raw);
        $text = preg_replace('/\(\s*\)/', '', $text);
        $text = preg_replace('/^[\s,\/\-\.\:]+/', '', $text);
        $text = preg_replace('/[\s,\/\-\.\:]+$/', '', $text);
        $text = trim($text);

        while (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $depth = 0;
            $isOuterPair = true;
            $len = strlen($text);
            for ($i = 0; $i < $len - 1; $i++) {
                if ($text[$i] === '(') $depth++;
                elseif ($text[$i] === ')') $depth--;
                if ($depth === 0) {
                    $isOuterPair = false;
                    break;
                }
            }
            if ($isOuterPair) {
                $text = trim(substr($text, 1, -1));
                $text = preg_replace('/^[\s,\/\-\.\:]+/', '', $text);
                $text = preg_replace('/[\s,\/\-\.\:]+$/', '', $text);
            } else {
                break;
            }
        }

        $text = preg_replace('/^(dx\.?|diagnosis\:?)\s*/i', '', trim($text));
        $text = preg_replace('/^[\s,\/\-\.\:]+/', '', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return $text !== '' ? ucfirst($text) : '';
    }

    // =========================================================================
    // 🔄 Auto-Sync & Self-Healing กวาดและซ่อมข้อมูลทุกหมวดหมู่ (ไม่ยิง ALTER TABLE ซ้ำ)
    // =========================================================================
    private function syncFromExistingSystemData(string $selectedBatch): void
    {
        // ปรับคอลัมน์ category เป็น VARCHAR(50) เพียงครั้งเดียวแล้วจำลง Cache
        if (!Cache::get(self::CATEGORY_SCHEMA_CACHE_KEY)) {
            try {
                DB::statement('ALTER TABLE recruit_morbidities MODIFY COLUMN category VARCHAR(50) NOT NULL');
                Cache::forever(self::CATEGORY_SCHEMA_CACHE_KEY, true);
            } catch (\Throwable $e) {
            }
        }

        $defaultUnitId = MilitaryUnit::where('is_active', true)->orderBy('id')->value('id');
        $defaultActiveBatch = $this->getDefaultActiveBatch();

        // ---------------------------------------------------------------------
        // 🧹 สเต็ปที่ 0: ล้างแถวไข้หวัดใหญ่ (flu) ที่ถูกสร้างเกินมาจากระบบ Auto-Sync และยุบรวมแถวซ้ำ
        // ---------------------------------------------------------------------
        $respQuery = RecruitMorbidity::where('category', 'respiratory')
            ->orderByRaw("CASE WHEN sub_type = 'covid' THEN 0 ELSE 1 END")
            ->orderBy('onset_date', 'asc')
            ->orderBy('id', 'asc');

        if ($selectedBatch !== 'all') {
            $respQuery->where('intake_batch', $selectedBatch);
        }

        $allRespRows = $respQuery->get();

        foreach ($allRespRows as $rRow) {
            if ($rRow->sub_type === 'flu' && empty($rRow->recorded_by)) {
                $dxCheck = strtolower((string) $rRow->diagnosis_text);
                $isRealFluText = str_contains($dxCheck, 'ไข้หวัดใหญ่')
                    || str_contains($dxCheck, 'สายพันธุ์')
                    || str_contains($dxCheck, 'flu');

                $alreadyHasCovid = $allRespRows->where('patient_id', $rRow->patient_id)->where('sub_type', 'covid')->isNotEmpty();
                if (!$isRealFluText || $alreadyHasCovid) {
                    $rRow->delete();
                }
            }
        }

        $remainingRespGroups = RecruitMorbidity::where('category', 'respiratory')
            ->when($selectedBatch !== 'all', fn ($q) => $q->where('intake_batch', $selectedBatch))
            ->orderByRaw("CASE WHEN sub_type = 'covid' THEN 0 ELSE 1 END")
            ->orderBy('onset_date', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('patient_id');

        foreach ($remainingRespGroups as $patientId => $group) {
            if ($group->count() > 1) {
                $keeper = $group->first();
                $hasTreating = $group->contains(fn ($r) => $r->status === 'treating');
                if ($hasTreating && $keeper->status !== 'treating') {
                    $keeper->update([
                        'status'         => 'treating',
                        'recovered_date' => null,
                    ]);
                }
                $duplicateIds = $group->slice(1)->pluck('id')->toArray();
                RecruitMorbidity::whereIn('id', $duplicateIds)->delete();
            }
        }

        // ---------------------------------------------------------------------
        // 🛌 สเต็ปที่ 1: ซิงค์ข้อมูลจากตารางห้องพยาบาลหน่วยฝึก (RecruitInfirmaryStay)
        // ---------------------------------------------------------------------
        if (class_exists(RecruitInfirmaryStay::class) && Schema::hasTable('recruit_infirmary_stays')) {
            $infQuery = RecruitInfirmaryStay::with('patient')->orderBy('admitted_at', 'asc');
            if ($selectedBatch !== 'all') {
                $infQuery->where('intake_batch', $selectedBatch);
            }
            $infStays = $infQuery->get();

            foreach ($infStays as $stay) {
                if (!$stay->patient) {
                    continue;
                }

                $dxText  = (string) ($stay->diagnosis_text ?? '');
                $dxLower = strtolower($dxText);

                $isCovidStay = str_contains($dxText, 'โควิด')
                    || str_contains($dxText, 'โควิค')
                    || str_contains($dxLower, 'covid')
                    || str_contains($dxLower, 'co-vid');

                $isFluStay = !$isCovidStay && (
                    str_contains($dxText, 'ไข้หวัดใหญ่')
                    || str_contains($dxText, 'สายพันธุ์')
                    || preg_match('/\b(flu|influenza)\b/i', $dxLower)
                );

                $morbLocation = $stay->location_type === 'camp_hospital' ? 'hospital' : 'ci';
                $onsetDate    = $stay->admitted_at ? $stay->admitted_at->format('Y-m-d') : Carbon::today()->format('Y-m-d');
                $isStayingNow = ($stay->status === 'staying');

                $existingResp = RecruitMorbidity::where('patient_id', $stay->patient_id)
                    ->where('category', 'respiratory')
                    ->first();

                if ($existingResp) {
                    if ($isStayingNow) {
                        $existingResp->update([
                            'military_unit_id'   => $stay->military_unit_id ?? $stay->patient->military_unit_id ?? $existingResp->military_unit_id,
                            'treatment_location' => $morbLocation,
                            'status'             => 'treating',
                            'recovered_date'     => null,
                        ]);
                    } elseif ($stay->status === 'returned' && $existingResp->status !== 'recovered') {
                        $existingResp->update([
                            'status'         => 'recovered',
                            'recovered_date' => $stay->discharged_at ? $stay->discharged_at->format('Y-m-d') : Carbon::today()->format('Y-m-d'),
                        ]);
                    }
                } elseif ($isCovidStay || $isFluStay) {
                    $subType = $isCovidStay ? 'covid' : 'flu';
                    RecruitMorbidity::create([
                        'patient_id'         => $stay->patient_id,
                        'military_unit_id'   => $stay->military_unit_id ?? $stay->patient->military_unit_id ?? $defaultUnitId,
                        'intake_batch'       => $stay->intake_batch ?? ($stay->patient->intake_batch ?: $defaultActiveBatch),
                        'category'           => 'respiratory',
                        'sub_type'           => $subType,
                        'treatment_location' => $morbLocation,
                        'diagnosis_text'     => $dxText ?: ($subType === 'covid' ? 'COVID-19' : 'Influenza'),
                        'onset_date'         => $onsetDate,
                        'status'             => $isStayingNow ? 'treating' : 'recovered',
                        'recovered_date'     => $isStayingNow ? null : ($stay->discharged_at ? $stay->discharged_at->format('Y-m-d') : Carbon::today()->format('Y-m-d')),
                    ]);
                }
            }

            $wronglyRecoveredList = RecruitMorbidity::where('category', 'respiratory')
                ->where('status', 'recovered')
                ->where(function ($q) {
                    $q->whereNull('severity')->orWhere('severity', '!=', 'manual_recovered');
                })
                ->when($selectedBatch !== 'all', fn ($q) => $q->where('intake_batch', $selectedBatch))
                ->get();

            foreach ($wronglyRecoveredList as $wRow) {
                $hasReturnedInfirmary = RecruitInfirmaryStay::where('patient_id', $wRow->patient_id)
                    ->where('status', 'returned')
                    ->exists();

                if (!$hasReturnedInfirmary) {
                    $wRow->update([
                        'status'             => 'treating',
                        'recovered_date'     => null,
                        'treatment_location' => $wRow->treatment_location ?: 'ci',
                    ]);
                }
            }
        }

        // ---------------------------------------------------------------------
        // 📋 สเต็ปที่ 2: ซิงค์ข้อมูลโควิด-19 และไข้หวัดใหญ่ จากตาราง recruit_screenings
        // ---------------------------------------------------------------------
        $screeningsQuery = RecruitScreening::with('patient');
        if ($selectedBatch !== 'all') {
            $screeningsQuery->where('intake_batch', $selectedBatch);
        }
        $screenings = $screeningsQuery->get();

        foreach ($screenings as $sc) {
            if (!$sc->patient) {
                continue;
            }
            $note = (string) ($sc->symptoms_note ?? '');
            $noteLower = strtolower($note);

            $isCovid = str_contains($note, 'โควิด') || str_contains($note, 'โควิค') || str_contains($noteLower, 'covid');
            $isFlu   = !$isCovid && (str_contains($note, 'ไข้หวัดใหญ่') || str_contains($note, 'สายพันธุ์') || preg_match('/\b(flu|influenza)\b/i', $noteLower));

            if ($isCovid || $isFlu) {
                $subType = $isCovid ? 'covid' : 'flu';
                $onsetDate = $sc->created_at ? $sc->created_at->format('Y-m-d') : Carbon::today()->format('Y-m-d');

                $exists = RecruitMorbidity::where('patient_id', $sc->patient_id)
                    ->where('category', 'respiratory')
                    ->exists();

                if (!$exists) {
                    RecruitMorbidity::create([
                        'patient_id'         => $sc->patient_id,
                        'military_unit_id'   => $sc->military_unit_id ?? $sc->patient->military_unit_id ?? $defaultUnitId,
                        'intake_batch'       => $sc->intake_batch ?? ($sc->patient->intake_batch ?: $defaultActiveBatch),
                        'category'           => 'respiratory',
                        'sub_type'           => $subType,
                        'treatment_location' => 'ci',
                        'diagnosis_text'     => $note ?: ($subType === 'covid' ? 'COVID-19' : 'Influenza'),
                        'onset_date'         => $onsetDate,
                        'status'             => 'treating',
                    ]);
                }
            }
        }

        // ---------------------------------------------------------------------
        // 🩺 สเต็ปที่ 3: ซิงค์ข้อมูลความร้อน (heat) และการนอน รพ. (other_ipd) จากตาราง mews_assessments
        // ---------------------------------------------------------------------
        $mewsQuery = MewsAssessment::with('patient')
            ->where('status', '!=', 'cancelled');

        if ($selectedBatch !== 'all') {
            $mewsQuery->whereHas('patient', function ($q) use ($selectedBatch) {
                $q->where('intake_batch', $selectedBatch);
            });
        }

        $allMews = $mewsQuery->orderBy('id', 'asc')->get()->groupBy('case_id');

        $heatMapping = [
            'heat_confusion' => ['sub_type' => 'heat_stroke',     'severity' => 'severe', 'label' => 'Heat Stroke'],
            'heat_urine'     => ['sub_type' => 'severe_rhabdo',   'severity' => 'severe', 'label' => 'Rhabdomyolysis'],
            'heat_fatigue'   => ['sub_type' => 'heat_exhaustion', 'severity' => 'severe', 'label' => 'Heat Exhaustion'],
            'heat_pale'      => ['sub_type' => 'heat_syncope',    'severity' => 'mild',   'label' => 'Heat Syncope'],
            'heat_cramp'     => ['sub_type' => 'heat_cramps',     'severity' => 'mild',   'label' => 'Heat Cramps'],
            'heat_numbness'  => ['sub_type' => 'heat_tetany',     'severity' => 'mild',   'label' => 'Heat Tetany'],
        ];

        foreach ($allMews as $caseId => $caseGroup) {
            $firstMews  = $caseGroup->first();
            $latestMews = $caseGroup->last();
            $patient    = $latestMews->patient;
            if (!$patient) {
                continue;
            }

            $onsetDate = $firstMews->created_at ? $firstMews->created_at->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            $unitId    = $patient->military_unit_id ?? $defaultUnitId;
            $batch     = $patient->intake_batch ?: ($selectedBatch !== 'all' ? $selectedBatch : $defaultActiveBatch);

            $isCaseRecoveredInMews = ($latestMews->status === 'completed') && (
                $latestMews->discharge_type === 'recovered' || !empty($latestMews->returned_to_unit_at)
            );

            $resolvedRecoveredDate = null;
            if ($isCaseRecoveredInMews) {
                $resolvedRecoveredDate = !empty($latestMews->returned_to_unit_at)
                    ? Carbon::parse($latestMews->returned_to_unit_at)->format('Y-m-d')
                    : $latestMews->updated_at->format('Y-m-d');
            }

            $allSymCodes = [];
            $notesList   = [];
            foreach ($caseGroup as $m) {
                $sArr = is_string($m->symptoms) ? json_decode($m->symptoms, true) : ($m->symptoms ?? []);
                if (is_array($sArr)) {
                    $allSymCodes = array_unique(array_merge($allSymCodes, $sArr));
                }
                if (!empty(trim((string) $m->symptoms_note))) {
                    $notesList[] = trim((string) $m->symptoms_note);
                }
            }
            $combinedNote = !empty($notesList) ? end($notesList) : '';

            foreach ($heatMapping as $code => $hMeta) {
                if (in_array($code, $allSymCodes, true)) {
                    $existingHeat = RecruitMorbidity::where('patient_id', $patient->id)
                        ->where('category', 'heat')
                        ->where('sub_type', $hMeta['sub_type'])
                        ->whereDate('onset_date', $onsetDate)
                        ->first();

                    if (!$existingHeat) {
                        RecruitMorbidity::create([
                            'patient_id'       => $patient->id,
                            'military_unit_id' => $unitId,
                            'intake_batch'     => $batch,
                            'category'         => 'heat',
                            'sub_type'         => $hMeta['sub_type'],
                            'severity'         => $hMeta['severity'],
                            'diagnosis_text'   => $this->cleanEnglishDx($combinedNote) ?: $hMeta['label'],
                            'onset_date'       => $onsetDate,
                            'status'           => $isCaseRecoveredInMews ? 'recovered' : 'treating',
                            'recovered_date'   => $resolvedRecoveredDate,
                        ]);
                    } else {
                        $targetStatus = $isCaseRecoveredInMews ? 'recovered' : 'treating';
                        if ($existingHeat->status !== $targetStatus) {
                            $existingHeat->update([
                                'status'         => $targetStatus,
                                'recovered_date' => $isCaseRecoveredInMews ? ($existingHeat->recovered_date ?? $resolvedRecoveredDate) : null,
                            ]);
                        }
                    }
                    break;
                }
            }

            if (in_array($latestMews->discharge_type, ['admitted', 'refer'], true)) {
                $hospSubType = $latestMews->discharge_type === 'refer' ? 'public_hospital' : 'camp_hospital';

                $englishFromDischarge = $this->cleanEnglishDx($latestMews->discharge_details);
                $englishFromNote      = $this->cleanEnglishDx($combinedNote);
                $initialEnglishDx     = $englishFromDischarge ?: ($englishFromNote ?: 'Pending Dx.');

                $existingIpd = RecruitMorbidity::where('patient_id', $patient->id)
                    ->where('category', 'other_ipd')
                    ->whereDate('onset_date', $onsetDate)
                    ->first();

                $ipdStatus = !empty($latestMews->returned_to_unit_at) ? 'recovered' : 'treating';
                $ipdRecDate = !empty($latestMews->returned_to_unit_at)
                    ? Carbon::parse($latestMews->returned_to_unit_at)->format('Y-m-d')
                    : null;

                if (!$existingIpd) {
                    RecruitMorbidity::create([
                        'patient_id'       => $patient->id,
                        'military_unit_id' => $unitId,
                        'intake_batch'     => $batch,
                        'category'         => 'other_ipd',
                        'sub_type'         => $hospSubType,
                        'diagnosis_text'   => mb_substr($initialEnglishDx, 0, 250),
                        'onset_date'       => $onsetDate,
                        'status'           => $ipdStatus,
                        'recovered_date'   => $ipdRecDate,
                    ]);
                } else {
                    $isManuallyRecordedActive = !empty($existingIpd->recorded_by) && $existingIpd->status === 'treating' && $ipdStatus === 'recovered';

                    if (!$isManuallyRecordedActive) {
                        $updateIpdData = ['sub_type' => $hospSubType];

                        $cleanedCurrentDx = $this->cleanEnglishDx($existingIpd->diagnosis_text);
                        if ($cleanedCurrentDx !== '' && $cleanedCurrentDx !== $existingIpd->diagnosis_text) {
                            $updateIpdData['diagnosis_text'] = mb_substr($cleanedCurrentDx, 0, 250);
                        } elseif ($cleanedCurrentDx === '' && $initialEnglishDx !== 'Pending Dx.') {
                            $updateIpdData['diagnosis_text'] = mb_substr($initialEnglishDx, 0, 250);
                        }

                        if ($existingIpd->status !== $ipdStatus) {
                            $updateIpdData['status'] = $ipdStatus;
                            $updateIpdData['recovered_date'] = $ipdRecDate;
                        }
                        $existingIpd->update($updateIpdData);
                    }
                }
            }
        }

        // ---------------------------------------------------------------------
        // 🧠 สเต็ปที่ 4: ซิงค์ข้อมูล "เคสสุขภาพจิต (mental)" อัตโนมัติ
        // ---------------------------------------------------------------------
        $ipdRowsForMental = RecruitMorbidity::with('patient')
            ->where('category', 'other_ipd')
            ->when($selectedBatch !== 'all', fn ($q) => $q->where('intake_batch', $selectedBatch))
            ->get();

        foreach ($ipdRowsForMental as $ipdRow) {
            if (!$ipdRow->patient) {
                continue;
            }
            if ($this->isPsychiatricDiagnosis($ipdRow->diagnosis_text)) {
                $existingMental = RecruitMorbidity::where('patient_id', $ipdRow->patient_id)
                    ->where('category', 'mental')
                    ->first();

                $targetLoc = $ipdRow->status === 'treating' ? 'hospital' : 'ci';

                if (!$existingMental) {
                    RecruitMorbidity::create([
                        'patient_id'         => $ipdRow->patient_id,
                        'military_unit_id'   => $ipdRow->military_unit_id ?? $ipdRow->patient->military_unit_id ?? $defaultUnitId,
                        'intake_batch'       => $ipdRow->intake_batch ?: $defaultActiveBatch,
                        'category'           => 'mental',
                        'sub_type'           => 'mental_ipd',
                        'treatment_location' => $targetLoc,
                        'diagnosis_text'     => $ipdRow->diagnosis_text,
                        'onset_date'         => $ipdRow->onset_date->format('Y-m-d'),
                        'status'             => $ipdRow->status,
                        'recovered_date'     => $ipdRow->recovered_date ? $ipdRow->recovered_date->format('Y-m-d') : null,
                    ]);
                } else {
                    $existingMental->update([
                        'treatment_location' => $targetLoc,
                        'diagnosis_text'     => $ipdRow->diagnosis_text ?: $existingMental->diagnosis_text,
                    ]);
                }
            }
        }

        if (class_exists(PatientMedication::class) && Schema::hasTable('patient_medications')) {
            $psychMeds = PatientMedication::with('patient')
                ->where('care_group', 'psychiatric')
                ->where('is_active', true)
                ->get()
                ->groupBy('patient_id');

            foreach ($psychMeds as $ptId => $medGroup) {
                $firstMed = $medGroup->first();
                $pt = $firstMed->patient;
                if (!$pt) {
                    continue;
                }
                if ($selectedBatch !== 'all' && $pt->intake_batch !== $selectedBatch) {
                    continue;
                }

                $existingMental = RecruitMorbidity::where('patient_id', $pt->id)
                    ->where('category', 'mental')
                    ->first();

                if (!$existingMental) {
                    $drugNames = $medGroup->pluck('drug_name')->implode(', ');
                    $startDt = $firstMed->start_date ? Carbon::parse($firstMed->start_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

                    RecruitMorbidity::create([
                        'patient_id'         => $pt->id,
                        'military_unit_id'   => $pt->military_unit_id ?? $defaultUnitId,
                        'intake_batch'       => $pt->intake_batch ?: $defaultActiveBatch,
                        'category'           => 'mental',
                        'sub_type'           => 'mental_opd',
                        'treatment_location' => 'ci',
                        'diagnosis_text'     => "จิตเวชรับยาต่อเนื่อง ({$drugNames})",
                        'onset_date'         => $startDt,
                        'status'             => 'treating',
                    ]);
                }
            }
        }

        // 🧹 5. ทำความสะอาดรายการ other_ipd ทั้งหมดที่กำลังรักษาอยู่ ให้เหลือเฉพาะ Dx. ภาษาอังกฤษล้วน
        $activeIpdRecords = RecruitMorbidity::where('category', 'other_ipd')
            ->where('status', 'treating')
            ->get();

        foreach ($activeIpdRecords as $ipdRec) {
            $cleaned = $this->cleanEnglishDx($ipdRec->diagnosis_text);
            if ($cleaned !== '' && $cleaned !== $ipdRec->diagnosis_text) {
                $ipdRec->update(['diagnosis_text' => $cleaned]);
            }
        }

        // 🔄 6. ซิงค์ทำความสะอาดข้อมูล other_ipd ย้อนกลับ (Reconcile & Deduplicate)
        $allIpdRows = RecruitMorbidity::where('category', 'other_ipd')
            ->orderBy('id', 'asc')
            ->get();

        $seenActivePatientIds = [];
        foreach ($allIpdRows as $row) {
            $validAdmitMews = MewsAssessment::where('patient_id', $row->patient_id)
                ->where('status', 'completed')
                ->whereIn('discharge_type', ['admitted', 'refer'])
                ->orderBy('id', 'desc')
                ->first();

            if (!$validAdmitMews && empty($row->recorded_by)) {
                $row->delete();
                continue;
            }

            if ($row->status === 'treating') {
                if (in_array($row->patient_id, $seenActivePatientIds, true)) {
                    $row->delete();
                    continue;
                }
                $seenActivePatientIds[] = $row->patient_id;
            }
        }
    }

    // 🌟 บันทึกเคสอาการเจ็บป่วยใหม่ (รองรับ 4 หมวดเจ็บป่วย + หมวดที่ 5 ขึ้นทะเบียนนำปลดทหารด่วน)
    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id'         => 'required|exists:patients,id',
            'category'           => 'required|in:respiratory,heat,other_ipd,mental,discharge',
            'sub_type'           => 'required|string|max:50',
            'treatment_location' => 'nullable|in:ci,hospital',
            'diagnosis_text'     => 'required|string|max:255',
            'onset_date'         => 'required|date',
        ], [
            'patient_id.required'     => 'กรุณาค้นหาและคลิกเลือกรายชื่อพลทหารจากรายการ',
            'category.required'       => 'กรุณาเลือกหมวดหมู่การเจ็บป่วย',
            'sub_type.required'       => 'กรุณาเลือกชนิดย่อยหรืออาการ',
            'diagnosis_text.required' => 'กรุณาระบุการวินิจฉัยโรค (Dx.)',
            'onset_date.required'     => 'กรุณาระบุวันที่เริ่มป่วยหรือวันที่ Admit',
        ]);

        $patient = Patient::findOrFail($data['patient_id']);
        $defaultUnitId = MilitaryUnit::where('is_active', true)->orderBy('id')->value('id');
        $defaultActiveBatch = $this->getDefaultActiveBatch();

        // 🎖️ กรณีเลือกหมวดที่ 5: บันทึกขึ้นทะเบียนนำปลดทหาร (แยกปลดทางกาย / ปลดทางสุขภาพจิต)
        if ($data['category'] === 'discharge' && class_exists(MilitaryDischarge::class)) {
            $prefixTag = $data['sub_type'] === 'discharge_mental' ? '[ปลดทางสุขภาพจิต] ' : '[ปลดทางกาย] ';
            $cleanDxInput = trim(str_replace(['[ปลดทางสุขภาพจิต]', '[ปลดทางกาย]'], '', $data['diagnosis_text']));

            MilitaryDischarge::updateOrCreate(
                ['patient_id' => $patient->id],
                [
                    'military_unit_id' => $patient->military_unit_id ?? $defaultUnitId,
                    'intake_batch'     => $patient->intake_batch ?: $defaultActiveBatch,
                    'diagnosis_text'   => $prefixTag . $cleanDxInput,
                    'process_status'   => 'preparing',
                    'recorded_by'      => auth()->id(),
                ]
            );

            if ($data['sub_type'] === 'discharge_mental') {
                RecruitMorbidity::updateOrCreate(
                    ['patient_id' => $patient->id, 'category' => 'mental'],
                    [
                        'military_unit_id'   => $patient->military_unit_id ?? $defaultUnitId,
                        'intake_batch'       => $patient->intake_batch ?: $defaultActiveBatch,
                        'sub_type'           => 'mental_opd',
                        'treatment_location' => $data['treatment_location'] ?? 'ci',
                        'diagnosis_text'     => $cleanDxInput,
                        'onset_date'         => $data['onset_date'],
                        'status'             => 'treating',
                        'recorded_by'        => auth()->id(),
                    ]
                );
            }

            return back()->with('success', "ขึ้นทะเบียนนำปลดของ {$patient->rank}{$patient->first_name} เรียบร้อยแล้ว");
        }

        $severeHeatTypes = ['severe_rhabdo', 'heat_exhaustion', 'heat_stroke'];
        $severity = null;
        if ($data['category'] === 'heat') {
            $severity = in_array($data['sub_type'], $severeHeatTypes, true) ? 'severe' : 'mild';
        }

        $dxToSave = $data['diagnosis_text'];
        if ($data['category'] === 'other_ipd') {
            $cleanedDx = $this->cleanEnglishDx($dxToSave);
            if ($cleanedDx !== '') {
                $dxToSave = $cleanedDx;
            }
        }

        $resolvedLocation = null;
        if (in_array($data['category'], ['respiratory', 'mental'], true)) {
            $resolvedLocation = $data['treatment_location'] ?? ($data['sub_type'] === 'mental_ipd' ? 'hospital' : 'ci');
        }

        if (in_array($data['category'], ['respiratory', 'mental'], true)) {
            $existingQuery = RecruitMorbidity::where('patient_id', $patient->id)
                ->where('category', $data['category']);
            if ($data['category'] === 'respiratory') {
                $existingQuery->where('sub_type', $data['sub_type']);
            }
            $existingRow = $existingQuery->first();

            if ($existingRow) {
                $existingRow->update([
                    'military_unit_id'   => $patient->military_unit_id ?? $defaultUnitId,
                    'sub_type'           => $data['sub_type'],
                    'treatment_location' => $resolvedLocation,
                    'diagnosis_text'     => $dxToSave,
                    'onset_date'         => $data['onset_date'],
                    'status'             => 'treating',
                    'recovered_date'     => null,
                    'severity'           => null,
                    'recorded_by'        => auth()->id(),
                ]);
                return back()->with('success', "อัปเดตข้อมูลการเจ็บป่วยของ {$patient->rank}{$patient->first_name} เรียบร้อยแล้ว");
            }
        }

        RecruitMorbidity::create([
            'patient_id'         => $patient->id,
            'military_unit_id'   => $patient->military_unit_id ?? $defaultUnitId,
            'intake_batch'       => $patient->intake_batch ?: $defaultActiveBatch,
            'category'           => $data['category'],
            'sub_type'           => $data['sub_type'],
            'severity'           => $severity,
            'treatment_location' => $resolvedLocation,
            'diagnosis_text'     => $dxToSave,
            'onset_date'         => $data['onset_date'],
            'status'             => 'treating',
            'recorded_by'        => auth()->id(),
        ]);

        return back()->with('success', "บันทึกข้อมูลการเจ็บป่วยของ {$patient->rank}{$patient->first_name} เรียบร้อยแล้ว");
    }

    // 🌟 อัปเดตสถานะเป็น "หายป่วยแล้ว / กลับหน่วยฝึกแล้ว" หรือ "บันทึกการแก้ไข Dx."
    public function markRecovered(Request $request, $id)
    {
        $record = RecruitMorbidity::findOrFail($id);

        if ($request->has('update_dx_only')) {
            $rawInputDx = trim((string) $request->input('diagnosis_text'));
            $cleanDx = $record->category === 'other_ipd'
                ? ($this->cleanEnglishDx($rawInputDx) ?: $rawInputDx)
                : $rawInputDx;

            RecruitMorbidity::where('patient_id', $record->patient_id)
                ->where('status', 'treating')
                ->update([
                    'diagnosis_text' => $cleanDx,
                ]);

            if (class_exists(AuditLog::class)) {
                AuditLog::record('update_morbidity_dx', $record, [
                    'patient_id'     => $record->patient_id,
                    'diagnosis_text' => $cleanDx,
                ]);
            }

            return back()->with('success', 'อัปเดตข้อความการวินิจฉัยโรค (Dx.) เรียบร้อยแล้ว');
        }

        $recoveredDate = $request->input('recovered_date', Carbon::today()->format('Y-m-d'));
        $recoveredTimestamp = Carbon::parse($recoveredDate . ' ' . now()->format('H:i:s'));

        DB::transaction(function () use ($record, $recoveredDate, $recoveredTimestamp) {
            RecruitMorbidity::where('patient_id', $record->patient_id)
                ->where('status', 'treating')
                ->update([
                    'status'         => 'recovered',
                    'recovered_date' => $recoveredDate,
                    'severity'       => in_array($record->category, ['respiratory', 'mental'], true) ? 'manual_recovered' : $record->severity,
                ]);

            if (class_exists(RecruitInfirmaryStay::class) && Schema::hasTable('recruit_infirmary_stays')) {
                RecruitInfirmaryStay::where('patient_id', $record->patient_id)
                    ->where('status', 'staying')
                    ->update([
                        'status'         => 'returned',
                        'discharged_at'  => $recoveredTimestamp,
                        'discharge_note' => 'หายป่วยและจำหน่ายกลับโรงนอนปกติ (อัปเดตจากหน้ารายงานประจำวัน)',
                    ]);
            }

            MewsAssessment::where('patient_id', $record->patient_id)
                ->where('status', 'completed')
                ->whereIn('discharge_type', ['admitted', 'refer'])
                ->whereNull('returned_to_unit_at')
                ->update([
                    'returned_to_unit_at' => $recoveredTimestamp,
                    'return_note'         => 'อาการดีขึ้น หายป่วยและจำหน่ายกลับหน่วยฝึกแล้ว (อัปเดตจากหน้ารายงานประจำวัน)',
                ]);
        });

        if (class_exists(AuditLog::class)) {
            AuditLog::record('mark_morbidity_recovered', $record, [
                'patient_id'     => $record->patient_id,
                'category'       => $record->category,
                'recovered_date' => $recoveredDate,
            ]);
        }

        return back()->with('success', 'อัปเดตสถานะเป็น "หายป่วย / กลับหน่วยฝึกแล้ว" ซิงค์ครบทั้งหน้ารายงาน ห้องพยาบาลหน่วยฝึก และหน้า MEWS เรียบร้อยแล้ว!');
    }

    // 🌟 ลบรายการกรณีกรอกผิด
    public function destroy($id)
    {
        $record = RecruitMorbidity::findOrFail($id);
        $patientId = $record->patient_id;
        $category = $record->category;

        RecruitMorbidity::where('patient_id', $patientId)
            ->where('category', $category)
            ->delete();

        if (class_exists(AuditLog::class)) {
            AuditLog::record('delete_morbidity', null, [
                'patient_id' => $patientId,
                'category'   => $category,
            ]);
        }

        return back()->with('success', 'ลบรายการของผู้ป่วยรายนี้เรียบร้อยแล้ว');
    }

    // 🌟 ดึงการวินิจฉัยโรค (Dx.) ภาษาอังกฤษล่าสุดจาก HosXP (2-Step Index Lookup ป้องกัน Filesort บน HIS)
    public function fetchHosxpDiagnosis(Request $request)
    {
        // 🛡️ เช็กสวิตช์พักการซิงค์ HosXP ชั่วคราวก่อนยิง Query
        if ($this->isHosxpSyncPaused()) {
            return response()->json([
                'status'  => 'error',
                'paused'  => true,
                'message' => 'ขณะนี้ผู้ดูแลระบบพักการเชื่อมต่อฐานข้อมูล HosXP ชั่วคราว กรุณาลองใหม่ภายหลังครับ',
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $patient = Patient::findOrFail($request->get('patient_id'));
        if (empty($patient->hn)) {
            return response()->json(['status' => 'error', 'message' => 'พลทหารนายนี้ยังไม่ได้ซิงค์รหัส HN กับระบบ HosXP ครับ']);
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

            // ขั้นที่ 1: หา Visit (vn) ล่าสุดจากตาราง ovst ด้วย Index hn + vstdate (ไม่ Join ข้ามตารางตอน Sort)
            $latestVisit = DB::connection('hosxp')
                ->table('ovst')
                ->where('hn', trim((string) $patient->hn))
                ->orderBy('vstdate', 'desc')
                ->orderBy('vsttime', 'desc')
                ->first(['vn', 'vstdate']);

            if (!$latestVisit || empty($latestVisit->vn)) {
                return response()->json(['status' => 'not_found', 'message' => 'ยังไม่พบประวัติการมารับบริการ (Visit) ใน HosXP ครับ']);
            }

            // ขั้นที่ 2: ดึง Principal Diagnosis (diagtype น้อยสุด) ของ vn นั้นโดยตรงผ่าน Index vn
            $diag = DB::connection('hosxp')
                ->table('ovstdiag')
                ->leftJoin('icd101', 'ovstdiag.icd10', '=', 'icd101.code')
                ->where('ovstdiag.vn', $latestVisit->vn)
                ->orderBy('ovstdiag.diagtype', 'asc')
                ->select('ovstdiag.icd10', 'icd101.name as icd_name')
                ->first();

            if (!$diag || empty($diag->icd10)) {
                return response()->json(['status' => 'not_found', 'message' => 'ยังไม่พบรหัสการวินิจฉัยโรค (ICD-10) ใน Visit ล่าสุดของ HosXP ครับ']);
            }

            $icdEnglishName = $cleanUTF8($diag->icd_name);

            return response()->json([
                'status'     => 'success',
                'dx_text'    => $icdEnglishName ?: $cleanUTF8($diag->icd10),
                'visit_date' => $latestVisit->vstdate,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Log::warning('HosXP Diagnosis Fetch Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'ไม่สามารถดึง Dx. จาก HosXP ได้ในขณะนี้']);
        }
    }
}