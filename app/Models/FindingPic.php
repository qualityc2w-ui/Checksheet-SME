<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FindingPic extends Model
{
    protected $fillable = ['name', 'department', 'email', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'pic_id');
    }
}
