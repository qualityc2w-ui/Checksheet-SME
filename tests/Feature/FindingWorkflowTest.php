<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\FindingPic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class FindingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_finding_module_uses_existing_login_and_role_permissions(): void
    {
        $this->get(route('findings.index'))->assertRedirect('/login');

        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->get(route('findings.index'))->assertOk();
        $this->get(route('findings.dashboard'))->assertOk();
        $this->get(route('findings.create'))->assertForbidden();
        $this->get(route('findings.settings'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'inspector']))
            ->get(route('findings.create'))->assertOk();
    }

    public function test_inspector_can_create_finding_with_private_photo_and_notify_pic(): void
    {
        Storage::fake('local');
        $inspector = User::factory()->create(['role' => 'inspector']);
        $pic = FindingPic::create(['name' => 'PIC Uji', 'department' => 'Produksi', 'email' => 'pic@example.com']);

        $response = $this->actingAs($inspector)->post(route('findings.store'), [
            ...$this->validFindingInput($pic),
            'problem_photo' => UploadedFile::fake()->createWithContent(
                'temuan.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
            ),
        ]);

        $finding = Finding::firstOrFail();
        $response->assertRedirect(route('findings.show', $finding));
        $this->assertSame('TMN-'.str_pad((string) $finding->id, 6, '0', STR_PAD_LEFT), $finding->code);
        $this->assertSame($inspector->id, $finding->reporter_id);
        $this->assertSame('open', $finding->status);
        $this->assertNotNull($finding->problem_photo_path);
        Storage::disk('local')->assertExists($finding->problem_photo_path);
        $this->get(route('findings.show', $finding))->assertOk()->assertSee($finding->code);
        Mail::assertSent(\App\Mail\FindingPicNotification::class, fn ($mail) => $mail->hasTo('pic@example.com'));
        $this->assertNotNull($finding->fresh()->email_sent_at);
        $this->post(route('findings.email', $finding))->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(\App\Mail\FindingPicNotification::class, 2);
        $this->assertNotNull($finding->fresh()->email_sent_at);
    }

    public function test_pic_can_submit_plan_and_completion_and_reporter_can_verify(): void
    {
        $reporter = User::factory()->create(['role' => 'inspector']);
        $finding = $this->createFinding($reporter);
        Storage::fake('local');

        $responseUrl = route('findings.respond', $finding->response_token);
        $this->get($responseUrl)->assertOk()->assertSee('Rencana perbaikan');
        $this->post(route('findings.respond.plan', $finding->response_token), [
            'due_date' => today()->addDays(3)->toDateString(),
            'pic_plan' => 'Ganti komponen dan periksa ulang.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'status' => 'scheduled']);

        $this->post(route('findings.respond.completion', $finding->response_token), [
            'completion_note' => 'Komponen sudah diganti dan hasil pengecekan normal.',
            'completion_photo' => UploadedFile::fake()->createWithContent(
                'selesai.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
            ),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $finding->refresh();
        $this->assertSame('done', $finding->status);
        Storage::disk('local')->assertExists($finding->completion_photo_path);
        $this->get(route('findings.respond.photo', [$finding->response_token, 'completion']))->assertOk();

        $this->actingAs($reporter)->post(route('findings.verify', $finding), [
            'decision' => 'verified',
            'verification_note' => 'Perbaikan sudah sesuai.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'status' => 'verified']);
    }

    public function test_only_admin_can_manage_finding_options_and_pic_records(): void
    {
        $inspector = User::factory()->create(['role' => 'inspector']);
        $pic = FindingPic::create(['name' => 'PIC Lama', 'email' => 'lama@example.com']);
        $this->actingAs($inspector)
            ->post(route('findings.pics.store'), ['name' => 'PIC Baru', 'email' => 'baru@example.com'])
            ->assertForbidden();
        $this->assertDatabaseCount('finding_pics', 1);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('findings.settings'))
            ->assertOk()->assertSee('Tambah PIC');
        $this->post(route('findings.pics.store'), ['name' => 'PIC Baru', 'email' => 'baru@example.com'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('findings.options.store'), ['kind' => 'problem_type', 'value' => 'Kebocoran'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('finding_lookup_options', ['kind' => 'problem_type', 'value' => 'Kebocoran']);
        $this->assertDatabaseHas('finding_pics', ['name' => 'PIC Baru']);
        $this->delete(route('findings.pics.destroy', $pic))->assertRedirect();
    }

    public function test_findings_dashboard_and_register_apply_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pic = FindingPic::create(['name' => 'PIC Filter', 'email' => 'filter@example.com']);
        $first = $this->createFinding($admin, ['part_name' => 'Part Filter A', 'status' => 'open', 'category' => 'machine', 'pic_id' => $pic->id]);
        $this->createFinding($admin, ['part_name' => 'Part Filter B', 'status' => 'verified', 'category' => 'material', 'pic_id' => $pic->id]);

        $this->actingAs($admin)->get(route('findings.dashboard', ['status' => 'open', 'category' => 'machine']))
            ->assertOk()->assertSee($first->part_name)->assertDontSee('Part Filter B')->assertSee('Status temuan');
        $this->get(route('findings.index', ['q' => 'Part Filter B']))
            ->assertOk()->assertSee('Part Filter B')->assertDontSee('Part Filter A');
        $export = $this->get(route('findings.export', ['category' => 'machine']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();
        ob_start();
        $export->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Part Filter A', $csv);
        $this->assertStringNotContainsString('Part Filter B', $csv);
    }

    public function test_admin_can_edit_a_finding_but_inspector_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $finding = $this->createFinding($admin);
        $this->actingAs(User::factory()->create(['role' => 'inspector']))
            ->get(route('findings.edit', $finding))->assertForbidden();

        $this->actingAs($admin)->get(route('findings.edit', $finding))
            ->assertOk()->assertSee('Edit temuan');
        $this->put(route('findings.update', $finding), [
            ...$this->validFindingInput($finding->pic),
            'part_name' => 'Cover Cylinder Head',
        ])->assertRedirect(route('findings.show', $finding))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'part_name' => 'Cover Cylinder Head']);
    }

    private function validFindingInput(FindingPic $pic): array
    {
        return [
            'found_date' => today()->toDateString(),
            'shift' => 'Shift 1',
            'area_line' => 'Line 1',
            'part_name' => 'Cover Cylinder Head',
            'part_type' => 'CCH-250A',
            'problem_type' => 'Dimensi tidak sesuai',
            'description' => 'Diameter ditemukan di luar batas.',
            'category' => 'machine',
            'priority' => 'high',
            'analysis_man' => 'Operator mengikuti instruksi.',
            'analysis_method' => 'Prosedur perlu ditinjau.',
            'analysis_machine' => 'Mesin mengalami keausan.',
            'analysis_material' => 'Material sesuai spesifikasi.',
            'root_cause' => 'Keausan alat menyebabkan ukuran berubah.',
            'pic_id' => $pic->id,
        ];
    }

    private function createFinding(User $reporter, array $overrides = []): Finding
    {
        $picId = $overrides['pic_id'] ?? FindingPic::create([
            'name' => 'PIC Temuan',
            'email' => 'pic-temuan@example.com',
        ])->id;

        return Finding::create([
            'code' => 'TMN-'.Str::upper(Str::random(8)),
            'title' => $overrides['part_name'] ?? 'Part Uji',
            'part_name' => $overrides['part_name'] ?? 'Part Uji',
            'part_type' => 'Tipe Uji',
            'found_date' => today(),
            'shift' => 'Shift 1',
            'area_line' => 'Line 1',
            'problem_type' => 'Dimensi tidak sesuai',
            'description' => '',
            'category' => $overrides['category'] ?? 'man',
            'priority' => 'medium',
            'analysis_man' => '',
            'analysis_method' => '',
            'analysis_machine' => '',
            'analysis_material' => '',
            'root_cause' => 'Akar masalah pengujian.',
            'pic_id' => $picId,
            'status' => $overrides['status'] ?? 'open',
            'response_token' => Str::random(64),
            'reporter_id' => $reporter->id,
            'reporter_name' => $reporter->name,
        ]);
    }
}
