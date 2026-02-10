<?php

namespace App\Mail;

use App\Models\ActivationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivationDiscardedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ActivationRequest $activation) {}

    public function build()
    {
        return $this
            ->subject('Tu solicitud de activación ha expirado')
            ->view('emails.activation.discarded')
            ->with([
                'activation' => $this->activation,
            ]);
    }
}
