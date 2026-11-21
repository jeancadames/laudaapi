<?php

namespace Tests\Feature\BusinessCommercial;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Payment;
use App\Models\ServiceCatalogVersion;
use App\Models\User;
use App\Services\Billing\BusinessCatalogService;
use App\Services\Billing\BusinessQuoteAcceptanceService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BusinessCommercialTestCase;
use Tests\Support\BusinessQuoteFixtures;
use Tests\Support\BusinessRenewalFixtures;

final class CapacityPolicyTest extends BusinessCommercialTestCase
{
    private function asAdmin(): void
    {
        $this->withoutVite();
        $this->actingAs(User::findOrFail(4))->withSession(['_token' => 'policy-csrf'])->withHeader('X-CSRF-TOKEN', 'policy-csrf');
    }

    public function test_admin_revision_changes_future_quotes_but_preserves_existing_contract_and_renewal(): void
    {
        $this->asAdmin();
        $originalQuote = $this->quote->fresh()->getAttributes();
        $originalContract = $this->contract->fresh()->getAttributes();
        $version = ServiceCatalogVersion::sole();
        $revision = $this->postJson('/admin/business-catalog/'.$version->id.'/revise', ['lock_version' => $version->lock_version])->assertOk()->json();
        $draft = ServiceCatalogVersion::findOrFail($revision['id']);
        $payload = $draft->payload;
        $payload['capacity_policy']['users'] = 'per_solution';
        $payload['capacity_policy']['owner_counts'] = false;
        $changedQuote = clone $this->quote;
        $changedSnapshot = $changedQuote->snapshot;
        $changedSnapshot['capacity_policy'] = $payload['capacity_policy'];
        $changedQuote->snapshot = $changedSnapshot;
        $this->assertNotSame(BusinessQuoteAcceptanceService::fingerprint($this->quote), BusinessQuoteAcceptanceService::fingerprint($changedQuote));
        $url = '/admin/business-catalog/'.$draft->id;
        $this->putJson($url, ['lock_version' => $draft->lock_version, 'payload' => $payload])->assertOk()->assertJsonPath('payload.capacity_policy.users', 'per_solution')->assertJsonPath('payload.products.ecf.description', '');
        $this->putJson($url, ['lock_version' => $draft->lock_version, 'payload' => $payload])->assertUnprocessable();
        $this->get('/admin/business-catalog?version='.$draft->id)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Admin/BusinessCatalog/Index')->where('selected.payload.capacity_policy', $payload['capacity_policy'])
            ->where('initialCapacityPolicy.users', 'shared_identity'));
        $draft->refresh();
        $this->travelTo(CarbonImmutable::now()->addMinute());
        $this->postJson('/admin/business-catalog/'.$draft->id.'/publish', ['lock_version' => $draft->lock_version])->assertOk();
        $draft->refresh();
        $next = BusinessQuoteFixtures::issue($draft, 2);
        $this->assertSame($payload['capacity_policy'], $next->snapshot['capacity_policy']);
        $this->assertSame($this->quote->snapshot['recurring_cents'], $next->snapshot['recurring_cents']);
        $this->assertSame($this->quote->snapshot['capacity'], $next->snapshot['capacity']);
        $this->assertNotSame(BusinessQuoteAcceptanceService::fingerprint($this->quote), BusinessQuoteAcceptanceService::fingerprint($next));
        $this->actingAs(User::findOrFail(2))->get('/app/business/quotes/'.$next->public_id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('quote.snapshot.capacity_policy', $payload['capacity_policy']));
        $this->postJson('/app/business/quotes/'.$next->public_id.'/accept', [
            'accepted' => true, 'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($next), 'idempotency_key' => (string) Str::uuid(),
        ])->assertSuccessful();
        $audit = AuditLog::where('event', 'business.catalog.saved')->where('model_id', $draft->id)->sole();
        $this->assertSame('shared_identity', $audit->data['before']['payload']['capacity_policy']['users']);
        $this->assertSame('per_solution', $audit->data['after']['payload']['capacity_policy']['users']);
        $this->assertSame($originalQuote, $this->quote->fresh()->getAttributes());
        $this->assertSame($originalContract, $this->contract->fresh()->getAttributes());
        $this->assertSame($this->quote->snapshot['capacity_policy'], $this->exchange('pos', 'snapshot')['capacity_policy']);

        (require base_path('database/migrations/2026_11_06_000008_create_business_renewals.php'))->up();
        config()->set('business_commercial.renewal_enabled', true);
        $period = BusinessRenewalFixtures::issue($this->contract, $this->quote);
        Payment::create(['company_id' => 1, 'invoice_id' => $period->invoice_id, 'currency' => 'DOP', 'method' => 'bank_transfer', 'amount' => $period->total, 'paid_at' => now()]);
        $this->travelTo($period->period_starts_at);
        $snapshot = $this->exchange('pos', 'snapshot');
        $this->assertTrue($snapshot['access']);
        $this->assertSame($this->quote->snapshot['capacity_policy'], $snapshot['capacity_policy']);
        $this->assertSame($originalContract, $this->contract->fresh()->getAttributes());
    }

    public function test_unsupported_register_policy_and_non_admin_edits_are_rejected_without_mutation(): void
    {
        $this->asAdmin();
        $version = ServiceCatalogVersion::sole();
        $draft = app(BusinessCatalogService::class)->revise($version, $version->lock_version, 4);
        $original = $draft->fresh()->getAttributes();
        $payload = $draft->payload;
        $payload['capacity_policy']['registers'] = 'physical_registers';
        $this->putJson('/admin/business-catalog/'.$draft->id, ['lock_version' => $draft->lock_version, 'payload' => $payload])->assertUnprocessable();
        $this->assertSame($original, $draft->fresh()->getAttributes());
        $this->actingAs(User::findOrFail(1))->putJson('/admin/business-catalog/'.$draft->id, ['lock_version' => $draft->lock_version, 'payload' => $draft->payload])->assertForbidden();
        $this->assertSame($original, $draft->fresh()->getAttributes());
    }

    public function test_legacy_catalog_can_be_previewed_and_retried_but_needs_a_revision_for_new_quotes(): void
    {
        BusinessQuoteFixtures::assertSyntheticDatabase();
        $version = ServiceCatalogVersion::sole();
        $payload = $version->payload;
        unset($payload['capacity_policy']);
        DB::table('service_catalog_versions')->where('id', $version->id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        $version->refresh();
        $service = app(BusinessCatalogService::class);
        $this->assertNull($service->preview($version, $this->quote->snapshot['selection'], null)['capacity_policy']);
        $retried = $service->issue($version, $version->lock_version, Company::findOrFail(1), $this->quote->snapshot['selection'], $this->quote->idempotency_key, 4);
        $this->assertSame($this->quote->snapshot, $retried->snapshot);
        $draft = $service->revise($version, $version->lock_version, 4);
        $this->assertArrayNotHasKey('capacity_policy', $draft->payload);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('capacity_policy');
        BusinessQuoteFixtures::issue($version, 2);
    }

    public function test_reading_a_snapshot_without_policy_does_not_default_or_rewrite_it(): void
    {
        BusinessQuoteFixtures::assertSyntheticDatabase();
        $snapshot = $this->contract->snapshot;
        unset($snapshot['capacity_policy']);
        // Field-absence read test only: this altered fixture does not certify historical fingerprint integrity.
        DB::table('business_contracts')->where('id', $this->contract->id)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $original = $this->contract->fresh()->getAttributes();
        $result = $this->exchange('pos', 'snapshot');
        $this->assertNull($result['capacity_policy']);
        $this->assertSame($this->contract->snapshot_hash, $result['snapshot_hash']);
        $this->assertSame($original, $this->contract->fresh()->getAttributes());
        $this->assertSame($snapshot['capacity'], $result['capacity']);
    }
}
