<?php

namespace App\Services\Billing;

use App\Models\BusinessContract;
use App\Models\BusinessRenewalPeriod;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** The activation dates remain immutable; subsequent periods have their own ledger. */
final class BusinessContractPeriods
{
    public static function forSubscription(?Subscription $subscription): ?array
    {
        if (! $subscription || ! Schema::hasTable('business_contracts')) {
            return null;
        }
        $contract = BusinessContract::where('subscription_id', $subscription->id)->first();
        if (! $contract) {
            return null;
        }
        $period = self::display($contract);
        $renewal = self::latestStarted($contract);
        $amounts = app(BusinessQuoteInvoiceAmounts::class);
        if ($renewal) {
            app(BusinessRenewalService::class)->invoiceDetails($contract, $renewal);
            $line = ['subtotal_cents' => $amounts->cents($renewal->subtotal), 'tax_cents' => $amounts->cents($renewal->tax_total),
                'total_cents' => $amounts->cents($renewal->total)];
        } else {
            $line = $amounts->fromSnapshot($contract->snapshot)['lines']['recurring'];
        }

        return ['start' => CarbonImmutable::parse($period['starts_at'])->setTimezone($contract->timezone)->toDateString(),
            'end' => CarbonImmutable::parse($period['ends_at'])->setTimezone($contract->timezone)->toDateString(),
            'starts_at' => $period['starts_at'], 'ends_at' => $period['ends_at'],
            'subtotal' => $amounts::decimal($line['subtotal_cents']), 'tax' => $amounts::decimal($line['tax_cents']),
            'total' => $amounts::decimal($line['total_cents'])];
    }

    /** A prepaid future renewal extends paid access, but its price starts on its own billing date. */
    public static function packageSummary(BusinessContract $contract, bool $initialPaid): array
    {
        $now = CarbonImmutable::now();
        $currentTotal = $contract->activated_at <= $now && $now < $contract->period_ends_at
            ? $contract->snapshot['self_service']['recurring']['total_cents'] : null;
        $paidUntil = $initialPaid ? $contract->period_ends_at : null;
        if (Schema::hasTable('business_renewal_periods')) {
            [$started, $future] = BusinessRenewalPeriod::where('business_contract_id', $contract->id)->orderByDesc('period_number')
                ->get()->partition(fn ($period) => $period->period_starts_at <= $now);
            foreach ($started as $period) {
                $bill = self::renewalBill($contract, $period);
                if ($now < $period->period_ends_at) {
                    $currentTotal = $bill['total_cents'] ?? null;
                }
                if (! $initialPaid) {
                    break;
                }
                if ($bill['covered'] ?? false) {
                    $paidUntil = $period->period_ends_at;
                    break;
                }
            }
            foreach ($future->reverse() as $period) {
                if (! $paidUntil || ! $period->period_starts_at->equalTo($paidUntil)
                    || ! (self::renewalBill($contract, $period)['covered'] ?? false)) {
                    break;
                }
                $paidUntil = $period->period_ends_at;
            }
        }

        return ['total_cents' => $currentTotal, 'access_until' => $paidUntil?->toIso8601String()];
    }

    private static function renewalBill(BusinessContract $contract, BusinessRenewalPeriod $period): ?array
    {
        try {
            return app(BusinessRenewalService::class)->invoiceDetails($contract, $period);
        } catch (HttpExceptionInterface $error) {
            if ($error->getStatusCode() !== 409) {
                throw $error;
            }

            // Disputed invoice data cannot establish the price or extend confirmed coverage.
            return null;
        }
    }

    public static function latestStarted(BusinessContract $contract): ?BusinessRenewalPeriod
    {
        if (! Schema::hasTable('business_renewal_periods')) {
            return null;
        }

        return BusinessRenewalPeriod::where('business_contract_id', $contract->id)
            ->where('period_starts_at', '<=', now())->orderByDesc('period_number')->first();
    }

    public static function display(BusinessContract $contract): array
    {
        $period = self::latestStarted($contract);

        return ['number' => $period?->period_number ?? 1,
            'starts_at' => ($period?->period_starts_at ?? $contract->activated_at)->toIso8601String(),
            'ends_at' => ($period?->period_ends_at ?? $contract->period_ends_at)->toIso8601String()];
    }
}
