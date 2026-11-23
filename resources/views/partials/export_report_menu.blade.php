@php
    $expModule   = $module ?? 'infirmary';
    $expParams   = $params ?? request()->query();
    $expLabel    = $label ?? 'ส่งออกรายงาน';
    $expBtnClass = $btnClass ?? 'bg-slate-800 hover:bg-slate-900 dark:bg-indigo-600 dark:hover:bg-indigo-500 text-white';
    $expIconCls  = $iconClass ?? 'text-emerald-400 dark:text-white';
    $expUid      = 'ksvr_exp_' . $expModule . '_' . uniqid();
    $dataUrl     = route('backend.reports.export.data', array_merge(['module' => $expModule], $expParams));
    $sheetUrl    = route('backend.reports.export.sheet', array_merge(['module' => $expModule], $expParams));
@endphp

<div class="relative inline-block w-full sm:w-auto print:hidden" data-ksvr-export-wrapper>
    <button type="button"
            onclick="window.toggleKsvrExportMenu('{{ $expUid }}', event)"
            class="w-full sm:w-auto justify-center px-4 py-2.5 {{ $expBtnClass }} rounded-2xl text-xs sm:text-sm font-bold shadow-sm inline-flex items-center gap-2 transition-all whitespace-nowrap">
        <i class="fa-solid fa-file-export {{ $expIconCls }}"></i>
        <span>{{ $expLabel }}</span>
        <i class="fa-solid fa-chevron-down text-[10px] opacity-80"></i>
    </button>

    <div id="{{ $expUid }}"
         class="hidden absolute right-0 mt-2 w-60 max-w-[calc(100vw-2rem)] bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 z-50 overflow-hidden p-1.5 space-y-1 text-left">
         
        <!-- 1. ดาวน์โหลด Excel (.xlsx) ทันที -->
        <button type="button"
                onclick="window.ksvrQuickExportExcel('{{ $dataUrl }}', '{{ $expUid }}')"
                class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl hover:bg-emerald-50 dark:hover:bg-emerald-900/30 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors">
            <span class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-file-excel"></i>
            </span>
            <div>
                <div class="font-black text-slate-800 dark:text-white">ไฟล์ Excel (.xlsx)</div>
                <div class="text-[10px] text-slate-400 font-medium">ข้อมูลครบทุกหน้าตามตัวกรอง</div>
            </div>
        </button>

        <!-- 2. ส่งออก PDF (A4 ทางการ) -->
        <a href="{{ $sheetUrl . (str_contains($sheetUrl, '?') ? '&' : '?') . 'auto=pdf' }}" target="_blank"
           onclick="document.getElementById('{{ $expUid }}')?.classList.add('hidden')"
           class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-900/30 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors">
            <span class="w-7 h-7 rounded-lg bg-rose-100 dark:bg-rose-900/50 text-rose-600 dark:text-rose-300 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-file-pdf"></i>
            </span>
            <div>
                <div class="font-black text-slate-800 dark:text-white">ไฟล์ PDF รายงาน (A4)</div>
                <div class="text-[10px] text-slate-400 font-medium">หัวกระดาษ รพ. พร้อมช่องลงนาม</div>
            </div>
        </a>

        <!-- 3. บันทึกเป็นรูปภาพ PNG (ส่ง LINE ผู้บริหาร) -->
        <a href="{{ $sheetUrl . (str_contains($sheetUrl, '?') ? '&' : '?') . 'auto=png' }}" target="_blank"
           onclick="document.getElementById('{{ $expUid }}')?.classList.add('hidden')"
           class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl hover:bg-sky-50 dark:hover:bg-sky-900/30 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors">
            <span class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-600 dark:text-sky-300 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-image"></i>
            </span>
            <div>
                <div class="font-black text-slate-800 dark:text-white">รูปภาพรายงาน (.PNG)</div>
                <div class="text-[10px] text-slate-400 font-medium">ความละเอียดสูง พร้อมส่ง LINE</div>
            </div>
        </a>

        <!-- 4. เปิดหน้าพรีวิว & พิมพ์ A4 -->
        <a href="{{ $sheetUrl }}" target="_blank"
           onclick="document.getElementById('{{ $expUid }}')?.classList.add('hidden')"
           class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold transition-colors border-t border-slate-100 dark:border-slate-700 mt-1 pt-2">
            <span class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-print"></i>
            </span>
            <span>พรีวิวกระดาษ / สั่งพิมพ์ A4</span>
        </a>
    </div>
</div>

@once
<script>
    window.toggleKsvrExportMenu = function(menuId, evt) {
        if (evt) evt.stopPropagation();
        document.querySelectorAll('[data-ksvr-export-wrapper] > div[id^="ksvr_exp_"]').forEach(el => {
            if (el.id !== menuId) el.classList.add('hidden');
        });
        document.getElementById(menuId)?.classList.toggle('hidden');
    };

    document.addEventListener('click', function() {
        document.querySelectorAll('[data-ksvr-export-wrapper] > div[id^="ksvr_exp_"]').forEach(el => {
            el.classList.add('hidden');
        });
    });

    function loadSheetJsLazy() {
        return new Promise((resolve, reject) => {
            if (typeof window.XLSX !== 'undefined') return resolve();
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('ไม่สามารถโหลดตัวสร้างไฟล์ Excel ได้'));
            document.head.appendChild(s);
        });
    }

    window.ksvrQuickExportExcel = async function(dataUrl, menuId) {
        document.getElementById(menuId)?.classList.add('hidden');

        Swal.fire({
            title: 'กำลังสร้างไฟล์ Excel (.xlsx)...',
            html: 'ระบบกำลังรวบรวมข้อมูลตามตัวกรองทั้งหมด',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            customClass: { popup: 'rounded-3xl' }
        });

        try {
            const [res] = await Promise.all([
                fetch(dataUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()),
                loadSheetJsLazy()
            ]);

            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || 'ไม่สามารถดึงข้อมูลได้');
            }

            const payload = res.data;
            const wb = XLSX.utils.book_new();

            (payload.sheets || []).forEach(sheet => {
                const aoa = [
                    [payload.title],
                    [payload.subtitle],
                    ['พิมพ์เมื่อ: ' + payload.printed_at + ' | ผู้จัดทำ: ' + payload.printed_by],
                    [],
                    sheet.headers,
                    ...sheet.rows
                ];
                const ws = XLSX.utils.aoa_to_sheet(aoa);

                if (sheet.cid_col !== null && sheet.cid_col !== undefined) {
                    const colIdx = Number(sheet.cid_col);
                    for (let r = 5; r < aoa.length; r++) {
                        const ref = XLSX.utils.encode_cell({ r, c: colIdx });
                        if (ws[ref]) {
                            ws[ref].t = 's';
                            ws[ref].z = '@';
                        }
                    }
                }

                ws['!cols'] = sheet.headers.map((h, cIdx) => {
                    let maxLen = String(h || '').length;
                    sheet.rows.forEach(row => {
                        const len = String(row[cIdx] ?? '').length;
                        if (len > maxLen) maxLen = len;
                    });
                    return { wch: Math.min(42, Math.max(10, maxLen + 4)) };
                });

                const safeName = String(sheet.name || 'Report').substring(0, 31).replace(/[\\\/\?\*\[\]\:]/g, '');
                XLSX.utils.book_append_sheet(wb, ws, safeName || 'Report');
            });

            XLSX.writeFile(wb, `${payload.filename || 'KSVR_Report'}.xlsx`);

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'ดาวน์โหลดไฟล์ Excel (.xlsx) สำเร็จ!',
                showConfirmButton: false,
                timer: 2500
            });
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'ส่งออกไม่สำเร็จ', text: err.message || 'กรุณาลองใหม่อีกครั้ง', customClass: { popup: 'rounded-3xl' } });
        }
    };
</script>
@endonce