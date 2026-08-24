<?php

namespace App\Services\Diagnosis;

use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisDetailedRoadmapOrder;
use App\Models\DiagnosisExpandedReport;
use App\Models\DiagnosisExpandedReportOrder;

class DiagnosisTransformationProgressService
{
    public function forAssessment(
        DiagnosisAssessment $assessment,
        bool $admin = false
    ): array {
        $state = $this->state($assessment);

        $steps = [
            $this->step(
                'request_submitted',
                'Solicitud de diagnóstico enviada',
                $state['contact'] !== null,
                $state['contact']?->created_at,
                'Su solicitud fue registrada para revisión por LAUDA.',
                'Revisar solicitud y decidir acceso.'
            ),
            $this->step(
                'access_approved',
                'Acceso aprobado por LAUDA',
                $state['workflow']?->approved_at !== null
                    || in_array(
                        $state['workflow']?->status,
                        ['approved', 'invited', 'active'],
                        true
                    ),
                $state['workflow']?->approved_at,
                'LAUDA aprobó el acceso privado al Diagnóstico 360.',
                'Aprobar acceso o solicitar información adicional.'
            ),
            $this->step(
                'invitation_sent',
                'Invitación enviada',
                $state['workflow']?->invitation_sent_at !== null,
                $state['workflow']?->invitation_sent_at,
                'La invitación privada fue enviada al correo registrado.',
                'Enviar o reenviar invitación.'
            ),
            $this->step(
                'account_activated',
                'Cuenta / invitación activada',
                $state['workflow']?->invitation_accepted_at !== null
                    || $state['workflow']?->status === 'active',
                $state['workflow']?->invitation_accepted_at,
                'El acceso al Diagnóstico LAUDA 360 fue activado.',
                'Esperar activación del cliente.'
            ),
            $this->step(
                'diagnosis_started',
                'Diagnóstico en progreso',
                $assessment->started_at !== null
                    || in_array(
                        $assessment->status,
                        ['in_progress', 'submitted', 'reviewed'],
                        true
                    ),
                $assessment->started_at,
                'El Diagnóstico LAUDA 360 fue iniciado.',
                'Dar seguimiento al llenado del diagnóstico.'
            ),
            $this->step(
                'diagnosis_submitted',
                'Diagnóstico enviado',
                $assessment->submitted_at !== null,
                $assessment->submitted_at,
                'El diagnóstico fue enviado a LAUDA para revisión.',
                'Revisar scoring, contexto y conclusión ejecutiva.'
            ),
            $this->step(
                'diagnosis_reviewed',
                'Resultado en revisión LAUDA',
                $assessment->reviewed_at !== null
                    || $assessment->published_at !== null,
                $assessment->reviewed_at,
                'LAUDA revisa y valida el resultado antes de publicarlo.',
                'Completar revisión y preparar publicación.'
            ),
            $this->step(
                'diagnosis_published',
                'Resultado gratuito publicado',
                $assessment->published_at !== null,
                $assessment->published_at,
                'El resultado oficial gratuito está disponible.',
                'Dar seguimiento a la decisión sobre Informe Ampliado.'
            ),
            $this->step(
                'expanded_report_requested',
                'Informe Ampliado solicitado',
                $state['expanded_order']?->requested_at !== null,
                $state['expanded_order']?->requested_at,
                'La solicitud comercial del Informe Ampliado fue recibida.',
                'Preparar factura del Informe Ampliado.'
            ),
            $this->step(
                'expanded_report_invoiced',
                'Factura del Informe preparada',
                $state['expanded_order']?->invoiced_at !== null
                    || $state['expanded_order']?->invoice_id !== null,
                $state['expanded_order']?->invoiced_at,
                'La facturación del Informe Ampliado fue preparada.',
                'Dar seguimiento al pago del Informe Ampliado.'
            ),
            $this->step(
                'expanded_report_paid',
                'Pago del Informe confirmado',
                $state['expanded_order']?->paid_at !== null
                    && $state['expanded_order']?->invoice?->status === 'paid',
                $state['expanded_order']?->paid_at,
                'El pago del Informe Ampliado fue confirmado.',
                'Completar revisión y publicación del Informe.'
            ),
            $this->step(
                'expanded_report_published',
                'Informe Ampliado publicado',
                $state['expanded_report']?->published_at !== null,
                $state['expanded_report']?->published_at,
                'El Informe Ampliado está disponible en la cuenta.',
                'Dar seguimiento a la solicitud del Roadmap Detallado.'
            ),
            $this->step(
                'roadmap_requested',
                'Roadmap Detallado solicitado',
                $state['roadmap_order']?->requested_at !== null,
                $state['roadmap_order']?->requested_at,
                'La solicitud comercial del Roadmap fue recibida.',
                'Preparar factura del Roadmap Detallado.'
            ),
            $this->step(
                'roadmap_invoiced',
                'Factura del Roadmap preparada',
                $state['roadmap_order']?->invoiced_at !== null
                    || $state['roadmap_order']?->invoice_id !== null,
                $state['roadmap_order']?->invoiced_at,
                'La facturación del Roadmap fue preparada.',
                'Dar seguimiento al pago del Roadmap.'
            ),
            $this->step(
                'roadmap_paid',
                'Pago del Roadmap confirmado',
                $state['roadmap_order']?->paid_at !== null
                    && $state['roadmap_order']?->invoice?->status === 'paid',
                $state['roadmap_order']?->paid_at,
                'El pago del Roadmap fue confirmado.',
                'Completar preparación y revisión del Roadmap.'
            ),
            $this->step(
                'roadmap_preparation',
                'Roadmap en preparación',
                $state['roadmap'] !== null,
                $state['roadmap']?->created_at,
                'LAUDA está preparando o revisando el Roadmap Detallado.',
                'Revisar Roadmap y verificar requisitos de publicación.'
            ),
            $this->step(
                'roadmap_published',
                'Roadmap publicado',
                $state['roadmap']?->published_at !== null,
                $state['roadmap']?->published_at,
                'El Roadmap Detallado está disponible para el cliente.',
                'Coordinar la siguiente fase de transformación.'
            ),
            $this->step(
                'execution',
                'Transformación / ejecución',
                false,
                null,
                'La ejecución se define y contrata según el Roadmap aprobado.',
                'Definir alcance, propuesta y puesta en marcha de la ejecución.'
            ),
        ];

        $blockedCode = $this->blockedCode($state);
        $currentFound = false;

        foreach ($steps as $index => $step) {
            if ($step['completed']) {
                $steps[$index]['status'] = 'completed';

                continue;
            }

            if (! $currentFound) {
                $steps[$index]['status'] =
                    $step['code'] === $blockedCode
                        ? 'blocked'
                        : 'current';

                $currentFound = true;

                continue;
            }

            $steps[$index]['status'] = 'pending';
        }

        $completedCount = count(
            array_filter(
                $steps,
                fn (array $step): bool =>
                    $step['status'] === 'completed'
            )
        );

        $current = collect($steps)->first(
            fn (array $step): bool =>
                in_array(
                    $step['status'],
                    ['current', 'blocked'],
                    true
                )
        );

        if ($admin) {
            $steps = $this->withAdminDetails(
                $steps,
                $state
            );
        } else {
            foreach ($steps as $index => $step) {
                unset($steps[$index]['admin_action']);
                unset($steps[$index]['admin_detail']);
            }
        }

        return [
            'current_step' =>
                $current['code'] ?? null,
            'current_step_label' =>
                $current['label'] ?? null,
            'completed_count' =>
                $completedCount,
            'total' =>
                count($steps),
            'percentage' =>
                (int) round(
                    ($completedCount / count($steps))
                    * 100
                ),
            'next_action' =>
                $admin
                    ? ($current['admin_action'] ?? null)
                    : ($current['description'] ?? null),
            'steps' =>
                array_values($steps),
        ];
    }

    public function roadmapReadiness(
        DiagnosisAssessment $assessment
    ): array {
        $state = $this->state($assessment);

        $diagnosisPublished =
            $assessment->status === 'reviewed'
            && $assessment->published_at !== null;

        $expandedPublished =
            $state['expanded_report']?->published_at !== null;

        $existingRoadmap =
            $state['roadmap'];

        $generationBlockers = [];

        if (! $diagnosisPublished) {
            $generationBlockers[] =
                'Falta publicar el resultado oficial del Diagnóstico LAUDA 360.';
        }

        if (! $expandedPublished) {
            $generationBlockers[] =
                'Falta publicar el Informe Ampliado.';
        }

        if ($existingRoadmap) {
            $generationBlockers[] =
                sprintf(
                    'El Roadmap V%d ya fue generado y está %s. No debe generarse otra versión mientras esta versión esté activa.',
                    $existingRoadmap->version,
                    $this->roadmapStatusLabel(
                        $existingRoadmap->status
                    )
                );
        }

        $paidAccess =
            $state['roadmap_order']?->paid_at !== null
            && $state['roadmap_order']?->invoice?->status === 'paid';

        $publicationBlockers = [];

        if (! $existingRoadmap) {
            $publicationBlockers[] =
                'Primero debe generarse el Roadmap Detallado.';
        } elseif (
            ! in_array(
                $existingRoadmap->status,
                [
                    DiagnosisDetailedRoadmap::STATUS_DRAFT,
                    DiagnosisDetailedRoadmap::STATUS_UNDER_REVIEW,
                ],
                true
            )
        ) {
            $publicationBlockers[] =
                'El Roadmap ya no está en un estado editable.';
        }

        if (! $paidAccess) {
            $commercialStatus =
                $state['roadmap_order']?->status;

            $publicationBlockers[] =
                match ($commercialStatus) {
                    null =>
                        'El cliente todavía no ha solicitado comercialmente el Roadmap.',
                    DiagnosisDetailedRoadmapOrder::STATUS_REQUESTED =>
                        'Solicitud recibida. Falta preparar la factura del Roadmap.',
                    DiagnosisDetailedRoadmapOrder::STATUS_INVOICED =>
                        'Factura preparada. Falta confirmar el pago del Roadmap.',
                    DiagnosisDetailedRoadmapOrder::STATUS_CANCELLED =>
                        'La solicitud comercial del Roadmap está cancelada.',
                    DiagnosisDetailedRoadmapOrder::STATUS_PAID =>
                        'El pago no está completamente conciliado con la factura del Roadmap.',
                    default =>
                        'Falta confirmar el acceso comercial del Roadmap.',
                };
        }

        return [
            'generation_ready' =>
                $diagnosisPublished
                && $expandedPublished
                && $existingRoadmap === null,

            'generation_blockers' =>
                $generationBlockers,

            'publication_ready' =>
                $existingRoadmap !== null
                && in_array(
                    $existingRoadmap->status,
                    [
                        DiagnosisDetailedRoadmap::STATUS_DRAFT,
                        DiagnosisDetailedRoadmap::STATUS_UNDER_REVIEW,
                    ],
                    true
                )
                && $paidAccess,

            'publication_blockers' =>
                $publicationBlockers,

            'prerequisites' => [
                'diagnosis_published' =>
                    $diagnosisPublished,
                'expanded_report_published' =>
                    $expandedPublished,
                'roadmap_requested' =>
                    $state['roadmap_order']?->requested_at !== null,
                'roadmap_invoiced' =>
                    $state['roadmap_order']?->invoiced_at !== null
                    || $state['roadmap_order']?->invoice_id !== null,
                'roadmap_paid' =>
                    $paidAccess,
            ],

            'commercial' => [
                'status' =>
                    $state['roadmap_order']?->status,
                'invoice_number' =>
                    $state['roadmap_order']?->invoice?->number,
                'invoice_status' =>
                    $state['roadmap_order']?->invoice?->status,
                'paid_at' =>
                    $state['roadmap_order']?->paid_at
                        ?->toISOString(),
            ],

            'existing_roadmap' =>
                $existingRoadmap
                    ? [
                        'id' =>
                            $existingRoadmap->id,
                        'version' =>
                            $existingRoadmap->version,
                        'status' =>
                            $existingRoadmap->status,
                        'status_label' =>
                            $this->roadmapStatusLabel(
                                $existingRoadmap->status
                            ),
                        'published_at' =>
                            $existingRoadmap->published_at
                                ?->toISOString(),
                    ]
                    : null,
        ];
    }

    private function state(
        DiagnosisAssessment $assessment
    ): array {
        $workflow =
            DiagnosisAccessRequest::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->first();

        $contact =
            $workflow?->contact_request_id
                ? ContactRequest::query()
                    ->find(
                        $workflow->contact_request_id
                    )
                : null;

        $expandedReport =
            DiagnosisExpandedReport::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->where(
                    'status',
                    DiagnosisExpandedReport::STATUS_PUBLISHED
                )
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->first();

        $expandedOrder =
            DiagnosisExpandedReportOrder::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->with(
                    'invoice:id,number,status,total,amount_paid'
                )
                ->first();

        $roadmap =
            DiagnosisDetailedRoadmap::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->orderByDesc('version')
                ->first();

        $roadmapOrder =
            DiagnosisDetailedRoadmapOrder::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->with(
                    'invoice:id,number,status,total,amount_paid'
                )
                ->first();

        return [
            'workflow' =>
                $workflow,
            'contact' =>
                $contact,
            'expanded_report' =>
                $expandedReport,
            'expanded_order' =>
                $expandedOrder,
            'roadmap' =>
                $roadmap,
            'roadmap_order' =>
                $roadmapOrder,
        ];
    }

    private function step(
        string $code,
        string $label,
        bool $completed,
        mixed $occurredAt,
        string $description,
        string $adminAction
    ): array {
        return [
            'code' =>
                $code,
            'label' =>
                $label,
            'completed' =>
                $completed,
            'status' =>
                'pending',
            'occurred_at' =>
                $occurredAt?->toISOString(),
            'description' =>
                $description,
            'admin_action' =>
                $adminAction,
            'admin_detail' =>
                null,
        ];
    }

    private function blockedCode(
        array $state
    ): ?string {
        if (
            $state['workflow']?->status
            === DiagnosisAccessRequest::STATUS_REJECTED
        ) {
            return 'access_approved';
        }

        if (
            $state['expanded_order']?->status
            === DiagnosisExpandedReportOrder::STATUS_CANCELLED
        ) {
            return 'expanded_report_requested';
        }

        if (
            $state['roadmap_order']?->status
            === DiagnosisDetailedRoadmapOrder::STATUS_CANCELLED
        ) {
            return 'roadmap_requested';
        }

        return null;
    }

    private function withAdminDetails(
        array $steps,
        array $state
    ): array {
        $details = [
            'request_submitted' =>
                $state['contact']
                    ? 'Contacto #' . $state['contact']->id
                    : null,

            'access_approved' =>
                $state['workflow']
                    ? 'Workflow: ' . $state['workflow']->status
                    : null,

            'expanded_report_requested' =>
                $state['expanded_order']
                    ? 'Orden Informe #' .
                        $state['expanded_order']->id .
                        ' · ' .
                        $state['expanded_order']->status
                    : null,

            'expanded_report_invoiced' =>
                $state['expanded_order']?->invoice
                    ? 'Factura ' .
                        $state['expanded_order']->invoice->number .
                        ' · ' .
                        $state['expanded_order']->invoice->status
                    : null,

            'roadmap_requested' =>
                $state['roadmap_order']
                    ? 'Orden Roadmap #' .
                        $state['roadmap_order']->id .
                        ' · ' .
                        $state['roadmap_order']->status
                    : null,

            'roadmap_invoiced' =>
                $state['roadmap_order']?->invoice
                    ? 'Factura ' .
                        $state['roadmap_order']->invoice->number .
                        ' · ' .
                        $state['roadmap_order']->invoice->status
                    : null,

            'roadmap_preparation' =>
                $state['roadmap']
                    ? 'Roadmap V' .
                        $state['roadmap']->version .
                        ' · ' .
                        $this->roadmapStatusLabel(
                            $state['roadmap']->status
                        )
                    : null,
        ];

        foreach ($steps as $index => $step) {
            $steps[$index]['admin_detail'] =
                $details[$step['code']] ?? null;
        }

        return $steps;
    }

    private function roadmapStatusLabel(
        string $status
    ): string {
        return match ($status) {
            DiagnosisDetailedRoadmap::STATUS_DRAFT =>
                'Borrador',
            DiagnosisDetailedRoadmap::STATUS_UNDER_REVIEW =>
                'En revisión',
            DiagnosisDetailedRoadmap::STATUS_PUBLISHED =>
                'Publicado',
            default =>
                $status,
        };
    }
}
