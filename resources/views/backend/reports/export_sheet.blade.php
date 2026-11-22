<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }} - KSVR Hospital</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Prompt', sans-serif; background: #f1f5f9; color: #0f172a; }
        @page { size: A4 {{ ($report['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape' }}; margin: 10mm; }
        @media print {
            body { background: #ffffff !important; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            #report-capture-area { box-shadow: none !important; border: none !important; margin: 0 !important; padding: 0 !important; max-width: 100% !important; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body class="py-4 px-3 sm:px-6">

    <!-- 🛠️ แถบเครื่องมือส่งออกด้านบน (ซ่อนอัตโนมัติตอนพิมพ์และตอนแคปรูป/PDF) -->
    <div class="no-print max-w-[1200px] mx-auto mb-4 bg-slate-900 text-white rounded-2xl p-3.5 shadow-lg flex flex-wrap items-center justify-between gap-3 sticky top-3 z-50">
        <div class="flex items-center gap-2.5 min-w-0">
            <button type="button" onclick="window.close(); history.back();" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold inline-flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> กลับ
            </button>
            <span class="text-xs sm:text-sm font-bold truncate text-emerald-300">
                <i class="fa-solid fa-file-shield mr-1"></i>พร้อมส่งออกรายงานทางการ
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="window.print()"
                    class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-black inline-flex items-center gap-1.5 shadow-sm transition-colors">
                <i class="fa-solid fa-print"></i> พิมพ์ / บันทึก PDF (คมชัดสุด)
            </button>

            <button type="button" id="btn-dl-pdf" onclick="downloadReportAsPdf()"
                    class="px-3.5 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-600 text-rose-200 hover:text-white border border-rose-400/40 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-file-pdf"></i> ดาวน์โหลดไฟล์ .PDF
            </button>

            <button type="button" id="btn-dl-png" onclick="downloadReportAsImage()"
                    class="px-3.5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-black inline-flex items-center gap-1.5 shadow-sm transition-colors">
                <i class="fa-solid fa-image"></i> บันทึกรูปภาพ (.PNG ส่ง LINE)
            </button>

            <button type="button" onclick="downloadReportAsExcel()"
                    class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black inline-flex items-center gap-1.5 shadow-sm transition-colors">
                <i class="fa-solid fa-file-excel"></i> ดาวน์โหลด Excel (.XLSX)
            </button>
        </div>
    </div>

    <!-- 📄 พื้นที่กระดาษรายงานทางการ A4 (เป้าหมายสำหรับ Print / PDF / PNG) -->
    <div id="report-capture-area" class="max-w-[1200px] mx-auto bg-white rounded-2xl shadow-xl border border-slate-200 p-6 sm:p-8 space-y-5">

        <!-- หัวกระดาษโรงพยาบาล -->
        <div class="flex items-start justify-between gap-4 border-b-2 border-slate-800 pb-4">
            <div class="flex items-center gap-3.5">
                <img src="{{ asset('images/logo5.png') }}" alt="KSVR Logo" class="w-14 h-14 object-contain shrink-0">
                <div>
                    <div class="text-xs font-bold text-emerald-700 uppercase tracking-wider">โรงพยาบาลค่ายกฤษณ์สีวะรา (KSVR Hospital) • M-CARE System</div>
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 mt-0.5">{{ $report['title'] }}</h1>
                    <p class="text-xs font-semibold text-slate-600 mt-0.5">{{ $report['subtitle'] }}</p>
                </div>
            </div>
            <div class="text-right text-[11px] text-slate-500 shrink-0 space-y-0.5">
                <div><b>พิมพ์เมื่อ:</b> {{ $report['printed_at'] }}</div>
                <div><b>ผู้จัดทำ:</b> {{ $report['printed_by'] }}</div>
            </div>
        </div>

        <!-- กล่องสรุปตัวเลข KPI ด้านบน -->
        @if(!empty($report['summary']))
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                @foreach($report['summary'] as $sItem)
                    <div class="px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="text-[10px] font-bold text-slate-500 uppercase">{{ $sItem['label'] }}</div>
                        <div class="text-sm font-black text-slate-900 mt-0.5">{{ $sItem['value'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- ตารางข้อมูล (รองรับทั้ง 1 ตาราง และ 2 ตารางสำหรับ Care Chart) -->
        @foreach($report['sheets'] as $sheet)
            <div class="space-y-2">
                <h2 class="text-xs sm:text-sm font-black text-slate-800 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-600 inline-block"></span>
                    <span>{{ $sheet['title'] }}</span>
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-[11px] border-collapse border border-slate-700">
                        <thead>
                            <tr class="bg-slate-100 text-slate-900 font-black">
                                @foreach($sheet['headers'] as $head)
                                    <th class="border border-slate-700 px-2 py-2 text-center whitespace-nowrap">{{ $head }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sheet['rows'] as $row)
                                <tr class="odd:bg-white even:bg-slate-50/60">
                                    @foreach($row as $cell)
                                        @php
                                            $isCheck = ($cell === '✓');
                                            $isCross = ($cell === '✕');
                                            $alignCls = ($loop->first || $isCheck || $isCross) ? 'text-center font-bold' : 'text-left';
                                            $colorCls = $isCheck ? 'text-emerald-700 bg-emerald-50/70' : ($isCross ? 'text-rose-700 bg-rose-50/70' : 'text-slate-800');
                                        @endphp
                                        <td class="border border-slate-400 px-2 py-1.5 {{ $alignCls }} {{ $colorCls }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($sheet['headers']) }}" class="border border-slate-400 py-8 text-center text-slate-400 font-bold">
                                        ไม่พบข้อมูลในช่วงเวลาหรือตัวกรองที่เลือก
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <!-- ส่วนท้ายสำหรับลงนามรายงานผู้บังคับบัญชา -->
        <div class="pt-6 mt-4 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs text-slate-700">
            <div class="text-center space-y-6">
                <div>ผู้จัดทำรายงาน / ผู้บันทึก</div>
                <div>ลงชื่อ ...........................................................</div>
                <div>({{ $report['printed_by'] }}) • วันที่ {{ $report['printed_at'] }}</div>
            </div>
            <div class="text-center space-y-6">
                <div>ผู้ตรวจสอบ / รับทราบรายงาน</div>
                <div>ลงชื่อ ...........................................................</div>
                <div>(...........................................................) • ตำแหน่ง .......................................</div>
            </div>
        </div>
    </div>

    <!-- ไลบรารีส่งออกฝั่ง Client (SheetJS + html2canvas + jsPDF — 0 โหลดเซิร์ฟเวอร์) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

    <script>
        const reportPayload = @json($report);
        const autoAction    = @json($autoAction);

        // 📊 1. ส่งออกเป็นไฟล์ Excel (.xlsx แท้) รองรับหลายชีต + คอลัมน์เลข ปชช. เป็น Text
        function downloadReportAsExcel() {
            if (typeof XLSX === 'undefined') {
                alert('กำลังโหลดไลบรารี Excel กรุณารอสักครู่แล้วกดอีกครั้ง');
                return;
            }
            const wb = XLSX.utils.book_new();

            (reportPayload.sheets || []).forEach(sheet => {
                const aoa = [
                    [reportPayload.title],
                    [reportPayload.subtitle],
                    ['พิมพ์เมื่อ: ' + reportPayload.printed_at + ' | ผู้จัดทำ: ' + reportPayload.printed_by],
                    [],
                    sheet.headers,
                    ...sheet.rows
                ];

                const ws = XLSX.utils.aoa_to_sheet(aoa);

                // บังคับคอลัมน์เลข ปชช. 13 หลักให้เป็น String กันเพี้ยนเป็น 1.47E+12
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

                // คำนวณความกว้างคอลัมน์อัตโนมัติ
                ws['!cols'] = sheet.headers.map((h, cIdx) => {
                    let maxLen = String(h || '').length;
                    sheet.rows.forEach(row => {
                        const len = String(row[cIdx] ?? '').length;
                        if (len > maxLen) maxLen = len;
                    });
                    return { wch: Math.min(42, Math.max(10, maxLen + 4)) };
                });

                const safeSheetName = String(sheet.name || 'Report').substring(0, 31).replace(/[\\\/\?\*\[\]\:]/g, '');
                XLSX.utils.book_append_sheet(wb, ws, safeSheetName || 'Report');
            });

            XLSX.writeFile(wb, `${reportPayload.filename || 'KSVR_Report'}.xlsx`);
        }

        // 🖼️ 2. ส่งออกเป็นรูปภาพ PNG ความละเอียดสูง (scale 2x คมชัดพร้อมส่ง LINE)
        async function downloadReportAsImage() {
            const btn = document.getElementById('btn-dl-png');
            const el  = document.getElementById('report-capture-area');
            if (!el || typeof html2canvas === 'undefined') return;

            const origText = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสร้างภาพ...'; }

            try {
                const canvas = await html2canvas(el, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    windowWidth: Math.max(1200, el.scrollWidth)
                });
                const link = document.createElement('a');
                link.download = `${reportPayload.filename || 'KSVR_Report'}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
            } catch (e) {
                alert('ไม่สามารถสร้างรูปภาพได้ กรุณาลองใหม่อีกครั้ง');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            }
        }

        // 📄 3. ดาวน์โหลดเป็นไฟล์ .PDF โดยตรง (แบ่งหน้า A4 แนวนอนอัตโนมัติสำหรับข้อมูลหลายหน้า)
        async function downloadReportAsPdf() {
            const btn = document.getElementById('btn-dl-pdf');
            const el  = document.getElementById('report-capture-area');
            if (!el || typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') return;

            const origText = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสร้าง PDF...'; }

            try {
                const canvas = await html2canvas(el, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    windowWidth: Math.max(1200, el.scrollWidth)
                });

                const imgData = canvas.toDataURL('image/jpeg', 0.95);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

                const pageWidth  = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const margin     = 8;
                const usableW    = pageWidth - (margin * 2);
                const usableH    = pageHeight - (margin * 2);
                const imgH       = (canvas.height * usableW) / canvas.width;

                let heightLeft = imgH;
                let position   = margin;

                pdf.addImage(imgData, 'JPEG', margin, position, usableW, imgH);
                heightLeft -= usableH;

                while (heightLeft > 0) {
                    position = margin - (imgH - heightLeft);
                    pdf.addPage();
                    pdf.addImage(imgData, 'JPEG', margin, position, usableW, imgH);
                    heightLeft -= usableH;
                }

                pdf.save(`${reportPayload.filename || 'KSVR_Report'}.pdf`);
            } catch (e) {
                alert('ไม่สามารถสร้างไฟล์ PDF ได้ กรุณาใช้ปุ่ม "พิมพ์ / บันทึก PDF" แทนครับ');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            }
        }

        // ทำงานอัตโนมัติหากเปิดหน้านี้มาจากปุ่มลัด PNG / PDF / Print
        window.addEventListener('load', () => {
            if (autoAction === 'png') {
                setTimeout(() => downloadReportAsImage(), 450);
            } else if (autoAction === 'pdf') {
                setTimeout(() => downloadReportAsPdf(), 450);
            } else if (autoAction === 'print') {
                setTimeout(() => window.print(), 450);
            }
        });
    </script>
</body>
</html>