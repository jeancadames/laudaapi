<?php

namespace App\Services;

use App\Models\ErrorLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorLogService
{
    /**
     * Agrupa si existe un error con mismo fingerprint en esta ventana.
     * Ajusta a tu gusto (7 días suele ser perfecto).
     */
    public int $groupWindowDays = 7;

    /**
     * Decide si persiste según level.
     * Puedes meter warning si lo necesitas.
     */
    public array $persistLevels = ['error', 'critical', 'alert', 'emergency'];

    public function capture(Throwable $e, array $extra = []): ?ErrorLog
    {
        $level = $extra['level'] ?? 'error';

        if (!in_array($level, $this->persistLevels, true)) {
            return null;
        }

        $payload = $this->buildPayload($e, $level, $extra);

        return DB::transaction(function () use ($payload) {
            $existing = ErrorLog::query()
                ->where('fingerprint', $payload['fingerprint'])
                ->where('last_seen_at', '>=', now()->subDays($this->groupWindowDays))
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->update([
                    'occurrences' => DB::raw('occurrences + 1'),
                    'last_seen_at' => now(),
                    // Mantén actualizado contexto mínimo si quieres
                    'status_code' => $payload['status_code'] ?? $existing->status_code,
                    'route' => $payload['route'] ?? $existing->route,
                    'url' => $payload['url'] ?? $existing->url,
                    'user_id' => $payload['user_id'] ?? $existing->user_id,
                    'ip' => $payload['ip'] ?? $existing->ip,
                ]);

                return $existing->fresh();
            }

            $payload['occurrences'] = 1;
            $payload['first_seen_at'] = now();
            $payload['last_seen_at'] = now();

            return ErrorLog::create($payload);
        });
    }

    private function buildPayload(Throwable $e, string $level, array $extra): array
    {
        $type = get_class($e);
        $message = trim($e->getMessage()) ?: $type;

        $file = $e->getFile();
        $line = $e->getLine();
        $code = (string) ($e->getCode() ?? null);

        $statusCode = $extra['status_code'] ?? $this->guessStatusCode($e);

        /**
         * Request context:
         * - En CLI puede no existir un request HTTP real.
         */
        $req = null;
        if (!app()->runningInConsole() && app()->bound('request')) {
            $req = app('request');
        }

        $method = $extra['method'] ?? ($req?->method() ?? null);
        $url = $extra['url'] ?? ($req?->fullUrl() ?? null);
        $route = $extra['route'] ?? ($req?->route()?->getName() ?? $req?->route()?->uri() ?? null);
        $ip = $extra['ip'] ?? ($req?->ip() ?? null);
        $userAgent = $extra['user_agent'] ?? ($req?->userAgent() ?? null);
        $requestId = $extra['request_id'] ?? ($req?->headers?->get('X-Request-Id') ?? null);

        // ✅ Robusto (null si no hay auth)
        $userId = $extra['user_id'] ?? Auth::id();

        $context = $this->sanitizeContext(array_merge(
            $extra['context'] ?? [],
            [
                'exception' => [
                    'type' => $type,
                    'message' => $message,
                ],
            ]
        ));

        $tags = $extra['tags'] ?? null;

        $fingerprint = $this->fingerprint($type, $file, $line, $message);

        return [
            'level' => $level,
            'type' => $type,
            'fingerprint' => $fingerprint,
            'message' => Str::limit($message, 65000, ''), // seguro para mysql text
            'file' => $file,
            'line' => $line ? (int) $line : null,
            'code' => $code ?: null,
            'trace' => $extra['trace'] ?? $e->getTraceAsString(),

            'method' => $method,
            'url' => $url,
            'route' => $route,
            'request_id' => $requestId,
            'status_code' => $statusCode,

            'user_id' => $userId,
            'ip' => $ip,
            'user_agent' => $userAgent,

            'context' => $context ?: null,
            'tags' => $tags,
        ];
    }

    private function fingerprint(string $type, ?string $file, ?int $line, string $message): string
    {
        // Normaliza números largos para no fragmentar por IDs
        $normalized = preg_replace('/\b\d{3,}\b/', '#', $message) ?? $message;

        return hash('sha256', implode('|', [
            $type,
            (string) $file,
            (string) $line,
            $normalized,
        ]));
    }

    /**
     * Determina status HTTP si aplica.
     * - Evita llamar getStatusCode() sobre Throwable directamente.
     */
    private function guessStatusCode(Throwable $e): ?int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof AuthenticationException) {
            return 401;
        }

        if ($e instanceof AuthorizationException) {
            return 403;
        }

        if ($e instanceof ModelNotFoundException) {
            return 404;
        }

        return null;
    }

    private function sanitizeContext(array $ctx): array
    {
        $deny = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'cookie',
            'set-cookie',
            'secret',
            'api_key',
        ];

        $out = [];

        foreach ($ctx as $k => $v) {
            $key = strtolower((string) $k);

            if (in_array($key, $deny, true)) {
                $out[$k] = '[filtered]';
                continue;
            }

            // Reduce objetos/arrays enormes
            if (is_array($v)) {
                $out[$k] = array_slice($v, 0, 50, true);
                continue;
            }

            if (is_string($v) && strlen($v) > 5000) {
                $out[$k] = substr($v, 0, 5000) . '…';
                continue;
            }

            $out[$k] = $v;
        }

        return $out;
    }
}
