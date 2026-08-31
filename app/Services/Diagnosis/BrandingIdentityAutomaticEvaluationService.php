<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityNeed;
use App\Models\TransformationCapabilityNeedEvaluation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Ecosystem\BrandingIdentityEvaluationContextService;
use Illuminate\Validation\ValidationException;

final class BrandingIdentityAutomaticEvaluationService
{
    public function __construct(
        private readonly BrandingIdentityEvaluationContextService $context,
        private readonly BrandingIdentityEvaluationDraftGenerator $generator,
        private readonly TransformationCapabilityNeedEvaluationService $evaluations
    ) {
    }

    public function generatePending(
        TransformationCapabilityActivation $activation,
        User $actor
    ): array {
        if (
            $activation->capability_key
            !== 'branding_identity'
        ) {
            throw ValidationException::withMessages([
                'branding' => [
                    'La generación automática solo está disponible para Branding e Identidad Digital.',
                ],
            ]);
        }

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_IN_PROGRESS
        ) {
            throw ValidationException::withMessages([
                'branding' => [
                    'Los borradores automáticos solo pueden generarse mientras la evaluación está en progreso.',
                ],
            ]);
        }

        $context =
            $this->context->forActivation(
                $activation
            );

        $needs =
            $activation
                ->needs()
                ->with('evaluation')
                ->get();

        $generated = 0;
        $skippedEvaluated = 0;

        foreach ($needs as $need) {
            if (
                $need->evaluation?->status
                === TransformationCapabilityNeedEvaluation::STATUS_EVALUATED
            ) {
                $skippedEvaluated++;

                continue;
            }

            $draft =
                $this->generator->generate(
                    $need,
                    $context
                );

            $this->evaluations
                ->saveGeneratedDraft(
                    $need,
                    $actor,
                    $draft
                );

            $generated++;
        }

        AuditService::log(
            'branding_identity_automatic_drafts_generated',
            $activation,
            [
                'company_id' =>
                    $activation->company_id,

                'assessment_id' =>
                    $activation
                        ->diagnosis_assessment_id,

                'actor_user_id' =>
                    $actor->id,

                'generated_count' =>
                    $generated,

                'skipped_evaluated_count' =>
                    $skippedEvaluated,

                'generation_mode' =>
                    $context[
                        'generation_mode'
                    ]
                    ?? null,

                'sources' =>
                    $context[
                        'sources'
                    ]
                    ?? [],

                'human_decision_changed' =>
                    false,

                'activation_status_changed' =>
                    false,

                'commercial_acceptance' =>
                    false,
            ]
        );

        return [
            'generated' =>
                $generated,

            'skipped_evaluated' =>
                $skippedEvaluated,

            'sources' =>
                $context[
                    'sources'
                ]
                ?? [],
        ];
    }
}
