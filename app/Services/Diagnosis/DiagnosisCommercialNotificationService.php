<?php

namespace App\Services\Diagnosis;

use App\Mail\DiagnosisCommercialMilestoneMail;
use App\Models\DiagnosisAssessment;
use App\Models\Invoice;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DiagnosisCommercialNotificationService
{
    public function invoiceRequired(
        DiagnosisAssessment $assessment,
        string $deliverable,
        Invoice $invoice,
        Model $order
    ): void {
        $this->safeQueue(
            $assessment,
            $deliverable,
            'invoice_required',
            [
                ...$this->invoicePayload(
                    $deliverable,
                    $invoice,
                    $order
                ),
                'action_url' => route(
                    'diagnosis.show',
                    $assessment
                ),
            ]
        );
    }

    public function paymentConfirmed(
        DiagnosisAssessment $assessment,
        string $deliverable,
        Invoice $invoice,
        Model $order
    ): void {
        $this->safeQueue(
            $assessment,
            $deliverable,
            'payment_confirmed',
            [
                ...$this->invoicePayload(
                    $deliverable,
                    $invoice,
                    $order
                ),
                'action_url' => route(
                    'diagnosis.show',
                    $assessment
                ),
            ]
        );
    }

    public function deliverablePublished(
        DiagnosisAssessment $assessment,
        string $deliverable,
        int $version,
        string $url
    ): void {
        $this->safeQueue(
            $assessment,
            $deliverable,
            'published',
            [
                'version' => $version,
                'scope' =>
                    $this->scope($deliverable),
                'exclusions' =>
                    $this->exclusions($deliverable),
                'action_url' => $url,
            ]
        );
    }

    private function invoicePayload(
        string $deliverable,
        Invoice $invoice,
        Model $order
    ): array {
        $payload = [
            'invoice_id' => $invoice->id,
            'invoice_number' =>
                $invoice->number,
            'invoice_status' =>
                $invoice->status,
            'currency' =>
                $invoice->currency,
            'subtotal' =>
                (float) $invoice->subtotal,
            'discount_total' =>
                (float) $invoice->discount_total,
            'tax_total' =>
                (float) $invoice->tax_total,
            'total' =>
                (float) $invoice->total,
            'amount_paid' =>
                (float) $invoice->amount_paid,
            'scope' =>
                $this->scope($deliverable),
            'exclusions' =>
                $this->exclusions($deliverable),
        ];

        if (
            $deliverable
            === 'detailed_roadmap'
        ) {
            $payload['credit'] = [
                'eligible' =>
                    (bool) $order->getAttribute(
                        'credit_eligible'
                    ),
                'amount' =>
                    (float) (
                        $order->getAttribute(
                            'credit_amount'
                        ) ?? 0
                    ),
                'base_subtotal' =>
                    (float) (
                        $order->getAttribute(
                            'base_subtotal'
                        ) ?? 0
                    ),
                'net_subtotal' =>
                    (float) (
                        $order->getAttribute(
                            'net_subtotal'
                        ) ?? 0
                    ),
                'window_days' =>
                    (int) (
                        $order->getAttribute(
                            'credit_window_days'
                        ) ?? 0
                    ),
                'source_paid_at' =>
                    $order->getAttribute(
                        'credit_source_paid_at'
                    ),
                'expires_at' =>
                    $order->getAttribute(
                        'credit_expires_at'
                    ),
            ];
        }

        return $payload;
    }

    private function safeQueue(
        DiagnosisAssessment $assessment,
        string $deliverable,
        string $milestone,
        array $payload
    ): void {
        try {
            $assessment->loadMissing(
                'user:id,name,email'
            );

            $recipient =
                $assessment->user;

            if (
                ! $recipient
                || blank($recipient->email)
            ) {
                $this->safeAudit(
                    'diagnosis_commercial_email_skipped',
                    $assessment,
                    [
                        'deliverable' =>
                            $deliverable,
                        'milestone' =>
                            $milestone,
                        'reason' =>
                            'recipient_missing',
                    ]
                );

                return;
            }

            $this->safeAudit(
                'diagnosis_commercial_email_attempted',
                $assessment,
                [
                    'deliverable' =>
                        $deliverable,
                    'milestone' =>
                        $milestone,
                    'recipient' =>
                        $recipient->email,
                    'invoice_number' =>
                        $payload[
                            'invoice_number'
                        ] ?? null,
                ]
            );

            Mail::to($recipient->email)
                ->queue(
                    new DiagnosisCommercialMilestoneMail(
                        $assessment,
                        $deliverable,
                        $milestone,
                        $payload
                    )
                );

            $this->safeAudit(
                'diagnosis_commercial_email_queued',
                $assessment,
                [
                    'deliverable' =>
                        $deliverable,
                    'milestone' =>
                        $milestone,
                    'recipient' =>
                        $recipient->email,
                    'invoice_number' =>
                        $payload[
                            'invoice_number'
                        ] ?? null,
                ]
            );
        } catch (Throwable $e) {
            $this->safeAudit(
                'diagnosis_commercial_email_queue_failed',
                $assessment,
                [
                    'deliverable' =>
                        $deliverable,
                    'milestone' =>
                        $milestone,
                    'error' =>
                        $e->getMessage(),
                ]
            );

            report($e);
        }
    }

    private function safeAudit(
        string $event,
        DiagnosisAssessment $assessment,
        array $data
    ): void {
        try {
            AuditService::log(
                $event,
                $assessment,
                $data
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function scope(
        string $deliverable
    ): array {
        if (
            $deliverable
            === 'detailed_roadmap'
        ) {
            return [
                'Dirección ejecutiva de transformación.',
                'Cuatro horizontes de ejecución: 0–30, 31–90, 91–180 y 181–365 días.',
                'Iniciativas priorizadas con responsables sugeridos, dependencias, impacto y esfuerzo.',
                'Acciones e indicadores de éxito por iniciativa.',
                'Gobierno y seguimiento del Roadmap.',
                'Identificación de capacidades de Transformación Detallada, incluyendo Guía de Procesos y Procedimientos y Branding/Identidad Digital cuando corresponda.',
            ];
        }

        return [
            'Conclusión ejecutiva e interpretación de resultados.',
            'Contexto de negocio a partir del perfil comercial.',
            'Análisis de madurez por dimensiones.',
            'Brechas críticas y fortalezas relativas.',
            'Implicaciones para el negocio.',
            'Focos recomendados y modalidad de transformación.',
        ];
    }

    private function exclusions(
        string $deliverable
    ): array {
        if (
            $deliverable
            === 'detailed_roadmap'
        ) {
            return [
                'Ejecución técnica de las iniciativas.',
                'Desarrollo, parametrización, licencias e integraciones.',
                'Implementación completa de procedimientos.',
                'Ejecución de branding, diseño o producción de identidad visual.',
                'Acompañamiento posterior, salvo contratación específica.',
            ];
        }

        return [
            'Ejecución de las iniciativas recomendadas.',
            'Roadmap Detallado.',
            'Desarrollo tecnológico, integraciones o licencias.',
            'Branding o implementación de procedimientos.',
        ];
    }
}
