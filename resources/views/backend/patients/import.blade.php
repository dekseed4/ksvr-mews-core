@extends('layouts.backend')
@section('page_header', 'นำเข้าข้อมูลพลทหาร (Excel)')

@section('content')
<div class="max-w-3xl mx-auto mt-8 bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
    
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-bold text-slate-800">อัปโหลดรายชื่อจากหน่วยทหาร</h2>
            <p class="text-sm text-slate-500 mt-1">รองรับไฟล์ .xlsx, .xls หรือ .csv ขนาดไม่เกิน 5MB</p>
        </div>
        
        <!-- 🌟 เพิ่มปุ่มดาวน์โหลด Template ตรงนี้ครับ -->
        <a href="{{ asset('downloads/military_patient_template.xlsx') }}" download
           class="px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-sm font-semibold hover:bg-emerald-100 hover:text-emerald-800 transition-colors flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-download"></i> โหลด Template
        </a>
    </div>

    <form action="{{ route('backend.patients.import.process') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <!-- โซนเลือกหน่วยทหาร -->
        <!-- โซนตั้งค่าข้อมูลพื้นฐาน (หน่วย และ ผลัด) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 bg-slate-50 p-6 rounded-2xl border border-slate-100">
            
            <!-- 1. เลือกสังกัด -->
            <div>
                <label for="military_unit_id" class="block text-slate-700 text-sm font-bold mb-2">
                    <i class="fa-solid fa-tent text-emerald-600 mr-1"></i> เลือกสังกัด / กองร้อย <span class="text-red-500">*</span>
                </label>
                <select name="military_unit_id" id="military_unit_id" required
                        class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-emerald-500 focus:border-emerald-500 transition-colors shadow-sm">
                    <option value="">-- กรุณาเลือกสังกัด --</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" {{ old('military_unit_id', auth()->user()->military_unit_id) == $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }} @if($unit->short_name) ({{ $unit->short_name }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('military_unit_id')
                    <p class="text-red-500 text-sm mt-2"><i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $message }}</p>
                @enderror
            </div>

            <!-- 2. เลือกผลัดทหาร -->
            <div>
                <label for="intake_batch" class="block text-slate-700 text-sm font-bold mb-2">
                    <i class="fa-solid fa-users text-emerald-600 mr-1"></i> เลือกผลัดทหาร <span class="text-red-500">*</span>
                </label>
                <select name="intake_batch" id="intake_batch" required
                        class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-emerald-500 focus:border-emerald-500 transition-colors shadow-sm">
                    <option value="">-- กรุณาเลือกผลัดทหาร --</option>
                    @foreach($batches as $batch)
                        <option value="{{ $batch }}" {{ old('intake_batch', auth()->user()->intake_batch) == $batch ? 'selected' : '' }}>
                            ผลัดที่ {{ $batch }}
                        </option>
                    @endforeach
                </select>
                @error('intake_batch')
                    <p class="text-red-500 text-sm mt-2"><i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $message }}</p>
                @enderror
            </div>
            
            <div class="md:col-span-2 text-xs text-slate-500 bg-blue-50/50 p-3 rounded-lg border border-blue-100/50">
                <i class="fa-solid fa-circle-info text-blue-500 mr-1"></i> 
                <b>ข้อแนะนำ:</b> ข้อมูลสังกัดและผลัดที่คุณเลือกที่นี่ จะถูกนำไปใช้กับ "ทุกคน" ที่อยู่ในไฟล์ Excel นี้โดยอัตโนมัติ (ไม่ต้องกรอกคอลัมน์นี้ใน Excel ก็ได้)
            </div>
        </div>
        <!-- โซนอัปโหลดไฟล์ (เพิ่ม id="drop_zone") -->
        <div id="drop_zone" class="border-2 border-dashed border-slate-300 rounded-xl p-10 text-center hover:bg-slate-50 transition-all duration-300 cursor-pointer relative overflow-hidden">
            
            <!-- 🌟 เพิ่ม id="excel_file_input" และ z-50 เพื่อให้อยู่บนสุดเสมอ -->
            <input type="file" name="excel_file" id="excel_file_input" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-50" required accept=".xlsx, .xls, .csv">
            
            <div class="text-emerald-500 text-4xl mb-3">
                <i id="upload_icon" class="fa-solid fa-file-excel transition-transform duration-300"></i>
            </div>
            
            <!-- โซนข้อความแนะนำการอัปโหลด -->
            <div id="file_name_display">
                <p class="text-slate-700 font-bold text-lg mb-1">ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</p>
                
                <!-- 🌟 กล่องข้อความแนะนำ (UI ใหม่ สไตล์ Modern) -->
                <div class="mt-4 text-left bg-blue-50/50 border border-blue-100 rounded-xl p-4 text-sm text-slate-600 inline-block max-w-xl mx-auto shadow-sm">
                    <p class="font-bold text-blue-700 mb-2 border-b border-blue-200/50 pb-2">
                        <i class="fa-solid fa-circle-info mr-1"></i> คำแนะนำการเตรียมไฟล์ Excel (ใช้หัวคอลัมน์ตามเทมเพลต)
                    </p>
                    <ul class="space-y-2">
                        <li><span class="font-bold text-slate-800">id <span class="text-rose-500">*</span>:</span> เลข ปชช. 13 หลัก <span class="text-rose-500 text-[10px] bg-rose-50 px-1 py-0.5 rounded border border-rose-100">**แนะนำให้ตั้ง Format เซลล์เป็น Text (ข้อความ)**</span></li>
                        <li><span class="font-bold text-slate-800">ชื่อ / สกุล <span class="text-rose-500">*</span>:</span> ชื่อและนามสกุลจริง (ไม่ต้องใส่ยศ/คำนำหน้า)</li>
                        <li><span class="font-bold text-slate-800">หน่วยฝึก:</span> ชื่อหน่วยฝึกปัจจุบัน (ถ้าระบุตรงกับในระบบ ระบบจะแยกหน่วยให้อัตโนมัติแม้จะเลือก Dropdown ผิด)</li>
                        <li><span class="font-bold text-slate-800">ส่งแผนก:</span> สังกัดปลายทางหลังจบการฝึก (เช่น ร.3 พัน.1)</li>
                        <li><span class="font-bold text-slate-800">BMI:</span> สามารถใส่จุดทศนิยมหลายตำแหน่งได้ ระบบจะปัดเศษเหลือ 2 ตำแหน่งให้เอง</li>
                    </ul>
                </div>
            </div>
        </div>

        @error('excel_file')
            <p class="text-red-500 text-sm mt-2"><i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $message }}</p>
        @enderror

        <div class="mt-6 flex justify-end">
            <button type="submit" class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-medium shadow-sm transition-all flex items-center gap-2">
                <i class="fa-solid fa-cloud-arrow-up"></i> เริ่มนำเข้าข้อมูล
            </button>
        </div>
    </form>
</div>
@endsection
@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('excel_file_input');
    const fileDisplay = document.getElementById('file_name_display');
    const dropZone = document.getElementById('drop_zone');
    const uploadIcon = document.getElementById('upload_icon');

    // เก็บข้อความ HTML เดิมเอาไว้เผื่อกดยกเลิก
    const defaultText = fileDisplay.innerHTML;

    // 1. ฟังก์ชันทำงานเมื่อมีการเลือกไฟล์ (ลากมาวาง หรือ คลิกเลือก)
    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files.length > 0) {
            const file = this.files[0];
            const fileSize = (file.size / 1024 / 1024).toFixed(2); // แปลงหน่วยเป็น MB
            
            // อัปเดต UI ให้แสดงชื่อไฟล์สีเขียว
            fileDisplay.innerHTML = `
                <div class="text-emerald-600 font-bold text-lg mb-1">
                    <i class="fa-solid fa-circle-check mr-2"></i> เตรียมอัปโหลดไฟล์
                </div>
                <div class="text-slate-700 font-medium">${file.name}</div>
                <div class="text-xs text-slate-500 mt-1">ขนาด: ${fileSize} MB</div>
            `;
            
            // เปลี่ยนสีกรอบกล่อง
            dropZone.classList.add('border-emerald-400', 'bg-emerald-50');
            dropZone.classList.remove('border-slate-300', 'hover:bg-slate-50');
            uploadIcon.classList.add('scale-110');
        } else {
            // ถ้ากดยกเลิก ให้คืนค่าหน้าตาเดิม
            fileDisplay.innerHTML = defaultText;
            dropZone.classList.remove('border-emerald-400', 'bg-emerald-50');
            dropZone.classList.add('border-slate-300', 'hover:bg-slate-50');
            uploadIcon.classList.remove('scale-110');
        }
    });

    // 2. เพิ่มลูกเล่น (UX) ตอนลากไฟล์มาวนอยู่เหนือกล่อง
    fileInput.addEventListener('dragenter', function() {
        dropZone.classList.add('border-emerald-400', 'bg-emerald-50/50', 'scale-[1.02]');
    });

    fileInput.addEventListener('dragleave', function() {
        dropZone.classList.remove('border-emerald-400', 'bg-emerald-50/50', 'scale-[1.02]');
    });

    fileInput.addEventListener('drop', function() {
        dropZone.classList.remove('border-emerald-400', 'bg-emerald-50/50', 'scale-[1.02]');
    });
});
</script>
@endsection