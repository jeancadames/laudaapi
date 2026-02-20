<?php

namespace App\Services\Compliance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DueDateEngine
{
    public function computeDueDate(array $template, array $company, string $periodKey, CarbonImmutable $periodStart): CarbonImmutable
    {
        // 1) Override exacto (TSS calendario, o manual)
        $override = DB::table('compliance_due_overrides')
            ->where('template_id', $template['id'])
            ->where('period_key', $periodKey)
            ->value('due_date');

        if ($override) {
            return CarbonImmutable::parse($override);
        }

        $rule = $template['due_rule'] ?? [];
        $type = $rule['type'] ?? null;

        return match ($type) {
            'monthly_day' => $this->monthlyDay($rule, $company, $periodStart),
            'monthly_nth_business_day' => $this->monthlyNthBusinessDay($rule, $periodStart),
            'year_table' => $this->yearTableFallback($rule, $company, $periodStart),
            default => throw new \RuntimeException("Unsupported due_rule.type: {$type} (template {$template['id']})"),
        };
    }

    private function monthlyDay(array $rule, array $company, CarbonImmutable $periodStart): CarbonImmutable
    {
        $tz = $company['timezone'] ?? 'UTC';

        $monthOffset = (int)($rule['month_offset'] ?? 1);
        $target = $periodStart->setTimezone($tz)->addMonthsNoOverflow($monthOffset);

        $day = (int)($rule['day'] ?? 1);
        $day = max(1, min($day, $target->daysInMonth));

        $due = CarbonImmutable::create($target->year, $target->month, $day, 0, 0, 0, $tz);

        $shift = $rule['shift'] ?? 'company_default';
        return $this->applyWeekendShift($due, $company, $shift);
    }

    private function monthlyNthBusinessDay(array $rule, CarbonImmutable $periodStart): CarbonImmutable
    {
        // business day = Mon-Fri (MVP). Feriados luego.
        $monthOffset = (int)($rule['month_offset'] ?? 1);
        $n = (int)($rule['n'] ?? 3);
        $n = max(1, min($n, 31));

        $targetMonth = $periodStart->addMonthsNoOverflow($monthOffset)->startOfMonth();

        $count = 0;
        $cursor = $targetMonth;
        while (true) {
            $isWeekend = $cursor->isWeekend();
            if (!$isWeekend) {
                $count++;
                if ($count === $n) {
                    return $cursor;
                }
            }
            $cursor = $cursor->addDay();
        }
    }

    private function yearTableFallback(array $rule, array $company, CarbonImmutable $periodStart): CarbonImmutable
    {
        // si no hay override, usa fallback
        $fallback = $rule['fallback'] ?? null;
        if (!$fallback || !is_array($fallback)) {
            // fallback final: 3er día laborable mes siguiente (TSS FAQ) :contentReference[oaicite:7]{index=7}
            return $this->monthlyNthBusinessDay(['type' => 'monthly_nth_business_day', 'n' => 3, 'month_offset' => 1], $periodStart);
        }

        $type = $fallback['type'] ?? null;

        return match ($type) {
            'monthly_nth_business_day' => $this->monthlyNthBusinessDay($fallback, $periodStart),
            'monthly_day' => $this->monthlyDay($fallback, $company, $periodStart),
            default => throw new \RuntimeException("Unsupported year_table fallback.type: {$type}"),
        };
    }

    private function applyWeekendShift(CarbonImmutable $due, array $company, string $shift): CarbonImmutable
    {
        // MVP: solo fin de semana. (Feriados se agrega luego)
        // shift puede venir de company settings; aquí asumimos company['weekend_shift']
        $companyShift = $company['weekend_shift'] ?? 'next_business_day';

        $mode = ($shift === 'company_default') ? $companyShift : $shift;

        if (!$due->isWeekend() || $mode === 'none') {
            return $due;
        }

        if ($mode === 'previous_business_day') {
            $cursor = $due;
            while ($cursor->isWeekend()) $cursor = $cursor->subDay();
            return $cursor;
        }

        // default: next_business_day
        $cursor = $due;
        while ($cursor->isWeekend()) $cursor = $cursor->addDay();
        return $cursor;
    }
}
