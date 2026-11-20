<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบบันทึก KSVR MEWS - {{ $mews->patient->first_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- ไลบรารี html2canvas สำหรับสร้างไฟล์รูปภาพ -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f1f5f9; }
        
        /* บังคับตั้งค่ากระดาษเป็น A4 แนวตั้ง (Portrait) และบังคับให้โชว์สีพื้นหลังเวลาปริ้นท์ */
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background-color: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-hidden { display: none !important; }
        }
        
        /* สไตล์ตารางเพื่อให้เส้นขอบคมชัดแบบกระดาษแบบฟอร์ม */
        .mews-table th, .mews-table td { border: 1px solid #000; padding: 2px 4px; text-align: center; vertical-align: middle; font-size: 11px; }
        .mews-table th { font-weight: bold; }
        
        /* กล่อง Checklist อาการฉุกเฉิน */
        .check-box-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; border: 1px solid #000; font-size: 10px; }
        .check-box-item { border: 1px solid #e2e8f0; padding: 4px; }
        .check-box-item p { margin-bottom: 2px; }
    </style>
</head>
<body class="text-black">

    @php
        $rawRoles = auth()->check() ? (auth()->user()->roles ?? auth()->user()->role ?? []) : [];
        $userRoles = is_string($rawRoles) ? json_decode($rawRoles, true) : (array) $rawRoles;
        $isAdmin = in_array('admin', $userRoles);

        $avpuVal = $mews->consciousness ?? $mews->avpu ?? null;
        $o2satVal = $mews->o2sat ?? $mews->o2_sat ?? null;
        $noteText = trim((string)($mews->symptoms_note ?? ''));

        $caseAssessments = \App\Models\MewsAssessment::where('case_id', $mews->case_id)
            ->orderBy('created_at', 'asc')
            ->get();
        if ($caseAssessments->isEmpty()) {
            $caseAssessments = collect([$mews]);
        }
    @endphp

    <!-- โซนเครื่องมือ (ซ่อนตอนปริ้นท์) -->
    <div class="print-hidden bg-slate-800 text-white p-4 sticky top-0 z-50 shadow-md flex justify-end items-center gap-3">
        <button type="button" onclick="downloadAsImage()" id="btn-download" class="px-5 py-2 bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-lg shadow-lg flex items-center gap-2 transition-transform hover:-translate-y-0.5">
            <i class="fa-solid fa-image"></i> บันทึกเป็นรูปภาพ (JPG)
        </button>

        <button type="button" onclick="window.print()" class="px-5 py-2 bg-rose-500 hover:bg-rose-600 text-white font-bold rounded-lg shadow-lg flex items-center gap-2 transition-transform hover:-translate-y-0.5">
            <i class="fa-solid fa-print"></i> พิมพ์เอกสาร (Print)
        </button>
    </div>

    <!-- โซนกระดาษ A4 -->
    <div id="mews-paper" class="max-w-[210mm] mx-auto bg-white p-6 shadow-xl print:shadow-none print:m-0 print:p-0">

        <!-- ส่วนที่ 1: ข้อมูลผู้ป่วย (Patient Info Box) -->
        <div class="border-[1.5px] border-black p-3 mb-3 text-[13px] leading-relaxed">
            <div class="flex justify-between">
                <span><b>หน่วยฝึกทหารใหม่</b> {{ $mews->patient->militaryUnit->name ?? $mews->patient->company ?? '-' }}</span>
                <span><b>หมวด/สังกัด</b> {{ $mews->patient->battalion ?? '-' }}</span>
                <span><b>HN</b> {{ $mews->patient->hn ?: '..................' }}</span>
            </div>
            <div class="flex justify-between mt-1">
                <span class="flex-1"><b>ชื่อ</b> {{ $mews->patient->rank }}{{ $mews->patient->first_name }} <b>สกุล</b> {{ $mews->patient->last_name }}</span>
                <span class="w-28"><b>อายุ</b> {{ $mews->patient->dob ? \Carbon\Carbon::parse($mews->patient->dob)->age : '-' }} ปี</span>
                <span class="w-52"><b>เลขบัตรปชช.</b> {{ $mews->patient->cid }}</span>
            </div>
            <div class="flex justify-between mt-1">
                <span class="flex-1">
                    <b>โรคประจำตัว</b> 
                    @if($isAdmin && ($mews->patient->chronic_disease_status ?? 'none') === 'yes')
                        <span class="underline decoration-dotted font-semibold">{{ $mews->patient->chronic_disease_detail ?: 'มีโรคประจำตัว' }}</span>
                    @elseif($isAdmin && ($mews->patient->chronic_disease_status ?? 'none') === 'other')
                        <span class="underline decoration-dotted font-semibold">{{ $mews->patient->chronic_disease_detail ?: '-' }}</span>
                    @elseif($isAdmin)
                        ไม่มีโรคประจำตัว
                    @else
                        .....................................................
                    @endif
                </span>
                <span class="flex-1"><b>สิทธิการรักษา</b> {{ $mews->patient->pttype_name ?: '.....................................................' }}</span>
            </div>
            <div class="flex justify-between items-baseline mt-1 gap-2">
                <span class="flex-1">
                    <b>อาการสำคัญ / บันทึกเพิ่มเติม</b> 
                    @if($noteText !== '')
                        <span class="underline decoration-dotted font-semibold px-1">{{ $noteText }}</span>
                    @else
                        .......................................................................
                    @endif
                </span>
                <span class="shrink-0"><b>นน.</b> {{ $mews->patient->weight ?: '.......' }} กก. &nbsp;&nbsp;<b>ส่วนสูง</b> {{ $mews->patient->height ?: '.......' }} ซม.</span>
            </div>
            <!-- 🌟 เพิ่มจุดไข่ปลาบรรทัดถัดไป สำหรับเจ้าหน้าที่จุดคัดกรองเขียนบันทึกเพิ่มเติม -->
            <div class="mt-2 border-b-[1.5px] border-dotted border-black w-full h-3"></div>
        </div>

        <!-- ส่วนที่ 2: หัวเรื่อง -->
        <div class="text-center mb-2 relative">
            <div class="font-bold underline text-sm">คัดกรอง KSVR MEWS</div>
            
            <div class="absolute right-0 top-0 text-[10px] font-bold text-slate-500 border border-slate-300 px-2 py-0.5 rounded bg-slate-50">
                อ้างอิงเกณฑ์: {{ $mews->template->name ?? 'KSVR MEWS มาตรฐาน' }}
            </div>

            <div class="text-[13px] mt-1">
                อาการสำคัญฉุกเฉิน 
                <span class="ml-2 inline-flex items-center gap-1"><div class="w-3 h-3 border border-black rounded-full {{ ($mews->risk_level == 'emergency' || count($symptomCodes) > 0) ? 'bg-black' : '' }}"></div> ใช่</span>
                <span class="ml-2 inline-flex items-center gap-1"><div class="w-3 h-3 border border-black rounded-full {{ ($mews->risk_level != 'emergency' && count($symptomCodes) == 0) ? 'bg-black' : '' }}"></div> ไม่ใช่</span>
            </div>
        </div>

        <!-- ส่วนที่ 3: ตาราง MEWS และ เกณฑ์ -->
        <div class="flex items-start gap-2 mb-2">
            
            <!-- ฝั่งซ้าย: ตารางคะแนน MEWS -->
            <div class="flex-1">
                <table class="mews-table w-full mb-2">
                    <thead>
                        <tr>
                            <th colspan="10" class="text-xs">Krit-Siwara Modified Early Warning Score (KSVR MEWS)</th>
                        </tr>
                        <tr>
                            <th>Score</th>
                            <th class="bg-[#ef4444] text-white">3</th>
                            <th class="bg-[#f97316] text-white">2</th>
                            <th class="bg-[#fde047]">1</th>
                            <th class="bg-[#86efac]">0</th>
                            <th class="bg-[#fde047]">1</th>
                            <th class="bg-[#f97316] text-white">2</th>
                            <th class="bg-[#ef4444] text-white">3</th>
                            <th class="w-11">Data</th>
                            <!-- 🌟 ช่องรับรองจุดคัดกรอง (เขียนมือ) ต่อจาก Data -->
                            <th class="w-14 bg-slate-100 leading-tight text-[10px]">จุดคัดกรอง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="font-bold text-left px-1">SBP</td>
                            <td class="bg-[#ef4444] text-white">≤70</td>
                            <td class="bg-[#f97316] text-white">71-80</td>
                            <td class="bg-[#fde047]">81-100</td>
                            <td class="bg-[#86efac]">101-140</td>
                            <td class="bg-[#fde047]">141-179</td>
                            <td class="bg-[#f97316] text-white">180-199</td>
                            <td class="bg-[#ef4444] text-white">≥200</td>
                            <td class="font-bold">{{ $mews->systolic_bp ?? '-' }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="font-bold text-left px-1">Pulse</td>
                            <td class="bg-[#ef4444] text-white">≤39</td>
                            <td class="bg-[#f97316] text-white">40-50</td>
                            <td class="bg-[#fde047]">51-59</td>
                            <td class="bg-[#86efac]">60-100</td>
                            <td class="bg-[#fde047]">101-110</td>
                            <td class="bg-[#f97316] text-white">111-120</td>
                            <td class="bg-[#ef4444] text-white">≥121</td>
                            <td class="font-bold">{{ $mews->heart_rate ?? '-' }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="font-bold text-left px-1">RR</td>
                            <td class="bg-gray-200"></td>
                            <td class="bg-[#f97316] text-white">≤8</td>
                            <td class="bg-[#fde047]">9-11</td>
                            <td class="bg-[#86efac]">12-20</td>
                            <td class="bg-[#fde047]">21-25</td>
                            <td class="bg-[#f97316] text-white">26-29</td>
                            <td class="bg-[#ef4444] text-white">≥30</td>
                            <td class="font-bold">{{ $mews->respiratory_rate ?? '-' }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="font-bold text-left px-1">Temp</td>
                            <td class="bg-gray-200"></td>
                            <td class="bg-[#f97316] text-white">≤35.5</td>
                            <td class="bg-[#fde047]">35.6-36.4</td>
                            <td class="bg-[#86efac]">36.5-37.4</td>
                            <td class="bg-[#fde047]">37.5-38</td>
                            <td class="bg-[#f97316] text-white">38.1-39.5</td>
                            <td class="bg-[#ef4444] text-white">≥39.6</td>
                            <td class="font-bold">{{ $mews->temperature ?? '-' }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="font-bold text-left px-1">AVPU</td>
                            <td class="bg-gray-200"></td>
                            <td class="bg-gray-200"></td>
                            <td class="bg-[#fde047]">Confused</td>
                            <td class="bg-[#86efac]">Alert</td>
                            <td class="bg-[#fde047]">Response<br>to pain</td>
                            <td class="bg-[#f97316] text-white">Response<br>to voice</td>
                            <td class="bg-[#ef4444] text-white">Unresponsive</td>
                            <td class="font-bold">{{ $avpuVal ? strtoupper($avpuVal) : '-' }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="font-bold text-left px-1">O2 sat</td>
                            <td class="bg-[#ef4444] text-white">≤84</td>
                            <td class="bg-[#f97316] text-white">85-89</td>
                            <td class="bg-[#fde047]">90-94</td>
                            <td class="bg-[#86efac]">95-100</td>
                            <td class="bg-gray-200"></td>
                            <td class="bg-gray-200"></td>
                            <td class="bg-gray-200"></td>
                            <td class="font-bold">{{ $o2satVal ?? '-' }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <!-- แถบล่าง: Pain Score และ ตารางผลรวม (เพิ่มช่องรับรอง จุดคัดกรอง แบบเขียนมือ) -->
                <div class="flex items-stretch border border-black h-[108px]">
                    
                    <!-- ฝั่งซ้าย: Wong-Baker FACES Scale -->
                    <div class="w-[62%] p-1 flex flex-col justify-between border-r border-black bg-white relative">
                        <div class="text-[10px] text-center font-bold underline mb-1">Pain Measurements score</div>
                        
                        <div class="grid grid-cols-6 items-end px-0.5 pb-4 w-full text-[8.5px] font-bold text-center leading-tight">
                            <div class="flex flex-col items-center justify-end w-full">
                                <span class="mb-1 text-center w-full">No Pain</span>
                                <i class="fa-solid fa-face-smile text-[#16a34a] text-[24px]" style="-webkit-text-stroke: 0.7px #000; line-height: 1;"></i>
                            </div>
                            <div class="flex flex-col items-center justify-end w-full">
                                <span class="mb-1 text-center w-full">Mild</span>
                                <i class="fa-solid fa-face-smile text-[#84cc16] text-[24px]" style="-webkit-text-stroke: 0.7px #000; line-height: 1;"></i>
                            </div>
                            <div class="flex flex-col items-center justify-end w-full">
                                <span class="mb-1 text-center w-full">Moderate</span>
                                <i class="fa-solid fa-face-meh text-[#facc15] text-[24px]" style="-webkit-text-stroke: 0.7px #000; line-height: 1;"></i>
                            </div>
                            <div class="flex flex-col items-center justify-end w-full">
                                <span class="mb-1 text-center w-full">Severe</span>
                                <i class="fa-solid fa-face-frown text-[#f59e0b] text-[24px]" style="-webkit-text-stroke: 0.7px #000; line-height: 1;"></i>
                            </div>
                            <div class="flex flex-col items-center justify-end w-full">
                                <span class="mb-1 text-center w-full">Very Severe</span>
                                <i class="fa-solid fa-face-angry text-[#ea580c] text-[24px]" style="-webkit-text-stroke: 0.7px #000; line-height: 1;"></i>
                            </div>
                            <div class="flex flex-col items-center justify-end w-full">
                                <span class="mb-1 text-center w-full whitespace-nowrap leading-[9px]">Worst Pain<br>Possible</span>
                                <i class="fa-solid fa-face-sad-cry text-[#dc2626] text-[24px]" style="-webkit-text-stroke: 0.7px #000; line-height: 1;"></i>
                            </div>
                        </div>

                        <div class="absolute bottom-1.5 left-0 w-full grid grid-cols-6 px-0.5 text-[10px] font-bold text-center">
                            <div>0</div>
                            <div>1-3</div>
                            <div class="col-span-2">4-6</div>
                            <div>7-9</div>
                            <div>10</div>
                        </div>
                    </div>

                    <!-- ฝั่งขวา: ผลรวมคะแนน, Pain score และผู้ประเมิน (มีช่องเขียนรับรองของจุดคัดกรองต่อท้าย) -->
                    <div class="w-[38%] flex flex-col text-[10px]">
                        <!-- หัวคอลัมน์ย่อย -->
                        <div class="grid grid-cols-12 border-b border-black bg-slate-100 font-bold text-[9.5px] h-[18px]">
                            <div class="col-span-4 border-r border-black flex items-center justify-center">รายการ</div>
                            <div class="col-span-4 border-r border-black flex items-center justify-center">Data</div>
                            <div class="col-span-4 flex items-center justify-center">จุดคัดกรอง</div>
                        </div>
                        <!-- แถว MEWS รวม -->
                        <div class="grid grid-cols-12 border-b border-black flex-1">
                            <div class="col-span-4 border-r border-black flex items-center justify-center font-bold bg-slate-50">MEWS รวม</div>
                            <div class="col-span-4 border-r border-black flex items-center justify-center font-black text-lg">{{ $mews->total_score }}</div>
                            <div class="col-span-4 flex items-center justify-center bg-white"></div>
                        </div>
                        <!-- แถว Pain score -->
                        <div class="grid grid-cols-12 border-b border-black flex-1">
                            <div class="col-span-4 border-r border-black flex items-center justify-center font-bold bg-slate-50">Pain score</div>
                            <div class="col-span-4 border-r border-black flex items-center justify-center font-black text-sm">{{ $mews->pain_score ?? '-' }}</div>
                            <div class="col-span-4 flex items-center justify-center bg-white"></div>
                        </div>
                        <!-- แถว ผู้ประเมิน -->
                        <div class="grid grid-cols-12 flex-1">
                            <div class="col-span-4 border-r border-black flex items-center justify-center font-bold bg-slate-50">ผู้ประเมิน</div>
                            <div class="col-span-4 border-r border-black flex items-center justify-center text-[8.5px] leading-tight text-center px-0.5">
                                {{ $mews->evaluator->rank ?? '' }}{{ $mews->evaluator->name ?? 'ไม่ระบุ' }}
                            </div>
                            <div class="col-span-4 flex items-center justify-center bg-white"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ฝั่งขวา: Normal V/S by age -->
            <div class="w-44 shrink-0">
                <table class="mews-table w-full">
                    <thead>
                        <tr><th colspan="3">Normal V/S by age</th></tr>
                        <tr><th>Age</th><th>HR (bpm)</th><th>RR (/min)</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Premie</td><td>120-170</td><td>40-70</td></tr>
                        <tr><td>0-3 mo</td><td>110-160</td><td>30-60</td></tr>
                        <tr><td>3-6 mo</td><td>100-150</td><td>30-45</td></tr>
                        <tr><td>6-12 mo</td><td>90-130</td><td>25-40</td></tr>
                        <tr><td>1-3 yrs</td><td>80-125</td><td>20-30</td></tr>
                        <tr><td>3-6 yrs</td><td>70-115</td><td>20-25</td></tr>
                        <tr><td>6-12 yrs</td><td>60-100</td><td>14-22</td></tr>
                        <tr><td>>12 yrs</td><td>60-100</td><td>12-18</td></tr>
                    </tbody>
                </table>
                <div class="border border-black border-t-0 p-1 text-[9px] text-center font-bold bg-slate-50">
                    *ผิดปกติ ส่งห้องฉุกเฉิน
                </div>
                <div class="border border-black border-t-0 p-1 text-[8px] text-red-600 leading-tight">
                    <p>-MEWS ≥2 ส่งห้องฉุกเฉิน</p>
                    <p>-MEWS 0-1 ส่งOPD/ARI/ไตเทียม/เวชกรรม/ห้องยา/พยาธิ/ห้องผ่าตัด/แพทย์แผนไทย/กายภาพ/ฝังเข็ม/ทันตกรรม</p>
                    <p>*MEWS 1 จากHR,RR ส่งห้องฉุกเฉินทุกกรณี</p>
                </div>
            </div>
        </div>

        <!-- ส่วนที่ 4: Emergency specific condition -->
        <div class="border border-black mt-2">
            <div class="text-center font-bold text-[11px] border-b border-black py-0.5 bg-slate-50">Emergency specific condition</div>
            <div class="check-box-grid border-none">
                <!-- MI -->
                <div class="check-box-item border-none border-r border-b border-black">
                    <div class="text-center font-bold underline mb-1">MI</div>
                    <p><i class="fa-regular {{ in_array('mi_chest_pain', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> เจ็บแน่นหน้าอกเหมือนโดนกดทับ</p>
                    <p><i class="fa-regular {{ in_array('mi_dyspnea', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> เหนื่อย หายใจไม่อิ่มหรือมีเหงื่อแตก ใจสั่น</p>
                    <p><i class="fa-regular {{ in_array('mi_referred_pain', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> เจ็บร้าวไปไหล่ซ้าย กรามขากรรไกร แขน</p>
                </div>
                <!-- Stroke -->
                <div class="check-box-item border-none border-r border-b border-black">
                    <div class="text-center font-bold underline mb-1">Stroke</div>
                    <p><i class="fa-regular {{ in_array('stroke_dizzy', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> เวียนศีรษะบ้านหมุน เดินเซ สูญเสียการทรงตัว</p>
                    <p><i class="fa-regular {{ in_array('stroke_vision', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> ตามัว มองเห็นภาพซ้อน มองไม่เห็น</p>
                    <p><i class="fa-regular {{ in_array('stroke_facial_weakness', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> หน้าเบี้ยว ปากเบี้ยวครึ่งซีก</p>
                    <p><i class="fa-regular {{ in_array('stroke_arm_leg_weakness', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> แขนขาอ่อนแรงครึ่งซีก</p>
                    <p><i class="fa-regular {{ in_array('stroke_speech', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> พูดไม่ชัด พูดตะกุกตะกัก สื่อสารไม่เข้าใจ</p>
                </div>
                <!-- Heat Injury -->
                <div class="check-box-item border-none border-r border-b border-black">
                    <div class="text-center font-bold underline mb-1">Heat Injury</div>
                    <p><i class="fa-regular {{ in_array('heat_confusion', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> ซึมสับสน พูดไม่รู้เรื่อง เดินเซ ชัก หมดสติ</p>
                    <p><i class="fa-regular {{ in_array('heat_fatigue', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> อ่อนเพลียไม่มีแรง เหงื่อออกมาก</p>
                    <p><i class="fa-regular {{ in_array('heat_pale', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> หน้าซีดหน้ามืด เป็นลม ตัวเย็น</p>
                    <p><i class="fa-regular {{ in_array('heat_cramp', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> ตะคริว หายใจหอบเร็วลึก</p>
                    <p><i class="fa-regular {{ in_array('heat_numbness', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> ชาปลายมือ/เท้า นิ้วมือจีบเกร็ง</p>
                    <p><i class="fa-regular {{ in_array('heat_urine', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> ปัสสาวะสีเข้มเป็นสีน้ำตาลหรือสีโค้ก</p>
                </div>
                <!-- Anaphylaxis -->
                <div class="check-box-item border-none border-b border-black row-span-2">
                    <div class="text-center font-bold underline mb-1">Anaphylaxis</div>
                    <p><i class="fa-regular {{ in_array('ana_rash', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> มีอาการผื่นแดง คัน ลมพิษขึ้นทั่วตัว ปากบวม ลิ้นบวม เพดานอ่อนบวม</p>
                    <p><b>ร่วมกับ</b> <i class="fa-regular {{ in_array('ana_resp', $symptomCodes) ? 'fa-circle-check' : 'fa-circle' }}"></i> มีอาการทางระบบทางเดินหายใจ เช่น หายใจสะดวก คัดจมูก น้ำมูกไหล เสียงแหบ หอบเหนื่อย</p>
                    <p class="ml-8"><i class="fa-regular {{ in_array('ana_sys', $symptomCodes) ? 'fa-circle-check' : 'fa-circle' }}"></i> การทำงานของระบบต่างๆล้มเหลว เช่น กล้ามเนื้ออ่อนแรง เป็นลม อุจจาระราด ปัสสาวะราด</p>
                    <p class="ml-8"><i class="fa-regular {{ in_array('ana_gi', $symptomCodes) ? 'fa-circle-check' : 'fa-circle' }}"></i> อาการทางระบบทางเดินอาหาร เช่น ปวดท้อง คลื่นไส้ อาเจียน</p>
                    <p class="ml-8"><i class="fa-regular {{ in_array('ana_chest', $symptomCodes) ? 'fa-circle-check' : 'fa-circle' }}"></i> แน่นหน้าอก</p>
                </div>
                
                <!-- Appendicitis -->
                <div class="check-box-item border-none border-r border-black">
                    <div class="text-center font-bold underline mb-1">Appendicitis</div>
                    <p><i class="fa-regular {{ in_array('appendicitis', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> ปวดท้อง RLQ + Alvarado score > 4</p>
                </div>
                <!-- Sepsis -->
                <div class="check-box-item border-none border-r border-black">
                    <div class="text-center font-bold underline mb-1">Sepsis</div>
                    <p><i class="fa-regular {{ in_array('sepsis_drowsy', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> ซึม</p>
                    <p><i class="fa-regular {{ in_array('sepsis_tachypnea', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> หายใจหอบ</p>
                </div>
                <!-- Trauma -->
                <div class="check-box-item border-none border-r border-black">
                    <div class="text-center font-bold underline mb-1">Trauma</div>
                    <p><i class="fa-regular {{ in_array('trauma', $symptomCodes) ? 'fa-square-check' : 'fa-square' }}"></i> อุบัติเหตุภายใน 24 ชม.</p>
                </div>
            </div>
        </div>

        <!-- ส่วนที่ 5: Note Table -->
        <div class="mt-2">
            <div class="text-[11px] underline mb-1 font-bold">Note (บันทึกการประเมินและอาการปัจจุบัน)</div>
            <table class="mews-table w-full min-h-[140px]">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="w-20">ว/ด/ป/เวลา</th>
                        <th class="w-40">(V/S : BT, BP, PR, RR, O2sat, AVPU)</th>
                        <th class="w-12">MEWS</th>
                        <th class="w-36">การรักษา / สถานะ</th>
                        <th>อาการปัจจุบัน / บันทึกเพิ่มเติม</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($caseAssessments->take(5) as $item)
                        @php
                            $rowO2 = $item->o2sat ?? $item->o2_sat ?? '-';
                            $rowAvpu = $item->consciousness ?? $item->avpu ?? '-';
                            $rowStatus = $item->treatment_note ?? ($item->status === 'completed' ? ($item->discharge_type === 'admitted' ? 'Admit: ' . $item->discharge_details : ($item->discharge_type === 'refer' ? 'Refer: ' . $item->discharge_details : 'จำหน่ายกลับกองร้อย')) : '');
                        @endphp
                        <tr class="h-8">
                            <td>{{ $item->created_at->addYears(543)->format('d/m/y H:i') }}</td>
                            <td class="text-[10px]">
                                T:{{ $item->temperature ?? '-' }} BP:{{ $item->systolic_bp ?? '-' }} PR:{{ $item->heart_rate ?? '-' }}<br>
                                RR:{{ $item->respiratory_rate ?? '-' }} O2:{{ $rowO2 }} AVPU:{{ $rowAvpu }}
                            </td>
                            <td class="font-bold">{{ $item->total_score }}</td>
                            <td class="text-[10px]">{{ $rowStatus }}</td>
                            <td class="text-left px-2">{{ $item->symptoms_note ?? '' }}</td>
                        </tr>
                    @endforeach

                    <!-- เติมแถวว่างให้ครบ 5 แถวสำหรับเขียนมือต่อ -->
                    @for($i = $caseAssessments->count(); $i < 5; $i++)
                        <tr class="h-8"><td></td><td></td><td></td><td></td><td></td></tr>
                    @endfor
                </tbody>
            </table>
        </div>

    </div>

    <script>
        function downloadAsImage() {
            const btn = document.getElementById('btn-download');
            const paper = document.getElementById('mews-paper');
            const originalText = btn.innerHTML;

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสร้างรูปภาพ...';
            btn.classList.add('opacity-75', 'cursor-not-allowed');

            html2canvas(paper, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(canvas => {
                const imgData = canvas.toDataURL('image/jpeg', 0.9);
                const link = document.createElement('a');
                link.download = `KSVR-MEWS-{{ $mews->patient->cid }}.jpg`; 
                link.href = imgData;
                link.click();

                btn.innerHTML = originalText;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
            }).catch(err => {
                console.error("เกิดข้อผิดพลาดในการสร้างรูปภาพ", err);
                alert("ขออภัยครับ ไม่สามารถบันทึกเป็นรูปภาพได้ในขณะนี้");
                btn.innerHTML = originalText;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
            });
        }
    </script>
</body>
</html>