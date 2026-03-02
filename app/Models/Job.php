<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    use HasFactory;

    protected $table = 'studio_jobs';

    protected $fillable = [
        'ref_number',
        'source_id',
        'customer_name',
        'notes',
        'due_date',
        'is_active',
        'status',
        'blocked_category_ids',
        'assigned_editor_id',
        'delivered_at',
        'delivery_method',
        'delivered_by',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'due_date' => 'date',
            'is_active' => 'boolean',
            'blocked_category_ids' => 'array',
        ];
    }

    public const STATUS_NEW = 'new';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_LABELS = [
        self::STATUS_NEW => 'New',
        self::STATUS_ASSIGNED => 'Assigned',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_DELIVERED => 'Delivered',
    ];

    public const DELIVERY_ONLINE = 'online';
    public const DELIVERY_WALKIN = 'walkin';
    public const DELIVERY_COURIER = 'courier';

    public function edits(): HasMany
    {
        return $this->hasMany(JobEdit::class, 'studio_job_id')->orderBy('sort_order');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_editor_id');
    }

    /** Multiple editors assigned to this job (pivot job_editor) */
    public function editors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_editor', 'studio_job_id', 'user_id')->withTimestamps();
    }

    public function deliveredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /** Users who dismissed this job (don't want to edit). */
    public function dismissedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_dismissals', 'studio_job_id', 'user_id')->withTimestamps();
    }

    /** Whether items in this category (source_category_id) should be hidden/blocked for this job. */
    public function isCategoryBlocked($sourceCategoryId): bool
    {
        $ids = $this->blocked_category_ids ?? [];
        return in_array((int) $sourceCategoryId, array_map('intval', $ids), true);
    }

    public function isDismissedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if (! $this->relationLoaded('dismissedByUsers')) {
            $this->load('dismissedByUsers');
        }
        return $this->dismissedByUsers->contains('id', $user->id);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_DELIVERED;
    }

    public function allEditsCompleted(): bool
    {
        return $this->edits->every(fn (JobEdit $e) => $e->edit_status === JobEdit::EDIT_STATUS_COMPLETED);
    }
}
