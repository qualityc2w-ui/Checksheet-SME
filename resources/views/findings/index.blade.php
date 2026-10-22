@extends('layouts.app')

@section('title', 'Temuan 4M')
@section('eyebrow', 'REGISTER TEMUAN DAN TINDAK LANJUT')

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">TEMUAN QC 2W DPM</p><h1>Daftar temuan</h1><p class="muted">Pantau penanggung jawab, target perbaikan, dan status verifikasi.</p></div>
    <div class="dashboard-heading-actions">
        <a class="button button-secondary" href="{{ route('findings.export', request()->query()) }}">↓ Unduh CSV</a>
        @if (auth()->user()->canWriteInspections())<a class="button button-primary" href="{{ route('findings.create') }}">＋ Input temuan</a>@endif
    </div>
</div>

<section class="panel dashboard-filters">
    <form method="GET" action="{{ route('findings.index') }}" class="filter-grid">
        <label class="field"><span>Cari kode / part / masalah</span><input name="q" value="{{ request('q') }}" placeholder="Contoh: TMN-000001"></label>
        <label class="field"><span>Status</span><select name="status"><option value="all">Semua status</option>@foreach ($filters['statuses'] as $value => $label)<option value="{{ $value }}" @selected(request('status', 'all') === $value)>{{ $label }}</option>@endforeach<option value="late" @selected(request('status') === 'late')>Terlambat</option></select></label>
        <label class="field"><span>Kategori 4M</span><select name="category"><option value="all">Semua kategori</option>@foreach ($filters['categories'] as $value => $label)<option value="{{ $value }}" @selected(request('category', 'all') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field"><span>Area / Line</span><select name="area_line"><option value="">Semua area/line</option>@foreach ($filters['lines'] as $line)<option @selected(request('area_line') === $line)>{{ $line }}</option>@endforeach</select></label>
        <label class="field"><span>PIC</span><select name="pic_id"><option value="">Semua PIC</option>@foreach ($filters['pics'] as $pic)<option value="{{ $pic->id }}" @selected((string) request('pic_id') === (string) $pic->id)>{{ $pic->name }}</option>@endforeach</select></label>
        <label class="field"><span>Prioritas</span><select name="priority"><option value="">Semua prioritas</option>@foreach ($filters['priorities'] as $value => $label)<option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field"><span>Shift</span><select name="shift"><option value="">Semua shift</option>@foreach (['Shift 1', 'Shift 2', 'Shift 3'] as $shift)<option @selected(request('shift') === $shift)>{{ $shift }}</option>@endforeach</select></label>
        <label class="field"><span>Nama part</span><select name="part_name"><option value="">Semua part</option>@foreach ($filters['partNames'] as $part)<option @selected(request('part_name') === $part)>{{ $part }}</option>@endforeach</select></label>
        <label class="field"><span>Tipe part</span><select name="part_type"><option value="">Semua tipe</option>@foreach ($filters['partTypes'] as $type)<option @selected(request('part_type') === $type)>{{ $type }}</option>@endforeach</select></label>
        <label class="field"><span>Tanggal dari</span><input type="date" name="from" value="{{ request('from') }}"></label>
        <label class="field"><span>Tanggal sampai</span><input type="date" name="to" value="{{ request('to') }}"></label>
        <div class="filter-actions"><button class="button button-primary">Filter</button> <a class="button button-secondary" href="{{ route('findings.index') }}">Reset</a></div>
    </form>
</section>

<section class="panel">
    <div class="panel-heading"><div><h2>Register temuan</h2><p class="muted">{{ $findings->total() }} temuan sesuai filter</p></div></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Kode / tanggal</th><th>Temuan / part</th><th>4M</th><th>PIC</th><th>Target</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($findings as $finding)
                    <tr>
                        <td><strong>{{ $finding->code }}</strong><small class="table-subtitle">{{ $finding->found_date->format('d M Y') }} · {{ $finding->shift }}</small></td>
                        <td><a class="text-link" href="{{ route('findings.show', $finding) }}">{{ $finding->part_name }}</a><small class="table-subtitle">{{ $finding->part_type }} · {{ $finding->area_line }}</small></td>
                        <td>{{ $filters['categories'][$finding->category] ?? $finding->category }}<small class="table-subtitle">{{ $finding->problem_type }}</small></td>
                        <td>{{ $finding->pic?->name ?? '—' }}</td>
                        <td>{{ $finding->due_date?->format('d M Y') ?? '—' }}</td>
                        <td><span class="badge {{ $finding->status === 'verified' ? 'badge-pass' : (in_array($finding->status, ['rejected']) || $finding->isOverdue() ? 'badge-ng' : 'badge-neutral') }}">{{ $filters['statuses'][$finding->status] }}{{ $finding->isOverdue() ? ' · terlambat' : '' }}</span></td>
                        <td class="table-actions">
                            <a class="text-link" href="{{ route('findings.show', $finding) }}">Detail</a>
                            @if (auth()->user()->isAdmin())
                                <a class="text-link" href="{{ route('findings.edit', $finding) }}">Edit</a>
                                <form method="POST" action="{{ route('findings.destroy', $finding) }}" onsubmit="return confirm('Hapus temuan {{ $finding->code }}?')">@csrf @method('DELETE')<button class="text-link button-link" type="submit">Hapus</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state compact"><strong>Belum ada temuan</strong><p>Temuan yang cocok dengan filter akan tampil di sini.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $findings->links() }}</div>
</section>
@endsection
