<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_PRINTER = 'printer';
    public const ROLE_SALES = 'sales';
    public const ROLE_DELIVERY = 'delivery';

    public const ROLES = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_EDITOR => 'Editor',
        self::ROLE_PRINTER => 'Printer',
        self::ROLE_SALES => 'Sales',
        self::ROLE_DELIVERY => 'Delivery',
    ];

    /** Roles that can be assigned when creating a new user (Admin is created via seeder only). */
    public const ROLES_FOR_CREATE = [
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_EDITOR => 'Editor',
        self::ROLE_PRINTER => 'Printer',
        self::ROLE_SALES => 'Sales',
        self::ROLE_DELIVERY => 'Delivery',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function assignedJobs(): HasMany
    {
        return $this->hasMany(Job::class, 'assigned_editor_id');
    }

    /** Jobs this user is assigned to as editor (many-to-many) */
    public function editedJobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'job_editor', 'user_id', 'studio_job_id')->withTimestamps();
    }

    /** Jobs this user dismissed (don't want to edit). */
    public function dismissedJobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'job_dismissals', 'user_id', 'studio_job_id')->withTimestamps();
    }

    /** Categories (by source_category_id) this editor is allowed to edit. Empty = can edit all categories. */
    public function editorCategories(): HasMany
    {
        return $this->hasMany(EditorCategory::class);
    }

    /** Source category IDs this editor can edit. Empty array = no restriction (edit all). */
    public function assignedCategoryIds(): array
    {
        if (! $this->relationLoaded('editorCategories')) {
            $this->load('editorCategories');
        }
        return $this->editorCategories->pluck('source_category_id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** Whether this user can edit a specific job item (edit status / print status). Admin/Manager: all; Editors: must be assigned to the job and (if they have categories) item must be in their categories. */
    public function canEditJobItem(JobEdit $edit): bool
    {
        if (in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true)) {
            return true;
        }
        if (! $this->isEditor()) {
            return false;
        }
        $job = $edit->job ?? \App\Models\Job::find($edit->studio_job_id);
        if (! $job) {
            return false;
        }
        $job->loadMissing('editors');
        $isAssignedToJob = $job->assigned_editor_id === $this->id || $job->editors->contains('id', $this->id);
        if (! $isAssignedToJob) {
            return false;
        }
        $allowedIds = $this->assignedCategoryIds();
        if (empty($allowedIds)) {
            return true;
        }
        $itemCategoryId = $edit->source_category_id ? (int) $edit->source_category_id : null;
        if ($itemCategoryId === null) {
            return true;
        }
        return in_array($itemCategoryId, $allowedIds, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isEditor(): bool
    {
        return $this->role === self::ROLE_EDITOR;
    }

    public function isPrinter(): bool
    {
        return $this->role === self::ROLE_PRINTER;
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    public function isDelivery(): bool
    {
        return $this->role === self::ROLE_DELIVERY;
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canManageJobs(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_EDITOR], true);
    }

    public function canTakeJob(): bool
    {
        return $this->isEditor();
    }

    /** Update print status: only Admin + Printer accounts. */
    public function canUpdatePrintStatus(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_PRINTER], true);
    }

    /** Can add/remove editors on a job: Admin/Manager always; Editor only if they are already on the job. */
    public function canAddOrRemoveEditorsOn(Job $job): bool
    {
        if (in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true)) {
            return true;
        }
        if ($this->isEditor()) {
            $job->loadMissing('editors');
            return $job->editors->contains('id', $this->id) || $job->assigned_editor_id === $this->id;
        }
        return false;
    }

    public function canDeliver(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_SALES, self::ROLE_DELIVERY], true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
