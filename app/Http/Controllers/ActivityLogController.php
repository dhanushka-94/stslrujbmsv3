<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function show(ActivityLog $activity_log): View
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }
        $activity_log->load('user');
        $relatedJob = null;
        if ($activity_log->subject_type === 'job' && $activity_log->subject_id) {
            $relatedJob = Job::find($activity_log->subject_id);
        }

        return view('activity-log.show', compact('activity_log', 'relatedJob'));
    }

    public function index(Request $request): View
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }
        $query = ActivityLog::with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);
        $actions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return view('activity-log.index', compact('logs', 'users', 'actions'));
    }
}
