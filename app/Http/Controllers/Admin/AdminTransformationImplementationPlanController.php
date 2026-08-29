<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\TransformationImplementationMilestone;
use App\Models\TransformationImplementationPhase;
use App\Models\TransformationImplementationPhaseEstimate;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\TransformationImplementationMilestoneBillingService;
use App\Services\Diagnosis\TransformationImplementationModalityCatalog;
use App\Services\Diagnosis\TransformationImplementationModalityService;
use App\Services\Diagnosis\TransformationImplementationPhaseService;
use App\Services\Diagnosis\TransformationImplementationPlanService;
use App\Services\Diagnosis\TransformationImplementationPricingService;
use App\Services\Diagnosis\TransformationProfessionalCapabilityCatalog;
use App\Services\Diagnosis\TransformationServiceCapabilityCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminTransformationImplementationPlanController extends Controller
{
    public function show(
        ContactRequest $contact,
        DiagnosisAccessService $accessService
    ): Response {
        $assessment = $this->assessmentFor($contact, $accessService);
        $roadmap = $this->publishedRoadmap($assessment);

        $plan = TransformationImplementationPlan::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->with([
                'roadmap:id,version,status,published_at',
                'phases' => fn ($query) => $query->orderBy('sequence'),
                'phases.capabilities' => fn ($query) =>
                    $query->orderBy('sequence'),
            ])
            ->orderByDesc('version')
            ->first();

        return Inertia::render(
            'Admin/DiagnosisRequests/ImplementationPlan',
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
                ],
                'roadmap' => $roadmap ? [
                    'id' => $roadmap->id,
                    'version' => $roadmap->version,
                    'published_at' =>
                        $roadmap->published_at?->toISOString(),
                ] : null,
                'plan' => $plan
                    ? $this->serializePlan($plan)
                    : null,
                'capability_options' => $plan
                    ? $this->capabilityOptionsFor($plan)
                    : [],
                'modality_options' => $this->modalityOptions(),
                'endpoints' => [
                    'back' => $roadmap
                        ? route(
                            'admin.diagnosis_requests.detailed_roadmap.show',
                            $contact
                        )
                        : route(
                            'admin.diagnosis_requests.show',
                            $contact
                        ),
                    'create' => route(
                        'admin.diagnosis_requests.implementation_plan.create',
                        $contact
                    ),
                    'phase_store' => route(
                        'admin.diagnosis_requests.implementation_plan.phase.store',
                        $contact
                    ),
                    'modality_select' => route(
                        'admin.diagnosis_requests.implementation_plan.modality.select',
                        $contact
                    ),
                    'present' => route(
                        'admin.diagnosis_requests.implementation_plan.present',
                        $contact
                    ),
                    'accept' => route(
                        'admin.diagnosis_requests.implementation_plan.accept',
                        $contact
                    ),
                    'phase_base' =>
                        "/admin/diagnosis-requests/{$contact->id}/implementation-plan/phases",
                ],
            ]
        );
    }

    public function create(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        TransformationImplementationPlanService $planService
    ): RedirectResponse {
        $assessment = $this->assessmentFor($contact, $accessService);

        $existing = $planService->latestForAssessment($assessment);

        if ($existing) {
            return $this->backToPlan(
                $contact,
                "Ya existe el Plan de Implementación V{$existing->version}.",
                'warning'
            );
        }

        $roadmap = $this->publishedRoadmap($assessment);

        $plan = $roadmap
            ? $planService->createDraftFromPublishedRoadmap(
                $roadmap,
                $request->user()
            )
            : $planService->createDraftFromAssessment(
                $assessment,
                $request->user()
            );

        return $this->backToPlan(
            $contact,
            "Plan de Implementación V{$plan->version} creado como borrador."
        );
    }

    public function storePhase(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        TransformationImplementationPhaseService $phaseService
    ): RedirectResponse {
        $plan = $this->editablePlanFor($contact, $accessService);

        $options = collect(
            $this->capabilityOptionsFor($plan)
        )->keyBy('key');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'capability_keys' => ['required', 'array', 'min:1'],
            'capability_keys.*' => [
                'required',
                'string',
                'distinct',
                Rule::in($options->keys()->all()),
            ],
        ]);

        $sequence = (int) $plan->phases()->max('sequence') + 1;

        $capabilities = collect(
            $validated['capability_keys']
        )->values()->map(
            function (
                string $key,
                int $index
            ) use ($options): array {
                $option = $options->get($key);

                return [
                    'sequence' =>
                        $index + 1,

                    'capability_key' =>
                        $key,

                    'capability_label' =>
                        $option['label'],

                    'capability_summary' =>
                        $option['purpose']
                        ?? null,

                    'source_snapshot' => [
                        'capability_key' =>
                            $key,

                        'capability_label' =>
                            $option['label'],

                        'kind' =>
                            $option['kind']
                            ?? null,

                        'subscription_candidate' =>
                            (bool) (
                                $option['subscription_candidate']
                                ?? false
                            ),

                        'service_key' =>
                            $option['service_key']
                            ?? null,

                        'purpose' =>
                            $option['purpose']
                            ?? null,

                        'includes' =>
                            $option['includes']
                            ?? [],
                    ],
                ];
            }
        )->all();

        $phaseService->createPhaseFromRoadmap(
            $plan,
            [
                'sequence' => $sequence,
                'name' => $validated['name'],
                'objective' =>
                    $validated['objective'] ?? null,
                'capabilities' => $capabilities,
            ],
            $request->user()?->id
        );

        return $this->backToPlan(
            $contact,
            'Fase agregada al Plan.'
        );
    }

    public function selectModality(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        TransformationImplementationModalityService $modalityService
    ): RedirectResponse {
        $plan = $this->configurablePlanFor(
            $contact,
            $accessService
        );

        $keys = collect(
            $this->modalityOptions()
        )->pluck('key')->all();

        $validated = $request->validate([
            'modality' => [
                'required',
                'string',
                Rule::in($keys),
            ],
        ]);

        $modalityService->select(
            $plan,
            $validated['modality'],
            $request->user()?->id
        );

        return $this->backToPlan(
            $contact,
            'Modalidad seleccionada.'
        );
    }

    public function upsertEstimate(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPhase $phase,
        DiagnosisAccessService $accessService,
        TransformationImplementationPricingService $pricingService
    ): RedirectResponse {
        $plan = $this->editablePlanFor($contact, $accessService);
        $this->assertPhaseBelongsToPlan($phase, $plan);

        abort_unless(
            filled($plan->selected_modality),
            422,
            'Seleccione primero una modalidad.'
        );

        $validated = $request->validate([
            'price_amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'estimated_duration_value' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'estimated_duration_unit' => [
                'required',
                'string',
                Rule::in(['days', 'weeks', 'months']),
            ],
        ]);

        $pricingService->upsertEstimate(
            $phase,
            [
                'modality' => $plan->selected_modality,
                'price_amount' => $validated['price_amount'],
                'currency' => 'DOP',
                'estimated_duration_value' =>
                    $validated['estimated_duration_value'],
                'estimated_duration_unit' =>
                    $validated['estimated_duration_unit'],
            ],
            $request->user()?->id
        );

        return $this->backToPlan(
            $contact,
            'Precio y tiempo actualizados.'
        );
    }

    public function upsertMilestone(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPhase $phase,
        DiagnosisAccessService $accessService,
        TransformationImplementationMilestoneBillingService $milestoneService
    ): RedirectResponse {
        $plan = $this->editablePlanFor($contact, $accessService);
        $this->assertPhaseBelongsToPlan($phase, $plan);

        abort_unless(
            filled($plan->selected_modality),
            422,
            'Seleccione primero una modalidad.'
        );

        $validated = $request->validate([
            'sequence' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'billing_amount' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        $milestoneService->upsertMilestone(
            $phase,
            [
                'sequence' => $validated['sequence'],
                'name' => $validated['name'],
                'billing_amount' =>
                    $validated['billing_amount'],
            ],
            $request->user()?->id
        );

        return $this->backToPlan(
            $contact,
            'Hito guardado.'
        );
    }

    public function present(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        TransformationImplementationPlanService $planService
    ): RedirectResponse {
        $plan = $this->editablePlanFor($contact, $accessService);

        $planService->markPresented(
            $plan,
            $request->user()
        );

        return $this->backToPlan(
            $contact,
            'Plan presentado al cliente.'
        );
    }

    public function accept(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        TransformationImplementationPlanService $planService
    ): RedirectResponse {
        $assessment = $this->assessmentFor($contact, $accessService);

        $plan = TransformationImplementationPlan::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->orderByDesc('version')
            ->firstOrFail();

        $planService->acceptPlan(
            $plan,
            $request->user()
        );

        return $this->backToPlan(
            $contact,
            'Plan marcado como aceptado.'
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
            abort(
                422,
                'La solicitud no tiene diagnóstico vinculado.'
            );
        }

        return $workflow->assessment;
    }

    private function publishedRoadmap(
        DiagnosisAssessment $assessment
    ): ?DiagnosisDetailedRoadmap {
        return DiagnosisDetailedRoadmap::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where(
                'status',
                DiagnosisDetailedRoadmap::STATUS_PUBLISHED
            )
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();
    }

    private function configurablePlanFor(
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
            ->orderByDesc('version')
            ->firstOrFail();

        abort_unless(
            in_array(
                $plan->status,
                [
                    TransformationImplementationPlan::STATUS_DRAFT,
                    TransformationImplementationPlan::STATUS_PRESENTED,
                ],
                true
            ),
            422,
            'La configuración comercial ya no puede modificarse en este estado.'
        );

        return $plan;
    }

    private function editablePlanFor(
        ContactRequest $contact,
        DiagnosisAccessService $accessService
    ): TransformationImplementationPlan {
        $plan = $this->configurablePlanFor(
            $contact,
            $accessService
        );

        abort_unless(
            $plan->status
                === TransformationImplementationPlan::STATUS_DRAFT,
            422,
            'Solo un Plan en borrador puede editar fases, precios e hitos.'
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

    private function modalityOptions(): array
    {
        return collect(
            TransformationImplementationModalityCatalog::all()
        )->map(
            fn (array $definition, string $key) => [
                'key' => $key,
                'label' =>
                    $definition['label'] ?? $key,
                'description' =>
                    $definition['description'] ?? null,
            ]
        )->values()->all();
    }

    private function capabilityOptionsFor(
        TransformationImplementationPlan $plan
    ): array {
        $snapshot = json_encode(
            $plan->source_snapshot,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        $catalog = array_merge(
            TransformationServiceCapabilityCatalog::all(),
            TransformationProfessionalCapabilityCatalog::all()
        );

        return collect($catalog)
            ->map(
                function (
                    array $definition,
                    string|int $catalogKey
                ) {
                    $key = (string) (
                        $definition['capability_key']
                        ?? $definition['key']
                        ?? $catalogKey
                    );

                    $subscriptionCandidate = (bool) (
                        $definition['subscription_candidate']
                        ?? false
                    );

                    return [
                        'key' =>
                            $key,

                        'label' =>
                            $definition['title']
                            ?? $definition['label']
                            ?? $key,

                        'service_key' =>
                            $definition['service_key']
                            ?? null,

                        'kind' =>
                            $definition['kind']
                            ?? (
                                $subscriptionCandidate
                                    ? 'subscription_service'
                                    : 'professional_service'
                            ),

                        'subscription_candidate' =>
                            $subscriptionCandidate,

                        'purpose' =>
                            $definition['purpose']
                            ?? null,

                        'includes' =>
                            $definition['includes']
                            ?? [],
                    ];
                }
            )
            ->filter(
                fn (array $option) =>
                    is_string($snapshot)
                    && str_contains(
                        $snapshot,
                        $option['key']
                    )
            )
            ->values()
            ->all();
    }

    private function serializePlan(
        TransformationImplementationPlan $plan
    ): array {
        return [
            'id' => $plan->id,
            'version' => $plan->version,
            'status' => $plan->status,
            'source_type' =>
                data_get(
                    $plan->source_snapshot,
                    'source_type',
                    $plan->diagnosis_detailed_roadmap_id
                        ? 'published_roadmap'
                        : 'internal_assessment'
                ),
            'recommended_modality' =>
                $plan->recommended_modality,
            'recommended_modality_label' =>
                $plan->recommended_modality_label,
            'selected_modality' =>
                $plan->selected_modality,
            'selected_modality_label' =>
                $plan->selected_modality_label,
            'presented_at' =>
                $plan->presented_at?->toISOString(),
            'accepted_at' =>
                $plan->accepted_at?->toISOString(),
            'phases' => $plan->phases->map(
                function (
                    TransformationImplementationPhase $phase
                ) use ($plan) {
                    $estimate =
                        TransformationImplementationPhaseEstimate::query()
                            ->where(
                                'transformation_implementation_phase_id',
                                $phase->id
                            )
                            ->when(
                                filled($plan->selected_modality),
                                fn ($query) =>
                                    $query->where(
                                        'modality',
                                        $plan->selected_modality
                                    )
                            )
                            ->first();

                    $milestones =
                        TransformationImplementationMilestone::query()
                            ->where(
                                'transformation_implementation_phase_id',
                                $phase->id
                            )
                            ->when(
                                filled($plan->selected_modality),
                                fn ($query) =>
                                    $query->where(
                                        'modality',
                                        $plan->selected_modality
                                    )
                            )
                            ->orderBy('sequence')
                            ->get();

                    return [
                        'id' => $phase->id,
                        'sequence' => $phase->sequence,
                        'name' => $phase->name,
                        'objective' => $phase->objective,
                        'capabilities' =>
                            $phase->capabilities->map(
                                fn ($capability) => [
                                    'id' => $capability->id,
                                    'sequence' =>
                                        $capability->sequence,
                                    'capability_key' =>
                                        $capability->capability_key,
                                    'capability_label' =>
                                        $capability->capability_label,
                                ]
                            )->values(),
                        'estimate' => $estimate ? [
                            'price_amount' =>
                                (float) $estimate->price_amount,
                            'currency' =>
                                $estimate->currency,
                            'estimated_duration_value' =>
                                (float) $estimate
                                    ->estimated_duration_value,
                            'estimated_duration_unit' =>
                                $estimate
                                    ->estimated_duration_unit,
                        ] : null,
                        'milestones' =>
                            $milestones->map(
                                fn ($milestone) => [
                                    'id' => $milestone->id,
                                    'sequence' =>
                                        $milestone->sequence,
                                    'name' =>
                                        $milestone->name,
                                    'billing_amount' =>
                                        (float) $milestone
                                            ->billing_amount,
                                    'currency' =>
                                        $milestone->currency,
                                    'billing_status' =>
                                        $milestone
                                            ->billing_status,
                                ]
                            )->values(),
                    ];
                }
            )->values(),
        ];
    }

    private function backToPlan(
        ContactRequest $contact,
        string $message,
        string $flashKey = 'success'
    ): RedirectResponse {
        return redirect()
            ->route(
                'admin.diagnosis_requests.implementation_plan.show',
                $contact
            )
            ->with($flashKey, $message);
    }
}
