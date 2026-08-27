<?php

namespace App\Services\LaudaErp;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

class ServiceLaunchTokenFactory
{
    public function make(
        User $user,
        Company $company,
        Service $service
    ): string {
        $secret = trim(
            (string) config('lauda_sso.secret', '')
        );

        if (strlen($secret) < 32) {
            throw new RuntimeException(
                'LAUDA_SSO_SECRET no está configurado correctamente.'
            );
        }

        $ttlMinutes = max(
            1,
            min(
                10,
                (int) config(
                    'lauda_sso.ttl_minutes',
                    5
                )
            )
        );

        $issuedAt = now();
        $expiresAt = $issuedAt
            ->copy()
            ->addMinutes($ttlMinutes);

        $serviceKey = (string) (
            $service->service_key
            ?: $service->slug
        );

        $payload = [
            'v' => 1,
            'iss' => (string) config(
                'lauda_sso.issuer',
                config('app.url')
            ),
            'aud' => $serviceKey,
            'sub' => (string) $user->id,

            'service' => [
                'id' => $service->id,
                'slug' => $service->slug,
                'service_key' => $serviceKey,
                'launch_mode' =>
                    $service->launch_mode,
            ],

            'company' => [
                'id' => $company->id,
                'subscriber_id' =>
                    $company->subscriber_id,
                'name' =>
                    $company->name
                    ?? $company->business_name
                    ?? null,
            ],

            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
                'role' => $user->role ?? null,
            ],

            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
            'nonce' => bin2hex(
                random_bytes(16)
            ),
        ];

        $encrypter = new Encrypter(
            hash('sha256', $secret, true),
            'AES-256-CBC'
        );

        return $encrypter->encryptString(
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            )
        );
    }
}
