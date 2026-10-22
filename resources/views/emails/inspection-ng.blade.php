LAPORAN TEMUAN QC — Inspeksi #{{ $inspection->id }}

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
    return collect($items)->map(fn (string $path, string|int $itemId) => basename($path).': '.\Illuminate\Support\Facades\URL::temporarySignedRoute(
        'inspections.shared-evidence',
        now()->addHours(24),
        ['inspection' => $inspection->id, 'section' => $section, 'item' => $itemId]
    ));
}) as $link)
{{ $link }}
@empty
Tidak ada foto bukti.
@endforelse
