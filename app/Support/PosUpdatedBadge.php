<?php

namespace App\Support;

use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Cached POS-updated badge/count. Heavy scan runs only on refresh (AJAX / POS updated tab), not on every page.
 */
class PosUpdatedBadge
{
    public const CACHE_TTL_SECONDS = 120;

    public static function version(): int
    {
        return (int) Cache::get('pos_updated_cache_ver', 1);
    }

    public static function bumpVersion(): void
    {
        Cache::increment('pos_updated_cache_ver');
    }

    public static function cacheKey(User $user): string
    {
        return 'pos_updated_badge:'.self::version().':u'.$user->id.':'.$user->role;
    }

    /** Fast: read only. Never hits POS. */
    public static function cachedCount(User $user): int
    {
        $payload = Cache::get(self::cacheKey($user));
        if (! is_array($payload)) {
            return 0;
        }

        return (int) ($payload['count'] ?? 0);
    }

    /**
     * @return list<int>
     */
    public static function cachedIds(User $user): array
    {
        $payload = Cache::get(self::cacheKey($user));
        if (! is_array($payload) || ! isset($payload['ids']) || ! is_array($payload['ids'])) {
            return [];
        }

        return array_values(array_map('intval', $payload['ids']));
    }

    public static function hasFreshCache(User $user): bool
    {
        return is_array(Cache::get(self::cacheKey($user)));
    }

    /**
     * Run POS drift scan for this user's Jobs scope and cache the result.
     *
     * @return array{count: int, ids: list<int>}
     */
    public static function refresh(User $user): array
    {
        $printFramingQueueIds = null;
        if ($user->usesDedicatedPrintFramingJobPool() && ! $user->isEditor()) {
            $printFramingQueueIds = Job::applyJobPoolGateForDedicatedPool(
                Job::queryDedicatedPrintFramingJobPool($user),
                $user
            )->pluck('id')->all();
        }

        $query = Job::query()
            ->select(['id', 'source_id'])
            ->whereNotNull('source_id')
            ->where('source_id', '<>', '')
            ->whereNotIn('status', [Job::STATUS_DELIVERED])
            ->where(function ($q) {
                $q->whereIn('status', [Job::STATUS_NEW, Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS])
                    ->orWhere(function ($q2) {
                        $q2->where('status', Job::STATUS_COMPLETED)
                            ->where('updated_at', '>=', now()->subDays(60));
                    });
            })
            ->with(['edits' => fn ($eq) => $eq
                ->select([
                    'id',
                    'studio_job_id',
                    'name',
                    'source_product_id',
                    'source_sale_item_id',
                    'source_quantity_unit_index',
                    'source_quantity_unit_total',
                    'sort_order',
                ])
                ->orderBy('sort_order')]);

        if ($user->isEditor()) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_editor_id', $user->id)
                    ->orWhereHas('editors', fn ($q2) => $q2->where('user_id', $user->id));
            });
        } elseif ($printFramingQueueIds !== null) {
            $query->whereIn('id', $printFramingQueueIds);
        }

        $ids = PosJobLineDrift::driftedJobIdsAmong($query->get());
        $payload = [
            'count' => count($ids),
            'ids' => $ids,
            'checked_at' => now()->toIso8601String(),
        ];
        Cache::put(self::cacheKey($user), $payload, self::CACHE_TTL_SECONDS);

        return [
            'count' => $payload['count'],
            'ids' => $ids,
        ];
    }

    /**
     * Return cached payload, refreshing only when missing/stale and $allowRefresh is true.
     *
     * @return array{count: int, ids: list<int>}
     */
    public static function countAndIds(User $user, bool $allowRefresh = false): array
    {
        if (self::hasFreshCache($user)) {
            return [
                'count' => self::cachedCount($user),
                'ids' => self::cachedIds($user),
            ];
        }

        if (! $allowRefresh) {
            return ['count' => 0, 'ids' => []];
        }

        return self::refresh($user);
    }
}
