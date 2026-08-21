<?php

namespace App\Support;

use App\Models\BlockedCategory;
use App\Models\BlockedProduct;
use App\Models\Job;
use App\Models\JobEdit;
use App\Models\User;
use App\Services\SaleItemsJobEditsBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Job Pool visibility: sales must have at least one eligible line (POS item or studio job_edit).
 */
class JobPoolEligibility
{
    /**
     * Restrict a sma_sales query to rows with ≥1 eligible POS line, or (printer/framing pool) an opened job with eligible edits.
     *
     * @param  Builder  $query  Query on sma_sales (source connection).
     */
    public static function constrainSalesQuery(Builder $query, User $user, string $connection = 'source'): void
    {
        self::applyExcludeFinishedStudioJobsToSalesQuery($query);
        self::applyExcludeFullyHandledStudioJobsForUser($query, $user);

        $query->where(function (Builder $outer) use ($user, $connection): void {
            $outer->whereExists(function (Builder $exists) use ($user, $connection): void {
                self::applyEligiblePosLineExists($exists, $user, $connection);
            });

            if ($user->usesDedicatedPrintFramingJobPool()) {
                $sourceIds = self::sourceSaleIdsWithEligibleStudioJobEdits($user);
                if ($sourceIds !== []) {
                    $outer->orWhereIn('sma_sales.id', $sourceIds);
                }
            }
        });
    }

    /**
     * Hide POS sales whose opened studio job has no remaining work for this user's Job Pool role
     * (e.g. all print lines already Printed / Not required for Printer or Editor+Printer).
     *
     * @param  Builder  $query  Query on sma_sales (source connection).
     */
    public static function applyExcludeFullyHandledStudioJobsForUser(Builder $query, User $user, string $saleIdColumn = 'sma_sales.id'): void
    {
        if (! self::userUsesPrintOrFramingPoolCompletionFilter($user)) {
            return;
        }

        $doneSourceIds = self::sourceSaleIdsWithNoRemainingPoolWork($user);
        if ($doneSourceIds !== []) {
            $query->whereNotIn($saleIdColumn, $doneSourceIds);
        }
    }

    public static function userUsesPrintOrFramingPoolCompletionFilter(User $user): bool
    {
        return $user->jobPoolShowsPrintQueue()
            || $user->jobPoolShowsFramingQueue()
            || $user->isEditorPrinter();
    }

    /**
     * POS sale IDs linked to studio jobs that still exist for open work overall, but have
     * nothing left for this user's print/edit/framing pool responsibilities.
     *
     * @return list<int>
     */
    public static function sourceSaleIdsWithNoRemainingPoolWork(User $user): array
    {
        $jobs = Job::query()
            ->with('edits')
            ->whereNotNull('source_id')
            ->where('source_id', '<>', '')
            ->whereNotIn('status', [Job::STATUS_COMPLETED, Job::STATUS_DELIVERED])
            ->get();

        $ids = [];
        foreach ($jobs as $job) {
            $relevant = [];
            foreach ($job->edits as $edit) {
                if (self::isPoolCategoryLineForUser($edit, $user)) {
                    $relevant[] = $edit;
                }
            }

            if ($relevant === []) {
                continue;
            }

            if (! self::jobHasRemainingPoolWorkForUser($relevant, $user)) {
                $ids[] = (int) $job->source_id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * Category matches this user's Job Pool profiles (ignores whether the line is already done).
     */
    public static function isPoolCategoryLineForUser(JobEdit $edit, User $user): bool
    {
        if ($edit->isGloballyHiddenFromStudioWorkflow()) {
            return false;
        }

        if (! $user->isJobLineVisibleToMe($edit)) {
            return false;
        }

        return CategoryWorkflow::categoryMatchesUserJobPool($edit->category_name, $user);
    }

    /**
     * @param  list<JobEdit>  $relevantEdits
     */
    public static function jobHasRemainingPoolWorkForUser(array $relevantEdits, User $user): bool
    {
        $caresAboutPrint = $user->jobPoolShowsPrintQueue() || $user->isEditorPrinter();
        $caresAboutEdit = $user->isEditor();
        $caresAboutFraming = $user->jobPoolShowsFramingQueue() || $user->isEditorPrinterFraming();

        foreach ($relevantEdits as $edit) {
            if ($edit->isDoneOnlyWorkflow()) {
                if ($caresAboutFraming && ! $edit->isFramingDone()) {
                    return true;
                }
                continue;
            }

            if ($edit->isPrintOnlyWorkflow()) {
                if ($caresAboutPrint && ! $edit->hasPrintDone()) {
                    return true;
                }
                continue;
            }

            if ($edit->isEditPrintWorkflow()) {
                if ($caresAboutEdit && ! $edit->hasEditDone()) {
                    return true;
                }
                if ($caresAboutPrint && ! $edit->hasPrintDone()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Hide POS sales already linked to a completed or delivered studio job (Job Pool is for open work only).
     *
     * @param  Builder  $query  Query on sma_sales (source connection).
     */
    public static function applyExcludeFinishedStudioJobsToSalesQuery(Builder $query, string $saleIdColumn = 'sma_sales.id'): void
    {
        $finishedSourceIds = self::finishedStudioJobSourceSaleIds();
        if ($finishedSourceIds !== []) {
            $query->whereNotIn($saleIdColumn, $finishedSourceIds);
        }
    }

    /**
     * @return list<int>
     */
    public static function finishedStudioJobSourceSaleIds(): array
    {
        return Job::query()
            ->whereNotNull('source_id')
            ->where('source_id', '<>', '')
            ->whereIn('status', [Job::STATUS_COMPLETED, Job::STATUS_DELIVERED])
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * POS sale IDs that have an opened studio job with ≥1 eligible job_edit (app DB only — not on source connection).
     *
     * @return list<int>
     */
    public static function sourceSaleIdsWithEligibleStudioJobEdits(User $user): array
    {
        $jobsTable = (new Job)->getTable();
        $editsTable = (new JobEdit)->getTable();

        $query = DB::connection(config('database.default'))
            ->table($editsTable.' as je')
            ->join($jobsTable.' as j', 'je.studio_job_id', '=', 'j.id')
            ->whereNotNull('j.source_id')
            ->where('j.source_id', '<>', '')
            ->whereNotIn('j.status', [Job::STATUS_COMPLETED, Job::STATUS_DELIVERED])
            ->select('j.source_id')
            ->distinct();

        self::applyBlockedAndCategoryFilters($query, $user, 'je.source_category_id', 'je.source_product_id');
        self::applyWorkflowFilterToQuery($query, $user, 'je.category_name');
        self::applyRemainingPoolWorkFilterToEditsQuery($query, $user, 'je');

        return $query->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Keep only job_edit rows that still need print / edit / framing work for this user.
     *
     * @param  Builder  $query  Query with job_edits aliased (default je).
     */
    public static function applyRemainingPoolWorkFilterToEditsQuery(Builder $query, User $user, string $alias = 'je'): void
    {
        $caresAboutPrint = $user->jobPoolShowsPrintQueue() || $user->isEditorPrinter();
        $caresAboutEdit = $user->isEditor();
        $caresAboutFraming = $user->jobPoolShowsFramingQueue() || $user->isEditorPrinterFraming();

        if (! $caresAboutPrint && ! $caresAboutEdit && ! $caresAboutFraming) {
            return;
        }

        $printedTerminal = JobEdit::terminalPrintStatuses();

        $query->where(function (Builder $outer) use ($alias, $caresAboutPrint, $caresAboutEdit, $caresAboutFraming, $printedTerminal): void {
            if ($caresAboutPrint || $caresAboutEdit) {
                $outer->orWhere(function (Builder $printOnly) use ($alias, $caresAboutPrint, $printedTerminal): void {
                    CategoryWorkflow::scopePrintOnlyCategory($printOnly, $alias.'.category_name');
                    if ($caresAboutPrint) {
                        $printOnly->whereNotIn($alias.'.print_status', $printedTerminal);
                    } else {
                        $printOnly->whereRaw('1 = 0');
                    }
                });

                $outer->orWhere(function (Builder $editPrint) use ($alias, $caresAboutPrint, $caresAboutEdit, $printedTerminal): void {
                    CategoryWorkflow::scopeEditPrintCategory($editPrint, $alias.'.category_name');
                    $editPrint->where(function (Builder $need) use ($alias, $caresAboutPrint, $caresAboutEdit, $printedTerminal): void {
                        if ($caresAboutEdit) {
                            $need->orWhereNull($alias.'.edit_done_at');
                        }
                        if ($caresAboutPrint) {
                            $need->orWhereNotIn($alias.'.print_status', $printedTerminal);
                        }
                        if (! $caresAboutEdit && ! $caresAboutPrint) {
                            $need->whereRaw('1 = 0');
                        }
                    });
                });
            }

            if ($caresAboutFraming) {
                $outer->orWhere(function (Builder $doneOnly) use ($alias): void {
                    CategoryWorkflow::scopeDoneOnlyCategory($doneOnly, $alias.'.category_name');
                    $doneOnly->whereNull($alias.'.framing_done_at');
                });
            }
        });
    }

    /**
     * @param  iterable<int, object>  $saleItems  sma_sale_items rows (with product_id; optional category_id from join).
     * @return list<string>
     */
    public static function eligiblePosItemDisplayNames(string $connection, iterable $saleItems, User $user): array
    {
        return array_column(self::eligiblePosItemLines($connection, $saleItems, $user), 'name');
    }

    /**
     * @param  iterable<int, object>  $saleItems
     * @return list<array{name: string, category_name: string|null, profile: string}>
     */
    public static function eligiblePosItemLines(string $connection, iterable $saleItems, User $user): array
    {
        $lines = [];
        foreach (SaleItemsJobEditsBuilder::rowsFromSaleItems($connection, $saleItems) as $row) {
            if (! self::isEligiblePosRow($row, $user)) {
                continue;
            }
            $categoryName = $row['category_name'] ?? null;
            $lines[] = [
                'name' => $row['name'],
                'category_name' => $categoryName,
                'profile' => CategoryWorkflow::profileForCategoryName($categoryName),
            ];
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public static function eligibleJobEditDisplayNames(Job $job, User $user): array
    {
        return array_column(self::eligibleJobEditLines($job, $user), 'name');
    }

    /**
     * @return list<array{name: string, category_name: string|null, profile: string}>
     */
    public static function eligibleJobEditLines(Job $job, User $user): array
    {
        $job->loadMissing('edits');
        $lines = [];
        foreach ($job->edits as $edit) {
            if (! self::isEligibleJobEdit($edit, $user)) {
                continue;
            }
            $lines[] = [
                'name' => (string) $edit->name,
                'category_name' => $edit->category_name,
                'profile' => CategoryAppearance::profileForJobEdit($edit),
            ];
        }

        return $lines;
    }

    public static function isEligibleJobEdit(JobEdit $edit, User $user): bool
    {
        if (! self::isPoolCategoryLineForUser($edit, $user)) {
            return false;
        }

        if (! self::userUsesPrintOrFramingPoolCompletionFilter($user)) {
            return true;
        }

        return self::jobHasRemainingPoolWorkForUser([$edit], $user);
    }

    /**
     * @param  array{source_category_id?: int|null, source_product_id?: int|null, category_name?: string|null}  $row
     */
    public static function isEligiblePosRow(array $row, User $user): bool
    {
        $catId = isset($row['source_category_id']) ? (int) $row['source_category_id'] : null;
        $productId = isset($row['source_product_id']) ? (int) $row['source_product_id'] : null;
        $categoryName = $row['category_name'] ?? null;

        if ($catId !== null && in_array($catId, BlockedCategory::blockedCategoryIds(), true)) {
            return false;
        }
        if ($productId !== null && in_array($productId, BlockedProduct::blockedProductIds(), true)) {
            return false;
        }

        if (! $user->isPosCategoryVisibleToMe($categoryName, $catId)) {
            return false;
        }

        return CategoryWorkflow::categoryMatchesUserJobPool($categoryName, $user);
    }

    private static function applyEligiblePosLineExists(Builder $exists, User $user, string $connection): void
    {
        $exists->select(DB::raw(1))
            ->from('sma_sale_items as si')
            ->join('sma_products as p', 'si.product_id', '=', 'p.id')
            ->leftJoin('sma_categories as c', 'p.category_id', '=', 'c.id')
            ->whereColumn('si.sale_id', 'sma_sales.id');

        self::applyBlockedAndCategoryFilters($exists, $user, 'p.category_id', 'si.product_id');
        self::applyWorkflowFilterToQuery($exists, $user, 'c.name');
    }

    private static function applyBlockedAndCategoryFilters(
        Builder $query,
        User $user,
        string $categoryIdColumn,
        string $productIdColumn
    ): void {
        $blockedCats = BlockedCategory::blockedCategoryIds();
        $blockedProds = BlockedProduct::blockedProductIds();
        $allowed = $user->scopedCategoryIdsForJobLineTable();

        if ($blockedCats !== []) {
            $query->where(function (Builder $w) use ($blockedCats, $categoryIdColumn): void {
                $w->whereNull($categoryIdColumn)
                    ->orWhereNotIn($categoryIdColumn, $blockedCats);
            });
        }

        if ($blockedProds !== []) {
            $query->where(function (Builder $w) use ($blockedProds, $productIdColumn): void {
                $w->whereNull($productIdColumn)
                    ->orWhereNotIn($productIdColumn, $blockedProds);
            });
        }

        if ($allowed !== []) {
            $query->where(function (Builder $w) use ($allowed, $categoryIdColumn): void {
                $w->whereNull($categoryIdColumn)
                    ->orWhereIn($categoryIdColumn, $allowed);
            });
        }
    }

    private static function applyWorkflowFilterToQuery(Builder $query, User $user, string $categoryNameColumn): void
    {
        if ($user->jobPoolShowsPrintQueue() || $user->jobPoolShowsFramingQueue()) {
            CategoryWorkflow::scopeJobPoolCategoriesForUser($query, $user, $categoryNameColumn);
        }
    }
}
