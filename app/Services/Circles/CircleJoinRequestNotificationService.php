<?php

namespace App\Services\Circles;

use App\Jobs\SendPushNotificationJob;
use App\Mail\CircleJoinRequestStatusMail;
use App\Models\CircleJoinRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CircleJoinRequestNotificationService
{
    public function sendCdApprovedToUser(CircleJoinRequest $request): void
    {
        $circleName = $request->circle?->name ?? 'this circle';
        $title = 'Circle Join Request Updated';
        $body = "Your request to join {$circleName} has been approved by Circle Director and is now pending Industry Director approval.";

        $this->sendUserUpdate($request, $title, $body, 'circle_join_request_cd_approved', 'pending_id_approval', null, 'Your Circle Join Request is Pending Industry Director Approval');
    }

    public function sendCdRejectedToUser(CircleJoinRequest $request): void
    {
        $circleName = $request->circle?->name ?? 'this circle';
        $reason = trim((string) $request->cd_rejection_reason);
        $title = 'Circle Join Request Rejected';
        $body = "Your request to join {$circleName} was rejected by Circle Director." . ($reason !== '' ? " Reason: {$reason}" : '');

        $this->sendUserUpdate($request, $title, $body, 'circle_join_request_cd_rejected', 'rejected_by_cd', $reason !== '' ? $reason : null, 'Your Circle Join Request Was Rejected by Circle Director');
    }

    public function sendIdApprovedToUser(CircleJoinRequest $request): void
    {
        $circleName = $request->circle?->name ?? 'this circle';
        $title = 'Circle Join Request Approved';
        $body = "Your request to join {$circleName} has been approved and is now pending circle fee payment.";

        $this->sendUserUpdate($request, $title, $body, 'circle_join_request_id_approved', 'pending_circle_fee', null, 'Your Circle Join Request is Pending Circle Fee Payment');
    }

    public function sendIdRejectedToUser(CircleJoinRequest $request): void
    {
        $circleName = $request->circle?->name ?? 'this circle';
        $reason = trim((string) $request->id_rejection_reason);
        $title = 'Circle Join Request Rejected';
        $body = "Your request to join {$circleName} was rejected by Industry Director." . ($reason !== '' ? " Reason: {$reason}" : '');

        $this->sendUserUpdate($request, $title, $body, 'circle_join_request_id_rejected', 'rejected_by_id', $reason !== '' ? $reason : null, 'Your Circle Join Request Was Rejected by Industry Director');
    }

    public function sendCircleMemberConfirmedToUser(CircleJoinRequest $request): void
    {
        $circleName = $request->circle?->name ?? 'this circle';
        $title = 'Circle Membership Confirmed';
        $body = "Your payment is successful and you are now a Circle Member of {$circleName}.";

        $this->sendUserUpdate($request, $title, $body, 'circle_join_request_member_confirmed', 'circle_member', null, 'Your Circle Membership is Confirmed');
    }

    private function sendUserUpdate(
        CircleJoinRequest $request,
        string $title,
        string $body,
        string $eventType,
        string $status,
        ?string $rejectionReason,
        string $emailSubject,
    ): void {
        $request->loadMissing(['user', 'circle', 'cdApprovedBy', 'cdRejectedBy', 'idApprovedBy', 'idRejectedBy']);

        $user = $request->user;
        if (! $user) {
            return;
        }

        $actor = $this->resolveActor($request, $status) ?? $user;

        $payloadData = [
            'type' => 'circle_join_request',
            'circle_join_request_id' => (string) $request->id,
            'circle_id' => (string) $request->circle_id,
            'status' => $status,
            'action_by' => $this->resolveActionBy($request, $status),
            'title' => $title,
            'body' => $body,
        ];

        if ($rejectionReason) {
            $payloadData['rejection_reason'] = $rejectionReason;
        }

        try {
            Notification::query()->create([
                'user_id' => $user->id,
                'type' => 'activity_update',
                'payload' => [
                    'notification_type' => $eventType,
                    'title' => $title,
                    'body' => $body,
                    'from_user_id' => (string) $actor->id,
                    'to_user_id' => (string) $user->id,
                    'data' => $payloadData,
                    'notifiable_type' => CircleJoinRequest::class,
                    'notifiable_id' => (string) $request->id,
                ],
                'is_read' => false,
                'created_at' => now(),
                'read_at' => null,
            ]);

            SendPushNotificationJob::dispatch($user, $title, $body, [
                'type' => 'circle_join_request',
                'circle_join_request_id' => (string) $request->id,
                'circle_id' => (string) $request->circle_id,
                'status' => $status,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Circle join request in-app notification failed', [
                'circle_join_request_id' => (string) $request->id,
                'user_id' => (string) $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            if (! empty($user->email)) {
                Mail::to($user->email)->send(new CircleJoinRequestStatusMail(
                    $request,
                    $emailSubject,
                    $title,
                    $body,
                    $this->statusLabel($status),
                    $rejectionReason,
                ));
            }
        } catch (\Throwable $exception) {
            Log::warning('Circle join request email send failed', [
                'circle_join_request_id' => (string) $request->id,
                'user_id' => (string) $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveActor(CircleJoinRequest $request, string $status): ?User
    {
        return match ($status) {
            'pending_id_approval' => $request->cdApprovedBy,
            'rejected_by_cd' => $request->cdRejectedBy,
            'pending_circle_fee' => $request->idApprovedBy,
            'rejected_by_id' => $request->idRejectedBy,
            default => null,
        };
    }

    private function resolveActionBy(CircleJoinRequest $request, string $status): ?array
    {
        $actor = $this->resolveActor($request, $status);

        if (! $actor) {
            return null;
        }

        $role = match ($status) {
            'pending_id_approval', 'rejected_by_cd' => 'circle_director',
            'pending_circle_fee', 'rejected_by_id' => 'industry_director',
            default => 'system',
        };

        return [
            'id' => (string) $actor->id,
            'name' => (string) ($actor->display_name ?: trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? ''))),
            'role' => $role,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending_id_approval' => 'Pending for Industry Director Approval',
            'rejected_by_cd' => 'Rejected by Circle Director',
            'pending_circle_fee' => 'Pending for Circle Fee',
            'rejected_by_id' => 'Rejected by Industry Director',
            'circle_member' => 'Circle Member',
            default => $status,
        };
    }
}
