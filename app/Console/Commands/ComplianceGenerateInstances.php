<?php

namespace App\Console\Commands;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComplianceGenerateInstances extends Command
{
    protected $signature = 'compliance:generate-instances
        {--company_id= : Solo para una compañía}
        {--months=6 : Horizonte de meses hacia adelante (mensual)}
        {--lookback=2 : Meses hacia atrás (mensual)}
        {--due_soon_days=7 : Ventana para marcar due_soon}';

    protected $description = 'Materializa obligation_instances desde templates + tenant_obligations (MVP).';

    public function handle(): int
    {
        if (
            !Schema::hasTable('tenant_obligations') ||
            !Schema::hasTable('compliance_obligation_templates') ||
            !Schema::hasTable('obligation_instances')
        ) {
            $this->error('Faltan tablas base (tenant_obligations, compliance_obligation_templates, obligation_instances).');
            return self::FAILURE;
        }

        $companyId = (int) ($this->option('company_id') ?: 0);
        $months = max(1, (int) $this->option('months'));
        $lookback = max(0, (int) $this->option('lookback'));
        $dueSoonDays = max(1, (int) $this->option('due_soon_days'));

        $companies = Company::query()
            ->when($companyId > 0, fn($q) => $q->where('id', $companyId))
            ->get(['id', 'timezone']);

        foreach ($companies as $company) {
            $tz = $company->timezone ?: config('app.timezone');
            $today = Carbon::now($tz)->startOfDay();

            $tenantObligations = DB::table('tenant_obligations as to')
                ->join('compliance_obligation_templates as t', 't.id', '=', 'to.template_id')
                ->where('to.company_id', (int) $company->id)
                ->where('to.enabled', 1)
                ->where('t.active', 1)
                ->get([
                    'to.id as tenant_obligation_id',
                    'to.company_id',
                    'to.starts_on',
                    'to.ends_on',
                    'to.overrides',
                    't.id as template_id',
                    't.code',
                    't.name',
                    't.frequency',
                    't.due_rule',
                ]);

            if ($tenantObligations->isEmpty()) {
                $this->line("Company {$company->id}: no tenant_obligations enabled.");
                continue;
            }

            DB::beginTransaction();
            try {
                foreach ($tenantObligations as $row) {
                    $freq = strtolower((string) $row->frequency);

                    if ($freq !== 'monthly' && $freq !== 'annual') {
                        // MVP: soportamos monthly y annual
                        continue;
                    }

                    // ventanas onboarding
                    $startsOn = $row->starts_on ? Carbon::parse($row->starts_on, $tz)->startOfDay() : null;
                    $endsOn   = $row->ends_on ? Carbon::parse($row->ends_on, $tz)->startOfDay() : null;

                    $dueRule = json_decode((string) $row->due_rule, true) ?: [];
                    $type = strtolower((string) ($dueRule['type'] ?? ''));

                    if ($freq === 'monthly') {
                        $this->generateMonthly(
                            companyId: (int) $company->id,
                            tenantObligationId: (int) $row->tenant_obligation_id,
                            templateId: (int) $row->template_id,
                            dueRule: $dueRule,
                            type: $type,
                            tz: $tz,
                            today: $today,
                            monthsAhead: $months,
                            lookback: $lookback,
                            dueSoonDays: $dueSoonDays,
                            startsOn: $startsOn,
                            endsOn: $endsOn
                        );
                    }

                    if ($freq === 'annual') {
                        $this->generateAnnual(
                            companyId: (int) $company->id,
                            tenantObligationId: (int) $row->tenant_obligation_id,
                            templateId: (int) $row->template_id,
                            dueRule: $dueRule,
                            type: $type,
                            tz: $tz,
                            today: $today,
                            yearsAhead: 1,
                            dueSoonDays: $dueSoonDays,
                            startsOn: $startsOn,
                            endsOn: $endsOn
                        );
                    }
                }

                DB::commit();
                $this->info("Company {$company->id}: instances generated/updated.");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Company {$company->id}: ERROR {$e->getMessage()}");
                report($e);
            }
        }

        return self::SUCCESS;
    }

    private function generateMonthly(
        int $companyId,
        int $tenantObligationId,
        int $templateId,
        array $dueRule,
        string $type,
        string $tz,
        Carbon $today,
        int $monthsAhead,
        int $lookback,
        int $dueSoonDays,
        ?Carbon $startsOn,
        ?Carbon $endsOn
    ): void {
        // period = mes a reportar (por defecto: mes anterior)
        // due_date = period + month_offset, day = dueRule.day
        $day = (int) ($dueRule['day'] ?? 15);
        $monthOffset = (int) ($dueRule['month_offset'] ?? 1);
        $shift = (string) ($dueRule['shift'] ?? 'next_business_day'); // next_business_day|previous_business_day|none

        // iteramos periodos desde lookback hasta monthsAhead
        $start = $today->copy()->startOfMonth()->subMonths($lookback);
        $end   = $today->copy()->startOfMonth()->addMonths($monthsAhead);

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $periodStart = $cursor->copy()->startOfMonth();
            $periodEnd   = $cursor->copy()->endOfMonth();
            $periodKey   = $cursor->format('Y-m');

            // onboarding boundaries
            if ($startsOn && $periodEnd->lt($startsOn)) {
                $cursor->addMonth();
                continue;
            }
            if ($endsOn && $periodStart->gt($endsOn)) {
                $cursor->addMonth();
                continue;
            }

            // due date month = period + offset
            $dueMonth = $cursor->copy()->addMonths($monthOffset)->startOfMonth();

            // clamp day a fin de mes
            $dueDay = min($day, (int) $dueMonth->daysInMonth);
            $due = Carbon::create($dueMonth->year, $dueMonth->month, $dueDay, 0, 0, 0, $tz);

            // overrides (si existe tabla)
            $due = $this->applyDueOverrideIfAny($templateId, $periodKey, $due, $tz);

            // shift weekend
            $due = $this->shiftWeekend($due, $shift);

            $status = $this->computeStatus($due, $today, $dueSoonDays);

            DB::table('obligation_instances')->updateOrInsert(
                [
                    'tenant_obligation_id' => $tenantObligationId,
                    'period_key' => $periodKey,
                ],
                [
                    'company_id' => $companyId,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'due_date' => $due->toDateString(),
                    'status' => $status,
                    'updated_at' => now(),
                    'created_at' => now(), // MySQL ignora si ya existe por updateOrInsert
                ]
            );

            $cursor->addMonth();
        }
    }

    private function generateAnnual(
        int $companyId,
        int $tenantObligationId,
        int $templateId,
        array $dueRule,
        string $type,
        string $tz,
        Carbon $today,
        int $yearsAhead,
        int $dueSoonDays,
        ?Carbon $startsOn,
        ?Carbon $endsOn
    ): void {
        // MVP: annual_fixed_month_day
        if ($type !== 'annual_fixed_month_day') {
            return;
        }

        $month = (int) ($dueRule['month'] ?? 3);
        $day   = (int) ($dueRule['day'] ?? 31);
        $shift = (string) ($dueRule['shift'] ?? 'next_business_day');

        $yearStart = $today->year - 1; // normalmente declaras año anterior
        $yearEnd   = $today->year + $yearsAhead;

        for ($y = $yearStart; $y <= $yearEnd; $y++) {
            $periodKey = (string) $y;

            $due = Carbon::create($y + 1, $month, 1, 0, 0, 0, $tz);
            $dueDay = min($day, (int) $due->daysInMonth);
            $due->setDay($dueDay);

            $due = $this->applyDueOverrideIfAny($templateId, $periodKey, $due, $tz);
            $due = $this->shiftWeekend($due, $shift);

            // onboarding boundaries (simple)
            if ($startsOn && $due->lt($startsOn)) continue;
            if ($endsOn && $due->gt($endsOn)) continue;

            $status = $this->computeStatus($due, $today, $dueSoonDays);

            DB::table('obligation_instances')->updateOrInsert(
                [
                    'tenant_obligation_id' => $tenantObligationId,
                    'period_key' => $periodKey,
                ],
                [
                    'company_id' => $companyId,
                    'period_start' => null,
                    'period_end' => null,
                    'due_date' => $due->toDateString(),
                    'status' => $status,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function computeStatus(Carbon $due, Carbon $today, int $dueSoonDays): string
    {
        if ($due->lt($today)) return 'overdue';
        $diff = $today->diffInDays($due, false);
        if ($diff >= 0 && $diff <= $dueSoonDays) return 'due_soon';
        return 'pending';
    }

    private function shiftWeekend(Carbon $due, string $shift): Carbon
    {
        if ($shift === 'none') return $due;

        // 6=sábado, 0=domingo
        while (in_array($due->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
            $due = $shift === 'previous_business_day'
                ? $due->subDay()
                : $due->addDay();
        }
        return $due;
    }

    private function applyDueOverrideIfAny(int $templateId, string $periodKey, Carbon $due, string $tz): Carbon
    {
        if (!Schema::hasTable('compliance_due_overrides')) {
            return $due;
        }

        $override = DB::table('compliance_due_overrides')
            ->where('template_id', $templateId)
            ->where('period_key', $periodKey)
            ->value('due_date');

        if (!$override) return $due;

        return Carbon::parse($override, $tz)->startOfDay();
    }
}
