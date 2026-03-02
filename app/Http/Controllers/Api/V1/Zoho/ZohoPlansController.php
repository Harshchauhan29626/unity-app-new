<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Support\Zoho\ZohoBillingService;
use Throwable;

class ZohoPlansController extends Controller
{
    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function index()
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Active plans fetched successfully.',
                'data' => [
                    'plans' => $this->zohoBillingService->listActivePlans(),
                ],
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
