<?php

namespace App\Http\Controllers;

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

                'capability' =>
                    $capability,
            ]
        );
    }
}
