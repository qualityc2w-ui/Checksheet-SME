<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DimensionStandard;
use App\Models\Finding;
use App\Models\FindingPic;
use App\Models\FunctionStandard;
use App\Models\Inspection;
use App\Models\Inspector;
use App\Models\Product;
use App\Models\User;
use App\Models\VisualStandard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use ZipArchive;
use Tests\TestCase;

class InspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_dashboard_and_master_data_pages_load(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/')->assertOk()->assertSee('Dashboard');
        $master = $this->get('/master/products')->assertOk()->assertSee('Tambah Produk');
        $html = $master->getContent();
        $this->assertLessThan(
            strpos($html, route('master.index', 'products')),
            strpos($html, route('master.index', 'areas')),
            'Area / Line should be the first Master Data tab.'
        );
    }

    public function test_dashboard_requires_a_signed_in_user(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda');
    }

    public function test_duplicate_product_code_has_a_clear_validation_message(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Product::create(['kode' => 'PRD-DUPLICATE', 'nama' => 'Produk Pertama']);

        $this->post(route('master.store', 'products'), [
            'kode' => 'PRD-DUPLICATE',
            'nama' => 'Produk Kedua',
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'kode' => 'Kode produk sudah terdaftar. Gunakan kode produk yang berbeda.',
            ]);

        $this->get(route('master.index', 'products'))
            ->assertOk()
            ->assertSee('Kode produk sudah terdaftar. Gunakan kode produk yang berbeda.');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_user_can_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'test-password-123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'test-password-123',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_inspection_result_is_calculated_from_standards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $building = Area::create(['nama' => 'Gedung Uji']);
        $area = Area::create(['nama' => 'Line Uji', 'parent_id' => $building->id]);
        $product = Product::create(['kode' => 'PRD-100', 'nama' => 'Produk Uji', 'area_id' => $area->id]);
        $inspector = Inspector::create([
            'nama' => 'Petugas Uji',
            'dept' => 'QC',
            'email' => 'petugas@example.com',
        ]);
        $dimension = DimensionStandard::create([
            'product_id' => $product->id,
            'parameter' => 'Panjang',
            'nilai_standar' => 10,
            'toleransi_min' => 1,
            'toleransi_max' => 1,
            'satuan' => 'mm',
        ]);
        $visual = VisualStandard::create([
            'product_id' => $product->id,
            'checklist' => 'Bebas goresan',
        ]);
        $function = FunctionStandard::create([
            'product_id' => $product->id,
            'checklist' => 'Berfungsi normal',
        ]);
        Storage::fake('local');

        $this->get(route('inspections.index', ['building_id' => $building->id]))
            ->assertOk()
            ->assertSee('PRD-100')
            ->assertSee('Area / line otomatis')
            ->assertDontSee('Folder area / line');

        $response = $this->post('/inspections', [
            'tanggal' => '2026-10-09',
            'building_id' => $building->id,
            'product_id' => $product->id,
            'inspector_id' => $inspector->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 30,
            'dimensions' => [$dimension->id => '11'],
            'visual_results' => [$visual->id => 'OK'],
            'function_results' => [$function->id => 'NG'],
            'evidence' => [
                'function' => [
                    $function->id => UploadedFile::fake()->createWithContent(
                        'temuan.png',
                        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
                    ),
                ],
            ],
        ]);

        $response->assertRedirect(route('findings.inspection.create', [Inspection::firstOrFail(), 'function', 0]));
        $this->assertDatabaseHas('inspections', [
            'product_id' => $product->id,
            'inspector_id' => $inspector->id,
            'hasil_akhir' => 'NG',
            'measurement_mode' => 'manual',
        ]);

        $inspection = Inspection::firstOrFail();
        $this->assertSame('OK', $inspection->dimensi['detail'][0]['hasil']);
        $this->assertSame('NG', $inspection->fungsi['detail'][0]['hasil']);
        $this->assertCount(1, $inspection->evidence['function']);
        Storage::disk('local')->assertExists($inspection->evidence['function'][$function->id]);
        $this->get(route('inspections.evidence', [$inspection, 0]))->assertOk();
        Mail::assertSent(\App\Mail\InspectionNgReport::class, fn ($mail) => $mail->hasTo('petugas@example.com'));
        $this->assertNotNull($inspection->fresh()->email_sent_at);
        $sharedEvidenceUrl = URL::temporarySignedRoute(
            'inspections.shared-evidence',
            now()->addHour(),
            ['inspection' => $inspection->id, 'section' => 'function', 'item' => $function->id]
        );
        $this->get($sharedEvidenceUrl)->assertOk();
        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'area_id' => $area->id]);
        $this->actingAs(User::factory()->create(['role' => 'inspector']))
            ->post(route('inspections.approve', $inspection))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('inspections.approve', $inspection))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'approved' => true,
            'approved_by' => $admin->id,
        ]);
        $this->get('/inspections')->assertOk()->assertSee('QC 2W', false)->assertSee('DPM');
    }

    public function test_inspection_records_automatic_measurement_mode_and_reports_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $building = Area::create(['nama' => 'Gedung Otomatis']);
        $line = Area::create(['nama' => 'Line Otomatis', 'parent_id' => $building->id]);
        $product = Product::create(['kode' => 'PRD-AUTO', 'nama' => 'Produk Otomatis', 'area_id' => $line->id]);
        $inspector = Inspector::create(['nama' => 'Inspector Otomatis', 'email' => 'otomatis@example.com']);
        $dimension = DimensionStandard::create([
            'product_id' => $product->id,
            'parameter' => 'Diameter',
            'nilai_standar' => 10,
            'toleransi_min' => 1,
            'toleransi_max' => 1,
            'satuan' => 'mm',
        ]);

        $this->get(route('inspections.index', ['building_id' => $building->id]))
            ->assertOk()
            ->assertSee('Sumber pengukuran dimensi')
            ->assertSee('Otomatis (Bluetooth / Wi-Fi)')
            ->assertSee('Baca alat ukur')
            ->assertSee('Mode otomatis disiapkan melalui adaptor perangkat');

        $this->post(route('inspections.store'), [
            'building_id' => $building->id,
            'tanggal' => today()->toDateString(),
            'product_id' => $product->id,
            'inspector_id' => $inspector->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 1,
            'measurement_mode' => 'automatic',
            'dimensions' => [$dimension->id => '10.25'],
            'visual_results' => ['manual' => 'OK'],
            'function_results' => ['manual' => 'OK'],
        ])->assertRedirect(route('inspections.index'));

        $inspection = Inspection::firstOrFail();
        $this->assertSame('automatic', $inspection->measurement_mode);
        $this->assertSame(10.25, $inspection->dimensi['detail'][0]['nilai']);
        $this->get(route('inspections.report.single', $inspection))
            ->assertOk()
            ->assertSee('Pengukuran dimensi otomatis');
    }

    public function test_inspection_ng_opens_separate_4m_entry_for_each_ng_point_and_reuses_photos(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $building = Area::create(['nama' => 'Gedung 4M']);
        $area = Area::create(['nama' => 'Line 4M', 'parent_id' => $building->id]);
        $product = Product::create([
            'kode' => 'PRD-4M',
            'nama' => 'Produk 4M',
            'tipe' => 'Type 4M',
            'area_id' => $area->id,
        ]);
        $inspector = Inspector::create(['nama' => 'Inspector 4M', 'email' => 'inspector4m@example.com']);
        $dimension = DimensionStandard::create([
            'product_id' => $product->id,
            'parameter' => 'Diameter',
            'nilai_standar' => 10,
            'toleransi_min' => 1,
            'toleransi_max' => 1,
            'satuan' => 'mm',
        ]);
        $visual = VisualStandard::create(['product_id' => $product->id, 'checklist' => 'Bebas retak']);
        $pic = FindingPic::create(['name' => 'PIC Integrasi', 'email' => 'integrasi@example.com']);

        $this->post(route('inspections.store'), [
            'building_id' => $building->id,
            'tanggal' => today()->toDateString(),
            'product_id' => $product->id,
            'inspector_id' => $inspector->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 1,
            'dimensions' => [$dimension->id => '12'],
            'visual_results' => [$visual->id => 'NG'],
            'function_results' => ['manual' => 'OK'],
            'evidence' => [
                'dimensions' => [
                    $dimension->id => UploadedFile::fake()->createWithContent(
                        'dimensi.png',
                        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
                    ),
                ],
                'visual' => [
                    $visual->id => UploadedFile::fake()->createWithContent(
                        'visual.png',
                        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
                    ),
                ],
            ],
        ])->assertRedirect(route('findings.inspection.create', [Inspection::firstOrFail(), 'dimensions', 0]));

        $inspection = Inspection::firstOrFail();
        $this->get(route('findings.inspection.create', [$inspection, 'dimensions', 0]))
            ->assertOk()->assertSee('Diameter')->assertSee('Foto bukti ini akan otomatis dilampirkan pada temuan 4M.', false);

        $formInput = [
            'problem_type' => 'Dimensi tidak sesuai',
            'category' => 'machine',
            'priority' => 'high',
            'analysis_man' => 'Operator sudah mengikuti instruksi kerja.',
            'analysis_method' => 'Metode pengukuran perlu ditinjau.',
            'analysis_machine' => 'Mesin pengukur perlu dikalibrasi.',
            'analysis_material' => 'Material sesuai spesifikasi.',
            'root_cause' => 'Alat ukur tidak terkalibrasi sehingga dimensi meleset.',
            'pic_id' => $pic->id,
        ];
        $this->post(route('findings.inspection.store', [$inspection, 'dimensions', 0]), $formInput)
            ->assertRedirect(route('findings.inspection.create', [$inspection, 'visual', 0]));

        $this->post(route('findings.inspection.store', [$inspection, 'visual', 0]), [
            ...$formInput,
            'problem_type' => 'Hasil pemeriksaan visual NG',
        ])->assertRedirect();

        $this->assertDatabaseCount('findings', 2);
        $dimensionFinding = Finding::where('inspection_section', 'dimensions')->firstOrFail();
        $visualFinding = Finding::where('inspection_section', 'visual')->firstOrFail();
        $this->assertSame('Diameter', $dimensionFinding->point_check);
        $this->assertSame('12', $dimensionFinding->observed_value);
        $this->assertSame('Bebas retak', $visualFinding->point_check);
        $this->get(route('findings.edit', $dimensionFinding))
            ->assertOk()
            ->assertSee(today()->format('d M Y'))
            ->assertSee('Shift 1')
            ->assertSee('Gedung 4M / Line 4M')
            ->assertSee('Produk 4M')
            ->assertSee('Type 4M')
            ->assertSee('diambil otomatis dari data inspeksi.');
        $this->put(route('findings.update', $dimensionFinding), $formInput)
            ->assertRedirect(route('findings.show', $dimensionFinding));
        $this->assertSame(today()->toDateString(), $dimensionFinding->fresh()->found_date->toDateString());
        $this->assertDatabaseHas('findings', [
            'id' => $dimensionFinding->id,
            'shift' => 'Shift 1',
            'area_line' => 'Gedung 4M / Line 4M',
            'part_name' => 'Produk 4M',
            'part_type' => 'Type 4M',
        ]);
        Storage::disk('local')->assertExists($dimensionFinding->problem_photo_path);
        Storage::disk('local')->assertExists($visualFinding->problem_photo_path);
        $this->get(route('findings.show', $dimensionFinding))->assertOk()->assertSee('Point check NG: Diameter');
        $this->get(route('inspection-reports.index'))->assertOk()->assertSee('4M lengkap');
    }

    public function test_inspection_rejects_missing_measurements(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $area = Area::create(['nama' => 'Area Uji']);
        $product = Product::create(['kode' => 'PRD-101', 'nama' => 'Produk Uji', 'area_id' => $area->id]);
        $dimension = DimensionStandard::create([
            'product_id' => $product->id,
            'parameter' => 'Lebar',
            'nilai_standar' => 20,
            'toleransi_min' => 0.5,
            'toleransi_max' => 0.5,
            'satuan' => 'mm',
        ]);
        $inspector = Inspector::create(['nama' => 'Petugas Uji', 'dept' => 'QC']);

        $response = $this->from('/inspections')->post('/inspections', [
            'tanggal' => '2026-10-09',
            'building_id' => $area->id,
            'product_id' => $product->id,
            'inspector_id' => $inspector->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 1,
            'visual_results' => ['manual' => 'OK'],
            'function_results' => ['manual' => 'OK'],
        ]);

        $response->assertRedirect('/inspections')
            ->assertSessionHasErrors(['dimensions.'.$dimension->id]);
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_ng_result_requires_a_photo_for_each_failed_standard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $area = Area::create(['nama' => 'Area Uji']);
        $product = Product::create(['kode' => 'PRD-102', 'nama' => 'Produk Uji', 'area_id' => $area->id]);
        $inspector = Inspector::create(['nama' => 'Petugas', 'dept' => 'QC', 'email' => 'petugas@example.com']);
        $visual = VisualStandard::create(['product_id' => $product->id, 'checklist' => 'Tidak retak']);

        $response = $this->from('/inspections')->post('/inspections', [
            'tanggal' => '2026-10-09',
            'building_id' => $area->id,
            'product_id' => $product->id,
            'inspector_id' => $inspector->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 1,
            'visual_results' => [$visual->id => 'NG'],
            'function_results' => ['manual' => 'OK'],
        ]);

        $response->assertRedirect('/inspections')
            ->assertSessionHasErrors(['evidence.visual.'.$visual->id]);
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_admin_can_add_point_check_image_when_creating_a_product(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $building = Area::create(['nama' => 'Gedung Gambar']);
        $line = Area::create(['nama' => 'Line Gambar', 'parent_id' => $building->id]);
        Storage::fake('local');

        $this->get('/master/products')
            ->assertOk()
            ->assertSee('Gambar visual point check produk');
        $this->get('/master/visual-standards')
            ->assertOk()
            ->assertDontSee('Gambar visualisasi point check');

        $this->post('/master/products', [
            'kode' => 'PRD-103',
            'nama' => 'Produk Gambar',
            'tipe' => 'Tipe Gambar',
            'area_id' => $line->id,
            'point_check_image' => UploadedFile::fake()->createWithContent(
                'point-check.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
                    .str_repeat('x', 6 * 1024 * 1024)
            ),
        ])->assertRedirect('/master/products');

        $product = Product::where('kode', 'PRD-103')->firstOrFail();
        $this->assertNotNull($product->image_path);
        Storage::disk('local')->assertExists($product->image_path);
        $this->assertGreaterThan(5 * 1024 * 1024, Storage::disk('local')->size($product->image_path));
        $this->get(route('products.point-check-image', $product))->assertOk();

        $oldImagePath = $product->image_path;
        $this->put(route('master.update', ['products', $product->id]), [
            'kode' => 'PRD-103',
            'nama' => 'Produk Gambar Diperbarui',
            'tipe' => 'Tipe Gambar',
            'area_id' => $line->id,
            'point_check_image' => UploadedFile::fake()->createWithContent(
                'point-check-baru.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
            ),
        ])->assertRedirect('/master/products');
        $product->refresh();
        $this->assertNotSame($oldImagePath, $product->image_path);
        Storage::disk('local')->assertMissing($oldImagePath);
        Storage::disk('local')->assertExists($product->image_path);

        $standard = VisualStandard::create([
            'product_id' => $product->id,
            'checklist' => 'Pastikan posisi pin sesuai',
        ]);

        $response = $this->get(route('inspections.index', ['building_id' => $building->id]));
        $response->assertOk()
            ->assertSee('Visualisasi point check produk')
            ->assertSee('Gunakan gambar ini sebagai acuan untuk seluruh pemeriksaan produk')
            ->assertSee('alt="Visual point check produk PRD-103"', false);
        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Dimensi part'),
            strpos($content, 'point-check-gallery'),
            'Point-check image gallery should appear before the dimension, visual, and function sections.'
        );
    }

    public function test_master_standards_can_be_filtered_by_registered_line_and_product_type(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $building = Area::create(['nama' => 'Gedung Master']);
        $line = Area::create(['nama' => 'Line Master', 'parent_id' => $building->id]);
        $otherLine = Area::create(['nama' => 'Line Lain', 'parent_id' => $building->id]);
        $product = Product::create([
            'kode' => 'PART-MASTER',
            'nama' => 'Part Master',
            'tipe' => 'Tipe Master',
            'area_id' => $line->id,
        ]);
        Product::create([
            'kode' => 'PART-LAIN',
            'nama' => 'Part Lain',
            'tipe' => 'Tipe Lain',
            'area_id' => $line->id,
        ]);
        Product::create([
            'kode' => 'PART-LINE-LAIN',
            'nama' => 'Part Line Lain',
            'tipe' => 'Tipe Master',
            'area_id' => $otherLine->id,
        ]);

        foreach (['dimension-standards', 'visual-standards', 'function-standards'] as $section) {
            $this->get(route('master.index', $section))
                ->assertOk()
                ->assertSee('Area / line terdaftar')
                ->assertSee('Tipe part')
                ->assertSee('Produk / part')
                ->assertSee('Gedung Master / Line Master')
                ->assertSee('Tipe Master');
        }

        $base = [
            'area_id' => (string) $line->id,
            'product_type' => 'Tipe Master',
            'product_id' => $product->id,
        ];

        $this->post('/master/dimension-standards', $base + [
            'parameter' => 'Jarak A',
            'nilai_standar' => 10,
            'toleransi_min' => 1,
            'toleransi_max' => 1,
            'satuan' => 'mm',
        ])->assertRedirect('/master/dimension-standards');
        $this->post('/master/visual-standards', $base + ['checklist' => 'Permukaan bersih'])
            ->assertRedirect('/master/visual-standards');
        $this->post('/master/function-standards', $base + ['checklist' => 'Berfungsi normal'])
            ->assertRedirect('/master/function-standards');

        $this->assertDatabaseHas('dimension_standards', ['product_id' => $product->id, 'parameter' => 'Jarak A']);
        $this->assertDatabaseHas('visual_standards', ['product_id' => $product->id, 'checklist' => 'Permukaan bersih']);
        $this->assertDatabaseHas('function_standards', ['product_id' => $product->id, 'checklist' => 'Berfungsi normal']);

        $this->from('/master/dimension-standards')->post('/master/dimension-standards', array_merge($base, [
            'area_id' => (string) $otherLine->id,
            'parameter' => 'Tidak valid',
            'nilai_standar' => 10,
            'toleransi_min' => 1,
            'toleransi_max' => 1,
            'satuan' => 'mm',
        ]))->assertRedirect('/master/dimension-standards')
            ->assertSessionHasErrors('product_id');
    }

    public function test_admin_can_create_building_folders_with_nested_lines(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/master/areas', ['nama' => 'Gedung Baru'])
            ->assertRedirect('/master/areas');
        $building = Area::where('nama', 'Gedung Baru')->firstOrFail();

        $this->post('/master/areas', [
            'nama' => 'Line Baru',
            'parent_id' => $building->id,
        ])->assertRedirect('/master/areas');

        $line = Area::where('nama', 'Line Baru')->firstOrFail();
        $this->assertSame($building->id, $line->parent_id);
    }

    public function test_dashboard_filters_and_shows_numeric_monitoring_charts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $typeA = Product::create(['kode' => 'PART-A', 'nama' => 'Part A', 'tipe' => 'Tipe A']);
        $typeB = Product::create(['kode' => 'PART-B', 'nama' => 'Part B', 'tipe' => 'Tipe B']);
        $dimension = DimensionStandard::create([
            'product_id' => $typeA->id,
            'parameter' => 'Lebar',
            'nilai_standar' => 10,
            'toleransi_min' => 1,
            'toleransi_max' => 1,
            'satuan' => 'mm',
        ]);
        foreach ([[$typeA, 'OK'], [$typeB, 'NG']] as [$product, $result]) {
            Inspection::create([
                'tanggal' => '2026-10-09',
                'product_id' => $product->id,
                'shift' => 'Shift 1',
                'sesi' => 'Start',
                'sample' => 1,
                'dimensi' => [
                    'hasil' => 'OK',
                    'detail' => $product->is($typeA) ? [[
                        'parameter' => $dimension->parameter,
                        'nilai' => 10,
                        'hasil' => 'OK',
                        'satuan' => 'mm',
                    ]] : [],
                ],
                'visual' => ['hasil' => 'OK', 'detail' => []],
                'fungsi' => ['hasil' => 'OK', 'detail' => []],
                'hasil_akhir' => $result,
            ]);
        }

        $this->get('/?product_type=Tipe%20A&from=2026-10-01&to=2026-10-10')
            ->assertOk()
            ->assertSee('Filter dashboard')
            ->assertSee('Filter X-Chart diterapkan otomatis saat pilihan diubah.')
            ->assertSee('xchartFilterForm.requestSubmit()', false)
            ->assertSee('Monitoring part / produk')
            ->assertSee('Monitoring tipe produk')
            ->assertSee('100.0%')
            ->assertSee('PART-A')
            ->assertSee('10.000')
            ->assertDontSee('<span class="monitor-label"><strong>PART-B</strong>', false);

        $this->get('/?from=2026-10-01&to=2026-10-10')
            ->assertOk()
            ->assertSee('PART-A')
            ->assertSee('<span class="monitor-label"><strong>PART-B</strong>', false)
            ->assertSee('50.0%');
    }

    public function test_dashboard_shows_dimensional_accuracy_and_filters_its_measurements(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = Product::create(['kode' => 'PART-DIM-ACC', 'nama' => 'Part Akurasi Dimensi']);
        DimensionStandard::create([
            'product_id' => $product->id,
            'parameter' => 'Lebar',
            'nilai_standar' => 10,
            'toleransi_min' => 1,
            'toleransi_max' => 1,
            'satuan' => 'mm',
        ]);

        foreach (['OK', 'OK', 'NG'] as $result) {
            Inspection::create([
                'tanggal' => '2026-10-09',
                'product_id' => $product->id,
                'shift' => 'Shift 1',
                'sesi' => 'Start',
                'sample' => 1,
                'dimensi' => [
                    'hasil' => $result,
                    'detail' => [[
                        'parameter' => 'Lebar',
                        'nilai' => $result === 'OK' ? 10 : 12,
                        'hasil' => $result,
                        'satuan' => 'mm',
                    ]],
                ],
                'visual' => ['hasil' => 'OK', 'detail' => []],
                'fungsi' => ['hasil' => 'OK', 'detail' => []],
                'hasil_akhir' => $result,
            ]);
        }

        $this->get('/?from=2026-10-01&to=2026-10-10')
            ->assertOk()
            ->assertSee('Akurasi dimensi part')
            ->assertSee('66.7%')
            ->assertSee('3 hasil ukur dimensi');

        $this->get('/?result=OK&from=2026-10-01&to=2026-10-10')
            ->assertOk()
            ->assertSee('100.0%')
            ->assertSee('2 hasil ukur dimensi');
    }

    public function test_x_chart_filter_with_area_and_product_type_does_not_break_dashboard_monitoring(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $building = Area::create(['nama' => 'Gedung Filter']);
        $area = Area::create(['nama' => 'Line Filter', 'parent_id' => $building->id]);
        $product = Product::create([
            'kode' => 'PART-FILTER',
            'nama' => 'Part Filter',
            'tipe' => 'Tipe Filter',
            'area_id' => $area->id,
        ]);
        $otherArea = Area::create(['nama' => 'Line Lain']);
        $otherProduct = Product::create([
            'kode' => 'PART-LAIN',
            'nama' => 'Part Lain',
            'tipe' => 'Tipe Lain',
            'area_id' => $otherArea->id,
        ]);
        DimensionStandard::create([
            'product_id' => $product->id,
            'parameter' => 'Thickness A',
            'nilai_standar' => 2,
            'toleransi_min' => 0.1,
            'toleransi_max' => 0.1,
            'satuan' => 'mm',
        ]);
        DimensionStandard::create([
            'product_id' => $otherProduct->id,
            'parameter' => 'Thickness B',
            'nilai_standar' => 3,
            'toleransi_min' => 0.1,
            'toleransi_max' => 0.1,
            'satuan' => 'mm',
        ]);
        Inspection::create([
            'tanggal' => '2026-10-09',
            'product_id' => $product->id,
            'area_id' => $area->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 1,
            'dimensi' => [
                'hasil' => 'OK',
                'detail' => [[
                    'parameter' => 'Thickness A',
                    'nilai' => 2,
                    'hasil' => 'OK',
                    'satuan' => 'mm',
                ]],
            ],
            'visual' => ['hasil' => 'OK', 'detail' => []],
            'fungsi' => ['hasil' => 'OK', 'detail' => []],
            'hasil_akhir' => 'OK',
        ]);

        $response = $this->get('/?product_id=&parameter=Thickness%20A&area_id='.$area->id.'&shift=Shift%201&from=2026-10-01&to=2026-10-10&product_type=Tipe%20Filter&result=')
            ->assertOk()
            ->assertSee('X-Chart')
            ->assertSee('Monitoring tipe produk')
            ->assertSee('Part Filter')
            ->assertSee('<option value="'.$product->id.'" data-area-id="'.$area->id.'" data-building-id="'.$building->id.'" data-product-type="Tipe Filter"', false)
            ->assertDontSee('<option value="'.$otherProduct->id.'" data-area-id="'.$otherArea->id.'"', false)
            ->assertSee('2.0000 mm');
        $xchartForm = substr(
            $response->getContent(),
            strpos($response->getContent(), '<form method="GET" action="http://localhost" class="xchart-filters"')
        );
        $xchartForm = substr($xchartForm, 0, strpos($xchartForm, '</form>'));
        $this->assertStringContainsString('<option value="Tipe Filter"', $xchartForm);
        $this->assertStringNotContainsString('<option value="Tipe Lain"', $xchartForm);

        $this->get('/?product_id='.$product->id.'&from=2026-10-01&to=2026-10-10')
            ->assertOk()
            ->assertSee('<option value="'.$building->id.'"', false)
            ->assertSee('<option value="'.$area->id.'"', false)
            ->assertSee('<option value="Tipe Filter"', false)
            ->assertSee('name="parameter" required', false)
            ->assertSee('<option value="Thickness A" selected', false);
    }

    public function test_x_chart_plots_daily_average_for_the_selected_dimension_parameter(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = Product::create(['kode' => 'PART-X', 'nama' => 'Part X', 'tipe' => 'Tipe X']);
        DimensionStandard::create([
            'product_id' => $product->id,
            'parameter' => 'Diameter',
            'nilai_standar' => 10,
            'toleransi_min' => 1,
            'toleransi_max' => 1,
            'satuan' => 'mm',
        ]);

        foreach ([
            ['2026-10-08', 8],
            ['2026-10-08', 12],
            ['2026-10-09', 12],
            ['2026-10-09', 14],
        ] as [$date, $value]) {
            Inspection::create([
                'tanggal' => $date,
                'product_id' => $product->id,
                'shift' => 'Shift 1',
                'sesi' => 'Start',
                'sample' => 1,
                'dimensi' => [
                    'hasil' => 'OK',
                    'detail' => [[
                        'parameter' => 'Diameter',
                        'nilai' => $value,
                        'hasil' => $value < 9 || $value > 11 ? 'NG' : 'OK',
                        'satuan' => 'mm',
                    ]],
                ],
                'visual' => ['hasil' => 'OK', 'detail' => []],
                'fungsi' => ['hasil' => 'OK', 'detail' => []],
                'hasil_akhir' => 'OK',
            ]);
        }

        $this->assertDatabaseCount('inspections', 4);
        $this->assertSame(4, Inspection::query()
            ->where('product_id', $product->id)
            ->whereDate('tanggal', '>=', '2026-10-08')
            ->whereDate('tanggal', '<=', '2026-10-09')
            ->count());

        $this->get('/?product_id='.$product->id.'&parameter=Diameter&from=2026-10-08&to=2026-10-09')
            ->assertOk()
            ->assertSee('Rata-rata harian nilai parameter terpilih')
            ->assertSee('2 hari')
            ->assertSee('10.000')
            ->assertSee('13.000')
            ->assertSee('1 hari')
            ->assertSee('rata-rata 10.0000 mm dari 2 pengukuran', false)
            ->assertSee('rata-rata 13.0000 mm dari 2 pengukuran', false);
    }

    public function test_inspection_report_can_be_printed_and_downloaded_as_pdf(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Storage::fake('local');
        Storage::disk('local')->put(
            'point-check/produk.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
        );
        $product = Product::create([
            'kode' => 'PRD-PDF',
            'nama' => 'Produk Laporan',
            'tipe' => 'Tipe PDF',
            'image_path' => 'point-check/produk.png',
        ]);
        VisualStandard::create([
            'product_id' => $product->id,
            'checklist' => 'Titik ukur A',
            'image_path' => 'point-check/produk.png',
        ]);
        $inspector = Inspector::create(['nama' => 'Inspector PDF', 'dept' => 'QC']);
        $startInspection = Inspection::create([
            'tanggal' => '2026-10-09',
            'product_id' => $product->id,
            'inspector_id' => $inspector->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 5,
            'dimensi' => ['hasil' => 'OK', 'detail' => [['parameter' => 'Lebar', 'nilai' => 10, 'min' => 9, 'max' => 11, 'satuan' => 'mm', 'hasil' => 'OK']]],
            'visual' => ['hasil' => 'OK', 'detail' => [['checklist' => 'Bebas goresan', 'hasil' => 'OK']]],
            'fungsi' => ['hasil' => 'OK', 'detail' => [['checklist' => 'Berfungsi', 'hasil' => 'OK']]],
            'hasil_akhir' => 'OK',
        ]);
        foreach (['Middle', 'End'] as $session) {
            Inspection::create([
                'tanggal' => '2026-10-09',
                'product_id' => $product->id,
                'inspector_id' => $inspector->id,
                'shift' => 'Shift 1',
                'sesi' => $session,
                'sample' => 5,
                'dimensi' => ['hasil' => 'OK', 'detail' => [['parameter' => 'Lebar', 'nilai' => 10, 'min' => 9, 'max' => 11, 'satuan' => 'mm', 'hasil' => 'OK']]],
                'visual' => ['hasil' => 'OK', 'detail' => [['checklist' => 'Bebas goresan', 'hasil' => 'OK']]],
                'fungsi' => ['hasil' => 'OK', 'detail' => [['checklist' => 'Berfungsi', 'hasil' => 'OK']]],
                'hasil_akhir' => 'OK',
            ]);
        }

        $reportResponse = $this->get('/inspections/report?product_type=Tipe%20PDF&from=2026-10-01&to=2026-10-10')
            ->assertOk()
            ->assertSee('Laporan hasil inspeksi')
            ->assertSee(route('inspection-reports.index'), false)
            ->assertSee('Filter laporan')
            ->assertSee('Unduh PDF')
            ->assertSee('Inspector PDF')
            ->assertSee('Lebar')
            ->assertSee('Tipe part:')
            ->assertSee('Titik ukur A')
            ->assertSee('Start')
            ->assertSee('Middle')
            ->assertSee('End')
            ->assertSee('1 laporan per tanggal, shift, dan part')
            ->assertSee('data:image/png;base64,', false);
        $reportContent = $reportResponse->getContent();
        $this->assertLessThan(
            strpos($reportContent, 'Nilai / Hasil'),
            strpos($reportContent, 'Batas / Standar'),
            'The report must show the standard before the measured value.'
        );
        $this->assertLessThan(
            strpos($reportContent, 'Judgment'),
            strpos($reportContent, 'Nilai / Hasil'),
            'The report must show the measured value before its judgment.'
        );

        $response = $this->get('/inspections/report.pdf?product_type=Tipe%20PDF&from=2026-10-01&to=2026-10-10');
        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $this->get(route('inspection-reports.index'))
            ->assertOk()
            ->assertSee('Riwayat inspeksi')
            ->assertSee(route('inspection-reports.pdf'), false);

        $this->get(route('inspection-reports.pdf', [
            'product_type' => 'Tipe PDF',
            'from' => '2026-10-01',
            'to' => '2026-10-10',
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->get('/inspections')
            ->assertOk()
            ->assertSee(route('inspection-reports.index'), false)
            ->assertDontSee(route('inspections.report.single', Inspection::firstOrFail()).'?print=1', false)
            ->assertDontSee(route('inspections.report.single.pdf', Inspection::firstOrFail()), false);

        $singleReport = $this->get(route('inspections.report.single', Inspection::firstOrFail()).'?print=1');
        $singleReport->assertOk()->assertSee('window.print()', false)->assertSee('Middle')->assertSee('End');
        $singlePdf = $this->get(route('inspections.report.single.pdf', $startInspection));
        $singlePdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $singlePdf->getContent());
    }

    public function test_viewer_can_only_view_and_admin_can_create_role_limited_accounts(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);
        $this->get('/')->assertOk();
        $this->get('/inspections')->assertOk();
        $this->get('/master/accounts')->assertForbidden();
        $this->post('/inspections', [])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->post('/master/accounts', [
            'name' => 'Operator Line',
            'email' => 'operator@example.test',
            'password' => 'password-aman-123',
            'password_confirmation' => 'password-aman-123',
            'role' => 'inspector',
        ])->assertRedirect('/master/accounts');
        $this->assertDatabaseHas('users', [
            'email' => 'operator@example.test',
            'role' => 'inspector',
        ]);
    }

    public function test_inspector_results_and_inspector_name_are_bound_to_logged_in_account(): void
    {
        $inspectorUser = User::factory()->create([
            'name' => 'Inspektor Satu',
            'email' => 'inspektor.satu@example.test',
            'role' => 'inspector',
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Inspektor Dua',
            'email' => 'inspektor.dua@example.test',
            'role' => 'inspector',
        ]);
        $building = Area::create(['nama' => 'Gedung Inspektor']);
        $line = Area::create(['nama' => 'Line Inspektor', 'parent_id' => $building->id]);
        $product = Product::create(['kode' => 'PRD-OWN', 'nama' => 'Produk Inspector', 'area_id' => $line->id]);
        $otherProduct = Product::create(['kode' => 'PRD-OTHER', 'nama' => 'Produk Inspector Lain']);
        $otherInspector = Inspector::create([
            'nama' => $otherUser->name,
            'email' => $otherUser->email,
        ]);

        $this->actingAs($inspectorUser)
            ->get(route('inspections.index', ['building_id' => $building->id]))
            ->assertOk()
            ->assertSee('value="Inspektor Satu"', false)
            ->assertDontSee('name="inspector_id"', false);

        $this->post(route('inspections.store'), [
            'building_id' => $building->id,
            'tanggal' => today()->toDateString(),
            'product_id' => $product->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 1,
            'measurement_mode' => 'manual',
            'visual_results' => ['manual' => 'OK'],
            'function_results' => ['manual' => 'OK'],
        ])->assertRedirect(route('inspections.index'));

        $ownedInspection = Inspection::where('user_id', $inspectorUser->id)->firstOrFail();
        $this->assertSame('Inspektor Satu', $ownedInspection->inspectorName);
        $this->assertDatabaseHas('inspectors', [
            'id' => $ownedInspection->inspector_id,
            'nama' => $inspectorUser->name,
            'email' => $inspectorUser->email,
        ]);

        $otherInspection = Inspection::create([
            'tanggal' => today(),
            'product_id' => $otherProduct->id,
            'inspector_id' => $otherInspector->id,
            'user_id' => $otherUser->id,
            'shift' => 'Shift 1',
            'sesi' => 'Start',
            'sample' => 1,
            'dimensi' => ['detail' => []],
            'visual' => ['detail' => []],
            'fungsi' => ['detail' => []],
            'hasil_akhir' => 'OK',
        ]);

        $this->get(route('inspections.index'))
            ->assertOk()
            ->assertDontSee('Riwayat inspeksi');
        $this->get(route('inspection-reports.index'))
            ->assertOk()
            ->assertSee('1 catatan ditemukan')
            ->assertSee('PRD-OWN')
            ->assertSee('Inspektor Satu')
            ->assertDontSee('Inspektor Dua');
        $this->get(route('inspections.report.single', $otherInspection))->assertNotFound();
        $this->get(route('inspections.report'))->assertOk()->assertDontSee('PRD-OTHER');
        $xlsx = $this->get(route('inspection-reports.export'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $xlsx->baseResponse->headers->get('content-disposition'));
        $xlsxPath = $xlsx->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertSame(true, $zip->open($xlsxPath));
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('PRD-OWN', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($xlsxPath);

        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)
            ->get(route('inspection-reports.index'))
            ->assertOk()
            ->assertSee('0 catatan ditemukan')
            ->assertDontSee('Inspektor Satu')
            ->assertDontSee('Inspektor Dua');
        $this->get(route('inspections.report.single', $ownedInspection))->assertNotFound();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('inspection-reports.index'))
            ->assertOk()
            ->assertSee('2 catatan ditemukan')
            ->assertSee('Inspektor Satu')
            ->assertSee('Inspektor Dua');
    }
}
