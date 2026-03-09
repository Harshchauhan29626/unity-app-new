<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Requirement\RequirementTimelineResource;
use App\Models\Requirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimelineRequirementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Requirement::query()
            ->with('user')
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Open requirements fetched successfully.',
            'data' => RequirementTimelineResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
