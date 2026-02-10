<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Company extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function taxProfile(): HasOne
    {
        return $this->hasOne(\App\Models\CompanyTaxProfile::class);
    }

    protected static function booted(): void
    {
        static::saved(fn() => Cache::forget('admin.dashboard.stats'));
        static::deleted(fn() => Cache::forget('admin.dashboard.stats'));
    }
}
