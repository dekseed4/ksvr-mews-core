@extends('layouts.backend')

@section('title', 'ค้นหาประวัติผู้ป่วย - KSVR Hospital')
@section('page_header', 'ระบบคัดกรองและประเมิน MEWS')

@section('content')
@php
    $nf = session('not_found_detail');
    $hasNotFound = !empty($nf);
@endphp

<div class="max-w-2xl mx-auto mt-2 sm:mt-6 pb-20 px-1 sm:px-0 space-y-4">

    <!-- ส่วนแสดง Alert ทั่วไป -->
    @if(session('error'))
        <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-bold flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('info'))
        <div class="p-3.5 rounded-2xl bg-sky-50 border border-sky-200 text-sky-700 text-sm font-bold flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-info text-base shrink-0"></i>
            <span>{{ session('info') }}</span>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 🔍 1. กล่องค้นหาหลัก (ปรับโหมดกะทัดรัดอัตโนมัติเมื่อมีการแจ้งเตือนไม่พบข้อมูล) -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-visible">
        
        <!-- หัวกล่องสีเข้ม: ซ่อนอัตโนมัติตอนมีผล "ไม่พบข้อมูล" เพื่อไม่ให้กินพื้นที่จอมือถือ -->
        <div id="search_hero_banner" class="{{ $hasNotFound ? 'hidden' : '' }} bg-slate-800 px-5 py-5 sm:p-6 text-center rounded-t-3xl">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-3 shadow-lg rotate-3">
                <i class="fa-solid fa-magnifying-glass-chart"></i>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white">ค้นหาประวัติผู้ป่วย</h2>
            <p class="text-slate-300 text-xs sm:text-sm mt-1">กรอกเลขบัตร ปชช., HN, ชื่อ-สกุล หรือเปิดกล้องสแกนหน้าบัตร</p>
        </div>

        <form id="patient_search_form" action="{{ route('backend.patients.processSearch') }}" method="POST" class="{{ $hasNotFound ? 'p-3.5 sm:p-4' : 'p-5 sm:p-7' }}">
            @csrf
            <input type="hidden" name="scanned_name" id="scanned_name" value="">
            <input type="hidden" name="is_scanned" id="is_scanned" value="0">

            <div class="relative {{ $hasNotFound ? '' : 'mb-4' }}" id="search_container">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 flex items-center">
                        <span class="absolute left-3.5 text-slate-400 pointer-events-none z-20">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </span>

                        <input type="text" name="search_term" id="search_term" required {{ $hasNotFound ? '' : 'autofocus' }} autocomplete="off"
                               value="{{ old('search_term', $term ?? '') }}"
                               placeholder="พิมพ์ชื่อ, สกุล, เลขบัตร หรือ HN..."
                               class="w-full {{ $hasNotFound ? 'text-left text-base py-3 pl-10 pr-20' : 'text-center text-lg py-3.5 pl-10 pr-20' }} rounded-2xl border-2 border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 shadow-sm transition-all outline-none relative z-10 font-bold text-slate-800">

                        <!-- ปุ่มล้างข้อความด่วน (แสดงเมื่อมีตัวอักษรในช่อง) -->
                        <button type="button" id="btn_clear_search" onclick="clearSearchInput()"
                                title="ล้างข้อความ"
                                class="{{ old('search_term', $term ?? '') ? 'flex' : 'hidden' }} absolute right-12 z-20 w-7 h-7 rounded-full text-slate-400 hover:text-rose-500 hover:bg-slate-100 items-center justify-center transition-colors">
                            <i class="fa-solid fa-circle-xmark text-sm"></i>
                        </button>

                        <!-- ปุ่มไอคอนเปิดกล้องด่วนในช่องค้นหา -->
                        <button type="button" onclick="openIdCardScanner()"
                                title="เปิดกล้องสแกนหน้าบัตรประชาชน"
                                class="absolute right-2 z-20 w-9 h-9 rounded-xl bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white border border-emerald-200 flex items-center justify-center transition-all">
                            <i class="fa-solid fa-id-card text-base"></i>
                        </button>
                    </div>

                    <!-- ปุ่มค้นหาขนาดกะทัดรัด (โชว์เฉพาะตอนโหมด Compact ที่มีผลแจ้งเตือนด้านล่าง) -->
                    <button type="submit" id="compact_search_btn"
                            class="{{ $hasNotFound ? 'flex' : 'hidden' }} shrink-0 h-12 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm items-center justify-center gap-1.5 shadow-sm transition-all">
                        <i class="fa-solid fa-search"></i>
                        <span class="hidden sm:inline">ค้นหา</span>
                    </button>
                </div>

                <!-- 🌟 กล่อง Auto-complete -->
                <div id="autocomplete_dropdown" class="absolute left-0 right-0 top-full mt-2 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 hidden overflow-hidden max-h-72 overflow-y-auto divide-y divide-slate-100">
                </div>
            </div>

            <!-- ปุ่มขนาดใหญ่ 2 ปุ่ม (ซ่อนอัตโนมัติตอนโชว์การ์ดไม่พบข้อมูล เพื่อลดความซ้ำซ้อน) -->
            <div id="full_search_actions" class="{{ $hasNotFound ? 'hidden' : 'grid' }} grid-cols-1 sm:grid-cols-2 gap-2.5">
                <button type="submit" class="w-full flex justify-center items-center gap-2 rounded-2xl bg-emerald-600 py-3.5 px-4 text-base font-bold text-white shadow-sm hover:bg-emerald-700 transition-all">
                    <i class="fa-solid fa-search"></i> ค้นหาข้อมูล
                </button>

                <button type="button" onclick="openIdCardScanner()" class="w-full flex justify-center items-center gap-2 rounded-2xl bg-slate-800 py-3.5 px-4 text-base font-bold text-white shadow-sm hover:bg-slate-900 transition-all">
                    <i class="fa-solid fa-camera text-emerald-400"></i> สแกนบัตร ปชช. (กล้องสด)
                </button>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- 🪪 2. การ์ดตรวจสอบผล "ไม่พบข้อมูล" แบบกะทัดรัด จบในหน้าจอเดียว (Zero-Scroll) -->
    <!-- ========================================================================= -->
    @if($hasNotFound)
        @php
            $statusBanner = match($nf['name_source'] ?? null) {
                'hosxp' => [
                    'cls'  => 'bg-indigo-50/90 text-indigo-800 border-indigo-100',
                    'icon' => 'fa-hospital text-indigo-600',
                    'text' => 'พบใน HosXP' . (!empty($nf['hosxp_hn']) ? ' (HN: ' . $nf['hosxp_hn'] . ')' : '') . ' • ยังไม่ได้ลงทะเบียน KSVR',
                ],
                'ksvr_other_scope' => [
                    'cls'  => 'bg-amber-50/90 text-amber-900 border-amber-100',
                    'icon' => 'fa-tent text-amber-600',
                    'text' => $nf['extra_note'] ?? 'พบประวัติใน KSVR แล้ว แต่อยู่ต่างหน่วย/ผลัด',
                ],
                'ocr' => [
                    'cls'  => 'bg-sky-50/90 text-sky-800 border-sky-100',
                    'icon' => 'fa-camera text-sky-600',
                    'text' => 'อ่านชื่อจากหน้าบัตร (OCR) • ยังไม่มีข้อมูลใน KSVR / HosXP',
                ],
                default => [
                    'cls'  => 'bg-slate-100/80 text-slate-600 border-slate-200/70',
                    'icon' => 'fa-circle-info text-slate-500',
                    'text' => 'ยังไม่มีประวัติในระบบ กรุณาตรวจเลขบัตรหรือกดสแกนใหม่',
                ],
            };
        @endphp

        <div id="not_found_card" class="bg-white rounded-3xl border-2 border-rose-200/90 shadow-md overflow-hidden animate-fade-in-up">
            
            <!-- แถบหัวการ์ดแบบบางเฉียบ -->
            <div class="px-4 py-3 bg-rose-50/90 border-b border-rose-100 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-sm text-sm">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm sm:text-base font-black text-rose-800 leading-tight truncate">
                            {{ ($nf['exists_in_ksvr'] ?? false) ? 'อยู่ต่างหน่วยฝึก / ต่างผลัด' : 'ไม่พบประวัติในระบบ KSVR' }}
                        </h3>
                        <p class="text-[11px] font-medium text-rose-600/90 truncate">
                            ตรวจชื่อ-สกุล และเลขบัตรด้านล่างก่อนดำเนินการต่อ
                        </p>
                    </div>
                </div>

                <button type="button" onclick="dismissNotFoundCard()" title="ปิดและค้นหาใหม่"
                        class="w-8 h-8 rounded-full text-rose-400 hover:text-rose-700 hover:bg-rose-100 flex items-center justify-center shrink-0 transition-colors">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <!-- เนื้อหาการ์ด: รวม ชื่อ + เลขบัตร + สถานะ HosXP ไว้ในกล่องเดียว -->
            <div class="p-4 space-y-3.5">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 overflow-hidden">
                    <div class="px-4 py-3.5 space-y-1.5">
                        <!-- บรรทัด 1: ชื่อ - นามสกุล (เด่นสุด อ่านง่ายทันที) -->
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-user text-indigo-500 text-sm shrink-0"></i>
                            <div class="font-black text-base sm:text-lg leading-snug {{ !empty($nf['full_name']) ? 'text-slate-900' : 'text-slate-400 italic text-sm font-semibold' }}">
                                {{ !empty($nf['full_name']) ? $nf['full_name'] : 'ไม่พบชื่อ-สกุล (อ่านจากหน้าบัตรไม่ชัด)' }}
                            </div>
                        </div>

                        <!-- บรรทัด 2: เลขบัตร ปชช. + ป้ายยืนยัน Mod-11 -->
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-0.5">
                            <div class="flex items-center gap-2">
                                <i class="fa-regular fa-id-card text-emerald-600 text-sm shrink-0"></i>
                                <span class="font-mono font-black text-base sm:text-lg text-slate-800 tracking-wide">
                                    {{ $nf['formatted_cid'] ?? $nf['searched_term'] }}
                                </span>
                            </div>

                            @if(!empty($nf['cid']))
                                @if($nf['is_cid_valid'])
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-emerald-100 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                                        <i class="fa-solid fa-check text-[10px]"></i> 13 หลักถูกต้อง
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-rose-100 text-rose-700 border border-rose-200 inline-flex items-center gap-1">
                                        <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> เลขไม่ถูกหลัก
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>

                    <!-- บรรทัด 3: แถบสถานะรวม (HosXP / ต่างหน่วย / OCR) บรรทัดเดียวจบ -->
                    <div class="px-4 py-2 border-t text-xs font-bold flex items-center gap-2 {{ $statusBanner['cls'] }}">
                        <i class="fa-solid {{ $statusBanner['icon'] }} shrink-0"></i>
                        <span class="leading-snug">{{ $statusBanner['text'] }}</span>
                    </div>
                </div>

                <!-- ปุ่มดำเนินการแบบเต็มนิ้วโป้ง 2 คอลัมน์สมดุล -->
                <div class="grid {{ empty($nf['exists_in_ksvr']) ? 'grid-cols-2' : 'grid-cols-1' }} gap-2.5">
                    <button type="button" onclick="openIdCardScanner()"
                            class="w-full py-3 px-3 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white text-xs sm:text-sm font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all">
                        <i class="fa-solid fa-camera text-emerald-400"></i>
                        <span>สแกนใหม่</span>
                    </button>

                    @if(empty($nf['exists_in_ksvr']))
                        <a href="{{ route('backend.patients.create', array_filter(['cid' => $nf['cid'] ?? null])) }}"
                           class="w-full py-3 px-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all">
                            <i class="fa-solid fa-user-plus"></i>
                            <span>เพิ่มประวัติใหม่</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- 🌟 โชว์ผลลัพธ์กรณีเจอหลายคน -->
    @if(isset($results) && $results->count() > 0)
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-5 animate-fade-in-up">
            <h3 class="text-base sm:text-lg font-bold text-slate-800 mb-3 border-b pb-2">
                <i class="fa-solid fa-list-check text-emerald-500 mr-2"></i> พบข้อมูล {{ $results->count() }} รายการ
            </h3>
            
            <div class="space-y-2.5">
                @foreach($results as $patient)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 bg-slate-50 rounded-2xl border border-slate-100 hover:bg-emerald-50 transition-colors">
                        <div>
                            <div class="font-bold text-slate-800 text-base">{{ $patient->rank }} {{ $patient->first_name }} {{ $patient->last_name }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                <i class="fa-regular fa-id-card mr-1"></i> CID: {{ $patient->cid }} 
                                @if($patient->hn) | <i class="fa-solid fa-file-medical mr-1"></i> HN: {{ $patient->hn }} @endif
                            </div>
                            <div class="text-xs text-emerald-600 font-bold mt-0.5">
                                <i class="fa-solid fa-tent mr-1"></i> สังกัด: {{ $patient->militaryUnit->short_name ?? $patient->militaryUnit->name ?? 'ไม่ระบุ' }}
                            </div>
                        </div>
                        <a href="{{ route('backend.mews.create', $patient->id) }}" class="px-4 py-2.5 bg-slate-800 hover:bg-emerald-600 text-white rounded-xl font-bold transition-colors shadow-sm text-xs sm:text-sm flex items-center justify-center gap-2 shrink-0">
                            <i class="fa-solid fa-stethoscope"></i> ประเมิน MEWS
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>

<!-- ========================================================================= -->
<!-- 📸 Modal กล้องสแกนบัตรประชาชนแบบ Real-time (อ่านเลข 13 หลัก + ชื่อ-สกุลไทย) -->
<!-- ========================================================================= -->
<div id="idCardScannerModal" class="hidden fixed inset-0 z-[90] bg-slate-950/90 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
    <div class="bg-slate-900 text-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-700 flex flex-col">
        
        <div class="px-5 py-3.5 bg-slate-800/90 border-b border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-expand"></i>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-black text-white leading-tight">สแกนหน้าบัตรประชาชน (Real-time)</h3>
                    <p class="text-[11px] text-slate-400">วางเลข 13 หลักและแถวชื่อ-สกุลให้อยู่ในกรอบสีเขียว</p>
                </div>
            </div>
            <button type="button" onclick="closeIdCardScanner()" class="w-9 h-9 rounded-full bg-slate-700 hover:bg-rose-600 text-slate-300 hover:text-white flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="relative w-full aspect-[4/3] bg-black overflow-hidden">
            <video id="id_scanner_video" autoplay playsinline muted class="w-full h-full object-cover"></video>

            <div class="absolute inset-0 pointer-events-none flex flex-col items-center justify-center px-5">
                <div class="w-full flex-1 bg-black/50"></div>

                <div class="w-full flex items-center">
                    <div class="w-1 sm:w-3 h-32 bg-black/50"></div>
                    <div id="scanner_target_box" class="relative flex-1 h-32 border-2 border-emerald-400 rounded-2xl shadow-[0_0_20px_rgba(16,185,129,0.4)] overflow-hidden flex flex-col">
                        <span class="absolute top-0 left-0 w-4 h-4 border-t-4 border-l-4 border-emerald-400 rounded-tl-xl"></span>
                        <span class="absolute top-0 right-0 w-4 h-4 border-t-4 border-r-4 border-emerald-400 rounded-tr-xl"></span>
                        <span class="absolute bottom-0 left-0 w-4 h-4 border-b-4 border-l-4 border-emerald-400 rounded-bl-xl"></span>
                        <span class="absolute bottom-0 right-0 w-4 h-4 border-b-4 border-r-4 border-emerald-400 rounded-br-xl"></span>

                        <div class="flex-1 border-b border-dashed border-emerald-400/60 flex items-center justify-center relative">
                            <div class="absolute left-3 right-3 h-0.5 bg-emerald-400/80 shadow-[0_0_8px_#34d399] animate-pulse"></div>
                            <span class="text-[10px] font-bold text-emerald-200 bg-black/40 px-2 py-0.5 rounded-full">
                                1. วางแถวเลข 13 หลักตรงนี้
                            </span>
                        </div>

                        <div class="flex-1 flex items-center justify-center">
                            <span class="text-[10px] font-bold text-sky-200 bg-black/40 px-2 py-0.5 rounded-full">
                                2. แถวชื่อ-สกุล ภาษาไทย
                            </span>
                        </div>
                    </div>
                    <div class="w-1 sm:w-3 h-32 bg-black/50"></div>
                </div>

                <div class="w-full flex-1 bg-black/50 flex items-start justify-center pt-3">
                    <span id="scanner_status_badge" class="px-3.5 py-1 rounded-full bg-slate-900/85 border border-slate-600 text-xs font-bold text-emerald-300 flex items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <span>กำลังเตรียมระบบสแกน OCR...</span>
                    </span>
                </div>
            </div>

            <canvas id="id_scanner_canvas" class="hidden"></canvas>
            <canvas id="id_name_canvas" class="hidden"></canvas>
        </div>

        <div class="p-4 bg-slate-800/80 border-t border-slate-700 flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex items-center gap-2">
                <button type="button" id="btn_toggle_torch" onclick="toggleScannerTorch()"
                        class="hidden px-3.5 py-2 rounded-xl bg-slate-700 hover:bg-amber-500 text-slate-200 hover:text-slate-950 text-xs font-bold flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-bolt"></i>
                    <span id="torch_btn_label">เปิดไฟฉาย</span>
                </button>

                <label class="px-3.5 py-2 rounded-xl bg-slate-700 hover:bg-indigo-600 text-slate-200 hover:text-white text-xs font-bold flex items-center gap-1.5 cursor-pointer transition-colors">
                    <i class="fa-solid fa-image"></i>
                    <span>สแกนจากรูปภาพ</span>
                    <input type="file" id="id_card_file_input" accept="image/*" capture="environment" class="hidden" onchange="handleStaticIdCardImage(this)">
                </label>
            </div>

            <button type="button" onclick="closeIdCardScanner()" class="px-4 py-2 rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-bold transition-colors">
                ปิดกล้อง
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@php
    $rawRoles = auth()->user()->roles ?? auth()->user()->role ?? [];
    $userRoles = is_string($rawRoles) ? json_decode($rawRoles, true) : (array)$rawRoles;
    $isClinical = in_array('admin', $userRoles)
        || in_array('er_nurse', $userRoles)
        || in_array('opd_nurse', $userRoles)
        || in_array('medic', $userRoles)
        || in_array('medical_staff', $userRoles);

    $targetBaseUrl = $isClinical 
        ? route('backend.mews.create', ['patient_id' => '__ID__']) 
        : route('backend.patients.show', '__ID__');
@endphp

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_term');
    const clearBtn = document.getElementById('btn_clear_search');
    const dropdown = document.getElementById('autocomplete_dropdown');
    let timeout = null;

    const autocompleteUrl = "{{ route('backend.patients.autocomplete') }}";
    const targetUrlTemplate = "{{ $targetBaseUrl }}";

    searchInput.addEventListener('input', function() {
        document.getElementById('scanned_name').value = '';
        document.getElementById('is_scanned').value = '0';

        const term = this.value.trim();
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', term.length === 0);
            clearBtn.classList.toggle('flex', term.length > 0);
        }

        clearTimeout(timeout);
        timeout = setTimeout(() => window.runPatientAutocomplete(term), 300);
    });

    window.runPatientAutocomplete = function(term) {
        if (term.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        fetch(`${autocompleteUrl}?term=${encodeURIComponent(term)}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                dropdown.innerHTML = ''; 
                
                if (data.length > 0) {
                    data.forEach(patient => {
                        const item = document.createElement('a');
                        const profileUrl = "{{ route('backend.patients.show', '__ID__') }}".replace('__ID__', patient.id);
                        item.href = patient.is_locked ? profileUrl : targetUrlTemplate.replace('__ID__', patient.id);
                        item.className = 'block px-4 py-3 hover:bg-emerald-50 transition-colors cursor-pointer text-left';
                        const lockBadge = patient.is_locked 
                            ? `<span class="ml-1.5 px-2 py-0.5 bg-rose-100 text-rose-700 border border-rose-200 rounded text-[11px] font-bold"><i class="fa-solid fa-ban mr-1"></i>${patient.status_label}</span>` 
                            : '';
                        item.innerHTML = `
                            <div class="font-bold text-slate-800 text-sm sm:text-base">${patient.rank}${patient.first_name} ${patient.last_name} ${lockBadge}</div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                <i class="fa-regular fa-id-card mr-1"></i> ${patient.cid} | <i class="fa-solid fa-tent mr-1"></i> ${patient.company || '-'}
                            </div>
                        `;
                        dropdown.appendChild(item);
                    });
                } else {
                    dropdown.innerHTML = `
                        <div class="px-4 py-4 text-sm text-slate-500 text-center">
                            <i class="fa-solid fa-folder-open text-slate-300 text-xl mb-1.5 block"></i>
                            ไม่พบข้อมูลทหารรายนี้ในหน่วยและผลัดที่คุณรับผิดชอบ
                        </div>
                    `;
                }
                dropdown.classList.remove('hidden');
            })
            .catch(error => console.error('เกิดข้อผิดพลาดในการดึงข้อมูล:', error));
    };

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});

// ล้างข้อความในช่องค้นหาทันที
function clearSearchInput() {
    const input = document.getElementById('search_term');
    const clearBtn = document.getElementById('btn_clear_search');
    const dropdown = document.getElementById('autocomplete_dropdown');
    if (input) {
        input.value = '';
        input.focus();
    }
    if (clearBtn) {
        clearBtn.classList.add('hidden');
        clearBtn.classList.remove('flex');
    }
    if (dropdown) dropdown.classList.add('hidden');
    document.getElementById('scanned_name').value = '';
    document.getElementById('is_scanned').value = '0';
}

// ปิดการ์ดไม่พบข้อมูล แล้วคืนหน้าค้นหาแบบเต็มให้ทันที
function dismissNotFoundCard() {
    document.getElementById('not_found_card')?.remove();
    document.getElementById('search_hero_banner')?.classList.remove('hidden');
    document.getElementById('compact_search_btn')?.classList.add('hidden');
    document.getElementById('compact_search_btn')?.classList.remove('flex');

    const fullActions = document.getElementById('full_search_actions');
    if (fullActions) {
        fullActions.classList.remove('hidden');
        fullActions.classList.add('grid');
    }

    const form = document.getElementById('patient_search_form');
    if (form) {
        form.classList.remove('p-3.5', 'sm:p-4');
        form.classList.add('p-5', 'sm:p-7');
    }
    document.getElementById('search_container')?.classList.add('mb-4');
    clearSearchInput();
}

// =========================================================================
// 📸 ระบบสแกนหน้าบัตร ปชช. Real-time (เลข 13 หลัก + อ่านชื่อ-สกุลภาษาไทย)
// =========================================================================
let scannerStream = null;
let scannerTimer = null;
let ocrWorker = null;
let thaiWorker = null;
let isScanningFrame = false;
let torchEnabled = false;

function isValidThaiCid(cid) {
    if (!/^[1-8]\d{12}$/.test(cid)) return false;
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(cid.charAt(i), 10) * (13 - i);
    }
    const checkDigit = (11 - (sum % 11)) % 10;
    return checkDigit === parseInt(cid.charAt(12), 10);
}

function extractValidThaiCidFromText(rawText) {
    if (!rawText) return null;
    const lines = rawText.split(/\r?\n/);
    for (const line of lines) {
        const digits = line.replace(/\D/g, '');
        if (digits.length >= 13) {
            for (let i = 0; i <= digits.length - 13; i++) {
                const candidate = digits.substring(i, i + 13);
                if (isValidThaiCid(candidate)) return candidate;
            }
        }
    }
    const allDigits = rawText.replace(/\D/g, '');
    if (allDigits.length >= 13) {
        for (let i = 0; i <= allDigits.length - 13; i++) {
            const candidate = allDigits.substring(i, i + 13);
            if (isValidThaiCid(candidate)) return candidate;
        }
    }
    return null;
}

function extractThaiNameFromText(rawText) {
    if (!rawText) return '';
    const cleaned = rawText
        .replace(/ชื่อตัวและชื่อสกุ[ลน]/g, ' ')
        .replace(/ชื่อตัวและชื่อสกล/g, ' ')
        .replace(/[^\u0E00-\u0E7F\s\.]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const prefixMatch = cleaned.match(/(นาย|นางสาว|นาง|น\.ส\.|พลฯ|พลทหาร|จ\.ส\.อ\.|ส\.อ\.|ส\.ท\.|ส\.ต\.)\s*[\u0E00-\u0E7F]{2,}\s+[\u0E00-\u0E7F]{2,}/);
    if (prefixMatch) {
        return prefixMatch[0].replace(/\s+/g, ' ').trim();
    }

    const words = cleaned.split(' ').filter(w => w.length >= 2 && !/^(ชื่อ|ตัว|และ|สกุล|บัตร|ประจำตัว|ประชาชน)$/.test(w));
    if (words.length >= 2) {
        return words.slice(0, 3).join(' ');
    }
    return '';
}

function playScanSuccessFeedback() {
    if (navigator.vibrate) navigator.vibrate([80, 40, 80]);
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1046.5, ctx.currentTime);
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.18);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.18);
    } catch (e) {}
}

function setScannerStatus(htmlText) {
    const el = document.getElementById('scanner_status_badge');
    if (el) el.innerHTML = htmlText;
}

async function getOcrWorker() {
    if (ocrWorker) return ocrWorker;
    ocrWorker = await Tesseract.createWorker('eng');
    await ocrWorker.setParameters({
        tessedit_char_whitelist: '0123456789 -',
        tessedit_pageseg_mode: '6',
    });
    return ocrWorker;
}

async function getThaiWorker() {
    if (thaiWorker) return thaiWorker;
    thaiWorker = await Tesseract.createWorker('tha');
    await thaiWorker.setParameters({
        tessedit_pageseg_mode: '6',
    });
    return thaiWorker;
}

async function readThaiNameWithTimeout(imageSource, timeoutMs = 5000) {
    try {
        const ocrPromise = (async () => {
            const worker = await getThaiWorker();
            const { data: { text } } = await worker.recognize(imageSource);
            return extractThaiNameFromText(text);
        })();

        const timeoutPromise = new Promise(resolve => setTimeout(() => resolve(''), timeoutMs));
        return await Promise.race([ocrPromise, timeoutPromise]);
    } catch (e) {
        return '';
    }
}

function preprocessCanvasForOcr(ctx, width, height) {
    const imgData = ctx.getImageData(0, 0, width, height);
    const data = imgData.data;
    const contrast = 1.65;
    const intercept = 128 * (1 - contrast);

    for (let i = 0; i < data.length; i += 4) {
        let gray = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
        gray = gray * contrast + intercept;
        gray = Math.max(0, Math.min(255, gray));
        data[i] = data[i + 1] = data[i + 2] = gray;
    }
    ctx.putImageData(imgData, 0, 0);
}

async function openIdCardScanner() {
    const modal = document.getElementById('idCardScannerModal');
    const video = document.getElementById('id_scanner_video');
    const torchBtn = document.getElementById('btn_toggle_torch');

    modal.classList.remove('hidden');
    setScannerStatus('<i class="fa-solid fa-spinner fa-spin"></i> <span>กำลังเปิดกล้องและเตรียมตัวอ่านเลขบัตร...</span>');

    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('เบราว์เซอร์ไม่รองรับการเปิดกล้องสด กรุณาใช้ปุ่ม "สแกนจากรูปภาพ" ด้านล่างแทนครับ');
        }

        scannerStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        });

        video.srcObject = scannerStream;
        await video.play();

        const track = scannerStream.getVideoTracks()[0];
        const caps = track && typeof track.getCapabilities === 'function' ? track.getCapabilities() : {};
        if (caps.torch && torchBtn) {
            torchBtn.classList.remove('hidden');
            torchEnabled = false;
            document.getElementById('torch_btn_label').innerText = 'เปิดไฟฉาย';
        } else if (torchBtn) {
            torchBtn.classList.add('hidden');
        }

        await getOcrWorker();
        setScannerStatus('<span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> <span>กำลังสแกนอัตโนมัติ... ถือบัตรให้นิ่ง</span>');

        scannerTimer = setInterval(scanCurrentVideoFrame, 900);

    } catch (err) {
        console.error('Camera Error:', err);
        setScannerStatus('<i class="fa-solid fa-triangle-exclamation text-amber-400"></i> <span>เปิดกล้องสดไม่ได้ กรุณาแตะ "สแกนจากรูปภาพ"</span>');
        Swal.fire({
            icon: 'warning',
            title: 'ไม่สามารถเปิดกล้องสดได้',
            text: err.message || 'กรุณาอนุญาตสิทธิ์การใช้กล้องบนเบราว์เซอร์ หรือใช้ปุ่ม "สแกนจากรูปภาพ" แทนครับ',
            confirmButtonColor: '#059669',
            customClass: { popup: 'rounded-3xl' }
        });
    }
}

async function scanCurrentVideoFrame() {
    if (isScanningFrame || !scannerStream) return;
    const video = document.getElementById('id_scanner_video');
    const cidCanvas = document.getElementById('id_scanner_canvas');
    const nameCanvas = document.getElementById('id_name_canvas');
    if (!video || video.readyState !== 4 || video.videoWidth === 0) return;

    isScanningFrame = true;
    try {
        const cropW = Math.floor(video.videoWidth * 0.88);
        const cidH  = Math.floor(video.videoHeight * 0.24);
        const cropX = Math.floor((video.videoWidth - cropW) / 2);
        const cidY  = Math.floor(video.videoHeight * 0.28);

        cidCanvas.width = cropW;
        cidCanvas.height = cidH;
        const ctx = cidCanvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(video, cropX, cidY, cropW, cidH, 0, 0, cropW, cidH);
        preprocessCanvasForOcr(ctx, cropW, cidH);

        const worker = await getOcrWorker();
        const { data: { text } } = await worker.recognize(cidCanvas);
        const validCid = extractValidThaiCidFromText(text);

        if (validCid) {
            if (scannerTimer) {
                clearInterval(scannerTimer);
                scannerTimer = null;
            }
            playScanSuccessFeedback();

            const nameH = Math.floor(video.videoHeight * 0.24);
            const nameY = Math.floor(video.videoHeight * 0.48);
            nameCanvas.width = cropW;
            nameCanvas.height = nameH;
            const nCtx = nameCanvas.getContext('2d', { willReadFrequently: true });
            nCtx.drawImage(video, cropX, nameY, cropW, nameH, 0, 0, cropW, nameH);
            preprocessCanvasForOcr(nCtx, cropW, nameH);

            const formatted = validCid.replace(/^(\d)(\d{4})(\d{5})(\d{2})(\d)$/, '$1-$2-$3-$4-$5');
            setScannerStatus(`<i class="fa-solid fa-spinner fa-spin"></i> <span>พบเลข ${formatted} กำลังอ่านชื่อ-สกุล...</span>`);

            const scannedThaiName = await readThaiNameWithTimeout(nameCanvas, 5000);
            onCidScanSuccess(validCid, scannedThaiName);
        }
    } catch (e) {
        console.warn('Frame OCR skip:', e);
    } finally {
        isScanningFrame = false;
    }
}

async function handleStaticIdCardImage(inputEl) {
    const file = inputEl.files && inputEl.files[0];
    if (!file) return;

    if (scannerTimer) {
        clearInterval(scannerTimer);
        scannerTimer = null;
    }
    setScannerStatus('<i class="fa-solid fa-spinner fa-spin"></i> <span>กำลังอ่านเลขบัตรและชื่อ-สกุลจากภาพ...</span>');

    try {
        const worker = await getOcrWorker();
        await worker.setParameters({ tessedit_pageseg_mode: '11' });
        const { data: { text } } = await worker.recognize(file);
        await worker.setParameters({ tessedit_pageseg_mode: '6' });

        const validCid = extractValidThaiCidFromText(text);
        if (validCid) {
            playScanSuccessFeedback();
            const scannedThaiName = await readThaiNameWithTimeout(file, 6000);
            onCidScanSuccess(validCid, scannedThaiName);
        } else {
            setScannerStatus('<i class="fa-solid fa-circle-xmark text-rose-400"></i> <span>ไม่พบเลข 13 หลักที่ชัดเจน ลองถ่ายใหม่ให้ชัดขึ้นครับ</span>');
            if (scannerStream) {
                scannerTimer = setInterval(scanCurrentVideoFrame, 900);
            }
        }
    } catch (e) {
        setScannerStatus('<i class="fa-solid fa-triangle-exclamation text-rose-400"></i> <span>อ่านภาพไม่สำเร็จ กรุณาลองใหม่</span>');
    } finally {
        inputEl.value = '';
    }
}

function onCidScanSuccess(cid, scannedName = '') {
    closeIdCardScanner();

    const searchInput = document.getElementById('search_term');
    const nameInput   = document.getElementById('scanned_name');
    const flagInput   = document.getElementById('is_scanned');
    const searchForm  = document.getElementById('patient_search_form');

    if (searchInput) searchInput.value = cid;
    if (nameInput)   nameInput.value   = scannedName || '';
    if (flagInput)   flagInput.value   = '1';

    const formattedCid = cid.replace(/^(\d)(\d{4})(\d{5})(\d{2})(\d)$/, '$1-$2-$3-$4-$5');
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: `สแกนสำเร็จ: ${formattedCid}`,
        text: scannedName ? `ชื่อบนบัตร: ${scannedName} — กำลังค้นหา...` : 'กำลังค้นหาประวัติผู้ป่วย...',
        showConfirmButton: false,
        timer: 2000
    });

    if (searchForm) {
        setTimeout(() => searchForm.submit(), 300);
    }
}

async function toggleScannerTorch() {
    if (!scannerStream) return;
    const track = scannerStream.getVideoTracks()[0];
    if (!track) return;

    try {
        torchEnabled = !torchEnabled;
        await track.applyConstraints({ advanced: [{ torch: torchEnabled }] });
        const lbl = document.getElementById('torch_btn_label');
        const btn = document.getElementById('btn_toggle_torch');
        if (lbl) lbl.innerText = torchEnabled ? 'ปิดไฟฉาย' : 'เปิดไฟฉาย';
        if (btn) {
            btn.classList.toggle('bg-amber-400', torchEnabled);
            btn.classList.toggle('text-slate-950', torchEnabled);
            btn.classList.toggle('bg-slate-700', !torchEnabled);
            btn.classList.toggle('text-slate-200', !torchEnabled);
        }
    } catch (e) {
        console.warn('Torch not supported:', e);
    }
}

function closeIdCardScanner() {
    if (scannerTimer) {
        clearInterval(scannerTimer);
        scannerTimer = null;
    }
    if (scannerStream) {
        scannerStream.getTracks().forEach(track => track.stop());
        scannerStream = null;
    }
    isScanningFrame = false;
    torchEnabled = false;
    document.getElementById('idCardScannerModal')?.classList.add('hidden');
}

window.addEventListener('pagehide', closeIdCardScanner);
</script>
@endsection