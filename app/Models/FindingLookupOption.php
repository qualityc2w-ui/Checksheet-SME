<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FindingLookupOption extends Model
{
    public const KINDS = [
        'area_line' => 'Area / Line',
        'part_name' => 'Nama part',
        'part_type' => 'Tipe part',
        'problem_type' => 'Jenis masalah',
    ];

    protected $fillable = ['kind', 'value', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
