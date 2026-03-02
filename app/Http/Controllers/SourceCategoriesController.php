<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SourceCategoriesController extends Controller
{
    private const SOURCE_CONNECTION = 'source';

    /**
     * List all categories and their subcategories from the read-only source DB (sma_categories).
     */
    public function index(): View
    {
        $conn = self::SOURCE_CONNECTION;
        $db = config("database.connections.{$conn}.database");
        $categories = collect();
        $error = null;

        if (empty($db)) {
            return view('source-categories.index', [
                'categories' => $categories,
                'error' => 'Source database not configured. Set DB_SOURCE_DATABASE in .env.',
            ]);
        }

        try {
            $topLevel = DB::connection($conn)
                ->table('sma_categories')
                ->where('parent_id', 0)
                ->orWhereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'code', 'name']);

            $subcategories = DB::connection($conn)
                ->table('sma_categories')
                ->where('parent_id', '>', 0)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'parent_id'])
                ->groupBy('parent_id');

            foreach ($topLevel as $cat) {
                $categories->push((object) [
                    'id' => $cat->id,
                    'code' => $cat->code,
                    'name' => $cat->name,
                    'subcategories' => $subcategories->get($cat->id, collect())->values(),
                ]);
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('source-categories.index', [
            'categories' => $categories,
            'error' => $error,
        ]);
    }
}
