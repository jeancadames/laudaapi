<?php

namespace App\Services\Entitlements;

use Illuminate\Support\Facades\Schema;

final class BusinessRenewalCoverage
{
    /** Correlated to the original contract; its identity, links and initial payment still apply. */
    public static function alternative($query): void
    {
        if (! config('business_commercial.renewal_enabled') || ! Schema::hasTable('business_renewal_periods')
            || ! Schema::hasTable('business_renewal_acceptances') || ! Schema::hasTable('business_renewal_offers')) {
            return;
        }
        $query->orWhereExists(function ($valid): void {
            $valid->selectRaw('1')->from('business_renewal_periods as renewal')
                ->join('business_renewal_acceptances as consent', 'consent.id', '=', 'renewal.business_renewal_acceptance_id')
                ->join('business_renewal_offers as offer', 'offer.id', '=', 'consent.business_renewal_offer_id')
                ->join('invoices as renewal_bill', 'renewal_bill.id', '=', 'renewal.invoice_id')
                ->whereColumn('renewal.business_contract_id', 'contract.id')
                ->whereColumn('consent.business_contract_id', 'contract.id')->whereColumn('offer.business_contract_id', 'contract.id')
                ->whereColumn('offer.terms_hash', 'renewal.terms_hash')->whereColumn('consent.terms_hash', 'renewal.terms_hash')
                ->whereColumn('offer.period_number', 'renewal.period_number')->whereColumn('consent.period_number', 'renewal.period_number')
                ->whereColumn('offer.terms->contract_hash', 'contract.snapshot_hash')
                ->whereColumn('offer.period_starts_at', 'renewal.period_starts_at')->whereColumn('offer.period_ends_at', 'renewal.period_ends_at')
                ->whereColumn('renewal_bill.company_id', 'tenant.id')->whereColumn('renewal_bill.subscription_id', 'subscription.id')
                ->whereColumn('renewal_bill.currency', 'bill.currency')->whereColumn('offer.terms->currency', 'bill.currency')
                ->whereColumn('renewal_bill.period_start', 'renewal.period_starts_at')->whereColumn('renewal_bill.period_end', 'renewal.period_ends_at')
                ->whereColumn('renewal_bill.subtotal', 'renewal.subtotal')->whereColumn('renewal_bill.tax_total', 'renewal.tax_total')
                ->whereColumn('renewal_bill.total', 'renewal.total')->where('renewal_bill.discount_total', 0)
                ->whereColumn('renewal_bill.billing_snapshot->terms_hash', 'renewal.terms_hash')
                ->whereColumn('renewal_bill.billing_snapshot->contract_id', 'contract.id')
                ->whereColumn('renewal_bill.billing_snapshot->acceptance_id', 'consent.id')
                ->where('renewal_bill.billing_snapshot->source', 'business_renewal')->whereIn('renewal_bill.status', ['issued', 'overdue', 'paid'])
                ->whereNull('subscription.cancelled_at')
                ->where('renewal.period_starts_at', '<=', now())->where('renewal.period_ends_at', '>', now())
                ->whereColumn('renewal_bill.amount_paid', '>=', 'renewal.total')
                ->whereRaw('(SELECT COUNT(*) FROM invoice_items line WHERE line.invoice_id = renewal_bill.id) = 1')
                ->whereExists(fn ($line) => $line->selectRaw('1')->from('invoice_items as renewal_line')
                    ->whereColumn('renewal_line.invoice_id', 'renewal_bill.id')->whereNull('renewal_line.service_id')
                    ->where('renewal_line.quantity', 1)->where('renewal_line.discount_amount', 0)
                    ->whereColumn('renewal_line.unit_price', 'renewal.subtotal')->whereColumn('renewal_line.line_subtotal', 'renewal.subtotal')
                    ->whereColumn('renewal_line.tax_amount', 'renewal.tax_total')->whereColumn('renewal_line.line_total', 'renewal.total')
                    ->whereColumn('renewal_line.meta->terms_hash', 'renewal.terms_hash')->where('renewal_line.meta->source', 'business_renewal'))
                ->whereRaw('(SELECT ROUND(COALESCE(SUM(p.amount), 0), 2) FROM payments p WHERE p.invoice_id = renewal_bill.id AND p.paid_at IS NOT NULL) = renewal_bill.amount_paid')
                ->whereNotExists(fn ($bad) => $bad->selectRaw('1')->from('payments as bad')->whereColumn('bad.invoice_id', 'renewal_bill.id')
                    ->where(fn ($q) => $q->whereColumn('bad.company_id', '!=', 'renewal_bill.company_id')
                        ->orWhereColumn('bad.currency', '!=', 'renewal_bill.currency')->orWhere('bad.amount', '<', 0)));
            // Compare scalar JSON values, not JSON objects (whose comparison differs by driver).
            foreach (['schema_version', 'contract_id', 'contract_hash', 'company_id', 'subscriber_id', 'currency', 'timezone',
                'billing_cycle', 'period_number', 'period_starts_at', 'period_ends_at', 'calendar', 'base_cents', 'discount_cents',
                'subtotal_cents', 'tax_bps', 'tax_cents', 'total_cents', 'one_time_cents', 'renewal_mode', 'discount_duration',
                'price_changes', 'tax_changes', 'unpaid_coverage', 'commercial_note', 'consent'] as $field) {
                $valid->whereColumn('renewal_bill.billing_snapshot->terms->'.$field, 'offer.terms->'.$field);
            }
        });
    }
}
