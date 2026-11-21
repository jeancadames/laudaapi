<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCatalogVersion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'version' => 'integer',
        'lock_version' => 'integer',
        'effective_at' => 'immutable_datetime',
        'published_at' => 'immutable_datetime',
        'retired_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') !== 'draft' && $version->isDirty(['payload', 'effective_at', 'service_catalog_id', 'version'])) {
                throw new \LogicException('Las condiciones publicadas son inmutables; cree otra revisión.');
            }
        });
        static::deleting(function (): void {
            throw new \LogicException('El historial del catálogo se conserva mediante retiro.');
        });
    }
}
