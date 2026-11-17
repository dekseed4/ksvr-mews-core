@extends('layouts.backend')

@section('title', 'บังคับเปลี่ยนรหัสผ่าน - KSVR Hospital')
@section('page_header', 'เปลี่ยนรหัสผ่านเพื่อความปลอดภัย')

@section('content')
<div class="max-w-md mx-auto mt-10 mb-20">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        
        <!-- ส่วนหัว (Header) -->
        <div class="px-8 pt-8 pb-6 text-center border-b border-slate-50">
            <div class="w-16 h-16 bg-yellow-50 text-yellow-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-shield-halved text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-800">ตั้งรหัสผ่านใหม่</h2>
            <p class="text-slate-500 text-sm mt-2 leading-relaxed">
                เพื่อความปลอดภัยของระบบสารสนเทศทางการแพทย์ กรุณาเปลี่ยนรหัสผ่านชั่วคราวของคุณก่อนเริ่มใช้งานระบบ
            </p>
        </div>

        <!-- แจ้งเตือนข้อผิดพลาด (Validation Errors) -->
        @if ($errors->any())
            <div class="mx-8 mt-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-start gap-3">
                <i class="fa-solid fa-circle-exclamation mt-1"></i>
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-8 mt-6 bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-xl flex items-start gap-3 text-sm">
                <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        <!-- ฟอร์มเปลี่ยนรหัสผ่าน -->
        <div class="p-8">
            <form action="{{ route('password.force.update') }}" method="POST">
                @csrf

                <!-- ช่องกรอกรหัสผ่านใหม่ -->
                <div class="mb-5">
                    <label for="password" class="block text-slate-700 text-sm font-semibold mb-2">
                        รหัสผ่านใหม่ (New Password) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-slate-400"></i>
                        </div>
                        <input type="password" name="password" id="password" 
                               class="w-full pl-11 pr-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all" 
                               placeholder="ความยาวอย่างน้อย 6 ตัวอักษร" required autofocus>
                    </div>
                </div>

                <!-- ช่องยืนยันรหัสผ่านใหม่ -->
                <div class="mb-8">
                    <label for="password_confirmation" class="block text-slate-700 text-sm font-semibold mb-2">
                        ยืนยันรหัสผ่านใหม่ (Confirm Password) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fa-solid fa-check-double text-slate-400"></i>
                        </div>
                        <input type="password" name="password_confirmation" id="password_confirmation" 
                               class="w-full pl-11 pr-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all" 
                               placeholder="กรอกรหัสผ่านใหม่อีกครั้งให้ตรงกัน" required>
                    </div>
                </div>

                <!-- ปุ่ม Submit -->
                <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-sm hover:shadow transition-all flex justify-center items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    บันทึกรหัสผ่านและเข้าสู่ระบบ
                </button>
            </form>
        </div>

    </div>
</div>
@endsection