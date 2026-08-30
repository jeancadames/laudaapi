<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\TransformationCapabilityActivation;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationCapabilityActivationService
{
    public function __construct(
        private readonly TransformationCapabilityNeedService $needs,
        private readonly TransformationCapabilityDecisionService $decisions
    ) {
    }

    public function activateFromRoadmap(
        Company $company,
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmap $roadmap,
        string $capabilityKey,
        User $actor
    ): TransformationCapabilityActivation {
        $capabilityKey = trim($capabilityKey);
        $definition = $this->professionalDefinition($capabilityKey);

        $this->assertAssessmentCompany($assessment, $company);
        $this->assertPublishedRoadmap($assessment, $roadmap);

        $roadmapDefinition = $this->roadmapDefinition(
            $roadmap,
            $capabilityKey
        );

        return DB::transaction(function () use (
            $company,
            $assessment,
            $roadmap,
            $capabilityKey,
            $definition,
            $roadmapDefinition,
            $actor
        ): TransformationCapabilityActivation {
            $activation = $this->persistActivation(
                $company,
                $capabilityKey,
                $definition,
                $actor,
                $assessment,
                $roadmap,
                $roadmapDefinition,
                TransformationCapabilityActivation::SOURCE_DETAILED_ROADMAP
            );

            $this->decisions->acceptFromRoadmap(
                $company,
                $assessment,
                $roadmap,
                $capabilityKey,
                $actor
            );

            return $activation->fresh();
        }, 3);
    }

    public function activateManually(
        Company $company,
        string $capabilityKey,
        User $actor
    ): TransformationCapabilityActivation {
        $capabilityKey = trim($capabilityKey);
        $definition = $this->professionalDefinition($capabilityKey);

        return DB::transaction(function () use (
            $company,
            $capabilityKey,
            $definition,
            $actor
        ): TransformationCapabilityActivation {
            return $this->persistActivation(
                $company,
                $capabilityKey,
                $definition,
                $actor,
                null,
                null,
                null,
                TransformationCapabilityActivation::SOURCE_MANUAL
            );
        }, 3);
    }

    private function persistActivation(
        Company $company,
        string $capabilityKey,
        array $definition,
        User $actor,
        ?DiagnosisAssessment $assessment,
        ?DiagnosisDetailedRoadmap $roadmap,
        ?array $roadmapDefinition,
        string $sourceType
    ): TransformationCapabilityActivation {
        $existing = TransformationCapabilityActivation::query()
            ->where('company_id', $company->id)
            ->where('capability_key', $capabilityKey)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if (
                (int) $existing->company_id
                    !== (int) $company->id
            ) {
                throw ValidationException::withMessages([
                    'capability' => [
                        'La activación existente no pertenece a la empresa indicada.',
                    ],
                ]);
            }

            if (
                $existing->status
                    === TransformationCapabilityActivation::STATUS_CANCELLED
            ) {
                $existing->forceFill([
                    'diagnosis_assessment_id' => $assessment?->id,
                    'source_type' => $sourceType,
                    'source_id' => $roadmap?->id,
                    'source_version' => $roadmap?->version,
                    'source_snapshot' => $this->activationSnapshot(
                        $definition,
                        $capabilityKey,
                        $sourceType,
                        $roadmapDefinition
                    ),
                    'status' =>
                        TransformationCapabilityActivation::STATUS_ACTIVATED,
                    'activated_by_user_id' => $actor->id,
                    'activated_at' => now(),
                    'started_at' => null,
                    'ready_for_review_at' => null,
                    'validated_at' => null,
                    'completed_at' => null,
                    'cancelled_at' => null,
                ])->save();

                AuditService::log(
                    'transformation_capability_reactivated_free',
                    $existing,
                    $this->auditContext(
                        $company,
                        $capabilityKey,
                        $assessment,
                        $roadmap,
                        $sourceType,
                        $actor
                    )
                );
            }

            $this->needs->syncForActivation($existing);

            return $existing->fresh();
        }

        $activation = TransformationCapabilityActivation::create([
            'company_id' => $company->id,
            'diagnosis_assessment_id' => $assessment?->id,
            'capability_key' => $capabilityKey,
            'source_type' => $sourceType,
            'source_id' => $roadmap?->id,
            'source_version' => $roadmap?->version,
            'source_snapshot' => $this->activationSnapshot(
                $definition,
                $capabilityKey,
                $sourceType,
                $roadmapDefinition
            ),
            'status' =>
                TransformationCapabilityActivation::STATUS_ACTIVATED,
            'activated_by_user_id' => $actor->id,
            'activated_at' => now(),
        ]);

        AuditService::log(
            'transformation_capability_activated_free',
            $activation,
            $this->auditContext(
                $company,
                $capabilityKey,
                $assessment,
                $roadmap,
                $sourceType,
                $actor
            )
        );

        $this->needs->syncForActivation($activation);

        return $activation->fresh();
    }

    private function activationSnapshot(
        array $definition,
        string $capabilityKey,
        string $sourceType,
        ?array $roadmapDefinition
    ): array {
        return [
            'catalog' => [
                'capability_key' =>
                    $definition['capability_key'] ?? $capabilityKey,
                'title' => $definition['title'] ?? $capabilityKey,
                'kind' => 'professional_service',
                'category' => $definition['category'] ?? null,
                'purpose' => $definition['purpose'] ?? null,
                'includes' => array_values(
                    $definition['includes'] ?? []
                ),
                'requires_lauda_review' => (bool) (
                    $definition['requires_lauda_review'] ?? false
                ),
                'service_key' => null,
                'subscription_candidate' => false,
            ],
            'activation_origin' => [
                'type' => $sourceType,
                'manual' =>
                    $sourceType
                        === TransformationCapabilityActivation::SOURCE_MANUAL,
            ],
            'roadmap' => $roadmapDefinition ?? [],
            'recommendation_context' => [
                'recommended' => (bool) (
                    $roadmapDefinition['recommended'] ?? false
                ),
                'basis' =>
                    $roadmapDefinition['recommendation_basis'] ?? null,
            ],
            'free_activation_contract' => [
                'free' => true,
                'commercial_acceptance' => false,
                'requires_modality' => false,
                'requires_payment' => false,
                'creates_order' => false,
                'creates_invoice' => false,
                'creates_payment' => false,
                'creates_subscription' => false,
                'creates_subscription_item' => false,
                'creates_go_live' => false,
            ],
        ];
    }

    private function auditContext(
        Company $company,
        string $capabilityKey,
        ?DiagnosisAssessment $assessment,
        ?DiagnosisDetailedRoadmap $roadmap,
        string $sourceType,
        User $actor
    ): array {
        return [
            'company_id' => $company->id,
            'assessment_id' => $assessment?->id,
            'capability_key' => $capabilityKey,
            'source_type' => $sourceType,
            'source_id' => $roadmap?->id,
            'source_version' => $roadmap?->version,
            'actor_user_id' => $actor->id,
            'commercial_acceptance' => false,
            'subscription_created' => false,
            'subscription_item_created' => false,
        ];
    }

    public function start(
        TransformationCapabilityActivation $activation,
        User $actor
    ): TransformationCapabilityActivation {
        return $this->transition(
            $activation,
            $actor,
            TransformationCapabilityActivation::STATUS_IN_PROGRESS,
            [
                TransformationCapabilityActivation::STATUS_ACTIVATED,
            ],
            'started_at',
            'transformation_capability_started'
        );
    }

    public function markReadyForReview(
        TransformationCapabilityActivation $activation,
        User $actor
    ): TransformationCapabilityActivation {
        return $this->transition(
            $activation,
            $actor,
            TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW,
            [
                TransformationCapabilityActivation::STATUS_IN_PROGRESS,
            ],
            'ready_for_review_at',
            'transformation_capability_ready_for_review'
        );
    }

    public function validate(
        TransformationCapabilityActivation $activation,
        User $actor
    ): TransformationCapabilityActivation {
        return $this->transition(
            $activation,
            $actor,
            TransformationCapabilityActivation::STATUS_VALIDATED,
            [
                TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW,
            ],
            'validated_at',
            'transformation_capability_validated'
        );
    }

    public function complete(
        TransformationCapabilityActivation $activation,
        User $actor
    ): TransformationCapabilityActivation {
        return $this->transition(
            $activation,
            $actor,
            TransformationCapabilityActivation::STATUS_COMPLETED,
            [
                TransformationCapabilityActivation::STATUS_VALIDATED,
            ],
            'completed_at',
            'transformation_capability_completed'
        );
    }

    public function cancel(
        TransformationCapabilityActivation $activation,
        User $actor
    ): TransformationCapabilityActivation {
        return $this->transition(
            $activation,
            $actor,
            TransformationCapabilityActivation::STATUS_CANCELLED,
            [
                TransformationCapabilityActivation::STATUS_ACTIVATED,
                TransformationCapabilityActivation::STATUS_IN_PROGRESS,
                TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW,
            ],
            'cancelled_at',
            'transformation_capability_cancelled'
        );
    }

    private function transition(
        TransformationCapabilityActivation $activation,
        User $actor,
        string $targetStatus,
        array $allowedFrom,
        string $timestampColumn,
        string $auditEvent
    ): TransformationCapabilityActivation {
        return DB::transaction(function () use (
            $activation,
            $actor,
            $targetStatus,
            $allowedFrom,
            $timestampColumn,
            $auditEvent
        ): TransformationCapabilityActivation {
            $locked = TransformationCapabilityActivation::query()
                ->whereKey($activation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $targetStatus) {
                return $locked->fresh();
            }

            if (! in_array($locked->status, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'capability' => [
                        'La capacidad no puede pasar de '
                        .$locked->status
                        .' a '
                        .$targetStatus
                        .'.',
                    ],
                ]);
            }

            $locked->forceFill([
                'status' => $targetStatus,
                $timestampColumn => now(),
            ])->save();

            AuditService::log(
                $auditEvent,
                $locked,
                [
                    'company_id' => $locked->company_id,
                    'assessment_id' =>
                        $locked->diagnosis_assessment_id,
                    'capability_key' => $locked->capability_key,
                    'actor_user_id' => $actor->id,
                    'status' => $targetStatus,
                    'commercial_acceptance' => false,
                ]
            );

            return $locked->fresh();
        }, 3);
    }

    private function professionalDefinition(
        string $capabilityKey
    ): array {
        $definition = TransformationProfessionalCapabilityCatalog::get(
            $capabilityKey
        );

        if (
            ! $definition
            || ($definition['kind'] ?? null) !== 'professional_service'
            || ($definition['service_key'] ?? null) !== null
            || ($definition['subscription_candidate'] ?? true) !== false
        ) {
            throw ValidationException::withMessages([
                'capability' => [
                    'Solo una capacidad profesional no recurrente puede activarse mediante este flujo gratuito.',
                ],
            ]);
        }

        return $definition;
    }

    private function assertAssessmentCompany(
        DiagnosisAssessment $assessment,
        Company $company
    ): void {
        if (
            (int) ($assessment->organization_id ?? 0)
                === (int) $company->id
        ) {
            return;
        }

        $historicalCompanyLink = DiagnosisAccessRequest::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('meta->company_id', $company->id)
            ->exists();

        if ($historicalCompanyLink) {
            return;
        }

        throw ValidationException::withMessages([
            'assessment' => [
                'El Diagnóstico 360 no pertenece a la empresa indicada.',
            ],
        ]);
    }

    private function assertPublishedRoadmap(
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmap $roadmap
    ): void {
        if (
            (int) $roadmap->diagnosis_assessment_id
                !== (int) $assessment->id
            || ! $roadmap->isPublished()
        ) {
            throw ValidationException::withMessages([
                'roadmap' => [
                    'La activación gratuita requiere un Roadmap Detallado publicado del mismo diagnóstico.',
                ],
            ]);
        }
    }

    private function roadmapDefinition(
        DiagnosisDetailedRoadmap $roadmap,
        string $capabilityKey
    ): array {
        $content = is_array($roadmap->roadmap)
            ? $roadmap->roadmap
            : [];

        $transformation = is_array(
            $content['transformation_capabilities'] ?? null
        )
            ? $content['transformation_capabilities']
            : [];

        $definition = $transformation[$capabilityKey] ?? null;

        if (! is_array($definition)) {
            throw ValidationException::withMessages([
                'capability' => [
                    'La capacidad no está definida en el Roadmap publicado.',
                ],
            ]);
        }

        return $definition;
    }
}
