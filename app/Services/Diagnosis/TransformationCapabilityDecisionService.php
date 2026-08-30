<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityDecision;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationCapabilityDecisionService
{
    public function acceptFromRoadmap(
        Company $company,
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmap $roadmap,
        string $capabilityKey,
        User $actor
    ): TransformationCapabilityDecision {
        return $this->recordFromRoadmap(
            $company,
            $assessment,
            $roadmap,
            $capabilityKey,
            $actor,
            TransformationCapabilityDecision::DECISION_ACCEPTED
        );
    }

    public function declineFromRoadmap(
        Company $company,
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmap $roadmap,
        string $capabilityKey,
        User $actor
    ): TransformationCapabilityDecision {
        $definition = $this->assertRoadmapContext(
            $company,
            $assessment,
            $roadmap,
            $capabilityKey
        );

        if (($definition['recommended'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'capability' => [
                    'Solo una capacidad recomendada por el Diagnóstico puede marcarse como “Ahora no”.',
                ],
            ]);
        }

        $active = TransformationCapabilityActivation::query()
            ->where('company_id', $company->id)
            ->where('capability_key', trim($capabilityKey))
            ->where(
                'status',
                '!=',
                TransformationCapabilityActivation::STATUS_CANCELLED
            )
            ->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'capability' => [
                    'Branding ya está activo para esta empresa.',
                ],
            ]);
        }

        return $this->record(
            $company,
            $assessment,
            $roadmap,
            trim($capabilityKey),
            $definition,
            $actor,
            TransformationCapabilityDecision::DECISION_DECLINED
        );
    }

    private function recordFromRoadmap(
        Company $company,
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmap $roadmap,
        string $capabilityKey,
        User $actor,
        string $decision
    ): TransformationCapabilityDecision {
        $capabilityKey = trim($capabilityKey);

        $definition = $this->assertRoadmapContext(
            $company,
            $assessment,
            $roadmap,
            $capabilityKey
        );

        return $this->record(
            $company,
            $assessment,
            $roadmap,
            $capabilityKey,
            $definition,
            $actor,
            $decision
        );
    }

    private function record(
        Company $company,
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmap $roadmap,
        string $capabilityKey,
        array $definition,
        User $actor,
        string $decision
    ): TransformationCapabilityDecision {
        return DB::transaction(function () use (
            $company,
            $assessment,
            $roadmap,
            $capabilityKey,
            $definition,
            $actor,
            $decision
        ): TransformationCapabilityDecision {
            $record = TransformationCapabilityDecision::query()
                ->where('diagnosis_assessment_id', $assessment->id)
                ->where('capability_key', $capabilityKey)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                $record = new TransformationCapabilityDecision();
            }

            $recommended =
                ($definition['recommended'] ?? false) === true;

            $recommendationStatus = $recommended
                ? TransformationCapabilityDecision::RECOMMENDATION_RECOMMENDED
                : TransformationCapabilityDecision::RECOMMENDATION_NOT_RECOMMENDED;

            if (
                $record->exists
                && (int) $record->company_id === (int) $company->id
                && $record->recommendation_status === $recommendationStatus
                && $record->decision === $decision
                && $record->source_type
                    === TransformationCapabilityActivation::SOURCE_DETAILED_ROADMAP
                && (int) $record->source_id === (int) $roadmap->id
                && (int) ($record->source_version ?? 0)
                    === (int) ($roadmap->version ?? 0)
            ) {
                return $record->fresh();
            }

            $record->forceFill([
                'company_id' => $company->id,
                'diagnosis_assessment_id' => $assessment->id,
                'capability_key' => $capabilityKey,
                'recommendation_status' => $recommendationStatus,
                'decision' => $decision,
                'source_type' =>
                    TransformationCapabilityActivation::SOURCE_DETAILED_ROADMAP,
                'source_id' => $roadmap->id,
                'source_version' => $roadmap->version,
                'source_snapshot' => [
                    'recommended' => $recommended,
                    'recommendation_basis' =>
                        $definition['recommendation_basis'] ?? null,
                    'roadmap_title' =>
                        $definition['title'] ?? null,
                ],
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ])->save();

            AuditService::log(
                'transformation_capability_tenant_decision',
                $record,
                [
                    'company_id' => $company->id,
                    'assessment_id' => $assessment->id,
                    'capability_key' => $capabilityKey,
                    'recommendation_status' =>
                        $record->recommendation_status,
                    'decision' => $decision,
                    'actor_user_id' => $actor->id,
                    'commercial_acceptance' => false,
                ]
            );

            return $record->fresh();
        }, 3);
    }

    private function assertRoadmapContext(
        Company $company,
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmap $roadmap,
        string $capabilityKey
    ): array {
        $belongsToCompany =
            (int) ($assessment->organization_id ?? 0)
                === (int) $company->id;

        if (! $belongsToCompany) {
            $belongsToCompany = DiagnosisAccessRequest::query()
                ->where('diagnosis_assessment_id', $assessment->id)
                ->where('meta->company_id', $company->id)
                ->exists();
        }

        if (! $belongsToCompany) {
            throw ValidationException::withMessages([
                'assessment' => [
                    'El Diagnóstico 360 no pertenece a la empresa indicada.',
                ],
            ]);
        }

        if (
            (int) $roadmap->diagnosis_assessment_id
                !== (int) $assessment->id
            || ! $roadmap->isPublished()
        ) {
            throw ValidationException::withMessages([
                'roadmap' => [
                    'La decisión requiere un Roadmap Detallado publicado del mismo diagnóstico.',
                ],
            ]);
        }

        $definition = data_get(
            is_array($roadmap->roadmap) ? $roadmap->roadmap : [],
            'transformation_capabilities.'.trim($capabilityKey)
        );

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
