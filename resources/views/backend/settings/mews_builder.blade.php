@extends('layouts.backend')

@section('title', 'ปรับแต่งเกณฑ์ประเมิน - KSVR Hospital')
@section('page_header', 'กำหนดเกณฑ์: ' . $template->name)

@section('content')
@php
    $savedRules = is_string($template->rules) ? json_decode($template->rules, true) : ($template->rules ?? []);
@endphp

<!-- 🌟 แจ้งเตือนโหมดอ่านอย่างเดียว -->
@if($template->is_default)
    <div class="max-w-5xl mx-auto mt-6 mb-2 bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl flex items-start gap-3 shadow-sm animate-fade-in-up">
        <i class="fa-solid fa-shield-halved text-emerald-500 text-xl mt-0.5"></i>
        <div>
            <h4 class="font-bold">โหมดอ่านอย่างเดียว (Read-Only)</h4>
            <p class="text-sm text-emerald-700 mt-1">นี่คือค่ามาตรฐานของโรงพยาบาล ไม่สามารถแก้ไขตัวเลขในหน้านี้ได้ หากต้องการปรับเปลี่ยนเกณฑ์ กรุณากลับไปที่หน้าแรกแล้วกดปุ่ม "คัดลอก" แทนครับ</p>
        </div>
    </div>
@endif

<div class="max-w-5xl mx-auto mt-6 pb-12">
    
    <!-- จัดระเบียบ Header ให้ Responsive -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <a href="{{ route('backend.settings.mews.index') }}" class="w-fit bg-slate-100 sm:bg-transparent px-4 py-2 sm:p-0 rounded-xl text-slate-500 hover:text-slate-800 font-bold transition-colors flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> กลับหน้าตั้งค่า
        </a>
        <h2 class="text-xl font-black text-slate-800 flex items-start sm:items-center gap-2 leading-tight">
            <i class="fa-solid fa-sliders text-indigo-500 mt-1 sm:mt-0 shrink-0"></i> 
            <span>ปรับแต่งเกณฑ์ <br class="sm:hidden"> (Visual Builder)</span>
        </h2>
    </div>

    <form action="{{ route('backend.settings.mews.update_builder', $template->id) }}" method="POST" class="space-y-6">
        @csrf @method('PUT')

        @php
            $numericParams = [
                'sbp'   => ['label' => 'ความดันโลหิตตัวบน (SBP)', 'unit' => 'mmHg', 'color' => 'blue'],
                'hr'    => ['label' => 'อัตราการเต้นของหัวใจ (HR)', 'unit' => 'ครั้ง/นาที', 'color' => 'rose'],
                'rr'    => ['label' => 'อัตราการหายใจ (RR)', 'unit' => 'ครั้ง/นาที', 'color' => 'emerald'],
                'temp'  => ['label' => 'อุณหภูมิร่างกาย (Temp)', 'unit' => '°C', 'color' => 'orange'],
                'o2sat' => ['label' => 'ออกซิเจนในเลือด (O2 Sat)', 'unit' => '%', 'color' => 'cyan'],
                'pain'  => ['label' => 'ระดับความปวด (Pain Score)', 'unit' => 'คะแนน', 'color' => 'red']
            ];
        @endphp

        <!-- โซนตัวเลข -->
        @foreach($numericParams as $key => $data)
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
                    <h4 class="font-bold text-{{ $data['color'] }}-700 text-lg flex items-center gap-2">
                        <i class="fa-solid fa-heart-pulse"></i> {{ $data['label'] }}
                    </h4>
                    @if(!$template->is_default)
                        <button type="button" onclick="addNumericRow('{{ $key }}', '{{ $data['unit'] }}')" class="text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                            <i class="fa-solid fa-plus text-emerald-500 mr-1"></i> เพิ่มช่วงคะแนน
                        </button>
                    @endif
                </div>

                <div id="container-{{ $key }}" class="space-y-4">
                    @foreach($savedRules[$key] ?? [] as $index => $rule)
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4 bg-slate-50 p-4 md:p-3 rounded-xl border border-slate-100 group relative">
                            
                            <div class="flex items-center justify-between w-full md:w-auto">
                                <span class="text-sm font-bold text-slate-600 md:w-16 md:text-right">ตั้งแต่:</span>
                                <input type="number" step="0.01" name="rules[{{ $key }}][{{ $index }}][min]" value="{{ $rule['min'] }}" class="w-full md:w-24 ml-3 md:ml-0 p-2 text-center rounded-lg border border-slate-300 focus:border-indigo-500 disabled:bg-slate-100 disabled:text-slate-400" {{ $template->is_default ? 'disabled' : 'required' }}>
                            </div>
                            
                            <div class="flex items-center justify-between w-full md:w-auto">
                                <span class="text-sm font-bold text-slate-600 md:w-16 md:text-center">ถึง:</span>
                                <div class="flex items-center w-full md:w-auto ml-3 md:ml-0">
                                    <input type="number" step="0.01" name="rules[{{ $key }}][{{ $index }}][max]" value="{{ $rule['max'] }}" class="w-full md:w-24 p-2 text-center rounded-lg border border-slate-300 focus:border-indigo-500 disabled:bg-slate-100 disabled:text-slate-400" {{ $template->is_default ? 'disabled' : 'required' }}>
                                    <span class="text-xs text-slate-400 font-bold ml-2 md:mr-4 shrink-0">{{ $data['unit'] }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between w-full md:w-auto pt-2 md:pt-0 border-t border-slate-200 border-dashed md:border-none">
                                <span class="text-sm font-bold text-slate-600 md:w-auto">ให้:</span>
                                <select name="rules[{{ $key }}][{{ $index }}][score]" class="w-full md:w-32 ml-3 md:ml-2 p-2 rounded-lg border border-slate-300 font-bold focus:border-indigo-500 disabled:bg-slate-100 disabled:text-slate-400" {{ $template->is_default ? 'disabled' : '' }}>
                                    @foreach([0, 1, 2, 3] as $score)
                                        <option value="{{ $score }}" {{ $rule['score'] == $score ? 'selected' : '' }}>{{ $score }} คะแนน</option>
                                    @endforeach
                                </select>
                            </div>

                            @if(!$template->is_default)
                                <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 md:static md:-top-0 md:-right-0 ml-auto w-8 h-8 flex items-center justify-center rounded-full md:rounded-lg bg-rose-100 text-rose-500 md:bg-white md:text-rose-300 hover:text-white hover:bg-rose-500 border border-rose-200 transition-colors shadow-sm md:shadow-none" title="ลบแถว">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <!-- โซน AVPU (ครบทั้ง 5 ระดับ: A, C, V, P, U) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
                <h4 class="font-bold text-purple-700 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-brain"></i> ระดับการรู้สึกตัว (AVPU)
                </h4>
                @if(!$template->is_default)
                    <button type="button" onclick="addAvpuRow()" class="text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors">
                        <i class="fa-solid fa-plus text-emerald-500 mr-1"></i> เพิ่มค่า AVPU
                    </button>
                @endif
            </div>

            <div id="container-avpu" class="space-y-4">
                @foreach($savedRules['avpu'] ?? [] as $index => $rule)
                    <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4 bg-purple-50/50 p-4 md:p-3 rounded-xl border border-purple-100 group relative">
                        
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 md:gap-3 w-full md:w-auto">
                            <span class="text-sm font-bold text-purple-700 md:w-20 md:text-right shrink-0">ประเมินได้:</span>
                            <!-- 🌟 เพิ่มตัวเลือก C - Confused (สับสน) และขยายความกว้างเป็น md:w-64 ไม่ให้ตัวหนังสือโดนตัด -->
                            <select name="rules[avpu][{{ $index }}][value]" class="w-full md:w-64 p-2 rounded-lg border border-slate-300 focus:border-indigo-500 font-bold disabled:bg-slate-100 disabled:text-slate-400" {{ $template->is_default ? 'disabled' : '' }}>
                                <option value="A" {{ strtoupper($rule['value']) == 'A' ? 'selected' : '' }}>A - Alert (ตื่นตัว/รู้สึกตัวดี)</option>
                                <option value="C" {{ strtoupper($rule['value']) == 'C' ? 'selected' : '' }}>C - Confused (สับสน)</option>
                                <option value="V" {{ strtoupper($rule['value']) == 'V' ? 'selected' : '' }}>V - Voice (เรียกถึงลืมตา/รู้สึกตัว)</option>
                                <option value="P" {{ strtoupper($rule['value']) == 'P' ? 'selected' : '' }}>P - Pain (เจ็บถึงลืมตา/กระตุ้นเจ็บ)</option>
                                <option value="U" {{ strtoupper($rule['value']) == 'U' ? 'selected' : '' }}>U - Unresponsive (ไม่ตอบสนอง)</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between w-full md:w-auto pt-2 md:pt-0 border-t border-purple-200 border-dashed md:border-none md:ml-4">
                            <span class="text-sm font-bold text-slate-600">ให้:</span>
                            <select name="rules[avpu][{{ $index }}][score]" class="w-full md:w-32 ml-3 md:ml-2 p-2 rounded-lg border border-slate-300 font-bold focus:border-indigo-500 disabled:bg-slate-100 disabled:text-slate-400" {{ $template->is_default ? 'disabled' : '' }}>
                                @foreach([0, 1, 2, 3] as $score)
                                    <option value="{{ $score }}" {{ $rule['score'] == $score ? 'selected' : '' }}>{{ $score }} คะแนน</option>
                                @endforeach
                            </select>
                        </div>

                        @if(!$template->is_default)
                            <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 md:static md:-top-0 md:-right-0 ml-auto w-8 h-8 flex items-center justify-center rounded-full md:rounded-lg bg-rose-100 text-rose-500 md:bg-white md:text-rose-300 hover:text-white hover:bg-rose-500 border border-rose-200 transition-colors shadow-sm md:shadow-none">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        
        @if(!$template->is_default)
            <div class="bg-slate-800 p-4 rounded-2xl flex flex-col sm:flex-row sm:justify-end shadow-lg sticky bottom-6 z-10 mx-2 sm:mx-0">
                <button type="submit" class="w-full sm:w-auto flex justify-center items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-8 py-3 rounded-xl font-bold shadow-md transition-all hover:-translate-y-0.5">
                    <i class="fa-solid fa-floppy-disk"></i> บันทึกและอัปเดตเกณฑ์
                </button>
            </div>
        @endif

    </form>
</div>
@endsection

@section('scripts')
<script>
function addNumericRow(paramKey, unit) {
    const idx = Date.now();
    const html = `
        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4 bg-indigo-50/50 p-4 md:p-3 rounded-xl border border-indigo-100 group relative animate-fade-in-up">
            
            <div class="flex items-center justify-between w-full md:w-auto">
                <span class="text-sm font-bold text-slate-600 md:w-16 md:text-right">ตั้งแต่:</span>
                <input type="number" step="0.01" name="rules[${paramKey}][${idx}][min]" class="w-full md:w-24 ml-3 md:ml-0 p-2 text-center rounded-lg border border-slate-300 focus:border-indigo-500" required>
            </div>
            
            <div class="flex items-center justify-between w-full md:w-auto">
                <span class="text-sm font-bold text-slate-600 md:w-16 md:text-center">ถึง:</span>
                <div class="flex items-center w-full md:w-auto ml-3 md:ml-0">
                    <input type="number" step="0.01" name="rules[${paramKey}][${idx}][max]" class="w-full md:w-24 p-2 text-center rounded-lg border border-slate-300 focus:border-indigo-500" required>
                    <span class="text-xs text-slate-400 font-bold ml-2 md:mr-4 shrink-0">${unit}</span>
                </div>
            </div>

            <div class="flex items-center justify-between w-full md:w-auto pt-2 md:pt-0 border-t border-slate-200 border-dashed md:border-none">
                <span class="text-sm font-bold text-slate-600 md:w-auto">ให้:</span>
                <select name="rules[${paramKey}][${idx}][score]" class="w-full md:w-32 ml-3 md:ml-2 p-2 rounded-lg border border-slate-300 font-bold focus:border-indigo-500">
                    <option value="0">0 คะแนน</option>
                    <option value="1">1 คะแนน</option>
                    <option value="2">2 คะแนน</option>
                    <option value="3">3 คะแนน</option>
                </select>
            </div>

            <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 md:static md:-top-0 md:-right-0 ml-auto w-8 h-8 flex items-center justify-center rounded-full md:rounded-lg bg-rose-100 text-rose-500 md:bg-white md:text-rose-300 hover:text-white hover:bg-rose-500 border border-rose-200 transition-colors shadow-sm md:shadow-none">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    `;
    document.getElementById(`container-${paramKey}`).insertAdjacentHTML('beforeend', html);
}

function addAvpuRow() {
    const idx = Date.now();
    const html = `
        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4 bg-purple-50/50 p-4 md:p-3 rounded-xl border border-purple-100 group relative animate-fade-in-up">
            
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 md:gap-3 w-full md:w-auto">
                <span class="text-sm font-bold text-purple-700 md:w-20 md:text-right shrink-0">ประเมินได้:</span>
                <!-- 🌟 เพิ่มตัวเลือก C - Confused (สับสน) ตอนกดปุ่มเพิ่มแถวใหม่ด้วย -->
                <select name="rules[avpu][${idx}][value]" class="w-full md:w-64 p-2 rounded-lg border border-slate-300 focus:border-indigo-500 font-bold">
                    <option value="A">A - Alert (ตื่นตัว/รู้สึกตัวดี)</option>
                    <option value="C">C - Confused (สับสน)</option>
                    <option value="V">V - Voice (เรียกถึงลืมตา/รู้สึกตัว)</option>
                    <option value="P">P - Pain (เจ็บถึงลืมตา/กระตุ้นเจ็บ)</option>
                    <option value="U">U - Unresponsive (ไม่ตอบสนอง)</option>
                </select>
            </div>

            <div class="flex items-center justify-between w-full md:w-auto pt-2 md:pt-0 border-t border-purple-200 border-dashed md:border-none md:ml-4">
                <span class="text-sm font-bold text-slate-600">ให้:</span>
                <select name="rules[avpu][${idx}][score]" class="w-full md:w-32 ml-3 md:ml-2 p-2 rounded-lg border border-slate-300 font-bold focus:border-indigo-500">
                    <option value="0">0 คะแนน</option>
                    <option value="1">1 คะแนน</option>
                    <option value="2">2 คะแนน</option>
                    <option value="3">3 คะแนน</option>
                </select>
            </div>

            <button type="button" onclick="this.parentElement.remove()" class="absolute -top-2 -right-2 md:static md:-top-0 md:-right-0 ml-auto w-8 h-8 flex items-center justify-center rounded-full md:rounded-lg bg-rose-100 text-rose-500 md:bg-white md:text-rose-300 hover:text-white hover:bg-rose-500 border border-rose-200 transition-colors shadow-sm md:shadow-none">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    `;
    document.getElementById('container-avpu').insertAdjacentHTML('beforeend', html);
}
</script>
@endsection