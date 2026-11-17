@extends('layouts.backend')

@section('title', 'รายชื่อผู้ใช้งานระบบ - KSVR Hospital')
@section('page_header', 'รายชื่อผู้ใช้งานระบบ (Members)')

@section('content')
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        
        <!-- แถบด้านบนของตาราง -->
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-semibold text-slate-800">
                <i class="fa-solid fa-users text-emerald-500 mr-2"></i>ผู้ใช้งานทั้งหมดในระบบ
            </h3>
            <a href="{{ route('backend.users.create') }}" class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> เพิ่มผู้ใช้งาน
            </a>
        </div>

        <!-- แจ้งเตือนเมื่อมีการบันทึกสำเร็จ (ดึงจาก session) -->
        @if(session('success'))
            <div class="m-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-start gap-3">
                <i class="fa-solid fa-circle-check mt-1"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        <!-- แจ้งเตือนเมื่อสร้างยูสเซอร์และสุ่มรหัสผ่านสำเร็จ -->
        @if(session('temp_password'))
            <div class="m-4 bg-yellow-50 border border-yellow-300 text-yellow-800 px-6 py-4 rounded-xl flex flex-col gap-2 shadow-sm">
                <div class="flex items-center gap-2 font-semibold">
                    <i class="fa-solid fa-key text-yellow-600"></i>
                    <span>บัญชีถูกสร้างเรียบร้อยแล้ว! โปรดจดรหัสผ่านชั่วคราวนี้ให้ผู้ใช้งาน</span>
                </div>
                <div class="bg-white px-4 py-3 rounded-lg border border-yellow-200 font-mono text-lg flex items-center justify-between">
                    <div>
                        <span class="text-slate-500 text-sm mr-2">Username:</span> 
                        <span class="font-bold text-emerald-700 mr-6">{{ session('temp_username') }}</span>
                        <span class="text-slate-500 text-sm mr-2">Password:</span> 
                        <span class="font-bold text-red-600 select-all">{{ session('temp_password') }}</span>
                    </div>
                    <span class="text-xs text-yellow-600">(รหัสนี้จะแสดงเพียงครั้งเดียว)</span>
                </div>
            </div>
        @endif
        <!-- 🌟 เมนู Tab กรองผู้ใช้งานตามสิทธิ์ (Role Filter Tabs) -->
        <div class="px-6 pt-6 pb-2 flex flex-wrap gap-2 md:gap-3">
            <!-- Tab: ทั้งหมด -->
            <a href="{{ route('backend.users.index', ['role' => 'all']) }}" 
               class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'all' ? 'bg-slate-800 text-white shadow-md transform -translate-y-0.5' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300 shadow-sm' }}">
                <i class="fa-solid fa-users"></i> ทั้งหมด
            </a>

            <!-- Tab: ผู้ดูแลระบบ -->
            <a href="{{ route('backend.users.index', ['role' => 'admin']) }}" 
               class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'admin' ? 'bg-purple-100 text-purple-700 ring-2 ring-purple-500 shadow-sm transform -translate-y-0.5' : 'bg-white border border-slate-200 text-slate-600 hover:bg-purple-50 hover:text-purple-700 shadow-sm' }}">
                <i class="fa-solid fa-shield-halved"></i> ผู้ดูแลระบบ
            </a>

            <!-- Tab: ผู้บริหาร -->
            <a href="{{ route('backend.users.index', ['role' => 'executive']) }}" 
               class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'executive' ? 'bg-slate-200 text-slate-800 ring-2 ring-slate-500 shadow-sm transform -translate-y-0.5' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-800 shadow-sm' }}">
                <i class="fa-solid fa-user-tie"></i> ผู้บริหาร
            </a>

            <!-- Tab: พยาบาล ER (ใส่คลาส Tailwind ครบสมบูรณ์) -->
            <a href="{{ route('backend.users.index', ['role' => 'er_nurse']) }}" 
               class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'er_nurse' ? 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-500 shadow-sm transform -translate-y-0.5' : 'bg-white border border-slate-200 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 shadow-sm' }}">
                <i class="fa-solid fa-user-nurse"></i> พยาบาล ER
            </a>

            <!-- Tab: พยาบาล OPD -->
            <a href="{{ route('backend.users.index', ['role' => 'opd_nurse']) }}" 
               class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'opd_nurse' ? 'bg-teal-100 text-teal-700 ring-2 ring-teal-500 shadow-sm transform -translate-y-0.5' : 'bg-white border border-slate-200 text-slate-600 hover:bg-teal-50 hover:text-teal-700 shadow-sm' }}">
                <i class="fa-solid fa-stethoscope"></i> พยาบาล OPD
            </a>

            <!-- Tab: เสนารักษ์ -->
            <a href="{{ route('backend.users.index', ['role' => 'medic']) }}" 
               class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'medic' ? 'bg-blue-100 text-blue-700 ring-2 ring-blue-500 shadow-sm transform -translate-y-0.5' : 'bg-white border border-slate-200 text-slate-600 hover:bg-blue-50 hover:text-blue-700 shadow-sm' }}">
                <i class="fa-solid fa-truck-medical"></i> เสนารักษ์
            </a>

            <!-- Tab: ทะเบียน -->
            <a href="{{ route('backend.users.index', ['role' => 'registration']) }}" 
               class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'registration' ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500 shadow-sm transform -translate-y-0.5' : 'bg-white border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:text-indigo-700 shadow-sm' }}">
                <i class="fa-solid fa-id-card-clip"></i> ทะเบียน
            </a>

            <!-- Tab: เจ้าหน้าที่เวชกรรม -->
            <a href="{{ route('backend.users.index', ['role' => 'medical_staff']) }}" 
               class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 flex items-center gap-2 {{ $activeTab === 'medical_staff' ? 'bg-cyan-100 text-cyan-800 ring-2 ring-cyan-500 shadow-sm transform -translate-y-0.5' : 'bg-white border border-slate-200 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 shadow-sm' }}">
                <i class="fa-solid fa-kit-medical"></i> เจ้าหน้าที่เวชกรรม
            </a>
        </div>

        <!-- ส่วนของตาราง <div class="bg-white rounded-3xl ..."> ที่คุณมีอยู่แล้ว จะอยู่ต่อจากตรงนี้ครับ -->
        <!-- ตารางข้อมูล -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-200">
                        <th class="px-6 py-3 font-medium">#</th>
                        <th class="px-6 py-3 font-medium">ชื่อ-นามสกุล</th>
                        <th class="px-6 py-3 font-medium">Username</th>
                        <th class="px-6 py-3 font-medium">อีเมล</th>
                        <th class="px-6 py-3 font-medium">วันที่เพิ่มเข้าระบบ</th>
                        <th class="px-6 py-3 font-medium text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($users as $index => $user)
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 text-slate-500">{{ $users->firstItem() + $index }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    
                                    <!-- 🟢 1. ส่วนรูปโปรไฟล์ (Avatar) ดึงตัวอักษรตัวแรกของชื่อมาโชว์ -->
                                    <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-lg shrink-0 shadow-sm border border-emerald-200">
                                        {{ mb_substr($user->name ?? 'U', 0, 1) }}
                                    </div>

                                    <!-- 🟢 2. ส่วนชื่อและป้ายสิทธิ์ -->
                                    <div>
                                        <!-- ชื่อ-นามสกุล และยศ -->
                                        <div class="text-slate-800 font-bold text-base mb-1.5">
                                            {{ $user->rank }} {{ $user->name }}
                                        </div>

                                        <!-- ลอจิกป้ายสิทธิ์การใช้งาน (Multi-Role) -->
                                        @php
                                            // ดึงข้อมูลสิทธิ์แบบ Bulletproof
                                            $rawRoles = $user->roles ?? $user->role ?? [];
                                            $rolesList = is_string($rawRoles) ? json_decode($rawRoles, true) : (array) $rawRoles;
                                            
                                            // พจนานุกรมแปลสิทธิ์
                                            $roleDict = [
                                                'admin'     => ['name' => 'ผู้ดูแลระบบ', 'color' => 'bg-purple-50 text-purple-700 border-purple-200', 'icon' => 'fa-shield-halved'],
                                                'executive' => ['name' => 'ผู้บริหาร', 'color' => 'bg-slate-50 text-slate-700 border-slate-200', 'icon' => 'fa-user-tie'],
                                                'er_nurse'  => ['name' => 'พยาบาล ER', 'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'fa-user-nurse'],
                                                
                                                // 🌟 เพิ่มป้ายสีให้พยาบาล OPD ตรงนี้
                                                'opd_nurse' => ['name' => 'พยาบาล OPD', 'color' => 'bg-teal-50 text-teal-700 border-teal-200', 'icon' => 'fa-stethoscope'],
                                                
                                                'medic'     => ['name' => 'เสนารักษ์', 'color' => 'bg-blue-50 text-blue-700 border-blue-200', 'icon' => 'fa-walkie-talkie'],
                                                'registration' => ['name' => 'เจ้าหน้าที่ทะเบียน', 'color' => 'bg-yellow-50 text-yellow-700 border-yellow-200', 'icon' => 'fa-id-card-clip'],
                                                'medical_staff' => ['name' => 'เจ้าหน้าที่เวชกรรม', 'color' => 'bg-cyan-50 text-cyan-700 border-cyan-200', 'icon' => 'fa-kit-medical'],
                                            ];
                                        @endphp

                                        <!-- แสดงป้ายสิทธิ์เรียงกันแบบ Flex Wrap -->
                                        <div class="flex flex-wrap gap-1.5 mt-1"> <!-- เพิ่ม mt-1 เพื่อเว้นระยะนิดนึง -->
                                            @forelse($rolesList as $roleKey)
                                                <!-- (โค้ดลอจิกป้ายสิทธิ์ของคุณศุภกิจที่เดิม) -->
                                                @if(isset($roleDict[$roleKey]))
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold border shadow-sm {{ $roleDict[$roleKey]['color'] }}">
                                                        <i class="fa-solid {{ $roleDict[$roleKey]['icon'] }} opacity-70"></i> 
                                                        {{ $roleDict[$roleKey]['name'] }}
                                                    </span>
                                                @endif
                                            @empty
                                                <span class="text-slate-400 text-xs italic">- ไม่มีสิทธิ์ -</span>
                                            @endforelse
                                            
                                            @if($user->militaryUnit)
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold border shadow-sm bg-indigo-50 text-indigo-700 border-indigo-200 ml-1">
                                                    <i class="fa-solid fa-tent opacity-70"></i> {{ $user->militaryUnit->short_name ?? $user->militaryUnit->name }}
                                                    @if($user->intake_batch)
                                                        <span class="text-indigo-400">|</span> ผลัด {{ $user->intake_batch }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-mono">
                                    {{ $user->username }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-slate-700 font-medium">{{ $user->email }}</div>
                                @if($user->phone)
                                    <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-1 font-mono">
                                        <i class="fa-solid fa-phone text-emerald-500 text-[10px]"></i> {{ $user->phone }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 text-center space-x-2">
    
                                <form id="reset-form-{{ $user->id }}" action="{{ route('backend.users.reset_password', $user->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="button" 
                                            onclick="confirmAction(event, 'รีเซ็ตรหัสผ่าน?', 'ระบบจะสุ่มรหัสผ่านใหม่ให้คุณ {{ $user->name }}', 'warning', 'reset-form-{{ $user->id }}')" 
                                            class="text-yellow-500 hover:text-yellow-700 transition-colors" title="รีเซ็ตรหัสผ่านชั่วคราว">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                </form>

                                <a href="{{ route('backend.users.edit', $user->id) }}" class="inline-block text-blue-500 hover:text-blue-700 transition-colors" title="แก้ไข">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                
                                <form id="delete-form-{{ $user->id }}" action="{{ route('backend.users.destroy', $user->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" 
                                            onclick="confirmAction(event, 'ยืนยันการลบข้อมูล', 'คุณต้องการลบบัญชี {{ $user->name }} ใช่หรือไม่? ข้อมูลจะไม่สามารถกู้คืนได้!', 'error', 'delete-form-{{ $user->id }}')" 
                                            class="text-red-500 hover:text-red-700 transition-colors" title="ลบ">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <i class="fa-regular fa-folder-open text-4xl mb-3 text-slate-300 block"></i>
                                ยังไม่มีข้อมูลผู้ใช้งานในระบบ
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-white">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection

