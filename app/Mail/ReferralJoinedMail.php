<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReferralJoinedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $subjectLine = 'New Referral Joined';

    public function __construct(
        public readonly string $referrerName,
        public readonly string $peerName,
        public readonly string $referralCode,
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.referral_joined');
    }
}
