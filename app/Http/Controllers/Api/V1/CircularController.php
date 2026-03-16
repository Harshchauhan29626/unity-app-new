<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CircularDetailResource;
use App\Http\Resources\CircularListResource;
use App\Models\Circular;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CircularController extends BaseApiController
{
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $now = now();
        $userCircleIds = $this->userCircleIds($user);

        $query = Circular::query()->visibleNow();

        $this->applyAudienceFilter($query, $user, $userCircleIds);

        $query->orderedForFeed();

        $perPage = (int) min(max((int) $request->query('per_page', 20), 1), 100);

        $circulars = $query->paginate($perPage);

        Log::debug('Circular list query evaluated.', [
            'user_id' => $user->id,
            'user_city_id' => $user->city_id,
            'user_circle_ids' => $userCircleIds,
            'timezone' => config('app.timezone'),
            'now' => $now->toIso8601String(),
            'result_count' => $circulars->count(),
            'result_total' => $circulars->total(),
        ]);

        return $this->success([
            'items' => CircularListResource::collection($circulars),
            'pagination' => [
                'current_page' => $circulars->currentPage(),
                'last_page' => $circulars->lastPage(),
                'per_page' => $circulars->perPage(),
                'total' => $circulars->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $userCircleIds = $this->userCircleIds($user);

        $query = Circular::query()->visibleNow()->where('id', $id);

        $this->applyAudienceFilter($query, $user, $userCircleIds);

        $circular = $query->first();

        Log::debug('Circular detail query evaluated.', [
            'user_id' => $user->id,
            'circular_id' => $id,
            'user_circle_ids' => $userCircleIds,
            'found' => (bool) $circular,
        ]);

        if (! $circular) {
            return $this->error('Circular not found.', 404);
        }

        return $this->success(new CircularDetailResource($circular));
    }

    /**
     * Apply audience targeting only.
     *
     * Note: city_id and circle_id are audience constraints only for matching audience types,
     * they are not global hard filters for all_members circulars.
     */
    private function applyAudienceFilter(Builder $query, User $user, array $userCircleIds): void
    {
        $isFempreneur = $this->userHasSegment($user, 'fempreneur');
        $isGreenpreneur = $this->userHasSegment($user, 'greenpreneur');

        $query->where(function (Builder $audience) use ($user, $userCircleIds, $isFempreneur, $isGreenpreneur): void {
            // 1) all_members: visible to every authenticated app user.
            $audience->where(function (Builder $allMembers): void {
                $allMembers->where('audience_type', 'all_members');
            });

            // 2) circle_members: user must belong to circular's circle_id.
            $audience->orWhere(function (Builder $circleMembers) use ($userCircleIds): void {
                $circleMembers->where('audience_type', 'circle_members')
                    ->whereNotNull('circle_id')
                    ->when(
                        $userCircleIds !== [],
                        fn (Builder $q) => $q->whereIn('circle_id', $userCircleIds),
                        fn (Builder $q) => $q->whereRaw('1 = 0')
                    );
            });

            // 3) fempreneur: include only users matching fempreneur segment logic.
            if ($isFempreneur) {
                $audience->orWhere(function (Builder $fempreneur) use ($user): void {
                    $fempreneur->where('audience_type', 'fempreneur')
                        ->where(function (Builder $city) use ($user): void {
                            $city->whereNull('city_id')
                                ->orWhere('city_id', $user->city_id);
                        });
                });
            }

            // 4) greenpreneur: include only users matching greenpreneur segment logic.
            if ($isGreenpreneur) {
                $audience->orWhere(function (Builder $greenpreneur) use ($user): void {
                    $greenpreneur->where('audience_type', 'greenpreneur')
                        ->where(function (Builder $city) use ($user): void {
                            $city->whereNull('city_id')
                                ->orWhere('city_id', $user->city_id);
                        });
                });
            }
        });
    }

    private function userCircleIds(User $user): array
    {
        return CircleMember::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $statusQuery): void {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            ->pluck('circle_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function userHasSegment(User $user, string $segment): bool
    {
        // TODO: Replace this fallback check with project-specific segment mapping once finalized.
        $segment = strtolower($segment);

        foreach (["is_{$segment}", $segment] as $flagKey) {
            $value = data_get($user, $flagKey);
            if (is_bool($value) && $value === true) {
                return true;
            }
            if (is_string($value) && in_array(strtolower($value), ['1', 'yes', 'true', $segment], true)) {
                return true;
            }
        }

        foreach (['business_type', 'designation', 'short_bio', 'long_bio_html', 'company_name'] as $textColumn) {
            $value = strtolower((string) data_get($user, $textColumn));
            if ($value !== '' && str_contains($value, $segment)) {
                return true;
            }
        }

        return false;
    }
}
