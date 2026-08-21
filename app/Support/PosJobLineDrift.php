<?php

namespace App\Support;

use App\Models\Job;
use App\Services\SaleItemsJobEditsBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detect when an opened studio job's lines no longer match the linked POS sale.
 */
class PosJobLineDrift
{
    /**
     * Stable fingerprint for one expanded job/POS line.
     *
     * @param  array{name?: string, source_product_id?: int|null, source_sale_item_id?: int|null, source_quantity_unit_index?: int|null}  $row
     */
    public static function fingerprintRow(array $row): string
    {
        return implode('|', [
            (string) ((int) ($row['source_sale_item_id'] ?? 0)),
            (string) ((int) ($row['source_product_id'] ?? 0)),
            mb_strtoupper(trim((string) ($row['name'] ?? ''))),
            (string) ((int) ($row['source_quantity_unit_index'] ?? 1)),
            (string) ((int) ($row['source_quantity_unit_total'] ?? 1)),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function fingerprintRows(array $rows): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = self::fingerprintRow($row);
        }

        return implode("\n", $parts);
    }

    /**
     * Match key used to pair job lines with current POS lines.
     *
     * @param  array<string, mixed>  $row
     */
    public static function matchKey(array $row): string
    {
        $saleItemId = (int) ($row['source_sale_item_id'] ?? 0);
        $unitIndex = (int) ($row['source_quantity_unit_index'] ?? 1);
        if ($saleItemId > 0) {
            return 'si:'.$saleItemId.':'.$unitIndex;
        }

        return 'fp:'.self::fingerprintRow($row);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private static function indexByMatchKey(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = self::matchKey($row);
            // Keep first occurrence if duplicate keys appear.
            if (! isset($out[$key])) {
                $out[$key] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{name: string, category_name: ?string, unit_label: ?string}
     */
    public static function displayRow(array $row): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        $unitIndex = (int) ($row['source_quantity_unit_index'] ?? 1);
        $unitTotal = (int) ($row['source_quantity_unit_total'] ?? 1);
        $unitLabel = ($unitTotal > 1) ? ('unit '.$unitIndex.'/'.$unitTotal) : null;

        return [
            'name' => $name !== '' ? $name : '—',
            'category_name' => isset($row['category_name']) && filled($row['category_name'])
                ? (string) $row['category_name']
                : null,
            'unit_label' => $unitLabel,
        ];
    }

    /**
     * @return list<array{name: string, source_product_id: int|null, source_sale_item_id: int|null, source_quantity_unit_index: int, source_quantity_unit_total: int, category_name?: string|null}>
     */
    public static function rowsFromJob(Job $job): array
    {
        $edits = $job->relationLoaded('edits')
            ? $job->edits->sortBy('sort_order')->values()
            : $job->edits()->orderBy('sort_order')->get();

        $out = [];
        foreach ($edits as $edit) {
            $out[] = [
                'name' => (string) ($edit->name ?? ''),
                'source_product_id' => $edit->source_product_id !== null ? (int) $edit->source_product_id : null,
                'source_sale_item_id' => $edit->source_sale_item_id !== null ? (int) $edit->source_sale_item_id : null,
                'source_quantity_unit_index' => (int) ($edit->source_quantity_unit_index ?? 1),
                'source_quantity_unit_total' => (int) ($edit->source_quantity_unit_total ?? 1),
                'category_name' => $edit->category_name,
            ];
        }

        return $out;
    }

    /**
     * Expand POS sale items into job-line shape without extra product/category lookups.
     * Used for cheap drift detection (Jobs list count / scan).
     *
     * @param  iterable<int, object>  $saleItems
     * @return list<array<string, mixed>>
     */
    public static function lightweightRowsFromPosSaleItems(iterable $saleItems): array
    {
        $out = [];
        $i = 0;
        foreach ($saleItems as $item) {
            $saleItemId = isset($item->id) ? (int) $item->id : 0;
            $baseName = trim((string) ($item->product_name ?? ''));
            if ($baseName === '') {
                $baseName = 'Item '.($i + 1);
            }
            $rawProductId = isset($item->product_id) ? (int) $item->product_id : null;
            if (! SaleItemsJobEditsBuilder::isLinkableSourceProductId($rawProductId)) {
                $rawProductId = null;
            }

            $qtyRaw = (float) ($item->quantity ?? 1);
            if ($qtyRaw < 0) {
                $qtyRaw = 1;
            }
            $units = max(1, min(SaleItemsJobEditsBuilder::MAX_UNITS_PER_LINE, (int) floor($qtyRaw)));

            for ($u = 1; $u <= $units; $u++) {
                $name = $units > 1
                    ? $baseName.' ('.$u.' of '.$units.')'
                    : $baseName;
                $out[] = [
                    'name' => $name,
                    'source_product_id' => $rawProductId,
                    'source_sale_item_id' => $saleItemId > 0 ? $saleItemId : null,
                    'source_quantity_unit_index' => $u,
                    'source_quantity_unit_total' => $units,
                ];
            }
            $i++;
        }

        return $out;
    }

    /**
     * @param  iterable<int, object>  $saleItems
     * @return list<array<string, mixed>>
     */
    public static function rowsFromPosSaleItems(string $connection, iterable $saleItems): array
    {
        return SaleItemsJobEditsBuilder::rowsFromSaleItems($connection, $saleItems);
    }

    public static function differs(Job $job, array $posRows): bool
    {
        return self::fingerprintRows(self::rowsFromJob($job)) !== self::fingerprintRows($posRows);
    }

    /**
     * @return array{
     *   differs: bool,
     *   job_line_count: int,
     *   pos_line_count: int,
     *   summary: string,
     *   pending_count: int,
     *   unchanged: list<array{name: string, category_name: ?string, unit_label: ?string}>,
     *   changed: list<array{from: array{name: string, category_name: ?string, unit_label: ?string}, to: array{name: string, category_name: ?string, unit_label: ?string}}>,
     *   added: list<array{name: string, category_name: ?string, unit_label: ?string}>,
     *   removed: list<array{name: string, category_name: ?string, unit_label: ?string}>
     * }
     */
    public static function compare(Job $job, array $posRows): array
    {
        $jobRows = self::rowsFromJob($job);
        $jobCount = count($jobRows);
        $posCount = count($posRows);
        $differs = self::fingerprintRows($jobRows) !== self::fingerprintRows($posRows);

        $jobByKey = self::indexByMatchKey($jobRows);
        $posByKey = self::indexByMatchKey($posRows);

        $unchanged = [];
        $changed = [];
        $added = [];
        $removed = [];

        foreach ($jobByKey as $key => $jobRow) {
            if (! isset($posByKey[$key])) {
                $removed[] = self::displayRow($jobRow);
                continue;
            }
            $posRow = $posByKey[$key];
            if (self::fingerprintRow($jobRow) === self::fingerprintRow($posRow)) {
                $unchanged[] = self::displayRow($jobRow);
            } else {
                $changed[] = [
                    'from' => self::displayRow($jobRow),
                    'to' => self::displayRow($posRow),
                ];
            }
        }

        foreach ($posByKey as $key => $posRow) {
            if (! isset($jobByKey[$key])) {
                $added[] = self::displayRow($posRow);
            }
        }

        $pendingCount = count($changed) + count($added) + count($removed);

        $summary = 'Lines match POS';
        if ($differs) {
            $bits = [];
            if (count($added) > 0) {
                $bits[] = '+'.count($added).' new on POS';
            }
            if (count($removed) > 0) {
                $bits[] = '−'.count($removed).' removed from POS';
            }
            if (count($changed) > 0) {
                $bits[] = count($changed).' changed';
            }
            $summary = $bits !== [] ? implode(' · ', $bits) : 'POS lines changed';
        }

        return [
            'differs' => $differs,
            'job_line_count' => $jobCount,
            'pos_line_count' => $posCount,
            'summary' => $summary,
            'pending_count' => $pendingCount,
            'job_lines' => array_map(fn (array $r) => self::displayRow($r), $jobRows),
            'pos_lines' => array_map(fn (array $r) => self::displayRow($r), $posRows),
            'unchanged' => $unchanged,
            'changed' => $changed,
            'added' => $added,
            'removed' => $removed,
        ];
    }

    /**
     * Cheap scan: job ids whose lines no longer match POS (no product/category lookups).
     *
     * @param  Collection<int, Job>  $jobs
     * @return list<int>
     */
    public static function driftedJobIdsAmong(Collection $jobs, string $connection = 'source'): array
    {
        if ($jobs->isEmpty() || empty(config("database.connections.{$connection}.database"))) {
            return [];
        }

        $saleIds = $jobs
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($saleIds === []) {
            return [];
        }

        try {
            $existingSaleIdSet = array_fill_keys(
                DB::connection($connection)
                    ->table('sma_sales')
                    ->whereIn('id', $saleIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                true
            );

            $grouped = DB::connection($connection)
                ->table('sma_sale_items')
                ->whereIn('sale_id', $saleIds)
                ->orderBy('id')
                ->get(['id', 'sale_id', 'product_id', 'product_name', 'quantity'])
                ->groupBy('sale_id');
        } catch (\Throwable) {
            return [];
        }

        $ids = [];
        foreach ($jobs as $job) {
            $saleId = (int) $job->source_id;
            if ($saleId <= 0 || ! isset($existingSaleIdSet[$saleId])) {
                continue;
            }
            $items = $grouped->get($saleId, collect());
            $posRows = self::lightweightRowsFromPosSaleItems($items->all());
            if (self::fingerprintRows(self::rowsFromJob($job)) !== self::fingerprintRows($posRows)) {
                $ids[] = (int) $job->id;
            }
        }

        return $ids;
    }

    /**
     * @param  Collection<int, Job>  $jobs
     * @return Collection<int, array{job: Job, job_line_count: int, pos_line_count: int, summary: string, pending_count: int, changed: list, added: list, removed: list, job_lines: list, pos_lines: list}>
     */
    public static function driftedJobsAmong(Collection $jobs, string $connection = 'source'): Collection
    {
        if ($jobs->isEmpty() || empty(config("database.connections.{$connection}.database"))) {
            return collect();
        }

        $saleIds = $jobs
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($saleIds === []) {
            return collect();
        }

        try {
            $existingSaleIdSet = array_fill_keys(
                DB::connection($connection)
                    ->table('sma_sales')
                    ->whereIn('id', $saleIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                true
            );

            $grouped = DB::connection($connection)
                ->table('sma_sale_items')
                ->whereIn('sale_id', $saleIds)
                ->orderBy('id')
                ->get(['id', 'sale_id', 'product_id', 'product_name', 'quantity'])
                ->groupBy('sale_id');
        } catch (\Throwable) {
            return collect();
        }

        $out = collect();
        foreach ($jobs as $job) {
            $saleId = (int) $job->source_id;
            if ($saleId <= 0 || ! isset($existingSaleIdSet[$saleId])) {
                continue;
            }
            $items = $grouped->get($saleId, collect());
            $cmp = self::compare($job, self::lightweightRowsFromPosSaleItems($items->all()));
            if (! $cmp['differs']) {
                continue;
            }
            $out->push([
                'job' => $job,
                'job_line_count' => $cmp['job_line_count'],
                'pos_line_count' => $cmp['pos_line_count'],
                'summary' => $cmp['summary'],
                'pending_count' => $cmp['pending_count'],
                'changed' => $cmp['changed'],
                'added' => $cmp['added'],
                'removed' => $cmp['removed'],
                'job_lines' => $cmp['job_lines'],
                'pos_lines' => $cmp['pos_lines'],
            ]);
        }

        return $out;
    }

    /**
     * True when the linked POS sale row is gone (deleted/voided), not merely empty of items.
     */
    public static function saleMissingForJob(Job $job, string $connection = 'source'): bool
    {
        $saleId = (int) ($job->source_id ?? 0);
        if ($saleId <= 0) {
            return false;
        }
        if (empty(config("database.connections.{$connection}.database"))) {
            return false;
        }

        try {
            return ! DB::connection($connection)->table('sma_sales')->where('id', $saleId)->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
