@extends('layouts.backend')

@section('title', 'แก้ไขหน่วยทหาร - KSVR Hospital')
@section('page_header', 'แก้ไขข้อมูลกองร้อย/หน่วยฝึก')

@section('content')
<div class="max-w-3xl mx-auto mt-4">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        
        <!-- ส่วนหัวฟอร์ม -->
        <div class="px-8 py-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800">แก้ไขข้อมูลหน่วยทหาร</h2>
            </div>
            <span class="text-xs font-mono text-slate-400 bg-white px-2 py-1 rounded border border-slate-200">ID: {{ $militaryUnit->id }}</span>
        </div>

        <div class="p-8">
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation mt-1"></i>
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('backend.military-units.update', $militaryUnit->id) }}" method="POST">
                @csrf 
                @method('PUT') <!-- สำคัญมาก สำหรับการแก้ไข -->

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- ชื่อเต็ม -->
                    <div class="md:col-span-2">
                        <label for="name" class="block text-slate-700 text-sm font-bold mb-2">ชื่อหน่วย (เต็ม) <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $militaryUnit->name) }}" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all" required>
                    </div>

                    <!-- ชื่อย่อ -->
                    <div class="md:col-span-1">
                        <label for="short_name" class="block text-slate-700 text-sm font-bold mb-2">ชื่อย่อ</label>
                        <input type="text" name="short_name" id="short_name" value="{{ old('short_name', $militaryUnit->short_name) }}" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                    </div>

                    <!-- กองพันที่สังกัด -->
                    <div class="md:col-span-1">
                        <label for="battalion" class="block text-slate-700 text-sm font-bold mb-2">สังกัดกองพัน/กรม</label>
                        <input type="text" name="battalion" id="battalion" value="{{ old('battalion', $militaryUnit->battalion) }}" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                    </div>

                    <!-- สถานะการใช้งาน -->
                    <div class="md:col-span-2 p-5 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-slate-800">สถานะการใช้งาน</h4>
                            <p class="text-xs text-slate-500 mt-1">หากปิดใช้งาน หน่วยนี้จะไม่แสดงในตัวเลือกตอนเพิ่มบุคลากรหรือคนไข้</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <!-- เช็คค่าเก่าจาก Database -->
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $militaryUnit->is_active) == '1' ? 'checked' : '' }}>
                            <div class="w-14 h-7 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                </div>

                <!-- แถบปุ่มกดด้านล่าง -->
                <div class="flex flex-col-reverse md:flex-row justify-between items-center gap-4 pt-6 border-t border-slate-100">
                    <a href="{{ route('backend.military-units.index') }}" class="w-full md:w-auto px-6 py-3 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl font-medium transition-colors flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrow-left"></i> ย้อนกลับ
                    </a>
                    
                    <button type="submit" class="w-full md:w-auto px-8 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 shadow-sm transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection