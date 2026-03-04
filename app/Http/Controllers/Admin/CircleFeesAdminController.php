<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCircleFeeRequest;
use App\Http\Requests\Admin\UpdateCircleFeeRequest;
use App\Jobs\SyncCircleFeesToZohoJob;
use App\Models\Circle;
use App\Models\CircleFee;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CircleFeesAdminController extends Controller
{
    public function index(Circle $circle)
    {
        return response()->json([
            'status' => true,
            'message' => 'Circle fees fetched successfully.',
            'data' => $circle->fees()->orderBy('interval_key')->get(),
        ]);
    }

    public function store(StoreCircleFeeRequest $request, Circle $circle)
    {
        $fee = $circle->fees()->create([
            'id' => (string) Str::uuid(),
            'interval_key' => $request->string('interval_key'),
            'amount' => $request->input('amount'),
            'currency' => strtoupper((string) $request->input('currency', 'INR')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        SyncCircleFeesToZohoJob::dispatch($circle->id);

        return response()->json([
            'status' => true,
            'message' => 'Circle fee created successfully.',
            'data' => $fee,
        ], 201);
    }

    public function update(UpdateCircleFeeRequest $request, CircleFee $fee)
    {
        $fee->fill($request->validated());

        if ($request->has('currency')) {
            $fee->currency = strtoupper((string) $request->input('currency'));
        }

        $fee->save();

        SyncCircleFeesToZohoJob::dispatch($fee->circle_id);

        return response()->json([
            'status' => true,
            'message' => 'Circle fee updated successfully.',
            'data' => $fee,
        ]);
    }

    public function destroy(CircleFee $fee)
    {
        $circleId = $fee->circle_id;
        $fee->delete();

        SyncCircleFeesToZohoJob::dispatch($circleId);

        return response()->json([
            'status' => true,
            'message' => 'Circle fee deleted successfully.',
            'data' => null,
        ]);
    }

    public function syncZoho(Circle $circle, Request $request)
    {
        SyncCircleFeesToZohoJob::dispatch($circle->id);

        return response()->json([
            'status' => true,
            'message' => 'Circle fee sync dispatched.',
            'data' => ['circle_id' => $circle->id],
        ]);
    }
}
