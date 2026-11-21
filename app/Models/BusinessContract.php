<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessContract extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'technical_evidence' => 'array', 'activated_at' => 'immutable_datetime',
            'period_ends_at' => 'immutable_datetime', 'initial_invoice_total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new \LogicException('La evidencia de activación Business es inmutable.');
        });
        self::deleting(function (): void {
            throw new \LogicException('El historial contractual Business se conserva.');
        });
    }
}
