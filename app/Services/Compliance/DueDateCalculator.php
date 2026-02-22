<?php

namespace App\Services\Compliance;

use Carbon\CarbonImmutable;

class DueDateCalculator
{
    public function __construct(
        private readonly DueDateEngine $engine,
    ) {}

    /**
     * Mantiene compatibilidad con Jobs/Commands que esperen DueDateCalculator.
     */
    public function computeDueDate(
        array $template,
        array $company,
        string $periodKey,
        CarbonImmutable $periodStart
    ): CarbonImmutable {
        return $this->engine->computeDueDate($template, $company, $periodKey, $periodStart);
    }

    /**
     * Opcional: timestamp (UTC) para ordenar/filtrar por hora real.
     * Si quieres otra hora (ej 09:00) cámbiala aquí.
     */
    public function computeDueAtUtc(
        array $template,
        array $company,
        string $periodKey,
        CarbonImmutable $periodStart
    ): CarbonImmutable {
        $tz = (string)($company['timezone'] ?? 'UTC');
        $dueLocal = $this->computeDueDate($template, $company, $periodKey, $periodStart)
            ->setTimezone($tz)
            ->startOfDay();

        return $dueLocal->setTimezone('UTC');
    }
}
