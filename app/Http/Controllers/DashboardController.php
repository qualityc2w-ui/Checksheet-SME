<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\DimensionStandard;
use App\Models\Inspection;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = Carbon::today();
        $products = Product::with('areaRelation')->orderBy('kode')->get();
        $areas = Area::with('parent')->orderBy('nama')->get();
        $types = Product::query()->whereNotNull('tipe')->where('tipe', '!=', '')
            ->distinct()->orderBy('tipe')->pluck('tipe');
        $filters = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'product_type' => ['nullable', 'string', 'max:120'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'parameter' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'shift' => ['nullable', 'in:Shift 1,Shift 2'],
            'result' => ['nullable', 'in:OK,NG'],
        ]);
        $from = Carbon::parse($filters['from'] ?? $today->copy()->subDays(29)->toDateString());
        $to = Carbon::parse($filters['to'] ?? $today->toDateString());
        $filters['from'] = $from->toDateString();
        $filters['to'] = $to->toDateString();

        $selectedProduct = isset($filters['product_id'])
            ? $products->firstWhere('id', (int) $filters['product_id'])
            : null;
        if ($selectedProduct) {
            $filters['product_type'] = $selectedProduct->tipe ?: null;
            $filters['area_id'] = $selectedProduct->area_id;
        }

        $selectedArea = isset($filters['area_id']) ? $areas->firstWhere('id', (int) $filters['area_id']) : null;
        $buildingForProduct = fn (Product $product) => $areas->firstWhere('id', (int) $product->area_id)?->parent_id;
        $productMatchesArea = function (Product $product) use ($selectedArea, $buildingForProduct): bool {
            return ! $selectedArea
                || (string) $product->area_id === (string) $selectedArea->id
                || ($selectedArea->parent_id === null
                    && (string) $buildingForProduct($product) === (string) $selectedArea->id);
        };
        $productMatchesType = fn (Product $product): bool => ! ($filters['product_type'] ?? null)
            || $product->tipe === $filters['product_type'];
        $areaCompatibleProducts = $products->filter($productMatchesArea);
        $typeCompatibleProducts = $products->filter($productMatchesType);
        $chartProducts = $products->filter(function (Product $product) use ($productMatchesArea, $productMatchesType) {
            return $productMatchesArea($product) && $productMatchesType($product);
        })->values();
        $chartTypes = $areaCompatibleProducts->pluck('tipe')->filter()->unique()->sort()->values();
        $chartAreaIds = $typeCompatibleProducts
            ->flatMap(fn (Product $product) => [$product->area_id, $buildingForProduct($product)])
            ->filter()
            ->unique();
        $chartAreas = $areas->filter(fn (Area $area) => $chartAreaIds->contains($area->id))->values();
        $chartProductFilterData = $products->map(fn (Product $product) => [
            'id' => (string) $product->id,
            'areaId' => (string) ($product->area_id ?? ''),
            'buildingId' => (string) ($buildingForProduct($product) ?? ''),
            'type' => (string) ($product->tipe ?? ''),
        ])->values();

        $chartProductId = $filters['product_id'] ?? $chartProducts->first()?->id;
        $standards = $chartProductId
            ? DimensionStandard::where('product_id', $chartProductId)->orderBy('parameter')->get()
            : collect();
        $standard = $standards->firstWhere('parameter', $filters['parameter'] ?? null) ?? $standards->first();
        $measurements = $standard
            ? $this->filteredInspections($filters, $from, $to)
                ->where('product_id', $chartProductId)
                ->orderBy('tanggal')
                ->orderBy('id')
                ->get()
                ->map(function (Inspection $inspection) use ($standard) {
                    $measurement = collect($inspection->dimensi['detail'] ?? [])
                        ->firstWhere('parameter', $standard->parameter);

                    return $measurement ? [
                        'tanggal' => $inspection->tanggal->toDateString(),
                        'value' => (float) $measurement['nilai'],
                    ] : null;
                })
                ->filter()
                ->groupBy('tanggal')
                ->map(function ($dailyMeasurements, string $date) use ($standard) {
                    $average = $dailyMeasurements->avg('value');
                    $lowerLimit = (float) $standard->nilai_standar - (float) $standard->toleransi_min;
                    $upperLimit = (float) $standard->nilai_standar + (float) $standard->toleransi_max;

                    return [
                        'tanggal' => Carbon::parse($date)->format('d/m'),
                        'average' => $average,
                        'count' => $dailyMeasurements->count(),
                        'result' => $average < $lowerLimit || $average > $upperLimit ? 'NG' : 'OK',
                    ];
                })
                ->values()
            : collect();

        $lowerLimit = $standard
            ? (float) $standard->nilai_standar - (float) $standard->toleransi_min
            : 0;
        $upperLimit = $standard
            ? (float) $standard->nilai_standar + (float) $standard->toleransi_max
            : 0;
        $chartPoints = collect();
        $lowerY = $upperY = 240;
        if ($measurements->isNotEmpty() && $standard) {
            $values = $measurements->pluck('average')->all();
            $minimum = min(array_merge($values, [$lowerLimit]));
            $maximum = max(array_merge($values, [$upperLimit]));
            $padding = max(($maximum - $minimum) * 0.12, max(abs($minimum), abs($maximum), 1) * 0.01);
            $minimum -= $padding;
            $maximum += $padding;
            $yFor = fn (float $value): float => 30 + (210 * ($maximum - $value) / ($maximum - $minimum));
            $lowerY = $yFor($lowerLimit);
            $upperY = $yFor($upperLimit);
            $chartPoints = $measurements->map(function (array $point, int $index) use ($measurements, $yFor) {
                $point['x'] = $measurements->count() === 1 ? 520 : 70 + (900 * $index / ($measurements->count() - 1));
                $point['y'] = $yFor($point['average']);

                return $point;
            });
        }

        $scope = fn (): Builder => $this->filteredInspections($filters, $from, $to);
        $totalCount = $scope()->count();
        $passCount = (clone $scope())->where('hasil_akhir', 'OK')->count();
        $ngCount = (clone $scope())->where('hasil_akhir', 'NG')->count();
        $passPercentage = $totalCount > 0 ? round($passCount / $totalCount * 100, 1) : 0;
        $dimensionOutcomes = $scope()->get(['dimensi'])
            ->flatMap(fn (Inspection $inspection) => collect($inspection->dimensi['detail'] ?? [])
                ->filter(fn ($measurement) => is_array($measurement)
                    && in_array($measurement['hasil'] ?? null, ['OK', 'NG'], true))
                ->pluck('hasil'));
        $dimensionMeasurementCount = $dimensionOutcomes->count();
        $dimensionOkCount = $dimensionOutcomes->filter(fn (string $result) => $result === 'OK')->count();
        $dimensionNgCount = $dimensionOutcomes->filter(fn (string $result) => $result === 'NG')->count();
        $dimensionAccuracy = $dimensionMeasurementCount > 0
            ? round($dimensionOkCount / $dimensionMeasurementCount * 100, 1)
            : 0;

        $recent = $scope()->with(['product', 'inspector'])
            ->latest('tanggal')
            ->latest()
            ->limit(8)
            ->get();

        $dailyEnd = min($to->copy()->startOfDay(), $today->copy()->startOfDay());
        $dailyStart = max($from->copy()->startOfDay(), $dailyEnd->copy()->subDays(13));
        $dailyCounts = $dailyStart->greaterThan($dailyEnd)
            ? collect()
            : $scope()
                ->whereBetween('tanggal', [$dailyStart->toDateString(), $dailyEnd->toDateString()])
                ->select(
                    'tanggal',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN hasil_akhir = 'OK' THEN 1 ELSE 0 END) as ok"),
                    DB::raw("SUM(CASE WHEN hasil_akhir = 'NG' THEN 1 ELSE 0 END) as ng")
                )
                ->groupBy('tanggal')
                ->get()
                ->keyBy(fn (Inspection $inspection) => $inspection->tanggal->toDateString());
        $daily = collect(range(13, 0))->map(function (int $daysAgo) use ($dailyEnd, $dailyStart, $from, $to, $dailyCounts) {
            $date = $dailyEnd->copy()->subDays($daysAgo);
            $included = $date->betweenIncluded($dailyStart, $dailyEnd)
                && $date->betweenIncluded($from->copy()->startOfDay(), $to->copy()->startOfDay());
            $counts = $dailyCounts->get($date->toDateString());

            return [
                'label' => $date->format('d/m'),
                'total' => $included ? (int) ($counts?->total ?? 0) : 0,
                'ok' => $included ? (int) ($counts?->ok ?? 0) : 0,
                'ng' => $included ? (int) ($counts?->ng ?? 0) : 0,
            ];
        });

        $partMonitoring = $scope()
            ->select('product_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN hasil_akhir = 'NG' THEN 1 ELSE 0 END) as ng"))
            ->with('product')
            ->groupBy('product_id')
            ->get()
            ->sortByDesc('total')
            ->take(8)
            ->map(fn (Inspection $inspection) => [
                'label' => $inspection->product?->kode ?? 'Produk dihapus',
                'name' => $inspection->product?->nama ?? '',
                'type' => $inspection->product?->tipe ?: 'Tanpa tipe',
                'total' => (int) $inspection->total,
                'ng' => (int) $inspection->ng,
            ])->values();

        $typeMonitoring = $scope()
            ->join('products as monitored_products', 'inspections.product_id', '=', 'monitored_products.id')
            ->selectRaw("COALESCE(NULLIF(monitored_products.tipe, ''), 'Tanpa tipe') as label, COUNT(*) as total, SUM(CASE WHEN inspections.hasil_akhir = 'NG' THEN 1 ELSE 0 END) as ng")
            ->groupBy('monitored_products.tipe')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
                'ng' => (int) $row->ng,
            ]);

        return view('dashboard', [
            'productCount' => $products->count(),
            'todayCount' => $scope()->whereDate('tanggal', $today)->count(),
            'passCount' => $passCount,
            'ngCount' => $ngCount,
            'totalCount' => $totalCount,
            'passPercentage' => $passPercentage,
            'dimensionMeasurementCount' => $dimensionMeasurementCount,
            'dimensionOkCount' => $dimensionOkCount,
            'dimensionNgCount' => $dimensionNgCount,
            'dimensionAccuracy' => $dimensionAccuracy,
            'recent' => $recent,
            'daily' => $daily,
            'products' => $products,
            'productTypes' => $types,
            'areas' => $areas,
            'chartProducts' => $chartProducts,
            'chartTypes' => $chartTypes,
            'chartAreas' => $chartAreas,
            'chartProductFilterData' => $chartProductFilterData,
            'standards' => $standards,
            'standard' => $standard,
            'chartProductId' => $chartProductId,
            'chartPoints' => $chartPoints,
            'partMonitoring' => $partMonitoring,
            'typeMonitoring' => $typeMonitoring,
            'lowerLimit' => $lowerLimit,
            'upperLimit' => $upperLimit,
            'lowerY' => $lowerY,
            'upperY' => $upperY,
            'chartFilters' => array_merge($filters, [
                'product_id' => $filters['product_id'] ?? '',
                'parameter' => $standard?->parameter,
            ]),
        ]);
    }

    private function filteredInspections(array $filters, Carbon $from, Carbon $to): Builder
    {
        return Inspection::visibleTo(request()->user())
            ->whereDate('inspections.tanggal', '>=', $from->toDateString())
            ->whereDate('inspections.tanggal', '<=', $to->toDateString())
            ->when($filters['product_id'] ?? null, fn (Builder $query, $productId) => $query->where('inspections.product_id', $productId))
            ->when($filters['product_type'] ?? null, fn (Builder $query, $type) => $query->whereHas('product', fn (Builder $productQuery) => $productQuery->where('tipe', $type)))
            ->when($filters['area_id'] ?? null, function (Builder $query, $areaId) {
                $area = Area::with('children')->find($areaId);
                $areaIds = $area && $area->parent_id === null
                    ? $area->children->modelKeys()
                    : [];
                $areaIds[] = $areaId;

                $query->whereIn('inspections.area_id', $areaIds);
            })
            ->when($filters['shift'] ?? null, fn (Builder $query, $shift) => $query->where('inspections.shift', $shift))
            ->when($filters['result'] ?? null, fn (Builder $query, $result) => $query->where('inspections.hasil_akhir', $result));
    }
}
