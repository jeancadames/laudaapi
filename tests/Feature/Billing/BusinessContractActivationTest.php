<?php

namespace Tests\Feature\Billing;

use App\Models\ActivationRequestService;
use App\Models\AuditLog;
use App\Models\BusinessContract;
use App\Models\BusinessImplementationOrder;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\Billing\BusinessContractActivationService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\Billing\BusinessQuoteCalculator;
use App\Services\Billing\StandaloneServiceCheckoutService;
use App\Services\Billing\SubscriptionTotalsService;
use App\Services\Entitlements\CentralEntitlementActivationService;
use App\Services\Entitlements\SubscriberEntitlements;
use App\Services\LaudaErp\ServiceAccessResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessContractFixtures;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessQuoteFixtures;

final class BusinessContractActivationTest extends TestCase
{
    private string $runtime;

    private ServiceCommercialQuote $quote;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business activation tests only');

        $this->beforeApplicationDestroyed(BusinessIdentityApplication::restoreEnvironmentCallback());

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central');
    }

    protected function setUp(): void
    {
        parent::setUp();
        BusinessContractFixtures::schema();
        Mail::fake();
        Notification::fake();
        $this->quote = BusinessContractFixtures::seed();
        $this->withoutVite();
        $this->asUser(4);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->runtime);
    }

    private function asUser(int $id): void
    {
        $this->actingAs(User::findOrFail($id))->withSession(['_token' => 'activation-csrf'])->withHeader('X-CSRF-TOKEN', 'activation-csrf');
    }

    private function url(bool $admin = true): string
    {
        return $admin ? '/admin/business-implementations/'.$this->quote->public_id.'/activation' : '/app/business/quotes/'.$this->quote->public_id.'/activation';
    }

    private function payload(): array
    {
        return ['confirmed' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($this->quote),
            'idempotency_key' => (string) Str::uuid(), 'implementation_version' => BusinessImplementationOrder::where('scope_snapshot->company_id', $this->quote->company_id)->sole()->lock_version,
            'technical_evidence' => array_fill_keys(array_keys($this->quote->snapshot['products']), 'Destino y empresa verificados por administración en QA.')];
    }

    private function activate(): BusinessContract
    {
        $this->postJson($this->url(), $this->payload())->assertOk()->assertJsonPath('activation.state', 'active');

        return BusinessContract::sole();
    }

    private function canAccess(string $code = 'pos'): bool
    {
        return app(ServiceAccessResolver::class)->userCanAccess(User::findOrFail(1), Company::findOrFail(1), Service::where('service_key', $code)->sole());
    }

    public function test_review_is_private_and_does_not_create_contract_or_subscription(): void
    {
        $response = $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->component('Business/Activation/Show')->where('activation.can_activate', true)->where('activation.contract', null)
            ->where('activation.recurring.total_cents', 413000));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(0, BusinessContract::count());
        $this->assertSame(0, Subscription::count());
        $this->assertFalse($this->canAccess());
    }

    public function test_activation_keeps_package_price_and_initial_invoice_and_grants_coverage(): void
    {
        $invoice = Invoice::sole()->getAttributes();
        $contract = $this->activate();
        $this->assertSame($this->quote->snapshot, $contract->snapshot);
        $subscription = Subscription::sole();
        $this->assertSame('3500.00', $subscription->subtotal_amount);
        $this->assertSame('0.00', $subscription->discount_amount);
        $this->assertSame('630.00', $subscription->tax_amount);
        $this->assertSame('4130.00', $subscription->total_amount);
        $this->assertSame(4, SubscriptionItem::count());
        $this->assertSame(4, DB::table('business_contract_items')->count());
        $this->assertSame(0.0, (float) SubscriptionItem::sum('amount'));
        $this->assertSame($invoice, Invoice::sole()->getAttributes());
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, AuditLog::where('event', 'business.contract.activated')->count());
        $this->assertTrue($this->canAccess());
        $this->assertCount(4, app(SubscriberEntitlements::class)->erpServicesForSubscriber(1));
        $this->assertFalse($subscription->meta['business_contract']['automatic_renewal']);
    }

    public function test_existing_empty_subscription_is_reused(): void
    {
        $subscription = Subscription::create(['subscriber_id' => 1, 'status' => 'active', 'currency' => 'DOP', 'billing_cycle' => 'yearly']);
        $contract = $this->activate();
        $this->assertSame($subscription->id, $contract->subscription_id);
        $this->assertSame(1, Subscription::count());
        $this->assertSame('monthly', $subscription->fresh()->billing_cycle);
    }

    public function test_retries_recover_same_contract_after_reversal_and_cannot_change_evidence(): void
    {
        $payload = $this->payload();
        $this->postJson($this->url(), $payload)->assertOk();
        $original = BusinessContract::sole()->getAttributes();
        Payment::sole()->update(['paid_at' => null]);
        $this->postJson($this->url(), $payload)->assertOk()->assertJsonPath('activation.state', 'review');
        $changed = $payload;
        $changed['technical_evidence']['pos'] = 'Evidencia diferente con la misma clave.';
        $this->postJson($this->url(), $changed)->assertConflict();
        $this->postJson($this->url(), $this->payload())->assertOk();
        $this->assertSame($original, BusinessContract::sole()->getAttributes());
        $this->assertSame(1, Subscription::count());
        $this->assertSame(1, AuditLog::where('event', 'business.contract.activated')->count());
    }

    public static function deniedUsers(): array
    {
        return [[1], [2], [3]];
    }

    #[DataProvider('deniedUsers')]
    public function test_only_platform_admin_can_activate(int $id): void
    {
        $this->asUser($id);
        $this->postJson($this->url(), $this->payload())->assertForbidden();
        $this->assertSame(0, BusinessContract::count());
    }

    public function test_customer_read_is_tenant_scoped_and_platform_admin_cannot_impersonate_customer(): void
    {
        $this->activate();
        $this->asUser(1);
        $this->get($this->url(false))->assertOk()->assertInertia(fn ($p) => $p->where('activation.can_activate', false)->where('activation.state', 'active'));
        foreach ([2, 3, 4] as $id) {
            $this->asUser($id);
            $this->get($this->url(false))->assertNotFound();
        }
    }

    public function test_guest_unverified_and_revoked_admin_are_denied(): void
    {
        DB::table('users')->where('id', 4)->update(['email_verified_at' => null]);
        $this->asUser(4);
        $this->postJson($this->url(), $this->payload())->assertForbidden();
        DB::table('users')->where('id', 4)->update(['email_verified_at' => now(), 'role' => 'subscriber']);
        $this->asUser(4);
        $this->postJson($this->url(), $this->payload())->assertForbidden();
        auth()->logout();
        $this->postJson($this->url(), $this->payload())->assertUnauthorized();
    }

    public function test_consent_csrf_hash_version_and_per_solution_evidence_are_required(): void
    {
        $payload = $this->payload();
        $this->postJson($this->url(), [...$payload, 'confirmed' => false])->assertUnprocessable();
        $this->postJson($this->url(), [...$payload, 'technical_evidence' => ['pos' => 'Verificado correctamente']])->assertUnprocessable();
        $this->postJson($this->url(), [...$payload, 'snapshot_hash' => str_repeat('a', 64)])->assertConflict();
        $this->postJson($this->url(), [...$payload, 'implementation_version' => 1])->assertConflict();
        $this->withHeader('X-CSRF-TOKEN', 'wrong')->postJson($this->url(), $payload)->assertStatus(419);
        $this->assertSame(0, BusinessContract::count());
    }

    public static function unavailable(): array
    {
        return array_map(fn ($value) => [$value], ['implementation', 'partial_payment', 'unconfirmed_payment', 'void_invoice', 'identity_disabled',
            'link_revoked', 'ready_missing', 'membership_revoked', 'company_inactive', 'subscriber_inactive', 'currency_changed', 'service_inactive', 'peer_missing']);
    }

    #[DataProvider('unavailable')]
    public function test_activation_fails_closed_when_prerequisite_changes(string $case): void
    {
        match ($case) {
            'implementation' => DB::table('business_implementation_orders')->update(['status' => 'delivered']),
            'partial_payment' => Payment::sole()->update(['amount' => '11209.99']),
            'unconfirmed_payment' => Payment::sole()->update(['paid_at' => null]),
            'void_invoice' => DB::table('invoices')->update(['status' => 'void']),
            'identity_disabled' => config()->set('business_identity.enabled', false),
            'link_revoked' => DB::table('business_identity_links')->where('service', 'pos')->update(['active' => false]),
            'ready_missing' => DB::table('business_identity_members')->update(['ready_at' => null]),
            'membership_revoked' => DB::table('subscriber_user')->where('user_id', 1)->update(['active' => false]),
            'company_inactive' => DB::table('companies')->where('id', 1)->update(['active' => false]),
            'subscriber_inactive' => DB::table('subscribers')->where('id', 1)->update(['active' => false]),
            'currency_changed' => DB::table('companies')->where('id', 1)->update(['currency' => 'USD']),
            'service_inactive' => DB::table('services')->where('service_key', 'pos')->update(['active' => false]),
            'peer_missing' => config()->set('business_identity.peers.pos.url', null),
        };
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame(0, BusinessContract::count());
        $this->assertSame(0, SubscriptionItem::count());
    }

    public function test_existing_items_and_multiple_subscriptions_require_transition(): void
    {
        $sub = Subscription::create(['subscriber_id' => 1, 'status' => 'active', 'currency' => 'DOP']);
        $item = SubscriptionItem::create(['subscription_id' => $sub->id, 'service_id' => Service::first()->id, 'status' => 'active']);
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame('active', $item->fresh()->status);
        $item->delete();
        Subscription::create(['subscriber_id' => 1, 'status' => 'trialing', 'currency' => 'DOP']);
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame(2, Subscription::count());
    }

    public function test_standalone_invoice_arriving_before_activation_requires_transition(): void
    {
        Invoice::create(['company_id' => 1, 'number' => 'SYNTHETIC-STANDALONE', 'currency' => 'DOP', 'status' => 'issued',
            'subtotal' => 100, 'total' => 100, 'billing_snapshot' => ['source' => 'standalone_service_checkout']]);
        $this->postJson($this->url(), $this->payload())->assertConflict();
        $this->assertSame(2, Invoice::count());
        $this->assertSame(0, BusinessContract::count());
    }

    public function test_existing_mutation_paths_cannot_reprice_or_cancel_business_services(): void
    {
        $this->activate();
        foreach ([fn () => SubscriptionItem::first()->update(['status' => 'cancelled']),
            fn () => SubscriptionItem::first()->delete(),
            fn () => app(CentralEntitlementActivationService::class)->ensureSubscription(Subscriber::findOrFail(1), Company::findOrFail(1), 'standalone_settlement', currency: 'DOP')] as $operation) {
            try {
                $operation();
                $this->fail('Business must require transition');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('business_contract', $error->errors());
            }
        }
        $this->assertSame(4, SubscriptionItem::where('status', 'active')->count());
    }

    public function test_recalculation_keeps_contract_price_after_catalog_changes(): void
    {
        $this->activate();
        DB::table('services')->update(['monthly_price' => 999999]);
        $sub = app(SubscriptionTotalsService::class)->recalculate(Subscription::sole());
        $this->assertSame('4130.00', $sub->total_amount);
        $this->assertSame('0.00', $sub->discount_amount);
        $this->assertSame(1, Invoice::count());
    }

    public function test_payment_reversal_and_forged_paid_status_stop_access_immediately(): void
    {
        $this->activate();
        $payment = Payment::sole();
        $payment->update(['paid_at' => null]);
        $this->assertFalse($this->canAccess());
        $this->assertCount(0, (new SubscriberEntitlements)->erpServicesForSubscriber(1));
        DB::table('invoices')->update(['status' => 'paid', 'amount_paid' => '11210.00']);
        $this->assertFalse($this->canAccess());
        $payment->update(['paid_at' => now()]);
        $this->assertTrue($this->canAccess());
        $this->assertSame('completed', BusinessImplementationOrder::sole()->status);
        $this->assertSame(1, BusinessContract::count());
    }

    public function test_exact_period_end_denies_access_without_scheduler_or_new_invoice(): void
    {
        $contract = $this->activate();
        Carbon::setTestNow($contract->period_ends_at);
        CarbonImmutable::setTestNow($contract->period_ends_at);
        $this->assertFalse($this->canAccess());
        $this->get($this->url())->assertOk()->assertInertia(fn ($p) => $p->where('activation.state', 'expired'));
        $this->assertSame(1, Invoice::count());
    }

    public function test_link_revocation_only_blocks_its_solution_and_disabled_flag_blocks_business(): void
    {
        $this->activate();
        DB::table('business_identity_links')->where('service', 'pos')->update(['active' => false]);
        $this->assertFalse($this->canAccess('pos'));
        $this->assertTrue($this->canAccess('ecf'));
        config()->set('business_commercial.activation_enabled', false);
        $this->assertFalse($this->canAccess('ecf'));
        $this->assertSame(4, SubscriptionItem::count());
    }

    public function test_audit_failure_rolls_back_subscription_items_and_contract(): void
    {
        AuditLog::creating(function ($log): void {
            if ($log->event === 'business.contract.activated') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->postJson($this->url(), $this->payload())->assertStatus(500);
        $this->assertSame(0, Subscription::count());
        $this->assertSame(0, SubscriptionItem::count());
        $this->assertSame(0, BusinessContract::count());
        $this->assertSame(0, DB::table('business_contract_items')->count());
    }

    public function test_flag_schema_and_rollback_preserve_history(): void
    {
        $migration = require base_path('database/migrations/2026_11_06_000007_create_business_contracts.php');
        $migration->down();
        $this->get($this->url())->assertStatus(503);
        $this->assertFalse(Schema::hasTable('business_contracts'));
        $migration->up();
        config()->set('business_commercial.activation_enabled', false);
        $this->get($this->url())->assertNotFound();
        config()->set('business_commercial.activation_enabled', true);
        $this->activate();
        foreach ([fn () => $migration->down(), fn () => BusinessContract::sole()->delete(), fn () => BusinessContract::sole()->update(['snapshot' => []])] as $operation) {
            try {
                $operation();
                $this->fail('History must be preserved');
            } catch (\LogicException|\RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
    }

    public static function periods(): array
    {
        return [['2027-01-31 10:00:00', 'monthly', '2027-02-28 10:00:00'],
            ['2028-01-31 10:00:00', 'monthly', '2028-02-29 10:00:00'],
            ['2028-02-29 10:00:00', 'yearly', '2029-02-28 10:00:00'],
            ['2026-03-01 10:00:00', 'monthly', '2026-04-01 10:00:00']];
    }

    #[DataProvider('periods')]
    public function test_period_calendars_preserve_local_time_and_handle_month_end(string $start, string $cycle, string $expected): void
    {
        $end = BusinessContractActivationService::periodEnd(CarbonImmutable::parse($start, 'America/New_York'), $cycle);
        $this->assertSame($expected, $end->format('Y-m-d H:i:s'));
    }

    public function test_yearly_contract_keeps_annual_discount_and_excludes_one_time_addons_from_recurrence(): void
    {
        $this->quote = BusinessQuoteFixtures::issue(companyId: 2);
        $selection = $this->quote->snapshot['selection'];
        $selection['cycle'] = 'yearly';
        $selection['addons'] = ['hours' => 2];
        $snapshot = app(BusinessQuoteCalculator::class)->calculate(
            ServiceCatalogVersion::first()->payload, $selection, CarbonImmutable::now(), 2);
        $snapshot['commercial_status'] = 'quoted';
        DB::table('service_commercial_quotes')->where('id', $this->quote->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->quote->refresh();
        BusinessContractFixtures::prepare($this->quote, 2);
        $contract = $this->activate();
        $sub = Subscription::findOrFail($contract->subscription_id);
        $this->assertSame('yearly', $sub->billing_cycle);
        $this->assertGreaterThan(0, $snapshot['annual_discount_cents']);
        $this->assertSame((float) $snapshot['taxes']['recurring']['line_total'], (float) $sub->total_amount);
        $this->assertSame('0.00', $sub->discount_amount);
        $this->assertSame(900000, $contract->snapshot['one_time_cents']);
        $this->assertSame($contract->activated_at->addYearNoOverflow()->timestamp, $contract->period_ends_at->timestamp);
    }

    public function test_zero_contract_has_no_fabricated_payment_and_can_grant_access(): void
    {
        $this->quote = BusinessQuoteFixtures::issue(companyId: 2);
        $snapshot = $this->quote->snapshot;
        foreach (['recurring', 'one_time'] as $kind) {
            $snapshot[$kind.'_cents'] = 0;
            $snapshot['taxes'][$kind] = ['taxable_base' => 0, 'tax_amount' => 0, 'line_total' => 0];
        }
        DB::table('service_commercial_quotes')->where('id', $this->quote->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->quote->refresh();
        BusinessContractFixtures::prepare($this->quote, 2);
        $contract = $this->activate();
        $this->assertSame('0.00', Subscription::findOrFail($contract->subscription_id)->total_amount);
        $this->assertSame(0, Payment::where('company_id', 2)->count());
        $this->assertTrue(app(ServiceAccessResolver::class)->userCanAccess(User::findOrFail(2), Company::findOrFail(2), Service::first()));
    }

    public function test_legacy_coverage_and_totals_are_preserved_when_business_is_disabled(): void
    {
        config()->set('business_commercial.activation_enabled', false);
        $sub = Subscription::create(['subscriber_id' => 2, 'status' => 'active', 'currency' => 'DOP', 'tax_amount' => 18]);
        SubscriptionItem::create(['subscription_id' => $sub->id, 'service_id' => Service::first()->id, 'status' => 'active', 'amount' => 100, 'currency' => 'DOP']);
        $this->assertTrue(app(ServiceAccessResolver::class)->userCanAccess(User::findOrFail(2), Company::findOrFail(2), Service::first()));
        $updated = app(SubscriptionTotalsService::class)->recalculate($sub);
        $this->assertSame('118.00', $updated->total_amount);
    }

    public function test_actual_standalone_checkout_after_business_activation_is_rejected_before_invoicing(): void
    {
        $this->activate();
        $service = Service::first();
        $service->update(['monthly_price' => 100, 'billing_model' => 'flat']);
        $requestId = DB::table('activation_requests')->insertGetId(['user_id' => 1, 'name' => 'QA', 'company' => 'QA', 'email' => 'qa@business.invalid', 'topic' => 'QA', 'status' => 'accepted']);
        $rowId = DB::table('activation_request_service')->insertGetId(['activation_request_id' => $requestId, 'service_id' => $service->id, 'status' => 'pending']);
        try {
            app(StandaloneServiceCheckoutService::class)->checkout(
                ActivationRequestService::findOrFail($rowId), Subscriber::findOrFail(1), Company::findOrFail(1), $service, 'monthly', 1);
            $this->fail('Standalone checkout must require transition');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('business_contract', $error->errors());
        }
        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, DB::table('standalone_service_settlements')->count());
    }
}
