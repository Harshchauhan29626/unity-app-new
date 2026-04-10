<?php

namespace App\Services\Circles;

use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CircleJoinRequestService
{
    public function __construct(
        private readonly NotifyUserService $notifyUserService,
        private readonly CircleJoinRequestNotificationService $circleJoinRequestNotificationService,
    ) {
    }

    public function submitRequest(User $user, Circle $circle, ?string $reason, array $categoryIds = []): CircleJoinRequest
    {
        return DB::transaction(function () use ($user, $circle, $reason, $categoryIds) {
            $alreadyMember = CircleMember::query()
                ->where('circle_id', $circle->id)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->where('status', 'approved')
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'circle_id' => ['You are already a member of this circle.'],
                ]);
            }

            $duplicateRequest = CircleJoinRequest::query()
                ->where('user_id', $user->id)
                ->where('circle_id', $circle->id)
                ->whereIn('status', CircleJoinRequest::ACTIVE_STATUSES)
                ->exists();

            if ($duplicateRequest) {
                throw ValidationException::withMessages([
                    'circle_id' => ['You already have an active join request for this circle.'],
                ]);
            }

            $payload = [
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'reason_for_joining' => $reason,
                'status' => CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                'requested_at' => now(),
            ];

            $selection = [
                'level1_category_id' => isset($categoryIds['level1_category_id']) ? (int) $categoryIds['level1_category_id'] : null,
                'level2_category_id' => isset($categoryIds['level2_category_id']) ? (int) $categoryIds['level2_category_id'] : null,
                'level3_category_id' => isset($categoryIds['level3_category_id']) ? (int) $categoryIds['level3_category_id'] : null,
                'level4_category_id' => isset($categoryIds['level4_category_id']) ? (int) $categoryIds['level4_category_id'] : null,
            ];

            foreach ($selection as $key => $value) {
                if ($value !== null && $value <= 0) {
                    $selection[$key] = null;
                }
            }

            $hasCategoryColumns = Schema::hasColumns('circle_join_requests', [
                'level1_category_id',
                'level2_category_id',
                'level3_category_id',
                'level4_category_id',
            ]);

            if ($hasCategoryColumns) {
                $payload = array_merge($payload, $selection);
            } else {
                $payload['notes'] = array_filter([
                    'category_selection' => array_filter($selection, fn ($value) => $value !== null),
                ]);
            }

            $request = CircleJoinRequest::query()->create($payload);

            $this->notifyStakeholders($request, $user);

            return $request;
        });
    }

    public function approveByCd(CircleJoinRequest $request, User $admin): CircleJoinRequest
    {
        return DB::transaction(function () use ($request, $admin) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
                'cd_approved_by' => $admin->id,
                'cd_approved_at' => now(),
                'cd_rejected_by' => null,
                'cd_rejected_at' => null,
                'cd_rejection_reason' => null,
            ])->save();

            $updated = $locked->fresh(['user', 'circle']);

            $this->safeSendTransitionNotifications(
                $updated,
                fn () => $this->circleJoinRequestNotificationService->sendCdApprovedToUser($updated)
            );

            return $updated;
        });
    }

    public function rejectByCd(CircleJoinRequest $request, User $admin, string $reason): CircleJoinRequest
    {
        return DB::transaction(function () use ($request, $admin, $reason) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_REJECTED_BY_CD,
                'cd_rejected_by' => $admin->id,
                'cd_rejected_at' => now(),
                'cd_rejection_reason' => $reason,
            ])->save();

            $updated = $locked->fresh(['user', 'circle']);

            $this->safeSendTransitionNotifications(
                $updated,
                fn () => $this->circleJoinRequestNotificationService->sendCdRejectedToUser($updated)
            );

            return $updated;
        });
    }

    public function approveById(CircleJoinRequest $request, User $admin): CircleJoinRequest
    {
        return DB::transaction(function () use ($request, $admin) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_ID_APPROVAL);

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
                'id_approved_by' => $admin->id,
                'id_approved_at' => now(),
                'id_rejected_by' => null,
                'id_rejected_at' => null,
                'id_rejection_reason' => null,
                'fee_marked_at' => now(),
            ])->save();

            $updated = $locked->fresh(['user', 'circle']);

            $this->safeSendTransitionNotifications(
                $updated,
                fn () => $this->circleJoinRequestNotificationService->sendIdApprovedToUser($updated)
            );

            return $updated;
        });
    }

    public function rejectById(CircleJoinRequest $request, User $admin, string $reason): CircleJoinRequest
    {
        return DB::transaction(function () use ($request, $admin, $reason) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_ID_APPROVAL);

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_REJECTED_BY_ID,
                'id_rejected_by' => $admin->id,
                'id_rejected_at' => now(),
                'id_rejection_reason' => $reason,
            ])->save();

            $updated = $locked->fresh(['user', 'circle']);

            $this->safeSendTransitionNotifications(
                $updated,
                fn () => $this->circleJoinRequestNotificationService->sendIdRejectedToUser($updated)
            );

            return $updated;
        });
    }

    public function cancelByUser(CircleJoinRequest $request, User $user): CircleJoinRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $locked = $this->lockOrFail($request->id);

            if ((string) $locked->user_id !== (string) $user->id) {
                throw ValidationException::withMessages([
                    'id' => ['You can only cancel your own request.'],
                ]);
            }

            if (! in_array($locked->status, [
                CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
            ], true)) {
                throw ValidationException::withMessages([
                    'id' => ['This request can no longer be cancelled.'],
                ]);
            }

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_CANCELLED,
            ])->save();

            return $locked->fresh(['user', 'circle']);
        });
    }

    public function markPaidAndConvertToMember(CircleJoinRequest $request, array $context = []): CircleJoinRequest
    {
        return DB::transaction(function () use ($request, $context) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE);

            $startedAt = $context['started_at'] ?? now();

            $member = CircleMember::withTrashed()
                ->where('circle_id', $locked->circle_id)
                ->where('user_id', $locked->user_id)
                ->first();

            if ($member) {
                if ($member->trashed()) {
                    $member->restore();
                }

                $member->forceFill([
                    'status' => 'approved',
                    'role' => $member->role ?: 'member',
                    'joined_at' => $member->joined_at ?: $startedAt,
                    'left_at' => null,
                ])->save();
            } else {
                CircleMember::query()->create([
                    'circle_id' => $locked->circle_id,
                    'user_id' => $locked->user_id,
                    'status' => 'approved',
                    'role' => 'member',
                    'joined_at' => $startedAt,
                ]);
            }

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_CIRCLE_MEMBER,
                'fee_marked_at' => $locked->fee_marked_at ?: now(),
                'fee_paid_at' => now(),
                'notes' => array_merge((array) $locked->notes, $context),
            ])->save();

            $updated = $locked->fresh(['user', 'circle']);

            $this->safeSendTransitionNotifications(
                $updated,
                fn () => $this->circleJoinRequestNotificationService->sendCircleMemberConfirmedToUser($updated)
            );

            return $updated;
        });
    }

    private function lockOrFail(string $id): CircleJoinRequest
    {
        return CircleJoinRequest::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureStatus(CircleJoinRequest $request, string $expected): void
    {
        if ($request->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Invalid status transition from {$request->status}."],
            ]);
        }
    }

    private function safeSendTransitionNotifications(CircleJoinRequest $request, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Log::warning('Circle join request transition notification failed', [
                'circle_join_request_id' => (string) $request->id,
                'status' => (string) $request->status,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyStakeholders(CircleJoinRequest $request, User $actor): void
    {
        $circle = $request->circle()->first();

        if (! $circle) {
            return;
        }

        $targets = User::query()
            ->whereIn('id', array_filter([
                $circle->director_user_id,
                $circle->industry_director_user_id,
                $circle->ded_user_id,
            ]))
            ->get();

        $globalAdminRoleId = Role::query()
            ->where('key', 'global_admin')
            ->value('id');

        if ($globalAdminRoleId) {
            $globalAdmins = User::query()
                ->whereHas('roles', fn ($q) => $q->where('roles.id', $globalAdminRoleId))
                ->get();

            $targets = $targets->merge($globalAdmins);
        }

        $targets->unique('id')->each(function (User $recipient) use ($actor, $request): void {
            $this->notifyUser(
                $recipient,
                $actor,
                'circle_join_request_submitted',
                'A new circle join request was submitted.',
                ['circle_join_request_id' => $request->id]
            );
        });
    }

    private function notifyUser(User $to, User $from, string $type, string $body, array $data = []): void
    {
        $this->notifyUserService->notifyUser(
            $to,
            $from,
            $type,
            array_merge($data, [
                'title' => 'Circle Joining Request',
                'body' => $body,
            ])
        );
    }
}