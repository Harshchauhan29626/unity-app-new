<?php

namespace App\Console\Commands;

use App\Mail\FreeTrialExpiredMail;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExpireTrialUsers extends Command
{
    protected $signature = 'users:expire-trial';

    protected $description = 'Expire free trial users and downgrade them to free peer.';

    public function handle(): int
    {
        $expiredUsers = User::query()
            ->where('membership_status', User::STATUS_FREE_TRIAL)
            ->whereNotNull('membership_ends_at')
            ->where('membership_ends_at', '<=', now())
            ->get();

        $updated = 0;
        $emailsSent = 0;

        foreach ($expiredUsers as $user) {
            $user->forceFill([
                'membership_status' => User::STATUS_FREE,
            ])->save();

            $updated++;

            if (EmailLog::query()
                ->where('user_id', (string) $user->id)
                ->where('template_key', 'free_trial_expired')
                ->exists()) {
                continue;
            }

            $mailable = new FreeTrialExpiredMail($user);

            try {
                Mail::to($user->email)->send($mailable);

                app(EmailLogService::class)->logMailableSent($mailable, [
                    'user_id' => (string) $user->id,
                    'to_email' => (string) $user->email,
                    'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                    'template_key' => 'free_trial_expired',
                    'source_module' => 'membership',
                    'related_type' => 'user',
                    'related_id' => (string) $user->id,
                    'payload' => [
                        'flow' => 'trial_expiry_command',
                        'expired_at' => optional($user->membership_ends_at)->toDateTimeString(),
                    ],
                ]);

                $emailsSent++;
            } catch (\Throwable $exception) {
                app(EmailLogService::class)->logMailableFailed($mailable, [
                    'user_id' => (string) $user->id,
                    'to_email' => (string) $user->email,
                    'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                    'template_key' => 'free_trial_expired',
                    'source_module' => 'membership',
                    'related_type' => 'user',
                    'related_id' => (string) $user->id,
                    'payload' => [
                        'flow' => 'trial_expiry_command',
                        'expired_at' => optional($user->membership_ends_at)->toDateTimeString(),
                    ],
                ], $exception);

                Log::warning('membership.free_trial_expired_email_failed', [
                    'user_id' => (string) $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Expired trial users: {$updated}");
        $this->info("Free-trial-ended emails sent: {$emailsSent}");

        return self::SUCCESS;
    }
}
