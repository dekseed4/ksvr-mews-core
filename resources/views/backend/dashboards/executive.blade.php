@extends('layouts.backend')

@section('title', 'Executive Dashboard - KSVR Hospital')
@section('page_header', 'ภาพรวมสถานการณ์ผู้ป่วย (Executive Dashboard)')

@section('content')
<div class="max-w-7xl mx-auto mt-4 pb-12 space-y-6 md:space-y-8">
    
    <!-- 🌟 แท็บสลับมุมมอง (Dynamic Dashboard Switcher - Enterprise Grade) -->
    <?php
        $user = auth()->user();
        
        // 1. ป้องกัน JSON Parsing Error
        $userRoles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        
        // 2. เช็คสิทธิ์แบบแยกขาดจากกัน (Strict Check)
        $isAdmin = in_array('admin', $userRoles);
        $isExecutive = $isAdmin || in_array('executive', $userRoles);
        $isER = $isAdmin || in_array('er_nurse', $userRoles);
        $isMedic = $isAdmin || in_array('medic', $userRoles);
        $isRegistration = $isAdmin || in_array('registration', $userRoles);
        $isOPD = $isAdmin || in_array('opd_nurse', $userRoles);
        
        // 3. นับจำนวนสิทธิ์ที่มี เพื่อตัดสินใจว่าจะโชว์แท็บสลับหน้าจอไหม
        $accessCount = ($isExecutive ? 1 : 0) + ($isER ? 1 : 0) + ($isMedic ? 1 : 0) + ($isRegistration ? 1 : 0) + ($isOPD ? 1 : 0); 
        $hasMultipleRoles = $accessCount > 1;

        // ค่า Default ของหน้านี้
        $currentView = request('view') ?? 'executive'; 
    ?>

    <?php if ($hasMultipleRoles): ?>
    <div class="flex justify-center mb-6 animate-fade-in-up">
        <div class="bg-slate-100 p-1 rounded-2xl inline-flex border border-slate-200 shadow-sm max-w-full overflow-x-auto">
            
            <?php if ($isExecutive): ?>
            <a href="{{ route('backend.dashboard', ['view' => 'executive']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'executive' ? 'bg-white text-slate-800 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-chart-pie"></i> <span class="hidden sm:inline">ผู้บริหาร</span>
            </a>
            <?php endif; ?>
            
            <?php if ($isER): ?>
            <a href="{{ route('backend.dashboard', ['view' => 'er']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'er' ? 'bg-white text-emerald-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-emerald-600 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-truck-medical"></i> <span class="hidden sm:inline">ER</span>
            </a>
            <?php endif; ?>
            
            <?php if ($isMedic): ?>
            <a href="{{ route('backend.dashboard', ['view' => 'medic']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'medic' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-blue-600 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-walkie-talkie"></i> <span class="hidden sm:inline">เสนารักษ์</span>
            </a>
            <?php endif; ?>

            <?php if ($isRegistration): ?>
            <a href="{{ route('backend.dashboard', ['view' => 'registration']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'registration' ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-id-card-clip"></i> <span class="hidden sm:inline">ทะเบียน</span>
            </a>
            <?php endif; ?>

            <?php if ($isOPD): ?>
            <a href="{{ route('backend.dashboard', ['view' => 'opd']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'opd' ? 'bg-white text-teal-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-teal-600 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-stethoscope"></i> <span class="hidden sm:inline">OPD</span>
            </a>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; ?>

    <!-- 🟢 Header ทักทายผู้บริหาร & ตัวกรองข้อมูล (Responsive & Non-Overflow Design) -->
    <div class="bg-slate-800 rounded-3xl px-5 py-6 md:px-7 md:py-6 flex flex-col xl:flex-row xl:items-center justify-between shadow-lg text-white gap-5 relative">
        
        <!-- แยก Layer แสงตกกระทบ Background เพื่อไม่ให้ตัดขอบปฏิทิน -->
        <div class="absolute inset-0 overflow-hidden rounded-3xl pointer-events-none">
            <div class="absolute top-0 right-0 -mr-16 -mt-16 w-48 h-48 rounded-full bg-emerald-500/10 blur-3xl"></div>
        </div>

        <!-- 🌟 ฝั่งซ้าย: ข้อมูลทักทายและป้ายสถานะ -->
        <div class="relative z-10 flex flex-col justify-center shrink-0">
            <h2 class="text-2xl md:text-3xl font-bold mb-2 flex items-center gap-2 tracking-tight">
                สวัสดีครับ, {{ auth()->user()->name }} <span class="origin-bottom-right inline-block hover:animate-pulse cursor-default text-xl md:text-2xl">👋</span>
            </h2>
            
            <div class="flex flex-wrap items-center gap-2 text-slate-300 text-sm mt-1">
                <div class="flex items-center gap-1.5 mr-1">
                    <i class="fa-solid fa-chart-line text-emerald-400"></i>
                    <span>รายงานสถานการณ์ผู้ป่วย</span>
                </div>
                
                <!-- Badge ป้ายกำกับช่วงเวลา -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 font-semibold border border-emerald-500/30 shadow-sm text-xs md:text-sm w-fit">
                    <i class="fa-regular fa-calendar-days"></i>
                    {{ $badgeText ?? 'ไม่ระบุช่วงเวลา' }}
                </span>
                
                <!-- Badge ผลัด -->
                <?php if (($filterBatch ?? 'all') !== 'all'): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-400 font-semibold border border-indigo-500/30 shadow-sm text-xs md:text-sm w-fit">
                    <i class="fa-solid fa-users-rays"></i>
                    ผลัด {{ $filterBatch }}
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 🌟 ฝั่งขวา: เครื่องมือกรอง (คำนวณความกว้างให้พอดีกับ max-w-7xl ไม่ล้นกรอบ 100%) -->
        <div class="relative z-20 w-full xl:w-auto flex-1">
            <form action="{{ route('backend.dashboard') }}" method="GET" class="flex flex-col lg:flex-row lg:items-center gap-2.5 w-full justify-start xl:justify-end flex-wrap">
                <input type="hidden" name="view" value="executive"> 
                
                <!-- กล่องรวมตัวกรองหลัก -->
                <div class="flex flex-col sm:flex-row flex-wrap lg:flex-nowrap items-stretch sm:items-center bg-slate-900/60 border border-slate-700/80 rounded-2xl p-1.5 shadow-inner w-full lg:w-auto gap-1.5 sm:gap-1">
                    
                    <!-- 1. เลือกผลัด -->
                    <select name="batch" class="bg-slate-800/80 sm:bg-transparent border border-slate-700 sm:border-0 text-white text-sm font-semibold rounded-xl focus:ring-2 focus:ring-emerald-500/40 block w-full sm:w-auto sm:min-w-[115px] py-2 sm:py-0 pl-3 pr-7 hover:bg-slate-700/50 cursor-pointer outline-none h-10">
                        <option value="all" {{ ($filterBatch ?? 'all') == 'all' ? 'selected' : '' }} class="bg-slate-800 font-bold text-indigo-400">ทุกผลัด</option>
                        <option value="1/69" {{ ($filterBatch ?? '') == '1/69' ? 'selected' : '' }} class="bg-slate-800 text-white">ผลัด 1/69</option>
                        <option value="2/69" {{ ($filterBatch ?? '') == '2/69' ? 'selected' : '' }} class="bg-slate-800 text-white">ผลัด 2/69</option>
                    </select>

                    <div class="hidden sm:block w-px h-5 bg-slate-700 mx-0.5"></div>

                    <!-- 2. เลือกโหมดตัวกรอง -->
                    <select name="filter_mode" id="filter_mode" onchange="toggleFilterMode()" class="bg-slate-800/80 sm:bg-transparent border border-slate-700 sm:border-0 text-emerald-400 text-sm font-bold rounded-xl focus:ring-2 focus:ring-emerald-500/40 block w-full sm:w-auto sm:min-w-[140px] py-2 sm:py-0 pl-3 pr-7 hover:bg-slate-700/50 cursor-pointer outline-none h-10">
                        <option value="month" {{ ($filterMode ?? 'month') === 'month' ? 'selected' : '' }} class="bg-slate-800 text-white">รายเดือน</option>
                        <option value="year" {{ ($filterMode ?? '') === 'year' ? 'selected' : '' }} class="bg-slate-800 text-white">ตลอดทั้งปี</option>
                        <option value="custom" {{ ($filterMode ?? '') === 'custom' ? 'selected' : '' }} class="bg-slate-800 text-white">กำหนดช่วงวัน</option>
                    </select>

                    <div class="hidden sm:block w-px h-5 bg-slate-700 mx-0.5"></div>

                    <!-- 3.1 กลุ่มเดือน/ปี -->
                    <div id="wrapper_month_year" class="flex flex-col sm:flex-row items-stretch sm:items-center w-full sm:w-auto gap-1.5 sm:gap-1">
                        <select name="month" id="input_month" class="bg-slate-800/80 sm:bg-transparent border border-slate-700 sm:border-0 text-white text-sm font-medium rounded-xl focus:ring-2 focus:ring-emerald-500/40 block w-full sm:w-auto sm:min-w-[130px] py-2 sm:py-0 pl-3 pr-7 hover:bg-slate-700/50 cursor-pointer outline-none h-10">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <?php $mVal = sprintf('%02d', $m); ?>
                                <option value="{{ $mVal }}" {{ ($filterMonth ?? date('m')) == $mVal ? 'selected' : '' }} class="bg-slate-800 text-white">
                                    {{ \Carbon\Carbon::create(null, $m, 1)->locale('th')->translatedFormat('F') }}
                                </option>
                            <?php endfor; ?>
                        </select>
                        <div id="divider_year" class="hidden sm:block w-px h-5 bg-slate-700 mx-0.5"></div>
                        <select name="year" id="input_year" class="bg-slate-800/80 sm:bg-transparent border border-slate-700 sm:border-0 text-white text-sm font-medium rounded-xl focus:ring-2 focus:ring-emerald-500/40 block w-full sm:w-auto sm:min-w-[95px] py-2 sm:py-0 pl-3 pr-7 hover:bg-slate-700/50 cursor-pointer outline-none h-10">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="{{ $y }}" {{ ($filterYear ?? date('Y')) == $y ? 'selected' : '' }} class="bg-slate-800 text-white">{{ $y + 543 }}</option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- 3.2 กลุ่มกำหนดช่วงวัน (Custom Dates - ใช้ Flatpickr ปฏิทินภาษาไทย + ปี พ.ศ. 100%) -->
                    <div id="wrapper_custom" class="flex-col sm:flex-row items-stretch sm:items-center w-full sm:w-auto gap-1.5 sm:gap-1.5" style="display: none;">
                        
                        <!-- วันเริ่มต้น -->
                        <div id="box_start_date" class="relative flex items-center bg-slate-800/90 hover:bg-slate-700/80 border border-slate-600/80 hover:border-emerald-500/60 rounded-xl px-3 h-10 w-full sm:w-[142px] transition-all cursor-pointer group shadow-sm">
                            <i class="fa-regular fa-calendar text-emerald-400 mr-2 text-xs pointer-events-none shrink-0"></i>
                            <input type="text" name="start_date" id="start_date" value="{{ $startDateInput ?? date('Y-m-d') }}" class="thai-datepicker bg-transparent border-0 text-white text-xs sm:text-sm font-semibold flex-1 text-left focus:ring-0 p-0 cursor-pointer outline-none w-full" placeholder="เลือกวันที่">
                            <i class="fa-solid fa-caret-down text-slate-400 group-hover:text-white text-xs ml-1 pointer-events-none shrink-0"></i>
                        </div>
                        
                        <span class="text-slate-400 text-xs font-bold text-center self-center px-0.5">ถึง</span>
                        
                        <!-- วันสิ้นสุด -->
                        <div id="box_end_date" class="relative flex items-center bg-slate-800/90 hover:bg-slate-700/80 border border-slate-600/80 hover:border-emerald-500/60 rounded-xl px-3 h-10 w-full sm:w-[142px] transition-all cursor-pointer group shadow-sm">
                            <i class="fa-regular fa-calendar-check text-emerald-400 mr-2 text-xs pointer-events-none shrink-0"></i>
                            <input type="text" name="end_date" id="end_date" value="{{ $endDateInput ?? date('Y-m-d') }}" class="thai-datepicker bg-transparent border-0 text-white text-xs sm:text-sm font-semibold flex-1 text-left focus:ring-0 p-0 cursor-pointer outline-none w-full" placeholder="เลือกวันที่">
                            <i class="fa-solid fa-caret-down text-slate-400 group-hover:text-white text-xs ml-1 pointer-events-none shrink-0"></i>
                        </div>

                    </div>

                </div>
                
                <!-- กลุ่มปุ่มค้นหาและรีเซ็ต -->
                <div class="flex items-center gap-2 w-full lg:w-auto flex-shrink-0">
                    <button type="submit" class="flex-1 lg:flex-none lg:w-11 h-11 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 tooltip" title="ค้นหาข้อมูล">
                        <i class="fa-solid fa-magnifying-glass text-base"></i> <span class="lg:hidden font-bold text-sm">ค้นหา</span>
                    </button>
                    <a href="{{ route('backend.dashboard', ['view' => 'executive']) }}" class="flex-1 lg:flex-none lg:w-11 h-11 bg-slate-700/80 hover:bg-rose-500 text-slate-300 hover:text-white rounded-xl flex items-center justify-center transition-all shadow-sm border border-slate-600 hover:border-rose-500 gap-2 tooltip" title="รีเซ็ตค่าเริ่มต้น">
                        <i class="fa-solid fa-rotate-left text-base"></i> <span class="lg:hidden font-bold text-sm">รีเซ็ต</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- 🦠 เรดาร์แจ้งเตือนการระบาดและกลุ่มเสี่ยงสีแดง (แสดงเฉพาะเมื่อตรวจพบสัญญาณ) -->
    <?php if (isset($outbreakUnits) && count($outbreakUnits) > 0): ?>
    <div class="bg-gradient-to-r from-rose-50 via-purple-50 to-indigo-50 border border-rose-200 rounded-3xl p-5 md:p-6 shadow-sm animate-fade-in-up">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-2xl shadow-md animate-pulse shrink-0">
                    <i class="fa-solid fa-virus-covid"></i>
                </div>
                <div>
                    <h3 class="text-base md:text-lg font-black text-rose-900 flex items-center gap-2">
                        เรดาร์เฝ้าระวังโรคติดต่อและการระบาดในหน่วยฝึก (ในรอบ 48 ชม.)
                        <span class="bg-rose-600 text-white text-[11px] px-2.5 py-0.5 rounded-full font-bold">เฝ้าระวังพิเศษ</span>
                    </h3>
                    <p class="text-xs md:text-sm text-slate-600">ตรวจพบหน่วยฝึกที่มีผู้ป่วยไข้สูงสะสม (≥ 38.0°C) หรือมีผู้ป่วยจากกลุ่มคัดกรองสีแดง (เสี่ยงไข้หวัดใหญ่/โรคติดต่อ)</p>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($outbreakUnits as $ob): ?>
                <div class="bg-white/90 rounded-2xl p-4 border border-rose-100 flex items-center justify-between shadow-sm">
                    <div>
                        <div class="font-bold text-slate-800 text-sm">{{ $ob->company }} <span class="text-xs text-indigo-600 font-semibold">(ผลัด {{ $ob->intake_batch ?? '-' }})</span></div>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php if ($ob->high_fever_count > 0): ?>
                                <span class="px-2 py-0.5 bg-orange-100 text-orange-800 rounded-lg text-xs font-bold border border-orange-200">
                                    <i class="fa-solid fa-temperature-high mr-1"></i>ไข้สูง ≥38°C: {{ $ob->high_fever_count }} นาย
                                </span>
                            <?php endif; ?>
                            <?php if ($ob->red_group_count > 0): ?>
                                <span class="px-2 py-0.5 bg-rose-100 text-rose-800 rounded-lg text-xs font-bold border border-rose-200">
                                    <i class="fa-solid fa-kit-medical mr-1"></i>คัดกรองสีแดง: {{ $ob->red_group_count }} นาย
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 📊 โซน 1: ภาพรวมปฏิบัติการ (Operations Summary) -->
    <div>
        <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-check text-blue-500"></i> ภาพรวมปฏิบัติการ {{ $periodText ?? '(ในรอบเดือน)' }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- ผู้ป่วยที่ประเมิน -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between hover:-translate-y-1 hover:shadow-md transition-all border-b-4 border-b-blue-500 group">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <?php if (($stats['growth_percent'] ?? 0) > 0): ?>
                        <span class="px-2 py-1 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg tooltip" title="เทียบกับช่วงก่อนหน้า"><i class="fa-solid fa-arrow-trend-up mr-1"></i>+{{ $stats['growth_percent'] }}%</span>
                    <?php elseif (($stats['growth_percent'] ?? 0) < 0): ?>
                        <span class="px-2 py-1 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-lg tooltip" title="เทียบกับช่วงก่อนหน้า"><i class="fa-solid fa-arrow-trend-down mr-1"></i>{{ $stats['growth_percent'] }}%</span>
                    <?php else: ?>
                        <span class="px-2 py-1 bg-slate-50 text-slate-500 text-xs font-bold rounded-lg tooltip" title="เทียบกับช่วงก่อนหน้า">คงที่</span>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-3xl font-black text-slate-800">{{ $stats['patients_today'] ?? 0 }} <span class="text-sm font-medium text-slate-500">นาย</span></h3>
                    <p class="text-sm font-bold text-slate-400 mt-1">ผู้ป่วยที่ประเมิน (รวมไม่ซ้ำคน)</p>
                </div>
            </div>

            <!-- ภาระงานประเมินรวม -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between hover:-translate-y-1 hover:shadow-md transition-all border-b-4 border-b-indigo-500 group">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-black text-slate-800">{{ $stats['assessments_today'] ?? 0 }} <span class="text-sm font-medium text-slate-500">ครั้ง</span></h3>
                    <p class="text-sm font-bold text-slate-400 mt-1">ภาระงานประเมินทั้งหมด</p>
                </div>
            </div>

            <!-- ส่งต่อ / แอดมิต -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between hover:-translate-y-1 hover:shadow-md transition-all border-b-4 border-b-amber-500 group">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-bed-pulse"></i>
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-black text-amber-600">{{ $stats['admit_refer'] ?? 0 }} <span class="text-sm font-medium text-slate-500">นาย</span></h3>
                    <p class="text-sm font-bold text-slate-400 mt-1">Admit รพ. หรือ ส่งต่อ</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 🚨 โซน 2: จำแนกตามความวิกฤต (MEWS Triage - คลิกดูรายชื่อได้ครบทั้ง 3 ระดับ) -->
    <div>
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-2">
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-heart-pulse text-rose-500"></i> จำแนกตามระดับความรุนแรง {{ $periodText ?? '(ในรอบเดือน)' }}
            </h2>
            <p class="text-xs font-medium text-slate-500 bg-slate-100 px-3 py-1 rounded-full w-fit">
                <i class="fa-solid fa-hand-pointer text-indigo-500 mr-1"></i> คลิกที่การ์ด <b>สีเขียว</b>, <b>สีเหลือง</b> หรือ <b>สีแดง</b> เพื่อดูรายชื่อและผลการรักษา
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php
                $triageLevels = ['normal', 'observe', 'emergency'];
                foreach ($triageLevels as $level):
                    $count = $mewsLevelsToday[$level] ?? 0;
                    $style = match($level) {
                        'normal' => [
                            'title'  => 'สีเขียว (Normal)',
                            'border' => 'hover:border-emerald-400 border-b-emerald-400 cursor-pointer hover:-translate-y-1 hover:shadow-md',
                            'text'   => 'text-emerald-500',
                            'iconBg' => 'bg-emerald-50 text-emerald-500',
                            'icon'   => 'fa-check-circle',
                            'anim'   => '',
                            'badge'  => 'bg-emerald-50 text-emerald-600'
                        ],
                        'observe' => [
                            'title'  => 'สีเหลือง (Observe)',
                            'border' => 'hover:border-amber-400 border-b-amber-400 cursor-pointer hover:-translate-y-1 hover:shadow-md',
                            'text'   => 'text-amber-500',
                            'iconBg' => 'bg-amber-50 text-amber-500',
                            'icon'   => 'fa-triangle-exclamation',
                            'anim'   => '',
                            'badge'  => 'bg-amber-50 text-amber-600'
                        ],
                        'emergency' => [
                            'title'  => 'สีแดง (Emergency)',
                            'border' => 'hover:border-rose-400 border-b-rose-500 cursor-pointer hover:-translate-y-1 hover:shadow-md',
                            'text'   => 'text-rose-600',
                            'iconBg' => 'bg-rose-50 text-rose-600',
                            'icon'   => 'fa-heart-crack',
                            'anim'   => $count > 0 ? 'animate-pulse' : '',
                            'badge'  => 'bg-rose-50 text-rose-600'
                        ]
                    };
            ?>
                <div onclick="showExecutiveTriageModal('{{ $level }}')"
                     class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 transition-all flex items-center justify-between group border-b-4 {{ $style['border'] }}">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                            {{ $style['title'] }}
                            <span class="text-[10px] px-2 py-0.5 rounded-full {{ $style['badge'] }} font-bold">
                                คลิกดูข้อมูล <i class="fa-solid fa-magnifying-glass-plus ml-0.5"></i>
                            </span>
                        </p>
                        <h3 class="text-4xl font-black {{ $style['text'] }}">{{ $count }} <span class="text-base font-medium text-slate-500">นาย</span></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform {{ $style['iconBg'] }} {{ $style['anim'] }}">
                        <i class="fa-solid {{ $style['icon'] }}"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 📈 โซน 3: กราฟวิเคราะห์ข้อมูล (Charts) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Trend (กราฟเส้นแนวโน้ม) -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-blue-500"></i> แนวโน้มผู้ป่วย{{ $trendText ?? 'ตลอดทั้งเดือน' }}
            </h3>
            <div id="trendChart" class="w-full h-72"></div>
        </div>

        <!-- กราฟวงแหวน: สัดส่วนความเสี่ยง -->
        <div class="lg:col-span-1 bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col">
            <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-indigo-500"></i> สัดส่วนระดับความเสี่ยง
            </h3>
            <div id="riskDonutChart" class="flex-1 flex items-center justify-center min-h-[250px] w-full"></div>
        </div>
    </div>

    <!-- 📋 โซน 4: ตารางจัดอันดับ (Top 5) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- กราฟแท่ง: อันดับโรคฉุกเฉิน -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-virus text-rose-500"></i> 5 อันดับอาการฉุกเฉินสะสม {{ $periodText ?? '(ในรอบเดือน)' }}
            </h3>
            <div id="symptomsBarChart" class="w-full h-64"></div>
        </div>

        <!-- Top 5 หน่วยฝึก / กองร้อย -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-tent text-emerald-600"></i> แจ้งเตือน: 5 อันดับกองร้อยผู้ป่วยสูงสุด {{ $periodText ?? '(ในรอบเดือน)' }}
            </h3>
            <div class="space-y-4 mt-2">
                <?php if (isset($topUnits) && count($topUnits) > 0): ?>
                    <?php foreach ($topUnits as $index => $unit): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl {{ $index === 0 ? 'bg-rose-50 border border-rose-100' : 'bg-slate-50' }}">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm {{ $index === 0 ? 'bg-rose-500 text-white' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $index + 1 }}
                                </div>
                                <span class="font-bold {{ $index === 0 ? 'text-rose-700' : 'text-slate-700' }}">{{ $unit->company }}</span>
                            </div>
                            <span class="font-bold text-lg {{ $index === 0 ? 'text-rose-600' : 'text-slate-600' }}">{{ $unit->total }} <span class="text-xs font-normal text-slate-400">นาย</span></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-48 text-slate-400">
                        <i class="fa-solid fa-shield-heart text-4xl mb-3 text-emerald-200"></i>
                        <p>ยังไม่มีรายงานผู้ป่วยจากกองร้อย {{ $periodText ?? 'ในช่วงเวลานี้' }}</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<!-- 🌟 โหลด ApexCharts และ Flatpickr (ปฏิทินภาษาไทย + Dark Theme) -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const colors = { emerald: '#34d399', amber: '#fbbf24', rose: '#f43f5e', indigo: '#6366f1', blue: '#3b82f6' };

    // ==========================================
    // 📊 1. กราฟวงแหวน (Donut Chart)
    // ==========================================
    const riskData = [
        {{ $riskLevels['normal'] ?? 0 }},
        {{ $riskLevels['observe'] ?? 0 }},
        {{ $riskLevels['emergency'] ?? 0 }}
    ];
    const totalPatients = riskData.reduce((a, b) => a + b, 0);

    const donutOptions = {
        series: totalPatients > 0 ? riskData : [1],
        chart: { type: 'donut', height: 320, fontFamily: 'Prompt, sans-serif' },
        labels: totalPatients > 0 ? ['ปกติ', 'เฝ้าระวัง', 'ฉุกเฉินวิกฤต'] : ['ไม่มีข้อมูล'],
        colors: totalPatients > 0 ? [colors.emerald, colors.amber, colors.rose] : ['#f1f5f9'],
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: { fontSize: '14px', color: '#64748b' },
                        value: { fontSize: '32px', fontWeight: 900, color: '#334155' },
                        total: {
                            show: true, label: 'ผู้ป่วยทั้งหมด', color: '#94a3b8',
                            formatter: function () { return totalPatients > 0 ? totalPatients + " นาย" : "0 นาย" }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        stroke: { width: 0 },
        legend: { position: 'bottom', fontSize: '14px', markers: { radius: 12 } },
        tooltip: { enabled: totalPatients > 0 }
    };
    
    new ApexCharts(document.querySelector("#riskDonutChart"), donutOptions).render();

    // ==========================================
    // 📈 2. กราฟแท่ง (Bar Chart) - Top 5 อาการ
    // ==========================================
    const barLabels = @json($top5Labels ?? []);
    const barData = @json($top5Data ?? []);
    const maxVal = barData.length > 0 ? Math.max(...barData) : 0;
    const xAxisMax = maxVal === 0 ? 1 : maxVal * 1.15;

    const barOptions = {
        series: [{ name: 'จำนวนเคส', data: barData }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'Prompt, sans-serif' },
        colors: [colors.indigo],
        plotOptions: {
            bar: { borderRadius: 4, horizontal: true, barHeight: '40%', dataLabels: { position: 'top' } }
        },
        dataLabels: {
            enabled: true, offsetX: 25,
            style: { fontSize: '13px', fontWeight: 'bold', colors: ['#475569'] },
            formatter: function (val) { return val + " นาย"; }
        },
        xaxis: { categories: barLabels, max: xAxisMax, labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
        grid: { show: false, padding: { right: 50 } },
        tooltip: { theme: 'light', y: { formatter: function (val) { return val + " นาย" } } }
    };

    setTimeout(() => {
        if(barData.length > 0) {
            new ApexCharts(document.querySelector("#symptomsBarChart"), barOptions).render();
        } else {
            document.querySelector("#symptomsBarChart").innerHTML = `<div class="flex flex-col items-center justify-center h-full text-slate-400"><i class="fa-solid fa-folder-open text-4xl mb-3 opacity-50"></i><p>ยังไม่มีข้อมูลอาการฉุกเฉิน</p></div>`;
        }
    }, 50);

    // ==========================================
    // 📉 3. กราฟเส้น (Line Chart) - Trend แนวโน้ม
    // ==========================================
    const trendLabels = @json($trendLabels ?? []);
    const trendData = @json($trendData ?? []);

    const trendOptions = {
        series: [{ name: 'จำนวนผู้ป่วย', data: trendData }],
        chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'Prompt, sans-serif' },
        colors: [colors.blue],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        xaxis: { categories: trendLabels, tooltip: { enabled: false }, labels: { style: { colors: '#94a3b8' } } },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function (val) { return val.toFixed(0); } } },
        tooltip: { 
            theme: 'light', 
            x: { 
                formatter: function (val, opts) { 
                    const label = trendLabels[opts.dataPointIndex] !== undefined ? trendLabels[opts.dataPointIndex] : val;
                    return isNaN(label) ? "ช่วง: " + label : "วันที่ " + label; 
                } 
            } 
        }
    };
    
    setTimeout(() => {
        new ApexCharts(document.querySelector("#trendChart"), trendOptions).render();
    }, 100);

    // ==========================================
    // 🗓️ 4. ตั้งค่า Flatpickr ปฏิทินภาษาไทย + ปี พ.ศ.
    // ==========================================
    toggleFilterMode();

    const syncBuddhistYearHeader = function(instance) {
        if (instance && instance.currentYearElement) {
            instance.currentYearElement.readOnly = true;
            setTimeout(() => {
                instance.currentYearElement.value = instance.currentYear + 543;
            }, 10);
        }
    };

    const fpInstances = flatpickr(".thai-datepicker", {
        locale: "th",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "j M Y",
        disableMobile: true, // บังคับให้บนมือถือก็เห็นปฏิทินภาษาไทยสวยงามเหมือนกัน
        formatDate: function(date, formatStr) {
            if (formatStr === "j M Y") {
                const thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                return `${date.getDate()} ${thaiMonths[date.getMonth()]} ${date.getFullYear() + 543}`;
            }
            return flatpickr.formatDate(date, formatStr);
        },
        onReady: function(selectedDates, dateStr, instance) {
            syncBuddhistYearHeader(instance);
        },
        onOpen: function(selectedDates, dateStr, instance) {
            syncBuddhistYearHeader(instance);
        },
        onYearChange: function(selectedDates, dateStr, instance) {
            syncBuddhistYearHeader(instance);
        },
        onMonthChange: function(selectedDates, dateStr, instance) {
            syncBuddhistYearHeader(instance);
        }
    });

    // ให้คลิกตรงไหนของกล่องวันที่ (รวมถึงไอคอน) ก็เปิดปฏิทินได้ทันที
    document.getElementById('box_start_date')?.addEventListener('click', function() {
        document.getElementById('start_date')?._flatpickr?.open();
    });
    document.getElementById('box_end_date')?.addEventListener('click', function() {
        document.getElementById('end_date')?._flatpickr?.open();
    });

});

function toggleFilterMode() {
    const mode = document.getElementById('filter_mode').value;
    const wrapMonthYear = document.getElementById('wrapper_month_year');
    const inputMonth = document.getElementById('input_month');
    const dividerYear = document.getElementById('divider_year');
    const wrapCustom = document.getElementById('wrapper_custom');

    if (mode === 'custom') {
        wrapMonthYear.style.display = 'none';
        wrapCustom.style.display = 'flex'; 
    } else {
        wrapMonthYear.style.display = 'flex';
        wrapCustom.style.display = 'none';
        
        if (mode === 'year') {
            inputMonth.style.display = 'none';
            if(dividerYear) dividerYear.style.display = 'none';
        } else {
            inputMonth.style.display = 'block';
            if(dividerYear) dividerYear.style.display = ''; 
        }
    }
}

    // 🌟 ข้อมูลผู้ป่วยสีเหลืองและสีแดงสำหรับผู้บริหาร
    const executiveTriageData = @json($executivePatientDetails ?? ['observe' => [], 'emergency' => []]);
    const mewsShowUrlTemplate = "{{ route('backend.mews.show', '__ID__') }}";

    function showExecutiveTriageModal(level) {
        const list = executiveTriageData[level] || [];
        const isEmergency = (level === 'emergency');
        const isObserve   = (level === 'observe');

        const titleText = isEmergency 
            ? 'รายชื่อผู้ป่วยวิกฤต: สีแดง (Emergency)' 
            : (isObserve ? 'รายชื่อผู้ป่วยเฝ้าระวัง: สีเหลือง (Observe)' : 'รายชื่อผู้ป่วยอาการคงที่/ปกติ: สีเขียว (Normal)');

        const headerColor = isEmergency ? 'text-rose-600' : (isObserve ? 'text-amber-600' : 'text-emerald-600');
        const headerIcon  = isEmergency ? 'fa-heart-crack' : (isObserve ? 'fa-triangle-exclamation' : 'fa-circle-check');

        if (list.length === 0) {
            Swal.fire({
                icon: 'info',
                title: titleText,
                text: 'ไม่มีข้อมูลผู้ป่วยในระดับความรุนแรงนี้ในช่วงเวลาที่เลือกครับ',
                confirmButtonText: 'ปิดหน้าต่าง',
                customClass: { popup: 'rounded-3xl', confirmButton: 'bg-slate-800 text-white rounded-xl px-6 py-2.5 font-bold' }
            });
            return;
        }

        let html = `<div class="space-y-3.5 mt-3 text-left max-h-[65vh] overflow-y-auto pr-1">`;

        list.forEach(item => {
            const mewsUrl = mewsShowUrlTemplate.replace('__ID__', item.mews_id);
            
            let symptomsHtml = '';
            if (item.symptoms && item.symptoms.length > 0) {
                symptomsHtml = `<div class="flex flex-wrap gap-1.5 mt-2">`;
                item.symptoms.forEach(sym => {
                    symptomsHtml += `<span class="px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded-md text-[11px] font-bold"><i class="fa-solid fa-notes-medical mr-1"></i>${sym}</span>`;
                });
                symptomsHtml += `</div>`;
            } else {
                symptomsHtml = `<div class="text-xs text-slate-400 italic mt-1.5">ไม่มีอาการฉุกเฉินเฉพาะโรคระบุเพิ่มเติม</div>`;
            }

            const scoreBadgeClass = isEmergency 
                ? 'bg-rose-600 text-white' 
                : (isObserve ? 'bg-amber-500 text-white' : 'bg-emerald-600 text-white');

            html += `
                <div class="p-4 bg-slate-50 hover:bg-white rounded-2xl border border-slate-200 shadow-sm transition-all">
                    <!-- แถวที่ 1: ชื่อ-นามสกุล + คะแนน MEWS -->
                    <div class="flex flex-wrap justify-between items-start gap-2 mb-2">
                        <div>
                            <h4 class="font-black text-slate-800 text-base flex items-center gap-2">
                                ${item.full_name}
                                <span class="text-[11px] font-bold px-2 py-0.5 bg-slate-200 text-slate-700 rounded-md">HN: ${item.hn}</span>
                            </h4>
                            <p class="text-xs text-slate-600 font-semibold mt-0.5">
                                <i class="fa-solid fa-tent text-emerald-600 mr-1"></i> สังกัด: <b>${item.unit}</b> 
                                <span class="text-indigo-600 ml-1">(ผลัด ${item.batch})</span>
                            </p>
                        </div>
                        <span class="px-3 py-1 rounded-xl text-xs font-black ${scoreBadgeClass} shadow-sm">
                            MEWS: ${item.score} คะแนน
                        </span>
                    </div>

                    <!-- แถวที่ 2: สัญญาณชีพและอาการ -->
                    <div class="bg-white p-2.5 rounded-xl border border-slate-100 text-xs text-slate-700 font-mono mb-2">
                        <i class="fa-solid fa-stethoscope text-slate-400 mr-1"></i> ${item.vitals}
                        ${symptomsHtml}
                    </div>

                    <!-- แถวที่ 3: สถานะปัจจุบัน / ส่งต่อไปไหน / กลับหน่วย + ปุ่มดูแฟ้ม -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 border-t border-slate-200/70 mt-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold border ${item.disposition_badge} inline-flex items-center gap-1.5">
                                <i class="fa-solid ${item.disposition_icon}"></i> ${item.disposition_text}
                            </span>
                        </div>
                        <div class="flex items-center justify-between sm:justify-end gap-3">
                            <span class="text-[11px] text-slate-400"><i class="fa-regular fa-clock mr-1"></i>${item.updated_time}</span>
                            <a href="${mewsUrl}" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1 shadow-sm">
                                <i class="fa-solid fa-folder-open"></i> ดูแฟ้มประวัติ
                            </a>
                        </div>
                    </div>
                </div>
            `;
        });

        html += `</div>`;

        Swal.fire({
            title: `<div class="text-lg md:text-xl font-black ${headerColor} flex items-center justify-center gap-2"><i class="fa-solid ${headerIcon}"></i> ${titleText}</div>`,
            html: html,
            width: '42rem',
            showCloseButton: true,
            confirmButtonText: 'ปิดหน้าต่าง',
            customClass: {
                popup: 'rounded-3xl p-4 md:p-6',
                confirmButton: 'bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl px-6 py-2.5'
            }
        });
    }
</script>
@endsection