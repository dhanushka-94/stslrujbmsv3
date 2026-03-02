<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BlockedCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlockedCategoriesController extends Controller
{
    public function index(): View
    {
        $sourceCategories = $this->getSourceCategoriesForDropdown();
        $blockedIds = BlockedCategory::blockedCategoryIds();

        return view('settings.block-categories', [
            'sourceCategories' => $sourceCategories,
            'blockedCategoryIds' => $blockedIds,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! auth()->user()->isAdmin() && ! auth()->user()->isManager()) {
            abort(403);
        }

        $ids = $request->input('category_ids', []);
        $ids = is_array($ids) ? array_values(array_unique(array_map('intval', array_filter($ids)))) : [];

        DB::transaction(function () use ($ids) {
            BlockedCategory::query()->delete();
            foreach ($ids as $sourceCategoryId) {
                if ($sourceCategoryId > 0) {
                    BlockedCategory::create(['source_category_id' => $sourceCategoryId]);
                }
            }
        });
        ActivityLog::log('blocked_categories_updated', 'Updated global blocked categories (' . count($ids) . ' selected)');
        return redirect()->route('settings.block-categories')->with('success', 'Blocked categories saved. Items in those categories are hidden in all jobs.');
    }

    /**
     * Return categories in a hierarchical structure: main categories with their subcategories.
     */
    private function getSourceCategoriesForDropdown(): \Illuminate\Support\Collection
    {
        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return collect();
        }
        try {
            $all = DB::connection($conn)
                ->table('sma_categories')
                ->orderBy('parent_id')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']);

            $parents = $all->filter(fn ($c) => (int) $c->parent_id === 0 || $c->parent_id === null)->values();
            $subsByParent = $all->filter(fn ($c) => (int) $c->parent_id > 0)->groupBy('parent_id');

            return $parents->map(function ($parent) use ($subsByParent) {
                $subs = $subsByParent->get($parent->id, collect())->map(fn ($s) => (object) [
                    'id' => (int) $s->id,
                    'name' => $s->name,
                ])->values();

                return (object) [
                    'id' => (int) $parent->id,
                    'name' => $parent->name,
                    'subcategories' => $subs,
                ];
            });
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
