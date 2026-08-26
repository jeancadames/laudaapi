<?php

namespace App\Services\Billing;

use App\Models\Service;
use App\Models\ServicePricingTier;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicePricingEngine
{
    public const MODEL_FLAT = 'flat';
    public const MODEL_PER_USER = 'per_user';
    public const MODEL_SEAT_BLOCK = 'seat_block';
    public const MODEL_USAGE = 'usage';

    public const PRICING_VERSION = 'global-v2';

    public function quote(
        Service $service,
        Subscription $subscription,
        ?int $quantity = null
    ): array {
        $cycle = $this->billingCycle(
            $subscription
        );

        $model = $this->billingModel(
            $service
        );

        $currency = $this->currency(
            $service,
            $subscription
        );

        if (
            $model
            === self::MODEL_USAGE
        ) {
            return [
                'pricing_version' =>
                    self::PRICING_VERSION,
                'billing_model' =>
                    self::MODEL_USAGE,
                'currency' => $currency,
                'cycle' => $cycle,
                'quantity' => 1,
                'quantity_source' => 'usage',
                'unit_price' => 0.0,
                'amount' => 0.0,
                'block_size' =>
                    $service->block_size,
                'included_units' =>
                    (int) (
                        $service->included_units
                        ?? 0
                    ),
                'unit_name' =>
                    $service->unit_name,
                'overage_unit_price' =>
                    (float) (
                        $service->overage_unit_price
                        ?? 0
                    ),
                'tier_id' => null,
                'tier_min_quantity' => null,
                'tier_max_quantity' => null,
            ];
        }

        if (
            $model
            === self::MODEL_PER_USER
        ) {
            $price = $this->cyclePrice(
                $service,
                $cycle
            );

            [
                $resolvedQuantity,
                $quantitySource,
            ] = $this->seatQuantity(
                $subscription,
                $quantity
            );

            return [
                'pricing_version' =>
                    self::PRICING_VERSION,
                'billing_model' =>
                    self::MODEL_PER_USER,
                'currency' => $currency,
                'cycle' => $cycle,
                'quantity' =>
                    $resolvedQuantity,
                'quantity_source' =>
                    $quantitySource,
                'unit_price' => $price,
                'amount' => round(
                    $price
                    * $resolvedQuantity,
                    2
                ),
                'block_size' => null,
                'included_units' => null,
                'unit_name' =>
                    $service->unit_name
                    ?: 'usuario',
                'overage_unit_price' => null,
                'tier_id' => null,
                'tier_min_quantity' => null,
                'tier_max_quantity' => null,
            ];
        }

        if (
            $model
            === self::MODEL_SEAT_BLOCK
        ) {
            [
                $resolvedQuantity,
                $quantitySource,
            ] = $this->seatQuantity(
                $subscription,
                $quantity
            );

            $tier = $this->resolveSeatBlockTier(
                $service,
                $cycle,
                $resolvedQuantity,
                $currency
            );

            $price = round(
                (float) $tier->price,
                2
            );

            $blockSize =
                $tier->max_quantity !== null
                    ? (
                        (int) $tier->max_quantity
                        - (int) $tier->min_quantity
                        + 1
                    )
                    : null;

            return [
                'pricing_version' =>
                    self::PRICING_VERSION,
                'billing_model' =>
                    self::MODEL_SEAT_BLOCK,
                'currency' => $currency,
                'cycle' => $cycle,
                'quantity' =>
                    $resolvedQuantity,
                'quantity_source' =>
                    $quantitySource,
                'unit_price' => $price,
                'amount' => $price,
                'block_size' => $blockSize,
                'included_units' => null,
                'unit_name' =>
                    $service->unit_name
                    ?: 'usuario',
                'overage_unit_price' => null,
                'tier_id' =>
                    (int) $tier->id,
                'tier_min_quantity' =>
                    (int) $tier->min_quantity,
                'tier_max_quantity' =>
                    $tier->max_quantity !== null
                        ? (int) $tier->max_quantity
                        : null,
            ];
        }

        $price = $this->cyclePrice(
            $service,
            $cycle
        );

        return [
            'pricing_version' =>
                self::PRICING_VERSION,
            'billing_model' =>
                self::MODEL_FLAT,
            'currency' => $currency,
            'cycle' => $cycle,
            'quantity' => 1,
            'quantity_source' => 'fixed',
            'unit_price' => $price,
            'amount' => $price,
            'block_size' => null,
            'included_units' => null,
            'unit_name' => null,
            'overage_unit_price' => null,
            'tier_id' => null,
            'tier_min_quantity' => null,
            'tier_max_quantity' => null,
        ];
    }

    private function resolveSeatBlockTier(
        Service $service,
        string $cycle,
        int $quantity,
        string $currency
    ): ServicePricingTier {
        $tiers = ServicePricingTier::query()
            ->where(
                'service_id',
                $service->id
            )
            ->where(
                'billing_cycle',
                $cycle
            )
            ->where('active', true)
            ->where(
                'min_quantity',
                '<=',
                $quantity
            )
            ->where(
                function ($query) use (
                    $quantity
                ) {
                    $query
                        ->whereNull(
                            'max_quantity'
                        )
                        ->orWhere(
                            'max_quantity',
                            '>=',
                            $quantity
                        );
                }
            )
            ->orderBy('min_quantity')
            ->get();

        if ($tiers->isEmpty()) {
            throw ValidationException::withMessages([
                'pricing_tier' =>
                    "No existe un tier seat_block {$cycle} para {$quantity} usuarios.",
            ]);
        }

        if ($tiers->count() !== 1) {
            throw ValidationException::withMessages([
                'pricing_tier' =>
                    'La configuración seat_block es ambigua: más de un tier resuelve la cantidad.',
            ]);
        }

        $tier = $tiers->first();

        $tierCurrency = strtoupper(
            trim(
                (string) (
                    $tier->currency
                    ?? ''
                )
            )
        );

        if (
            $tierCurrency
            !== $currency
        ) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda del tier debe coincidir con Service y Subscription.',
            ]);
        }

        if (
            (float) $tier->price
            < 0
        ) {
            throw ValidationException::withMessages([
                'price' =>
                    'El precio del tier no puede ser negativo.',
            ]);
        }

        return $tier;
    }

    private function billingCycle(
        Subscription $subscription
    ): string {
        $cycle = strtolower(
            trim(
                (string) (
                    $subscription->billing_cycle
                    ?? 'monthly'
                )
            )
        );

        if (! in_array(
            $cycle,
            ['monthly', 'yearly'],
            true
        )) {
            throw ValidationException::withMessages([
                'billing_cycle' =>
                    'El ciclo de facturación debe ser monthly o yearly.',
            ]);
        }

        return $cycle;
    }

    private function billingModel(
        Service $service
    ): string {
        $model = strtolower(
            trim(
                (string) (
                    $service->billing_model
                    ?? self::MODEL_FLAT
                )
            )
        );

        if (! in_array(
            $model,
            [
                self::MODEL_FLAT,
                self::MODEL_PER_USER,
                self::MODEL_SEAT_BLOCK,
                self::MODEL_USAGE,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'billing_model' =>
                    'El billing_model del Service no es compatible.',
            ]);
        }

        return $model;
    }

    private function currency(
        Service $service,
        Subscription $subscription
    ): string {
        $serviceCurrency = strtoupper(
            trim(
                (string) (
                    $service->currency
                    ?? ''
                )
            )
        );

        $subscriptionCurrency = strtoupper(
            trim(
                (string) (
                    $subscription->currency
                    ?? 'DOP'
                )
            )
        );

        $serviceCurrency =
            $serviceCurrency !== ''
                ? $serviceCurrency
                : $subscriptionCurrency;

        $subscriptionCurrency =
            $subscriptionCurrency !== ''
                ? $subscriptionCurrency
                : 'DOP';

        foreach ([
            'service' =>
                $serviceCurrency,
            'subscription' =>
                $subscriptionCurrency,
        ] as $field => $value) {
            if (! preg_match(
                '/^[A-Z]{3}$/',
                $value
            )) {
                throw ValidationException::withMessages([
                    'currency' =>
                        "La moneda {$field} debe usar un código ISO de tres letras.",
                ]);
            }
        }

        if (
            $serviceCurrency
            !== $subscriptionCurrency
        ) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda del Service debe coincidir con la moneda de la Subscription. '
                    .'No se permite conversión FX implícita.',
            ]);
        }

        return $serviceCurrency;
    }

    private function cyclePrice(
        Service $service,
        string $cycle
    ): float {
        $rawPrice =
            $cycle === 'yearly'
                ? $service->yearly_price
                : $service->monthly_price;

        if (
            (bool) $service->billable
            && $rawPrice === null
        ) {
            throw ValidationException::withMessages([
                'price' =>
                    "El Service facturable no tiene precio {$cycle} configurado.",
            ]);
        }

        $price = round(
            (float) ($rawPrice ?? 0),
            2
        );

        if ($price < 0) {
            throw ValidationException::withMessages([
                'price' =>
                    'El precio del Service no puede ser negativo.',
            ]);
        }

        return $price;
    }

    private function seatQuantity(
        Subscription $subscription,
        ?int $quantity
    ): array {
        if ($quantity !== null) {
            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' =>
                        'La cantidad de usuarios debe ser mayor o igual a 1.',
                ]);
            }

            return [
                $quantity,
                'explicit',
            ];
        }

        $activeUsers = DB::table(
            'subscriber_user'
        )
            ->where(
                'subscriber_id',
                $subscription->subscriber_id
            )
            ->where(
                'active',
                true
            )
            ->count();

        return [
            max(1, $activeUsers),
            'subscriber_user.active',
        ];
    }
}
