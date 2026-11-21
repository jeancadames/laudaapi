<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\PosSetting;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Fiscal\FiscalDistributionApprovalService;
use App\Support\Fiscal\DocumentFiscalCalculator;
use App\Support\Fiscal\FiscalLineSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

config()->set('business_commercial_sales.services_enabled', $mode !== 'services_disabled');
if (! ServiceOrder::whereKey(501)->exists()) {
    Company::whereKey(501)->update(['catalog_mode' => 'services_only']);
    PosSetting::where('company_id', 501)->update(['services_enabled' => true, 'service_orders_enabled' => true, 'service_credit_billing_enabled' => true]);
    $credit = $input['service_credit'] ?? false;
    $term = PaymentTerm::forceCreate(['company_id' => 501, 'code' => 'CREDIT_30', 'name' => 'Crédito 30 días', 'credit_days' => 30, 'type' => 'credit', 'is_active' => true]);
    $customer = Customer::firstOrCreate(['id' => 501], ['company_id' => 501, 'name' => 'Comprador sintético', 'rnc' => '131880681', 'is_active' => true]);
    $customer->forceFill(['customer_type' => Customer::TYPE_CREDIT, 'default_payment_term_id' => $term->id,
        'credit_limit' => 100000, 'credit_status' => Customer::CREDIT_STATUS_ACTIVE])->save();
    $order = ServiceOrder::forceCreate(['id' => 501, 'company_id' => 501, 'branch_id' => 501, 'customer_id' => 501, 'created_by' => 501,
        'order_number' => 'SYNTHETIC-SERVICE', 'status' => ServiceOrder::STATUS_COMPLETED,
        'payment_condition' => $credit ? 'credit' : 'cash', 'payment_term_id' => $credit ? $term->id : null,
        'credit_terms_days' => $credit ? 30 : null, 'ecf_type' => '31', 'applies_itbis' => true,
        'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 18, 'total' => 118]);
    $line = ['description' => 'Servicio sintético', 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => 0,
        'discount_amount' => 0, 'tax_amount' => 18, 'line_total' => 118, 'applies_itbis' => true,
        'tax_breakdown' => [['dgii_indicator' => 1, 'rate' => 18, 'base_amount' => 100, 'amount' => 18, 'is_exempt' => false]]];
    $line['fiscal_snapshot'] = FiscalLineSnapshot::capture($line, 2, '31', $line['tax_breakdown']);
    $line = app(DocumentFiscalCalculator::class)->calculate([$line])[0];
    unset($line['line_subtotal']);
    $order->items()->create($line);
    $distribution = app(FiscalDistributionApprovalService::class);
    $preview = $distribution->preview($order->fresh('items'), User::findOrFail(501));
    $distribution->approve($order->fresh('items'), User::findOrFail(501), $preview['id'], $preview['fingerprint'], 'Synthetic approved service');
}
if ($mode === 'late_service_validation') {
    DB::connection()->beforeExecuting(function ($sql): void {
        if (str_starts_with($sql, 'insert into "accounts_receivable"')) {
            throw ValidationException::withMessages(['service' => 'Synthetic rejection after SQL invoice and lines.']);
        }
    });
}
if (in_array($mode, ['service_after_commit', 'service_evidence_loss'], true)) {
    ServiceOrder::updated(function () use ($mode): void {
        DB::afterCommit(function () use ($mode): void {
            if ($mode === 'service_evidence_loss') {
                DB::table('pos_business_sale_documents')->delete();
                DB::table('pos_operation_receipts')->delete();
            }
            throw new RuntimeException('Synthetic service afterCommit failure.');
        });
    });
}
