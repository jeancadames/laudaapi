<?php

namespace App\Services\Billing;

use App\Models\ServiceBundleDiscountRule;
use App\Models\Subscription;
use App\Services\Entitlements\ServiceEntitlementPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BundleDiscountEngine
{
    public const PRICING_VERSION = 'bundle-v1';

    public function quote(
        Subscription $subscription
    ): array {
        $currency = strtoupper(
            trim(
                (string) (
                    $subscription->currency
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
                    'La moneda de la Subscription debe ser ISO de tres letras.',
            ]);
        }

        $items = DB::table(
            'subscription_items'
        )
            ->where(
                'subscription_id',
                $subscription->id
            )
            ->whereIn(
                'status',
                ServiceEntitlementPolicy::ITEM_STATUSES
            )
            ->get([
                'service_id',
                'amount',
                'currency',
            ]);

        $amountByService = [];

        foreach ($items as $item) {
            $itemCurrency = strtoupper(
                trim(
                    (string) (
                        $item->currency
                        ?? ''
                    )
                )
            );

            if (
                $itemCurrency !== ''
                && $itemCurrency !== $currency
            ) {
                throw ValidationException::withMessages([
                    'currency' =>
                        'Todos los SubscriptionItems deben compartir moneda con la Subscription.',
                ]);
            }

            $serviceId =
                (int) $item->service_id;

            $amountByService[$serviceId] =
                round(
                    (float) $item->amount,
                    2
                );
        }

        if ($amountByService === []) {
            return $this->emptyQuote(
                $currency
            );
        }

        $rules = ServiceBundleDiscountRule::query()
            ->where('active', true)
            ->whereHas(
                'bundleService',
                fn ($query) =>
                    $query->where(
                        'active',
                        true
                    )
            )
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $candidates = [];

        foreach ($rules as $rule) {
            $bundleItems = DB::table(
                'service_bundle_items'
            )
                ->where(
                    'bundle_service_id',
                    $rule->bundle_service_id
                )
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get([
                    'included_service_id',
                    'required',
                ]);

            $requiredIds = $bundleItems
                ->where('required', true)
                ->pluck(
                    'included_service_id'
                )
                ->map(
                    fn ($id) => (int) $id
                )
                ->values()
                ->all();

            if (
                count($requiredIds)
                < 2
            ) {
                continue;
            }

            $matchesRequired = collect(
                $requiredIds
            )->every(
                fn (int $serviceId): bool =>
                    array_key_exists(
                        $serviceId,
                        $amountByService
                    )
            );

            if (! $matchesRequired) {
                continue;
            }

            $includedIds = $bundleItems
                ->pluck(
                    'included_service_id'
                )
                ->map(
                    fn ($id) => (int) $id
                )
                ->unique()
                ->values()
                ->all();

            $matchedIds = array_values(
                array_filter(
                    $includedIds,
                    fn (int $serviceId): bool =>
                        array_key_exists(
                            $serviceId,
                            $amountByService
                        )
                )
            );

            $bundleBase = round(
                array_sum(
                    array_map(
                        fn (int $serviceId): float =>
                            $amountByService[
                                $serviceId
                            ],
                        $matchedIds
                    )
                ),
                2
            );

            if ($bundleBase <= 0) {
                continue;
            }

            $discount = $this->discountAmount(
                $rule,
                $bundleBase,
                $currency
            );

            $snapshot = [
                'pricing_version' =>
                    self::PRICING_VERSION,
                'rule_id' =>
                    (int) $rule->id,
                'bundle_service_id' =>
                    (int) $rule->bundle_service_id,
                'code' => $rule->code,
                'name' => $rule->name,
                'discount_type' =>
                    $rule->discount_type,
                'discount_value' =>
                    (float) $rule->discount_value,
                'discount_amount' =>
                    $discount,
                'bundle_base_amount' =>
                    $bundleBase,
                'currency' => $currency,
                'priority' =>
                    (int) $rule->priority,
                'required_service_ids' =>
                    $requiredIds,
                'matched_service_ids' =>
                    $matchedIds,
            ];

            $candidates[] = [
                'rule' => $rule,
                'discount_amount' =>
                    $discount,
                'bundle_base_amount' =>
                    $bundleBase,
                'snapshot' => $snapshot,
            ];
        }

        if ($candidates === []) {
            return $this->emptyQuote(
                $currency
            );
        }

        usort(
            $candidates,
            function (
                array $a,
                array $b
            ): int {
                $priorityCompare =
                    (int) $b['rule']->priority
                    <=>
                    (int) $a['rule']->priority;

                if ($priorityCompare !== 0) {
                    return $priorityCompare;
                }

                $discountCompare =
                    (float) $b['discount_amount']
                    <=>
                    (float) $a['discount_amount'];

                if ($discountCompare !== 0) {
                    return $discountCompare;
                }

                return
                    (int) $a['rule']->id
                    <=>
                    (int) $b['rule']->id;
            }
        );

        $winner = $candidates[0];

        return [
            'pricing_version' =>
                self::PRICING_VERSION,
            'matched' => true,
            'rule_id' =>
                (int) $winner['rule']->id,
            'bundle_service_id' =>
                (int) $winner['rule']
                    ->bundle_service_id,
            'currency' => $currency,
            'bundle_base_amount' =>
                $winner['bundle_base_amount'],
            'discount_amount' =>
                $winner['discount_amount'],
            'snapshot' =>
                $winner['snapshot'],
        ];
    }

    private function discountAmount(
        ServiceBundleDiscountRule $rule,
        float $bundleBase,
        string $subscriptionCurrency
    ): float {
        $value = round(
            (float) $rule->discount_value,
            4
        );

        if ($value < 0) {
            throw ValidationException::withMessages([
                'discount_value' =>
                    'El descuento no puede ser negativo.',
            ]);
        }

        if (
            $rule->discount_type
            === ServiceBundleDiscountRule::TYPE_PERCENTAGE
        ) {
            if ($value > 100) {
                throw ValidationException::withMessages([
                    'discount_value' =>
                        'El porcentaje del bundle no puede superar 100.',
                ]);
            }

            return round(
                min(
                    $bundleBase,
                    $bundleBase
                    * ($value / 100)
                ),
                2
            );
        }

        if (
            $rule->discount_type
            === ServiceBundleDiscountRule::TYPE_FIXED_AMOUNT
        ) {
            $ruleCurrency = strtoupper(
                trim(
                    (string) (
                        $rule->currency
                        ?? ''
                    )
                )
            );

            if (
                $ruleCurrency === ''
                || $ruleCurrency
                    !== $subscriptionCurrency
            ) {
                throw ValidationException::withMessages([
                    'currency' =>
                        'El fixed_amount del bundle debe usar la misma moneda de la Subscription.',
                ]);
            }

            return round(
                min(
                    $bundleBase,
                    $value
                ),
                2
            );
        }

        throw ValidationException::withMessages([
            'discount_type' =>
                'El tipo de descuento del bundle no es compatible.',
        ]);
    }

    private function emptyQuote(
        string $currency
    ): array {
        return [
            'pricing_version' =>
                self::PRICING_VERSION,
            'matched' => false,
            'rule_id' => null,
            'bundle_service_id' => null,
            'currency' => $currency,
            'bundle_base_amount' => 0.0,
            'discount_amount' => 0.0,
            'snapshot' => null,
        ];
    }
}
