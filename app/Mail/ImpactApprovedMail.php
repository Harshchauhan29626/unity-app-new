<?php

namespace App\Mail;

use App\Models\Impact;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ImpactApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Impact $impact, public User $submitter)
    {
    }

    public function build(): self
    {
        return $this->subject('Your Impact Has Been Approved')
            ->view('emails.impact_approved');
    }
}
