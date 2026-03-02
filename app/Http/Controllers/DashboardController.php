<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobEdit;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        if ($user->isAdmin() || $user->isManager()) {
            $stats = [
                'total' => Job::count(),
                'new' => Job::where('status', Job::STATUS_NEW)->count(),
                'in_progress' => Job::whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS])->count(),
                'completed' => Job::where('status', Job::STATUS_COMPLETED)->count(),
                'delivered' => Job::where('status', Job::STATUS_DELIVERED)->count(),
            ];
            $ongoingJobs = Job::with(['editor', 'editors'])->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS])->latest()->take(15)->get();
            $recentJobs = Job::with(['editor', 'editors'])->latest()->take(10)->get();
            $recentActivityCount = ActivityLog::where('created_at', '>=', now()->subDays(7))->count();
            return view('dashboard.owner-manager', compact('stats', 'ongoingJobs', 'recentJobs', 'recentActivityCount'));
        }

        if ($user->isEditor()) {
            $myJobs = Job::where(function ($q) use ($user) {
                $q->where('assigned_editor_id', $user->id)->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id));
            })
                ->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS])
                ->withCount('edits')
                ->latest()
                ->get();
            $availableCount = Job::where('status', Job::STATUS_NEW)->count();
            $myCompletedCount = Job::where(function ($q) use ($user) {
                $q->where('assigned_editor_id', $user->id)->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id));
            })->whereIn('status', [Job::STATUS_COMPLETED, Job::STATUS_DELIVERED])->count();
            return view('dashboard.editor', compact('myJobs', 'availableCount', 'myCompletedCount'));
        }

        if ($user->isPrinter()) {
            $editsPendingPrint = JobEdit::whereIn('print_status', [
                JobEdit::PRINT_STATUS_PENDING,
                JobEdit::PRINT_STATUS_SENT_TO_PRINT,
            ])->with('job')->orderBy('updated_at', 'desc')->take(20)->get();
            $pendingCount = JobEdit::whereIn('print_status', [
                JobEdit::PRINT_STATUS_PENDING,
                JobEdit::PRINT_STATUS_SENT_TO_PRINT,
            ])->count();
            $printedToday = JobEdit::where('print_status', JobEdit::PRINT_STATUS_PRINTED)
                ->whereDate('updated_at', today())->count();
            return view('dashboard.printer', compact('editsPendingPrint', 'pendingCount', 'printedToday'));
        }

        if ($user->isSales() || $user->isDelivery()) {
            $readyForDelivery = Job::where('status', Job::STATUS_COMPLETED)->with('editor')->latest()->take(15)->get();
            $readyCount = Job::where('status', Job::STATUS_COMPLETED)->count();
            $deliveredToday = Job::where('status', Job::STATUS_DELIVERED)->where('delivered_by', $user->id)
                ->whereDate('delivered_at', today())->count();
            return view('dashboard.cashier', compact('readyForDelivery', 'readyCount', 'deliveredToday'));
        }

        return view('dashboard.default');
    }
}
