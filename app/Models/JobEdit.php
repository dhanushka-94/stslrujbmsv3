<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobEdit extends Model
{
    use HasFactory;

    protected $fillable = [
        'studio_job_id',
        'claimed_by_user_id',
        'name',
        'source_product_id',
        'category_name',
        'subcategory_name',
        'source_category_id',
        'sort_order',
        'edit_status',
        'print_status',
        'completed_at',
        'customer_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
        ];
    }

    public function claimedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function isCustomerConfirmed(): bool
    {
        return $this->customer_confirmed_at !== null;
    }

    public const EDIT_STATUS_PENDING = 'pending';
    public const EDIT_STATUS_IN_PROGRESS = 'in_progress';
    public const EDIT_STATUS_COMPLETED = 'completed';

    public const PRINT_STATUS_NOT_REQUIRED = 'not_required';
    public const PRINT_STATUS_PENDING = 'pending';
    public const PRINT_STATUS_SENT_TO_PRINT = 'sent_to_print';
    public const PRINT_STATUS_PRINTED = 'printed';

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'studio_job_id');
    }
}
