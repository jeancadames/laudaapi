<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\Billing\BusinessCatalogService;
use App\Services\Billing\BusinessTransitionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class BusinessTransitionFixtures
{
    public static function schema(): void
    {
        (require base_path('database/migrations/2026_11_06_000009_create_business_transitions.php'))->up();
        config()->set('business_commercial.transition_enabled', true);
    }

    public static function sources(): array
    {
        $sources = [];
        foreach (['ecf', 'pos'] as $index => $code) {
            $start = CarbonImmutable::now('UTC')->subDays(20);
            $end = CarbonImmutable::now('UTC')->addDays(5 + $index);
            $subscription = Subscription::create(['subscriber_id' => 1, 'created_by_user_id' => 1, 'status' => 'active', 'billing_cycle' => 'monthly',
                'currency' => 'DOP', 'current_period_start' => $start, 'current_period_end' => $end, 'starts_at' => $start,
                'subtotal_amount' => '1000.00', 'tax_amount' => '180.00', 'total_amount' => '1180.00']);
            $service = Service::where('service_key', $code)->sole();
            $item = SubscriptionItem::create(['subscription_id' => $subscription->id, 'service_id' => $service->id, 'status' => 'active',
                'billing_model' => 'flat', 'quantity' => 1, 'currency' => 'DOP', 'unit_price' => '1000.00', 'amount' => '1000.00']);
            $invoice = Invoice::create(['company_id' => 1, 'subscription_id' => $subscription->id, 'number' => 'SYNTHETIC-OLD-'.($index + 1),
                'status' => 'issued', 'issued_on' => $start->toDateString(), 'period_start' => $start, 'period_end' => $end,
                'currency' => 'DOP', 'subtotal' => '1000.00', 'discount_total' => '0.00', 'tax_total' => '180.00', 'total' => '1180.00', 'amount_paid' => '0.00']);
            InvoiceItem::create(['invoice_id' => $invoice->id, 'service_id' => $service->id, 'description' => 'Servicio anterior sintético '.$code,
                'quantity' => 1, 'unit_price' => '1000.00', 'line_subtotal' => '1000.00', 'discount_amount' => '0.00', 'tax_rate' => '.18', 'tax_amount' => '180.00', 'line_total' => '1180.00']);
            Payment::create(['company_id' => 1, 'invoice_id' => $invoice->id, 'currency' => 'DOP', 'method' => 'bank_transfer', 'amount' => '1180.00', 'paid_at' => $start]);
            $sources[] = ['subscription' => $subscription, 'item' => $item, 'invoice' => $invoice->fresh()];
        }

        return $sources;
    }

    public static function selection(): array
    {
        return ['offer' => 'business', 'level' => 'initial', 'cycle' => 'monthly', 'implementation' => 'assisted', 'addons' => [], 'discount' => null];
    }

    public static function input(): array
    {
        $version = ServiceCatalogVersion::firstOrFail();
        $preview = app(BusinessCatalogService::class)->preview($version, self::selection(), Company::findOrFail(1));
        $inventory = $preview['transition_review'];
        if (! $inventory['eligible']) {
            throw new \RuntimeException($inventory['reason']);
        }

        return ['mode' => BusinessTransitionService::MODE, 'confirmed' => true, 'source_hash' => $inventory['source_hash'], 'target_subscription_id' => $inventory['subscriptions'][0]['id']];
    }

    public static function issue(): ServiceCommercialQuote
    {
        $version = ServiceCatalogVersion::firstOrFail();

        return app(BusinessCatalogService::class)->issue($version, $version->lock_version, Company::findOrFail(1), self::selection(), (string) Str::uuid(), 4, self::input());
    }
}
