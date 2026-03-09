<?php

namespace App\Services\CoinClaims;

use App\Mail\CoinClaimApprovedMail;
use App\Mail\CoinClaimRejectedMail;
use App\Mail\CoinClaimSubmittedMail;
use App\Models\CoinClaimRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CoinClaimEmailService
{
    public function sendSubmitted(CoinClaimRequest $claim): void
    {
        $this->safeSend($claim, new CoinClaimSubmittedMail($claim), 'submitted');
    }

    public function sendApproved(CoinClaimRequest $claim): void
    {
        $this->safeSend($claim, new CoinClaimApprovedMail($claim), 'approved');
    }

    public function sendRejected(CoinClaimRequest $claim): void
    {
        $this->safeSend($claim, new CoinClaimRejectedMail($claim), 'rejected');
    }

    private function safeSend(CoinClaimRequest $claim, object $mailable, string $type): void
    {
        try {
            $email = $claim->user?->email;
            if (! $email) {
                return;
            }

            Mail::to($email)->send($mailable);
        } catch (\Throwable $exception) {
            Log::warning('Coin claim email send failed', [
                'claim_id' => (string) $claim->id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
