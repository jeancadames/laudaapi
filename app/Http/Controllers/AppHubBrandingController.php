<?php

namespace App\Http\Controllers;

use App\Models\TransformationCapabilityActivation;
use App\Services\Ecosystem\BrandingIdentityWorkspaceService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AppHubBrandingController extends Controller
{
    public function __invoke(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        BrandingIdentityWorkspaceService $workspace
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

        abort_unless($subscriberId > 0, 403);

        $tenantAccess = $tenantAccessService->resolve(
            $user,
            $subscriberId
        );

        abort_unless(
            ($tenantAccess['mode'] ?? null)
                === TenantAccessService::SUBSCRIBER_ADMIN
            && (bool) ($tenantAccess['tenant_admin'] ?? false),
            403
        );

        $company = $companyResolver->resolve(
            $user,
            $subscriberId
        );

        abort_unless($company, 404);

        $activation =
            TransformationCapabilityActivation::query()
                ->where('company_id', $company->id)
                ->where('capability_key', 'branding_identity')
                ->where(
                    'status',
                    '!=',
                    TransformationCapabilityActivation::STATUS_CANCELLED
                )
                ->orderByDesc('activated_at')
                ->orderByDesc('id')
                ->first();

        abort_unless($activation, 404);

        return Inertia::render(
            'App/BrandingIdentity',
            [
                'company' => [
                    'id' => (int) $company->id,
                    'name' =>
                        $company->name
                        ?? $company->business_name
                        ?? 'Mi empresa',
                ],
                'branding' =>
                    $workspace->forActivation($activation),
            ]
        );
    }
}
