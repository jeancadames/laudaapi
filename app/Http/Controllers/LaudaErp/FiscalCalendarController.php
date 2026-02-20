<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;
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
            ->firstOrFail();

        $tz = $company->timezone ?: config('app.timezone');
        $today = now($tz)->toDateString();

        $items = [];

        // ------------------------------------------------------------------
        // ✅ Items reales desde nuevas migraciones (si existen tablas)
        // ------------------------------------------------------------------
        $hasCore =
            Schema::hasTable('obligation_instances') &&
            Schema::hasTable('tenant_obligations') &&
            Schema::hasTable('compliance_obligation_templates');

        if ($hasCore) {
            // ventana típica (ajusta si quieres)
            $from = now($tz)->subDays(15)->toDateString();
            $to   = now($tz)->addDays(120)->toDateString();

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

            // ✅ select único (no mezclar addSelect + get([...]) para no pisar columnas)
            $columns = [
                'oi.id as id',
                'oi.due_date as due_date',
                'oi.period_key as period_key',
                'oi.status as status',

                'tpl.name as name',
                'tpl.code as code',
            ];

            if ($hasAuthorities) {
                $columns[] = 'auth.name as authority';
            } else {
                $columns[] = DB::raw("'DGII/TSS' as authority");
            }

            $rows = $q->get($columns);

            $items = $rows->map(function ($r) {
                $st = strtolower((string) $r->status);

                // ✅ no inventar status "upcoming/done": devuelve el status real de oi
                // y el UI lo pinta.
                $status = in_array($st, ['pending', 'due_soon', 'overdue', 'filed', 'paid', 'not_applicable'], true)
                    ? $st
                    : 'pending';

                // prioridad simple
                $priority = 'low';
                if ($status === 'overdue') {
                    $priority = 'high';
                } elseif ($status === 'due_soon') {
                    $priority = 'high';
                } elseif ($status === 'pending') {
                    $priority = 'medium';
                }

                return [
                    'id' => (int) $r->id,
                    'due_date' => (string) $r->due_date,
                    'name' => (string) $r->name,
                    'authority' => (string) ($r->authority ?? '—'),
                    'code' => (string) ($r->code ?? ''),
                    'period_key' => (string) ($r->period_key ?? '—'),
                    'status' => $status,
                    'priority' => $priority,
                ];
            })->values()->all();
        }

        // Por ahora ics_url queda null hasta que implementes calendar_feeds
        $icsUrl = null;

        return Inertia::render('LaudaERP/Services/CalendarioFiscal/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'timezone' => $tz,
            ],
            'today' => $today,
            'items' => $items,
            'ics_url' => $icsUrl,
        ]);
    }
}
