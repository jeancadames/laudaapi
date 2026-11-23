<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteCheckout;
use App\Models\User;
use App\Services\Subscribers\CompanyContextResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class BusinessTransferProofs
{
    public function __construct(
        private readonly BusinessQuoteCheckoutService $billing,
        private readonly BusinessQuoteAcceptanceService $quotes,
    ) {}

    public static function available(): bool
    {
        return config('business_commercial.enabled') && config('business_commercial.checkout_enabled')
            && Schema::hasTable('business_transfer_proofs') && Schema::hasTable('payment_transactions');
    }

    public function form(ServiceCommercialQuote $quote, ServiceQuoteCheckout $checkout): ?array
    {
        if (! isset($quote->snapshot['self_service']) || ! self::available()) {
            return null;
        }
        $latest = DB::table('business_transfer_proofs')->where('invoice_id', $checkout->invoice_id)->latest('id')->first();

        return ['instructions' => config('business_commercial.transfer_instructions'),
            'status' => $latest?->status, 'reason' => $latest?->reason,
            'submit_url' => route('business.package.proof', $quote->public_id, false)];
    }

    public function submit(User $actor, string $publicId, UploadedFile $file, string $reference, string $key): void
    {
        abort_unless(self::available(), 503);
        $quote = $this->quotes->quote($actor, $publicId);
        abort_unless($actor->role === 'subscriber' && isset($quote->snapshot['self_service'])
            && app(CompanyContextResolver::class)->resolve($actor)?->id === $quote->company_id, 404);
        $receipt = $this->billing->acceptance($quote);
        $checkout = ServiceQuoteCheckout::where('service_quote_acceptance_id', $receipt->id)->firstOrFail();
        $hash = hash_file('sha256', $file->getRealPath());
        $path = null;
        try {
            DB::transaction(function () use ($actor, $quote, $checkout, $receipt, $file, $reference, $key, $hash, &$path): void {
                Invoice::whereKey($checkout->invoice_id)->lockForUpdate()->firstOrFail();
                $this->quotes->quote($actor, $quote->public_id);
                $rows = DB::table('business_transfer_proofs')->where('invoice_id', $checkout->invoice_id);
                $existing = (clone $rows)->where('submission_key', $key)->first();
                if ($existing) {
                    if ($existing->reference !== $reference || $existing->file_hash !== $hash) {
                        throw ValidationException::withMessages(['proof' => 'Este envío corresponde a otro comprobante. Actualiza la factura.']);
                    }

                    return;
                }
                if ((clone $rows)->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['proof' => 'LAUDA ya está revisando un comprobante para esta factura.']);
                }
                $invoice = $this->billing->invoiceDetails($checkout, $receipt);
                abort_unless(in_array($invoice['status'], ['issued', 'overdue'], true) && $invoice['balance_cents'] > 0, 409, 'La factura ya no admite pagos.');
                $path = $file->store('business/transfers', 'private');
                abort_unless($path, 503, 'No se pudo guardar el archivo. Vuelve a intentarlo.');
                $rows->insert(['invoice_id' => $checkout->invoice_id, 'submission_key' => $key, 'submitted_by' => $actor->id,
                    'reference' => $reference, 'file_path' => $path, 'file_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $e;
        }
    }

    public function review(User $actor, int $proofId, array $data): void
    {
        abort_unless(self::available(), 503);
        abort_unless($actor->hasVerifiedEmail() && $actor->role === 'admin', 403);
        $proofs = DB::table('business_transfer_proofs');
        $invoiceId = (clone $proofs)->where('id', $proofId)->value('invoice_id');
        abort_if($invoiceId === null, 404);

        DB::transaction(function () use ($actor, $data, $proofs, $proofId, $invoiceId): void {
            $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            $proof = (clone $proofs)->where('id', $proofId)->lockForUpdate()->first();
            $approve = $data['decision'] === 'approved';
            if ($proof->status !== 'pending') {
                $same = $approve ? (int) $data['amount_cents'] === app(BusinessQuoteInvoiceAmounts::class)->cents(Payment::find($proof->payment_id)?->amount ?? 0)
                    : $proof->reason === $data['reason'];
                abort_unless($proof->status === $data['decision'] && $same, 409, 'Este comprobante ya fue revisado.');

                return;
            }
            $paymentId = null;
            if ($approve) {
                $quote = ServiceCommercialQuote::where('public_id', data_get($invoice->billing_snapshot, 'quote_public_id'))
                    ->whereNotNull('snapshot->self_service')->firstOrFail();
                $receipt = $this->billing->acceptance($quote);
                $checkout = ServiceQuoteCheckout::where('invoice_id', $invoice->id)->where('service_quote_acceptance_id', $receipt->id)->sole();
                $bill = $this->billing->invoiceDetails($checkout, $receipt);
                $amount = (int) $data['amount_cents'];
                if (! in_array($bill['status'], ['issued', 'overdue'], true) || $amount <= 0 || $amount > $bill['balance_cents']) {
                    throw ValidationException::withMessages(['amount_cents' => 'El importe verificado debe estar dentro del saldo pendiente.']);
                }
                $transferKey = 'business-bank-'.hash('sha256', mb_strtoupper(trim($proof->reference)));
                if (PaymentTransaction::where('idempotency_key', $transferKey)->exists()) {
                    throw ValidationException::withMessages(['reference' => 'Esta referencia bancaria ya tiene un pago confirmado.']);
                }
                $decimal = BusinessQuoteInvoiceAmounts::decimal($amount);
                $payment = Payment::create(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id,
                    'method' => 'bank_transfer', 'reference' => $proof->reference, 'amount' => $decimal,
                    'currency' => $invoice->currency, 'paid_at' => now(), 'meta' => ['business_transfer_proof_id' => $proofId, 'reviewed_by' => $actor->id]]);
                try {
                    PaymentTransaction::create(['payable_type' => Invoice::class, 'payable_id' => $invoice->id,
                        'company_id' => $invoice->company_id, 'user_id' => $proof->submitted_by, 'provider' => 'manual',
                        'provider_mode' => 'live', 'provider_transaction_id' => $proof->reference, 'status' => 'succeeded',
                        'payment_method' => 'bank_transfer', 'amount' => $decimal, 'currency' => $invoice->currency,
                        'paid_at' => $payment->paid_at, 'idempotency_key' => $transferKey,
                        'metadata' => ['payment_id' => $payment->id, 'proof_id' => $proofId, 'reviewed_by' => $actor->id]]);
                } catch (UniqueConstraintViolationException $error) {
                    if (! PaymentTransaction::where('idempotency_key', $transferKey)->lockForUpdate()->exists()) {
                        throw $error;
                    }
                    throw ValidationException::withMessages(['reference' => 'Esta referencia bancaria ya tiene un pago confirmado.']);
                }
                $paymentId = $payment->id;
            }
            (clone $proofs)->where('id', $proofId)->update(['status' => $data['decision'], 'reviewed_by' => $actor->id,
                'payment_id' => $paymentId, 'reason' => $approve ? null : $data['reason'], 'updated_at' => now()]);
        }, 3);
    }
}
