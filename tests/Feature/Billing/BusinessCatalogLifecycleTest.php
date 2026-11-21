<?php

namespace Tests\Feature\Billing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BusinessCatalogService;
use Carbon\CarbonImmutable;
use Database\Seeders\BusinessCatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Run against the configured MySQL DEV database after migration, with rollback. */
final class BusinessCatalogLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'Esta suite exige MySQL DEV configurado; no crea bases de pruebas.');
        $this->assertTrue(app()->environment(['local', 'testing']), 'Esta suite no se ejecuta en producción.');
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $root = ServiceCatalog::firstOrCreate(['code' => 'business']);
        $payload = BusinessCatalogSeeder::initialPayload();
        foreach ($payload['products'] as $code => &$product) {
            $service = Service::firstOrCreate(['service_key' => $code], ['slug' => 'business-test-'.Str::uuid(), 'title' => $code, 'currency' => 'DOP']);
            $service->update(['active' => true]);
            $product['service_id'] = (int) $service->id;
        }
        unset($product);
        $payload['terms'] = 'Condiciones ficticias de prueba, sin activación ni cobros.';
        $payload['quote_valid_days'] = 15;
        $payload['tax_rate'] = 0;
        $version = ServiceCatalogVersion::create([
            'service_catalog_id' => $root->id, 'version' => (ServiceCatalogVersion::where('service_catalog_id', $root->id)->max('version') ?? 0) + 1,
            'status' => 'draft', 'payload' => $payload, 'created_by' => $admin->id,
        ]);
        $lastEffective = ServiceCatalogVersion::where('service_catalog_id', $root->id)->max('effective_at');
        $clock = CarbonImmutable::now('UTC')->addDay();
        if ($lastEffective && $clock->lessThanOrEqualTo(CarbonImmutable::parse($lastEffective, 'UTC'))) {
            $clock = CarbonImmutable::parse($lastEffective, 'UTC')->addDay();
        }
        CarbonImmutable::setTestNow($clock);
        $subscriber = Subscriber::create(['name' => 'Business QA', 'slug' => 'business-qa-'.Str::uuid(), 'currency' => 'DOP', 'active' => true]);
        $company = Company::withoutEvents(fn () => Company::create(['name' => 'Business QA', 'slug' => 'business-qa-'.Str::uuid(), 'subscriber_id' => $subscriber->id, 'currency' => 'DOP', 'active' => true]));

        return [$admin, $version, $company];
    }

    private function selection(): array
    {
        return ['offer' => 'business', 'level' => 'initial', 'cycle' => 'monthly', 'implementation' => 'guided', 'addons' => [], 'discount' => null];
    }

    public function test_save_rejects_stale_revision_and_records_actor_before_and_after(): void
    {
        [$admin, $version] = $this->fixture();
        $service = app(BusinessCatalogService::class);
        $payload = $version->payload;
        $payload['offers']['business']['levels']['initial']['monthly_cents'] = 370000;
        $saved = $service->save($version, 1, $payload, $admin->id);
        $this->assertSame(2, $saved->lock_version);
        $audit = AuditLog::where('event', 'business.catalog.saved')->where('model_id', $version->id)->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame(350000, $audit->data['before']['payload']['offers']['business']['levels']['initial']['monthly_cents']);
        $this->assertSame(370000, $audit->data['after']['payload']['offers']['business']['levels']['initial']['monthly_cents']);
        $this->expectException(DomainException::class);
        $service->save($version, 1, $version->payload, $admin->id);
    }

    public function test_future_publication_retirement_and_old_quote_snapshot(): void
    {
        [$admin, $version, $company] = $this->fixture();
        $service = app(BusinessCatalogService::class);
        $published = $service->publish($version, 1, null, $admin->id);
        $first = $service->issue($published, $published->lock_version, $company, $this->selection(), (string) Str::uuid(), $admin->id);
        $draft = $service->revise($published, $published->lock_version, $admin->id);
        $payload = $draft->payload;
        $payload['offers']['business']['levels']['initial']['monthly_cents'] = 390000;
        $draft = $service->save($draft, 1, $payload, $admin->id);
        $future = CarbonImmutable::now('UTC')->addDay();
        $next = $service->publish($draft, $draft->lock_version, $future->format(DATE_ATOM), $admin->id);
        $this->assertSame($published->id, $service->current()->id);
        CarbonImmutable::setTestNow($future->addSecond());
        $this->assertSame($next->id, $service->current()->id);
        $this->assertSame(350000, $first->fresh()->snapshot['recurring_cents']);
        $service->retire($next, $next->lock_version, $admin->id);
        $this->assertNull($service->current());
        $this->assertSame(350000, $first->fresh()->snapshot['recurring_cents']);
    }

    public function test_issuance_is_idempotent_without_invoice_or_subscription(): void
    {
        [$admin, $version, $company] = $this->fixture();
        $service = app(BusinessCatalogService::class);
        $published = $service->publish($version, 1, null, $admin->id);
        $key = (string) Str::uuid();
        $invoices = Invoice::count();
        $subscriptions = Subscription::count();
        $one = $service->issue($published, $published->lock_version, $company, $this->selection(), $key, $admin->id);
        $service->retire($published, $published->lock_version, $admin->id);
        $two = $service->issue($published, $published->lock_version, $company, $this->selection(), $key, $admin->id);
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, ServiceCommercialQuote::where('company_id', $company->id)->count());
        $this->assertSame($invoices, Invoice::count());
        $this->assertSame($subscriptions, Subscription::count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_idempotency_key_cannot_be_reused_for_different_conditions(): void
    {
        [$admin, $version, $company] = $this->fixture();
        $service = app(BusinessCatalogService::class);
        $published = $service->publish($version, 1, null, $admin->id);
        $key = (string) Str::uuid();
        $service->issue($published, $published->lock_version, $company, $this->selection(), $key, $admin->id);
        $this->expectException(DomainException::class);
        $service->issue($published, $published->lock_version, $company, array_replace($this->selection(), ['level' => 'growth']), $key, $admin->id);
    }

    public function test_seeding_again_preserves_draft_changes(): void
    {
        [$admin, $version] = $this->fixture();
        $payload = $version->payload;
        $payload['terms'] = 'Condiciones particulares conservadas.';
        app(BusinessCatalogService::class)->save($version, 1, $payload, $admin->id);
        $count = ServiceCatalogVersion::count();
        (new BusinessCatalogSeeder)->run($admin->id);
        $this->assertSame($count, ServiceCatalogVersion::count());
        $this->assertSame($payload, $version->fresh()->payload);
    }

    public function test_admin_routes_are_denied_to_non_admin_even_with_known_quote_id(): void
    {
        [$admin, $version, $company] = $this->fixture();
        $service = app(BusinessCatalogService::class);
        $published = $service->publish($version, 1, null, $admin->id);
        $quote = $service->issue($published, $published->lock_version, $company, $this->selection(), (string) Str::uuid(), $admin->id);
        $user = User::factory()->create(['role' => 'subscriber']);
        $this->actingAs($user)->get('/admin/business-catalog')->assertForbidden();
        $this->getJson('/admin/business-catalog/quotes/'.$quote->id)->assertForbidden();
        $this->postJson('/admin/business-catalog/'.$published->id.'/retire', ['lock_version' => $published->lock_version])->assertForbidden();
        $this->assertSame('published', $published->fresh()->status);
    }

    public function test_draft_cannot_be_issued_and_published_payload_cannot_be_mutated(): void
    {
        [$admin, $version, $company] = $this->fixture();
        $this->actingAs($admin)->postJson('/admin/business-catalog/'.$version->id.'/issue', [
            'lock_version' => 1, 'selection' => $this->selection(), 'company_id' => $company->id, 'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $published = app(BusinessCatalogService::class)->publish($version, 1, null, $admin->id);
        $this->expectException(\LogicException::class);
        $published->update(['payload' => []]);
    }
}
