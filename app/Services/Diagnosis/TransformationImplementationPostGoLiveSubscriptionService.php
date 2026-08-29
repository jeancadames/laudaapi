<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\TransformationImplementationSubscriptionActivation;
use App\Services\Commercial\CommercialCustomerProvisioningService;
use Illuminate\Validation\ValidationException;

class TransformationImplementationPostGoLiveSubscriptionService
{
    public function __construct(
        private readonly CommercialCustomerProvisioningService $provisioningService,
        private readonly TransformationImplementationSubscriptionService $subscriptionService,
    ) {
    }

    public function activateSubscriptionForGoLive(
        TransformationImplementationCapabilityGoLive $goLive,
        ?int $userId = null,
        string $billingCycle = 'monthly'
    ): TransformationImplementationSubscriptionActivation {
        $goLive->loadMissing(
            'capability.phase.plan.assessment'
        );

        $capabilityKey = trim(
            (string) $goLive->capability?->capability_key
        );

        if (
            TransformationProfessionalCapabilityCatalog::isProfessional(
                $capabilityKey
            )
        ) {
            throw ValidationException::withMessages([
                'capability' =>
                    'Esta capability es un servicio profesional de '
                    .'Transformación 360. Su ejecución y Go-Live se '
                    .'controlan dentro del Plan, pero no genera '
                    .'Subscription ni SubscriptionItem.',
            ]);
        }


        if (
            $goLive->status
            !== TransformationImplementationCapabilityGoLive::STATUS_LIVE
            || ! $goLive->went_live_at
        ) {
            throw ValidationException::withMessages([
                'go_live' =>
                    'La identidad comercial y la Subscription solo pueden activarse después de un Go-Live LIVE.',
            ]);
        }

        $plan = $goLive->capability?->phase?->plan;
        $assessment = $plan?->assessment;

        if (! $plan || ! $assessment) {
            throw ValidationException::withMessages([
                'assessment' =>
                    'El Go-Live debe pertenecer a un Plan con diagnóstico vinculado.',
            ]);
        }

        $identity = $this->provisioningService
            ->ensureForAssessment($assessment);

        return $this->subscriptionService
            ->activateFromGoLive(
                $goLive,
                $identity['subscriber'],
                $identity['company'],
                $userId,
                $billingCycle,
                $identity['company']->currency
                    ?: $identity['subscriber']->currency
                    ?: 'DOP'
            );
    }
}
