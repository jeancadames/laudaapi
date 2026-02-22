<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FiscalComplianceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // -----------------------------
        // Tenant / Company
        // -----------------------------
        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }
        abort_unless($subscriberId > 0, 403);

        /** @var Company $company */
        $company = Company::query()
            ->where('subscriber_id', $subscriberId)
            ->firstOrFail(['id', 'name', 'slug', 'timezone']);

        $tz = $company->timezone ?: config('app.timezone', 'UTC');
        $today = CarbonImmutable::now($tz)->toDateString(); // YYYY-MM-DD

        // -----------------------------
        // Settings: company_compliance_settings
        // -----------------------------
        $settingsRow = DB::table('company_compliance_settings')
            ->where('company_id', $company->id)
            ->first();

        $settings = $settingsRow ? [
            'timezone'          => $settingsRow->timezone,
            'weekend_shift'     => $settingsRow->weekend_shift,
            'use_holidays'      => (bool) $settingsRow->use_holidays,
            'default_reminders' => $this->safeJson($settingsRow->default_reminders),
            'channels'          => $this->safeJson($settingsRow->channels),
            'meta'              => $this->safeJson($settingsRow->meta),
        ] : null;

        // -----------------------------
        // Instances: obligation_instances (+ template + authority)
        // -----------------------------
        $rows = DB::table('obligation_instances as oi')
            ->join('tenant_obligations as to', 'to.id', '=', 'oi.tenant_obligation_id')
            ->join('compliance_obligation_templates as tpl', 'tpl.id', '=', 'to.template_id')
            ->leftJoin('tax_authorities as ta', 'ta.id', '=', 'tpl.authority_id')
            ->where('oi.company_id', $company->id)
            // filtros sanos (ajusta si quieres ver histórico completo)
            ->where('to.enabled', true)
            ->where('tpl.active', true)
            ->select([
                'oi.id',
                'oi.due_date',
                'oi.period_key',
                'oi.status as db_status',

                'tpl.name as tpl_name',
                'tpl.code as tpl_code',

                DB::raw("COALESCE(ta.name, '—') as authority_name"),
            ])
            ->orderBy('oi.due_date')
            ->limit(1500)
            ->get();

        $instances = [];
        foreach ($rows as $r) {
            $due = (string) $r->due_date;           // YYYY-MM-DD
            $dbStatus = (string) $r->db_status;     // pending|... (o legacy)

            $uiStatus = $this->normalizeStatus($dbStatus, $today, $due);
            $priority = $this->derivePriority($uiStatus, $today, $due);

            $instances[] = [
                'id'         => (int) $r->id,
                'due_date'   => $due,
                'name'       => (string) $r->tpl_name,
                'authority'  => (string) $r->authority_name,
                'code'       => $r->tpl_code ? (string) $r->tpl_code : null,
                'period_key' => (string) $r->period_key,
                'status'     => $uiStatus,
                'priority'   => $priority,
            ];
        }

        // -----------------------------
        // Stats: server-side (shape exacto del front)
        // -----------------------------
        $stats = $this->computeStats($instances, $today);

        // -----------------------------
        // Calendar feed: calendar_feeds
        // -----------------------------
        $feedRow = DB::table('calendar_feeds')
            ->where('company_id', $company->id)
            ->orderByDesc('enabled')
            ->orderByDesc('id')
            ->first();

        $feed = $this->normalizeFeed($feedRow);

        return Inertia::render('LaudaERP/Services/CumplimientoFiscal/Index', [
            'company' => [
                'id'       => $company->id,
                'name'     => $company->name,
                'slug'     => $company->slug,
                'timezone' => $tz,
            ],
            'today'     => $today,
            'stats'     => $stats,
            'instances' => $instances,
            'settings'  => $settings,
            'feed'      => $feed,
        ]);
    }

    // -----------------------------
    // Helpers
    // -----------------------------
    private function computeStats(array $instances, string $todayYmd): array
    {
        $total = 0;
        $done = 0;
        $overdue = 0;
        $upcoming7 = 0;

        $today = CarbonImmutable::createFromFormat('Y-m-d', $todayYmd)->startOfDay();

        foreach ($instances as $it) {
            $status = (string) ($it['status'] ?? 'pending');
            if ($status === 'not_applicable') {
                continue;
            }

            $total++;

            if ($status === 'filed' || $status === 'paid') $done++;
            if ($status === 'overdue') $overdue++;

            if ($status === 'pending' || $status === 'due_soon') {
                $due = CarbonImmutable::createFromFormat('Y-m-d', (string) $it['due_date'])->startOfDay();
                $diff = (int) $today->diffInDays($due, false);
                if ($diff >= 0 && $diff <= 7) $upcoming7++;
            }
        }

        $completion = $total > 0 ? ($done / $total) * 100 : 0;

        return [
            'total'           => $total,
            'upcoming7'       => $upcoming7,
            'overdue'         => $overdue,
            'done'            => $done,
            'completion_rate' => $completion,
        ];
    }

    private function normalizeStatus(string $dbStatus, string $todayYmd, string $dueYmd): string
    {
        // Estados “UI” permitidos
        $allowed = ['pending', 'due_soon', 'overdue', 'filed', 'paid', 'not_applicable'];

        if (in_array($dbStatus, $allowed, true)) {
            // Ajuste por fecha si viene pending/due_soon
            if ($dbStatus === 'pending' || $dbStatus === 'due_soon') {
                $today = CarbonImmutable::createFromFormat('Y-m-d', $todayYmd)->startOfDay();
                $due = CarbonImmutable::createFromFormat('Y-m-d', $dueYmd)->startOfDay();
                $diff = (int) $today->diffInDays($due, false);

                if ($diff < 0) return 'overdue';
                if ($diff <= 7) return 'due_soon';
                return 'pending';
            }

            return $dbStatus;
        }

        // Map de legacy (ajusta si tienes otros)
        if ($dbStatus === 'completed') return 'filed';
        if ($dbStatus === 'closed') return 'filed';

        // Derivado por fecha
        $today = CarbonImmutable::createFromFormat('Y-m-d', $todayYmd)->startOfDay();
        $due = CarbonImmutable::createFromFormat('Y-m-d', $dueYmd)->startOfDay();
        $diff = (int) $today->diffInDays($due, false);

        if ($diff < 0) return 'overdue';
        if ($diff <= 7) return 'due_soon';
        return 'pending';
    }

    private function derivePriority(string $uiStatus, string $todayYmd, string $dueYmd): string
    {
        if ($uiStatus === 'overdue') return 'high';
        if ($uiStatus === 'filed' || $uiStatus === 'paid' || $uiStatus === 'not_applicable') return 'low';

        $today = CarbonImmutable::createFromFormat('Y-m-d', $todayYmd)->startOfDay();
        $due = CarbonImmutable::createFromFormat('Y-m-d', $dueYmd)->startOfDay();
        $diff = (int) $today->diffInDays($due, false);

        // Reglas sencillas (ajusta si quieres)
        if ($diff >= 0 && $diff <= 3) return 'high';
        if ($diff >= 0 && $diff <= 10) return 'medium';
        return 'low';
    }

    private function normalizeFeed(?object $feedRow): ?array
    {
        if (!$feedRow) return null;

        $meta = $this->safeJson($feedRow->meta);

        /**
         * ⚠️ IMPORTANTE:
         * Con tu esquema guardas token_hash (sha256), eso es correcto,
         * pero NO puedes reconstruir la URL real sin guardar algo en claro.
         *
         * Recomendación: guardar el URL público en meta->public_url al crearlo/rotarlo.
         */
        $icsUrl = is_array($meta) ? ($meta['public_url'] ?? null) : null;

        return [
            'ics_url'         => is_string($icsUrl) ? $icsUrl : null,
            'enabled'         => (bool) $feedRow->enabled,
            'expires_at'      => $feedRow->expires_at ? (string) $feedRow->expires_at : null,
            'last_rotated_at' => $feedRow->last_rotated_at ? (string) $feedRow->last_rotated_at : null,
        ];
    }

    private function safeJson($value)
    {
        if ($value === null) return null;
        if (is_array($value)) return $value;
        if (is_object($value)) return (array) $value;

        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
