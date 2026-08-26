<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\User;
use App\Services\LaudaErp\ServiceAccessResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\CompanyContextResolver;

class TransformationImplementationClientSolutionAccessService
{
    public function __construct(
        private readonly ServiceAccessResolver $accessResolver,
        private readonly SubscriberResolver $subscriberResolver,
        private readonly CompanyContextResolver $companyContextResolver,
    ) {
    }

    public function resolve(
        ?User $user,
        ?TransformationImplementationCapabilityGoLive $goLive
    ): ?array {
        if (! $user || ! $goLive) {
            return null;
        }

        $goLive->loadMissing([
            'subscriptionItemActivation.service',
            'subscriptionItemActivation.subscriptionItem.subscription',
        ]);

        $activation =
            $goLive->subscriptionItemActivation;

        if (! $activation) {
            return null;
        }

        $service = $activation->service;
        $item = $activation->subscriptionItem;
        $subscription = $item?->subscription;

        if (! $service || ! $item || ! $subscription) {
            return [
                'service_id' =>
                    $activation->service_id,
                'service_name' =>
                    $service?->title,
                'service_slug' =>
                    $service?->slug,
                'subscription_status' =>
                    $subscription?->status,
                'subscription_item_status' =>
                    $item?->status,
                'entitlement_allowed' =>
                    false,
                'access_url' =>
                    null,
            ];
        }

        $company =
            $this->resolveCompany($user);

        $allowed =
            $company !== null
            && $this->accessResolver->userCanAccess(
                $user,
                $company,
                $service
            );

        return [
            'service_id' =>
                $service->id,
            'service_name' =>
                $service->title,
            'service_slug' =>
                $service->slug,
            'subscription_status' =>
                $subscription->status,
            'subscription_item_status' =>
                $item->status,
            'entitlement_allowed' =>
                $allowed,
            'access_url' =>
                $allowed && trim((string) $service->slug) !== ''
                    ? route(
                        'erp.services.open',
                        ['service' => $service->slug],
                        false
                    )
                    : null,
        ];
    }

    private function resolveCompany(
        User $user
    ): ?Company {
        $subscriberId = (int) (
            $this->subscriberResolver->resolve($user)
            ?? 0
        );

        return $this->companyContextResolver->resolve(
            $user,
            $subscriberId
        );
    }
}
