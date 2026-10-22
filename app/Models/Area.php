<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = ['nama', 'keterangan', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
