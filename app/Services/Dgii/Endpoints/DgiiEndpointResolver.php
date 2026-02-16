<?php

namespace App\Services\Dgii\Endpoints;

use RuntimeException;

final class DgiiEndpointResolver
{
    /**
     * $baseUrls ejemplo:
     *  [
     *    'ecf' => 'https://ecf.dgii.gov.do',
     *    'fc' => 'https://fc.dgii.gov.do',
     *    'status' => 'https://statusecf.dgii.gov.do',
     *  ]
     */
    public function resolve(string $baseUrl, string $pathTemplate, string $cfPrefix, array $params = [], bool $strict = false): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        // {cf}
        $path = str_replace('{cf}', trim($cfPrefix, '/'), $pathTemplate);

        // placeholders adicionales {trackid}, etc
        $path = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($params) {
            $key = $m[1];

            if ($key === 'cf') {
                // ya resuelto arriba
                return $m[0];
            }

            if (!array_key_exists($key, $params)) {
                // dejamos placeholder si no hay valor; UI puede mostrarlo tal cual
                return '{' . $key . '}';
            }

            // si va en querystring, igual conviene rawurlencode
            return rawurlencode((string) $params[$key]);
        }, $path);

        // sanity
        if ($path === null) {
            throw new RuntimeException('No se pudo construir el path del endpoint.');
        }

        return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
    }
}
