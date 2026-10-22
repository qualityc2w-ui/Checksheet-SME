@extends('layouts.app')

@section('title', 'Master Data')
@section('eyebrow', 'KONFIGURASI DATA QC')

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">KONFIGURASI</p><h1>Master Data</h1><p class="muted">Atur area, produk, batas dimensi, inspector, defect, dan akun.</p></div>
</div>

<nav class="tab-nav" aria-label="Jenis master data">
    @foreach ($sections as $key => $item)
        <a href="{{ route('master.index', $key) }}" @class(['active' => $section === $key])>{{ $item['label'] }}</a>
    @endforeach
</nav>

<div class="master-grid">
    <section class="panel master-form-panel">
        <div class="panel-heading">
            <div><h2>{{ $editing ? 'Ubah '.$sections[$section]['label'] : 'Tambah '.$sections[$section]['label'] }}</h2><p class="muted">Lengkapi informasi berikut.</p></div>
            @if ($editing)<a class="text-link" href="{{ route('master.index', $section) }}">Batal ubah</a>@endif
        </div>
        <form method="POST" action="{{ $editing ? route('master.update', [$section, $editing->id]) : route('master.store', $section) }}" class="form-stack" enctype="multipart/form-data">
            @csrf
            @if ($editing) @method('PUT') @endif

            @if ($section === 'products')
                <label class="field"><span>Kode produk <b>*</b></span><input name="kode" value="{{ old('kode', $editing?->kode) }}" maxlength="100" required placeholder="Contoh: PRD-004">@error('kode')<small class="field-validation-error">{{ $message }}</small>@enderror</label>
                <label class="field"><span>Nama produk <b>*</b></span><input name="nama" value="{{ old('nama', $editing?->nama) }}" maxlength="255" required placeholder="Nama produk"></label>
                <label class="field"><span>Tipe produk</span><input name="tipe" value="{{ old('tipe', $editing?->tipe) }}" maxlength="120" placeholder="Contoh: Plastik"></label>
                <label class="field"><span>Area / line</span><select name="area_id"><option value="">Belum ditentukan</option>@foreach ($areas as $area)<option value="{{ $area->id }}" @selected((string) old('area_id', $editing?->area_id) === (string) $area->id)>{{ $area->parent ? $area->parent->nama.' / ' : '' }}{{ $area->nama }}{{ $area->parent ? '' : ' · folder gedung' }}</option>@endforeach</select></label>
                @if ($editing?->image_path)
                    <figure class="master-point-check"><img src="{{ route('products.point-check-image', $editing) }}" alt="Gambar point check produk saat ini"><figcaption>Visual point check produk saat ini</figcaption></figure>
                @endif
                <label class="field"><span>Gambar visual point check produk</span><input type="file" name="point_check_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="muted">Opsional · JPG / PNG / WebP, maks. 25 MB. Gambar menjadi acuan seluruh pemeriksaan part ini.</small>@error('point_check_image')<small class="field-validation-error">{{ $message }}</small>@enderror</label>
            @elseif ($section === 'areas')
                <label class="field"><span>Nama folder gedung / area / line <b>*</b></span><input name="nama" value="{{ old('nama', $editing?->nama) }}" maxlength="120" required placeholder="Contoh: Gedung A atau Line 1"></label>
                <label class="field"><span>Folder induk</span><select name="parent_id"><option value="">Tidak ada — folder gedung</option>@foreach ($buildingAreas as $building)<option value="{{ $building->id }}" @selected((string) old('parent_id', $editing?->parent_id) === (string) $building->id)>{{ $building->nama }}</option>@endforeach</select><small class="muted">Pilih folder gedung untuk mendaftarkan area/line di dalamnya.</small></label>
                <label class="field"><span>Keterangan</span><textarea name="keterangan" rows="3" maxlength="255" placeholder="Keterangan area">{{ old('keterangan', $editing?->keterangan) }}</textarea></label>
            @elseif ($section === 'accounts')
                <label class="field"><span>Nama pengguna <b>*</b></span><input name="name" value="{{ old('name', $editing?->name) }}" maxlength="255" required placeholder="Nama lengkap"></label>
                <label class="field"><span>Email login <b>*</b></span><input type="email" name="email" value="{{ old('email', $editing?->email) }}" maxlength="255" required placeholder="nama@perusahaan.com"></label>
                <label class="field"><span>Akses <b>*</b></span><select name="role" required>@foreach (['viewer' => 'Hanya lihat', 'inspector' => 'Input inspeksi', 'admin' => 'Akses penuh / Admin'] as $role => $label)<option value="{{ $role }}" @selected(old('role', $editing?->role ?? 'viewer') === $role)>{{ $label }}</option>@endforeach</select></label>
                <label class="field"><span>{{ $editing ? 'Kata sandi baru (opsional)' : 'Kata sandi (min. 12 karakter) *' }}</span><input type="password" name="password" minlength="12" autocomplete="new-password" {{ $editing ? '' : 'required' }}></label>
                <label class="field"><span>Ulangi kata sandi {{ $editing ? '' : '*' }}</span><input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" {{ $editing ? '' : 'required' }}></label>
            @elseif ($section === 'inspectors')
                <label class="field"><span>Nama inspector <b>*</b></span><input name="nama" value="{{ old('nama', $editing?->nama) }}" maxlength="255" required placeholder="Nama lengkap"></label>
                <label class="field"><span>Departemen / line</span><input name="dept" value="{{ old('dept', $editing?->dept) }}" maxlength="150" placeholder="Contoh: QC Line 1"></label>
                <label class="field"><span>Email inspector <b>*</b></span><input type="email" name="email" value="{{ old('email', $editing?->email) }}" maxlength="255" required placeholder="nama@perusahaan.com"></label>
            @elseif ($section === 'defects')
                <label class="field"><span>Nama defect <b>*</b></span><input name="nama" value="{{ old('nama', $editing?->nama) }}" maxlength="255" required placeholder="Contoh: Goresan permukaan"></label>
                <label class="field"><span>Tipe <b>*</b></span><select name="tipe" required>@foreach (['Dimensi', 'Visual', 'Fungsi'] as $option)<option value="{{ $option }}" @selected(old('tipe', $editing?->tipe ?? 'Visual') === $option)>{{ $option }}</option>@endforeach</select></label>
                <label class="field"><span>Kategori <b>*</b></span><select name="kategori" required>@foreach (['Minor', 'Mayor', 'Kritis'] as $option)<option value="{{ $option }}" @selected(old('kategori', $editing?->kategori ?? 'Minor') === $option)>{{ $option }}</option>@endforeach</select></label>
            @else
                @php($isProductStandard = in_array($section, ['dimension-standards', 'visual-standards', 'function-standards'], true))
                @if ($isProductStandard)
                    <div class="form-grid">
                        <label class="field"><span>Area / line terdaftar <b>*</b></span>
                            <select name="area_id" data-standard-line required>
                                <option value="">Pilih line terlebih dahulu</option>
                                @foreach ($standardLines as $line)
                                    <option value="{{ $line->id }}" @selected((string) old('area_id', $editing?->product?->area_id) === (string) $line->id)>{{ $line->parent ? $line->parent->nama.' / ' : '' }}{{ $line->nama }}</option>
                                @endforeach
                                @if ($hasUnassignedProducts)
                                    <option value="__none__" @selected(old('area_id', $editing?->product?->area_id ? (string) $editing->product->area_id : ($editing ? '__none__' : '')) === '__none__')>Produk tanpa line terdaftar</option>
                                @endif
                            </select>
                        </label>
                        <label class="field"><span>Tipe part <b>*</b></span>
                            <select name="product_type" data-standard-type required>
                                <option value="">Pilih tipe part</option>
                                @foreach ($productTypes as $type)
                                    <option value="{{ $type }}" @selected(old('product_type', $editing?->product?->tipe ?: '') === $type)>{{ $type }}</option>
                                @endforeach
                                @if ($hasUntypedProducts)
                                    <option value="__none__" @selected(old('product_type', $editing?->product?->tipe ? $editing->product->tipe : ($editing ? '__none__' : '')) === '__none__')>Tanpa tipe</option>
                                @endif
                            </select>
                        </label>
                    </div>
                    <p class="muted standard-product-hint">Pilih line dan tipe part untuk menyaring daftar produk.</p>
                @endif
                <label class="field"><span>Produk / part <b>*</b></span>
                    <select name="product_id" data-standard-product @disabled($isProductStandard) required>
                        <option value="">@if ($isProductStandard) Pilih line dan tipe part dahulu @else Pilih produk @endif</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                @if ($isProductStandard)
                                    data-area-id="{{ $product->area_id ?? '__none__' }}"
                                    data-product-type="{{ $product->tipe ?: '__none__' }}"
                                @endif
                                @selected((string) old('product_id', $editing?->product_id) === (string) $product->id)>
                                {{ $product->kode }} · {{ $product->nama }}{{ $product->tipe ? ' · '.$product->tipe : ' · Tanpa tipe' }}{{ $product->areaRelation ? ' · '.($product->areaRelation->parent ? $product->areaRelation->parent->nama.' / ' : '').$product->areaRelation->nama : ' · Tanpa line' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                @if ($section === 'dimension-standards')
                    <label class="field"><span>Parameter <b>*</b></span><input name="parameter" value="{{ old('parameter', $editing?->parameter) }}" maxlength="255" required placeholder="Contoh: Panjang"></label>
                    <div class="form-grid form-grid-3">
                        <label class="field"><span>Nilai standar <b>*</b></span><input type="number" step="any" name="nilai_standar" value="{{ old('nilai_standar', $editing?->nilai_standar) }}" required></label>
                        <label class="field"><span>Toleransi min <b>*</b></span><input type="number" min="0" step="any" name="toleransi_min" value="{{ old('toleransi_min', $editing?->toleransi_min ?? 0) }}" required></label>
                        <label class="field"><span>Toleransi max <b>*</b></span><input type="number" min="0" step="any" name="toleransi_max" value="{{ old('toleransi_max', $editing?->toleransi_max ?? 0) }}" required></label>
                    </div>
                    <label class="field"><span>Satuan <b>*</b></span><input name="satuan" value="{{ old('satuan', $editing?->satuan ?? 'mm') }}" maxlength="30" required placeholder="mm"></label>
                @else
                    <label class="field"><span>Item checklist <b>*</b></span><textarea name="checklist" rows="4" maxlength="255" required placeholder="Deskripsikan kondisi yang harus diperiksa">{{ old('checklist', $editing?->checklist) }}</textarea></label>
                @endif
            @endif

            <button class="button button-primary button-full" type="submit">{{ $editing ? 'Simpan perubahan' : 'Tambah data' }}</button>
        </form>
    </section>

    <section class="panel master-list-panel">
        <div class="panel-heading">
            <div><h2>{{ $sections[$section]['label'] }}</h2><p class="muted">{{ $records->count() }} data terdaftar</p></div>
        </div>
        @if ($records->isEmpty())
            <div class="empty-state compact"><span class="empty-icon">▤</span><strong>Belum ada data</strong><p>Gunakan formulir untuk menambah data pertama.</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table master-table">
                    <thead><tr>
                        @if ($section === 'products')<th>Kode / produk</th><th>Tipe</th><th>Area</th>
                        @elseif ($section === 'areas')<th>Folder</th><th>Keterangan</th><th>Isi folder</th>
                        @elseif ($section === 'accounts')<th>Nama</th><th>Email</th><th>Akses</th>
                        @elseif ($section === 'inspectors')<th>Nama</th><th>Departemen</th><th>Email</th>
                        @elseif ($section === 'defects')<th>Nama defect</th><th>Tipe</th><th>Kategori</th>
                        @elseif ($section === 'dimension-standards')<th>Produk</th><th>Parameter</th><th>Nilai & toleransi</th>
                        @elseif ($section === 'visual-standards')<th>Produk</th><th>Checklist / gambar point check</th><th>Ditambahkan</th>
                        @else<th>Produk</th><th>Checklist</th><th>Ditambahkan</th>@endif
                        <th>Aksi</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($records as $record)
                        <tr>
                            @if ($section === 'products')
                                <td><strong>{{ $record->kode }}</strong><small class="table-subtitle">{{ $record->nama }}</small>@if ($record->image_path)<figure class="master-point-check"><img src="{{ route('products.point-check-image', $record) }}" alt="Visual point check {{ $record->kode }}" loading="lazy"><figcaption>Visual point check</figcaption></figure>@else<small class="table-subtitle">Belum ada visual point check</small>@endif</td><td>{{ $record->tipe ?: '—' }}</td><td>{{ $record->areaRelation?->parent ? $record->areaRelation->parent->nama.' / ' : '' }}{{ $record->areaRelation?->nama ?? $record->area ?: '—' }}</td>
                            @elseif ($section === 'areas')
                                <td><strong>{{ $record->nama }}</strong><small class="table-subtitle">{{ $record->parent ? 'Area / line · '.$record->parent->nama : 'Folder gedung' }}</small></td><td>{{ $record->keterangan ?: '—' }}</td><td>{{ $record->parent ? ($record->products_count ?? $record->products()->count()).' produk' : ($record->children_count ?? $record->children()->count()).' area/line · '.($record->products_count ?? $record->products()->count()).' produk' }}</td>
                            @elseif ($section === 'accounts')
                                <td><strong>{{ $record->name }}</strong>@if ((int) $record->id === (int) auth()->id())<small class="table-subtitle">Akun saat ini</small>@endif</td><td>{{ $record->email }}</td><td><span class="badge badge-neutral">{{ ['viewer' => 'Hanya lihat', 'inspector' => 'Input inspeksi', 'admin' => 'Akses penuh'][$record->role] ?? $record->role }}</span></td>
                            @elseif ($section === 'inspectors')
                                <td><strong>{{ $record->nama }}</strong></td><td>{{ $record->dept ?: '—' }}</td><td>{{ $record->email ?: '—' }}</td>
                            @elseif ($section === 'defects')
                                <td><strong>{{ $record->nama }}</strong></td><td>{{ $record->tipe }}</td><td><span class="badge badge-neutral">{{ $record->kategori }}</span></td>
                            @elseif ($section === 'dimension-standards')
                                <td><strong>{{ $record->product?->kode ?? '—' }}</strong><small class="table-subtitle">{{ $record->product?->nama ?? 'Produk dihapus' }}</small><small class="table-subtitle">Tipe: {{ $record->product?->tipe ?: 'Tanpa tipe' }} · Line: {{ $record->product?->areaRelation?->parent ? $record->product->areaRelation->parent->nama.' / ' : '' }}{{ $record->product?->areaRelation?->nama ?? 'Belum didaftarkan' }}</small></td><td>{{ $record->parameter }}</td><td>{{ $record->nilai_standar }} ± {{ max((float) $record->toleransi_min, (float) $record->toleransi_max) }} {{ $record->satuan }}<small class="table-subtitle">−{{ $record->toleransi_min }} / +{{ $record->toleransi_max }}</small></td>
                            @elseif ($section === 'visual-standards')
                                <td><strong>{{ $record->product?->kode ?? '—' }}</strong><small class="table-subtitle">{{ $record->product?->nama ?? 'Produk dihapus' }}</small><small class="table-subtitle">Tipe: {{ $record->product?->tipe ?: 'Tanpa tipe' }} · Line: {{ $record->product?->areaRelation?->parent ? $record->product->areaRelation->parent->nama.' / ' : '' }}{{ $record->product?->areaRelation?->nama ?? 'Belum didaftarkan' }}</small></td><td>{{ $record->checklist }}@if ($record->image_path)<figure class="master-point-check"><img src="{{ route('inspections.point-check-image', $record) }}" alt="Gambar point check {{ $record->checklist }}" loading="lazy"></figure>@else<small class="table-subtitle">Belum ada gambar point check</small>@endif</td><td>{{ $record->created_at->format('d M Y') }}</td>
                            @else
                                <td><strong>{{ $record->product?->kode ?? '—' }}</strong><small class="table-subtitle">{{ $record->product?->nama ?? 'Produk dihapus' }}</small><small class="table-subtitle">Tipe: {{ $record->product?->tipe ?: 'Tanpa tipe' }} · Line: {{ $record->product?->areaRelation?->parent ? $record->product->areaRelation->parent->nama.' / ' : '' }}{{ $record->product?->areaRelation?->nama ?? 'Belum didaftarkan' }}</small></td><td>{{ $record->checklist }}</td><td>{{ $record->created_at->format('d M Y') }}</td>
                            @endif
                            <td class="row-actions">
                                <a class="icon-button" href="{{ route('master.index', ['section' => $section, 'edit' => $record->id]) }}">Ubah</a>
                                @if ($section !== 'accounts' || (int) $record->id !== (int) auth()->id())
                                    <form method="POST" action="{{ route('master.destroy', [$section, $record->id]) }}" onsubmit="return confirm('Hapus data ini?')">@csrf @method('DELETE')<button class="icon-button danger-text" type="submit">Hapus</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection

@if (in_array($section, ['dimension-standards', 'visual-standards', 'function-standards'], true))
    @push('scripts')
        <script>
            const standardLine = document.querySelector('[data-standard-line]');
            const standardType = document.querySelector('[data-standard-type]');
            const standardProduct = document.querySelector('[data-standard-product]');
            if (standardLine && standardType && standardProduct) {
                const productOptions = [...standardProduct.options].slice(1);
                const filterStandardProducts = () => {
                    const lineId = standardLine.value;
                    const productType = standardType.value;
                    const currentProduct = standardProduct.value;
                    let visibleCount = 0;

                    productOptions.forEach((option) => {
                        const matches = lineId !== ''
                            && productType !== ''
                            && option.dataset.areaId === lineId
                            && option.dataset.productType === productType;
                        option.hidden = !matches;
                        option.disabled = !matches;
                        if (matches) visibleCount++;
                    });

                    if (!productOptions.some((option) => option.value === currentProduct && !option.disabled)) {
                        standardProduct.value = '';
                    }
                    standardProduct.disabled = lineId === '' || productType === '' || visibleCount === 0;
                    if (visibleCount === 0 && lineId && productType) {
                        standardProduct.options[0].textContent = 'Tidak ada produk untuk line dan tipe ini';
                    } else {
                        standardProduct.options[0].textContent = 'Pilih produk / part';
                    }
                };

                standardLine.addEventListener('change', filterStandardProducts);
                standardType.addEventListener('change', filterStandardProducts);
                filterStandardProducts();
            }
        </script>
    @endpush
@endif
