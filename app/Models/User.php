<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Job;
use App\Support\CategoryWorkflow;
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

    /** Whether this role may have allowed POS categories assigned (all roles except Admin). */
    public static function roleMayHaveCategoryAssignments(?string $role): bool
    {
        return $role !== null && $role !== self::ROLE_ADMIN;
    }

    /** Roles that can have POS category restrictions (every role except Admin). */
    public static function rolesWithCategoryAssignments(): array
    {
        return array_values(array_filter(
            array_keys(self::ROLES),
            fn (string $role) => self::roleMayHaveCategoryAssignments($role)
        ));
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

    public function isAssignedToStudioJob(?Job $job): bool
    {
        if (! $job) {
            return false;
        }
        $job->loadMissing('editors');

        return $job->assigned_editor_id === $this->id
            || $job->editors->contains('id', $this->id);
    }

    public function categoryAllowsJobLine(JobEdit $edit): bool
    {
        $allowed = $this->scopedCategoryIdsForJobLineTable();
        if ($allowed === []) {
            return true;
        }
        $catId = $edit->source_category_id ? (int) $edit->source_category_id : null;
        if ($catId === null) {
            return true;
        }

        return in_array($catId, $allowed, true);
    }

    /**
     * Assigned to the job and allowed category (edit/print/done lines on that job).
     */
    public function canWorkOnAssignedJobLine(JobEdit $edit): bool
    {
        if (! $this->isEditor()) {
            return false;
        }
        $job = $edit->job ?? \App\Models\Job::find($edit->studio_job_id);
        if (! $this->isAssignedToStudioJob($job)) {
            return false;
        }

        if ($this->seesEditAndPrintLinesRegardlessOfCategoryAllowlist()
            && ($edit->needsEditWorkflow() || $edit->isPrintOnlyWorkflow())) {
            return true;
        }

        return $this->categoryAllowsJobLine($edit);
    }

    /**
     * Photo-editing workflow on a line (claim, edit status, customer steps, edit done): editors only.
     * Admin/Manager: edit/print lines only. Printers use print actions; framers use Done.
     */
    public function canEditJobItem(JobEdit $edit): bool
    {
        if (! $this->canModifyJobWorkflow()) {
            return false;
        }

        if (in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true)) {
            return $edit->needsEditWorkflow();
        }
        if (! $edit->needsEditWorkflow()) {
            return false;
        }

        return $this->canWorkOnAssignedJobLine($edit);
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

    /** Editor, Editor + Printer, or Editor + Printer + Framing — may open the time report for themselves only. */
    public function canViewOwnEditorTimeReport(): bool
    {
        return $this->isEditor();
    }

    /** Time report page: own data (editor roles) or all editors (admin/manager). */
    public function canViewEditorTimeReport(): bool
    {
        return $this->canViewAllEditorsTimeReport() || $this->canViewOwnEditorTimeReport();
    }

    /** All editors, filters, and cross-user totals — admin and manager only. */
    public function canViewAllEditorsTimeReport(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    /** User reports list and another user's activity report — admin and manager only. */
    public function canViewOtherUsersReports(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    /** @return list<string> Roles included in the admin/manager editor time report. */
    public static function rolesInEditorTimeReport(): array
    {
        return [
            self::ROLE_EDITOR,
            self::ROLE_EDITOR_PRINTER,
            self::ROLE_EDITOR_PRINTER_FRAMING,
        ];
    }

    /** Editor or Framing (for POS category filters, legacy checks). Prefer isEditor() for photo-edit permissions. */
    public function isEditorOrFraming(): bool
    {
        return $this->isEditor() || $this->isFraming();
    }

    /**
     * Category restrictions for job lines, Job Pool, and reports. Admin has no limit; empty list = all categories.
     */
    public function scopedCategoryIdsForJobLineTable(): array
    {
        if ($this->isAdmin() || $this->isSalesViewOnly()) {
            return [];
        }

        return $this->assignedCategoryIds();
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

    /** Editor + Printer only (not Editor + Printer + Framing). */
    public function isEditorPrinterOnly(): bool
    {
        return $this->role === self::ROLE_EDITOR_PRINTER;
    }

    public function isEditorPrinterFraming(): bool
    {
        return $this->role === self::ROLE_EDITOR_PRINTER_FRAMING;
    }

    /**
     * Editor + Printer + Framing always sees edit/print workflow lines; category allowlist only limits done-only lines.
     */
    public function seesEditAndPrintLinesRegardlessOfCategoryAllowlist(): bool
    {
        return $this->isEditorPrinterFraming();
    }

    /**
     * Whether this user should see/work this line on job detail, jobs list, and bulk actions.
     */
    public function isJobLineVisibleToMe(JobEdit $edit): bool
    {
        if ($edit->isGloballyHiddenFromStudioWorkflow()) {
            return false;
        }

        if ($this->isSalesViewOnly()) {
            return true;
        }

        if ($this->isEditorPrinterOnly()) {
            return $edit->needsEditWorkflow() || $edit->isPrintOnlyWorkflow();
        }

        if ($this->seesEditAndPrintLinesRegardlessOfCategoryAllowlist()
            && ($edit->needsEditWorkflow() || $edit->isPrintOnlyWorkflow())) {
            return true;
        }

        if ($this->isPrinter()) {
            return $edit->needsPrintWorkflow()
                && CategoryWorkflow::categoryMatchesUserJobPool($edit->category_name, $this)
                && $this->categoryAllowsJobLine($edit);
        }

        if ($this->role === self::ROLE_FRAMING) {
            return $edit->needsDoneWorkflow()
                && CategoryWorkflow::categoryMatchesUserJobPool($edit->category_name, $this)
                && $this->categoryAllowsJobLine($edit);
        }

        if ($this->role === self::ROLE_PRINTER_FRAMING) {
            return CategoryWorkflow::categoryMatchesUserJobPool($edit->category_name, $this)
                && $this->categoryAllowsJobLine($edit);
        }

        return $this->categoryAllowsJobLine($edit);
    }

    /**
     * Job Pool / POS row visibility for a category name (before a studio job exists).
     */
    public function isPosCategoryVisibleToMe(?string $categoryName, ?int $sourceCategoryId = null): bool
    {
        if ($this->isSalesViewOnly()) {
            return true;
        }

        if ($this->isEditorPrinterOnly()) {
            $profile = CategoryWorkflow::profileForCategoryName($categoryName);

            return in_array($profile, [
                CategoryWorkflow::PROFILE_EDIT_PRINT,
                CategoryWorkflow::PROFILE_PRINT_ONLY,
            ], true);
        }

        if ($this->seesEditAndPrintLinesRegardlessOfCategoryAllowlist()) {
            $profile = CategoryWorkflow::profileForCategoryName($categoryName);
            if (in_array($profile, [
                CategoryWorkflow::PROFILE_EDIT_PRINT,
                CategoryWorkflow::PROFILE_PRINT_ONLY,
            ], true)) {
                return true;
            }

            return $this->categoryIdAllowedForPos($sourceCategoryId);
        }

        if ($this->jobPoolShowsPrintQueue() || $this->jobPoolShowsFramingQueue()) {
            return CategoryWorkflow::categoryMatchesUserJobPool($categoryName, $this)
                && $this->categoryIdAllowedForPos($sourceCategoryId);
        }

        return $this->categoryIdAllowedForPos($sourceCategoryId);
    }

    public function categoryIdAllowedForPos(?int $sourceCategoryId): bool
    {
        $allowed = $this->scopedCategoryIdsForJobLineTable();
        if ($allowed === [] || $sourceCategoryId === null || $sourceCategoryId <= 0) {
            return true;
        }

        return in_array($sourceCategoryId, $allowed, true);
    }

    /** Roles that see a read-only list of every line item name (Job Pool + job detail footer). */
    public static function rolesWithFullJobItemNamesSummary(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_EDITOR,
            self::ROLE_EDITOR_PRINTER,
            self::ROLE_EDITOR_PRINTER_FRAMING,
            self::ROLE_PRINTER,
            self::ROLE_PRINTER_FRAMING,
        ];
    }

    /** Read-only full job line names (all edits), separate from per-line workflow visibility. */
    public function canSeeFullJobItemNamesSummary(): bool
    {
        return in_array($this->role, self::rolesWithFullJobItemNamesSummary(), true);
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

    /** Sales: view all line statuses; no edit, print, or Done actions — delivery only. */
    public function isSalesViewOnly(): bool
    {
        return $this->isSales();
    }

    /** Job Pool page/nav (Delivery cannot; Sales can view). */
    public function canAccessJobPool(): bool
    {
        return ! $this->isDeliveryViewOnly();
    }

    /** Sales nav: Jobs + Job Pool only (no Dashboard / Profile / Reports). */
    public function usesJobsAndJobPoolNavOnly(): bool
    {
        return $this->isSales();
    }

    /** Default landing route after login / home. */
    public function homeRouteName(): string
    {
        if ($this->usesJobsAndJobPoolNavOnly()) {
            return 'jobs.index';
        }

        return 'dashboard';
    }

    /** Delivery: completed jobs only; mark delivered — no edit/print/Done. */
    public function isDeliveryViewOnly(): bool
    {
        return $this->isDelivery();
    }

    /** Sales or Delivery counter staff — read-only on job workflow actions. */
    public function isCounterStaffViewOnly(): bool
    {
        return $this->isSalesViewOnly() || $this->isDeliveryViewOnly();
    }

    public function canModifyJobWorkflow(): bool
    {
        return ! $this->isCounterStaffViewOnly();
    }

    public function isDelivery(): bool
    {
        return $this->role === self::ROLE_DELIVERY;
    }

    /** Jobs list sections this role may open (null = all sections). */
    public function allowedJobsListSections(): ?array
    {
        if ($this->isDeliveryViewOnly()) {
            return ['completed'];
        }

        if ($this->isPrinter()) {
            return ['ongoing', 'pos_updated', 'print_done', 'completed', 'delivered', 'dismissed'];
        }

        if ($this->role === self::ROLE_FRAMING) {
            return ['ongoing', 'pos_updated', 'framing_done', 'completed', 'delivered', 'dismissed'];
        }

        if ($this->role === self::ROLE_PRINTER_FRAMING) {
            return ['ongoing', 'pos_updated', 'print_done', 'framing_done', 'completed', 'delivered', 'dismissed'];
        }

        return null;
    }

    public function canViewJobDetail(Job $job): bool
    {
        if ($this->isDeliveryViewOnly()) {
            return $job->status === Job::STATUS_COMPLETED;
        }

        return true;
    }

    /**
     * Printer / framing-only roles: Job Pool shows all eligible POS sales (not only unopened ones); Jobs list uses studio job scope.
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

    /** Framing and Printer + Framing: open from Job Pool but must take/join before line workflow actions. */
    public function usesPoolTakeJobWorkflow(): bool
    {
        return in_array($this->role, [self::ROLE_FRAMING, self::ROLE_PRINTER_FRAMING], true);
    }

    /** Photo editors take new jobs; pool framing roles must take/join before print or Done actions. */
    public function canTakeJob(): bool
    {
        return $this->isEditor() || $this->usesPoolTakeJobWorkflow();
    }

    /** Show the Take job button on job detail (editors: status new; pool framing roles: not yet on job). */
    public function showTakeJobButtonFor(Job $job): bool
    {
        if (! $this->canTakeJob()) {
            return false;
        }
        if ($this->isEditor()) {
            return $job->status === Job::STATUS_NEW;
        }
        if ($this->usesPoolTakeJobWorkflow()) {
            return ! $this->isAssignedToStudioJob($job);
        }

        return false;
    }

    /** Pool framing roles: must take/join the studio job before print or Done line actions. */
    public function framingMustTakeJobBeforeWorkOn(?Job $job): bool
    {
        return $this->usesPoolTakeJobWorkflow()
            && $job !== null
            && ! $this->isAssignedToStudioJob($job);
    }

    /** Create a studio job from a POS sale on the Job Pool page. */
    public function canOpenJobFromPool(): bool
    {
        if ($this->isCounterStaffViewOnly()) {
            return false;
        }

        return $this->canManageJobs()
            || $this->canTakeJob()
            || in_array($this->role, [
                self::ROLE_PRINTER,
                self::ROLE_FRAMING,
                self::ROLE_PRINTER_FRAMING,
            ], true);
    }

    /** Update print status: Admin, Printer, Editor + Printer, composite printer roles. */
    public function canUpdatePrintStatus(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
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
    public function canManageWorkflowReversals(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function canSetPrintStatusOnJobEdit(JobEdit $edit, string $newStatus): bool
    {
        if (! $this->canModifyJobWorkflow()) {
            return false;
        }

        if ($edit->isDoneOnlyWorkflow() || ! $edit->needsPrintWorkflow()) {
            return false;
        }

        $current = (string) $edit->print_status;

        if ($current === JobEdit::PRINT_STATUS_PRINTED && $newStatus !== $current) {
            return $this->canManageWorkflowReversals();
        }

        if ($newStatus === JobEdit::PRINT_STATUS_NOT_REQUIRED && ! JobEdit::allowsNotRequiredFrom($current)) {
            return $this->canManageWorkflowReversals();
        }

        $revertingPrintDone = JobEdit::isTerminalPrintStatus($current)
            && ! JobEdit::isTerminalPrintStatus($newStatus);
        if ($revertingPrintDone) {
            return $this->canManageWorkflowReversals();
        }

        if ($edit->isEditPrintWorkflow() && ! $edit->edit_done_at) {
            return false;
        }
        if ($this->role === self::ROLE_PRINTER_FRAMING) {
            $job = $edit->job ?? Job::find($edit->studio_job_id);
            if (! $job || ! $this->isAssignedToStudioJob($job)) {
                return false;
            }
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
        if ($this->isEditorPrinter() && $this->canWorkOnAssignedJobLine($edit)) {
            return true;
        }

        return in_array($this->role, [self::ROLE_PRINTER, self::ROLE_PRINTER_FRAMING], true);
    }

    public function canApplyPrintStatusToJobEdit(JobEdit $edit): bool
    {
        foreach (array_merge(JobEdit::terminalPrintStatuses(), [
            JobEdit::PRINT_STATUS_PENDING,
            JobEdit::PRINT_STATUS_SENT_TO_PRINT,
        ]) as $status) {
            if ($this->canSetPrintStatusOnJobEdit($edit, $status)) {
                return true;
            }
        }

        return false;
    }

    public function canRevertEditDoneOnJobEdit(JobEdit $edit): bool
    {
        return $this->canManageWorkflowReversals()
            && $edit->needsEditWorkflow()
            && $edit->hasEditDone();
    }

    /** Can add/remove editors on a job: Admin and Manager only. */
    public function canAddOrRemoveEditorsOn(?Job $job = null): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }

    /** Mark job delivered: Sales and Delivery (Admin/Manager may override). */
    public function canDeliver(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_SALES,
            self::ROLE_DELIVERY,
        ], true);
    }

    /** Mark Done on done-only categories (Frame, Laminating, Delivery, etc.). */
    public function canMarkFramingDone(JobEdit $edit): bool
    {
        if (! $this->canModifyJobWorkflow()) {
            return false;
        }

        if ($edit->framing_done_at !== null || ! $edit->needsDoneWorkflow()) {
            return false;
        }
        if ($this->isAdmin() || $this->isManager()) {
            return $this->categoryAllowsJobLine($edit);
        }
        if ($this->role === self::ROLE_EDITOR_PRINTER_FRAMING) {
            return $this->canWorkOnAssignedJobLine($edit);
        }
        if (in_array($this->role, [self::ROLE_FRAMING, self::ROLE_PRINTER_FRAMING], true)) {
            if (! $this->categoryAllowsJobLine($edit)) {
                return false;
            }
            $job = $edit->job ?? Job::find($edit->studio_job_id);

            return $job && $this->isAssignedToStudioJob($job);
        }

        return false;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
