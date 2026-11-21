@extends('layouts.backend')

@section('title', 'ประวัติการใช้งานระบบ (Audit Log) - KSVR Hospital')
@section('page_header', 'ประวัติการใช้งานระบบ (Audit Log)')

@section('content')
@php
    // พจนานุกรมแปลงค่า Action (รองรับทั้ง Trait RecordsSystemLog ใหม่ และ Log รุ่นเดิมครบทั้งหมด)
    $actionDict = [
        // Trait อัตโนมัติ (RecordsSystemLog)
        'created'                => ['text' => 'เพิ่มข้อมูลใหม่',          'color' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'icon' => 'fa-plus-circle'],
        'updated'                => ['text' => 'แก้ไขข้อมูล',              'color' => 'bg-amber-100 text-amber-800 border-amber-200',       'icon' => 'fa-pen-to-square'],
        'deleted'                => ['text' => 'ลบข้อมูล',                 'color' => 'bg-rose-100 text-rose-700 border-rose-200',          'icon' => 'fa-trash-can'],

        // Log เฉพาะทางรุ่นเดิม
        'create_patient'         => ['text' => 'เพิ่มประวัติผู้ป่วยใหม่',     'color' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'icon' => 'fa-user-plus'],
        'update_patient'         => ['text' => 'แก้ไขประวัติผู้ป่วย',        'color' => 'bg-amber-100 text-amber-800 border-amber-200',       'icon' => 'fa-user-pen'],
        'delete_patient'         => ['text' => 'ลบประวัติผู้ป่วย',           'color' => 'bg-rose-100 text-rose-700 border-rose-200',          'icon' => 'fa-trash-can'],
        'bulk_delete_patients'   => ['text' => 'ลบประวัติผู้ป่วย (แบบกลุ่ม)', 'color' => 'bg-red-100 text-red-800 border-red-200',             'icon' => 'fa-dumpster-fire'],
        'import_patients_excel'  => ['text' => 'นำเข้ารายชื่อผ่าน Excel',    'color' => 'bg-teal-100 text-teal-700 border-teal-200',          'icon' => 'fa-file-excel'],
        'search_hosxp_success'   => ['text' => 'ดึงข้อมูล HosXP สำเร็จ',    'color' => 'bg-blue-100 text-blue-700 border-blue-200',          'icon' => 'fa-cloud-arrow-down'],
        'search_hosxp_not_found' => ['text' => 'ค้นหา HosXP ไม่พบ',         'color' => 'bg-amber-100 text-amber-700 border-amber-200',       'icon' => 'fa-magnifying-glass-minus'],
        'search_hosxp_error'     => ['text' => 'เชื่อมต่อ HosXP ล้มเหลว',   'color' => 'bg-rose-100 text-rose-700 border-rose-200',          'icon' => 'fa-triangle-exclamation'],
        'create_user'            => ['text' => 'สร้างผู้ใช้ใหม่',            'color' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'icon' => 'fa-user-plus'],
        'update_user'            => ['text' => 'แก้ไขข้อมูลผู้ใช้',          'color' => 'bg-amber-100 text-amber-800 border-amber-200',       'icon' => 'fa-user-pen'],
        'reset_password'         => ['text' => 'รีเซ็ตรหัสผ่าน',            'color' => 'bg-purple-100 text-purple-700 border-purple-200',    'icon' => 'fa-key'],
        'delete_user'            => ['text' => 'ลบผู้ใช้',                  'color' => 'bg-rose-100 text-rose-700 border-rose-200',          'icon' => 'fa-user-minus'],
        'create_mews'            => ['text' => 'บันทึกประเมิน MEWS',        'color' => 'bg-indigo-100 text-indigo-700 border-indigo-200',    'icon' => 'fa-stethoscope'],
        'update_mews'            => ['text' => 'แก้ไขผล MEWS',              'color' => 'bg-orange-100 text-orange-700 border-orange-200',    'icon' => 'fa-file-signature'],
        'delete_mews'            => ['text' => 'ลบผลประเมิน MEWS',          'color' => 'bg-red-100 text-red-700 border-red-200',             'icon' => 'fa-file-circle-xmark'],
        'discharge_patient'      => ['text' => 'จำหน่ายคนไข้ (จบเคส)',      'color' => 'bg-slate-200 text-slate-700 border-slate-300',       'icon' => 'fa-door-open'],
        'create_military_unit'   => ['text' => 'เพิ่มหน่วยทหาร',            'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200',  'icon' => 'fa-tent-arrow-down-to-line'],
        'update_military_unit'   => ['text' => 'แก้ไขหน่วยทหาร',            'color' => 'bg-amber-50 text-amber-700 border-amber-200',        'icon' => 'fa-pen-to-square'],
        'delete_military_unit'   => ['text' => 'ลบหน่วยทหาร',               'color' => 'bg-rose-50 text-rose-700 border-rose-200',           'icon' => 'fa-tent-arrow-turn-left'],
    ];

    $moduleBadges = [
        'Patient'              => ['label' => 'ทะเบียนพลทหาร',      'cls' => 'bg-blue-50 text-blue-700 border-blue-200'],
        'MewsAssessment'       => ['label' => 'ประเมิน MEWS',        'cls' => 'bg-rose-50 text-rose-700 border-rose-200'],
        'CovidVitalLog'        => ['label' => 'สัญญาณชีพรายวัน',     'cls' => 'bg-pink-50 text-pink-700 border-pink-200'],
        'RecruitInfirmaryStay' => ['label' => 'ห้องพยาบาล/แยกนอน',   'cls' => 'bg-teal-50 text-teal-700 border-teal-200'],
        'PatientMedication'    => ['label' => 'รายการยา e-MAR',      'cls' => 'bg-cyan-50 text-cyan-800 border-cyan-200'],
        'PatientMedicationLog' => ['label' => 'เช็คกิน/ทายา e-MAR',  'cls' => 'bg-sky-50 text-sky-700 border-sky-200'],
        'RecruitAppointment'   => ['label' => 'นัดพบแพทย์',          'cls' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        'RecruitMorbidity'     => ['label' => 'รายงานเจ็บป่วย',      'cls' => 'bg-orange-50 text-orange-700 border-orange-200'],
        'RecruitScreening'     => ['label' => 'คัดกรองทหารใหม่',     'cls' => 'bg-amber-50 text-amber-800 border-amber-200'],
        'MilitaryDischarge'    => ['label' => 'เอกสารนำปลด',         'cls' => 'bg-purple-50 text-purple-700 border-purple-200'],
        'MilitaryUnit'         => ['label' => 'หน่วยฝึกทหาร',        'cls' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'User'                 => ['label' => 'ผู้ใช้งานระบบ',       'cls' => 'bg-violet-50 text-violet-700 border-violet-200'],
        'MewsTemplate'         => ['label' => 'ตั้งค่าเกณฑ์ MEWS',    'cls' => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200'],
        'EmergencySymptom'     => ['label' => 'ตั้งค่าอาการฉุกเฉิน',  'cls' => 'bg-red-50 text-red-700 border-red-200'],
    ];

    $hasActiveFilter = request()->filled('search') || request()->filled('module') || request()->filled('action_group') || request()->filled('date_from') || request()->filled('date_to');
@endphp

<div class="max-w-7xl mx-auto space-y-5 pb-12">

    <!-- 📊 1. การ์ดสรุป KPI 4 มิติ (2x2 บนมือถือ) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white dark:bg-slate-800 p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <span class="text-[11px] sm:text-xs font-bold text-slate-400 block truncate">กิจกรรมวันนี้</span>
                <h4 class="text-2xl sm:text-3xl font-black text-slate-800 dark:text-white mt-1">{{ number_format($summary['today_total']) }} <span class="text-xs font-medium text-slate-400">ครั้ง</span></h4>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 flex items-center justify-center text-lg sm:text-xl shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <span class="text-[11px] sm:text-xs font-bold text-emerald-600 dark:text-emerald-400 block truncate">เจ้าหน้าที่ใช้งานวันนี้</span>
                <h4 class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($summary['today_users']) }} <span class="text-xs font-medium text-slate-400">คน</span></h4>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 flex items-center justify-center text-lg sm:text-xl shrink-0">
                <i class="fa-solid fa-users-viewfinder"></i>
            </div>
        </div>

        <a href="{{ route('backend.system_logs.index', ['action_group' => 'deleted', 'date_from' => date('Y-m-d')]) }}"
           class="bg-white dark:bg-slate-800 p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-sm border {{ $summary['today_delete'] > 0 ? 'border-rose-200 hover:border-rose-400' : 'border-slate-100 dark:border-slate-700' }} flex items-center justify-between gap-2 transition-all">
            <div class="min-w-0">
                <span class="text-[11px] sm:text-xs font-bold text-rose-600 dark:text-rose-400 block truncate">การลบข้อมูลวันนี้</span>
                <h4 class="text-2xl sm:text-3xl font-black {{ $summary['today_delete'] > 0 ? 'text-rose-600' : 'text-slate-700 dark:text-slate-200' }} mt-1">{{ number_format($summary['today_delete']) }} <span class="text-xs font-medium text-slate-400">ครั้ง</span></h4>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-rose-50 dark:bg-rose-900/30 text-rose-500 flex items-center justify-center text-lg sm:text-xl shrink-0">
                <i class="fa-solid fa-trash-can"></i>
            </div>
        </a>

        <div class="bg-white dark:bg-slate-800 p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <span class="text-[11px] sm:text-xs font-bold text-sky-600 dark:text-sky-400 block truncate">รายการที่พบตามเงื่อนไข</span>
                <h4 class="text-2xl sm:text-3xl font-black text-sky-600 dark:text-sky-400 mt-1">{{ number_format($summary['filtered_total']) }} <span class="text-xs font-medium text-slate-400">รายการ</span></h4>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-sky-50 dark:bg-sky-900/30 text-sky-600 flex items-center justify-center text-lg sm:text-xl shrink-0">
                <i class="fa-solid fa-list-check"></i>
            </div>
        </div>
    </div>

    <!-- 🛠️ 2. แถบค้นหาและตัวกรอง 14 โมดูล + ช่วงวันที่ -->
    <div class="bg-white dark:bg-slate-800 p-4 md:p-5 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700">
        <form action="{{ route('backend.system_logs.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2.5 items-center">
            <!-- ช่องค้นหา -->
            <div class="sm:col-span-2 lg:col-span-3 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="w-full pl-9 pr-3 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 focus:bg-white text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500"
                       placeholder="ค้นหาชื่อผู้ใช้, เหตุการณ์, IP, สรุป...">
            </div>

            <!-- กรองโมดูล (14 ระบบ) -->
            <div class="lg:col-span-3">
                <select name="module" onchange="this.form.submit()" class="w-full rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-200 px-3 py-2.5 outline-none focus:border-indigo-500 cursor-pointer">
                    <option value="">ทุกโมดูลในระบบ (14 โมดูล)</option>
                    @foreach($moduleOptions as $mClass => $mLabel)
                        <option value="{{ $mClass }}" {{ request('module') === $mClass ? 'selected' : '' }}>{{ $mLabel }}</option>
                    @endforeach
                </select>
            </div>

            <!-- กรองประเภทการกระทำ -->
            <div class="lg:col-span-2">
                <select name="action_group" onchange="this.form.submit()" class="w-full rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-200 px-3 py-2.5 outline-none focus:border-indigo-500 cursor-pointer">
                    <option value="">ทุกการกระทำ</option>
                    @foreach($actionGroupOptions as $aKey => $aLabel)
                        <option value="{{ $aKey }}" {{ request('action_group') === $aKey ? 'selected' : '' }}>{{ $aLabel }}</option>
                    @endforeach
                </select>
            </div>

            <!-- ช่วงวันที่ -->
            <div class="sm:col-span-2 lg:col-span-2 grid grid-cols-2 gap-1.5">
                <input type="date" name="date_from" value="{{ request('date_from') }}" title="ตั้งแต่วันที่"
                       class="w-full rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-xs font-bold text-slate-700 dark:text-slate-200 px-2.5 py-2.5 outline-none focus:border-indigo-500">
                <input type="date" name="date_to" value="{{ request('date_to') }}" title="ถึงวันที่"
                       class="w-full rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-xs font-bold text-slate-700 dark:text-slate-200 px-2.5 py-2.5 outline-none focus:border-indigo-500">
            </div>

            <!-- ปุ่มค้นหา + ล้างตัวกรอง -->
            <div class="sm:col-span-2 lg:col-span-2 flex items-center gap-1.5">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <button type="submit" class="flex-1 py-2.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-xs sm:text-sm font-bold shadow-sm transition-colors">
                    <i class="fa-solid fa-filter mr-1"></i> กรอง
                </button>
                @if($hasActiveFilter)
                    <a href="{{ route('backend.system_logs.index') }}" class="py-2.5 px-3 bg-slate-100 dark:bg-slate-700 hover:bg-rose-50 text-slate-600 dark:text-slate-200 hover:text-rose-600 rounded-2xl text-xs sm:text-sm font-bold transition-colors" title="ล้างตัวกรองทั้งหมด">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- 📋 3. รายการ Audit Log (Responsive Mobile Cards + Desktop Table) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-black text-slate-800 dark:text-white text-sm sm:text-base flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-indigo-500"></i> บันทึกเหตุการณ์ระบบย้อนหลัง
            </h3>
            <span class="text-xs font-semibold text-slate-400">
                หน้าที่ {{ $logs->currentPage() }} / {{ max(1, $logs->lastPage()) }}
            </span>
        </div>

        <!-- 📱 โหมดมือถือ: แสดงเป็นการ์ดอ่านง่ายเต็มจอ -->
        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($logs as $log)
                @php
                    $actionData  = $actionDict[$log->action] ?? ['text' => $log->action, 'color' => 'bg-slate-100 text-slate-600 border-slate-200', 'icon' => 'fa-bolt'];
                    $modelShort  = $log->loggable_type ? class_basename($log->loggable_type) : null;
                    $modBadge    = $modelShort ? ($moduleBadges[$modelShort] ?? ['label' => $modelShort, 'cls' => 'bg-slate-100 text-slate-600 border-slate-200']) : ['label' => 'ระบบ/HosXP', 'cls' => 'bg-slate-100 text-slate-600 border-slate-200'];
                    $payloadArr  = is_array($log->payload) ? $log->payload : [];
                    $summaryText = $payloadArr['summary'] ?? null;

                    if (!$summaryText && $modelShort === 'Patient' && $log->loggable) {
                        $summaryText = trim(($log->loggable->rank ?? '') . ($log->loggable->first_name ?? '') . ' ' . ($log->loggable->last_name ?? ''));
                    } elseif (!$summaryText && $modelShort) {
                        $summaryText = "{$modBadge['label']} #{$log->loggable_id}";
                    }

                    $actorName = $log->user ? trim(($log->user->rank ?? '') . $log->user->name) : 'ระบบ (System)';
                    $timeThai  = $log->created_at->addYears(543)->format('d/m/Y H:i:s') . ' น.';
                    $modalData = [
                        'time'       => $timeThai,
                        'user'       => $actorName,
                        'username'   => $log->user->username ?? '-',
                        'action'     => $actionData['text'],
                        'action_raw' => $log->action,
                        'module'     => $payloadArr['module'] ?? $modBadge['label'],
                        'target_id'  => $log->loggable_id,
                        'summary'    => $summaryText ?: '-',
                        'payload'    => $payloadArr,
                        'patient_url'=> (!empty($payloadArr['patient_id']) && \Illuminate\Support\Facades\Route::has('backend.patients.show'))
                                        ? route('backend.patients.show', $payloadArr['patient_id'])
                                        : ($modelShort === 'Patient' && $log->loggable && \Illuminate\Support\Facades\Route::has('backend.patients.show') ? route('backend.patients.show', $log->loggable_id) : null),
                    ];
                @endphp

                <div class="p-4 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg border text-[11px] font-bold {{ $actionData['color'] }}">
                                <i class="fa-solid {{ $actionData['icon'] }} text-[10px]"></i> {{ $actionData['text'] }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg border text-[10px] font-bold {{ $modBadge['cls'] }}">
                                {{ $modBadge['label'] }}
                            </span>
                        </div>
                        <span class="text-[11px] font-mono text-slate-400 shrink-0">{{ $log->created_at->format('H:i') }} น.</span>
                    </div>

                    <div class="font-bold text-slate-800 dark:text-white text-sm leading-snug">
                        {{ $summaryText ?: '-' }}
                    </div>

                    <div class="flex items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400 pt-1">
                        <div class="min-w-0 truncate">
                            <i class="fa-solid fa-user-shield text-slate-400 mr-1"></i>
                            <span class="font-bold text-slate-700 dark:text-slate-300">{{ $actorName }}</span>
                            <span class="mx-1">•</span>
                            <span>{{ $log->created_at->addYears(543)->format('d/m/y') }}</span>
                        </div>

                        @if(!empty($payloadArr))
                            <button type="button"
                                    onclick="showAuditDetailModal(this)"
                                    data-log="{{ json_encode($modalData, JSON_UNESCAPED_UNICODE) }}"
                                    class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white font-bold text-xs inline-flex items-center gap-1 transition-colors shrink-0">
                                <i class="fa-solid fa-eye"></i> รายละเอียด
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-6 py-14 text-center text-slate-400">
                    <i class="fa-solid fa-clipboard-list text-4xl mb-3 block opacity-30"></i>
                    <p class="font-bold text-slate-600 dark:text-slate-300 text-sm">ไม่พบประวัติการใช้งานตามเงื่อนไขที่เลือก</p>
                </div>
            @endforelse
        </div>

        <!-- 💻 โหมดคอมพิวเตอร์: ตารางมาตรฐาน ล็อกสัดส่วนอ่านง่าย -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[920px] text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="text-xs text-slate-500 dark:text-slate-400 uppercase bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700 whitespace-nowrap">
                    <tr>
                        <th class="px-5 py-3.5 font-bold w-[135px]">วันเวลา</th>
                        <th class="px-5 py-3.5 font-bold w-[190px]">ผู้ใช้งาน</th>
                        <th class="px-5 py-3.5 font-bold w-[170px]">เหตุการณ์ (Action)</th>
                        <th class="px-5 py-3.5 font-bold">โมดูล &amp; สรุปรายการ (Target)</th>
                        <th class="px-5 py-3.5 font-bold text-center w-[120px]">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($logs as $log)
                        @php
                            $actionData  = $actionDict[$log->action] ?? ['text' => $log->action, 'color' => 'bg-slate-100 text-slate-600 border-slate-200', 'icon' => 'fa-bolt'];
                            $modelShort  = $log->loggable_type ? class_basename($log->loggable_type) : null;
                            $modBadge    = $modelShort ? ($moduleBadges[$modelShort] ?? ['label' => $modelShort, 'cls' => 'bg-slate-100 text-slate-600 border-slate-200']) : ['label' => 'ระบบ/HosXP', 'cls' => 'bg-slate-100 text-slate-600 border-slate-200'];
                            $payloadArr  = is_array($log->payload) ? $log->payload : [];
                            $summaryText = $payloadArr['summary'] ?? null;

                            if (!$summaryText && $modelShort === 'Patient' && $log->loggable) {
                                $summaryText = trim(($log->loggable->rank ?? '') . ($log->loggable->first_name ?? '') . ' ' . ($log->loggable->last_name ?? ''));
                            } elseif (!$summaryText && $modelShort) {
                                $summaryText = "{$modBadge['label']} #{$log->loggable_id}";
                            }

                            $changedCount = ($log->action === 'updated' && !empty($payloadArr['new']) && is_array($payloadArr['new']))
                                ? count($payloadArr['new'])
                                : 0;

                            $actorName = $log->user ? trim(($log->user->rank ?? '') . $log->user->name) : 'ระบบ (System)';
                            $timeThai  = $log->created_at->addYears(543)->format('d/m/Y H:i:s') . ' น.';
                            $modalData = [
                                'time'       => $timeThai,
                                'user'       => $actorName,
                                'username'   => $log->user->username ?? '-',
                                'action'     => $actionData['text'],
                                'action_raw' => $log->action,
                                'module'     => $payloadArr['module'] ?? $modBadge['label'],
                                'target_id'  => $log->loggable_id,
                                'summary'    => $summaryText ?: '-',
                                'payload'    => $payloadArr,
                                'patient_url'=> (!empty($payloadArr['patient_id']) && \Illuminate\Support\Facades\Route::has('backend.patients.show'))
                                                ? route('backend.patients.show', $payloadArr['patient_id'])
                                                : ($modelShort === 'Patient' && $log->loggable && \Illuminate\Support\Facades\Route::has('backend.patients.show') ? route('backend.patients.show', $log->loggable_id) : null),
                            ];
                        @endphp

                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40 transition-colors">
                            <!-- 1. วันเวลา -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="font-bold text-slate-800 dark:text-white">{{ $log->created_at->addYears(543)->format('d/m/Y') }}</div>
                                <div class="text-xs text-slate-400 font-mono mt-0.5">{{ $log->created_at->format('H:i:s') }} น.</div>
                            </td>

                            <!-- 2. ผู้ใช้งาน -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if($log->user)
                                    <div class="font-bold text-slate-800 dark:text-slate-100">{{ $actorName }}</div>
                                    <div class="text-xs text-slate-400">{{ $log->user->username }}
                                        @if(!empty($payloadArr['ip']))
                                            <span class="font-mono text-[10px] text-slate-400">• {{ $payloadArr['ip'] }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">ระบบ (System)</span>
                                @endif
                            </td>

                            <!-- 3. เหตุการณ์ -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border text-xs font-bold {{ $actionData['color'] }}">
                                    <i class="fa-solid {{ $actionData['icon'] }} opacity-80"></i> {{ $actionData['text'] }}
                                </span>
                            </td>

                            <!-- 4. โมดูล & เป้าหมาย -->
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                    <span class="px-2 py-0.5 rounded-md border text-[11px] font-bold {{ $modBadge['cls'] }}">
                                        {{ $modBadge['label'] }}
                                    </span>
                                    @if($log->loggable_id)
                                        <span class="text-[11px] font-mono text-slate-400">#{{ $log->loggable_id }}</span>
                                    @endif
                                    @if($changedCount > 0)
                                        <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">
                                            แก้ {{ $changedCount }} ฟิลด์
                                        </span>
                                    @endif
                                </div>
                                <div class="font-semibold text-slate-800 dark:text-slate-200 text-sm">
                                    {{ $summaryText ?: '-' }}
                                </div>
                            </td>

                            <!-- 5. ปุ่มดูรายละเอียด -->
                            <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                @if(!empty($payloadArr))
                                    <button type="button"
                                            onclick="showAuditDetailModal(this)"
                                            data-log="{{ json_encode($modalData, JSON_UNESCAPED_UNICODE) }}"
                                            class="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-600 hover:text-white text-xs font-bold inline-flex items-center gap-1.5 transition-colors shadow-sm"
                                            title="ดูการเปลี่ยนแปลง (ค่าเดิม → ค่าใหม่)">
                                        <i class="fa-solid fa-magnifying-glass-chart"></i> ดูข้อมูล
                                    </button>
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center text-slate-400">
                                <i class="fa-solid fa-clipboard-list text-4xl mb-3 text-slate-300 block"></i>
                                <p class="font-bold text-slate-600 dark:text-slate-300">ยังไม่มีประวัติการใช้งานตามเงื่อนไขที่เลือก</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- แถบเปลี่ยนหน้า + ตัวเลือกจำนวนรายการต่อหน้า -->
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold">
                <span>แสดงหน้าละ:</span>
                @foreach([20, 30, 50, 100] as $pSize)
                    <a href="{{ request()->fullUrlWithQuery(['per_page' => $pSize, 'page' => 1]) }}"
                       class="px-2.5 py-1 rounded-lg font-bold transition-colors {{ $perPage == $pSize ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-indigo-50' }}">
                        {{ $pSize }}
                    </a>
                @endforeach
                <span>รายการ</span>
            </div>

            @if($logs->hasPages())
                <div class="w-full sm:w-auto">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // พจนานุกรมแปลชื่อคอลัมน์เป็นภาษาไทยให้อ่านเข้าใจง่ายในหน้าต่าง Diff
    const auditFieldLabels = {
        hn: 'รหัส HN',
        cid: 'เลขบัตร ปชช.',
        rank: 'ยศ/คำนำหน้า',
        first_name: 'ชื่อ',
        last_name: 'นามสกุล',
        dob: 'วันเกิด',
        phone: 'เบอร์โทรศัพท์',
        intake_batch: 'ผลัดทหาร',
        military_unit_id: 'รหัสหน่วยฝึก',
        company: 'กองร้อย/หน่วยฝึก',
        pttype_name: 'สิทธิการรักษา',
        pttype_code: 'รหัสสิทธิ',
        duty_status: 'สถานะกำลังพล',
        duty_status_note: 'หมายเหตุสถานะ',
        chronic_disease_status: 'สถานะโรคประจำตัว',
        chronic_disease_detail: 'รายละเอียดโรคประจำตัว',
        total_score: 'คะแนน MEWS',
        risk_level: 'ระดับความเสี่ยง',
        status: 'สถานะ',
        temperature: 'อุณหภูมิ (°C)',
        heart_rate: 'ชีพจร (/นาที)',
        respiratory_rate: 'หายใจ (/นาที)',
        systolic_bp: 'ความดันตัวบน (SBP)',
        diastolic_bp: 'ความดันตัวล่าง (DBP)',
        o2sat: 'ออกซิเจน (%)',
        consciousness: 'ระดับความรู้สึกตัว',
        symptoms: 'อาการฉุกเฉิน',
        symptoms_note: 'หมายเหตุอาการ',
        discharge_type: 'ประเภทการจำหน่าย',
        discharge_details: 'รายละเอียดจำหน่าย',
        cancel_reason: 'เหตุผลยกเลิก',
        location_type: 'สถานที่แยกนอน',
        bed_or_zone: 'โซน/เตียง',
        reason_category: 'หมวดหมู่สาเหตุ',
        diagnosis_text: 'การวินิจฉัย (Dx.)',
        care_instruction: 'คำแนะนำการดูแล',
        admitted_at: 'วัน-เวลาเริ่มพัก',
        discharged_at: 'วัน-เวลากลับโรงนอน',
        discharge_note: 'หมายเหตุกลับโรงนอน',
        drug_name: 'ชื่อยา',
        dosage: 'ขนาด/ปริมาณยา',
        meal_timing: 'มื้ออาหาร/วิธีใช้',
        care_group: 'กลุ่มดูแลการใช้ยา',
        take_morning: 'มื้อเช้า',
        take_noon: 'มื้อเที่ยง',
        take_evening: 'มื้อเย็น',
        take_bedtime: 'มื้อก่อนนอน',
        take_prn: 'เวลามีอาการ (PRN)',
        custom_slot_text: 'ระบุเวลาใช้ยาเอง',
        instruction_note: 'ข้อควรระวังยา',
        log_date: 'วันที่ให้ยา',
        time_slot: 'รอบเวลาให้ยา',
        hospital_type: 'ประเภท รพ.',
        hospital_name: 'ชื่อโรงพยาบาล',
        appointment_date: 'วันที่นัดพบแพทย์',
        appointment_time: 'เวลานัด',
        clinic_name: 'คลินิก/แผนก',
        doctor_name: 'แพทย์ผู้นัด',
        app_cause: 'สาเหตุการนัด',
        note: 'หมายเหตุ',
        category: 'หมวดหมู่โรค',
        severity: 'ระดับความรุนแรง',
        onset_date: 'วันที่เริ่มป่วย',
        recovered_date: 'วันที่หายป่วย',
        color_group: 'กลุ่มสีคัดกรอง',
        process_status: 'สถานะเอกสารนำปลด',
        documents_matrix: 'รายการเอกสารนำปลด',
        name: 'ชื่อ-สกุล / ชื่อรายการ',
        username: 'ชื่อบัญชีผู้ใช้',
        email: 'อีเมล',
        roles: 'บทบาทสิทธิ์',
        is_active: 'สถานะเปิดใช้งาน',
        is_default: 'ค่าเริ่มต้น',
        rules: 'เกณฑ์คะแนน'
    };

    function escapeLogHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatAuditValue(val) {
        if (val === null || val === undefined || val === '') return '<span class="text-slate-400 italic">(ว่าง)</span>';
        if (typeof val === 'boolean') {
            return val
                ? '<span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 font-bold text-[11px]">ใช่ / เปิด</span>'
                : '<span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-bold text-[11px]">ไม่ใช่ / ปิด</span>';
        }
        if (typeof val === 'object') {
            return `<code class="text-[11px] font-mono break-all">${escapeLogHtml(JSON.stringify(val))}</code>`;
        }
        return `<span class="break-words">${escapeLogHtml(val)}</span>`;
    }

    function showAuditDetailModal(button) {
        let data = {};
        try {
            data = JSON.parse(button.getAttribute('data-log') || '{}');
        } catch (e) {
            return;
        }

        const payload = data.payload || {};
        const oldVals = (payload.old && typeof payload.old === 'object') ? payload.old : null;
        const newVals = (payload.new && typeof payload.new === 'object') ? payload.new : null;

        let bodyHtml = '';

        // กรณี 1: แก้ไขข้อมูล (มีทั้งค่าเดิมและค่าใหม่ -> แสดงตาราง Diff เทียบกันชัดเจน)
        if (oldVals && newVals && Object.keys(newVals).length > 0) {
            let rows = '';
            Object.keys(newVals).forEach(key => {
                const label = auditFieldLabels[key] || key;
                rows += `
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-2 px-3 font-bold text-slate-700 bg-slate-50/70 align-top w-1/3">${escapeLogHtml(label)}</td>
                        <td class="py-2 px-3 text-rose-700 bg-rose-50/40 align-top line-through decoration-rose-400/70 w-1/3">${formatAuditValue(oldVals[key])}</td>
                        <td class="py-2 px-3 text-emerald-800 bg-emerald-50/50 font-semibold align-top w-1/3">${formatAuditValue(newVals[key])}</td>
                    </tr>
                `;
            });
            bodyHtml = `
                <div class="rounded-2xl border border-slate-200 overflow-hidden">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-100 text-slate-600 font-black">
                            <tr>
                                <th class="py-2 px-3">ข้อมูลที่เปลี่ยน</th>
                                <th class="py-2 px-3 text-rose-700">ค่าเดิม</th>
                                <th class="py-2 px-3 text-emerald-700">ค่าใหม่</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        }
        // กรณี 2: เพิ่มข้อมูลใหม่ หรือ ลบข้อมูล
        else if (newVals || oldVals) {
            const targetObj = newVals || oldVals;
            const isDel = !newVals && !!oldVals;
            let rows = '';
            Object.entries(targetObj).forEach(([key, val]) => {
                if (val === null || val === '') return;
                const label = auditFieldLabels[key] || key;
                rows += `
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-1.5 px-3 font-bold text-slate-600 bg-slate-50/70 align-top w-2/5">${escapeLogHtml(label)}</td>
                        <td class="py-1.5 px-3 ${isDel ? 'text-rose-700' : 'text-slate-800'} align-top">${formatAuditValue(val)}</td>
                    </tr>
                `;
            });
            bodyHtml = `
                <div class="rounded-2xl border ${isDel ? 'border-rose-200' : 'border-emerald-200'} overflow-hidden">
                    <div class="px-3 py-1.5 text-[11px] font-black ${isDel ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'}">
                        ${isDel ? 'ข้อมูลเดิมก่อนถูกลบออกจากระบบ' : 'รายละเอียดข้อมูลที่บันทึกใหม่'}
                    </div>
                    <table class="w-full text-xs text-left"><tbody>${rows || '<tr><td class="p-3 text-slate-400 text-center">ไม่มีรายละเอียดเพิ่มเติม</td></tr>'}</tbody></table>
                </div>
            `;
        }
        // กรณี 3: Log รุ่นเดิมที่เป็น key-value ชั้นเดียว
        else {
            const skipMeta = ['module', 'summary', 'ip', 'url', 'patient_id'];
            const entries = Object.entries(payload).filter(([k]) => !skipMeta.includes(k));
            if (entries.length > 0) {
                let rows = entries.map(([k, v]) => `
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-1.5 px-3 font-bold text-slate-600 bg-slate-50/70 align-top w-2/5">${escapeLogHtml(auditFieldLabels[k] || k)}</td>
                        <td class="py-1.5 px-3 text-slate-800 align-top">${formatAuditValue(v)}</td>
                    </tr>
                `).join('');
                bodyHtml = `<div class="rounded-2xl border border-slate-200 overflow-hidden"><table class="w-full text-xs text-left"><tbody>${rows}</tbody></table></div>`;
            }
        }

        const rawJsonEscaped = escapeLogHtml(JSON.stringify(payload, null, 2));
        const patientBtnHtml = data.patient_url
            ? `<a href="${escapeLogHtml(data.patient_url)}" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 rounded-xl text-xs font-bold inline-flex items-center gap-1 transition-colors"><i class="fa-solid fa-address-card"></i> เปิดแฟ้มพลทหาร</a>`
            : '';

        Swal.fire({
            title: `<div class="text-base sm:text-lg font-black text-slate-800 flex items-center justify-center gap-2"><i class="fa-solid fa-shield-halved text-indigo-500"></i> รายละเอียดเหตุการณ์ (Audit Detail)</div>`,
            html: `
                <div class="text-left space-y-3 mt-2 max-h-[65vh] overflow-y-auto pr-1">
                    <!-- ข้อมูลหัวเรื่อง -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-black text-slate-900 text-sm">${escapeLogHtml(data.summary)}</span>
                            ${patientBtnHtml}
                        </div>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-slate-500 pt-1">
                            <span><i class="fa-solid fa-layer-group text-indigo-400 mr-1"></i>โมดูล: <b class="text-slate-700">${escapeLogHtml(data.module)}</b></span>
                            <span><i class="fa-solid fa-bolt text-amber-500 mr-1"></i>เหตุการณ์: <b class="text-slate-700">${escapeLogHtml(data.action)}</b></span>
                            <span><i class="fa-solid fa-user text-emerald-500 mr-1"></i>ผู้ทำ: <b class="text-slate-700">${escapeLogHtml(data.user)}</b></span>
                            <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i>${escapeLogHtml(data.time)}</span>
                            ${payload.ip ? `<span><i class="fa-solid fa-network-wired text-sky-500 mr-1"></i>IP: <b class="font-mono text-slate-700">${escapeLogHtml(payload.ip)}</b></span>` : ''}
                        </div>
                    </div>

                    <!-- ตารางเปรียบเทียบข้อมูล -->
                    ${bodyHtml}

                    <!-- ปุ่มเปิด/ซ่อน Raw JSON สำหรับทีม IT -->
                    <div class="pt-1">
                        <button type="button" onclick="document.getElementById('audit-raw-json-box').classList.toggle('hidden')"
                                class="text-[11px] font-bold text-slate-500 hover:text-indigo-600 inline-flex items-center gap-1">
                            <i class="fa-solid fa-code"></i> สลับดูข้อมูลดิบ (Raw JSON)
                        </button>
                        <pre id="audit-raw-json-box" class="hidden mt-2 bg-slate-900 text-emerald-400 p-3.5 rounded-xl text-[11px] overflow-x-auto font-mono whitespace-pre-wrap border border-slate-800">${rawJsonEscaped}</pre>
                    </div>
                </div>
            `,
            width: '44rem',
            confirmButtonText: 'ปิดหน้าต่าง',
            confirmButtonColor: '#4f46e5',
            heightAuto: false,
            scrollbarPadding: false,
            customClass: { popup: 'rounded-3xl p-5 sm:p-6' }
        });
    }
</script>
@endsection