<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $authUser = auth()->user();
        $rawMetaRoles = $authUser ? ($authUser->roles ?? $authUser->role ?? []) : [];
        $cleanMetaRoles = is_string($rawMetaRoles) ? (json_decode($rawMetaRoles, true) ?: [$rawMetaRoles]) : (array)$rawMetaRoles;
    @endphp
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="user-unit" content="{{ $authUser->military_unit_id ?? '' }}">
    <meta name="user-roles" content="{{ json_encode(array_values($cleanMetaRoles)) }}">
    <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') ?: env('VAPID_PUBLIC_KEY') }}">

    <title>@yield('title', 'KSVR Hospital System')</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1.0">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=1.0">
    <link rel="manifest" href="{{ asset('manifest.json') }}?v=3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <meta name="theme-color" content="#10b981">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome สำหรับ Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Prompt', sans-serif; }

        /* 🌟 ล็อกความสูง ป้องกัน SweetAlert2 ทำลายโครงสร้าง h-screen ของ Tailwind */
        body.swal2-height-auto { height: 100vh !important; }
        html.swal2-height-auto { height: 100vh !important; }
        .swal2-container { z-index: 99999 !important; }
        body.swal2-shown aside#sidebar {
            z-index: 0 !important;
            pointer-events: none;
        }

        /* 🌟 ปรับสี SweetAlert ให้เข้ากับ Dark Mode */
        html.dark body, html.dark main, html.dark aside#sidebar {
            background-color: #0f172a !important;
            color: #f1f5f9 !important;
        }
        html.dark .bg-white {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        html.dark .text-slate-800, html.dark .text-slate-700 {
            color: #f8fafc !important;
        }

        /* 🌟 ปรับแต่ง Scrollbar ให้ดูหรูหรา */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        html.dark ::-webkit-scrollbar-thumb { background: #475569; }
        html.dark ::-webkit-scrollbar-thumb:hover { background: #64748b; }
    </style>

    <script>
        // 1. จัดการ Dark Mode (ทำงานก่อนหน้าเว็บโหลดเสร็จเพื่อกันจอกระพริบ)
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // 2. จัดการขนาดตัวอักษร
        const savedTextSize = localStorage.getItem('textSize') || '16px';
        document.documentElement.style.fontSize = savedTextSize;
    </script>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-sans antialiased transition-colors duration-300">
    <!-- 🌟 การ์ดลอยแนะนำติดตั้งแอป (Floating PWA Install Card — ใช้ fixed เพื่อไม่ให้ดัน Layout หลัก) -->
    <div id="pwa-install-banner"
         class="hidden fixed top-3 left-3 right-3 sm:top-auto sm:bottom-5 sm:left-5 sm:right-auto sm:w-[370px] z-[85] bg-white/95 dark:bg-slate-800/95 backdrop-blur-md rounded-2xl shadow-[0_12px_40px_-8px_rgba(15,23,42,0.28)] border border-slate-200/90 dark:border-slate-700 p-3.5 transition-all duration-300">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-slate-900 border border-emerald-200/70 dark:border-slate-700 shadow-sm flex items-center justify-center p-1.5 shrink-0">
                <img src="{{ asset('images/logo5.png') }}" alt="KSVR Logo" class="w-full h-full object-contain">
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5">
                    <h4 class="text-sm font-black text-slate-800 dark:text-white leading-tight truncate">แอป KSVR MEWS</h4>
                    <span class="px-1.5 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 text-[9px] font-black uppercase tracking-wide shrink-0">App</span>
                </div>
                <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-0.5 truncate">เปิดเต็มจอ ไร้แถบ URL ใช้งานไวขึ้น</p>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" onclick="installPwaApp()"
                        class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-black shadow-sm transition-all whitespace-nowrap">
                    ติดตั้ง
                </button>
                <button type="button" onclick="dismissPwaBanner()" title="ไว้ทีหลัง"
                        class="w-7 h-7 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- 🌟 แถบเมนูด้านข้าง (Sidebar) -->
    <aside id="sidebar" class="w-64 bg-slate-800 text-white flex flex-col fixed lg:relative z-40 h-screen transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out shadow-xl shrink-0">
        <!-- โลโก้และชื่อระบบ -->
        <div class="h-20 flex items-center border-b border-slate-700/80 shrink-0 px-4 transition-all">
            <a href="{{ url('/') }}" class="flex items-center gap-3 w-full group">
                <div class="w-[72px] h-12 bg-white rounded-xl shadow-sm flex items-center justify-center p-1.5 shrink-0 group-hover:shadow-emerald-500/20 group-hover:ring-2 group-hover:ring-emerald-400 transition-all duration-300 group-hover:-translate-y-0.5">
                    <img src="{{ asset('images/logo5.png') }}" alt="KSVR Logo" class="h-full w-full object-contain">
                </div>
                <div class="flex flex-col overflow-hidden">
                    <h1 class="text-base font-black tracking-wider text-white truncate transition-colors">
                        KSVR <span class="text-emerald-400 group-hover:text-emerald-300 transition-colors">MEWS</span>
                    </h1>
                    <span class="text-[10px] font-medium text-slate-400 truncate mt-0.5 group-hover:text-slate-300 transition-colors">
                        ระบบประเมินสัญญาณชีพ
                    </span>
                </div>
            </a>
        </div>

        <!-- รายการเมนู -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4">
            @php
                $user = auth()->user();
                $userRoles = is_string($user->roles) ? (json_decode($user->roles, true) ?: []) : (array)($user->roles ?? $user->role);

                $isAdmin        = in_array('admin', $userRoles, true);
                $isExecutive    = $isAdmin || in_array('executive', $userRoles, true);
                $isER           = $isAdmin || in_array('er_nurse', $userRoles, true);
                $isOPD          = $isAdmin || in_array('opd_nurse', $userRoles, true);
                $isMedic        = $isAdmin || in_array('medic', $userRoles, true);
                $isRegistration = $isAdmin || in_array('registration', $userRoles, true);
                $isMedicalStaff = $isAdmin || in_array('medical_staff', $userRoles, true);
                $isScreening    = $isAdmin || in_array('screening', $userRoles, true);

                // อ่านค่าตั้งค่าระบบจาก Cache (0 DB Query + ครอบ try-catch ป้องกัน 500 Error)
                $sysAppAlertDays = 1;
                $sysPollingSeconds = 30;
                $sysPdpaChronicRoles = ['admin'];
                if (class_exists(\App\Services\SettingService::class)) {
                    try {
                        $sysAppAlertDays     = max(1, min(7, (int) \App\Services\SettingService::get('appointment_alert_days', 1)));
                        $sysPollingSeconds   = max(15, min(300, (int) \App\Services\SettingService::get('polling_interval_seconds', 30)));
                        $sysPdpaChronicRoles = (array) \App\Services\SettingService::get('pdpa_chronic_roles', ['admin']);
                    } catch (\Throwable $e) {
                    }
                }
                $canViewChronicAi = $isAdmin || !empty(array_intersect($userRoles, $sysPdpaChronicRoles));

                // เช็กว่ากำลังเปิดหน้าใดหน้าหนึ่งในหมวด "ข้อมูลทหารใหม่" อยู่หรือไม่
                $isRecruitGroupActive = request()->routeIs(
                    'backend.patients.index',
                    'backend.patients.show',
                    'backend.patients.edit',
                    'backend.patients.create',
                    'backend.patients.import*',
                    'backend.recruit_screenings.*',
                    'backend.morbidity_reports.*',
                    'backend.military_discharges.*',
                    'backend.recruit_appointments.*',
                    'backend.recruit_infirmary.*',
                    'backend.care_chart.*'
                );

                $isOverviewMenu = request()->routeIs('backend.patients.index') && request('view') === 'overview';
                $isListMenu     = request()->routeIs('backend.patients.index', 'backend.patients.show', 'backend.patients.edit', 'backend.patients.create', 'backend.patients.import*') && request('view') !== 'overview';

                // 🌟 1. ดึงรายการนัดพบแพทย์วันนี้ และล่วงหน้าตามค่า appointment_alert_days จากหน้าตั้งค่าระบบ
                $upcomingAppAlerts = collect();
                if (\Illuminate\Support\Facades\Schema::hasTable('recruit_appointments')) {
                    $alertStart = \Carbon\Carbon::today()->format('Y-m-d');
                    $alertEnd   = \Carbon\Carbon::today()->addDays($sysAppAlertDays)->format('Y-m-d');

                    $appAlertQuery = \App\Models\RecruitAppointment::with(['patient.militaryUnit', 'militaryUnit'])
                        ->where('status', 'scheduled')
                        ->whereBetween('appointment_date', [$alertStart, $alertEnd]);

                    if (!$isAdmin && !$isER && !$isOPD && !$isExecutive && !$isRegistration && !$isMedicalStaff && !$isScreening) {
                        $appAlertQuery->where('military_unit_id', $user->military_unit_id);
                        if (!empty($user->intake_batch)) {
                            $appAlertQuery->where('intake_batch', $user->intake_batch);
                        }
                    }

                    $upcomingAppAlerts = $appAlertQuery->orderBy('appointment_date', 'asc')
                        ->orderBy('appointment_time', 'asc')
                        ->get();
                }
                $upcomingAppAlertCount = $upcomingAppAlerts->count();

                // 🌟 2. นับจำนวนพลทหารที่กำลังแยกนอนในห้องพยาบาลหน่วยฝึก / ฝากนอน รพ. ปัจจุบัน
                $activeInfirmaryCount = 0;
                if (\Illuminate\Support\Facades\Schema::hasTable('recruit_infirmary_stays')) {
                    $infCountQuery = \App\Models\RecruitInfirmaryStay::where('status', 'staying')
                        ->whereHas('patient');

                    if (!$isAdmin && !$isER && !$isOPD && !$isExecutive && !$isRegistration && !$isMedicalStaff && !$isScreening) {
                        $infCountQuery->where('military_unit_id', $user->military_unit_id);
                        if (!empty($user->intake_batch)) {
                            $infCountQuery->where('intake_batch', $user->intake_batch);
                        }
                    }
                    $activeInfirmaryCount = (int) $infCountQuery->distinct('patient_id')->count('patient_id');
                }
            @endphp

            <ul class="space-y-1.5 px-3 text-[15px]">
                <!-- 1. แดชบอร์ดหลักของระบบ -->
                <li>
                    <a href="{{ route('backend.dashboard') }}" class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.dashboard') ? 'bg-slate-700/80 border-l-4 border-sky-400 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-chart-line w-6 text-center text-sky-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium">แดชบอร์ด</span>
                    </a>
                </li>

                <!-- 2. ประเมินผู้ป่วย (MEWS) -->
                <li>
                    <a href="{{ route('backend.patients.search') }}" class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.patients.search', 'backend.patients.processSearch', 'backend.mews.*') ? 'bg-slate-700/80 border-l-4 border-emerald-400 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-stethoscope w-6 text-center text-emerald-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium">ประเมินผู้ป่วย (MEWS)</span>
                    </a>
                </li>

                <!-- 🎖️ 3. เมนูหลักแบบยุบรวม: M-CARE System (ระบบข้อมูลทหารใหม่) -->
                <li class="pt-1">
                    <button type="button"
                            onclick="const sub = document.getElementById('recruit-submenu'); const ic = document.getElementById('recruit-submenu-icon'); sub.classList.toggle('hidden'); ic.classList.toggle('rotate-180');"
                            class="w-full group flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-2xl transition-all duration-300 {{ $isRecruitGroupActive ? 'bg-gradient-to-r from-teal-500/20 via-emerald-500/10 to-slate-800 border border-teal-500/40 text-white shadow-lg shadow-teal-950/50' : 'text-slate-300 hover:text-white hover:bg-slate-800/80 border border-transparent' }}">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-teal-400 to-emerald-600 text-slate-950 flex items-center justify-center shadow-md shadow-teal-500/20 group-hover:scale-105 transition-transform duration-300 shrink-0">
                                <i class="fa-solid fa-shield-heart text-white text-base"></i>
                            </div>
                            <div class="text-left min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-black tracking-wider text-teal-300 uppercase">M-CARE</span>
                                    <span class="px-1.5 py-0.2 text-[9px] font-extrabold bg-teal-400/20 text-teal-200 rounded border border-teal-400/30">SYSTEM</span>
                                </div>
                                <div class="text-xs font-bold text-white truncate mt-0.5">
                                    ข้อมูลทหารใหม่
                                </div>
                            </div>
                        </div>
                        <i id="recruit-submenu-icon" class="fa-solid fa-chevron-down text-xs text-teal-300 transition-transform duration-300 shrink-0 {{ $isRecruitGroupActive ? 'rotate-180' : '' }}"></i>
                    </button>

                    <ul id="recruit-submenu" class="{{ $isRecruitGroupActive ? '' : 'hidden' }} mt-2 ml-2 pl-2.5 border-l-2 border-teal-500/40 space-y-1 text-[13.5px]">
                        <li>
                            <a href="{{ route('backend.patients.index', ['view' => 'overview']) }}"
                               class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl transition-all {{ $isOverviewMenu ? 'bg-teal-500/20 text-teal-300 font-bold border border-teal-500/30 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-700/40 border border-transparent' }}">
                                <i class="fa-solid fa-chart-column w-5 text-center text-teal-400 shrink-0"></i>
                                <span class="truncate">ภาพรวมข้อมูลพลทหาร</span>
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('backend.patients.index', ['view' => 'list']) }}"
                               class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl transition-all {{ $isListMenu ? 'bg-indigo-500/20 text-indigo-300 font-bold border border-indigo-500/30 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-700/40 border border-transparent' }}">
                                <i class="fa-solid fa-address-book w-5 text-center text-indigo-400 shrink-0"></i>
                                <span class="truncate">ทะเบียนรายชื่อพลทหาร</span>
                            </a>
                        </li>

                        @if($isER || $isOPD || $isExecutive || $isRegistration || $isMedicalStaff)
                            <li>
                                <a href="{{ route('backend.recruit_screenings.dashboard') }}"
                                   class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl transition-all {{ request()->routeIs('backend.recruit_screenings.*') ? 'bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-700/40 border border-transparent' }}">
                                    <i class="fa-solid fa-chart-pie w-5 text-center text-amber-400 shrink-0"></i>
                                    <span class="truncate">คัดกรองทหารใหม่</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ route('backend.morbidity_reports.index') }}"
                                   class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl transition-all {{ request()->routeIs('backend.morbidity_reports.*') ? 'bg-rose-500/20 text-rose-300 font-bold border border-rose-500/30 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-700/40 border border-transparent' }}">
                                    <i class="fa-solid fa-file-waveform w-5 text-center text-rose-400 shrink-0"></i>
                                    <span class="truncate">รายงานเจ็บป่วยประจำวัน</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ route('backend.military_discharges.index') }}"
                                   class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl transition-all {{ request()->routeIs('backend.military_discharges.*') ? 'bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-700/40 border border-transparent' }}">
                                    <i class="fa-solid fa-folder-open w-5 text-center text-emerald-400 shrink-0"></i>
                                    <span class="truncate">เอกสารนำปลดทหาร</span>
                                </a>
                            </li>
                        @endif

                        <li>
                            <a href="{{ route('backend.recruit_appointments.index') }}"
                               class="group flex items-center justify-between gap-1.5 px-2.5 py-2 rounded-xl transition-all duration-200 {{ request()->routeIs('backend.recruit_appointments.*') ? 'bg-sky-500/20 text-sky-300 font-bold border border-sky-500/30 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-700/40 border border-transparent' }}">
                                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                    <i class="fa-solid fa-calendar-check w-5 text-center text-sky-400 shrink-0"></i>
                                    <span class="truncate">นัดพบแพทย์</span>
                                </div>

                                @if(isset($upcomingAppAlertCount) && $upcomingAppAlertCount > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40 shrink-0">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                                        <span>{{ $upcomingAppAlertCount }} นัด</span>
                                    </span>
                                @endif
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('backend.recruit_infirmary.index') }}"
                               class="group flex items-center justify-between gap-1.5 px-2.5 py-2 rounded-xl transition-all duration-200 {{ request()->routeIs('backend.recruit_infirmary.*', 'backend.care_chart.*') ? 'bg-teal-500/20 text-teal-300 font-bold border border-teal-500/30 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-700/40 border border-transparent' }}">
                                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                    <i class="fa-solid fa-bed-pulse w-5 text-center text-teal-400 shrink-0"></i>
                                    <span class="truncate" title="ห้องพยาบาล / แยกนอน">ห้องพยาบาล / แยกนอน</span>
                                </div>

                                @if(isset($activeInfirmaryCount) && $activeInfirmaryCount > 0)
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-black bg-teal-400/20 text-teal-200 border border-teal-400/40 shrink-0" title="กำลังแยกนอนปัจจุบัน {{ $activeInfirmaryCount }} นาย">
                                        {{ $activeInfirmaryCount }}
                                    </span>
                                @endif
                            </a>
                        </li>
                    </ul>
                </li>

                @if($isAdmin)
                <li class="pt-5 pb-2">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest px-4">การจัดการระบบ</span>
                </li>

                <li>
                    <a href="{{ route('backend.users.index') }}" class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.users.*') ? 'bg-slate-700/80 border-l-4 border-indigo-400 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-user-nurse w-6 text-center text-indigo-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium">จัดการผู้ใช้งาน</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('backend.military-units.index') }}" class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.military-units.*') ? 'bg-slate-700/80 border-l-4 border-orange-400 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-tents w-6 text-center text-orange-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium">จัดการหน่วยทหาร</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('backend.system_logs.index') }}" class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.system_logs.*') ? 'bg-slate-700/80 border-l-4 border-slate-300 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-list-check w-6 text-center text-slate-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium">ประวัติการใช้งาน (Log)</span>
                    </a>
                </li>

                <li class="pt-5 pb-2">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest px-4">ตั้งค่าระบบ</span>
                </li>

                <li>
                    <a href="{{ route('backend.settings.symptoms.index') }}" class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.settings.symptoms.*') ? 'bg-slate-700/80 border-l-4 border-rose-400 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-truck-medical w-6 text-center text-rose-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium">อาการฉุกเฉินเฉพาะโรค</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('backend.settings.mews.index') }}" class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.settings.mews.*') ? 'bg-slate-700/80 border-l-4 border-violet-400 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-calculator w-6 text-center text-violet-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium text-sm">ตั้งค่าเกณฑ์ประเมิน MEWS</span>
                    </a>
                </li>

                <li>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('backend.settings.general.index') ? route('backend.settings.general.index') : url('/settings/general') }}" class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.settings.general.*') ? 'bg-slate-700/80 border-l-4 border-emerald-400 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-sliders w-6 text-center text-emerald-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium">ตั้งค่าระบบทั่วไป</span>
                    </a>
                </li>
                @endif

                <li class="pt-5 pb-2">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest px-4">ช่วยเหลือ &amp; คู่มือ</span>
                </li>

                <li>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('backend.help.index') ? route('backend.help.index') : url('/backend/help') }}"
                       class="group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 {{ request()->routeIs('backend.help.*') || request()->is('backend/help*') ? 'bg-slate-700/80 border-l-4 border-indigo-400 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-slate-700/50 border-l-4 border-transparent' }}">
                        <i class="fa-solid fa-book-open-reader w-6 text-center text-indigo-400 drop-shadow-sm group-hover:scale-110 transition-transform duration-300"></i>
                        <span class="font-medium">คู่มือการใช้งานระบบ</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- ส่วนท้าย Sidebar (โปรไฟล์ผู้ใช้) -->
        <div class="p-4 border-t border-slate-700 mt-auto bg-slate-900/50 shrink-0">
            <div class="flex items-center gap-3">
                <a href="{{ route('backend.profile.edit') }}" class="flex items-center gap-3 p-3 w-full hover:bg-slate-800 rounded-xl transition-colors cursor-pointer border border-transparent hover:border-slate-700">
                    <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold shadow-sm shrink-0">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-sm font-bold text-white truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-400 truncate mt-0.5">{{ auth()->user()->email }}</p>
                    </div>
                </a>
            </div>
        </div>
    </aside>

    <!-- 🌟 พื้นที่เนื้อหาหลัก (Main Content) -->
    <div class="flex-1 flex flex-col relative overflow-hidden h-screen">

        <!-- แถบด้านบน (Topbar) -->
        <header class="h-16 shrink-0 bg-white dark:bg-slate-800 shadow-sm flex items-center justify-between px-4 md:px-6 z-30 transition-colors duration-300 relative">

            <div class="flex items-center gap-3 md:gap-4 min-w-0">
                <button id="sidebar-toggle-btn" class="text-slate-500 hover:text-emerald-500 dark:text-slate-400 dark:hover:text-emerald-400 focus:outline-none z-50 transition-colors shrink-0">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="hidden sm:flex items-center gap-2.5 min-w-0">
                    @if(isset($isRecruitGroupActive) && $isRecruitGroupActive)
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-900 dark:bg-teal-950 text-white border border-teal-500/40 shadow-sm shrink-0">
                            <span class="w-2 h-2 rounded-full bg-teal-400 animate-pulse"></span>
                            <span class="text-xs font-black tracking-wider text-teal-300">M-CARE</span>
                            <span class="text-[10px] font-bold text-slate-300 uppercase">System</span>
                        </div>
                        <span class="text-slate-300 dark:text-slate-600 font-light text-lg">/</span>
                    @endif
                    <h2 class="text-base md:text-lg font-extrabold text-slate-800 dark:text-slate-100 truncate">
                        @yield('page_header', 'ระบบจัดการข้อมูล KSVR')
                    </h2>
                </div>
            </div>

            <div class="flex items-center gap-1.5 md:gap-4 ml-auto">
                <!-- 1. ตั้งค่าฟอนต์ & ธีม -->
                <div class="flex items-center gap-1.5 md:gap-3 pr-2 md:pr-4 border-r border-slate-200 dark:border-slate-700">
                    <div class="relative flex items-center bg-slate-200/70 dark:bg-slate-700 rounded-full p-1 shadow-inner w-[84px] md:w-[96px] h-7 md:h-8">
                        <div class="absolute inset-1 z-0">
                            <div id="font-slider-bg" class="h-full w-[33.33%] bg-white dark:bg-slate-800 rounded-full shadow-sm transition-transform duration-300 ease-in-out"></div>
                        </div>
                        <button onclick="changeTextSize('14px')" id="text-btn-sm" type="button" class="relative z-10 flex-1 flex items-center justify-center text-[10px] md:text-[11px] font-bold text-slate-500 dark:text-slate-400 transition-colors duration-300">A-</button>
                        <button onclick="changeTextSize('16px')" id="text-btn-md" type="button" class="relative z-10 flex-1 flex items-center justify-center text-[12px] md:text-[13px] font-black text-indigo-600 dark:text-indigo-400 transition-colors duration-300">A</button>
                        <button onclick="changeTextSize('18px')" id="text-btn-lg" type="button" class="relative z-10 flex-1 flex items-center justify-center text-[14px] md:text-[15px] font-bold text-slate-500 dark:text-slate-400 transition-colors duration-300">A+</button>
                    </div>

                    <button onclick="toggleDarkMode()" type="button" class="relative inline-flex h-6 md:h-8 w-11 md:w-14 items-center rounded-full bg-slate-200 dark:bg-slate-700 transition-colors duration-300 focus:outline-none shadow-inner shrink-0">
                        <span id="theme-slider" class="inline-flex h-4 md:h-6 w-4 md:w-6 items-center justify-center rounded-full bg-white dark:bg-slate-800 shadow-sm transition-transform duration-300 z-10 translate-x-1 dark:translate-x-6 md:dark:translate-x-7">
                            <i id="theme-icon" class="fa-solid fa-sun text-amber-500 text-[9px] md:text-[11px]"></i>
                        </span>
                    </button>
                </div>

                <!-- 2. ดึงข้อมูลแจ้งเตือน MEWS -->
                @php
                    $isERorAdmin = $isAdmin || $isER || $isOPD || $isScreening;

                    $bellNotifications = collect();
                    $latestAlertId = 0;
                    $latestAlertPayload = null;

                    if ($isERorAdmin) {
                        $firstRoundOnlyIds = \App\Models\MewsAssessment::selectRaw('MAX(id) as id')
                            ->groupBy('case_id')
                            ->havingRaw('COUNT(*) = 1')
                            ->pluck('id');

                        $bellNotifications = \App\Models\MewsAssessment::with('patient.militaryUnit')
                            ->whereIn('id', $firstRoundOnlyIds)
                            ->where('status', 'pending')
                            ->orderBy('created_at', 'desc')
                            ->take(5)->get();

                        $firstAlert = $bellNotifications->first();
                        if ($firstAlert && $firstAlert->patient) {
                            $rawFirstSyms = is_string($firstAlert->symptoms) ? json_decode($firstAlert->symptoms, true) : ($firstAlert->symptoms ?? []);
                            $hasFirstSym = is_array($rawFirstSyms) && count(array_filter($rawFirstSyms)) > 0;
                            $resolvedRisk = ($firstAlert->risk_level === 'normal' && $hasFirstSym) ? 'emergency_condition' : $firstAlert->risk_level;

                            $latestAlertId = $firstAlert->id;
                            $latestAlertPayload = [
                                'id'         => $firstAlert->id,
                                'name'       => ($firstAlert->patient->rank ?? '') . ($firstAlert->patient->first_name ?? '') . ' ' . ($firstAlert->patient->last_name ?? ''),
                                'unit'       => $firstAlert->patient->militaryUnit->short_name ?? $firstAlert->patient->company ?? '-',
                                'score'      => $firstAlert->total_score,
                                'risk_level' => $resolvedRisk,
                                'url'        => route('backend.mews.show', $firstAlert->id),
                            ];
                        }
                    }
                @endphp

                <!-- 2.5 ปุ่ม Quick Help -->
                <div class="relative flex items-center shrink-0 mr-1 md:mr-2">
                    <button type="button" onclick="showQuickHelp()" class="relative flex items-center justify-center w-7 h-7 md:w-8 md:h-8 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-500 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 hover:text-indigo-600 transition-colors border border-indigo-100 dark:border-indigo-800 shadow-sm focus:outline-none" title="คู่มือด่วน (Cheat Sheet)">
                        <i class="fa-solid fa-circle-question text-base md:text-lg"></i>
                    </button>
                </div>

                <!-- 3. ปุ่มกระดิ่งแจ้งเตือน -->
                <div class="relative flex items-center shrink-0">
                    <button id="notification-bell-btn"
                            data-latest-id="{{ $latestAlertId }}"
                            data-latest-payload='@json($latestAlertPayload)'
                            type="button"
                            class="relative text-slate-400 hover:text-emerald-500 transition-colors p-1.5 md:p-2 focus:outline-none">
                        <i class="fa-solid fa-bell text-lg md:text-xl"></i>
                        <span id="notification-badge" class="absolute top-1 right-1 w-2.5 h-2.5 bg-rose-500 rounded-full ring-2 ring-white dark:ring-slate-800 hidden"></span>
                    </button>

                    <div id="notification-dropdown" class="hidden fixed left-4 right-4 top-16 sm:absolute sm:left-auto sm:right-0 sm:top-full mt-2 sm:mt-3 w-auto sm:w-80 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl sm:shadow-[0_10px_40px_-10px_rgba(0,0,0,0.15)] border border-slate-100 dark:border-slate-700 z-[60] overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center">
                            <span class="font-bold text-sm text-slate-800 dark:text-white">
                                <i class="fa-solid fa-bell text-emerald-500 mr-1.5"></i> แจ้งเตือนล่าสุด
                            </span>
                            <button type="button" id="btn-enable-browser-notify" onclick="requestBrowserNotificationPermission()"
                                    class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-200 transition-colors flex items-center gap-1 shadow-sm">
                                <i class="fa-regular fa-bell"></i> เปิดแจ้งเตือนวินโดวส์
                            </button>
                        </div>

                        <div id="notification-list" class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/50 bg-white dark:bg-slate-800">
                            @if($bellNotifications->count() > 0)
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-900/50 border-b border-slate-100 flex items-center justify-between sticky top-0 z-10 shadow-sm">
                                    <span class="text-[10px] font-black text-rose-600 uppercase tracking-widest"><i class="fa-solid fa-heart-pulse mr-1"></i> เคสฉุกเฉิน / เฝ้าระวัง (ER)</span>
                                    <span class="text-[10px] font-bold text-slate-500">{{ $bellNotifications->count() }} ราย</span>
                                </div>
                                @foreach($bellNotifications as $alert)
                                    @php
                                        $rawAlertSyms = is_string($alert->symptoms) ? json_decode($alert->symptoms, true) : ($alert->symptoms ?? []);
                                        $hasSym = is_array($rawAlertSyms) && count(array_filter($rawAlertSyms)) > 0;
                                        $isEmerRow = $alert->risk_level === 'emergency' || $hasSym;
                                        $isObsRow  = !$isEmerRow && $alert->risk_level === 'observe';

                                        $badgeColor = $isEmerRow ? 'bg-rose-100 text-rose-700' : ($isObsRow ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');
                                        $scoreColor = $isEmerRow ? 'text-rose-600' : ($isObsRow ? 'text-amber-600' : 'text-emerald-600');
                                        $borderLeft = $isEmerRow ? 'border-l-rose-500' : ($isObsRow ? 'border-l-amber-400' : 'border-l-emerald-400');
                                        $badgeText  = $hasSym ? 'อาการฉุกเฉิน' : ($isEmerRow ? 'EMERGENCY' : ($isObsRow ? 'OBSERVE' : 'ปกติ (OPD)'));
                                    @endphp
                                    <a href="{{ route('backend.mews.show', $alert->id) }}" class="block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors border-l-4 {{ $borderLeft }}">
                                        <div class="flex justify-between items-start mb-1">
                                            <p class="text-sm font-bold text-slate-800 dark:text-white">{{ $alert->patient->rank }}{{ $alert->patient->first_name }} {{ $alert->patient->last_name }}</p>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $badgeColor }}">{{ $badgeText }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500"><i class="fa-solid fa-tent mr-1"></i> {{ $alert->patient->militaryUnit->short_name ?? '-' }}</p>
                                        <p class="text-xs text-slate-600 mt-1 font-medium">คะแนน MEWS: <span class="font-bold {{ $scoreColor }}">{{ $alert->total_score }} แต้ม</span></p>
                                    </a>
                                @endforeach
                            @endif

                            @if(isset($upcomingAppAlerts) && $upcomingAppAlerts->count() > 0)
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-900/50 border-y border-slate-100 flex items-center justify-between sticky top-0 z-10 shadow-sm mt-1">
                                    <span class="text-[10px] font-black text-sky-600 uppercase tracking-widest"><i class="fa-solid fa-calendar-check mr-1"></i> คิวนัดพบแพทย์ (ล่วงหน้า {{ $sysAppAlertDays }} วัน)</span>
                                    <span class="text-[10px] font-bold text-slate-500">{{ $upcomingAppAlerts->count() }} ราย</span>
                                </div>
                                @foreach($upcomingAppAlerts->take(5) as $appAlert)
                                    @php
                                        $ptApp = $appAlert->patient;
                                        $isAppToday = $appAlert->appointment_date->format('Y-m-d') === date('Y-m-d');
                                        $unitShort = $appAlert->militaryUnit->short_name ?? $ptApp->militaryUnit->short_name ?? '-';
                                        $hospName = $appAlert->hospital_name ?: ($appAlert->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายฯ');
                                    @endphp
                                    <a href="{{ route('backend.recruit_appointments.index', ['patient_id' => $appAlert->patient_id, 'intake_batch' => $appAlert->intake_batch]) }}"
                                       class="block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors border-l-4 border-l-transparent">
                                        <div class="flex justify-between items-start gap-2 mb-1">
                                            <p class="text-sm font-bold text-slate-800 dark:text-white">
                                                {{ $ptApp->rank ?? 'พลฯ' }}{{ $ptApp->first_name ?? '' }} {{ $ptApp->last_name ?? '' }}
                                            </p>
                                            <span class="text-[10px] font-black px-2 py-0.5 rounded shrink-0 {{ $isAppToday ? 'bg-rose-100 text-rose-700 animate-pulse' : 'bg-amber-100 text-amber-700' }}">
                                                {{ $isAppToday ? '🚨 นัดวันนี้' : '⏰ นัดล่วงหน้า' }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-300 font-semibold">
                                            <i class="fa-solid fa-hospital text-indigo-500 mr-1"></i> {{ $hospName }} ({{ $appAlert->clinic_name ?: 'ตรวจตามนัด' }})
                                        </p>
                                        <p class="text-[11px] text-slate-500 mt-0.5">
                                            <i class="fa-regular fa-clock mr-1"></i> {{ $appAlert->appointment_date->copy()->addYears(543)->format('d/m/Y') }} เวลา {{ $appAlert->appointment_time ?: '08:30' }} น. • <b>{{ $unitShort }}</b>
                                        </p>
                                    </a>
                                @endforeach
                            @endif

                            @if($bellNotifications->count() === 0 && (!isset($upcomingAppAlerts) || $upcomingAppAlerts->count() === 0))
                                <div id="notification-empty" class="px-4 py-10 text-center flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mb-3 shadow-inner">
                                        <i class="fa-regular fa-bell-slash text-xl text-slate-300"></i>
                                    </div>
                                    <p class="text-sm font-bold text-slate-500">ยังไม่มีการแจ้งเตือน</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 4. Profile Dropdown -->
                <div class="relative ml-1 shrink-0">
                    <button id="profile-menu-btn" type="button" class="flex items-center gap-2 pl-1.5 md:pl-2 pr-1 md:pr-3 py-1 bg-white hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-full md:rounded-xl transition-colors focus:outline-none border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div class="w-7 h-7 md:w-8 md:h-8 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs md:text-sm">
                            {{ mb_substr(auth()->user()->name, 0, 1) }}
                        </div>
                        <span class="hidden md:block text-sm font-bold text-slate-700 dark:text-slate-200 pr-1">{{ auth()->user()->name }}</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 hidden md:block"></i>
                    </button>

                    <div id="profile-dropdown" class="hidden absolute right-0 top-full mt-2 w-64 max-w-[calc(100vw-1.5rem)] bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200/80 dark:border-slate-700 z-[60] overflow-hidden animate-fade-in-up">
                        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 md:hidden bg-slate-50/80 dark:bg-slate-800/80">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ auth()->user()->email }}</p>
                        </div>

                        <div class="p-1.5 space-y-1">
                            <a href="{{ route('backend.profile.edit') }}" class="flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100/80 dark:hover:bg-slate-700/70 transition-colors group">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 group-hover:bg-white dark:group-hover:bg-slate-600 transition-colors">
                                    <i class="fa-solid fa-user-doctor text-sm"></i>
                                </div>
                                <span class="truncate">ตั้งค่าโปรไฟล์</span>
                            </a>

                            <div class="pwa-hide-when-installed">
                                <button type="button" onclick="window.openPwaInstallModal()"
                                        class="w-full flex items-center gap-3 px-2.5 py-2 rounded-xl bg-emerald-50/80 hover:bg-emerald-100/90 dark:bg-emerald-900/25 dark:hover:bg-emerald-900/45 border border-emerald-200/70 dark:border-emerald-800/70 text-left transition-all group">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                        <i class="fa-solid fa-download text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[13px] font-bold text-emerald-900 dark:text-emerald-200 leading-tight whitespace-nowrap">
                                            ติดตั้งแอปลงมือถือ
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-0.5 text-[11px] font-semibold text-emerald-700/90 dark:text-emerald-400 leading-none">
                                            <span class="inline-flex items-center gap-1"><i class="fa-brands fa-apple text-[11px]"></i> iOS</span>
                                            <span class="opacity-40">•</span>
                                            <span class="inline-flex items-center gap-1"><i class="fa-brands fa-android text-[10px]"></i> Android</span>
                                        </div>
                                    </div>
                                    <i class="fa-solid fa-chevron-right text-[10px] text-emerald-500/70 group-hover:translate-x-0.5 transition-transform shrink-0"></i>
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-700"></div>

                        <div class="p-1.5">
                            <form action="{{ route('logout') }}" method="POST" class="block w-full m-0 p-0">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors text-left group">
                                    <div class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-900/30 text-rose-500 flex items-center justify-center shrink-0 group-hover:bg-rose-100 dark:group-hover:bg-rose-900/50 transition-colors">
                                        <i class="fa-solid fa-right-from-bracket text-sm"></i>
                                    </div>
                                    <span>ออกจากระบบ</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- เนื้อหาหลัก -->
        <main class="flex-1 flex flex-col justify-between overflow-x-hidden overflow-y-auto bg-slate-50/50 dark:bg-slate-900 transition-colors duration-300">
            <div class="p-4 md:p-6 flex-1">
                <!-- 🎖️ M-CARE SYSTEM: แบนเนอร์แสดงเฉพาะหน้าภาพรวมข้อมูลพลทหาร -->
                @if(request()->routeIs('backend.patients.index') && (request('view') === 'overview' || (isset($viewMode) && $viewMode === 'overview')))
                <div class="max-w-7xl mx-auto mb-6 print:hidden animate-fade-in-down">
                    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-[#0b3b36] to-slate-900 p-5 sm:p-6 shadow-xl border border-teal-500/30 text-white">
                        <div class="absolute -top-24 -left-24 w-64 h-64 bg-teal-500/20 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="absolute -bottom-24 -right-16 w-72 h-72 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="absolute right-6 top-1/2 -translate-y-1/2 text-white/[0.04] text-8xl sm:text-9xl pointer-events-none select-none">
                            <i class="fa-solid fa-shield-heart"></i>
                        </div>

                        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                            <div class="flex items-start sm:items-center gap-4 sm:gap-5">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-teal-400 via-emerald-500 to-teal-700 p-0.5 shadow-lg shadow-teal-500/25 shrink-0">
                                    <div class="w-full h-full bg-slate-950/40 backdrop-blur-sm rounded-[14px] flex flex-col items-center justify-center">
                                        <i class="fa-solid fa-heart-pulse text-xl sm:text-2xl text-white drop-shadow"></i>
                                        <span class="text-[8px] font-black tracking-widest text-teal-200 uppercase mt-0.5">M-CARE</span>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <h1 class="text-xl sm:text-2xl md:text-3xl font-black tracking-tight bg-gradient-to-r from-white via-teal-100 to-emerald-300 bg-clip-text text-transparent">
                                            M-CARE SYSTEM
                                        </h1>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-extrabold bg-teal-400/15 text-teal-300 border border-teal-400/30 shadow-sm">
                                            <span class="w-1.5 h-1.5 rounded-full bg-teal-400 animate-ping"></span>
                                            KSVR HOSPITAL
                                        </span>
                                    </div>
                                    <p class="text-xs sm:text-sm font-bold text-teal-300/95 tracking-wide">
                                        Military Conscript Administration &amp; Remote Health Care
                                    </p>
                                    <p class="text-xs sm:text-[13px] font-medium text-slate-300">
                                        ระบบสารสนเทศเพื่อการบริหารจัดการสุขภาพทหารใหม่แบบครบวงจร • โรงพยาบาลค่ายกฤษณ์สีวะรา
                                    </p>
                                </div>
                            </div>

                            <div class="hidden xl:grid grid-cols-2 gap-2 shrink-0 bg-slate-950/40 backdrop-blur-md p-2.5 rounded-2xl border border-white/10 text-[11px] font-bold text-slate-200">
                                <a href="{{ route('backend.patients.index', ['view' => 'list']) }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-teal-500/20 hover:text-teal-200 border border-transparent hover:border-teal-500/30 transition-all">
                                    <i class="fa-solid fa-qrcode text-teal-400"></i>
                                    <span>คัดกรอง &amp; ทะเบียนประวัติ</span>
                                </a>
                                <a href="{{ route('backend.patients.search') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-rose-500/20 hover:text-rose-200 border border-transparent hover:border-rose-500/30 transition-all">
                                    <i class="fa-solid fa-heart-circle-check text-rose-400"></i>
                                    <span>เฝ้าระวังสัญญาณชีพ MEWS</span>
                                </a>
                                <a href="{{ route('backend.recruit_infirmary.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-amber-500/20 hover:text-amber-200 border border-transparent hover:border-amber-500/30 transition-all">
                                    <i class="fa-solid fa-bed-pulse text-amber-400"></i>
                                    <span>ห้องพยาบาล &amp; ระบบยา e-MAR</span>
                                </a>
                                <a href="{{ route('backend.recruit_appointments.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-sky-500/20 hover:text-sky-200 border border-transparent hover:border-sky-500/30 transition-all">
                                    <i class="fa-solid fa-rotate text-sky-400"></i>
                                    <span>เชื่อมโยงนัดหมาย HosXP</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- 📢 แถบประกาศระบบ (อ่านจาก Cache 0 DB Query + มีเกราะกัน Error 500) -->
                @php
                    $sysAnnouncementOn = false;
                    $sysAnnouncementMsg = '';
                    $sysAnnouncementType = 'info';
                    if (class_exists(\App\Services\SettingService::class)) {
                        try {
                            $sysAnnouncementOn   = \App\Services\SettingService::get('announcement_enabled', false);
                            $sysAnnouncementMsg  = \App\Services\SettingService::get('announcement_message', '');
                            $sysAnnouncementType = \App\Services\SettingService::get('announcement_type', 'info');
                        } catch (\Throwable $e) {
                            $sysAnnouncementOn = false;
                        }
                    }
                    $sysAnnCls = match($sysAnnouncementType) {
                        'danger'  => 'bg-rose-600 text-white border-rose-700',
                        'warning' => 'bg-amber-500 text-white border-amber-600',
                        default   => 'bg-indigo-600 text-white border-indigo-700',
                    };
                @endphp
                @if($sysAnnouncementOn && !empty($sysAnnouncementMsg))
                    <div class="max-w-7xl mx-auto mb-4 px-4 py-2.5 rounded-2xl border shadow-sm text-xs sm:text-sm font-bold flex items-center gap-2.5 print:hidden {{ $sysAnnCls }}">
                        <i class="fa-solid fa-bullhorn shrink-0"></i>
                        <span>{{ $sysAnnouncementMsg }}</span>
                    </div>
                @endif

                @yield('content')
            </div>

            <!-- Footer -->
            <footer class="py-4 px-4 md:px-6 text-center text-xs sm:text-sm text-slate-500 dark:text-slate-400 border-t border-slate-200/80 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm shrink-0 print:hidden">
                <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-2">
                    <div class="flex flex-wrap justify-center md:justify-start items-center gap-2">
                        @if(isset($isRecruitGroupActive) && $isRecruitGroupActive)
                            <span class="px-2 py-0.5 rounded-md bg-teal-50 dark:bg-teal-950 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 font-black text-[11px]">
                                <i class="fa-solid fa-shield-heart mr-1"></i>M-CARE System
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-black text-[11px]">
                                <i class="fa-solid fa-heart-pulse mr-1"></i>KSVR MEWS
                            </span>
                        @endif
                        <span>&copy; {{ date('Y') }} โรงพยาบาลค่ายกฤษณ์สีวะรา (KSVR Hospital). All rights reserved.</span>
                    </div>

                    <div class="flex flex-wrap justify-center items-center gap-2">
                        <span>
                            Developed with <i class="fa-solid fa-heart text-rose-500 animate-pulse mx-0.5"></i> by
                            <span class="font-bold text-indigo-600 dark:text-indigo-400">Supakit Dangprasert</span>
                        </span>
                        <span class="hidden sm:inline text-slate-300 dark:text-slate-700">|</span>
                        <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-[11px] text-slate-500 font-mono font-bold">v1.4.2</span>
                    </div>
                </div>
            </footer>
        </main>

        <!-- 🌟 โซนปุ่มลอย (Floating Action Buttons) -->
        <div class="fixed bottom-6 right-6 md:bottom-10 md:right-10 z-[70] flex flex-col-reverse items-end gap-3 md:gap-4 pointer-events-none print:hidden">
            @if(!isset($hideExecutiveAiButton) || !$hideExecutiveAiButton)
                <button onclick="openExecutiveAiModal()"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-full w-14 h-14 md:w-16 md:h-16 flex items-center justify-center shadow-[0_10px_25px_rgba(79,70,229,0.5)] hover:shadow-[0_15px_35px_rgba(79,70,229,0.6)] transition-all duration-300 transform hover:-translate-y-1 focus:outline-none ring-4 ring-indigo-500/20 group pointer-events-auto" title="ถาม AI อัจฉริยะ (KSVR AI)">
                    <i class="fa-solid fa-robot text-2xl group-hover:scale-110 transition-transform duration-300"></i>
                </button>
            @endif

            {{-- @if(request()->routeIs('backend.mews.show') && isset($currentMews) && \Illuminate\Support\Facades\Route::has('backend.ai.sbar_handover'))
                <button type="button" onclick="openSbarHandoverModal({{ $currentMews->id }})"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white rounded-full h-12 md:h-13 px-4 flex items-center justify-center gap-2 shadow-[0_10px_25px_rgba(16,185,129,0.45)] transition-all duration-300 transform hover:-translate-y-1 focus:outline-none ring-4 ring-emerald-500/20 pointer-events-auto text-xs md:text-sm font-black" title="สรุปส่งเวร / ส่งต่อ Refer แบบ SBAR ด้วย AI 1 คลิก">
                    <i class="fa-solid fa-file-medical text-base"></i>
                    <span class="hidden sm:inline">AI ส่งเวร SBAR</span>
                </button>
            @endif --}}

            @yield('floating_buttons')
        </div>
    </div>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- 🌟 Scripts หลักควบคุมระบบ UI 🌟 -->
    <script>
        function updateFontSizeUI(sizePx) {
            const slider = document.getElementById('font-slider-bg');
            const btnSm = document.getElementById('text-btn-sm');
            const btnMd = document.getElementById('text-btn-md');
            const btnLg = document.getElementById('text-btn-lg');

            [btnSm, btnMd, btnLg].forEach(btn => {
                if (btn) {
                    btn.classList.remove('text-indigo-600', 'dark:text-indigo-400', 'font-black');
                    btn.classList.add('text-slate-500', 'dark:text-slate-400', 'font-bold');
                }
            });

            if (sizePx === '14px') {
                if (slider) slider.style.transform = 'translateX(0%)';
                if (btnSm) btnSm.classList.add('text-indigo-600', 'dark:text-indigo-400', 'font-black');
            } else if (sizePx === '16px') {
                if (slider) slider.style.transform = 'translateX(100%)';
                if (btnMd) btnMd.classList.add('text-indigo-600', 'dark:text-indigo-400', 'font-black');
            } else if (sizePx === '18px') {
                if (slider) slider.style.transform = 'translateX(200%)';
                if (btnLg) btnLg.classList.add('text-indigo-600', 'dark:text-indigo-400', 'font-black');
            }
        }

        function changeTextSize(sizePx) {
            document.documentElement.style.fontSize = sizePx;
            localStorage.setItem('textSize', sizePx);
            updateFontSizeUI(sizePx);
        }

        function updateThemeUI() {
            const htmlTag = document.documentElement;
            const themeIcon = document.getElementById('theme-icon');
            const themeSlider = document.getElementById('theme-slider');
            const isMobile = window.innerWidth < 768;

            if (htmlTag.classList.contains('dark')) {
                if (themeIcon) themeIcon.className = "fa-solid fa-moon text-indigo-500 text-[9px] md:text-[11px] transition-all duration-300";
                if (themeSlider) themeSlider.style.transform = isMobile ? 'translateX(20px)' : 'translateX(24px)';
            } else {
                if (themeIcon) themeIcon.className = "fa-solid fa-sun text-amber-500 text-[9px] md:text-[11px] transition-all duration-300";
                if (themeSlider) themeSlider.style.transform = 'translateX(4px)';
            }
        }

        function toggleDarkMode() {
            const htmlTag = document.documentElement;
            if (htmlTag.classList.contains('dark')) {
                htmlTag.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                htmlTag.classList.add('dark');
                localStorage.theme = 'dark';
            }
            updateThemeUI();
        }

        document.addEventListener('DOMContentLoaded', function () {
            const savedTextSize = localStorage.getItem('textSize') || '16px';
            updateFontSizeUI(savedTextSize);
            updateThemeUI();

            const modalCustomClass = {
                popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border border-slate-100 dark:border-slate-700 p-6 transition-colors duration-300',
                title: 'text-xl font-bold text-slate-800 dark:text-white',
                htmlContainer: 'text-sm text-slate-500 dark:text-slate-400 mt-2',
                actions: 'flex gap-3 mt-6 w-full justify-center',
                confirmButton: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl px-6 py-2.5 transition-colors shadow-sm outline-none',
                cancelButton: 'bg-slate-200 hover:bg-slate-300 text-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200 font-bold rounded-xl px-6 py-2.5 transition-colors shadow-sm outline-none'
            };

            @if(session('success'))
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: @json(session('success')),
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    scrollbarPadding: false,
                    heightAuto: false,
                    customClass: {
                        popup: 'bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700',
                        title: 'text-sm font-bold text-slate-800 dark:text-white mt-1'
                    }
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด!',
                    text: @json(session('error')),
                    buttonsStyling: false,
                    scrollbarPadding: false,
                    heightAuto: false,
                    customClass: modalCustomClass
                });
            @endif

            window.confirmAction = function(event, title, text, icon, formId) {
                event.preventDefault();
                const sidebar = document.getElementById('sidebar');
                if (sidebar) sidebar.style.zIndex = '0';

                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> ยืนยัน',
                    cancelButtonText: 'ยกเลิก',
                    reverseButtons: true,
                    buttonsStyling: false,
                    scrollbarPadding: false,
                    heightAuto: false,
                    customClass: modalCustomClass
                }).then((result) => {
                    if (sidebar) sidebar.style.zIndex = '40';
                    if (result.isConfirmed) {
                        document.getElementById(formId).submit();
                    }
                });
            };

            const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn') || document.getElementById('mobile-menu-btn');
            const sidebar = document.getElementById('sidebar');

            if (sidebarToggleBtn && sidebar) {
                sidebarToggleBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('-translate-x-full');
                    sidebar.classList.toggle('lg:-ml-64');
                });

                document.addEventListener('click', function (e) {
                    if (window.innerWidth < 1024) {
                        if (!sidebar.classList.contains('-translate-x-full') && !sidebar.contains(e.target) && !sidebarToggleBtn.contains(e.target)) {
                            sidebar.classList.add('-translate-x-full');
                        }
                    }
                });

                const menuLinks = sidebar.querySelectorAll('nav a');
                menuLinks.forEach(link => {
                    link.addEventListener('click', () => {
                        if (window.innerWidth < 1024) {
                            sidebar.classList.add('-translate-x-full');
                        }
                    });
                });
            }

            const profileBtn = document.getElementById('profile-menu-btn');
            const profileDropdown = document.getElementById('profile-dropdown');

            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');
                });
            }

            const bellBtn = document.getElementById('notification-bell-btn');
            const bellDropdown = document.getElementById('notification-dropdown');
            const badge = document.getElementById('notification-badge');

            if (bellBtn && badge) {
                const serverId = parseInt(bellBtn.getAttribute('data-latest-id'), 10) || 0;
                const localId = parseInt(localStorage.getItem('ksvr_bell_id'), 10) || 0;

                if (serverId > 0 && serverId > localId) {
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }

                bellBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (bellDropdown) bellDropdown.classList.toggle('hidden');

                    if (bellDropdown && !bellDropdown.classList.contains('hidden')) {
                        badge.classList.add('hidden');
                        if (serverId > 0) {
                            localStorage.setItem('ksvr_bell_id', serverId.toString());
                        }
                    }
                });
            }

            document.addEventListener('click', function (e) {
                if (profileDropdown && !profileDropdown.contains(e.target) && profileBtn && !profileBtn.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }
                if (bellDropdown && !bellDropdown.contains(e.target) && bellBtn && !bellBtn.contains(e.target)) {
                    bellDropdown.classList.add('hidden');
                }
            });
        });

        function showQuickHelp() {
            if (typeof window.openWorkflowOverviewModal === 'function') {
                window.openWorkflowOverviewModal();
            }
        }
    </script>

    <!-- 🌟 ระบบแจ้งเตือนผ่านเบราว์เซอร์ + Hybrid Real-time Polling (เชื่อมรอบเวลาจาก SettingService) 🌟 -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                const swUrl = "{{ asset('sw.js') }}";
                navigator.serviceWorker.register(swUrl).catch(err => console.warn('SW Register Warning:', err));
            });
        }

        window.playAlertSound = function(isEmergency = false) {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const playTone = (freq, startTime, duration) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = isEmergency ? 'sawtooth' : 'sine';
                    osc.frequency.setValueAtTime(freq, startTime);
                    gain.gain.setValueAtTime(0.15, startTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, startTime + duration);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(startTime);
                    osc.stop(startTime + duration);
                };

                if (isEmergency) {
                    playTone(880, ctx.currentTime, 0.18);
                    playTone(880, ctx.currentTime + 0.22, 0.18);
                    playTone(1174, ctx.currentTime + 0.44, 0.35);
                } else {
                    playTone(587.33, ctx.currentTime, 0.15);
                    playTone(880, ctx.currentTime + 0.18, 0.3);
                }
            } catch (e) {}
        };

        window.isIosNotInstalled = function() {
            const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            return isIos && !isStandalone;
        };

        window.updateBrowserNotifyButtonUI = function() {
            const btn = document.getElementById('btn-enable-browser-notify');
            if (!btn) return;

            if (window.isIosNotInstalled()) {
                btn.className = 'text-[10px] font-bold px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1';
                btn.innerHTML = '<i class="fa-brands fa-apple"></i> วิธีเปิดแจ้งเตือนบน iPad/iPhone';
                return;
            }

            if (!('Notification' in window)) {
                btn.classList.add('hidden');
                return;
            }

            if (Notification.permission === 'granted') {
                btn.className = 'text-[10px] font-bold px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 flex items-center gap-1 cursor-pointer transition-colors';
                btn.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-500"></i> แจ้งเตือนเปิดอยู่ (คลิกทดสอบ)';
                btn.removeAttribute('onclick');
                btn.onclick = function(e) {
                    e.stopPropagation();
                    window.playAlertSound(false);
                    window.triggerNativeBrowserNotification(
                        '🔔 ทดสอบการแจ้งเตือน KSVR MEWS',
                        'ระบบแจ้งเตือนบนเบราว์เซอร์ทำงานสมบูรณ์แล้วครับ! (คลิกเพื่อเปิดหน้าต่าง)',
                        window.location.href,
                        'test-notify-' + Date.now(),
                        false,
                        true
                    );
                };
            } else if (Notification.permission === 'denied') {
                btn.className = 'text-[10px] font-bold px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 flex items-center gap-1 cursor-pointer';
                btn.innerHTML = '<i class="fa-solid fa-bell-slash"></i> ถูกบล็อก (คลิกดูวิธีแก้)';
            } else {
                btn.innerHTML = '<i class="fa-regular fa-bell"></i> เปิดแจ้งเตือนเบราว์เซอร์';
            }
        };

        window.requestBrowserNotificationPermission = async function() {
            if (window.isIosNotInstalled()) {
                Swal.fire({
                    icon: 'info',
                    title: 'วิธีเปิดแจ้งเตือนบน iPhone / iPad (Safari)',
                    html: `
                        <div class="text-left text-sm space-y-2 mt-2 text-slate-600">
                            <p>บนระบบ iOS/iPadOS ต้องเพิ่มเว็บไว้ที่หน้าจอโฮมก่อนจึงจะรับแจ้งเตือนได้ครับ:</p>
                            <ol class="list-decimal list-inside space-y-1.5 font-semibold text-slate-800">
                                <li>แตะปุ่ม <b>แชร์ (Share)</b> ของเบราว์เซอร์ Safari</li>
                                <li>เลือกเมนู <b>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen)</b></li>
                                <li>เปิดแอป <b>KSVR MEWS</b> จากหน้าจอโฮม แล้วกดปุ่มเปิดแจ้งเตือนนี้อีกครั้งครับ</li>
                            </ol>
                        </div>
                    `,
                    confirmButtonText: 'เข้าใจแล้ว',
                    customClass: { popup: 'rounded-3xl' }
                });
                return;
            }

            if (!('Notification' in window)) {
                Swal.fire({ icon: 'warning', title: 'เบราว์เซอร์ไม่รองรับ', text: 'กรุณาเปิดใช้งานผ่าน Chrome, Edge, Safari หรือ Firefox บน HTTPS ครับ' });
                return;
            }

            if (Notification.permission === 'denied') {
                Swal.fire({
                    icon: 'info',
                    title: 'การแจ้งเตือนถูกปิดกั้นไว้',
                    html: 'กรุณาคลิกที่ไอคอน <b>ตั้งค่าเว็บไซต์ (ข้างแถบ URL ด้านบน)</b> บนเบราว์เซอร์ แล้วปรับ <b>Notifications (การแจ้งเตือน)</b> เป็น <b>Allow (อนุญาต)</b> ครับ',
                    confirmButtonText: 'เข้าใจแล้ว',
                    customClass: { popup: 'rounded-3xl' }
                });
                return;
            }

            let permission = 'default';
            try {
                permission = await Notification.requestPermission();
            } catch (e) {
                permission = await new Promise(resolve => Notification.requestPermission(resolve));
            }

            window.updateBrowserNotifyButtonUI();

            if (permission === 'granted') {
                window.playAlertSound(false);
                window.triggerNativeBrowserNotification(
                    '✅ เปิดรับการแจ้งเตือนสำเร็จ!',
                    'เมื่อมีเคสผู้ป่วยส่งเข้ามา ระบบจะเด้งแจ้งเตือนที่มุมจอทันทีครับ',
                    window.location.href,
                    'init-success-' + Date.now(),
                    false,
                    true
                );
            }
        };

        window.triggerNativeBrowserNotification = async function(title, bodyText, targetUrl, tagId = 'ksvr-mews-alert', isUrgent = false, isManualTest = false) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;

            const iconUrl = "{{ asset('images/logo5.png') }}";
            const uniqueTag = tagId + '-' + Date.now();
            const options = {
                body: bodyText,
                icon: iconUrl,
                badge: iconUrl,
                tag: uniqueTag,
                renotify: true,
                requireInteraction: isUrgent,
                data: { url: targetUrl }
            };

            let shownSuccess = false;

            try {
                const notification = new Notification(title, options);
                notification.onclick = function(event) {
                    event.preventDefault();
                    window.focus();
                    if (targetUrl) window.location.href = targetUrl;
                    notification.close();
                };
                shownSuccess = true;
            } catch (directErr) {
                try {
                    if ('serviceWorker' in navigator) {
                        const reg = await navigator.serviceWorker.getRegistration();
                        if (reg && reg.active && typeof reg.showNotification === 'function') {
                            await reg.showNotification(title, options);
                            shownSuccess = true;
                        }
                    }
                } catch (swErr) {
                    console.warn('SW Notification Error:', swErr);
                }
            }

            if (isManualTest && shownSuccess) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'ส่งแจ้งเตือนไปยังเบราว์เซอร์แล้ว!',
                    html: '<span class="text-xs text-slate-500">หากไม่เห็นป๊อปอัปมุมจอ Windows กรุณาเช็คโหมด "ห้ามรบกวน (Do Not Disturb / Focus Assist)" ของ Windows ครับ</span>',
                    showConfirmButton: false,
                    timer: 4500
                });
            }
        };

        window.checkAndTriggerBellAlert = function(payloadData) {
            if (!payloadData || !payloadData.id) return;

            const incomingId = parseInt(payloadData.id, 10) || 0;
            const lastNotifiedId = parseInt(localStorage.getItem('ksvr_notified_alert_id'), 10) || 0;

            if (incomingId > lastNotifiedId) {
                localStorage.setItem('ksvr_notified_alert_id', incomingId.toString());

                const badge = document.getElementById('notification-badge');
                if (badge) badge.classList.remove('hidden');

                const isEmer = payloadData.risk_level === 'emergency' || payloadData.risk_level === 'emergency_condition';
                const isObs  = payloadData.risk_level === 'observe';

                window.playAlertSound(isEmer);
                const notifyTitle = isEmer
                    ? `🚨 ฉุกเฉิน! ส่งตัวเข้า ER (MEWS: ${payloadData.score})`
                    : (isObs ? `⚠️ เคสเฝ้าระวังใหม่ (MEWS: ${payloadData.score})` : `✅ ข้อมูลประเมินใหม่ (MEWS: ${payloadData.score})`);

                window.triggerNativeBrowserNotification(
                    notifyTitle,
                    `ผู้ป่วย: ${payloadData.name}\nสังกัด: ${payloadData.unit} (คลิกเพื่อเปิดดูเคสทันที)`,
                    payloadData.url,
                    `mews-case-${incomingId}`,
                    isEmer
                );
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            window.updateBrowserNotifyButtonUI();

            const bellBtn = document.getElementById('notification-bell-btn');
            if (bellBtn) {
                try {
                    const initialPayload = JSON.parse(bellBtn.getAttribute('data-latest-payload') || 'null');
                    if (initialPayload && initialPayload.id) {
                        const lastNotified = parseInt(localStorage.getItem('ksvr_notified_alert_id'), 10) || 0;
                        if (lastNotified === 0) {
                            localStorage.setItem('ksvr_notified_alert_id', String(initialPayload.id));
                        } else if (initialPayload.id > lastNotified) {
                            window.checkAndTriggerBellAlert(initialPayload);
                        }
                    }
                } catch (e) {}
            }

            // 🔄 Background Smart Polling (อ้างอิงความถี่จากหน้าตั้งค่าระบบทั่วไป)
            const ksvrPollingMs = {{ (int) ($sysPollingSeconds ?? 30) * 1000 }};
            setInterval(function() {
                if (document.hidden === false || Notification.permission === 'granted') {
                    fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(res => res.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newBellBtn = doc.getElementById('notification-bell-btn');
                            const newNotifList = doc.getElementById('notification-list');

                            if (newBellBtn) {
                                const newId = parseInt(newBellBtn.getAttribute('data-latest-id'), 10) || 0;
                                const currentBellBtn = document.getElementById('notification-bell-btn');
                                if (currentBellBtn) currentBellBtn.setAttribute('data-latest-id', String(newId));

                                const payload = JSON.parse(newBellBtn.getAttribute('data-latest-payload') || 'null');
                                if (payload && payload.id) {
                                    const lastNotified = parseInt(localStorage.getItem('ksvr_notified_alert_id'), 10) || 0;
                                    if (payload.id > lastNotified) {
                                        if (newNotifList && document.getElementById('notification-list')) {
                                            document.getElementById('notification-list').innerHTML = newNotifList.innerHTML;
                                        }
                                        window.checkAndTriggerBellAlert(payload);
                                    }
                                }
                            }
                        })
                        .catch(() => {});
                }
            }, ksvrPollingMs);
        });
    </script>

    <!-- 🌟 Laravel Echo Real-time WebSocket 🌟 -->
    <script type="module">
        document.addEventListener('DOMContentLoaded', function () {
            if (window.Echo) {
                const userUnit = document.querySelector('meta[name="user-unit"]')?.getAttribute('content');
                const userRolesStr = document.querySelector('meta[name="user-roles"]')?.getAttribute('content') || '[]';
                let userRoles = [];
                try {
                    const parsed = JSON.parse(userRolesStr);
                    userRoles = typeof parsed === 'string' ? JSON.parse(parsed) : Object.values(parsed);
                } catch (e) {
                    userRoles = [];
                }

                const isMedic = userRoles.includes('medic');
                const isER = userRoles.includes('er_nurse') || userRoles.includes('opd_nurse') || userRoles.includes('screening');
                const isAdmin = userRoles.includes('admin');

                if (isER || isAdmin) {
                    window.Echo.private('er-alerts')
                        .listen('.mews.emergency', (e) => {
                            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
                            const targetMewsUrl = `${baseUrl}/backend/mews/${e.patientData.mews_id}`;
                            const isEmer = e.patientData.risk_level === 'emergency' || e.patientData.risk_level === 'emergency_condition';
                            const isObs  = e.patientData.risk_level === 'observe';

                            if (e.patientData.mews_id) {
                                localStorage.setItem('ksvr_notified_alert_id', String(e.patientData.mews_id));
                            }

                            const badge = document.getElementById('notification-badge');
                            if (badge) badge.classList.remove('hidden');

                            const bellBtn = document.getElementById('notification-bell-btn');
                            if (bellBtn && e.patientData.mews_id) bellBtn.setAttribute('data-latest-id', e.patientData.mews_id);

                            const notifList = document.getElementById('notification-list');
                            const notifEmpty = document.getElementById('notification-empty');
                            if (notifList) {
                                if (notifEmpty) notifEmpty.remove();
                                const itemBadgeClass = isEmer ? 'bg-rose-100 text-rose-700' : (isObs ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');
                                const itemScoreClass = isEmer ? 'text-rose-600' : (isObs ? 'text-amber-600' : 'text-emerald-600');

                                const itemHtml = `
                                    <a href="${targetMewsUrl}" class="block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors animate-fade-in-up">
                                        <div class="flex justify-between items-start mb-1">
                                            <p class="text-sm font-bold text-slate-800 dark:text-white">${e.patientData.name}</p>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded ${itemBadgeClass}">${e.patientData.risk_level.toUpperCase()}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate"><i class="fa-solid fa-tent mr-1"></i> ${e.patientData.unit}</p>
                                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 font-medium">คะแนน MEWS: <span class="font-bold ${itemScoreClass}">${e.patientData.score} แต้ม</span></p>
                                    </a>
                                `;
                                notifList.insertAdjacentHTML('afterbegin', itemHtml);
                            }

                            window.playAlertSound(isEmer);
                            const notifyTitle = isEmer
                                ? `🚨 ฉุกเฉิน! ส่งตัวเข้า ER (MEWS: ${e.patientData.score})`
                                : (isObs ? `⚠️ เคสเฝ้าระวังใหม่ (MEWS: ${e.patientData.score})` : `✅ ข้อมูลประเมินใหม่ (MEWS: ${e.patientData.score})`);

                            window.triggerNativeBrowserNotification(
                                notifyTitle,
                                `ผู้ป่วย: ${e.patientData.name}\nสังกัด: ${e.patientData.unit} (คลิกเพื่อเปิดดูเคสทันที)`,
                                targetMewsUrl,
                                `mews-alert-${e.patientData.mews_id}`,
                                isEmer
                            );

                            let alertTheme = e.patientData.risk_level === 'normal'
                                ? { title: '✅ ข้อมูลใหม่ (ปกติ)', icon: 'success', color: '#10b981', badge: 'bg-emerald-100 text-emerald-700', autoClose: 5000 }
                                : e.patientData.risk_level === 'observe'
                                ? { title: '⚠️ มีเคสเฝ้าระวังใหม่', icon: 'warning', color: '#f59e0b', badge: 'bg-amber-100 text-amber-700', autoClose: 5000 }
                                : { title: '🚨 เตือนภัยฉุกเฉิน (MEWS สีแดง)!', icon: 'error', color: '#e11d48', badge: 'bg-rose-100 text-rose-700 animate-pulse', autoClose: false };

                            Swal.fire({
                                title: alertTheme.title,
                                html: `
                                    <div class="text-left mt-2 border-t border-slate-100 dark:border-slate-700 pt-3">
                                        <p class="text-lg font-bold text-slate-800 dark:text-white mb-1">${e.patientData.name}</p>
                                        <p class="text-sm text-slate-600 dark:text-slate-400"><i class="fa-solid fa-tent text-slate-400 w-5"></i> สังกัด: <b class="dark:text-slate-200">${e.patientData.unit}</b></p>
                                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-3 bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                                            คะแนนรวม: <b class="text-lg">${e.patientData.score} แต้ม</b>
                                            <span class="px-2 py-0.5 rounded ml-2 font-bold ${alertTheme.badge}">${e.patientData.risk_level.toUpperCase()}</span>
                                        </p>
                                    </div>
                                `,
                                icon: alertTheme.icon,
                                iconColor: alertTheme.color,
                                showCancelButton: true,
                                confirmButtonColor: '#0f172a',
                                cancelButtonColor: '#94a3b8',
                                cancelButtonText: 'ปิดหน้าต่าง',
                                confirmButtonText: '<i class="fa-solid fa-file-medical mr-1"></i> ดูรายละเอียดเคส',
                                timer: alertTheme.autoClose ? alertTheme.autoClose : undefined,
                                timerProgressBar: !!alertTheme.autoClose,
                                customClass: {
                                    popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border border-slate-100 dark:border-slate-700 p-6',
                                    title: 'text-xl font-black text-slate-800 dark:text-white',
                                    actions: 'flex gap-3 mt-6 w-full justify-center',
                                    confirmButton: 'bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl px-6 py-2.5 transition-colors shadow-md outline-none',
                                    cancelButton: 'bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200 text-slate-700 font-bold rounded-xl px-6 py-2.5 transition-colors shadow-sm outline-none'
                                }
                            }).then((result) => {
                                if (result.isConfirmed) window.location.href = targetMewsUrl;
                            });
                        });
                }

                if ((isMedic || isAdmin) && userUnit) {
                    window.Echo.private(`unit-alerts.${userUnit}`)
                        .listen('.mews.status.updated', (e) => {
                            const badge = document.getElementById('notification-badge');
                            if (badge) badge.classList.remove('hidden');

                            window.playAlertSound(false);
                            window.triggerNativeBrowserNotification(
                                `🏥 อัปเดตผลการรักษาจากห้อง ER`,
                                `${e.assessmentData.name}: ${e.assessmentData.status_text}`,
                                window.location.href,
                                `unit-update-${Date.now()}`,
                                false
                            );

                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: e.assessmentData.status === 'admit' ? 'info' : 'success',
                                title: 'อัปเดตจากห้อง ER',
                                html: `พลฯ <b>${e.assessmentData.name}</b><br><span class="text-xs text-slate-500">${e.assessmentData.status_text}</span>`,
                                showConfirmButton: false,
                                timer: 6000,
                                timerProgressBar: true,
                                customClass: {
                                    popup: 'bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 p-4',
                                    title: 'text-sm font-bold text-slate-800 dark:text-white mt-1'
                                }
                            });
                        });
                }
            }

            window.addEventListener('pagehide', function () {
                if (window.Echo) window.Echo.disconnect();
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted && window.Echo) window.Echo.connect();
            });
        });
    </script>

    <!-- ⏰ Global Script: แจ้งเตือนคิวนัดพบแพทย์ล่วงหน้า (แก้บั๊กบวกปี พ.ศ. ซ้ำเรียบร้อย) -->
    @if(isset($upcomingAppAlerts) && $upcomingAppAlerts->count() > 0 && !request()->routeIs('backend.recruit_appointments.*'))
        @php
            $globalAppModalList = $upcomingAppAlerts->map(function($a) {
                $p = $a->patient;
                $isToday = $a->appointment_date->format('Y-m-d') === date('Y-m-d');
                return [
                    'id'            => $a->id,
                    'patient_id'    => $a->patient_id,
                    'full_name'     => ($p->rank ?? 'พลฯ') . ($p->first_name ?? '') . ' ' . ($p->last_name ?? ''),
                    'unit'          => $a->militaryUnit->short_name ?? $p->militaryUnit->short_name ?? '-',
                    'batch'         => $a->intake_batch ?: '-',
                    'is_today'      => $isToday,
                    'day_badge'     => $isToday ? '🚨 นัดวันนี้!' : '⏰ นัดล่วงหน้า',
                    'date_thai'     => $a->appointment_date->copy()->addYears(543)->format('d/m/Y'),
                    'time'          => ($a->appointment_time ?: '08:30') . ' น.',
                    'hospital_type' => $a->hospital_type,
                    'hospital_name' => $a->hospital_name ?: ($a->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายกฤษณ์สีวะรา'),
                    'clinic'        => $a->clinic_name ?: 'ไม่ระบุคลินิก',
                    'cause'         => $a->app_cause ?: 'พบแพทย์ตามนัด',
                    'note'          => $a->note ?: '',
                ];
            })->values();
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const globalAlerts = @json($globalAppModalList);
                if (!globalAlerts || globalAlerts.length === 0) return;

                const badge = document.getElementById('notification-badge');
                if (badge) badge.classList.remove('hidden');

                const storageKey = 'ksvr_medic_app_alert_{{ auth()->id() }}_{{ date("Y-m-d") }}';
                if (sessionStorage.getItem(storageKey)) return;

                setTimeout(() => {
                    if (typeof window.playAlertSound === 'function') {
                        window.playAlertSound(false);
                    }
                    if (typeof window.triggerNativeBrowserNotification === 'function') {
                        window.triggerNativeBrowserNotification(
                            `📅 แจ้งเตือนคิวนัดพบแพทย์ (${globalAlerts.length} ราย)`,
                            `มีพลทหารต้องไปพบแพทย์เร็วๆ นี้ กรุณาเตรียมการส่งตัวครับ`,
                            "{{ route('backend.recruit_appointments.index') }}",
                            'appointment-advance-alert',
                            false
                        );
                    }

                    let itemsHtml = '<div class="max-h-[50vh] overflow-y-auto text-left space-y-2.5 mt-3 pr-1">';
                    globalAlerts.forEach((item, idx) => {
                        const badgeCls = item.is_today ? 'bg-rose-600 text-white animate-pulse' : 'bg-amber-500 text-white';
                        const hospCls  = item.hospital_type === 'public_hospital' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : 'bg-emerald-100 text-emerald-700 border-emerald-200';
                        const noteHtml = item.note ? `<div class="text-[11px] text-amber-800 bg-amber-100/80 px-2.5 py-1 rounded-lg border border-amber-200 mt-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i><b>การเตรียมตัว:</b> ${item.note}</div>` : '';

                        itemsHtml += `
                            <div class="p-3.5 rounded-2xl border-2 ${item.is_today ? 'border-rose-200 bg-rose-50/40' : 'border-amber-200 bg-amber-50/40'} space-y-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-black text-slate-800 text-sm">${idx + 1}. ${item.full_name} <span class="text-xs font-bold text-indigo-600">(${item.unit})</span></span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black ${badgeCls}">${item.day_badge}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs pt-0.5">
                                    <span class="px-2 py-0.5 rounded border font-bold ${hospCls}">${item.hospital_name}</span>
                                    <span class="font-bold text-slate-700"><i class="fa-regular fa-calendar mr-1"></i>${item.date_thai} เวลา ${item.time}</span>
                                    <span class="text-slate-500">| แผนก: <b>${item.clinic}</b></span>
                                </div>
                                <div class="text-xs text-slate-700 font-medium pt-0.5">
                                    <b>สาเหตุการนัด:</b> ${item.cause}
                                </div>
                                ${noteHtml}
                            </div>
                        `;
                    });
                    itemsHtml += '</div>';

                    Swal.fire({
                        title: `<div class="text-lg md:text-xl font-black text-slate-800 flex items-center justify-center gap-2"><i class="fa-solid fa-calendar-check text-amber-500 animate-bounce"></i> แจ้งเตือนนำทหารพบแพทย์ (${globalAlerts.length} ราย)</div>`,
                        html: `
                            <p class="text-xs text-slate-500">แจ้งเตือนล่วงหน้าและคิวนัดวันนี้ เพื่อให้เสนารักษ์และหน่วยฝึกเตรียมความพร้อมในการนำพลทหารมาพบแพทย์ตามนัดครับ</p>
                            ${itemsHtml}
                        `,
                        width: '40rem',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fa-solid fa-calendar-days mr-1"></i> เปิดหน้าตารางนัดพบแพทย์',
                        cancelButtonText: '<i class="fa-solid fa-check mr-1"></i> รับทราบ',
                        confirmButtonColor: '#4f46e5',
                        cancelButtonColor: '#059669',
                        customClass: { popup: 'rounded-3xl p-6 shadow-2xl' }
                    }).then((res) => {
                        sessionStorage.setItem(storageKey, '1');
                        if (res.isConfirmed) {
                            window.location.href = "{{ route('backend.recruit_appointments.index') }}";
                        }
                    });
                }, 600);
            });
        </script>
    @endif

    @yield('scripts')

    <!-- 🤖 KSVR AI Data Analyzer (ครบ 11 โหมด + บริบทหน้าดูผล MEWS + AI SBAR Handover 1 คลิก) -->
    @php
        $aiPageContext = null;
        if (request()->routeIs('backend.mews.show') && isset($currentMews) && isset($patient)) {
            $aiPageContext = [
                'is_mews_show' => true,
                'mews_id'      => $currentMews->id,
                'patient_name' => trim(($patient->rank ?? '') . ($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')),
                'short_name'   => trim((string) ($patient->first_name ?? '')),
                'has_sbar_api' => \Illuminate\Support\Facades\Route::has('backend.ai.sbar_handover'),
                'sbar_url'     => \Illuminate\Support\Facades\Route::has('backend.ai.sbar_handover')
                                    ? route('backend.ai.sbar_handover', $currentMews->id)
                                    : null,
            ];
        }
    @endphp
    <script>
        const styleTitle = "color: #4f46e5; font-size: 24px; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.2);";
        const styleSubtitle = "color: #10b981; font-size: 14px; font-weight: bold;";

        console.log("%c🚀 KSVR Hospital Management System", styleTitle);
        console.log("%c👨‍💻 Engineered & Developed by Supakit (IT KSVR)", styleSubtitle);
        console.log("--------------------------------------------------");

        const ksvrAiMeta = {
            isAdmin: @json((bool) ($isAdmin ?? false)),
            canViewChronic: @json((bool) ($canViewChronicAi ?? false)),
            mewsContext: @json($aiPageContext),
            sbarUrlTemplate: @json(\Illuminate\Support\Facades\Route::has('backend.ai.sbar_handover') ? route('backend.ai.sbar_handover', ['mews_id' => '__MEWS_ID__']) : null)
        };

        function setExecutiveQuickPrompt(qText) {
            const el = document.getElementById('ai_executive_prompt');
            if (el) {
                el.value = qText;
                el.focus();
            }
        }

        function escapeAiHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // 📋 ตัวช่วยก๊อปปี้ข้อความรองรับทั้ง HTTPS และ HTTP วงแลนโรงพยาบาล
        window.ksvrCopyTextWithFeedback = function(text, btnEl, successLabel = 'ก๊อปปี้แล้ว!') {
            const onCopied = () => {
                if (!btnEl) return;
                const orig = btnEl.innerHTML;
                btnEl.innerHTML = `<i class="fa-solid fa-check mr-1"></i>${successLabel}`;
                setTimeout(() => { btnEl.innerHTML = orig; }, 1800);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(onCopied).catch(() => fallbackCopyAi(text, onCopied));
            } else {
                fallbackCopyAi(text, onCopied);
            }
        };

        function fallbackCopyAi(text, cb) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); cb(); } catch (e) {}
            document.body.removeChild(ta);
        }

        // 🩺 ฟีเจอร์ AI SBAR Handover (สรุปส่งเวร / ส่งต่อ Refer 1 คลิก บนหน้าดูผล MEWS)
        window.openSbarHandoverModal = function(mewsId) {
            if (!ksvrAiMeta.sbarUrlTemplate) {
                Swal.fire({
                    icon: 'info',
                    title: 'ยังไม่พบเส้นทาง API ของ SBAR Handover',
                    text: 'กรุณาเพิ่มเมธอด sbarHandover ใน AiController.php และ Route backend.ai.sbar_handover ใน routes/web.php ก่อนครับ',
                    customClass: { popup: 'rounded-3xl' }
                });
                return;
            }

            const targetUrl = ksvrAiMeta.sbarUrlTemplate.replace('__MEWS_ID__', mewsId);

            Swal.fire({
                title: '<div class="text-lg md:text-xl font-black text-emerald-800 flex items-center justify-center gap-2"><i class="fa-solid fa-file-medical text-emerald-500"></i> กำลังสร้างสรุปส่งเวร SBAR...</div>',
                html: '<div class="py-6 text-slate-500 text-sm"><i class="fa-solid fa-spinner fa-spin text-2xl text-emerald-500 mb-3 block"></i>AI กำลังรวบรวมสัญญาณชีพ แนวโน้มอาการ ยา e-MAR และโรคประจำตัวครับ</div>',
                showConfirmButton: false,
                allowOutsideClick: false,
                customClass: { popup: 'rounded-3xl p-6' }
            });

            fetch(targetUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่สามารถสร้างสรุป SBAR ได้',
                        text: res.message || 'กรุณาลองใหม่อีกครั้ง',
                        customClass: { popup: 'rounded-3xl' }
                    });
                    return;
                }

                const d = res.data;
                window.__latestSbarCopyText = d.copy_text || [
                    `[SBAR ส่งเวร/ส่งต่อ — ${d.patient_name || ''}]`,
                    `S (Situation): ${d.situation || '-'}`,
                    `B (Background): ${d.background || '-'}`,
                    `A (Assessment): ${d.assessment || '-'}`,
                    `R (Recommendation): ${d.recommendation || '-'}`
                ].join('\n');

                Swal.fire({
                    title: '<div class="text-lg md:text-xl font-black text-emerald-800 flex items-center justify-center gap-2"><i class="fa-solid fa-notes-medical text-emerald-500"></i> สรุปส่งเวร / ส่งต่อมาตรฐาน SBAR</div>',
                    html: `
                        <div class="text-left space-y-2.5 mt-2 max-h-[60vh] overflow-y-auto pr-1 text-xs sm:text-sm">
                            <div class="p-3 rounded-2xl bg-rose-50/70 border border-rose-200">
                                <div class="font-black text-rose-800 mb-1">S — Situation (สถานการณ์ปัจจุบัน)</div>
                                <div class="text-slate-700 leading-relaxed">${escapeAiHtml(d.situation)}</div>
                            </div>
                            <div class="p-3 rounded-2xl bg-sky-50/70 border border-sky-200">
                                <div class="font-black text-sky-800 mb-1">B — Background (ประวัติ / โรคประจำตัว / ยา e-MAR)</div>
                                <div class="text-slate-700 leading-relaxed">${escapeAiHtml(d.background)}</div>
                            </div>
                            <div class="p-3 rounded-2xl bg-amber-50/70 border border-amber-200">
                                <div class="font-black text-amber-800 mb-1">A — Assessment (การประเมินและแนวโน้ม MEWS)</div>
                                <div class="text-slate-700 leading-relaxed">${escapeAiHtml(d.assessment)}</div>
                            </div>
                            <div class="p-3 rounded-2xl bg-emerald-50/70 border border-emerald-200">
                                <div class="font-black text-emerald-800 mb-1">R — Recommendation (ข้อเสนอแนะและการส่งต่อ)</div>
                                <div class="text-slate-700 leading-relaxed">${escapeAiHtml(d.recommendation)}</div>
                            </div>
                            <div class="pt-1 flex justify-end">
                                <button type="button" onclick="window.ksvrCopyTextWithFeedback(window.__latestSbarCopyText, this, 'ก๊อปปี้ข้อความ SBAR แล้ว!')"
                                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs inline-flex items-center justify-center gap-1.5 shadow-sm transition-colors">
                                    <i class="fa-regular fa-copy"></i> ก๊อปปี้ส่งกลุ่ม ER / LINE
                                </button>
                            </div>
                        </div>
                    `,
                    width: '42rem',
                    confirmButtonText: 'ปิดหน้าต่าง',
                    confirmButtonColor: '#334155',
                    heightAuto: false,
                    scrollbarPadding: false,
                    customClass: { popup: 'rounded-3xl p-5 sm:p-6' }
                });
            })
            .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'เชื่อมต่อ AI ไม่สำเร็จ',
                    text: 'กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ตแล้วลองใหม่อีกครั้ง',
                    customClass: { popup: 'rounded-3xl' }
                });
            });
        };

        function openExecutiveAiModal() {
            // แถบทางลัดพิเศษเมื่อเปิดหน้าต่างหุ่นยนต์จากหน้า "ดูผล MEWS"
            let mewsContextHtml = '';
            if (ksvrAiMeta.mewsContext && ksvrAiMeta.mewsContext.is_mews_show) {
                const ctx = ksvrAiMeta.mewsContext;
                const safeFull = escapeAiHtml(ctx.patient_name);
                const safeShort = escapeAiHtml(ctx.short_name || ctx.patient_name);
                const sbarBtnHtml = ctx.has_sbar_api
                    ? `<button type="button" onclick="openSbarHandoverModal(${ctx.mews_id})" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[11px] font-black shadow-sm transition-colors inline-flex items-center gap-1.5"><i class="fa-solid fa-file-medical"></i> สรุปส่งเวร SBAR 1 คลิก</button>`
                    : '';

                mewsContextHtml = `
                    <div class="mb-3 p-3 rounded-2xl bg-emerald-50/80 border border-emerald-200 space-y-2">
                        <div class="text-[11px] font-black text-emerald-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-stethoscope text-emerald-600"></i>
                            <span>เคสที่เปิดดูอยู่ตอนนี้: ${safeFull}</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            ${sbarBtnHtml}
                            <button type="button" data-q="ขอดูรายการยา e-MAR ทั้งหมดของ ${safeShort}" onclick="setExecutiveQuickPrompt(this.dataset.q)" class="px-2.5 py-1.5 bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl text-[11px] font-bold transition-colors">
                                💊 เช็กยา e-MAR รายนี้
                            </button>
                            <button type="button" data-q="ขอดูคิวนัดพบแพทย์ของ ${safeShort}" onclick="setExecutiveQuickPrompt(this.dataset.q)" class="px-2.5 py-1.5 bg-white hover:bg-sky-100 text-sky-800 border border-sky-200 rounded-xl text-[11px] font-bold transition-colors">
                                📅 เช็กนัดแพทย์รายนี้
                            </button>
                            <button type="button" data-q="ขอดูประวัติแยกนอนห้องพยาบาลของ ${safeShort}" onclick="setExecutiveQuickPrompt(this.dataset.q)" class="px-2.5 py-1.5 bg-white hover:bg-teal-100 text-teal-800 border border-teal-200 rounded-xl text-[11px] font-bold transition-colors">
                                🛌 เช็กแยกนอนรายนี้
                            </button>
                        </div>
                    </div>
                `;
            }

            // ปุ่มคำถามด่วนเฉพาะสิทธิ์ PDPA และ Admin
            const chronicBtnHtml = ksvrAiMeta.canViewChronic
                ? `<button type="button" onclick="setExecutiveQuickPrompt('ขอดูรายชื่อพลทหารที่มีโรคประจำตัวทั้งหมดในระบบ')" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 rounded-lg text-[11px] font-bold transition-colors">🔒 ทหารที่มีโรคประจำตัว</button>`
                : '';

            const adminOnlineBtnHtml = ksvrAiMeta.isAdmin
                ? `<button type="button" onclick="setExecutiveQuickPrompt('ตอนนี้มีใครออนไลน์อยู่บ้าง และวันนี้มีการลบข้อมูลอะไรในระบบไหม')" class="px-2.5 py-1.5 bg-emerald-100/80 hover:bg-emerald-200 text-emerald-800 border border-emerald-300 rounded-lg text-[11px] font-bold transition-colors">🟢 คนออนไลน์ &amp; Audit Log วันนี้</button>`
                : '';

            Swal.fire({
                title: '<div class="text-lg md:text-2xl font-black text-indigo-800 flex items-center justify-center gap-2"><i class="fa-solid fa-wand-magic-sparkles text-indigo-500"></i> KSVR AI Data Analyzer</div>',
                html: `
                    <div class="mt-2 text-left">
                        ${mewsContextHtml}

                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2"><i class="fa-solid fa-bolt text-amber-500 mr-1"></i> แตะเลือกคำถามด่วน (ครบ 11 โหมด) หรือพิมพ์คำถามด้านล่าง:</p>
                        <div class="flex flex-wrap gap-1.5 mb-3 max-h-[155px] overflow-y-auto pr-1">
                            <button type="button" onclick="setExecutiveQuickPrompt('ตอนนี้มีหน่วยไหนเสี่ยงโรคระบาดหรือพบกลุ่มก้อนไข้สูงบ้าง')" class="px-2.5 py-1.5 bg-rose-100/80 hover:bg-rose-200 text-rose-800 border border-rose-300 rounded-lg text-[11px] font-black transition-colors">
                                📡 เรดาร์เฝ้าระวังโรคระบาด
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('ขอดูคิวผู้ป่วยที่กำลังรอตรวจในห้องฉุกเฉิน ER วันนี้ทั้งหมด')" class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg text-[11px] font-bold transition-colors">
                                🚨 คิวห้องฉุกเฉิน (ER) วันนี้
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('ขอดูคิวผู้ป่วยนอก OPD ที่รอตรวจวันนี้ทั้งหมด')" class="px-2.5 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 rounded-lg text-[11px] font-bold transition-colors">
                                🩺 คิวผู้ป่วยนอก (OPD) วันนี้
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('สรุปรายชื่อผู้ป่วยที่ถูก Admit นอน รพ. หรือส่งต่อ Refer ทั้งหมดในระบบ')" class="px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-[11px] font-bold transition-colors">
                                🏥 ผู้ป่วย Admit / ส่งต่อ รพ.
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('รายงานเจ็บป่วยประจำวัน มีใครป่วยโควิด ไข้หวัดใหญ่ หรือโรคลมร้อนบ้าง')" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg text-[11px] font-bold transition-colors">
                                🦠 โควิด / ไข้หวัดใหญ่ / ลมร้อน
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('ขอดูรายชื่อเคสสุขภาพจิตและจิตเวชทั้งหมดในรายงานเจ็บป่วยประจำวัน')" class="px-2.5 py-1.5 bg-fuchsia-50 hover:bg-fuchsia-100 text-fuchsia-700 border border-fuchsia-200 rounded-lg text-[11px] font-bold transition-colors">
                                🧠 เคสสุขภาพจิต / จิตเวช
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('ขณะนี้มีพลทหารคนไหนแยกนอนที่ห้องพยาบาลหน่วยฝึกหรือฝากนอน รพ. บ้าง')" class="px-2.5 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 rounded-lg text-[11px] font-bold transition-colors">
                                🛌 ห้องพยาบาล / ฝากนอน รพ.
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('ขอดูคิวนัดพบแพทย์ของพลทหารวันนี้และพรุ่งนี้ทั้งหมด')" class="px-2.5 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 rounded-lg text-[11px] font-bold transition-colors">
                                📅 คิวนัดพบแพทย์ (วันนี้/พรุ่งนี้)
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('มีพลทหารคนไหนต้องรับประทานยาต่อเนื่องหรือยาจิตเวชในระบบ e-MAR บ้าง')" class="px-2.5 py-1.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 border border-cyan-200 rounded-lg text-[11px] font-bold transition-colors">
                                💊 เช็กรายชื่อรับประทานยา (e-MAR)
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('ขอดูบันทึกสัญญาณชีพ 06:00 และ 18:00 ของวันนี้ทั้งหมด')" class="px-2.5 py-1.5 bg-pink-50 hover:bg-pink-100 text-pink-700 border border-pink-200 rounded-lg text-[11px] font-bold transition-colors">
                                🌡️ สัญญาณชีพ 06:00 / 18:00
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('ขอดูรายชื่อพลทหารที่อยู่ในทะเบียนเอกสารนำปลดทั้งหมดในระบบ')" class="px-2.5 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-lg text-[11px] font-bold transition-colors">
                                🎖️ เอกสารนำปลดทหาร
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('ขอดูผลคัดกรองทหารใหม่ที่อยู่ในกลุ่มเสี่ยงสีเหลืองและสีแดงทั้งหมด')" class="px-2.5 py-1.5 bg-orange-50 hover:bg-orange-100 text-orange-700 border border-orange-200 rounded-lg text-[11px] font-bold transition-colors">
                                📋 คัดกรองกลุ่มสีเหลือง/แดง
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('มีพลทหารคนไหนบ้างที่ยังไม่มี HN หรือยังไม่ซิงค์สิทธิการรักษา')" class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 rounded-lg text-[11px] font-bold transition-colors">
                                🛡️ ยังไม่ซิงค์สิทธิ / HN
                            </button>
                            <button type="button" onclick="setExecutiveQuickPrompt('วันนี้มีผู้ป่วยคนไหนมาทำแผล ล้างแผล หรือตัดไหมบ้าง')" class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-[11px] font-bold transition-colors">
                                🩹 เคสมาทำแผล / หัตถการ
                            </button>
                            ${chronicBtnHtml}
                            ${adminOnlineBtnHtml}
                        </div>

                        <textarea id="ai_executive_prompt" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-sm text-slate-800 focus:ring-indigo-500 focus:border-indigo-500 min-h-[78px]" placeholder="พิมพ์คำถามอิสระ เช่น 'พลทหาร ร.3 พัน.1 ที่มีไข้ 38 ขึ้นไป', 'ใครใช้สิทธิบัตรทองบ้าง', 'เกณฑ์คะแนน MEWS คิดยังไง' (กด Ctrl+Enter เพื่อส่งทันที)"></textarea>

                        <div id="ai_result_box" class="mt-4 hidden p-4 bg-white border border-indigo-100 rounded-xl shadow-sm max-h-[340px] overflow-y-auto"></div>
                    </div>
                `,
                width: '44rem',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-magnifying-glass mr-1"></i> ให้ AI วิเคราะห์ข้อมูล',
                cancelButtonText: 'ปิดหน้าต่าง',
                showLoaderOnConfirm: true,
                didOpen: () => {
                    const ta = document.getElementById('ai_executive_prompt');
                    if (ta) {
                        ta.addEventListener('keydown', (e) => {
                            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                                e.preventDefault();
                                Swal.clickConfirm();
                            }
                        });
                    }
                },
                customClass: {
                    popup: 'rounded-[2rem] shadow-2xl border border-slate-100 p-5 sm:p-6',
                    confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-5 py-2.5 shadow-md',
                    cancelButton: 'bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl px-5 py-2.5'
                },
                preConfirm: () => {
                    const promptText = document.getElementById('ai_executive_prompt').value.trim();
                    if (!promptText) {
                        Swal.showValidationMessage('กรุณาแตะเลือกคำถามด่วน หรือพิมพ์คำถามก่อนครับ');
                        return false;
                    }

                    return fetch('{{ route("backend.ai.ask_executive") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ text: promptText })
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        const resultBox = document.getElementById('ai_result_box');
                        resultBox.classList.remove('hidden');

                        if (data.status === 'success') {
                            resultBox.innerHTML = data.html;
                        } else {
                            resultBox.innerHTML = `<div class="text-rose-500 font-bold"><i class="fa-solid fa-circle-exclamation"></i> ${escapeAiHtml(data.message)}</div>`;
                        }

                        return false;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`เชื่อมต่อไม่สำเร็จ: ${error}`);
                    });
                }
            });
        }
    </script>

    <!-- 📱 PWA Install Banner Script -->
    <script>
        let deferredPrompt = null;
        const isPwaStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;

            if (isPwaStandalone) return;

            const dismissedTime = localStorage.getItem('ksvr_pwa_dismissed');
            const now = Date.now();
            if (!dismissedTime || (now - Number(dismissedTime) > 24 * 60 * 60 * 1000)) {
                document.getElementById('pwa-install-banner')?.classList.remove('hidden');
            }
        });

        window.addEventListener('appinstalled', () => {
            deferredPrompt = null;
            document.getElementById('pwa-install-banner')?.classList.add('hidden');
        });

        async function installPwaApp() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    document.getElementById('pwa-install-banner')?.classList.add('hidden');
                }
                deferredPrompt = null;
                return;
            }

            if (typeof window.openPwaInstallModal === 'function') {
                window.openPwaInstallModal();
            }
        }

        function dismissPwaBanner() {
            document.getElementById('pwa-install-banner')?.classList.add('hidden');
            localStorage.setItem('ksvr_pwa_dismissed', String(Date.now()));
        }
    </script>

    <!-- 🔐 Web Push Subscription Script -->
    <script>
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        window.registerServerWebPushSubscription = async function() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
            if (Notification.permission !== 'granted') return;

            const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]')?.getAttribute('content');
            if (!vapidPublicKey) return;

            try {
                const registration = await navigator.serviceWorker.ready;
                let subscription = await registration.pushManager.getSubscription();

                if (!subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                    });
                }

                const subJson = subscription.toJSON();
                await fetch("{{ route('backend.push.subscribe') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        endpoint: subJson.endpoint,
                        keys: subJson.keys || {},
                        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0]
                    })
                });
            } catch (err) {
                console.warn('WebPush Subscribe Warning:', err);
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            if ('Notification' in window && Notification.permission === 'granted') {
                window.registerServerWebPushSubscription();
            }

            const notifyBtn = document.getElementById('btn-enable-browser-notify');
            if (notifyBtn && 'Notification' in window && Notification.permission === 'granted') {
                notifyBtn.onclick = function(e) {
                    e.stopPropagation();
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'info',
                        title: 'กำลังส่งแจ้งเตือนจากเซิร์ฟเวอร์ใน 3 วินาที...',
                        html: '<span class="text-xs text-slate-600">ลองกดล็อกหน้าจอมือถือหรือพับหน้าต่างตอนนี้ได้เลยครับ!</span>',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    setTimeout(() => {
                        fetch("{{ route('backend.push.test') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                    }, 3000);
                };
            }
        });
    </script>

    @include('partials.pwa_install_modal')
    @include('partials.workflow_overview_modal')
</body>
</html>