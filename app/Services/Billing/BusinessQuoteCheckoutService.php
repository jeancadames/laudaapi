<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteAcceptance;
use App\Models\ServiceQuoteCheckout;
use App\Models\Subscriber;
use App\Models\SubscriptionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BusinessQuoteCheckoutService
{
    public function __construct(
        private readonly BusinessQuoteAcceptanceService $quotes,
        private readonly BusinessQuoteInvoiceAmounts $amounts,
    ) {}

    public function ensureAvailable(): void
    {
        $this->quotes->ensureAvailable();
        abort_unless(config('business_commercial.checkout_enabled'), 404);
        foreach (['service_quote_checkouts', 'invoices', 'invoice_items', 'payments'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'La facturación Business todavía no está disponible.');
        }
    }

    public function details(User $user, string $publicId): array
    {
        $this->ensureAvailable();
        $quote = $this->quotes->quote($user, $publicId);
        $receipt = $this->acceptance($quote);
        $checkout = ServiceQuoteCheckout::where('service_quote_acceptance_id', $receipt->id)->first();

        return [
            'quote' => $this->quotes->details($quote),
            'amounts' => $this->amounts->fromSnapshot($receipt->snapshot),
            'invoice' => $checkout ? $this->invoiceDetails($checkout, $receipt) : null,
            'unavailable_reason' => $checkout ? null : $this->unavailableReason($receipt),
            'create_url' => route('business.quotes.checkout.store', $publicId, false),
            'implementation_url' => $checkout && config('business_commercial.implementation_enabled')
                ? route('business.quotes.implementation.show', $publicId, false) : null,
        ];
    }

    public function checkout(User $user, string $publicId, string $hash, string $key): ServiceQuoteCheckout
    {
        $this->ensureAvailable();
        $quote = $this->quotes->quote($user, $publicId);

        return DB::transaction(function () use ($user, $quote, $hash, $key): ServiceQuoteCheckout {
            // Company/subscriber serialize concurrent contracts, including different accepted quotes.
            Company::whereKey($quote->company_id)->lockForUpdate()->firstOrFail();
            Subscriber::whereKey($quote->subscriber_id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('subscriber_user')->where('subscriber_id', $quote->subscriber_id)->where('user_id', $user->id)->lockForUpdate()->get();
            $quote = $this->quotes->quote($user, $quote->public_id);
            $receipt = $this->acceptance($quote);
            abort_unless(hash_equals($receipt->snapshot_hash, $hash), 409, 'Las condiciones no coinciden. Recarga la cotización.');
            $keyOwner = ServiceQuoteCheckout::where('company_id', $quote->company_id)->where('idempotency_key', $key)->first();
            abort_if($keyOwner && (int) $keyOwner->service_quote_acceptance_id !== (int) $receipt->id, 409,
                'Esta solicitud ya corresponde a otra cotización.');
            $existing = ServiceQuoteCheckout::where('service_quote_acceptance_id', $receipt->id)->first();
            if ($existing) {
                $this->invoiceDetails($existing, $receipt);

                return $existing;
            }

            Service::whereIn('id', array_column($receipt->snapshot['products'], 'service_id'))->orderBy('id')->lockForUpdate()->get();
            $reason = $this->unavailableReason($receipt);
            abort_if($reason !== null, 409, $reason ?? 'Facturación no disponible.');
            $snapshot = $receipt->snapshot;
            $amounts = $this->amounts->fromSnapshot($snapshot);
            $number = sprintf('LAUDA-BIZ-%010d', $receipt->id);
            abort_if(Invoice::where('number', $number)->exists(), 409, 'Existe una factura sin su referencia Business. Solicita revisión.');
            $evidence = [
                'source' => 'business_quote_checkout', 'service_quote_acceptance_id' => $receipt->id,
                'quote_public_id' => $quote->public_id, 'subscriber_id' => $receipt->subscriber_id,
                'company_id' => $receipt->company_id, 'snapshot_hash' => $receipt->snapshot_hash,
                'billing_cycle' => $snapshot['billing_cycle'], 'price_snapshot' => $snapshot,
                'entitlement_granted' => false,
            ];
            $invoice = Invoice::create([
                'company_id' => $receipt->company_id, 'subscription_id' => null, 'number' => $number,
                'status' => 'issued', 'issued_on' => now()->toDateString(), 'currency' => $snapshot['currency'],
                'subtotal' => $this->amounts::decimal($amounts['subtotal_cents']), 'discount_total' => '0.00',
                'tax_total' => $this->amounts::decimal($amounts['tax_cents']), 'total' => $this->amounts::decimal($amounts['total_cents']),
                'amount_paid' => '0.00', 'billing_snapshot' => $evidence,
                'fiscal_meta' => ['fiscal_document_pending' => true, 'source' => 'business_quote_checkout'],
            ]);
            foreach ($amounts['lines'] as $kind => $line) {
                // Two contractual totals preserve saved tax rounding and package discounts.
                // Never allocate the package price to individual Services or apply discounts twice.
                InvoiceItem::create([
                    'invoice_id' => $invoice->id, 'service_id' => null,
                    'description' => $snapshot['offer']['name'].' · '.$snapshot['level']['name'].' · '.($kind === 'recurring'
                        ? 'Primer período '.($snapshot['billing_cycle'] === 'yearly' ? 'anual' : 'mensual')
                        : 'Implementación y cargos únicos'),
                    'quantity' => 1, 'unit_price' => $this->amounts::decimal($line['subtotal_cents']),
                    'line_subtotal' => $this->amounts::decimal($line['subtotal_cents']), 'discount_amount' => '0.00',
                    'tax_rate' => $snapshot['tax_rate'], 'tax_amount' => $this->amounts::decimal($line['tax_cents']),
                    'line_total' => $this->amounts::decimal($line['total_cents']),
                    'meta' => ['source' => 'business_quote_checkout', 'kind' => $kind, 'contractual_tax_rate' => $snapshot['tax_rate'],
                        'service_quote_acceptance_id' => $receipt->id, 'snapshot_hash' => $receipt->snapshot_hash,
                        'billing_cycle' => $kind === 'recurring' ? $snapshot['billing_cycle'] : 'one_time'],
                ]);
            }
            $checkout = ServiceQuoteCheckout::create([
                'service_quote_acceptance_id' => $receipt->id, 'company_id' => $receipt->company_id,
                'subscriber_id' => $receipt->subscriber_id, 'invoice_id' => $invoice->id, 'created_by' => $user->id,
                'idempotency_key' => $key, 'snapshot_hash' => $receipt->snapshot_hash,
            ]);
            $this->invoiceDetails($checkout, $receipt);
            AuditLog::create([
                'event' => 'business.quote.checkout_created', 'model_type' => ServiceQuoteCheckout::class,
                'model_id' => $checkout->id, 'user_id' => $user->id,
                'data' => ['quote_public_id' => $quote->public_id, 'invoice_id' => $invoice->id,
                    'snapshot_hash' => $receipt->snapshot_hash, 'total_cents' => $amounts['total_cents']],
            ]);

            return $checkout;
        }, 3);
    }

    public function acceptance(ServiceCommercialQuote $quote): ServiceQuoteAcceptance
    {
        $receipt = $quote->acceptance;
        abort_unless($receipt, 409, 'Primero debes aceptar la cotización.');
        abort_unless((int) $receipt->company_id === (int) $quote->company_id
            && (int) $receipt->subscriber_id === (int) $quote->subscriber_id
            && $receipt->snapshot === $quote->snapshot
            && hash_equals($receipt->snapshot_hash, BusinessQuoteAcceptanceService::fingerprint($quote)),
            409, 'La aceptación requiere revisión antes de facturar.');

        return $receipt;
    }

    private function unavailableReason(ServiceQuoteAcceptance $receipt): ?string
    {
        if (Company::whereKey($receipt->company_id)->value('currency') !== $receipt->snapshot['currency']
            || Subscriber::whereKey($receipt->subscriber_id)->value('currency') !== $receipt->snapshot['currency']) {
            return 'La moneda de la empresa cambió. Solicita una revisión antes de facturar.';
        }
        $services = Service::whereIn('id', array_column($receipt->snapshot['products'], 'service_id'))->get()->keyBy('id');
        foreach ($receipt->snapshot['products'] as $code => $product) {
            $service = $services->get($product['service_id']);
            if (! $service || ! $service->active || ! $service->billable || $service->service_key !== $code) {
                return 'Una solución no está disponible para facturación. Solicita una revisión.';
            }
        }
        if (isset($receipt->snapshot['transition'])) {
            return app(BusinessTransitionService::class)->reason(ServiceCommercialQuote::findOrFail($receipt->service_commercial_quote_id));
        }
        if (SubscriptionItem::whereHas('subscription', fn ($query) => $query->where('subscriber_id', $receipt->subscriber_id)
            ->whereIn('status', ['active', 'trialing']))->activeOrTrialing()->exists()) {
            return 'La empresa tiene servicios activos. Se requiere revisar su transición antes de facturar.';
        }
        if (ServiceQuoteCheckout::where('subscriber_id', $receipt->subscriber_id)->whereHas('invoice', fn ($query) => $query
            ->where(fn ($invoice) => $invoice->where('status', '!=', 'void')->orWhere('amount_paid', '>', 0)))->exists()) {
            return 'Ya existe una contratación Business facturada para este suscriptor. Solicita revisar su transición.';
        }

        if (Invoice::where('company_id', $receipt->company_id)
            ->where('billing_snapshot->source', 'standalone_service_checkout')
            ->where(fn ($invoice) => $invoice->where('status', '!=', 'void')->orWhere('amount_paid', '>', 0))->exists()) {
            return 'Hay facturación de servicios individuales pendiente de revisar antes de contratar Business.';
        }

        return null;
    }

    public function invoiceDetails(ServiceQuoteCheckout $checkout, ServiceQuoteAcceptance $receipt): array
    {
        $invoice = $checkout->invoice()->firstOrFail();
        $amounts = $this->amounts->fromSnapshot($receipt->snapshot);
        abort_unless((int) $invoice->company_id === (int) $receipt->company_id
            && (int) $checkout->company_id === (int) $receipt->company_id
            && (int) $checkout->subscriber_id === (int) $receipt->subscriber_id
            && hash_equals($receipt->snapshot_hash, $checkout->snapshot_hash)
            && data_get($invoice->billing_snapshot, 'snapshot_hash') === $receipt->snapshot_hash
            && data_get($invoice->billing_snapshot, 'source') === 'business_quote_checkout'
            && (int) data_get($invoice->billing_snapshot, 'service_quote_acceptance_id') === (int) $receipt->id
            && (int) data_get($invoice->billing_snapshot, 'subscriber_id') === (int) $receipt->subscriber_id
            && (int) data_get($invoice->billing_snapshot, 'company_id') === (int) $receipt->company_id
            && data_get($invoice->billing_snapshot, 'price_snapshot') === $receipt->snapshot
            && $invoice->currency === $receipt->snapshot['currency']
            && $this->amounts->cents($invoice->subtotal) === $amounts['subtotal_cents']
            && $this->amounts->cents($invoice->discount_total) === 0
            && $this->amounts->cents($invoice->tax_total) === $amounts['tax_cents']
            && $this->amounts->cents($invoice->total) === $amounts['total_cents'],
            409, 'La factura requiere revisión; sus importes o referencias cambiaron.');
        $paid = $this->amounts->cents($invoice->amount_paid);
        $balance = max(0, $amounts['total_cents'] - $paid);
        $url = $invoice->payment_url;
        $parts = is_string($url) ? parse_url($url) : false;
        $canPay = in_array($invoice->status, ['issued', 'overdue'], true) && $balance > 0;
        $safeUrl = $parts && ($parts['scheme'] ?? null) === 'https' && ! empty($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass']) && filter_var($url, FILTER_VALIDATE_URL);

        return ['number' => $invoice->number, 'status' => $invoice->status,
            'issued_on' => $invoice->issued_on?->toDateString(), 'total_cents' => $amounts['total_cents'],
            'paid_cents' => $paid, 'balance_cents' => $balance, 'payment_url' => $canPay && $safeUrl ? $url : null,
            'payments' => $invoice->payments()->whereNotNull('paid_at')->orderBy('paid_at')->get()->map(fn ($payment) => [
                'amount_cents' => $this->amounts->cents($payment->amount), 'paid_at' => $payment->paid_at->toIso8601String(),
            ])->all(),
        ];
    }
}
