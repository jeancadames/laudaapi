<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\ActivationRequest;

class ActivationInternalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $locale = 'es';

    public function __construct(public ActivationRequest $activation) {}

    public function build()
    {
        $adminUrl = route('admin.requests.show', $this->activation->id);

        return $this->view('emails.activation.internal')
            ->with([
                'activation' => $this->activation,
                'adminUrl' => $adminUrl,
            ]);
    }
}
