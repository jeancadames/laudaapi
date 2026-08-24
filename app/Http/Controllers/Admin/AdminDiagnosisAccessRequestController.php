<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\PublishDiagnosisResultRequest;
use App\Http\Requests\Diagnosis\SaveDiagnosisReviewRequest;
use App\Mail\DiagnosisResultPublishedMail;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Services\AuditService;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\DiagnosisResultPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminDiagnosisAccessRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $status = (string) ($request->get('status', 'all') ?: 'all');

        if (
            $status !== 'all'
            && !in_array($status, DiagnosisAccessRequest::STATUSES, true)
        ) {
            $status = 'all';
        }

        $base = ContactRequest::query()
            ->where(function ($q): void {
                $q->whereIn('topic', [
                    'Solicitud de acceso al Diagnóstico LAUDA 360',
                    'Solicitud de Diagnóstico Digital 360',
                ])->orWhereIn('metadata->request_type', [
                    'digital_diagnosis_access_request',
                    'digital_transformation_diagnosis',
                ]);
            });

        if ($search !== '') {
            $base->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        $base
            ->leftJoin(
                'diagnosis_access_requests as dar',
                'dar.contact_request_id',
                '=',
                'contact_requests.id'
            )
            ->select([
                'contact_requests.*',
                'dar.public_id as workflow_public_id',
                'dar.status as workflow_status',
                'dar.user_id as workflow_user_id',
                'dar.diagnosis_assessment_id as workflow_assessment_id',
                'dar.invitation_sent_at as workflow_invitation_sent_at',
                'dar.invitation_accepted_at as workflow_invitation_accepted_at',
            ]);

        if ($status === DiagnosisAccessRequest::STATUS_PENDING) {
            $base->where(function ($q): void {
                $q->whereNull('dar.id')
                    ->orWhere(
                        'dar.status',
                        DiagnosisAccessRequest::STATUS_PENDING
                    );
            });
        } elseif ($status !== 'all') {
            $base->where('dar.status', $status);
        }

        $requests = $base
            ->orderByRaw("CASE
                WHEN dar.id IS NULL THEN 1
                WHEN dar.status = 'pending' THEN 1
                WHEN dar.status = 'under_review' THEN 2
                WHEN dar.status = 'more_info_required' THEN 3
                WHEN dar.status = 'approved' THEN 4
                WHEN dar.status = 'invited' THEN 5
                WHEN dar.status = 'active' THEN 6
                WHEN dar.status = 'rejected' THEN 7
                ELSE 99 END")
            ->orderByDesc('contact_requests.id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (ContactRequest $contact): array {
                $metadata = $contact->metadata ?? [];

                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'company' => $contact->company,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'company_size' => $metadata['company_size'] ?? null,
                    'main_challenge' => $metadata['main_challenge'] ?? null,
                    'assistance_level' => $metadata['assistance_level'] ?? null,
                    'status' => $contact->workflow_status
                        ?: DiagnosisAccessRequest::STATUS_PENDING,
                    'workflow_public_id' => $contact->workflow_public_id,
                    'user_id' => $contact->workflow_user_id,
                    'assessment_id' => $contact->workflow_assessment_id,
                    'invitation_sent_at' => $contact
                        ->workflow_invitation_sent_at,
                    'invitation_accepted_at' => $contact
                        ->workflow_invitation_accepted_at,
                    'created_at' => $contact->created_at?->toISOString(),
                ];
            });

        $countsQuery = DB::table('contact_requests as c')
            ->leftJoin(
                'diagnosis_access_requests as dar',
                'dar.contact_request_id',
                '=',
                'c.id'
            )
            ->where(function ($q): void {
                $q->whereIn('c.topic', [
                    'Solicitud de acceso al Diagnóstico LAUDA 360',
                    'Solicitud de Diagnóstico Digital 360',
                ])->orWhereIn('c.metadata->request_type', [
                    'digital_diagnosis_access_request',
                    'digital_transformation_diagnosis',
                ]);
            });

        $counts = [
            'all' => (clone $countsQuery)->count('c.id'),
            'pending' => (clone $countsQuery)
                ->where(function ($q): void {
                    $q->whereNull('dar.id')->orWhere(
                        'dar.status',
                        DiagnosisAccessRequest::STATUS_PENDING
                    );
                })
                ->count('c.id'),
        ];

        foreach (
            array_diff(
                DiagnosisAccessRequest::STATUSES,
                [DiagnosisAccessRequest::STATUS_PENDING]
            ) as $itemStatus
        ) {
            $counts[$itemStatus] = (clone $countsQuery)
                ->where('dar.status', $itemStatus)
                ->count('c.id');
        }

        return Inertia::render('Admin/DiagnosisRequests/Index', [
            'requests' => $requests,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'counts' => $counts,
            'statuses' => DiagnosisAccessRequest::STATUSES,
        ]);
    }

    public function show(
        ContactRequest $contact,
        DiagnosisAccessService $service
    ): Response {
        if (!$service->isDiagnosisContact($contact)) {
            abort(404);
        }

        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->with([
                'user:id,name,email,role,must_change_password',
                'assessment.reviewedBy:id,name,email',
            ])
            ->first();

        $assessment = $workflow?->assessment;

        return Inertia::render('Admin/DiagnosisRequests/Show', [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'company' => $contact->company,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'topic' => $contact->topic,
                'message' => $contact->message,
                'metadata' => $contact->metadata,
                'created_at' => $contact->created_at?->toISOString(),
            ],
            'workflow' => $workflow ? [
                'public_id' => $workflow->public_id,
                'status' => $workflow->status,
                'review_notes' => $workflow->review_notes,
                'rejection_reason' => $workflow->rejection_reason,
                'approved_at' => $workflow->approved_at?->toISOString(),
                'invitation_sent_at' => $workflow
                    ->invitation_sent_at?->toISOString(),
                'invitation_expires_at' => $workflow
                    ->invitation_expires_at?->toISOString(),
                'invitation_accepted_at' => $workflow
                    ->invitation_accepted_at?->toISOString(),
                'rejected_at' => $workflow->rejected_at?->toISOString(),
                'user' => $workflow->user,
                'assessment' => $assessment ? [
                    'id' => $assessment->id,
                    'organization_name' => $assessment->organization_name,
                    'status' => $assessment->status,
                    'current_step' => $assessment->current_step,
                    'answers' => $assessment->answers ?? [],
                    'notes' => $assessment->notes ?? [],
                    'maturity_score' => $assessment->maturity_score,
                    'capacity_score' => $assessment->capacity_score,
                    'urgency_score' => $assessment->urgency_score,
                    'dimension_scores' => $assessment->dimension_scores ?? [],
                    'maturity_level' => $assessment->maturity_level,
                    'urgency_level' => $assessment->urgency_level,
                    'recommended_modality' => $assessment
                        ->recommended_modality,
                    'recommended_modality_label' => $assessment
                        ->recommended_modality_label,
                    'review_required' => (bool) $assessment->review_required,
                    'review_summary' => $assessment->review_summary,
                    'review_priorities' => $assessment
                        ->review_priorities ?? [],
                    'final_modality' => $assessment->final_modality,
                    'final_modality_label' => $assessment
                        ->final_modality_label,
                    'submitted_at' => $assessment
                        ->submitted_at?->toISOString(),
                    'reviewed_at' => $assessment
                        ->reviewed_at?->toISOString(),
                    'published_at' => $assessment
                        ->published_at?->toISOString(),
                    'reviewed_by' => $assessment->reviewedBy ? [
                        'id' => $assessment->reviewedBy->id,
                        'name' => $assessment->reviewedBy->name,
                        'email' => $assessment->reviewedBy->email,
                    ] : null,
                ] : null,
            ] : null,
            'statuses' => DiagnosisAccessRequest::STATUSES,
            'businessProfileOptions' => config(
                'lauda360_business_profile',
                []
            ),
        ]);
    }

    public function updateStatus(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $service
    ): RedirectResponse {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    DiagnosisAccessRequest::STATUS_PENDING,
                    DiagnosisAccessRequest::STATUS_UNDER_REVIEW,
                    DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED,
                ]),
            ],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $workflow = $service->workflowFor($contact);

        $workflow->forceFill([
            'status' => $data['status'],
            'review_notes' => $data['review_notes']
                ?? $workflow->review_notes,
            'reviewed_by_user_id' => $request->user()->id,
        ])->save();

        AuditService::log('diagnosis_access_status_changed', $workflow, [
            'status' => $workflow->status,
            'reviewed_by_user_id' => $request->user()->id,
        ]);

        return back()->with(
            'success',
            'Estado del diagnóstico actualizado.'
        );
    }

    public function approve(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $service
    ): RedirectResponse {
        $service->approve($contact, $request->user());

        return back()->with(
            'success',
            'Solicitud aprobada e invitación de diagnóstico enviada.'
        );
    }

    public function resend(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $service
    ): RedirectResponse {
        if (!$service->isDiagnosisContact($contact)) {
            abort(404);
        }

        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->firstOrFail();

        if (!$workflow->canResendInvitation()) {
            abort(
                422,
                'La solicitud todavía no está preparada para reenviar la invitación.'
            );
        }

        $service->sendInvitation($workflow, $request->user());

        return back()->with('success', 'Invitación reenviada.');
    }

    public function reject(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $service
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $workflow = $service->workflowFor($contact);

        if ($workflow->status === DiagnosisAccessRequest::STATUS_ACTIVE) {
            abort(
                422,
                'No se puede rechazar un acceso que ya fue aceptado por el cliente.'
            );
        }

        $workflow->forceFill([
            'status' => DiagnosisAccessRequest::STATUS_REJECTED,
            'rejection_reason' => $data['reason'],
            'rejected_at' => now(),
            'reviewed_by_user_id' => $request->user()->id,
        ])->save();

        AuditService::log('diagnosis_access_rejected', $workflow, [
            'reason' => $data['reason'],
            'reviewed_by_user_id' => $request->user()->id,
        ]);

        return back()->with(
            'success',
            'Solicitud de diagnóstico rechazada.'
        );
    }

    public function saveReview(
        SaveDiagnosisReviewRequest $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        DiagnosisResultPublisher $publisher
    ): RedirectResponse {
        if (!$accessService->isDiagnosisContact($contact)) {
            abort(404);
        }

        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->with('assessment')
            ->firstOrFail();

        if (!$workflow->assessment) {
            abort(
                422,
                'La solicitud no tiene un diagnóstico vinculado.'
            );
        }

        $publisher->saveDraft(
            $workflow->assessment,
            $request->user(),
            $request->validated()
        );

        return back()->with(
            'success',
            'Borrador de revisión guardado.'
        );
    }

    public function publishResult(
        PublishDiagnosisResultRequest $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        DiagnosisResultPublisher $publisher
    ): RedirectResponse {
        if (!$accessService->isDiagnosisContact($contact)) {
            abort(404);
        }

        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->with(['user', 'assessment'])
            ->firstOrFail();

        if (!$workflow->assessment || !$workflow->user) {
            abort(
                422,
                'La solicitud no tiene diagnóstico y usuario vinculados.'
            );
        }

        $assessment = $publisher->publish(
            $workflow->assessment,
            $request->user(),
            $request->validated()
        );

        try {
            Mail::to($workflow->user->email)->send(
                new DiagnosisResultPublishedMail(
                    $assessment,
                    route('diagnosis.show', $assessment)
                )
            );
        } catch (\Throwable $e) {
            Log::warning(
                'Resultado Diagnóstico 360 publicado, pero el correo falló.',
                [
                    'assessment_id' => $assessment->id,
                    'user_id' => $workflow->user->id,
                    'exception' => $e->getMessage(),
                ]
            );

            return back()->with(
                'warning',
                'Resultado publicado. No se pudo enviar el correo de notificación.'
            );
        }

        return back()->with(
            'success',
            'Resultado revisado, publicado y notificado al cliente.'
        );
    }
}
