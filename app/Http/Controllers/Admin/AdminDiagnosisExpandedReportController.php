<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\UpdateDiagnosisExpandedReportReviewRequest;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReport;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\DiagnosisExpandedReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDiagnosisExpandedReportController extends Controller
{
    public function show(
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        \App\Services\Diagnosis\DiagnosisExpandedReportCommercialService
            $commercialService
    ): Response {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $report = DiagnosisExpandedReport::query()
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->with([
                'generatedBy:id,name,email',
                'reviewedBy:id,name,email',
            ])
            ->orderByDesc('version')
            ->first();

        return Inertia::render(
            'Admin/DiagnosisRequests/ExpandedReport',
            [
                'contact' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'company' => $contact->company,
                    'email' => $contact->email,
                ],
                'assessment' => [
                    'id' => $assessment->id,
                    'organization_name' =>
                        $assessment->organization_name,
                    'status' => $assessment->status,
                    'published_at' =>
                        $assessment->published_at
                            ?->toISOString(),
                    'maturity_score' =>
                        $assessment->maturity_score,
                    'capacity_score' =>
                        $assessment->capacity_score,
                    'urgency_score' =>
                        $assessment->urgency_score,
                ],
                'report' => $report
                    ? $this->serializeReport(
                        $report,
                        $contact
                    )
                    : null,
                'can_generate' =>
                    $assessment->status === 'reviewed'
                    && $assessment->published_at !== null,
                'commercial_notice' =>
                    'El Informe Ampliado es un cargo one-time. No crea suscripción.',
                'commercial' =>
                    $commercialService->state($assessment),
                'endpoints' => [
                    'back' => route(
                        'admin.diagnosis_requests.show',
                        $contact
                    ),
                    'generate' => route(
                        'admin.diagnosis_requests.expanded_report.generate',
                        $contact
                    ),
                ],
            ]
        );
    }

    public function generate(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        DiagnosisExpandedReportService $reportService
    ): RedirectResponse {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $reportService->createOrGetDraft(
            $assessment,
            $request->user()
        );

        return back()->with(
            'success',
            'Borrador del Informe Ampliado preparado.'
        );
    }

    public function saveReview(
        UpdateDiagnosisExpandedReportReviewRequest $request,
        ContactRequest $contact,
        DiagnosisExpandedReport $report,
        DiagnosisAccessService $accessService,
        DiagnosisExpandedReportService $reportService
    ): RedirectResponse {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $this->assertReport(
            $report,
            $assessment
        );

        $reportService->saveReviewNotes(
            $report,
            $request->user(),
            $request->validated('review_notes')
        );

        return back()->with(
            'success',
            'Notas internas guardadas.'
        );
    }

    public function review(
        Request $request,
        ContactRequest $contact,
        DiagnosisExpandedReport $report,
        DiagnosisAccessService $accessService,
        DiagnosisExpandedReportService $reportService
    ): RedirectResponse {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $this->assertReport(
            $report,
            $assessment
        );

        $reportService->markUnderReview(
            $report,
            $request->user()
        );

        return back()->with(
            'success',
            'Informe marcado En revisión.'
        );
    }

    public function regenerate(
        Request $request,
        ContactRequest $contact,
        DiagnosisExpandedReport $report,
        DiagnosisAccessService $accessService,
        DiagnosisExpandedReportService $reportService
    ): RedirectResponse {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $this->assertReport(
            $report,
            $assessment
        );

        $reportService->regenerateDraft(
            $report,
            $request->user()
        );

        return back()->with(
            'success',
            'Borrador regenerado desde el diagnóstico publicado.'
        );
    }

    public function publish(
        Request $request,
        ContactRequest $contact,
        DiagnosisExpandedReport $report,
        DiagnosisAccessService $accessService,
        DiagnosisExpandedReportService $reportService,
        \App\Services\Diagnosis\DiagnosisExpandedReportCommercialService $commercialService
    ): RedirectResponse {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $this->assertReport(
            $report,
            $assessment
        );

        abort_unless(
            $commercialService->hasPaidAccess(
                $assessment
            ),
            422,
            'El Informe Ampliado solo puede publicarse después de confirmar el pago.'
        );

        $reportService->publish(
            $report,
            $request->user()
        );

        return back()->with(
            'success',
            'Informe Ampliado publicado para el cliente.'
        );
    }

    private function assessmentFor(
        ContactRequest $contact,
        DiagnosisAccessService $accessService
    ): DiagnosisAssessment {
        if (! $accessService->isDiagnosisContact($contact)) {
            abort(404);
        }

        $workflow = DiagnosisAccessRequest::query()
            ->where(
                'contact_request_id',
                $contact->id
            )
            ->with('assessment')
            ->firstOrFail();

        if (! $workflow->assessment) {
            abort(
                422,
                'La solicitud no tiene diagnóstico vinculado.'
            );
        }

        return $workflow->assessment;
    }

    private function assertReport(
        DiagnosisExpandedReport $report,
        DiagnosisAssessment $assessment
    ): void {
        abort_unless(
            (int) $report->diagnosis_assessment_id
                === (int) $assessment->id,
            404
        );
    }

    private function serializeReport(
        DiagnosisExpandedReport $report,
        ContactRequest $contact
    ): array {
        return [
            'id' => $report->id,
            'version' => $report->version,
            'status' => $report->status,
            'currency' => $report->currency,
            'subtotal' => (string) $report->subtotal,
            'tax_rate' => (string) $report->tax_rate,
            'tax_amount' =>
                (string) $report->tax_amount,
            'total' => (string) $report->total,
            'sections' => $report->sections ?? [],
            'review_notes' => $report->review_notes,
            'reviewed_at' =>
                $report->reviewed_at?->toISOString(),
            'published_at' =>
                $report->published_at?->toISOString(),
            'generated_by' => $report->generatedBy
                ? [
                    'name' =>
                        $report->generatedBy->name,
                    'email' =>
                        $report->generatedBy->email,
                ]
                : null,
            'reviewed_by' => $report->reviewedBy
                ? [
                    'name' =>
                        $report->reviewedBy->name,
                    'email' =>
                        $report->reviewedBy->email,
                ]
                : null,
            'endpoints' => [
                'save_review' => route(
                    'admin.diagnosis_requests.expanded_report.save_review',
                    [
                        'contact' => $contact,
                        'report' => $report,
                    ]
                ),
                'review' => route(
                    'admin.diagnosis_requests.expanded_report.review',
                    [
                        'contact' => $contact,
                        'report' => $report,
                    ]
                ),
                'regenerate' => route(
                    'admin.diagnosis_requests.expanded_report.regenerate',
                    [
                        'contact' => $contact,
                        'report' => $report,
                    ]
                ),
                'publish' => route(
                    'admin.diagnosis_requests.expanded_report.publish',
                    [
                        'contact' => $contact,
                        'report' => $report,
                    ]
                ),
            ],
        ];
    }
}
