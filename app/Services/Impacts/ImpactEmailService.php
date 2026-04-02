<?php

namespace App\Services\Impacts;

use App\Mail\ImpactApprovedMail;
use App\Mail\ImpactSubmittedMail;
use App\Models\Impact;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ImpactEmailService
{
    public function __construct(private readonly EmailLogService $emailLogService)
    {
    }

    public function sendSubmitted(Impact $impact): void
    {
        $this->safeSend($impact, new ImpactSubmittedMail($impact), 'submitted');
    }

    public function sendApproved(Impact $impact): void
    {
        if (! $impact->relationLoaded('user') || ! $impact->user) {
            $impact->load('user');
        }

        if (! $impact->user) {
            return;
        }

        $this->safeSend($impact, new ImpactApprovedMail($impact, $impact->user), 'approved');
    }

    private function safeSend(Impact $impact, Mailable $mailable, string $type): void
    {
        try {
            if (! $impact->relationLoaded('user') || ! $impact->user) {
                $impact->load('user');
            }

            $email = $impact->user?->email;

            if (! $email) {
                return;
            }

            Mail::to($email)->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => $impact->user?->id,
                'to_email' => $email,
                'to_name' => $impact->user?->display_name ?: trim(($impact->user?->first_name ?? '') . ' ' . ($impact->user?->last_name ?? '')),
                'template_key' => 'impact_' . $type,
                'source_module' => 'Impacts',
                'related_type' => Impact::class,
                'related_id' => (string) $impact->id,
                'payload' => [
                    'status' => $impact->status,
                    'action' => $impact->action,
                    'life_impacted' => (int) ($impact->life_impacted ?? 1),
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => $impact->user?->id,
                'to_email' => (string) ($impact->user?->email ?? ''),
                'to_name' => $impact->user?->display_name ?: trim(($impact->user?->first_name ?? '') . ' ' . ($impact->user?->last_name ?? '')),
                'template_key' => 'impact_' . $type,
                'source_module' => 'Impacts',
                'related_type' => Impact::class,
                'related_id' => (string) $impact->id,
                'payload' => [
                    'status' => $impact->status,
                    'action' => $impact->action,
                    'life_impacted' => (int) ($impact->life_impacted ?? 1),
                ],
            ], $exception);

            Log::warning('Impact email send failed', [
                'impact_id' => (string) $impact->id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
