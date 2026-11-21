<?php

namespace Tests\Feature\BusinessCommercial;

use App\Models\Company;
use App\Models\Payment;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Services\Billing\BusinessCatalogService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BusinessCommercialTestCase;
use Tests\Support\BusinessContractFixtures;
use Tests\Support\BusinessQuoteCheckoutFixtures;
use Tests\Support\BusinessRenewalFixtures;

final class CommercialAnnualUsageTest extends BusinessCommercialTestCase
{
    protected function seedQuote(): ServiceCommercialQuote
    {
        Carbon::setTestNow('2028-01-31 15:00:00 UTC');
        CarbonImmutable::setTestNow('2028-01-31 15:00:00 UTC');
        BusinessContractFixtures::configure();
        BusinessQuoteCheckoutFixtures::seed();
        $version = ServiceCatalogVersion::sole();
        $quote = app(BusinessCatalogService::class)->issue($version, $version->lock_version, Company::findOrFail(1),
            ['offer' => 'business', 'level' => 'initial', 'cycle' => 'yearly', 'implementation' => 'assisted', 'addons' => [], 'discount' => null], (string) Str::uuid(), 4);

        return BusinessContractFixtures::prepare($quote);
    }

    public function test_annual_payment_has_monthly_capacity_and_old_documents_are_not_counted_again(): void
    {
        $operation = $this->operation();
        $first = $this->exchange('pos', 'reserve', $operation);
        $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        $this->travelTo(CarbonImmutable::parse('2028-02-29 15:00:00 UTC'));
        $snapshot = $this->exchange('pos', 'snapshot');
        $this->assertTrue($snapshot['access']);
        $this->assertSame('yearly', $snapshot['billing_cycle']);
        $this->assertSame(500, $snapshot['documents']['remaining']);
        $this->assertSame('2028-03-31T15:00:00+00:00', $snapshot['documents']['ends_at']);
        $this->exchange('ecf', 'reserve', $this->operation('invoice', ['document_id' => $first['document_id']]));
        $this->assertSame(500, $this->exchange('pos', 'snapshot')['documents']['remaining']);
        $this->exchange('pos', 'reserve', $this->operation('new'));
        $this->assertSame(499, $this->exchange('pos', 'snapshot')['documents']['remaining']);
    }

    public function test_released_document_can_be_admitted_in_new_month_without_rewriting_old_receipt(): void
    {
        $operation = $this->operation();
        $first = $this->exchange('pos', 'reserve', $operation);
        $this->exchange('pos', 'release', ['operation_key' => $operation['operation_key']]);
        $this->travelTo(CarbonImmutable::parse('2028-03-31 15:00:00 UTC'));
        $new = $this->exchange('pos', 'reserve', $this->operation());
        $this->assertSame($first['document_id'], $new['document_id']);
        $this->assertSame('2028-03-31T15:00:00+00:00', $new['period_starts_at']);
        $old = $this->exchange('pos', 'reconcile', ['operation_key' => $operation['operation_key']]);
        $this->assertSame($first['period_starts_at'], $old['period_starts_at']);
        $this->assertSame($first['period_ends_at'], $old['period_ends_at']);
        $this->assertSame('released', $old['state']);
        $this->assertSame(499, $this->exchange('pos', 'snapshot')['documents']['remaining']);
        $this->assertSame(1, DB::table('business_usage_documents')->count());
    }

    public function test_admission_validity_cannot_cross_monthly_capacity_boundary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2028-02-29 14:59:55 UTC'));
        $receipt = $this->exchange('pos', 'reserve', $this->operation());
        $this->assertSame('2028-02-29T15:00:00+00:00', $receipt['valid_until']);
    }

    public function test_annual_renewal_keeps_monthly_quota_and_clips_receipts_at_both_boundaries(): void
    {
        (require base_path('database/migrations/2026_11_06_000008_create_business_renewals.php'))->up();
        config()->set('business_commercial.renewal_enabled', true);
        $period = BusinessRenewalFixtures::issue($this->contract, $this->quote);
        Payment::create(['company_id' => 1, 'invoice_id' => $period->invoice_id, 'currency' => 'DOP',
            'method' => 'bank_transfer', 'amount' => $period->total, 'paid_at' => now()]);
        $this->travelTo($period->period_starts_at);
        $snapshot = $this->exchange('pos', 'snapshot');
        $this->assertTrue($snapshot['access']);
        $this->assertSame(2, $snapshot['coverage']['number']);
        $this->assertSame('yearly', $snapshot['billing_cycle']);
        $this->assertSame('2030-01-31T15:00:00+00:00', $snapshot['coverage']['ends_at']);
        $this->assertSame('2029-02-28T15:00:00+00:00', $snapshot['documents']['ends_at']);
        $this->assertSame(500, $snapshot['documents']['remaining']);

        $this->travelTo(CarbonImmutable::parse('2029-02-28 14:59:55 UTC'));
        $operation = $this->operation('sale:renewal-month-one');
        $receipt = $this->exchange('pos', 'reserve', $operation);
        $this->assertSame('2029-02-28T15:00:00+00:00', $receipt['valid_until']);
        $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        $this->assertSame(499, $this->exchange('pos', 'snapshot')['documents']['remaining']);
        $this->travelTo(CarbonImmutable::parse('2029-02-28 15:00:00 UTC'));
        $this->assertSame(500, $this->exchange('pos', 'snapshot')['documents']['remaining']);
        $reference = $this->exchange('cumplimiento', 'reference', $this->operation('report:renewed', ['document_id' => $receipt['document_id']]));
        $this->assertSame($receipt['document_id'], $reference['document_id']);
        $this->assertSame(500, $this->exchange('pos', 'snapshot')['documents']['remaining']);

        $this->travelTo($period->period_ends_at->subSeconds(5));
        $receipt = $this->exchange('pos', 'reserve', $this->operation('sale:last-renewal-month'));
        $this->assertSame('2030-01-31T15:00:00+00:00', $receipt['valid_until']);
        $this->travelTo($period->period_ends_at);
        $this->assertFalse($this->exchange('pos', 'snapshot')['access']);
        $this->exchange('pos', 'reserve', $this->operation('sale:unpaid-year-three'), 403);
    }
}
