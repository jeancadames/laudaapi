<?php

namespace App\Services\Diagnosis;

use App\Models\Service;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\TransformationImplementationCapabilityServiceMapping;
use App\Models\TransformationImplementationSubscriptionActivation;
use App\Models\TransformationImplementationSubscriptionItemActivation;
use App\Services\Billing\ServicePricingEngine;
use App\Services\Billing\SubscriptionTotalsService;
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
        $capabilityKey = trim($capabilityKey);

        if ($capabilityKey === '') {
            throw ValidationException::withMessages([
                'capability_key' => 'capability_key es obligatorio.',
            ]);
        }

        if (!$service->active) {
            throw ValidationException::withMessages([
                'service' => 'No se puede mapear una capacidad a un Service inactivo.',
            ]);
        }

        $existing = TransformationImplementationCapabilityServiceMapping::query()
            ->where('capability_key', $capabilityKey)
            ->first();

        return TransformationImplementationCapabilityServiceMapping::query()->updateOrCreate(
            ['capability_key' => $capabilityKey],
            [
                'capability_label' => $capabilityLabel !== null
                    ? trim($capabilityLabel)
                    : $existing?->capability_label,
                'service_id' => $service->id,
                'active' => true,
                'internal_notes' => $internalNotes !== null
                    ? trim($internalNotes)
                    : $existing?->internal_notes,
                'created_by_user_id' => $existing?->created_by_user_id ?? $userId,
                'updated_by_user_id' => $userId,
            ]
        );
    }

    public function activateFromGoLive(
        TransformationImplementationCapabilityGoLive $goLive,
        TransformationImplementationSubscriptionActivation $subscriptionActivation,
        ?int $userId = null
    ): TransformationImplementationSubscriptionItemActivation {
        $goLive->loadMissing('capability');

        if (
            $goLive->status !== TransformationImplementationCapabilityGoLive::STATUS_LIVE
            || !$goLive->went_live_at
        ) {
            throw ValidationException::withMessages([
                'go_live' =>
                    'Solo una capacidad con Go-Live LIVE puede activar un SubscriptionItem.',
            ]);
        }

        if (
            (int) $subscriptionActivation->transformation_implementation_capability_go_live_id
            !== (int) $goLive->id
        ) {
            throw ValidationException::withMessages([
                'subscription_activation' =>
                    'La activación de suscripción debe pertenecer al mismo Go-Live.',
            ]);
        }

        $existingActivation = TransformationImplementationSubscriptionItemActivation::query()
            ->where(
                'transformation_implementation_capability_go_live_id',
                $goLive->id
            )
            ->first();

        if ($existingActivation) {
            return $existingActivation;
        }

        $capabilityKey = trim((string) $goLive->capability?->capability_key);

        if ($capabilityKey === '') {
            throw ValidationException::withMessages([
                'capability_key' =>
                    'La capacidad del Go-Live no tiene capability_key.',
            ]);
        }

        $mapping = TransformationImplementationCapabilityServiceMapping::query()
            ->with('service')
            ->where('capability_key', $capabilityKey)
            ->where('active', true)
            ->first();

        if (!$mapping) {
            throw ValidationException::withMessages([
                'mapping' =>
                    'No existe un mapping activo capability_key → Service para esta capacidad.',
            ]);
        }

        $service = $mapping->service;

        if (!$service || !$service->active) {
            throw ValidationException::withMessages([
                'service' =>
                    'El Service mapeado no existe o está inactivo.',
            ]);
        }

        $subscription = Subscription::query()
            ->whereKey($subscriptionActivation->subscription_id)
            ->where('subscriber_id', $subscriptionActivation->subscriber_id)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            throw ValidationException::withMessages([
                'subscription' =>
                    'La Subscription vinculada por R2-I debe permanecer active.',
            ]);
        }

        if (
            $subscription->starts_at
            && $subscription->starts_at->gt($goLive->went_live_at)
        ) {
            throw ValidationException::withMessages([
                'subscription' =>
                    'La Subscription no puede iniciar después del Go-Live que activa esta capacidad.',
            ]);
        }

        return DB::transaction(function () use (
            $goLive,
            $subscriptionActivation,
            $subscription,
            $mapping,
            $service,
            $capabilityKey,
            $userId
        ) {
            /*
             * PASO 9E-B:
             * Subscription es el mutex de sus SubscriptionItems.
             *
             * Si el item todavía no existe, dos requests no pueden
             * decidir create al mismo tiempo: el segundo espera aquí.
             */
            $subscription = Subscription::query()
                ->whereKey($subscription->id)
                ->where('subscriber_id', $subscriptionActivation->subscriber_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                throw ValidationException::withMessages([
                    'subscription' =>
                        'La Subscription vinculada por R2-I debe permanecer active durante R2-J.',
                ]);
            }

            /*
             * El fast-path previo a la transacción es solo optimización.
             * La decisión idempotente definitiva ocurre después del lock.
             */
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

            $existingItem = SubscriptionItem::query()
                ->where('subscription_id', $subscription->id)
                ->where('service_id', $service->id)
                ->lockForUpdate()
                ->first();

            $itemPayload = $this->buildActiveItem(
                $service,
                $subscription,
                $goLive,
                $mapping,
                $capabilityKey
            );

            $activationType =
                TransformationImplementationSubscriptionItemActivation::TYPE_REUSED;

            if ($existingItem) {
                $wasAlreadyActive = in_array(
                    strtolower(
                        (string) $existingItem->status
                    ),
                    ['active', 'trialing'],
                    true
                );

                if ($wasAlreadyActive) {
                    $item = $existingItem;
                } else {
                    $existingItem->forceFill(
                        $itemPayload
                    )->save();

                    $item = $existingItem->fresh();
                }
            } else {
                $item = SubscriptionItem::query()
                    ->create(
                        $itemPayload
                    );

                $activationType =
                    TransformationImplementationSubscriptionItemActivation::TYPE_CREATED;
            }

            $this->recalculateSubscriptionTotals($subscription);

            return TransformationImplementationSubscriptionItemActivation::query()->create([
                'transformation_implementation_capability_go_live_id' => $goLive->id,
                'transformation_implementation_subscription_activation_id' =>
                    $subscriptionActivation->id,
                'transformation_implementation_capability_service_mapping_id' =>
                    $mapping->id,
                'service_id' => $service->id,
                'subscription_item_id' => $item->id,
                'activation_type' => $activationType,
                'status' => TransformationImplementationSubscriptionItemActivation::STATUS_ACTIVE,
                'price_snapshot' => $this->buildPriceSnapshot($service, $subscription),
                'activated_at' => now(),
                'created_by_user_id' => $userId,
            ]);
        }, 3);
    }

    private function buildActiveItem(
        Service $service,
        Subscription $subscription,
        TransformationImplementationCapabilityGoLive $goLive,
        TransformationImplementationCapabilityServiceMapping $mapping,
        string $capabilityKey
    ): array {
        $snapshot = $this->buildPriceSnapshot($service, $subscription);

        return [
            'subscription_id' => $subscription->id,
            'service_id' => $service->id,
            'status' => 'active',
            'billing_model' => $snapshot['billing_model'],
            'quantity' => $snapshot['quantity'],
            'unit_price' => $snapshot['unit_price'],
            'amount' => $snapshot['amount'],
            'currency' => $snapshot['currency'],
            'block_size' => $snapshot['block_size'],
            'unit_name' => $snapshot['unit_name'],
            'included_units' => $snapshot['included_units'],
            'overage_unit_price' => $snapshot['overage_unit_price'],
            'meta' => [
                'source' => 'transformation_360',
                'activation_mode' => 'post_go_live',
                'capability_key' => $capabilityKey,
                'mapping_id' => $mapping->id,
                'go_live_id' => $goLive->id,
                'went_live_at' => $goLive->went_live_at?->toIso8601String(),
                'activated_at' => now()->toISOString(),
                'pricing' => [
                    'pricing_version' =>
                        $snapshot['pricing_version'] ?? null,
                    'quantity_source' =>
                        $snapshot['quantity_source'] ?? null,
                    'tier_id' =>
                        $snapshot['tier_id'] ?? null,
                    'tier_min_quantity' =>
                        $snapshot['tier_min_quantity'] ?? null,
                    'tier_max_quantity' =>
                        $snapshot['tier_max_quantity'] ?? null,
                ],
            ],
        ];
    }

    private function buildPriceSnapshot(
        Service $service,
        Subscription $subscription
    ): array {
        return app(ServicePricingEngine::class)
            ->quote(
                $service,
                $subscription
            );
    }

        private function recalculateSubscriptionTotals(
        Subscription $subscription
    ): void {
        app(SubscriptionTotalsService::class)
            ->recalculate(
                $subscription
            );
    }
}
