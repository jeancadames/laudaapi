<?php

namespace App\Services\BusinessCommercial;

use Carbon\CarbonImmutable;

final class UsagePeriod
{
    /** Monthly anniversaries of activation, including annual billing; never drift after a short month. */
    public static function containing(CarbonImmutable $activation, string $timezone, CarbonImmutable $now): array
    {
        $anchor = $activation->setTimezone($timezone);
        $current = $now->setTimezone($timezone);
        $months = max(0, ($current->year - $anchor->year) * 12 + $current->month - $anchor->month);
        if ($months > 0 && $anchor->addMonthsNoOverflow($months)->gt($current)) {
            $months--;
        }

        return [$anchor->addMonthsNoOverflow($months)->utc(), $anchor->addMonthsNoOverflow($months + 1)->utc()];
    }
}
