<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FreeTrialExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Your Free Trial Has Ended – Continue Your Peers Global Journey')
            ->view('emails.membership.free_trial_expired')
            ->with([
                'user' => $this->user,
            ]);
    }
}
