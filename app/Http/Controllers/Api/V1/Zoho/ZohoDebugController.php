<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Support\Zoho\ZohoBillingService;
use App\Support\Zoho\ZohoBillingTokenService;
use Throwable;

class ZohoDebugController extends Controller
{
    public function __construct(
        private readonly ZohoBillingTokenService $tokenService,
        private readonly ZohoBillingService $zohoBillingService,
    ) {
    }

    public function testToken()
    {
        try {
            $this->tokenService->getAccessToken();

            return response()->json([
                'success' => true,
                'message' => 'Zoho access token fetched successfully.',
                'data' => $this->tokenService->tokenMeta(),
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'success' => false,
                'message' => $throwable->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function org()
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Zoho organization fetched successfully.',
                'data' => $this->zohoBillingService->getOrganization(),
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'success' => false,
                'message' => $throwable->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
