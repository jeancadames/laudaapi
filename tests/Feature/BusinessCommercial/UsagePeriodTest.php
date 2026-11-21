<?php

namespace Tests\Feature\BusinessCommercial;

use App\Services\BusinessCommercial\UsagePeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsagePeriodTest extends TestCase
{
    public static function periods(): array
    {
        return [
            ['2028-01-31T15:00:00Z', 'UTC', '2028-02-29T14:59:59Z', '2028-01-31T15:00:00Z', '2028-02-29T15:00:00Z'],
            ['2028-01-31T15:00:00Z', 'UTC', '2028-02-29T15:00:00Z', '2028-02-29T15:00:00Z', '2028-03-31T15:00:00Z'],
            ['2028-02-29T15:00:00Z', 'UTC', '2029-02-28T15:00:00Z', '2029-02-28T15:00:00Z', '2029-03-29T15:00:00Z'],
            ['2026-11-07T03:00:00Z', 'America/Santo_Domingo', '2026-12-07T02:59:59Z', '2026-11-07T03:00:00Z', '2026-12-07T03:00:00Z'],
            ['2026-01-10T17:00:00Z', 'America/New_York', '2026-03-10T16:00:00Z', '2026-03-10T16:00:00Z', '2026-04-10T16:00:00Z'],
        ];
    }

    #[DataProvider('periods')]
    public function test_monthly_usage_uses_original_anchor_timezone_and_exclusive_end(string $anchor, string $zone, string $at, string $start, string $end): void
    {
        [$actualStart, $actualEnd] = UsagePeriod::containing(CarbonImmutable::parse($anchor), $zone, CarbonImmutable::parse($at));
        $this->assertTrue(CarbonImmutable::parse($start)->equalTo($actualStart));
        $this->assertTrue(CarbonImmutable::parse($end)->equalTo($actualEnd));
    }
}
