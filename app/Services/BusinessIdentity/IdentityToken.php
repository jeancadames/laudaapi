<?php

namespace App\Services\BusinessIdentity;

use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;
use Throwable;

/** Versioned contract shared by the hub and the four Business receivers. */
final class IdentityToken
{
    public const SERVICES = ['ecf', 'pos', 'bys', 'cumplimiento'];

    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Configura un secreto Business exclusivo de al menos 32 caracteres.');
        }
    }

    public function encode(string $issuer, string $audience, string $purpose, array $data, int $now, int $ttl = 300): string
    {
        if ($issuer === '' || $audience === '' || $ttl < 1 || $ttl > 600) {
            throw new InvalidArgumentException('Configuración de identidad inválida.');
        }

        return $this->cipher($purpose)->encryptString(json_encode([
            'v' => 2, 'iss' => $issuer, 'aud' => $audience, 'purpose' => $purpose,
            'iat' => $now, 'exp' => $now + $ttl, 'nonce' => bin2hex(random_bytes(16)), 'data' => $data,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function decode(string $token, string $issuer, string $audience, string $purpose, int $now): array
    {
        try {
            if ($issuer === '' || $audience === '' || strlen($token) > 16384) {
                throw new InvalidArgumentException;
            }
            $claims = json_decode($this->cipher($purpose)->decryptString($token), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($claims) || ($claims['v'] ?? null) !== 2
                || ($claims['iss'] ?? null) !== $issuer || ($claims['aud'] ?? null) !== $audience
                || ($claims['purpose'] ?? null) !== $purpose || ! is_int($claims['iat'] ?? null)
                || ! is_int($claims['exp'] ?? null) || $claims['iat'] > $now + 30
                || $claims['exp'] <= $now || $claims['exp'] <= $claims['iat']
                || $claims['exp'] - $claims['iat'] > 600
                || ! is_string($claims['nonce'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $claims['nonce'])
                || ! is_array($claims['data'] ?? null)) {
                throw new InvalidArgumentException;
            }

            return $claims;
        } catch (Throwable) {
            throw new InvalidArgumentException('El comprobante de identidad no es válido o ha vencido.');
        }
    }

    public static function assertBinding(array $data): void
    {
        foreach (['central_company_id', 'central_subscriber_id', 'central_user_id', 'local_company_id', 'local_user_id', 'generation'] as $key) {
            if (! is_int($data[$key] ?? null) || $data[$key] <= 0) {
                throw new InvalidArgumentException('Identidad de empresa o usuario inválida.');
            }
        }
        if (! in_array($data['service'] ?? null, self::SERVICES, true)
            || ! is_string($data['link_id'] ?? null) || ! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $data['link_id'])
            || ! is_string($data['consent'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $data['consent'])) {
            throw new InvalidArgumentException('Vínculo de identidad inválido.');
        }
    }

    private function cipher(string $purpose): Encrypter
    {
        if (! in_array($purpose, ['link-request', 'link-proof', 'link-activation', 'link-ready', 'launch'], true)) {
            throw new InvalidArgumentException('Propósito de identidad inválido.');
        }

        return new Encrypter(hash_hkdf('sha256', $this->secret, 32, 'lauda-business-v2:'.$purpose), 'AES-256-CBC');
    }
}
