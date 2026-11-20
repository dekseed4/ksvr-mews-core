@extends('layouts.backend')

@section('title', 'คิวผู้ป่วยนอก (OPD) - KSVR Hospital')
@section('page_header', 'คิวรอตรวจแผนกผู้ป่วยนอก (OPD Queue)')

@section('content')
@php
    $safeCompletedOpd = $completedOpdToday ?? collect();
@endphp

<div class="max-w-7xl mx-auto space-y-6 pb-12">
    
    @include('backend.dashboards.partials.view_tabs')

    <!-- 🌟 ส่วนหัว (Header & Stats) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 md:p-8 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col md:flex-row items-start md:items-center justify-between gap-5 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 text-emerald-500/10 text-9xl pointer-events-none">
            <i class="fa-solid fa-stethoscope"></i>
        </div>

        <div class="flex items-center gap-4 relative z-10">
            <div class="w-14 h-14 md:w-16 md:h-16 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 rounded-2xl flex items-center justify-center text-2xl md:text-3xl shadow-inner border border-emerald-100 dark:border-emerald-800 shrink-0">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
            <div>
                <h2 class="text-xl md:text-2xl font-black text-slate-800 dark:text-white tracking-tight">คิวรอพบแพทย์ (OPD)</h2>
                <p class="text-xs md:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
                    แสดงเฉพาะผู้ป่วยที่มีระดับความเสี่ยงปกติ (สีเขียว) และไม่มีอาการฉุกเฉิน
                </p>
            </div>
        </div>

        <div class="relative z-10 grid grid-cols-2 gap-3 w-full md:w-auto">
            <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-4 md:px-5 py-3 rounded-2xl text-center">
                <p class="text-[11px] font-bold text-slate-400 uppercase mb-1">คิวรอตรวจขณะนี้</p>
                <div class="text-2xl md:text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ $opdQueue->count() }} <span class="text-xs md:text-sm font-bold text-slate-500">คิว</span></div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-4 md:px-5 py-3 rounded-2xl text-center">
                <p class="text-[11px] font-bold text-slate-400 uppercase mb-1">ตรวจเสร็จวันนี้</p>
                <div class="text-2xl md:text-3xl font-black text-teal-600 dark:text-teal-400">{{ $safeCompletedOpd->count() }} <span class="text-xs md:text-sm font-bold text-slate-500">ราย</span></div>
            </div>
        </div>
    </div>

    <!-- 🌟 1. คิวผู้ป่วยรอตรวจ (Mobile Cards + Desktop Table) -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 md:px-6 py-4 md:py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="text-sm md:text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-list-ol text-emerald-500 shrink-0"></i>
                <span>ลำดับคิวเรียงตามเวลาประเมิน (มาก่อน-ได้ตรวจก่อน)</span>
            </h3>
            <button onclick="window.location.reload();" class="text-slate-400 hover:text-emerald-500 transition-colors text-xs md:text-sm font-bold flex items-center gap-1.5 shrink-0">
                <i class="fa-solid fa-rotate-right"></i> รีเฟรช
            </button>
        </div>

        <!-- 📱 โหมดมือถือ: แสดงเป็นการ์ด อ่านง่ายไม่บีบตัวหนังสือ -->
        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700/70">
            @forelse($opdQueue as $index => $queue)
                @php
                    $pt = $queue->patient;
                    $fullName = ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');
                    $unitShort = $pt->militaryUnit->short_name ?? $pt->company ?? '-';
                @endphp
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-black text-sm flex items-center justify-center shrink-0 mt-0.5">
                                #{{ $index + 1 }}
                            </span>
                            <div class="min-w-0">
                                <div class="font-black text-slate-800 dark:text-white text-base leading-snug">
                                    {{ $fullName }}
                                </div>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    <span class="font-mono"><i class="fa-regular fa-id-card mr-1"></i>{{ $pt->cid }}</span>
                                    <span class="text-indigo-600 dark:text-indigo-400 font-bold">HN: {{ $pt->hn ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 text-center px-2.5 py-1 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800">
                            <div class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 uppercase leading-none">MEWS</div>
                            <div class="text-lg font-black text-emerald-700 dark:text-emerald-300 leading-tight">{{ $queue->total_score }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/50 px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-700/70">
                        <span class="font-bold">
                            <i class="fa-solid fa-tent text-emerald-500 mr-1"></i>{{ $unitShort }}
                            <span class="font-normal text-slate-400">(ผลัด {{ $pt->intake_batch ?? '-' }})</span>
                        </span>
                        <span class="font-semibold text-slate-500 dark:text-slate-400">
                            <i class="fa-regular fa-clock text-emerald-500 mr-1"></i>{{ \Carbon\Carbon::parse($queue->created_at)->format('H:i น.') }} ({{ \Carbon\Carbon::parse($queue->created_at)->locale('th')->diffForHumans() }})
                        </span>
                    </div>

                    @if(!empty($queue->symptoms_note))
                        <div class="text-xs leading-relaxed text-teal-800 dark:text-teal-200 bg-teal-50/90 dark:bg-teal-900/30 border border-teal-200/70 dark:border-teal-800 px-3 py-2.5 rounded-xl font-medium">
                            <div class="text-[10px] font-black text-teal-600 dark:text-teal-400 uppercase mb-0.5">
                                <i class="fa-solid fa-notes-medical mr-1"></i>อาการสำคัญ / บันทึกเพิ่มเติม
                            </div>
                            {{ $queue->symptoms_note }}
                        </div>
                    @endif

                    <div class="grid grid-cols-12 gap-2 pt-1">
                        <button type="button"
                                onclick="openOpdQuickDischargeModal('{{ route('backend.mews.discharge', $queue->id) }}', '{{ addslashes($fullName) }}')"
                                class="col-span-6 py-2.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition-all">
                            <i class="fa-solid fa-circle-check"></i> จบการรักษา
                        </button>
                        <a href="{{ route('backend.mews.show', $queue->id) }}"
                           class="col-span-4 py-2.5 px-2.5 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1 shadow-sm">
                            <i class="fa-solid fa-file-waveform text-indigo-500"></i> ดูเคส
                        </a>
                        <a href="{{ route('backend.patients.show', $queue->patient_id) }}"
                           class="col-span-2 py-2.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-200 rounded-xl flex items-center justify-center"
                           title="เปิดแฟ้มประวัติผู้ป่วย">
                            <i class="fa-solid fa-address-card text-sm"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="px-6 py-14 text-center">
                    <div class="w-16 h-16 bg-slate-50 dark:bg-slate-900 text-slate-300 dark:text-slate-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-3 border-2 border-dashed border-slate-200 dark:border-slate-700">
                        <i class="fa-solid fa-mug-hot"></i>
                    </div>
                    <h4 class="text-base font-bold text-slate-700 dark:text-slate-300 mb-1">เคลียร์คิวเรียบร้อย</h4>
                    <p class="text-slate-500 text-xs">ขณะนี้ไม่มีผู้ป่วยรอตรวจที่แผนกผู้ป่วยนอกครับ</p>
                </div>
            @endforelse
        </div>

        <!-- 💻 โหมดคอมพิวเตอร์: ตารางอ่านง่าย กำหนดสัดส่วนคอลัมน์ชัดเจน -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[860px] text-sm text-left">
                <thead class="text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 whitespace-nowrap">
                    <tr>
                        <th class="px-5 py-4 font-bold text-center w-16">คิวที่</th>
                        <th class="px-5 py-4 font-bold min-w-[210px]">ข้อมูลผู้ป่วย</th>
                        <th class="px-5 py-4 font-bold min-w-[260px]">สังกัดหน่วย / อาการสำคัญ</th>
                        <th class="px-4 py-4 font-bold text-center w-20">MEWS</th>
                        <th class="px-5 py-4 font-bold whitespace-nowrap">เวลารอคอย</th>
                        <th class="px-5 py-4 font-bold text-center whitespace-nowrap">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($opdQueue as $index => $queue)
                        @php
                            $pt = $queue->patient;
                            $fullName = ($pt->rank ?? '') . ($pt->first_name ?? '') . ' ' . ($pt->last_name ?? '');
                        @endphp
                        <tr class="hover:bg-emerald-50/50 dark:hover:bg-slate-700/50 transition-colors group">
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <span class="w-8 h-8 flex items-center justify-center bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-black rounded-full mx-auto">
                                    {{ $index + 1 }}
                                </span>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-800 dark:text-white text-base">
                                    {{ $fullName }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1 font-mono">
                                    <i class="fa-regular fa-id-card"></i> {{ $pt->cid }} |
                                    <span class="text-indigo-500 font-bold">HN: {{ $pt->hn ?? '-' }}</span>
                                </div>
                            </td>

                            <td class="px-5 py-4 max-w-sm">
                                <div class="text-slate-700 dark:text-slate-300 font-bold whitespace-nowrap">
                                    {{ $pt->militaryUnit->short_name ?? $pt->company ?? '-' }}
                                    <span class="text-xs font-normal text-slate-400 ml-1">(ผลัด {{ $pt->intake_batch ?? '-' }})</span>
                                </div>
                                @if(!empty($queue->symptoms_note))
                                    <div class="text-xs leading-relaxed text-teal-700 dark:text-teal-200 bg-teal-50 dark:bg-teal-900/30 border border-teal-100 dark:border-teal-800 px-2.5 py-1 rounded-lg mt-1.5 inline-block font-medium whitespace-normal">
                                        <i class="fa-solid fa-notes-medical mr-1"></i>{{ $queue->symptoms_note }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 font-black rounded-lg border border-emerald-200 dark:border-emerald-800">
                                    {{ $queue->total_score }}
                                </span>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-700 dark:text-slate-300">
                                    <i class="fa-regular fa-clock text-emerald-500 mr-1"></i> {{ \Carbon\Carbon::parse($queue->created_at)->locale('th')->diffForHumans() }}
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">
                                    ส่งตัวเมื่อ: {{ \Carbon\Carbon::parse($queue->created_at)->format('H:i น.') }}
                                </div>
                            </td>

                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button"
                                            onclick="openOpdQuickDischargeModal('{{ route('backend.mews.discharge', $queue->id) }}', '{{ addslashes($fullName) }}')"
                                            class="inline-flex items-center justify-center px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs transition-all shadow-sm hover:shadow-md gap-1.5">
                                        <i class="fa-solid fa-circle-check"></i> จบการรักษา
                                    </button>

                                    <a href="{{ route('backend.mews.show', $queue->id) }}"
                                       class="inline-flex items-center justify-center px-3 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-white hover:bg-slate-100 rounded-xl font-bold text-xs transition-all shadow-sm"
                                       title="ดูรายละเอียดการประเมิน MEWS">
                                        <i class="fa-solid fa-file-waveform mr-1 text-indigo-500"></i> ดูเคส
                                    </a>

                                    <a href="{{ route('backend.patients.show', $queue->patient_id) }}"
                                       class="inline-flex items-center justify-center w-8 h-8 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 hover:bg-indigo-600 hover:text-white rounded-xl transition-all"
                                       title="เปิดแฟ้มประวัติผู้ป่วย">
                                        <i class="fa-solid fa-address-card text-xs"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="w-20 h-20 bg-slate-50 dark:bg-slate-800 text-slate-300 dark:text-slate-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-4 border-2 border-dashed border-slate-200 dark:border-slate-700">
                                    <i class="fa-solid fa-mug-hot"></i>
                                </div>
                                <h4 class="text-lg font-bold text-slate-700 dark:text-slate-300 mb-1">เคลียร์คิวเรียบร้อย</h4>
                                <p class="text-slate-500 text-sm">ขณะนี้ไม่มีผู้ป่วยรอตรวจที่แผนกผู้ป่วยนอกครับ</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 🗄️ 2. ประวัติการจำหน่ายผู้ป่วยนอก / ยกเลิก (Mobile Cards + Desktop Table) -->
    <div class="bg-slate-50/80 dark:bg-slate-800/60 rounded-3xl p-5 md:p-8 border border-slate-200 dark:border-slate-700">
        <h3 class="text-base md:text-lg font-bold text-slate-800 dark:text-white mb-5 flex items-center gap-2 border-b border-slate-200 dark:border-slate-700 pb-3">
            <i class="fa-solid fa-clock-rotate-left text-teal-600 shrink-0"></i>
            <span>ประวัติการจำหน่ายผู้ป่วยนอก / ยกเลิก (OPD ประจำวัน)</span>
            <span class="bg-teal-100 text-teal-800 text-xs px-2.5 py-0.5 rounded-full font-bold ml-auto shrink-0">{{ $safeCompletedOpd->count() }} ราย</span>
        </h3>

        <!-- 📱 โหมดมือถือ -->
        <div class="md:hidden space-y-3">
            @forelse($safeCompletedOpd as $history)
                @php $isReturnedOpd = !empty($history->returned_to_unit_at); @endphp
                <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700 shadow-sm space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-white text-sm">
                                {{ $history->patient->rank ?? '' }}{{ $history->patient->first_name ?? '' }} {{ $history->patient->last_name ?? '' }}
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                {{ $history->patient->militaryUnit->short_name ?? $history->patient->company ?? '-' }} • {{ $history->updated_at->format('H:i น.') }}
                            </div>
                        </div>
                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 rounded-lg font-black text-xs shrink-0">
                            MEWS {{ $history->total_score }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <div>
                            @if($history->status === 'cancelled')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-bold text-[11px] border border-slate-200"><i class="fa-solid fa-ban"></i> ถูกยกเลิก</span>
                            @elseif($history->discharge_type === 'admitted')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg {{ $isReturnedOpd ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }} font-bold text-[11px] border">
                                    Admit {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} {{ $isReturnedOpd ? '• กลับหน่วยแล้ว' : '' }}
                                </span>
                            @elseif($history->discharge_type === 'refer')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg {{ $isReturnedOpd ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200' }} font-bold text-[11px] border">
                                    Refer {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} {{ $isReturnedOpd ? '• กลับหน่วยแล้ว' : '' }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-[11px] border border-emerald-200"><i class="fa-solid fa-house-chimney-medical"></i> จำหน่ายกลับหน่วย</span>
                            @endif
                        </div>
                        <a href="{{ route('backend.mews.show', $history->id) }}" class="text-teal-600 font-bold text-xs bg-teal-50 px-3 py-1.5 rounded-lg border border-teal-200 shrink-0">
                            <i class="fa-solid fa-folder-open mr-1"></i> ดูแฟ้ม
                        </a>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-slate-400 text-xs">ยังไม่มีประวัติการจำหน่ายผู้ป่วยนอก (OPD) ในวันนี้</div>
            @endforelse
        </div>

        <!-- 💻 โหมดคอมพิวเตอร์ -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[760px] text-left border-collapse">
                <thead>
                    <tr class="text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 whitespace-nowrap">
                        <th class="px-4 py-3 font-bold">เวลา</th>
                        <th class="px-4 py-3 font-bold">ชื่อ-นามสกุลผู้ป่วย</th>
                        <th class="px-4 py-3 font-bold">สังกัดหน่วย</th>
                        <th class="px-4 py-3 font-bold text-center">MEWS</th>
                        <th class="px-4 py-3 font-bold">สถานะ/ผลการรักษา</th>
                        <th class="px-4 py-3 font-bold text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($safeCompletedOpd as $history)
                        <tr class="hover:bg-white dark:hover:bg-slate-800 transition-colors">
                            <td class="px-4 py-4 text-slate-500 font-medium whitespace-nowrap">{{ $history->updated_at->format('H:i') }} น.</td>
                            <td class="px-4 py-4 font-bold text-slate-800 dark:text-white whitespace-nowrap">
                                {{ $history->patient->rank ?? '' }}{{ $history->patient->first_name ?? '' }} {{ $history->patient->last_name ?? '' }}
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-600 dark:text-slate-300 font-semibold whitespace-nowrap">
                                {{ $history->patient->militaryUnit->short_name ?? $history->patient->company ?? '-' }}
                            </td>
                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 rounded-lg font-black text-xs">{{ $history->total_score }}</span>
                            </td>
                            <td class="px-4 py-4">
                                @php $isReturnedOpd = !empty($history->returned_to_unit_at); @endphp
                                @if($history->status === 'cancelled')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 font-bold text-xs border border-slate-200 whitespace-nowrap">
                                        <i class="fa-solid fa-ban"></i> ถูกยกเลิก
                                    </span>
                                @elseif($history->discharge_type === 'admitted')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $isReturnedOpd ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }} font-bold text-xs border">
                                        <i class="fa-solid {{ $isReturnedOpd ? 'fa-house-chimney-check' : 'fa-bed-pulse' }}"></i>
                                        Admit {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} {{ $isReturnedOpd ? '• กลับหน่วยแล้ว' : '' }}
                                    </span>
                                @elseif($history->discharge_type === 'refer')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $isReturnedOpd ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200' }} font-bold text-xs border">
                                        <i class="fa-solid {{ $isReturnedOpd ? 'fa-house-chimney-check' : 'fa-ambulance' }}"></i>
                                        ส่งต่อ Refer {{ $history->discharge_details ? "({$history->discharge_details})" : '' }} {{ $isReturnedOpd ? '• กลับหน่วยแล้ว' : '' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs border border-emerald-200 whitespace-nowrap">
                                        <i class="fa-solid fa-house-chimney-medical"></i> จำหน่ายกลับหน่วย (OPD)
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('backend.mews.show', $history->id) }}" class="text-teal-600 hover:text-teal-800 font-bold text-xs transition-colors bg-teal-50 px-3 py-1.5 rounded-lg border border-teal-200 shadow-sm">
                                    <i class="fa-solid fa-folder-open mr-1"></i> ดูแฟ้ม
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">ยังไม่มีประวัติการจำหน่ายผู้ป่วยนอก (OPD) ในวันนี้</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Echo) {
            window.Echo.channel('mews-channel')
                .listen('.mews.updated', (e) => {
                    refreshOpdQueue();
                });
        }

        setInterval(() => {
            refreshOpdQueue();
        }, 60000); 

        function refreshOpdQueue() {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const currentContent = document.querySelector('.max-w-7xl');
                const newContent = doc.querySelector('.max-w-7xl');
                if (currentContent && newContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                }
            });
        }
    });

    window.openOpdQuickDischargeModal = function(actionUrl, soldierName) {
        Swal.fire({
            title: `<div class="text-lg font-black text-slate-800"><i class="fa-solid fa-house-chimney-medical text-emerald-600 mr-2"></i>จบการรักษาผู้ป่วยนอก (OPD)</div>`,
            html: `
                <div class="text-left space-y-3.5 mt-2">
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs sm:text-sm text-emerald-900">
                        ผู้ป่วย: <b class="text-base text-emerald-800">${soldierName}</b>
                    </div>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-500 transition-all">
                        <input type="radio" name="opd_discharge_type" value="recovered" checked onchange="toggleOpdExtraInput()" class="mt-1 w-4 h-4 text-emerald-600">
                        <div>
                            <span class="font-bold text-slate-800 text-sm block">🏠 จำหน่ายกลับกองร้อย (Recovered)</span>
                            <span class="text-xs text-slate-500">ตรวจรักษา/รับยาเรียบร้อย สามารถกลับไปปฏิบัติภารกิจหรือพักฟื้นที่หน่วยได้</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:bg-amber-50 has-[:checked]:border-amber-500 transition-all">
                        <input type="radio" name="opd_discharge_type" value="admitted" onchange="toggleOpdExtraInput()" class="mt-1 w-4 h-4 text-amber-600">
                        <div class="w-full">
                            <span class="font-bold text-slate-800 text-sm block">🛏️ รับไว้รักษาตัวใน รพ.ค่ายฯ (Admit)</span>
                            <span class="text-xs text-slate-500 block">แพทย์สั่งให้นอนรักษาตัวที่หอผู้ป่วยใน</span>
                            <div id="opd_admit_box" class="hidden mt-2">
                                <input type="text" id="opd_admit_ward" class="w-full px-3 py-2 bg-white border border-amber-300 rounded-lg text-xs sm:text-sm" placeholder="ระบุ Dx. หรือ หอผู้ป่วย/เตียง...">
                            </div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border-2 border-slate-200 cursor-pointer has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 transition-all">
                        <input type="radio" name="opd_discharge_type" value="refer" onchange="toggleOpdExtraInput()" class="mt-1 w-4 h-4 text-blue-600">
                        <div class="w-full">
                            <span class="font-bold text-slate-800 text-sm block">🚑 ส่งตัวรักษาต่อ (Refer)</span>
                            <span class="text-xs text-slate-500 block">ส่งต่อไปตรวจรักษาที่ รพ.สาธารณสุข / รพ.ศูนย์</span>
                            <div id="opd_refer_box" class="hidden mt-2">
                                <input type="text" id="opd_refer_hosp" class="w-full px-3 py-2 bg-white border border-blue-300 rounded-lg text-xs sm:text-sm" placeholder="ระบุ รพ.ปลายทาง หรือ Dx....">
                            </div>
                        </div>
                    </label>
                </div>
            `,
            width: '34rem',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk mr-1"></i> ยืนยันจบการรักษา',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#059669',
            customClass: { popup: 'rounded-3xl p-6' },
            preConfirm: () => {
                const type = document.querySelector('input[name="opd_discharge_type"]:checked')?.value || 'recovered';
                let details = '';
                if (type === 'admitted') details = document.getElementById('opd_admit_ward')?.value.trim() || '';
                if (type === 'refer') details = document.getElementById('opd_refer_hosp')?.value.trim() || '';
                return { type, details };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = actionUrl;
                form.innerHTML = `
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="discharge_type" value="${res.value.type}">
                    <input type="hidden" name="discharge_details" value="${res.value.details}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    };

    window.toggleOpdExtraInput = function() {
        const type = document.querySelector('input[name="opd_discharge_type"]:checked')?.value;
        document.getElementById('opd_admit_box')?.classList.toggle('hidden', type !== 'admitted');
        document.getElementById('opd_refer_box')?.classList.toggle('hidden', type !== 'refer');
    };
</script>
@endsection