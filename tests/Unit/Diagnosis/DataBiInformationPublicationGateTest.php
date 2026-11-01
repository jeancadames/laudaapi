<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiInformationPublicationGate;
use PHPUnit\Framework\TestCase;

final class DataBiInformationPublicationGateTest extends TestCase
{
    public function test_exact_dev_and_prod_are_allowed(): void
    {
        self::assertTrue(
            DataBiInformationPublicationGate::allowedEnvironment(
                'local',
                'mysql',
                'laudaapi_dev'
            )
        );

        self::assertTrue(
            DataBiInformationPublicationGate::allowedEnvironment(
                'production',
                'mysql',
                'laudaapi-prod'
            )
        );
    }

    public function test_unsafe_combinations_are_rejected(): void
    {
        foreach ([
            ['local', 'mysql', 'laudaapi-prod'],
            ['production', 'mysql', 'laudaapi_dev'],
            ['testing', 'mysql', 'laudaapi_dev'],
            ['production', 'sqlite', 'laudaapi-prod'],
            ['production', 'mysql', 'laudaapi_prod'],
            ['staging', 'mysql', 'laudaapi-prod'],
        ] as [$env, $driver, $database]) {
            self::assertFalse(
                DataBiInformationPublicationGate::allowedEnvironment(
                    $env,
                    $driver,
                    $database
                )
            );
        }
    }
}
