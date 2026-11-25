@extends('layouts.backend')

@section('title', 'รายงานอาการเจ็บป่วยทหารใหม่ประจำวัน - KSVR Hospital')
@section('page_header', 'รายงานอาการเจ็บป่วยทหารใหม่ประจำวัน')

@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-12">

    @php
        // สร้าง Map ชื่อหน่วยฝึกของพลทหารแต่ละนายไว้สำรองสำหรับแสดงหลังชื่อทหารในรายการ Admit
        $patientUnitLookup = [];
        if (isset($batchPatients)) {
            foreach ($batchPatients as $bp) {
                $fullNameKey = trim(($bp->rank ?? '') . ($bp->first_name ?? '') . ' ' . ($bp->last_name ?? ''));
                $spacedNameKey = trim(($bp->rank ?? '') . ' ' . ($bp->first_name ?? '') . ' ' . ($bp->last_name ?? ''));
                $uShort = $bp->militaryUnit->short_name ?? null;
                if ($uShort) {
                    $patientUnitLookup[$fullNameKey] = $uShort;
                    $patientUnitLookup[$spacedNameKey] = $uShort;
                    $patientUnitLookup[trim(($bp->first_name ?? '') . ' ' . ($bp->last_name ?? ''))] = $uShort;
                }
            }
        }
        if (isset($activeCasesGrouped)) {
            foreach ($activeCasesGrouped as $ac) {
                if ($ac->patient && $ac->militaryUnit) {
                    $fn1 = trim(($ac->patient->rank ?? '') . ($ac->patient->first_name ?? '') . ' ' . ($ac->patient->last_name ?? ''));
                    $fn2 = trim(($ac->patient->rank ?? '') . ' ' . ($ac->patient->first_name ?? '') . ' ' . ($ac->patient->last_name ?? ''));
                    $patientUnitLookup[$fn1] = $ac->militaryUnit->short_name;
                    $patientUnitLookup[$fn2] = $ac->militaryUnit->short_name;
                }
            }
        }
    @endphp

    <!-- 🛠️ แถบเครื่องมือ: เลือกผลัด, เลือกวันที่, เพิ่มเคส และบันทึกเป็นรูปภาพสไลด์ -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 print:hidden">
        <form method="GET" action="{{ route('backend.morbidity_reports.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">ผลัดทหารใหม่</label>
                <select name="intake_batch" onchange="this.form.submit()" class="rounded-xl border border-slate-300 bg-slate-50 text-sm font-bold px-3.5 py-2 outline-none focus:border-emerald-500">
                    <option value="all" {{ $selectedBatch == 'all' ? 'selected' : '' }}>ทุกผลัด (รวมทั้งหมด)</option>
                    @foreach($batches as $b)
                        <option value="{{ $b }}" {{ $selectedBatch == $b ? 'selected' : '' }}>ผลัด {{ $b }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">ข้อมูลประจำวันที่</label>
                <input type="date" name="report_date" value="{{ $reportDate }}" onchange="this.form.submit()" class="rounded-xl border border-slate-300 bg-slate-50 text-sm font-bold px-3.5 py-2 outline-none focus:border-emerald-500">
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="openAddMorbidityModal()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs sm:text-sm shadow-sm flex items-center gap-2 transition-all">
                <i class="fa-solid fa-plus-circle"></i> บันทึกผู้ป่วยรายใหม่ / นำปลด
            </button>

            <button type="button" onclick="openManageActiveCasesModal()" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs sm:text-sm shadow-sm flex items-center gap-2 transition-all">
                <i class="fa-solid fa-user-check"></i> อัปเดตหายป่วย / แก้ไข Dx. ({{ $activeCasesGrouped->count() }} นาย)
            </button>

            <button type="button" onclick="exportSlideAsImage()" id="btn-export-slide" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs sm:text-sm shadow-sm flex items-center gap-2 transition-all">
                <i class="fa-solid fa-camera"></i> บันทึกภาพสไลด์ (JPG)
            </button>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 🖼️ โซนกระดานสไลด์รายงาน (ถอดแบบตามรูปภาพมาตรฐาน รพ.ค่ายกฤษณ์สีวะรา) -->
    <!-- ===================================================================== -->
    <div id="morbidity-slide-board" class="bg-[#fdf8f5] p-4 md:p-6 rounded-2xl shadow-lg border-2 border-slate-300 text-slate-900">
        
        <!-- 🟢 ส่วนหัวสไลด์ (Banner สีเขียวเข้ม) -->
        <div class="bg-[#1b6b4a] text-white px-6 py-3 rounded-xl shadow-sm text-center mb-4 relative flex flex-col items-center justify-center">
            <h2 class="text-xl md:text-2xl font-black tracking-wide">
                รายงานอาการเจ็บป่วยทหารใหม่ {{ $selectedBatch === 'all' ? 'ทุกผลัด' : 'ผลัด ' . $selectedBatch }}
            </h2>
            <p class="text-lg md:text-xl font-bold text-[#fde047] mt-0.5">
                รพ.ค่ายกฤษณ์สีวะรา {{ $thaiDateHeader }}
            </p>
        </div>

        <!-- โครงสร้างหลักแบ่งซ้าย (8 ส่วน) และ ขวา (4 ส่วน) -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">

            <!-- ================= ฝั่งซ้าย (8 คอลัมน์): ทางเดินหายใจ + ความร้อน + เคสสุขภาพจิต ================= -->
            <div class="xl:col-span-8 space-y-5">

                <!-- 1. โรคระบบทางเดินหายใจ -->
                <div class="bg-white p-4 rounded-xl border border-slate-300 shadow-sm">
                    <div class="inline-block bg-[#9ec5e8] text-slate-900 font-black text-sm px-4 py-1.5 rounded-lg mb-3 border border-blue-300">
                        โรคระบบทางเดินหายใจ
                    </div>

                    <!-- กล่องสรุป 3 กล่องบน (ติดเชื้อเพิ่มวันนี้ | รักษาอยู่ | ยอดสะสม) -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3 text-xs font-bold">
                        <!-- ติดเชื้อเพิ่ม ยอดวันนี้ -->
                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-5 bg-[#c00000] text-white flex items-center justify-center p-2 text-center leading-tight">
                                ติดเชื้อเพิ่ม<br>ยอดวันนี้
                            </div>
                            <div class="col-span-7 divide-y divide-black">
                                <div class="grid grid-cols-3 bg-[#f8cbad]">
                                    <div class="col-span-2 p-1.5 border-r border-black">โควิด</div>
                                    <div class="col-span-1 p-1.5 text-center font-black">{{ $respSummary['covid_new'] }}</div>
                                </div>
                                <div class="grid grid-cols-3 bg-[#f8cbad]">
                                    <div class="col-span-2 p-1.5 border-r border-black">ไข้หวัดใหญ่</div>
                                    <div class="col-span-1 p-1.5 text-center font-black">{{ $respSummary['flu_new'] }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- รักษาอยู่ -->
                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-4 bg-[#2e75b6] text-white flex items-center justify-center p-2 text-center">
                                รักษาอยู่
                            </div>
                            <div class="col-span-8 divide-y divide-black">
                                <div class="grid grid-cols-3 bg-[#bdd7ee]">
                                    <div class="col-span-2 p-1.5 border-r border-black">โควิด</div>
                                    <div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $respSummary['covid_treating'] }}</div>
                                </div>
                                <div class="grid grid-cols-3 bg-[#bdd7ee]">
                                    <div class="col-span-2 p-1.5 border-r border-black">ไข้หวัดใหญ่</div>
                                    <div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $respSummary['flu_treating'] }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- ยอดสะสม -->
                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-4 bg-[#548235] text-white flex items-center justify-center p-2 text-center leading-tight">
                                ยอด<br>สะสม
                            </div>
                            <div class="col-span-8 divide-y divide-black">
                                <div class="grid grid-cols-3 bg-[#e2efda]">
                                    <div class="col-span-2 p-1.5 border-r border-black">โควิด</div>
                                    <div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $respSummary['covid_cum'] }}</div>
                                </div>
                                <div class="grid grid-cols-3 bg-[#e2efda]">
                                    <div class="col-span-2 p-1.5 border-r border-black">ไข้หวัดใหญ่</div>
                                    <div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $respSummary['flu_cum'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ตารางโรคระบบทางเดินหายใจ (ตัวเลขตัวหนาทั้งหมด) -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-center border-collapse border border-black font-bold">
                            <thead>
                                <tr class="font-bold">
                                    <th rowspan="2" class="border border-black bg-[#fff2cc] p-1.5 align-middle">หน่วยฝึก</th>
                                    <th rowspan="2" class="border border-black bg-[#deebf7] p-1.5 align-middle">ยอดทหารใหม่<br>(จัดสรร)</th>
                                    <th colspan="3" class="border border-black bg-[#ffe699] p-1.5">โควิด-19 (นาย)</th>
                                    <th colspan="3" class="border border-black bg-[#f8cbad] p-1.5">ไข้หวัดใหญ่ (นาย)</th>
                                    <th rowspan="2" class="border border-black bg-[#f4b183] p-1.5 align-middle">ร้อยละ<br>การระบาด</th>
                                    <th rowspan="2" class="border border-black bg-[#e2efda] p-1.5 align-middle">หายป่วย</th>
                                    <th colspan="2" class="border border-black bg-[#bdd7ee] p-1.5">การรักษา</th>
                                </tr>
                                <tr class="font-bold">
                                    <th class="border border-black bg-[#fff2cc] p-1">รายใหม่</th>
                                    <th class="border border-black bg-white p-1">รายเก่า</th>
                                    <th class="border border-black bg-white p-1">สะสม</th>
                                    <th class="border border-black bg-[#fce4d6] p-1">รายใหม่</th>
                                    <th class="border border-black bg-white p-1">รายเก่า</th>
                                    <th class="border border-black bg-white p-1">สะสม</th>
                                    <th class="border border-black bg-white p-1">แยกกัก CI</th>
                                    <th class="border border-black bg-white p-1">นอน รพ.</th>
                                </tr>
                            </thead>
                            <tbody class="font-bold">
                                @foreach($respTable as $row)
                                    <tr>
                                        <td class="border border-black font-bold p-1.5 bg-white">{{ $row['unit_name'] }}</td>
                                        <td class="border border-black font-bold p-1.5 bg-white">{{ $row['allocated'] }}</td>
                                        <td class="border border-black font-bold bg-[#fff2cc] p-1.5">{{ $row['covid_new'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $row['covid_old'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $row['covid_cum'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-[#fce4d6] p-1.5">{{ $row['flu_new'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $row['flu_old'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $row['flu_cum'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-[#f8cbad] p-1.5">{{ $row['outbreak_percent'] > 0 ? $row['outbreak_percent'].'%' : '-' }}</td>
                                        <td class="border border-black font-bold bg-[#e2efda] p-1.5">{{ $row['recovered'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $row['ci_count'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $row['hosp_count'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 2. การบาดเจ็บจากความร้อน -->
                <div class="bg-[#fff9e6] p-4 rounded-xl border border-amber-300 shadow-sm">
                    <div class="inline-block bg-[#ffc000] text-slate-900 font-black text-sm px-4 py-1.5 rounded-lg mb-3 border border-amber-500">
                        การบาดเจ็บจากความร้อน
                    </div>

                    <!-- กล่องสรุป 3 กล่องบน (ยอดวันนี้ | รักษาอยู่ | สะสม) -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3 text-xs font-bold">
                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-4 bg-[#c00000] text-white flex items-center justify-center p-2 text-center">ยอดวันนี้</div>
                            <div class="col-span-8 divide-y divide-black bg-[#f8cbad]">
                                <div class="grid grid-cols-3"><div class="col-span-2 p-1.5 border-r border-black">ชนิดไม่รุนแรง</div><div class="col-span-1 p-1.5 text-center font-black">{{ $heatSummary['mild_new'] }}</div></div>
                                <div class="grid grid-cols-3"><div class="col-span-2 p-1.5 border-r border-black">ชนิดรุนแรง</div><div class="col-span-1 p-1.5 text-center font-black">{{ $heatSummary['severe_new'] }}</div></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-4 bg-[#2e75b6] text-white flex items-center justify-center p-2 text-center">รักษาอยู่</div>
                            <div class="col-span-8 divide-y divide-black bg-[#bdd7ee]">
                                <div class="grid grid-cols-3"><div class="col-span-2 p-1.5 border-r border-black">ชนิดไม่รุนแรง</div><div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $heatSummary['mild_treating'] }}</div></div>
                                <div class="grid grid-cols-3"><div class="col-span-2 p-1.5 border-r border-black">ชนิดรุนแรง</div><div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $heatSummary['severe_treating'] }}</div></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-4 bg-[#548235] text-white flex items-center justify-center p-2 text-center">สะสม</div>
                            <div class="col-span-8 divide-y divide-black bg-[#c5e0b4]">
                                <div class="grid grid-cols-3"><div class="col-span-2 p-1.5 border-r border-black">ชนิดไม่รุนแรง</div><div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $heatSummary['mild_cum'] }}</div></div>
                                <div class="grid grid-cols-3"><div class="col-span-2 p-1.5 border-r border-black">ชนิดรุนแรง</div><div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $heatSummary['severe_cum'] }}</div></div>
                            </div>
                        </div>
                    </div>

                    <!-- ตารางการบาดเจ็บจากความร้อน 9 ชนิด (ตัวเลขตัวหนาทั้งหมด) -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-[11px] text-center border-collapse border border-black bg-white font-bold">
                            <thead>
                                <tr class="font-bold">
                                    <th class="border border-black bg-[#fff2cc] p-1.5">หน่วยฝึก</th>
                                    <th class="border border-black bg-[#a9d18e] p-1">ผื่นแดด</th>
                                    <th class="border border-black bg-[#a9d18e] p-1">บวมแดด</th>
                                    <th class="border border-black bg-[#a9d18e] p-1">ตะคริว<br>แดด</th>
                                    <th class="border border-black bg-[#a9d18e] p-1">เกร็ง<br>แดด</th>
                                    <th class="border border-black bg-[#a9d18e] p-1">ลมแดด</th>
                                    <th class="border border-black bg-[#a9d18e] p-1">กล้ามเนื้อสลาย<br>ไม่รุนแรง</th>
                                    <th class="border border-black bg-[#f4b183] p-1">กล้ามเนื้อสลาย<br>รุนแรง</th>
                                    <th class="border border-black bg-[#f4b183] p-1">ฮีทเอ็กซอสชั่น</th>
                                    <th class="border border-black bg-[#f4b183] p-1">ฮีทสโตรก</th>
                                    <th class="border border-black bg-[#fce4d6] p-1.5">รวม</th>
                                </tr>
                            </thead>
                            <tbody class="font-bold">
                                @foreach($heatTable as $hRow)
                                    <tr>
                                        <td class="border border-black font-bold p-1.5 bg-white">{{ $hRow['unit_name'] }}</td>
                                        @foreach(['heat_rash', 'heat_edema', 'heat_cramps', 'heat_tetany', 'heat_syncope', 'mild_rhabdo', 'severe_rhabdo', 'heat_exhaustion', 'heat_stroke'] as $stKey)
                                            <td class="border border-black font-bold p-1.5 bg-white">{{ $hRow['counts'][$stKey] ?: '-' }}</td>
                                        @endforeach
                                        <td class="border border-black font-bold p-1.5 bg-[#fce4d6]">{{ $hRow['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. เคสสุขภาพจิต / จิตเวช -->
                @php
                    $safeMentalSummary = $mentalSummary ?? ['new_today' => 0, 'treating' => 0, 'unit_care' => 0, 'hosp_care' => 0, 'cum' => 0, 'recovered' => 0];
                    $safeMentalTable   = $mentalTable ?? [];
                    $safeMentalList    = $activeMentalList ?? collect();
                @endphp
                <div class="bg-[#f8f5ff] p-4 rounded-xl border border-purple-300 shadow-sm">
                    <div class="inline-block bg-[#b4a7d6] text-slate-900 font-black text-sm px-4 py-1.5 rounded-lg mb-3 border border-purple-400">
                        เคสสุขภาพจิต / จิตเวช
                    </div>

                    <!-- กล่องสรุป 3 กล่องบนของเคสสุขภาพจิต -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3 text-xs font-bold">
                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-5 bg-[#c00000] text-white flex items-center justify-center p-2 text-center leading-tight">
                                เคสใหม่<br>ยอดวันนี้
                            </div>
                            <div class="col-span-7 bg-[#f8cbad] flex items-center justify-between px-3 py-2">
                                <span>สุขภาพจิต</span>
                                <span class="font-black text-sm">{{ $safeMentalSummary['new_today'] }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-4 bg-[#2e75b6] text-white flex items-center justify-center p-2 text-center">
                                รักษาอยู่
                            </div>
                            <div class="col-span-8 divide-y divide-black bg-[#bdd7ee]">
                                <div class="grid grid-cols-3">
                                    <div class="col-span-2 p-1.5 border-r border-black">ดูแลที่หน่วย</div>
                                    <div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $safeMentalSummary['unit_care'] }}</div>
                                </div>
                                <div class="grid grid-cols-3">
                                    <div class="col-span-2 p-1.5 border-r border-black">นอน รพ.</div>
                                    <div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $safeMentalSummary['hosp_care'] }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-12 border border-black">
                            <div class="col-span-4 bg-[#548235] text-white flex items-center justify-center p-2 text-center leading-tight">
                                ยอด<br>สะสม
                            </div>
                            <div class="col-span-8 divide-y divide-black bg-[#e2efda]">
                                <div class="grid grid-cols-3">
                                    <div class="col-span-2 p-1.5 border-r border-black">สะสมทั้งหมด</div>
                                    <div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $safeMentalSummary['cum'] }}</div>
                                </div>
                                <div class="grid grid-cols-3">
                                    <div class="col-span-2 p-1.5 border-r border-black">อาการปกติ</div>
                                    <div class="col-span-1 p-1.5 text-center font-black bg-white">{{ $safeMentalSummary['recovered'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ตารางเคสสุขภาพจิตแยกตามหน่วยฝึก (ตัวเลขตัวหนาทั้งหมด) -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-center border-collapse border border-black bg-white font-bold">
                            <thead>
                                <tr class="font-bold">
                                    <th rowspan="2" class="border border-black bg-[#fff2cc] p-1.5 align-middle">หน่วยฝึก</th>
                                    <th colspan="3" class="border border-black bg-[#d9d2e9] p-1.5">เคสสุขภาพจิต / จิตเวช (นาย)</th>
                                    <th rowspan="2" class="border border-black bg-[#e2efda] p-1.5 align-middle">อาการปกติ<br>/ จำหน่าย</th>
                                    <th colspan="2" class="border border-black bg-[#bdd7ee] p-1.5">รูปแบบการดูแลปัจจุบัน</th>
                                </tr>
                                <tr class="font-bold">
                                    <th class="border border-black bg-[#ede7f6] p-1">รายใหม่</th>
                                    <th class="border border-black bg-white p-1">รายเก่า</th>
                                    <th class="border border-black bg-white p-1">สะสม</th>
                                    <th class="border border-black bg-white p-1">รับยา/ดูแลที่หน่วย</th>
                                    <th class="border border-black bg-white p-1">นอน รพ.</th>
                                </tr>
                            </thead>
                            <tbody class="font-bold">
                                @foreach($safeMentalTable as $mRow)
                                    <tr>
                                        <td class="border border-black font-bold p-1.5 bg-white">{{ $mRow['unit_name'] }}</td>
                                        <td class="border border-black font-bold bg-[#ede7f6] p-1.5">{{ $mRow['new'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $mRow['old'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $mRow['cum'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-[#e2efda] p-1.5">{{ $mRow['recovered'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $mRow['unit_care'] ?: '-' }}</td>
                                        <td class="border border-black font-bold bg-white p-1.5">{{ $mRow['hosp_care'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- <!-- รายชื่อผู้ป่วยเคสสุขภาพจิตที่กำลังดูแลอยู่ (ถ้ามี) -->
                    @if($safeMentalList->count() > 0)
                        <div class="mt-3 p-3 bg-white rounded-xl border border-purple-200 space-y-1.5">
                            @foreach($safeMentalList as $mItem)
                                <div class="text-xs sm:text-sm font-bold text-slate-800 flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        - {{ $mItem['full_name'] }} <span class="text-xs text-slate-500">({{ $mItem['unit_name'] }})</span>
                                        <span class="text-purple-700 ml-1">Dx. {{ $mItem['dx'] }}</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold border {{ $mItem['is_hosp'] ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200' }}">
                                        {{ $mItem['care_loc'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif --}}
                </div>

            </div>

            <!-- ================= ฝั่งขวา (4 คอลัมน์): การเจ็บป่วยอื่นๆ (นอน รพ.) + ตารางการนำปลด ================= -->
            <div class="xl:col-span-4 space-y-5">

                <!-- กล่องขวาบน: การเจ็บป่วยอื่นๆ (วางตาราง นอน รพ.ค่ายฯ และ นอน รพ.สาธารณสุข ก่อน แล้วแสดงรายชื่อใต้ตาราง) -->
                <div class="flex flex-col bg-[#fdf2e9] p-4 rounded-xl border border-rose-200 shadow-sm space-y-3.5">
                    <div>
                        <div class="inline-block bg-[#f8cbad] text-slate-900 font-black text-sm px-4 py-1.5 rounded-lg border border-rose-300">
                            การเจ็บป่วยอื่นๆ
                        </div>
                    </div>

                    <!-- ตารางที่ 1: นอน รพ.ค่ายฯ (ตัวเลขตัวหนาทั้งหมด) -->
                    <table class="w-full text-xs text-center border-collapse border border-black bg-white font-bold">
                        <thead>
                            <tr class="font-bold">
                                <th rowspan="2" class="border border-black bg-[#ffe699] p-1.5 align-middle">หน่วยฝึก</th>
                                <th colspan="4" class="border border-black bg-[#ffe699] p-1.5">นอน รพ.ค่ายฯ</th>
                            </tr>
                            <tr class="font-bold">
                                <th class="border border-black bg-[#fff2cc] p-1">รายใหม่</th>
                                <th class="border border-black bg-white p-1">รายเก่า</th>
                                <th class="border border-black bg-white p-1">สะสม</th>
                                <th class="border border-black bg-white p-1">หายป่วย</th>
                            </tr>
                        </thead>
                        <tbody class="font-bold">
                            @foreach($campHospTable as $cRow)
                                <tr>
                                    <td class="border border-black font-bold p-1.5 bg-[#fce4d6]">{{ $cRow['unit_name'] }}</td>
                                    <td class="border border-black font-bold bg-[#fff2cc] p-1.5">{{ $cRow['new'] }}</td>
                                    <td class="border border-black font-bold bg-white p-1.5">{{ $cRow['old'] }}</td>
                                    <td class="border border-black font-bold bg-white p-1.5">{{ $cRow['cum'] }}</td>
                                    <td class="border border-black font-bold bg-white p-1.5">{{ $cRow['recovered'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <!-- 📋 กล่องแสดงรายชื่อทหารที่ Admit พร้อมชื่อหน่วยฝึกหลังชื่อทหาร และ Dx.  -->
                    <div class="p-3.5 bg-white rounded-xl border border-rose-200 min-h-[90px] flex flex-col justify-center">
                        @forelse($activeIpdList as $ipd)
                        @if($ipd['hosp_type'] === 'รพ.ค่ายฯ')
                            @php
                                $cleanSoldierName = trim($ipd['full_name'] ?? '');
                                $noRankName = trim(preg_replace('/^(พลฯ|พลทหาร)\s*/u', '', $cleanSoldierName));
                                $resolvedUnitName = $ipd['unit_name']
                                    ?? $ipd['unit']
                                    ?? ($patientUnitLookup[$cleanSoldierName] ?? null)
                                    ?? ($patientUnitLookup[$noRankName] ?? null);
                            @endphp
                            <div class="text-sm font-bold text-slate-800 leading-relaxed mb-2 last:mb-0">
                                - {{ $ipd['full_name'] }}
                                @if(!empty($resolvedUnitName))
                                    <span class="text-xs text-slate-500">({{ $resolvedUnitName }})</span>
                                @endif
                                <span class="text-indigo-700">Dx. {{ $ipd['dx'] }}</span><br>
                                <span class="text-xs text-slate-600 ml-2">Admit วันที่ {{ $ipd['admit_date'] }} ({{ $ipd['hosp_type'] }})</span>
                            </div>
                        @endif
                        @empty
                            <div class="text-xs text-slate-400 text-center italic">
                                - ไม่มีผู้ป่วยนอนพักรักษาตัวในโรงพยาบาล ณ วันนี้ -
                            </div>
                        @endforelse
                    </div>

                    <!-- ตารางที่ 2: นอน รพ.สาธารณสุข (ตัวเลขตัวหนาทั้งหมด) -->
                    <table class="w-full text-xs text-center border-collapse border border-black bg-white font-bold">
                        <thead>
                            <tr class="font-bold">
                                <th rowspan="2" class="border border-black bg-[#ffe699] p-1.5 align-middle">หน่วยฝึก</th>
                                <th colspan="4" class="border border-black bg-[#ffe699] p-1.5">นอน รพ.สาธารณสุข</th>
                            </tr>
                            <tr class="font-bold">
                                <th class="border border-black bg-[#fff2cc] p-1">รายใหม่</th>
                                <th class="border border-black bg-white p-1">รายเก่า</th>
                                <th class="border border-black bg-white p-1">สะสม</th>
                                <th class="border border-black bg-white p-1">หายป่วย</th>
                            </tr>
                        </thead>
                        <tbody class="font-bold">
                            @foreach($publicHospTable as $pRow)
                                <tr>
                                    <td class="border border-black font-bold p-1.5 bg-[#fce4d6]">{{ $pRow['unit_name'] }}</td>
                                    <td class="border border-black font-bold bg-[#fff2cc] p-1.5">{{ $pRow['new'] }}</td>
                                    <td class="border border-black font-bold bg-white p-1.5">{{ $pRow['old'] }}</td>
                                    <td class="border border-black font-bold bg-white p-1.5">{{ $pRow['cum'] }}</td>
                                    <td class="border border-black font-bold bg-white p-1.5">{{ $pRow['recovered'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <!-- 📋 กล่องแสดงรายชื่อทหารที่ Admit พร้อมชื่อหน่วยฝึกหลังชื่อทหาร และ Dx. -->
                    <div class="p-3.5 bg-white rounded-xl border border-rose-200 min-h-[90px] flex flex-col justify-center">
                        @forelse($activeIpdList as $ipd)
                        @if($ipd['hosp_type'] === 'รพ.สาธารณสุข')
                            @php
                                $cleanSoldierName = trim($ipd['full_name'] ?? '');
                                $noRankName = trim(preg_replace('/^(พลฯ|พลทหาร)\s*/u', '', $cleanSoldierName));
                                $resolvedUnitName = $ipd['unit_name']
                                    ?? $ipd['unit']
                                    ?? ($patientUnitLookup[$cleanSoldierName] ?? null)
                                    ?? ($patientUnitLookup[$noRankName] ?? null);
                            @endphp
                            <div class="text-sm font-bold text-slate-800 leading-relaxed mb-2 last:mb-0">
                                - {{ $ipd['full_name'] }}
                                @if(!empty($resolvedUnitName))
                                    <span class="text-xs text-slate-500">({{ $resolvedUnitName }})</span>
                                @endif
                                <span class="text-indigo-700">Dx. {{ $ipd['dx'] }}</span><br>
                                <span class="text-xs text-slate-600 ml-2">Admit วันที่ {{ $ipd['admit_date'] }} ({{ $ipd['hosp_type'] }})</span>
                            </div>
                        @endif
                        @empty
                            <div class="text-xs text-slate-400 text-center italic">
                                - ไม่มีผู้ป่วยนอนพักรักษาตัวในโรงพยาบาล ณ วันนี้ -
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- 🎖️ กล่องขวาล่าง: ตารางการนำปลด (ตัวเลขตัวหนาทั้งหมด) -->
                @php
                    $safeDcSummary = $dischargeSummary ?? ['physical_total' => 0, 'mental_total' => 0, 'grand_total' => 0, 'completed' => 0, 'in_progress' => 0];
                    $safeDcTable   = $dischargeTable ?? [];
                    $safeDcList    = $dischargeList ?? collect();
                @endphp
                <div class="bg-[#eef7f4] p-4 rounded-xl border border-teal-300 shadow-sm space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="inline-block bg-[#a2c4c9] text-slate-900 font-black text-sm px-4 py-1.5 rounded-lg border border-teal-400">
                            ตารางการนำปลด
                        </div>
                        <a href="{{ route('backend.military_discharges.index', ['intake_batch' => $selectedBatch]) }}" class="text-[11px] font-bold text-teal-800 hover:underline print:hidden">
                            <i class="fa-solid fa-folder-open mr-1"></i>ดูทะเบียนนำปลด ({{ $safeDcSummary['grand_total'] }})
                        </a>
                    </div>

                    <table class="w-full text-xs text-center border-collapse border border-black bg-white font-bold">
                        <thead>
                            <tr class="font-bold">
                                <th rowspan="2" class="border border-black bg-[#ffe699] p-1.5 align-middle">หน่วยฝึก</th>
                                <th colspan="2" class="border border-black bg-[#a2c4c9] p-1.5">ประเภทการนำปลด (นาย)</th>
                                <th rowspan="2" class="border border-black bg-[#ffe699] p-1.5 align-middle">รวม</th>
                                <th rowspan="2" class="border border-black bg-[#e2efda] p-1.5 align-middle">ปลดแล้ว</th>
                            </tr>
                            <tr class="font-bold">
                                <th class="border border-black bg-[#d0e0e3] p-1">ปลดทางกาย</th>
                                <th class="border border-black bg-[#d9d2e9] p-1">ปลดทางสุขภาพจิต</th>
                            </tr>
                        </thead>
                        <tbody class="font-bold">
                            @foreach($safeDcTable as $dRow)
                                <tr>
                                    <td class="border border-black font-bold p-1.5 bg-[#fce4d6]">{{ $dRow['unit_name'] }}</td>
                                    <td class="border border-black bg-white font-bold text-teal-900 p-1.5">{{ $dRow['physical_count'] ?: '-' }}</td>
                                    <td class="border border-black bg-white font-bold text-purple-900 p-1.5">{{ $dRow['mental_count'] ?: '-' }}</td>
                                    <td class="border border-black bg-[#fff2cc] font-black p-1.5">{{ $dRow['total_count'] ?: '-' }}</td>
                                    <td class="border border-black bg-[#e2efda] font-bold p-1.5">{{ $dRow['completed'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-black">
                                <td class="border border-black bg-[#ffe699] p-1.5">รวมทั้งหมด</td>
                                <td class="border border-black bg-[#d0e0e3] p-1.5 text-teal-950">{{ $safeDcSummary['physical_total'] }}</td>
                                <td class="border border-black bg-[#d9d2e9] p-1.5 text-purple-950">{{ $safeDcSummary['mental_total'] }}</td>
                                <td class="border border-black bg-[#ffc000] p-1.5">{{ $safeDcSummary['grand_total'] }}</td>
                                <td class="border border-black bg-[#c5e0b4] p-1.5">{{ $safeDcSummary['completed'] }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- รายชื่อพลทหารที่ยื่นนำปลด พร้อมป้ายแยก ปลดทางกาย / ปลดทางสุขภาพจิต -->
                    @if($safeDcList->count() > 0)
                        <div class="p-3 bg-white rounded-xl border border-teal-200 space-y-2">
                            @foreach($safeDcList as $dcItem)
                                <div class="text-xs font-bold text-slate-800 leading-snug border-b border-slate-100 last:border-0 pb-1.5 last:pb-0">
                                    <div class="flex flex-wrap items-center justify-between gap-1">
                                        <span>- {{ $dcItem['full_name'] }} <span class="text-slate-500">({{ $dcItem['unit_name'] }})</span></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black border {{ $dcItem['discharge_group'] === 'mental' ? 'bg-purple-100 text-purple-800 border-purple-300' : 'bg-teal-100 text-teal-800 border-teal-300' }}">
                                            {{ $dcItem['group_label'] }}
                                        </span>
                                    </div>
                                    <div class="text-indigo-700 mt-0.5 pl-2">
                                        Dx. {{ $dcItem['dx'] }}
                                        <span class="text-[10px] text-slate-500 font-semibold ml-1">[{{ $dcItem['status_label'] }}]</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

        </div>

        <!-- 🎖️ แถบเครดิตระบบ M-CARE System ด้านล่างสุดของภาพสไลด์ -->
        <div class="mt-4 pt-2.5 border-t border-slate-300/80 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] font-bold text-slate-600 px-1">
            <div class="flex items-center gap-1.5">
                <span class="px-2 py-0.5 rounded bg-[#1b6b4a] text-white font-black text-[10px]">M-CARE System</span>
                <span class="text-slate-800">Military Conscript Administration &amp; Remote Health Care</span>
            </div>
            <div class="text-slate-600">
                ระบบสารสนเทศเพื่อการบริหารจัดการสุขภาพทหารใหม่แบบครบวงจร • โรงพยาบาลค่ายกฤษณ์สีวะรา
            </div>
        </div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- ➕ Modal บันทึกผู้ป่วยรายใหม่ / สุขภาพจิต / นำปลด (คงระบบตรวจสอบรายช่อง 100%) -->
<!-- ========================================================================= -->
<div id="addMorbidityModal" class="{{ $errors->any() ? '' : 'hidden' }} fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 my-8">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
            <h3 class="text-lg font-black text-slate-800"><i class="fa-solid fa-notes-medical text-emerald-500 mr-2"></i>บันทึกอาการเจ็บป่วย / สุขภาพจิต / นำปลด</h3>
            <button type="button" onclick="closeAddMorbidityModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="{{ route('backend.morbidity_reports.store') }}" method="POST" novalidate onsubmit="return validateMorbidityForm(event)" class="space-y-4">
            @csrf

            <!-- 🔍 1. ช่องค้นหาพลทหารอัจฉริยะ -->
            <div class="relative">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    ค้นหาและเลือกพลทหาร ({{ $selectedBatch === 'all' ? 'ทุกผลัด' : 'ผลัด ' . $selectedBatch }}) <span class="text-rose-500">*</span>
                </label>

                <input type="hidden" name="patient_id" id="modal_patient_id" value="{{ old('patient_id') }}">

                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                    </span>
                    <input type="text" id="recruit_search_box" autocomplete="off"
                           onfocus="openRecruitDropdown()" oninput="filterRecruitDropdown(this.value); clearMorbError('recruit_search_box', 'err_morb_patient');"
                           placeholder="พิมพ์ชื่อ, นามสกุล, เลข ปชช., HN หรือหน่วยฝึก..."
                           class="w-full pl-10 pr-9 py-2.5 rounded-xl border-2 {{ $errors->has('patient_id') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 text-sm font-medium text-slate-800 outline-none transition-all">
                    <button type="button" id="btn_clear_recruit" onclick="clearSelectedRecruit()" class="hidden absolute inset-y-0 right-0 pr-3 items-center text-slate-400 hover:text-rose-500">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <p id="err_morb_patient" class="{{ $errors->has('patient_id') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1.5 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first('patient_id') ?: 'กรุณาพิมพ์ค้นหาแล้วคลิกเลือกรายชื่อพลทหารจากรายการครับ' }}</span>
                </p>

                <!-- รายการ Dropdown ที่กรองตามคำค้นหา -->
                <div id="recruit_dropdown_list" class="hidden absolute left-0 right-0 mt-1.5 max-h-56 overflow-y-auto bg-white rounded-2xl shadow-xl border-2 border-slate-200 z-50 divide-y divide-slate-100">
                    @foreach($batchPatients as $pt)
                        @php
                            $ptUnitName = $pt->militaryUnit->short_name ?? 'ไม่ระบุหน่วย';
                            $ptFullName = ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');
                            $searchData = strtolower($ptFullName . ' ' . ($pt->cid ?? '') . ' ' . ($pt->hn ?? '') . ' ' . $ptUnitName);
                        @endphp
                        <div onclick="selectRecruitItem('{{ $pt->id }}', '{{ addslashes($ptFullName) }} ({{ addslashes($ptUnitName) }} | HN: {{ $pt->hn ?: 'ไม่มี' }})')"
                             data-search="{{ $searchData }}"
                             class="recruit-option-item px-4 py-2.5 hover:bg-emerald-50 cursor-pointer transition-colors flex items-center justify-between gap-2">
                            <div>
                                <div class="font-bold text-slate-800 text-sm">{{ $ptFullName }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">ปชช: {{ $pt->cid ?? '-' }} | หน่วย: <b class="text-indigo-600">{{ $ptUnitName }}</b></div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $pt->hn ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' }} shrink-0">
                                HN: {{ $pt->hn ?: 'ไม่มี' }}
                            </span>
                        </div>
                    @endforeach
                    <div id="recruit_no_result" class="hidden px-4 py-4 text-center text-xs text-slate-400 italic">
                        ไม่พบรายชื่อพลทหารที่ค้นหา
                    </div>
                </div>
            </div>

            <!-- 2. หมวดหมู่การเจ็บป่วย และ ชนิดย่อย (ครบทั้ง 5 หมวด) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">หมวดหมู่การเจ็บป่วย <span class="text-rose-500">*</span></label>
                    <select name="category" id="modal_category" onchange="updateSubTypeOptions()" required 
                            class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-bold text-slate-800 outline-none transition-all">
                        <option value="respiratory" {{ old('category') === 'respiratory' ? 'selected' : '' }}>1. โรคระบบทางเดินหายใจ</option>
                        <option value="heat" {{ old('category') === 'heat' ? 'selected' : '' }}>2. การบาดเจ็บจากความร้อน</option>
                        <option value="other_ipd" {{ old('category') === 'other_ipd' ? 'selected' : '' }}>3. การเจ็บป่วยอื่นๆ (นอน รพ.)</option>
                        <option value="mental" {{ old('category') === 'mental' ? 'selected' : '' }}>4. เคสสุขภาพจิต / จิตเวช</option>
                        <option value="discharge" {{ old('category') === 'discharge' ? 'selected' : '' }}>5. การนำปลดทหารใหม่ (กาย/จิต)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">ชนิดย่อย / อาการ <span class="text-rose-500">*</span></label>
                    <select name="sub_type" id="modal_sub_type" required 
                            class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-bold text-slate-800 outline-none transition-all"></select>
                </div>
            </div>

            <!-- ตัวเลือกสถานที่รักษา (แสดงเมื่อเลือกโรคทางเดินหายใจ หรือ เคสสุขภาพจิต) -->
            <div id="wrapper_treatment_location">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">รูปแบบการดูแลรักษา</label>
                <select name="treatment_location" class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-semibold text-slate-800 outline-none transition-all">
                    <option value="ci">แยกกัก CI / รับยาและดูแลที่หน่วยฝึก</option>
                    <option value="hospital">นอน รพ. (Admit)</option>
                </select>
            </div>

            <!-- 3. ช่องกรอก Dx. พร้อมปุ่มดึงจาก HosXP (บังคับกรอก *) -->
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-xs font-bold text-slate-700">การวินิจฉัยโรค (Dx.) / อาการสำคัญ <span class="text-rose-500">*</span></label>
                    <button type="button" onclick="fetchDxFromHosxp()" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg border border-indigo-200 flex items-center gap-1 transition-colors">
                        <i class="fa-solid fa-cloud-arrow-down"></i> ดึง Dx. ล่าสุดจาก HosXP
                    </button>
                </div>
                <input type="text" name="diagnosis_text" id="modal_dx_text" value="{{ old('diagnosis_text') }}" required
                       oninput="clearMorbError('modal_dx_text', 'err_morb_dx')"
                       placeholder="เช่น Influenza A, Adjustment disorder, Asthma..." 
                       class="w-full rounded-xl border-2 {{ $errors->has('diagnosis_text') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-semibold text-slate-800 outline-none transition-all">
                <p id="err_morb_dx" class="{{ $errors->has('diagnosis_text') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first('diagnosis_text') ?: 'กรุณาระบุการวินิจฉัยโรค (Dx.) หรืออาการสำคัญครับ' }}</span>
                </p>
            </div>

            <!-- 4. วันที่เริ่มป่วย -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">วันที่เริ่มป่วย / วันที่ Admit <span class="text-rose-500">*</span></label>
                <input type="date" name="onset_date" id="modal_onset_date" value="{{ old('onset_date', $reportDate) }}" required 
                       onchange="clearMorbError('modal_onset_date', 'err_morb_date')"
                       class="w-full rounded-xl border-2 {{ $errors->has('onset_date') ? 'border-rose-500 bg-rose-50/30' : 'border-slate-300 bg-slate-50' }} focus:bg-white focus:border-emerald-500 text-sm p-2.5 font-semibold text-slate-800 outline-none transition-all">
                <p id="err_morb_date" class="{{ $errors->has('onset_date') ? '' : 'hidden' }} text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>กรุณาระบุวันที่เริ่มป่วยหรือวันที่ Admit</span>
                </p>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeAddMorbidityModal()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-bold transition-colors">ยกเลิก</button>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-md transition-colors">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- ✅ Modal จัดการเคสที่กำลังรักษาอยู่ (มีปุ่ม "ดูประวัติ" ครบถ้วน 100%) -->
<!-- ========================================================================= -->
<div id="manageActiveModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
            <h3 class="text-lg font-black text-slate-800"><i class="fa-solid fa-bed-pulse text-amber-500 mr-2"></i>จัดการผู้ป่วยที่กำลังรักษาอยู่ ({{ $activeCasesGrouped->count() }} นาย)</h3>
            <button type="button" onclick="document.getElementById('manageActiveModal').classList.add('hidden')" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto space-y-3 pr-1">
            @forelse($activeCasesGrouped as $activeCase)
                <div class="p-4 bg-slate-50 rounded-2xl border-2 border-slate-200 space-y-2.5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-800 text-base">
                                {{ $activeCase->patient->rank ?? '' }}{{ $activeCase->patient->first_name ?? '' }} {{ $activeCase->patient->last_name ?? '' }}
                                <span class="text-xs font-bold text-indigo-600 ml-1">({{ $activeCase->militaryUnit->short_name ?? '-' }})</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                @foreach($activeCase->badges as $badgeText)
                                    <span class="px-2.5 py-0.5 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-700 shadow-sm">
                                        {{ $badgeText }}
                                    </span>
                                @endforeach
                                <span class="text-xs text-slate-500 ml-1">
                                    | เริ่มป่วย/Admit: <b>{{ $activeCase->onset_date->addYears(543)->format('d/m/Y') }}</b>
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('backend.patients.show', $activeCase->patient_id) }}" 
                               class="px-3 py-2 bg-white hover:bg-slate-100 text-slate-600 border border-slate-300 rounded-xl text-xs font-bold transition-colors shadow-sm"
                               title="เปิดดูแฟ้มประวัติและ Timeline MEWS ของพลทหารนายนี้">
                                <i class="fa-solid fa-address-card"></i> ดูประวัติ
                            </a>

                            <form action="{{ route('backend.morbidity_reports.recover', $activeCase->primary_id) }}" method="POST">
                                @csrf @method('PATCH')
                                <input type="hidden" name="recovered_date" value="{{ $reportDate }}">
                                <button type="submit" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors">
                                    <i class="fa-solid fa-house-chimney-check mr-1"></i> หายป่วย / กลับหน่วยแล้ว
                                </button>
                            </form>

                            <form action="{{ route('backend.morbidity_reports.destroy', $activeCase->primary_id) }}" method="POST" onsubmit="return confirm('ยืนยันการลบรายการของผู้ป่วยรายนี้?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-3 py-2 bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 rounded-xl text-xs font-bold transition-colors">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- ช่องแก้ไขชื่อโรค (Dx.) ด่วน -->
                    <form action="{{ route('backend.morbidity_reports.recover', $activeCase->primary_id) }}" method="POST" class="flex gap-2 pt-2 border-t border-slate-200/80">
                        @csrf @method('PATCH')
                        <input type="hidden" name="update_dx_only" value="1">
                        <input type="text" name="diagnosis_text" value="{{ $activeCase->diagnosis_text }}" placeholder="ระบุ Dx. เช่น Acute Pharyngitis..." 
                               class="flex-1 px-3 py-2 bg-white border-2 border-slate-300 focus:border-indigo-500 rounded-xl text-xs font-medium text-slate-800 outline-none">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shrink-0 shadow-sm transition-colors">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> บันทึก Dx.
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-center text-sm text-slate-400 py-8">ไม่มีผู้ป่วยที่กำลังรักษาอยู่ในขณะนี้ครับ</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- ใช้ html2canvas-pro ที่รองรับระบบสี oklch() ของ Tailwind CSS เวอร์ชันใหม่โดยตรง -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas-pro@1.5.8/dist/html2canvas-pro.min.js"></script>
<script>
    const subTypeMap = {
        'respiratory': [
            { val: 'covid', label: 'โควิด-19 (COVID-19)' },
            { val: 'flu', label: 'ไข้หวัดใหญ่ (Influenza)' }
        ],
        'heat': [
            { val: 'heat_rash', label: '[ไม่รุนแรง] 1. ผื่นแดด' },
            { val: 'heat_edema', label: '[ไม่รุนแรง] 2. บวมแดด' },
            { val: 'heat_cramps', label: '[ไม่รุนแรง] 3. ตะคริวแดด' },
            { val: 'heat_tetany', label: '[ไม่รุนแรง] 4. เกร็งแดด' },
            { val: 'heat_syncope', label: '[ไม่รุนแรง] 5. ลมแดด' },
            { val: 'mild_rhabdo', label: '[ไม่รุนแรง] 6. กล้ามเนื้อสลายไม่รุนแรง' },
            { val: 'severe_rhabdo', label: '[รุนแรง] 7. กล้ามเนื้อสลายรุนแรง' },
            { val: 'heat_exhaustion', label: '[รุนแรง] 8. ฮีทเอ็กซอสชั่น (Heat Exhaustion)' },
            { val: 'heat_stroke', label: '[รุนแรง] 9. ฮีทสโตรก (Heat Stroke)' }
        ],
        'other_ipd': [
            { val: 'camp_hospital', label: 'นอน รพ.ค่ายกฤษณ์สีวะรา' },
            { val: 'public_hospital', label: 'นอน รพ.สาธารณสุข (ส่งต่อ)' }
        ],
        'mental': [
            { val: 'mental_opd', label: 'จิตเวชรับยาต่อเนื่อง (ดูแลที่หน่วยฝึก)' },
            { val: 'mental_ipd', label: 'จิตเวชส่งรักษา รพ. (Admit / Refer)' },
            { val: 'adjustment', label: 'ภาวะความเครียด / การปรับตัว / ซึมเศร้า' },
            { val: 'substance',  label: 'จิตเวชจากสารเสพติด / ถอนพิษยา' }
        ],
        'discharge': [
            { val: 'discharge_physical', label: 'ปลดทางกาย (โรคทางร่างกาย)' },
            { val: 'discharge_mental',   label: 'ปลดทางสุขภาพจิต (โรคทางจิตเวช)' }
        ]
    };

    function openRecruitDropdown() {
        document.getElementById('recruit_dropdown_list')?.classList.remove('hidden');
    }

    function filterRecruitDropdown(keyword) {
        const listEl = document.getElementById('recruit_dropdown_list');
        const items = document.querySelectorAll('.recruit-option-item');
        const noRes = document.getElementById('recruit_no_result');
        const clearBtn = document.getElementById('btn_clear_recruit');

        if (listEl) listEl.classList.remove('hidden');
        document.getElementById('modal_patient_id').value = '';

        const cleanKw = keyword.trim().toLowerCase();
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', cleanKw.length === 0);
            clearBtn.classList.toggle('flex', cleanKw.length > 0);
        }

        let matchCount = 0;
        items.forEach(item => {
            const hay = item.getAttribute('data-search') || '';
            if (cleanKw === '' || hay.includes(cleanKw)) {
                item.classList.remove('hidden');
                matchCount++;
            } else {
                item.classList.add('hidden');
            }
        });

        if (noRes) {
            noRes.classList.toggle('hidden', matchCount > 0);
        }
    }

    function markMorbError(inputId, errId) {
        const el = document.getElementById(inputId);
        const err = document.getElementById(errId);
        if (el) {
            el.classList.remove('border-slate-300', 'bg-slate-50', 'border-emerald-500', 'bg-emerald-50/30');
            el.classList.add('border-rose-500', 'bg-rose-50/40', 'ring-2', 'ring-rose-500/20');
        }
        if (err) err.classList.remove('hidden');
    }

    function clearMorbError(inputId, errId) {
        const el = document.getElementById(inputId);
        const err = document.getElementById(errId);
        if (el) {
            el.classList.remove('border-rose-500', 'bg-rose-50/40', 'ring-2', 'ring-rose-500/20');
            el.classList.add('border-slate-300', 'bg-slate-50');
        }
        if (err) err.classList.add('hidden');
    }

    function selectRecruitItem(id, displayText) {
        document.getElementById('modal_patient_id').value = id;
        const box = document.getElementById('recruit_search_box');
        if (box) {
            box.value = displayText;
            clearMorbError('recruit_search_box', 'err_morb_patient');
            box.classList.add('border-emerald-500', 'bg-emerald-50/30', 'font-bold');
        }
        const clearBtn = document.getElementById('btn_clear_recruit');
        if (clearBtn) {
            clearBtn.classList.remove('hidden');
            clearBtn.classList.add('flex');
        }
        document.getElementById('recruit_dropdown_list')?.classList.add('hidden');
    }

    function validateMorbidityForm(e) {
        let missing = [];
        let firstEl = null;

        if (!document.getElementById('modal_patient_id')?.value.trim()) {
            markMorbError('recruit_search_box', 'err_morb_patient');
            missing.push('รายชื่อพลทหาร (ต้องคลิกเลือกจากรายการ)');
            if (!firstEl) firstEl = document.getElementById('recruit_search_box');
        }

        if (!document.getElementById('modal_dx_text')?.value.trim()) {
            markMorbError('modal_dx_text', 'err_morb_dx');
            missing.push('การวินิจฉัยโรค (Dx.) / อาการสำคัญ');
            if (!firstEl) firstEl = document.getElementById('modal_dx_text');
        }

        if (!document.getElementById('modal_onset_date')?.value.trim()) {
            markMorbError('modal_onset_date', 'err_morb_date');
            missing.push('วันที่เริ่มป่วย / วันที่ Admit');
            if (!firstEl) firstEl = document.getElementById('modal_onset_date');
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

    document.addEventListener('DOMContentLoaded', function() {
        updateSubTypeOptions();
    });

    function clearSelectedRecruit() {
        document.getElementById('modal_patient_id').value = '';
        const box = document.getElementById('recruit_search_box');
        if (box) {
            box.value = '';
            box.classList.remove('border-emerald-500', 'bg-emerald-50/30', 'font-bold');
            box.focus();
        }
        filterRecruitDropdown('');
    }

    document.addEventListener('click', function(e) {
        const box = document.getElementById('recruit_search_box');
        const list = document.getElementById('recruit_dropdown_list');
        if (box && list && !box.contains(e.target) && !list.contains(e.target)) {
            list.classList.add('hidden');
        }
    });

    function updateSubTypeOptions() {
        const cat = document.getElementById('modal_category').value;
        const subSelect = document.getElementById('modal_sub_type');
        const locWrapper = document.getElementById('wrapper_treatment_location');

        subSelect.innerHTML = '';
        (subTypeMap[cat] || []).forEach(item => {
            subSelect.insertAdjacentHTML('beforeend', `<option value="${item.val}">${item.label}</option>`);
        });

        if (cat === 'respiratory' || cat === 'mental') {
            locWrapper.classList.remove('hidden');
        } else {
            locWrapper.classList.add('hidden');
        }
    }

    function openAddMorbidityModal() {
        updateSubTypeOptions();
        document.getElementById('addMorbidityModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('recruit_search_box')?.focus(), 100);
    }

    function closeAddMorbidityModal() {
        document.getElementById('addMorbidityModal').classList.add('hidden');
    }

    function openManageActiveCasesModal() {
        document.getElementById('manageActiveModal').classList.remove('hidden');
    }

    function fetchDxFromHosxp() {
        const patientId = document.getElementById('modal_patient_id').value;
        if (!patientId) {
            Swal.fire({ icon: 'warning', title: 'กรุณาค้นหาและคลิกเลือกพลทหารก่อนครับ' });
            return;
        }

        Swal.fire({ title: 'กำลังดึง Dx. จาก HosXP...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch(`{{ route('backend.morbidity_reports.fetch_hosxp_dx') }}?patient_id=${patientId}`)
            .then(res => res.json())
            .then(data => {
                Swal.close();
                if (data.status === 'success') {
                    document.getElementById('modal_dx_text').value = data.dx_text;
                    clearMorbError('modal_dx_text', 'err_morb_dx');
                    if (data.visit_date) {
                        document.getElementById('modal_onset_date').value = data.visit_date;
                    }
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ดึง Dx. สำเร็จ!', showConfirmButton: false, timer: 2000 });
                } else {
                    Swal.fire({ icon: 'info', title: 'แจ้งเตือน', text: data.message });
                }
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ HosXP' });
            });
    }

    function sanitizeOklchColorsInClone(clonedDoc) {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        const ctx = canvas.getContext('2d');

        const toRgb = (val) => {
            if (!val || (!val.includes('oklch') && !val.includes('oklab') && !val.includes('color-mix'))) return val;
            try {
                ctx.fillStyle = '#000000';
                ctx.fillStyle = val;
                return ctx.fillStyle;
            } catch (e) {
                return '#1e293b';
            }
        };

        const colorProps = ['color', 'backgroundColor', 'borderColor', 'borderTopColor', 'borderRightColor', 'borderBottomColor', 'borderLeftColor', 'outlineColor'];
        const allElements = clonedDoc.querySelectorAll('#morbidity-slide-board, #morbidity-slide-board *');

        allElements.forEach(el => {
            const win = clonedDoc.defaultView || window;
            const computed = win.getComputedStyle(el);
            colorProps.forEach(prop => {
                const val = computed[prop];
                if (val && (val.includes('oklch') || val.includes('oklab') || val.includes('color-mix'))) {
                    el.style[prop] = toRgb(val);
                }
            });
            if (computed.boxShadow && (computed.boxShadow.includes('oklch') || computed.boxShadow.includes('oklab'))) {
                el.style.boxShadow = 'none';
            }
        });
    }

    async function exportSlideAsImage() {
        const board = document.getElementById('morbidity-slide-board');
        const btn = document.getElementById('btn-export-slide');
        if (!board || !btn) return;

        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-wait');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสร้างภาพ...';

        try {
            const canvas = await html2canvas(board, {
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#fdf8f5',
                windowWidth: 1440,
                onclone: (clonedDoc) => {
                    sanitizeOklchColorsInClone(clonedDoc);
                }
            });

            const link = document.createElement('a');
            link.download = `รายงานเจ็บป่วยทหารใหม่_ผลัด_{{ str_replace('/', '-', $selectedBatch) }}_{{ $reportDate }}.jpg`;
            link.href = canvas.toDataURL('image/jpeg', 0.95);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'บันทึกภาพสไลด์รายงาน (JPG) สำเร็จ!',
                showConfirmButton: false,
                timer: 2500
            });
        } catch (err) {
            console.error('Export Slide Error:', err);
            Swal.fire({
                icon: 'error',
                title: 'สร้างรูปภาพไม่สำเร็จ',
                text: 'เกิดข้อผิดพลาด: ' + (err.message || 'ไม่สามารถแคปภาพหน้าจอได้')
            });
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-wait');
            btn.innerHTML = oldHtml;
        }
    }
</script>
@endsection