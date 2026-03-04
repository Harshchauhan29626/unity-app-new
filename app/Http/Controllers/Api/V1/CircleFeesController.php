<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Circle;

class CircleFeesController extends Controller
{
    public function index(Circle $circle)
    {
        $fees = $circle->fees()
            ->where('is_active', true)
            ->orderBy('amount')
            ->get()
            ->map(fn ($fee) => [
                'id' => $fee->id,
                'interval_key' => $fee->interval_key,
                'label' => str_replace('_', ' ', ucfirst($fee->interval_key)),
                'amount' => (float) $fee->amount,
                'currency' => $fee->currency,
                'is_active' => (bool) $fee->is_active,
            ])
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Circle fees fetched successfully.',
            'data' => $fees,
        ]);
    }
}
