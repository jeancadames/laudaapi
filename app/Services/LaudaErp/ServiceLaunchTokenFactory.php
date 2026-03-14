<?php

namespace App\Services\LaudaErp;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class ServiceLaunchTokenFactory
{
    public function make(User $user, Company $company, Service $service): string
    {
        $expiresAt = now()->addMinutes(5);

        $payload = [
            'iss' => config('app.url'),
            'aud' => (string) ($service->service_key ?: $service->slug),
            'sub' => (string) $user->id,

            'service' => [
                'id' => $service->id,
                'slug' => $service->slug,
                'service_key' => $service->service_key,
                'launch_mode' => $service->launch_mode,
            ],

            'company' => [
                'id' => $company->id,
                'subscriber_id' => $company->subscriber_id,
                'name' => $company->name ?? $company->business_name ?? null,
            ],

            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
                'role' => $user->role ?? null,
            ],

            'iat' => now()->timestamp,
            'exp' => $expiresAt->timestamp,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        return Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}