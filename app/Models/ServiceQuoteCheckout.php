<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceQuoteCheckout extends Model
{
    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new \LogicException('La referencia de facturación Business es inmutable.');
        });
        self::deleting(function (): void {
            throw new \LogicException('El historial de facturación Business se conserva.');
        });
    }
}
