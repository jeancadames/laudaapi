<?php

namespace App\Mail;

use App\Models\ActivationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivationPendingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ActivationRequest $activation,
        public string $activationUrl
    ) {}

    public function build()
    {
        return $this->subject('Recordatorio: activa tu prueba gratuita (link expira pronto)')
            ->view('emails.activation.pendingreminder')
            ->with([
                'activation' => $this->activation,
                'activationUrl' => $this->activationUrl,
            ]);
    }
}
