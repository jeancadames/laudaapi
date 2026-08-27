<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandaloneServiceSettlement extends Model
{
    public const STATUS_PENDING_PAYMENT =
        'pending_payment';

    public const STATUS_ACTIVATED =
        'activated';

    public const STATUS_FAILED =
        'failed';

    public const STATUS_REVOKED =
        'revoked';

    protected $fillable = [
        'activation_request_service_id',
        'activation_request_id',
        'subscriber_id',
        'company_id',
        'service_id',
        'service_plan_id',
        'invoice_id',
        'invoice_item_id',
        'subscription_id',
        'subscription_item_id',
        'status',
        'billing_cycle',
        'currency',
        'amount_due',
        'amount_paid',
        'settled_at',
        'activated_at',
        'revoked_at',
        'failure_reason',
        'evidence_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'service_plan_id' => 'integer',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'settled_at' => 'datetime',
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'evidence_snapshot' => 'array',
        ];
    }

    public function activationRequest(): BelongsTo
    {
        return $this->belongsTo(
            ActivationRequest::class
        );
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(
            Subscriber::class
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class
        );
    }

    public function servicePlan(): BelongsTo
    {
        return $this->belongsTo(
            ServicePlan::class,
            'service_plan_id'
        );
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            Invoice::class
        );
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(
            InvoiceItem::class
        );
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            Subscription::class
        );
    }

    public function subscriptionItem(): BelongsTo
    {
        return $this->belongsTo(
            SubscriptionItem::class
        );
    }
}
