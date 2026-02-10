<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $table = 'payment_methods';

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'credentials' => 'array',
        'config' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // -------------------------
    // Relationships
    // -------------------------
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    // -------------------------
    // Scopes
    // -------------------------
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // -------------------------
    // Helpers
    // -------------------------
    public function isGateway(): bool
    {
        return ($this->type ?? null) === 'gateway';
    }

    public function isBankTransfer(): bool
    {
        return ($this->type ?? null) === 'bank_transfer';
    }
}
