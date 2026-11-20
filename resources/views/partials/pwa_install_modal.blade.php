<!-- ========================================================================= -->
<!-- 📱 Modal คู่มือติดตั้งแอป KSVR MEWS ลงมือถือ (รองรับ iOS & Android อัตโนมัติ) -->
<!-- ========================================================================= -->
<div id="pwaInstallGuideModal" class="hidden fixed inset-0 z-[100] bg-slate-950/75 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div class="bg-white dark:bg-slate-800 w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden max-h-[90vh] flex flex-col">

        <!-- ส่วนหัว Modal -->
        <div class="px-5 py-4 bg-slate-800 text-white flex items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-500 flex items-center justify-center text-white text-lg shadow-md shrink-0">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <div>
                    <h3 class="text-base font-black leading-tight">ติดตั้งแอป KSVR ลงมือถือ</h3>
                    <p class="text-[11px] text-slate-300 mt-0.5">เปิดใช้งานเต็มจอ ไร้แถบ URL และแจ้งเตือนไว</p>
                </div>
            </div>
            <button type="button" onclick="window.closePwaInstallModal()"
                    class="w-8 h-8 rounded-full bg-slate-700 hover:bg-rose-500 text-slate-300 hover:text-white flex items-center justify-center transition-colors shrink-0">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- แถบแจ้งเตือนพิเศษ กรณีผู้ใช้เผลอกดลิงก์เปิดผ่านแอป LINE / Facebook -->
        <div id="pwa_inapp_warning" class="hidden mx-4 mt-4 p-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-bold flex items-start gap-2.5 shrink-0">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm mt-0.5 shrink-0"></i>
            <div>
                ขณะนี้คุณเปิดผ่านเบราว์เซอร์ในแอป (เช่น LINE/Facebook) กรุณากดปุ่ม <b>จุด 3 จุด / แชร์</b> แล้วเลือก <b>"เปิดด้วย Safari หรือ Chrome"</b> ก่อนจึงจะติดตั้งได้ครับ
            </div>
        </div>

        <!-- แถบสลับแท็บ iOS / Android -->
        <div class="px-4 pt-4 shrink-0">
            <div class="grid grid-cols-2 gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-700">
                <button type="button" id="pwa_tab_ios" onclick="window.switchPwaTab('ios')"
                        class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-black flex items-center justify-center gap-2 transition-all">
                    <i class="fa-brands fa-apple text-base"></i> iPhone / iPad (iOS)
                </button>
                <button type="button" id="pwa_tab_android" onclick="window.switchPwaTab('android')"
                        class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-black flex items-center justify-center gap-2 transition-all">
                    <i class="fa-brands fa-android text-base"></i> Android (Chrome)
                </button>
            </div>
        </div>

        <!-- เนื้อหาวิธีติดตั้ง (เลื่อนดูได้เมื่อจอเล็ก) -->
        <div class="p-4 sm:p-5 overflow-y-auto space-y-4 text-left">

            <!-- ================= แท็บ 1: วิธีติดตั้งบน iOS (Safari) ================= -->
            <div id="pwa_panel_ios" class="hidden space-y-3">
                <div class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i class="fa-brands fa-safari text-sky-500 text-sm"></i>
                    <span>เปิดเว็บด้วยแอป <b>Safari</b> เท่านั้น แล้วทำตาม 3 ขั้นตอน:</span>
                </div>

                <!-- ขั้นตอน 1 -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-slate-800 text-white text-xs font-black flex items-center justify-center shrink-0 mt-0.5">1</div>
                    <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-200 leading-relaxed">
                        แตะปุ่ม <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-sky-100 text-sky-700 font-black text-xs mx-0.5"><i class="fa-solid fa-arrow-up-from-bracket"></i> แชร์ (Share)</span> ที่แถบเมนูด้านล่างสุดของหน้าจอ Safari
                    </div>
                </div>

                <!-- ขั้นตอน 2 -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-slate-800 text-white text-xs font-black flex items-center justify-center shrink-0 mt-0.5">2</div>
                    <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-200 leading-relaxed">
                        เลื่อนรายการลงมาแล้วแตะที่เมนู <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-800 font-black text-xs mx-0.5"><i class="fa-regular fa-square-plus"></i> เพิ่มไปยังหน้าจอโฮม</span> (Add to Home Screen)
                    </div>
                </div>

                <!-- ขั้นตอน 3 -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-slate-800 text-white text-xs font-black flex items-center justify-center shrink-0 mt-0.5">3</div>
                    <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-200 leading-relaxed">
                        แตะปุ่ม <b class="text-sky-600">"เพิ่ม" (Add)</b> มุมขวาบน จากนั้นกดเข้าใช้งานผ่านไอคอน <b>KSVR MEWS</b> บนหน้าจอหลักได้เลย
                    </div>
                </div>
            </div>

            <!-- ================= แท็บ 2: วิธีติดตั้งบน Android (Chrome) ================= -->
            <div id="pwa_panel_android" class="hidden space-y-3">

                <!-- ปุ่มติดตั้งอัตโนมัติใน 1 คลิก (แสดงทันทีเมื่อ Chrome พร้อมติดตั้ง WebAPK) -->
                <div id="pwa_android_oneclick_box" class="hidden p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border-2 border-emerald-300 dark:border-emerald-700 text-center space-y-2.5">
                    <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300">
                        เครื่องของคุณรองรับการติดตั้งแอปอัตโนมัติใน 1 คลิก!
                    </p>
                    <button type="button" onclick="window.triggerAndroidNativeInstall()"
                            class="w-full py-3 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm shadow-md flex items-center justify-center gap-2 transition-all">
                        <i class="fa-solid fa-download"></i> ติดตั้งแอป KSVR ทันที
                    </button>
                </div>

                <div class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i class="fa-brands fa-chrome text-emerald-600 text-sm"></i>
                    <span>เปิดเว็บด้วยแอป <b>Google Chrome</b> แล้วทำตาม 3 ขั้นตอน:</span>
                </div>

                <!-- ขั้นตอน 1 -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-emerald-600 text-white text-xs font-black flex items-center justify-center shrink-0 mt-0.5">1</div>
                    <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-200 leading-relaxed">
                        แตะปุ่ม <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-white font-black text-xs mx-0.5"><i class="fa-solid fa-ellipsis-vertical"></i> จุด 3 จุด</span> ที่มุมขวาบนของหน้าจอ Chrome
                    </div>
                </div>

                <!-- ขั้นตอน 2 -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-emerald-600 text-white text-xs font-black flex items-center justify-center shrink-0 mt-0.5">2</div>
                    <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-200 leading-relaxed">
                        เลือกเมนู <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-800 font-black text-xs mx-0.5"><i class="fa-solid fa-mobile-screen"></i> เพิ่มลงในหน้าจอหลัก</span> หรือ <b>"ติดตั้งแอป"</b>
                    </div>
                </div>

                <!-- ขั้นตอน 3 -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-emerald-600 text-white text-xs font-black flex items-center justify-center shrink-0 mt-0.5">3</div>
                    <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-200 leading-relaxed">
                        ในหน้าต่างยืนยัน ให้เลือกคำว่า <b class="text-emerald-600">"ติดตั้ง" (Install)</b> <span class="text-rose-600 font-bold">(ห้ามเลือก "สร้างทางลัด")</span> รอ 3 วินาที ไอคอนแอปเต็มจอจะปรากฏบนเครื่องทันที
                    </div>
                </div>
            </div>

        </div>

        <!-- แถบปุ่มปิดด้านล่าง -->
        <div class="px-4 py-3 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700 shrink-0">
            <button type="button" onclick="window.closePwaInstallModal()"
                    class="w-full py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-sm transition-colors">
                เข้าใจแล้ว / ปิดหน้าต่าง
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    let deferredInstallPrompt = null;

    // 1. ตรวจสอบว่ากำลังรันอยู่ในโหมดแอปเต็มจอ (Standalone PWA) แล้วหรือไม่
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    // 2. ตรวจจับระบบปฏิบัติการและเบราว์เซอร์ในแอป (LINE / Facebook)
    const ua = navigator.userAgent || '';
    const isIos = /iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isInAppBrowser = /Line\/|FBAN|FBAV|Instagram/i.test(ua);

    // 3. ซ่อนปุ่มชวนติดตั้งอัตโนมัติ (คลาส .pwa-hide-when-installed) หากเปิดผ่านแอปที่ติดตั้งแล้ว
    document.addEventListener('DOMContentLoaded', function() {
        if (isStandalone) {
            document.querySelectorAll('.pwa-hide-when-installed').forEach(el => el.classList.add('hidden'));
        }
        if (isInAppBrowser) {
            document.getElementById('pwa_inapp_warning')?.classList.remove('hidden');
        }
        window.switchPwaTab(isIos ? 'ios' : 'android');
    });

    // 4. ดักจับ Event ติดตั้งแอป 1 คลิกของ Android Chrome
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredInstallPrompt = e;
        document.getElementById('pwa_android_oneclick_box')?.classList.remove('hidden');
    });

    // ซ่อนปุ่มอัตโนมัติทันทีหลังผู้ใช้กดติดตั้งสำเร็จ
    window.addEventListener('appinstalled', function() {
        deferredInstallPrompt = null;
        document.getElementById('pwa_android_oneclick_box')?.classList.add('hidden');
        document.querySelectorAll('.pwa-hide-when-installed').forEach(el => el.classList.add('hidden'));
        window.closePwaInstallModal();
    });

    window.switchPwaTab = function(os) {
        const tabIos = document.getElementById('pwa_tab_ios');
        const tabAnd = document.getElementById('pwa_tab_android');
        const pnlIos = document.getElementById('pwa_panel_ios');
        const pnlAnd = document.getElementById('pwa_panel_android');

        const activeCls   = ['bg-white', 'dark:bg-slate-800', 'text-slate-900', 'dark:text-white', 'shadow-sm'];
        const inactiveCls = ['text-slate-500', 'dark:text-slate-400'];

        if (os === 'ios') {
            pnlIos?.classList.remove('hidden');
            pnlAnd?.classList.add('hidden');
            tabIos?.classList.add(...activeCls);
            tabIos?.classList.remove(...inactiveCls);
            tabAnd?.classList.remove(...activeCls);
            tabAnd?.classList.add(...inactiveCls);
        } else {
            pnlAnd?.classList.remove('hidden');
            pnlIos?.classList.add('hidden');
            tabAnd?.classList.add(...activeCls);
            tabAnd?.classList.remove(...inactiveCls);
            tabIos?.classList.remove(...activeCls);
            tabIos?.classList.add(...inactiveCls);
        }
    };

    window.openPwaInstallModal = function() {
        window.switchPwaTab(isIos ? 'ios' : 'android');
        document.getElementById('pwaInstallGuideModal')?.classList.remove('hidden');
    };

    window.closePwaInstallModal = function() {
        document.getElementById('pwaInstallGuideModal')?.classList.add('hidden');
    };

    window.triggerAndroidNativeInstall = async function() {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        const choice = await deferredInstallPrompt.userChoice;
        if (choice.outcome === 'accepted') {
            deferredInstallPrompt = null;
        }
    };

    // ปิด Modal เมื่อคลิกพื้นหลังด้านนอก
    document.getElementById('pwaInstallGuideModal')?.addEventListener('click', function(e) {
        if (e.target === this) window.closePwaInstallModal();
    });
})();
</script>