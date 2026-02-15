<?php

namespace App\Services\Dgii;

use Carbon\Carbon;
use Illuminate\Support\Facades\Process;

class DgiiCertificateReader
{
    /**
     * Lee un certificado desde bytes de upload (p12/pfx/cer) y devuelve info normalizada.
     *
     * Retorna keys:
     * - subject_cn, subject_rnc, issuer_cn, serial_number
     * - valid_from, valid_to (ISO8601 string)
     * - has_private_key (bool)
     * - password_ok (bool|null)   // null = no se pudo determinar
     * - status: active|expired|invalid|unparsed
     * - meta (array)
     */
    public function readFromUpload(string $ext, string $bytes, ?string $password = null): array
    {
        $ext = strtolower(trim($ext));

        if (in_array($ext, ['p12', 'pfx'], true)) {
            return $this->readPkcs12($bytes, $password);
        }

        if ($ext === 'cer') {
            return $this->readCer($bytes);
        }

        throw new \InvalidArgumentException("Unsupported certificate extension: {$ext}");
    }

    private function readPkcs12(string $bytes, ?string $password): array
    {
        $certs = [];

        // Para pkcs12, '' (vacío) es distinto de null; normaliza.
        $password = $password ?? '';

        // Limpia cola de errores antes de intentar (importante para clasificar bien)
        while (openssl_error_string()) { /* drain */
        }

        $ok = openssl_pkcs12_read($bytes, $certs, $password);

        $errs = [];
        while ($e = openssl_error_string()) {
            $errs[] = $e;
        }

        // ✅ Si PHP/OpenSSL pudo leer el PKCS12, seguimos normal
        if ($ok && !empty($certs['cert'])) {
            $x509 = openssl_x509_parse($certs['cert']);

            if (!$x509) {
                $errs2 = [];
                while ($e = openssl_error_string()) {
                    $errs2[] = $e;
                }

                return $this->makePkcs12Error(
                    hint: 'No se pudo parsear el certificado X509 dentro del PFX/P12.',
                    opensslErrors: array_merge($errs, $errs2),
                    hasPrivateKey: !empty($certs['pkey']),
                    passwordOk: true,
                    status: 'invalid',
                    extraMeta: ['parse_source' => 'php_openssl']
                );
            }

            $hasPrivateKey = !empty($certs['pkey']);

            return $this->normalizeX509($x509, [
                'container' => 'pkcs12',
                'parse_source' => 'php_openssl',
                'has_chain' => !empty($certs['extracerts']),
                'has_private_key' => $hasPrivateKey,
                'openssl_errors' => $errs, // útil para auditoría
            ], [
                'has_private_key' => $hasPrivateKey,
                'password_ok' => true,
            ]);
        }

        // ❌ Falló PHP/OpenSSL. Clasificamos.
        $first = $errs[0] ?? null;

        // Caso OpenSSL 3 sin legacy provider (muy común)
        if ($first && str_contains($first, 'digital envelope routines::unsupported')) {
            // ✅ Fallback real: usa binario openssl con -legacy (y OPENSSL_CONF fijo)
            $fallback = $this->readPkcs12ViaOpensslCli($bytes, $password);

            // Si el fallback funcionó, devolvemos normalizado
            if (($fallback['ok'] ?? false) === true && !empty($fallback['x509'])) {
                return $this->normalizeX509($fallback['x509'], [
                    'container' => 'pkcs12',
                    'parse_source' => 'openssl_cli',
                    'legacy' => (bool) ($fallback['legacy'] ?? false),
                    'openssl_errors' => array_merge($errs, (array) ($fallback['openssl_errors'] ?? [])),
                ], [
                    'has_private_key' => (bool) ($fallback['has_private_key'] ?? false),
                    'password_ok' => $fallback['password_ok'] ?? null,
                ]);
            }

            // Si el fallback también falla, devolvemos el error “pro”
            return $this->makePkcs12Error(
                hint: $fallback['hint'] ?? 'OpenSSL 3: requiere legacy provider / -legacy para leer este PFX/P12.',
                opensslErrors: array_merge($errs, (array) ($fallback['openssl_errors'] ?? [])),
                hasPrivateKey: true,
                passwordOk: $fallback['password_ok'] ?? null,
                status: 'unparsed',
                extraMeta: [
                    'parse_source' => 'openssl_cli_fallback',
                    'legacy_tried' => true,
                ]
            );
        }

        // Password incorrecto (MAC failure)
        if ($first && str_contains($first, 'mac verify failure')) {
            return $this->makePkcs12Error(
                hint: 'Password incorrecto para el PFX/P12 (mac verify failure).',
                opensslErrors: $errs,
                hasPrivateKey: true,
                passwordOk: false,
                status: 'invalid',
                extraMeta: ['parse_source' => 'php_openssl']
            );
        }

        // Otro fallo genérico
        return $this->makePkcs12Error(
            hint: 'No se pudo leer el PFX/P12 (archivo inválido, password incorrecto o formato no soportado).',
            opensslErrors: $errs,
            hasPrivateKey: true,
            passwordOk: null,
            status: 'invalid',
            extraMeta: ['parse_source' => 'php_openssl']
        );
    }

    /**
     * Fallback robusto para OpenSSL 3: usa el binario `openssl` y reintenta con `-legacy`
     * para extraer cert + private key.
     */
    private function readPkcs12ViaOpensslCli(string $bytes, string $password): array
    {
        $tmpP12 = $this->tmpWrite($bytes, 'dgii_p12_');
        $tmpPem = $this->tmpPath('dgii_pem_');
        $tmpKey = $this->tmpPath('dgii_key_');

        try {
            // 1) Extraer cert+key a un PEM (primero sin -legacy, luego con -legacy si hace falta)
            $r = $this->opensslPkcs12Extract($tmpP12, $password, $tmpPem, legacy: false);

            if (!$r['ok']) {
                $err = (string) ($r['err'] ?? '');
                if (str_contains($err, 'error:0308010C') || str_contains($err, 'unsupported')) {
                    $r2 = $this->opensslPkcs12Extract($tmpP12, $password, $tmpPem, legacy: true);
                    if (!$r2['ok']) {
                        // Si con -legacy falla, puede ser password incorrecto o archivo corrupto
                        $hint = $this->classifyOpensslCliError($r2['err'] ?? '');
                        return [
                            'ok' => false,
                            'legacy' => true,
                            'password_ok' => $hint['password_ok'],
                            'hint' => $hint['hint'],
                            'openssl_errors' => $this->explodeErrLines($r2['err'] ?? ''),
                        ];
                    }
                    $r = $r2 + ['legacy' => true];
                } else {
                    $hint = $this->classifyOpensslCliError($err);
                    return [
                        'ok' => false,
                        'legacy' => false,
                        'password_ok' => $hint['password_ok'],
                        'hint' => $hint['hint'],
                        'openssl_errors' => $this->explodeErrLines($err),
                    ];
                }
            }

            // 2) Separar key (opcional) y cert para confirmar private key
            $keyOk = $this->opensslExtractPrivateKeyFromPem($tmpPem, $tmpKey, $password);
            $hasPrivateKey = $keyOk;

            // 3) Extraer cert del PEM y parsearlo
            $certPem = $this->extractFirstCertificatePem((string) @file_get_contents($tmpPem));
            if (!$certPem) {
                return [
                    'ok' => false,
                    'legacy' => (bool) ($r['legacy'] ?? false),
                    'password_ok' => null,
                    'hint' => 'OpenSSL extrajo PEM pero no se encontró un bloque CERTIFICATE dentro.',
                    'openssl_errors' => array_merge(
                        $this->explodeErrLines($r['err'] ?? ''),
                        ['pem_missing_certificate_block']
                    ),
                ];
            }

            $x509 = @openssl_x509_parse($certPem);
            if (!$x509) {
                return [
                    'ok' => false,
                    'legacy' => (bool) ($r['legacy'] ?? false),
                    'password_ok' => null,
                    'hint' => 'No se pudo parsear X509 luego del fallback openssl.',
                    'openssl_errors' => array_merge(
                        $this->explodeErrLines($r['err'] ?? ''),
                        ['php_openssl_x509_parse_failed']
                    ),
                ];
            }

            return [
                'ok' => true,
                'legacy' => (bool) ($r['legacy'] ?? false),
                'password_ok' => true, // si openssl pudo extraer, password fue aceptado
                'has_private_key' => $hasPrivateKey,
                'x509' => $x509,
                'openssl_errors' => $this->explodeErrLines($r['err'] ?? ''),
            ];
        } finally {
            $this->safeUnlink($tmpP12);
            $this->safeUnlink($tmpPem);
            $this->safeUnlink($tmpKey);
        }
    }

    private function opensslPkcs12Extract(string $inP12, string $password, string $outPem, bool $legacy): array
    {
        // -nodes: key sin cifrar dentro del PEM temporal (lo borramos luego)
        $cmd = [
            'openssl',
            'pkcs12',
            '-in',
            $inP12,
            '-out',
            $outPem,
            '-nodes',
            '-passin',
            'pass:' . $password,
        ];

        if ($legacy) {
            // ✅ clave para OpenSSL 3
            array_splice($cmd, 2, 0, ['-legacy']);
        }

        // ✅ Forzamos el cnf del sistema
        $env = ['OPENSSL_CONF' => '/etc/ssl/openssl.cnf'];

        $res = Process::env($env)->run($cmd);

        return [
            'ok' => $res->successful(),
            'out' => $res->output(),
            'err' => $res->errorOutput(),
            'code' => $res->exitCode(),
            'legacy' => $legacy,
        ];
    }

    /**
     * Intenta extraer/validar que exista una private key dentro del PEM generado.
     * No necesita password porque el PEM lo generamos con -nodes (sin cifrar).
     */
    private function opensslExtractPrivateKeyFromPem(string $pemPath, string $outKeyPath, string $password): bool
    {
        // Si el PEM ya trae la llave, esto debe funcionar.
        // Usamos openssl pkey para validar key.
        $cmd = ['openssl', 'pkey', '-in', $pemPath, '-out', $outKeyPath];

        $env = ['OPENSSL_CONF' => '/etc/ssl/openssl.cnf'];
        $res = Process::env($env)->run($cmd);

        return $res->successful() && @file_exists($outKeyPath) && @filesize($outKeyPath) > 0;
    }

    private function readCer(string $bytes): array
    {
        // CER puede venir en DER (binario) o PEM (texto). Intentamos ambos.
        $pem = $this->ensurePemFromCer($bytes);

        $x509 = @openssl_x509_parse($pem);
        if (!$x509) {
            throw new \RuntimeException('No se pudo parsear el archivo .cer (PEM/DER inválido).');
        }

        return $this->normalizeX509($x509, [
            'container' => 'x509',
            'encoding' => str_contains($pem, 'BEGIN CERTIFICATE') ? 'PEM' : 'unknown',
        ], [
            'has_private_key' => false,
            'password_ok' => true,
        ]);
    }

    private function ensurePemFromCer(string $bytes): string
    {
        // Si ya es PEM
        if (str_contains($bytes, 'BEGIN CERTIFICATE')) {
            return $bytes;
        }

        // Asumimos DER -> convertimos a PEM
        $b64 = chunk_split(base64_encode($bytes), 64, "\n");
        return "-----BEGIN CERTIFICATE-----\n{$b64}-----END CERTIFICATE-----\n";
    }

    private function normalizeX509(array $x509, array $meta = [], array $flags = []): array
    {
        $subject = $x509['subject'] ?? [];
        $issuer  = $x509['issuer'] ?? [];

        $subjectCN = $subject['CN'] ?? null;
        $issuerCN  = $issuer['CN'] ?? null;

        $serial = $x509['serialNumberHex'] ?? $x509['serialNumber'] ?? null;

        $fromTs = $x509['validFrom_time_t'] ?? null;
        $toTs   = $x509['validTo_time_t'] ?? null;

        $validFrom = $fromTs ? Carbon::createFromTimestamp($fromTs)->toISOString() : null;
        $validTo   = $toTs ? Carbon::createFromTimestamp($toTs)->toISOString() : null;

        // Intento de extraer RNC desde CN si existe un número 9-11 dígitos
        $subjectRnc = null;
        if (is_string($subjectCN) && preg_match('/\b(\d{9,11})\b/', $subjectCN, $m)) {
            $subjectRnc = $m[1];
        }

        $status = 'active';
        if ($toTs && Carbon::now()->greaterThan(Carbon::createFromTimestamp($toTs))) {
            $status = 'expired';
        }

        $passwordOk = array_key_exists('password_ok', $flags) ? $flags['password_ok'] : true;

        return [
            'subject_cn' => $subjectCN,
            'subject_rnc' => $subjectRnc,
            'issuer_cn' => $issuerCN,
            'serial_number' => $serial,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,

            'has_private_key' => (bool) ($flags['has_private_key'] ?? false),
            'password_ok' => $passwordOk,
            'status' => $status,

            'meta' => array_merge([
                'subject' => $subject,
                'issuer' => $issuer,
            ], $meta),
        ];
    }

    private function makePkcs12Error(
        string $hint,
        array $opensslErrors,
        bool $hasPrivateKey,
        ?bool $passwordOk,
        string $status,
        array $extraMeta = []
    ): array {
        return [
            'subject_cn' => null,
            'subject_rnc' => null,
            'issuer_cn' => null,
            'serial_number' => null,
            'valid_from' => null,
            'valid_to' => null,

            // PKCS12 normalmente trae key, pero puede no leerse.
            'has_private_key' => $hasPrivateKey,
            'password_ok' => $passwordOk,
            'status' => $status,

            'meta' => array_merge([
                'container' => 'pkcs12',
                'openssl_errors' => $opensslErrors,
                'hint' => $hint,
            ], $extraMeta),
        ];
    }

    private function classifyOpensslCliError(string $stderr): array
    {
        $s = strtolower($stderr);

        if (str_contains($s, 'mac verify failure') || str_contains($s, 'invalid password')) {
            return [
                'password_ok' => false,
                'hint' => 'Password incorrecto para el PFX/P12 (mac verify failure).',
            ];
        }

        if (str_contains($s, 'error:0308010c') || str_contains($s, 'unsupported')) {
            return [
                'password_ok' => null,
                'hint' => 'OpenSSL 3: requiere legacy provider / -legacy para leer este PFX/P12.',
            ];
        }

        return [
            'password_ok' => null,
            'hint' => 'No se pudo leer el PFX/P12 (archivo inválido, password incorrecto o formato no soportado).',
        ];
    }

    private function explodeErrLines(string $stderr): array
    {
        $stderr = trim((string) $stderr);
        if ($stderr === '') return [];
        $lines = preg_split('/\R+/', $stderr) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines)));
        return array_slice($lines, 0, 40);
    }

    private function extractFirstCertificatePem(string $pem): ?string
    {
        if ($pem === '') return null;

        if (preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $m)) {
            return "-----BEGIN CERTIFICATE-----" . $m[1] . "-----END CERTIFICATE-----\n";
        }

        return null;
    }

    private function tmpWrite(string $bytes, string $prefix): string
    {
        $path = $this->tmpPath($prefix);
        file_put_contents($path, $bytes);
        return $path;
    }

    private function tmpPath(string $prefix): string
    {
        $dir = sys_get_temp_dir();
        $name = $prefix . bin2hex(random_bytes(8));
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    }

    private function safeUnlink(string $path): void
    {
        if ($path && @file_exists($path)) {
            @unlink($path);
        }
    }
}
