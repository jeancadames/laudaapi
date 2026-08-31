<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityNeed;
use App\Models\TransformationCapabilityNeedEvaluation;
use Illuminate\Validation\ValidationException;

final class BrandingIdentityEvaluationSummaryGenerator
{
    public const GENERATION_MODE =
        'confirmed_area_evaluations_v1';

    public function generate(
        TransformationCapabilityActivation $activation
    ): array {
        if (
            $activation->capability_key
            !== 'branding_identity'
        ) {
            throw ValidationException::withMessages([
                'branding' => [
                    'La síntesis automática solo está disponible para Branding e Identidad Digital.',
                ],
            ]);
        }

        $needs =
            $activation
                ->needs()
                ->with(
                    'evaluation'
                )
                ->get();

        if ($needs->isEmpty()) {
            throw ValidationException::withMessages([
                'summary' => [
                    'No existen áreas de Branding para sintetizar.',
                ],
            ]);
        }

        $notEvaluated =
            $needs->filter(
                fn (
                    TransformationCapabilityNeed $need
                ): bool =>
                    $need->evaluation?->status
                        !== TransformationCapabilityNeedEvaluation::STATUS_EVALUATED
            );

        if ($notEvaluated->isNotEmpty()) {
            throw ValidationException::withMessages([
                'summary' => [
                    'La síntesis solo puede generarse cuando todas las áreas tienen una evaluación profesional confirmada.',
                ],
            ]);
        }

        /*
         * Fuente única:
         * decisiones HUMANAS confirmadas.
         *
         * Los campos suggested_* no participan.
         */
        $confirmed =
            $needs
                ->map(
                    fn (
                        TransformationCapabilityNeed $need
                    ): array => [
                        'need_id' =>
                            (int) $need->id,

                        'sequence' =>
                            (int) $need->sequence,

                        'need_key' =>
                            (string) $need->need_key,

                        'title' =>
                            (string) $need->title,

                        'result' =>
                            (string) $need
                                ->evaluation
                                ->result,

                        'findings' =>
                            $need
                                ->evaluation
                                ->findings,

                        'recommendation' =>
                            $need
                                ->evaluation
                                ->recommendation,

                        'priority' =>
                            $need
                                ->evaluation
                                ->priority,

                        'evaluated_by_user_id' =>
                            $need
                                ->evaluation
                                ->evaluated_by_user_id,

                        'evaluated_at' =>
                            $need
                                ->evaluation
                                ->evaluated_at
                                ?->toISOString(),
                    ]
                )
                ->values();

        $attention =
            $confirmed
                ->filter(
                    fn (array $area): bool =>
                        $area['result']
                            === TransformationCapabilityNeedEvaluation::RESULT_REQUIRES_ATTENTION
                )
                ->values();

        $adequate =
            $confirmed
                ->filter(
                    fn (array $area): bool =>
                        $area['result']
                            === TransformationCapabilityNeedEvaluation::RESULT_ADEQUATE
                )
                ->values();

        $notApplicable =
            $confirmed
                ->filter(
                    fn (array $area): bool =>
                        $area['result']
                            === TransformationCapabilityNeedEvaluation::RESULT_NOT_APPLICABLE
                )
                ->values();

        $priorityOrder =
            $attention
                ->sort(
                    function (
                        array $a,
                        array $b
                    ): int {
                        $priorityCompare =
                            $this->priorityRank(
                                $a['priority']
                            )
                            <=>
                            $this->priorityRank(
                                $b['priority']
                            );

                        if ($priorityCompare !== 0) {
                            return $priorityCompare;
                        }

                        return
                            $a['sequence']
                            <=>
                            $b['sequence'];
                    }
                )
                ->values()
                ->map(
                    fn (array $area): array => [
                        'need_key' =>
                            $area['need_key'],

                        'title' =>
                            $area['title'],

                        'priority' =>
                            $area['priority'],

                        'recommendation' =>
                            $area['recommendation'],
                    ]
                )
                ->all();

        $attentionByKey =
            $attention
                ->keyBy(
                    'need_key'
                );

        $dependencies =
            $this->dependencies(
                $attentionByKey->all()
            );

        return [
            'executive_summary' =>
                $this->executiveSummary(
                    $confirmed->count(),
                    $attention->count(),
                    $adequate->count(),
                    $notApplicable->count(),
                    $priorityOrder
                ),

            'counts' => [
                'total' =>
                    $confirmed->count(),

                'requires_attention' =>
                    $attention->count(),

                'adequate' =>
                    $adequate->count(),

                'not_applicable' =>
                    $notApplicable->count(),
            ],

            'priority_order' =>
                $priorityOrder,

            'dependencies' =>
                $dependencies,

            'overall_recommendation' =>
                $this->overallRecommendation(
                    $attention->all()
                ),

            'confirmed_areas' =>
                $confirmed->all(),

            'generation_context' => [
                'generation_mode' =>
                    self::GENERATION_MODE,

                'activation_id' =>
                    (int) $activation->id,

                'company_id' =>
                    (int) $activation->company_id,

                'human_evaluations_only' =>
                    true,

                'evaluated_need_ids' =>
                    $confirmed
                        ->pluck(
                            'need_id'
                        )
                        ->all(),

                'area_count' =>
                    $confirmed->count(),
            ],
        ];
    }

    private function executiveSummary(
        int $total,
        int $attention,
        int $adequate,
        int $notApplicable,
        array $priorityOrder
    ): string {
        if ($attention === 0) {
            return
                'La evaluación profesional revisó '
                .$total
                .' áreas de Branding e Identidad Digital y no confirmó áreas que requieran intervención en este momento. '
                .$adequate
                .' fueron consideradas adecuadas y '
                .$notApplicable
                .' no aplican al contexto actual.';
        }

        $priorityTitles =
            collect(
                $priorityOrder
            )
                ->take(3)
                ->pluck(
                    'title'
                )
                ->filter()
                ->implode(
                    ', '
                );

        $summary =
            'La evaluación profesional revisó '
            .$total
            .' áreas de Branding e Identidad Digital y confirmó '
            .$attention
            .' área(s) que requieren atención. '
            .$adequate
            .' fueron consideradas adecuadas y '
            .$notApplicable
            .' no aplican al contexto actual.';

        if ($priorityTitles !== '') {
            $summary .=
                ' Las prioridades principales son: '
                .$priorityTitles
                .'.';
        }

        return $summary;
    }

    private function overallRecommendation(
        array $attention
    ): string {
        if ($attention === []) {
            return
                'Mantener los lineamientos actuales y revisar nuevamente estas áreas cuando cambien el posicionamiento, la identidad o los principales puntos de contacto de la marca.';
        }

        $recommendations =
            collect(
                $attention
            )
                ->pluck(
                    'recommendation'
                )
                ->filter(
                    fn ($value): bool =>
                        is_string($value)
                        && trim($value) !== ''
                )
                ->map(
                    fn (string $value): string =>
                        trim($value)
                )
                ->unique()
                ->values();

        if ($recommendations->isEmpty()) {
            return
                'Atender las áreas confirmadas según su prioridad y documentar el alcance específico antes de iniciar cualquier trabajo posterior.';
        }

        return
            $recommendations
                ->map(
                    fn (
                        string $recommendation,
                        int $index
                    ): string =>
                        ($index + 1)
                        .'. '
                        .$recommendation
                )
                ->implode(
                    "\n"
                );
    }

    private function dependencies(
        array $attentionByKey
    ): array {
        $rules = [
            [
                'before' =>
                    'positioning_refinement',

                'after' =>
                    'visual_identity_update',

                'reason' =>
                    'El posicionamiento debe orientar las decisiones de identidad visual.',
            ],
            [
                'before' =>
                    'visual_identity_update',

                'after' =>
                    'brand_kit',

                'reason' =>
                    'El Brand Kit debe consolidar una identidad visual previamente definida.',
            ],
            [
                'before' =>
                    'brand_kit',

                'after' =>
                    'social_normalization',

                'reason' =>
                    'La normalización de redes debe apoyarse en lineamientos de marca consistentes.',
            ],
            [
                'before' =>
                    'brand_kit',

                'after' =>
                    'commercial_documents',

                'reason' =>
                    'Los documentos comerciales deben aplicar los lineamientos de marca aprobados.',
            ],
            [
                'before' =>
                    'brand_kit',

                'after' =>
                    'web_application',

                'reason' =>
                    'La presencia web debe aplicar los lineamientos de identidad aprobados.',
            ],
        ];

        $result = [];

        foreach ($rules as $rule) {
            $before =
                $attentionByKey[
                    $rule['before']
                ]
                ?? null;

            $after =
                $attentionByKey[
                    $rule['after']
                ]
                ?? null;

            if (! $before || ! $after) {
                continue;
            }

            $result[] = [
                'before_key' =>
                    $rule['before'],

                'before_title' =>
                    $before['title'],

                'after_key' =>
                    $rule['after'],

                'after_title' =>
                    $after['title'],

                'reason' =>
                    $rule['reason'],
            ];
        }

        return $result;
    }

    private function priorityRank(
        ?string $priority
    ): int {
        return match ($priority) {
            TransformationCapabilityNeedEvaluation::PRIORITY_HIGH =>
                1,

            TransformationCapabilityNeedEvaluation::PRIORITY_MEDIUM =>
                2,

            TransformationCapabilityNeedEvaluation::PRIORITY_LOW =>
                3,

            default =>
                4,
        };
    }
}
