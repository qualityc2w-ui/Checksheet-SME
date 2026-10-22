@extends('layouts.app')

@section('title', 'Dashboard')
@section('eyebrow', 'RINGKASAN QUALITY CONTROL')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">OVERVIEW</p>
        <h1>Dashboard</h1>
        <p class="muted">Pantau hasil pemeriksaan kualitas produk Anda.</p>
    </div>
    <div class="dashboard-heading-actions">
        <a class="button button-primary" href="{{ route('inspections.index') }}#form-inspeksi"><span>＋</span> Input inspeksi</a>
    </div>
</div>

<form method="GET" action="{{ route('dashboard') }}" class="panel dashboard-filters">
    <div class="panel-heading"><div><h2>Filter dashboard</h2><p class="muted">Filter berlaku untuk ringkasan, grafik, akurasi dimensi, monitoring part/tipe, dan X-Chart.</p></div></div>
    <div class="dashboard-filter-grid">
        <label class="field"><span>Part / produk</span><select name="product_id"><option value="">Semua part</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((string) ($chartFilters['product_id'] ?? '') === (string) $product->id)>{{ $product->kode }} · {{ $product->nama }}</option>@endforeach</select></label>
        <label class="field"><span>Tipe produk</span><select name="product_type"><option value="">Semua tipe</option>@foreach ($productTypes as $type)<option value="{{ $type }}" @selected(($chartFilters['product_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></label>
        <label class="field"><span>Area / line</span><select name="area_id"><option value="">Semua area</option>@foreach ($areas as $area)<option value="{{ $area->id }}" @selected((string) ($chartFilters['area_id'] ?? '') === (string) $area->id)>{{ $area->parent ? $area->parent->nama.' / ' : '' }}{{ $area->nama }}</option>@endforeach</select></label>
        <label class="field"><span>Shift</span><select name="shift"><option value="">Semua shift</option><option value="Shift 1" @selected(($chartFilters['shift'] ?? '') === 'Shift 1')>Shift 1</option><option value="Shift 2" @selected(($chartFilters['shift'] ?? '') === 'Shift 2')>Shift 2</option></select></label>
        <label class="field"><span>Hasil</span><select name="result"><option value="">Semua hasil</option><option value="OK" @selected(($chartFilters['result'] ?? '') === 'OK')>OK</option><option value="NG" @selected(($chartFilters['result'] ?? '') === 'NG')>NG</option></select></label>
        <label class="field"><span>Dari tanggal</span><input type="date" name="from" value="{{ $chartFilters['from'] }}"></label>
        <label class="field"><span>Sampai tanggal</span><input type="date" name="to" value="{{ $chartFilters['to'] }}"></label>
    </div>
    <div class="filter-actions"><button class="button button-primary" type="submit">Terapkan filter</button><a class="text-link" href="{{ route('dashboard') }}">Reset filter</a></div>
</form>

<section class="stat-grid" aria-label="Ringkasan inspeksi">
    <article class="stat-card">
        <div class="stat-symbol symbol-blue">▤</div>
        <div><p>Total produk</p><strong>{{ number_format($productCount) }}</strong><small>produk terdaftar</small></div>
    </article>
    <article class="stat-card">
        <div class="stat-symbol symbol-violet">◷</div>
        <div><p>Inspeksi sesuai filter</p><strong>{{ number_format($totalCount) }}</strong><small>{{ $chartFilters['from'] }} s.d. {{ $chartFilters['to'] }}</small></div>
    </article>
    <article class="stat-card">
        <div class="stat-symbol symbol-green">✓</div>
        <div><p>Hasil OK</p><strong>{{ number_format($passCount) }}</strong><small>{{ number_format($passPercentage, 1) }}% dari hasil terfilter</small></div>
    </article>
    <article class="stat-card">
        <div class="stat-symbol symbol-red">!</div>
        <div><p>Perlu tindak lanjut</p><strong>{{ number_format($ngCount) }}</strong><small>hasil NG · sesuai filter</small></div>
    </article>
</section>

<div class="dashboard-grid">
    <section class="panel chart-panel">
        <div class="panel-heading">
            <div><h2>Aktivitas inspeksi</h2><p class="muted">Jumlah OK/NG per hari dalam rentang filter</p></div>
            <a class="text-link" href="{{ route('inspections.index') }}">Lihat inspeksi →</a>
        </div>
        <div class="chart" role="img" aria-label="Jumlah inspeksi per hari selama 14 hari terakhir">
            @php($dailyMaximum = max(1, $daily->max('total')))
            @foreach ($daily as $day)
                <div class="chart-column">
                    <strong class="chart-value">{{ $day['total'] }}</strong>
                    <div class="chart-bars">
                        @if ($day['total'] > 0)
                            <span class="bar-pass" style="height: {{ max(0, ($day['ok'] / $dailyMaximum) * 100) }}%" title="{{ $day['ok'] }} OK"></span>
                            <span class="bar-ng" style="height: {{ max(0, ($day['ng'] / $dailyMaximum) * 100) }}%" title="{{ $day['ng'] }} NG"></span>
                        @else
                            <span class="bar-empty"></span>
                        @endif
                    </div>
                    <small>{{ $day['label'] }}</small>
                </div>
            @endforeach
        </div>
        <div class="chart-legend"><span><i class="legend-pass"></i> OK</span><span><i class="legend-ng"></i> NG</span></div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div><h2>Inspeksi terbaru</h2><p class="muted">Aktivitas terakhir yang tercatat</p></div>
        </div>
        @if ($recent->isEmpty())
            <div class="empty-state compact"><span class="empty-icon">✓</span><strong>Belum ada inspeksi</strong><p>Mulai catat pemeriksaan pertama Anda.</p><a class="text-link" href="{{ route('inspections.index') }}#form-inspeksi">Input inspeksi →</a></div>
        @else
            <div class="recent-list">
                @foreach ($recent as $inspection)
                    <a class="recent-item" href="{{ route('inspections.index') }}">
                        <span class="recent-icon">{{ $inspection->hasil_akhir === 'NG' ? '!' : '✓' }}</span>
                        <span class="recent-main"><strong>{{ $inspection->product?->nama ?? 'Produk dihapus' }}</strong><small>{{ $inspection->tanggal->format('d M Y') }} · {{ $inspection->shift }} · {{ $inspection->sesi }}</small></span>
                        <span @class(['badge', 'badge-ng' => $inspection->hasil_akhir === 'NG', 'badge-pass' => $inspection->hasil_akhir !== 'NG'])>{{ $inspection->hasil_akhir }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>

<div class="dashboard-grid dashboard-analytics-grid">
    <section class="panel percentage-panel">
        <div class="panel-heading"><div><h2>Persentase hasil inspeksi</h2><p class="muted">Proporsi OK dan NG sesuai filter</p></div></div>
        <div class="percentage-content">
            <div class="percentage-ring" style="background: {{ $totalCount ? 'conic-gradient(#5d7bec 0 '.$passPercentage.'%, #f2948c '.$passPercentage.'% 100%)' : '#e8edf4' }}" role="img" aria-label="{{ $passPercentage }} persen OK dan {{ number_format($totalCount ? 100 - $passPercentage : 0, 1) }} persen NG">
                <span><strong>{{ number_format($passPercentage, 1) }}%</strong><small>OK</small></span>
            </div>
            <div class="percentage-legend">
                <span><i class="legend-pass"></i><span>OK<strong>{{ number_format($passCount) }} · {{ number_format($passPercentage, 1) }}%</strong></span></span>
                <span><i class="legend-ng"></i><span>NG<strong>{{ number_format($ngCount) }} · {{ number_format($totalCount ? 100 - $passPercentage : 0, 1) }}%</strong></span></span>
                <small>{{ number_format($totalCount) }} inspeksi</small>
            </div>
        </div>
    </section>
    <section class="panel percentage-panel dimension-accuracy-panel">
        <div class="panel-heading"><div><h2>Akurasi dimensi part</h2><p class="muted">Rasio hasil ukur dimensi yang berstatus OK sesuai filter</p></div></div>
        <div class="percentage-content">
            <div class="dimension-accuracy-ring" style="background: {{ $dimensionMeasurementCount ? 'conic-gradient(#16a879 0 '.$dimensionAccuracy.'%, #f2948c '.$dimensionAccuracy.'% 100%)' : '#e8edf4' }}" role="img" aria-label="{{ $dimensionMeasurementCount ? 'Akurasi dimensi '.number_format($dimensionAccuracy, 1).' persen dari '.number_format($dimensionMeasurementCount).' hasil ukur' : 'Belum ada hasil ukur dimensi pada filter ini' }}">
                <span><strong>{{ $dimensionMeasurementCount ? number_format($dimensionAccuracy, 1).'%' : '—' }}</strong><small>AKURASI</small></span>
            </div>
            <div class="percentage-legend">
                <span><i class="legend-pass"></i><span>Dimensi OK<strong>{{ number_format($dimensionOkCount) }}@if ($dimensionMeasurementCount) · {{ number_format($dimensionAccuracy, 1) }}%@endif</strong></span></span>
                <span><i class="legend-ng"></i><span>Dimensi NG<strong>{{ number_format($dimensionNgCount) }}@if ($dimensionMeasurementCount) · {{ number_format(100 - $dimensionAccuracy, 1) }}%@endif</strong></span></span>
                <small>{{ $dimensionMeasurementCount ? number_format($dimensionMeasurementCount).' hasil ukur dimensi' : 'Belum ada hasil ukur dimensi pada filter ini' }}</small>
            </div>
        </div>
    </section>
    <section class="panel monitoring-panel">
        <div class="panel-heading"><div><h2>Monitoring part / produk</h2><p class="muted">Jumlah inspeksi OK dan NG berdasarkan part</p></div></div>
        @forelse ($partMonitoring as $part)
            <div class="monitor-row">
                <span class="monitor-label"><strong>{{ $part['label'] }}</strong><small>{{ $part['name'] }} · {{ $part['type'] }}</small></span>
                <span class="monitor-bar"><i class="monitor-ok" style="width: {{ ($part['total'] ? ($part['total'] - $part['ng']) / $part['total'] : 0) * 100 }}%"></i><i class="monitor-ng" style="width: {{ ($part['total'] ? $part['ng'] / $part['total'] : 0) * 100 }}%"></i></span>
                <strong class="monitor-value">{{ $part['total'] }} <small>(NG {{ $part['ng'] }})</small></strong>
            </div>
        @empty
            <p class="empty-state compact">Belum ada inspeksi pada filter ini.</p>
        @endforelse
        <div class="chart-legend"><span><i class="legend-pass"></i> OK</span><span><i class="legend-ng"></i> NG</span></div>
    </section>
    <section class="panel monitoring-panel">
        <div class="panel-heading"><div><h2>Monitoring tipe produk</h2><p class="muted">Jumlah inspeksi OK dan NG berdasarkan tipe</p></div></div>
        @forelse ($typeMonitoring as $type)
            <div class="monitor-row">
                <span class="monitor-label"><strong>{{ $type['label'] }}</strong></span>
                <span class="monitor-bar"><i class="monitor-ok" style="width: {{ ($type['total'] ? ($type['total'] - $type['ng']) / $type['total'] : 0) * 100 }}%"></i><i class="monitor-ng" style="width: {{ ($type['total'] ? $type['ng'] / $type['total'] : 0) * 100 }}%"></i></span>
                <strong class="monitor-value">{{ $type['total'] }} <small>(NG {{ $type['ng'] }})</small></strong>
            </div>
        @empty
            <p class="empty-state compact">Belum ada data tipe pada filter ini.</p>
        @endforelse
        <div class="chart-legend"><span><i class="legend-pass"></i> OK</span><span><i class="legend-ng"></i> NG</span></div>
    </section>
</div>

<section class="panel xchart-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">STATISTICAL PROCESS CONTROL</p><h2>X-Chart · Stabilitas dimensi</h2><p class="muted">Rata-rata harian nilai parameter terpilih dibandingkan dengan batas spesifikasi bawah dan atas.</p></div>
        @if ($standard)
            <span class="badge badge-neutral">{{ $chartPoints->count() }} hari</span>
        @endif
    </div>
    <form method="GET" action="{{ route('dashboard') }}" class="xchart-filters" id="xchart-filter-form">
        <label class="field"><span>Produk</span>
            <select name="product_id">
                <option value="">Semua part</option>
                @foreach ($chartProducts as $product)
                    <option value="{{ $product->id }}" data-area-id="{{ $product->area_id ?? '' }}" data-building-id="{{ $areas->firstWhere('id', (int) $product->area_id)?->parent_id ?? '' }}" data-product-type="{{ $product->tipe ?? '' }}" @selected((string) ($chartFilters['product_id'] ?? '') === (string) $product->id)>{{ $product->kode }} · {{ $product->nama }}</option>
                @endforeach
            </select>
        </label>
        <label class="field"><span>Parameter dimensi</span>
            <select name="parameter" required>
                @foreach ($standards as $dimension)
                    <option value="{{ $dimension->parameter }}" @selected($chartFilters['parameter'] === $dimension->parameter)>{{ $dimension->parameter }} · {{ $dimension->satuan }}</option>
                @endforeach
            </select>
        </label>
        <label class="field"><span>Area / line</span>
            <select name="area_id"><option value="">Semua area</option>@foreach ($chartAreas as $area)<option value="{{ $area->id }}" @selected((string) ($chartFilters['area_id'] ?? '') === (string) $area->id)>{{ $area->parent ? $area->parent->nama.' / ' : '' }}{{ $area->nama }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Shift</span>
            <select name="shift"><option value="">Semua shift</option><option value="Shift 1" @selected(($chartFilters['shift'] ?? '') === 'Shift 1')>Shift 1</option><option value="Shift 2" @selected(($chartFilters['shift'] ?? '') === 'Shift 2')>Shift 2</option></select>
        </label>
        <label class="field"><span>Dari tanggal</span><input type="date" name="from" value="{{ $chartFilters['from'] }}"></label>
        <label class="field"><span>Sampai tanggal</span><input type="date" name="to" value="{{ $chartFilters['to'] }}"></label>
        <label class="field"><span>Tipe produk</span><select name="product_type"><option value="">Semua tipe</option>@foreach ($chartTypes as $type)<option value="{{ $type }}" @selected(($chartFilters['product_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></label>
        <label class="field"><span>Hasil inspeksi</span><select name="result"><option value="">Semua hasil</option><option value="OK" @selected(($chartFilters['result'] ?? '') === 'OK')>OK</option><option value="NG" @selected(($chartFilters['result'] ?? '') === 'NG')>NG</option></select></label>
        <div class="filter-actions"><small class="muted">Filter X-Chart diterapkan otomatis saat pilihan diubah.</small><a class="text-link" href="{{ route('dashboard') }}">Reset</a></div>
    </form>
    @if (! $standard)
        <div class="empty-state compact"><span class="empty-icon">⌁</span><strong>Belum ada standar dimensi</strong><p>Daftarkan parameter dan batas toleransi pada Master Data untuk menampilkan X-Chart.</p><a class="text-link" href="{{ route('master.index', 'dimension-standards') }}">Atur standar dimensi →</a></div>
    @elseif ($chartPoints->isEmpty())
        <div class="limit-summary">
            <span><small>Batas bawah</small><strong>{{ number_format($lowerLimit, 4) }} {{ $standard->satuan }}</strong></span>
            <span><small>Nilai standar</small><strong>{{ number_format((float) $standard->nilai_standar, 4) }} {{ $standard->satuan }}</strong></span>
            <span><small>Batas atas</small><strong>{{ number_format($upperLimit, 4) }} {{ $standard->satuan }}</strong></span>
        </div>
        <div class="empty-state compact"><span class="empty-icon">⌁</span><strong>Belum ada data pengukuran</strong><p>Belum ada nilai ukur untuk parameter {{ $standard->parameter }} pada filter ini.</p></div>
    @else
        <div class="limit-summary">
            <span><small>Batas bawah (LSL)</small><strong>{{ number_format($lowerLimit, 4) }} {{ $standard->satuan }}</strong></span>
            <span><small>Nilai standar</small><strong>{{ number_format((float) $standard->nilai_standar, 4) }} {{ $standard->satuan }}</strong></span>
            <span><small>Batas atas (USL)</small><strong>{{ number_format($upperLimit, 4) }} {{ $standard->satuan }}</strong></span>
            <span><small>Rata-rata harian di luar batas</small><strong class="{{ $chartPoints->where('result', 'NG')->isNotEmpty() ? 'ng-value' : 'ok-value' }}">{{ $chartPoints->where('result', 'NG')->count() }} hari</strong></span>
        </div>
        <div class="xchart-scroll">
            <svg class="xchart" viewBox="0 0 1000 285" role="img" aria-label="X-Chart rata-rata harian {{ $standard->parameter }}; garis merah menunjukkan batas toleransi">
                @foreach ([30, 135, 240] as $gridY)
                    <line x1="70" y1="{{ $gridY }}" x2="970" y2="{{ $gridY }}" class="xchart-grid"/>
                @endforeach
                <line x1="70" y1="{{ $lowerY }}" x2="970" y2="{{ $lowerY }}" class="limit-line"/>
                <line x1="70" y1="{{ $upperY }}" x2="970" y2="{{ $upperY }}" class="limit-line"/>
                <text x="5" y="{{ $lowerY - 5 }}" class="limit-label">Min {{ number_format($lowerLimit, 3) }}</text>
                <text x="5" y="{{ $upperY - 5 }}" class="limit-label">Max {{ number_format($upperLimit, 3) }}</text>
                @if ($chartPoints->count() > 1)
                    <polyline points="{{ $chartPoints->map(fn ($point) => number_format($point['x'], 2, '.', '').','.number_format($point['y'], 2, '.', ''))->implode(' ') }}" class="xchart-line"/>
                @endif
                @foreach ($chartPoints as $index => $point)
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" class="{{ $point['result'] === 'NG' ? 'point-ng' : 'point-ok' }}"><title>{{ $point['tanggal'] }} · rata-rata {{ number_format($point['average'], 4) }} {{ $standard->satuan }} dari {{ $point['count'] }} pengukuran · {{ $point['result'] }}</title></circle>
                    @if ($index === 0 || $index === $chartPoints->count() - 1 || $index % max(1, (int) ceil($chartPoints->count() / 7)) === 0)
                        <text x="{{ $point['x'] }}" y="{{ max(18, $point['y'] - 10) }}" text-anchor="middle" class="xchart-value-label">{{ number_format($point['average'], 3) }}</text>
                        <text x="{{ $point['x'] }}" y="270" text-anchor="middle" class="xchart-label">{{ $point['tanggal'] }}</text>
                    @endif
                @endforeach
            </svg>
        </div>
        <div class="chart-legend"><span><i class="legend-pass"></i> Dalam batas</span><span><i class="legend-ng"></i> Di luar batas</span><span><i class="legend-limit"></i> Batas minimum / maksimum</span></div>
    @endif
</section>

<div class="quick-links">
    @if (auth()->user()->isAdmin())
        <a href="{{ route('master.index', 'areas') }}"><span>＋</span><div><strong>Kelola area / line</strong><small>Daftarkan area pemeriksaan</small></div><b>→</b></a>
        <a href="{{ route('master.index', 'dimension-standards') }}"><span>＋</span><div><strong>Standar dimensi</strong><small>Atur batas minimum dan maksimum</small></div><b>→</b></a>
        <a href="{{ route('master.index', 'accounts') }}"><span>＋</span><div><strong>Kelola akun</strong><small>Atur peran dan akses pengguna</small></div><b>→</b></a>
    @endif
</div>
@endsection

@push('scripts')
    <script>
        const xchartFilterForm = document.getElementById('xchart-filter-form');
        const xchartProductSelect = xchartFilterForm?.querySelector('[name="product_id"]');
        const xchartAreaSelect = xchartFilterForm?.querySelector('[name="area_id"]');
        const xchartTypeSelect = xchartFilterForm?.querySelector('[name="product_type"]');
        const allXChartProducts = {{ \Illuminate\Support\Js::from($chartProductFilterData) }};
        const matchingXChartProducts = () => allXChartProducts.filter((product) => {
            const selectedArea = xchartAreaSelect?.value ?? '';
            const selectedType = xchartTypeSelect?.value ?? '';
            const matchesArea = !selectedArea || product.areaId === selectedArea || product.buildingId === selectedArea;
            const matchesType = !selectedType || product.type === selectedType;

            return matchesArea && matchesType;
        });

        xchartProductSelect?.addEventListener('change', () => {
            const selectedOption = xchartProductSelect.selectedOptions[0];
            if (selectedOption?.value) {
                if (xchartAreaSelect) xchartAreaSelect.value = selectedOption.dataset.areaId === '' ? '' : selectedOption.dataset.areaId;
                if (xchartTypeSelect) xchartTypeSelect.value = selectedOption.dataset.productType ?? '';
            }
            xchartFilterForm?.requestSubmit();
        });

        xchartAreaSelect?.addEventListener('change', () => {
            if (xchartTypeSelect?.value && matchingXChartProducts().length === 0) {
                xchartTypeSelect.value = '';
            }
            if (xchartProductSelect?.value && !matchingXChartProducts().some((product) => product.id === xchartProductSelect.value)) {
                xchartProductSelect.value = '';
            }
            xchartFilterForm?.requestSubmit();
        });

        xchartTypeSelect?.addEventListener('change', () => {
            if (xchartAreaSelect?.value && matchingXChartProducts().length === 0) {
                xchartAreaSelect.value = '';
            }
            if (xchartProductSelect?.value && !matchingXChartProducts().some((product) => product.id === xchartProductSelect.value)) {
                xchartProductSelect.value = '';
            }
            xchartFilterForm?.requestSubmit();
        });

        xchartFilterForm?.addEventListener('change', (event) => {
            if ((event.target instanceof HTMLSelectElement || event.target instanceof HTMLInputElement)
                && !['product_id', 'area_id', 'product_type'].includes(event.target.name)) {
                xchartFilterForm.requestSubmit();
            }
        });
    </script>
@endpush
