<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Jobs\SyncObligationInstancesForCompany;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class SubscriberCompanyController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);
        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);

        if (!$company) {
            return redirect()->route('subscriber')
                ->with('error', 'No tienes empresa asignada todavía. Completa tu activación primero.');
        }

        // ✅ Asegurar 1:1 profile (race-safe)
        $profile = $this->ensureTaxProfile($company, $user);

        // -----------------------------
        // ✅ Fiscal Year End catalog (cierre fiscal)
        // -----------------------------
        $fiscalYearEnds = [];
        if (Schema::hasTable('fiscal_year_end_catalog')) {
            $country = strtoupper((string)($profile->country_code ?: 'DO'));

            $fiscalYearEnds = DB::table('fiscal_year_end_catalog')
                ->where('active', 1)
                ->where('country_code', $country)
                ->orderBy('sort_order')
                ->get(['id', 'country_code', 'close_month', 'close_day', 'label', 'common_business_types', 'ir2_due_days'])
                ->map(fn($r) => [
                    'id' => (int)$r->id,
                    'country_code' => (string)$r->country_code,
                    'close_month' => (int)$r->close_month,
                    'close_day' => (int)$r->close_day,
                    'label' => (string)$r->label,
                    'common_business_types' => $r->common_business_types,
                    'ir2_due_days' => (int)$r->ir2_due_days,
                ])
                ->values()
                ->all();
        }

        // -----------------------------
        // ✅ Compliance catalog + company obligations (tenant_obligations)
        // -----------------------------
        [$complianceCatalog, $companyObligations] = $this->loadComplianceData($company->id, $profile->country_code);

        return Inertia::render('Subscriber/Company/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
                'active' => (bool)$company->active,
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
                'tax_exempt' => (bool)$profile->tax_exempt,
                'default_itbis_rate' => (string)$profile->default_itbis_rate,

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

                // ✅ NUEVO: cierre fiscal seleccionado
                'fiscal_year_end_id' => $profile->fiscal_year_end_id ? (int)$profile->fiscal_year_end_id : null,
            ],

            // ✅ catálogos
            'fiscalYearEnds' => $fiscalYearEnds,

            // ✅ compliance
            'complianceCatalog' => $complianceCatalog,
            'companyObligations' => $companyObligations,
        ]);
    }

    public function upsert(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) return back()->with('error', 'No tienes empresa asignada todavía.');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_currency' => ['required', 'string', 'size:3'],
            'company_timezone' => ['required', 'string', 'max:64'],
            'company_active' => ['nullable', 'boolean'],

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

            // ✅ NUEVO: cierre fiscal seleccionado
            'fiscal_year_end_id' => ['nullable', 'integer', 'min:1'],
        ]);

        // Normalización
        $data['company_name'] = trim((string)$data['company_name']);
        $data['company_currency'] = strtoupper(trim((string)$data['company_currency']));
        $data['company_timezone'] = trim((string)$data['company_timezone']);

        $data['legal_name'] = trim((string)$data['legal_name']);
        $data['trade_name'] = isset($data['trade_name']) ? trim((string)$data['trade_name']) : null;

        $data['country_code'] = strtoupper(trim((string)($data['country_code'] ?? 'DO')));
        $data['tax_id_type'] = strtoupper(trim((string)($data['tax_id_type'] ?? 'RNC')));
        $data['tax_id'] = isset($data['tax_id']) ? trim((string)$data['tax_id']) : null;

        $data['tax_exempt'] = (bool)($data['tax_exempt'] ?? false);
        $data['default_itbis_rate'] = $data['default_itbis_rate'] ?? 18.000;
        $data['tax_regime'] = $data['tax_regime'] ?? 'general';

        if (($data['tax_regime'] ?? 'general') !== 'rst') {
            $data['rst_modality'] = null;
            $data['rst_category'] = null;
        }

        $secondary = collect($data['economic_activities_secondary'] ?? [])
            ->map(fn($row) => [
                'code' => isset($row['code']) ? trim((string)$row['code']) : null,
                'name' => isset($row['name']) ? trim((string)$row['name']) : null,
            ])
            ->filter(fn($row) => !empty($row['code']) || !empty($row['name']))
            ->values()
            ->take(5)
            ->all();

        $data['economic_activities_secondary'] = $secondary;

        // ✅ Validar fiscal_year_end_id por país y fallback a 31/12
        $data['fiscal_year_end_id'] = $this->normalizeFiscalYearEndId(
            $data['country_code'],
            (int)($data['fiscal_year_end_id'] ?? 0)
        );

        try {
            DB::transaction(function () use ($company, $data) {
                $company->name = $data['company_name'];
                $company->currency = $data['company_currency'];
                $company->timezone = $data['company_timezone'];

                if (array_key_exists('company_active', $data)) {
                    $company->active = (bool)$data['company_active'];
                }

                $company->save();

                CompanyTaxProfile::updateOrCreate(
                    ['company_id' => $company->id],
                    [
                        'legal_name' => $data['legal_name'],
                        'trade_name' => $data['trade_name'],
                        'country_code' => $data['country_code'],
                        'tax_id' => $data['tax_id'],
                        'tax_id_type' => $data['tax_id_type'],

                        'address_line1' => $data['address_line1'] ?? null,
                        'address_line2' => $data['address_line2'] ?? null,
                        'city' => $data['city'] ?? null,
                        'state' => $data['state'] ?? null,
                        'postal_code' => $data['postal_code'] ?? null,

                        'billing_email' => $data['billing_email'] ?? null,
                        'billing_phone' => $data['billing_phone'] ?? null,
                        'billing_contact_name' => $data['billing_contact_name'] ?? null,

                        'tax_exempt' => (bool)$data['tax_exempt'],
                        'default_itbis_rate' => $data['default_itbis_rate'],

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

                        // ✅ NUEVO
                        'fiscal_year_end_id' => $data['fiscal_year_end_id'],
                    ]
                );
            });

            Cache::forget("subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}");
            AuditService::log('subscriber_company_upserted', null, [
                'company_id' => $company->id,
                'user_id' => $user->id,
            ], ['user_id' => $user->id]);

            return back()->with('success', 'Empresa y perfil fiscal guardados correctamente.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'No se pudo guardar: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Endpoint PRO: guarda tenant_obligations con upsert() (MySQL) + dispara sync instances
     */
    public function upsertObligations(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) return back()->with('error', 'No tienes empresa asignada todavía.');

        $hasCompliance =
            Schema::hasTable('compliance_obligation_templates') &&
            Schema::hasTable('tenant_obligations') &&
            Schema::hasTable('company_tax_profiles');

        if (!$hasCompliance) {
            return back()->with('error', 'Compliance no está instalado (faltan tablas).');
        }

        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.template_id' => ['required', 'integer', 'min:1'],
            'items.*.enabled' => ['required', 'boolean'],
            'items.*.starts_on' => ['nullable', 'date'],
            'items.*.ends_on' => ['nullable', 'date'],
        ]);

        foreach ($payload['items'] as $it) {
            if (!empty($it['starts_on']) && !empty($it['ends_on']) && $it['starts_on'] > $it['ends_on']) {
                return back()->with('error', 'En Cumplimiento: "Desde" no puede ser mayor que "Hasta".');
            }
        }

        $profile = CompanyTaxProfile::query()->where('company_id', $company->id)->first();
        $country = strtoupper((string)($profile?->country_code ?: 'DO'));

        // De-dupe por template_id (último gana)
        $byTpl = [];
        foreach ($payload['items'] as $it) {
            $byTpl[(int)$it['template_id']] = $it;
        }
        $templateIds = array_keys($byTpl);

        // Solo templates válidos (activos y del país)
        $validTemplateIds = DB::table('compliance_obligation_templates')
            ->where('active', 1)
            ->where('country_code', $country)
            ->whereIn('id', $templateIds)
            ->pluck('id')
            ->map(fn($x) => (int)$x)
            ->values()
            ->all();

        $validSet = array_flip($validTemplateIds);

        $now = now();
        $rows = [];

        foreach ($byTpl as $tplId => $it) {
            if (!isset($validSet[$tplId])) continue;

            $rows[] = [
                'company_id' => (int)$company->id,
                'template_id' => (int)$tplId,
                'enabled' => (bool)$it['enabled'] ? 1 : 0,
                'starts_on' => $it['starts_on'] ?? null,
                'ends_on' => $it['ends_on'] ?? null,
                'owner_user_id' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        try {
            DB::transaction(function () use ($rows) {
                if (count($rows) === 0) return;

                DB::table('tenant_obligations')->upsert(
                    $rows,
                    ['company_id', 'template_id'],
                    ['enabled', 'starts_on', 'ends_on', 'owner_user_id', 'updated_at']
                );
            });

            // ✅ Si queue=database, esto queda ENCOLADO (requiere worker corriendo).
            // Si queue=sync, se ejecuta inmediatamente.
            SyncObligationInstancesForCompany::dispatch((int)$company->id, 18, 3, 7);

            AuditService::log('subscriber_company_obligations_upserted', null, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'country' => $country,
                'count' => count($rows),
            ], ['user_id' => $user->id]);

            $queue = config('queue.default');
            $msg = ($queue === 'sync')
                ? 'Cumplimiento guardado. Calendario actualizado.'
                : 'Cumplimiento guardado. Calendario en cola (asegúrate de tener un queue worker corriendo).';

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'No se pudo guardar Cumplimiento: ' . $e->getMessage());
        }
    }

    private function ensureTaxProfile(Company $company, $user): CompanyTaxProfile
    {
        return DB::transaction(function () use ($company, $user) {
            $profile = CompanyTaxProfile::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->first();

            if ($profile) return $profile;

            $profile = CompanyTaxProfile::create([
                'company_id' => $company->id,
                'legal_name' => $company->name,
                'country_code' => 'DO',
                'tax_id_type' => 'RNC',
                'billing_email' => $user->email ?? null,
                'tax_exempt' => false,
                'default_itbis_rate' => 18.000,
                'tax_regime' => 'general',
                'economic_activities_secondary' => [],
            ]);

            Cache::forget("subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}");
            AuditService::log('subscriber_company_tax_profile_auto_created', $profile, [
                'company_id' => $company->id,
                'user_id' => $user->id,
            ], ['user_id' => $user->id]);

            return $profile;
        });
    }

    private function loadComplianceData(int $companyId, ?string $countryCode): array
    {
        $complianceCatalog = [];
        $companyObligations = [];

        $hasCompliance =
            Schema::hasTable('tax_authorities') &&
            Schema::hasTable('compliance_obligation_templates') &&
            Schema::hasTable('tenant_obligations');

        if (!$hasCompliance) return [$complianceCatalog, $companyObligations];

        $country = strtoupper((string)($countryCode ?: 'DO'));

        $complianceCatalog = DB::table('compliance_obligation_templates as tpl')
            ->join('tax_authorities as auth', 'auth.id', '=', 'tpl.authority_id')
            ->where('tpl.active', 1)
            ->where('tpl.country_code', $country)
            ->orderBy('auth.sort_order')
            ->orderBy('tpl.code')
            ->get([
                'tpl.id',
                'tpl.country_code',
                'tpl.code',
                'tpl.name',
                'tpl.description',
                'tpl.frequency',
                'tpl.due_rule',
                'tpl.default_reminders',
                'tpl.official_ref_url',
                'tpl.version',
                'tpl.active',
                'tpl.authority_id',
                'auth.code as authority_code',
                'auth.name as authority_name',
            ])
            ->map(fn($r) => [
                'id' => (int)$r->id,
                'country_code' => (string)$r->country_code,
                'code' => (string)$r->code,
                'name' => (string)$r->name,
                'description' => $r->description,
                'frequency' => (string)$r->frequency,
                'due_rule' => $r->due_rule,
                'default_reminders' => $r->default_reminders,
                'official_ref_url' => $r->official_ref_url,
                'version' => (int)$r->version,
                'active' => (bool)$r->active,
                'authority_id' => (int)$r->authority_id,
                'authority_code' => (string)$r->authority_code,
                'authority_name' => (string)$r->authority_name,
            ])
            ->values()
            ->all();

        $companyObligations = DB::table('tenant_obligations')
            ->where('company_id', $companyId)
            ->get(['template_id', 'enabled', 'starts_on', 'ends_on', 'owner_user_id', 'reminders', 'overrides'])
            ->map(fn($o) => [
                'template_id' => (int)$o->template_id,
                'enabled' => (bool)$o->enabled,
                'starts_on' => $o->starts_on ? (string)$o->starts_on : null,
                'ends_on' => $o->ends_on ? (string)$o->ends_on : null,
                'owner_user_id' => $o->owner_user_id ? (int)$o->owner_user_id : null,
                'reminders' => $o->reminders,
                'overrides' => $o->overrides,
            ])
            ->values()
            ->all();

        return [$complianceCatalog, $companyObligations];
    }

    private function normalizeFiscalYearEndId(string $countryCode, int $incomingId): ?int
    {
        $country = strtoupper(trim($countryCode ?: 'DO'));

        if (!Schema::hasTable('fiscal_year_end_catalog')) {
            return null;
        }

        $validId = null;

        if ($incomingId > 0) {
            $validId = DB::table('fiscal_year_end_catalog')
                ->where('id', $incomingId)
                ->where('country_code', $country)
                ->where('active', 1)
                ->value('id');
        }

        if ($validId) return (int)$validId;

        // fallback a 31/12 si existe en el país
        $fallback = DB::table('fiscal_year_end_catalog')
            ->where('country_code', $country)
            ->where('close_month', 12)
            ->where('close_day', 31)
            ->where('active', 1)
            ->value('id');

        return $fallback ? (int)$fallback : null;
    }
    private function resolveCompanyForUser(int $userId, $userCompanyId, $userSubscriberId): ?Company
    {
        if (!empty($userCompanyId)) {
            $c = Company::query()->find((int)$userCompanyId);
            if ($c) return $c;
        }

        $c = Company::query()->where('owner_user_id', $userId)->first();
        if ($c) return $c;

        $subscriberId = (int) DB::table('subscriber_user')
            ->where('user_id', $userId)
            ->where('active', 1)
            ->value('subscriber_id');

        if ($subscriberId <= 0 && !empty($userSubscriberId)) {
            $subscriberId = (int)$userSubscriberId;
        }

        if ($subscriberId > 0) {
            return Company::query()->where('subscriber_id', $subscriberId)->first();
        }

        return null;
    }
}
