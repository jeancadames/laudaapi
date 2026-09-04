<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\TransformationImplementationPhase;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\TransformationImplementationExecutionService;
use App\Services\Diagnosis\TransformationImplementationGoLiveService;
use App\Services\Diagnosis\TransformationImplementationPostGoLiveSubscriptionService;
use App\Services\Diagnosis\TransformationImplementationPostGoLiveServiceActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTransformationImplementationExecutionController extends Controller
{
    public function show(
        ContactRequest $contact,
        DiagnosisAccessService $accessService
    ): Response {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $plan->load([
            'phases' => fn ($query) => $query->orderBy('sequence'),
            'phases.capabilities' => fn ($query) =>
                $query->orderBy('sequence'),
            'phases.execution',
            'phases.capabilities.execution',
            'phases.capabilities.latestGoLive',
        ]);

        $subscriptionActivations =
            \App\Models\TransformationImplementationSubscriptionActivation::query()
                ->whereIn(
                    'transformation_implementation_capability_go_live_id',
                    $plan->phases
                        ->flatMap(fn ($phase) =>
                            $phase->capabilities
                        )
                        ->pluck('latestGoLive.id')
                        ->filter()
                        ->values()
                )
                ->with([
                    'subscriber:id,name,currency',
                    'company:id,name,currency,subscriber_id',
                    'subscription:id,status,billing_cycle,currency,subtotal_amount,discount_amount,tax_amount,total_amount,starts_at,current_period_end',
                ])
                ->get()
                ->keyBy(
                    'transformation_implementation_capability_go_live_id'
                );

        $serviceActivations =
            \App\Models\TransformationImplementationSubscriptionItemActivation::query()
                ->whereIn(
                    'transformation_implementation_capability_go_live_id',
                    $plan->phases
                        ->flatMap(fn ($phase) =>
                            $phase->capabilities
                        )
                        ->pluck('latestGoLive.id')
                        ->filter()
                        ->values()
                )
                ->with([
                    'service:id,key,name',
                    'subscriptionItem:id,subscription_id,service_id,status,billing_model,quantity,unit_price,amount,currency,block_size,included_units,unit_name',
                ])
                ->get()
                ->keyBy(
                    'transformation_implementation_capability_go_live_id'
                );

        return Inertia::render(
            'Admin/DiagnosisRequests/ImplementationExecution',
            [
                'contact' => [
                    'id' => $contact->id,
                    'company' => $contact->company,
                ],
                'assessment' => [
                    'id' => $plan->diagnosis_assessment_id,
                    'organization_name' =>
                        $plan->assessment?->organization_name,
                ],
                'plan' => [
                    'id' => $plan->id,
                    'version' => $plan->version,
                    'status' => $plan->status,
                    'selected_modality_label' =>
                        $plan->selected_modality_label,
                    'accepted_at' =>
                        $plan->accepted_at?->toISOString(),
                    'phases' => $plan->phases->map(
                        fn (TransformationImplementationPhase $phase) =>
                            $this->serializePhase(
                                $phase,
                                $subscriptionActivations,
                                $serviceActivations
                            )
                    )->values(),
                ],
                'endpoints' => [
                    'back' => route(
                        'admin.diagnosis_requests.implementation_plan.show',
                        $contact
                    ),
                    'base' =>
                        "/admin/diagnosis-requests/{$contact->id}/implementation-plan/execution",
                ],
            ]
        );
    }

    public function initializePhase(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPhase $phase,
        DiagnosisAccessService $accessService,
        TransformationImplementationExecutionService $executionService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertPhaseBelongsToPlan(
            $phase,
            $plan
        );

        $executionService->initializePhase(
            $phase->fresh(),
            $request->user()?->id
        );

        return $this->backToExecution(
            $contact,
            'Ejecución de la fase inicializada.'
        );
    }

    public function startCapability(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPhaseCapability $capability,
        DiagnosisAccessService $accessService,
        TransformationImplementationExecutionService $executionService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertCapabilityBelongsToPlan(
            $capability,
            $plan
        );

        $executionService->startCapability(
            $capability->fresh(),
            $request->user()?->id,
            $request->user()?->id
        );

        return $this->backToExecution(
            $contact,
            'Capability iniciada.'
        );
    }

    public function updateProgress(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPhaseCapability $capability,
        DiagnosisAccessService $accessService,
        TransformationImplementationExecutionService $executionService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertCapabilityBelongsToPlan(
            $capability,
            $plan
        );

        $validated = $request->validate([
            'progress_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:99',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $executionService->updateCapabilityProgress(
            $capability->fresh(),
            (float) $validated['progress_percentage'],
            $request->user()?->id,
            [
                'source' => 'admin_execution_ui',
                'updated_at' => now()->toISOString(),
            ],
            $validated['notes'] ?? null
        );

        return $this->backToExecution(
            $contact,
            'Progreso actualizado.'
        );
    }

    public function completeCapability(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPhaseCapability $capability,
        DiagnosisAccessService $accessService,
        TransformationImplementationExecutionService $executionService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertCapabilityBelongsToPlan(
            $capability,
            $plan
        );

        $validated = $request->validate([
            'completion_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $executionService->completeCapability(
            $capability->fresh(),
            $request->user()?->id,
            [
                'source' => 'admin_execution_ui',
                'completion_notes' =>
                    $validated['completion_notes'] ?? null,
                'completed_at' => now()->toISOString(),
            ]
        );

        return $this->backToExecution(
            $contact,
            'Capability completada al 100%.'
        );
    }

    public function createGoLive(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPhaseCapability $capability,
        DiagnosisAccessService $accessService,
        TransformationImplementationGoLiveService $goLiveService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertCapabilityBelongsToPlan(
            $capability,
            $plan
        );

        $existing = TransformationImplementationCapabilityGoLive::query()
            ->where(
                'transformation_implementation_phase_capability_id',
                $capability->id
            )
            ->whereNotIn(
                'status',
                [
                    TransformationImplementationCapabilityGoLive::STATUS_ROLLED_BACK,
                    TransformationImplementationCapabilityGoLive::STATUS_CANCELLED,
                ]
            )
            ->latest('attempt')
            ->first();

        if ($existing) {
            return $this->backToExecution(
                $contact,
                'Ya existe un intento de Go-Live activo para esta capability.',
                'warning'
            );
        }

        $goLiveService->createAttempt(
            $capability->fresh(),
            $request->user()?->id
        );

        return $this->backToExecution(
            $contact,
            'Intento de Go-Live creado.'
        );
    }

    public function markGoLiveReady(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationCapabilityGoLive $goLive,
        DiagnosisAccessService $accessService,
        TransformationImplementationGoLiveService $goLiveService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertGoLiveBelongsToPlan(
            $goLive,
            $plan
        );

        $validated = $request->validate([
            'technical_readiness' => [
                'accepted',
            ],
            'operational_readiness' => [
                'accepted',
            ],
            'client_readiness' => [
                'accepted',
            ],
            'readiness_notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $goLiveService->markReady(
            $goLive->fresh(),
            [
                'technical_readiness' => true,
                'operational_readiness' => true,
                'client_readiness' => true,
                'confirmed_by_user_id' =>
                    $request->user()?->id,
                'confirmed_at' => now()->toISOString(),
            ],
            $request->user()?->id,
            [
                'source' => 'admin_execution_ui',
                'notes' =>
                    $validated['readiness_notes'] ?? null,
            ]
        );

        return $this->backToExecution(
            $contact,
            'Readiness de Go-Live confirmado.'
        );
    }

    public function goLive(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationCapabilityGoLive $goLive,
        DiagnosisAccessService $accessService,
        TransformationImplementationGoLiveService $goLiveService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertGoLiveBelongsToPlan(
            $goLive,
            $plan
        );

        $validated = $request->validate([
            'go_live_notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $goLiveService->goLive(
            $goLive->fresh(),
            $request->user()?->id,
            [
                'source' => 'admin_execution_ui',
                'notes' =>
                    $validated['go_live_notes'] ?? null,
                'confirmed_at' => now()->toISOString(),
            ]
        );

        return $this->backToExecution(
            $contact,
            'Capability marcada como LIVE. La suscripción aún no ha sido activada.'
        );
    }

    public function activateSubscription(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationCapabilityGoLive $goLive,
        DiagnosisAccessService $accessService,
        TransformationImplementationPostGoLiveSubscriptionService $postGoLiveService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertGoLiveBelongsToPlan(
            $goLive,
            $plan
        );

        $validated = $request->validate([
            'billing_cycle' => [
                'required',
                'string',
                'in:monthly,yearly',
            ],
        ]);

        $activation =
            $postGoLiveService->activateSubscriptionForGoLive(
                $goLive->fresh(),
                $request->user()?->id,
                $validated['billing_cycle']
            );

        return $this->backToExecution(
            $contact,
            sprintf(
                'Go-Live vinculado a la Subscription general #%d (%s). Aún no se activó ningún Service.',
                (int) $activation->subscription_id,
                $activation->activation_type
            )
        );
    }

    public function activateService(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationCapabilityGoLive $goLive,
        DiagnosisAccessService $accessService,
        TransformationImplementationPostGoLiveServiceActivationService $postGoLiveService
    ): RedirectResponse {
        $plan = $this->acceptedPlanFor(
            $contact,
            $accessService
        );

        $this->assertGoLiveBelongsToPlan(
            $goLive,
            $plan
        );

        $activation =
            $postGoLiveService->activateServiceForGoLive(
                $goLive->fresh(),
                $request->user()?->id
            );

        $activation->loadMissing([
            'subscriptionItem',
            'service',
        ]);

        return $this->backToExecution(
            $contact,
            sprintf(
                'Service activado en SubscriptionItem #%d (%s).',
                (int) $activation->subscription_item_id,
                $activation->activation_type
            )
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

    private function acceptedPlanFor(
        ContactRequest $contact,
        DiagnosisAccessService $accessService
    ): TransformationImplementationPlan {
        $assessment = $this->assessmentFor(
            $contact,
            $accessService
        );

        $plan = TransformationImplementationPlan::query()
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->with('assessment')
            ->orderByDesc('version')
            ->firstOrFail();

        abort_unless(
            in_array(
                $plan->status,
                [
                    TransformationImplementationPlan::STATUS_ACCEPTED,
                    TransformationImplementationPlan::STATUS_ACTIVE,
                    TransformationImplementationPlan::STATUS_COMPLETED,
                ],
                true
            )
            && $plan->accepted_at !== null,
            422,
            'El Plan debe estar aceptado antes de iniciar ejecución.'
        );

        return $plan;
    }

    private function assertPhaseBelongsToPlan(
        TransformationImplementationPhase $phase,
        TransformationImplementationPlan $plan
    ): void {
        abort_unless(
            (int) $phase->transformation_implementation_plan_id
                === (int) $plan->id,
            404
        );
    }

    private function assertCapabilityBelongsToPlan(
        TransformationImplementationPhaseCapability $capability,
        TransformationImplementationPlan $plan
    ): void {
        $belongs = TransformationImplementationPhase::query()
            ->whereKey(
                $capability->transformation_implementation_phase_id
            )
            ->where(
                'transformation_implementation_plan_id',
                $plan->id
            )
            ->exists();

        abort_unless(
            $belongs,
            404
        );
    }

    private function assertGoLiveBelongsToPlan(
        TransformationImplementationCapabilityGoLive $goLive,
        TransformationImplementationPlan $plan
    ): void {
        $capability = TransformationImplementationPhaseCapability::query()
            ->findOrFail(
                $goLive->transformation_implementation_phase_capability_id
            );

        $this->assertCapabilityBelongsToPlan(
            $capability,
            $plan
        );
    }

    private function serializePhase(
        TransformationImplementationPhase $phase,
        $subscriptionActivations = null,
        $serviceActivations = null
    ): array {
        $subscriptionActivations ??= collect();
        $serviceActivations ??= collect();
        return [
            'id' => $phase->id,
            'sequence' => $phase->sequence,
            'name' => $phase->name,
            'objective' => $phase->objective,
            'execution' => $phase->execution ? [
                'id' => $phase->execution->id,
                'status' => $phase->execution->status,
                'progress_percentage' =>
                    (float) $phase->execution
                        ->progress_percentage,
            ] : null,
            'capabilities' => $phase->capabilities->map(
                fn (
                    TransformationImplementationPhaseCapability $capability
                ) => [
                    'id' => $capability->id,
                    'sequence' => $capability->sequence,
                    'capability_key' =>
                        $capability->capability_key,
                    'capability_label' =>
                        $capability->capability_label,
                    'kind' => data_get(
                        $capability->source_snapshot,
                        'kind'
                    ),
                    'activation_policy' => data_get(
                        $capability->source_snapshot,
                        'activation_policy'
                    ),
                    'commercial_readiness' => data_get(
                        $capability->source_snapshot,
                        'commercial_readiness'
                    ),
                    'execution' => $capability->execution ? [
                        'id' => $capability->execution->id,
                        'status' =>
                            $capability->execution->status,
                        'progress_percentage' =>
                            (float) $capability->execution
                                ->progress_percentage,
                        'started_at' =>
                            $capability->execution
                                ->started_at?->toISOString(),
                        'completed_at' =>
                            $capability->execution
                                ->completed_at?->toISOString(),
                    ] : null,
                    'go_live' => $capability->latestGoLive ? [
                        'id' =>
                            $capability->latestGoLive->id,
                        'attempt' =>
                            $capability->latestGoLive->attempt,
                        'status' =>
                            $capability->latestGoLive->status,
                        'ready_at' =>
                            $capability->latestGoLive
                                ->ready_at?->toISOString(),
                        'went_live_at' =>
                            $capability->latestGoLive
                                ->went_live_at?->toISOString(),
                        'subscription_activation' => (
                            $activation =
                                $subscriptionActivations->get(
                                    $capability->latestGoLive->id
                                )
                        ) ? [
                            'id' => $activation->id,
                            'activation_type' =>
                                $activation->activation_type,
                            'subscriber_id' =>
                                $activation->subscriber_id,
                            'company_id' =>
                                $activation->company_id,
                            'subscriber' =>
                                $activation->subscriber ? [
                                    'id' =>
                                        $activation->subscriber->id,
                                    'name' =>
                                        $activation->subscriber->name,
                                    'currency' =>
                                        $activation->subscriber->currency,
                                ] : null,
                            'company' =>
                                $activation->company ? [
                                    'id' =>
                                        $activation->company->id,
                                    'name' =>
                                        $activation->company->name,
                                    'currency' =>
                                        $activation->company->currency,
                                ] : null,
                            'subscription_id' =>
                                $activation->subscription_id,
                            'subscription' =>
                                $activation->subscription ? [
                                    'id' =>
                                        $activation
                                            ->subscription
                                            ->id,
                                    'status' =>
                                        $activation
                                            ->subscription
                                            ->status,
                                    'billing_cycle' =>
                                        $activation
                                            ->subscription
                                            ->billing_cycle,
                                    'currency' =>
                                        $activation
                                            ->subscription
                                            ->currency,
                                    'subtotal_amount' =>
                                        (float) $activation
                                            ->subscription
                                            ->subtotal_amount,
                                    'discount_amount' =>
                                        (float) $activation
                                            ->subscription
                                            ->discount_amount,
                                    'tax_amount' =>
                                        (float) $activation
                                            ->subscription
                                            ->tax_amount,
                                    'total_amount' =>
                                        (float) $activation
                                            ->subscription
                                            ->total_amount,
                                    'starts_at' =>
                                        $activation
                                            ->subscription
                                            ->starts_at?->toISOString(),
                                    'current_period_end' =>
                                        $activation
                                            ->subscription
                                            ->current_period_end?->toISOString(),
                                ] : null,
                        ] : null,
                        'service_activation' => (
                            $serviceActivation =
                                $serviceActivations->get(
                                    $capability->latestGoLive->id
                                )
                        ) ? [
                            'id' =>
                                $serviceActivation->id,
                            'activation_type' =>
                                $serviceActivation
                                    ->activation_type,
                            'service_id' =>
                                $serviceActivation->service_id,
                            'service' =>
                                $serviceActivation->service ? [
                                    'id' =>
                                        $serviceActivation
                                            ->service
                                            ->id,
                                    'key' =>
                                        $serviceActivation
                                            ->service
                                            ->key,
                                    'name' =>
                                        $serviceActivation
                                            ->service
                                            ->name,
                                ] : null,
                            'subscription_item_id' =>
                                $serviceActivation
                                    ->subscription_item_id,
                            'subscription_item' =>
                                $serviceActivation
                                    ->subscriptionItem ? [
                                    'id' =>
                                        $serviceActivation
                                            ->subscriptionItem
                                            ->id,
                                    'status' =>
                                        $serviceActivation
                                            ->subscriptionItem
                                            ->status,
                                    'billing_model' =>
                                        $serviceActivation
                                            ->subscriptionItem
                                            ->billing_model,
                                    'quantity' =>
                                        (int) $serviceActivation
                                            ->subscriptionItem
                                            ->quantity,
                                    'unit_price' =>
                                        (float) $serviceActivation
                                            ->subscriptionItem
                                            ->unit_price,
                                    'amount' =>
                                        (float) $serviceActivation
                                            ->subscriptionItem
                                            ->amount,
                                    'currency' =>
                                        $serviceActivation
                                            ->subscriptionItem
                                            ->currency,
                                    'block_size' =>
                                        $serviceActivation
                                            ->subscriptionItem
                                            ->block_size,
                                    'included_units' =>
                                        $serviceActivation
                                            ->subscriptionItem
                                            ->included_units,
                                    'unit_name' =>
                                        $serviceActivation
                                            ->subscriptionItem
                                            ->unit_name,
                                ] : null,
                        ] : null,
                    ] : null,
                ]
            )->values(),
        ];
    }

    private function backToExecution(
        ContactRequest $contact,
        string $message,
        string $flashKey = 'success'
    ): RedirectResponse {
        return redirect()
            ->route(
                'admin.diagnosis_requests.implementation_execution.show',
                $contact
            )
            ->with(
                $flashKey,
                $message
            );
    }
}
