<!-- ========================================================================= -->
<!-- 🗺️ Modal ภาพรวมกระบวนการระบบ M-CARE & KSVR MEWS (ฉบับอัปเดตครบทุกฟีเจอร์) -->
<!-- ========================================================================= -->
<div id="workflowOverviewModal" class="hidden fixed inset-0 z-[95] bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5">
    <div class="bg-white dark:bg-slate-900 w-full max-w-5xl rounded-3xl shadow-2xl border border-slate-200/80 dark:border-slate-700 overflow-hidden max-h-[92vh] flex flex-col animate-fade-in-up">

        <!-- ส่วนหัว Modal -->
        <div class="px-5 sm:px-7 py-4 sm:py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-4 bg-white/95 dark:bg-slate-900/95 shrink-0">
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/60 flex items-center justify-center text-xl shrink-0 shadow-sm">
                    <i class="fa-solid fa-diagram-project"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base sm:text-xl font-black text-slate-800 dark:text-white tracking-tight truncate">
                            ภาพรวมกระบวนการ M-CARE &amp; KSVR MEWS
                        </h3>
                        <span class="hidden sm:inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> End-to-End Workflow
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                        ระบบบริหารจัดการสุขภาพทหารใหม่และคัดกรองฉุกเฉินแบบครบวงจร • โรงพยาบาลค่ายกฤษณ์สีวะรา
                    </p>
                </div>
            </div>

            <button type="button" onclick="window.closeWorkflowOverviewModal()" title="ปิดหน้าต่าง"
                    class="w-9 h-9 rounded-full bg-slate-100 hover:bg-rose-100 dark:bg-slate-800 dark:hover:bg-rose-900/40 text-slate-500 hover:text-rose-600 flex items-center justify-center transition-colors shrink-0">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- เนื้อหากระบวนการ (เลื่อนดูได้ลื่นบนมือถือ) -->
        <div class="p-5 sm:p-7 overflow-y-auto space-y-5">
            <div class="relative">
                <!-- เส้นเชื่อมแนวนอน (เฉพาะจอใหญ่) -->
                <div class="hidden lg:block absolute top-14 left-12 right-12 h-0.5 bg-gradient-to-r from-teal-300 via-emerald-300 via-rose-300 to-indigo-300 dark:from-teal-800 dark:via-emerald-800 dark:via-rose-800 dark:to-indigo-800 z-0"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 relative z-10">

                    <!-- ขั้นตอนที่ 1 -->
                    <div class="relative pt-3">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-teal-600 text-white text-[11px] font-black shadow-sm whitespace-nowrap">
                            ขั้นตอนที่ 1
                        </div>
                        <div class="h-full bg-white dark:bg-slate-800/90 rounded-2xl border-2 border-teal-200 dark:border-teal-800/70 hover:border-teal-400 p-4 pt-6 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-all">
                            <div class="w-12 h-12 rounded-2xl bg-teal-50 dark:bg-teal-900/40 text-teal-600 dark:text-teal-400 flex items-center justify-center text-xl mb-3 shadow-inner">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mb-2.5">
                                คัดกรองแรกรับ &amp; ทะเบียน
                            </h4>
                            <ul class="w-full text-left text-xs text-slate-600 dark:text-slate-300 space-y-2 font-medium">
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-camera text-teal-500 mt-0.5 shrink-0"></i>
                                    <span>สแกนบัตร ปชช. <b class="text-slate-800 dark:text-white">กล้องสด (OCR)</b></span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-traffic-light text-teal-500 mt-0.5 shrink-0"></i>
                                    <span>คัดแยกกลุ่มสี &amp; ซักประวัติโรคประจำตัว</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-rotate text-teal-500 mt-0.5 shrink-0"></i>
                                    <span>ซิงค์ <b class="text-teal-700 dark:text-teal-400">HN &amp; สิทธิ HosXP</b> อัตโนมัติ</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- ขั้นตอนที่ 2 -->
                    <div class="relative pt-3">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-emerald-600 text-white text-[11px] font-black shadow-sm whitespace-nowrap">
                            ขั้นตอนที่ 2
                        </div>
                        <div class="h-full bg-white dark:bg-slate-800/90 rounded-2xl border-2 border-emerald-200 dark:border-emerald-800/70 hover:border-emerald-400 p-4 pt-6 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-all">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl mb-3 shadow-inner">
                                <i class="fa-solid fa-walkie-talkie"></i>
                            </div>
                            <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mb-2.5">
                                เสนารักษ์ (ภาคสนาม)
                            </h4>
                            <ul class="w-full text-left text-xs text-slate-600 dark:text-slate-300 space-y-2 font-medium">
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-magnifying-glass text-emerald-500 mt-0.5 shrink-0"></i>
                                    <span>สแกนบัตรค้นหาประวัติเข้าประเมินทันที</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-heart-pulse text-emerald-500 mt-0.5 shrink-0"></i>
                                    <span>ประเมิน <b class="text-slate-800 dark:text-white">MEWS (V/S)</b> &amp; อาการเฉพาะโรค</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-tower-broadcast text-emerald-500 mt-0.5 shrink-0"></i>
                                    <span>แจ้งเตือน ER / Discord / เฝ้าระบาด</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- ขั้นตอนที่ 3 -->
                    <div class="relative pt-3">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-rose-600 text-white text-[11px] font-black shadow-sm whitespace-nowrap">
                            ขั้นตอนที่ 3
                        </div>
                        <div class="h-full bg-white dark:bg-slate-800/90 rounded-2xl border-2 border-rose-200 dark:border-rose-800/70 hover:border-rose-400 p-4 pt-6 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-all">
                            <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl mb-3 shadow-inner">
                                <i class="fa-solid fa-truck-medical"></i>
                            </div>
                            <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mb-2.5">
                                ห้องฉุกเฉิน (ER) &amp; แพทย์
                            </h4>
                            <ul class="w-full text-left text-xs text-slate-600 dark:text-slate-300 space-y-2 font-medium">
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-bell text-rose-500 mt-0.5 shrink-0"></i>
                                    <span>รับ Alert + <b class="text-rose-600 dark:text-rose-400">Web Push</b> ปลุกมือถือทันที</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-stethoscope text-rose-500 mt-0.5 shrink-0"></i>
                                    <span>เปิดแฟ้ม<b class="text-slate-800 dark:text-white">ประเมินซ้ำ</b> &amp; สั่ง Admit / Refer</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-paper-plane text-rose-500 mt-0.5 shrink-0"></i>
                                    <span>จบเคสพร้อมแจ้งผลกลับต้นสังกัดทันที</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- ขั้นตอนที่ 4 -->
                    <div class="relative pt-3">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-indigo-600 text-white text-[11px] font-black shadow-sm whitespace-nowrap">
                            ขั้นตอนที่ 4
                        </div>
                        <div class="h-full bg-white dark:bg-slate-800/90 rounded-2xl border-2 border-indigo-200 dark:border-indigo-800/70 hover:border-indigo-400 p-4 pt-6 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-all">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl mb-3 shadow-inner">
                                <i class="fa-solid fa-pills"></i>
                            </div>
                            <h4 class="text-sm sm:text-base font-black text-slate-800 dark:text-white mb-2.5">
                                ดูแลต่อเนื่อง &amp; นำปลด
                            </h4>
                            <ul class="w-full text-left text-xs text-slate-600 dark:text-slate-300 space-y-2 font-medium">
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-bed-pulse text-indigo-500 mt-0.5 shrink-0"></i>
                                    <span>ห้องพยาบาลหน่วย / แยกกัก CI &amp; <b class="text-slate-800 dark:text-white">ตารางยา e-MAR</b></span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-calendar-check text-indigo-500 mt-0.5 shrink-0"></i>
                                    <span>ติดตามกลับหน่วยฝึก &amp; นัดพบแพทย์</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-file-shield text-indigo-500 mt-0.5 shrink-0"></i>
                                    <span>ทะเบียนนำปลด (ทางกาย / สุขภาพจิต)</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>

            <!-- แถบผู้บริหาร & การเชื่อมต่อระบบ -->
            <div class="bg-slate-900 dark:bg-slate-800/95 text-white rounded-2xl p-4 sm:p-5 border border-slate-700/80 shadow-md">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="flex items-start sm:items-center gap-3.5">
                        <div class="w-11 h-11 rounded-2xl bg-amber-500/15 border border-amber-400/30 text-amber-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <div>
                            <h4 class="text-sm sm:text-base font-black text-white">
                                ผู้บริหาร, ทะเบียน &amp; ผู้ดูแลระบบ (Executive / Admin)
                            </h4>
                            <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                                ติดตาม <b class="text-white">Dashboard สถิติภาพรวมแบบ Real-time</b>, ออก <b class="text-amber-300">รายงานอาการเจ็บป่วยประจำวัน (Export สไลด์ JPG 1 คลิก)</b>, วิเคราะห์ข้อมูลด้วย <b class="text-indigo-300">KSVR AI</b> และส่งออก Excel/CSV โดยไม่รบกวนหน้างาน
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 lg:justify-end shrink-0">
                        <span class="px-2.5 py-1 rounded-lg bg-slate-800 dark:bg-slate-900 border border-slate-700 text-[11px] font-bold text-emerald-300 inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-database"></i> HosXP Read-Only
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-slate-800 dark:bg-slate-900 border border-slate-700 text-[11px] font-bold text-sky-300 inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-shield-halved"></i> OCR ในเครื่อง (PDPA)
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-slate-800 dark:bg-slate-900 border border-slate-700 text-[11px] font-bold text-indigo-300 inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-plug"></i> REST API
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-slate-800 dark:bg-slate-900 border border-slate-700 text-[11px] font-bold text-amber-300 inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-mobile-screen"></i> PWA เต็มจอ
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ส่วนท้าย Modal (แก้ลิงก์ใช้ url('/backend/help') เพื่อไม่ให้หลุดขอบเขต /ksvr-mews/ ของ PWA) -->
        <div class="px-5 sm:px-7 py-3.5 bg-slate-50 dark:bg-slate-800/70 border-t border-slate-200/80 dark:border-slate-800 flex flex-wrap items-center justify-center sm:justify-between gap-2.5 shrink-0">
            <div class="flex flex-wrap items-center justify-center gap-2.5 w-full sm:w-auto">
                <a href="{{ Route::has('backend.help.index') ? route('backend.help.index') : url('/backend/help') }}"
                   class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-600 dark:hover:text-white border border-indigo-200 dark:border-indigo-700/70 text-xs sm:text-sm font-black inline-flex items-center justify-center gap-2 shadow-sm transition-all">
                    <i class="fa-solid fa-book-open-reader"></i>
                    <span>เปิดศูนย์ช่วยเหลือ (Help Center)</span>
                </a>

                <button type="button" onclick="window.closeWorkflowOverviewModal(); if (typeof window.openPwaInstallModal === 'function') window.openPwaInstallModal();"
                        class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-emerald-600 dark:hover:text-white border border-emerald-200 dark:border-emerald-800 text-xs sm:text-sm font-bold inline-flex items-center justify-center gap-2 shadow-sm transition-all">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                    <span>วิธีติดตั้งแอปลงมือถือ</span>
                </button>
            </div>

            <button type="button" onclick="window.closeWorkflowOverviewModal()"
                    class="hidden sm:inline-flex px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs sm:text-sm font-bold transition-colors">
                ปิดหน้าต่าง
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    window.openWorkflowOverviewModal = function() {
        const modal = document.getElementById('workflowOverviewModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    };

    window.closeWorkflowOverviewModal = function() {
        const modal = document.getElementById('workflowOverviewModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    };

    document.getElementById('workflowOverviewModal')?.addEventListener('click', function(e) {
        if (e.target === this) window.closeWorkflowOverviewModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('workflowOverviewModal');
            if (modal && !modal.classList.contains('hidden')) {
                window.closeWorkflowOverviewModal();
            }
        }
    });
})();
</script>