<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SubscriberCompanyController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);

        if (!$company) {
            return redirect()->route('subscriber')
                ->with('error', 'No tienes empresa asignada todavía. Completa tu activación primero.');
        }

        // ✅ Asegurar 1:1 (si no existe, se crea con defaults)
        $profile = CompanyTaxProfile::query()
            ->where('company_id', $company->id)
            ->first();

        if (!$profile) {
            $profile = CompanyTaxProfile::create([
                'company_id' => $company->id,

                // base
                'legal_name' => $company->name,
                'trade_name' => null,
                'country_code' => 'DO',
                'tax_id' => null,
                'tax_id_type' => 'RNC',

                // address
                'address_line1' => null,
                'address_line2' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,

                // billing
                'billing_email' => $user->email ?? null,
                'billing_phone' => null,
                'billing_contact_name' => null,

                // tax
                'tax_exempt' => false,
                'default_itbis_rate' => 18.000,

                // DGII extras (si ya existen en tu migración)
                'taxpayer_type' => null,               // persona_fisica | persona_juridica
                'tax_regime' => 'general',             // general | rst | special
                'rst_modality' => null,                // ingresos | compras
                'rst_category' => null,

                'economic_activity_primary_code' => null,
                'economic_activity_primary_name' => null,
                'economic_activities_secondary' => [], // json

                'invoicing_mode' => null,              // ncf | ecf | both
                'dgii_status' => null,
                'dgii_registered_on' => null,

                'meta' => null,
            ]);

            Cache::forget("subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}");

            AuditService::log('subscriber_company_tax_profile_auto_created', $profile, [
                'company_id' => $company->id,
                'user_id' => $user->id,
            ], ['user_id' => $user->id]);
        }

        return Inertia::render('Subscriber/Company/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
                'active' => (bool) $company->active,
            ],
            'taxProfile' => [
                'id' => $profile->id,
                'legal_name' => $profile->legal_name,
                'trade_name' => $profile->trade_name,
                'country_code' => $profile->country_code,
                'tax_id' => $profile->tax_id,
                'tax_id_type' => $profile->tax_id_type,

                'address_line1' => $profile->address_line1,
                'address_line2' => $profile->address_line2,
                'city' => $profile->city,
                'state' => $profile->state,
                'postal_code' => $profile->postal_code,

                'billing_email' => $profile->billing_email,
                'billing_phone' => $profile->billing_phone,
                'billing_contact_name' => $profile->billing_contact_name,

                'tax_exempt' => (bool) $profile->tax_exempt,
                'default_itbis_rate' => (string) $profile->default_itbis_rate,

                // DGII extras
                'taxpayer_type' => $profile->taxpayer_type ?? null,
                'tax_regime' => $profile->tax_regime ?? 'general',
                'rst_modality' => $profile->rst_modality ?? null,
                'rst_category' => $profile->rst_category ?? null,

                'economic_activity_primary_code' => $profile->economic_activity_primary_code ?? null,
                'economic_activity_primary_name' => $profile->economic_activity_primary_name ?? null,
                'economic_activities_secondary' => $profile->economic_activities_secondary ?? [],

                'invoicing_mode' => $profile->invoicing_mode ?? null,
                'dgii_status' => $profile->dgii_status ?? null,
                'dgii_registered_on' => $profile->dgii_registered_on?->format('Y-m-d'),
            ],
        ]);
    }

    public function upsert(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);

        if (!$company) {
            return back()->with('error', 'No tienes empresa asignada todavía.');
        }

        // ✅ Payload plano (como lo envía tu Vue)
        $data = $request->validate([
            // Company
            'company_name' => ['required', 'string', 'max:255'],
            'company_currency' => ['required', 'string', 'size:3'],
            'company_timezone' => ['required', 'string', 'max:64'],
            'company_active' => ['nullable', 'boolean'],

            // TaxProfile base
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],

            'country_code' => ['nullable', 'string', 'size:2'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'tax_id_type' => ['nullable', 'string', 'max:20'],

            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],

            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_contact_name' => ['nullable', 'string', 'max:255'],

            'tax_exempt' => ['nullable', 'boolean'],
            'default_itbis_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // DGII extras
            'taxpayer_type' => ['nullable', 'in:persona_fisica,persona_juridica'],
            'tax_regime' => ['nullable', 'in:general,rst,special'],
            'rst_modality' => ['nullable', 'in:ingresos,compras'],
            'rst_category' => ['nullable', 'string', 'max:50'],

            'economic_activity_primary_code' => ['nullable', 'string', 'max:20'],
            'economic_activity_primary_name' => ['nullable', 'string', 'max:255'],

            'economic_activities_secondary' => ['nullable', 'array', 'max:5'],
            'economic_activities_secondary.*.code' => ['nullable', 'string', 'max:20'],
            'economic_activities_secondary.*.name' => ['nullable', 'string', 'max:255'],

            'invoicing_mode' => ['nullable', 'in:ncf,ecf,both'],
            'dgii_status' => ['nullable', 'string', 'max:50'],
            'dgii_registered_on' => ['nullable', 'date'],
        ]);

        // -------------------------
        // Normalización / defaults
        // -------------------------
        $data['company_name'] = trim((string) $data['company_name']);
        $data['company_currency'] = strtoupper(trim((string) $data['company_currency']));
        $data['company_timezone'] = trim((string) $data['company_timezone']);

        $data['legal_name'] = trim((string) $data['legal_name']);
        $data['trade_name'] = isset($data['trade_name']) ? trim((string) $data['trade_name']) : null;

        $data['country_code'] = strtoupper(trim((string) ($data['country_code'] ?? 'DO')));
        $data['tax_id_type'] = strtoupper(trim((string) ($data['tax_id_type'] ?? 'RNC')));
        $data['tax_id'] = isset($data['tax_id']) ? trim((string) $data['tax_id']) : null;

        $data['tax_exempt'] = (bool) ($data['tax_exempt'] ?? false);
        $data['default_itbis_rate'] = $data['default_itbis_rate'] ?? 18.000;

        $data['tax_regime'] = $data['tax_regime'] ?? 'general';

        // si no es RST, limpia campos RST
        if (($data['tax_regime'] ?? 'general') !== 'rst') {
            $data['rst_modality'] = null;
            $data['rst_category'] = null;
        }

        // Limpiar secundarios vacíos
        $secondary = $data['economic_activities_secondary'] ?? [];
        $secondary = collect($secondary)->map(function ($row) {
            return [
                'code' => isset($row['code']) ? trim((string) $row['code']) : null,
                'name' => isset($row['name']) ? trim((string) $row['name']) : null,
            ];
        })->filter(function ($row) {
            return !empty($row['code']) || !empty($row['name']);
        })->values()->take(5)->all();

        $data['economic_activities_secondary'] = $secondary;

        // -------------------------
        // Persistencia (TX)
        // -------------------------
        try {
            $result = DB::transaction(function () use ($company, $data) {

                // actualizar company
                $company->name = $data['company_name'];
                $company->currency = $data['company_currency'];
                $company->timezone = $data['company_timezone'];

                if (array_key_exists('company_active', $data)) {
                    $company->active = (bool) $data['company_active'];
                }

                // opcional: si quieres mantener slug estable, NO lo cambies.
                // si quieres regenerarlo cuando cambia nombre, hazlo aquí (pero yo NO lo recomiendo).
                $company->save();

                // upsert tax profile 1:1
                $existing = CompanyTaxProfile::query()
                    ->where('company_id', $company->id)
                    ->first();

                $profile = CompanyTaxProfile::updateOrCreate(
                    ['company_id' => $company->id],
                    [
                        // base
                        'legal_name' => $data['legal_name'],
                        'trade_name' => $data['trade_name'],
                        'country_code' => $data['country_code'],
                        'tax_id' => $data['tax_id'],
                        'tax_id_type' => $data['tax_id_type'],

                        // address
                        'address_line1' => $data['address_line1'] ?? null,
                        'address_line2' => $data['address_line2'] ?? null,
                        'city' => $data['city'] ?? null,
                        'state' => $data['state'] ?? null,
                        'postal_code' => $data['postal_code'] ?? null,

                        // billing
                        'billing_email' => $data['billing_email'] ?? null,
                        'billing_phone' => $data['billing_phone'] ?? null,
                        'billing_contact_name' => $data['billing_contact_name'] ?? null,

                        // tax
                        'tax_exempt' => (bool) $data['tax_exempt'],
                        'default_itbis_rate' => $data['default_itbis_rate'],

                        // DGII
                        'taxpayer_type' => $data['taxpayer_type'] ?? null,
                        'tax_regime' => $data['tax_regime'] ?? 'general',
                        'rst_modality' => $data['rst_modality'] ?? null,
                        'rst_category' => $data['rst_category'] ?? null,

                        'economic_activity_primary_code' => $data['economic_activity_primary_code'] ?? null,
                        'economic_activity_primary_name' => $data['economic_activity_primary_name'] ?? null,
                        'economic_activities_secondary' => $data['economic_activities_secondary'] ?? [],

                        'invoicing_mode' => $data['invoicing_mode'] ?? null,
                        'dgii_status' => $data['dgii_status'] ?? null,
                        'dgii_registered_on' => $data['dgii_registered_on'] ?? null,
                    ]
                );

                return [
                    'profile' => $profile,
                    'created' => $existing ? false : true,
                ];
            });

            Cache::forget("subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}");

            AuditService::log('subscriber_company_upserted', $result['profile'], [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'created_tax_profile' => $result['created'],
                'tax_regime' => $data['tax_regime'] ?? null,
            ], ['user_id' => $user->id]);

            return back()->with('success', 'Empresa y perfil fiscal guardados correctamente.');
        } catch (\Throwable $e) {
            AuditService::log('subscriber_company_upsert_failed', null, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ], ['user_id' => $user->id]);

            report($e);
            return back()->with('error', 'No se pudo guardar: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Resolver Company de forma segura.
     * Orden:
     * 1) user->company_id
     * 2) companies.owner_user_id
     * 3) companies.subscriber_id por pivot subscriber_user activo o user->subscriber_id (fallback)
     */
    private function resolveCompanyForUser(int $userId, $userCompanyId, $userSubscriberId): ?Company
    {
        // 1) shortcut company_id
        if (!empty($userCompanyId)) {
            $c = Company::query()->find((int) $userCompanyId);
            if ($c) return $c;
        }

        // 2) owner
        $c = Company::query()->where('owner_user_id', $userId)->first();
        if ($c) return $c;

        // 3) subscriber por pivot activo (fuente de verdad)
        $subscriberId = (int) DB::table('subscriber_user')
            ->where('user_id', $userId)
            ->where('active', 1)
            ->value('subscriber_id');

        // fallback si existe (aunque en tu sistema normalmente no)
        if ($subscriberId <= 0 && !empty($userSubscriberId)) {
            $subscriberId = (int) $userSubscriberId;
        }

        if ($subscriberId > 0) {
            return Company::query()->where('subscriber_id', $subscriberId)->first();
        }

        return null;
    }
}
