<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\BusinessContract;
use App\Models\BusinessRenewalAcceptance;
use App\Models\BusinessRenewalOffer;
use App\Models\BusinessRenewalPeriod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceCommercialQuote;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

final class BusinessRenewalService
{
    public function __construct(
        private readonly BusinessContractActivationService $activation,
        private readonly BusinessImplementationService $implementation,
        private readonly BusinessQuoteAcceptanceService $quotes,
        private readonly BusinessRenewalTerms $terms,
        private readonly BusinessQuoteInvoiceAmounts $amounts,
    ) {}

    public function ensureAvailable(): void
    {
        $this->activation->ensureAvailable();
        abort_unless(config('business_commercial.renewal_enabled'), 404);
        foreach (['business_renewal_offers', 'business_renewal_acceptances', 'business_renewal_periods'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'La renovación Business todavía no está disponible.');
        }
    }

    private function context(User $actor, string $publicId, bool $admin): array
    {
        $this->ensureAvailable();
        if ($admin) {
            $this->implementation->authorizeAdmin($actor);
            $quote = ServiceCommercialQuote::where('public_id', $publicId)->firstOrFail();
        } else {
            $quote = $this->quotes->quote($actor, $publicId);
        }
        [$receipt, $checkout] = $this->implementation->contract($quote);
        $contract = BusinessContract::where('service_quote_checkout_id', $checkout->id)->first();
        abort_unless($contract, 409, 'Primero debe completarse la activación Business.');
        abort_unless($contract->snapshot === $receipt->snapshot && hash_equals($contract->snapshot_hash, $receipt->snapshot_hash)
            && (int) $contract->company_id === (int) $quote->company_id && (int) $contract->subscriber_id === (int) $quote->subscriber_id,
            409, 'El origen contractual requiere revisión.');

        return [$quote, $contract];
    }

    private function locked(User $actor, string $publicId, bool $admin, callable $action): mixed
    {
        [$quote] = $this->context($actor, $publicId, $admin);

        return DB::transaction(function () use ($actor, $publicId, $admin, $quote, $action): mixed {
            Company::whereKey($quote->company_id)->lockForUpdate()->firstOrFail();
            Subscriber::whereKey($quote->subscriber_id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            DB::table('subscriber_user')->where('subscriber_id', $quote->subscriber_id)->where('user_id', $actor->id)->lockForUpdate()->get();
            Subscription::where('subscriber_id', $quote->subscriber_id)->orderBy('id')->lockForUpdate()->get();
            [$quote, $contract] = $this->context($actor, $publicId, $admin);
            BusinessContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();

            return $action($actor, $quote, $contract);
        }, 3);
    }

    public function assertOffer(BusinessContract $contract, BusinessRenewalOffer $offer): void
    {
        $expected = $this->terms->make($contract, (int) $offer->period_number, $offer->terms);
        abort_unless((int) $offer->business_contract_id === (int) $contract->id
            && hash_equals(BusinessRenewalTerms::hash($expected), BusinessRenewalTerms::hash($offer->terms))
            && hash_equals(BusinessRenewalTerms::hash($expected), $offer->terms_hash)
            && $offer->period_starts_at->equalTo($expected['period_starts_at']) && $offer->period_ends_at->equalTo($expected['period_ends_at']),
            409, 'Las condiciones de renovación requieren revisión.');
    }

    private function nextNumber(BusinessContract $contract): int
    {
        return ((int) BusinessRenewalPeriod::where('business_contract_id', $contract->id)->max('period_number') ?: 1) + 1;
    }

    private function latest(BusinessContract $contract, int $number): ?BusinessRenewalOffer
    {
        return BusinessRenewalOffer::where('business_contract_id', $contract->id)->where('period_number', $number)->orderByDesc('revision')->first();
    }

    private function hold(ServiceCommercialQuote $quote, BusinessContract $contract, bool $lock = false): ?string
    {
        [, $checkout, $invoice] = $this->implementation->contract($quote, $lock);
        $hold = $this->implementation->identityHold($quote) ?? $this->implementation->paymentHold($checkout, $invoice, $lock);
        if ($hold) {
            return $hold;
        }
        if (! config('business_identity.enabled')) {
            return 'La vinculación Business requiere revisión antes de renovar.';
        }
        $subscription = Subscription::findOrFail($contract->subscription_id);
        if ($subscription->status !== 'active' || $subscription->ends_at !== null || $subscription->cancelled_at !== null
            || (int) $subscription->subscriber_id !== (int) $contract->subscriber_id || $subscription->currency !== $contract->snapshot['currency']) {
            return 'La suscripción requiere una revisión antes de renovar.';
        }
        app(BusinessContractPricing::class)->initialLine($subscription, $contract);
        if ($subscription->items()->whereNotIn('status', ['active', 'trialing'])->exists()) {
            return 'Un servicio incluido requiere revisión de transición.';
        }
        if (Subscription::where('subscriber_id', $contract->subscriber_id)->where('id', '!=', $subscription->id)
            ->whereNotIn('status', ['cancelled', 'expired'])->exists()) {
            return 'Existen otras suscripciones abiertas. Se requiere revisar la transición.';
        }
        $maps = DB::table('business_contract_items')->where('business_contract_id', $contract->id)->get()->keyBy('service_id');
        foreach ($contract->snapshot['products'] as $code => $product) {
            $service = Service::whereKey($product['service_id'])->when($lock, fn ($q) => $q->lockForUpdate())->first();
            $map = $maps->get($product['service_id']);
            $link = $map ? DB::table('business_identity_links')->where('id', $map->identity_link_id)->when($lock, fn ($q) => $q->lockForUpdate())->first() : null;
            if (! $service || ! $service->active || ! $service->billable || $service->service_key !== $code || ! $link || ! $link->active
                || $link->service !== $code || (int) $link->central_company_id !== (int) $contract->company_id
                || (int) $link->central_subscriber_id !== (int) $contract->subscriber_id || (int) $link->local_company_id !== (int) $map->local_company_id) {
                return 'Una solución o su vínculo empresarial requiere revisión antes de renovar.';
            }
        }
        foreach (BusinessRenewalPeriod::where('business_contract_id', $contract->id)->orderBy('period_number')->get() as $period) {
            $bill = $this->invoiceDetails($contract, $period, $lock);
            if (! $bill['covered']) {
                return 'Una factura de renovación requiere pago o revisión.';
            }
        }

        return null;
    }

    public function propose(User $actor, string $publicId, array $data): BusinessRenewalOffer
    {
        Validator::make($data, ['confirmed' => 'required|accepted', 'idempotency_key' => 'required|uuid',
            'period_number' => 'required|integer|min:2|max:1200', 'expected_revision' => 'required|integer|min:0',
            'valid_until' => 'required|date_format:Y-m-d\TH:i:sP'])->validate();

        return $this->locked($actor, $publicId, true, function ($actor, $quote, $contract) use ($data): BusinessRenewalOffer {
            $terms = $this->terms->make($contract, (int) $data['period_number'], $data);
            $valid = CarbonImmutable::parse($data['valid_until'])->utc();
            $hash = BusinessRenewalTerms::hash([$actor->id, $terms, $valid->toIso8601String(), (int) $data['expected_revision']]);
            $existing = BusinessRenewalOffer::where('business_contract_id', $contract->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Esta solicitud ya se utilizó con otras condiciones.');
                $this->assertOffer($contract, $existing);

                return $existing;
            }
            $hold = $this->hold($quote, $contract, true);
            abort_if($hold !== null, 409, $hold ?? 'Renovación en revisión.');
            abort_unless($this->nextNumber($contract) === (int) $data['period_number'], 409, 'El período cambió. Recarga la revisión.');
            $latest = $this->latest($contract, (int) $data['period_number']);
            abort_unless(($latest?->revision ?? 0) === (int) $data['expected_revision'], 409, 'Hay una propuesta más reciente. Recarga la revisión.');
            abort_if(BusinessRenewalAcceptance::where('business_contract_id', $contract->id)->where('period_number', $data['period_number'])->exists(),
                409, 'Este período ya fue aceptado. Sus condiciones se conservan.');
            abort_unless(BusinessRenewalTerms::boundary($contract, (int) $data['period_number'] - 2)->lessThanOrEqualTo(now()),
                409, 'Ya hay un período futuro facturado. Espera a su inicio antes de proponer otro.');
            abort_unless($valid->greaterThan(now()) && $valid->lessThanOrEqualTo($terms['period_ends_at']), 422,
                'La vigencia debe ser futura y terminar como máximo al finalizar el período propuesto.');
            $offer = BusinessRenewalOffer::create(['business_contract_id' => $contract->id, 'period_number' => $data['period_number'],
                'revision' => ($latest?->revision ?? 0) + 1, 'period_starts_at' => CarbonImmutable::parse($terms['period_starts_at']),
                'period_ends_at' => CarbonImmutable::parse($terms['period_ends_at']), 'valid_until' => $valid,
                'terms' => $terms, 'terms_hash' => BusinessRenewalTerms::hash($terms), 'offered_by' => $actor->id,
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash]);
            $this->audit('offered', $offer, $actor, $offer->terms_hash);

            return $offer;
        });
    }

    private function selection(BusinessContract $contract, array $data): BusinessRenewalOffer
    {
        $offer = BusinessRenewalOffer::where('business_contract_id', $contract->id)->whereKey($data['offer_id'])->firstOrFail();
        $this->assertOffer($contract, $offer);
        abort_unless(hash_equals($offer->terms_hash, $data['terms_hash']), 409, 'Las condiciones cambiaron. Recarga antes de confirmar.');

        return $offer;
    }

    private function validateConsent(array $data): void
    {
        Validator::make($data, ['confirmed' => 'required|accepted', 'idempotency_key' => 'required|uuid',
            'offer_id' => 'required|integer|min:1', 'terms_hash' => 'required|string|regex:/^[a-f0-9]{64}$/D'])->validate();
    }

    public function accept(User $actor, string $publicId, array $data): BusinessRenewalAcceptance
    {
        $this->validateConsent($data);

        return $this->locked($actor, $publicId, false, function ($actor, $quote, $contract) use ($data): BusinessRenewalAcceptance {
            $offer = $this->selection($contract, $data);
            $keyOwner = BusinessRenewalAcceptance::where('business_contract_id', $contract->id)->where('idempotency_key', $data['idempotency_key'])->first();
            abort_if($keyOwner && (int) $keyOwner->business_renewal_offer_id !== (int) $offer->id, 409, 'Esta solicitud corresponde a otra propuesta.');
            $existing = BusinessRenewalAcceptance::where('business_renewal_offer_id', $offer->id)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->terms_hash, $offer->terms_hash), 409, 'La aceptación requiere revisión.');

                return $existing;
            }
            $hold = $this->hold($quote, $contract, true);
            abort_if($hold !== null, 409, $hold ?? 'Renovación en revisión.');
            abort_unless($this->nextNumber($contract) === (int) $offer->period_number
                && $this->latest($contract, $offer->period_number)?->id === $offer->id && $offer->valid_until->greaterThan(now()),
                409, 'La propuesta venció o fue sustituida. Solicita una revisión.');
            $receipt = BusinessRenewalAcceptance::create(['business_renewal_offer_id' => $offer->id, 'business_contract_id' => $contract->id,
                'period_number' => $offer->period_number, 'terms_hash' => $offer->terms_hash, 'accepted_by' => $actor->id,
                'accepted_name' => $actor->name, 'accepted_at' => now(), 'idempotency_key' => $data['idempotency_key']]);
            $this->audit('accepted', $receipt, $actor, $offer->terms_hash);

            return $receipt;
        });
    }

    public function issue(User $actor, string $publicId, array $data): BusinessRenewalPeriod
    {
        $this->validateConsent($data);

        return $this->locked($actor, $publicId, true, function ($actor, $quote, $contract) use ($data): BusinessRenewalPeriod {
            $offer = $this->selection($contract, $data);
            $receipt = BusinessRenewalAcceptance::where('business_renewal_offer_id', $offer->id)->first();
            abort_unless($receipt && (int) $receipt->business_contract_id === (int) $contract->id
                && (int) $receipt->period_number === (int) $offer->period_number && hash_equals($receipt->terms_hash, $offer->terms_hash),
                409, 'La empresa debe aceptar estas condiciones antes de facturar.');
            $keyOwner = BusinessRenewalPeriod::where('business_contract_id', $contract->id)->where('idempotency_key', $data['idempotency_key'])->first();
            abort_if($keyOwner && (int) $keyOwner->business_renewal_acceptance_id !== (int) $receipt->id, 409, 'Esta solicitud corresponde a otra factura.');
            $existing = BusinessRenewalPeriod::where('business_renewal_acceptance_id', $receipt->id)->first();
            if ($existing) {
                $this->invoiceDetails($contract, $existing, true);

                return $existing;
            }
            $hold = $this->hold($quote, $contract, true);
            abort_if($hold !== null, 409, $hold ?? 'Renovación en revisión.');
            abort_unless($this->nextNumber($contract) === (int) $offer->period_number && $offer->period_ends_at->greaterThan(now()),
                409, 'El período ya terminó o fue facturado. Solicita revisión comercial.');
            $terms = $offer->terms;
            $number = sprintf('LAUDA-BIZ-R-%010d-%04d', $contract->id, $offer->period_number);
            abort_if(Invoice::where('number', $number)->exists(), 409, 'Ya existe una factura sin su referencia de renovación.');
            $invoice = Invoice::create(['company_id' => $contract->company_id, 'subscription_id' => $contract->subscription_id,
                'number' => $number, 'status' => 'issued', 'issued_on' => now($contract->timezone)->toDateString(),
                'period_start' => $offer->period_starts_at, 'period_end' => $offer->period_ends_at, 'currency' => $terms['currency'],
                'subtotal' => $this->amounts::decimal($terms['subtotal_cents']), 'discount_total' => '0.00',
                'tax_total' => $this->amounts::decimal($terms['tax_cents']), 'total' => $this->amounts::decimal($terms['total_cents']), 'amount_paid' => '0.00',
                'billing_snapshot' => ['source' => 'business_renewal', 'contract_id' => $contract->id, 'acceptance_id' => $receipt->id,
                    'terms_hash' => $offer->terms_hash, 'terms' => $terms],
                'fiscal_meta' => ['fiscal_document_pending' => true, 'source' => 'business_renewal']]);
            InvoiceItem::create(['invoice_id' => $invoice->id, 'service_id' => null,
                'description' => $contract->snapshot['offer']['name'].' · Renovación del período '.$offer->period_number,
                'quantity' => 1, 'unit_price' => $invoice->subtotal, 'line_subtotal' => $invoice->subtotal, 'discount_amount' => '0.00',
                'tax_rate' => $terms['tax_bps'] / 10000, 'tax_amount' => $invoice->tax_total, 'line_total' => $invoice->total,
                'meta' => ['source' => 'business_renewal', 'terms_hash' => $offer->terms_hash, 'tax_bps' => $terms['tax_bps'], 'discount_included_cents' => $terms['discount_cents']]]);
            $period = BusinessRenewalPeriod::create(['business_contract_id' => $contract->id, 'business_renewal_acceptance_id' => $receipt->id,
                'invoice_id' => $invoice->id, 'period_number' => $offer->period_number,
                'period_starts_at' => $offer->period_starts_at, 'period_ends_at' => $offer->period_ends_at, 'terms_hash' => $offer->terms_hash,
                'subtotal' => $invoice->subtotal, 'tax_total' => $invoice->tax_total, 'total' => $invoice->total,
                'issued_by' => $actor->id, 'idempotency_key' => $data['idempotency_key']]);
            $this->invoiceDetails($contract, $period, true);
            $this->audit('invoiced', $period, $actor, $offer->terms_hash);

            return $period;
        });
    }

    public function invoiceDetails(BusinessContract $contract, BusinessRenewalPeriod $period, bool $lock = false): array
    {
        $receipt = BusinessRenewalAcceptance::findOrFail($period->business_renewal_acceptance_id);
        $offer = BusinessRenewalOffer::findOrFail($receipt->business_renewal_offer_id);
        $this->assertOffer($contract, $offer);
        $invoice = Invoice::whereKey($period->invoice_id)->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
        $terms = $offer->terms;
        abort_unless((int) $receipt->business_contract_id === (int) $contract->id && (int) $period->business_contract_id === (int) $contract->id
            && (int) $receipt->period_number === (int) $offer->period_number && (int) $period->period_number === (int) $offer->period_number
            && hash_equals($receipt->terms_hash, $offer->terms_hash) && hash_equals($period->terms_hash, $offer->terms_hash)
            && (int) $invoice->company_id === (int) $contract->company_id && (int) $invoice->subscription_id === (int) $contract->subscription_id
            && $invoice->currency === $terms['currency'] && $period->period_starts_at->equalTo($offer->period_starts_at)
            && $period->period_ends_at->equalTo($offer->period_ends_at) && $invoice->period_start?->equalTo($offer->period_starts_at)
            && $invoice->period_end?->equalTo($offer->period_ends_at)
            && hash_equals(BusinessRenewalTerms::hash($invoice->billing_snapshot ?? []), BusinessRenewalTerms::hash([
                'source' => 'business_renewal', 'contract_id' => $contract->id, 'acceptance_id' => $receipt->id,
                'terms_hash' => $offer->terms_hash, 'terms' => $terms]))
            && $this->amounts->cents($invoice->discount_total) === 0
            && $this->amounts->cents($invoice->subtotal) === $terms['subtotal_cents'] && $invoice->subtotal === $period->subtotal
            && $this->amounts->cents($invoice->tax_total) === $terms['tax_cents'] && $invoice->tax_total === $period->tax_total
            && $this->amounts->cents($invoice->total) === $terms['total_cents'] && $invoice->total === $period->total,
            409, 'La factura de renovación requiere revisión; sus importes o referencias cambiaron.');
        $lines = $invoice->items()->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $line = $lines->first();
        abort_unless($lines->count() === 1 && $line->service_id === null && (int) $line->quantity === 1
            && $line->unit_price === $invoice->subtotal && $line->line_subtotal === $invoice->subtotal
            && $line->tax_amount === $invoice->tax_total && $line->line_total === $invoice->total && $this->amounts->cents($line->discount_amount) === 0
            && data_get($line->meta, 'source') === 'business_renewal' && data_get($line->meta, 'terms_hash') === $offer->terms_hash,
            409, 'El detalle de la factura de renovación requiere revisión.');
        $paid = 0;
        $consistent = true;
        foreach ($invoice->payments()->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get() as $payment) {
            $consistent = $consistent && (int) $payment->company_id === (int) $invoice->company_id && $payment->currency === $invoice->currency;
            if ($payment->paid_at !== null) {
                $paid += $this->amounts->cents($payment->amount);
            }
        }
        $covered = $consistent && $paid === $this->amounts->cents($invoice->amount_paid) && $paid >= $terms['total_cents']
            && in_array($invoice->status, ['issued', 'overdue', 'paid'], true);

        return ['number' => $invoice->number, 'status' => $invoice->status, 'total_cents' => $terms['total_cents'],
            'paid_cents' => $paid, 'balance_cents' => max(0, $terms['total_cents'] - $paid), 'covered' => $covered];
    }

    private function audit(string $action, $model, User $actor, string $hash): void
    {
        AuditLog::create(['event' => 'business.renewal.'.$action, 'model_type' => $model::class, 'model_id' => $model->id,
            'user_id' => $actor->id, 'data' => ['business_contract_id' => $model->business_contract_id,
                'period_number' => $model->period_number, 'terms_hash' => $hash]]);
    }

    public function details(User $actor, string $publicId, bool $admin = false): array
    {
        [$quote, $contract] = $this->context($actor, $publicId, $admin);
        $hold = $this->hold($quote, $contract);
        $number = $this->nextNumber($contract);
        $latest = $this->latest($contract, $number);
        $accepted = BusinessRenewalAcceptance::where('business_contract_id', $contract->id)->where('period_number', $number)->exists();
        $offers = BusinessRenewalOffer::where('business_contract_id', $contract->id)->orderByDesc('id')->limit(20)->get()->map(function ($offer) use ($contract, $number, $latest, $hold, $admin): array {
            $this->assertOffer($contract, $offer);
            $receipt = BusinessRenewalAcceptance::where('business_renewal_offer_id', $offer->id)->first();
            $period = $receipt ? BusinessRenewalPeriod::where('business_renewal_acceptance_id', $receipt->id)->first() : null;
            $available = (int) $offer->period_number === $number && $latest?->id === $offer->id && $hold === null;

            return ['id' => $offer->id, 'revision' => $offer->revision, 'terms' => $offer->terms, 'terms_hash' => $offer->terms_hash,
                'valid_until' => $offer->valid_until->toIso8601String(), 'offered_at' => $offer->created_at->toIso8601String(),
                'superseded' => ! $receipt && $this->latest($contract, $offer->period_number)?->id !== $offer->id,
                'acceptance' => $receipt ? ['name' => $receipt->accepted_name, 'at' => $receipt->accepted_at->toIso8601String()] : null,
                'invoice' => $period ? $this->invoiceDetails($contract, $period) : null,
                'can_accept' => ! $admin && ! $receipt && $available && $offer->valid_until->greaterThan(now()),
                'can_invoice' => $admin && $receipt && ! $period && $available && $offer->period_ends_at->greaterThan(now())];
        })->all();

        return ['company_name' => Company::whereKey($contract->company_id)->value('name'), 'currency' => $contract->snapshot['currency'],
            'timezone' => $contract->timezone, 'admin' => $admin, 'hold' => $hold, 'offers' => $offers,
            'current_period' => BusinessContractPeriods::display($contract),
            'can_propose' => $admin && $hold === null && ! $accepted && BusinessRenewalTerms::boundary($contract, $number)->greaterThan(now())
                && BusinessRenewalTerms::boundary($contract, $number - 2)->lessThanOrEqualTo(now()),
            'next' => ['period_number' => $number, 'revision' => $latest?->revision ?? 0,
                'period_starts_at' => BusinessRenewalTerms::boundary($contract, $number - 1)->toIso8601String(),
                'period_ends_at' => BusinessRenewalTerms::boundary($contract, $number)->toIso8601String()],
            'back_url' => route($admin ? 'admin.business_activation.show' : 'business.quotes.activation.show', $publicId, false),
            'propose_url' => $admin ? route('admin.business_renewal.propose', $publicId, false) : null,
            'accept_url' => ! $admin ? route('business.quotes.renewal.accept', $publicId, false) : null,
            'invoice_url' => $admin ? route('admin.business_renewal.invoice', $publicId, false) : null];
    }
}
