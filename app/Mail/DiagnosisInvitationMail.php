<?php

namespace App\Mail;

use App\Models\DiagnosisAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DiagnosisInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DiagnosisAccessRequest $access,
        public string $invitationUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu Diagnóstico LAUDA 360 está listo',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.diagnosis-invitation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
