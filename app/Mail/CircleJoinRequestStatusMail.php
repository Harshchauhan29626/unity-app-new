<?php

namespace App\Mail;

use App\Models\CircleJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CircleJoinRequestStatusMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CircleJoinRequest $circleJoinRequest,
        public string $subjectLine,
        public string $title,
        public string $body,
        public ?string $statusLabel = null,
        public ?string $rejectionReason = null,
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.circle_join_request_status');
    }
}
