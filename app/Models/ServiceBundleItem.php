<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBundleItem extends Model
{
    protected $fillable = [
        'bundle_service_id',
        'included_service_id',
        'required',
        'sort_order',
    ];

    protected $casts = [
        'bundle_service_id' => 'integer',
        'included_service_id' => 'integer',
        'required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function bundleService(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'bundle_service_id'
        );
    }

    public function includedService(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'included_service_id'
        );
    }
}
