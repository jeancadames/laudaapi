<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\Subscriber;
use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\TransformationImplementationSubscriptionActivation;
use App\Services\Entitlements\CentralEntitlementActivationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationSubscriptionService
{
    public function activateFromGoLive(
        TransformationImplementationCapabilityGoLive $goLive,
        Subscriber $subscriber,
        Company $company,
        ?int $userId = null,
        string $billingCycle = 'monthly',
        ?string $currency = null
    ): TransformationImplementationSubscriptionActivation {
        $goLive->loadMissing(
            'capability.phase.plan'
        );

        if (
            $goLive->status
            !== TransformationImplementationCapabilityGoLive::STATUS_LIVE
            || ! $goLive->went_live_at
        ) {
            throw ValidationException::withMessages([
                'go_live' =>
                    'La suscripción LAUDAAPI solo puede '
                    .'iniciar después de un Go-Live LIVE.',
            ]);
        }

        if (! $subscriber->active) {
            throw ValidationException::withMessages([
                'subscriber' =>
                    'El Subscriber debe estar activo.',
            ]);
        }

        if (
            (int) $company->subscriber_id
            !== (int) $subscriber->id
        ) {
            throw ValidationException::withMessages([
                'company' =>
                    'La Company debe pertenecer al mismo '
                    .'Subscriber que recibirá la suscripción.',
            ]);
        }

        if (! in_array(
            $billingCycle,
            ['monthly', 'yearly'],
            true
        )) {
            throw ValidationException::withMessages([
                'billing_cycle' =>
                    'El ciclo debe ser monthly o yearly.',
            ]);
        }

        $existingActivation =
            TransformationImplementationSubscriptionActivation::query()
                ->where(
                    'transformation_implementation_capability_go_live_id',
                    $goLive->id
                )
                ->first();

        if ($existingActivation) {
            $this->assertExistingActivationIdentity(
                $existingActivation,
                $subscriber,
                $company
            );

            return $existingActivation;
        }

        $currency = strtoupper(
            trim(
                $currency
                ?: (string) (
                    $company->currency
                    ?? ''
                )
                ?: (string) (
                    $subscriber->currency
                    ?? 'DOP'
                )
            )
        );

        if (! preg_match(
            '/^[A-Z]{3}$/',
            $currency
        )) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda debe utilizar un código '
                    .'ISO de tres letras.',
            ]);
        }

        return DB::transaction(function () use (
            $goLive,
            $subscriber,
            $company,
            $userId,
            $billingCycle,
            $currency
        ) {
            /*
             * T360 conserva Subscriber como mutex
             * del ledger R2-I.
             *
             * El owner central usa el mismo orden
             * Subscriber → Subscription.
             */
            $subscriber = Subscriber::query()
                ->whereKey(
                    $subscriber->id
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (! $subscriber->active) {
                throw ValidationException::withMessages([
                    'subscriber' =>
                        'El Subscriber debe permanecer '
                        .'activo durante la activación '
                        .'post-Go-Live.',
                ]);
            }

            $lockedActivation =
                TransformationImplementationSubscriptionActivation::query()
                    ->where(
                        'transformation_implementation_capability_go_live_id',
                        $goLive->id
                    )
                    ->first();

            if ($lockedActivation) {
                $this->assertExistingActivationIdentity(
                    $lockedActivation,
                    $subscriber,
                    $company
                );

                return $lockedActivation;
            }

            $sourceSnapshot =
                $this->sourceSnapshot(
                    $goLive
                );

            $central = app(
                CentralEntitlementActivationService::class
            )->ensureSubscription(
                $subscriber,
                $company,
                CentralEntitlementActivationService::SOURCE_TRANSFORMATION_360,
                $userId,
                $billingCycle,
                $currency,
                $goLive->went_live_at,
                array_merge(
                    $sourceSnapshot,
                    [
                        'started_from_go_live_id' =>
                            (int) $goLive->id,
                        'subscription_items_pending_r2j' =>
                            true,
                    ]
                )
            );

            $subscription =
                $central['subscription'];

            $activationType =
                $central['subscription_created']
                    ? TransformationImplementationSubscriptionActivation::TYPE_CREATED
                    : TransformationImplementationSubscriptionActivation::TYPE_REUSED;

            if (
                $activationType
                === TransformationImplementationSubscriptionActivation::TYPE_CREATED
                && $subscription->starts_at?->lt(
                    $goLive->went_live_at
                )
            ) {
                throw ValidationException::withMessages([
                    'subscription' =>
                        'Una suscripción creada por '
                        .'Transformación 360 nunca puede '
                        .'iniciar antes del Go-Live.',
                ]);
            }

            return TransformationImplementationSubscriptionActivation::query()
                ->create([
                    'transformation_implementation_capability_go_live_id' =>
                        $goLive->id,
                    'subscriber_id' =>
                        $subscriber->id,
                    'company_id' =>
                        $company->id,
                    'subscription_id' =>
                        $subscription->id,
                    'activation_type' =>
                        $activationType,
                    'status' =>
                        TransformationImplementationSubscriptionActivation::STATUS_ACTIVE,
                    'go_live_at' =>
                        $goLive->went_live_at,
                    'subscription_started_at' =>
                        $subscription->starts_at
                        ?? $goLive->went_live_at,
                    'source_snapshot' =>
                        $sourceSnapshot,
                    'created_by_user_id' =>
                        $userId,
                ]);
        }, 3);
    }

    private function assertExistingActivationIdentity(
        TransformationImplementationSubscriptionActivation $activation,
        Subscriber $subscriber,
        Company $company
    ): void {
        if (
            (int) $activation->subscriber_id
            !== (int) $subscriber->id
            || (int) $activation->company_id
                !== (int) $company->id
        ) {
            throw ValidationException::withMessages([
                'activation' =>
                    'El Go-Live ya fue vinculado a otro '
                    .'Subscriber/Company.',
            ]);
        }
    }

    private function sourceSnapshot(
        TransformationImplementationCapabilityGoLive $goLive
    ): array {
        return [
            'plan_id' =>
                $goLive->capability?->phase?->plan?->id,
            'phase_id' =>
                $goLive->capability?->phase?->id,
            'capability_id' =>
                $goLive->capability?->id,
            'capability_key' =>
                $goLive->capability?->capability_key,
            'capability_label' =>
                $goLive->capability?->capability_label,
            'go_live_attempt' =>
                $goLive->attempt,
            'went_live_at' =>
                $goLive->went_live_at
                    ?->toIso8601String(),
        ];
    }
}
