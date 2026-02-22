<?php

namespace App\Services\Compliance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DueDateEngine
{
    /**
     * Calcula la fecha de vencimiento según la plantilla, compañía y periodo.
     *
     * @param array $template
     * @param array $company
     * @param string $periodKey
     * @param CarbonImmutable $periodStart
     * @return CarbonImmutable
     * @throws RuntimeException
     */
    public function computeDueDate(array $template, array $company, string $periodKey, CarbonImmutable $periodStart): CarbonImmutable
    {
        $templateId = (int) ($template['id'] ?? 0);
        if ($templateId <= 0) {
            throw new RuntimeException('Template id inválido en DueDateEngine.');
        }

        $tz = (string) ($company['timezone'] ?? 'UTC');

        // ✅ Normaliza rule (puede venir JSON string desde DB)
        $rule = $template['due_rule'] ?? [];
        if (is_string($rule)) {
            $decoded = json_decode($rule, true);
            $rule = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rule)) $rule = [];

        $type = (string) ($rule['type'] ?? '');
        if ($type === '') {
            throw new RuntimeException("due_rule.type vacío (template {$templateId})");
        }

        // 1) ✅ Override exacto (TSS calendario, o manual)
        // Evitar consultar Schema::hasTable en cada invocación: cachear en propiedad estática
        static $overridesTableExists = null;
        if ($overridesTableExists === null) {
            $overridesTableExists = Schema::hasTable('compliance_due_overrides');
        }

        $override = null;
        if ($overridesTableExists) {
            $override = DB::table('compliance_due_overrides')
                ->where('template_id', $templateId)
                ->where('period_key', $periodKey)
                ->value('due_date');
        }

        if ($override) {
            // due_date es DATE => lo fijamos al tz de company y startOfDay
            return CarbonImmutable::parse($override, $tz)->startOfDay();
        }

        return match ($type) {
            'monthly_day' => $this->monthlyDay($rule, $company, $periodStart, $tz),
            'monthly_nth_business_day' => $this->monthlyNthBusinessDay($rule, $periodStart, $tz),
            'year_table' => $this->yearTableFallback($rule, $company, $periodStart, $tz),
            'annual_fixed_month_day' => $this->annualFixedMonthDay($rule, $company, $periodKey, $tz),
            default => throw new RuntimeException("Unsupported due_rule.type: {$type} (template {$templateId})"),
        };
    }

    private function monthlyDay(array $rule, array $company, CarbonImmutable $periodStart, string $tz): CarbonImmutable
    {
        $monthOffset = (int) ($rule['month_offset'] ?? 1);
        $target = $periodStart->setTimezone($tz)->startOfMonth()->addMonthsNoOverflow($monthOffset);

        $day = (int) ($rule['day'] ?? 1);
        $day = max(1, min($day, $target->daysInMonth));

        $due = CarbonImmutable::create($target->year, $target->month, $day, 0, 0, 0, $tz)->startOfDay();

        $shift = (string) ($rule['shift'] ?? 'company_default');
        return $this->applyWeekendShift($due, $company, $shift, $tz);
    }

    private function monthlyNthBusinessDay(array $rule, CarbonImmutable $periodStart, string $tz): CarbonImmutable
    {
        // business day = Mon-Fri (MVP). Feriados luego.
        $monthOffset = (int) ($rule['month_offset'] ?? 1);
        $n = (int) ($rule['n'] ?? 3);
        $n = max(1, min($n, 31));

        $month = $periodStart->setTimezone($tz)->startOfMonth()->addMonthsNoOverflow($monthOffset);
        $cursor = $month->startOfMonth()->startOfDay();
        $monthNum = $cursor->month;

        $count = 0;

        // ✅ corta al salir del mes para evitar “derrape”
        while ($cursor->month === $monthNum) {
            if (!$cursor->isWeekend()) {
                $count++;
                if ($count === $n) return $cursor;
            }
            $cursor = $cursor->addDay();
        }

        throw new RuntimeException("No se pudo calcular el {$n}° día laborable para {$month->format('Y-m')}.");
    }

    private function yearTableFallback(array $rule, array $company, CarbonImmutable $periodStart, string $tz): CarbonImmutable
    {
        // si no hay override, usa fallback
        $fallback = $rule['fallback'] ?? null;

        if (!$fallback || !is_array($fallback)) {
            // fallback final: 3er día laborable del mes siguiente (MVP)
            return $this->monthlyNthBusinessDay(['n' => 3, 'month_offset' => 1], $periodStart, $tz);
        }

        $type = (string) ($fallback['type'] ?? '');

        return match ($type) {
            'monthly_nth_business_day' => $this->monthlyNthBusinessDay($fallback, $periodStart, $tz),
            'monthly_day' => $this->monthlyDay($fallback, $company, $periodStart, $tz),
            default => throw new RuntimeException("Unsupported year_table fallback.type: {$type}"),
        };
    }

    private function annualFixedMonthDay(array $rule, array $company, string $periodKey, string $tz): CarbonImmutable
    {
        // periodKey esperado: "YYYY"
        $year = (int) $periodKey;
        if ($year <= 0) throw new RuntimeException("period_key inválido para annual: {$periodKey}");

        $month = (int) ($rule['month'] ?? 0);
        $day = (int) ($rule['day'] ?? 0);
        if ($month < 1 || $month > 12 || $day < 1) throw new RuntimeException("Regla annual inválida.");

        $yearOffset = (int) ($rule['year_offset'] ?? 1); // normalmente vence año siguiente
        $dueYear = $year + $yearOffset;

        $target = CarbonImmutable::create($dueYear, $month, 1, 0, 0, 0, $tz)->startOfMonth();
        $day = max(1, min($day, $target->daysInMonth));
        $due = CarbonImmutable::create($dueYear, $month, $day, 0, 0, 0, $tz)->startOfDay();

        $shift = (string) ($rule['shift'] ?? 'company_default');
        return $this->applyWeekendShift($due, $company, $shift, $tz);
    }

    private function applyWeekendShift(CarbonImmutable $due, array $company, string $shift, string $tz): CarbonImmutable
    {
        $companyShift = (string) ($company['weekend_shift'] ?? 'next_business_day');
        $mode = ($shift === 'company_default') ? $companyShift : $shift;
        $mode = strtolower(trim($mode));

        if (!$due->isWeekend() || $mode === 'none') return $due;

        if (in_array($mode, ['previous_business_day', 'prev_business_day'], true)) {
            $cursor = $due;
            while ($cursor->isWeekend()) $cursor = $cursor->subDay();
            return $cursor->startOfDay();
        }

        // default: next_business_day
        $cursor = $due;
        while ($cursor->isWeekend()) $cursor = $cursor->addDay();
        return $cursor->startOfDay();
    }
}
