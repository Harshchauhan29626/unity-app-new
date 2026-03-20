<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpsertAppVersionRequest;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Throwable;

class AppVersionController extends Controller
{
    public function upsert(UpsertAppVersionRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();

            $appVersion = AppVersion::updateOrCreate(
                ['platform' => $payload['platform']],
                [
                    'latest_version' => $payload['latest_version'],
                    'min_version' => $payload['min_version'],
                    'update_type' => $payload['update_type'],
                    'is_active' => true,
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'App version updated successfully',
                'data' => [
                    'latest_version' => $appVersion->latest_version,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update app version at the moment',
                'data' => null,
            ], 500);
        }
    }
}
