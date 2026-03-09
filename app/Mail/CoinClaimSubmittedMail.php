<?php

namespace App\Mail;

use App\Models\CoinClaimRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CoinClaimSubmittedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CoinClaimRequest $claim)
    {
    }

    public function build(): self
    {
        return $this->subject('Coin claim submitted')
            ->view('emails.coin_claim_submitted');
    }
}
