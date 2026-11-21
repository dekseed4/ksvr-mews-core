@extends('layouts.backend')

@section('title', 'Medic Dashboard - KSVR Hospital')
@section('page_header', 'งานภาคสนาม (Field Operations)')

@section('content')
@php
    $medicUser = auth()->user();
    $medicUnitName = $medicUser?->militaryUnit?->short_name ?? $medicUser?->militaryUnit?->name ?? null;
    $medicBatch = $medicUser?->intake_batch ?? null;
    $evaluatedTodayCount = $myStats['evaluated_today'] ?? 0;
    $notFoundDetail = session('not_found_detail');
@endphp

<div class="max-w-4xl mx-auto mt-2 sm:mt-4 pb-16 space-y-5 md:space-y-7">

    @include('backend.dashboards.partials.view_tabs')

    <!-- 🟢 1. Header ทักทายเสนารักษ์ (Modern Compact + Dark Mode) -->
    <div class="bg-gradient-to-br from-slate-800 via-slate-800 to-emerald-950 rounded-3xl p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg text-white relative overflow-hidden border border-slate-700/80">
        <div class="absolute -right-8 -top-8 text-white/5 text-9xl pointer-events-none select-none">
            <i class="fa-solid fa-truck-medical"></i>
        </div>

        <div class="relative z-10 space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl sm:text-2xl font-black tracking-tight">
                    สวัสดี, เสนารักษ์ {{ $medicUser->name }}
                </h2>
                @if($medicUnitName)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                        <i class="fa-solid fa-tent mr-1"></i>{{ $medicUnitName }}@if($medicBatch) • ผลัด {{ $medicBatch }}@endif
                    </span>
                @endif
            </div>
            <p class="text-slate-300 text-xs sm:text-sm font-medium">
                <i class="fa-solid fa-calendar-day text-emerald-400 mr-1.5"></i>
                ปฏิบัติหน้าที่ประจำวันที่ {{ \Carbon\Carbon::now()->locale('th')->addYears(543)->translatedFormat('j F Y') }}
            </p>
        </div>

        <div class="relative z-10 bg-white/10 rounded-2xl px-5 py-3.5 flex items-center justify-between sm:justify-center gap-4 backdrop-blur-sm border border-white/10 shrink-0">
            <div class="text-left sm:text-center">
                <p class="text-[10px] text-slate-300 uppercase tracking-widest font-bold">ประเมินวันนี้</p>
                <div class="text-2xl sm:text-3xl font-black text-emerald-400 leading-tight mt-0.5">
                    <span id="medic-today-count">{{ $evaluatedTodayCount }}</span>
                    <span class="text-xs sm:text-sm font-medium text-slate-200 ml-0.5">นาย</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center text-lg sm:hidden">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
        </div>
    </div>

    <!-- 🔍 2. ศูนย์ค้นหาผู้ป่วย & เริ่มประเมิน MEWS ภาคสนาม (อัปเกรดเทียบเท่าหน้าประเมิน MEWS) -->
    <div id="medic-search-card" class="bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-700 space-y-4 transition-all">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm shrink-0">
                        <i class="fa-solid fa-stethoscope"></i>
                    </span>
                    <span>ค้นหาประวัติ / เริ่มประเมิน MEWS ใหม่</span>
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    พิมพ์ชื่อ-นามสกุล, รหัส HN, เลข ปชช. 13 หลัก หรือใช้เสียง/สแกน QR เพื่อดึงประวัติทันที
                </p>
            </div>

            <a href="{{ route('backend.patients.search') }}"
               class="self-start sm:self-center text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 px-3 py-1.5 rounded-xl border border-indigo-100 dark:border-indigo-800 transition-colors inline-flex items-center gap-1.5 shrink-0">
                <i class="fa-solid fa-id-card"></i>
                <span>หน้าสแกนบัตรเต็มรูปแบบ</span>
            </a>
        </div>

        <form id="medic-search-form" action="{{ route('backend.patients.processSearch') }}" method="POST" class="space-y-3">
            @csrf
            <input type="hidden" id="medic-scanned-name" name="scanned_name" value="{{ old('scanned_name') }}">
            <input type="hidden" id="medic-is-scanned" name="is_scanned" value="{{ old('is_scanned', 0) }}">

            <div class="relative">
                <!-- ช่องกรอกค้นหาหลัก -->
                <div class="flex items-center bg-slate-50 dark:bg-slate-900 rounded-2xl p-1.5 sm:p-2 border-2 border-slate-200 dark:border-slate-700 focus-within:border-emerald-500 focus-within:bg-white dark:focus-within:bg-slate-900 focus-within:ring-4 focus-within:ring-emerald-500/15 transition-all shadow-inner">
                    <div class="pl-3 pr-2 text-slate-400 dark:text-slate-500 shrink-0">
                        <i id="medic-search-icon" class="fa-solid fa-magnifying-glass text-base sm:text-lg"></i>
                    </div>

                    <input type="text"
                           id="medic-search-input"
                           name="search_term"
                           value="{{ old('search_term') }}"
                           autocomplete="off"
                           required
                           placeholder="พิมพ์ชื่อ, นามสกุล, HN หรือเลข ปชช. 13 หลัก..."
                           class="w-full bg-transparent border-none focus:ring-0 text-slate-800 dark:text-white text-sm sm:text-base px-1.5 py-2 outline-none font-semibold placeholder-slate-400">

                    <!-- ปุ่มล้างข้อความ -->
                    <button type="button"
                            id="medic-search-clear-btn"
                            onclick="clearMedicSearchInput()"
                            class="hidden w-8 h-8 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30 flex items-center justify-center transition-colors shrink-0 mr-1"
                            title="ล้างข้อความ">
                        <i class="fa-solid fa-circle-xmark text-base"></i>
                    </button>

                    <!-- ปุ่มค้นหา (แสดงบนจอใหญ่) -->
                    <button type="submit"
                            id="medic-search-submit-desktop"
                            class="hidden sm:inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold text-sm shadow-sm transition-all shrink-0">
                        <i class="fa-solid fa-arrow-right"></i>
                        <span>ค้นหา</span>
                    </button>
                </div>

                <!-- ผลตรวจสูตรเลขบัตร 13 หลัก (Mod-11) แบบเรียลไทม์ -->
                <div id="medic-cid-status-hint" class="hidden mt-2 text-xs font-bold px-3 py-1.5 rounded-xl border flex items-center gap-2"></div>

                <!-- 📋 กล่องรายชื่อ Autocomplete อัจฉริยะ (แสดงหน่วยฝึก, HN, CID และสถานะล็อกกำลังพล) -->
                <div id="medic-autocomplete-results"
                     class="hidden absolute left-0 right-0 top-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-[0_18px_45px_-12px_rgba(15,23,42,0.28)] rounded-2xl z-50 max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/70">
                </div>
            </div>

            <!-- แถบปุ่มเครื่องมือภาคสนาม (มือถือเรียงสวยงาม แตะง่าย) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-0.5">
                <button type="button"
                        id="medic-voice-btn"
                        onclick="startMedicVoiceSearch()"
                        class="py-2.5 px-3 rounded-xl bg-slate-100 dark:bg-slate-700/70 hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-slate-700 dark:text-slate-200 hover:text-indigo-700 dark:hover:text-indigo-300 border border-slate-200 dark:border-slate-600 text-xs sm:text-sm font-bold inline-flex items-center justify-center gap-2 transition-all">
                    <i id="medic-voice-icon" class="fa-solid fa-microphone text-indigo-500"></i>
                    <span id="medic-voice-label">พูดชื่อ / เลข</span>
                </button>

                <button type="button"
                        onclick="openMedicQrScanner()"
                        class="py-2.5 px-3 rounded-xl bg-slate-100 dark:bg-slate-700/70 hover:bg-teal-50 dark:hover:bg-teal-900/40 text-slate-700 dark:text-slate-200 hover:text-teal-700 dark:hover:text-teal-300 border border-slate-200 dark:border-slate-600 text-xs sm:text-sm font-bold inline-flex items-center justify-center gap-2 transition-all">
                    <i class="fa-solid fa-qrcode text-teal-500"></i>
                    <span>สแกน QR / บาร์โค้ด</span>
                </button>

                <button type="submit"
                        class="col-span-2 sm:col-span-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs sm:text-sm font-black shadow-sm inline-flex items-center justify-center gap-2 transition-all sm:hidden">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>ค้นหาและเริ่มประเมิน MEWS</span>
                </button>

                <a href="{{ route('backend.patients.create') }}"
                   class="hidden sm:inline-flex py-2.5 px-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 text-slate-600 dark:text-slate-300 hover:text-emerald-700 border border-slate-200 dark:border-slate-700 text-xs sm:text-sm font-bold items-center justify-center gap-2 transition-all">
                    <i class="fa-solid fa-user-plus text-emerald-500"></i>
                    <span>ลงทะเบียนทหารใหม่</span>
                </a>
            </div>

            @error('search_term')
                <div class="text-xs font-bold text-rose-600 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 px-3.5 py-2 rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </form>

        <!-- ⚠️ กล่องแสดงผลกรณีค้นหาไม่พบ (รองรับ session('not_found_detail') จาก PatientController::processSearch ครบ 100%) -->
        @if(is_array($notFoundDetail))
            @php
                $nfCid = $notFoundDetail['cid'] ?? null;
                $nfFormattedCid = $notFoundDetail['formatted_cid'] ?? null;
                $nfName = $notFoundDetail['full_name'] ?? null;
                $nfSource = $notFoundDetail['name_source'] ?? null;
                $nfIsValidCid = $notFoundDetail['is_cid_valid'] ?? false;
                $nfHosxpHn = $notFoundDetail['hosxp_hn'] ?? null;
                $nfExtraNote = $notFoundDetail['extra_note'] ?? null;
                $nfSearched = $notFoundDetail['searched_term'] ?? '';
            @endphp
            <div class="mt-3 p-4 sm:p-5 rounded-2xl bg-amber-50/90 dark:bg-amber-950/30 border-2 border-amber-300 dark:border-amber-700/80 space-y-3 animate-fade-in-up">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="fa-solid fa-user-magnifying-glass text-base"></i>
                        </div>
                        <div>
                            <h4 class="text-sm sm:text-base font-black text-amber-900 dark:text-amber-200">
                                ไม่พบรายชื่อในขอบเขตหน่วย/ผลัดที่ดูแล
                            </h4>
                            <p class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5">
                                คำค้นหา: <span class="font-mono font-bold bg-white/80 dark:bg-slate-900 px-2 py-0.5 rounded">{{ $nfSearched }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                @if($nfCid || $nfName || $nfExtraNote)
                    <div class="bg-white dark:bg-slate-900/90 rounded-xl p-3.5 border border-amber-200 dark:border-amber-800 space-y-2 text-xs sm:text-sm">
                        @if($nfCid)
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-slate-500 font-semibold">เลข ปชช.:</span>
                                    <span class="font-mono font-black text-slate-800 dark:text-white">{{ $nfFormattedCid ?: $nfCid }}</span>
                                    @if($nfIsValidCid)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-check mr-0.5"></i>เลข 13 หลักถูกต้อง
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-700 border border-rose-200">
                                            <i class="fa-solid fa-triangle-exclamation mr-0.5"></i>เลขบัตรผิดหลัก (Mod-11)
                                        </span>
                                    @endif
                                </div>
                                <button type="button"
                                        onclick="copyMedicText('{{ $nfCid }}', this)"
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white text-slate-600 dark:text-slate-300 text-[11px] font-bold transition-colors">
                                    <i class="fa-regular fa-copy mr-1"></i>ก๊อปปี้เลข
                                </button>
                            </div>
                        @endif

                        @if($nfName)
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-slate-500 font-semibold">ชื่อที่ตรวจพบ:</span>
                                <span class="font-black text-indigo-700 dark:text-indigo-300">{{ $nfName }}</span>
                                @if($nfSource === 'hosxp')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700 border border-sky-200">
                                        พบใน HosXP @if($nfHosxpHn) (HN: {{ $nfHosxpHn }}) @endif
                                    </span>
                                @elseif($nfSource === 'ksvr_other_scope')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-700 border border-purple-200">
                                        มีประวัติใน KSVR ต่างสังกัด
                                    </span>
                                @endif
                            </div>
                        @endif

                        @if($nfExtraNote)
                            <div class="text-xs text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/50 px-3 py-2 rounded-lg border border-amber-200/70">
                                <i class="fa-solid fa-circle-info mr-1"></i>{{ $nfExtraNote }}
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-2 pt-0.5">
                    @if(empty($notFoundDetail['exists_in_ksvr']))
                        <a href="{{ route('backend.patients.create') }}"
                           class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black inline-flex items-center gap-1.5 shadow-sm transition-colors">
                            <i class="fa-solid fa-user-plus"></i> ลงทะเบียนพลทหารรายใหม่
                        </a>
                    @endif
                    <button type="button" onclick="focusMedicSearch(true)"
                            class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 text-slate-700 dark:text-slate-200 border border-amber-300 dark:border-slate-600 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
                        <i class="fa-solid fa-rotate-left"></i> ลองค้นหาใหม่
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- 🌟 ปุ่มลอยเสริมเฉพาะหน้า Medic: กดเพื่อเลื่อนกลับมาโฟกัสช่องค้นหาได้ทันทีจากทุกจุดของหน้า -->
    @section('floating_buttons')
        <button type="button" onclick="focusMedicSearch(false)"
                class="bg-emerald-500 hover:bg-emerald-400 text-white rounded-full w-12 h-12 md:w-14 md:h-14 flex items-center justify-center shadow-[0_10px_20px_rgba(16,185,129,0.4)] hover:shadow-[0_15px_30px_rgba(16,185,129,0.5)] transition-all duration-300 transform hover:-translate-y-1 focus:outline-none ring-4 ring-emerald-500/20 group pointer-events-auto"
                title="ค้นหาประวัติ / ประเมินใหม่">
            <i class="fa-solid fa-magnifying-glass-plus text-xl md:text-2xl group-hover:scale-110 transition-transform duration-300"></i>
        </button>
    @endsection

    <!-- 📋 3. ติดตามสถานะคนไข้ของฉัน (แยก Container สำหรับ Real-time Refresh ไม่ทับช่องค้นหา) -->
    <div id="medic-tracking-section" class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
            <h3 class="text-base sm:text-lg font-black text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-route text-blue-500"></i>
                <span>ติดตามสถานะผู้ป่วยของฉัน</span>
                <span id="medic-tracking-count-badge" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-200/80 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    {{ count($myPatients) }} รายการ
                </span>
            </h3>

            <!-- แถบกรองสถานะด่วน (ฝั่งหน้าบ้าน ไม่ยิง DB) -->
            @if(count($myPatients) > 0)
                <div class="flex flex-wrap items-center gap-1.5" id="medic-tracking-filters">
                    <button type="button" data-filter="all" onclick="setMedicTrackingFilter('all')"
                            class="js-track-filter-btn px-3 py-1 rounded-xl text-xs font-bold bg-slate-800 text-white shadow-sm transition-all">
                        ทั้งหมด
                    </button>
                    <button type="button" data-filter="active" onclick="setMedicTrackingFilter('active')"
                            class="js-track-filter-btn px-3 py-1 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 transition-all">
                        กำลังตรวจ/รอรับ
                    </button>
                    <button type="button" data-filter="admit" onclick="setMedicTrackingFilter('admit')"
                            class="js-track-filter-btn px-3 py-1 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 transition-all">
                        Admit/Refer
                    </button>
                    <button type="button" data-filter="done" onclick="setMedicTrackingFilter('done')"
                            class="js-track-filter-btn px-3 py-1 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 transition-all">
                        กลับหน่วยแล้ว
                    </button>
                </div>
            @endif
        </div>

        <div id="medic-tracking-container" class="space-y-3.5">
            @forelse($myPatients as $case)
                @php
                    $medicHasReturned = !empty($case->returned_to_unit_at);
                    $trackGroup = 'active';
                    if ($case->is_cancelled) {
                        $trackGroup = 'done';
                    } elseif ($case->is_completed) {
                        if ($case->final_discharge === 'recovered' || $medicHasReturned) {
                            $trackGroup = 'done';
                        } elseif (in_array($case->final_discharge, ['admitted', 'refer'], true)) {
                            $trackGroup = 'admit';
                        } else {
                            $trackGroup = 'done';
                        }
                    }

                    $rawMedicSyms = is_string($case->symptoms) ? json_decode($case->symptoms, true) : ($case->symptoms ?? []);
                    $isMedicRoutedToER = in_array($case->risk_level, ['observe', 'emergency'], true) || (!empty($rawMedicSyms));
                @endphp

                <a href="{{ route('backend.mews.show', $case->id) }}"
                   data-track-group="{{ $trackGroup }}"
                   class="js-medic-case-card block bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-5 shadow-sm border border-slate-100 dark:border-slate-700 relative overflow-hidden transition-all hover:shadow-md hover:border-emerald-300 dark:hover:border-emerald-500/60 group cursor-pointer">

                    <div class="flex flex-col sm:flex-row justify-between gap-3.5 sm:gap-4">
                        <!-- ข้อมูลคนไข้ -->
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h4 class="font-black text-slate-800 dark:text-white text-base sm:text-lg group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors truncate">
                                    {{ $case->patient?->rank }}{{ $case->patient?->first_name }} {{ $case->patient?->last_name }}
                                </h4>
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full shrink-0 {{ $case->risk_level === 'emergency' ? 'bg-rose-100 text-rose-700' : ($case->risk_level === 'observe' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">
                                    MEWS: {{ $case->total_score }}
                                </span>
                            </div>

                            <div class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span>
                                    <i class="fa-solid fa-tent mr-1 text-slate-400"></i>
                                    @if($case->patient?->militaryUnit)
                                        {{ $case->patient->militaryUnit->short_name ?? $case->patient->militaryUnit->name }}
                                    @else
                                        {{ $case->patient?->company ?? 'ไม่ระบุสังกัด' }}
                                    @endif
                                </span>
                                <span class="text-slate-300 dark:text-slate-600">|</span>
                                <span>
                                    <i class="fa-regular fa-clock text-slate-400 mr-1"></i>
                                    ส่งเมื่อ: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $case->created_at?->format('H:i') ?? '-' }} น.</span>
                                </span>
                            </div>
                        </div>

                        <!-- 🌟 Tracking Status Badge (ครบทุกสถานะเหมือนเดิม) -->
                        <div class="shrink-0 flex items-center justify-between sm:justify-end gap-3">
                            <div class="w-full sm:w-auto">
                                @if($case->is_cancelled)
                                    <div class="bg-slate-100 dark:bg-slate-700/70 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-600 flex items-center justify-center text-slate-500 dark:text-slate-300 shrink-0"><i class="fa-solid fa-ban"></i></div>
                                        <div>
                                            <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                            <div class="font-bold text-xs sm:text-sm">ถูกยกเลิกการประเมิน</div>
                                        </div>
                                    </div>
                                @elseif($case->is_completed)
                                    @if($case->final_discharge === 'recovered')
                                        <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                            <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center text-emerald-600 dark:text-emerald-300 shrink-0"><i class="fa-solid fa-house-chimney-medical"></i></div>
                                            <div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                <div class="font-bold text-xs sm:text-sm">จำหน่ายกลับหน่วย</div>
                                            </div>
                                        </div>
                                    @elseif($case->final_discharge === 'admitted')
                                        @if($medicHasReturned)
                                            <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                                <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center text-emerald-600 dark:text-emerald-300 shrink-0"><i class="fa-solid fa-house-chimney-check"></i></div>
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">Admit รพ.ค่ายฯ</div>
                                                    <div class="font-bold text-xs sm:text-sm">กลับหน่วยฝึกแล้ว</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                                <div class="w-8 h-8 rounded-full bg-amber-200 dark:bg-amber-900/60 flex items-center justify-center text-amber-700 dark:text-amber-300 shrink-0"><i class="fa-solid fa-bed-pulse"></i></div>
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                    <div class="font-bold text-xs sm:text-sm">Admit นอน รพ. (ยังไม่กลับหน่วย)</div>
                                                </div>
                                            </div>
                                        @endif
                                    @elseif($case->final_discharge === 'refer')
                                        @if($medicHasReturned)
                                            <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                                <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center text-emerald-600 dark:text-emerald-300 shrink-0"><i class="fa-solid fa-house-chimney-check"></i></div>
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">ส่งตัว (Refer)</div>
                                                    <div class="font-bold text-xs sm:text-sm">กลับหน่วยฝึกแล้ว</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                                <div class="w-8 h-8 rounded-full bg-blue-200 dark:bg-blue-900/60 flex items-center justify-center text-blue-700 dark:text-blue-300 shrink-0"><i class="fa-solid fa-ambulance"></i></div>
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                    <div class="font-bold text-xs sm:text-sm">ส่งตัว Refer (ยังไม่กลับหน่วย)</div>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                @elseif($case->is_accepted_by_er)
                                    <div class="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                        <div class="w-8 h-8 rounded-full bg-indigo-200 dark:bg-indigo-900/60 flex items-center justify-center text-indigo-700 dark:text-indigo-300 animate-pulse shrink-0"><i class="fa-solid fa-user-nurse"></i></div>
                                        <div>
                                            <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                            <div class="font-bold text-xs sm:text-sm">ER รับผู้ป่วยแล้ว</div>
                                        </div>
                                    </div>
                                @else
                                    @if($isMedicRoutedToER)
                                        <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                            <div class="w-8 h-8 rounded-full bg-rose-100 dark:bg-rose-900/60 flex items-center justify-center text-rose-600 dark:text-rose-300 shrink-0"><i class="fa-solid fa-spinner fa-spin-pulse"></i></div>
                                            <div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                <div class="font-bold text-xs sm:text-sm">รอ ER รับเคส...</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="bg-teal-50 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800 text-teal-700 dark:text-teal-300 px-3.5 py-2 rounded-xl flex items-center gap-2.5 shadow-sm">
                                            <div class="w-8 h-8 rounded-full bg-teal-100 dark:bg-teal-900/60 flex items-center justify-center text-teal-600 dark:text-teal-300 shrink-0"><i class="fa-solid fa-user-doctor"></i></div>
                                            <div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                <div class="font-bold text-xs sm:text-sm">รอตรวจแผนก OPD</div>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <div class="text-slate-300 dark:text-slate-600 group-hover:text-emerald-500 group-hover:translate-x-1 transition-all ml-1 hidden sm:block">
                                <i class="fa-solid fa-chevron-right text-lg"></i>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="bg-white dark:bg-slate-800 rounded-3xl p-10 shadow-sm border border-dashed border-slate-300 dark:border-slate-700 text-center">
                    <div class="w-16 h-16 bg-slate-50 dark:bg-slate-900 text-slate-300 dark:text-slate-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <h4 class="text-base sm:text-lg font-bold text-slate-700 dark:text-slate-200 mb-1">ยังไม่มีประวัติการประเมินวันนี้</h4>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm">พิมพ์ค้นหาชื่อพลทหาร หรือแตะปุ่มพูด/สแกน QR ด้านบนเพื่อเริ่มประเมิน MEWS ได้ทันที</p>
                </div>
            @endforelse

            <div id="medic-tracking-filter-empty" class="hidden bg-white dark:bg-slate-800 rounded-3xl p-8 text-center border border-dashed border-slate-200 dark:border-slate-700 text-slate-400 text-sm font-bold">
                ไม่มีรายการในหมวดสถานะที่เลือกครับ
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    const medicAutocompleteUrl = "{{ route('backend.patients.autocomplete') }}";
    const medicPatientShowTemplate = "{{ route('backend.patients.show', ['patient' => '__ID__']) }}";

    let medicSearchTimer = null;
    let medicAbortController = null;
    let medicActiveIndex = -1;
    let medicCurrentItems = [];
    let medicCurrentTrackFilter = 'all';
    let medicQrInstance = null;

    function escapeMedicHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ✅ ตรวจสอบสูตร Mod-11 สำหรับเลขบัตรประชาชน 13 หลัก
    function checkThaiCidMod11(digits) {
        if (!/^[1-8]\d{12}$/.test(digits)) return false;
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(digits.charAt(i), 10) * (13 - i);
        }
        return ((11 - (sum % 11)) % 10) === parseInt(digits.charAt(12), 10);
    }

    function updateCidValidationHint(rawVal) {
        const hintEl = document.getElementById('medic-cid-status-hint');
        if (!hintEl) return;

        const digitsOnly = rawVal.replace(/[^0-9]/g, '');
        const hasLetters = /[a-zA-Zก-๙]/.test(rawVal);

        if (!hasLetters && digitsOnly.length === 13) {
            const isValid = checkThaiCidMod11(digitsOnly);
            hintEl.classList.remove('hidden');
            if (isValid) {
                hintEl.className = 'mt-2 text-xs font-bold px-3 py-1.5 rounded-xl border flex items-center gap-2 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
                hintEl.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-500"></i><span>เลขบัตรประชาชน 13 หลักถูกต้องตามสูตรตรวจสอบ (กด Enter เพื่อค้นหาได้เลย)</span>';
            } else {
                hintEl.className = 'mt-2 text-xs font-bold px-3 py-1.5 rounded-xl border flex items-center gap-2 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
                hintEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-rose-500"></i><span>เลขครบ 13 หลัก แต่ไม่ผ่านสูตรตรวจสอบ (Mod-11) กรุณาเช็กตัวเลขอีกครั้งครับ</span>';
            }
        } else {
            hintEl.classList.add('hidden');
        }
    }

    // 🌟 โฟกัสและเลื่อนขึ้นมายังกล่องค้นหาหลัก (พร้อมคงชื่อ openSearchModal เดิมกันโค้ดอื่นเรียก)
    function focusMedicSearch(clearFirst = false) {
        const card = document.getElementById('medic-search-card');
        const input = document.getElementById('medic-search-input');
        if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.classList.add('ring-4', 'ring-emerald-400/40');
            setTimeout(() => card.classList.remove('ring-4', 'ring-emerald-400/40'), 1100);
        }
        if (input) {
            if (clearFirst) {
                clearMedicSearchInput();
            }
            setTimeout(() => input.focus(), 220);
        }
    }
    window.openSearchModal = () => focusMedicSearch(false);

    function clearMedicSearchInput() {
        const input = document.getElementById('medic-search-input');
        const results = document.getElementById('medic-autocomplete-results');
        const clearBtn = document.getElementById('medic-search-clear-btn');
        const hintEl = document.getElementById('medic-cid-status-hint');

        if (input) {
            input.value = '';
            input.focus();
        }
        if (results) {
            results.innerHTML = '';
            results.classList.add('hidden');
        }
        if (clearBtn) clearBtn.classList.add('hidden');
        if (hintEl) hintEl.classList.add('hidden');
        document.getElementById('medic-is-scanned').value = '0';
        document.getElementById('medic-scanned-name').value = '';
        medicActiveIndex = -1;
        medicCurrentItems = [];
    }

    // 🔍 ดึงรายชื่อ Autocomplete จากฝั่ง KSVR (ไม่แตะ HosXP)
    function fetchMedicAutocomplete(term) {
        const resultsEl = document.getElementById('medic-autocomplete-results');
        const iconEl = document.getElementById('medic-search-icon');
        if (!resultsEl) return;

        if (medicAbortController) {
            medicAbortController.abort();
        }
        medicAbortController = new AbortController();

        if (iconEl) iconEl.className = 'fa-solid fa-spinner fa-spin text-emerald-500 text-base sm:text-lg';

        fetch(`${medicAutocompleteUrl}?term=${encodeURIComponent(term)}`, {
            signal: medicAbortController.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('Autocomplete failed');
            return res.json();
        })
        .then(data => {
            medicCurrentItems = Array.isArray(data) ? data : [];
            medicActiveIndex = -1;
            renderMedicAutocompleteList(medicCurrentItems);
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.warn('Medic Autocomplete Warning:', err);
            }
        })
        .finally(() => {
            if (iconEl) iconEl.className = 'fa-solid fa-magnifying-glass text-base sm:text-lg';
        });
    }

    function renderMedicAutocompleteList(items) {
        const resultsEl = document.getElementById('medic-autocomplete-results');
        if (!resultsEl) return;

        if (!items.length) {
            resultsEl.innerHTML = `
                <div class="px-4 py-5 text-center space-y-1">
                    <div class="text-sm font-bold text-slate-600 dark:text-slate-300">ไม่พบรายชื่อในหน่วย/ผลัดที่ดูแล</div>
                    <div class="text-xs text-slate-400">กดปุ่ม <b>"ค้นหา"</b> หรือ <b>Enter</b> เพื่อตรวจสอบเลข 13 หลักเพิ่มเติม</div>
                </div>
            `;
            resultsEl.classList.remove('hidden');
            return;
        }

        let html = '';
        items.forEach((pt, idx) => {
            const fullName = `${pt.rank || ''}${pt.first_name || ''} ${pt.last_name || ''}`.trim();
            const hn = pt.hn || '-';
            const cid = pt.cid || '-';
            const unit = pt.company || '-';
            const isLocked = Boolean(pt.is_locked);
            const statusLabel = pt.status_label || 'งดประเมิน MEWS';

            const rightBadge = isLocked
                ? `<span class="px-2.5 py-1 rounded-xl bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-[11px] font-black shrink-0"><i class="fa-solid fa-lock mr-1"></i>งดประเมิน</span>`
                : `<span class="px-2.5 py-1 rounded-xl bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-[11px] font-black shrink-0">ประเมิน <i class="fa-solid fa-arrow-right ml-0.5"></i></span>`;

            const lockSubText = isLocked
                ? `<div class="text-[11px] font-bold text-rose-600 dark:text-rose-400 mt-0.5"><i class="fa-solid fa-circle-exclamation mr-1"></i>${escapeMedicHtml(statusLabel)}</div>`
                : '';

            html += `
                <button type="button"
                        data-ac-index="${idx}"
                        onclick="selectMedicAutocompleteItem(${idx})"
                        class="js-medic-ac-item w-full text-left px-4 py-3 hover:bg-emerald-50/80 dark:hover:bg-slate-700/70 focus:bg-emerald-50 dark:focus:bg-slate-700 transition-colors flex items-center justify-between gap-3 outline-none">
                    <div class="min-w-0">
                        <div class="text-sm font-black text-slate-800 dark:text-white truncate">${escapeMedicHtml(fullName)}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono truncate mt-0.5">
                            HN: ${escapeMedicHtml(hn)} • ปชช: ${escapeMedicHtml(cid)} • <span class="font-sans font-bold text-indigo-600 dark:text-indigo-400">${escapeMedicHtml(unit)}</span>
                        </div>
                        ${lockSubText}
                    </div>
                    ${rightBadge}
                </button>
            `;
        });

        resultsEl.innerHTML = html;
        resultsEl.classList.remove('hidden');
    }

    function selectMedicAutocompleteItem(idx) {
        const pt = medicCurrentItems[idx];
        if (!pt) return;

        const resultsEl = document.getElementById('medic-autocomplete-results');
        if (resultsEl) resultsEl.classList.add('hidden');

        // 🛡️ หากพลทหารอยู่ในสถานะปลดทหาร/ขาดหนี/ต้องโทษ/เสียชีวิต แจ้งเตือนชัดเจนและพาไปดูแฟ้มประวัติแทน
        if (pt.is_locked) {
            const fullName = `${pt.rank || ''}${pt.first_name || ''} ${pt.last_name || ''}`.trim();
            const profileUrl = medicPatientShowTemplate.replace('__ID__', pt.id);

            Swal.fire({
                icon: 'warning',
                title: 'ไม่สามารถประเมิน MEWS ได้',
                html: `
                    <div class="text-sm text-slate-600 dark:text-slate-300 space-y-2 mt-1">
                        <p><b>${escapeMedicHtml(fullName)}</b></p>
                        <p class="p-3 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 font-bold text-xs">
                            <i class="fa-solid fa-lock mr-1"></i>สถานะกำลังพล: ${escapeMedicHtml(pt.status_label || 'งดประเมิน')}
                        </p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-address-card mr-1"></i> เปิดแฟ้มประวัติ',
                cancelButtonText: 'ปิด',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-3xl p-6' }
            }).then(res => {
                if (res.isConfirmed) window.location.href = profileUrl;
            });
            return;
        }

        const input = document.getElementById('medic-search-input');
        const form = document.getElementById('medic-search-form');
        const bestKey = (pt.cid && pt.cid !== '-')
            ? pt.cid
            : ((pt.hn && pt.hn !== '-') ? pt.hn : `${pt.first_name || ''} ${pt.last_name || ''}`.trim());

        if (input && form) {
            input.value = bestKey;
            form.submit();
        }
    }

    function updateAutocompleteActiveHighlight() {
        const nodes = document.querySelectorAll('.js-medic-ac-item');
        nodes.forEach((el, i) => {
            el.classList.toggle('bg-emerald-50', i === medicActiveIndex);
            el.classList.toggle('dark:bg-slate-700', i === medicActiveIndex);
            if (i === medicActiveIndex) {
                el.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    // 🎙️ ค้นหาด้วยเสียงภาษาไทย (Web Speech API — เหมาะกับเสนารักษ์หน้างานบนมือถือ)
    function startMedicVoiceSearch() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'เบราว์เซอร์นี้ยังไม่รองรับการค้นหาด้วยเสียง กรุณาใช้ Chrome หรือ Safari ครับ',
                showConfirmButton: false,
                timer: 3500
            });
            return;
        }

        const btn = document.getElementById('medic-voice-btn');
        const icon = document.getElementById('medic-voice-icon');
        const label = document.getElementById('medic-voice-label');
        const input = document.getElementById('medic-search-input');

        const recognition = new SpeechRecognition();
        recognition.lang = 'th-TH';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.onstart = () => {
            if (btn) btn.classList.add('bg-rose-50', 'border-rose-300', 'text-rose-700');
            if (icon) icon.className = 'fa-solid fa-microphone-lines text-rose-500 animate-pulse';
            if (label) label.textContent = 'กำลังฟัง... พูดชื่อได้เลย';
        };

        recognition.onresult = (event) => {
            const spoken = (event.results[0][0].transcript || '').trim();
            if (spoken && input) {
                // ลบคำนำหน้าทั่วไปที่มักเผลอพูดออกเล็กน้อยเพื่อให้ค้นเจอแม่นขึ้น
                const cleaned = spoken.replace(/^(พลทหาร|พลฯ|นาย)\s*/u, '').trim();
                input.value = cleaned || spoken;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            }
        };

        recognition.onerror = () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: 'ไม่สามารถรับเสียงได้ กรุณาอนุญาตการใช้ไมโครโฟนแล้วลองใหม่ครับ',
                showConfirmButton: false,
                timer: 3000
            });
        };

        recognition.onend = () => {
            if (btn) btn.classList.remove('bg-rose-50', 'border-rose-300', 'text-rose-700');
            if (icon) icon.className = 'fa-solid fa-microphone text-indigo-500';
            if (label) label.textContent = 'พูดชื่อ / เลข';
        };

        try {
            recognition.start();
        } catch (e) {}
    }

    // 📷 สแกน QR Code / บาร์โค้ดด้วยกล้องมือถือ (โหลดไลบรารีเฉพาะตอนกดใช้ ไม่หนักหน้าแดชบอร์ด)
    function loadHtml5QrScript() {
        return new Promise((resolve, reject) => {
            if (typeof window.Html5Qrcode !== 'undefined') return resolve();
            const s = document.createElement('script');
            s.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('ไม่สามารถโหลดตัวสแกนกล้องได้'));
            document.head.appendChild(s);
        });
    }

    function stopMedicQrScanner() {
        if (medicQrInstance) {
            try {
                medicQrInstance.stop().catch(() => {}).finally(() => {
                    try { medicQrInstance.clear(); } catch (e) {}
                    medicQrInstance = null;
                });
            } catch (e) {
                medicQrInstance = null;
            }
        }
    }

    async function openMedicQrScanner() {
        if (!window.isSecureContext) {
            Swal.fire({
                icon: 'info',
                title: 'ต้องเปิดใช้งานผ่าน HTTPS เพื่อเปิดกล้อง',
                text: 'เบราว์เซอร์จำกัดการเข้าถึงกล้องเฉพาะหน้าเว็บ HTTPS เท่านั้นครับ',
                customClass: { popup: 'rounded-3xl' }
            });
            return;
        }

        Swal.fire({
            title: '<div class="text-lg font-black text-slate-800 flex items-center justify-center gap-2"><i class="fa-solid fa-qrcode text-teal-500"></i> สแกน QR / บาร์โค้ดผู้ป่วย</div>',
            html: `
                <div class="space-y-3 mt-2">
                    <div id="medic-qr-reader" class="w-full rounded-2xl overflow-hidden bg-slate-900 min-h-[240px] flex items-center justify-center text-slate-300 text-xs">
                        <span><i class="fa-solid fa-spinner fa-spin mr-1.5"></i>กำลังเปิดกล้องหลัง...</span>
                    </div>
                    <p class="text-xs text-slate-500">ส่องไปที่ QR Code บัตรประจำตัวทหาร หรือบาร์โค้ด HN ระบบจะดึงประวัติให้อัตโนมัติ</p>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'ปิดกล้อง',
            customClass: { popup: 'rounded-3xl p-5' },
            didOpen: async () => {
                try {
                    await loadHtml5QrScript();
                    const readerEl = document.getElementById('medic-qr-reader');
                    if (readerEl) readerEl.innerHTML = '';

                    medicQrInstance = new Html5Qrcode('medic-qr-reader');
                    await medicQrInstance.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: { width: 230, height: 180 } },
                        (decodedText) => {
                            const cleanCode = (decodedText || '').trim();
                            if (!cleanCode) return;

                            stopMedicQrScanner();
                            Swal.close();

                            const input = document.getElementById('medic-search-input');
                            document.getElementById('medic-is-scanned').value = '1';
                            if (input) {
                                input.value = cleanCode;
                                updateCidValidationHint(cleanCode);
                                document.getElementById('medic-search-form').submit();
                            }
                        },
                        () => {}
                    );
                } catch (err) {
                    const readerEl = document.getElementById('medic-qr-reader');
                    if (readerEl) {
                        readerEl.innerHTML = `<div class="p-4 text-rose-300 font-bold text-center">ไม่สามารถเปิดกล้องได้ กรุณาอนุญาตสิทธิ์กล้องในเบราว์เซอร์ครับ</div>`;
                    }
                }
            },
            willClose: () => {
                stopMedicQrScanner();
            }
        });
    }

    // 📋 ตัวกรองสถานะรายการติดตามผู้ป่วยของฉัน (Client-side Instant Filter)
    function setMedicTrackingFilter(filterKey) {
        medicCurrentTrackFilter = filterKey;

        document.querySelectorAll('.js-track-filter-btn').forEach(btn => {
            const isAct = btn.getAttribute('data-filter') === filterKey;
            if (isAct) {
                btn.className = 'js-track-filter-btn px-3 py-1 rounded-xl text-xs font-bold bg-slate-800 dark:bg-emerald-600 text-white shadow-sm transition-all';
            } else {
                btn.className = 'js-track-filter-btn px-3 py-1 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 transition-all';
            }
        });

        let visibleCount = 0;
        const cards = document.querySelectorAll('.js-medic-case-card');
        cards.forEach(card => {
            const grp = card.getAttribute('data-track-group');
            const show = (filterKey === 'all' || grp === filterKey);
            card.classList.toggle('hidden', !show);
            if (show) visibleCount++;
        });

        const emptyNotice = document.getElementById('medic-tracking-filter-empty');
        if (emptyNotice) {
            emptyNotice.classList.toggle('hidden', cards.length === 0 || visibleCount > 0);
        }
    }

    // 📋 ก๊อปปี้ข้อความรองรับทั้ง HTTPS และ HTTP วงแลนโรงพยาบาล
    function copyMedicText(text, btnEl) {
        const done = () => {
            if (!btnEl) return;
            const orig = btnEl.innerHTML;
            btnEl.innerHTML = '<i class="fa-solid fa-check mr-1"></i>ก๊อปปี้แล้ว';
            setTimeout(() => { btnEl.innerHTML = orig; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('medic-search-input');
        const resultsEl = document.getElementById('medic-autocomplete-results');
        const clearBtn = document.getElementById('medic-search-clear-btn');

        if (input) {
            if (input.value.trim() !== '') {
                clearBtn?.classList.remove('hidden');
                updateCidValidationHint(input.value);
            }

            input.addEventListener('input', function () {
                const term = this.value.trim();
                clearBtn?.classList.toggle('hidden', term === '');
                updateCidValidationHint(this.value);

                clearTimeout(medicSearchTimer);
                if (term.length < 2) {
                    if (resultsEl) resultsEl.classList.add('hidden');
                    medicCurrentItems = [];
                    medicActiveIndex = -1;
                    return;
                }

                medicSearchTimer = setTimeout(() => fetchMedicAutocomplete(term), 250);
            });

            // รองรับคีย์บอร์ด ↑ ↓ Enter Escape
            input.addEventListener('keydown', function (e) {
                if (!resultsEl || resultsEl.classList.contains('hidden') || !medicCurrentItems.length) {
                    return;
                }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    medicActiveIndex = (medicActiveIndex + 1) % medicCurrentItems.length;
                    updateAutocompleteActiveHighlight();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    medicActiveIndex = (medicActiveIndex - 1 + medicCurrentItems.length) % medicCurrentItems.length;
                    updateAutocompleteActiveHighlight();
                } else if (e.key === 'Enter' && medicActiveIndex >= 0) {
                    e.preventDefault();
                    selectMedicAutocompleteItem(medicActiveIndex);
                } else if (e.key === 'Escape') {
                    resultsEl.classList.add('hidden');
                    medicActiveIndex = -1;
                }
            });
        }

        // ปิด Dropdown Autocomplete เมื่อคลิกพื้นที่ว่าง
        document.addEventListener('click', function (e) {
            const card = document.getElementById('medic-search-card');
            if (resultsEl && card && !card.contains(e.target)) {
                resultsEl.classList.add('hidden');
            }
        });

        // 🔔 Laravel Echo Real-time Refresh (อัปเดตเฉพาะยอดวันนี้และรายการติดตาม ไม่แตะกล่องค้นหา!)
        if (window.Echo) {
            window.Echo.channel('mews-channel')
                .listen('.mews.updated', () => {
                    refreshMedicTrackingOnly();
                });
        }

        function refreshMedicTrackingOnly() {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');

                    const newCount = doc.getElementById('medic-today-count');
                    const curCount = document.getElementById('medic-today-count');
                    if (newCount && curCount) curCount.textContent = newCount.textContent;

                    const newBadge = doc.getElementById('medic-tracking-count-badge');
                    const curBadge = document.getElementById('medic-tracking-count-badge');
                    if (newBadge && curBadge) curBadge.textContent = newBadge.textContent;

                    const newTrack = doc.getElementById('medic-tracking-container');
                    const curTrack = document.getElementById('medic-tracking-container');
                    if (newTrack && curTrack) {
                        curTrack.innerHTML = newTrack.innerHTML;
                        setMedicTrackingFilter(medicCurrentTrackFilter);
                    }
                })
                .catch(() => {});
        }
    });
</script>
@endsection