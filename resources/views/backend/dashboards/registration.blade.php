@extends('layouts.backend')

@section('title', 'แดชบอร์ดงานเวชระเบียน - KSVR Hospital')
@section('page_header', 'ศูนย์ควบคุมข้อมูลผู้ป่วย (Registration Control)')

@section('content')
@php
    $isValidCidMod11 = function (?string $cid): bool {
        $clean = preg_replace('/\D/', '', (string) $cid);
        if (strlen($clean) !== 13) return false;
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $clean[$i] * (13 - $i);
        }
        return ((11 - ($sum % 11)) % 10) === (int) $clean[12];
    };

    $shownActionCount = count($actionNeededPatients);
    $validSyncableIds = $actionNeededPatients
        ->filter(fn($p) => $isValidCidMod11($p->cid))
        ->pluck('id')
        ->values();
    $invalidInTableCount = $shownActionCount - $validSyncableIds->count();
    $batchQueryParam = ($selectedBatch !== 'all' && $selectedBatch !== '') ? ['batch' => $selectedBatch] : [];
@endphp

<div class="max-w-7xl mx-auto mt-2 sm:mt-4 pb-12 space-y-6">

    @include('backend.dashboards.partials.view_tabs')

    <!-- 🚨 0. โซนแจ้งเตือนด่วนที่สุด: เคส ER / OPD ที่กำลังเปิดรักษาอยู่แต่ยังไม่มี HN -->
    @if(isset($urgentNoHnCases) && $urgentNoHnCases->count() > 0)
    <div class="bg-gradient-to-r from-rose-600 via-rose-600 to-red-600 rounded-3xl p-4 sm:p-6 text-white shadow-lg border border-rose-500 animate-fade-in-down space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shrink-0">
                    <i class="fa-solid fa-bell animate-bounce"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base sm:text-lg font-black tracking-wide">
                            เคสด่วนกำลังรักษา (ER/OPD) ที่ยังไม่มีรหัส HN จำนวน {{ $urgentNoHnCases->count() }} ราย!
                        </h3>
                        <span class="px-2 py-0.5 rounded-full bg-white text-rose-700 text-[10px] font-black uppercase">เปิดบัตรด่วน</span>
                    </div>
                    <p class="text-xs sm:text-sm text-rose-100 mt-0.5">
                        กรุณาก๊อปปี้เลข ปชช. ไปเปิดทำบัตรใน HosXP แล้วกดปุ่มซิงค์เพื่อให้ห้องฉุกเฉินและห้องยาเบิกจ่ายได้ทันที
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($urgentNoHnCases as $urgentMews)
                @php
                    $upt = $urgentMews->patient;
                    $uFullName = trim(($upt->rank ?? '') . ($upt->first_name ?? '') . ' ' . ($upt->last_name ?? ''));
                    $uUnit = $upt->militaryUnit?->short_name ?? $upt->militaryUnit?->name ?? $upt->company ?? '-';
                    $uCidValid = $isValidCidMod11($upt->cid);
                @endphp
                <div class="bg-white text-slate-800 rounded-2xl p-4 shadow-sm flex flex-col justify-between gap-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="js-reg-name-{{ $upt->id }} font-black text-slate-900 text-sm sm:text-base">{{ $uFullName }}</span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black {{ $urgentMews->risk_level === 'emergency' ? 'bg-rose-100 text-rose-700' : ($urgentMews->risk_level === 'observe' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">
                                    MEWS {{ $urgentMews->total_score }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="font-mono font-bold {{ $uCidValid ? 'text-slate-700' : 'text-rose-600' }}"><i class="fa-regular fa-id-card mr-1"></i>{{ $upt->cid ?: '-' }}</span>
                                @if(!$uCidValid)
                                    <span class="px-1.5 py-0.2 bg-amber-100 text-amber-800 rounded text-[10px] font-bold">เลขผิดหลัก</span>
                                @endif
                                <span>• {{ $uUnit }} {{ $upt->intake_batch ? "(ผลัด {$upt->intake_batch})" : '' }}</span>
                            </div>
                        </div>
                        <span class="js-reg-badge-{{ $upt->id }} px-2 py-0.5 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg text-[10px] font-black shrink-0">
                            ยังไม่มี HN
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100">
                        <button type="button" onclick="copyToClipboard('{{ $upt->cid }}')"
                                class="py-2 px-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold flex items-center justify-center gap-1 transition-colors">
                            <i class="fa-regular fa-copy"></i> ก๊อปปี้ ปชช.
                        </button>
                        <button type="button" onclick="syncPatientInRegDashboard({{ $upt->id }})"
                                class="js-reg-btn-{{ $upt->id }} py-2 px-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1 shadow-sm transition-colors">
                            <i class="fa-solid fa-rotate"></i> ซิงค์ HN
                        </button>
                        <a href="{{ route('backend.patients.show', $upt->id) }}"
                           class="py-2 px-2 bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 rounded-xl text-xs font-bold flex items-center justify-center gap-1 transition-colors">
                            <i class="fa-solid fa-address-card"></i> แฟ้มประวัติ
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- 🔍 1. โซนค้นหาหลัก (Hero Search) + ปุ่มสแกนบัตร OCR กล้องสด & ทางลัดงานเวชระเบียน -->
    <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 rounded-3xl p-6 md:p-10 shadow-xl relative overflow-hidden border border-slate-700/80">
        <div class="absolute top-0 right-0 -mt-10 -mr-10 text-white/[0.04] text-9xl pointer-events-none select-none">
            <i class="fa-solid fa-id-card-clip"></i>
        </div>
        <div class="absolute -bottom-20 -left-16 w-64 h-64 bg-blue-500/15 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="relative z-10 max-w-4xl mx-auto space-y-5">
            <!-- แถวบน: หัวเรื่อง + ตัวกรองผลัดทหาร -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 bg-blue-500 rounded-2xl flex items-center justify-center text-2xl text-white shadow-lg shadow-blue-950/50 shrink-0">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-white tracking-tight">ศูนย์ควบคุมทะเบียนประวัติผู้ป่วย</h2>
                        <p class="text-slate-300 text-xs sm:text-sm font-medium mt-0.5">ค้นหาด้วยเลข ปชช., รหัสทหาร, HN หรือชื่อ-นามสกุล หรือสแกนหน้าบัตร ปชช. กล้องสด</p>
                    </div>
                </div>

                <!-- 🎖️ ตัวกรองแยกตามผลัดทหาร -->
                <form method="GET" action="{{ route('backend.dashboard') }}" class="flex items-center gap-2 bg-slate-950/50 border border-slate-700 rounded-2xl px-3.5 py-2 shrink-0 self-start md:self-center">
                    <input type="hidden" name="view" value="registration">
                    <span class="text-xs font-bold text-slate-400 whitespace-nowrap"><i class="fa-solid fa-filter text-blue-400 mr-1"></i>มุมมองผลัด:</span>
                    <select name="batch" onchange="this.form.submit()" class="bg-transparent text-xs sm:text-sm font-black text-white outline-none cursor-pointer pr-1">
                        <option value="all" class="bg-slate-800 text-white" {{ $selectedBatch === 'all' ? 'selected' : '' }}>ทุกผลัดในระบบ</option>
                        @foreach($availableBatches as $b)
                            <option value="{{ $b }}" class="bg-slate-800 text-white" {{ $selectedBatch == $b ? 'selected' : '' }}>ผลัดที่ {{ $b }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            
            <!-- ฟอร์มค้นหาหลัก -->
            <form action="{{ route('backend.patients.processSearch') }}" method="POST" class="flex flex-col sm:flex-row gap-2.5">
                @csrf
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fa-solid fa-keyboard text-slate-400"></i>
                    </div>
                    <input type="text" name="search_term" 
                           class="w-full pl-11 pr-4 py-3.5 rounded-2xl border-0 focus:ring-4 focus:ring-blue-500/40 bg-white text-slate-800 shadow-inner text-base font-medium placeholder:text-slate-400 placeholder:font-normal" 
                           placeholder="พิมพ์เลขบัตร ปชช. 13 หลัก, HN หรือชื่อ-นามสกุล..." required autocomplete="off">
                </div>
                <button type="submit" class="py-3.5 px-7 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl font-bold text-base shadow-md transition-all hover:-translate-y-0.5 shrink-0">
                    <i class="fa-solid fa-magnifying-glass mr-1.5"></i>ค้นหาข้อมูล
                </button>
            </form>

            <!-- ปุ่มทางลัดงานเวชระเบียน (สแกนบัตร OCR + เพิ่มผู้ป่วยใหม่ + นำเข้า Excel) -->
            <div class="flex flex-wrap items-center gap-2 pt-1">
                @if(\Illuminate\Support\Facades\Route::has('backend.patients.search'))
                    <a href="{{ route('backend.patients.search') }}"
                       class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-black shadow-sm inline-flex items-center justify-center gap-2 transition-all">
                        <i class="fa-solid fa-camera"></i> สแกนบัตร ปชช. (กล้องสด OCR)
                    </a>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('backend.patients.create'))
                    <a href="{{ route('backend.patients.create') }}"
                       class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/15 text-xs sm:text-sm font-bold inline-flex items-center justify-center gap-2 transition-all">
                        <i class="fa-solid fa-user-plus text-sky-300"></i> + ลงทะเบียนผู้ป่วยใหม่
                    </a>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('backend.patients.import'))
                    <a href="{{ route('backend.patients.import') }}"
                       class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/15 text-xs sm:text-sm font-bold inline-flex items-center justify-center gap-2 transition-all">
                        <i class="fa-solid fa-file-excel text-emerald-300"></i> นำเข้ารายชื่อ Excel
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- 📊 2. โซนกล่องสถิติ KPI + แถบความคืบหน้าการผูกรหัส HN -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-5">
        
        <!-- กล่อง 1: ยอดกำลังพล & Progress Bar ความคืบหน้า HN -->
        <a href="{{ route('backend.patients.index', array_merge(['view' => 'list'], $batchQueryParam)) }}"
           class="bg-white dark:bg-slate-800 p-5 sm:p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 hover:shadow-md hover:border-blue-200 transition-all flex flex-col justify-between gap-3 group">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        กำลังพล{{ $selectedBatch !== 'all' ? " (ผลัด {$selectedBatch})" : 'ทั้งหมดในระบบ' }}
                    </p>
                    <h3 class="text-3xl font-black text-slate-800 dark:text-white mt-1">
                        {{ number_format($totalPatients) }} <span class="text-sm font-medium text-slate-400">นาย</span>
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-xs font-bold">
                    <span class="text-emerald-600 dark:text-emerald-400">ผูก HN แล้ว {{ number_format($syncedPatients) }} / {{ number_format($activeDutyTotal) }} นาย</span>
                    <span class="text-slate-700 dark:text-slate-200">{{ $hnSyncPercent }}%</span>
                </div>
                <div class="w-full h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full transition-all duration-500" style="width: {{ min(100, $hnSyncPercent) }}%"></div>
                </div>
                @if($inactiveExcludedCount > 0)
                    <p class="text-[11px] text-slate-400 font-medium">* ตัดกลุ่มไม่มารายงานตัว/ต้องโทษ/เสียชีวิตออก {{ number_format($inactiveExcludedCount) }} นาย</p>
                @endif
            </div>
        </a>

        <!-- กล่อง 2: สถานะรอ Sync HosXP -->
        <a href="{{ route('backend.patients.index', array_merge(['view' => 'list', 'hn_status' => 'unsynced'], $batchQueryParam)) }}"
           class="bg-white dark:bg-slate-800 p-5 sm:p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 flex items-center justify-between group hover:shadow-md hover:border-rose-300 transition-all relative overflow-hidden">
            <div class="relative z-10">
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                    ยังไม่ผูกรหัส HN (รอ Sync) <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-rose-400"></i>
                </p>
                <div class="flex items-baseline gap-2">
                    <h3 id="unsynced-counter" class="text-3xl font-black {{ $unsyncedPatients > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ number_format($unsyncedPatients) }}
                    </h3>
                    <span class="text-xs sm:text-sm font-medium text-slate-400">ราย (เฉพาะประจำการ)</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-1.5 font-medium">คลิกเพื่อเปิดดูและจัดการรายชื่อทั้งหมด</p>
            </div>
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl {{ $unsyncedPatients > 0 ? 'bg-rose-50 text-rose-500' : 'bg-emerald-50 text-emerald-500' }} flex items-center justify-center text-2xl relative z-10 shrink-0">
                <i class="fa-solid {{ $unsyncedPatients > 0 ? 'fa-triangle-exclamation animate-pulse' : 'fa-check-double' }}"></i>
            </div>
            @if($unsyncedPatients > 0)
                <div class="absolute bottom-0 left-0 w-full h-1 bg-rose-500"></div>
            @endif
        </a>

        <!-- กล่อง 3: เคส MEWS ที่กำลังเปิดอยู่ -->
        <button type="button" onclick="showActivePatientsModal()"
                class="w-full text-left bg-white dark:bg-slate-800 p-5 sm:p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 flex items-center justify-between group hover:shadow-md hover:border-amber-300 transition-all focus:outline-none">
            <div>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                    เคส MEWS ที่กำลังเปิดอยู่ <i class="fa-solid fa-hand-pointer text-amber-400 animate-bounce"></i>
                </p>
                <div class="flex items-baseline gap-2">
                    <h3 class="text-3xl font-black text-amber-500">{{ number_format($activeCases) }}</h3>
                    <span class="text-xs sm:text-sm font-medium text-slate-400">เคส (คลิกดูรายชื่อ)</span>
                </div>
                @if(isset($urgentNoHnCases) && $urgentNoHnCases->count() > 0)
                    <p class="text-[11px] text-rose-600 font-bold mt-1.5">🚨 ไม่มี HN {{ $urgentNoHnCases->count() }} เคส</p>
                @else
                    <p class="text-[11px] text-emerald-600 font-bold mt-1.5">✅ ทุกเคสที่เปิดอยู่มี HN ครบแล้ว</p>
                @endif
            </div>
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-2xl group-hover:bg-amber-500 group-hover:text-white transition-colors shadow-sm shrink-0">
                <i class="fa-solid fa-truck-medical"></i>
            </div>
        </button>

    </div>

    <!-- 🛡️ 2.5 ศูนย์ตรวจสอบคุณภาพข้อมูล (Data Quality Audit) & สรุปสัดส่วนสิทธิการรักษา -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">

        <!-- ฝั่งซ้าย (7 คอลัมน์): ศูนย์ตรวจสอบคุณภาพข้อมูล 4 หมวด -->
        <div class="xl:col-span-7 bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-700 pb-3">
                <h3 class="font-black text-slate-800 dark:text-white text-sm sm:text-base flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-shield-halved"></i>
                    </span>
                    <span>ศูนย์ตรวจสอบคุณภาพข้อมูล (Data Quality Audit)</span>
                </h3>
                <span class="text-[11px] font-bold text-slate-400">คลิกแต่ละช่องเพื่อตรวจแก้</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <!-- 1. เลข ปชช. ผิดหลัก Mod-11 -->
                <button type="button" onclick="showAuditListModal('invalid_cid')"
                        class="text-left p-3.5 rounded-2xl border-2 transition-all {{ $dataAudit['invalid_cid_count'] > 0 ? 'bg-amber-50/70 border-amber-200 hover:border-amber-400' : 'bg-slate-50/70 dark:bg-slate-900/50 border-slate-100 dark:border-slate-700' }}">
                    <div class="flex items-center justify-between text-xs font-bold {{ $dataAudit['invalid_cid_count'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                        <span>ปชช. ผิดหลัก</span>
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div class="text-2xl font-black mt-1.5 {{ $dataAudit['invalid_cid_count'] > 0 ? 'text-amber-700' : 'text-emerald-600' }}">
                        {{ number_format($dataAudit['invalid_cid_count']) }}
                    </div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">ไม่ผ่านสูตร Mod-11</div>
                </button>

                <!-- 2. เลข ปชช. ซ้ำ -->
                <button type="button" onclick="showAuditListModal('duplicate_cid')"
                        class="text-left p-3.5 rounded-2xl border-2 transition-all {{ $dataAudit['duplicate_cid_count'] > 0 ? 'bg-rose-50/70 border-rose-200 hover:border-rose-400' : 'bg-slate-50/70 dark:bg-slate-900/50 border-slate-100 dark:border-slate-700' }}">
                    <div class="flex items-center justify-between text-xs font-bold {{ $dataAudit['duplicate_cid_count'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                        <span>ข้อมูลซ้ำซ้อน</span>
                        <i class="fa-solid fa-clone"></i>
                    </div>
                    <div class="text-2xl font-black mt-1.5 {{ $dataAudit['duplicate_cid_count'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ number_format($dataAudit['duplicate_cid_count']) }}
                    </div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">เลข ปชช. ซ้ำในระบบ</div>
                </button>

                <!-- 3. มี HN แต่ยังไม่มีสิทธิ -->
                <a href="{{ route('backend.patients.index', array_merge(['view' => 'list', 'hn_status' => 'synced', 'pttype' => 'unsynced'], $batchQueryParam)) }}"
                   class="text-left p-3.5 rounded-2xl border-2 transition-all {{ $dataAudit['missing_pttype_count'] > 0 ? 'bg-indigo-50/70 border-indigo-200 hover:border-indigo-400' : 'bg-slate-50/70 dark:bg-slate-900/50 border-slate-100 dark:border-slate-700' }}">
                    <div class="flex items-center justify-between text-xs font-bold {{ $dataAudit['missing_pttype_count'] > 0 ? 'text-indigo-700' : 'text-slate-400' }}">
                        <span>ขาดสิทธิรักษา</span>
                        <i class="fa-solid fa-shield-heart"></i>
                    </div>
                    <div class="text-2xl font-black mt-1.5 {{ $dataAudit['missing_pttype_count'] > 0 ? 'text-indigo-600' : 'text-emerald-600' }}">
                        {{ number_format($dataAudit['missing_pttype_count']) }}
                    </div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">มี HN แต่รอซิงค์สิทธิ</div>
                </a>

                <!-- 4. ขาดวันเกิด / เบอร์โทร -->
                <button type="button" onclick="showAuditListModal('missing_contact')"
                        class="text-left p-3.5 rounded-2xl border-2 transition-all {{ $dataAudit['missing_contact_count'] > 0 ? 'bg-sky-50/70 border-sky-200 hover:border-sky-400' : 'bg-slate-50/70 dark:bg-slate-900/50 border-slate-100 dark:border-slate-700' }}">
                    <div class="flex items-center justify-between text-xs font-bold {{ $dataAudit['missing_contact_count'] > 0 ? 'text-sky-700' : 'text-slate-400' }}">
                        <span>ประวัติไม่ครบ</span>
                        <i class="fa-solid fa-phone-slash"></i>
                    </div>
                    <div class="text-2xl font-black mt-1.5 {{ $dataAudit['missing_contact_count'] > 0 ? 'text-sky-700' : 'text-emerald-600' }}">
                        {{ number_format($dataAudit['missing_contact_count']) }}
                    </div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">ขาดวันเกิด/เบอร์โทร</div>
                </button>
            </div>
        </div>

        <!-- ฝั่งขวา (5 คอลัมน์): สรุปสัดส่วนสิทธิการรักษาพยาบาลตามรหัสสีมาตรฐาน -->
        <div class="xl:col-span-5 bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between space-y-3">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-700 pb-3">
                <h3 class="font-black text-slate-800 dark:text-white text-sm sm:text-base flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-notes-medical"></i>
                    </span>
                    <span>สัดส่วนสิทธิการรักษาพยาบาล</span>
                </h3>
                <a href="{{ route('backend.patients.index', array_merge(['view' => 'overview'], $batchQueryParam)) }}" class="text-xs font-bold text-indigo-600 hover:underline">
                    ดูรายละเอียด
                </a>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($coverageSummary as $covKey => $cov)
                    @php
                        $covUrl = $covKey === 'unsynced'
                            ? route('backend.patients.index', array_merge(['view' => 'list', 'pttype' => 'unsynced'], $batchQueryParam))
                            : route('backend.patients.index', array_merge(['view' => 'list'], $batchQueryParam));
                    @endphp
                    <a href="{{ $covUrl }}"
                       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-bold shadow-sm hover:opacity-90 transition-opacity {{ $cov['badge'] }}">
                        <span>{{ $cov['label'] }}</span>
                        <span class="px-1.5 py-0.2 rounded-md bg-black/10 font-black">{{ number_format($cov['count']) }}</span>
                    </a>
                @endforeach
            </div>

            <!-- แถบสัดส่วนรวมแนวนอน -->
            @if($activeDutyTotal > 0)
            <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden flex">
                @foreach($coverageSummary as $cov)
                    @if($cov['count'] > 0)
                        <div class="{{ $cov['bar'] }} h-full" style="width: {{ round(($cov['count'] / $activeDutyTotal) * 100, 2) }}%" title="{{ $cov['label'] }}: {{ number_format($cov['count']) }} นาย"></div>
                    @endif
                @endforeach
            </div>
            @endif
        </div>

    </div>

    <!-- 📋 3. โซนตารางสิ่งที่ต้องทำ (To-Do List: Missing HN - พร้อมตรวจ Mod-11 และปุ่มก๊อปปี้ ปชช.) -->
    @if($shownActionCount > 0)
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-rose-100 dark:border-rose-900/40 overflow-hidden">
        <div class="px-5 sm:px-6 py-4 bg-rose-50/50 dark:bg-rose-950/20 border-b border-rose-100 dark:border-rose-900/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-rose-800 dark:text-rose-300 flex flex-wrap items-center gap-2 text-sm sm:text-base">
                    <i class="fa-solid fa-clipboard-list"></i>
                    @if($unsyncedPatients > $shownActionCount)
                        <span>ผู้ป่วยที่ยังไม่มีรหัส HN (แสดง {{ $shownActionCount }} จากทั้งหมด {{ number_format($unsyncedPatients) }} รายการล่าสุด)</span>
                    @else
                        <span>ผู้ป่วยที่ยังไม่มีรหัส HN (ทั้งหมด {{ $shownActionCount }} รายการที่รอซิงค์จาก HosXP)</span>
                    @endif
                </h3>
                <p class="text-xs text-rose-600/80 dark:text-rose-400/80 mt-0.5">
                    @if($invalidInTableCount > 0)
                        พบเลข ปชช. ผิดหลัก Mod-11 จำนวน <b>{{ $invalidInTableCount }} รายการ</b> (ระบบจะข้ามอัตโนมัติตอนกดซิงค์กลุ่มเพื่อไม่ให้โหลด HosXP)
                    @else
                        เมื่อซิงค์สำเร็จแล้ว รายการที่ยังไม่มี HN ลำดับถัดไปจะเลื่อนขึ้นมาแสดงแทนอัตโนมัติ
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if($validSyncableIds->count() > 0)
                    <button type="button" onclick="syncTopActionNeeded()" id="btn-sync-top5" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-rotate" id="icon-sync-top5"></i> ซิงค์รายการที่ถูกต้อง ({{ $validSyncableIds->count() }})
                    </button>
                @endif
                <a href="{{ route('backend.patients.index', array_merge(['view' => 'list', 'hn_status' => 'unsynced'], $batchQueryParam)) }}" class="px-3.5 py-2 bg-white dark:bg-slate-800 hover:bg-rose-50 text-rose-600 border border-rose-200 dark:border-rose-800 rounded-xl text-xs font-bold transition-colors shadow-sm">
                    ดูทั้งหมด ({{ number_format($unsyncedPatients) }}) <i class="fa-solid fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>

        <!-- 📱 โหมดมือถือ -->
        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($actionNeededPatients as $pt)
            @php $cidValid = $isValidCidMod11($pt->cid); @endphp
            <div class="p-4 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="js-reg-name-{{ $pt->id }} font-bold text-slate-800 dark:text-white text-sm">
                            {{ $pt->rank }}{{ $pt->first_name }} {{ $pt->last_name }}
                        </div>
                        <div class="text-xs text-slate-500 font-mono mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="{{ $cidValid ? '' : 'text-rose-600 font-bold' }}"><i class="fa-regular fa-id-card mr-1"></i>{{ $pt->cid ?: '-' }}</span>
                            @if(!$cidValid)
                                <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 border border-amber-300 rounded text-[10px] font-sans font-bold">
                                    <i class="fa-solid fa-triangle-exclamation mr-0.5"></i>เลขผิดหลัก
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            <i class="fa-solid fa-tent text-indigo-400 mr-1"></i>{{ $pt->militaryUnit?->short_name ?? $pt->militaryUnit?->name ?? $pt->company ?? '-' }}
                            @if($pt->intake_batch)
                                <span class="text-indigo-500 font-semibold">(ผลัด {{ $pt->intake_batch }})</span>
                            @endif
                        </div>
                    </div>
                    <span class="js-reg-badge-{{ $pt->id }} px-2.5 py-1 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg text-[11px] font-bold shrink-0">
                        รอซิงค์ HN
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-2 pt-1">
                    <button type="button" onclick="copyToClipboard('{{ $pt->cid }}')"
                            class="py-2 px-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs flex items-center justify-center gap-1">
                        <i class="fa-regular fa-copy"></i> ก๊อปปี้
                    </button>
                    <button type="button" onclick="syncPatientInRegDashboard({{ $pt->id }})"
                            class="js-reg-btn-{{ $pt->id }} py-2 px-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1 shadow-sm">
                        <i class="fa-solid fa-rotate"></i> ซิงค์
                    </button>
                    <a href="{{ route('backend.patients.show', $pt->id) }}"
                       class="py-2 px-2 bg-slate-100 text-slate-700 hover:bg-blue-600 hover:text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1">
                        <i class="fa-solid fa-user-pen"></i> แก้ไข
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        <!-- 💻 โหมดคอมพิวเตอร์ -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[780px] text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider whitespace-nowrap">
                        <th class="px-6 py-4 font-bold border-b border-slate-100 dark:border-slate-700">ยศ ชื่อ-นามสกุล</th>
                        <th class="px-6 py-4 font-bold border-b border-slate-100 dark:border-slate-700">เลขบัตร ปชช.</th>
                        <th class="px-6 py-4 font-bold border-b border-slate-100 dark:border-slate-700">สังกัด</th>
                        <th class="px-6 py-4 font-bold border-b border-slate-100 dark:border-slate-700 text-center">สถานะ HN</th>
                        <th class="px-6 py-4 font-bold border-b border-slate-100 dark:border-slate-700 text-right">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @foreach($actionNeededPatients as $pt)
                    @php $cidValid = $isValidCidMod11($pt->cid); @endphp
                    <tr id="reg-row-{{ $pt->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors group border-b border-slate-50 dark:border-slate-700/60 last:border-0">
                        <td class="js-reg-name-{{ $pt->id }} px-6 py-4 font-bold text-slate-800 dark:text-white whitespace-nowrap">{{ $pt->rank }}{{ $pt->first_name }} {{ $pt->last_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span class="font-medium font-mono {{ $cidValid ? 'text-slate-600 dark:text-slate-300' : 'text-rose-600 font-bold' }}">{{ $pt->cid ?: '-' }}</span>
                                @if($pt->cid)
                                    <button type="button" onclick="copyToClipboard('{{ $pt->cid }}')" class="text-slate-400 hover:text-indigo-600 transition-colors" title="ก๊อปปี้เลขบัตร ปชช. ไปวางใน HosXP">
                                        <i class="fa-regular fa-copy text-xs"></i>
                                    </button>
                                @endif
                                @if(!$cidValid)
                                    <span class="px-2 py-0.5 bg-amber-100 text-amber-800 border border-amber-300 rounded-md text-[10px] font-bold" title="เลขบัตรไม่ผ่านสูตร Mod-11 กรุณาตรวจสอบหรือแก้ไขก่อนซิงค์ HosXP">
                                        <i class="fa-solid fa-triangle-exclamation mr-0.5"></i>เลขผิดหลัก
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-300 whitespace-nowrap">
                            {{ $pt->militaryUnit?->short_name ?? $pt->militaryUnit?->name ?? $pt->company ?? '-' }}
                            @if($pt->intake_batch)
                                <span class="text-xs text-indigo-500 font-semibold">(ผลัด {{ $pt->intake_batch }})</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            <span class="js-reg-badge-{{ $pt->id }} px-2.5 py-1 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg text-xs font-bold">
                                รอซิงค์ HN
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-2">
                                <button type="button" onclick="syncPatientInRegDashboard({{ $pt->id }})"
                                        class="js-reg-btn-{{ $pt->id }} inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold transition-colors text-xs shadow-sm">
                                    <i class="fa-solid fa-rotate"></i> ซิงค์ HosXP
                                </button>
                                <a href="{{ route('backend.patients.show', $pt->id) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-blue-600 hover:text-white rounded-lg font-bold transition-colors text-xs shadow-sm">
                                    <i class="fa-solid fa-user-pen"></i> ดูข้อมูล
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-3xl p-8 text-center">
        <div class="w-14 h-14 rounded-2xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 flex items-center justify-center text-2xl mx-auto mb-3">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h3 class="text-base sm:text-lg font-black text-emerald-900 dark:text-emerald-200">เยี่ยมมาก! ผูกรหัส HN ครบทุกคนแล้ว</h3>
        <p class="text-xs sm:text-sm text-emerald-700 dark:text-emerald-400 mt-1">ไม่มีรายชื่อกำลังพลประจำการที่ค้างรอซิงค์ HN ในผลัดที่เลือกครับ</p>
    </div>
    @endif

</div>
@endsection

@section('scripts')
<script>
    const activePatients = @json($activePatientsList);
    const validSyncableActionIds = @json($validSyncableIds);
    const regDataAudit = @json($dataAudit);
    const patientShowUrlTemplate = "{{ route('backend.patients.show', '__ID__') }}";
    const singleSyncUrlTemplate = "{{ route('backend.patients.sync_hosxp', '__ID__') }}";

    function escapeRegHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // 🔄 ซิงค์ HosXP รายบุคคล (ลดตัวเลข KPI สดทันทีเมื่อสำเร็จ)
    function syncPatientInRegDashboard(patientId, forceSync = false, updateName = false) {
        const btns = document.querySelectorAll('.js-reg-btn-' + patientId);
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
                document.querySelectorAll('.js-reg-badge-' + patientId).forEach(badge => {
                    badge.className = 'js-reg-badge-' + patientId + ' px-2.5 py-1 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-xs font-black shrink-0';
                    badge.innerHTML = '<i class="fa-solid fa-check mr-1"></i>HN: ' + escapeRegHtml(data.hn);
                });
                if (data.updated_name) {
                    document.querySelectorAll('.js-reg-name-' + patientId).forEach(el => {
                        el.innerText = data.updated_name;
                    });
                }
                btns.forEach(b => b.remove());

                // ลดตัวเลข KPI สดทันที
                const counterEl = document.getElementById('unsynced-counter');
                if (counterEl) {
                    const currentVal = parseInt(counterEl.innerText.replace(/,/g, ''), 10) || 0;
                    if (currentVal > 0) counterEl.innerText = (currentVal - 1).toLocaleString();
                }

                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2500 });
            } else if (data.status === 'mismatch_registrar') {
                Swal.fire({
                    icon: 'warning',
                    title: 'พบข้อมูลชื่อ-นามสกุลไม่ตรงกัน!',
                    html: `
                        <div class="text-left text-sm space-y-3 mt-2">
                            <p class="text-slate-600">เลขบัตร ปชช. <b class="font-mono text-slate-800">${escapeRegHtml(data.cid)}</b> พบใน HosXP (<b>HN: ${escapeRegHtml(data.hn)}</b>) แต่ชื่อไม่ตรงกัน:</p>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                <div class="text-xs text-slate-400 font-bold uppercase">ชื่อในระบบ KSVR MEWS:</div>
                                <div class="text-base font-bold text-slate-800">${escapeRegHtml(data.mews_name)}</div>
                            </div>
                            <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-200">
                                <div class="text-xs text-indigo-400 font-bold uppercase">ชื่อในระบบ HosXP รพ.:</div>
                                <div class="text-base font-bold text-indigo-800">${escapeRegHtml(data.hosxp_name)}</div>
                            </div>
                        </div>
                    `,
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-user-check mr-1"></i> ผูก HN + ใช้ชื่อตาม HosXP',
                    denyButtonText: 'ผูกเฉพาะ HN (คงชื่อเดิม)',
                    cancelButtonText: 'ข้ามไปก่อน',
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        denyButton: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        cancelButton: 'bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) syncPatientInRegDashboard(patientId, true, true);
                    else if (result.isDenied) syncPatientInRegDashboard(patientId, true, false);
                });
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: data.message, showConfirmButton: false, timer: 3500 });
            }
        })
        .catch(() => {
            btns.forEach(b => {
                b.disabled = false;
                b.querySelector('i')?.classList.remove('fa-spin');
            });
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'เชื่อมต่อเซิร์ฟเวอร์ HosXP ไม่สำเร็จ', showConfirmButton: false, timer: 3000 });
        });
    }

    // 🔄 ซิงค์เฉพาะรายการที่เลขบัตร ปชช. ผ่าน Mod-11 พร้อมกัน (ป้องกันการยิง Query เสียเปล่าไป HosXP)
    function syncTopActionNeeded(forceSync = false, updateName = false, customIds = null) {
        const ids = customIds || validSyncableActionIds;
        if (!ids || ids.length === 0) {
            Swal.fire({ icon: 'info', title: 'ไม่มีรายการที่พร้อมซิงค์', text: 'รายการที่เหลือมีเลขบัตร ปชช. ผิดหลัก Mod-11 กรุณาตรวจสอบแก้ไขก่อนซิงค์ครับ' });
            return;
        }

        const btn = document.getElementById('btn-sync-top5');
        const icon = document.getElementById('icon-sync-top5');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        fetch("{{ route('backend.patients.bulk_sync_hosxp') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                patient_ids: ids,
                force_sync: forceSync,
                update_name: updateName
            })
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');

            if (data.status === 'success') {
                if (data.mismatched && data.mismatched.length > 0 && data.is_registrar_admin) {
                    const mismatchIds = data.mismatched.map(m => m.id);
                    let listHtml = `<div class="text-left text-xs space-y-2 mt-3 max-h-[40vh] overflow-y-auto pr-1">`;
                    data.mismatched.forEach(m => {
                        listHtml += `
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl">
                                <div class="font-bold text-slate-800">ปชช: <span class="font-mono">${escapeRegHtml(m.cid)}</span> <span class="text-blue-600 ml-1">(HN: ${escapeRegHtml(m.hn)})</span></div>
                                <div class="text-slate-600 mt-1">ระบบ MEWS: <b>${escapeRegHtml(m.mews_name)}</b></div>
                                <div class="text-indigo-700">ระบบ HosXP: <b>${escapeRegHtml(m.hosxp_name)}</b></div>
                            </div>
                        `;
                    });
                    listHtml += `</div>`;

                    Swal.fire({
                        icon: 'warning',
                        title: `ซิงค์สำเร็จ ${data.synced_count} ราย | ชื่อไม่ตรง ${data.mismatched.length} ราย`,
                        html: `พบรายชื่อไม่ตรงกับ HosXP ดังนี้:${listHtml}`,
                        width: '36rem',
                        showDenyButton: true,
                        showCancelButton: true,
                        confirmButtonText: 'ผูก HN + ใช้ชื่อตาม HosXP',
                        denyButtonText: 'ผูกเฉพาะ HN',
                        cancelButtonText: 'ข้ามไปก่อน',
                        customClass: {
                            popup: 'rounded-3xl p-6',
                            confirmButton: 'bg-indigo-600 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                            denyButton: 'bg-emerald-600 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                            cancelButton: 'bg-slate-200 text-slate-700 font-bold rounded-xl px-4 py-2.5 text-xs'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) syncTopActionNeeded(true, true, mismatchIds);
                        else if (result.isDenied) syncTopActionNeeded(true, false, mismatchIds);
                        else if (data.synced_count > 0) window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: data.synced_count > 0 ? 'success' : 'info',
                        title: `ซิงค์สำเร็จ ${data.synced_count} รายการ`,
                        text: data.not_found_count > 0 ? `ยังไม่พบประวัติใน HosXP จำนวน ${data.not_found_count} รายการ` : 'อัปเดตข้อมูลเรียบร้อยแล้ว',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        if (data.synced_count > 0) window.location.reload();
                    });
                }
            }
        })
        .catch(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
            Swal.fire({ icon: 'error', title: 'เชื่อมต่อไม่สำเร็จ', text: 'ไม่สามารถติดต่อฐานข้อมูล HosXP ได้ในขณะนี้' });
        });
    }

    // 🛡️ เปิดหน้าต่างแสดงรายการจากศูนย์ตรวจสอบคุณภาพข้อมูล (Data Quality Audit)
    function showAuditListModal(type) {
        const metaMap = {
            invalid_cid: {
                title: 'รายชื่อเลขบัตร ปชช. ผิดหลัก (ไม่ผ่าน Mod-11)',
                icon: 'fa-id-card text-amber-500',
                list: regDataAudit.invalid_cid_list || [],
                empty: 'ไม่พบเลขบัตร ปชช. ผิดหลักในผลัดนี้ ข้อมูลสมบูรณ์ 100% ครับ'
            },
            duplicate_cid: {
                title: 'รายชื่อเลขบัตร ปชช. ซ้ำซ้อนในระบบ',
                icon: 'fa-clone text-rose-500',
                list: regDataAudit.duplicate_cid_list || [],
                empty: 'ไม่พบรายชื่อที่มีเลขบัตร ปชช. ซ้ำกันในระบบครับ'
            },
            missing_contact: {
                title: 'รายชื่อที่ข้อมูลไม่ครบ (ขาดวันเกิด / เบอร์โทร)',
                icon: 'fa-phone-slash text-sky-500',
                list: regDataAudit.missing_contact_list || [],
                empty: 'กำลังพลทุกคนมีข้อมูลวันเกิดและเบอร์โทรศัพท์ครบถ้วนแล้วครับ'
            }
        };

        const cfg = metaMap[type];
        if (!cfg) return;

        if (!cfg.list || cfg.list.length === 0) {
            Swal.fire({ icon: 'success', title: 'ข้อมูลสะอาดสมบูรณ์!', text: cfg.empty, confirmButtonColor: '#059669', customClass: { popup: 'rounded-3xl' } });
            return;
        }

        let html = '<div class="space-y-2.5 mt-3 text-left max-h-[60vh] overflow-y-auto pr-1">';
        cfg.list.forEach((item, idx) => {
            const url = patientShowUrlTemplate.replace('__ID__', item.id);
            const extraTag = item.missing ? `<span class="px-2 py-0.5 bg-sky-100 text-sky-700 rounded text-[10px] font-bold">ขาด: ${escapeRegHtml(item.missing)}</span>` : '';
            html += `
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    <div class="min-w-0">
                        <div class="font-bold text-slate-800 text-sm">${idx + 1}. ${escapeRegHtml(item.name)}</div>
                        <div class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="font-mono font-bold text-rose-600">${escapeRegHtml(item.cid)}</span>
                            <span>• ${escapeRegHtml(item.unit)} (ผลัด ${escapeRegHtml(item.batch)})</span>
                            ${extraTag}
                        </div>
                    </div>
                    <a href="${url}" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold text-center shrink-0">
                        <i class="fa-solid fa-user-pen mr-1"></i> แก้ไขข้อมูล
                    </a>
                </div>
            `;
        });
        html += '</div>';

        Swal.fire({
            title: `<div class="text-base sm:text-lg font-black text-slate-800 flex items-center justify-center gap-2"><i class="fa-solid ${cfg.icon}"></i> ${cfg.title} (${cfg.list.length} ราย)</div>`,
            html: html,
            width: '40rem',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: { container: 'z-[99999]', popup: 'rounded-3xl p-5' }
        });
    }

    function showActivePatientsModal() {
        if (activePatients.length === 0) {
            Swal.fire({
                icon: 'success',
                title: 'เยี่ยมมาก!',
                text: 'ขณะนี้ไม่มีเคส MEWS ที่ค้างอยู่ในระบบ',
                confirmButtonColor: '#10b981'
            });
            return;
        }

        let htmlContent = '<div class="space-y-3 mt-4 text-left max-h-[60vh] overflow-y-auto pr-1">';
        
        activePatients.forEach(function(mews) {
            let patient = mews.patient;
            if (!patient) return;
            let patientUrl = patientShowUrlTemplate.replace('__ID__', patient.id);
            
            let unitName = 'ไม่ระบุกองร้อย';
            if (patient.military_unit && (patient.military_unit.short_name || patient.military_unit.name)) {
                unitName = patient.military_unit.short_name || patient.military_unit.name;
                if (patient.battalion) unitName += ' (' + patient.battalion + ')';
            } else if (patient.company) {
                unitName = patient.company;
                if (patient.battalion) unitName += ' (' + patient.battalion + ')';
            }

            let hnBadge = patient.hn && patient.hn !== '-'
                ? `<span class="bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-md text-xs font-bold border border-blue-200">HN: ${escapeRegHtml(patient.hn)}</span>` 
                : `<span class="bg-rose-100 text-rose-700 px-2.5 py-0.5 rounded-md text-xs font-bold border border-rose-200 animate-pulse">ยังไม่มี HN</span>`;

            let actionButtonsHtml = (!patient.hn || patient.hn === '-') ? `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-slate-200/70">
                    <button type="button" onclick="copyToClipboard('${escapeRegHtml(patient.cid || '')}', this)" class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 py-2 px-3 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-regular fa-copy"></i> ก๊อปปี้ ปชช.
                    </button>
                    <a href="${patientUrl}" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-3 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-user-pen"></i> ดูข้อมูล / อัปเดต HN
                    </a>
                </div>
            ` : `
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 border-t border-slate-200/70">
                    <button type="button" onclick="copyToClipboard('${escapeRegHtml(patient.cid || '')}', this)" class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 py-2 px-2.5 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1">
                        <i class="fa-regular fa-copy"></i> ก๊อปปี้ ปชช.
                    </button>
                    <button type="button" onclick="copyToClipboard('${escapeRegHtml(patient.hn)}', this)" class="w-full bg-blue-100 hover:bg-blue-200 text-blue-700 py-2 px-2.5 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1">
                        <i class="fa-regular fa-copy"></i> ก๊อปปี้ HN
                    </button>
                    <a href="${patientUrl}" class="w-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 py-2 px-2.5 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1">
                        <i class="fa-solid fa-address-card"></i> ดูข้อมูลพลทหาร
                    </a>
                </div>
            `;

            htmlContent += `
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl hover:bg-white hover:shadow-sm transition-all">
                    <div class="flex justify-between items-start mb-2 gap-2">
                        <a href="${patientUrl}" class="font-bold text-slate-800 hover:text-indigo-600 text-base transition-colors">
                            ${escapeRegHtml((patient.rank || '') + (patient.first_name || '') + ' ' + (patient.last_name || ''))}
                        </a>
                        ${hnBadge}
                    </div>
                    <div class="text-sm text-slate-600 space-y-1 mb-3">
                        <p><i class="fa-regular fa-id-card w-5 text-center text-slate-400"></i> ปชช: <b class="font-mono">${escapeRegHtml(patient.cid || '-')}</b></p>
                        <p><i class="fa-solid fa-tent w-5 text-center text-slate-400"></i> สังกัด: ${escapeRegHtml(unitName)} ${patient.intake_batch ? `<span class="text-xs text-indigo-600 font-bold">(ผลัด ${escapeRegHtml(patient.intake_batch)})</span>` : ''}</p>
                        <p class="text-amber-600 text-xs mt-2 font-medium"><i class="fa-regular fa-clock"></i> อัปเดตล่าสุด: ${new Date(mews.updated_at).toLocaleTimeString('th-TH')} น.</p>
                    </div>
                    ${actionButtonsHtml}
                </div>
            `;
        });
        
        htmlContent += '</div>';

        Swal.fire({
            title: '<span class="text-amber-600"><i class="fa-solid fa-clipboard-user mr-2"></i>รายชื่อเคสที่กำลังเปิดอยู่</span>',
            html: htmlContent,
            showCloseButton: true,
            showConfirmButton: false,
            width: '34rem',
            customClass: { container: 'z-[99999]', popup: 'rounded-3xl' }
        });
    }

    // 📋 แสดง Toast แจ้งเตือนแบบไม่ปิด SweetAlert2 Modal (ลอยอยู่ชั้นบนสุด)
    function showLightweightCopyToast(message) {
        let toast = document.getElementById('ksvr-copy-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'ksvr-copy-toast';
            toast.style.cssText = 'position:fixed;top:1.25rem;right:1.25rem;z-index:999999;pointer-events:none;transition:all 0.25s ease;';
            document.body.appendChild(toast);
        }

        toast.innerHTML = `
            <div style="display:flex;align-items:center;gap:0.5rem;background:#0f172a;color:#fff;padding:0.65rem 1rem;border-radius:0.875rem;box-shadow:0 12px 30px -6px rgba(15,23,42,0.35);border:1px solid rgba(52,211,153,0.4);font-size:0.8rem;font-weight:700;">
                <i class="fa-solid fa-circle-check" style="color:#34d399;font-size:1rem;"></i>
                <span>${message}</span>
            </div>
        `;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';

        clearTimeout(window._ksvrCopyToastTimer);
        window._ksvrCopyToastTimer = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
        }, 1600);
    }

    // 📋 ก๊อปปี้ข้อความโดยไม่ปิด Modal (รองรับทั้ง HTTPS และ HTTP วงแลน รพ.)
    function copyToClipboard(text, btnEl = null) {
        if (!text) return;

        const onCopiedSuccess = () => {
            showLightweightCopyToast(`ก๊อปปี้ ${text} แล้ว`);

            // เปลี่ยนสถานะปุ่มที่กดให้เป็นเครื่องหมายถูกชั่วคราว (ช่วยจำว่าก๊อปปี้รายไหนไปแล้ว)
            if (btnEl) {
                if (!btnEl.dataset.origHtml) {
                    btnEl.dataset.origHtml = btnEl.innerHTML;
                    btnEl.dataset.origClass = btnEl.className;
                }
                btnEl.innerHTML = '<i class="fa-solid fa-check"></i> ก๊อปปี้แล้ว';
                btnEl.className = btnEl.dataset.origClass
                    .replace(/bg-slate-200|bg-blue-100|text-slate-700|text-blue-700/g, '')
                    + ' bg-emerald-600 text-white';

                clearTimeout(btnEl._resetTimer);
                btnEl._resetTimer = setTimeout(() => {
                    btnEl.innerHTML = btnEl.dataset.origHtml;
                    btnEl.className = btnEl.dataset.origClass;
                }, 1500);
            }
        };

        // 1. กรณีเข้าผ่าน HTTPS ใช้ Clipboard API มาตรฐาน
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(onCopiedSuccess).catch(() => fallbackCopy(text, onCopiedSuccess));
        } else {
            // 2. กรณีเข้าผ่าน HTTP วงแลนภายใน รพ. ใช้ Fallback (วางใน .swal2-popup เพื่อหลบ Focus Trap)
            fallbackCopy(text, onCopiedSuccess);
        }
    }

    function fallbackCopy(text, callback) {
        const container = document.querySelector('.swal2-popup') || document.body;
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
        container.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            document.execCommand('copy');
            callback();
        } catch (e) {
            console.warn('Copy failed:', e);
        }
        container.removeChild(textarea);
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.Echo) {
            window.Echo.channel('mews-channel')
                .listen('.mews.updated', () => {
                    window.location.reload();
                });
        }
        setInterval(() => {
            window.location.reload();
        }, 180000); 
    });
</script>
@endsection