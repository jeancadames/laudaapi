<?php

namespace App\Mail;

use App\Models\DiagnosisAccessRequest;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DiagnosisMoreInfoRequiredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public DiagnosisAccessRequest $workflow,
        public array $missingInformation,
        public ?string $reviewNotes = null
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject(
                'Necesitamos información adicional para continuar con tu Diagnóstico 360'
            )
            ->view(
                'emails.diagnosis-more-info-required'
            );
    }

    public function failed(Throwable $exception): void
    {
        try {
            AuditService::log(
                'diagnosis_more_info_email_delivery_failed',
                $this->workflow,
                [
                    'recipient' =>
                        $this->workflow->user?->email,
                    'assessment_id' =>
                        $this->workflow->diagnosis_assessment_id,
                    'error' => $exception->getMessage(),
                ]
            );
        } catch (Throwable $auditException) {
            report($auditException);
        }

        report($exception);
    }
}
