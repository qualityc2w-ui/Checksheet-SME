<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['kode', 'nama', 'tipe', 'area', 'area_id', 'image_path'];

    public function areaRelation(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function dimensionStandards(): HasMany
    {
        return $this->hasMany(DimensionStandard::class);
    }

    public function visualStandards(): HasMany
    {
        return $this->hasMany(VisualStandard::class);
    }

    public function functionStandards(): HasMany
    {
        return $this->hasMany(FunctionStandard::class);
    }
}
