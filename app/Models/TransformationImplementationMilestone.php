<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationImplementationMilestone extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transformation_implementation_phase_id',
        'sequence',
        'name',
        'description',
        'modality',
        'modality_label',
        'billing_percentage',
        'billing_amount',
        'currency',
        'billing_status',
        'due_at',
        'ready_to_invoice_at',
        'invoice_reference',
        'invoice_issued_at',
        'payment_reference',
        'paid_at',
        'scope_snapshot',
        'internal_notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'billing_percentage' => 'decimal:4',
            'billing_amount' => 'decimal:2',
            'due_at' => 'datetime',
            'ready_to_invoice_at' => 'datetime',
            'invoice_issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'scope_snapshot' => 'array',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPhase::class,
            'transformation_implementation_phase_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
