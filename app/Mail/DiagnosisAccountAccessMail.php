<?php

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class DiagnosisAccountAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactRequest $contact,
        public bool $accountCreated,
        public ?string $setupUrl,
        public string $continueUrl
    ) {}

    public function build(): self
    {
        return $this
            ->subject(
                $this->accountCreated
                    ? 'Configura tu cuenta LAUDAAPI para continuar'
                    : 'Recibimos tu solicitud de Diagnóstico 360'
            )
            ->view('emails.diagnosis-account-access');
    }
}
