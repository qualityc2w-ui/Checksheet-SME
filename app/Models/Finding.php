<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    public const STATUSES = ['open', 'scheduled', 'done', 'verified', 'rejected'];

    public const STATUS_LABELS = [
        'open' => 'Open',
        'scheduled' => 'Dijadwalkan',
        'done' => 'Selesai',
        'verified' => 'Diverifikasi',
        'rejected' => 'Ditolak',
    ];

    public const CATEGORIES = [
        'man' => 'Man',
        'method' => 'Method',
        'machine' => 'Machine',
        'material' => 'Material',
    ];

    public const PRIORITIES = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
    ];

    protected $fillable = [
        'code', 'title', 'part_name', 'part_type', 'found_date', 'shift', 'area_line',
        'problem_type', 'description', 'category', 'priority', 'analysis_man',
        'analysis_method', 'analysis_machine', 'analysis_material', 'root_cause',
        'pic_id', 'status', 'response_token', 'due_date', 'pic_plan', 'responded_at',
        'completion_note', 'problem_photo_path', 'completion_photo_path', 'completed_at',
        'verified_at', 'verification_note', 'email_sent_at', 'reporter_id', 'reporter_name',
        'inspection_id', 'inspection_section', 'inspection_item_index', 'point_check',
        'observed_value', 'standard_value', 'minimum_value', 'maximum_value', 'unit',
    ];

    protected function casts(): array
    {
        return [
            'found_date' => 'date',
            'due_date' => 'date',
            'responded_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(FindingPic::class, 'pic_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && ! in_array($this->status, ['verified', 'done'], true)
            && $this->due_date->isBefore(today());
    }

    public function responseUrl(): string
    {
        return route('findings.respond', $this->response_token);
    }

}
