<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessTransition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'executed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('La transición Business es inmutable.'));
        self::deleting(fn () => throw new \LogicException('El historial de transición Business se conserva.'));
    }
}
