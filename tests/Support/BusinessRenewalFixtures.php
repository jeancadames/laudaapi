<?php

namespace Tests\Support;

use App\Models\BusinessContract;
use App\Models\BusinessRenewalPeriod;
use App\Models\ServiceCommercialQuote;
use App\Models\User;
use App\Services\Billing\BusinessContractPeriods;
use App\Services\Billing\BusinessRenewalService;
use App\Services\Billing\BusinessRenewalTerms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class BusinessRenewalFixtures
{
    public static function issue(BusinessContract $contract, ServiceCommercialQuote $quote, array $terms = []): BusinessRenewalPeriod
    {
        BusinessQuoteFixtures::assertSyntheticDatabase();
        $current = BusinessContractPeriods::display($contract);
        $service = app(BusinessRenewalService::class);
        $publicId = $quote->public_id;
        $offer = $service->propose(User::findOrFail(4), $publicId, array_replace([
            'confirmed' => true, 'idempotency_key' => (string) Str::uuid(), 'period_number' => $current['number'] + 1,
            'expected_revision' => 0, 'valid_until' => CarbonImmutable::parse($current['ends_at'])->subDay()->toIso8601String(),
            'base_cents' => 350000, 'discount_cents' => 50000, 'tax_bps' => 1800,
            'commercial_note' => 'Condiciones sintéticas de QA para este período de renovación.', ...BusinessRenewalTerms::POLICIES,
        ], $terms));
        $consent = ['confirmed' => true, 'idempotency_key' => (string) Str::uuid(), 'offer_id' => $offer->id, 'terms_hash' => $offer->terms_hash];
        $service->accept(User::findOrFail(1), $publicId, $consent);

        return $service->issue(User::findOrFail(4), $publicId, [...$consent, 'idempotency_key' => (string) Str::uuid()]);
    }
}
