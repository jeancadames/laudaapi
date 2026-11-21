<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ServiceQuoteAcceptance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'accepted_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new \LogicException('La aceptación y sus condiciones son inmutables.');
        });
        self::deleting(function (): void {
            throw new \LogicException('El historial de aceptación se conserva.');
        });
    }
}
