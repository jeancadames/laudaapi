<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityNeed;
use App\Models\TransformationCapabilityNeedEvaluation;
use App\Models\TransformationCapabilityEvaluationSummary;
use App\Services\Diagnosis\BrandingIdentityAutomaticEvaluationService;
use App\Services\Diagnosis\BrandingIdentityEvaluationSummaryGenerator;
use App\Services\Diagnosis\TransformationCapabilityActivationService;
use App\Services\Diagnosis\TransformationCapabilityNeedEvaluationService;
use App\Services\Diagnosis\TransformationCapabilityEvaluationSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdminBrandingEvaluationController extends Controller
{
    public function index(
        TransformationCapabilityNeedEvaluationService $evaluations
    ): Response {
        $activations =
            TransformationCapabilityActivation::query()
                ->where(
                    'capability_key',
                    'branding_identity'
                )
                ->where(
                    'status',
                    '!=',
                    TransformationCapabilityActivation::STATUS_CANCELLED
                )
                ->with([
                    'company:id,name,slug,subscriber_id',
                    'needs.evaluation',
                ])
                ->orderByRaw(
                    "CASE
                        WHEN status = 'in_progress' THEN 1
                        WHEN status = 'activated' THEN 2
                        WHEN status = 'ready_for_review' THEN 3
                        WHEN status = 'validated' THEN 4
                        WHEN status = 'completed' THEN 5
                        ELSE 6
                    END"
                )
                ->orderByDesc('activated_at')
                ->orderByDesc('id')
                ->get();

        return Inertia::render(
            'Admin/BrandingEvaluations/Index',
            [
                'evaluations' =>
                    $activations
                        ->map(
                            function (
                                TransformationCapabilityActivation $activation
                            ) use ($evaluations): array {
                                $summary =
                                    $evaluations->summaryForActivation(
                                        $activation
                                    );

                                return [
                                    'id' =>
                                        (int) $activation->id,

                                    'company' => [
                                        'id' =>
                                            (int) $activation->company_id,

                                        'name' =>
                                            $activation->company?->name
                                            ?? 'Empresa',
                                    ],

                                    'status' =>
                                        (string) $activation->status,

                                    'status_label' =>
                                        $this->activationStatusLabel(
                                            (string) $activation->status
                                        ),

                                    'activated_at' =>
                                        $activation
                                            ->activated_at
                                            ?->toISOString(),

                                    'started_at' =>
                                        $activation
                                            ->started_at
                                            ?->toISOString(),

                                    'summary' =>
                                        $summary,

                                    'url' =>
                                        route(
                                            'admin.branding_evaluations.show',
                                            $activation
                                        ),
                                ];
                            }
                        )
                        ->values(),
            ]
        );
    }

    public function show(
        TransformationCapabilityActivation $activation,
        TransformationCapabilityNeedEvaluationService $evaluations
    ): Response {
        $this->assertBrandingActivation(
            $activation
        );

        $activation->load([
            'company:id,name,slug,subscriber_id',
            'assessment:id,organization_name',
            'needs.evaluation.evaluatedBy:id,name,email',
            'evaluationSummary.reviewedBy:id,name,email',
        ]);

        $summary =
            $evaluations->summaryForActivation(
                $activation
            );

        $evaluationSummary =
            $activation->evaluationSummary;

        return Inertia::render(
            'Admin/BrandingEvaluations/Show',
            [
                'branding' => [
                    'id' =>
                        (int) $activation->id,

                    'capability_key' =>
                        (string) $activation->capability_key,

                    'company' => [
                        'id' =>
                            (int) $activation->company_id,

                        'name' =>
                            $activation->company?->name
                            ?? 'Empresa',
                    ],

                    'assessment' =>
                        $activation->assessment
                            ? [
                                'id' =>
                                    (int) $activation->assessment->id,

                                'organization_name' =>
                                    $activation
                                        ->assessment
                                        ->organization_name,
                            ]
                            : null,

                    'source_type' =>
                        $activation->source_type,

                    'status' =>
                        (string) $activation->status,

                    'status_label' =>
                        $this->activationStatusLabel(
                            (string) $activation->status
                        ),

                    'activated_at' =>
                        $activation
                            ->activated_at
                            ?->toISOString(),

                    'started_at' =>
                        $activation
                            ->started_at
                            ?->toISOString(),

                    'ready_for_review_at' =>
                        $activation
                            ->ready_for_review_at
                            ?->toISOString(),

                    'validated_at' =>
                        $activation
                            ->validated_at
                            ?->toISOString(),

                    'completed_at' =>
                        $activation
                            ->completed_at
                            ?->toISOString(),

                    'summary' =>
                        $summary,

                    'evaluation_summary' =>
                        $this->serializeEvaluationSummary(
                            $evaluationSummary
                        ),

                    'can_generate_summary' =>
                        $activation->status
                            === TransformationCapabilityActivation::STATUS_IN_PROGRESS
                        && (bool) $summary['all_evaluated'],

                    'can_edit' =>
                        $activation->status
                            === TransformationCapabilityActivation::STATUS_IN_PROGRESS,

                    'can_mark_ready' =>
                        $activation->status
                            === TransformationCapabilityActivation::STATUS_IN_PROGRESS
                        && (bool) $summary['all_evaluated']
                        && $this->summaryIsGenerated(
                            $evaluationSummary
                        ),

                    'can_review_summary' =>
                        $activation->status
                            === TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW
                        && $this->summaryIsGenerated(
                            $evaluationSummary
                        ),

                    'can_validate' =>
                        $activation->status
                            === TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW
                        && $evaluationSummary?->status
                            === TransformationCapabilityEvaluationSummary::STATUS_REVIEWED,

                    'can_complete' =>
                        $activation->status
                            === TransformationCapabilityActivation::STATUS_VALIDATED,

                    'needs' =>
                        $activation
                            ->needs
                            ->map(
                                fn (
                                    TransformationCapabilityNeed $need
                                ): array => [
                                    'id' =>
                                        (int) $need->id,

                                    'sequence' =>
                                        (int) $need->sequence,

                                    'need_key' =>
                                        (string) $need->need_key,

                                    'title' =>
                                        (string) $need->title,

                                    'description' =>
                                        $need->description,

                                    'evaluation' =>
                                        $this->serializeEvaluation(
                                            $need->evaluation
                                        ),
                                ]
                            )
                            ->values(),

                    'endpoints' => [
                        'index' =>
                            route(
                                'admin.branding_evaluations.index'
                            ),

                        'base' =>
                            "/admin/branding-evaluations/{$activation->id}",
                    ],
                ],
            ]
        );
    }

    public function evaluateNeed(
        Request $request,
        TransformationCapabilityActivation $activation,
        TransformationCapabilityNeed $need,
        TransformationCapabilityNeedEvaluationService $evaluations
    ): RedirectResponse {
        $this->assertBrandingActivation(
            $activation
        );

        abort_unless(
            $need->transformation_capability_activation_id
                === $activation->id,
            404
        );

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_IN_PROGRESS
        ) {
            return back()->withErrors([
                'evaluation' =>
                    'La evaluación solo puede editarse mientras Branding está en progreso.',
            ]);
        }

        $validated =
            $request->validate([
                'result' => [
                    'required',
                    'string',
                    Rule::in(
                        TransformationCapabilityNeedEvaluation::RESULTS
                    ),
                ],

                'findings' => [
                    'required',
                    'string',
                    'max:5000',
                ],

                'recommendation' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'priority' => [
                    'nullable',
                    'string',
                    Rule::in(
                        TransformationCapabilityNeedEvaluation::PRIORITIES
                    ),
                ],
            ]);

        $actor = $request->user();

        abort_unless(
            $actor,
            403
        );

        $evaluations->evaluate(
            $need,
            $actor,
            $validated
        );

        return back()->with(
            'success',
            'Área de Branding evaluada correctamente.'
        );
    }


    public function resetNeed(
        Request $request,
        TransformationCapabilityActivation $activation,
        TransformationCapabilityNeed $need,
        TransformationCapabilityNeedEvaluationService $evaluations
    ): RedirectResponse {
        $this->assertBrandingActivation(
            $activation
        );

        abort_unless(
            $need->transformation_capability_activation_id
                === $activation->id,
            404
        );

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_IN_PROGRESS
        ) {
            return back()->withErrors([
                'evaluation' =>
                    'La evaluación solo puede restablecerse mientras Branding está en progreso.',
            ]);
        }

        $actor = $request->user();

        abort_unless(
            $actor,
            403
        );

        $evaluations->resetHumanEvaluation(
            $need,
            $actor
        );

        return back()->with(
            'success',
            'Evaluación del área restablecida. El borrador automático se conservó cuando estaba disponible.'
        );
    }


    public function generateDrafts(
        Request $request,
        TransformationCapabilityActivation $activation,
        BrandingIdentityAutomaticEvaluationService $automatic
    ): RedirectResponse {
        $this->assertBrandingActivation(
            $activation
        );

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_IN_PROGRESS
        ) {
            return back()->withErrors([
                'evaluation' =>
                    'Los borradores automáticos solo pueden generarse mientras la evaluación está en progreso.',
            ]);
        }

        $actor = $request->user();

        abort_unless(
            $actor,
            403
        );

        $result =
            $automatic->generatePending(
                $activation,
                $actor
            );

        return back()->with(
            'success',
            'Borradores automáticos generados o regenerados: '
            .$result['generated']
            .'. Las evaluaciones profesionales ya confirmadas no fueron modificadas.'
        );
    }


    public function generateSummary(
        Request $request,
        TransformationCapabilityActivation $activation,
        TransformationCapabilityNeedEvaluationService $evaluations,
        BrandingIdentityEvaluationSummaryGenerator $generator,
        TransformationCapabilityEvaluationSummaryService $summaries
    ): RedirectResponse {
        $this->assertBrandingActivation(
            $activation
        );

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_IN_PROGRESS
        ) {
            return back()->withErrors([
                'summary' =>
                    'La síntesis solo puede generarse mientras la evaluación está en progreso.',
            ]);
        }

        $evaluations->assertAllEvaluated(
            $activation
        );

        $actor = $request->user();

        abort_unless(
            $actor,
            403
        );

        $generated =
            $generator->generate(
                $activation
            );

        $summary =
            $summaries->saveGeneratedDraft(
                $activation,
                $actor,
                $generated
            );

        return back()->with(
            'success',
            'Síntesis automática de Branding generada como borrador V'
            .$summary->generation_version
            .'.'
        );
    }

    public function markReadyForReview(
        Request $request,
        TransformationCapabilityActivation $activation,
        TransformationCapabilityNeedEvaluationService $evaluations,
        TransformationCapabilityEvaluationSummaryService $summaries,
        TransformationCapabilityActivationService $activations
    ): RedirectResponse {
        $this->assertBrandingActivation(
            $activation
        );

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_IN_PROGRESS
        ) {
            return back()->withErrors([
                'evaluation' =>
                    'Solo una evaluación en progreso puede enviarse a revisión.',
            ]);
        }

        $evaluations->assertAllEvaluated(
            $activation
        );

        $summaries->assertGeneratedForActivation(
            $activation
        );

        $actor = $request->user();

        abort_unless(
            $actor,
            403
        );

        $activations->markReadyForReview(
            $activation,
            $actor
        );

        return back()->with(
            'success',
            'Evaluación de Branding lista para revisión.'
        );
    }

    public function reviewSummary(
        Request $request,
        TransformationCapabilityActivation $activation,
        TransformationCapabilityEvaluationSummaryService $summaries
    ): RedirectResponse {
        $this->assertBrandingActivation(
            $activation
        );

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW
        ) {
            return back()->withErrors([
                'summary' =>
                    'La síntesis solo puede revisarse cuando la evaluación está lista para revisión.',
            ]);
        }

        $validated =
            $request->validate([
                'executive_summary' => [
                    'required',
                    'string',
                    'max:10000',
                ],

                'overall_recommendation' => [
                    'nullable',
                    'string',
                    'max:10000',
                ],
            ]);

        $actor =
            $request->user();

        abort_unless(
            $actor,
            403
        );

        $summaries->review(
            $activation,
            $actor,
            $validated
        );

        return back()->with(
            'success',
            'Síntesis de Branding revisada y confirmada.'
        );
    }

    public function validateEvaluation(
        Request $request,
        TransformationCapabilityActivation $activation,
        TransformationCapabilityEvaluationSummaryService $summaries,
        TransformationCapabilityActivationService $activations
    ): RedirectResponse {
        $this->assertBrandingActivation(
            $activation
        );

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW
        ) {
            return back()->withErrors([
                'capability' =>
                    'Solo una evaluación lista para revisión puede validarse.',
            ]);
        }

        $summaries->assertReviewedForActivation(
            $activation
        );

        $actor =
            $request->user();

        abort_unless(
            $actor,
            403
        );

        $activations->validate(
            $activation,
            $actor
        );

        return back()->with(
            'success',
            'Evaluación de Branding validada.'
        );
    }

    public function completeEvaluation(
        Request $request,
        TransformationCapabilityActivation $activation,
        TransformationCapabilityEvaluationSummaryService $summaries,
        TransformationCapabilityActivationService $activations
    ): RedirectResponse {
        $this->assertBrandingActivation(
            $activation
        );

        if (
            $activation->status
            !== TransformationCapabilityActivation::STATUS_VALIDATED
        ) {
            return back()->withErrors([
                'capability' =>
                    'Solo una evaluación validada puede marcarse como completada.',
            ]);
        }

        $summaries->assertReviewedForActivation(
            $activation
        );

        $actor =
            $request->user();

        abort_unless(
            $actor,
            403
        );

        $activations->complete(
            $activation,
            $actor
        );

        return back()->with(
            'success',
            'Evaluación de Branding completada.'
        );
    }

    private function assertBrandingActivation(
        TransformationCapabilityActivation $activation
    ): void {
        abort_unless(
            $activation->capability_key
                === 'branding_identity',
            404
        );

        abort_if(
            $activation->status
                === TransformationCapabilityActivation::STATUS_CANCELLED,
            404
        );
    }

    private function serializeEvaluation(
        ?TransformationCapabilityNeedEvaluation $evaluation
    ): array {
        if (! $evaluation) {
            return [
                'status' =>
                    TransformationCapabilityNeedEvaluation::STATUS_PENDING,

                'suggested_result' =>
                    null,

                'suggested_findings' =>
                    null,

                'suggested_recommendation' =>
                    null,

                'suggested_priority' =>
                    null,

                'suggested_questions' =>
                    [],

                'generation_context' =>
                    null,

                'generation_version' =>
                    0,

                'generated_at' =>
                    null,

                'result' =>
                    null,

                'findings' =>
                    null,

                'recommendation' =>
                    null,

                'priority' =>
                    null,

                'evaluated_at' =>
                    null,

                'evaluated_by' =>
                    null,
            ];
        }

        return [
            'status' =>
                (string) $evaluation->status,

            'suggested_result' =>
                $evaluation->suggested_result,

            'suggested_findings' =>
                $evaluation->suggested_findings,

            'suggested_recommendation' =>
                $evaluation->suggested_recommendation,

            'suggested_priority' =>
                $evaluation->suggested_priority,

            'suggested_questions' =>
                is_array(
                    $evaluation->suggested_questions
                )
                    ? $evaluation->suggested_questions
                    : [],

            'generation_context' =>
                is_array(
                    $evaluation->generation_context
                )
                    ? $evaluation->generation_context
                    : null,

            'generation_version' =>
                (int) $evaluation->generation_version,

            'generated_at' =>
                $evaluation
                    ->generated_at
                    ?->toISOString(),

            'result' =>
                $evaluation->result,

            'findings' =>
                $evaluation->findings,

            'recommendation' =>
                $evaluation->recommendation,

            'priority' =>
                $evaluation->priority,

            'evaluated_at' =>
                $evaluation
                    ->evaluated_at
                    ?->toISOString(),

            'evaluated_by' =>
                $evaluation->evaluatedBy
                    ? [
                        'id' =>
                            (int) $evaluation
                                ->evaluatedBy
                                ->id,

                        'name' =>
                            $evaluation
                                ->evaluatedBy
                                ->name,

                        'email' =>
                            $evaluation
                                ->evaluatedBy
                                ->email,
                    ]
                    : null,
        ];
    }


    private function serializeEvaluationSummary(
        ?TransformationCapabilityEvaluationSummary $summary
    ): ?array {
        if (! $summary) {
            return null;
        }

        $generatedPayload =
            is_array(
                $summary->generated_payload
            )
                ? $summary->generated_payload
                : [];

        $reviewedPayload =
            is_array(
                $summary->reviewed_payload
            )
                ? $summary->reviewed_payload
                : [];

        $isReviewed =
            $summary->hasReviewedSummary();

        $payload =
            $isReviewed
                ? $reviewedPayload
                : $generatedPayload;

        return [
            'status' =>
                (string) $summary->status,

            'is_reviewed' =>
                $isReviewed,

            'generation_version' =>
                (int) $summary->generation_version,

            'generated_at' =>
                $summary
                    ->generated_at
                    ?->toISOString(),

            'reviewed_at' =>
                $summary
                    ->reviewed_at
                    ?->toISOString(),

            'reviewed_by' =>
                $summary->reviewedBy
                    ? [
                        'id' =>
                            (int) $summary
                                ->reviewedBy
                                ->id,

                        'name' =>
                            $summary
                                ->reviewedBy
                                ->name,

                        'email' =>
                            $summary
                                ->reviewedBy
                                ->email,
                    ]
                    : null,

            'executive_summary' =>
                $payload[
                    'executive_summary'
                ]
                ?? null,

            'counts' =>
                is_array(
                    $payload[
                        'counts'
                    ]
                    ?? null
                )
                    ? $payload[
                        'counts'
                    ]
                    : [],

            'priority_order' =>
                is_array(
                    $payload[
                        'priority_order'
                    ]
                    ?? null
                )
                    ? $payload[
                        'priority_order'
                    ]
                    : [],

            'dependencies' =>
                is_array(
                    $payload[
                        'dependencies'
                    ]
                    ?? null
                )
                    ? $payload[
                        'dependencies'
                    ]
                    : [],

            'overall_recommendation' =>
                $payload[
                    'overall_recommendation'
                ]
                ?? null,

            'generation_context' =>
                is_array(
                    $summary->generation_context
                )
                    ? $summary
                        ->generation_context
                    : [],
        ];
    }

    private function summaryIsGenerated(
        ?TransformationCapabilityEvaluationSummary $summary
    ): bool {
        return $summary?->hasGeneratedDraft()
            ?? false;
    }

    private function activationStatusLabel(
        string $status
    ): string {
        return match ($status) {
            TransformationCapabilityActivation::STATUS_ACTIVATED =>
                'Activado',

            TransformationCapabilityActivation::STATUS_IN_PROGRESS =>
                'En progreso',

            TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW =>
                'Listo para revisión',

            TransformationCapabilityActivation::STATUS_VALIDATED =>
                'Validado',

            TransformationCapabilityActivation::STATUS_COMPLETED =>
                'Completado',

            default =>
                $status,
        };
    }
}
