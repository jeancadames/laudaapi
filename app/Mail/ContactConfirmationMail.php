<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\ContactRequest;

class ContactConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactRequest $contact) {}

    public function build()
    {
        return $this->subject('Hemos recibido tu mensaje')
            ->view('emails.contact.confirmation')
            ->with([
                'contact' => $this->contact,
            ]);
    }
}
