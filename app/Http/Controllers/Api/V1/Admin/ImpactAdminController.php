<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\Impacts\ReviewImpactRequest;
use App\Http\Resources\ImpactResource;
use App\Models\Impact;
use App\Models\Role;
use App\Services\Impacts\ImpactService;
use Illuminate\Http\JsonResponse;

class ImpactAdminController extends BaseApiController
{
    public function __construct(private readonly ImpactService $impactService)
    {
    }

    public function approve(ReviewImpactRequest $request, Impact $impact): JsonResponse
    {
        $user = $request->user();

        if (! $this->canReview($user)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to review impacts.',
            ], 403);
        }

        try {
            $approved = $this->impactService->approveImpact($impact, $user, $request->input('review_remarks'));

            return response()->json([
                'status' => true,
                'message' => 'Impact approved successfully.',
                'data' => (new ImpactResource($approved->load(['user', 'impactedPeer'])))->resolve(),
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function reject(ReviewImpactRequest $request, Impact $impact): JsonResponse
    {
        $user = $request->user();

        if (! $this->canReview($user)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to review impacts.',
            ], 403);
        }

        try {
            $rejected = $this->impactService->rejectImpact($impact, $user, $request->input('review_remarks'));

            return response()->json([
                'status' => true,
                'message' => 'Impact rejected successfully.',
                'data' => (new ImpactResource($rejected->load(['user', 'impactedPeer'])))->resolve(),
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function canReview($user): bool
    {
        if (! $user) {
            return false;
        }

        $roleIds = Role::query()->whereIn('key', ['global_admin', 'industry_director', 'ded'])->pluck('id');

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }
}
