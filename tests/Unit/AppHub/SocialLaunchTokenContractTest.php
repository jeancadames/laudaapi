<?php

namespace Tests\Unit\AppHub;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\LaudaErp\ServiceLaunchTokenFactory;
use Illuminate\Encryption\Encrypter;
use Tests\TestCase;

class SocialLaunchTokenContractTest extends TestCase
{
    public function test_social_launch_token_uses_dedicated_sso_secret(): void
    {
        $secret = str_repeat('S', 64);

        config()->set('lauda_sso.secret', $secret);
        config()->set(
            'lauda_sso.issuer',
            'http://localhost:8000'
        );
        config()->set(
            'lauda_sso.ttl_minutes',
            5
        );

        $user = new User();
        $user->forceFill([
            'id' => 101,
            'name' => 'Cliente Central',
            'email' => 'cliente@example.com',
            'role' => 'subscriber',
        ]);

        $company = new Company();
        $company->forceFill([
            'id' => 202,
            'subscriber_id' => 303,
            'name' => 'Empresa Central',
        ]);

        $service = new Service();
        $service->forceFill([
            'id' => 34,
            'slug' => 'social',
            'service_key' => 'social',
            'launch_mode' => 'external',
        ]);

        $token = app(
            ServiceLaunchTokenFactory::class
        )->make(
            $user,
            $company,
            $service
        );

        $encrypter = new Encrypter(
            hash('sha256', $secret, true),
            'AES-256-CBC'
        );

        $payload = json_decode(
            $encrypter->decryptString($token),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(1, $payload['v']);
        $this->assertSame(
            'social',
            $payload['aud']
        );
        $this->assertSame(
            101,
            $payload['user']['id']
        );
        $this->assertSame(
            202,
            $payload['company']['id']
        );
        $this->assertSame(
            303,
            $payload['company'][
                'subscriber_id'
            ]
        );
        $this->assertSame(
            'social',
            $payload['service'][
                'service_key'
            ]
        );

        $this->assertGreaterThan(
            $payload['iat'],
            $payload['exp']
        );

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{32}$/',
            $payload['nonce']
        );
    }
}
