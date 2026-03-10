<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyTaxProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LaudaErpDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // -------------------------
        // ✅ Resolve company (robusto)
        // -------------------------
        $company = null;

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);

        if (!empty($user->company_id)) {
            $company = Company::query()->find((int)$user->company_id);
        }

        if (!$company && !empty($user->subscriber_id)) {
            $company = Company::query()
                ->where('subscriber_id', (int)$user->subscriber_id)
                ->first();
        }

        if (!$company) {
            // Si quieres, puedes redirigir al setup/activation
            return Inertia::render('LaudaERP/Dashboard', [
                'company' => null,
                'taxProfileReady' => false,
                'range' => [
                    'preset' => 'mtd',
                    'from' => now()->startOfMonth()->format('Y-m-d'),
                    'to' => now()->format('Y-m-d'),
                ],
                'alerts' => [[
                    'level' => 'critical',
                    'title' => 'No tienes empresa asignada',
                    'description' => 'Completa tu activación primero para ver el dashboard.',
                    'cta' => ['label' => 'Ir a Activación', 'href' => '/subscriber'],
                ]],
                'kpis' => $this->emptyKpis(),
                'dgii' => $this->emptyDgii(),
                'compliance' => ['nextDue' => []],
                'charts' => ['cashflow' => [], 'sales' => []],
                'activity' => [],
            ]);
        }

        $tz = $company->timezone ?: 'America/Santo_Domingo';

        // -------------------------
        // ✅ Range (timezone de empresa)
        // -------------------------
        $preset = $request->string('range', 'mtd')->toString(); // mtd|ytd|30d|7d|custom
        $preset = in_array($preset, ['mtd', 'ytd', '30d', '7d', 'custom'], true) ? $preset : 'mtd';

        [$from, $to] = $this->resolveRange($preset, $request, $tz);

        // -------------------------
        // ✅ Tax profile readiness
        // -------------------------
        $taxProfile = CompanyTaxProfile::query()
            ->where('company_id', $company->id)
            ->first();

        $taxProfileReady = (bool)$taxProfile;

        // -------------------------
        // ✅ Cached dashboard payload
        // -------------------------
        $cacheKey = "laudaerp.dashboard.company.{$company->id}.{$preset}.{$from}.{$to}";
        $payload = Cache::remember($cacheKey, 60, function () use ($company, $taxProfile, $taxProfileReady, $tz, $from, $to) {

            $alerts = [];

            if (!$taxProfileReady) {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'Perfil fiscal incompleto',
                    'description' => 'Completa el perfil fiscal para habilitar cumplimiento, DGII y cálculos por periodo.',
                    'cta' => ['label' => 'Completar perfil', 'href' => '/subscriber/company'],
                ];
            }

            // -------------------------
            // ✅ KPIs (TODO: conecta tus tablas reales)
            // -------------------------
            $kpis = $this->emptyKpis();

            // EJEMPLO: si tienes ventas en alguna tabla, aquí las sumarías por rango.
            // $kpis['revenue']['value'] = (float) DB::table('sales_invoices')
            //    ->where('company_id', $company->id)
            //    ->whereBetween('issued_on', [$from, $to])
            //    ->sum('total');

            // -------------------------
            // ✅ DGII status (opcional / tolerante)
            // -------------------------
            $dgii = $this->resolveDgiiStatusForCompany($company, $tz);

            // Si tienes tablas DGII, aquí detectas token/cert.
            // if (Schema::hasTable('dgii_company_settings')) { ... }

            // -------------------------
            // ✅ Compliance (obligation_instances) - tolerante
            // -------------------------
            $compliance = ['nextDue' => []];

            // IMPORTANTE: esto asume campos típicos. Si difiere tu schema, ajústalo.
            if (Schema::hasTable('obligation_instances')) {
                try {
                    $today = now($tz)->format('Y-m-d');

                    // Próximas 30 (vencidas o por vencer)
                    $rows = DB::table('obligation_instances')
                        ->where('company_id', $company->id)
                        ->whereNotIn('status', ['done', 'completed', 'filed']) // ajusta a tus status reales
                        ->orderBy('due_on')
                        ->limit(30)
                        ->get(['authority_code', 'obligation_code', 'name', 'due_on', 'status']);

                    $nextDue = [];
                    $overdue = 0;
                    $dueSoon7d = 0;

                    foreach ($rows as $r) {
                        $due = (string)$r->due_on;
                        $isOverdue = $due < $today;

                        if ($isOverdue) $overdue++;

                        // dueSoon 7d: (hoy..hoy+7)
                        $in7 = now($tz)->addDays(7)->format('Y-m-d');
                        if ($due >= $today && $due <= $in7) $dueSoon7d++;

                        $nextDue[] = [
                            'authority' => (string)($r->authority_code ?? '—'),
                            'code' => (string)($r->obligation_code ?? '—'),
                            'name' => (string)($r->name ?? '—'),
                            'due_on' => $due ?: '—',
                            'status' => $isOverdue ? 'overdue' : 'due',
                        ];
                    }

                    $compliance['nextDue'] = $nextDue;
                    $kpis['compliance'] = [
                        'overdue' => $overdue,
                        'dueSoon7d' => $dueSoon7d,
                    ];
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // -------------------------
            // ✅ Charts (TODO)
            // -------------------------
            $charts = [
                'cashflow' => [],
                'sales' => [],
            ];

            // -------------------------
            // ✅ Activity (audit) - opcional
            // -------------------------
            $activity = [];
            if (Schema::hasTable('audits')) {
                try {
                    $activity = DB::table('audits')
                        ->where('company_id', $company->id)
                        ->orderByDesc('created_at')
                        ->limit(15)
                        ->get(['created_at', 'actor_name', 'event', 'meta'])
                        ->map(fn($a) => [
                            'at' => (string)($a->created_at ?? ''),
                            'actor' => (string)($a->actor_name ?? 'Sistema'),
                            'event' => (string)($a->event ?? '—'),
                            'meta' => $a->meta ?? null,
                        ])
                        ->values()
                        ->all();
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // Alertas derivadas de cumplimiento (si hay)
            if (($kpis['compliance']['overdue'] ?? 0) > 0) {
                $alerts[] = [
                    'level' => 'critical',
                    'title' => 'Tienes obligaciones vencidas',
                    'description' => 'Revisa Cumplimiento para evitar recargos.',
                    'cta' => ['label' => 'Abrir Cumplimiento', 'href' => '/erp/compliance'],
                ];
            } elseif (($kpis['compliance']['dueSoon7d'] ?? 0) > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'Obligaciones próximas (7 días)',
                    'description' => 'Agenda estas presentaciones para evitar vencimientos.',
                    'cta' => ['label' => 'Ver calendario', 'href' => '/erp/compliance'],
                ];
            }

            return compact('alerts', 'kpis', 'dgii', 'compliance', 'charts', 'activity');
        });

        return Inertia::render('LaudaERP/Dashboard', [
            'company' => [
                'id' => (int)$company->id,
                'name' => (string)$company->name,
                'slug' => (string)$company->slug,
                'currency' => (string)$company->currency,
                'timezone' => (string)$tz,
                'active' => (bool)$company->active,
            ],
            'taxProfileReady' => $taxProfileReady,

            'range' => [
                'preset' => $preset,
                'from' => $from,
                'to' => $to,
            ],

            ...$payload,
        ]);
    }

    private function resolveRange(string $preset, Request $request, string $tz): array
    {
        $now = now($tz);

        if ($preset === 'ytd') {
            return [$now->copy()->startOfYear()->format('Y-m-d'), $now->format('Y-m-d')];
        }

        if ($preset === '30d') {
            return [$now->copy()->subDays(29)->format('Y-m-d'), $now->format('Y-m-d')];
        }

        if ($preset === '7d') {
            return [$now->copy()->subDays(6)->format('Y-m-d'), $now->format('Y-m-d')];
        }

        if ($preset === 'custom') {
            $from = $request->string('from', $now->copy()->startOfMonth()->format('Y-m-d'))->toString();
            $to   = $request->string('to', $now->format('Y-m-d'))->toString();
            // sanity: swap si viene invertido
            if ($from && $to && $from > $to) [$from, $to] = [$to, $from];
            return [$from, $to];
        }

        // default mtd
        return [$now->copy()->startOfMonth()->format('Y-m-d'), $now->format('Y-m-d')];
    }

    private function emptyKpis(): array
    {
        return [
            'revenue' => ['value' => 0, 'deltaPct' => null],
            'collections' => ['value' => 0, 'deltaPct' => null],
            'arOutstanding' => ['value' => 0],
            'apOutstanding' => ['value' => 0],
            'cashBalance' => ['value' => 0],
            'orders' => ['value' => 0, 'deltaPct' => null],
            'activeCustomers' => ['value' => 0, 'deltaPct' => null],
            'compliance' => ['overdue' => 0, 'dueSoon7d' => 0],
        ];
    }

    private function emptyDgii(): array
    {
        return [
            'tokenStatus' => 'missing', // ok|warn|expired|missing
            'tokenExpiresAt' => null,
            'environment' => null,
            'lastTokenRefreshAt' => null,
            'certStatus' => 'missing',  // ok|warn|missing|invalid
        ];
    }

    private function resolveDgiiStatusForCompany(Company $company, string $tz): array
    {
        $dgii = $this->emptyDgii();

        if (!Schema::hasTable('dgii_company_settings')) {
            return $dgii;
        }

        try {
            $settings = DB::table('dgii_company_settings')
                ->where('company_id', $company->id)
                ->first([
                    'environment',
                    'cf_prefix',
                    'dgii_access_token',
                    'dgii_token_issued_at',
                    'dgii_token_expires_at',
                    'dgii_token_last_requested_at',
                    'dgii_token_last_error',
                    'dgii_token_auto',
                    'dgii_token_refresh_before_seconds',
                ]);

            if (!$settings) {
                return $dgii;
            }

            // Environment real del tenant
            $dgii['environment'] = $settings->environment ?: null;

            // Último refresh exitoso del token
            if (!empty($settings->dgii_token_issued_at)) {
                try {
                    $dgii['lastTokenRefreshAt'] = Carbon::parse($settings->dgii_token_issued_at, $tz)->toISOString();
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // Expiración del token
            if (!empty($settings->dgii_token_expires_at)) {
                try {
                    $expiresAt = Carbon::parse($settings->dgii_token_expires_at, $tz);
                    $dgii['tokenExpiresAt'] = $expiresAt->toISOString();

                    $now = now($tz);
                    $refreshBefore = (int) ($settings->dgii_token_refresh_before_seconds ?? 0);

                    if ($expiresAt->isPast()) {
                        $dgii['tokenStatus'] = 'expired';
                    } elseif ($refreshBefore > 0 && $expiresAt->lte($now->copy()->addSeconds($refreshBefore))) {
                        $dgii['tokenStatus'] = 'warn';
                    } else {
                        $dgii['tokenStatus'] = 'ok';
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $dgii['tokenStatus'] = !empty($settings->dgii_access_token) ? 'warn' : 'missing';
                }
            } else {
                $dgii['tokenStatus'] = !empty($settings->dgii_access_token) ? 'warn' : 'missing';
            }

            // Esta tabla no tiene info suficiente de certificado
            $dgii['certStatus'] = 'missing';

            return $dgii;
        } catch (\Throwable $e) {
            report($e);
            return $dgii;
        }
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
