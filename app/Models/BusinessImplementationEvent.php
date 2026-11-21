<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessImplementationEvent extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new \LogicException('La evidencia de implementación es inmutable.');
        });
        self::deleting(function (): void {
            throw new \LogicException('La evidencia de implementación se conserva.');
        });
    }
}
