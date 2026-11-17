@extends('layouts.backend')

@section('title', 'ตั้งค่าเกณฑ์ประเมิน MEWS - KSVR Hospital')
@section('page_header', 'ตั้งค่าเกณฑ์ประเมิน (MEWS Score Templates)')

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
                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    รูปแบบสูตรคำนวณ (MEWS Templates)
                </h3>
                <p class="text-xs text-slate-500 mt-1 ml-10">คุณสามารถปรับช่วงคะแนน สัญญาณชีพ เพื่อให้สอดคล้องกับนโยบายของโรงพยาบาลได้</p>
            </div>
            <!-- 🌟 ปลดล็อกปุ่ม สร้างสูตรใหม่ ให้กดได้แล้วครับ -->
            <button onclick="openAddTemplateModal()" class="px-5 py-2.5 bg-slate-800 text-white text-sm rounded-xl hover:bg-slate-900 transition-all font-bold flex items-center gap-2 shadow-sm hover:-translate-y-0.5">
                <i class="fa-solid fa-plus"></i> สร้างสูตรใหม่
            </button>
        </div>
        
        <div class="p-8 bg-slate-50/30 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-8">
            @foreach($templates as $template)
                <div class="bg-white border-t-4 {{ $template->is_default ? 'border-t-emerald-500 border-x border-b border-slate-200 shadow-md' : 'border-t-blue-500 border-x border-b border-slate-200 shadow-sm' }} rounded-2xl p-6 transition-all duration-300 hover:shadow-lg relative flex flex-col h-full">
                    
                    @if($template->is_default)
                        <div class="absolute -top-3 right-4">
                            <span class="bg-gradient-to-r from-emerald-400 to-emerald-600 text-white px-3 py-1 rounded-full text-xs font-bold shadow-sm">
                                <i class="fa-solid fa-star mr-1 text-yellow-200"></i> ค่ามาตรฐาน
                            </span>
                        </div>
                    @endif

                    <div>
                        <h4 class="font-black text-xl text-slate-800 mb-2 pr-20">{{ $template->name }}</h4>
                        <p class="text-sm text-slate-500">{{ $template->description }}</p>
                    </div>

                    <!-- 🌟 ส่วนแสดงข้อมูลพรีวิวสูตรคำนวณแบบย่อ (Summary Preview) -->
                    <div class="mt-4 mb-5 bg-slate-50 rounded-xl p-3.5 border border-slate-100 flex-1">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-microscope"></i> สรุปพารามิเตอร์ที่ตั้งค่าไว้
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @php
                                $previewRules = is_string($template->rules) ? json_decode($template->rules, true) : ($template->rules ?? []);
                                $params = ['sbp' => 'ความดัน (SBP)', 'hr' => 'ชีพจร (HR)', 'rr' => 'หายใจ (RR)', 'temp' => 'อุณหภูมิ', 'avpu' => 'การรู้สึกตัว'];
                            @endphp
                            
                            @php $hasRule = false; @endphp
                            @foreach($params as $key => $label)
                                @if(isset($previewRules[$key]) && count($previewRules[$key]) > 0)
                                    @php $hasRule = true; @endphp
                                    <span class="bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-md text-[11px] font-bold border border-indigo-100 shadow-sm">
                                        {{ $label }} <span class="bg-indigo-200 text-indigo-800 px-1.5 rounded ml-1">{{ count($previewRules[$key]) }}</span>
                                    </span>
                                @endif
                            @endforeach
                            
                            @if(!$hasRule)
                                <span class="text-xs font-medium text-rose-500 bg-rose-50 px-2 py-1 rounded-md border border-rose-100">
                                    <i class="fa-solid fa-circle-exclamation mr-1"></i> ยังไม่ได้ตั้งค่าเกณฑ์
                                </span>
                            @endif
                        </div>
                    </div>
                    
                    <!-- 🌟 ส่วนปุ่มจัดการ (ปรับให้ Responsive สำหรับมือถือ) -->
                    <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 pt-4 border-t border-slate-100 mt-auto">
                        
                        <!-- ปุ่มเปิด/ปิด การใช้งาน -->
                        <form action="{{ route('backend.settings.mews.toggle', $template->id) }}" method="POST" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="w-full justify-center text-sm font-bold px-3 py-2 sm:py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $template->is_active ? 'bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200' : 'bg-slate-50 text-slate-400 hover:bg-slate-100 border border-slate-200' }}">
                                <i class="fa-solid fa-power-off"></i> 
                                {{ $template->is_active ? 'ใช้งานอยู่' : 'ปิดใช้งาน' }}
                            </button>
                        </form>
                        
                        <!-- กลุ่มปุ่มจัดการด้านขวา (ห่อด้วย flex-wrap) -->
                        <div class="flex flex-wrap items-center justify-end gap-2 w-full sm:w-auto">
                            
                            <!-- ถังขยะ (ลบ) -->
                            @if(!$template->is_default)
                                @if($template->is_active)
                                    <button type="button" 
                                            onclick="Swal.fire({icon: 'warning', title: 'ลบไม่ได้!', text: 'ไม่สามารถลบสูตรที่กำลัง ใช้งานอยู่ ได้ กรุณากดสลับไปใช้สูตรอื่นก่อนครับ', customClass: { popup: 'rounded-3xl' }})" 
                                            class="flex-1 sm:flex-none justify-center px-3 py-2 bg-slate-100 text-slate-400 text-sm font-bold rounded-xl shadow-inner flex items-center gap-2 border border-slate-200 cursor-not-allowed" 
                                            title="ไม่สามารถลบสูตรที่ใช้งานอยู่ได้">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                @else
                                    <form id="del-tpl-{{ $template->id }}" action="{{ route('backend.settings.mews.destroy', $template->id) }}" method="POST" class="inline-block flex-1 sm:flex-none">
                                        @csrf @method('DELETE')
                                        <button type="button" 
                                                onclick="confirmAction(event, 'ลบสูตรนี้?', 'คุณแน่ใจหรือไม่ที่จะลบ {{ $template->name }} ออกจากระบบ', 'error', 'del-tpl-{{ $template->id }}')" 
                                                class="w-full justify-center px-3 py-2 bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white text-sm font-bold rounded-xl transition-all shadow-sm flex items-center gap-2 border border-rose-100 hover:border-rose-500" 
                                                title="ลบสูตรนี้">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                @endif
                            @endif

                            <!-- ปุ่มแก้ไขชื่อ (ดินสอ) -->
                            @if(!$template->is_default)
                                <button type="button" 
                                        onclick="openEditTemplateModal('{{ $template->id }}', '{{ addslashes($template->name) }}', '{{ addslashes($template->description) }}')" 
                                        class="flex-1 sm:flex-none justify-center px-3 py-2 bg-slate-50 hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center gap-2 border border-slate-100 hover:border-indigo-200" 
                                        title="แก้ไขชื่อและคำอธิบาย">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            @endif

                            <!-- ปุ่มคัดลอก (Duplicate) -->
                            <form action="{{ route('backend.settings.mews.duplicate', $template->id) }}" method="POST" class="inline-block flex-1 sm:flex-none">
                                @csrf
                                <button type="submit" class="w-full justify-center px-3 py-2 bg-slate-50 hover:bg-slate-100 text-slate-500 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center gap-2 border border-slate-100" title="คัดลอกสูตรนี้">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </form>

                            <!-- ปุ่มดูเกณฑ์ / กำหนดเกณฑ์ (ดันให้เต็มบรรทัดในจอมือถือ) -->
                            <div class="w-full sm:w-auto mt-1 sm:mt-0">
                                @if($template->is_default)
                                    <a href="{{ route('backend.settings.mews.builder', $template->id) }}" 
                                       class="w-full justify-center px-5 py-2 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center gap-2 border border-emerald-200" 
                                       title="ดูเกณฑ์มาตรฐาน">
                                        <i class="fa-solid fa-eye"></i> ดูเกณฑ์
                                    </a>
                                @else
                                    <a href="{{ route('backend.settings.mews.builder', $template->id) }}" 
                                       class="w-full justify-center px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-all shadow-sm hover:-translate-y-0.5 flex items-center gap-2">
                                        <i class="fa-solid fa-sliders"></i> กำหนดเกณฑ์
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// 🌟 ฟังก์ชันเปิดหน้าต่างสร้างสูตรใหม่ (SweetAlert2)
function openAddTemplateModal() {
    Swal.fire({
        title: 'สร้างรูปแบบเกณฑ์ MEWS ใหม่',
        html: `
            <div class="text-left space-y-4 mt-2">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อรูปแบบ (Template Name) <span class="text-rose-500">*</span></label>
                    <input type="text" id="tpl_name" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="เช่น เกณฑ์ประเมินผู้สูงอายุ (Geriatric)">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">คำอธิบาย (Description)</label>
                    <textarea id="tpl_desc" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[80px]" placeholder="เช่น ใช้สำหรับประเมินผู้ป่วยที่อายุ 60 ปีขึ้นไป..."></textarea>
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
            const name = document.getElementById('tpl_name').value;
            const desc = document.getElementById('tpl_desc').value;
            if (!name) {
                Swal.showValidationMessage('กรุณากรอกชื่อรูปแบบเกณฑ์ด้วยครับ');
                return false;
            }
            return { name, desc };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('backend.settings.mews.store') }}";
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="name" value="${result.value.name}">
                <input type="hidden" name="description" value="${result.value.desc}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
// 🌟 ฟังก์ชันเปิดหน้าต่าง แก้ไขชื่อและคำอธิบาย (SweetAlert2)
function openEditTemplateModal(id, currentName, currentDesc) {
    Swal.fire({
        title: 'แก้ไขข้อมูลรูปแบบเกณฑ์',
        html: `
            <div class="text-left space-y-4 mt-2">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">ชื่อรูปแบบ (Template Name) <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_tpl_name" value="${currentName}" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">คำอธิบาย (Description)</label>
                    <textarea id="edit_tpl_desc" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[80px]">${currentDesc}</textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-save"></i> บันทึกการแก้ไข',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'rounded-3xl shadow-2xl border border-slate-100',
            title: 'text-xl font-bold text-slate-800',
            confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-5 py-2.5 font-bold',
            cancelButton: 'bg-slate-200 text-slate-700 hover:bg-slate-300 rounded-xl px-5 py-2.5 font-bold'
        },
        preConfirm: () => {
            const name = document.getElementById('edit_tpl_name').value;
            const desc = document.getElementById('edit_tpl_desc').value;
            if (!name) {
                Swal.showValidationMessage('กรุณากรอกชื่อรูปแบบเกณฑ์ด้วยครับ');
                return false;
            }
            return { name, desc };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let updateUrl = "{{ route('backend.settings.mews.update_info', ':id') }}".replace(':id', id);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = updateUrl;
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="name" value="${result.value.name}">
                <input type="hidden" name="description" value="${result.value.desc}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
@endsection