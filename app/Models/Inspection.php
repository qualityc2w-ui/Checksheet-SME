<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    protected $fillable = [
        'tanggal', 'product_id', 'inspector_id', 'user_id', 'shift', 'sesi', 'sample',
        'dimensi', 'visual', 'fungsi', 'hasil_akhir', 'approved', 'approved_by', 'approved_at',
        'evidence', 'area_id', 'email_sent_at', 'measurement_mode',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'dimensi' => 'array',
            'visual' => 'array',
            'fungsi' => 'array',
            'approved' => 'boolean',
            'approved_at' => 'datetime',
            'evidence' => 'array',
            'email_sent_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Inspector::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin()
            ? $query
            : $query->where('user_id', $user->id);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function ngPointChecks(): array
    {
        $this->loadMissing(['product']);
        $productId = $this->product_id;
        $standardIds = [
            'dimensions' => DimensionStandard::where('product_id', $productId)->orderBy('id')->pluck('id')->all(),
            'visual' => VisualStandard::where('product_id', $productId)->orderBy('id')->pluck('id')->all(),
            'function' => FunctionStandard::where('product_id', $productId)->orderBy('id')->pluck('id')->all(),
        ];
        $labels = [
            'dimensions' => 'Dimensi tidak sesuai',
            'visual' => 'Hasil pemeriksaan visual NG',
            'function' => 'Hasil pemeriksaan fungsi NG',
        ];
        $items = [];

        foreach ($standardIds as $section => $ids) {
            $inspectionSection = match ($section) {
                'dimensions' => 'dimensi',
                'function' => 'fungsi',
                default => $section,
            };
            $details = $this->{$inspectionSection}['detail'] ?? [];
            foreach ($details as $index => $detail) {
                if (($detail['hasil'] ?? null) !== 'NG') {
                    continue;
                }
                $itemId = (string) ($detail['item_id'] ?? ($ids[$index] ?? 'manual'));
                $items[] = [
                    'section' => $section,
                    'index' => $index,
                    'item_id' => $itemId,
                    'label' => $detail['parameter'] ?? $detail['checklist'] ?? 'Pengecekan manual',
                    'problem_type' => $labels[$section],
                    'observed_value' => (string) ($detail['nilai'] ?? 'NG'),
                    'standard_value' => isset($detail['standar']) ? (string) $detail['standar'] : null,
                    'minimum_value' => isset($detail['min']) ? (string) $detail['min'] : null,
                    'maximum_value' => isset($detail['max']) ? (string) $detail['max'] : null,
                    'unit' => $detail['satuan'] ?? null,
                    'evidence_path' => $this->evidence[$section][$itemId] ?? null,
                ];
            }
        }

        return $items;
    }

    public function pendingNgPointChecks(): array
    {
        $createdItems = $this->relationLoaded('findings')
            ? $this->findings->whereNotNull('inspection_section')
            : $this->findings()->whereNotNull('inspection_section')->get();
        $createdKeys = $createdItems
            ->map(fn (Finding $finding) => $finding->inspection_section.':'.$finding->inspection_item_index)
            ->all();

        return array_values(array_filter(
            $this->ngPointChecks(),
            fn (array $item) => ! in_array($item['section'].':'.$item['index'], $createdKeys, true),
        ));
    }

    public function getInspectorNameAttribute(): string
    {
        return $this->inspector?->nama ?? '—';
    }

}
