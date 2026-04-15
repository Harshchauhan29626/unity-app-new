<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\LifeImpact\LifeImpactHistoryResource;
use App\Models\LifeImpactHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LifeImpactHistoryController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $query = LifeImpactHistory::query()
            ->where('user_id', (string) $user->id)
            ->with([
                'user:id,first_name,last_name,display_name,email,life_impacted_count',
                'triggeredByUser:id,first_name,last_name,display_name,email,life_impacted_count',
            ])
            ->orderByDesc('created_at');

        if (filled($request->query('activity_type'))) {
            $query->where('activity_type', (string) $request->query('activity_type'));
        }

        if (filled($request->query('date_from'))) {
            $query->whereDate('created_at', '>=', (string) $request->query('date_from'));
        }

        if (filled($request->query('date_to'))) {
            $query->whereDate('created_at', '<=', (string) $request->query('date_to'));
        }

        $matchingHistories = (clone $query)->get();
        $sourceBreakdown = $matchingHistories
            ->map(fn (LifeImpactHistory $history): string => $history->resolveImpactValueSource())
            ->countBy()
            ->all();
        $totalLifeImpacted = (int) $matchingHistories
            ->sum(fn (LifeImpactHistory $history): int => $history->resolveImpactValue());

        Log::info('life_impact.history.summary', [
            'user_id' => (string) $user->id,
            'matched_history_count' => $matchingHistories->count(),
            'resolved_total_life_impacted' => $totalLifeImpacted,
            'impact_value_source_breakdown' => $sourceBreakdown,
        ]);

        $histories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'total_life_impacted' => $totalLifeImpacted,
                'items' => LifeImpactHistoryResource::collection($histories->getCollection())->resolve(),
            ],
            'meta' => [
                'pagination' => [
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                    'per_page' => $histories->perPage(),
                    'total' => $histories->total(),
                ],
            ],
        ]);
    }
}
