<?php

namespace App\Services\BusinessCommercial;

use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;
use Throwable;

/** Wire contract v1. Purpose keys are separate from F2 identity / launch tokens. */
final class CommercialToken
{
    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Falta el secreto comercial Business.');
        }
    }

    public function encode(string $issuer, string $audience, string $direction, array $data, int $now): string
    {
        return $this->cipher($direction)->encryptString(json_encode([
            'v' => 1, 'iss' => $issuer, 'aud' => $audience, 'direction' => $direction,
            'iat' => $now, 'exp' => $now + 120, 'data' => $data,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function decode(string $token, string $issuer, string $audience, string $direction, int $now): array
    {
        try {
            if ($issuer === '' || $audience === '' || strlen($token) > 16384) {
                throw new InvalidArgumentException;
            }
            $claims = json_decode($this->cipher($direction)->decryptString($token), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($claims) || ($claims['v'] ?? null) !== 1 || ($claims['iss'] ?? null) !== $issuer
                || ($claims['aud'] ?? null) !== $audience || ($claims['direction'] ?? null) !== $direction
                || ! is_int($claims['iat'] ?? null) || ! is_int($claims['exp'] ?? null)
                || $claims['iat'] > $now + 30 || $claims['exp'] <= $now || $claims['exp'] - $claims['iat'] !== 120
                || ! is_array($claims['data'] ?? null)) {
                throw new InvalidArgumentException;
            }

            return $claims['data'];
        } catch (Throwable) {
            throw new InvalidArgumentException('Comprobante comercial inválido o vencido.');
        }
    }

    private function cipher(string $direction): Encrypter
    {
        if (! in_array($direction, ['request', 'response'], true)) {
            throw new InvalidArgumentException('Dirección comercial inválida.');
        }

        return new Encrypter(hash_hkdf('sha256', $this->secret, 32, 'lauda-business-commercial-v1:'.$direction), 'AES-256-CBC');
    }
}
