<?php

namespace App\Services\Billing;

use App\Models\BusinessContract;
use App\Models\ServiceQuoteAcceptance;
use App\Models\ServiceQuoteCheckout;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

final class BusinessContractPricing
{
    public function __construct(private readonly BusinessQuoteInvoiceAmounts $amounts) {}

    public function recalculate(Subscription $subscription, BusinessContract $contract): Subscription
    {
        $line = $this->initialLine($subscription, $contract);
        $period = BusinessContractPeriods::latestStarted($contract);
        if ($period) {
            $bill = app(BusinessRenewalService::class)->invoiceDetails($contract, $period);
            $line = ['subtotal_cents' => $this->amounts->cents($period->subtotal),
                'tax_cents' => $this->amounts->cents($period->tax_total), 'total_cents' => $bill['total_cents']];
        }
        $meta = $subscription->meta ?? [];
        $meta['business_contract'] = ['id' => $contract->id, 'snapshot_hash' => $contract->snapshot_hash,
            'pricing' => 'contract_total_includes_discounts', 'automatic_renewal' => false,
            'period' => BusinessContractPeriods::display($contract)];
        unset($meta['bundle_discount']);
        $subscription->forceFill([
            'subtotal_amount' => $this->amounts::decimal($line['subtotal_cents']), 'discount_amount' => '0.00',
            'tax_amount' => $this->amounts::decimal($line['tax_cents']), 'total_amount' => $this->amounts::decimal($line['total_cents']),
            'meta' => $meta,
        ])->save();

        return $subscription->fresh();
    }

    public function initialLine(Subscription $subscription, BusinessContract $contract): array
    {
        $checkout = ServiceQuoteCheckout::findOrFail($contract->service_quote_checkout_id);
        $receipt = ServiceQuoteAcceptance::findOrFail($checkout->service_quote_acceptance_id);
        abort_unless((int) $subscription->subscriber_id === (int) $contract->subscriber_id
            && (int) $subscription->id === (int) $contract->subscription_id
            && (int) $checkout->company_id === (int) $contract->company_id
            && (int) $checkout->subscriber_id === (int) $contract->subscriber_id
            && $subscription->currency === $contract->snapshot['currency']
            && $receipt->snapshot === $contract->snapshot
            && hash_equals($receipt->snapshot_hash, $contract->snapshot_hash), 409, 'El contrato Business requiere revisión.');
        $items = DB::table('subscription_items')->where('subscription_id', $subscription->id)->get();
        $maps = DB::table('business_contract_items')->where('business_contract_id', $contract->id)->get()->keyBy('subscription_item_id');
        $expected = array_map('intval', array_column($contract->snapshot['products'], 'service_id'));
        sort($expected);
        $actual = $maps->pluck('service_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        abort_unless($actual === $expected && $items->count() === $maps->count(), 409, 'La cobertura Business requiere una revisión de transición.');
        foreach ($items as $item) {
            $map = $maps->get($item->id);
            abort_unless($map && (int) $map->service_id === (int) $item->service_id && $item->currency === $subscription->currency
                && (float) $item->amount === 0.0 && (float) $item->unit_price === 0.0, 409, 'Un servicio incluido cambió su precio o su referencia.');
        }

        return $this->amounts->fromSnapshot($contract->snapshot)['lines']['recurring'];
    }
}
