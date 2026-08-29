<?php

namespace App\Http\Controllers;

use App\Services\Ecosystem\SubscriberTransformation360DashboardService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class AppHubTransformationController extends Controller
{
    public function __invoke(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        SubscriberTransformation360DashboardService $transformation360Dashboard
    ) {
        $user = $request->user();

        abort_unless($user, 403);

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('dashboard');
        }

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

        $company = $companyResolver->resolve(
            $user,
            $subscriberId
        );

        abort_unless(
            $company,
            404
        );

        $tenantAccess = $tenantAccessService->resolve(
            $user,
            $subscriberId
        );

        $transformation360 =
            $transformation360Dashboard->forCompany(
                $company,
                (int) $user->id
            );

        /*
         * El propio read-model exige owner/admin del tenant.
         * Un subscriber.user no puede consultar esta sección.
         */
        abort_unless(
            (bool) (
                $transformation360['visible']
                ?? false
            ),
            403
        );

        return Inertia::render(
            'App/Transformation360',
            [
                'company' => [
                    'id' => $company->id,
                    'name' =>
                        $company->name
                        ?? $company->business_name
                        ?? 'Mi empresa',
                    'subscriber_id' =>
                        $company->subscriber_id,
                ],

                'tenant_access' =>
                    $tenantAccess,

                'transformation360' =>
                    $transformation360,
            ]
        );
    }
}
