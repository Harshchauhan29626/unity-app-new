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

class CircularController extends BaseApiController
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $query = Circular::query()
            ->visibleNow()
            ->orderedForFeed();

        $this->applyAudienceFilter($query, $user);

        $circulars = $query->paginate((int) min(max((int) $request->query('per_page', 20), 1), 100));

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
        /** @var User $user */
        $user = $request->user();

        $query = Circular::query()->visibleNow()->where('id', $id);
        $this->applyAudienceFilter($query, $user);

        $circular = $query->first();

        if (! $circular) {
            return $this->error('Circular not found.', 404);
        }

        return $this->success(new CircularDetailResource($circular));
    }

    private function applyAudienceFilter(Builder $query, User $user): void
    {
        $isFempreneur = $this->userHasSegment($user, 'fempreneur');
        $isGreenpreneur = $this->userHasSegment($user, 'greenpreneur');

        $query->where(function (Builder $scope) use ($user, $isFempreneur, $isGreenpreneur): void {
            $scope->where('audience_type', 'all_members')
                ->orWhere(function (Builder $circleScope) use ($user): void {
                    $circleScope->where('audience_type', 'circle_members')
                        ->whereNotNull('circle_id')
                        ->whereIn('circle_id', function ($subQuery) use ($user): void {
                            $subQuery->select('circle_id')
                                ->from((new CircleMember())->getTable())
                                ->where('user_id', $user->id)
                                ->where(function ($statusQuery): void {
                                    $statusQuery->whereNull('status')->orWhere('status', 'active');
                                });
                        });
                });

            if ($isFempreneur) {
                $scope->orWhere('audience_type', 'fempreneur');
            }

            if ($isGreenpreneur) {
                $scope->orWhere('audience_type', 'greenpreneur');
            }
        });

        $query->where(function (Builder $cityScope) use ($user): void {
            $cityScope->whereNull('city_id');

            if ($user->city_id) {
                $cityScope->orWhere('city_id', $user->city_id);
            }
        });

        $query->where(function (Builder $circleScope) use ($user): void {
            $circleScope->whereNull('circle_id')
                ->orWhereIn('circle_id', function ($subQuery) use ($user): void {
                    $subQuery->select('circle_id')
                        ->from((new CircleMember())->getTable())
                        ->where('user_id', $user->id)
                        ->where(function ($statusQuery): void {
                            $statusQuery->whereNull('status')->orWhere('status', 'active');
                        });
                });
        });
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
