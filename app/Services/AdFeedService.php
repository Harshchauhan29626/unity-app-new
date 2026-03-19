<?php

namespace App\Services;

use App\Http\Resources\V1\AdResource;
use App\Models\Ad;
use Illuminate\Support\Collection;

class AdFeedService
{
    private const AUTO_AD_INTERVAL = 5;

    public function timelineAds(?int $limit = null): Collection
    {
        $now = now();

        $query = Ad::query()
            ->whereRaw('LOWER(placement) = ?', ['timeline'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($builder) use ($now) {
                $builder->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($builder) use ($now) {
                $builder->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderByRaw('CASE WHEN timeline_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('timeline_position')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function mergeTimelineFeed(Collection $posts, Collection $ads): Collection
    {
        if ($ads->isEmpty()) {
            return $posts->values();
        }

        $postItems = $posts->values()->all();

        $manualPositionAds = $ads
            ->filter(fn (Ad $ad) => ! is_null($ad->timeline_position) && (int) $ad->timeline_position > 0)
            ->groupBy(fn (Ad $ad) => (int) $ad->timeline_position)
            ->map(function (Collection $group) {
                return $group->sortBy([
                    ['sort_order', 'asc'],
                    ['created_at', 'asc'],
                    ['id', 'asc'],
                ])->values()->all();
            })
            ->sortKeys();

        $automaticAds = $ads
            ->filter(fn (Ad $ad) => is_null($ad->timeline_position) || (int) $ad->timeline_position <= 0)
            ->sortBy([
                ['sort_order', 'asc'],
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();

        $manualMerged = $this->mergeManualPositionAds($postItems, $manualPositionAds);

        return $this->distributeAutomaticAds($manualMerged, $automaticAds);
    }

    private function mergeManualPositionAds(array $postItems, Collection $manualPositionAds): array
    {
        $result = [];
        $postIndex = 0;
        $slot = 1;

        $maxSlot = max(
            count($postItems) + $manualPositionAds->flatten(1)->count(),
            (int) ($manualPositionAds->keys()->max() ?? 0)
        );

        while ($slot <= $maxSlot || $postIndex < count($postItems)) {
            if ($manualPositionAds->has($slot)) {
                foreach ($manualPositionAds->get($slot) as $ad) {
                    $result[] = $this->transformAd($ad);
                }
                $manualPositionAds->forget($slot);
            }

            if ($postIndex < count($postItems)) {
                $result[] = $postItems[$postIndex];
                $postIndex++;
            }

            $slot++;
        }

        foreach ($manualPositionAds as $group) {
            foreach ($group as $ad) {
                $result[] = $this->transformAd($ad);
            }
        }

        return $result;
    }

    private function distributeAutomaticAds(array $items, array $automaticAds): Collection
    {
        if (empty($automaticAds)) {
            return collect($items)->values();
        }

        $result = [];
        $postCount = 0;
        $autoAdIndex = 0;

        foreach ($items as $item) {
            $result[] = $item;

            if (($item['type'] ?? null) !== 'post') {
                continue;
            }

            $postCount++;

            if ($postCount % self::AUTO_AD_INTERVAL === 0 && isset($automaticAds[$autoAdIndex])) {
                $result[] = $this->transformAd($automaticAds[$autoAdIndex]);
                $autoAdIndex++;
            }
        }

        return collect($result)->values();
    }

    private function transformAd(Ad $ad): array
    {
        return AdResource::make($ad)->resolve();
    }
}
