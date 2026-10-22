<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Hasil Inspeksi QC</title>
    <style>
        @page{size:A4 landscape;margin:24px 28px}
        body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#19263a}
        h1{font-size:20px;margin:0 0 5px}
        h2{font-size:12px;margin:0 0 7px}
        h3{font-size:10px;margin:12px 0 5px}
        h4{font-size:9px;margin:10px 0 5px}
        p{margin:3px 0 10px}
        table{width:100%;border-collapse:collapse;margin-bottom:9px}
        th,td{padding:5px;border:1px solid #cbd3df;text-align:left;vertical-align:top}
        th{background:#edf1f7}
        .inspection{page-break-inside:avoid;margin-bottom:17px}
        .stamp{display:inline-block;padding:4px 6px;border:3px double #24795f;color:#24795f;font-weight:bold;text-align:center}
        .muted{color:#68758a}
        .footer{margin-top:14px;font-size:8px;color:#68758a}
        .report-group-summary{margin:4px 0 12px}
        .report-point-check{margin:12px 0 18px;padding:10px;border:1px solid #cbd3df}
        .report-point-check-images{width:100%}
        .report-point-check figure{display:inline-block;width:48%;margin:0 1% 10px;text-align:center;vertical-align:top}
        .report-point-check img{width:100%;max-height:430px;object-fit:contain}
        .report-point-check figcaption{margin-top:4px;font-weight:bold}
        .report-point-check-missing{padding:35px 8px;background:#edf1f7;color:#8a3540}
        .report-session{page-break-inside:auto;margin-top:14px}
        .report-inspector{margin:5px 0}
    </style>
</head>
<body>
    <h1>Laporan Hasil Inspeksi QC</h1>
    <p class="muted">Dibuat {{ $generatedAt->format('d/m/Y H:i') }} · {{ $reportGroups->count() }} laporan per tanggal, shift, dan part</p>
    @include('inspections.partials.report-content')
    <p class="footer">Dokumen dihasilkan oleh QC Inspect.</p>
</body>
</html>
