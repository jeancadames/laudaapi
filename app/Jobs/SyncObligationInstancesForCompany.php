<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Compliance\DueDateCalculator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncObligationInstancesForCompany implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $companyId,
        public int $monthsAhead = 18,
        public int $backMonths = 3,
        public int $soonDays = 7,
    ) {}

    public function handle(DueDateCalculator $due): void
    {
        $company = Company::query()->findOrFail($this->companyId);

        $tz = $company->timezone ?: config('app.timezone', 'UTC');
        $today = CarbonImmutable::now($tz)->startOfDay();

        // Si luego agregas esto en DB: company.weekend_shift, aquí lo lees.
        $companyCtx = [
            'id' => (int) $company->id,
            'timezone' => (string) $tz,
            'weekend_shift' => 'next_business_day',
        ];

        // Trae tenant_obligations + template
        $rows = DB::table('tenant_obligations as to')
            ->join('compliance_obligation_templates as tpl', 'tpl.id', '=', 'to.template_id')
            ->where('to.company_id', $company->id)
            ->where('tpl.active', 1)
            ->get([
                'to.id as tenant_obligation_id',
                'to.enabled',
                'to.starts_on',
                'to.ends_on',
                'to.template_id',

                'tpl.id as template_id2',
                'tpl.code',
                'tpl.name',
                'tpl.frequency',
                'tpl.due_rule',
            ]);

        if ($rows->isEmpty()) return;

        DB::transaction(function () use ($rows, $due, $company, $companyCtx, $today, $tz) {
            foreach ($rows as $row) {
                $tenantOblId = (int) $row->tenant_obligation_id;

                // Si está disabled, marca FUTUROS como not_applicable y sigue
                if (!(bool) $row->enabled) {
                    DB::table('obligation_instances')
                        ->where('tenant_obligation_id', $tenantOblId)
                        ->whereIn('status', ['pending', 'due_soon'])
                        ->whereDate('due_date', '>=', $today->toDateString())
                        ->update([
                            'status' => 'not_applicable',
                            'status_reason' => 'disabled_by_company',
                            'updated_at' => now(),
                        ]);
                    continue;
                }

                $freq = strtolower((string) $row->frequency);
                if (!in_array($freq, ['monthly', 'annual'], true)) continue;

                $startsOn = $row->starts_on ? CarbonImmutable::parse($row->starts_on, $tz)->startOfDay() : null;
                $endsOn   = $row->ends_on ? CarbonImmutable::parse($row->ends_on, $tz)->startOfDay() : null;

                $template = [
                    'id' => (int) $row->template_id2,
                    'code' => (string) $row->code,
                    'name' => (string) $row->name,
                    'frequency' => (string) $row->frequency,
                    'due_rule' => $row->due_rule, // puede venir string JSON
                ];

                if ($freq === 'monthly') {
                    $this->syncMonthly(
                        due: $due,
                        companyCtx: $companyCtx,
                        tenantObligationId: $tenantOblId,
                        companyId: (int) $company->id,
                        template: $template,
                        today: $today,
                        tz: $tz,
                        monthsAhead: $this->monthsAhead,
                        backMonths: $this->backMonths,
                        soonDays: $this->soonDays,
                        startsOn: $startsOn,
                        endsOn: $endsOn
                    );
                }

                if ($freq === 'annual') {
                    $this->syncAnnual(
                        due: $due,
                        companyCtx: $companyCtx,
                        tenantObligationId: $tenantOblId,
                        companyId: (int) $company->id,
                        template: $template,
                        today: $today,
                        tz: $tz,
                        yearsAhead: 2,
                        soonDays: $this->soonDays,
                        startsOn: $startsOn,
                        endsOn: $endsOn
                    );
                }
            }
        });
    }

    private function syncMonthly(
        DueDateCalculator $due,
        array $companyCtx,
        int $tenantObligationId,
        int $companyId,
        array $template,
        CarbonImmutable $today,
        string $tz,
        int $monthsAhead,
        int $backMonths,
        int $soonDays,
        ?CarbonImmutable $startsOn,
        ?CarbonImmutable $endsOn
    ): void {
        $start = $today->startOfMonth()->subMonths($backMonths);
        $end   = $today->startOfMonth()->addMonths($monthsAhead);

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->addMonth()) {
            $periodStart = $cursor->startOfMonth();
            $periodEnd   = $cursor->endOfMonth();
            $periodKey   = $cursor->format('Y-m');

            if ($startsOn && $periodEnd->lt($startsOn)) continue;
            if ($endsOn && $periodStart->gt($endsOn)) continue;

            $dueDate = $due->computeDueDate($template, $companyCtx, $periodKey, $periodStart)->setTimezone($tz)->startOfDay();
            $dueAt = $dueDate->endOfDay(); // ✅ recomendado en MySQL para “vence hoy”

            [$status, $reason] = $this->computeStatus($dueDate, $today, $soonDays);

            $existing = DB::table('obligation_instances')
                ->where('tenant_obligation_id', $tenantObligationId)
                ->where('period_key', $periodKey)
                ->first(['id', 'status']);

            // Si ya está finalizado, NO le cambies el status
            $final = $existing && in_array($existing->status, ['filed', 'paid', 'not_applicable'], true);

            if ($existing) {
                DB::table('obligation_instances')
                    ->where('id', $existing->id)
                    ->update([
                        'company_id' => $companyId,
                        'period_start' => $periodStart->toDateString(),
                        'period_end' => $periodEnd->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'due_at' => $dueAt->toDateTimeString(),
                        'status' => $final ? $existing->status : $status,
                        'status_reason' => $final ? null : $reason,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('obligation_instances')->insert([
                    'tenant_obligation_id' => $tenantObligationId,
                    'company_id' => $companyId,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'period_key' => $periodKey,
                    'due_date' => $dueDate->toDateString(),
                    'due_at' => $dueAt->toDateTimeString(),
                    'status' => $status,
                    'status_reason' => $reason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function syncAnnual(
        DueDateCalculator $due,
        array $companyCtx,
        int $tenantObligationId,
        int $companyId,
        array $template,
        CarbonImmutable $today,
        string $tz,
        int $yearsAhead,
        int $soonDays,
        ?CarbonImmutable $startsOn,
        ?CarbonImmutable $endsOn
    ): void {
        $yearStart = $today->year - 1;
        $yearEnd   = $today->year + $yearsAhead;

        for ($y = $yearStart; $y <= $yearEnd; $y++) {
            $periodKey = (string) $y;
            $periodStart = CarbonImmutable::create($y, 1, 1, 0, 0, 0, $tz)->startOfDay();

            $dueDate = $due->computeDueDate($template, $companyCtx, $periodKey, $periodStart)->startOfDay();
            $dueAt = $dueDate->endOfDay();

            if ($startsOn && $dueDate->lt($startsOn)) continue;
            if ($endsOn && $dueDate->gt($endsOn)) continue;

            [$status, $reason] = $this->computeStatus($dueDate, $today, $soonDays);

            $existing = DB::table('obligation_instances')
                ->where('tenant_obligation_id', $tenantObligationId)
                ->where('period_key', $periodKey)
                ->first(['id', 'status']);

            $final = $existing && in_array($existing->status, ['filed', 'paid', 'not_applicable'], true);

            if ($existing) {
                DB::table('obligation_instances')
                    ->where('id', $existing->id)
                    ->update([
                        'company_id' => $companyId,
                        'period_start' => null,
                        'period_end' => null,
                        'due_date' => $dueDate->toDateString(),
                        'due_at' => $dueAt->toDateTimeString(),
                        'status' => $final ? $existing->status : $status,
                        'status_reason' => $final ? null : $reason,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('obligation_instances')->insert([
                    'tenant_obligation_id' => $tenantObligationId,
                    'company_id' => $companyId,
                    'period_start' => null,
                    'period_end' => null,
                    'period_key' => $periodKey,
                    'due_date' => $dueDate->toDateString(),
                    'due_at' => $dueAt->toDateTimeString(),
                    'status' => $status,
                    'status_reason' => $reason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function computeStatus(CarbonImmutable $due, CarbonImmutable $today, int $soonDays): array
    {
        if ($due->lt($today)) return ['overdue', 'auto:past_due'];

        $diff = $today->diffInDays($due, false);
        if ($diff >= 0 && $diff <= $soonDays) return ['due_soon', "auto:due_within_{$soonDays}d"];

        return ['pending', null];
    }
}
