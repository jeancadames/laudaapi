<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\DiagnosisAccessService;
use App\Services\Diagnosis\DiagnosisDeliverableValidationService;
use App\Services\Diagnosis\TransformationImplementationPhaseService;
use App\Services\Diagnosis\TransformationImplementationPlanAutogenerator;
use App\Services\Diagnosis\TransformationImplementationPlanService;
use App\Services\Diagnosis\TransformationProfessionalCapabilityCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminTransformationImplementationPlanController extends Controller
{
    public function show(
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        DiagnosisDeliverableValidationService $validations
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
                    'organization_name' => $assessment->organization_name,
                ],
                'roadmap' => $roadmap ? [
                    'id' => $roadmap->id,
                    'version' => $roadmap->version,
                    'published_at' => $roadmap->published_at?->toISOString(),
                ] : null,
                'plan' => $plan ? $this->serializePlan($plan) : null,
                'tenant_validation' =>
                    $validations->closureForAssessment(
                        $assessment
                    )['implementation_plan'],
                'capability_options' => $plan
                    ? $this->capabilityOptionsFor($plan)
                    : [],
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
                    'regenerate' => route(
                        'admin.diagnosis_requests.implementation_plan.regenerate',
                        $contact
                    ),
                    'phase_store' => route(
                        'admin.diagnosis_requests.implementation_plan.phase.store',
                        $contact
                    ),
                    'present' => route(
                        'admin.diagnosis_requests.implementation_plan.present',
                        $contact
                    ),
                ],
            ]
        );
    }

    public function create(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        TransformationImplementationPlanService $planService,
        TransformationImplementationPlanAutogenerator $autogenerator
    ): RedirectResponse {
        $assessment = $this->assessmentFor($contact, $accessService);
        $existing = $planService->latestForAssessment($assessment);

        if ($existing) {
            if (
                $existing->status === TransformationImplementationPlan::STATUS_DRAFT
                && ! $existing->phases()->exists()
            ) {
                $generated = $autogenerator->generate(
                    $existing,
                    $request->user()?->id
                );

                return $this->backToPlan(
                    $contact,
                    "Plan de Implementación V{$generated->version} autogenerado desde su fuente."
                );
            }

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

        $plan = $autogenerator->generate(
            $plan,
            $request->user()?->id
        );

        return $this->backToPlan(
            $contact,
            "Plan de Implementación V{$plan->version} creado y autogenerado como borrador consultivo."
        );
    }

    public function regenerateStructure(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        TransformationImplementationPlanAutogenerator $autogenerator
    ): RedirectResponse {
        $plan = $this->editablePlanFor($contact, $accessService);

        $generated = $autogenerator->regenerate(
            $plan,
            $request->user()?->id
        );

        return $this->backToPlan(
            $contact,
            "Plan de Implementación V{$generated->version} regenerado desde su fuente consultiva."
        );
    }

    public function storePhase(
        Request $request,
        ContactRequest $contact,
        DiagnosisAccessService $accessService,
        TransformationImplementationPhaseService $phaseService
    ): RedirectResponse {
        $plan = $this->editablePlanFor($contact, $accessService);

        $options = collect($this->capabilityOptionsFor($plan))
            ->filter(
                fn (array $option): bool =>
                    ($option['kind'] ?? null) === 'professional_service'
                    && ! ($option['subscription_candidate'] ?? false)
            )
            ->keyBy('key');

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

        $capabilities = collect($validated['capability_keys'])
            ->values()
            ->map(function (string $key, int $index) use ($options): array {
                $option = $options->get($key);

                return [
                    'sequence' => $index + 1,
                    'capability_key' => $key,
                    'capability_label' => $option['label'],
                    'capability_summary' => $option['purpose'] ?? null,
                    'source_snapshot' => [
                        'capability_key' => $key,
                        'capability_label' => $option['label'],
                        'kind' => 'professional_service',
                        'subscription_candidate' => false,
                        'service_key' => null,
                        'purpose' => $option['purpose'] ?? null,
                        'includes' => $option['includes'] ?? [],
                    ],
                ];
            })
            ->all();

        $phaseService->createPhaseFromRoadmap(
            $plan,
            [
                'sequence' => $sequence,
                'name' => $validated['name'],
                'objective' => $validated['objective'] ?? null,
                'capabilities' => $capabilities,
            ],
            $request->user()?->id
        );

        return $this->backToPlan(
            $contact,
            'Fase profesional agregada al Plan.'
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
            'Plan de Implementación presentado al tenant como entregable consultivo gratuito.'
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

    private function editablePlanFor(
        ContactRequest $contact,
        DiagnosisAccessService $accessService
    ): TransformationImplementationPlan {
        $assessment = $this->assessmentFor($contact, $accessService);

        $plan = TransformationImplementationPlan::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->orderByDesc('version')
            ->firstOrFail();

        abort_unless(
            $plan->status === TransformationImplementationPlan::STATUS_DRAFT,
            422,
            'Solo un Plan consultivo en borrador puede editarse.'
        );

        return $plan;
    }

    private function capabilityOptionsFor(
        TransformationImplementationPlan $plan
    ): array {
        $snapshot = json_encode(
            $plan->source_snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return collect(TransformationProfessionalCapabilityCatalog::all())
            ->map(function (array $definition, string|int $catalogKey): array {
                $key = (string) (
                    $definition['capability_key']
                    ?? $definition['key']
                    ?? $catalogKey
                );

                return [
                    'key' => $key,
                    'label' => $definition['title']
                        ?? $definition['label']
                        ?? $key,
                    'service_key' => null,
                    'kind' => 'professional_service',
                    'subscription_candidate' => false,
                    'purpose' => $definition['purpose'] ?? null,
                    'includes' => $definition['includes'] ?? [],
                ];
            })
            ->filter(
                fn (array $option): bool =>
                    is_string($snapshot)
                    && str_contains($snapshot, $option['key'])
            )
            ->values()
            ->all();
    }

    private function serializePlan(
        TransformationImplementationPlan $plan
    ): array {
        $sourceType = data_get(
            $plan->source_snapshot,
            'source_type',
            $plan->diagnosis_detailed_roadmap_id
                ? 'published_roadmap'
                : 'internal_assessment'
        );

        return [
            'id' => $plan->id,
            'version' => $plan->version,
            'status' => $plan->status,
            'source_type' => $sourceType,
            'source_label' => $sourceType === 'published_roadmap'
                ? 'Roadmap Detallado publicado'
                : 'Diagnóstico oficial · snapshot interno',
            'autogeneration' => data_get(
                $plan->source_snapshot,
                'autogeneration'
            ),
            'presented_at' => $plan->presented_at?->toISOString(),
            'legacy_execution_available' => in_array(
                $plan->status,
                [
                    TransformationImplementationPlan::STATUS_ACCEPTED,
                    TransformationImplementationPlan::STATUS_ACTIVE,
                    TransformationImplementationPlan::STATUS_COMPLETED,
                ],
                true
            ),
            'phases' => $plan->phases
                ->map(fn ($phase): array => $this->serializePhase($phase))
                ->values(),
        ];
    }

    private function serializePhase($phase): array
    {
        $initiatives = collect(
            data_get($phase->source_snapshot, 'initiatives', [])
        )->map(fn ($initiative): array => [
            'id' => data_get($initiative, 'id'),
            'priority' => data_get($initiative, 'priority'),
            'title' => data_get($initiative, 'title'),
            'objective' => data_get($initiative, 'objective'),
            'actions' => data_get($initiative, 'actions', []),
            'owner_role' => data_get($initiative, 'owner_role'),
            'dependencies' => data_get($initiative, 'dependencies', []),
            'success_metrics' => data_get($initiative, 'success_metrics', []),
        ])->values();

        $capabilities = $phase->capabilities
            ->filter(function ($capability): bool {
                $kind = data_get($capability->source_snapshot, 'kind');
                $subscriptionCandidate = (bool) data_get(
                    $capability->source_snapshot,
                    'subscription_candidate',
                    false
                );

                return $kind !== 'subscription_service'
                    && ! $subscriptionCandidate;
            })
            ->map(fn ($capability): array => [
                'id' => $capability->id,
                'sequence' => $capability->sequence,
                'capability_key' => $capability->capability_key,
                'capability_label' => $capability->capability_label,
                'summary' => $capability->capability_summary,
                'kind' => 'professional_service',
                'includes' => data_get(
                    $capability->source_snapshot,
                    'includes',
                    []
                ),
            ])
            ->values();

        return [
            'id' => $phase->id,
            'sequence' => $phase->sequence,
            'name' => $phase->name,
            'objective' => $phase->objective,
            'horizon' => data_get($phase->source_snapshot, 'horizon'),
            'initiative_ids' => data_get(
                $phase->source_snapshot,
                'initiative_ids',
                []
            ),
            'initiatives' => $initiatives,
            'dependencies' => data_get(
                $phase->source_snapshot,
                'dependencies',
                []
            ),
            'deliverables' => data_get(
                $phase->source_snapshot,
                'deliverables',
                []
            ),
            'autogenerated' => (bool) data_get(
                $phase->source_snapshot,
                'autogenerated',
                false
            ),
            'capabilities' => $capabilities,
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
