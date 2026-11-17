@extends('layouts.backend')

@section('title', 'การนัดพบแพทย์ทหารใหม่ - KSVR Hospital')
@section('page_header', 'ระบบการนัดพบแพทย์ของทหารกองประจำการ')

@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-12">

    @php
        $todayStr = \Carbon\Carbon::today()->format('Y-m-d');
        $tomorrowStr = \Carbon\Carbon::tomorrow()->format('Y-m-d');
        $safeAlertList = $alertAppointments ?? collect();
    @endphp

    <!-- 🔔 1. แบนเนอร์แจ้งเตือนด่วน (กรณีมีทหารนัดพบแพทย์ในวันพรุ่งนี้ หรือ วันนี้) -->
    @if($safeAlertList->count() > 0)
    <div class="bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-3xl p-5 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4 animate-fade-in-down">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shrink-0">
                <i class="fa-solid fa-bell animate-bounce"></i>
            </div>
            <div>
                <h3 class="text-base md:text-lg font-black tracking-wide">
                    แจ้งเตือนวันนัดพบแพทย์: มีพลทหารต้องไปพบแพทย์ (วันนี้ / พรุ่งนี้) จำนวน {{ $safeAlertList->count() }} รายการ!
                </h3>
                <p class="text-xs md:text-sm text-amber-100 mt-0.5">
                    กรุณาตรวจสอบการเตรียมตัวผู้ป่วย (เช่น งดน้ำ-อาหาร, เอกสารส่งตัว) และการเตรียมรถรับ-ส่งของหน่วยฝึก
                </p>
            </div>
        </div>
        <button type="button" onclick="showAdvanceAppointmentAlertModal(true)" class="px-4 py-2.5 bg-white text-orange-700 hover:bg-amber-50 rounded-xl text-xs sm:text-sm font-black shadow-sm shrink-0 transition-transform hover:-translate-y-0.5">
            <i class="fa-solid fa-list-check mr-1"></i> เปิดดูรายชื่อที่ต้องเตรียมส่งตัว
        </button>
    </div>
    @endif

    <!-- 📊 2. การ์ดสรุป KPI 4 มิติ (คลิกเพื่อกรองตารางได้ทันที) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- นัดพรุ่งนี้ (ล่วงหน้า 1 วัน) -->
        <a href="{{ route('backend.recruit_appointments.index', ['intake_batch' => $selectedBatch, 'unit_id' => $selectedUnit, 'date_scope' => 'tomorrow']) }}"
           class="bg-white p-5 rounded-3xl shadow-sm border-2 {{ $selectedScope === 'tomorrow' ? 'border-amber-500 ring-2 ring-amber-200' : 'border-slate-100 hover:border-amber-300' }} flex items-center justify-between transition-all">
            <div>
                <span class="text-xs font-bold text-amber-600 uppercase">⏰ นัดพรุ่งนี้ (เตือนล่วงหน้า 1 วัน)</span>
                <h4 class="text-3xl font-black text-slate-800 mt-1">{{ number_format($summary['tomorrow_count']) }} <span class="text-sm font-medium text-slate-400">ราย</span></h4>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl"><i class="fa-solid fa-calendar-day"></i></div>
        </a>

        <!-- นัดวันนี้ -->
        <a href="{{ route('backend.recruit_appointments.index', ['intake_batch' => $selectedBatch, 'unit_id' => $selectedUnit, 'date_scope' => 'today']) }}"
           class="bg-white p-5 rounded-3xl shadow-sm border-2 {{ $selectedScope === 'today' ? 'border-rose-500 ring-2 ring-rose-200' : 'border-slate-100 hover:border-rose-300' }} flex items-center justify-between transition-all">
            <div>
                <span class="text-xs font-bold text-rose-600 uppercase">🚨 นัดพบแพทย์วันนี้</span>
                <h4 class="text-3xl font-black text-rose-600 mt-1">{{ number_format($summary['today_count']) }} <span class="text-sm font-medium text-slate-400">ราย</span></h4>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl"><i class="fa-solid fa-user-doctor"></i></div>
        </a>

        <!-- นัด รพ.ค่ายกฤษณ์สีวะรา -->
        <a href="{{ route('backend.recruit_appointments.index', ['intake_batch' => $selectedBatch, 'unit_id' => $selectedUnit, 'hospital_type' => 'camp_hospital', 'date_scope' => 'upcoming']) }}"
           class="bg-white p-5 rounded-3xl shadow-sm border-2 {{ $selectedType === 'camp_hospital' ? 'border-emerald-500 ring-2 ring-emerald-200' : 'border-slate-100 hover:border-emerald-300' }} flex items-center justify-between transition-all">
            <div>
                <span class="text-xs font-bold text-emerald-600 uppercase">🏥 นัด รพ.ค่ายกฤษณ์สีวะรา</span>
                <h4 class="text-3xl font-black text-emerald-600 mt-1">{{ number_format($summary['camp_count']) }} <span class="text-sm font-medium text-slate-400">ราย</span></h4>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl"><i class="fa-solid fa-hospital"></i></div>
        </a>

        <!-- นัด รพ.สาธารณสุข -->
        <a href="{{ route('backend.recruit_appointments.index', ['intake_batch' => $selectedBatch, 'unit_id' => $selectedUnit, 'hospital_type' => 'public_hospital', 'date_scope' => 'upcoming']) }}"
           class="bg-white p-5 rounded-3xl shadow-sm border-2 {{ $selectedType === 'public_hospital' ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-slate-100 hover:border-indigo-300' }} flex items-center justify-between transition-all">
            <div>
                <span class="text-xs font-bold text-indigo-600 uppercase">🚑 นัด รพ.สาธารณสุข (ส่งต่อ)</span>
                <h4 class="text-3xl font-black text-indigo-600 mt-1">{{ number_format($summary['public_count']) }} <span class="text-sm font-medium text-slate-400">ราย</span></h4>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl"><i class="fa-solid fa-truck-medical"></i></div>
        </a>
    </div>

    <!-- 🛠️ 3. แถบเครื่องมือตัวกรอง + เลือกจำนวน 10/20 รายชื่อ + ปุ่มดึงข้อมูลจาก HosXP -->
    <div class="bg-white p-4 md:p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-4">
        <form method="GET" action="{{ route('backend.recruit_appointments.index') }}" class="flex flex-wrap items-center gap-2.5">
            @if(isset($focusedPatient) && $focusedPatient)
                <input type="hidden" name="patient_id" value="{{ $focusedPatient->id }}">
            @endif

            <!-- ตัวกรองผลัด -->
            <select name="intake_batch" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-emerald-500 cursor-pointer transition-colors">
                <option value="all" {{ $selectedBatch == 'all' ? 'selected' : '' }}>ทุกผลัดทหาร</option>
                @foreach($batches as $b)
                    <option value="{{ $b }}" {{ $selectedBatch == $b ? 'selected' : '' }}>ผลัดที่ {{ $b }}</option>
                @endforeach
            </select>

            <!-- ตัวกรองหน่วยฝึก -->
            <select name="unit_id" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-emerald-500 cursor-pointer transition-colors">
                <option value="">ทุกหน่วยฝึก</option>
                @foreach($units as $u)
                    <option value="{{ $u->id }}" {{ $selectedUnit == $u->id ? 'selected' : '' }}>{{ $u->short_name ?? $u->name }}</option>
                @endforeach
            </select>

            <!-- ตัวกรองประเภทโรงพยาบาล -->
            <select name="hospital_type" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-emerald-500 cursor-pointer transition-colors">
                <option value="">ทุกสถานที่นัด (รพ.ค่ายฯ + รพ.สธ.)</option>
                <option value="camp_hospital" {{ $selectedType === 'camp_hospital' ? 'selected' : '' }}>🏥 นัดพบแพทย์ รพ.ค่ายฯ</option>
                <option value="public_hospital" {{ $selectedType === 'public_hospital' ? 'selected' : '' }}>🚑 นัดพบแพทย์ รพ.สาธารณสุข</option>
            </select>
            
            <!-- ตัวกรองช่วงเวลานัด -->
            <select name="date_scope" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-emerald-500 cursor-pointer transition-colors">
                <option value="upcoming" {{ $selectedScope === 'upcoming' ? 'selected' : '' }}>📅 นัดตั้งแต่วันนี้เป็นต้นไป</option>
                <option value="tomorrow" {{ $selectedScope === 'tomorrow' ? 'selected' : '' }}>⏰ เฉพาะนัดพรุ่งนี้ (ล่วงหน้า 1 วัน)</option>
                <option value="today" {{ $selectedScope === 'today' ? 'selected' : '' }}>🚨 เฉพาะนัดวันนี้</option>
                <option value="past" {{ $selectedScope === 'past' ? 'selected' : '' }}>🕓 ประวัตินัดย้อนหลัง</option>
                <option value="all" {{ $selectedScope === 'all' ? 'selected' : '' }}>📋 ทั้งหมด</option>
            </select>

            <!-- 🌟 ตัวเลือกจำนวนรายชื่อต่อหน้า (10 / 20 / 50 / 100 รายชื่อ) -->
            <div class="flex items-center bg-slate-50 border border-slate-200 rounded-2xl px-3 py-1.5">
                <span class="text-xs font-bold text-slate-400 mr-2"><i class="fa-solid fa-list-ol mr-1"></i>แสดง:</span>
                <select name="per_page" onchange="this.form.submit()" class="bg-transparent text-xs sm:text-sm font-black text-indigo-600 outline-none cursor-pointer pr-1">
                    @foreach([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" {{ ($perPage ?? 10) == $size ? 'selected' : '' }}>{{ $size }} รายชื่อ</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <!-- 🔄 ปุ่มซิงค์ข้อมูลวันนัดจากตาราง oapp ของ HosXP -->
            <button type="button" id="btn-sync-oapp" onclick="syncAppointmentsFromHosxp()"
                    class="flex-1 sm:flex-none justify-center px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-2xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 shadow-sm whitespace-nowrap">
                <i class="fa-solid fa-rotate" id="icon-sync-oapp"></i> ดึงวันนัดจาก HosXP (อัตโนมัติ)
            </button>

            <!-- ➕ ปุ่มลงข้อมูลนัดพบแพทย์ -->
            <button type="button" onclick="openAddAppointmentModal()"
                    class="flex-1 sm:flex-none justify-center px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 shadow-sm whitespace-nowrap">
                <i class="fa-solid fa-calendar-plus"></i> + ลงวันนัดพบแพทย์
            </button>
        </div>
    </div>

    <!-- 📋 4. ตารางรายการนัดพบแพทย์ของทหารใหม่ (จัดกลุ่มแยกตามผลัดและหน่วยฝึก) -->
    <div>
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2 mb-3 px-1">
            <h3 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-indigo-500"></i> ตารางคิวนัดพบแพทย์แยกตามหน่วยฝึก
                <span class="text-xs font-bold bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full">
                    พบทั้งหมด {{ number_format($appointments->total()) }} รายการ
                </span>
            </h3>
            <span class="text-xs font-semibold text-slate-400">
                แสดงหน้าละ {{ $perPage ?? 10 }} รายชื่อ (หน้าที่ {{ $appointments->currentPage() }} / {{ max(1, $appointments->lastPage()) }})
            </span>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-slate-600">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50/80 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 font-bold">วัน-เวลานัด</th>
                            <th class="px-6 py-4 font-bold">ข้อมูลพลทหาร</th>
                            <th class="px-6 py-4 font-bold">สถานที่นัด / คลินิก</th>
                            <th class="px-6 py-4 font-bold">สาเหตุการนัด / การเตรียมตัว</th>
                            <th class="px-6 py-4 font-bold text-center">สถานะ</th>
                            <th class="px-6 py-4 font-bold text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $currentBatchGroup = null;
                            $currentUnitGroup  = null;
                        @endphp

                        @forelse($appointments as $app)
                            @php
                                $pt = $app->patient;
                                $rowBatch = $app->intake_batch ?? $pt->intake_batch ?? 'ไม่ระบุ';
                                $rowUnitName = $app->militaryUnit->name ?? $pt->militaryUnit->name ?? $pt->company ?? 'ไม่ระบุหน่วยฝึก';

                                $appDateStr = $app->appointment_date->format('Y-m-d');
                                $isTomorrow = ($appDateStr === $tomorrowStr && $app->status === 'scheduled');
                                $isToday    = ($appDateStr === $todayStr && $app->status === 'scheduled');
                                $isCamp     = ($app->hospital_type === 'camp_hospital');
                            @endphp

                            <!-- 🎖️ Grouping ชั้นที่ 1: แยกตามผลัดทหาร -->
                            @if($currentBatchGroup !== $rowBatch)
                                @php
                                    $currentBatchGroup = $rowBatch;
                                    $currentUnitGroup  = null;
                                @endphp
                                <tr class="bg-slate-800">
                                    <td colspan="6" class="px-6 py-2.5">
                                        <div class="flex items-center gap-2 text-white font-bold text-sm">
                                            <i class="fa-solid fa-calendar-days text-emerald-400"></i> ทหารกองประจำการ ผลัดที่ {{ $currentBatchGroup }}
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            <!-- 🏕️ Grouping ชั้นที่ 2: แยกตามหน่วยฝึกทหาร -->
                            @if($currentUnitGroup !== $rowUnitName)
                                @php $currentUnitGroup = $rowUnitName; @endphp
                                <tr class="bg-indigo-50/60">
                                    <td colspan="6" class="px-6 py-2.5 border-y border-indigo-100">
                                        <div class="flex items-center gap-2 text-indigo-700 font-bold text-xs uppercase tracking-wide">
                                            <i class="fa-solid fa-tent text-indigo-500"></i> {{ $currentUnitGroup }}
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            <!-- แถวข้อมูลการนัดพบแพทย์ -->
                            <tr class="hover:bg-slate-50/80 transition-colors {{ $isToday ? 'bg-rose-50/30' : ($isTomorrow ? 'bg-amber-50/30' : '') }}">
                                <!-- วัน-เวลานัด -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-black text-slate-800 text-base">
                                        {{ $app->appointment_date->locale('th')->addYears(543)->translatedFormat('j M Y') }}
                                    </div>
                                    <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-1.5">
                                        <i class="fa-regular fa-clock text-indigo-500"></i> เวลา {{ $app->appointment_time ?: '08:30' }} น.
                                    </div>
                                    @if($isToday)
                                        <span class="mt-1 inline-block px-2 py-0.5 bg-rose-600 text-white rounded text-[10px] font-black animate-pulse">🚨 นัดวันนี้!</span>
                                    @elseif($isTomorrow)
                                        <span class="mt-1 inline-block px-2 py-0.5 bg-amber-500 text-white rounded text-[10px] font-black">⏰ พรุ่งนี้ (อีก 1 วัน)</span>
                                    @endif
                                </td>

                                <!-- ข้อมูลพลทหาร -->
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900 text-base">
                                        {{ $pt->rank ?? '' }}{{ $pt->first_name ?? '' }} {{ $pt->last_name ?? '' }}
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                                        <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded font-bold">HN: {{ $pt->hn ?: '-' }}</span>
                                        <span class="font-mono text-slate-400"><i class="fa-regular fa-id-card mr-1"></i>{{ $pt->cid ?? '-' }}</span>
                                    </div>
                                </td>

                                <!-- สถานที่นัด / คลินิก -->
                                <td class="px-6 py-4">
                                    @if($isCamp)
                                        <span class="px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold inline-flex items-center gap-1.5">
                                            <i class="fa-solid fa-hospital"></i> {{ $app->hospital_name ?: 'รพ.ค่ายกฤษณ์สีวะรา' }}
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-bold inline-flex items-center gap-1.5">
                                            <i class="fa-solid fa-truck-medical"></i> {{ $app->hospital_name ?: 'รพ.สาธารณสุข' }}
                                        </span>
                                    @endif
                                    <div class="text-xs font-semibold text-slate-700 mt-1.5">
                                        แผนก/คลินิก: <span class="text-indigo-700">{{ $app->clinic_name ?: 'ไม่ระบุ' }}</span>
                                    </div>
                                    @if($app->doctor_name)
                                        <div class="text-[11px] text-slate-400">แพทย์: {{ $app->doctor_name }}</div>
                                    @endif
                                </td>

                                <!-- สาเหตุการนัด -->
                                <td class="px-6 py-4 max-w-xs">
                                    <div class="font-bold text-slate-800 text-xs sm:text-sm">{{ $app->app_cause ?: 'นัดตรวจติดตามอาการ' }}</div>
                                    @if($app->note)
                                        <div class="text-xs text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg mt-1 font-medium">
                                            <i class="fa-solid fa-circle-info text-amber-500 mr-1"></i>{{ $app->note }}
                                        </div>
                                    @endif
                                    @if($app->hosxp_oapp_id)
                                        <span class="text-[10px] text-slate-400 font-mono mt-1 block"><i class="fa-solid fa-link mr-1"></i>ซิงค์จาก HosXP (#{{ $app->hosxp_oapp_id }})</span>
                                    @endif
                                </td>

                                <!-- สถานะ -->
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    @if($app->status === 'completed')
                                        <span class="px-3 py-1 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200"><i class="fa-solid fa-check mr-1"></i>ไปพบแพทย์แล้ว</span>
                                    @elseif($app->status === 'postponed')
                                        <span class="px-3 py-1 rounded-xl bg-amber-100 text-amber-800 text-xs font-bold border border-amber-200"><i class="fa-solid fa-clock-rotate-left mr-1"></i>เลื่อนนัด</span>
                                    @elseif($app->status === 'cancelled')
                                        <span class="px-3 py-1 rounded-xl bg-slate-100 text-slate-500 text-xs font-bold"><i class="fa-solid fa-ban mr-1"></i>ยกเลิกนัด</span>
                                    @else
                                        <span class="px-3 py-1 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold"><i class="fa-regular fa-calendar-check mr-1"></i>รอพบแพทย์</span>
                                    @endif
                                </td>

                                <!-- ปุ่มจัดการ -->
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        @if($app->status === 'scheduled')
                                            <form action="{{ route('backend.recruit_appointments.update_status', $app->id) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors" title="บันทึกว่าไปพบแพทย์ตามนัดแล้ว">
                                                    <i class="fa-solid fa-check mr-1"></i> ไปตามนัดแล้ว
                                                </button>
                                            </form>
                                        @endif

                                        <a href="{{ route('backend.patients.show', $pt->id) }}" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-indigo-600 text-slate-600 hover:text-white flex items-center justify-center transition-colors" title="ดูแฟ้มประวัติ">
                                            <i class="fa-solid fa-address-card text-xs"></i>
                                        </a>

                                        <form action="{{ route('backend.recruit_appointments.destroy', $app->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบรายการนัดนี้?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white flex items-center justify-center transition-colors" title="ลบรายการ">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center text-slate-400">
                                    <i class="fa-regular fa-calendar-xmark text-4xl mb-3 block opacity-40"></i>
                                    <p class="font-bold text-slate-600 text-base">ไม่พบรายการนัดพบแพทย์ในหมวดที่เลือก</p>
                                    <p class="text-xs mt-1">คลิกปุ่ม "ดึงวันนัดจาก HosXP" หรือปุ่ม "+ ลงวันนัดพบแพทย์" ด้านบนเพื่อเพิ่มข้อมูลได้เลยครับ</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- แถบเปลี่ยนหน้า (Pagination) พร้อมตัวเลือกสลับ 10 / 20 รายชื่อด้านล่างตาราง -->
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-xs text-slate-500 font-semibold">
                    <span>แสดงหน้าละ:</span>
                    @foreach([10, 20, 50] as $pSize)
                        <a href="{{ request()->fullUrlWithQuery(['per_page' => $pSize, 'page' => 1]) }}"
                           class="px-2.5 py-1 rounded-lg font-bold transition-colors {{ ($perPage ?? 10) == $pSize ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-indigo-50' }}">
                            {{ $pSize }}
                        </a>
                    @endforeach
                    <span>รายชื่อ</span>
                </div>

                @if($appointments->hasPages())
                    <div class="w-full sm:w-auto">
                        {{ $appointments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- ➕ Modal ลงวันนัดพบแพทย์ใหม่ (พร้อมระบบตรวจสอบและแจ้งเตือนรายช่อง 100%) -->
<!-- ========================================================================= -->
<div id="addAppointmentModal" class="{{ $errors->any() ? '' : 'hidden' }} fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 my-8">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
            <h3 class="text-lg font-black text-slate-800"><i class="fa-solid fa-calendar-plus text-emerald-500 mr-2"></i>ลงรายการนัดพบแพทย์ทหารใหม่</h3>
            <button type="button" onclick="closeAddAppointmentModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- ใช้ novalidate เพื่อให้ระบบแจ้งเตือนของเราตรวจเช็คและตีกรอบสีแดงได้ครบทุกช่อง -->
        <form id="appointmentForm" action="{{ route('backend.recruit_appointments.store') }}" method="POST" novalidate onsubmit="return validateAppointmentForm(event)" class="space-y-4">
            @csrf

            <!-- 🔍 1. ช่องค้นหาพลทหารแบบพิมพ์ค้นหาสด -->
            <div class="relative">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ค้นหาและเลือกพลทหาร <span class="text-rose-500">*</span></label>
                <input type="hidden" name="patient_id" id="app_patient_id" value="{{ old('patient_id') }}">

                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                    </span>
                    <input type="text" id="app_patient_search" autocomplete="off"
                           onfocus="document.getElementById('app_patient_dropdown').classList.remove('hidden')"
                           oninput="filterAppointmentPatients(this.value); clearFieldError('app_patient_search', 'err_patient_id');"
                           placeholder="พิมพ์ชื่อ, นามสกุล, เลข ปชช., HN แล้วคลิกเลือก..."
                           class="w-full pl-10 pr-9 py-2.5 rounded-xl border-2 {{ $errors->has('patient_id') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm font-medium text-slate-800 outline-none transition-all">
                </div>

                <p id="err_patient_id" class="{{ $errors->has('patient_id') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1.5 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first('patient_id') ?: 'กรุณาพิมพ์ค้นหาแล้วคลิกเลือกรายชื่อพลทหารจากรายการครับ' }}</span>
                </p>

                <div id="app_patient_dropdown" class="hidden absolute left-0 right-0 mt-1 max-h-52 overflow-y-auto bg-white rounded-2xl shadow-xl border-2 border-slate-200 z-50 divide-y divide-slate-100">
                    @foreach($batchPatients as $pt)
                        @php
                            $uName = $pt->militaryUnit->short_name ?? 'ไม่ระบุหน่วย';
                            $fName = ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');
                            $sKey  = strtolower($fName . ' ' . ($pt->cid ?? '') . ' ' . ($pt->hn ?? '') . ' ' . $uName);
                        @endphp
                        <div onclick="selectAppointmentPatient('{{ $pt->id }}', '{{ addslashes($fName) }} ({{ addslashes($uName) }} | HN: {{ $pt->hn ?: 'ไม่มี' }})')"
                             data-search="{{ $sKey }}"
                             class="app-pt-option px-4 py-2.5 hover:bg-emerald-50 cursor-pointer flex justify-between items-center">
                            <div>
                                <div class="font-bold text-slate-800 text-sm">{{ $fName }}</div>
                                <div class="text-[11px] text-slate-500">หน่วย: <b class="text-indigo-600">{{ $uName }}</b> | ผลัด {{ $pt->intake_batch }}</div>
                            </div>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">HN: {{ $pt->hn ?: '-' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 🏥 2. เลือกประเภทโรงพยาบาล (รพ.ค่ายฯ vs รพ.สาธารณสุข) -->
            @php
                $oldHospType = old('hospital_type', 'camp_hospital');
            @endphp
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ประเภทสถานพยาบาลที่นัด <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/60 transition-all">
                        <input type="radio" name="hospital_type" value="camp_hospital" {{ $oldHospType === 'camp_hospital' ? 'checked' : '' }} onchange="toggleHospitalNameInput()" class="w-4 h-4 text-emerald-600">
                        <div>
                            <div class="text-xs font-black text-slate-800">🏥 รพ.ค่ายกฤษณ์สีวะรา</div>
                            <div class="text-[10px] text-slate-500">ตรวจภายใน รพ.ค่ายฯ</div>
                        </div>
                    </label>

                    <label class="flex items-center gap-2.5 p-3 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/60 transition-all">
                        <input type="radio" name="hospital_type" value="public_hospital" {{ $oldHospType === 'public_hospital' ? 'checked' : '' }} onchange="toggleHospitalNameInput()" class="w-4 h-4 text-indigo-600">
                        <div>
                            <div class="text-xs font-black text-slate-800">🚑 รพ.สาธารณสุข</div>
                            <div class="text-[10px] text-slate-500">ส่งต่อ รพ.ศูนย์ / รพ.ภายนอก</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ช่องระบุชื่อ รพ.สาธารณสุข (บังคับกรอกเมื่อเลือก รพ.สาธารณสุข) -->
            <div id="wrapper_hospital_name" class="{{ $oldHospType === 'public_hospital' ? '' : 'hidden' }}">
                <label class="block text-xs font-bold text-slate-700 mb-1">ระบุชื่อโรงพยาบาลสาธารณสุขปลายทาง <span class="text-rose-500">*</span></label>
                <input type="text" name="hospital_name" id="app_hospital_name" 
                       value="{{ old('hospital_name', $oldHospType === 'public_hospital' ? '' : 'รพ.ค่ายกฤษณ์สีวะรา') }}" 
                       oninput="clearFieldError('app_hospital_name', 'err_hospital_name')"
                       placeholder="เช่น รพ.ศูนย์สกลนคร, รพ.ศรีนครินทร์..."
                       class="w-full rounded-xl border-2 {{ $errors->has('hospital_name') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-indigo-500 text-sm p-2.5 font-semibold text-slate-800 outline-none transition-all">
                <p id="err_hospital_name" class="{{ $errors->has('hospital_name') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first('hospital_name') ?: 'กรุณาระบุชื่อโรงพยาบาลสาธารณสุขปลายทางครับ' }}</span>
                </p>
            </div>

            <!-- 📅 3. วันและเวลานัด -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">วันที่นัดพบแพทย์ <span class="text-rose-500">*</span></label>
                    <input type="date" name="appointment_date" id="app_date_input" value="{{ old('appointment_date', $tomorrowStr) }}" required
                           onchange="clearFieldError('app_date_input', 'err_appointment_date')"
                           class="w-full rounded-xl border-2 {{ $errors->has('appointment_date') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-bold text-slate-800 outline-none transition-all">
                    <p id="err_appointment_date" class="{{ $errors->has('appointment_date') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ $errors->first('appointment_date') ?: 'กรุณาระบุวันที่นัดพบแพทย์' }}</span>
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">เวลานัด <span class="text-rose-500">*</span></label>
                    <input type="time" name="appointment_time" id="app_time_input" value="{{ old('appointment_time', '08:30') }}" required
                           onchange="clearFieldError('app_time_input', 'err_appointment_time')"
                           class="w-full rounded-xl border-2 {{ $errors->has('appointment_time') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-bold text-slate-800 outline-none transition-all">
                    <p id="err_appointment_time" class="{{ $errors->has('appointment_time') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ $errors->first('appointment_time') ?: 'กรุณาระบุเวลานัด' }}</span>
                    </p>
                </div>
            </div>

            <!-- 🩺 4. คลินิก/แผนกที่นัด (บังคับ *) และ แพทย์ผู้นัด -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">คลินิก / แผนกที่นัด <span class="text-rose-500">*</span></label>
                    <input type="text" name="clinic_name" id="app_clinic_input" value="{{ old('clinic_name') }}" required
                           oninput="clearFieldError('app_clinic_input', 'err_clinic_name')"
                           placeholder="เช่น อายุรกรรม, ศัลยกรรม, ทันตกรรม..."
                           class="w-full rounded-xl border-2 {{ $errors->has('clinic_name') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-semibold text-slate-800 outline-none transition-all">
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        <button type="button" onclick="setQuickAppointmentField('app_clinic_input', 'err_clinic_name', 'ตรวจโรคทั่วไป (OPD)')" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 rounded text-[10px] font-bold border border-slate-200">+ OPD ทั่วไป</button>
                        <button type="button" onclick="setQuickAppointmentField('app_clinic_input', 'err_clinic_name', 'คลินิกอายุรกรรม')" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 rounded text-[10px] font-bold border border-slate-200">+ อายุรกรรม</button>
                        <button type="button" onclick="setQuickAppointmentField('app_clinic_input', 'err_clinic_name', 'ห้องฉุกเฉิน (ER)')" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 rounded text-[10px] font-bold border border-slate-200">+ ER</button>
                    </div>
                    <p id="err_clinic_name" class="{{ $errors->has('clinic_name') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ $errors->first('clinic_name') ?: 'กรุณาระบุคลินิกหรือแผนกที่นัดครับ' }}</span>
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">แพทย์ผู้นัด (ถ้าทราบ)</label>
                    <input type="text" name="doctor_name" value="{{ old('doctor_name') }}" placeholder="ระบุชื่อแพทย์ (เว้นว่างได้)..."
                           class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-emerald-500 text-sm p-2.5 text-slate-800 outline-none transition-all">
                </div>
            </div>

            <!-- 📋 5. สาเหตุการนัด / อาการที่ติดตาม (บังคับ *) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">สาเหตุการนัด / อาการที่ติดตาม <span class="text-rose-500">*</span></label>
                <input type="text" name="app_cause" id="app_cause_input" value="{{ old('app_cause') }}" required
                       oninput="clearFieldError('app_cause_input', 'err_app_cause')"
                       placeholder="เช่น ติดตามผลเลือด, ล้างแผลตัดไหม, พบแพทย์เฉพาะทาง..."
                       class="w-full rounded-xl border-2 {{ $errors->has('app_cause') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-semibold text-slate-800 outline-none transition-all">
                <div class="flex flex-wrap gap-1 mt-1.5">
                    <button type="button" onclick="setQuickAppointmentField('app_cause_input', 'err_app_cause', 'นัดตรวจติดตามอาการต่อเนื่อง')" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 rounded text-[10px] font-bold border border-slate-200">+ ติดตามอาการ</button>
                    <button type="button" onclick="setQuickAppointmentField('app_cause_input', 'err_app_cause', 'เจาะเลือด / ฟังผลตรวจทางห้องปฏิบัติการ')" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 rounded text-[10px] font-bold border border-slate-200">+ เจาะเลือด/ฟังผลแล็บ</button>
                    <button type="button" onclick="setQuickAppointmentField('app_cause_input', 'err_app_cause', 'นัดทำแผล / ตัดไหม')" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 rounded text-[10px] font-bold border border-slate-200">+ ทำแผล/ตัดไหม</button>
                </div>
                <p id="err_app_cause" class="{{ $errors->has('app_cause') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first('app_cause') ?: 'กรุณาระบุสาเหตุการนัดหรืออาการที่ติดตามครับ' }}</span>
                </p>
            </div>

            <!-- 📝 6. คำแนะนำการเตรียมตัว (หมายเหตุเพิ่มเติม) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">คำแนะนำการเตรียมตัวก่อนไปพบแพทย์ (หมายเหตุ)</label>
                <input type="text" name="note" value="{{ old('note') }}" placeholder="เช่น งดน้ำงดอาหารหลัง 24.00 น., เตรียมใบส่งตัว (เว้นว่างได้)..."
                       class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-emerald-500 text-sm p-2.5 text-slate-800 outline-none transition-all">
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeAddAppointmentModal()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-bold transition-colors">ยกเลิก</button>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-md transition-colors">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> บันทึกวันนัด
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openAddAppointmentModalForFocusedSoldier(id, label) {
        openAddAppointmentModal();
        selectAppointmentPatient(id, label);
    }
    
    const upcomingAlertsData = @json($safeAlertList);
    const hasServerValidationErrors = @json($errors->any());

    // =========================================================================
    // ⏰ ฟังก์ชันแสดงป๊อปอัปแจ้งเตือนล่วงหน้า 1 วัน (และนัดวันนี้) อัตโนมัติ
    // =========================================================================
    function showAdvanceAppointmentAlertModal(forceOpen = false) {
        if (!upcomingAlertsData || upcomingAlertsData.length === 0) return;
        // หากเปิดหน้ามาเพราะกรอกฟอร์มไม่ครบ ให้แสดงฟอร์มที่ติด Error ก่อน ไม่ต้องเด้งป๊อปอัปทับ
        if (!forceOpen && hasServerValidationErrors) return;

        const alertStorageKey = 'ksvr_app_alert_shown_{{ date("Y-m-d") }}';
        if (!forceOpen && sessionStorage.getItem(alertStorageKey)) {
            return;
        }

        let listHtml = `<div class="max-h-[55vh] overflow-y-auto text-left space-y-2.5 mt-3 pr-1">`;
        upcomingAlertsData.forEach((item, idx) => {
            const badgeClass = item.is_today 
                ? 'bg-rose-600 text-white animate-pulse' 
                : 'bg-amber-500 text-white';
            const hospClass = item.hospital_type === 'public_hospital'
                ? 'bg-indigo-100 text-indigo-700 border-indigo-200'
                : 'bg-emerald-100 text-emerald-700 border-emerald-200';

            listHtml += `
                <div class="p-3.5 rounded-2xl border-2 ${item.is_today ? 'border-rose-200 bg-rose-50/40' : 'border-amber-200 bg-amber-50/40'} space-y-1.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-black text-slate-800 text-sm">${idx + 1}. ${item.full_name} <span class="text-xs font-bold text-indigo-600">(${item.unit})</span></span>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black ${badgeClass}">${item.day_badge}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="px-2 py-0.5 rounded border font-bold ${hospClass}">${item.hospital_name}</span>
                        <span class="font-bold text-slate-700"><i class="fa-regular fa-calendar mr-1"></i>${item.date_thai} เวลา ${item.time}</span>
                        <span class="text-slate-500">| แผนก: <b>${item.clinic}</b></span>
                    </div>
                    <div class="text-xs text-slate-700 bg-white p-2 rounded-xl border border-slate-200 font-medium">
                        <i class="fa-solid fa-stethoscope text-indigo-500 mr-1"></i> <b>รายละเอียด:</b> ${item.cause}
                    </div>
                </div>
            `;
        });
        listHtml += `</div>`;

        Swal.fire({
            title: `<div class="text-lg md:text-xl font-black text-slate-800 flex items-center justify-center gap-2"><i class="fa-solid fa-bell text-amber-500 animate-bounce"></i> แจ้งเตือนคิวนัดพบแพทย์ (${upcomingAlertsData.length} ราย)</div>`,
            html: `
                <p class="text-xs text-slate-500">ระบบแจ้งเตือนล่วงหน้า 1 วัน และนัดประจำวันนี้ เพื่อให้หน่วยฝึกและพยาบาลเตรียมความพร้อมในการส่งตัวพลทหารครับ</p>
                ${listHtml}
            `,
            width: '42rem',
            showCloseButton: true,
            confirmButtonText: '<i class="fa-solid fa-check-double mr-1"></i> รับทราบรายการนัดหมาย',
            customClass: {
                popup: 'rounded-3xl p-6',
                confirmButton: 'bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl px-6 py-2.5 text-sm'
            }
        }).then(() => {
            sessionStorage.setItem(alertStorageKey, '1');
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (upcomingAlertsData && upcomingAlertsData.length > 0 && !hasServerValidationErrors) {
            setTimeout(() => showAdvanceAppointmentAlertModal(false), 400);
        }
    });

    // =========================================================================
    // 🔄 ฟังก์ชันกดซิงค์วันนัดพบแพทย์จากตาราง oapp ของ HosXP
    // =========================================================================
    function syncAppointmentsFromHosxp() {
        const btn = document.getElementById('btn-sync-oapp');
        const icon = document.getElementById('icon-sync-oapp');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        Swal.fire({
            title: 'กำลังดึงข้อมูลวันนัดจาก HosXP...',
            html: 'กำลังตรวจสอบตารางการนัดหมาย (oapp) ของพลทหารในผลัด <b>{{ $selectedBatch }}</b>',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            customClass: { popup: 'rounded-3xl' }
        });

        fetch("{{ route('backend.recruit_appointments.sync_hosxp') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ intake_batch: '{{ $selectedBatch }}' })
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');

            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'ซิงค์วันนัด HosXP สำเร็จ!',
                    text: data.message,
                    confirmButtonText: 'อัปเดตตาราง',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'bg-emerald-600 text-white rounded-xl px-6 py-2.5 font-bold' }
                }).then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'info', title: 'แจ้งเตือน', text: data.message });
            }
        })
        .catch(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
            Swal.fire({ icon: 'error', title: 'การเชื่อมต่อขัดข้อง', text: 'ไม่สามารถติดต่อฐานข้อมูล HosXP ได้ในขณะนี้' });
        });
    }

    // =========================================================================
    // ➕ ฟังก์ชันจัดการ Modal เพิ่มวันนัดพบแพทย์ & ระบบตรวจสอบช่องว่างรายช่อง
    // =========================================================================
    function openAddAppointmentModal() {
        document.getElementById('addAppointmentModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('app_patient_search')?.focus(), 100);
    }

    function closeAddAppointmentModal() {
        document.getElementById('addAppointmentModal').classList.add('hidden');
    }

    function filterAppointmentPatients(kw) {
        const list = document.getElementById('app_patient_dropdown');
        if (list) list.classList.remove('hidden');
        document.getElementById('app_patient_id').value = '';

        const clean = kw.trim().toLowerCase();
        document.querySelectorAll('.app-pt-option').forEach(el => {
            const hay = el.getAttribute('data-search') || '';
            el.classList.toggle('hidden', clean !== '' && !hay.includes(clean));
        });
    }

    function selectAppointmentPatient(id, label) {
        document.getElementById('app_patient_id').value = id;
        const searchInput = document.getElementById('app_patient_search');
        if (searchInput) {
            searchInput.value = label;
            clearFieldError('app_patient_search', 'err_patient_id');
            searchInput.classList.add('border-emerald-500', 'bg-emerald-50/30', 'font-bold');
        }
        document.getElementById('app_patient_dropdown')?.classList.add('hidden');
    }

    function toggleHospitalNameInput() {
        const type = document.querySelector('input[name="hospital_type"]:checked')?.value;
        const wrapper = document.getElementById('wrapper_hospital_name');
        const input = document.getElementById('app_hospital_name');

        if (type === 'public_hospital') {
            wrapper.classList.remove('hidden');
            if (input.value === 'รพ.ค่ายกฤษณ์สีวะรา') {
                input.value = '';
            }
            input.focus();
        } else {
            wrapper.classList.add('hidden');
            input.value = 'รพ.ค่ายกฤษณ์สีวะรา';
            clearFieldError('app_hospital_name', 'err_hospital_name');
        }
    }

    function setQuickAppointmentField(inputId, errId, textValue) {
        const el = document.getElementById(inputId);
        if (el) {
            el.value = textValue;
            clearFieldError(inputId, errId);
            el.focus();
        }
    }

    // 🌟 ฟังก์ชันตีกรอบสีแดงและแสดงข้อความเตือนใต้ช่องที่ลืมกรอก
    function markFieldError(inputId, errId, customMsg = null) {
        const inputEl = document.getElementById(inputId);
        const errEl = document.getElementById(errId);
        if (inputEl) {
            inputEl.classList.remove('border-slate-300', 'bg-slate-50', 'border-emerald-500', 'bg-emerald-50/30');
            inputEl.classList.add('border-rose-500', 'bg-rose-50/40', 'ring-2', 'ring-rose-500/20');
        }
        if (errEl) {
            if (customMsg) {
                const span = errEl.querySelector('span');
                if (span) span.textContent = customMsg;
            }
            errEl.classList.remove('hidden');
        }
    }

    // 🌟 ฟังก์ชันล้างกรอบสีแดงทันทีเมื่อผู้ใช้เริ่มพิมพ์แก้ไข
    function clearFieldError(inputId, errId) {
        const inputEl = document.getElementById(inputId);
        const errEl = document.getElementById(errId);
        if (inputEl) {
            inputEl.classList.remove('border-rose-500', 'bg-rose-50/40', 'ring-2', 'ring-rose-500/20');
            inputEl.classList.add('border-slate-300', 'bg-slate-50');
        }
        if (errEl) {
            errEl.classList.add('hidden');
        }
    }

    // 🛡️ ฟังก์ชันตรวจสอบทุกช่องที่บังคับกรอกก่อนส่งฟอร์ม
    function validateAppointmentForm(e) {
        let missingFields = [];
        let firstInvalidEl = null;

        // 1. ตรวจสอบการเลือกพลทหาร
        const patientId = document.getElementById('app_patient_id')?.value.trim();
        if (!patientId) {
            markFieldError('app_patient_search', 'err_patient_id', 'กรุณาพิมพ์ค้นหาแล้วคลิกเลือกรายชื่อพลทหารจากรายการที่ปรากฏ');
            missingFields.push('รายชื่อพลทหาร (ต้องคลิกเลือกจากรายการ)');
            if (!firstInvalidEl) firstInvalidEl = document.getElementById('app_patient_search');
        }

        // 2. ตรวจสอบชื่อโรงพยาบาลสาธารณสุข (กรณีเลือกส่งต่อ รพ.สาธารณสุข)
        const hospType = document.querySelector('input[name="hospital_type"]:checked')?.value;
        const hospName = document.getElementById('app_hospital_name')?.value.trim();
        if (hospType === 'public_hospital' && (!hospName || hospName === 'รพ.ค่ายกฤษณ์สีวะรา')) {
            markFieldError('app_hospital_name', 'err_hospital_name', 'กรุณาระบุชื่อโรงพยาบาลสาธารณสุขปลายทาง');
            missingFields.push('ชื่อโรงพยาบาลสาธารณสุขปลายทาง');
            if (!firstInvalidEl) firstInvalidEl = document.getElementById('app_hospital_name');
        }

        // 3. ตรวจสอบวันที่นัด
        const appDate = document.getElementById('app_date_input')?.value.trim();
        if (!appDate) {
            markFieldError('app_date_input', 'err_appointment_date', 'กรุณาระบุวันที่นัดพบแพทย์');
            missingFields.push('วันที่นัดพบแพทย์');
            if (!firstInvalidEl) firstInvalidEl = document.getElementById('app_date_input');
        }

        // 4. ตรวจสอบเวลานัด
        const appTime = document.getElementById('app_time_input')?.value.trim();
        if (!appTime) {
            markFieldError('app_time_input', 'err_appointment_time', 'กรุณาระบุเวลานัด');
            missingFields.push('เวลานัด');
            if (!firstInvalidEl) firstInvalidEl = document.getElementById('app_time_input');
        }

        // 5. ตรวจสอบคลินิก / แผนกที่นัด
        const clinicName = document.getElementById('app_clinic_input')?.value.trim();
        if (!clinicName) {
            markFieldError('app_clinic_input', 'err_clinic_name', 'กรุณาระบุคลินิกหรือแผนกที่นัด (หรือแตะปุ่มตัวเลือกด่วน)');
            missingFields.push('คลินิก / แผนกที่นัด');
            if (!firstInvalidEl) firstInvalidEl = document.getElementById('app_clinic_input');
        }

        // 6. ตรวจสอบสาเหตุการนัด / อาการที่ติดตาม
        const appCause = document.getElementById('app_cause_input')?.value.trim();
        if (!appCause) {
            markFieldError('app_cause_input', 'err_app_cause', 'กรุณาระบุสาเหตุการนัดหรืออาการที่ติดตาม');
            missingFields.push('สาเหตุการนัด / อาการที่ติดตาม');
            if (!firstInvalidEl) firstInvalidEl = document.getElementById('app_cause_input');
        }

        // หากมีช่องที่ลืมกรอก ให้ระงับการส่งฟอร์มและเด้งแจ้งเตือนชัดเจนทันที!
        if (missingFields.length > 0) {
            if (e) e.preventDefault();

            const listItems = missingFields.map(item => `<li class="text-rose-700 font-bold">${item}</li>`).join('');
            Swal.fire({
                icon: 'warning',
                title: 'กรอกข้อมูลยังไม่ครบถ้วน!',
                html: `
                    <div class="text-left text-xs sm:text-sm text-slate-600 space-y-2 mt-2">
                        <p>กรุณาตรวจสอบและกรอกข้อมูลในช่องที่มีกรอบสีแดงต่อไปนี้ให้ครบก่อนกดบันทึกครับ:</p>
                        <ul class="list-disc list-inside bg-rose-50 p-3.5 rounded-2xl border border-rose-200 space-y-1">
                            ${listItems}
                        </ul>
                    </div>
                `,
                confirmButtonText: 'กลับไปกรอกข้อมูล',
                confirmButtonColor: '#e11d48',
                customClass: {
                    popup: 'rounded-3xl p-6'
                }
            }).then(() => {
                if (firstInvalidEl) {
                    setTimeout(() => firstInvalidEl.focus(), 150);
                }
            });

            return false;
        }

        return true;
    }

    document.addEventListener('click', function(e) {
        const sBox = document.getElementById('app_patient_search');
        const sList = document.getElementById('app_patient_dropdown');
        if (sBox && sList && !sBox.contains(e.target) && !sList.contains(e.target)) {
            sList.classList.add('hidden');
        }
    });
</script>
@endsection