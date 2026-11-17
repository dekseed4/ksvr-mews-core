@extends('layouts.backend')

@section('title', 'จัดการรายชื่อหน่วยทหาร - KSVR Hospital')
@section('page_header', 'การจัดการข้อมูลกองร้อย/หน่วยฝึก')

@section('content')
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    
    <!-- แถบด้านบนของตาราง -->
    <div class="px-6 py-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50/50">
        <h3 class="font-bold text-slate-800 text-lg flex items-center">
            <i class="fa-solid fa-tents text-emerald-600 mr-3 text-xl"></i>
            รายชื่อหน่วยทหารในระบบ
        </h3>
        <a href="{{ route('backend.military-units.create') }}" class="w-full md:w-auto px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 shadow-sm hover:shadow transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-plus"></i> เพิ่มหน่วยทหาร
        </a>
    </div>

    <!-- ตารางข้อมูล -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-sm border-b border-slate-200">
                    <th class="px-6 py-4 font-semibold w-16 text-center">#</th>
                    <th class="px-6 py-4 font-semibold">ชื่อหน่วย (เต็ม)</th>
                    <th class="px-6 py-4 font-semibold">ชื่อย่อ (ใช้แสดงผล)</th>
                    <th class="px-6 py-4 font-semibold">กองพัน/กรม</th>
                    <th class="px-6 py-4 font-semibold text-center">สถานะ</th>
                    <th class="px-6 py-4 font-semibold text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-slate-100">
                @forelse($militaryUnits as $index => $unit)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="px-6 py-4 text-slate-500 text-center">{{ $index + 1 }}</td>
                        
                        <td class="px-6 py-4">
                            <span class="font-bold text-slate-800">{{ $unit->name }}</span>
                        </td>
                        
                        <td class="px-6 py-4">
                            @if($unit->short_name)
                                <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-lg font-medium text-xs">{{ $unit->short_name }}</span>
                            @else
                                <span class="text-slate-400 italic text-xs">-</span>
                            @endif
                        </td>
                        
                        <td class="px-6 py-4 text-slate-600">
                            {{ $unit->battalion ?? '-' }}
                        </td>
                        
                        <td class="px-6 py-4 text-center">
                            @if($unit->is_active)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> เปิดใช้งาน
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> ปิดใช้งาน
                                </span>
                            @endif
                        </td>
                        
                        <td class="px-6 py-4 text-center space-x-3">
                            <a href="{{ route('backend.military-units.edit', $unit->id) }}" class="inline-block text-blue-500 hover:text-blue-700 transition-colors" title="แก้ไข">
                                <i class="fa-solid fa-pen-to-square text-lg"></i>
                            </a>
                            
                            <form id="delete-form-{{ $unit->id }}" action="{{ route('backend.military-units.destroy', $unit->id) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="button" 
                                        onclick="confirmAction(event, 'ยืนยันการลบข้อมูล', 'คุณต้องการลบข้อมูล {{ $unit->name }} ใช่หรือไม่? (ระวัง: หากมีข้อมูลผูกอยู่ อาจเกิดข้อผิดพลาด)', 'error', 'delete-form-{{ $unit->id }}')" 
                                        class="text-red-500 hover:text-red-700 transition-colors" title="ลบ">
                                    <i class="fa-solid fa-trash-can text-lg"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center text-slate-400">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                    <i class="fa-solid fa-tents text-2xl text-slate-300"></i>
                                </div>
                                <p class="text-base font-medium text-slate-500">ยังไม่มีข้อมูลหน่วยทหารในระบบ</p>
                                <p class="text-sm mt-1">คลิกที่ปุ่ม "เพิ่มหน่วยทหาร" เพื่อเริ่มต้นสร้างฐานข้อมูล</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection