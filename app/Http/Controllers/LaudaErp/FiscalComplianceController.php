<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Dgii\DgiiCertificateRequirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class FiscalComplianceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // ✅ si EnsureErpAccess ya lo puso, úsalo
        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);

        // fallback (solo si por alguna razón no pasó por el middleware)
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

        // ------------------------------------------------------------------
        // ✅ Certificados reales (MISMA fuente que Certificación Emisor)
        // ------------------------------------------------------------------
        $certReq = app(DgiiCertificateRequirements::class)->checkForCompany((int) $company->id);
        $certOk  = (bool) ($certReq['has_usable_signer'] ?? false);

        // ------------------------------------------------------------------
        // ✅ Obligaciones reales (nuevas migraciones)
        // ------------------------------------------------------------------
        $obligations = [];

        if (
            Schema::hasTable('obligation_instances') &&
            Schema::hasTable('tenant_obligations') &&
            Schema::hasTable('compliance_obligation_templates')
        ) {
            $from = now($tz)->subDays(30)->toDateString();
            $to   = now($tz)->addDays(120)->toDateString();

            $q = DB::table('obligation_instances as oi')
                ->join('tenant_obligations as tob', 'tob.id', '=', 'oi.tenant_obligation_id')
                ->join('compliance_obligation_templates as t', 't.id', '=', 'tob.template_id')
                ->where('oi.company_id', (int) $company->id)
                ->where('tob.enabled', 1)
                ->where(function ($w) use ($from, $to) {
                    $w->whereBetween('oi.due_date', [$from, $to])
                        ->orWhere('oi.status', 'overdue');
                })
                ->orderBy('oi.due_date')
                ->limit(250);

            $select = [
                'oi.id',
                'oi.due_date',
                'oi.period_key',
                'oi.status',
                't.name as template_name',
                't.code as template_code',
            ];

            // Join opcional a tax_authorities (por si todavía no la creaste)
            if (Schema::hasTable('tax_authorities')) {
                $q->leftJoin('tax_authorities as a', 'a.id', '=', 't.authority_id');
                $select[] = 'a.name as authority_name';
            } else {
                $select[] = DB::raw("'DGII/TSS' as authority_name");
            }

            $rows = $q->select($select)->get();

            $obligations = $rows->map(function ($r) {
                return [
                    'id'         => (int) $r->id,
                    'due_date'   => (string) $r->due_date,
                    'period_key' => (string) $r->period_key,
                    'status'     => (string) $r->status,
                    'name'       => (string) $r->template_name,
                    'authority'  => (string) ($r->authority_name ?? '—'),
                    'code'       => $r->template_code ? (string) $r->template_code : null,
                ];
            })->values()->all();
        }

        // ------------------------------------------------------------------
        // ✅ Checks (shape correcto para tu Index.vue nuevo)
        // ------------------------------------------------------------------
        $overdue = collect($obligations)->where('status', 'overdue')->count();
        $dueSoon = collect($obligations)->where('status', 'due_soon')->count();

        $checks = [
            [
                'key' => 'certs',
                'title' => 'Certificados DGII',
                'status' => $certOk ? 'ok' : 'fail',
                'hint' => $certOk
                    ? 'Listo para firmar (firmador usable detectado).'
                    : (string) (($certReq['why_blocked'] ?? null) ?: 'Revisa certificados (P12/PFX + password guardado).'),
            ],
            [
                'key' => 'obligations',
                'title' => 'Obligaciones generadas',
                'status' => count($obligations) > 0 ? 'ok' : 'warn',
                'hint' => count($obligations) > 0
                    ? 'Instancias listas (obligation_instances).'
                    : 'Aún no hay instancias: falta job/command que materialice los templates por periodo.',
            ],
            [
                'key' => 'overdue',
                'title' => 'Vencidos',
                'status' => $overdue > 0 ? 'fail' : ($dueSoon > 0 ? 'warn' : 'ok'),
                'hint' => $overdue > 0
                    ? "Tienes {$overdue} obligaciones vencidas."
                    : ($dueSoon > 0 ? "Tienes {$dueSoon} obligaciones por vencer." : 'Sin vencidos por ahora.'),
            ],
        ];

        // ------------------------------------------------------------------
        // ✅ Risks (simple y útil)
        // ------------------------------------------------------------------
        $risks = [];

        if ($overdue > 0) {
            $risks[] = [
                'level' => 'high',
                'title' => 'Obligaciones vencidas',
                'detail' => "Tienes {$overdue} obligaciones vencidas. Prioriza regularización para evitar sanciones/rechazos.",
            ];
        } elseif ($dueSoon > 0) {
            $risks[] = [
                'level' => 'medium',
                'title' => 'Obligaciones por vencer',
                'detail' => "Tienes {$dueSoon} obligaciones por vencer. Programa recordatorios y valida pagos/acuse.",
            ];
        } else {
            $risks[] = [
                'level' => 'low',
                'title' => 'Riesgo bajo',
                'detail' => 'No se detectan vencidos ni próximos críticos en la ventana actual.',
            ];
        }

        if (!$certOk) {
            $risks[] = [
                'level' => 'high',
                'title' => 'Firma no lista',
                'detail' => 'No hay firmador usable (P12/PFX con password guardado). Esto bloquea firma/envío a DGII.',
            ];
        }

        return Inertia::render('LaudaERP/Services/CumplimientoFiscal/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'timezone' => $tz,
            ],
            'today' => $today,
            'checks' => $checks,
            'risks' => $risks,
            'obligations' => $obligations,

            // opcional
            'cert_requirements' => $certReq,
        ]);
    }
}
