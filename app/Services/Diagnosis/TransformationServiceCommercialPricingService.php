<?php

namespace App\Services\Diagnosis;

use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationServiceCommercialPricingService
{
    public function allowedServiceKeys(): array
    {
        return collect(
            TransformationServiceCapabilityCatalog::all()
        )
            ->pluck('service_key')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function preview(
        string $serviceKey,
        string $currency,
        float|int|string $monthlyPrice,
        float|int|string $yearlyPrice
    ): array {
        $service = $this->serviceFor($serviceKey);

        $normalizedCurrency =
            $this->normalizeCurrency($currency);

        $monthly =
            $this->normalizePrice(
                $monthlyPrice,
                'monthly_price'
            );

        $yearly =
            $this->normalizePrice(
                $yearlyPrice,
                'yearly_price'
            );

        $this->assertPricingModel($service);

        return [
            'service_id' => $service->id,
            'service_key' => $service->service_key,
            'title' => $service->title,
            'current' => [
                'currency' => $service->currency,
                'monthly_price' => $service->monthly_price,
                'yearly_price' => $service->yearly_price,
            ],
            'proposed' => [
                'currency' => $normalizedCurrency,
                'monthly_price' => $monthly,
                'yearly_price' => $yearly,
            ],
            'changes' => [
                'currency' =>
                    strtoupper((string) $service->currency)
                    !== $normalizedCurrency,
                'monthly_price' =>
                    (float) ($service->monthly_price ?? 0)
                    !== $monthly
                    || $service->monthly_price === null,
                'yearly_price' =>
                    (float) ($service->yearly_price ?? 0)
                    !== $yearly
                    || $service->yearly_price === null,
            ],
            'existing_subscription_items' =>
                DB::table('subscription_items')
                    ->where('service_id', $service->id)
                    ->count(),
            'mapping_count' =>
                DB::table(
                    'transformation_implementation_capability_service_mappings'
                )
                    ->where('service_id', $service->id)
                    ->count(),
        ];
    }

    public function apply(
        string $serviceKey,
        string $currency,
        float|int|string $monthlyPrice,
        float|int|string $yearlyPrice
    ): Service {
        $preview = $this->preview(
            $serviceKey,
            $currency,
            $monthlyPrice,
            $yearlyPrice
        );

        return DB::transaction(function () use ($preview) {
            $service = Service::query()
                ->whereKey($preview['service_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $service->forceFill([
                'currency' =>
                    $preview['proposed']['currency'],
                'monthly_price' =>
                    $preview['proposed']['monthly_price'],
                'yearly_price' =>
                    $preview['proposed']['yearly_price'],
            ])->save();

            return $service->fresh();
        });
    }

    public function readiness(
        string $serviceKey,
        ?string $subscriptionCurrency = null
    ): array {
        $service = $this->serviceFor($serviceKey);

        $reasons = [];

        if (! $service->active) {
            $reasons[] = 'service_inactive';
        }

        if (
            $service->billable
            && in_array(
                $service->billing_model,
                ['flat', 'per_user'],
                true
            )
        ) {
            if ($service->monthly_price === null) {
                $reasons[] = 'monthly_price_missing';
            }

            if ($service->yearly_price === null) {
                $reasons[] = 'yearly_price_missing';
            }
        }

        if (
            $service->billable
            && $service->billing_model === 'seat_block'
        ) {
            $monthlyTiers = DB::table(
                'service_pricing_tiers'
            )
                ->where('service_id', $service->id)
                ->where('billing_cycle', 'monthly')
                ->where('active', true)
                ->count();

            $yearlyTiers = DB::table(
                'service_pricing_tiers'
            )
                ->where('service_id', $service->id)
                ->where('billing_cycle', 'yearly')
                ->where('active', true)
                ->count();

            if ($monthlyTiers < 1) {
                $reasons[] = 'monthly_tier_missing';
            }

            if ($yearlyTiers < 1) {
                $reasons[] = 'yearly_tier_missing';
            }
        }

        if ($subscriptionCurrency !== null) {
            $target =
                $this->normalizeCurrency(
                    $subscriptionCurrency
                );

            if (
                strtoupper((string) $service->currency)
                !== $target
            ) {
                $reasons[] = 'currency_mismatch';
            }
        }

        return [
            'service_key' => $service->service_key,
            'service_id' => $service->id,
            'currency' => $service->currency,
            'monthly_price' => $service->monthly_price,
            'yearly_price' => $service->yearly_price,
            'ready' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    private function serviceFor(
        string $serviceKey
    ): Service {
        $serviceKey = trim($serviceKey);

        if (
            $serviceKey === ''
            || ! in_array(
                $serviceKey,
                $this->allowedServiceKeys(),
                true
            )
        ) {
            throw ValidationException::withMessages([
                'service_key' =>
                    'El Service no pertenece al catálogo de capabilities de Transformación 360.',
            ]);
        }

        $service = Service::query()
            ->where('service_key', $serviceKey)
            ->first();

        if (! $service) {
            throw ValidationException::withMessages([
                'service_key' =>
                    'El service_key no existe en el catálogo services.',
            ]);
        }

        return $service;
    }

    private function normalizeCurrency(
        string $currency
    ): string {
        $currency =
            strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages([
                'currency' =>
                    'La moneda debe ser un código ISO de tres letras.',
            ]);
        }

        if ($currency !== 'DOP') {
            throw ValidationException::withMessages([
                'currency' =>
                    'Los Services comerciales de Transformación 360 se configuran únicamente en DOP.',
            ]);
        }

        return $currency;
    }

    private function normalizePrice(
        float|int|string $price,
        string $field
    ): float {
        if (
            $price === ''
            || ! is_numeric($price)
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'El precio debe ser numérico y explícito.',
            ]);
        }

        $price = round((float) $price, 2);

        if ($price < 0) {
            throw ValidationException::withMessages([
                $field =>
                    'El precio no puede ser negativo.',
            ]);
        }

        return $price;
    }

    private function assertPricingModel(
        Service $service
    ): void {
        if (! $service->active) {
            throw ValidationException::withMessages([
                'service' =>
                    'No se puede configurar un Service inactivo.',
            ]);
        }

        if (! $service->billable) {
            throw ValidationException::withMessages([
                'service' =>
                    'Este configurador está reservado para Services facturables.',
            ]);
        }

        if (
            ! in_array(
                $service->billing_model,
                ['flat', 'per_user'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'billing_model' =>
                    'El configurador mensual/anual solo admite flat o per_user; seat_block se configura mediante tiers.',
            ]);
        }
    }
}
