<?php

namespace App\Mail;

use App\Models\CoinClaimRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CoinClaimRejectedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CoinClaimRequest $claim)
    {
    }

    public function build(): self
    {
        return $this->subject('Coin claim rejected')
            ->view('emails.coin_claim_rejected');
    }
}
