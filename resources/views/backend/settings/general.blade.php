@extends('layouts.backend')

@section('title', 'ตั้งค่าระบบทั่วไป (System Settings) - KSVR Hospital')
@section('page_header', 'ตั้งค่าระบบทั่วไป (System Settings)')

@section('content')
@php
    $tabs = [
        'general'       => ['label' => 'ทั่วไป & ผลัดทหาร',     'icon' => 'fa-sliders',          'color' => 'emerald'],
        'notifications' => ['label' => 'แจ้งเตือน & โรคระบาด',   'icon' => 'fa-bell',             'color' => 'amber'],
        'hosxp'         => ['label' => 'ความปลอดภัย HosXP',      'icon' => 'fa-database',         'color' => 'indigo'],
        'clinical'      => ['label' => 'เกณฑ์คลินิก & PDPA',     'icon' => 'fa-shield-halved',    'color' => 'rose'],
    ];
@endphp

<style>
    .ksvr-no-scrollbar::-webkit-scrollbar { display: none; }
    .ksvr-no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div class="max-w-5xl mx-auto space-y-5 pb-12">

    <!-- 🧭 1. แถบหัวเรื่อง + เมนูแท็บ 4 หมวด (ปัดเลื่อนซ้าย-ขวาได้บนมือถือ) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-5 shadow-sm border border-slate-100 dark:border-slate-700 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-slate-900 dark:bg-indigo-600 text-white flex items-center justify-center text-lg shadow-sm shrink-0">
                    <i class="fa-solid fa-gear"></i>
                </div>
                <div>
                    <h2 class="text-base sm:text-lg font-black text-slate-800 dark:text-white">ศูนย์ควบคุมการตั้งค่าระบบ (System Settings)</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">ปรับแต่งพารามิเตอร์ระบบได้ทันทีโดยไม่ต้องแก้โค้ด ทุกการเปลี่ยนแปลงถูกบันทึกใน Audit Log อัตโนมัติ</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 self-start sm:self-center shrink-0">
                <i class="fa-solid fa-bolt"></i> Cache-Optimized
            </span>
        </div>

        <div class="ksvr-no-scrollbar flex flex-nowrap sm:flex-wrap items-center gap-2 overflow-x-auto pb-1 sm:pb-0 pt-2 border-t border-slate-100 dark:border-slate-700">
            @foreach($tabs as $tKey => $tMeta)
                @php $isAct = ($activeTab === $tKey); @endphp
                <a href="{{ route('backend.settings.general.index', ['tab' => $tKey]) }}"
                   class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold whitespace-nowrap border transition-all inline-flex items-center gap-2 shrink-0 {{ $isAct ? 'bg-slate-900 dark:bg-indigo-600 text-white border-slate-900 dark:border-indigo-500 shadow-md' : 'bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                    <i class="fa-solid {{ $tMeta['icon'] }}"></i>
                    <span>{{ $tMeta['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <!-- ⚠️ แจ้งเตือน Validation Error (ถ้ามี) -->
    @if($errors->any())
        <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 p-4 rounded-2xl text-xs sm:text-sm space-y-1">
            <div class="font-black"><i class="fa-solid fa-circle-exclamation mr-1"></i>กรุณาตรวจสอบข้อมูลต่อไปนี้:</div>
            <ul class="list-disc list-inside space-y-0.5 font-medium">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- 📝 2. ฟอร์มตั้งค่าแยกตามแท็บที่เลือก -->
    <!-- ===================================================================== -->
    <form action="{{ route('backend.settings.general.update') }}" method="POST" class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        @csrf
        <input type="hidden" name="tab" value="{{ $activeTab }}">

        <div class="p-5 sm:p-7 space-y-6">

            {{-- ======================== TAB 1: ทั่วไป & ผลัดทหาร ======================== --}}
            @if($activeTab === 'general')
                <div class="space-y-1 border-b border-slate-100 dark:border-slate-700 pb-3">
                    <h3 class="text-base font-black text-slate-800 dark:text-white"><i class="fa-solid fa-sliders text-emerald-500 mr-2"></i>ตั้งค่าทั่วไป, ผลัดทหารหลัก และแถบประกาศระบบ</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">กำหนดผลัดทหารปัจจุบันให้เป็นค่าเริ่มต้นของระบบ และเปิดประกาศแจ้งเตือนเจ้าหน้าที่ได้จากจุดเดียว</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">ชื่อโรงพยาบาล / หน่วยงาน <span class="text-rose-500">*</span></label>
                        <input type="text" name="hospital_name" value="{{ old('hospital_name', $settings['hospital_name']) }}" required
                               class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 focus:bg-white text-sm font-bold text-slate-800 dark:text-white outline-none focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">ผลัดทหารหลักที่กำลังฝึกอยู่ (Active Batch) <span class="text-rose-500">*</span></label>
                        <input type="text" id="input_active_batch" name="active_intake_batch" value="{{ old('active_intake_batch', $settings['active_intake_batch']) }}" required placeholder="เช่น 2/69, 1/70"
                               class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 focus:bg-white text-sm font-black text-emerald-700 dark:text-emerald-400 outline-none focus:border-emerald-500">
                        @if($existingBatches->count() > 0)
                            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                <span class="text-[11px] font-semibold text-slate-400">ผลัดที่มีในระบบ:</span>
                                @foreach($existingBatches->take(6) as $b)
                                    <button type="button" onclick="document.getElementById('input_active_batch').value='{{ $b }}'"
                                            class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-emerald-50 text-slate-600 dark:text-slate-300 hover:text-emerald-700 text-[11px] font-bold border border-slate-200 dark:border-slate-600 transition-colors">
                                        ผลัด {{ $b }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- แถบประกาศระบบ (System Announcement) -->
                <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-900/50 border border-slate-200/80 dark:border-slate-700 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-black text-sm text-slate-800 dark:text-white"><i class="fa-solid fa-bullhorn text-amber-500 mr-1.5"></i>แถบประกาศแจ้งเตือนด้านบนระบบ (System Banner)</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ใช้แจ้งปิดปรับปรุงระบบ หรือประกาศสำคัญให้เจ้าหน้าที่ทุกคนเห็นทันที</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" name="announcement_enabled" value="1" {{ old('announcement_enabled', $settings['announcement_enabled']) ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-12 h-6 bg-slate-300 dark:bg-slate-700 rounded-full peer peer-checked:bg-emerald-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-6"></div>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">ระดับสีแถบประกาศ</label>
                            <select name="announcement_type" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-200 outline-none">
                                <option value="info" {{ old('announcement_type', $settings['announcement_type']) === 'info' ? 'selected' : '' }}>🔵 ทั่วไป (สีฟ้า)</option>
                                <option value="warning" {{ old('announcement_type', $settings['announcement_type']) === 'warning' ? 'selected' : '' }}>🟡 แจ้งเตือน (สีส้ม)</option>
                                <option value="danger" {{ old('announcement_type', $settings['announcement_type']) === 'danger' ? 'selected' : '' }}>🔴 ด่วนมาก (สีแดง)</option>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">ข้อความประกาศ</label>
                            <input type="text" name="announcement_message" value="{{ old('announcement_message', $settings['announcement_message']) }}"
                                   placeholder="เช่น แจ้งปิดปรับปรุงเซิร์ฟเวอร์ HosXP คืนนี้เวลา 23.00 - 23.30 น."
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm font-medium text-slate-800 dark:text-white outline-none focus:border-emerald-500">
                        </div>
                    </div>
                </div>

            {{-- ======================== TAB 2: แจ้งเตือน & โรคระบาด ======================== --}}
            @elseif($activeTab === 'notifications')
                <div class="space-y-1 border-b border-slate-100 dark:border-slate-700 pb-3">
                    <h3 class="text-base font-black text-slate-800 dark:text-white"><i class="fa-solid fa-bell text-amber-500 mr-2"></i>ตั้งค่า Discord Webhook และเกณฑ์เฝ้าระวังโรคระบาด</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">แยกช่องทางแจ้งเตือนและทดสอบยิงข้อความเข้า Discord ได้โดยตรงโดยไม่ต้องแก้ไฟล์ .env</p>
                </div>

                <!-- สวิตช์เปิด-ปิด Discord -->
                <div class="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200/80 dark:border-indigo-800 flex items-center justify-between gap-3">
                    <div>
                        <div class="font-black text-sm text-indigo-900 dark:text-indigo-200"><i class="fa-brands fa-discord text-indigo-600 mr-1.5"></i>เปิดใช้งานการส่งแจ้งเตือนเข้า Discord</div>
                        <div class="text-xs text-indigo-700/80 dark:text-indigo-300/80 mt-0.5">หากปิดชั่วคราว (เช่น ตอนซ้อมแผนหรือกรอกข้อมูลทดสอบ) ระบบจะระงับการยิง Webhook ทันที</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="discord_enabled" value="1" {{ old('discord_enabled', $settings['discord_enabled']) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-12 h-6 bg-slate-300 dark:bg-slate-700 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-6"></div>
                    </label>
                </div>

                <!-- Webhook ช่อง ER & ผู้บริหาร -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Discord Webhook URL — ช่องห้องฉุกเฉิน (ER / จุดคัดกรอง MEWS)</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="url" id="discord_webhook_er" name="discord_webhook_er" value="{{ old('discord_webhook_er', $settings['discord_webhook_er']) }}"
                                   placeholder="https://discord.com/api/webhooks/..."
                                   class="flex-1 px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-xs sm:text-sm font-mono text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500">
                            <button type="button" onclick="testDiscordWebhook('discord_webhook_er', 'ห้อง ER / MEWS', this)"
                                    class="px-4 py-2.5 rounded-2xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-colors shrink-0">
                                <i class="fa-solid fa-paper-plane"></i> ทดสอบส่ง
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Discord Webhook URL — ช่องผู้บริหาร / แจ้งเตือนโรคระบาด (Outbreak Radar)</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="url" id="discord_webhook_exec" name="discord_webhook_exec" value="{{ old('discord_webhook_exec', $settings['discord_webhook_exec']) }}"
                                   placeholder="https://discord.com/api/webhooks/..."
                                   class="flex-1 px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-xs sm:text-sm font-mono text-slate-700 dark:text-slate-200 outline-none focus:border-indigo-500">
                            <button type="button" onclick="testDiscordWebhook('discord_webhook_exec', 'ผู้บริหาร / โรคระบาด', this)"
                                    class="px-4 py-2.5 rounded-2xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-colors shrink-0">
                                <i class="fa-solid fa-paper-plane"></i> ทดสอบส่ง
                            </button>
                        </div>
                    </div>
                </div>

                <!-- เกณฑ์เรดาร์โรคระบาด -->
                <div class="p-4 sm:p-5 rounded-2xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/70 dark:border-rose-900 space-y-3">
                    <div class="font-black text-sm text-rose-800 dark:text-rose-300"><i class="fa-solid fa-virus-covid mr-1.5"></i>เกณฑ์ตรวจจับกลุ่มเสี่ยงระบาด (Outbreak Radar)</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">เกณฑ์อุณหภูมิไข้สูง (°C)</label>
                            <input type="number" step="0.1" min="36.5" max="41.0" name="outbreak_temp_threshold" value="{{ old('outbreak_temp_threshold', $settings['outbreak_temp_threshold']) }}" required
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm font-black text-rose-600 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">จำนวนป่วยสะสมต่อหน่วย (นาย)</label>
                            <input type="number" min="1" max="50" name="outbreak_min_cases" value="{{ old('outbreak_min_cases', $settings['outbreak_min_cases']) }}" required
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm font-black text-slate-800 dark:text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">กรอบเวลาเฝ้าระวัง (ชั่วโมง)</label>
                            <input type="number" min="6" max="168" name="outbreak_window_hours" value="{{ old('outbreak_window_hours', $settings['outbreak_window_hours']) }}" required
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm font-black text-slate-800 dark:text-white outline-none">
                        </div>
                    </div>
                </div>

            {{-- ======================== TAB 3: ความปลอดภัย HosXP ======================== --}}
            @elseif($activeTab === 'hosxp')
                <div class="space-y-1 border-b border-slate-100 dark:border-slate-700 pb-3">
                    <h3 class="text-base font-black text-slate-800 dark:text-white"><i class="fa-solid fa-database text-indigo-500 mr-2"></i>ควบคุมความปลอดภัยการเชื่อมต่อ HosXP (HIS Safety)</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">ป้องกันไม่ให้การดึงข้อมูลจากฝั่ง KSVR ส่งผลกระทบต่อประสิทธิภาพของฐานข้อมูลหลักโรงพยาบาล</p>
                </div>

                <!-- กล่องทดสอบการเชื่อมต่อ HosXP Health Check -->
                <div class="p-4 sm:p-5 rounded-2xl bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[10px] font-black uppercase">Read-Only Mode</span>
                            <span class="font-black text-sm">HosXP Connection Health Check</span>
                        </div>
                        <div class="text-xs text-slate-300 font-mono">
                            Host: <b class="text-white">{{ $hosxpInfo['host'] }}:{{ $hosxpInfo['port'] }}</b> • DB: <b class="text-emerald-300">{{ $hosxpInfo['database'] }}</b>
                        </div>
                        <div id="hosxp-health-result" class="text-xs font-bold text-slate-400 pt-1">
                            กดปุ่มด้านขวาเพื่อทดสอบการเชื่อมต่อ (ยิงเฉพาะ SELECT 1 ไม่สแกนตารางผู้ป่วย)
                        </div>
                    </div>

                    <button type="button" id="btn-test-hosxp" onclick="runHosxpHealthCheck()"
                            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-black shadow-md inline-flex items-center justify-center gap-2 transition-all shrink-0">
                        <i class="fa-solid fa-plug-circle-check" id="icon-test-hosxp"></i> ทดสอบเชื่อมต่อ HosXP
                    </button>
                </div>

                <!-- สวิตช์พักการซิงค์ชั่วคราว -->
                <div class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border-2 border-amber-200 dark:border-amber-800 flex items-center justify-between gap-3">
                    <div>
                        <div class="font-black text-sm text-amber-900 dark:text-amber-200"><i class="fa-solid fa-pause-circle text-amber-600 mr-1.5"></i>พักการซิงค์ข้อมูล HosXP ชั่วคราว (Maintenance Pause)</div>
                        <div class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5">เปิดสวิตช์นี้เมื่อศูนย์คอมฯ กำลัง Backup หรือปิดปรับปรุงฐานข้อมูล HosXP เพื่อป้องกันหน้าเว็บค้าง</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="hosxp_sync_paused" value="1" {{ old('hosxp_sync_paused', $settings['hosxp_sync_paused']) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-12 h-6 bg-slate-300 dark:bg-slate-700 rounded-full peer peer-checked:bg-amber-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-6"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Connection Timeout (วินาที)</label>
                        <input type="number" min="2" max="30" name="hosxp_timeout_seconds" value="{{ old('hosxp_timeout_seconds', $settings['hosxp_timeout_seconds']) }}" required
                               class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-sm font-black text-indigo-700 dark:text-indigo-400 outline-none">
                        <p class="text-[11px] text-slate-400 mt-1">แนะนำ 5 วินาที เพื่อไม่ให้ PHP-FPM รอค้างนานหากเครือข่ายสะดุด</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">จำนวนสูงสุดต่อการซิงค์แบบกลุ่ม (ราย/ครั้ง)</label>
                        <input type="number" min="10" max="200" name="hosxp_max_batch_size" value="{{ old('hosxp_max_batch_size', $settings['hosxp_max_batch_size']) }}" required
                               class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-sm font-black text-indigo-700 dark:text-indigo-400 outline-none">
                        <p class="text-[11px] text-slate-400 mt-1">แนะนำ 50 ราย เพื่อคุมขนาด Batch Query (`WHERE cid IN (...)`) ให้เบาเครื่อง HIS</p>
                    </div>
                </div>

            {{-- ======================== TAB 4: เกณฑ์คลินิก & PDPA ======================== --}}
            @elseif($activeTab === 'clinical')
                <div class="space-y-1 border-b border-slate-100 dark:border-slate-700 pb-3">
                    <h3 class="text-base font-black text-slate-800 dark:text-white"><i class="fa-solid fa-shield-halved text-rose-500 mr-2"></i>เกณฑ์เวลาคลินิก (Clinical SLA) และนโยบายข้อมูลสุขภาพ PDPA</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">ปรับรอบเวลาแจ้งเตือนทางการพยาบาลและกำหนดสิทธิ์การเข้าถึงข้อมูลสุขภาพอ่อนไหว</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">🔴 SLA วัดสัญญาณชีพซ้ำใน ER — สีแดง (นาที)</label>
                        <input type="number" min="5" max="120" name="er_sla_red_minutes" value="{{ old('er_sla_red_minutes', $settings['er_sla_red_minutes']) }}" required
                               class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-sm font-black text-rose-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">🟡 SLA วัดสัญญาณชีพซ้ำใน ER — สีเหลือง (นาที)</label>
                        <input type="number" min="10" max="240" name="er_sla_yellow_minutes" value="{{ old('er_sla_yellow_minutes', $settings['er_sla_yellow_minutes']) }}" required
                               class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-sm font-black text-amber-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">📅 แจ้งเตือนคิวนัดพบแพทย์ล่วงหน้า (วัน)</label>
                        <input type="number" min="1" max="7" name="appointment_alert_days" value="{{ old('appointment_alert_days', $settings['appointment_alert_days']) }}" required
                               class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-sm font-black text-sky-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">🔄 รอบเช็กแจ้งเตือนเบื้องหลัง (วินาที)</label>
                        <input type="number" min="15" max="300" name="polling_interval_seconds" value="{{ old('polling_interval_seconds', $settings['polling_interval_seconds']) }}" required
                               class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-sm font-black text-slate-800 dark:text-white outline-none">
                        <p class="text-[11px] text-slate-400 mt-1">แนะนำ 30–60 วินาที เพื่อประหยัด CPU/RAM ของเซิร์ฟเวอร์</p>
                    </div>
                </div>

                <!-- สิทธิ์การเห็นข้อมูลโรคประจำตัว (PDPA) -->
                @php $allowedRoles = old('pdpa_chronic_roles', $settings['pdpa_chronic_roles'] ?? ['admin']); @endphp
                <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-900/50 border border-slate-200/80 dark:border-slate-700 space-y-3">
                    <div>
                        <div class="font-black text-sm text-slate-800 dark:text-white"><i class="fa-solid fa-user-lock text-indigo-500 mr-1.5"></i>สิทธิ์การมองเห็นข้อมูลโรคประจำตัว / สุขภาพจิต (PDPA Policy)</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">เลือกบทบาทที่อนุญาตให้ดูรายละเอียดโรคประจำตัวของพลทหารได้</div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2">
                        @foreach($roleLabels as $rKey => $rText)
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 cursor-pointer has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/60 dark:has-[:checked]:bg-indigo-900/30 transition-all">
                                <input type="checkbox" name="pdpa_chronic_roles[]" value="{{ $rKey }}"
                                       {{ in_array($rKey, (array)$allowedRoles, true) ? 'checked' : '' }}
                                       {{ $rKey === 'admin' ? 'onclick="return false;"' : '' }}
                                       class="w-4 h-4 text-indigo-600 rounded">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 truncate">{{ $rText }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- ⏱️ นโยบายกำหนดเวลาล็อกเอาต์อัตโนมัติ (Auto-Logout / Idle Timeout) -->
                @php
                    $idleTimeoutPc     = old('idle_timeout_pc', $settings['idle_timeout_pc'] ?? config('session.idle_timeout_pc', 30));
                    $idleTimeoutMobile = old('idle_timeout_mobile', $settings['idle_timeout_mobile'] ?? config('session.idle_timeout_mobile', 720));
                    $idleExemptRoles   = old('idle_timeout_exempt_roles', $settings['idle_timeout_exempt_roles'] ?? ['er_nurse']);
                    $serverSessionMax  = (int) config('session.lifetime', 720);
                @endphp
                <div class="p-4 sm:p-5 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-900/70 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="font-black text-sm text-slate-800 dark:text-white">
                                <i class="fa-solid fa-hourglass-half text-amber-500 mr-1.5"></i>กำหนดเวลาล็อกเอาต์อัตโนมัติเมื่อไม่มีการใช้งาน (Auto-Logout)
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                ป้องกันการเปิดหน้าจอข้อมูลผู้ป่วยทิ้งไว้บนเครื่องส่วนกลาง (ใส่ <b>0</b> หากต้องการปิดการตัดออกอัตโนมัติ)
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-amber-200 dark:border-amber-800 text-[11px] font-bold text-amber-700 dark:text-amber-300 shrink-0 self-start sm:self-center">
                            <i class="fa-solid fa-server text-[10px]"></i> เพดาน Session เซิร์ฟเวอร์: {{ $serverSessionMax }} นาที
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- เวลาล็อกเอาต์ฝั่งคอมพิวเตอร์ (PC) --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                <i class="fa-solid fa-desktop text-slate-400 mr-1"></i> คอมพิวเตอร์ทั่วไป / PC (นาที) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" id="input_idle_pc" min="0" max="1440" name="idle_timeout_pc" value="{{ $idleTimeoutPc }}" required
                                   class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm font-black text-amber-600 dark:text-amber-400 outline-none focus:border-amber-500">
                            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                <span class="text-[11px] font-semibold text-slate-400">เลือกด่วน:</span>
                                @foreach([15 => '15 นาที', 30 => '30 นาที', 60 => '1 ชม.', 0 => 'ไม่ตัดออก'] as $minVal => $minLabel)
                                    <button type="button" onclick="document.getElementById('input_idle_pc').value='{{ $minVal }}'"
                                            class="px-2 py-0.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-amber-50 dark:hover:bg-amber-900/40 text-slate-600 dark:text-slate-300 hover:text-amber-700 text-[11px] font-bold border border-slate-200 dark:border-slate-700 transition-colors">
                                        {{ $minLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- เวลาล็อกเอาต์ฝั่งมือถือ / แท็บเล็ต --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                                <i class="fa-solid fa-mobile-screen-button text-slate-400 mr-1"></i> มือถือ / แท็บเล็ต / PWA (นาที) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" id="input_idle_mobile" min="0" max="1440" name="idle_timeout_mobile" value="{{ $idleTimeoutMobile }}" required
                                   class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm font-black text-emerald-600 dark:text-emerald-400 outline-none focus:border-emerald-500">
                            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                <span class="text-[11px] font-semibold text-slate-400">เลือกด่วน:</span>
                                @foreach([120 => '2 ชม.', 480 => '8 ชม.', 720 => '12 ชม. (1 เวร)', 0 => 'ไม่ตัดออก'] as $minVal => $minLabel)
                                    <button type="button" onclick="document.getElementById('input_idle_mobile').value='{{ $minVal }}'"
                                            class="px-2 py-0.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/40 text-slate-600 dark:text-slate-300 hover:text-emerald-700 text-[11px] font-bold border border-slate-200 dark:border-slate-700 transition-colors">
                                        {{ $minLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- บทบาทที่ได้รับยกเว้นไม่ต้องล็อกเอาต์อัตโนมัติ (เช่น จอมอนิเตอร์ ER) --}}
                    <div class="pt-3 border-t border-amber-200/70 dark:border-amber-900/60 space-y-2.5">
                        <div>
                            <div class="text-xs font-black text-slate-700 dark:text-slate-200">
                                <i class="fa-solid fa-tv text-emerald-500 mr-1"></i> บทบาทที่ยกเว้นการล็อกเอาต์อัตโนมัติ (โหมดเปิดหน้าจอค้าง / Heartbeat)
                            </div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                บทบาทที่ติ๊กเลือกจะเลี้ยง Session ให้อัตโนมัติทุก 10 นาที เหมาะสำหรับหน้าจอเฝ้าระวังแผนก ER
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2">
                            @foreach($roleLabels as $rKey => $rText)
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/60 dark:has-[:checked]:bg-emerald-900/30 transition-all">
                                    <input type="checkbox" name="idle_timeout_exempt_roles[]" value="{{ $rKey }}"
                                           {{ in_array($rKey, (array) $idleExemptRoles, true) ? 'checked' : '' }}
                                           class="w-4 h-4 text-emerald-600 rounded">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200 truncate">{{ $rText }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>

        <!-- แถบปุ่มบันทึกด้านล่าง -->
        <div class="px-5 sm:px-7 py-4 bg-slate-50/70 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <span class="text-xs text-slate-400 font-medium">
                <i class="fa-solid fa-circle-info mr-1"></i>เมื่อกดบันทึก ค่าใหม่จะมีผลทันทีและล้างแคชให้อัตโนมัติ
            </span>
            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-sm font-black shadow-md transition-all inline-flex items-center justify-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> บันทึกการตั้งค่าแท็บนี้
            </button>
        </div>
    </form>

</div>
@endsection

@section('scripts')
<script>
    // 🏥 ทดสอบเชื่อมต่อ HosXP (Read-Only SELECT 1)
    function runHosxpHealthCheck() {
        const btn = document.getElementById('btn-test-hosxp');
        const icon = document.getElementById('icon-test-hosxp');
        const out = document.getElementById('hosxp-health-result');
        if (btn) btn.disabled = true;
        if (icon) icon.className = 'fa-solid fa-spinner fa-spin';
        if (out) out.innerHTML = '<span class="text-amber-300">กำลังทดสอบการเชื่อมต่อกับฐานข้อมูล HosXP...</span>';

        fetch("{{ route('backend.settings.general.test_hosxp') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                if (out) out.innerHTML = `<span class="text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i>${res.message} (MySQL: ${res.db_version})</span>`;
            } else {
                if (out) out.innerHTML = `<span class="text-rose-400"><i class="fa-solid fa-circle-xmark mr-1"></i>${res.message}</span>`;
            }
        })
        .catch(() => {
            if (out) out.innerHTML = '<span class="text-rose-400"><i class="fa-solid fa-triangle-exclamation mr-1"></i>การเชื่อมต่อขัดข้อง หรือหมดเวลาเชื่อมต่อ (Timeout)</span>';
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.className = 'fa-solid fa-plug-circle-check';
        });
    }

    // 🔔 ทดสอบส่งข้อความเข้า Discord Webhook
    function testDiscordWebhook(inputId, channelName, btnEl) {
        const url = document.getElementById(inputId)?.value.trim();
        if (!url) {
            Swal.fire({ icon: 'warning', title: 'ยังไม่ได้กรอก Webhook URL', text: 'กรุณาวางลิงก์ Discord Webhook ก่อนกดทดสอบครับ', customClass: { popup: 'rounded-3xl' } });
            return;
        }

        const origHtml = btnEl.innerHTML;
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังส่ง...';

        fetch("{{ route('backend.settings.general.test_discord') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ webhook_url: url, channel_name: channelName })
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({
                icon: res.status === 'success' ? 'success' : 'error',
                title: res.status === 'success' ? 'ส่งทดสอบสำเร็จ!' : 'ส่งไม่สำเร็จ',
                text: res.message,
                customClass: { popup: 'rounded-3xl' }
            });
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'เชื่อมต่อไม่สำเร็จ', text: 'ไม่สามารถติดต่อไปยัง Discord ได้ในขณะนี้', customClass: { popup: 'rounded-3xl' } });
        })
        .finally(() => {
            btnEl.disabled = false;
            btnEl.innerHTML = origHtml;
        });
    }
</script>
@endsection