@extends('layouts.app')

@section('title', 'Laporan Inspeksi')
@section('eyebrow', 'LAPORAN HASIL INSPEKSI')

@section('content')
<div class="page-heading report-toolbar">
    <div>
        <p class="eyebrow">LAPORAN QUALITY CONTROL</p>
        <h1>Laporan hasil inspeksi</h1>
        <p class="muted">{{ $reportGroups->count() }} laporan per tanggal, shift, dan part{{ $singleInspection ? ' · '.$singleInspection->shift.' · '.($singleInspection->product?->nama ?? 'Produk dihapus') : ' sesuai filter' }} · Dibuat {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </div>
    <div class="report-actions">
        @if (! $singleInspection)
            <a class="button button-secondary" href="{{ route('inspections.report', array_merge($filters, ['print' => 1])) }}">Cetak</a>
            <a class="button button-primary" href="{{ route('inspections.report.pdf', $filters) }}">Unduh PDF</a>
        @endif
    </div>
</div>
@if (! $singleInspection)
    <section class="panel report-filters">
        <div class="panel-heading"><div><h2>Filter laporan</h2><p class="muted">Pilih rentang dan kriteria inspeksi yang ingin ditampilkan.</p></div></div>
        <form method="GET" action="{{ route('inspections.report') }}" class="filter-grid">
            <label class="field"><span>Dari tanggal</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="field"><span>Sampai tanggal</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <label class="field"><span>Produk</span>
                <select name="product_id"><option value="">Semua produk</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->kode }} · {{ $product->nama }}</option>@endforeach</select>
            </label>
            <label class="field"><span>Tipe produk</span>
                <select name="product_type"><option value="">Semua tipe</option>@foreach ($productTypes as $type)<option value="{{ $type }}" @selected(($filters['product_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select>
            </label>
            <label class="field"><span>Area / line</span>
                <select name="area_id"><option value="">Semua area</option>@foreach ($areas as $area)<option value="{{ $area->id }}" @selected((string) ($filters['area_id'] ?? '') === (string) $area->id)>{{ $area->nama }}</option>@endforeach</select>
            </label>
            <label class="field"><span>Shift</span>
                <select name="shift"><option value="">Semua shift</option>@foreach (['Shift 1', 'Shift 2'] as $shift)<option value="{{ $shift }}" @selected(($filters['shift'] ?? '') === $shift)>{{ $shift }}</option>@endforeach</select>
            </label>
            <label class="field"><span>Hasil</span><select name="result"><option value="">Semua hasil</option><option value="OK" @selected(in_array(($filters['result'] ?? ''), ['OK', 'Lulus'], true))>OK</option><option value="NG" @selected(($filters['result'] ?? '') === 'NG')>NG</option></select></label>
            <div class="filter-actions"><button class="button button-secondary" type="submit">Tampilkan laporan</button><a class="text-link" href="{{ route('inspections.report') }}">Reset</a></div>
        </form>
    </section>
@endif
@include('inspections.partials.report-content')
@endsection

@push('styles')
<style>
    .report-actions{display:flex;gap:8px}
    .report-filters{margin-bottom:16px}
    .report-section{margin-bottom:16px}
    .report-table{width:100%;border-collapse:collapse;font-size:10px}
    .report-table th,.report-table td{padding:7px;border:1px solid #dfe5ed;text-align:left;vertical-align:top}
    .report-table th{background:#f4f6fa;color:#526078}
    .report-inspection{break-inside:avoid;margin-bottom:22px}
    .report-inspection h2{margin-bottom:9px}
    .report-stamp{display:inline-block;padding:4px 7px;border:3px double #24795f;color:#24795f;font-weight:800;text-align:center;transform:rotate(-4deg)}
    .report-detail-title{margin:10px 0 5px;font-size:11px;font-weight:700}
    .report-group-summary{margin:4px 0 12px}
    .report-point-check{margin:12px 0 18px;padding:12px;border:1px solid #dfe5ed;border-radius:10px}
    .report-point-check-images{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
    .report-point-check figure{margin:0;text-align:center}
    .report-point-check img{display:block;width:100%;max-height:520px;object-fit:contain}
    .report-point-check figcaption{margin-top:5px;font-weight:700}
    .report-point-check-missing{padding:40px 10px;background:#f4f6fa;color:#8a3540}
    .report-session{break-inside:avoid;margin-top:16px}
    .report-session h4{margin:10px 0 5px;font-size:10px}
    .report-inspector{margin:5px 0}
    @media print{
        @page{size:A4 landscape;margin:12mm}
        body,.main-area{background:#fff!important}
        .sidebar,.topbar,.report-actions{display:none!important}
        .main-area{margin:0!important}
        .page-content{max-width:none;padding:0!important}
        .panel{box-shadow:none;border:0;padding:0}
        .report-inspection{break-inside:auto}
        .report-point-check img{max-height:420px}
    }
</style>
@endpush

@if (request()->boolean('print'))
    @push('scripts')
        <script>window.addEventListener('load', () => window.print());</script>
    @endpush
@endif
