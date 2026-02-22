<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFiscalSignal extends Model
{
    protected $table = 'company_fiscal_signals';

    protected $fillable = [
        'company_id',
        'source_module',
        'event_type',
        'source_ref',
        'period_key',
        'occurred_at',
        'payload',
        'payload_hash',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
