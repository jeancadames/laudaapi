<?php

namespace App\Http\Controllers\Admin;

use App\Services\Diagnosis\DiagnosisAssessmentCompletenessService;

use App\Mail\DiagnosisMoreInfoRequiredMail;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnosis\PublishDiagnosisResultRequest;
use App\Http\Requests\Diagnosis\SaveDiagnosisReviewRequest;
use App\Mail\DiagnosisResultPublishedMail;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Services\AuditService;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\DiagnosisAssessmentDeletionService;
use App\Services\Diagnosis\DiagnosisDeliverableValidationService;
use App\Services\Diagnosis\DiagnosisResultPublisher;
use App\Services\Diagnosis\DiagnosisTransformationProgressService;
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
            ->leftJoin(
                'diagnosis_assessments as da',
                'da.id',
                '=',
                'dar.diagnosis_assessment_id'
            )
            ->select([
                'contact_requests.*',
                'dar.public_id as workflow_public_id',
                'dar.status as workflow_status',
                'dar.user_id as workflow_user_id',
                'dar.diagnosis_assessment_id as workflow_assessment_id',
                'da.is_active as assessment_is_active',
                'da.inactivated_at as assessment_inactivated_at',
                'da.superseded_by_assessment_id as assessment_superseded_by_assessment_id',
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
                WHEN dar.status = 'inactive' THEN 8
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
                    'assessment_is_active' =>
                        $contact->assessment_is_active !== null
                            ? (bool) $contact->assessment_is_active
                            : null,
                    'assessment_inactivated_at' =>
                        $contact->assessment_inactivated_at
                            ? (string) $contact->assessment_inactivated_at
                            : null,
                    'assessment_superseded_by_assessment_id' =>
                        $contact->assessment_superseded_by_assessment_id !== null
                            ? (int) $contact->assessment_superseded_by_assessment_id
                            : null,
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
        DiagnosisAccessService $service,
        DiagnosisTransformationProgressService $progressService,
        DiagnosisDeliverableValidationService $validations
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

        $companyId = (int) (
            data_get($workflow?->meta, 'company_id')
            ?: ($assessment?->organization_id ?? 0)
        );

        $diagnosisCompany = $companyId > 0
            ? \App\Models\Company::query()
                ->with('diagnosisSetting')
                ->find($companyId)
            : null;

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
                'inactivated_at' =>
                    $workflow->inactivated_at?->toISOString(),
                'inactivated_by_user_id' =>
                    $workflow->inactivated_by_user_id !== null
                        ? (int) $workflow->inactivated_by_user_id
                        : null,
                'inactivation_reason' =>
                    $workflow->inactivation_reason,
                'status_before_inactivation' =>
                    $workflow->status_before_inactivation,
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
                'initial_diagnosis' => data_get(
                    $workflow->meta,
                    'initial_diagnosis'
                ),
                'source' => data_get($workflow->meta, 'source'),
                'user' => $workflow->user,
                'assessment' => $assessment ? [
                    'id' => $assessment->id,
                    'organization_name' => $assessment->organization_name,
                    'status' => $assessment->status,
                    'is_active' => (bool) $assessment->is_active,
                    'inactivated_at' =>
                        $assessment->inactivated_at?->toISOString(),
                    'superseded_by_assessment_id' =>
                        $assessment->superseded_by_assessment_id !== null
                            ? (int) $assessment->superseded_by_assessment_id
                            : null,
                    'current_step' => $assessment->current_step,
                    'answers' => $assessment->answers ?? [],
                    'notes' => $assessment->notes ?? [],
                    'maturity_score' => $assessment->maturity_score,
                    'capacity_score' => $assessment->capacity_score,
                    'urgency_score' => $assessment->urgency_score,
                    'dimension_scores' => $assessment->dimension_scores ?? [],
                    'maturity_level' => $assessment->maturity_level,
                    'urgency_level' => $assessment->urgency_level,
                    'review_required' => (bool) $assessment->review_required,
                    'review_summary' => $assessment->review_summary,
                    'review_priorities' => $assessment
                        ->review_priorities ?? [],
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
            'diagnosis_request_control' =>
                $diagnosisCompany ? [
                    'company_id' =>
                        (int) $diagnosisCompany->id,
                    'company_name' =>
                        $diagnosisCompany->name,
                    'new_requests_blocked' =>
                        (bool) (
                            $diagnosisCompany
                                ->diagnosisSetting
                                ?->new_requests_blocked
                            ?? false
                        ),
                    'blocked_at' =>
                        $diagnosisCompany
                            ->diagnosisSetting
                            ?->blocked_at
                            ?->toISOString(),
                    'blocked_by_user_id' =>
                        $diagnosisCompany
                            ->diagnosisSetting
                            ?->blocked_by_user_id,
                    'block_reason' =>
                        $diagnosisCompany
                            ->diagnosisSetting
                            ?->block_reason,
                ] : null,
            'businessProfileOptions' => config(
                'lauda360_business_profile',
                []
            ),
            'transformation_progress' =>
                $assessment
                    ? $progressService->forAssessment(
                        $assessment,
                        true
                    )
                    : null,
            'document_closure' =>
                $assessment
                    ? $validations->closureForAssessment($assessment)
                    : null,
        ]);
    }

    public function updateStatus(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $service,
        DiagnosisAssessmentCompletenessService $completeness
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

        $previousStatus = $workflow->status;

        $workflow->forceFill([
            'status' => $data['status'],
            'review_notes' => $data['review_notes']
                ?? $workflow->review_notes,
            'reviewed_by_user_id' => $request->user()->id,
        ])->save();

        AuditService::log(
            'diagnosis_access_status_changed',
            $workflow,
            [
                'previous_status' => $previousStatus,
                'status' => $workflow->status,
                'reviewed_by_user_id' =>
                    $request->user()->id,
            ]
        );

        if (
            $workflow->status
            === DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED
            && $previousStatus
                !== DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED
        ) {
            $workflow->loadMissing([
                'user',
                'assessment',
            ]);

            $missingInformation =
                $workflow->assessment
                    ? $completeness->missingInformation(
                        $workflow->assessment
                    )
                    : [
                        'business_profile' => [],
                        'missing_answers' => [],
                        'profile_complete' => false,
                        'answers_complete' => false,
                        'complete' => false,
                    ];

            if ($workflow->user?->email) {
                Mail::to($workflow->user->email)->queue(
                    new DiagnosisMoreInfoRequiredMail(
                        $workflow,
                        $missingInformation,
                        $workflow->review_notes
                    )
                );

                AuditService::log(
                    'diagnosis_more_info_email_queued',
                    $workflow,
                    [
                        'recipient' =>
                            $workflow->user->email,
                        'assessment_id' =>
                            $workflow->diagnosis_assessment_id,
                        'business_profile_items' =>
                            count(
                                $missingInformation[
                                    'business_profile'
                                ] ?? []
                            ),
                        'missing_answer_count' =>
                            count(
                                $missingInformation[
                                    'missing_answers'
                                ] ?? []
                            ),
                    ]
                );
            }
        }

        return back()->with(
            'success',
            'Estado del diagnóstico actualizado.'
        );
    }

    public function deleteAssessment(
        Request $request,
        ContactRequest $contact,
        DiagnosisAssessmentDeletionService $deletion
    ): RedirectResponse {
        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->firstOrFail();

        if (!$workflow->diagnosis_assessment_id) {
            abort(
                422,
                'La solicitud no tiene un diagnóstico asociado para eliminar.'
            );
        }

        $assessment = $workflow->assessment()->firstOrFail();

        $assessmentId = $assessment->id;

        AuditService::log(
            'diagnosis_assessment_deletion_requested',
            $assessment,
            [
                'workflow_id' => $workflow->id,
                'contact_request_id' => $workflow->contact_request_id,
                'actor_user_id' => $request->user()?->id,
            ]
        );

        $deletion->delete($assessment);

        return back()->with(
            'success',
            "Diagnóstico #{$assessmentId} eliminado correctamente."
        );
    }

    public function inactivateAssessment(
        Request $request,
        ContactRequest $contact
    ): RedirectResponse {
        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->firstOrFail();

        if (!$workflow->diagnosis_assessment_id) {
            abort(
                422,
                'La solicitud no tiene un diagnóstico asociado para inactivar.'
            );
        }

        DB::transaction(function () use ($workflow, $request): void {
            $assessment = $workflow->assessment()
                ->lockForUpdate()
                ->firstOrFail();

            if (!$assessment->is_active) {
                return;
            }

            $assessment->forceFill([
                'is_active' => false,
                'inactivated_at' => now(),
            ])->save();

            AuditService::log(
                'diagnosis_assessment_inactivated',
                $assessment,
                [
                    'workflow_id' => $workflow->id,
                    'contact_request_id' => $workflow->contact_request_id,
                    'actor_user_id' => $request->user()?->id,
                    'published_at' => $assessment->published_at?->toISOString(),
                    'superseded_by_assessment_id' =>
                        $assessment->superseded_by_assessment_id,
                ]
            );
        });

        return back()->with(
            'success',
            'Diagnóstico inactivado correctamente.'
        );
    }

    public function reactivateAssessment(
        Request $request,
        ContactRequest $contact
    ): RedirectResponse {
        $workflow = DiagnosisAccessRequest::query()
            ->where('contact_request_id', $contact->id)
            ->firstOrFail();

        if (!$workflow->diagnosis_assessment_id) {
            abort(
                422,
                'La solicitud no tiene un diagnóstico asociado para reactivar.'
            );
        }

        DB::transaction(function () use ($workflow, $request): void {
            $assessment = $workflow->assessment()
                ->lockForUpdate()
                ->firstOrFail();

            if ($assessment->is_active) {
                return;
            }

            if ($assessment->superseded_by_assessment_id !== null) {
                abort(
                    422,
                    'No se puede reactivar un diagnóstico que ya fue sustituido por uno posterior.'
                );
            }

            $assessment->forceFill([
                'is_active' => true,
                'inactivated_at' => null,
            ])->save();

            AuditService::log(
                'diagnosis_assessment_reactivated',
                $assessment,
                [
                    'workflow_id' => $workflow->id,
                    'contact_request_id' => $workflow->contact_request_id,
                    'actor_user_id' => $request->user()?->id,
                    'published_at' => $assessment->published_at?->toISOString(),
                ]
            );
        });

        return back()->with(
            'success',
            'Diagnóstico reactivado correctamente.'
        );
    }

    public function inactivateRequest(
        Request $request,
        ContactRequest $contact
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(
            function () use (
                $contact,
                $request,
                $data
            ): void {
                $workflow =
                    DiagnosisAccessRequest::query()
                        ->where(
                            'contact_request_id',
                            $contact->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $workflow->status
                    === DiagnosisAccessRequest::STATUS_INACTIVE
                ) {
                    return;
                }

                $previousStatus = $workflow->status;

                $workflow->forceFill([
                    'status' =>
                        DiagnosisAccessRequest::STATUS_INACTIVE,
                    'status_before_inactivation' =>
                        $previousStatus,
                    'inactivated_at' => now(),
                    'inactivated_by_user_id' =>
                        $request->user()->id,
                    'inactivation_reason' =>
                        $data['reason'] ?? null,
                ])->save();

                AuditService::log(
                    'diagnosis_access_request_inactivated',
                    $workflow,
                    [
                        'previous_status' =>
                            $previousStatus,
                        'reason' =>
                            $data['reason'] ?? null,
                        'actor_user_id' =>
                            $request->user()->id,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Solicitud de diagnóstico inactivada correctamente.'
        );
    }

    public function reactivateRequest(
        Request $request,
        ContactRequest $contact
    ): RedirectResponse {
        DB::transaction(
            function () use (
                $contact,
                $request
            ): void {
                $workflow =
                    DiagnosisAccessRequest::query()
                        ->where(
                            'contact_request_id',
                            $contact->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $workflow->status
                    !== DiagnosisAccessRequest::STATUS_INACTIVE
                ) {
                    return;
                }

                $restoreStatus =
                    $workflow->status_before_inactivation;

                if (
                    ! in_array(
                        $restoreStatus,
                        DiagnosisAccessRequest::STATUSES,
                        true
                    )
                    || $restoreStatus
                        === DiagnosisAccessRequest::STATUS_INACTIVE
                ) {
                    $restoreStatus =
                        DiagnosisAccessRequest::STATUS_PENDING;
                }

                /*
                 * An active workflow without assessment is not a
                 * valid active access. Reactivating that historical
                 * row makes it a pending administrative request.
                 */
                if (
                    $restoreStatus
                        === DiagnosisAccessRequest::STATUS_ACTIVE
                    && ! $workflow->diagnosis_assessment_id
                ) {
                    $restoreStatus =
                        DiagnosisAccessRequest::STATUS_PENDING;
                }

                $workflow->forceFill([
                    'status' => $restoreStatus,
                    'status_before_inactivation' => null,
                    'inactivated_at' => null,
                    'inactivated_by_user_id' => null,
                    'inactivation_reason' => null,
                ])->save();

                AuditService::log(
                    'diagnosis_access_request_reactivated',
                    $workflow,
                    [
                        'restored_status' =>
                            $restoreStatus,
                        'actor_user_id' =>
                            $request->user()->id,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Solicitud de diagnóstico reactivada correctamente.'
        );
    }

    public function blockNewDiagnosisRequests(
        Request $request,
        ContactRequest $contact
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $workflow =
            DiagnosisAccessRequest::query()
                ->where(
                    'contact_request_id',
                    $contact->id
                )
                ->with('assessment')
                ->firstOrFail();

        $companyId = (int) (
            data_get($workflow->meta, 'company_id')
            ?: ($workflow->assessment?->organization_id ?? 0)
        );

        abort_if(
            $companyId <= 0,
            422,
            'No fue posible determinar la empresa de esta solicitud.'
        );

        DB::transaction(
            function () use (
                $companyId,
                $request,
                $data
            ): void {
                $company =
                    \App\Models\Company::query()
                        ->whereKey($companyId)
                        ->lockForUpdate()
                        ->firstOrFail();

                $setting =
                    \App\Models\CompanyDiagnosisSetting::query()
                        ->firstOrNew([
                            'company_id' => $company->id,
                        ]);

                if (
                    (bool) $setting->new_requests_blocked
                ) {
                    return;
                }

                $setting->forceFill([
                    'new_requests_blocked' => true,
                    'blocked_at' => now(),
                    'blocked_by_user_id' =>
                        $request->user()->id,
                    'block_reason' =>
                        $data['reason'] ?? null,
                ])->save();

                AuditService::log(
                    'diagnosis_new_requests_blocked',
                    $company,
                    [
                        'reason' =>
                            $data['reason'] ?? null,
                        'actor_user_id' =>
                            $request->user()->id,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Nuevas solicitudes de Diagnóstico 360 bloqueadas para la empresa.'
        );
    }

    public function unblockNewDiagnosisRequests(
        Request $request,
        ContactRequest $contact
    ): RedirectResponse {
        $workflow =
            DiagnosisAccessRequest::query()
                ->where(
                    'contact_request_id',
                    $contact->id
                )
                ->with('assessment')
                ->firstOrFail();

        $companyId = (int) (
            data_get($workflow->meta, 'company_id')
            ?: ($workflow->assessment?->organization_id ?? 0)
        );

        abort_if(
            $companyId <= 0,
            422,
            'No fue posible determinar la empresa de esta solicitud.'
        );

        DB::transaction(
            function () use (
                $companyId,
                $request
            ): void {
                $company =
                    \App\Models\Company::query()
                        ->whereKey($companyId)
                        ->lockForUpdate()
                        ->firstOrFail();

                $setting =
                    \App\Models\CompanyDiagnosisSetting::query()
                        ->where(
                            'company_id',
                            $company->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $setting
                    || ! (bool) $setting
                        ->new_requests_blocked
                ) {
                    return;
                }

                $previousReason =
                    $setting->block_reason;

                $setting->forceFill([
                    'new_requests_blocked' => false,
                    'blocked_at' => null,
                    'blocked_by_user_id' => null,
                    'block_reason' => null,
                ])->save();

                AuditService::log(
                    'diagnosis_new_requests_unblocked',
                    $company,
                    [
                        'previous_reason' =>
                            $previousReason,
                        'actor_user_id' =>
                            $request->user()->id,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Nuevas solicitudes de Diagnóstico 360 habilitadas para la empresa.'
        );
    }

    public function approve(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $service
    ): RedirectResponse {
        $workflow = $service->approve(
            $contact,
            $request->user()
        );

        $native =
            data_get($workflow->meta, 'source')
            === \App\Services\Diagnosis\InitialDiagnosisCommercialService::SOURCE;

        return back()->with(
            'success',
            $native
                ? 'Solicitud confirmada. La factura RD$0.00 queda como evidencia de cortesía y el Diagnóstico 360 fue habilitado en App Hub.'
                : 'Solicitud aprobada e invitación de diagnóstico enviada.'
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
