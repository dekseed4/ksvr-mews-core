@extends('layouts.backend')

@section('title', 'คิวผู้ป่วยนอก (OPD) - KSVR Hospital')
@section('page_header', 'คิวรอตรวจแผนกผู้ป่วยนอก (OPD Queue)')

@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-12">
    <!-- 🌟 แท็บสลับมุมมอง (Dynamic Dashboard Switcher - Enterprise Grade) -->
    @php
        $user = auth()->user();
        
        // 1. ป้องกัน JSON Parsing Error
        $userRoles = is_string($user->roles) ? json_decode($user->roles, true) : (array)($user->roles ?? $user->role);
        
        // 2. เช็คสิทธิ์แบบแยกขาดจากกัน (Strict Check)
        $isAdmin = in_array('admin', $userRoles);
        $isExecutive = $isAdmin || in_array('executive', $userRoles);
        $isER = $isAdmin || in_array('er_nurse', $userRoles);
        $isMedic = $isAdmin || in_array('medic', $userRoles);
        $isRegistration = $isAdmin || in_array('registration', $userRoles);
        $isOPD = $isAdmin || in_array('opd_nurse', $userRoles) || in_array('medical_staff', $userRoles);
        
        // 3. นับจำนวนสิทธิ์ที่มี เพื่อตัดสินใจว่าจะโชว์แท็บสลับหน้าจอไหม
        $accessCount = ($isExecutive ? 1 : 0) + ($isER ? 1 : 0) + ($isMedic ? 1 : 0) + ($isRegistration ? 1 : 0) + ($isOPD ? 1 : 0); 
        $hasMultipleRoles = $accessCount > 1;
        
        // 🌟 แก้ไขค่า Default ของหน้านี้ให้เป็น 'opd'
        $currentView = request('view') ?? 'opd'; 
        $safeCompletedOpd = $completedOpdToday ?? collect();
    @endphp

    @if($hasMultipleRoles)
    <div class="flex justify-center mb-6 animate-fade-in-up">
        <div class="bg-slate-100 p-1 rounded-2xl inline-flex border border-slate-200 shadow-sm max-w-full overflow-x-auto">
            
            @if($isExecutive)
            <a href="{{ route('backend.dashboard', ['view' => 'executive']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'executive' ? 'bg-white text-slate-800 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-chart-pie"></i> <span class="hidden sm:inline">ผู้บริหาร</span>
            </a>
            @endif
            
            @if($isER)
            <a href="{{ route('backend.dashboard', ['view' => 'er']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'er' ? 'bg-white text-emerald-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-emerald-600 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-truck-medical"></i> <span class="hidden sm:inline">ER</span>
            </a>
            @endif
            
            @if($isMedic)
            <a href="{{ route('backend.dashboard', ['view' => 'medic']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'medic' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-blue-600 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-walkie-talkie"></i> <span class="hidden sm:inline">เสนารักษ์</span>
            </a>
            @endif

            @if($isRegistration)
            <a href="{{ route('backend.dashboard', ['view' => 'registration']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'registration' ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-id-card-clip"></i> <span class="hidden sm:inline">ทะเบียน</span>
            </a>
            @endif

            @if($isOPD)
            <a href="{{ route('backend.dashboard', ['view' => 'opd']) }}" class="px-4 md:px-6 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap {{ $currentView === 'opd' ? 'bg-white text-teal-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-teal-600 hover:bg-slate-200/50' }}">
                <i class="fa-solid fa-stethoscope"></i> <span class="hidden sm:inline">OPD</span>
            </a>
            @endif

        </div>
    </div>
    @endif

    <!-- 🌟 ส่วนหัว (Header & Stats) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col md:flex-row items-center justify-between gap-6 relative overflow-hidden">
        
        <div class="absolute -right-10 -top-10 text-emerald-500/10 text-9xl pointer-events-none">
            <i class="fa-solid fa-stethoscope"></i>
        </div>

        <div class="flex items-center gap-5 relative z-10">
            <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 rounded-2xl flex items-center justify-center text-3xl shadow-inner border border-emerald-100 dark:border-emerald-800">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-800 dark:text-white tracking-tight">คิวรอพบแพทย์ (OPD)</h2>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
                    แสดงเฉพาะผู้ป่วยที่มีระดับความเสี่ยงปกติ (สีเขียว) และไม่มีอาการฉุกเฉิน
                </p>
            </div>
        </div>

        <div class="relative z-10 flex gap-3 w-full md:w-auto">
            <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-5 py-3 rounded-2xl text-center flex-1 md:flex-none">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">คิวรอตรวจขณะนี้</p>
                <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ $opdQueue->count() }} <span class="text-sm font-bold text-slate-500">คิว</span></div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-5 py-3 rounded-2xl text-center flex-1 md:flex-none">
                <p class="text-xs font-bold text-slate-400 uppercase mb-1">ตรวจเสร็จวันนี้</p>
                <div class="text-3xl font-black text-teal-600 dark:text-teal-400">{{ $safeCompletedOpd->count() }} <span class="text-sm font-bold text-slate-500">ราย</span></div>
            </div>
        </div>
    </div>

    <!-- 🌟 1. ตารางคิวผู้ป่วยรอตรวจ (Queue List) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-list-ol text-emerald-500"></i> ลำดับคิวเรียงตามเวลาประเมิน (มาก่อน-ได้ตรวจก่อน)
            </h3>
            <button onclick="window.location.reload();" class="text-slate-400 hover:text-emerald-500 transition-colors text-sm font-bold flex items-center gap-2">
                <i class="fa-solid fa-rotate-right"></i> รีเฟรช
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-6 py-4 font-bold text-center w-20">คิวที่</th>
                        <th class="px-6 py-4 font-bold">ข้อมูลผู้ป่วย</th>
                        <th class="px-6 py-4 font-bold">สังกัดหน่วย / อาการสำคัญ</th>
                        <th class="px-6 py-4 font-bold text-center">MEWS</th>
                        <th class="px-6 py-4 font-bold">เวลารอคอย</th>
                        <th class="px-6 py-4 font-bold text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($opdQueue as $index => $queue)
                        <tr class="hover:bg-emerald-50/50 dark:hover:bg-slate-700/50 transition-colors group">
                            
                            <!-- หมายเลขคิว -->
                            <td class="px-6 py-4 text-center">
                                <span class="w-8 h-8 flex items-center justify-center bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-black rounded-full mx-auto">
                                    {{ $index + 1 }}
                                </span>
                            </td>

                            <!-- ข้อมูลผู้ป่วย -->
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 dark:text-white text-base">
                                    {{ $queue->patient->rank }}{{ $queue->patient->first_name }} {{ $queue->patient->last_name }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1 font-mono">
                                    <i class="fa-regular fa-id-card"></i> {{ $queue->patient->cid }} | 
                                    <span class="text-indigo-500 font-bold">HN: {{ $queue->patient->hn ?? '-' }}</span>
                                </div>
                            </td>

                            <!-- สังกัด & อาการสำคัญ -->
                            <td class="px-6 py-4">
                                <div class="text-slate-700 dark:text-slate-300 font-bold">
                                    {{ $queue->patient->militaryUnit->short_name ?? $queue->patient->company ?? '-' }}
                                    <span class="text-xs font-normal text-slate-400 ml-1">(ผลัด {{ $queue->patient->intake_batch ?? '-' }})</span>
                                </div>
                                @if(!empty($queue->symptoms_note))
                                    <div class="text-xs text-teal-700 bg-teal-50 border border-teal-100 px-2.5 py-0.5 rounded-lg mt-1 inline-block font-medium">
                                        <i class="fa-solid fa-notes-medical mr-1"></i>{{ $queue->symptoms_note }}
                                    </div>
                                @endif
                            </td>

                            <!-- คะแนน MEWS (สีเขียวเสมอ) -->
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 font-black rounded-lg border border-emerald-200 dark:border-emerald-800">
                                    {{ $queue->total_score }}
                                </span>
                            </td>

                            <!-- เวลารอคอย -->
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-700 dark:text-slate-300">
                                    <i class="fa-regular fa-clock text-emerald-500 mr-1"></i> {{ \Carbon\Carbon::parse($queue->created_at)->locale('th')->diffForHumans() }}
                                </div>
                                <div class="text-[10px] text-slate-400 mt-1">
                                    (ส่งตัวเมื่อ: {{ \Carbon\Carbon::parse($queue->created_at)->format('H:i น.') }})
                                </div>
                            </td>

                            <!-- ปุ่มจัดการ -->
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" 
                                            onclick="openOpdQuickDischargeModal('{{ route('backend.mews.discharge', $queue->id) }}', '{{ addslashes($queue->patient->rank . $queue->patient->first_name . ' ' . $queue->patient->last_name) }}')"
                                            class="inline-flex items-center justify-center px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs transition-all shadow-sm hover:shadow-md gap-1.5">
                                        <i class="fa-solid fa-circle-check"></i> จบการรักษา
                                    </button>

                                    <a href="{{ route('backend.mews.show', $queue->id) }}" 
                                       class="inline-flex items-center justify-center px-3 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-white hover:bg-slate-100 rounded-xl font-bold text-xs transition-all shadow-sm"
                                       title="ดูรายละเอียดการประเมิน MEWS">
                                        <i class="fa-solid fa-file-waveform mr-1 text-indigo-500"></i> ดูเคส
                                    </a>

                                    <a href="{{ route('backend.patients.show', $queue->patient_id) }}" 
                                       class="inline-flex items-center justify-center w-8 h-8 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 hover:bg-indigo-600 hover:text-white rounded-xl transition-all"
                                       title="เปิดแฟ้มประวัติผู้ป่วย">
                                        <i class="fa-solid fa-address-card text-xs"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="w-20 h-20 bg-slate-50 dark:bg-slate-800 text-slate-300 dark:text-slate-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-4 border-2 border-dashed border-slate-200 dark:border-slate-700">
                                    <i class="fa-solid fa-mug-hot"></i>
                                </div>
                                <h4 class="text-lg font-bold text-slate-700 dark:text-slate-300 mb-1">เคลียร์คิวเรียบร้อย</h4>
                                <p class="text-slate-500 text-sm">ขณะนี้ไม่มีผู้ป่วยรอตรวจที่แผนกผู้ป่วยนอกครับ</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 🗄️ 2. ประวัติการจำหน่ายผู้ป่วยนอก / ยกเลิก (OPD ประจำวัน) — แยกจากห้อง ER ชัดเจน -->
    <div class="bg-slate-50/80 dark:bg-slate-800/60 rounded-3xl p-6 md:p-8 border border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6 flex items-center gap-2 border-b border-slate-200 dark:border-slate-700 pb-3">
            <i class="fa-solid fa-clock-rotate-left text-teal-600"></i> ประวัติการจำหน่ายผู้ป่วยนอก / ยกเลิก (OPD ประจำวัน)
            <span class="bg-teal-100 text-teal-800 text-xs px-2.5 py-0.5 rounded-full font-bold ml-auto">{{ $safeCompletedOpd->count() }} ราย</span>
        </h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <th class="px-4 py-3 font-bold">เวลา</th>
                        <th class="px-4 py-3 font-bold">ชื่อ-นามสกุลผู้ป่วย</th>
                        <th class="px-4 py-3 font-bold">สังกัดหน่วย</th>
                        <th class="px-4 py-3 font-bold text-center">MEWS</th>
                        <th class="px-4 py-3 font-bold">สถานะ/ผลการรักษา</th>
                        <th class="px-4 py-3 font-bold text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($safeCompletedOpd as $history)
                        <tr class="hover:bg-white dark:hover:bg-slate-800 transition-colors">
                            <td class="px-4 py-4 text-slate-500 font-medium whitespace-nowrap">{{ $history->updated_at->format('H:i') }} น.</td>
                            <td class="px-4 py-4 font-bold text-slate-800 dark:text-white">
                                {{ $history->patient->rank ?? '' }}{{ $history->patient->first_name ?? '' }} {{ $history->patient->last_name ?? '' }}
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-600 dark:text-slate-300 font-semibold">
                                {{ $history->patient->militaryUnit->short_name ?? $history->patient->company ?? '-' }}
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 rounded-lg font-black text-xs">{{ $history->total_score }}</span>
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $isReturnedOpd = !empty($history->returned_to_unit_at);
                                @endphp
                                @if($history->status === 'cancelled')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-bold text-xs border border-slate-200">
                                        <i class="fa-solid fa-ban"></i> ถูกยกเลิก
                                    </span>
                                @elseif($history->discharge_type === 'admitted')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $isReturnedOpd ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }} font-bold text-xs border">
                                        <i class="fa-solid {{ $isReturnedOpd ? 'fa-house-chimney-check' : 'fa-bed-pulse' }}"></i>
                                        Admit {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} {{ $isReturnedOpd ? '• กลับหน่วยแล้ว' : '' }}
                                    </span>
                                @elseif($history->discharge_type === 'refer')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $isReturnedOpd ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200' }} font-bold text-xs border">
                                        <i class="fa-solid {{ $isReturnedOpd ? 'fa-house-chimney-check' : 'fa-ambulance' }}"></i>
                                        ส่งต่อ Refer {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} {{ $isReturnedOpd ? '• กลับหน่วยแล้ว' : '' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs border border-emerald-200">
                                        <i class="fa-solid fa-house-chimney-medical"></i> จำหน่ายกลับหน่วย (OPD)
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('backend.mews.show', $history->id) }}" class="text-teal-600 hover:text-teal-800 font-bold text-xs transition-colors bg-teal-50 px-3 py-1.5 rounded-lg border border-teal-200 shadow-sm">
                                    <i class="fa-solid fa-folder-open mr-1"></i> ดูแฟ้ม
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">ยังไม่มีประวัติการจำหน่ายผู้ป่วยนอก (OPD) ในวันนี้</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Echo) {
            window.Echo.channel('mews-channel')
                .listen('.mews.updated', (e) => {
                    refreshOpdQueue();
                });
        }

        setInterval(() => {
            refreshOpdQueue();
        }, 60000); 

        function refreshOpdQueue() {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const currentContent = document.querySelector('.max-w-7xl');
                const newContent = doc.querySelector('.max-w-7xl');
                if (currentContent && newContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                }
            });
        }
    });

    // 🌟 ฟังก์ชันเปิดป๊อปอัปจบการรักษาด่วนจากหน้าคิว OPD โดยตรง (ไม่ต้องประเมินซ้ำ)
    window.openOpdQuickDischargeModal = function(actionUrl, soldierName) {
        Swal.fire({
            title: `<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-house-chimney-medical text-emerald-600 mr-2"></i>จบการรักษาผู้ป่วยนอก (OPD)</div>`,
            html: `
                <div class="text-left space-y-3.5 mt-2">
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs sm:text-sm text-emerald-900">
                        ผู้ป่วย: <b class="text-base text-emerald-800">${soldierName}</b>
                    </div>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-500 transition-all">
                        <input type="radio" name="opd_discharge_type" value="recovered" checked onchange="toggleOpdExtraInput()" class="mt-1 w-4 h-4 text-emerald-600">
                        <div>
                            <span class="font-bold text-slate-800 text-sm block">🏠 จำหน่ายกลับกองร้อย (Recovered)</span>
                            <span class="text-xs text-slate-500">ตรวจรักษา/รับยาเรียบร้อย สามารถกลับไปปฏิบัติภารกิจหรือพักฟื้นที่หน่วยได้</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:bg-amber-50 has-[:checked]:border-amber-500 transition-all">
                        <input type="radio" name="opd_discharge_type" value="admitted" onchange="toggleOpdExtraInput()" class="mt-1 w-4 h-4 text-amber-600">
                        <div class="w-full">
                            <span class="font-bold text-slate-800 text-sm block">🛏️ รับไว้รักษาตัวใน รพ.ค่ายฯ (Admit)</span>
                            <span class="text-xs text-slate-500 block">แพทย์สั่งให้นอนรักษาตัวที่หอผู้ป่วยใน</span>
                            <div id="opd_admit_box" class="hidden mt-2">
                                <input type="text" id="opd_admit_ward" class="w-full px-3 py-2 bg-white border border-amber-300 rounded-lg text-xs sm:text-sm" placeholder="ระบุ Dx. หรือ หอผู้ป่วย/เตียง...">
                            </div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 transition-all">
                        <input type="radio" name="opd_discharge_type" value="refer" onchange="toggleOpdExtraInput()" class="mt-1 w-4 h-4 text-blue-600">
                        <div class="w-full">
                            <span class="font-bold text-slate-800 text-sm block">🚑 ส่งตัวรักษาต่อ (Refer)</span>
                            <span class="text-xs text-slate-500 block">ส่งต่อไปตรวจรักษาที่ รพ.สาธารณสุข / รพ.ศูนย์</span>
                            <div id="opd_refer_box" class="hidden mt-2">
                                <input type="text" id="opd_refer_hosp" class="w-full px-3 py-2 bg-white border border-blue-300 rounded-lg text-xs sm:text-sm" placeholder="ระบุ รพ.ปลายทาง หรือ Dx....">
                            </div>
                        </div>
                    </label>
                </div>
            `,
            width: '34rem',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk mr-1"></i> ยืนยันจบการรักษา',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#059669',
            customClass: { popup: 'rounded-3xl p-6' },
            preConfirm: () => {
                const type = document.querySelector('input[name="opd_discharge_type"]:checked')?.value || 'recovered';
                let details = '';
                if (type === 'admitted') details = document.getElementById('opd_admit_ward')?.value.trim() || '';
                if (type === 'refer') details = document.getElementById('opd_refer_hosp')?.value.trim() || '';
                return { type, details };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = actionUrl;
                form.innerHTML = `
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="discharge_type" value="${res.value.type}">
                    <input type="hidden" name="discharge_details" value="${res.value.details}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    };

    window.toggleOpdExtraInput = function() {
        const type = document.querySelector('input[name="opd_discharge_type"]:checked')?.value;
        document.getElementById('opd_admit_box')?.classList.toggle('hidden', type !== 'admitted');
        document.getElementById('opd_refer_box')?.classList.toggle('hidden', type !== 'refer');
    };
</script>
@endsection