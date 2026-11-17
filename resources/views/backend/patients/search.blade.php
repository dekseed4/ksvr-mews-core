@extends('layouts.backend')

@section('title', 'ค้นหาประวัติผู้ป่วย - KSVR Hospital')
@section('page_header', 'ระบบคัดกรองและประเมิน MEWS')

@section('content')
<div class="max-w-3xl mx-auto mt-8 md:mt-12 pb-12">
    
    <!-- ส่วนแสดง Alert ต่างๆ (คงไว้เหมือนเดิม) -->
    @if(session('error')) ... @endif
    @if(session('info')) ... @endif

    <!-- กล่องฟอร์มค้นหา -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 mb-6">
        <div class="bg-slate-800 p-6 text-center rounded-t-3xl">
            <div class="w-16 h-16 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-3xl mx-auto mb-4 shadow-lg rotate-3">
                <i class="fa-solid fa-magnifying-glass-chart"></i>
            </div>
            <h2 class="text-2xl font-bold text-white">ค้นหาประวัติผู้ป่วย</h2>
            <p class="text-slate-300 text-sm mt-2">กรอกเลขบัตร ปชช., HN, ชื่อ หรือ นามสกุล อย่างใดอย่างหนึ่ง</p>
        </div>

        <form action="{{ route('backend.patients.processSearch') }}" method="POST" class="p-8">
            @csrf
            <div class="mb-6 relative" id="search_container">
                <input type="text" name="search_term" id="search_term" required autofocus autocomplete="off"
                       value="{{ old('search_term', $term ?? '') }}"
                       placeholder="พิมพ์ชื่อ, สกุล, CID หรือ HN..."
                       class="w-full text-center text-xl rounded-xl border-slate-300 py-4 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm transition-colors relative z-10">
                
                <!-- 🌟 กล่องโชว์ผลลัพธ์ Auto-complete (ซ่อนไว้ก่อน) -->
                <div id="autocomplete_dropdown" class="absolute left-0 right-0 top-full mt-2 bg-white border border-slate-100 rounded-xl shadow-xl z-50 hidden overflow-hidden max-h-80 overflow-y-auto divide-y divide-slate-50">
                    <!-- ข้อมูลที่ค้นหาเจอจะถูก JavaScript นำมายัดใส่ตรงนี้ -->
                </div>
            </div>

            <button type="submit" class="w-full md:w-2/3 mx-auto flex justify-center items-center gap-2 rounded-xl bg-emerald-600 py-4 px-4 text-lg font-bold text-white shadow-md hover:bg-emerald-700 transition-all">
                <i class="fa-solid fa-search"></i> ค้นหาข้อมูล
            </button>
        </form>
    </div>

    <!-- 🌟 ส่วนที่เพิ่มใหม่: โชว์ผลลัพธ์กรณีเจอหลายคน (เช่น พิมพ์ชื่อ "สมชาย") -->
    @if(isset($results) && $results->count() > 0)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 animate-fade-in-up">
            <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">
                <i class="fa-solid fa-list-check text-emerald-500 mr-2"></i> พบข้อมูล {{ $results->count() }} รายการ
            </h3>
            
            <div class="space-y-3">
                @foreach($results as $patient)
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 hover:bg-emerald-50 transition-colors">
                        <div>
                            <div class="font-bold text-slate-800 text-lg">{{ $patient->rank }} {{ $patient->first_name }} {{ $patient->last_name }}</div>
                            <div class="text-sm text-slate-500 mt-1">
                                <i class="fa-regular fa-id-card mr-1"></i> CID: {{ $patient->cid }} 
                                @if($patient->hn) | <i class="fa-solid fa-file-medical mr-1"></i> HN: {{ $patient->hn }} @endif
                            </div>
                            <!-- 🌟 เพิ่มบรรทัดนี้: เพื่อให้แสดงสังกัดหน่วยทหารตอนค้นหาเจอ -->
                            <div class="text-xs text-emerald-600 font-bold mt-1">
                                <i class="fa-solid fa-tent mr-1"></i> สังกัด: {{ $patient->militaryUnit->short_name ?? $patient->militaryUnit->name ?? 'ไม่ระบุ' }}
                            </div>
                        </div>
                        <a href="{{ route('backend.mews.create', $patient->id) }}" class="px-5 py-2.5 bg-slate-800 hover:bg-emerald-600 text-white rounded-lg font-medium transition-colors shadow-sm text-sm flex items-center gap-2">
                            <i class="fa-solid fa-stethoscope"></i> ประเมิน MEWS
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection

@section('scripts')
@php
    $rawRoles = auth()->user()->roles ?? auth()->user()->role ?? [];
    $userRoles = is_string($rawRoles) ? json_decode($rawRoles, true) : (array)$rawRoles;
    $isClinical = in_array('admin', $userRoles) || in_array('er_nurse', $userRoles) || in_array('opd_nurse', $userRoles) || in_array('medic', $userRoles);

    // 🌟 ถ้าเป็นสายการแพทย์ให้ไปหน้าประเมิน MEWS ถ้าเป็นทะเบียน/ผู้บริหารให้ไปหน้าแฟ้มประวัติ
    $targetBaseUrl =$isClinical 
        ? route('backend.mews.create', ['patient_id' => '__ID__']) 
        : route('backend.patients.show', '__ID__');
@endphp
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_term');
    const dropdown = document.getElementById('autocomplete_dropdown');
    let timeout = null;

    const autocompleteUrl = "{{ route('backend.patients.autocomplete') }}";
    const targetUrlTemplate = "{{ $targetBaseUrl }}"; // 🌟 รับเทมเพลต URL ที่ถูกต้อง 100%

    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        const term = this.value.trim();

        if (term.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        timeout = setTimeout(() => {
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
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});
</script>
@endsection