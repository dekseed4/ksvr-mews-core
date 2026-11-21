@extends('layouts.backend')

@section('title', 'รายชื่อผู้ใช้งานระบบ - KSVR Hospital')
@section('page_header', 'รายชื่อผู้ใช้งานระบบ (Members)')

@section('content')
@php
    $activeTab = $activeTab ?? request('role', 'all');

    // พจนานุกรมแปลสิทธิ์ (ประกาศครั้งเดียวด้านบน ไม่ต้องสร้างซ้ำทุกรอบในลูป)
    $roleDict = [
        'admin'         => ['name' => 'ผู้ดูแลระบบ',         'color' => 'bg-purple-50 text-purple-700 border-purple-200',  'icon' => 'fa-shield-halved'],
        'executive'     => ['name' => 'ผู้บริหาร',           'color' => 'bg-slate-100 text-slate-700 border-slate-200',    'icon' => 'fa-user-tie'],
        'er_nurse'      => ['name' => 'พยาบาล ER',           'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200','icon' => 'fa-user-nurse'],
        'opd_nurse'     => ['name' => 'พยาบาล OPD',          'color' => 'bg-teal-50 text-teal-700 border-teal-200',        'icon' => 'fa-stethoscope'],
        'medic'         => ['name' => 'เสนารักษ์',           'color' => 'bg-blue-50 text-blue-700 border-blue-200',        'icon' => 'fa-walkie-talkie'],
        'registration'  => ['name' => 'เจ้าหน้าที่ทะเบียน',   'color' => 'bg-indigo-50 text-indigo-700 border-indigo-200',  'icon' => 'fa-id-card-clip'],
        'medical_staff' => ['name' => 'เจ้าหน้าที่เวชกรรม',  'color' => 'bg-cyan-50 text-cyan-800 border-cyan-200',        'icon' => 'fa-kit-medical'],
        'screening'     => ['name' => 'จุดคัดกรอง',          'color' => 'bg-amber-50 text-amber-800 border-amber-200',     'icon' => 'fa-clipboard-check'],
    ];

    $roleTabs = [
        'all'           => ['label' => 'ทั้งหมด',            'icon' => 'fa-users',           'active' => 'bg-slate-800 text-white border-slate-800 shadow-sm',                                'hover' => 'hover:bg-slate-50'],
        'admin'         => ['label' => 'ผู้ดูแลระบบ',         'icon' => 'fa-shield-halved',   'active' => 'bg-purple-100 text-purple-800 border-purple-400 ring-2 ring-purple-500/30',          'hover' => 'hover:bg-purple-50 hover:text-purple-700'],
        'executive'     => ['label' => 'ผู้บริหาร',           'icon' => 'fa-user-tie',        'active' => 'bg-slate-200 text-slate-900 border-slate-400 ring-2 ring-slate-500/30',              'hover' => 'hover:bg-slate-100 hover:text-slate-800'],
        'er_nurse'      => ['label' => 'พยาบาล ER',           'icon' => 'fa-user-nurse',      'active' => 'bg-emerald-100 text-emerald-800 border-emerald-400 ring-2 ring-emerald-500/30',      'hover' => 'hover:bg-emerald-50 hover:text-emerald-700'],
        'opd_nurse'     => ['label' => 'พยาบาล OPD',          'icon' => 'fa-stethoscope',     'active' => 'bg-teal-100 text-teal-800 border-teal-400 ring-2 ring-teal-500/30',                  'hover' => 'hover:bg-teal-50 hover:text-teal-700'],
        'medic'         => ['label' => 'เสนารักษ์',           'icon' => 'fa-truck-medical',   'active' => 'bg-blue-100 text-blue-800 border-blue-400 ring-2 ring-blue-500/30',                  'hover' => 'hover:bg-blue-50 hover:text-blue-700'],
        'registration'  => ['label' => 'ทะเบียน',            'icon' => 'fa-id-card-clip',    'active' => 'bg-indigo-100 text-indigo-800 border-indigo-400 ring-2 ring-indigo-500/30',          'hover' => 'hover:bg-indigo-50 hover:text-indigo-700'],
        'medical_staff' => ['label' => 'เจ้าหน้าที่เวชกรรม',  'icon' => 'fa-kit-medical',     'active' => 'bg-cyan-100 text-cyan-800 border-cyan-400 ring-2 ring-cyan-500/30',                  'hover' => 'hover:bg-cyan-50 hover:text-cyan-700'],
        'screening'     => ['label' => 'จุดคัดกรอง',          'icon' => 'fa-clipboard-check', 'active' => 'bg-amber-100 text-amber-800 border-amber-400 ring-2 ring-amber-500/30',              'hover' => 'hover:bg-amber-50 hover:text-amber-700'],
    ];

    // นับยอดคนกำลังออนไลน์อย่างปลอดภัย (Query เฉพาะตาราง users ฝั่ง KSVR มี Index บน last_seen_at)
    $onlineUsersCount = \Illuminate\Support\Facades\Schema::hasColumn('users', 'last_seen_at')
        ? \App\Models\User::where('last_seen_at', '>=', now()->subMinutes(5))->count()
        : 0;
@endphp

<style>
    .ksvr-no-scrollbar::-webkit-scrollbar { display: none; }
    .ksvr-no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div class="max-w-7xl mx-auto space-y-5 pb-12">

    <!-- 🟢 1. กรอบหลักจัดการผู้ใช้งาน -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        <!-- แถบหัวเรื่อง + ยอดออนไลน์ + ปุ่มเพิ่มผู้ใช้งาน -->
        <div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h3 class="font-black text-slate-800 dark:text-white text-base sm:text-lg flex items-center gap-2">
                    <span class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-base shrink-0">
                        <i class="fa-solid fa-users"></i>
                    </span>
                    <span>ผู้ใช้งานในระบบ</span>
                </h3>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    พบ {{ number_format($users->total()) }} บัญชี
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    ออนไลน์ {{ number_format($onlineUsersCount) }} คน
                </span>
            </div>

            <a href="{{ route('backend.users.create') }}"
               class="w-full sm:w-auto justify-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold rounded-2xl shadow-sm transition-all flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-user-plus"></i> + เพิ่มผู้ใช้งานใหม่
            </a>
        </div>

        <!-- ✅ แจ้งเตือนเมื่อบันทึกสำเร็จ -->
        @if(session('success'))
            <div class="mx-4 sm:mx-6 mt-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-2xl text-xs sm:text-sm font-semibold flex items-start gap-2.5">
                <i class="fa-solid fa-circle-check mt-0.5 text-emerald-500 shrink-0"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- 🔑 แจ้งเตือนรหัสผ่านชั่วคราว (ปรับ Responsive เต็มจอ + ปุ่มก๊อปปี้ 1 คลิก) -->
        @if(session('temp_password'))
            <div class="mx-4 sm:mx-6 mt-4 bg-amber-50 dark:bg-amber-950/40 border-2 border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 p-4 sm:p-5 rounded-2xl space-y-3 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2 font-black text-sm">
                        <i class="fa-solid fa-key text-amber-600"></i>
                        <span>โปรดจดหรือส่งรหัสผ่านชั่วคราวนี้ให้ผู้ใช้งาน</span>
                    </div>
                    <span class="text-[11px] font-bold text-amber-700 dark:text-amber-400 bg-amber-100/80 dark:bg-amber-900/50 px-2.5 py-0.5 rounded-full self-start sm:self-auto">
                        แสดงเพียงครั้งเดียว
                    </span>
                </div>

                <div class="bg-white dark:bg-slate-900 px-4 py-3 rounded-xl border border-amber-200 dark:border-amber-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6 font-mono text-sm sm:text-base">
                        <div>
                            <span class="text-slate-400 text-xs font-sans font-bold mr-1.5">Username:</span>
                            <span class="font-black text-emerald-700 dark:text-emerald-400 select-all">{{ session('temp_username') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-xs font-sans font-bold mr-1.5">Password:</span>
                            <span class="font-black text-rose-600 dark:text-rose-400 select-all">{{ session('temp_password') }}</span>
                        </div>
                    </div>

                    <button type="button"
                            data-username="{{ session('temp_username') }}"
                            data-password="{{ session('temp_password') }}"
                            onclick="copyTempCredentials(this)"
                            class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-black inline-flex items-center justify-center gap-1.5 shadow-sm transition-colors shrink-0">
                        <i class="fa-regular fa-copy"></i> ก๊อปปี้ส่งให้ผู้ใช้
                    </button>
                </div>
            </div>
        @endif

        <!-- 🌟 แถบ Tab กรองตามสิทธิ์ (ปัดซ้าย-ขวาได้บนมือถือ / วางเต็มแนวบนคอมพิวเตอร์) -->
        <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between md:hidden text-[11px] font-bold text-slate-400 mb-2">
                <span><i class="fa-solid fa-filter text-emerald-500 mr-1"></i>กรองตามบทบาทสิทธิ์</span>
                <span>ปัดเลื่อนได้ →</span>
            </div>

            <div id="user-role-tabs" class="ksvr-no-scrollbar flex flex-nowrap md:flex-wrap items-center gap-2 overflow-x-auto pb-1 md:pb-0 scroll-smooth">
                @foreach($roleTabs as $tabKey => $tab)
                    @php $isCurrent = ($activeTab === $tabKey); @endphp
                    <a href="{{ route('backend.users.index', ['role' => $tabKey]) }}"
                       data-role-tab="{{ $tabKey }}"
                       class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap border transition-all inline-flex items-center gap-1.5 shrink-0 {{ $isCurrent ? $tab['active'] : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 ' . $tab['hover'] }}">
                        <i class="fa-solid {{ $tab['icon'] }} text-xs"></i>
                        <span>{{ $tab['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- =========================================================== -->
        <!-- 📱 โหมดมือถือ: การ์ดรายชื่อผู้ใช้งาน (อ่านง่ายเต็มจอ ปุ่มกดพอดีนิ้ว) -->
        <!-- =========================================================== -->
        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($users as $index => $user)
                @php
                    $isOnline = method_exists($user, 'isOnline') ? $user->isOnline() : false;
                    $rawRoles = $user->roles ?? $user->role ?? [];
                    $rolesList = is_string($rawRoles) ? (json_decode($rawRoles, true) ?: []) : (array) $rawRoles;
                    $safeDisplayName = trim(($user->rank ?? '') . ' ' . ($user->name ?? ''));
                @endphp

                <div class="p-4 border-l-4 {{ $isOnline ? 'border-l-emerald-500 bg-emerald-50/15 dark:bg-emerald-950/10' : 'border-l-slate-200 dark:border-l-slate-700 bg-white dark:bg-slate-800' }} space-y-3">
                    
                    <!-- แถว 1: Avatar + ชื่อ + สถานะออนไลน์ -->
                    <div class="flex items-start justify-between gap-2.5">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="relative shrink-0">
                                <div class="w-11 h-11 rounded-2xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-black text-lg border border-emerald-200 dark:border-emerald-800 shadow-sm">
                                    {{ mb_substr($user->name ?? 'U', 0, 1) }}
                                </div>
                                <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-800 {{ $isOnline ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                            </div>

                            <div class="min-w-0">
                                <div class="font-black text-slate-900 dark:text-white text-sm sm:text-base leading-snug truncate">
                                    {{ $safeDisplayName }}
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                    <span class="bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-md text-[11px] font-mono font-bold">
                                        @ {{ $user->username }}
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-400">#{{ $users->firstItem() + $index }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0">
                            @if($isOnline)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span> ออนไลน์
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                                    ออฟไลน์
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- แถว 2: ป้ายสิทธิ์การใช้งาน + หน่วยฝึก/ผลัด -->
                    <div class="flex flex-wrap gap-1.5">
                        @forelse($rolesList as $roleKey)
                            @if(isset($roleDict[$roleKey]))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-bold border {{ $roleDict[$roleKey]['color'] }}">
                                    <i class="fa-solid {{ $roleDict[$roleKey]['icon'] }} text-[10px] opacity-80"></i>
                                    {{ $roleDict[$roleKey]['name'] }}
                                </span>
                            @endif
                        @empty
                            <span class="text-slate-400 text-xs italic">- ไม่มีสิทธิ์ -</span>
                        @endforelse

                        @if($user->militaryUnit)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-bold border bg-indigo-50 text-indigo-700 border-indigo-200">
                                <i class="fa-solid fa-tent text-[10px] opacity-80"></i>
                                {{ $user->militaryUnit->short_name ?? $user->militaryUnit->name }}
                                @if($user->intake_batch)
                                    <span class="text-indigo-400">|</span> ผลัด {{ $user->intake_batch }}
                                @endif
                            </span>
                        @endif
                    </div>

                    <!-- แถว 3: กล่องข้อมูลติดต่อ & เวลาเข้าใช้งานล่าสุด -->
                    <div class="p-3 rounded-2xl bg-slate-50/90 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 text-xs space-y-1.5">
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300 min-w-0">
                            <i class="fa-regular fa-envelope text-slate-400 w-4 text-center shrink-0"></i>
                            <span class="truncate">{{ $user->email ?: '-' }}</span>
                        </div>
                        @if($user->phone)
                            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                <i class="fa-solid fa-phone text-emerald-500 w-4 text-center shrink-0 text-[11px]"></i>
                                <a href="tel:{{ $user->phone }}" class="font-mono font-bold text-emerald-700 dark:text-emerald-400 hover:underline">{{ $user->phone }}</a>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-center justify-between gap-1 pt-1 border-t border-slate-200/60 dark:border-slate-700/60 text-[11px] text-slate-400">
                            <span><i class="fa-regular fa-clock mr-1"></i>{{ $user->last_seen_label ?? '-' }}</span>
                            <span>เพิ่มเมื่อ {{ $user->created_at->format('d/m/Y') }}</span>
                        </div>
                    </div>

                    <!-- แถว 4: ปุ่มจัดการ 3 ปุ่ม (เต็มแถว แตะง่ายไม่พลาดปุ่ม) -->
                    <div class="grid grid-cols-3 gap-2 pt-0.5">
                        <form id="reset-form-mobile-{{ $user->id }}" action="{{ route('backend.users.reset_password', $user->id) }}" method="POST">
                            @csrf
                            <button type="button"
                                    onclick="confirmAction(event, 'รีเซ็ตรหัสผ่าน?', 'ระบบจะสุ่มรหัสผ่านใหม่ให้คุณ {{ addslashes($user->name) }}', 'warning', 'reset-form-mobile-{{ $user->id }}')"
                                    class="w-full py-2 px-2 rounded-xl bg-amber-50 hover:bg-amber-500 text-amber-700 hover:text-white border border-amber-200 text-xs font-bold inline-flex items-center justify-center gap-1 transition-colors">
                                <i class="fa-solid fa-key text-[11px]"></i> รีเซ็ต
                            </button>
                        </form>

                        <a href="{{ route('backend.users.edit', $user->id) }}"
                           class="w-full py-2 px-2 rounded-xl bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white border border-blue-200 text-xs font-bold inline-flex items-center justify-center gap-1 transition-colors">
                            <i class="fa-solid fa-pen-to-square text-[11px]"></i> แก้ไข
                        </a>

                        <form id="delete-form-mobile-{{ $user->id }}" action="{{ route('backend.users.destroy', $user->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="button"
                                    onclick="confirmAction(event, 'ยืนยันการลบข้อมูล', 'คุณต้องการลบบัญชี {{ addslashes($user->name) }} ใช่หรือไม่? ข้อมูลจะไม่สามารถกู้คืนได้!', 'error', 'delete-form-mobile-{{ $user->id }}')"
                                    class="w-full py-2 px-2 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 text-xs font-bold inline-flex items-center justify-center gap-1 transition-colors">
                                <i class="fa-solid fa-trash-can text-[11px]"></i> ลบ
                            </button>
                        </form>
                    </div>

                </div>
            @empty
                <div class="px-6 py-14 text-center text-slate-400">
                    <i class="fa-regular fa-folder-open text-4xl mb-3 block opacity-40"></i>
                    <p class="font-bold text-slate-600 dark:text-slate-300 text-sm">ยังไม่มีข้อมูลผู้ใช้งานในหมวดที่เลือก</p>
                </div>
            @endforelse
        </div>

        <!-- =========================================================== -->
        <!-- 💻 โหมดคอมพิวเตอร์: ตารางมาตรฐาน ล็อกสัดส่วนอ่านง่ายไม่บีบข้อความ -->
        <!-- =========================================================== -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[920px] text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/90 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-100 dark:border-slate-700 whitespace-nowrap">
                        <th class="px-5 py-3.5 font-bold w-14 text-center">#</th>
                        <th class="px-5 py-3.5 font-bold">ชื่อ-นามสกุล &amp; สิทธิ์การใช้งาน</th>
                        <th class="px-5 py-3.5 font-bold">Username</th>
                        <th class="px-5 py-3.5 font-bold">อีเมล / เบอร์โทร</th>
                        <th class="px-5 py-3.5 font-bold">วันที่เพิ่มเข้าระบบ</th>
                        <th class="px-5 py-3.5 font-bold text-center w-36">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($users as $index => $user)
                        @php
                            $isOnline = method_exists($user, 'isOnline') ? $user->isOnline() : false;
                            $rawRoles = $user->roles ?? $user->role ?? [];
                            $rolesList = is_string($rawRoles) ? (json_decode($rawRoles, true) ?: []) : (array) $rawRoles;
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors">
                            <td class="px-5 py-4 text-slate-400 font-semibold text-center">{{ $users->firstItem() + $index }}</td>

                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3.5">
                                    <!-- Avatar + จุดสถานะออนไลน์ -->
                                    <div class="relative shrink-0 mt-0.5">
                                        <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-black text-lg shadow-sm border border-emerald-200 dark:border-emerald-800">
                                            {{ mb_substr($user->name ?? 'U', 0, 1) }}
                                        </div>
                                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white dark:border-slate-800 {{ $isOnline ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                                    </div>

                                    <!-- ชื่อ + ป้ายสถานะออนไลน์ + ป้ายสิทธิ์ -->
                                    <div>
                                        <div class="text-slate-800 dark:text-white font-bold text-base flex flex-wrap items-center gap-2 mb-1.5">
                                            <span>{{ $user->rank }} {{ $user->name }}</span>
                                            @if($isOnline)
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                                    กำลังออนไลน์
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-600">
                                                    <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-500"></span>
                                                    {{ $user->last_seen_label }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap gap-1.5">
                                            @forelse($rolesList as $roleKey)
                                                @if(isset($roleDict[$roleKey]))
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[11px] font-bold border shadow-sm {{ $roleDict[$roleKey]['color'] }}">
                                                        <i class="fa-solid {{ $roleDict[$roleKey]['icon'] }} opacity-75"></i>
                                                        {{ $roleDict[$roleKey]['name'] }}
                                                    </span>
                                                @endif
                                            @empty
                                                <span class="text-slate-400 text-xs italic">- ไม่มีสิทธิ์ -</span>
                                            @endforelse

                                            @if($user->militaryUnit)
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[11px] font-bold border shadow-sm bg-indigo-50 text-indigo-700 border-indigo-200">
                                                    <i class="fa-solid fa-tent opacity-75"></i> {{ $user->militaryUnit->short_name ?? $user->militaryUnit->name }}
                                                    @if($user->intake_batch)
                                                        <span class="text-indigo-400">|</span> ผลัด {{ $user->intake_batch }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 px-2.5 py-1 rounded-lg text-xs font-mono font-bold">
                                    {{ $user->username }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div class="text-slate-700 dark:text-slate-200 font-medium">{{ $user->email }}</div>
                                @if($user->phone)
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                        <i class="fa-solid fa-phone text-emerald-500 text-[10px]"></i> {{ $user->phone }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-5 py-4 text-slate-500 dark:text-slate-400 whitespace-nowrap font-mono text-xs">
                                {{ $user->created_at->format('d/m/Y H:i') }}
                            </td>

                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <div class="inline-flex items-center justify-center gap-1.5">
                                    <form id="reset-form-desktop-{{ $user->id }}" action="{{ route('backend.users.reset_password', $user->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="button"
                                                onclick="confirmAction(event, 'รีเซ็ตรหัสผ่าน?', 'ระบบจะสุ่มรหัสผ่านใหม่ให้คุณ {{ addslashes($user->name) }}', 'warning', 'reset-form-desktop-{{ $user->id }}')"
                                                class="w-8 h-8 rounded-xl bg-amber-50 hover:bg-amber-500 text-amber-600 hover:text-white border border-amber-200 flex items-center justify-center transition-colors shadow-sm"
                                                title="รีเซ็ตรหัสผ่านชั่วคราว">
                                            <i class="fa-solid fa-key text-xs"></i>
                                        </button>
                                    </form>

                                    <a href="{{ route('backend.users.edit', $user->id) }}"
                                       class="w-8 h-8 rounded-xl bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white border border-blue-200 flex items-center justify-center transition-colors shadow-sm"
                                       title="แก้ไข">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </a>

                                    <form id="delete-form-desktop-{{ $user->id }}" action="{{ route('backend.users.destroy', $user->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                onclick="confirmAction(event, 'ยืนยันการลบข้อมูล', 'คุณต้องการลบบัญชี {{ addslashes($user->name) }} ใช่หรือไม่? ข้อมูลจะไม่สามารถกู้คืนได้!', 'error', 'delete-form-desktop-{{ $user->id }}')"
                                                class="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white border border-rose-200 flex items-center justify-center transition-colors shadow-sm"
                                                title="ลบ">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center text-slate-400">
                                <i class="fa-regular fa-folder-open text-4xl mb-3 text-slate-300 block"></i>
                                <p class="font-bold text-slate-600 dark:text-slate-300">ยังไม่มีข้อมูลผู้ใช้งานในหมวดที่เลือก</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40">
                {{ $users->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

@section('scripts')
<script>
    // เลื่อนแท็บสิทธิ์ที่กำลังเลือกมาอยู่ตรงกลางอัตโนมัติบนจอมือถือ
    document.addEventListener('DOMContentLoaded', function () {
        if (window.innerWidth < 768) {
            const activeRoleBtn = document.querySelector('[data-role-tab="{{ $activeTab }}"]');
            if (activeRoleBtn) {
                activeRoleBtn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        }
    });

    // ก๊อปปี้ Username และรหัสผ่านชั่วคราว 1 คลิก (รองรับทั้ง HTTPS และ HTTP วงแลน รพ.)
    function copyTempCredentials(btn) {
        const u = btn.getAttribute('data-username') || '';
        const p = btn.getAttribute('data-password') || '';
        const text = `ข้อมูลเข้าสู่ระบบ KSVR MEWS\nUsername: ${u}\nPassword ชั่วคราว: ${p}\n(กรุณาเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก)`;

        const onDone = () => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> ก๊อปปี้แล้ว!';
            btn.classList.remove('bg-amber-500', 'hover:bg-amber-600');
            btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
            setTimeout(() => {
                btn.innerHTML = orig;
                btn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                btn.classList.add('bg-amber-500', 'hover:bg-amber-600');
            }, 1800);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(onDone);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); onDone(); } catch (e) {}
            document.body.removeChild(ta);
        }
    }
</script>
@endsection