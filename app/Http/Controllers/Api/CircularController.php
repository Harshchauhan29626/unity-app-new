<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Circular;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CircularController extends Controller
{
    public function index(Request $request)
    {
        $query = Circular::query()
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->where('publish_date', '<=', DB::raw("NOW() + interval '5 minutes'"))
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>', DB::raw('NOW()'));
            });

        $items = $query
            ->orderByDesc('is_pinned')
            ->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 3
                    WHEN 'important' THEN 2
                    ELSE 1
                END DESC
            ")
            ->orderByDesc('publish_date')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Circulars fetched successfully.',
            'data' => [
                'items' => $items->items(),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function show(string $id)
    {
        $circular = Circular::query()
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Circular detail fetched successfully.',
            'data' => $circular,
        ]);
    }
}
