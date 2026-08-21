<?php

namespace App\Providers;

use App\Models\Job;
use App\Support\JobPoolEligibility;
use App\Support\PosUpdatedBadge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                $view->with([
                    'jobPoolNewCount' => 0,
                    'jobPoolNotifyTitle' => '',
                    'posUpdatedNavCount' => 0,
                ]);

                return;
            }

            if ($user->isDeliveryViewOnly()) {
                $view->with([
                    'jobPoolNewCount' => 0,
                    'jobPoolNotifyTitle' => '',
                    'posUpdatedNavCount' => 0,
                ]);

                return;
            }

            $allowedJobsSections = $user->allowedJobsListSections()
                ?? ['ongoing', 'pos_updated', 'edit_done', 'print_done', 'framing_done', 'completed', 'delivered', 'dismissed'];
            // Cache read only — never scan POS in the layout.
            $posUpdatedNavCount = in_array('pos_updated', $allowedJobsSections, true)
                ? PosUpdatedBadge::cachedCount($user)
                : 0;

            $conn = 'source';
            $dbName = config("database.connections.{$conn}.database");
            if (empty($dbName)) {
                $view->with([
                    'jobPoolNewCount' => 0,
                    'jobPoolNotifyTitle' => 'Job Pool: source database is not configured (DB_SOURCE_DATABASE).',
                    'posUpdatedNavCount' => $posUpdatedNavCount,
                ]);

                return;
            }

            $lastChecked = $user->job_pool_last_checked_at;
            $cacheKey = 'job_pool_badge:'.$user->id.':'.md5((string) ($lastChecked?->timestamp ?? 0).'|'.$user->role);

            try {
                $payload = Cache::remember($cacheKey, 60, function () use ($user, $conn, $lastChecked) {
                    $tz = config('app.timezone');
                    $minSaleDate = Carbon::parse(Job::SOURCE_JOB_POOL_MIN_SALE_DATE, $tz)->startOfDay();

                    $query = DB::connection($conn)
                        ->table('sma_sales')
                        ->where('pos', 1)
                        ->whereIn('payment_status', Job::SOURCE_JOB_POOL_PAYMENT_STATUSES)
                        ->where('date', '>=', $minSaleDate)
                        ->whereNotNull('due_date')
                        ->where('due_date', '<>', '0000-00-00')
                        ->where('due_date', '<>', '0000-00-00 00:00:00');

                    // Same started-job exclusion logic as Job Pool
                    $startedJobQuery = Job::whereNotNull('source_id')
                        ->whereIn('status', [
                            Job::STATUS_ASSIGNED,
                            Job::STATUS_IN_PROGRESS,
                            Job::STATUS_COMPLETED,
                            Job::STATUS_DELIVERED,
                        ]);

                    if ($user->isEditor()) {
                        $startedJobQuery->where(function ($q) use ($user) {
                            $q->where('assigned_editor_id', $user->id)
                                ->orWhereHas('editors', fn ($qq) => $qq->where('user_id', $user->id));
                        });
                    }

                    $usedSourceIds = $startedJobQuery
                        ->pluck('source_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if (! empty($usedSourceIds) && ! $user->usesDedicatedPrintFramingJobPool()) {
                        $query->whereNotIn('id', $usedSourceIds);
                    }

                    if ($lastChecked) {
                        $query->where('date', '>', $lastChecked);
                    }

                    JobPoolEligibility::constrainSalesQuery($query, $user, $conn);

                    $count = (int) $query->count();

                    return [
                        'count' => $count,
                        'title' => $lastChecked
                            ? ($count > 0
                                ? "Job Pool: {$count} POS sale(s) with sale date after your last visit (".$lastChecked->timezone(config('app.timezone'))->format('M j, g:i A').'). Open Job Pool to clear the badge.'
                                : 'Job Pool: no new POS rows since your last visit. Open Job Pool to refresh.')
                            : "Job Pool: {$count} eligible sale(s) not yet opened as jobs (first visit or never cleared). Open Job Pool to mark as seen.",
                    ];
                });

                $view->with([
                    'jobPoolNewCount' => $payload['count'],
                    'jobPoolNotifyTitle' => $payload['title'],
                    'posUpdatedNavCount' => $posUpdatedNavCount,
                ]);
            } catch (\Throwable $e) {
                $view->with([
                    'jobPoolNewCount' => 0,
                    'jobPoolNotifyTitle' => 'Job Pool notification: could not count (check source DB connection).',
                    'posUpdatedNavCount' => $posUpdatedNavCount,
                ]);
            }
        });
    }
}
