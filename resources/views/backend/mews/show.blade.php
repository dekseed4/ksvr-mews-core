@extends('layouts.backend')

@section('title', 'ประวัติการประเมินผู้ป่วย (Timeline) - KSVR Hospital')
@section('page_header', 'ประวัติการประเมิน MEWS')

@section('content')
<div class="max-w-5xl mx-auto mt-6 pb-12 space-y-8">
    
    <!-- 🟢 แถบนำทางด้านบน: ปุ่มย้อนกลับ & ปุ่มเปิดดูแฟ้มประวัติผู้ป่วย -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
        <a href="{{ route('backend.dashboard') }}" class="text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400 transition-colors flex items-center gap-2 font-medium bg-white dark:bg-slate-800 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm text-sm">
            <i class="fa-solid fa-arrow-left"></i> กลับหน้าแดชบอร์ด
        </a>

        <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto justify-end">
            <a href="{{ route('backend.patients.show', $patient->id) }}" 
               class="w-full sm:w-auto px-5 py-2.5 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white rounded-xl font-bold text-sm transition-all flex items-center justify-center gap-2 shadow-sm hover:-translate-y-0.5">
                <i class="fa-solid fa-address-card text-emerald-400"></i> ดูแฟ้มประวัติผู้ป่วย (Patient Profile)
            </a>
        </div>
    </div>
    
    <!-- 🟢 Header: ข้อมูลผู้ป่วย -->
    <div class="bg-slate-800 rounded-3xl p-6 md:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-lg text-white">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 bg-emerald-500 rounded-2xl flex items-center justify-center text-3xl shadow-inner shrink-0 border border-emerald-400">
                <i class="fa-solid fa-user-injured"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold tracking-tight">{{ $patient->rank }}{{ $patient->first_name }} {{ $patient->last_name }}</h2>
                
                <div class="text-slate-300 mt-2 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm font-medium">
                    <span class="flex items-center gap-2 font-mono">
                        <i class="fa-regular fa-id-card text-emerald-400 text-lg"></i> {{ $patient->cid ?? '-' }}
                    </span>
                    
                    <span class="flex items-center gap-2 bg-blue-500/20 px-3 py-1 rounded-lg border border-blue-500/30 text-blue-100">
                        <i class="fa-solid fa-hospital-user text-blue-400 text-lg"></i> HN: <span class="font-bold tracking-wider">{{ $patient->hn ?? 'ยังไม่ซิงค์' }}</span>
                    </span>
                    
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-tent text-emerald-400 text-lg"></i> 
                        {{ $patient->militaryUnit->name ?? $patient->company ?? 'ไม่ระบุกองร้อย' }}
                        @if(isset($patient->militaryUnit->battalion) || $patient->battalion) 
                            ({{ $patient->militaryUnit->battalion ?? $patient->battalion }}) 
                        @endif
                    </span>
                </div>
            </div>
        </div>
        
        <div class="text-center md:text-right shrink-0 bg-slate-700/50 px-6 py-3 rounded-2xl border border-slate-600">
            <div class="text-slate-400 text-xs uppercase tracking-wider font-bold mb-1">ผลัดที่เข้าประจำการ</div>
            <div class="text-2xl font-black text-emerald-400">{{ $patient->intake_batch ?? '-' }}</div>
        </div>
    </div>

    <!-- 📅 Smart Appointment Banner: แจ้งเตือนหากพลทหารมีนัดพบแพทย์เร็วๆ นี้ -->
    @if($patient->upcomingAppointment)
        @php
            $upApp = $patient->upcomingAppointment;
            $appDateStr = $upApp->appointment_date->format('Y-m-d');
            $isTodayApp = ($appDateStr === date('Y-m-d'));
            $isTomorrowApp = ($appDateStr === date('Y-m-d', strtotime('+1 day')));
            $hospTitle = $upApp->hospital_name ?: ($upApp->hospital_type === 'public_hospital' ? 'รพ.สาธารณสุข' : 'รพ.ค่ายกฤษณ์สีวะรา');
        @endphp
        <div class="mt-4 mb-6 bg-sky-50 border-2 border-sky-200 text-sky-900 p-4 md:p-5 rounded-2xl shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 animate-fade-in-down">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-white text-sky-600 flex items-center justify-center text-xl shrink-0 shadow-sm border border-sky-100">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="font-black text-sm md:text-base text-sky-950">
                            ข้อมูลนัดหมาย: ผู้ป่วยมีนัดพบแพทย์ที่ {{ $hospTitle }}
                        </h4>
                        @if($isTodayApp)
                            <span class="px-2.5 py-0.5 bg-rose-600 text-white rounded-full text-[11px] font-black animate-pulse">🚨 นัดวันนี้!</span>
                        @elseif($isTomorrowApp)
                            <span class="px-2.5 py-0.5 bg-amber-500 text-white rounded-full text-[11px] font-black">⏰ นัดพรุ่งนี้ (อีก 1 วัน)</span>
                        @endif
                    </div>
                    <p class="text-xs md:text-sm text-sky-800 mt-0.5 font-medium">
                        <b>วันที่:</b> {{ $upApp->appointment_date->locale('th')->addYears(543)->translatedFormat('j M Y') }} เวลา {{ $upApp->appointment_time ?: '08:30' }} น.
                        | <b>แผนก:</b> {{ $upApp->clinic_name ?: 'ไม่ระบุ' }}
                        | <b>สาเหตุ:</b> {{ $upApp->app_cause ?: 'นัดตรวจติดตามอาการ' }}
                        @if($upApp->note) <span class="text-rose-600 font-bold ml-1">({{ $upApp->note }})</span> @endif
                    </p>
                </div>
            </div>
        </div>
    @endif
    
    <!-- 🌟 โซน Timeline ประวัติการรักษา -->
    <div id="realtime-timeline-container" class="space-y-6">
        @php 
            $groupedHistory = $patient->mewsAssessments->sortByDesc('id')->groupBy('case_id'); 
            $caseCounter = $groupedHistory->count();

            $avpuFullDict = [
                'A' => ['short' => 'A (Alert)', 'desc' => 'รู้สึกตัวดี'],
                'V' => ['short' => 'V (Voice)', 'desc' => 'เรียกถึงรู้สึกตัว'],
                'C' => ['short' => 'C (Confused)', 'desc' => 'สับสน / ซึม'],
                'P' => ['short' => 'P (Pain)', 'desc' => 'ตอบสนองเมื่อกระตุ้นเจ็บ'],
                'U' => ['short' => 'U (Unresponsive)', 'desc' => 'ไม่รู้สึกตัว / ไม่ตอบสนอง'],
            ];

            $currentUserRolesTop = is_string(auth()->user()->roles) ? json_decode(auth()->user()->roles, true) : (array)(auth()->user()->roles ?? auth()->user()->role);
            $canConfirmReturn = in_array('admin', $currentUserRolesTop) || in_array('er_nurse', $currentUserRolesTop) || in_array('opd_nurse', $currentUserRolesTop) || in_array('medic', $currentUserRolesTop) || in_array('medical_staff', $currentUserRolesTop);
        @endphp

        @foreach($groupedHistory as $caseId => $mewsGroup)
            @php 
                $latestOfThisRound = $mewsGroup->first(); 
                $templateName = $latestOfThisRound->template->name 
                    ?? ($activeTemplate->name ?? 'KSVR MEWS (ปรับปรุง)');
            @endphp

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden transition-colors duration-300">

                <!-- 🟢 1. ส่วนหัวการ์ด (Header ของรอบการรักษา) -->
                <div class="w-full px-5 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 transition-colors">
                    
                    <!-- ฝั่งซ้าย: รอบการรักษา + รหัสอ้างอิง -->
                    <div class="flex flex-wrap items-center gap-3 md:gap-4 w-full md:w-auto">
                        <button type="button" onclick="toggleRound('round-{{ $loop->index }}', 'icon-{{ $loop->index }}')" class="flex items-center hover:opacity-80 transition-opacity focus:outline-none shrink-0">
                            <div class="bg-indigo-600 text-white text-sm font-bold px-4 py-1.5 rounded-full shadow-sm flex items-center">
                                <i class="fa-solid fa-folder-open mr-2"></i> รอบการรักษาที่ {{ $caseCounter-- }}
                                <i id="icon-{{ $loop->index }}" class="fa-solid fa-chevron-{{ $loop->first ? 'up' : 'down' }} ml-2 text-xs opacity-70"></i>
                            </div>
                        </button>
                        
                        <div class="flex items-center gap-1.5 bg-white dark:bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 shadow-sm shrink-0">
                            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">รหัสอ้างอิง:</span>
                            <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 font-mono tracking-wider">{{ $caseId ?? 'ไม่ระบุ' }}</span>
                            <button type="button" onclick="showCaseIdInfo()" class="text-slate-300 hover:text-indigo-500 transition-colors ml-1 focus:outline-none" title="ที่มาของรหัสอ้างอิง">
                                <i class="fa-solid fa-circle-info text-[13px]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ฝั่งขวา: ป้ายชื่อสูตร + จำนวนครั้งประเมิน -->
                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto mt-1 md:mt-0">
                        <div class="flex items-center gap-1.5 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-800/50 shadow-sm shrink-0">
                            <i class="fa-solid fa-calculator text-indigo-500 text-[10px]"></i>
                            <span class="text-[11px] font-bold text-indigo-700 dark:text-indigo-400 tracking-wide uppercase truncate max-w-[180px] md:max-w-[250px]" title="{{ $templateName }}">
                                อ้างอิง: {{ $templateName }}
                            </span>
                        </div>

                        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 shadow-sm shrink-0">
                            ประเมินทั้งหมด {{ $mewsGroup->count() }} ครั้ง
                        </span>
                    </div>
                </div>

                <!-- เนื้อหา Timeline ด้านใน -->
                <div id="round-{{ $loop->index }}" class="{{ $loop->first ? 'block' : 'hidden' }} p-6 md:p-8 bg-slate-50/30 dark:bg-slate-900/20 relative">
                    <div class="absolute left-[39px] md:left-[55px] top-8 bottom-8 w-1 bg-slate-200 dark:bg-slate-700 rounded-full"></div>

                    <!-- 🌟 กล่องสรุปสถานะ ปิดเคส + ระบบติดตามและแก้ไขการกลับหน่วยฝึกหลัง Admit/Refer -->
                    @if($latestOfThisRound->status === 'completed' || $latestOfThisRound->status === 'cancelled')
                        <div class="mb-10 ml-8 md:ml-16 relative z-10">
                            @php
                                $isAdmitOrRefer = in_array($latestOfThisRound->discharge_type, ['admitted', 'refer']);
                                $hasReturnedToUnit = !empty($latestOfThisRound->returned_to_unit_at);
                                $canEditDischargeInfo = in_array('admin', $currentUserRolesTop) || in_array('er_nurse', $currentUserRolesTop) || in_array('opd_nurse', $currentUserRolesTop) || in_array('medical_staff', $currentUserRolesTop);

                                if ($latestOfThisRound->status === 'cancelled') {
                                    $endTheme = ['bg' => 'bg-slate-100 dark:bg-slate-800', 'text' => 'text-slate-600 dark:text-slate-300', 'border' => 'border-slate-200 dark:border-slate-700', 'icon' => 'fa-ban', 'title' => 'ยกเลิกเคส', 'desc' => $latestOfThisRound->cancel_reason];
                                } elseif ($latestOfThisRound->discharge_type === 'admitted') {
                                    $endTheme = [
                                        'bg'     => $hasReturnedToUnit ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-amber-50 dark:bg-amber-900/20',
                                        'text'   => $hasReturnedToUnit ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400',
                                        'border' => $hasReturnedToUnit ? 'border-emerald-200 dark:border-emerald-800/50' : 'border-amber-200 dark:border-amber-800/50',
                                        'icon'   => $hasReturnedToUnit ? 'fa-house-chimney-medical' : 'fa-bed-pulse',
                                        'title'  => $hasReturnedToUnit ? 'Admit รพ.ค่ายฯ (จำหน่ายกลับหน่วยฝึกแล้ว)' : 'Admit เข้ารักษาใน รพ. (กำลังนอนรักษาตัว)',
                                        'desc'   => 'เตียง/หอผู้ป่วย: ' . ($latestOfThisRound->discharge_details ?? '-')
                                    ];
                                } elseif ($latestOfThisRound->discharge_type === 'refer') {
                                    $endTheme = [
                                        'bg'     => $hasReturnedToUnit ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-blue-50 dark:bg-blue-900/20',
                                        'text'   => $hasReturnedToUnit ? 'text-emerald-700 dark:text-emerald-400' : 'text-blue-700 dark:text-blue-400',
                                        'border' => $hasReturnedToUnit ? 'border-emerald-200 dark:border-emerald-800/50' : 'border-blue-200 dark:border-blue-800/50',
                                        'icon'   => $hasReturnedToUnit ? 'fa-house-chimney-medical' : 'fa-ambulance',
                                        'title'  => $hasReturnedToUnit ? 'ส่งตัวรักษาต่อ Refer (กลับมาถึงหน่วยฝึกแล้ว)' : 'ส่งตัวรักษาต่อ (Refer - ยังไม่กลับหน่วยฝึก)',
                                        'desc'   => 'รพ.ปลายทาง: ' . ($latestOfThisRound->discharge_details ?? '-')
                                    ];
                                } else {
                                    $endTheme = ['bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'text' => 'text-emerald-700 dark:text-emerald-400', 'border' => 'border-emerald-200 dark:border-emerald-800/50', 'icon' => 'fa-house-chimney-medical', 'title' => 'จำหน่ายกลับกองร้อย', 'desc' => 'อาการปกติ สามารถกลับไปปฏิบัติภารกิจได้'];
                                }

                                $existingRetDate = $hasReturnedToUnit ? \Carbon\Carbon::parse($latestOfThisRound->returned_to_unit_at)->format('Y-m-d') : date('Y-m-d');
                                $existingRetTime = $hasReturnedToUnit ? \Carbon\Carbon::parse($latestOfThisRound->returned_to_unit_at)->format('H:i') : date('H:i');
                                $existingRetNote = $latestOfThisRound->return_note ?? '';
                            @endphp

                            <div class="{{ $endTheme['bg'] }} {{ $endTheme['border'] }} border-2 p-5 rounded-2xl flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-sm transition-colors">
                                <div class="flex items-start sm:items-center gap-4">
                                    <div class="w-12 h-12 bg-white dark:bg-slate-800 rounded-full flex items-center justify-center text-xl shadow-sm {{ $endTheme['text'] }} shrink-0">
                                        <i class="fa-solid {{ $endTheme['icon'] }}"></i>
                                    </div>
                                    <div class="space-y-1">
                                        <h4 class="font-bold text-base md:text-lg {{ $endTheme['text'] }} flex flex-wrap items-center gap-2">
                                            <span>{{ $endTheme['title'] }}</span>
                                            @if($isAdmitOrRefer && !$hasReturnedToUnit)
                                                <span class="bg-rose-600 text-white text-[11px] font-black px-2.5 py-0.5 rounded-full animate-pulse shadow-sm">
                                                    <i class="fa-solid fa-hospital-user mr-1"></i> ยังไม่กลับหน่วยฝึก
                                                </span>
                                            @elseif($isAdmitOrRefer && $hasReturnedToUnit)
                                                <span class="bg-emerald-600 text-white text-[11px] font-black px-2.5 py-0.5 rounded-full shadow-sm">
                                                    <i class="fa-solid fa-circle-check mr-1"></i> กลับหน่วยฝึกแล้ว
                                                </span>
                                            @else
                                                <span class="bg-white dark:bg-slate-800 text-[10px] px-2 py-0.5 rounded-full border {{ $endTheme['border'] }}"><i class="fa-solid fa-lock mr-1"></i>ปิดเคสแล้ว</span>
                                            @endif
                                        </h4>
                                        <p class="text-sm font-medium {{ $endTheme['text'] }} opacity-90">{{ $endTheme['desc'] }}</p>

                                        @if($isAdmitOrRefer && $hasReturnedToUnit)
                                            <div class="mt-2 pt-2 border-t border-emerald-200/70 text-xs text-emerald-900 dark:text-emerald-200 space-y-0.5">
                                                <div><i class="fa-regular fa-calendar-check mr-1"></i> <b>วันที่กลับถึงหน่วยฝึก:</b> {{ \Carbon\Carbon::parse($latestOfThisRound->returned_to_unit_at)->locale('th')->addYears(543)->translatedFormat('j M Y เวลา H:i น.') }}</div>
                                                @if($latestOfThisRound->return_note)
                                                    <div><i class="fa-solid fa-notes-medical mr-1"></i> <b>หมายเหตุ/การพักฟื้น:</b> {{ $latestOfThisRound->return_note }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- 🛠️ โซนปุ่มจัดการด้านขวา: บันทึกกลับหน่วย / แก้ไขข้อมูลกลับหน่วย / แก้ไขผลปิดเคส -->
                                @if($latestOfThisRound->status === 'completed')
                                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                                        @if($isAdmitOrRefer && !$hasReturnedToUnit && $canConfirmReturn)
                                            <!-- กรณี Admit/Refer และยังไม่กลับหน่วยฝึก -->
                                            <button type="button" 
                                                    onclick="openReturnToUnitModal('{{ route('backend.mews.return_to_unit', $latestOfThisRound->id) }}', '{{ addslashes($patient->rank . $patient->first_name . ' ' . $patient->last_name) }}', false)"
                                                    class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs sm:text-sm shadow-md transition-all flex items-center justify-center gap-1.5 hover:-translate-y-0.5">
                                                <i class="fa-solid fa-house-chimney-user"></i> บันทึกกลับหน่วยฝึกแล้ว
                                            </button>
                                        @elseif($isAdmitOrRefer && $hasReturnedToUnit && $canConfirmReturn)
                                            <!-- ✏️ กรณีกลับหน่วยฝึกแล้ว: มีปุ่มให้กดแก้ไขวันที่/หมายเหตุ หรือย้อนสถานะกลับเป็นนอน รพ. ได้ -->
                                            <button type="button" 
                                                    onclick="openReturnToUnitModal('{{ route('backend.mews.return_to_unit', $latestOfThisRound->id) }}', '{{ addslashes($patient->rank . $patient->first_name . ' ' . $patient->last_name) }}', true, '{{ $existingRetDate }}', '{{ $existingRetTime }}', '{{ addslashes($existingRetNote) }}')"
                                                    class="w-full sm:w-auto px-3.5 py-2 bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl font-bold text-xs shadow-sm transition-all flex items-center justify-center gap-1.5">
                                                <i class="fa-solid fa-pen-to-square text-emerald-600"></i> แก้ไขข้อมูลกลับหน่วย
                                            </button>
                                        @endif

                                        @if($canEditDischargeInfo)
                                            <!-- ⚙️ ปุ่มสำหรับพยาบาล/แอดมิน เพื่อแก้ไขชื่อหอผู้ป่วย หรือเปลี่ยนประเภทการจำหน่าย -->
                                            <button type="button" 
                                                    onclick="openDischargeModal('{{ route('backend.mews.discharge', $latestOfThisRound->id) }}', '{{ $latestOfThisRound->discharge_type }}', '{{ addslashes($latestOfThisRound->discharge_details ?? '') }}')"
                                                    class="w-full sm:w-auto px-3 py-2 bg-white/80 hover:bg-white text-slate-600 hover:text-slate-900 border border-slate-300/80 rounded-xl font-bold text-xs shadow-sm transition-all flex items-center justify-center gap-1"
                                                    title="แก้ไขประเภทการปิดเคส หรือแก้ไขชื่อหอผู้ป่วย/รพ.ปลายทาง">
                                                <i class="fa-solid fa-sliders"></i> แก้ไขผลปิดเคส
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="space-y-12">
                        @foreach($mewsGroup->sortByDesc('id') as $history)
                            @php
                                $isEmergency = $history->risk_level === 'emergency';
                                $isObserve = $history->risk_level === 'observe';
                                
                                $dotColor = $isEmergency ? 'bg-rose-500' : ($isObserve ? 'bg-amber-400' : 'bg-emerald-500');
                                $scoreBadgeClasses = $isEmergency 
                                    ? 'bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800' 
                                    : ($isObserve 
                                        ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800' 
                                        : 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800');

                                $typeIcon = $history->assessment_type === 'field' ? 'fa-truck-medical' : 'fa-hospital-user';
                                $assessmentNumber = $mewsGroup->count() - $loop->index;
                                
                                if ($history->assessment_type === 'field') {
                                    $typeText = 'เสนารักษ์ประเมิน (ภาคสนาม)';
                                } else {
                                    $typeText = ($assessmentNumber === 1) ? 'พยาบาลประเมิน (ห้องฉุกเฉิน)' : 'พยาบาลประเมินซ้ำ (ห้องฉุกเฉิน)';
                                }

                                $avpuKey = strtoupper(trim($history->consciousness ?? ''));
                                $avpuInfo = $avpuFullDict[$avpuKey] ?? ['short' => ($avpuKey ?: '-'), 'desc' => 'ไม่ระบุ'];
                            @endphp

                            <div class="relative w-full">
                                <!-- จุดไข่ปลาบนเส้น -->
                                <div class="absolute -left-1 md:left-3 top-5 w-6 h-6 rounded-full {{ $dotColor }} border-4 border-white dark:border-slate-800 shadow-sm z-10"></div>

                                <div class="ml-8 md:ml-16 bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden {{ $history->status === 'completed' ? 'opacity-90' : '' }}">
                                    
                                    <!-- 🟢 หัวการ์ดด้านใน -->
                                    <div class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-700 px-6 py-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                        
                                        <div class="flex items-center gap-3">
                                            <span class="bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 px-3 py-1 rounded-full flex items-center justify-center font-bold text-xs shadow-sm whitespace-nowrap">
                                                ประเมินครั้งที่ {{ $assessmentNumber }}
                                            </span>
                                            <div>
                                                <h4 class="font-bold text-slate-800 dark:text-slate-200"><i class="fa-solid {{ $typeIcon }} text-slate-400 mr-1"></i> {{ $typeText }}</h4>
                                                <p class="text-xs text-slate-500 dark:text-slate-400"><i class="fa-regular fa-clock"></i> {{ \Carbon\Carbon::parse($history->created_at)->locale('th')->addYears(543)->translatedFormat('j M Y H:i น.') }} (โดย: {{ $history->evaluator->name ?? 'ไม่ระบุ' }})</p>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                                            <div class="px-4 py-1.5 rounded-full text-sm font-bold border shadow-sm shrink-0 {{ $scoreBadgeClasses }}">
                                                คะแนน MEWS: {{ $history->total_score }}
                                            </div>

                                            <button type="button" onclick="showCopilotModalByMewsId({{ $history->id }})" 
                                                    class="inline-flex items-center justify-center px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700 text-xs font-bold rounded-full transition-all shadow-sm shrink-0 gap-1.5"
                                                    title="ดูคำแนะนำปฐมพยาบาลเบื้องต้นจาก AI สำหรับการประเมินรอบนี้">
                                                <i class="fa-solid fa-user-doctor"></i> คำแนะนำปฐมพยาบาล (AI Copilot)
                                            </button>

                                            <a href="{{ route('backend.mews.print_form', $history->id) }}" 
                                               target="_blank"
                                               class="inline-flex items-center justify-center px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-full transition-all shadow-sm hover:-translate-y-0.5 shrink-0 gap-1.5"
                                               title="พิมพ์แบบฟอร์มคัดกรองของการประเมินครั้งนี้">
                                                <i class="fa-solid fa-print"></i> พิมพ์ฟอร์ม
                                            </a>
                                        </div>

                                    </div>

                                    <!-- 🌟 ส่วนสัญญาณชีพ และอาการเฉพาะโรค -->
                                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                            <div class="bg-white dark:bg-slate-700 p-3 rounded-xl border border-slate-100 dark:border-slate-600 shadow-sm text-center flex flex-col justify-center transition-colors">
                                                <div class="text-[10px] text-slate-400 dark:text-slate-300 font-bold uppercase">SBP (mmHg)</div>
                                                <div class="text-xl font-black text-slate-700 dark:text-white mt-0.5">{{ $history->systolic_bp ?? '-' }}</div>
                                            </div>
                                            <div class="bg-white dark:bg-slate-700 p-3 rounded-xl border border-slate-100 dark:border-slate-600 shadow-sm text-center flex flex-col justify-center transition-colors">
                                                <div class="text-[10px] text-slate-400 dark:text-slate-300 font-bold uppercase">HR (bpm)</div>
                                                <div class="text-xl font-black text-slate-700 dark:text-white mt-0.5">{{ $history->heart_rate ?? '-' }}</div>
                                            </div>
                                            <div class="bg-white dark:bg-slate-700 p-3 rounded-xl border border-slate-100 dark:border-slate-600 shadow-sm text-center flex flex-col justify-center transition-colors">
                                                <div class="text-[10px] text-slate-400 dark:text-slate-300 font-bold uppercase">RR (/min)</div>
                                                <div class="text-xl font-black text-slate-700 dark:text-white mt-0.5">{{ $history->respiratory_rate ?? '-' }}</div>
                                            </div>
                                            <div class="bg-white dark:bg-slate-700 p-3 rounded-xl border border-slate-100 dark:border-slate-600 shadow-sm text-center flex flex-col justify-center transition-colors">
                                                <div class="text-[10px] text-slate-400 dark:text-slate-300 font-bold uppercase">Temp (°C)</div>
                                                <div class="text-xl font-black {{ ($history->temperature >= 38.0) ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-white' }} mt-0.5">{{ $history->temperature ?? '-' }}</div>
                                            </div>
                                            <div class="bg-white dark:bg-slate-700 p-3 rounded-xl border border-slate-100 dark:border-slate-600 shadow-sm text-center flex flex-col justify-center transition-colors">
                                                <div class="text-[10px] text-slate-400 dark:text-slate-300 font-bold uppercase">O2 Sat (%)</div>
                                                <div class="text-xl font-black {{ ($history->o2sat && $history->o2sat < 95) ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-white' }} mt-0.5">{{ $history->o2sat ?? '-' }}</div>
                                            </div>
                                            <div class="bg-white dark:bg-slate-700 p-2.5 rounded-xl border border-slate-100 dark:border-slate-600 shadow-sm text-center flex flex-col justify-center transition-colors">
                                                <div class="text-[10px] text-slate-400 dark:text-slate-300 font-bold uppercase">AVPU (ความรู้สึกตัว)</div>
                                                <div class="text-sm font-black {{ ($avpuKey && $avpuKey !== 'A') ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} mt-0.5 leading-tight">
                                                    {{ $avpuInfo['short'] }}
                                                </div>
                                                <div class="text-[11px] font-bold {{ ($avpuKey && $avpuKey !== 'A') ? 'text-rose-500 dark:text-rose-300' : 'text-slate-500 dark:text-slate-300' }} mt-0.5 leading-tight">
                                                    {{ $avpuInfo['desc'] }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-3 h-full flex flex-col justify-between">
                                            <!-- กล่องอาการฉุกเฉินเฉพาะโรค -->
                                            <div class="bg-red-50 dark:bg-red-900/30 rounded-2xl p-4 border border-red-100 dark:border-red-800/50 flex-1 transition-colors">
                                                <h5 class="text-sm font-bold text-red-800 dark:text-red-400 mb-2.5"><i class="fa-solid fa-truck-medical mr-1"></i> อาการเฉพาะโรค</h5>
                                                @if(count($history->grouped_symptoms) > 0)
                                                    <div class="space-y-2.5">
                                                        @foreach($history->grouped_symptoms as $category => $syms)
                                                            <div>
                                                                <div class="text-xs font-bold text-red-900 dark:text-red-300 border-b border-red-200 dark:border-red-700/50 pb-0.5 mb-1 flex items-center"><i class="fa-solid fa-caret-right mr-1"></i> {{ $category }}</div>
                                                                <ul class="list-disc list-inside text-xs text-red-700 dark:text-red-200 space-y-0.5 ml-2 font-medium">
                                                                    @foreach($syms as $sym) <li>{{ $sym }}</li> @endforeach
                                                                </ul>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 italic">- ไม่พบอาการฉุกเฉินเฉพาะโรค -</p>
                                                @endif
                                            </div>

                                            <!-- กล่องแสดงโน้ตอาการสำคัญ / มาทำแผล / บันทึกเพิ่มเติม -->
                                            <div class="bg-emerald-50/70 dark:bg-emerald-900/20 rounded-2xl p-4 border border-emerald-200/80 dark:border-emerald-800/50 transition-colors">
                                                <h5 class="text-xs font-bold text-emerald-800 dark:text-emerald-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                                    <i class="fa-solid fa-notes-medical text-emerald-600"></i> อาการสำคัญ / บันทึกเพิ่มเติม (Note)
                                                </h5>
                                                @if(!empty($history->symptoms_note))
                                                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100 leading-relaxed">{{ $history->symptoms_note }}</p>
                                                @else
                                                    <p class="text-xs text-slate-400 italic">- ไม่มีการระบุบันทึกเพิ่มเติม -</p>
                                                @endif
                                            </div>
                                        </div>
                                        
                                    </div>

                                    <!-- 🌟 โซนปุ่มจัดการ (รองรับการกดจบการรักษาเคส OPD ทันทีโดยไม่ต้องประเมินซ้ำ) -->
                                    @if ($loop->iteration === 1 && $loop->parent->first && $history->status === 'pending') 
                                        @php
                                            $currentUserRoles = is_string(auth()->user()->roles) ? json_decode(auth()->user()->roles, true) : (array)(auth()->user()->roles ?? auth()->user()->role);
                                            $isAdmin        = in_array('admin', $currentUserRoles);
                                            $isERNurse      = in_array('er_nurse', $currentUserRoles);
                                            $isOPDNurse     = in_array('opd_nurse', $currentUserRoles);
                                            $isMedicalStaff = in_array('medical_staff', $currentUserRoles);
                                            $isMedic        = in_array('medic', $currentUserRoles);

                                            $isCreator    = $history->evaluator_id === auth()->id();
                                            $isStillField = $history->assessment_type === 'field';

                                            // ตรวจสอบว่าเป็นเคสอาการไม่รุนแรง (OPD: สีเขียว normal และไม่มีอาการฉุกเฉินเฉพาะโรค)
                                            $rawHistorySyms = is_string($history->symptoms) ? json_decode($history->symptoms, true) : ($history->symptoms ?? []);
                                            $hasEmergencySyms = is_array($rawHistorySyms) && count($rawHistorySyms) > 0;
                                            $isMildOpdCase = ($history->risk_level === 'normal' && !$hasEmergencySyms);

                                            $isHospitalClinical = $isAdmin || $isERNurse || $isOPDNurse || $isMedicalStaff;

                                            $canReassess  = $isHospitalClinical;
                                            // 🌟 หากเป็นเคส OPD (อาการไม่รุนแรง) ให้แสดงปุ่ม "จบการรักษา" ทันทีตั้งแต่ครั้งที่ 1 โดยไม่ต้องประเมินซ้ำ!
                                            // ส่วนเคส ER (เหลือง/แดง/มีอาการฉุกเฉิน) จะปิดเคสได้เมื่อประเมินซ้ำแล้ว หรือดำเนินการโดยพยาบาล/แอดมิน
                                            $canDischarge = ($isHospitalClinical && ($isMildOpdCase || $mewsGroup->count() > 1))
                                                         || ($isMedic && $isMildOpdCase);

                                            $canDelete    = $isAdmin;
                                            $canEdit      = $isHospitalClinical || ($isMedic && $isCreator && $isStillField); 
                                            $canCancel    = $isHospitalClinical || ($isMedic && $isCreator && $isStillField); 
                                            
                                            $canSeeActionBar = $canReassess || $canDischarge || $canEdit || $canCancel;
                                            $nextType = $isHospitalClinical ? 'er' : 'field'; 
                                        @endphp

                                        @if($canSeeActionBar)
                                            <div class="bg-slate-50 dark:bg-slate-800/80 px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center gap-3 rounded-b-3xl">
                                                @if($canReassess)
                                                    <a href="{{ route('backend.mews.create', ['patient_id' => $patient->id, 'type' => $nextType]) }}" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-sm transition-colors text-sm">
                                                        <i class="fa-solid fa-stethoscope mr-1"></i> ประเมินซ้ำ
                                                    </a>
                                                @endif
                                                @if($canEdit)
                                                    <a href="{{ route('backend.mews.edit', $history->id) }}" class="px-5 py-2.5 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 rounded-xl font-bold shadow-sm transition-colors text-sm">
                                                        <i class="fa-solid fa-pen mr-1"></i> แก้ไข
                                                    </a>
                                                @endif
                                                @if($canCancel)
                                                    <button type="button" onclick="openCancelModal('{{ route('backend.mews.cancel', $history->id) }}')" class="px-5 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600 rounded-xl font-bold shadow-sm transition-colors text-sm">
                                                        <i class="fa-solid fa-ban mr-1"></i> ยกเลิกเคส
                                                    </button>
                                                @endif
                                                
                                                <div class="ml-auto flex flex-wrap items-center gap-3">
                                                    @if($canDelete)
                                                        <form action="{{ route('backend.mews.destroy', $history->id) }}" method="POST" id="delete-form-{{ $history->id }}">
                                                            @csrf @method('DELETE')
                                                            <button type="button" onclick="confirmAction(event, 'ยืนยันลบ?', 'ลบข้อมูลถาวร', 'error', 'delete-form-{{ $history->id }}')" class="px-5 py-2.5 bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 hover:bg-rose-500 hover:text-white rounded-xl font-bold shadow-sm transition-colors text-sm border border-rose-200 dark:border-rose-800">
                                                                <i class="fa-solid fa-trash-can mr-1"></i> ลบทิ้ง
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if($canDischarge)
                                                        <button type="button" onclick="openDischargeModal('{{ route('backend.mews.discharge', $history->id) }}')" class="px-5 py-2.5 bg-slate-800 dark:bg-indigo-600 text-white hover:bg-slate-900 dark:hover:bg-indigo-500 rounded-xl font-bold shadow-md transition-all hover:-translate-y-0.5 text-sm flex items-center gap-1.5">
                                                            <i class="fa-solid fa-bed-pulse"></i> จบการรักษา
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div> 
            </div> 
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<script>
// 🏠 ฟังก์ชันเปิดป๊อปอัปบันทึกเมื่อพลทหารออกจากหอผู้ป่วย (Admit/Refer) กลับมาถึงหน่วยฝึกแล้ว
function openReturnToUnitModal(actionUrl, soldierName) {
    const todayStr = '{{ date("Y-m-d") }}';
    Swal.fire({
        title: '<div class="text-lg md:text-xl font-black text-slate-800 dark:text-white"><i class="fa-solid fa-house-chimney-medical text-emerald-500 mr-2"></i>ยืนยันการกลับหน่วยฝึก</div>',
        html: `
            <div class="text-left space-y-4 mt-3">
                <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs sm:text-sm text-emerald-900">
                    กำลังบันทึกจำหน่ายออกจากโรงพยาบาลกลับสู่หน่วยฝึกของ:<br>
                    <b class="text-base text-emerald-800">${soldierName}</b>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">วันที่กลับถึงหน่วยฝึก <span class="text-rose-500">*</span></label>
                    <input type="date" id="swal_returned_date" value="${todayStr}" class="w-full px-3.5 py-2.5 bg-slate-50 border-2 border-slate-300 rounded-xl text-sm font-bold text-slate-800 outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">หมายเหตุ / คำสั่งการพักฟื้นต่อที่กองร้อย</label>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <button type="button" onclick="document.getElementById('swal_return_note').value='อาการปกติ กลับเข้าฝึกได้ตามปกติ'" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 border border-slate-200 rounded-lg text-[11px] font-bold">+ อาการปกติ</button>
                        <button type="button" onclick="document.getElementById('swal_return_note').value='แพทย์ให้พักฟื้นงดฝึกหนักที่กองร้อย 3 วัน'" class="px-2.5 py-1 bg-slate-100 hover:bg-amber-50 text-slate-600 hover:text-amber-700 border border-slate-200 rounded-lg text-[11px] font-bold">+ พักฟื้นที่กองร้อย 3 วัน</button>
                        <button type="button" onclick="document.getElementById('swal_return_note').value='รับยากลับไปทานต่อ และรอพบแพทย์ตามนัด'" class="px-2.5 py-1 bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-700 border border-slate-200 rounded-lg text-[11px] font-bold">+ ทานยาต่อ/รอนัด</button>
                    </div>
                    <input type="text" id="swal_return_note" placeholder="เช่น อาการดีขึ้น แพทย์อนุญาตให้กลับกองร้อย..." class="w-full px-3.5 py-2.5 bg-white border-2 border-slate-300 rounded-xl text-sm text-slate-800 outline-none focus:border-emerald-500">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> ยืนยันกลับหน่วยฝึกแล้ว',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#94a3b8',
        customClass: {
            popup: 'bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-2xl border border-slate-100 dark:border-slate-700'
        },
        preConfirm: () => {
            const retDate = document.getElementById('swal_returned_date').value;
            const retNote = document.getElementById('swal_return_note').value.trim();
            if (!retDate) {
                Swal.showValidationMessage('กรุณาระบุวันที่กลับถึงหน่วยฝึกครับ');
                return false;
            }
            return { returned_date: retDate, return_note: retNote };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="PATCH">
                <input type="hidden" name="returned_date" value="${result.value.returned_date}">
                <input type="hidden" name="return_note" value="${result.value.return_note}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function openDischargeModal(actionUrl) {
    Swal.fire({
        title: 'เลือกผลการรักษา (Discharge)',
        html: `
            <div class="text-left space-y-4 mt-4">
                <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all has-[:checked]:bg-emerald-50 dark:has-[:checked]:bg-emerald-900/30 has-[:checked]:border-emerald-500 has-[:checked]:ring-1 has-[:checked]:ring-emerald-500">
                    <input type="radio" name="discharge_type" value="recovered" checked class="mt-1 w-4 h-4 text-emerald-600 focus:ring-emerald-500 bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-600" onchange="toggleDischargeInputs()">
                    <div>
                        <span class="font-bold text-slate-800 dark:text-slate-100 block">จำหน่ายกลับกองร้อย (Recovered)</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">อาการปกติ ทหารสามารถกลับไปปฏิบัติภารกิจต่อได้ทันที</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-all has-[:checked]:bg-amber-50 dark:has-[:checked]:bg-amber-900/30 has-[:checked]:border-amber-500 has-[:checked]:ring-1 has-[:checked]:ring-amber-500">
                    <input type="radio" name="discharge_type" value="admitted" class="mt-1 w-4 h-4 text-amber-600 focus:ring-amber-500 bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-600" onchange="toggleDischargeInputs()">
                    <div class="w-full">
                        <span class="font-bold text-slate-800 dark:text-slate-100 block">รับไว้รักษาตัวใน รพ. (Admit)</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 mb-2 block">เข้าหอผู้ป่วยใน (ระบบจะขึ้นสถานะรอติดตามจนกว่าจะกลับหน่วยฝึก)</span>
                        <div id="admit_input_group" class="hidden mt-3 animate-fade-in-up">
                            <input type="text" id="admit_ward" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700 rounded-lg text-sm text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-amber-500 focus:border-amber-500" placeholder="ระบุ หอผู้ป่วย / ห้อง / เลขเตียง (ถ้าทราบ)...">
                        </div>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all has-[:checked]:bg-blue-50 dark:has-[:checked]:bg-blue-900/30 has-[:checked]:border-blue-500 has-[:checked]:ring-1 has-[:checked]:ring-blue-500">
                    <input type="radio" name="discharge_type" value="refer" class="mt-1 w-4 h-4 text-blue-600 focus:ring-blue-500 bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-600" onchange="toggleDischargeInputs()">
                    <div class="w-full">
                        <span class="font-bold text-slate-800 dark:text-slate-100 block">ส่งตัวรักษาต่อ (Refer)</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 mb-2 block">ส่งต่อไปโรงพยาบาลแม่ข่ายหรือโรงพยาบาลศูนย์ (รอติดตามกลับหน่วยฝึก)</span>
                        <div id="refer_input_group" class="hidden mt-3 animate-fade-in-up">
                            <input type="text" id="refer_hospital" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-blue-300 dark:border-blue-700 rounded-lg text-sm text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" placeholder="ระบุ ชื่อโรงพยาบาลปลายทาง...">
                        </div>
                    </div>
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-save mr-1"></i> ยืนยันปิดเคส ER',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#1e293b', 
        cancelButtonColor: '#94a3b8', 
        focusConfirm: false,
        customClass: {
            popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700 transition-colors duration-300',
            title: 'text-xl font-bold text-slate-800 dark:text-white',
        },
        didOpen: () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.style.setProperty('z-index', '-1', 'important'); 
            const swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) swalContainer.style.setProperty('z-index', '999999', 'important');
        },
        didClose: () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.style.setProperty('z-index', '40', 'important'); 
        },
        preConfirm: () => {
            const selected = document.querySelector('input[name="discharge_type"]:checked');
            if (!selected) {
                Swal.showValidationMessage('กรุณาเลือกผลการรักษาอย่างน้อย 1 รายการ');
                return false;
            }
            const type = selected.value;
            let extraData = null;
            if (type === 'admitted') {
                extraData = document.getElementById('admit_ward').value.trim();
            } else if (type === 'refer') {
                extraData = document.getElementById('refer_hospital').value.trim();
            }
            return { type: type, details: extraData };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="PATCH">
                <input type="hidden" name="discharge_type" value="${result.value.type}">
            `;
            if (result.value.details) {
                form.innerHTML += `<input type="hidden" name="discharge_details" value="${result.value.details}">`;
            }
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function toggleDischargeInputs() {
    const type = document.querySelector('input[name="discharge_type"]:checked').value;
    const admitGroup = document.getElementById('admit_input_group');
    const referGroup = document.getElementById('refer_input_group');

    if (type === 'admitted') {
        admitGroup.classList.remove('hidden');
        referGroup.classList.add('hidden');
    } else if (type === 'refer') {
        referGroup.classList.remove('hidden');
        admitGroup.classList.add('hidden');
    } else {
        admitGroup.classList.add('hidden');
        referGroup.classList.add('hidden');
    }
}

function toggleRound(containerId, iconId) {
    const container = document.getElementById(containerId);
    const icon = document.getElementById(iconId);
    
    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        container.classList.add('block');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        container.classList.remove('block');
        container.classList.add('hidden');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

function openCancelModal(actionUrl) {
    Swal.fire({
        title: 'ยกเลิกการประเมินนี้?',
        text: 'กรุณาระบุเหตุผลที่ต้องการยกเลิกเคสนี้ เพื่อบันทึกเป็นประวัติ',
        input: 'text',
        inputPlaceholder: 'เช่น กรอกข้อมูลผิดรอบ, ผู้ป่วยปฏิเสธการตรวจ...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> ยืนยันยกเลิกเคส',
        cancelButtonText: 'ปิดหน้าต่าง',
        confirmButtonColor: '#ef4444', 
        cancelButtonColor: '#94a3b8',
        customClass: {
            popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700 transition-colors duration-300',
            title: 'text-xl font-bold text-slate-800 dark:text-white',
            htmlContainer: 'text-slate-500 dark:text-slate-400',
            input: 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-600 text-slate-800 dark:text-white focus:ring-emerald-500 focus:border-emerald-500'
        },
        didOpen: () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.style.setProperty('z-index', '-1', 'important'); 
            const swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) swalContainer.style.setProperty('z-index', '999999', 'important');
        },
        didClose: () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.style.setProperty('z-index', '40', 'important'); 
        },
        inputValidator: (value) => {
            if (!value || value.trim() === '') {
                return 'กรุณาระบุเหตุผลด้วยครับ!'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="PATCH">
                <input type="hidden" name="cancel_reason" value="${result.value}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function showCaseIdInfo() {
    Swal.fire({
        title: '<span class="text-indigo-700 dark:text-indigo-400"><i class="fa-solid fa-microchip mr-2"></i>ที่มาของ "รหัสอ้างอิง"</span>',
        html: `
            <div class="text-left text-sm text-slate-600 dark:text-slate-300 space-y-3 mt-2">
                <p>รหัสอ้างอิง (Case ID) ทำหน้าที่เสมือน <b>VN (Visit Number)</b> เพื่อจัดกลุ่มการประเมิน MEWS ให้อยู่ในการเข้ารับการรักษาครั้งเดียวกัน มีรูปแบบดังนี้:</p>
                <div class="bg-indigo-50 dark:bg-indigo-900/40 border border-indigo-100 dark:border-indigo-800 p-3 rounded-lg text-center font-mono text-indigo-800 dark:text-indigo-300 font-bold tracking-widest text-lg shadow-inner">
                    VN-YYMMDD-XXXX
                </div>
                <ul class="list-disc list-inside space-y-1.5 ml-2 mt-3">
                    <li><b class="text-slate-800 dark:text-white">VN:</b> อักษรนำหน้า (Visit Number)</li>
                    <li><b class="text-slate-800 dark:text-white">YYMMDD:</b> ปี, เดือน, วันที่เริ่มเปิดเคส</li>
                    <li><b class="text-slate-800 dark:text-white">XXXX:</b> รหัสสุ่ม 4 หลัก (Unique ID)</li>
                </ul>
                <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-lg border border-slate-200 dark:border-slate-700 mt-4 text-xs">
                    <i class="fa-solid fa-lightbulb text-amber-500 mr-1"></i> <b>เกร็ดความรู้:</b> รหัสนี้จะถูกสร้างใหม่เฉพาะตอนที่เปิดประเมินครั้งแรก หรือเมื่อเคสเดิมถูกกด "จบการรักษา" ไปแล้วเท่านั้นครับ
                </div>
            </div>
        `,
        confirmButtonText: 'เข้าใจแล้ว',
        confirmButtonColor: '#4f46e5',
        width: '500px',
        customClass: {
            popup: 'bg-white dark:bg-slate-800 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700'
        }
    });
}

function fetchLatestTimeline() {
    fetch(window.location.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newTimeline = doc.getElementById('realtime-timeline-container');
        
        if (newTimeline) {
            document.getElementById('realtime-timeline-container').innerHTML = newTimeline.innerHTML;
            Swal.fire({
                toast: true, 
                position: 'top-end', 
                icon: 'info',
                title: 'อัปเดตข้อมูล V/S ล่าสุดแล้ว',
                showConfirmButton: false, 
                timer: 3000
            });
        }
    })
    .catch(error => console.error('Real-time Refresh Error:', error));
}

function showCopilotModalByMewsId(mewsId) {
    Swal.fire({
        title: '<div class="text-base font-bold text-indigo-800 dark:text-indigo-300"><i class="fa-solid fa-user-doctor text-indigo-600 dark:text-indigo-400 mr-2"></i>AI Clinical Triage Copilot</div>',
        html: '<div class="py-4 text-sm text-slate-600 dark:text-slate-300">กำลังวิเคราะห์สัญญาณชีพและแนวทางปฐมพยาบาลเบื้องต้น...</div>',
        allowOutsideClick: false,
        didOpen: () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.style.setProperty('z-index', '-1', 'important');
            Swal.showLoading();
        },
        didClose: () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.style.setProperty('z-index', '40', 'important');
        },
        customClass: { popup: 'bg-white dark:bg-slate-800 rounded-3xl' }
    });

    fetch("{{ route('backend.ai.triage_copilot') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ mews_id: mewsId })
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success' && res.data) {
            let adviceHtml = '<div class="text-left space-y-2.5 mt-3">';
            if (res.data.summary) {
                adviceHtml += `
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/40 border border-indigo-100 dark:border-indigo-800 rounded-xl text-xs font-bold text-indigo-900 dark:text-indigo-200">
                        <i class="fa-solid fa-stethoscope mr-1.5 text-indigo-600 dark:text-indigo-400"></i> ${res.data.summary}
                    </div>
                `;
            }
            adviceHtml += '<ul class="space-y-2">';
            (res.data.advice || []).forEach((item, index) => {
                adviceHtml += `
                    <li class="flex items-start gap-2.5 bg-slate-50 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs sm:text-sm text-slate-700 dark:text-slate-200 font-medium">
                        <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">${index + 1}</span>
                        <span class="leading-relaxed">${item}</span>
                    </li>
                `;
            });
            adviceHtml += '</ul></div>';

            Swal.fire({
                title: '<div class="text-lg font-black text-indigo-900 dark:text-indigo-300 flex items-center justify-center gap-2"><i class="fa-solid fa-kit-medical text-indigo-600 dark:text-indigo-400"></i> แนวทางปฐมพยาบาลเบื้องต้น</div>',
                html: adviceHtml,
                width: '36rem',
                showCloseButton: true,
                confirmButtonText: 'รับทราบ / ปิดหน้าต่าง',
                didOpen: () => {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar) sidebar.style.setProperty('z-index', '-1', 'important');
                },
                didClose: () => {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar) sidebar.style.setProperty('z-index', '40', 'important');
                },
                customClass: {
                    popup: 'bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-100 dark:border-slate-700',
                    confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-6 py-2.5 text-sm'
                }
            });
        } else {
            Swal.fire({ icon: 'error', title: 'ขออภัย', text: res.message || 'ไม่สามารถดึงคำแนะนำได้' });
        }
    })
    .catch(() => {
        Swal.fire({ icon: 'error', title: 'การเชื่อมต่อขัดข้อง', text: 'ไม่สามารถติดต่อ AI Copilot ได้ในขณะนี้' });
    });
}
</script>

<script type="module">
    document.addEventListener('DOMContentLoaded', function () {
        const patientId = {{ $patient->id }};
        if (typeof window.Echo !== 'undefined') {
            window.Echo.channel('patient.' + patientId)
                .listen('.mews.updated', (e) => {
                    fetchLatestTimeline();
                });
        }
    });
</script>
@endsection