<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\ActivationRequest;

class ActivationConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ActivationRequest $activation,
        public string $activationUrl
    ) {}

    public function build()
    {
        return $this->subject('Activa tu prueba gratuita de 30 días')
            ->view('emails.activation.confirmation')
            ->with([
                'activationUrl' => $this->activationUrl,
                'activation' => $this->activation,
            ]);
    }
}
