<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Requests\Api\V1\VerifyPaymentRequest;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Razorpay\Api\Utility;

class PaymentController extends Controller
{
    public function __construct(private readonly MembershipService $membershipService)
    {
    }

    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $planId = $request->validated('membership_plan_id');

        $plan = MembershipPlan::query()
            ->where('id', $planId)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'Membership plan not found.'], 404);
        }

        if ($plan->is_free) {
            return response()->json(['message' => 'Free plans do not require payment.'], 422);
        }

        $amounts = $this->membershipService->calculateAmounts($plan);
        $paymentId = (string) Str::uuid();

        try {
            $api = new Api(config('razorpay.key_id'), config('razorpay.key_secret'));
            $order = $api->order->create([
                'amount' => (int) round($amounts['total_amount'] * 100),
                'currency' => config('razorpay.currency', 'INR'),
                'receipt' => $paymentId,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Razorpay order creation failed', [
                'user_id' => $user?->id,
                'plan_id' => $planId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to create payment order.'], 500);
        }

        Payment::query()->create([
            'id' => $paymentId,
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'base_amount' => $amounts['base_amount'],
            'gst_percent' => $amounts['gst_percent'],
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
            'razorpay_order_id' => $order['id'],
            'status' => Payment::STATUS_CREATED,
        ]);

        return response()->json([
            'order_id' => $order['id'],
            'amount' => (int) $order['amount'],
            'currency' => $order['currency'],
            'key_id' => config('razorpay.key_id'),
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (float) $plan->price,
                'gst_percent' => (float) $plan->gst_percent,
                'gst_amount' => $amounts['gst_amount'],
                'total_amount' => $amounts['total_amount'],
                'duration_days' => (int) $plan->duration_days,
                'duration_months' => $plan->duration_months ? (int) $plan->duration_months : null,
                'is_free' => (bool) $plan->is_free,
            ],
        ]);
    }

    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validated();

        $payment = Payment::query()
            ->where('razorpay_order_id', $payload['razorpay_order_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment order not found.'], 404);
        }

        try {
            Utility::verifyPaymentSignature([
                'razorpay_order_id' => $payload['razorpay_order_id'],
                'razorpay_payment_id' => $payload['razorpay_payment_id'],
                'razorpay_signature' => $payload['razorpay_signature'],
            ]);
        } catch (SignatureVerificationError $exception) {
            Log::warning('Razorpay signature verification failed', [
                'user_id' => $user?->id,
                'order_id' => $payload['razorpay_order_id'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid payment signature.'], 422);
        }

        $updatedUser = DB::transaction(function () use ($payment, $payload, $user) {
            $lockedPayment = Payment::query()->where('id', $payment->id)->lockForUpdate()->first();
            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return $user->fresh();
            }

            $lockedPayment->update([
                'razorpay_payment_id' => $payload['razorpay_payment_id'],
                'razorpay_signature' => $payload['razorpay_signature'],
                'status' => Payment::STATUS_SUCCESS,
                'paid_at' => now(),
            ]);

            $plan = MembershipPlan::query()->where('id', $lockedPayment->membership_plan_id)->first();
            if (! $plan) {
                Log::error('Membership plan missing for payment', [
                    'payment_id' => $lockedPayment->id,
                ]);

                return $user->fresh();
            }

            return $this->membershipService->activateMembership($user, $plan, $lockedPayment);
        });

        return response()->json([
            'membership_status' => $updatedUser->membership_status,
            'membership_expiry' => $updatedUser->membership_ends_at,
        ]);
    }
}
