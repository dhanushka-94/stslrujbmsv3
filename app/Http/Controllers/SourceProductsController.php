<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SourceProductsController extends Controller
{
    /** Read-only connection for studiosalaru_datadb */
    private const SOURCE_CONNECTION = 'source';

    /**
     * List products with category and subcategory from the read-only source DB.
     */
    public function index(): View
    {
        $conn = self::SOURCE_CONNECTION;
        $db = config("database.connections.{$conn}.database");

        if (empty($db)) {
            return view('source-products.index', ['products' => collect(), 'error' => 'Source database not configured.']);
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
                    'p.price',
                    'p.quantity',
                    'cat.name as category_name',
                    'cat.code as category_code',
                    'sub.name as subcategory_name',
                    'sub.code as subcategory_code',
                ])
                ->orderBy('cat.name')
                ->orderBy('sub.name')
                ->orderBy('p.name')
                ->get();
        } catch (\Throwable $e) {
            return view('source-products.index', ['products' => collect(), 'error' => $e->getMessage()]);
        }

        return view('source-products.index', ['products' => $products, 'error' => null]);
    }
}
