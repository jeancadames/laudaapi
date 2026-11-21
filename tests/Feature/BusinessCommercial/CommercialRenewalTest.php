<?php

namespace Tests\Feature\BusinessCommercial;

use App\Models\BusinessRenewalPeriod;
use App\Models\Payment;
use App\Models\ServiceCommercialQuote;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BusinessCommercialTestCase;
use Tests\Support\BusinessRenewalFixtures;

final class CommercialRenewalTest extends BusinessCommercialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_11_06_000008_create_business_renewals.php'))->up();
        config()->set('business_commercial.renewal_enabled', true);
    }

    protected function seedQuote(): ServiceCommercialQuote
    {
        Carbon::setTestNow('2026-11-06 12:00:00 UTC');
        CarbonImmutable::setTestNow('2026-11-06 12:00:00 UTC');

        return parent::seedQuote();
    }

    private function pay(BusinessRenewalPeriod $period, ?string $amount = null): Payment
    {
        return Payment::create(['company_id' => 1, 'invoice_id' => $period->invoice_id, 'currency' => 'DOP',
            'method' => 'bank_transfer', 'amount' => $amount ?? $period->total, 'paid_at' => now()]);
    }

    public function test_unpaid_partial_and_paid_renewal_use_the_original_capacity_and_preserve_document_identity(): void
    {
        $original = $this->contract->fresh()->getAttributes();
        $oldOperation = $this->operation('sale:previous');
        $old = $this->exchange('pos', 'reserve', $oldOperation);
        $this->exchange('pos', 'commit', ['operation_key' => $oldOperation['operation_key']]);
        $period = BusinessRenewalFixtures::issue($this->contract, $this->quote);
        $this->travelTo($period->period_starts_at);

        $snapshot = $this->exchange('pos', 'snapshot');
        $this->assertTrue($snapshot['managed']);
        $this->assertFalse($snapshot['access']);
        $this->assertSame($this->contract->id, $snapshot['contract_id']);
        $this->assertSame(2, $snapshot['coverage']['number']);
        $this->exchange('pos', 'reserve', $this->operation('sale:unpaid'), 403);
        $this->exchange('cumplimiento', 'reference', $this->operation('report:unpaid', ['document_id' => $old['document_id']]), 403);
        $payment = $this->pay($period, '100.00');
        $this->assertFalse($this->exchange('pos', 'snapshot')['access']);
        $this->exchange('pos', 'reserve', $this->operation('sale:partial'), 403);
        $this->assertSame(1, DB::table('business_usage_documents')->count());

        $payment->update(['amount' => $period->total]);
        DB::table('services')->update(['monthly_price' => 999999]);
        $snapshot = $this->exchange('pos', 'snapshot');
        $this->assertTrue($snapshot['access']);
        $this->assertSame($this->contract->snapshot['capacity'], $snapshot['capacity']);
        $this->assertSame(500, $snapshot['documents']['remaining']);
        $this->assertSame($period->period_ends_at->toIso8601String(), $snapshot['coverage']['ends_at']);
        foreach (['ecf', 'cumplimiento'] as $service) {
            $reference = $this->exchange($service, 'reference', $this->operation('report:previous', ['document_id' => $old['document_id']]));
            $this->assertSame($old['document_id'], $reference['document_id']);
            $this->assertSame('referenced', $reference['state']);
        }
        $this->assertSame(500, $this->exchange('pos', 'snapshot')['documents']['remaining']);
        $new = $this->operation('sale:renewed');
        $receipt = $this->exchange('pos', 'reserve', $new);
        $this->assertSame($period->period_starts_at->toIso8601String(), $receipt['period_starts_at']);
        $this->exchange('pos', 'commit', ['operation_key' => $new['operation_key']]);
        $this->assertSame(499, $this->exchange('pos', 'snapshot')['documents']['remaining']);
        $this->assertSame($old['period_starts_at'], $this->exchange('pos', 'reconcile', ['operation_key' => $oldOperation['operation_key']])['period_starts_at']);
        $this->assertSame($original, $this->contract->fresh()->getAttributes());
    }

    public function test_reversed_renewal_blocks_admission_but_preserves_settlement_and_recovery(): void
    {
        $period = BusinessRenewalFixtures::issue($this->contract, $this->quote);
        $payment = $this->pay($period);
        $this->travelTo($period->period_starts_at);
        $operation = $this->operation();
        $receipt = $this->exchange('pos', 'reserve', $operation);
        $payment->update(['paid_at' => null]);
        DB::table('invoices')->where('id', $period->invoice_id)->update(['status' => 'paid', 'amount_paid' => $period->total]);
        $this->travelTo($period->period_starts_at->addSeconds(121));

        $this->assertFalse($this->exchange('pos', 'snapshot')['access']);
        $this->exchange('pos', 'reserve', $this->operation('sale:reversed'), 403);
        $committed = $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]);
        $this->assertSame('committed', $committed['state']);
        $this->assertSame($receipt['document_id'], $committed['document_id']);
        $this->assertSame($committed, $this->exchange('pos', 'reconcile', ['operation_key' => $operation['operation_key']]));
        $this->assertSame($committed, $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']]));
        $this->assertSame(1, DB::table('business_usage_documents')->whereNotNull('consumed_at')->count());

        $payment->update(['paid_at' => now()]);
        $this->assertTrue($this->exchange('pos', 'snapshot')['access']);
        $this->exchange('pos', 'reserve', $this->operation('sale:restored'));
        $this->assertSame(498, $this->exchange('pos', 'snapshot')['documents']['remaining']);
    }

    public static function admissionTimes(): array
    {
        return ['receipt timeout' => [false], 'paid period boundary' => [true]];
    }

    #[DataProvider('admissionTimes')]
    public function test_admission_expires_at_the_shorter_receipt_or_renewed_period_boundary(bool $atEnd): void
    {
        $period = BusinessRenewalFixtures::issue($this->contract, $this->quote);
        $this->pay($period);
        $this->travelTo($atEnd ? $period->period_ends_at->subSeconds(5) : $period->period_starts_at);
        $operation = $this->operation();
        $receipt = $this->exchange('pos', 'reserve', $operation);
        $expected = $atEnd ? $period->period_ends_at : CarbonImmutable::now()->addSeconds(120);
        $this->assertSame($expected->toIso8601String(), $receipt['valid_until']);
        $this->travelTo($period->period_ends_at);
        $this->assertFalse($this->exchange('pos', 'snapshot')['access']);
        $this->exchange('pos', 'reserve', $this->operation('sale:expired'), 403);
        $this->assertSame('committed', $this->exchange('pos', 'commit', ['operation_key' => $operation['operation_key']])['state']);
    }

    public function test_explicit_zero_renewal_admits_without_inventing_a_payment(): void
    {
        $payments = Payment::count();
        $period = BusinessRenewalFixtures::issue($this->contract, $this->quote, ['base_cents' => 0, 'discount_cents' => 0, 'tax_bps' => 0]);
        $this->travelTo($period->period_starts_at);
        $this->assertTrue($this->exchange('pos', 'snapshot')['access']);
        $this->exchange('pos', 'reserve', $this->operation());
        $this->assertSame(499, $this->exchange('pos', 'snapshot')['documents']['remaining']);
        $this->assertSame($payments, Payment::count());
    }

    public function test_a_paid_renewal_does_not_cover_the_next_unpaid_renewal(): void
    {
        $second = BusinessRenewalFixtures::issue($this->contract, $this->quote);
        $this->pay($second);
        $this->travelTo($second->period_starts_at);
        $third = BusinessRenewalFixtures::issue($this->contract, $this->quote);
        $this->travelTo($third->period_starts_at);
        $snapshot = $this->exchange('pos', 'snapshot');
        $this->assertSame(3, $snapshot['coverage']['number']);
        $this->assertFalse($snapshot['access']);
        $this->exchange('pos', 'reserve', $this->operation('sale:third-unpaid'), 403);
        $this->pay($third);
        $this->assertTrue($this->exchange('pos', 'snapshot')['access']);
        $this->exchange('pos', 'reserve', $this->operation('sale:third-paid'));
        $this->assertSame(499, $this->exchange('pos', 'snapshot')['documents']['remaining']);
    }
}
