<?php

namespace App\Services\BusinessIdentity;

use InvalidArgumentException;

final class IdentityFlow
{
    public const LABELS = ['ecf' => 'e-CF', 'pos' => 'POS', 'bys' => 'BYS', 'cumplimiento' => 'Cumplimiento'];

    public static function origin(string $url): string
    {
        $url = rtrim($url, '/');
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path'])) {
            throw new InvalidArgumentException('Falta configurar el origen HTTPS de la aplicación.');
        }

        return $url;
    }

    public static function handoff(string $origin, string $stage, string $service, string $token): string
    {
        return self::origin($origin).'/business/identity#'.http_build_query(['stage' => $stage, 'service' => $service, 'token' => $token], '', '&', PHP_QUERY_RFC3986);
    }

    public static function request(array $data, string $service): void
    {
        if (($data['service'] ?? null) !== $service || ! in_array($data['mode'] ?? null, ['company', 'member'], true)
            || ! array_key_exists('local_company_id', $data)
            || ($data['local_company_id'] !== null && (! is_int($data['local_company_id']) || $data['local_company_id'] <= 0))) {
            throw new InvalidArgumentException('La solicitud de vinculación no es válida.');
        }
        IdentityToken::assertBinding([...$data, 'local_company_id' => $data['local_company_id'] ?? 1, 'local_user_id' => 1, 'consent' => str_repeat('a', 32)]);
    }

    public static function summary(array $data, int $expiresAt): array
    {
        return [
            'service' => $data['service'], 'service_name' => self::LABELS[$data['service']],
            'mode' => $data['mode'] ?? 'member', 'expires_at' => $expiresAt,
            'central_company_id' => $data['central_company_id'], 'central_user_id' => $data['central_user_id'],
            'central_company_name' => $data['central_company_name'] ?? 'Empresa '.$data['central_company_id'],
            'central_user_name' => $data['central_user_name'] ?? 'Cuenta '.$data['central_user_id'],
            'central_user_email' => $data['central_user_email'] ?? null,
            'local_company_id' => $data['local_company_id'] ?? null, 'local_user_id' => $data['local_user_id'] ?? null,
            'local_company_name' => $data['local_company_name'] ?? null,
            'local_user_name' => $data['local_user_name'] ?? null, 'local_user_email' => $data['local_user_email'] ?? null,
        ];
    }
}
