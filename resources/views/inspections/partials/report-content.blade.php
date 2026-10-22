<section class="panel report-section">
    @if ($reportGroups->isEmpty())
        <p>Tidak ada hasil inspeksi untuk filter yang dipilih.</p>
    @else
        @foreach ($reportGroups as $group)
            <article class="report-inspection inspection">
                <h2>{{ $group['product']?->kode ?? '—' }} — {{ $group['product']?->nama ?? 'Produk dihapus' }}</h2>
                <p class="report-group-summary">
                    Tipe part: <strong>{{ $group['product']?->tipe ?: '—' }}</strong>
                    · Tanggal: <strong>{{ $group['date']->format('d/m/Y') }}</strong>
                    · Shift: <strong>{{ $group['shift'] }}</strong>
                    · Gedung: <strong>{{ $group['area']?->parent?->nama ?? $group['area']?->nama ?? '—' }}</strong>
                    · Area / Line: <strong>{{ $group['area']?->parent ? $group['area']->nama : '—' }}</strong>
                </p>

                @if ($group['point_check_images']->isNotEmpty())
                    <section class="report-point-check">
                        <h3>Visualisasi point check</h3>
                        <p>Acuan point check untuk seluruh pemeriksaan part pada shift ini.</p>
                        <div class="report-point-check-images">
                            @foreach ($group['point_check_images'] as $pointCheckImage)
                                <figure>
                                    @if ($pointCheckImage['data_uri'])
                                        <img src="{{ $pointCheckImage['data_uri'] }}" alt="Point check: {{ $pointCheckImage['checklist'] }}">
                                    @else
                                        <div class="report-point-check-missing">{{ $pointCheckImage['missing_message'] ?? 'Gambar point check tidak tersedia' }}</div>
                                    @endif
                                    <figcaption>{{ $pointCheckImage['checklist'] }}</figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </section>
                @endif

                <h3>Ringkasan sesi pengecekan dalam satu shift</h3>
                <table class="report-table">
                    <thead><tr><th>Sesi</th><th>Inspector</th><th>Sample</th><th>Hasil</th><th>Approval</th></tr></thead>
                    <tbody>
                        @foreach ($group['sessions'] as $session => $sessionInspections)
                            @forelse ($sessionInspections as $inspection)
                                <tr>
                                    <td>{{ $session }}</td>
                                    <td>{{ $inspection->inspectorName }}</td>
                                    <td>{{ number_format($inspection->sample) }}</td>
                                    <td>{{ $inspection->hasil_akhir }}</td>
                                    <td>
                                        @if ($inspection->approved)
                                            <span class="report-stamp stamp">QC 2W DPM</span>
                                            <br>Disetujui {{ $inspection->approver?->name ?? 'Admin' }} · {{ $inspection->approved_at?->format('d/m/Y H:i') }}
                                        @else
                                            Menunggu approval
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td>{{ $session }}</td><td colspan="4">Belum ada hasil sesi ini.</td></tr>
                            @endforelse
                        @endforeach
                    </tbody>
                </table>

                @foreach ($group['sessions'] as $session => $sessionInspections)
                    <section class="report-session">
                        <h3>Hasil sesi {{ $session }}</h3>
                        @forelse ($sessionInspections as $inspection)
                            <p class="report-inspector"><strong>{{ $inspection->inspectorName }}</strong> · Sample {{ number_format($inspection->sample) }} · Pengukuran dimensi {{ $inspection->measurement_mode === 'automatic' ? 'otomatis' : 'manual' }} · Hasil {{ $inspection->hasil_akhir }}</p>
                            @foreach (['dimensi' => 'Dimensi', 'visual' => 'Visual', 'fungsi' => 'Fungsi'] as $key => $label)
                                <h4>{{ $label }}</h4>
                                <table class="report-table">
                                    <thead><tr><th>Item pemeriksaan</th><th>Batas / Standar</th><th>Nilai / Hasil</th><th>Judgment</th></tr></thead>
                                    <tbody>
                                    @forelse (($inspection->{$key}['detail'] ?? []) as $item)
                                        <tr>
                                            <td>{{ $item['parameter'] ?? $item['checklist'] ?? '—' }}</td>
                                            <td>
                                                @if (isset($item['min'], $item['max']))
                                                    {{ $item['min'] }} – {{ $item['max'] }} {{ $item['satuan'] ?? '' }}
                                                @else
                                                    {{ $item['checklist'] ?? 'Sesuai checklist' }}
                                                @endif
                                            </td>
                                            <td>{{ $item['nilai'] ?? $item['hasil'] ?? '—' }} {{ $item['satuan'] ?? '' }}</td>
                                            <td>{{ $item['hasil'] ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4">Tidak ada item pemeriksaan.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                                @if (! empty($inspection->{$key}['catatan']))
                                    <p><strong>Catatan {{ strtolower($label) }}:</strong> {{ $inspection->{$key}['catatan'] }}</p>
                                @endif
                            @endforeach
                        @empty
                            <p>Belum ada hasil inspeksi untuk sesi {{ strtolower($session) }}.</p>
                        @endforelse
                    </section>
                @endforeach
            </article>
        @endforeach
    @endif
</section>
