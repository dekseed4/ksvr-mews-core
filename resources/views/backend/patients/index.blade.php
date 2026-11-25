@extends('layouts.backend')

@php
    $currentView = $viewMode ?? request('view', 'list');
    $isOverviewMode = ($currentView === 'overview');
@endphp

@section('title', $isOverviewMode ? 'ภาพรวมข้อมูลพลทหาร - KSVR Hospital' : 'ทะเบียนรายชื่อพลทหาร - KSVR Hospital')
@section('page_header', $isOverviewMode ? 'ภาพรวมข้อมูลพลทหาร (Recruit Overview Dashboard)' : 'ทะเบียนรายชื่อพลทหาร (จัดการข้อมูลคนไข้)')

@section('content')

<div class="max-w-7xl mx-auto mt-2 pb-12 space-y-6">
    
    @php
        $rawRoles = auth()->user()->roles ?? auth()->user()->role ?? [];
        $userRoles = is_string($rawRoles) ? json_decode($rawRoles, true) : (array) $rawRoles;
        $isAdmin        = in_array('admin', $userRoles);
        $isExecutive    = in_array('executive', $userRoles);
        $isRegistration = in_array('registration', $userRoles);
        $isERNurse      = in_array('er_nurse', $userRoles);
        $isOPDNurse     = in_array('opd_nurse', $userRoles);
        $isMedicalStaff = in_array('medical_staff', $userRoles);

        $canImport    = $isAdmin || !$isExecutive;
        $canSyncHosxp = $isAdmin || $isRegistration || $isERNurse || $isOPDNurse || $isMedicalStaff;
        $showCheckbox = $isAdmin || $canSyncHosxp;
        $tableColSpan = $showCheckbox ? '5' : '4';
    @endphp

    <!-- ========================================================================= -->
    <!-- 🧭 แถบสลับโหมดมุมมอง (Segmented View Switcher: ภาพรวมข้อมูล vs ทะเบียนรายชื่อ) -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-3xl p-3.5 md:p-4 shadow-sm border border-slate-100 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        
        <!-- ปุ่มสลับ 2 หน้าจอ -->
        <div class="inline-flex p-1 bg-slate-100 rounded-2xl border border-slate-200/70 w-full lg:w-auto">
            <a href="{{ route('backend.patients.index', ['view' => 'overview', 'batch' => request('batch'), 'unit' => request('unit')]) }}" 
               class="flex-1 lg:flex-none px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-2 {{ $isOverviewMode ? 'bg-white text-teal-700 shadow-sm border border-slate-200/60' : 'text-slate-500 hover:text-slate-800' }}">
                <i class="fa-solid fa-chart-column {{ $isOverviewMode ? 'text-teal-500' : 'text-slate-400' }}"></i>
                <span>ภาพรวมข้อมูลพลทหาร (Dashboard)</span>
            </a>

            <a href="{{ route('backend.patients.index', ['view' => 'list', 'batch' => request('batch'), 'unit' => request('unit')]) }}" 
               class="flex-1 lg:flex-none px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-2 {{ !$isOverviewMode ? 'bg-white text-indigo-700 shadow-sm border border-slate-200/60' : 'text-slate-500 hover:text-slate-800' }}">
                <i class="fa-solid fa-address-book {{ !$isOverviewMode ? 'text-indigo-500' : 'text-slate-400' }}"></i>
                <span>ทะเบียนรายชื่อพลทหาร ({{ number_format($totalPatientsCount ?? 0) }})</span>
            </a>
        </div>

        <!-- หากอยู่ในหน้า Overview ให้มีตัวกรองผลัด/หน่วย และปุ่มทางลัดด้านขวา -->
        @if($isOverviewMode)
            <form method="GET" action="{{ route('backend.patients.index') }}" class="flex flex-wrap items-center gap-2.5">
                <input type="hidden" name="view" value="overview">
                <select name="batch" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2 outline-none focus:border-teal-500 cursor-pointer">
                    <option value="">ทุกผลัดทหาร</option>
                    @foreach($availableBatches as $b)
                        <option value="{{ $b }}" {{ request('batch') == $b ? 'selected' : '' }}>ผลัดที่ {{ $b }}</option>
                    @endforeach
                </select>

                <select name="unit" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2 outline-none focus:border-teal-500 cursor-pointer">
                    <option value="">ทุกหน่วยฝึก</option>
                    @foreach($allUnits as $u)
                        <option value="{{ $u->id }}" {{ request('unit') == $u->id ? 'selected' : '' }}>{{ $u->short_name ?? $u->name }}</option>
                    @endforeach
                </select>

                @if(request()->filled('batch') || request()->filled('unit'))
                    <a href="{{ route('backend.patients.index', ['view' => 'overview']) }}" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold border border-rose-200">
                        <i class="fa-solid fa-rotate-left mr-1"></i> ล้างตัวกรอง
                    </a>
                @endif
            </form>
        @else
            <!-- หากอยู่ในหน้า ทะเบียนรายชื่อ (list) ให้แสดงสรุปยอดสั้นๆ 1 บรรทัด -->
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-600">
                <span class="px-3 py-1.5 bg-slate-50 rounded-xl border border-slate-200">
                    กำลังพล: <b class="text-slate-900">{{ number_format($totalPatientsCount ?? 0) }}</b>
                </span>
                @if($activePatients->count() > 0)
                    <span class="px-3 py-1.5 bg-rose-50 text-rose-700 rounded-xl border border-rose-200">
                        <i class="fa-solid fa-bed-pulse mr-1 animate-pulse"></i> กำลังรักษา: <b>{{ $activePatients->count() }}</b>
                    </span>
                @endif
                <span class="px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200">
                    คัดกรองแล้ว: <b>{{ number_format($screeningStats['screened'] ?? 0) }}</b>
                </span>
                @if(($overviewExtraStats['inactive_total'] ?? 0) > 0)
                    <span class="px-3 py-1.5 bg-orange-50 text-orange-700 rounded-xl border border-orange-200">
                        ไม่มารายงานตัว/อื่นๆ: <b>{{ number_format($overviewExtraStats['inactive_total']) }}</b>
                    </span>
                @endif
            </div>
        @endif
    </div>

    <!-- ========================================================================= -->
    <!-- 📊 โหมดที่ 1: หน้าภาพรวมข้อมูลพลทหาร (แสดงเฉพาะเมื่อ view === 'overview') -->
    <!-- ========================================================================= -->
    @if($isOverviewMode)
    <div id="realtime-overview-container" class="space-y-6">
        
        <!-- โซนที่ 1: KPI Dashboard Cards หลัก 4 ใบ -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 animate-fade-in-up">
            <!-- Card 1: ยอดรวมทั้งหมด -->
            <a href="{{ route('backend.patients.index', ['view' => 'list', 'batch' => request('batch'), 'unit' => request('unit')]) }}" 
               class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md hover:border-indigo-200 transition-all">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">กำลังพลทั้งหมด</p>
                    <h3 class="text-3xl font-black text-slate-800">{{ number_format($totalPatientsCount ?? 0) }} <span class="text-sm font-medium text-slate-400">นาย</span></h3>
                    <span class="text-[11px] font-bold text-indigo-600 mt-1.5 inline-block group-hover:underline">คลิกดูทะเบียนรายชื่อ <i class="fa-solid fa-arrow-right ml-0.5"></i></span>
                </div>
                <div class="w-14 h-14 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-users"></i>
                </div>
            </a>

            <!-- Card 2: เคสที่กำลังรักษา -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all relative overflow-hidden">
                <div class="absolute right-0 top-0 w-2 h-full bg-blue-500"></div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">กำลังรับการรักษา (Active)</p>
                    <h3 class="text-3xl font-black text-blue-600">{{ $activePatients->count() }} <span class="text-sm font-medium text-slate-400">นาย</span></h3>
                    <span class="text-[11px] text-slate-400 mt-1.5 inline-block">เคสที่ยังไม่ปิดการรักษา</span>
                </div>
                <div class="w-14 h-14 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-bed-pulse"></i>
                </div>
            </div>

            <!-- Card 3: เฝ้าระวัง -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all relative overflow-hidden">
                <div class="absolute right-0 top-0 w-2 h-full bg-amber-400"></div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">กลุ่มเฝ้าระวัง (Observe)</p>
                    <h3 class="text-3xl font-black text-amber-500">{{ $observeCount ?? 0 }} <span class="text-sm font-medium text-slate-400">นาย</span></h3>
                    <span class="text-[11px] text-slate-400 mt-1.5 inline-block">คะแนน MEWS ระดับเฝ้าระวัง</span>
                </div>
                <div class="w-14 h-14 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-bell"></i>
                </div>
            </div>

            <!-- Card 4: วิกฤตฉุกเฉิน -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all relative overflow-hidden">
                <div class="absolute right-0 top-0 w-2 h-full bg-rose-500"></div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">วิกฤตฉุกเฉิน (Emergency)</p>
                    <h3 class="text-3xl font-black text-rose-600">{{ $emergencyCount ?? 0 }} <span class="text-sm font-medium text-slate-400">นาย</span></h3>
                    <span class="text-[11px] text-slate-400 mt-1.5 inline-block">ต้องได้รับการดูแลเร่งด่วน</span>
                </div>
                <div class="w-14 h-14 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-truck-medical"></i>
                </div>
            </div>
        </div>

        <!-- โซนที่ 1.2: การ์ดสรุปความคืบหน้าการซิงค์ HN, การคัดกรอง, สถานะการรายงานตัว/นำปลด และโรคประจำตัว -->
        @if(isset($overviewExtraStats))
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 animate-fade-in-up">
            <!-- ความคืบหน้าการผูกรหัส HN -->
            <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-slate-500 uppercase"><i class="fa-solid fa-hospital-user text-blue-500 mr-1.5"></i>การซิงค์รหัส HN (HosXP)</span>
                    <span class="text-xs font-black text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-100">{{ $overviewExtraStats['hn_percent'] }}%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden mb-3">
                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-700" style="width: {{ $overviewExtraStats['hn_percent'] }}%"></div>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <a href="{{ route('backend.patients.index', ['view' => 'list', 'hn_status' => 'synced', 'batch' => request('batch'), 'unit' => request('unit')]) }}" class="font-bold text-emerald-600 hover:underline">
                        มี HN แล้ว: {{ number_format($overviewExtraStats['synced_hn']) }} นาย
                    </a>
                    <a href="{{ route('backend.patients.index', ['view' => 'list', 'hn_status' => 'unsynced', 'batch' => request('batch'), 'unit' => request('unit')]) }}" class="font-bold text-amber-600 hover:underline">
                        รอซิงค์ HN: {{ number_format($overviewExtraStats['unsynced_hn']) }} นาย
                    </a>
                </div>
            </div>

            <!-- ความคืบหน้าการคัดกรองแรกรับ -->
            <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-slate-500 uppercase"><i class="fa-solid fa-qrcode text-emerald-500 mr-1.5"></i>ความคืบหน้าคัดกรองแรกรับ</span>
                    <span class="text-xs font-black text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-100">{{ $screeningStats['percent'] ?? 0 }}%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden mb-3">
                    <div class="bg-emerald-500 h-2 rounded-full transition-all duration-700" style="width: {{ $screeningStats['percent'] ?? 0 }}%"></div>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <a href="{{ route('backend.patients.index', ['view' => 'list', 'screening_status' => 'screened', 'batch' => request('batch'), 'unit' => request('unit')]) }}" class="font-bold text-emerald-600 hover:underline">
                        คัดกรองแล้ว: {{ number_format($screeningStats['screened'] ?? 0) }} นาย
                    </a>
                    <a href="{{ route('backend.recruit_screenings.dashboard', ['intake_batch' => request('batch', '2/69')]) }}" class="font-bold text-indigo-600 hover:underline">
                        ไปหน้าสแกนคัดกรอง <i class="fa-solid fa-arrow-right ml-0.5"></i>
                    </a>
                </div>
            </div>

            <!-- โรคประจำตัว (เฉพาะ Admin) หรือ สถานะไม่มารายงานตัว/นำปลด -->
            @if($isAdmin)
            <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-rose-600 uppercase"><i class="fa-solid fa-heart-pulse mr-1.5"></i>โรคประจำตัว & สถานะพิเศษ</span>
                    <span class="text-[10px] font-bold bg-slate-800 text-white px-2 py-0.5 rounded">Admin</span>
                </div>
                <div class="grid grid-cols-3 gap-2 my-1 text-center">
                    <a href="{{ route('backend.patients.index', ['view' => 'list', 'chronic_status' => 'has_chronic', 'batch' => request('batch'), 'unit' => request('unit')]) }}" 
                       class="p-2 rounded-xl bg-rose-50/70 hover:bg-rose-100 border border-rose-200 transition-colors">
                        <div class="text-[10px] font-bold text-rose-700 truncate">โรคประจำตัว</div>
                        <div class="text-lg font-black text-rose-600">{{ number_format($overviewExtraStats['chronic_total']) }}</div>
                    </a>
                    <a href="{{ route('backend.patients.index', ['view' => 'list', 'discharge_status' => 'inactive_all', 'batch' => request('batch'), 'unit' => request('unit')]) }}" 
                       class="p-2 rounded-xl bg-orange-50/70 hover:bg-orange-100 border border-orange-200 transition-colors">
                        <div class="text-[10px] font-bold text-orange-700 truncate">ไม่มา/ต้องโทษ</div>
                        <div class="text-lg font-black text-orange-600">{{ number_format($overviewExtraStats['inactive_total'] ?? 0) }}</div>
                    </a>
                    <a href="{{ route('backend.military_discharges.index', ['intake_batch' => request('batch', 'all')]) }}" 
                       class="p-2 rounded-xl bg-indigo-50/70 hover:bg-indigo-100 border border-indigo-200 transition-colors">
                        <div class="text-[10px] font-bold text-indigo-700 truncate">ยื่นนำปลด</div>
                        <div class="text-lg font-black text-indigo-600">{{ number_format($overviewExtraStats['discharge_total']) }}</div>
                    </a>
                </div>
            </div>
            @else
            <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-indigo-600 uppercase"><i class="fa-solid fa-folder-open mr-1.5"></i>สถานะกำลังพล & รายงาน</span>
                </div>
                <div class="grid grid-cols-2 gap-2 my-1">
                    <a href="{{ route('backend.patients.index', ['view' => 'list', 'discharge_status' => 'inactive_all', 'batch' => request('batch'), 'unit' => request('unit')]) }}" class="p-2.5 rounded-xl bg-orange-50/70 hover:bg-orange-100 border border-orange-200 transition-colors flex flex-col justify-center">
                        <div class="text-xs font-bold text-orange-800"><i class="fa-solid fa-user-xmark mr-1"></i>ไม่มารายงานตัว/อื่นๆ</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">จำนวน {{ number_format($overviewExtraStats['inactive_total'] ?? 0) }} นาย</div>
                    </a>
                    <a href="{{ route('backend.military_discharges.index') }}" class="p-2.5 rounded-xl bg-emerald-50/70 hover:bg-emerald-100 border border-emerald-200 transition-colors flex flex-col justify-center">
                        <div class="text-xs font-bold text-emerald-700"><i class="fa-solid fa-folder-tree mr-1"></i>เอกสารนำปลด</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">ขึ้นทะเบียน {{ number_format($overviewExtraStats['discharge_total']) }} นาย</div>
                    </a>
                </div>
            </div>
            @endif
        </div>
        @endif

        <!-- โซน 1.8: แผงสรุปและกรองด่วน "สถานะการคัดกรองแรกรับ" & "สิทธิการรักษาพยาบาล" -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fade-in-up">
            <!-- กล่องซ้าย: สถานะการคัดกรองแรกรับ -->
            @if(isset($screeningStats))
            <div class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </div>
                            <h3 class="text-sm md:text-base font-bold text-slate-800">สถานะการคัดกรองแรกรับ (เฉพาะผู้มารายงานตัว)</h3>
                        </div>
                        <span class="text-xs font-bold bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full border border-emerald-100">
                            คัดกรองแล้ว {{ number_format($screeningStats['screened']) }} / {{ number_format($screeningStats['total']) }} นาย
                        </span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <button type="button" onclick="selectQuickScreeningFilter('green')" 
                                class="p-3 rounded-2xl border border-emerald-200 bg-emerald-50/50 hover:bg-emerald-500 text-emerald-700 hover:text-white transition-all text-left group shadow-sm">
                            <div class="flex items-center justify-between text-xs font-bold opacity-80 mb-1">
                                <span>ปกติ (เขียว)</span>
                                <i class="fa-solid fa-shield-virus"></i>
                            </div>
                            <div class="text-xl font-black">{{ number_format($screeningStats['green']) }}</div>
                        </button>

                        <button type="button" onclick="selectQuickScreeningFilter('yellow')" 
                                class="p-3 rounded-2xl border border-amber-200 bg-amber-50/50 hover:bg-amber-500 text-amber-700 hover:text-white transition-all text-left group shadow-sm">
                            <div class="flex items-center justify-between text-xs font-bold opacity-80 mb-1">
                                <span>กลุ่มเสี่ยง</span>
                                <i class="fa-solid fa-head-side-cough"></i>
                            </div>
                            <div class="text-xl font-black">{{ number_format($screeningStats['yellow']) }}</div>
                        </button>

                        <button type="button" onclick="selectQuickScreeningFilter('red')" 
                                class="p-3 rounded-2xl border border-rose-200 bg-rose-50/50 hover:bg-rose-500 text-rose-700 hover:text-white transition-all text-left group shadow-sm">
                            <div class="flex items-center justify-between text-xs font-bold opacity-80 mb-1">
                                <span>พบเชื้อ (แดง)</span>
                                <i class="fa-solid fa-virus-covid"></i>
                            </div>
                            <div class="text-xl font-black">{{ number_format($screeningStats['red']) }}</div>
                        </button>

                        <button type="button" onclick="selectQuickScreeningFilter('unscreened')" 
                                class="p-3 rounded-2xl border border-slate-200 bg-slate-50 hover:bg-slate-800 text-slate-600 hover:text-white transition-all text-left group shadow-sm">
                            <div class="flex items-center justify-between text-xs font-bold opacity-80 mb-1">
                                <span>รอคัดกรอง</span>
                                <i class="fa-regular fa-clock"></i>
                            </div>
                            <div class="text-xl font-black">{{ number_format($screeningStats['unscreened']) }}</div>
                        </button>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-3"><i class="fa-solid fa-hand-pointer mr-1 text-indigo-400"></i>คลิกที่กล่องแต่ละสถานะเพื่อเปิดดูรายชื่อพลทหารในหมวดนั้นทันที</p>
            </div>
            @endif

            <!-- กล่องขวา: สัดส่วนสิทธิการรักษาพยาบาล (HosXP) -->
            @if(isset($pttypeCounts))
            <div class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-shield-heart"></i>
                            </div>
                            <h3 class="text-sm md:text-base font-bold text-slate-800">แยกตามสิทธิการรักษาพยาบาล (HosXP)</h3>
                        </div>
                        <button type="button" onclick="selectQuickPttypeFilter('')" class="text-xs font-bold text-indigo-600 hover:underline">
                            ดูทุกสิทธิ
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2 max-h-[115px] overflow-y-auto pr-1">
                        @foreach($pttypeCounts as $ptStat)
                            @php
                                $isUnsyncedPt = ($ptStat->right_key === 'unsynced');
                                $ptLabel = $isUnsyncedPt ? 'ยังไม่ซิงค์สิทธิ HosXP' : $ptStat->right_key;
                            @endphp
                            <button type="button" onclick="selectQuickPttypeFilter('{{ $ptStat->right_key }}')" 
                                    class="flex items-center border rounded-xl px-3 py-1.5 shadow-sm transition-all cursor-pointer group {{ $isUnsyncedPt ? 'bg-amber-50/70 border-amber-200 hover:border-amber-400 text-amber-800' : 'bg-slate-50/80 border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/40 text-slate-700' }}">
                                <i class="fa-solid {{ $isUnsyncedPt ? 'fa-clock text-amber-500' : 'fa-shield-heart text-emerald-500' }} text-xs mr-1.5"></i>
                                <span class="text-xs font-bold truncate max-w-[180px]" title="{{ $ptLabel }}">{{ $ptLabel }}</span>
                                <div class="w-px h-3.5 mx-2 {{ $isUnsyncedPt ? 'bg-amber-200' : 'bg-slate-200' }}"></div>
                                <span class="text-xs font-black {{ $isUnsyncedPt ? 'text-amber-700' : 'text-indigo-600' }}">{{ number_format($ptStat->total) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-3"><i class="fa-solid fa-hand-pointer mr-1 text-indigo-400"></i>คลิกที่ป้ายชื่อสิทธิการรักษาเพื่อเปิดดูรายชื่อกำลังพลในสิทธินั้นๆ</p>
            </div>
            @endif
        </div>

        <!-- โซน 1.5: สัดส่วนกำลังพล แยกตามผลัดและหน่วย -->
        @if(isset($batchUnitStats) && $batchUnitStats->count() > 0)
        <div class="animate-fade-in-up">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6 px-1">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-chart-pie text-indigo-500 text-lg"></i>
                        <h3 class="text-base font-bold text-slate-800 tracking-wide">สัดส่วนกำลังพล แยกตามผลัดและหน่วยฝึก</h3>
                    </div>
                    <div class="bg-indigo-50 text-indigo-600 text-xs font-bold px-3 py-1.5 rounded-full flex items-center gap-1.5 shadow-sm w-fit">
                        <i class="fa-solid fa-clock-rotate-left"></i> แสดงข้อมูล {{ $batchUnitStats->count() }} ผลัดล่าสุด
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    @foreach($batchUnitStats as $batch => $units)
                        <div class="bg-slate-50/70 rounded-2xl p-5 border border-slate-100 hover:border-indigo-100 hover:shadow-sm transition-all">
                            <div class="font-black text-slate-800 mb-4 flex items-center justify-between border-b border-slate-200/60 pb-3">
                                <span class="bg-indigo-600 text-white text-xs px-3 py-1.5 rounded-lg shadow-sm">
                                    ผลัด {{ $batch ?: 'ไม่ระบุ' }}
                                </span>
                                <span class="text-xs text-slate-500 font-bold bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm">
                                    รวม {{ $units->sum('total') }} นาย
                                </span>
                            </div>
                            
                            <div class="flex flex-wrap gap-2.5">
                                @foreach($units as $stat)
                                    <a href="{{ route('backend.patients.index', ['view' => 'list', 'unit' => $stat->military_unit_id, 'batch' => $batch]) }}" 
                                       onclick="event.preventDefault(); selectUnitAndBatchQuickFilter('{{ $stat->military_unit_id }}', '{{ $batch }}');"
                                       class="flex items-center border rounded-xl px-3 py-1.5 shadow-sm transition-all cursor-pointer group bg-white border-slate-200 hover:border-indigo-400 hover:shadow-md" 
                                       title="คลิกเพื่อดูรายชื่อเฉพาะหน่วยและผลัดนี้">
                                        <span class="text-xs font-bold text-slate-600 group-hover:text-indigo-700 transition-colors">
                                            {{ $stat->militaryUnit->short_name ?? $stat->militaryUnit->name ?? 'ไม่ระบุ' }}
                                        </span>
                                        <div class="w-px h-3.5 mx-2.5 bg-slate-200"></div>
                                        <span class="text-xs font-black text-indigo-600">{{ $stat->total }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 text-center">
                    <button type="button" onclick="showAllBatchStatsModal()" 
                            class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 rounded-xl text-xs font-bold transition-all border border-slate-200 hover:border-indigo-200 shadow-sm">
                        <i class="fa-solid fa-layer-group text-indigo-500"></i> ดูสถิติผลัดทั้งหมด ({{ isset($allBatchUnitStats) ? $allBatchUnitStats->count() : 0 }} ผลัด)
                    </button>
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- 📊 แทรก Executive Analytics Bento Grid ไว้ด้านนอก #realtime-overview-container เพื่อไม่ให้ถูก Real-time Polling ลบกราฟ --}}
    @include('backend.patients.partials.overview_charts')

    @endif <!-- จบส่วนเฉพาะโหมด Overview -->


    <!-- ========================================================================= -->
    <!-- 🚨 โซนที่ 3: ตารางเคสที่กำลังรักษา (High Priority - Responsive Cards + Table) -->
    <!-- ========================================================================= -->
    <div id="realtime-active-cases-container">
        @if(isset($activePatients) && $activePatients->count() > 0)
        @php
            $getActivePttypeClass = function (?string $pttypeName): string {
                if (empty($pttypeName)) return 'bg-amber-50/80 text-amber-700 border-amber-200 font-medium';
                $n = mb_strtolower($pttypeName);
                if (str_contains($n, 'บัตรทอง') || str_contains($n, 'หลักประกันสุขภาพ') || str_contains($n, 'uc') || str_contains($n, 'ถ้วนหน้า')) {
                    return 'bg-amber-100 text-amber-900 border-amber-400 font-bold';
                }
                if (str_contains($n, 'ประกันสังคม')) {
                    return 'bg-blue-600 text-white border-blue-700 font-bold';
                }
                if (str_contains($n, 'ข้าราชการพลเรือน') || (str_contains($n, 'ข้าราชการ') && !str_contains($n, 'ทหาร'))) {
                    return 'bg-sky-100 text-sky-800 border-sky-300 font-bold';
                }
                if (str_contains($n, 'ครอบครัว')) {
                    return 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold';
                }
                if (str_contains($n, 'ทหาร') || str_contains($n, 'กองทัพ') || str_contains($n, 'กลาโหม') || str_contains($n, 'พลฯ')) {
                    return 'bg-emerald-800 text-white border-emerald-900 font-bold';
                }
                return 'bg-yellow-100 text-yellow-900 border-yellow-300 font-bold';
            };
        @endphp
        <div class="animate-fade-in-up">
            <h3 class="text-base sm:text-lg font-black text-slate-800 mb-3 sm:mb-4 flex items-center gap-2 px-1">
                <i class="fa-solid fa-circle-exclamation text-rose-500 animate-pulse"></i> กำลังรับการรักษา (Active Cases)
                <span class="px-2.5 py-0.5 text-xs font-black bg-rose-100 text-rose-700 border border-rose-200 rounded-full">
                    {{ $activePatients->count() }} ราย
                </span>
            </h3>

            <div class="bg-white rounded-3xl shadow-sm border-2 border-rose-100 overflow-hidden relative">
                
                <!-- 📱 โหมดมือถือ: การ์ดเคสกำลังรักษา อ่านง่ายเต็มจอ -->
                <div class="md:hidden divide-y divide-rose-100/80">
                    @foreach ($activePatients as $activePatient)
                        @php
                            $latest = $activePatient->latestMews;
                            $isEmer = $latest && $latest->risk_level === 'emergency';
                            $isObs  = $latest && $latest->risk_level === 'observe';
                            $badgeClass = $isEmer ? 'bg-rose-600 text-white' : ($isObs ? 'bg-amber-500 text-white' : 'bg-emerald-600 text-white');
                            $borderLeft = $isEmer ? 'border-l-rose-500' : ($isObs ? 'border-l-amber-400' : 'border-l-emerald-500');
                            $fullActiveName = $activePatient->rank . $activePatient->first_name . ' ' . $activePatient->last_name;
                            $unitActiveName = $activePatient->militaryUnit->short_name ?? $activePatient->militaryUnit->name ?? 'ไม่ระบุ';
                        @endphp
                        <div class="p-4 border-l-4 {{ $borderLeft }} space-y-2.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-slate-900 font-black text-base leading-snug">{{ $fullActiveName }}</div>
                                    <div class="text-xs text-slate-600 font-semibold mt-0.5">
                                        <i class="fa-solid fa-tent text-indigo-500 mr-1"></i>{{ $unitActiveName }}
                                        <span class="text-indigo-600 font-bold ml-1">(ผลัด {{ $activePatient->intake_batch ?? '-' }})</span>
                                    </div>
                                </div>
                                <span class="{{ $badgeClass }} text-xs font-black px-2.5 py-1.5 rounded-xl inline-flex items-center gap-1 shadow-sm shrink-0">
                                    <i class="fa-solid fa-heart-pulse animate-pulse text-[11px]"></i> MEWS {{ $latest->total_score ?? 0 }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                                <span class="font-mono text-slate-500 bg-slate-50 px-2 py-0.5 rounded border border-slate-200"><i class="fa-regular fa-id-card mr-1"></i>{{ $activePatient->cid }}</span>
                                <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded font-bold">HN: {{ $activePatient->hn ?? '-' }}</span>
                                @if($activePatient->pttype_name)
                                    <span class="border px-2 py-0.5 rounded {{ $getActivePttypeClass($activePatient->pttype_name) }}">
                                        <i class="fa-solid fa-shield-heart mr-1"></i>{{ $activePatient->pttype_name }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-rose-50">
                                <span class="text-[11px] text-slate-400 font-medium">
                                    <i class="fa-regular fa-clock mr-1"></i>อัปเดต {{ $latest ? $latest->updated_at->diffForHumans() : '-' }}
                                </span>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($latest)
                                        <a href="{{ route('backend.mews.show', $latest->id) }}" class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm inline-flex items-center gap-1.5">
                                            <i class="fa-solid fa-bed-pulse"></i> ดูเคส
                                        </a>
                                    @endif
                                    <a href="{{ route('backend.patients.show', $activePatient->id) }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold inline-flex items-center gap-1">
                                        <i class="fa-solid fa-address-card"></i> แฟ้ม
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- 💻 โหมดคอมพิวเตอร์: ตารางมาตรฐาน (เก็บ .active-case-row ไว้สำหรับ Real-time Detection) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full min-w-[780px] text-sm text-left text-slate-600">
                        <thead class="text-xs text-rose-800 uppercase bg-rose-50/50 border-b border-rose-100 whitespace-nowrap">
                            <tr>
                                <th class="px-6 py-4 font-bold">ข้อมูลผู้ป่วย</th>
                                <th class="px-6 py-4 font-bold">สังกัด / ผลัด</th>
                                <th class="px-6 py-4 font-bold text-center">สถานะ MEWS ล่าสุด</th>
                                <th class="px-6 py-4 font-bold text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rose-100/60">
                            @foreach ($activePatients as $activePatient)
                                @php
                                    $latest = $activePatient->latestMews;
                                    $isEmer = $latest && $latest->risk_level === 'emergency';
                                    $isObs  = $latest && $latest->risk_level === 'observe';
                                    $badgeClass = $isEmer ? 'bg-rose-500 text-white' : ($isObs ? 'bg-amber-400 text-white' : 'bg-emerald-500 text-white');
                                    $fullActiveName = $activePatient->rank . $activePatient->first_name . ' ' . $activePatient->last_name;
                                    $unitActiveName = $activePatient->militaryUnit->short_name ?? $activePatient->militaryUnit->name ?? 'ไม่ระบุ';
                                @endphp
                                <tr class="active-case-row hover:bg-rose-50/30 transition-colors"
                                    data-mews-id="{{ $latest->id ?? $activePatient->id }}"
                                    data-patient-name="{{ $fullActiveName }}"
                                    data-unit-name="{{ $unitActiveName }}"
                                    data-mews-score="{{ $latest->total_score ?? 0 }}"
                                    data-risk-level="{{ $latest->risk_level ?? 'normal' }}"
                                    data-case-url="{{ $latest ? route('backend.mews.show', $latest->id) : route('backend.patients.show', $activePatient->id) }}">
                                    <td class="px-6 py-4">
                                        <div class="text-slate-900 font-bold text-base">{{ $fullActiveName }}</div>
                                        <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                                            <span class="font-mono"><i class="fa-regular fa-id-card text-slate-400"></i> {{ $activePatient->cid }}</span>
                                            <span class="bg-slate-100 px-2 py-0.5 rounded font-semibold">HN: {{ $activePatient->hn ?? '-' }}</span>
                                            @if($activePatient->pttype_name)
                                                <span class="border px-2 py-0.5 rounded text-[11px] {{ $getActivePttypeClass($activePatient->pttype_name) }}">
                                                    <i class="fa-solid fa-shield-heart mr-1"></i>{{ $activePatient->pttype_name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-slate-700">{{ $unitActiveName }}</div>
                                        <div class="text-xs text-indigo-600 font-semibold mt-0.5">ผลัดที่ {{ $activePatient->intake_batch ?? '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <span class="{{ $badgeClass }} text-xs font-black px-3 py-1.5 rounded-xl inline-flex items-center gap-1.5 shadow-sm">
                                            <i class="fa-solid fa-heart-pulse animate-pulse"></i> MEWS: {{ $latest->total_score ?? 0 }}
                                        </span>
                                        <div class="text-[11px] text-slate-400 mt-1">
                                            อัปเดต {{ $latest ? $latest->updated_at->diffForHumans() : '-' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2">
                                            @if($latest)
                                                <a href="{{ route('backend.mews.show', $latest->id) }}" class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                                                    <i class="fa-solid fa-bed-pulse"></i> ดูเคส
                                                </a>
                                            @endif
                                            <a href="{{ route('backend.patients.show', $activePatient->id) }}" class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-blue-500 hover:text-white flex items-center justify-center transition-all shadow-sm" title="แฟ้มประวัติ">
                                                <i class="fa-solid fa-address-card"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        @endif
    </div>

    <!-- ========================================================================= -->
    <!-- 👥 โหมดที่ 2: หน้าทะเบียนรายชื่อพลทหาร (แสดงเฉพาะเมื่อ view !== 'overview') -->
    <!-- ========================================================================= -->
    @if(!$isOverviewMode)

        @php
            // นับจำนวนตัวกรองขั้นสูงที่กำลังถูกเลือกใช้งานอยู่
            $advancedActiveCount = collect(['appointment_filter', 'screening_status', 'hn_status', 'pttype', 'discharge_status', 'chronic_status'])
                ->filter(fn($f) => request()->filled($f))
                ->count();
        @endphp

        <!-- 🌟 โซนที่ 2 (ดีไซน์ใหม่ โปร่งโล่ง 100%): การ์ดค้นหาและตัวกรองหลัก -->
        <div class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-200/70 space-y-4">
            
            <form id="patient-filter-form" action="{{ route('backend.patients.index') }}" method="GET" class="space-y-4">
                <input type="hidden" name="view" value="list">

                <!-- 🔥 แถวที่ 1: ให้พื้นที่ 100% เต็มความกว้างสำหรับ ค้นหา + ผลัด + หน่วยฝึก + สถานะรักษา + ปุ่มตัวกรองเพิ่มเติม -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                    
                    <!-- 1. ช่องค้นหาอัจฉริยะ (4 คอลัมน์ — กว้างสบายตา) -->
                    <div class="relative sm:col-span-2 lg:col-span-4">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i id="search-status-icon" class="fa-solid fa-magnifying-glass text-indigo-500 text-sm transition-all"></i>
                        </div>
                        <input type="text" 
                               id="live-search-input" 
                               name="search" 
                               value="{{ request('search') }}" 
                               autocomplete="off"
                               placeholder="ค้นหาชื่อ-สกุล, เลขบัตร ปชช., HN, ตำหนิ..." 
                               class="w-full h-11 pl-11 pr-9 bg-slate-50/80 hover:bg-slate-50 focus:bg-white border border-slate-200 focus:border-indigo-500 rounded-2xl text-sm font-medium text-slate-800 placeholder:text-slate-400 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all">
                        <button type="button" 
                                id="clear-search-btn" 
                                onclick="clearLiveSearch()" 
                                style="{{ request('search') ? 'display: flex;' : 'display: none;' }}"
                                class="absolute inset-y-0 right-0 pr-3.5 items-center text-slate-400 hover:text-rose-500 transition-colors"
                                title="ล้างคำค้นหา">
                            <i class="fa-solid fa-circle-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- 2. ผลัดทหาร (2 คอลัมน์) -->
                    <div class="relative lg:col-span-2">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i class="fa-solid fa-layer-group text-emerald-500 text-xs"></i>
                        </div>
                        <select name="batch" id="filter-batch-select" onchange="triggerLiveFilter()" 
                                class="w-full h-11 pl-9 pr-8 bg-slate-50/80 hover:bg-slate-50 focus:bg-white border border-slate-200 focus:border-indigo-500 rounded-2xl text-sm font-semibold text-slate-700 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all cursor-pointer">
                            <option value="">ทุกผลัดทหาร</option>
                            @foreach($availableBatches as $b)
                                <option value="{{ $b }}" {{ request('batch') == $b ? 'selected' : '' }}>ผลัดที่ {{ $b }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- 3. หน่วยฝึกทหาร (2 คอลัมน์) -->
                    <div class="relative lg:col-span-2">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i class="fa-solid fa-tent text-indigo-500 text-xs"></i>
                        </div>
                        <select name="unit" id="filter-unit-select" onchange="triggerLiveFilter()" 
                                class="w-full h-11 pl-9 pr-8 bg-slate-50/80 hover:bg-slate-50 focus:bg-white border border-slate-200 focus:border-indigo-500 rounded-2xl text-sm font-semibold text-slate-700 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all cursor-pointer truncate">
                            <option value="">ทุกหน่วยฝึก</option>
                            @foreach($allUnits as $u)
                                <option value="{{ $u->id }}" {{ request('unit') == $u->id ? 'selected' : '' }}>{{ $u->short_name ?? $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- 4. สถานะรักษา MEWS (2 คอลัมน์) -->
                    <div class="relative lg:col-span-2">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i class="fa-solid fa-heart-pulse text-rose-500 text-xs"></i>
                        </div>
                        <select name="mews_status" id="filter-mews-select" onchange="triggerLiveFilter()" 
                                class="w-full h-11 pl-9 pr-8 bg-slate-50/80 hover:bg-slate-50 focus:bg-white border border-slate-200 focus:border-indigo-500 rounded-2xl text-sm font-semibold text-slate-700 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all cursor-pointer truncate">
                            <option value="">ทุกสถานะรักษา</option>
                            <option value="pending_er" {{ request('mews_status') === 'pending_er' ? 'selected' : '' }}>🚨 กำลังรักษา (ER)</option>
                            <option value="pending_opd" {{ request('mews_status') === 'pending_opd' ? 'selected' : '' }}>🩺 รอตรวจ (OPD)</option>
                            <option value="hospitalized" {{ request('mews_status') === 'hospitalized' ? 'selected' : '' }}>🛏️ Admit/Refer (ยังไม่กลับ)</option>
                            <option value="admitted_only" {{ request('mews_status') === 'admitted_only' ? 'selected' : '' }}>🏥 เฉพาะ Admit รพ.ค่ายฯ</option>
                            <option value="admit_cumulative" {{ request('mews_status') === 'admit_cumulative' ? 'selected' : '' }}>📊 เคย Admit/Refer สะสมทั้งหมด</option>
                            <option value="refer_only" {{ request('mews_status') === 'refer_only' ? 'selected' : '' }}>🚑 เฉพาะส่งต่อ Refer</option>
                            <option value="returned_unit" {{ request('mews_status') === 'returned_unit' ? 'selected' : '' }}>🏠 กลับหน่วยฝึกแล้ว</option>
                            <option value="recovered" {{ request('mews_status') === 'recovered' ? 'selected' : '' }}>✅ จำหน่ายกลับหน่วยปกติ</option>
                            <option value="has_history" {{ request('mews_status') === 'has_history' ? 'selected' : '' }}>📋 มีประวัติป่วยทั้งหมด</option>
                            <option value="no_history" {{ request('mews_status') === 'no_history' ? 'selected' : '' }}>⚪ ไม่มีประวัติป่วย</option>
                        </select>
                    </div>

                    <!-- 5. ปุ่มตัวกรองเพิ่มเติม (2 คอลัมน์) -->
                    <div class="lg:col-span-2">
                        <button type="button" onclick="toggleAdvancedFilterDrawer()" id="btn-toggle-adv-filters"
                                class="w-full h-11 px-4 rounded-2xl border text-sm font-bold transition-all flex items-center justify-center gap-2 {{ $advancedActiveCount > 0 ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-slate-50/80 hover:bg-slate-100 text-slate-700 border-slate-200' }}">
                            <i class="fa-solid fa-sliders text-xs"></i>
                            <span>ตัวกรองเพิ่มเติม</span>
                            <span id="adv-filter-badge" class="{{ $advancedActiveCount > 0 ? 'inline-flex' : 'hidden' }} px-1.5 py-0.2 bg-white text-indigo-700 rounded-full text-[10px] font-black">
                                {{ $advancedActiveCount }}
                            </span>
                        </button>
                    </div>

                </div>

                <!-- ⚡ แถวที่ 2: ปุ่มทางลัดกรองด่วน 1 คลิก (Quick Filter Chips) -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-100">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-slate-400 mr-1"><i class="fa-solid fa-bolt text-amber-500 mr-1"></i>กรองด่วน:</span>

                        <button type="button" onclick="resetAllLiveFilters()" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200">
                            ทั้งหมด
                        </button>

                        <button type="button" onclick="applyQuickChipFilter('mews_status', 'pending_er')" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all bg-rose-50/80 hover:bg-rose-100 text-rose-700 border-rose-200/80">
                            🚨 กำลังรักษา (ER)
                        </button>

                        <button type="button" onclick="applyQuickChipFilter('mews_status', 'pending_opd')" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all bg-teal-50/80 hover:bg-teal-100 text-teal-700 border-teal-200/80">
                            🩺 รอตรวจ (OPD)
                        </button>
                        
                        <button type="button" onclick="applyQuickChipFilter('mews_status', 'hospitalized')" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all bg-amber-50/80 hover:bg-amber-100 text-amber-800 border-amber-200/80">
                            🛏️ Admit/Refer (ยังไม่กลับหน่วย)
                        </button>

                        <button type="button" onclick="applyQuickChipFilter('appointment_filter', 'has_upcoming')" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all bg-sky-50/80 hover:bg-sky-100 text-sky-700 border-sky-200/80">
                            📅 มีนัดพบแพทย์
                        </button>

                        <button type="button" onclick="applyQuickChipFilter('hn_status', 'unsynced')" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all bg-indigo-50/80 hover:bg-indigo-100 text-indigo-700 border-indigo-200/80">
                            ⚠️ รอซิงค์รหัส HN
                        </button>

                        <button type="button" onclick="applyQuickChipFilter('discharge_status', 'absent')" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all bg-orange-50/80 hover:bg-orange-100 text-orange-800 border-orange-200/80">
                            🟠 ไม่มารายงานตัว
                        </button>

                        <button type="button" onclick="applyQuickChipFilter('discharge_status', 'discharged')" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all bg-purple-50/80 hover:bg-purple-100 text-purple-700 border-purple-200/80">
                            🎖️ ปลดทหาร/นำปลด
                        </button>
                    </div>

                    <button type="button" onclick="resetAllLiveFilters()" class="text-xs font-bold text-rose-500 hover:text-rose-700 hover:underline flex items-center gap-1.5 py-1">
                        <i class="fa-solid fa-rotate-left"></i> ล้างตัวกรองทั้งหมด
                    </button>
                </div>

                <!-- 🛠️ ลิ้นชักตัวกรองละเอียด (แสดงเมื่อกดปุ่ม "ตัวกรองเพิ่มเติม") -->
                <div id="advanced-filter-drawer" class="{{ $advancedActiveCount > 0 ? 'grid' : 'hidden' }} grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 pt-3 border-t border-slate-100 bg-slate-50/60 p-4 rounded-2xl">
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5"><i class="fa-solid fa-calendar-check text-sky-500 mr-1"></i>การนัดพบแพทย์</label>
                        <select name="appointment_filter" id="filter-appointment-select" onchange="triggerLiveFilter()" 
                                class="w-full px-3 py-2 bg-white border border-slate-200 focus:border-indigo-500 rounded-xl text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="">ทุกสถานะนัดพบแพทย์</option>
                            <option value="has_upcoming" {{ request('appointment_filter') === 'has_upcoming' ? 'selected' : '' }}>📅 มีนัดพบแพทย์เร็วๆ นี้</option>
                            <option value="tomorrow" {{ request('appointment_filter') === 'tomorrow' ? 'selected' : '' }}>⏰ นัดพรุ่งนี้ (อีก 1 วัน)</option>
                            <option value="today" {{ request('appointment_filter') === 'today' ? 'selected' : '' }}>🚨 นัดพบแพทย์วันนี้</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5"><i class="fa-solid fa-clipboard-user text-amber-500 mr-1"></i>คัดกรองแรกรับ</label>
                        <select name="screening_status" id="filter-screening-select" onchange="triggerLiveFilter()" 
                                class="w-full px-3 py-2 bg-white border border-slate-200 focus:border-indigo-500 rounded-xl text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="">ทุกสถานะคัดกรองแรกรับ</option>
                            <option value="unscreened" {{ request('screening_status') === 'unscreened' ? 'selected' : '' }}>⏳ รอคัดกรองแรกรับ</option>
                            <option value="screened" {{ request('screening_status') === 'screened' ? 'selected' : '' }}>✅ คัดกรองแล้วทั้งหมด</option>
                            <option value="green" {{ request('screening_status') === 'green' ? 'selected' : '' }}>🟢 ปกติ (สีเขียว)</option>
                            <option value="yellow" {{ request('screening_status') === 'yellow' ? 'selected' : '' }}>🟡 กลุ่มเสี่ยง (สีเหลือง)</option>
                            <option value="red" {{ request('screening_status') === 'red' ? 'selected' : '' }}>🔴 พบเชื้อ (สีแดง)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5"><i class="fa-solid fa-hospital-user text-blue-500 mr-1"></i>สถานะรหัส HN</label>
                        <select name="hn_status" id="filter-hn-select" onchange="triggerLiveFilter()" 
                                class="w-full px-3 py-2 bg-white border border-slate-200 focus:border-indigo-500 rounded-xl text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="">ทุกสถานะ HN</option>
                            <option value="unsynced" {{ request('hn_status') === 'unsynced' ? 'selected' : '' }}>ยังไม่มี HN (รอซิงค์)</option>
                            <option value="synced" {{ request('hn_status') === 'synced' ? 'selected' : '' }}>มีรหัส HN แล้ว</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5"><i class="fa-solid fa-shield-heart text-emerald-500 mr-1"></i>สิทธิการรักษา (HosXP)</label>
                        <select name="pttype" id="filter-pttype-select" onchange="triggerLiveFilter()" 
                                class="w-full px-3 py-2 bg-white border border-slate-200 focus:border-indigo-500 rounded-xl text-xs font-bold text-slate-700 outline-none cursor-pointer truncate">
                            <option value="">ทุกสิทธิการรักษา</option>
                            <option value="unsynced" {{ request('pttype') === 'unsynced' ? 'selected' : '' }}>⚠️ ยังไม่ซิงค์สิทธิ HosXP</option>
                            @if(isset($availablePttypes))
                                @foreach($availablePttypes as $ptName)
                                    <option value="{{ $ptName }}" {{ request('pttype') === $ptName ? 'selected' : '' }}>{{ $ptName }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5"><i class="fa-solid fa-user-shield text-purple-600 mr-1"></i>สถานะกำลังพล / รายงานตัว</label>
                        <select name="discharge_status" id="filter-discharge-select" onchange="triggerLiveFilter()" 
                                class="w-full px-3 py-2 bg-white border border-slate-200 focus:border-indigo-500 rounded-xl text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="">ทุกสถานะกำลังพล</option>
                            <option value="active_duty" {{ request('discharge_status') === 'active_duty' ? 'selected' : '' }}>🟢 มารายงานตัว (ประจำการปกติ)</option>
                            <option value="absent" {{ request('discharge_status') === 'absent' ? 'selected' : '' }}>🟠 ไม่มารายงานตัว</option>
                            <option value="imprisoned" {{ request('discharge_status') === 'imprisoned' ? 'selected' : '' }}>🔴 ต้องโทษ / ดำเนินคดี</option>
                            <option value="deceased" {{ request('discharge_status') === 'deceased' ? 'selected' : '' }}>⚫ เสียชีวิต</option>
                            <option value="discharged" {{ request('discharge_status') === 'discharged' ? 'selected' : '' }}>🎖️ ปลดทหาร / นำปลด</option>
                            <option value="inactive_all" {{ request('discharge_status') === 'inactive_all' ? 'selected' : '' }}>📋 รวมกลุ่มไม่ได้ประจำการทั้งหมด</option>
                        </select>
                    </div>

                    @if($isAdmin)
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5"><i class="fa-solid fa-heart-pulse text-rose-500 mr-1"></i>โรคประจำตัว (Admin)</label>
                        <select name="chronic_status" id="filter-chronic-select" onchange="triggerLiveFilter()" 
                                class="w-full px-3 py-2 bg-white border border-slate-200 focus:border-indigo-500 rounded-xl text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="">ทุกสถานะโรคประจำตัว</option>
                            <option value="has_chronic" {{ request('chronic_status') === 'has_chronic' ? 'selected' : '' }}>⚠️ มีโรคประจำตัว/อื่นๆ</option>
                            <option value="yes" {{ request('chronic_status') === 'yes' ? 'selected' : '' }}>❤️ มีโรคประจำตัว</option>
                            <option value="other" {{ request('chronic_status') === 'other' ? 'selected' : '' }}>📌 ประวัติสุขภาพอื่นๆ</option>
                            <option value="none" {{ request('chronic_status') === 'none' ? 'selected' : '' }}>✅ ไม่มีโรคประจำตัว</option>
                        </select>
                    </div>
                    @endif

                </div>

            </form>
        </div>

        <!-- แถบจัดการหลายรายการแบบจำค่าข้ามหน้าได้ (Sticky Multi-Page Bulk Action Bar) -->
        @if($showCheckbox)
        <div id="multi-page-bulk-bar" class="hidden mb-4 p-4 bg-slate-900 text-white rounded-2xl shadow-lg border border-slate-700 flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 animate-fade-in-up sticky top-4 z-30">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 flex items-center justify-center font-black text-base shrink-0">
                    <span id="selected-count">0</span>
                </div>
                <div>
                    <div class="text-sm font-bold text-white flex items-center gap-2">
                        รายการที่เลือกไว้ <span class="text-[11px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2 py-0.5 rounded-full font-semibold">จำค่าข้ามหน้าได้</span>
                    </div>
                    <p class="text-xs text-slate-400">คุณสามารถเปิดหน้าถัดไปเพื่อติ๊กเลือกพลทหารเพิ่ม แล้วกดปุ่มจัดการในครั้งเดียวได้ครับ</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button type="button" onclick="clearAllSelectedPatients()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition-all border border-slate-600">
                    <i class="fa-solid fa-xmark mr-1"></i> ล้างที่เลือก
                </button>

                <button type="button" onclick="openCustomExportBuilderModal('selected')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-file-csv"></i> Export ที่เลือก (<span class="selected-count-text">0</span>)
                </button>

                @if($canSyncHosxp)
                <button type="button" id="btn-bulk-sync-hosxp" onclick="runBulkSyncHosxp(false, false)" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-rotate" id="bulk-sync-icon"></i> ซิงค์ HosXP ที่เลือก (<span class="selected-count-text">0</span>)
                </button>
                @endif

                @if($isAdmin)
                <form action="{{ route('backend.patients.bulk_destroy') }}" method="POST" id="bulk-delete-form" class="inline-block">
                    @csrf @method('DELETE')
                    <input type="hidden" name="patient_ids" id="bulk_patient_ids">
                    <button type="button" onclick="confirmBulkDelete(event)" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-trash-can"></i> ลบที่เลือก (<span class="selected-count-text">0</span>)
                    </button>
                </form>
                @endif
            </div>
        </div>
        @endif

        <!-- 🌟 โซนที่ 4: บัญชีรายชื่อทั้งหมด (Mobile Cards + Desktop Table + Batched IPD Query) -->
        <div id="patients-table-container" class="transition-opacity duration-200">
            @php
                // 🚀 ดึงข้อมูลเคสนอน รพ. (other_ipd) ทั้งหน้าใน 1 Query เดียว ลด N+1 Query
                $pagePatientIds = $patients->pluck('id')->all();
                $batchedActiveIpds = collect();
                if (!empty($pagePatientIds) && class_exists('\App\Models\RecruitMorbidity')) {
                    $batchedActiveIpds = \App\Models\RecruitMorbidity::whereIn('patient_id', $pagePatientIds)
                        ->where('category', 'other_ipd')
                        ->where('status', 'treating')
                        ->orderByDesc('onset_date')
                        ->orderByDesc('id')
                        ->get()
                        ->unique('patient_id')
                        ->keyBy('patient_id');
                }

                // 🎨 ฟังก์ชันกำหนดสีป้ายสิทธิการรักษาตามหมวดหมู่มาตรฐาน
                $getPttypeBadgeClass = function (?string $pttypeName): string {
                    if (empty($pttypeName)) {
                        return 'bg-amber-50/70 text-amber-700 border-amber-200 font-medium';
                    }
                    $n = mb_strtolower($pttypeName);
                    if (str_contains($n, 'บัตรทอง') || str_contains($n, 'หลักประกันสุขภาพ') || str_contains($n, 'uc') || str_contains($n, 'ถ้วนหน้า')) {
                        return 'bg-amber-100 text-amber-900 border-amber-400 font-bold';
                    }
                    if (str_contains($n, 'ประกันสังคม')) {
                        return 'bg-blue-600 text-white border-blue-700 font-bold';
                    }
                    if (str_contains($n, 'ข้าราชการพลเรือน') || (str_contains($n, 'ข้าราชการ') && !str_contains($n, 'ทหาร'))) {
                        return 'bg-sky-100 text-sky-800 border-sky-300 font-bold';
                    }
                    if (str_contains($n, 'ครอบครัว')) {
                        return 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold';
                    }
                    if (str_contains($n, 'ทหาร') || str_contains($n, 'กองทัพ') || str_contains($n, 'กลาโหม') || str_contains($n, 'พลฯ')) {
                        return 'bg-emerald-800 text-white border-emerald-900 font-bold';
                    }
                    return 'bg-yellow-100 text-yellow-900 border-yellow-300 font-bold';
                };
            @endphp

            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 mb-4 px-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-list text-indigo-500"></i> ทะเบียนรายชื่อกำลังพล
                    </h3>
                    <span id="current-filtered-total-badge" data-total="{{ $patients->total() }}" class="text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1 rounded-full shadow-sm">
                        พบ {{ number_format($patients->total()) }} นาย
                    </span>
                </div>

                <!-- 📥 ปุ่มจัดการข้อมูลตาราง -->
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="openCustomExportBuilderModal('filtered')" 
                            class="flex-1 sm:flex-none justify-center px-4 py-2 bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs sm:text-sm font-bold rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-file-export text-indigo-600"></i> ส่งออกข้อมูล (Export)
                    </button>

                    @if($canImport)
                        <a href="{{ route('backend.patients.create') }}" class="flex-1 sm:flex-none justify-center px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-user-plus text-xs"></i> เพิ่มรายบุคคล
                        </a>
                        <a href="{{ route('backend.patients.import') }}" class="flex-1 sm:flex-none justify-center px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs sm:text-sm font-bold rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-file-excel text-emerald-600"></i> นำเข้า Excel
                        </a>
                    @endif
                </div>
            </div>
            
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">

                <!-- =========================================================== -->
                <!-- 📱 โหมดมือถือ: แสดงเป็นการ์ดรายบุคคล (อ่านง่ายไม่บีบข้อความ) -->
                <!-- =========================================================== -->
                <div class="md:hidden">
                    @if($showCheckbox && $patients->count() > 0)
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                            <label class="inline-flex items-center gap-2.5 text-xs font-bold text-slate-700 cursor-pointer select-none">
                                <input type="checkbox" class="check-all-toggle rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                                <span>เลือกทั้งหมดในหน้านี้ ({{ $patients->count() }} นาย)</span>
                            </label>
                            <span class="text-[11px] text-slate-400 font-semibold">หน้า {{ $patients->currentPage() }}/{{ $patients->lastPage() }}</span>
                        </div>
                    @endif

                    @php
                        $mCurrentBatch = null;
                        $mCurrentUnit  = null;
                    @endphp

                    @forelse ($patients as $patient)
                        @php
                            $patientUnitName = $patient->militaryUnit->name ?? $patient->company ?? 'ไม่ระบุหน่วยฝึก';
                            $sc = $patient->recruitScreening;
                            $dc = $patient->militaryDischarge;
                            $dutyStat = $patient->duty_status ?? 'active';
                            $isInactiveDuty = in_array($dutyStat, ['absent', 'imprisoned', 'deceased']);
                            $fullSoldierName = ($patient->rank ?? '') . ($patient->patient_name ?: ($patient->first_name . ' ' . $patient->last_name));

                            $lm = $patient->latestMews;
                            $activeIpd = $patient->activeIpdMorbidity ?? $batchedActiveIpds->get($patient->id);
                            $isMewsPending = $lm && $lm->status === 'pending';
                            $isMewsAdmitOrRefer = $lm && $lm->status === 'completed' && in_array($lm->discharge_type, ['admitted', 'refer']);
                            $isMewsReturned = $lm && !empty($lm->returned_to_unit_at);
                            $rawLmSyms = $lm ? (is_string($lm->symptoms) ? json_decode($lm->symptoms, true) : ($lm->symptoms ?? [])) : [];
                            $hasLmEmergencySym = is_array($rawLmSyms) && count($rawLmSyms) > 0;
                            $isErPendingCase = $isMewsPending && (
                                $lm->assessment_type === 'er'
                                || in_array($lm->risk_level, ['observe', 'emergency'])
                                || $hasLmEmergencySym
                            );
                        @endphp

                        @if($mCurrentBatch !== $patient->intake_batch)
                            @php
                                $mCurrentBatch = $patient->intake_batch;
                                $mCurrentUnit = null;
                            @endphp
                            <div class="bg-slate-800 px-4 py-2 text-white font-bold text-xs flex items-center gap-2">
                                <i class="fa-solid fa-calendar-days text-slate-400"></i>
                                <span>ทหารกองประจำการ ผลัดที่ {{ $mCurrentBatch ?? 'ไม่ระบุ' }}</span>
                            </div>
                        @endif

                        @if($mCurrentUnit !== $patientUnitName)
                            @php $mCurrentUnit = $patientUnitName; @endphp
                            <div class="bg-indigo-50/70 px-4 py-2 border-y border-indigo-100 text-indigo-700 font-bold text-xs flex items-center gap-1.5">
                                <i class="fa-solid fa-tent text-indigo-400"></i>
                                <span class="truncate">{{ $mCurrentUnit }}</span>
                            </div>
                        @endif

                        <div class="p-4 border-b border-slate-100 last:border-b-0 space-y-3 {{ $isInactiveDuty ? 'bg-slate-50/70 opacity-85' : 'bg-white' }}">
                            
                            <!-- แถว 1: Checkbox + ชื่อ-สกุล + ป้ายสถานะพิเศษ -->
                            <div class="flex items-start gap-3">
                                @if($showCheckbox)
                                    <div class="pt-1 shrink-0">
                                        <input type="checkbox" class="patient-checkbox rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer" value="{{ $patient->id }}">
                                    </div>
                                @endif

                                <div class="flex-1 min-w-0 space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <a href="{{ route('backend.patients.show', $patient->id) }}"
                                           class="js-patient-name-{{ $patient->id }} text-slate-900 hover:text-indigo-600 font-black text-base leading-snug {{ $dutyStat === 'deceased' ? 'line-through text-slate-500' : '' }}">
                                            {{ $fullSoldierName }}
                                        </a>

                                        @if($dutyStat === 'absent')
                                            <button type="button" onclick="openDutyStatusModal({{ $patient->id }}, '{{ addslashes($fullSoldierName) }}', '{{ $dutyStat }}', '{{ addslashes($patient->duty_status_note ?? '') }}')"
                                                    class="bg-orange-100 text-orange-800 border border-orange-300 font-black px-2 py-0.5 rounded-lg text-[10px] inline-flex items-center gap-1">
                                                <i class="fa-solid fa-user-xmark text-orange-600"></i> ไม่มารายงานตัว
                                            </button>
                                        @elseif($dutyStat === 'imprisoned')
                                            <button type="button" onclick="openDutyStatusModal({{ $patient->id }}, '{{ addslashes($fullSoldierName) }}', '{{ $dutyStat }}', '{{ addslashes($patient->duty_status_note ?? '') }}')"
                                                    class="bg-rose-100 text-rose-800 border border-rose-300 font-black px-2 py-0.5 rounded-lg text-[10px] inline-flex items-center gap-1">
                                                <i class="fa-solid fa-gavel text-rose-600"></i> ต้องโทษ
                                            </button>
                                        @elseif($dutyStat === 'deceased')
                                            <button type="button" onclick="openDutyStatusModal({{ $patient->id }}, '{{ addslashes($fullSoldierName) }}', '{{ $dutyStat }}', '{{ addslashes($patient->duty_status_note ?? '') }}')"
                                                    class="bg-slate-900 text-white font-black px-2 py-0.5 rounded-lg text-[10px] inline-flex items-center gap-1">
                                                <i class="fa-solid fa-ribbon text-slate-300"></i> เสียชีวิต
                                            </button>
                                        @endif
                                    </div>

                                    <!-- เลข ปชช. + HN + สิทธิการรักษา -->
                                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                                        <span class="font-mono text-slate-500 bg-slate-50 px-2 py-0.5 rounded border border-slate-200/80">
                                            <i class="fa-regular fa-id-card text-slate-400 mr-1"></i>{{ $patient->cid }}
                                        </span>
                                        <span class="js-patient-hn-{{ $patient->id }} {{ $patient->hn ? 'bg-blue-50 text-blue-700 border border-blue-200 font-bold' : 'bg-slate-100 text-slate-500 font-semibold' }} px-2 py-0.5 rounded">
                                            HN: {{ $patient->hn ?? '-' }}
                                        </span>
                                        <span class="js-patient-pttype-{{ $patient->id }} border px-2 py-0.5 rounded {{ $getPttypeBadgeClass($patient->pttype_name) }}">
                                            <i class="fa-solid fa-shield-heart mr-1"></i>{{ $patient->pttype_name ?: 'รอซิงค์สิทธิ' }}
                                        </span>
                                    </div>

                                    <!-- ป้ายข้อมูลคลินิกเพิ่มเติม (โรคประจำตัว / นำปลด / นัดแพทย์ / ห้องพยาบาล) -->
                                    @php
                                        $hasExtraBadges = ($isAdmin && in_array($patient->chronic_disease_status ?? 'none', ['yes', 'other']))
                                            || $dc
                                            || ($patient->upcomingAppointment && !$isInactiveDuty && !$dc)
                                            || ($patient->activeInfirmaryStay && (!$dc || $dc->process_status !== 'completed') && !$isInactiveDuty);
                                    @endphp

                                    @if($hasExtraBadges)
                                    <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                        @if($isAdmin)
                                            @if(($patient->chronic_disease_status ?? 'none') === 'yes')
                                                <span class="bg-rose-50 text-rose-700 border border-rose-200 font-bold px-2 py-0.5 rounded text-[11px]">
                                                    <i class="fa-solid fa-heart-pulse mr-1 text-rose-500"></i>โรคประจำตัว: {{ $patient->chronic_disease_detail ?: 'ระบุ' }}
                                                </span>
                                            @elseif(($patient->chronic_disease_status ?? 'none') === 'other')
                                                <span class="bg-amber-50 text-amber-700 border border-amber-200 font-bold px-2 py-0.5 rounded text-[11px]">
                                                    <i class="fa-solid fa-notes-medical mr-1 text-amber-500"></i>อื่นๆ: {{ $patient->chronic_disease_detail ?: 'ระบุ' }}
                                                </span>
                                            @endif
                                        @endif

                                        @if($dc)
                                            @if($dc->process_status === 'completed')
                                                <span class="bg-slate-800 text-white font-bold px-2 py-0.5 rounded text-[11px] inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-user-slash text-rose-400"></i> ปลดทหารแล้ว ({{ $dc->diagnosis_text }})
                                                </span>
                                            @else
                                                <span class="bg-purple-100 text-purple-800 border border-purple-300 font-bold px-2 py-0.5 rounded text-[11px] inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-folder-open text-purple-600"></i> นำปลด: {{ $dc->diagnosis_text }}
                                                </span>
                                            @endif
                                        @endif

                                        @if($patient->upcomingAppointment && !$isInactiveDuty && !$dc)
                                            @php
                                                $upApp = $patient->upcomingAppointment;
                                                $appDateStr = $upApp->appointment_date->format('Y-m-d');
                                                $isAppToday = ($appDateStr === date('Y-m-d'));
                                                $isAppTomorrow = ($appDateStr === date('Y-m-d', strtotime('+1 day')));
                                                $appDateTh = $upApp->appointment_date->locale('th')->addYears(543)->translatedFormat('j M y');
                                                $appDateFullTh = $upApp->appointment_date->locale('th')->addYears(543)->translatedFormat('j F Y');
                                                $hospShort = $upApp->hospital_type === 'public_hospital' ? ($upApp->hospital_name ?: 'รพ.สธ.') : 'รพ.ค่ายฯ';
                                                $appBadgeClass = $isAppToday
                                                    ? 'bg-rose-600 text-white border-rose-600 animate-pulse'
                                                    : ($isAppTomorrow ? 'bg-amber-500 text-white border-amber-500' : 'bg-sky-50 text-sky-700 border-sky-200');
                                                $appModalPayload = [
                                                    'app_id'        => $upApp->id,
                                                    'patient_id'    => $patient->id,
                                                    'full_name'     => $fullSoldierName,
                                                    'hn'            => $patient->hn ?: 'ยังไม่มี HN',
                                                    'unit'          => $patientUnitName,
                                                    'batch'         => $patient->intake_batch ?: '-',
                                                    'date_full'     => $appDateFullTh,
                                                    'time'          => ($upApp->appointment_time ?: '08:30') . ' น.',
                                                    'hospital_name' => $upApp->hospital_name ?: ($upApp->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายกฤษณ์สีวะรา'),
                                                    'is_camp'       => $upApp->hospital_type === 'camp_hospital',
                                                    'clinic'        => $upApp->clinic_name ?: 'ไม่ระบุคลินิก',
                                                    'doctor'        => $upApp->doctor_name ?: '-',
                                                    'cause'         => $upApp->app_cause ?: 'นัดตรวจติดตามอาการ',
                                                    'note'          => $upApp->note ?: '',
                                                    'is_today'      => $isAppToday,
                                                    'is_tomorrow'   => $isAppTomorrow,
                                                ];
                                            @endphp
                                            <button type="button" onclick='showSingleSoldierAppointmentModal(@json($appModalPayload))'
                                                    class="{{ $appBadgeClass }} border font-bold px-2 py-0.5 rounded text-[11px] inline-flex items-center gap-1 shadow-sm">
                                                <i class="fa-solid fa-calendar-day"></i>
                                                <span>{{ $isAppToday ? 'นัดวันนี้!' : ($isAppTomorrow ? 'นัดพรุ่งนี้!' : 'นัด ' . $appDateTh) }} ({{ $hospShort }})</span>
                                            </button>
                                        @endif

                                        @if($patient->activeInfirmaryStay && (!$dc || $dc->process_status !== 'completed') && !$isInactiveDuty)
                                            @php
                                                $infStay = $patient->activeInfirmaryStay;
                                                $isUnitLoc = ($infStay->location_type === 'unit_infirmary');
                                            @endphp
                                            <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $patient->intake_batch, 'unit_id' => $patient->military_unit_id]) }}"
                                               class="{{ $isUnitLoc ? 'bg-indigo-100 text-indigo-800 border-indigo-300' : 'bg-teal-100 text-teal-800 border-teal-300' }} border font-bold px-2 py-0.5 rounded text-[11px] inline-flex items-center gap-1">
                                                <i class="fa-solid {{ $isUnitLoc ? 'fa-tent-arrows-down' : 'fa-bed' }}"></i>
                                                <span>{{ $isUnitLoc ? 'ห้องพยาบาลหน่วย' : 'ฝากนอน รพ.' }}: {{ $infStay->diagnosis_text }}</span>
                                            </a>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- แถว 2: กล่องสรุปสถานะ 2 ช่อง (รักษา MEWS + คัดกรองแรกรับ) -->
                            <div class="grid grid-cols-2 gap-2 bg-slate-50/90 p-2.5 rounded-2xl border border-slate-100 text-xs">
                                <!-- ช่องซ้าย: สถานะรักษา MEWS -->
                                <div class="space-y-1">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">สถานะรักษา (MEWS)</div>
                                    @if($isMewsPending)
                                        @if($isErPendingCase)
                                            <a href="{{ route('backend.mews.show', $lm->id) }}" class="text-rose-600 font-bold bg-rose-50 border border-rose-200 px-2 py-1 rounded-lg inline-flex items-center gap-1 text-[11px]">
                                                <i class="fa-solid fa-spinner fa-spin-pulse"></i> รักษา (ER)
                                            </a>
                                        @else
                                            <a href="{{ route('backend.mews.show', $lm->id) }}" class="text-teal-700 font-bold bg-teal-50 border border-teal-200 px-2 py-1 rounded-lg inline-flex items-center gap-1 text-[11px]">
                                                <i class="fa-solid fa-stethoscope"></i> รอตรวจ OPD
                                            </a>
                                        @endif
                                    @elseif(($isMewsAdmitOrRefer && !$isMewsReturned) || $activeIpd)
                                        @php
                                            $isOutsidePublicHosp = $activeIpd ? ($activeIpd->sub_type === 'public_hospital') : ($lm && $lm->discharge_type === 'refer');
                                            $targetLink = ($isMewsAdmitOrRefer && !$isMewsReturned && $lm)
                                                ? route('backend.mews.show', $lm->id)
                                                : route('backend.morbidity_reports.index', ['intake_batch' => $patient->intake_batch]);
                                        @endphp
                                        <a href="{{ $targetLink }}" class="{{ $isOutsidePublicHosp ? 'text-blue-800 bg-blue-100 border-blue-300' : 'text-amber-800 bg-amber-100 border-amber-300' }} font-black border px-2 py-1 rounded-lg inline-flex items-center gap-1 text-[11px]">
                                            <i class="fa-solid {{ $isOutsidePublicHosp ? 'fa-truck-medical' : 'fa-bed-pulse' }} animate-pulse"></i>
                                            <span>{{ $isOutsidePublicHosp ? 'นอน รพ.สธ.' : 'Admit รพ.' }}</span>
                                        </a>
                                    @elseif($dc)
                                        <span class="text-slate-600 font-bold text-[11px]"><i class="fa-solid fa-ban text-rose-500 mr-1"></i>ปลดทหาร</span>
                                    @elseif($isInactiveDuty)
                                        <span class="text-orange-700 font-bold text-[11px]">งดแจ้ง MEWS</span>
                                    @elseif($lm)
                                        @if($lm->status === 'cancelled')
                                            <span class="text-slate-500 text-[11px]">ยกเลิกเคส</span>
                                        @elseif($isMewsAdmitOrRefer && $isMewsReturned)
                                            <a href="{{ route('backend.mews.show', $lm->id) }}" class="text-emerald-700 font-bold bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-lg inline-flex items-center gap-1 text-[11px]">
                                                <i class="fa-solid fa-house-chimney-check"></i> กลับหน่วยแล้ว
                                            </a>
                                        @else
                                            <a href="{{ route('backend.mews.show', $lm->id) }}" class="text-emerald-600 font-bold bg-emerald-50 border border-emerald-100 px-2 py-1 rounded-lg inline-flex items-center gap-1 text-[11px]">
                                                <i class="fa-solid fa-house-chimney-medical"></i> กลับหน่วย
                                            </a>
                                        @endif
                                    @else
                                        <span class="text-slate-400 text-[11px] font-medium">ไม่มีประวัติ</span>
                                    @endif
                                </div>

                                <!-- ช่องขวา: คัดกรองแรกรับ -->
                                <div class="space-y-1 border-l border-slate-200/70 pl-2.5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">คัดกรองแรกรับ</div>
                                    @if($isInactiveDuty)
                                        <span class="text-slate-400 text-[11px] font-bold">ไม่ได้คัดกรอง</span>
                                    @elseif($sc)
                                        @if($sc->color_group === 'red')
                                            <span class="text-rose-700 font-bold bg-rose-50 border border-rose-200 px-2 py-1 rounded-lg inline-flex items-center gap-1 text-[11px]">
                                                <i class="fa-solid fa-virus-covid text-rose-500"></i> พบเชื้อ (แดง)
                                            </span>
                                        @elseif($sc->color_group === 'yellow')
                                            <span class="text-amber-700 font-bold bg-amber-50 border border-amber-200 px-2 py-1 rounded-lg inline-flex items-center gap-1 text-[11px]">
                                                <i class="fa-solid fa-head-side-cough text-amber-500"></i> กลุ่มเสี่ยง
                                            </span>
                                        @else
                                            <span class="text-emerald-700 font-bold bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-lg inline-flex items-center gap-1 text-[11px]">
                                                <i class="fa-solid fa-shield-virus text-emerald-500"></i> ปกติ (เขียว)
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 text-[11px] font-medium inline-flex items-center gap-1">
                                            <i class="fa-regular fa-clock"></i> รอคัดกรอง
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- แถว 3: แถบปุ่มจัดการ (กดง่ายบนมือถือ) -->
                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                <a href="{{ route('backend.patients.show', $patient->id) }}"
                                   class="flex-1 py-2 px-3 rounded-xl bg-slate-800 hover:bg-indigo-600 text-white text-xs font-bold inline-flex items-center justify-center gap-1.5 shadow-sm transition-colors">
                                    <i class="fa-solid fa-address-card"></i> ดูข้อมูล
                                </a>

                                <a href="{{ route('backend.patients.edit', $patient->id) }}"
                                   class="py-2 px-3 rounded-xl bg-amber-50 hover:bg-amber-500 text-amber-700 hover:text-white border border-amber-200 text-xs font-bold inline-flex items-center justify-center gap-1 transition-colors">
                                    <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                                </a>

                                @if($canSyncHosxp && !$isInactiveDuty && (empty($patient->hn) || empty($patient->pttype_name)))
                                    <button type="button"
                                            onclick="syncSinglePatientInTable({{ $patient->id }})"
                                            class="js-patient-sync-btn-{{ $patient->id }} py-2 px-3 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 text-xs font-bold inline-flex items-center justify-center gap-1 transition-colors"
                                            title="ซิงค์ HN และสิทธิ HosXP">
                                        <i class="fa-solid fa-rotate"></i> ซิงค์
                                    </button>
                                @endif

                                <button type="button"
                                        onclick="openDutyStatusModal({{ $patient->id }}, '{{ addslashes($fullSoldierName) }}', '{{ $dutyStat }}', '{{ addslashes($patient->duty_status_note ?? '') }}')"
                                        class="w-9 h-9 rounded-xl border inline-flex items-center justify-center transition-colors shrink-0 {{ $isInactiveDuty ? 'bg-orange-50 border-orange-300 text-orange-600' : 'bg-white border-slate-200 text-slate-500' }}"
                                        title="เปลี่ยนสถานะกำลังพล">
                                    <i class="fa-solid fa-user-gear text-xs"></i>
                                </button>

                                @if($isAdmin)
                                    <form action="{{ route('backend.patients.destroy', $patient->id) }}" method="POST" id="delete-form-mobile-{{ $patient->id }}" class="inline-block">
                                        @csrf @method('DELETE')
                                        <button type="button" onclick="confirmAction(event, 'ยืนยันการลบ?', 'ข้อมูลจะถูกลบถาวร', 'warning', 'delete-form-mobile-{{ $patient->id }}')"
                                                class="w-9 h-9 rounded-xl bg-white border border-rose-200 text-rose-500 hover:bg-rose-500 hover:text-white inline-flex items-center justify-center transition-colors shrink-0" title="ลบข้อมูล">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>

                        </div>
                    @empty
                        <div class="px-6 py-14 text-center text-slate-500">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl text-slate-300">
                                <i class="fa-solid fa-folder-open"></i>
                            </div>
                            <p class="text-base font-bold text-slate-700">ไม่พบข้อมูลกำลังพลที่ค้นหา</p>
                            <p class="text-xs mt-1">ลองเปลี่ยนคำค้นหา หรือกดปุ่มล้างตัวกรองเพื่อดูข้อมูลทั้งหมด</p>
                        </div>
                    @endforelse
                </div>

                <!-- =========================================================== -->
                <!-- 💻 โหมดคอมพิวเตอร์: ตารางมาตรฐาน จัดสัดส่วนชัดเจนไม่บีบตัวหนังสือ -->
                <!-- =========================================================== -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full min-w-[920px] text-sm text-left text-slate-600">
                        <thead class="text-xs text-slate-500 uppercase bg-slate-50/80 border-b border-slate-100 whitespace-nowrap">
                            <tr>
                                @if($showCheckbox)
                                    <th scope="col" class="px-6 py-4 w-10">
                                        <input type="checkbox" id="check-all" class="check-all-toggle rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer" title="เลือกทั้งหมดในหน้านี้">
                                    </th>
                                @endif
                                <th scope="col" class="px-6 py-4 font-bold">1. ข้อมูลกำลังพล / สิทธิการรักษา</th>
                                <th scope="col" class="px-6 py-4 font-bold text-center">2. สถานะรักษา (MEWS)</th>
                                <th scope="col" class="px-6 py-4 font-bold text-center">3. คัดกรองแรกรับ</th>
                                <th scope="col" class="px-6 py-4 font-bold text-center w-52">4. จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            
                            @php 
                                $currentBatch = null; 
                                $currentUnit  = null; 
                            @endphp

                            @forelse ($patients as $patient)
                                @php
                                    $patientUnitName = $patient->militaryUnit->name ?? $patient->company ?? 'ไม่ระบุหน่วยฝึก';
                                    $sc = $patient->recruitScreening;
                                    $dc = $patient->militaryDischarge;
                                    $dutyStat = $patient->duty_status ?? 'active';
                                    $isInactiveDuty = in_array($dutyStat, ['absent', 'imprisoned', 'deceased']);
                                    $fullSoldierName = ($patient->rank ?? '') . ($patient->patient_name ?: ($patient->first_name . ' ' . $patient->last_name));
                                @endphp
                                
                                @if($currentBatch !== $patient->intake_batch)
                                    @php 
                                        $currentBatch = $patient->intake_batch; 
                                        $currentUnit = null; 
                                    @endphp
                                    <tr class="bg-slate-800">
                                        <td colspan="{{ $tableColSpan }}" class="px-6 py-2.5">
                                            <div class="flex items-center gap-2 text-white font-bold text-sm">
                                                <i class="fa-solid fa-calendar-days text-slate-400"></i> ทหารกองประจำการ ผลัดที่ {{ $currentBatch ?? 'ไม่ระบุ' }}
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                @if($currentUnit !== $patientUnitName)
                                    @php $currentUnit = $patientUnitName; @endphp
                                    <tr class="bg-indigo-50/50">
                                        <td colspan="{{ $tableColSpan }}" class="px-6 py-2.5 border-y border-indigo-100">
                                            <div class="flex items-center gap-2 text-indigo-700 font-bold text-xs uppercase tracking-wide">
                                                <i class="fa-solid fa-tent text-indigo-400"></i> {{ $currentUnit }}
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                <tr class="hover:bg-slate-50 transition-colors {{ $isInactiveDuty ? 'bg-slate-50/60 opacity-85' : '' }}">
                                    @if($showCheckbox)
                                        <td class="px-6 py-4 w-10 pl-6">
                                            <input type="checkbox" class="patient-checkbox rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer" value="{{ $patient->id }}">
                                        </td>
                                    @endif

                                    <!-- คอลัมน์ 1: ข้อมูลกำลังพล + สิทธิการรักษา -->
                                    <td class="px-6 py-4 {{ $showCheckbox ? '' : 'pl-10' }}">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="js-patient-name-{{ $patient->id }} text-slate-900 font-bold text-base {{ $dutyStat === 'deceased' ? 'line-through text-slate-500' : '' }}">
                                                {{ $fullSoldierName }}
                                            </div>

                                            @if($dutyStat === 'absent')
                                                <button type="button" onclick="openDutyStatusModal({{ $patient->id }}, '{{ addslashes($fullSoldierName) }}', '{{ $dutyStat }}', '{{ addslashes($patient->duty_status_note ?? '') }}')"
                                                        class="bg-orange-100 hover:bg-orange-200 text-orange-800 border border-orange-300 font-black px-2.5 py-0.5 rounded-lg text-[11px] inline-flex items-center gap-1 shadow-sm transition-colors cursor-pointer" title="คลิกเพื่อเปลี่ยนสถานะกำลังพล">
                                                    <i class="fa-solid fa-user-xmark text-orange-600"></i> ไม่มารายงานตัว {{ $patient->duty_status_note ? "({$patient->duty_status_note})" : '' }}
                                                </button>
                                            @elseif($dutyStat === 'imprisoned')
                                                <button type="button" onclick="openDutyStatusModal({{ $patient->id }}, '{{ addslashes($fullSoldierName) }}', '{{ $dutyStat }}', '{{ addslashes($patient->duty_status_note ?? '') }}')"
                                                        class="bg-rose-100 hover:bg-rose-200 text-rose-800 border border-rose-300 font-black px-2.5 py-0.5 rounded-lg text-[11px] inline-flex items-center gap-1 shadow-sm transition-colors cursor-pointer" title="คลิกเพื่อเปลี่ยนสถานะกำลังพล">
                                                    <i class="fa-solid fa-gavel text-rose-600"></i> ต้องโทษ {{ $patient->duty_status_note ? "({$patient->duty_status_note})" : '' }}
                                                </button>
                                            @elseif($dutyStat === 'deceased')
                                                <button type="button" onclick="openDutyStatusModal({{ $patient->id }}, '{{ addslashes($fullSoldierName) }}', '{{ $dutyStat }}', '{{ addslashes($patient->duty_status_note ?? '') }}')"
                                                        class="bg-slate-900 hover:bg-slate-800 text-white font-black px-2.5 py-0.5 rounded-lg text-[11px] inline-flex items-center gap-1 shadow-sm transition-colors cursor-pointer" title="คลิกเพื่อเปลี่ยนสถานะกำลังพล">
                                                    <i class="fa-solid fa-ribbon text-slate-300"></i> เสียชีวิต {{ $patient->duty_status_note ? "({$patient->duty_status_note})" : '' }}
                                                </button>
                                            @endif
                                        </div>

                                        <div class="text-xs text-slate-500 mt-1.5 flex flex-wrap items-center gap-2">
                                            <span class="font-mono"><i class="fa-regular fa-id-card text-slate-400 mr-1"></i>{{ $patient->cid }}</span>
                                            <span class="js-patient-hn-{{ $patient->id }} {{ $patient->hn ? 'bg-blue-50 text-blue-700 border border-blue-200 font-bold' : 'bg-slate-100 text-slate-500 font-semibold' }} px-2 py-0.5 rounded">
                                                HN: {{ $patient->hn ?? '-' }}
                                            </span>
                                            <span class="js-patient-pttype-{{ $patient->id }} border px-2 py-0.5 rounded text-[11px] {{ $getPttypeBadgeClass($patient->pttype_name) }}">
                                                <i class="fa-solid fa-shield-heart mr-1"></i>{{ $patient->pttype_name ?: 'รอซิงค์สิทธิ' }}
                                            </span>

                                            @if($isAdmin)
                                                @if(($patient->chronic_disease_status ?? 'none') === 'yes')
                                                    <span class="bg-rose-50 text-rose-700 border border-rose-200 font-bold px-2 py-0.5 rounded text-[11px]">
                                                        <i class="fa-solid fa-heart-pulse mr-1 text-rose-500"></i>โรคประจำตัว: {{ $patient->chronic_disease_detail ?: 'ระบุ' }}
                                                    </span>
                                                @elseif(($patient->chronic_disease_status ?? 'none') === 'other')
                                                    <span class="bg-amber-50 text-amber-700 border border-amber-200 font-bold px-2 py-0.5 rounded text-[11px]">
                                                        <i class="fa-solid fa-notes-medical mr-1 text-amber-500"></i>อื่นๆ: {{ $patient->chronic_disease_detail ?: 'ระบุ' }}
                                                    </span>
                                                @endif
                                            @endif

                                            @if($dc)
                                                @if($dc->process_status === 'completed')
                                                    <span class="bg-slate-800 text-white font-bold px-2.5 py-0.5 rounded text-[11px] inline-flex items-center gap-1 shadow-sm" title="โรควินิจฉัยนำปลด: {{ $dc->diagnosis_text }}">
                                                        <i class="fa-solid fa-user-slash text-rose-400"></i> ปลดทหารแล้ว ({{ $dc->diagnosis_text }})
                                                    </span>
                                                @else
                                                    <span class="bg-purple-100 text-purple-800 border border-purple-300 font-bold px-2.5 py-0.5 rounded text-[11px] inline-flex items-center gap-1 shadow-sm" title="โรควินิจฉัยนำปลด: {{ $dc->diagnosis_text }}">
                                                        <i class="fa-solid fa-folder-open text-purple-600"></i> นำปลดทหาร: {{ $dc->diagnosis_text }}
                                                    </span>
                                                @endif
                                            @endif

                                            @if($patient->upcomingAppointment && !$isInactiveDuty && !$dc)
                                                @php
                                                    $upApp = $patient->upcomingAppointment;
                                                    $appDateStr = $upApp->appointment_date->format('Y-m-d');
                                                    $isAppToday = ($appDateStr === date('Y-m-d'));
                                                    $isAppTomorrow = ($appDateStr === date('Y-m-d', strtotime('+1 day')));
                                                    $appDateTh = $upApp->appointment_date->locale('th')->addYears(543)->translatedFormat('j M y');
                                                    $appDateFullTh = $upApp->appointment_date->locale('th')->addYears(543)->translatedFormat('j F Y');
                                                    $hospShort = $upApp->hospital_type === 'public_hospital' ? ($upApp->hospital_name ?: 'รพ.สธ.') : 'รพ.ค่ายฯ';
                                                    $appBadgeClass = $isAppToday
                                                        ? 'bg-rose-600 text-white border-rose-600 animate-pulse'
                                                        : ($isAppTomorrow ? 'bg-amber-500 text-white border-amber-500' : 'bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100');

                                                    $appModalPayload = [
                                                        'app_id'        => $upApp->id,
                                                        'patient_id'    => $patient->id,
                                                        'full_name'     => $fullSoldierName,
                                                        'hn'            => $patient->hn ?: 'ยังไม่มี HN',
                                                        'unit'          => $patientUnitName,
                                                        'batch'         => $patient->intake_batch ?: '-',
                                                        'date_full'     => $appDateFullTh,
                                                        'time'          => ($upApp->appointment_time ?: '08:30') . ' น.',
                                                        'hospital_name' => $upApp->hospital_name ?: ($upApp->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายกฤษณ์สีวะรา'),
                                                        'is_camp'       => $upApp->hospital_type === 'camp_hospital',
                                                        'clinic'        => $upApp->clinic_name ?: 'ไม่ระบุคลินิก',
                                                        'doctor'        => $upApp->doctor_name ?: '-',
                                                        'cause'         => $upApp->app_cause ?: 'นัดตรวจติดตามอาการ',
                                                        'note'          => $upApp->note ?: '',
                                                        'is_today'      => $isAppToday,
                                                        'is_tomorrow'   => $isAppTomorrow,
                                                    ];
                                                @endphp
                                                <button type="button"
                                                        onclick='showSingleSoldierAppointmentModal(@json($appModalPayload))'
                                                        class="{{ $appBadgeClass }} border font-bold px-2.5 py-0.5 rounded text-[11px] inline-flex items-center gap-1 shadow-sm transition-all cursor-pointer"
                                                        title="คลิกเพื่อดูรายละเอียดใบนัดและการเตรียมตัวของ {{ $patient->first_name }}">
                                                    <i class="fa-solid fa-calendar-day"></i>
                                                    <span>
                                                        {{ $isAppToday ? 'นัดวันนี้!' : ($isAppTomorrow ? 'นัดพรุ่งนี้!' : 'นัด ' . $appDateTh) }}
                                                        ({{ $hospShort }}{{ $upApp->clinic_name ? ' • ' . $upApp->clinic_name : '' }})
                                                    </span>
                                                </button>
                                            @endif

                                            @if($patient->activeInfirmaryStay && (!$dc || $dc->process_status !== 'completed') && !$isInactiveDuty)
                                                @php
                                                    $infStay = $patient->activeInfirmaryStay;
                                                    $isUnitLoc = ($infStay->location_type === 'unit_infirmary');
                                                @endphp
                                                <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $patient->intake_batch, 'unit_id' => $patient->military_unit_id]) }}"
                                                   class="{{ $isUnitLoc ? 'bg-indigo-100 text-indigo-800 border-indigo-300' : 'bg-teal-100 text-teal-800 border-teal-300' }} border font-black px-2.5 py-0.5 rounded-lg text-[11px] inline-flex items-center gap-1 shadow-sm hover:opacity-90 transition-opacity"
                                                   title="สาเหตุ: {{ $infStay->diagnosis_text }} {{ $infStay->care_instruction ? '(' . $infStay->care_instruction . ')' : '' }}">
                                                    <i class="fa-solid {{ $isUnitLoc ? 'fa-tent-arrows-down text-indigo-600' : 'fa-bed text-teal-600' }}"></i>
                                                    <span>{{ $isUnitLoc ? 'นอนห้องพยาบาลหน่วยฝึก' : 'ฝากนอนแยกกัก รพ.' }}{{ $infStay->bed_or_zone ? ' (' . $infStay->bed_or_zone . ')' : '' }}: {{ $infStay->diagnosis_text }}</span>
                                                </a>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- คอลัมน์ 2: สถานะรักษา (MEWS) -->
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        @php
                                            $lm = $patient->latestMews;
                                            $activeIpd = $patient->activeIpdMorbidity ?? $batchedActiveIpds->get($patient->id);
                                            $isMewsPending = $lm && $lm->status === 'pending';
                                            $isMewsAdmitOrRefer = $lm && $lm->status === 'completed' && in_array($lm->discharge_type, ['admitted', 'refer']);
                                            $isMewsReturned = $lm && !empty($lm->returned_to_unit_at);
                                            $rawLmSyms = $lm ? (is_string($lm->symptoms) ? json_decode($lm->symptoms, true) : ($lm->symptoms ?? [])) : [];
                                            $hasLmEmergencySym = is_array($rawLmSyms) && count($rawLmSyms) > 0;
                                            $isErPendingCase = $isMewsPending && (
                                                $lm->assessment_type === 'er'
                                                || in_array($lm->risk_level, ['observe', 'emergency'])
                                                || $hasLmEmergencySym
                                            );
                                        @endphp

                                        @if($isMewsPending)
                                            @if($isErPendingCase)
                                                <a href="{{ route('backend.mews.show', $lm->id) }}" class="text-rose-600 hover:bg-rose-100 text-xs font-bold bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl inline-flex items-center transition-colors shadow-sm">
                                                    <i class="fa-solid fa-spinner fa-spin-pulse mr-1.5"></i> กำลังรักษา (ER)
                                                </a>
                                            @else
                                                <a href="{{ route('backend.mews.show', $lm->id) }}" class="text-teal-700 hover:bg-teal-100 text-xs font-bold bg-teal-50 border border-teal-200 px-3 py-1.5 rounded-xl inline-flex items-center transition-colors shadow-sm">
                                                    <i class="fa-solid fa-stethoscope mr-1.5 text-teal-600"></i> รอตรวจ (OPD)
                                                </a>
                                            @endif
                                        @elseif(($isMewsAdmitOrRefer && !$isMewsReturned) || $activeIpd)
                                            @php
                                                $isOutsidePublicHosp = $activeIpd ? ($activeIpd->sub_type === 'public_hospital') : ($lm && $lm->discharge_type === 'refer');
                                                $targetLink = ($isMewsAdmitOrRefer && !$isMewsReturned && $lm)
                                                    ? route('backend.mews.show', $lm->id)
                                                    : route('backend.morbidity_reports.index', ['intake_batch' => $patient->intake_batch]);
                                                $dxTooltip = $activeIpd && $activeIpd->diagnosis_text
                                                    ? 'Dx.: ' . $activeIpd->diagnosis_text . ' (Admit ' . $activeIpd->onset_date->addYears(543)->format('d/m/Y') . ')'
                                                    : 'คลิกเพื่อดูรายละเอียด หรือบันทึกเมื่อทหารกลับถึงหน่วยฝึกแล้ว';
                                            @endphp
                                            <a href="{{ $targetLink }}"
                                               class="{{ $isOutsidePublicHosp ? 'text-blue-800 bg-blue-100 border-blue-300 hover:bg-blue-200' : 'text-amber-800 bg-amber-100 border-amber-300 hover:bg-amber-200' }} text-xs font-black border px-3 py-1.5 rounded-xl inline-flex flex-col items-center justify-center shadow-sm transition-all"
                                               title="{{ $dxTooltip }}">
                                                <span class="flex items-center gap-1.5">
                                                    <i class="fa-solid {{ $isOutsidePublicHosp ? 'fa-truck-medical text-blue-600' : 'fa-bed-pulse text-amber-600' }} animate-pulse"></i>
                                                    {{ $isOutsidePublicHosp ? 'นอน รพ.สาธารณสุข' : 'Admit นอน รพ.' }}
                                                </span>
                                                @if($activeIpd && $activeIpd->diagnosis_text)
                                                    <span class="text-[10px] font-bold text-slate-700 max-w-[140px] truncate mt-0.5">{{ $activeIpd->diagnosis_text }}</span>
                                                @endif
                                                <span class="text-[10px] font-bold text-rose-600 mt-0.5">⚠️ ยังไม่กลับหน่วยฝึก</span>
                                            </a>
                                        @elseif($dc)
                                            <span class="text-slate-600 text-xs font-bold bg-slate-100 border border-slate-300 px-3 py-1.5 rounded-xl inline-flex items-center gap-1.5 shadow-sm">
                                                <i class="fa-solid fa-ban text-rose-500"></i> ปลดทหาร (งดแจ้ง MEWS)
                                            </span>
                                        @elseif($dutyStat === 'absent')
                                            <span class="text-orange-800 text-xs font-bold bg-orange-50 border border-orange-200 px-3 py-1.5 rounded-xl inline-flex items-center gap-1.5 shadow-sm">
                                                <i class="fa-solid fa-user-xmark text-orange-500"></i> ไม่มารายงานตัว
                                            </span>
                                        @elseif($dutyStat === 'imprisoned')
                                            <span class="text-rose-800 text-xs font-bold bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl inline-flex items-center gap-1.5 shadow-sm">
                                                <i class="fa-solid fa-gavel text-rose-600"></i> ต้องโทษ (งดแจ้ง MEWS)
                                            </span>
                                        @elseif($dutyStat === 'deceased')
                                            <span class="text-white text-xs font-bold bg-slate-800 px-3 py-1.5 rounded-xl inline-flex items-center gap-1.5 shadow-sm">
                                                <i class="fa-solid fa-ribbon text-slate-300"></i> เสียชีวิต
                                            </span>
                                        @elseif($lm)
                                            @if($lm->status === 'cancelled')
                                                <span class="text-slate-500 text-xs font-medium"><i class="fa-solid fa-ban mr-1"></i> ยกเลิกเคส</span>
                                            @elseif($isMewsAdmitOrRefer && $isMewsReturned)
                                                <a href="{{ route('backend.mews.show', $lm->id) }}"
                                                   class="text-emerald-700 text-xs font-bold bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-xl inline-flex flex-col items-center justify-center hover:bg-emerald-100 transition-colors shadow-sm"
                                                   title="{{ $lm->return_note ?: 'กลับถึงหน่วยฝึกเรียบร้อยแล้ว' }}">
                                                    <span class="flex items-center gap-1"><i class="fa-solid fa-house-chimney-check text-emerald-600"></i> กลับหน่วยฝึกแล้ว</span>
                                                    <span class="text-[10px] text-slate-500 font-medium">({{ \Carbon\Carbon::parse($lm->returned_to_unit_at)->addYears(543)->format('d/m/y') }})</span>
                                                </a>
                                            @else
                                                <a href="{{ route('backend.mews.show', $lm->id) }}" class="text-emerald-600 hover:bg-emerald-100 text-xs font-bold bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-xl inline-flex items-center transition-colors">
                                                    <i class="fa-solid fa-house-chimney-medical mr-1.5"></i> จำหน่ายกลับหน่วย
                                                </a>
                                            @endif
                                        @else
                                            <span class="text-slate-400 text-xs font-medium bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-100">ไม่มีประวัติ</span>
                                        @endif
                                    </td>

                                    <!-- คอลัมน์ 3: คัดกรองแรกรับ -->
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        @if($isInactiveDuty)
                                            <span class="text-slate-400 text-xs font-bold bg-slate-100 px-3 py-1 rounded-xl border border-slate-200 inline-flex items-center gap-1">
                                                <i class="fa-solid fa-minus text-[10px]"></i> ไม่ได้คัดกรอง ({{ $dutyStat === 'absent' ? 'ไม่มารายงานตัว' : ($dutyStat === 'imprisoned' ? 'ต้องโทษ' : 'เสียชีวิต') }})
                                            </span>
                                        @elseif($sc)
                                            @if($sc->color_group === 'red')
                                                <span class="text-rose-700 text-xs font-bold bg-rose-50 border border-rose-200 px-3 py-1 rounded-xl inline-flex items-center gap-1.5 shadow-sm">
                                                    <i class="fa-solid fa-virus-covid text-rose-500"></i> พบเชื้อ (แดง)
                                                </span>
                                            @elseif($sc->color_group === 'yellow')
                                                <span class="text-amber-700 text-xs font-bold bg-amber-50 border border-amber-200 px-3 py-1 rounded-xl inline-flex items-center gap-1.5 shadow-sm">
                                                    <i class="fa-solid fa-head-side-cough text-amber-500"></i> กลุ่มเสี่ยง (เหลือง)
                                                </span>
                                            @else
                                                <span class="text-emerald-700 text-xs font-bold bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-xl inline-flex items-center gap-1.5 shadow-sm">
                                                    <i class="fa-solid fa-shield-virus text-emerald-500"></i> ปกติ (เขียว)
                                                </span>
                                            @endif

                                            @if(!empty($sc->symptoms_note) && $sc->symptoms_note !== 'ปกติ (ไม่มีอาการ)')
                                                <div class="text-[11px] text-slate-500 mt-1 max-w-[180px] mx-auto truncate" title="{{ $sc->symptoms_note }}">
                                                    {{ $sc->symptoms_note }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-slate-400 text-xs font-medium bg-slate-50 px-3 py-1 rounded-xl border border-slate-100 inline-flex items-center gap-1">
                                                <i class="fa-regular fa-clock text-[11px]"></i> รอคัดกรอง
                                            </span>
                                        @endif
                                    </td>
                                    
                                    <!-- คอลัมน์ 4: ปุ่มจัดการ -->
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1.5">
                                            @if($canSyncHosxp && !$isInactiveDuty && (empty($patient->hn) || empty($patient->pttype_name)))
                                                <button type="button"
                                                        onclick="syncSinglePatientInTable({{ $patient->id }})"
                                                        class="js-patient-sync-btn-{{ $patient->id }} w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-600 hover:bg-indigo-600 hover:text-white flex items-center justify-center transition-all shadow-sm"
                                                        title="ซิงค์ HN และสิทธิการรักษาจาก HosXP">
                                                    <i class="fa-solid fa-rotate"></i>
                                                </button>
                                            @endif

                                            <button type="button"
                                                    onclick="openDutyStatusModal({{ $patient->id }}, '{{ addslashes($fullSoldierName) }}', '{{ $dutyStat }}', '{{ addslashes($patient->duty_status_note ?? '') }}')"
                                                    class="w-9 h-9 rounded-xl border flex items-center justify-center transition-all shadow-sm {{ $isInactiveDuty ? 'bg-orange-50 border-orange-300 text-orange-600 hover:bg-orange-500 hover:text-white' : 'bg-white border-slate-200 text-slate-500 hover:bg-purple-600 hover:text-white hover:border-purple-600' }}"
                                                    title="เปลี่ยนสถานะกำลังพล">
                                                <i class="fa-solid fa-user-gear"></i>
                                            </button>

                                            <a href="{{ route('backend.patients.show', $patient->id) }}" class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-blue-500 hover:text-white hover:border-blue-500 flex items-center justify-center transition-all shadow-sm" title="ดูข้อมูล">
                                                <i class="fa-solid fa-address-card"></i>
                                            </a>
                                            <a href="{{ route('backend.patients.edit', $patient->id) }}" class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-amber-500 hover:text-white hover:border-amber-500 flex items-center justify-center transition-all shadow-sm" title="แก้ไข">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            @if($isAdmin)
                                                <form action="{{ route('backend.patients.destroy', $patient->id) }}" method="POST" id="delete-form-{{ $patient->id }}" class="inline-block">
                                                    @csrf @method('DELETE')
                                                    <button type="button" onclick="confirmAction(event, 'ยืนยันการลบ?', 'ข้อมูลจะถูกลบถาวร', 'warning', 'delete-form-{{ $patient->id }}')" class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-400 hover:bg-rose-500 hover:text-white hover:border-rose-500 transition-all shadow-sm" title="ลบข้อมูล">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $tableColSpan }}" class="px-6 py-16 text-center text-slate-500">
                                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl text-slate-300">
                                            <i class="fa-solid fa-folder-open"></i>
                                        </div>
                                        <p class="text-lg font-bold text-slate-700">ไม่พบข้อมูลกำลังพลที่ค้นหา</p>
                                        <p class="text-sm mt-1">ลองเปลี่ยนคำค้นหา หรือกดปุ่มยกเลิกตัวกรองเพื่อดูข้อมูลทั้งหมด</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($patients->hasPages())
                    <div class="px-4 sm:px-6 py-4 border-t border-slate-100 bg-white">
                        {{ $patients->links() }}
                    </div>
                @endif
            </div>

        </div>

    @endif <!-- จบส่วนเฉพาะโหมด List -->

</div>
@endsection

@section('scripts')
<script>   
    @php
        $safeBatchUnitStats = $allBatchUnitStats ?? [];
    @endphp
    const isAdminUser = @json($isAdmin);
    const isOverviewPage = @json($isOverviewMode);
    const basePatientsIndexUrl = "{{ route('backend.patients.index') }}";

    // =========================================================================
    // 🌟 1. ระบบจดจำ Checkbox ข้ามหน้า (Multi-Page Persistent Selection via sessionStorage)
    // =========================================================================
    const STORAGE_KEY = 'ksvr_selected_patient_ids';
    let selectedPatientIds = new Set(JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]').map(String));

    function saveSelectedToStorage() {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(selectedPatientIds)));
        updateBulkActionBarUI();
    }

    function updateBulkActionBarUI() {
        const bulkBar = document.getElementById('multi-page-bulk-bar');
        const countEl = document.getElementById('selected-count');
        const countTexts = document.querySelectorAll('.selected-count-text');
        const totalSelected = selectedPatientIds.size;

        if (countEl) countEl.innerText = totalSelected;
        countTexts.forEach(el => el.innerText = totalSelected);

        if (bulkBar) {
            if (totalSelected > 0) {
                bulkBar.classList.remove('hidden');
                bulkBar.classList.add('flex');
            } else {
                bulkBar.classList.add('hidden');
                bulkBar.classList.remove('flex');
            }
        }
    }

    function clearAllSelectedPatients() {
        selectedPatientIds.clear();
        saveSelectedToStorage();
        document.querySelectorAll('.patient-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.check-all-toggle').forEach(cb => cb.checked = false);
    }

    function initBulkDeleteCheckboxes() {
        const checkAllToggles = document.querySelectorAll('.check-all-toggle');
        const checkboxes = document.querySelectorAll('.patient-checkbox');

        const syncCheckAllState = () => {
            const uniquePageIds = Array.from(new Set(Array.from(checkboxes).map(c => String(c.value))));
            const allChecked = uniquePageIds.length > 0 && uniquePageIds.every(id => selectedPatientIds.has(id));
            checkAllToggles.forEach(toggle => toggle.checked = allChecked);
        };

        checkboxes.forEach(cb => {
            cb.checked = selectedPatientIds.has(String(cb.value));

            cb.addEventListener('change', function() {
                const idStr = String(this.value);
                if (this.checked) {
                    selectedPatientIds.add(idStr);
                } else {
                    selectedPatientIds.delete(idStr);
                }
                // ซิงค์ Checkbox ของคนเดียวกันระหว่างโหมดมือถือและคอมพิวเตอร์
                document.querySelectorAll(`.patient-checkbox[value="${idStr}"]`).forEach(el => {
                    el.checked = this.checked;
                });
                syncCheckAllState();
                saveSelectedToStorage();
            });
        });

        syncCheckAllState();

        checkAllToggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const isChecked = this.checked;
                checkboxes.forEach(cb => {
                    cb.checked = isChecked;
                    const idStr = String(cb.value);
                    if (isChecked) {
                        selectedPatientIds.add(idStr);
                    } else {
                        selectedPatientIds.delete(idStr);
                    }
                });
                checkAllToggles.forEach(t => t.checked = isChecked);
                saveSelectedToStorage();
            });
        });

        document.querySelectorAll('#patients-table-container a[href*="page="]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetUrl = this.getAttribute('href');
                if (targetUrl) {
                    fetchTableByUrl(targetUrl, true);
                }
            });
        });

        updateBulkActionBarUI();
    }

    // =========================================================================
    // 🌟 2. ระบบ Live Search & AJAX Pagination + Quick Filters
    // =========================================================================
    let searchDebounceTimer = null;
    let currentSearchAbortController = null;

    function setSearchIconLoading(isLoading) {
        const icon = document.getElementById('search-status-icon');
        if (!icon) return;
        if (isLoading) {
            icon.className = 'fa-solid fa-circle-notch fa-spin text-indigo-500 text-sm transition-all';
        } else {
            icon.className = 'fa-solid fa-magnifying-glass text-slate-400 text-sm transition-all';
        }
    }

    function fetchTableByUrl(targetUrl, scrollToTopOfTable = false) {
        if (currentSearchAbortController) {
            currentSearchAbortController.abort();
        }
        currentSearchAbortController = new AbortController();

        setSearchIconLoading(true);

        const tableContainer = document.getElementById('patients-table-container');
        if (tableContainer) tableContainer.style.opacity = '0.5';

        window.history.replaceState({}, '', targetUrl);

        fetch(targetUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: currentSearchAbortController.signal
        })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.getElementById('patients-table-container');

            if (tableContainer && newTable) {
                tableContainer.innerHTML = newTable.innerHTML;
                initBulkDeleteCheckboxes();
                if (scrollToTopOfTable) {
                    tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                window.location.href = targetUrl;
            }
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error('Live Search Error:', err);
            }
        })
        .finally(() => {
            setSearchIconLoading(false);
            if (tableContainer) tableContainer.style.opacity = '1';
        });
    }

    function triggerLiveFilter() {
        const filterForm = document.getElementById('patient-filter-form');
        if (!filterForm) return;

        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData).toString();
        const targetUrl = params ? `${filterForm.action}?${params}` : filterForm.action;

        fetchTableByUrl(targetUrl, false);
    }

    function clearLiveSearch() {
        const searchInput = document.getElementById('live-search-input');
        const clearBtn = document.getElementById('clear-search-btn');
        if (!searchInput) return;

        searchInput.value = '';
        if (clearBtn) clearBtn.style.display = 'none';
        searchInput.focus();
        triggerLiveFilter();
    }

    // 🌟 เปิด-ปิดลิ้นชักตัวกรองเพิ่มเติม
    function toggleAdvancedFilterDrawer() {
        const drawer = document.getElementById('advanced-filter-drawer');
        if (!drawer) return;
        if (drawer.classList.contains('hidden')) {
            drawer.classList.remove('hidden');
            drawer.classList.add('grid');
        } else {
            drawer.classList.add('hidden');
            drawer.classList.remove('grid');
        }
    }

    // 🌟 กดปุ่มทางลัดกรองด่วน 1 คลิก (Quick Filter Chips)
    function applyQuickChipFilter(fieldName, fieldValue) {
        const filterMap = {
            'mews_status': 'filter-mews-select',
            'appointment_filter': 'filter-appointment-select',
            'hn_status': 'filter-hn-select',
            'discharge_status': 'filter-discharge-select',
            'screening_status': 'filter-screening-select'
        };

        Object.values(filterMap).forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });

        const targetEl = document.getElementById(filterMap[fieldName]);
        if (targetEl) {
            targetEl.value = fieldValue;
            if (fieldName !== 'mews_status') {
                const drawer = document.getElementById('advanced-filter-drawer');
                if (drawer && drawer.classList.contains('hidden')) {
                    drawer.classList.remove('hidden');
                    drawer.classList.add('grid');
                }
            }
            triggerLiveFilter();
        }
    }

    function resetAllLiveFilters() {
        const idsToReset = [
            'live-search-input',
            'filter-batch-select',
            'filter-unit-select',
            'filter-mews-select',
            'filter-appointment-select',
            'filter-screening-select',
            'filter-hn-select',
            'filter-pttype-select',
            'filter-discharge-select',
            'filter-chronic-select'
        ];

        idsToReset.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });

        const clearBtn = document.getElementById('clear-search-btn');
        if (clearBtn) clearBtn.style.display = 'none';
        triggerLiveFilter();
    }

    function selectUnitAndBatchQuickFilter(unitId, batch) {
        if (isOverviewPage) {
            const params = new URLSearchParams({ view: 'list', unit: unitId, batch: batch });
            window.location.href = `${basePatientsIndexUrl}?${params.toString()}`;
            return;
        }
        const unitSelect = document.getElementById('filter-unit-select');
        const batchSelect = document.getElementById('filter-batch-select');
        if (unitSelect) unitSelect.value = unitId;
        if (batchSelect) batchSelect.value = batch;
        triggerLiveFilter();
        document.getElementById('patients-table-container')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function selectQuickScreeningFilter(statusValue) {
        if (isOverviewPage) {
            const params = new URLSearchParams(window.location.search);
            params.set('view', 'list');
            params.set('screening_status', statusValue);
            window.location.href = `${basePatientsIndexUrl}?${params.toString()}`;
            return;
        }
        const screeningSelect = document.getElementById('filter-screening-select');
        if (screeningSelect) {
            screeningSelect.value = statusValue;
            triggerLiveFilter();
            document.getElementById('patients-table-container')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function selectQuickPttypeFilter(pttypeValue) {
        if (isOverviewPage) {
            const params = new URLSearchParams(window.location.search);
            params.set('view', 'list');
            params.set('pttype', pttypeValue);
            window.location.href = `${basePatientsIndexUrl}?${params.toString()}`;
            return;
        }
        const pttypeSelect = document.getElementById('filter-pttype-select');
        if (pttypeSelect) {
            pttypeSelect.value = pttypeValue;
            triggerLiveFilter();
            document.getElementById('patients-table-container')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        initBulkDeleteCheckboxes();

        const searchInput = document.getElementById('live-search-input');
        const clearBtn = document.getElementById('clear-search-btn');
        const filterForm = document.getElementById('patient-filter-form');

        if (searchInput && filterForm) {
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                clearTimeout(searchDebounceTimer);
                triggerLiveFilter();
            });

            searchInput.addEventListener('input', function() {
                if (clearBtn) {
                    clearBtn.style.display = this.value.trim().length > 0 ? 'flex' : 'none';
                }
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    triggerLiveFilter();
                }, 500);
            });
        }
    });

    // =========================================================================
    // 📥 3. ระบบ Pro Custom Column Export Builder (เลือกคอลัมน์ส่งออกเองแบบมือโปร)
    // =========================================================================
    const EXPORT_PREF_KEY = 'ksvr_patient_export_columns_v1';

    const EXPORT_COLUMN_GROUPS = [
        {
            groupTitle: '1. ข้อมูลประจำตัว & สังกัดทหาร',
            icon: 'fa-id-card text-indigo-500',
            columns: [
                { key: 'full_name',        label: 'ยศ ชื่อ-นามสกุล (รวม)', default: true },
                { key: 'cid',              label: 'เลขบัตรประชาชน 13 หลัก', default: true, isCid: true },
                { key: 'hn',               label: 'รหัส HN (รพ.)', default: true },
                { key: 'intake_batch',     label: 'ผลัดทหาร', default: true },
                { key: 'training_unit',    label: 'หน่วยฝึกปัจจุบัน', default: true },
                { key: 'battalion',        label: 'สังกัดปลายทาง (ส่งแผนก)', default: true },
                { key: 'discharge_status', label: 'สถานะกำลังพล / การรายงานตัว / นำปลด', default: true },
                { key: 'military_id',      label: 'รหัสประจำตัวทหาร', default: false },
                { key: 'camp_name',        label: 'ชื่อค่ายทหาร', default: false },
            ]
        },
        {
            groupTitle: '2. สิทธิการรักษา, ร่างกาย, รอยตำหนิ & โรคประจำตัว',
            icon: 'fa-heart-pulse text-rose-500',
            columns: [
                { key: 'pttype_name',    label: 'สิทธิการรักษาพยาบาล (HosXP)', default: true },
                { key: 'dob',            label: 'วันเดือนปีเกิด (พ.ศ.)', default: false },
                { key: 'age',            label: 'อายุ (ปี)', default: true },
                { key: 'blood_group',    label: 'กรุ๊ปเลือด', default: false },
                { key: 'height',         label: 'ส่วนสูง (cm)', default: false },
                { key: 'weight',         label: 'น้ำหนัก (kg)', default: false },
                { key: 'chest_size',     label: 'รอบอก (cm)', default: false },
                { key: 'bmi',            label: 'ค่าดัชนีมวลกาย (BMI)', default: true },
                { key: 'scar_details',   label: 'รอยตำหนิ / แผลเป็น', default: true },
                { key: 'chronic_status', label: 'สถานะโรคประจำตัว (เฉพาะ Admin)', default: true, adminOnly: true },
                { key: 'chronic_detail', label: 'รายละเอียดโรคประจำตัว/อื่นๆ (เฉพาะ Admin)', default: true, adminOnly: true },
            ]
        },
        {
            groupTitle: '3. ผลการคัดกรองแรกรับ, นัดพบแพทย์ & สถานะรักษา (MEWS)',
            icon: 'fa-stethoscope text-emerald-500',
            columns: [
                { key: 'screening_status', label: 'กลุ่มสีคัดกรองแรกรับ', default: true },
                { key: 'screening_note',   label: 'อาการ/ผลตรวจแรกรับ', default: false },
                { key: 'mews_status',      label: 'สถานะการรักษา (MEWS)', default: true },
                { key: 'mews_score',       label: 'คะแนน MEWS ล่าสุด', default: false },
                { key: 'next_appointment', label: 'วันนัดพบแพทย์ถัดไป', default: true },
                { key: 'infirmary_status', label: 'สถานะห้องพยาบาลหน่วยฝึก / แยกโรงนอน', default: true },
            ]
        },
        {
            groupTitle: '4. ข้อมูลติดต่อ, ครอบครัว & การรับราชการ',
            icon: 'fa-house-chimney-user text-amber-500',
            columns: [
                { key: 'phone',               label: 'เบอร์โทรศัพท์', default: false },
                { key: 'father_name',         label: 'ชื่อ-สกุล บิดา', default: false },
                { key: 'mother_name',         label: 'ชื่อ-สกุล มารดา', default: false },
                { key: 'full_address',        label: 'ที่อยู่ภูมิลำเนา (ฉบับเต็ม)', default: false },
                { key: 'district',            label: 'อำเภอ', default: false },
                { key: 'province',            label: 'จังหวัด', default: false },
                { key: 'education',           label: 'วุฒิการศึกษา', default: false },
                { key: 'religion',            label: 'ศาสนา', default: false },
                { key: 'volunteer_status',    label: 'การเข้ารับราชการ (ร้องขอ)', default: false },
                { key: 'privilege_reduction', label: 'การขอสิทธิลดหย่อน', default: false },
                { key: 'service_duration',    label: 'เวลารับราชการ', default: false },
            ]
        }
    ];

    function getAllAvailableColumns() {
        const list = [];
        EXPORT_COLUMN_GROUPS.forEach(g => {
            g.columns.forEach(c => {
                if (c.adminOnly && !isAdminUser) return;
                list.push(c);
            });
        });
        return list;
    }

    function updateSelectedColCounter() {
        const checked = document.querySelectorAll('.export-col-checkbox:checked').length;
        const badge = document.getElementById('export-col-count-badge');
        if (badge) badge.innerText = `เลือกไว้ ${checked} คอลัมน์`;
    }

    window.applyExportPreset = function(presetType) {
        const checkboxes = document.querySelectorAll('.export-col-checkbox');
        const medicalKeys = ['full_name', 'cid', 'hn', 'intake_batch', 'training_unit', 'discharge_status', 'pttype_name', 'age', 'blood_group', 'height', 'weight', 'bmi', 'scar_details', 'chronic_status', 'chronic_detail', 'screening_status', 'screening_note', 'mews_status', 'mews_score', 'next_appointment'];
        const basicKeys   = ['full_name', 'cid', 'hn', 'pttype_name', 'intake_batch', 'training_unit', 'battalion', 'discharge_status', 'screening_status'];

        checkboxes.forEach(cb => {
            if (presetType === 'all') {
                cb.checked = true;
            } else if (presetType === 'none') {
                cb.checked = (cb.value === 'full_name' || cb.value === 'cid');
            } else if (presetType === 'medical') {
                cb.checked = medicalKeys.includes(cb.value);
            } else if (presetType === 'basic') {
                cb.checked = basicKeys.includes(cb.value);
            }
        });
        updateSelectedColCounter();
    };

    window.openCustomExportBuilderModal = function(defaultScope = 'filtered') {
        const totalFiltered = document.getElementById('current-filtered-total-badge')?.getAttribute('data-total') || '{{ $patients->total() }}';
        const selectedCount = selectedPatientIds.size;
        const useSelectedScope = (defaultScope === 'selected' && selectedCount > 0);

        let savedCols = null;
        try {
            savedCols = JSON.parse(localStorage.getItem(EXPORT_PREF_KEY));
        } catch (e) {}

        let groupsHtml = '';
        EXPORT_COLUMN_GROUPS.forEach(group => {
            const visibleCols = group.columns.filter(c => !(c.adminOnly && !isAdminUser));
            if (visibleCols.length === 0) return;

            let colsHtml = '';
            visibleCols.forEach(col => {
                const isChecked = Array.isArray(savedCols) ? savedCols.includes(col.key) : col.default;
                const adminTag = col.adminOnly ? `<span class="ml-1 px-1.5 py-0.2 bg-rose-100 text-rose-700 rounded text-[9px] font-black">ADMIN</span>` : '';

                colsHtml += `
                    <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/60 has-[:checked]:text-indigo-900 cursor-pointer transition-all select-none shadow-sm">
                        <input type="checkbox" value="${col.key}" ${isChecked ? 'checked' : ''} onchange="updateSelectedColCounter()" 
                               class="export-col-checkbox w-4 h-4 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-slate-700 leading-tight truncate" title="${col.label}">
                            ${col.label} ${adminTag}
                        </span>
                    </label>
                `;
            });

            groupsHtml += `
                <div class="bg-slate-50/80 p-4 rounded-2xl border border-slate-200/80">
                    <div class="text-xs font-black text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <i class="fa-solid ${group.icon}"></i> ${group.groupTitle}
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                        ${colsHtml}
                    </div>
                </div>
            `;
        });

        Swal.fire({
            title: `
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 text-left">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-sm">
                            <i class="fa-solid fa-file-csv"></i>
                        </div>
                        <div>
                            <div class="text-base md:text-lg font-black text-slate-800">ส่งออกข้อมูลกำลังพล (Pro Custom Export)</div>
                            <div class="text-xs font-medium text-slate-400">เลือกคอลัมน์และขอบเขตข้อมูลที่ต้องการดาวน์โหลดเป็นไฟล์ Excel (CSV)</div>
                        </div>
                    </div>
                </div>
            `,
            html: `
                <div class="text-left space-y-4 mt-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <label class="flex items-center gap-3 p-3.5 rounded-2xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/50 transition-all">
                            <input type="radio" name="export_scope_mode" value="filtered" ${!useSelectedScope ? 'checked' : ''} class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <div class="text-xs font-black text-slate-800">ส่งออกตามตัวกรองปัจจุบัน (ทุกหน้า)</div>
                                <div class="text-[11px] text-slate-500">จำนวนทั้งหมด <b class="text-indigo-600">${Number(totalFiltered).toLocaleString()} นาย</b></div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3.5 rounded-2xl border-2 border-slate-200 ${selectedCount === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/50'} transition-all">
                            <input type="radio" name="export_scope_mode" value="selected" ${useSelectedScope ? 'checked' : ''} ${selectedCount === 0 ? 'disabled' : ''} class="w-4 h-4 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <div class="text-xs font-black text-slate-800">ส่งออกเฉพาะที่ติ๊กเลือกไว้</div>
                                <div class="text-[11px] text-slate-500">${selectedCount > 0 ? `เลือกไว้แล้ว <b class="text-emerald-600">${selectedCount} นาย</b>` : 'ยังไม่ได้ติ๊กเลือกรายการในตาราง'}</div>
                            </div>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-100/80 p-2.5 rounded-xl border border-slate-200">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-bold text-slate-500 px-1.5"><i class="fa-solid fa-wand-magic-sparkles text-indigo-500 mr-1"></i>ชุดคอลัมน์ด่วน:</span>
                            <button type="button" onclick="window.applyExportPreset('basic')" class="px-2.5 py-1 bg-white hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 border border-slate-200 rounded-lg text-[11px] font-bold transition-colors shadow-sm">
                                ข้อมูลพื้นฐาน+สิทธิ
                            </button>
                            <button type="button" onclick="window.applyExportPreset('medical')" class="px-2.5 py-1 bg-white hover:bg-rose-50 text-rose-700 border border-rose-200 rounded-lg text-[11px] font-bold transition-colors shadow-sm">
                                สุขภาพ+รอยตำหนิ${isAdminUser ? '+โรคประจำตัว' : ''}
                            </button>
                            <button type="button" onclick="window.applyExportPreset('all')" class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[11px] font-bold transition-colors shadow-sm">
                                เลือกทั้งหมด
                            </button>
                            <button type="button" onclick="window.applyExportPreset('none')" class="px-2.5 py-1 bg-white hover:bg-slate-200 text-slate-500 border border-slate-200 rounded-lg text-[11px] font-bold transition-colors">
                                ล้างการเลือก
                            </button>
                        </div>
                        <span id="export-col-count-badge" class="text-xs font-black text-indigo-600 bg-white px-2.5 py-1 rounded-lg border border-indigo-100 shadow-sm">
                            เลือกไว้ 0 คอลัมน์
                        </span>
                    </div>

                    <div class="max-h-[48vh] overflow-y-auto space-y-3 pr-1">
                        ${groupsHtml}
                    </div>
                </div>
            `,
            width: '52rem',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-download mr-1.5"></i> ดาวน์โหลดไฟล์ Excel (CSV)',
            cancelButtonText: 'ยกเลิก',
            didOpen: () => {
                updateSelectedColCounter();
            },
            customClass: {
                popup: 'rounded-3xl p-5 md:p-6',
                confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-6 py-3 text-sm shadow-md',
                cancelButton: 'bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl px-5 py-3 text-sm'
            },
            preConfirm: () => {
                const checkedCols = Array.from(document.querySelectorAll('.export-col-checkbox:checked')).map(cb => cb.value);
                if (checkedCols.length === 0) {
                    Swal.showValidationMessage('กรุณาติ๊กเลือกคอลัมน์ที่ต้องการส่งออกอย่างน้อย 1 คอลัมน์ครับ');
                    return false;
                }
                const scopeMode = document.querySelector('input[name="export_scope_mode"]:checked')?.value || 'filtered';
                try {
                    localStorage.setItem(EXPORT_PREF_KEY, JSON.stringify(checkedCols));
                } catch (e) {}

                return { selectedColumnKeys: checkedCols, scopeMode: scopeMode };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                executeCustomPatientExport(result.value.selectedColumnKeys, result.value.scopeMode);
            }
        });
    };

    function executeCustomPatientExport(selectedColumnKeys, scopeMode) {
        const filterForm = document.getElementById('patient-filter-form');
        const formData = filterForm ? new FormData(filterForm) : new FormData();
        const params = new URLSearchParams(formData);
        params.set('export_custom', '1');

        if (scopeMode === 'selected' && selectedPatientIds.size > 0) {
            params.set('selected_ids', Array.from(selectedPatientIds).join(','));
        }

        const exportUrl = `{{ route('backend.patients.index') }}?${params.toString()}`;

        Swal.fire({
            title: 'กำลังจัดเตรียมไฟล์รายงาน...',
            html: 'ระบบกำลังรวบรวมข้อมูลตามคอลัมน์ที่คุณเลือก',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); },
            customClass: { popup: 'rounded-3xl' }
        });

        fetch(exportUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(res => {
            if (res.status !== 'success' || !res.data || res.data.length === 0) {
                Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: 'ไม่มีข้อมูลพลทหารตามเงื่อนไขที่เลือกครับ' });
                return;
            }

            const allColsMeta = getAllAvailableColumns();
            const activeCols = allColsMeta.filter(c => selectedColumnKeys.includes(c.key));

            const escapeCsv = (val) => {
                const s = String(val ?? '').replace(/(\r\n|\n|\r)/gm, ' ').trim().replace(/"/g, '""');
                return `"${s}"`;
            };

            const headers = ['ลำดับ', ...activeCols.map(c => c.label)];
            const csvRows = [headers.map(escapeCsv).join(',')];

            res.data.forEach((rowObj, idx) => {
                const line = [escapeCsv(idx + 1)];
                activeCols.forEach(col => {
                    const rawVal = rowObj[col.key] ?? '-';
                    if (col.isCid && rawVal !== '-') {
                        line.push(`="${rawVal}"`);
                    } else {
                        line.push(escapeCsv(rawVal));
                    }
                });
                csvRows.push(line.join(','));
            });

            const csvFile = new Blob(["\uFEFF" + csvRows.join("\n")], { type: "text/csv;charset=utf-8;" });
            const downloadLink = document.createElement("a");
            const dateStamp = new Date().toISOString().slice(0, 10);
            downloadLink.download = `ข้อมูลกำลังพล_KSVR_${dateStamp}.csv`;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `ส่งออกข้อมูลสำเร็จ ${res.count} รายการ (${activeCols.length} คอลัมน์)`,
                showConfirmButton: false,
                timer: 3000
            });
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถดึงข้อมูลเพื่อส่งออกได้ในขณะนี้' });
        });
    }

    // =========================================================================
    // 🌟 4. ระบบซิงค์ HosXP แบบกลุ่ม (Bulk Sync) และรายบุคคลในตาราง
    // =========================================================================
    const singleSyncUrlTemplate = "{{ route('backend.patients.sync_hosxp', '__ID__') }}";

    function getJsPttypeBadgeClass(pttypeName) {
        if (!pttypeName) return 'bg-amber-50/70 text-amber-700 border-amber-200 font-medium';
        const n = pttypeName.toLowerCase();
        if (n.includes('บัตรทอง') || n.includes('หลักประกันสุขภาพ') || n.includes('uc') || n.includes('ถ้วนหน้า')) {
            return 'bg-amber-100 text-amber-900 border-amber-400 font-bold';
        }
        if (n.includes('ประกันสังคม')) {
            return 'bg-blue-600 text-white border-blue-700 font-bold';
        }
        if (n.includes('ข้าราชการพลเรือน') || (n.includes('ข้าราชการ') && !n.includes('ทหาร'))) {
            return 'bg-sky-100 text-sky-800 border-sky-300 font-bold';
        }
        if (n.includes('ครอบครัว')) {
            return 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold';
        }
        if (n.includes('ทหาร') || n.includes('กองทัพ') || n.includes('กลาโหม') || n.includes('พลฯ')) {
            return 'bg-emerald-800 text-white border-emerald-900 font-bold';
        }
        return 'bg-yellow-100 text-yellow-900 border-yellow-300 font-bold';
    }

    function syncSinglePatientInTable(patientId, forceSync = false, updateName = false) {
        const btns = document.querySelectorAll('.js-patient-sync-btn-' + patientId);
        btns.forEach(b => {
            b.disabled = true;
            b.querySelector('i')?.classList.add('fa-spin');
        });

        fetch(singleSyncUrlTemplate.replace('__ID__', patientId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ force_sync: forceSync, update_name: updateName })
        })
        .then(res => res.json())
        .then(data => {
            btns.forEach(b => {
                b.disabled = false;
                b.querySelector('i')?.classList.remove('fa-spin');
            });

            if (data.status === 'synced') {
                if (data.pttype_name) {
                    const pttypeCls = getJsPttypeBadgeClass(data.pttype_name);
                    document.querySelectorAll('.js-patient-pttype-' + patientId).forEach(ptBadge => {
                        ptBadge.className = `js-patient-pttype-${patientId} border px-2 py-0.5 rounded text-[11px] ${pttypeCls}`;
                        ptBadge.innerHTML = '<i class="fa-solid fa-shield-heart mr-1"></i>' + data.pttype_name;
                    });
                }
                document.querySelectorAll('.js-patient-hn-' + patientId).forEach(badge => {
                    badge.className = `js-patient-hn-${patientId} bg-blue-50 text-blue-700 border border-blue-200 font-bold px-2 py-0.5 rounded`;
                    badge.innerText = 'HN: ' + data.hn;
                });
                if (data.updated_name) {
                    document.querySelectorAll('.js-patient-name-' + patientId).forEach(nameEl => {
                        nameEl.innerText = data.updated_name;
                    });
                }
                btns.forEach(b => b.remove());
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
            } else if (data.status === 'mismatch_registrar') {
                Swal.fire({
                    icon: 'warning',
                    title: 'พบข้อมูลชื่อ-นามสกุลไม่ตรงกัน!',
                    html: `
                        <div class="text-left text-sm space-y-3 mt-2">
                            <p class="text-slate-600">เลขบัตร ปชช. <b class="font-mono text-slate-800">${data.cid}</b> พบใน HosXP (<b>HN: ${data.hn}</b>) แต่ชื่อไม่ตรงกัน:</p>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                <div class="text-xs text-slate-400 font-bold uppercase">ชื่อในระบบ KSVR MEWS:</div>
                                <div class="text-base font-bold text-slate-800">${data.mews_name}</div>
                            </div>
                            <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-200">
                                <div class="text-xs text-indigo-400 font-bold uppercase">ชื่อในระบบ HosXP รพ.:</div>
                                <div class="text-base font-bold text-indigo-800">${data.hosxp_name}</div>
                            </div>
                        </div>
                    `,
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-user-check mr-1"></i> ผูก HN + ใช้ชื่อตาม HosXP',
                    denyButtonText: 'ผูกเฉพาะ HN (คงชื่อเดิม)',
                    cancelButtonText: 'ยกเลิก',
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        denyButton: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        cancelButton: 'bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) syncSinglePatientInTable(patientId, true, true);
                    else if (result.isDenied) syncSinglePatientInTable(patientId, true, false);
                });
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: data.message, showConfirmButton: false, timer: 3500 });
            }
        });
    }

    function runBulkSyncHosxp(forceSync = false, updateName = false, customIds = null) {
        const idsToSync = customIds || Array.from(selectedPatientIds);
        if (idsToSync.length === 0) return;

        const btn = document.getElementById('btn-bulk-sync-hosxp');
        const icon = document.getElementById('bulk-sync-icon');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        Swal.fire({
            title: 'กำลังซิงค์ข้อมูลกับ HosXP...',
            html: `กำลังตรวจสอบเลขบัตรประชาชนและชื่อ-นามสกุลจำนวน <b>${idsToSync.length}</b> รายการ`,
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
                
                selectedPatientIds = new Set(mismatchIds);
                saveSelectedToStorage();
                fetchTableByUrl(window.location.href, false);

                let listHtml = `<div class="text-left text-xs space-y-2 mt-3 max-h-[45vh] overflow-y-auto pr-1">`;
                data.mismatched.forEach(m => {
                    listHtml += `
                        <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl">
                            <div class="font-bold text-slate-800">ปชช: <span class="font-mono">${m.cid}</span> <span class="text-blue-600 ml-1">(HN: ${m.hn})</span></div>
                            <div class="text-slate-600 mt-1">ระบบ MEWS: <b>${m.mews_name}</b></div>
                            <div class="text-indigo-700">ระบบ HosXP: <b>${m.hosxp_name}</b></div>
                        </div>
                    `;
                });
                listHtml += `</div>`;

                Swal.fire({
                    icon: 'warning',
                    title: `ซิงค์สำเร็จ ${data.synced_count} ราย | ชื่อไม่ตรง ${data.mismatched.length} ราย`,
                    html: `
                        <div class="text-sm text-slate-600">
                            ไม่พบใน HosXP: <b>${data.not_found_count}</b> ราย<br>
                            พบเลข ปชช. ตรงกันแต่ <b>ชื่อ-นามสกุลไม่ตรงกับ HosXP จำนวน ${data.mismatched.length} ราย</b> ดังนี้:
                            ${listHtml}
                        </div>
                    `,
                    width: '38rem',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-user-check mr-1"></i> ผูก HN + อัปเดตชื่อตาม HosXP ทั้งหมด',
                    denyButtonText: 'ผูกเฉพาะ HN (คงชื่อเดิมไว้)',
                    cancelButtonText: 'ข้ามไปก่อน',
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        denyButton: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        cancelButton: 'bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        runBulkSyncHosxp(true, true, mismatchIds);
                    } else if (result.isDenied) {
                        runBulkSyncHosxp(true, false, mismatchIds);
                    } else {
                        clearAllSelectedPatients();
                    }
                });
            } else {
                clearAllSelectedPatients();
                fetchTableByUrl(window.location.href, false);

                Swal.fire({
                    icon: 'success',
                    title: 'ซิงค์ข้อมูล HosXP เสร็จสมบูรณ์!',
                    html: `
                        <div class="text-sm text-slate-600 space-y-1 mt-2">
                            <p>✅ ผูกรหัส HN สำเร็จ: <b class="text-emerald-600 text-base">${data.synced_count}</b> ราย</p>
                            <p>🔍 ยังไม่พบประวัติใน HosXP: <b>${data.not_found_count}</b> ราย</p>
                            ${data.mismatched && data.mismatched.length > 0 ? `<p class="text-amber-600">⚠️ ชื่อไม่ตรงกัน (รอห้องทะเบียนตรวจสอบ): <b>${data.mismatched.length}</b> ราย</p>` : ''}
                        </div>
                    `,
                    confirmButtonText: 'รับทราบ',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'bg-slate-800 text-white rounded-xl px-6 py-2.5 font-bold' }
                });
            }
        })
        .catch(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
            Swal.fire({ icon: 'error', title: 'การเชื่อมต่อขัดข้อง', text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์เพื่อซิงค์ข้อมูลได้' });
        });
    }

    // =========================================================================
    // 🌟 5. ยืนยันการลบหลายรายการข้ามหน้า (Multi-Page Bulk Delete)
    // =========================================================================
    function confirmBulkDelete(e) {
        e.preventDefault();
        
        const checkedIds = Array.from(selectedPatientIds);
        if (checkedIds.length === 0) return;
        
        document.getElementById('bulk_patient_ids').value = checkedIds.join(',');

        Swal.fire({
            title: 'ยืนยันการลบข้อมูล?',
            text: `คุณต้องการลบข้อมูลประวัติทหารที่เลือกไว้ทั้งหมด ${checkedIds.length} รายการใช่หรือไม่? (การกระทำนี้ไม่สามารถย้อนกลับได้)`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ใช่, ลบทิ้งทั้งหมด!',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                sessionStorage.removeItem(STORAGE_KEY);
                document.getElementById('bulk-delete-form').submit();
            }
        });
    }

    // =========================================================================
    // 🌟 6. แสดงสถิติกำลังพลแยกตามผลัดทั้งหมด (Modal)
    // =========================================================================
    const allBatchStats = @json($safeBatchUnitStats);

    function showAllBatchStatsModal() {
        const batchKeys = Object.keys(allBatchStats);
        if (batchKeys.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'ยังไม่มีข้อมูลสถิติผลัด',
                text: 'ยังไม่มีการระบุผลัดทหารในฐานข้อมูลครับ',
                confirmButtonText: 'ปิดหน้าต่าง',
                customClass: { popup: 'rounded-3xl', confirmButton: 'bg-slate-800 text-white rounded-xl px-6 py-2.5 font-bold' }
            });
            return;
        }

        let html = '<div class="space-y-4 mt-3 text-left max-h-[60vh] overflow-y-auto pr-1">';
        
        batchKeys.forEach(batch => {
            const units = allBatchStats[batch] || [];
            const totalInBatch = units.reduce((sum, item) => sum + parseInt(item.total || 0), 0);

            html += `
                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200">
                    <div class="flex justify-between items-center border-b border-slate-200 pb-2 mb-3">
                        <span class="font-black text-slate-800 text-base flex items-center gap-2">
                            <i class="fa-solid fa-users-rays text-indigo-500"></i> ผลัดที่ ${batch}
                        </span>
                        <span class="bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                            รวม ${totalInBatch.toLocaleString()} นาย
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            `;

            units.forEach(u => {
                const unitName = u.military_unit ? (u.military_unit.short_name || u.military_unit.name) : 'ไม่ระบุหน่วย';
                html += `
                    <div class="bg-white px-3 py-2 rounded-xl border border-slate-100 flex justify-between items-center text-xs shadow-sm">
                        <span class="font-semibold text-slate-600 truncate pr-2"><i class="fa-solid fa-tent text-emerald-500 mr-1.5"></i>${unitName}</span>
                        <span class="font-black text-slate-800 shrink-0">${u.total} <span class="font-normal text-slate-400">นาย</span></span>
                    </div>
                `;
            });

            html += `</div></div>`;
        });

        html += '</div>';

        Swal.fire({
            title: '<div class="text-lg md:text-xl font-black text-slate-800"><i class="fa-solid fa-chart-pie text-indigo-500 mr-2"></i>สถิติกำลังพลแยกตามผลัดทั้งหมด</div>',
            html: html,
            width: '40rem',
            showCloseButton: true,
            confirmButtonText: 'ปิดหน้าต่าง',
            customClass: {
                popup: 'rounded-3xl p-4 md:p-6',
                confirmButton: 'bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl px-6 py-2.5'
            }
        });
    }

    // =========================================================================
    // 📅 7. ฟังก์ชันแสดงป๊อปอัปการ์ดใบนัดพบแพทย์รายบุคคล (เมื่อคลิกป้ายนัดในตารางรายชื่อ)
    // =========================================================================
    const appointmentManageBaseUrl = "{{ route('backend.recruit_appointments.index') }}";
    const appointmentCompleteUrlTemplate = "{{ route('backend.recruit_appointments.update_status', '__APP_ID__') }}";

    window.showSingleSoldierAppointmentModal = function(app) {
        const specificSoldierUrl = `${appointmentManageBaseUrl}?patient_id=${app.patient_id}&intake_batch=${encodeURIComponent(app.batch)}`;
        const completeActionUrl  = appointmentCompleteUrlTemplate.replace('__APP_ID__', app.app_id);

        const urgentBanner = app.is_today 
            ? `<div class="mb-3 px-3 py-1.5 bg-rose-600 text-white rounded-xl text-xs font-black text-center animate-pulse">🚨 มีกำหนดนัดพบแพทย์ในวันนี้!</div>`
            : (app.is_tomorrow ? `<div class="mb-3 px-3 py-1.5 bg-amber-500 text-white rounded-xl text-xs font-black text-center">⏰ แจ้งเตือนล่วงหน้า: มีนัดพบแพทย์ในวันพรุ่งนี้!</div>` : '');

        const noteBox = app.note 
            ? `<div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 font-semibold flex items-start gap-2">
                   <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
                   <div><b>การเตรียมตัว / จุดติดต่อ:</b><br>${app.note}</div>
               </div>`
            : '';

        Swal.fire({
            title: `<div class="text-base md:text-lg font-black text-slate-800 flex items-center justify-center gap-2"><i class="fa-solid fa-calendar-check text-sky-500"></i> รายละเอียดการนัดพบแพทย์</div>`,
            html: `
                <div class="text-left mt-2">
                    ${urgentBanner}
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2.5 text-xs sm:text-sm">
                        <div class="flex justify-between items-start border-b border-slate-200 pb-2.5">
                            <div>
                                <div class="font-black text-slate-900 text-base">${app.full_name}</div>
                                <div class="text-xs text-slate-500 mt-0.5"><i class="fa-solid fa-tent text-indigo-500 mr-1"></i>${app.unit} (ผลัด ${app.batch})</div>
                            </div>
                            <span class="bg-blue-100 text-blue-700 font-bold px-2.5 py-1 rounded-lg text-xs shrink-0">HN: ${app.hn}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                            <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                                <span class="text-[11px] font-bold text-slate-400 block uppercase">วัน-เวลานัดหมาย</span>
                                <span class="font-black text-sky-700 text-sm">${app.date_full}</span>
                                <span class="text-xs text-slate-600 block">เวลา ${app.time}</span>
                            </div>
                            <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                                <span class="text-[11px] font-bold text-slate-400 block uppercase">สถานที่นัด / แผนก</span>
                                <span class="font-black ${app.is_camp ? 'text-emerald-700' : 'text-indigo-700'} text-sm">${app.hospital_name}</span>
                                <span class="text-xs text-slate-600 block">คลินิก: <b>${app.clinic}</b></span>
                            </div>
                        </div>

                        <div class="bg-white p-3 rounded-xl border border-slate-200/80 space-y-1">
                            <div><span class="font-bold text-slate-500">แพทย์ผู้นัด:</span> <span class="font-bold text-slate-800">${app.doctor}</span></div>
                            <div><span class="font-bold text-slate-500">สาเหตุการนัด:</span> <span class="font-bold text-indigo-700">${app.cause}</span></div>
                        </div>

                        ${noteBox}
                    </div>
                </div>
            `,
            width: '36rem',
            showCloseButton: true,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> ยืนยันไปตามนัดแล้ว',
            denyButtonText: '<i class="fa-solid fa-calendar-days mr-1"></i> ดู/จัดการนัดของทหารนายนี้',
            cancelButtonText: 'ปิดหน้าต่าง',
            customClass: {
                popup: 'rounded-3xl p-5 md:p-6',
                confirmButton: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs shadow-sm',
                denyButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs shadow-sm',
                cancelButton: 'bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl px-4 py-2.5 text-xs'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = completeActionUrl;
                form.innerHTML = `
                    <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="status" value="completed">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (result.isDenied) {
                window.location.href = specificSoldierUrl;
            }
        });
    };

    // =========================================================================
    // 🎖️ 8. ฟังก์ชันเปิดป๊อปอัปเปลี่ยนสถานะกำลังพล (มารายงานตัว / ไม่มารายงานตัว / ต้องโทษ / เสียชีวิต)
    // =========================================================================
    const dutyStatusUrlTemplate = "{{ route('backend.patients.update_duty_status', '__ID__') }}";

    window.openDutyStatusModal = function(patientId, fullName, currentStatus = 'active', currentNote = '') {
        Swal.fire({
            title: `<div class="text-lg font-black text-slate-800 flex items-center justify-center gap-2"><i class="fa-solid fa-user-gear text-purple-600"></i> เปลี่ยนสถานะกำลังพล</div>`,
            html: `
                <div class="text-left space-y-3 mt-2">
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-xs sm:text-sm">
                        พลทหาร: <b class="text-slate-900 text-base">${fullName}</b>
                    </div>

                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 rounded-2xl border-2 border-slate-200 cursor-pointer hover:bg-emerald-50/40 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/60 transition-all">
                            <input type="radio" name="swal_duty_status" value="active" ${currentStatus === 'active' ? 'checked' : ''} onchange="toggleSwalDutyNote(this.value)" class="mt-0.5 w-4 h-4 text-emerald-600">
                            <div>
                                <div class="text-xs sm:text-sm font-black text-emerald-800">🟢 มารายงานตัว (ประจำการปกติ)</div>
                                <div class="text-[11px] text-slate-500">ปฏิบัติหน้าที่และเข้ารับการคัดกรอง/ประเมิน MEWS ได้ตามปกติ</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-2xl border-2 border-slate-200 cursor-pointer hover:bg-orange-50/40 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50/60 transition-all">
                            <input type="radio" name="swal_duty_status" value="absent" ${currentStatus === 'absent' ? 'checked' : ''} onchange="toggleSwalDutyNote(this.value)" class="mt-0.5 w-4 h-4 text-orange-600">
                            <div>
                                <div class="text-xs sm:text-sm font-black text-orange-800">🟠 ไม่มารายงานตัว / ขาดหนี</div>
                                <div class="text-[11px] text-slate-500">ไม่มารายงานตัวแรกรับ หรือหนีราชการ (งดประเมิน MEWS)</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-2xl border-2 border-slate-200 cursor-pointer hover:bg-rose-50/40 has-[:checked]:border-rose-600 has-[:checked]:bg-rose-50/60 transition-all">
                            <input type="radio" name="swal_duty_status" value="imprisoned" ${currentStatus === 'imprisoned' ? 'checked' : ''} onchange="toggleSwalDutyNote(this.value)" class="mt-0.5 w-4 h-4 text-rose-600">
                            <div>
                                <div class="text-xs sm:text-sm font-black text-rose-800">🔴 ต้องโทษ / ดำเนินคดี</div>
                                <div class="text-[11px] text-slate-500">อยู่ระหว่างถูกควบคุมตัว รอคำพิพากษา หรือจำคุก (งดประเมิน MEWS)</div>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-2xl border-2 border-slate-200 cursor-pointer hover:bg-slate-100 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900/5 transition-all">
                            <input type="radio" name="swal_duty_status" value="deceased" ${currentStatus === 'deceased' ? 'checked' : ''} onchange="toggleSwalDutyNote(this.value)" class="mt-0.5 w-4 h-4 text-slate-900">
                            <div>
                                <div class="text-xs sm:text-sm font-black text-slate-900">⚫ เสียชีวิต</div>
                                <div class="text-[11px] text-slate-500">ยุติการประเมินและล็อกข้อมูลการรักษาทั้งหมด</div>
                            </div>
                        </label>
                    </div>

                    <div id="swal_duty_note_box" class="${currentStatus === 'active' ? 'hidden' : ''} pt-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">ระบุรายละเอียด / หมายเหตุเพิ่มเติม</label>
                        <input type="text" id="swal_duty_note_input" value="${currentNote}" placeholder="เช่น ไม่มารายงานตัวตั้งแต่แรกรับ, เรือนจำ มทบ.29..." 
                               class="w-full px-3.5 py-2.5 bg-white border-2 border-slate-300 focus:border-indigo-500 rounded-xl text-xs sm:text-sm text-slate-800 outline-none">
                    </div>
                </div>
            `,
            width: '34rem',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk mr-1"></i> บันทึกสถานะ',
            cancelButtonText: 'ยกเลิก',
            customClass: {
                popup: 'rounded-3xl p-6',
                confirmButton: 'bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl px-6 py-2.5 text-xs sm:text-sm',
                cancelButton: 'bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl px-5 py-2.5 text-xs sm:text-sm'
            },
            preConfirm: () => {
                const selected = document.querySelector('input[name="swal_duty_status"]:checked')?.value || 'active';
                const note = document.getElementById('swal_duty_note_input')?.value.trim() || '';
                return { duty_status: selected, duty_status_note: note };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                fetch(dutyStatusUrlTemplate.replace('__ID__', patientId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        _method: 'PATCH',
                        duty_status: result.value.duty_status,
                        duty_status_note: result.value.duty_status_note
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        fetchTableByUrl(window.location.href, false);
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
                    } else {
                        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message || 'ไม่สามารถอัปเดตสถานะได้' });
                    }
                });
            }
        });
    };

    window.toggleSwalDutyNote = function(val) {
        const box = document.getElementById('swal_duty_note_box');
        const input = document.getElementById('swal_duty_note_input');
        if (!box || !input) return;
        if (val === 'active') {
            box.classList.add('hidden');
            input.value = '';
        } else {
            box.classList.remove('hidden');
            input.focus();
        }
    };

    // =========================================================================
    // ⚡ 9. ระบบอัปเดตข้อมูลและเด้งแจ้งเตือนแบบ Real-time (Reverb WebSocket + Smart Polling)
    // =========================================================================
    let knownActiveMewsIds = new Set();
    let isRealtimeRefreshing = false;

    function collectCurrentActiveMewsIds(rootDoc = document) {
        const ids = new Set();
        rootDoc.querySelectorAll('#realtime-active-cases-container .active-case-row').forEach(row => {
            const mId = row.getAttribute('data-mews-id');
            if (mId) ids.add(String(mId));
        });
        return ids;
    }

    function refreshPatientsPageRealtime(triggerPopupOnNew = true) {
        if (isRealtimeRefreshing || document.hidden) return;

        const searchBox = document.getElementById('live-search-input');
        if (searchBox && document.activeElement === searchBox && searchBox.value.trim().length > 0) {
            return;
        }

        isRealtimeRefreshing = true;

        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // 1. ตรวจสอบว่ามีผู้ป่วยเคสใหม่เข้ามาในตาราง "กำลังรับการรักษา (Active Cases)" หรือไม่
            const newActiveContainer = doc.getElementById('realtime-active-cases-container');
            const currentActiveContainer = document.getElementById('realtime-active-cases-container');

            if (newActiveContainer && currentActiveContainer) {
                let newlyArrivedRow = null;
                newActiveContainer.querySelectorAll('.active-case-row').forEach(row => {
                    const mId = String(row.getAttribute('data-mews-id') || '');
                    if (mId && !knownActiveMewsIds.has(mId)) {
                        newlyArrivedRow = row;
                    }
                });

                currentActiveContainer.innerHTML = newActiveContainer.innerHTML;
                knownActiveMewsIds = collectCurrentActiveMewsIds(document);

                // 🚨 หากพบเคสใหม่เข้ามา ให้ส่งเสียงและเด้งป๊อปอัปแจ้งเตือนทันที!
                if (triggerPopupOnNew && newlyArrivedRow && !Swal.isVisible()) {
                    const ptName = newlyArrivedRow.getAttribute('data-patient-name') || 'พลทหาร';
                    const ptUnit = newlyArrivedRow.getAttribute('data-unit-name') || '-';
                    const ptScore = newlyArrivedRow.getAttribute('data-mews-score') || '0';
                    const ptRisk = newlyArrivedRow.getAttribute('data-risk-level') || 'normal';
                    const ptUrl = newlyArrivedRow.getAttribute('data-case-url') || window.location.href;

                    const isEmer = (ptRisk === 'emergency');
                    const isObs  = (ptRisk === 'observe');

                    if (typeof window.playAlertSound === 'function') {
                        window.playAlertSound(isEmer);
                    }

                    const badgeColor = isEmer ? 'bg-rose-600 text-white' : (isObs ? 'bg-amber-500 text-white' : 'bg-emerald-600 text-white');
                    const titleText  = isEmer ? '🚨 เคสฉุกเฉินใหม่ (Emergency)!' : (isObs ? '⚠️ เคสเฝ้าระวังใหม่ (Observe)' : '🩺 มีผู้ป่วยเข้ารับการรักษาใหม่');

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: isEmer ? 'error' : (isObs ? 'warning' : 'info'),
                        title: titleText,
                        html: `
                            <div class="text-xs text-slate-700 mt-1 space-y-1">
                                <div class="font-black text-sm text-slate-900">${ptName} <span class="text-indigo-600">(${ptUnit})</span></div>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="px-2 py-0.5 rounded font-black ${badgeColor}">MEWS: ${ptScore} คะแนน</span>
                                    <a href="${ptUrl}" class="font-bold text-indigo-600 hover:underline">คลิกดูเคส <i class="fa-solid fa-arrow-right"></i></a>
                                </div>
                            </div>
                        `,
                        showConfirmButton: false,
                        showCloseButton: true,
                        timer: isEmer ? 10000 : 6000,
                        timerProgressBar: true,
                        customClass: {
                            popup: 'rounded-2xl shadow-xl border border-slate-200 p-4'
                        }
                    });
                }
            }

            // 2. อัปเดตการ์ด KPI ในโหมด Overview
            const newOverview = doc.getElementById('realtime-overview-container');
            const curOverview = document.getElementById('realtime-overview-container');
            if (newOverview && curOverview) {
                curOverview.innerHTML = newOverview.innerHTML;
            }

            // 3. อัปเดตตารางทะเบียนรายชื่อกำลังพล (คงค่า Checkbox ที่ติ๊กไว้เหมือนเดิม)
            const newTable = doc.getElementById('patients-table-container');
            const curTable = document.getElementById('patients-table-container');
            if (newTable && curTable) {
                curTable.innerHTML = newTable.innerHTML;
                initBulkDeleteCheckboxes();
            }
        })
        .catch(err => console.debug('Realtime Sync:', err))
        .finally(() => {
            isRealtimeRefreshing = false;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        knownActiveMewsIds = collectCurrentActiveMewsIds(document);

        if (typeof window.Echo !== 'undefined') {
            window.Echo.channel('mews-channel')
                .listen('.mews.updated', () => {
                    refreshPatientsPageRealtime(true);
                });

            window.Echo.private('er-alerts')
                .listen('.mews.emergency', () => {
                    refreshPatientsPageRealtime(true);
                });
        }

        setInterval(() => {
            refreshPatientsPageRealtime(true);
        }, 20000);
    });
</script>
@endsection