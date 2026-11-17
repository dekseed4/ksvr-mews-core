@extends('layouts.backend')

@section('title', 'ห้องพยาบาลหน่วยฝึก / แยกโรงนอน - KSVR Hospital')
@section('page_header', 'ระบบจัดการนอนห้องพยาบาลหน่วยฝึก & แยกโรงนอน')

@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-12">

    @php
        $reasonMeta = [
            'respiratory'     => ['label' => 'โรคระบบทางเดินหายใจ / โรคติดต่อ', 'icon' => 'fa-head-side-mask text-rose-500', 'badge' => 'bg-rose-50 text-rose-700 border-rose-200'],
            'post_admit'      => ['label' => 'พักฟื้นต่อเนื่องหลังออกจาก รพ.', 'icon' => 'fa-bed-pulse text-amber-500', 'badge' => 'bg-amber-50 text-amber-800 border-amber-200'],
            'musculoskeletal' => ['label' => 'บาดเจ็บกล้ามเนื้อ / กระดูก / แผล', 'icon' => 'fa-crutch text-indigo-500', 'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
            'other'           => ['label' => 'สาเหตุอื่นๆ (สังเกตอาการ)', 'icon' => 'fa-notes-medical text-teal-500', 'badge' => 'bg-teal-50 text-teal-700 border-teal-200'],
        ];
    @endphp

    <!-- 📊 1. การ์ดสรุป KPI 4 มิติ (คลิกเพื่อกรองตารางได้ทันที) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- ยอดกำลังแยกนอนทั้งหมด -->
        <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $selectedBatch, 'unit_id' => $selectedUnit, 'status' => 'staying']) }}"
           class="bg-white p-5 rounded-3xl shadow-sm border-2 {{ ($selectedStatus === 'staying' && empty($selectedLocation) && empty($selectedReason)) ? 'border-teal-500 ring-2 ring-teal-100' : 'border-slate-100 hover:border-teal-300' }} flex items-center justify-between transition-all">
            <div>
                <span class="text-xs font-bold text-slate-400 uppercase">🛌 กำลังแยกนอนทั้งหมด</span>
                <h4 class="text-3xl font-black text-slate-800 mt-1">{{ number_format($summary['active_total']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h4>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl"><i class="fa-solid fa-bed"></i></div>
        </a>

        <!-- นอนห้องพยาบาลหน่วยฝึก (แยกโรงนอนที่หน่วย) -->
        <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $selectedBatch, 'unit_id' => $selectedUnit, 'location_type' => 'unit_infirmary', 'status' => 'staying']) }}"
           class="bg-white p-5 rounded-3xl shadow-sm border-2 {{ $selectedLocation === 'unit_infirmary' ? 'border-indigo-500 ring-2 ring-indigo-100' : 'border-slate-100 hover:border-indigo-300' }} flex items-center justify-between transition-all">
            <div>
                <span class="text-xs font-bold text-indigo-600 uppercase">🏕️ ห้องพยาบาลหน่วยฝึก</span>
                <h4 class="text-3xl font-black text-indigo-600 mt-1">{{ number_format($summary['unit_infirmary']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h4>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl"><i class="fa-solid fa-tent-arrows-down"></i></div>
        </a>

        <!-- ฝากนอนแยกกักที่ รพ.ค่ายฯ -->
        <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $selectedBatch, 'unit_id' => $selectedUnit, 'location_type' => 'camp_hospital', 'status' => 'staying']) }}"
           class="bg-white p-5 rounded-3xl shadow-sm border-2 {{ $selectedLocation === 'camp_hospital' ? 'border-amber-500 ring-2 ring-amber-100' : 'border-slate-100 hover:border-amber-300' }} flex items-center justify-between transition-all">
            <div>
                <span class="text-xs font-bold text-amber-600 uppercase">🏥 ฝากนอนที่ รพ.ค่ายฯ</span>
                <h4 class="text-3xl font-black text-amber-600 mt-1">{{ number_format($summary['camp_hospital']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h4>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl"><i class="fa-solid fa-hospital-user"></i></div>
        </a>

        <!-- แยกนอนด้วยโรคทางเดินหายใจ -->
        <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $selectedBatch, 'unit_id' => $selectedUnit, 'reason_category' => 'respiratory', 'status' => 'staying']) }}"
           class="bg-white p-5 rounded-3xl shadow-sm border-2 {{ $selectedReason === 'respiratory' ? 'border-rose-500 ring-2 ring-rose-100' : 'border-slate-100 hover:border-rose-300' }} flex items-center justify-between transition-all">
            <div>
                <span class="text-xs font-bold text-rose-600 uppercase">🦠 โรคทางเดินหายใจ (CI)</span>
                <h4 class="text-3xl font-black text-rose-600 mt-1">{{ number_format($summary['respiratory']) }} <span class="text-sm font-medium text-slate-400">นาย</span></h4>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl"><i class="fa-solid fa-head-side-mask"></i></div>
        </a>
    </div>

    <!-- 🛠️ 2. แถบเครื่องมือตัวกรอง + เลือกจำนวน 10/20 รายชื่อ + ปุ่มเพิ่มผู้ป่วยแยกโรงนอน -->
    <div class="bg-white p-4 md:p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-4">
        <form method="GET" action="{{ route('backend.recruit_infirmary.index') }}" class="flex flex-wrap items-center gap-2.5">
            <select name="intake_batch" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-teal-500 cursor-pointer">
                <option value="all" {{ $selectedBatch == 'all' ? 'selected' : '' }}>ทุกผลัดทหาร</option>
                @foreach($batches as $b)
                    <option value="{{ $b }}" {{ $selectedBatch == $b ? 'selected' : '' }}>ผลัดที่ {{ $b }}</option>
                @endforeach
            </select>

            <select name="unit_id" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-teal-500 cursor-pointer">
                <option value="">ทุกหน่วยฝึก</option>
                @foreach($units as $u)
                    <option value="{{ $u->id }}" {{ $selectedUnit == $u->id ? 'selected' : '' }}>{{ $u->short_name ?? $u->name }}</option>
                @endforeach
            </select>

            <select name="location_type" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-teal-500 cursor-pointer">
                <option value="">ทุกสถานที่แยกนอน</option>
                <option value="unit_infirmary" {{ $selectedLocation === 'unit_infirmary' ? 'selected' : '' }}>🏕️ ห้องพยาบาลหน่วยฝึก (แยกโรงนอน)</option>
                <option value="camp_hospital" {{ $selectedLocation === 'camp_hospital' ? 'selected' : '' }}>🏥 ฝากนอนแยกกักที่ รพ.ค่ายฯ</option>
            </select>

            <select name="reason_category" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-teal-500 cursor-pointer">
                <option value="">ทุกสาเหตุการแยกนอน</option>
                <option value="respiratory" {{ $selectedReason === 'respiratory' ? 'selected' : '' }}>🦠 โรคระบบทางเดินหายใจ</option>
                <option value="post_admit" {{ $selectedReason === 'post_admit' ? 'selected' : '' }}>🛌 พักฟื้นหลังออกจาก รพ.</option>
                <option value="musculoskeletal" {{ $selectedReason === 'musculoskeletal' ? 'selected' : '' }}>🩹 บาดเจ็บกล้ามเนื้อ/แผล</option>
                <option value="other" {{ $selectedReason === 'other' ? 'selected' : '' }}>📌 สาเหตุอื่นๆ</option>
            </select>

            <select name="status" onchange="this.form.submit()" class="rounded-2xl border border-slate-200 bg-slate-50 hover:bg-white text-xs sm:text-sm font-bold text-slate-700 px-3.5 py-2.5 outline-none focus:border-teal-500 cursor-pointer">
                <option value="staying" {{ $selectedStatus === 'staying' ? 'selected' : '' }}>🛌 กำลังแยกนอนอยู่ปัจจุบัน</option>
                <option value="returned" {{ $selectedStatus === 'returned' ? 'selected' : '' }}>✅ กลับโรงนอนปกติแล้ว ({{ $summary['returned_total'] }})</option>
                <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>📋 ประวัติทั้งหมด</option>
            </select>

            <div class="flex items-center bg-slate-50 border border-slate-200 rounded-2xl px-3 py-1.5">
                <span class="text-xs font-bold text-slate-400 mr-2">แสดง:</span>
                <select name="per_page" onchange="this.form.submit()" class="bg-transparent text-xs sm:text-sm font-black text-teal-600 outline-none cursor-pointer pr-1">
                    @foreach([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" {{ ($perPage ?? 10) == $size ? 'selected' : '' }}>{{ $size }} รายชื่อ</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="flex items-center gap-2 shrink-0">
            <button type="button" onclick="openAddInfirmaryModal()"
                    class="w-full sm:w-auto justify-center px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-2xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 shadow-sm whitespace-nowrap">
                <i class="fa-solid fa-bed-pulse"></i> + บันทึกแยกโรงนอน / ห้องพยาบาล
            </button>
        </div>
    </div>

    <!-- 📋 3. ตารางรายชื่อพลทหารแยกโรงนอน / ห้องพยาบาลหน่วยฝึก (จัดสัดส่วนคอลัมน์ใหม่ให้สมดุล สวยงาม ไม่เบียดกัน) -->
    <div>
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2 mb-3 px-1">
            <h3 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-house-medical-flag text-teal-600"></i> ทะเบียนพลทหารนอนห้องพยาบาลหน่วยฝึก & ฝากนอน รพ.
                <span class="text-xs font-bold bg-teal-50 text-teal-700 border border-teal-200 px-2.5 py-1 rounded-full">
                    พบ {{ number_format($stays->total()) }} รายการ
                </span>
            </h3>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-slate-600">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50/90 border-b border-slate-100">
                        <tr>
                            <th class="px-4 py-3.5 font-bold whitespace-nowrap w-[135px]">วัน-เวลาที่เริ่มพัก</th>
                            <th class="px-4 py-3.5 font-bold whitespace-nowrap min-w-[185px]">ข้อมูลพลทหาร</th>
                            <th class="px-4 py-3.5 font-bold whitespace-nowrap min-w-[165px]">สถานที่แยกนอน / เตียง</th>
                            <th class="px-4 py-3.5 font-bold min-w-[260px]">สาเหตุ / อาการ (Dx.) / การดูแล</th>
                            <th class="px-4 py-3.5 font-bold text-center whitespace-nowrap w-[175px]">สถานะ & วัดไข้วันนี้</th>
                            <th class="px-4 py-3.5 font-bold text-center whitespace-nowrap w-[230px]">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $currentBatchGroup = null;
                            $currentUnitGroup  = null;
                        @endphp

                        @forelse($stays as $stay)
                            @php
                                $pt = $stay->patient;
                                $rowBatch = $stay->intake_batch ?? $pt->intake_batch ?? 'ไม่ระบุ';
                                $rowUnitName = $stay->militaryUnit->name ?? $pt->militaryUnit->name ?? $pt->company ?? 'ไม่ระบุหน่วยฝึก';
                                $isUnitInf = ($stay->location_type === 'unit_infirmary');
                                $rMeta = $reasonMeta[$stay->reason_category] ?? $reasonMeta['other'];

                                // คำนวณจำนวนวันนอนตามวันปฏิทินและบังคับเป็นเลขจำนวนเต็ม (int)
                                $endRefDate = ($stay->discharged_at ?? now())->copy()->startOfDay();
                                $startRefDate = $stay->admitted_at->copy()->startOfDay();
                                $daysStay = max(1, (int) $startRefDate->diffInDays($endRefDate) + 1);

                                $ptTodayVitals = isset($todayVitalsByPatient) ? ($todayVitalsByPatient->get($pt->id) ?? collect()) : collect();
                                $hasMorningVs  = $ptTodayVitals->firstWhere('round_time', '06:00');
                                $hasEveningVs  = $ptTodayVitals->firstWhere('round_time', '18:00');
                                $ptMedCount    = isset($activeMedsCountByPatient) ? ($activeMedsCountByPatient[$pt->id] ?? 0) : 0;

                                $editPayload = [
                                    'id'               => $stay->id,
                                    'update_url'       => route('backend.recruit_infirmary.update', $stay->id),
                                    'patient_id'       => $pt->id ?? null,
                                    'soldier_name'     => ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? ''),
                                    'hn'               => $pt->hn ?: 'ไม่มี HN',
                                    'unit_name'        => $stay->militaryUnit->short_name ?? $pt->militaryUnit->short_name ?? $rowUnitName,
                                    'location_type'    => $stay->location_type,
                                    'bed_or_zone'      => $stay->bed_or_zone ?? '',
                                    'reason_category'  => $stay->reason_category,
                                    'diagnosis_text'   => $stay->diagnosis_text ?? '',
                                    'care_instruction' => $stay->care_instruction ?? '',
                                    'admitted_date'    => $stay->admitted_at->format('Y-m-d'),
                                    'admitted_time'    => $stay->admitted_at->format('H:i'),
                                ];
                            @endphp

                            @if($currentBatchGroup !== $rowBatch)
                                @php
                                    $currentBatchGroup = $rowBatch;
                                    $currentUnitGroup  = null;
                                @endphp
                                <tr class="bg-slate-800">
                                    <td colspan="6" class="px-5 py-2.5">
                                        <div class="flex items-center gap-2 text-white font-bold text-sm">
                                            <i class="fa-solid fa-calendar-days text-teal-400"></i> ทหารกองประจำการ ผลัดที่ {{ $currentBatchGroup }}
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            @if($currentUnitGroup !== $rowUnitName)
                                @php $currentUnitGroup = $rowUnitName; @endphp
                                <tr class="bg-teal-50/70">
                                    <td colspan="6" class="px-5 py-2 border-y border-teal-100">
                                        <div class="flex items-center gap-2 text-teal-800 font-bold text-xs uppercase tracking-wide">
                                            <i class="fa-solid fa-tent text-teal-600"></i> {{ $currentUnitGroup }}
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            <tr class="hover:bg-slate-50/80 transition-colors align-middle {{ $stay->status === 'returned' ? 'opacity-75 bg-slate-50/40' : '' }}">
                                
                                <!-- 1. วัน-เวลาที่เริ่มพัก -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-black text-slate-800 text-sm">
                                        {{ $stay->admitted_at->locale('th')->addYears(543)->translatedFormat('j M Y') }}
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                                        <span><i class="fa-regular fa-clock text-teal-500 mr-1"></i>{{ $stay->admitted_at->format('H:i') }} น.</span>
                                        <span class="px-2 py-0.5 bg-teal-50 text-teal-700 border border-teal-200 rounded-md font-black text-[11px]">
                                            {{ number_format($daysStay) }} วัน
                                        </span>
                                    </div>
                                </td>

                                <!-- 2. ข้อมูลพลทหาร (ชื่อไม่ตัดคำกลางคัน + ป้าย HN/ปชช. กระชับ) -->
                                <td class="px-4 py-3.5">
                                    <div class="font-black text-slate-900 text-sm sm:text-base whitespace-nowrap">
                                        {{ $pt->rank ?? '' }}{{ $pt->first_name ?? '' }} {{ $pt->last_name ?? '' }}
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                                        <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-md font-bold whitespace-nowrap">
                                            HN: {{ $pt->hn ?: '-' }}
                                        </span>
                                        <span class="font-mono text-slate-400 whitespace-nowrap">{{ $pt->cid ?? '-' }}</span>
                                    </div>
                                    @if($pt && $pt->militaryDischarge)
                                        <div class="mt-1">
                                            <span class="bg-purple-100 text-purple-800 border border-purple-300 px-2 py-0.5 rounded-md font-bold text-[10px] inline-flex items-center gap-1">
                                                <i class="fa-solid fa-folder-open text-purple-600"></i> รอปลด ({{ str_replace(['[ปลดทางสุขภาพจิต]', '[ปลดทางกาย]'], '', $pt->militaryDischarge->diagnosis_text) }})
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <!-- 3. สถานที่แยกนอน / เตียง (แสดงเป็นป้ายแนวนอนสะอาดตา ไม่บีบเป็นแท่งแนวตั้ง) -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    @if($isUnitInf)
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-indigo-50/90 text-indigo-700 border border-indigo-200 shadow-sm">
                                            <i class="fa-solid fa-tent-arrows-down text-indigo-600 text-xs"></i>
                                            <div class="leading-tight">
                                                <div class="text-xs font-black">ห้องพยาบาลหน่วยฝึก</div>
                                                <div class="text-[10px] font-semibold text-indigo-500">แยกโรงนอน / CI</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50/90 text-amber-800 border border-amber-200 shadow-sm">
                                            <i class="fa-solid fa-hospital-user text-amber-600 text-xs"></i>
                                            <div class="leading-tight">
                                                <div class="text-xs font-black">ฝากนอนที่ รพ.ค่ายฯ</div>
                                                <div class="text-[10px] font-semibold text-amber-600">หอผู้ป่วย / แยกกัก</div>
                                            </div>
                                        </div>
                                    @endif

                                    @if($stay->bed_or_zone)
                                        <div class="text-[11px] font-semibold text-slate-600 mt-1 pl-1">
                                            <i class="fa-solid fa-location-dot text-slate-400 mr-1"></i>โซน/เตียง: <b class="text-slate-800">{{ $stay->bed_or_zone }}</b>
                                        </div>
                                    @endif
                                </td>

                                <!-- 4. สาเหตุ / อาการ (Dx.) / การดูแล (ขยายพื้นที่ให้อ่านง่ายใน 1-2 บรรทัด) -->
                                <td class="px-4 py-3.5 max-w-md">
                                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                        <span class="px-2 py-0.5 rounded-md border text-[11px] font-bold inline-flex items-center gap-1 whitespace-nowrap {{ $rMeta['badge'] }}">
                                            <i class="fa-solid {{ $rMeta['icon'] }}"></i> {{ $rMeta['label'] }}
                                        </span>
                                    </div>
                                    <div class="font-black text-slate-800 text-xs sm:text-sm leading-snug">
                                        {{ $stay->diagnosis_text }}
                                    </div>
                                    @if($stay->care_instruction)
                                        <div class="text-[11px] text-teal-800 bg-teal-50/70 border border-teal-200/80 px-2.5 py-1 rounded-lg mt-1.5 font-medium leading-relaxed">
                                            <i class="fa-solid fa-user-nurse text-teal-600 mr-1"></i><b>การดูแล:</b> {{ $stay->care_instruction }}
                                        </div>
                                    @endif
                                </td>

                                <!-- 5. สถานะปัจจุบัน & ป้ายเช็คสัญญาณชีพวันนี้ (06:00 / 18:00) -->
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    @if($stay->status === 'staying')
                                        <div class="inline-flex flex-col items-center gap-1.5">
                                            <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-300 text-xs font-black inline-flex items-center gap-1.5 shadow-sm">
                                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span> กำลังแยกนอนพัก
                                            </span>

                                            <!-- ป้ายสถานะการวัดสัญญาณชีพวันนี้ (06:00 / 18:00) จัดวางใต้สถานะอย่างลงตัว -->
                                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold">
                                                <a href="{{ route('backend.care_chart.show', $pt->id) }}"
                                                   title="คลิกเพื่อลงหรือดูสัญญาณชีพรอบ 06:00 น."
                                                   class="px-2 py-0.5 rounded-md border transition-colors {{ $hasMorningVs ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-slate-50 text-slate-500 border-slate-200 hover:border-sky-300 hover:text-sky-700' }}">
                                                    🌅 06:00 {{ $hasMorningVs ? number_format($hasMorningVs->temperature, 1) . '°C' : 'รอวัด' }}
                                                </a>
                                                <a href="{{ route('backend.care_chart.show', $pt->id) }}"
                                                   title="คลิกเพื่อลงหรือดูสัญญาณชีพรอบ 18:00 น."
                                                   class="px-2 py-0.5 rounded-md border transition-colors {{ $hasEveningVs ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-slate-50 text-slate-500 border-slate-200 hover:border-sky-300 hover:text-sky-700' }}">
                                                    🌆 18:00 {{ $hasEveningVs ? number_format($hasEveningVs->temperature, 1) . '°C' : 'รอวัด' }}
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <div class="inline-flex flex-col items-center">
                                            <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold">
                                                <i class="fa-solid fa-check mr-1"></i> กลับโรงนอนปกติแล้ว
                                            </span>
                                            @if($stay->discharged_at)
                                                <span class="text-[10px] text-slate-400 mt-1">
                                                    เมื่อ {{ $stay->discharged_at->addYears(543)->format('d/m/y H:i') }} น.
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <!-- 6. ปุ่มจัดการ (จัดเรียง 2 แถวสะอาดตา ไม่ยืดล้นขอบจอ) -->
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center gap-1.5">
                                        <!-- แถวที่ 1: ปุ่มงานหลัก (V/S & เช็คกินยา) -->
                                        <a href="{{ route('backend.care_chart.show', $pt->id) }}"
                                           class="w-full justify-center px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors inline-flex items-center gap-1.5"
                                           title="เปิดใบบันทึกสัญญาณชีพ (06:00/18:00) และตารางรับประทานยา/ทายา">
                                            <i class="fa-solid fa-pills"></i>
                                            <span>V/S & เช็คกินยา</span>
                                            @if($ptMedCount > 0)
                                                <span class="px-1.5 py-0.2 bg-white text-sky-700 rounded-md font-black text-[10px]">{{ $ptMedCount }} ยา</span>
                                            @endif
                                        </a>

                                        <!-- แถวที่ 2: ปุ่มกลับโรงนอนปกติ + ปุ่มแก้ไข / ดูประวัติ / ลบ -->
                                        <div class="flex items-center justify-center gap-1">
                                            @if($stay->status === 'staying')
                                                <button type="button"
                                                        onclick="openDischargeInfirmaryModal('{{ route('backend.recruit_infirmary.discharge', $stay->id) }}', '{{ addslashes(($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '')) }}')"
                                                        class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[11px] font-bold shadow-sm transition-colors inline-flex items-center gap-1"
                                                        title="จำหน่ายกลับโรงนอนปกติ">
                                                    <i class="fa-solid fa-house-chimney-check"></i>
                                                    <span>กลับโรงนอน</span>
                                                </button>
                                            @else
                                                <form action="{{ route('backend.recruit_infirmary.discharge', $stay->id) }}" method="POST" onsubmit="return confirm('ต้องการย้อนสถานะกลับเป็นกำลังแยกนอนพักใช่หรือไม่?')" class="inline">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="revert_to_staying" value="1">
                                                    <button type="submit" class="px-2.5 py-1.5 bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-800 rounded-xl text-[11px] font-bold transition-colors inline-flex items-center gap-1" title="ย้อนสถานะกลับเป็นกำลังแยกนอน">
                                                        <i class="fa-solid fa-rotate-left"></i>
                                                        <span>ย้อนสถานะ</span>
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- ✏️ ปุ่มแก้ไขข้อมูลการแยกโรงนอน -->
                                            <button type="button"
                                                    onclick='openEditInfirmaryModal(@json($editPayload))'
                                                    class="w-7 h-7 rounded-xl bg-amber-50 hover:bg-amber-500 text-amber-600 hover:text-white border border-amber-200 flex items-center justify-center transition-colors shadow-sm"
                                                    title="แก้ไขข้อมูลการแยกโรงนอน">
                                                <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                            </button>

                                            <!-- 🪪 ปุ่มดูแฟ้มประวัติ -->
                                            <a href="{{ route('backend.patients.show', $pt->id) }}"
                                               class="w-7 h-7 rounded-xl bg-slate-100 hover:bg-indigo-600 text-slate-600 hover:text-white border border-slate-200 flex items-center justify-center transition-colors"
                                               title="ดูแฟ้มประวัติ">
                                                <i class="fa-solid fa-address-card text-[11px]"></i>
                                            </a>

                                            <!-- 🗑️ ปุ่มลบรายการ -->
                                            <form action="{{ route('backend.recruit_infirmary.destroy', $stay->id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบรายการนี้?')" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-7 h-7 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white border border-rose-200 flex items-center justify-center transition-colors" title="ลบรายการ">
                                                    <i class="fa-solid fa-trash-can text-[11px]"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center text-slate-400">
                                    <i class="fa-solid fa-bed text-4xl mb-3 block opacity-30"></i>
                                    <p class="font-bold text-slate-600 text-base">ยังไม่มีรายชื่อพลทหารที่แยกโรงนอนในหมวดที่เลือก</p>
                                    <p class="text-xs mt-1">คลิกปุ่ม "+ บันทึกแยกโรงนอน / ห้องพยาบาล" ด้านบนเพื่อเพิ่มข้อมูลได้เลยครับ</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-xs text-slate-500 font-semibold">
                    <span>แสดงหน้าละ:</span>
                    @foreach([10, 20, 50] as $pSize)
                        <a href="{{ request()->fullUrlWithQuery(['per_page' => $pSize, 'page' => 1]) }}"
                           class="px-2.5 py-1 rounded-lg font-bold transition-colors {{ ($perPage ?? 10) == $pSize ? 'bg-teal-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-teal-50' }}">
                            {{ $pSize }}
                        </a>
                    @endforeach
                    <span>รายชื่อ</span>
                </div>

                @if($stays->hasPages())
                    <div class="w-full sm:w-auto">
                        {{ $stays->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- ➕ Modal บันทึกแยกโรงนอน / นอนห้องพยาบาลหน่วยฝึก / ฝากนอน รพ. -->
<!-- ========================================================================= -->
<div id="addInfirmaryModal" class="{{ $errors->any() ? '' : 'hidden' }} fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 my-8">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
            <h3 class="text-lg font-black text-slate-800"><i class="fa-solid fa-bed-pulse text-teal-600 mr-2"></i>บันทึกแยกโรงนอน / ห้องพยาบาลหน่วยฝึก</h3>
            <button type="button" onclick="closeAddInfirmaryModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="infirmaryForm" action="{{ route('backend.recruit_infirmary.store') }}" method="POST" novalidate onsubmit="return validateInfirmaryForm(event)" class="space-y-4">
            @csrf

            <!-- 1. ช่องค้นหาพลทหาร -->
            <div class="relative">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ค้นหาและเลือกพลทหาร <span class="text-rose-500">*</span></label>
                <input type="hidden" name="patient_id" id="inf_patient_id" value="{{ old('patient_id') }}">

                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                    </span>
                    <input type="text" id="inf_patient_search" autocomplete="off"
                           onfocus="document.getElementById('inf_patient_dropdown').classList.remove('hidden')"
                           oninput="filterInfirmaryPatients(this.value); clearInfError('inf_patient_search', 'err_inf_patient');"
                           placeholder="พิมพ์ชื่อ, นามสกุล, เลข ปชช., HN แล้วคลิกเลือก..."
                           class="w-full pl-10 pr-9 py-2.5 rounded-xl border-2 {{ $errors->has('patient_id') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-teal-500 text-sm font-medium text-slate-800 outline-none transition-all">
                </div>
                <p id="err_inf_patient" class="{{ $errors->has('patient_id') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1.5 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first('patient_id') ?: 'กรุณาพิมพ์ค้นหาแล้วคลิกเลือกรายชื่อพลทหารจากรายการครับ' }}</span>
                </p>

                <div id="inf_patient_dropdown" class="hidden absolute left-0 right-0 mt-1 max-h-52 overflow-y-auto bg-white rounded-2xl shadow-xl border-2 border-slate-200 z-50 divide-y divide-slate-100">
                    @foreach($batchPatients as $pt)
                        @php
                            $uName = $pt->militaryUnit->short_name ?? 'ไม่ระบุหน่วย';
                            $fName = ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');
                            $dcObj = $pt->militaryDischarge;
                            $dcDx  = $dcObj ? $dcObj->diagnosis_text : '';
                            $sKey  = strtolower($fName . ' ' . ($pt->cid ?? '') . ' ' . ($pt->hn ?? '') . ' ' . $uName . ' ' . $dcDx);
                        @endphp
                        <div onclick="selectInfirmaryPatient('{{ $pt->id }}', '{{ addslashes($fName) }} ({{ addslashes($uName) }} | HN: {{ $pt->hn ?: 'ไม่มี' }})', '{{ addslashes($dcDx) }}')"
                             data-search="{{ $sKey }}"
                             class="inf-pt-option px-4 py-2.5 hover:bg-teal-50 cursor-pointer flex justify-between items-center gap-2">
                            <div>
                                <div class="font-bold text-slate-800 text-sm flex flex-wrap items-center gap-1.5">
                                    <span>{{ $fName }}</span>
                                    @if($dcObj)
                                        <span class="px-2 py-0.2 bg-purple-100 text-purple-800 border border-purple-200 rounded text-[10px] font-black">
                                            📁 อยู่ระหว่างรอปลด
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    หน่วย: <b class="text-indigo-600">{{ $uName }}</b> | ผลัด {{ $pt->intake_batch }}
                                    @if($dcDx) <span class="text-purple-700 font-semibold">| Dx.นำปลด: {{ $dcDx }}</span> @endif
                                </div>
                            </div>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 shrink-0">HN: {{ $pt->hn ?: '-' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 2. เลือกสถานที่แยกนอน -->
            @php $oldLocType = old('location_type', 'unit_infirmary'); @endphp
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">สถานที่แยกนอนพัก <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/60 transition-all">
                        <input type="radio" name="location_type" value="unit_infirmary" {{ $oldLocType === 'unit_infirmary' ? 'checked' : '' }} class="mt-0.5 w-4 h-4 text-indigo-600">
                        <div>
                            <div class="text-xs font-black text-slate-800">🏕️ ห้องพยาบาลหน่วยฝึก</div>
                            <div class="text-[10px] text-slate-500">แยกโรงนอนที่หน่วยฝึก / แยกกัก CI</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/60 transition-all">
                        <input type="radio" name="location_type" value="camp_hospital" {{ $oldLocType === 'camp_hospital' ? 'checked' : '' }} class="mt-0.5 w-4 h-4 text-amber-600">
                        <div>
                            <div class="text-xs font-black text-slate-800">🏥 ฝากนอนที่ รพ.ค่ายฯ</div>
                            <div class="text-[10px] text-slate-500">ฝากนอนแยกกัก/ดูอาการที่ รพ.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 3. หมวดหมู่สาเหตุ และ โซน/เตียง -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">สาเหตุที่ต้องแยกโรงนอน <span class="text-rose-500">*</span></label>
                    <select name="reason_category" id="inf_reason_category" required class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white text-xs sm:text-sm p-2.5 font-bold text-slate-800 outline-none">
                        <option value="respiratory" {{ old('reason_category') === 'respiratory' ? 'selected' : '' }}>🦠 1. โรคระบบทางเดินหายใจ</option>
                        <option value="post_admit" {{ old('reason_category') === 'post_admit' ? 'selected' : '' }}>🛌 2. พักฟื้นหลังออกจาก รพ.</option>
                        <option value="musculoskeletal" {{ old('reason_category') === 'musculoskeletal' ? 'selected' : '' }}>🩹 3. บาดเจ็บกล้ามเนื้อ/แผล</option>
                        <option value="other" {{ old('reason_category') === 'other' ? 'selected' : '' }}>📌 4. สาเหตุอื่นๆ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">ระบุโซน / เตียง (ถ้ามี)</label>
                    <input type="text" name="bed_or_zone" value="{{ old('bed_or_zone') }}" placeholder="เช่น โซนทางเดินหายใจ, เตียง 2..."
                           class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white text-sm p-2.5 text-slate-800 outline-none">
                </div>
            </div>

            <!-- 4. โรควินิจฉัย / อาการสำคัญ (บังคับกรอก *) -->
            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="text-xs font-bold text-slate-700">อาการสำคัญ / การวินิจฉัยโรค (Dx.) <span class="text-rose-500">*</span></label>
                    <button type="button" onclick="fetchHosxpDxForInfirmary()" class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg border border-indigo-200">
                        <i class="fa-solid fa-cloud-arrow-down mr-1"></i> ดึง Dx. จาก HosXP
                    </button>
                </div>
                <input type="text" name="diagnosis_text" id="inf_dx_text" value="{{ old('diagnosis_text') }}" required
                       oninput="clearInfError('inf_dx_text', 'err_inf_dx')"
                       placeholder="เช่น ไข้หวัดใหญ่สายพันธุ์ A, มีไข้ไอเจ็บคอ, พักฟื้น..."
                       class="w-full rounded-xl border-2 {{ $errors->has('diagnosis_text') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-teal-500 text-sm p-2.5 font-semibold text-slate-800 outline-none transition-all">
                <p id="err_inf_dx" class="{{ $errors->has('diagnosis_text') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first('diagnosis_text') ?: 'กรุณาระบุอาการสำคัญ หรือการวินิจฉัยโรค (Dx.) ครับ' }}</span>
                </p>
            </div>

            <!-- 5. คำแนะนำการดูแล -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">คำแนะนำการดูแล / การแยกกัก</label>
                <div class="flex flex-wrap gap-1.5 mb-1.5">
                    <button type="button" onclick="document.getElementById('inf_care_note').value='สวมหน้ากากอนามัย แยกของใช้ และวัดไข้เช้า-เย็น'" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-600 rounded text-[11px] font-bold border border-slate-200">+ แยกกักทางเดินหายใจ</button>
                    <button type="button" onclick="document.getElementById('inf_care_note').value='งดฝึกภาคสนามและงดวิ่ง 3 วัน ทานยาต่อเนื่อง'" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-600 rounded text-[11px] font-bold border border-slate-200">+ งดฝึกหนัก 3 วัน</button>
                </div>
                <input type="text" name="care_instruction" id="inf_care_note" value="{{ old('care_instruction') }}" placeholder="ระบุคำสั่งการดูแลหรือจำนวนวันที่ให้แยกนอน..."
                       class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white text-sm p-2.5 text-slate-800 outline-none">
            </div>

            <!-- 6. วันและเวลาที่เริ่มเข้าพัก -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">วันที่เริ่มแยกนอน <span class="text-rose-500">*</span></label>
                    <input type="date" name="admitted_date" id="inf_admitted_date" value="{{ old('admitted_date', date('Y-m-d')) }}" required
                           onchange="clearInfError('inf_admitted_date', 'err_inf_date')"
                           class="w-full rounded-xl border-2 {{ $errors->has('admitted_date') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white text-sm p-2.5 font-bold text-slate-800 outline-none transition-all">
                    <p id="err_inf_date" class="{{ $errors->has('admitted_date') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>กรุณาระบุวันที่เริ่มแยกนอน</span>
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">เวลา <span class="text-rose-500">*</span></label>
                    <input type="time" name="admitted_time" id="inf_admitted_time" value="{{ old('admitted_time', date('H:i')) }}" required
                           onchange="clearInfError('inf_admitted_time', 'err_inf_time')"
                           class="w-full rounded-xl border-2 {{ $errors->has('admitted_time') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white text-sm p-2.5 font-bold text-slate-800 outline-none transition-all">
                    <p id="err_inf_time" class="{{ $errors->has('admitted_time') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>กรุณาระบุเวลา</span>
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeAddInfirmaryModal()" class="px-5 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-sm font-bold">ยกเลิก</button>
                <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-sm font-bold shadow-md">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openAddInfirmaryModal() {
        document.getElementById('addInfirmaryModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('inf_patient_search')?.focus(), 100);
    }

    function closeAddInfirmaryModal() {
        document.getElementById('addInfirmaryModal').classList.add('hidden');
    }

    function filterInfirmaryPatients(kw) {
        const list = document.getElementById('inf_patient_dropdown');
        if (list) list.classList.remove('hidden');
        document.getElementById('inf_patient_id').value = '';

        const clean = kw.trim().toLowerCase();
        document.querySelectorAll('.inf-pt-option').forEach(el => {
            const hay = el.getAttribute('data-search') || '';
            el.classList.toggle('hidden', clean !== '' && !hay.includes(clean));
        });
    }

    function selectInfirmaryPatient(id, label, dischargeDx = '') {
        document.getElementById('inf_patient_id').value = id;
        const box = document.getElementById('inf_patient_search');
        if (box) {
            box.value = label;
            clearInfError('inf_patient_search', 'err_inf_patient');
            box.classList.add('border-teal-500', 'bg-teal-50/30', 'font-bold');
        }
        document.getElementById('inf_patient_dropdown')?.classList.add('hidden');

        if (dischargeDx) {
            const dxInput = document.getElementById('inf_dx_text');
            const reasonSelect = document.getElementById('inf_reason_category');
            const careInput = document.getElementById('inf_care_note');
            if (dxInput && !dxInput.value.trim()) {
                dxInput.value = `ฝากนอนระหว่างรอปลดประจำการ (${dischargeDx})`;
                clearInfError('inf_dx_text', 'err_inf_dx');
            }
            if (reasonSelect) {
                reasonSelect.value = 'other';
            }
            if (careInput && !careInput.value.trim()) {
                careInput.value = 'ฝากนอนดูแลอาการระหว่างรอดำเนินการเอกสารนำปลดทหาร';
            }
        }
    }

    function markInfError(inputId, errId) {
        const el = document.getElementById(inputId);
        const err = document.getElementById(errId);
        if (el) {
            el.classList.remove('border-slate-300', 'bg-slate-50', 'border-teal-500', 'bg-teal-50/30');
            el.classList.add('border-rose-500', 'bg-rose-50/40', 'ring-2', 'ring-rose-500/20');
        }
        if (err) err.classList.remove('hidden');
    }

    function clearInfError(inputId, errId) {
        const el = document.getElementById(inputId);
        const err = document.getElementById(errId);
        if (el) {
            el.classList.remove('border-rose-500', 'bg-rose-50/40', 'ring-2', 'ring-rose-500/20');
            el.classList.add('border-slate-300', 'bg-slate-50');
        }
        if (err) err.classList.add('hidden');
    }

    function validateInfirmaryForm(e) {
        let missing = [];
        let firstEl = null;

        if (!document.getElementById('inf_patient_id')?.value.trim()) {
            markInfError('inf_patient_search', 'err_inf_patient');
            missing.push('รายชื่อพลทหาร (ต้องคลิกเลือกจากรายการ)');
            if (!firstEl) firstEl = document.getElementById('inf_patient_search');
        }

        if (!document.getElementById('inf_dx_text')?.value.trim()) {
            markInfError('inf_dx_text', 'err_inf_dx');
            missing.push('อาการสำคัญ / การวินิจฉัยโรค (Dx.)');
            if (!firstEl) firstEl = document.getElementById('inf_dx_text');
        }

        if (!document.getElementById('inf_admitted_date')?.value.trim()) {
            markInfError('inf_admitted_date', 'err_inf_date');
            missing.push('วันที่เริ่มแยกนอน');
            if (!firstEl) firstEl = document.getElementById('inf_admitted_date');
        }

        if (!document.getElementById('inf_admitted_time')?.value.trim()) {
            markInfError('inf_admitted_time', 'err_inf_time');
            missing.push('เวลาที่เริ่มแยกนอน');
            if (!firstEl) firstEl = document.getElementById('inf_admitted_time');
        }

        if (missing.length > 0) {
            if (e) e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'กรอกข้อมูลยังไม่ครบถ้วน!',
                html: `<div class="text-left text-xs sm:text-sm text-slate-600 space-y-2 mt-2"><p>กรุณากรอกข้อมูลในช่องที่มีกรอบสีแดงต่อไปนี้ให้ครบก่อนกดบันทึกครับ:</p><ul class="list-disc list-inside bg-rose-50 p-3.5 rounded-2xl border border-rose-200 space-y-1 text-rose-700 font-bold">${missing.map(m => `<li>${m}</li>`).join('')}</ul></div>`,
                confirmButtonText: 'กลับไปกรอกข้อมูล',
                confirmButtonColor: '#e11d48',
                customClass: { popup: 'rounded-3xl p-6' }
            }).then(() => {
                if (firstEl) setTimeout(() => firstEl.focus(), 150);
            });
            return false;
        }
        return true;
    }

    function fetchHosxpDxForInfirmary(targetInputId = 'inf_dx_text', customPatientId = null) {
        const pid = customPatientId || document.getElementById('inf_patient_id')?.value;
        if (!pid) {
            markInfError('inf_patient_search', 'err_inf_patient');
            Swal.fire({ icon: 'warning', title: 'กรุณาคลิกเลือกพลทหารก่อนดึง Dx. ครับ' });
            return;
        }
        fetch(`{{ route('backend.morbidity_reports.fetch_hosxp_dx') }}?patient_id=${pid}`)
            .then(r => r.json())
            .then(data => {
                const dxEl = document.getElementById(targetInputId);
                if (data.status === 'success' && dxEl) {
                    dxEl.value = data.dx_text;
                    dxEl.classList.remove('border-rose-500', 'bg-rose-50/40');
                    dxEl.classList.add('border-emerald-500', 'bg-emerald-50/30');
                    if (targetInputId === 'inf_dx_text') {
                        clearInfError('inf_dx_text', 'err_inf_dx');
                    }
                } else {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: data.message || 'ไม่พบ Dx. ใน HosXP', showConfirmButton: false, timer: 3000 });
                }
            });
    }

    // ✏️ ฟังก์ชันเปิดหน้าต่างแก้ไขข้อมูลการแยกโรงนอนรายบุคคล
    function openEditInfirmaryModal(item) {
        Swal.fire({
            title: '<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-pen-to-square text-amber-500 mr-2"></i>แก้ไขข้อมูลการแยกโรงนอน</div>',
            html: `
                <div class="text-left space-y-3.5 mt-2">
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs sm:text-sm flex justify-between items-center">
                        <div>
                            <div class="font-black text-slate-900 text-base">${item.soldier_name}</div>
                            <div class="text-xs text-slate-500">หน่วยฝึก: <b class="text-indigo-600">${item.unit_name}</b></div>
                        </div>
                        <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold">HN: ${item.hn}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">สถานที่แยกนอนพัก <span class="text-rose-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label class="flex items-start gap-2 p-2.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/60">
                                <input type="radio" name="swal_edit_loc" value="unit_infirmary" ${item.location_type === 'unit_infirmary' ? 'checked' : ''} class="mt-0.5 w-4 h-4 text-indigo-600">
                                <div>
                                    <div class="text-xs font-black text-slate-800">🏕️ ห้องพยาบาลหน่วยฝึก</div>
                                    <div class="text-[10px] text-slate-500">แยกโรงนอนที่หน่วยฝึก (CI)</div>
                                </div>
                            </label>
                            <label class="flex items-start gap-2 p-2.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/60">
                                <input type="radio" name="swal_edit_loc" value="camp_hospital" ${item.location_type === 'camp_hospital' ? 'checked' : ''} class="mt-0.5 w-4 h-4 text-amber-600">
                                <div>
                                    <div class="text-xs font-black text-slate-800">🏥 ฝากนอนที่ รพ.ค่ายฯ</div>
                                    <div class="text-[10px] text-slate-500">ฝากนอนแยกกักที่ รพ.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">สาเหตุที่แยกโรงนอน <span class="text-rose-500">*</span></label>
                            <select id="swal_edit_cat" class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 p-2 text-xs font-bold text-slate-800 outline-none">
                                <option value="respiratory" ${item.reason_category === 'respiratory' ? 'selected' : ''}>🦠 1. โรคระบบทางเดินหายใจ</option>
                                <option value="post_admit" ${item.reason_category === 'post_admit' ? 'selected' : ''}>🛌 2. พักฟื้นหลังออกจาก รพ.</option>
                                <option value="musculoskeletal" ${item.reason_category === 'musculoskeletal' ? 'selected' : ''}>🩹 3. บาดเจ็บกล้ามเนื้อ/แผล</option>
                                <option value="other" ${item.reason_category === 'other' ? 'selected' : ''}>📌 4. สาเหตุอื่นๆ</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ระบุโซน / เตียง (ถ้ามี)</label>
                            <input type="text" id="swal_edit_bed" value="${item.bed_or_zone}" placeholder="เช่น โซนทางเดินหายใจ, เตียง 2" class="w-full rounded-xl border-2 border-slate-300 bg-white p-2 text-xs text-slate-800 outline-none">
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="text-xs font-bold text-slate-700">อาการสำคัญ / การวินิจฉัยโรค (Dx.) <span class="text-rose-500">*</span></label>
                            <button type="button" onclick="fetchHosxpDxForInfirmary('swal_edit_dx', '${item.patient_id}')" class="text-[11px] font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded border border-indigo-200">
                                <i class="fa-solid fa-cloud-arrow-down mr-1"></i> ดึง Dx. จาก HosXP
                            </button>
                        </div>
                        <input type="text" id="swal_edit_dx" value="${item.diagnosis_text}" placeholder="ระบุอาการสำคัญ หรือการวินิจฉัยโรค..." class="w-full rounded-xl border-2 border-slate-300 bg-white p-2.5 text-xs sm:text-sm font-semibold text-slate-800 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">คำแนะนำการดูแล / การแยกกัก</label>
                        <div class="flex flex-wrap gap-1 mb-1.5">
                            <button type="button" onclick="document.getElementById('swal_edit_care').value='สวมหน้ากากอนามัย แยกของใช้ และวัดไข้เช้า-เย็น'" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-600 rounded text-[10px] font-bold border border-slate-200">+ แยกกักทางเดินหายใจ</button>
                            <button type="button" onclick="document.getElementById('swal_edit_care').value='งดฝึกภาคสนามและงดวิ่ง 3 วัน ทานยาต่อเนื่อง'" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-600 rounded text-[10px] font-bold border border-slate-200">+ งดฝึกหนัก 3 วัน</button>
                        </div>
                        <input type="text" id="swal_edit_care" value="${item.care_instruction}" placeholder="ระบุคำสั่งการดูแลหรือจำนวนวันที่ให้แยกนอน..." class="w-full rounded-xl border-2 border-slate-300 bg-white p-2 text-xs text-slate-800 outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">วันที่เริ่มแยกนอน <span class="text-rose-500">*</span></label>
                            <input type="date" id="swal_edit_date" value="${item.admitted_date}" class="w-full rounded-xl border-2 border-slate-300 p-2 text-xs font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">เวลา <span class="text-rose-500">*</span></label>
                            <input type="time" id="swal_edit_time" value="${item.admitted_time}" class="w-full rounded-xl border-2 border-slate-300 p-2 text-xs font-bold">
                        </div>
                    </div>
                </div>
            `,
            width: '36rem',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk mr-1"></i> บันทึกการแก้ไข',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#d97706',
            customClass: { popup: 'rounded-3xl p-6' },
            preConfirm: () => {
                const dxEl = document.getElementById('swal_edit_dx');
                const dateEl = document.getElementById('swal_edit_date');
                const timeEl = document.getElementById('swal_edit_time');

                if (!dxEl.value.trim()) {
                    dxEl.classList.add('border-rose-500', 'bg-rose-50/40');
                    Swal.showValidationMessage('กรุณาระบุอาการสำคัญ หรือการวินิจฉัยโรค (Dx.) ก่อนกดบันทึกครับ');
                    return false;
                }
                if (!dateEl.value.trim() || !timeEl.value.trim()) {
                    Swal.showValidationMessage('กรุณาระบุวันที่และเวลาที่เริ่มแยกนอนให้ครบถ้วนครับ');
                    return false;
                }

                return {
                    location_type: document.querySelector('input[name="swal_edit_loc"]:checked')?.value || 'unit_infirmary',
                    reason_category: document.getElementById('swal_edit_cat').value,
                    bed_or_zone: document.getElementById('swal_edit_bed').value.trim(),
                    diagnosis_text: dxEl.value.trim(),
                    care_instruction: document.getElementById('swal_edit_care').value.trim(),
                    admitted_date: dateEl.value,
                    admitted_time: timeEl.value
                };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = item.update_url;
                form.innerHTML = `
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="location_type" value="${res.value.location_type}">
                    <input type="hidden" name="reason_category" value="${res.value.reason_category}">
                    <input type="hidden" name="bed_or_zone" value="${res.value.bed_or_zone}">
                    <input type="hidden" name="diagnosis_text" value="${res.value.diagnosis_text}">
                    <input type="hidden" name="care_instruction" value="${res.value.care_instruction}">
                    <input type="hidden" name="admitted_date" value="${res.value.admitted_date}">
                    <input type="hidden" name="admitted_time" value="${res.value.admitted_time}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function openDischargeInfirmaryModal(actionUrl, soldierName) {
        Swal.fire({
            title: '<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-house-chimney-check text-emerald-500 mr-2"></i>จำหน่ายกลับโรงนอนปกติ</div>',
            html: `
                <div class="text-left space-y-3 mt-2">
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs sm:text-sm text-emerald-900">
                        ยืนยันให้ <b class="text-emerald-800">${soldierName}</b> ออกจากห้องพยาบาล/จุดแยกกัก กลับเข้าโรงนอนปกติของกองร้อย
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">วันที่กลับโรงนอน</label>
                            <input type="date" id="swal_inf_date" value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">เวลา</label>
                            <input type="time" id="swal_inf_time" value="{{ date('H:i') }}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-bold">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">หมายเหตุเมื่อกลับโรงนอน</label>
                        <input type="text" id="swal_inf_note" value="อาการดีขึ้น ไม่มีไข้ กลับเข้าโรงนอนและฝึกได้ตามปกติ" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> ยืนยันกลับโรงนอนปกติ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#059669',
            customClass: { popup: 'rounded-3xl p-6' },
            preConfirm: () => {
                return {
                    discharged_date: document.getElementById('swal_inf_date').value,
                    discharged_time: document.getElementById('swal_inf_time').value,
                    discharge_note: document.getElementById('swal_inf_note').value.trim()
                };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = actionUrl;
                form.innerHTML = `
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="discharged_date" value="${res.value.discharged_date}">
                    <input type="hidden" name="discharged_time" value="${res.value.discharged_time}">
                    <input type="hidden" name="discharge_note" value="${res.value.discharge_note}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    document.addEventListener('click', function(e) {
        const sBox = document.getElementById('inf_patient_search');
        const sList = document.getElementById('inf_patient_dropdown');
        if (sBox && sList && !sBox.contains(e.target) && !sList.contains(e.target)) {
            sList.classList.add('hidden');
        }
    });
</script>
@endsection