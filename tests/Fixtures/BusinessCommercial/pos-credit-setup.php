<?php

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\Order;
use App\Models\PaymentTerm;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Fiscal\FiscalDistributionApprovalService;
use App\Support\Fiscal\DocumentFiscalCalculator;
use App\Support\Fiscal\FiscalLineSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

config()->set('business_commercial_sales.credit_invoices_enabled', $mode !== 'credit_disabled');
if (! Order::whereKey(501)->exists()) {
    $term = PaymentTerm::forceCreate(['company_id' => 501, 'code' => 'CREDIT_30', 'name' => 'Crédito 30 días', 'credit_days' => 30, 'type' => 'credit', 'is_active' => true]);
    $customer = Customer::firstOrCreate(['id' => 501], ['company_id' => 501, 'name' => 'Comprador sintético', 'rnc' => '131880681', 'is_active' => true]);
    $customer->forceFill(['customer_type' => Customer::TYPE_CREDIT, 'default_payment_term_id' => $term->id,
        'credit_limit' => 100000, 'credit_status' => Customer::CREDIT_STATUS_ACTIVE])->save();
    $order = Order::forceCreate(['id' => 501, 'company_id' => 501, 'branch_id' => 501, 'customer_id' => 501, 'created_by' => 501,
        'order_number' => 'SYNTHETIC-CREDIT', 'order_type' => Order::TYPE_CREDIT_ORDER, 'source' => 'counter',
        'status' => Order::STATUS_APPROVED, 'flow_status' => Order::FLOW_STATUS_READY_FOR_DELIVERY_NOTE,
        'payment_condition' => Order::PAYMENT_CONDITION_CREDIT, 'payment_term_id' => $term->id, 'credit_terms_days' => 30,
        'ecf_type' => '31', 'subtotal' => 200, 'discount_total' => 0, 'tax_total' => 36,
        'total' => match ($mode) {
            'credit_batch_exceeds_commitment' => 180,
            'credit_individual_exceeds_commitment' => 100,
            default => 236,
        }]);
    for ($id = 501; $id <= 502; $id++) {
        // The admission fixture begins with posted delivery notes: invoicing must not move stock again.
        $note = DeliveryNote::forceCreate(['id' => $id, 'company_id' => 501, 'branch_id' => 501, 'customer_id' => 501,
            'order_id' => 501, 'warehouse_id' => Warehouse::first()->id, 'created_by' => 501, 'posted_by' => 501,
            'delivery_note_number' => 'SYNTHETIC-NOTE-'.$id, 'status' => DeliveryNote::STATUS_POSTED,
            'stock_posted' => true, 'posted_at' => now(), 'subtotal' => 100, 'tax_total' => 18, 'discount_total' => 0, 'total' => 118]);
        $line = ['product_id' => 501, 'sku' => 'SYNTHETIC', 'description' => 'Producto sintético', 'quantity' => 1,
            'unit_price' => 100, 'unit_cost' => 40, 'discount_amount' => 0, 'line_subtotal' => 100, 'tax_amount' => 18, 'line_total' => 118,
            'tax_breakdown' => [['dgii_indicator' => 1, 'rate' => 18, 'base_amount' => 100, 'amount' => 18, 'is_exempt' => false]]];
        $line['fiscal_snapshot'] = FiscalLineSnapshot::capture($line, 1, '31', $line['tax_breakdown']);
        $line = app(DocumentFiscalCalculator::class)->calculate([$line])[0];
        $orderItem = $order->items()->create($line);
        $note->items()->create($line + ['order_item_id' => $orderItem->id]);
        $distribution = app(FiscalDistributionApprovalService::class);
        $preview = $distribution->preview($note->fresh('items'), User::findOrFail(501));
        $distribution->approve($note->fresh('items'), User::findOrFail(501), $preview['id'], $preview['fingerprint'], 'Synthetic approved distribution');
    }
}
if ($mode === 'late_credit_validation') {
    $accountInserts = 0;
    DB::connection()->beforeExecuting(function ($sql) use (&$accountInserts): void {
        if (str_starts_with($sql, 'insert into "accounts_receivable"') && ++$accountInserts === 2) {
            throw ValidationException::withMessages(['invoices' => 'Synthetic last account rejection.']);
        }
    });
}
if ($mode === 'due') {
    DB::table('pos_business_credit_intents')->update(['lease_token' => null, 'leased_until' => null]);
}
if ($mode === 'credit_orphan_prepare') {
    DB::connection()->beforeExecuting(function ($sql): void {
        if (str_starts_with($sql, 'update "pos_business_credit_items" set "commercial_operation_id"')) {
            throw new RuntimeException('Synthetic stopped before saving child operation id.');
        }
    });
}
