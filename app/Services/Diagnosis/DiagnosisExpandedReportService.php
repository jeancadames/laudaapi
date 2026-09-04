<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;
use Illuminate\Validation\ValidationException;

class DiagnosisExpandedReportService
{
    public function __construct(
        private readonly DiagnosisExpandedReportGenerator
            $generator
    ) {
    }

    /**
     * Crea una nueva versión draft solo cuando el resultado gratuito
     * ya fue revisado y publicado.
     *
     * Es idempotente mientras exista un draft/under_review activo.
     */
    public function createOrGetDraft(
        DiagnosisAssessment $assessment,
        User $actor
    ): DiagnosisExpandedReport {
        $this->assertAssessmentEligible($assessment);

        return DB::transaction(
            function () use (
                $assessment,
                $actor
            ): DiagnosisExpandedReport {
                $locked = DiagnosisAssessment::query()
                    ->whereKey($assessment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertAssessmentEligible($locked);

                $active = DiagnosisExpandedReport::query()
                    ->where(
                        'diagnosis_assessment_id',
                        $locked->id
                    )
                    ->whereIn(
                        'status',
                        [
                            DiagnosisExpandedReport::STATUS_DRAFT,
                            DiagnosisExpandedReport::STATUS_UNDER_REVIEW,
                        ]
                    )
                    ->orderByDesc('version')
                    ->first();

                if ($active) {
                    return $active;
                }

                $latestVersion = (int)
                    DiagnosisExpandedReport::query()
                        ->where(
                            'diagnosis_assessment_id',
                            $locked->id
                        )
                        ->max('version');

                $generated =
                    $this->generator->generate($locked);

                return DiagnosisExpandedReport::create([
                    'diagnosis_assessment_id' =>
                        $locked->id,
                    'version' =>
                        $latestVersion + 1,
                    'status' =>
                        DiagnosisExpandedReport::STATUS_DRAFT,
                    'generated_by_user_id' =>
                        $actor->id,
                    'currency' =>
                        'DOP',
                    'subtotal' =>
                        0,
                    'tax_rate' =>
                        0,
                    'tax_amount' =>
                        0,
                    'total' =>
                        0,
                    'methodology_version' =>
                        $locked->methodology_version,
                    'source_snapshot' =>
                        $generated['source_snapshot'],
                    'sections' =>
                        $generated['sections'],
                ]);
            }
        );
    }

    /**
     * Regenera exclusivamente una versión en draft.
     * Nunca toca versiones publicadas.
     */
    public function regenerateDraft(
        DiagnosisExpandedReport $report,
        User $actor
    ): DiagnosisExpandedReport {
        if (
            $report->status
            !== DiagnosisExpandedReport::STATUS_DRAFT
        ) {
            throw ValidationException::withMessages([
                'report' => [
                    'Solo una versión en borrador puede regenerarse.',
                ],
            ]);
        }

        $assessment = $report->assessment;

        if (! $assessment) {
            throw ValidationException::withMessages([
                'assessment' => [
                    'El informe no tiene un diagnóstico asociado.',
                ],
            ]);
        }

        $this->assertAssessmentEligible($assessment);

        $generated =
            $this->generator->generate($assessment);

        $report->forceFill([
            'generated_by_user_id' => $actor->id,
            'methodology_version' =>
                $assessment->methodology_version,
            'source_snapshot' =>
                $generated['source_snapshot'],
            'sections' =>
                $generated['sections'],
        ])->save();

        return $report->fresh();
    }

    /**
     * Snapshot monetario independiente de Company/Subscriber.
     *
     * La factura real se conectará en R1-C2.
     *
     * @return array{
     *     currency:string,
     *     subtotal:float,
     *     tax_rate:float,
     *     tax_amount:float,
     *     total:float
     * }
     */
    public function commercialSnapshot(): array
    {
        $offer = config(
            'lauda360_commercial.expanded_report',
            []
        );

        $currency = strtoupper(
            trim(
                (string)
                    ($offer['currency'] ?? 'DOP')
            )
        );

        $subtotal = round(
            (float) ($offer['subtotal'] ?? 0),
            2
        );

        $taxRate = round(
            (float) ($offer['tax_rate'] ?? 0),
            3
        );

        $taxAmount = round(
            $subtotal * ($taxRate / 100),
            2
        );

        return [
            'currency' => $currency,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => round(
                $subtotal + $taxAmount,
                2
            ),
        ];
    }

    private function assertAssessmentEligible(
        DiagnosisAssessment $assessment
    ): void {
        if (
            $assessment->status !== 'reviewed'
            || $assessment->published_at === null
        ) {
            throw ValidationException::withMessages([
                'assessment' => [
                    'El Informe Ampliado solo puede iniciarse después de publicar el resultado oficial del Diagnóstico LAUDA 360.',
                ],
            ]);
        }
    }

    public function saveReviewNotes(
        DiagnosisExpandedReport $report,
        User $actor,
        ?string $notes
    ): DiagnosisExpandedReport {
        if (! $report->isEditable()) {
            throw ValidationException::withMessages([
                'report' => [
                    'Una versión publicada no puede modificarse.',
                ],
            ]);
        }

        $report->forceFill([
            'review_notes' => filled($notes)
                ? trim((string) $notes)
                : null,
            'reviewed_by_user_id' => $actor->id,
        ])->save();

        AuditService::log(
            'diagnosis_expanded_report_review_notes_saved',
            $report,
            [
                'assessment_id' =>
                    $report->diagnosis_assessment_id,
                'version' => $report->version,
                'actor_user_id' => $actor->id,
            ]
        );

        return $report->fresh([
            'generatedBy',
            'reviewedBy',
        ]);
    }

    public function markUnderReview(
        DiagnosisExpandedReport $report,
        User $actor
    ): DiagnosisExpandedReport {
        if (
            $report->status
            !== DiagnosisExpandedReport::STATUS_DRAFT
        ) {
            throw ValidationException::withMessages([
                'report' => [
                    'Solo un borrador puede pasar a revisión.',
                ],
            ]);
        }

        $report->forceFill([
            'status' =>
                DiagnosisExpandedReport::STATUS_UNDER_REVIEW,
            'reviewed_by_user_id' => $actor->id,
        ])->save();

        AuditService::log(
            'diagnosis_expanded_report_under_review',
            $report,
            [
                'assessment_id' =>
                    $report->diagnosis_assessment_id,
                'version' => $report->version,
                'actor_user_id' => $actor->id,
            ]
        );

        return $report->fresh([
            'generatedBy',
            'reviewedBy',
        ]);
    }

    public function publish(
        DiagnosisExpandedReport $report,
        User $actor
    ): DiagnosisExpandedReport {
        if (
            ! in_array(
                $report->status,
                [
                    DiagnosisExpandedReport::STATUS_DRAFT,
                    DiagnosisExpandedReport::STATUS_UNDER_REVIEW,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'report' => [
                    'Solo una versión editable puede publicarse.',
                ],
            ]);
        }

        $sections = $report->sections ?? [];

        foreach ([
            'executive_summary.body',
            'business_context.interpretation',
            'maturity_interpretation',
            'dimension_analysis.items',
            'critical_gaps.items',
            'business_implications.items',
            'recommended_focus.items',
            'execution_capacity.body',
            'next_step_note.body',
        ] as $path) {
            $value = data_get($sections, $path);

            if (
                $value === null
                || $value === ''
                || $value === []
            ) {
                throw ValidationException::withMessages([
                    'report' => [
                        "El informe no está completo: falta {$path}.",
                    ],
                ]);
            }
        }

        $report->forceFill([
            'status' =>
                DiagnosisExpandedReport::STATUS_PUBLISHED,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'published_at' => now(),
        ])->save();

        AuditService::log(
            'diagnosis_expanded_report_published',
            $report,
            [
                'assessment_id' =>
                    $report->diagnosis_assessment_id,
                'version' => $report->version,
                'actor_user_id' => $actor->id,
            ]
        );

        return $report->fresh([
            'generatedBy',
            'reviewedBy',
        ]);
    }

}
