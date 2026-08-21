<?php

namespace App\Models;

use App\Support\CategoryWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
            'due_date' => 'datetime',
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

    /**
     * POS sma_sales.payment_status values included in Job Pool (paid, partial, due/unpaid/pending).
     * Case variants match typical POS storage.
     *
     * @var list<string>
     */
    public const SOURCE_JOB_POOL_PAYMENT_STATUSES = [
        'paid', 'partial', 'pending', 'due', 'unpaid',
        'Paid', 'Partial', 'Pending', 'Due', 'Unpaid',
    ];

    /** First calendar day of POS `sma_sales.date` included in Job Pool (app timezone applied when querying). */
    public const SOURCE_JOB_POOL_MIN_SALE_DATE = '2026-08-01';

    public const DELIVERY_ONLINE = 'online';
    public const DELIVERY_WALKIN = 'walkin';
    public const DELIVERY_COURIER = 'courier';

    public function edits(): HasMany
    {
        return $this->hasMany(JobEdit::class, 'studio_job_id')->orderBy('sort_order');
    }

    public function posApplyHistories(): HasMany
    {
        return $this->hasMany(JobPosApplyHistory::class, 'studio_job_id')->orderByDesc('applied_at');
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

    /** True when the given instant has a clock time (not date-only at midnight). */
    public static function dueCarbonHasAssignedTime(?Carbon $due): bool
    {
        return $due !== null && $due->format('H:i:s') !== '00:00:00';
    }

    /**
     * Prefer raw POS `sma_sales.due_date` when present so Job Pool and job views match.
     *
     * @param  \DateTimeInterface|string|null  $stored  Value from `studio_jobs.due_date`
     * @param  mixed  $posRaw  Raw string from POS (or null)
     */
    public static function resolveDueFromStoredAndPos(mixed $stored, mixed $posRaw): ?Carbon
    {
        $tz = (string) config('app.timezone');
        if ($posRaw !== null && $posRaw !== '' && $posRaw !== '0000-00-00' && $posRaw !== '0000-00-00 00:00:00') {
            try {
                return Carbon::parse($posRaw)->timezone($tz);
            } catch (\Throwable) {
                // fall through to stored
            }
        }
        if ($stored instanceof Carbon) {
            return $stored->copy()->timezone($tz);
        }
        if ($stored) {
            try {
                return Carbon::parse($stored)->timezone($tz);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /** Trim POS `sma_sales.staff_note`; empty strings become null. */
    public static function normalizePosStaffNote(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim((string) $raw);

        return $s !== '' ? $s : null;
    }

    /**
     * Overdue when due is in the past. Date-only dues (midnight) count overdue from the start of the next calendar day.
     */
    public static function isDueCarbonPastDeadline(?Carbon $due, bool $workflowComplete): bool
    {
        if (! $due || $workflowComplete) {
            return false;
        }

        $d = $due->copy()->timezone((string) config('app.timezone'));

        if ($d->format('H:i:s') === '00:00:00') {
            return $d->copy()->startOfDay()->lt(today());
        }

        return $d->isPast();
    }

    /** True when POS / DB stored a clock time (not date-only at midnight). */
    public function dueHasTimeAssigned(): bool
    {
        return self::dueCarbonHasAssignedTime($this->due_date);
    }

    public function isDueOverdue(bool $workflowComplete): bool
    {
        return self::isDueCarbonPastDeadline($this->due_date, $workflowComplete);
    }

    /**
     * True when every workflow-relevant line item is done (FRAME = framing only; others = edit + print).
     * Lines hidden by global Block categories / Block products are ignored — same as the main job items table.
     */
    public function allEditsCompleted(): bool
    {
        $this->loadMissing('edits');
        if ($this->edits->isEmpty()) {
            return false;
        }

        $relevant = $this->edits->filter(fn (JobEdit $e) => ! $e->isGloballyHiddenFromStudioWorkflow());
        if ($relevant->isEmpty()) {
            return true;
        }

        return $relevant->every(fn (JobEdit $e) => $e->isWorkflowCompleteForJob());
    }

    /**
     * Counts workflow-relevant lines (excludes globally blocked categories/products) that are not yet complete.
     * FRAME lines count toward framing; all others need edit done + print terminal.
     *
     * @return array{
     *     relevant_total: int,
     *     complete_total: int,
     *     incomplete_total: int,
     *     pending_framing: int,
     *     pending_photo_edit: int,
     *     pending_photo_print: int,
     *     pending_edit_print: int
     * }
     */
    public function workflowLineProgressSummary(): array
    {
        $this->loadMissing('edits');
        $relevant = $this->edits->filter(fn (JobEdit $e) => ! $e->isGloballyHiddenFromStudioWorkflow());

        $pendingFraming = 0;
        $pendingPhotoEdit = 0;
        $pendingPhotoPrint = 0;
        foreach ($relevant as $e) {
            if ($e->isWorkflowCompleteForJob()) {
                continue;
            }
            if ($e->isDoneOnlyWorkflow()) {
                $pendingFraming++;
            } elseif ($e->isPrintOnlyWorkflow()) {
                if (! in_array($e->print_status, [JobEdit::PRINT_STATUS_PRINTED, JobEdit::PRINT_STATUS_NOT_REQUIRED], true)) {
                    $pendingPhotoPrint++;
                }
            } elseif ($e->edit_done_at === null) {
                $pendingPhotoEdit++;
            } else {
                $pendingPhotoPrint++;
            }
        }

        $relevantCount = $relevant->count();
        $pendingEditPrint = $pendingPhotoEdit + $pendingPhotoPrint;
        $incomplete = $pendingFraming + $pendingEditPrint;

        return [
            'relevant_total' => $relevantCount,
            'complete_total' => $relevantCount - $incomplete,
            'incomplete_total' => $incomplete,
            'pending_framing' => $pendingFraming,
            'pending_photo_edit' => $pendingPhotoEdit,
            'pending_photo_print' => $pendingPhotoPrint,
            'pending_edit_print' => $pendingEditPrint,
        ];
    }

    /**
     * Studio jobs shown on Job Pool for dedicated printer / framing users (all opened jobs, not only lines in print/framing queue).
     */
    public static function queryDedicatedPrintFramingJobPool(User $user): Builder
    {
        $blockedCats = BlockedCategory::blockedCategoryIds();
        $blockedProds = BlockedProduct::blockedProductIds();

        return static::query()
            ->whereNotNull('source_id')
            ->where('status', '!=', self::STATUS_DELIVERED)
            ->whereHas('edits', function ($ed) use ($blockedCats, $blockedProds, $user) {
                self::applyBlockedCatalogToJobEditQuery($ed, $blockedCats, $blockedProds);
                if ($user->jobPoolShowsPrintQueue() || $user->jobPoolShowsFramingQueue()) {
                    CategoryWorkflow::scopeJobPoolCategoriesForUser($ed, $user, 'category_name');
                }
            });
    }

    /**
     * @param  Builder<\App\Models\JobEdit>  $ed
     */
    protected static function applyBlockedCatalogToJobEditQuery(Builder $ed, array $blockedCats, array $blockedProds): void
    {
        if ($blockedCats !== []) {
            $ed->where(function ($qq) use ($blockedCats) {
                $qq->whereNull('source_category_id')->orWhereNotIn('source_category_id', $blockedCats);
            });
        }
        if ($blockedProds !== []) {
            $ed->where(function ($qq) use ($blockedProds) {
                $qq->whereNull('source_product_id')->orWhereNotIn('source_product_id', $blockedProds);
            });
        }
    }

    /**
     * Limit to jobs whose POS sale still meets standard Job Pool eligibility (pos, payment, date, due).
     */
    public static function applyJobPoolEligiblePosSaleExists(Builder $jobQuery): Builder
    {
        $sourceDb = config('database.connections.source.database');
        $tz = (string) config('app.timezone');
        $minSaleDate = Carbon::parse(self::SOURCE_JOB_POOL_MIN_SALE_DATE, $tz)->startOfDay();

        return $jobQuery->whereExists(function ($sub) use ($sourceDb, $minSaleDate) {
            $sub->select(DB::raw(1))
                ->from(DB::raw('`'.$sourceDb.'`.sma_sales as spos'))
                ->whereRaw('spos.id = CAST(studio_jobs.source_id AS UNSIGNED)')
                ->where('spos.pos', 1)
                ->whereIn('spos.payment_status', self::SOURCE_JOB_POOL_PAYMENT_STATUSES)
                ->where('spos.date', '>=', $minSaleDate)
                ->whereNotNull('spos.due_date')
                ->where('spos.due_date', '<>', '0000-00-00')
                ->where('spos.due_date', '<>', '0000-00-00 00:00:00');
        });
    }

    /**
     * Dedicated printer / framing Job Pool lists all opened studio jobs; do not hide jobs when POS payment/date/due changes.
     */
    public static function applyJobPoolGateForDedicatedPool(Builder $jobQuery, User $user): Builder
    {
        return $jobQuery;
    }

    public static function orderJobPoolByPosDueDate(Builder $jobQuery): Builder
    {
        $table = $jobQuery->getModel()->getTable();
        $sourceDb = config('database.connections.source.database');

        if (empty($sourceDb) || ! self::sourceDatabaseIsSameMysqlServer()) {
            return $jobQuery
                ->orderBy($table.'.due_date')
                ->orderBy($table.'.created_at')
                ->orderBy($table.'.id');
        }

        // Same MySQL server only: `other_db.table` works. Different hosts / missing grants → Jobs 500.
        $sourceDb = str_replace('`', '``', (string) $sourceDb);

        return $jobQuery
            ->orderByRaw(
                'COALESCE('
                .'(SELECT sp.due_date FROM `'.$sourceDb.'`.sma_sales sp WHERE sp.id = CAST('.$table.'.source_id AS UNSIGNED) LIMIT 1),'
                .$table.'.due_date)'
            )
            ->orderByRaw(
                'COALESCE('
                .'(SELECT sp2.`date` FROM `'.$sourceDb.'`.sma_sales sp2 WHERE sp2.id = CAST('.$table.'.source_id AS UNSIGNED) LIMIT 1),'
                .$table.'.created_at)'
            )
            ->orderBy($table.'.id');
    }

    /** True when app DB and POS source DB are on the same MySQL host/port (required for cross-db ORDER BY). */
    public static function sourceDatabaseIsSameMysqlServer(): bool
    {
        $default = config('database.connections.'.config('database.default', 'mysql'), []);
        $source = config('database.connections.source', []);
        if (! is_array($default) || ! is_array($source)) {
            return false;
        }

        $defaultHost = self::normalizeMysqlHost((string) ($default['host'] ?? ''));
        $sourceHost = self::normalizeMysqlHost((string) ($source['host'] ?? ''));
        $defaultPort = (string) ($default['port'] ?? '3306');
        $sourcePort = (string) ($source['port'] ?? '3306');

        if ($defaultHost === '' || $sourceHost === '') {
            return false;
        }

        return $defaultHost === $sourceHost && $defaultPort === $sourcePort;
    }

    private static function normalizeMysqlHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === 'localhost' || $host === '::1') {
            return '127.0.0.1';
        }

        return $host;
    }

    /**
     * @param  Builder<\App\Models\JobEdit>  $ed
     */
    protected static function constrainJobEditScopeForPrintQueue(Builder $ed, array $blockedCats, array $blockedProds): void
    {
        $ed->whereIn('print_status', [JobEdit::PRINT_STATUS_PENDING, JobEdit::PRINT_STATUS_SENT_TO_PRINT])
            ->where(function (Builder $printLines) {
                $printLines->where(function (Builder $printOnly) {
                    CategoryWorkflow::scopePrintOnlyCategory($printOnly);
                })->orWhere(function (Builder $editPrint) {
                    CategoryWorkflow::scopeEditPrintCategory($editPrint);
                    $editPrint->whereNotNull('edit_done_at');
                });
            });

        if ($blockedCats !== []) {
            $ed->where(function ($qq) use ($blockedCats) {
                $qq->whereNull('source_category_id')->orWhereNotIn('source_category_id', $blockedCats);
            });
        }
        if ($blockedProds !== []) {
            $ed->where(function ($qq) use ($blockedProds) {
                $qq->whereNull('source_product_id')->orWhereNotIn('source_product_id', $blockedProds);
            });
        }
    }

    /**
     * Framing queue: framing-only POS categories with framing not yet done.
     *
     * @param  Builder<\App\Models\JobEdit>  $ed
     */
    protected static function constrainJobEditScopeForFramingQueue(Builder $ed, array $blockedCats, array $blockedProds, User $user): void
    {
        $ed->whereNull('framing_done_at');
        CategoryWorkflow::scopeDoneOnlyCategory($ed);

        if ($blockedCats !== []) {
            $ed->where(function ($qq) use ($blockedCats) {
                $qq->whereNull('source_category_id')->orWhereNotIn('source_category_id', $blockedCats);
            });
        }
        if ($blockedProds !== []) {
            $ed->where(function ($qq) use ($blockedProds) {
                $qq->whereNull('source_product_id')->orWhereNotIn('source_product_id', $blockedProds);
            });
        }

        $allowed = $user->assignedCategoryIds();
        if ($allowed !== []) {
            $ed->where(function ($cat) use ($allowed) {
                $cat->whereNull('source_category_id')
                    ->orWhereIn('source_category_id', $allowed);
            });
        }
    }
}
