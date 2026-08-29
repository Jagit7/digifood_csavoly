<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeAccountActivationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $payload
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Digifood - Dolgozói fiók aktiválása',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-account-activation',
            with: $this->payload,
        );
    }
}
