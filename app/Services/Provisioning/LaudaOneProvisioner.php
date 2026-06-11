<?php

namespace App\Services\Provisioning;

use App\Models\Company as ApiCompany;
use App\Models\User as ApiUser;
use Illuminate\Support\Facades\DB;

class LaudaOneProvisioner
{
    public function provision(ApiCompany $apiCompany, ApiUser $apiUser, string $channel): array
    {
        return DB::connection('laudaone')->transaction(function () use ($apiCompany, $apiUser, $channel) {
            $now = now();
            $conn = DB::connection('laudaone');

            /*
            |--------------------------------------------------------------------------
            | 1) USER
            |--------------------------------------------------------------------------
            */

            $existingUser = $conn->table('users')
                ->where('email', $apiUser->email)
                ->first();

            if ($existingUser) {
                $conn->table('users')
                    ->where('id', $existingUser->id)
                    ->update([
                        'name' => $apiUser->name,
                        'email_verified_at' => $apiUser->email_verified_at,
                        'password' => $apiUser->password,
                        'updated_at' => $now,
                    ]);

                $laudaOneUserId = (int) $existingUser->id;
            } else {
                $laudaOneUserId = $conn->table('users')->insertGetId([
                    'name' => $apiUser->name,
                    'email' => $apiUser->email,
                    'email_verified_at' => $apiUser->email_verified_at,
                    'password' => $apiUser->password,
                    'role' => 'admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 2) COMPANY
            |--------------------------------------------------------------------------
            */

            $existingCompany = $conn->table('companies')
                ->where('slug', $apiCompany->slug)
                ->first();

            $existingMeta = [];

            if ($existingCompany && !empty($existingCompany->meta)) {
                $decoded = json_decode($existingCompany->meta, true);
                if (is_array($decoded)) {
                    $existingMeta = $decoded;
                }
            }

            $channels = $existingMeta['channels'] ?? [];

            if (!is_array($channels)) {
                $channels = [];
            }

            if (!in_array($channel, $channels, true)) {
                $channels[] = $channel;
            }

            sort($channels);

            $meta = [
                'source' => 'laudaapi',
                'laudaapi_company_id' => $apiCompany->id,
                'laudaapi_owner_user_id' => $apiCompany->owner_user_id,
                'laudaapi_activating_user_id' => $apiUser->id,
                'channels' => $channels,
                'last_channel_activated' => $channel,
                'provisioned_at' => $now->toISOString(),
            ];

            $companyPayload = [
                'name' => $apiCompany->name,
                'slug' => $apiCompany->slug,
                'legal_name' => $apiCompany->name,
                'tax_id' => null,
                'email' => $apiUser->email,
                'phone' => null,
                'country' => 'DO',
                'currency' => $this->mapCurrency($apiCompany->currency),
                'timezone' => $apiCompany->timezone ?: 'America/Santo_Domingo',
                'owner_user_id' => $laudaOneUserId,
                'active' => (bool) $apiCompany->active,
                'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ];

            if ($existingCompany) {
                $conn->table('companies')
                    ->where('id', $existingCompany->id)
                    ->update($companyPayload);

                $laudaOneCompanyId = (int) $existingCompany->id;
            } else {
                $laudaOneCompanyId = $conn->table('companies')->insertGetId([
                    ...$companyPayload,
                    'created_at' => $now,
                ]);
            }

            return [
                'user_id' => $laudaOneUserId,
                'company_id' => $laudaOneCompanyId,
                'channels' => $channels,
            ];
        });
    }

    private function mapCurrency(?string $currency): string
    {
        $allowed = ['DOP', 'USD', 'EUR'];

        return in_array((string) $currency, $allowed, true)
            ? (string) $currency
            : 'USD';
    }
}