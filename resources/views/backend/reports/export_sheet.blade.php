<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }} - KSVR Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">

    @php
        // ฝังโลโก้เป็น Base64 เพื่อป้องกันปัญหา CORS / Tainted Canvas ตอนสร้าง PNG และ PDF
        $logoPath = public_path('images/logo5.png');
        $logoDataUri = (file_exists($logoPath) && is_readable($logoPath))
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : asset('images/logo5.png');
        $isPortrait = ($report['orientation'] ?? 'landscape') === 'portrait';
    @endphp

    <style>
        /* ใช้สีมาตรฐาน HEX/RGB ล้วน 100% เพื่อรองรับ html2canvas ทุกเบราว์เซอร์โดยไม่ติด oklch() ของ Tailwind v4 */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px 12px 40px;
            font-family: 'Prompt', sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        /* แถบเครื่องมือด้านบน */
        .export-toolbar {
            max-width: 1240px;
            margin: 0 auto 16px;
            background-color: #0f172a;
            color: #ffffff;
            border-radius: 16px;
            padding: 12px 16px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.22);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            position: sticky;
            top: 10px;
            z-index: 50;
        }
        .toolbar-left, .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .toolbar-title {
            font-size: 13px;
            font-weight: 700;
            color: #6ee7b7;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-exp {
            border: none;
            cursor: pointer;
            font-family: 'Prompt', sans-serif;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.15s ease, opacity 0.15s ease;
            color: #ffffff;
            text-decoration: none;
        }
        .btn-exp:disabled { opacity: 0.65; cursor: wait; }
        .btn-back  { background-color: #1e293b; color: #e2e8f0; }
        .btn-back:hover  { background-color: #334155; }
        .btn-print { background-color: #e11d48; }
        .btn-print:hover { background-color: #be123c; }
        .btn-pdf   { background-color: #9f1239; border: 1px solid #fb7185; }
        .btn-pdf:hover   { background-color: #be123c; }
        .btn-png   { background-color: #0284c7; }
        .btn-png:hover   { background-color: #0369a1; }
        .btn-excel { background-color: #059669; }
        .btn-excel:hover { background-color: #047857; }

        /* พื้นที่กระดาษรายงานทางการ */
        #report-capture-area {
            max-width: 1240px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            padding: 28px 32px;
        }

        /* หัวกระดาษ รพ. */
        .sheet-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .sheet-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .sheet-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .sheet-org {
            font-size: 11px;
            font-weight: 700;
            color: #047857;
            letter-spacing: 0.3px;
        }
        .sheet-title {
            margin: 2px 0 0;
            font-size: 19px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.3;
        }
        .sheet-subtitle {
            margin: 3px 0 0;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }
        .sheet-meta {
            text-align: right;
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
        }
        .sheet-meta b { color: #334155; }

        /* กล่องสรุปตัวเลข KPI */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 14px;
        }
        .summary-label {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
        }
        .summary-value {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }

        /* ตารางข้อมูล */
        .sheet-section { margin-bottom: 22px; }
        .section-heading {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 800;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-dot {
            width: 8px;
            height: 8px;
            border-radius: 9999px;
            background-color: #059669;
            display: inline-block;
            flex-shrink: 0;
        }
        .table-wrap { width: 100%; overflow-x: auto; }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            background-color: #ffffff;
        }
        table.report-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 700;
            text-align: center;
            border: 1px solid #475569;
            padding: 7px 6px;
            white-space: nowrap;
        }
        table.report-table td {
            border: 1px solid #94a3b8;
            padding: 6px 7px;
            color: #1e293b;
            vertical-align: middle;
            line-height: 1.4;
        }
        table.report-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        .cell-center { text-align: center; font-weight: 700; }
        .cell-nowrap { white-space: nowrap; }
        .cell-check  { text-align: center; font-weight: 800; color: #047857; background-color: #ecfdf5; }
        .cell-cross  { text-align: center; font-weight: 800; color: #be123c; background-color: #fff1f2; }
        .cell-empty  { text-align: center; padding: 28px 10px; color: #94a3b8; font-weight: 700; }

        /* ส่วนท้ายลงนาม */
        .signature-grid {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            font-size: 11.5px;
            color: #334155;
            text-align: center;
        }
        .sig-box { line-height: 2.2; }
        .sig-line { margin-top: 22px; }

        @media (max-width: 640px) {
            #report-capture-area { padding: 18px 16px; }
            .sheet-meta { text-align: left; }
            .signature-grid { grid-template-columns: 1fr; }
        }

        @page {
            size: A4 {{ $isPortrait ? 'portrait' : 'landscape' }};
            margin: 10mm;
        }
        @media print {
            body { background-color: #ffffff !important; padding: 0 !important; }
            .no-print { display: none !important; }
            #report-capture-area {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            table.report-table { page-break-inside: auto; }
            table.report-table tr { page-break-inside: avoid; page-break-after: auto; }
            table.report-table thead { display: table-header-group; }
        }
    </style>
</head>
<body>

    <!-- 🛠️ แถบเครื่องมือส่งออกด้านบน -->
    <div class="export-toolbar no-print">
        <div class="toolbar-left">
            <button type="button" onclick="window.close(); history.back();" class="btn-exp btn-back">
                <i class="fa-solid fa-arrow-left"></i> กลับ
            </button>
            <span class="toolbar-title">
                <i class="fa-solid fa-file-shield"></i> พร้อมส่งออกรายงานทางการ
            </span>
        </div>

        <div class="toolbar-actions">
            <button type="button" onclick="window.print()" class="btn-exp btn-print">
                <i class="fa-solid fa-print"></i> พิมพ์ / บันทึก PDF (คมชัดสุด)
            </button>

            <button type="button" id="btn-dl-pdf" onclick="downloadReportAsPdf()" class="btn-exp btn-pdf">
                <i class="fa-solid fa-file-pdf"></i> ดาวน์โหลดไฟล์ .PDF
            </button>

            <button type="button" id="btn-dl-png" onclick="downloadReportAsImage()" class="btn-exp btn-png">
                <i class="fa-solid fa-image"></i> บันทึกรูปภาพ (.PNG ส่ง LINE)
            </button>

            <button type="button" onclick="downloadReportAsExcel()" class="btn-exp btn-excel">
                <i class="fa-solid fa-file-excel"></i> ดาวน์โหลด Excel (.XLSX)
            </button>
        </div>
    </div>

    <!-- 📄 พื้นที่กระดาษรายงานทางการ A4 -->
    <div id="report-capture-area">
        <div class="sheet-header">
            <div class="sheet-brand">
                <img src="{{ $logoDataUri }}" alt="KSVR Logo" class="sheet-logo">
                <div>
                    <div class="sheet-org">โรงพยาบาลค่ายกฤษณ์สีวะรา (KSVR Hospital) • M-CARE System</div>
                    <h1 class="sheet-title">{{ $report['title'] }}</h1>
                    <p class="sheet-subtitle">{{ $report['subtitle'] }}</p>
                </div>
            </div>
            <div class="sheet-meta">
                <div><b>พิมพ์เมื่อ:</b> {{ $report['printed_at'] }}</div>
                <div><b>ผู้จัดทำ:</b> {{ $report['printed_by'] }}</div>
            </div>
        </div>

        @if(!empty($report['summary']))
            <div class="summary-grid">
                @foreach($report['summary'] as $sItem)
                    <div class="summary-card">
                        <div class="summary-label">{{ $sItem['label'] }}</div>
                        <div class="summary-value">{{ $sItem['value'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        @foreach($report['sheets'] as $sheet)
            <div class="sheet-section">
                <h2 class="section-heading">
                    <span class="section-dot"></span>
                    <span>{{ $sheet['title'] }}</span>
                </h2>

                <div class="table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                @foreach($sheet['headers'] as $head)
                                    <th>{{ $head }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sheet['rows'] as $row)
                                <tr>
                                    @foreach($row as $cIdx => $cell)
                                        @php
                                            $headLabel = (string) ($sheet['headers'][$cIdx] ?? '');
                                            $isCheck   = ($cell === '✓');
                                            $isCross   = ($cell === '✕');
                                            $isNoWrap  = in_array($headLabel, ['ลำดับ', 'วันที่นัด', 'เวลา', 'รอบเวลา', 'เลข ปชช.', 'HN', 'ผลัด', 'จำนวนวัน', 'วันที่ขึ้นทะเบียน'], true);

                                            $tdClass = $isCheck
                                                ? 'cell-check'
                                                : ($isCross
                                                    ? 'cell-cross'
                                                    : ($loop->first ? 'cell-center cell-nowrap' : ($isNoWrap ? 'cell-nowrap' : '')));
                                        @endphp
                                        <td class="{{ $tdClass }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($sheet['headers']) }}" class="cell-empty">
                                        ไม่พบข้อมูลในช่วงเวลาหรือตัวกรองที่เลือก
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="signature-grid">
            <div class="sig-box">
                <div>ผู้จัดทำรายงาน / ผู้บันทึก</div>
                <div class="sig-line">ลงชื่อ ...........................................................</div>
                <div>({{ $report['printed_by'] }}) • วันที่ {{ $report['printed_at'] }}</div>
            </div>
            <div class="sig-box">
                <div>ผู้ตรวจสอบ / รับทราบรายงาน</div>
                <div class="sig-line">ลงชื่อ ...........................................................</div>
                <div>(...........................................................) • ตำแหน่ง .......................................</div>
            </div>
        </div>
    </div>

    <!-- ไลบรารีส่งออกฝั่ง Client (SheetJS + html2canvas + jsPDF) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

    <script>
        const reportPayload = @json($report);
        const autoAction    = @json($autoAction);

        // ตัวช่วยเรนเดอร์กระดาษเป็น Canvas (รอฟอนต์ไทยพร้อม + คำนวณ scale ปลอดภัยไม่เกินขีดจำกัดเบราว์เซอร์)
        async function captureReportCanvas() {
            const el = document.getElementById('report-capture-area');
            if (!el || typeof window.html2canvas === 'undefined') {
                throw new Error('ไลบรารีสร้างภาพยังโหลดไม่เสร็จ กรุณารอสักครู่แล้วลองใหม่');
            }

            if (document.fonts && document.fonts.ready) {
                try { await document.fonts.ready; } catch (e) {}
            }

            // จำกัดความสูงรวมไม่ให้เกิน 14,000px เพื่อกันเบราว์เซอร์/มือถือค้างในเคสที่มีหลายร้อยแถว
            const captureWidth = Math.max(1200, el.scrollWidth);
            const naturalHeight = Math.max(el.scrollHeight, el.offsetHeight, 1);
            const safeScale = Math.max(1, Math.min(2, Math.floor((14000 / naturalHeight) * 10) / 10));

            return await window.html2canvas(el, {
                scale: safeScale,
                useCORS: true,
                allowTaint: false,
                backgroundColor: '#ffffff',
                logging: false,
                windowWidth: captureWidth,
                scrollX: 0,
                scrollY: 0
            });
        }

        // 📊 1. ส่งออกเป็นไฟล์ Excel (.xlsx แท้)
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

                const safeSheetName = String(sheet.name || 'Report').substring(0, 31).replace(/[\\\/\?\*\[\]\:]/g, '');
                XLSX.utils.book_append_sheet(wb, ws, safeSheetName || 'Report');
            });

            XLSX.writeFile(wb, `${reportPayload.filename || 'KSVR_Report'}.xlsx`);
        }

        // 🖼️ 2. ส่งออกเป็นรูปภาพ PNG ความละเอียดสูง (ใช้ Blob เพื่อรองรับ PWA และภาพขนาดใหญ่)
        async function downloadReportAsImage() {
            const btn = document.getElementById('btn-dl-png');
            const origText = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสร้างภาพ...'; }

            try {
                const canvas = await captureReportCanvas();
                await new Promise((resolve, reject) => {
                    canvas.toBlob((blob) => {
                        if (!blob) return reject(new Error('ไม่สามารถสร้างไฟล์ภาพได้'));
                        const url = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.download = `${reportPayload.filename || 'KSVR_Report'}.png`;
                        link.href = url;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        setTimeout(() => URL.revokeObjectURL(url), 5000);
                        resolve();
                    }, 'image/png');
                });
            } catch (e) {
                console.error('Export PNG Error:', e);
                alert('ไม่สามารถสร้างรูปภาพได้: ' + (e?.message || 'กรุณาลองใหม่อีกครั้ง'));
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            }
        }

        // 📄 3. ดาวน์โหลดเป็นไฟล์ .PDF โดยตรง (ตัดแบ่งหน้า A4 ทีละแผ่น ขอบกระดาษสะอาดไม่ล้น)
        async function downloadReportAsPdf() {
            const btn = document.getElementById('btn-dl-pdf');
            const origText = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสร้าง PDF...'; }

            try {
                if (typeof window.jspdf === 'undefined') {
                    throw new Error('ไลบรารี PDF ยังโหลดไม่เสร็จ กรุณารอสักครู่แล้วกดอีกครั้ง');
                }

                const canvas = await captureReportCanvas();
                const { jsPDF } = window.jspdf;
                const orientation = reportPayload.orientation === 'portrait' ? 'portrait' : 'landscape';
                const pdf = new jsPDF({ orientation, unit: 'mm', format: 'a4' });

                const pageWidth  = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const margin     = 8;
                const usableW    = pageWidth - (margin * 2);
                const usableH    = pageHeight - (margin * 2);

                const pxPerMm = canvas.width / usableW;
                const pageSliceHeightPx = Math.floor(usableH * pxPerMm);

                let renderedY = 0;
                let pageIndex = 0;

                while (renderedY < canvas.height) {
                    const sliceH = Math.min(pageSliceHeightPx, canvas.height - renderedY);
                    const sliceCanvas = document.createElement('canvas');
                    sliceCanvas.width = canvas.width;
                    sliceCanvas.height = sliceH;

                    const ctx = sliceCanvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
                    ctx.drawImage(canvas, 0, renderedY, canvas.width, sliceH, 0, 0, canvas.width, sliceH);

                    if (pageIndex > 0) pdf.addPage();
                    const sliceHeightMm = sliceH / pxPerMm;
                    pdf.addImage(sliceCanvas.toDataURL('image/jpeg', 0.95), 'JPEG', margin, margin, usableW, sliceHeightMm);

                    renderedY += sliceH;
                    pageIndex++;
                }

                pdf.save(`${reportPayload.filename || 'KSVR_Report'}.pdf`);
            } catch (e) {
                console.error('Export PDF Error:', e);
                alert('ไม่สามารถสร้างไฟล์ PDF ได้ (' + (e?.message || 'เกิดข้อผิดพลาด') + ') กรุณาใช้ปุ่ม "พิมพ์ / บันทึก PDF" แทนครับ');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            }
        }

        window.addEventListener('load', () => {
            if (autoAction === 'png') {
                setTimeout(() => downloadReportAsImage(), 600);
            } else if (autoAction === 'pdf') {
                setTimeout(() => downloadReportAsPdf(), 600);
            } else if (autoAction === 'print') {
                setTimeout(() => window.print(), 500);
            }
        });
    </script>
</body>
</html>