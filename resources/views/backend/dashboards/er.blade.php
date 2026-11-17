@extends('layouts.backend')

@section('title', 'ER Dashboard - KSVR Hospital')
@section('page_header', 'ห้องฉุกเฉิน (ER Operations)')

@section('content')
<div class="max-w-7xl mx-auto mt-4 pb-12 space-y-8">
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
        $isOPD = $isAdmin || in_array('opd_nurse', $userRoles);
        
        // 3. นับจำนวนสิทธิ์ที่มี เพื่อตัดสินใจว่าจะโชว์แท็บสลับหน้าจอไหม
        $accessCount = ($isExecutive ? 1 : 0) + ($isER ? 1 : 0) + ($isMedic ? 1 : 0) + ($isRegistration ? 1 : 0) + ($isOPD ? 1 : 0); 
        $hasMultipleRoles = $accessCount > 1;
        
        // ค่า Default ของหน้านี้
        $currentView = request('view') ?? 'er'; 
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

    <!-- 🟢 Header ทักทายพยาบาล + ปุ่มลัดประเมินผู้ป่วยด่วน -->
    <div class="bg-gradient-to-r from-emerald-800 to-teal-700 rounded-3xl p-6 md:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg text-white">
        <div>
            <h2 class="text-2xl md:text-3xl font-bold mb-2">เวรปฏิบัติการห้องฉุกเฉิน (ER)</h2>
            <p class="text-emerald-100 text-sm md:text-base"><i class="fa-solid fa-user-nurse mr-1"></i> พยาบาลเวร: {{ auth()->user()->name }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('backend.patients.search') }}" class="px-4 py-3 bg-white text-emerald-800 hover:bg-emerald-50 rounded-2xl font-bold text-sm shadow-md transition-all flex items-center gap-2 hover:-translate-y-0.5">
                <i class="fa-solid fa-stethoscope text-emerald-600"></i> + ประเมินผู้ป่วยด่วน (Triage)
            </a>
            <div class="hidden md:flex w-14 h-14 bg-white/10 rounded-2xl items-center justify-center text-2xl backdrop-blur-sm border border-white/20 shrink-0">
                <i class="fa-solid fa-truck-medical text-white"></i>
            </div>
        </div>
    </div>

    <!-- 🚨 แจ้งเตือนฉุกเฉิน (จะโชว์ก็ต่อเมื่อมีคิวรอรับเข้า ER) -->
    @if($incomingCases->count() > 0)
    <div class="bg-rose-500 rounded-2xl p-4 md:p-5 flex items-center gap-4 shadow-lg animate-fade-in text-white border border-rose-600">
        <div class="w-12 h-12 bg-white text-rose-600 rounded-full flex items-center justify-center text-xl shrink-0 animate-pulse shadow-sm">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div>
            <h4 class="text-lg font-bold">มีผู้ป่วยรอรับเข้า ER จำนวน {{ $incomingCases->count() }} ราย!</h4>
            <p class="text-sm text-rose-100 font-medium">โปรดตรวจสอบผู้ป่วยที่มีคะแนน MEWS สูง หรือมีอาการฉุกเฉินเฉพาะโรคก่อน (Triage Priority)</p>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        
        <!-- 🔴 1. คิวรอรับผู้ป่วยจากภาคสนาม / จุดคัดกรอง (Incoming) -->
        <div class="bg-slate-50/50 rounded-3xl p-6 border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2 border-b border-slate-200 pb-3">
                <i class="fa-solid fa-walkie-talkie text-blue-500"></i> รอรับจากภาคสนาม / จุดคัดกรอง (Incoming)
                <span class="bg-blue-100 text-blue-700 text-xs px-2.5 py-1 rounded-full ml-auto font-bold">{{ $incomingCases->count() }}</span>
            </h3>
            
            <div class="space-y-4">
                @forelse($incomingCases as $case)
                    @php
                        $isRed = $case->risk_level === 'emergency';
                        $isYellow = $case->risk_level === 'observe';
                        $border = $isRed ? 'border-rose-200 bg-rose-50/70' : ($isYellow ? 'border-amber-200 bg-amber-50/70' : 'border-slate-200 bg-white');
                        $badge = $isRed ? 'bg-rose-500 text-white' : ($isYellow ? 'bg-amber-400 text-white' : 'bg-emerald-500 text-white');
                        
                        $syms = is_string($case->symptoms) ? json_decode($case->symptoms, true) : ($case->symptoms ?? []);
                        $unitName = $case->patient->militaryUnit->short_name ?? $case->patient->militaryUnit->name ?? $case->patient->company ?? 'ไม่ระบุสังกัด';
                        $battalionName = $case->patient->militaryUnit->battalion ?? $case->patient->battalion ?? '';
                    @endphp
                    
                    <div class="rounded-2xl p-5 border {{ $border }} shadow-sm transition-transform hover:-translate-y-0.5 relative overflow-hidden group">
                        <!-- แถบสีบอกความรุนแรงด้านซ้าย -->
                        <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $badge }}"></div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pl-3">
                            <div class="flex-1">
                                <!-- ชื่อ + เวลา + สถานะ HN -->
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <a href="{{ route('backend.patients.show', $case->patient->id) }}" class="font-bold text-slate-800 hover:text-indigo-600 text-lg transition-colors">
                                        {{ $case->patient->rank }}{{ $case->patient->first_name }} {{ $case->patient->last_name }}
                                    </a>
                                    @if($case->patient->hn)
                                        <span class="text-[11px] bg-blue-100 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-md font-bold">HN: {{ $case->patient->hn }}</span>
                                    @else
                                        <span class="text-[11px] bg-rose-100 text-rose-700 border border-rose-200 px-2 py-0.5 rounded-md font-bold">รอซิงค์ HN</span>
                                    @endif
                                    <span class="text-[11px] text-slate-500 bg-white/80 border border-slate-200 px-2 py-0.5 rounded-full">
                                        <i class="fa-regular fa-clock"></i> {{ $case->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                <!-- สังกัดหน่วยฝึก & ผลัด -->
                                <div class="text-xs text-slate-600 mb-2.5 flex flex-wrap items-center gap-2">
                                    <span><i class="fa-solid fa-tent mr-1 text-slate-400"></i> {{ $unitName }} {{ $battalionName ? "({$battalionName})" : '' }}</span>
                                    @if($case->patient->intake_batch)
                                        <span class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded font-bold">ผลัด {{ $case->patient->intake_batch }}</span>
                                    @endif
                                </div>

                                <!-- 🌟 แถบสรุปสัญญาณชีพแรกรับ (Vital Signs Quick Bar) -->
                                <div class="flex flex-wrap gap-1.5 text-[11px] font-semibold bg-white/90 p-2 rounded-xl border border-slate-200/80 mb-2">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700">BP: <b>{{ $case->systolic_bp ?? '-' }}</b></span>
                                    <span class="px-2 py-0.5 rounded {{ ($case->heart_rate >= 110 || $case->heart_rate <= 50) ? 'bg-rose-100 text-rose-700 font-bold' : 'bg-slate-100 text-slate-700' }}">HR: <b>{{ $case->heart_rate ?? '-' }}</b></span>
                                    <span class="px-2 py-0.5 rounded {{ ($case->respiratory_rate >= 21 || $case->respiratory_rate <= 8) ? 'bg-rose-100 text-rose-700 font-bold' : 'bg-slate-100 text-slate-700' }}">RR: <b>{{ $case->respiratory_rate ?? '-' }}</b></span>
                                    <span class="px-2 py-0.5 rounded {{ ($case->temperature >= 38.0 || $case->temperature < 35.0) ? 'bg-orange-100 text-orange-800 font-bold' : 'bg-slate-100 text-slate-700' }}">T: <b>{{ $case->temperature ?? '-' }}°C</b></span>
                                    <span class="px-2 py-0.5 rounded {{ ($case->o2sat && $case->o2sat < 95) ? 'bg-rose-100 text-rose-700 font-bold' : 'bg-slate-100 text-slate-700' }}">O2: <b>{{ $case->o2sat ?? '-' }}%</b></span>
                                    <span class="px-2 py-0.5 rounded {{ ($case->consciousness && $case->consciousness !== 'A') ? 'bg-rose-100 text-rose-700 font-bold' : 'bg-slate-100 text-slate-700' }}">AVPU: <b>{{ $case->consciousness ?? '-' }}</b></span>
                                </div>
                                
                                <!-- โชว์อาการที่เสนารักษ์/จุดคัดกรองติ๊กมา (ถ้ามี) -->
                                @if(is_array($syms) && count($syms) > 0)
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        @foreach($syms as $sym)
                                            <span class="text-[11px] bg-rose-600 text-white px-2.5 py-0.5 rounded-md font-bold shadow-sm">
                                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $symptomDict[$sym] ?? $sym }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            
                            <div class="flex sm:flex-col items-center sm:items-end justify-between gap-3 shrink-0">
                                <div class="text-center bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm min-w-[72px]">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">MEWS</div>
                                    <div class="text-2xl font-black {{ $isRed ? 'text-rose-600' : ($isYellow ? 'text-amber-500' : 'text-emerald-500') }}">{{ $case->total_score }}</div>
                                </div>
                                
                                <!-- ปุ่มรับผู้ป่วยเข้า ER -->
                                <a href="{{ route('backend.mews.create', ['patient_id' => $case->patient->id, 'type' => 'er']) }}" 
                                   class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold rounded-xl shadow-sm transition-colors text-center flex items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-hand-holding-medical"></i> รับผู้ป่วยเข้า ER
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 bg-white rounded-2xl border border-dashed border-slate-300">
                        <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">
                            <i class="fa-solid fa-mug-hot"></i>
                        </div>
                        <p class="text-slate-500 font-medium">ขณะนี้ไม่มีผู้ป่วยรอรับเข้า ER</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 🟡 2. ผู้ป่วยที่กำลังติดตามอาการใน ER (Active Cases) -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2 border-b border-slate-100 pb-3">
                <i class="fa-solid fa-bed-pulse text-indigo-500"></i> กำลังติดตามอาการในห้องฉุกเฉิน (Active in ER)
                <span class="bg-indigo-100 text-indigo-700 text-xs px-2.5 py-1 rounded-full ml-auto font-bold">{{ $activeCases->count() }}</span>
            </h3>
            
            <div class="space-y-4">
                @forelse($activeCases as $case)
                    @php
                        // คำนวณจำนวนรอบว่าเคสนี้ประเมินไปแล้วกี่ครั้ง
                        $roundCount = \App\Models\MewsAssessment::where('case_id', $case->case_id)->count();
                        $syms = is_string($case->symptoms) ? json_decode($case->symptoms, true) : ($case->symptoms ?? []);
                        $unitName = $case->patient->militaryUnit->short_name ?? $case->patient->militaryUnit->name ?? $case->patient->company ?? 'ผู้ป่วยทั่วไป / ไม่ระบุหน่วย';

                        // 🌟 คำนวณเวลาสำหรับการเตือนวัดสัญญาณชีพซ้ำ (SLA Timer)
                        $minutesSinceLastUpdate = $case->updated_at->diffInMinutes(now());
                        $isOverdue = ($case->risk_level === 'emergency' && $minutesSinceLastUpdate >= 15) || ($case->risk_level === 'observe' && $minutesSinceLastUpdate >= 30);
                    @endphp

                    <div class="p-4 rounded-2xl border {{ $isOverdue ? 'border-rose-300 bg-rose-50/30' : 'border-slate-100 hover:bg-slate-50' }} transition-colors shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-start gap-3.5 flex-1">
                                
                                <!-- วงกลมแสดงจำนวนรอบ -->
                                <div class="w-12 h-12 rounded-2xl {{ $case->risk_level === 'emergency' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-indigo-50 border-indigo-200 text-indigo-700' }} border-2 flex flex-col items-center justify-center shadow-sm shrink-0 mt-0.5">
                                    <span class="text-[10px] font-bold uppercase leading-none mb-0.5">รอบที่</span>
                                    <span class="font-black text-xl leading-none">{{ $roundCount }}</span>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <!-- ชื่อ + คะแนน MEWS + HN -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('backend.mews.show', $case->id) }}" class="font-bold text-slate-800 text-base hover:text-indigo-600 transition-colors">
                                            {{ $case->patient->rank }}{{ $case->patient->first_name }} {{ $case->patient->last_name }}
                                        </a>
                                        
                                        <span class="px-2 py-0.5 rounded text-[11px] uppercase font-black tracking-wider {{ $case->risk_level === 'emergency' ? 'bg-rose-100 text-rose-600 border border-rose-200' : ($case->risk_level === 'observe' ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200') }}">
                                            MEWS: {{ $case->total_score }}
                                        </span>

                                        @if($case->patient->hn)
                                            <span class="text-[10px] bg-blue-50 text-blue-600 border border-blue-200 px-2 py-0.5 rounded font-bold">HN: {{ $case->patient->hn }}</span>
                                        @else
                                            <a href="{{ route('backend.patients.show', $case->patient->id) }}" class="text-[10px] bg-rose-50 text-rose-600 border border-rose-200 px-2 py-0.5 rounded font-bold hover:bg-rose-100">
                                                ยังไม่มี HN
                                            </a>
                                        @endif
                                    </div>

                                    <!-- สังกัดและเวลาอัปเดตล่าสุด + เตือนวัดซ้ำ -->
                                    <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span><i class="fa-solid fa-tent text-slate-400 mr-1"></i>{{ $unitName }}</span>
                                        <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i>V/S ล่าสุด: <b class="text-slate-700">{{ $case->updated_at->format('H:i') }} น.</b> ({{ $case->updated_at->diffForHumans() }})</span>
                                        @if($isOverdue)
                                            <span class="text-[11px] bg-rose-600 text-white px-2 py-0.5 rounded-full font-bold animate-pulse">
                                                <i class="fa-solid fa-bell mr-1"></i>ถึงเวลาวัด V/S ซ้ำ!
                                            </span>
                                        @endif
                                    </div>

                                    <!-- แถบสัญญาณชีพล่าสุดแบบกระชับ -->
                                    <div class="flex flex-wrap gap-1.5 mt-2 text-[11px] text-slate-600">
                                        <span class="bg-slate-100 px-2 py-0.5 rounded">BP: <b>{{ $case->systolic_bp ?? '-' }}</b></span>
                                        <span class="bg-slate-100 px-2 py-0.5 rounded">HR: <b>{{ $case->heart_rate ?? '-' }}</b></span>
                                        <span class="bg-slate-100 px-2 py-0.5 rounded">T: <b>{{ $case->temperature ?? '-' }}°C</b></span>
                                        <span class="bg-slate-100 px-2 py-0.5 rounded">O2: <b>{{ $case->o2sat ?? '-' }}%</b></span>
                                    </div>

                                    <!-- ป้ายอาการฉุกเฉินเฉพาะโรค -->
                                    @if(is_array($syms) && count($syms) > 0)
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            @foreach($syms as $sym)
                                                <span class="text-[10px] bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded font-bold">
                                                    {{ $symptomDict[$sym] ?? $sym }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- ปุ่มจัดการ (วัด V/S รอบใหม่ / ดูแฟ้มเพื่อจำหน่าย) -->
                            <div class="flex items-center justify-end gap-2 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                                <a href="{{ route('backend.mews.create', ['patient_id' => $case->patient->id, 'type' => 'er']) }}" 
                                   class="px-3 py-2 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white flex items-center gap-1.5 text-xs font-bold transition-colors shadow-sm" title="บันทึก V/S รอบใหม่">
                                    <i class="fa-solid fa-plus"></i> วัด V/S ซ้ำ
                                </a>
                                <a href="{{ route('backend.mews.show', $case->id) }}" 
                                   class="px-3 py-2 rounded-xl bg-slate-800 text-white hover:bg-slate-900 flex items-center gap-1.5 text-xs font-bold transition-colors shadow-sm" title="จัดการเคส / จบการรักษา">
                                    <span>จัดการเคส</span> <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <p class="text-slate-400 text-sm">ไม่มีผู้ป่วยที่กำลังติดตามอาการในห้อง ER ขณะนี้</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- 🗄️ โซน 3: ประวัติการรักษาย้อนหลัง (Completed / Cancelled Cases Today) -->
    <div class="mt-8 bg-slate-50/80 rounded-3xl p-6 md:p-8 border border-slate-200">
        <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2 border-b border-slate-200 pb-3">
            <i class="fa-solid fa-clock-rotate-left text-slate-500"></i> ประวัติการจำหน่ายผู้ป่วย / ยกเลิก (ประจำวัน)
        </h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="px-4 py-3 font-bold">เวลา</th>
                        <th class="px-4 py-3 font-bold">ชื่อ-นามสกุลผู้ป่วย</th>
                        <th class="px-4 py-3 font-bold text-center">MEWS ล่าสุด</th>
                        <th class="px-4 py-3 font-bold">สถานะ/ผลการรักษา</th>
                        <th class="px-4 py-3 font-bold text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($completedCasesToday as $history)
                        <tr class="border-b border-slate-100 hover:bg-white transition-colors">
                            <td class="px-4 py-4 text-slate-500 font-medium">{{ $history->updated_at->format('H:i') }} น.</td>
                            <td class="px-4 py-4 font-bold text-slate-700">
                                {{ $history->patient->rank }}{{ $history->patient->first_name }} {{ $history->patient->last_name }}
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded font-black">{{ $history->total_score }}</span>
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $isReturnedToUnit = !empty($history->returned_to_unit_at);
                                @endphp

                                @if($history->status === 'cancelled')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-bold text-xs border border-slate-200">
                                        <i class="fa-solid fa-ban"></i> ถูกยกเลิก
                                    </span>
                                @elseif($history->discharge_type === 'admitted')
                                    @if($isReturnedToUnit)
                                        <span class="inline-flex flex-wrap items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs border border-emerald-200">
                                            <i class="fa-solid fa-house-chimney-check text-emerald-600"></i> 
                                            Admit {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} • กลับหน่วยฝึกแล้ว
                                        </span>
                                    @else
                                        <span class="inline-flex flex-wrap items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 font-bold text-xs border border-amber-200">
                                            <i class="fa-solid fa-bed-pulse text-amber-600"></i> 
                                            Admit {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} (กำลังนอน รพ.)
                                        </span>
                                    @endif
                                @elseif($history->discharge_type === 'refer')
                                    @if($isReturnedToUnit)
                                        <span class="inline-flex flex-wrap items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs border border-emerald-200">
                                            <i class="fa-solid fa-house-chimney-check text-emerald-600"></i> 
                                            ส่งต่อ Refer {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} • กลับหน่วยฝึกแล้ว
                                        </span>
                                    @else
                                        <span class="inline-flex flex-wrap items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-bold text-xs border border-blue-200">
                                            <i class="fa-solid fa-ambulance text-blue-600"></i> 
                                            ส่งต่อ (Refer) {{ $history->discharge_details ? "({$history->discharge_details})" : '' }}
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs border border-emerald-200">
                                        <i class="fa-solid fa-house-chimney-medical"></i> จำหน่ายกลับหน่วย
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('backend.mews.show', $history->id) }}" class="text-indigo-500 hover:text-indigo-700 font-bold text-xs transition-colors bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100 shadow-sm">
                                    <i class="fa-solid fa-folder-open mr-1"></i> ดูแฟ้ม
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400">ยังไม่มีประวัติการจำหน่ายผู้ป่วยในวันนี้</td>
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
    // ฟังก์ชันแอบรีเฟรชหน้าจอแบบไม่กระตุก (DOM Replacement)
    function refreshERDashboard() {
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

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.Echo !== 'undefined') {
            window.Echo.private('er-alerts')
                .listen('.mews.emergency', (e) => {
                    refreshERDashboard();
                });

            window.Echo.channel('mews-channel')
                .listen('.mews.updated', (e) => {
                    refreshERDashboard();
                });
        } else {
            setInterval(refreshERDashboard, 60000);
        }
    });
</script>
@endsection