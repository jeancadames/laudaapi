<?php

namespace App\Services\Diagnosis;

use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDeliverableValidation;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Models\TransformationImplementationPlan;

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
                'Resultado oficial publicado',
                $assessment->published_at !== null,
                $assessment->published_at,
                'El resultado oficial del Diagnóstico 360 está disponible.',
                'Preparar el Informe Ampliado gratuito.'
            ),
            $this->step(
                'expanded_report_preparation',
                'Informe Ampliado en preparación',
                $state['expanded_report'] !== null,
                $state['expanded_report']?->created_at,
                'LAUDA está preparando o revisando el Informe Ampliado gratuito.',
                'Completar revisión y publicación del Informe Ampliado.'
            ),
            $this->step(
                'expanded_report_published',
                'Informe Ampliado publicado',
                $state['expanded_report']?->published_at !== null,
                $state['expanded_report']?->published_at,
                'El Informe Ampliado está disponible en la cuenta.',
                'Preparar el Roadmap Detallado gratuito.'
            ),
            $this->step(
                'expanded_report_reviewed',
                'Informe Ampliado revisado por tenant',
                $state['expanded_validation']?->reviewed_at !== null,
                $state['expanded_validation']?->reviewed_at,
                'Revisa el Informe Ampliado y confirma que refleja adecuadamente el contexto de tu empresa.',
                'Dar seguimiento a la revisión del tenant.'
            ),
            $this->step(
                'expanded_report_validated',
                'Informe Ampliado validado',
                $state['expanded_validation']?->validated_at !== null,
                $state['expanded_validation']?->validated_at,
                'El tenant validó el Informe Ampliado sin generar compromiso comercial.',
                'Registrar cualquier ajuste solicitado o continuar seguimiento.'
            ),
            $this->step(
                'roadmap_preparation',
                'Roadmap en preparación',
                $state['roadmap'] !== null,
                $state['roadmap']?->created_at,
                'LAUDA está preparando o revisando el Roadmap Detallado gratuito.',
                'Completar revisión y publicación del Roadmap.'
            ),
            $this->step(
                'roadmap_published',
                'Roadmap publicado',
                $state['roadmap']?->published_at !== null,
                $state['roadmap']?->published_at,
                'El Roadmap Detallado está disponible para el cliente.',
                'El Plan de Implementación se genera y presenta automáticamente.'
            ),
            $this->step(
                'roadmap_reviewed',
                'Roadmap revisado por tenant',
                $state['roadmap_validation']?->reviewed_at !== null,
                $state['roadmap_validation']?->reviewed_at,
                'Revisa fases, iniciativas, responsables y dependencias del Roadmap.',
                'Dar seguimiento a la revisión del tenant.'
            ),
            $this->step(
                'roadmap_validated',
                'Roadmap validado',
                $state['roadmap_validation']?->validated_at !== null,
                $state['roadmap_validation']?->validated_at,
                'El tenant validó el Roadmap sin generar compromiso comercial.',
                'Registrar cualquier ajuste solicitado o continuar seguimiento.'
            ),
            $this->step(
                'implementation_plan',
                'Plan de Implementación presentado',
                $state['plan']?->presented_at !== null,
                $state['plan']?->presented_at,
                'El Plan de Implementación gratuito está disponible para revisión.',
                'Dar seguimiento a la revisión del Plan.'
            ),
            $this->step(
                'implementation_plan_reviewed',
                'Plan revisado por tenant',
                $state['plan_validation']?->reviewed_at !== null,
                $state['plan_validation']?->reviewed_at,
                'Revisa el Plan consultivo, sus fases, actividades y entregables.',
                'Dar seguimiento a la revisión del tenant.'
            ),
            $this->step(
                'implementation_plan_validated',
                'Plan de Implementación validado',
                $state['plan_validation']?->validated_at !== null,
                $state['plan_validation']?->validated_at,
                'El tenant validó el Plan. Esta validación no constituye contratación.',
                'El flujo consultivo gratuito quedó validado por el tenant.'
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

        $publicationReady =
            $existingRoadmap !== null
            && in_array(
                $existingRoadmap->status,
                [
                    DiagnosisDetailedRoadmap::STATUS_DRAFT,
                    DiagnosisDetailedRoadmap::STATUS_UNDER_REVIEW,
                ],
                true
            );

        return [
            'generation_ready' =>
                $diagnosisPublished
                && $expandedPublished
                && $existingRoadmap === null,

            'generation_blockers' =>
                $generationBlockers,

            'publication_ready' =>
                $publicationReady,

            'publication_blockers' =>
                $publicationBlockers,

            'prerequisites' => [
                'diagnosis_published' =>
                    $diagnosisPublished,
                'expanded_report_published' =>
                    $expandedPublished,
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
                ->orderByDesc('version')
                ->first();

        $roadmap =
            DiagnosisDetailedRoadmap::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->orderByDesc('version')
                ->first();

        $plan = TransformationImplementationPlan::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->orderByDesc('version')
            ->first();

        $expandedValidation = $this->validationFor(
            DiagnosisDeliverableValidation::TYPE_EXPANDED_REPORT,
            $expandedReport?->id
        );

        $roadmapValidation = $this->validationFor(
            DiagnosisDeliverableValidation::TYPE_DETAILED_ROADMAP,
            $roadmap?->id
        );

        $planValidation = $this->validationFor(
            DiagnosisDeliverableValidation::TYPE_IMPLEMENTATION_PLAN,
            $plan?->id
        );

        return [
            'workflow' =>
                $workflow,
            'contact' =>
                $contact,
            'expanded_report' =>
                $expandedReport,
            'roadmap' =>
                $roadmap,
            'plan' =>
                $plan,
            'expanded_validation' =>
                $expandedValidation,
            'roadmap_validation' =>
                $roadmapValidation,
            'plan_validation' =>
                $planValidation,
        ];
    }

    private function validationFor(
        string $type,
        ?int $deliverableId
    ): ?DiagnosisDeliverableValidation {
        if (! $deliverableId) {
            return null;
        }

        return DiagnosisDeliverableValidation::query()
            ->where('deliverable_type', $type)
            ->where('deliverable_id', $deliverableId)
            ->first();
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

            'expanded_report_preparation' =>
                $state['expanded_report']
                    ? 'Informe V' .
                        $state['expanded_report']->version .
                        ' · ' .
                        $state['expanded_report']->status
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

            'expanded_report_reviewed' =>
                $state['expanded_validation']?->reviewed_at
                    ? 'Revisado por tenant'
                    : null,

            'expanded_report_validated' =>
                $state['expanded_validation']?->validated_at
                    ? 'Validado por tenant'
                    : null,

            'roadmap_reviewed' =>
                $state['roadmap_validation']?->reviewed_at
                    ? 'Revisado por tenant'
                    : null,

            'roadmap_validated' =>
                $state['roadmap_validation']?->validated_at
                    ? 'Validado por tenant'
                    : null,

            'implementation_plan' =>
                $state['plan']?->presented_at
                    ? 'Plan V' . $state['plan']->version . ' · presentado'
                    : null,

            'implementation_plan_reviewed' =>
                $state['plan_validation']?->reviewed_at
                    ? 'Revisado por tenant'
                    : null,

            'implementation_plan_validated' =>
                $state['plan_validation']?->validated_at
                    ? 'Validado por tenant'
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
