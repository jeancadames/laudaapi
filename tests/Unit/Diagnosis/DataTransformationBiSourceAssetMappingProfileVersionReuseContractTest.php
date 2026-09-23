<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetMappingProfileVersionReuseContractTest
    extends TestCase
{
    private string $method;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetMappingService.php'
            );

        self::assertIsString(
            $source
        );

        $start =
            strpos(
                $source,
                'public function startDraft('
            );

        $end =
            strpos(
                $source,
                'public function replaceFieldMappings(',
                $start
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $this->method =
            substr(
                $source,
                $start,
                $end - $start
            );
    }

    public function test_draft_reuse_is_pinned_to_profile_version(): void
    {
        self::assertStringContainsString(
            '$sourceProfileVersion',
            $this->method
        );

        self::assertStringContainsString(
            "'source_profile_version'",
            $this->method
        );

        self::assertStringContainsString(
            "'source_sha256'",
            $this->method
        );

        self::assertStringContainsString(
            "'canonical_registry_version'",
            $this->method
        );
    }

    public function test_profile_version_is_taken_from_current_profile(): void
    {
        self::assertStringContainsString(
            "\$profile['version']",
            $this->method
        );

        self::assertStringContainsString(
            '$sourceProfileVersion <= 0',
            $this->method
        );
    }

    public function test_older_profile_mappings_become_stale_on_explicit_start(): void
    {
        self::assertStringContainsString(
            "'source_profile_version',\n"
            ."                        '<>',\n"
            .'                        $sourceProfileVersion',
            $this->method
        );

        self::assertStringContainsString(
            '::STATUS_STALE',
            $this->method
        );

        self::assertStringContainsString(
            "'updated_by_user_id'",
            $this->method
        );
    }

    public function test_new_mapping_persists_exact_validated_profile_version(): void
    {
        self::assertStringContainsString(
            "'source_profile_version' =>\n"
            .'                        $sourceProfileVersion',
            $this->method
        );
    }
}
