<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentPeriodNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $payload
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) $this->payload['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-period-notification',
            with: $this->payload,
        );
    }
}
