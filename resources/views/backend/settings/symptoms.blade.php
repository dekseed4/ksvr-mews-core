@extends('layouts.backend')

@section('title', 'จัดการอาการฉุกเฉินเฉพาะโรค - KSVR Hospital')
@section('page_header', 'จัดการอาการฉุกเฉินเฉพาะโรค (Emergency Symptoms)')

@section('content')
<div class="max-w-7xl mx-auto mt-6 pb-12 space-y-6">

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-start gap-3 shadow-sm animate-fade-in-up">
            <i class="fa-solid fa-circle-check mt-1"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <div>
                <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center">
                        <i class="fa-solid fa-truck-medical"></i>
                    </div>
                    รายการอาการฉุกเฉิน
                </h3>
                <p class="text-xs text-slate-500 mt-1 ml-10">รายการอาการที่หากพบ จะถูกประเมินผลเป็นวิกฤต (Emergency) ทันที</p>
            </div>
            <button onclick="openAddSymptomModal()" class="px-5 py-2.5 bg-slate-800 text-white text-sm rounded-xl hover:bg-slate-900 transition-all shadow-sm font-bold flex items-center gap-2 hover:-translate-y-0.5">
                <i class="fa-solid fa-plus"></i> เพิ่มอาการใหม่
            </button>
        </div>

        <div class="overflow-x-auto p-6 pt-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-slate-400 text-xs uppercase tracking-wider border-b border-slate-100">
                        <th class="px-4 py-4 font-bold">กลุ่มโรค (Category)</th>
                        <th class="px-4 py-4 font-bold">รหัส (Code)</th>
                        <th class="px-4 py-4 font-bold">ชื่ออาการ (Name)</th>
                        <th class="px-4 py-4 font-bold text-center">สถานะ</th>
                        <th class="px-4 py-4 font-bold text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-50">
                    @php
                        // 🎨 พจนานุกรมสีสำหรับป้ายกำกับกลุ่มโรค
                        $badgeColors = [
                            'MI' => 'bg-rose-100 text-rose-700 border-rose-200',
                            'Stroke' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                            'Heat Injury' => 'bg-orange-100 text-orange-700 border-orange-200',
                            'Anaphylaxis' => 'bg-pink-100 text-pink-700 border-pink-200',
                            'Appendicitis' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            'Sepsis' => 'bg-purple-100 text-purple-700 border-purple-200',
                            'Trauma' => 'bg-sky-100 text-sky-700 border-sky-200',
                        ];
                    @endphp

                    @forelse($symptoms as $symptom)
                        <tr class="hover:bg-slate-50/80 transition-colors group">
                            <td class="px-4 py-3.5">
                                @php $badge = $badgeColors[$symptom->category] ?? 'bg-slate-100 text-slate-700 border-slate-200'; @endphp
                                <span class="px-3 py-1.5 rounded-lg text-xs font-bold border shadow-sm inline-block {{ $badge }}">
                                    {{ $symptom->category }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-slate-400 font-mono text-xs">{{ $symptom->code }}</td>
                            <td class="px-4 py-3.5 font-bold text-slate-700 group-hover:text-blue-600 transition-colors">{{ $symptom->name }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <form action="{{ route('backend.settings.symptoms.update', $symptom->id) }}" method="POST">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="toggle_active" value="1">
                                    <button type="submit" class="text-3xl {{ $symptom->is_active ? 'text-emerald-500 hover:text-emerald-600 drop-shadow-sm' : 'text-slate-200 hover:text-slate-300' }} transition-all" title="คลิกเพื่อเปิด/ปิด">
                                        <i class="fa-solid fa-toggle-{{ $symptom->is_active ? 'on' : 'off' }}"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap space-x-1">
                                <button type="button" 
                                        onclick="openEditSymptomModal('{{ $symptom->id }}', '{{ addslashes($symptom->category) }}', '{{ $symptom->code }}', '{{ addslashes($symptom->name) }}')" 
                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-500 hover:text-white transition-all shadow-sm" 
                                        title="แก้ไขข้อมูล">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>

                                <form id="del-sym-{{ $symptom->id }}" action="{{ route('backend.settings.symptoms.destroy', $symptom->id) }}" method="POST" class="inline-block">
                                    @csrf @method('DELETE')
                                    <button type="button" onclick="confirmAction(event, 'ลบอาการนี้?', 'คุณแน่ใจหรือไม่ที่จะลบ {{ $symptom->name }} ออกจากระบบ', 'error', 'del-sym-{{ $symptom->id }}')" 
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white transition-all shadow-sm" 
                                            title="ลบข้อมูล">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-slate-500">
                                <i class="fa-regular fa-folder-open text-4xl text-slate-300 mb-3 block"></i>
                                ยังไม่มีข้อมูลอาการฉุกเฉินในระบบ
                            </td>
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
// ฟังก์ชันเปิด Modal เพิ่มอาการฉุกเฉิน (เหมือนเดิมครับ)
function openAddSymptomModal() {
    Swal.fire({
        title: 'เพิ่มอาการฉุกเฉินใหม่',
        html: `
            <div class="text-left space-y-4 mt-2">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">กลุ่มโรค (Category)</label>
                    <input type="text" id="sym_category" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm text-slate-800 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="เช่น MI, Stroke, Trauma...">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">รหัสอ้างอิง (Code - ภาษาอังกฤษ)</label>
                    <input type="text" id="sym_code" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm text-slate-800 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="เช่น mi_chest_pain">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">ชื่ออาการ (แสดงให้ผู้ใช้เห็น)</label>
                    <input type="text" id="sym_name" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm text-slate-800 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="เช่น เจ็บแน่นหน้าอกรุนแรง">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-save"></i> บันทึกข้อมูล',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'rounded-3xl shadow-2xl border border-slate-100',
            title: 'text-xl font-bold text-slate-800',
            confirmButton: 'bg-slate-800 hover:bg-slate-900 text-white rounded-xl px-5 py-2.5 font-bold',
            cancelButton: 'bg-slate-200 text-slate-700 hover:bg-slate-300 rounded-xl px-5 py-2.5 font-bold'
        },
        preConfirm: () => {
            const category = document.getElementById('sym_category').value;
            const code = document.getElementById('sym_code').value;
            const name = document.getElementById('sym_name').value;
            if (!category || !code || !name) {
                Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบทุกช่องครับ');
                return false;
            }
            return { category, code, name };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('backend.settings.symptoms.store') }}";
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="category" value="${result.value.category}">
                <input type="hidden" name="code" value="${result.value.code}">
                <input type="hidden" name="name" value="${result.value.name}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// ฟังก์ชันเปิด Modal แก้ไขอาการฉุกเฉิน
function openEditSymptomModal(id, category, code, name) {
    Swal.fire({
        title: 'แก้ไขข้อมูลอาการฉุกเฉิน',
        html: `
            <div class="text-left space-y-4 mt-2">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">กลุ่มโรค (Category)</label>
                    <input type="text" id="edit_sym_category" value="${category}" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm text-slate-800 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1 flex justify-between">
                        รหัสอ้างอิง (Code) <span class="text-xs font-normal text-rose-500 bg-rose-50 px-2 rounded">*ไม่อนุญาตให้แก้ไข</span>
                    </label>
                    <input type="text" value="${code}" readonly class="w-full bg-slate-100 border border-slate-200 text-slate-400 rounded-lg p-2.5 text-sm cursor-not-allowed font-mono">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">ชื่ออาการ (แสดงให้ผู้ใช้เห็น)</label>
                    <input type="text" id="edit_sym_name" value="${name}" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm text-slate-800 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-save"></i> บันทึกการแก้ไข',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'rounded-3xl shadow-2xl border border-slate-100',
            title: 'text-xl font-bold text-slate-800',
            confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-5 py-2.5 font-bold',
            cancelButton: 'bg-slate-200 text-slate-700 hover:bg-slate-300 rounded-xl px-5 py-2.5 font-bold'
        },
        preConfirm: () => {
            const newCategory = document.getElementById('edit_sym_category').value;
            const newName = document.getElementById('edit_sym_name').value;
            if (!newCategory || !newName) {
                Swal.showValidationMessage('กรุณากรอกกลุ่มโรคและชื่ออาการให้ครบถ้วนครับ');
                return false;
            }
            return { category: newCategory, name: newName };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let updateUrl = "{{ route('backend.settings.symptoms.update', ':id') }}".replace(':id', id);
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = updateUrl;
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="category" value="${result.value.category}">
                <input type="hidden" name="name" value="${result.value.name}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
@endsection