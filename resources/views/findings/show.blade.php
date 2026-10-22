@extends('layouts.app')

@section('title', $finding->code.' — Temuan')
@section('eyebrow', 'DETAIL TINDAK LANJUT TEMUAN')

@section('content')
@php
    $canVerify = auth()->user()->isAdmin() || $finding->reporter_id === auth()->id();
    $steps = [
        ['Dibuat', $finding->created_at],
        ['Email PIC dikirim', $finding->email_sent_at],
        ['PIC menjawab', $finding->responded_at],
        ['Perbaikan selesai', $finding->completed_at],
        ['Diverifikasi', $finding->verified_at],
    ];
@endphp
<div class="page-heading">
    <div><p class="eyebrow">{{ $finding->code }} · {{ $finding->found_date->format('d M Y') }} · {{ $finding->shift }}</p><h1>{{ $finding->part_name }}</h1><p class="muted">{{ $finding->part_type }} · {{ $finding->area_line }} · {{ $finding->problem_type }} · {{ $finding->reporter_name }}</p></div>
    <div class="dashboard-heading-actions">
        @if (auth()->user()->isAdmin())<a class="button button-secondary" href="{{ route('findings.edit', $finding) }}">Edit temuan</a>@endif
        @if (auth()->user()->canWriteInspections())<form method="POST" action="{{ route('findings.email', $finding) }}">@csrf<button class="button button-primary" type="submit">{{ $finding->email_sent_at ? 'Kirim ulang email ke PIC' : 'Kirim email ke PIC' }}</button></form>@endif
    </div>
</div>
<section class="panel finding-timeline">
    @foreach ($steps as [$label, $date])
        <div class="{{ $date ? 'complete' : '' }}"><i></i><strong>{{ $label }}</strong><small>{{ $date?->format('d M Y H:i') ?? 'Belum' }}</small></div>
    @endforeach
</section>
<div class="dashboard-grid finding-detail-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Temuan dan analisis 4M</h2><p class="muted">Kategori utama: {{ \App\Models\Finding::CATEGORIES[$finding->category] }} · Prioritas {{ \App\Models\Finding::PRIORITIES[$finding->priority] }}</p></div><span class="badge {{ $finding->status === 'verified' ? 'badge-pass' : ($finding->status === 'rejected' || $finding->isOverdue() ? 'badge-ng' : 'badge-neutral') }}">{{ \App\Models\Finding::STATUS_LABELS[$finding->status] }}{{ $finding->isOverdue() ? ' · terlambat' : '' }}</span></div>
        @if ($finding->point_check)
            <div class="point-check-summary">
                <strong>Point check NG: {{ $finding->point_check }}</strong>
                <span>Hasil: {{ $finding->observed_value }}{{ $finding->unit ? ' '.$finding->unit : '' }}</span>
                @if ($finding->minimum_value !== null || $finding->maximum_value !== null)
                    <span>Batas: {{ $finding->minimum_value ?? '—' }} – {{ $finding->maximum_value ?? '—' }}{{ $finding->unit ? ' '.$finding->unit : '' }}</span>
                    <span>Standar: {{ $finding->standard_value ?? '—' }}{{ $finding->unit ? ' '.$finding->unit : '' }}</span>
                @endif
                @if ($finding->inspection)<a class="text-link" href="{{ route('inspection-reports.index', ['from' => $finding->inspection->tanggal->toDateString(), 'to' => $finding->inspection->tanggal->toDateString(), 'product_id' => $finding->inspection->product_id]) }}">Lihat inspeksi sumber →</a>@endif
            </div>
        @endif
        <h3>Deskripsi masalah</h3><p class="finding-copy">{{ $finding->description ?: '—' }}</p>
        <h3 class="finding-subheading">Analisis 4M</h3>
        <div class="form-grid form-grid-2 finding-analysis-read">
            @foreach (\App\Models\Finding::CATEGORIES as $key => $label)
                <article class="finding-analysis-card {{ $finding->category === $key ? 'primary-cause' : '' }}"><strong>{{ $label }}{{ $finding->category === $key ? ' · Penyebab utama' : '' }}</strong><p>{{ $finding->{'analysis_'.$key} ?: '—' }}</p></article>
            @endforeach
        </div>
        <h3 class="finding-subheading">Kesimpulan akar masalah</h3><p class="finding-copy">{{ $finding->root_cause }}</p>
        @if ($finding->problem_photo_path)
            <h3 class="finding-subheading">Foto bukti masalah</h3><a href="{{ route('findings.photo', [$finding, 'problem']) }}" target="_blank" rel="noopener"><img class="finding-photo" src="{{ route('findings.photo', [$finding, 'problem']) }}" alt="Foto bukti masalah"></a>
        @endif
    </section>
    <div class="form-stack">
        <section class="panel">
            <div class="panel-heading"><div><h2>PIC perbaikan</h2><p class="muted">Penanggung jawab tindak lanjut</p></div></div>
            <strong>{{ $finding->pic?->name ?? 'PIC tidak tersedia' }}</strong>
            <p class="muted">{{ $finding->pic?->department ?: '—' }}</p>
            <dl class="finding-facts"><dt>Email PIC</dt><dd>{{ auth()->user()->isAdmin() ? ($finding->pic?->email ?? '—') : 'Tersimpan untuk pengiriman PIC' }}</dd><dt>Tanggal target</dt><dd>{{ $finding->due_date?->format('d M Y') ?? 'Belum ditetapkan' }}</dd><dt>Rencana perbaikan</dt><dd>{{ $finding->pic_plan ?: 'Belum diisi' }}</dd></dl>
        </section>
        @if ($finding->status === 'done')
            <section class="panel"><h2>Penyelesaian PIC</h2><p class="finding-copy">{{ $finding->completion_note }}</p>
                @if ($finding->completion_photo_path)<a href="{{ route('findings.photo', [$finding, 'completion']) }}" target="_blank" rel="noopener"><img class="finding-photo" src="{{ route('findings.photo', [$finding, 'completion']) }}" alt="Foto penyelesaian"></a>@endif
            </section>
        @endif
        @if ($finding->verification_note)
            <section class="panel"><h2>Catatan verifikasi</h2><p class="finding-copy">{{ $finding->verification_note }}</p></section>
        @endif
        @if ($finding->status === 'done' && $canVerify)
            <section class="panel">
                <div class="panel-heading"><div><h2>Verifikasi perbaikan</h2><p class="muted">Tentukan apakah perbaikan sudah memenuhi kebutuhan.</p></div></div>
                <form method="POST" action="{{ route('findings.verify', $finding) }}" class="form-stack">@csrf
                    <label class="field"><span>Catatan verifikasi <b>*</b></span><textarea name="verification_note" rows="3" minlength="3" maxlength="2000" required>{{ old('verification_note') }}</textarea></label>
                    <div class="dashboard-heading-actions"><button class="button button-primary" name="decision" value="verified">Verifikasi</button><button class="button button-secondary" name="decision" value="rejected">Kembalikan ke PIC</button></div>
                </form>
            </section>
        @endif
    </div>
</div>
@endsection
