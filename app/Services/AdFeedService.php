<?php

namespace App\Services;

use App\Http\Resources\V1\AdResource;
use App\Models\Ad;
use Illuminate\Support\Collection;

class AdFeedService
{
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
            ->orderBy('created_at');

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

        $items = $posts->values()->all();
        $placedAds = [];
        $floatingAds = [];

        foreach ($ads as $ad) {
            $position = $ad->timeline_position;
            if ($position && $position > 0) {
                $placedAds[(int) $position][] = $ad;
            } else {
                $floatingAds[] = $ad;
            }
        }

        if (! empty($placedAds)) {
            ksort($placedAds);
            $offset = 0;

            foreach ($placedAds as $position => $bucket) {
                $index = max(0, min(count($items), $position - 1 + $offset));
                array_splice($items, $index, 0, $bucket);
                $offset += count($bucket);
            }
        }

        if (! empty($floatingAds)) {
            $basePostsCount = max(1, $posts->count());
            $gap = max(2, (int) ceil($basePostsCount / count($floatingAds)));
            $offset = 0;

            foreach ($floatingAds as $i => $ad) {
                $index = min(count($items), (($i + 1) * $gap) - 1 + $offset);
                array_splice($items, $index, 0, [$ad]);
                $offset++;
            }
        }

        return collect($items)->map(function ($item) {
            if ($item instanceof Ad) {
                return AdResource::make($item)->resolve();
            }

            return $item;
        })->values();
    }
}
