<?php

namespace App\Services\Billing;

use App\Services\Fiscal\FiscalDocumentTotals;
use DateTimeImmutable;

final class BusinessQuoteCalculator
{
    public function __construct(
        private readonly BusinessCatalogRules $rules = new BusinessCatalogRules,
        private readonly FiscalDocumentTotals $taxes = new FiscalDocumentTotals,
    ) {}

    public function calculate(array $catalog, array $selection, DateTimeImmutable $at, ?int $companyId = null, bool $recommend = true): array
    {
        $catalog['tax_rate'] ??= 0;
        $this->rules->validate($catalog);
        $offerCode = $selection['offer'] ?? '';
        $level = $selection['level'] ?? '';
        $cycle = $selection['cycle'] ?? '';
        $this->rules->require(in_array($cycle, ['monthly', 'yearly'], true), 'cycle', 'Seleccione mensual o anual.');
        $price = $this->rules->monthlyPrice($catalog, $offerCode, $level);
        $offer = $catalog['offers'][$offerCode];
        $products = [];
        foreach ($offer['products'] as $code) {
            $this->rules->require($catalog['products'][$code]['available'], 'products', 'Uno de los productos está retirado.');
            $products[$code] = $catalog['products'][$code];
        }
        $capacity = array_intersect_key($catalog['capacities'][$level], array_flip(BusinessCatalogRules::DIMENSIONS));
        if (! isset($products['pos'])) {
            $capacity['branches'] = $capacity['registers'] = 0;
        }
        $mode = $catalog['implementations'][$selection['implementation'] ?? ''] ?? null;
        $this->rules->require($mode !== null, 'implementation', 'Seleccione una modalidad.');
        $this->rules->require(in_array($offerCode, $mode['offers'], true), 'implementation', 'Esta modalidad todavía no tiene tarifa para la oferta elegida.');
        $this->rules->integer($mode['price_cents'], 'implementation.price', 0, 10000000000);
        $oneTime = $mode['price_cents'];
        $monthlyAddons = 0;
        $addonLines = [];
        $quantities = $selection['addons'] ?? [];
        $this->rules->require(is_array($quantities) && count($quantities) <= 100, 'addons', 'Cantidades inválidas.');
        foreach ($quantities as $code => $quantity) {
            $this->rules->integer($quantity, "addons.$code", 0, 100000);
            $addon = $catalog['addons'][$code] ?? null;
            $this->rules->require($addon !== null, "addons.$code", 'Adicional desconocido.');
            if ($quantity === 0) {
                continue;
            }
            $this->rules->require($addon['product'] === null || isset($products[$addon['product']]), "addons.$code", 'Requiere un producto que no está contratado en esta oferta.');
            $this->rules->integer($addon['price_cents'], "addons.$code.price", 0, 10000000000);
            $amount = $addon['price_cents'] * $quantity;
            if ($addon['recurring']) {
                $monthlyAddons += $amount;
            } else {
                $oneTime += $amount;
            }
            foreach ($addon['capacity'] as $dimension => $units) {
                $this->rules->require($units === 0 || ! in_array($dimension, ['branches', 'registers'], true) || isset($products['pos']), "addons.$code", 'La capacidad POS requiere POS.');
                $capacity[$dimension] += $units * $addon['block_size'] * $quantity;
                $this->rules->integer($capacity[$dimension], "capacity.$dimension", 0, 1000000000000);
            }
            $addonLines[$code] = $addon + ['quantity' => $quantity, 'amount_cents' => $amount];
        }

        $annualDiscount = 0;
        $annualExplicit = $offer['levels'][$level]['annual_cents'];
        $recurrence = $price['amount_cents'] + $monthlyAddons;
        $this->rules->integer($recurrence, 'recurrence', 0, 1000000000000);
        $this->rules->integer($oneTime, 'one_time', 0, 1000000000000);
        if ($cycle === 'yearly') {
            if ($annualExplicit !== null) {
                $addonYear = $monthlyAddons * 12;
                $annualDiscount = BusinessCatalogRules::percentage($addonYear, $catalog['annual_discount_bps']);
                $recurrence = $annualExplicit + $addonYear - $annualDiscount;
            } else {
                $recurrence *= 12;
                $annualDiscount = BusinessCatalogRules::percentage($recurrence, $catalog['annual_discount_bps']);
                $recurrence -= $annualDiscount;
            }
        }
        $discountSnapshot = null;
        $commercialDiscount = 0;
        $discountCode = $selection['discount'] ?? null;
        if ($discountCode !== null && $discountCode !== '') {
            $discount = $catalog['discounts'][$discountCode] ?? null;
            $this->rules->require($discount !== null, 'discount', 'Descuento inexistente.');
            $this->rules->require($discount['company_id'] === null || $discount['company_id'] === $companyId, 'discount', 'La condición particular pertenece a otra empresa.');
            $this->rules->require(in_array($offerCode, $discount['offers'], true), 'discount', 'Descuento incompatible con esta oferta.');
            $this->rules->require($at >= new DateTimeImmutable($discount['starts_at']) && $at < new DateTimeImmutable($discount['ends_at']), 'discount', 'Descuento fuera de vigencia.');
            $this->rules->require($cycle !== 'yearly' || $discount['compatible_annual'], 'discount', 'No se combina con anualidad.');
            $this->rules->require(count($offer['products']) === 1 || $discount['compatible_package'], 'discount', 'No se combina con tarifa de paquete.');
            $commercialDiscount = BusinessCatalogRules::percentage($recurrence, $discount['percent_bps']);
            $recurrence -= $commercialDiscount;
            $discountSnapshot = ['code' => $discountCode] + $discount;
        }

        $taxSnapshot = [];
        foreach (['recurring' => $recurrence, 'one_time' => $oneTime] as $kind => $amount) {
            $taxSnapshot[$kind] = $this->taxes->computeLine([
                'quantity' => 1, 'unit_price' => $amount / 100, 'tax_rate' => (float) $catalog['tax_rate'],
            ], (float) $catalog['tax_rate']);
        }
        $quote = [
            'schema_version' => 1, 'currency' => $catalog['currency'], 'timezone' => $catalog['timezone'],
            'calculated_at' => $at->format(DATE_ATOM), 'company_id' => $companyId,
            'selection' => $selection, 'products' => $products, 'offer' => $offer,
            'level' => $catalog['capacities'][$level], 'capacity' => $capacity,
            'capacity_policy' => $catalog['capacity_policy'] ?? null,
            'capacity_cycle' => 'monthly', 'billing_cycle' => $cycle,
            'monthly_base_cents' => $price['amount_cents'], 'price_sources' => $price['sources'],
            'addons' => $addonLines, 'implementation' => $mode,
            'recurring_cents' => $recurrence, 'one_time_cents' => $oneTime,
            'annual_price_method' => $cycle === 'yearly' ? ($annualExplicit === null ? 'formula' : 'explicit') : null,
            'annual_discount_bps' => $catalog['annual_discount_bps'], 'annual_discount_cents' => $annualDiscount,
            'commercial_discount_cents' => $commercialDiscount, 'discount' => $discountSnapshot,
            'tax_rate' => $catalog['tax_rate'], 'taxes' => $taxSnapshot, 'terms' => $catalog['terms'],
            'quote_valid_days' => $catalog['quote_valid_days'], 'recommendation' => null,
            'pos_includes_standard_ecommerce' => isset($products['pos']),
        ];
        if ($recommend) {
            $quote['recommendation'] = $this->recommend($catalog, $selection, $quote, $at, $companyId);
        }

        return $quote;
    }

    private function recommend(array $catalog, array $selection, array $quote, DateTimeImmutable $at, ?int $companyId): ?array
    {
        $best = null;
        foreach ($catalog['offers'][$selection['offer']]['levels'] as $level => $price) {
            if ($level === $selection['level'] || ! $price['enabled']) {
                continue;
            }
            $capacity = $catalog['capacities'][$level];
            if (! isset($quote['products']['pos'])) {
                $capacity['branches'] = $capacity['registers'] = 0;
            }
            $candidate = $selection;
            $candidate['level'] = $level;
            $candidate['addons'] = array_filter($selection['addons'] ?? [], fn ($code) => ! $catalog['addons'][$code]['recurring'], ARRAY_FILTER_USE_KEY);
            // Preserve the one-time scope and fill residual capacity. Branches
            // precede registers so included registers are not purchased twice.
            foreach (BusinessCatalogRules::DIMENSIONS as $dimension) {
                $missing = $quote['capacity'][$dimension] - $capacity[$dimension];
                if ($missing <= 0) {
                    continue;
                }
                $block = null;
                foreach ($catalog['addons'] as $code => $addon) {
                    $units = ($addon['capacity'][$dimension] ?? 0) * $addon['block_size'];
                    if (! $addon['recurring'] || $units <= 0 || $addon['price_cents'] === null || ($addon['product'] !== null && ! isset($quote['products'][$addon['product']]))) {
                        continue;
                    }
                    $quantity = (int) ceil($missing / $units);
                    if ($quantity + ($candidate['addons'][$code] ?? 0) > 100000) {
                        continue;
                    }
                    $cost = $quantity * $addon['price_cents'];
                    if ($block === null || $cost < $block['cost']) {
                        $block = ['code' => $code, 'quantity' => $quantity, 'cost' => $cost];
                    }
                }
                if ($block === null) {
                    continue 2;
                }
                $candidate['addons'][$block['code']] = ($candidate['addons'][$block['code']] ?? 0) + $block['quantity'];
                $addon = $catalog['addons'][$block['code']];
                foreach ($addon['capacity'] as $extraDimension => $units) {
                    $capacity[$extraDimension] += $units * $addon['block_size'] * $block['quantity'];
                }
            }
            try {
                $alternative = $this->calculate($catalog, $candidate, $at, $companyId, false);
            } catch (\DomainException) {
                continue;
            }
            if ($alternative['recurring_cents'] < ($best['recurring_cents'] ?? $quote['recurring_cents'])) {
                $best = ['level' => $level, 'name' => $capacity['name'], 'recurring_cents' => $alternative['recurring_cents'],
                    'savings_cents' => $quote['recurring_cents'] - $alternative['recurring_cents'],
                    'selection' => $candidate, 'requires_acceptance' => true];
            }
        }

        return $best;
    }
}
