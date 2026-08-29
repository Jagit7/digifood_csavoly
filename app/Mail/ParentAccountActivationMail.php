<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParentAccountActivationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $payload
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Digifood - Szülői fiók aktiválása',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.parent-account-activation',
            with: $this->payload,
        );
    }
}
