<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QC Inspect') · QC Inspect</title>
    <link rel="stylesheet" href="{{ asset('css/qc.css') }}">
    @stack('styles')
</head>
<body>
<div class="app-frame">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">Q</span>
            <span><strong>QC Inspect</strong><small>QUALITY CONTROL</small></span>
        </a>
        <div class="nav-label">MENU UTAMA</div>
        <nav class="nav-links" aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>
                <span class="nav-icon">▦</span> Dashboard
            </a>
            <a href="{{ route('inspections.index') }}" @class(['active' => request()->routeIs('inspections.index', 'inspections.destroy', 'inspections.approve', 'inspections.email')])>
                <span class="nav-icon">✓</span> Inspeksi QC
            </a>
            <a href="{{ route('inspection-reports.index') }}" @class(['active' => request()->routeIs('inspection-reports.*', 'inspections.report*')])>
                <span class="nav-icon">▤</span> Laporan Inspeksi
            </a>
            <a href="{{ route('findings.dashboard') }}" @class(['active' => request()->routeIs('findings.dashboard')])>
                <span class="nav-icon">◉</span> Dashboard Temuan
            </a>
            <a href="{{ route('findings.index') }}" @class(['active' => request()->routeIs('findings.index', 'findings.show', 'findings.edit', 'findings.create')])>
                <span class="nav-icon">!</span> Temuan 4M
            </a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('findings.settings') }}" @class(['active' => request()->routeIs('findings.settings')])>
                    <span class="nav-icon">⚙</span> Pengaturan Temuan
                </a>
            @endif
            @if (auth()->user()->isAdmin())
                <a href="{{ route('master.index') }}" @class(['active' => request()->routeIs('master.*')])>
                    <span class="nav-icon">▤</span> Master Data
                </a>
            @endif
        </nav>
        <div class="sidebar-footer">
            <span class="status-dot"></span>
            <span><strong>Sistem aktif</strong><small>Data tersimpan lokal</small></span>
        </div>
    </aside>
    <main class="main-area">
        <header class="topbar">
            <div class="mobile-brand"><span class="brand-mark">Q</span><strong>QC Inspect</strong></div>
            <div class="topbar-title">@yield('eyebrow', 'SISTEM QUALITY CONTROL')</div>
            <div class="topbar-actions">
                <div class="topbar-date">{{ now()->translatedFormat('l, d F Y') }}</div>
                <span class="user-name">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit">Keluar</button></form>
            </div>
        </header>
        <div class="page-content">
            @if (session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <strong>Periksa kembali data yang dimasukkan.</strong>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
