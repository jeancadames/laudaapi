<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DgiiCompanySetting extends Model
{
    protected $table = 'dgii_company_settings';

    protected $fillable = [
        'company_id',
        'environment',
        'cf_prefix',
        'use_directory',
        'endpoints',
        'meta',

        // token control
        'dgii_token_auto',
        'dgii_token_refresh_before_seconds',

        // token data
        'dgii_access_token',
        'dgii_token_issued_at',
        'dgii_token_expires_at',

        // debug
        'dgii_token_last_requested_at',
        'dgii_token_last_error',
    ];

    protected $casts = [
        'use_directory' => 'boolean',
        'endpoints' => 'array',
        'meta' => 'array',

        'dgii_token_auto' => 'boolean',
        'dgii_token_refresh_before_seconds' => 'integer',

        // ✅ token cifrado en DB (Laravel encripta/descifra automático)
        'dgii_access_token' => 'encrypted',

        'dgii_token_issued_at' => 'datetime',
        'dgii_token_expires_at' => 'datetime',
        'dgii_token_last_requested_at' => 'datetime',
    ];


    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tokenSecondsLeft(): int
    {
        if (!$this->dgii_token_expires_at) return 0;

        $diff = now()->diffInSeconds($this->dgii_token_expires_at, false);
        return max(0, (int)$diff);
    }

    public function tokenStatus(): string
    {
        if (!$this->dgii_access_token || !$this->dgii_token_expires_at) return 'expired';
        if (now()->gte($this->dgii_token_expires_at)) return 'expired';

        $s = $this->tokenSecondsLeft();
        return $s > 600 ? 'green' : ($s > 120 ? 'yellow' : 'red');
    }

    public function isTokenValid(int $skewSeconds = 90): bool
    {
        if (!$this->dgii_access_token || !$this->dgii_token_expires_at) return false;

        // ✅ respeta el “refresh_before” configurado por compañía
        $refreshBefore = (int) ($this->dgii_token_refresh_before_seconds ?? 0);

        // ✅ mínimo de seguridad (clock skew/red/colas)
        $effectiveSkew = max(120, $refreshBefore, $skewSeconds);

        return now()->lt($this->dgii_token_expires_at->copy()->subSeconds($effectiveSkew));
    }
}
