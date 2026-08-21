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

    /** POS uses this product_id for manual / ad-hoc sale lines (not a real sma_products row). */
    public const MANUAL_SALE_PRODUCT_ID = 4294967295;

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
            $rawProductId = isset($item->product_id) ? (int) $item->product_id : null;
            $productCode = trim((string) ($item->product_code ?? ''));
            $productType = strtolower(trim((string) ($item->product_type ?? '')));

            $meta = self::resolveCategoryMetadata($connection, $baseName, $rawProductId, $productCode, $productType);

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
                    'source_product_id' => $meta['source_product_id'],
                    'category_name' => $meta['category_name'],
                    'subcategory_name' => $meta['subcategory_name'],
                    'source_category_id' => $meta['source_category_id'],
                    'source_sale_item_id' => $saleItemId > 0 ? $saleItemId : null,
                    'source_quantity_unit_index' => $u,
                    'source_quantity_unit_total' => $units,
                ];
            }
        }

        return $out;
    }

    /**
     * Resolve POS category + product for a sale line (used when syncing jobs and when enriching job detail).
     *
     * @return array{source_product_id: int|null, category_name: string|null, subcategory_name: string|null, source_category_id: int|null}
     */
    public static function resolveCategoryMetadata(
        string $connection,
        string $lineName,
        ?int $rawProductId = null,
        ?string $productCode = null,
        ?string $productType = null
    ): array {
        $empty = [
            'source_product_id' => null,
            'category_name' => null,
            'subcategory_name' => null,
            'source_category_id' => null,
        ];

        $productCode = trim((string) ($productCode ?? ''));

        try {
            $db = DB::connection($connection);

            $lookupName = self::baseNameForProductLookup($lineName);

            if (self::isLinkableSourceProductId($rawProductId)) {
                $fromId = self::categoryRowForProductId($db, $rawProductId);
                if ($fromId['category_name'] !== null) {
                    $fromId['source_product_id'] = $rawProductId;

                    return $fromId;
                }
            }

            if ($productCode !== '') {
                $fromCode = self::categoryRowForProductCode($db, $productCode);
                if ($fromCode['category_name'] !== null) {
                    return $fromCode;
                }
            }

            if ($lookupName !== '') {
                $fromExact = self::categoryRowForProductName($db, $lookupName, exact: true);
                if ($fromExact['category_name'] !== null) {
                    return $fromExact;
                }

                $fromFuzzy = self::categoryRowForProductName($db, $lookupName, exact: false);
                if ($fromFuzzy['category_name'] !== null) {
                    return $fromFuzzy;
                }
            }

            $inferred = self::inferCategoryFromLineName($db, $lookupName !== '' ? $lookupName : $lineName);
            if ($inferred['category_name'] !== null) {
                return $inferred;
            }
        } catch (\Throwable) {
            // POS lookup failed — do not break job detail page
        }

        return $empty;
    }

    public static function isLinkableSourceProductId(?int $productId): bool
    {
        return $productId !== null
            && $productId > 0
            && $productId !== self::MANUAL_SALE_PRODUCT_ID
            && $productId <= 999999;
    }

    /**
     * Strip quantity suffix added when expanding multi-qty lines, e.g. "Foo (2 of 3)" → "Foo".
     */
    public static function baseNameForProductLookup(string $name): string
    {
        $name = trim($name);
        if (preg_match('/^(.+?)\s*\(\d+\s+of\s+\d+\)\s*$/iu', $name, $m)) {
            return trim($m[1]);
        }

        return $name;
    }

    /**
     * @return array{source_product_id: int|null, category_name: string|null, subcategory_name: string|null, source_category_id: int|null}
     */
    private static function categoryRowForProductId($db, int $productId): array
    {
        $product = $db->table('sma_products')->where('id', $productId)->first(['id', 'category_id', 'subcategory_id']);
        if (! $product) {
            return self::emptyCategoryRow();
        }

        return self::categoryRowFromProduct($db, $product);
    }

    /**
     * @return array{source_product_id: int|null, category_name: string|null, subcategory_name: string|null, source_category_id: int|null}
     */
    private static function categoryRowForProductCode($db, string $code): array
    {
        $product = $db->table('sma_products')->where('code', $code)->first(['id', 'category_id', 'subcategory_id']);
        if (! $product) {
            return self::emptyCategoryRow();
        }

        $row = self::categoryRowFromProduct($db, $product);
        $row['source_product_id'] = (int) $product->id;

        return $row;
    }

    /**
     * @return array{source_product_id: int|null, category_name: string|null, subcategory_name: string|null, source_category_id: int|null}
     */
    private static function categoryRowForProductName($db, string $name, bool $exact): array
    {
        $query = $db->table('sma_products as p')
            ->leftJoin('sma_categories as cat', 'p.category_id', '=', 'cat.id')
            ->leftJoin('sma_categories as sub', 'p.subcategory_id', '=', 'sub.id')
            ->select([
                'p.id',
                'p.category_id as source_category_id',
                'cat.name as category_name',
                'sub.name as subcategory_name',
            ]);

        if ($exact) {
            $query->whereRaw('TRIM(p.name) = ?', [$name]);
        } else {
            $needle = '%'.self::likePatternFromName($name).'%';
            $query->where(function ($q) use ($needle, $name) {
                $q->whereRaw('TRIM(p.name) LIKE ?', [$needle])
                    ->orWhereRaw('TRIM(p.code) LIKE ?', [$needle]);
                if (self::containsYouAndMe($name)) {
                    $q->orWhereRaw('TRIM(p.name) LIKE ?', ['%you%me%'])
                        ->orWhere('p.code', 'PH-FR-Y-A-M');
                }
            });
        }

        $product = $query->orderBy('p.id')->first();
        if (! $product || $product->category_name === null) {
            return self::emptyCategoryRow();
        }

        return [
            'source_product_id' => (int) $product->id,
            'category_name' => trim((string) $product->category_name) ?: null,
            'subcategory_name' => filled($product->subcategory_name) ? trim((string) $product->subcategory_name) : null,
            'source_category_id' => ! empty($product->source_category_id) ? (int) $product->source_category_id : null,
        ];
    }

    /**
     * Manual lines like "YOU & ME FRAME" — map to Frame when name clearly indicates framing.
     *
     * @return array{source_product_id: int|null, category_name: string|null, subcategory_name: string|null, source_category_id: int|null}
     */
    private static function inferCategoryFromLineName($db, string $lineName): array
    {
        $normalized = mb_strtoupper(trim($lineName));

        if (self::containsYouAndMe($lineName) || str_contains($normalized, 'FRAME')) {
            $frame = $db->table('sma_categories')->where('id', 2)->first(['id', 'name']);
            if ($frame && filled($frame->name)) {
                return [
                    'source_product_id' => null,
                    'category_name' => trim((string) $frame->name),
                    'subcategory_name' => null,
                    'source_category_id' => (int) $frame->id,
                ];
            }
        }

        return self::emptyCategoryRow();
    }

    /**
     * @return array{source_product_id: int|null, category_name: string|null, subcategory_name: string|null, source_category_id: int|null}
     */
    private static function categoryRowFromProduct($db, object $product): array
    {
        $catId = (int) ($product->category_id ?? 0);
        $categoryName = null;
        $subcategoryName = null;
        if ($catId > 0) {
            $categoryName = $db->table('sma_categories')->where('id', $catId)->value('name');
        }
        $subId = (int) ($product->subcategory_id ?? 0);
        if ($subId > 0) {
            $subcategoryName = $db->table('sma_categories')->where('id', $subId)->value('name');
        }

        return [
            'source_product_id' => (int) $product->id,
            'category_name' => filled($categoryName) ? trim((string) $categoryName) : null,
            'subcategory_name' => filled($subcategoryName) ? trim((string) $subcategoryName) : null,
            'source_category_id' => $catId > 0 ? $catId : null,
        ];
    }

    /** @return array{source_product_id: int|null, category_name: string|null, subcategory_name: string|null, source_category_id: int|null} */
    private static function emptyCategoryRow(): array
    {
        return [
            'source_product_id' => null,
            'category_name' => null,
            'subcategory_name' => null,
            'source_category_id' => null,
        ];
    }

    private static function containsYouAndMe(string $name): bool
    {
        $n = mb_strtoupper($name);

        return str_contains($n, 'YOU')
            && str_contains($n, 'ME');
    }

    /** Build a loose LIKE pattern from a product line name (alphanumeric chunks). */
    private static function likePatternFromName(string $name): string
    {
        $parts = preg_split('/[^a-z0-9]+/iu', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return mb_strtolower($name);
        }

        return implode('%', $parts);
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
