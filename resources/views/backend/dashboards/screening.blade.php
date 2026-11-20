@extends('layouts.backend')

@section('title', 'จุดคัดกรองผู้ป่วย (Screening / Triage) - KSVR Hospital')
@section('page_header', 'แดชบอร์ดจุดคัดกรอง (เรียงลำดับ MEWS สูงสุดก่อน)')

@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-10">
    
    @include('backend.dashboards.partials.view_tabs')

    <!-- 🟢 ส่วนหัวและปุ่มลัด -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl shrink-0 shadow-inner">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg md:text-xl font-black text-slate-800 dark:text-white">คิวจุดคัดกรองผู้ป่วย (Triage Queue)</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800">
                        <i class="fa-solid fa-arrow-down-9-1 mr-1"></i> เรียง MEWS สูงสุดขึ้นอันดับ 1
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">แสดงทุกเคสที่รอคัดกรองและติดตามอาการ พร้อมจุดแยกส่งต่อ ER / OPD และพิมพ์ใบรับรองจุดคัดกรอง</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('backend.patients.search') }}" class="flex-1 md:flex-none px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-sm inline-flex items-center justify-center gap-2 transition-all">
                <i class="fa-solid fa-id-card"></i> ค้นหา / สแกนบัตรประเมินใหม่
            </a>
            <button type="button" onclick="window.location.reload()" class="px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs sm:text-sm font-bold inline-flex items-center gap-1.5 transition-all">
                <i class="fa-solid fa-rotate"></i> รีเฟรช
            </button>
        </div>
    </div>

    <!-- 📊 KPI Summary Cards -->
    <div id="screening-kpi-zone" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <button type="button" onclick="filterScreeningRisk('all', this)" class="screening-filter-card text-left bg-white dark:bg-slate-800 p-4 sm:p-5 rounded-2xl border-2 border-slate-800 dark:border-indigo-500 shadow-sm transition-all">
            <p class="text-xs font-bold text-slate-500 dark:text-slate-400">รอคัดกรองทั้งหมด</p>
            <div class="flex items-end justify-between mt-1.5">
                <h3 class="text-2xl sm:text-3xl font-black text-slate-800 dark:text-white">{{ $stats['total_pending'] }} <span class="text-xs font-medium text-slate-400">ราย</span></h3>
                <span class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center"><i class="fa-solid fa-users"></i></span>
            </div>
        </button>

        <button type="button" onclick="filterScreeningRisk('emergency', this)" class="screening-filter-card text-left bg-white dark:bg-slate-800 p-4 sm:p-5 rounded-2xl border-2 border-transparent hover:border-rose-400 shadow-sm transition-all">
            <p class="text-xs font-bold text-rose-600 dark:text-rose-400">ฉุกเฉิน / มีอาการเฉพาะโรค</p>
            <div class="flex items-end justify-between mt-1.5">
                <h3 class="text-2xl sm:text-3xl font-black text-rose-600 dark:text-rose-400">{{ $stats['emergency'] }} <span class="text-xs font-medium text-slate-400">ราย</span></h3>
                <span class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-500 flex items-center justify-center"><i class="fa-solid fa-truck-medical"></i></span>
            </div>
        </button>

        <button type="button" onclick="filterScreeningRisk('observe', this)" class="screening-filter-card text-left bg-white dark:bg-slate-800 p-4 sm:p-5 rounded-2xl border-2 border-transparent hover:border-amber-400 shadow-sm transition-all">
            <p class="text-xs font-bold text-amber-600 dark:text-amber-400">เฝ้าระวัง (Observe)</p>
            <div class="flex items-end justify-between mt-1.5">
                <h3 class="text-2xl sm:text-3xl font-black text-amber-500">{{ $stats['observe'] }} <span class="text-xs font-medium text-slate-400">ราย</span></h3>
                <span class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-500 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation"></i></span>
            </div>
        </button>

        <button type="button" onclick="filterScreeningRisk('normal', this)" class="screening-filter-card text-left bg-white dark:bg-slate-800 p-4 sm:p-5 rounded-2xl border-2 border-transparent hover:border-emerald-400 shadow-sm transition-all">
            <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400">ปกติ (ส่ง OPD)</p>
            <div class="flex items-end justify-between mt-1.5">
                <h3 class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ $stats['normal'] }} <span class="text-xs font-medium text-slate-400">ราย</span></h3>
                <span class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 flex items-center justify-center"><i class="fa-solid fa-shield-heart"></i></span>
            </div>
        </button>
    </div>

    <!-- 📋 คิวผู้ป่วยเรียงตามคะแนน MEWS สูงสุด -> ต่ำสุด -->
    <div id="screening-queue-zone" class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/70 dark:bg-slate-800/80">
            <h3 class="font-black text-slate-800 dark:text-white text-base flex items-center gap-2">
                <i class="fa-solid fa-list-ol text-amber-500"></i>
                รายชื่อผู้ป่วยรอคัดกรอง / ติดตามอาการ ({{ $screeningQueue->count() }} ราย)
            </h3>
            <div class="relative w-full sm:w-64">
                <i class="fa-solid fa-magnifying-glass text-slate-400 text-xs absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                <input type="text" id="screening-search-input" oninput="filterScreeningByText(this.value)"
                       placeholder="ค้นหาชื่อ, HN, หน่วยฝึก..."
                       class="w-full pl-9 pr-3 py-2 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 focus:border-amber-500 outline-none">
            </div>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-700/70">
            @forelse($screeningQueue as $index => $case)
                @php
                    $pt = $case->patient;
                    $isEmer = $case->risk_level === 'emergency' || $case->has_emergency_symptom;
                    $isObs  = !$isEmer && $case->risk_level === 'observe';
                    $riskGroup = $isEmer ? 'emergency' : ($isObs ? 'observe' : 'normal');

                    $scoreBoxCls = $isEmer
                        ? 'bg-rose-600 text-white shadow-rose-500/30'
                        : ($isObs ? 'bg-amber-500 text-white shadow-amber-500/30' : 'bg-emerald-500 text-white shadow-emerald-500/30');
                    $borderAccent = $isEmer ? 'border-l-rose-500' : ($isObs ? 'border-l-amber-400' : 'border-l-emerald-400');
                    $routeBadge = $isEmer
                        ? ['text' => '🚨 ส่งห้องฉุกเฉิน (ER ด่วน)', 'cls' => 'bg-rose-100 text-rose-700 border-rose-200']
                        : ($isObs ? ['text' => '⚠️ เฝ้าระวัง (ส่ง ER)', 'cls' => 'bg-amber-100 text-amber-800 border-amber-200'] : ['text' => '✅ ปกติ (ส่ง OPD)', 'cls' => 'bg-emerald-100 text-emerald-700 border-emerald-200']);

                    $unitName = $pt->militaryUnit->short_name ?? $pt->militaryUnit->name ?? $pt->company ?? '-';
                    $fullName = ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');
                    $printUrl = \Illuminate\Support\Facades\Route::has('backend.mews.print')
                        ? route('backend.mews.print', $case->id)
                        : (\Illuminate\Support\Facades\Route::has('backend.mews.print_form')
                            ? route('backend.mews.print_form', $case->id)
                            : url('/backend/mews/' . $case->id . '/print'));
                @endphp

                <div class="screening-row p-4 sm:p-5 border-l-4 {{ $borderAccent }} hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors"
                     data-risk="{{ $riskGroup }}"
                     data-search="{{ mb_strtolower($fullName . ' ' . ($pt->hn ?? '') . ' ' . ($pt->cid ?? '') . ' ' . $unitName) }}">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        
                        <!-- ฝั่งซ้าย: ลำดับ + คะแนน MEWS + ข้อมูลผู้ป่วย -->
                        <div class="flex items-start gap-3.5 min-w-0">
                            <!-- ลำดับและกล่องคะแนน MEWS -->
                            <div class="flex flex-col items-center shrink-0">
                                <span class="text-[10px] font-black text-slate-400 mb-1">อันดับ #{{ $index + 1 }}</span>
                                <div class="w-14 h-14 rounded-2xl {{ $scoreBoxCls }} shadow-md flex flex-col items-center justify-center leading-none">
                                    <span class="text-[9px] font-bold opacity-85 uppercase">MEWS</span>
                                    <span class="text-2xl font-black mt-0.5">{{ $case->total_score }}</span>
                                </div>
                            </div>

                            <!-- รายละเอียดผู้ป่วยและสัญญาณชีพ -->
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="font-black text-slate-800 dark:text-white text-base sm:text-lg leading-tight">
                                        {{ $fullName }}
                                    </h4>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $routeBadge['cls'] }}">
                                        {{ $routeBadge['text'] }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        ประเมินรอบที่ {{ $case->assessment_round }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    <span><i class="fa-solid fa-tent text-indigo-500 mr-1"></i><b>{{ $unitName }}</b></span>
                                    <span><i class="fa-regular fa-id-card mr-1"></i>HN: <b>{{ $pt->hn ?: 'ยังไม่มี HN' }}</b></span>
                                    <span><i class="fa-regular fa-clock mr-1"></i>{{ $case->created_at->addYears(543)->format('d/m/y H:i น.') }} ({{ $case->created_at->diffForHumans() }})</span>
                                </div>

                                <!-- แถบสัญญาณชีพ -->
                                <div class="flex flex-wrap gap-1.5 pt-0.5 text-[11px] font-bold">
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200">BP: {{ $case->systolic_bp ?? '-' }}</span>
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200">HR: {{ $case->heart_rate ?? '-' }}</span>
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200">RR: {{ $case->respiratory_rate ?? '-' }}</span>
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200">T: {{ $case->temperature ?? '-' }}°C</span>
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200">O2: {{ $case->o2sat ?? '-' }}%</span>
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200">AVPU: {{ $case->consciousness ?? '-' }}</span>
                                </div>

                                <!-- อาการฉุกเฉิน / โน้ตเพิ่มเติม -->
                                @if($case->has_emergency_symptom)
                                    <div class="flex flex-wrap gap-1 pt-0.5">
                                        @foreach($case->parsed_symptoms as $symCode)
                                            <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $symptomDict[$symCode] ?? $symCode }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                @if(!empty($case->symptoms_note))
                                    <p class="text-xs text-slate-600 dark:text-slate-300 bg-amber-50/70 dark:bg-amber-900/20 px-2.5 py-1.5 rounded-xl border border-amber-200/70 dark:border-amber-800/40 inline-block">
                                        <i class="fa-solid fa-note-sticky text-amber-500 mr-1"></i> {{ $case->symptoms_note }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <!-- ฝั่งขวา: ปุ่มจัดการ -->
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0 lg:justify-end">
                            <a href="{{ $printUrl }}" target="_blank"
                               class="flex-1 sm:flex-none px-3.5 py-2.5 rounded-xl bg-amber-50 hover:bg-amber-500 text-amber-700 hover:text-white border border-amber-200 text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-all shadow-sm">
                                <i class="fa-solid fa-print"></i> พิมพ์ใบ MEWS
                            </a>
                            <a href="{{ route('backend.mews.create', $case->patient_id) }}"
                               class="flex-1 sm:flex-none px-3.5 py-2.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-all shadow-sm">
                                <i class="fa-solid fa-stethoscope"></i> ประเมินซ้ำ
                            </a>
                            <a href="{{ route('backend.mews.show', $case->id) }}"
                               class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-emerald-600 text-white text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-all shadow-sm">
                                <i class="fa-solid fa-folder-open"></i> เปิดเคส
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-14 px-6 text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 flex items-center justify-center text-3xl mx-auto mb-3">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h4 class="font-bold text-slate-700 dark:text-slate-200 text-base">ยังไม่มีผู้ป่วยค้างในจุดคัดกรองขณะนี้</h4>
                    <p class="text-xs text-slate-400 mt-1">เมื่อมีการส่งผลประเมิน MEWS รอบแรกเข้ามา ระบบจะจัดเรียงผู้ป่วยคะแนนสูงสุดขึ้นอันดับ 1 อัตโนมัติครับ</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 🗄️ ประวัติปิดเคสวันนี้ (สรุปสั้นด้านล่าง) -->
    @if($completedToday->count() > 0)
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/80 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                ประวัติที่ดำเนินการเสร็จสิ้นวันนี้ ({{ $completedToday->count() }} ราย)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3 font-bold">ผู้ป่วย / หน่วยฝึก</th>
                        <th class="px-4 py-3 font-bold text-center">MEWS</th>
                        <th class="px-4 py-3 font-bold">สถานะจำหน่าย</th>
                        <th class="px-4 py-3 font-bold text-right">ดูรายละเอียด</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @foreach($completedToday as $done)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800 dark:text-white">{{ $done->patient->rank ?? '' }}{{ $done->patient->first_name ?? '' }} {{ $done->patient->last_name ?? '' }}</div>
                                <div class="text-[11px] text-slate-400">{{ $done->patient->militaryUnit->short_name ?? '-' }} • {{ $done->updated_at->format('H:i น.') }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-lg font-black {{ $done->total_score >= 4 ? 'bg-rose-100 text-rose-700' : ($done->total_score >= 2 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">
                                    {{ $done->total_score }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-600 dark:text-slate-300">
                                @if($done->status === 'cancelled')
                                    <span class="text-slate-400">ยกเลิกเคส</span>
                                @elseif($done->discharge_type === 'admitted')
                                    <span class="text-amber-600">Admit {{ $done->discharge_details }}</span>
                                @elseif($done->discharge_type === 'refer')
                                    <span class="text-blue-600">Refer {{ $done->discharge_details }}</span>
                                @else
                                    <span class="text-emerald-600">จำหน่ายกลับหน่วย</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('backend.mews.show', $done->id) }}" class="text-indigo-600 hover:underline font-bold">ดูแฟ้ม</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection

@section('scripts')
<script>
    let currentRiskFilter = 'all';

    function filterScreeningRisk(risk, btnEl) {
        currentRiskFilter = risk;
        document.querySelectorAll('.screening-filter-card').forEach(el => {
            el.classList.remove('border-slate-800', 'dark:border-indigo-500', 'border-rose-500', 'border-amber-500', 'border-emerald-500');
            el.classList.add('border-transparent');
        });
        if (btnEl) {
            btnEl.classList.remove('border-transparent');
            const activeMap = {
                'all': ['border-slate-800', 'dark:border-indigo-500'],
                'emergency': ['border-rose-500'],
                'observe': ['border-amber-500'],
                'normal': ['border-emerald-500']
            };
            btnEl.classList.add(...(activeMap[risk] || ['border-slate-800']));
        }
        applyScreeningFilters();
    }

    function filterScreeningByText() {
        applyScreeningFilters();
    }

    function applyScreeningFilters() {
        const kw = (document.getElementById('screening-search-input')?.value || '').trim().toLowerCase();
        document.querySelectorAll('.screening-row').forEach(row => {
            const rRisk = row.getAttribute('data-risk') || '';
            const rSearch = row.getAttribute('data-search') || '';
            const matchRisk = (currentRiskFilter === 'all' || rRisk === currentRiskFilter);
            const matchText = (kw === '' || rSearch.includes(kw));
            row.classList.toggle('hidden', !(matchRisk && matchText));
        });
    }

    // รีโหลดคิวอัตโนมัติเมื่อมีเคส MEWS ใหม่เข้ามาผ่าน WebSocket
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Echo) {
            window.Echo.private('er-alerts').listen('.mews.emergency', () => {
                fetch(window.location.href)
                    .then(res => res.text())
                    .then(html => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const newKpi = doc.getElementById('screening-kpi-zone');
                        const newQueue = doc.getElementById('screening-queue-zone');
                        if (newKpi && document.getElementById('screening-kpi-zone')) {
                            document.getElementById('screening-kpi-zone').innerHTML = newKpi.innerHTML;
                        }
                        if (newQueue && document.getElementById('screening-queue-zone')) {
                            document.getElementById('screening-queue-zone').innerHTML = newQueue.innerHTML;
                            applyScreeningFilters();
                        }
                    }).catch(() => {});
            });
        }
    });
</script>
@endsection