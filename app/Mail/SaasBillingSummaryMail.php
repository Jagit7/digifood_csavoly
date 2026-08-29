<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SaasBillingSummaryMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly array $data)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Digifood havi számlázási összesítő – '.$this->data['monthLabel']
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.saas-billing-summary',
            with: $this->data,
        );
    }
}
