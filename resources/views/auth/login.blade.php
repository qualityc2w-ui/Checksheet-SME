<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · QC Inspect</title>
    <link rel="stylesheet" href="{{ asset('css/qc.css') }}">
</head>
<body class="login-page">
    <main class="login-card">
        <a class="login-brand" href="{{ route('login') }}"><span class="brand-mark">Q</span><span><strong>QC Inspect</strong><small>QUALITY CONTROL</small></span></a>
        <p class="eyebrow">SELAMAT DATANG KEMBALI</p>
        <h1>Masuk ke akun Anda</h1>
        <p class="muted login-description">Gunakan akun QC Inspect untuk membuka dashboard pemeriksaan.</p>

        @if ($errors->any())
            <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="form-stack">
            @csrf
            <label class="field"><span>Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="nama@perusahaan.com"></label>
            <label class="field"><span>Kata sandi</span><input type="password" name="password" autocomplete="current-password" required placeholder="Masukkan kata sandi"></label>
            <label class="remember-field"><input type="checkbox" name="remember" value="1"> Tetap masuk di perangkat ini</label>
            <button class="button button-primary button-full" type="submit">Masuk <span>→</span></button>
        </form>
        <p class="login-help">Akun dibuat oleh administrator server melalui perintah <code>php artisan qc:create-admin</code>.</p>
    </main>
</body>
</html>
