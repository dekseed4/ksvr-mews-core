@extends('layouts.backend')

@section('title', 'แดชบอร์ด - KSVR Hospital')
@section('page_header', 'ภาพรวมระบบ (Dashboard)')

@section('content')
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
            <i class="fa-solid fa-circle-exclamation mt-1"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
            <i class="fa-solid fa-circle-check mt-1"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Card 1 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100 border-l-4 border-l-emerald-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-slate-500 font-medium mb-1">ผู้ป่วยที่ประเมินวันนี้</p>
                    <h3 class="text-3xl font-bold text-slate-800">{{ number_format($todayAssessments) }}</h3>
                </div>
                <div class="p-3 bg-emerald-50 text-emerald-500 rounded-lg">
                    <i class="fa-solid fa-hospital-user text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-emerald-500 mt-4 flex items-center gap-1">
                <i class="fa-solid fa-calendar-day"></i> อัปเดตข้อมูลแบบ Real-time
            </p>
        </div>

        <!-- Card 2 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100 border-l-4 border-l-red-500 relative overflow-hidden">
            @if($highRiskCases > 0)
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-red-50 rounded-full opacity-50 animate-pulse"></div>
            @endif
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-sm text-slate-500 font-medium mb-1">ผู้ป่วยความเสี่ยงสูง (MEWS)</p>
                    <h3 class="text-3xl font-bold {{ $highRiskCases > 0 ? 'text-red-600' : 'text-slate-800' }}">{{ number_format($highRiskCases) }}</h3>
                </div>
                <div class="p-3 bg-red-50 text-red-500 rounded-lg">
                    <i class="fa-solid fa-heart-pulse text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-red-500 mt-4 flex items-center gap-1 relative z-10">
                <i class="fa-solid fa-circle-exclamation"></i> ต้องการการดูแลด่วน
            </p>
        </div>

        <!-- Card 3 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100 border-l-4 border-l-blue-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-slate-500 font-medium mb-1">ผู้ใช้งานในระบบ</p>
                    <h3 class="text-3xl font-bold text-slate-800">{{ number_format($totalUsers) }}</h3>
                </div>
                <div class="p-3 bg-blue-50 text-blue-500 rounded-lg">
                    <i class="fa-solid fa-user-doctor text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-4">แพทย์ และพยาบาล</p>
        </div>

        <!-- Card 4 -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100 border-l-4 {{ $hosxpStatus == 'connected' ? 'border-l-amber-500' : 'border-l-slate-400' }}">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-slate-500 font-medium mb-1">สถานะฐานข้อมูล HosXP</p>
                    <h3 class="text-xl font-bold {{ $hosxpStatus == 'connected' ? 'text-emerald-600' : 'text-slate-500' }} mt-2">
                        @if($hosxpStatus == 'connected')
                            <i class="fa-solid fa-link text-sm mr-1"></i> เชื่อมต่อปกติ
                        @else
                            <i class="fa-solid fa-link-slash text-sm mr-1"></i> ไม่ได้เชื่อมต่อ
                        @endif
                    </h3>
                </div>
                <div class="p-3 {{ $hosxpStatus == 'connected' ? 'bg-amber-50 text-amber-500' : 'bg-slate-50 text-slate-400' }} rounded-lg">
                    <i class="fa-solid fa-database text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-4">ตรวจสอบสถานะแบบ Real-time</p>
        </div>
    </div>

    <!-- ส่วนตารางข้อมูลล่าสุด -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-slate-400"></i> รายการประเมินสัญญาณชีพล่าสุด
            </h3>
            <a href="{{ route('backend.patients.index') }}" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium bg-emerald-50 px-3 py-1.5 rounded-lg transition-colors">ดูรายชื่อทั้งหมด</a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-slate-600">
                <thead class="text-xs text-slate-500 uppercase bg-white border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">เวลาประเมิน</th>
                        <th class="px-6 py-4">ผู้ป่วย / สังกัด</th>
                        <th class="px-6 py-4 text-center">คะแนน MEWS</th>
                        <th class="px-6 py-4 text-center">สถานะ</th>
                        <th class="px-6 py-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentAssessments as $mews)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-slate-500">
                                <div class="font-medium text-slate-700">{{ $mews->created_at->format('d/m/Y H:i') }}</div>
                                <div class="text-xs mt-1">{{ $mews->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800">{{ $mews->patient->rank }} {{ $mews->patient->first_name }} {{ $mews->patient->last_name }}</div>
                                <div class="text-xs text-slate-500 mt-1"><i class="fa-solid fa-tent text-[10px] mr-1"></i> {{ $mews->patient->company ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-lg font-black {{ $mews->risk_level === 'emergency' ? 'text-red-500' : ($mews->risk_level === 'observe' ? 'text-amber-500' : 'text-emerald-500') }}">
                                    {{ $mews->total_score }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($mews->risk_level === 'emergency')
                                    <span class="bg-red-50 text-red-600 px-3 py-1 rounded-full text-xs font-bold border border-red-100">ฉุกเฉิน</span>
                                @elseif($mews->risk_level === 'observe')
                                    <span class="bg-amber-50 text-amber-600 px-3 py-1 rounded-full text-xs font-bold border border-amber-100">เฝ้าระวัง</span>
                                @else
                                    <span class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-xs font-bold border border-emerald-100">ปกติ</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap space-x-1">
                                <!-- ปุ่ม View -->
                                <a href="{{ route('backend.mews.show', $mews->id) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-500 hover:text-white transition-colors" title="ดูรายละเอียด">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                
                                @if(in_array('admin', auth()->user()->roles ?? []))
                                    <!-- ปุ่ม Edit -->
                                    <a href="{{ route('backend.mews.edit', $mews->id) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-colors" title="แก้ไข">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <!-- ปุ่ม Delete -->
                                    <form action="{{ route('backend.mews.destroy', $mews->id) }}" method="POST" class="inline-block" id="delete-mews-{{ $mews->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" onclick="confirmAction(event, 'ยืนยันการลบ?', 'ประวัติการประเมินนี้จะถูกลบ', 'warning', 'delete-mews-{{ $mews->id }}')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-colors" title="ลบข้อมูล">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <!-- กรณีที่ยังไม่มีข้อมูล จะแสดงกล่องข้อความเดิมที่คุณออกแบบไว้ -->
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-500 py-12">
                                <i class="fa-regular fa-folder-open text-4xl mb-3 text-slate-300"></i>
                                <p>ยังไม่มีข้อมูลแสดงผลในขณะนี้</p>
                                <p class="text-sm">ข้อมูลจะแสดงเมื่อมีการบันทึกจากเสนารักษ์/พยาบาล</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection