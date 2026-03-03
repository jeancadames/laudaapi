<?php

namespace App\Services\Dgii\Endpoints;

use RuntimeException;

final class DgiiEndpointResolver
{
    /**
     * Construye URL final:
     * - Reemplaza {cf} por $cfPrefix
     * - Reemplaza placeholders {x} por $params['x']
     * - Si $pathTemplate es URL absoluta, se devuelve esa URL ya resuelta
     *
     * strict=false:
     *  - placeholders faltantes se quedan como {placeholder}
     *
     * strict=true:
     *  - si falta algún placeholder (excepto cf) => throw
     *  - si quedan placeholders al final => throw
     */
    public function resolve(
        string $baseUrl,
        string $pathTemplate,
        string $cfPrefix,
        array $params = [],
        bool $strict = false
    ): string {
        $baseUrl = trim($baseUrl);
        $pathTemplate = trim($pathTemplate);

        if ($pathTemplate === '') {
            throw new RuntimeException('pathTemplate vacío: no se puede resolver endpoint.');
        }

        // Si el template es URL absoluta, no usar baseUrl
        $isAbsolute = preg_match('/^https?:\/\//i', $pathTemplate) === 1;

        // Normaliza baseUrl (solo si lo vamos a usar)
        if (!$isAbsolute) {
            if ($baseUrl === '') {
                throw new RuntimeException('baseUrl vacío y pathTemplate no es absoluto.');
            }
            $baseUrl = $this->normalizeBaseUrl($baseUrl);
        }

        // Normaliza cfPrefix
        $cf = trim($cfPrefix);
        $cf = trim($cf, '/');

        if ($cf === '') {
            // Si no tienes cfPrefix, en strict esto debe explotar.
            if ($strict) {
                throw new RuntimeException('cfPrefix vacío en modo strict.');
            }
            // En no-strict dejamos {cf} tal cual (útil para UI preview)
        }

        // 1) Resuelve {cf}
        $path = str_replace('{cf}', $cf !== '' ? $cf : '{cf}', $pathTemplate);

        // 2) Resuelve placeholders restantes {xxx}
        $missing = [];

        $path = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($params, $strict, &$missing) {
            $key = $m[1];

            if ($key === 'cf') {
                return $m[0]; // ya resuelto arriba
            }

            if (!array_key_exists($key, $params)) {
                $missing[] = $key;
                return $strict ? $m[0] : ('{' . $key . '}');
            }

            return rawurlencode((string) $params[$key]);
        }, $path);

        if ($path === null) {
            throw new RuntimeException('No se pudo construir el path del endpoint (preg_replace_callback devolvió null).');
        }

        // 3) strict: si faltan placeholders => throw
        if ($strict && count(array_unique($missing)) > 0) {
            throw new RuntimeException('Faltan placeholders requeridos: ' . implode(', ', array_unique($missing)));
        }

        // 4) strict: si todavía quedan placeholders => throw
        if ($strict && preg_match('/\{[a-zA-Z0-9_]+\}/', $path) === 1) {
            throw new RuntimeException('Quedaron placeholders sin resolver en el endpoint final.');
        }

        // 5) Devuelve final
        if ($isAbsolute) {
            return $this->sanitizeDoubleSlashes($path);
        }

        return $this->joinUrl($baseUrl, $path);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        // si viene sin scheme, asume https
        if (!preg_match('/^https?:\/\//i', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }

        return $baseUrl;
    }

    private function joinUrl(string $baseUrl, string $path): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path = trim($path);

        if ($path === '') {
            return $baseUrl;
        }

        // Si path trae scheme por algún error/override, respétalo
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $this->sanitizeDoubleSlashes($path);
        }

        $path = ltrim($path, '/');

        return $this->sanitizeDoubleSlashes($baseUrl . '/' . $path);
    }

    private function sanitizeDoubleSlashes(string $url): string
    {
        // evita https://// o similar, sin romper https://
        return preg_replace('#(?<!:)//+#', '/', $url) ?? $url;
    }
}
