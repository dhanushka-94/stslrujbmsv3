<?php

namespace App\Providers;

use App\Models\Job;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                ]);

                return;
            }

            $conn = 'source';
            $dbName = config("database.connections.{$conn}.database");
            if (empty($dbName)) {
                $view->with([
                    'jobPoolNewCount' => 0,
                    'jobPoolNotifyTitle' => 'Job Pool: source database is not configured (DB_SOURCE_DATABASE).',
                ]);

                return;
            }

            $lastChecked = $user->job_pool_last_checked_at;

            try {
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
                $startedJobQuery = \App\Models\Job::whereNotNull('source_id')
                    ->whereIn('status', [
                        \App\Models\Job::STATUS_ASSIGNED,
                        \App\Models\Job::STATUS_IN_PROGRESS,
                        \App\Models\Job::STATUS_COMPLETED,
                        \App\Models\Job::STATUS_DELIVERED,
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

                if ($user->isEditor()) {
                    $allowed = $user->assignedCategoryIds();
                    if (! empty($allowed)) {
                        $query->whereExists(function ($q) use ($allowed) {
                            $q->select(DB::raw(1))
                                ->from('sma_sale_items as si')
                                ->leftJoin('sma_products as p', 'si.product_id', '=', 'p.id')
                                ->whereColumn('si.sale_id', 'sma_sales.id')
                                ->whereIn('p.category_id', $allowed);
                        });
                    }
                }

                $count = $query->count();
                $view->with([
                    'jobPoolNewCount' => $count,
                    'jobPoolNotifyTitle' => $lastChecked
                        ? ($count > 0
                            ? "Job Pool: {$count} POS sale(s) with sale date after your last visit (".($lastChecked->timezone(config('app.timezone'))->format('M j, g:i A'))."). Open Job Pool to clear the badge."
                            : 'Job Pool: no new POS rows since your last visit. Open Job Pool to refresh.')
                        : "Job Pool: {$count} eligible sale(s) not yet opened as jobs (first visit or never cleared). Open Job Pool to mark as seen.",
                ]);
            } catch (\Throwable $e) {
                $view->with([
                    'jobPoolNewCount' => 0,
                    'jobPoolNotifyTitle' => 'Job Pool notification: could not count (check source DB connection).',
                ]);
            }
        });
    }
}
