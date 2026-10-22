@extends('layouts.app')

@section('title', 'Inspeksi QC')
@section('eyebrow', 'INPUT PEMERIKSAAN')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">QUALITY CONTROL</p>
        <h1>Inspeksi QC</h1>
        <p class="muted">Catat pemeriksaan produk.</p>
    </div>
</div>

<section class="panel building-picker">
    <div class="panel-heading">
        <div><h2>1. Pilih folder area gedung</h2><p class="muted">Area/line dan produk akan mengikuti folder yang dipilih.</p></div>
        @if ($selectedBuilding)<a class="text-link" href="{{ route('inspections.index') }}">Ganti folder</a>@endif
    </div>
    <div class="building-folders">
        @forelse ($buildings as $building)
            <a class="building-folder {{ (string) $selectedBuilding?->id === (string) $building->id ? 'active' : '' }}" href="{{ route('inspections.index', ['building_id' => $building->id]) }}">
                <span class="folder-icon" aria-hidden="true">▰</span>
                <span><strong>{{ $building->nama }}</strong><small>{{ $building->children_count }} area/line · {{ $building->products_count }} produk</small></span>
                <span class="folder-arrow" aria-hidden="true">→</span>
            </a>
        @empty
            <div class="empty-state compact"><strong>Folder gedung belum tersedia</strong><p>Admin dapat mendaftarkan gedung dan area/line di Master Data → Area / Line.</p></div>
        @endforelse
    </div>
</section>

@if ($selectedBuilding)
<section class="panel form-panel" id="form-inspeksi">
    <div class="panel-heading">
        <div><h2>2. Input pemeriksaan baru</h2><p class="muted">Folder gedung: <strong>{{ $selectedBuilding->nama }}</strong>. Area/line otomatis mengikuti produk.</p></div>
        <span class="required-note">* Wajib diisi</span>
    </div>
    @if (! auth()->user()->canWriteInspections())
        <div class="empty-state compact"><span class="empty-icon">⌕</span><strong>Akses lihat saja</strong><p>Akun Anda tidak memiliki izin untuk membuat atau menghapus inspeksi.</p></div>
    @elseif ($formProducts->isEmpty())
        <div class="empty-state"><span class="empty-icon">＋</span><strong>Belum ada produk di folder ini</strong><p>Hubungkan produk ke area/line di folder gedung melalui Master Data.</p><a class="button button-primary" href="{{ route('master.index', 'products') }}">Buka Master Data</a></div>
    @else
        <form method="POST" action="{{ route('inspections.store') }}" class="form-stack" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="building_id" value="{{ $selectedBuilding->id }}">
            <div class="form-grid form-grid-3">
                <label class="field"><span>Tanggal <b>*</b></span><input type="date" name="tanggal" value="{{ old('tanggal', today()->toDateString()) }}" required></label>
                <label class="field"><span>Produk <b>*</b></span>
                    <select name="product_id" id="product-select" required>
                        @foreach ($formProducts as $product)
                            <option value="{{ $product->id }}" data-line="{{ $product->areaRelation?->nama }}" @selected((string) old('product_id', $formProducts->first()->id) === (string) $product->id)>{{ $product->kode }} · {{ $product->nama }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Area / line otomatis</span><input id="selected-line" value="{{ $formProducts->first()->areaRelation?->nama ?? $selectedBuilding->nama }}" readonly></label>
                <label class="field"><span>Inspector <b>*</b></span>
                    @if (auth()->user()->role === 'inspector')
                        <input value="{{ auth()->user()->name }}" readonly aria-readonly="true">
                    @else
                        <select name="inspector_id" id="inspector-select" required>
                            <option value="">Pilih inspector</option>
                            @foreach ($inspectors as $inspector)
                                <option value="{{ $inspector->id }}" @selected((string) old('inspector_id') === (string) $inspector->id)>{{ $inspector->nama }}{{ $inspector->dept ? ' · '.$inspector->dept : '' }}</option>
                            @endforeach
                        </select>
                    @endif
                </label>
                <label class="field"><span>Shift <b>*</b></span>
                    <select name="shift" required>
                        @foreach (['Shift 1', 'Shift 2'] as $shift)
                            <option @selected(old('shift', 'Shift 1') === $shift)>{{ $shift }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Sesi pengecekan <b>*</b></span>
                    <select name="sesi" required>
                        @foreach (['Start', 'Middle', 'End'] as $sesi)
                            <option @selected(old('sesi', 'Start') === $sesi)>{{ $sesi }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Jumlah sample <b>*</b></span><input type="number" name="sample" min="1" max="10000" value="{{ old('sample', 30) }}" required></label>
            </div>

            <fieldset class="measurement-mode" data-measurement-mode>
                <legend>Sumber pengukuran dimensi</legend>
                <div class="measurement-mode-options">
                    <label><input type="radio" name="measurement_mode" value="manual" @checked(old('measurement_mode', 'manual') === 'manual') required><span>Manual</span></label>
                    <label><input type="radio" name="measurement_mode" value="automatic" @checked(old('measurement_mode') === 'automatic') required><span>Otomatis (Bluetooth / Wi-Fi)</span></label>
                </div>
                <p class="muted">Mode otomatis disiapkan melalui adaptor perangkat. Dukungan koneksi bergantung pada merek, model, dan protokol alat ukur; pemeriksaan visual dan fungsi tetap dilakukan manual.</p>
                <p class="measurement-mode-status" data-measurement-mode-status aria-live="polite">Nilai dimensi diisi manual.</p>
            </fieldset>

            @foreach ($formProducts as $product)
                @php
                    $productDimensions = $dimensionStandards->get($product->id, collect());
                    $productVisual = $visualStandards->get($product->id, collect());
                    $productFunctions = $functionStandards->get($product->id, collect());
                @endphp
                <fieldset class="product-checks" data-product-group="{{ $product->id }}">
                    <legend>{{ $product->kode }} — {{ $product->nama }}</legend>

                    @php($pointCheckImages = $productVisual->filter(fn ($standard) => $standard->image_path))
                @if ($product->image_path || $pointCheckImages->isNotEmpty())
                        <section class="point-check-gallery" aria-label="Visualisasi point check seluruh inspeksi">
                            <div class="point-check-gallery-heading">
                                <span class="check-number">QC</span>
                            <div><h3>Visualisasi point check produk</h3><p>Gunakan gambar ini sebagai acuan untuk seluruh pemeriksaan produk: dimensi, visual, dan fungsi.</p></div>
                            </div>
                            <div class="point-check-gallery-images">
                            @if ($product->image_path)
                                <figure class="point-check-figure">
                                    <img src="{{ route('products.point-check-image', $product) }}" alt="Visual point check produk {{ $product->kode }}" loading="lazy">
                                    <figcaption>{{ $product->kode }} · {{ $product->nama }}</figcaption>
                                </figure>
                            @endif
                            @foreach ($pointCheckImages as $pointCheck)
                                    <figure class="point-check-figure">
                                        <img src="{{ route('inspections.point-check-image', $pointCheck) }}" alt="Visualisasi point check: {{ $pointCheck->checklist }}" loading="lazy">
                                        <figcaption>{{ $pointCheck->checklist }}</figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="check-section">
                        <div class="check-heading"><span class="check-number">01</span><div><h3>Dimensi part</h3><p>Nilai otomatis dibandingkan dengan batas toleransi standar.</p></div></div>
                        @if ($productDimensions->isEmpty())
                            <p class="inline-notice">Belum ada standar dimensi untuk produk ini. Tambahkan melalui Master Data jika diperlukan.</p>
                        @else
                            <div class="table-wrap">
                                <table class="data-table standards-table">
                                    <thead><tr><th>Parameter</th><th>Standar</th><th>Toleransi</th><th>Hasil ukur</th></tr></thead>
                                    <tbody>
                                        @foreach ($productDimensions as $standard)
                                            <tr>
                                                <td><strong>{{ $standard->parameter }}</strong></td>
                                                <td>{{ $standard->nilai_standar }} {{ $standard->satuan }}</td>
                                                <td>-{{ $standard->toleransi_min }} / +{{ $standard->toleransi_max }} {{ $standard->satuan }}</td>
                                                <td>
                                                    <div class="measure-input"><input type="number" name="dimensions[{{ $standard->id }}]" step="any" value="{{ old('dimensions.'.$standard->id) }}" placeholder="Masukkan nilai" aria-label="Hasil ukur {{ $standard->parameter }}" required data-dimension-measure data-standard-id="{{ $standard->id }}" data-parameter="{{ $standard->parameter }}" data-unit="{{ $standard->satuan }}" data-min="{{ (float) $standard->nilai_standar - (float) $standard->toleransi_min }}" data-max="{{ (float) $standard->nilai_standar + (float) $standard->toleransi_max }}"><span>{{ $standard->satuan }}</span><button type="button" class="icon-button" data-measurement-read hidden>Baca alat ukur</button></div>
                                                    <small class="measurement-read-status" data-measurement-read-status aria-live="polite" hidden></small>
                                                    <label class="evidence-field dimension-evidence"><span>Foto bukti <b data-evidence-required hidden>* wajib jika NG</b></span><input type="file" name="evidence[dimensions][{{ $standard->id }}]" accept="image/jpeg,image/png,image/webp" data-evidence-file><small>JPG / PNG / WebP, maks. 5 MB</small></label>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="check-section">
                        <div class="check-heading"><span class="check-number">02</span><div><h3>Pemeriksaan visual</h3><p>Periksa kondisi visual sesuai standar.</p></div></div>
                        @if ($productVisual->isEmpty())
                            @include('inspections.partials.result-choice', ['label' => 'Pengecekan visual manual', 'name' => 'visual_results[manual]', 'evidenceName' => 'evidence[visual][manual]', 'value' => old('visual_results.manual', 'OK')])
                        @else
                            @foreach ($productVisual as $standard)
                                @include('inspections.partials.result-choice', ['label' => $standard->checklist, 'name' => 'visual_results['.$standard->id.']', 'evidenceName' => 'evidence[visual]['.$standard->id.']', 'value' => old('visual_results.'.$standard->id, 'OK')])
                            @endforeach
                        @endif
                        <label class="field"><span>Catatan visual</span><textarea name="catatan_visual" rows="2" maxlength="2000" placeholder="Tambahkan catatan bila diperlukan">{{ old('catatan_visual') }}</textarea></label>
                    </section>

                    <section class="check-section">
                        <div class="check-heading"><span class="check-number">03</span><div><h3>Pemeriksaan fungsi</h3><p>Pastikan fungsi produk berjalan sesuai spesifikasi.</p></div></div>
                        @if ($productFunctions->isEmpty())
                            @include('inspections.partials.result-choice', ['label' => 'Pengecekan fungsi manual', 'name' => 'function_results[manual]', 'evidenceName' => 'evidence[function][manual]', 'value' => old('function_results.manual', 'OK')])
                        @else
                            @foreach ($productFunctions as $standard)
                                @include('inspections.partials.result-choice', ['label' => $standard->checklist, 'name' => 'function_results['.$standard->id.']', 'evidenceName' => 'evidence[function]['.$standard->id.']', 'value' => old('function_results.'.$standard->id, 'OK')])
                            @endforeach
                        @endif
                        <label class="field"><span>Catatan fungsi</span><textarea name="catatan_fungsi" rows="2" maxlength="2000" placeholder="Tambahkan catatan bila diperlukan">{{ old('catatan_fungsi') }}</textarea></label>
                    </section>
                </fieldset>
            @endforeach

            <div class="form-grid form-grid-2">
                <label class="field"><span>Jenis defect (bila ditemukan)</span>
                    <select name="defect_id">
                        <option value="">Pilih defect</option>
                        @foreach ($defects as $defect)
                            <option value="{{ $defect->id }}" @selected((string) old('defect_id') === (string) $defect->id)>{{ $defect->nama }} · {{ $defect->tipe }} / {{ $defect->kategori }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Catatan dimensi</span><textarea name="catatan_dimensi" rows="2" maxlength="2000" placeholder="Catatan hasil ukur (opsional)">{{ old('catatan_dimensi') }}</textarea></label>
            </div>
            <div class="form-actions"><span class="muted">Hasil akhir OK/NG dihitung otomatis. Setiap parameter NG wajib memiliki foto bukti; laporan NG dikirim melalui email inspector.</span><button class="button button-primary" type="submit">Simpan inspeksi <span>→</span></button></div>
        </form>
    @endif
</section>
@else
<section class="panel empty-state building-prompt"><span class="empty-icon">▰</span><strong>Pilih folder gedung untuk melanjutkan</strong><p>Formulir pemeriksaan akan menampilkan produk dan area/line pada folder yang dipilih.</p></section>
@endif

@endsection

@push('scripts')
<script>
    const productSelect = document.getElementById('product-select');
    const selectedLine = document.getElementById('selected-line');
    const productGroups = document.querySelectorAll('[data-product-group]');
    const form = document.querySelector('#form-inspeksi form');
    function updateProductChecks() {
        if (!productSelect) return;
        productGroups.forEach((group) => {
            const active = group.dataset.productGroup === productSelect.value;
            group.hidden = !active;
            group.disabled = !active;
        });
        if (selectedLine) selectedLine.value = productSelect.selectedOptions[0]?.dataset.line || @js($selectedBuilding?->nama ?? '');
        updateEvidenceRequirements();
    }
    function updateEvidenceRequirements() {
        if (!form) return;
        form.querySelectorAll('[data-result-item]').forEach((item) => {
            const ng = item.querySelector('input[type="radio"]:checked')?.value === 'NG';
            const evidence = item.querySelector('[data-evidence-file]');
            const requiredLabel = item.querySelector('[data-evidence-required]');
            if (evidence) evidence.required = ng;
            if (evidence) evidence.disabled = !ng;
            const evidenceField = evidence?.closest('.evidence-field');
            if (evidenceField) evidenceField.hidden = !ng;
            if (requiredLabel) requiredLabel.hidden = !ng;
        });
        form.querySelectorAll('[data-dimension-measure]').forEach((measurement) => {
            const value = Number(measurement.value);
            const ng = measurement.value !== '' && (value < Number(measurement.dataset.min) || value > Number(measurement.dataset.max));
            const evidence = measurement.closest('tr')?.querySelector('[data-evidence-file]');
            const requiredLabel = measurement.closest('tr')?.querySelector('[data-evidence-required]');
            if (evidence) evidence.required = ng;
            if (evidence) evidence.disabled = !ng;
            const evidenceField = evidence?.closest('.evidence-field');
            if (evidenceField) evidenceField.hidden = !ng;
            if (requiredLabel) requiredLabel.hidden = !ng;
        });
    }
    productSelect?.addEventListener('change', updateProductChecks);
    form?.addEventListener('change', updateEvidenceRequirements);
    form?.addEventListener('input', updateEvidenceRequirements);
    updateProductChecks();
</script>
<script src="{{ asset('js/inspection-measurements.js') }}"></script>
@endpush
