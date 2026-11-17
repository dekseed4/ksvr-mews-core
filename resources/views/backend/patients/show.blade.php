@extends('layouts.backend')

@section('title', 'ข้อมูลผู้ป่วย - KSVR Hospital')
@section('page_header', 'แฟ้มประวัติผู้ป่วย (Patient Profile)')

@section('content')
<div class="max-w-7xl mx-auto mt-4 pb-12 space-y-6">

    @php
        // 1. แยกสิทธิ์อย่างเด็ดขาด (รองรับสิทธิ์เจ้าหน้าที่เวชกรรม medical_staff)
        $userRoles = is_string(auth()->user()->roles) ? json_decode(auth()->user()->roles, true) : (array)(auth()->user()->roles ?? auth()->user()->role);
        $isAdmin        = in_array('admin', $userRoles);
        $isERNurse      = in_array('er_nurse', $userRoles);
        $isOPDNurse     = in_array('opd_nurse', $userRoles);
        $isMedic        = in_array('medic', $userRoles);
        $isRegistration = in_array('registration', $userRoles);
        $isMedicalStaff = in_array('medical_staff', $userRoles);
        
        $isHospitalStaff    = $isAdmin || $isERNurse || $isOPDNurse || $isMedicalStaff;
        $canSyncHosxp       = $isAdmin || $isRegistration || $isERNurse || $isOPDNurse || $isMedicalStaff;
        $canManageInfirmary = $isHospitalStaff || $isMedic;

        $activeInfStay = $patient->activeInfirmaryStay;
        $allInfStays   = $patient->infirmaryStays ?? collect();

        // 🌟 อนุญาตให้ใช้ระบบห้องพยาบาล/ฝากนอน รพ. ได้ แม้จะอยู่ระหว่างเตรียมเอกสารนำปลด (ยกเว้นปลดเสร็จสิ้นแล้ว หรือ ขาดหนี/ต้องโทษ/เสียชีวิต)
        $isCompletedDischarge = $patient->militaryDischarge && $patient->militaryDischarge->process_status === 'completed';
        $isInactiveDutyStatus = in_array($patient->duty_status ?? 'active', ['absent', 'imprisoned', 'deceased']);
        $canUseInfirmaryForSoldier = !$isCompletedDischarge && !$isInactiveDutyStatus;

        $infReasonMeta = [
            'respiratory'     => ['label' => 'โรคระบบทางเดินหายใจ / โรคติดต่อ', 'icon' => 'fa-head-side-mask text-rose-500', 'badge' => 'bg-rose-50 text-rose-700 border-rose-200'],
            'post_admit'      => ['label' => 'พักฟื้นต่อเนื่องหลังออกจาก รพ.', 'icon' => 'fa-bed-pulse text-amber-500', 'badge' => 'bg-amber-50 text-amber-800 border-amber-200'],
            'musculoskeletal' => ['label' => 'บาดเจ็บกล้ามเนื้อ / กระดูก / แผล', 'icon' => 'fa-crutch text-indigo-500', 'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
            'other'           => ['label' => 'สาเหตุอื่นๆ (สังเกตอาการ)', 'icon' => 'fa-notes-medical text-teal-500', 'badge' => 'bg-teal-50 text-teal-700 border-teal-200'],
        ];
    @endphp

    <!-- 🟢 Header และปุ่มย้อนกลับ -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <a href="{{ route('backend.patients.index') }}" class="text-slate-500 hover:text-emerald-600 transition-colors flex items-center gap-2 font-medium bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
            <i class="fa-solid fa-arrow-left"></i> กลับหน้ารายชื่อ
        </a>
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
    
            <!-- ปุ่มแก้ไขประวัติ -->
            <a href="{{ route('backend.patients.edit', $patient->id) }}" class="h-11 px-5 bg-white border-2 border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-600 hover:text-amber-700 rounded-xl font-bold transition-all duration-200 shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-pen-to-square"></i> แก้ไขประวัติ
            </a>

            <!-- 2. ตรวจสอบสถานะปลดทหาร หรือ ไม่มารายงานตัว/ต้องโทษ/เสียชีวิต ก่อนแสดงปุ่ม MEWS -->
            @if($patient->isCannotAssessMews())
                <div class="h-11 px-5 bg-slate-800 text-white rounded-xl font-bold shadow-sm flex items-center justify-center gap-2 text-xs sm:text-sm cursor-not-allowed opacity-90">
                    <i class="fa-solid fa-ban text-rose-400"></i> 
                    <span>{{ $patient->duty_status_label }} (งดแจ้ง MEWS)</span>
                </div>
            @elseif($isHospitalStaff || $isMedic)
                @php
                    $latestMews = $patient->mewsAssessments->sortByDesc('created_at')->first();
                    $hasActiveCase = $latestMews && $latestMews->status !== 'completed' && $latestMews->status !== 'cancelled';
                    $assessType = $isHospitalStaff ? 'er' : 'field';
                @endphp

                @if($hasActiveCase)
                    @if($isHospitalStaff)
                        <a href="{{ route('backend.mews.create', ['patient_id' => $patient->id, 'type' => $assessType]) }}" class="h-11 px-6 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold transition-all duration-200 shadow-sm shadow-orange-500/30 flex items-center justify-center gap-2 hover:-translate-y-0.5 ring-2 ring-transparent focus:ring-orange-500">
                            <i class="fa-solid fa-stethoscope"></i> ประเมินซ้ำ
                        </a>
                    @endif
                @else
                    <a href="{{ route('backend.mews.create', ['patient_id' => $patient->id, 'type' => $assessType]) }}" class="h-11 px-6 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold transition-all duration-200 shadow-sm shadow-emerald-500/30 flex items-center justify-center gap-2 hover:-translate-y-0.5 ring-2 ring-transparent focus:ring-emerald-500">
                        <i class="fa-solid fa-file-medical"></i> เริ่มประเมิน MEWS ใหม่
                    </a>
                @endif
            @endif
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 🚨 โซนแบนเนอร์สถานะสำคัญของผู้ป่วย (Comprehensive Smart Status Banners) -->
    <!-- ========================================================================= -->

    <!-- 1. แบนเนอร์แจ้งเตือนเมื่อพลทหารไม่ได้ประจำการปกติ (ไม่มารายงานตัว / ต้องโทษ / เสียชีวิต) -->
    @php
        $curDutyStatus = $patient->duty_status ?? 'active';
    @endphp
    @if(in_array($curDutyStatus, ['absent', 'imprisoned', 'deceased']))
        @php
            $dutyBannerTheme = match($curDutyStatus) {
                'absent'     => ['bg' => 'from-orange-50 to-amber-50 border-orange-300 text-orange-950', 'iconBg' => 'bg-orange-500 text-white', 'icon' => 'fa-user-xmark', 'badge' => 'bg-orange-600 text-white', 'title' => 'สถานะกำลังพล: ไม่มารายงานตัว / ขาดหนีราชการ'],
                'imprisoned' => ['bg' => 'from-rose-50 to-red-50 border-rose-300 text-rose-950', 'iconBg' => 'bg-rose-600 text-white', 'icon' => 'fa-gavel', 'badge' => 'bg-rose-600 text-white', 'title' => 'สถานะกำลังพล: ต้องโทษ / อยู่ระหว่างดำเนินคดี'],
                'deceased'   => ['bg' => 'from-slate-800 to-slate-900 border-slate-700 text-white', 'iconBg' => 'bg-slate-700 text-slate-200', 'icon' => 'fa-ribbon', 'badge' => 'bg-rose-500 text-white', 'title' => 'สถานะกำลังพล: เสียชีวิต'],
            };
        @endphp
        <div class="bg-gradient-to-r {{ $dutyBannerTheme['bg'] }} border-2 rounded-2xl p-5 shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 animate-fade-in-down">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 {{ $dutyBannerTheme['iconBg'] }} rounded-2xl flex items-center justify-center text-xl shrink-0 shadow-sm">
                    <i class="fa-solid {{ $dutyBannerTheme['icon'] }}"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base md:text-lg font-black">{{ $dutyBannerTheme['title'] }}</h3>
                        <span class="{{ $dutyBannerTheme['badge'] }} text-[11px] font-bold px-2.5 py-0.5 rounded-full">งดแจ้งและประเมิน MEWS</span>
                    </div>
                    <p class="text-xs sm:text-sm opacity-90 mt-1">
                        <b>หมายเหตุ / รายละเอียด:</b> <span class="font-bold">{{ $patient->duty_status_note ?: 'ไม่ได้ระบุหมายเหตุเพิ่มเติม' }}</span>
                    </p>
                </div>
            </div>
            <a href="{{ route('backend.patients.edit', $patient->id) }}" 
               class="px-4 py-2 bg-white text-slate-800 hover:bg-slate-100 border border-slate-300 rounded-xl text-xs font-bold transition-colors shrink-0 shadow-sm">
                <i class="fa-solid fa-user-gear mr-1 text-indigo-600"></i> แก้ไขสถานะกำลังพล
            </a>
        </div>
    @endif

    <!-- 2. แบนเนอร์แจ้งเตือนเมื่อพลทหารอยู่ในทะเบียนนำปลด / ปลดประจำการแล้ว -->
    @if($patient->militaryDischarge)
        <div class="bg-slate-900 text-white border-2 border-slate-700 rounded-2xl p-5 shadow-md flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-rose-500/20 border border-rose-400/30 text-rose-400 rounded-2xl flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base md:text-lg font-black text-white">
                            สถานะ: {{ $patient->militaryDischarge->process_status === 'completed' ? 'ปลดประจำการแล้ว (ส่งเอกสาร ศปน. ครบถ้วน)' : 'ขึ้นทะเบียนเอกสารนำปลดทหาร' }}
                        </h3>
                        <span class="bg-rose-500 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full">งดแจ้งและประเมิน MEWS</span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1">
                        <b>โรควินิจฉัยที่ใช้นำปลด:</b> <span class="text-amber-300 font-bold">{{ $patient->militaryDischarge->diagnosis_text }}</span>
                    </p>
                </div>
            </div>
            <a href="{{ route('backend.military_discharges.index', ['intake_batch' => $patient->intake_batch]) }}" 
               class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/20 rounded-xl text-xs font-bold transition-colors shrink-0">
                <i class="fa-solid fa-folder-open mr-1"></i> ดูตารางเอกสารนำปลด
            </a>
        </div>
    @endif

    <!-- 3. แบนเนอร์แจ้งเตือนด่วน: เมื่อพลทหารกำลัง Admit นอน รพ.ค่ายฯ หรือ ส่งต่อไปนอนนอก รพ. (รพ.สาธารณสุข) -->
    <!-- (แสดงผลเสมอแม้จะมีชื่ออยู่ในทะเบียนนำปลด เพื่อให้พยาบาลกดบันทึกเมื่อกลับถึงหน่วยฝึกได้) -->
    @php
        $ipdNow = $patient->activeIpdMorbidity 
            ?? \App\Models\RecruitMorbidity::where('patient_id', $patient->id)
                ->where('category', 'other_ipd')
                ->where('status', 'treating')
                ->orderByDesc('onset_date')
                ->orderByDesc('id')
                ->first();
    @endphp
    @if($ipdNow && !in_array($curDutyStatus, ['absent', 'imprisoned', 'deceased']))
        @php
            $isPublicHospNow = ($ipdNow->sub_type === 'public_hospital');
            $ipdDaysNow = max(1, (int) $ipdNow->onset_date->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1);
        @endphp
        <div class="bg-gradient-to-r {{ $isPublicHospNow ? 'from-blue-50 via-indigo-50 to-sky-50 border-blue-200' : 'from-amber-50 via-orange-50 to-yellow-50 border-amber-200' }} border-2 rounded-2xl p-5 shadow-sm flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 animate-fade-in-down">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-12 h-12 rounded-2xl {{ $isPublicHospNow ? 'bg-blue-600 text-white' : 'bg-amber-500 text-white' }} flex items-center justify-center text-xl shrink-0 shadow-md">
                    <i class="fa-solid {{ $isPublicHospNow ? 'fa-truck-medical' : 'fa-bed-pulse' }}"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black {{ $isPublicHospNow ? 'bg-blue-600 text-white' : 'bg-amber-600 text-white' }} flex items-center gap-1.5 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                            {{ $isPublicHospNow ? 'กำลังนอนรักษาตัวนอก รพ. (ส่งต่อ รพ.สาธารณสุข)' : 'กำลัง Admit นอนรักษาตัวที่ รพ.ค่ายกฤษณ์สีวะรา' }}
                        </span>
                        <span class="text-xs font-bold text-slate-700 bg-white px-2.5 py-0.5 rounded-lg border border-slate-200">
                            รวม {{ number_format($ipdDaysNow) }} วัน (ยังไม่กลับหน่วยฝึก)
                        </span>
                    </div>
                    <h4 class="text-sm sm:text-base font-black text-slate-900 mt-1.5">
                        การวินิจฉัยโรค (Dx.): <span class="text-indigo-700">{{ $ipdNow->diagnosis_text ?: 'รอระบุ Dx.' }}</span>
                    </h4>
                    <p class="text-xs text-slate-600 mt-0.5">
                        วันที่เริ่ม Admit: <b>{{ $ipdNow->onset_date->locale('th')->addYears(543)->translatedFormat('j M Y') }}</b>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto justify-end shrink-0">
                @if($isHospitalStaff || $isMedic)
                    <form action="{{ route('backend.morbidity_reports.recover', $ipdNow->id) }}" method="POST" onsubmit="return confirm('ยืนยันว่าพลทหารหายป่วยและกลับมาถึงหน่วยฝึกแล้วใช่หรือไม่?')">
                        @csrf @method('PATCH')
                        <input type="hidden" name="recovered_date" value="{{ date('Y-m-d') }}">
                        <button type="submit" class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-house-chimney-check"></i> บันทึกกลับหน่วยฝึกแล้ว
                        </button>
                    </form>
                @endif
                <a href="{{ route('backend.morbidity_reports.index', ['intake_batch' => $patient->intake_batch]) }}"
                   class="w-full sm:w-auto px-3.5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-file-waveform text-indigo-600"></i> ดูรายงานประจำวัน
                </a>
            </div>
        </div>
    @endif

    <!-- 4. แบนเนอร์แจ้งเตือนด่วน: เมื่อพลทหารกำลังแยกนอนที่ห้องพยาบาลหน่วยฝึก หรือ ป่วยโรคทางเดินหายใจ/ความร้อน -->
    @php
        $activeOtherMorb = ($patient->morbidityRecords ?? collect())
            ->whereIn('category', ['respiratory', 'heat'])
            ->where('status', 'treating')
            ->first();
    @endphp
    @if($activeInfStay && $canUseInfirmaryForSoldier)
        @php
            $isUnitInfBanner = ($activeInfStay->location_type === 'unit_infirmary');
            $stayDaysBanner  = max(1, (int) $activeInfStay->admitted_at->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1);
        @endphp
        <div class="bg-gradient-to-r {{ $isUnitInfBanner ? 'from-indigo-50 via-teal-50 to-emerald-50 border-indigo-200' : 'from-amber-50 via-orange-50 to-yellow-50 border-amber-200' }} border-2 rounded-2xl p-5 shadow-sm flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 animate-fade-in-down">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-12 h-12 rounded-2xl {{ $isUnitInfBanner ? 'bg-indigo-600 text-white' : 'bg-amber-500 text-white' }} flex items-center justify-center text-xl shrink-0 shadow-md">
                    <i class="fa-solid {{ $isUnitInfBanner ? 'fa-tent-arrows-down' : 'fa-bed-pulse' }}"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black {{ $isUnitInfBanner ? 'bg-indigo-600 text-white' : 'bg-amber-600 text-white' }} flex items-center gap-1.5 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                            {{ $isUnitInfBanner ? 'กำลังนอนห้องพยาบาลหน่วยฝึก (แยกโรงนอน)' : 'กำลังฝากนอนแยกกักที่ รพ.ค่ายกฤษณ์สีวะรา' }}
                        </span>
                        @if($activeInfStay->bed_or_zone)
                            <span class="text-xs font-bold text-slate-700 bg-white px-2.5 py-0.5 rounded-lg border border-slate-200">
                                โซน/เตียง: {{ $activeInfStay->bed_or_zone }}
                            </span>
                        @endif
                        <span class="text-xs font-bold text-teal-700 bg-teal-100/80 px-2.5 py-0.5 rounded-lg">
                            แยกนอนมาแล้ว {{ number_format($stayDaysBanner) }} วัน
                        </span>
                    </div>
                    <h4 class="text-sm sm:text-base font-black text-slate-900 mt-1.5">
                        อาการ/สาเหตุ: <span class="text-indigo-700">{{ $activeInfStay->diagnosis_text }}</span>
                    </h4>
                    <p class="text-xs text-slate-600 mt-0.5">
                        เริ่มแยกนอนเมื่อ: <b>{{ $activeInfStay->admitted_at->locale('th')->addYears(543)->translatedFormat('j M Y เวลา H:i น.') }}</b>
                        @if($activeInfStay->care_instruction)
                            | <b>การดูแล:</b> <span class="text-teal-800 font-semibold">{{ $activeInfStay->care_instruction }}</span>
                        @endif
                    </p>
                </div>
            </div>

            @if($canManageInfirmary)
                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto justify-end shrink-0">
                    <a href="{{ route('backend.care_chart.show', $patient->id) }}"
                       class="w-full sm:w-auto px-3.5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-pills"></i> บันทึก V/S & ยา (e-MAR)
                    </a>
                    <button type="button"
                            onclick="openProfileDischargeInfirmaryModal('{{ route('backend.recruit_infirmary.discharge', $activeInfStay->id) }}', '{{ addslashes($patient->rank . $patient->first_name . ' ' . $patient->last_name) }}')"
                            class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-house-chimney-check"></i> จำหน่ายกลับโรงนอนปกติ
                    </button>
                </div>
            @endif
        </div>
    @elseif($activeOtherMorb && !$patient->isCannotAssessMews())
        <div class="bg-gradient-to-r from-rose-50 via-orange-50 to-amber-50 border-2 border-rose-200 rounded-2xl p-5 shadow-sm flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 animate-fade-in-down">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-xl shrink-0 shadow-md">
                    <i class="fa-solid {{ $activeOtherMorb->category === 'respiratory' ? 'fa-head-side-mask' : 'fa-temperature-arrow-up' }}"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-rose-600 text-white flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                            กำลังรักษาในรายงานประจำวัน ({{ $activeOtherMorb->category === 'respiratory' ? 'โรคระบบทางเดินหายใจ' : 'การบาดเจ็บจากความร้อน' }})
                        </span>
                    </div>
                    <h4 class="text-sm sm:text-base font-black text-slate-900 mt-1">
                        การวินิจฉัยโรค (Dx.): <span class="text-rose-700">{{ $activeOtherMorb->diagnosis_text ?: $activeOtherMorb->sub_type }}</span>
                    </h4>
                    <p class="text-xs text-slate-600 mt-0.5">
                        เริ่มป่วยเมื่อ: <b>{{ $activeOtherMorb->onset_date->locale('th')->addYears(543)->translatedFormat('j M Y') }}</b>
                    </p>
                </div>
            </div>
            @if($canManageInfirmary)
                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto justify-end shrink-0">
                    <a href="{{ route('backend.care_chart.show', $patient->id) }}"
                       class="w-full sm:w-auto px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-pills"></i> ใบบันทึก V/S & รับประทานยา (e-MAR)
                    </a>
                </div>
            @endif
        </div>
    @endif
    
    <!-- 5. แบนเนอร์แจ้งเตือนเมื่อมี Active MEWS Case ค้างอยู่ในระบบ (แยกสีและข้อความระหว่าง คิว OPD vs ห้องฉุกเฉิน ER ชัดเจน 100%) -->
    @if($patient->active_workflow_status)
        @php
            $latestPendingMews = $patient->mewsAssessments->where('status', 'pending')->sortByDesc('id')->first();
            $rawPendingSyms = $latestPendingMews ? (is_string($latestPendingMews->symptoms) ? json_decode($latestPendingMews->symptoms, true) : ($latestPendingMews->symptoms ?? [])) : [];
            $isPendingErRoute = !$latestPendingMews 
                || $latestPendingMews->assessment_type === 'er' 
                || in_array($latestPendingMews->risk_level, ['observe', 'emergency']) 
                || (!empty($rawPendingSyms));

            $wfTitle = $isPendingErRoute 
                ? $patient->active_workflow_status['text'] 
                : 'อยู่ในคิวรอตรวจแผนกผู้ป่วยนอก (OPD Queue)';
            $wfSubtitle = $isPendingErRoute
                ? 'ผู้ป่วยรายนี้กำลังอยู่ในกระบวนการดูแลของห้องฉุกเฉิน (ER)'
                : 'ระดับความเสี่ยงปกติ (สีเขียว) ส่งตัวเข้าคิวรอพบแพทย์แผนกผู้ป่วยนอก (OPD)';
        @endphp
        <div class="mb-6 bg-gradient-to-r {{ $isPendingErRoute ? 'from-amber-50 to-orange-50 border-amber-200' : 'from-teal-50 to-emerald-50 border-teal-200' }} border-2 rounded-2xl p-5 md:p-6 shadow-sm flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 animate-fade-in-down relative overflow-hidden">
            
            <div class="absolute -right-6 -top-6 {{ $isPendingErRoute ? 'text-amber-500/10' : 'text-teal-500/10' }} text-9xl pointer-events-none">
                <i class="fa-solid {{ $isPendingErRoute ? 'fa-truck-medical' : 'fa-stethoscope' }}"></i>
            </div>

            <div class="flex items-center gap-4 relative z-10">
                <div class="w-14 h-14 {{ $isPendingErRoute ? 'bg-amber-100 text-amber-600' : 'bg-teal-100 text-teal-600' }} rounded-full flex items-center justify-center text-2xl shrink-0 shadow-inner relative">
                    <i class="fa-solid {{ $isPendingErRoute ? $patient->active_workflow_status['icon'] : 'fa-user-doctor' }}"></i>
                    <span class="absolute top-0 right-0 flex h-3.5 w-3.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $isPendingErRoute ? 'bg-amber-400' : 'bg-teal-400' }} opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3.5 w-3.5 {{ $isPendingErRoute ? 'bg-amber-500' : 'bg-teal-500' }} border-2 border-white"></span>
                    </span>
                </div>
                
                <div>
                    <h3 class="text-lg font-black {{ $isPendingErRoute ? 'text-amber-800' : 'text-teal-900' }} tracking-tight">
                        สถานะ: {{ $wfTitle }}
                    </h3>
                    <p class="text-sm font-medium {{ $isPendingErRoute ? 'text-amber-700/80' : 'text-teal-700' }} mt-0.5">
                        {{ $wfSubtitle }} (อัปเดตข้อมูลล่าสุดเมื่อ {{ $patient->active_workflow_status['time_ago'] }})
                    </p>
                </div>
            </div>

            <div class="relative z-10 w-full lg:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 shrink-0">
                <button type="button" onclick="showCopilotModalByMewsId({{ $patient->active_workflow_status['mews_id'] }})" 
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm font-bold rounded-xl transition-all shadow-sm gap-2">
                    <i class="fa-solid fa-user-doctor text-indigo-600"></i> คำแนะนำปฐมพยาบาล (AI Copilot)
                </button>
                <a href="{{ route('backend.mews.show', $patient->active_workflow_status['mews_id']) }}" 
                   class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 {{ $isPendingErRoute ? 'bg-amber-500 hover:bg-amber-600' : 'bg-teal-600 hover:bg-teal-700' }} text-white text-sm font-bold rounded-xl transition-colors shadow-sm gap-2">
                    จัดการเคส / จบการรักษา <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        
        <!-- 📋 ฝั่งซ้าย (กินพื้นที่ 1 ส่วน): ข้อมูลโปรไฟล์และรายละเอียด -->
        <div class="xl:col-span-1 space-y-6">
            
            <!-- การ์ด 1: ข้อมูลสรุปตัวบุคคล -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 mx-auto bg-slate-100 text-slate-400 rounded-full flex items-center justify-center text-4xl mb-4 border-4 border-white shadow-md relative">
                        <i class="fa-solid fa-user-injured"></i>
                        <div class="absolute -bottom-2 -right-2 {{ $patient->blood_group ? 'bg-rose-100 text-rose-600' : 'bg-slate-200 text-slate-500' }} text-[10px] font-black px-2 py-1 rounded-full border-2 border-white shadow-sm" title="กรุ๊ปเลือด">
                            {{ $patient->blood_group ?: '-' }}
                        </div>
                    </div>
                    <h2 id="patient-fullname-display" class="text-xl font-bold text-slate-800">{{ $patient->rank }}{{ $patient->first_name }} {{ $patient->last_name }}</h2>
                    
                    <div class="mt-3 inline-flex flex-col items-center gap-1.5 bg-slate-50 px-5 py-2.5 rounded-xl border border-slate-100 shadow-sm w-full">
                        <p class="text-slate-600 text-[13px] flex items-center gap-2"><i class="fa-solid fa-tent text-indigo-500 w-4"></i> หน่วยฝึก: <span class="font-bold text-slate-800">{{ $patient->militaryUnit->short_name ?? $patient->militaryUnit->name ?? 'ไม่ระบุ' }}</span></p>
                        <div class="w-full border-t border-slate-200/60 my-0.5"></div>
                        <p class="text-slate-600 text-[13px] flex items-center gap-2"><i class="fa-solid fa-sitemap text-slate-400 w-4"></i> สังกัดปลายทาง: <span class="font-bold text-slate-800">{{ $patient->battalion ?? 'รอประจำหน่วย' }}</span></p>
                    </div>
                </div>

                <div class="space-y-4 divide-y divide-slate-100">
                    <div class="pt-3 flex justify-between items-center"><span class="text-slate-500 text-sm">เลข ปชช.</span><span class="font-semibold text-slate-800 font-mono">{{ $patient->cid ?? '-' }}</span></div>
                    <div class="pt-3 flex justify-between items-center"><span class="text-slate-500 text-sm">รหัสทหาร</span><span class="font-semibold text-slate-800">{{ $patient->military_id ?? '-' }}</span></div>
                    
                    <!-- ช่องแสดงรหัส HN พร้อมปุ่มซิงค์ HosXP อัตโนมัติ -->
                    <div class="pt-3 flex justify-between items-center">
                        <span class="text-slate-500 text-sm">รหัส HN</span>
                        <div class="flex items-center gap-2" id="hn-display-container">
                            @if($patient->hn)
                                <span class="font-bold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-md border border-blue-100">
                                    {{ $patient->hn }}
                                </span>
                            @else
                                <span id="hn-badge-unsynced" class="text-amber-600 text-xs font-bold bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                                    ไม่ซิงค์ HosXP
                                </span>
                                @if($canSyncHosxp)
                                    <button type="button" id="btn-sync-hosxp" onclick="syncPatientHosxp(false)" 
                                            class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                        <i class="fa-solid fa-rotate" id="sync-icon"></i> ซิงค์ HosXP
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>

                    <!-- ช่องแสดงสิทธิการรักษา (ดึงจาก HosXP) -->
                    <div class="pt-3 flex justify-between items-center gap-2">
                        <span class="text-slate-500 text-sm shrink-0">สิทธิการรักษา</span>
                        <div class="text-right" id="pttype-display-container">
                            @if($patient->pttype_name)
                                <span class="font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-md border border-emerald-200 text-xs inline-block">
                                    <i class="fa-solid fa-shield-heart mr-1 text-emerald-500"></i>{{ $patient->pttype_name }}
                                </span>
                            @else
                                <span class="text-slate-400 text-xs italic">รอซิงค์สิทธิจาก HosXP</span>
                            @endif
                        </div>
                    </div>

                    <div class="pt-3 flex justify-between items-center"><span class="text-slate-500 text-sm">ผลัด/ปี</span><span class="font-semibold text-slate-800">{{ $patient->intake_batch ?? '-' }}</span></div>
                    <div class="pt-3 flex justify-between items-center">
                        <span class="text-slate-500 text-sm">วันเกิด (อายุ)</span>
                        <span class="font-semibold text-slate-800">
                            @if($patient->dob)
                                {{ \Carbon\Carbon::parse($patient->dob)->addYears(543)->format('d/m/Y') }} <span class="text-xs text-slate-500 ml-1">({{ \Carbon\Carbon::parse($patient->dob)->age }} ปี)</span>
                            @else - @endif
                        </span>
                    </div>
                </div>
            </div>

            <!-- การ์ด 2: ข้อมูลเจาะลึก -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i class="fa-solid fa-address-book text-indigo-500"></i> ข้อมูลเพิ่มเติม
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase mb-1">
                            {{ $isAdmin ? 'ร่างกาย, ตำหนิ และโรคประจำตัว' : 'ร่างกายและตำหนิ' }}
                        </p>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">สูง/หนัก/BMI:</span> 
                                <span class="font-medium text-slate-800">{{ $patient->height ? $patient->height.'cm' : '-' }} / {{ $patient->weight ? $patient->weight.'kg' : '-' }} / {{ $patient->bmi ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">ตำหนิ:</span> 
                                <span class="font-medium text-slate-800 truncate max-w-[160px]" title="{{ $patient->scar_details ?? '-' }}">{{ $patient->scar_details ?? '-' }}</span>
                            </div>

                            @if($isAdmin)
                                <div class="pt-1.5 border-t border-slate-200/60 flex justify-between items-start gap-2">
                                    <span class="text-slate-500 shrink-0">โรคประจำตัว:</span>
                                    @if(($patient->chronic_disease_status ?? 'none') === 'yes')
                                        <span class="font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200 text-right">
                                            <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-1"></i>{{ $patient->chronic_disease_detail ?: 'มีโรคประจำตัว' }}
                                        </span>
                                    @elseif(($patient->chronic_disease_status ?? 'none') === 'other')
                                        <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200 text-right">
                                            <i class="fa-solid fa-notes-medical text-indigo-500 mr-1"></i>อื่นๆ: {{ $patient->chronic_disease_detail ?: '-' }}
                                        </span>
                                    @else
                                        <span class="font-semibold text-emerald-600">ไม่มีโรคประจำตัว</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase mb-1">ครอบครัวและที่อยู่</p>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs space-y-1.5">
                            <div class="flex justify-between"><span class="text-slate-500">บิดา:</span> <span class="font-medium text-slate-800 truncate max-w-[150px]">{{ $patient->father_name ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500">มารดา:</span> <span class="font-medium text-slate-800 truncate max-w-[150px]">{{ $patient->mother_name ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500">เบอร์โทร:</span> <span class="font-medium text-slate-800">{{ $patient->phone ?? '-' }}</span></div>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-[11px] font-bold text-emerald-500 uppercase mb-1">การรับราชการทหาร</p>
                        <div class="bg-emerald-50/50 p-3 rounded-xl border border-emerald-100 text-xs space-y-1.5">
                            <div class="flex justify-between"><span class="text-slate-500">การเข้ารับราชการ:</span> <span class="font-bold text-slate-800">{{ $patient->volunteer_status ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500">สิทธิลดหย่อน:</span> <span class="font-bold text-slate-800">{{ $patient->privilege_reduction ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500">เวลารับราชการ:</span> <span class="font-bold text-slate-800">{{ $patient->service_duration ?? '-' }}</span></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- 📋 ฝั่งขวา (กินพื้นที่ 2 ส่วน): ข้อมูลด้านการแพทย์ (Medical Records) -->
        <div class="xl:col-span-2 space-y-6">
            
            <!-- การ์ด 3: ผลคัดกรองทหารใหม่ -->
            @if($patient->recruitScreening)
                @php
                    $scColor = $patient->recruitScreening->color_group;
                    $bgClass = $scColor == 'red' ? 'bg-rose-50 border-rose-200' : ($scColor == 'yellow' ? 'bg-amber-50 border-amber-200' : 'bg-emerald-50 border-emerald-200');
                    $txtClass = $scColor == 'red' ? 'text-rose-600' : ($scColor == 'yellow' ? 'text-amber-600' : 'text-emerald-600');
                    $icon = $scColor == 'red' ? 'fa-virus-covid' : ($scColor == 'yellow' ? 'fa-head-side-cough' : 'fa-shield-virus');
                    $label = $scColor == 'red' ? 'กลุ่มพบเชื้อ / เสี่ยงสูงมาก' : ($scColor == 'yellow' ? 'กลุ่มเสี่ยง / ต้องเฝ้าระวัง' : 'กลุ่มปกติ');
                @endphp
                <div class="{{ $bgClass }} border-2 rounded-3xl p-5 shadow-sm relative overflow-hidden flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="absolute -right-4 -top-4 text-8xl opacity-10 {{ $txtClass }} pointer-events-none"><i class="fa-solid {{ $icon }}"></i></div>
                    
                    <div class="flex items-center gap-4 relative z-10 w-full sm:w-auto">
                        <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center {{ $txtClass }} shadow-sm text-2xl shrink-0"><i class="fa-solid {{ $icon }}"></i></div>
                        <div>
                            <p class="text-xs font-bold {{ $txtClass }} opacity-80 uppercase tracking-wider mb-0.5">ผลการคัดกรองแรกรับ</p>
                            <h3 class="text-lg font-black {{ $txtClass }}">{{ $label }}</h3>
                        </div>
                    </div>

                    <div class="relative z-10 bg-white/80 backdrop-blur-sm p-3 rounded-xl border border-white/50 w-full sm:max-w-xs text-sm font-medium text-slate-700 shadow-sm">
                        <i class="fa-solid fa-notes-medical text-indigo-500 mr-1"></i> {{ $patient->recruitScreening->symptoms_note ?? 'ไม่มีบันทึกอาการเพิ่มเติม' }}
                        <div class="text-[10px] text-slate-500 mt-1.5 pt-1.5 border-t border-slate-200/50">ประเมินเมื่อ: {{ $patient->recruitScreening->created_at->addYears(543)->format('d/m/Y H:i น.') }}</div>
                    </div>
                </div>
            @endif

            <!-- ===================================================================== -->
            <!-- 📅 การ์ด 3.5: ตารางการนัดพบแพทย์ของพลทหารรายนี้ -->
            <!-- ===================================================================== -->
            @php
                $patientAppointments = $patient->recruitAppointments ?? collect();
                $scheduledCount = $patientAppointments->where('status', 'scheduled')->count();
                $todayDateStr = \Carbon\Carbon::today()->format('Y-m-d');
                $tomorrowDateStr = \Carbon\Carbon::tomorrow()->format('Y-m-d');
            @endphp
            <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100">
                
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6 border-b border-slate-100 pb-5">
                    <div class="flex items-start sm:items-center gap-3.5 min-w-0">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center text-xl shadow-md shadow-sky-500/20 shrink-0">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h3 class="text-lg font-black text-slate-800 tracking-tight whitespace-nowrap">
                                    ตารางนัดพบแพทย์
                                </h3>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider hidden sm:inline">
                                    (Medical Appointments)
                                </span>

                                @if($scheduledCount > 0)
                                    <span class="inline-flex items-center gap-1.5 bg-sky-50 text-sky-700 text-xs font-black px-3 py-1 rounded-full border border-sky-200 whitespace-nowrap shrink-0 shadow-sm">
                                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-ping"></span>
                                        รอพบแพทย์ {{ $scheduledCount }} นัด
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-500 text-xs font-bold px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0">
                                        ไม่มีคิวนัดค้าง
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500 mt-1 truncate">
                                ข้อมูลนัดหมายตรวจติดตามอาการทั้ง รพ.ค่ายกฤษณ์สีวะรา และ รพ.สาธารณสุข
                            </p>
                        </div>
                    </div>

                    @if(!$patient->isCannotAssessMews())
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
                        @if(!empty($patient->hn))
                            <button type="button" id="btn-sync-pt-oapp" onclick="syncSinglePatientAppointments()" 
                                    class="flex-1 sm:flex-none justify-center px-3.5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/80 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm whitespace-nowrap">
                                <i class="fa-solid fa-rotate" id="icon-sync-pt-oapp"></i> ดึงนัดจาก HosXP
                            </button>
                        @endif
                        <a href="{{ route('backend.recruit_appointments.index', ['patient_id' => $patient->id, 'intake_batch' => $patient->intake_batch]) }}" 
                           class="flex-1 sm:flex-none justify-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm whitespace-nowrap">
                            <i class="fa-solid fa-calendar-plus"></i> จัดการ/ลงนัดเพิ่ม
                        </a>
                    </div>
                    @endif
                </div>

                @if($patientAppointments->count() > 0)
                    <div class="space-y-3.5">
                        @foreach($patientAppointments as $appItem)
                            @php
                                $aDate = $appItem->appointment_date->format('Y-m-d');
                                $isAppToday = ($aDate === $todayDateStr && $appItem->status === 'scheduled');
                                $isAppTomorrow = ($aDate === $tomorrowDateStr && $appItem->status === 'scheduled');
                                $isCampHosp = ($appItem->hospital_type === 'camp_hospital');
                            @endphp
                            <div class="p-4 sm:p-5 rounded-2xl border {{ $isAppToday ? 'bg-rose-50/50 border-rose-200' : ($isAppTomorrow ? 'bg-amber-50/50 border-amber-200' : 'bg-slate-50/70 border-slate-200/80 hover:border-sky-200 hover:bg-white') }} flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-all">
                                <div class="space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-black text-slate-800 text-sm sm:text-base flex items-center gap-1.5">
                                            <i class="fa-regular fa-calendar-check text-sky-500"></i>
                                            {{ $appItem->appointment_date->locale('th')->addYears(543)->translatedFormat('j M Y') }}
                                            <span class="text-xs font-bold text-slate-500">(เวลา {{ $appItem->appointment_time ?: '08:30' }} น.)</span>
                                        </span>

                                        @if($isAppToday)
                                            <span class="px-2.5 py-0.5 bg-rose-600 text-white rounded-full text-[11px] font-black animate-pulse whitespace-nowrap">🚨 นัดวันนี้!</span>
                                        @elseif($isAppTomorrow)
                                            <span class="px-2.5 py-0.5 bg-amber-500 text-white rounded-full text-[11px] font-black whitespace-nowrap">⏰ นัดพรุ่งนี้ (ล่วงหน้า 1 วัน)</span>
                                        @endif

                                        <span class="px-2.5 py-0.5 rounded-lg text-[11px] font-bold border whitespace-nowrap {{ $isCampHosp ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200' }}">
                                            {{ $isCampHosp ? '🏥 ' : '🚑 ' }}{{ $appItem->hospital_name ?: ($isCampHosp ? 'รพ.ค่ายกฤษณ์สีวะรา' : 'รพ.สาธารณสุข') }}
                                        </span>
                                    </div>

                                    <div class="text-xs sm:text-[13px] text-slate-600 font-medium leading-relaxed">
                                        <b>แผนก/คลินิก:</b> <span class="text-indigo-700 font-bold">{{ $appItem->clinic_name ?: 'ไม่ระบุ' }}</span>
                                        @if($appItem->doctor_name) <span class="mx-1 text-slate-300">|</span> <b>แพทย์:</b> {{ $appItem->doctor_name }} @endif
                                        <span class="mx-1 text-slate-300">|</span> <b>สาเหตุ:</b> {{ $appItem->app_cause ?: 'นัดตรวจติดตามอาการ' }}
                                    </div>

                                    @if($appItem->note)
                                        <div class="pt-1">
                                            <div class="text-xs text-amber-900 bg-amber-50 px-3 py-1.5 rounded-xl border border-amber-200/90 inline-flex items-center gap-1.5 font-semibold shadow-sm">
                                                <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                                                <span><b>การเตรียมตัว:</b> {{ $appItem->note }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="shrink-0 sm:text-right pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-200/60">
                                    @if($appItem->status === 'completed')
                                        <span class="px-3.5 py-1.5 bg-emerald-100 text-emerald-800 rounded-xl text-xs font-bold border border-emerald-200 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fa-solid fa-circle-check text-emerald-600"></i> ไปพบแพทย์แล้ว
                                        </span>
                                    @elseif($appItem->status === 'scheduled')
                                        <form action="{{ route('backend.recruit_appointments.update_status', $appItem->id) }}" method="POST" class="inline-block w-full sm:w-auto">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5 whitespace-nowrap">
                                                <i class="fa-solid fa-check"></i> ยืนยันไปตามนัดแล้ว
                                            </button>
                                        </form>
                                    @else
                                        <span class="px-3 py-1 bg-slate-200 text-slate-600 rounded-xl text-xs font-bold inline-block whitespace-nowrap">{{ $appItem->status }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 bg-slate-50/60 rounded-2xl border border-dashed border-slate-200">
                        <i class="fa-regular fa-calendar-xmark text-2xl text-slate-300 mb-2 block"></i>
                        <p class="text-xs text-slate-500 font-medium">ยังไม่มีรายการนัดพบแพทย์สำหรับพลทหารรายนี้</p>
                    </div>
                @endif
            </div>

            <!-- ===================================================================== -->
            <!-- 🛌 การ์ด 3.8: ระบบจัดการนอนห้องพยาบาลหน่วยฝึก & แยกโรงนอน (ดีไซน์ใหม่ ไม่ซ้อนทับ 100%) -->
            <!-- ===================================================================== -->
            @php
                $hasChronicOrPsych   = in_array($patient->chronic_disease_status ?? 'none', ['yes', 'other']);
                $hasRiskScreening    = in_array(optional($patient->recruitScreening)->color_group, ['yellow', 'red']);
                $hasMorbidityHistory = ($patient->morbidityRecords && $patient->morbidityRecords->count() > 0) || !empty($ipdNow);
                $hasMedOrVitalLogs   = ($patient->medications && $patient->medications->count() > 0) || ($patient->covidVitalLogs && $patient->covidVitalLogs->count() > 0);

                $shouldShowCareChartBtn = $activeInfStay 
                    || $allInfStays->count() > 0 
                    || $hasChronicOrPsych 
                    || $hasRiskScreening 
                    || $hasMorbidityHistory 
                    || $hasMedOrVitalLogs
                    || ($patient->militaryDischarge !== null)
            @endphp
            <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100">
                <!-- ส่วนหัวการ์ด 3.8: แยกแถวชื่อหัวข้อ+ป้ายสถานะ ออกจากแถบปุ่มกดเพื่อความโปร่งโล่ง ไม่เบียดซ้อนกัน -->
                <div class="flex flex-col gap-4 mb-6 border-b border-slate-100 pb-5">
                    
                    <!-- แถวบน: ไอคอน + ชื่อหัวข้อ + ป้ายสถานะปัจจุบัน -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start sm:items-center gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-teal-500 to-emerald-600 text-white flex items-center justify-center text-xl shadow-md shadow-teal-500/20 shrink-0">
                                <i class="fa-solid fa-bed-pulse"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-slate-800 tracking-tight">
                                    ห้องพยาบาลหน่วยฝึก & แยกโรงนอน
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                                    ประวัติการแยกโรงนอนด้วยโรคระบบทางเดินหายใจ พักฟื้นหลัง Admit หรือสาเหตุทางการแพทย์อื่นๆ
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0">
                            @if($activeInfStay)
                                <span class="inline-flex items-center gap-1.5 bg-amber-100 text-amber-800 text-xs font-black px-3.5 py-1.5 rounded-full border border-amber-300 shadow-sm">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                                    {{ $activeInfStay->location_type === 'unit_infirmary' ? 'กำลังนอนห้องพยาบาลหน่วยฝึก' : 'กำลังฝากนอนที่ รพ.ค่ายฯ' }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-500 text-xs font-bold px-3 py-1 rounded-full border border-slate-200">
                                    อยู่ในโรงนอนปกติ (ประวัติ {{ $allInfStays->count() }} ครั้ง)
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- แถวล่าง: แถบปุ่มจัดการ (เรียงสวยงาม ไม่ทับป้ายสถานะ) -->
                    @if($canManageInfirmary && $canUseInfirmaryForSoldier)
                        <div class="flex flex-wrap items-center justify-start sm:justify-end gap-2.5 pt-1">
                            @if($shouldShowCareChartBtn)
                                <a href="{{ route('backend.care_chart.show', $patient->id) }}"
                                   class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                    <i class="fa-solid fa-pills"></i> ใบบันทึก V/S & รับประทานยา (e-MAR)
                                </a>
                            @endif

                            <button type="button" onclick="openProfileAddInfirmaryModal()"
                                    class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-bed"></i> {{ $activeInfStay ? 'แก้ไขข้อมูลการแยกนอน' : '+ บันทึกแยกโรงนอน' }}
                            </button>

                            <a href="{{ route('backend.recruit_infirmary.index', ['intake_batch' => $patient->intake_batch, 'unit_id' => $patient->military_unit_id]) }}"
                               class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-list-check"></i> ดูทะเบียนรวม
                            </a>
                        </div>
                    @endif
                </div>

                <!-- 🌟 ส่วนแสดงรายการประวัติการแยกนอน (ที่ตกหล่นไปในรอบก่อน) -->
                @if($allInfStays->count() > 0)
                    <div class="space-y-3.5">
                        @foreach($allInfStays as $stayItem)
                            @php
                                $isStayingNow = ($stayItem->status === 'staying');
                                $isUnitInf    = ($stayItem->location_type === 'unit_infirmary');
                                $rMeta        = $infReasonMeta[$stayItem->reason_category] ?? $infReasonMeta['other'];
                                $stayDays     = max(1, (int) $stayItem->admitted_at->copy()->startOfDay()->diffInDays(($stayItem->discharged_at ?? now())->copy()->startOfDay()) + 1);
                            @endphp
                            <div class="p-4 sm:p-5 rounded-2xl border-2 {{ $isStayingNow ? ($isUnitInf ? 'bg-indigo-50/40 border-indigo-200' : 'bg-amber-50/40 border-amber-200') : 'bg-slate-50/70 border-slate-200/80 opacity-85' }} flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-all">
                                <div class="space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($isUnitInf)
                                            <span class="px-2.5 py-1 rounded-xl bg-indigo-100 text-indigo-800 border border-indigo-200 text-xs font-black inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-tent-arrows-down"></i> ห้องพยาบาลหน่วยฝึก (แยกโรงนอน)
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-xl bg-amber-100 text-amber-900 border border-amber-300 text-xs font-black inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-hospital-user"></i> ฝากนอนแยกกักที่ รพ.ค่ายฯ
                                            </span>
                                        @endif

                                        <span class="px-2.5 py-0.5 rounded-lg border text-[11px] font-bold inline-flex items-center gap-1 {{ $rMeta['badge'] }}">
                                            <i class="fa-solid {{ $rMeta['icon'] }}"></i> {{ $rMeta['label'] }}
                                        </span>

                                        @if($stayItem->bed_or_zone)
                                            <span class="px-2 py-0.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700">
                                                โซน/เตียง: {{ $stayItem->bed_or_zone }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="text-sm font-black text-slate-800 pt-0.5">
                                        อาการ/การวินิจฉัยโรค (Dx.): <span class="text-indigo-700">{{ $stayItem->diagnosis_text }}</span>
                                    </div>

                                    @if($stayItem->care_instruction)
                                        <div class="text-xs text-teal-900 bg-teal-50/90 border border-teal-200 px-3 py-1.5 rounded-xl inline-flex items-center gap-1.5 font-semibold">
                                            <i class="fa-solid fa-user-nurse text-teal-600"></i>
                                            <span><b>คำสั่งการดูแล:</b> {{ $stayItem->care_instruction }}</span>
                                        </div>
                                    @endif

                                    <div class="text-xs text-slate-500 pt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span>
                                            <i class="fa-regular fa-clock text-teal-600 mr-1"></i>
                                            เริ่มแยกนอน: <b>{{ $stayItem->admitted_at->locale('th')->addYears(543)->translatedFormat('j M Y H:i น.') }}</b>
                                        </span>
                                        @if($stayItem->discharged_at)
                                            <span>
                                                • กลับโรงนอนปกติ: <b class="text-emerald-700">{{ $stayItem->discharged_at->locale('th')->addYears(543)->translatedFormat('j M Y H:i น.') }}</b>
                                            </span>
                                        @endif
                                        <span class="px-2 py-0.2 bg-white border border-slate-200 rounded text-[11px] font-bold text-slate-700">
                                            รวม {{ number_format($stayDays) }} วัน
                                        </span>
                                    </div>

                                    @if(!$isStayingNow && $stayItem->discharge_note)
                                        <div class="text-[11px] text-emerald-700 font-semibold">
                                            <i class="fa-solid fa-circle-check mr-1"></i>หมายเหตุเมื่อกลับโรงนอน: {{ $stayItem->discharge_note }}
                                        </div>
                                    @endif
                                </div>

                                <div class="shrink-0 sm:text-right pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-200/60 flex sm:flex-col items-center sm:items-end justify-between gap-2">
                                    @if($isStayingNow)
                                        <span class="px-3 py-1 rounded-full bg-amber-500 text-white text-[11px] font-black shadow-sm inline-flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span> กำลังแยกนอนพัก
                                        </span>
                                        @if($canManageInfirmary)
                                            <button type="button"
                                                    onclick="openProfileDischargeInfirmaryModal('{{ route('backend.recruit_infirmary.discharge', $stayItem->id) }}', '{{ addslashes($patient->rank . $patient->first_name . ' ' . $patient->last_name) }}')"
                                                    class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5 whitespace-nowrap">
                                                <i class="fa-solid fa-house-chimney-check"></i> กลับโรงนอนปกติ
                                            </button>
                                        @endif
                                    @else
                                        <span class="px-3 py-1 rounded-xl bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fa-solid fa-check"></i> กลับโรงนอนปกติแล้ว
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 bg-slate-50/60 rounded-2xl border border-dashed border-slate-200">
                        <i class="fa-solid fa-bed text-2xl text-slate-300 mb-2 block"></i>
                        <p class="text-xs text-slate-500 font-medium">พลทหารรายนี้ยังไม่มีประวัติการแยกนอนที่ห้องพยาบาลหน่วยฝึกหรือฝากนอน รพ.</p>
                    </div>
                @endif
            </div> <!-- 🌟 ปิดกรอบการ์ด 3.8 อย่างสมบูรณ์ ไม่ให้การ์ดที่ 4 ไหลเข้ามาซ้อน -->

            <!-- การ์ด 4: ประวัติรักษา MEWS -->
            <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2 border-b border-slate-100 pb-4">
                    <i class="fa-solid fa-clock-rotate-left text-emerald-500"></i> สรุปรอบการรักษาล่าสุด (MEWS Timeline)
                </h3>

                @if($patient->mewsAssessments && $patient->mewsAssessments->count() > 0)
                    @php
                        $groupedHistory = $patient->mewsAssessments->sortByDesc('id')->groupBy('case_id'); 
                    @endphp             

                    <div id="realtime-timeline-container" class="space-y-4">
                        @foreach($groupedHistory as $caseId => $mewsGroup)
                            <button type="button" onclick="openHistoryModal('{{ $caseId }}')" class="w-full text-left block border border-slate-200 rounded-2xl p-4 hover:bg-emerald-50 hover:border-emerald-300 hover:shadow-md transition-all duration-300 group focus:outline-none">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-lg shrink-0 bg-slate-100 text-slate-500 group-hover:bg-emerald-500 group-hover:text-white transition-colors shadow-inner">
                                            <i class="fa-solid fa-folder-open"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 flex items-center gap-2 group-hover:text-emerald-700 transition-colors text-base">
                                                แฟ้มรักษาเลขที่: {{ $caseId }}
                                            </div>
                                            <div class="text-xs text-slate-500 mt-1 font-medium">
                                                บันทึกประวัติไว้ {{ $mewsGroup->count() }} ครั้ง • สถานะ: 
                                                @php 
                                                    $firstRoundMews = $mewsGroup->first();
                                                    $latestStatus = $firstRoundMews->status; 
                                                    $rawRoundSyms = is_string($firstRoundMews->symptoms) ? json_decode($firstRoundMews->symptoms, true) : ($firstRoundMews->symptoms ?? []);
                                                    $isRoundEr = ($firstRoundMews->assessment_type === 'er') || in_array($firstRoundMews->risk_level, ['observe', 'emergency']) || (!empty($rawRoundSyms));
                                                @endphp
                                                @if($latestStatus === 'completed')
                                                    <span class="text-emerald-600 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">ปิดเคสแล้ว</span>
                                                @elseif($latestStatus === 'cancelled')
                                                    <span class="text-slate-500 font-bold bg-slate-100 px-2 py-0.5 rounded border border-slate-200">ยกเลิกเคส</span>
                                                @elseif($isRoundEr)
                                                    <span class="text-rose-600 font-bold bg-rose-50 px-2 py-0.5 rounded border border-rose-200">กำลังรักษา (ER)</span>
                                                @else
                                                    <span class="text-teal-700 font-bold bg-teal-50 px-2 py-0.5 rounded border border-teal-200">รอตรวจ (OPD)</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="w-8 h-8 rounded-full bg-slate-50 text-slate-300 group-hover:bg-emerald-100 group-hover:text-emerald-600 flex items-center justify-center transition-colors">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </div>
                                </div>
                            </button>

                            <!-- โครงสร้าง Modal -->
                            <div id="modal-{{ $caseId }}" class="hidden fixed inset-0 z-[99999] bg-slate-900/60 backdrop-blur-sm overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                                    <div class="relative w-full max-w-4xl transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8">   
                                        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center sticky top-0 z-20">
                                            <div>
                                                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                                    <i class="fa-solid fa-folder-open text-indigo-500"></i> ประวัติการรักษา (Timeline)
                                                </h3>
                                                <p class="text-xs text-slate-500 font-mono mt-0.5">รหัสอ้างอิง: {{ $caseId }}</p>
                                            </div>
                                            <button type="button" onclick="closeHistoryModal('{{ $caseId }}')" class="w-10 h-10 rounded-full bg-slate-200 text-slate-500 hover:bg-rose-100 hover:text-rose-600 flex items-center justify-center transition-colors focus:outline-none shadow-sm">
                                                <i class="fa-solid fa-xmark text-lg"></i>
                                            </button>
                                        </div>

                                        <div class="px-6 py-6 bg-slate-50/50">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                @foreach($mewsGroup->sortBy('id') as $history)
                                                    <div class="p-5 rounded-2xl border border-slate-200 bg-white shadow-sm hover:shadow-md transition-shadow relative">
                                                        <div class="flex justify-between items-start mb-4">
                                                            <div>
                                                                <span class="text-[10px] font-bold bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full mb-2 inline-block border border-indigo-100">
                                                                    ประเมินครั้งที่ {{ $loop->iteration }}
                                                                </span>
                                                                <div class="font-bold text-slate-800 text-base">
                                                                    <i class="fa-solid {{ $history->assessment_type === 'field' ? 'fa-truck-medical text-slate-400' : 'fa-hospital-user text-slate-400' }}"></i> 
                                                                    {{ $history->assessment_type === 'field' ? 'ภาคสนาม' : 'ห้องฉุกเฉิน' }}
                                                                </div>
                                                                <div class="text-xs text-slate-500 mt-1"><i class="fa-regular fa-clock"></i> {{ \Carbon\Carbon::parse($history->created_at)->locale('th')->addYears(543)->translatedFormat('j M Y H:i น.') }}</div>
                                                            </div>
                                                            <div class="text-right bg-slate-50 px-3 py-2 rounded-xl border border-slate-100">
                                                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">MEWS</div>
                                                                <div class="text-2xl font-black {{ $history->risk_level === 'emergency' ? 'text-rose-600' : ($history->risk_level === 'observe' ? 'text-amber-500' : 'text-emerald-500') }}">
                                                                    {{ $history->total_score }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="grid grid-cols-4 gap-2 text-center">
                                                            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100"><div class="text-[10px] text-slate-400 font-bold mb-1">SBP</div><div class="font-black text-slate-700">{{ $history->systolic_bp }}</div></div>
                                                            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100"><div class="text-[10px] text-slate-400 font-bold mb-1">HR</div><div class="font-black text-slate-700">{{ $history->heart_rate }}</div></div>
                                                            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100"><div class="text-[10px] text-slate-400 font-bold mb-1">RR</div><div class="font-black text-slate-700">{{ $history->respiratory_rate }}</div></div>
                                                            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100"><div class="text-[10px] text-slate-400 font-bold mb-1">T</div><div class="font-black text-slate-700">{{ $history->temperature }}</div></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="mt-6 pt-5 border-t border-slate-200/80 flex flex-wrap justify-center gap-3">
                                                <button type="button" onclick="closeHistoryModal('{{ $caseId }}'); showCopilotModalByMewsId({{ $mewsGroup->first()->id }});" 
                                                        class="inline-flex items-center justify-center w-full sm:w-auto px-6 py-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-sm font-bold transition-all shadow-sm gap-2">
                                                    <i class="fa-solid fa-user-doctor"></i> คำแนะนำปฐมพยาบาล (AI Copilot)
                                                </button>
                                                <a href="{{ route('backend.mews.show', $mewsGroup->first()->id) }}" class="inline-flex items-center justify-center w-full sm:w-auto px-8 py-3 bg-slate-800 hover:bg-indigo-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5">
                                                    ดูรายละเอียดแบบเต็ม / จัดการเคส <i class="fa-solid fa-arrow-right ml-2"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-4xl mx-auto mb-4">
                            <i class="fa-regular fa-folder-open"></i>
                        </div>
                        <h4 class="text-lg font-bold text-slate-700 mb-1">ยังไม่มีประวัติการประเมิน</h4>
                        <p class="text-slate-500 text-sm">ผู้ป่วยรายนี้ยังไม่เคยได้รับการประเมิน MEWS ในระบบ</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    function openHistoryModal(caseId) {
        const modal = document.getElementById('modal-' + caseId);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    }

    function closeHistoryModal(caseId) {
        const modal = document.getElementById('modal-' + caseId);
        if (modal) {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    // 🛌 ฟังก์ชันเปิดป๊อปอัปบันทึก/แก้ไขการแยกโรงนอนจากหน้าแฟ้มประวัติผู้ป่วยโดยตรง (ล็อกชื่อทหารให้อัตโนมัติ)
    function openProfileAddInfirmaryModal() {
        const soldierName = @json($patient->rank . $patient->first_name . ' ' . $patient->last_name);
        const existingLoc = @json($activeInfStay->location_type ?? 'unit_infirmary');
        const existingCat = @json($activeInfStay->reason_category ?? 'respiratory');
        const existingBed = @json($activeInfStay->bed_or_zone ?? '');
        const existingDx = @json($activeInfStay->diagnosis_text ?? ($patient->militaryDischarge ? 'ฝากนอนระหว่างรอปลดประจำการ (' . $patient->militaryDischarge->diagnosis_text . ')' : ''));
        const existingCare = @json($activeInfStay->care_instruction ?? '');

        Swal.fire({
            title: '<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-bed-pulse text-teal-600 mr-2"></i>บันทึกแยกโรงนอน / ห้องพยาบาลหน่วยฝึก</div>',
            html: `
                <div class="text-left space-y-3.5 mt-2">
                    <div class="p-3 bg-teal-50 border border-teal-200 rounded-2xl text-xs sm:text-sm text-teal-900">
                        พลทหาร: <b class="text-base text-teal-800">${soldierName}</b>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">สถานที่แยกนอนพัก <span class="text-rose-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label class="flex items-start gap-2 p-2.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/60">
                                <input type="radio" name="swal_inf_loc" value="unit_infirmary" ${existingLoc === 'unit_infirmary' ? 'checked' : ''} class="mt-0.5 w-4 h-4 text-indigo-600">
                                <div>
                                    <div class="text-xs font-black text-slate-800">🏕️ ห้องพยาบาลหน่วยฝึก</div>
                                    <div class="text-[10px] text-slate-500">แยกโรงนอนที่หน่วยฝึก (CI)</div>
                                </div>
                            </label>
                            <label class="flex items-start gap-2 p-2.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/60">
                                <input type="radio" name="swal_inf_loc" value="camp_hospital" ${existingLoc === 'camp_hospital' ? 'checked' : ''} class="mt-0.5 w-4 h-4 text-amber-600">
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
                            <select id="swal_inf_cat" class="w-full rounded-xl border-2 border-slate-300 bg-slate-50 p-2 text-xs font-bold text-slate-800 outline-none">
                                <option value="respiratory" ${existingCat === 'respiratory' ? 'selected' : ''}>🦠 1. โรคระบบทางเดินหายใจ</option>
                                <option value="post_admit" ${existingCat === 'post_admit' ? 'selected' : ''}>🛌 2. พักฟื้นหลังออกจาก รพ.</option>
                                <option value="musculoskeletal" ${existingCat === 'musculoskeletal' ? 'selected' : ''}>🩹 3. บาดเจ็บกล้ามเนื้อ/แผล</option>
                                <option value="other" ${existingCat === 'other' ? 'selected' : ''}>📌 4. สาเหตุอื่นๆ</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ระบุโซน / เตียง (ถ้ามี)</label>
                            <input type="text" id="swal_inf_bed" value="${existingBed}" placeholder="เช่น โซนทางเดินหายใจ, เตียง 2" class="w-full rounded-xl border-2 border-slate-300 bg-white p-2 text-xs text-slate-800 outline-none">
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="text-xs font-bold text-slate-700">อาการสำคัญ / การวินิจฉัยโรค (Dx.) <span class="text-rose-500">*</span></label>
                            <button type="button" onclick="fetchProfileHosxpDx()" class="text-[11px] font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded border border-indigo-200">
                                <i class="fa-solid fa-cloud-arrow-down mr-1"></i> ดึง Dx. จาก HosXP
                            </button>
                        </div>
                        <input type="text" id="swal_inf_dx" value="${existingDx}" placeholder="เช่น ไข้หวัดใหญ่สายพันธุ์ A, มีไข้ไอเจ็บคอ, พักฟื้น..." class="w-full rounded-xl border-2 border-slate-300 bg-white p-2.5 text-xs sm:text-sm font-semibold text-slate-800 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">คำแนะนำการดูแล / การแยกกัก</label>
                        <div class="flex flex-wrap gap-1 mb-1.5">
                            <button type="button" onclick="document.getElementById('swal_inf_care').value='สวมหน้ากากอนามัย แยกของใช้ และวัดไข้เช้า-เย็น'" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-600 rounded text-[10px] font-bold border border-slate-200">+ แยกกักทางเดินหายใจ</button>
                            <button type="button" onclick="document.getElementById('swal_inf_care').value='งดฝึกภาคสนามและงดวิ่ง 3 วัน ทานยาต่อเนื่อง'" class="px-2 py-0.5 bg-slate-100 hover:bg-teal-50 text-slate-600 rounded text-[10px] font-bold border border-slate-200">+ งดฝึกหนัก 3 วัน</button>
                        </div>
                        <input type="text" id="swal_inf_care" value="${existingCare}" placeholder="ระบุคำสั่งการดูแลหรือจำนวนวันที่ให้แยกนอน..." class="w-full rounded-xl border-2 border-slate-300 bg-white p-2 text-xs text-slate-800 outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">วันที่เริ่มแยกนอน <span class="text-rose-500">*</span></label>
                            <input type="date" id="swal_inf_admit_date" value="{{ date('Y-m-d') }}" class="w-full rounded-xl border-2 border-slate-300 p-2 text-xs font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">เวลา <span class="text-rose-500">*</span></label>
                            <input type="time" id="swal_inf_admit_time" value="{{ date('H:i') }}" class="w-full rounded-xl border-2 border-slate-300 p-2 text-xs font-bold">
                        </div>
                    </div>
                </div>
            `,
            width: '36rem',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk mr-1"></i> บันทึกข้อมูล',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0d9488',
            customClass: { popup: 'rounded-3xl p-6' },
            preConfirm: () => {
                const dxEl = document.getElementById('swal_inf_dx');
                const dxVal = dxEl.value.trim();
                if (!dxVal) {
                    dxEl.classList.add('border-rose-500', 'bg-rose-50/40');
                    Swal.showValidationMessage('กรุณาระบุอาการสำคัญ หรือการวินิจฉัยโรค (Dx.) ก่อนกดบันทึกครับ');
                    return false;
                }
                return {
                    location_type: document.querySelector('input[name="swal_inf_loc"]:checked')?.value || 'unit_infirmary',
                    reason_category: document.getElementById('swal_inf_cat').value,
                    bed_or_zone: document.getElementById('swal_inf_bed').value.trim(),
                    diagnosis_text: dxVal,
                    care_instruction: document.getElementById('swal_inf_care').value.trim(),
                    admitted_date: document.getElementById('swal_inf_admit_date').value,
                    admitted_time: document.getElementById('swal_inf_admit_time').value
                };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = "{{ route('backend.recruit_infirmary.store') }}";
                form.innerHTML = `
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
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

    function fetchProfileHosxpDx() {
        fetch(`{{ route('backend.morbidity_reports.fetch_hosxp_dx') }}?patient_id={{ $patient->id }}`)
            .then(r => r.json())
            .then(data => {
                const dxInput = document.getElementById('swal_inf_dx');
                if (data.status === 'success' && dxInput) {
                    dxInput.value = data.dx_text;
                    dxInput.classList.remove('border-rose-500', 'bg-rose-50/40');
                    dxInput.classList.add('border-emerald-500', 'bg-emerald-50/30');
                } else {
                    Swal.showValidationMessage(data.message || 'ไม่พบรหัส Dx. ล่าสุดใน HosXP');
                }
            });
    }

    function openProfileDischargeInfirmaryModal(actionUrl, soldierName) {
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
                            <input type="date" id="swal_prof_inf_date" value="{{ date('Y-m-d') }}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">เวลา</label>
                            <input type="time" id="swal_prof_inf_time" value="{{ date('H:i') }}" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm font-bold">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">หมายเหตุเมื่อกลับโรงนอน</label>
                        <input type="text" id="swal_prof_inf_note" value="อาการดีขึ้น ไม่มีไข้ กลับเข้าโรงนอนและฝึกได้ตามปกติ" class="w-full px-3 py-2 border-2 border-slate-300 rounded-xl text-sm">
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
                    discharged_date: document.getElementById('swal_prof_inf_date').value,
                    discharged_time: document.getElementById('swal_prof_inf_time').value,
                    discharge_note: document.getElementById('swal_prof_inf_note').value.trim()
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

    // 🌟 ฟังก์ชันซิงค์และตรวจสอบรหัส HN จากฐานข้อมูล HosXP
    function syncPatientHosxp(isAuto = false, forceSync = false, updateName = false) {
        const btn = document.getElementById('btn-sync-hosxp');
        const icon = document.getElementById('sync-icon');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        fetch("{{ route('backend.patients.sync_hosxp', $patient->id) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                force_sync: forceSync,
                update_name: updateName
            })
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');

            if (data.status === 'synced') {
                const container = document.getElementById('hn-display-container');
                if (container) {
                    container.innerHTML = `
                        <span class="font-bold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-md border border-blue-100 animate-fade-in">
                            ${data.hn}
                        </span>
                    `;
                }
                if (data.updated_name) {
                    const nameEl = document.getElementById('patient-fullname-display');
                    if (nameEl) nameEl.textContent = data.updated_name;
                }
                if (data.pttype_name) {
                    const ptContainer = document.getElementById('pttype-display-container');
                    if (ptContainer) {
                        ptContainer.innerHTML = `
                            <span class="font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-md border border-emerald-200 text-xs inline-block animate-fade-in">
                                <i class="fa-solid fa-shield-heart mr-1 text-emerald-500"></i>${data.pttype_name}
                            </span>
                        `;
                    }
                }
                
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
            } 
            else if (data.status === 'mismatch_registrar') {
                Swal.fire({
                    icon: 'warning',
                    title: 'พบข้อมูลชื่อ-นามสกุลไม่ตรงกัน!',
                    html: `
                        <div class="text-left text-sm space-y-3 mt-2">
                            <p class="text-slate-600">เลขบัตร ปชช. <b class="font-mono text-slate-800">${data.cid}</b> พบใน HosXP (<b>HN: ${data.hn}</b>) แต่ชื่อไม่ตรงกัน:</p>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                <div class="text-xs text-slate-400 font-bold uppercase">ชื่อในระบบ KSVR MEWS:</div>
                                <div class="text-base font-bold text-slate-800">${data.mews_name}</div>
                            </div>
                            <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-200">
                                <div class="text-xs text-indigo-400 font-bold uppercase">ชื่อในระบบ HosXP รพ.:</div>
                                <div class="text-base font-bold text-indigo-800">${data.hosxp_name}</div>
                            </div>
                            <p class="text-xs text-amber-600 font-semibold"><i class="fa-solid fa-circle-info"></i> เฉพาะเจ้าหน้าที่ทะเบียน: กรุณาเลือกวิธีจัดการข้อมูล</p>
                        </div>
                    `,
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-user-check mr-1"></i> ผูก HN + ใช้ชื่อตาม HosXP',
                    denyButtonText: 'ผูกเฉพาะ HN (คงชื่อเดิม)',
                    cancelButtonText: 'ยังไม่ผูก (ตรวจสอบก่อน)',
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        denyButton: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl px-4 py-2.5 text-xs',
                        cancelButton: 'bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        syncPatientHosxp(false, true, true);
                    } else if (result.isDenied) {
                        syncPatientHosxp(false, true, false);
                    }
                });
            } 
            else if (data.status === 'mismatch_pass') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: data.message, showConfirmButton: false, timer: 4500 });
            } 
            else if (!isAuto && (data.status === 'not_found' || data.status === 'error')) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: data.message, showConfirmButton: false, timer: 3500 });
            }
        })
        .catch(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
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
                    toast: true, position: 'top-end', icon: 'info',
                    title: 'อัปเดตข้อมูล V/S ล่าสุดแล้ว',
                    showConfirmButton: false, timer: 3000
                });
            }
        })
        .catch(error => console.error('Real-time Refresh Error:', error));
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if(empty($patient->hn) && $canSyncHosxp)
            syncPatientHosxp(true);
        @endif

        const patientId = {{ $patient->id }};
        if (typeof window.Echo !== 'undefined') {
            window.Echo.channel('patient.' + patientId)
                .listen('.mews.updated', (e) => {
                    fetchLatestTimeline();
                });
        }
    });

    // 🩺 ฟังก์ชันเปิดป๊อปอัปแสดงคำแนะนำปฐมพยาบาลเบื้องต้น (AI Clinical Triage Copilot)
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

    // 🔄 ฟังก์ชันดึงข้อมูลวันนัดพบแพทย์จากตาราง oapp ของ HosXP เฉพาะพลทหารรายนี้
    function syncSinglePatientAppointments() {
        const btn = document.getElementById('btn-sync-pt-oapp');
        const icon = document.getElementById('icon-sync-pt-oapp');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        fetch("{{ route('backend.recruit_appointments.sync_hosxp') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                patient_id: '{{ $patient->id }}',
                intake_batch: '{{ $patient->intake_batch }}'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');

            if (data.status === 'success' && data.synced_count > 0) {
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: data.message, showConfirmButton: false, timer: 2000
                }).then(() => window.location.reload());
            } else {
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'info',
                    title: data.message || 'ไม่พบวันนัดใหม่ใน HosXP', showConfirmButton: false, timer: 3000
                });
            }
        })
        .catch(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
        });
    }
</script>
@endsection