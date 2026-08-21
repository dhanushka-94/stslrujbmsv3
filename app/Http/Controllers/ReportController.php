<?php

namespace App\Http\Controllers;

use App\Models\JobEdit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Reports hub: links to My report, Activity log (admin), Editor time report, and User reports list (admin).
     */
    public function index(): View|RedirectResponse
    {
        $viewer = auth()->user();
        if ($viewer->usesJobsAndJobPoolNavOnly()) {
            return redirect()->route('jobs.index');
        }
        $users = $viewer->canViewOtherUsersReports()
            ? User::orderBy('name')->get(['id', 'name', 'email', 'role'])
            : collect();

        return view('reports.index', compact('users'));
    }

    /**
     * Editor time report: estimated minutes per editor (summary + detail).
     * Admin/Manager: all editors. Editors: own claimed lines only.
     */
    public function editorTimeReport(Request $request): View
    {
        $viewer = auth()->user();
        if (! $viewer->canViewEditorTimeReport()) {
            abort(403);
        }

        $viewAllEditors = $viewer->canViewAllEditorsTimeReport();

        if (! $viewAllEditors && ! $viewer->canViewOwnEditorTimeReport()) {
            abort(403);
        }

        if (! $viewAllEditors) {
            if ($request->filled('editor_id') && $request->integer('editor_id') !== $viewer->id) {
                abort(403);
            }
        }

        $editorsQuery = User::whereIn('role', User::rolesInEditorTimeReport())->orderBy('name');
        if (! $viewAllEditors) {
            $editorsQuery->whereKey($viewer->id);
        }
        $editors = $editorsQuery->get(['id', 'name', 'email', 'role']);

        $filterEditorId = $viewAllEditors && $request->filled('editor_id')
            ? $request->integer('editor_id')
            : ($viewAllEditors ? null : $viewer->id);

        // Summary: total estimated_minutes and item count per editor (claimed_by_user_id, where estimated_minutes is set)
        $summaryQuery = JobEdit::query()
            ->whereNotNull('claimed_by_user_id')
            ->whereNotNull('estimated_minutes')
            ->selectRaw('claimed_by_user_id as user_id, SUM(estimated_minutes) as total_minutes, COUNT(*) as item_count')
            ->groupBy('claimed_by_user_id');

        if ($filterEditorId) {
            $summaryQuery->where('claimed_by_user_id', $filterEditorId);
        }
        if ($request->filled('from')) {
            $summaryQuery->whereDate('estimated_minutes_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $summaryQuery->whereDate('estimated_minutes_at', '<=', $request->input('to'));
        }

        $summaryRows = $summaryQuery->get()->keyBy('user_id');

        // Build summary per editor (include editors with 0 if no filter)
        $summary = $editors->map(function (User $u) use ($summaryRows) {
            $row = $summaryRows->get($u->id);
            return [
                'user' => $u,
                'total_minutes' => $row ? (int) $row->total_minutes : 0,
                'item_count' => $row ? (int) $row->item_count : 0,
            ];
        });

        if ($filterEditorId) {
            $summary = $summary->filter(fn ($row) => $row['user']->id == $filterEditorId)->values();
        }

        // Detail: all job_edits with estimated_minutes (for table)
        $detailQuery = JobEdit::query()
            ->whereNotNull('estimated_minutes')
            ->with(['job:id,ref_number', 'claimedByUser:id,name,role'])
            ->orderByDesc('estimated_minutes_at');

        if ($filterEditorId) {
            $detailQuery->where('claimed_by_user_id', $filterEditorId);
        }
        if ($request->filled('from')) {
            $detailQuery->whereDate('estimated_minutes_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $detailQuery->whereDate('estimated_minutes_at', '<=', $request->input('to'));
        }

        $detail = $detailQuery->get();

        $filterFrom = $request->input('from');
        $filterTo = $request->input('to');

        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);
        $periodTotals = [
            'today' => self::estimatedTimeTotalsForPeriod(
                $filterEditorId,
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay()
            ),
            'this_month' => self::estimatedTimeTotalsForPeriod(
                $filterEditorId,
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            ),
            'last_month' => self::estimatedTimeTotalsForPeriod(
                $filterEditorId,
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth()
            ),
        ];

        $periodScopeLabel = null;
        if ($filterEditorId) {
            $periodScopeLabel = $editors->firstWhere('id', $filterEditorId)?->name;
        } elseif ($viewAllEditors) {
            $periodScopeLabel = 'All editors';
        }

        $lastMonthLabel = $now->copy()->subMonth()->format('F Y');

        return view('reports.editor-time', compact(
            'editors',
            'summary',
            'detail',
            'filterEditorId',
            'filterFrom',
            'filterTo',
            'viewAllEditors',
            'periodTotals',
            'periodScopeLabel',
            'lastMonthLabel'
        ));
    }

    /**
     * @return array{minutes: int, items: int, formatted: string, hours: float}
     */
    private static function estimatedTimeTotalsForPeriod(?int $editorId, Carbon $from, Carbon $to): array
    {
        $query = JobEdit::query()
            ->whereNotNull('estimated_minutes')
            ->whereNotNull('estimated_minutes_at')
            ->whereBetween('estimated_minutes_at', [$from, $to]);

        if ($editorId) {
            $query->where('claimed_by_user_id', $editorId);
        }

        $row = $query
            ->selectRaw('COALESCE(SUM(estimated_minutes), 0) as total_minutes, COUNT(*) as item_count')
            ->first();

        $minutes = (int) ($row->total_minutes ?? 0);
        $items = (int) ($row->item_count ?? 0);

        return [
            'minutes' => $minutes,
            'items' => $items,
            'formatted' => self::formatEstimatedMinutes($minutes),
            'hours' => $minutes > 0 ? round($minutes / 60, 1) : 0.0,
        ];
    }

    private static function formatEstimatedMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return $hours.'h '.$mins.'m';
        }

        if ($hours > 0) {
            return $hours.'h';
        }

        return $mins.' min';
    }
}
