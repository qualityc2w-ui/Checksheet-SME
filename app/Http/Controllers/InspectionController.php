<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Defect;
use App\Models\DimensionStandard;
use App\Models\Finding;
use App\Models\FunctionStandard;
use App\Models\Inspection;
use App\Models\Inspector;
use App\Models\Product;
use App\Models\VisualStandard;
use App\Mail\InspectionNgReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InspectionController extends Controller
{
    public function index(Request $request): View
    {
        $buildingId = $request->validate([
            'building_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->whereNull('parent_id')],
        ])['building_id'] ?? null;
        $buildings = Area::whereNull('parent_id')->withCount(['children', 'products'])->orderBy('nama')->get();
        $selectedBuilding = $buildingId ? Area::with('children')->findOrFail($buildingId) : null;
        $formProducts = Product::with('areaRelation.parent')->orderBy('kode')->get();
        if ($selectedBuilding) {
            $allowedAreaIds = $selectedBuilding->children->modelKeys();
            $allowedAreaIds[] = $selectedBuilding->id;
            $formProducts = $formProducts->whereIn('area_id', $allowedAreaIds)->values();
        } else {
            $formProducts = collect();
        }

        return view('inspections.index', [
            'formProducts' => $formProducts,
            'buildings' => $buildings,
            'selectedBuilding' => $selectedBuilding,
            'inspectors' => Inspector::orderBy('nama')->get(),
            'defects' => Defect::orderBy('nama')->get(),
            'dimensionStandards' => DimensionStandard::with('product')->get()->groupBy('product_id'),
            'visualStandards' => VisualStandard::with('product')->get()->groupBy('product_id'),
            'functionStandards' => FunctionStandard::with('product')->get()->groupBy('product_id'),
        ]);
    }

    public function history(Request $request): View
    {
        $filters = $this->validateFilters($request);
        $visibleInspections = Inspection::visibleTo($request->user());
        $inspections = $this->filteredQuery($request)
            ->with(['product', 'inspector', 'area.parent', 'approver', 'findings'])
            ->latest('tanggal')
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $products = Product::whereIn('id', (clone $visibleInspections)
            ->whereNotNull('product_id')->select('product_id')->distinct())
            ->orderBy('kode')->get();
        $areas = Area::whereIn('id', (clone $visibleInspections)
            ->whereNotNull('area_id')->select('area_id')->distinct())
            ->orderBy('nama')->get();

        return view('inspections.history', [
            'inspections' => $inspections,
            'filters' => $filters,
            'products' => $products,
            'areas' => $areas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->canWriteInspections(), 403);
        $validated = $request->validate([
            'building_id' => ['required', 'integer', Rule::exists('areas', 'id')->whereNull('parent_id')],
            'tanggal' => ['required', 'date'],
            'product_id' => ['required', 'exists:products,id'],
            'inspector_id' => auth()->user()->isAdmin()
                ? ['required', 'exists:inspectors,id']
                : ['prohibited'],
            'shift' => ['required', 'in:Shift 1,Shift 2'],
            'sesi' => ['required', 'in:Start,Middle,End'],
            'sample' => ['required', 'integer', 'min:1', 'max:10000'],
            'measurement_mode' => ['sometimes', Rule::in(['manual', 'automatic'])],
            'dimensions' => ['nullable', 'array'],
            'dimensions.*' => ['required', 'numeric', 'between:-99999999,99999999'],
            'visual_results' => ['nullable', 'array'],
            'visual_results.*' => ['required', 'in:OK,NG'],
            'function_results' => ['nullable', 'array'],
            'function_results.*' => ['required', 'in:OK,NG'],
            'defect_id' => ['nullable', 'exists:defects,id'],
            'catatan_dimensi' => ['nullable', 'string', 'max:2000'],
            'catatan_visual' => ['nullable', 'string', 'max:2000'],
            'catatan_fungsi' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['nullable', 'array'],
            'evidence.*.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $productId = (int) $validated['product_id'];
        $product = Product::with('areaRelation.parent')->findOrFail($productId);
        $building = Area::with('children')->findOrFail($validated['building_id']);
        $allowedAreaIds = $building->children->modelKeys();
        $allowedAreaIds[] = $building->id;
        if (! in_array((int) $product->area_id, $allowedAreaIds, true)) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk tidak terdaftar pada folder gedung yang dipilih.',
            ]);
        }
        $dimensions = DimensionStandard::where('product_id', $productId)->get();
        $visualStandards = VisualStandard::where('product_id', $productId)->get();
        $functionStandards = FunctionStandard::where('product_id', $productId)->get();
        $dimensionValues = $request->input('dimensions', []);
        $visualValues = $request->input('visual_results', []);
        $functionValues = $request->input('function_results', []);
        $evidenceFiles = $request->file('evidence', []);
        $measurementMode = $validated['measurement_mode'] ?? 'manual';

        $this->requireSubmittedStandards($dimensions->modelKeys(), $dimensionValues, 'dimensions', true);
        $this->requireSubmittedStandards(
            $visualStandards->isEmpty() ? ['manual'] : $visualStandards->modelKeys(),
            $visualValues,
            'visual_results'
        );
        $this->requireSubmittedStandards(
            $functionStandards->isEmpty() ? ['manual'] : $functionStandards->modelKeys(),
            $functionValues,
            'function_results'
        );

        $ngDimensionIds = $dimensions->filter(fn (DimensionStandard $standard) => (
            (float) $dimensionValues[$standard->id] < (float) $standard->nilai_standar - (float) $standard->toleransi_min
            || (float) $dimensionValues[$standard->id] > (float) $standard->nilai_standar + (float) $standard->toleransi_max
        ))->modelKeys();
        $ngVisualIds = collect($visualValues)->filter(fn (string $result) => $result === 'NG')->keys()->all();
        $ngFunctionIds = collect($functionValues)->filter(fn (string $result) => $result === 'NG')->keys()->all();
        $ngItemsBySection = [
            'dimensions' => $ngDimensionIds,
            'visual' => $ngVisualIds,
            'function' => $ngFunctionIds,
        ];
        foreach ($evidenceFiles as $section => $items) {
            if (! isset($ngItemsBySection[$section])) {
                throw ValidationException::withMessages([
                    'evidence' => 'Foto bukti hanya dapat dilampirkan pada item dengan hasil NG.',
                ]);
            }
            foreach (array_keys($items) as $itemId) {
                if (! in_array((string) $itemId, array_map('strval', $ngItemsBySection[$section]), true)) {
                    throw ValidationException::withMessages([
                        "evidence.{$section}.{$itemId}" => 'Foto bukti hanya dapat dilampirkan bila item tersebut berstatus NG.',
                    ]);
                }
            }
        }
        foreach ($ngItemsBySection as $section => $ids) {
            foreach ($ids as $id) {
                if (! isset($evidenceFiles[$section][$id])) {
                    throw ValidationException::withMessages([
                        "evidence.{$section}.{$id}" => 'Foto bukti wajib dilampirkan untuk setiap item dengan hasil NG.',
                    ]);
                }
            }
        }

        $dimensiDetails = $dimensions->map(function (DimensionStandard $standard) use ($dimensionValues) {
            $value = (float) $dimensionValues[$standard->id];
            $minimum = (float) $standard->nilai_standar - (float) $standard->toleransi_min;
            $maximum = (float) $standard->nilai_standar + (float) $standard->toleransi_max;

            return [
                'item_id' => (string) $standard->id,
                'parameter' => $standard->parameter,
                'nilai' => $value,
                'standar' => (float) $standard->nilai_standar,
                'min' => $minimum,
                'max' => $maximum,
                'satuan' => $standard->satuan,
                'hasil' => $value >= $minimum && $value <= $maximum ? 'OK' : 'NG',
            ];
        })->all();

        $visualItems = $this->checklistResults($visualStandards, $visualValues);
        $functionItems = $this->checklistResults($functionStandards, $functionValues);
        $overallResult = collect($dimensiDetails)->contains(fn (array $item) => $item['hasil'] === 'NG')
            || collect($visualItems)->contains(fn (array $item) => $item['hasil'] === 'NG')
            || collect($functionItems)->contains(fn (array $item) => $item['hasil'] === 'NG')
                ? 'NG'
                : 'OK';

        $inspector = auth()->user()->isAdmin()
            ? Inspector::findOrFail($validated['inspector_id'])
            : $this->inspectorForCurrentUser();
        $validated['inspector_id'] = $inspector->id;

        if ($overallResult === 'NG') {
            if (! filter_var($inspector->email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'inspector_id' => 'Inspector harus memiliki alamat email yang valid untuk menerima laporan NG.',
                ]);
            }
        }

        $storedEvidence = [];
        $storedEvidenceByItem = [];
        $inspectionId = null;
        $userId = auth()->id();

        try {
            $inspectionId = DB::transaction(function () use (
                $validated,
                $userId,
                $product,
                $dimensiDetails,
                $visualItems,
                $functionItems,
                $overallResult,
                $measurementMode,
                $request,
                &$storedEvidence,
                &$storedEvidenceByItem
            ): int {
                $inspection = Inspection::create([
                    'tanggal' => $validated['tanggal'],
                    'product_id' => $validated['product_id'],
                    'area_id' => $product->area_id,
                    'inspector_id' => $validated['inspector_id'],
                    'user_id' => $userId,
                    'shift' => $validated['shift'],
                    'sesi' => $validated['sesi'],
                    'sample' => $validated['sample'],
                    'measurement_mode' => $measurementMode,
                    'dimensi' => [
                        'hasil' => collect($dimensiDetails)->contains(fn (array $item) => $item['hasil'] === 'NG') ? 'NG' : 'OK',
                        'detail' => $dimensiDetails,
                        'defectId' => $validated['defect_id'] ?? '',
                        'catatan' => $validated['catatan_dimensi'] ?? '',
                    ],
                    'visual' => [
                        'hasil' => collect($visualItems)->contains(fn (array $item) => $item['hasil'] === 'NG') ? 'NG' : 'OK',
                        'detail' => $visualItems,
                        'defectId' => $validated['defect_id'] ?? '',
                        'catatan' => $validated['catatan_visual'] ?? '',
                    ],
                    'fungsi' => [
                        'hasil' => collect($functionItems)->contains(fn (array $item) => $item['hasil'] === 'NG') ? 'NG' : 'OK',
                        'detail' => $functionItems,
                        'defectId' => $validated['defect_id'] ?? '',
                        'catatan' => $validated['catatan_fungsi'] ?? '',
                    ],
                    'hasil_akhir' => $overallResult,
                    'approved' => false,
                ]);

                foreach ($request->file('evidence', []) as $section => $items) {
                    foreach ($items as $itemId => $file) {
                        $path = $file->store('inspection-evidence/'.$inspection->id, 'local');
                        if ($path === false) {
                            throw new \RuntimeException('Bukti foto gagal disimpan.');
                        }
                        $storedEvidence[] = $path;
                        $storedEvidenceByItem[$section][$itemId] = $path;
                    }
                }

                $inspection->update(['evidence' => $storedEvidenceByItem]);

                return $inspection->id;
            });
        } catch (Throwable $exception) {
            if (! Storage::disk('local')->delete($storedEvidence)) {
                Log::error('Unable to clean up inspection evidence after a failed save.', [
                    'paths' => $storedEvidence,
                ]);
            }
            throw $exception;
        }

        $savedInspection = Inspection::with(['product', 'area', 'inspector'])->findOrFail($inspectionId);
        $redirect = redirect()->route('inspections.index')->with('success', 'Data inspeksi berhasil disimpan dengan hasil '.$overallResult.'.');

        if ($overallResult !== 'NG') {
            return $redirect;
        }

        try {
            $this->deliverInspectionNgEmail($savedInspection);
            $redirect->with('success', 'Data inspeksi NG berhasil disimpan dan laporan dikirim melalui email ke '.$savedInspection->inspector->email.'.');
        } catch (Throwable $exception) {
            Log::error('Unable to email NG inspection report.', [
                'inspection_id' => $savedInspection->id,
                'recipient' => $savedInspection->inspector->email,
                'exception' => $exception,
            ]);
            $redirect->with('warning', 'Data inspeksi berhasil disimpan, tetapi laporan email gagal dikirim. Periksa konfigurasi email lalu kirim ulang dari riwayat inspeksi.');
        }

        $nextPoint = $savedInspection->pendingNgPointChecks()[0] ?? null;
        if ($nextPoint) {
            return redirect()->route('findings.inspection.create', [
                $savedInspection,
                $nextPoint['section'],
                $nextPoint['index'],
            ])->with('success', 'Hasil inspeksi NG. Lengkapi analisis 4M untuk setiap point check NG.');
        }

        return $redirect;
    }

    public function pointCheckImage(VisualStandard $visualStandard)
    {
        $path = $visualStandard->image_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return $this->privateImageResponse($path);
    }

    public function productPointCheckImage(Product $product)
    {
        $path = $product->image_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return $this->privateImageResponse($path);
    }

    private function privateImageResponse(string $path)
    {
        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Inspection $inspection): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_if($inspection->approved, 422, 'Laporan ini sudah disetujui.');

        $inspection->update([
            'approved' => true,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Laporan inspeksi disetujui dengan stempel QC 2W DPM.');
    }

    public function sendEmail(Inspection $inspection): RedirectResponse
    {
        abort_unless(auth()->user()?->canWriteInspections(), 403);
        $this->authorizeInspectionAccess($inspection);
        abort_unless($inspection->hasil_akhir === 'NG', 422);

        try {
            $this->deliverInspectionNgEmail($inspection->load(['product', 'area.parent', 'inspector']));
        } catch (Throwable $exception) {
            Log::error('Unable to resend NG inspection report by email.', [
                'inspection_id' => $inspection->id,
                'recipient' => $inspection->inspector?->email,
                'exception' => $exception,
            ]);

            return back()->withErrors(['email' => 'Email laporan gagal dikirim. Periksa alamat email inspector dan konfigurasi SMTP.']);
        }

        return back()->with('success', 'Laporan inspeksi berhasil dikirim melalui email ke '.$inspection->inspector->email.'.');
    }

    public function destroy(Inspection $inspection): RedirectResponse
    {
        abort_unless(auth()->user()?->canWriteInspections(), 403);
        $this->authorizeInspectionAccess($inspection);
        $evidence = collect($inspection->evidence ?? [])->flatten()->all();
        $inspection->delete();

        if ($evidence !== [] && ! Storage::disk('local')->delete($evidence)) {
            return back()->with('warning', 'Data inspeksi terhapus, tetapi sebagian foto bukti gagal dibersihkan dari penyimpanan.');
        }

        return back()->with('success', 'Data inspeksi berhasil dihapus.');
    }

    public function evidence(Inspection $inspection, int $index): StreamedResponse
    {
        $this->authorizeInspectionAccess($inspection);
        $path = collect($inspection->evidence ?? [])->flatten()->values()->get($index);

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    public function sharedEvidence(Inspection $inspection, string $section, string $item)
    {
        abort_unless(in_array($section, ['dimensions', 'visual', 'function'], true), 404);
        $path = $inspection->evidence[$section][$item] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->validateFilters($request);
        $inspections = $this->filteredQuery($request)
            ->with(['product', 'inspector', 'area.parent', 'approver'])
            ->latest('tanggal')
            ->get();

        $rows = $inspections->map(fn (Inspection $inspection) => [
                    $inspection->tanggal->format('Y-m-d'),
                    $inspection->product?->kode ?? '-',
                    $inspection->product?->nama ?? 'Produk dihapus',
                    $inspection->area?->parent?->nama ?? $inspection->area?->nama ?? '-',
                    $inspection->area?->nama ?? '-',
                    $inspection->inspectorName,
                    $inspection->shift,
                    $inspection->sesi,
                    $inspection->sample,
                    $inspection->measurement_mode === 'automatic' ? 'Otomatis' : 'Manual',
                    $inspection->hasil_akhir,
                    $inspection->approved ? 'Disetujui' : 'Menunggu',
                    $inspection->approver?->name ?? '-',
                    $inspection->approved_at?->format('Y-m-d H:i:s') ?? '-',
                ])->all();

        $path = app(\App\Support\XlsxWorkbook::class)->create(
            'Riwayat Inspeksi',
            ['Tanggal', 'Kode Produk', 'Produk', 'Gedung', 'Area / Line', 'Inspector', 'Shift', 'Sesi', 'Sample', 'Mode ukur dimensi', 'Hasil', 'Approval', 'Disetujui oleh', 'Waktu approval'],
            $rows,
        );

        return response()->download($path, 'riwayat-inspeksi-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function report(Request $request): View
    {
        $filters = $this->validateFilters($request);
        $visibleInspections = Inspection::visibleTo($request->user());
        $inspections = $this->filteredQuery($request)
            ->with(['product.visualStandards', 'inspector', 'area.parent', 'approver'])
            ->latest('tanggal')
            ->latest()
            ->get();
        $products = Product::whereIn('id', (clone $visibleInspections)
            ->whereNotNull('product_id')
            ->select('product_id')
            ->distinct())
            ->orderBy('kode')
            ->get();
        $areas = Area::whereIn('id', (clone $visibleInspections)
            ->whereNotNull('area_id')
            ->select('area_id')
            ->distinct())
            ->orderBy('nama')
            ->get();

        return view('inspections.report', [
            'reportGroups' => $this->groupInspectionsForReport($inspections),
            'singleInspection' => null,
            'filters' => $filters,
            'generatedAt' => now(),
            'products' => $products,
            'areas' => $areas,
            'productTypes' => $products->pluck('tipe')->filter()->unique()->sort()->values(),
        ]);
    }

    public function reportPdf(Request $request)
    {
        $filters = $this->validateFilters($request);
        $inspections = $this->filteredQuery($request)
            ->with(['product.visualStandards', 'inspector', 'area.parent', 'approver'])
            ->latest('tanggal')
            ->latest()
            ->get();

        return Pdf::loadView('inspections.report-pdf', [
            'reportGroups' => $this->groupInspectionsForReport($inspections, extension_loaded('gd')),
            'singleInspection' => null,
            'filters' => $filters,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
            ->download('laporan-inspeksi-qc-'.now()->format('Ymd-His').'.pdf');
    }

    public function reportInspection(Inspection $inspection): View
    {
        $this->authorizeInspectionAccess($inspection);
        $inspections = $this->inspectionsForShiftReport($inspection);

        return view('inspections.report', [
            'reportGroups' => $this->groupInspectionsForReport($inspections),
            'singleInspection' => $inspection,
            'filters' => [],
            'generatedAt' => now(),
        ]);
    }

    public function reportInspectionPdf(Inspection $inspection)
    {
        $this->authorizeInspectionAccess($inspection);
        $inspections = $this->inspectionsForShiftReport($inspection);

        return Pdf::loadView('inspections.report-pdf', [
            'reportGroups' => $this->groupInspectionsForReport($inspections, extension_loaded('gd')),
            'singleInspection' => $inspection,
            'filters' => [],
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
            ->download('laporan-inspeksi-'.$inspection->tanggal->format('Ymd').'-'.strtolower(str_replace(' ', '-', $inspection->shift)).'-'.$inspection->product_id.'.pdf');
    }

    private function inspectionsForShiftReport(Inspection $inspection): Collection
    {
        return Inspection::query()
            ->visibleTo(auth()->user())
            ->whereDate('tanggal', $inspection->tanggal)
            ->where('shift', $inspection->shift)
            ->where('product_id', $inspection->product_id)
            ->with(['product.visualStandards', 'inspector', 'area.parent', 'approver'])
            ->orderByRaw("CASE sesi WHEN 'Start' THEN 0 WHEN 'Middle' THEN 1 WHEN 'End' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();
    }

    private function groupInspectionsForReport(Collection $inspections, bool $includePointCheckImages = true): Collection
    {
        return $inspections
            ->groupBy(fn (Inspection $inspection) => implode('|', [
                $inspection->tanggal->format('Y-m-d'),
                $inspection->shift,
                $inspection->product_id,
            ]))
            ->map(function (Collection $group) use ($includePointCheckImages) {
                $first = $group->first();
                $productImage = $first->product?->image_path;
                $pointCheckImages = collect();
                if ($productImage) {
                    $exists = Storage::disk('local')->exists($productImage);
                    $pointCheckImages->push([
                        'checklist' => 'Visual point check part '.$first->product->kode,
                        'data_uri' => $exists && $includePointCheckImages
                            ? 'data:'.Storage::disk('local')->mimeType($productImage).';base64,'.base64_encode(Storage::disk('local')->get($productImage))
                            : null,
                        'missing_message' => $exists && ! $includePointCheckImages
                            ? 'Gambar point check tidak disertakan karena ekstensi PHP GD belum aktif.'
                            : 'Gambar point check tidak tersedia',
                    ]);
                }
                $legacyImages = $first->product?->visualStandards
                    ->filter(fn (VisualStandard $standard) => filled($standard->image_path))
                    ->map(function (VisualStandard $standard) use ($includePointCheckImages) {
                        $imagePath = $standard->image_path;
                        $imageExists = Storage::disk('local')->exists($imagePath);

                        return [
                            'checklist' => $standard->checklist,
                            'data_uri' => $imageExists && $includePointCheckImages
                                ? 'data:'.Storage::disk('local')->mimeType($imagePath).';base64,'.base64_encode(Storage::disk('local')->get($imagePath))
                                : null,
                            'missing_message' => $imageExists && ! $includePointCheckImages
                                ? 'Gambar point check tidak disertakan karena ekstensi PHP GD belum aktif.'
                                : 'Gambar point check tidak tersedia',
                        ];
                    }) ?? collect();
                $pointCheckImages = $pointCheckImages->concat($legacyImages)->values();

                return [
                    'date' => $first->tanggal,
                    'shift' => $first->shift,
                    'product' => $first->product,
                    'area' => $first->area,
                    'point_check_images' => $pointCheckImages,
                    'sessions' => collect(['Start', 'Middle', 'End'])
                        ->mapWithKeys(fn (string $session) => [
                            $session => $group->where('sesi', $session)->values(),
                        ]),
                ];
            })->values();
    }

    private function filteredQuery(Request $request): Builder
    {
        return Inspection::visibleTo($request->user())
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('tanggal', '>=', $request->input('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('tanggal', '<=', $request->input('to')))
            ->when($request->filled('product_id'), fn (Builder $query) => $query->where('product_id', $request->input('product_id')))
            ->when($request->filled('product_type'), fn (Builder $query) => $query->whereHas('product', fn (Builder $productQuery) => $productQuery->where('tipe', $request->input('product_type'))))
            ->when($request->filled('area_id'), fn (Builder $query) => $query->where('area_id', $request->input('area_id')))
            ->when($request->filled('shift'), fn (Builder $query) => $query->where('shift', $request->input('shift')))
            ->when($request->input('result') === 'NG', fn (Builder $query) => $query->where('hasil_akhir', 'NG'))
            ->when(in_array($request->input('result'), ['OK', 'Lulus'], true), fn (Builder $query) => $query->whereIn('hasil_akhir', ['OK', 'Lulus']));
    }

    private function inspectorForCurrentUser(): Inspector
    {
        $user = auth()->user();
        $inspector = Inspector::where('email', $user->email)->first()
            ?? Inspector::where('nama', $user->name)->whereNull('email')->first();

        if (! $inspector) {
            return Inspector::create([
                'nama' => $user->name,
                'email' => $user->email,
            ]);
        }

        $inspector->forceFill([
            'nama' => $user->name,
            'email' => $user->email,
        ])->save();

        return $inspector;
    }

    private function deliverInspectionNgEmail(Inspection $inspection): void
    {
        $email = $inspection->inspector?->email;
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Alamat email inspector tidak valid.');
        }
        $this->assertSmtpConfigured();
        Mail::to($email)->send(new InspectionNgReport($inspection));
        $inspection->update(['email_sent_at' => now()]);
    }

    private function assertSmtpConfigured(): void
    {
        if (! app()->environment('testing') && (
            config('mail.default') !== 'smtp'
            || ! filled(config('mail.mailers.smtp.host'))
            || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
        )) {
            throw new \RuntimeException('Mailer aplikasi belum diatur untuk pengiriman email keluar.');
        }
    }

    private function authorizeInspectionAccess(Inspection $inspection): void
    {
        abort_unless(
            auth()->user()->isAdmin() || (int) $inspection->user_id === (int) auth()->id(),
            404
        );
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'product_id' => ['nullable', 'exists:products,id'],
            'product_type' => ['nullable', 'string', 'max:120'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'shift' => ['nullable', 'in:Shift 1,Shift 2'],
            'result' => ['nullable', 'in:OK,Lulus,NG'],
        ]);
    }

    private function requireSubmittedStandards(array $ids, mixed $values, string $field, bool $numeric = false): void
    {
        foreach ($ids as $id) {
            if (! array_key_exists($id, $values) || ($numeric && (! is_numeric($values[$id]) || $values[$id] === ''))) {
                throw ValidationException::withMessages([
                    $field.'.'.$id => 'Mohon lengkapi seluruh item pengecekan.',
                ]);
            }

            if (! $numeric && ! in_array($values[$id], ['OK', 'NG'], true)) {
                throw ValidationException::withMessages([
                    $field.'.'.$id => 'Pilih hasil OK atau NG untuk setiap item.',
                ]);
            }
        }
    }

    private function checklistResults(iterable $standards, array $values): array
    {
        if (count($standards) === 0) {
            return [[
                'checklist' => 'Pengecekan manual',
                'hasil' => $values['manual'],
            ]];
        }

        return collect($standards)->map(fn ($standard) => [
            'item_id' => (string) $standard->id,
            'checklist' => $standard->checklist,
            'hasil' => $values[$standard->id],
        ])->all();
    }

    private static function safeCsvCell(string|int $value): string|int
    {
        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
