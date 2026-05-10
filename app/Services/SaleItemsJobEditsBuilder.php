<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Maps POS sma_sale_items into job line rows. Quantity &gt; 1 becomes multiple rows (one workflow line per unit).
 */
class SaleItemsJobEditsBuilder
{
    /** Safety cap so a bad POS value cannot create thousands of job_edits rows. */
    public const MAX_UNITS_PER_LINE = 99;

    /**
     * @param  iterable<int, object>  $items  sma_sale_items rows for one sale, ordered by id
     * @return list<array{name: string, source_product_id: int|null, category_name: string|null, subcategory_name: string|null, source_category_id: int|null, source_sale_item_id: int|null, source_quantity_unit_index: int, source_quantity_unit_total: int}>
     */
    public static function rowsFromSaleItems(string $connection, iterable $items): array
    {
        $out = [];
        foreach ($items as $i => $item) {
            $saleItemId = isset($item->id) ? (int) $item->id : null;
            $baseName = trim((string) ($item->product_name ?? ''));
            if ($baseName === '') {
                $baseName = 'Item ' . ((int) $i + 1);
            }
            $productId = isset($item->product_id) ? (int) $item->product_id : null;
            $categoryName = null;
            $subcategoryName = null;
            $sourceCategoryId = null;
            if ($productId > 0) {
                $product = DB::connection($connection)->table('sma_products')->where('id', $productId)->first(['id', 'category_id', 'subcategory_id']);
                if ($product) {
                    $catId = (int) ($product->category_id ?? 0);
                    if ($catId > 0) {
                        $sourceCategoryId = $catId;
                        $categoryName = DB::connection($connection)->table('sma_categories')->where('id', $catId)->value('name');
                    }
                    $subId = (int) ($product->subcategory_id ?? 0);
                    if ($subId > 0) {
                        $subcategoryName = DB::connection($connection)->table('sma_categories')->where('id', $subId)->value('name');
                    }
                }
            }

            $qtyRaw = (float) ($item->quantity ?? 1);
            if ($qtyRaw < 0) {
                $qtyRaw = 1;
            }
            $units = max(1, min(self::MAX_UNITS_PER_LINE, (int) floor($qtyRaw)));

            for ($u = 1; $u <= $units; $u++) {
                $name = $units > 1
                    ? $baseName . ' (' . $u . ' of ' . $units . ')'
                    : $baseName;
                $out[] = [
                    'name' => $name,
                    'source_product_id' => $productId ?: null,
                    'category_name' => $categoryName,
                    'subcategory_name' => $subcategoryName,
                    'source_category_id' => $sourceCategoryId,
                    'source_sale_item_id' => $saleItemId > 0 ? $saleItemId : null,
                    'source_quantity_unit_index' => $u,
                    'source_quantity_unit_total' => $units,
                ];
            }
        }

        return $out;
    }

    /**
     * Display names for Job Pool (same expansion rules as job sync).
     *
     * @param  iterable<int, object>  $items
     * @return list<string>
     */
    public static function namesForJobPool(string $connection, iterable $items): array
    {
        return array_map(
            fn (array $row) => $row['name'],
            self::rowsFromSaleItems($connection, $items)
        );
    }
}
