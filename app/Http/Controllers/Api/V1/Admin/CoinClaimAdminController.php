<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CoinClaimRequestResource;
use App\Models\CoinClaimRequest;
use Illuminate\Http\JsonResponse;

class CoinClaimAdminController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $items = CoinClaimRequest::with('user:id,display_name,first_name,last_name,phone')
            ->latest('created_at')
            ->paginate(20);

        return $this->success($items);
    }

    public function show(string $id): JsonResponse
    {
        $claim = CoinClaimRequest::with('user:id,display_name,first_name,last_name,phone,email')->findOrFail($id);

        return $this->success(new CoinClaimRequestResource($claim));
    }
}
