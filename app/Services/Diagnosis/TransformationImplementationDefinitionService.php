<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationPlan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationDefinitionService
{
    public function createOrGetDraftFromPresentedPlan(
        TransformationImplementationPlan $plan,
        User $actor
    ): TransformationImplementationDefinition {
        return DB::transaction(
            function () use (
                $plan,
                $actor
            ): TransformationImplementationDefinition {
                $locked =
                    TransformationImplementationPlan::query()
                        ->whereKey($plan->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->status
                        !== TransformationImplementationPlan::STATUS_PRESENTED
                    || $locked->presented_at === null
                ) {
                    throw ValidationException::withMessages([
                        'plan' => [
                            'La Definición de Implementación requiere un Plan de Implementación presentado.',
                        ],
                    ]);
                }

                $assessment =
                    $locked->assessment;

                if (! $assessment) {
                    throw ValidationException::withMessages([
                        'assessment' => [
                            'El Plan no tiene un Diagnóstico asociado.',
                        ],
                    ]);
                }

                $companyId =
                    (int) (
                        $assessment->organization_id
                        ?? 0
                    );

                if ($companyId <= 0) {
                    throw ValidationException::withMessages([
                        'company' => [
                            'El Diagnóstico debe estar vinculado a una empresa.',
                        ],
                    ]);
                }

                $existing =
                    TransformationImplementationDefinition::query()
                        ->where(
                            'transformation_implementation_plan_id',
                            $locked->id
                        )
                        ->whereIn(
                            'status',
                            [
                                TransformationImplementationDefinition::STATUS_DRAFT,
                                TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
                            ]
                        )
                        ->orderByDesc('version')
                        ->first();

                if ($existing) {
                    return $existing;
                }

                $nextVersion =
                    (
                        (int)
                        TransformationImplementationDefinition::query()
                            ->where(
                                'transformation_implementation_plan_id',
                                $locked->id
                            )
                            ->max('version')
                    ) + 1;

                $locked->loadMissing([
                    'phases.capabilities',
                ]);

                $phases =
                    $locked->phases
                        ->sortBy('sequence')
                        ->values()
                        ->map(
                            function ($phase): array {
                                return [
                                    'id' =>
                                        $phase->id,

                                    'sequence' =>
                                        $phase->sequence,

                                    'name' =>
                                        $phase->name,

                                    'objective' =>
                                        $phase->objective
                                        ?? null,

                                    'horizon' =>
                                        $phase->horizon
                                        ?? null,

                                    'source_snapshot' =>
                                        $phase->source_snapshot
                                        ?? [],

                                    'capabilities' =>
                                        $phase->capabilities
                                            ->sortBy('sequence')
                                            ->values()
                                            ->map(
                                                fn ($capability): array => [
                                                    'sequence' =>
                                                        $capability->sequence,

                                                    'capability_key' =>
                                                        $capability->capability_key,

                                                    'capability_label' =>
                                                        $capability->capability_label,

                                                    'source_snapshot' =>
                                                        $capability->source_snapshot
                                                        ?? [],
                                                ]
                                            )
                                            ->all(),
                                ];
                            }
                        )
                        ->all();

                $definition =
                    TransformationImplementationDefinition::create([
                        'transformation_implementation_plan_id' =>
                            $locked->id,

                        'diagnosis_assessment_id' =>
                            $assessment->id,

                        'company_id' =>
                            $companyId,

                        'version' =>
                            $nextVersion,

                        'status' =>
                            TransformationImplementationDefinition::STATUS_DRAFT,

                        'source_snapshot' => [
                            'source_type' =>
                                'presented_implementation_plan',

                            'plan_id' =>
                                $locked->id,

                            'plan_version' =>
                                $locked->version,

                            'plan_presented_at' =>
                                $locked
                                    ->presented_at
                                    ?->toISOString(),

                            'diagnosis_assessment_id' =>
                                $assessment->id,

                            'company_id' =>
                                $companyId,

                            'phases' =>
                                $phases,
                        ],

                        'implementation_scope' =>
                            null,

                        'deliverables' =>
                            null,

                        'dependencies' =>
                            null,

                        'responsibility_model' =>
                            null,

                        'readiness' =>
                            null,

                        'created_by_user_id' =>
                            $actor->id,

                        'updated_by_user_id' =>
                            $actor->id,
                    ]);

                AuditService::log(
                    'transformation_implementation_definition_created',
                    $definition,
                    [
                        'plan_id' =>
                            $locked->id,

                        'plan_version' =>
                            $locked->version,

                        'definition_version' =>
                            $definition->version,

                        'assessment_id' =>
                            $assessment->id,

                        'company_id' =>
                            $companyId,

                        'commercial_context_attached' =>
                            false,

                        'pricing_attached' =>
                            false,

                        'billing_created' =>
                            false,

                        'subscription_created' =>
                            false,

                        'execution_created' =>
                            false,

                        'actor_user_id' =>
                            $actor->id,
                    ]
                );

                return $definition->fresh();
            },
            3
        );
    }
}
