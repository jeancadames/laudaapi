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
        // 0) Cache-first (reduce DB hits)
        $cacheKey = $this->cacheKey($setting);
        $cached = Cache::get($cacheKey);

        if (!$this->shouldPreWarm($setting) && is_array($cached)) {
            $token = (string)($cached['token'] ?? '');
            $expiresAtTs = (int)($cached['expires_at'] ?? 0);

            $effectiveSkew = max(
                120,
                $skewSeconds,
                (int)($setting->dgii_token_refresh_before_seconds ?? 0)
            );

            if ($token !== '' && $expiresAtTs > 0 && now()->timestamp < ($expiresAtTs - $effectiveSkew)) {
                return $token;
            }
        }

        // 1) Si es válido y NO aplica prewarm => devolver
        if ($setting->isTokenValid($skewSeconds) && !$this->shouldPreWarm($setting)) {
            // mantiene cache caliente
            $this->putCacheFromSetting($setting);
            return (string) $setting->dgii_access_token;
        }

        // 2) Si no permites auto => modo manual
        if (!$setting->dgii_token_auto) {
            throw new \RuntimeException(
                'DGII token vencido/no disponible. Modo manual activo (dgii_token_auto=false).'
            );
        }

        // ✅ Lock TTL SUBIDO: 60s (DGII + red + colas)
        $lockKey = $this->lockKey($setting);
        $lock = Cache::lock($lockKey, 60);

        return $lock->block(15, function () use ($setting, $skewSeconds, $cacheKey) {

            $fresh = DgiiCompanySetting::query()
                ->where('company_id', $setting->company_id)
                ->firstOrFail();

            // re-check cache dentro del lock (por si otro proceso refrescó justo antes)
            $cached = Cache::get($cacheKey);
            if (!$this->shouldPreWarm($fresh) && is_array($cached)) {
                $token = (string)($cached['token'] ?? '');
                $expiresAtTs = (int)($cached['expires_at'] ?? 0);
                $effectiveSkew = max(120, $skewSeconds, (int)($fresh->dgii_token_refresh_before_seconds ?? 0));

                if ($token !== '' && $expiresAtTs > 0 && now()->timestamp < ($expiresAtTs - $effectiveSkew)) {
                    return $token;
                }
            }

            if ($fresh->isTokenValid($skewSeconds) && !$this->shouldPreWarm($fresh)) {
                $this->putCacheFromSetting($fresh);
                return (string) $fresh->dgii_access_token;
            }

            $fresh->dgii_token_last_requested_at = now();

            try {
                $res = $this->authClient->requestToken($fresh);
                // esperado: ['token' => '...', 'expires_in' => 3600]

                $issuedAt  = CarbonImmutable::now();
                $expiresAt = $issuedAt->addSeconds((int)($res['expires_in'] ?? 3600));

                DB::transaction(function () use ($fresh, $res, $issuedAt, $expiresAt) {
                    // ✅ no guardes strings si el cast es datetime; deja que Eloquent lo maneje
                    $fresh->dgii_access_token = (string) $res['token'];
                    $fresh->dgii_token_issued_at = $issuedAt;
                    $fresh->dgii_token_expires_at = $expiresAt;
                    $fresh->dgii_token_last_error = null;
                    $fresh->save();
                });

                // ✅ cache token hasta expirar - buffer
                $this->putCache($cacheKey, (string)$fresh->dgii_access_token, $expiresAt->getTimestamp());

                return (string) $fresh->dgii_access_token;
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

        // ✅ limpia cache
        Cache::forget($this->cacheKey($setting));
    }

    public function getTokenStatus(DgiiCompanySetting $setting): array
    {
        $skewSeconds = 90;

        $isValid = $setting->isTokenValid($skewSeconds);

        $lastError = $setting->dgii_token_last_error;
        $lastReq   = $setting->dgii_token_last_requested_at;

        $errorRecent = false;
        if ($lastError && $lastReq) {
            $errorRecent = $lastReq->greaterThanOrEqualTo(now()->subMinutes(10));
        }

        $isOnline = $isValid || !$errorRecent;

        return [
            'auto' => (bool) $setting->dgii_token_auto,
            'status' => $setting->tokenStatus(),
            'secondsLeft' => $setting->tokenSecondsLeft(),
            'expiresAt' => $setting->dgii_token_expires_at?->toIso8601String(),
            'lastError' => $lastError,
            'lastRequestedAt' => $lastReq?->toIso8601String(),
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

    private function cacheKey(DgiiCompanySetting $setting): string
    {
        return "dgii:token:company:{$setting->company_id}:env:{$setting->environment}";
    }

    private function lockKey(DgiiCompanySetting $setting): string
    {
        return "dgii:token:lock:company:{$setting->company_id}:env:{$setting->environment}";
    }

    private function putCacheFromSetting(DgiiCompanySetting $setting): void
    {
        if (!$setting->dgii_access_token || !$setting->dgii_token_expires_at) return;

        $this->putCache(
            $this->cacheKey($setting),
            (string)$setting->dgii_access_token,
            $setting->dgii_token_expires_at->getTimestamp()
        );
    }

    private function putCache(string $cacheKey, string $token, int $expiresAtTs): void
    {
        $ttl = max(30, $expiresAtTs - now()->timestamp - 60); // buffer 60s
        Cache::put($cacheKey, ['token' => $token, 'expires_at' => $expiresAtTs], $ttl);
    }
}
