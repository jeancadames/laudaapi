<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\TransformationImplementationSubscriptionActivation;
use Carbon\CarbonInterface;
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
        $goLive->loadMissing('capability.phase.plan');

        if (
            $goLive->status !== TransformationImplementationCapabilityGoLive::STATUS_LIVE
            || !$goLive->went_live_at
        ) {
            throw ValidationException::withMessages([
                'go_live' =>
                    'La suscripción LAUDAAPI solo puede iniciar después de un Go-Live LIVE.',
            ]);
        }

        if (!$subscriber->active) {
            throw ValidationException::withMessages([
                'subscriber' => 'El Subscriber debe estar activo.',
            ]);
        }

        if ((int) $company->subscriber_id !== (int) $subscriber->id) {
            throw ValidationException::withMessages([
                'company' =>
                    'La Company debe pertenecer al mismo Subscriber que recibirá la suscripción.',
            ]);
        }

        if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
            throw ValidationException::withMessages([
                'billing_cycle' => 'El ciclo debe ser monthly o yearly.',
            ]);
        }

        $existingActivation = TransformationImplementationSubscriptionActivation::query()
            ->where(
                'transformation_implementation_capability_go_live_id',
                $goLive->id
            )
            ->first();

        if ($existingActivation) {
            if (
                (int) $existingActivation->subscriber_id !== (int) $subscriber->id
                || (int) $existingActivation->company_id !== (int) $company->id
            ) {
                throw ValidationException::withMessages([
                    'activation' =>
                        'El Go-Live ya fue vinculado a otro Subscriber/Company.',
                ]);
            }

            return $existingActivation;
        }

        $currency = strtoupper(trim(
            $currency
            ?: (string) ($company->currency ?? '')
            ?: (string) ($subscriber->currency ?? 'DOP')
        ));

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages([
                'currency' => 'La moneda debe utilizar un código ISO de tres letras.',
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
             * PASO 9E-A:
             * Subscriber es el mutex de la Subscription general.
             */
            $subscriber = Subscriber::query()
                ->whereKey($subscriber->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $subscriber->active) {
                throw ValidationException::withMessages([
                    'subscriber' =>
                        'El Subscriber debe permanecer activo durante la activación post-Go-Live.',
                ]);
            }

            /*
             * El fast-path previo a la transacción es solo optimización.
             * La decisión idempotente definitiva ocurre después del lock.
             */
            $lockedActivation =
                TransformationImplementationSubscriptionActivation::query()
                    ->where(
                        'transformation_implementation_capability_go_live_id',
                        $goLive->id
                    )
                    ->first();

            if ($lockedActivation) {
                if (
                    (int) $lockedActivation->subscriber_id !== (int) $subscriber->id
                    || (int) $lockedActivation->company_id !== (int) $company->id
                ) {
                    throw ValidationException::withMessages([
                        'activation' =>
                            'El Go-Live ya fue vinculado a otro Subscriber/Company.',
                    ]);
                }

                return $lockedActivation;
            }

            $subscription = Subscription::query()
                ->where('subscriber_id', $subscriber->id)
                ->where('status', 'active')
                ->where(function ($query) use ($goLive) {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $goLive->went_live_at);
                })
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $activationType = TransformationImplementationSubscriptionActivation::TYPE_REUSED;

            if (!$subscription) {
                $periodStart = $goLive->went_live_at->copy();
                $periodEnd = $this->periodEnd($periodStart, $billingCycle);

                $subscription = Subscription::query()->create([
                    'subscriber_id' => $subscriber->id,
                    'created_by_user_id' => $userId,
                    'status' => 'active',
                    'billing_cycle' => $billingCycle,
                    'currency' => $currency,
                    'subtotal_amount' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'trial_ends_at' => null,
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'starts_at' => $goLive->went_live_at,
                    'ends_at' => null,
                    'cancelled_at' => null,
                    'provider' => null,
                    'provider_subscription_id' => null,
                    'meta' => [
                        'source' => 'transformation_360',
                        'started_from_go_live_id' => $goLive->id,
                        'subscription_items_pending_r2j' => true,
                    ],
                ]);

                $activationType = TransformationImplementationSubscriptionActivation::TYPE_CREATED;
            }

            if (
                $activationType === TransformationImplementationSubscriptionActivation::TYPE_CREATED
                && $subscription->starts_at?->lt($goLive->went_live_at)
            ) {
                throw ValidationException::withMessages([
                    'subscription' =>
                        'Una suscripción creada por Transformación 360 nunca puede iniciar antes del Go-Live.',
                ]);
            }

            return TransformationImplementationSubscriptionActivation::query()->create([
                'transformation_implementation_capability_go_live_id' => $goLive->id,
                'subscriber_id' => $subscriber->id,
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'activation_type' => $activationType,
                'status' => TransformationImplementationSubscriptionActivation::STATUS_ACTIVE,
                'go_live_at' => $goLive->went_live_at,
                'subscription_started_at' => $subscription->starts_at ?? $goLive->went_live_at,
                'source_snapshot' => [
                    'plan_id' => $goLive->capability?->phase?->plan?->id,
                    'phase_id' => $goLive->capability?->phase?->id,
                    'capability_id' => $goLive->capability?->id,
                    'capability_key' => $goLive->capability?->capability_key,
                    'capability_label' => $goLive->capability?->capability_label,
                    'go_live_attempt' => $goLive->attempt,
                    'went_live_at' => $goLive->went_live_at?->toIso8601String(),
                ],
                'created_by_user_id' => $userId,
            ]);
        }, 3);
    }

    private function periodEnd(
        CarbonInterface $periodStart,
        string $billingCycle
    ): CarbonInterface {
        return $billingCycle === 'yearly'
            ? $periodStart->copy()->addYear()
            : $periodStart->copy()->addMonth();
    }
}
