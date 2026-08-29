<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class PublicDiagnosisIntakeLockContractTest extends TestCase
{
    public function test_mysql_advisory_lock_name_stays_within_limit(): void
    {
        $lockName =
            'laudaapi:diag-intake:'
            .sha1('diagnosis-lock-contract@example.com');

        $this->assertLessThanOrEqual(
            64,
            strlen($lockName),
            'MySQL GET_LOCK names must not exceed 64 characters.'
        );

        $this->assertSame(
            61,
            strlen($lockName)
        );
    }

    public function test_intake_uses_same_lock_name_for_acquire_and_release(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            .'/app/Services/Diagnosis/PublicDiagnosisIntakeService.php'
        );

        $this->assertIsString($source);

        $this->assertStringContainsString(
            "\$lockName = 'laudaapi:diag-intake:'.sha1(\$email);",
            $source
        );

        $this->assertStringContainsString(
            'SELECT GET_LOCK(?, 10) AS acquired',
            $source
        );

        $this->assertStringContainsString(
            'SELECT RELEASE_LOCK(?) AS released',
            $source
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, '[$lockName]')
        );

        $this->assertStringNotContainsString(
            'laudaapi:diagnosis-intake:',
            $source
        );
    }
}
