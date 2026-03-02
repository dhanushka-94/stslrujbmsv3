<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BlockedProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlockedProductsController extends Controller
{
    public function index(): View
    {
        $productsByCategory = $this->getSourceProductsGroupedByCategory();
        $blockedIds = BlockedProduct::blockedProductIds();

        return view('settings.block-products', [
            'productsByCategory' => $productsByCategory,
            'blockedProductIds' => $blockedIds,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! auth()->user()->isAdmin() && ! auth()->user()->isManager()) {
            abort(403);
        }

        $ids = $request->input('product_ids', []);
        $ids = is_array($ids) ? array_values(array_unique(array_map('intval', array_filter($ids)))) : [];

        DB::transaction(function () use ($ids) {
            BlockedProduct::query()->delete();
            foreach ($ids as $sourceProductId) {
                if ($sourceProductId > 0) {
                    BlockedProduct::create(['source_product_id' => $sourceProductId]);
                }
            }
        });
        ActivityLog::log('blocked_products_updated', 'Updated global blocked products (' . count($ids) . ' selected)');
        return redirect()->route('settings.block-products')->with('success', 'Blocked products saved. Those items are hidden in all jobs.');
    }

    /**
     * Load products from source DB grouped by category (category name, then products).
     */
    private function getSourceProductsGroupedByCategory(): \Illuminate\Support\Collection
    {
        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return collect();
        }
        try {
            $products = DB::connection($conn)
                ->table('sma_products as p')
                ->leftJoin('sma_categories as cat', 'p.category_id', '=', 'cat.id')
                ->leftJoin('sma_categories as sub', 'p.subcategory_id', '=', 'sub.id')
                ->select([
                    'p.id',
                    'p.code',
                    'p.name',
                    'cat.name as category_name',
                    'sub.name as subcategory_name',
                ])
                ->orderBy('cat.name')
                ->orderBy('sub.name')
                ->orderBy('p.name')
                ->get();

            return $products->groupBy(function ($p) {
                return $p->category_name ?: 'Uncategorized';
            })->map(function ($items, $categoryName) {
                return (object) [
                    'category_name' => $categoryName,
                    'products' => $items->values()->map(fn ($p) => (object) [
                        'id' => (int) $p->id,
                        'code' => $p->code,
                        'name' => $p->name,
                        'subcategory_name' => $p->subcategory_name,
                    ])->all(),
                ];
            })->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
