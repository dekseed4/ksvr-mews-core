@extends('layouts.backend')
@php
    $hideExecutiveAiButton = true;
@endphp

@section('title', 'ประเมิน MEWS - KSVR Hospital')
@section('page_header', 'ประเมินสัญญาณชีพและคัดกรอง (MEWS)')

@section('content')
<div class="max-w-5xl mx-auto mt-6 pb-12">
    
    <!-- 🟢 Header: ข้อมูลผู้ป่วย (จัดระยะห่างใหม่ & ดึงข้อมูลสังกัดที่ถูกต้อง) -->
    <div class="bg-slate-800 rounded-3xl p-6 md:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-lg text-white">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 bg-emerald-500 rounded-2xl flex items-center justify-center text-3xl shadow-inner shrink-0 border border-emerald-400">
                <i class="fa-solid fa-user-injured"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold tracking-tight">{{ $patient->rank }}{{ $patient->first_name }} {{ $patient->last_name }}</h2>
                
                <!-- 🌟 จัดระยะห่างใหม่ด้วย gap-x-6 (แนวนอน) และ gap-y-3 (แนวตั้งเมื่อจอเล็ก) -->
                <div class="text-slate-300 mt-2 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm font-medium">
                    
                    <!-- เลขบัตร ปชช. -->
                    <span class="flex items-center gap-2">
                        <i class="fa-regular fa-id-card text-emerald-400 text-lg"></i> {{ $patient->cid ?? '-' }}
                    </span>
                    
                    <!-- รหัส HN -->
                    <span class="flex items-center gap-2 bg-blue-500/20 px-3 py-1 rounded-lg border border-blue-500/30 text-blue-100">
                        <i class="fa-solid fa-hospital-user text-blue-400 text-lg"></i> HN: <span class="font-bold tracking-wider">{{ $patient->hn ?? 'ยังไม่ซิงค์' }}</span>
                    </span>
                    
                    <!-- 🌟 อ้างอิงชื่อหน่วยจาก Relationship (militaryUnit) หรือ company เดิม -->
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

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-2xl mb-6 shadow-sm">
            <div class="font-bold mb-2 flex items-center">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> บันทึกข้อมูลไม่สำเร็จ กรุณาตรวจสอบ:
            </div>
            <ul class="list-disc list-inside text-sm space-y-1 ml-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <!-- 🌟 Smart Alert Banner: แสดงผลคัดกรองทหารใหม่ (ถ้ามี) -->
    @if($patient->recruitScreening)
        @php
            $scColor = $patient->recruitScreening->color_group;
            $bgClass = $scColor == 'red' ? 'bg-rose-50 border-rose-200 text-rose-700' : ($scColor == 'yellow' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700');
            $icon = $scColor == 'red' ? 'fa-virus-covid text-rose-500' : ($scColor == 'yellow' ? 'fa-head-side-cough text-amber-500' : 'fa-shield-virus text-emerald-500');
            $label = $scColor == 'red' ? 'กลุ่มพบเชื้อ / เสี่ยงสูงมาก (สีแดง)' : ($scColor == 'yellow' ? 'กลุ่มเสี่ยง / ต้องเฝ้าระวัง (สีเหลือง)' : 'กลุ่มปกติ (สีเขียว)');
        @endphp

        <!-- จะแสดงผลโดดเด่นเฉพาะสีเหลืองและสีแดง -->
        @if(in_array($scColor, ['red', 'yellow']))
            <div class="mt-6 mb-8 {{ $bgClass }} p-5 rounded-2xl border shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-4 animate-fade-in-down">
                <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-2xl shrink-0 shadow-sm">
                    <i class="fa-solid {{ $icon }}"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-black text-base md:text-lg tracking-wide mb-1">
                        ข้อควรระวัง: ผู้ป่วยรายนี้ผ่านการคัดกรองอยู่ใน {{ $label }}
                    </h4>
                    <p class="text-sm font-medium opacity-90 flex items-center gap-2">
                        <i class="fa-solid fa-notes-medical"></i>
                        {{ $patient->recruitScreening->symptoms_note ?? 'ไม่มีบันทึกอาการเพิ่มเติม' }}
                    </p>
                </div>
                <div class="text-xs font-bold opacity-70 shrink-0 text-right mt-2 sm:mt-0 bg-white/50 px-3 py-1.5 rounded-lg">
                    คัดกรองเมื่อ: {{ $patient->recruitScreening->created_at->addYears(543)->format('d/m/Y H:i น.') }}
                </div>
            </div>
        @endif
    @endif

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
    
    <form action="{{ route('backend.mews.store') }}" method="POST" id="mews-form">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
        <input type="hidden" name="total_score" id="hidden_total_score">
        <input type="hidden" name="risk_level" id="hidden_risk_level">
        
        @if($isReassessment)
            <!-- 🔵 กรณีประเมินต่อเนื่องในเคสเดิม -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-3xl p-6 md:p-8 mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-sm animate-fade-in-up">
                <div class="flex items-center gap-6 w-full">
                    <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-2xl shrink-0 shadow-inner">
                        <i class="fa-solid fa-truck-medical"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-xl font-bold text-blue-800 flex flex-wrap items-center gap-3 mb-1.5">
                            บันทึกการประเมินต่อเนื่อง (แฟ้มประวัติที่ {{ $currentCaseNumber }})
                            <span class="bg-blue-600 text-white text-xs px-3 py-1 rounded-full uppercase tracking-wider shadow-sm font-bold">ประเมินครั้งที่ {{ $previousCount + 1 }}</span>
                        </h4>
                        <div class="text-blue-700/80 font-medium text-sm flex flex-wrap items-center gap-2">
                            <span>ผู้ป่วยกำลังอยู่ในกระบวนการรักษา และผ่านการประเมินในแฟ้มนี้มาแล้ว <span class="font-bold">{{ $previousCount }} ครั้ง</span></span>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- 🟢 กรณีเริ่มต้นเคสใหม่ -->
            <div class="mt-6 bg-emerald-50 border border-emerald-200 rounded-3xl p-6 md:p-8 mb-6 flex flex-col md:flex-row items-start md:items-center gap-6 shadow-sm animate-fade-in-up">
                <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl shrink-0 shadow-inner">
                    <i class="fa-solid fa-folder-plus"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-xl font-bold text-emerald-800 flex flex-wrap items-center gap-3 mb-1.5">
                        เริ่มต้นเปิดแฟ้มใหม่ (แฟ้มประวัติที่ {{ $currentCaseNumber }})
                        <span class="bg-emerald-600 text-white text-xs px-3 py-1 rounded-full uppercase tracking-wider shadow-sm font-bold">ประเมินครั้งที่ 1</span>
                    </h4>
                    <p class="text-emerald-700/80 font-medium text-sm">
                        นี่คือการประเมิน <span class="font-bold underline decoration-emerald-400 decoration-2 underline-offset-2">ครั้งแรก</span> ของแฟ้มประวัติใหม่ โปรดกรอกข้อมูลสัญญาณชีพและอาการให้ครบถ้วน
                    </p>
                </div>
            </div>
        @endif
        
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- 🌟🌟 ฝั่งซ้าย (กินพื้นที่ 2 คอลัมน์ — ใช้ Native CSS Flexbox Order เรียงลำดับ 1 -> 2 -> 3 ตามสิทธิ์ 100%) 🌟🌟 -->
            <div class="lg:col-span-2" style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                @php
                    // ตรวจสอบสิทธิ์ผู้ใช้งานทั้งหมดในระบบ
                    $rawUserRoles = auth()->user()->roles ?? auth()->user()->role ?? [];
                    $currentRoles = is_string($rawUserRoles) ? json_decode($rawUserRoles, true) : (array) $rawUserRoles;

                    $isHospitalRole = in_array('admin', $currentRoles)
                                   || in_array('er_nurse', $currentRoles)
                                   || in_array('opd_nurse', $currentRoles)
                                   || in_array('medical_staff', $currentRoles)
                                   || in_array('executive', $currentRoles)
                                   || in_array('registration', $currentRoles);

                    // เป็น true เฉพาะเมื่อล็อกอินด้วยสิทธิ์ "เสนารักษ์ (medic)" เพียวๆ เท่านั้น
                    $isMedicView = in_array('medic', $currentRoles) && !$isHospitalRole;

                    // กำหนดลำดับที่ (2 หรือ 3) ให้ตรงกันทั้งตำแหน่งกล่องและตัวเลขหน้าหัวข้อ
                    $emergencyBoxOrder = $isMedicView ? 3 : 2;
                    $clinicalNoteOrder = $isMedicView ? 2 : 3;

                    // เตรียมข้อมูลการประเมินรอบก่อนหน้า (กรณีประเมินซ้ำ)
                    $showPreviousMedicSymptoms = false;
                    $latestSymptoms = [];
                    $previousAssessor = 'รอบก่อนหน้า';
                    $latestMewsOfThisCase = null;

                    if ($isReassessment) {
                        $latestMewsOfThisCase = $patient->mewsAssessments()->latest()->first();

                        if ($latestMewsOfThisCase) {
                            $showPreviousMedicSymptoms = true;
                            $latestSymptoms = $latestMewsOfThisCase->symptoms ?? [];
                            
                            if (is_string($latestSymptoms)) {
                                $latestSymptoms = json_decode($latestSymptoms, true) ?? [];
                            }

                            $previousAssessor = $latestMewsOfThisCase->assessment_type === 'field' 
                                                ? 'เสนารักษ์ (ภาคสนาม)' 
                                                : 'พยาบาล (ER)';
                        }
                    }
                @endphp

                <!-- 1. ฟอร์มกรอกสัญญาณชีพ (V/S) — อยู่ลำดับที่ 1 เสมอ -->
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100" style="order: 1;">
                    <h3 class="text-lg font-bold text-slate-800 mb-6 pb-4 border-b flex items-center gap-2">
                        <i class="fa-solid fa-heart-pulse text-rose-500"></i> 1. สัญญาณชีพ (Vital Signs)
                    </h3>
                        
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Systolic BP -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>ความดันโลหิตตัวบน (SBP) <span class="text-slate-400 text-xs font-normal ml-1">mmHg</span></span>
                                <button type="button" onclick="showCriteria('sbp', 'ความดันโลหิตตัวบน (SBP)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" id="sbp" name="sbp" value="{{ old('sbp') }}" min="0" placeholder="เช่น 120" inputmode="numeric" pattern="[0-9]*" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- Pulse (HR) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>ชีพจร (Pulse) <span class="text-slate-400 text-xs font-normal ml-1">ครั้ง/นาที</span></span>
                                <button type="button" onclick="showCriteria('hr', 'ชีพจร (HR)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" id="hr" name="hr" value="{{ old('hr') }}" min="0" placeholder="เช่น 80" inputmode="numeric" pattern="[0-9]*" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- Respiratory Rate (RR) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>อัตราการหายใจ (RR) <span class="text-slate-400 text-xs font-normal ml-1">ครั้ง/นาที</span></span>
                                <button type="button" onclick="showCriteria('rr', 'อัตราการหายใจ (RR)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" id="rr" name="rr" value="{{ old('rr') }}" min="0" placeholder="เช่น 18" inputmode="numeric" pattern="[0-9]*" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- Temp -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>อุณหภูมิ (Temp) <span class="text-slate-400 text-xs font-normal ml-1">°C</span></span>
                                <button type="button" onclick="showCriteria('temp', 'อุณหภูมิร่างกาย (Temp)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" step="0.1" min="0" id="temp" name="temp" value="{{ old('temp') }}" inputmode="decimal" placeholder="เช่น 36.5" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- O2 Sat -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>ออกซิเจนในเลือด (O2 Sat) <span class="text-slate-400 text-xs font-normal ml-1">%</span></span>
                                <button type="button" onclick="showCriteria('o2sat', 'ออกซิเจนในเลือด (O2 Sat)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" id="o2sat" name="o2sat" value="{{ old('o2sat') }}" min="0" max="100" inputmode="numeric" pattern="[0-9]*" placeholder="เช่น 98" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- AVPU -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>ระดับความรู้สึกตัว (AVPU)</span>
                                <button type="button" onclick="showCriteria('avpu', 'ระดับการรู้สึกตัว (AVPU)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <select id="avpu" name="avpu" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-base text-center" required>
                                <option value="" disabled {{ old('avpu') ? '' : 'selected' }}>-- เลือกการตอบสนอง --</option>
                                <option value="A" {{ old('avpu') == 'A' ? 'selected' : '' }}>Alert (รู้สึกตัวดี)</option>
                                <option value="C" {{ old('avpu') == 'C' ? 'selected' : '' }}>Confused (สับสน)</option>
                                <option value="P" {{ old('avpu') == 'P' ? 'selected' : '' }}>Response to pain (กระตุ้นด้วยความเจ็บ)</option>
                                <option value="V" {{ old('avpu') == 'V' ? 'selected' : '' }}>Response to voice (เรียกถึงรู้สึกตัว)</option>
                                <option value="U" {{ old('avpu') == 'U' ? 'selected' : '' }}>Unresponsive (ไม่ตอบสนอง)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 🚨 กล่องอาการฉุกเฉินเฉพาะโรค (Emergency Specific Condition) -->
                <!-- สำหรับ Admin / พยาบาล รพ. จะอยู่ลำดับที่ 2 (order: 2) | สำหรับเสนารักษ์ จะอยู่ลำดับที่ 3 (order: 3) -->
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100" style="order: {{ $emergencyBoxOrder }};">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4 pb-4 border-b border-slate-100">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-truck-medical text-red-500"></i>
                            <span>{{ $emergencyBoxOrder }}. อาการฉุกเฉินเฉพาะโรค (Emergency Specific Condition)</span>
                        </h3>
                        @if($isMedicView)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 w-fit">
                                <i class="fa-solid fa-triangle-exclamation text-rose-500"></i> ติ๊กเฉพาะเมื่อพบอาการวิกฤตฉุกเฉินเท่านั้น
                            </span>
                        @endif
                    </div>

                    @if($isMedicView)
                        <!-- คำแนะนำพิเศษสำหรับเสนารักษ์ -->
                        <div class="mb-5 p-3.5 bg-amber-50/90 border border-amber-200 rounded-2xl text-xs sm:text-sm text-amber-900 flex items-start gap-3">
                            <i class="fa-solid fa-circle-info text-amber-500 text-base mt-0.5 shrink-0"></i>
                            <div class="leading-relaxed">
                                <b>คำแนะนำสำหรับเสนารักษ์:</b> หากพลทหารมาด้วยอาการเจ็บป่วยทั่วไป (เช่น มาทำแผล, ไข้หวัดทั่วไป, ปวดเมื่อย หรือมาตามนัด) <b>ไม่ต้องติ๊กช่องใดๆ ในหมวดนี้</b> (ให้เว้นว่างไว้ ระบบจะส่งเข้าคิวตรวจ OPD ตามปกติ) <span class="text-rose-600 font-bold">ให้ติ๊กเฉพาะกรณีที่ผู้ป่วยมีอาการรุนแรงที่ต้องส่งเข้าห้องฉุกเฉิน (ER) ด่วนเท่านั้นครับ</span>
                            </div>
                        </div>
                    @endif

                    @if($showPreviousMedicSymptoms)
                        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-2xl shadow-sm animate-fade-in-up">
                            <p class="text-sm font-bold text-blue-800 mb-2 flex items-center gap-2">
                                <i class="fa-solid fa-notes-medical"></i> อาการที่ {{ $previousAssessor }} ระบุไว้:
                            </p>
                            @if(is_array($latestSymptoms) && count($latestSymptoms) > 0)
                                <ul class="list-disc list-inside text-sm text-blue-700 font-medium ml-2 space-y-1">
                                    @foreach($latestSymptoms as $symKey)
                                        <li>{{ $symptomsDict[$symKey] ?? $symKey }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="flex items-center gap-2 text-sm text-blue-600/80 font-medium italic ml-1 mt-1">
                                    <i class="fa-solid fa-circle-check text-xs"></i> 
                                    รอบที่แล้ว ไม่พบการระบุอาการฉุกเฉินเฉพาะโรค
                                </div>
                            @endif
                        </div>
                    @endif
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @php 
                            $oldConds = old('emergency_conditions', $latestSymptoms ?? []); 
                            if (is_string($oldConds)) {
                                $oldConds = json_decode($oldConds, true) ?? [];
                            }
                            
                            $colorThemes = [
                                'Heat Injury' => [
                                    'title'    => 'Heat Injury (โรคลมร้อน / ความร้อน)',
                                    'icon'     => 'fa-sun text-orange-500',
                                    'bg'       => 'bg-orange-50/60', 
                                    'border'   => 'border-orange-200', 
                                    'text'     => 'text-orange-900', 
                                    'line'     => 'border-orange-200/80', 
                                    'checkbox' => 'border-orange-300 text-orange-600 focus:ring-orange-500'
                                ],
                                'Trauma' => [
                                    'title'    => 'Trauma (อุบัติเหตุ / บาดเจ็บจากการฝึก)',
                                    'icon'     => 'fa-crutch text-sky-500',
                                    'bg'       => 'bg-sky-50/60', 
                                    'border'   => 'border-sky-200', 
                                    'text'     => 'text-sky-900', 
                                    'line'     => 'border-sky-200/80', 
                                    'checkbox' => 'border-sky-300 text-sky-600 focus:ring-sky-500'
                                ],
                                'Anaphylaxis' => [
                                    'title'    => 'Anaphylaxis (แพ้รุนแรง / แมลงกัดต่อย)',
                                    'icon'     => 'fa-hand-dots text-pink-500',
                                    'bg'       => 'bg-pink-50/50', 
                                    'border'   => 'border-pink-200', 
                                    'text'     => 'text-pink-900', 
                                    'line'     => 'border-pink-200/80', 
                                    'checkbox' => 'border-pink-300 text-pink-600 focus:ring-pink-500'
                                ],
                                'MI' => [
                                    'title'    => 'MI (กล้ามเนื้อหัวใจขาดเลือด / แน่นอก)',
                                    'icon'     => 'fa-heart-crack text-rose-500',
                                    'bg'       => 'bg-rose-50/50', 
                                    'border'   => 'border-rose-200', 
                                    'text'     => 'text-rose-900', 
                                    'line'     => 'border-rose-200/80', 
                                    'checkbox' => 'border-rose-300 text-rose-600 focus:ring-rose-500'
                                ],
                                'Sepsis' => [
                                    'title'    => 'Sepsis (ติดเชื้อในกระแสเลือด / ซึมหอบ)',
                                    'icon'     => 'fa-bacteria text-purple-500',
                                    'bg'       => 'bg-purple-50/50', 
                                    'border'   => 'border-purple-200', 
                                    'text'     => 'text-purple-900', 
                                    'line'     => 'border-purple-200/80', 
                                    'checkbox' => 'border-purple-300 text-purple-600 focus:ring-purple-500'
                                ],
                                'Appendicitis' => [
                                    'title'    => 'Appendicitis (ปวดท้องไส้ติ่งอักเสบ)',
                                    'icon'     => 'fa-notes-medical text-emerald-500',
                                    'bg'       => 'bg-emerald-50/50', 
                                    'border'   => 'border-emerald-200', 
                                    'text'     => 'text-emerald-900', 
                                    'line'     => 'border-emerald-200/80', 
                                    'checkbox' => 'border-emerald-300 text-emerald-600 focus:ring-emerald-500'
                                ],
                                'Stroke' => [
                                    'title'    => 'Stroke (หลอดเลือดสมอง)',
                                    'icon'     => 'fa-brain text-indigo-500',
                                    'bg'       => 'bg-indigo-50/50', 
                                    'border'   => 'border-indigo-200', 
                                    'text'     => 'text-indigo-900', 
                                    'line'     => 'border-indigo-200/80', 
                                    'checkbox' => 'border-indigo-300 text-indigo-600 focus:ring-indigo-500'
                                ],
                            ];

                            $defaultTheme = [
                                'title'    => null,
                                'icon'     => 'fa-circle-exclamation text-slate-500',
                                'bg'       => 'bg-slate-50/50', 
                                'border'   => 'border-slate-200', 
                                'text'     => 'text-slate-800', 
                                'line'     => 'border-slate-200', 
                                'checkbox' => 'border-slate-300 text-slate-600 focus:ring-slate-500'
                            ];
                        @endphp

                        @foreach($activeSymptoms as $category => $symptomsList)
                            @php 
                                $theme = $colorThemes[$category] ?? $defaultTheme; 
                                $displayTitle = $theme['title'] ?? $category;
                            @endphp
                            
                            <div class="{{ $theme['bg'] }} rounded-2xl p-5 border {{ $theme['border'] }} hover:shadow-md transition-all">
                                <h4 class="font-bold {{ $theme['text'] }} mb-3 border-b {{ $theme['line'] }} pb-2.5 flex items-center gap-2 text-sm md:text-base">
                                    <i class="fa-solid {{ $theme['icon'] }}"></i>
                                    <span>{{ $displayTitle }}</span>
                                </h4>
                                <div class="space-y-3">
                                    @foreach($symptomsList as $sym)
                                        <label class="flex items-start gap-3 cursor-pointer group p-1 rounded-lg hover:bg-white/60 transition-colors">
                                            <input type="checkbox" name="emergency_conditions[]" value="{{ $sym->code }}" 
                                                   {{ is_array($oldConds) && in_array($sym->code, $oldConds) ? 'checked' : '' }} 
                                                   class="mt-0.5 w-5 h-5 rounded {{ $theme['checkbox'] }} transition-colors cursor-pointer shrink-0">
                                            <span class="text-sm text-slate-700 group-hover:text-slate-900 font-medium leading-snug">{{ $sym->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 🌟 กล่องอาการสำคัญ / บันทึกเพิ่มเติม (Clinical Note) -->
                <!-- สำหรับเสนารักษ์ จะอยู่ลำดับที่ 2 (order: 2) | สำหรับ Admin / พยาบาล รพ. จะอยู่ลำดับที่ 3 (order: 3) -->
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100" style="order: {{ $clinicalNoteOrder }};">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4 pb-4 border-b border-slate-100">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-notes-medical text-emerald-500"></i>
                            <span>{{ $clinicalNoteOrder }}. อาการสำคัญ / บันทึกเพิ่มเติม (Clinical Note)</span>
                        </h3>
                        <span class="text-xs text-slate-400 font-medium">ระบุอาการทั่วไป เช่น มาทำแผล, ตัดไหม, รับยา หรือรายละเอียดเพิ่มเติม</span>
                    </div>

                    @if($showPreviousMedicSymptoms && !empty($latestMewsOfThisCase->symptoms_note))
                        <div class="mb-4 p-3.5 bg-amber-50/80 border border-amber-200 rounded-2xl text-xs text-amber-900 flex items-start gap-2.5">
                            <i class="fa-solid fa-clock-rotate-left text-amber-500 mt-0.5"></i>
                            <div>
                                <span class="font-bold">บันทึกจาก {{ $previousAssessor }} รอบก่อนหน้า:</span>
                                <p class="mt-0.5 font-medium">{{ $latestMewsOfThisCase->symptoms_note }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-wand-magic-sparkles text-indigo-400 mr-1"></i> แตะเพื่อเพิ่มข้อความด่วน:
                        </span>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['มาทำแผล / ล้างแผลต่อเนื่อง', 'นัดตัดไหม', 'ขอรับยาต่อเนื่อง', 'ปวดเมื่อยกล้ามเนื้อ / ข้อเท้าพลิก', 'มีไข้ / ไอ / เจ็บคอทั่วไป', 'ทำหัตถการอื่นๆ'] as $quickTag)
                                <button type="button" onclick="appendSymptomNote('{{ $quickTag }}')" 
                                        class="px-3 py-1.5 bg-slate-50 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 border border-slate-200 hover:border-emerald-300 rounded-xl text-xs font-bold transition-all shadow-sm active:scale-95">
                                    + {{ $quickTag }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <textarea id="symptoms_note" name="symptoms_note" rows="3" maxlength="1000"
                              placeholder="พิมพ์อาการสำคัญ หรือรายละเอียดเพิ่มเติมที่นี่ เช่น มาทำแผลที่เข่าขวา, ขอรับยาลดน้ำมูก..."
                              class="w-full rounded-2xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors p-4 text-sm text-slate-800 placeholder:text-slate-400 shadow-inner">{{ old('symptoms_note', $latestMewsOfThisCase->symptoms_note ?? '') }}</textarea>
                </div>

            </div>
            
            <!-- 🌟🌟 ฝั่งขวา: กล่องแสดงผลคะแนน MEWS Real-time 🌟🌟 -->
            <div class="space-y-6">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 sticky top-6 text-center">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">คะแนนรวม MEWS Score</h3>
                    
                    <div id="mews-badge" class="w-32 h-32 mx-auto rounded-full bg-slate-100 border-4 border-slate-200 flex items-center justify-center transition-all duration-500 mb-4 shadow-inner">
                        <span id="mews-score-display" class="text-5xl font-black text-slate-400">-</span>
                    </div>
                    
                    <p id="mews-status-text" class="text-slate-500 font-medium min-h-[3rem] leading-relaxed mb-4">รอคำนวณ...</p>
                    
                    @if(isset($activeTemplate) && $activeTemplate)
                        <div class="mb-5 bg-indigo-50 border border-indigo-100 p-3 rounded-xl shadow-sm animate-fade-in-up w-full">
                            <div class="flex items-start justify-center gap-2">
                                <i class="fa-solid fa-calculator text-indigo-500 text-xs mt-1 shrink-0"></i>
                                <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide leading-relaxed break-words text-left">
                                    อ้างอิง: {{ $activeTemplate->name }}
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="mb-5 bg-rose-50 border border-rose-100 p-3 rounded-xl shadow-sm animate-fade-in-up w-full">
                            <div class="flex items-start justify-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-rose-500 text-xs mt-1 shrink-0"></i>
                                <span class="text-xs font-bold text-rose-700 leading-relaxed text-left">
                                    ไม่พบเกณฑ์ที่เปิดใช้งาน
                                </span>
                            </div>
                        </div>
                    @endif
                    
                    
                    <!-- กล่องแจ้งเตือนอาการฉุกเฉิน (จะถูกควบคุมโดย JS) -->
                    <div id="emergency-alert-box" class="hidden mt-2 p-4 bg-red-50 text-red-700 rounded-xl text-sm border border-red-200 animate-fade-in-up text-left shadow-sm">
                        <div class="font-bold flex items-center mb-2 border-b border-red-200 pb-2">
                            <i class="fa-solid fa-truck-medical animate-pulse mr-2 text-lg text-red-600"></i> 
                            <span id="emergency-alert-text">พบอาการฉุกเฉินเฉพาะโรค!</span>
                        </div>
                        <ul id="emergency-alert-list" class="list-disc list-inside space-y-1 text-xs font-medium text-red-800 ml-1">
                        </ul>
                    </div>
                    
                    <!-- 🩺 กล่องผู้ช่วย AI Clinical Triage Copilot (คำแนะนำการปฐมพยาบาลเบื้องต้น) -->
                    <div class="mt-4 text-left">
                        <button type="button" onclick="askTriageCopilot()" id="btn_triage_copilot" class="w-full py-2.5 px-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl font-bold text-xs transition-all flex items-center justify-center gap-2 shadow-sm">
                            <i class="fa-solid fa-user-doctor text-indigo-600"></i> ขอคำแนะนำปฐมพยาบาลเบื้องต้น (AI Copilot)
                        </button>

                        <div id="ai-copilot-box" class="hidden mt-3 p-4 bg-gradient-to-br from-indigo-50/90 to-blue-50/90 text-indigo-950 rounded-2xl text-xs border border-indigo-200 shadow-sm animate-fade-in-up">
                            <div class="font-black flex items-center justify-between mb-2 border-b border-indigo-200/70 pb-2 text-indigo-800">
                                <span class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-kit-medical text-indigo-600"></i> แนวทางปฐมพยาบาลเบื้องต้น (AI Copilot)
                                </span>
                            </div>
                            <p id="ai-copilot-summary" class="font-bold text-slate-700 mb-2"></p>
                            <ul id="ai-copilot-list" class="space-y-1.5 text-slate-700 font-medium list-none"></ul>
                        </div>
                    </div>

                    <hr class="my-6 border-slate-100">
                    
                    <div class="flex flex-col gap-3">
                        <button type="submit" class="w-full py-4 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-bold shadow-md transition-colors text-lg flex items-center justify-center gap-2">
                            <i class="fa-solid fa-save"></i> บันทึกผลการประเมิน
                        </button>
                        
                        <!-- ปุ่มยกเลิก กลับไปหน้าก่อนหน้า -->
                        <a href="{{ route('backend.patients.index') }}" class="w-full py-3 bg-white border-2 border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-500 hover:text-slate-700 rounded-xl font-bold shadow-sm transition-colors text-center flex items-center justify-center gap-2">
                            <i class="fa-solid fa-xmark"></i> ยกเลิก
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
        <!-- 🌟 โซน Floating AI Widget (เมนูลอยมุมขวาล่าง) -->
        <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-4 pointer-events-none">
            
            <!-- 🌟 แก้ไขบรรทัดล่างนี้: ลบ scale-0 opacity-0 ออก แล้วใช้ style ควบคุมแทน -->
            <div id="ai_floating_widget" class="hidden flex-col bg-white/95 backdrop-blur-md w-[320px] sm:w-[380px] rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-indigo-100 overflow-hidden transform transition-all duration-300 origin-bottom-right pointer-events-auto" style="transform: scale(0); opacity: 0;">

                <!-- ส่วนหัว Header -->
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-4 flex justify-between items-center border-b border-indigo-100/50">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center shadow-inner">
                            <i class="fa-solid fa-robot text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-indigo-900 leading-none mb-1 text-sm">AI อัจฉริยะ</h3>
                            <p class="text-[10px] text-indigo-500 font-medium tracking-wide">SMART ASSISTANT</p>
                        </div>
                    </div>
                    <button type="button" onclick="toggleAiWidget()" class="text-slate-400 hover:text-rose-500 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-rose-50">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- ส่วนรับคำสั่ง Input & Buttons -->
                <div class="p-5 space-y-4">
                    
                    <!-- กล่องข้อความ และประวัติ -->
                    <div class="relative w-full flex flex-col gap-2">
                        <input type="text" id="ai_prompt_input" 
                            placeholder="พิมพ์ / กดปุ่มพูด 'ความดัน 120...'" 
                            class="w-full pl-4 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition-colors shadow-inner text-slate-700 text-sm h-[45px]">
                        
                        <!-- จุดแสดงข้อความพิมพ์สด -->
                        <span id="interim_text_display" class="text-xs text-indigo-500 italic font-medium min-h-[16px] hidden px-2 transition-all"></span>
                        
                        <!-- กล่องประวัติล่าสุด (ชั่วคราว) -->
                        <div id="last_analyzed_box" class="hidden items-start gap-2 p-2 bg-emerald-50 border border-emerald-200 rounded-lg shadow-sm text-emerald-700 animate-fade-in-up">
                            <i class="fa-solid fa-clock-rotate-left mt-0.5 text-emerald-500 text-xs"></i>
                            <span id="last_analyzed_text" class="text-[11px] font-medium italic flex-1 leading-tight"></span>
                            <button type="button" onclick="document.getElementById('last_analyzed_box').classList.add('hidden'); document.getElementById('last_analyzed_box').classList.remove('flex');" class="text-emerald-400 hover:text-emerald-600 transition-colors" title="ซ่อนกล่อง">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ปุ่มควบคุม 2 ปุ่ม -->
                    <div class="flex gap-2">
                        <button type="button" id="btn_voice_record" onclick="startDictation()" class="flex-[3] py-2.5 bg-rose-100 hover:bg-rose-200 text-rose-600 font-bold rounded-xl shadow-sm transition-all duration-300 flex items-center justify-center gap-2 border border-rose-200 text-sm">
                            <i class="fa-solid fa-microphone" id="mic_icon"></i> <span id="mic_text">พูดสั่งงาน</span>
                        </button>
                        <button type="button" id="btn_ai_extract" onclick="extractVitalsWithAI()" class="flex-[2] py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm transition-transform hover:-translate-y-0.5 flex items-center justify-center gap-1.5 text-sm">
                            <i class="fa-solid fa-bolt"></i> วิเคราะห์
                        </button>
                    </div>

                </div>
            </div>

            @section('floating_buttons')
                <!-- 🌟 ปุ่มเสริมเฉพาะหน้า Medic: ค้นหาคนไข้ใหม่ (จะลอยอยู่เหนือปุ่ม AI อัตโนมัติ) -->
                <button type="button" onclick="toggleAiWidget()" id="fab_ai_btn" class="w-14 h-14 bg-indigo-600 text-white rounded-full shadow-[0_10px_25px_rgba(79,70,229,0.5)] hover:bg-indigo-700 hover:scale-110 transition-all flex items-center justify-center animate-bounce group relative pointer-events-auto">
                    <i class="fa-solid fa-wand-magic-sparkles text-xl group-hover:rotate-12 transition-transform"></i>
                    <!-- จุดแดงแจ้งเตือนให้ดึงดูดสายตา -->
                    <span class="absolute top-0 right-0 flex h-3.5 w-3.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-rose-500 border-2 border-white"></span>
                    </span>
                </button>
            @endsection
            
        </div>
    </form>
</div>
@endsection

@section('scripts')
    
    <script>
        let isAnalyzingAI = false; // 🌟 ตัวแปรล็อกป้องกันการยิง API ซ้ำซ้อน

        function extractVitalsWithAI() {
            // ถ้ากำลังอัดเสียงอยู่แล้วผู้ใช้กดปุ่ม "วิเคราะห์" ให้หยุดไมค์ก่อน (โดยไม่ต้องให้ onend ยิงซ้ำ)
            if (typeof isRecording !== 'undefined' && isRecording && recognition) {
                recognition.stop();
                return; // ปล่อยให้ recognition.onend เป็นคนเรียกฟังก์ชันนี้ต่อเพียงรอบเดียว
            }

            const inputText = document.getElementById('ai_prompt_input').value.trim();
            if (!inputText || isAnalyzingAI) {
                if (!inputText) {
                    Swal.fire({ icon: 'warning', title: 'กรุณาระบุข้อมูล', text: 'พิมพ์หรือพูดข้อมูลสัญญาณชีพก่อนครับ' });
                }
                return;
            }

            isAnalyzingAI = true;

            Swal.fire({
                title: 'Gemini กำลังวิเคราะห์...',
                html: 'รอสักครู่ ระบบกำลังสกัดข้อมูลสัญญาณชีพให้อัตโนมัติ',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('{{ route("backend.ai.extract_vitals") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ 
                    text: inputText,
                    patient_id: '{{ $patient->id }}' // 🌟 ส่ง patient_id ไปด้วยเพื่อใช้ร่วมกับ Triage Copilot
                })
            })
            .then(response => response.json())
            .then(data => {
                isAnalyzingAI = false;
                if (data.status === 'success') {
                    Swal.close();
                    
                    // 🌟 อัปเกรด: หยอดค่าพร้อมสั่ง Dispatch Event ให้ mews-calculator.js คำนวณคะแนนทันที!
                    const setVal = (id, val) => {
                        const el = document.getElementById(id);
                        if (el && val !== null && val !== undefined && val !== '') {
                            el.value = val;
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    };

                    // 1. หยอดข้อมูลตัวเลขและช่อง Select ให้ครบทุกช่อง
                    setVal('sbp', data.data.sbp);
                    setVal('hr', data.data.hr);
                    setVal('rr', data.data.rr);
                    setVal('temp', data.data.temp);
                    setVal('o2sat', data.data.o2sat);
                    setVal('avpu', data.data.avpu);
                    setVal('symptoms_note', data.data.note);

                    // 2. ลอจิกติ๊กถูก อาการฉุกเฉินเฉพาะโรคอัตโนมัติ พร้อมสั่ง Trigger Event
                    document.querySelectorAll('input[name="emergency_conditions[]"]').forEach(cb => {
                        if (cb.checked) {
                            cb.checked = false;
                            cb.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });

                    if (data.data.conditions && Array.isArray(data.data.conditions)) {
                        data.data.conditions.forEach(code => {
                            const checkbox = document.querySelector(`input[name="emergency_conditions[]"][value="${code}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
                    }

                    // 🌟 3. ถ้า AI ส่งคำแนะนำการปฐมพยาบาลเบื้องต้น (Triage Copilot) มาด้วย ให้แสดงผลทันที!
                    if (data.data.first_aid_advice && typeof renderCopilotAdvice === 'function') {
                        renderCopilotAdvice(data.data.first_aid_advice, data.data.note);
                    }

                    // 4. เอาข้อความจากช่อง Input ไปใส่กล่องประวัติชั่วคราว
                    const currentPromptText = document.getElementById('ai_prompt_input').value;
                    if (currentPromptText.trim() !== '') {
                        document.getElementById('last_analyzed_text').innerText = 'ประวัติล่าสุด: "' + currentPromptText + '"';
                        const historyBox = document.getElementById('last_analyzed_box');
                        historyBox.classList.remove('hidden');
                        historyBox.classList.add('flex');
                    }

                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success',
                        title: 'สกัดข้อมูลและคำนวณคะแนน MEWS สำเร็จ!',
                        showConfirmButton: false, timer: 4000
                    });
                    
                    document.getElementById('ai_prompt_input').value = '';
                    document.getElementById('ai_prompt_input').setAttribute('data-base-text', '');

                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message });
                }
            })
            .catch(error => {
                isAnalyzingAI = false;
                console.error(error);
                Swal.fire({ icon: 'error', title: 'การเชื่อมต่อล้มเหลว', text: 'ไม่สามารถติดต่อ AI ได้ หรือเกิดข้อผิดพลาดที่อินเทอร์เน็ต' });
            });
        }

        function appendSymptomNote(text) {
            const el = document.getElementById('symptoms_note');
            if (!el) return;
            const current = el.value.trim();
            el.value = current ? (current + ', ' + text) : text;
            el.focus();
        }
    </script>
    <!-- 🌟 สคริปต์สั่งงานด้วยเสียง (Web Speech API) - โหมด Continuous & Real-time -->
    <script>
        let recognition = null;
        let isRecording = false;

        function initSpeechRecognition() {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                return false;
            }
            
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            
            // 🌟 1. ฟังต่อเนื่องจนกว่าผู้ใช้จะกดหยุดเอง (แก้ปัญหาไมค์ตัดตอนพักหายใจ)
            recognition.continuous = true; 
            // 🌟 2. โชว์ข้อความสดๆ ระหว่างพูด (Real-time feedback)
            recognition.interimResults = true; 
            recognition.lang = "th-TH"; 

            recognition.onstart = function() {
                isRecording = true;
                updateVoiceUI(true);
            };

            recognition.onresult = function(e) {
                let interimTranscript = '';
                let finalTranscript = '';

                // ลูปแยกประเภทคำ: พูดจบประโยคแล้ว (isFinal) vs คำที่กำลังพูด (Interim)
                for (let i = e.resultIndex; i < e.results.length; ++i) {
                    if (e.results[i].isFinal) {
                        finalTranscript += e.results[i][0].transcript;
                    } else {
                        interimTranscript += e.results[i][0].transcript;
                    }
                }
                
                const inputPrompt = document.getElementById('ai_prompt_input');
                const interimDisplay = document.getElementById('interim_text_display');
                
                // 📝 ถ้ามีคำที่ "ยืนยันแล้ว" ให้เอาไปต่อท้ายในช่อง Input หลัก
                if (finalTranscript !== '') {
                    inputPrompt.value += finalTranscript + ' ';
                }
                
                // 🗣️ ถ้า "กำลังพูดอยู่" ให้เอาไปแสดงในกล่อง Interim ด้านล่างแบบ Real-time
                if (interimTranscript !== '') {
                    interimDisplay.classList.remove('hidden');
                    // ใส่ไอคอนรูปหูฟังกระพริบ เพื่อให้ดูน่าใช้งาน
                    interimDisplay.innerHTML = '<i class="fa-solid fa-ear-listen animate-pulse mr-1"></i> "' + interimTranscript + '..."';
                } else {
                    interimDisplay.classList.add('hidden');
                    interimDisplay.innerHTML = '';
                }
            };

            recognition.onerror = function(e) {
                console.warn('Speech Error:', e.error);
                if (e.error === 'aborted' || e.error === 'no-speech') return; // เพิกเฉยต่อ Error ที่ไม่อันตราย
                
                isRecording = false;
                updateVoiceUI(false);
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'ไมโครโฟนมีปัญหา: ' + e.error, showConfirmButton: false, timer: 3000 });
            };

            recognition.onend = function() {
                isRecording = false;
                updateVoiceUI(false);
                
                // 🌟 3. เมื่อไมค์ปิดตัวลง และมีข้อความในช่อง ให้สั่ง AI วิเคราะห์ข้อมูลทันที
                const finalStr = document.getElementById('ai_prompt_input').value.trim();
                if (finalStr !== '') {
                    extractVitalsWithAI();
                }
            };

            return true;
        }

        function startDictation() {
            if (!recognition) {
                if (!initSpeechRecognition()) {
                    Swal.fire({ icon: 'warning', title: 'ไม่รองรับ', text: 'เบราว์เซอร์ไม่รองรับคำสั่งเสียง แนะนำ Google Chrome ครับ' });
                    return;
                }
            }

            if (isRecording) {
                // กดซ้ำ = สั่งหยุดฟัง และระบบจะวิ่งไปเข้าฟังก์ชัน onend เพื่อประมวลผล
                recognition.stop();
                return;
            }

            try {
                // เคลียร์ช่องข้อความและเตรียมรับเสียงใหม่
                const inputPrompt = document.getElementById('ai_prompt_input');
                inputPrompt.value = '';
                inputPrompt.setAttribute('data-base-text', '');
                recognition.start();
            } catch (ex) {
                console.error("Start Error:", ex);
                recognition.stop();
            }
        }

        function updateVoiceUI(isListening) {
                const btnVoice = document.getElementById('btn_voice_record');
                const micIcon = document.getElementById('mic_icon');
                const micText = document.getElementById('mic_text');
                const inputPrompt = document.getElementById('ai_prompt_input');
                const interimDisplay = document.getElementById('interim_text_display'); // เพิ่มตัวแปรนี้

                if (isListening) {
                    btnVoice.classList.remove('bg-rose-100', 'text-rose-600');
                    btnVoice.classList.add('bg-rose-500', 'text-white', 'animate-pulse');
                    micIcon.classList.add('fa-beat-fade');
                    micText.innerText = 'กดเพื่อหยุดและวิเคราะห์';
                    inputPrompt.placeholder = 'กำลังฟัง... พูดข้อความได้เลยครับ';
                } else {
                    btnVoice.classList.remove('bg-rose-500', 'text-white', 'animate-pulse');
                    btnVoice.classList.add('bg-rose-100', 'text-rose-600');
                    micIcon.classList.remove('fa-beat-fade');
                    micText.innerText = 'พูดสั่งงาน';
                    
                    // ปิดซ่อนข้อความพิมพ์สดเมื่อไมค์หยุดทำงาน
                    interimDisplay.classList.add('hidden');
                    interimDisplay.innerHTML = '';

                    if(!inputPrompt.value) {
                        inputPrompt.placeholder = "พิมพ์หรือกดปุ่มพูด เช่น 'ความดัน 120 80...'";
                    }
                }
            }
    </script>
    
    <script>
        function toggleAiWidget() {
            const widget = document.getElementById('ai_floating_widget');
            const fabBtn = document.getElementById('fab_ai_btn');
            
            if (widget.classList.contains('hidden')) {
                // 1. เปิดให้กล่อง Widget มีตัวตน
                widget.classList.remove('hidden');
                widget.classList.add('flex');
                
                // 🌟 2. อนิเมชันซ่อนปุ่ม FAB (ไม้กายสิทธิ์) เพื่อไม่ให้บังปุ่มวิเคราะห์บนมือถือ
                if (fabBtn) {
                    fabBtn.classList.remove('animate-bounce');
                    fabBtn.style.transform = 'scale(0)';
                    fabBtn.style.opacity = '0';
                    // รอให้อนิเมชันหดตัวเสร็จค่อยใส่ class hidden
                    setTimeout(() => { fabBtn.classList.add('hidden'); }, 300); 
                }
                
                // 3. ถ่วงเวลา 10ms แล้วสั่งขยายขนาด Widget ด้วย JavaScript
                setTimeout(() => {
                    widget.style.transform = 'scale(1)';
                    widget.style.opacity = '1';
                    document.getElementById('ai_prompt_input').focus();
                }, 10);
                
            } else {
                // 1. สั่งหดกล่อง Widget และทำให้โปร่งใส
                widget.style.transform = 'scale(0)';
                widget.style.opacity = '0';
                
                // ปิดไมค์ถ้ากำลังอัดเสียงอยู่
                if (typeof isRecording !== 'undefined' && isRecording && typeof recognition !== 'undefined') {
                    recognition.stop();
                }
                
                // 🌟 2. ดึงปุ่ม FAB (ไม้กายสิทธิ์) กลับมาแสดงเหมือนเดิม
                if (fabBtn) {
                    fabBtn.classList.remove('hidden');
                    setTimeout(() => {
                        fabBtn.style.transform = 'scale(1)';
                        fabBtn.style.opacity = '1';
                    }, 10); // รอให้ display: block ทำงานก่อนค่อยสั่งขยายขนาด
                }
                
                // 3. รอให้อนิเมชันจบ (300ms) แล้วค่อยซ่อนกล่อง Widget
                setTimeout(() => {
                    widget.classList.remove('flex');
                    widget.classList.add('hidden');
                }, 300);
            }
        }
    </script>
    <!-- 🌟 สคริปต์สำหรับปุ่ม Info (ดูเกณฑ์) -->
    <script>
        // ดึงกฎเกณฑ์ทั้งหมดจาก PHP ส่งให้ Javascript
        const mewsRules = @json($activeTemplate->rules ?? []);

        // ฟังก์ชันแสดงเกณฑ์อ้างอิงเมื่อกดปุ่ม Info
        function showCriteria(paramKey, paramName) {
            if (!mewsRules[paramKey] || mewsRules[paramKey].length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'ไม่มีข้อมูลเกณฑ์',
                    text: 'ยังไม่มีการตั้งค่าเกณฑ์อ้างอิงสำหรับสัญญาณชีพนี้ครับ',
                    customClass: { popup: 'rounded-3xl' }
                });
                return;
            }

            // วาดตาราง HTML
            let tableHtml = `
                <div class="overflow-hidden rounded-xl border border-slate-200 mt-4 shadow-sm">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 font-bold text-center">ค่าที่วัดได้</th>
                                <th class="px-4 py-3 font-bold text-center w-24">คะแนน</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
            `;

            mewsRules[paramKey].forEach(rule => {
                let scoreBadge = '';
                if (rule.score === 0) scoreBadge = '<span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg font-bold shadow-sm">0</span>';
                else if (rule.score === 1) scoreBadge = '<span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-lg font-bold shadow-sm">1</span>';
                else if (rule.score === 2) scoreBadge = '<span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-lg font-bold shadow-sm">2</span>';
                else if (rule.score === 3) scoreBadge = '<span class="bg-rose-100 text-rose-700 px-3 py-1 rounded-lg font-bold shadow-sm">3</span>';
                else scoreBadge = `<span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-lg font-bold shadow-sm">${rule.score}</span>`;

                let conditionText = paramKey === 'avpu' ? rule.value : `${rule.min} - ${rule.max}`;

                tableHtml += `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 text-center font-medium text-slate-700">${conditionText}</td>
                        <td class="px-4 py-3 text-center">${scoreBadge}</td>
                    </tr>
                `;
            });

            tableHtml += `</tbody></table></div>`;

            // เปิดหน้าต่าง SweetAlert2
            Swal.fire({
                title: `<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-book-medical text-indigo-500 mr-2"></i>เกณฑ์อ้างอิง: ${paramName}</div>`,
                html: tableHtml,
                confirmButtonText: 'ปิดหน้าต่าง',
                customClass: {
                    popup: 'rounded-3xl shadow-2xl border border-slate-100',
                    confirmButton: 'bg-slate-800 hover:bg-slate-900 text-white rounded-xl px-6 py-2.5 font-bold'
                }
            });
        }

        // 🩺 ฟังก์ชันแสดงผลคำแนะนำปฐมพยาบาลบนหน้าจอ
        function renderCopilotAdvice(adviceList, summaryText = '') {
            const box = document.getElementById('ai-copilot-box');
            const summaryEl = document.getElementById('ai-copilot-summary');
            const listEl = document.getElementById('ai-copilot-list');

            if (!box || !listEl || !Array.isArray(adviceList) || adviceList.length === 0) return;

            summaryEl.textContent = summaryText || 'คำแนะนำการปฏิบัติตัวระหว่างรอส่งต่อ:';
            listEl.innerHTML = '';

            adviceList.forEach((item, idx) => {
                listEl.insertAdjacentHTML('beforeend', `
                    <li class="flex items-start gap-2 bg-white/80 p-2 rounded-lg border border-indigo-100">
                        <span class="w-4 h-4 rounded-full bg-indigo-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">${idx + 1}</span>
                        <span class="leading-relaxed">${item}</span>
                    </li>
                `);
            });

            box.classList.remove('hidden');
        }

        // 🩺 ฟังก์ชันกดปุ่มขอคำแนะนำ AI Copilot จากค่าสัญญาณชีพปัจจุบัน
        function askTriageCopilot() {
            const sbp = document.getElementById('sbp')?.value;
            const hr = document.getElementById('hr')?.value;
            const rr = document.getElementById('rr')?.value;
            const temp = document.getElementById('temp')?.value;
            const o2sat = document.getElementById('o2sat')?.value;
            const avpu = document.getElementById('avpu')?.value;
            const totalScore = document.getElementById('hidden_total_score')?.value || '0';

            if (!sbp && !hr && !temp) {
                Swal.fire({ icon: 'info', title: 'กรอกสัญญาณชีพก่อนครับ', text: 'โปรดระบุสัญญาณชีพอย่างน้อย 1-2 ค่า เพื่อให้ AI วิเคราะห์แนวทางปฐมพยาบาลได้อย่างแม่นยำครับ' });
                return;
            }

            const selectedConditions = Array.from(document.querySelectorAll('input[name="emergency_conditions[]"]:checked')).map(cb => cb.value);
            const btn = document.getElementById('btn_triage_copilot');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> AI กำลังวิเคราะห์แนวทางปฐมพยาบาล...';

            fetch('{{ route("backend.ai.triage_copilot") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    sbp: sbp, hr: hr, rr: rr, temp: temp, o2sat: o2sat, avpu: avpu,
                    total_score: totalScore,
                    conditions: selectedConditions,
                    screening_note: @json($patient->recruitScreening->symptoms_note ?? '')
                })
            })
            .then(res => res.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (res.status === 'success') {
                    renderCopilotAdvice(res.data.advice, res.data.summary);
                } else {
                    Swal.fire({ icon: 'error', title: 'ขออภัย', text: res.message });
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                Swal.fire({ icon: 'error', title: 'เชื่อมต่อไม่สำเร็จ', text: 'ไม่สามารถติดต่อ AI Copilot ได้ในขณะนี้' });
            });
        }
    </script>

    <!-- ไฟล์ JS หลักสำหรับการคำนวณ -->
    <script src="{{ asset('js/mews-calculator.js') }}"></script>
@endsection