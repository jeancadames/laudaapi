<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceOwnerReadinessIsolationContractTest
    extends TestCase
{
    public function test_owner_is_not_a_source_readiness_gate(): void
    {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceReadinessService.php'
            );

        /*
         * SourceAsset.owner is descriptive operational metadata.
         *
         * Readiness remains derived from source/file lifecycle state
         * and must never require an owner to be populated.
         */
        foreach ([
            '->owner',
            "['owner']",
            '["owner"]',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_owner_contract_remains_descriptive_metadata(): void
    {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetService.php'
            );

        self::assertStringContainsString(
            'MAX_OWNER_LENGTH',
            $source
        );

        self::assertStringContainsString(
            "\$payload['owner']",
            $source
        );

        self::assertStringNotContainsString(
            'owner_domain_key',
            $source
        );

        self::assertStringNotContainsString(
            'source_owner_domain',
            $source
        );
    }
}
