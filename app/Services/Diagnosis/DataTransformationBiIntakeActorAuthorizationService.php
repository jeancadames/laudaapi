<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Authorization boundary shared by LAUDA Admin and Tenant Admin
 * for the client-owned Data BI intake workspace.
 *
 * This service does not authorize:
 * - generic subscriber users;
 * - arbitrary companies inside the same subscriber;
 * - browser-supplied company context;
 * - cross-tenant implementation requests.
 *
 * Exact Company context is always resolved server-side.
 */
final class DataTransformationBiIntakeActorAuthorizationService
{
    public function __construct(
        private readonly SubscriberResolver $subscriberResolver,
        private readonly CompanyContextResolver $companyResolver,
        private readonly TenantAccessService $tenantAccessService
    ) {
    }

    public function assertCanManage(
        TransformationImplementationRequest $implementationRequest,
        User $actor
    ): void {
        /*
         * Lifecycle is server-owned and applies equally to
         * LAUDA Admin and Tenant Admin.
         *
         * Actor authorization answers WHO may operate.
         * This gate answers WHEN the workspace may operate.
         */
        app(
            DataTransformationBiTenantSourceWorkspaceGate::class
        )->assertCanManage(
            $implementationRequest
        );

        /*
         * LAUDA Admin keeps its existing operational capability.
         *
         * Request/session/source integrity continues to be validated by
         * the domain services themselves.
         */
        if ((string) ($actor->role ?? '') === 'admin') {
            return;
        }

        /*
         * Only subscriber identities can enter the Tenant Admin path.
         */
        if ((string) ($actor->role ?? '') !== 'subscriber') {
            throw $this->denied();
        }

        /*
         * Fail closed before resolving tenant context.
         */
        if (
            ! $implementationRequest->exists
            || (int) $implementationRequest->getKey() <= 0
            || (int) $implementationRequest->company_id <= 0
            || (string) $implementationRequest->capability_key
                !== 'data_transformation_bi'
        ) {
            throw $this->denied();
        }

        $subscriberId =
            (int) (
                $this->subscriberResolver->resolve(
                    $actor
                )
                ?? 0
            );

        if ($subscriberId <= 0) {
            throw $this->denied();
        }

        /*
         * Membership alone is insufficient.
         *
         * The user must be the resolved Tenant Admin for this subscriber.
         */
        $tenantAccess =
            $this->tenantAccessService->resolve(
                $actor,
                $subscriberId
            );

        if (
            ($tenantAccess['mode'] ?? null)
                !== TenantAccessService::SUBSCRIBER_ADMIN
            || (bool) (
                $tenantAccess['tenant_admin']
                ?? false
            ) !== true
        ) {
            throw $this->denied();
        }

        /*
         * Resolve exact Company context server-side.
         *
         * This prevents a Tenant Admin from presenting an implementation
         * request belonging to another Company, including another Company
         * that might exist under a related subscriber context.
         */
        $company =
            $this->companyResolver->resolve(
                $actor,
                $subscriberId
            );

        if (
            $company === null
            || (int) $company->getKey()
                !== (int) $implementationRequest->company_id
            || (int) ($company->subscriber_id ?? 0)
                !== $subscriberId
        ) {
            throw $this->denied();
        }
    }

    private function denied(): AuthorizationException
    {
        return new AuthorizationException(
            'No tienes autorización para gestionar este workspace de datos.'
        );
    }
}
