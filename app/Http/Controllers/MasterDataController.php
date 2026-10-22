<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Defect;
use App\Models\DimensionStandard;
use App\Models\FunctionStandard;
use App\Models\Inspector;
use App\Models\Product;
use App\Models\User;
use App\Models\VisualStandard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    private const SECTIONS = [
        'areas' => ['label' => 'Area / Line', 'model' => Area::class],
        'products' => ['label' => 'Produk', 'model' => Product::class],
        'inspectors' => ['label' => 'Inspector', 'model' => Inspector::class],
        'defects' => ['label' => 'Jenis defect', 'model' => Defect::class],
        'dimension-standards' => ['label' => 'Standard dimensi', 'model' => DimensionStandard::class],
        'visual-standards' => ['label' => 'Standard visual', 'model' => VisualStandard::class],
        'function-standards' => ['label' => 'Standard fungsi', 'model' => FunctionStandard::class],
        'accounts' => ['label' => 'Akun pengguna', 'model' => User::class],
    ];

    public function index(Request $request, ?string $section = 'products'): View
    {
        $this->authorizeAdmin();
        $section = $section ?? 'products';
        $model = $this->modelFor($section);
        $query = $model::query();

        if (in_array($section, ['dimension-standards', 'visual-standards', 'function-standards'], true)) {
            $query->with('product.areaRelation.parent');
        }
        if ($section === 'areas') {
            $query->with(['parent'])->withCount(['products', 'children']);
        }

        $records = $section === 'accounts'
            ? $query->orderBy('name')->get()
            : $query->latest()->get();
        $editing = $request->filled('edit') ? $model::findOrFail($request->input('edit')) : null;

        return view('master.index', [
            'section' => $section,
            'sections' => self::SECTIONS,
            'records' => $records,
            'editing' => $editing,
            'products' => Product::with('areaRelation.parent')->orderBy('kode')->get(),
            'areas' => Area::with('parent')->orderBy('nama')->get(),
            'standardLines' => Area::whereHas('products')->with('parent')->orderBy('nama')->get(),
            'productTypes' => Product::query()->whereNotNull('tipe')->where('tipe', '!=', '')
                ->distinct()->orderBy('tipe')->pluck('tipe'),
            'hasUnassignedProducts' => Product::whereNull('area_id')->exists(),
            'hasUntypedProducts' => Product::whereNull('tipe')->orWhere('tipe', '')->exists(),
            'buildingAreas' => Area::whereNull('parent_id')->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request, string $section): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($section === 'accounts') {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12', 'confirmed'],
                'role' => ['required', 'in:viewer,inspector,admin'],
            ]);
            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ]);

            return redirect()->route('master.index', $section)->with('success', 'Akun berhasil dibuat.');
        }

        $model = $this->modelFor($section);
        $attributes = $this->validatedData($request, $section);
        $pointCheckImage = $attributes['point_check_image'] ?? null;
        unset($attributes['point_check_image']);

        if ($section === 'products') {
            $attributes['area'] = Area::find($attributes['area_id'] ?? null)?->nama;
        }
        if (in_array($section, ['dimension-standards', 'visual-standards', 'function-standards'], true)) {
            $this->validateStandardProductFilters($request, $attributes['product_id']);
        }

        if (in_array($section, ['products', 'visual-standards'], true) && $pointCheckImage) {
            $path = $pointCheckImage->store('point-check-images', 'local');
            if ($path === false) {
                throw new \RuntimeException('Gambar point-check gagal disimpan.');
            }
            $attributes['image_path'] = $path;
        }

        try {
            $model::create($attributes);
        } catch (\Throwable $exception) {
            if (isset($path) && ! Storage::disk('local')->delete($path)) {
                Log::error('Unable to clean up point-check image after a failed save.', ['path' => $path]);
            }
            throw $exception;
        }

        return redirect()->route('master.index', $section)->with('success', 'Data berhasil ditambahkan.');
    }

    public function update(Request $request, string $section, string $record): RedirectResponse
    {
        $this->authorizeAdmin();
        $model = $this->modelFor($section);
        $item = $model::findOrFail($record);

        if ($section === 'accounts') {
            abort_if((int) $item->id === (int) auth()->id() && $request->input('role') !== 'admin', 422, 'Akun yang sedang digunakan tidak dapat diubah menjadi non-admin.');
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$item->id],
                'password' => ['nullable', 'string', 'min:12', 'confirmed'],
                'role' => ['required', 'in:viewer,inspector,admin'],
            ]);
            abort_if($item->role === 'admin' && $data['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1, 422, 'Setidaknya satu akun admin harus tetap ada.');
            $item->name = $data['name'];
            $item->email = $data['email'];
            $item->role = $data['role'];
            if (($data['password'] ?? '') !== '') {
                $item->password = Hash::make($data['password']);
            }
            $item->save();

            return redirect()->route('master.index', $section)->with('success', 'Akun berhasil diperbarui.');
        }

        $attributes = $this->validatedData($request, $section, $item);
        if (in_array($section, ['dimension-standards', 'visual-standards', 'function-standards'], true)) {
            $this->validateStandardProductFilters($request, $attributes['product_id']);
        }
        $pointCheckImage = $attributes['point_check_image'] ?? null;
        unset($attributes['point_check_image']);
        $oldImagePath = in_array($section, ['products', 'visual-standards'], true) ? $item->image_path : null;

        if (in_array($section, ['products', 'visual-standards'], true) && $pointCheckImage) {
            $path = $pointCheckImage->store('point-check-images', 'local');
            if ($path === false) {
                throw new \RuntimeException('Gambar point-check gagal disimpan.');
            }
            $attributes['image_path'] = $path;
        }

        try {
            $item->update($attributes);
        } catch (\Throwable $exception) {
            if (isset($path) && ! Storage::disk('local')->delete($path)) {
                Log::error('Unable to clean up point-check image after a failed update.', ['path' => $path]);
            }
            throw $exception;
        }
        if (isset($path) && $oldImagePath && ! Storage::disk('local')->delete($oldImagePath)) {
            Log::warning('Old point-check image could not be removed.', ['path' => $oldImagePath]);
        }
        if ($section === 'products') {
            $item->update(['area' => Area::find($item->area_id)?->nama]);
        }

        return redirect()->route('master.index', $section)->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(string $section, string $record): RedirectResponse
    {
        $this->authorizeAdmin();
        $model = $this->modelFor($section);
        $item = $model::findOrFail($record);

        if ($section === 'accounts') {
            abort_if((int) $item->id === (int) auth()->id(), 422, 'Anda tidak dapat menghapus akun yang sedang digunakan.');
            abort_if($item->role === 'admin' && User::where('role', 'admin')->count() <= 1, 422, 'Setidaknya satu akun admin harus tetap ada.');
        }
        if ($section === 'areas') {
            abort_if($item->children()->exists(), 422, 'Pindahkan atau hapus area/line di dalam folder ini terlebih dahulu.');
        }

        $imagePath = in_array($section, ['products', 'visual-standards'], true) ? $item->image_path : null;
        $item->delete();
        if ($imagePath && ! Storage::disk('local')->delete($imagePath)) {
            return redirect()->route('master.index', $section)
                ->with('warning', 'Data berhasil dihapus, tetapi gambar point-check gagal dibersihkan dari penyimpanan.');
        }

        return redirect()->route('master.index', $section)->with('success', 'Data berhasil dihapus.');
    }

    private function modelFor(string $section): string
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        return self::SECTIONS[$section]['model'];
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    private function validateStandardProductFilters(Request $request, int|string $productId): void
    {
        $filters = $request->validate([
            'area_id' => ['sometimes', 'required', 'string', 'max:30'],
            'product_type' => ['sometimes', 'required', 'string', 'max:120'],
        ]);
        $product = Product::findOrFail($productId);
        $selectedAreaId = ($filters['area_id'] ?? null) === '__none__' ? null : ($filters['area_id'] ?? null);

        if (array_key_exists('area_id', $filters)) {
            if ($selectedAreaId !== null && (! ctype_digit((string) $selectedAreaId) || ! Area::whereKey($selectedAreaId)->exists())) {
                throw ValidationException::withMessages([
                    'area_id' => 'Line yang dipilih tidak terdaftar.',
                ]);
            }
            if ((string) ($product->area_id ?? '') !== (string) ($selectedAreaId ?? '')) {
                throw ValidationException::withMessages([
                    'product_id' => 'Part yang dipilih tidak terdaftar pada line yang dipilih.',
                ]);
            }
        }

        if (array_key_exists('product_type', $filters)) {
            $selectedType = $filters['product_type'] === '__none__' ? null : $filters['product_type'];
            $productType = filled($product->tipe) ? $product->tipe : null;
            if ($productType !== $selectedType) {
                throw ValidationException::withMessages([
                    'product_id' => 'Tipe part tidak sesuai dengan produk yang dipilih.',
                ]);
            }
        }
    }

    private function validatedData(Request $request, string $section, ?Model $item = null): array
    {
        $uniqueCode = 'required|string|max:100|unique:products,kode'.($item ? ','.$item->getKey() : '');

        return match ($section) {
            'products' => $request->validate([
                'kode' => $uniqueCode,
                'nama' => ['required', 'string', 'max:255'],
                'tipe' => ['nullable', 'string', 'max:120'],
                'area_id' => ['nullable', 'exists:areas,id'],
                'point_check_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
            ], array_merge($this->pointCheckImageMessages(), [
                'kode.unique' => 'Kode produk sudah terdaftar. Gunakan kode produk yang berbeda.',
            ])),
            'areas' => $request->validate([
                'nama' => ['required', 'string', 'max:120', 'unique:areas,nama'.($item ? ','.$item->getKey() : '')],
                'keterangan' => ['nullable', 'string', 'max:255'],
                'parent_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('areas', 'id')->whereNull('parent_id'),
                    Rule::notIn($item ? [$item->getKey()] : []),
                ],
            ]),
            'inspectors' => $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'dept' => ['nullable', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:255', Rule::unique('inspectors', 'email')->ignore($item?->getKey())],
            ]),
            'defects' => $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'tipe' => ['required', 'in:Dimensi,Visual,Fungsi'],
                'kategori' => ['required', 'in:Minor,Mayor,Kritis'],
            ]),
            'dimension-standards' => $request->validate([
                'product_id' => ['required', 'exists:products,id'],
                'parameter' => ['required', 'string', 'max:255'],
                'nilai_standar' => ['required', 'numeric', 'between:-99999999,99999999'],
                'toleransi_min' => ['required', 'numeric', 'min:0'],
                'toleransi_max' => ['required', 'numeric', 'min:0'],
                'satuan' => ['required', 'string', 'max:30'],
            ]),
            'visual-standards' => $request->validate([
                'product_id' => ['required', 'exists:products,id'],
                'checklist' => ['required', 'string', 'max:255'],
                'point_check_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ], [
                'point_check_image.image' => 'File point check harus berupa gambar JPG, PNG, atau WebP yang valid.',
                'point_check_image.mimes' => 'Format gambar point check harus JPG, PNG, atau WebP.',
                'point_check_image.max' => 'Ukuran gambar point check maksimal 5 MB.',
            ]),
            'function-standards' => $request->validate([
                'product_id' => ['required', 'exists:products,id'],
                'checklist' => ['required', 'string', 'max:255'],
            ]),
            default => abort(404),
        };
    }

    private function pointCheckImageMessages(): array
    {
        return [
            'point_check_image.image' => 'File point check harus berupa gambar JPG, PNG, atau WebP yang valid.',
            'point_check_image.mimes' => 'Format gambar point check harus JPG, PNG, atau WebP.',
            'point_check_image.max' => 'Ukuran gambar point check maksimal 25 MB.',
        ];
    }
}
