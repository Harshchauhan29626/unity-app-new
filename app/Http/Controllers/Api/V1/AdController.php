<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Ads\IndexAdRequest;
use App\Http\Resources\V1\AdResource;
use App\Models\Ad;
use App\Services\AdFeedService;

class AdController extends BaseApiController
{
    public function index(IndexAdRequest $request)
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $ads = Ad::query()
            ->currentlyVisible()
            ->when(! empty($filters['placement']), fn ($query) => $query->where('placement', $filters['placement']))
            ->when(! empty($filters['page_name']), fn ($query) => $query->where('page_name', $filters['page_name']))
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->success([
            'items' => AdResource::collection($ads->getCollection()),
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $ad = Ad::query()->currentlyVisible()->find($id);

        if (! $ad) {
            return $this->error('Ad not found', 404);
        }

        return $this->success(AdResource::make($ad));
    }

    public function timeline(AdFeedService $adFeedService)
    {
        $ads = $adFeedService->timelineAds();

        return $this->success(AdResource::collection($ads));
    }
}
