<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\Service;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\TransformationImplementationCapabilityServiceMapping;
use App\Models\TransformationImplementationSubscriptionActivation;
use App\Models\TransformationImplementationSubscriptionItemActivation;
use App\Services\Entitlements\CentralEntitlementActivationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationCapabilitySubscriptionService
{
    public function upsertMapping(
        string $capabilityKey,
        Service $service,
        ?string $capabilityLabel = null,
        ?int $userId = null,
        ?string $internalNotes = null
    ): TransformationImplementationCapabilityServiceMapping {
        $capabilityKey = trim(
            $capabilityKey
        );

        if ($capabilityKey === '') {
            throw ValidationException::withMessages([
                'capability_key' =>
                    'capability_key es obligatorio.',
            ]);
        }

        if (! $service->active) {
            throw ValidationException::withMessages([
                'service' =>
                    'No se puede mapear una capacidad '
                    .'a un Service inactivo.',
            ]);
        }

        $existing =
            TransformationImplementationCapabilityServiceMapping::query()
                ->where(
                    'capability_key',
                    $capabilityKey
                )
                ->first();

        return TransformationImplementationCapabilityServiceMapping::query()
            ->updateOrCreate(
                [
                    'capability_key' =>
                        $capabilityKey,
                ],
                [
                    'capability_label' =>
                        $capabilityLabel !== null
                            ? trim(
                                $capabilityLabel
                            )
                            : $existing?->capability_label,
                    'service_id' =>
                        $service->id,
                    'active' => true,
                    'internal_notes' =>
                        $internalNotes !== null
                            ? trim(
                                $internalNotes
                            )
                            : $existing?->internal_notes,
                    'created_by_user_id' =>
                        $existing?->created_by_user_id
                        ?? $userId,
                    'updated_by_user_id' =>
                        $userId,
                ]
            );
    }

    public function activateFromGoLive(
        TransformationImplementationCapabilityGoLive $goLive,
        TransformationImplementationSubscriptionActivation $subscriptionActivation,
        ?int $userId = null
    ): TransformationImplementationSubscriptionItemActivation {
        $goLive->loadMissing(
            'capability'
        );

        if (
            $goLive->status
            !== TransformationImplementationCapabilityGoLive::STATUS_LIVE
            || ! $goLive->went_live_at
        ) {
            throw ValidationException::withMessages([
                'go_live' =>
                    'Solo una capacidad con Go-Live LIVE '
                    .'puede activar un SubscriptionItem.',
            ]);
        }

        if (
            (int) $subscriptionActivation
                ->transformation_implementation_capability_go_live_id
            !== (int) $goLive->id
        ) {
            throw ValidationException::withMessages([
                'subscription_activation' =>
                    'La activación de suscripción debe '
                    .'pertenecer al mismo Go-Live.',
            ]);
        }

        $existingActivation =
            TransformationImplementationSubscriptionItemActivation::query()
                ->where(
                    'transformation_implementation_capability_go_live_id',
                    $goLive->id
                )
                ->first();

        if ($existingActivation) {
            return $existingActivation;
        }

        $capabilityKey = trim(
            (string)
            $goLive->capability?->capability_key
        );

        if ($capabilityKey === '') {
            throw ValidationException::withMessages([
                'capability_key' =>
                    'La capacidad del Go-Live no tiene '
                    .'capability_key.',
            ]);
        }

        $mapping =
            TransformationImplementationCapabilityServiceMapping::query()
                ->with('service')
                ->where(
                    'capability_key',
                    $capabilityKey
                )
                ->where(
                    'active',
                    true
                )
                ->first();

        if (! $mapping) {
            throw ValidationException::withMessages([
                'mapping' =>
                    'No existe un mapping activo '
                    .'capability_key → Service para '
                    .'esta capacidad.',
            ]);
        }

        $service = $mapping->service;

        if (
            ! $service
            || ! $service->active
        ) {
            throw ValidationException::withMessages([
                'service' =>
                    'El Service mapeado no existe '
                    .'o está inactivo.',
            ]);
        }

        $subscriber = Subscriber::query()
            ->find(
                $subscriptionActivation->subscriber_id
            );

        $company = Company::query()
            ->find(
                $subscriptionActivation->company_id
            );

        if (
            ! $subscriber
            || ! $company
        ) {
            throw ValidationException::withMessages([
                'identity' =>
                    'R2-I no tiene Subscriber/Company '
                    .'válidos para esta activación.',
            ]);
        }

        $subscription = Subscription::query()
            ->whereKey(
                $subscriptionActivation->subscription_id
            )
            ->where(
                'subscriber_id',
                $subscriptionActivation->subscriber_id
            )
            ->where(
                'status',
                'active'
            )
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' =>
                    'La Subscription vinculada por R2-I '
                    .'debe permanecer active.',
            ]);
        }

        if (
            $subscription->starts_at
            && $subscription->starts_at->gt(
                $goLive->went_live_at
            )
        ) {
            throw ValidationException::withMessages([
                'subscription' =>
                    'La Subscription no puede iniciar '
                    .'después del Go-Live que activa '
                    .'esta capacidad.',
            ]);
        }

        return DB::transaction(function () use (
            $goLive,
            $subscriptionActivation,
            $subscription,
            $subscriber,
            $company,
            $mapping,
            $service,
            $capabilityKey,
            $userId
        ) {
            /*
             * Orden global:
             * Subscriber → Subscription → Item.
             *
             * R2-J adquiere los parents antes de
             * revalidar su ledger. El owner central
             * usa exactamente el mismo orden.
             */
            $subscriber = Subscriber::query()
                ->whereKey(
                    $subscriber->id
                )
                ->lockForUpdate()
                ->firstOrFail();

            $subscription =
                Subscription::query()
                    ->whereKey(
                        $subscription->id
                    )
                    ->where(
                        'subscriber_id',
                        $subscriptionActivation
                            ->subscriber_id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->lockForUpdate()
                    ->first();

            if (! $subscription) {
                throw ValidationException::withMessages([
                    'subscription' =>
                        'La Subscription vinculada por '
                        .'R2-I debe permanecer active '
                        .'durante R2-J.',
                ]);
            }

            $lockedActivation =
                TransformationImplementationSubscriptionItemActivation::query()
                    ->where(
                        'transformation_implementation_capability_go_live_id',
                        $goLive->id
                    )
                    ->first();

            if ($lockedActivation) {
                return $lockedActivation;
            }

            $central = app(
                CentralEntitlementActivationService::class
            )->activateCommercialItem(
                $subscriber,
                $company,
                $service,
                $subscription,
                CentralEntitlementActivationService::SOURCE_TRANSFORMATION_360,
                $userId,
                $goLive->went_live_at,
                [
                    'activation_mode' =>
                        'post_go_live',
                    'capability_key' =>
                        $capabilityKey,
                    'mapping_id' =>
                        (int) $mapping->id,
                    'go_live_id' =>
                        (int) $goLive->id,
                    'went_live_at' =>
                        $goLive->went_live_at
                            ?->toIso8601String(),
                ]
            );

            $item = $central['item'];

            /*
             * Ledger histórico R2-J conserva
             * created/reused. Una reactivación central
             * reutiliza la misma fila económica, por
             * tanto se registra como reused.
             */
            $activationType =
                $central['item_activation']
                === 'created'
                    ? TransformationImplementationSubscriptionItemActivation::TYPE_CREATED
                    : TransformationImplementationSubscriptionItemActivation::TYPE_REUSED;

            return TransformationImplementationSubscriptionItemActivation::query()
                ->create([
                    'transformation_implementation_capability_go_live_id' =>
                        $goLive->id,
                    'transformation_implementation_subscription_activation_id' =>
                        $subscriptionActivation->id,
                    'transformation_implementation_capability_service_mapping_id' =>
                        $mapping->id,
                    'service_id' =>
                        $service->id,
                    'subscription_item_id' =>
                        $item->id,
                    'activation_type' =>
                        $activationType,
                    'status' =>
                        TransformationImplementationSubscriptionItemActivation::STATUS_ACTIVE,
                    'price_snapshot' =>
                        $central['pricing'],
                    'activated_at' =>
                        now(),
                    'created_by_user_id' =>
                        $userId,
                ]);
        }, 3);
    }
}
