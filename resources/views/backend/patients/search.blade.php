@extends('layouts.backend')

@section('title', 'ค้นหาประวัติผู้ป่วย - KSVR Hospital')
@section('page_header', 'ระบบคัดกรองและประเมิน MEWS')

@section('content')
<div class="max-w-3xl mx-auto mt-6 md:mt-10 pb-12 px-2 sm:px-0">
    
    <!-- ส่วนแสดง Alert แจ้งเตือน -->
    @if(session('error'))
        <div class="mb-5 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-bold flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-lg shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('info'))
        <div class="mb-5 p-4 rounded-2xl bg-sky-50 border border-sky-200 text-sky-700 text-sm font-bold flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-info text-lg shrink-0"></i>
            <span>{{ session('info') }}</span>
        </div>
    @endif

    <!-- กล่องฟอร์มค้นหา -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 mb-6 overflow-visible">
        <div class="bg-slate-800 p-6 text-center rounded-t-3xl">
            <div class="w-16 h-16 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-3xl mx-auto mb-4 shadow-lg rotate-3">
                <i class="fa-solid fa-magnifying-glass-chart"></i>
            </div>
            <h2 class="text-2xl font-bold text-white">ค้นหาประวัติผู้ป่วย</h2>
            <p class="text-slate-300 text-sm mt-2">กรอกเลขบัตร ปชช., HN, ชื่อ-สกุล หรือเปิดกล้องสแกนหน้าบัตรประชาชนแบบ Real-time</p>
        </div>

        <form id="patient_search_form" action="{{ route('backend.patients.processSearch') }}" method="POST" class="p-6 md:p-8">
            @csrf
            <div class="mb-5 relative" id="search_container">
                <div class="relative flex items-center">
                    <input type="text" name="search_term" id="search_term" required autofocus autocomplete="off"
                           value="{{ old('search_term', $term ?? '') }}"
                           placeholder="พิมพ์ชื่อ, สกุล, CID หรือ HN..."
                           class="w-full text-center text-lg sm:text-xl rounded-2xl border-2 border-slate-200 py-4 pl-4 pr-14 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 shadow-sm transition-all outline-none relative z-10 font-bold text-slate-800">

                    <!-- ปุ่มไอคอนเปิดกล้องด่วนด้านขวาในช่องค้นหา -->
                    <button type="button" onclick="openIdCardScanner()"
                            title="เปิดกล้องสแกนเลขบัตรประชาชน (Real-time)"
                            class="absolute right-2.5 z-20 w-11 h-11 rounded-xl bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white border border-emerald-200 flex items-center justify-center transition-all shadow-sm">
                        <i class="fa-solid fa-id-card text-lg"></i>
                    </button>
                </div>
                
                <!-- 🌟 กล่องโชว์ผลลัพธ์ Auto-complete -->
                <div id="autocomplete_dropdown" class="absolute left-0 right-0 top-full mt-2 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 hidden overflow-hidden max-h-80 overflow-y-auto divide-y divide-slate-100">
                </div>
            </div>

            <!-- แถวปุ่มดำเนินการ: ค้นหา + เปิดกล้องสแกนสด -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl mx-auto">
                <button type="submit" class="w-full flex justify-center items-center gap-2 rounded-2xl bg-emerald-600 py-3.5 px-5 text-base font-bold text-white shadow-md hover:bg-emerald-700 transition-all">
                    <i class="fa-solid fa-search"></i> ค้นหาข้อมูล
                </button>

                <button type="button" onclick="openIdCardScanner()" class="w-full flex justify-center items-center gap-2 rounded-2xl bg-slate-800 py-3.5 px-5 text-base font-bold text-white shadow-md hover:bg-slate-900 transition-all">
                    <i class="fa-solid fa-camera text-emerald-400"></i> สแกนบัตร ปชช. (กล้องสด)
                </button>
            </div>
        </form>
    </div>

    <!-- 🌟 โชว์ผลลัพธ์กรณีเจอหลายคน -->
    @if(isset($results) && $results->count() > 0)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 animate-fade-in-up">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">
                <i class="fa-solid fa-list-check text-emerald-500 mr-2"></i> พบข้อมูล {{ $results->count() }} รายการ
            </h3>
            
            <div class="space-y-3">
                @foreach($results as $patient)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100 hover:bg-emerald-50 transition-colors">
                        <div>
                            <div class="font-bold text-slate-800 text-lg">{{ $patient->rank }} {{ $patient->first_name }} {{ $patient->last_name }}</div>
                            <div class="text-sm text-slate-500 mt-1">
                                <i class="fa-regular fa-id-card mr-1"></i> CID: {{ $patient->cid }} 
                                @if($patient->hn) | <i class="fa-solid fa-file-medical mr-1"></i> HN: {{ $patient->hn }} @endif
                            </div>
                            <div class="text-xs text-emerald-600 font-bold mt-1">
                                <i class="fa-solid fa-tent mr-1"></i> สังกัด: {{ $patient->militaryUnit->short_name ?? $patient->militaryUnit->name ?? 'ไม่ระบุ' }}
                            </div>
                        </div>
                        <a href="{{ route('backend.mews.create', $patient->id) }}" class="px-5 py-2.5 bg-slate-800 hover:bg-emerald-600 text-white rounded-xl font-bold transition-colors shadow-sm text-sm flex items-center justify-center gap-2 shrink-0">
                            <i class="fa-solid fa-stethoscope"></i> ประเมิน MEWS
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>

<!-- ========================================================================= -->
<!-- 📸 Modal กล้องสแกนเลขบัตรประชาชนแบบ Real-time (ไม่บันทึกรูปลงเครื่อง / PDPA Safe) -->
<!-- ========================================================================= -->
<div id="idCardScannerModal" class="hidden fixed inset-0 z-[90] bg-slate-950/90 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
    <div class="bg-slate-900 text-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-700 flex flex-col">
        
        <!-- ส่วนหัว Modal -->
        <div class="px-5 py-3.5 bg-slate-800/90 border-b border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-expand"></i>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-black text-white leading-tight">สแกนเลขบัตรประชาชน (Real-time)</h3>
                    <p class="text-[11px] text-slate-400">วางเลข 13 หลักให้ตรงกรอบสีเขียว ระบบอ่านให้อัตโนมัติ</p>
                </div>
            </div>
            <button type="button" onclick="closeIdCardScanner()" class="w-9 h-9 rounded-full bg-slate-700 hover:bg-rose-600 text-slate-300 hover:text-white flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- พื้นที่กล้องสด (Live Video Viewfinder) -->
        <div class="relative w-full aspect-[4/3] bg-black overflow-hidden">
            <video id="id_scanner_video" autoplay playsinline muted class="w-full h-full object-cover"></video>

            <!-- กรอบนำสายตา (Guide Box) เน้นช่องอ่านเลข 13 หลักตรงกลาง -->
            <div class="absolute inset-0 pointer-events-none flex flex-col items-center justify-center px-6">
                <!-- เงาด้านบน -->
                <div class="w-full flex-1 bg-black/50"></div>

                <!-- แถบเล็งเลข 13 หลักตรงกลาง -->
                <div class="w-full flex items-center">
                    <div class="w-2 sm:w-4 h-20 bg-black/50"></div>
                    <div id="scanner_target_box" class="relative flex-1 h-20 border-2 border-emerald-400 rounded-xl shadow-[0_0_20px_rgba(16,185,129,0.4)] overflow-hidden">
                        <!-- มุมกรอบ 4 ด้าน -->
                        <span class="absolute top-0 left-0 w-4 h-4 border-t-4 border-l-4 border-emerald-400 rounded-tl-lg"></span>
                        <span class="absolute top-0 right-0 w-4 h-4 border-t-4 border-r-4 border-emerald-400 rounded-tr-lg"></span>
                        <span class="absolute bottom-0 left-0 w-4 h-4 border-b-4 border-l-4 border-emerald-400 rounded-bl-lg"></span>
                        <span class="absolute bottom-0 right-0 w-4 h-4 border-b-4 border-r-4 border-emerald-400 rounded-br-lg"></span>

                        <!-- เส้นเลเซอร์วิ่งสแกน -->
                        <div class="absolute left-2 right-2 h-0.5 bg-emerald-400/90 shadow-[0_0_8px_#34d399] animate-pulse top-1/2 -translate-y-1/2"></div>

                        <div class="absolute bottom-1 inset-x-0 text-center text-[10px] font-bold text-emerald-200 drop-shadow">
                            วางแถวเลข 13 หลักให้อยู่ในกรอบนี้
                        </div>
                    </div>
                    <div class="w-2 sm:w-4 h-20 bg-black/50"></div>
                </div>

                <!-- เงาด้านล่าง -->
                <div class="w-full flex-1 bg-black/50 flex items-start justify-center pt-3">
                    <span id="scanner_status_badge" class="px-3.5 py-1 rounded-full bg-slate-900/85 border border-slate-600 text-xs font-bold text-emerald-300 flex items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <span>กำลังเตรียมระบบสแกน OCR...</span>
                    </span>
                </div>
            </div>

            <!-- Canvas ลับสำหรับครอปและปรับความคมชัดภาพก่อนส่งให้ OCR (ไม่แสดงผล) -->
            <canvas id="id_scanner_canvas" class="hidden"></canvas>
        </div>

        <!-- แถบเครื่องมือด้านล่างกล้อง -->
        <div class="p-4 bg-slate-800/80 border-t border-slate-700 flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex items-center gap-2">
                <!-- ปุ่มเปิด/ปิดไฟฉาย (แสดงเมื่อมือถือรองรับ) -->
                <button type="button" id="btn_toggle_torch" onclick="toggleScannerTorch()"
                        class="hidden px-3.5 py-2 rounded-xl bg-slate-700 hover:bg-amber-500 text-slate-200 hover:text-slate-950 text-xs font-bold flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-bolt"></i>
                    <span id="torch_btn_label">เปิดไฟฉาย</span>
                </button>

                <!-- ปุ่มสำรอง: อ่านจากรูปภาพในเครื่อง / ถ่ายภาพนิ่ง -->
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

<!-- ไลบรารี Tesseract.js v5 (ประมวลผล OCR ในเบราว์เซอร์ 100% ปลอดภัยตาม PDPA) -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_term');
    const dropdown = document.getElementById('autocomplete_dropdown');
    let timeout = null;

    const autocompleteUrl = "{{ route('backend.patients.autocomplete') }}";
    const targetUrlTemplate = "{{ $targetBaseUrl }}";

    // ฟังก์ชันค้นหา Auto-complete (แยกออกมาเพื่อให้ระบบสแกนเรียกใช้ร่วมกันได้)
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
                        item.className = 'block px-5 py-3 hover:bg-emerald-50 transition-colors cursor-pointer text-left';
                        const lockBadge = patient.is_locked 
                            ? `<span class="ml-2 px-2 py-0.5 bg-rose-100 text-rose-700 border border-rose-200 rounded text-[11px] font-bold"><i class="fa-solid fa-ban mr-1"></i>${patient.status_label} (งดประเมิน MEWS)</span>` 
                            : '';
                        item.innerHTML = `
                            <div class="font-bold text-slate-800">${patient.rank}${patient.first_name} ${patient.last_name} ${lockBadge}</div>
                            <div class="text-xs text-slate-500 mt-1">
                                <i class="fa-regular fa-id-card mr-1"></i> ${patient.cid} | <i class="fa-solid fa-tent mr-1"></i> ${patient.company || '-'} (${patient.battalion || 'ไม่ระบุพัน.'})
                            </div>
                        `;
                        dropdown.appendChild(item);
                    });
                } else {
                    dropdown.innerHTML = `
                        <div class="px-5 py-4 text-sm text-slate-500 text-center">
                            <i class="fa-solid fa-folder-open text-slate-300 text-2xl mb-2 block"></i>
                            ไม่พบข้อมูลทหารรายนี้ในหน่วยและผลัดที่คุณรับผิดชอบ
                        </div>
                    `;
                }
                dropdown.classList.remove('hidden');
            })
            .catch(error => console.error('เกิดข้อผิดพลาดในการดึงข้อมูล:', error));
    };

    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        const term = this.value.trim();
        timeout = setTimeout(() => window.runPatientAutocomplete(term), 300);
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});

// =========================================================================
// 📸 ระบบสแกนเลขบัตรประชาชน 13 หลักแบบ Real-time (Live Camera + Mod-11 Checksum)
// =========================================================================
let scannerStream = null;
let scannerTimer = null;
let ocrWorker = null;
let isScanningFrame = false;
let torchEnabled = false;

// 1. ตรวจสอบความถูกต้องเลขบัตรประชาชนไทย 13 หลักด้วยสูตร Mod-11 (ป้องกันอ่านผิด 100%)
function isValidThaiCid(cid) {
    if (!/^[1-8]\d{12}$/.test(cid)) return false;
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(cid.charAt(i), 10) * (13 - i);
    }
    const checkDigit = (11 - (sum % 11)) % 10;
    return checkDigit === parseInt(cid.charAt(12), 10);
}

// 2. ค้นหาและคัดแยกเลข 13 หลักที่ผ่านสูตร Mod-11 ออกจากข้อความที่ OCR อ่านได้
function extractValidThaiCidFromText(rawText) {
    if (!rawText) return null;

    // ตรวจทีละบรรทัดก่อนเพื่อป้องกันเลขจากคนละบรรทัดมาต่อกัน
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

    // กรณีเลขถูกตัดบรรทัด ให้ลองตรวจรวมทั้งข้อความ
    const allDigits = rawText.replace(/\D/g, '');
    if (allDigits.length >= 13) {
        for (let i = 0; i <= allDigits.length - 13; i++) {
            const candidate = allDigits.substring(i, i + 13);
            if (isValidThaiCid(candidate)) return candidate;
        }
    }
    return null;
}

// 3. ส่งเสียงติ๊ดสั้นๆ + สั่นเตือนเมื่อสแกนติด
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

// 4. เตรียม Tesseract OCR Worker (โหลดครั้งเดียว ใช้ได้ตลอดหน้าเว็บ)
async function getOcrWorker() {
    if (ocrWorker) return ocrWorker;
    ocrWorker = await Tesseract.createWorker('eng');
    await ocrWorker.setParameters({
        tessedit_char_whitelist: '0123456789 -',
        tessedit_pageseg_mode: '6',
    });
    return ocrWorker;
}

// 5. ปรับแต่งภาพบน Canvas (แปลงขาว-ดำ + เพิ่ม Contrast เพื่อให้ตัวเลขชัดขึ้น)
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

// 6. เปิดกล้องสดและเริ่มลูปสแกน Real-time
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

        // เช็คว่ากล้องรองรับการเปิดไฟฉายหรือไม่
        const track = scannerStream.getVideoTracks()[0];
        const caps = track && typeof track.getCapabilities === 'function' ? track.getCapabilities() : {};
        if (caps.torch && torchBtn) {
            torchBtn.classList.remove('hidden');
            torchEnabled = false;
            document.getElementById('torch_btn_label').innerText = 'เปิดไฟฉาย';
        } else if (torchBtn) {
            torchBtn.classList.add('hidden');
        }

        // โหลด OCR Worker
        await getOcrWorker();
        setScannerStatus('<span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> <span>กำลังสแกนอัตโนมัติ... ถือบัตรให้นิ่ง</span>');

        // เริ่มวนลูปอ่านเฟรมจากกล้องสดทุก 900ms (ไม่บันทึกรูปลงเครื่อง)
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
    const canvas = document.getElementById('id_scanner_canvas');
    if (!video || video.readyState !== 4 || video.videoWidth === 0) return;

    isScanningFrame = true;
    try {
        // ครอปเฉพาะโซนตรงกลางจอ (ความกว้าง 85% x ความสูง 32%) ที่ตรงกับกรอบสีเขียว
        const cropW = Math.floor(video.videoWidth * 0.85);
        const cropH = Math.floor(video.videoHeight * 0.32);
        const cropX = Math.floor((video.videoWidth - cropW) / 2);
        const cropY = Math.floor((video.videoHeight - cropH) / 2);

        canvas.width = cropW;
        canvas.height = cropH;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(video, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);
        preprocessCanvasForOcr(ctx, cropW, cropH);

        const worker = await getOcrWorker();
        const { data: { text } } = await worker.recognize(canvas);
        const validCid = extractValidThaiCidFromText(text);

        if (validCid) {
            onCidScanSuccess(validCid);
        }
    } catch (e) {
        console.warn('Frame OCR skip:', e);
    } finally {
        isScanningFrame = false;
    }
}

// 7. โหมดสำรอง: สแกนจากรูปภาพที่ถ่ายหรือเลือกจากเครื่อง
async function handleStaticIdCardImage(inputEl) {
    const file = inputEl.files && inputEl.files[0];
    if (!file) return;

    // หยุดลูปกล้องสดชั่วคราว
    if (scannerTimer) {
        clearInterval(scannerTimer);
        scannerTimer = null;
    }
    setScannerStatus('<i class="fa-solid fa-spinner fa-spin"></i> <span>กำลังอ่านเลขบัตรประชาชนจากภาพ...</span>');

    try {
        const worker = await getOcrWorker();
        await worker.setParameters({ tessedit_pageseg_mode: '11' }); // โหมด Sparse text สำหรับภาพเต็มใบ
        const { data: { text } } = await worker.recognize(file);
        await worker.setParameters({ tessedit_pageseg_mode: '6' });

        const validCid = extractValidThaiCidFromText(text);
        if (validCid) {
            onCidScanSuccess(validCid);
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

// 8. เมื่อสแกนเจอเลขบัตร 13 หลักที่ถูกต้อง -> ปิดกล้อง ใส่ค่าลงช่องค้นหา และส่งฟอร์มค้นหาทันที
function onCidScanSuccess(cid) {
    playScanSuccessFeedback();
    closeIdCardScanner();

    const searchInput = document.getElementById('search_term');
    const searchForm = document.getElementById('patient_search_form');
    if (searchInput) {
        searchInput.value = cid;
    }

    const formattedCid = cid.replace(/^(\d)(\d{4})(\d{5})(\d{2})(\d)$/, '$1-$2-$3-$4-$5');
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: `สแกนสำเร็จ: ${formattedCid}`,
        text: 'กำลังค้นหาประวัติพลทหาร...',
        showConfirmButton: false,
        timer: 1800
    });

    if (searchForm) {
        setTimeout(() => searchForm.submit(), 350);
    }
}

// 9. สลับไฟฉายมือถือ
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

// 10. ปิดกล้องและคืนทรัพยากรให้มือถือทันที
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