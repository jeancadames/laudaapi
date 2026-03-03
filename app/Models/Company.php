<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CompanyTaxProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
        return $this->hasOne(CompanyTaxProfile::class);
    }

    protected static function booted(): void
    {
        // ✅ genera el ws_subdomain una sola vez al crear
        static::created(function (Company $company) {
            if (!empty($company->ws_subdomain)) {
                return;
            }

            $company->ws_subdomain = self::generateWsSubdomain($company);
            $company->saveQuietly(); // evita loops y evita disparar saved()
        });

        // lo tuyo
        static::saved(fn() => Cache::forget('admin.dashboard.stats'));
        static::deleted(fn() => Cache::forget('admin.dashboard.stats'));
    }

    private static function generateWsSubdomain(Company $company): string
    {
        // Base: usa slug sí o sí (ya es único y "DNS-friendly")
        $base = Str::slug($company->slug ?: $company->name, '-');
        $base = strtolower($base);

        // Reservados típicos (no uses www, api, etc.)
        $reserved = ['www', 'api', 'app', 'erp', 'mail', 'smtp', 'ftp', 'admin', 'test', 'dev', 'staging'];
        if ($base === '' || in_array($base, $reserved, true)) {
            $base = 'company';
        }

        // Garantiza unicidad: sufijo con ID
        $suffix = '-' . $company->id;

        // DNS label max 63
        $maxBaseLen = 63 - strlen($suffix);
        $base = substr($base, 0, max(1, $maxBaseLen));
        $base = trim($base, '-');
        if ($base === '') $base = 'c';

        return $base . $suffix; // ej: "demo-1"
    }
}
