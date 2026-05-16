<?php

namespace App\Http\Controllers;

use App\Models\JobEdit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Reports hub: links to My report, Activity log (admin), Editor time report, and User reports list (admin).
     */
    public function index(): View
    {
        $users = (auth()->user()->isAdmin() || auth()->user()->isManager())
            ? User::orderBy('name')->get(['id', 'name', 'email', 'role'])
            : collect();

        return view('reports.index', compact('users'));
    }

    /**
     * Editor time report: estimated minutes per editor (summary + detail).
     * Accessible by Admin and Manager.
     */
    public function editorTimeReport(Request $request): View
    {
        if (! auth()->user()->isAdmin() && ! auth()->user()->isManager()) {
            abort(403);
        }

        $editorRoleIds = [
            User::ROLE_EDITOR,
            User::ROLE_EDITOR_PRINTER,
            User::ROLE_EDITOR_PRINTER_FRAMING,
            User::ROLE_FRAMING,
            User::ROLE_PRINTER_FRAMING,
        ];
        $editors = User::whereIn('role', $editorRoleIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        // Summary: total estimated_minutes and item count per editor (claimed_by_user_id, where estimated_minutes is set)
        $summaryQuery = JobEdit::query()
            ->whereNotNull('claimed_by_user_id')
            ->whereNotNull('estimated_minutes')
            ->selectRaw('claimed_by_user_id as user_id, SUM(estimated_minutes) as total_minutes, COUNT(*) as item_count')
            ->groupBy('claimed_by_user_id');

        if ($request->filled('editor_id')) {
            $summaryQuery->where('claimed_by_user_id', $request->integer('editor_id'));
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

        if ($request->filled('editor_id')) {
            $summary = $summary->filter(fn ($row) => $row['user']->id == $request->integer('editor_id'))->values();
        }

        // Detail: all job_edits with estimated_minutes (for table)
        $detailQuery = JobEdit::query()
            ->whereNotNull('estimated_minutes')
            ->with(['job:id,ref_number', 'claimedByUser:id,name,role'])
            ->orderByDesc('estimated_minutes_at');

        if ($request->filled('editor_id')) {
            $detailQuery->where('claimed_by_user_id', $request->integer('editor_id'));
        }
        if ($request->filled('from')) {
            $detailQuery->whereDate('estimated_minutes_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $detailQuery->whereDate('estimated_minutes_at', '<=', $request->input('to'));
        }

        $detail = $detailQuery->get();

        $filterEditorId = $request->input('editor_id');
        $filterFrom = $request->input('from');
        $filterTo = $request->input('to');

        return view('reports.editor-time', compact(
            'editors',
            'summary',
            'detail',
            'filterEditorId',
            'filterFrom',
            'filterTo'
        ));
    }
}
