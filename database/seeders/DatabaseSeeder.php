<?php

namespace Database\Seeders;

use App\Models\Defect;
use App\Models\Area;
use App\Models\DimensionStandard;
use App\Models\FunctionStandard;
use App\Models\Inspector;
use App\Models\Product;
use App\Models\VisualStandard;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $products = collect([
            ['kode' => 'PRD-001', 'nama' => 'Casing Plastik A', 'tipe' => 'Plastik', 'area' => 'Line 1'],
            ['kode' => 'PRD-002', 'nama' => 'Bracket Metal B', 'tipe' => 'Metal', 'area' => 'Line 2'],
            ['kode' => 'PRD-003', 'nama' => 'PCB Modul C', 'tipe' => 'Elektronik', 'area' => 'Line 3'],
        ])->mapWithKeys(fn (array $attributes) => [
            $attributes['kode'] => Product::firstOrCreate(['kode' => $attributes['kode']], $attributes),
        ]);

        foreach ($products as $product) {
            if ($product->area) {
                $area = Area::firstOrCreate(['nama' => $product->area]);
                $product->update(['area_id' => $area->id]);
            }
        }

        foreach ([
            ['nama' => 'Budi Santoso', 'dept' => 'QC Line 1'],
            ['nama' => 'Siti Aminah', 'dept' => 'QC Line 2'],
        ] as $inspector) {
            Inspector::firstOrCreate(['nama' => $inspector['nama']], $inspector);
        }

        foreach ([
            ['nama' => 'Ukuran Diluar Toleransi', 'tipe' => 'Dimensi', 'kategori' => 'Mayor'],
            ['nama' => 'Ketebalan Tidak Sesuai', 'tipe' => 'Dimensi', 'kategori' => 'Minor'],
            ['nama' => 'Goresan Permukaan', 'tipe' => 'Visual', 'kategori' => 'Minor'],
            ['nama' => 'Warna Tidak Merata', 'tipe' => 'Visual', 'kategori' => 'Minor'],
            ['nama' => 'Komponen Tidak Berfungsi', 'tipe' => 'Fungsi', 'kategori' => 'Kritis'],
            ['nama' => 'Respon Tombol Lambat', 'tipe' => 'Fungsi', 'kategori' => 'Mayor'],
        ] as $defect) {
            Defect::firstOrCreate(['nama' => $defect['nama']], $defect);
        }

        foreach ([
            ['product' => 'PRD-001', 'parameter' => 'Panjang', 'nilai_standar' => 50, 'toleransi_min' => 0.5, 'toleransi_max' => 0.5],
            ['product' => 'PRD-001', 'parameter' => 'Lebar', 'nilai_standar' => 30, 'toleransi_min' => 0.3, 'toleransi_max' => 0.3],
            ['product' => 'PRD-002', 'parameter' => 'Diameter Lubang', 'nilai_standar' => 12, 'toleransi_min' => 0.2, 'toleransi_max' => 0.2],
        ] as $standard) {
            DimensionStandard::firstOrCreate(
                ['product_id' => $products[$standard['product']]->id, 'parameter' => $standard['parameter']],
                [
                    'nilai_standar' => $standard['nilai_standar'],
                    'toleransi_min' => $standard['toleransi_min'],
                    'toleransi_max' => $standard['toleransi_max'],
                    'satuan' => 'mm',
                ]
            );
        }

        foreach ([
            ['table' => VisualStandard::class, 'product' => 'PRD-001', 'checklist' => 'Permukaan bebas goresan dan retak'],
            ['table' => VisualStandard::class, 'product' => 'PRD-001', 'checklist' => 'Warna sesuai sampel'],
            ['table' => VisualStandard::class, 'product' => 'PRD-001', 'checklist' => 'Dimensi dan bentuk sesuai'],
            ['table' => FunctionStandard::class, 'product' => 'PRD-001', 'checklist' => 'Komponen berfungsi normal'],
            ['table' => VisualStandard::class, 'product' => 'PRD-002', 'checklist' => 'Lubang bebas burr dan deformasi'],
            ['table' => VisualStandard::class, 'product' => 'PRD-002', 'checklist' => 'Bracket tidak bengkok'],
            ['table' => FunctionStandard::class, 'product' => 'PRD-002', 'checklist' => 'Kekuatan sambungan sesuai'],
            ['table' => VisualStandard::class, 'product' => 'PRD-003', 'checklist' => 'Komponen terpasang lengkap'],
            ['table' => FunctionStandard::class, 'product' => 'PRD-003', 'checklist' => 'Modul menyala dan merespons normal'],
        ] as $standard) {
            $attributes = ['product_id' => $products[$standard['product']]->id, 'checklist' => $standard['checklist']];
            $standard['table']::firstOrCreate($attributes, $attributes);
        }
    }
}
