<?php

namespace App\Services\Entitlements;

use App\Models\Company;
use App\Models\Service;
use App\Models\ServicePlan;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\Billing\ServicePricingEngine;
use App\Services\Billing\SubscriptionTotalsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CentralEntitlementActivationService
{
    public const SOURCE_STANDALONE_SETTLEMENT =
        'standalone_settlement';

    public const SOURCE_TRANSFORMATION_360 =
        'transformation_360';

    /**
     * Paso general:
     * asegura la Subscription del Subscriber.
     *
     * NO crea SubscriptionItem.
     * NO concede entitlement por sí solo.
     *
     * @return array{
     *   subscription: Subscription,
     *   subscription_created: bool
     * }
     */
    public function ensureSubscription(
        Subscriber $subscriber,
        Company $company,
        string $source,
        ?int $userId = null,
        string $billingCycle = 'monthly',
        ?string $currency = null,
        ?CarbonInterface $effectiveAt = null,
        array $sourceMeta = []
    ): array {
        $source = trim($source);
        $billingCycle = strtolower(
            trim($billingCycle)
        );

        $this->assertSource($source);
        $this->assertIdentity(
            $subscriber,
            $company
        );
        $this->assertBillingCycle(
            $billingCycle
        );

        $effectiveAt ??= now();

        $currency = $this->resolveSubscriptionCurrency(
            $subscriber,
            $company,
            $currency
        );

        return DB::transaction(function () use (
            $subscriber,
            $source,
            $userId,
            $billingCycle,
            $currency,
            $effectiveAt,
            $sourceMeta
        ): array {
            /*
             * Subscriber es el mutex de la Subscription general.
             */
            $subscriber = Subscriber::query()
                ->whereKey($subscriber->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $subscriber->active) {
                throw ValidationException::withMessages([
                    'subscriber' =>
                        'El Subscriber debe permanecer activo.',
                ]);
            }

            $subscription = Subscription::query()
                ->where(
                    'subscriber_id',
                    $subscriber->id
                )
                ->where('status', 'active')
                ->where(function ($query) use (
                    $effectiveAt
                ) {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere(
                            'ends_at',
                            '>=',
                            $effectiveAt
                        );
                })
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $created = false;

            if (! $subscription) {
                $meta = array_merge(
                    $sourceMeta,
                    [
                        'source' =>
                            'central_entitlement_activation',
                        'started_from_source' =>
                            $source,
                    ]
                );

                $subscription =
                    Subscription::query()->create([
                        'subscriber_id' =>
                            $subscriber->id,
                        'created_by_user_id' =>
                            $userId,
                        'status' => 'active',
                        'billing_cycle' =>
                            $billingCycle,
                        'currency' => $currency,
                        'subtotal_amount' => 0,
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'total_amount' => 0,
                        'trial_ends_at' => null,
                        'current_period_start' =>
                            $effectiveAt,
                        'current_period_end' =>
                            $this->periodEnd(
                                $effectiveAt,
                                $billingCycle
                            ),
                        'starts_at' =>
                            $effectiveAt,
                        'ends_at' => null,
                        'cancelled_at' => null,
                        'provider' => null,
                        'provider_subscription_id' =>
                            null,
                        'meta' => $meta,
                    ]);

                $created = true;
            } else {
                $this->assertSubscriptionContract(
                    $subscription,
                    $subscriber,
                    $billingCycle,
                    $currency
                );
            }

            return [
                'subscription' =>
                    $subscription->fresh(),
                'subscription_created' =>
                    $created,
            ];
        }, 3);
    }

    /**
     * Paso económico por Service:
     * activa/reutiliza un SubscriptionItem.
     *
     * Requiere Subscription general active existente.
     *
     * @return array{
     *   subscription: Subscription,
     *   item: SubscriptionItem,
     *   item_activation: string,
     *   pricing: array
     * }
     */
    /**
     * Reutiliza una Subscription general sin imponer su ciclo legacy
     * a los contratos individuales de cada solución.
     */
    public function ensureSubscriptionForItem(
        Subscriber $subscriber,
        Company $company,
        string $source,
        ?int $userId = null,
        string $itemBillingCycle = 'monthly',
        ?string $currency = null,
        ?CarbonInterface $effectiveAt = null,
        array $sourceMeta = []
    ): array {
        $source = trim($source);
        $itemBillingCycle = strtolower(trim($itemBillingCycle));

        $this->assertSource($source);
        $this->assertIdentity($subscriber, $company);
        $this->assertBillingCycle($itemBillingCycle);

        $effectiveAt ??= now();

        $currency = $this->resolveSubscriptionCurrency(
            $subscriber,
            $company,
            $currency
        );

        return DB::transaction(function () use (
            $subscriber,
            $source,
            $userId,
            $itemBillingCycle,
            $currency,
            $effectiveAt,
            $sourceMeta
        ): array {
            $subscriber = Subscriber::query()
                ->whereKey($subscriber->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $subscriber->active) {
                throw ValidationException::withMessages([
                    'subscriber' =>
                        'El Subscriber debe permanecer activo.',
                ]);
            }

            $subscription = Subscription::query()
                ->where('subscriber_id', $subscriber->id)
                ->where('status', 'active')
                ->where(function ($query) use ($effectiveAt) {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $effectiveAt);
                })
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $created = false;

            if (! $subscription) {
                $meta = array_merge($sourceMeta, [
                    'source' =>
                        'central_entitlement_activation',
                    'started_from_source' => $source,
                    'billing_cycle_semantics' =>
                        'legacy_default_item_cycles_independent',
                ]);

                $subscription = Subscription::query()->create([
                    'subscriber_id' => $subscriber->id,
                    'created_by_user_id' => $userId,
                    'status' => 'active',
                    'billing_cycle' => $itemBillingCycle,
                    'currency' => $currency,
                    'subtotal_amount' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'trial_ends_at' => null,
                    'current_period_start' => $effectiveAt,
                    'current_period_end' =>
                        $this->periodEnd(
                            $effectiveAt,
                            $itemBillingCycle
                        ),
                    'starts_at' => $effectiveAt,
                    'ends_at' => null,
                    'cancelled_at' => null,
                    'provider' => null,
                    'provider_subscription_id' => null,
                    'meta' => $meta,
                ]);

                $created = true;
            } else {
                $this->assertSubscriptionItemContract(
                    $subscription,
                    $subscriber,
                    $currency
                );
            }

            return [
                'subscription' => $subscription->fresh(),
                'subscription_created' => $created,
            ];
        }, 3);
    }

    public function activateCommercialItem(
        Subscriber $subscriber,
        Company $company,
        Service $service,
        Subscription $subscription,
        string $source,
        ?int $userId = null,
        ?CarbonInterface $effectiveAt = null,
        array $sourceMeta = []
    ): array {
        $source = trim($source);

        $this->assertSource($source);
        $this->assertIdentity(
            $subscriber,
            $company
        );
        $this->assertCommercialService(
            $service
        );

        $effectiveAt ??= now();

        $servicePlan = $this->resolveServicePlan(
            $service,
            $sourceMeta['service_plan_id'] ?? null
        );

        $billingCycle = strtolower(
            trim(
                (string) (
                    $servicePlan
                        ? (
                            $sourceMeta['billing_cycle']
                            ?? $subscription->billing_cycle
                        )
                        : $subscription->billing_cycle
                )
            )
        );

        $this->assertBillingCycle(
            $billingCycle
        );

        $currency = $servicePlan
            ? $this->resolvePlanCurrency(
                $subscriber,
                $company,
                $servicePlan
            )
            : $this->resolveServiceCurrency(
                $subscriber,
                $company,
                $service
            );

        if ($servicePlan) {
            $this->assertSubscriptionItemContract(
                $subscription,
                $subscriber,
                $currency
            );
        } else {
            if ($servicePlan) {
                $this->assertSubscriptionItemContract(
                    $subscription,
                    $subscriber,
                    $currency
                );
            } else {
                $this->assertSubscriptionContract(
                    $subscription,
                    $subscriber,
                    $billingCycle,
                    $currency
                );
            }
        }

        /*
         * Pricing se valida antes de cualquier mutación del item.
         */
        $pricing = $servicePlan
            ? app(ServicePricingEngine::class)->quotePlan(
                $service,
                $servicePlan,
                new Subscription([
                    'subscriber_id' => $subscriber->id,
                    'billing_cycle' => $billingCycle,
                    'currency' => $currency,
                ]),
                $billingCycle
            )
            : app(ServicePricingEngine::class)->quote(
                $service,
                $subscription
            );

        return DB::transaction(function () use (
            $subscriber,
            $company,
            $service,
            $subscription,
            $source,
            $userId,
            $effectiveAt,
            $sourceMeta,
            $billingCycle,
            $currency,
            $servicePlan
        ): array {
            /*
             * Orden global de locks:
             * Subscriber → Subscription → SubscriptionItem.
             */
            $subscriber = Subscriber::query()
                ->whereKey($subscriber->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $subscriber->active) {
                throw ValidationException::withMessages([
                    'subscriber' =>
                        'El Subscriber debe permanecer activo.',
                ]);
            }

            $subscription =
                Subscription::query()
                    ->whereKey($subscription->id)
                    ->where(
                        'subscriber_id',
                        $subscriber->id
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
                        'La Subscription debe permanecer active.',
                ]);
            }

            $this->assertSubscriptionContract(
                $subscription,
                $subscriber,
                $billingCycle,
                $currency
            );

            /*
             * Pricing se recalcula después del lock.
             */
            $pricing = $servicePlan
                ? app(ServicePricingEngine::class)->quotePlan(
                    $service,
                    $servicePlan,
                    new Subscription([
                        'subscriber_id' => $subscriber->id,
                        'billing_cycle' => $billingCycle,
                        'currency' => $currency,
                    ]),
                    $billingCycle
                )
                : app(ServicePricingEngine::class)->quote(
                    $service,
                    $subscription
                );

            $item = SubscriptionItem::query()
                ->where(
                    'subscription_id',
                    $subscription->id
                )
                ->where(
                    'service_id',
                    $service->id
                )
                ->lockForUpdate()
                ->first();

            $itemMeta = array_merge(
                $sourceMeta,
                [
                    'source' => $source,
                    'activated_at' =>
                        now()->toISOString(),
                    'effective_at' =>
                        $effectiveAt->toIso8601String(),
                    'pricing' => [
                        'service_plan_id' => $servicePlan?->id,
                        'service_plan_code' => $servicePlan?->code,
                        'source_solution' => $servicePlan?->source_solution,
                        'source_plan_key' => $servicePlan?->source_plan_key,
                        'billing_cycle' => $billingCycle,
                        'pricing_version' =>
                            $pricing[
                                'pricing_version'
                            ] ?? null,
                        'quantity_source' =>
                            $pricing[
                                'quantity_source'
                            ] ?? null,
                        'tier_id' =>
                            $pricing[
                                'tier_id'
                            ] ?? null,
                        'tier_min_quantity' =>
                            $pricing[
                                'tier_min_quantity'
                            ] ?? null,
                        'tier_max_quantity' =>
                            $pricing[
                                'tier_max_quantity'
                            ] ?? null,
                    ],
                ]
            );

            /*
             * Un mismo SubscriptionItem puede estar sostenido por más
             * de una fuente (standalone, T360, etc.). El status del item
             * representa acceso agregado; entitlement_claims conserva
             * qué fuentes siguen sosteniendo ese acceso.
             */
            $existingMeta = $item?->meta;

            if (is_string($existingMeta)) {
                $existingMeta = json_decode(
                    $existingMeta,
                    true
                );
            }

            if (! is_array($existingMeta)) {
                $existingMeta = [];
            }

            $claims = $existingMeta[
                'entitlement_claims'
            ] ?? [];

            if (! is_array($claims)) {
                $claims = [];
            }

            /*
             * Compatibilidad con items creados antes de S10-E:
             * el meta legacy guardaba una sola source.
             */
            if (
                $claims === []
                && isset($existingMeta['source'])
                && in_array(
                    (string) $existingMeta['source'],
                    [
                        self::SOURCE_STANDALONE_SETTLEMENT,
                        self::SOURCE_TRANSFORMATION_360,
                    ],
                    true
                )
            ) {
                $legacySource =
                    (string) $existingMeta['source'];

                $legacyClaimKey =
                    $this->entitlementClaimKey(
                        $legacySource,
                        $existingMeta
                    );

                $claims[$legacyClaimKey] = [
                    'source' => $legacySource,
                    'claim_key' => $legacyClaimKey,
                    'source_meta' => $existingMeta,
                    'claimed_at' =>
                        $existingMeta['activated_at']
                        ?? now()->toISOString(),
                    'user_id' => null,
                    'legacy_backfill' => true,
                ];
            }

            $claimKey = $this->entitlementClaimKey(
                $source,
                $sourceMeta
            );

            $claims[$claimKey] = [
                'source' => $source,
                'claim_key' => $claimKey,
                'source_meta' => $sourceMeta,
                'claimed_at' => now()->toISOString(),
                'user_id' => $userId,
                'legacy_backfill' => false,
            ];

            $itemMeta = array_merge(
                $existingMeta,
                $itemMeta,
                [
                    'entitlement_claims' => $claims,
                    'last_entitlement_claim' =>
                        $claims[$claimKey],
                ]
            );

            $payload = [
                'status' => 'active',
                'service_plan_id' => $servicePlan?->id,
                'billing_cycle' => $billingCycle,
                'billing_model' =>
                    $pricing['billing_model'],
                'quantity' =>
                    $pricing['quantity'],
                'unit_price' =>
                    $pricing['unit_price'],
                'amount' =>
                    $pricing['amount'],
                'currency' =>
                    $pricing['currency'],
                'block_size' =>
                    $pricing['block_size'],
                'unit_name' =>
                    $pricing['unit_name'],
                'included_units' =>
                    $pricing['included_units'],
                'overage_unit_price' =>
                    $pricing[
                        'overage_unit_price'
                    ],
                'meta' => $itemMeta,
            ];

            $activation = 'reused';

            if ($item) {
                $alreadyEntitled = in_array(
                    strtolower(
                        (string) $item->status
                    ),
                    ServiceEntitlementPolicy::ITEM_STATUSES,
                    true
                );

                if (! $alreadyEntitled) {
                    $item->forceFill(
                        $payload
                    )->save();

                    $item = $item->fresh();
                    $activation =
                        'reactivated';
                } else {
                    /*
                     * Reused también debe registrar el claim nuevo.
                     */
                    $item->forceFill([
                        'meta' => $itemMeta,
                    ])->save();

                    $item = $item->fresh();
                }
            } else {
                $item = SubscriptionItem::query()
                    ->create(
                        array_merge(
                            [
                                'subscription_id' =>
                                    $subscription->id,
                                'service_id' =>
                                    $service->id,
                            ],
                            $payload
                        )
                    );

                $activation = 'created';
            }

            app(
                SubscriptionTotalsService::class
            )->recalculate(
                $subscription
            );

            return [
                'subscription' =>
                    $subscription->fresh(),
                'item' => $item->fresh(),
                'item_activation' =>
                    $activation,
                'pricing' => $pricing,
            ];
        }, 3);
    }

    /**
     * Conveniencia atómica para standalone:
     * crea/reutiliza Subscription + activa Service.
     *
     * El adapter standalone debe validar antes
     * Payment/Invoice/settlement.
     *
     * @return array{
     *   subscription: Subscription,
     *   item: SubscriptionItem,
     *   subscription_created: bool,
     *   item_activation: string,
     *   pricing: array
     * }
     */
    public function activateCommercial(
        Subscriber $subscriber,
        Company $company,
        Service $service,
        string $source,
        ?int $userId = null,
        string $billingCycle = 'monthly',
        ?CarbonInterface $effectiveAt = null,
        array $sourceMeta = []
    ): array {
        $this->assertSource($source);
        $this->assertIdentity(
            $subscriber,
            $company
        );
        $this->assertCommercialService(
            $service
        );
        $this->assertBillingCycle(
            strtolower(
                trim($billingCycle)
            )
        );

        $effectiveAt ??= now();

        /*
         * Validar pricing ANTES de crear Subscription.
         * Evita Subscription vacía si el Service no está listo.
         */
        $servicePlan = $this->resolveServicePlan(
            $service,
            $sourceMeta['service_plan_id'] ?? null
        );

        $billingCycle = strtolower(trim($billingCycle));

        $sourceMeta['billing_cycle'] = $billingCycle;

        if ($servicePlan) {
            $sourceMeta['service_plan_id'] = (int) $servicePlan->id;
            $sourceMeta['service_plan_code'] = (string) $servicePlan->code;
            $sourceMeta['source_solution'] =
                (string) $servicePlan->source_solution;
            $sourceMeta['source_plan_key'] =
                (string) $servicePlan->source_plan_key;
        }

        $currency = $servicePlan
            ? $this->resolvePlanCurrency(
                $subscriber,
                $company,
                $servicePlan
            )
            : $this->resolveServiceCurrency(
                $subscriber,
                $company,
                $service
            );

        $probe = new Subscription([
            'subscriber_id' =>
                $subscriber->id,
            'billing_cycle' =>
                strtolower(
                    trim($billingCycle)
                ),
            'currency' => $currency,
        ]);

        if ($servicePlan) {
            app(ServicePricingEngine::class)->quotePlan(
                $service,
                $servicePlan,
                $probe,
                $billingCycle
            );
        } else {
            app(ServicePricingEngine::class)->quote(
                $service,
                $probe
            );
        }

        return DB::transaction(function () use (
            $subscriber,
            $company,
            $service,
            $source,
            $userId,
            $billingCycle,
            $effectiveAt,
            $sourceMeta,
            $servicePlan
        ): array {
            $subscriptionResult =
                $servicePlan
                    ? $this->ensureSubscriptionForItem(
                        $subscriber,
                        $company,
                        $source,
                        $userId,
                        $billingCycle,
                        null,
                        $effectiveAt,
                        $sourceMeta
                    )
                    : $this->ensureSubscription(
                        $subscriber,
                        $company,
                        $source,
                        $userId,
                        $billingCycle,
                        null,
                        $effectiveAt,
                        $sourceMeta
                    );

            $itemResult =
                $this->activateCommercialItem(
                    $subscriber,
                    $company,
                    $service,
                    $subscriptionResult[
                        'subscription'
                    ],
                    $source,
                    $userId,
                    $effectiveAt,
                    $sourceMeta
                );

            return [
                'subscription' =>
                    $itemResult[
                        'subscription'
                    ],
                'item' =>
                    $itemResult['item'],
                'subscription_created' =>
                    $subscriptionResult[
                        'subscription_created'
                    ],
                'item_activation' =>
                    $itemResult[
                        'item_activation'
                    ],
                'pricing' =>
                    $itemResult['pricing'],
            ];
        }, 3);
    }

    /**
     * Libera el claim de una fuente sobre un SubscriptionItem.
     *
     * Si quedan otros claims, el entitlement agregado permanece activo.
     * Si no queda ninguno, el item pasa a cancelled.
     * La Subscription general nunca se cancela aquí.
     *
     * @return array{
     *   subscription: Subscription,
     *   item: SubscriptionItem,
     *   item_revocation: string,
     *   released_claim_key: string,
     *   remaining_claims: int
     * }
     */
    public function revokeCommercialItem(
        Subscription $subscription,
        SubscriptionItem $item,
        string $source,
        ?int $userId = null,
        array $sourceMeta = []
    ): array {
        $source = trim($source);

        $this->assertSource($source);

        return DB::transaction(function () use (
            $subscription,
            $item,
            $source,
            $userId,
            $sourceMeta
        ): array {
            /*
             * Orden global de mutación económica:
             * Subscription → SubscriptionItem.
             */
            $subscription = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            $item = SubscriptionItem::query()
                ->whereKey($item->id)
                ->where(
                    'subscription_id',
                    $subscription->id
                )
                ->lockForUpdate()
                ->firstOrFail();

            $status = strtolower(
                trim((string) $item->status)
            );

            if (
                $status !== 'cancelled'
                && ! in_array(
                    $status,
                    ServiceEntitlementPolicy::ITEM_STATUSES,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'subscription_item' => [
                        'Solo un entitlement económico real '
                        .'puede revocarse.',
                    ],
                ]);
            }

            $meta = $item->meta;

            if (is_string($meta)) {
                $meta = json_decode($meta, true);
            }

            if (! is_array($meta)) {
                $meta = [];
            }

            $claims = $meta['entitlement_claims']
                ?? [];

            if (! is_array($claims)) {
                $claims = [];
            }

            /*
             * Backfill para un item legacy que todavía no tenga claims.
             */
            if (
                $claims === []
                && isset($meta['source'])
                && in_array(
                    (string) $meta['source'],
                    [
                        self::SOURCE_STANDALONE_SETTLEMENT,
                        self::SOURCE_TRANSFORMATION_360,
                    ],
                    true
                )
            ) {
                $legacySource =
                    (string) $meta['source'];

                $legacyClaimKey =
                    $this->entitlementClaimKey(
                        $legacySource,
                        $meta
                    );

                $claims[$legacyClaimKey] = [
                    'source' => $legacySource,
                    'claim_key' => $legacyClaimKey,
                    'source_meta' => $meta,
                    'claimed_at' =>
                        $meta['activated_at']
                        ?? now()->toISOString(),
                    'user_id' => null,
                    'legacy_backfill' => true,
                ];
            }

            $claimKey = $this->entitlementClaimKey(
                $source,
                $sourceMeta
            );

            $released = array_key_exists(
                $claimKey,
                $claims
            );

            unset($claims[$claimKey]);

            $history = $meta['entitlement_revocations']
                ?? [];

            if (! is_array($history)) {
                $history = [];
            }

            $history[] = [
                'source' => $source,
                'claim_key' => $claimKey,
                'claim_released' => $released,
                'source_meta' => $sourceMeta,
                'remaining_claims' => count($claims),
                'revoked_at' => now()->toISOString(),
                'user_id' => $userId,
            ];

            $meta['entitlement_claims'] = $claims;
            $meta['entitlement_revocations'] = $history;
            $meta['last_entitlement_revocation'] =
                end($history);

            $revocation = 'reused';

            if ($claims !== []) {
                /*
                 * Otra fuente todavía sostiene el mismo Service.
                 */
                $item->forceFill([
                    'meta' => $meta,
                ])->save();

                $revocation =
                    'preserved_by_other_claim';
            } elseif ($status !== 'cancelled') {
                $item->forceFill([
                    'status' => 'cancelled',
                    'meta' => $meta,
                ])->save();

                $revocation = 'revoked';
            } else {
                $item->forceFill([
                    'meta' => $meta,
                ])->save();
            }

            $item = $item->fresh();

            app(
                SubscriptionTotalsService::class
            )->recalculate(
                $subscription
            );

            return [
                'subscription' =>
                    $subscription->fresh(),
                'item' =>
                    $item,
                'item_revocation' =>
                    $revocation,
                'released_claim_key' =>
                    $claimKey,
                'remaining_claims' =>
                    count($claims),
            ];
        }, 3);
    }

    private function entitlementClaimKey(
        string $source,
        array $sourceMeta
    ): string {
        $explicit = trim(
            (string) (
                $sourceMeta['entitlement_claim_key']
                ?? ''
            )
        );

        if ($explicit !== '') {
            return $source.':'.$explicit;
        }

        if (
            $source === self::SOURCE_STANDALONE_SETTLEMENT
            && ! empty(
                $sourceMeta['standalone_settlement_id']
            )
        ) {
            return $source.':'
                .(int) $sourceMeta[
                    'standalone_settlement_id'
                ];
        }

        if (
            $source === self::SOURCE_TRANSFORMATION_360
            && ! empty(
                $sourceMeta[
                    'transformation_implementation_capability_go_live_id'
                ]
            )
        ) {
            return $source.':'
                .(int) $sourceMeta[
                    'transformation_implementation_capability_go_live_id'
                ];
        }

        /*
         * Fallback compatible para fuentes sin identificador granular.
         */
        return $source;
    }

    private function assertSource(
        string $source
    ): void {
        if (! in_array(
            $source,
            [
                self::SOURCE_STANDALONE_SETTLEMENT,
                self::SOURCE_TRANSFORMATION_360,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'source' =>
                    'La fuente de activación comercial no es válida.',
            ]);
        }
    }

    private function assertIdentity(
        Subscriber $subscriber,
        Company $company
    ): void {
        if (! $subscriber->active) {
            throw ValidationException::withMessages([
                'subscriber' =>
                    'El Subscriber debe estar activo.',
            ]);
        }

        if (! $company->active) {
            throw ValidationException::withMessages([
                'company' =>
                    'La Company debe estar activa.',
            ]);
        }

        if (
            (int) $company->subscriber_id
            !== (int) $subscriber->id
        ) {
            throw ValidationException::withMessages([
                'company' =>
                    'La Company debe pertenecer al mismo Subscriber.',
            ]);
        }
    }

    private function assertCommercialService(
        Service $service
    ): void {
        if (! $service->active) {
            throw ValidationException::withMessages([
                'service' =>
                    'El Service debe estar activo.',
            ]);
        }

        if (! $service->billable) {
            throw ValidationException::withMessages([
                'service' =>
                    'El Service todavía no está habilitado '
                    .'para activación comercial recurrente.',
            ]);
        }
    }

    private function assertBillingCycle(
        string $billingCycle
    ): void {
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
    }

    private function resolveSubscriptionCurrency(
        Subscriber $subscriber,
        Company $company,
        ?string $currency
    ): string {
        $resolved = strtoupper(
            trim(
                (string) (
                    $currency
                    ?: $company->currency
                    ?: $subscriber->currency
                    ?: 'DOP'
                )
            )
        );

        if (! preg_match(
            '/^[A-Z]{3}$/',
            $resolved
        )) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda debe usar un código ISO '
                    .'de tres letras.',
            ]);
        }

        return $resolved;
    }

    private function resolveServiceCurrency(
        Subscriber $subscriber,
        Company $company,
        Service $service
    ): string {
        $subscriptionCurrency =
            $this->resolveSubscriptionCurrency(
                $subscriber,
                $company,
                null
            );

        $serviceCurrency = strtoupper(
            trim(
                (string) (
                    $service->currency
                    ?: $subscriptionCurrency
                )
            )
        );

        if (! preg_match(
            '/^[A-Z]{3}$/',
            $serviceCurrency
        )) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda del Service debe usar un '
                    .'código ISO de tres letras.',
            ]);
        }

        if (
            $subscriptionCurrency
            !== $serviceCurrency
        ) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda de Company/Subscriber debe '
                    .'coincidir con la moneda del Service. '
                    .'No se permite FX implícito.',
            ]);
        }

        return $subscriptionCurrency;
    }

    private function resolveServicePlan(
        Service $service,
        mixed $servicePlanId
    ): ?ServicePlan {
        if ($servicePlanId === null || $servicePlanId === '') {
            return null;
        }

        if (! is_numeric($servicePlanId)) {
            throw ValidationException::withMessages([
                'service_plan' => 'El service_plan_id no es válido.',
            ]);
        }

        $plan = ServicePlan::query()
            ->whereKey((int) $servicePlanId)
            ->where('service_id', $service->id)
            ->where('active', true)
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'service_plan' =>
                    'El plan no está activo o no pertenece al Service.',
            ]);
        }

        return $plan;
    }

    private function resolvePlanCurrency(
        Subscriber $subscriber,
        Company $company,
        ServicePlan $servicePlan
    ): string {
        $base = $this->resolveSubscriptionCurrency(
            $subscriber,
            $company,
            null
        );

        $planCurrency = strtoupper(
            trim((string) ($servicePlan->currency ?: $base))
        );

        if (! preg_match('/^[A-Z]{3}$/', $planCurrency)) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda del ServicePlan debe usar '
                    .'un código ISO de tres letras.',
            ]);
        }

        if ($planCurrency !== $base) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda del ServicePlan debe coincidir '
                    .'con Company/Subscriber.',
            ]);
        }

        return $base;
    }

    private function assertSubscriptionItemContract(
        Subscription $subscription,
        Subscriber $subscriber,
        string $currency
    ): void {
        if (
            (int) $subscription->subscriber_id
            !== (int) $subscriber->id
        ) {
            throw ValidationException::withMessages([
                'subscription' =>
                    'La Subscription pertenece a otro Subscriber.',
            ]);
        }

        if (strtolower((string) $subscription->status) !== 'active') {
            throw ValidationException::withMessages([
                'subscription' =>
                    'La Subscription debe estar active.',
            ]);
        }

        /*
         * Deliberadamente NO valida Subscription.billing_cycle.
         * El ciclo contractual vive en SubscriptionItem.billing_cycle.
         */
        if (
            strtoupper((string) $subscription->currency)
            !== $currency
        ) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La Subscription activa existente usa otra moneda.',
            ]);
        }
    }

    private function assertSubscriptionContract(
        Subscription $subscription,
        Subscriber $subscriber,
        string $billingCycle,
        string $currency
    ): void {
        if (
            (int) $subscription->subscriber_id
            !== (int) $subscriber->id
        ) {
            throw ValidationException::withMessages([
                'subscription' =>
                    'La Subscription pertenece a otro Subscriber.',
            ]);
        }

        if (
            strtolower(
                (string) $subscription->status
            ) !== 'active'
        ) {
            throw ValidationException::withMessages([
                'subscription' =>
                    'La Subscription debe estar active.',
            ]);
        }

        if (
            strtolower(
                (string)
                $subscription->billing_cycle
            ) !== $billingCycle
        ) {
            throw ValidationException::withMessages([
                'billing_cycle' =>
                    'La Subscription activa existente '
                    .'usa otro ciclo de facturación.',
            ]);
        }

        if (
            strtoupper(
                (string) $subscription->currency
            ) !== $currency
        ) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La Subscription activa existente '
                    .'usa otra moneda.',
            ]);
        }
    }

    private function periodEnd(
        CarbonInterface $start,
        string $billingCycle
    ): CarbonInterface {
        return $billingCycle === 'yearly'
            ? $start->copy()->addYear()
            : $start->copy()->addMonth();
    }
}
