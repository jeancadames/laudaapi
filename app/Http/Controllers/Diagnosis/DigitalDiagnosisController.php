<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\SubmitDiagnosisAssessmentRequest;
use App\Http\Requests\Diagnosis\UpdateDiagnosisAssessmentRequest;
use App\Models\DiagnosisAssessment;
use App\Services\Diagnosis\DiagnosisBusinessProfileService;
use App\Services\Diagnosis\DiagnosisExecutiveSummaryGenerator;
use App\Services\Diagnosis\Lauda360ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DigitalDiagnosisController extends Controller
{
    public function show(
        DiagnosisAssessment $assessment,
        DiagnosisBusinessProfileService $businessProfile
    ): Response {
        Gate::authorize('view', $assessment);

        $published = $assessment->status === 'reviewed'
            && $assessment->published_at !== null;

        return Inertia::render('Diagnosis/Show', [
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'current_step' => $assessment->current_step,
                'answers' => $assessment->answers ?? [],
                'notes' => $assessment->notes ?? [],
                'business_activity_type' =>
                    $assessment->business_activity_type,
                'business_sector' =>
                    $assessment->business_sector,
                'business_sector_other' =>
                    $assessment->business_sector_other,
                'customer_market' =>
                    $assessment->customer_market,
                'sales_channels' =>
                    $assessment->sales_channels ?? [],
                'sales_channel_other' =>
                    $assessment->sales_channel_other,
                'logistics_operation_types' =>
                    $assessment->logistics_operation_types ?? [],
                'logistics_operation_other' =>
                    $assessment->logistics_operation_other,
                'business_activity_description' =>
                    $assessment->business_activity_description,
                'business_profile_completed_at' =>
                    $assessment->business_profile_completed_at
                        ?->toIso8601String(),
                'started_at' =>
                    $assessment->started_at?->toIso8601String(),
                'submitted_at' =>
                    $assessment->submitted_at?->toIso8601String(),
                'reviewed_at' =>
                    $assessment->reviewed_at?->toIso8601String(),
                'updated_at' =>
                    $assessment->updated_at?->toIso8601String(),
            ],
            'organization' => [
                'id' =>
                    $assessment->organization_id ?? $assessment->id,
                'name' => $assessment->organization_name,
            ],
            'businessProfileOptions' =>
                $businessProfile->options(),
            'result' => $published ? [
                'maturity_score' =>
                    $assessment->maturity_score,
                'maturity_level' =>
                    $assessment->maturity_level,
                'capacity_score' =>
                    $assessment->capacity_score,
                'urgency_score' =>
                    $assessment->urgency_score,
                'urgency_level' =>
                    $assessment->urgency_level,
                'dimension_scores' =>
                    $assessment->dimension_scores ?? [],
                'modality' =>
                    $assessment->final_modality
                    ?: $assessment->recommended_modality,
                'modality_label' =>
                    $assessment->final_modality_label
                    ?: $assessment->recommended_modality_label,
                'summary' =>
                    $assessment->review_summary,
                'priorities' =>
                    $assessment->review_priorities ?? [],
                'published_at' =>
                    $assessment->published_at
                        ?->toIso8601String(),
            ] : null,
            'expanded_report' =>
                $published
                    ? $assessment->expandedReports()
                        ->where(
                            'status',
                            \App\Models\DiagnosisExpandedReport::STATUS_PUBLISHED
                        )
                        ->whereNotNull('published_at')
                        ->orderByDesc('version')
                        ->first()
                        ?->only([
                            'id',
                            'version',
                            'published_at',
                        ])
                    : null,
            'expanded_report_commercial' =>
                app(
                    \App\Services\Diagnosis\DiagnosisExpandedReportCommercialService::class
                )->state($assessment),
            'endpoints' => [
                'request_expanded_report' =>
                    route(
                        'diagnosis.expanded_report.request',
                        $assessment
                    ),
                'update' =>
                    route('diagnosis.update', $assessment),
                'submit' =>
                    route('diagnosis.submit', $assessment),
                'back' => url('/'),
            ],
        ]);
    }

    public function update(
        UpdateDiagnosisAssessmentRequest $request,
        DiagnosisAssessment $assessment,
        DiagnosisBusinessProfileService $businessProfile
    ): RedirectResponse {
        Gate::authorize('update', $assessment);

        $validated = $request->validated();

        $profile = $businessProfile->extract(
            $validated
        );

        DB::transaction(
            function () use (
                $assessment,
                $validated,
                $profile
            ): void {
                $assessment->fill([
                    'answers' =>
                        $validated['answers'] ?? [],
                    'notes' =>
                        $validated['notes'] ?? [],
                    'current_step' =>
                        (int) $validated['current_step'],
                    ...$profile,
                    'business_profile_completed_at' =>
                        $assessment->business_profile_completed_at
                        ?? now(),
                ]);

                if ($assessment->status === 'draft') {
                    $assessment->status = 'in_progress';
                    $assessment->started_at ??= now();
                }

                $assessment->save();
            }
        );

        return back()->with(
            'success',
            'Diagnóstico y perfil comercial guardados.'
        );
    }

    public function submit(
        SubmitDiagnosisAssessmentRequest $request,
        DiagnosisAssessment $assessment,
        Lauda360ScoringService $scoring,
        DiagnosisBusinessProfileService $businessProfile,
        DiagnosisExecutiveSummaryGenerator $executiveSummary
    ): RedirectResponse {
        Gate::authorize('submit', $assessment);

        $validated = $request->validated();
        $answers = $validated['answers'];
        $notes = $validated['notes'] ?? [];
        $profile = $businessProfile->extract(
            $validated
        );

        $result = $scoring->calculate($answers);

        $suggestion = $executiveSummary->generate(
            $result,
            $profile
        );

        DB::transaction(
            function () use (
                $assessment,
                $answers,
                $notes,
                $profile,
                $result,
                $suggestion
            ): void {
                $assessment->fill([
                    'answers' => $answers,
                    'notes' => $notes,
                    ...$profile,
                    'business_profile_completed_at' =>
                        $assessment->business_profile_completed_at
                        ?? now(),
                    'current_step' =>
                        (int) config(
                            'lauda360_diagnosis.steps',
                            11
                        ),
                    'status' => 'submitted',
                    'started_at' =>
                        $assessment->started_at ?? now(),
                    'submitted_at' => now(),
                    ...$result,
                ]);

                if (blank($assessment->review_summary)) {
                    $assessment->review_summary =
                        $suggestion['summary'];
                }

                if (
                    empty($assessment->review_priorities)
                ) {
                    $assessment->review_priorities =
                        $suggestion['priorities'];
                }

                if (blank($assessment->final_modality)) {
                    $assessment->final_modality =
                        $suggestion['modality'];
                }

                if (
                    blank(
                        $assessment->final_modality_label
                    )
                ) {
                    $assessment->final_modality_label =
                        $suggestion['modality_label'];
                }

                $assessment->save();
            }
        );

        return redirect()
            ->route('diagnosis.show', $assessment)
            ->with(
                'success',
                'Diagnóstico enviado correctamente.'
            );
    }
}
