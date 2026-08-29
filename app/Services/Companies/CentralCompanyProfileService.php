<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Validation\Rule;

final class CentralCompanyProfileService
{
    /**
     * Campos que pertenecen al perfil empresarial declarado.
     *
     * No incluye estado DGII, régimen fiscal, RST, cierre fiscal,
     * obligaciones ni configuración operativa de soluciones.
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'taxpayer_type' => [
                'nullable',
                Rule::in(['persona_fisica', 'persona_juridica']),
            ],

            'country_code' => ['required', 'string', 'size:2'],
            'currency' => [
                'required',
                Rule::in(['DOP', 'USD', 'EUR']),
            ],
            'timezone' => ['required', 'string', 'max:100'],
            'company_size' => [
                'required',
                Rule::in(['1-10', '11-50', '51-200', '201+']),
            ],

            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_contact_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],

            'economic_activity_primary_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'economic_activity_primary_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function onboardingDefaults(User $user): array
    {
        return [
            'company_name' => '',
            'legal_name' => '',
            'tax_id' => '',
            'taxpayer_type' => null,
            'country_code' => 'DO',
            'currency' => 'DOP',
            'timezone' => 'America/Santo_Domingo',
            'company_size' => '',
            'billing_email' => (string) $user->email,
            'billing_phone' => '',
            'billing_contact_name' => (string) $user->name,
            'address_line1' => '',
            'address_line2' => '',
            'state' => '',
            'city' => '',
            'postal_code' => '',
            'economic_activity_primary_code' => '',
            'economic_activity_primary_name' => '',
        ];
    }

    /**
     * @return array{0: Subscriber|null, 1: Company|null, 2: string|null}
     */
    public function resolveEditableContext(User $user): array
    {
        $subscriber = $user->activeSubscribers()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->orderBy('subscribers.id')
            ->first();

        if (! $subscriber) {
            return [null, null, null];
        }

        $company = Company::query()
            ->where('subscriber_id', $subscriber->id)
            ->where('active', true)
            ->orderBy('id')
            ->first();

        $role = $subscriber->users()
            ->whereKey($user->id)
            ->first()
            ?->pivot
            ?->role;

        return [
            $subscriber,
            $company,
            is_string($role) ? $role : null,
        ];
    }

    public function payload(
        Company $company,
        Subscriber $subscriber
    ): array {
        $profile = CompanyTaxProfile::query()
            ->where('company_id', $company->id)
            ->first();

        $meta = is_array($subscriber->meta)
            ? $subscriber->meta
            : [];

        $profileMeta = is_array($meta['company_profile'] ?? null)
            ? $meta['company_profile']
            : [];

        $legacy = is_array($meta['app_hub_onboarding'] ?? null)
            ? $meta['app_hub_onboarding']
            : [];

        $companySize = $profileMeta['company_size']
            ?? $legacy['company_size']
            ?? '';

        return [
            'company_name' => (string) $company->name,
            'legal_name' => (string) ($profile?->legal_name ?? ''),
            'tax_id' => (string) ($profile?->tax_id ?? ''),
            'taxpayer_type' => $profile?->taxpayer_type,

            'country_code' => strtoupper((string) (
                $profile?->country_code
                ?: $subscriber->country_code
                ?: 'DO'
            )),
            'currency' => strtoupper((string) (
                $company->currency
                ?: $subscriber->currency
                ?: 'DOP'
            )),
            'timezone' => (string) (
                $company->timezone
                ?: $subscriber->timezone
                ?: 'America/Santo_Domingo'
            ),
            'company_size' => (string) $companySize,

            'billing_email' => (string) (
                $profile?->billing_email
                ?? ''
            ),
            'billing_phone' => (string) (
                $profile?->billing_phone
                ?? ''
            ),
            'billing_contact_name' => (string) (
                $profile?->billing_contact_name
                ?? ''
            ),

            'address_line1' => (string) (
                $profile?->address_line1
                ?? ''
            ),
            'address_line2' => (string) (
                $profile?->address_line2
                ?? ''
            ),
            'state' => (string) ($profile?->state ?? ''),
            'city' => (string) ($profile?->city ?? ''),
            'postal_code' => (string) (
                $profile?->postal_code
                ?? ''
            ),

            'economic_activity_primary_code' => (string) (
                $profile?->economic_activity_primary_code
                ?? ''
            ),
            'economic_activity_primary_name' => (string) (
                $profile?->economic_activity_primary_name
                ?? ''
            ),
        ];
    }

    public function save(
        Company $company,
        Subscriber $subscriber,
        array $data,
        User $actor
    ): CompanyTaxProfile {
        $normalized = $this->normalize($data);

        $companyName = $normalized['company_name'];
        $country = $normalized['country_code'];
        $currency = $normalized['currency'];
        $timezone = $normalized['timezone'];

        $company->forceFill([
            'name' => $companyName,
            'currency' => $currency,
            'timezone' => $timezone,
        ])->save();

        $meta = is_array($subscriber->meta)
            ? $subscriber->meta
            : [];

        $profileMeta = is_array($meta['company_profile'] ?? null)
            ? $meta['company_profile']
            : [];

        $profileMeta = array_merge($profileMeta, [
            'company_size' => $normalized['company_size'],
            'declared_at' => now()->toIso8601String(),
            'declared_by_user_id' => $actor->id,
        ]);

        $legacy = is_array($meta['app_hub_onboarding'] ?? null)
            ? $meta['app_hub_onboarding']
            : [];

        $legacy = array_merge($legacy, [
            'phone' => $normalized['billing_phone'],
            'legal_name' => $normalized['legal_name'],
            'tax_id' => $normalized['tax_id'],
            'company_size' => $normalized['company_size'],
            'completed_at' => $legacy['completed_at']
                ?? now()->toIso8601String(),
        ]);

        $meta['source'] ??= 'app_hub_direct_onboarding';
        $meta['company_profile'] = $profileMeta;
        $meta['app_hub_onboarding'] = $legacy;

        $subscriber->forceFill([
            'name' => $companyName,
            'country_code' => $country,
            'currency' => $currency,
            'timezone' => $timezone,
            'meta' => $meta,
            'active' => true,
        ])->save();

        $profile = CompanyTaxProfile::query()
            ->firstOrNew([
                'company_id' => $company->id,
            ]);

        $profile->fill([
            'legal_name' => $normalized['legal_name'],
            'trade_name' => $companyName,
            'country_code' => $country,
            'tax_id' => $normalized['tax_id'],
            'tax_id_type' => $country === 'DO'
                ? 'RNC'
                : 'TAX_ID',

            'address_line1' => $normalized['address_line1'],
            'address_line2' => $normalized['address_line2'],
            'city' => $normalized['city'],
            'state' => $normalized['state'],
            'postal_code' => $normalized['postal_code'],

            'billing_email' => $normalized['billing_email'],
            'billing_phone' => $normalized['billing_phone'],
            'billing_contact_name' =>
                $normalized['billing_contact_name'],

            'taxpayer_type' => $normalized['taxpayer_type'],
            'economic_activity_primary_code' =>
                $normalized['economic_activity_primary_code'],
            'economic_activity_primary_name' =>
                $normalized['economic_activity_primary_name'],
        ]);

        $profile->save();

        return $profile;
    }

    private function normalize(array $data): array
    {
        $nullable = static fn (mixed $value): ?string => (
            ($value = trim((string) ($value ?? ''))) !== ''
                ? $value
                : null
        );

        return [
            'company_name' => trim((string) $data['company_name']),
            'legal_name' => $nullable($data['legal_name'] ?? null),
            'tax_id' => $nullable($data['tax_id'] ?? null),
            'taxpayer_type' =>
                $nullable($data['taxpayer_type'] ?? null),

            'country_code' => strtoupper(trim(
                (string) $data['country_code']
            )),
            'currency' => strtoupper(trim(
                (string) $data['currency']
            )),
            'timezone' => trim((string) $data['timezone']),
            'company_size' => trim(
                (string) $data['company_size']
            ),

            'billing_email' => strtolower(trim(
                (string) $data['billing_email']
            )),
            'billing_phone' =>
                $nullable($data['billing_phone'] ?? null),
            'billing_contact_name' =>
                $nullable($data['billing_contact_name'] ?? null),

            'address_line1' =>
                $nullable($data['address_line1'] ?? null),
            'address_line2' =>
                $nullable($data['address_line2'] ?? null),
            'state' => $nullable($data['state'] ?? null),
            'city' => $nullable($data['city'] ?? null),
            'postal_code' =>
                $nullable($data['postal_code'] ?? null),

            'economic_activity_primary_code' =>
                $nullable(
                    $data['economic_activity_primary_code']
                    ?? null
                ),
            'economic_activity_primary_name' =>
                $nullable(
                    $data['economic_activity_primary_name']
                    ?? null
                ),
        ];
    }
}
