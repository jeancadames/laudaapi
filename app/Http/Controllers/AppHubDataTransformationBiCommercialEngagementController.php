<?php

namespace App\Http\Controllers;

use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\TransformationImplementationCommercialEngagementService;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AppHubDataTransformationBiCommercialEngagementController
    extends Controller
{
    public function accept(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        TransformationImplementationCommercialEngagementService $commercial
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
                $subscriberResolver->resolve(
                    $user
                )
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

        /*
         * Browser does not supply:
         * - Company id
         * - implementation Request id
         * - Commercial Engagement id
         *
         * Everything is resolved from the authenticated tenant.
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
                    TransformationImplementationRequestContract
                        ::STATUS_READY_FOR_COMMERCIAL
                )
                ->orderByDesc(
                    'attempt'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        abort_unless(
            $implementationRequest,
            404
        );

        $engagement =
            TransformationImplementationCommercialEngagement::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'company_id',
                    $company->id
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
                ->where(
                    'status',
                    TransformationImplementationCommercialEngagement
                        ::STATUS_PRESENTED
                )
                ->orderByDesc(
                    'version'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        abort_unless(
            $engagement,
            404
        );

        /*
         * Defense in depth:
         * the domain service revalidates Tenant Admin,
         * Request, Definition and exact engagement context.
         */
        $commercial->accept(
            $engagement,
            $user
        );

        return back()->with(
            'success',
            'La propuesta comercial fue aceptada por tu empresa.'
        );
    }
}
