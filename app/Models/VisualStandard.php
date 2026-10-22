<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisualStandard extends Model
{
    protected $fillable = ['product_id', 'checklist', 'image_path'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
