<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Requirements\InterestRequirementRequest;
use App\Models\Requirement;
use App\Models\RequirementInterest;
use App\Services\Requirements\RequirementNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class RequirementInterestController extends Controller
{
    public function __construct(private readonly RequirementNotificationService $requirementNotificationService)
    {
    }

    public function store(InterestRequirementRequest $request, Requirement $requirement): JsonResponse
    {
        if ($requirement->status !== 'open') {
            return response()->json([
                'status' => false,
                'message' => 'Interest is allowed only for open requirements.',
                'data' => null,
                'meta' => null,
            ], 422);
        }

        $interest = RequirementInterest::query()->updateOrCreate(
            [
                'requirement_id' => $requirement->id,
                'user_id' => $request->user()->id,
            ],
            [
                'source' => $request->input('source', 'interest_button'),
                'comment' => $request->input('comment'),
            ]
        );

        $requirement->loadMissing('user');

        try {
            $this->requirementNotificationService->notifyRequirementInterest(
                $requirement,
                $request->user(),
                $interest->comment
            );
        } catch (Throwable $exception) {
            Log::warning('Interest saved but creator notification failed.', [
                'requirement_id' => (string) $requirement->id,
                'user_id' => (string) $request->user()->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Interest registered successfully.',
            'data' => [
                'id' => $interest->id,
                'requirement_id' => $interest->requirement_id,
                'user_id' => $interest->user_id,
                'source' => $interest->source,
                'comment' => $interest->comment,
                'created_at' => optional($interest->created_at)?->toISOString(),
            ],
            'meta' => null,
        ]);
    }
}
