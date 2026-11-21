<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessRenewalPeriod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_starts_at' => 'immutable_datetime', 'period_ends_at' => 'immutable_datetime',
            'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new \LogicException('El período facturado de renovación es inmutable.');
        });
        self::deleting(function (): void {
            throw new \LogicException('El período facturado de renovación se conserva.');
        });
    }
}
