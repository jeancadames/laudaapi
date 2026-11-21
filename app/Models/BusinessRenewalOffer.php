<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessRenewalOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['terms' => 'array', 'period_starts_at' => 'immutable_datetime', 'period_ends_at' => 'immutable_datetime', 'valid_until' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new \LogicException('Las condiciones de renovación publicadas son inmutables.');
        });
        self::deleting(function (): void {
            throw new \LogicException('El historial de renovación Business se conserva.');
        });
    }
}
