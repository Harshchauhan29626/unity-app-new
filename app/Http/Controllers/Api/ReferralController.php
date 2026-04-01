<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Activity\StoreReferralRequest;
use App\Events\ActivityCreated;
use App\Models\Referral;
use App\Models\User;
use App\Services\Coins\CoinsService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\Request;
use Throwable;

class ReferralController extends BaseApiController
{
    public function index(Request $request)
    {
        $authUser = $request->user();
        $filter = $request->input('filter', 'given');
        $referralType = $request->input('referral_type');

        $query = Referral::query()
            ->where('is_deleted', false)
            ->whereNull('deleted_at');

        if ($filter === 'received') {
            $query->where('to_user_id', $authUser->id);
        } elseif ($filter === 'all') {
            $query->where(function ($q) use ($authUser) {
                $q->where('from_user_id', $authUser->id)
                    ->orWhere('to_user_id', $authUser->id);
            });
        } else {
            $query->where('from_user_id', $authUser->id);
        }

        if ($referralType) {
            $query->where('referral_type', $referralType);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query
            ->orderBy('referral_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->success([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreReferralRequest $request, NotifyUserService $notifyUserService)
    {
        $authUser = $request->user();

        try {
            $referral = Referral::create([
                'from_user_id' => $authUser->id,
                'to_user_id' => $request->input('to_user_id'),
                'referral_type' => $request->input('referral_type'),
                'referral_date' => $request->input('referral_date'),
                'referral_of' => $request->input('referral_of'),
                'phone' => $request->input('phone'),
                'email' => $request->input('email'),
                'address' => $request->input('address'),
                'hot_value' => $request->input('hot_value'),
                'remarks' => $request->input('remarks'),
                'is_deleted' => false,
            ]);

            $coinsLedger = app(CoinsService::class)->rewardForActivity(
                $authUser,
                'referral',
                null,
                'Activity: referral',
                $authUser->id
            );

            if ($coinsLedger) {
                $referral->setAttribute('coins', [
                    'earned' => $coinsLedger->amount,
                    'balance_after' => $coinsLedger->balance_after,
                ]);
            }

            event(new ActivityCreated(
                'Referral',
                $referral,
                (string) $authUser->id,
                $referral->to_user_id ? (string) $referral->to_user_id : null
            ));

            $targetUser = User::find($referral->to_user_id);

            if ($targetUser) {
                $notifyUserService->notifyUser(
                    $targetUser,
                    $authUser,
                    'activity_referral',
                    [
                        'activity_type' => 'referral',
                        'activity_id' => (string) $referral->id,
                        'title' => 'New Referral',
                        'body' => ($authUser->display_name ?? $authUser->name ?? 'A member') . ' sent you a referral',
                    ],
                    $referral
                );
            }

            // Postman example (referral create):
            // {
            //   "to_user_id": "<receiver-user-uuid>",
            //   "referral_type": "service",
            //   "referral_date": "2026-01-20",
            //   "referral_of": "Acme Corp",
            //   "phone": "+1234567890",
            //   "email": "lead@example.com",
            //   "address": "Downtown",
            //   "hot_value": "hot",
            //   "remarks": "High intent"
            // }
            // Verify SQL:
            // select * from notifications where user_id = '<receiver-user-uuid>' order by created_at desc limit 20;

            return $this->success($referral, 'Referral saved successfully', 201);
        } catch (Throwable $e) {
            return $this->error('Something went wrong', 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $authUser = $request->user();

        $referral = Referral::where('id', $id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($authUser) {
                $q->where('from_user_id', $authUser->id)
                    ->orWhere('to_user_id', $authUser->id);
            })
            ->first();

        if (! $referral) {
            return $this->error('Referral not found', 404);
        }

        return $this->success($referral);
    }
}
