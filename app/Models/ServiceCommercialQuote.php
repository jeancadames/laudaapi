<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceCommercialQuote extends Model
{
    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'issued_at' => 'immutable_datetime',
        'valid_until' => 'immutable_datetime',
    ];

    public function acceptance(): HasOne
    {
        return $this->hasOne(ServiceQuoteAcceptance::class);
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Una cotización emitida es inmutable; emita una nueva revisión.');
        });
        static::deleting(function (): void {
            throw new \LogicException('El historial de cotizaciones se conserva.');
        });
    }
}
