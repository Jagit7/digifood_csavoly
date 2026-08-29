<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sikeres bankkártyás (CIB) tranzakció után kiküldött visszaigazoló e-mail
 * a szülő/gondviselő részére. A banki visszajelzés kifejezetten hiányolta
 * ezt a funkciót ("A tranzakciókról egyáltalán nem érkezek megerősítő
 * e-mailek."), ezért a fizetés lezárásakor (CardPaymentService::
 * bookSuccessfulPayment) kerül kiküldésre.
 */
class PaymentConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $payload
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Digifood - Sikeres bankkártyás fizetés visszaigazolása',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-confirmation',
            with: $this->payload,
        );
    }
}
