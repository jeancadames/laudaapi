<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\TransformationImplementationDefinitionAutogenerator;
use App\Services\Diagnosis\TransformationImplementationDefinitionReviewService;
use App\Services\Diagnosis\TransformationImplementationDefinitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdminTransformationImplementationDefinitionController
    extends Controller
{
    public function show(
        ContactRequest $contact,
        TransformationImplementationPlan $plan
    ): Response {
        $this->assertContext(
            $contact,
            $plan
        );

        $assessment =
            $plan->assessment()
                ->firstOrFail();

        $definition =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_plan_id',
                    $plan->id
                )
                ->orderByDesc('version')
                ->first();

        return Inertia::render(
            'Admin/DiagnosisRequests/ImplementationDefinition',
            [
                'contact' => [
                    'id' =>
                        $contact->id,

                    'company' =>
                        $contact->company
                        ?? null,

                    'name' =>
                        $contact->name
                        ?? null,
                ],

                'assessment' => [
                    'id' =>
                        $assessment->id,

                    'organization_name' =>
                        $assessment
                            ->organization_name,
                ],

                'plan' => [
                    'id' =>
                        $plan->id,

                    'version' =>
                        $plan->version,

                    'status' =>
                        $plan->status,

                    'presented_at' =>
                        $plan->presented_at
                            ?->toISOString(),
                ],

                'definition' =>
                    $definition
                        ? $this->definitionPayload(
                            $definition
                        )
                        : null,

                'endpoints' => [
                    'back' =>
                        route(
                            'admin.diagnosis_requests.implementation_plan.show',
                            $contact
                        ),

                    'create' =>
                        route(
                            'admin.diagnosis_requests.implementation_definition.create',
                            [
                                'contact' =>
                                    $contact,

                                'plan' =>
                                    $plan,
                            ]
                        ),

                    'regenerate' =>
                        $definition
                            ? route(
                                'admin.diagnosis_requests.implementation_definition.regenerate',
                                [
                                    'contact' =>
                                        $contact,

                                    'plan' =>
                                        $plan,

                                    'definition' =>
                                        $definition,
                                ]
                            )
                            : null,

                    'review' =>
                        $definition
                            ? route(
                                'admin.diagnosis_requests.implementation_definition.review',
                                [
                                    'contact' =>
                                        $contact,

                                    'plan' =>
                                        $plan,

                                    'definition' =>
                                        $definition,
                                ]
                            )
                            : null,

                    'ready' =>
                        $definition
                            ? route(
                                'admin.diagnosis_requests.implementation_definition.ready',
                                [
                                    'contact' =>
                                        $contact,

                                    'plan' =>
                                        $plan,

                                    'definition' =>
                                        $definition,
                                ]
                            )
                            : null,
                ],
            ]
        );
    }

    public function create(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPlan $plan,
        TransformationImplementationDefinitionService $definitionService,
        TransformationImplementationDefinitionAutogenerator $autogenerator
    ): RedirectResponse {
        $this->assertContext(
            $contact,
            $plan
        );

        $existing =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_plan_id',
                    $plan->id
                )
                ->orderByDesc('version')
                ->first();

        if ($existing) {
            return redirect()
                ->route(
                    'admin.diagnosis_requests.implementation_definition.show',
                    [
                        'contact' =>
                            $contact,

                        'plan' =>
                            $plan,
                    ]
                )
                ->with(
                    'info',
                    "Ya existe la Definición de Implementación V{$existing->version}."
                );
        }

        $definition =
            $definitionService
                ->createOrGetDraftFromPresentedPlan(
                    $plan,
                    $request->user()
                );

        $definition =
            $autogenerator->generate(
                $definition,
                $request->user()->id
            );

        return redirect()
            ->route(
                'admin.diagnosis_requests.implementation_definition.show',
                [
                    'contact' =>
                        $contact,

                    'plan' =>
                        $plan,
                ]
            )
            ->with(
                'success',
                "Definición de Implementación V{$definition->version} creada y preparada para revisión."
            );
    }

    public function regenerate(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPlan $plan,
        TransformationImplementationDefinition $definition,
        TransformationImplementationDefinitionAutogenerator $autogenerator
    ): RedirectResponse {
        $this->assertDefinitionContext(
            $contact,
            $plan,
            $definition
        );

        $autogenerator->generate(
            $definition,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Definición regenerada desde el Plan presentado.'
        );
    }

    public function review(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPlan $plan,
        TransformationImplementationDefinition $definition,
        TransformationImplementationDefinitionReviewService $reviewService
    ): RedirectResponse {
        $this->assertDefinitionContext(
            $contact,
            $plan,
            $definition
        );

        $validated =
            $request->validate([
                'implementation_scope' =>
                    ['sometimes', 'array'],

                'deliverables' =>
                    ['sometimes', 'array'],

                'dependencies' =>
                    ['sometimes', 'array'],

                'responsibility_model' =>
                    ['required', 'array'],

                'responsibility_model.assignments' =>
                    ['required', 'array'],

                'responsibility_model.assignments.*.initiative_id' =>
                    ['required', 'string'],

                'responsibility_model.assignments.*.responsible_party' =>
                    [
                        'required',
                        'string',
                        'in:lauda,client,shared',
                    ],

                'readiness' =>
                    ['required', 'array'],

                'readiness.scope_confirmed' =>
                    ['required', 'boolean'],

                'readiness.deliverables_confirmed' =>
                    ['required', 'boolean'],

                'readiness.dependencies_confirmed' =>
                    ['required', 'boolean'],

                'readiness.inputs_validated' =>
                    ['required', 'boolean'],

                'readiness.accesses_validated' =>
                    ['required', 'boolean'],

                'readiness.responsibilities_confirmed' =>
                    ['required', 'boolean'],
            ]);

        $reviewService->saveReview(
            $definition,
            $validated,
            $request->user()
        );

        return back()->with(
            'success',
            'Revisión de la Definición guardada.'
        );
    }

    public function ready(
        Request $request,
        ContactRequest $contact,
        TransformationImplementationPlan $plan,
        TransformationImplementationDefinition $definition,
        TransformationImplementationDefinitionReviewService $reviewService
    ): RedirectResponse {
        $this->assertDefinitionContext(
            $contact,
            $plan,
            $definition
        );

        $reviewService->markReady(
            $definition,
            $request->user()
        );

        return back()->with(
            'success',
            'Definición de Implementación marcada como lista.'
        );
    }

    private function assertDefinitionContext(
        ContactRequest $contact,
        TransformationImplementationPlan $plan,
        TransformationImplementationDefinition $definition
    ): void {
        $this->assertContext(
            $contact,
            $plan
        );

        abort_unless(
            (int)
                $definition
                    ->transformation_implementation_plan_id
            === (int) $plan->id,
            404
        );
    }

    private function assertContext(
        ContactRequest $contact,
        TransformationImplementationPlan $plan
    ): void {
        $assessment =
            $plan->assessment()
                ->firstOrFail();

        $linked =
            DB::table(
                'diagnosis_access_requests'
            )
                ->where(
                    'contact_request_id',
                    $contact->id
                )
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->exists();

        abort_unless(
            $linked,
            404
        );
    }

    private function definitionPayload(
        TransformationImplementationDefinition $definition
    ): array {
        return [
            'id' =>
                $definition->id,

            'version' =>
                $definition->version,

            'status' =>
                $definition->status,

            'implementation_scope' =>
                $definition
                    ->implementation_scope
                ?? [],

            'deliverables' =>
                $definition->deliverables
                ?? [],

            'dependencies' =>
                $definition->dependencies
                ?? [],

            'responsibility_model' =>
                $definition
                    ->responsibility_model
                ?? [],

            'readiness' =>
                $definition->readiness
                ?? [],

            'reviewed_at' =>
                $definition->reviewed_at
                    ?->toISOString(),

            'ready_at' =>
                $definition->ready_at
                    ?->toISOString(),

            'editable' =>
                $definition->isEditable(),

            'is_ready' =>
                $definition->isReady(),
        ];
    }
}
