<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPosApplyHistory extends Model
{
    protected $table = 'job_pos_apply_histories';

    protected $fillable = [
        'studio_job_id',
        'applied_by',
        'applied_at',
        'pos_sale_created_at',
        'pos_sale_updated_at',
        'summary',
        'previous_lines',
        'new_lines',
        'added',
        'removed',
        'changed',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'pos_sale_created_at' => 'datetime',
            'pos_sale_updated_at' => 'datetime',
            'previous_lines' => 'array',
            'new_lines' => 'array',
            'added' => 'array',
            'removed' => 'array',
            'changed' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'studio_job_id');
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
