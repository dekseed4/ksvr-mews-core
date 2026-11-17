@extends('layouts.backend')
@php
    $hideExecutiveAiButton = true;
    $patient = $mews->patient;
    $resolvedTemplate = $activeTemplate ?? $mews->template ?? \App\Models\MewsTemplate::where('is_active', true)->first();
@endphp

@section('title', 'แก้ไขผลประเมิน MEWS - KSVR Hospital')
@section('page_header', 'แก้ไขผลการประเมินสัญญาณชีพ (Edit MEWS)')

@section('content')
<div class="max-w-5xl mx-auto mt-6 pb-12">
    
    <!-- 🟢 Header: ข้อมูลผู้ป่วย -->
    <div class="bg-slate-800 rounded-3xl p-6 md:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-lg text-white mb-6">
        <div class="flex items-center gap-6">
            <div class="w-16 h-16 bg-amber-500 rounded-2xl flex items-center justify-center text-3xl shadow-inner shrink-0 border border-amber-400">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-2xl font-bold tracking-tight">{{ $patient->rank }}{{ $patient->first_name }} {{ $patient->last_name }}</h2>
                    <span class="px-3 py-0.5 bg-amber-500/20 text-amber-300 border border-amber-400/30 rounded-full text-xs font-bold">
                        โหมดแก้ไขข้อมูล (VN: {{ $mews->case_id }})
                    </span>
                </div>
                
                <div class="text-slate-300 mt-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm font-medium">
                    <span class="flex items-center gap-2">
                        <i class="fa-regular fa-id-card text-emerald-400 text-lg"></i> {{ $patient->cid ?? '-' }}
                    </span>
                    <span class="flex items-center gap-2 bg-blue-500/20 px-3 py-1 rounded-lg border border-blue-500/30 text-blue-100">
                        <i class="fa-solid fa-hospital-user text-blue-400 text-lg"></i> HN: <span class="font-bold tracking-wider">{{ $patient->hn ?? 'ยังไม่ซิงค์' }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-tent text-emerald-400 text-lg"></i> 
                        {{ $patient->militaryUnit->name ?? $patient->company ?? 'ไม่ระบุกองร้อย' }}
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
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> บันทึกการแก้ไขไม่สำเร็จ กรุณาตรวจสอบ:
            </div>
            <ul class="list-disc list-inside text-sm space-y-1 ml-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('backend.mews.update', $mews->id) }}" method="POST" id="mews-form">
        @csrf
        @method('PUT')
        <input type="hidden" name="total_score" id="hidden_total_score" value="{{ old('total_score', $mews->total_score) }}">
        <input type="hidden" name="risk_level" id="hidden_risk_level" value="{{ old('risk_level', $mews->risk_level) }}">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- 🌟🌟 ฝั่งซ้าย (กินพื้นที่ 2 คอลัมน์ — เรียงลำดับ 1 -> 2 -> 3 ตามสิทธิ์ผู้ใช้งานอัตโนมัติ) 🌟🌟 -->
            <div class="lg:col-span-2" style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                @php
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

                    $emergencyBoxOrder = $isMedicView ? 3 : 2;
                    $clinicalNoteOrder = $isMedicView ? 2 : 3;
                @endphp

                <!-- 1. ฟอร์มแก้ไขสัญญาณชีพ (V/S) — อยู่ลำดับที่ 1 เสมอ -->
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
                            <input type="number" id="sbp" name="sbp" value="{{ old('sbp', $mews->systolic_bp) }}" min="0" placeholder="เช่น 120" inputmode="numeric" pattern="[0-9]*" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- Pulse (HR) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>ชีพจร (Pulse) <span class="text-slate-400 text-xs font-normal ml-1">ครั้ง/นาที</span></span>
                                <button type="button" onclick="showCriteria('hr', 'ชีพจร (HR)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" id="hr" name="hr" value="{{ old('hr', $mews->heart_rate) }}" min="0" placeholder="เช่น 80" inputmode="numeric" pattern="[0-9]*" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- Respiratory Rate (RR) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>อัตราการหายใจ (RR) <span class="text-slate-400 text-xs font-normal ml-1">ครั้ง/นาที</span></span>
                                <button type="button" onclick="showCriteria('rr', 'อัตราการหายใจ (RR)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" id="rr" name="rr" value="{{ old('rr', $mews->respiratory_rate) }}" min="0" placeholder="เช่น 18" inputmode="numeric" pattern="[0-9]*" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- Temp -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>อุณหภูมิ (Temp) <span class="text-slate-400 text-xs font-normal ml-1">°C</span></span>
                                <button type="button" onclick="showCriteria('temp', 'อุณหภูมิร่างกาย (Temp)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" step="0.1" min="0" id="temp" name="temp" value="{{ old('temp', $mews->temperature) }}" inputmode="decimal" placeholder="เช่น 36.5" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- O2 Sat -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>ออกซิเจนในเลือด (O2 Sat) <span class="text-slate-400 text-xs font-normal ml-1">%</span></span>
                                <button type="button" onclick="showCriteria('o2sat', 'ออกซิเจนในเลือด (O2 Sat)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <input type="number" id="o2sat" name="o2sat" value="{{ old('o2sat', $mews->o2sat) }}" min="0" max="100" inputmode="numeric" pattern="[0-9]*" placeholder="เช่น 98" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-lg text-center" required>
                        </div>
                        <!-- AVPU -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between items-center">
                                <span>ระดับความรู้สึกตัว (AVPU)</span>
                                <button type="button" onclick="showCriteria('avpu', 'ระดับการรู้สึกตัว (AVPU)')" class="text-blue-400 hover:text-blue-600 transition-colors" title="ดูเกณฑ์การให้คะแนน"><i class="fa-solid fa-circle-info text-base"></i></button>
                            </label>
                            <select id="avpu" name="avpu" class="mews-input w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors px-4 py-3 text-base text-center" required>
                                <option value="" disabled {{ old('avpu', $mews->consciousness) ? '' : 'selected' }}>-- เลือกการตอบสนอง --</option>
                                <option value="A" {{ old('avpu', $mews->consciousness) == 'A' ? 'selected' : '' }}>Alert (รู้สึกตัวดี)</option>
                                <option value="C" {{ old('avpu', $mews->consciousness) == 'C' ? 'selected' : '' }}>Confused (สับสน)</option>
                                <option value="P" {{ old('avpu', $mews->consciousness) == 'P' ? 'selected' : '' }}>Response to pain (กระตุ้นด้วยความเจ็บ)</option>
                                <option value="V" {{ old('avpu', $mews->consciousness) == 'V' ? 'selected' : '' }}>Response to voice (เรียกถึงรู้สึกตัว)</option>
                                <option value="U" {{ old('avpu', $mews->consciousness) == 'U' ? 'selected' : '' }}>Unresponsive (ไม่ตอบสนอง)</option>
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
                        <div class="mb-5 p-3.5 bg-amber-50/90 border border-amber-200 rounded-2xl text-xs sm:text-sm text-amber-900 flex items-start gap-3">
                            <i class="fa-solid fa-circle-info text-amber-500 text-base mt-0.5 shrink-0"></i>
                            <div class="leading-relaxed">
                                <b>คำแนะนำสำหรับเสนารักษ์:</b> หากพลทหารมาด้วยอาการเจ็บป่วยทั่วไป (เช่น มาทำแผล, ไข้หวัดทั่วไป, ปวดเมื่อย หรือมาตามนัด) <b>ไม่ต้องติ๊กช่องใดๆ ในหมวดนี้</b> (ให้เว้นว่างไว้ ระบบจะส่งเข้าคิวตรวจ OPD ตามปกติ) <span class="text-rose-600 font-bold">ให้ติ๊กเฉพาะกรณีที่ผู้ป่วยมีอาการรุนแรงที่ต้องส่งเข้าห้องฉุกเฉิน (ER) ด่วนเท่านั้นครับ</span>
                            </div>
                        </div>
                    @endif
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @php 
                            $existingSyms = is_string($mews->symptoms) ? json_decode($mews->symptoms, true) : ($mews->symptoms ?? []);
                            $oldConds = old('emergency_conditions', $existingSyms ?? []); 
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
                              class="w-full rounded-2xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition-colors p-4 text-sm text-slate-800 placeholder:text-slate-400 shadow-inner">{{ old('symptoms_note', $mews->symptoms_note ?? '') }}</textarea>
                </div>

            </div>

            <!-- 🌟🌟 ฝั่งขวา: กล่องแสดงผลคะแนน MEWS Real-time 🌟🌟 -->
            <div class="space-y-6">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 sticky top-6 text-center">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-2">คะแนนรวม MEWS Score</h3>
                    
                    <div id="mews-badge" class="w-32 h-32 mx-auto rounded-full bg-slate-100 border-4 border-slate-200 flex items-center justify-center transition-all duration-500 mb-4 shadow-inner">
                        <span id="mews-score-display" class="text-5xl font-black text-slate-400">{{ $mews->total_score }}</span>
                    </div>
                    
                    <p id="mews-status-text" class="text-slate-500 font-medium min-h-[3rem] leading-relaxed mb-4">กำลังคำนวณ...</p>
                    
                    @if($resolvedTemplate)
                        <div class="mb-5 bg-indigo-50 border border-indigo-100 p-3 rounded-xl shadow-sm w-full">
                            <div class="flex items-start justify-center gap-2">
                                <i class="fa-solid fa-calculator text-indigo-500 text-xs mt-1 shrink-0"></i>
                                <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide leading-relaxed break-words text-left">
                                    อ้างอิง: {{ $resolvedTemplate->name }}
                                </span>
                            </div>
                        </div>
                    @endif
                    
                    <div id="emergency-alert-box" class="hidden mt-2 p-4 bg-red-50 text-red-700 rounded-xl text-sm border border-red-200 animate-fade-in-up text-left shadow-sm">
                        <div class="font-bold flex items-center mb-2 border-b border-red-200 pb-2">
                            <i class="fa-solid fa-truck-medical animate-pulse mr-2 text-lg text-red-600"></i> 
                            <span id="emergency-alert-text">พบอาการฉุกเฉินเฉพาะโรค!</span>
                        </div>
                        <ul id="emergency-alert-list" class="list-disc list-inside space-y-1 text-xs font-medium text-red-800 ml-1"></ul>
                    </div>

                    <hr class="my-6 border-slate-100">
                    
                    <div class="flex flex-col gap-3">
                        <button type="submit" class="w-full py-4 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-bold shadow-md transition-colors text-lg flex items-center justify-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> บันทึกการแก้ไข
                        </button>
                        
                        <a href="{{ route('backend.mews.show', $mews->id) }}" class="w-full py-3 bg-white border-2 border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-500 hover:text-slate-700 rounded-xl font-bold shadow-sm transition-colors text-center flex items-center justify-center gap-2">
                            <i class="fa-solid fa-xmark"></i> ยกเลิก
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection

@section('scripts')
    <script>
        const mewsRules = @json($resolvedTemplate->rules ?? []);

        function appendSymptomNote(text) {
            const el = document.getElementById('symptoms_note');
            if (!el) return;
            const current = el.value.trim();
            el.value = current ? (current + ', ' + text) : text;
            el.focus();
        }

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
    </script>
    <script src="{{ asset('js/mews-calculator.js') }}"></script>
@endsection