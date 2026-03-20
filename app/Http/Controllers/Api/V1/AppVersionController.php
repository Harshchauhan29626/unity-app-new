<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AppVersionRequest;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Throwable;

class AppVersionController extends Controller
{
    public function show(AppVersionRequest $request): JsonResponse
    {
        try {
            $platform = $request->validatedPlatform();

            $version = AppVersion::query()
                ->where('platform', $platform)
                ->where('is_active', true)
                ->first();

            if (! $version) {
                return response()->json([
                    'status' => false,
                    'message' => 'No app version found for this platform.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'latest_version' => $version->latest_version,
                    'min_version' => $version->min_version,
                    'update_type' => $version->update_type,
                    'playstore_url' => $this->playStoreUrl(),
                    'appstore_url' => $this->appStoreUrl(),
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch app version at the moment.',
                'data' => null,
            ], 500);
        }
    }

    private function playStoreUrl(): string
    {
        return (string) config('app_links.android.store_url', '');
    }

    private function appStoreUrl(): string
    {
        return (string) config('app_links.ios.store_url', '');
    }
}
