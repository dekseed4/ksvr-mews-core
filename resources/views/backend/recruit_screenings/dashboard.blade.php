@extends('layouts.backend')

@section('title', 'แดชบอร์ดคัดกรองทหารใหม่')
@section('page_header', 'สรุปยอดคัดกรองทหารกองประจำการ')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    @php
        // 🔒 ตรวจสอบสิทธิ์ Admin เพื่อควบคุมการแสดงผลข้อมูลโรคประจำตัวและประวัติสุขภาพอื่นๆ
        $rawRoles = auth()->user()->roles ?? auth()->user()->role ?? [];
        $userRoles = is_string($rawRoles) ? json_decode($rawRoles, true) : (array) $rawRoles;
        $isAdmin = in_array('admin', $userRoles);
    @endphp

    <!-- 🌟 โซนตัวกรอง (Filter & View Selector) -->
    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
        <form method="GET" action="{{ route('backend.recruit_screenings.dashboard') }}" class="flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="flex gap-4 w-full md:w-auto">
                <!-- เลือกผลัด -->
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-500 mb-1">ผลัดที่รับรายงานตัว</label>
                    <select name="intake_batch" onchange="this.form.submit()" class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-sm font-medium">
                        @foreach($batches as $batch)
                            <option value="{{ $batch }}" {{ $selectedBatch == $batch ? 'selected' : '' }}>ผลัด {{ $batch }}</option>
                        @endforeach
                    </select>
                </div>
                <!-- เลือกมุมมอง (หน่วย/กองร้อย) -->
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-500 mb-1">มุมมองการจัดกลุ่ม</label>
                    <select name="view_by" onchange="this.form.submit()" class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-sm font-medium">
                        <option value="battalion" {{ $viewBy == 'battalion' ? 'selected' : '' }}>แยกตามหน่วย/กองพัน (Battalion)</option>
                        <option value="company" {{ $viewBy == 'company' ? 'selected' : '' }}>แยกตามกองร้อย/หน่วยฝึก (Company)</option>
                    </select>
                </div>
            </div>
            
            <!-- ปุ่มเรียกเปิด Modal สแกน และ ปุ่ม Export รายงาน -->
            <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                <button type="button" onclick="window.openExportMenuModal()" class="flex-1 md:flex-none px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold rounded-xl shadow-sm transition-colors text-center text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-export"></i> ส่งออกรายงาน (Excel/CSV)
                </button>
                <button type="button" onclick="openScanModal()" class="flex-1 md:flex-none px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl shadow-sm transition-colors text-center text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-qrcode"></i> เข้าสู่หน้าสแกนคัดกรอง
                </button>
            </div>
        </form>
    </div>

    <!-- 🌟 โซน Executive Summary (KPI Cards - กลุ่มสีอาการคัดกรอง) -->
    <div id="dashboard-kpi-zone" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        
        <!-- ความคืบหน้าภาพรวม -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 text-indigo-50 dark:text-slate-700/30 text-7xl -z-0 group-hover:scale-110 transition-transform"><i class="fa-solid fa-chart-pie"></i></div>
            <div class="relative z-10">
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 mb-1">ความคืบหน้าการคัดกรอง</p>
                <div class="flex items-end gap-2">
                    <h3 class="text-3xl font-black text-slate-800 dark:text-white">{{ $summary['progress_percent'] }}<span class="text-xl">%</span></h3>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2 mt-4 overflow-hidden">
                    <div class="bg-indigo-500 h-2 rounded-full transition-all duration-1000" style="width: {{ $summary['progress_percent'] }}%"></div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                    คัดกรองแล้ว <b class="text-indigo-600 dark:text-indigo-400">{{ number_format($summary['total_screened']) }}</b> จาก {{ number_format($summary['total_recruits']) }} นาย
                </p>
            </div>
        </div>

        <!-- ยอดคัดกรอง: สีเขียว -->
        <div onclick="window.viewPatientsByColor('green')" class="cursor-pointer hover:-translate-y-1 hover:shadow-md transition-all duration-300 bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 mb-1">กลุ่มปกติ (เขียว)</p>
                <h3 class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($summary['total_green']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-14 h-14 rounded-full bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-500 text-2xl shadow-inner">
                <i class="fa-solid fa-shield-virus"></i>
            </div>
        </div>

        <!-- ยอดคัดกรอง: สีเหลือง -->
        <div onclick="window.viewPatientsByColor('yellow')" class="cursor-pointer hover:-translate-y-1 hover:shadow-md transition-all duration-300 bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 mb-1">กลุ่มเสี่ยง (เหลือง)</p>
                <h3 class="text-3xl font-black text-amber-500">{{ number_format($summary['total_yellow']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="w-14 h-14 rounded-full bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-500 text-2xl shadow-inner">
                <i class="fa-solid fa-head-side-cough"></i>
            </div>
        </div>

        <!-- ยอดคัดกรอง: สีแดง -->
        <div onclick="window.viewPatientsByColor('red')" class="cursor-pointer hover:-translate-y-1 hover:shadow-md hover:ring-rose-300 transition-all duration-300 bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 flex items-center justify-between relative overflow-hidden ring-2 ring-transparent dark:hover:ring-rose-900">
            @if($summary['total_red'] > 0)
                <div class="absolute top-0 right-0 w-16 h-16 bg-rose-500 transform rotate-45 translate-x-8 -translate-y-8 animate-pulse"></div>
            @endif
            <div class="relative z-10">
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 mb-1">กลุ่มพบเชื้อ (แดง)</p>
                <h3 class="text-3xl font-black text-rose-600 dark:text-rose-400">{{ number_format($summary['total_red']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h3>
            </div>
            <div class="relative z-10 w-14 h-14 rounded-full bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center text-rose-500 text-2xl shadow-inner">
                <i class="fa-solid fa-virus-covid"></i>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 🔒 โซนรายงานข้อมูลโรคประจำตัว / ประวัติสุขภาพแรกรับ (แสดงเฉพาะ Admin เท่านั้น) -->
    <!-- ========================================================================= -->
    @if($isAdmin && isset($chronicSummary))
    <div id="chronic-disease-zone" class="bg-white dark:bg-slate-800 p-5 md:p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 animate-fade-in-up">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 mb-5 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-rose-50 dark:bg-rose-900/30 text-rose-500 flex items-center justify-center text-xl shrink-0 shadow-inner">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>
                <div>
                    <h3 class="text-base md:text-lg font-black text-slate-800 dark:text-white flex flex-wrap items-center gap-2">
                        สรุปข้อมูลโรคประจำตัว & ประวัติสุขภาพแรกรับ
                        <span class="bg-slate-800 text-white text-[10px] font-bold px-2 py-0.5 rounded-md">เฉพาะ Admin</span>
                        @if($chronicSummary['special_total'] > 0)
                            <span class="bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300 text-xs font-bold px-2.5 py-0.5 rounded-full border border-rose-200">
                                ต้องเฝ้าระวัง {{ number_format($chronicSummary['special_total']) }} นาย
                            </span>
                        @endif
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">คลิกที่การ์ดเพื่อดูรายละเอียดโรคประจำตัวรายบุคคล หรือกดส่งออกรายชื่อเป็นไฟล์ Excel/CSV</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button type="button" onclick="window.exportRecruitsDetailCSV('chronic_only')" 
                        class="w-full sm:w-auto px-4 py-2 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-file-csv text-amber-600"></i> Export เฉพาะผู้มีโรคประจำตัว/อื่นๆ ({{ $chronicSummary['special_total'] }})
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- การ์ด 1: ไม่มีโรคประจำตัว -->
            <div onclick="window.showChronicPatientsModal('none')" class="cursor-pointer p-5 rounded-2xl border-2 border-emerald-500/30 hover:border-emerald-500 bg-emerald-50/30 dark:bg-emerald-900/10 hover:bg-white dark:hover:bg-slate-800 transition-all flex items-center justify-between group shadow-sm">
                <div>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">1. ไม่มีโรคประจำตัว (ตรวจแล้ว)</span>
                    <h4 class="text-3xl font-black text-slate-800 dark:text-white mt-1">{{ number_format($chronicSummary['none_count']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h4>
                    <p class="text-[11px] text-slate-400 mt-1 group-hover:text-emerald-600 transition-colors"><i class="fa-solid fa-magnifying-glass mr-1"></i>คลิกดูรายชื่อ</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-2xl shadow-sm group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-heart-circle-check"></i>
                </div>
            </div>

            <!-- การ์ด 2: มีโรคประจำตัว -->
            <div onclick="window.showChronicPatientsModal('yes')" class="cursor-pointer p-5 rounded-2xl border-2 border-amber-500/40 hover:border-amber-500 bg-amber-50/40 dark:bg-amber-900/10 hover:bg-white dark:hover:bg-slate-800 transition-all flex items-center justify-between group shadow-sm">
                <div>
                    <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">2. มีโรคประจำตัว</span>
                    <h4 class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ number_format($chronicSummary['yes_count']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h4>
                    <p class="text-[11px] text-amber-600/80 mt-1 font-semibold"><i class="fa-solid fa-hand-pointer mr-1"></i>คลิกดูชื่อโรคประจำตัว</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-2xl shadow-sm group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>
            </div>

            <!-- การ์ด 3: อื่นๆ (แพ้ยา / เคยผ่าตัด / ดามเหล็ก) -->
            <div onclick="window.showChronicPatientsModal('other')" class="cursor-pointer p-5 rounded-2xl border-2 border-rose-500/40 hover:border-rose-500 bg-rose-50/40 dark:bg-rose-900/10 hover:bg-white dark:hover:bg-slate-800 transition-all flex items-center justify-between group shadow-sm">
                <div>
                    <span class="text-xs font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider">3. ประวัติสุขภาพอื่นๆ (ระบุ)</span>
                    <h4 class="text-3xl font-black text-rose-600 dark:text-rose-400 mt-1">{{ number_format($chronicSummary['other_count']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h4>
                    <p class="text-[11px] text-rose-600/80 mt-1 font-semibold"><i class="fa-solid fa-hand-pointer mr-1"></i>คลิกดูรายละเอียด (แพ้ยา/ผ่าตัด)</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-rose-500 text-white flex items-center justify-center text-2xl shadow-sm group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-file-medical"></i>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- 🌟 โซน Data Visualization (ApexCharts) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
        <div class="bg-white dark:bg-slate-800 p-4 md:p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 lg:col-span-1">
            <h3 class="text-base font-bold text-slate-800 dark:text-white mb-4"><i class="fa-solid fa-chart-pie text-indigo-500 mr-2"></i> สัดส่วนการพบอาการ</h3>
            <div id="triageDonutChart" data-series="[{{ $summary['total_green'] }}, {{ $summary['total_yellow'] }}, {{ $summary['total_red'] }}]" class="flex justify-center min-h-[300px]"></div>
        </div>
        
        <div class="bg-white dark:bg-slate-800 p-4 md:p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 lg:col-span-2">
            <h3 class="text-base font-bold text-slate-800 dark:text-white mb-4"><i class="fa-solid fa-chart-column text-indigo-500 mr-2"></i> สถิติการรายงานตัวแยกตามหน่วย</h3>
            <div id="unitBarChart" data-series="{{ json_encode($chartScreenedData) }}" class="min-h-[300px]"></div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 🏥 โซนสรุปข้อมูลสิทธิการรักษาทหารกองประจำการ (HosXP Treatment Rights) -->
    <!-- ========================================================================= -->
    @if(isset($treatmentRightsSummary))
    <div id="treatment-rights-zone" class="bg-white dark:bg-slate-800 p-5 md:p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mt-6 animate-fade-in-up">
        
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 mb-6 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl shrink-0 shadow-inner">
                    <i class="fa-solid fa-shield-heart"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base md:text-lg font-black text-slate-800 dark:text-white">
                            สรุปข้อมูลสิทธิการรักษาพยาบาล (HosXP Treatment Rights)
                        </h3>
                        <span class="bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-bold px-2.5 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                            ซิงค์สิทธิแล้ว {{ number_format($treatmentRightsSummary['synced_count']) }} / {{ number_format($treatmentRightsSummary['total_patients']) }} นาย ({{ $treatmentRightsSummary['synced_percent'] }}%)
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        คลิกที่การ์ดแต่ละสิทธิเพื่อดูรายชื่อพลทหาร หรือกดปุ่มด้านขวาเพื่อดึงข้อมูลสิทธิการรักษาล่าสุดจากฐานข้อมูล HosXP ทั้งผลัด
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button type="button" id="btn-sync-batch-rights" onclick="window.syncEntireBatchHosxp()" 
                        class="w-full sm:w-auto px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-2 shadow-sm">
                    <i class="fa-solid fa-rotate" id="icon-sync-batch-rights"></i> ซิงค์สิทธิการรักษา & HN ทั้งผลัด ({{ $selectedBatch }})
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($treatmentRightsSummary['rights_breakdown'] as $rightItem)
                @php
                    $isSynced = $rightItem['is_synced'];
                    $cardBorder = $isSynced 
                        ? 'border-slate-200 dark:border-slate-700 hover:border-emerald-400 dark:hover:border-emerald-500 bg-slate-50/70 dark:bg-slate-900/40' 
                        : 'border-amber-200 dark:border-amber-800/60 hover:border-amber-400 bg-amber-50/40 dark:bg-amber-900/10';
                    $badgeColor = $isSynced ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white';
                    $barColor = $isSynced ? 'bg-emerald-500' : 'bg-amber-400';
                @endphp

                <div onclick="window.showPatientsByRightModal({{ json_encode($rightItem['name']) }})" 
                     class="cursor-pointer p-4 rounded-2xl border {{ $cardBorder }} hover:bg-white dark:hover:bg-slate-800 hover:shadow-md hover:-translate-y-0.5 transition-all group">
                    <div class="flex justify-between items-start gap-3 mb-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid {{ $isSynced ? 'fa-circle-check text-emerald-500' : 'fa-clock text-amber-500' }} text-xs shrink-0"></i>
                                <h4 class="font-bold text-slate-800 dark:text-slate-100 text-sm truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" title="{{ $rightItem['name'] }}">
                                    {{ $rightItem['name'] }}
                                </h4>
                            </div>
                            @if($rightItem['code'])
                                <span class="text-[11px] text-slate-400 font-mono ml-4">รหัสสิทธิ HosXP: <b>{{ $rightItem['code'] }}</b></span>
                            @else
                                <span class="text-[11px] text-amber-600 dark:text-amber-400 ml-4">คลิกเพื่อดูรายชื่อผู้ที่รอซิงค์</span>
                            @endif
                        </div>

                        <div class="text-right shrink-0">
                            <span class="{{ $badgeColor }} text-xs font-black px-2.5 py-1 rounded-xl shadow-sm inline-block">
                                {{ number_format($rightItem['count']) }} นาย
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 mt-2">
                        <div class="flex-1 bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                            <div class="{{ $barColor }} h-1.5 rounded-full transition-all duration-700" style="width: {{ $rightItem['percentage'] }}%"></div>
                        </div>
                        <span class="text-xs font-black text-slate-600 dark:text-slate-300 w-12 text-right">{{ $rightItem['percentage'] }}%</span>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
    @endif
    
    <!-- 🌟 โซนสถิติภูมิลำเนา (มารายงานตัวแล้ว VS ยอดทั้งหมด) -->
    @if(isset($geographicStats) && $geographicStats->count() > 0)
    <div id="geographic-stats-zone" class="bg-white dark:bg-slate-800 p-4 md:p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 mt-6 animate-fade-in-up">

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-3">
            <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-map-location-dot text-indigo-500 text-xl"></i> 
                สถิติการมารายงานตัว แยกตามภูมิลำเนา
            </h3>
            <span class="bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-bold px-3 py-1 rounded-full border border-indigo-100 dark:border-indigo-800 shadow-sm">
                ผลัด {{ $selectedBatch }}
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($geographicStats as $province => $districts)
                @php
                    $provTotal = $districts->sum('total_recruits');
                    $provReported = $districts->sum('reported_count');
                    $isProvComplete = ($provReported >= $provTotal) && ($provTotal > 0);
                @endphp
                
                <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-5 border {{ $isProvComplete ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-900/20' : 'border-slate-100 dark:border-slate-700' }} hover:shadow-md transition-all duration-300">
                    
                    <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-4">
                        <h4 class="font-black text-slate-800 dark:text-slate-100 text-lg flex items-center gap-2">
                            {{ $province ?: 'ไม่ระบุจังหวัด' }}
                            @if($isProvComplete) <i class="fa-solid fa-circle-check text-emerald-500 text-sm"></i> @endif
                        </h4>
                        <div class="text-right">
                            <div class="{{ $isProvComplete ? 'bg-emerald-500' : 'bg-indigo-600' }} text-white text-xs font-black px-2.5 py-1.5 rounded-xl shadow-sm inline-block">
                                มาแล้ว {{ $provReported }} / {{ $provTotal }}
                            </div>
                        </div>
                    </div>
                    
                    <ul class="space-y-3.5">
                        @foreach($districts as $stat)
                            @php
                                $percent = $stat->total_recruits > 0 ? round(($stat->reported_count / $stat->total_recruits) * 100) : 0;
                                $isComplete = ($stat->reported_count >= $stat->total_recruits) && ($stat->total_recruits > 0);
                            @endphp
                            <li class="group">
                                <div class="flex justify-between items-end mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 rounded-full {{ $isComplete ? 'bg-emerald-400' : 'bg-slate-300' }} group-hover:bg-indigo-400 transition-colors"></div>
                                        <span class="text-sm font-bold {{ $isComplete ? 'text-emerald-700 dark:text-emerald-500' : 'text-slate-600 dark:text-slate-400' }} group-hover:text-indigo-600 transition-colors">
                                            อ.{{ $stat->district ?: 'ไม่ระบุ' }}
                                        </span>
                                    </div>
                                    <div class="text-xs font-black {{ $isComplete ? 'text-emerald-600' : 'text-slate-600 dark:text-slate-300' }}">
                                        {{ $stat->reported_count }} <span class="text-[10px] text-slate-400 font-medium">/ {{ $stat->total_recruits }}</span>
                                    </div>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                    <div class="{{ $isComplete ? 'bg-emerald-500' : 'bg-indigo-500' }} h-1.5 rounded-full transition-all duration-1000" style="width: {{ $percent }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                </div>
            @endforeach
        </div>

    </div>
    @endif

    <!-- 🌟 โซนตารางสรุปผล (Real-time Table — ซ่อนคอลัมน์โรคประจำตัวหากไม่ใช่ Admin) -->
    <div id="dashboard-table-zone" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden mt-6">
        
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-table-list text-indigo-500"></i> 
                {{ $isAdmin ? 'สรุปยอดคัดกรองและโรคประจำตัวแยกตามหน่วย' : 'สรุปยอดคัดกรองแยกตามหน่วย' }}
            </h3>
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <button type="button" onclick="window.print()" class="flex-1 sm:flex-none px-3.5 py-2 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-print"></i> พิมพ์
                </button>
                <button type="button" onclick="window.exportTableToCSV('สรุปยอดคัดกรองรายหน่วย_ผลัด_{{ str_replace('/', '-', $selectedBatch) }}.csv')" class="flex-1 sm:flex-none px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-table"></i> CSV สรุปรายหน่วย
                </button>
                <button type="button" onclick="window.openExportMenuModal()" class="flex-1 sm:flex-none px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-file-excel"></i> ดาวน์โหลดรายชื่อละเอียด (CSV/Excel)
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="summaryTable" class="w-full text-sm text-left whitespace-nowrap">
                <thead class="text-center font-bold">
                    <tr>
                        <th class="px-5 py-4 bg-slate-800 text-white w-14 border-b border-slate-700">ลำดับ</th>
                        <th class="px-6 py-4 bg-slate-800 text-white text-left border-b border-slate-700">หน่วย/กองร้อย</th>
                        <th class="px-4 py-4 bg-slate-700 text-white border-b border-slate-600">ยอดเปิดรับ (คน)</th>
                        <th class="px-4 py-4 bg-emerald-500 text-white shadow-inner border-b border-emerald-600">กลุ่มปกติ (เขียว)</th>
                        <th class="px-4 py-4 bg-amber-500 text-white shadow-inner border-b border-amber-600">กลุ่มเสี่ยง (เหลือง)</th>
                        <th class="px-4 py-4 bg-rose-500 text-white shadow-inner border-b border-rose-600">กลุ่มพบเชื้อ (แดง)</th>
                        @if($isAdmin)
                            <th class="px-4 py-4 bg-purple-600 text-white shadow-inner border-b border-purple-700">โรคประจำตัว / อื่นๆ</th>
                        @endif
                        <th class="px-4 py-4 bg-slate-700 text-white border-b border-slate-600">รายงานตัวแล้ว</th>
                        <th class="px-4 py-4 bg-indigo-600 text-white border-b border-indigo-700 shadow-inner">ร้อยละ (%)</th>
                        <th class="px-4 py-4 bg-slate-800 text-white border-b border-slate-700">รอรายงานตัว</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-center">
                    @php 
                        $sumTotal = 0; $sumGreen = 0; $sumYellow = 0; $sumRed = 0; $sumChronic = 0; $sumScreened = 0; $sumRemain = 0;
                    @endphp
                    
                    @forelse($dashboardData as $index => $row)
                        @php
                            $screenedTotal = $row->green_count + $row->yellow_count + $row->red_count;
                            $remaining = $row->total_recruits - $screenedTotal;
                            $percent = $row->total_recruits > 0 ? round(($screenedTotal / $row->total_recruits) * 100, 1) : 0;
                            $unitChronicCount = ($row->chronic_yes_count ?? 0) + ($row->chronic_other_count ?? 0);
                            
                            $sumTotal += $row->total_recruits;
                            $sumGreen += $row->green_count;
                            $sumYellow += $row->yellow_count;
                            $sumRed += $row->red_count;
                            $sumChronic += $unitChronicCount;
                            $sumScreened += $screenedTotal;
                            $sumRemain += $remaining;
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-5 py-4 font-bold text-slate-500">{{ $index + 1 }}</td>
                            <td class="px-6 py-4 text-left font-bold text-slate-800 dark:text-slate-200">{{ $row->unit_name }}</td>
                            <td class="px-4 py-4 font-black text-blue-600 dark:text-blue-400">{{ $row->total_recruits }}</td>
                            <td class="px-4 py-4 font-bold text-emerald-600 dark:text-emerald-400">{{ $row->green_count ?: '-' }}</td>
                            <td class="px-4 py-4 font-bold text-amber-500">{{ $row->yellow_count ?: '-' }}</td>
                            <td class="px-4 py-4 font-bold text-rose-600 dark:text-rose-400">{{ $row->red_count ?: '-' }}</td>
                            @if($isAdmin)
                                <td class="px-4 py-4 font-bold text-purple-600 dark:text-purple-400">{{ $unitChronicCount ?: '-' }}</td>
                            @endif
                            <td class="px-4 py-4 font-black text-slate-700 dark:text-slate-300">{{ $screenedTotal ?: '-' }}</td>
                            
                            <td class="px-4 py-3 align-middle">
                                <div class="flex flex-col items-center justify-center gap-1.5">
                                    <span class="font-black text-[13px] {{ $percent >= 100 ? 'text-emerald-600' : ($percent >= 80 ? 'text-indigo-600' : 'text-amber-500') }}">
                                        {{ number_format($percent, 1) }}%
                                    </span>
                                    <div class="w-16 bg-slate-200 dark:bg-slate-700 rounded-full h-1 overflow-hidden">
                                        <div class="{{ $percent >= 100 ? 'bg-emerald-500' : ($percent >= 80 ? 'bg-indigo-500' : 'bg-amber-500') }} h-1 rounded-full transition-all duration-1000" style="width: {{ $percent > 100 ? 100 : $percent }}%"></div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-4 font-black {{ $remaining > 0 ? 'text-slate-500' : 'text-emerald-500' }}">{{ $remaining }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? '10' : '9' }}" class="px-6 py-10 text-center text-slate-500">
                                <i class="fa-solid fa-folder-open text-3xl mb-3 opacity-50 block"></i>
                                ไม่พบข้อมูลทหารใหม่ในผลัดที่เลือก
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if(count($dashboardData) > 0)
                <tfoot class="bg-slate-100 dark:bg-slate-700/80 font-black text-center text-slate-800 dark:text-white border-t-2 border-slate-300 dark:border-slate-600">
                    @php
                        $sumPercent = $sumTotal > 0 ? round(($sumScreened / $sumTotal) * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td colspan="2" class="px-6 py-4 text-right">รวมทั้งสิ้น (มทบ.29)</td>
                        <td class="px-4 py-4 text-blue-600 dark:text-blue-400 text-lg">{{ $sumTotal }}</td>
                        <td class="px-4 py-4 text-emerald-600 dark:text-emerald-400 text-lg">{{ $sumGreen }}</td>
                        <td class="px-4 py-4 text-amber-500 text-lg">{{ $sumYellow }}</td>
                        <td class="px-4 py-4 text-rose-600 dark:text-rose-400 text-lg">{{ $sumRed }}</td>
                        @if($isAdmin)
                            <td class="px-4 py-4 text-purple-600 dark:text-purple-400 text-lg">{{ $sumChronic }}</td>
                        @endif
                        <td class="px-4 py-4 text-slate-700 dark:text-slate-200 text-lg">{{ $sumScreened }}</td>
                        <td class="px-4 py-4 text-indigo-600 dark:text-indigo-400 text-lg">{{ number_format($sumPercent, 1) }}%</td>
                        <td class="px-4 py-4 {{ $sumRemain > 0 ? 'text-slate-500' : 'text-emerald-500' }} text-lg">{{ $sumRemain }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<script type="module">
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Echo) {
            window.Echo.channel('recruit-dashboard')
                .listen('RecruitScreened', (e) => {
                    if (e.intakeBatch === '{{ $selectedBatch }}') {
                        
                        // เช็คก่อนว่าหน้าจอนี้กำลังเปิด Modal สแกน/ซักประวัติ ค้างอยู่หรือไม่
                        if (typeof Swal !== 'undefined' && !Swal.isVisible()) {
                            Swal.fire({
                                toast: true, 
                                position: 'top-end', 
                                icon: 'info', 
                                title: 'มียอดคัดกรองอัปเดตใหม่!', 
                                showConfirmButton: false, 
                                timer: 2000, 
                                timerProgressBar: true,
                            });
                        }

                        fetch(window.location.href)
                            .then(response => response.text())
                            .then(html => {
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');
                                
                                const tableZone = document.getElementById('dashboard-table-zone');
                                if(tableZone && doc.getElementById('dashboard-table-zone')) {
                                    tableZone.innerHTML = doc.getElementById('dashboard-table-zone').innerHTML;
                                }

                                const kpiZone = document.getElementById('dashboard-kpi-zone');
                                if(kpiZone && doc.getElementById('dashboard-kpi-zone')) {
                                    kpiZone.innerHTML = doc.getElementById('dashboard-kpi-zone').innerHTML;
                                }

                                const chronicZone = document.getElementById('chronic-disease-zone');
                                if(chronicZone && doc.getElementById('chronic-disease-zone')) {
                                    chronicZone.innerHTML = doc.getElementById('chronic-disease-zone').innerHTML;
                                }

                                const geoZone = document.getElementById('geographic-stats-zone');
                                const newGeoZone = doc.getElementById('geographic-stats-zone');
                                if(geoZone && newGeoZone) {
                                    geoZone.innerHTML = newGeoZone.innerHTML;
                                }

                                if (window.donutChart && doc.getElementById('triageDonutChart')) {
                                    const newDonutSeries = JSON.parse(doc.getElementById('triageDonutChart').getAttribute('data-series'));
                                    window.donutChart.updateSeries(newDonutSeries);
                                }

                                if (window.barChart && doc.getElementById('unitBarChart')) {
                                    const newBarSeries = JSON.parse(doc.getElementById('unitBarChart').getAttribute('data-series'));
                                    window.barChart.updateSeries([{ data: newBarSeries }]);
                                }
                            })
                            .catch(error => console.error('Error auto-updating dashboard:', error));
                    }
                });
        }
    });
</script>

<script>
    let html5QrCodeScanner = null;
    let isCameraScanning = false;

    @php
        // เตรียมตัวแปรใน PHP ก่อนส่งเข้า @json เพื่อป้องกันปัญหาวงเล็บซ้อนกันใน Blade Directive
        $emptyChronicMap = ['none' => [], 'yes' => [], 'other' => []];
        $safeChronicPatientsData = $isAdmin ? ($chronicPatientsMap ?? $emptyChronicMap) : $emptyChronicMap;
        $safePttypePatientsData  = $pttypePatientsMap ?? [];
        $safeExportRecruitsData  = $exportRecruitsList ?? [];
        $safeAllBatchPatientIds  = $allBatchPatientIds ?? [];
    @endphp

    // 🔒 สถานะสิทธิ์ Admin สำหรับควบคุมการแสดงผลและ Export ข้อมูลโรคประจำตัวในฝั่ง JavaScript
    const isAdminUser = @json($isAdmin);

    // 🌟 ข้อมูลรายชื่อพลทหารสำหรับแสดงใน Modal และส่งออก CSV/Excel
    const pttypePatientsData = @json($safePttypePatientsData);
    const chronicPatientsData = @json($safeChronicPatientsData);
    const exportRecruitsData = @json($safeExportRecruitsData);
    const allBatchPatientIds = @json($safeAllBatchPatientIds);
    const currentBatchLabel = '{{ str_replace("/", "-", $selectedBatch) }}';
    const patientProfileUrlTemplate = '{{ route("backend.patients.show", ["patient" => "__ID__"]) }}';

    // =========================================================================
    // 🩺 ฟังก์ชันเปิด Modal ดูรายชื่อพลทหารแยกตามสถานะโรคประจำตัว / ประวัติสุขภาพ (เฉพาะ Admin)
    // =========================================================================
    window.showChronicPatientsModal = function(type) {
        if (!isAdminUser) return;

        const list = chronicPatientsData[type] || [];
        const titles = {
            'none': { text: 'กลุ่มไม่มีโรคประจำตัว (ตรวจแล้ว)', color: '#10b981', icon: 'fa-heart-circle-check' },
            'yes':  { text: 'รายชื่อผู้มีโรคประจำตัว', color: '#f59e0b', icon: 'fa-heart-pulse' },
            'other':{ text: 'รายชื่อผู้มีประวัติสุขภาพอื่นๆ (แพ้ยา/ผ่าตัด)', color: '#f43f5e', icon: 'fa-file-medical' }
        };
        const cfg = titles[type] || titles['yes'];

        if (list.length === 0) {
            Swal.fire({
                icon: 'info',
                title: cfg.text,
                text: 'ยังไม่มีข้อมูลพลทหารในหมวดหมู่นี้ครับ',
                confirmButtonColor: '#0f172a',
                customClass: { popup: 'rounded-3xl' }
            });
            return;
        }

        let html = `<div class="max-h-[60vh] overflow-y-auto text-left mt-3 pr-1 space-y-2.5">`;
        list.forEach((pt, idx) => {
            const profileUrl = patientProfileUrlTemplate.replace('__ID__', pt.id);
            const colorBadge = pt.color_group === 'red' 
                ? 'bg-rose-100 text-rose-700 border-rose-200' 
                : (pt.color_group === 'yellow' ? 'bg-amber-100 text-amber-700 border-amber-200' : (pt.color_group === 'green' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'));

            html += `
                <div class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 hover:bg-white dark:hover:bg-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-all">
                    <div class="space-y-1">
                        <div class="font-bold text-slate-800 dark:text-white text-sm flex flex-wrap items-center gap-1.5">
                            <span>${idx + 1}. ${pt.full_name}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border ${colorBadge}">${pt.color_label}</span>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap gap-3">
                            <span class="font-mono"><i class="fa-regular fa-id-card mr-1"></i>${pt.cid}</span>
                            <span><i class="fa-solid fa-tent mr-1 text-indigo-500"></i>${pt.unit}</span>
                        </div>
                        ${type !== 'none' ? `
                            <div class="text-xs font-bold ${type === 'yes' ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-rose-700 bg-rose-50 border-rose-200'} px-2.5 py-1.5 rounded-lg border inline-block mt-1">
                                <i class="fa-solid ${cfg.icon} mr-1"></i> ${pt.detail}
                            </div>
                        ` : ''}
                    </div>
                    <a href="${profileUrl}" class="px-3.5 py-2 bg-slate-800 hover:bg-indigo-600 text-white rounded-xl text-xs font-bold transition-colors text-center shrink-0">
                        <i class="fa-solid fa-address-card mr-1"></i> ดูแฟ้มประวัติ
                    </a>
                </div>
            `;
        });
        html += `</div>`;

        Swal.fire({
            title: `<div class="text-base md:text-lg font-black flex items-center gap-2" style="color: ${cfg.color}"><i class="fa-solid ${cfg.icon}"></i> ${cfg.text} (${list.length} นาย)</div>`,
            html: html,
            width: '42rem',
            showCloseButton: true,
            showDenyButton: type !== 'none',
            confirmButtonText: 'ปิดหน้าต่าง',
            denyButtonText: '<i class="fa-solid fa-file-csv mr-1"></i> ดาวน์โหลดรายชื่อชุดนี้ (CSV)',
            customClass: {
                popup: 'bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-100 dark:border-slate-700',
                confirmButton: 'bg-slate-800 text-white font-bold rounded-xl px-5 py-2.5 text-xs',
                denyButton: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl px-5 py-2.5 text-xs'
            }
        }).then((res) => {
            if (res.isDenied) {
                window.exportRecruitsDetailCSV('chronic_only');
            }
        });
    };

    // =========================================================================
    // 📥 ระบบเมนูส่งออกรายงาน Excel/CSV รายบุคคล (ซ่อนตัวเลือกโรคประจำตัวหากไม่ใช่ Admin)
    // =========================================================================
    window.openExportMenuModal = function() {
        const chronicOptionHtml = isAdminUser ? `
            <button type="button" onclick="Swal.close(); window.exportRecruitsDetailCSV('chronic_only');" class="w-full p-4 rounded-2xl border border-slate-200 hover:border-amber-500 hover:bg-amber-50/40 flex items-center justify-between transition-all group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg"><i class="fa-solid fa-heart-pulse"></i></div>
                    <div>
                        <div class="font-bold text-slate-800 text-sm group-hover:text-amber-700">เฉพาะผู้ที่มีโรคประจำตัว / ประวัติสุขภาพอื่นๆ (Admin)</div>
                        <div class="text-xs text-slate-500">คัดเฉพาะทหารที่ระบุว่ามีโรคประจำตัว หรือประวัติแพ้ยา/ผ่าตัด</div>
                    </div>
                </div>
                <i class="fa-solid fa-download text-slate-400 group-hover:text-amber-600"></i>
            </button>
        ` : '';

        Swal.fire({
            title: '<div class="text-lg font-black text-slate-800 dark:text-white"><i class="fa-solid fa-file-export text-indigo-500 mr-2"></i>เลือกรูปแบบรายงานที่ต้องการส่งออก</div>',
            html: `
                <p class="text-xs text-slate-500 mb-4">ไฟล์ CSV เข้ารหัสภาษาไทย (UTF-8 BOM) สามารถดับเบิลคลิกเปิดในโปรแกรม Microsoft Excel ได้ทันทีครับ</p>
                <div class="space-y-2.5 text-left">
                    <button type="button" onclick="Swal.close(); window.exportRecruitsDetailCSV('all');" class="w-full p-4 rounded-2xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/40 flex items-center justify-between transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-lg"><i class="fa-solid fa-users"></i></div>
                            <div>
                                <div class="font-bold text-slate-800 text-sm group-hover:text-indigo-700">รายชื่อพลทหารทั้งหมดในผลัด ({{ $selectedBatch }})</div>
                                <div class="text-xs text-slate-500">รวมข้อมูลกลุ่มสีคัดกรอง, อาการ และสิทธิการรักษาครบทุกคน</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-download text-slate-400 group-hover:text-indigo-600"></i>
                    </button>

                    ${chronicOptionHtml}

                    <button type="button" onclick="Swal.close(); window.exportRecruitsDetailCSV('risk_only');" class="w-full p-4 rounded-2xl border border-slate-200 hover:border-rose-500 hover:bg-rose-50/40 flex items-center justify-between transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg"><i class="fa-solid fa-virus-covid"></i></div>
                            <div>
                                <div class="font-bold text-slate-800 text-sm group-hover:text-rose-700">เฉพาะกลุ่มเสี่ยง (สีเหลือง) และกลุ่มพบเชื้อ (สีแดง)</div>
                                <div class="text-xs text-slate-500">สำหรับส่งต่อทีมแพทย์ระบาดวิทยาและห้องฉุกเฉิน</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-download text-slate-400 group-hover:text-rose-600"></i>
                    </button>

                    <button type="button" onclick="Swal.close(); window.exportTableToCSV('สรุปยอดคัดกรองรายหน่วย_ผลัด_' + currentBatchLabel + '.csv');" class="w-full p-4 rounded-2xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/40 flex items-center justify-between transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg"><i class="fa-solid fa-table"></i></div>
                            <div>
                                <div class="font-bold text-slate-800 text-sm group-hover:text-emerald-700">ตารางสรุปยอดรวมแยกตามหน่วยฝึก</div>
                                <div class="text-xs text-slate-500">ตารางสรุปจำนวนและร้อยละความคืบหน้าระดับหน่วยฝึก/กองพัน</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-download text-slate-400 group-hover:text-emerald-600"></i>
                    </button>
                </div>
            `,
            width: '38rem',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: { popup: 'bg-white dark:bg-slate-800 rounded-3xl p-6' }
        });
    };

    window.exportRecruitsDetailCSV = function(filterMode = 'all') {
        if (filterMode === 'chronic_only' && !isAdminUser) return;

        let filtered = exportRecruitsData;
        let filenamePrefix = 'รายงานคัดกรองทหารใหม่ทั้งหมด';

        if (filterMode === 'chronic_only') {
            filtered = exportRecruitsData.filter(r => r.chronic_status === 'yes' || r.chronic_status === 'other');
            filenamePrefix = 'รายชื่อทหารมีโรคประจำตัวและประวัติสุขภาพ';
        } else if (filterMode === 'risk_only') {
            filtered = exportRecruitsData.filter(r => r.color_group === 'yellow' || r.color_group === 'red');
            filenamePrefix = 'รายชื่อทหารกลุ่มเสี่ยงสีเหลืองและสีแดง';
        }

        if (!filtered || filtered.length === 0) {
            Swal.fire({ icon: 'info', title: 'ไม่มีข้อมูลตามเงื่อนไข', text: 'ยังไม่มีรายชื่อทหารที่ตรงกับหมวดหมู่ที่คุณเลือกส่งออกครับ' });
            return;
        }

        const headers = [
            'ลำดับ',
            'ยศ ชื่อ-นามสกุล',
            'เลขประจำตัวประชาชน',
            'รหัส HN',
            'สิทธิการรักษา (HosXP)',
            'หน่วยฝึกปัจจุบัน',
            'สังกัดปลายทาง',
            'อำเภอ',
            'จังหวัด',
            'สถานะรายงานตัว',
            'กลุ่มสีคัดกรอง',
            'อาการ/ผลตรวจที่พบ'
        ];

        // 🔒 เพิ่มคอลัมน์โรคประจำตัวในไฟล์ CSV เฉพาะกรณีที่ผู้ส่งออกเป็น Admin เท่านั้น
        if (isAdminUser) {
            headers.push('สถานะโรคประจำตัว', 'รายละเอียดโรคประจำตัว/ประวัติสุขภาพ');
        }
        headers.push('วันเวลาที่คัดกรอง');

        const escapeCsv = (val) => {
            const s = String(val ?? '').replace(/(\r\n|\n|\r)/gm, ' ').trim().replace(/"/g, '""');
            return `"${s}"`;
        };

        let csvRows = [headers.map(escapeCsv).join(',')];

        filtered.forEach((item, index) => {
            const safeCid = item.cid && item.cid !== '-' ? `="${item.cid}"` : `"-"` ;
            const rowData = [
                escapeCsv(index + 1),
                escapeCsv(item.full_name),
                safeCid,
                escapeCsv(item.hn),
                escapeCsv(item.pttype),
                escapeCsv(item.company),
                escapeCsv(item.battalion),
                escapeCsv(item.district),
                escapeCsv(item.province),
                escapeCsv(item.is_screened),
                escapeCsv(item.color_label),
                escapeCsv(item.symptoms_note)
            ];

            if (isAdminUser) {
                rowData.push(escapeCsv(item.chronic_label), escapeCsv(item.chronic_detail));
            }
            rowData.push(escapeCsv(item.screened_time));

            csvRows.push(rowData.join(','));
        });

        const csvFile = new Blob(["\uFEFF" + csvRows.join("\n")], { type: "text/csv;charset=utf-8;" });
        const downloadLink = document.createElement("a");
        downloadLink.download = `${filenamePrefix}_ผลัด_${currentBatchLabel}.csv`;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);

        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: `ดาวน์โหลดไฟล์สำเร็จ (${filtered.length} รายการ)`,
            showConfirmButton: false, timer: 2500
        });
    };

    // 🏥 ฟังก์ชันเปิดดูรายชื่อพลทหารแยกตามสิทธิการรักษา
    window.showPatientsByRightModal = function(rightName) {
        const list = pttypePatientsData[rightName] || [];
        if (list.length === 0) {
            Swal.fire({ icon: 'info', title: rightName, text: 'ไม่พบรายชื่อในหมวดหมู่นี้ครับ' });
            return;
        }

        let html = `<div class="max-h-[60vh] overflow-y-auto text-left mt-3 pr-1 space-y-2.5">`;
        list.forEach((pt, idx) => {
            const profileUrl = patientProfileUrlTemplate.replace('__ID__', pt.id);
            const hasHn = pt.hn && pt.hn !== 'ยังไม่มี HN';

            html += `
                <div class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 hover:bg-white dark:hover:bg-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-all">
                    <div>
                        <div class="font-bold text-slate-800 dark:text-white text-sm">
                            ${idx + 1}. ${pt.full_name}
                            <span class="ml-1.5 px-2 py-0.5 rounded text-[11px] font-bold ${hasHn ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'}">
                                HN: ${pt.hn}
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex flex-wrap gap-3">
                            <span class="font-mono"><i class="fa-regular fa-id-card mr-1"></i>${pt.cid}</span>
                            <span><i class="fa-solid fa-tent mr-1 text-indigo-500"></i>${pt.unit}</span>
                        </div>
                    </div>
                    <a href="${profileUrl}" class="px-3.5 py-2 bg-slate-800 hover:bg-indigo-600 text-white rounded-xl text-xs font-bold transition-colors text-center shrink-0">
                        <i class="fa-solid fa-address-card mr-1"></i> ดูแฟ้มประวัติ
                    </a>
                </div>
            `;
        });
        html += `</div>`;

        Swal.fire({
            title: `<div class="text-base md:text-lg font-black text-slate-800 dark:text-white flex items-center gap-2"><i class="fa-solid fa-shield-heart text-emerald-500"></i> ${rightName} (${list.length} นาย)</div>`,
            html: html,
            width: '40rem',
            showCloseButton: true,
            confirmButtonText: 'ปิดหน้าต่าง',
            customClass: {
                popup: 'bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-100 dark:border-slate-700',
                confirmButton: 'bg-slate-800 text-white font-bold rounded-xl px-6 py-2.5 text-sm'
            }
        });
    };

    // 🔄 ฟังก์ชันซิงค์สิทธิการรักษา & HN จาก HosXP ทั้งผลัดในคลิกเดียว
    window.syncEntireBatchHosxp = function(forceSync = false, updateName = false, customIds = null) {
        const idsToSync = customIds || allBatchPatientIds;
        if (!idsToSync || idsToSync.length === 0) {
            Swal.fire({ icon: 'info', title: 'ไม่มีรายชื่อ', text: 'ไม่พบรายชื่อทหารในผลัดนี้ครับ' });
            return;
        }

        const btn = document.getElementById('btn-sync-batch-rights');
        const icon = document.getElementById('icon-sync-batch-rights');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        Swal.fire({
            title: 'กำลังซิงค์สิทธิการรักษา & HN จาก HosXP...',
            html: `ระบบกำลังดึงข้อมูลสิทธิการรักษาของทหารผลัด <b>{{ $selectedBatch }}</b> จำนวน <b>${idsToSync.length}</b> นาย`,
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); },
            customClass: { popup: 'rounded-3xl' }
        });

        fetch("{{ route('backend.patients.bulk_sync_hosxp') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                patient_ids: idsToSync,
                force_sync: forceSync,
                update_name: updateName
            })
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');

            if (data.status !== 'success') {
                Swal.fire({ icon: 'error', title: 'ซิงค์ไม่สำเร็จ', text: data.message || 'เกิดข้อผิดพลาด' });
                return;
            }

            if (data.mismatched && data.mismatched.length > 0 && data.is_registrar_admin) {
                const mismatchIds = data.mismatched.map(m => String(m.id));
                let listHtml = `<div class="text-left text-xs space-y-2 mt-3 max-h-[40vh] overflow-y-auto pr-1">`;
                data.mismatched.forEach(m => {
                    listHtml += `
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl">
                            <div class="font-bold text-slate-800">ปชช: <span class="font-mono">${m.cid}</span> <span class="text-blue-600 ml-1">(HN: ${m.hn})</span></div>
                            <div class="text-emerald-700 font-semibold mt-0.5">สิทธิ HosXP: ${m.pttype_name || '-'}</div>
                            <div class="text-slate-600 mt-1">ระบบ MEWS: <b>${m.mews_name}</b> | ระบบ HosXP: <b class="text-indigo-700">${m.hosxp_name}</b></div>
                        </div>
                    `;
                });
                listHtml += `</div>`;

                Swal.fire({
                    icon: 'warning',
                    title: `ซิงค์สำเร็จ ${data.synced_count} ราย | ชื่อไม่ตรง ${data.mismatched.length} ราย`,
                    html: `พบเลข ปชช. ตรงกันแต่ชื่อไม่ตรงกับ HosXP จำนวน <b>${data.mismatched.length}</b> ราย:${listHtml}`,
                    width: '38rem',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'ผูก HN/สิทธิ + อัปเดตชื่อตาม HosXP',
                    denyButtonText: 'ผูกเฉพาะ HN และสิทธิ (คงชื่อเดิม)',
                    cancelButtonText: 'ข้ามไปก่อน',
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'bg-indigo-600 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        denyButton: 'bg-emerald-600 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        cancelButton: 'bg-slate-200 text-slate-700 font-bold rounded-xl px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.syncEntireBatchHosxp(true, true, mismatchIds);
                    } else if (result.isDenied) {
                        window.syncEntireBatchHosxp(true, false, mismatchIds);
                    } else {
                        window.location.reload();
                    }
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'ซิงค์สิทธิการรักษา & HN สำเร็จ!',
                    html: `
                        <div class="text-sm text-slate-600 space-y-1 mt-2">
                            <p>✅ ซิงค์ข้อมูลสำเร็จ: <b class="text-emerald-600 text-base">${data.synced_count}</b> นาย</p>
                            <p>🔍 ยังไม่พบประวัติใน HosXP: <b>${data.not_found_count}</b> นาย</p>
                        </div>
                    `,
                    confirmButtonText: 'รีเฟรชหน้าแดชบอร์ด',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'bg-emerald-600 text-white rounded-xl px-6 py-2.5 font-bold' }
                }).then(() => {
                    window.location.reload();
                });
            }
        })
        .catch(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
            Swal.fire({ icon: 'error', title: 'การเชื่อมต่อขัดข้อง', text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์เพื่อซิงค์ข้อมูลได้' });
        });
    };

    function playScannerBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1200, ctx.currentTime);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
        } catch (e) {}
    }

    function isValidThaiCID(cid) {
        if (!/^[0-9]{13}$/.test(cid)) return false;
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(cid.charAt(i), 10) * (13 - i);
        }
        const checkDigit = (11 - (sum % 11)) % 10;
        return checkDigit === parseInt(cid.charAt(12), 10);
    }

    window.stopCameraScanner = async function() {
        if (html5QrCodeScanner && isCameraScanning) {
            try {
                await html5QrCodeScanner.stop();
                html5QrCodeScanner.clear();
            } catch (err) {
                console.warn('Stop camera warning:', err);
            }
        }
        isCameraScanning = false;
        const camWrapper = document.getElementById('camera-scanner-wrapper');
        const btnToggleCam = document.getElementById('btn-toggle-camera');
        if (camWrapper) camWrapper.classList.add('hidden');
        if (btnToggleCam) {
            btnToggleCam.innerHTML = '<i class="fa-solid fa-camera text-base"></i> ใช้กล้องมือถือสแกนบาร์โค้ด';
            btnToggleCam.classList.remove('bg-rose-50', 'text-rose-600', 'border-rose-200');
            btnToggleCam.classList.add('bg-indigo-50', 'text-indigo-600', 'border-indigo-200');
        }
    };

    window.toggleCameraScanner = async function() {
        const camWrapper = document.getElementById('camera-scanner-wrapper');
        const btnToggleCam = document.getElementById('btn-toggle-camera');
        const statusText = document.getElementById('camera-status-text');

        if (isCameraScanning) {
            await window.stopCameraScanner();
            return;
        }

        if (typeof Html5Qrcode === 'undefined') {
            Swal.showValidationMessage('ไม่สามารถโหลดไลบรารีกล้องได้ กรุณาตรวจสอบอินเทอร์เน็ต');
            return;
        }

        camWrapper.classList.remove('hidden');
        btnToggleCam.innerHTML = '<i class="fa-solid fa-video-slash text-base"></i> ปิดกล้องสแกน';
        btnToggleCam.classList.remove('bg-indigo-50', 'text-indigo-600', 'border-indigo-200');
        btnToggleCam.classList.add('bg-rose-50', 'text-rose-600', 'border-rose-200');
        if (statusText) statusText.innerText = 'กำลังเปิดกล้องหลัง... กรุณาหันบาร์โค้ดบัตร ปชช. แนวนอนให้อยู่ในกรอบ';

        const formatsToSupport = [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.QR_CODE
        ];

        html5QrCodeScanner = new Html5Qrcode("barcode-reader-box", { formatsToSupport: formatsToSupport, verbose: false });

        const config = {
            fps: 15,
            qrbox: { width: 260, height: 100 },
            aspectRatio: 1.777778,
            videoConstraints: {
                facingMode: "environment",
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        try {
            isCameraScanning = true;
            await html5QrCodeScanner.start(
                { facingMode: "environment" },
                config,
                async (decodedText) => {
                    const cleanDigits = decodedText.replace(/[^0-9]/g, '');

                    if (cleanDigits.length === 13 && isValidThaiCID(cleanDigits)) {
                        playScannerBeep();
                        const input = document.getElementById('swal-cid-input');
                        if (input) input.value = cleanDigits;

                        await window.stopCameraScanner();
                        Swal.clickConfirm();
                    } else if (cleanDigits.length === 13) {
                        playScannerBeep();
                        const input = document.getElementById('swal-cid-input');
                        if (input) input.value = cleanDigits;
                        await window.stopCameraScanner();
                        Swal.clickConfirm();
                    }
                },
                (errorMessage) => {}
            );
            if (statusText) statusText.innerText = 'วางบาร์โค้ดบนบัตรประชาชน (แนวนอน) ให้ขนานกับกรอบสี่เหลี่ยม';
        } catch (err) {
            isCameraScanning = false;
            camWrapper.classList.add('hidden');
            btnToggleCam.innerHTML = '<i class="fa-solid fa-camera text-base"></i> ใช้กล้องมือถือสแกนบาร์โค้ด';
            Swal.showValidationMessage('ไม่สามารถเข้าถึงกล้องได้ กรุณากดอนุญาตสิทธิ์การใช้กล้อง (Allow Camera) บนเบราว์เซอร์ครับ');
        }
    };

    window.scanBarcodeFromImage = async function(inputElement) {
        if (!inputElement.files || inputElement.files.length === 0) return;
        const imageFile = inputElement.files[0];

        if (isCameraScanning) {
            await window.stopCameraScanner();
        }

        const tempScanner = new Html5Qrcode("barcode-reader-box");
        try {
            const decodedText = await tempScanner.scanFile(imageFile, true);
            const cleanDigits = decodedText.replace(/[^0-9]/g, '');
            if (cleanDigits.length === 13) {
                playScannerBeep();
                document.getElementById('swal-cid-input').value = cleanDigits;
                tempScanner.clear();
                Swal.clickConfirm();
            } else {
                Swal.showValidationMessage('อ่านค่าได้: ' + decodedText + ' (ไม่ใช่เลข 13 หลัก)');
            }
        } catch (err) {
            Swal.showValidationMessage('ไม่พบบาร์โค้ดที่ชัดเจนในรูปภาพ กรุณาถ่ายให้เห็นบาร์โค้ดชัดๆ หรือพิมพ์ตัวเลขครับ');
        }
    };

    window.openScanModal = function() {
        Swal.fire({
            title: '<div class="text-xl md:text-2xl font-black text-slate-800 dark:text-white"><i class="fa-solid fa-barcode text-indigo-500 mr-2"></i> สแกนบัตรประชาชน</div>',
            html: `
                <p class="text-xs sm:text-sm text-slate-500 mb-3">ใช้เครื่องยิงบาร์โค้ด, กล้องมือถือ หรือพิมพ์เลข 13 หลัก</p>
                
                <div class="flex flex-wrap gap-2 justify-center mb-3">
                    <button type="button" id="btn-toggle-camera" onclick="window.toggleCameraScanner()" 
                            class="flex-1 py-2.5 px-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-200 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 shadow-sm">
                        <i class="fa-solid fa-camera text-base"></i> ใช้กล้องมือถือสแกนบาร์โค้ด
                    </button>

                    <label class="cursor-pointer py-2.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-1.5 shadow-sm" title="ถ่ายรูปบาร์โค้ดกรณีกล้องไม่โฟกัส">
                        <i class="fa-solid fa-image"></i>
                        <span class="hidden sm:inline">สแกนจากภาพถ่าย</span>
                        <input type="file" accept="image/*" capture="environment" class="hidden" onchange="window.scanBarcodeFromImage(this)">
                    </label>
                </div>

                <div id="camera-scanner-wrapper" class="hidden mb-4">
                    <div id="barcode-reader-box" class="w-full rounded-2xl overflow-hidden border-2 border-indigo-500 bg-slate-900 shadow-inner"></div>
                    <p id="camera-status-text" class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold mt-1.5"></p>
                </div>

                <div class="relative">
                    <input type="text" id="swal-cid-input" inputmode="numeric" pattern="[0-9]*" 
                           class="w-full text-center text-xl md:text-2xl font-bold tracking-[0.2em] p-4 bg-slate-50 dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-700 rounded-xl focus:ring-4 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none text-slate-800 dark:text-white" 
                           placeholder="เลข 13 หลัก" maxlength="13">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'ค้นหา',
            cancelButtonText: 'ปิด',
            buttonsStyling: false,
            customClass: {
                popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-2xl p-6 border border-slate-100 dark:border-slate-700 w-full max-w-md',
                confirmButton: 'w-full md:w-auto mt-4 md:mt-0 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-6 py-3 mx-2 outline-none',
                cancelButton: 'w-full md:w-auto mt-2 md:mt-0 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl px-6 py-3 mx-2 outline-none'
            },
            didOpen: () => {
                const input = document.getElementById('swal-cid-input');
                if (window.innerWidth >= 768) {
                    setTimeout(() => input.focus(), 300);
                }
                input.addEventListener('keyup', (e) => {
                    if (e.key === 'Enter' && input.value.replace(/[^0-9]/g, '').length === 13) {
                        Swal.clickConfirm();
                    }
                });
            },
            willClose: () => {
                window.stopCameraScanner();
            },
            preConfirm: async () => {
                await window.stopCameraScanner();
                const cid = document.getElementById('swal-cid-input').value.replace(/[^0-9]/g, '');
                if (cid.length !== 13) {
                    Swal.showValidationMessage('กรุณาระบุเลข ปชช. ให้ครบ 13 หลักครับ');
                    return false;
                }
                return window.fetchPatientData(cid);
            }
        });
    }

    window.fetchPatientData = async function(cid) {
        Swal.showLoading();
        try {
            const formData = new FormData();
            formData.append('cid', cid);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            const response = await fetch('{{ route("backend.recruit_screenings.search") }}', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'error') {
                Swal.fire({
                    icon: 'error', title: 'ผิดพลาด', text: result.message,
                    confirmButtonColor: '#0f172a', confirmButtonText: 'ลองใหม่'
                }).then(() => setTimeout(window.openScanModal, 300));
                return;
            }

            window.showColorSelectionModal(result.data);

        } catch (error) {
            Swal.showValidationMessage('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        }
    }

    window.tempChronicStatus = 'none';
    window.tempChronicDetail = '';

    window.toggleScreeningChronicInput = function(status) {
        const wrapper = document.getElementById('screening-chronic-detail-wrapper');
        const input = document.getElementById('screening-chronic-detail-input');
        const label = document.getElementById('screening-chronic-detail-label');

        const cardConfigs = {
            'none': {
                cardId: 'chronic-card-none',
                checkId: 'chronic-check-none',
                activeClass: 'flex flex-col items-center justify-center p-4 bg-emerald-500 text-white border-2 border-emerald-500 rounded-xl cursor-pointer transition-all group shadow-md relative',
                inactiveClass: 'flex flex-col items-center justify-center p-4 bg-white dark:bg-slate-800 text-emerald-600 border-2 border-emerald-500 rounded-xl hover:bg-emerald-50 dark:hover:bg-slate-700 cursor-pointer transition-all group relative'
            },
            'yes': {
                cardId: 'chronic-card-yes',
                checkId: 'chronic-check-yes',
                activeClass: 'flex flex-col items-center justify-center p-4 bg-amber-500 text-white border-2 border-amber-500 rounded-xl cursor-pointer transition-all group shadow-md relative',
                inactiveClass: 'flex flex-col items-center justify-center p-4 bg-white dark:bg-slate-800 text-amber-500 border-2 border-amber-500 rounded-xl hover:bg-amber-50 dark:hover:bg-slate-700 cursor-pointer transition-all group relative'
            },
            'other': {
                cardId: 'chronic-card-other',
                checkId: 'chronic-check-other',
                activeClass: 'flex flex-col items-center justify-center p-4 bg-rose-500 text-white border-2 border-rose-500 rounded-xl cursor-pointer transition-all group shadow-md relative',
                inactiveClass: 'flex flex-col items-center justify-center p-4 bg-white dark:bg-slate-800 text-rose-500 border-2 border-rose-500 rounded-xl hover:bg-rose-50 dark:hover:bg-slate-700 cursor-pointer transition-all group relative'
            }
        };

        Object.keys(cardConfigs).forEach(key => {
            const cfg = cardConfigs[key];
            const cardEl = document.getElementById(cfg.cardId);
            const checkEl = document.getElementById(cfg.checkId);
            if (cardEl) {
                cardEl.className = (key === status) ? cfg.activeClass : cfg.inactiveClass;
            }
            if (checkEl) {
                if (key === status) {
                    checkEl.classList.remove('hidden');
                } else {
                    checkEl.classList.add('hidden');
                }
            }
        });

        if (!wrapper || !input) return;

        if (status === 'yes') {
            wrapper.classList.remove('hidden');
            if (label) label.innerHTML = '<i class="fa-solid fa-heart-pulse text-amber-500 mr-1"></i> ระบุโรคประจำตัว <span class="text-rose-500">*</span>';
            input.placeholder = 'เช่น หอบหืด, ลมชัก, ความดันโลหิตสูง, โรคหัวใจ...';
            setTimeout(() => input.focus(), 100);
        } else if (status === 'other') {
            wrapper.classList.remove('hidden');
            if (label) label.innerHTML = '<i class="fa-solid fa-notes-medical text-rose-500 mr-1"></i> ระบุข้อมูลสุขภาพอื่นๆ <span class="text-rose-500">*</span>';
            input.placeholder = 'เช่น แพ้ยา/อาหาร, เคยผ่าตัด/ดามเหล็ก, ประวัติจิตเวช...';
            setTimeout(() => input.focus(), 100);
        } else {
            wrapper.classList.add('hidden');
            input.value = '';
        }
    };

    window.showColorSelectionModal = function(patient) {
        const initStatus = patient.chronic_disease_status || 'none';
        const initDetail = patient.chronic_disease_detail || '';
        const showDetailBox = (initStatus === 'yes' || initStatus === 'other');

        Swal.fire({
            title: 'ผลการค้นหา & ซักประวัติแรกรับ',
            html: `
                <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl text-left mb-5 border border-slate-100 dark:border-slate-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="bg-indigo-100 text-indigo-700 text-[10px] px-2 py-1 rounded-full font-bold">ผลัด ${patient.batch}</span>
                        <span class="bg-emerald-100 text-emerald-700 text-[10px] px-2 py-1 rounded-full font-bold"><i class="fa-solid fa-shield-heart mr-1"></i>${patient.pttype_name || 'รอซิงค์สิทธิ'}</span>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 dark:text-white mt-2">${patient.fullname}</h3>
                    <p class="text-sm text-slate-500 mt-1"><b>ปชช:</b> ${patient.cid}</p>
                    <p class="text-sm text-slate-500"><b>หน่วย:</b> ${patient.unit}</p>
                </div>

                <!-- 🩺 ข้อ 1: ข้อมูลโรคประจำตัว / ประวัติสุขภาพ -->
                <div class="mb-5">
                    <h4 class="font-bold text-slate-700 dark:text-slate-300 mb-3 text-sm text-left flex items-center gap-1.5">
                        <i class="fa-solid fa-notes-medical text-indigo-500"></i> 1. ข้อมูลโรคประจำตัว / ประวัติสุขภาพ
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <label id="chronic-card-none" class="flex flex-col items-center justify-center p-4 border-2 border-emerald-500 rounded-xl cursor-pointer transition-all group relative">
                            <input type="radio" name="screening_chronic_status" value="none" class="hidden" ${initStatus === 'none' ? 'checked' : ''} onchange="window.toggleScreeningChronicInput('none')">
                            <span id="chronic-check-none" class="hidden absolute top-2 right-2 text-xs"><i class="fa-solid fa-circle-check"></i></span>
                            <i class="fa-solid fa-heart-circle-check text-3xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="font-black text-sm">ไม่มีโรคประจำตัว</span>
                        </label>

                        <label id="chronic-card-yes" class="flex flex-col items-center justify-center p-4 border-2 border-amber-500 rounded-xl cursor-pointer transition-all group relative">
                            <input type="radio" name="screening_chronic_status" value="yes" class="hidden" ${initStatus === 'yes' ? 'checked' : ''} onchange="window.toggleScreeningChronicInput('yes')">
                            <span id="chronic-check-yes" class="hidden absolute top-2 right-2 text-xs"><i class="fa-solid fa-circle-check"></i></span>
                            <i class="fa-solid fa-heart-pulse text-3xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="font-black text-sm">มีโรคประจำตัว</span>
                        </label>

                        <label id="chronic-card-other" class="flex flex-col items-center justify-center p-4 border-2 border-rose-500 rounded-xl cursor-pointer transition-all group relative">
                            <input type="radio" name="screening_chronic_status" value="other" class="hidden" ${initStatus === 'other' ? 'checked' : ''} onchange="window.toggleScreeningChronicInput('other')">
                            <span id="chronic-check-other" class="hidden absolute top-2 right-2 text-xs"><i class="fa-solid fa-circle-check"></i></span>
                            <i class="fa-solid fa-file-medical text-3xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="font-black text-sm">อื่นๆ (ระบุ)</span>
                        </label>
                    </div>

                    <div id="screening-chronic-detail-wrapper" class="${showDetailBox ? '' : 'hidden'} mt-3 p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 text-left">
                        <label id="screening-chronic-detail-label" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            <i class="fa-solid fa-heart-pulse text-amber-500 mr-1"></i> ระบุรายละเอียด <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="screening-chronic-detail-input" value="${initDetail}" 
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 outline-none" 
                               placeholder="ระบุรายละเอียดโรคประจำตัว หรือข้อมูลสุขภาพ...">
                    </div>
                </div>
                
                <!-- 🚦 ข้อ 2: เลือกกลุ่มสีอาการเพื่อบันทึกคัดกรอง -->
                <div>
                    <h4 class="font-bold text-slate-700 dark:text-slate-300 mb-3 text-sm text-left flex items-center gap-1.5">
                        <i class="fa-solid fa-traffic-light text-emerald-500"></i> 2. เลือกกลุ่มสีอาการเพื่อบันทึกคัดกรอง
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <button type="button" onclick="window.selectColorGroup(${patient.id}, 'green')" class="flex flex-col items-center justify-center p-4 bg-white dark:bg-slate-800 border-2 border-emerald-500 rounded-xl hover:bg-emerald-500 text-emerald-600 hover:text-white transition-all group">
                            <i class="fa-solid fa-shield-virus text-3xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="font-black text-sm">กลุ่มปกติ</span>
                        </button>
                        <button type="button" onclick="window.selectColorGroup(${patient.id}, 'yellow')" class="flex flex-col items-center justify-center p-4 bg-white dark:bg-slate-800 border-2 border-amber-500 rounded-xl hover:bg-amber-500 text-amber-500 hover:text-white transition-all group">
                            <i class="fa-solid fa-head-side-cough text-3xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="font-black text-sm">กลุ่มเสี่ยง</span>
                        </button>
                        <button type="button" onclick="window.selectColorGroup(${patient.id}, 'red')" class="flex flex-col items-center justify-center p-4 bg-white dark:bg-slate-800 border-2 border-rose-500 rounded-xl hover:bg-rose-500 text-rose-500 hover:text-white transition-all group">
                            <i class="fa-solid fa-virus-covid text-3xl mb-2 group-hover:scale-110 transition-transform"></i>
                            <span class="font-black text-sm">พบเชื้อ</span>
                        </button>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true,
            focusConfirm: false,
            didOpen: () => {
                window.toggleScreeningChronicInput(initStatus);
            },
            customClass: {
                popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-2xl p-6 border border-slate-100 dark:border-slate-700 w-full max-w-lg',
            }
        });
    };

    window.selectColorGroup = function(patientId, colorGroup) {
        const selectedRadio = document.querySelector('input[name="screening_chronic_status"]:checked');
        const status = selectedRadio ? selectedRadio.value : 'none';
        const detailInput = document.getElementById('screening-chronic-detail-input');
        const detail = detailInput ? detailInput.value.trim() : '';

        if ((status === 'yes' || status === 'other') && detail === '') {
            Swal.showValidationMessage('กรุณากรอกรายละเอียดโรคประจำตัว หรือข้อมูลอื่นๆ ด้วยครับ');
            if (detailInput) detailInput.focus();
            return;
        }

        window.tempChronicStatus = status;
        window.tempChronicDetail = (status === 'none') ? '' : detail;

        if (colorGroup === 'green') {
            window.submitScreening(patientId, colorGroup, 'ปกติ (ไม่มีอาการ)');
        } else {
            window.showSymptomsForm(patientId, colorGroup);
        }
    }

    window.showSymptomsForm = function(patientId, colorGroup) {
        let title = colorGroup === 'yellow' ? 'ระบุอาการ (กลุ่มสีเหลือง)' : 'ระบุผลตรวจและอาการ (กลุ่มสีแดง)';
        let colorHex = colorGroup === 'yellow' ? '#f59e0b' : '#f43f5e';
        let checkColor = colorGroup === 'yellow' ? 'text-amber-500 focus:ring-amber-500' : 'text-rose-500 focus:ring-rose-500';

        let redFindingsHtml = '';
        if (colorGroup === 'red') {
            redFindingsHtml = `
                <div class="mb-5 bg-rose-50 dark:bg-rose-900/20 p-4 rounded-xl border border-rose-100 dark:border-rose-800/50">
                    <label class="block font-black text-rose-700 dark:text-rose-400 mb-3 text-sm"><i class="fa-solid fa-microscope mr-1"></i> ผลการตรวจพบ:</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-left">
                        <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="finding[]" value="พบเชื้อโควิด-19" class="w-5 h-5 rounded ${checkColor}"> พบเชื้อโควิด-19</label>
                        <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="finding[]" value="ไข้หวัดใหญ่ สายพันธุ์ A" class="w-5 h-5 rounded ${checkColor}"> ไข้หวัดใหญ่ สายพันธุ์ A</label>
                        <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="finding[]" value="ไข้หวัดใหญ่ สายพันธุ์ B" class="w-5 h-5 rounded ${checkColor}"> ไข้หวัดใหญ่ สายพันธุ์ B</label>
                        <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="finding[]" value="ไข้หวัดใหญ่ สายพันธุ์ A และ B" class="w-5 h-5 rounded ${checkColor}"> ไข้หวัดใหญ่ A และ B</label>
                    </div>
                </div>
            `;
        }

        let symptomsHtml = `
            <div class="mb-2 p-4">
                <label class="block font-black text-slate-700 dark:text-slate-300 mb-3 text-sm"><i class="fa-solid fa-head-side-cough mr-1"></i> อาการที่พบ:</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-left">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="symptom[]" value="ไข้" class="w-5 h-5 rounded ${checkColor}"> ไข้</label>
                    <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="symptom[]" value="ปวดศีรษะ" class="w-5 h-5 rounded ${checkColor}"> ปวดศีรษะ</label>
                    <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="symptom[]" value="มีน้ำมูก" class="w-5 h-5 rounded ${checkColor}"> มีน้ำมูก</label>
                    <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="symptom[]" value="ไอแห้ง" class="w-5 h-5 rounded ${checkColor}"> ไอแห้ง</label>
                    <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="symptom[]" value="ไอมีเสมหะ" class="w-5 h-5 rounded ${checkColor}"> ไอมีเสมหะ</label>
                    <label class="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300"><input type="checkbox" name="symptom[]" value="เจ็บคอ" class="w-5 h-5 rounded ${checkColor}"> เจ็บคอ</label>
                </div>
                <div class="mt-4">
                    <input type="text" id="other_symptom" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 p-3 text-sm focus:ring focus:ring-indigo-500/20 text-slate-700 dark:text-white" placeholder="อาการอื่นๆ (พิมพ์ระบุเพิ่มเติม...)" maxlength="100">
                </div>
            </div>
        `;

        Swal.fire({
            title: `<div style="color: ${colorHex}" class="font-black text-xl border-b border-slate-100 dark:border-slate-700 pb-3">${title}</div>`,
            html: `<div class="text-sm mt-2">` + redFindingsHtml + symptomsHtml + `</div>`,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk mr-1"></i> บันทึกข้อมูล',
            cancelButtonText: 'กลับ',
            confirmButtonColor: colorHex,
            cancelButtonColor: '#94a3b8',
            customClass: {
                popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-2xl p-4 md:p-6 w-full max-w-2xl border border-slate-100 dark:border-slate-700',
                confirmButton: 'font-bold rounded-xl px-6 py-2.5 shadow-md outline-none',
                cancelButton: 'font-bold rounded-xl px-6 py-2.5 shadow-sm outline-none'
            },
            preConfirm: () => {
                let findings = [];
                if(colorGroup === 'red') {
                    document.querySelectorAll('input[name="finding[]"]:checked').forEach(el => findings.push(el.value));
                    if(findings.length === 0) {
                        Swal.showValidationMessage('<i class="fa-solid fa-triangle-exclamation"></i> กรุณาเลือกผลการตรวจพบอย่างน้อย 1 รายการครับ');
                        return false;
                    }
                }

                let symptoms = [];
                document.querySelectorAll('input[name="symptom[]"]:checked').forEach(el => symptoms.push(el.value));
                
                let other = document.getElementById('other_symptom').value.trim();
                if (other !== '') {
                    symptoms.push(`อื่นๆ: ${other}`);
                }

                if(symptoms.length === 0 && colorGroup === 'yellow') {
                    Swal.showValidationMessage('<i class="fa-solid fa-triangle-exclamation"></i> กรุณาเลือกอาการอย่างน้อย 1 รายการครับ');
                    return false;
                }

                let finalNote = '';
                if(findings.length > 0) {
                    finalNote += `[ตรวจพบ: ${findings.join(', ')}] `;
                }
                if(symptoms.length > 0) {
                    finalNote += `[อาการ: ${symptoms.join(', ')}]`;
                }
                
                return finalNote.trim();
            }
        }).then((result) => {
            if(result.isConfirmed) {
                window.submitScreening(patientId, colorGroup, result.value);
            }
        });
    }

    window.submitScreening = async function(patientId, colorGroup, symptomsNote = '') {
        Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        try {
            const formData = new FormData();
            formData.append('patient_id', patientId);
            formData.append('color_group', colorGroup);
            formData.append('symptoms_note', symptomsNote);
            formData.append('chronic_disease_status', window.tempChronicStatus || 'none');
            formData.append('chronic_disease_detail', window.tempChronicDetail || '');
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            const response = await fetch('{{ route("backend.recruit_screenings.store") }}', {
                method: 'POST', body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                if (colorGroup === 'yellow' || colorGroup === 'red') {
                    let mewsUrl = '{{ route("backend.mews.create", ["patient_id" => "__ID__"]) }}'.replace('__ID__', patientId);
                    
                    Swal.fire({
                        icon: 'warning',
                        title: 'บันทึกสำเร็จ!',
                        text: 'ระบบกำลังพาท่านเข้าสู่หน้าประเมิน MEWS ทันที...',
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        timer: 1500,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    }).then(() => {
                        window.location.replace(mewsUrl);
                    });
                    
                } else {
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success', title: result.message, showConfirmButton: false, timer: 2000
                    }).then(() => {
                        window.openScanModal(); 
                    });
                }

            } else {
                Swal.fire('ผิดพลาด', result.message, 'error');
            }
        } catch (error) {
            console.error(error);
            Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        }
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        
        const isDarkMode = document.documentElement.classList.contains('dark');
        const textColor = isDarkMode ? '#94a3b8' : '#64748b';

        const donutOptions = {
            series: [{{ $summary['total_green'] }}, {{ $summary['total_yellow'] }}, {{ $summary['total_red'] }}],
            labels: ['กลุ่มปกติ (เขียว)', 'กลุ่มเสี่ยง (เหลือง)', 'พบเชื้อ (แดง)'],
            colors: ['#10b981', '#f59e0b', '#f43f5e'],
            chart: {
                type: 'donut',
                height: 320,
                fontFamily: 'Prompt, sans-serif',
                background: 'transparent'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: { fontSize: '14px', color: textColor },
                            value: { fontSize: '24px', fontWeight: 800, color: isDarkMode ? '#f8fafc' : '#1e293b' },
                            total: {
                                show: true,
                                label: 'ตรวจแล้ว',
                                color: textColor,
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => { return a + b }, 0) + ' นาย'
                                }
                            }
                        }
                    }
                }
            },
            stroke: { show: false },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', labels: { colors: textColor } },
            theme: { mode: isDarkMode ? 'dark' : 'light' }
        };
        window.donutChart = new ApexCharts(document.querySelector("#triageDonutChart"), donutOptions);
        window.donutChart.render();

        const barOptions = {
            series: [{
                name: 'รายงานตัวแล้ว',
                data: {!! json_encode($chartScreenedData) !!}
            }],
            chart: {
                type: 'bar',
                height: 320,
                fontFamily: 'Prompt, sans-serif',
                background: 'transparent',
                toolbar: { show: false }
            },
            colors: ['#6366f1'],
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '45%',
                    distributed: false,
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: {!! json_encode($chartCategories) !!},
                labels: {
                    style: { colors: textColor, fontSize: '11px', fontWeight: 500 },
                    trim: true,
                    hideOverlappingLabels: false
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { style: { colors: textColor } }
            },
            grid: {
                borderColor: isDarkMode ? '#334155' : '#e2e8f0',
                strokeDashArray: 4,
            },
            theme: { mode: isDarkMode ? 'dark' : 'light' }
        };
        window.barChart = new ApexCharts(document.querySelector("#unitBarChart"), barOptions);
        window.barChart.render();

        window.viewPatientsByColor = async function(color) {
            let colorName = color === 'red' ? 'กลุ่มพบเชื้อ (แดง)' : (color === 'yellow' ? 'กลุ่มเสี่ยง (เหลือง)' : 'กลุ่มปกติ (เขียว)');
            let colorHex  = color === 'red' ? '#e11d48' : (color === 'yellow' ? '#d97706' : '#10b981'); 
            let icon      = color === 'red' ? 'fa-virus-covid' : (color === 'yellow' ? 'fa-head-side-cough' : 'fa-shield-virus');
            let batch     = '{{ $selectedBatch }}';
            let mewsUrlTemplate = '{{ route("backend.mews.create", ["patient_id" => "__ID__"]) }}';
            let patientUrlTemplate = '{{ route("backend.patients.show", ["patient" => "__ID__"]) }}';

            Swal.fire({
                title: 'กำลังโหลดข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                customClass: { popup: 'bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700' }
            });

            try {
                const response = await fetch(`{{ route('backend.recruit_screenings.patients_by_color') }}?color=${color}&batch=${batch}`);
                const result = await response.json();

                if (result.status === 'success') {
                    if (result.data.length === 0) {
                        Swal.fire({
                            icon: 'info',
                            title: 'ไม่พบข้อมูล',
                            text: `ยังไม่มีผู้ป่วยใน${colorName} ของผลัด ${batch} ครับ`,
                            confirmButtonColor: '#0f172a',
                            customClass: { popup: 'bg-white dark:bg-slate-800 rounded-3xl p-6 border border-slate-100 dark:border-slate-700' }
                        });
                        return;
                    }

                    let html = `<div class="max-h-[60vh] overflow-y-auto text-left mt-4 pr-2">`;
                    result.data.forEach((p, index) => {
                        let mewsLink = mewsUrlTemplate.replace('__ID__', p.patient_id); 
                        let patientLink = patientUrlTemplate.replace('__ID__', p.patient_id);

                        // 🔒 แสดงป้ายโรคประจำตัวและอื่นๆ ในป๊อปอัปเฉพาะสิทธิ์ Admin เท่านั้น
                        let chronicBadge = '';
                        if (isAdminUser) {
                            if (p.chronic_disease_status === 'yes') {
                                chronicBadge = `<span class="text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded font-bold text-[11px]"><i class="fa-solid fa-heart-pulse mr-1"></i>โรคประจำตัว: ${p.chronic_disease_detail || 'ระบุ'}</span>`;
                            } else if (p.chronic_disease_status === 'other') {
                                chronicBadge = `<span class="text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded font-bold text-[11px]"><i class="fa-solid fa-file-medical mr-1"></i>อื่นๆ: ${p.chronic_disease_detail || 'ระบุ'}</span>`;
                            }
                        }

                        html += `
                            <div class="mb-3 p-4 rounded-xl border border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 hover:bg-white dark:hover:bg-slate-800 transition-colors shadow-sm relative group overflow-hidden">
                                <div class="absolute top-3 right-3 flex items-center gap-2">
                                    ${p.has_mews ? `
                                        <a href="${patientLink}" class="h-8 px-3 rounded-lg ${p.mews_status === 'pending' ? 'bg-indigo-50 text-indigo-600 border-indigo-200 hover:bg-indigo-100 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700'} flex items-center justify-center shadow-sm text-xs font-bold gap-1.5 border transition-all cursor-pointer group/btn" title="คลิกเพื่อดูแฟ้มประวัติการประเมิน">
                                            ${p.mews_status === 'pending' ? '<i class="fa-solid fa-spinner fa-spin"></i> ER กำลังรักษา' : '<i class="fa-solid fa-check-double"></i> จบเคสแล้ว'} 
                                            (MEWS: ${p.mews_score})
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-50 group-hover/btn:opacity-100 transition-opacity"></i>
                                        </a>
                                    ` : (color !== 'green' ? `
                                        <a href="${mewsLink}" class="h-8 px-3 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white flex items-center justify-center transition-colors shadow-sm text-xs font-bold gap-1" title="ประเมิน MEWS ทันที">
                                            <i class="fa-solid fa-stethoscope"></i> ประเมิน MEWS
                                        </a>
                                    ` : '')}

                                    ${!p.has_mews ? `
                                        <button onclick="window.editScreening(${p.screening_id}, '${p.cid}', '${color}')" class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-500 hover:text-white flex items-center justify-center transition-colors shadow-sm" title="แก้ไขอาการ">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button onclick="window.deleteScreening(${p.screening_id}, '${color}')" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white flex items-center justify-center transition-colors shadow-sm" title="ลบข้อมูล">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    ` : ''}
                                </div>

                                <div class="flex justify-between items-start gap-4 pr-24">
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-white">${index + 1}. ${p.name}</h4>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex flex-wrap gap-2 items-center">
                                            <span><i class="fa-solid fa-tent w-4 text-slate-400"></i> ${p.unit}</span>
                                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold"><i class="fa-solid fa-shield-heart mr-1"></i>${p.pttype_name || 'รอซิงค์สิทธิ'}</span>
                                            ${chronicBadge}
                                        </div>
                                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-2 font-medium bg-white dark:bg-slate-800 p-2.5 rounded-lg border border-slate-100 dark:border-slate-700">
                                            <i class="fa-solid fa-notes-medical w-4 text-indigo-400"></i> ${p.note}
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 text-[10px] font-bold text-slate-400">บันทึกเมื่อ: ${p.time}</div>
                            </div>
                        `;
                    });
                    html += `</div>`;

                    Swal.fire({
                        title: `<div class="text-xl font-black text-left flex items-center gap-3 border-b border-slate-100 dark:border-slate-700 pb-4" style="color: ${colorHex}">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white" style="background-color: ${colorHex}">
                                        <i class="fa-solid ${icon} text-lg"></i>
                                    </div>
                                    รายชื่อ${colorName}
                                </div>`,
                        html: html,
                        width: '600px',
                        showCloseButton: true,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-2xl p-6 border border-slate-100 dark:border-slate-700',
                        }
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'ผิดพลาด',
                    text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                    confirmButtonColor: '#0f172a'
                });
            }
        }
    });

    window.deleteScreening = function(screeningId, currentColor) {
        Swal.fire({
            title: 'ยืนยันการลบข้อมูล?',
            text: "ข้อมูลอาการจะถูกลบ และทหารนายนี้จะกลับไปอยู่กลุ่ม 'รอรายงานตัว' ครับ",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'กำลังลบข้อมูล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                try {
                    let url = `{{ route('backend.recruit_screenings.destroy_api', ':id') }}`.replace(':id', screeningId);
                    const response = await fetch(url, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                    });
                    const res = await response.json();
                    
                    if (res.status === 'success') {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.message, showConfirmButton: false, timer: 2000 });
                        window.viewPatientsByColor(currentColor);
                    } else {
                        Swal.fire('ผิดพลาด', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                }
            }
        });
    }

    window.editScreening = function(screeningId, cid, currentColor) {
        Swal.fire({
            title: 'แก้ไขอาการ/ผลตรวจ?',
            text: "ระบบจะให้คุณเลือกกลุ่มสี และติ๊กอาการใหม่อีกครั้ง ยืนยันหรือไม่?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ใช่, เริ่มแก้ไข',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'กำลังเตรียมข้อมูล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                try {
                    let url = `{{ route('backend.recruit_screenings.destroy_api', ':id') }}`.replace(':id', screeningId);
                    await fetch(url, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                    });
                    Swal.close();
                    window.fetchPatientData(cid);
                } catch (error) {
                    Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                }
            }
        });
    }

    window.exportTableToCSV = function(filename) {
        let csv = [];
        let rows = document.querySelectorAll("#summaryTable tr");
        
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll("td, th");
            for (let j = 0; j < cols.length; j++) {
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                data = data.replace(/"/g, '""');
                row.push('"' + data + '"');
            }
            csv.push(row.join(","));
        }

        let csvFile = new Blob(["\uFEFF" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
        let downloadLink = document.createElement("a");
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        
        Swal.fire({
            toast: true, position: 'top-end', icon: 'success', 
            title: 'ดาวน์โหลดไฟล์สำเร็จ!', showConfirmButton: false, timer: 2500 
        });
    }
</script>
@endsection