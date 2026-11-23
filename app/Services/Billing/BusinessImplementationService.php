<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\BusinessImplementationOrder;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteCheckout;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BusinessImplementationService
{
    public const LABELS = ['queued' => 'Pendiente de inicio', 'in_progress' => 'En ejecución', 'blocked' => 'Bloqueada',
        'delivered' => 'Pendiente de conformidad', 'completed' => 'Finalizada', 'cancelled' => 'Cancelada'];

    private const TRANSITIONS = [
        'start' => ['queued' => 'in_progress'], 'block' => ['in_progress' => 'blocked'],
        'resume' => ['blocked' => 'in_progress'], 'deliver' => ['in_progress' => 'delivered'],
        'complete' => ['delivered' => 'completed'], 'request_changes' => ['delivered' => 'blocked'],
        'cancel' => ['queued' => 'cancelled', 'in_progress' => 'cancelled', 'blocked' => 'cancelled', 'delivered' => 'cancelled'],
    ];

    public function __construct(
        private readonly BusinessQuoteAcceptanceService $quotes,
        private readonly BusinessQuoteCheckoutService $billing,
        private readonly BusinessQuoteInvoiceAmounts $amounts,
    ) {}

    public function ensureAvailable(): void
    {
        $this->billing->ensureAvailable();
        abort_unless(config('business_commercial.implementation_enabled'), 404);
        foreach (['business_implementation_orders', 'business_implementation_events'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'El seguimiento de implementación todavía no está disponible.');
        }
    }

    public function authorizeAdmin(User $actor): void
    {
        abort_unless($actor->role === 'admin' && $actor->hasVerifiedEmail(), 403);
    }

    private function quote(User $actor, string $publicId, bool $admin): ServiceCommercialQuote
    {
        $this->ensureAvailable();
        if (! $admin) {
            return $this->quotes->quote($actor, $publicId);
        }
        $this->authorizeAdmin($actor);

        return ServiceCommercialQuote::where('public_id', $publicId)->firstOrFail();
    }

    public function contract(ServiceCommercialQuote $quote, bool $lock = false): array
    {
        $receipt = $this->billing->acceptance($quote);
        $checkout = ServiceQuoteCheckout::where('service_quote_acceptance_id', $receipt->id)->first();
        abort_unless($checkout, 409, 'Primero debes crear la factura de la cotización aceptada.');
        if ($lock) {
            Invoice::whereKey($checkout->invoice_id)->lockForUpdate()->firstOrFail();
        }
        $invoice = $this->billing->invoiceDetails($checkout, $receipt);
        $order = BusinessImplementationOrder::where('service_quote_checkout_id', $checkout->id)->first();
        if ($order) {
            abort_unless(hash_equals($receipt->snapshot_hash, $order->snapshot_hash)
                && $order->scope_snapshot === $receipt->snapshot, 409, 'El alcance de implementación requiere revisión.');
        }

        return [$receipt, $checkout, $invoice, $order];
    }

    /** Payment is a live gate, not a persisted grant of access or a work status. */
    public function paymentHold(ServiceQuoteCheckout $checkout, array $invoice, bool $lock = false): ?string
    {
        if (! in_array($invoice['status'], ['issued', 'overdue', 'paid'], true)) {
            return 'La factura está anulada o en preparación. Facturación debe revisarla antes de continuar.';
        }
        $payments = $checkout->invoice->payments()->orderBy('id');
        if ($lock) {
            $payments->lockForUpdate();
        }
        $paid = 0;
        foreach ($payments->get() as $payment) {
            if ((int) $payment->company_id !== (int) $checkout->company_id || $payment->currency !== $checkout->invoice->currency) {
                return 'Los pagos requieren conciliación antes de continuar.';
            }
            if ($payment->paid_at !== null) {
                $paid += $this->amounts->cents($payment->amount);
            }
        }
        if ($paid !== $invoice['paid_cents']) {
            return 'Los pagos requieren conciliación antes de continuar.';
        }
        if ($paid < $invoice['total_cents']) {
            return 'La factura debe estar cubierta con pagos confirmados antes de avanzar en la implementación.';
        }

        return null;
    }

    public function identityHold(ServiceCommercialQuote $quote): ?string
    {
        $company = Company::findOrFail($quote->company_id);
        $subscriber = Subscriber::findOrFail($quote->subscriber_id);
        if (! $company->active || ! $subscriber->active || (int) $company->subscriber_id !== (int) $subscriber->id
            || $company->currency !== $quote->snapshot['currency'] || $subscriber->currency !== $quote->snapshot['currency']) {
            return 'La empresa o su suscriptor requieren revisión antes de continuar.';
        }

        return null;
    }

    public function details(User $actor, string $publicId, bool $admin = false): array
    {
        $quote = $this->quote($actor, $publicId, $admin);
        [$receipt, $checkout, $invoice, $order] = $this->contract($quote);
        $hold = $this->identityHold($quote) ?? $this->paymentHold($checkout, $invoice);
        $actions = $order ? array_keys(array_filter(self::TRANSITIONS, fn ($states) => isset($states[$order->status]))) : ['request'];
        $actions = array_values(array_filter($actions, fn ($action) => $this->allowed($action, $admin)
            && (! in_array($action, ['start', 'resume', 'deliver', 'complete'], true) || $hold === null)));
        $guided = ! $admin && $quote->isGuidedPackage() && config('business_commercial.activation_enabled')
            && (! $order || ($order->status === 'completed' && $order->events()->where('version', 1)->where('action', 'complete')->exists()));
        if ($guided && ! $order) {
            $hold ??= app(BusinessContractActivationService::class)->guidedHold($quote, $checkout->id);
            $actions = $hold === null ? ['complete'] : [];
        }
        if (! $order && ($this->identityHold($quote) !== null || ! in_array($invoice['status'], ['issued', 'overdue', 'paid'], true))) {
            $actions = [];
        }

        return [
            'quote' => $this->quotes->details($quote), 'invoice' => $invoice, 'admin' => $admin,
            'activation_url' => config('business_commercial.activation_enabled') ? route($admin ? 'admin.business_activation.show' : 'business.quotes.activation.show', $publicId, false) : null,
            'guided' => $guided,
            'purchase_url' => $guided ? route('business.package.build', ['compra' => $publicId], false) : null,
            'payment_hold' => $hold, 'actions' => $actions,
            'action_url' => route($admin ? 'admin.business_implementations.update' : 'business.quotes.implementation.store', $publicId, false),
            'order' => $order ? [
                'id' => $order->id, 'status' => $order->status, 'status_label' => self::LABELS[$order->status],
                'version' => $order->lock_version, 'assigned_name' => $order->assigned_to ? User::whereKey($order->assigned_to)->value('name') : null,
                'created_at' => $order->created_at->toIso8601String(),
                'events' => $order->events()->orderBy('version')->get()->map(fn ($event) => [
                    'action' => $event->action, 'status_label' => self::LABELS[$event->to_status],
                    'actor_name' => User::whereKey($event->actor_id)->value('name'), 'note' => $event->note,
                    'at' => $event->created_at->toIso8601String(), 'version' => $event->version,
                ])->all(),
            ] : null,
        ];
    }

    private function allowed(string $action, bool $admin): bool
    {
        return in_array($action, $admin ? ['start', 'block', 'resume', 'deliver', 'cancel'] : ['request', 'complete', 'request_changes'], true);
    }

    public function act(User $actor, string $publicId, array $data, bool $admin = false): BusinessImplementationOrder
    {
        $quote = $this->quote($actor, $publicId, $admin);
        abort_unless($this->allowed($data['action'], $admin), 403);

        return DB::transaction(function () use ($actor, $quote, $data, $admin): BusinessImplementationOrder {
            // Same company/subscriber order as checkout; serialize all changes for this contract.
            Company::whereKey($quote->company_id)->lockForUpdate()->firstOrFail();
            Subscriber::whereKey($quote->subscriber_id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            DB::table('subscriber_user')->where('subscriber_id', $quote->subscriber_id)->where('user_id', $actor->id)->lockForUpdate()->get();
            $quote = $this->quote($actor, $quote->public_id, $admin);
            [$receipt, $checkout, $invoice, $order] = $this->contract($quote, true);
            abort_unless(hash_equals($receipt->snapshot_hash, $data['snapshot_hash']), 409, 'El alcance cambió. Recarga la página.');
            $hash = hash('sha256', json_encode([(int) $actor->id, $data['action'], (int) $data['version'], $data['snapshot_hash'], trim($data['note'])], JSON_THROW_ON_ERROR));
            $existing = $order?->events()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Esta solicitud ya se utilizó con otros datos. Recarga la página.');

                return $order;
            }
            $action = $data['action'];
            if ($action === 'request' && $order) {
                return $order;
            }
            abort_unless((int) $data['version'] === ($order?->lock_version ?? 0), 409, 'Otra persona actualizó la implementación. Recarga antes de continuar.');
            $from = $order?->status;
            $guided = ! $order && ! $admin && $quote->isGuidedPackage() && config('business_commercial.activation_enabled');
            abort_if($guided && $action === 'request', 409, 'Completa la vinculación y confirma la implementación guiada.');
            if ($guided && $action === 'complete') {
                $hold = $this->identityHold($quote) ?? $this->paymentHold($checkout, $invoice, true)
                    ?? app(BusinessContractActivationService::class)->guidedHold($quote, $checkout->id);
                abort_if($hold !== null, 409, $hold ?? '');
            }
            if ($action === 'request' || ($guided && $action === 'complete')) {
                abort_if($this->identityHold($quote) !== null, 409, 'La empresa requiere revisión.');
                abort_unless(in_array($invoice['status'], ['issued', 'overdue', 'paid'], true), 409, 'La factura no permite solicitar implementación.');
                $order = BusinessImplementationOrder::create([
                    'service_quote_checkout_id' => $checkout->id, 'created_by' => $actor->id,
                    'snapshot_hash' => $receipt->snapshot_hash, 'scope_snapshot' => $receipt->snapshot,
                    'status' => $guided ? 'completed' : 'queued', 'lock_version' => 1,
                ]);
            } else {
                $to = self::TRANSITIONS[$action][$from ?? ''] ?? null;
                abort_unless($order && $to !== null, 409, 'Esta acción no está disponible en el estado actual.');
                if (in_array($action, ['start', 'resume', 'deliver', 'complete'], true)) {
                    $hold = $this->identityHold($quote) ?? $this->paymentHold($checkout, $invoice, true);
                    abort_if($hold !== null, 409, $hold ?? 'Implementación no disponible.');
                }
                $order->status = $to;
                $order->lock_version++;
                if (in_array($action, ['start', 'resume'], true)) {
                    $order->assigned_to = $actor->id;
                }
                $order->save();
            }
            $event = $order->events()->create([
                'actor_id' => $actor->id, 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
                'action' => $action, 'from_status' => $from, 'to_status' => $order->status,
                'version' => $order->lock_version, 'note' => trim($data['note']),
            ]);
            AuditLog::create([
                'event' => 'business.implementation.'.$action, 'model_type' => BusinessImplementationOrder::class,
                'model_id' => $order->id, 'user_id' => $actor->id,
                'data' => ['event_id' => $event->id, 'invoice_id' => $checkout->invoice_id, 'snapshot_hash' => $receipt->snapshot_hash,
                    'from' => $from, 'to' => $order->status, 'version' => $order->lock_version],
            ]);

            if ($guided && $action === 'complete') {
                app(BusinessContractActivationService::class)->activateGuided($actor, $quote, $order->lock_version, $data['idempotency_key']);
            }

            return $order;
        }, 3);
    }
}
