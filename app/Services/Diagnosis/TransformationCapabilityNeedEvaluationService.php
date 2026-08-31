<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityNeed;
use App\Models\TransformationCapabilityNeedEvaluation;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationCapabilityNeedEvaluationService
{
    public function ensureForNeed(
        TransformationCapabilityNeed $need
    ): TransformationCapabilityNeedEvaluation {
        return TransformationCapabilityNeedEvaluation::query()
            ->firstOrCreate(
                [
                    'transformation_capability_need_id' =>
                        $need->id,
                ],
                [
                    'status' =>
                        TransformationCapabilityNeedEvaluation::STATUS_PENDING,
                    'generation_version' =>
                        0,
                ]
            );
    }

    /*
     * Guarda exclusivamente sugerencias automáticas.
     *
     * Nunca establece result/evaluated_at ni toma una
     * decisión profesional en nombre de LAUDA.
     */
    public function saveGeneratedDraft(
        TransformationCapabilityNeed $need,
        User $actor,
        array $draft
    ): TransformationCapabilityNeedEvaluation {
        $suggestedResult = trim(
            (string) (
                $draft['suggested_result']
                ?? ''
            )
        );

        if (
            ! in_array(
                $suggestedResult,
                TransformationCapabilityNeedEvaluation::SUGGESTED_RESULTS,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'suggested_result' => [
                    'El resultado sugerido no es válido.',
                ],
            ]);
        }

        $suggestedPriority = $this->nullableString(
            $draft['suggested_priority']
            ?? null
        );

        if (
            $suggestedPriority !== null
            && ! in_array(
                $suggestedPriority,
                TransformationCapabilityNeedEvaluation::PRIORITIES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'suggested_priority' => [
                    'La prioridad sugerida no es válida.',
                ],
            ]);
        }

        $questions = collect(
            is_array(
                $draft['suggested_questions']
                ?? null
            )
                ? $draft['suggested_questions']
                : []
        )
            ->map(
                fn ($question) =>
                    trim((string) $question)
            )
            ->filter()
            ->values()
            ->all();

        $generationContext =
            is_array(
                $draft['generation_context']
                ?? null
            )
                ? $draft['generation_context']
                : [];

        return DB::transaction(
            function () use (
                $need,
                $actor,
                $draft,
                $suggestedResult,
                $suggestedPriority,
                $questions,
                $generationContext
            ): TransformationCapabilityNeedEvaluation {
                $evaluation =
                    TransformationCapabilityNeedEvaluation::query()
                        ->where(
                            'transformation_capability_need_id',
                            $need->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $evaluation) {
                    $evaluation =
                        TransformationCapabilityNeedEvaluation::create([
                            'transformation_capability_need_id' =>
                                $need->id,
                            'status' =>
                                TransformationCapabilityNeedEvaluation::STATUS_PENDING,
                            'generation_version' =>
                                0,
                        ]);
                }

                $nextVersion =
                    ((int) $evaluation->generation_version)
                    + 1;

                $evaluation->forceFill([
                    'status' =>
                        $evaluation->status
                            === TransformationCapabilityNeedEvaluation::STATUS_EVALUATED
                                ? TransformationCapabilityNeedEvaluation::STATUS_EVALUATED
                                : TransformationCapabilityNeedEvaluation::STATUS_DRAFT_GENERATED,

                    'suggested_result' =>
                        $suggestedResult,

                    'suggested_findings' =>
                        $this->nullableString(
                            $draft['suggested_findings']
                            ?? null
                        ),

                    'suggested_recommendation' =>
                        $this->nullableString(
                            $draft['suggested_recommendation']
                            ?? null
                        ),

                    'suggested_priority' =>
                        $suggestedPriority,

                    'suggested_questions' =>
                        $questions,

                    'generation_context' =>
                        $generationContext,

                    'generation_version' =>
                        $nextVersion,

                    'generated_at' =>
                        now(),
                ])->save();

                AuditService::log(
                    'transformation_capability_need_evaluation_draft_generated',
                    $evaluation,
                    [
                        'company_id' =>
                            $need->activation?->company_id,

                        'activation_id' =>
                            $need->transformation_capability_activation_id,

                        'need_id' =>
                            $need->id,

                        'need_key' =>
                            $need->need_key,

                        'actor_user_id' =>
                            $actor->id,

                        'suggested_result' =>
                            $suggestedResult,

                        'generation_version' =>
                            $nextVersion,

                        'human_decision_changed' =>
                            false,

                        'commercial_acceptance' =>
                            false,
                    ]
                );

                return $evaluation->fresh();
            },
            3
        );
    }

    /*
     * Decisión profesional explícita del Admin.
     */
    public function evaluate(
        TransformationCapabilityNeed $need,
        User $actor,
        array $input
    ): TransformationCapabilityNeedEvaluation {
        $result = trim(
            (string) (
                $input['result']
                ?? ''
            )
        );

        if (
            ! in_array(
                $result,
                TransformationCapabilityNeedEvaluation::RESULTS,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'result' => [
                    'El resultado de evaluación no es válido.',
                ],
            ]);
        }

        $findings = $this->nullableString(
            $input['findings']
            ?? null
        );

        if ($findings === null) {
            throw ValidationException::withMessages([
                'findings' => [
                    'Registra los hallazgos que sustentan la evaluación.',
                ],
            ]);
        }

        $recommendation = $this->nullableString(
            $input['recommendation']
            ?? null
        );

        $priority = $this->nullableString(
            $input['priority']
            ?? null
        );

        if (
            $result
            === TransformationCapabilityNeedEvaluation::RESULT_REQUIRES_ATTENTION
        ) {
            if ($recommendation === null) {
                throw ValidationException::withMessages([
                    'recommendation' => [
                        'Una necesidad identificada requiere una recomendación.',
                    ],
                ]);
            }

            if (
                $priority === null
                || ! in_array(
                    $priority,
                    TransformationCapabilityNeedEvaluation::PRIORITIES,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'priority' => [
                        'Una necesidad identificada requiere prioridad.',
                    ],
                ]);
            }
        } else {
            /*
             * Si no requiere intervención,
             * no heredamos una prioridad automática.
             */
            $priority = null;
        }

        return DB::transaction(
            function () use (
                $need,
                $actor,
                $result,
                $findings,
                $recommendation,
                $priority
            ): TransformationCapabilityNeedEvaluation {
                $evaluation =
                    TransformationCapabilityNeedEvaluation::query()
                        ->where(
                            'transformation_capability_need_id',
                            $need->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $evaluation) {
                    $evaluation =
                        TransformationCapabilityNeedEvaluation::create([
                            'transformation_capability_need_id' =>
                                $need->id,
                            'status' =>
                                TransformationCapabilityNeedEvaluation::STATUS_PENDING,
                            'generation_version' =>
                                0,
                        ]);
                }

                $evaluation->forceFill([
                    'status' =>
                        TransformationCapabilityNeedEvaluation::STATUS_EVALUATED,

                    'result' =>
                        $result,

                    'findings' =>
                        $findings,

                    'recommendation' =>
                        $recommendation,

                    'priority' =>
                        $priority,

                    'evaluated_by_user_id' =>
                        $actor->id,

                    'evaluated_at' =>
                        now(),
                ])->save();

                AuditService::log(
                    'transformation_capability_need_evaluated',
                    $evaluation,
                    [
                        'company_id' =>
                            $need->activation?->company_id,

                        'activation_id' =>
                            $need->transformation_capability_activation_id,

                        'need_id' =>
                            $need->id,

                        'need_key' =>
                            $need->need_key,

                        'actor_user_id' =>
                            $actor->id,

                        'result' =>
                            $result,

                        'priority' =>
                            $priority,

                        'commercial_acceptance' =>
                            false,
                    ]
                );

                return $evaluation->fresh();
            },
            3
        );
    }

    public function summaryForActivation(
        TransformationCapabilityActivation $activation
    ): array {
        $needs = $activation
            ->needs()
            ->with('evaluation')
            ->get();

        $evaluated = $needs->filter(
            fn (TransformationCapabilityNeed $need): bool =>
                $need->evaluation?->status
                    === TransformationCapabilityNeedEvaluation::STATUS_EVALUATED
        );

        return [
            'total' =>
                $needs->count(),

            'evaluated' =>
                $evaluated->count(),

            'pending' =>
                $needs->count()
                    - $evaluated->count(),

            'requires_attention' =>
                $evaluated->filter(
                    fn (TransformationCapabilityNeed $need): bool =>
                        $need->evaluation?->result
                            === TransformationCapabilityNeedEvaluation::RESULT_REQUIRES_ATTENTION
                )->count(),

            'adequate' =>
                $evaluated->filter(
                    fn (TransformationCapabilityNeed $need): bool =>
                        $need->evaluation?->result
                            === TransformationCapabilityNeedEvaluation::RESULT_ADEQUATE
                )->count(),

            'not_applicable' =>
                $evaluated->filter(
                    fn (TransformationCapabilityNeed $need): bool =>
                        $need->evaluation?->result
                            === TransformationCapabilityNeedEvaluation::RESULT_NOT_APPLICABLE
                )->count(),

            'all_evaluated' =>
                $needs->isNotEmpty()
                && $evaluated->count()
                    === $needs->count(),
        ];
    }

    public function assertAllEvaluated(
        TransformationCapabilityActivation $activation
    ): void {
        $summary =
            $this->summaryForActivation(
                $activation
            );

        if (! $summary['all_evaluated']) {
            throw ValidationException::withMessages([
                'evaluation' => [
                    'Todas las áreas deben estar evaluadas antes de continuar. Pendientes: '
                    .$summary['pending']
                    .'.',
                ],
            ]);
        }
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }
}
