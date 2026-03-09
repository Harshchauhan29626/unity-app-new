<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollaborationTypeResource;
use App\Models\CollaborationType;
use Illuminate\Http\JsonResponse;

class CollaborationTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = CollaborationType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Collaboration types fetched successfully.',
            'data' => CollaborationTypeResource::collection($types),
        ]);
    }
}
