<?php

namespace App\Mail;

use App\Models\ActivationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivationReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ActivationRequest $activation) {}

    public function build()
    {
        $dashboardUrl = route('subscriber'); // o config('app.url').'/subscriber'

        return $this->subject('Recordatorio: activa tu prueba desde tu dashboard')
            ->view('emails.activation.reminder')
            ->with([
                'activation' => $this->activation,
                'dashboardUrl' => $dashboardUrl,
            ]);
    }
}
