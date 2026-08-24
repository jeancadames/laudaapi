<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisExpandedReportOrder extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_INVOICED,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'diagnosis_assessment_id',
        'user_id',
        'contact_request_id',
        'subscriber_id',
        'company_id',
        'invoice_id',
        'status',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'requested_at',
        'invoiced_at',
        'paid_at',
        'cancelled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'requested_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosisAssessment::class,
            'diagnosis_assessment_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID
            && $this->paid_at !== null;
    }
}
