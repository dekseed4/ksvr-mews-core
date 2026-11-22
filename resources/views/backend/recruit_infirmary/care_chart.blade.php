@extends('layouts.backend')

@section('title', 'ใบบันทึกสัญญาณชีพและการรับประทานยา - KSVR Hospital')
@section('page_header', 'ใบบันทึกสัญญาณชีพ (06:00/18:00) & บันทึกการรับประทานยา (e-MAR)')

@section('content')
@php
    // 🛡️ ใช้ตัวเช็ก Schema ที่มี Cache ครอบแล้วจาก RecruitCareChartController (ไม่ยิง ALTER TABLE ซ้ำ)
    \App\Http\Controllers\Backend\RecruitCareChartController::ensureCareChartSchema();

    $activeStay = $patient->activeInfirmaryStay;
    $daysCount  = (int) ($days ?? count($dateColumns));
    $todayStr   = $todayDateStr ?? date('Y-m-d');
    $prevStart  = $prevStartDate ?? \Carbon\Carbon::parse($startDateStr)->subDays($daysCount)->format('Y-m-d');
    $nextStart  = $nextStartDate ?? \Carbon\Carbon::parse($startDateStr)->addDays($daysCount)->format('Y-m-d');
    $firstCol   = $dateColumns[0] ?? null;
    $lastCol    = !empty($dateColumns) ? $dateColumns[count($dateColumns) - 1] : null;
    $isCompactMar = $daysCount >= 14;

    $defaultMobileMarDate = collect($dateColumns)->firstWhere('is_today', true)['date_str']
        ?? ($dateColumns[0]['date_str'] ?? date('Y-m-d'));
@endphp

<style>
    .ksvr-no-scrollbar::-webkit-scrollbar { display: none; }
    .ksvr-no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div class="max-w-7xl mx-auto space-y-5 pb-12">

    <!-- 🧭 แถบด้านบน: ปุ่มกลับหน้าห้องพยาบาล + ข้อมูลผู้ป่วย + สลับคนตรวจด่วน + เลือกช่วงวันที่ -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col xl:flex-row items-start xl:items-center justify-between gap-4 print:border-0 print:shadow-none print:p-0">
        <div class="flex items-start sm:items-center gap-3.5">
            <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $patient->intake_batch]) }}"
               class="px-3.5 py-2.5 rounded-2xl bg-teal-50 hover:bg-teal-600 text-teal-700 hover:text-white border border-teal-200 font-bold text-xs flex items-center gap-1.5 transition-all shrink-0 print:hidden"
               title="กลับไปยังตารางห้องพยาบาลหน่วยฝึก & แยกโรงนอน">
                <i class="fa-solid fa-arrow-left"></i>
                <span class="hidden sm:inline">ห้องพยาบาลหน่วยฝึก</span>
            </a>

            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
                        {{ $patient->rank }}{{ $patient->first_name }} {{ $patient->last_name }}
                    </h2>
                    <span class="px-2.5 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold">
                        HN: {{ $patient->hn ?: 'ยังไม่มี HN' }}
                    </span>
                    <span class="px-2.5 py-0.5 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold">
                        {{ $patient->militaryUnit?->short_name ?? $patient->company ?? 'ไม่ระบุหน่วย' }} (ผลัด {{ $patient->intake_batch ?? '-' }})
                    </span>
                    <a href="{{ route('backend.patients.show', $patient->id) }}" class="text-xs font-bold text-slate-500 hover:text-indigo-600 underline print:hidden">
                        <i class="fa-regular fa-address-card mr-0.5"></i>ดูแฟ้มประวัติ
                    </a>
                </div>

                @if($activeStay)
                    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                        <span class="px-2.5 py-0.5 rounded-md font-bold {{ $activeStay->location_type === 'unit_infirmary' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800' }}">
                            <i class="fa-solid {{ $activeStay->location_type === 'unit_infirmary' ? 'fa-tent-arrows-down' : 'fa-hospital-user' }} mr-1"></i>
                            {{ $activeStay->location_type === 'unit_infirmary' ? 'ห้องพยาบาลหน่วยฝึก' : 'ฝากนอน รพ.ค่ายฯ' }}
                            {{ $activeStay->bed_or_zone ? '(' . $activeStay->bed_or_zone . ')' : '' }}
                        </span>
                        <span class="font-bold text-rose-700 dark:text-rose-400">Dx: {{ $activeStay->diagnosis_text }}</span>
                        @if($activeStay->care_instruction)
                            <span class="text-teal-700 dark:text-teal-300 font-semibold">• การดูแล: {{ $activeStay->care_instruction }}</span>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        แบบฟอร์มติดตามสัญญาณชีพประจำวัน (06.00 น. / 18.00 น.) และตารางเช็กการรับประทานยาต่อเนื่อง (e-MAR)
                    </p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto justify-end print:hidden">
            @if(isset($infirmaryPeers) && $infirmaryPeers->count() > 1)
                <div class="flex items-center gap-1.5 bg-teal-50/70 border border-teal-200 rounded-2xl px-3 py-1.5">
                    <i class="fa-solid fa-user-injured text-teal-600 text-xs"></i>
                    <span class="text-xs font-bold text-teal-800">สลับผู้ป่วย:</span>
                    <select onchange="if(this.value) window.location.href=this.value" class="bg-transparent text-xs font-black text-slate-800 outline-none cursor-pointer max-w-[180px] truncate">
                        @foreach($infirmaryPeers as $peerStay)
                            @php
                                $peerPt = $peerStay->patient;
                                if (!$peerPt) continue;
                                $peerUrl = route('backend.care_chart.show', ['patient' => $peerPt->id, 'days' => $daysCount]);
                            @endphp
                            <option value="{{ $peerUrl }}" {{ $peerPt->id === $patient->id ? 'selected' : '' }}>
                                {{ $peerPt->rank }}{{ $peerPt->first_name }} {{ $peerPt->last_name }} ({{ $peerStay->diagnosis_text }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <form method="GET" action="{{ route('backend.care_chart.show', $patient->id) }}" class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl px-3 py-1.5">
                <input type="hidden" name="days" value="{{ $daysCount }}">
                <span class="text-xs font-bold text-slate-500">เริ่มวันที่:</span>
                <input type="date" name="start_date" value="{{ $startDateStr }}" onchange="this.form.submit()" class="bg-transparent text-xs sm:text-sm font-black text-indigo-700 dark:text-indigo-400 outline-none cursor-pointer">
            </form>

            @include('partials.export_report_menu', [
                'module' => 'care_chart',
                'params' => ['patient_id' => $patient->id, 'start_date' => $startDateStr, 'days' => $daysCount],
            ])
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 📊 ส่วนที่ 1: บันทึกสัญญาณชีพ 06.00 น. และ 18.00 น. (แสดง 7 วันจากวันเริ่มต้น) -->
    <!-- ========================================================================= -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm border border-slate-200 dark:border-slate-700 print:border-0 print:shadow-none print:p-0">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-slate-700 print:hidden">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-heart-pulse text-rose-500"></i> 1. บันทึกสัญญาณชีพประจำวัน (06.00 น. / 18.00 น.)
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">แตะรอบเวลา <b>06.00 น.</b> หรือ <b>18.00 น.</b> เพื่อลงข้อมูล ระบบคำนวณ <b>Mew score</b> อัตโนมัติ</p>
            </div>
            <button type="button" onclick="openVitalModal('{{ date('Y-m-d') }}', '{{ (int)date('H') < 12 ? '06:00' : '18:00' }}')"
                    class="w-full sm:w-auto justify-center px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl text-xs sm:text-sm font-bold shadow-sm flex items-center gap-1.5 shrink-0">
                <i class="fa-solid fa-plus-circle"></i> + ลงสัญญาณชีพวันนี้
            </button>
        </div>

        <!-- 📱 โหมดมือถือ: การ์ดสัญญาณชีพรายวัน -->
        <div class="md:hidden print:hidden space-y-3">
            @foreach(array_slice($dateColumns, 0, 7) as $dCol)
                @php
                    $dayLogs = $vitalLogs->get($dCol['date_str'], collect());
                    $mLog = $dayLogs->firstWhere('round_time', '06:00');
                    $eLog = $dayLogs->firstWhere('round_time', '18:00');
                @endphp
                <div class="rounded-2xl border {{ $dCol['is_today'] ? 'border-emerald-300 bg-emerald-50/25 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40' }} overflow-hidden">
                    <div class="px-3.5 py-2 border-b {{ $dCol['is_today'] ? 'border-emerald-200 bg-emerald-50/70 dark:bg-emerald-900/30' : 'border-slate-200/80 dark:border-slate-700 bg-slate-100/70 dark:bg-slate-800/80' }} flex items-center justify-between">
                        <span class="font-black text-xs {{ $dCol['is_today'] ? 'text-emerald-800 dark:text-emerald-300' : 'text-slate-700 dark:text-slate-200' }}">
                            <i class="fa-regular fa-calendar mr-1"></i>{{ $dCol['thai_text'] }}
                        </span>
                        @if($dCol['is_today'])
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-600 text-white">วันนี้</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 divide-x divide-slate-200/80 dark:divide-slate-700">
                        <button type="button" onclick='openVitalModal("{{ $dCol["date_str"] }}", "06:00", @json($mLog))'
                                class="p-3 text-left hover:bg-amber-50/60 dark:hover:bg-amber-900/20 transition-colors space-y-1.5">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[11px] font-black text-amber-700 dark:text-amber-400">🌅 06.00 น.</span>
                                @if($mLog)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $mLog->mews_score >= 4 ? 'bg-rose-600 text-white' : ($mLog->mews_score >= 2 ? 'bg-amber-500 text-white' : 'bg-emerald-100 text-emerald-800') }}">
                                        MEWS {{ $mLog->mews_score }}
                                    </span>
                                @endif
                            </div>

                            @if($mLog)
                                <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 text-[11px] font-semibold text-slate-700 dark:text-slate-200">
                                    <span class="{{ $mLog->temperature >= 38.0 ? 'text-rose-600 font-black' : '' }}">T: {{ number_format($mLog->temperature, 1) }}°</span>
                                    <span>PR: {{ $mLog->heart_rate ?? '-' }}</span>
                                    <span class="col-span-2">BP: {{ $mLog->systolic_bp ?? '-' }}/{{ $mLog->diastolic_bp ?? '-' }}</span>
                                    <span>RR: {{ $mLog->respiratory_rate ?? '-' }}</span>
                                    <span class="{{ $mLog->o2sat < 95 ? 'text-rose-600 font-black' : '' }}">O2: {{ $mLog->o2sat }}%</span>
                                </div>
                            @else
                                <div class="py-2 text-center text-[11px] font-bold text-slate-400 border border-dashed border-slate-300 dark:border-slate-600 rounded-xl">
                                    + แตะลงข้อมูล
                                </div>
                            @endif
                        </button>

                        <button type="button" onclick='openVitalModal("{{ $dCol["date_str"] }}", "18:00", @json($eLog))'
                                class="p-3 text-left hover:bg-indigo-50/60 dark:hover:bg-indigo-900/20 transition-colors space-y-1.5">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[11px] font-black text-indigo-700 dark:text-indigo-400">🌆 18.00 น.</span>
                                @if($eLog)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $eLog->mews_score >= 4 ? 'bg-rose-600 text-white' : ($eLog->mews_score >= 2 ? 'bg-amber-500 text-white' : 'bg-emerald-100 text-emerald-800') }}">
                                        MEWS {{ $eLog->mews_score }}
                                    </span>
                                @endif
                            </div>

                            @if($eLog)
                                <div class="grid grid-cols-2 gap-x-2 gap-y-0.5 text-[11px] font-semibold text-slate-700 dark:text-slate-200">
                                    <span class="{{ $eLog->temperature >= 38.0 ? 'text-rose-600 font-black' : '' }}">T: {{ number_format($eLog->temperature, 1) }}°</span>
                                    <span>PR: {{ $eLog->heart_rate ?? '-' }}</span>
                                    <span class="col-span-2">BP: {{ $eLog->systolic_bp ?? '-' }}/{{ $eLog->diastolic_bp ?? '-' }}</span>
                                    <span>RR: {{ $eLog->respiratory_rate ?? '-' }}</span>
                                    <span class="{{ $eLog->o2sat < 95 ? 'text-rose-600 font-black' : '' }}">O2: {{ $eLog->o2sat }}%</span>
                                </div>
                            @else
                                <div class="py-2 text-center text-[11px] font-bold text-slate-400 border border-dashed border-slate-300 dark:border-slate-600 rounded-xl">
                                    + แตะลงข้อมูล
                                </div>
                            @endif
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- 💻 โหมดคอมพิวเตอร์ & โหมดพิมพ์ A4 -->
        <div class="hidden md:block print:block overflow-x-auto">
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
    <!-- 💊 ส่วนที่ 2: ตารางยา e-MAR (รองรับขยายวัน 10 / 14 / 30 วัน + เลื่อนช่วงวันไม่จำกัด) -->
    <!-- ========================================================================= -->
    <div id="emar-section" class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm border border-slate-200 dark:border-slate-700 print:border-0 print:shadow-none print:p-0">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-slate-700 print:hidden">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-pills text-teal-600"></i> 2. บันทึกการรับประทานยา / ยาทา / ยานวด (e-MAR)
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    แตะช่องมื้อยาเพื่อเช็กสถานะ (<b>✅ ใช้แล้ว → ❌ ขาดยา → ล้างค่า</b>) รองรับยาต่อเนื่องทุกวัน (จิตเวช/โรคเรื้อรัง) และขยายช่วงวันได้ไม่จำกัด
                </p>
            </div>
            <div class="grid grid-cols-1 sm:flex sm:flex-wrap items-center gap-2 shrink-0">
                @if(!empty($patient->hn))
                    <button type="button" onclick="fetchDrugsFromHosxp()" class="w-full sm:w-auto justify-center px-3.5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-2xl text-xs font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-cloud-arrow-down"></i> ดึงรายการยาจาก HosXP
                    </button>
                @endif
                <button type="button" onclick="openAddMedicationModal()" class="w-full sm:w-auto justify-center px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-2xl text-xs sm:text-sm font-bold shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-plus"></i> + เพิ่มรายการยาที่ต้องกิน / ทา
                </button>
            </div>
        </div>

        <!-- 🗓️ แถบควบคุมจำนวนวันและเลื่อนช่วงวัน e-MAR (10 / 14 / 30 วัน + ก่อนหน้า / วันนี้ / ถัดไป) -->
        <div class="mb-4 p-3 rounded-2xl bg-slate-50/90 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-700 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 print:hidden">
            <!-- ฝั่งซ้าย: ปุ่มเลื่อนช่วงวัน -->
            <div class="flex flex-wrap items-center justify-between sm:justify-start gap-2">
                <div class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 p-1 shadow-sm">
                    <a href="{{ route('backend.care_chart.show', ['patient' => $patient->id, 'start_date' => $prevStart, 'days' => $daysCount]) }}#emar-section"
                       class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 inline-flex items-center gap-1 transition-colors"
                       title="ดูย้อนหลัง {{ $daysCount }} วัน">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i> ก่อนหน้า
                    </a>
                    <a href="{{ route('backend.care_chart.show', ['patient' => $patient->id, 'start_date' => $todayStr, 'days' => $daysCount]) }}#emar-section"
                       class="px-3 py-1.5 rounded-lg text-xs font-black text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-900/40 hover:bg-sky-100 mx-0.5 inline-flex items-center gap-1 transition-colors">
                        <i class="fa-solid fa-location-crosshairs text-[11px]"></i> วันนี้
                    </a>
                    <a href="{{ route('backend.care_chart.show', ['patient' => $patient->id, 'start_date' => $nextStart, 'days' => $daysCount]) }}#emar-section"
                       class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 inline-flex items-center gap-1 transition-colors"
                       title="ดูเพิ่มอีก {{ $daysCount }} วันถัดไป">
                        ถัดไป <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </a>
                </div>

                @if($firstCol && $lastCol)
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300 px-2">
                        <i class="fa-regular fa-calendar-days text-teal-600 mr-1"></i>
                        {{ $firstCol['thai_text'] }} – {{ $lastCol['thai_text'] }}
                        <span class="text-teal-700 dark:text-teal-400 font-black">({{ $daysCount }} วัน)</span>
                    </span>
                @endif
            </div>

            <!-- ฝั่งขวา: ปุ่มสลับจำนวนวันในตาราง (10 / 14 / 30 วัน) -->
            <div class="flex flex-wrap items-center justify-between sm:justify-end gap-2">
                <span class="text-[11px] font-bold text-slate-400">ขยายช่วงวันในตาราง:</span>
                <div class="inline-flex items-center gap-1 bg-white dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-600 shadow-sm">
                    @foreach([10 => '10 วัน', 14 => '14 วัน', 30 => '30 วัน (ทั้งเดือน)'] as $dVal => $dLabel)
                        <a href="{{ route('backend.care_chart.show', ['patient' => $patient->id, 'start_date' => $startDateStr, 'days' => $dVal]) }}#emar-section"
                           class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all {{ $daysCount === $dVal ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-teal-50 dark:hover:bg-slate-700' }}">
                            {{ $dLabel }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <h4 class="hidden print:block text-center font-black text-base mb-2">
            บันทึกการรับประทานยา / การใช้ยา ({{ $firstCol['thai_text'] ?? '' }} – {{ $lastCol['thai_text'] ?? '' }})
        </h4>

        <!-- =========================================================== -->
        <!-- 📱 โหมดมือถือ e-MAR: แถบเลือกวันที่ + การ์ดยาพร้อมปุ่มติ๊กขนาดใหญ่ -->
        <!-- =========================================================== -->
        <div class="md:hidden print:hidden space-y-4">
            @if($medications->count() > 0)
                <div>
                    <div class="text-[11px] font-bold text-slate-400 mb-1.5 flex items-center justify-between">
                        <span><i class="fa-regular fa-calendar-check text-teal-500 mr-1"></i>แตะเลือกวันที่ต้องการเช็กยา ({{ $daysCount }} วัน):</span>
                        <span class="text-[10px]">ปัดเลื่อนได้ →</span>
                    </div>
                    <div id="mobile-mar-date-strip" class="ksvr-no-scrollbar flex items-center gap-1.5 overflow-x-auto pb-1 scroll-smooth">
                        @foreach($dateColumns as $dCol)
                            @php $isDefDate = ($dCol['date_str'] === $defaultMobileMarDate); @endphp
                            <button type="button"
                                    data-mar-date-btn="{{ $dCol['date_str'] }}"
                                    onclick="selectMobileMarDate('{{ $dCol['date_str'] }}', this)"
                                    class="px-3 py-2 rounded-xl text-xs font-black whitespace-nowrap border transition-all shrink-0 {{ $isDefDate ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : ($dCol['is_today'] ? 'bg-sky-50 text-sky-700 border-sky-300' : 'bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700') }}">
                                {{ $dCol['short_thai'] ?? $dCol['thai_text'] }}
                                @if($dCol['is_today'])
                                    <span class="ml-1 text-[9px] px-1.5 py-0.2 rounded-full {{ $isDefDate ? 'bg-white/25 text-white' : 'bg-sky-600 text-white' }}">วันนี้</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach($medications as $med)
                        @php
                            $hasStandardSlots = $med->take_morning || $med->take_noon || $med->take_evening || $med->take_bedtime;
                            $hasPrnOrCustom   = $med->take_prn || !empty($med->custom_slot_text) || !$hasStandardSlots || in_array($med->meal_timing, ['prn', 'external']);
                            $medStartStr      = $med->start_date ? $med->start_date->format('Y-m-d') : null;
                            $medEndStr        = $med->end_date ? $med->end_date->format('Y-m-d') : null;
                            $isContinuousMed  = empty($medEndStr);

                            $mobileSlots = [];
                            if ($med->take_morning) $mobileSlots['morning'] = ['label' => '🌅 เช้า',     'is_prn' => false];
                            if ($med->take_noon)    $mobileSlots['noon']    = ['label' => '☀️ เที่ยง',   'is_prn' => false];
                            if ($med->take_evening) $mobileSlots['evening'] = ['label' => '🌆 เย็น',     'is_prn' => false];
                            if ($med->take_bedtime) $mobileSlots['bedtime'] = ['label' => '🌙 ก่อนนอน',  'is_prn' => false];
                            if ($hasPrnOrCustom) {
                                $customLabel = !empty($med->custom_slot_text)
                                    ? '⚡ ' . $med->custom_slot_text
                                    : ($med->meal_timing === 'external' ? '🧴 ทา/นวดตามอาการ' : '⚡ เวลามีอาการ (PRN)');
                                $mobileSlots['prn'] = ['label' => $customLabel, 'is_prn' => true];
                            }

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
                                'start_date'       => $medStartStr ?: $startDateStr,
                                'end_date'         => $medEndStr ?: '',
                                'instruction_note' => $med->instruction_note ?? '',
                            ];
                        @endphp

                        <div class="rounded-2xl border-2 border-slate-200/90 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-black text-slate-900 dark:text-white text-base leading-snug">{{ $med->drug_name }}</div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <span class="px-2 py-0.5 bg-teal-50 text-teal-800 border border-teal-200 rounded-md font-bold text-[11px]">
                                            {{ $med->dosage ?: 'ตามแพทย์สั่ง' }}
                                        </span>
                                        <span class="px-2 py-0.5 {{ $med->meal_timing === 'external' ? 'bg-amber-100 text-amber-900 border-amber-300' : ($med->meal_timing === 'prn' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-indigo-50 text-indigo-800 border-indigo-200') }} border rounded-md font-bold text-[11px]">
                                            {{ $med->meal_timing_label }}
                                        </span>
                                        @if($isContinuousMed)
                                            <span class="px-2 py-0.5 bg-purple-50 text-purple-700 border border-purple-200 rounded-md font-bold text-[10px]">
                                                <i class="fa-solid fa-infinity mr-0.5"></i>ต่อเนื่องทุกวัน
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 rounded-md font-bold text-[10px]">
                                                ถึง {{ \Carbon\Carbon::parse($medEndStr)->addYears(543)->format('d/m/y') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button" onclick='openEditMedicationModal(@json($editMedPayload))'
                                            class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center" title="แก้ไขยา">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    <form id="delete-med-mobile-{{ $med->id }}" action="{{ route('backend.care_chart.destroy_medication', $med->id) }}" method="POST" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="button"
                                                onclick="confirmAction(event, 'ยืนยันการลบรายการยา?', 'ต้องการลบ {{ addslashes($med->drug_name) }} ออกจากตารางใช่หรือไม่?', 'warning', 'delete-med-mobile-{{ $med->id }}')"
                                                class="w-8 h-8 rounded-xl bg-rose-50 text-rose-500 border border-rose-200 flex items-center justify-center" title="ลบยา">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            @if($med->instruction_note)
                                <div class="text-xs text-rose-600 bg-rose-50/70 border border-rose-100 px-2.5 py-1.5 rounded-xl font-semibold">
                                    <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $med->instruction_note }}
                                </div>
                            @endif

                            @foreach($dateColumns as $dCol)
                                @php
                                    $isVisibleDay = ($dCol['date_str'] === $defaultMobileMarDate);
                                    $isOutsideMedRange = ($medStartStr && $dCol['date_str'] < $medStartStr) || ($medEndStr && $dCol['date_str'] > $medEndStr);
                                @endphp
                                <div data-mar-date-panel="{{ $dCol['date_str'] }}"
                                     class="{{ $isVisibleDay ? 'grid' : 'hidden' }} {{ count($mobileSlots) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 pt-2 border-t border-slate-100 dark:border-slate-700 {{ $isOutsideMedRange ? 'opacity-60' : '' }}">
                                    @foreach($mobileSlots as $slotKey => $slotInfo)
                                        @php
                                            $cellKey = $med->id . '_' . $dCol['date_str'] . '_' . $slotKey;
                                            $cellLog = $medLogs->get($cellKey);
                                            $st = $cellLog->status ?? 'none';

                                            $btnCls = $st === 'taken'
                                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                                                : ($st === 'missed'
                                                    ? 'bg-rose-600 text-white border-rose-600 shadow-sm'
                                                    : ($slotInfo['is_prn']
                                                        ? 'bg-amber-50/70 text-amber-900 border-amber-300'
                                                        : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600'));
                                            $stateLabel = $st === 'taken' ? '✓ ใช้แล้ว' : ($st === 'missed' ? '✕ ขาดยา' : 'แตะเช็ก');
                                        @endphp
                                        <button type="button"
                                                id="med-mobile-btn-{{ $cellKey }}"
                                                data-is-prn="{{ $slotInfo['is_prn'] ? '1' : '0' }}"
                                                onclick="toggleMedCell({{ $med->id }}, '{{ $dCol['date_str'] }}', '{{ $slotKey }}', '{{ $cellKey }}')"
                                                class="{{ $slotInfo['is_prn'] && count($mobileSlots) > 1 ? 'col-span-2' : '' }} p-2.5 rounded-xl border-2 font-bold text-xs flex items-center justify-between gap-2 transition-all active:scale-95 {{ $btnCls }}">
                                            <span class="truncate">{{ $slotInfo['label'] }}</span>
                                            <span class="js-mobile-state-label px-2 py-0.5 rounded-lg text-[10px] font-black shrink-0 {{ $st === 'none' ? 'bg-white/80 dark:bg-slate-800 text-slate-500' : 'bg-white/20 text-white' }}">
                                                {{ $stateLabel }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-10 px-4 text-center text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl text-xs">
                    ยังไม่มีรายการยาที่ต้องรับประทาน แตะปุ่ม <b>"+ เพิ่มรายการยาที่ต้องกิน / ทา"</b> ด้านบนได้เลยครับ
                </div>
            @endif
        </div>

        <!-- =========================================================== -->
        <!-- 💻 โหมดคอมพิวเตอร์ & โหมดพิมพ์ A4: ตาราง e-MAR ไดนามิก (10 / 14 / 30 วัน + Sticky Name) -->
        <!-- =========================================================== -->
        <div class="hidden md:block print:block overflow-x-auto">
            <table class="w-full text-xs text-center border-collapse border-2 border-slate-800">
                <thead>
                    <tr class="bg-slate-100 text-slate-900 font-black">
                        <th rowspan="2" class="border border-slate-800 px-3 py-2.5 min-w-[215px] {{ $isCompactMar ? 'sticky left-0 z-20 bg-slate-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.12)] print:static' : '' }}">
                            ชื่อยา / ปริมาณ / วิธีรับประทานหรือวิธีใช้
                        </th>
                        <th rowspan="2" class="border border-slate-800 px-2 py-2.5 min-w-[115px] w-32">เวลารับประทาน / ใช้ยา</th>
                        <th colspan="{{ count($dateColumns) }}" class="border border-slate-800 px-2 py-1.5">
                            วัน/เดือน/ปี (แสดง {{ $daysCount }} วัน)
                        </th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-800 font-bold text-[11px]">
                        @foreach($dateColumns as $dCol)
                            <th class="border border-slate-800 px-1 py-2 {{ $isCompactMar ? 'min-w-[46px]' : 'min-w-[74px]' }} {{ $dCol['is_today'] ? 'bg-sky-100 text-sky-900 ring-2 ring-inset ring-sky-500' : '' }}"
                                title="{{ $dCol['thai_text'] }}">
                                {{ $isCompactMar ? ($dCol['short_thai'] ?? $dCol['thai_text']) : $dCol['thai_text'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($medications as $med)
                        @php
                            $hasStandardSlots = $med->take_morning || $med->take_noon || $med->take_evening || $med->take_bedtime;
                            $hasPrnOrCustom   = $med->take_prn || !empty($med->custom_slot_text) || !$hasStandardSlots || in_array($med->meal_timing, ['prn', 'external']);
                            $medStartStr      = $med->start_date ? $med->start_date->format('Y-m-d') : null;
                            $medEndStr        = $med->end_date ? $med->end_date->format('Y-m-d') : null;
                            $isContinuousMed  = empty($medEndStr);

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
                                'start_date'       => $medStartStr ?: $startDateStr,
                                'end_date'         => $medEndStr ?: '',
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
                                    <td rowspan="{{ $totalSlotRows }}" class="border border-slate-800 p-3 text-left align-middle bg-white relative {{ $isCompactMar ? 'sticky left-0 z-10 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.12)] print:static' : '' }}">
                                        <div class="font-black text-slate-900 text-sm leading-snug">{{ $med->drug_name }}</div>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <span class="px-2 py-0.5 bg-teal-50 text-teal-800 border border-teal-200 rounded font-bold text-[11px]">
                                                {{ $med->dosage ?: 'ตามแพทย์สั่ง' }}
                                            </span>
                                            <span class="px-2 py-0.5 {{ $med->meal_timing === 'external' ? 'bg-amber-100 text-amber-900 border-amber-300' : ($med->meal_timing === 'prn' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-indigo-50 text-indigo-800 border-indigo-200') }} border rounded font-bold text-[11px]">
                                                {{ $med->meal_timing_label }}
                                            </span>
                                        </div>

                                        <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                            @if($isContinuousMed)
                                                <span class="px-2 py-0.5 bg-purple-50 text-purple-700 border border-purple-200 rounded text-[10px] font-bold">
                                                    <i class="fa-solid fa-infinity mr-0.5"></i>ให้ยาต่อเนื่องทุกวัน
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 rounded text-[10px] font-bold">
                                                    <i class="fa-regular fa-calendar mr-0.5"></i>{{ $medStartStr ? \Carbon\Carbon::parse($medStartStr)->addYears(543)->format('d/m/y') : '-' }} – {{ \Carbon\Carbon::parse($medEndStr)->addYears(543)->format('d/m/y') }}
                                                </span>
                                            @endif
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

                                            <form id="delete-med-desktop-{{ $med->id }}" action="{{ route('backend.care_chart.destroy_medication', $med->id) }}" method="POST" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="button"
                                                        onclick="confirmAction(event, 'ยืนยันการลบรายการยา?', 'ต้องการลบ {{ addslashes($med->drug_name) }} ออกจากตารางใช่หรือไม่?', 'warning', 'delete-med-desktop-{{ $med->id }}')"
                                                        class="text-[11px] text-rose-500 hover:underline font-bold flex items-center gap-1">
                                                    <i class="fa-solid fa-trash-can"></i> ลบ
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif

                                <td class="border border-slate-800 px-2.5 py-2 text-left font-bold {{ $isPrnRow ? 'text-amber-900 bg-amber-50/60 text-[11px]' : ($isPrescribedSlot ? 'text-slate-900 bg-white' : 'text-slate-300 bg-slate-50/70') }}">
                                    {{ $slotMeta['label'] }}
                                </td>

                                @foreach($dateColumns as $dCol)
                                    @php
                                        $cellKey = $med->id . '_' . $dCol['date_str'] . '_' . $slotKey;
                                        $cellLog = $medLogs->get($cellKey);
                                        $isOutsideRange = ($medStartStr && $dCol['date_str'] < $medStartStr) || ($medEndStr && $dCol['date_str'] > $medEndStr);
                                    @endphp
                                    @if($isPrescribedSlot)
                                        <td id="med-cell-{{ $cellKey }}"
                                            onclick="toggleMedCell({{ $med->id }}, '{{ $dCol['date_str'] }}', '{{ $slotKey }}', '{{ $cellKey }}')"
                                            class="border border-slate-800 p-1 cursor-pointer hover:bg-teal-50 transition-colors select-none {{ $dCol['is_today'] ? 'bg-sky-50/40' : ($isOutsideRange ? 'bg-slate-50/80 opacity-50' : 'bg-white') }}"
                                            title="{{ $dCol['thai_text'] }} — คลิกเพื่อบันทึก: กิน/ทายาแล้ว (✓) หรือ ขาดยา (✕){{ $isOutsideRange ? ' (อยู่นอกช่วงวันที่ตั้งไว้)' : '' }}">
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
                            <td colspan="{{ count($dateColumns) + 2 }}" class="border border-slate-800 py-10 text-center text-slate-400">
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
    window._hosxpDrugsCache = [];

    function escapeMarHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function submitMarPostForm(actionUrl, fields, arrayFields = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = actionUrl;

        const appendInput = (name, val) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = val ?? '';
            form.appendChild(input);
        };

        appendInput('_token', "{{ csrf_token() }}");
        Object.entries(fields).forEach(([k, v]) => appendInput(k, v));
        Object.entries(arrayFields).forEach(([k, arr]) => {
            (arr || []).forEach(v => appendInput(k, v));
        });

        document.body.appendChild(form);
        form.submit();
    }

    // 🧮 คำนวณคะแนน MEWS สดในหน้าต่างกรอกสัญญาณชีพ
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
            title: `<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-heart-pulse text-rose-500 mr-2"></i>บันทึกสัญญาณชีพ (เวลา ${escapeMarHtml(roundTime)} น.)</div>`,
            html: `
                <div class="text-left space-y-3 mt-2">
                    <div class="flex items-center justify-between bg-slate-50 p-3 rounded-2xl border border-slate-200">
                        <div class="text-xs font-bold text-slate-700">วันที่: <span class="text-indigo-700 font-black">${escapeMarHtml(dateStr)}</span> (${escapeMarHtml(roundTime)} น.)</div>
                        <span id="mv_mews_preview" class="px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800">Mew score: 0 คะแนน</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">1. อุณหภูมิ (°C) *</label>
                            <input type="number" step="0.1" id="mv_temp" oninput="calcLiveModalMews()" value="${escapeMarHtml(existingLog?.temperature ?? '36.6')}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">2. ชีพจร (/นาที) *</label>
                            <input type="number" id="mv_hr" oninput="calcLiveModalMews()" value="${escapeMarHtml(existingLog?.heart_rate ?? '80')}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">3. หายใจ (/นาที) *</label>
                            <input type="number" id="mv_rr" oninput="calcLiveModalMews()" value="${escapeMarHtml(existingLog?.respiratory_rate ?? '18')}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">4. ความดันตัวบน (SBP) *</label>
                            <input type="number" id="mv_sbp" oninput="calcLiveModalMews()" value="${escapeMarHtml(existingLog?.systolic_bp ?? '120')}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">5. ความดันตัวล่าง (DBP) *</label>
                            <input type="number" id="mv_dbp" value="${escapeMarHtml(existingLog?.diastolic_bp ?? '80')}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">6. ออกซิเจนปลายนิ้ว (%) *</label>
                            <input type="number" id="mv_o2" oninput="calcLiveModalMews()" value="${escapeMarHtml(existingLog?.o2sat ?? '98')}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-black">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">หมายเหตุอาการ (ถ้ามี)</label>
                        <input type="text" id="mv_note" value="${escapeMarHtml(existingLog?.note ?? '')}" placeholder="เช่น ไม่มีไข้ อาการปกติ..." class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-xs sm:text-sm">
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
                submitMarPostForm("{{ route('backend.care_chart.store_vital') }}", {
                    patient_id: "{{ $patient->id }}",
                    record_date: dateStr,
                    round_time: roundTime,
                    ...res.value
                });
            }
        });
    }

    // 🛠️ ตัวช่วยตั้งค่าระยะเวลาให้ยา (ต่อเนื่องทุกวัน vs กำหนดจำนวนวัน +7/+10/+14/+30)
    window.toggleMedContinuousMode = function(isChecked) {
        const endDateInput = document.getElementById('med_end_date');
        if (!endDateInput) return;
        if (isChecked) {
            endDateInput.value = '';
            endDateInput.disabled = true;
            endDateInput.classList.add('opacity-50', 'bg-slate-100');
        } else {
            endDateInput.disabled = false;
            endDateInput.classList.remove('opacity-50', 'bg-slate-100');
        }
    };

    window.applyMedDurationDays = function(addDays) {
        const startEl = document.getElementById('med_start_date');
        const endEl   = document.getElementById('med_end_date');
        const contCb  = document.getElementById('med_is_continuous');
        if (!startEl || !endEl) return;

        const base = startEl.value ? new Date(startEl.value + 'T00:00:00') : new Date();
        base.setDate(base.getDate() + Math.max(0, addDays - 1));
        const yyyy = base.getFullYear();
        const mm   = String(base.getMonth() + 1).padStart(2, '0');
        const dd   = String(base.getDate()).padStart(2, '0');

        if (contCb) contCb.checked = false;
        window.toggleMedContinuousMode(false);
        endEl.value = `${yyyy}-${mm}-${dd}`;
    };

    window.onSwalCareGroupChange = function(val) {
        const contCb = document.getElementById('med_is_continuous');
        if ((val === 'psychiatric' || val === 'chronic') && contCb) {
            contCb.checked = true;
            window.toggleMedContinuousMode(true);
        }
    };

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

    // 💊 เปิดป๊อปอัปเพิ่มหรือแก้ไขรายการยา (รองรับระยะเวลาต่อเนื่องทุกวัน + ปุ่มกำหนดจำนวนวัน)
    function openAddMedicationModal(prefillDrugName = '', prefillDosage = '', editData = null) {
        const isEdit       = !!editData;
        const careGroup    = editData ? editData.care_group : 'ci_isolation';
        const drugName     = editData ? editData.drug_name : prefillDrugName;
        const dosage       = editData ? editData.dosage : (prefillDosage || 'ครั้งละ 1 เม็ด');
        const mealTiming   = editData ? editData.meal_timing : 'after_meal';
        const chkM         = editData ? editData.take_morning : true;
        const chkN         = editData ? editData.take_noon : true;
        const chkE         = editData ? editData.take_evening : true;
        const chkB         = editData ? editData.take_bedtime : false;
        const chkPrn       = editData ? editData.take_prn : false;
        const customSlot   = editData ? editData.custom_slot_text : '';
        const startDate    = editData ? editData.start_date : '{{ $startDateStr }}';
        const endDate      = editData ? (editData.end_date || '') : '';
        const isContinuous = !endDate;
        const noteText     = editData ? editData.instruction_note : '';

        Swal.fire({
            title: `<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-pills text-teal-600 mr-2"></i>${isEdit ? 'แก้ไขรายการยา / วิธีใช้ยา' : 'เพิ่มรายการยาที่ต้องรับประทาน / ยาใช้ภายนอก'}</div>`,
            html: `
                <div class="text-left space-y-3.5 mt-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">กลุ่มผู้ป่วยที่ต้องติดตามการใช้ยา <span class="text-rose-500">*</span></label>
                        <select id="med_care_group" onchange="window.onSwalCareGroupChange(this.value)" class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 p-2.5 text-xs sm:text-sm font-bold text-slate-800 outline-none">
                            <option value="ci_isolation" ${careGroup === 'ci_isolation' ? 'selected' : ''}>🏕️ 1. ผู้ป่วยแยกกัก CI / ห้องพยาบาลหน่วยฝึก (โควิด/ทางเดินหายใจ/อื่นๆ)</option>
                            <option value="psychiatric" ${careGroup === 'psychiatric' ? 'selected' : ''}>🧠 2. ผู้ป่วยโรคจิตเวช (ต้องรับประทานยาต่อเนื่องทุกวัน)</option>
                            <option value="chronic" ${careGroup === 'chronic' ? 'selected' : ''}>💊 3. ผู้ป่วยโรคประจำตัวเรื้อรังอื่นๆ (เช่น หอบหืด, ลมชัก, ความดัน)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">ชื่อยา (Drug Name) <span class="text-rose-500">*</span></label>
                        <div class="flex flex-wrap gap-1 mb-1.5">
                            <button type="button" onclick="window.applyQuickDrugPreset('Paracetamol 500 mg.', 'ครั้งละ 1 เม็ด', 'prn', false, true, 'กินเวลามีไข้หรือปวด ทุก 4-6 ชม.')" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-700 rounded text-[10px] font-bold border border-slate-200">+ Paracetamol (PRN)</button>
                            <button type="button" onclick="window.applyQuickDrugPreset('ยาละลายเสมหะ (Bromhexine)', 'ครั้งละ 1 เม็ด', 'after_meal', true, false, '')" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-700 rounded text-[10px] font-bold border border-slate-200">+ ละลายเสมหะ</button>
                            <button type="button" onclick="window.applyQuickDrugPreset('ยาแก้แพ้ ลดน้ำมูก (CPM)', 'ครั้งละ 1 เม็ด', 'after_meal', true, false, '')" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-700 rounded text-[10px] font-bold border border-slate-200">+ ลดน้ำมูก (CPM)</button>
                            <button type="button" onclick="window.applyQuickDrugPreset('ยานวดคลายกล้ามเนื้อ (Balm)', 'ทาบางๆ บริเวณที่ปวด', 'external', false, true, 'ทา/นวดบริเวณที่ปวดเมื่อย เช้า-เย็น หรือเวลามีอาการ')" class="px-2 py-0.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded text-[10px] font-bold border border-amber-200">🧴 + ยานวด</button>
                            <button type="button" onclick="window.applyQuickDrugPreset('ยาทาผื่นคัน / ยาทาแผล', 'ทาบางๆ บริเวณที่เป็น', 'external', false, true, 'ทาบริเวณแผล/ผื่นคัน เช้า-เย็น')" class="px-2 py-0.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded text-[10px] font-bold border border-amber-200">🧴 + ยาทา</button>
                        </div>
                        <input type="text" id="med_drug_name" value="${escapeMarHtml(drugName)}" placeholder="เช่น Sertraline 50 mg., Paracetamol 500 mg., ยานวด..." class="w-full px-3.5 py-2.5 border-2 border-slate-300 rounded-xl text-sm font-bold text-slate-800 outline-none">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ปริมาณยา / ขนาดที่ใช้</label>
                            <input type="text" id="med_dosage" value="${escapeMarHtml(dosage)}" placeholder="เช่น ครั้งละ 1 เม็ด, ทาบางๆ..." class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-bold text-slate-800 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">การรับประทาน / วิธีใช้ <span class="text-rose-500">*</span></label>
                            <select id="med_meal_timing" onchange="window.onSwalMealTimingChange(this.value)" class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 p-2 text-sm font-bold text-slate-800 outline-none">
                                <option value="after_meal" ${mealTiming === 'after_meal' ? 'selected' : ''}>🍽️ หลังอาหาร</option>
                                <option value="before_meal" ${mealTiming === 'before_meal' ? 'selected' : ''}>⏳ ก่อนอาหาร</option>
                                <option value="with_meal" ${mealTiming === 'with_meal' ? 'selected' : ''}>🥗 พร้อมอาหาร</option>
                                <option value="bedtime_only" ${mealTiming === 'bedtime_only' ? 'selected' : ''}>🌙 ก่อนนอนอย่างเดียว</option>
                                <option value="prn" ${mealTiming === 'prn' ? 'selected' : ''}>🚨 เวลามีอาการ (PRN)</option>
                                <option value="external" ${mealTiming === 'external' ? 'selected' : ''}>🧴 ยาทาภายนอก / ยานวด</option>
                            </select>
                        </div>
                    </div>

                    <!-- โซนเลือกมื้อยา -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-1">
                            <label class="text-xs font-bold text-slate-700">
                                เวลาใช้ยา <span class="text-teal-600 font-semibold">(เลือกมื้อปกติ หรือเวลามีอาการ)</span>
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
                                <span>⚡ มีอาการ</span>
                            </label>
                        </div>

                        <div class="pt-1">
                            <input type="text" id="med_custom_slot_text" value="${escapeMarHtml(customSlot)}"
                                   placeholder="ระบุเวลา / วิธีใช้เพิ่มเติม (ถ้ามี) เช่น กินเวลามีไข้, ทานวดเวลาปวด..."
                                   class="w-full px-3 py-2 bg-white border border-slate-300 focus:border-teal-600 rounded-xl text-xs font-semibold text-slate-800 outline-none">
                        </div>
                    </div>

                    <!-- 🗓️ โซนกำหนดวันเริ่มและระยะเวลาให้ยา (รองรับต่อเนื่องทุกวันไม่จำกัด) -->
                    <div class="p-3.5 rounded-2xl bg-purple-50/50 border border-purple-200/80 space-y-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-black text-purple-900">
                                <input type="checkbox" id="med_is_continuous" ${isContinuous ? 'checked' : ''} onchange="window.toggleMedContinuousMode(this.checked)" class="w-4 h-4 text-purple-600 rounded">
                                <span><i class="fa-solid fa-infinity mr-1"></i>ให้ยาต่อเนื่องทุกวัน (ไม่กำหนดวันสิ้นสุด — จิตเวช/เรื้อรัง)</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">วันที่เริ่มให้ยา <span class="text-rose-500">*</span></label>
                                <input type="date" id="med_start_date" value="${escapeMarHtml(startDate)}" class="w-full px-3 py-2 bg-white border-2 border-slate-300 rounded-xl text-xs font-bold">
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-bold text-slate-700">วันที่สิ้นสุด (ถ้ามี)</label>
                                    <div class="flex items-center gap-1">
                                        <button type="button" onclick="window.applyMedDurationDays(7)" class="px-1.5 py-0.5 bg-white hover:bg-purple-100 border border-purple-200 rounded text-[10px] font-bold text-purple-700">+7วัน</button>
                                        <button type="button" onclick="window.applyMedDurationDays(14)" class="px-1.5 py-0.5 bg-white hover:bg-purple-100 border border-purple-200 rounded text-[10px] font-bold text-purple-700">+14วัน</button>
                                        <button type="button" onclick="window.applyMedDurationDays(30)" class="px-1.5 py-0.5 bg-white hover:bg-purple-100 border border-purple-200 rounded text-[10px] font-bold text-purple-700">+30วัน</button>
                                    </div>
                                </div>
                                <input type="date" id="med_end_date" value="${escapeMarHtml(endDate)}" ${isContinuous ? 'disabled' : ''} class="w-full px-3 py-2 bg-white border-2 border-slate-300 rounded-xl text-xs font-bold ${isContinuous ? 'opacity-50 bg-slate-100' : ''}">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">ข้อควรระวัง / หมายเหตุให้เสนารักษ์</label>
                        <input type="text" id="med_note" value="${escapeMarHtml(noteText)}" placeholder="เช่น ห้ามขาดยา, เฝ้าระวังอาการง่วงซึม..." class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-xs">
                    </div>
                </div>
            `,
            width: '42rem',
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
                const sDate      = document.getElementById('med_start_date').value;
                const isCont     = document.getElementById('med_is_continuous')?.checked ? 1 : 0;
                const eDate      = isCont ? '' : (document.getElementById('med_end_date')?.value || '');
                let checkedSlots = Array.from(document.querySelectorAll('input[name="swal_med_slots"]:checked')).map(cb => cb.value);

                if (!dName) {
                    Swal.showValidationMessage('กรุณาระบุชื่อยา (Drug Name) ครับ');
                    return false;
                }
                if (!isCont && eDate && sDate && eDate < sDate) {
                    Swal.showValidationMessage('วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มให้ยาครับ');
                    return false;
                }
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
                    start_date: sDate,
                    end_date: eDate,
                    is_continuous: isCont,
                    instruction_note: document.getElementById('med_note').value.trim()
                };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const { slots, ...scalarFields } = res.value;
                submitMarPostForm("{{ route('backend.care_chart.store_medication') }}", {
                    patient_id: "{{ $patient->id }}",
                    ...scalarFields
                }, { 'slots[]': slots });
            }
        });
    }

    function openEditMedicationModal(medObj) {
        openAddMedicationModal('', '', medObj);
    }

    // 📅 สลับวันที่ในโหมดการ์ดมือถือ e-MAR
    function selectMobileMarDate(dateStr, clickedBtn) {
        document.querySelectorAll('[data-mar-date-btn]').forEach(btn => {
            const isSelected = btn.getAttribute('data-mar-date-btn') === dateStr;
            const hasTodayPill = btn.querySelector('span');
            if (isSelected) {
                btn.className = 'px-3 py-2 rounded-xl text-xs font-black whitespace-nowrap border transition-all shrink-0 bg-teal-600 text-white border-teal-600 shadow-sm';
                if (hasTodayPill) hasTodayPill.className = 'ml-1 text-[9px] px-1.5 py-0.2 rounded-full bg-white/25 text-white';
            } else {
                btn.className = hasTodayPill
                    ? 'px-3 py-2 rounded-xl text-xs font-black whitespace-nowrap border transition-all shrink-0 bg-sky-50 text-sky-700 border-sky-300'
                    : 'px-3 py-2 rounded-xl text-xs font-black whitespace-nowrap border transition-all shrink-0 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                if (hasTodayPill) hasTodayPill.className = 'ml-1 text-[9px] px-1.5 py-0.2 rounded-full bg-sky-600 text-white';
            }
        });

        document.querySelectorAll('[data-mar-date-panel]').forEach(panel => {
            const match = panel.getAttribute('data-mar-date-panel') === dateStr;
            panel.classList.toggle('hidden', !match);
            panel.classList.toggle('grid', match);
        });

        if (clickedBtn) {
            clickedBtn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const activeDateBtn = document.querySelector('[data-mar-date-btn="{{ $defaultMobileMarDate }}"]');
        if (activeDateBtn && window.innerWidth < 768) {
            activeDateBtn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
    });

    // ✅ คลิกสลับสถานะกิน/ทายา
    function toggleMedCell(medId, logDate, timeSlot, cellKey) {
        const desktopCell = document.getElementById('med-cell-' + cellKey);
        const mobileBtn   = document.getElementById('med-mobile-btn-' + cellKey);
        if (mobileBtn) mobileBtn.disabled = true;

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
            if (res.status !== 'success') return;

            if (desktopCell) {
                if (res.state === 'taken') {
                    desktopCell.innerHTML = `<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white font-black text-xs shadow-sm">✓</span>`;
                } else if (res.state === 'missed') {
                    desktopCell.innerHTML = `<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-500 text-white font-black text-xs shadow-sm">✕</span>`;
                } else {
                    desktopCell.innerHTML = `<span class="text-slate-200 text-[10px] print:hidden">•</span>`;
                }
            }

            if (mobileBtn) {
                const isPrn = mobileBtn.getAttribute('data-is-prn') === '1';
                const colSpanCls = mobileBtn.classList.contains('col-span-2') ? 'col-span-2 ' : '';
                const stateBadge = mobileBtn.querySelector('.js-mobile-state-label');

                if (res.state === 'taken') {
                    mobileBtn.className = `${colSpanCls}p-2.5 rounded-xl border-2 font-bold text-xs flex items-center justify-between gap-2 transition-all active:scale-95 bg-emerald-600 text-white border-emerald-600 shadow-sm`;
                    if (stateBadge) {
                        stateBadge.className = 'js-mobile-state-label px-2 py-0.5 rounded-lg text-[10px] font-black shrink-0 bg-white/20 text-white';
                        stateBadge.innerText = '✓ ใช้แล้ว';
                    }
                } else if (res.state === 'missed') {
                    mobileBtn.className = `${colSpanCls}p-2.5 rounded-xl border-2 font-bold text-xs flex items-center justify-between gap-2 transition-all active:scale-95 bg-rose-600 text-white border-rose-600 shadow-sm`;
                    if (stateBadge) {
                        stateBadge.className = 'js-mobile-state-label px-2 py-0.5 rounded-lg text-[10px] font-black shrink-0 bg-white/20 text-white';
                        stateBadge.innerText = '✕ ขาดยา';
                    }
                } else {
                    const neutralCls = isPrn
                        ? 'bg-amber-50/70 text-amber-900 border-amber-300'
                        : 'bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600';
                    mobileBtn.className = `${colSpanCls}p-2.5 rounded-xl border-2 font-bold text-xs flex items-center justify-between gap-2 transition-all active:scale-95 ${neutralCls}`;
                    if (stateBadge) {
                        stateBadge.className = 'js-mobile-state-label px-2 py-0.5 rounded-lg text-[10px] font-black shrink-0 bg-white/80 dark:bg-slate-800 text-slate-500';
                        stateBadge.innerText = 'แตะเช็ก';
                    }
                }
            }
        })
        .finally(() => {
            if (mobileBtn) mobileBtn.disabled = false;
        });
    }

    // 🏥 ดึงรายการยาล่าสุดจาก HosXP
    function selectHosxpDrugByIndex(idx) {
        const drug = window._hosxpDrugsCache[idx];
        if (!drug) return;
        Swal.close();
        openAddMedicationModal(drug.drug_name || '', drug.usage || '');
    }

    function fetchDrugsFromHosxp() {
        Swal.fire({ title: 'กำลังดึงรายการยาล่าสุดจาก HosXP...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch("{{ route('backend.care_chart.hosxp_drugs', $patient->id) }}")
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success' || !res.data || res.data.length === 0) {
                    Swal.fire({ icon: 'info', title: 'แจ้งเตือน', text: res.message || 'ไม่พบรายการยา' });
                    return;
                }

                window._hosxpDrugsCache = res.data;
                let listHtml = '<div class="text-left space-y-2 mt-2 max-h-64 overflow-y-auto">';
                res.data.forEach((d, idx) => {
                    listHtml += `
                        <div class="p-3 bg-slate-50 hover:bg-teal-50 rounded-xl border border-slate-200 flex items-center justify-between gap-2 cursor-pointer"
                             onclick="selectHosxpDrugByIndex(${idx})">
                            <div class="min-w-0">
                                <div class="font-black text-slate-800 text-sm">${escapeMarHtml(d.drug_name)}</div>
                                <div class="text-xs text-slate-500">${escapeMarHtml(d.usage)}</div>
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
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'เชื่อมต่อไม่สำเร็จ', text: 'ไม่สามารถดึงรายการยาจาก HosXP ได้ในขณะนี้' });
            });
    }
</script>
@endsection