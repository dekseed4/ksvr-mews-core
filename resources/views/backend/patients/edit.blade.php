@extends('layouts.backend')

@section('title', 'แก้ไขข้อมูลผู้ป่วย - KSVR Hospital')
@section('page_header', 'แก้ไขข้อมูลผู้ป่วย')

@section('content')
<div class="max-w-4xl mx-auto mt-4 pb-12">
    
    <!-- ปุ่มย้อนกลับ -->
    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('backend.patients.show', $patient->id) }}" 
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 hover:text-emerald-600 hover:border-emerald-200 transition-all shadow-sm font-medium">
            <i class="fa-solid fa-arrow-left"></i> ย้อนกลับไปแฟ้มประวัติ
        </a>
    </div>

    <!-- 🌟 โซนเครื่องมือพิเศษ: ดึงข้อมูลอัปเดตจาก HosXP -->
    <div class="bg-blue-50/50 rounded-3xl p-6 md:p-8 shadow-sm border border-blue-100 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-xl shrink-0 shadow-sm mt-1">
                <i class="fa-solid fa-cloud-arrow-down"></i>
            </div>
            <div class="w-full">
                <h3 class="text-lg font-bold text-blue-900 mb-1">ซิงค์ข้อมูลและสิทธิการรักษาล่าสุดจาก HosXP</h3>
                <p class="text-sm text-blue-700/80 mb-4">หากมีการแก้ไขชื่อ, วันเกิด, กรุ๊ปเลือด หรือเปลี่ยนสิทธิการรักษาในฐานข้อมูลโรงพยาบาล สามารถกดปุ่มนี้เพื่ออัปเดตลงฟอร์มได้ทันที</p>
                
                <div class="flex flex-col md:flex-row gap-3">
                    <input type="text" id="search_hosxp_input" value="{{ $patient->cid ?? $patient->hn }}" 
                           inputmode="numeric" pattern="[0-9]*" 
                           class="w-full md:w-2/3 px-4 py-3 bg-white border border-blue-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 font-mono text-lg tracking-wider transition-colors shadow-inner" 
                           placeholder="กรอกเลขบัตร ปชช. 13 หลัก หรือ รหัส HN...">
                           
                    <button type="button" id="btn_sync_hosxp" class="w-full md:w-1/3 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrows-rotate"></i> ดึงข้อมูลอัปเดต
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-3xl p-6 md:p-10 shadow-sm border border-slate-100">
        <div class="flex items-center gap-3 mb-8 border-b border-slate-100 pb-5">
            <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center text-xl">
                <i class="fa-solid fa-user-pen"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-slate-800">แก้ไขข้อมูลพื้นฐาน</h2>
                <p class="text-sm text-slate-500">ปรับปรุงข้อมูลของ {{ $patient->first_name }} {{ $patient->last_name }}</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-8 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-start gap-3">
                <i class="fa-solid fa-circle-exclamation mt-1"></i>
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('backend.patients.update', $patient->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-8">
                <!-- 🌟 1. ข้อมูลบุคคลพื้นฐาน (ครบ 9 ช่องแบบ 3x3 Grid สมมาตร) -->
                <div class="bg-slate-50/50 p-6 md:p-8 rounded-2xl border border-slate-100">
                    <h3 class="text-sm font-bold text-indigo-500 uppercase tracking-widest mb-4 border-b border-indigo-100 pb-3 flex items-center gap-2">
                        <i class="fa-solid fa-address-card"></i> ข้อมูลบุคคล (PERSONAL INFO)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">เลขประจำตัวประชาชน <span class="text-rose-500">*</span></label>
                            <input type="text" name="cid" id="input_cid" value="{{ old('cid', $patient->cid) }}" required maxlength="13" inputmode="numeric" pattern="[0-9]*" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">รหัส HN (HosXP)</label>
                            <input type="text" name="hn" id="input_hn" value="{{ old('hn', $patient->hn) }}" inputmode="numeric" pattern="[0-9]*" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">ยศ / คำนำหน้า <span class="text-rose-500">*</span></label>
                            <input type="text" name="rank" id="input_rank" value="{{ old('rank', $patient->rank) }}" required class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">ชื่อจริง <span class="text-rose-500">*</span></label>
                            <input type="text" name="first_name" id="input_fname" value="{{ old('first_name', $patient->first_name) }}" required class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">นามสกุล <span class="text-rose-500">*</span></label>
                            <input type="text" name="last_name" id="input_lname" value="{{ old('last_name', $patient->last_name) }}" required class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">วันเกิด (ถ้ามี)</label>
                            <input type="date" name="dob" id="input_dob" value="{{ old('dob', $patient->dob ? \Carbon\Carbon::parse($patient->dob)->format('Y-m-d') : '') }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">ศาสนา</label>
                            <input type="text" name="religion" value="{{ old('religion', $patient->religion) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">วุฒิการศึกษา</label>
                            <input type="text" name="education" value="{{ old('education', $patient->education) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>

                        <!-- 🌟 ช่องที่ 9: สิทธิการรักษาพยาบาล (ดีไซน์รวมในกรอบเดียว ไม่ล้นขอบ 100%) -->
                        <div class="min-w-0">
                            <label class="block text-xs font-bold text-emerald-600 mb-1">
                                <i class="fa-solid fa-shield-heart mr-1"></i> สิทธิการรักษาพยาบาล (HosXP)
                            </label>
                            <div class="flex items-center w-full bg-white border border-slate-200 focus-within:ring-2 focus-within:ring-emerald-500 focus-within:border-emerald-500 rounded-xl shadow-sm overflow-hidden transition-all">
                                <input type="text" name="pttype_code" id="input_pttype_code" value="{{ old('pttype_code', $patient->pttype_code) }}" placeholder="รหัส" maxlength="10" 
                                       class="w-12 shrink-0 px-2 py-2.5 bg-slate-50 border-0 border-r border-slate-200 focus:ring-0 text-xs font-mono font-bold text-emerald-700 text-center outline-none" title="รหัสสิทธิ HosXP">
                                <input type="text" name="pttype_name" id="input_pttype_name" value="{{ old('pttype_name', $patient->pttype_name) }}" placeholder="เช่น ทหารกองประจำการ, บัตรทอง..." maxlength="150" 
                                       class="min-w-0 flex-1 w-full px-3 py-2.5 bg-transparent border-0 focus:ring-0 text-sm text-slate-700 outline-none truncate">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🌟 2. ข้อมูลทางกายภาพ -->
                <div class="bg-slate-50/50 p-6 md:p-8 rounded-2xl border border-slate-100">
                    <h3 class="text-sm font-bold text-emerald-500 uppercase tracking-widest mb-4 border-b border-emerald-100 pb-3 flex items-center gap-2">
                        <i class="fa-solid fa-child-reaching"></i> ข้อมูลร่างกาย (PHYSICAL DATA)
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-5">
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-slate-500 mb-1">กรุ๊ปเลือด</label>
                            <select name="blood_group" id="input_blood" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-emerald-500 focus:border-emerald-500 rounded-xl shadow-sm text-sm transition-all">
                                <option value="">- ไม่ระบุ -</option>
                                @foreach(['A', 'B', 'O', 'AB'] as $bg)
                                    <option value="{{ $bg }}" {{ old('blood_group', $patient->blood_group) == $bg ? 'selected' : '' }}>กรุ๊ป {{ $bg }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs font-bold text-slate-500 mb-1">ส่วนสูง (cm)</label>
                            <input type="number" name="height" id="calc_h" value="{{ old('height', $patient->height) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-emerald-500 focus:border-emerald-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs font-bold text-slate-500 mb-1">น้ำหนัก (kg)</label>
                            <input type="number" name="weight" id="calc_w" value="{{ old('weight', $patient->weight) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-emerald-500 focus:border-emerald-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs font-bold text-slate-500 mb-1">ค่า BMI</label>
                            <input type="text" name="bmi" id="calc_bmi" value="{{ old('bmi', $patient->bmi) }}" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 text-slate-500 rounded-xl shadow-sm text-sm outline-none">
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs font-bold text-slate-500 mb-1">รอบอก (cm)</label>
                            <input type="text" name="chest_size" value="{{ old('chest_size', $patient->chest_size) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-emerald-500 focus:border-emerald-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div class="col-span-2 md:col-span-6">
                            <label class="block text-xs font-bold text-slate-500 mb-1">ตำหนิ / แผลเป็น</label>
                            <input type="text" name="scar_details" value="{{ old('scar_details', $patient->scar_details) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-emerald-500 focus:border-emerald-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <!-- 🌟 โรคประจำตัว / ข้อมูลสุขภาพเพิ่มเติม (3 ตัวเลือก + ช่องกรอกอัตโนมัติ) -->
                        @php
                            $currentChronicStatus = old('chronic_disease_status', $patient->chronic_disease_status ?? 'none');
                            $currentChronicDetail = old('chronic_disease_detail', $patient->chronic_disease_detail ?? '');
                        @endphp
                        <div class="col-span-2 md:col-span-6 pt-3 border-t border-slate-200/70">
                            <label class="block text-xs font-bold text-slate-600 mb-2">
                                <i class="fa-solid fa-notes-medical text-rose-500 mr-1"></i> โรคประจำตัว / ข้อมูลสุขภาพสำคัญ
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="flex items-center gap-2.5 p-3 bg-white border border-slate-200 rounded-xl cursor-pointer hover:bg-emerald-50/40 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/60 has-[:checked]:ring-2 has-[:checked]:ring-emerald-500/20 transition-all text-sm font-bold text-slate-700">
                                    <input type="radio" name="chronic_disease_status" value="none" {{ $currentChronicStatus === 'none' ? 'checked' : '' }} onchange="toggleFormChronicDetail(this.value)" class="text-emerald-600 focus:ring-emerald-500">
                                    <span>1. ไม่มีโรคประจำตัว</span>
                                </label>
                                <label class="flex items-center gap-2.5 p-3 bg-white border border-slate-200 rounded-xl cursor-pointer hover:bg-rose-50/40 has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/60 has-[:checked]:ring-2 has-[:checked]:ring-rose-500/20 transition-all text-sm font-bold text-slate-700">
                                    <input type="radio" name="chronic_disease_status" value="yes" {{ $currentChronicStatus === 'yes' ? 'checked' : '' }} onchange="toggleFormChronicDetail(this.value)" class="text-rose-600 focus:ring-rose-500">
                                    <span>2. มีโรคประจำตัว</span>
                                </label>
                                <label class="flex items-center gap-2.5 p-3 bg-white border border-slate-200 rounded-xl cursor-pointer hover:bg-indigo-50/40 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/60 has-[:checked]:ring-2 has-[:checked]:ring-indigo-500/20 transition-all text-sm font-bold text-slate-700">
                                    <input type="radio" name="chronic_disease_status" value="other" {{ $currentChronicStatus === 'other' ? 'checked' : '' }} onchange="toggleFormChronicDetail(this.value)" class="text-indigo-600 focus:ring-indigo-500">
                                    <span>3. อื่นๆ (ระบุ)</span>
                                </label>
                            </div>

                            <div id="form_chronic_detail_box" class="{{ in_array($currentChronicStatus, ['yes', 'other']) ? '' : 'hidden' }} mt-3">
                                <input type="text" name="chronic_disease_detail" id="input_chronic_detail" value="{{ $currentChronicDetail }}" maxlength="255"
                                       placeholder="ระบุชื่อโรคประจำตัว หรือรายละเอียดอื่นๆ (เช่น หอบหืด, แพ้ยา, เคยผ่าตัด)..."
                                       class="w-full px-4 py-2.5 bg-white border border-rose-200 focus:ring-rose-500 focus:border-rose-500 rounded-xl shadow-sm text-sm transition-all">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🌟 3. ข้อมูลครอบครัว & ติดต่อ -->
                <div class="bg-slate-50/50 p-6 md:p-8 rounded-2xl border border-slate-100">
                    <h3 class="text-sm font-bold text-amber-500 uppercase tracking-widest mb-4 border-b border-amber-100 pb-3 flex items-center gap-2">
                        <i class="fa-solid fa-house-chimney-user"></i> ข้อมูลติดต่อ (CONTACT & ADDRESS)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 mb-1">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" value="{{ old('phone', $patient->phone) }}" inputmode="numeric" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 mb-1">ชื่อ-สกุล บิดา</label>
                            <input type="text" name="father_name" value="{{ old('father_name', $patient->father_name) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 mb-1">บ้านเลขที่</label>
                            <input type="text" name="house_no" value="{{ old('house_no', $patient->house_no) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 mb-1">ชื่อ-สกุล มารดา</label>
                            <input type="text" name="mother_name" value="{{ old('mother_name', $patient->mother_name) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">หมู่ที่</label>
                            <input type="text" name="village_no" value="{{ old('village_no', $patient->village_no) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">ตำบล</label>
                            <input type="text" name="sub_district" value="{{ old('sub_district', $patient->sub_district) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">อำเภอ</label>
                            <input type="text" name="district" value="{{ old('district', $patient->district) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">จังหวัด</label>
                            <input type="text" name="province" value="{{ old('province', $patient->province) }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-amber-500 focus:border-amber-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                    </div>
                </div>

                <!-- 🌟 4. ข้อมูลทหาร (หน้า Edit) -->
                <div class="bg-slate-50/50 p-6 md:p-8 rounded-2xl border border-slate-100">
                    <h3 class="text-sm font-bold text-teal-600 uppercase tracking-widest mb-4 border-b border-teal-100 pb-3 flex items-center gap-2">
                        <i class="fa-solid fa-tent"></i> ข้อมูลสังกัดทหาร (MILITARY INFO)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">รหัสประจำตัวทหาร</label>
                            <input type="text" name="military_id" value="{{ old('military_id', $patient->military_id) }}" placeholder="ปล่อยว่างได้หากยังไม่มี" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-teal-500 focus:border-teal-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">สังกัดปลายทาง (กรม / กองพัน) <span class="text-slate-400 font-normal">(เช่น ร.3 พัน.1)</span></label>
                            <input type="text" name="battalion" value="{{ old('battalion', $patient->battalion) }}" placeholder="ระบุสังกัดเมื่อฝึกจบ" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-teal-500 focus:border-teal-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">กองร้อย / หน่วยฝึกปัจจุบัน <span class="text-rose-500">*</span></label>
                            <select name="military_unit_id" required class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-teal-500 focus:border-teal-500 rounded-xl shadow-sm text-sm transition-all">
                                <option value="">-- เลือกหน่วยฝึก --</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}" {{ old('military_unit_id', $patient->military_unit_id) == $unit->id ? 'selected' : '' }}>
                                        {{ $unit->name }} @if($unit->short_name) ({{ $unit->short_name }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">ผลัดทหาร (ถ้ามี)</label>
                            <select name="intake_batch" class="w-full px-4 py-2.5 bg-white border border-slate-200 focus:ring-teal-500 focus:border-teal-500 rounded-xl shadow-sm text-sm transition-all">
                                <option value="">- ไม่ระบุ -</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch }}" {{ old('intake_batch', $patient->intake_batch) == $batch ? 'selected' : '' }}>
                                        ผลัดที่ {{ $batch }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 mb-1">ชื่อค่ายทหาร</label>
                            <input type="text" name="camp_name" value="{{ old('camp_name', $patient->camp_name ?? 'ค่ายกฤษณ์สีวะรา') }}" class="w-full md:w-1/2 px-4 py-2.5 bg-white border border-slate-200 focus:ring-teal-500 focus:border-teal-500 rounded-xl shadow-sm text-sm transition-all">
                        </div>
                    </div>

                    <!-- โซนข้อมูลการรับราชการ -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                        <div>
                            <label for="volunteer_status" class="block text-sm font-bold text-slate-700 mb-1">การเข้ารับราชการ</label>
                            <select name="volunteer_status" id="volunteer_status" class="w-full rounded-xl border-slate-300 focus:ring-emerald-500 text-sm">
                                <option value="">-- เลือกสถานะ --</option>
                                <option value="ร้องขอ" {{ old('volunteer_status', $patient->volunteer_status ?? '') == 'ร้องขอ' ? 'selected' : '' }}>ร้องขอ</option>
                                <option value="จับได้สลากแดง" {{ old('volunteer_status', $patient->volunteer_status ?? '') == 'จับได้สลากแดง' ? 'selected' : '' }}>จับได้สลากแดง</option>
                            </select>
                        </div>

                        <div>
                            <label for="privilege_reduction" class="block text-sm font-bold text-slate-700 mb-1">การขอสิทธิลดหย่อน</label>
                            <select name="privilege_reduction" id="privilege_reduction" class="w-full rounded-xl border-slate-300 focus:ring-emerald-500 text-sm">
                                <option value="">-- เลือกสิทธิ --</option>
                                <option value="ขอสิทธิลด" {{ old('privilege_reduction', $patient->privilege_reduction ?? '') == 'ขอสิทธิลด' ? 'selected' : '' }}>ขอสิทธิลด (มีวุฒิฯ)</option>
                                <option value="ไม่ใช้สิทธิ" {{ old('privilege_reduction', $patient->privilege_reduction ?? '') == 'ไม่ใช้สิทธิ' ? 'selected' : '' }}>ไม่ใช้สิทธิ</option>
                            </select>
                        </div>

                        <div>
                            <label for="service_duration" class="block text-sm font-bold text-slate-700 mb-1">เวลารับราชการ</label>
                            <select name="service_duration" id="service_duration" class="w-full rounded-xl border-slate-300 focus:ring-emerald-500 text-sm">
                                <option value="">-- เลือกระยะเวลา --</option>
                                <option value="6 เดือน" {{ old('service_duration', $patient->service_duration ?? '') == '6 เดือน' ? 'selected' : '' }}>6 เดือน</option>
                                <option value="1 ปี" {{ old('service_duration', $patient->service_duration ?? '') == '1 ปี' ? 'selected' : '' }}>1 ปี</option>
                                <option value="2 ปี" {{ old('service_duration', $patient->service_duration ?? '') == '2 ปี' ? 'selected' : '' }}>2 ปี</option>
                            </select>
                        </div>
                        
                    </div>
                    <!-- 🌟 สถานะการรายงานตัว / สถานะกำลังพล (เพิ่มให้ครบตรงกับหน้า Create) -->
                    @php
                        $curDutyStat = old('duty_status', $patient->duty_status ?? 'active');
                    @endphp
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 p-4 bg-white border border-slate-200 rounded-xl">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                <i class="fa-solid fa-user-check text-indigo-500 mr-1"></i> สถานะการรายงานตัว / สถานะกำลังพล
                            </label>
                            <select name="duty_status" class="w-full rounded-xl border-slate-300 focus:ring-indigo-500 text-sm font-bold text-slate-700">
                                <option value="active" {{ $curDutyStat === 'active' ? 'selected' : '' }}>🟢 มารายงานตัว (ประจำการปกติ)</option>
                                <option value="absent" {{ $curDutyStat === 'absent' ? 'selected' : '' }}>🟠 ไม่มารายงานตัว / ขาดหนี</option>
                                <option value="imprisoned" {{ $curDutyStat === 'imprisoned' ? 'selected' : '' }}>🔴 ต้องโทษ / ดำเนินคดี</option>
                                <option value="deceased" {{ $curDutyStat === 'deceased' ? 'selected' : '' }}>⚫ เสียชีวิต</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">หมายเหตุสถานะกำลังพล (ถ้ามี)</label>
                            <input type="text" name="duty_status_note" value="{{ old('duty_status_note', $patient->duty_status_note ?? '') }}" placeholder="เช่น ไม่มารายงานตัวแรกรับ, เรือนจำ มทบ.29..." class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ปุ่มบันทึก -->
            <div class="flex items-center justify-end gap-4 pt-8 mt-4 border-t border-slate-100">
                <a href="{{ route('backend.patients.show', $patient->id) }}" class="px-6 py-3 text-slate-500 font-bold hover:bg-slate-100 rounded-xl transition-colors">ยกเลิก</a>
                <button type="submit" class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md transition-transform hover:-translate-y-1 flex items-center gap-2">
                    <i class="fa-solid fa-save"></i> บันทึกการแก้ไข
                </button>
            </div>
        </form>

    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ==========================================
    // 🌟 ระบบคำนวณ BMI อัตโนมัติ
    // ==========================================
    const heightInput = document.getElementById('calc_h');
    const weightInput = document.getElementById('calc_w');
    const bmiInput = document.getElementById('calc_bmi');

    function calculateBMI() {
        const heightCm = parseFloat(heightInput.value);
        const weightKg = parseFloat(weightInput.value);

        if (!isNaN(heightCm) && heightCm > 0 && !isNaN(weightKg) && weightKg > 0) {
            const heightMeter = heightCm / 100;
            const bmiValue = weightKg / (heightMeter * heightMeter);
            bmiInput.value = bmiValue.toFixed(2);

            bmiInput.classList.remove('text-slate-500', 'text-rose-500', 'text-amber-500', 'text-emerald-600', 'font-bold');
            
            if (bmiValue < 18.5) {
                bmiInput.classList.add('text-amber-500', 'font-bold');
            } else if (bmiValue >= 18.5 && bmiValue <= 22.9) {
                bmiInput.classList.add('text-emerald-600', 'font-bold');
            } else if (bmiValue > 22.9 && bmiValue <= 24.9) {
                bmiInput.classList.add('text-amber-500', 'font-bold');
            } else {
                bmiInput.classList.add('text-rose-500', 'font-bold');
            }
        } else {
            bmiInput.value = '';
            bmiInput.classList.remove('text-rose-500', 'text-amber-500', 'text-emerald-600', 'font-bold');
            bmiInput.classList.add('text-slate-500');
        }
    }

    if (heightInput && weightInput && bmiInput) {
        heightInput.addEventListener('input', calculateBMI);
        weightInput.addEventListener('input', calculateBMI);
        calculateBMI();
    }

    const btnSync = document.getElementById('btn_sync_hosxp');
    const searchInput = document.getElementById('search_hosxp_input');

    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            btnSync.click();
        }
    });

    btnSync.addEventListener('click', function() {
        const term = searchInput.value.trim();
        
        if(term.length < 5) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'ข้อมูลไม่ครบ', 
                text: 'กรุณากรอกเลขบัตร ปชช. หรือ รหัส HN ให้ถูกต้อง',
                heightAuto: false, 
                scrollbarPadding: false 
            });
            return;
        }

        Swal.fire({
            title: 'กำลังเชื่อมต่อ HosXP...',
            html: 'ระบบกำลังดึงข้อมูลและสิทธิการรักษาล่าสุดจากฐานข้อมูลโรงพยาบาล',
            allowOutsideClick: false,
            heightAuto: false, 
            scrollbarPadding: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);

        fetch(`{{ route('backend.api.hosxp.search') }}?term=${term}`, {
            signal: controller.signal
        })
            .then(response => {
                clearTimeout(timeoutId);
                return response.json();
            })
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'อัปเดตข้อมูลสำเร็จ!',
                        text: `ดึงประวัติล่าสุดของคุณ ${data.data.first_name} เรียบร้อยแล้ว` + (data.data.pttype_name ? ` (${data.data.pttype_name})` : ''),
                        timer: 1800,
                        showConfirmButton: false,
                        heightAuto: false, 
                        scrollbarPadding: false 
                    });

                    // เติมข้อมูลลงช่อง Input ด้านล่างให้อัตโนมัติ (รวมถึงรหัสสิทธิและชื่อสิทธิการรักษา)
                    document.getElementById('input_cid').value = data.data.cid || term; 
                    if(data.data.hn) document.getElementById('input_hn').value = data.data.hn;
                    if(data.data.rank) document.getElementById('input_rank').value = data.data.rank;
                    document.getElementById('input_fname').value = data.data.first_name || '';
                    document.getElementById('input_lname').value = data.data.last_name || '';
                    
                    if(data.data.dob && data.data.dob !== '0000-00-00') {
                        document.getElementById('input_dob').value = data.data.dob;
                    }

                    // 🌟 หยอดข้อมูลสิทธิการรักษาพยาบาล
                    if(data.data.pttype_code !== undefined) {
                        document.getElementById('input_pttype_code').value = data.data.pttype_code || '';
                    }
                    if(data.data.pttype_name !== undefined) {
                        document.getElementById('input_pttype_name').value = data.data.pttype_name || '';
                    }
                    
                    if(data.data.blood_group) {
                        let cleanBloodGroup = data.data.blood_group.toUpperCase().replace(/[\s+-]/g, '');
                        if (['A', 'B', 'O', 'AB'].includes(cleanBloodGroup)) {
                            document.getElementById('input_blood').value = cleanBloodGroup;
                        } else {
                            document.getElementById('input_blood').value = ''; 
                        }
                    }
                    
                    ['input_cid', 'input_hn', 'input_rank', 'input_fname', 'input_lname', 'input_dob', 'input_blood', 'input_pttype_code', 'input_pttype_name'].forEach(id => {
                        let el = document.getElementById(id);
                        if(el && el.value) {
                            el.classList.add('ring-2', 'ring-emerald-400', 'bg-emerald-50');
                            setTimeout(() => el.classList.remove('ring-2', 'ring-emerald-400', 'bg-emerald-50'), 2000);
                        }
                    });

                } else if(data.status === 'not_found') {
                    Swal.fire({ icon: 'info', title: 'ไม่พบประวัติ', text: data.message, heightAuto: false, scrollbarPadding: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message, heightAuto: false, scrollbarPadding: false });
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error(error);
                
                let errorText = 'เครือข่ายมีปัญหา หรือเซิร์ฟเวอร์โรงพยาบาลไม่ตอบสนอง';
                if (error.name === 'AbortError') {
                    errorText = 'การเชื่อมต่อใช้เวลานานเกินไป (Timeout) เซิร์ฟเวอร์ HosXP อาจกำลังทำงานหนัก';
                }

                Swal.fire({ 
                    icon: 'error', 
                    title: 'การเชื่อมต่อล้มเหลว', 
                    text: errorText, 
                    heightAuto: false, 
                    scrollbarPadding: false 
                });
            });
    });
});
function toggleFormChronicDetail(val) {
    const box = document.getElementById('form_chronic_detail_box');
    const input = document.getElementById('input_chronic_detail');
    if (!box || !input) return;
    if (val === 'yes' || val === 'other') {
        box.classList.remove('hidden');
        input.placeholder = val === 'yes' 
            ? 'ระบุโรคประจำตัว เช่น หอบหืด, ลมชัก, โรคหัวใจ...' 
            : 'ระบุข้อมูลอื่นๆ เช่น แพ้ยา/อาหาร, เคยผ่าตัด/ดามเหล็ก...';
        input.focus();
    } else {
        box.classList.add('hidden');
        input.value = '';
    }
}
</script>
@endsection