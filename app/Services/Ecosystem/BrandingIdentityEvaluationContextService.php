<?php

namespace App\Services\Ecosystem;

use App\Models\TransformationCapabilityActivation;

final class BrandingIdentityEvaluationContextService
{
    public const GENERATION_MODE =
        'deterministic_context_v1';

    public function __construct(
        private readonly BrandingIdentityPlanContextService $planContext
    ) {
    }

    public function forActivation(
        TransformationCapabilityActivation $activation
    ): array {
        $activation->loadMissing([
            'company',
            'assessment',
        ]);

        $assessment =
            $activation->assessment;

        $snapshot =
            is_array($activation->source_snapshot)
                ? $activation->source_snapshot
                : [];

        $roadmap =
            is_array($snapshot['roadmap'] ?? null)
                ? $snapshot['roadmap']
                : [];

        $plan =
            $this->planContext->forActivation(
                $activation
            );

        $sources = [
            'Perfil de empresa',
        ];

        if ($assessment) {
            $sources[] =
                'Diagnóstico 360';
        }

        if ($roadmap !== []) {
            $sources[] =
                'Contexto de Roadmap';
        }

        if (
            (bool) (
                $plan['available']
                ?? false
            )
        ) {
            $sources[] =
                'Plan de Implementación';
        }

        return [
            'generation_mode' =>
                self::GENERATION_MODE,

            'company' => [
                'id' =>
                    (int) $activation->company_id,

                'name' =>
                    $activation->company?->name,

                'slug' =>
                    $activation->company?->slug,
            ],

            'assessment' =>
                $assessment
                    ? [
                        'id' =>
                            (int) $assessment->id,

                        'organization_name' =>
                            $assessment->organization_name,

                        'status' =>
                            $assessment->status,

                        'review_summary' =>
                            $assessment->review_summary,

                        'review_priorities' =>
                            is_array(
                                $assessment
                                    ->review_priorities
                            )
                                ? $assessment
                                    ->review_priorities
                                : [],

                        'business_activity_type' =>
                            $assessment
                                ->business_activity_type,

                        'business_sector' =>
                            $assessment
                                ->business_sector,

                        'customer_market' =>
                            $assessment
                                ->customer_market,

                        'sales_channels' =>
                            is_array(
                                $assessment
                                    ->sales_channels
                            )
                                ? $assessment
                                    ->sales_channels
                                : [],

                        'business_activity_description' =>
                            $assessment
                                ->business_activity_description,

                        'dimension_scores' =>
                            is_array(
                                $assessment
                                    ->dimension_scores
                            )
                                ? $assessment
                                    ->dimension_scores
                                : [],

                        'maturity_score' =>
                            $assessment
                                ->maturity_score,

                        'capacity_score' =>
                            $assessment
                                ->capacity_score,

                        'urgency_score' =>
                            $assessment
                                ->urgency_score,
                    ]
                    : null,

            'roadmap' =>
                $roadmap !== []
                    ? [
                        'recommended' =>
                            (bool) (
                                $roadmap[
                                    'recommended'
                                ]
                                ?? false
                            ),

                        'recommendation_basis' =>
                            $roadmap[
                                'recommendation_basis'
                            ]
                            ?? null,
                    ]
                    : null,

            'plan_context' =>
                $plan,

            'sources' =>
                array_values(
                    array_unique(
                        $sources
                    )
                ),
        ];
    }
}
