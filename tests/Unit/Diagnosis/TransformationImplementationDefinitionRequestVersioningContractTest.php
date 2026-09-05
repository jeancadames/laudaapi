<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationDefinitionRequestVersioningContractTest
    extends TestCase
{
    private function project(
        string $path
    ): string {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/'
            .$path
        );
    }

    public function test_request_version_is_the_new_unique_authority(): void
    {
        $foundation =
            $this->project(
                'database/migrations/'
                .'2026_09_05_134500_add_request_scope_to_'
                .'transformation_implementation_definitions.php'
            );

        $this->assertStringContainsString(
            "'tid_request_version_uq'",
            $foundation
        );

        $this->assertStringContainsString(
            "'transformation_implementation_request_id'",
            $foundation
        );

        $this->assertStringContainsString(
            "'version'",
            $foundation
        );
    }

    public function test_legacy_plan_version_unique_is_replaced_by_read_index(): void
    {
        $migration =
            $this->project(
                'database/migrations/'
                .'2026_09_05_135500_replace_definition_'
                .'plan_version_unique_for_request_scope.php'
            );

        $upStart =
            strpos(
                $migration,
                'public function up(): void'
            );

        $downStart =
            strpos(
                $migration,
                'public function down(): void'
            );

        $this->assertNotFalse(
            $upStart
        );

        $this->assertNotFalse(
            $downStart
        );

        $up =
            substr(
                $migration,
                $upStart,
                $downStart - $upStart
            );

        $readIndexPosition =
            strpos(
                $up,
                "'tid_plan_version_idx'"
            );

        $dropUniquePosition =
            strpos(
                $up,
                'dropUnique('
            );

        $this->assertNotFalse(
            $readIndexPosition
        );

        $this->assertNotFalse(
            $dropUniquePosition
        );

        /*
         * Contrato crítico MySQL:
         *
         * primero debe existir un índice alternativo cuyo
         * primer campo sea plan_id; solamente después puede
         * eliminarse el unique que estaba sosteniendo la FK.
         */
        $this->assertTrue(
            $readIndexPosition
                < $dropUniquePosition
        );

        $this->assertStringContainsString(
            "'tid_plan_version_uq'",
            $up
        );

        $this->assertStringContainsString(
            "'transformation_implementation_plan_id'",
            $up
        );

        $this->assertStringContainsString(
            "'version'",
            $up
        );
    }

    public function test_domain_versions_from_request_not_plan(): void
    {
        $service =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionService.php'
            );

        $this->assertStringContainsString(
            "'transformation_implementation_request_id'",
            $service
        );

        $this->assertStringContainsString(
            "->max(\n"
            ."                                'version'",
            $service
        );

        $this->assertStringNotContainsString(
            "->where(\n"
            ."                                'transformation_implementation_plan_id'",
            $service
        );
    }

    public function test_versioning_change_has_no_downstream_semantics(): void
    {
        $migration =
            $this->project(
                'database/migrations/'
                .'2026_09_05_135500_replace_definition_'
                .'plan_version_unique_for_request_scope.php'
            );

        foreach ([
            'TransformationCapabilityActivationService',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationSubscriptionService',
            'Invoice::',
            'Payment::',
            'Subscription::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $migration
            );
        }
    }
}
