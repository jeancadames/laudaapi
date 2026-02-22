<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class FiscalCalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // ✅ si EnsureErpAccess ya lo puso, úsalo
        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);

        // fallback (solo si por alguna razón no pasó por middleware)
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
            $request->attributes->set('resolved_subscriber_id', $subscriberId);
        }

        abort_unless($subscriberId > 0, 403);

        $company = Company::query()
            ->where('subscriber_id', $subscriberId)
            ->orderByDesc('id')
            ->firstOrFail(['id', 'name', 'slug', 'timezone']);

        $tz = $company->timezone ?: config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($tz);
        $today = $now->toDateString(); // YYYY-MM-DD

        // ------------------------------------------------------------------
        // ✅ Items/Instances desde nuevas tablas (si existen)
        // ------------------------------------------------------------------
        $items = [];

        $hasCore =
            Schema::hasTable('obligation_instances') &&
            Schema::hasTable('tenant_obligations') &&
            Schema::hasTable('compliance_obligation_templates');

        if ($hasCore) {
            // Ventana típica (ajusta si quieres)
            $from = $now->subDays(15)->toDateString();
            $to   = $now->addDays(120)->toDateString();

            $q = DB::table('obligation_instances as oi')
                ->join('tenant_obligations as tob', 'tob.id', '=', 'oi.tenant_obligation_id')
                ->join('compliance_obligation_templates as tpl', 'tpl.id', '=', 'tob.template_id')
                ->where('oi.company_id', (int) $company->id)
                ->where('tob.enabled', 1)
                ->where(function ($w) use ($from, $to) {
                    $w->whereBetween('oi.due_date', [$from, $to])
                        ->orWhere('oi.status', 'overdue');
                })
                ->orderBy('oi.due_date')
                ->limit(500);

            $hasAuthorities = Schema::hasTable('tax_authorities');

            if ($hasAuthorities) {
                $q->leftJoin('tax_authorities as auth', 'auth.id', '=', 'tpl.authority_id');
            }

            $columns = [
                'oi.id as id',
                'oi.due_date as due_date',
                'oi.period_key as period_key',
                'oi.status as db_status',

                'tpl.name as name',
                'tpl.code as code',
            ];

            if ($hasAuthorities) {
                $columns[] = 'auth.name as authority';
            } else {
                $columns[] = DB::raw("'DGII/TSS' as authority");
            }

            $rows = $q->get($columns);

            $items = $rows->map(function ($r) use ($today) {
                $due = (string) $r->due_date;
                $dbStatus = strtolower((string) $r->db_status);

                $status = $this->normalizeStatus($dbStatus, $today, $due);
                $priority = $this->derivePriority($status, $today, $due);

                return [
                    'id'        => (int) $r->id,
                    'due_date'  => $due,
                    'name'      => (string) $r->name,
                    'authority' => (string) ($r->authority ?? '—'),
                    'code'      => $r->code !== null ? (string) $r->code : null, // ✅ string|null
                    'period_key' => (string) ($r->period_key ?? '—'),
                    'status'    => $status,
                    'priority'  => $priority,
                ];
            })->values()->all();
        }

        // ------------------------------------------------------------------
        // ✅ Stats server-side (para Index nuevo y para evitar undefined)
        // ------------------------------------------------------------------
        $stats = $this->computeStats($items, $today);

        // ------------------------------------------------------------------
        // ✅ Feed ICS (si existe calendar_feeds)
        // ------------------------------------------------------------------
        $feed = null;
        if (Schema::hasTable('calendar_feeds')) {
            $feedRow = DB::table('calendar_feeds')
                ->where('company_id', (int) $company->id)
                ->orderByDesc('enabled')
                ->orderByDesc('id')
                ->first();

            if ($feedRow) {
                $meta = $this->safeJson($feedRow->meta);
                $icsUrl = is_array($meta) ? ($meta['public_url'] ?? null) : null;

                $feed = [
                    'ics_url'         => is_string($icsUrl) ? $icsUrl : null,
                    'enabled'         => (bool) $feedRow->enabled,
                    'expires_at'      => $feedRow->expires_at ? (string) $feedRow->expires_at : null,
                    'last_rotated_at' => $feedRow->last_rotated_at ? (string) $feedRow->last_rotated_at : null,
                ];
            }
        }

        // ✅ Compat viejo
        $icsUrlCompat = $feed['ics_url'] ?? null;

        return Inertia::render('LaudaERP/Services/CalendarioFiscal/Index', [
            'company' => [
                'id'       => $company->id,
                'name'     => $company->name,
                'slug'     => $company->slug,
                'timezone' => $tz,
            ],
            'today' => $today,

            // ✅ nuevo shape
            'instances' => $items,
            'stats'     => $stats,
            'feed'      => $feed,

            // ✅ viejo shape (no rompes nada)
            'items'   => $items,
            'ics_url' => $icsUrlCompat,
        ]);
    }

    private function normalizeStatus(string $dbStatus, string $todayYmd, string $dueYmd): string
    {
        $allowed = ['pending', 'due_soon', 'overdue', 'filed', 'paid', 'not_applicable'];
        if (!in_array($dbStatus, $allowed, true)) {
            $dbStatus = 'pending';
        }

        // Si ya está finalizado, no tocar
        if ($dbStatus === 'filed' || $dbStatus === 'paid' || $dbStatus === 'not_applicable') {
            return $dbStatus;
        }

        // Derivar por fecha (evita “pending” vencido)
        $today = CarbonImmutable::createFromFormat('Y-m-d', $todayYmd)->startOfDay();
        $due = CarbonImmutable::createFromFormat('Y-m-d', $dueYmd)->startOfDay();
        $diff = (int) $today->diffInDays($due, false);

        if ($diff < 0) return 'overdue';
        if ($diff <= 7) return 'due_soon';
        return 'pending';
    }

    private function derivePriority(string $status, string $todayYmd, string $dueYmd): string
    {
        if ($status === 'overdue') return 'high';
        if ($status === 'filed' || $status === 'paid' || $status === 'not_applicable') return 'low';

        $today = CarbonImmutable::createFromFormat('Y-m-d', $todayYmd)->startOfDay();
        $due = CarbonImmutable::createFromFormat('Y-m-d', $dueYmd)->startOfDay();
        $diff = (int) $today->diffInDays($due, false);

        if ($diff >= 0 && $diff <= 3) return 'high';
        if ($diff >= 0 && $diff <= 10) return 'medium';
        return 'low';
    }

    private function computeStats(array $items, string $todayYmd): array
    {
        $total = 0;
        $done = 0;
        $overdue = 0;
        $upcoming7 = 0;

        $today = CarbonImmutable::createFromFormat('Y-m-d', $todayYmd)->startOfDay();

        foreach ($items as $it) {
            $status = (string) ($it['status'] ?? 'pending');
            if ($status === 'not_applicable') continue;

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

    private function safeJson($value)
    {
        if ($value === null) return null;
        if (is_array($value)) return $value;
        if (is_object($value)) return (array) $value;

        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
