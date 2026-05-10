<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class JobEdit extends Model
{
    use HasFactory;

    protected $fillable = [
        'studio_job_id',
        'claimed_by_user_id',
        'claimed_at',
        'name',
        'source_product_id',
        'category_name',
        'subcategory_name',
        'source_category_id',
        'source_sale_item_id',
        'source_quantity_unit_index',
        'source_quantity_unit_total',
        'sort_order',
        'edit_status',
        'print_status',
        'print_status_at',
        'completed_at',
        'customer_confirmed_at',
        'sent_to_customer_count',
        'sent_to_customer_at',
        'reedit_count',
        'reedit_at',
        'edit_done_at',
        'framing_done_at',
        'estimated_minutes',
        'estimated_minutes_at',
    ];

    protected function casts(): array
    {
        return [
            'source_sale_item_id' => 'integer',
            'source_quantity_unit_index' => 'integer',
            'source_quantity_unit_total' => 'integer',
            'completed_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
            'claimed_at' => 'datetime',
            'sent_to_customer_at' => 'datetime',
            'reedit_at' => 'datetime',
            'edit_done_at' => 'datetime',
            'framing_done_at' => 'datetime',
            'print_status_at' => 'datetime',
            'estimated_minutes_at' => 'datetime',
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

    public function isFramingDone(): bool
    {
        return $this->framing_done_at !== null;
    }

    public const EDIT_STATUS_PENDING = 'pending';
    public const EDIT_STATUS_IN_PROGRESS = 'in_progress';
    public const EDIT_STATUS_COMPLETED = 'completed';

    public const PRINT_STATUS_NOT_REQUIRED = 'not_required';
    public const PRINT_STATUS_PENDING = 'pending';
    public const PRINT_STATUS_SENT_TO_PRINT = 'sent_to_print';
    public const PRINT_STATUS_PRINTED = 'printed';

    /**
     * POS category_name "FRAME" (case-insensitive): framing-only line — no editor or print workflow.
     */
    public function isFrameCategoryLine(): bool
    {
        return strcasecmp(trim((string) $this->category_name), 'FRAME') === 0;
    }

    /**
     * True when this line is hidden by global Block categories / Block products (same rules as the job line table).
     * Those lines are out of the studio workflow and must not block job completion or delivery.
     */
    public function isGloballyHiddenFromStudioWorkflow(): bool
    {
        $catId = $this->source_category_id ? (int) $this->source_category_id : null;
        $productId = $this->source_product_id ? (int) $this->source_product_id : null;
        $categoryBlocked = $catId !== null && in_array($catId, BlockedCategory::blockedCategoryIds(), true);
        $productBlocked = $productId !== null && in_array($productId, BlockedProduct::blockedProductIds(), true);

        return $categoryBlocked || $productBlocked;
    }

    /** Whether this line satisfies its workflow for overall job completion. */
    public function isWorkflowCompleteForJob(): bool
    {
        if ($this->isFrameCategoryLine()) {
            return $this->framing_done_at !== null;
        }

        return $this->edit_done_at !== null
            && in_array($this->print_status, [
                self::PRINT_STATUS_PRINTED,
                self::PRINT_STATUS_NOT_REQUIRED,
            ], true);
    }

    public function printStatusLabel(): string
    {
        return match ($this->print_status) {
            self::PRINT_STATUS_NOT_REQUIRED => 'Not required',
            self::PRINT_STATUS_PENDING => 'Pending',
            self::PRINT_STATUS_SENT_TO_PRINT => 'Sent to print',
            self::PRINT_STATUS_PRINTED => 'Printed',
            default => ucfirst(str_replace('_', ' ', (string) $this->print_status)),
        };
    }

    /** Compact workflow text for Jobs index line-item list (matches detail page semantics). */
    public function workflowStatusLineForJobList(): string
    {
        if ($this->isGloballyHiddenFromStudioWorkflow()) {
            return 'Out of workflow (blocked)';
        }

        if ($this->isFrameCategoryLine()) {
            return $this->framing_done_at !== null ? 'Framing done' : 'Awaiting framing';
        }

        $chunks = [];
        $chunks[] = $this->edit_done_at !== null ? 'Edit done' : 'Edit pending';
        $chunks[] = 'Print: ' . $this->printStatusLabel();

        $printTerminal = in_array($this->print_status, [
            self::PRINT_STATUS_PRINTED,
            self::PRINT_STATUS_NOT_REQUIRED,
        ], true);

        if ($printTerminal) {
            $chunks[] = $this->framing_done_at !== null ? 'Framing done' : 'Await framing';
        }

        return implode(' · ', $chunks);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'studio_job_id');
    }

    /**
     * Keep only keys that exist as columns on job_edits (avoids SQL errors when migrations lag).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function attributesForExistingColumns(array $attributes): array
    {
        $out = [];
        foreach ($attributes as $column => $value) {
            if (Schema::hasColumn('job_edits', $column)) {
                $out[$column] = $value;
            }
        }

        return $out;
    }
}
