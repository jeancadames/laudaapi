<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\DiagnosisDeliverableValidationService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransformationImplementationPlanController extends Controller
{
    public function show(
        DiagnosisAssessment $assessment,
        DiagnosisDeliverableValidationService $validations
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
                'phases.capabilities' => fn ($query) =>
                    $query->orderBy('sequence'),
            ])
            ->orderByDesc('version')
            ->firstOrFail();

        $phases = $plan->phases
            ->map(function ($phase): array {
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
                        $kind = data_get(
                            $capability->source_snapshot,
                            'kind'
                        );
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
                    'horizon' => data_get(
                        $phase->source_snapshot,
                        'horizon'
                    ),
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
                    'capabilities' => $capabilities,
                ];
            })
            ->values();

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
                    'presented_at' => $plan->presented_at?->toISOString(),
                    'phases' => $phases,
                ],
                'validation' => $validations->stateFor($plan),
                'validation_endpoints' => [
                    'review' => route(
                        'diagnosis.deliverable.review',
                        [$assessment, 'implementation-plan']
                    ),
                    'validate' => route(
                        'diagnosis.deliverable.validate',
                        [$assessment, 'implementation-plan']
                    ),
                    'request_adjustment' => route(
                        'diagnosis.deliverable.adjustment',
                        [$assessment, 'implementation-plan']
                    ),
                ],
                'roadmap_url' => $plan->diagnosis_detailed_roadmap_id
                    ? route(
                        'diagnosis.detailed_roadmap.show',
                        $assessment
                    )
                    : null,
                'diagnosis_url' => route(
                    'diagnosis.show',
                    $assessment
                ),
            ]
        );
    }
}
