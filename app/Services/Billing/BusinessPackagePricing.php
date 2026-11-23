<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceCatalogVersion;
use App\Services\Fiscal\FiscalDocumentTotals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;

final class BusinessPackagePricing
{
    private const TASKS = [
        'ecf' => 'Emitir facturas electrónicas',
        'cumplimiento' => 'Gestionar mis obligaciones fiscales',
        'pos' => 'Registrar ventas en mi negocio',
        'bys' => 'Gestionar compras de bienes y servicios',
    ];

    private const INCLUSIONS = [
        'ecf' => ['facil_ecf' => 'Fácil ECF'],
        'pos' => ['standard_ecommerce' => 'Comercio electrónico estándar'],
    ];

    public function __construct(
        private readonly BusinessQuoteCalculator $calculator,
        private readonly FiscalDocumentTotals $taxes,
        private readonly BusinessQuoteInvoiceAmounts $amounts,
    ) {}

    public function catalog(ServiceCatalogVersion $version, Company $company): array
    {
        $catalog = $version->payload;
        $live = Service::whereIn('id', array_column($catalog['products'], 'service_id'))
            ->where('active', true)->where('billable', true)->pluck('service_key', 'id');
        $available = [];
        foreach ($catalog['products'] as $code => $product) {
            if ($product['available'] && $live->get($product['service_id']) === $code) {
                $available[] = $code;
            }
        }

        $offered = [];
        $levels = [];
        foreach ($this->guidedOffers($catalog, $available) as $offer) {
            foreach ($offer['levels'] as $level => $price) {
                if ($price['enabled']) {
                    $levels[$level] = ['code' => $level, 'name' => $catalog['capacities'][$level]['name']];
                    $offered = array_merge($offered, $offer['products']);
                }
            }
        }
        $products = [];
        foreach (self::TASKS as $code => $task) {
            if (in_array($code, $offered, true)) {
                $includes = self::INCLUSIONS[$code] ?? [];
                $products[] = ['code' => $code, 'name' => $catalog['products'][$code]['name'], 'task' => $task,
                    'includes' => array_values($includes), 'inclusion_codes' => array_keys($includes)];
            }
        }

        return ['version' => $version->version, 'company_name' => $company->name, 'products' => $products,
            'cycles' => ['monthly', 'yearly'], 'levels' => array_values($levels), 'terms' => $catalog['terms'],
            'resume_purchase_id' => null];
    }

    private function guidedOffers(array $catalog, array $products): array
    {
        $offers = array_intersect_key($catalog['offers'], array_flip($catalog['implementations']['guided']['offers'] ?? []));
        ksort($offers, SORT_STRING);

        return array_filter($offers, static fn ($offer) => $offer['available'] && ! array_diff($offer['products'], $products));
    }

    public function preview(ServiceCatalogVersion $version, Company $company, array $selection): ?array
    {
        $catalog = $version->payload;
        $now = CarbonImmutable::now('UTC');
        $products = $selection['products'];
        sort($products, SORT_STRING);
        $target = implode(',', $products);
        // One cheapest partial cover per subset. Each offer can enter the package only once.
        $covers = ['' => ['net' => 0, 'components' => [], 'products' => []]];
        foreach ($this->guidedOffers($catalog, $products) as $code => $offer) {
            if (! ($offer['levels'][$selection['level']]['enabled'] ?? false)) {
                continue;
            }
            $quote = $this->calculator->calculate($catalog, ['offer' => $code, 'level' => $selection['level'],
                'cycle' => $selection['cycle'], 'implementation' => 'guided'], $now, $company->id, false);
            $component = ['offer_code' => $code, 'name' => $offer['name'], 'products' => $offer['products'],
                'net_cents' => $quote['recurring_cents']];
            foreach ($covers as $cover) {
                if (array_intersect($offer['products'], $cover['products'])) {
                    continue;
                }
                $union = array_merge($cover['products'], $offer['products']);
                sort($union, SORT_STRING);
                $key = implode(',', $union);
                $net = $cover['net'] + $component['net_cents'];
                $components = [...$cover['components'], $component];
                $best = $covers[$key] ?? null;
                if ($best === null || [$net, count($components)] < [$best['net'], count($best['components'])]) {
                    $covers[$key] = ['net' => $net, 'products' => $union, 'components' => $components];
                }
            }
        }
        $best = $covers[$target] ?? null;
        if (! $best) {
            return null;
        }

        $mode = $catalog['implementations']['guided'];
        $rate = (float) $catalog['tax_rate'];
        $recurring = $this->amount($best['net'], $rate);
        $oneTime = $this->amount($mode['price_cents'], $rate);
        $capacity = array_intersect_key($catalog['capacities'][$selection['level']], array_flip(BusinessCatalogRules::DIMENSIONS));
        if (! in_array('pos', $products, true)) {
            $capacity['branches'] = $capacity['registers'] = 0;
        }
        $summary = ['catalog_version' => $version->version,
            'selection' => $selection, 'components' => $best['components'], 'capacity' => $capacity, 'capacity_cycle' => 'monthly',
            'implementation' => ['code' => 'guided', 'name' => $mode['name'], 'scope' => $mode['scope']],
            'terms' => $catalog['terms'], 'currency' => $catalog['currency'], 'recurring' => $recurring, 'one_time' => $oneTime,
            'due_today' => ['net_cents' => $recurring['net_cents'] + $oneTime['net_cents'],
                'tax_cents' => $recurring['tax_cents'] + $oneTime['tax_cents'], 'total_cents' => $recurring['total_cents'] + $oneTime['total_cents']],
            'due_today_cents' => $recurring['total_cents'] + $oneTime['total_cents'], 'automatic_renewal' => false];
        $expires = $now->addDays($catalog['quote_valid_days']);
        $claims = ['purpose' => 'business_package_v1', 'version_id' => $version->id, 'company_id' => $company->id,
            'subscriber_id' => $company->subscriber_id, 'selection' => $selection, 'expires_at' => $expires->timestamp,
            'summary_hash' => BusinessRenewalTerms::hash($summary)];
        return $summary + ['proposal_ref' => Crypt::encryptString(json_encode($claims, JSON_THROW_ON_ERROR)),
            'expires_at' => $expires->toIso8601String()];
    }

    /** Freeze the same components chosen by preview in the existing Business billing format. */
    public function contractSnapshot(ServiceCatalogVersion $version, Company $company, array $preview): array
    {
        $catalog = $version->payload;
        $selection = $preview['selection'] + ['implementation' => 'guided', 'addons' => [], 'discount' => null];
        $components = [];
        foreach ($preview['components'] as $line) {
            $components[$line['offer_code']] = $this->calculator->calculate($catalog, $selection + ['offer' => $line['offer_code']],
                CarbonImmutable::now('UTC'), $company->id, false);
        }
        $contract = reset($components);
        if (count($components) > 1) {
            $contract['offer'] = ['name' => implode(' + ', array_column($preview['components'], 'name')),
                'products' => $selection['products']];
            $contract['selection'] = $selection;
            $contract['annual_price_method'] = $selection['cycle'] === 'yearly' ? 'components' : null;
        }
        $contract['products'] = array_intersect_key($catalog['products'], array_flip($selection['products']));
        $contract['capacity'] = $preview['capacity'];
        $contract['pos_includes_standard_ecommerce'] = isset($contract['products']['pos']);
        $contract['monthly_base_cents'] = array_sum(array_column($components, 'monthly_base_cents'));
        $contract['annual_discount_cents'] = array_sum(array_column($components, 'annual_discount_cents'));
        $contract['price_sources'] = [];
        foreach ($components as $code => $component) {
            $contract['price_sources'][] = ['offer' => $code, 'level' => $selection['level'], 'amount_cents' => $component['recurring_cents'],
                'monthly_base_cents' => $component['monthly_base_cents'], 'annual_price_method' => $component['annual_price_method']];
        }
        foreach (['recurring', 'one_time'] as $kind) {
            $contract[$kind.'_cents'] = $preview[$kind]['net_cents'];
            $contract['taxes'][$kind] = $this->taxes->computeLine(['quantity' => 1, 'unit_price' => $preview[$kind]['net_cents'] / 100],
                (float) $contract['tax_rate']);
        }
        $contract['self_service'] = $preview;

        return $contract;
    }

    private function amount(int $net, float $rate): array
    {
        $line = $this->taxes->computeLine(['quantity' => 1, 'unit_price' => $net / 100], $rate);

        return ['net_cents' => $net, 'tax_cents' => $this->amounts->cents($line['tax_amount']),
            'total_cents' => $this->amounts->cents($line['line_total'])];
    }
}
