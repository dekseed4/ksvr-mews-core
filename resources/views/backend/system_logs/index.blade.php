@extends('layouts.backend')

@section('title', 'ประวัติการใช้งานระบบ (Audit Log) - KSVR Hospital')
@section('page_header', 'ประวัติการใช้งานระบบ (Audit Log)')

@section('content')
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    
    <!-- แถบด้านบนของตาราง & ช่องค้นหา -->
    <div class="px-6 py-4 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50/50">
        <h3 class="font-bold text-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-list-check text-indigo-500"></i> บันทึกเหตุการณ์ระบบ
        </h3>
        
        <form action="{{ route('backend.system_logs.index') }}" method="GET" class="w-full md:w-80 relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <input type="text" name="search" value="{{ request('search') }}" 
                   class="bg-white border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2.5 shadow-sm" 
                   placeholder="ค้นหาชื่อผู้ใช้, เหตุการณ์...">
        </form>
    </div>

    <!-- ตารางข้อมูล -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="text-xs text-slate-500 uppercase bg-slate-50/80 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-4 font-semibold">วันเวลา</th>
                    <th class="px-6 py-4 font-semibold">ผู้ใช้งาน</th>
                    <th class="px-6 py-4 font-semibold">เหตุการณ์ (Action)</th>
                    <th class="px-6 py-4 font-semibold">เป้าหมาย (Target)</th>
                    <th class="px-6 py-4 font-semibold text-center">รายละเอียด</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                
                @php
                    // 🌟 พจนานุกรมแปลงค่า Action ให้เป็นภาษาไทย + สีป้าย Badge
                    $actionDict = [
                        'create_patient' => ['text' => 'เพิ่มประวัติผู้ป่วยใหม่', 'color' => 'bg-emerald-100 text-emerald-700', 'icon' => 'fa-user-plus'],
                        'search_hosxp_success' => ['text' => 'ดึงข้อมูล HosXP สำเร็จ', 'color' => 'bg-blue-100 text-blue-700', 'icon' => 'fa-cloud-arrow-down'],
                        'search_hosxp_not_found' => ['text' => 'ค้นหา HosXP ไม่พบ', 'color' => 'bg-amber-100 text-amber-700', 'icon' => 'fa-magnifying-glass-minus'],
                        'search_hosxp_error' => ['text' => 'เชื่อมต่อ HosXP ล้มเหลว', 'color' => 'bg-rose-100 text-rose-700', 'icon' => 'fa-triangle-exclamation'],
                        'create_user' => ['text' => 'สร้างผู้ใช้ใหม่', 'color' => 'bg-emerald-100 text-emerald-700', 'icon' => 'fa-user-plus'],
                        'update_user' => ['text' => 'แก้ไขข้อมูลผู้ใช้', 'color' => 'bg-amber-100 text-amber-700', 'icon' => 'fa-user-pen'],
                        'reset_password' => ['text' => 'รีเซ็ตรหัสผ่าน', 'color' => 'bg-purple-100 text-purple-700', 'icon' => 'fa-key'],
                        'delete_user' => ['text' => 'ลบผู้ใช้', 'color' => 'bg-rose-100 text-rose-700', 'icon' => 'fa-user-minus'],
                        'update_patient' => ['text' => 'แก้ไขประวัติผู้ป่วย', 'color' => 'bg-amber-100 text-amber-700', 'icon' => 'fa-user-pen'],
                        'delete_patient' => ['text' => 'ลบประวัติผู้ป่วย', 'color' => 'bg-rose-100 text-rose-700', 'icon' => 'fa-trash-can'],
                        'bulk_delete_patients' => ['text' => 'ลบประวัติผู้ป่วย (แบบกลุ่ม)', 'color' => 'bg-red-100 text-red-800', 'icon' => 'fa-dumpster-fire'],
                        'create_mews' => ['text' => 'บันทึกประเมิน MEWS', 'color' => 'bg-indigo-100 text-indigo-700', 'icon' => 'fa-stethoscope'],
                        'update_mews' => ['text' => 'แก้ไขผล MEWS', 'color' => 'bg-orange-100 text-orange-700', 'icon' => 'fa-file-signature'],
                        'delete_mews' => ['text' => 'ลบผลประเมิน MEWS', 'color' => 'bg-red-100 text-red-700', 'icon' => 'fa-file-circle-xmark'],
                        'import_patients_excel' => ['text' => 'นำเข้ารายชื่อผ่าน Excel', 'color' => 'bg-teal-100 text-teal-700', 'icon' => 'fa-file-excel'],
                        'discharge_patient' => ['text' => 'จำหน่ายคนไข้ (จบเคส)', 'color' => 'bg-slate-200 text-slate-700', 'icon' => 'fa-door-open'],
                        'create_military_unit' => ['text' => 'เพิ่มหน่วยทหาร', 'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'fa-tent-arrow-down-to-line'],
                        'update_military_unit' => ['text' => 'แก้ไขหน่วยทหาร', 'color' => 'bg-amber-50 text-amber-700 border-amber-200', 'icon' => 'fa-pen-to-square'],
                        'delete_military_unit' => ['text' => 'ลบหน่วยทหาร', 'color' => 'bg-rose-50 text-rose-700 border-rose-200', 'icon' => 'fa-tent-arrow-turn-left'],
                        ];
                @endphp

                @forelse($logs as $log)
                    @php
                        // เช็คว่า Action นี้มีในพจนานุกรมไหม ถ้าไม่มีให้ใช้ค่า Default สีเทา
                        $actionData = $actionDict[$log->action] ?? ['text' => $log->action, 'color' => 'bg-slate-100 text-slate-600', 'icon' => 'fa-bolt'];
                    @endphp
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <!-- 1. วันเวลา -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium text-slate-800">{{ $log->created_at->addYears(543)->format('d/m/Y') }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ $log->created_at->format('H:i:s') }} น.</div>
                        </td>
                        
                        <!-- 2. ผู้ใช้งาน -->
                        <td class="px-6 py-4">
                            @if($log->user)
                                <div class="font-bold text-slate-700">{{ $log->user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $log->user->username }}</div>
                            @else
                                <span class="text-slate-400 italic">ระบบ (System)</span>
                            @endif
                        </td>
                        
                        <!-- 3. เหตุการณ์ -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold {{ $actionData['color'] }}">
                                <i class="fa-solid {{ $actionData['icon'] }} opacity-70"></i> {{ $actionData['text'] }}
                            </span>
                        </td>
                        
                        <!-- 4. เป้าหมาย -->
                        <td class="px-6 py-4">
                            @if($log->loggable_type)
                                <div class="text-xs font-mono text-slate-500 mb-0.5">{{ class_basename($log->loggable_type) }} (ID: {{ $log->loggable_id }})</div>
                                <!-- แสดงชื่อเป้าหมายถ้าเป็น Patient -->
                                @if(class_basename($log->loggable_type) === 'Patient' && $log->loggable)
                                    <div class="font-semibold text-slate-700 text-sm">{{ $log->loggable->first_name }} {{ $log->loggable->last_name }}</div>
                                @endif
                            @else
                                <span class="text-slate-300">-</span>
                            @endif
                        </td>
                        
                        <!-- 5. ปุ่มดูรายละเอียด (Payload) -->
                        <td class="px-6 py-4 text-center">
                            @if($log->payload)
                                <!-- ซ่อน JSON ข้อมูลไว้ใน data-payload เพื่อให้ JS ดึงไปแสดง -->
                                <button type="button" 
                                        onclick="showPayloadInfo(this)" 
                                        data-payload="{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}"
                                        class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white flex items-center justify-center transition-colors mx-auto" 
                                        title="ดูข้อมูลเบื้องลึก">
                                    <i class="fa-solid fa-code"></i>
                                </button>
                            @else
                                <span class="text-slate-300">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                            <i class="fa-solid fa-clipboard-list text-4xl mb-3 text-slate-300 block"></i>
                            ยังไม่มีประวัติการใช้งานในระบบ
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <!-- ตัวแบ่งหน้า -->
    @if($logs->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // 🌟 ฟังก์ชันรับค่า JSON จากปุ่ม และแสดงผลผ่าน SweetAlert2 แบบสวยงาม
    function showPayloadInfo(button) {
        // ดึงค่า JSON ออกมาจาก attribute ของปุ่ม
        const rawPayload = button.getAttribute('data-payload');
        
        Swal.fire({
            title: '<div class="flex items-center gap-2"><i class="fa-solid fa-microchip text-indigo-500"></i> ข้อมูลเชิงลึก (Payload)</div>',
            html: `
                <div class="text-left mt-2">
                    <pre class="bg-slate-900 text-emerald-400 p-4 rounded-xl text-xs overflow-x-auto font-mono whitespace-pre-wrap border border-slate-800 shadow-inner">${rawPayload}</pre>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: 'ปิดหน้าต่าง',
            confirmButtonColor: '#4f46e5', // สี Indigo-600
            width: '600px', // ขยายกล่องให้กว้างขึ้นเพื่ออ่าน Code ได้สบายตา
            heightAuto: false, 
            scrollbarPadding: false 
        });
    }
</script>
@endsection