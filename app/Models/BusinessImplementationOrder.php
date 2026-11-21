<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BusinessImplementationOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['scope_snapshot' => 'array', 'lock_version' => 'integer'];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(ServiceQuoteCheckout::class, 'service_quote_checkout_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BusinessImplementationEvent::class);
    }

    protected static function booted(): void
    {
        self::updating(function (self $order): void {
            if ($order->isDirty(['service_quote_checkout_id', 'created_by', 'snapshot_hash', 'scope_snapshot'])) {
                throw new \LogicException('El alcance y origen de la implementación son inmutables.');
            }
        });
        self::deleting(function (): void {
            throw new \LogicException('El historial de implementación se conserva.');
        });
    }
}
