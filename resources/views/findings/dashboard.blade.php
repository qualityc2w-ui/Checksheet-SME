@extends('layouts.app')

@section('title', 'Dashboard Temuan')
@section('eyebrow', 'MONITORING TEMUAN QC 2W DPM')

@section('content')
@php
    $statusColors = ['open' => '#5877e8', 'scheduled' => '#8a65dc', 'done' => '#e5a531', 'verified' => '#25a477', 'rejected' => '#d65a61'];
    $statusSegments = [];
    $offset = 0;
    foreach ($statusCounts as $status => $count) {
        $width = $total ? $count / $total * 100 : 0;
        $statusSegments[] = $statusColors[$status].' '.$offset.'% '.($offset + $width).'%';
        $offset += $width;
    }
@endphp
<div class="page-heading">
    <div><p class="eyebrow">KONTROL TINDAK LANJUT</p><h1>Dashboard temuan</h1><p class="muted">Ringkasan status temuan, PIC, prioritas, dan jenis 4M.</p></div>
    @if (auth()->user()->canWriteInspections())<a class="button button-primary" href="{{ route('findings.create') }}">＋ Input temuan</a>@endif
</div>

<section class="panel dashboard-filters">
    <form method="GET" action="{{ route('findings.dashboard') }}" class="filter-grid">
        <label class="field"><span>Cari kode / part / PIC</span><input name="q" value="{{ request('q') }}"></label>
        <label class="field"><span>Status</span><select name="status"><option value="all">Semua status</option>@foreach ($statusCounts as $status => $count)<option value="{{ $status }}" @selected(request('status', 'all') === $status)>{{ \App\Models\Finding::STATUS_LABELS[$status] }}</option>@endforeach<option value="late" @selected(request('status') === 'late')>Terlambat</option></select></label>
        <label class="field"><span>Kategori 4M</span><select name="category"><option value="all">Semua kategori</option>@foreach ($filters['categories'] as $value => $label)<option value="{{ $value }}" @selected(request('category', 'all') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field"><span>Area / Line</span><select name="area_line"><option value="">Semua</option>@foreach ($filters['lines'] as $line)<option @selected(request('area_line') === $line)>{{ $line }}</option>@endforeach</select></label>
        <label class="field"><span>PIC</span><select name="pic_id"><option value="">Semua</option>@foreach ($filters['pics'] as $pic)<option value="{{ $pic->id }}" @selected((string) request('pic_id') === (string) $pic->id)>{{ $pic->name }}</option>@endforeach</select></label>
        <label class="field"><span>Prioritas</span><select name="priority"><option value="">Semua</option>@foreach ($filters['priorities'] as $value => $label)<option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field"><span>Shift</span><select name="shift"><option value="">Semua</option>@foreach (['Shift 1', 'Shift 2', 'Shift 3'] as $shift)<option @selected(request('shift') === $shift)>{{ $shift }}</option>@endforeach</select></label>
        <label class="field"><span>Nama part</span><select name="part_name"><option value="">Semua part</option>@foreach ($filters['partNames'] as $part)<option @selected(request('part_name') === $part)>{{ $part }}</option>@endforeach</select></label>
        <label class="field"><span>Tipe part</span><select name="part_type"><option value="">Semua tipe</option>@foreach ($filters['partTypes'] as $type)<option @selected(request('part_type') === $type)>{{ $type }}</option>@endforeach</select></label>
        <label class="field"><span>Tanggal dari</span><input type="date" name="from" value="{{ request('from') }}"></label>
        <label class="field"><span>Tanggal sampai</span><input type="date" name="to" value="{{ request('to') }}"></label>
        <div class="filter-actions"><button class="button button-primary">Filter</button> <a class="button button-secondary" href="{{ route('findings.dashboard') }}">Reset</a></div>
    </form>
</section>

<div class="stat-grid">
    <article class="stat-card"><span class="stat-symbol symbol-blue">!</span><div><p>Total temuan</p><strong>{{ $total }}</strong></div></article>
    <article class="stat-card"><span class="stat-symbol symbol-violet">◷</span><div><p>Menunggu PIC</p><strong>{{ $statusCounts['open'] }}</strong></div></article>
    <article class="stat-card"><span class="stat-symbol symbol-green">✓</span><div><p>Dalam perbaikan</p><strong>{{ $statusCounts['scheduled'] }}</strong></div></article>
    <article class="stat-card"><span class="stat-symbol symbol-red">⌛</span><div><p>Terlambat</p><strong>{{ $overdue }}</strong></div></article>
</div>
<div class="stat-grid">
    <article class="stat-card"><span class="stat-symbol symbol-green">%</span><div><p>Closing rate</p><strong>{{ $closeRate }}%</strong><small>{{ $statusCounts['verified'] }} terverifikasi</small></div></article>
    <article class="stat-card"><span class="stat-symbol symbol-blue">⌕</span><div><p>Perlu verifikasi</p><strong>{{ $statusCounts['done'] }}</strong></div></article>
    <article class="stat-card"><span class="stat-symbol symbol-violet">↺</span><div><p>Ditolak / revisi</p><strong>{{ $statusCounts['rejected'] }}</strong></div></article>
    <article class="stat-card"><span class="stat-symbol symbol-red">4M</span><div><p>Kategori terbanyak</p><strong>{{ $categoryCounts->sortDesc()->keys()->first() ? $filters['categories'][$categoryCounts->sortDesc()->keys()->first()] : '—' }}</strong></div></article>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Status temuan</h2><p class="muted">{{ $total }} temuan dalam filter</p></div></div>
        <div class="findings-donut-layout">
            <div class="findings-donut" style="background: conic-gradient({{ implode(', ', $statusSegments) ?: '#e9edf3 0% 100%' }})"><span>{{ $total }}<small>Total</small></span></div>
            <div class="findings-legend">
                @foreach ($statusCounts as $status => $count)
                    <div><i style="background:{{ $statusColors[$status] }}"></i><span>{{ \App\Models\Finding::STATUS_LABELS[$status] }}</span><strong>{{ $count }}</strong></div>
                @endforeach
            </div>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Kategori 4M</h2><p class="muted">Jumlah temuan berdasarkan penyebab utama</p></div></div>
        <div class="finding-bars">
            @foreach ($categoryCounts as $category => $count)
                <div><span>{{ $filters['categories'][$category] }}</span><div><i style="width:{{ $total ? $count / $total * 100 : 0 }}%"></i></div><strong>{{ $count }}</strong></div>
            @endforeach
        </div>
    </section>
</div>
<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Beban PIC</h2><p class="muted">Jumlah pekerjaan aktif dan terlambat.</p></div></div>
        <div class="finding-bars">
            @forelse ($picCounts as $pic)
                <div><span>{{ $pic['name'] }}</span><div><i style="width:{{ $picCounts->max('open') ? $pic['open'] / $picCounts->max('open') * 100 : 0 }}%"></i></div><strong>{{ $pic['open'] }}{{ $pic['late'] ? ' / '.$pic['late'].' telat' : '' }}</strong></div>
            @empty<p class="muted">Belum ada data PIC.</p>@endforelse
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Target mendatang</h2><p class="muted">Rencana dengan tenggat terdekat.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Part</th><th>PIC</th><th>Target</th></tr></thead><tbody>
            @forelse ($upcoming as $finding)
                <tr><td><a class="text-link" href="{{ route('findings.show', $finding) }}">{{ $finding->part_name }}</a><small class="table-subtitle">{{ $finding->code }}</small></td><td>{{ $finding->pic?->name ?? '—' }}</td><td>{{ $finding->due_date->format('d M Y') }}</td></tr>
            @empty<tr><td colspan="3">Tidak ada target mendatang.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
<section class="panel">
    <div class="panel-heading"><div><h2>Monitoring part dan tipe</h2><p class="muted">Maksimal 12 part dengan temuan terbanyak dalam filter.</p></div><a class="text-link" href="{{ route('findings.index', request()->query()) }}">Buka register →</a></div>
    <div class="finding-bars">
        @forelse ($partCounts as $part)
            <div><span>{{ $part['label'] }}</span><div><i style="width:{{ $partCounts->max('count') ? $part['count'] / $partCounts->max('count') * 100 : 0 }}%"></i></div><strong>{{ $part['count'] }}</strong></div>
        @empty
            <p class="muted">Belum ada data temuan.</p>
        @endforelse
    </div>
</section>
@endsection
