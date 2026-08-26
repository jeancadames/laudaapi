<?php

namespace App\Http\Controllers\Diagnosis;

use App\Services\Diagnosis\TransformationImplementationClientSolutionAccessService;
use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\TransformationImplementationPlanService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransformationImplementationPlanController extends Controller
{
    public function show(
        DiagnosisAssessment $assessment
    ): Response {
        Gate::authorize('view', $assessment);

        $plan = TransformationImplementationPlan::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->whereIn('status', [
                TransformationImplementationPlan::STATUS_PRESENTED,
                TransformationImplementationPlan::STATUS_ACCEPTED,
                TransformationImplementationPlan::STATUS_ACTIVE,
                TransformationImplementationPlan::STATUS_COMPLETED,
            ])
            ->whereNotNull('presented_at')
            ->with([
                'phases' => fn ($query) => $query->orderBy('sequence'),
                'phases.capabilities' => fn ($query) => $query->orderBy('sequence'),
                'phases.execution',
                'phases.capabilities.execution',
                'phases.capabilities.latestGoLive',
                'phases.capabilities.latestGoLive.subscriptionItemActivation.service',
                'phases.capabilities.latestGoLive.subscriptionItemActivation.subscriptionItem.subscription',
                'phases.estimates',
                'phases.milestones',
            ])
            ->orderByDesc('version')
            ->firstOrFail();


        $selectedModality = $plan->selected_modality;

        $commercialPhases = $plan->phases->map(
            function ($phase) use ($selectedModality) {
                $estimate = $phase->estimates
                    ->firstWhere(
                        'modality',
                        $selectedModality
                    );

                $milestones = $phase->milestones
                    ->where(
                        'modality',
                        $selectedModality
                    )
                    ->where(
                        'billing_status',
                        '!=',
                        'cancelled'
                    )
                    ->sortBy('sequence')
                    ->values();

                return [
                    'id' => $phase->id,
                    'sequence' => $phase->sequence,
                    'name' => $phase->name,
                    'objective' => $phase->objective,
                    'capabilities' =>
                        $phase->capabilities->map(
                            fn ($capability) => [
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
                            (int) $estimate
                                ->estimated_duration_value,
                        'estimated_duration_unit' =>
                            $estimate->estimated_duration_unit,
                    ] : null,
                    'milestones' =>
                        $milestones->map(
                            fn ($milestone) => [
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
        )->values();

        $commercialSummary = [
            'currency' => 'DOP',
            'total_price_amount' => round(
                (float) $commercialPhases->sum(
                    fn (array $phase) =>
                        (float) data_get(
                            $phase,
                            'estimate.price_amount',
                            0
                        )
                ),
                2
            ),
            'phases_count' =>
                $commercialPhases->count(),
            'milestones_count' =>
                $commercialPhases->sum(
                    fn (array $phase) =>
                        collect(
                            $phase['milestones'] ?? []
                        )->count()
                ),
        ];

        
                $solutionAccessService = app(
            TransformationImplementationClientSolutionAccessService::class
        );

$clientPhases = $commercialPhases->map(
            function (array $phaseData) use ($plan, $solutionAccessService) {
                $phaseModel = $plan->phases->firstWhere(
                    'id',
                    $phaseData['id']
                );

                $phaseExecution = $phaseModel?->execution;

                $capabilities = collect(
                    $phaseData['capabilities'] ?? []
                )->map(
                    function (
                        array $capabilityData
                    ) use (
                        $phaseModel,
                        $solutionAccessService
                    ) {
                        $capabilityModel =
                            $phaseModel?->capabilities
                                ->first(
                                    fn ($item) =>
                                        (int) $item->sequence
                                            === (int) (
                                                $capabilityData[
                                                    'sequence'
                                                ] ?? -1
                                            )
                                        && $item->capability_key
                                            === (
                                                $capabilityData[
                                                    'capability_key'
                                                ] ?? null
                                            )
                                );

                        $execution =
                            $capabilityModel?->execution;

                        $goLive =
                            $capabilityModel?->latestGoLive;

                        $recurringSolution =
                            $solutionAccessService->resolve(
                                request()->user(),
                                $goLive
                            );

                        return array_merge(
                            $capabilityData,
                            [
                                'execution' => [
                                    'status' =>
                                        $execution?->status
                                        ?? 'pending',
                                    'progress_percentage' =>
                                        round(
                                            (float) (
                                                $execution
                                                    ?->progress_percentage
                                                ?? 0
                                            ),
                                            2
                                        ),
                                    'started_at' =>
                                        $execution?->started_at
                                            ?->toISOString(),
                                    'completed_at' =>
                                        $execution?->completed_at
                                            ?->toISOString(),
                                ],
                                'go_live' => $goLive ? [
                                    'status' =>
                                        $goLive->status,
                                    'ready_at' =>
                                        $goLive->ready_at
                                            ?->toISOString(),
                                    'scheduled_at' =>
                                        $goLive->scheduled_at
                                            ?->toISOString(),
                                    'went_live_at' =>
                                        $goLive->went_live_at
                                            ?->toISOString(),
                                    'rolled_back_at' =>
                                        $goLive->rolled_back_at
                                            ?->toISOString(),
                                ] : null,
                                'recurring_solution' =>
                                    $recurringSolution,
                            ]
                        );
                    }
                )->values();

                return array_merge(
                    $phaseData,
                    [
                        'execution' => [
                            'status' =>
                                $phaseExecution?->status
                                ?? 'pending',
                            'progress_percentage' =>
                                round(
                                    (float) (
                                        $phaseExecution
                                            ?->progress_percentage
                                        ?? 0
                                    ),
                                    2
                                ),
                            'started_at' =>
                                $phaseExecution?->started_at
                                    ?->toISOString(),
                            'completed_at' =>
                                $phaseExecution?->completed_at
                                    ?->toISOString(),
                        ],
                        'capabilities' =>
                            $capabilities,
                    ]
                );
            }
        )->values();

        $allCapabilityExecutions = $clientPhases
            ->flatMap(
                fn (array $phase) =>
                    collect(
                        $phase['capabilities'] ?? []
                    )->pluck('execution')
            )
            ->filter(
                fn ($execution) =>
                    is_array($execution)
            )
            ->values();

        $activeCapabilityExecutions =
            $allCapabilityExecutions
                ->reject(
                    fn (array $execution) =>
                        ($execution['status'] ?? null)
                            === 'cancelled'
                )
                ->values();

        $executionSummary = [
            'total_capabilities' =>
                $allCapabilityExecutions->count(),
            'active_capabilities' =>
                $activeCapabilityExecutions->count(),
            'pending_count' =>
                $allCapabilityExecutions->where(
                    'status',
                    'pending'
                )->count(),
            'in_progress_count' =>
                $allCapabilityExecutions->where(
                    'status',
                    'in_progress'
                )->count(),
            'blocked_count' =>
                $allCapabilityExecutions->where(
                    'status',
                    'blocked'
                )->count(),
            'completed_count' =>
                $allCapabilityExecutions->where(
                    'status',
                    'completed'
                )->count(),
            'cancelled_count' =>
                $allCapabilityExecutions->where(
                    'status',
                    'cancelled'
                )->count(),
            'progress_percentage' =>
                $activeCapabilityExecutions->isEmpty()
                    ? 0.0
                    : round(
                        (float)
                        $activeCapabilityExecutions->avg(
                            'progress_percentage'
                        ),
                        2
                    ),
        ];


        $allGoLives = $clientPhases
            ->flatMap(
                fn (array $phase) =>
                    collect(
                        $phase['capabilities'] ?? []
                    )->map(
                        fn (array $capability) =>
                            $capability['go_live']
                            ?? null
                    )
            )
            ->values();

        $goLivesWithAttempt = $allGoLives
            ->filter(
                fn ($goLive) =>
                    is_array($goLive)
            )
            ->values();

        $goLiveSummary = [
            'total_capabilities' =>
                $allGoLives->count(),
            'without_go_live_count' =>
                $allGoLives->filter(
                    fn ($goLive) =>
                        $goLive === null
                )->count(),
            'draft_count' =>
                $goLivesWithAttempt->where(
                    'status',
                    'draft'
                )->count(),
            'ready_count' =>
                $goLivesWithAttempt->where(
                    'status',
                    'ready'
                )->count(),
            'scheduled_count' =>
                $goLivesWithAttempt->where(
                    'status',
                    'scheduled'
                )->count(),
            'live_count' =>
                $goLivesWithAttempt->where(
                    'status',
                    'live'
                )->count(),
            'rolled_back_count' =>
                $goLivesWithAttempt->where(
                    'status',
                    'rolled_back'
                )->count(),
            'cancelled_count' =>
                $goLivesWithAttempt->where(
                    'status',
                    'cancelled'
                )->count(),
        ];


        $allClientCapabilities = $clientPhases
            ->flatMap(
                fn (array $phase) =>
                    collect(
                        $phase['capabilities'] ?? []
                    )
            )
            ->values();

        $activatedSolutions =
            $allClientCapabilities
                ->pluck('recurring_solution')
                ->filter(
                    fn ($solution) =>
                        is_array($solution)
                )
                ->values();

        $solutionAccessSummary = [
            'r2j_activated_count' =>
                $activatedSolutions->count(),
            'entitled_count' =>
                $activatedSolutions->filter(
                    fn (array $solution) =>
                        ($solution[
                            'entitlement_allowed'
                        ] ?? false) === true
                )->count(),
            'access_unavailable_count' =>
                $activatedSolutions->filter(
                    fn (array $solution) =>
                        ($solution[
                            'entitlement_allowed'
                        ] ?? false) !== true
                )->count(),
            'live_without_r2j_count' =>
                $allClientCapabilities->filter(
                    fn (array $capability) =>
                        data_get(
                            $capability,
                            'go_live.status'
                        ) === 'live'
                        && ! is_array(
                            $capability[
                                'recurring_solution'
                            ] ?? null
                        )
                )->count(),
        ];

        $solutionAccessSummary['portal_url'] =
            ($solutionAccessSummary['entitled_count'] ?? 0) > 0
                ? route('app.gateway', [], false)
                : null;

return Inertia::render(
            'Diagnosis/ImplementationPlan',
            [
                'assessment' => [
                    'id' => $assessment->id,
                    'organization_name' => $assessment->organization_name,
                ],
                'plan' => [
                    'id' => $plan->id,
                    'version' => $plan->version,
                    'status' => $plan->status,
                    'selected_modality_label' =>
                        $plan->selected_modality_label
                        ?: (
                            TransformationImplementationPlan::modalities()[
                                $plan->selected_modality
                            ] ?? null
                        ),
                    'presented_at' =>
                        $plan->presented_at?->toISOString(),
                    'accepted_at' =>
                        $plan->accepted_at?->toISOString(),
                    'commercial_summary' => $commercialSummary,
                    'execution_summary' => $executionSummary,
                    'go_live_summary' => $goLiveSummary,
                    'solution_access_summary' => $solutionAccessSummary,
                    'phases' => $clientPhases,
                ],
                'roadmap_url' =>
                    $plan->diagnosis_detailed_roadmap_id
                    ? route(
                        'diagnosis.detailed_roadmap.show',
                        $assessment
                    )
                    : null,
                'accept_url' => route(
                    'diagnosis.implementation_plan.accept',
                    $assessment
                ),
                'diagnosis_url' => route(
                    'diagnosis.show',
                    $assessment
                ),
            ]
        );
    }

    public function accept(
        DiagnosisAssessment $assessment,
        TransformationImplementationPlanService $planService
    ): \Illuminate\Http\RedirectResponse {
        Gate::authorize('view', $assessment);

        $plan = TransformationImplementationPlan::query()
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->whereIn('status', [
                TransformationImplementationPlan::STATUS_PRESENTED,
                TransformationImplementationPlan::STATUS_ACCEPTED,
            ])
            ->whereNotNull('presented_at')
            ->orderByDesc('version')
            ->firstOrFail();

        $accepted = $planService->acceptPlan(
            $plan,
            request()->user()
        );

        return redirect()
            ->route(
                'diagnosis.implementation_plan.show',
                $assessment
            )
            ->with(
                'success',
                'Plan de Implementación aceptado correctamente.'
            );
    }

}
