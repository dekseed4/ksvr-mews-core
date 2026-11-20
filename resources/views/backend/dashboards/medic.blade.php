@extends('layouts.backend')

@section('title', 'Medic Dashboard - KSVR Hospital')
@section('page_header', 'งานภาคสนาม (Field Operations)')

@section('content')
<div class="max-w-4xl mx-auto mt-4 pb-12 space-y-6 md:space-y-8">
    
    @include('backend.dashboards.partials.view_tabs')
    
    <!-- 🟢 Header ทักทายเสนารักษ์ -->
    <div class="bg-slate-800 rounded-3xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 shadow-lg text-white relative overflow-hidden">
        <!-- ลวดลายพื้นหลัง -->
        <div class="absolute -right-10 -top-10 text-white/5 text-9xl">
            <i class="fa-solid fa-truck-medical"></i>
        </div>
        
        <div class="relative z-10">
            <h2 class="text-2xl font-bold mb-1">สวัสดี, เสนารักษ์ {{ auth()->user()->name }}</h2>
            <p class="text-slate-300 text-sm"><i class="fa-solid fa-calendar-day mr-1"></i> ปฏิบัติหน้าที่ประจำวันที่ {{ \Carbon\Carbon::now()->locale('th')->addYears(543)->translatedFormat('j F Y') }}</p>
        </div>
        
        <div class="relative z-10 bg-white/10 rounded-2xl p-4 flex items-center gap-4 backdrop-blur-sm border border-white/10 text-center">
            <div>
                <p class="text-[10px] text-slate-300 uppercase tracking-widest font-bold">ประเมินวันนี้</p>
                <div class="text-3xl font-black text-emerald-400">{{ $myStats['evaluated_today'] }} <span class="text-sm font-medium text-slate-200">นาย</span></div>
            </div>
        </div>
    </div>
    @section('floating_buttons')
        <!-- 🌟 ปุ่มเสริมเฉพาะหน้า Medic: ค้นหาคนไข้ใหม่ (จะลอยอยู่เหนือปุ่ม AI อัตโนมัติ) -->
        <button onclick="openSearchModal()" 
                class="bg-emerald-500 hover:bg-emerald-400 text-white rounded-full w-12 h-12 md:w-14 md:h-14 flex items-center justify-center shadow-[0_10px_20px_rgba(16,185,129,0.4)] hover:shadow-[0_15px_30px_rgba(16,185,129,0.5)] transition-all duration-300 transform hover:-translate-y-1 focus:outline-none ring-4 ring-emerald-500/20 group pointer-events-auto tooltip" title="ค้นหาประวัติ / ประเมินใหม่">
            <i class="fa-solid fa-magnifying-glass-plus text-xl md:text-2xl group-hover:scale-110 transition-transform duration-300"></i>
        </button>
    @endsection
    <!-- 📋 ติดตามสถานะคนไข้ของฉัน (My Patients Tracking) -->
    <div>
        <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2 px-2">
            <i class="fa-solid fa-route text-blue-500"></i> ติดตามสถานะผู้ป่วยของฉัน
        </h3>
        
        <div class="space-y-4">
            @forelse($myPatients as $case)
                <!-- 🌟 1. เปลี่ยนจาก <div> เป็น <a> และใส่ route ชี้ไปที่หน้า show -->
                <!-- 🌟 2. เพิ่มคลาส group เพื่อให้จัดการสีตอน Hover ได้ทั้งการ์ด -->
                <a href="{{ route('backend.mews.show', $case->id) }}" class="block bg-white rounded-3xl p-5 md:p-6 shadow-sm border border-slate-100 relative overflow-hidden transition-all hover:shadow-md hover:border-emerald-300 group cursor-pointer">
                    
                    <div class="flex flex-col sm:flex-row justify-between gap-4">
                        <!-- ข้อมูลคนไข้ -->
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <!-- 🌟 3. เปลี่ยนสีชื่อคนไข้เป็นสีเขียวเวลาเอาเมาส์ชี้ (group-hover) -->
                                <h4 class="font-bold text-slate-800 text-lg group-hover:text-emerald-600 transition-colors">
                                    {{ $case->patient->rank }}{{ $case->patient->first_name }} {{ $case->patient->last_name }}
                                </h4>
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $case->risk_level === 'emergency' ? 'bg-rose-100 text-rose-700' : ($case->risk_level === 'observe' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">
                                    MEWS: {{ $case->total_score }}
                                </span>
                            </div>
                            <div class="text-sm text-slate-500 mb-2">
                                <i class="fa-solid fa-tent mr-1"></i> 
                                <!-- 🌟 เช็คว่าถ้ามีตารางหน่วยทหาร ให้ดึงชื่อย่อมาโชว์ ถ้าไม่มีให้ดึง company เดิม -->
                                @if($case->patient->militaryUnit)
                                    {{ $case->patient->militaryUnit->short_name ?? $case->patient->militaryUnit->name }}
                                @else
                                    {{ $case->patient->company ?? 'ไม่ระบุสังกัด' }}
                                @endif
                                <span class="mx-1 text-slate-300">|</span> 
                                <i class="fa-regular fa-clock text-slate-400 mr-1"></i> ส่งเมื่อ: <span class="font-medium text-slate-600">{{ $case->created_at->format('H:i') }} น.</span>
                            </div>
                        </div>

                        <!-- 🌟 Tracking Status Badge (ป้ายบอกสถานะ) -->
                        <div class="shrink-0 flex items-center justify-between sm:justify-end gap-3">
                            
                            <!-- ป้ายสถานะเดิม -->
                            <div>
                                @if($case->is_cancelled)
                                    <div class="bg-slate-100 border border-slate-200 text-slate-600 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-500"><i class="fa-solid fa-ban"></i></div>
                                        <div>
                                            <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                            <div class="font-bold text-sm">ถูกยกเลิกการประเมิน</div>
                                        </div>
                                    </div>
                                @elseif($case->is_completed)
                                    @php
                                        $medicHasReturned = !empty($case->returned_to_unit_at);
                                    @endphp

                                    @if($case->final_discharge === 'recovered')
                                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600"><i class="fa-solid fa-house-chimney-medical"></i></div>
                                            <div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                <div class="font-bold text-sm">จำหน่ายกลับหน่วย</div>
                                            </div>
                                        </div>
                                    @elseif($case->final_discharge === 'admitted')
                                        @if($medicHasReturned)
                                            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600"><i class="fa-solid fa-house-chimney-check"></i></div>
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">Admit รพ.ค่ายฯ</div>
                                                    <div class="font-bold text-sm">กลับหน่วยฝึกแล้ว</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                                <div class="w-8 h-8 rounded-full bg-amber-200 flex items-center justify-center text-amber-700"><i class="fa-solid fa-bed-pulse"></i></div>
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                    <div class="font-bold text-sm">Admit นอน รพ. (ยังไม่กลับหน่วย)</div>
                                                </div>
                                            </div>
                                        @endif
                                    @elseif($case->final_discharge === 'refer')
                                        @if($medicHasReturned)
                                            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600"><i class="fa-solid fa-house-chimney-check"></i></div>
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">ส่งตัว (Refer)</div>
                                                    <div class="font-bold text-sm">กลับหน่วยฝึกแล้ว</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                                <div class="w-8 h-8 rounded-full bg-blue-200 flex items-center justify-center text-blue-700"><i class="fa-solid fa-ambulance"></i></div>
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                    <div class="font-bold text-sm">ส่งตัว Refer (ยังไม่กลับหน่วย)</div>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                @elseif($case->is_accepted_by_er)
                                    <div class="bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                        <div class="w-8 h-8 rounded-full bg-indigo-200 flex items-center justify-center text-indigo-700 animate-pulse"><i class="fa-solid fa-user-nurse"></i></div>
                                        <div>
                                            <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                            <div class="font-bold text-sm">ER รับผู้ป่วยแล้ว</div>
                                        </div>
                                    </div>
                                @else
                                    @php
                                        $rawMedicSyms = is_string($case->symptoms) ? json_decode($case->symptoms, true) : ($case->symptoms ?? []);
                                        $isMedicRoutedToER = in_array($case->risk_level, ['observe', 'emergency']) || (!empty($rawMedicSyms));
                                    @endphp
                                    @if($isMedicRoutedToER)
                                        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                            <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600"><i class="fa-solid fa-spinner fa-spin-pulse"></i></div>
                                            <div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                <div class="font-bold text-sm">รอ ER รับเคส...</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="bg-teal-50 border border-teal-200 text-teal-700 px-4 py-2 rounded-xl flex items-center gap-2 w-full sm:w-auto shadow-sm">
                                            <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center text-teal-600"><i class="fa-solid fa-user-doctor"></i></div>
                                            <div>
                                                <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">สถานะล่าสุด</div>
                                                <div class="font-bold text-sm">รอตรวจแผนก OPD</div>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <!-- 🌟 4. เพิ่มไอคอนลูกศรชี้ขวา (Chevron) เพื่อบอกใบ้ให้ User รู้ว่ากดเข้าดูต่อได้ -->
                            <div class="text-slate-300 group-hover:text-emerald-500 group-hover:translate-x-1 transition-all ml-2 hidden sm:block">
                                <i class="fa-solid fa-chevron-right text-xl"></i>
                            </div>

                        </div>
                    </div>
                    
                </a>
            @empty
                <div class="bg-white rounded-3xl p-10 shadow-sm border border-dashed border-slate-300 text-center">
                    <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-4xl mx-auto mb-4">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <h4 class="text-lg font-bold text-slate-700 mb-1">ยังไม่มีประวัติการประเมินวันนี้</h4>
                    <p class="text-slate-500 text-sm">คลิกที่ปุ่มสีเขียวด้านบนเพื่อเริ่มการประเมิน MEWS ภาคสนาม</p>
                </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
@section('scripts')
<script>
    // -----------------------------------------------------
    // 🌟 ฟังก์ชันเปิด Modal ค้นหาผู้ป่วยใหม่ (ล็อก Z-Index แก้ปัญหาซ้อนทับ 100%)
    // -----------------------------------------------------
    function openSearchModal() {
        Swal.fire({
            title: '<div class="text-xl md:text-2xl font-black text-slate-800 dark:text-white mt-1"><i class="fa-solid fa-magnifying-glass-plus text-emerald-500 mr-2"></i> ค้นหาประวัติ / ประเมินใหม่</div>',
            html: `
                <!-- 🚨 พลังเวทย์: บังคับเลเยอร์ Z-Index ของ SweetAlert2 โดยตรง -->
                <style>
                    .swal2-popup { overflow: visible !important; }
                    /* ดันกล่องเนื้อหา (ช่องค้นหา+รายชื่อ) ให้อยู่ชั้นบนสุดเสมอ (Z-Index 100) */
                    .swal2-html-container { overflow: visible !important; position: relative !important; z-index: 100 !important; }
                    /* กดโซนปุ่มให้อยู่ชั้นล่างสุด (Z-Index 10) จะได้โดนทับได้เนียนๆ */
                    .swal2-actions { position: relative !important; z-index: 10 !important; }
                </style>

                <div class="mt-4 text-left">
                    <p class="text-[13px] md:text-sm text-slate-500 dark:text-slate-400 mb-4">
                        กรุณาระบุเลข ปชช., รหัส HN หรือชื่อ-นามสกุล เพื่อดึงประวัติ
                    </p>
                    
                    <form id="fab-search-form" action="{{ route('backend.patients.processSearch') }}" method="POST">
                        @csrf
                        <div class="relative">
                            
                            <!-- กล่องพิมพ์ค้นหา -->
                            <div class="flex items-center bg-slate-50 dark:bg-slate-900 rounded-2xl p-2 border border-slate-200 dark:border-slate-700 focus-within:ring-4 focus-within:ring-emerald-500/20 focus-within:border-emerald-400 transition-all shadow-inner relative z-20">
                                <div class="pl-3 pr-2 text-slate-400 dark:text-slate-500">
                                    <i class="fa-regular fa-keyboard text-lg"></i>
                                </div>
                                <input type="text" id="fab-search-input" name="search_term" 
                                       class="w-full bg-transparent border-none focus:ring-0 text-slate-700 dark:text-slate-200 text-base px-2 py-2 outline-none font-medium placeholder-slate-400" 
                                       placeholder="พิมพ์ชื่อ, HN หรือเลข ปชช..." required autocomplete="off">
                            </div>

                            <!-- 🌟 กล่องรายชื่อ Autocomplete (เปลี่ยนกลับมาเด้งลงล่าง เพื่อหลบคีย์บอร์ดมือถือ) -->
                            <div id="autocomplete-results" class="absolute left-0 right-0 top-full mt-2 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 shadow-[0_15px_40px_-10px_rgba(0,0,0,0.3)] rounded-2xl z-[999] hidden max-h-52 overflow-y-auto">
                            </div>

                        </div>
                    </form>
                </div>
            `,
            showCancelButton: true,
            showConfirmButton: true,
            confirmButtonText: '<i class="fa-solid fa-arrow-right mr-1"></i> ค้นหาเลย',
            cancelButtonText: 'ยกเลิก',
            buttonsStyling: false,
            customClass: {
                popup: 'bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl border border-slate-100 dark:border-slate-700 p-6 !overflow-visible',
                htmlContainer: '!overflow-visible',
                actions: 'grid grid-cols-1 md:grid-cols-2 gap-3 w-full mt-6',
                confirmButton: 'w-full m-0 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-xl px-4 py-3 transition-colors shadow-md outline-none text-sm md:text-base',
                cancelButton: 'w-full m-0 bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-300 font-bold rounded-xl px-4 py-3 transition-colors shadow-sm outline-none text-sm md:text-base'
            },
            didOpen: () => {
                const input = document.getElementById('fab-search-input');
                const results = document.getElementById('autocomplete-results');
                let timeout = null;

                setTimeout(() => input.focus(), 100);

                input.addEventListener('input', function(e) {
                    clearTimeout(timeout);
                    const term = this.value.trim();

                    if(term.length < 2) {
                        results.classList.add('hidden');
                        return;
                    }

                    timeout = setTimeout(() => {
                        const fetchUrl = `{{ route('backend.patients.autocomplete') }}?term=${encodeURIComponent(term)}`;

                        fetch(fetchUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                            .then(res => {
                                if (!res.ok) throw new Error('Server error');
                                return res.json();
                            })
                            .then(data => {
                                results.innerHTML = '';
                                if(data.length > 0) {
                                    data.forEach(patient => {
                                        const hn = patient.hn || '-';
                                        const cid = patient.cid || '-';
                                        const rank = patient.rank || '';
                                        const unit = patient.company || '-';
                                        
                                        results.innerHTML += `
                                            <div class="px-4 py-3 hover:bg-emerald-50 dark:hover:bg-slate-700 cursor-pointer border-b border-slate-50 dark:border-slate-700/50 flex justify-between items-center transition-colors first:rounded-t-2xl last:rounded-b-2xl"
                                                 onclick="selectAutocomplete('${hn !== '-' ? hn : cid}')">
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200">${rank}${patient.first_name} ${patient.last_name}</span>
                                                    <span class="text-[10px] text-slate-500">HN: ${hn} | ปชช: ${cid} | ${unit}</span>
                                                </div>
                                            </div>
                                        `;
                                    });
                                    results.classList.remove('hidden');
                                } else {
                                    results.innerHTML = '<div class="px-4 py-4 text-sm text-slate-500 text-center">ไม่พบข้อมูลในระบบ...</div>';
                                    results.classList.remove('hidden');
                                }
                            })
                            .catch(error => {
                                console.error('Autocomplete Error:', error);
                            });
                    }, 300);
                });

                window.selectAutocomplete = function(val) {
                    input.value = val;
                    results.classList.add('hidden');
                    document.getElementById('fab-search-form').submit();
                };

                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (this.value.trim() !== '') document.getElementById('fab-search-form').submit();
                    }
                });
            },
            preConfirm: () => {
                const inputVal = document.getElementById('fab-search-input').value.trim();
                if (!inputVal) {
                    Swal.showValidationMessage('<i class="fa-solid fa-circle-exclamation mr-1"></i> กรุณาระบุข้อมูลสำหรับค้นหา');
                    return false;
                }
                document.getElementById('fab-search-form').submit();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.Echo) {
            // ฟังเสียงกระดิ่งจาก Reverb 
            window.Echo.channel('mews-channel')
                .listen('.mews.updated', (e) => {
                    refreshMedicDashboard();
                });
        }

        // ฟังก์ชันรีเฟรชหน้าจอแบบเนียนๆ
        function refreshMedicDashboard() {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const currentContent = document.querySelector('.max-w-4xl');
                const newContent = doc.querySelector('.max-w-4xl');
                if (currentContent && newContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                }
            });
        }
    });
</script>
@endsection