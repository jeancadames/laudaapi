<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log de auditoría.
     *
     * - HTTP: usa request()->ip() / request()->userAgent() / Auth::id()
     * - Console/Queue: ip=null, user_agent="console:<cmd>", user_id=null
     *
     * @param  string  $event
     * @param  mixed|null  $model
     * @param  array  $data
     * @param  array  $context  Overrides: ['ip' => ?, 'user_agent' => ?, 'user_id' => ?]
     */
    public static function log(string $event, $model = null, array $data = [], array $context = []): AuditLog
    {
        $req = app()->bound('request') ? app('request') : null;

        // Defaults (HTTP)
        $ip = $req ? $req->ip() : null;
        $ua = $req ? $req->userAgent() : null;
        $userId = Auth::id();

        // Console/Queue fallback
        if (app()->runningInConsole()) {
            $ip = null;

            $cmd = $_SERVER['argv'][1] ?? 'console';
            $ua = $ua ?: ('console:' . $cmd);

            $userId = $userId ?: null;
        }

        // Overrides explícitos (tienen prioridad)
        if (array_key_exists('ip', $context)) {
            $ip = $context['ip'];
        }
        if (array_key_exists('user_agent', $context)) {
            $ua = $context['user_agent'];
        }
        if (array_key_exists('user_id', $context)) {
            $userId = $context['user_id'];
        }

        // ✅ Blindaje: garantiza JSON-serializable
        $data = json_decode(json_encode($data), true);

        return AuditLog::create([
            'event' => $event,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->getKey() : null,
            'data' => $data,
            'ip' => $ip,
            'user_agent' => $ua,
            'user_id' => $userId,
        ]);
    }
}
