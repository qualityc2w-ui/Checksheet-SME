LAPORAN TEMUAN QC — Inspeksi

Produk: {{ $inspection->product?->kode ?? '-' }} - {{ $inspection->product?->nama ?? 'Produk dihapus' }}
Area/Line: {{ $inspection->area?->parent ? $inspection->area->parent->nama.' / '.$inspection->area->nama : ($inspection->area?->nama ?? '-') }}
Inspector: {{ $inspection->inspectorName }}
Tanggal: {{ $inspection->tanggal->format('d/m/Y') }} | {{ $inspection->shift }} | {{ $inspection->sesi }}
Hasil akhir: NG

Temuan:
@foreach (['dimensi' => 'Dimensi', 'visual' => 'Visual', 'fungsi' => 'Fungsi'] as $section => $label)
@foreach (($inspection->{$section}['detail'] ?? []) as $item)
@if (($item['hasil'] ?? '') === 'NG')
- {{ $label }}: {{ $item['parameter'] ?? $item['checklist'] ?? 'Item pemeriksaan' }} — {{ $item['nilai'] ?? 'NG' }} {{ $item['satuan'] ?? '' }}
@endif
@endforeach
@endforeach

Bukti foto (tautan berlaku 24 jam):
@forelse (collect($inspection->evidence ?? [])->filter(fn ($items) => is_array($items))->flatMap(function (array $items, string $section) use ($inspection) {
    $counter = 0;
    return collect($items)->map(function (string $path, string|int $itemId) use ($inspection, $section, &$counter) {
        $counter++;
        $label = 'Bukti foto '.$counter;
        return $label.': '.\Illuminate\Support\Facades\URL::temporarySignedRoute(
            'inspections.shared-evidence',
            now()->addHours(24),
            ['payload' => \Illuminate\Support\Facades\Crypt::encrypt([
                'inspection_id' => (string) $inspection->id,
                'section' => $section,
                'item' => (string) $itemId,
                'user_id' => (string) $inspection->user_id,
            ])]
        );
    });
}) as $link)
{{ $link }}
@empty
Tidak ada foto bukti.
@endforelse
