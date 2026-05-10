<?php

namespace App\Models;

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
    public const SOURCE_JOB_POOL_MIN_SALE_DATE = '2026-04-01';

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
            if ($e->isFrameCategoryLine()) {
                $pendingFraming++;
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
     * Studio jobs shown on Job Pool for dedicated printer / framing users.
     */
    public static function queryDedicatedPrintFramingJobPool(User $user): Builder
    {
        $blockedCats = BlockedCategory::blockedCategoryIds();
        $blockedProds = BlockedProduct::blockedProductIds();

        $showPrint = $user->jobPoolShowsPrintQueue();
        $showFraming = $user->jobPoolShowsFramingQueue();

        $q = static::query()
            ->whereNotNull('source_id')
            ->where('status', '!=', self::STATUS_DELIVERED)
            ->where(function ($outer) use ($user, $blockedCats, $blockedProds, $showPrint, $showFraming) {
                if ($showPrint) {
                    $outer->orWhereHas('edits', function ($ed) use ($blockedCats, $blockedProds) {
                        self::constrainJobEditScopeForPrintQueue($ed, $blockedCats, $blockedProds);
                    });
                }
                if ($showFraming) {
                    $outer->orWhereHas('edits', function ($ed) use ($blockedCats, $blockedProds, $user) {
                        self::constrainJobEditScopeForFramingQueue($ed, $blockedCats, $blockedProds, $user);
                    });
                }
            });

        return $q;
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

    public static function orderJobPoolByPosDueDate(Builder $jobQuery): Builder
    {
        $sourceDb = config('database.connections.source.database');

        return $jobQuery
            ->orderByRaw('(SELECT sp.due_date FROM `'.$sourceDb.'`.sma_sales sp WHERE sp.id = CAST(studio_jobs.source_id AS UNSIGNED) LIMIT 1)')
            ->orderByRaw('(SELECT sp2.`date` FROM `'.$sourceDb.'`.sma_sales sp2 WHERE sp2.id = CAST(studio_jobs.source_id AS UNSIGNED) LIMIT 1)');
    }

    /**
     * @param  Builder<\App\Models\JobEdit>  $ed
     */
    protected static function constrainJobEditScopeForPrintQueue(Builder $ed, array $blockedCats, array $blockedProds): void
    {
        $ed->whereNotNull('edit_done_at')
            ->whereIn('print_status', [JobEdit::PRINT_STATUS_PENDING, JobEdit::PRINT_STATUS_SENT_TO_PRINT])
            ->whereRaw('LOWER(TRIM(category_name)) <> ?', ['frame']);

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
     * @param  Builder<\App\Models\JobEdit>  $ed
     */
    protected static function constrainJobEditScopeForFramingQueue(Builder $ed, array $blockedCats, array $blockedProds, User $user): void
    {
        $ed->whereNull('framing_done_at');

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

        $ed->where(function ($w) use ($allowed) {
            $w->where(function ($frameOnly) use ($allowed) {
                $frameOnly->whereRaw('LOWER(TRIM(category_name)) = ?', ['frame']);
                if ($allowed !== []) {
                    $frameOnly->whereNotNull('source_category_id')->whereIn('source_category_id', $allowed);
                }
            })->orWhere(function ($afterPrint) use ($allowed) {
                $afterPrint->whereRaw('LOWER(TRIM(category_name)) <> ?', ['frame'])
                    ->where('print_status', JobEdit::PRINT_STATUS_PRINTED);
                if ($allowed !== []) {
                    $afterPrint->whereNotNull('source_category_id')->whereIn('source_category_id', $allowed);
                }
            });
        });
    }
}
