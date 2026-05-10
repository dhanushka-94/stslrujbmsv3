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
    /** Editor only: editing job items, no print status. */
    public const ROLE_EDITOR = 'editor';
    /** Editor + Printer: editing and updating print status. */
    public const ROLE_EDITOR_PRINTER = 'editor_printer';
    /** Editor + Printer + Framing: full editing, print status, and framing done. */
    public const ROLE_EDITOR_PRINTER_FRAMING = 'editor_printer_framing';
    public const ROLE_PRINTER = 'printer';
    /** Printer + Framing: update print status and mark framing done (no separate editor-only role). */
    public const ROLE_PRINTER_FRAMING = 'printer_framing';
    public const ROLE_FRAMING = 'framing';
    public const ROLE_SALES = 'sales';
    public const ROLE_DELIVERY = 'delivery';

    public const ROLES = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_EDITOR => 'Editor',
        self::ROLE_EDITOR_PRINTER => 'Editor + Printer',
        self::ROLE_EDITOR_PRINTER_FRAMING => 'Editor + Printer + Framing',
        self::ROLE_PRINTER => 'Printer',
        self::ROLE_PRINTER_FRAMING => 'Printer + Framing',
        self::ROLE_FRAMING => 'Framing (Photo Framing)',
        self::ROLE_SALES => 'Sales',
        self::ROLE_DELIVERY => 'Delivery',
    ];

    /** Roles that can be assigned when creating a new user (Admin is created via seeder only). */
    public const ROLES_FOR_CREATE = [
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_EDITOR => 'Editor',
        self::ROLE_EDITOR_PRINTER => 'Editor + Printer',
        self::ROLE_EDITOR_PRINTER_FRAMING => 'Editor + Printer + Framing',
        self::ROLE_PRINTER => 'Printer',
        self::ROLE_PRINTER_FRAMING => 'Printer + Framing',
        self::ROLE_FRAMING => 'Framing (Photo Framing)',
        self::ROLE_SALES => 'Sales',
        self::ROLE_DELIVERY => 'Delivery',
    ];

    /** Roles that can have POS category restrictions for job items. */
    public static function rolesWithCategoryAssignments(): array
    {
        return [
            self::ROLE_EDITOR,
            self::ROLE_EDITOR_PRINTER,
            self::ROLE_EDITOR_PRINTER_FRAMING,
            self::ROLE_FRAMING,
            self::ROLE_PRINTER_FRAMING,
        ];
    }

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
            'job_pool_last_checked_at' => 'datetime',
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

    /**
     * Photo-editing workflow on a line (claim, edit status, customer steps, edit done): editors only.
     * Admin/Manager: all non-FRAME lines. Printers / dedicated framers use print & framing actions only.
     */
    public function canEditJobItem(JobEdit $edit): bool
    {
        if (in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true)) {
            return true;
        }
        if ($edit->isFrameCategoryLine()) {
            return false;
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

    /**
     * Admin/Manager may set or change estimated minutes anytime. Other roles may set them only while the line still has no estimate (first time).
     */
    public function canSetOrChangeJobEditEstimatedMinutes(JobEdit $edit): bool
    {
        if ($this->isAdmin() || $this->isManager()) {
            return true;
        }

        return $edit->estimated_minutes === null;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /** True if user can do editor work (Editor or Editor + Printer). */
    public function isEditor(): bool
    {
        return in_array($this->role, [self::ROLE_EDITOR, self::ROLE_EDITOR_PRINTER, self::ROLE_EDITOR_PRINTER_FRAMING], true);
    }

    /** Editor or Framing (for POS category filters, legacy checks). Prefer isEditor() for photo-edit permissions. */
    public function isEditorOrFraming(): bool
    {
        return $this->isEditor() || $this->isFraming();
    }

    /**
     * Category restrictions for the job line table: editors + dedicated framers (not printer-only).
     */
    public function scopedCategoryIdsForJobLineTable(): array
    {
        if ($this->isEditor() || ($this->isFraming() && ! $this->isEditor())) {
            return $this->assignedCategoryIds();
        }

        return [];
    }

    /** Users who may be attached to a job as photo editors (pivot job_editor). */
    public static function rolesAssignableAsJobEditors(): array
    {
        return [
            self::ROLE_EDITOR,
            self::ROLE_EDITOR_PRINTER,
            self::ROLE_EDITOR_PRINTER_FRAMING,
        ];
    }

    public function canDismissNewJobs(): bool
    {
        return $this->isEditor() || $this->isAdmin() || $this->isManager();
    }

    /** True if user has Editor + Printer role (can update print status). */
    public function isEditorPrinter(): bool
    {
        return in_array($this->role, [self::ROLE_EDITOR_PRINTER, self::ROLE_EDITOR_PRINTER_FRAMING], true);
    }

    public function isPrinter(): bool
    {
        return $this->role === self::ROLE_PRINTER;
    }

    public function isFraming(): bool
    {
        return in_array($this->role, [self::ROLE_FRAMING, self::ROLE_EDITOR_PRINTER_FRAMING, self::ROLE_PRINTER_FRAMING], true);
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    public function isDelivery(): bool
    {
        return $this->role === self::ROLE_DELIVERY;
    }

    /**
     * Printer / framing-only roles: Job Pool lists existing jobs with print or framing queue lines only (not all POS sales).
     * Users who can edit (editor, editor_printer, editor_printer_framing) keep the full POS pool.
     */
    public function usesDedicatedPrintFramingJobPool(): bool
    {
        return in_array($this->role, [
            self::ROLE_PRINTER,
            self::ROLE_FRAMING,
            self::ROLE_PRINTER_FRAMING,
        ], true);
    }

    public function jobPoolShowsPrintQueue(): bool
    {
        return in_array($this->role, [self::ROLE_PRINTER, self::ROLE_PRINTER_FRAMING], true);
    }

    public function jobPoolShowsFramingQueue(): bool
    {
        return in_array($this->role, [self::ROLE_FRAMING, self::ROLE_PRINTER_FRAMING], true);
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canManageJobs(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_EDITOR,
            self::ROLE_EDITOR_PRINTER,
            self::ROLE_EDITOR_PRINTER_FRAMING,
        ], true);
    }

    /** Only photo editors “take” jobs from New; printers/framers open work from Job Pool / jobs list. */
    public function canTakeJob(): bool
    {
        return $this->isEditor();
    }

    /** Update print status: Admin, Printer, Editor + Printer, composite printer roles. */
    public function canUpdatePrintStatus(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN,
            self::ROLE_PRINTER,
            self::ROLE_EDITOR_PRINTER,
            self::ROLE_EDITOR_PRINTER_FRAMING,
            self::ROLE_PRINTER_FRAMING,
        ], true);
    }

    /**
     * Whether this user may set print status on a line (matches job detail print controls + bulk rules).
     * Dedicated printer roles do not need editor assignment; editors still follow category/job rules.
     */
    public function canApplyPrintStatusToJobEdit(JobEdit $edit): bool
    {
        if ($edit->isFrameCategoryLine() || ! $edit->edit_done_at) {
            return false;
        }
        if ($this->isAdmin() || $this->isManager()) {
            return true;
        }
        if (! $this->canUpdatePrintStatus()) {
            return false;
        }
        if ($this->canEditJobItem($edit)) {
            return true;
        }

        return in_array($this->role, [self::ROLE_PRINTER, self::ROLE_PRINTER_FRAMING], true);
    }

    /** Can add/remove editors on a job: Admin and Manager only. */
    public function canAddOrRemoveEditorsOn(?Job $job = null): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }

    /** Delivery permission: Sales and Delivery roles (Admin/Manager also allowed for override). */
    public function canDeliver(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_SALES, self::ROLE_DELIVERY], true);
    }

    /** Mark framing complete: Framing or Admin, after print status is Printed (per line item). */
    public function canMarkFramingDone(JobEdit $edit): bool
    {
        if (! $this->isFraming() && ! $this->isAdmin()) {
            return false;
        }
        if ($edit->framing_done_at !== null) {
            return false;
        }
        $allowedIds = $this->assignedCategoryIds();
        if (! empty($allowedIds)) {
            $itemCategoryId = $edit->source_category_id ? (int) $edit->source_category_id : null;
            if ($itemCategoryId !== null && ! in_array($itemCategoryId, $allowedIds, true)) {
                return false;
            }
        }
        if ($edit->isFrameCategoryLine()) {
            return true;
        }

        return $edit->print_status === JobEdit::PRINT_STATUS_PRINTED;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
