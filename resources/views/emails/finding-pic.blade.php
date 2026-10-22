Temuan Baru {{ $finding->code }}

Tanggal: {{ $finding->found_date->format('d/m/Y') }}
Shift: {{ $finding->shift }}
Area/Line: {{ $finding->area_line }}
Nama Part: {{ $finding->part_name }}
Tipe Part: {{ $finding->part_type }}
Jenis masalah: {{ $finding->problem_type }}
Kategori 4M: {{ strtoupper($finding->category) }}
Prioritas: {{ \App\Models\Finding::PRIORITIES[$finding->priority] ?? $finding->priority }}
Deskripsi: {{ $finding->description }}
Akar masalah: {{ $finding->root_cause }}
@if ($finding->point_check)
Point check NG: {{ $finding->point_check }}
Hasil: {{ $finding->observed_value }}{{ $finding->unit ? ' '.$finding->unit : '' }}
@endif

Mohon isi rencana perbaikan dan tanggal target melalui tautan berikut:
{{ $finding->responseUrl() }}
