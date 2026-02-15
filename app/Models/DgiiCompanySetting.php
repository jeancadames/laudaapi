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
        return now()->lt($this->dgii_token_expires_at->copy()->subSeconds($skewSeconds));
    }
}

class DgiiTokenManager
{
    public function ensureValidToken(DgiiCompanySetting $setting, bool $force = false): void
    {
        // si auto está apagado y no es force, no hace nada
        if (!$force && !$setting->dgii_token_auto) {
            return;
        }

        // si ya es válido, no hace nada (a menos que force)
        if (!$force && $setting->isTokenValid()) {
            return;
        }

        // ✅ aquí llamas a DGII y obtienes token + expiresAt (1 hora)
        // $token = ...
        // $issuedAt = now();
        // $expiresAt = now()->addSeconds(3600); // o la exp real que devuelva DGII

        // EJEMPLO: reemplaza con tu implementación real
        [$token, $issuedAt, $expiresAt] = $this->requestTokenFromDgii($setting);

        $setting->update([
            'dgii_access_token' => $token,
            'dgii_token_issued_at' => $issuedAt,
            'dgii_token_expires_at' => $expiresAt,
            'dgii_token_last_requested_at' => now(),
            'dgii_token_last_error' => null,
        ]);
    }

    private function requestTokenFromDgii(DgiiCompanySetting $setting): array
    {
        // TODO: tu lógica real (endpoints + credenciales + http client)
        throw new \RuntimeException('Implement requestTokenFromDgii()');
    }

    public function getTokenStatus(DgiiCompanySetting $setting): array
    {
        return [
            'auto' => (bool) $setting->dgii_token_auto,
            'status' => $setting->tokenStatus(),
            'secondsLeft' => $setting->tokenSecondsLeft(),
            'expiresAt' => optional($setting->dgii_token_expires_at)->toISOString(),
            'lastError' => $setting->dgii_token_last_error,
            'lastRequestedAt' => optional($setting->dgii_token_last_requested_at)->toISOString(),
        ];
    }
}
