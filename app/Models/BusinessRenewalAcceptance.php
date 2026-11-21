<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessRenewalAcceptance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new \LogicException('El consentimiento de renovación es inmutable.');
        });
        self::deleting(function (): void {
            throw new \LogicException('El consentimiento de renovación se conserva.');
        });
    }
}
