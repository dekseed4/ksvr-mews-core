@extends('layouts.backend')

@section('title', 'เพิ่มผู้ใช้งานระบบ - KSVR Hospital')
@section('page_header', 'การจัดการผู้ใช้งาน')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
            <!-- ส่วนหัวของฟอร์ม -->
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <h3 class="font-semibold text-slate-800 flex items-center">
                    <i class="fa-solid fa-user-plus mr-2 text-emerald-500"></i>
                    เพิ่มผู้ใช้งานใหม่
                </h3>
            </div>
            
            <div class="p-6">
                <!-- แสดงข้อความแจ้งเตือนเมื่อเกิดข้อผิดพลาด (Validation Errors) -->
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3 shadow-sm">
                        <i class="fa-solid fa-circle-exclamation mt-1"></i>
                        <ul class="list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- ฟอร์มกรอกข้อมูล -->
                <form action="{{ route('backend.users.store') }}" method="POST">
                    @csrf 

                    <!-- แถวที่ 1: ยศ และ ชื่อ-นามสกุล -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <!-- ช่องเลือกยศ -->
                        <div class="col-span-1">
                            <label for="rank" class="block text-slate-700 text-sm font-medium mb-2">ยศ / คำนำหน้า <span class="text-red-500">*</span></label>
                            <select name="rank" id="rank" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white" required>
                                <option value="" disabled {{ old('rank') ? '' : 'selected' }}>-- เลือกยศ / คำนำหน้า --</option>
                                
                                <optgroup label="ชั้นนายพล (General Officers)">
                                    <option value="พล.อ." {{ old('rank') == 'พล.อ.' ? 'selected' : '' }}>พล.อ. (พลเอก)</option>
                                    <option value="พล.อ.หญิง" {{ old('rank') == 'พล.อ.หญิง' ? 'selected' : '' }}>พล.อ.หญิง (พลเอกหญิง)</option>
                                    <option value="พล.ท." {{ old('rank') == 'พล.ท.' ? 'selected' : '' }}>พล.ท. (พลโท)</option>
                                    <option value="พล.ท.หญิง" {{ old('rank') == 'พล.ท.หญิง' ? 'selected' : '' }}>พล.ท.หญิง (พลโทหญิง)</option>
                                    <option value="พล.ต." {{ old('rank') == 'พล.ต.' ? 'selected' : '' }}>พล.ต. (พลตรี)</option>
                                    <option value="พล.ต.หญิง" {{ old('rank') == 'พล.ต.หญิง' ? 'selected' : '' }}>พล.ต.หญิง (พลตรีหญิง)</option>
                                </optgroup>

                                <optgroup label="ชั้นสัญญาบัตร (Commissioned Officers)">
                                    <option value="พ.อ.(พิเศษ)" {{ old('rank') == 'พ.อ.(พิเศษ)' ? 'selected' : '' }}>พ.อ.(พิเศษ) (พันเอกพิเศษ)</option>
                                    <option value="พ.อ.(พิเศษ)หญิง" {{ old('rank') == 'พ.อ.(พิเศษ)หญิง' ? 'selected' : '' }}>พ.อ.(พิเศษ)หญิง (พันเอกพิเศษหญิง)</option>
                                    <option value="พ.อ." {{ old('rank') == 'พ.อ.' ? 'selected' : '' }}>พ.อ. (พันเอก)</option>
                                    <option value="พ.อ.หญิง" {{ old('rank') == 'พ.อ.หญิง' ? 'selected' : '' }}>พ.อ.หญิง (พันเอกหญิง)</option>
                                    <option value="พ.ท." {{ old('rank') == 'พ.ท.' ? 'selected' : '' }}>พ.ท. (พันโท)</option>
                                    <option value="พ.ท.หญิง" {{ old('rank') == 'พ.ท.หญิง' ? 'selected' : '' }}>พ.ท.หญิง (พันโทหญิง)</option>
                                    <option value="พ.ต." {{ old('rank') == 'พ.ต.' ? 'selected' : '' }}>พ.ต. (พันตรี)</option>
                                    <option value="พ.ต.หญิง" {{ old('rank') == 'พ.ต.หญิง' ? 'selected' : '' }}>พ.ต.หญิง (พันตรีหญิง)</option>
                                    <option value="ร.อ." {{ old('rank') == 'ร.อ.' ? 'selected' : '' }}>ร.อ. (ร้อยเอก)</option>
                                    <option value="ร.อ.หญิง" {{ old('rank') == 'ร.อ.หญิง' ? 'selected' : '' }}>ร.อ.หญิง (ร้อยเอกหญิง)</option>
                                    <option value="ร.ท." {{ old('rank') == 'ร.ท.' ? 'selected' : '' }}>ร.ท. (ร้อยโท)</option>
                                    <option value="ร.ท.หญิง" {{ old('rank') == 'ร.ท.หญิง' ? 'selected' : '' }}>ร.ท.หญิง (ร้อยโทหญิง)</option>
                                    <option value="ร.ต." {{ old('rank') == 'ร.ต.' ? 'selected' : '' }}>ร.ต. (ร้อยตรี)</option>
                                    <option value="ร.ต.หญิง" {{ old('rank') == 'ร.ต.หญิง' ? 'selected' : '' }}>ร.ต.หญิง (ร้อยตรีหญิง)</option>
                                    <option value="ว่าที่ ร.ต." {{ old('rank') == 'ว่าที่ ร.ต.' ? 'selected' : '' }}>ว่าที่ ร.ต. (ว่าที่ร้อยตรี)</option>
                                    <option value="ว่าที่ ร.ต.หญิง" {{ old('rank') == 'ว่าที่ ร.ต.หญิง' ? 'selected' : '' }}>ว่าที่ ร.ต.หญิง (ว่าที่ร้อยตรีหญิง)</option>
                                </optgroup>

                                <optgroup label="ชั้นประทวน (Non-Commissioned)">
                                    <option value="จ.ส.อ.(พิเศษ)" {{ old('rank') == 'จ.ส.อ.(พิเศษ)' ? 'selected' : '' }}>จ.ส.อ.(พิเศษ) (จ่าสิบเอกพิเศษ)</option>
                                    <option value="จ.ส.อ.(พิเศษ)หญิง" {{ old('rank') == 'จ.ส.อ.(พิเศษ)หญิง' ? 'selected' : '' }}>จ.ส.อ.(พิเศษ)หญิง (จ่าสิบเอกพิเศษหญิง)</option>
                                    <option value="จ.ส.อ." {{ old('rank') == 'จ.ส.อ.' ? 'selected' : '' }}>จ.ส.อ. (จ่าสิบเอก)</option>
                                    <option value="จ.ส.อ.หญิง" {{ old('rank') == 'จ.ส.อ.หญิง' ? 'selected' : '' }}>จ.ส.อ.หญิง (จ่าสิบเอกหญิง)</option>
                                    <option value="จ.ส.ท." {{ old('rank') == 'จ.ส.ท.' ? 'selected' : '' }}>จ.ส.ท. (จ่าสิบโท)</option>
                                    <option value="จ.ส.ท.หญิง" {{ old('rank') == 'จ.ส.ท.หญิง' ? 'selected' : '' }}>จ.ส.ท.หญิง (จ่าสิบโทหญิง)</option>
                                    <option value="จ.ส.ต." {{ old('rank') == 'จ.ส.ต.' ? 'selected' : '' }}>จ.ส.ต. (จ่าสิบตรี)</option>
                                    <option value="จ.ส.ต.หญิง" {{ old('rank') == 'จ.ส.ต.หญิง' ? 'selected' : '' }}>จ.ส.ต.หญิง (จ่าสิบตรีหญิง)</option>
                                    <option value="ส.อ." {{ old('rank') == 'ส.อ.' ? 'selected' : '' }}>ส.อ. (สิบเอก)</option>
                                    <option value="ส.อ.หญิง" {{ old('rank') == 'ส.อ.หญิง' ? 'selected' : '' }}>ส.อ.หญิง (สิบเอกหญิง)</option>
                                    <option value="ส.ท." {{ old('rank') == 'ส.ท.' ? 'selected' : '' }}>ส.ท. (สิบโท)</option>
                                    <option value="ส.ท.หญิง" {{ old('rank') == 'ส.ท.หญิง' ? 'selected' : '' }}>ส.ท.หญิง (สิบโทหญิง)</option>
                                    <option value="ส.ต." {{ old('rank') == 'ส.ต.' ? 'selected' : '' }}>ส.ต. (สิบตรี)</option>
                                    <option value="ส.ต.หญิง" {{ old('rank') == 'ส.ต.หญิง' ? 'selected' : '' }}>ส.ต.หญิง (สิบตรีหญิง)</option>
                                </optgroup>

                                <optgroup label="พลทหารกองประจำการ">
                                    <option value="พลฯ" {{ old('rank') == 'พลฯ' ? 'selected' : '' }}>พลฯ (พลทหาร)</option>
                                </optgroup>

                                <optgroup label="บุคลากรทางการแพทย์">
                                    <option value="นพ." {{ old('rank') == 'นพ.' ? 'selected' : '' }}>นพ. (นายแพทย์)</option>
                                    <option value="พญ." {{ old('rank') == 'พญ.' ? 'selected' : '' }}>พญ. (แพทย์หญิง)</option>
                                    <option value="ทพ." {{ old('rank') == 'ทพ.' ? 'selected' : '' }}>ทพ. (ทันตแพทย์)</option>
                                    <option value="ทพญ." {{ old('rank') == 'ทพญ.' ? 'selected' : '' }}>ทพญ. (ทันตแพทย์หญิง)</option>
                                </optgroup>

                                <optgroup label="บุคคลทั่วไป">
                                    <option value="นาย" {{ old('rank') == 'นาย' ? 'selected' : '' }}>นาย</option>
                                    <option value="นาง" {{ old('rank') == 'นาง' ? 'selected' : '' }}>นาง</option>
                                    <option value="นางสาว" {{ old('rank') == 'นางสาว' ? 'selected' : '' }}>นางสาว</option>
                                </optgroup>
                            </select>
                        </div>

                        <!-- ช่องกรอกชื่อ-นามสกุล -->
                        <div class="col-span-1 md:col-span-2">
                            <label for="name" class="block text-slate-700 text-sm font-medium mb-2">ชื่อ - นามสกุล <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="เช่น ใจดี มีสุข (ไม่ต้องใส่คำนำหน้า)" required>
                        </div>
                    </div>

                    <!-- แถวที่ 2: Username และ อีเมล -->
                    <!-- แถวที่ 2: Username, อีเมล และ เบอร์โทรศัพท์ (ปรับเป็น md:grid-cols-3) -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label for="username" class="block text-slate-700 text-sm font-medium mb-2">ชื่อผู้ใช้งาน (Username) <span class="text-red-500">*</span></label>
                            <input type="text" name="username" id="username" value="{{ old('username') }}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="ใช้สำหรับเข้าสู่ระบบ" required>
                        </div>

                        <div>
                            <label for="email" class="block text-slate-700 text-sm font-medium mb-2">อีเมล <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="เช่น email@ksvr.rta.mi.th" required>
                        </div>

                        <!-- 🌟 ช่องกรอกเบอร์โทรศัพท์ -->
                        <div>
                            <label for="phone" class="block text-slate-700 text-sm font-medium mb-2">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
                            <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" maxlength="10" pattern="[0-9]{9,10}" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="เช่น 0812345678 (เฉพาะตัวเลข)" required>
                        </div>
                    </div>
                    
                   

                    <!-- แถวที่ 3: สิทธิ์การใช้งาน (ใช้เหมือนกันทั้งใน create.blade.php และ edit.blade.php) -->
                    <div class="mb-6">
                        <label class="block text-slate-700 text-sm font-bold mb-3">
                            สิทธิ์การใช้งาน (Roles) <span class="text-rose-500">*</span>
                        </label>
                        
                        @php
                            $rawRoles = old('roles', isset($user) ? ($user->roles ?? $user->role ?? []) : []);
                            $currentRoles = is_array($rawRoles) ? $rawRoles : (is_string($rawRoles) ? (json_decode($rawRoles, true) ?? []) : []);

                            // 🌟 รวมสิทธิ์ทั้งหมดไว้ใน Array เดียว (รวมเจ้าหน้าที่เวชกรรมป้องกัน)
                            $availableRoles = [
                                'admin'         => ['name' => 'ผู้ดูแลระบบ', 'desc' => 'IT Admin', 'icon' => 'fa-user-shield'],
                                'executive'     => ['name' => 'ผู้บริหาร', 'desc' => 'Executive', 'icon' => 'fa-user-tie'],
                                'er_nurse'      => ['name' => 'พยาบาล ER', 'desc' => 'ER Nurse', 'icon' => 'fa-user-nurse'],
                                'opd_nurse'     => ['name' => 'พยาบาล OPD', 'desc' => 'OPD Nurse', 'icon' => 'fa-stethoscope'],
                                'medic'         => ['name' => 'เสนารักษ์', 'desc' => 'Medic', 'icon' => 'fa-walkie-talkie'],
                                'registration'  => ['name' => 'เจ้าหน้าที่ทะเบียน', 'desc' => 'Registration', 'icon' => 'fa-id-card-clip'],
                                'medical_staff' => ['name' => 'เจ้าหน้าที่เวชกรรม', 'desc' => 'Medical Staff', 'icon' => 'fa-kit-medical'],
                            ];
                        @endphp

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            @foreach($availableRoles as $roleValue => $roleData)
                                <label class="relative flex items-center p-4 bg-white border border-slate-200 rounded-2xl cursor-pointer transition-all hover:bg-slate-50 hover:border-emerald-300 has-[:checked]:bg-emerald-50/50 has-[:checked]:border-emerald-500 has-[:checked]:ring-1 has-[:checked]:ring-emerald-500 group shadow-sm">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="roles[]" value="{{ $roleValue }}" 
                                               class="w-5 h-5 text-emerald-600 bg-white border-slate-300 rounded focus:ring-emerald-500 transition-colors"
                                               @checked(in_array($roleValue, $currentRoles))>
                                    </div>
                                    
                                    <div class="ml-3 flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center group-has-[:checked]:bg-emerald-100 group-has-[:checked]:text-emerald-600 transition-colors">
                                            <i class="fa-solid {{ $roleData['icon'] }}"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-slate-800 group-has-[:checked]:text-emerald-700">{{ $roleData['name'] }}</div>
                                            <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">{{ $roleData['desc'] }}</div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        @error('roles')
                            <div class="flex items-center gap-2 text-rose-500 text-sm mt-3 bg-rose-50 p-3 rounded-lg border border-rose-100">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <p class="font-medium">{{ $message }}</p>
                            </div>
                        @enderror
                        @error('roles.*')
                            <div class="flex items-center gap-2 text-rose-500 text-sm mt-3 bg-rose-50 p-3 rounded-lg border border-rose-100">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <p class="font-medium">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>

                    <!-- 🌟 กล่องเฉพาะกิจสำหรับ "เสนารักษ์": จะแสดงผลเมื่อติ๊กเลือกสิทธิ์ 'เสนารักษ์ (medic)' เท่านั้น -->
                    <div id="medic-assignment-box" class="hidden mb-8 p-6 bg-blue-50/50 rounded-2xl border border-blue-200 transition-all duration-300">
                        <div class="flex items-center gap-2 text-blue-800 font-bold text-sm mb-4 border-b border-blue-100 pb-2">
                            <i class="fa-solid fa-walkie-talkie text-blue-600"></i>
                            <span>กำหนดพื้นที่รับผิดชอบของเสนารักษ์ (เชื่อมกับข้อมูลทหารใหม่)</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- 1. เลือกหน่วยฝึก -->
                            <div>
                                <label for="military_unit_id" class="block text-slate-700 text-sm font-medium mb-2">
                                    สังกัด / หน่วยฝึก <span class="text-rose-500">*</span>
                                </label>
                                <select name="military_unit_id" id="military_unit_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 bg-white transition-colors">
                                    <option value="">-- เลือกหน่วยฝึกที่ประจำการ --</option>
                                    @foreach($units as $unit)
                                        <option value="{{ $unit->id }}" {{ (old('military_unit_id') ?? ($user->military_unit_id ?? '')) == $unit->id ? 'selected' : '' }}>
                                            {{ $unit->short_name ?? $unit->name }} @if($unit->battalion) ({{ $unit->battalion }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('military_unit_id')
                                    <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- 2. เลือกผลัดทหารใหม่ -->
                            <div>
                                <label for="intake_batch" class="block text-slate-700 text-sm font-medium mb-2">
                                    ประจำผลัดที่ <span class="text-rose-500">*</span>
                                </label>
                                <select name="intake_batch" id="intake_batch" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 bg-white transition-colors">
                                    <option value="">-- เลือกผลัดทหารใหม่ --</option>
                                    @foreach($batches as $batch)
                                        <option value="{{ $batch }}" {{ (old('intake_batch') ?? ($user->intake_batch ?? '')) == $batch ? 'selected' : '' }}>
                                            ผลัดที่ {{ $batch }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('intake_batch')
                                    <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- แถบปุ่มกดด้านล่าง -->
                    <div class="flex flex-col-reverse md:flex-row justify-between items-center gap-4 pt-4 border-t border-slate-100 mt-6">
                        <!-- ปุ่มย้อนกลับ (ซ้าย) -->
                        <a href="{{ route('backend.users.index') }}" class="w-full md:w-auto px-5 py-2.5 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                            <i class="fa-solid fa-arrow-left"></i> ย้อนกลับ
                        </a>
                        
                        <!-- ปุ่มเคลียร์และบันทึก (ขวา) -->
                        <div class="w-full md:w-auto flex flex-col md:flex-row gap-3">
                            <button type="reset" class="px-5 py-2.5 text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg font-medium transition-colors text-center">
                                ล้างข้อมูล
                            </button>
                            <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 shadow-sm transition-colors flex items-center justify-center gap-2">
                                <i class="fa-solid fa-save"></i> บันทึกข้อมูลผู้ใช้งาน
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ค้นหา Checkbox ที่มีค่า value="medic"
        const medicCheckbox = document.querySelector('input[name="roles[]"][value="medic"]');
        const medicBox = document.getElementById('medic-assignment-box');
        const unitSelect = document.getElementById('military_unit_id');
        const batchSelect = document.getElementById('intake_batch');

        function toggleMedicFields() {
            if (!medicCheckbox || !medicBox) return;

            if (medicCheckbox.checked) {
                // ถ้าติ๊กเสนารักษ์ -> แสดงกล่อง และบังคับให้ต้องเลือกค่า (required)
                medicBox.classList.remove('hidden');
                unitSelect.setAttribute('required', 'required');
                batchSelect.setAttribute('required', 'required');
            } else {
                // ถ้าไม่ได้ติ๊กเสนารักษ์ (เช่น เป็นพยาบาล/ผู้บริหาร) -> ซ่อนกล่อง และปลด required ออก
                medicBox.classList.add('hidden');
                unitSelect.removeAttribute('required');
                batchSelect.removeAttribute('required');
            }
        }

        // ตรวจสอบทันทีตอนโหลดหน้าเว็บ (เผื่อกรณีหน้า Edit หรือติด Validate กลับมา)
        toggleMedicFields();

        // ดักจับเมื่อแอดมินคลิกติ๊กเข้า/ออก
        if (medicCheckbox) {
            medicCheckbox.addEventListener('change', toggleMedicFields);
        }
    });
</script>
@endsection