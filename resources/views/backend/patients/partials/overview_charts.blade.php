@if(isset($overviewCharts) && is_array($overviewCharts))
@php
    $chartBatchParam = request('batch', '');
    $chartUnitParam  = request('unit', '');
    $bmiHighRisk     = (int) ($overviewCharts['bmi_and_chronic']['bmi']['high_risk_cnt'] ?? 0);
    $chronicAllowed  = (bool) ($overviewCharts['bmi_and_chronic']['chronic']['allowed'] ?? false);
    $activeBedsTotal = (int) ($overviewCharts['infirmary_ipd_by_unit']['totals']['grand_active_beds'] ?? 0);
    $infirmaryTotal  = (int) ($overviewCharts['infirmary_ipd_by_unit']['totals']['infirmary_total'] ?? 0);
    $ipdTotal        = (int) ($overviewCharts['infirmary_ipd_by_unit']['totals']['ipd_total'] ?? 0);
    $appTodayCount   = (int) ($overviewCharts['appointments_next_7d']['today_count'] ?? 0);
    $appTomCount     = (int) ($overviewCharts['appointments_next_7d']['tomorrow_count'] ?? 0);
    $appTotal7d      = (int) ($overviewCharts['appointments_next_7d']['total_7d'] ?? 0);
    $dcSum           = $overviewCharts['discharge_summary'] ?? ['physical' => 0, 'mental' => 0, 'in_progress' => 0, 'completed' => 0, 'total' => 0, 'completed_percent' => 0];
@endphp

<!-- ========================================================================= -->
<!-- 📊 Executive Analytics Bento Grid (เรียงตามลำดับ มิติที่ 1 → 2 → 3 → 4) -->
<!-- ========================================================================= -->
<section id="executive-bento-charts" class="space-y-5 animate-fade-in-up">

    <!-- หัวข้อโซนกราฟ + ปุ่มรีเฟรชตัวเลขสด -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl px-5 py-4 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-teal-500 to-indigo-600 text-white flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-chart-line text-base"></i>
            </div>
            <div>
                <h3 class="text-sm sm:text-base font-black text-slate-800 dark:text-white flex flex-wrap items-center gap-2">
                    <span>ศูนย์วิเคราะห์ข้อมูลผู้บริหาร (Executive Analytics)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800">
                        4 มิติ • 8 แผนภูมิ
                    </span>
                </h3>
                <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    <i class="fa-solid fa-hand-pointer text-indigo-500 mr-1"></i>คลิกที่แท่งกราฟหรือชิ้นส่วนแผนภูมิเพื่อเจาะดูรายชื่อพลทหารในหมวดนั้นทันที
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 self-start sm:self-center">
            <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-400 bg-slate-50 dark:bg-slate-900 px-3 py-1.5 rounded-xl border border-slate-200/70 dark:border-slate-700">
                <i class="fa-regular fa-clock mr-1"></i>อัปเดต: {{ $overviewCharts['generated_at'] ?? '-' }}
            </span>
            <a href="{{ route('backend.patients.index', array_merge(request()->query(), ['view' => 'overview', 'refresh_charts' => 1])) }}"
               class="px-3.5 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 hover:bg-indigo-600 text-indigo-700 dark:text-indigo-300 hover:text-white border border-indigo-200 dark:border-indigo-700 text-xs font-bold inline-flex items-center gap-1.5 transition-all shadow-sm">
                <i class="fa-solid fa-arrows-rotate"></i> อัปเดตตัวเลขสด
            </a>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 🎖️ แถวที่ 1 (มิติที่ 1): ยอดกำลังพล & ความพร้อม + สิทธิการรักษา (สีมาตรฐาน รพ.) -->
    <!-- ===================================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- กราฟที่ 1: ยอดกำลังพลและสถานะความพร้อม แยกตามหน่วยฝึก -->
        <div class="lg:col-span-7 bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-indigo-500">มิติที่ 1 • กำลังพล &amp; ความพร้อม</span>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mt-0.5 flex items-center gap-2">
                        <i class="fa-solid fa-users-rectangle text-indigo-500"></i> สถานะความพร้อมกำลังพล แยกตามหน่วยฝึก
                    </h4>
                </div>
                <span class="text-[11px] font-bold text-slate-400">คลิกแท่งเพื่อดูรายชื่อหน่วย</span>
            </div>
            <div class="relative h-64 sm:h-72 w-full">
                <canvas id="chart-manpower-unit"></canvas>
            </div>
        </div>

        <!-- กราฟที่ 2: สัดส่วนสิทธิการรักษาและความคืบหน้าการซิงค์ HN -->
        <div class="lg:col-span-5 bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400">มิติที่ 1 • สิทธิการรักษา &amp; HN</span>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mt-0.5 flex items-center gap-2">
                        <i class="fa-solid fa-shield-heart text-amber-500"></i> สัดส่วนสิทธิการรักษาพยาบาล
                    </h4>
                </div>
                <span class="px-2.5 py-1 rounded-xl text-xs font-black bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 shrink-0">
                    ซิงค์ HN {{ $overviewCharts['coverage_donut']['hn_percent'] ?? 0 }}%
                </span>
            </div>
            <div class="relative h-64 sm:h-72 w-full">
                <canvas id="chart-coverage-donut"></canvas>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 🩺 แถวที่ 2 (มิติที่ 2): ผลคัดกรองแรกรับแยกตามหน่วย + ดัชนี BMI & โรคประจำตัว -->
    <!-- ===================================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- กราฟที่ 3: ความคืบหน้าและผลคัดกรองทหารใหม่ แยกตามหน่วยฝึก -->
        <div class="lg:col-span-6 bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">มิติที่ 2 • คัดกรองแรกรับ</span>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mt-0.5 flex items-center gap-2">
                        <i class="fa-solid fa-clipboard-check text-emerald-500"></i> ผลคัดกรองทหารใหม่ แยกตามหน่วยฝึก
                    </h4>
                </div>
                <a href="{{ route('backend.recruit_screenings.dashboard', ['intake_batch' => $chartBatchParam ?: 'all']) }}"
                   class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:underline inline-flex items-center gap-1">
                    หน้าคัดกรอง <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>
            <div class="relative h-64 sm:h-72 w-full">
                <canvas id="chart-screening-unit"></canvas>
            </div>
        </div>

        <!-- กราฟที่ 4: การกระจายตัวของดัชนี BMI (เสี่ยงลมร้อน) + โรคประจำตัว (PDPA) -->
        <div class="lg:col-span-6 bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-orange-500">มิติที่ 2 • สุขภาพพื้นฐาน &amp; ความเสี่ยง</span>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mt-0.5 flex items-center gap-2">
                        <i class="fa-solid fa-weight-scale text-orange-500"></i> ดัชนีมวลกาย (BMI){{ $chronicAllowed ? ' & โรคประจำตัว' : '' }}
                    </h4>
                </div>
                @if($bmiHighRisk > 0)
                    <span class="px-2.5 py-1 rounded-xl text-[11px] font-black bg-rose-50 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 shrink-0">
                        <i class="fa-solid fa-temperature-arrow-up mr-1"></i>BMI ≥ 30: {{ number_format($bmiHighRisk) }} นาย
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 {{ $chronicAllowed ? 'sm:grid-cols-12' : '' }} gap-4 items-center">
                <div class="{{ $chronicAllowed ? 'sm:col-span-7' : '' }} relative h-60 sm:h-64 w-full">
                    <canvas id="chart-bmi-dist"></canvas>
                </div>

                @if($chronicAllowed)
                    <div class="sm:col-span-5 flex flex-col items-center justify-center border-t sm:border-t-0 sm:border-l border-slate-100 dark:border-slate-700 pt-4 sm:pt-0 sm:pl-4">
                        <div class="text-xs font-black text-rose-600 dark:text-rose-400 mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-heart-pulse"></i> โรคประจำตัว (PDPA)
                        </div>
                        <div class="relative h-48 w-full max-w-[200px]">
                            <canvas id="chart-chronic-donut"></canvas>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 🔥 แถวที่ 3 (มิติที่ 3): เฝ้าระวังโรคระบาด 14 วัน + ภาระเตียงห้องพยาบาล/นอน รพ. -->
    <!-- ===================================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- กราฟที่ 5: แนวโน้มการเจ็บป่วยรายวันย้อนหลัง 14 วัน -->
        <div class="lg:col-span-7 bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-rose-500">มิติที่ 3 • เฝ้าระวังโรคระบาด</span>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mt-0.5 flex items-center gap-2">
                        <i class="fa-solid fa-virus-covid text-rose-500"></i> แนวโน้มการเจ็บป่วยรายวัน (ย้อนหลัง 14 วัน)
                    </h4>
                </div>
                <a href="{{ route('backend.morbidity_reports.index', ['intake_batch' => $chartBatchParam ?: 'all']) }}"
                   class="text-[11px] font-bold text-rose-600 dark:text-rose-400 hover:underline inline-flex items-center gap-1">
                    เปิดรายงานประจำวัน <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>
            <div class="relative h-64 sm:h-72 w-full">
                <canvas id="chart-morbidity-trend"></canvas>
            </div>
            <p class="text-[11px] text-slate-400 mt-3">
                <i class="fa-solid fa-circle-info mr-1 text-sky-500"></i>คลิกจุดบนเส้นกราฟเพื่อเปิดดูรายงานประจำวันของวันที่นั้นโดยตรง
            </p>
        </div>

        <!-- กราฟที่ 6: ยอดผู้ป่วยแยกนอนห้องพยาบาล / นอน รพ. ปัจจุบัน แยกตามหน่วยฝึก -->
        <div class="lg:col-span-5 bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-teal-600 dark:text-teal-400">มิติที่ 3 • ภาระเตียง &amp; แยกกัก</span>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mt-0.5 flex items-center gap-2">
                        <i class="fa-solid fa-bed-pulse text-teal-500"></i> แยกนอนห้องพยาบาล / นอน รพ. ปัจจุบัน
                    </h4>
                </div>
                <span class="px-2.5 py-1 rounded-xl text-xs font-black bg-teal-50 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 shrink-0">
                    รวม {{ number_format($activeBedsTotal) }} นาย
                </span>
            </div>

            <div class="flex flex-wrap gap-2 mb-3 text-[11px] font-bold">
                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800">
                    ห้องพยาบาล/แยกกัก: {{ number_format($infirmaryTotal) }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                    Admit/Refer รพ.: {{ number_format($ipdTotal) }}
                </span>
            </div>

            <div class="relative h-60 sm:h-64 w-full">
                <canvas id="chart-infirmary-ipd"></canvas>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 📅 แถวที่ 4 (มิติที่ 4): นัดพบแพทย์ล่วงหน้า 7 วัน + สรุปเอกสารนำปลดทหาร -->
    <!-- ===================================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- กราฟที่ 7: ปริมาณคิวนัดพบแพทย์ล่วงหน้า 7 วัน -->
        <div class="lg:col-span-7 bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-sky-600 dark:text-sky-400">มิติที่ 4 • นัดหมาย &amp; การส่งตัว</span>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mt-0.5 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-check text-sky-500"></i> ปริมาณคิวนัดพบแพทย์ล่วงหน้า 7 วัน
                    </h4>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-black">
                    <span class="px-2.5 py-1 rounded-xl bg-rose-50 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                        วันนี้ {{ number_format($appTodayCount) }}
                    </span>
                    <span class="px-2.5 py-1 rounded-xl bg-amber-50 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        พรุ่งนี้ {{ number_format($appTomCount) }}
                    </span>
                    <span class="px-2.5 py-1 rounded-xl bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                        รวม 7 วัน: {{ number_format($appTotal7d) }}
                    </span>
                </div>
            </div>
            <div class="relative h-64 sm:h-72 w-full">
                <canvas id="chart-appointments-7d"></canvas>
            </div>
        </div>

        <!-- กราฟที่ 8: สรุปเอกสารนำปลดทหาร แยกประเภท (กาย vs สุขภาพจิต) และสถานะ -->
        <div class="lg:col-span-5 bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-400">มิติที่ 4 • เอกสารนำปลดทหาร</span>
                    <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mt-0.5 flex items-center gap-2">
                        <i class="fa-solid fa-folder-open text-purple-500"></i> สรุปนำปลด (ทางกาย vs สุขภาพจิต)
                    </h4>
                </div>
                <a href="{{ route('backend.military_discharges.index', ['intake_batch' => $chartBatchParam ?: 'all']) }}"
                   class="text-[11px] font-bold text-purple-600 dark:text-purple-400 hover:underline inline-flex items-center gap-1">
                    จัดการเอกสาร <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>

            <div class="relative h-48 sm:h-52 w-full flex items-center justify-center">
                @if(($dcSum['total'] ?? 0) > 0)
                    <canvas id="chart-discharge-donut"></canvas>
                @else
                    <div class="text-center text-slate-400 py-8">
                        <i class="fa-regular fa-folder-open text-3xl mb-2 block opacity-50"></i>
                        <span class="text-xs font-bold">ยังไม่มีรายการขึ้นทะเบียนนำปลดในขอบเขตนี้</span>
                    </div>
                @endif
            </div>

            <!-- แถบความคืบหน้าสถานะการนำปลด -->
            <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-700 space-y-2">
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-slate-600 dark:text-slate-300">ความคืบหน้าการปลดประจำการ</span>
                    <span class="text-purple-700 dark:text-purple-300 font-black">{{ $dcSum['completed_percent'] ?? 0 }}%</span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-emerald-500 h-2.5 rounded-full transition-all duration-700"
                         style="width: {{ $dcSum['completed_percent'] ?? 0 }}%"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] font-bold pt-0.5">
                    <span class="text-amber-600 dark:text-amber-400">
                        <i class="fa-solid fa-hourglass-half mr-1"></i>กำลังดำเนินการ: {{ number_format($dcSum['in_progress'] ?? 0) }} นาย
                    </span>
                    <span class="text-emerald-600 dark:text-emerald-400">
                        <i class="fa-solid fa-circle-check mr-1"></i>ปลดแล้ว: {{ number_format($dcSum['completed'] ?? 0) }} นาย
                    </span>
                </div>
            </div>
        </div>
    </div>

</section>

<!-- โหลด Chart.js v4 เฉพาะโหมด Overview -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const chartData = @json($overviewCharts);
    const routes = {
        patientsList: "{{ route('backend.patients.index') }}",
        morbidity: "{{ route('backend.morbidity_reports.index') }}",
        infirmary: "{{ route('backend.recruit_infirmary.index') }}",
        appointments: "{{ route('backend.recruit_appointments.index') }}",
        discharges: "{{ route('backend.military_discharges.index') }}"
    };
    const activeBatch = @json($chartBatchParam);
    const activeUnit  = @json($chartUnitParam);

    const chartInstances = {};

    function isDarkMode() {
        return document.documentElement.classList.contains('dark');
    }

    function getThemeTokens() {
        const dark = isDarkMode();
        return {
            text: dark ? '#cbd5e1' : '#475569',
            title: dark ? '#f8fafc' : '#0f172a',
            grid: dark ? 'rgba(148, 163, 184, 0.14)' : 'rgba(226, 232, 240, 0.85)',
            tooltipBg: dark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(15, 23, 42, 0.90)',
            borderColor: dark ? '#1e293b' : '#ffffff'
        };
    }

    function navigateWithParams(baseUrl, paramsObj) {
        const sp = new URLSearchParams();
        Object.entries(paramsObj).forEach(([k, v]) => {
            if (v !== null && v !== undefined && String(v).trim() !== '') {
                sp.set(k, String(v));
            }
        });
        const qs = sp.toString();
        window.location.href = qs ? `${baseUrl}?${qs}` : baseUrl;
    }

    const centerTextPlugin = {
        id: 'ksvrCenterText',
        afterDraw(chart) {
            const opts = chart.config.options?.plugins?.ksvrCenterText;
            if (!opts) return;
            const { ctx, chartArea } = chart;
            if (!chartArea) return;

            const centerX = (chartArea.left + chartArea.right) / 2;
            const centerY = (chartArea.top + chartArea.bottom) / 2;
            const theme = getThemeTokens();

            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            ctx.font = '800 22px "Prompt", sans-serif';
            ctx.fillStyle = theme.title;
            ctx.fillText(opts.primary || '', centerX, centerY - 8);

            ctx.font = '600 11px "Prompt", sans-serif';
            ctx.fillStyle = theme.text;
            ctx.fillText(opts.secondary || '', centerX, centerY + 14);
            ctx.restore();
        }
    };

    function handleHoverCursor(event, elements) {
        const target = event?.native?.target;
        if (target) {
            target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    }

    function renderAllOverviewCharts() {
        if (typeof window.Chart === 'undefined') return;

        Object.keys(chartInstances).forEach(k => {
            try { chartInstances[k].destroy(); } catch (e) {}
            delete chartInstances[k];
        });

        const theme = getThemeTokens();
        Chart.defaults.font.family = "'Prompt', sans-serif";
        Chart.defaults.color = theme.text;

        const baseTooltip = {
            backgroundColor: theme.tooltipBg,
            titleColor: '#f8fafc',
            bodyColor: '#e2e8f0',
            padding: 10,
            cornerRadius: 12,
            displayColors: true
        };

        // =====================================================================
        // มิติที่ 1 — กราฟที่ 1: ยอดกำลังพลและความพร้อม แยกตามหน่วยฝึก
        // =====================================================================
        const mpEl = document.getElementById('chart-manpower-unit');
        if (mpEl && chartData.manpower_by_unit) {
            const mp = chartData.manpower_by_unit;
            const statusByDataset = ['active_duty', 'discharged', 'absent', 'inactive_all'];
            chartInstances.manpower = new Chart(mpEl, {
                type: 'bar',
                data: {
                    labels: mp.labels,
                    datasets: [
                        { label: 'ประจำการปกติ', data: mp.active, backgroundColor: '#10b981', borderRadius: 4 },
                        { label: 'ยื่นนำปลด/ปลดแล้ว', data: mp.discharged, backgroundColor: '#8b5cf6', borderRadius: 4 },
                        { label: 'ไม่มารายงานตัว', data: mp.absent, backgroundColor: '#f97316', borderRadius: 4 },
                        { label: 'ต้องโทษ/เสียชีวิต', data: mp.other_inactive, backgroundColor: '#475569', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        const { datasetIndex, index } = els[0];
                        const uid = mp.unit_ids?.[index];
                        navigateWithParams(routes.patientsList, {
                            view: 'list',
                            unit: uid,
                            batch: activeBatch,
                            discharge_status: statusByDataset[datasetIndex] || ''
                        });
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } },
                        tooltip: baseTooltip
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: theme.grid } }
                    }
                }
            });
        }

        // =====================================================================
        // มิติที่ 1 — กราฟที่ 2: สัดส่วนสิทธิการรักษา & ความคืบหน้าซิงค์ HN
        // =====================================================================
        const covEl = document.getElementById('chart-coverage-donut');
        if (covEl && chartData.coverage_donut) {
            const cov = chartData.coverage_donut;
            chartInstances.coverage = new Chart(covEl, {
                type: 'doughnut',
                data: {
                    labels: cov.labels,
                    datasets: [{
                        data: cov.data,
                        backgroundColor: cov.colors,
                        borderColor: theme.borderColor,
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '66%',
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        const lbl = cov.labels?.[els[0].index] || '';
                        if (lbl === 'รอซิงค์สิทธิ') {
                            navigateWithParams(routes.patientsList, { view: 'list', batch: activeBatch, unit: activeUnit, pttype: 'unsynced' });
                        } else if (lbl === 'อื่นๆ') {
                            navigateWithParams(routes.patientsList, { view: 'list', batch: activeBatch, unit: activeUnit });
                        } else {
                            const searchKw = lbl === 'ข้าราชการพลเรือน' ? 'ข้าราชการ' : lbl;
                            navigateWithParams(routes.patientsList, { view: 'list', batch: activeBatch, unit: activeUnit, search: searchKw });
                        }
                    },
                    plugins: {
                        legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8, padding: 10, font: { size: 11 } } },
                        tooltip: baseTooltip,
                        ksvrCenterText: {
                            primary: `${cov.hn_percent ?? 0}%`,
                            secondary: `HN ${Number(cov.synced_hn ?? 0).toLocaleString()}/${Number(cov.total_patients ?? 0).toLocaleString()}`
                        }
                    }
                },
                plugins: [centerTextPlugin]
            });
        }

        // =====================================================================
        // มิติที่ 2 — กราฟที่ 3: ผลคัดกรองแรกรับ แยกตามหน่วยฝึก
        // =====================================================================
        const scEl = document.getElementById('chart-screening-unit');
        if (scEl && chartData.screening_by_unit) {
            const sc = chartData.screening_by_unit;
            const scKeys = ['green', 'yellow', 'red', 'unscreened'];
            chartInstances.screening = new Chart(scEl, {
                type: 'bar',
                data: {
                    labels: sc.labels,
                    datasets: [
                        { label: 'ปกติ (เขียว)', data: sc.green, backgroundColor: '#10b981', borderRadius: 4 },
                        { label: 'กลุ่มเสี่ยง (เหลือง)', data: sc.yellow, backgroundColor: '#f59e0b', borderRadius: 4 },
                        { label: 'พบเชื้อ (แดง)', data: sc.red, backgroundColor: '#e11d48', borderRadius: 4 },
                        { label: 'รอคัดกรอง', data: sc.unscreened, backgroundColor: '#94a3b8', borderRadius: 4 }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        const { datasetIndex, index } = els[0];
                        const uid = sc.unit_ids?.[index];
                        navigateWithParams(routes.patientsList, {
                            view: 'list',
                            unit: uid,
                            batch: activeBatch,
                            screening_status: scKeys[datasetIndex] || ''
                        });
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12, font: { size: 11 } } },
                        tooltip: baseTooltip
                    },
                    scales: {
                        x: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: theme.grid } },
                        y: { stacked: true, grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        // =====================================================================
        // มิติที่ 2 — กราฟที่ 4A & 4B: การกระจายตัวของดัชนี BMI + โรคประจำตัว (PDPA)
        // =====================================================================
        const bmiEl = document.getElementById('chart-bmi-dist');
        if (bmiEl && chartData.bmi_and_chronic?.bmi) {
            const bmi = chartData.bmi_and_chronic.bmi;
            chartInstances.bmi = new Chart(bmiEl, {
                type: 'bar',
                data: {
                    labels: ['ผอม', 'ปกติ', 'ท้วม', 'อ้วน 1', 'อ้วน 2 (≥30)', 'ไม่ระบุ'],
                    datasets: [{
                        label: 'จำนวนกำลังพล (นาย)',
                        data: bmi.data,
                        backgroundColor: bmi.colors,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        navigateWithParams(routes.patientsList, { view: 'list', batch: activeBatch, unit: activeUnit });
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...baseTooltip,
                            callbacks: {
                                title: (items) => bmi.labels?.[items[0].dataIndex] || items[0].label
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: theme.grid } }
                    }
                }
            });
        }

        const chrEl = document.getElementById('chart-chronic-donut');
        if (chrEl && chartData.bmi_and_chronic?.chronic?.allowed) {
            const chr = chartData.bmi_and_chronic.chronic;
            const chrFilterMap = ['none', 'yes', 'other'];
            chartInstances.chronic = new Chart(chrEl, {
                type: 'doughnut',
                data: {
                    labels: chr.labels,
                    datasets: [{
                        data: chr.data,
                        backgroundColor: chr.colors,
                        borderColor: theme.borderColor,
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        navigateWithParams(routes.patientsList, {
                            view: 'list',
                            batch: activeBatch,
                            unit: activeUnit,
                            chronic_status: chrFilterMap[els[0].index] || ''
                        });
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 7, padding: 8, font: { size: 10 } } },
                        tooltip: baseTooltip
                    }
                }
            });
        }

        // =====================================================================
        // มิติที่ 3 — กราฟที่ 5: แนวโน้มการเจ็บป่วยรายวันย้อนหลัง 14 วัน
        // =====================================================================
        const trendEl = document.getElementById('chart-morbidity-trend');
        if (trendEl && chartData.morbidity_trend_14d) {
            const mt = chartData.morbidity_trend_14d;
            chartInstances.trend = new Chart(trendEl, {
                type: 'line',
                data: {
                    labels: mt.labels,
                    datasets: [
                        { label: 'โควิด-19', data: mt.covid, borderColor: '#0ea5e9', backgroundColor: 'rgba(14,165,233,0.12)', fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6 },
                        { label: 'ไข้หวัดใหญ่', data: mt.flu, borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.10)', fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6 },
                        { label: 'บาดเจ็บความร้อน', data: mt.heat, borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.10)', fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6 },
                        { label: 'สุขภาพจิต', data: mt.mental, borderColor: '#a855f7', backgroundColor: 'rgba(168,85,247,0.08)', fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        const dateStr = mt.dates?.[els[0].index];
                        if (dateStr) {
                            navigateWithParams(routes.morbidity, {
                                report_date: dateStr,
                                intake_batch: activeBatch || 'all'
                            });
                        }
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } },
                        tooltip: baseTooltip
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: theme.grid } }
                    }
                }
            });
        }

        // =====================================================================
        // มิติที่ 3 — กราฟที่ 6: ยอดแยกนอนห้องพยาบาล / นอน รพ. ปัจจุบัน แยกตามหน่วยฝึก
        // =====================================================================
        const infEl = document.getElementById('chart-infirmary-ipd');
        if (infEl && chartData.infirmary_ipd_by_unit) {
            const inf = chartData.infirmary_ipd_by_unit;
            chartInstances.infirmary = new Chart(infEl, {
                type: 'bar',
                data: {
                    labels: inf.labels,
                    datasets: [
                        { label: 'ห้องพยาบาลหน่วย', data: inf.unit_infirmary, backgroundColor: '#6366f1', borderRadius: 6 },
                        { label: 'ฝากนอนแยกกัก รพ.', data: inf.camp_isolation, backgroundColor: '#14b8a6', borderRadius: 6 },
                        { label: 'Admit รพ.ค่ายฯ', data: inf.admit_camp, backgroundColor: '#f59e0b', borderRadius: 6 },
                        { label: 'Refer รพ.สธ.', data: inf.refer_public, backgroundColor: '#e11d48', borderRadius: 6 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        const { datasetIndex, index } = els[0];
                        const uid = inf.unit_ids?.[index];
                        if (datasetIndex === 0 || datasetIndex === 1) {
                            navigateWithParams(routes.infirmary, { unit_id: uid, intake_batch: activeBatch || 'all' });
                        } else if (datasetIndex === 2) {
                            navigateWithParams(routes.patientsList, { view: 'list', unit: uid, batch: activeBatch, mews_status: 'admitted_only' });
                        } else if (datasetIndex === 3) {
                            navigateWithParams(routes.patientsList, { view: 'list', unit: uid, batch: activeBatch, mews_status: 'refer_only' });
                        }
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12, font: { size: 11 } } },
                        tooltip: baseTooltip
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: theme.grid } }
                    }
                }
            });
        }

        // =====================================================================
        // มิติที่ 4 — กราฟที่ 7: ปริมาณคิวนัดพบแพทย์ล่วงหน้า 7 วัน
        // =====================================================================
        const appEl = document.getElementById('chart-appointments-7d');
        if (appEl && chartData.appointments_next_7d) {
            const app = chartData.appointments_next_7d;
            chartInstances.appointments = new Chart(appEl, {
                type: 'bar',
                data: {
                    labels: app.labels,
                    datasets: [
                        { label: 'รพ.ค่ายกฤษณ์สีวะรา', data: app.camp_hospital, backgroundColor: '#10b981', borderRadius: 6 },
                        { label: 'รพ.สาธารณสุขภายนอก', data: app.public_hospital, backgroundColor: '#6366f1', borderRadius: 6 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        const { datasetIndex, index } = els[0];
                        const scope = index === 0 ? 'today' : (index === 1 ? 'tomorrow' : 'upcoming');
                        const hType = datasetIndex === 0 ? 'camp_hospital' : 'public_hospital';
                        navigateWithParams(routes.appointments, {
                            intake_batch: activeBatch || 'all',
                            unit_id: activeUnit,
                            date_scope: scope,
                            hospital_type: hType
                        });
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } },
                        tooltip: baseTooltip
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: theme.grid } }
                    }
                }
            });
        }

        // =====================================================================
        // มิติที่ 4 — กราฟที่ 8: สรุปเอกสารนำปลดทหาร แยกประเภท (กาย vs สุขภาพจิต)
        // =====================================================================
        const dcEl = document.getElementById('chart-discharge-donut');
        if (dcEl && chartData.discharge_summary && chartData.discharge_summary.total > 0) {
            const dc = chartData.discharge_summary;
            chartInstances.discharge = new Chart(dcEl, {
                type: 'doughnut',
                data: {
                    labels: ['ปลดทางกาย', 'ปลดทางสุขภาพจิต'],
                    datasets: [{
                        data: [dc.physical, dc.mental],
                        backgroundColor: ['#3b82f6', '#9333ea'],
                        borderColor: theme.borderColor,
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '64%',
                    onHover: handleHoverCursor,
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        navigateWithParams(routes.discharges, { intake_batch: activeBatch || 'all' });
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12 } },
                        tooltip: baseTooltip,
                        ksvrCenterText: {
                            primary: `${dc.total}`,
                            secondary: 'นายทั้งหมด'
                        }
                    }
                },
                plugins: [centerTextPlugin]
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderAllOverviewCharts();

        let prevDark = isDarkMode();
        const themeObserver = new MutationObserver(() => {
            const curDark = isDarkMode();
            if (curDark !== prevDark) {
                prevDark = curDark;
                renderAllOverviewCharts();
            }
        });
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    });
})();
</script>
@endif