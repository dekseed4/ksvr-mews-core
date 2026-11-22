@extends('layouts.backend')

@section('title', 'ระบบเอกสารนำปลดทหาร - KSVR Hospital')
@section('page_header', 'ระบบติดตามเอกสารนำปลดทหารกองประจำการ')

@section('content')
<div class="max-w-7xl mx-auto space-y-4 pb-12">

    @php
        $rawRoles = auth()->user()->roles ?? auth()->user()->role ?? [];
        $userRoles = is_string($rawRoles) ? json_decode($rawRoles, true) : (array) $rawRoles;
        $isAdmin = in_array('admin', $userRoles);

        $docColumns = [
            'medical_history'    => ['label' => 'ประวัติ<br>การรักษา', 'short' => 'ประวัติการรักษา'],
            'old_hospital_cert'  => ['label' => 'ใบ รพ.<br>(เก่า)', 'short' => 'ใบ รพ. (เก่า)'],
            'camp_hospital_cert' => ['label' => 'ใบ รพ.<br>(ค่าย)', 'short' => 'ใบ รพ. (ค่าย)'],
            'unit_memo'          => ['label' => 'บันทึก<br>ข้อความหน่วย', 'short' => 'บันทึกข้อความหน่วย'],
            'military_profile'   => ['label' => 'ประวัติ<br>ทหาร', 'short' => 'ประวัติทหาร'],
            'photos_6'           => ['label' => 'รูปถ่าย 1.5"<br>(6ใบ)', 'short' => 'รูปถ่าย 1.5" (6ใบ)'],
            'form_p25'           => ['label' => 'ใบนำส่ง<br>(พ.25)', 'short' => 'ใบนำส่ง (พ.25)'],
        ];

        $stageRows = [
            'unit_received' => 'หน่วย(รับเอกสาร)',
            'unit_sent'     => 'หน่วย(ส่งเอกสาร)',
            'med_sent_spn'  => 'เวชกรรม(ส่งเอกสาร ศปน.)',
        ];

        $todayThaiShort = now()->day . '/' . now()->month . '/' . substr((string)(now()->year + 543), -2);

        // ฟังก์ชันตรวจสอบกลุ่มโรคเพื่อแยก "ปลดทางสุขภาพจิต" vs "ปลดทางกาย" (ตรงกับหน้ารายงานประจำวัน 100%)
        $checkIsMentalDx = function (?string $dxText): bool {
            $t = strtolower(trim((string) $dxText));
            if (str_contains($t, '[ปลดทางกาย]')) return false;
            if (str_contains($t, '[ปลดทางสุขภาพจิต]')) return true;
            $keywords = [
                'จิต', 'ซึมเศร้า', 'เครียด', 'ปรับตัว', 'สติปัญญา', 'สมาธิสั้น', 'ออทิสติก',
                'สารเสพติด', 'ถอนพิษ', 'ทำร้ายตัวเอง', 'ประสาทหลอน', 'หลงผิด', 'ไบโพลาร์',
                'อารมณ์สองขั้ว', 'วิตกกังวล', 'แพนิค', 'นอนไม่หลับเรื้อรัง',
                'psych', 'depress', 'schizo', 'bipolar', 'anxiety', 'adjustment',
                'mental', 'intellectual', 'autis', 'adhd', 'substance', 'sedative',
                'hypnotic', 'amphet', 'cannabis', 'alcohol', 'delusion', 'hallucin',
                'mdd', 'ptsd', 'mania', 'manic', 'panic', 'suicid', 'paranoi', 'retardation'
            ];
            foreach ($keywords as $kw) {
                if (str_contains($t, $kw)) return true;
            }
            return (bool) preg_match('/\bf[0-9]{2}(\.[0-9]+)?\b/i', $t);
        };

        $mentalCountTotal   = 0;
        $physicalCountTotal = 0;
        foreach ($discharges as $dItem) {
            if ($checkIsMentalDx($dItem->diagnosis_text)) {
                $mentalCountTotal++;
            } else {
                $physicalCountTotal++;
            }
        }
    @endphp

    <!-- 🌟 1. โซน KPI สรุปความคืบหน้าการนำปลดทหาร + แยกปลดทางกาย / ปลดทางสุขภาพจิต -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 print:hidden">
        <a href="{{ route('backend.military_discharges.index', ['intake_batch' => $selectedBatch]) }}"
           onclick="setGroupFilterFromCard('all', event)"
           class="bg-white p-4 rounded-2xl shadow-sm border {{ empty($statusFilter) ? 'border-indigo-400 ring-2 ring-indigo-500/10' : 'border-slate-200 hover:border-indigo-300' }} flex items-center justify-between transition-all">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase">ยอดนำปลดรวม</p>
                <h3 class="text-2xl font-black text-slate-800 mt-0.5">{{ number_format($summary['total']) }} <span class="text-xs font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-folder-tree"></i></div>
        </a>

        <!-- การ์ดแยก: ปลดทางกาย -->
        <button type="button" onclick="setGroupFilterFromCard('physical')"
                class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 hover:border-teal-400 flex items-center justify-between text-left transition-all">
            <div>
                <p class="text-[11px] font-bold text-teal-600 uppercase">ปลดทางกาย</p>
                <h3 class="text-2xl font-black text-teal-700 mt-0.5">{{ number_format($physicalCountTotal) }} <span class="text-xs font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-stethoscope"></i></div>
        </button>

        <!-- การ์ดแยก: ปลดทางสุขภาพจิต -->
        <button type="button" onclick="setGroupFilterFromCard('mental')"
                class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 hover:border-purple-400 flex items-center justify-between text-left transition-all">
            <div>
                <p class="text-[11px] font-bold text-purple-600 uppercase">ปลดสุขภาพจิต</p>
                <h3 class="text-2xl font-black text-purple-700 mt-0.5">{{ number_format($mentalCountTotal) }} <span class="text-xs font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-brain"></i></div>
        </button>

        <a href="{{ route('backend.military_discharges.index', ['intake_batch' => $selectedBatch, 'status' => 'preparing']) }}"
           class="bg-white p-4 rounded-2xl shadow-sm border {{ $statusFilter === 'preparing' ? 'border-amber-400 ring-2 ring-amber-500/10' : 'border-slate-200 hover:border-amber-300' }} flex items-center justify-between transition-all">
            <div>
                <p class="text-[11px] font-bold text-amber-500 uppercase">1. เตรียมเอกสาร</p>
                <h3 class="text-2xl font-black text-amber-500 mt-0.5">{{ number_format($summary['preparing']) }} <span class="text-xs font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-file-signature"></i></div>
        </a>

        <a href="{{ route('backend.military_discharges.index', ['intake_batch' => $selectedBatch, 'status' => 'unit_sent']) }}"
           class="bg-white p-4 rounded-2xl shadow-sm border {{ $statusFilter === 'unit_sent' ? 'border-blue-400 ring-2 ring-blue-500/10' : 'border-slate-200 hover:border-blue-300' }} flex items-center justify-between transition-all">
            <div>
                <p class="text-[11px] font-bold text-blue-600 uppercase">2. หน่วยส่งแล้ว</p>
                <h3 class="text-2xl font-black text-blue-600 mt-0.5">{{ number_format($summary['unit_sent']) }} <span class="text-xs font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-paper-plane"></i></div>
        </a>

        <a href="{{ route('backend.military_discharges.index', ['intake_batch' => $selectedBatch, 'status' => 'completed']) }}"
           class="bg-white p-4 rounded-2xl shadow-sm border {{ $statusFilter === 'completed' ? 'border-emerald-400 ring-2 ring-emerald-500/10' : 'border-slate-200 hover:border-emerald-300' }} flex items-center justify-between transition-all">
            <div>
                <p class="text-[11px] font-bold text-emerald-600 uppercase">3. ส่ง ศปน. ครบ</p>
                <h3 class="text-2xl font-black text-emerald-600 mt-0.5">{{ number_format($summary['completed']) }} <span class="text-xs font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-circle-check"></i></div>
        </a>
    </div>

    <!-- 🌟 2. แถบตัวกรอง ค้นหาสด และปุ่มจัดการ -->
    <div class="bg-white p-3.5 md:p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3 print:hidden">
        
        <div class="flex flex-wrap items-center gap-2.5 flex-1">
            <form method="GET" action="{{ route('backend.military_discharges.index') }}" class="flex flex-wrap items-center gap-2">
                <select name="intake_batch" onchange="this.form.submit()" class="rounded-xl border-2 border-slate-300 bg-slate-50 text-xs sm:text-sm font-bold px-3 py-2 outline-none focus:border-indigo-500 cursor-pointer">
                    <option value="all" {{ $selectedBatch == 'all' ? 'selected' : '' }}>ทุกผลัด (รวมทั้งหมด)</option>
                    @foreach($batches as $b)
                        <option value="{{ $b }}" {{ $selectedBatch == $b ? 'selected' : '' }}>ผลัด {{ $b }}</option>
                    @endforeach
                </select>

                <select name="status" onchange="this.form.submit()" class="rounded-xl border-2 border-slate-300 bg-slate-50 text-xs sm:text-sm font-bold px-3 py-2 outline-none focus:border-indigo-500 cursor-pointer">
                    <option value="">ทุกสถานะเอกสาร</option>
                    <option value="preparing" {{ $statusFilter === 'preparing' ? 'selected' : '' }}>1. กำลังเตรียมเอกสาร</option>
                    <option value="unit_sent" {{ $statusFilter === 'unit_sent' ? 'selected' : '' }}>2. หน่วยส่งเอกสารแล้ว</option>
                    <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>3. เวชกรรมส่ง ศปน. ครบแล้ว</option>
                </select>
            </form>

            <!-- ตัวกรองประเภทการปลด (กาย / สุขภาพจิต) แบบ Instant -->
            <select id="filter_dc_group" onchange="filterDischargeCardsClient()" class="rounded-xl border-2 border-slate-300 bg-slate-50 text-xs sm:text-sm font-bold px-3 py-2 outline-none focus:border-indigo-500 cursor-pointer">
                <option value="all">ทุกประเภท (กาย + สุขภาพจิต)</option>
                <option value="physical">🩺 เฉพาะปลดทางกาย ({{ $physicalCountTotal }})</option>
                <option value="mental">🧠 เฉพาะปลดทางสุขภาพจิต ({{ $mentalCountTotal }})</option>
            </select>

            <!-- ช่องค้นหาด่วนบนหน้าจอ -->
            <div class="relative flex-1 min-w-[200px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="live_dc_search" oninput="filterDischargeCardsClient()"
                       placeholder="ค้นหาชื่อ, HN, เลข ปชช., สังกัด หรือชื่อโรค..."
                       class="w-full pl-9 pr-8 py-2 rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-indigo-500 text-xs sm:text-sm font-medium outline-none transition-all">
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <!-- ปุ่มกาง/พับใบเอกสารทั้งหมด -->
            <button type="button" id="btn_toggle_all_papers" onclick="toggleAllDischargePapers()"
                    class="px-3.5 py-2 bg-slate-100 hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 border border-slate-300 font-bold rounded-xl text-xs sm:text-sm flex items-center gap-1.5 transition-all">
                <i class="fa-solid fa-up-right-and-down-left-from-center text-xs" id="icon_toggle_all"></i>
                <span id="text_toggle_all">กางตารางทั้งหมด</span>
            </button>

            <button type="button" onclick="openAddDischargeModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs sm:text-sm shadow-sm flex items-center gap-1.5 transition-all">
                <i class="fa-solid fa-user-plus"></i> เพิ่มรายชื่อทหารนำปลด
            </button>

            @include('partials.export_report_menu', [
                'module' => 'discharges',
                'params' => request()->only(['intake_batch', 'unit_id', 'process_status', 'status']),
            ])
        </div>
    </div>

    <!-- 🌟 2.5 แถบแนะนำด่วน: พลทหารที่มีโรคประจำตัวจากหน้าคัดกรอง (เฉพาะ Admin — แสดงข้อความเต็มและพับเก็บได้) -->
    @if($isAdmin && $suggestedChronicPatients->count() > 0)
    <div class="bg-amber-50/85 border border-amber-200 rounded-2xl p-3.5 print:hidden">
        <div class="flex items-center justify-between gap-2 cursor-pointer select-none"
             onclick="document.getElementById('suggested_chronic_drawer').classList.toggle('hidden'); document.getElementById('suggested_chevron').classList.toggle('rotate-180');">
            <div class="text-xs font-black text-amber-800 flex items-center gap-1.5">
                <i class="fa-solid fa-lightbulb text-amber-500 text-sm"></i> 
                <span>รายชื่อพลทหารที่พบโรคประจำตัวจากการคัดกรอง ({{ $suggestedChronicPatients->count() }} นาย) — คลิกที่ชื่อเพื่อดึงเข้าทะเบียนนำปลดทันที:</span>
            </div>
            <span class="text-[11px] font-bold text-amber-700 hover:underline flex items-center gap-1 shrink-0">
                <span>พับ/กาง</span>
                <i id="suggested_chevron" class="fa-solid fa-chevron-up text-[10px] transition-transform duration-200"></i>
            </span>
        </div>

        <div id="suggested_chronic_drawer" class="mt-2.5 flex flex-wrap gap-2">
            @foreach($suggestedChronicPatients->take(10) as $sug)
                @php
                    $sugName = ($sug->rank ?? '') . ($sug->first_name ?? '') . ' ' . ($sug->last_name ?? '');
                    $sugUnit = $sug->militaryUnit->short_name ?? 'ไม่ระบุหน่วย';
                    $sugDx   = $sug->chronic_disease_detail ?: 'มีโรคประจำตัว';
                @endphp
                <button type="button" 
                        onclick="quickSelectSuggestedSoldier('{{ $sug->id }}', '{{ addslashes($sugName) }} ({{ addslashes($sugUnit) }} | HN: {{ $sug->hn ?: 'ไม่มี' }})', '{{ addslashes($sugDx) }}')"
                        class="px-3 py-1.5 bg-white hover:bg-amber-500 text-slate-700 hover:text-white border border-amber-300 rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-plus-circle text-amber-500"></i>
                    <span>{{ $sugName }}</span>
                    <span class="opacity-75">({{ $sugDx }})</span>
                </button>
            @endforeach
        </div>
    </div>
    @endif

    <!-- ===================================================================== -->
    <!-- 📄 3. รายการใบเอกสารนำปลดทหาร (แถบสรุปกระชับ + กดกางดูตารางมาตรฐาน 100%) -->
    <!-- ===================================================================== -->
    <div class="space-y-2.5 print:space-y-8" id="discharge_cards_container">
        @forelse($discharges as $index => $item)
            @php
                $pt = $item->patient;
                $matrix = is_array($item->documents_matrix) ? $item->documents_matrix : \App\Http\Controllers\Backend\MilitaryDischargeController::defaultMatrix();
                $unitName = $pt->militaryUnit->short_name ?? $pt->militaryUnit->name ?? $pt->battalion ?? '-';
                $fullSoldierName = ($pt->rank ?? 'พลฯ') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');

                $isMental = $checkIsMentalDx($item->diagnosis_text);
                $dcGroupKey = $isMental ? 'mental' : 'physical';
                $cleanDisplayDx = trim(str_replace(['[ปลดทางสุขภาพจิต]', '[ปลดทางกาย]'], '', (string) $item->diagnosis_text));

                // คำนวณความคืบหน้าเอกสารที่ลงวันที่แล้วในแต่ละขั้นตอน (เต็ม 7 รายการ)
                $countFilledDocs = function ($stageKey) use ($matrix, $docColumns) {
                    $cnt = 0;
                    foreach (array_keys($docColumns) as $colKey) {
                        $val = trim((string) ($matrix[$stageKey][$colKey] ?? ''));
                        if ($val !== '' && $val !== '-') {
                            $cnt++;
                        }
                    }
                    return $cnt;
                };

                $cntRec  = $countFilledDocs('unit_received');
                $cntSent = $countFilledDocs('unit_sent');
                $cntSpn  = $countFilledDocs('med_sent_spn');

                $statusBadge = $item->process_status === 'completed' 
                    ? ['text' => 'ส่ง ศปน. ครบแล้ว', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-300']
                    : ($item->process_status === 'unit_sent' 
                        ? ['text' => 'หน่วยส่งเอกสารแล้ว', 'class' => 'bg-blue-100 text-blue-800 border-blue-300'] 
                        : ['text' => 'กำลังเตรียมเอกสาร', 'class' => 'bg-amber-100 text-amber-800 border-amber-300']);

                $searchHaystack = strtolower($fullSoldierName . ' ' . ($pt->cid ?? '') . ' ' . ($pt->hn ?? '') . ' ' . $unitName . ' ' . $cleanDisplayDx);
            @endphp

            <div class="discharge-card-item bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden transition-all print:shadow-none print:border-none print:break-inside-avoid"
                 data-search="{{ $searchHaystack }}"
                 data-group="{{ $dcGroupKey }}">
                
                <!-- 🧭 แถบหัวการ์ดแบบกระชับ (คลิกเพื่อกางดูตาราง หรือกดปุ่มจัดการได้ทันที) -->
                <div class="bg-slate-800 text-white px-4 py-2.5 flex flex-col lg:flex-row lg:items-center justify-between gap-2.5 print:hidden">
                    
                    <!-- ข้อมูลหลักของทหาร (คลิกเพื่อกาง/พับตารางได้) -->
                    <div class="flex flex-wrap items-center gap-2 cursor-pointer flex-1 min-w-0" onclick="toggleSingleDischargePaper('{{ $item->id }}')" title="คลิกเพื่อเปิด/ปิดตารางเอกสารนำปลด">
                        <span class="bg-indigo-500 text-white text-xs font-black px-2.5 py-1 rounded-lg shrink-0">
                            ลำดับที่ {{ $index + 1 }}
                        </span>

                        <span class="font-bold text-sm sm:text-base text-white hover:text-teal-300 transition-colors">
                            {{ $fullSoldierName }}
                        </span>

                        <span class="px-2 py-0.5 rounded-md bg-slate-700 text-slate-200 text-xs font-bold border border-slate-600 shrink-0">
                            {{ $unitName }}
                        </span>

                        <span class="px-2 py-0.5 rounded-md bg-slate-900/70 text-slate-300 text-[11px] font-mono border border-slate-700 shrink-0">
                            HN: {{ $pt->hn ?: '-' }}
                        </span>

                        <!-- ป้ายแยกประเภทการนำปลด (กาย vs สุขภาพจิต) -->
                        @if($isMental)
                            <span class="px-2 py-0.5 rounded-md bg-purple-500/20 text-purple-200 border border-purple-400/30 text-[11px] font-bold shrink-0">
                                🧠 ปลดทางสุขภาพจิต
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-md bg-teal-500/20 text-teal-200 border border-teal-400/30 text-[11px] font-bold shrink-0">
                                🩺 ปลดทางกาย
                            </span>
                        @endif

                        <!-- โรควินิจฉัย -->
                        <span class="text-xs font-bold text-amber-300 truncate max-w-[260px]" title="โรควินิจฉัย: {{ $cleanDisplayDx }}">
                            Dx: {{ $cleanDisplayDx }}
                        </span>

                        <span class="text-[11px] px-2.5 py-0.5 rounded-full font-bold border {{ $statusBadge['class'] }} shrink-0">
                            {{ $statusBadge['text'] }}
                        </span>
                    </div>

                    <!-- ปุ่มเครื่องมือจัดการฝั่งขวา -->
                    <div class="flex flex-wrap items-center justify-between lg:justify-end gap-1.5 shrink-0">
                        
                        <!-- สรุปตัวเลขเอกสาร 7 ช่อง x 3 ขั้นตอน -->
                        <div class="hidden xl:flex items-center gap-1 text-[11px] font-bold bg-slate-900/70 px-2.5 py-1 rounded-xl border border-slate-700 mr-1" title="จำนวนเอกสารที่ลงวันที่แล้วในแต่ละขั้นตอน (เต็ม 7 รายการ)">
                            <span class="{{ $cntRec > 0 ? 'text-indigo-300' : 'text-slate-500' }}">รับ:{{ $cntRec }}/7</span>
                            <span class="text-slate-600">|</span>
                            <span class="{{ $cntSent > 0 ? 'text-sky-300' : 'text-slate-500' }}">ส่ง:{{ $cntSent }}/7</span>
                            <span class="text-slate-600">|</span>
                            <span class="{{ $cntSpn > 0 ? 'text-emerald-300' : 'text-slate-500' }}">ศปน:{{ $cntSpn }}/7</span>
                        </div>

                        <!-- ปุ่มกาง/พับดูตารางเอกสาร -->
                        <button type="button" onclick="toggleSingleDischargePaper('{{ $item->id }}')"
                                class="px-2.5 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-100 rounded-xl text-xs font-bold transition-colors flex items-center gap-1 border border-slate-600">
                            <i class="fa-solid fa-table text-teal-400"></i>
                            <span id="lbl_toggle_paper_{{ $item->id }}">ดูตาราง</span>
                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" id="chev_paper_{{ $item->id }}"></i>
                        </button>

                        <button type="button" onclick='openEditMatrixModal(@json($item), "{{ addslashes($fullSoldierName) }}", "{{ $dcGroupKey }}")' 
                                class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-pen-to-square"></i> บันทึก/อัปเดตวันส่งเอกสาร
                        </button>

                        <button type="button" id="btn-jpg-{{ $item->id }}" onclick="exportSingleDischargeImage('discharge-paper-{{ $item->id }}', '{{ $pt->first_name }}', '{{ $item->id }}')" 
                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-camera"></i> บันทึกภาพ (JPG)
                        </button>

                        <form action="{{ route('backend.military_discharges.destroy', $item->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบรายการนำปลดของพลทหารนายนี้?')" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-2.5 py-1.5 bg-rose-600/80 hover:bg-rose-600 text-white rounded-xl text-xs font-bold transition-colors" title="ลบรายการ">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 🖨️ พื้นที่กระดาษแบบฟอร์ม "เอกสารนำปลดทหาร" (ซ่อนไว้เริ่มต้นเพื่อลดการเลื่อนจอ แต่แสดงผลเมื่อคลิกดูตาราง, บันทึกภาพ JPG หรือสั่งพิมพ์ A4) -->
                <div id="paper-collapse-wrapper-{{ $item->id }}" class="discharge-paper-wrapper hidden print:block border-t border-slate-200">
                    <div id="discharge-paper-{{ $item->id }}" class="p-5 md:p-6 bg-white text-black">
                        
                        <div class="text-center font-bold text-base md:text-lg mb-2">
                            เอกสารนำปลดทหาร ผลัด {{ $item->intake_batch }}
                        </div>

                        <!-- ตารางที่ 1: ข้อมูลพลทหารและโรควินิจฉัย -->
                        <div class="overflow-x-auto mb-5">
                            <table class="w-full text-xs md:text-sm text-center border-collapse border border-black">
                                <thead>
                                    <tr class="font-bold bg-slate-50">
                                        <th class="border border-black p-2 w-14">ลำดับ</th>
                                        <th class="border border-black p-2">ชื่อ</th>
                                        <th class="border border-black p-2">สกุล</th>
                                        <th class="border border-black p-2">สังกัด</th>
                                        <th class="border border-black p-2">เลขประจำตัวประชาชน</th>
                                        <th class="border border-black p-2">HN</th>
                                        <th class="border border-black p-2">โรควินิจฉัย</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="font-semibold text-sm">
                                        <td class="border border-black p-2.5">{{ $index + 1 }}</td>
                                        <td class="border border-black p-2.5 text-left px-3">{{ $pt->rank ?? 'พลฯ' }} {{ $pt->first_name ?? '-' }}</td>
                                        <td class="border border-black p-2.5 text-left px-3">{{ $pt->last_name ?? '-' }}</td>
                                        <td class="border border-black p-2.5">{{ $unitName }}</td>
                                        <td class="border border-black p-2.5 font-mono">{{ $pt->cid ?? '-' }}</td>
                                        <td class="border border-black p-2.5 font-mono">{{ $pt->hn ?: '-' }}</td>
                                        <td class="border border-black p-2.5 font-bold text-indigo-950">{{ $cleanDisplayDx }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ตารางที่ 2: ตารางติดตามเอกสารที่เตรียม (7 รายการ x 3 ขั้นตอน + ผู้ประสานงาน) -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs md:text-sm text-center border-collapse border border-black">
                                <thead>
                                    <tr class="font-bold bg-slate-50">
                                        <th colspan="9" class="border border-black p-1.5 text-center">เอกสารที่เตรียม</th>
                                    </tr>
                                    <tr class="font-bold bg-white">
                                        <th class="border border-black p-2 w-44"></th>
                                        @foreach($docColumns as $colKey => $colMeta)
                                            <th class="border border-black p-1.5 leading-snug">{!! $colMeta['label'] !!}</th>
                                        @endforeach
                                        <th class="border border-black p-2 w-40">หมายเหตุ(ครบ)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stageRows as $stageKey => $stageLabel)
                                        @php
                                            $rowVals = $matrix[$stageKey] ?? [];
                                        @endphp
                                        <tr>
                                            <td class="border border-black p-2.5 text-left font-bold bg-slate-50/60">{{ $stageLabel }}</td>
                                            @foreach($docColumns as $colKey => $colMeta)
                                                <td class="border border-black p-2 font-medium">
                                                    {{ !empty($rowVals[$colKey]) ? $rowVals[$colKey] : '-' }}
                                                </td>
                                            @endforeach
                                            <td class="border border-black p-2 text-left font-semibold text-xs">
                                                {{ $rowVals['remark'] ?? '' }}
                                            </td>
                                        </tr>
                                    @endforeach

                                    <!-- แถวผู้ประสานงาน รพ. -->
                                    <tr>
                                        <td colspan="9" class="border border-black p-2 text-left font-bold bg-white">
                                            ผู้ประสานงาน {{ $item->hospital_coordinator ?: '................................................................................' }}
                                        </td>
                                    </tr>
                                    <!-- แถวผู้ประสานหน่วยฝึก -->
                                    <tr>
                                        <td colspan="9" class="border border-black p-2 text-left font-bold bg-white">
                                            ผู้ประสานหน่วยฝึก {{ $item->unit_coordinator ?: '................................................................................' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

            </div>
        @empty
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-sm">
                <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center text-2xl mx-auto mb-3">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h4 class="text-base font-bold text-slate-700">ยังไม่มีรายการเอกสารนำปลดทหารในผลัดนี้</h4>
                <p class="text-xs text-slate-500 mt-1">คลิกปุ่ม "+ เพิ่มรายชื่อทหารนำปลด" ด้านบน หรือคลิกเลือกจากแถบแนะนำผู้มีโรคประจำตัวได้เลยครับ</p>
            </div>
        @endforelse
    </div>

</div>

<!-- ========================================================================= -->
<!-- ➕ Modal เพิ่มรายชื่อทหารนำปลดใหม่ (ครบทั้งตรวจสอบรายช่อง + เลือกประเภทปลดกาย/จิต + ดึง Dx. HosXP) -->
<!-- ========================================================================= -->
<div id="addDischargeModal" class="{{ $errors->any() ? '' : 'hidden' }} fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 my-8">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
            <h3 class="text-lg font-black text-slate-800"><i class="fa-solid fa-file-medical text-emerald-500 mr-2"></i>ขึ้นทะเบียนเอกสารนำปลดทหาร</h3>
            <button type="button" onclick="closeAddDischargeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="{{ route('backend.military_discharges.store') }}" method="POST" novalidate onsubmit="return validateDischargeForm(event)" class="space-y-4">
            @csrf

            <!-- 1. ค้นหาและเลือกพลทหาร -->
            <div class="relative">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ค้นหาและเลือกพลทหาร <span class="text-rose-500">*</span></label>
                <input type="hidden" name="patient_id" id="add_patient_id" value="{{ old('patient_id') }}" required>

                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                    </span>
                    <input type="text" id="discharge_patient_search" autocomplete="off"
                           onfocus="document.getElementById('discharge_patient_list').classList.remove('hidden')"
                           oninput="filterDischargePatients(this.value); clearDcError('discharge_patient_search', 'err_dc_patient');"
                           placeholder="พิมพ์ชื่อ, นามสกุล, เลข ปชช., HN..."
                           class="w-full pl-10 pr-9 py-2.5 rounded-xl border-2 {{ $errors->has('patient_id') ? 'border-rose-500 bg-rose-50/40' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm font-medium text-slate-800 outline-none transition-all">
                    <button type="button" id="btn_clear_dc_patient" onclick="clearSelectedDischargePatient()" class="hidden absolute inset-y-0 right-0 pr-3 items-center text-slate-400 hover:text-rose-500">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <p id="err_dc_patient" class="{{ $errors->has('patient_id') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1.5 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>กรุณาพิมพ์ค้นหาแล้วคลิกเลือกรายชื่อพลทหารจากรายการครับ</span>
                </p>

                <div id="discharge_patient_list" class="hidden absolute left-0 right-0 mt-1 max-h-52 overflow-y-auto bg-white rounded-2xl shadow-xl border-2 border-slate-200 z-50 divide-y divide-slate-100">
                    @foreach($batchPatients as $pt)
                        @php
                            $uName = $pt->militaryUnit->short_name ?? 'ไม่ระบุหน่วย';
                            $fName = ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');
                            $sText = strtolower($fName . ' ' . ($pt->cid ?? '') . ' ' . ($pt->hn ?? '') . ' ' . $uName);
                            $autoChronic = ($isAdmin && in_array($pt->chronic_disease_status, ['yes', 'other'])) ? ($pt->chronic_disease_detail ?? '') : '';
                        @endphp
                        <div onclick="selectDischargePatient('{{ $pt->id }}', '{{ addslashes($fName) }} ({{ addslashes($uName) }} | HN: {{ $pt->hn ?: 'ไม่มี' }})', '{{ addslashes($autoChronic) }}')"
                             data-search="{{ $sText }}"
                             class="discharge-pt-option px-4 py-2.5 hover:bg-emerald-50 cursor-pointer flex justify-between items-center">
                            <div>
                                <div class="font-bold text-slate-800 text-sm">{{ $fName }}</div>
                                <div class="text-[11px] text-slate-500">ปชช: {{ $pt->cid }} | หน่วย: <b class="text-indigo-600">{{ $uName }}</b></div>
                            </div>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">HN: {{ $pt->hn ?: '-' }}</span>
                        </div>
                    @endforeach
                    <div id="discharge_no_result" class="hidden px-4 py-3 text-center text-xs text-slate-400 italic">
                        ไม่พบรายชื่อพลทหารที่ค้นหา
                    </div>
                </div>
            </div>

            <!-- 2. เลือกประเภทการนำปลด (ปลดทางกาย vs ปลดทางสุขภาพจิต — ซิงค์กับหน้ารายงานประจำวัน 100%) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ประเภทการนำปลด (สำหรับสรุปยอดรายงานประจำวัน) <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-2 gap-2.5">
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 transition-all">
                        <input type="radio" name="add_dc_group_radio" value="physical" checked class="text-teal-600 focus:ring-teal-500">
                        <span class="text-xs font-bold text-slate-800">🩺 ปลดทางกาย</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50/60 transition-all">
                        <input type="radio" name="add_dc_group_radio" value="mental" class="text-purple-600 focus:ring-purple-500">
                        <span class="text-xs font-bold text-slate-800">🧠 ปลดทางสุขภาพจิต</span>
                    </label>
                </div>
            </div>

            <!-- 3. โรควินิจฉัย -->
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-xs font-bold text-slate-700">โรควินิจฉัย (ที่ใช้ยื่นนำปลด) <span class="text-rose-500">*</span></label>
                    <button type="button" onclick="fetchHosxpDxForDischarge()" class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg border border-indigo-200">
                        <i class="fa-solid fa-cloud-arrow-down mr-1"></i> ดึง Dx. จาก HosXP
                    </button>
                </div>
                <input type="text" name="diagnosis_text" id="add_diagnosis_text" value="{{ old('diagnosis_text') }}" required
                       oninput="clearDcError('add_diagnosis_text', 'err_dc_dx'); autoDetectMentalRadio(this.value, 'add_dc_group_radio');"
                       placeholder="เช่น ธาลัสซีเมีย, โรคจิตเภท (Schizophrenia), โรคซึมเศร้า (MDD)..."
                       class="w-full rounded-xl border-2 {{ $errors->has('diagnosis_text') ? 'border-rose-500 bg-rose-50/40' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-semibold text-slate-800 outline-none transition-all">
                <p id="err_dc_dx" class="{{ $errors->has('diagnosis_text') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first('diagnosis_text') ?: 'กรุณาระบุโรควินิจฉัยที่ใช้ในการนำปลดทหาร (Dx.) ครับ' }}</span>
                </p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ผู้ประสานงาน รพ.ค่ายกฤษณ์สีวะรา</label>
                <input type="text" name="hospital_coordinator" value="{{ old('hospital_coordinator', 'ร.อ.หญิง จริยา ลินโพธิ์สาน โทร. 0887976378') }}"
                       class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-emerald-500 text-sm p-2.5 text-slate-800 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ผู้ประสานหน่วยฝึก (ยศ ชื่อ-สกุล โทรศัพท์)</label>
                <input type="text" name="unit_coordinator" value="{{ old('unit_coordinator') }}" placeholder="เช่น ส.ท. วัชรพงษ์ วิเศษสาคร โทร 0841080307"
                       class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-emerald-500 text-sm p-2.5 text-slate-800 outline-none">
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeAddDischargeModal()" class="px-5 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-sm font-bold">ยกเลิก</button>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-md">บันทึกขึ้นทะเบียน</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 📝 Modal อัปเดตตารางเอกสาร 7 ช่อง x 3 ขั้นตอน (Matrix Editor) -->
<!-- ========================================================================= -->
<div id="editMatrixModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-5xl w-full p-6 shadow-2xl border border-slate-200 my-8">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-lg font-black text-slate-800"><i class="fa-solid fa-table-list text-amber-500 mr-2"></i>อัปเดตตารางเอกสารที่เตรียม</h3>
                <p id="edit_soldier_name_label" class="text-xs font-bold text-indigo-600 mt-0.5"></p>
            </div>
            <button type="button" onclick="document.getElementById('editMatrixModal').classList.add('hidden')" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="editMatrixForm" method="POST" onsubmit="prepareEditDiagnosisWithTag()" class="space-y-4">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-5">
                    <label class="block text-xs font-bold text-slate-700 mb-1">โรควินิจฉัย <span class="text-rose-500">*</span></label>
                    <input type="text" name="diagnosis_text" id="edit_diagnosis_text" required
                           oninput="autoDetectMentalRadio(this.value, 'edit_dc_group_radio')"
                           class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white text-sm p-2.5 font-bold text-slate-800 outline-none">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 mb-1">ประเภทการนำปลด</label>
                    <div class="grid grid-cols-2 gap-1.5">
                        <label class="flex items-center justify-center gap-1 p-2.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 text-xs font-bold">
                            <input type="radio" name="edit_dc_group_radio" value="physical" class="text-teal-600">
                            <span>🩺 ทางกาย</span>
                        </label>
                        <label class="flex items-center justify-center gap-1 p-2.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50 text-xs font-bold">
                            <input type="radio" name="edit_dc_group_radio" value="mental" class="text-purple-600">
                            <span>🧠 สุขภาพจิต</span>
                        </label>
                    </div>
                </div>

                <div class="md:col-span-4">
                    <label class="block text-xs font-bold text-slate-700 mb-1">สถานะภาพรวมของเคสนี้</label>
                    <select name="process_status" id="edit_process_status"
                            class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white text-sm p-2.5 font-bold text-slate-800 outline-none">
                        <option value="preparing">1. กำลังเตรียมเอกสาร</option>
                        <option value="unit_sent">2. หน่วยส่งเอกสารให้ รพ. แล้ว</option>
                        <option value="completed">3. เวชกรรมส่งเอกสาร ศปน. ครบถ้วนแล้ว</option>
                    </select>
                </div>
            </div>

            <!-- ตารางกรอกวันที่ 7 คอลัมน์ x 3 แถว พร้อมปุ่มใส่วันที่วันนี้ด่วน -->
            <div class="overflow-x-auto border-2 border-slate-200 rounded-2xl p-3 bg-slate-50/60">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-bold text-slate-600"><i class="fa-regular fa-calendar-check text-emerald-600 mr-1"></i>ระบุวันที่ เช่น <b>{{ $todayThaiShort }}</b> หรือใส่ <b>-</b> หากไม่มีเอกสารนั้น</span>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" onclick="fillStageWithDate('unit_received', '{{ $todayThaiShort }}')" class="px-2.5 py-1 bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-[11px] font-bold shadow-sm">
                            ⚡ ลงวันนี้: หน่วย(รับเอกสาร)
                        </button>
                        <button type="button" onclick="fillStageWithDate('unit_sent', '{{ $todayThaiShort }}')" class="px-2.5 py-1 bg-white hover:bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-[11px] font-bold shadow-sm">
                            ⚡ ลงวันนี้: หน่วย(ส่งเอกสาร)
                        </button>
                        <button type="button" onclick="fillStageWithDate('med_sent_spn', '{{ $todayThaiShort }}')" class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[11px] font-bold shadow-sm">
                            ⚡ ลงวันนี้: เวชกรรม(ส่ง ศปน.)
                        </button>
                    </div>
                </div>

                <table class="w-full text-xs text-center border-collapse bg-white">
                    <thead>
                        <tr class="bg-slate-800 text-white font-bold">
                            <th class="p-2 border border-slate-600 w-40">ขั้นตอน</th>
                            @foreach($docColumns as $colKey => $colMeta)
                                <th class="p-2 border border-slate-600">{!! $colMeta['label'] !!}</th>
                            @endforeach
                            <th class="p-2 border border-slate-600 w-36">หมายเหตุ(ครบ)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stageRows as $stageKey => $stageLabel)
                            <tr>
                                <td class="p-2 border border-slate-300 font-bold text-left bg-slate-100 text-slate-800">{{ $stageLabel }}</td>
                                @foreach($docColumns as $colKey => $colMeta)
                                    <td class="p-1.5 border border-slate-300">
                                        <input type="text" name="matrix[{{ $stageKey }}][{{ $colKey }}]" id="mtx_{{ $stageKey }}_{{ $colKey }}"
                                               placeholder="ว/ด/ป"
                                               class="w-full text-center p-1.5 rounded-lg border border-slate-300 focus:border-indigo-500 font-semibold text-slate-800">
                                    </td>
                                @endforeach
                                <td class="p-1.5 border border-slate-300">
                                    <input type="text" name="matrix[{{ $stageKey }}][remark]" id="mtx_{{ $stageKey }}_remark"
                                           placeholder="เช่น ครบ 30/6/69"
                                           class="w-full p-1.5 rounded-lg border border-slate-300 focus:border-indigo-500 text-slate-800">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">ผู้ประสานงาน รพ.</label>
                    <input type="text" name="hospital_coordinator" id="edit_hospital_coordinator"
                           class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white text-sm p-2.5 text-slate-800 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">ผู้ประสานหน่วยฝึก</label>
                    <input type="text" name="unit_coordinator" id="edit_unit_coordinator"
                           class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white text-sm p-2.5 text-slate-800 outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('editMatrixModal').classList.add('hidden')" class="px-5 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-sm font-bold">ยกเลิก</button>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-md">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> บันทึกตารางเอกสาร
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/html2canvas-pro@1.5.8/dist/html2canvas-pro.min.js"></script>
<script>
    const docColKeys = ['medical_history', 'old_hospital_cert', 'camp_hospital_cert', 'unit_memo', 'military_profile', 'photos_6', 'form_p25'];
    const stageKeys  = ['unit_received', 'unit_sent', 'med_sent_spn'];
    const updateUrlTemplate = "{{ route('backend.military_discharges.update', '__ID__') }}";
    let allPapersExpanded = false;

    // =========================================================================
    // 🔽 1. ระบบกาง/พับดูใบตารางเอกสารรายบุคคล และกาง/พับทั้งหมด (Accordion)
    // =========================================================================
    function toggleSingleDischargePaper(itemId) {
        const wrapper = document.getElementById(`paper-collapse-wrapper-${itemId}`);
        const chev    = document.getElementById(`chev_paper_${itemId}`);
        const lbl     = document.getElementById(`lbl_toggle_paper_${itemId}`);
        if (!wrapper) return;

        const isHidden = wrapper.classList.contains('hidden');
        if (isHidden) {
            wrapper.classList.remove('hidden');
            if (chev) chev.classList.add('rotate-180');
            if (lbl) lbl.innerText = 'ซ่อนตาราง';
        } else {
            wrapper.classList.add('hidden');
            if (chev) chev.classList.remove('rotate-180');
            if (lbl) lbl.innerText = 'ดูตาราง';
        }
    }

    function toggleAllDischargePapers() {
        allPapersExpanded = !allPapersExpanded;
        const wrappers = document.querySelectorAll('.discharge-paper-wrapper');
        const txtEl    = document.getElementById('text_toggle_all');
        const iconEl   = document.getElementById('icon_toggle_all');

        wrappers.forEach(w => {
            const idPart = w.id.replace('paper-collapse-wrapper-', '');
            const chev   = document.getElementById(`chev_paper_${idPart}`);
            const lbl    = document.getElementById(`lbl_toggle_paper_${idPart}`);

            if (allPapersExpanded) {
                w.classList.remove('hidden');
                if (chev) chev.classList.add('rotate-180');
                if (lbl) lbl.innerText = 'ซ่อนตาราง';
            } else {
                w.classList.add('hidden');
                if (chev) chev.classList.remove('rotate-180');
                if (lbl) lbl.innerText = 'ดูตาราง';
            }
        });

        if (txtEl) txtEl.innerText = allPapersExpanded ? 'พับเก็บตารางทั้งหมด' : 'กางตารางทั้งหมด';
        if (iconEl) iconEl.className = allPapersExpanded ? 'fa-solid fa-down-left-and-up-right-to-center text-xs' : 'fa-solid fa-up-right-and-down-left-from-center text-xs';
    }

    // =========================================================================
    // 🔍 2. ระบบค้นหาด่วนและกรองประเภทการปลด (กาย / สุขภาพจิต) แบบ Real-time
    // =========================================================================
    function filterDischargeCardsClient() {
        const kw    = (document.getElementById('live_dc_search')?.value || '').trim().toLowerCase();
        const group = document.getElementById('filter_dc_group')?.value || 'all';

        document.querySelectorAll('.discharge-card-item').forEach(card => {
            const hay   = card.getAttribute('data-search') || '';
            const cGrp  = card.getAttribute('data-group') || 'physical';
            const matchKw  = (kw === '' || hay.includes(kw));
            const matchGrp = (group === 'all' || cGrp === group);

            card.classList.toggle('hidden', !(matchKw && matchGrp));
        });
    }

    function setGroupFilterFromCard(groupValue, e = null) {
        const selectEl = document.getElementById('filter_dc_group');
        if (selectEl && groupValue !== 'all') {
            if (e) e.preventDefault();
            selectEl.value = groupValue;
            filterDischargeCardsClient();
        }
    }

    // วิเคราะห์ชื่อโรคเพื่อสลับ Radio "ปลดทางกาย / ปลดทางสุขภาพจิต" อัตโนมัติขณะพิมพ์
    function autoDetectMentalRadio(dxText, radioName) {
        const t = (dxText || '').toLowerCase();
        const mentalWords = ['จิต', 'ซึมเศร้า', 'เครียด', 'ปรับตัว', 'สติปัญญา', 'สมาธิสั้น', 'ออทิสติก', 'สารเสพติด', 'ถอนพิษ', 'ประสาทหลอน', 'หลงผิด', 'ไบโพลาร์', 'วิตกกังวล', 'แพนิค', 'psych', 'depress', 'schizo', 'bipolar', 'anxiety', 'adjustment', 'mental', 'mdd', 'ptsd', 'sedative', 'hypnotic'];
        const isMental = mentalWords.some(w => t.includes(w)) || /\bf[0-9]{2}/i.test(t);
        const targetVal = isMental ? 'mental' : 'physical';
        const radio = document.querySelector(`input[name="${radioName}"][value="${targetVal}"]`);
        if (radio) radio.checked = true;
    }

    function openAddDischargeModal() {
        document.getElementById('addDischargeModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('discharge_patient_search')?.focus(), 100);
    }

    function closeAddDischargeModal() {
        document.getElementById('addDischargeModal').classList.add('hidden');
    }

    function filterDischargePatients(kw) {
        const list     = document.getElementById('discharge_patient_list');
        const noRes    = document.getElementById('discharge_no_result');
        const clearBtn = document.getElementById('btn_clear_dc_patient');

        if (list) list.classList.remove('hidden');
        document.getElementById('add_patient_id').value = '';

        const clean = kw.trim().toLowerCase();
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', clean.length === 0);
            clearBtn.classList.toggle('flex', clean.length > 0);
        }

        let matchCount = 0;
        document.querySelectorAll('.discharge-pt-option').forEach(el => {
            const hay = el.getAttribute('data-search') || '';
            const matched = (clean === '' || hay.includes(clean));
            el.classList.toggle('hidden', !matched);
            if (matched) matchCount++;
        });

        if (noRes) {
            noRes.classList.toggle('hidden', matchCount > 0);
        }
    }

    function clearSelectedDischargePatient() {
        document.getElementById('add_patient_id').value = '';
        const input = document.getElementById('discharge_patient_search');
        if (input) {
            input.value = '';
            input.classList.remove('border-emerald-500', 'bg-emerald-50/30', 'font-bold');
            input.focus();
        }
        filterDischargePatients('');
    }

    function selectDischargePatient(id, label, defaultDx = '') {
        document.getElementById('add_patient_id').value = id;
        const input = document.getElementById('discharge_patient_search');
        if (input) {
            input.value = label;
            clearDcError('discharge_patient_search', 'err_dc_patient');
            input.classList.add('border-emerald-500', 'bg-emerald-50/30', 'font-bold');
        }
        const clearBtn = document.getElementById('btn_clear_dc_patient');
        if (clearBtn) {
            clearBtn.classList.remove('hidden');
            clearBtn.classList.add('flex');
        }
        if (defaultDx && !document.getElementById('add_diagnosis_text').value) {
            document.getElementById('add_diagnosis_text').value = defaultDx;
            clearDcError('add_diagnosis_text', 'err_dc_dx');
            autoDetectMentalRadio(defaultDx, 'add_dc_group_radio');
        }
        document.getElementById('discharge_patient_list')?.classList.add('hidden');
    }

    function quickSelectSuggestedSoldier(id, label, dx) {
        openAddDischargeModal();
        selectDischargePatient(id, label, dx);
        document.getElementById('add_diagnosis_text').value = dx;
        clearDcError('add_diagnosis_text', 'err_dc_dx');
        autoDetectMentalRadio(dx, 'add_dc_group_radio');
    }

    function fetchHosxpDxForDischarge() {
        const pid = document.getElementById('add_patient_id').value;
        if (!pid) {
            Swal.fire({ icon: 'warning', title: 'กรุณาเลือกพลทหารก่อนดึง Dx. ครับ' });
            return;
        }
        Swal.fire({ title: 'กำลังดึง Dx. จาก HosXP...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch(`{{ route('backend.morbidity_reports.fetch_hosxp_dx') }}?patient_id=${pid}`)
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (data.status === 'success') {
                    document.getElementById('add_diagnosis_text').value = data.dx_text;
                    clearDcError('add_diagnosis_text', 'err_dc_dx');
                    autoDetectMentalRadio(data.dx_text, 'add_dc_group_radio');
                } else {
                    Swal.fire({ icon: 'info', title: 'แจ้งเตือน', text: data.message });
                }
            });
    }

    function openEditMatrixModal(itemData, soldierName, dcGroup = 'physical') {
        const form = document.getElementById('editMatrixForm');
        form.action = updateUrlTemplate.replace('__ID__', itemData.id);

        const rawDx = itemData.diagnosis_text || '';
        const cleanDx = rawDx.replace('[ปลดทางสุขภาพจิต]', '').replace('[ปลดทางกาย]', '').trim();

        document.getElementById('edit_soldier_name_label').innerText = soldierName + ' (ผลัด ' + itemData.intake_batch + ')';
        document.getElementById('edit_diagnosis_text').value = cleanDx;
        document.getElementById('edit_process_status').value = itemData.process_status || 'preparing';
        document.getElementById('edit_hospital_coordinator').value = itemData.hospital_coordinator || '';
        document.getElementById('edit_unit_coordinator').value = itemData.unit_coordinator || '';

        const targetRadio = document.querySelector(`input[name="edit_dc_group_radio"][value="${dcGroup}"]`);
        if (targetRadio) targetRadio.checked = true;

        const matrix = itemData.documents_matrix || {};
        stageKeys.forEach(st => {
            const row = matrix[st] || {};
            docColKeys.forEach(col => {
                const el = document.getElementById(`mtx_${st}_${col}`);
                if (el) el.value = row[col] ?? (col === 'old_hospital_cert' ? '-' : '');
            });
            const remEl = document.getElementById(`mtx_${st}_remark`);
            if (remEl) remEl.value = row.remark || '';
        });

        document.getElementById('editMatrixModal').classList.remove('hidden');
    }

    function prepareEditDiagnosisWithTag() {
        const dxInput = document.getElementById('edit_diagnosis_text');
        const selectedGroup = document.querySelector('input[name="edit_dc_group_radio"]:checked')?.value || 'physical';
        if (dxInput && dxInput.value.trim()) {
            const clean = dxInput.value.replace('[ปลดทางสุขภาพจิต]', '').replace('[ปลดทางกาย]', '').trim();
            const prefix = selectedGroup === 'mental' ? '[ปลดทางสุขภาพจิต] ' : '[ปลดทางกาย] ';
            dxInput.value = prefix + clean;
        }
    }

    function fillStageWithDate(stageKey, dateStr) {
        docColKeys.forEach(col => {
            const el = document.getElementById(`mtx_${stageKey}_${col}`);
            if (el && col !== 'old_hospital_cert' && (!el.value || el.value === '-')) {
                el.value = dateStr;
            }
        });
    }

    function markDcError(inputId, errId) {
        const el  = document.getElementById(inputId);
        const err = document.getElementById(errId);
        if (el) {
            el.classList.remove('border-slate-300', 'bg-slate-50', 'border-emerald-500', 'bg-emerald-50/30');
            el.classList.add('border-rose-500', 'bg-rose-50/40', 'ring-2', 'ring-rose-500/20');
        }
        if (err) err.classList.remove('hidden');
    }

    function clearDcError(inputId, errId) {
        const el  = document.getElementById(inputId);
        const err = document.getElementById(errId);
        if (el) {
            el.classList.remove('border-rose-500', 'bg-rose-50/40', 'ring-2', 'ring-rose-500/20');
            el.classList.add('border-slate-300', 'bg-slate-50');
        }
        if (err) err.classList.add('hidden');
    }

    function validateDischargeForm(e) {
        let missing = [];
        let firstEl = null;

        const pidInput  = document.getElementById('add_patient_id');
        const searchBox = document.getElementById('discharge_patient_search');
        const dxInput   = document.getElementById('add_diagnosis_text');

        if (!pidInput || !pidInput.value.trim()) {
            markDcError('discharge_patient_search', 'err_dc_patient');
            missing.push('รายชื่อพลทหารที่ต้องการขึ้นทะเบียนนำปลด');
            if (!firstEl) firstEl = searchBox;
        }

        if (!dxInput || !dxInput.value.trim()) {
            markDcError('add_diagnosis_text', 'err_dc_dx');
            missing.push('โรควินิจฉัยที่ใช้ในการนำปลดทหาร (Dx.)');
            if (!firstEl) firstEl = dxInput;
        }

        if (missing.length > 0) {
            if (e) e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'กรอกข้อมูลยังไม่ครบถ้วน!',
                html: `<div class="text-left text-xs sm:text-sm text-slate-600 space-y-2 mt-2"><p>กรุณากรอกข้อมูลในช่องที่มีกรอบสีแดงต่อไปนี้ให้ครบก่อนกดบันทึกครับ:</p><ul class="list-disc list-inside bg-rose-50 p-3.5 rounded-2xl border border-rose-200 space-y-1 text-rose-700 font-bold">${missing.map(m => `<li>${m}</li>`).join('')}</ul></div>`,
                confirmButtonText: 'กลับไปกรอกข้อมูล',
                confirmButtonColor: '#e11d48',
                customClass: { popup: 'rounded-3xl p-6' }
            }).then(() => {
                if (firstEl) setTimeout(() => firstEl.focus(), 150);
            });
            return false;
        }

        // แนบป้ายประเภทการนำปลด [ปลดทางกาย] หรือ [ปลดทางสุขภาพจิต] เพื่อซิงค์กับหน้ารายงานประจำวัน
        const selectedGroup = document.querySelector('input[name="add_dc_group_radio"]:checked')?.value || 'physical';
        const cleanDx = dxInput.value.replace('[ปลดทางสุขภาพจิต]', '').replace('[ปลดทางกาย]', '').trim();
        dxInput.value = (selectedGroup === 'mental' ? '[ปลดทางสุขภาพจิต] ' : '[ปลดทางกาย] ') + cleanDx;

        return true;
    }

    // 🎨 แปลงค่าสี oklch(...) เป็น rgb(...) ป้องกัน Error ตอนแคปภาพ JPG
    function sanitizeOklchColorsInClone(clonedDoc, rootId) {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        const ctx = canvas.getContext('2d');

        const toRgb = (val) => {
            if (!val || (!val.includes('oklch') && !val.includes('oklab') && !val.includes('color-mix'))) return val;
            try {
                ctx.fillStyle = '#000000';
                ctx.fillStyle = val;
                return ctx.fillStyle;
            } catch (e) {
                return '#1e293b';
            }
        };

        const colorProps = ['color', 'backgroundColor', 'borderColor', 'borderTopColor', 'borderRightColor', 'borderBottomColor', 'borderLeftColor', 'outlineColor'];
        const allElements = clonedDoc.querySelectorAll(`#${rootId}, #${rootId} *`);

        allElements.forEach(el => {
            const win = clonedDoc.defaultView || window;
            const computed = win.getComputedStyle(el);
            colorProps.forEach(prop => {
                const val = computed[prop];
                if (val && (val.includes('oklch') || val.includes('oklab') || val.includes('color-mix'))) {
                    el.style[prop] = toRgb(val);
                }
            });
        });
    }

    // 📸 บันทึกภาพตารางเอกสารรายบุคคลเป็น JPG (กางตารางอัตโนมัติขณะถ่ายภาพ และพับเก็บคืนเมื่อเสร็จ)
    async function exportSingleDischargeImage(elementId, soldierName, itemId) {
        const el      = document.getElementById(elementId);
        const wrapper = document.getElementById(`paper-collapse-wrapper-${itemId}`);
        const btn     = document.getElementById(`btn-jpg-${itemId}`);
        if (!el) return;

        const wasHidden = wrapper && wrapper.classList.contains('hidden');
        if (wasHidden) {
            wrapper.classList.remove('hidden');
        }

        const oldHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสร้างภาพ...';
        }

        try {
            const canvas = await html2canvas(el, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                windowWidth: 1280,
                onclone: (clonedDoc) => {
                    sanitizeOklchColorsInClone(clonedDoc, elementId);
                }
            });
            const link = document.createElement('a');
            link.download = `เอกสารนำปลดทหาร_${soldierName}.jpg`;
            link.href = canvas.toDataURL('image/jpeg', 0.95);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `บันทึกภาพเอกสารนำปลดของ ${soldierName} สำเร็จ!`,
                showConfirmButton: false,
                timer: 2500
            });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถสร้างรูปภาพได้' });
        } finally {
            if (wasHidden && wrapper) {
                wrapper.classList.add('hidden');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }
    }

    document.addEventListener('click', function(e) {
        const sBox  = document.getElementById('discharge_patient_search');
        const sList = document.getElementById('discharge_patient_list');
        if (sBox && sList && !sBox.contains(e.target) && !sList.contains(e.target)) {
            sList.add('hidden');
        }
    });
</script>
@endsection