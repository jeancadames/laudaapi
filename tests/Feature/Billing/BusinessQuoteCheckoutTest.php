<?php

namespace Tests\Feature\Billing;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteAcceptance;
use App\Models\ServiceQuoteCheckout;
use App\Models\User;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\Billing\BusinessQuoteCalculator;
use App\Services\Billing\InvoiceReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessQuoteCheckoutFixtures;
use Tests\Support\BusinessQuoteFixtures;

final class BusinessQuoteCheckoutTest extends TestCase
{
    private string $runtime;

    private ServiceCommercialQuote $quote;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business checkout tests only');

        $this->beforeApplicationDestroyed(BusinessIdentityApplication::restoreEnvironmentCallback());

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central');
    }

    protected function setUp(): void
    {
        parent::setUp();
        BusinessQuoteCheckoutFixtures::schema();
        Mail::fake();
        Notification::fake();
        $this->quote = BusinessQuoteCheckoutFixtures::seed();
        $this->withoutVite();
        $this->actingAs(User::findOrFail(1))->withSession(['_token' => 'checkout-csrf'])->withHeader('X-CSRF-TOKEN', 'checkout-csrf');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->runtime);
    }

    private function url(?ServiceCommercialQuote $quote = null): string
    {
        return '/app/business/quotes/'.($quote ?? $this->quote)->public_id.'/checkout';
    }

    private function payload(?ServiceCommercialQuote $quote = null): array
    {
        return ['confirmed' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote ?? $this->quote), 'idempotency_key' => (string) Str::uuid()];
    }

    private function accept(?ServiceCommercialQuote $quote = null): ServiceQuoteAcceptance
    {
        $quote ??= $this->quote;

        return app(BusinessQuoteAcceptanceService::class)->accept(User::findOrFail(1), $quote->public_id, BusinessQuoteAcceptanceService::fingerprint($quote), (string) Str::uuid());
    }

    private function createInvoice(): Invoice
    {
        $this->accept();
        $this->postJson($this->url(), $this->payload())->assertOk();

        return Invoice::sole();
    }

    public function test_accepted_quote_review_is_private_and_get_does_not_bill(): void
    {
        $this->accept();
        $response = $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->component('Business/Quotes/Checkout')->where('checkout.amounts.total_cents', 1121000)
            ->where('checkout.invoice', null)->where('checkout.unavailable_reason', null));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(0, Invoice::count());
    }

    public function test_checkout_persists_contract_taxes_and_audit_without_payment_or_entitlement(): void
    {
        $this->withoutExceptionHandling();
        $receipt = $this->accept();
        $response = $this->postJson($this->url(), [...$this->payload(), 'total' => 1, 'company_id' => 2, 'tax_rate' => 0])->assertOk();
        $response->assertJsonPath('checkout.invoice.total_cents', 1121000)->assertJsonPath('checkout.invoice.balance_cents', 1121000);
        $invoice = Invoice::sole();
        $this->assertSame('9500.00', $invoice->subtotal);
        $this->assertSame('1710.00', $invoice->tax_total);
        $this->assertSame('11210.00', $invoice->total);
        $this->assertSame('issued', $invoice->status);
        $this->assertNull($invoice->subscription_id);
        $this->assertNull($invoice->fiscal_number);
        $this->assertSame($receipt->snapshot, $invoice->billing_snapshot['price_snapshot']);
        $this->assertSame($receipt->snapshot_hash, $invoice->billing_snapshot['snapshot_hash']);
        $this->assertSame(['4130.00', '7080.00'], $invoice->items->pluck('line_total')->all());
        $this->assertSame([null, null], $invoice->items->pluck('service_id')->all());
        $this->assertSame(['recurring', 'one_time'], $invoice->items->pluck('meta.kind')->all());
        $this->assertSame(1, ServiceQuoteCheckout::count());
        $this->assertSame(1, AuditLog::where('event', 'business.quote.checkout_created')->count());
        foreach (['payments', 'subscriptions', 'subscription_items', 'standalone_service_settlements', 'business_identity_links', 'diagnosis_assessments', 'transformation_capability_activations'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_acceptance_and_explicit_confirmation_hash_and_csrf_are_required(): void
    {
        $this->getJson($this->url())->assertConflict();
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->accept();
        $this->postJson($this->url(), [...$this->payload(), 'confirmed' => false])->assertUnprocessable();
        $this->postJson($this->url(), [...$this->payload(), 'snapshot_hash' => str_repeat('0', 64)])->assertConflict();
        $this->postJson($this->url(), [...$this->payload(), 'idempotency_key' => 'invalid'])->assertUnprocessable();
        $this->withHeader('X-CSRF-TOKEN', 'wrong')->postJson($this->url(), $this->payload())->assertStatus(419);
        $this->assertSame(0, Invoice::count());
    }

    public function test_same_or_new_key_retrieves_one_invoice_even_after_expiry_retirement_and_price_changes(): void
    {
        $this->accept();
        $payload = $this->payload();
        $first = $this->postJson($this->url(), $payload)->assertOk()->json('checkout.invoice');
        CarbonImmutable::setTestNow($this->quote->valid_until->addDay());
        DB::table('service_catalog_versions')->update(['status' => 'retired']);
        DB::table('services')->update(['monthly_price' => 999999, 'active' => false]);
        $this->assertSame($first, $this->postJson($this->url(), $payload)->assertOk()->json('checkout.invoice'));
        $this->assertSame($first, $this->postJson($this->url(), $this->payload())->assertOk()->json('checkout.invoice'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, DB::table('invoice_items')->count());
        $this->assertSame(1, AuditLog::where('event', 'business.quote.checkout_created')->count());
    }

    public function test_an_accepted_contract_can_be_invoiced_after_quote_expiry_without_repricing(): void
    {
        $this->accept();
        CarbonImmutable::setTestNow($this->quote->valid_until->addDay());
        DB::table('service_catalog_versions')->update(['status' => 'retired']);
        DB::table('services')->update(['monthly_price' => 888888]);
        $this->postJson($this->url(), $this->payload())->assertOk()->assertJsonPath('checkout.invoice.total_cents', 1121000);
    }

    public static function unauthorizedActors(): array
    {
        return [[2], [3], [4]];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_only_company_owner_or_admin_can_read_or_bill(int $actor): void
    {
        $this->accept();
        $this->actingAs(User::findOrFail($actor));
        $this->get($this->url())->assertNotFound();
        $this->postJson($this->url(), $this->payload())->assertNotFound();
        $this->assertSame(0, Invoice::count());
    }

    public function test_guest_and_unverified_user_cannot_bill(): void
    {
        $this->accept();
        $this->app['auth']->logout();
        $this->postJson($this->url(), $this->payload())->assertUnauthorized();
        DB::table('users')->where('id', 1)->update(['email_verified_at' => null]);
        $this->actingAs(User::findOrFail(1));
        $this->postJson($this->url(), $this->payload())->assertForbidden();
        $this->assertSame(0, Invoice::count());
    }

    public static function revokedContexts(): array
    {
        return [['membership'], ['company'], ['subscriber'], ['ownership']];
    }

    #[DataProvider('revokedContexts')]
    public function test_retry_checks_current_membership_and_company_ownership(string $change): void
    {
        $this->createInvoice();
        if ($change === 'membership') {
            DB::table('subscriber_user')->where('user_id', 1)->update(['active' => false]);
        }
        if ($change === 'company') {
            DB::table('companies')->where('id', 1)->update(['active' => false]);
        }
        if ($change === 'subscriber') {
            DB::table('subscribers')->where('id', 1)->update(['active' => false]);
        }
        if ($change === 'ownership') {
            DB::table('companies')->where('id', 2)->update(['subscriber_id' => null]);
            DB::table('companies')->where('id', 1)->update(['subscriber_id' => 2]);
            $this->actingAs(User::findOrFail(2));
        }
        $this->get($this->url())->assertNotFound();
        $this->postJson($this->url(), $this->payload())->assertNotFound();
        $this->assertSame(1, Invoice::count());
    }

    public function test_tenant_admin_can_bill(): void
    {
        $this->accept();
        DB::table('subscriber_user')->where('user_id', 1)->update(['role' => 'admin']);
        $this->postJson($this->url(), $this->payload())->assertOk();
    }

    public static function commercialChanges(): array
    {
        return [['currency'], ['inactive'], ['not_billable'], ['wrong_service'], ['active_subscription']];
    }

    #[DataProvider('commercialChanges')]
    public function test_changed_commercial_context_blocks_new_invoice(string $change): void
    {
        $this->accept();
        if ($change === 'currency') {
            DB::table('subscribers')->where('id', 1)->update(['currency' => 'USD']);
        }
        if ($change === 'inactive') {
            DB::table('services')->where('id', 1)->update(['active' => false]);
        }
        if ($change === 'not_billable') {
            DB::table('services')->where('id', 1)->update(['billable' => false]);
        }
        if ($change === 'wrong_service') {
            DB::table('services')->where('id', 1)->update(['service_key' => 'different']);
        }
        if ($change === 'active_subscription') {
            $id = DB::table('subscriptions')->insertGetId(['subscriber_id' => 1, 'status' => 'active']);
            DB::table('subscription_items')->insert(['subscription_id' => $id, 'service_id' => 1, 'status' => 'active']);
        }
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame(0, Invoice::count());
    }

    public function test_multiple_acceptances_and_reused_keys_cannot_duplicate_business_contracts(): void
    {
        $other = BusinessQuoteFixtures::issue();
        $this->accept($other);
        $this->accept();
        $payload = $this->payload();
        $this->postJson($this->url(), $payload)->assertOk();
        $this->postJson($this->url($other), [...$this->payload($other), 'idempotency_key' => $payload['idempotency_key']])->assertConflict();
        $this->postJson($this->url($other), $this->payload($other))->assertConflict();
        $this->assertSame(1, Invoice::count());
    }

    public function test_pending_standalone_invoice_requires_transition_review(): void
    {
        $this->accept();
        Invoice::create(['company_id' => 1, 'number' => 'SYNTHETIC-STANDALONE', 'status' => 'issued', 'currency' => 'DOP',
            'total' => '100.00', 'billing_snapshot' => ['source' => 'standalone_service_checkout']]);
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, ServiceQuoteCheckout::count());
    }

    public function test_void_invoice_with_paid_money_still_requires_transition_review(): void
    {
        $other = BusinessQuoteFixtures::issue();
        $this->accept($other);
        $invoice = $this->createInvoice();
        Payment::create(['invoice_id' => $invoice->id, 'company_id' => 1, 'currency' => 'DOP', 'amount' => '100.00', 'method' => 'bank_transfer', 'paid_at' => now()]);
        $invoice->refresh()->update(['status' => 'void']);
        $this->postJson($this->url($other), $this->payload($other))->assertConflict();
        $this->assertSame(1, Invoice::count());
    }

    public function test_orphan_business_invoice_is_not_overwritten(): void
    {
        $receipt = $this->accept();
        Invoice::create(['company_id' => 1, 'number' => sprintf('LAUDA-BIZ-%010d', $receipt->id), 'status' => 'issued', 'currency' => 'DOP', 'total' => '100.00']);
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame('100.00', Invoice::sole()->total);
        $this->assertSame(0, ServiceQuoteCheckout::count());
    }

    public function test_partial_unconfirmed_full_and_reversed_payments_use_existing_reconciliation(): void
    {
        $invoice = $this->createInvoice();
        $payment = Payment::create(['invoice_id' => $invoice->id, 'company_id' => 1, 'currency' => 'DOP', 'amount' => '1210.00', 'method' => 'bank_transfer', 'paid_at' => now()]);
        $pending = Payment::create(['invoice_id' => $invoice->id, 'company_id' => 1, 'currency' => 'DOP', 'amount' => '10000.00', 'method' => 'bank_transfer']);
        $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('checkout.invoice.status', 'issued')->where('checkout.invoice.paid_cents', 121000)->where('checkout.invoice.balance_cents', 1000000)->has('checkout.invoice.payments', 1));
        $pending->update(['paid_at' => now()]);
        $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('checkout.invoice.status', 'paid')->where('checkout.invoice.balance_cents', 0)->has('checkout.invoice.payments', 2));
        $payment->update(['paid_at' => null]);
        $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('checkout.invoice.status', 'issued')->where('checkout.invoice.balance_cents', 121000)->has('checkout.invoice.payments', 1));
        $this->assertSame(0, DB::table('subscription_items')->count());
        $this->assertSame(0, DB::table('standalone_service_settlements')->count());
    }

    public function test_payment_currency_or_company_mismatch_is_rejected_by_existing_circuit(): void
    {
        $invoice = $this->createInvoice();
        foreach ([['currency' => 'USD'], ['company_id' => 2]] as $wrong) {
            try {
                Payment::create([...['invoice_id' => $invoice->id, 'company_id' => 1, 'currency' => 'DOP', 'amount' => '100.00', 'method' => 'bank_transfer', 'paid_at' => now()], ...$wrong]);
                $this->fail('An inconsistent payment was accepted.');
            } catch (ValidationException) {
                $this->assertSame(0, Payment::count());
            }
        }
    }

    public function test_payment_link_is_only_shown_for_positive_payable_balance_and_valid_https_url(): void
    {
        $invoice = $this->createInvoice();
        foreach (['javascript:alert(1)', 'http://payments.invalid/pay', 'https://user:secret@payments.invalid/pay'] as $url) {
            $invoice->update(['payment_url' => $url]);
            $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('checkout.invoice.payment_url', null));
        }
        $invoice->update(['payment_url' => 'https://payments.invalid/pay']);
        $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('checkout.invoice.payment_url', 'https://payments.invalid/pay'));
        $invoice->update(['status' => 'void']);
        $this->postJson($this->url(), $this->payload())->assertOk()->assertJsonPath('checkout.invoice.status', 'void')->assertJsonPath('checkout.invoice.payment_url', null);
        $this->assertSame(1, Invoice::count());
    }

    public function test_corrupt_invoice_or_acceptance_is_not_repriced_or_replaced(): void
    {
        $invoice = $this->createInvoice();
        $invoice->update(['total' => '1.00']);
        $this->getJson($this->url())->assertConflict();
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame(1, Invoice::count());
        DB::table('service_quote_acceptances')->update(['snapshot_hash' => str_repeat('0', 64)]);
        $this->postJson($this->url(), $this->payload())->assertConflict();
    }

    public function test_audit_failure_rolls_back_invoice_lines_and_ledger(): void
    {
        $this->accept();
        AuditLog::creating(function ($log) {
            if ($log->event === 'business.quote.checkout_created') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->postJson($this->url(), $this->payload())->assertStatus(500);
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, DB::table('invoice_items')->count());
        $this->assertSame(0, ServiceQuoteCheckout::count());
        $this->assertSame(1, ServiceQuoteAcceptance::count());
    }

    public function test_unique_constraints_immutability_and_rollback_preserve_history(): void
    {
        $this->createInvoice();
        $checkout = ServiceQuoteCheckout::sole();
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $checkout->update(['snapshot_hash' => str_repeat('0', 64)]) : $checkout->delete();
                $this->fail('Immutable ledger changed.');
            } catch (\LogicException) {
                $this->assertSame(1, ServiceQuoteCheckout::count());
            }
        }
        try {
            $attributes = $checkout->fresh()->getAttributes();
            unset($attributes['id']);
            DB::table('service_quote_checkouts')->insert($attributes);
            $this->fail('Duplicate ledger was accepted.');
        } catch (QueryException) {
            $this->assertSame(1, ServiceQuoteCheckout::count());
        }
        try {
            (require base_path('database/migrations/2026_11_06_000005_create_service_quote_checkouts.php'))->down();
            $this->fail('History was dropped.');
        } catch (\RuntimeException) {
            $this->assertTrue(Schema::hasTable('service_quote_checkouts'));
        }
    }

    public function test_disabled_checkout_and_missing_migration_fail_closed(): void
    {
        $this->accept();
        config()->set('business_commercial.checkout_enabled', false);
        $this->get($this->url())->assertNotFound();
        $this->postJson($this->url(), $this->payload())->assertNotFound();
        config()->set('business_commercial.checkout_enabled', true);
        (require base_path('database/migrations/2026_11_06_000005_create_service_quote_checkouts.php'))->down();
        $this->getJson($this->url())->assertStatus(503);
        $this->postJson($this->url(), $this->payload())->assertStatus(503);
        $this->assertFalse(Schema::hasTable('service_quote_checkouts'));
        $this->assertSame(0, Invoice::count());
    }

    private function replaceSnapshot(array $snapshot): void
    {
        DB::table('service_commercial_quotes')->where('id', $this->quote->id)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $this->quote = $this->quote->fresh();
    }

    public function test_annual_discounts_and_addons_are_not_applied_twice(): void
    {
        $payload = ServiceCatalogVersion::firstOrFail()->payload;
        $selection = $this->quote->snapshot['selection'];
        $selection['cycle'] = 'yearly';
        $selection['addons'] = ['branch' => 1, 'hours' => 2];
        $selection['discount'] = 'qa';
        $payload['discounts']['qa'] = ['name' => 'Condición QA', 'percent_bps' => 750, 'company_id' => 1, 'reason' => 'Synthetic contract discount',
            'compatible_annual' => true, 'compatible_package' => true, 'offers' => ['business'],
            'starts_at' => now()->subDay()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String()];
        $snapshot = app(BusinessQuoteCalculator::class)->calculate($payload, $selection, CarbonImmutable::now('UTC'), 1);
        $snapshot['commercial_status'] = 'quoted';
        $this->replaceSnapshot($snapshot);
        $invoice = $this->createInvoice();
        $expected = $snapshot['taxes']['recurring']['line_total'] + $snapshot['taxes']['one_time']['line_total'];
        $this->assertSame(number_format($expected, 2, '.', ''), $invoice->total);
        $this->assertSame('0.00', $invoice->discount_total);
        $this->assertSame('yearly', $invoice->billing_snapshot['billing_cycle']);
        $this->assertGreaterThan(0, $snapshot['annual_discount_cents']);
        $this->assertGreaterThan(0, $snapshot['commercial_discount_cents']);
        $this->assertSame(900000, $snapshot['one_time_cents']);
        app(InvoiceReconciliationService::class)->recalculateFromItems($invoice);
        $this->assertSame($invoice->total, $invoice->fresh()->total);
    }

    public static function invalidAmounts(): array
    {
        return [['tax'], ['total'], ['precision'], ['overflow']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_contract_arithmetic_or_db_precision_is_rejected(string $change): void
    {
        $snapshot = $this->quote->snapshot;
        if ($change === 'tax') {
            $snapshot['taxes']['recurring']['taxable_base'] = 1;
        }
        if ($change === 'total') {
            $snapshot['taxes']['recurring']['line_total'] = 1;
        }
        if ($change === 'precision') {
            $snapshot['taxes']['recurring']['tax_amount'] = 0.001;
        }
        if ($change === 'overflow') {
            $snapshot['recurring_cents'] = 10000000000;
        }
        $this->replaceSnapshot($snapshot);
        $this->accept();
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame(0, Invoice::count());
    }

    public function test_explicit_zero_quote_has_no_payment_or_automatic_activation(): void
    {
        $snapshot = $this->quote->snapshot;
        foreach (['recurring', 'one_time'] as $kind) {
            $snapshot[$kind.'_cents'] = 0;
            $snapshot['taxes'][$kind] = ['taxable_base' => 0, 'tax_amount' => 0, 'line_total' => 0];
        }
        $this->replaceSnapshot($snapshot);
        $invoice = $this->createInvoice();
        $this->assertSame('0.00', $invoice->total);
        $this->assertSame('issued', $invoice->status);
        $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('checkout.invoice.balance_cents', 0)->where('checkout.invoice.payment_url', null));
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, DB::table('subscription_items')->count());
    }
}
