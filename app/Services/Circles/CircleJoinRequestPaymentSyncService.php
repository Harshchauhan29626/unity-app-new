<?php

namespace App\Services\Circles;

use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\CircleMemberCategorySelection;
use App\Models\JoinedCircleCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CircleJoinRequestPaymentSyncService
{
    public function __construct(private readonly CircleJoinRequestNotificationService $notificationService)
    {
    }

    public function markRequestPaidFromUserCircle(User $user): void
    {
        $freshUser = User::query()->find($user->id);
        if (! $freshUser) {
            Log::warning('circle join request sync skipped - user not found during refresh', ['user_id' => $user->id]);
            return;
        }

        $activeCircleId = (string) ($freshUser->active_circle_id ?? '');
        if ($activeCircleId === '') {
            Log::info('circle join request payment sync skipped - empty active_circle_id', ['user_id' => $freshUser->id]);
            return;
        }

        $this->markRequestPaid($freshUser, $activeCircleId);
    }

    public function markRequestPaid(User $user, string $circleId, $paidAt = null): void
    {
        if (trim($circleId) === '') {
            return;
        }

        $paidAtTimestamp = $paidAt ?: now();

        $joinRequest = DB::transaction(function () use ($user, $circleId, $paidAtTimestamp) {
            $joinRequest = CircleJoinRequest::query()
                ->where('user_id', $user->id)
                ->where('circle_id', $circleId)
                ->where('status', CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE)
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if (! $joinRequest) {
                $joinRequest = CircleJoinRequest::query()
                    ->where('user_id', $user->id)
                    ->where('circle_id', $circleId)
                    ->whereIn('status', [CircleJoinRequest::STATUS_PAID, CircleJoinRequest::STATUS_CIRCLE_MEMBER])
                    ->latest('created_at')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $joinRequest) {
                return null;
            }

            $oldStatus = (string) $joinRequest->status;

            if ($oldStatus === CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE) {
                $updates = [
                    'status' => CircleJoinRequest::STATUS_PAID,
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('circle_join_requests', 'fee_paid_at')) {
                    $updates['fee_paid_at'] = $joinRequest->fee_paid_at ?: $paidAtTimestamp;
                }
                if (Schema::hasColumn('circle_join_requests', 'fee_marked_at')) {
                    $updates['fee_marked_at'] = $joinRequest->fee_marked_at ?: $paidAtTimestamp;
                }

                $joinRequest->forceFill($updates)->save();

                Log::info('circle join request synced to paid', [
                    'request_id' => $joinRequest->id,
                    'user_id' => $user->id,
                    'circle_id' => $circleId,
                    'old_status' => $oldStatus,
                    'new_status' => (string) $joinRequest->status,
                ]);
            }

            $member = CircleMember::withTrashed()
                ->where('user_id', $user->id)
                ->where('circle_id', $circleId)
                ->first();

            $memberPayload = [
                'status' => (string) config('circle.member_joined_status', 'approved'),
                'role' => $member?->role ?: 'member',
                'left_at' => null,
            ];

            if (Schema::hasColumn('circle_members', 'joined_at')) {
                $memberPayload['joined_at'] = $member?->joined_at ?: $paidAtTimestamp;
            }

            if ($member) {
                if ($member->trashed()) {
                    $member->restore();
                }
                $member->forceFill($memberPayload)->save();
            } else {
                $member = CircleMember::query()->create(array_merge($memberPayload, [
                    'user_id' => $user->id,
                    'circle_id' => $circleId,
                ]));
            }

            $selection = $this->resolveSelectionFromRequest($joinRequest);
            $this->syncMemberCategorySelection($member, $joinRequest, $selection);

            return $joinRequest->fresh(['user', 'circle']);
        });

        if (! $joinRequest) {
            Log::info('circle join request sync skipped - no matching pending/finalized request found', [
                'user_id' => $user->id,
                'circle_id' => $circleId,
            ]);

            return;
        }

        $this->updateUserCircleMembershipTier($user->fresh() ?? $user);

        try {
            $this->notificationService->sendCircleMemberConfirmedToUser($joinRequest);
        } catch (Throwable $exception) {
            Log::warning('Circle join request paid notification failed after payment sync', [
                'circle_join_request_id' => $joinRequest->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveSelectionFromRequest(CircleJoinRequest $request): array
    {
        $notes = is_array($request->notes) ? $request->notes : [];
        $notesSelection = is_array($notes['category_selection'] ?? null) ? $notes['category_selection'] : [];

        $resolve = static function (string $key) use ($request, $notesSelection): ?int {
            $value = $request->getAttribute($key);
            if ($value !== null) {
                return (int) $value;
            }

            if (array_key_exists($key, $notesSelection) && $notesSelection[$key] !== null) {
                return (int) $notesSelection[$key];
            }

            return null;
        };

        return [
            'level1_category_id' => $resolve('level1_category_id'),
            'level2_category_id' => $resolve('level2_category_id'),
            'level3_category_id' => $resolve('level3_category_id'),
            'level4_category_id' => $resolve('level4_category_id'),
        ];
    }

    private function syncMemberCategorySelection(CircleMember $member, CircleJoinRequest $request, array $selection): void
    {
        if (Schema::hasTable('circle_member_category_selections')) {
            CircleMemberCategorySelection::query()->updateOrCreate(
                [
                    'circle_member_id' => $member->id,
                ],
                [
                    'user_id' => $request->user_id,
                    'circle_id' => $request->circle_id,
                    'level1_category_id' => $selection['level1_category_id'],
                    'level2_category_id' => $selection['level2_category_id'],
                    'level3_category_id' => $selection['level3_category_id'],
                    'level4_category_id' => $selection['level4_category_id'],
                ]
            );
        }

        if (Schema::hasTable('joined_circle_categories')) {
            JoinedCircleCategory::query()->updateOrCreate(
                [
                    'circle_member_id' => $member->id,
                ],
                [
                    'user_id' => $request->user_id,
                    'circle_id' => $request->circle_id,
                    'level1_category_id' => $selection['level1_category_id'],
                    'level2_category_id' => $selection['level2_category_id'],
                    'level3_category_id' => $selection['level3_category_id'],
                    'level4_category_id' => $selection['level4_category_id'],
                ]
            );
        }
    }

    public function updateUserCircleMembershipTier(User $user): void
    {
        try {
            $currentStatus = (string) ($user->membership_status ?? '');

            // Keep Unity/other non-circle statuses untouched.
            $allowedToOverride = [
                '',
                User::STATUS_FREE,
                User::STATUS_FREE_TRIAL,
                'Circle Peer',
                'Multi Circle Peer',
            ];

            if (! in_array($currentStatus, $allowedToOverride, true)) {
                return;
            }

            $joinedStatus = (string) config('circle.member_joined_status', 'approved');
            $activeCircleCount = CircleMember::query()
                ->where('user_id', $user->id)
                ->where('status', $joinedStatus)
                ->whereNull('deleted_at')
                ->whereNull('left_at')
                ->where(function ($query): void {
                    $query->whereNull('paid_ends_at')->orWhere('paid_ends_at', '>=', now());
                })
                ->count();

            if ($activeCircleCount <= 0) {
                return;
            }

            $nextStatus = $activeCircleCount > 1 ? 'Multi Circle Peer' : 'Circle Peer';
            if ($currentStatus !== $nextStatus) {
                $user->forceFill(['membership_status' => $nextStatus])->save();
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to sync user circle membership tier', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
