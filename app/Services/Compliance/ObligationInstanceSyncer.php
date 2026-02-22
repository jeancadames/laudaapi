<?php

namespace App\Services\Compliance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ObligationInstanceSyncer
{
    public function __construct(
        private readonly DueDateCalculator $calculator,
    ) {}

    /**
     * Genera/actualiza obligation_instances desde tenant_obligations(enabled=1).
     * - NO pisa status (solo fechas)
     * - Recalcula pending/due_soon/overdue para open (pending|due_soon)
     */
    public function syncCompany(
        int $companyId,
        int $monthsAhead = 18,
        int $monthsBack = 3,
        int $dueSoonDays = 7,
        bool $markDisabledAsNotApplicable = true
    ): array {
        $today = CarbonImmutable::now()->startOfDay();
        $startMonth = $today->startOfMonth()->subMonths($monthsBack);
        $endMonth = $today->startOfMonth()->addMonths($monthsAhead);

        // 1) tenant_obligations habilitadas + template
        $tobs = DB::table('tenant_obligations as tob')
            ->join('compliance_obligation_templates as tpl', 'tpl.id', '=', 'tob.template_id')
            ->where('tob.company_id', $companyId)
            ->where('tob.enabled', 1)
            ->where('tpl.active', 1)
            ->get([
                'tob.id as tenant_obligation_id',
                'tob.company_id as company_id',
                'tob.template_id as template_id',
                'tpl.frequency as frequency',
                'tpl.due_rule as due_rule',
            ]);

        $rows = [];

        foreach ($tobs as $t) {
            $frequency = (string)$t->frequency;
            $templateId = (int)$t->template_id;

            $rule = is_string($t->due_rule) ? json_decode($t->due_rule, true) : null;
            if (!is_array($rule)) $rule = [];

            // year_table necesita template_id para overrides
            $rule['template_id'] = $templateId;

            if ($frequency === 'monthly') {
                $m = $startMonth;
                while ($m <= $endMonth) {
                    $periodKey = $m->format('Y-m');
                    $periodStart = $m->startOfMonth();
                    $periodEnd = $m->endOfMonth();

                    $due = $this->calculator->computeDueDate($rule, $periodKey);
                    if ($due) {
                        $rows[] = [
                            'tenant_obligation_id' => (int)$t->tenant_obligation_id,
                            'company_id' => $companyId,

                            'period_start' => $periodStart->toDateString(),
                            'period_end' => $periodEnd->toDateString(),
                            'period_key' => $periodKey,

                            'due_date' => $due->toDateString(),
                            'due_at' => $due->endOfDay(), // timestamp (Laravel lo serializa)

                            'updated_at' => now(),
                            'created_at' => now(),
                        ];
                    }

                    $m = $m->addMonth();
                }
            }

            if ($frequency === 'annual') {
                $startYear = (int)$startMonth->format('Y');
                $endYear = (int)$endMonth->format('Y');

                for ($y = $startYear; $y <= $endYear; $y++) {
                    $periodKey = (string)$y;

                    $periodStart = CarbonImmutable::create($y, 1, 1)->startOfDay();
                    $periodEnd = CarbonImmutable::create($y, 12, 31)->startOfDay();

                    $due = $this->calculator->computeDueDate($rule, $periodKey);
                    if ($due) {
                        $rows[] = [
                            'tenant_obligation_id' => (int)$t->tenant_obligation_id,
                            'company_id' => $companyId,

                            'period_start' => $periodStart->toDateString(),
                            'period_end' => $periodEnd->toDateString(),
                            'period_key' => $periodKey,

                            'due_date' => $due->toDateString(),
                            'due_at' => $due->endOfDay(),

                            'updated_at' => now(),
                            'created_at' => now(),
                        ];
                    }
                }
            }
        }

        // 2) upsert (key: tenant_obligation_id + period_key)
        $upserted = 0;
        if (count($rows) > 0) {
            DB::table('obligation_instances')->upsert(
                $rows,
                ['tenant_obligation_id', 'period_key'],
                ['company_id', 'period_start', 'period_end', 'due_date', 'due_at', 'updated_at']
            );
            $upserted = count($rows);
        }

        // 3) Opcional: marcar como not_applicable lo que quedó “disabled”
        $disabledMarked = 0;
        if ($markDisabledAsNotApplicable) {
            $enabledTobIds = $tobs->pluck('tenant_obligation_id')->map(fn($x) => (int)$x)->values()->all();

            $disabledMarked = DB::table('obligation_instances')
                ->where('company_id', $companyId)
                ->whereNotIn('tenant_obligation_id', $enabledTobIds)
                ->whereDate('due_date', '>=', $today->toDateString())
                ->whereIn('status', ['pending', 'due_soon'])
                ->update([
                    'status' => 'not_applicable',
                    'status_reason' => 'tenant_obligation_disabled',
                    'updated_at' => now(),
                ]);
        }

        // 4) Recalcular estados (solo abiertos)
        $recalc = $this->recalculateOpenStatuses($companyId, $dueSoonDays);

        return [
            'upserted' => $upserted,
            'disabled_marked' => (int)$disabledMarked,
            'status_recalc' => $recalc,
        ];
    }

    private function recalculateOpenStatuses(int $companyId, int $dueSoonDays = 7): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $soon = $today->addDays($dueSoonDays);

        // overdue: due_date < today, status pending|due_soon, y no completado
        $overdue = DB::table('obligation_instances')
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'due_soon'])
            ->whereNull('filed_at')
            ->whereNull('paid_at')
            ->whereDate('due_date', '<', $today->toDateString())
            ->update([
                'status' => 'overdue',
                'status_reason' => 'past_due',
                'updated_at' => now(),
            ]);

        // due_soon: due_date entre hoy y soon, status pending
        $dueSoon = DB::table('obligation_instances')
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->whereNull('filed_at')
            ->whereNull('paid_at')
            ->whereBetween('due_date', [$today->toDateString(), $soon->toDateString()])
            ->update([
                'status' => 'due_soon',
                'status_reason' => 'due_within_window',
                'updated_at' => now(),
            ]);

        // pending: si estaba due_soon pero ya se fue fuera de ventana y no está overdue
        $backToPending = DB::table('obligation_instances')
            ->where('company_id', $companyId)
            ->where('status', 'due_soon')
            ->whereNull('filed_at')
            ->whereNull('paid_at')
            ->whereDate('due_date', '>', $soon->toDateString())
            ->update([
                'status' => 'pending',
                'status_reason' => null,
                'updated_at' => now(),
            ]);

        return [
            'overdue' => (int)$overdue,
            'due_soon' => (int)$dueSoon,
            'back_to_pending' => (int)$backToPending,
        ];
    }
}
