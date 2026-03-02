<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class BillingCheckoutController extends Controller
{
    public function __construct(private readonly ZohoBillingService $billingService)
    {
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'max:100'],
        ]);

        $user = Auth::user();

        try {
            $hostedPage = $this->billingService->createHostedPageForSubscription($user, $validated['plan_code']);

            return response()->json([
                'success' => true,
                'message' => 'Checkout URL generated successfully.',
                'data' => [
                    'hostedpage_id' => $hostedPage['hostedpage_id'],
                    'checkout_url' => $hostedPage['checkout_url'],
                    'expires_at' => $hostedPage['expires_at'],
                    'zoho_customer_id' => $hostedPage['zoho_customer_id'],
                    'note' => 'Open checkout_url in WebView',
                ],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => Arr::first(Arr::flatten($exception->errors())) ?? 'Validation failed.',
                'data' => ['errors' => $exception->errors()],
            ], 422);
        } catch (Throwable $throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate checkout URL.',
                'data' => ['error' => $throwable->getMessage()],
            ], 500);
        }
    }

    public function status(string $hostedpageId)
    {
        $user = Auth::user();

        try {
            $hostedPage = $this->billingService->getHostedPage($hostedpageId);
            $updated = $this->billingService->syncUserMembershipFromHostedPage($user, $hostedPage);

            return response()->json([
                'success' => true,
                'message' => 'Hosted page status fetched successfully.',
                'data' => [
                    'hosted_page' => $hostedPage,
                    'membership_updated' => $updated,
                ],
            ]);
        } catch (Throwable $throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch checkout status.',
                'data' => ['error' => $throwable->getMessage()],
            ], 500);
        }
    }
}

/*
| Test steps
| 1) Call POST /api/v1/billing/checkout with plan_code=01.
| 2) Open checkout_url: mobile must not be requested.
| 3) Complete payment and confirm redirect to Razorpay works.
*/
