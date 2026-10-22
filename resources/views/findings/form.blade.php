@extends('layouts.app')

@section('title', $editing ? 'Edit Temuan' : 'Input Temuan')
@section('eyebrow', 'REGISTER TEMUAN 4M')

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">{{ $editing ? $finding->code : 'TEMUAN BARU' }}</p><h1>{{ $editing ? 'Edit temuan' : 'Input temuan' }}</h1><p class="muted">{{ $editing ? 'Perbarui informasi temuan dan PIC.' : 'Catat masalah 4M lalu teruskan rencana perbaikan kepada PIC.' }}</p></div>
    <a class="button button-secondary" href="{{ $editing ? route('findings.show', $finding) : ($source ? route('findings.inspection.next', $source['inspection']) : route('findings.index')) }}">← Kembali</a>
</div>
<form method="POST" action="{{ $editing ? route('findings.update', $finding) : ($source ? route('findings.inspection.store', [$source['inspection'], $source['point']['section'], $source['point']['index']]) : route('findings.store')) }}" enctype="multipart/form-data" class="form-stack finding-form">
    @csrf
    @if ($editing) @method('PUT') @endif
    @if ($source)
        <section class="panel inspection-finding-source">
            <div class="panel-heading"><div><p class="eyebrow">DARI INSPEKSI NG</p><h2>{{ $source['point']['label'] }}</h2><p class="muted">Inspeksi {{ $source['inspection']->product?->kode }} · {{ $source['inspection']->tanggal->format('d M Y') }} · {{ $source['inspection']->shift }} / {{ $source['inspection']->sesi }} · Inspector {{ $source['inspection']->inspector?->nama ?? '—' }}</p></div><span class="badge badge-ng">NG</span></div>
            <p class="finding-copy">{{ $finding->description }}</p>
            @if ($source['point']['evidence_path'])
                <a href="{{ $source['evidence_url'] }}" target="_blank" rel="noopener"><img class="finding-photo" src="{{ $source['evidence_url'] }}" alt="Bukti foto point check NG"></a>
                <p class="muted">Foto bukti ini akan otomatis dilampirkan pada temuan 4M.</p>
            @endif
        </section>
    @endif
    <section class="panel">
        <div class="panel-heading"><div><h2>Informasi temuan</h2><p class="muted">Informasi part, waktu dan lokasi kejadian.</p></div></div>
        <div class="form-grid form-grid-2">
            @if ($source || $inspectionSource)
                <label class="field"><span>Nama penginput</span><input value="{{ auth()->user()->name }}" readonly></label>
                <label class="field"><span>Tanggal temuan</span><input value="{{ $finding->found_date->format('d M Y') }}" readonly></label>
                <label class="field"><span>Shift</span><input value="{{ $finding->shift }}" readonly></label>
                <label class="field"><span>Area / Line</span><input value="{{ $finding->area_line }}" readonly></label>
                <label class="field"><span>Nama part</span><input value="{{ $finding->part_name }}" readonly></label>
                <label class="field"><span>Tipe part</span><input value="{{ $finding->part_type }}" readonly></label>
                @if ($inspectionSource)
                    <p class="muted">Tanggal, shift, area/line, nama part, dan tipe part diambil otomatis dari data inspeksi.</p>
                @endif
            @elseif ($editing)
                <label class="field"><span>Kode temuan</span><input value="{{ $finding->code }}" readonly></label>
                <label class="field"><span>Tanggal temuan <b>*</b></span><input type="date" name="found_date" value="{{ old('found_date', $finding->found_date?->format('Y-m-d')) }}" required></label>
                <label class="field"><span>Shift <b>*</b></span><select name="shift" required>@foreach (['Shift 1', 'Shift 2', 'Shift 3'] as $shift)<option @selected(old('shift', $finding->shift) === $shift)>{{ $shift }}</option>@endforeach</select></label>
                <label class="field"><span>Area / Line <b>*</b></span><select name="area_line" required><option value="">Pilih area / line</option>@foreach ($options->get('area_line', collect()) as $option)<option value="{{ $option->value }}" @selected(old('area_line', $finding->area_line) === $option->value)>{{ $option->value }}</option>@endforeach</select></label>
                <label class="field"><span>Nama part <b>*</b></span><select name="part_name" required><option value="">Pilih nama part</option>@foreach ($options->get('part_name', collect()) as $option)<option value="{{ $option->value }}" @selected(old('part_name', $finding->part_name) === $option->value)>{{ $option->value }}</option>@endforeach</select></label>
                <label class="field"><span>Tipe part <b>*</b></span><select name="part_type" required><option value="">Pilih tipe part</option>@foreach ($options->get('part_type', collect()) as $option)<option value="{{ $option->value }}" @selected(old('part_type', $finding->part_type) === $option->value)>{{ $option->value }}</option>@endforeach</select></label>
            @else
                <label class="field"><span>Nama penginput</span><input value="{{ auth()->user()->name }}" readonly></label>
                <label class="field"><span>Tanggal temuan <b>*</b></span><input type="date" name="found_date" value="{{ old('found_date', $finding->found_date?->format('Y-m-d')) }}" required></label>
                <label class="field"><span>Shift <b>*</b></span><select name="shift" required>@foreach (['Shift 1', 'Shift 2', 'Shift 3'] as $shift)<option @selected(old('shift', $finding->shift) === $shift)>{{ $shift }}</option>@endforeach</select></label>
                <label class="field"><span>Area / Line <b>*</b></span><select name="area_line" required><option value="">Pilih area / line</option>@foreach ($options->get('area_line', collect()) as $option)<option value="{{ $option->value }}" @selected(old('area_line', $finding->area_line) === $option->value)>{{ $option->value }}</option>@endforeach</select></label>
                <label class="field"><span>Nama part <b>*</b></span><select name="part_name" required><option value="">Pilih nama part</option>@foreach ($options->get('part_name', collect()) as $option)<option value="{{ $option->value }}" @selected(old('part_name', $finding->part_name) === $option->value)>{{ $option->value }}</option>@endforeach</select></label>
                <label class="field"><span>Tipe part <b>*</b></span><select name="part_type" required><option value="">Pilih tipe part</option>@foreach ($options->get('part_type', collect()) as $option)<option value="{{ $option->value }}" @selected(old('part_type', $finding->part_type) === $option->value)>{{ $option->value }}</option>@endforeach</select></label>
            @endif
            <label class="field"><span>Jenis masalah <b>*</b></span><select name="problem_type" required><option value="">Pilih jenis masalah</option>@foreach ($options->get('problem_type', collect()) as $option)<option value="{{ $option->value }}" @selected(old('problem_type', $finding->problem_type) === $option->value)>{{ $option->value }}</option>@endforeach</select></label>
            <label class="field"><span>Prioritas <b>*</b></span><select name="priority" required>@foreach ($priorities as $value => $label)<option value="{{ $value }}" @selected(old('priority', $finding->priority) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field"><span>{{ $source ? 'Catatan tambahan (opsional)' : 'Deskripsi masalah' }}</span><textarea name="description" rows="3" maxlength="5000">{{ old('description', $source ? '' : $finding->description) }}</textarea></label>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Analisis 4M dan akar masalah</h2><p class="muted">Pilih kategori utama dan lengkapi analisis setiap faktor.</p></div></div>
        <label class="field"><span>Kategori penyebab utama <b>*</b></span><select name="category" required>@foreach ($categories as $value => $label)<option value="{{ $value }}" @selected(old('category', $finding->category) === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="form-grid form-grid-2 finding-analysis">
            @foreach ($categories as $value => $label)
                <label class="field"><span>Analisis {{ $label }} <b>*</b></span><textarea name="analysis_{{ $value }}" rows="3" maxlength="2000" required>{{ old('analysis_'.$value, $finding->{'analysis_'.$value}) }}</textarea></label>
            @endforeach
            <label class="field"><span>Kesimpulan akar masalah <b>*</b></span><textarea name="root_cause" rows="3" maxlength="2000" required>{{ old('root_cause', $finding->root_cause) }}</textarea></label>
            @if (! $source)
                <label class="field"><span>Foto bukti masalah (opsional)</span><input type="file" name="problem_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG, WebP · maks. 10 MB.{{ $editing && $finding->problem_photo_path ? ' Foto lama tetap tersimpan bila tidak memilih foto baru.' : '' }}</small></label>
            @endif
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>PIC perbaikan</h2><p class="muted">PIC akan menerima link untuk mengisi rencana dan target perbaikan.</p></div></div>
        <label class="field"><span>Pilih PIC <b>*</b></span><select name="pic_id" required><option value="">Pilih PIC</option>@foreach ($pics as $pic)<option value="{{ $pic->id }}" @selected((string) old('pic_id', $finding->pic_id) === (string) $pic->id)>{{ $pic->name }}{{ $pic->department ? ' · '.$pic->department : '' }}</option>@endforeach</select></label>
        @if ($pics->isEmpty())<p class="alert alert-warning">Belum ada PIC aktif. Minta admin menambahkan PIC di Pengaturan Temuan.</p>@endif
    </section>
    <div class="form-actions"><button class="button button-primary" type="submit">{{ $editing ? 'Simpan perubahan' : ($source ? 'Simpan 4M dan lanjut ke NG berikutnya' : 'Simpan temuan') }}</button><a class="button button-secondary" href="{{ $editing ? route('findings.show', $finding) : ($source ? route('findings.inspection.next', $source['inspection']) : route('findings.index')) }}">Batal</a></div>
</form>
@endsection
