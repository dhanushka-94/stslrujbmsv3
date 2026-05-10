<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\JobEdit;
use App\Services\SaleItemsJobEditsBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class JobsSyncFromSource extends Command
{
    protected $signature = 'jobs:sync-from-source';

    protected $description = 'Sync POS sales from studiosalaru_datadb (read-only) into studio jobs and edits';

    /** Read-only connection name for studiosalaru_datadb */
    private const SOURCE_CONNECTION = 'source';

    /** payment_status values that mean job is active (unpaid/pending) */
    private const ACTIVE_PAYMENT_STATUSES = ['pending', 'due', 'partial', 'unpaid'];

    public function handle(): int
    {
        set_time_limit(0); // No limit when run from CLI or long web sync
        $conn = self::SOURCE_CONNECTION;
        $db = config("database.connections.{$conn}.database");

        if (empty($db)) {
            $this->warn('Source database not configured. Set DB_SOURCE_DATABASE in .env (e.g. studiosalaru_datadb).');
            return self::FAILURE;
        }

        try {
            $sales = DB::connection($conn)
                ->table('sma_sales')
                ->where('pos', 1)
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            $this->error('Cannot read source sales: ' . $e->getMessage());
            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        foreach ($sales as $sale) {
            $refNumber = (string) ($sale->reference_no ?? '');
            if ($refNumber === '') {
                continue;
            }

            $dueDate = isset($sale->due_date) && $sale->due_date !== '0000-00-00'
                ? $sale->due_date
                : null;
            $isActive = in_array(strtolower((string) ($sale->payment_status ?? '')), self::ACTIVE_PAYMENT_STATUSES, true);

            $job = Job::where('ref_number', $refNumber)
                ->orWhere('source_id', (string) $sale->id)
                ->first();

            if ($job) {
                $job->update([
                    'customer_name' => $sale->customer ?? $job->customer_name,
                    'due_date' => $dueDate,
                    'is_active' => $isActive,
                ]);
                $updated++;
            } else {
                $job = Job::create([
                    'ref_number' => $refNumber,
                    'source_id' => (string) $sale->id,
                    'customer_name' => $sale->customer ?? null,
                    'notes' => $sale->note ?? null,
                    'due_date' => $dueDate,
                    'is_active' => $isActive,
                    'status' => Job::STATUS_NEW,
                ]);
                $created++;
            }

            $this->syncSaleItems($conn, $job, (int) $sale->id);
        }

        $this->info("Synced: {$created} new job(s), {$updated} job(s) updated.");
        return self::SUCCESS;
    }

    private function syncSaleItems(string $conn, Job $job, int $saleId): void
    {
        try {
            $items = DB::connection($conn)
                ->table('sma_sale_items')
                ->where('sale_id', $saleId)
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            return;
        }

        $rows = SaleItemsJobEditsBuilder::rowsFromSaleItems($conn, $items);

        if (empty($rows)) {
            if ($job->edits()->count() === 0) {
                $job->edits()->create(JobEdit::attributesForExistingColumns([
                    'name' => 'Edit 1',
                    'sort_order' => 0,
                    'edit_status' => JobEdit::EDIT_STATUS_PENDING,
                    'print_status' => JobEdit::PRINT_STATUS_PENDING,
                ]));
            }
            return;
        }

        $existing = $job->edits()->orderBy('sort_order')->get();
        foreach ($rows as $sortOrder => $row) {
            $edit = $existing->firstWhere('sort_order', $sortOrder);
            $payload = JobEdit::attributesForExistingColumns([
                'name' => $row['name'],
                'source_product_id' => $row['source_product_id'],
                'category_name' => $row['category_name'],
                'subcategory_name' => $row['subcategory_name'],
                'source_category_id' => $row['source_category_id'] ?? null,
                'source_sale_item_id' => $row['source_sale_item_id'] ?? null,
                'source_quantity_unit_index' => $row['source_quantity_unit_index'] ?? null,
                'source_quantity_unit_total' => $row['source_quantity_unit_total'] ?? null,
                'sort_order' => $sortOrder,
                'edit_status' => $edit ? $edit->edit_status : JobEdit::EDIT_STATUS_PENDING,
                'print_status' => $edit ? $edit->print_status : JobEdit::PRINT_STATUS_PENDING,
            ]);
            if ($edit) {
                $edit->update($payload);
            } else {
                $job->edits()->create($payload);
            }
        }
        $maxOrder = count($rows) - 1;
        $job->edits()->where('sort_order', '>', $maxOrder)->delete();
    }
}
