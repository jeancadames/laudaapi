<?php

namespace Tests\Support;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceCommercialQuote;
use App\Models\User;
use App\Services\Billing\BusinessImplementationService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\Billing\BusinessQuoteCheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BusinessContractFixtures
{
    public static function schema(): void
    {
        BusinessImplementationFixtures::schema();
        foreach (['2026_08_24_190000_create_service_bundle_discount_rules_and_applications.php',
            '2026_11_06_000007_create_business_contracts.php'] as $migration) {
            (require base_path('database/migrations/'.$migration))->up();
        }
    }

    public static function configure(): void
    {
        config()->set('business_commercial.activation_enabled', true);
        config()->set('business_commercial.implementation_enabled', true);
        config()->set('business_commercial.checkout_enabled', true);
        config()->set('business_identity.enabled', true);
        config()->set('business_identity.issuer', 'https://hub.example');
        foreach (['ecf', 'pos', 'bys', 'cumplimiento'] as $code) {
            config()->set('business_identity.peers.'.$code, ['url' => 'https://'.$code.'.example', 'secret' => str_repeat('synthetic-key-', 4)]);
        }
    }

    public static function links(ServiceCommercialQuote $quote, int $ownerId = 1): void
    {
        foreach ($quote->snapshot['products'] as $code => $product) {
            $id = (string) Str::uuid();
            DB::table('business_identity_links')->insert(['id' => $id, 'service' => $code, 'central_company_id' => $quote->company_id,
                'central_subscriber_id' => $quote->subscriber_id, 'local_company_id' => 500 + (int) $quote->company_id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('business_identity_members')->insert(['link_id' => $id, 'central_user_id' => $ownerId, 'local_user_id' => 200 + $ownerId,
                'consent' => bin2hex(random_bytes(16)), 'generation' => 1, 'active' => true, 'ready_at' => now(), 'updated_at' => now()]);
        }
    }

    public static function seed(): ServiceCommercialQuote
    {
        self::configure();
        $quote = BusinessQuoteCheckoutFixtures::seed();

        return self::prepare($quote);
    }

    public static function prepare(ServiceCommercialQuote $quote, int $ownerId = 1): ServiceCommercialQuote
    {
        $user = User::findOrFail($ownerId);
        $hash = BusinessQuoteAcceptanceService::fingerprint($quote);
        app(BusinessQuoteAcceptanceService::class)->accept($user, $quote->public_id, $hash, (string) Str::uuid());
        $checkout = app(BusinessQuoteCheckoutService::class)->checkout($user, $quote->public_id, $hash, (string) Str::uuid());
        $invoice = Invoice::findOrFail($checkout->invoice_id);
        if ((float) $invoice->total > 0) {
            Payment::create(['company_id' => $quote->company_id, 'invoice_id' => $invoice->id, 'method' => 'bank_transfer',
                'currency' => 'DOP', 'amount' => $invoice->total, 'paid_at' => now()]);
        }
        $implementation = app(BusinessImplementationService::class);
        foreach (['request', 'start', 'deliver', 'complete'] as $index => $action) {
            $admin = in_array($action, ['start', 'deliver'], true);
            $implementation->act(User::findOrFail($admin ? 4 : $ownerId), $quote->public_id,
                ['action' => $action, 'version' => $index, 'note' => 'Evidencia sintética de implementación.', 'snapshot_hash' => $hash, 'idempotency_key' => (string) Str::uuid()], $admin);
        }
        self::links($quote, $ownerId);

        return $quote;
    }
}
