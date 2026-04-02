<?php

namespace App\Mail;

use App\Models\Impact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ImpactSubmittedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Impact $impact)
    {
    }

    public function build(): self
    {
        return $this->subject('Impact Submitted Successfully')
            ->view('emails.impact_submitted');
    }
}
