@extends('layouts.app')

@section('title', 'Pengaturan Temuan')
@section('eyebrow', 'KONFIGURASI TEMUAN 4M')

@section('content')
<div class="page-heading"><div><p class="eyebrow">ADMINISTRASI</p><h1>Pengaturan temuan</h1><p class="muted">Kelola PIC, pilihan formulir, dan akun pengguna Laravel.</p></div><a class="button button-secondary" href="{{ route('master.index', 'accounts') }}">Kelola akun →</a></div>
<nav class="tab-nav"><a class="active" href="{{ route('findings.settings') }}">PIC & pilihan formulir</a><a href="{{ route('master.index', 'accounts') }}">Akun pengguna</a></nav>
<div class="dashboard-grid settings-grid">
    <section class="panel">
        <div class="panel-heading"><div><h2>Tambah PIC</h2><p class="muted">PIC akan menerima notifikasi email tindak lanjut.</p></div></div>
        <form method="POST" action="{{ route('findings.pics.store') }}" class="form-stack">@csrf
            <label class="field"><span>Nama PIC <b>*</b></span><input name="name" value="{{ old('name') }}" maxlength="150" required></label>
            <label class="field"><span>Departemen</span><input name="department" value="{{ old('department') }}" maxlength="150"></label>
            <label class="field"><span>Email PIC <b>*</b></span><input type="email" name="email" value="{{ old('email') }}" maxlength="255" placeholder="nama@perusahaan.com" required></label>
            <button class="button button-primary">Simpan PIC</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Tambah pilihan formulir</h2><p class="muted">Daftarkan area/line, part, tipe, atau jenis masalah.</p></div></div>
        <form method="POST" action="{{ route('findings.options.store') }}" class="form-stack">@csrf
            <label class="field"><span>Jenis pilihan <b>*</b></span><select name="kind" required>@foreach ($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label class="field"><span>Nama pilihan <b>*</b></span><input name="value" maxlength="150" required></label>
            <button class="button button-primary">Tambah pilihan</button>
        </form>
    </section>
</div>
<section class="panel settings-list-panel">
    <div class="panel-heading"><div><h2>Daftar PIC</h2><p class="muted">Nonaktifkan PIC untuk menyembunyikannya dari formulir tanpa menghapus data.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Nama</th><th>Departemen</th><th>Email</th><th>Aktif</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($pics as $pic)
            <tr>
                <td><form method="POST" id="pic-edit-{{ $pic->id }}" action="{{ route('findings.pics.update', $pic) }}">@csrf @method('PUT')</form><input class="setting-input" form="pic-edit-{{ $pic->id }}" name="name" value="{{ $pic->name }}" required maxlength="150"></td><td><input class="setting-input" form="pic-edit-{{ $pic->id }}" name="department" value="{{ $pic->department }}" maxlength="150"></td><td><input class="setting-input" form="pic-edit-{{ $pic->id }}" type="email" name="email" value="{{ $pic->email }}" required maxlength="255"></td>
                <td><label class="setting-active"><input form="pic-edit-{{ $pic->id }}" type="checkbox" name="active" value="1" @checked($pic->active)> Aktif</label></td><td class="table-actions"><button class="text-link button-link" form="pic-edit-{{ $pic->id }}">Simpan</button>
                    <form method="POST" action="{{ route('findings.pics.destroy', $pic) }}" onsubmit="return confirm('Hapus PIC {{ $pic->name }}?')">@csrf @method('DELETE')<button class="text-link button-link">Hapus</button></form></td>
            </tr>
        @empty<tr><td colspan="5">Belum ada PIC.</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="panel settings-list-panel">
    <div class="panel-heading"><div><h2>Pilihan formulir</h2><p class="muted">Pilihan yang terdaftar muncul pada form input temuan.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Jenis</th><th>Nilai</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($options as $option)
            <tr><td>{{ $kinds[$option->kind] ?? $option->kind }}</td><td><strong>{{ $option->value }}</strong></td><td><form method="POST" action="{{ route('findings.options.destroy', $option) }}" onsubmit="return confirm('Hapus pilihan {{ $option->value }}?')">@csrf @method('DELETE')<button class="text-link button-link">Hapus</button></form></td></tr>
        @empty<tr><td colspan="3">Belum ada pilihan formulir.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
