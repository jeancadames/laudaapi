<?php

namespace App\Services\Billing;

use App\Models\Service;
use App\Models\ServicePricingTier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicePricingTierService
{
    public function sync(
        Service $service,
        array $tiers
    ): void {
        if (
            $service->billing_model
            !== ServicePricingEngine::MODEL_SEAT_BLOCK
        ) {
            ServicePricingTier::query()
                ->where(
                    'service_id',
                    $service->id
                )
                ->delete();

            return;
        }

        $normalized = $this->normalize(
            $service,
            $tiers
        );

        DB::transaction(
            function () use (
                $service,
                $normalized
            ) {
                ServicePricingTier::query()
                    ->where(
                        'service_id',
                        $service->id
                    )
                    ->delete();

                foreach ($normalized as $tier) {
                    ServicePricingTier::query()
                        ->create([
                            'service_id' =>
                                $service->id,
                            ...$tier,
                        ]);
                }
            }
        );
    }

    public function normalize(
        Service $service,
        array $tiers
    ): array {
        $normalized = [];

        foreach (
            array_values($tiers)
            as $index => $tier
        ) {
            if (! is_array($tier)) {
                throw ValidationException::withMessages([
                    "pricing_tiers.{$index}" =>
                        'Cada tier debe ser un objeto válido.',
                ]);
            }

            $cycle = strtolower(
                trim(
                    (string) (
                        $tier['billing_cycle']
                        ?? ''
                    )
                )
            );

            if (! in_array(
                $cycle,
                ['monthly', 'yearly'],
                true
            )) {
                throw ValidationException::withMessages([
                    "pricing_tiers.{$index}.billing_cycle" =>
                        'billing_cycle debe ser monthly o yearly.',
                ]);
            }

            $min = filter_var(
                $tier['min_quantity'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                $min === false
                || $min < 1
            ) {
                throw ValidationException::withMessages([
                    "pricing_tiers.{$index}.min_quantity" =>
                        'min_quantity debe ser entero mayor o igual a 1.',
                ]);
            }

            $maxRaw =
                $tier['max_quantity']
                ?? null;

            $max = null;

            if (
                $maxRaw !== null
                && $maxRaw !== ''
            ) {
                $max = filter_var(
                    $maxRaw,
                    FILTER_VALIDATE_INT
                );

                if (
                    $max === false
                    || $max < $min
                ) {
                    throw ValidationException::withMessages([
                        "pricing_tiers.{$index}.max_quantity" =>
                            'max_quantity debe ser nulo o mayor o igual a min_quantity.',
                    ]);
                }
            }

            $priceRaw =
                $tier['price']
                ?? null;

            if (
                $priceRaw === null
                || $priceRaw === ''
                || ! is_numeric($priceRaw)
            ) {
                throw ValidationException::withMessages([
                    "pricing_tiers.{$index}.price" =>
                        'El precio del tier debe ser numérico y explícito.',
                ]);
            }

            $price = round(
                (float) $priceRaw,
                2
            );

            if ($price < 0) {
                throw ValidationException::withMessages([
                    "pricing_tiers.{$index}.price" =>
                        'El precio del tier no puede ser negativo.',
                ]);
            }

            $currency = strtoupper(
                trim(
                    (string) (
                        $tier['currency']
                        ?? $service->currency
                        ?? 'DOP'
                    )
                )
            );

            if (
                ! preg_match(
                    '/^[A-Z]{3}$/',
                    $currency
                )
            ) {
                throw ValidationException::withMessages([
                    "pricing_tiers.{$index}.currency" =>
                        'La moneda debe ser un código ISO de tres letras.',
                ]);
            }

            $serviceCurrency = strtoupper(
                trim(
                    (string) (
                        $service->currency
                        ?? 'DOP'
                    )
                )
            );

            if (
                $currency
                !== $serviceCurrency
            ) {
                throw ValidationException::withMessages([
                    "pricing_tiers.{$index}.currency" =>
                        'La moneda del tier debe coincidir con la moneda del Service.',
                ]);
            }

            $normalized[] = [
                'billing_cycle' => $cycle,
                'min_quantity' => $min,
                'max_quantity' => $max,
                'price' => $price,
                'currency' => $currency,
                'active' => array_key_exists(
                    'active',
                    $tier
                )
                    ? (bool) $tier['active']
                    : true,
                'sort_order' =>
                    max(
                        0,
                        (int) (
                            $tier['sort_order']
                            ?? $index
                        )
                    ),
                'metadata' =>
                    isset($tier['metadata'])
                    && is_array(
                        $tier['metadata']
                    )
                        ? $tier['metadata']
                        : null,
            ];
        }

        $this->assertNoOverlaps(
            $normalized
        );

        return $normalized;
    }

    private function assertNoOverlaps(
        array $tiers
    ): void {
        foreach (
            ['monthly', 'yearly']
            as $cycle
        ) {
            $active = array_values(
                array_filter(
                    $tiers,
                    fn (array $tier): bool =>
                        $tier['billing_cycle']
                            === $cycle
                        && $tier['active']
                )
            );

            usort(
                $active,
                fn (array $a, array $b): int =>
                    $a['min_quantity']
                    <=> $b['min_quantity']
            );

            $previous = null;

            foreach ($active as $tier) {
                if ($previous !== null) {
                    $previousMax =
                        $previous['max_quantity'];

                    if (
                        $previousMax === null
                        || $tier['min_quantity']
                            <= $previousMax
                    ) {
                        throw ValidationException::withMessages([
                            'pricing_tiers' =>
                                "Los tiers activos {$cycle} no pueden solaparse.",
                        ]);
                    }
                }

                $previous = $tier;
            }
        }
    }
}
