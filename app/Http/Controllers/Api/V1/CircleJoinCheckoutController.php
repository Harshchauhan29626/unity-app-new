<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateCircleJoinCheckoutRequest;
use App\Models\Circle;
use App\Models\CircleFee;
use App\Models\CircleJoinPayment;
use App\Models\User;
use App\Services\Zoho\ZohoCircleAddonService;
use Illuminate\Support\Str;
use RuntimeException;

class CircleJoinCheckoutController extends Controller
{
    public function __construct(private readonly ZohoCircleAddonService $zohoCircleAddonService)
    {
    }

    public function store(CreateCircleJoinCheckoutRequest $request, Circle $circle)
    {
        /** @var User $user */
        $user = $request->user();

        if ((string) $user->membership_status !== 'only_unity_peer' || $user->isFreeMember()) {
            return response()->json([
                'status' => false,
                'message' => 'Only users with only_unity_peer membership can join paid circles.',
                'data' => null,
            ], 422);
        }

        $fee = CircleFee::query()
            ->where('circle_id', $circle->id)
            ->where('is_active', true)
            ->when($request->filled('circle_fee_id'), fn ($q) => $q->where('id', (string) $request->input('circle_fee_id')))
            ->when($request->filled('interval_key'), fn ($q) => $q->where('interval_key', (string) $request->input('interval_key')))
            ->first();

        if (! $fee) {
            return response()->json([
                'status' => false,
                'message' => 'Active circle fee not found for selected interval.',
                'data' => null,
            ], 404);
        }

        $payment = CircleJoinPayment::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'circle_id' => $circle->id,
            'circle_fee_id' => $fee->id,
            'provider' => 'zoho',
            'status' => 'pending',
            'amount' => $fee->amount,
            'currency' => $fee->currency,
            'raw_payload' => [
                'request_id' => (string) Str::uuid(),
                'source' => 'circle_join_checkout',
            ],
        ]);

        try {
            $checkout = $this->zohoCircleAddonService->createHostedPageForCircleJoin($user, $payment, $fee);
        } catch (RuntimeException $exception) {
            $payment->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'raw_payload' => array_merge($payment->raw_payload ?? [], ['error' => $exception->getMessage()]),
            ])->save();

            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Checkout url created successfully.',
            'data' => [
                'payment_id' => $checkout['payment_id'],
                'checkout_url' => $checkout['checkout_url'],
            ],
        ]);
    }
}
