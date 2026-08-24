<?php

namespace App\Mail;

use App\Models\DiagnosisAssessment;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DiagnosisCommercialMilestoneMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public DiagnosisAssessment $assessment,
        public string $deliverable,
        public string $milestone,
        public array $payload
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine())
            ->view(
                'emails.diagnosis-commercial-milestone'
            );
    }

    public function failed(Throwable $exception): void
    {
        try {
            AuditService::log(
                'diagnosis_commercial_email_delivery_failed',
                $this->assessment,
                [
                    'deliverable' => $this->deliverable,
                    'milestone' => $this->milestone,
                    'recipient' =>
                        $this->assessment->user?->email,
                    'error' => $exception->getMessage(),
                ]
            );
        } catch (Throwable $auditException) {
            report($auditException);
        }

        report($exception);
    }

    private function subjectLine(): string
    {
        return match (
            $this->deliverable . ':' . $this->milestone
        ) {
            'expanded_report:invoice_required' =>
                'Informe Ampliado LAUDA 360 · Pago requerido',

            'expanded_report:payment_confirmed' =>
                'Pago confirmado · Informe Ampliado LAUDA 360',

            'expanded_report:published' =>
                'Su Informe Ampliado LAUDA 360 está disponible',

            'detailed_roadmap:invoice_required' =>
                'Roadmap Detallado LAUDA 360 · Pago requerido',

            'detailed_roadmap:payment_confirmed' =>
                'Pago confirmado · Roadmap Detallado LAUDA 360',

            'detailed_roadmap:published' =>
                'Su Roadmap Detallado LAUDA 360 está disponible',

            default =>
                'Actualización LAUDA Transformación Digital 360',
        };
    }
}
