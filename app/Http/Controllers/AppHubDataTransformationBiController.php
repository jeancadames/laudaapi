<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Diagnosis\TransformationProfessionalCapabilityCatalog;
use App\Services\Ecosystem\SubscriberTransformation360DashboardService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AppHubDataTransformationBiController
    extends Controller
{
    public function __invoke(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        SubscriberTransformation360DashboardService $dashboard
    ): Response {
        $user = $request->user();

        abort_unless($user, 403);

        abort_unless(
            ($user->role ?? null) === 'subscriber',
            403
        );

        $subscriberId = (int) (
            $subscriberResolver->resolve($user)
            ?? 0
        );

        abort_unless(
            $subscriberId > 0,
            403
        );

        $tenantAccess =
            $tenantAccessService->resolve(
                $user,
                $subscriberId
            );

        abort_unless(
            ($tenantAccess['mode'] ?? null)
                === TenantAccessService::SUBSCRIBER_ADMIN
            && (bool) (
                $tenantAccess['tenant_admin']
                ?? false
            ),
            403
        );

        $company =
            $companyResolver->resolve(
                $user,
                $subscriberId
            );

        abort_unless(
            $company,
            404
        );

        $transformation360 =
            $dashboard->forCompany(
                $company,
                (int) $user->id
            );

        abort_unless(
            (bool) (
                $transformation360['visible']
                ?? false
            ),
            403
        );

        $capability =
            data_get(
                $transformation360,
                'professional_capabilities.data_transformation_bi'
            );

        if (! is_array($capability)) {
            $catalog =
                TransformationProfessionalCapabilityCatalog::get(
                    'data_transformation_bi'
                );

            abort_unless(
                is_array($catalog),
                404
            );

            $capability = [
                'capability_key' =>
                    'data_transformation_bi',

                'title' =>
                    (string) (
                        $catalog['title']
                        ?? 'Transformación e Inteligencia de Datos para BI'
                    ),

                'kind' =>
                    'professional_service',

                'recommended' =>
                    false,

                'recommendation_basis' =>
                    null,

                'data_dimension_score' =>
                    null,

                'data_priority' =>
                    null,

                'purpose' =>
                    $catalog['purpose']
                    ?? null,

                'includes' =>
                    is_array(
                        $catalog['includes']
                        ?? null
                    )
                        ? $catalog['includes']
                        : [],

                'activation_policy' =>
                    'implementation_only',

                'commercial_note' =>
                    null,

                'recommended_in_plan' =>
                    false,

                'phase_sequence' =>
                    null,

                'phase_name' =>
                    null,

                'roadmap_url' =>
                    null,

                'plan_url' =>
                    null,

                'detail_url' =>
                    route(
                        'app.transformation.data_bi.show',
                        [],
                        false
                    ),
            ];
        }

        $hasWorkflow =
            (bool) (
                $transformation360['has_workflow']
                ?? false
            );

        $capability['recommendation_status'] =
            ($capability['recommended'] ?? false)
                ? 'recommended'
                : (
                    $hasWorkflow
                        ? 'not_recommended'
                        : 'not_evaluated'
                );


        $implementationRequest =
            $this->implementationRequestState(
                $company,
                $transformation360
            );

        return Inertia::render(
            'App/DataTransformationBi',
            [
                'company' => [
                    'id' =>
                        (int) $company->id,

                    'name' =>
                        $company->name
                        ?? $company->business_name
                        ?? 'Mi empresa',
                ],

                'transformation360' => [
                    'has_workflow' =>
                        $hasWorkflow,

                    'assessment_id' =>
                        $transformation360[
                            'assessment_id'
                        ]
                        ?? null,

                    'current_label' =>
                        $transformation360[
                            'current_label'
                        ]
                        ?? null,

                    'plan_public' =>
                        (bool) (
                            $transformation360[
                                'plan_public'
                            ]
                            ?? false
                        ),
                ],
                'implementation_request' =>
                    $implementationRequest,



                'capability' =>
                    $capability,
            ]
        );
    }

    /**
     * Read-model de la solicitud de implementación de BI.
     *
     * Esta lectura NO crea solicitudes, Definition,
     * activaciones, ejecución ni efectos comerciales.
     */
    private function implementationRequestState(
        Company $company,
        array $transformation360
    ): array {
        $empty = [
            'id' => null,
            'status' => null,
            'status_label' => 'No solicitada',
            'requested_at' => null,
            'attempt' => null,
            'can_request' => false,
            'request_endpoint' => null,
                    'definition_review' => null,
            'changes_request_endpoint' => null,
            'agreement_endpoint' => null,
        ];

        $assessmentId = (int) (
            $transformation360['assessment_id']
            ?? 0
        );

        if ($assessmentId <= 0) {
            return $empty;
        }

        /*
         * La solicitud solo corresponde a un Plan
         * formalmente presentado.
         */
        $plan =
            TransformationImplementationPlan::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessmentId
                )
                ->where(
                    'status',
                    TransformationImplementationPlan::STATUS_PRESENTED
                )
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

        if (! $plan) {
            return $empty;
        }

        /*
         * Resolver BI exclusivamente dentro del Plan
         * presentado. El navegador no controla IDs.
         */
        $phaseCapability =
            TransformationImplementationPhaseCapability::query()
                ->join(
                    'transformation_implementation_phases as phase',
                    'phase.id',
                    '=',
                    'transformation_implementation_phase_capabilities.transformation_implementation_phase_id'
                )
                ->where(
                    'phase.transformation_implementation_plan_id',
                    $plan->id
                )
                ->where(
                    'transformation_implementation_phase_capabilities.capability_key',
                    'data_transformation_bi'
                )
                ->select(
                    'transformation_implementation_phase_capabilities.*'
                )
                ->orderBy('phase.sequence')
                ->orderBy(
                    'transformation_implementation_phase_capabilities.sequence'
                )
                ->first();

        if (! $phaseCapability) {
            return $empty;
        }

        /*
         * Último intento de esta capability dentro
         * de este mismo Plan y Company.
         */
        $latest =
            TransformationImplementationRequest::query()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'transformation_implementation_plan_id',
                    $plan->id
                )
                ->where(
                    'capability_key',
                    'data_transformation_bi'
                )
                ->orderByDesc('attempt')
                ->orderByDesc('id')
                ->first();

        /*
         * Sin solicitud previa:
         * puede solicitar.
         *
         * Cancelada:
         * puede crear un nuevo attempt.
         *
         * Cualquier otro estado:
         * el proceso ya está abierto o completado.
         */
        $canRequest =
            $latest === null
            || $latest->status
                === TransformationImplementationRequestContract::STATUS_CANCELLED;

        return [
            'id' =>
                $latest
                    ? (int) $latest->id
                    : null,

            'status' =>
                $latest?->status,

            'status_label' =>
                $this->implementationRequestStatusLabel(
                    $latest?->status
                ),

            'requested_at' =>
                $latest?->requested_at
                    ?->format('d/m/Y H:i'),

            'attempt' =>
                $latest
                    ? (int) $latest->attempt
                    : null,

            'can_request' =>
                $canRequest,

            'request_endpoint' =>
                $canRequest
                    ? route(
                        'app.transformation.data_bi.request',
                        [],
                        false
                    )
                    : null,

            'changes_request_endpoint' =>
                $latest
                && $latest->status
                    === TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                && $this->tenantDefinitionReview(
                    $latest
                ) !== null
                    ? route(
                        'app.transformation.data_bi.definition.request_changes'
                    )
                    : null,

            'agreement_endpoint' =>
                $latest
                && $latest->status
                    === TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                && $this->tenantDefinitionReview(
                    $latest
                ) !== null
                    ? route(
                        'app.transformation.data_bi.definition.agree'
                    )
                    : null,

            'definition_review' =>
                $latest
                    ? $this->tenantDefinitionReview(
                        $latest
                    )
                    : null,

        ];
    }

    /**
     * Projection segura de la Definition presentada al tenant.
     *
     * Solo expone contenido funcional.
     *
     * Nunca expone:
     * - source_snapshot
     * - internal_notes
     * - created_by_user_id
     * - updated_by_user_id
     * - reviewed_by_user_id
     *
     * No realiza mutaciones.
     */
    private function tenantDefinitionReview(
        TransformationImplementationRequest $implementationRequest
    ): ?array {
        /*
         * Una Definition draft o en preparación interna LAUDA
         * nunca debe quedar visible al tenant.
         */
        if (
            ! in_array(
                $implementationRequest->status,
                [
                    TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
                    TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,
                    TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
                    TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL,
                ],
                true
            )
        ) {
            return null;
        }

        /*
         * Resolver siempre desde el Request ya scopeado
         * a Company + Plan + capability por
         * implementationRequestState().
         *
         * No se acepta ningún Definition ID del navegador.
         */
        $definition =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'company_id',
                    $implementationRequest->company_id
                )
                ->where(
                    'diagnosis_assessment_id',
                    $implementationRequest->diagnosis_assessment_id
                )
                ->where(
                    'transformation_implementation_plan_id',
                    $implementationRequest
                        ->transformation_implementation_plan_id
                )
                ->where(
                    'transformation_implementation_phase_capability_id',
                    $implementationRequest
                        ->transformation_implementation_phase_capability_id
                )
                ->where(
                    'capability_key',
                    $implementationRequest->capability_key
                )
                ->orderByDesc(
                    'version'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        if (! $definition) {
            return null;
        }

        /*
         * F6 solo presenta Definitions request-scoped.
         */
        if (
            data_get(
                $definition->source_snapshot,
                'source_type'
            ) !== 'implementation_request'

            || data_get(
                $definition->implementation_scope,
                'scope_mode'
            ) !== 'single_capability'

            || data_get(
                $definition->implementation_scope,
                'definition_scope_locked_to_request'
            ) !== true
        ) {
            return null;
        }

        /*
         * Draft jamás es tenant-visible.
         *
         * Durante awaiting/changes_requested la Definition
         * permanece under_review.
         *
         * Después del acuerdo podrá llegar a STATUS_READY
         * en una fase posterior de F6.
         */
        if (
            ! in_array(
                $definition->status,
                [
                    TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
                    TransformationImplementationDefinition::STATUS_READY,
                ],
                true
            )
        ) {
            return null;
        }

        $scope =
            is_array(
                $definition->implementation_scope
            )
                ? $definition->implementation_scope
                : [];

        $deliverables =
            is_array(
                $definition->deliverables
            )
                ? array_values(
                    $definition->deliverables
                )
                : [];

        $dependencies =
            is_array(
                $definition->dependencies
            )
                ? array_values(
                    $definition->dependencies
                )
                : [];

        $responsibilityModel =
            is_array(
                $definition->responsibility_model
            )
                ? $definition->responsibility_model
                : [];

        $readiness =
            is_array(
                $definition->readiness
            )
                ? $definition->readiness
                : [];

        $humanValidation =
            is_array(
                data_get(
                    $readiness,
                    'human_validation'
                )
            )
                ? data_get(
                    $readiness,
                    'human_validation'
                )
                : [];

        $confirmations = [
            'scope_confirmed' =>
                (
                    $humanValidation[
                        'scope_confirmed'
                    ] ?? false
                ) === true,

            'deliverables_confirmed' =>
                (
                    $humanValidation[
                        'deliverables_confirmed'
                    ] ?? false
                ) === true,

            'dependencies_confirmed' =>
                (
                    $humanValidation[
                        'dependencies_confirmed'
                    ] ?? false
                ) === true,

            'inputs_validated' =>
                (
                    $humanValidation[
                        'inputs_validated'
                    ] ?? false
                ) === true,

            'accesses_validated' =>
                (
                    $humanValidation[
                        'accesses_validated'
                    ] ?? false
                ) === true,

            'responsibilities_confirmed' =>
                (
                    $humanValidation[
                        'responsibilities_confirmed'
                    ] ?? false
                ) === true,
        ];

        $humanReviewCompleted =
            ! in_array(
                false,
                array_values(
                    $confirmations
                ),
                true
            )
            && data_get(
                $responsibilityModel,
                'party_assignment_status'
            ) === 'confirmed';

        return [
            'id' =>
                (int) $definition->id,

            'version' =>
                (int) $definition->version,

            'status' =>
                (string) $definition->status,

            'capability_key' =>
                (string) $definition->capability_key,

            /*
             * Projection deliberadamente reducida del scope.
             * No se entrega source_snapshot.
             */
            'scope' => [
                'scope_mode' =>
                    data_get(
                        $scope,
                        'scope_mode'
                    ),

                'capability_key' =>
                    data_get(
                        $scope,
                        'capability_key'
                    ),

                'phases' =>
                    is_array(
                        data_get(
                            $scope,
                            'phases'
                        )
                    )
                        ? array_values(
                            data_get(
                                $scope,
                                'phases'
                            )
                        )
                        : [],
            ],

            'deliverables' =>
                $deliverables,

            'dependencies' =>
                $dependencies,

            'responsibilities' => [
                'party_assignment_status' =>
                    data_get(
                        $responsibilityModel,
                        'party_assignment_status'
                    ),

                'assignments' =>
                    is_array(
                        data_get(
                            $responsibilityModel,
                            'assignments'
                        )
                    )
                        ? array_values(
                            data_get(
                                $responsibilityModel,
                                'assignments'
                            )
                        )
                        : [],
            ],

            'human_review' => [
                'state' =>
                    data_get(
                        $readiness,
                        'state'
                    ),

                'completed' =>
                    $humanReviewCompleted,

                'confirmations' =>
                    $confirmations,

                'reviewed_at' =>
                    $definition->reviewed_at
                        ?->format(
                            'd/m/Y H:i'
                        ),
            ],

            'submitted_at' =>
                $implementationRequest
                    ->tenant_review_requested_at
                    ?->format(
                        'd/m/Y H:i'
                    ),
        ];
    }

    private function implementationRequestStatusLabel(
        ?string $status
    ): string {
        return match ($status) {
            TransformationImplementationRequestContract::STATUS_REQUESTED =>
                'Solicitud enviada',

            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW =>
                'En revisión por LAUDA',

            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION =>
                'Definición en preparación',

            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW =>
                'Pendiente de revisión de tu empresa',

            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED =>
                'Ajustes solicitados',

            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED =>
                'Definición acordada',

            TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL =>
                'Definición completada',

            TransformationImplementationRequestContract::STATUS_CANCELLED =>
                'Solicitud cancelada',

            default =>
                'No solicitada',
        };
    }

}
