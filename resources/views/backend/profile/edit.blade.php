@extends('layouts.backend')

@section('title', 'โปรไฟล์ส่วนตัว - KSVR Hospital')
@section('page_header', 'ข้อมูลโปรไฟล์ของฉัน')

@section('content')
<!-- 🟢 กล่องที่ 1: ข้อมูลโปรไฟล์ทั่วไป -->
<div class="max-w-3xl mx-auto mt-6 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-8 transition-colors duration-300">
    
    <div class="mb-6 border-b border-slate-100 dark:border-slate-700 pb-4">
        <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <i class="fa-solid fa-id-card text-emerald-500"></i> ข้อมูลส่วนตัวและช่องทางเข้าสู่ระบบ
        </h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">คุณสามารถใช้ ชื่อผู้ใช้งาน (Username), เบอร์โทรศัพท์ หรือ อีเมล ในการเข้าสู่ระบบได้</p>
    </div>

    <!-- แจ้งเตือนเมื่อบันทึกโปรไฟล์สำเร็จ -->
    @if(session('success'))
        <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-lg flex items-center gap-3">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <form action="{{ route('backend.profile.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- 1. ยศ / คำนำหน้า -->
            <div>
                <label for="rank" class="block text-slate-700 dark:text-slate-300 text-sm font-medium mb-2">ยศ / คำนำหน้า <span class="text-red-500">*</span></label>
                <select name="rank" id="rank" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 transition-colors" required>
                    <option value="" disabled {{ (old('rank') ?? $user->rank) ? '' : 'selected' }}>-- เลือกยศ / คำนำหน้า --</option>
                                
                    <optgroup label="ชั้นนายพล (General Officers)">
                        <option value="พล.อ." {{ (old('rank') ?? $user->rank) == 'พล.อ.' ? 'selected' : '' }}>พล.อ. (พลเอก)</option>
                        <option value="พล.อ.หญิง" {{ (old('rank') ?? $user->rank) == 'พล.อ.หญิง' ? 'selected' : '' }}>พล.อ.หญิง (พลเอกหญิง)</option>
                        <option value="พล.ท." {{ (old('rank') ?? $user->rank) == 'พล.ท.' ? 'selected' : '' }}>พล.ท. (พลโท)</option>
                        <option value="พล.ท.หญิง" {{ (old('rank') ?? $user->rank) == 'พล.ท.หญิง' ? 'selected' : '' }}>พล.ท.หญิง (พลโทหญิง)</option>
                        <option value="พล.ต." {{ (old('rank') ?? $user->rank) == 'พล.ต.' ? 'selected' : '' }}>พล.ต. (พลตรี)</option>
                        <option value="พล.ต.หญิง" {{ (old('rank') ?? $user->rank) == 'พล.ต.หญิง' ? 'selected' : '' }}>พล.ต.หญิง (พลตรีหญิง)</option>
                    </optgroup>

                    <optgroup label="ชั้นสัญญาบัตร (Commissioned Officers)">
                        <option value="พ.อ.(พิเศษ)" {{ (old('rank') ?? $user->rank) == 'พ.อ.(พิเศษ)' ? 'selected' : '' }}>พ.อ.(พิเศษ) (พันเอกพิเศษ)</option>
                        <option value="พ.อ.(พิเศษ)หญิง" {{ (old('rank') ?? $user->rank) == 'พ.อ.(พิเศษ)หญิง' ? 'selected' : '' }}>พ.อ.(พิเศษ)หญิง (พันเอกพิเศษหญิง)</option>
                        <option value="พ.อ." {{ (old('rank') ?? $user->rank) == 'พ.อ.' ? 'selected' : '' }}>พ.อ. (พันเอก)</option>
                        <option value="พ.อ.หญิง" {{ (old('rank') ?? $user->rank) == 'พ.อ.หญิง' ? 'selected' : '' }}>พ.อ.หญิง (พันเอกหญิง)</option>
                        <option value="พ.ท." {{ (old('rank') ?? $user->rank) == 'พ.ท.' ? 'selected' : '' }}>พ.ท. (พันโท)</option>
                        <option value="พ.ท.หญิง" {{ (old('rank') ?? $user->rank) == 'พ.ท.หญิง' ? 'selected' : '' }}>พ.ท.หญิง (พันโทหญิง)</option>
                        <option value="พ.ต." {{ (old('rank') ?? $user->rank) == 'พ.ต.' ? 'selected' : '' }}>พ.ต. (พันตรี)</option>
                        <option value="พ.ต.หญิง" {{ (old('rank') ?? $user->rank) == 'พ.ต.หญิง' ? 'selected' : '' }}>พ.ต.หญิง (พันตรีหญิง)</option>
                        <option value="ร.อ." {{ (old('rank') ?? $user->rank) == 'ร.อ.' ? 'selected' : '' }}>ร.อ. (ร้อยเอก)</option>
                        <option value="ร.อ.หญิง" {{ (old('rank') ?? $user->rank) == 'ร.อ.หญิง' ? 'selected' : '' }}>ร.อ.หญิง (ร้อยเอกหญิง)</option>
                        <option value="ร.ท." {{ (old('rank') ?? $user->rank) == 'ร.ท.' ? 'selected' : '' }}>ร.ท. (ร้อยโท)</option>
                        <option value="ร.ท.หญิง" {{ (old('rank') ?? $user->rank) == 'ร.ท.หญิง' ? 'selected' : '' }}>ร.ท.หญิง (ร้อยโทหญิง)</option>
                        <option value="ร.ต." {{ (old('rank') ?? $user->rank) == 'ร.ต.' ? 'selected' : '' }}>ร.ต. (ร้อยตรี)</option>
                        <option value="ร.ต.หญิง" {{ (old('rank') ?? $user->rank) == 'ร.ต.หญิง' ? 'selected' : '' }}>ร.ต.หญิง (ร้อยตรีหญิง)</option>
                        <option value="ว่าที่ ร.ต." {{ (old('rank') ?? $user->rank) == 'ว่าที่ ร.ต.' ? 'selected' : '' }}>ว่าที่ ร.ต. (ว่าที่ร้อยตรี)</option>
                        <option value="ว่าที่ ร.ต.หญิง" {{ (old('rank') ?? $user->rank) == 'ว่าที่ ร.ต.หญิง' ? 'selected' : '' }}>ว่าที่ ร.ต.หญิง (ว่าที่ร้อยตรีหญิง)</option>
                    </optgroup>

                    <optgroup label="ชั้นประทวน (Non-Commissioned)">
                        <option value="จ.ส.อ.(พิเศษ)" {{ (old('rank') ?? $user->rank) == 'จ.ส.อ.(พิเศษ)' ? 'selected' : '' }}>จ.ส.อ.(พิเศษ) (จ่าสิบเอกพิเศษ)</option>
                        <option value="จ.ส.อ.(พิเศษ)หญิง" {{ (old('rank') ?? $user->rank) == 'จ.ส.อ.(พิเศษ)หญิง' ? 'selected' : '' }}>จ.ส.อ.(พิเศษ)หญิง (จ่าสิบเอกพิเศษหญิง)</option>
                        <option value="จ.ส.อ." {{ (old('rank') ?? $user->rank) == 'จ.ส.อ.' ? 'selected' : '' }}>จ.ส.อ. (จ่าสิบเอก)</option>
                        <option value="จ.ส.อ.หญิง" {{ (old('rank') ?? $user->rank) == 'จ.ส.อ.หญิง' ? 'selected' : '' }}>จ.ส.อ.หญิง (จ่าสิบเอกหญิง)</option>
                        <option value="จ.ส.ท." {{ (old('rank') ?? $user->rank) == 'จ.ส.ท.' ? 'selected' : '' }}>จ.ส.ท. (จ่าสิบโท)</option>
                        <option value="จ.ส.ท.หญิง" {{ (old('rank') ?? $user->rank) == 'จ.ส.ท.หญิง' ? 'selected' : '' }}>จ.ส.ท.หญิง (จ่าสิบโทหญิง)</option>
                        <option value="จ.ส.ต." {{ (old('rank') ?? $user->rank) == 'จ.ส.ต.' ? 'selected' : '' }}>จ.ส.ต. (จ่าสิบตรี)</option>
                        <option value="จ.ส.ต.หญิง" {{ (old('rank') ?? $user->rank) == 'จ.ส.ต.หญิง' ? 'selected' : '' }}>จ.ส.ต.หญิง (จ่าสิบตรีหญิง)</option>
                        <option value="ส.อ." {{ (old('rank') ?? $user->rank) == 'ส.อ.' ? 'selected' : '' }}>ส.อ. (สิบเอก)</option>
                        <option value="ส.อ.หญิง" {{ (old('rank') ?? $user->rank) == 'ส.อ.หญิง' ? 'selected' : '' }}>ส.อ.หญิง (สิบเอกหญิง)</option>
                        <option value="ส.ท." {{ (old('rank') ?? $user->rank) == 'ส.ท.' ? 'selected' : '' }}>ส.ท. (สิบโท)</option>
                        <option value="ส.ท.หญิง" {{ (old('rank') ?? $user->rank) == 'ส.ท.หญิง' ? 'selected' : '' }}>ส.ท.หญิง (สิบโทหญิง)</option>
                        <option value="ส.ต." {{ (old('rank') ?? $user->rank) == 'ส.ต.' ? 'selected' : '' }}>ส.ต. (สิบตรี)</option>
                        <option value="ส.ต.หญิง" {{ (old('rank') ?? $user->rank) == 'ส.ต.หญิง' ? 'selected' : '' }}>ส.ต.หญิง (สิบตรีหญิง)</option>
                    </optgroup>

                    <optgroup label="พลทหารกองประจำการ">
                        <option value="พลฯ" {{ (old('rank') ?? $user->rank) == 'พลฯ' ? 'selected' : '' }}>พลฯ (พลทหาร)</option>
                    </optgroup>

                    <optgroup label="บุคลากรทางการแพทย์">
                        <option value="นพ." {{ (old('rank') ?? $user->rank) == 'นพ.' ? 'selected' : '' }}>นพ. (นายแพทย์)</option>
                        <option value="พญ." {{ (old('rank') ?? $user->rank) == 'พญ.' ? 'selected' : '' }}>พญ. (แพทย์หญิง)</option>
                        <option value="ทพ." {{ (old('rank') ?? $user->rank) == 'ทพ.' ? 'selected' : '' }}>ทพ. (ทันตแพทย์)</option>
                        <option value="ทพญ." {{ (old('rank') ?? $user->rank) == 'ทพญ.' ? 'selected' : '' }}>ทพญ. (ทันตแพทย์หญิง)</option>
                    </optgroup>

                    <optgroup label="บุคคลทั่วไป">
                        <option value="นาย" {{ (old('rank') ?? $user->rank) == 'นาย' ? 'selected' : '' }}>นาย</option>
                        <option value="นาง" {{ (old('rank') ?? $user->rank) == 'นาง' ? 'selected' : '' }}>นาง</option>
                        <option value="นางสาว" {{ (old('rank') ?? $user->rank) == 'นางสาว' ? 'selected' : '' }}>นางสาว</option>
                    </optgroup>
                </select>
                @error('rank')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <!-- 2. ชื่อ - นามสกุล -->
            <div>
                <label for="name" class="block text-slate-700 dark:text-slate-300 text-sm font-medium mb-2">ชื่อ - นามสกุล <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 transition-colors" required>
                @error('name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <!-- 3. อีเมล (ปรับเป็น 1 คอลัมน์ เพื่อวางคู่กับเบอร์โทรศัพท์) -->
            <div>
                <label for="email" class="block text-slate-700 dark:text-slate-300 text-sm font-medium mb-2">อีเมล (Email) <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 transition-colors" required>
                @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- 🌟 4. เบอร์โทรศัพท์ (เพิ่มใหม่! รองรับการล็อกอิน) -->
            <div>
                <label for="phone" class="block text-slate-700 dark:text-slate-300 text-sm font-medium mb-2">
                    เบอร์โทรศัพท์ <span class="text-red-500">*</span> <span class="text-xs text-emerald-600 dark:text-emerald-400 font-normal">(ใช้ล็อกอินได้)</span>
                </label>
                <input type="tel" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" maxlength="10" inputmode="numeric" pattern="[0-9]{9,10}" placeholder="เช่น 0812345678" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-mono transition-colors" required>
                @error('phone')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- 5. ชื่อผู้ใช้งาน (Read-only) -->
            <div class="{{ $user->militaryUnit ? 'md:col-span-1' : 'md:col-span-2' }}">
                <label class="block text-slate-700 dark:text-slate-300 text-sm font-medium mb-2">
                    ชื่อผู้ใช้งาน (Username) - <span class="text-xs text-red-500">ไม่สามารถแก้ไขได้</span>
                </label>
                <input type="text" value="{{ $user->username }}" class="w-full px-4 py-2.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 rounded-lg cursor-not-allowed font-mono" readonly>
            </div>

            <!-- 🌟 6. แสดงหน่วยฝึกและผลัดที่รับผิดชอบ (แสดงเฉพาะเสนารักษ์ แบบ Read-only) -->
            @if($user->militaryUnit)
            <div class="md:col-span-1">
                <label class="block text-slate-700 dark:text-slate-300 text-sm font-medium mb-2">
                    พื้นที่รับผิดชอบ (เสนารักษ์) - <span class="text-xs text-slate-400">กำหนดโดยผู้ดูแลระบบ</span>
                </label>
                <input type="text" value="{{ $user->militaryUnit->short_name ?? $user->militaryUnit->name }} {{ $user->intake_batch ? '(ผลัดที่ ' . $user->intake_batch . ')' : '' }}" class="w-full px-4 py-2.5 border border-blue-200 dark:border-blue-900/50 bg-blue-50/50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 font-semibold rounded-lg cursor-not-allowed" readonly>
            </div>
            @endif
        </div>

        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium shadow-sm transition-colors flex items-center justify-center gap-2">
            <i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูลโปรไฟล์
        </button>
    </form>
</div>

<!-- 🌟 กล่องที่ 2: เปลี่ยนรหัสผ่าน (Change Password) -->
<div class="max-w-3xl mx-auto mt-6 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-8 transition-colors duration-300">
    <div class="mb-6 border-b border-slate-100 dark:border-slate-700 pb-4">
        <h3 class="text-lg font-bold text-slate-800 dark:text-white"><i class="fa-solid fa-lock text-emerald-500 mr-2"></i>เปลี่ยนรหัสผ่าน (Change Password)</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">กรุณาตั้งรหัสผ่านที่คาดเดาได้ยากเพื่อความปลอดภัยของบัญชี (ขั้นต่ำ 8 ตัวอักษร)</p>
    </div>

    @if(session('password_success'))
        <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-lg flex items-center gap-3">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('password_success') }}</span>
        </div>
    @endif

    <form action="{{ route('backend.profile.password.update') }}" method="POST" class="space-y-5">
        @csrf
        @method('PUT')

        <!-- รหัสผ่านปัจจุบัน -->
        <div>
            <label for="current_password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">รหัสผ่านปัจจุบัน <span class="text-red-500">*</span></label>
            <input type="password" name="current_password" id="current_password" required
                   class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 transition-colors">
            @error('current_password')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <!-- รหัสผ่านใหม่ & ยืนยันรหัสผ่านใหม่ -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">รหัสผ่านใหม่ <span class="text-red-500">*</span></label>
                <input type="password" name="password" id="password" required
                       class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 transition-colors">
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">ยืนยันรหัสผ่านใหม่ <span class="text-red-500">*</span></label>
                <input type="password" name="password_confirmation" id="password_confirmation" required
                       class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 transition-colors">
            </div>
        </div>

        <div class="pt-2 text-right">
            <button type="submit" class="px-6 py-2.5 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white rounded-lg font-bold shadow-sm transition-all flex items-center justify-center gap-2 w-full md:w-auto ml-auto">
                <i class="fa-solid fa-key"></i> อัปเดตรหัสผ่าน
            </button>
        </div>
    </form>
</div>
@endsection