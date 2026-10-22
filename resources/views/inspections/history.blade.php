@extends('layouts.app')

@section('title', 'Riwayat Inspeksi')
@section('eyebrow', 'RIWAYAT HASIL INSPEKSI')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">QUALITY CONTROL</p>
        <h1>Riwayat inspeksi</h1>
        <p class="muted">Telusuri hasil inspeksi QC dan ekspor riwayat ke Excel.</p>
    </div>
    <div class="dashboard-heading-actions">
        <a class="button button-secondary" href="{{ route('inspection-reports.pdf', request()->query()) }}">↓ Unduh PDF</a>
        <a class="button button-secondary" href="{{ route('inspection-reports.export', request()->query()) }}">↓ Ekspor Excel</a>
    </div>
</div>

<section class="panel history-panel">
    <div class="panel-heading">
        <div><h2>Hasil inspeksi</h2><p class="muted">{{ $inspections->total() }} catatan ditemukan</p></div>
    </div>
    <form method="GET" action="{{ route('inspection-reports.index') }}" class="filter-grid">
        <label class="field"><span>Dari tanggal</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="field"><span>Sampai tanggal</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label class="field"><span>Produk</span>
            <select name="product_id"><option value="">Semua produk</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->kode }} · {{ $product->nama }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Area / line</span>
            <select name="area_id"><option value="">Semua area</option>@foreach ($areas as $area)<option value="{{ $area->id }}" @selected((string) ($filters['area_id'] ?? '') === (string) $area->id)>{{ $area->nama }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Hasil</span><select name="result"><option value="">Semua hasil</option><option value="OK" @selected(in_array(($filters['result'] ?? ''), ['OK', 'Lulus'], true))>OK</option><option value="NG" @selected(($filters['result'] ?? '') === 'NG')>NG</option></select></label>
        <div class="filter-actions"><button class="button button-secondary" type="submit">Terapkan filter</button><a class="text-link" href="{{ route('inspection-reports.index') }}">Reset</a></div>
    </form>
    @if ($inspections->isEmpty())
        <div class="empty-state compact"><span class="empty-icon">⌕</span><strong>Tidak ada data inspeksi</strong><p>Ubah filter atau buat inspeksi baru.</p></div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tanggal</th><th>Produk</th><th>Gedung / Area</th><th>Inspector</th><th>Shift / Sesi</th><th>Sample</th><th>Hasil</th><th>Approval</th><th>Aksi</th></tr></thead>
                <tbody>
                    @foreach ($inspections as $inspection)
                        <tr>
                            <td>{{ $inspection->tanggal->format('d M Y') }}</td>
                            <td><strong>{{ $inspection->product?->kode ?? '—' }}</strong><small class="table-subtitle">{{ $inspection->product?->nama ?? 'Produk dihapus' }}</small></td>
                            <td>{{ $inspection->area?->parent?->nama ?? $inspection->area?->nama ?? '—' }}<small class="table-subtitle">{{ $inspection->area?->parent ? $inspection->area?->nama : 'Gedung' }}</small></td>
                            <td>{{ $inspection->inspectorName }}</td>
                            <td>{{ $inspection->shift }}<small class="table-subtitle">{{ $inspection->sesi }} · Dimensi {{ $inspection->measurement_mode === 'automatic' ? 'otomatis' : 'manual' }}</small></td>
                            <td>{{ number_format($inspection->sample) }}</td>
                            <td><span @class(['badge', 'badge-ng' => $inspection->hasil_akhir === 'NG', 'badge-pass' => $inspection->hasil_akhir === 'OK'])>{{ $inspection->hasil_akhir }}</span></td>
                            <td>
                                @if ($inspection->approved)
                                    <span class="qc-stamp" title="Disetujui {{ $inspection->approved_at?->format('d M Y H:i') }} oleh {{ $inspection->approver?->name }}">QC 2W<br>DPM</span>
                                @elseif (auth()->user()->isAdmin())
                                    <form method="POST" action="{{ route('inspections.approve', $inspection) }}" onsubmit="return confirm('Setujui laporan inspeksi dan bubuhkan stempel QC 2W DPM?')">@csrf<button class="icon-button" type="submit">Setujui</button></form>
                                @else
                                    <span class="badge badge-neutral">Menunggu</span>
                                @endif
                            </td>
                            <td class="row-actions">
                                <a class="icon-button" href="{{ route('inspections.report.single', $inspection) }}">Lihat laporan</a>
                                <a class="icon-button" href="{{ route('inspections.report.single.pdf', $inspection) }}">PDF</a>
                                @if ($inspection->hasil_akhir === 'NG')
                                    @php($pending4mCount = count($inspection->pendingNgPointChecks()))
                                    @if ($pending4mCount > 0 && auth()->user()->canWriteInspections())
                                        <a class="icon-button danger-text" href="{{ route('findings.inspection.next', $inspection) }}">Input 4M ({{ $pending4mCount }})</a>
                                    @elseif ($pending4mCount === 0)
                                        <span class="badge badge-pass">4M lengkap</span>
                                    @endif
                                @endif
                                @if ($inspection->evidence)
                                    @foreach (collect($inspection->evidence)->flatten()->values() as $photoIndex => $photo)
                                        <a class="icon-button" href="{{ route('inspections.evidence', [$inspection, $photoIndex]) }}">Foto {{ $photoIndex + 1 }}</a>
                                    @endforeach
                                @endif
                                @if ($inspection->hasil_akhir === 'NG' && $inspection->inspector?->email)
                                    <form method="POST" action="{{ route('inspections.email', $inspection) }}">@csrf<button class="icon-button" type="submit">{{ $inspection->email_sent_at ? 'Kirim ulang email' : 'Kirim email' }}</button></form>
                                @endif
                                @if (auth()->user()->canWriteInspections())
                                    <form method="POST" action="{{ route('inspections.destroy', $inspection) }}" onsubmit="return confirm('Hapus catatan inspeksi ini?')">@csrf @method('DELETE')<button class="icon-button danger-text" type="submit" aria-label="Hapus inspeksi">Hapus</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $inspections->links() }}</div>
    @endif
</section>
@endsection
