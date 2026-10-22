<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tindak Lanjut {{ $finding->code }} · QC Inspect</title>
    <link rel="stylesheet" href="{{ asset('css/qc.css') }}">
</head>
<body>
<main class="finding-respond">
    <a class="brand respond-brand" href="{{ route('login') }}"><span class="brand-mark">Q</span><span><strong>QC Inspect</strong><small>QUALITY CONTROL</small></span></a>
    @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error" role="alert"><strong>Periksa kembali data yang dimasukkan.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="page-heading"><div><p class="eyebrow">{{ $finding->code }} · {{ $finding->found_date->format('d M Y') }}</p><h1>{{ $finding->part_name }}</h1><p class="muted">{{ $finding->part_type }} · {{ $finding->area_line }} · {{ $finding->shift }}</p></div><span class="badge {{ $finding->status === 'verified' ? 'badge-pass' : 'badge-neutral' }}">{{ \App\Models\Finding::STATUS_LABELS[$finding->status] }}</span></div>
        <dl class="finding-facts"><dt>Jenis masalah</dt><dd>{{ $finding->problem_type }}</dd>@if ($finding->point_check)<dt>Point check NG</dt><dd>{{ $finding->point_check }}: {{ $finding->observed_value }}{{ $finding->unit ? ' '.$finding->unit : '' }}@if ($finding->minimum_value !== null || $finding->maximum_value !== null)<br>Batas {{ $finding->minimum_value ?? '—' }} – {{ $finding->maximum_value ?? '—' }}{{ $finding->unit ? ' '.$finding->unit : '' }}@endif</dd>@endif<dt>Deskripsi</dt><dd>{{ $finding->description ?: '—' }}</dd><dt>Akar masalah</dt><dd>{{ $finding->root_cause }}</dd><dt>PIC</dt><dd>{{ $finding->pic?->name ?? '—' }}</dd></dl>
        @if ($finding->problem_photo_path)<a href="{{ route('findings.respond.photo', [$finding->response_token, 'problem']) }}" target="_blank" rel="noopener"><img class="finding-photo" src="{{ route('findings.respond.photo', [$finding->response_token, 'problem']) }}" alt="Foto bukti masalah"></a>@endif
    </section>
    @if (in_array($finding->status, ['open', 'scheduled', 'rejected'], true))
        <section class="panel">
            <div class="panel-heading"><div><h2>Rencana perbaikan</h2><p class="muted">Isi tindakan perbaikan dan tanggal target penyelesaian.</p></div></div>
            <form method="POST" action="{{ route('findings.respond.plan', $finding->response_token) }}" class="form-stack">@csrf
                <label class="field"><span>Tanggal target <b>*</b></span><input type="date" name="due_date" value="{{ old('due_date', $finding->due_date?->format('Y-m-d')) }}" required></label>
                <label class="field"><span>Rencana perbaikan <b>*</b></span><textarea name="pic_plan" rows="4" minlength="3" maxlength="2000" required>{{ old('pic_plan', $finding->pic_plan) }}</textarea></label>
                <button class="button button-primary" type="submit">Simpan rencana</button>
            </form>
        </section>
    @endif
    @if ($finding->status === 'scheduled')
        <section class="panel">
            <div class="panel-heading"><div><h2>Laporan penyelesaian</h2><p class="muted">Laporkan tindakan yang sudah dilakukan untuk proses verifikasi.</p></div></div>
            <form method="POST" action="{{ route('findings.respond.completion', $finding->response_token) }}" enctype="multipart/form-data" class="form-stack">@csrf
                <label class="field"><span>Catatan penyelesaian <b>*</b></span><textarea name="completion_note" rows="4" minlength="3" maxlength="2000" required>{{ old('completion_note', $finding->completion_note) }}</textarea></label>
                <label class="field"><span>Foto penyelesaian (opsional)</span><input type="file" name="completion_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG, WebP · maks. 10 MB.</small></label>
                <button class="button button-primary" type="submit">Kirim penyelesaian</button>
            </form>
        </section>
    @elseif ($finding->status === 'done' || $finding->status === 'verified')
        <section class="panel"><h2>Penyelesaian perbaikan</h2><p class="finding-copy">{{ $finding->completion_note }}</p><p class="muted">Status: {{ \App\Models\Finding::STATUS_LABELS[$finding->status] }}</p>
            @if ($finding->completion_photo_path)<a href="{{ route('findings.respond.photo', [$finding->response_token, 'completion']) }}" target="_blank" rel="noopener"><img class="finding-photo" src="{{ route('findings.respond.photo', [$finding->response_token, 'completion']) }}" alt="Foto penyelesaian"></a>@endif
            @if ($finding->verification_note)<h3 class="finding-subheading">Catatan verifikasi</h3><p>{{ $finding->verification_note }}</p>@endif
        </section>
    @endif
</main>
</body>
</html>
