<?php

namespace Tests\Feature\Billing;

use App\Models\AuditLog;
use App\Models\BusinessContract;
use App\Models\BusinessRenewalAcceptance;
use App\Models\BusinessRenewalOffer;
use App\Models\BusinessRenewalPeriod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCommercialQuote;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\Billing\BusinessContractActivationService;
use App\Services\Billing\BusinessContractPeriods;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\Billing\BusinessRenewalService;
use App\Services\Billing\BusinessRenewalTerms;
use App\Services\Billing\SubscriptionTotalsService;
use App\Services\LaudaErp\ServiceAccessResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessContractFixtures;
use Tests\Support\BusinessIdentityApplication;

final class BusinessRenewalTest extends TestCase
{
    private string $runtime;

    private ServiceCommercialQuote $quote;

    private BusinessContract $contract;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business renewal tests only');

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->at('2026-11-06 12:00:00 UTC');
        BusinessContractFixtures::schema();
        (require base_path('database/migrations/2026_11_06_000008_create_business_renewals.php'))->up();
        Mail::fake();
        Notification::fake();
        $this->quote = BusinessContractFixtures::seed();
        $this->contract = app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $this->quote->public_id, [
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($this->quote), 'idempotency_key' => (string) Str::uuid(),
            'implementation_version' => 4, 'technical_evidence' => array_fill_keys(array_keys($this->quote->snapshot['products']), 'Preparación técnica comprobada en QA sintético.'),
        ]);
        config()->set('business_commercial.renewal_enabled', true);
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

    private function at(string $at): void
    {
        Carbon::setTestNow($at);
        CarbonImmutable::setTestNow($at);
    }

    private function asUser(int $id): void
    {
        $this->actingAs(User::findOrFail($id))->withSession(['_token' => 'renewal-csrf'])->withHeader('X-CSRF-TOKEN', 'renewal-csrf');
    }

    private function url(string $action = '', bool $admin = true): string
    {
        return ($admin ? '/admin/business-implementations/' : '/app/business/quotes/').$this->quote->public_id.'/renewal'.($action ? '/'.$action : '');
    }

    private function proposal(array $changes = []): array
    {
        return array_replace(['confirmed' => true, 'idempotency_key' => (string) Str::uuid(), 'period_number' => 2,
            'expected_revision' => 0, 'valid_until' => $this->contract->period_ends_at->subDay()->toIso8601String(),
            'base_cents' => 350000, 'discount_cents' => 50000, 'tax_bps' => 1800,
            'commercial_note' => 'Condición ficticia de QA para un único período; descuento no prorrogable.', ...BusinessRenewalTerms::POLICIES], $changes);
    }

    private function propose(array $changes = []): BusinessRenewalOffer
    {
        $this->asUser(4);
        $this->postJson($this->url('propose'), $this->proposal($changes))->assertOk();

        return BusinessRenewalOffer::latest('id')->firstOrFail();
    }

    private function consent(BusinessRenewalOffer $offer): array
    {
        return ['confirmed' => true, 'idempotency_key' => (string) Str::uuid(), 'offer_id' => $offer->id, 'terms_hash' => $offer->terms_hash];
    }

    private function accept(?BusinessRenewalOffer $offer = null): BusinessRenewalOffer
    {
        $offer ??= $this->propose();
        $this->asUser(1);
        $this->postJson($this->url('accept', false), $this->consent($offer))->assertOk();

        return $offer;
    }

    private function issue(?BusinessRenewalOffer $offer = null): BusinessRenewalPeriod
    {
        $offer ??= $this->accept();
        $this->asUser(4);
        $this->postJson($this->url('invoice'), $this->consent($offer))->assertOk();

        return BusinessRenewalPeriod::latest('id')->firstOrFail();
    }

    private function pay(BusinessRenewalPeriod $period, ?string $amount = null): Payment
    {
        return Payment::create(['company_id' => 1, 'invoice_id' => $period->invoice_id, 'currency' => 'DOP',
            'method' => 'bank_transfer', 'amount' => $amount ?? $period->total, 'paid_at' => now()]);
    }

    private function access(): bool
    {
        return app(ServiceAccessResolver::class)->userCanAccess(User::findOrFail(1), Company::findOrFail(1), Service::where('service_key', 'pos')->sole());
    }

    public function test_get_is_private_and_existing_contract_has_no_implied_renewal_consent(): void
    {
        $before = AuditLog::count();
        $response = $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->component('Business/Renewal/Show')
            ->where('renewal.can_propose', true)->where('renewal.offers', [])->where('renewal.next.period_number', 2));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame($before, AuditLog::count());
        $this->assertSame(0, BusinessRenewalAcceptance::count());
        $this->assertSame(1, Invoice::count());
        $this->at($this->contract->period_ends_at->toIso8601String());
        $this->assertFalse($this->access());
    }

    public function test_publication_acceptance_and_billing_preserve_scope_and_create_one_recurring_line(): void
    {
        $initial = Invoice::sole()->getAttributes();
        $original = $this->contract->fresh()->getAttributes();
        $offer = $this->propose();
        $this->assertSame(1, Invoice::count());
        $this->assertSame(354000, $offer->terms['total_cents']);
        $this->accept($offer);
        $this->assertSame(1, Invoice::count());
        $period = $this->issue($offer);
        $invoice = Invoice::findOrFail($period->invoice_id);
        $this->assertSame('3000.00', $invoice->subtotal);
        $this->assertSame('0.00', $invoice->discount_total);
        $this->assertSame('540.00', $invoice->tax_total);
        $this->assertSame('3540.00', $invoice->total);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertNull($invoice->items()->sole()->service_id);
        $this->assertNull($invoice->fiscal_number);
        $this->assertTrue($invoice->fiscal_meta['fiscal_document_pending']);
        $this->assertSame($initial, Invoice::findOrFail($initial['id'])->getAttributes());
        $this->assertSame($original, $this->contract->fresh()->getAttributes());
        $this->assertSame(1, Subscription::count());
        $this->assertSame(4, SubscriptionItem::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(3, AuditLog::where('event', 'like', 'business.renewal.%')->count());
    }

    public static function invalidTerms(): array
    {
        return [[['confirmed' => false]], [['base_cents' => null]], [['discount_cents' => null]], [['tax_bps' => null]],
            [['discount_cents' => 350001]], [['base_cents' => -1]], [['base_cents' => 1.5]], [['tax_bps' => 10001]],
            [['renewal_mode' => 'automatic']], [['discount_duration' => 'forever']], [['price_changes' => 'catalog']],
            [['tax_changes' => 'automatic']], [['unpaid_coverage' => 'grace']], [['commercial_note' => str_repeat(' ', 25)]],
            [['valid_until' => '2026-11-05T12:00:00+00:00']], [['valid_until' => '2028-01-01T12:00:00+00:00']]];
    }

    #[DataProvider('invalidTerms')]
    public function test_complete_explicit_terms_are_required(array $changes): void
    {
        $this->postJson($this->url('propose'), $this->proposal($changes))->assertUnprocessable();
        $this->assertSame(0, BusinessRenewalOffer::count());
        $this->assertSame(1, Invoice::count());
    }

    public function test_missing_consent_hash_and_no_acceptance_prevent_billing(): void
    {
        $offer = $this->propose();
        $this->postJson($this->url('invoice'), $this->consent($offer))->assertConflict();
        $this->asUser(1);
        $this->postJson($this->url('accept', false), array_replace($this->consent($offer), ['confirmed' => false]))->assertUnprocessable();
        $this->postJson($this->url('accept', false), array_replace($this->consent($offer), ['terms_hash' => str_repeat('0', 64)]))->assertConflict();
        $this->assertSame(0, BusinessRenewalAcceptance::count());
    }

    public function test_revisions_supersede_unaccepted_offers_and_freeze_after_acceptance(): void
    {
        $first = $this->propose();
        $second = $this->propose(['expected_revision' => 1, 'discount_cents' => 0]);
        $this->asUser(1);
        $this->postJson($this->url('accept', false), $this->consent($first))->assertConflict();
        $this->accept($second);
        $this->asUser(4);
        $this->postJson($this->url('propose'), $this->proposal(['expected_revision' => 2]))->assertConflict();
        $this->assertSame(2, BusinessRenewalOffer::count());
        $this->assertSame(1, BusinessRenewalAcceptance::count());
    }

    public function test_lost_responses_recover_without_duplicates_and_reused_keys_cannot_change_conditions(): void
    {
        $data = $this->proposal();
        $this->postJson($this->url('propose'), $data)->assertOk();
        $this->postJson($this->url('propose'), $data)->assertOk();
        $this->postJson($this->url('propose'), array_replace($data, ['tax_bps' => 0]))->assertConflict();
        $offer = BusinessRenewalOffer::sole();
        $data = $this->consent($offer);
        $this->asUser(1);
        $this->postJson($this->url('accept', false), $data)->assertOk();
        $this->postJson($this->url('accept', false), $data)->assertOk();
        $this->postJson($this->url('accept', false), $this->consent($offer))->assertOk();
        $this->asUser(4);
        $this->postJson($this->url('invoice'), $data)->assertOk();
        $this->postJson($this->url('invoice'), $data)->assertOk();
        $this->postJson($this->url('invoice'), $this->consent($offer))->assertOk();
        $this->assertSame(1, BusinessRenewalOffer::count());
        $this->assertSame(1, BusinessRenewalAcceptance::count());
        $this->assertSame(1, BusinessRenewalPeriod::count());
        $this->assertSame(2, Invoice::count());
        $this->assertSame(3, AuditLog::where('event', 'like', 'business.renewal.%')->count());
    }

    public function test_expired_and_stale_proposals_are_rejected_but_accepted_terms_survive_offer_expiry(): void
    {
        $offer = $this->propose();
        $this->postJson($this->url('propose'), $this->proposal())->assertConflict();
        $this->at($offer->valid_until->toIso8601String());
        $this->asUser(1);
        $this->postJson($this->url('accept', false), $this->consent($offer))->assertConflict();
        $this->at($offer->valid_until->subSecond()->toIso8601String());
        $this->accept($offer);
        $this->at($offer->valid_until->toIso8601String());
        $this->issue($offer);
        $this->assertSame(2, Invoice::count());
    }

    public function test_future_unpaid_and_partial_invoices_do_not_extend_coverage_but_confirmed_payment_does(): void
    {
        $period = $this->issue();
        $this->assertTrue($this->access());
        $this->at($period->period_starts_at->toIso8601String());
        $this->assertFalse($this->access());
        $payment = $this->pay($period, '100.00');
        $this->assertFalse($this->access());
        $payment->update(['amount' => $period->total]);
        $this->assertTrue($this->access());
        $this->get('/admin/business-implementations/'.$this->quote->public_id.'/activation')->assertOk()
            ->assertInertia(fn ($p) => $p->where('activation.state', 'active')->where('activation.current_period.number', 2));
        $this->at($period->period_ends_at->toIso8601String());
        $this->assertFalse($this->access());
        $this->assertSame(2, Invoice::count());
    }

    public function test_payment_reversal_restoration_and_unconfirmed_forged_paid_status(): void
    {
        $period = $this->issue();
        $payment = $this->pay($period);
        $this->at($period->period_starts_at->toIso8601String());
        $this->assertTrue($this->access());
        $payment->update(['paid_at' => null]);
        DB::table('invoices')->where('id', $period->invoice_id)->update(['status' => 'paid', 'amount_paid' => $period->total]);
        $this->assertFalse($this->access());
        $payment->update(['paid_at' => now()]);
        $this->assertTrue($this->access());
        $this->asUser(4);
        $this->postJson($this->url('invoice'), $this->consent(BusinessRenewalOffer::sole()))->assertOk();
        $this->assertSame(2, Invoice::count());
    }

    public function test_zero_explicit_renews_without_creating_payment(): void
    {
        $offer = $this->propose(['base_cents' => 0, 'discount_cents' => 0, 'tax_bps' => 0]);
        $this->accept($offer);
        $period = $this->issue($offer);
        $this->at($period->period_starts_at->toIso8601String());
        $this->assertTrue($this->access());
        $this->assertSame(1, Payment::count());
    }

    public function test_pricing_uses_current_accepted_period_and_does_not_double_discount_or_read_catalog_prices(): void
    {
        $period = $this->issue();
        $subscription = app(SubscriptionTotalsService::class)->recalculate(Subscription::sole());
        $this->assertSame('4130.00', $subscription->total_amount);
        $this->pay($period);
        $this->at($period->period_starts_at->toIso8601String());
        DB::table('services')->update(['monthly_price' => 999999]);
        $subscription = app(SubscriptionTotalsService::class)->recalculate(Subscription::sole());
        $this->assertSame('3540.00', $subscription->total_amount);
        $this->assertSame('0.00', $subscription->discount_amount);
        $this->assertSame(2, $subscription->meta['business_contract']['period']['number']);
        $this->assertSame(2, BusinessContractPeriods::display($this->contract)['number']);
    }

    public function test_next_period_requires_new_conditions_and_cannot_be_stacked_ahead_of_time(): void
    {
        $period = $this->issue();
        $this->pay($period);
        $data = $this->proposal(['period_number' => 3, 'valid_until' => $period->period_ends_at->subDay()->toIso8601String(), 'discount_cents' => 0]);
        $this->postJson($this->url('propose'), $data)->assertConflict();
        $this->at($period->period_starts_at->toIso8601String());
        $this->postJson($this->url('propose'), $data)->assertOk();
        $offer = BusinessRenewalOffer::latest('id')->firstOrFail();
        $this->assertSame(0, $offer->terms['discount_cents']);
        $this->accept($offer);
        $second = $this->issue($offer);
        $this->pay($second);
        $this->at($period->period_ends_at->toIso8601String());
        $this->assertTrue($this->access());
        $this->assertSame(3, Invoice::count());
        $this->assertSame(1, Subscription::count());
    }

    public static function deniedUsers(): array
    {
        return [[2], [3], [4]];
    }

    #[DataProvider('deniedUsers')]
    public function test_customer_acceptance_is_tenant_scoped_and_platform_admin_cannot_impersonate_owner(int $id): void
    {
        $offer = $this->propose();
        $this->asUser($id);
        $this->get($this->url('', false))->assertNotFound();
        $this->postJson($this->url('accept', false), $this->consent($offer))->assertNotFound();
        $this->assertSame(0, BusinessRenewalAcceptance::count());
    }

    public function test_company_owner_cannot_publish_or_issue_and_revoked_membership_cannot_accept(): void
    {
        $offer = $this->propose();
        $this->asUser(1);
        $this->postJson($this->url('propose'), $this->proposal())->assertForbidden();
        $this->postJson($this->url('invoice'), $this->consent($offer))->assertForbidden();
        DB::table('subscriber_user')->where('user_id', 1)->update(['active' => false]);
        $this->postJson($this->url('accept', false), $this->consent($offer))->assertNotFound();
    }

    public function test_csrf_verified_email_and_flag_are_required(): void
    {
        $this->withHeader('X-CSRF-TOKEN', 'wrong')->postJson($this->url('propose'), $this->proposal())->assertStatus(419);
        $this->asUser(4);
        DB::table('users')->where('id', 4)->update(['email_verified_at' => null]);
        $this->asUser(4);
        $this->getJson($this->url())->assertForbidden();
        DB::table('users')->where('id', 4)->update(['email_verified_at' => now()]);
        $this->asUser(4);
        config()->set('business_commercial.renewal_enabled', false);
        $this->get($this->url())->assertNotFound();
        $this->assertSame(0, BusinessRenewalOffer::count());
    }

    public function test_missing_tables_return_unavailable_without_creating_schema(): void
    {
        Schema::drop('business_renewal_periods');
        $this->get($this->url())->assertStatus(503);
        $this->assertFalse(Schema::hasTable('business_renewal_periods'));
        $this->assertTrue($this->access());
    }

    public function test_disabled_renewal_denies_only_renewed_period_and_keeps_history(): void
    {
        $period = $this->issue();
        $this->pay($period);
        config()->set('business_commercial.renewal_enabled', false);
        $this->assertTrue($this->access());
        $this->at($period->period_starts_at->toIso8601String());
        $this->assertFalse($this->access());
        $this->assertSame(1, BusinessRenewalPeriod::count());
    }

    public static function corruptInvoice(): array
    {
        return [[['status' => 'void']], [['total' => '1.00']], [['subtotal' => '1.00']], [['tax_total' => '1.00']],
            [['discount_total' => '1.00']], [['currency' => 'USD']], [['company_id' => 2]], [['subscription_id' => null]],
            [['period_end' => '2030-01-01 00:00:00']], [['billing_snapshot' => '{}']]];
    }

    #[DataProvider('corruptInvoice')]
    public function test_modified_invoices_cannot_grant_coverage(array $changes): void
    {
        $period = $this->issue();
        $this->pay($period);
        $this->at($period->period_starts_at->toIso8601String());
        DB::table('invoices')->where('id', $period->invoice_id)->update($changes);
        $this->assertFalse($this->access());
    }

    public function test_original_payment_reversal_and_link_revocation_still_deny_coverage(): void
    {
        $period = $this->issue();
        $this->pay($period);
        $this->at($period->period_starts_at->toIso8601String());
        $original = Payment::orderBy('id')->firstOrFail();
        $original->update(['paid_at' => null]);
        $this->assertFalse($this->access());
        $original->update(['paid_at' => now()]);
        $this->assertTrue($this->access());
        DB::table('business_identity_links')->where('service', 'pos')->update(['active' => false]);
        $this->assertFalse($this->access());
    }

    public function test_money_and_conditions_tampering_blocks_acceptance(): void
    {
        $offer = $this->propose();
        $terms = $offer->terms;
        $terms['total_cents'] = 1;
        DB::table('business_renewal_offers')->where('id', $offer->id)->update(['terms' => json_encode($terms)]);
        $this->asUser(1);
        $this->postJson($this->url('accept', false), $this->consent($offer))->assertConflict();
        $this->assertSame(0, BusinessRenewalAcceptance::count());
    }

    public function test_json_object_key_order_does_not_change_integrity(): void
    {
        $offer = $this->propose();
        DB::table('business_renewal_offers')->where('id', $offer->id)->update(['terms' => json_encode(array_reverse($offer->terms, true))]);
        $offer = $this->accept($offer);
        $period = $this->issue($offer);
        $invoice = Invoice::findOrFail($period->invoice_id);
        DB::table('invoices')->where('id', $invoice->id)->update(['billing_snapshot' => json_encode(array_reverse($invoice->billing_snapshot, true))]);
        $this->assertSame(354000, app(BusinessRenewalService::class)->invoiceDetails($this->contract, $period)['total_cents']);
    }

    public function test_audit_failure_rolls_back_invoice_and_period(): void
    {
        $offer = $this->accept();
        $this->asUser(4);
        AuditLog::creating(function ($log): void {
            if ($log->event === 'business.renewal.invoiced') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        try {
            $this->postJson($this->url('invoice'), $this->consent($offer))->assertStatus(500);
            $this->assertSame(0, BusinessRenewalPeriod::count());
            $this->assertSame(1, Invoice::count());
            $this->assertSame(2, DB::table('invoice_items')->count());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_models_and_migration_preserve_renewal_history(): void
    {
        $period = $this->issue();
        foreach ([BusinessRenewalOffer::sole(), BusinessRenewalAcceptance::sole(), $period] as $model) {
            try {
                $model->delete();
                $this->fail('History deleted');
            } catch (\LogicException) {
                $this->assertTrue($model->fresh() !== null);
            }
            try {
                $model->forceFill(['terms_hash' => str_repeat('a', 64)])->save();
                $this->fail('History changed');
            } catch (\LogicException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(\RuntimeException::class);
        (require base_path('database/migrations/2026_11_06_000008_create_business_renewals.php'))->down();
    }

    public function test_calendar_is_anchored_without_month_end_or_leap_year_drift(): void
    {
        $contract = new BusinessContract(['activated_at' => '2028-01-31 15:00:00', 'timezone' => 'America/New_York', 'snapshot' => ['billing_cycle' => 'monthly']]);
        $this->assertSame('2028-02-29 15:00:00', BusinessRenewalTerms::boundary($contract, 1)->format('Y-m-d H:i:s'));
        $this->assertSame('2028-03-31 14:00:00', BusinessRenewalTerms::boundary($contract, 2)->format('Y-m-d H:i:s'));
        $contract = new BusinessContract(['activated_at' => '2028-02-29 15:00:00', 'timezone' => 'UTC', 'snapshot' => ['billing_cycle' => 'yearly']]);
        $this->assertSame('2029-02-28', BusinessRenewalTerms::boundary($contract, 1)->toDateString());
        $this->assertSame('2032-02-29', BusinessRenewalTerms::boundary($contract, 4)->toDateString());
    }

    public function test_acceptance_and_publication_audit_failures_roll_back_their_evidence(): void
    {
        AuditLog::creating(function ($log): void {
            if ($log->event === 'business.renewal.offered') {
                throw new \RuntimeException('Synthetic offer audit failure');
            }
        });
        try {
            $this->postJson($this->url('propose'), $this->proposal())->assertStatus(500);
            $this->assertSame(0, BusinessRenewalOffer::count());
        } finally {
            AuditLog::flushEventListeners();
        }
        $offer = $this->propose();
        AuditLog::creating(function ($log): void {
            if ($log->event === 'business.renewal.accepted') {
                throw new \RuntimeException('Synthetic consent audit failure');
            }
        });
        try {
            $this->asUser(1);
            $this->postJson($this->url('accept', false), $this->consent($offer))->assertStatus(500);
            $this->assertSame(0, BusinessRenewalAcceptance::count());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_line_tampering_and_wrong_payment_tenant_deny_access(): void
    {
        $period = $this->issue();
        $payment = $this->pay($period);
        $this->at($period->period_starts_at->toIso8601String());
        DB::table('invoice_items')->where('invoice_id', $period->invoice_id)->update(['quantity' => 2]);
        $this->assertFalse($this->access());
        $this->get($this->url())->assertConflict();
        DB::table('invoice_items')->where('invoice_id', $period->invoice_id)->update(['quantity' => 1]);
        $this->assertTrue($this->access());
        DB::table('payments')->where('id', $payment->id)->update(['company_id' => 2]);
        $this->assertFalse($this->access());
    }

    public function test_unpaid_prior_period_and_ended_target_cannot_be_renewed_again(): void
    {
        $period = $this->issue();
        $this->at($period->period_starts_at->toIso8601String());
        $this->postJson($this->url('propose'), $this->proposal(['period_number' => 3,
            'valid_until' => $period->period_ends_at->subDay()->toIso8601String()]))->assertConflict();
        $this->pay($period);
        $offer = $this->propose(['period_number' => 3, 'valid_until' => $period->period_ends_at->subDay()->toIso8601String()]);
        $this->accept($offer);
        $this->at($offer->period_ends_at->toIso8601String());
        $this->asUser(4);
        $this->postJson($this->url('invoice'), $this->consent($offer))->assertConflict();
        $this->assertSame(2, Invoice::count());
    }

    public function test_different_requests_cannot_publish_the_same_revision_or_duplicate_a_period_in_database(): void
    {
        $period = $this->issue();
        $attributes = $period->getAttributes();
        unset($attributes['id']);
        $attributes['idempotency_key'] = (string) Str::uuid();
        try {
            DB::table('business_renewal_periods')->insert($attributes);
            $this->fail('Duplicate period was inserted.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('UNIQUE', $error->getMessage());
        }
        $this->assertSame(1, BusinessRenewalPeriod::count());
    }

    public function test_renewal_blocks_existing_subscription_conflicts_and_inactive_solutions(): void
    {
        DB::table('services')->where('service_key', 'pos')->update(['active' => false]);
        $this->postJson($this->url('propose'), $this->proposal())->assertConflict();
        DB::table('services')->where('service_key', 'pos')->update(['active' => true]);
        Subscription::create(['subscriber_id' => 1, 'status' => 'active', 'currency' => 'DOP', 'billing_cycle' => 'monthly']);
        $this->postJson($this->url('propose'), $this->proposal())->assertConflict();
        $this->assertSame(0, BusinessRenewalOffer::count());
    }

    public function test_read_projection_displays_renewed_dates_and_price_without_writing_subscription(): void
    {
        $period = $this->issue();
        $subscription = Subscription::sole();
        $before = $subscription->getAttributes();
        $this->assertSame('4130.00', BusinessContractPeriods::forSubscription($subscription)['total']);
        $this->at($period->period_starts_at->toIso8601String());
        $display = BusinessContractPeriods::forSubscription($subscription);
        $this->assertSame('3540.00', $display['total']);
        $this->assertSame($period->period_ends_at->setTimezone($this->contract->timezone)->toDateString(), $display['end']);
        $this->assertSame($before, $subscription->fresh()->getAttributes());
        $this->assertNull(BusinessContractPeriods::forSubscription(null));
        $this->asUser(4);
        $this->get('/admin/subscriptions')->assertOk()->assertInertia(fn ($page) => $page
            ->where('subscriptions.data.0.total_amount', 3540)->where('subscriptions.data.0.current_period_end', $period->period_ends_at->toIso8601String()));
    }

    public function test_tax_rate_precision_and_yearly_single_period_amounts_are_explicit(): void
    {
        $this->postJson($this->url('propose'), $this->proposal(['tax_bps' => 1801]))->assertUnprocessable();
        $contract = clone $this->contract;
        $contract->snapshot = array_replace($contract->snapshot, ['billing_cycle' => 'yearly']);
        $terms = app(BusinessRenewalTerms::class)->make($contract, 2, $this->proposal(['base_cents' => 4200000, 'discount_cents' => 0]));
        $this->assertSame(0, $terms['one_time_cents']);
        $this->assertSame(4956000, $terms['total_cents']);
        $this->assertSame('2027-11-06T12:00:00+00:00', $terms['period_starts_at']);
        $this->assertSame('2028-11-06T12:00:00+00:00', $terms['period_ends_at']);
    }
}
