<?php

namespace App\Http\Controllers\Api;

use App\Models\Circular;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CircularController extends BaseApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $now = now(config('app.timezone'));

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $userCityId = $user?->city_id;
        $userCircleIds = $this->resolveUserCircleIds($user);
        $userType = strtolower((string) ($user->membership_type ?? $user->member_type ?? $user->persona ?? ''));

        $query = Circular::query()->visibleInApp($now);
        $baseVisibleCount = (clone $query)->count();
        $baseVisibleIds = (clone $query)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        $this->applyAudienceFilter($query, $userCityId, $userCircleIds, $userType);
        $afterTargetingCount = (clone $query)->count();
        $afterTargetingIds = (clone $query)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        $query->orderByDesc('is_pinned')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'important' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('publish_date');

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (Circular $circular) => [
            'id' => (string) $circular->id,
            'title' => $circular->title,
            'summary' => $circular->summary,
            'category' => $circular->category,
            'priority' => $circular->priority,
            'featured_image_url' => $circular->featured_image_url,
            'publish_date' => optional($circular->publish_date)?->toIso8601String(),
            'slug' => $circular->slug,
            'cta_label' => $circular->cta_label,
            'cta_url' => $circular->cta_url,
            'view_count' => (int) $circular->view_count,
            'is_pinned' => (bool) $circular->is_pinned,
            'allow_comments' => (bool) $circular->allow_comments,
        ])->values();

        Log::info('Circular feed filters applied', [
            'user_id' => (string) ($user?->id ?? ''),
            'user_city_id' => $userCityId,
            'user_circle_ids' => $userCircleIds,
            'base_visible_count' => $baseVisibleCount,
            'base_visible_ids' => $baseVisibleIds,
            'after_targeting_count' => $afterTargetingCount,
            'after_targeting_ids' => $afterTargetingIds,
            'final_response_count' => $items->count(),
            'final_returned_ids' => $items->pluck('id')->values()->all(),
        ]);

        return $this->success([
            'items' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function show(Request $request, string $identifier)
    {
        $user = $request->user();
        $now = now(config('app.timezone'));

        $userCityId = $user?->city_id;
        $userCircleIds = $this->resolveUserCircleIds($user);
        $userType = strtolower((string) ($user->membership_type ?? $user->member_type ?? $user->persona ?? ''));

        $query = Circular::query()
            ->visibleInApp($now)
            ->with(['city:id,name', 'circle:id,name']);

        $this->applyAudienceFilter($query, $userCityId, $userCircleIds, $userType);

        $circular = (clone $query)
            ->where(function (Builder $identifierQuery) use ($identifier): void {
                $identifierQuery->where('slug', $identifier)->orWhere('id', $identifier);
            })
            ->first();

        if (! $circular) {
            return $this->error('Circular not found.', 404);
        }

        Circular::query()->whereKey($circular->id)->increment('view_count');
        $circular->refresh()->loadMissing(['city:id,name', 'circle:id,name']);

        return $this->success([
            'id' => (string) $circular->id,
            'title' => $circular->title,
            'summary' => $circular->summary,
            'content' => $circular->content,
            'featured_image_url' => $circular->featured_image_url,
            'attachment_url' => $circular->attachment_url,
            'video_url' => $circular->video_url,
            'category' => $circular->category,
            'priority' => $circular->priority,
            'publish_date' => optional($circular->publish_date)?->toIso8601String(),
            'expiry_date' => optional($circular->expiry_date)?->toIso8601String(),
            'cta_label' => $circular->cta_label,
            'cta_url' => $circular->cta_url,
            'allow_comments' => (bool) $circular->allow_comments,
            'send_push_notification' => (bool) $circular->send_push_notification,
            'is_pinned' => (bool) $circular->is_pinned,
            'city' => $circular->city ? ['id' => $circular->city->id, 'name' => $circular->city->name] : null,
            'circle' => $circular->circle ? ['id' => $circular->circle->id, 'name' => $circular->circle->name] : null,
            'view_count' => (int) $circular->view_count,
        ]);
    }

    private function applyAudienceFilter(Builder $query, $userCityId, array $userCircleIds, string $userType): void
    {
        $hasCity = filled($userCityId);
        $hasCircles = $userCircleIds !== [];

        if (! $hasCity && ! $hasCircles && $userType === '') {
            return;
        }

        $query->where(function (Builder $audienceQuery) use ($userCityId, $userCircleIds, $userType, $hasCity, $hasCircles): void {
            $audienceQuery->where(function (Builder $allMembersQuery) use ($userCityId, $userCircleIds, $hasCity, $hasCircles): void {
                $allMembersQuery
                    ->where(function (Builder $typeQuery): void {
                        $typeQuery->whereNull('audience_type')->orWhere('audience_type', 'all_members');
                    })
                    ->where(function (Builder $cityQuery) use ($userCityId, $hasCity): void {
                        if (! $hasCity) {
                            $cityQuery->whereRaw('1=1');

                            return;
                        }

                        $cityQuery->whereNull('city_id')->orWhere('city_id', $userCityId);
                    })
                    ->where(function (Builder $circleQuery) use ($userCircleIds, $hasCircles): void {
                        if (! $hasCircles) {
                            $circleQuery->whereRaw('1=1');

                            return;
                        }

                        $circleQuery->whereNull('circle_id')->orWhereIn('circle_id', $userCircleIds);
                    });
            });

            $audienceQuery->orWhere(function (Builder $circleMembersQuery) use ($userCircleIds, $hasCircles): void {
                $circleMembersQuery->where('audience_type', 'circle_members');

                if (! $hasCircles) {
                    $circleMembersQuery->whereNull('circle_id');

                    return;
                }

                $circleMembersQuery->where(function (Builder $circleScope) use ($userCircleIds): void {
                    $circleScope->whereNull('circle_id')->orWhereIn('circle_id', $userCircleIds);
                });
            });

            $audienceQuery->orWhere(function (Builder $fempreneurQuery) use ($userType): void {
                $fempreneurQuery->where('audience_type', 'fempreneur');

                if ($userType !== '' && $userType !== 'fempreneur') {
                    $fempreneurQuery->whereRaw('1=0');
                }
            });

            $audienceQuery->orWhere(function (Builder $greenpreneurQuery) use ($userType): void {
                $greenpreneurQuery->where('audience_type', 'greenpreneur');

                if ($userType !== '' && $userType !== 'greenpreneur') {
                    $greenpreneurQuery->whereRaw('1=0');
                }
            });
        });
    }

    private function resolveUserCircleIds($user): array
    {
        if (! $user?->id || ! Schema::hasTable('circle_members') || ! Schema::hasColumn('circle_members', 'user_id') || ! Schema::hasColumn('circle_members', 'circle_id')) {
            return [];
        }

        return DB::table('circle_members')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereNotNull('circle_id')
            ->where('status', 'approved')
            ->pluck('circle_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
