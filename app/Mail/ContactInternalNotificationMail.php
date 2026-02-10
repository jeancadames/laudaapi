<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\ContactRequest;

class ContactInternalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactRequest $contact) {}

    public function build()
    {
        return $this->subject('Nueva solicitud de contacto')
            ->view('emails.contact.internal')
            ->with([
                'contact' => $this->contact,
            ]);
    }
}
