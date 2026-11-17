@extends('layouts.backend')

@section('title', 'ใบบันทึกสัญญาณชีพและการรับประทานยา - KSVR Hospital')
@section('page_header', 'ใบบันทึกสัญญาณชีพ (06:00/18:00) & บันทึกการรับประทานยา (e-MAR)')

@section('content')
@php
    // 🛡️ อัปเกรดคอลัมน์ในฐานข้อมูลอัตโนมัติ (รองรับ take_prn และ custom_slot_text ทันที)
    \App\Http\Controllers\Backend\RecruitInfirmaryController::ensureCareChartSchema();
    $activeStay = $patient->activeInfirmaryStay;
@endphp

<div class="max-w-7xl mx-auto space-y-5 pb-12">

    <!-- 🧭 แถบด้านบน: ปุ่มกลับหน้าห้องพยาบาลหน่วยฝึก + ข้อมูลผู้ป่วย + สลับคนตรวจด่วน + เลือกช่วงวันที่ -->
    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200 flex flex-col xl:flex-row items-start xl:items-center justify-between gap-4 print:border-0 print:shadow-none print:p-0">
        <div class="flex items-start sm:items-center gap-3.5">
            <!-- ปุ่มกลับหน้าตารางห้องพยาบาลหน่วยฝึกโดยตรง -->
            <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $patient->intake_batch]) }}"
               class="px-3.5 py-2.5 rounded-2xl bg-teal-50 hover:bg-teal-600 text-teal-700 hover:text-white border border-teal-200 font-bold text-xs flex items-center gap-1.5 transition-all shrink-0 print:hidden"
               title="กลับไปยังตารางห้องพยาบาลหน่วยฝึก & แยกโรงนอน">
                <i class="fa-solid fa-arrow-left"></i>
                <span class="hidden sm:inline">ห้องพยาบาลหน่วยฝึก</span>
            </a>

            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg sm:text-xl font-black text-slate-900">
                        {{ $patient->rank }}{{ $patient->first_name }} {{ $patient->last_name }}
                    </h2>
                    <span class="px-2.5 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold">
                        HN: {{ $patient->hn ?: 'ยังไม่มี HN' }}
                    </span>
                    <span class="px-2.5 py-0.5 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold">
                        {{ $patient->militaryUnit->short_name ?? $patient->company ?? 'ไม่ระบุหน่วย' }} (ผลัด {{ $patient->intake_batch ?? '-' }})
                    </span>
                    <a href="{{ route('backend.patients.show', $patient->id) }}" class="text-xs font-bold text-slate-500 hover:text-indigo-600 underline print:hidden">
                        <i class="fa-regular fa-address-card mr-0.5"></i>ดูแฟ้มประวัติ
                    </a>
                </div>

                <!-- แสดงสถานะการแยกนอนปัจจุบันจากหน้าห้องพยาบาลหน่วยฝึก -->
                @if($activeStay)
                    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                        <span class="px-2.5 py-0.5 rounded-md font-bold {{ $activeStay->location_type === 'unit_infirmary' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800' }}">
                            <i class="fa-solid {{ $activeStay->location_type === 'unit_infirmary' ? 'fa-tent-arrows-down' : 'fa-hospital-user' }} mr-1"></i>
                            {{ $activeStay->location_type === 'unit_infirmary' ? 'ห้องพยาบาลหน่วยฝึก' : 'ฝากนอน รพ.ค่ายฯ' }}
                            {{ $activeStay->bed_or_zone ? '(' . $activeStay->bed_or_zone . ')' : '' }}
                        </span>
                        <span class="font-bold text-rose-700">Dx: {{ $activeStay->diagnosis_text }}</span>
                        @if($activeStay->care_instruction)
                            <span class="text-teal-700 font-semibold">• การดูแล: {{ $activeStay->care_instruction }}</span>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-slate-500 mt-1">
                        แบบฟอร์มติดตามสัญญาณชีพประจำวัน (06.00 น. / 18.00 น.) และตารางเช็คการรับประทานยา/ทายาสำหรับเสนารักษ์และพยาบาล
                    </p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 w-full xl:w-auto justify-end print:hidden">
            <!-- 🔄 Dropdown สลับไปตรวจพลทหารคนถัดไปในห้องพยาบาลได้ทันที -->
            @if(isset($infirmaryPeers) && $infirmaryPeers->count() > 1)
                <div class="flex items-center gap-1.5 bg-teal-50/70 border border-teal-200 rounded-2xl px-3 py-1.5">
                    <i class="fa-solid fa-user-injured text-teal-600 text-xs"></i>
                    <span class="text-xs font-bold text-teal-800">สลับผู้ป่วย:</span>
                    <select onchange="if(this.value) window.location.href=this.value" class="bg-transparent text-xs font-black text-slate-800 outline-none cursor-pointer max-w-[190px] truncate">
                        @foreach($infirmaryPeers as $peerStay)
                            @php
                                $peerPt = $peerStay->patient;
                                if (!$peerPt) continue;
                                $peerUrl = route('backend.care_chart.show', $peerPt->id);
                            @endphp
                            <option value="{{ $peerUrl }}" {{ $peerPt->id === $patient->id ? 'selected' : '' }}>
                                {{ $peerPt->rank }}{{ $peerPt->first_name }} {{ $peerPt->last_name }} ({{ $peerStay->diagnosis_text }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <form method="GET" action="{{ route('backend.care_chart.show', $patient->id) }}" class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-2xl px-3 py-1.5">
                <span class="text-xs font-bold text-slate-500">เริ่มแสดงตั้งแต่วันที่:</span>
                <input type="date" name="start_date" value="{{ $startDateStr }}" onchange="this.form.submit()" class="bg-transparent text-xs sm:text-sm font-black text-indigo-700 outline-none cursor-pointer">
            </form>

            <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-2xl text-xs sm:text-sm font-bold shadow-sm flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i> พิมพ์ใบตาราง (A4)
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 📊 ส่วนที่ 1: ตารางบันทึกสัญญาณชีพ 06.00 น. และ 18.00 น. -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-200">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 print:hidden">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-heart-pulse text-rose-500"></i> 1. ตารางบันทึกสัญญาณชีพประจำวัน (เวลา 06.00 น. และ 18.00 น.)
                </h3>
                <p class="text-xs text-slate-500">คลิกที่แถวเวลา <b>06.00 น.</b> หรือ <b>18.00 น.</b> ในแต่ละวันเพื่อลงข้อมูล ระบบจะคำนวณ <b>Mew score</b> ให้อัตโนมัติ</p>
            </div>
            <button type="button" onclick="openVitalModal('{{ date('Y-m-d') }}', '{{ (int)date('H') < 12 ? '06:00' : '18:00' }}')"
                    class="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl text-xs sm:text-sm font-bold shadow-sm flex items-center gap-1.5 shrink-0">
                <i class="fa-solid fa-plus-circle"></i> + ลงสัญญาณชีพวันนี้
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-center border-collapse border-2 border-slate-800">
                <thead>
                    <tr class="bg-slate-100 text-slate-900 font-black">
                        <th rowspan="2" class="border border-slate-800 px-3 py-2.5 w-32">วัน/เดือน/ปี</th>
                        <th rowspan="2" class="border border-slate-800 px-3 py-2.5 w-24">เวลา</th>
                        <th colspan="6" class="border border-slate-800 px-3 py-2">สัญญาณชีพ</th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-800 font-bold text-xs">
                        <th class="border border-slate-800 px-2.5 py-2">อุณหภูมิ (°C)</th>
                        <th class="border border-slate-800 px-2.5 py-2">ชีพจร (/นาที)</th>
                        <th class="border border-slate-800 px-2.5 py-2">ความดันโลหิต</th>
                        <th class="border border-slate-800 px-2.5 py-2">หายใจ (/นาที)</th>
                        <th class="border border-slate-800 px-2.5 py-2">ออกซิเจนปลายนิ้ว. (%)</th>
                        <th class="border border-slate-800 px-2.5 py-2 w-28">Mew score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($dateColumns, 0, 7) as $dCol)
                        @php
                            $dayLogs = $vitalLogs->get($dCol['date_str'], collect());
                            $mLog = $dayLogs->firstWhere('round_time', '06:00');
                            $eLog = $dayLogs->firstWhere('round_time', '18:00');
                        @endphp
                        <!-- แถวรอบเช้า 06.00 น. -->
                        <tr onclick='openVitalModal("{{ $dCol["date_str"] }}", "06:00", @json($mLog))'
                            class="hover:bg-amber-50/60 cursor-pointer transition-colors">
                            <td rowspan="2" class="border border-slate-800 px-3 py-2 font-black text-slate-900 bg-white {{ $dCol['is_today'] ? 'bg-emerald-50/50 text-emerald-800' : '' }}">
                                {{ $dCol['thai_text'] }}
                                @if($dCol['is_today'])
                                    <span class="block text-[10px] text-emerald-600 font-bold print:hidden">(วันนี้)</span>
                                @endif
                            </td>
                            <td class="border border-slate-800 px-2 py-2 font-bold text-slate-800 bg-amber-50/30">06.00 น.</td>
                            <td class="border border-slate-800 px-2 py-2 font-bold {{ ($mLog && $mLog->temperature >= 38.0) ? 'text-rose-600 font-black' : '' }}">
                                {{ $mLog ? number_format($mLog->temperature, 1) : '' }}
                            </td>
                            <td class="border border-slate-800 px-2 py-2 font-semibold">{{ $mLog->heart_rate ?? '' }}</td>
                            <td class="border border-slate-800 px-2 py-2 font-semibold">
                                {{ ($mLog && $mLog->systolic_bp) ? ($mLog->systolic_bp . '/' . ($mLog->diastolic_bp ?? '-')) : '' }}
                            </td>
                            <td class="border border-slate-800 px-2 py-2 font-semibold">{{ $mLog->respiratory_rate ?? '' }}</td>
                            <td class="border border-slate-800 px-2 py-2 font-bold {{ ($mLog && $mLog->o2sat < 95) ? 'text-rose-600 font-black' : '' }}">
                                {{ $mLog ? $mLog->o2sat . '%' : '' }}
                            </td>
                            <td class="border border-slate-800 px-2 py-2 font-black">
                                @if($mLog)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs {{ $mLog->mews_score >= 4 ? 'bg-rose-600 text-white' : ($mLog->mews_score >= 2 ? 'bg-amber-500 text-white' : 'bg-emerald-100 text-emerald-800') }}">
                                        {{ $mLog->mews_score }}
                                    </span>
                                @else
                                    <span class="text-[11px] text-slate-300 font-normal print:hidden">+ คลิกกรอก</span>
                                @endif
                            </td>
                        </tr>

                        <!-- แถวรอบเย็น 18.00 น. -->
                        <tr onclick='openVitalModal("{{ $dCol["date_str"] }}", "18:00", @json($eLog))'
                            class="hover:bg-indigo-50/60 cursor-pointer transition-colors">
                            <td class="border border-slate-800 px-2 py-2 font-bold text-slate-800 bg-indigo-50/30">18.00 น.</td>
                            <td class="border border-slate-800 px-2 py-2 font-bold {{ ($eLog && $eLog->temperature >= 38.0) ? 'text-rose-600 font-black' : '' }}">
                                {{ $eLog ? number_format($eLog->temperature, 1) : '' }}
                            </td>
                            <td class="border border-slate-800 px-2 py-2 font-semibold">{{ $eLog->heart_rate ?? '' }}</td>
                            <td class="border border-slate-800 px-2 py-2 font-semibold">
                                {{ ($eLog && $eLog->systolic_bp) ? ($eLog->systolic_bp . '/' . ($eLog->diastolic_bp ?? '-')) : '' }}
                            </td>
                            <td class="border border-slate-800 px-2 py-2 font-semibold">{{ $eLog->respiratory_rate ?? '' }}</td>
                            <td class="border border-slate-800 px-2 py-2 font-bold {{ ($eLog && $eLog->o2sat < 95) ? 'text-rose-600 font-black' : '' }}">
                                {{ $eLog ? $eLog->o2sat . '%' : '' }}
                            </td>
                            <td class="border border-slate-800 px-2 py-2 font-black">
                                @if($eLog)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs {{ $eLog->mews_score >= 4 ? 'bg-rose-600 text-white' : ($eLog->mews_score >= 2 ? 'bg-amber-500 text-white' : 'bg-emerald-100 text-emerald-800') }}">
                                        {{ $eLog->mews_score }}
                                    </span>
                                @else
                                    <span class="text-[11px] text-slate-300 font-normal print:hidden">+ คลิกกรอก</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 💊 ส่วนที่ 2: ตารางบันทึกการรับประทานยา & ยาทา/ยานวด/เวลามีอาการ (e-MAR) -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-200">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 print:hidden">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-pills text-teal-600"></i> 2. ตารางบันทึกการรับประทานยา / ยาทา / ยานวด (เช้า / เที่ยง / เย็น / ก่อนนอน / เวลามีอาการ)
                </h3>
                <p class="text-xs text-slate-500">
                    รองรับทั้งยารับประทานตามมื้อปกติ, ยากินเวลามีอาการ (PRN), ยานวด และยาทา <b>คลิกในช่องตารางเพื่อเช็คชื่อการใช้ยา (✅ ใช้แล้ว / ❌ ขาดยา)</b>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if(!empty($patient->hn))
                    <button type="button" onclick="fetchDrugsFromHosxp()" class="px-3.5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-2xl text-xs font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-cloud-arrow-down"></i> ดึงรายการยาจาก HosXP
                    </button>
                @endif
                <button type="button" onclick="openAddMedicationModal()" class="px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-2xl text-xs sm:text-sm font-bold shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-plus"></i> + เพิ่มรายการยาที่ต้องกิน / ทา
                </button>
            </div>
        </div>

        <h4 class="hidden print:block text-center font-black text-base mb-2">บันทึกการรับประทานยา / การใช้ยา</h4>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-center border-collapse border-2 border-slate-800">
                <thead>
                    <tr class="bg-slate-100 text-slate-900 font-black">
                        <th rowspan="2" class="border border-slate-800 px-3 py-2.5 min-w-[210px]">ชื่อยา / ปริมาณ / วิธีรับประทานหรือวิธีใช้</th>
                        <th rowspan="2" class="border border-slate-800 px-2 py-2.5 w-36">เวลารับประทาน / เวลาใช้ยา</th>
                        <th colspan="10" class="border border-slate-800 px-2 py-1.5">วัน/เดือน/ปี</th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-800 font-bold text-[11px]">
                        @foreach($dateColumns as $dCol)
                            <th class="border border-slate-800 px-1.5 py-2 min-w-[78px] {{ $dCol['is_today'] ? 'bg-sky-100 text-sky-900 ring-2 ring-sky-500' : '' }}">
                                {{ $dCol['thai_text'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($medications as $med)
                        @php
                            $hasStandardSlots = $med->take_morning || $med->take_noon || $med->take_evening || $med->take_bedtime;
                            $hasPrnOrCustom   = $med->take_prn || !empty($med->custom_slot_text) || !$hasStandardSlots || in_array($med->meal_timing, ['prn', 'external']);

                            // สร้างรายการแถวมื้อเวลาสำหรับยาตัวนี้
                            $medSlotRows = [];
                            if ($hasStandardSlots) {
                                $medSlotRows['morning'] = ['label' => 'เช้า',    'active' => (bool)$med->take_morning];
                                $medSlotRows['noon']    = ['label' => 'เที่ยง',  'active' => (bool)$med->take_noon];
                                $medSlotRows['evening'] = ['label' => 'เย็น',    'active' => (bool)$med->take_evening];
                                $medSlotRows['bedtime'] = ['label' => 'ก่อนนอน', 'active' => (bool)$med->take_bedtime];
                            }

                            if ($hasPrnOrCustom) {
                                $customLabel = !empty($med->custom_slot_text)
                                    ? '⚡ ' . $med->custom_slot_text
                                    : ($med->meal_timing === 'external' ? '🧴 ทา/นวดตามอาการ' : '⚡ เวลามีอาการ (PRN)');
                                $medSlotRows['prn'] = ['label' => $customLabel, 'active' => true, 'is_prn' => true];
                            }

                            $totalSlotRows = count($medSlotRows);

                            $editMedPayload = [
                                'id'               => $med->id,
                                'care_group'       => $med->care_group,
                                'drug_name'        => $med->drug_name,
                                'dosage'           => $med->dosage,
                                'meal_timing'      => $med->meal_timing,
                                'take_morning'     => (bool)$med->take_morning,
                                'take_noon'        => (bool)$med->take_noon,
                                'take_evening'     => (bool)$med->take_evening,
                                'take_bedtime'     => (bool)$med->take_bedtime,
                                'take_prn'         => (bool)$med->take_prn,
                                'custom_slot_text' => $med->custom_slot_text ?? '',
                                'start_date'       => $med->start_date ? $med->start_date->format('Y-m-d') : $startDateStr,
                                'instruction_note' => $med->instruction_note ?? '',
                            ];
                        @endphp

                        @foreach($medSlotRows as $slotKey => $slotMeta)
                            @php
                                $isPrescribedSlot = $slotMeta['active'];
                                $isPrnRow = !empty($slotMeta['is_prn']);
                            @endphp
                            <tr>
                                @if($loop->first)
                                    <!-- คอลัมน์ที่ 1: ชื่อยา + ปริมาณ + วิธีใช้ + ปุ่มแก้ไข/ลบ -->
                                    <td rowspan="{{ $totalSlotRows }}" class="border border-slate-800 p-3 text-left align-middle bg-white relative">
                                        <div class="font-black text-slate-900 text-sm leading-snug">{{ $med->drug_name }}</div>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <span class="px-2 py-0.5 bg-teal-50 text-teal-800 border border-teal-200 rounded font-bold text-[11px]">
                                                {{ $med->dosage ?: 'ตามแพทย์สั่ง' }}
                                            </span>
                                            <span class="px-2 py-0.5 {{ $med->meal_timing === 'external' ? 'bg-amber-100 text-amber-900 border-amber-300' : ($med->meal_timing === 'prn' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-indigo-50 text-indigo-800 border-indigo-200') }} border rounded font-bold text-[11px]">
                                                {{ $med->meal_timing_label }}
                                            </span>
                                        </div>

                                        @if(!empty($med->custom_slot_text))
                                            <div class="mt-1.5 px-2 py-1 rounded-lg bg-amber-50/90 border border-amber-200 text-[11px] font-bold text-amber-900">
                                                <i class="fa-solid fa-clock-rotate-left text-amber-600 mr-1"></i>{{ $med->custom_slot_text }}
                                            </div>
                                        @endif

                                        <div class="mt-1 text-[10px] font-bold text-slate-400">
                                            {{ $med->care_group_label }}
                                        </div>

                                        @if($med->instruction_note)
                                            <div class="mt-1 text-[11px] text-rose-600 font-semibold">
                                                * {{ $med->instruction_note }}
                                            </div>
                                        @endif

                                        <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center gap-3 print:hidden">
                                            <button type="button" onclick='openEditMedicationModal(@json($editMedPayload))'
                                                    class="text-[11px] text-amber-600 hover:text-amber-800 hover:underline font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                                            </button>

                                            <form action="{{ route('backend.care_chart.destroy_medication', $med->id) }}" method="POST" onsubmit="return confirm('ต้องการลบรายการยานี้ใช่หรือไม่?')" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-[11px] text-rose-500 hover:underline font-bold flex items-center gap-1">
                                                    <i class="fa-solid fa-trash-can"></i> ลบ
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif

                                <!-- คอลัมน์ที่ 2: มื้อเวลา (เช้า / เที่ยง / เย็น / ก่อนนอน / เวลามีอาการ-ยาทา-ยานวด) -->
                                <td class="border border-slate-800 px-2.5 py-2 text-left font-bold {{ $isPrnRow ? 'text-amber-900 bg-amber-50/60 text-[11px]' : ($isPrescribedSlot ? 'text-slate-900 bg-white' : 'text-slate-300 bg-slate-50/70') }}">
                                    {{ $slotMeta['label'] }}
                                </td>

                                <!-- คอลัมน์ที่ 3-12: ช่องติ๊กการรับประทานยา/ทายารายวัน (10 วัน) -->
                                @foreach($dateColumns as $dCol)
                                    @php
                                        $cellKey = $med->id . '_' . $dCol['date_str'] . '_' . $slotKey;
                                        $cellLog = $medLogs->get($cellKey);
                                    @endphp
                                    @if($isPrescribedSlot)
                                        <td id="med-cell-{{ $cellKey }}"
                                            onclick="toggleMedCell({{ $med->id }}, '{{ $dCol['date_str'] }}', '{{ $slotKey }}', 'med-cell-{{ $cellKey }}')"
                                            class="border border-slate-800 p-1.5 cursor-pointer hover:bg-teal-50 transition-colors select-none {{ $dCol['is_today'] ? 'bg-sky-50/30' : 'bg-white' }}"
                                            title="คลิกเพื่อบันทึก: กิน/ทายาแล้ว (✅) หรือ ขาดยา (❌)">
                                            @if($cellLog && $cellLog->status === 'taken')
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white font-black text-xs shadow-sm">✓</span>
                                            @elseif($cellLog && $cellLog->status === 'missed')
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-500 text-white font-black text-xs shadow-sm">✕</span>
                                            @else
                                                <span class="text-slate-200 text-[10px] print:hidden">•</span>
                                            @endif
                                        </td>
                                    @else
                                        <td class="border border-slate-800 bg-slate-100/80 text-slate-300 select-none">-</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="12" class="border border-slate-800 py-10 text-center text-slate-400">
                                ยังไม่มีรายการยาที่ต้องรับประทาน คลิกปุ่ม <b>"+ เพิ่มรายการยาที่ต้องกิน / ทา"</b> ด้านบนเพื่อเพิ่มชื่อยาได้เลยครับ
                            </td>
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
    // 🧮 ฟังก์ชันคำนวณคะแนน MEWS สดในหน้าต่างกรอกสัญญาณชีพ
    function calcLiveModalMews() {
        const temp = parseFloat(document.getElementById('mv_temp')?.value);
        const hr   = parseInt(document.getElementById('mv_hr')?.value);
        const sbp  = parseInt(document.getElementById('mv_sbp')?.value);
        const rr   = parseInt(document.getElementById('mv_rr')?.value);
        const o2   = parseInt(document.getElementById('mv_o2')?.value);

        let score = 0;
        if (!isNaN(temp)) {
            if (temp < 35.0) score += 2;
            else if (temp <= 36.0) score += 1;
            else if (temp >= 38.1 && temp <= 38.5) score += 1;
            else if (temp > 38.5) score += 2;
        }
        if (!isNaN(hr)) {
            if (hr <= 40) score += 2;
            else if (hr <= 50) score += 1;
            else if (hr >= 101 && hr <= 110) score += 1;
            else if (hr >= 111 && hr <= 129) score += 2;
            else if (hr >= 130) score += 3;
        }
        if (!isNaN(sbp)) {
            if (sbp <= 70) score += 3;
            else if (sbp <= 80) score += 2;
            else if (sbp <= 100) score += 1;
            else if (sbp >= 200) score += 2;
        }
        if (!isNaN(rr)) {
            if (rr < 9) score += 2;
            else if (rr >= 15 && rr <= 20) score += 1;
            else if (rr >= 21 && rr <= 29) score += 2;
            else if (rr >= 30) score += 3;
        }
        if (!isNaN(o2)) {
            if (o2 < 90) score += 3;
            else if (o2 <= 94) score += 2;
        }

        const badge = document.getElementById('mv_mews_preview');
        if (badge) {
            badge.innerText = `Mew score: ${score} คะแนน`;
            badge.className = score >= 4
                ? 'px-3 py-1 rounded-full text-xs font-black bg-rose-600 text-white'
                : (score >= 2 ? 'px-3 py-1 rounded-full text-xs font-black bg-amber-500 text-white' : 'px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800');
        }
    }

    // 🌡️ เปิดป๊อปอัปกรอกสัญญาณชีพ (06.00 น. / 18.00 น.)
    function openVitalModal(dateStr, roundTime, existingLog = null) {
        Swal.fire({
            title: `<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-heart-pulse text-rose-500 mr-2"></i>บันทึกสัญญาณชีพ (เวลา ${roundTime} น.)</div>`,
            html: `
                <div class="text-left space-y-3 mt-2">
                    <div class="flex items-center justify-between bg-slate-50 p-3 rounded-2xl border border-slate-200">
                        <div class="text-xs font-bold text-slate-700">วันที่: <span class="text-indigo-700 font-black">${dateStr}</span> (${roundTime} น.)</div>
                        <span id="mv_mews_preview" class="px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800">Mew score: 0 คะแนน</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">1. อุณหภูมิ (°C) *</label>
                            <input type="number" step="0.1" id="mv_temp" oninput="calcLiveModalMews()" value="${existingLog?.temperature ?? '36.6'}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">2. ชีพจร (/นาที) *</label>
                            <input type="number" id="mv_hr" oninput="calcLiveModalMews()" value="${existingLog?.heart_rate ?? '80'}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">3. หายใจ (/นาที) *</label>
                            <input type="number" id="mv_rr" oninput="calcLiveModalMews()" value="${existingLog?.respiratory_rate ?? '18'}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">4. ความดันตัวบน (SBP) *</label>
                            <input type="number" id="mv_sbp" oninput="calcLiveModalMews()" value="${existingLog?.systolic_bp ?? '120'}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">5. ความดันตัวล่าง (DBP) *</label>
                            <input type="number" id="mv_dbp" value="${existingLog?.diastolic_bp ?? '80'}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">6. ออกซิเจนปลายนิ้ว (%) *</label>
                            <input type="number" id="mv_o2" oninput="calcLiveModalMews()" value="${existingLog?.o2sat ?? '98'}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">หมายเหตุอาการ (ถ้ามี)</label>
                        <input type="text" id="mv_note" value="${existingLog?.note ?? ''}" placeholder="เช่น ไม่มีไข้ อาการปกติ..." class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-xs sm:text-sm">
                    </div>
                </div>
            `,
            width: '36rem',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk mr-1"></i> บันทึกสัญญาณชีพ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#e11d48',
            didOpen: () => calcLiveModalMews(),
            customClass: { popup: 'rounded-3xl p-6' },
            preConfirm: () => {
                const t   = document.getElementById('mv_temp').value.trim();
                const hr  = document.getElementById('mv_hr').value.trim();
                const rr  = document.getElementById('mv_rr').value.trim();
                const sbp = document.getElementById('mv_sbp').value.trim();
                const dbp = document.getElementById('mv_dbp').value.trim();
                const o2  = document.getElementById('mv_o2').value.trim();

                if (!t || !hr || !rr || !sbp || !dbp || !o2) {
                    Swal.showValidationMessage('กรุณากรอกสัญญาณชีพให้ครบทั้ง 6 ช่องครับ');
                    return false;
                }
                return {
                    temperature: t,
                    heart_rate: hr,
                    respiratory_rate: rr,
                    systolic_bp: sbp,
                    diastolic_bp: dbp,
                    o2sat: o2,
                    note: document.getElementById('mv_note').value.trim()
                };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = "{{ route('backend.care_chart.store_vital') }}";
                form.innerHTML = `
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                    <input type="hidden" name="record_date" value="${dateStr}">
                    <input type="hidden" name="round_time" value="${roundTime}">
                    <input type="hidden" name="temperature" value="${res.value.temperature}">
                    <input type="hidden" name="heart_rate" value="${res.value.heart_rate}">
                    <input type="hidden" name="respiratory_rate" value="${res.value.respiratory_rate}">
                    <input type="hidden" name="systolic_bp" value="${res.value.systolic_bp}">
                    <input type="hidden" name="diastolic_bp" value="${res.value.diastolic_bp}">
                    <input type="hidden" name="o2sat" value="${res.value.o2sat}">
                    <input type="hidden" name="note" value="${res.value.note}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // 🛠️ ตัวช่วยในป๊อปอัปเพิ่ม/แก้ไขยา: ล้างมื้อเช้า-เที่ยง-เย็น-ก่อนนอน แล้วเปิดโหมดเวลามีอาการ/กำหนดเอง
    window.clearStandardMedSlots = function() {
        ['morning', 'noon', 'evening', 'bedtime'].forEach(val => {
            const cb = document.querySelector(`input[name="swal_med_slots"][value="${val}"]`);
            if (cb) cb.checked = false;
        });
        const prnCb = document.querySelector(`input[name="swal_med_slots"][value="prn"]`);
        if (prnCb) prnCb.checked = true;
    };

    window.applyQuickDrugPreset = function(drugName, dosage, timing, useMeals = true, usePrn = false, customSlot = '') {
        document.getElementById('med_drug_name').value = drugName;
        document.getElementById('med_dosage').value = dosage;
        document.getElementById('med_meal_timing').value = timing;
        document.getElementById('med_custom_slot_text').value = customSlot;

        ['morning', 'noon', 'evening'].forEach(val => {
            const cb = document.querySelector(`input[name="swal_med_slots"][value="${val}"]`);
            if (cb) cb.checked = useMeals;
        });
        const bedCb = document.querySelector(`input[name="swal_med_slots"][value="bedtime"]`);
        if (bedCb) bedCb.checked = false;
        const prnCb = document.querySelector(`input[name="swal_med_slots"][value="prn"]`);
        if (prnCb) prnCb.checked = usePrn;
    };

    window.onSwalMealTimingChange = function(val) {
        const dosageEl = document.getElementById('med_dosage');
        const customEl = document.getElementById('med_custom_slot_text');
        if (val === 'prn') {
            window.clearStandardMedSlots();
            if (customEl && !customEl.value.trim()) customEl.value = 'กินเวลามีอาการ';
        } else if (val === 'external') {
            window.clearStandardMedSlots();
            if (dosageEl && dosageEl.value === 'ครั้งละ 1 เม็ด') dosageEl.value = 'ทาบางๆ บริเวณที่มีอาการ';
            if (customEl && !customEl.value.trim()) customEl.value = 'ทา/นวดบริเวณที่มีอาการ';
        } else if (val === 'bedtime_only') {
            ['morning', 'noon', 'evening', 'prn'].forEach(v => {
                const cb = document.querySelector(`input[name="swal_med_slots"][value="${v}"]`);
                if (cb) cb.checked = false;
            });
            const bedCb = document.querySelector(`input[name="swal_med_slots"][value="bedtime"]`);
            if (bedCb) bedCb.checked = true;
        }
    };

    window.setSwalCustomSlotTag = function(text) {
        const el = document.getElementById('med_custom_slot_text');
        const prnCb = document.querySelector(`input[name="swal_med_slots"][value="prn"]`);
        if (el) {
            el.value = text;
            el.focus();
        }
        if (prnCb) prnCb.checked = true;
    };

    // 💊 เปิดป๊อปอัปเพิ่มหรือแก้ไขรายการยา (ไม่บังคับติ๊กมื้อหลัก + รองรับเวลามีอาการ / ยานวด / ยาทา)
    function openAddMedicationModal(prefillDrugName = '', prefillDosage = '', editData = null) {
        const isEdit = !!editData;
        const careGroup  = editData ? editData.care_group : 'ci_isolation';
        const drugName   = editData ? editData.drug_name : prefillDrugName;
        const dosage     = editData ? editData.dosage : (prefillDosage || 'ครั้งละ 1 เม็ด');
        const mealTiming = editData ? editData.meal_timing : 'after_meal';
        const chkM       = editData ? editData.take_morning : true;
        const chkN       = editData ? editData.take_noon : true;
        const chkE       = editData ? editData.take_evening : true;
        const chkB       = editData ? editData.take_bedtime : false;
        const chkPrn     = editData ? editData.take_prn : false;
        const customSlot = editData ? editData.custom_slot_text : '';
        const startDate  = editData ? editData.start_date : '{{ $startDateStr }}';
        const noteText   = editData ? editData.instruction_note : '';

        Swal.fire({
            title: `<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-pills text-teal-600 mr-2"></i>${isEdit ? 'แก้ไขรายการยา / วิธีใช้ยา' : 'เพิ่มรายการยาที่ต้องรับประทาน / ยาใช้ภายนอก'}</div>`,
            html: `
                <div class="text-left space-y-3.5 mt-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">กลุ่มผู้ป่วยที่ต้องติดตามการใช้ยา <span class="text-rose-500">*</span></label>
                        <select id="med_care_group" class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 p-2.5 text-xs sm:text-sm font-bold text-slate-800 outline-none">
                            <option value="ci_isolation" ${careGroup === 'ci_isolation' ? 'selected' : ''}>🏕️ 1. ผู้ป่วยแยกกัก CI / ห้องพยาบาลหน่วยฝึก (โควิด/ทางเดินหายใจ/อื่นๆ)</option>
                            <option value="psychiatric" ${careGroup === 'psychiatric' ? 'selected' : ''}>🧠 2. ผู้ป่วยโรคจิตเวช (ต้องรับประทานยาต่อเนื่องตามเวลา)</option>
                            <option value="chronic" ${careGroup === 'chronic' ? 'selected' : ''}>💊 3. ผู้ป่วยโรคประจำตัวเรื้อรังอื่นๆ (เช่น หอบหืด, ลมชัก, ความดัน)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">ชื่อยา (Drug Name) <span class="text-rose-500">*</span></label>
                        <div class="flex flex-wrap gap-1 mb-1.5">
                            <button type="button" onclick="window.applyQuickDrugPreset('Paracetamol 500 mg.', 'ครั้งละ 1 เม็ด', 'prn', false, true, 'กินเวลามีไข้หรือปวด ทุก 4-6 ชม.')" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-700 rounded text-[10px] font-bold border border-slate-200">+ Paracetamol (เวลามีอาการ)</button>
                            <button type="button" onclick="window.applyQuickDrugPreset('ยาละลายเสมหะ (Bromhexine)', 'ครั้งละ 1 เม็ด', 'after_meal', true, false, '')" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-700 rounded text-[10px] font-bold border border-slate-200">+ ละลายเสมหะ</button>
                            <button type="button" onclick="window.applyQuickDrugPreset('ยาแก้แพ้ ลดน้ำมูก (CPM)', 'ครั้งละ 1 เม็ด', 'after_meal', true, false, '')" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-700 rounded text-[10px] font-bold border border-slate-200">+ ลดน้ำมูก (CPM)</button>
                            <button type="button" onclick="window.applyQuickDrugPreset('ยานวดคลายกล้ามเนื้อ (Balm)', 'ทาบางๆ บริเวณที่ปวด', 'external', false, true, 'ทา/นวดบริเวณที่ปวดเมื่อย เช้า-เย็น หรือเวลามีอาการ')" class="px-2 py-0.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded text-[10px] font-bold border border-amber-200">🧴 + ยานวดคลายกล้ามเนื้อ</button>
                            <button type="button" onclick="window.applyQuickDrugPreset('ยาทาผื่นคัน / ยาทาแผล', 'ทาบางๆ บริเวณที่เป็น', 'external', false, true, 'ทาบริเวณแผล/ผื่นคัน เช้า-เย็น')" class="px-2 py-0.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded text-[10px] font-bold border border-amber-200">🧴 + ยาทาผื่น/แผล</button>
                        </div>
                        <input type="text" id="med_drug_name" value="${drugName}" placeholder="เช่น Paracetamol 500 mg., ยานวดคลายกล้ามเนื้อ, ยาทาผื่น..." class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-sm font-bold text-slate-800 outline-none">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ปริมาณยา / ขนาดที่ใช้ <span class="text-slate-400 font-normal">(ไม่บังคับ)</span></label>
                            <input type="text" id="med_dosage" value="${dosage}" placeholder="เช่น ครั้งละ 1 เม็ด, ทาบางๆ บริเวณที่ปวด..." class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-bold text-slate-800 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">การรับประทาน / วิธีใช้ <span class="text-rose-500">*</span></label>
                            <select id="med_meal_timing" onchange="window.onSwalMealTimingChange(this.value)" class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 p-2 text-sm font-bold text-slate-800 outline-none">
                                <option value="after_meal" ${mealTiming === 'after_meal' ? 'selected' : ''}>🍽️ หลังอาหาร</option>
                                <option value="before_meal" ${mealTiming === 'before_meal' ? 'selected' : ''}>⏳ ก่อนอาหาร</option>
                                <option value="with_meal" ${mealTiming === 'with_meal' ? 'selected' : ''}>🥗 พร้อมอาหาร</option>
                                <option value="bedtime_only" ${mealTiming === 'bedtime_only' ? 'selected' : ''}>🌙 ก่อนนอนอย่างเดียว</option>
                                <option value="prn" ${mealTiming === 'prn' ? 'selected' : ''}>🚨 เวลามีอาการ (PRN)</option>
                                <option value="external" ${mealTiming === 'external' ? 'selected' : ''}>🧴 ยาทาภายนอก / ยานวด (ไม่เกี่ยวกับอาหาร)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 🌟 โซนเลือกมื้อยาแบบไม่บังคับ + ช่องพิมพ์เองสำหรับเวลามีอาการ / ยาทา / ยานวด -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-1">
                            <label class="text-xs font-bold text-slate-700">
                                เวลาใช้ยา <span class="text-teal-600 font-semibold">(ไม่บังคับมื้อหลัก — เลือกเวลามีอาการ หรือพิมพ์ระบุเองด้านล่างได้)</span>
                            </label>
                            <button type="button" onclick="window.clearStandardMedSlots()" class="text-[11px] font-bold text-rose-500 hover:underline">
                                ล้างมื้อ เช้า-เที่ยง-เย็น
                            </button>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                            <label class="flex items-center gap-1.5 p-2 rounded-xl border-2 border-slate-200 bg-white cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 font-bold text-xs">
                                <input type="checkbox" name="swal_med_slots" value="morning" ${chkM ? 'checked' : ''} class="w-4 h-4 text-teal-600 rounded">
                                <span>🌅 เช้า</span>
                            </label>
                            <label class="flex items-center gap-1.5 p-2 rounded-xl border-2 border-slate-200 bg-white cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 font-bold text-xs">
                                <input type="checkbox" name="swal_med_slots" value="noon" ${chkN ? 'checked' : ''} class="w-4 h-4 text-teal-600 rounded">
                                <span>☀️ เที่ยง</span>
                            </label>
                            <label class="flex items-center gap-1.5 p-2 rounded-xl border-2 border-slate-200 bg-white cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 font-bold text-xs">
                                <input type="checkbox" name="swal_med_slots" value="evening" ${chkE ? 'checked' : ''} class="w-4 h-4 text-teal-600 rounded">
                                <span>🌆 เย็น</span>
                            </label>
                            <label class="flex items-center gap-1.5 p-2 rounded-xl border-2 border-slate-200 bg-white cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 font-bold text-xs">
                                <input type="checkbox" name="swal_med_slots" value="bedtime" ${chkB ? 'checked' : ''} class="w-4 h-4 text-teal-600 rounded">
                                <span>🌙 ก่อนนอน</span>
                            </label>
                            <label class="col-span-2 sm:col-span-1 flex items-center gap-1.5 p-2 rounded-xl border-2 border-amber-200 bg-amber-50/50 cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-100 font-black text-amber-900 text-xs">
                                <input type="checkbox" name="swal_med_slots" value="prn" ${chkPrn ? 'checked' : ''} class="w-4 h-4 text-amber-600 rounded">
                                <span>⚡ มีอาการ/อื่นๆ</span>
                            </label>
                        </div>

                        <!-- ✍️ ช่องว่างให้พิมพ์ระบุเองสำหรับเวลามีอาการ / ยานวด / ยาทา -->
                        <div class="pt-1">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">
                                <i class="fa-solid fa-pen-clip text-teal-600 mr-1"></i>ช่องพิมพ์ระบุเวลา / วิธีใช้เพิ่มเติม (เช่น กินเวลามีอาการ, ยานวด, ยาทา — ไม่บังคับ):
                            </label>
                            <div class="flex flex-wrap gap-1 mb-1.5">
                                <button type="button" onclick="window.setSwalCustomSlotTag('กินเวลามีไข้หรือปวด (ทุก 4-6 ชม.)')" class="px-2 py-0.5 bg-white hover:bg-amber-50 text-slate-600 hover:text-amber-800 border border-slate-200 rounded text-[10px] font-bold">+ กินเวลามีไข้/ปวด</button>
                                <button type="button" onclick="window.setSwalCustomSlotTag('กินเวลามีอาการไอ หรือระคายคอ')" class="px-2 py-0.5 bg-white hover:bg-amber-50 text-slate-600 hover:text-amber-800 border border-slate-200 rounded text-[10px] font-bold">+ กินเวลาไอ</button>
                                <button type="button" onclick="window.setSwalCustomSlotTag('ทา/นวดบริเวณที่ปวดเมื่อย')" class="px-2 py-0.5 bg-white hover:bg-amber-50 text-slate-600 hover:text-amber-800 border border-slate-200 rounded text-[10px] font-bold">+ ทา/นวดเวลาปวด</button>
                                <button type="button" onclick="window.setSwalCustomSlotTag('ทาแผล/ผื่นคัน เช้า-เย็น')" class="px-2 py-0.5 bg-white hover:bg-amber-50 text-slate-600 hover:text-amber-800 border border-slate-200 rounded text-[10px] font-bold">+ ทาแผล/ผื่นคัน</button>
                            </div>
                            <input type="text" id="med_custom_slot_text" value="${customSlot}"
                                   placeholder="พิมพ์ระบุเองได้เลย เช่น กินเวลามีอาการปวด, ทานวดบริเวณข้อเท้า..."
                                   class="w-full px-3 py-2 bg-white border border-slate-300 focus:border-teal-600 rounded-xl text-xs font-semibold text-slate-800 outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">วันที่เริ่มให้ยา <span class="text-rose-500">*</span></label>
                            <input type="date" id="med_start_date" value="${startDate}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-xs font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ข้อควรระวัง / หมายเหตุให้เสนารักษ์</label>
                            <input type="text" id="med_note" value="${noteText}" placeholder="เช่น ห้ามขาดยา, ยาใช้ภายนอกห้ามรับประทาน..." class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-xs">
                        </div>
                    </div>
                </div>
            `,
            width: '40rem',
            showCancelButton: true,
            confirmButtonText: `<i class="fa-solid ${isEdit ? 'fa-floppy-disk' : 'fa-plus'} mr-1"></i> ${isEdit ? 'บันทึกการแก้ไข' : 'บันทึกรายการยา'}`,
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0d9488',
            customClass: { popup: 'rounded-3xl p-6' },
            preConfirm: () => {
                const dName      = document.getElementById('med_drug_name').value.trim();
                const dDosage    = document.getElementById('med_dosage').value.trim();
                const mTiming    = document.getElementById('med_meal_timing').value;
                const customSlot = document.getElementById('med_custom_slot_text').value.trim();
                let checkedSlots = Array.from(document.querySelectorAll('input[name="swal_med_slots"]:checked')).map(cb => cb.value);

                if (!dName) {
                    Swal.showValidationMessage('กรุณาระบุชื่อยา (Drug Name) ครับ');
                    return false;
                }

                // หากไม่ได้ติ๊กมื้อใดเลย ให้กำหนดเป็น 'prn' (เวลามีอาการ/กำหนดเอง) อัตโนมัติโดยไม่บล็อกผู้ใช้!
                if (checkedSlots.length === 0) {
                    checkedSlots = ['prn'];
                }

                return {
                    medication_id: editData ? editData.id : '',
                    care_group: document.getElementById('med_care_group').value,
                    drug_name: dName,
                    dosage: dDosage || (mTiming === 'external' ? 'ทาบางๆ บริเวณที่มีอาการ' : 'ตามแพทย์สั่ง'),
                    meal_timing: mTiming,
                    slots: checkedSlots,
                    custom_slot_text: customSlot,
                    start_date: document.getElementById('med_start_date').value,
                    instruction_note: document.getElementById('med_note').value.trim()
                };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = "{{ route('backend.care_chart.store_medication') }}";
                let slotsInputs = res.value.slots.map(s => `<input type="hidden" name="slots[]" value="${s}">`).join('');
                form.innerHTML = `
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="medication_id" value="${res.value.medication_id}">
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                    <input type="hidden" name="care_group" value="${res.value.care_group}">
                    <input type="hidden" name="drug_name" value="${res.value.drug_name}">
                    <input type="hidden" name="dosage" value="${res.value.dosage}">
                    <input type="hidden" name="meal_timing" value="${res.value.meal_timing}">
                    <input type="hidden" name="custom_slot_text" value="${res.value.custom_slot_text}">
                    <input type="hidden" name="start_date" value="${res.value.start_date}">
                    <input type="hidden" name="instruction_note" value="${res.value.instruction_note}">
                    ${slotsInputs}
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function openEditMedicationModal(medObj) {
        openAddMedicationModal('', '', medObj);
    }

    // ✅ คลิกสลับสถานะกิน/ทายา (ใช้แล้ว ✓ -> ขาดยา ✕ -> ว่าง) แบบไม่ต้องโหลดหน้าใหม่
    function toggleMedCell(medId, logDate, timeSlot, cellDomId) {
        const cell = document.getElementById(cellDomId);
        if (!cell) return;

        fetch("{{ route('backend.care_chart.toggle_med_log') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                patient_medication_id: medId,
                log_date: logDate,
                time_slot: timeSlot
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                if (res.state === 'taken') {
                    cell.innerHTML = `<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white font-black text-xs shadow-sm">✓</span>`;
                } else if (res.state === 'missed') {
                    cell.innerHTML = `<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-500 text-white font-black text-xs shadow-sm">✕</span>`;
                } else {
                    cell.innerHTML = `<span class="text-slate-200 text-[10px] print:hidden">•</span>`;
                }
            }
        });
    }

    // 🏥 ดึงรายการยาล่าสุดจาก HosXP มาให้เลือกเพิ่มเข้าตาราง
    function fetchDrugsFromHosxp() {
        Swal.fire({ title: 'กำลังดึงรายการยาล่าสุดจาก HosXP...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch("{{ route('backend.care_chart.hosxp_drugs', $patient->id) }}")
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data) {
                    Swal.fire({ icon: 'info', title: 'แจ้งเตือน', text: res.message || 'ไม่พบรายการยา' });
                    return;
                }

                let listHtml = '<div class="text-left space-y-2 mt-2 max-h-64 overflow-y-auto">';
                res.data.forEach(d => {
                    const safeName = d.drug_name.replace(/'/g, "\\'");
                    const safeUsage = d.usage.replace(/'/g, "\\'");
                    listHtml += `
                        <div class="p-3 bg-slate-50 hover:bg-teal-50 rounded-xl border border-slate-200 flex items-center justify-between gap-2 cursor-pointer"
                             onclick="Swal.close(); openAddMedicationModal('${safeName}', '${safeUsage}');">
                            <div>
                                <div class="font-black text-slate-800 text-sm">${d.drug_name}</div>
                                <div class="text-xs text-slate-500">${d.usage}</div>
                            </div>
                            <span class="px-2.5 py-1 bg-teal-600 text-white rounded-lg text-xs font-bold shrink-0">+ เลือกยานี้</span>
                        </div>
                    `;
                });
                listHtml += '</div>';

                Swal.fire({
                    title: 'เลือกรายการยาจาก HosXP',
                    html: listHtml,
                    showConfirmButton: false,
                    showCloseButton: true,
                    customClass: { popup: 'rounded-3xl p-6' }
                });
            });
    }
</script>
@endsection