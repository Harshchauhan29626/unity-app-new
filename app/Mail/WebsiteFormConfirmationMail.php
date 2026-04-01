<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WebsiteFormConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $recipientName,
        public readonly string $formTitle,
        public readonly string $confirmationMessage,
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.website_form_confirmation');
    }
}
