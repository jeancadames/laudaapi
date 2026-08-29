<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Jobs\SyncObligationInstancesForCompany;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Services\Companies\CentralCompanyProfileService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SubscriberCompanyController extends Controller
{
    public function show(
        Request $request,
        CentralCompanyProfileService $profiles
    ) {
        $user = $request->user();
        abort_unless($user, 403);

        [$subscriber, $company, $role] =
            $profiles->resolveEditableContext($user);

        abort_unless(
            $subscriber && $company,
            403,
            'Solo owner/admin puede administrar el perfil de empresa.'
        );

        return Inertia::render('Subscriber/Company/Index', [
            'profile' => $profiles->payload(
                $company,
                $subscriber
            ),
            'viewer' => [
                'role' => $role,
                'is_admin' => true,
            ],
            'account' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
        ]);
    }

    public function upsert(
        Request $request,
        CentralCompanyProfileService $profiles
    ) {
        $user = $request->user();
        abort_unless($user, 403);

        [$subscriber, $company] =
            $profiles->resolveEditableContext($user);

        abort_unless(
            $subscriber && $company,
            403,
            'Solo owner/admin puede administrar el perfil de empresa.'
        );

        $data = $request->validate(
            $profiles->rules()
        );

        try {
            DB::transaction(function () use (
                $profiles,
                $company,
                $subscriber,
                $data,
                $user
            ): void {
                $profiles->save(
                    $company,
                    $subscriber,
                    $data,
                    $user
                );
            });

            Cache::forget(
                "subscriber.dashboard.stats.company."
                ."{$company->id}.user.{$user->id}"
            );

            AuditService::log(
                'subscriber_company_profile_updated',
                null,
                [
                    'company_id' => $company->id,
                    'subscriber_id' => $subscriber->id,
                    'user_id' => $user->id,
                ],
                ['user_id' => $user->id]
            );

            return back()->with(
                'success',
                'Perfil de empresa actualizado correctamente.'
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'No se pudo guardar el perfil de empresa.'
            );
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
                'company_id'   => (int)$company->id,
                'template_id'  => (int)$tplId,
                'public_id'    => (string) Str::ulid(),
                'enabled'      => (bool)$it['enabled'] ? 1 : 0,
                'starts_on'    => $it['starts_on'] ?? null,
                'ends_on'      => $it['ends_on'] ?? null,
                'owner_user_id' => null,
                'updated_at'   => $now,
                'created_at'   => $now,
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
