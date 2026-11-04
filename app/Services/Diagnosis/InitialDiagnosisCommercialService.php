<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Historical class/SOURCE retained for existing consumers; this flow only manages free access. */
final class InitialDiagnosisCommercialService
{
    public const SOURCE = 'lauda360_initial_diagnosis';
    public const INTENT = 'diagnosis_360';

    public function offer(): array
    {
        return [
            'code' => self::SOURCE,
            'name' => 'Diagnóstico Inicial LAUDA 360',
            'free' => true,
            'invoice_required' => false,
            'payment_required' => false,
            'subscription_required' => false,
            'modality_required' => false,
            'contract_required' => false,
            'manual_confirmation_required' => true,
        ];
    }

    /** @return array{0: Subscriber, 1: Company, 2: string} */
    public function tenantContext(User $user): array
    {
        $subscriber = $user->activeSubscribers()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->orderBy('subscribers.id')
            ->first();

        if (! $subscriber) {
            throw ValidationException::withMessages([
                'tenant' => ['Solo owner/admin puede solicitar el Diagnóstico 360.'],
            ]);
        }

        $company = Company::query()
            ->where('subscriber_id', $subscriber->id)
            ->where('active', true)
            ->orderBy('id')
            ->first();

        if (! $company) {
            throw ValidationException::withMessages([
                'company' => ['Completa primero el Perfil de Empresa.'],
            ]);
        }

        $role = $subscriber->users()
            ->whereKey($user->id)
            ->first()
            ?->pivot
            ?->role;

        if (! in_array($role, ['owner', 'admin'], true)) {
            throw ValidationException::withMessages([
                'tenant' => ['Solo owner/admin puede solicitar el Diagnóstico 360.'],
            ]);
        }

        return [$subscriber, $company, (string) $role];
    }

    /**
     * Idempotent initializer used by onboarding. It never starts a second
     * assessment when the company already has an active one.
     */
    public function ensure(User $user): DiagnosisAccessRequest
    {
        return $this->ensureForUser($user, false);
    }

    /**
     * Explicit tenant action. If an assessment is already active, this creates
     * one pending replacement request. Repeated clicks reuse that request.
     */
    public function request(User $user): DiagnosisAccessRequest
    {
        return $this->ensureForUser($user, true);
    }

    private function ensureForUser(
        User $user,
        bool $allowReassessment
    ): DiagnosisAccessRequest {
        [$subscriber, $company] = $this->tenantContext($user);
        $offer = $this->offer();

        return DB::transaction(function () use (
            $user,
            $subscriber,
            $company,
            $offer,
            $allowReassessment
        ): DiagnosisAccessRequest {
            $company = Company::query()
                ->whereKey($company->id)
                ->lockForUpdate()
                ->firstOrFail();

            $pending = $this->pendingNativeWorkflowForCompany($company);

            if ($pending) {
                return $this->prepareNativeWorkflow(
                    $pending,
                    $user,
                    $subscriber,
                    $company,
                    $offer
                );
            }

            $activeWorkflow = $this->activeNativeWorkflowForCompany($company);
            $historical = null;

            if (! $activeWorkflow) {
                $historical = $this->activeHistoricalWorkflow($user, $company);
            }

            $activeAssessment =
                $activeWorkflow?->assessment
                ?? $historical?->assessment;

            if ($activeAssessment && ! $allowReassessment) {
                return $activeWorkflow ?? $historical;
            }

            $requestControl =
                \App\Models\CompanyDiagnosisSetting::query()
                    ->where('company_id', $company->id)
                    ->first();

            if (
                (bool) (
                    $requestControl?->new_requests_blocked
                    ?? false
                )
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'diagnosis' => [
                        $requestControl?->block_reason
                            ?: 'LAUDA ha bloqueado temporalmente nuevas solicitudes de Diagnóstico 360 para esta empresa.',
                    ],
                ]);
            }

            $workflow = $this->createNativeWorkflow(
                $user,
                $subscriber,
                $company,
                $activeAssessment?->id
            );

            return $this->prepareNativeWorkflow(
                $workflow,
                $user,
                $subscriber,
                $company,
                $offer
            );
        });
    }

    private function createNativeWorkflow(
        User $user,
        Subscriber $subscriber,
        Company $company,
        ?int $supersedesAssessmentId
    ): DiagnosisAccessRequest {
        $profile = CompanyTaxProfile::query()
            ->where('company_id', $company->id)
            ->first();

        $isReassessment = $supersedesAssessmentId !== null;

        $contact = ContactRequest::query()->create([
            'name' => $user->name,
            'email' => strtolower(trim((string) $user->email)),
            'phone' => $profile?->billing_phone,
            'company' => $company->name,
            'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
            'message' => $isReassessment
                ? 'Solicitud de nueva evaluación iniciada desde app.laudaapi.com.'
                : 'Solicitud iniciada desde app.laudaapi.com.',
            'terms' => true,
            'metadata' => [
                'request_type' => 'digital_diagnosis_access_request',
                'source' => 'apphub_native',
                'apphub_user_id' => $user->id,
                'subscriber_id' => $subscriber->id,
                'company_id' => $company->id,
                'complimentary' => true,
                'reassessment' => $isReassessment,
                'supersedes_assessment_id' => $supersedesAssessmentId,
            ],
        ]);

        $workflow = DiagnosisAccessRequest::query()->create([
            'contact_request_id' => $contact->id,
            'user_id' => $user->id,
            'status' => DiagnosisAccessRequest::STATUS_PENDING,
            'meta' => [
                'source' => self::SOURCE,
                'apphub_native' => true,
                'subscriber_id' => $subscriber->id,
                'company_id' => $company->id,
                'requested_at' => now()->toIso8601String(),
                'reassessment' => $isReassessment,
                'supersedes_assessment_id' => $supersedesAssessmentId,
            ],
        ]);

        AuditService::log(
            $isReassessment
                ? 'diagnosis_reassessment_apphub_requested'
                : 'diagnosis_initial_apphub_requested',
            $workflow,
            [
                'user_id' => $user->id,
                'subscriber_id' => $subscriber->id,
                'company_id' => $company->id,
                'supersedes_assessment_id' => $supersedesAssessmentId,
            ],
            ['user_id' => $user->id]
        );

        return $workflow;
    }

    private function prepareNativeWorkflow(
        DiagnosisAccessRequest $workflow,
        User $user,
        Subscriber $subscriber,
        Company $company,
        array $offer
    ): DiagnosisAccessRequest {
        if ($workflow->contact_request_id) {
            $contact = ContactRequest::query()
                ->whereKey($workflow->contact_request_id)
                ->first();

            if ($contact) {
                $contactMeta = is_array($contact->metadata)
                    ? $contact->metadata
                    : [];

                $contact->forceFill([
                    'metadata' => array_merge(
                        $contactMeta,
                        [
                            'request_type' =>
                                'digital_diagnosis_access_request',
                            'apphub_user_id' => $user->id,
                            'subscriber_id' => $subscriber->id,
                            'company_id' => $company->id,
                            'complimentary' => true,
                            'reassessment' => (bool) data_get(
                                $workflow->meta,
                                'reassessment',
                                false
                            ),
                            'supersedes_assessment_id' => data_get(
                                $workflow->meta,
                                'supersedes_assessment_id'
                            ),
                        ]
                    ),
                ])->save();
            }
        }

        $meta = is_array($workflow->meta) ? $workflow->meta : [];
        // Preserve any historical invoice references in meta without reading or changing fiscal records.
        $meta['source'] = self::SOURCE;
        $meta['apphub_native'] = true;
        $meta['subscriber_id'] = $subscriber->id;
        $meta['company_id'] = $company->id;
        $meta['initial_diagnosis'] = array_merge(
            is_array($meta['initial_diagnosis'] ?? null)
                ? $meta['initial_diagnosis']
                : [],
            [
                ...$offer,
                'confirmation_status' => data_get(
                    $meta,
                    'initial_diagnosis.confirmation_status',
                    'pending'
                ),
            ]
        );

        $workflow->forceFill([
            'user_id' => $user->id,
            'meta' => $meta,
        ])->save();

        return $workflow->fresh(['contactRequest', 'assessment']);
    }

    private function pendingNativeWorkflowForCompany(
        Company $company
    ): ?DiagnosisAccessRequest {
        return DiagnosisAccessRequest::query()
            ->where('meta->source', self::SOURCE)
            ->where('meta->company_id', $company->id)
            ->whereNull('diagnosis_assessment_id')
            ->whereNotIn('status', [
                DiagnosisAccessRequest::STATUS_ACTIVE,
                DiagnosisAccessRequest::STATUS_INACTIVE,
                DiagnosisAccessRequest::STATUS_REJECTED,
            ])
            ->with('assessment')
            ->latest('id')
            ->first();
    }

    private function activeNativeWorkflowForCompany(
        Company $company
    ): ?DiagnosisAccessRequest {
        /*
         * The official diagnosis is the latest accessible published
         * assessment. A newer draft reassessment must never hide it.
         */
        $published = DiagnosisAccessRequest::query()
            ->where('meta->source', self::SOURCE)
            ->where('meta->company_id', $company->id)
            ->whereNotNull('diagnosis_assessment_id')
            ->whereHas(
                'assessment',
                fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotNull('published_at')
            )
            ->with('assessment')
            ->get()
            ->sortByDesc(
                fn (DiagnosisAccessRequest $workflow) =>
                    $workflow->assessment?->published_at?->getTimestamp() ?? 0
            )
            ->first();

        if ($published) {
            return $published;
        }

        /*
         * First-cycle compatibility: before the tenant has ever
         * published a diagnosis, expose its accessible assessment so
         * onboarding and diagnosis completion continue to work.
         */
        return DiagnosisAccessRequest::query()
            ->where('meta->source', self::SOURCE)
            ->where('meta->company_id', $company->id)
            ->where(
                'status',
                '!=',
                DiagnosisAccessRequest::STATUS_INACTIVE
            )
            ->whereNotNull('diagnosis_assessment_id')
            ->whereHas(
                'assessment',
                fn ($query) => $query->where('is_active', true)
            )
            ->with('assessment')
            ->oldest('id')
            ->first();
    }

    private function workingNativeWorkflowForCompany(
        Company $company,
        ?DiagnosisAccessRequest $official = null
    ): ?DiagnosisAccessRequest {
        return DiagnosisAccessRequest::query()
            ->where('meta->source', self::SOURCE)
            ->where('meta->company_id', $company->id)
            ->where(
                'status',
                '!=',
                DiagnosisAccessRequest::STATUS_INACTIVE
            )
            ->whereNotNull('diagnosis_assessment_id')
            ->when(
                $official?->diagnosis_assessment_id,
                fn ($query, $assessmentId) =>
                    $query->where(
                        'diagnosis_assessment_id',
                        '!=',
                        $assessmentId
                    )
            )
            ->whereHas(
                'assessment',
                fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('published_at')
            )
            ->with('assessment')
            ->latest('id')
            ->first();
    }

    private function activeHistoricalWorkflow(
        User $user,
        Company $company
    ): ?DiagnosisAccessRequest {
        return DiagnosisAccessRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('diagnosis_assessment_id')
            ->where(function ($query): void {
                $query->whereNull('meta->source')
                    ->orWhere('meta->source', '!=', self::SOURCE);
            })
            ->where(function ($query) use ($company): void {
                $query->where('meta->company_id', $company->id)
                    ->orWhereHas(
                        'assessment',
                        fn ($assessmentQuery) => $assessmentQuery
                            ->where('organization_id', $company->id)
                    )
                    ->orWhereHas(
                        'contactRequest',
                        fn ($contactQuery) => $contactQuery
                            ->where('metadata->company_id', $company->id)
                    );
            })
            ->whereHas(
                'assessment',
                fn ($query) => $query->where('is_active', true)
            )
            ->with('assessment')
            ->latest('id')
            ->first();
    }

    public function nativeWorkflow(User $user): ?DiagnosisAccessRequest
    {
        return DiagnosisAccessRequest::query()
            ->where('user_id', $user->id)
            ->where('meta->source', self::SOURCE)
            ->with('assessment')
            ->latest('id')
            ->first();
    }

    public function historicalWorkflow(User $user): ?DiagnosisAccessRequest
    {
        return DiagnosisAccessRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('diagnosis_assessment_id')
            ->whereHas(
                'assessment',
                fn ($query) => $query->where('is_active', true)
            )
            ->with('assessment')
            ->latest('id')
            ->first();
    }

    public function state(User $user, Company $company): array
    {
        $requestControl =
            \App\Models\CompanyDiagnosisSetting::query()
                ->where('company_id', $company->id)
                ->first();

        $requestBlocked =
            (bool) (
                $requestControl?->new_requests_blocked
                ?? false
            );

        $pending = $this->pendingNativeWorkflowForCompany($company);
        $activeWorkflow = $this->activeNativeWorkflowForCompany($company);
        $historical = false;

        if (! $activeWorkflow) {
            $activeWorkflow = $this->activeHistoricalWorkflow($user, $company);
            $historical = (bool) $activeWorkflow;
        }

        $assessment = $activeWorkflow?->assessment;

        $workingWorkflow = $this->workingNativeWorkflowForCompany(
            $company,
            $activeWorkflow
        );

        $workflow = $pending ?? $workingWorkflow ?? $activeWorkflow;
        $offer = $this->offer();

        if (! $workflow && ! $assessment) {
            return [
                'exists' => false,
                'historical' => false,
                'needs_initialization' => true,
                'can_request_new' => false,
                'request_blocked' => $requestBlocked,
                'request_block_reason' =>
                    $requestControl?->block_reason,
                'reassessment_pending' => false,
                'working_assessment' => null,
                'workflow' => null,
                'assessment' => null,
                'offer' => $offer,
            ];
        }

        $reassessmentPending =
            $assessment !== null
            && ($pending !== null || $workingWorkflow !== null);

        return [
            'exists' => true,
            'historical' => $historical,
            'needs_initialization' => false,
            'can_request_new' =>
                $assessment !== null
                && $pending === null
                && $workingWorkflow === null
                && ! $requestBlocked,
            'request_blocked' => $requestBlocked,
            'request_block_reason' =>
                $requestControl?->block_reason,
            'reassessment_pending' => $reassessmentPending,
            'working_assessment' => $workingWorkflow?->assessment ? [
                'id' => $workingWorkflow->assessment->id,
                'status' => $workingWorkflow->assessment->status,
                'is_active' =>
                    (bool) $workingWorkflow->assessment->is_active,
                'current_step' =>
                    $workingWorkflow->assessment->current_step,
                'submitted_at' =>
                    $workingWorkflow->assessment
                        ->submitted_at?->toIso8601String(),
                'published_at' =>
                    $workingWorkflow->assessment
                        ->published_at?->toIso8601String(),
                'url' => route(
                    'diagnosis.show',
                    $workingWorkflow->assessment,
                    false
                ),
            ] : null,
            'workflow' => $workflow ? [
                'public_id' => $workflow->public_id,
                'status' => $workflow->status,
                'confirmation_status' => (string) data_get(
                    $workflow->meta,
                    'initial_diagnosis.confirmation_status',
                    $workflow->status === DiagnosisAccessRequest::STATUS_ACTIVE
                        ? 'confirmed'
                        : 'pending'
                ),
                'requested_at' => data_get($workflow->meta, 'requested_at'),
                'confirmed_at' => data_get(
                    $workflow->meta,
                    'initial_diagnosis.confirmed_at'
                ),
            ] : null,
            'assessment' => $assessment ? [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'is_active' => (bool) $assessment->is_active,
                'current_step' => $assessment->current_step,
                'submitted_at' => $assessment->submitted_at?->toIso8601String(),
                'published_at' => $assessment->published_at?->toIso8601String(),
                'url' => route('diagnosis.show', $assessment, false),
            ] : null,
            'offer' => $offer,
        ];
    }
}
