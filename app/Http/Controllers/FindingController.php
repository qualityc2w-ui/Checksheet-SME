<?php

namespace App\Http\Controllers;

use App\Mail\FindingPicNotification;
use App\Models\Finding;
use App\Models\FindingLookupOption;
use App\Models\FindingPic;
use App\Models\Inspection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FindingController extends Controller
{
    private const LOOKUP_LABELS = [
        'area_line' => 'Area / Line',
        'part_name' => 'Nama part',
        'part_type' => 'Tipe part',
        'problem_type' => 'Jenis masalah',
    ];

    public function dashboard(Request $request): View
    {
        $findings = $this->filteredFindings($request)->with('pic')->get();
        $total = $findings->count();
        $statusCounts = collect(Finding::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => $findings->where('status', $status)->count()]);
        $categoryCounts = collect(Finding::CATEGORIES)
            ->mapWithKeys(fn (string $label, string $category) => [$category => $findings->where('category', $category)->count()]);
        $partCounts = $findings->groupBy(fn (Finding $finding) => $finding->part_name.' · '.$finding->part_type)
            ->map(fn ($items, $label) => ['label' => $label, 'count' => $items->count()])
            ->sortByDesc('count')
            ->take(12)
            ->values();
        $picCounts = $findings->groupBy(fn (Finding $finding) => $finding->pic?->name ?? 'Belum ada PIC')
            ->map(fn ($items, $name) => [
                'name' => $name,
                'open' => $items->whereIn('status', ['open', 'scheduled', 'rejected'])->count(),
                'late' => $items->filter(fn (Finding $finding) => $finding->isOverdue())->count(),
            ])
            ->sortByDesc(fn (array $item) => $item['open'] + $item['late'])
            ->take(10)
            ->values();
        $upcoming = $findings->filter(fn (Finding $finding) => $finding->due_date
            && in_array($finding->status, ['open', 'scheduled', 'rejected'], true)
            && $finding->due_date->greaterThanOrEqualTo(today()))
            ->sortBy('due_date')
            ->take(6)
            ->values();

        return view('findings.dashboard', [
            'findings' => $findings,
            'total' => $total,
            'statusCounts' => $statusCounts,
            'categoryCounts' => $categoryCounts,
            'partCounts' => $partCounts,
            'picCounts' => $picCounts,
            'upcoming' => $upcoming,
            'overdue' => $findings->filter(fn (Finding $finding) => $finding->isOverdue())->count(),
            'closeRate' => $total ? round($statusCounts['verified'] / $total * 100) : 0,
            'filters' => $this->filterOptions(),
        ]);
    }

    public function index(Request $request): View
    {
        return view('findings.index', [
            'findings' => $this->filteredFindings($request)->with('pic')->paginate(20)->withQueryString(),
            'filters' => $this->filterOptions(),
        ]);
    }

    public function export(Request $request)
    {
        $findings = $this->filteredFindings($request)->with('pic')->get();
        $filename = 'temuan-qc-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($findings): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Gagal membuka file ekspor temuan.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Kode', 'Tanggal Temuan', 'Shift', 'Area / Line', 'Nama Part', 'Tipe Part',
                'Jenis Masalah', 'Kategori 4M', 'Prioritas', 'Deskripsi', 'Akar Masalah',
                'PIC', 'Tanggal Target', 'Rencana Perbaikan', 'Status', 'Reporter',
            ]);
            foreach ($findings as $finding) {
                $row = [
                    $finding->code,
                    $finding->found_date->format('Y-m-d'),
                    $finding->shift,
                    $finding->area_line,
                    $finding->part_name,
                    $finding->part_type,
                    $finding->problem_type,
                    Finding::CATEGORIES[$finding->category] ?? $finding->category,
                    Finding::PRIORITIES[$finding->priority] ?? $finding->priority,
                    $finding->description,
                    $finding->root_cause,
                    $finding->pic?->name,
                    $finding->due_date?->format('Y-m-d'),
                    $finding->pic_plan,
                    Finding::STATUS_LABELS[$finding->status] ?? $finding->status,
                    $finding->reporter_name,
                ];
                foreach ($row as &$cell) {
                    if (is_string($cell) && preg_match('/^[\s]*[=+\-@]/', $cell)) {
                        $cell = "'".$cell;
                    }
                }
                unset($cell);
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->canWriteInspections(), 403);

        return view('findings.form', [
            'finding' => new Finding([
                'found_date' => today(),
                'shift' => 'Shift 1',
                'category' => 'man',
                'priority' => 'medium',
            ]),
            'editing' => false,
            'source' => null,
            'inspectionSource' => null,
            ...$this->formOptions(),
        ]);
    }

    public function resumeInspection(Inspection $inspection): RedirectResponse
    {
        abort_unless(auth()->user()->canWriteInspections(), 403);
        $this->authorizeInspectionAccess($inspection);
        $pending = $inspection->pendingNgPointChecks();
        if ($pending === []) {
            return redirect()->route('inspections.index')
                ->with('success', 'Semua point check NG pada inspeksi ini sudah memiliki tindak lanjut 4M.');
        }

        return redirect()->route('findings.inspection.create', [
            $inspection,
            $pending[0]['section'],
            $pending[0]['index'],
        ]);
    }

    public function createFromInspection(Inspection $inspection, string $section, int $item): View|RedirectResponse
    {
        abort_unless(auth()->user()->canWriteInspections(), 403);
        $this->authorizeInspectionAccess($inspection);
        $point = $this->inspectionPoint($inspection, $section, $item);
        $existing = Finding::where('inspection_id', $inspection->id)
            ->where('inspection_section', $section)
            ->where('inspection_item_index', $item)
            ->first();
        if ($existing) {
            return redirect()->route('findings.show', $existing)
                ->with('warning', 'Point check NG ini sudah memiliki tindak lanjut 4M.');
        }

        $inspection->load(['inspector', 'findings']);
        $description = $this->inspectionPointDescription($point);
        $inspectionAttributes = $this->inspectionFindingAttributes($inspection);
        $finding = new Finding([
            ...$inspectionAttributes,
            'problem_type' => $point['problem_type'],
            'description' => $description,
            'priority' => 'medium',
            'category' => 'man',
        ]);
        $options = $this->formOptions();
        $options['source'] = [
            'inspection' => $inspection,
            'point' => $point,
            'evidence_url' => route('findings.inspection.photo', [$inspection, $section, $item]),
        ];

        return view('findings.form', [
            'finding' => $finding,
            'editing' => false,
            'inspectionSource' => null,
            ...$options,
        ]);
    }

    public function storeFromInspection(Request $request, Inspection $inspection, string $section, int $item): RedirectResponse
    {
        abort_unless(auth()->user()->canWriteInspections(), 403);
        $this->authorizeInspectionAccess($inspection);
        $point = $this->inspectionPoint($inspection, $section, $item);
        $data = $request->validate([
            'problem_type' => ['required', 'string', 'max:150', Rule::exists('finding_lookup_options', 'value')
                ->where('kind', 'problem_type')->where('active', true)],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(Finding::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Finding::PRIORITIES))],
            'analysis_man' => ['required', 'string', 'max:2000'],
            'analysis_method' => ['required', 'string', 'max:2000'],
            'analysis_machine' => ['required', 'string', 'max:2000'],
            'analysis_material' => ['required', 'string', 'max:2000'],
            'root_cause' => ['required', 'string', 'min:3', 'max:2000'],
            'pic_id' => ['required', 'integer', Rule::exists('finding_pics', 'id')->where('active', true)->whereNotNull('email')],
        ]);
        $inspection->load(['inspector', 'findings']);
        $inspectionAttributes = $this->inspectionFindingAttributes($inspection);
        $generatedDescription = $this->inspectionPointDescription($point);
        $description = trim((string) ($data['description'] ?? ''));
        if ($description !== '' && $description !== $generatedDescription) {
            $generatedDescription .= "\nCatatan tambahan: ".$description;
        }
        $copiedEvidence = null;

        try {
            $finding = DB::transaction(function () use (
                $inspection,
                $inspectionAttributes,
                $point,
                $data,
                $description,
                &$copiedEvidence,
                $section,
                $item
            ): Finding {
                $finding = Finding::create([
                    'code' => null,
                    'title' => $inspectionAttributes['part_name'],
                    ...$inspectionAttributes,
                    'problem_type' => $data['problem_type'],
                    'description' => $description,
                    'category' => $data['category'],
                    'priority' => $data['priority'],
                    'analysis_man' => $data['analysis_man'],
                    'analysis_method' => $data['analysis_method'],
                    'analysis_machine' => $data['analysis_machine'],
                    'analysis_material' => $data['analysis_material'],
                    'root_cause' => $data['root_cause'],
                    'pic_id' => $data['pic_id'],
                    'status' => 'open',
                    'response_token' => Str::random(64),
                    'reporter_id' => auth()->id(),
                    'reporter_name' => auth()->user()->name,
                    'inspection_id' => $inspection->id,
                    'inspection_section' => $section,
                    'inspection_item_index' => $item,
                    'point_check' => $point['label'],
                    'observed_value' => $point['observed_value'],
                    'standard_value' => $point['standard_value'],
                    'minimum_value' => $point['minimum_value'],
                    'maximum_value' => $point['maximum_value'],
                    'unit' => $point['unit'],
                ]);
                $finding->update(['code' => 'TMN-'.str_pad((string) $finding->id, 6, '0', STR_PAD_LEFT)]);

                if ($point['evidence_path']) {
                    $extension = pathinfo($point['evidence_path'], PATHINFO_EXTENSION);
                    $copiedEvidence = 'findings/'.$finding->id.'/inspection-evidence.'.$extension;
                    if (! Storage::disk('local')->copy($point['evidence_path'], $copiedEvidence)) {
                        throw new \RuntimeException('Foto bukti inspeksi gagal dilampirkan pada temuan 4M.');
                    }
                    $finding->update(['problem_photo_path' => $copiedEvidence]);
                }

                return $finding;
            });
        } catch (\Throwable $exception) {
            if ($copiedEvidence && ! Storage::disk('local')->delete($copiedEvidence)) {
                report(new \RuntimeException('Foto bukti inspeksi tidak dapat dibersihkan setelah penyimpanan 4M gagal.'));
            }
            throw $exception;
        }

        $pending = $inspection->fresh()->pendingNgPointChecks();
        $next = $pending[0] ?? null;
        $response = $next
            ? redirect()->route('findings.inspection.create', [$inspection, $next['section'], $next['index']])
            : redirect()->route('findings.show', $finding)->with('success', 'Semua point check NG pada inspeksi ini sudah dibuatkan tindak lanjut 4M.');

        try {
            $this->deliverFindingEmail($finding->fresh()->load('pic'));

            return $response->with('success', 'Temuan 4M berhasil dibuat dan email tindak lanjut dikirim kepada PIC.');
        } catch (\Throwable $exception) {
            Log::error('Unable to email finding notification to PIC.', [
                'finding_id' => $finding->id,
                'recipient' => $finding->pic?->email,
                'exception' => $exception,
            ]);

            return $response->with('warning', 'Temuan 4M berhasil dibuat, tetapi email PIC gagal dikirim. Periksa konfigurasi SMTP lalu kirim ulang dari detail temuan.');
        }
    }

    public function inspectionPhoto(Inspection $inspection, string $section, int $item)
    {
        abort_unless(auth()->user()->canWriteInspections(), 403);
        $this->authorizeInspectionAccess($inspection);
        $point = $this->inspectionPoint($inspection, $section, $item);
        abort_unless($point['evidence_path'] && Storage::disk('local')->exists($point['evidence_path']), 404);

        return response()->file(Storage::disk('local')->path($point['evidence_path']), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function inspectionPoint(Inspection $inspection, string $section, int $item): array
    {
        abort_unless(in_array($section, ['dimensions', 'visual', 'function'], true), 404);
        $inspection->loadMissing('product');
        $point = collect($inspection->ngPointChecks())
            ->first(fn (array $candidate) => $candidate['section'] === $section && $candidate['index'] === $item);
        abort_unless($point, 404, 'Point check ini tidak berstatus NG pada inspeksi tersebut.');

        return $point;
    }

    private function inspectionPointDescription(array $point): string
    {
        $description = 'Point check NG: '.$point['label']."\nHasil ukur: ".$point['observed_value'];
        if ($point['unit']) {
            $description .= ' '.$point['unit'];
        }
        if ($point['standard_value'] !== null) {
            $description .= "\nStandar: ".$point['standard_value'].($point['unit'] ? ' '.$point['unit'] : '');
        }
        if ($point['minimum_value'] !== null || $point['maximum_value'] !== null) {
            $description .= "\nBatas: ".($point['minimum_value'] ?? '—').' sampai '.($point['maximum_value'] ?? '—');
            if ($point['unit']) {
                $description .= ' '.$point['unit'];
            }
        }

        return $description;
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canWriteInspections(), 403);
        $data = $this->validatedFinding($request);
        $photo = $request->file('problem_photo');
        $path = null;

        try {
            $finding = DB::transaction(function () use ($data, $photo, &$path): Finding {
                $finding = Finding::create([
                    ...$data,
                    'title' => $data['part_name'],
                    'code' => null,
                    'response_token' => Str::random(64),
                    'status' => 'open',
                    'reporter_id' => auth()->id(),
                    'reporter_name' => auth()->user()->name,
                ]);
                $finding->update(['code' => 'TMN-'.str_pad((string) $finding->id, 6, '0', STR_PAD_LEFT)]);
                if ($photo) {
                    $path = $photo->store('findings/'.$finding->id, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Foto bukti masalah gagal disimpan.');
                    }
                    $finding->update(['problem_photo_path' => $path]);
                }

                return $finding;
            });
        } catch (\Throwable $exception) {
            if ($path && ! Storage::disk('local')->delete($path)) {
                report(new \RuntimeException('Gagal membersihkan foto temuan setelah penyimpanan dibatalkan.'));
            }
            throw $exception;
        }

        $response = redirect()->route('findings.show', $finding);
        try {
            $this->deliverFindingEmail($finding->fresh()->load('pic'));

            return $response->with('success', 'Temuan berhasil dicatat dan email tindak lanjut dikirim kepada PIC.');
        } catch (\Throwable $exception) {
            Log::error('Unable to email finding notification to PIC.', [
                'finding_id' => $finding->id,
                'recipient' => $finding->pic?->email,
                'exception' => $exception,
            ]);

            return $response->with('warning', 'Temuan berhasil dicatat, tetapi email PIC gagal dikirim. Periksa konfigurasi SMTP lalu coba kirim ulang dari detail temuan.');
        }
    }

    public function show(Finding $finding): View
    {
        $finding->load('pic');

        return view('findings.show', ['finding' => $finding]);
    }

    public function edit(Finding $finding): View
    {
        $this->authorizeAdmin();
        $inspection = $finding->inspection;
        if ($inspection) {
            $inspectionAttributes = $this->inspectionFindingAttributes($inspection);
            $finding->forceFill($inspectionAttributes);
        }

        return view('findings.form', [
            'finding' => $finding,
            'editing' => true,
            'source' => null,
            'inspectionSource' => $inspection,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Finding $finding): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $this->validatedFinding($request, $finding);
        $photo = $request->file('problem_photo');
        $oldPath = $finding->problem_photo_path;

        if ($photo) {
            $path = $photo->store('findings/'.$finding->id, 'local');
            if ($path === false) {
                throw new \RuntimeException('Foto bukti masalah gagal disimpan.');
            }
            $data['problem_photo_path'] = $path;
        }
        $data['title'] = $data['part_name'];

        try {
            $finding->update($data);
        } catch (\Throwable $exception) {
            if (isset($path) && ! Storage::disk('local')->delete($path)) {
                report(new \RuntimeException('Gagal membersihkan foto temuan setelah perubahan dibatalkan.'));
            }
            throw $exception;
        }
        if (isset($path) && $oldPath && ! Storage::disk('local')->delete($oldPath)) {
            report(new \RuntimeException('Foto bukti lama tidak dapat dihapus.'));
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Temuan berhasil diperbarui.');
    }

    public function destroy(Finding $finding): RedirectResponse
    {
        $this->authorizeAdmin();
        $paths = array_filter([$finding->problem_photo_path, $finding->completion_photo_path]);
        $finding->delete();
        foreach ($paths as $path) {
            if (! Storage::disk('local')->delete($path)) {
                report(new \RuntimeException('Berkas foto temuan tidak dapat dihapus.'));
            }
        }

        return redirect()->route('findings.index')->with('success', 'Temuan berhasil dihapus.');
    }

    public function sendEmail(Finding $finding): RedirectResponse
    {
        abort_unless(auth()->user()->canWriteInspections(), 403);
        $finding->load('pic');
        if (! filter_var($finding->pic?->email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => 'Alamat email PIC belum tersedia atau tidak valid.']);
        }

        try {
            $this->deliverFindingEmail($finding);
        } catch (\Throwable $exception) {
            Log::error('Unable to send finding notification email.', [
                'finding_id' => $finding->id,
                'recipient' => $finding->pic->email,
                'exception' => $exception,
            ]);

            return back()->withErrors(['email' => 'Email tindak lanjut gagal dikirim. Periksa konfigurasi SMTP lalu coba lagi.']);
        }

        return back()->with('success', 'Email tindak lanjut berhasil dikirim ke '.$finding->pic->email.'.');
    }

    public function verify(Request $request, Finding $finding): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin() || $finding->reporter_id === auth()->id(), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'rejected'])],
            'verification_note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        abort_unless($finding->status === 'done', 422, 'Hanya perbaikan yang selesai dapat diverifikasi.');
        $finding->update([
            'status' => $data['decision'],
            'verified_at' => $data['decision'] === 'verified' ? now() : null,
            'verification_note' => $data['verification_note'],
        ]);

        return back()->with('success', $data['decision'] === 'verified'
            ? 'Perbaikan berhasil diverifikasi.'
            : 'Perbaikan dikembalikan kepada PIC untuk ditindaklanjuti.');
    }

    public function respond(string $token): View
    {
        return view('findings.respond', ['finding' => $this->findingByToken($token)]);
    }

    public function submitPlan(Request $request, string $token): RedirectResponse
    {
        $finding = $this->findingByToken($token);
        abort_unless(in_array($finding->status, ['open', 'scheduled', 'rejected'], true), 422, 'Temuan sudah diselesaikan.');
        $data = $request->validate([
            'due_date' => ['required', 'date'],
            'pic_plan' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $oldCompletionPhoto = $finding->completion_photo_path;
        $finding->update([
            ...$data,
            'status' => 'scheduled',
            'responded_at' => now(),
            'completion_note' => '',
            'completion_photo_path' => null,
            'completed_at' => null,
            'verified_at' => null,
        ]);
        if ($oldCompletionPhoto && ! Storage::disk('local')->delete($oldCompletionPhoto)) {
            report(new \RuntimeException('Foto penyelesaian lama tidak dapat dihapus.'));
        }

        return back()->with('success', 'Rencana perbaikan berhasil disimpan.');
    }

    public function submitCompletion(Request $request, string $token): RedirectResponse
    {
        $finding = $this->findingByToken($token);
        abort_unless($finding->status === 'scheduled', 422, 'Isi rencana dan tanggal target sebelum melaporkan penyelesaian.');
        $data = $request->validate([
            'completion_note' => ['required', 'string', 'min:3', 'max:2000'],
            'completion_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $path = $request->file('completion_photo')?->store('findings/'.$finding->id, 'local');
        if ($request->hasFile('completion_photo') && $path === false) {
            throw new \RuntimeException('Foto penyelesaian gagal disimpan.');
        }
        $oldPath = $finding->completion_photo_path;
        try {
            $finding->update([
                'completion_note' => $data['completion_note'],
                'completion_photo_path' => $path,
                'status' => 'done',
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if ($path && ! Storage::disk('local')->delete($path)) {
                report(new \RuntimeException('Foto penyelesaian tidak dapat dibersihkan setelah penyimpanan gagal.'));
            }
            throw $exception;
        }
        if ($oldPath && $oldPath !== $path && ! Storage::disk('local')->delete($oldPath)) {
            report(new \RuntimeException('Foto penyelesaian lama tidak dapat dihapus.'));
        }

        return back()->with('success', 'Penyelesaian perbaikan berhasil dikirim untuk diverifikasi.');
    }

    public function settings(): View
    {
        $this->authorizeAdmin();

        return view('findings.settings', [
            'pics' => FindingPic::orderBy('name')->get(),
            'options' => FindingLookupOption::orderBy('kind')->orderBy('value')->get(),
            'kinds' => self::LOOKUP_LABELS,
        ]);
    }

    public function storePic(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
        ]);
        FindingPic::create([...$data, 'department' => $data['department'] ?? '', 'active' => true]);

        return back()->with('success', 'PIC berhasil ditambahkan.');
    }

    public function updatePic(Request $request, FindingPic $pic): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);
        $pic->update([...$data, 'department' => $data['department'] ?? '', 'active' => $request->boolean('active')]);

        return back()->with('success', 'Data PIC berhasil diperbarui.');
    }

    public function destroyPic(FindingPic $pic): RedirectResponse
    {
        $this->authorizeAdmin();
        $pic->delete();

        return back()->with('success', 'PIC berhasil dihapus dari daftar. Data temuan lama tetap tersimpan.');
    }

    public function storeOption(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(self::LOOKUP_LABELS))],
            'value' => ['required', 'string', 'max:150', Rule::unique('finding_lookup_options', 'value')->where('kind', $request->input('kind'))],
        ]);
        FindingLookupOption::create([...$data, 'active' => true]);

        return back()->with('success', 'Pilihan formulir berhasil ditambahkan.');
    }

    public function destroyOption(FindingLookupOption $option): RedirectResponse
    {
        $this->authorizeAdmin();
        $option->delete();

        return back()->with('success', 'Pilihan formulir berhasil dihapus.');
    }

    public function photo(Finding $finding, string $kind)
    {
        abort_unless(in_array($kind, ['problem', 'completion'], true), 404);
        $path = $kind === 'problem' ? $finding->problem_photo_path : $finding->completion_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    public function responsePhoto(string $token, string $kind)
    {
        abort_unless(in_array($kind, ['problem', 'completion'], true), 404);
        $finding = $this->findingByToken($token);
        $path = $kind === 'problem' ? $finding->problem_photo_path : $finding->completion_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    private function filteredFindings(Request $request): Builder
    {
        return Finding::query()
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->toString()).'%';
                $query->where(function (Builder $subquery) use ($term): void {
                    $subquery->where('code', 'like', $term)
                        ->orWhere('part_name', 'like', $term)
                        ->orWhere('part_type', 'like', $term)
                        ->orWhere('problem_type', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('root_cause', 'like', $term)
                        ->orWhereHas('pic', fn (Builder $picQuery) => $picQuery->where('name', 'like', $term));
                });
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', function (Builder $query) use ($request): void {
                if ($request->input('status') === 'late') {
                    $query->whereDate('due_date', '<', today())->whereNotIn('status', ['done', 'verified']);
                } else {
                    $query->where('status', $request->input('status'));
                }
            })
            ->when($request->filled('category') && $request->input('category') !== 'all',
                fn (Builder $query) => $query->where('category', $request->input('category')))
            ->when($request->filled('area_line'), fn (Builder $query) => $query->where('area_line', $request->input('area_line')))
            ->when($request->filled('pic_id'), fn (Builder $query) => $query->where('pic_id', $request->input('pic_id')))
            ->when($request->filled('priority'), fn (Builder $query) => $query->where('priority', $request->input('priority')))
            ->when($request->filled('shift'), fn (Builder $query) => $query->where('shift', $request->input('shift')))
            ->when($request->filled('part_name'), fn (Builder $query) => $query->where('part_name', $request->input('part_name')))
            ->when($request->filled('part_type'), fn (Builder $query) => $query->where('part_type', $request->input('part_type')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('found_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('found_date', '<=', $request->input('to')))
            ->orderByDesc('found_date')->orderByDesc('id');
    }

    private function filterOptions(): array
    {
        return [
            'statuses' => Finding::STATUS_LABELS,
            'categories' => Finding::CATEGORIES,
            'priorities' => Finding::PRIORITIES,
            'lines' => FindingLookupOption::where('kind', 'area_line')->where('active', true)->orderBy('value')->pluck('value'),
            'partNames' => FindingLookupOption::where('kind', 'part_name')->where('active', true)->orderBy('value')->pluck('value'),
            'partTypes' => FindingLookupOption::where('kind', 'part_type')->where('active', true)->orderBy('value')->pluck('value'),
            'pics' => FindingPic::where('active', true)->orderBy('name')->get(),
        ];
    }

    private function formOptions(): array
    {
        return [
            'options' => FindingLookupOption::where('active', true)->orderBy('value')->get()->groupBy('kind'),
            'pics' => FindingPic::where('active', true)->orderBy('name')->get(),
            'categories' => Finding::CATEGORIES,
            'priorities' => Finding::PRIORITIES,
        ];
    }

    private function inspectionFindingAttributes(Inspection $inspection): array
    {
        $inspection->loadMissing(['product', 'area.parent']);

        return [
            'found_date' => $inspection->tanggal,
            'shift' => $inspection->shift,
            'area_line' => $inspection->area?->parent
                ? $inspection->area->parent->nama.' / '.$inspection->area->nama
                : ($inspection->area?->nama ?? '—'),
            'part_name' => $inspection->product?->nama ?? 'Produk dihapus',
            'part_type' => $inspection->product?->tipe ?: 'Tidak ditentukan',
        ];
    }

    private function validatedFinding(Request $request, ?Finding $finding = null): array
    {
        $inspectionAttributes = $finding?->inspection
            ? $this->inspectionFindingAttributes($finding->inspection)
            : [];
        $rules = [
            'found_date' => ['required', 'date'],
            'shift' => ['required', 'string', 'max:40'],
            'area_line' => ['required', 'string', 'max:150', Rule::exists('finding_lookup_options', 'value')->where('kind', 'area_line')->where('active', true)],
            'part_name' => ['required', 'string', 'max:150', Rule::exists('finding_lookup_options', 'value')->where('kind', 'part_name')->where('active', true)],
            'part_type' => ['required', 'string', 'max:150', Rule::exists('finding_lookup_options', 'value')->where('kind', 'part_type')->where('active', true)],
            'problem_type' => ['required', 'string', 'max:150', Rule::exists('finding_lookup_options', 'value')->where('kind', 'problem_type')->where('active', true)],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(Finding::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Finding::PRIORITIES))],
            'analysis_man' => ['required', 'string', 'max:2000'],
            'analysis_method' => ['required', 'string', 'max:2000'],
            'analysis_machine' => ['required', 'string', 'max:2000'],
            'analysis_material' => ['required', 'string', 'max:2000'],
            'root_cause' => ['required', 'string', 'min:3', 'max:2000'],
            'pic_id' => ['required', 'integer', Rule::exists('finding_pics', 'id')->where('active', true)->whereNotNull('email')],
            'problem_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
        foreach (array_keys($inspectionAttributes) as $attribute) {
            unset($rules[$attribute]);
        }
        $data = $request->validate($rules);
        unset($data['problem_photo']);
        $data['description'] = $data['description'] ?? '';

        return [...$inspectionAttributes, ...$data];
    }

    private function findingByToken(string $token): Finding
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);

        return Finding::with('pic')->where('response_token', $token)->firstOrFail();
    }

    private function deliverFindingEmail(Finding $finding): void
    {
        $email = $finding->pic?->email;
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Alamat email PIC tidak valid.');
        }
        if (! app()->environment('testing') && (
            config('mail.default') !== 'smtp'
            || ! filled(config('mail.mailers.smtp.host'))
            || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
        )) {
            throw new \RuntimeException('Mailer aplikasi belum diatur untuk pengiriman email keluar.');
        }

        Mail::to($email)->send(new FindingPicNotification($finding));
        $finding->update(['email_sent_at' => now()]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    private function authorizeInspectionAccess(Inspection $inspection): void
    {
        abort_unless(
            auth()->user()->isAdmin() || (int) $inspection->user_id === (int) auth()->id(),
            404
        );
    }
}
