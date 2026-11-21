<?php

namespace Tests\Feature\Billing;

use App\Models\ActivationRequestService;
use App\Models\AuditLog;
use App\Models\BusinessContract;
use App\Models\BusinessTransition;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\StandaloneServiceSettlement;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\Billing\BusinessCatalogService;
use App\Services\Billing\BusinessContractActivationService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use App\Services\Billing\BusinessQuoteCheckoutService;
use App\Services\Billing\BusinessTransitionService;
use App\Services\Billing\StandaloneServiceCheckoutService;
use App\Services\Billing\StandaloneServiceSettlementService;
use App\Services\Billing\SubscriptionTotalsService;
use App\Services\BusinessCommercial\CommercialToken;
use App\Services\LaudaErp\ServiceAccessResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessContractFixtures;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessQuoteFixtures;
use Tests\Support\BusinessTransitionFixtures;

final class BusinessTransitionTest extends TestCase
{
    private string $runtime;

    private array $sources;

    private ServiceCommercialQuote $original;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business transition tests only');

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->at('2026-11-07 12:00:00 UTC');
        BusinessContractFixtures::schema();
        BusinessTransitionFixtures::schema();
        BusinessContractFixtures::configure();
        Mail::fake();
        Notification::fake();
        $this->original = BusinessQuoteFixtures::seed();
        $this->sources = BusinessTransitionFixtures::sources();
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
        $this->actingAs(User::findOrFail($id))->withSession(['_token' => 'transition-csrf'])->withHeader('X-CSRF-TOKEN', 'transition-csrf');
    }

    private function issuePayload(): array
    {
        return ['company_id' => 1, 'selection' => BusinessTransitionFixtures::selection(), 'lock_version' => ServiceCatalogVersion::firstOrFail()->lock_version,
            'idempotency_key' => (string) Str::uuid(), 'transition' => BusinessTransitionFixtures::input()];
    }

    private function issueUrl(): string
    {
        return '/admin/business-catalog/'.ServiceCatalogVersion::firstOrFail()->id.'/issue';
    }

    private function activationPayload(ServiceCommercialQuote $quote): array
    {
        return ['confirmed' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote), 'idempotency_key' => (string) Str::uuid(),
            'implementation_version' => 4, 'technical_evidence' => array_fill_keys(array_keys($quote->snapshot['products']), 'Evidencia técnica sintética verificada para transición.')];
    }

    private function commercialExchange(string $service, string $action, array $data = [], int $status = 200): array
    {
        $link = DB::table('business_identity_links')->where('service', $service)->sole();
        $codec = new CommercialToken(config('business_identity.peers.'.$service.'.secret'));
        $token = $codec->encode($service, config('business_identity.issuer'), 'request', [
            'action' => $action, 'link_id' => $link->id, 'local_company_id' => (int) $link->local_company_id,
            'central_company_id' => 1, 'central_subscriber_id' => 1, ...$data,
        ], now()->timestamp);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/business/commercial/v1/'.$service)
            ->assertStatus($status)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $reply = $codec->decode($response->json('token'), config('business_identity.issuer'), $service, 'response', now()->timestamp);
        $this->assertSame(hash('sha256', $token), $reply['request_hash']);
        $this->assertSame($status, $reply['status']);

        return $reply['result'] ?? $reply;
    }

    public function test_preview_recognizes_paid_periods_without_writing_and_does_not_choose_a_target(): void
    {
        $counts = [Subscription::count(), SubscriptionItem::count(), Invoice::count(), Payment::count(), ServiceCommercialQuote::count()];
        $response = $this->postJson(str_replace('/issue', '/preview', $this->issueUrl()), ['company_id' => 1, 'selection' => BusinessTransitionFixtures::selection()]);
        $response->assertOk()->assertJsonPath('transition_requires_review', true)->assertJsonPath('transition_review.eligible', true)
            ->assertJsonPath('transition_review.not_before', '2026-11-13T12:00:00+00:00')->assertJsonPath('transition_review.credit_cents', 0);
        $this->assertCount(2, $response->json('transition_review.periods'));
        $this->assertArrayNotHasKey('target_subscription_id', $response->json('transition_review'));
        $this->assertSame($counts, [Subscription::count(), SubscriptionItem::count(), Invoice::count(), Payment::count(), ServiceCommercialQuote::count()]);
    }

    public function test_issuance_requires_confirmation_and_current_source_hash_and_is_idempotent(): void
    {
        $data = $this->issuePayload();
        $bad = $data;
        $bad['transition']['confirmed'] = false;
        $this->postJson($this->issueUrl(), $bad)->assertUnprocessable();
        $bad = $data;
        $bad['transition']['source_hash'] = str_repeat('a', 64);
        $this->postJson($this->issueUrl(), $bad)->assertConflict();
        $response = $this->postJson($this->issueUrl(), $data)->assertOk();
        $this->postJson($this->issueUrl(), $data)->assertOk()->assertJsonPath('public_id', $response->json('public_id'));
        $bad = $data;
        $bad['transition']['target_subscription_id'] = $this->sources[1]['subscription']->id;
        $this->postJson($this->issueUrl(), $bad)->assertUnprocessable();
        $this->assertSame(2, ServiceCommercialQuote::count());
        $this->assertSame(0, BusinessTransition::count());
    }

    public function test_full_transition_preserves_paid_history_reuses_one_subscription_and_cannot_overlap(): void
    {
        $quote = BusinessTransitionFixtures::issue();
        $history = Invoice::orderBy('id')->get()->map->getAttributes()->all();
        BusinessContractFixtures::prepare($quote);
        $payload = $this->activationPayload($quote);
        $url = '/admin/business-implementations/'.$quote->public_id.'/activation';
        $this->postJson($url, $payload)->assertConflict();
        $this->assertSame(2, SubscriptionItem::where('status', 'active')->count());
        $this->at('2026-11-13 11:59:59 UTC');
        $this->postJson($url, $payload)->assertConflict();
        $this->at('2026-11-13 12:00:00 UTC');
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, [...$payload, 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $contract = BusinessContract::sole();
        $this->assertSame($this->sources[0]['subscription']->id, $contract->subscription_id);
        $this->assertSame('2026-11-13 12:00:00', $contract->activated_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, Subscription::count());
        $this->assertSame(1, Subscription::where('status', 'active')->count());
        $this->assertSame('expired', $this->sources[1]['subscription']->fresh()->status);
        $this->assertSame(4, SubscriptionItem::where('status', 'active')->count());
        $this->assertSame(5, SubscriptionItem::count());
        $this->assertSame('cancelled', $this->sources[1]['item']->fresh()->status);
        $this->assertSame(2, DB::table('business_transition_items')->count());
        $this->assertSame(1, BusinessTransition::count());
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'business.transition.executed')->count());
        $this->assertSame($history, Invoice::whereIn('id', array_column($history, 'id'))->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(3, Invoice::count());
        $this->assertSame('4130.00', app(SubscriptionTotalsService::class)->recalculate(Subscription::findOrFail($contract->subscription_id))->total_amount);
        foreach (Service::all() as $service) {
            $this->assertTrue(app(ServiceAccessResolver::class)->userCanAccess(User::findOrFail(1), Company::findOrFail(1), $service));
        }
    }

    public function test_commercial_admission_starts_after_transition_and_preserves_standalone_billing_history(): void
    {
        (require base_path('database/migrations/2026_11_07_000010_create_business_document_usage.php'))->up();
        config()->set('business_commercial_bridge.enabled', true);
        $history = Invoice::orderBy('id')->get()->map->getAttributes()->all();
        $payments = Payment::orderBy('id')->get()->map->getAttributes()->all();
        $quote = BusinessTransitionFixtures::issue();
        BusinessContractFixtures::prepare($quote);
        $operation = ['operation_key' => (string) Str::uuid(), 'document' => ['source_reference' => 'sale:after-transition']];
        $this->assertSame(['managed' => false, 'access' => false], $this->commercialExchange('pos', 'snapshot'));
        $this->commercialExchange('pos', 'reserve', $operation, 409);
        $this->assertSame(0, DB::table('business_usage_documents')->count());

        $payload = $this->activationPayload($quote);
        $url = '/admin/business-implementations/'.$quote->public_id.'/activation';
        $this->postJson($url, $payload)->assertConflict();
        $this->assertSame(['managed' => false, 'access' => false], $this->commercialExchange('pos', 'snapshot'));
        $this->at('2026-11-13 12:00:00 UTC');
        $this->postJson($url, $payload)->assertOk();
        $contract = BusinessContract::sole();
        $this->assertSame(4, DB::table('business_contract_items')->where('business_contract_id', $contract->id)->count());
        $this->assertFalse(DB::table('business_contract_items')->where('subscription_item_id', $this->sources[1]['item']->id)->exists());
        foreach (['pos', 'ecf', 'bys', 'cumplimiento'] as $service) {
            $snapshot = $this->commercialExchange($service, 'snapshot');
            $this->assertTrue($snapshot['managed']);
            $this->assertTrue($snapshot['access']);
            $this->assertSame($contract->id, $snapshot['contract_id']);
            $this->assertSame(500, $snapshot['documents']['remaining']);
        }
        $receipt = $this->commercialExchange('pos', 'reserve', $operation);
        $this->assertSame($contract->activated_at->toIso8601String(), $receipt['period_starts_at']);
        $this->commercialExchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        foreach (['pos', 'ecf', 'bys', 'cumplimiento'] as $service) {
            $this->assertSame(499, $this->commercialExchange($service, 'snapshot')['documents']['remaining']);
        }
        $this->assertSame(1, DB::table('business_usage_documents')->count());
        $this->assertSame($history, Invoice::whereIn('id', array_column($history, 'id'))->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($payments, Payment::whereIn('id', array_column($payments, 'id'))->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(3, Invoice::count());
        $this->assertSame(3, Payment::count());
    }

    public static function changes(): array
    {
        return array_map(fn ($key) => [$key], ['payment', 'period', 'item', 'new_invoice', 'provider', 'currency', 'claim', 'inactive', 'missing_period', 'line', 'overpaid']);
    }

    private function mutate(string $case): void
    {
        $sub = $this->sources[0]['subscription'];
        $item = $this->sources[0]['item'];
        $invoice = $this->sources[0]['invoice'];
        match ($case) {
            'payment' => DB::table('payments')->where('invoice_id', $invoice->id)->update(['amount' => '1179.00']),
            'period' => DB::table('invoices')->where('id', $invoice->id)->update(['period_end' => now()->addYear()]),
            'item' => $item->update(['amount' => '999.00']),
            'new_invoice' => Invoice::create(['company_id' => 1, 'subscription_id' => $sub->id, 'number' => 'NEW-PENDING', 'status' => 'issued', 'currency' => 'DOP', 'total' => '10.00']),
            'provider' => $sub->update(['provider' => 'external']),
            'currency' => $sub->update(['currency' => 'USD']),
            'claim' => $item->update(['meta' => ['entitlement_claims' => ['transformation:1' => ['source' => 'transformation_360']]]]),
            'inactive' => $item->update(['status' => 'trialing']),
            'missing_period' => $invoice->update(['period_end' => null]),
            'line' => DB::table('invoice_items')->where('invoice_id', $invoice->id)->update(['line_total' => '1.00']),
            'overpaid' => Payment::create(['company_id' => 1, 'invoice_id' => $invoice->id, 'currency' => 'DOP', 'method' => 'bank_transfer', 'amount' => '10.00', 'paid_at' => now()]),
        };
    }

    #[DataProvider('changes')]
    public function test_changed_evidence_blocks_acceptance(string $case): void
    {
        $quote = BusinessTransitionFixtures::issue();
        $this->mutate($case);
        $this->asUser(1);
        $this->postJson('/app/business/quotes/'.$quote->public_id.'/accept', ['accepted' => true,
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote), 'idempotency_key' => (string) Str::uuid()])->assertConflict();
        $this->assertSame(0, DB::table('service_quote_acceptances')->count());
    }

    public function test_changes_after_acceptance_block_checkout(): void
    {
        $quote = BusinessTransitionFixtures::issue();
        $hash = BusinessQuoteAcceptanceService::fingerprint($quote);
        app(BusinessQuoteAcceptanceService::class)->accept(User::findOrFail(1), $quote->public_id, $hash, (string) Str::uuid());
        $this->mutate('period');
        $this->asUser(1);
        $this->postJson('/app/business/quotes/'.$quote->public_id.'/checkout', ['confirmed' => true, 'snapshot_hash' => $hash, 'idempotency_key' => (string) Str::uuid()])->assertConflict();
        $this->assertSame(2, Invoice::count());
    }

    public function test_changes_after_payment_block_activation_atomically(): void
    {
        $quote = BusinessTransitionFixtures::issue();
        BusinessContractFixtures::prepare($quote);
        $this->mutate('period');
        $this->at('2026-11-14 12:00:00 UTC');
        $this->postJson('/admin/business-implementations/'.$quote->public_id.'/activation', $this->activationPayload($quote))->assertConflict();
        $this->assertSame(2, Subscription::where('status', 'active')->count());
        $this->assertSame(2, SubscriptionItem::where('status', 'active')->count());
        $this->assertSame(0, BusinessTransition::count());
    }

    public function test_original_non_transition_quote_still_cannot_bypass_existing_coverage(): void
    {
        $this->asUser(1);
        $this->postJson('/app/business/quotes/'.$this->original->public_id.'/accept', ['accepted' => true,
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($this->original), 'idempotency_key' => (string) Str::uuid()])->assertConflict();
    }

    public function test_disabled_transition_flag_blocks_previously_proposed_transition(): void
    {
        $quote = BusinessTransitionFixtures::issue();
        config()->set('business_commercial.transition_enabled', false);
        $this->assertNotNull(app(BusinessTransitionService::class)->reason($quote));
        $this->postJson($this->issueUrl(), ['company_id' => 1, 'selection' => BusinessTransitionFixtures::selection(),
            'lock_version' => ServiceCatalogVersion::firstOrFail()->lock_version, 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
    }

    public function test_retired_sources_cannot_be_reopened_and_history_cannot_be_deleted(): void
    {
        $quote = BusinessTransitionFixtures::issue();
        BusinessContractFixtures::prepare($quote);
        $this->at('2026-11-14 12:00:00 UTC');
        app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $quote->public_id, $this->activationPayload($quote));
        $source = $this->sources[1];
        foreach ([fn () => $source['item']->fresh()->update(['status' => 'active']), fn () => $source['item']->fresh()->delete(),
            fn () => $source['subscription']->fresh()->update(['status' => 'active']), fn () => $source['subscription']->fresh()->delete(),
            fn () => SubscriptionItem::create(['subscription_id' => $source['subscription']->id, 'service_id' => Service::where('service_key', 'bys')->value('id')])] as $mutation) {
            try {
                $mutation();
                $this->fail('Superseded source mutated');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        foreach ([fn () => BusinessTransition::sole()->delete(), fn () => BusinessTransition::sole()->update(['source_hash' => str_repeat('a', 64)])] as $mutation) {
            try {
                $mutation();
                $this->fail('Transition history mutated');
            } catch (\LogicException) {
                $this->assertTrue(true);
            }
        }
        $migration = require base_path('database/migrations/2026_11_06_000009_create_business_transitions.php');
        try {
            $migration->down();
            $this->fail('Used migration rolled back');
        } catch (\RuntimeException) {
            $this->assertSame(1, BusinessTransition::count());
        }
        $this->assertSame('2026-11-14 12:00:00', BusinessContract::sole()->activated_at->format('Y-m-d H:i:s'));
    }

    public function test_audit_failure_rolls_back_entire_consolidation(): void
    {
        $quote = BusinessTransitionFixtures::issue();
        BusinessContractFixtures::prepare($quote);
        $this->at('2026-11-14 12:00:00 UTC');
        AuditLog::creating(function ($log): void {
            if ($log->event === 'business.transition.executed') {
                throw new \RuntimeException('Synthetic transition audit failure');
            }
        });
        try {
            $this->postJson('/admin/business-implementations/'.$quote->public_id.'/activation', $this->activationPayload($quote))->assertStatus(500);
            $this->assertSame(0, BusinessTransition::count());
            $this->assertSame(0, BusinessContract::count());
            $this->assertSame(0, DB::table('business_transition_items')->count());
            $this->assertSame(2, Subscription::where('status', 'active')->count());
            $this->assertSame(2, SubscriptionItem::where('status', 'active')->count());
            $this->assertSame('1000.00', $this->sources[0]['item']->fresh()->amount);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public static function unauthorized(): array
    {
        return [[1], [2], [3]];
    }

    #[DataProvider('unauthorized')]
    public function test_only_platform_admin_can_issue_transition(int $id): void
    {
        $data = $this->issuePayload();
        $this->asUser($id);
        $this->postJson($this->issueUrl(), $data)->assertForbidden();
        $this->assertSame(1, ServiceCommercialQuote::count());
    }

    public function test_csrf_and_foreign_target_are_rejected(): void
    {
        $data = $this->issuePayload();
        $this->withHeader('X-CSRF-TOKEN', 'wrong')->postJson($this->issueUrl(), $data)->assertStatus(419);
        $this->asUser(4);
        $data['transition']['target_subscription_id'] = 9999;
        $this->postJson($this->issueUrl(), $data)->assertConflict();
    }

    public function test_get_views_do_not_write_and_other_tenants_cannot_read_proposal(): void
    {
        $quote = BusinessTransitionFixtures::issue();
        $this->asUser(2);
        $this->get('/app/business/quotes/'.$quote->public_id)->assertNotFound();
        $this->asUser(1);
        $this->get('/app/business/quotes/'.$quote->public_id)->assertOk();
        $this->assertSame(0, DB::table('service_quote_acceptances')->count());
        $this->assertSame(2, Invoice::count());
        $this->assertSame(0, BusinessTransition::count());
    }

    public function test_a_second_accepted_proposal_cannot_create_an_overlapping_invoice(): void
    {
        $first = BusinessTransitionFixtures::issue();
        $second = BusinessTransitionFixtures::issue();
        $owner = User::findOrFail(1);
        foreach ([$first, $second] as $quote) {
            app(BusinessQuoteAcceptanceService::class)->accept($owner, $quote->public_id, BusinessQuoteAcceptanceService::fingerprint($quote), (string) Str::uuid());
        }
        app(BusinessQuoteCheckoutService::class)->checkout($owner, $first->public_id, BusinessQuoteAcceptanceService::fingerprint($first), (string) Str::uuid());
        $this->asUser(1);
        $this->postJson('/app/business/quotes/'.$second->public_id.'/checkout', ['confirmed' => true,
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($second), 'idempotency_key' => (string) Str::uuid()])->assertConflict();
        $this->assertSame(3, Invoice::count());
    }

    public static function standaloneCalendars(): array
    {
        return [['2027-01-31 12:00:00 UTC', 'monthly', '2027-03-03T12:00:00+00:00'], ['2028-02-29 12:00:00 UTC', 'yearly', '2029-03-01T12:00:00+00:00']];
    }

    private function standalone(string $paidAt, string $cycle): StandaloneServiceSettlement
    {
        $source = $this->sources[0];
        $invoice = $source['invoice'];
        $item = $source['item'];
        $request = DB::table('activation_requests')->insertGetId(['user_id' => 1, 'name' => 'Ana sintética', 'company' => 'QA', 'email' => 'qa@business.invalid', 'topic' => 'QA']);
        $row = DB::table('activation_request_service')->insertGetId(['activation_request_id' => $request, 'service_id' => $item->service_id, 'status' => 'active']);
        $invoice->update(['subscription_id' => null, 'period_start' => null, 'period_end' => null, 'billing_snapshot' => [
            'source' => 'standalone_service_checkout', 'company_id' => 1, 'subscriber_id' => 1, 'service_id' => $item->service_id,
            'activation_request_id' => $request, 'activation_request_service_id' => $row, 'billing_cycle' => $cycle]]);
        DB::table('payments')->where('invoice_id', $invoice->id)->update(['paid_at' => CarbonImmutable::parse($paidAt)]);
        $settlement = StandaloneServiceSettlement::create(['activation_request_service_id' => $row, 'activation_request_id' => $request,
            'company_id' => 1, 'subscriber_id' => 1, 'service_id' => $item->service_id, 'invoice_id' => $invoice->id,
            'invoice_item_id' => $invoice->items()->sole()->id, 'subscription_id' => $source['subscription']->id, 'subscription_item_id' => $item->id,
            'status' => 'activated', 'billing_cycle' => $cycle, 'currency' => 'DOP', 'amount_due' => '1180.00', 'amount_paid' => '1180.00',
            'settled_at' => CarbonImmutable::parse($paidAt), 'activated_at' => now(), 'evidence_snapshot' => ['settlement' => ['fully_paid_at' => CarbonImmutable::parse($paidAt)->toIso8601String()]]]);
        $item->update(['meta' => ['source' => 'standalone_settlement', 'entitlement_claims' => ['standalone_settlement:'.$settlement->id => ['source' => 'standalone_settlement']]]]);
        $start = CarbonImmutable::parse($paidAt);
        $end = $cycle === 'yearly' ? $start->addYearWithOverflow() : $start->addMonthWithOverflow();
        $source['subscription']->update(['current_period_start' => $start, 'current_period_end' => $end]);

        return $settlement;
    }

    #[DataProvider('standaloneCalendars')]
    public function test_standalone_preserves_original_month_and_leap_year_calendar(string $paidAt, string $cycle, string $expected): void
    {
        $this->at($paidAt);
        $this->standalone($paidAt, $cycle);
        $inventory = app(BusinessTransitionService::class)->inventory(Company::findOrFail(1), $this->original->snapshot['products']);
        $period = collect($inventory['periods'])->firstWhere('basis', 'standalone_calendar');
        $this->assertSame($expected, $period['ends_at']);
        $this->assertSame($expected, $inventory['not_before']);
    }

    public function test_transferred_settlement_replays_and_payment_reversals_cannot_touch_business(): void
    {
        $settlement = $this->standalone('2026-10-18 12:00:00 UTC', 'monthly');
        $quote = BusinessTransitionFixtures::issue();
        BusinessContractFixtures::prepare($quote);
        $this->at('2026-11-18 12:00:00 UTC');
        app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $quote->public_id, $this->activationPayload($quote));
        $this->assertSame('superseded', $settlement->fresh()->status);
        $payment = Payment::where('invoice_id', $settlement->invoice_id)->sole();
        $payment->update(['amount' => '1.00']);
        $service = app(StandaloneServiceSettlementService::class);
        $this->assertSame('superseded', $service->revoke($settlement, 4)->status);
        $payment->update(['amount' => '1180.00']);
        $this->assertSame('superseded', $service->settle($settlement->fresh(), 4)->status);
        $this->assertSame(4, SubscriptionItem::where('status', 'active')->count());
        $this->assertSame('0.00', $this->sources[0]['item']->fresh()->amount);
        $this->assertSame('paid', Invoice::findOrFail($settlement->invoice_id)->status);
    }

    public function test_native_standalone_checkout_and_settlement_are_recognized_and_transferred(): void
    {
        (require base_path('database/migrations/2026_08_27_211500_create_solution_owned_service_plans_foundation.php'))->up();
        $service = Service::where('service_key', 'bys')->sole();
        $service->update(['monthly_price' => '1000.00', 'yearly_price' => '10000.00']);
        $request = DB::table('activation_requests')->insertGetId(['user_id' => 1, 'name' => 'Ana QA', 'company' => 'QA', 'email' => 'qa@business.invalid', 'topic' => 'QA']);
        $rowId = DB::table('activation_request_service')->insertGetId(['activation_request_id' => $request, 'service_id' => $service->id, 'status' => 'pending_payment']);
        $row = ActivationRequestService::findOrFail($rowId);
        $settlement = app(StandaloneServiceCheckoutService::class)->checkout($row, Subscriber::findOrFail(1), Company::findOrFail(1), $service, 'monthly', 4);
        Payment::create(['company_id' => 1, 'invoice_id' => $settlement->invoice_id, 'currency' => 'DOP', 'method' => 'bank_transfer', 'amount' => $settlement->amount_due, 'paid_at' => now()]);
        $settlement = app(StandaloneServiceSettlementService::class)->settle($settlement->fresh(), 4);
        $this->assertSame('activated', $settlement->status);
        $quote = BusinessTransitionFixtures::issue();
        $period = collect($quote->snapshot['transition']['periods'])->firstWhere('basis', 'standalone_calendar');
        $this->assertSame('2026-12-07T12:00:00+00:00', $period['ends_at']);
        BusinessContractFixtures::prepare($quote);
        $this->at($quote->snapshot['transition']['not_before']);
        $contract = app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $quote->public_id, $this->activationPayload($quote));
        $this->assertSame('superseded', $settlement->fresh()->status);
        $this->assertSame(3, DB::table('business_transition_items')->count());
        $this->assertSame(4, SubscriptionItem::where('subscription_id', $contract->subscription_id)->where('status', 'active')->count());
    }

    public function test_services_outside_business_are_kept_for_manual_review(): void
    {
        $extra = Service::create(['title' => 'Tesorería QA', 'slug' => 'treasury-qa', 'service_key' => 'treasury', 'currency' => 'DOP', 'active' => true]);
        SubscriptionItem::create(['subscription_id' => $this->sources[0]['subscription']->id, 'service_id' => $extra->id, 'currency' => 'DOP', 'status' => 'active']);
        $preview = app(BusinessCatalogService::class)->preview(ServiceCatalogVersion::firstOrFail(), BusinessTransitionFixtures::selection(), Company::findOrFail(1));
        $this->assertFalse($preview['transition_review']['eligible']);
        $this->assertStringContainsString('fuera del alcance', $preview['transition_review']['reason']);
        $this->assertSame(3, SubscriptionItem::where('status', 'active')->count());
    }

    public function test_disabling_transition_after_consent_prevents_activation_but_not_history_reads(): void
    {
        $quote = BusinessTransitionFixtures::issue();
        BusinessContractFixtures::prepare($quote);
        $this->at('2026-11-14 12:00:00 UTC');
        config()->set('business_commercial.transition_enabled', false);
        $url = '/admin/business-implementations/'.$quote->public_id.'/activation';
        $this->get($url)->assertOk();
        $this->postJson($url, $this->activationPayload($quote))->assertConflict();
        $this->assertSame(2, Subscription::where('status', 'active')->count());
    }

    public function test_subscriber_views_choose_retained_contract_and_dashboard_cache_is_invalidated(): void
    {
        DB::table('companies')->where('id', 1)->update(['owner_user_id' => 1]);
        $quote = BusinessTransitionFixtures::issue();
        BusinessContractFixtures::prepare($quote);
        Cache::put('subscriber.dashboard.stats.company.1.user.1', ['subscription' => ['id' => 2]], 60);
        $this->at('2026-11-14 12:00:00 UTC');
        $contract = app(BusinessContractActivationService::class)->activate(User::findOrFail(4), $quote->public_id, $this->activationPayload($quote));
        $this->assertSame(1, $contract->subscription_id);
        $this->assertNull(Cache::get('subscriber.dashboard.stats.company.1.user.1'));
        $this->assertSame($contract->subscription_id, Subscription::where('subscriber_id', 1)->preferBusinessContract()->latest('id')->first()->id);
        $this->assertSame(2, Subscription::count());
        $this->asUser(1);
        $this->get(route('subscriber.subscription.show', absolute: false))->assertOk()->assertInertia(fn ($page) => $page
            ->where('subscription.id', $contract->subscription_id)->where('subscription.status', 'active')->has('items', 4));
    }

    public function test_explicit_prepaid_future_period_postpones_cutoff_without_losing_paid_time(): void
    {
        $original = $this->sources[0]['invoice'];
        $future = $original->replicate();
        $future->fill(['number' => 'SYNTHETIC-PREPAID-FUTURE', 'status' => 'issued', 'amount_paid' => '0.00',
            'period_start' => $original->period_end, 'period_end' => $original->period_end->copy()->addMonth()])->save();
        $line = $original->items()->sole()->replicate();
        $line->invoice_id = $future->id;
        $line->save();
        Payment::create(['company_id' => 1, 'invoice_id' => $future->id, 'currency' => 'DOP', 'method' => 'bank_transfer',
            'amount' => $future->total, 'paid_at' => now()]);
        $quote = BusinessTransitionFixtures::issue();
        $this->assertSame('2026-12-12T12:00:00+00:00', $quote->snapshot['transition']['not_before']);
        $this->assertCount(3, $quote->snapshot['transition']['periods']);
        $this->assertSame(0, $quote->snapshot['transition']['credit_cents']);
    }
}
