<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyHeadcountEmailMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $summary
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) $this->summary['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-headcount',
            with: $this->summary,
        );
    }
}
