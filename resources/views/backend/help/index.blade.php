@extends('layouts.backend')

@section('title', 'ศูนย์ช่วยเหลือ (Help Center) - KSVR Hospital')
@section('page_header', 'คู่มือการใช้งานระบบ (Help Center)')

@section('content')
<div class="max-w-7xl mx-auto mt-2 sm:mt-4 pb-16 space-y-8">

    <!-- 🟢 1. Hero Banner: ศูนย์ช่วยเหลือ M-CARE & KSVR MEWS -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 md:p-10 text-white shadow-xl border border-indigo-500/30">
        <!-- แสง Glow พื้นหลัง -->
        <div class="absolute -top-20 -right-20 w-72 h-72 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-16 w-64 h-64 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
        <i class="fa-solid fa-book-medical absolute right-6 bottom-4 text-8xl sm:text-9xl text-white/[0.04] pointer-events-none select-none"></i>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="max-w-2xl space-y-2.5">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/30 text-indigo-200 text-xs font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    M-CARE &amp; KSVR MEWS Help Center
                </div>
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight leading-tight">
                    คู่มือและภาพรวมการใช้งานระบบ
                </h2>
                <p class="text-slate-300 text-xs sm:text-sm md:text-base leading-relaxed">
                    สรุปขั้นตอนการทำงานตั้งแต่การสแกนบัตร ปชช. คัดกรองทหารใหม่ การประเมิน MEWS ภาคสนาม คิวจุดคัดกรอง (Triage) การรับเคสห้องฉุกเฉิน (ER/OPD) ไปจนถึงตารางยา e-MAR และรายงานผู้บริหาร
                </p>
            </div>

            <!-- ปุ่มเครื่องมือด่วน -->
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button type="button" onclick="if (typeof window.openPwaInstallModal === 'function') window.openPwaInstallModal();"
                        class="flex-1 sm:flex-none px-4 py-3 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-black text-xs sm:text-sm shadow-lg shadow-emerald-950/40 inline-flex items-center justify-center gap-2 transition-all">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                    <span>วิธีติดตั้งแอปลงมือถือ</span>
                </button>
                <button type="button" onclick="if (typeof openExecutiveAiModal === 'function') openExecutiveAiModal();"
                        class="flex-1 sm:flex-none px-4 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white border border-white/20 font-bold text-xs sm:text-sm backdrop-blur-sm inline-flex items-center justify-center gap-2 transition-all">
                    <i class="fa-solid fa-robot text-indigo-300"></i>
                    <span>ถาม KSVR AI</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 🗺️ 2. ภาพรวมกระบวนการทำงานแบบครบวงจร 5 ขั้นตอน (End-to-End Workflow รวมจุดคัดกรอง Triage) -->
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-1">
            <h3 class="text-lg sm:text-xl font-black text-slate-800 dark:text-white flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-route"></i>
                </span>
                <span>ภาพรวมกระบวนการทำงาน (5 ขั้นตอนหลัก)</span>
            </h3>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                เชื่อมต่อข้อมูลแบบ Real-time ระหว่างหน่วยฝึก • จุดคัดกรอง • ห้อง ER/OPD • ฐานข้อมูล HosXP
            </span>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-7 shadow-sm border border-slate-200/80 dark:border-slate-700 space-y-6">
            <div class="relative">
                <!-- เส้นเชื่อมขั้นตอนแนวนอน (Desktop) -->
                <div class="hidden xl:block absolute top-14 left-10 right-10 h-0.5 bg-gradient-to-r from-teal-300 via-emerald-300 via-amber-300 via-rose-300 to-indigo-300 dark:from-teal-800 dark:via-emerald-800 dark:via-amber-800 dark:via-rose-800 dark:to-indigo-800 z-0"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 relative z-10">

                    <!-- STEP 1 -->
                    <div class="relative pt-3">
                        <span class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-teal-600 text-white text-[11px] font-black shadow-sm">
                            STEP 1
                        </span>
                        <div class="h-full bg-teal-50/40 dark:bg-teal-900/15 rounded-2xl border-2 border-teal-200 dark:border-teal-800/70 p-4 pt-6 flex flex-col text-center hover:shadow-md hover:-translate-y-0.5 transition-all">
                            <div class="w-12 h-12 mx-auto rounded-2xl bg-white dark:bg-slate-800 text-teal-600 dark:text-teal-400 border border-teal-100 dark:border-teal-800 flex items-center justify-center text-xl shadow-sm mb-3">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <h4 class="font-black text-sm sm:text-base text-slate-800 dark:text-white mb-2">คัดกรองแรกรับ &amp; ทะเบียน</h4>
                            <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 text-left mt-1 font-medium">
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-teal-500 mt-0.5 shrink-0"></i> สแกนบัตร ปชช. กล้องสด (OCR + Mod-11)</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-teal-500 mt-0.5 shrink-0"></i> คัดแยกสีเขียว/เหลือง/แดง &amp; โรคประจำตัว</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-teal-500 mt-0.5 shrink-0"></i> ซิงค์ HN และสิทธิการรักษาจาก HosXP</li>
                            </ul>
                        </div>
                    </div>

                    <!-- STEP 2 -->
                    <div class="relative pt-3">
                        <span class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-emerald-600 text-white text-[11px] font-black shadow-sm">
                            STEP 2
                        </span>
                        <div class="h-full bg-emerald-50/40 dark:bg-emerald-900/15 rounded-2xl border-2 border-emerald-200 dark:border-emerald-800/70 p-4 pt-6 flex flex-col text-center hover:shadow-md hover:-translate-y-0.5 transition-all">
                            <div class="w-12 h-12 mx-auto rounded-2xl bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800 flex items-center justify-center text-xl shadow-sm mb-3">
                                <i class="fa-solid fa-walkie-talkie"></i>
                            </div>
                            <h4 class="font-black text-sm sm:text-base text-slate-800 dark:text-white mb-2">เสนารักษ์ (ภาคสนาม)</h4>
                            <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 text-left mt-1 font-medium">
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-emerald-500 mt-0.5 shrink-0"></i> สแกนบัตรค้นหาประวัติเข้าประเมิน MEWS ทันที</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-emerald-500 mt-0.5 shrink-0"></i> บันทึก V/S และอาการฉุกเฉินเฉพาะโรค</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-emerald-500 mt-0.5 shrink-0"></i> แจ้งเตือนรอบแรกทุกเคส (รอบถัดไปไม่เด้งซ้ำ)</li>
                            </ul>
                        </div>
                    </div>

                    <!-- STEP 3 (เพิ่มใหม่): จุดคัดกรอง (Triage) -->
                    <div class="relative pt-3">
                        <span class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-amber-500 text-white text-[11px] font-black shadow-sm">
                            STEP 3
                        </span>
                        <div class="h-full bg-amber-50/50 dark:bg-amber-900/15 rounded-2xl border-2 border-amber-300 dark:border-amber-800/70 p-4 pt-6 flex flex-col text-center hover:shadow-md hover:-translate-y-0.5 transition-all">
                            <div class="w-12 h-12 mx-auto rounded-2xl bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 flex items-center justify-center text-xl shadow-sm mb-3">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </div>
                            <h4 class="font-black text-sm sm:text-base text-slate-800 dark:text-white mb-2">จุดคัดกรอง (Triage)</h4>
                            <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 text-left mt-1 font-medium">
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-amber-500 mt-0.5 shrink-0"></i> จัดคิวเรียงคะแนน <b>MEWS สูงสุดขึ้นอันดับ 1</b></li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-amber-500 mt-0.5 shrink-0"></i> ตรวจสอบอาการและคัดแยกส่งต่อ ER / OPD</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-amber-500 mt-0.5 shrink-0"></i> พิมพ์ใบ MEWS พร้อมช่องเขียนรับรองจุดคัดกรอง</li>
                            </ul>
                        </div>
                    </div>

                    <!-- STEP 4 -->
                    <div class="relative pt-3">
                        <span class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-rose-600 text-white text-[11px] font-black shadow-sm">
                            STEP 4
                        </span>
                        <div class="h-full bg-rose-50/40 dark:bg-rose-900/15 rounded-2xl border-2 border-rose-200 dark:border-rose-800/70 p-4 pt-6 flex flex-col text-center hover:shadow-md hover:-translate-y-0.5 transition-all">
                            <div class="w-12 h-12 mx-auto rounded-2xl bg-white dark:bg-slate-800 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-800 flex items-center justify-center text-xl shadow-sm mb-3">
                                <i class="fa-solid fa-truck-medical"></i>
                            </div>
                            <h4 class="font-black text-sm sm:text-base text-slate-800 dark:text-white mb-2">ห้องฉุกเฉิน (ER) &amp; แพทย์</h4>
                            <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 text-left mt-1 font-medium">
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-rose-500 mt-0.5 shrink-0"></i> รับแจ้งเตือน Real-time + Web Push ปลุกมือถือ</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-rose-500 mt-0.5 shrink-0"></i> เปิดแฟ้มประเมินสัญญาณชีพซ้ำเมื่อถึง ER</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-rose-500 mt-0.5 shrink-0"></i> สั่งจำหน่าย (กลับหน่วย / Admit / Refer)</li>
                            </ul>
                        </div>
                    </div>

                    <!-- STEP 5 -->
                    <div class="relative pt-3 sm:col-span-2 lg:col-span-1">
                        <span class="absolute top-0 left-1/2 -translate-x-1/2 z-20 px-3 py-0.5 rounded-full bg-indigo-600 text-white text-[11px] font-black shadow-sm">
                            STEP 5
                        </span>
                        <div class="h-full bg-indigo-50/40 dark:bg-indigo-900/15 rounded-2xl border-2 border-indigo-200 dark:border-indigo-800/70 p-4 pt-6 flex flex-col text-center hover:shadow-md hover:-translate-y-0.5 transition-all">
                            <div class="w-12 h-12 mx-auto rounded-2xl bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800 flex items-center justify-center text-xl shadow-sm mb-3">
                                <i class="fa-solid fa-pills"></i>
                            </div>
                            <h4 class="font-black text-sm sm:text-base text-slate-800 dark:text-white mb-2">ดูแลต่อเนื่อง &amp; นำปลด</h4>
                            <ul class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 text-left mt-1 font-medium">
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-indigo-500 mt-0.5 shrink-0"></i> ห้องพยาบาลหน่วย / แยกกัก CI &amp; ตารางยา e-MAR</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-indigo-500 mt-0.5 shrink-0"></i> แจ้งเตือนคิวนัดพบแพทย์ล่วงหน้า 1 วัน</li>
                                <li class="flex items-start gap-1.5"><i class="fa-solid fa-check text-indigo-500 mt-0.5 shrink-0"></i> ติดตามเอกสารนำปลด (กาย / สุขภาพจิต)</li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>

            <!-- กล่องศูนย์บัญชาการผู้บริหาร & สถาปัตยกรรมระบบ -->
            <div class="bg-slate-900 dark:bg-slate-950 rounded-2xl p-5 border border-slate-700/80 text-white flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-md">
                <div class="flex items-start sm:items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/15 border border-amber-400/30 text-amber-400 flex items-center justify-center text-2xl shrink-0">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-sm sm:text-base text-white">ผู้บริหาร, ทะเบียน &amp; ผู้ดูแลระบบ (Executive / Admin)</h4>
                        <p class="text-xs sm:text-sm text-slate-300 mt-0.5 leading-relaxed">
                            ติดตามภาพรวมกำลังพล, ออก <b class="text-amber-300">รายงานเจ็บป่วยประจำวัน (Export สไลด์ JPG 1 คลิก)</b>, วิเคราะห์ข้อมูลด้วย AI และเชื่อมข้อมูลสรุปออกภายนอกผ่าน <b class="text-indigo-300">REST API</b> อย่างปลอดภัย
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1.5 shrink-0">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 text-[11px] font-bold text-emerald-300"><i class="fa-solid fa-database mr-1"></i>HosXP Read-Only</span>
                    <span class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 text-[11px] font-bold text-sky-300"><i class="fa-solid fa-shield-halved mr-1"></i>OCR PDPA-Safe</span>
                    <span class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 text-[11px] font-bold text-rose-300"><i class="fa-solid fa-bell mr-1"></i>Web Push</span>
                    <span class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 text-[11px] font-bold text-amber-300"><i class="fa-solid fa-mobile-screen mr-1"></i>PWA เต็มจอ</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 📚 3. คู่มือการใช้งานแยกตามฟีเจอร์ (เลือกกรองตามบทบาท รวมแท็บจุดคัดกรอง) -->
    <div class="space-y-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 px-1">
            <h3 class="text-lg sm:text-xl font-black text-slate-800 dark:text-white flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-base">
                    <i class="fa-solid fa-list-check"></i>
                </span>
                <span>คู่มือการใช้งานแต่ละโมดูล (Quick Guides)</span>
            </h3>

            <!-- ปุ่มกรองตามบทบาท -->
            <div class="flex flex-wrap gap-1.5" id="help-role-filters">
                <button type="button" onclick="filterHelpCards('all', this)" class="help-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-black bg-slate-800 text-white shadow-sm transition-all">ทั้งหมด (7)</button>
                <button type="button" onclick="filterHelpCards('screening', this)" class="help-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-amber-400 transition-all">จุดคัดกรอง</button>
                <button type="button" onclick="filterHelpCards('medic', this)" class="help-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-emerald-400 transition-all">เสนารักษ์</button>
                <button type="button" onclick="filterHelpCards('er', this)" class="help-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-rose-400 transition-all">พยาบาล ER / OPD</button>
                <button type="button" onclick="filterHelpCards('admin', this)" class="help-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-400 transition-all">ทะเบียน / ผู้บริหาร</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">

            <!-- การ์ด 1: สแกนบัตร ปชช. กล้องสด (OCR) -->
            <div data-roles="screening medic er admin" class="help-guide-card bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700 hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">ทุกตำแหน่ง</span>
                    </div>
                    <h4 class="font-black text-slate-800 dark:text-white text-base mb-2">1. สแกนหน้าบัตร ปชช. กล้องสด (OCR)</h4>
                    <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 list-decimal list-inside leading-relaxed">
                        <li>กดปุ่ม <b>สแกนบัตร ปชช. (กล้องสด)</b> ในหน้าค้นหาหรือหน้าคัดกรอง</li>
                        <li>วางแถว <b>เลข 13 หลัก</b> ให้อยู่ในกรอบบน และ <b>ชื่อ-สกุลไทย</b> ในกรอบล่าง</li>
                        <li>ระบบตรวจสูตร Mod-11 อัตโนมัติ ไม่บันทึกรูปลงเครื่อง (PDPA Safe)</li>
                        <li>หากไม่พบใน KSVR ระบบช่วยเช็กชื่อยืนยันจาก <b>HosXP</b> ให้ทันที</li>
                    </ol>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400 font-semibold"><i class="fa-solid fa-bolt text-amber-500 mr-1"></i>มีปุ่มไฟฉายสำหรับที่มืด</span>
                    <a href="{{ route('backend.patients.search') }}" class="text-xs font-black text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-1">
                        ไปที่หน้าค้นหา <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- การ์ด 2: คัดกรองทหารใหม่แรกรับ -->
            <div data-roles="screening er admin" class="help-guide-card bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700 hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-shield-virus"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-teal-50 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800">จุดคัดกรอง / ทะเบียน</span>
                    </div>
                    <h4 class="font-black text-slate-800 dark:text-white text-base mb-2">2. คัดกรองทหารใหม่ &amp; ซิงค์สิทธิ HosXP</h4>
                    <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 list-decimal list-inside leading-relaxed">
                        <li>เข้าเมนู <b>คัดกรองทหารใหม่</b> แล้วกด <b>เข้าสู่หน้าสแกนคัดกรอง</b></li>
                        <li>ซักประวัติโรคประจำตัว แล้วเลือกกลุ่มสี <b>เขียว / เหลือง / แดง</b></li>
                        <li>เคสสีเหลืองและสีแดงจะพาเข้าหน้าประเมิน MEWS ต่ออัตโนมัติ</li>
                        <li>กดปุ่ม <b>ซิงค์สิทธิ &amp; HN ทั้งผลัด</b> เพื่อดึงข้อมูลล่าสุดจาก HosXP</li>
                    </ol>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400 font-semibold"><i class="fa-solid fa-file-csv text-teal-500 mr-1"></i>ส่งออก Excel/CSV ได้</span>
                    <a href="{{ route('backend.recruit_screenings.dashboard') }}" class="text-xs font-black text-teal-600 hover:text-teal-700 inline-flex items-center gap-1">
                        เปิดหน้าคัดกรอง <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- การ์ด 3 (เพิ่มใหม่): จุดคัดกรองผู้ป่วย (Triage Queue) & พิมพ์ใบรับรอง MEWS -->
            <div data-roles="screening er admin" class="help-guide-card bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border-2 border-amber-200/90 dark:border-amber-800/70 hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700">จุดคัดกรอง (Triage)</span>
                    </div>
                    <h4 class="font-black text-slate-800 dark:text-white text-base mb-2">3. จุดคัดกรองผู้ป่วย &amp; พิมพ์ใบรับรอง MEWS</h4>
                    <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 list-decimal list-inside leading-relaxed">
                        <li>เข้าเมนู <b>จุดคัดกรอง (Triage)</b> คิวจะเรียงผู้ป่วยคะแนน <b>MEWS สูงสุดขึ้นอันดับ 1</b></li>
                        <li>ระบบเด้งแจ้งเตือนทันทีสำหรับ <b>ประเมิน MEWS รอบแรกทุกเคส</b> (รอบถัดไปไม่เด้งซ้ำ)</li>
                        <li>แตะการ์ดสีเพื่อคัดแยกดูเคสฉุกเฉิน, เฝ้าระวัง หรือปกติ (ส่ง OPD) และกดประเมินซ้ำได้</li>
                        <li>กด <b>พิมพ์ใบ MEWS</b> จะมีช่องจุดไข่ปลาและคอลัมน์ <b>"จุดคัดกรอง"</b> สำหรับเขียนรับรองด้วยมือ</li>
                    </ol>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400 font-semibold"><i class="fa-solid fa-print text-amber-500 mr-1"></i>รองรับช่องเขียนมือ A4</span>
                    <a href="{{ route('backend.dashboard', ['view' => 'screening']) }}" class="text-xs font-black text-amber-600 hover:text-amber-700 inline-flex items-center gap-1">
                        เปิดหน้าจุดคัดกรอง <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- การ์ด 4: ประเมิน MEWS & รับเคสห้องฉุกเฉิน -->
            <div data-roles="screening medic er" class="help-guide-card bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700 hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-heart-pulse"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-rose-50 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">เสนารักษ์ &amp; ห้อง ER</span>
                    </div>
                    <h4 class="font-black text-slate-800 dark:text-white text-base mb-2">4. ประเมิน MEWS, รับแจ้งเตือน &amp; จบเคส</h4>
                    <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 list-decimal list-inside leading-relaxed">
                        <li><b>เสนารักษ์:</b> คีย์ V/S และอาการฉุกเฉิน ระบบคำนวณสีและแจ้งเตือนรอบแรกทันที</li>
                        <li><b>ห้อง ER / OPD:</b> รับเสียงเตือน + Web Push กดเปิดแฟ้มเพื่อ <b>ประเมินซ้ำ</b></li>
                        <li><b>จบเคส:</b> เลือกจำหน่ายกลับหน่วย / Admit / Refer พร้อมระบุเตียงหรือ รพ.</li>
                        <li>เมื่อผู้ป่วยออกจาก รพ. ให้กดบันทึก <b>กลับหน่วยฝึกแล้ว</b> เพื่ออัปเดตยอด</li>
                    </ol>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400 font-semibold"><i class="fa-solid fa-bell text-rose-500 mr-1"></i>แจ้งเตือนผ่านกระดิ่ง &amp; Discord</span>
                    <a href="{{ route('backend.dashboard') }}" class="text-xs font-black text-rose-600 hover:text-rose-700 inline-flex items-center gap-1">
                        ดูคิวเคสปัจจุบัน <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- การ์ด 5: ห้องพยาบาลหน่วยฝึก & ตารางยา e-MAR -->
            <div data-roles="medic er admin" class="help-guide-card bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700 hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-bed-pulse"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">เสนารักษ์ &amp; พยาบาล</span>
                    </div>
                    <h4 class="font-black text-slate-800 dark:text-white text-base mb-2">5. ห้องพยาบาลแยกนอน (CI) &amp; ระบบยา e-MAR</h4>
                    <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 list-decimal list-inside leading-relaxed">
                        <li>ลงทะเบียนพลทหารที่แยกนอนห้องพยาบาลหน่วยฝึก หรือฝากนอน รพ.ค่ายฯ</li>
                        <li>เพิ่มรายการยาในตาราง <b>e-MAR</b> รองรับมื้อปกติ, เวลามีอาการ (PRN) และยาทา</li>
                        <li>ติ๊กเช็กการกินยาแต่ละมื้อ และบันทึกสัญญาณชีพรายวัน (06:00 / 18:00)</li>
                        <li>ใช้งานได้สะดวกทั้งโหมดการ์ดมือถือและสั่งพิมพ์ใบตาราง A4</li>
                    </ol>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400 font-semibold"><i class="fa-solid fa-pills text-indigo-500 mr-1"></i>รองรับเคสจิตเวช &amp; โรคเรื้อรัง</span>
                    <a href="{{ route('backend.recruit_infirmary.index') }}" class="text-xs font-black text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1">
                        เปิดห้องพยาบาล <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- การ์ด 6: รายงานเจ็บป่วยประจำวัน & เอกสารนำปลด -->
            <div data-roles="er admin" class="help-guide-card bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700 hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-file-waveform"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-purple-50 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">พยาบาล / ผู้บริหาร</span>
                    </div>
                    <h4 class="font-black text-slate-800 dark:text-white text-base mb-2">6. รายงานเจ็บป่วยประจำวัน (JPG) &amp; นำปลด</h4>
                    <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 list-decimal list-inside leading-relaxed">
                        <li>สรุปครบ 5 หมวด: ทางเดินหายใจ, ความร้อน, สุขภาพจิต, นอน รพ., นำปลด</li>
                        <li>กดปุ่ม <b>ดึง Dx. ล่าสุดจาก HosXP</b> เพื่อลดการพิมพ์ชื่อโรคผิดพลาด</li>
                        <li>กด <b>บันทึกภาพสไลด์ (JPG)</b> เพื่อส่งรายงานผู้บังคับบัญชาทาง LINE ทันที</li>
                        <li>จัดการสถานะเอกสารนำปลด (ปลดทางกาย / สุขภาพจิต) ในทะเบียนนำปลด</li>
                    </ol>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400 font-semibold"><i class="fa-solid fa-image text-purple-500 mr-1"></i>ส่งออกสไลด์คมชัดสูง</span>
                    <a href="{{ route('backend.morbidity_reports.index') }}" class="text-xs font-black text-purple-600 hover:text-purple-700 inline-flex items-center gap-1">
                        เปิดรายงานประจำวัน <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

            <!-- การ์ด 7: นัดพบแพทย์, KSVR AI & การติดตั้งแอป PWA -->
            <div data-roles="screening medic er admin" class="help-guide-card bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700 hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">ทุกตำแหน่ง</span>
                    </div>
                    <h4 class="font-black text-slate-800 dark:text-white text-base mb-2">7. คิวนัดพบแพทย์, AI วิเคราะห์ &amp; แอปมือถือ</h4>
                    <ol class="text-xs text-slate-600 dark:text-slate-300 space-y-1.5 list-decimal list-inside leading-relaxed">
                        <li>ระบบแจ้งเตือนคิวนัดพบแพทย์ล่วงหน้า 1 วัน และวันนัดจริงทันทีที่เข้าระบบ</li>
                        <li>กดปุ่ม <b>หุ่นยนต์ AI มุมขวาล่าง</b> เพื่อถามสรุปยอด Admit, คัดกรอง, ยา e-MAR</li>
                        <li>เปิดแจ้งเตือนใน <b>ไอคอนกระดิ่ง</b> เพื่อรับ Web Push แม้ล็อกหน้าจอมือถือ</li>
                        <li>ติดตั้งเป็นแอป PWA (iOS/Android) เพื่อใช้งานเต็มจอไร้แถบ URL</li>
                    </ol>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400 font-semibold"><i class="fa-solid fa-mobile-screen text-sky-500 mr-1"></i>รองรับ iOS &amp; Android</span>
                    <a href="{{ route('backend.recruit_appointments.index') }}" class="text-xs font-black text-sky-600 hover:text-sky-700 inline-flex items-center gap-1">
                        ดูตารางนัดแพทย์ <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    function filterHelpCards(role, clickedBtn) {
        const cards = document.querySelectorAll('.help-guide-card');
        const buttons = document.querySelectorAll('.help-filter-btn');

        buttons.forEach(btn => {
            btn.className = 'help-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-indigo-400 transition-all';
        });
        if (clickedBtn) {
            clickedBtn.className = 'help-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-black bg-slate-800 dark:bg-indigo-600 text-white shadow-sm transition-all';
        }

        cards.forEach(card => {
            const roles = (card.getAttribute('data-roles') || '').split(' ');
            if (role === 'all' || roles.includes(role)) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }
</script>
@endsection