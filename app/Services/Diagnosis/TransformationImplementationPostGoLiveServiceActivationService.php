<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\TransformationImplementationSubscriptionActivation;
use App\Models\TransformationImplementationSubscriptionItemActivation;
use Illuminate\Validation\ValidationException;

class TransformationImplementationPostGoLiveServiceActivationService
{
    public function __construct(
        private readonly TransformationImplementationCapabilitySubscriptionService $capabilitySubscriptionService,
    ) {
    }

    public function activateServiceForGoLive(
        TransformationImplementationCapabilityGoLive $goLive,
        ?int $userId = null
    ): TransformationImplementationSubscriptionItemActivation {
        $goLive->loadMissing('capability');

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
                    'El Service solo puede activarse después de un Go-Live LIVE.',
            ]);
        }

        $subscriptionActivation =
            TransformationImplementationSubscriptionActivation::query()
                ->where(
                    'transformation_implementation_capability_go_live_id',
                    $goLive->id
                )
                ->where(
                    'status',
                    TransformationImplementationSubscriptionActivation::STATUS_ACTIVE
                )
                ->first();

        if (! $subscriptionActivation) {
            throw ValidationException::withMessages([
                'subscription_activation' =>
                    'Primero debe completarse R2-I para vincular este Go-Live a la Subscription general.',
            ]);
        }

        return $this->capabilitySubscriptionService
            ->activateFromGoLive(
                $goLive,
                $subscriptionActivation,
                $userId
            );
    }
}
