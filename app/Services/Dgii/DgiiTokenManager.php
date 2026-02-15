<?php

namespace App\Services\Dgii;

use App\Models\DgiiCompanySetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DgiiTokenManager
{
    public function __construct(
        private readonly DgiiAuthClient $authClient,
    ) {}

    public function getValidToken(DgiiCompanySetting $setting, int $skewSeconds = 90): string
    {
        // Si es válido y NO aplica prewarm => devolver
        if ($setting->isTokenValid($skewSeconds) && !$this->shouldPreWarm($setting)) {
            return (string)$setting->dgii_access_token;
        }

        // Si no permites auto => modo manual
        if (!$setting->dgii_token_auto) {
            throw new \RuntimeException(
                'DGII token vencido/no disponible. Modo manual activo (dgii_token_auto=false).'
            );
        }

        $lockKey = "dgii:token:company:{$setting->company_id}:env:{$setting->environment}";
        $lock = Cache::lock($lockKey, 10);

        return $lock->block(5, function () use ($setting, $skewSeconds) {

            $fresh = DgiiCompanySetting::query()
                ->where('company_id', $setting->company_id)
                ->firstOrFail();

            if ($fresh->isTokenValid($skewSeconds) && !$this->shouldPreWarm($fresh)) {
                return (string)$fresh->dgii_access_token;
            }

            $fresh->dgii_token_last_requested_at = now();

            try {
                $res = $this->authClient->requestToken($fresh);
                // esperado: ['token' => '...', 'expires_in' => 3600]

                $issuedAt = CarbonImmutable::now();
                $expiresAt = $issuedAt->addSeconds((int)($res['expires_in'] ?? 3600));

                DB::transaction(function () use ($fresh, $res, $issuedAt, $expiresAt) {
                    $fresh->dgii_access_token = (string)$res['token'];
                    $fresh->dgii_token_issued_at = $issuedAt->toDateTimeString();
                    $fresh->dgii_token_expires_at = $expiresAt->toDateTimeString();
                    $fresh->dgii_token_last_error = null;
                    $fresh->save();
                });

                return (string)$fresh->dgii_access_token;
            } catch (\Throwable $e) {
                $fresh->dgii_token_last_error = mb_substr($e->getMessage(), 0, 255);
                $fresh->save();
                throw $e;
            }
        });
    }

    public function invalidateToken(DgiiCompanySetting $setting): void
    {
        $setting->dgii_access_token = null;
        $setting->dgii_token_issued_at = null;
        $setting->dgii_token_expires_at = null;
        $setting->save();
    }

    public function getTokenStatus(DgiiCompanySetting $setting): array
    {
        $skewSeconds = 90;

        $isValid = $setting->isTokenValid($skewSeconds);

        $lastError = $setting->dgii_token_last_error;
        $lastReq   = $setting->dgii_token_last_requested_at;

        // “Reciente” = últimos 10 minutos (ajusta a gusto)
        $errorRecent = false;
        if ($lastError && $lastReq) {
            $errorRecent = $lastReq->greaterThanOrEqualTo(now()->subMinutes(10));
        }

        // ONLINE si:
        // - token válido, o
        // - no hay error reciente (para no quedar “offline” pegado por un error viejo)
        $isOnline = $isValid || !$errorRecent;

        return [
            'auto' => (bool) $setting->dgii_token_auto,
            'status' => $setting->tokenStatus(),
            'secondsLeft' => $setting->tokenSecondsLeft(),
            'expiresAt' => $setting->dgii_token_expires_at?->toIso8601String(),
            'lastError' => $lastError,
            'lastRequestedAt' => $lastReq?->toIso8601String(),

            // ✅ NUEVO
            'is_online' => (bool) $isOnline,
            'offline_reason' => (!$isOnline && $lastError) ? $lastError : null,
        ];
    }

    private function shouldPreWarm(DgiiCompanySetting $setting): bool
    {
        $before = (int)($setting->dgii_token_refresh_before_seconds ?? 0);
        if ($before <= 0) return false;

        if (!$setting->dgii_access_token || !$setting->dgii_token_expires_at) return false;

        $left = $setting->tokenSecondsLeft();
        return $left > 0 && $left <= $before;
    }

    public function ensureValidToken(DgiiCompanySetting $setting, bool $force = false, int $skewSeconds = 90): string
    {
        $fresh = DgiiCompanySetting::query()
            ->where('company_id', $setting->company_id)
            ->firstOrFail();

        if ($force) {
            $this->invalidateToken($fresh);
            $fresh = $fresh->fresh();
        }

        return $this->getValidToken($fresh, $skewSeconds);
    }
}
