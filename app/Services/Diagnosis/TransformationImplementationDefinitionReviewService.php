<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationDefinitionReviewService
{
    private const RESPONSIBLE_PARTIES = [
        'lauda',
        'client',
        'shared',
    ];

    private const REQUIRED_CONFIRMATIONS = [
        'scope_confirmed',
        'deliverables_confirmed',
        'dependencies_confirmed',
        'inputs_validated',
        'accesses_validated',
        'responsibilities_confirmed',
    ];

    /**
     * Guarda revisión humana.
     *
     * Nunca marca automáticamente la Definición como ready.
     */
    public function saveReview(
        TransformationImplementationDefinition $definition,
        array $data,
        User $actor
    ): TransformationImplementationDefinition {
        return DB::transaction(
            function () use (
                $definition,
                $data,
                $actor
            ): TransformationImplementationDefinition {
                $locked =
                    TransformationImplementationDefinition::query()
                        ->whereKey($definition->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertEditable(
                    $locked
                );

                $scope =
                    array_key_exists(
                        'implementation_scope',
                        $data
                    )
                        ? $this->requireArray(
                            $data[
                                'implementation_scope'
                            ],
                            'implementation_scope'
                        )
                        : (
                            $locked
                                ->implementation_scope
                            ?? []
                        );

                $deliverables =
                    array_key_exists(
                        'deliverables',
                        $data
                    )
                        ? $this->requireArray(
                            $data[
                                'deliverables'
                            ],
                            'deliverables'
                        )
                        : (
                            $locked->deliverables
                            ?? []
                        );

                $dependencies =
                    array_key_exists(
                        'dependencies',
                        $data
                    )
                        ? $this->requireArray(
                            $data[
                                'dependencies'
                            ],
                            'dependencies'
                        )
                        : (
                            $locked->dependencies
                            ?? []
                        );

                $responsibilityModel =
                    array_key_exists(
                        'responsibility_model',
                        $data
                    )
                        ? $this->normalizeResponsibilityModel(
                            $this->requireArray(
                                $data[
                                    'responsibility_model'
                                ],
                                'responsibility_model'
                            )
                        )
                        : (
                            $locked
                                ->responsibility_model
                            ?? []
                        );

                $readiness =
                    $this->reviewReadiness(
                        $locked->readiness
                            ?? [],
                        $data[
                            'readiness'
                        ] ?? []
                    );

                $locked->forceFill([
                    'implementation_scope' =>
                        $scope,

                    'deliverables' =>
                        $deliverables,

                    'dependencies' =>
                        $dependencies,

                    'responsibility_model' =>
                        $responsibilityModel,

                    'readiness' =>
                        $readiness,

                    'status' =>
                        TransformationImplementationDefinition::STATUS_UNDER_REVIEW,

                    'reviewed_by_user_id' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),

                    'ready_at' =>
                        null,

                    'updated_by_user_id' =>
                        $actor->id,
                ])->save();

                AuditService::log(
                    'transformation_implementation_definition_review_saved',
                    $locked,
                    [
                        'definition_id' =>
                            $locked->id,

                        'definition_version' =>
                            $locked->version,

                        'plan_id' =>
                            $locked
                                ->transformation_implementation_plan_id,

                        'reviewed_by_user_id' =>
                            $actor->id,

                        'definition_ready' =>
                            false,

                        'execution_started' =>
                            false,

                        'commercial_context_attached' =>
                            false,

                        'pricing_attached' =>
                            false,
                    ]
                );

                return $locked->fresh();
            },
            3
        );
    }

    /**
     * Finaliza la Definición funcional/técnica.
     *
     * STATUS_READY significa:
     * - definición revisada;
     * - alcance confirmado;
     * - insumos/accesos validados;
     * - responsabilidades confirmadas.
     *
     * NO significa que se haya iniciado ejecución.
     */
    public function markReady(
        TransformationImplementationDefinition $definition,
        User $actor
    ): TransformationImplementationDefinition {
        return DB::transaction(
            function () use (
                $definition,
                $actor
            ): TransformationImplementationDefinition {
                $locked =
                    TransformationImplementationDefinition::query()
                        ->whereKey($definition->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->status
                    !== TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                ) {
                    throw ValidationException::withMessages([
                        'definition' => [
                            'Solo una Definición en revisión puede marcarse como lista.',
                        ],
                    ]);
                }

                $this->assertReadyContent(
                    $locked
                );

                $readiness =
                    $locked->readiness
                    ?? [];

                $readiness[
                    'state'
                ] = 'ready';

                $readiness[
                    'definition_ready'
                ] = true;

                $readiness[
                    'technical_readiness'
                ] = true;

                /*
                 * Deliberadamente false.
                 *
                 * Cerrar la Definición no inicia ni autoriza
                 * automáticamente ejecución.
                 */
                $readiness[
                    'ready_for_execution'
                ] = false;

                $readiness[
                    'execution_started'
                ] = false;

                $readiness[
                    'human_review_required'
                ] = false;

                $readiness[
                    'blockers'
                ] = [];

                $locked->forceFill([
                    'status' =>
                        TransformationImplementationDefinition::STATUS_READY,

                    'readiness' =>
                        $readiness,

                    'reviewed_by_user_id' =>
                        $actor->id,

                    'reviewed_at' =>
                        $locked->reviewed_at
                        ?? now(),

                    'ready_at' =>
                        now(),

                    'updated_by_user_id' =>
                        $actor->id,
                ])->save();

                AuditService::log(
                    'transformation_implementation_definition_ready',
                    $locked,
                    [
                        'definition_id' =>
                            $locked->id,

                        'definition_version' =>
                            $locked->version,

                        'plan_id' =>
                            $locked
                                ->transformation_implementation_plan_id,

                        'technical_readiness' =>
                            true,

                        'ready_for_execution' =>
                            false,

                        'execution_started' =>
                            false,

                        'commercial_context_attached' =>
                            false,

                        'pricing_attached' =>
                            false,

                        'billing_created' =>
                            false,

                        'subscription_created' =>
                            false,

                        'actor_user_id' =>
                            $actor->id,
                    ]
                );

                return $locked->fresh();
            },
            3
        );
    }

    private function reviewReadiness(
        array $current,
        mixed $input
    ): array {
        $input =
            $this->requireArray(
                $input,
                'readiness'
            );

        $validation =
            is_array(
                $current[
                    'human_validation'
                ] ?? null
            )
                ? $current[
                    'human_validation'
                ]
                : [];

        foreach (
            self::REQUIRED_CONFIRMATIONS
            as $key
        ) {
            if (
                array_key_exists(
                    $key,
                    $input
                )
            ) {
                if (
                    ! is_bool(
                        $input[$key]
                    )
                ) {
                    throw ValidationException::withMessages([
                        "readiness.{$key}" => [
                            'La confirmación debe ser true o false.',
                        ],
                    ]);
                }

                $validation[
                    $key
                ] = $input[$key];
            } elseif (
                ! array_key_exists(
                    $key,
                    $validation
                )
            ) {
                $validation[
                    $key
                ] = null;
            }
        }

        $blockers = [];

        foreach (
            self::REQUIRED_CONFIRMATIONS
            as $key
        ) {
            if (
                ($validation[$key] ?? null)
                !== true
            ) {
                $blockers[] = [
                    'key' =>
                        $key,

                    'message' =>
                        $this->confirmationMessage(
                            $key
                        ),
                ];
            }
        }

        return array_merge(
            $current,
            [
                'state' =>
                    'under_review',

                'definition_ready' =>
                    false,

                'technical_readiness' =>
                    false,

                'ready_for_execution' =>
                    false,

                'execution_started' =>
                    false,

                'human_review_required' =>
                    true,

                'human_validation' =>
                    $validation,

                'blockers' =>
                    $blockers,
            ]
        );
    }

    private function normalizeResponsibilityModel(
        array $model
    ): array {
        $assignments =
            $model[
                'assignments'
            ] ?? [];

        if (! is_array($assignments)) {
            throw ValidationException::withMessages([
                'responsibility_model.assignments' => [
                    'Las responsabilidades deben enviarse como una lista.',
                ],
            ]);
        }

        $normalized = [];

        foreach (
            $assignments
            as $index => $assignment
        ) {
            if (! is_array($assignment)) {
                throw ValidationException::withMessages([
                    "responsibility_model.assignments.{$index}" => [
                        'La asignación de responsabilidad no es válida.',
                    ],
                ]);
            }

            $party =
                trim(
                    (string) (
                        $assignment[
                            'responsible_party'
                        ] ?? ''
                    )
                );

            if (
                ! in_array(
                    $party,
                    self::RESPONSIBLE_PARTIES,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    "responsibility_model.assignments.{$index}.responsible_party" => [
                        'El responsable debe ser lauda, client o shared.',
                    ],
                ]);
            }

            $initiativeId =
                trim(
                    (string) (
                        $assignment[
                            'initiative_id'
                        ] ?? ''
                    )
                );

            if ($initiativeId === '') {
                throw ValidationException::withMessages([
                    "responsibility_model.assignments.{$index}.initiative_id" => [
                        'La asignación requiere initiative_id.',
                    ],
                ]);
            }

            $normalized[] =
                array_merge(
                    $assignment,
                    [
                        'initiative_id' =>
                            $initiativeId,

                        'responsible_party' =>
                            $party,

                        'confirmation_status' =>
                            'confirmed',
                    ]
                );
        }

        return array_merge(
            $model,
            [
                'assignments' =>
                    $normalized,

                'unresolved' =>
                    [],

                'confirmation_required' =>
                    false,

                'party_assignment_status' =>
                    'confirmed',
            ]
        );
    }

    private function assertReadyContent(
        TransformationImplementationDefinition $definition
    ): void {
        $scope =
            $definition
                ->implementation_scope
            ?? [];

        $deliverables =
            $definition
                ->deliverables
            ?? [];

        $responsibilities =
            $definition
                ->responsibility_model
            ?? [];

        $readiness =
            $definition->readiness
            ?? [];

        if (
            ! is_array($scope)
            || empty(
                data_get(
                    $scope,
                    'phases',
                    []
                )
            )
        ) {
            throw ValidationException::withMessages([
                'implementation_scope' => [
                    'Debe existir alcance de implementación confirmado.',
                ],
            ]);
        }

        if (
            ! is_array(
                $deliverables
            )
            || $deliverables === []
        ) {
            throw ValidationException::withMessages([
                'deliverables' => [
                    'Debe existir al menos un entregable confirmado.',
                ],
            ]);
        }

        if (
            data_get(
                $responsibilities,
                'party_assignment_status'
            ) !== 'confirmed'
        ) {
            throw ValidationException::withMessages([
                'responsibility_model' => [
                    'Las responsabilidades deben estar confirmadas.',
                ],
            ]);
        }

        $validation =
            data_get(
                $readiness,
                'human_validation',
                []
            );

        foreach (
            self::REQUIRED_CONFIRMATIONS
            as $key
        ) {
            if (
                ($validation[$key] ?? null)
                !== true
            ) {
                throw ValidationException::withMessages([
                    "readiness.{$key}" => [
                        $this->confirmationMessage(
                            $key
                        ),
                    ],
                ]);
            }
        }
    }

    private function assertEditable(
        TransformationImplementationDefinition $definition
    ): void {
        if (! $definition->isEditable()) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definición ya no es editable.',
                ],
            ]);
        }
    }

    private function requireArray(
        mixed $value,
        string $field
    ): array {
        if (! is_array($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'El valor debe tener estructura válida.',
                ],
            ]);
        }

        return $value;
    }

    private function confirmationMessage(
        string $key
    ): string {
        return match ($key) {
            'scope_confirmed' =>
                'Confirmar el alcance de implementación.',

            'deliverables_confirmed' =>
                'Confirmar los entregables.',

            'dependencies_confirmed' =>
                'Confirmar las dependencias.',

            'inputs_validated' =>
                'Validar los insumos requeridos.',

            'accesses_validated' =>
                'Validar los accesos requeridos.',

            'responsibilities_confirmed' =>
                'Confirmar las responsabilidades.',

            default =>
                'Completar la validación requerida.',
        };
    }
}
