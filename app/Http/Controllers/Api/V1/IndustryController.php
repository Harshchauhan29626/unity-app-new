<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IndustryTreeResource;
use App\Models\Industry;
use Illuminate\Http\JsonResponse;

class IndustryController extends Controller
{
    public function tree(): JsonResponse
    {
        $industries = Industry::query()
            ->active()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Industry tree fetched successfully.',
            'data' => IndustryTreeResource::collection($industries),
        ]);
    }
}
