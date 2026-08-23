<?php

namespace App\Mail;

use App\Models\DiagnosisAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DiagnosisResultPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DiagnosisAssessment $assessment,
        public string $resultUrl
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Su resultado del Diagnóstico LAUDA 360 está disponible')
            ->view('emails.diagnosis-result-published');
    }
}
