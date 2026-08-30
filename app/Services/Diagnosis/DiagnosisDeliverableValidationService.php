<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDeliverableValidation;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Models\TransformationImplementationPlan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosisDeliverableValidationService
{
    public function stateFor(Model $deliverable): array
    {
        $descriptor = $this->descriptor($deliverable);

        $validation = DiagnosisDeliverableValidation::query()
            ->where('deliverable_type', $descriptor['type'])
            ->where('deliverable_id', $descriptor['id'])
            ->first();

        $status = 'presented';

        if ($validation?->adjustment_requested_at !== null) {
            $status = 'adjustment_requested';
        } elseif ($validation?->validated_at !== null) {
            $status = 'validated';
        } elseif ($validation?->reviewed_at !== null) {
            $status = 'reviewed';
        }

        return [
            'status' => $status,
            'reviewed_at' => $validation?->reviewed_at?->toISOString(),
            'validated_at' => $validation?->validated_at?->toISOString(),
            'adjustment_requested_at' =>
                $validation?->adjustment_requested_at?->toISOString(),
            'adjustment_note' => $validation?->adjustment_note,
        ];
    }


    /**
     * Estado administrativo de cierre de la versión activa de cada entregable.
     * No crea validaciones ni altera estados.
     */
    public function closureForAssessment(
        DiagnosisAssessment $assessment
    ): array {
        $report = DiagnosisExpandedReport::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->orderByDesc('version')
            ->first();

        $roadmap = DiagnosisDetailedRoadmap::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->orderByDesc('version')
            ->first();

        $plan = TransformationImplementationPlan::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->orderByDesc('version')
            ->first();

        $entries = [
            'expanded_report' => $this->closureEntry(
                DiagnosisDeliverableValidation::TYPE_EXPANDED_REPORT,
                $report?->id,
                $report?->version
            ),
            'detailed_roadmap' => $this->closureEntry(
                DiagnosisDeliverableValidation::TYPE_DETAILED_ROADMAP,
                $roadmap?->id,
                $roadmap?->version
            ),
            'implementation_plan' => $this->closureEntry(
                DiagnosisDeliverableValidation::TYPE_IMPLEMENTATION_PLAN,
                $plan?->id,
                $plan?->version
            ),
        ];

        $allValidated = collect($entries)->every(
            fn (array $entry): bool =>
                $entry['deliverable_id'] !== null
                && $entry['validated'] === true
        );

        $closedAt = collect($entries)
            ->pluck('validated_at')
            ->filter()
            ->sort()
            ->last();

        return [
            ...$entries,
            'all_validated' => $allValidated,
            'closed_at' => $allValidated ? $closedAt : null,
        ];
    }

    public function assertAssessmentOpenForPublication(
        DiagnosisAssessment $assessment
    ): void {
        if ($this->closureForAssessment($assessment)['all_validated']) {
            throw ValidationException::withMessages([
                'assessment' => [
                    'El ciclo documental fue validado por el tenant y está cerrado. Para cambios, crea una nueva versión del entregable correspondiente.',
                ],
            ]);
        }
    }

    public function assertNotValidated(Model $deliverable): void
    {
        $identity = $this->validationIdentity($deliverable);

        $validated = DiagnosisDeliverableValidation::query()
            ->where('deliverable_type', $identity['type'])
            ->where('deliverable_id', $identity['id'])
            ->whereNotNull('validated_at')
            ->exists();

        if ($validated) {
            throw ValidationException::withMessages([
                'deliverable' => [
                    'Esta versión fue validada por el tenant y quedó cerrada. Crea una nueva versión para realizar cambios.',
                ],
            ]);
        }
    }

    public function markReviewed(
        Model $deliverable,
        User $user
    ): DiagnosisDeliverableValidation {
        return DB::transaction(function () use ($deliverable, $user) {
            $descriptor = $this->descriptor($deliverable);

            $validation = $this->lockedValidation($descriptor);

            if ($validation->reviewed_at === null) {
                $validation->forceFill([
                    'reviewed_by_user_id' => $user->id,
                    'reviewed_at' => now(),
                ])->save();

                AuditService::log(
                    'diagnosis_deliverable_reviewed_by_tenant',
                    $validation,
                    [
                        'assessment_id' => $descriptor['assessment_id'],
                        'deliverable_type' => $descriptor['type'],
                        'deliverable_id' => $descriptor['id'],
                        'deliverable_version' => $descriptor['version'],
                        'actor_user_id' => $user->id,
                    ]
                );
            }

            return $validation->fresh();
        });
    }

    public function validate(
        Model $deliverable,
        User $user
    ): DiagnosisDeliverableValidation {
        return DB::transaction(function () use ($deliverable, $user) {
            $descriptor = $this->descriptor($deliverable);

            $validation = $this->lockedValidation($descriptor);

            if ($validation->validated_at !== null) {
                return $validation->fresh();
            }

            if ($validation->adjustment_requested_at !== null) {
                throw ValidationException::withMessages([
                    'deliverable' => [
                        'Esta versión tiene un ajuste solicitado. Debe presentarse una nueva versión antes de validarla.',
                    ],
                ]);
            }

            $validation->forceFill([
                'reviewed_by_user_id' =>
                    $validation->reviewed_by_user_id ?? $user->id,
                'reviewed_at' =>
                    $validation->reviewed_at ?? now(),
                'validated_by_user_id' => $user->id,
                'validated_at' => now(),
            ])->save();

            AuditService::log(
                'diagnosis_deliverable_validated_by_tenant',
                $validation,
                [
                    'assessment_id' => $descriptor['assessment_id'],
                    'deliverable_type' => $descriptor['type'],
                    'deliverable_id' => $descriptor['id'],
                    'deliverable_version' => $descriptor['version'],
                    'actor_user_id' => $user->id,
                    'commercial_acceptance' => false,
                ]
            );

            return $validation->fresh();
        });
    }

    public function requestAdjustment(
        Model $deliverable,
        User $user,
        string $note
    ): DiagnosisDeliverableValidation {
        $note = trim($note);

        if (mb_strlen($note) < 10) {
            throw ValidationException::withMessages([
                'adjustment_note' => [
                    'Describe el ajuste solicitado con al menos 10 caracteres.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $deliverable,
            $user,
            $note
        ) {
            $descriptor = $this->descriptor($deliverable);

            $validation = $this->lockedValidation($descriptor);

            if ($validation->validated_at !== null) {
                throw ValidationException::withMessages([
                    'deliverable' => [
                        'Una versión ya validada no puede marcarse posteriormente como pendiente de ajuste.',
                    ],
                ]);
            }

            $validation->forceFill([
                'reviewed_by_user_id' =>
                    $validation->reviewed_by_user_id ?? $user->id,
                'reviewed_at' =>
                    $validation->reviewed_at ?? now(),
                'adjustment_requested_by_user_id' => $user->id,
                'adjustment_requested_at' => now(),
                'adjustment_note' => $note,
            ])->save();

            AuditService::log(
                'diagnosis_deliverable_adjustment_requested',
                $validation,
                [
                    'assessment_id' => $descriptor['assessment_id'],
                    'deliverable_type' => $descriptor['type'],
                    'deliverable_id' => $descriptor['id'],
                    'deliverable_version' => $descriptor['version'],
                    'actor_user_id' => $user->id,
                ]
            );

            return $validation->fresh();
        });
    }

    private function closureEntry(
        string $type,
        ?int $deliverableId,
        mixed $version
    ): array {
        $validation = $deliverableId
            ? DiagnosisDeliverableValidation::query()
                ->where('deliverable_type', $type)
                ->where('deliverable_id', $deliverableId)
                ->first()
            : null;

        return [
            'deliverable_type' => $type,
            'deliverable_id' => $deliverableId,
            'version' => $version !== null ? (int) $version : null,
            'reviewed' => $validation?->reviewed_at !== null,
            'validated' => $validation?->validated_at !== null,
            'adjustment_requested' =>
                $validation?->adjustment_requested_at !== null,
            'reviewed_at' => $validation?->reviewed_at?->toISOString(),
            'validated_at' => $validation?->validated_at?->toISOString(),
            'adjustment_requested_at' =>
                $validation?->adjustment_requested_at?->toISOString(),
        ];
    }

    private function validationIdentity(Model $deliverable): array
    {
        if ($deliverable instanceof DiagnosisExpandedReport) {
            return [
                'type' => DiagnosisDeliverableValidation::TYPE_EXPANDED_REPORT,
                'id' => $deliverable->id,
            ];
        }

        if ($deliverable instanceof DiagnosisDetailedRoadmap) {
            return [
                'type' => DiagnosisDeliverableValidation::TYPE_DETAILED_ROADMAP,
                'id' => $deliverable->id,
            ];
        }

        if ($deliverable instanceof TransformationImplementationPlan) {
            return [
                'type' => DiagnosisDeliverableValidation::TYPE_IMPLEMENTATION_PLAN,
                'id' => $deliverable->id,
            ];
        }

        throw ValidationException::withMessages([
            'deliverable' => [
                'El tipo de entregable no admite cierre documental.',
            ],
        ]);
    }

    private function lockedValidation(array $descriptor): DiagnosisDeliverableValidation
    {
        $existing = DiagnosisDeliverableValidation::query()
            ->where('deliverable_type', $descriptor['type'])
            ->where('deliverable_id', $descriptor['id'])
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        return DiagnosisDeliverableValidation::create([
            'diagnosis_assessment_id' => $descriptor['assessment_id'],
            'deliverable_type' => $descriptor['type'],
            'deliverable_id' => $descriptor['id'],
            'deliverable_version' => $descriptor['version'],
        ]);
    }

    private function descriptor(Model $deliverable): array
    {
        if ($deliverable instanceof DiagnosisExpandedReport) {
            if (! $deliverable->isPublished()) {
                throw ValidationException::withMessages([
                    'deliverable' => [
                        'El Informe Ampliado debe estar publicado antes de revisarse o validarse.',
                    ],
                ]);
            }

            return [
                'type' => DiagnosisDeliverableValidation::TYPE_EXPANDED_REPORT,
                'id' => $deliverable->id,
                'version' => $deliverable->version,
                'assessment_id' => $deliverable->diagnosis_assessment_id,
            ];
        }

        if ($deliverable instanceof DiagnosisDetailedRoadmap) {
            if (! $deliverable->isPublished()) {
                throw ValidationException::withMessages([
                    'deliverable' => [
                        'El Roadmap Detallado debe estar publicado antes de revisarse o validarse.',
                    ],
                ]);
            }

            return [
                'type' => DiagnosisDeliverableValidation::TYPE_DETAILED_ROADMAP,
                'id' => $deliverable->id,
                'version' => $deliverable->version,
                'assessment_id' => $deliverable->diagnosis_assessment_id,
            ];
        }

        if ($deliverable instanceof TransformationImplementationPlan) {
            if (
                ! in_array(
                    $deliverable->status,
                    [
                        TransformationImplementationPlan::STATUS_PRESENTED,
                        TransformationImplementationPlan::STATUS_ACCEPTED,
                        TransformationImplementationPlan::STATUS_ACTIVE,
                        TransformationImplementationPlan::STATUS_COMPLETED,
                    ],
                    true
                )
                || $deliverable->presented_at === null
            ) {
                throw ValidationException::withMessages([
                    'deliverable' => [
                        'El Plan de Implementación debe estar presentado antes de revisarse o validarse.',
                    ],
                ]);
            }

            return [
                'type' => DiagnosisDeliverableValidation::TYPE_IMPLEMENTATION_PLAN,
                'id' => $deliverable->id,
                'version' => $deliverable->version,
                'assessment_id' => $deliverable->diagnosis_assessment_id,
            ];
        }

        throw ValidationException::withMessages([
            'deliverable' => [
                'El tipo de entregable no admite validación tenant.',
            ],
        ]);
    }
}
