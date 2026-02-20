<?php

namespace App\Jobs\Compliance;

use App\Services\Compliance\DueDateEngine;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class GenerateObligationInstancesForCompany implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public int $companyId,
        public int $monthsAhead = 12,
        public int $includePastMonths = 1, // incluye mes anterior
    ) {}

    public function handle(DueDateEngine $engine): void
    {
        $company = DB::table('companies')->where('id', $this->companyId)->first();
        if (!$company) return;

        $settings = DB::table('company_compliance_settings')
            ->where('company_id', $this->companyId)
            ->first();

        $companyCtx = [
            'timezone' => $settings->timezone ?? $company->timezone ?? 'UTC',
            'weekend_shift' => $settings->weekend_shift ?? 'next_business_day',
        ];

        $now = CarbonImmutable::now($companyCtx['timezone']);

        $first = $now->startOfMonth()->subMonths($this->includePastMonths);
        $totalMonths = max(1, $this->monthsAhead + $this->includePastMonths);

        $tenantObligations = DB::table('tenant_obligations as to')
            ->join('compliance_obligation_templates as t', 't.id', '=', 'to.template_id')
            ->where('to.company_id', $this->companyId)
            ->where('to.enabled', true)
            ->select([
                'to.id as tenant_obligation_id',
                'to.company_id',
                'to.starts_on',
                'to.ends_on',
                't.id as template_id',
                't.code',
                't.name',
                't.frequency',
                't.due_rule',
            ])
            ->get();

        DB::transaction(function () use ($engine, $tenantObligations, $first, $totalMonths, $companyCtx, $now) {

            foreach ($tenantObligations as $row) {
                $template = [
                    'id' => (int)$row->template_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'frequency' => $row->frequency,
                    'due_rule' => json_decode($row->due_rule, true) ?: [],
                ];

                // MVP: monthly only
                if (($template['frequency'] ?? null) !== 'monthly') continue;

                for ($i = 0; $i < $totalMonths; $i++) {
                    $periodStart = $first->addMonthsNoOverflow($i)->startOfMonth();
                    $periodEnd   = $periodStart->endOfMonth();
                    $periodKey   = $periodStart->format('Y-m');

                    // starts_on / ends_on gates
                    if ($row->starts_on && $periodStart->lt(CarbonImmutable::parse($row->starts_on))) continue;
                    if ($row->ends_on && $periodStart->gt(CarbonImmutable::parse($row->ends_on))) continue;

                    $due = $engine->computeDueDate($template, $companyCtx, $periodKey, $periodStart);

                    $status = $due->lt($now->startOfDay()) ? 'overdue' : 'pending';

                    DB::table('obligation_instances')->updateOrInsert(
                        [
                            'tenant_obligation_id' => (int)$row->tenant_obligation_id,
                            'period_key' => $periodKey,
                        ],
                        [
                            'company_id' => (int)$row->company_id,
                            'period_start' => $periodStart->toDateString(),
                            'period_end' => $periodEnd->toDateString(),
                            'due_date' => $due->toDateString(),
                            'status' => $status,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        });
    }
}
