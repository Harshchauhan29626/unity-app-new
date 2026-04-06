<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MembershipPurchaseCongratulationsMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Congratulations! Your Membership Is Now Active')
            ->view('emails.membership.membership_purchase_congratulations')
            ->with([
                'user' => $this->user,
            ]);
    }
}
