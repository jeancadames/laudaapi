<?php

namespace App\Services\Entitlements;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fresh authorization for Business-owned items; legacy items keep their existing policy. */
final class BusinessContractCoverage
{
    public static function constrain($query): void
    {
        if (! Schema::hasTable('business_contract_items')) {
            return;
        }
        $query->where(function ($eligible): void {
            $eligible->whereNotExists(fn ($map) => $map->selectRaw('1')->from('business_contract_items as ownership')
                ->whereColumn('ownership.subscription_item_id', 'subscription_items.id'));
            // Turning off creation does not remove ownership; existing contracts fail closed.
            if (! config('business_commercial.activation_enabled') || ! config('business_identity.enabled')) {
                return;
            }
            $eligible->orWhereExists(function ($valid): void {
                $valid->selectRaw('1')->from('business_contract_items as coverage')
                    ->join('business_contracts as contract', 'contract.id', '=', 'coverage.business_contract_id')
                    ->join('service_quote_checkouts as checkout', 'checkout.id', '=', 'contract.service_quote_checkout_id')
                    ->join('subscriptions as subscription', 'subscription.id', '=', 'contract.subscription_id')
                    ->join('invoices as bill', 'bill.id', '=', 'checkout.invoice_id')
                    ->join('companies as tenant', 'tenant.id', '=', 'contract.company_id')
                    ->join('subscribers as subscriber', 'subscriber.id', '=', 'contract.subscriber_id')
                    ->join('business_identity_links as link', 'link.id', '=', 'coverage.identity_link_id')
                    ->whereColumn('coverage.subscription_item_id', 'subscription_items.id')
                    ->whereColumn('coverage.service_id', 'subscription_items.service_id')
                    ->whereColumn('contract.subscription_id', 'subscription_items.subscription_id')
                    ->whereColumn('tenant.subscriber_id', 'contract.subscriber_id')->where('tenant.active', true)->where('subscriber.active', true)
                    ->whereColumn('bill.company_id', 'tenant.id')->whereColumn('checkout.company_id', 'tenant.id')
                    ->whereColumn('checkout.subscriber_id', 'subscriber.id')->whereColumn('checkout.snapshot_hash', 'contract.snapshot_hash')
                    ->whereColumn('bill.currency', 'subscription_items.currency')->whereColumn('tenant.currency', 'bill.currency')->whereColumn('subscriber.currency', 'bill.currency')
                    ->whereColumn('bill.total', 'contract.initial_invoice_total')->whereColumn('bill.amount_paid', '>=', 'contract.initial_invoice_total')
                    ->whereColumn('bill.subtotal', 'contract.initial_invoice_subtotal')->whereColumn('bill.tax_total', 'contract.initial_invoice_tax')->where('bill.discount_total', 0)
                    ->whereColumn('bill.billing_snapshot->snapshot_hash', 'contract.snapshot_hash')->where('bill.billing_snapshot->source', 'business_quote_checkout')
                    ->whereIn('bill.status', ['issued', 'overdue', 'paid'])
                    ->where('subscription.status', 'active')->whereColumn('subscription.subscriber_id', 'subscriber.id')
                    ->whereColumn('subscription.currency', 'bill.currency')
                    ->whereColumn('subscription.current_period_start', 'contract.activated_at')->whereColumn('subscription.current_period_end', 'contract.period_ends_at')
                    ->where(fn ($q) => $q->whereNull('subscription.ends_at')->orWhere('subscription.ends_at', '>', now()))
                    ->where(function ($period): void {
                        $period->where(fn ($first) => $first->where('contract.activated_at', '<=', now())->where('contract.period_ends_at', '>', now()));
                        BusinessRenewalCoverage::alternative($period);
                    })
                    ->where('link.active', true)->whereColumn('link.central_company_id', 'tenant.id')
                    ->whereColumn('link.central_subscriber_id', 'subscriber.id')->whereColumn('link.local_company_id', 'coverage.local_company_id')
                    ->whereRaw('(SELECT ROUND(COALESCE(SUM(p.amount), 0), 2) FROM payments p WHERE p.invoice_id = bill.id AND p.paid_at IS NOT NULL AND p.company_id = bill.company_id AND p.currency = bill.currency) >= contract.initial_invoice_total')
                    ->whereNotExists(fn ($bad) => $bad->selectRaw('1')->from('payments as bad')->whereColumn('bad.invoice_id', 'bill.id')
                        ->where(fn ($q) => $q->whereColumn('bad.company_id', '!=', 'bill.company_id')->orWhereColumn('bad.currency', '!=', 'bill.currency')));
            });
        });
    }

    public static function itemIsCovered(int $itemId): bool
    {
        return DB::table('subscription_items')->where('id', $itemId)->where(fn ($query) => self::constrain($query))->exists();
    }
}
