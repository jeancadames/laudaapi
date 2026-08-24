<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\UpdateDiagnosisDetailedRoadmapReviewRequest;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\DiagnosisCommercialNotificationService;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapCommercialService;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapService;
use App\Services\Diagnosis\DiagnosisTransformationProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDiagnosisDetailedRoadmapController extends Controller
{
    public function show(
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        DiagnosisDetailedRoadmapCommercialService $commercialService,
        DiagnosisTransformationProgressService $progressService
    ): Response {
        $assessment = $this->assessmentFor($contact, $accessService);

        $sourceReport = DiagnosisExpandedReport::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('status', DiagnosisExpandedReport::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        $roadmap = DiagnosisDetailedRoadmap::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->with([
                'generatedBy:id,name,email',
                'reviewedBy:id,name,email',
                'sourceExpandedReport:id,version,status,published_at',
            ])
            ->orderByDesc('version')
            ->first();

        $commercial = $commercialService->state(
            $assessment
        );

        $readiness =
            $progressService->roadmapReadiness(
                $assessment
            );

        return Inertia::render(
            'Admin/DiagnosisRequests/DetailedRoadmap',
            [
                'contact' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'company' => $contact->company,
                    'email' => $contact->email,
                ],
                'assessment' => [
                    'id' => $assessment->id,
                    'organization_name' => $assessment->organization_name,
                    'maturity_score' => $assessment->maturity_score,
                    'capacity_score' => $assessment->capacity_score,
                    'urgency_score' => $assessment->urgency_score,
                ],
                'source_report' => $sourceReport
                    ? [
                        'id' => $sourceReport->id,
                        'version' => $sourceReport->version,
                        'published_at' => $sourceReport->published_at?->toISOString(),
                    ]
                    : null,
                'roadmap' => $roadmap
                    ? $this->serializeRoadmap($roadmap, $contact)
                    : null,
                'can_generate' =>
                    $readiness['generation_ready'],
                'generation_readiness' =>
                    $readiness,
                'transformation_progress' =>
                    $progressService->forAssessment(
                        $assessment,
                        true
                    ),
                'commercial' => $commercial,
                'endpoints' => [
                    'back' => route(
                        'admin.diagnosis_requests.expanded_report.show',
                        $contact
                    ),
                    'generate' => route(
                        'admin.diagnosis_requests.detailed_roadmap.generate',
                        $contact
                    ),
                    'prepare_invoice' => $commercial
                        ? route(
                            'admin.diagnosis_requests.detailed_roadmap.prepare_invoice',
                            [
                                'contact' => $contact,
                                'order' => $commercial['id'],
                            ]
                        )
                        : null,
                    'record_payment' => $commercial
                        ? route(
                            'admin.diagnosis_requests.detailed_roadmap.record_payment',
                            [
                                'contact' => $contact,
                                'order' => $commercial['id'],
                            ]
                        )
                        : null,
                ],
            ]
        );
    }

    public function generate(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        DiagnosisDetailedRoadmapService $service
    ): RedirectResponse {
        $assessment = $this->assessmentFor($contact, $accessService);

        $service->createOrGetDraft(
            $assessment,
            $request->user()
        );

        return back()->with(
            'success',
            'Borrador del Roadmap Detallado preparado.'
        );
    }

    public function saveReview(
        UpdateDiagnosisDetailedRoadmapReviewRequest $request,
        ContactRequest $contact,
        DiagnosisDetailedRoadmap $roadmap,
        DiagnosisAccessService $accessService,
        DiagnosisDetailedRoadmapService $service
    ): RedirectResponse {
        $assessment = $this->assessmentFor($contact, $accessService);
        $this->assertRoadmap($roadmap, $assessment);

        $service->saveReviewNotes(
            $roadmap,
            $request->user(),
            $request->validated('review_notes')
        );

        return back()->with('success', 'Notas internas guardadas.');
    }

    public function review(
        Request $request,
        ContactRequest $contact,
        DiagnosisDetailedRoadmap $roadmap,
        DiagnosisAccessService $accessService,
        DiagnosisDetailedRoadmapService $service
    ): RedirectResponse {
        $assessment = $this->assessmentFor($contact, $accessService);
        $this->assertRoadmap($roadmap, $assessment);

        $service->markUnderReview(
            $roadmap,
            $request->user()
        );

        return back()->with('success', 'Roadmap marcado En revisión.');
    }

    public function regenerate(
        Request $request,
        ContactRequest $contact,
        DiagnosisDetailedRoadmap $roadmap,
        DiagnosisAccessService $accessService,
        DiagnosisDetailedRoadmapService $service
    ): RedirectResponse {
        $assessment = $this->assessmentFor($contact, $accessService);
        $this->assertRoadmap($roadmap, $assessment);

        $service->regenerateDraft(
            $roadmap,
            $request->user()
        );

        return back()->with(
            'success',
            'Roadmap editable regenerado desde el último Informe Ampliado publicado.'
        );
    }

    public function publish(
        Request $request,
        ContactRequest $contact,
        DiagnosisDetailedRoadmap $roadmap,
        DiagnosisAccessService $accessService,
        DiagnosisDetailedRoadmapService $service,
        DiagnosisDetailedRoadmapCommercialService $commercialService,
        DiagnosisCommercialNotificationService $notificationService
    ): RedirectResponse {
        $assessment = $this->assessmentFor($contact, $accessService);
        $this->assertRoadmap($roadmap, $assessment);

        abort_unless(
            $commercialService->hasPaidAccess(
                $assessment
            ),
            422,
            'El Roadmap Detallado solo puede publicarse después de confirmar el pago.'
        );

        $published = $service->publish(
            $roadmap,
            $request->user()
        );

        $notificationService->deliverablePublished(
            $assessment,
            'detailed_roadmap',
            (int) $published->version,
            route(
                'diagnosis.detailed_roadmap.show',
                $assessment
            )
        );

        return back()->with(
            'success',
            'Roadmap Detallado publicado para el cliente.'
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
            ->where('contact_request_id', $contact->id)
            ->with('assessment')
            ->firstOrFail();

        if (! $workflow->assessment) {
            abort(422, 'La solicitud no tiene diagnóstico vinculado.');
        }

        return $workflow->assessment;
    }

    private function assertRoadmap(
        DiagnosisDetailedRoadmap $roadmap,
        DiagnosisAssessment $assessment
    ): void {
        abort_unless(
            (int) $roadmap->diagnosis_assessment_id
                === (int) $assessment->id,
            404
        );
    }

    private function serializeRoadmap(
        DiagnosisDetailedRoadmap $roadmap,
        ContactRequest $contact
    ): array {
        return [
            'id' => $roadmap->id,
            'version' => $roadmap->version,
            'status' => $roadmap->status,
            'source_expanded_report_id' => $roadmap->source_expanded_report_id,
            'roadmap' => $roadmap->roadmap ?? [],
            'review_notes' => $roadmap->review_notes,
            'published_at' => $roadmap->published_at?->toISOString(),
            'endpoints' => [
                'save_review' => route(
                    'admin.diagnosis_requests.detailed_roadmap.save_review',
                    ['contact' => $contact, 'roadmap' => $roadmap]
                ),
                'review' => route(
                    'admin.diagnosis_requests.detailed_roadmap.review',
                    ['contact' => $contact, 'roadmap' => $roadmap]
                ),
                'regenerate' => route(
                    'admin.diagnosis_requests.detailed_roadmap.regenerate',
                    ['contact' => $contact, 'roadmap' => $roadmap]
                ),
                'publish' => route(
                    'admin.diagnosis_requests.detailed_roadmap.publish',
                    ['contact' => $contact, 'roadmap' => $roadmap]
                ),
            ],
        ];
    }
}
