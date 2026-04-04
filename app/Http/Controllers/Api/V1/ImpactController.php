<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Impacts\StoreImpactRequest;
use App\Http\Resources\ImpactResource;
use App\Models\Impact;
use App\Services\Impacts\ImpactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ImpactController extends BaseApiController
{
    public function __construct(private readonly ImpactService $impactService)
    {
    }

    public function actions(): JsonResponse
    {
        return $this->success([
            'actions' => Impact::availableActions(),
            'requires_leadership_approval' => (bool) config('impact.requires_leadership_approval', true),
        ]);
    }

    public function store(StoreImpactRequest $request): JsonResponse
    {
        $impact = $this->impactService->submitImpact($request->user(), $request->validated());

        return $this->success(new ImpactResource($impact->load(['user', 'impactedPeer'])), 'Impact submitted successfully.', 201);
    }

    public function timeline(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));

        $impacts = Impact::query()
            ->with(['user:id,display_name,first_name,last_name', 'impactedPeer:id,display_name,first_name,last_name'])
            ->where('status', 'approved')
            ->whereNotNull('timeline_posted_at')
            ->orderByDesc('timeline_posted_at')
            ->paginate($perPage);

        return $this->success([
            'items' => ImpactResource::collection($impacts->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $impacts->currentPage(),
                'per_page' => $impacts->perPage(),
                'total' => $impacts->total(),
                'last_page' => $impacts->lastPage(),
            ],
        ]);
    }

    public function my(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $user = $request->user();

        $impacts = Impact::query()
            ->with(['user:id,display_name,first_name,last_name', 'impactedPeer:id,display_name,first_name,last_name'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $totalLifeImpacted = Schema::hasColumn('users', 'life_impacted_count')
            ? $this->impactService->recalculateUserLifeImpactedCount($user)
            : (int) Impact::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->sum('life_impacted');

        return $this->success([
            'total_life_impacted' => $totalLifeImpacted,
            'items' => ImpactResource::collection($impacts->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $impacts->currentPage(),
                'per_page' => $impacts->perPage(),
                'total' => $impacts->total(),
                'last_page' => $impacts->lastPage(),
            ],
        ]);
    }
}
