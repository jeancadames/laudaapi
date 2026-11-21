<?php

namespace Tests\Feature\Billing;

use App\Models\AuditLog;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteAcceptance;
use App\Models\User;
use App\Services\Billing\BusinessCatalogService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessIdentityApplication;
use Tests\Support\BusinessQuoteFixtures;

final class BusinessQuoteAcceptanceTest extends TestCase
{
    private string $runtime;

    private ServiceCommercialQuote $quote;

    public function createApplication()
    {
        $this->runtime = '/tmp/lauda-business-'.bin2hex(random_bytes(8));
        mkdir($this->runtime, 0700);
        file_put_contents($this->runtime.'/synthetic-only', 'Business quote acceptance only');

        $this->beforeApplicationDestroyed(BusinessIdentityApplication::restoreEnvironmentCallback());

        return BusinessIdentityApplication::create(dirname(__DIR__, 3), $this->runtime.'/central');
    }

    protected function setUp(): void
    {
        parent::setUp();
        BusinessQuoteFixtures::schema();
        Mail::fake();
        Notification::fake();
        $this->quote = BusinessQuoteFixtures::seed();
        $this->withoutVite();
        $this->actingAs(User::findOrFail(1))->withSession(['_token' => 'quote-csrf'])->withHeader('X-CSRF-TOKEN', 'quote-csrf');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->runtime);
    }

    private function url(?ServiceCommercialQuote $quote = null): string
    {
        return '/app/business/quotes/'.($quote ?? $this->quote)->public_id;
    }

    private function payload(?ServiceCommercialQuote $quote = null): array
    {
        return ['accepted' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote ?? $this->quote), 'idempotency_key' => (string) Str::uuid()];
    }

    public function test_owner_reviews_exact_quote_and_implementation_scope(): void
    {
        $this->withoutExceptionHandling();
        $response = $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->component('Business/Quotes/Show')->where('quote.company_name', 'Comercial Rivera')
            ->where('quote.snapshot.recurring_cents', 350000)->where('quote.snapshot.one_time_cents', 600000)
            ->where('quote.snapshot.implementation.hours', 4)->where('quote.can_accept', true)
            ->where('quote.snapshot_hash', BusinessQuoteAcceptanceService::fingerprint($this->quote)));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_listing_is_tenant_scoped_and_includes_only_managed_companies(): void
    {
        BusinessQuoteFixtures::issue(companyId: 2);
        $this->get('/app/business/quotes')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Business/Quotes/Index')->has('quotes.data', 1)->where('quotes.data.0.public_id', $this->quote->public_id));
    }

    public function test_acceptance_preserves_snapshot_actor_and_audit_without_billing_or_activation(): void
    {
        $snapshot = $this->quote->snapshot;
        $payload = $this->payload();
        $this->postJson($this->url().'/accept', [...$payload, 'snapshot' => ['recurring_cents' => 1], 'company_id' => 2])->assertOk()
            ->assertJsonPath('acceptance.accepted_name', 'Ana Rivera')->assertJsonPath('acceptance.snapshot_hash', $payload['snapshot_hash']);
        $receipt = ServiceQuoteAcceptance::sole();
        $this->assertSame($snapshot, $receipt->snapshot);
        $this->assertSame($snapshot, $this->quote->fresh()->snapshot);
        $this->assertSame(1, $receipt->accepted_by);
        $this->assertSame(1, AuditLog::where('event', 'business.quote.accepted')->count());
        foreach (['invoices', 'payments', 'subscriptions', 'subscription_items', 'business_identity_links', 'diagnosis_assessments', 'transformation_capability_activations'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_retry_after_expiry_and_retirement_returns_the_same_acceptance(): void
    {
        $payload = $this->payload();
        $one = $this->postJson($this->url().'/accept', $payload)->assertOk()->json();
        DB::table('service_catalog_versions')->update(['status' => 'retired']);
        CarbonImmutable::setTestNow($this->quote->valid_until->addSecond());
        $this->assertSame($one, $this->postJson($this->url().'/accept', $payload)->assertOk()->json());
        $this->assertSame($one, $this->postJson($this->url().'/accept', $this->payload())->assertOk()->json());
        $this->assertSame(1, ServiceQuoteAcceptance::count());
        $this->assertSame(1, AuditLog::where('event', 'business.quote.accepted')->count());
    }

    public function test_acceptance_uses_issued_prices_after_a_new_catalogue_version_and_service_price_change(): void
    {
        $catalog = app(BusinessCatalogService::class);
        $version = ServiceCatalogVersion::firstOrFail();
        $draft = $catalog->revise($version, $version->lock_version, 4);
        $payload = $draft->payload;
        $payload['offers']['business']['levels']['initial']['monthly_cents'] = 999999;
        $draft = $catalog->save($draft, 1, $payload, 4);
        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinute());
        $catalog->publish($draft, $draft->lock_version, null, 4);
        DB::table('services')->update(['monthly_price' => 9999]);
        $this->postJson($this->url().'/accept', $this->payload())->assertOk();
        $this->assertSame(350000, ServiceQuoteAcceptance::sole()->snapshot['recurring_cents']);
    }

    public static function unauthorizedActors(): array
    {
        return ['other company' => [2], 'member without billing authority' => [3], 'platform admin without membership' => [4]];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_other_tenants_members_and_platform_admin_cannot_read_or_accept(int $userId): void
    {
        $this->actingAs(User::findOrFail($userId));
        $this->get($this->url())->assertNotFound();
        $this->postJson($this->url().'/accept', $this->payload())->assertNotFound();
        $this->assertSame(0, ServiceQuoteAcceptance::count());
    }

    public function test_tenant_admin_can_accept_but_removed_membership_cannot_retry(): void
    {
        DB::table('subscriber_user')->where('user_id', 1)->update(['role' => 'admin']);
        $payload = $this->payload();
        $this->postJson($this->url().'/accept', $payload)->assertOk();
        DB::table('subscriber_user')->where('user_id', 1)->update(['active' => false]);
        $this->postJson($this->url().'/accept', $payload)->assertNotFound();
        $this->assertSame(1, ServiceQuoteAcceptance::count());
    }

    public function test_guest_and_unverified_user_cannot_accept(): void
    {
        $this->app['auth']->logout();
        $this->postJson($this->url().'/accept', $this->payload())->assertUnauthorized();
        DB::table('users')->where('id', 1)->update(['email_verified_at' => null]);
        $this->actingAs(User::findOrFail(1));
        $this->getJson($this->url())->assertForbidden();
        $this->postJson($this->url().'/accept', $this->payload())->assertForbidden();
        $this->assertSame(0, ServiceQuoteAcceptance::count());
    }

    public static function inactiveEntities(): array
    {
        return ['company' => ['companies'], 'subscriber' => ['subscribers']];
    }

    #[DataProvider('inactiveEntities')]
    public function test_inactive_company_or_subscriber_cannot_read_or_accept(string $table): void
    {
        DB::table($table)->where('id', 1)->update(['active' => false]);
        $this->get($this->url())->assertNotFound();
        $this->postJson($this->url().'/accept', $this->payload())->assertNotFound();
        $this->assertSame(0, ServiceQuoteAcceptance::count());
    }

    public static function unavailableChanges(): array
    {
        return ['expiry' => ['expiry'], 'retired' => ['retired'], 'service inactive' => ['service'], 'currency' => ['currency'], 'existing subscription' => ['subscription']];
    }

    #[DataProvider('unavailableChanges')]
    public function test_changed_commercial_context_is_rejected(string $change): void
    {
        if ($change === 'expiry') {
            CarbonImmutable::setTestNow($this->quote->valid_until);
        }
        if ($change === 'retired') {
            DB::table('service_catalog_versions')->update(['status' => 'retired']);
        }
        if ($change === 'service') {
            DB::table('services')->where('service_key', 'pos')->update(['active' => false]);
        }
        if ($change === 'currency') {
            DB::table('subscribers')->where('id', 1)->update(['currency' => 'USD']);
        }
        if ($change === 'subscription') {
            $id = DB::table('subscriptions')->insertGetId(['subscriber_id' => 1, 'status' => 'active']);
            DB::table('subscription_items')->insert(['subscription_id' => $id, 'service_id' => 1, 'status' => 'active']);
        }
        $this->postJson($this->url().'/accept', $this->payload())->assertConflict();
        $this->assertSame(0, ServiceQuoteAcceptance::count());
    }

    public function test_reassigned_company_does_not_expose_its_previous_subscribers_quotes(): void
    {
        DB::table('companies')->where('id', 2)->update(['subscriber_id' => null]);
        DB::table('companies')->where('id', 1)->update(['subscriber_id' => 2]);
        $this->actingAs(User::findOrFail(2));
        $this->get('/app/business/quotes')->assertOk()->assertInertia(fn ($page) => $page->has('quotes.data', 0));
        $this->get($this->url())->assertNotFound();
    }

    public function test_explicit_consent_csrf_and_matching_hash_are_required(): void
    {
        $payload = $this->payload();
        $this->postJson($this->url().'/accept', [...$payload, 'accepted' => false])->assertUnprocessable();
        $this->withHeader('X-CSRF-TOKEN', 'wrong')->postJson($this->url().'/accept', $payload)->assertStatus(419)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->withHeader('X-CSRF-TOKEN', 'quote-csrf')->postJson($this->url().'/accept', [...$payload, 'snapshot_hash' => str_repeat('0', 64)])->assertConflict();
        $this->assertSame(0, ServiceQuoteAcceptance::count());
    }

    public function test_idempotency_key_cannot_accept_a_different_quote(): void
    {
        $other = BusinessQuoteFixtures::issue();
        $payload = $this->payload();
        $this->postJson($this->url().'/accept', $payload)->assertOk();
        $this->postJson($this->url($other).'/accept', [...$this->payload($other), 'idempotency_key' => $payload['idempotency_key']])->assertConflict();
        $this->assertSame(1, ServiceQuoteAcceptance::count());
    }

    public function test_failed_audit_rolls_back_the_acceptance(): void
    {
        AuditLog::creating(function ($audit): void {
            if ($audit->event === 'business.quote.accepted') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->postJson($this->url().'/accept', $this->payload())->assertStatus(500);
        $this->assertSame(0, ServiceQuoteAcceptance::count());
    }

    public function test_flag_and_missing_migration_block_access_without_creating_tables(): void
    {
        config()->set('business_commercial.enabled', false);
        $this->get($this->url())->assertNotFound();
        $this->postJson($this->url().'/accept', $this->payload())->assertNotFound();
        config()->set('business_commercial.enabled', true);
        (require base_path('database/migrations/2026_11_06_000004_create_service_quote_acceptances.php'))->down();
        $this->get($this->url())->assertStatus(503);
    }

    public function test_migration_round_trip_and_unique_quote_constraint(): void
    {
        $migration = require base_path('database/migrations/2026_11_06_000004_create_service_quote_acceptances.php');
        $migration->down();
        $migration->up();
        $this->postJson($this->url().'/accept', $this->payload())->assertOk();
        $row = (array) DB::table('service_quote_acceptances')->first();
        unset($row['id']);
        $row['idempotency_key'] = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::table('service_quote_acceptances')->insert($row);
    }

    public function test_migration_refuses_to_remove_acceptance_history(): void
    {
        $this->postJson($this->url().'/accept', $this->payload())->assertOk();
        $migration = require base_path('database/migrations/2026_11_06_000004_create_service_quote_acceptances.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_acceptance_model_cannot_change_the_signed_snapshot(): void
    {
        $this->postJson($this->url().'/accept', $this->payload())->assertOk();
        $this->expectException(\LogicException::class);
        ServiceQuoteAcceptance::sole()->update(['snapshot' => []]);
    }
}
