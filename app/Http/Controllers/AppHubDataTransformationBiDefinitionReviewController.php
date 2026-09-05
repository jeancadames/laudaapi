<?php

namespace App\Http\Controllers;

use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionTenantDecisionService;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AppHubDataTransformationBiDefinitionReviewController
    extends Controller
{
    public function requestChanges(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        TransformationImplementationRequestDefinitionTenantDecisionService $decisions
    ): RedirectResponse {
        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        abort_unless(
            ($user->role ?? null)
                === 'subscriber',
            403
        );

        $subscriberId =
            (int) (
                $subscriberResolver
                    ->resolve(
                        $user
                    )
                ?? 0
            );

        abort_unless(
            $subscriberId > 0,
            403
        );

        $tenantAccess =
            $tenantAccessService
                ->resolve(
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
            $companyResolver
                ->resolve(
                    $user,
                    $subscriberId
                );

        abort_unless(
            $company,
            404
        );

        $validated =
            $request->validate([
                'reason' => [
                    'required',
                    'string',
                    'min:10',
                    'max:4000',
                ],
            ]);

        /*
         * El navegador no envía Request ID ni Definition ID.
         *
         * Ambos se resuelven desde el contexto autenticado
         * Company + capability.
         */
        $implementationRequest =
            TransformationImplementationRequest::query()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'capability_key',
                    'data_transformation_bi'
                )
                ->where(
                    'status',
                    TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        abort_unless(
            $implementationRequest,
            404
        );

        $definition =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'diagnosis_assessment_id',
                    $implementationRequest
                        ->diagnosis_assessment_id
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
                    'data_transformation_bi'
                )
                ->orderByDesc(
                    'version'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        abort_unless(
            $definition,
            404
        );

        $decisions
            ->requestChanges(
                $implementationRequest,
                $definition,
                $user,
                (string) $validated['reason']
            );

        return back()->with(
            'success',
            'Tu solicitud de cambios fue enviada a LAUDA.'
        );
    }

    /**
     * El Tenant Admin acuerda explícitamente la Definition
     * actualmente presentada para BI.
     *
     * El navegador NO controla:
     * - Request id
     * - Definition id
     * - Company id
     * - capability id
     *
     * Todo se resuelve desde el tenant autenticado.
     */
    public function agree(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        TransformationImplementationRequestDefinitionTenantDecisionService $decisions
    ): RedirectResponse {
        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        abort_unless(
            ($user->role ?? null)
                === 'subscriber',
            403
        );

        $subscriberId =
            (int) (
                $subscriberResolver
                    ->resolve(
                        $user
                    )
                ?? 0
            );

        abort_unless(
            $subscriberId > 0,
            403
        );

        $tenantAccess =
            $tenantAccessService
                ->resolve(
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
            $companyResolver
                ->resolve(
                    $user,
                    $subscriberId
                );

        abort_unless(
            $company,
            404
        );

        /*
         * Resolver exclusivamente la solicitud BI que actualmente
         * espera decisión de ESTA empresa.
         */
        $implementationRequest =
            TransformationImplementationRequest::query()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'capability_key',
                    'data_transformation_bi'
                )
                ->where(
                    'status',
                    TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        abort_unless(
            $implementationRequest,
            404
        );

        /*
         * Resolver server-side la última Definition exacta
         * presentada para ese Request.
         */
        $definition =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'diagnosis_assessment_id',
                    $implementationRequest
                        ->diagnosis_assessment_id
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
                    'data_transformation_bi'
                )
                ->orderByDesc(
                    'version'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        abort_unless(
            $definition,
            404
        );

        /*
         * Defense in depth:
         * el dominio vuelve a validar tenant, Company,
         * exact context, latest version y human review.
         */
        $decisions
            ->agree(
                $implementationRequest,
                $definition,
                $user
            );

        return back()->with(
            'success',
            'La Definition fue acordada por tu empresa.'
        );
    }

}
