<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiSourceAssetMappingAuthorizationGateContractTest
    extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            base_path(
                'app/Services/Diagnosis/DataTransformationBiSourceAssetMappingService.php'
            )
        );
    }

    public function test_mapping_service_injects_modern_implementation_authorization_gate(): void
    {
        $source =
            $this->source();

        $this->assertStringContainsString(
            'private readonly TransformationImplementationAuthorizationGate',
            $source
        );

        $this->assertStringContainsString(
            '$implementationAuthorizationGate',
            $source
        );
    }

    public function test_all_mapping_write_boundaries_require_active_authorization(): void
    {
        $source =
            $this->source();

        $needle =
            <<<'PHP_SOURCE'
$this->implementationAuthorizationGate
                    ->assertActiveForRequest(
                        $implementationRequest,
                        true
                    );
PHP_SOURCE;

        $this->assertSame(
            4,
            substr_count(
                $source,
                $needle
            )
        );
    }

    public function test_expected_mapping_write_methods_remain_present(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'public function startDraft(',
                'public function replaceFieldMappings(',
                'public function markReady(',
                'public function validate(',
            ]
            as $method
        ) {
            $this->assertStringContainsString(
                $method,
                $source
            );
        }
    }

    public function test_workspace_remains_read_only_and_without_implementation_gate(): void
    {
        $source =
            $this->source();

        $workspaceStart =
            strpos(
                $source,
                'public function workspace('
            );

        $startDraftStart =
            strpos(
                $source,
                'public function startDraft('
            );

        $this->assertNotFalse(
            $workspaceStart
        );

        $this->assertNotFalse(
            $startDraftStart
        );

        $readSurface =
            substr(
                $source,
                $workspaceStart,
                $startDraftStart - $workspaceStart
            );

        /*
         * The READ ONLY documentation belongs to the docblock immediately
         * preceding workspace(), so it is intentionally outside the slice
         * that begins at the method signature.
         */
        $this->assertStringContainsString(
            'READ ONLY:',
            $source
        );

        $this->assertStringNotContainsString(
            '->assertActiveForRequest(',
            $readSurface
        );
    }

    public function test_start_draft_requires_gate_inside_its_transaction(): void
    {
        $source =
            $this->source();

        $start =
            strpos(
                $source,
                'public function startDraft('
            );

        $end =
            strpos(
                $source,
                'public function replaceFieldMappings('
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $body =
            substr(
                $source,
                $start,
                $end - $start
            );

        $transaction =
            strpos(
                $body,
                'return DB::transaction('
            );

        $gate =
            strpos(
                $body,
                '->assertActiveForRequest('
            );

        $firstWriteLock =
            strpos(
                $body,
                '$lockedAsset ='
            );

        $this->assertNotFalse(
            $transaction
        );

        $this->assertNotFalse(
            $gate
        );

        $this->assertNotFalse(
            $firstWriteLock
        );

        $this->assertGreaterThan(
            $transaction,
            $gate
        );

        $this->assertLessThan(
            $firstWriteLock,
            $gate
        );
    }

    public function test_replace_field_mappings_requires_gate_inside_write_transaction(): void
    {
        $source =
            $this->source();

        $start =
            strpos(
                $source,
                'public function replaceFieldMappings('
            );

        $this->assertNotFalse(
            $start
        );

        $body =
            substr(
                $source,
                $start
            );

        $transaction =
            strpos(
                $body,
                'DB::transaction('
            );

        $gate =
            strpos(
                $body,
                '->assertActiveForRequest('
            );

        $firstLockedAsset =
            strpos(
                $body,
                '$lockedAsset =',
                $transaction
            );

        $this->assertNotFalse(
            $transaction
        );

        $this->assertNotFalse(
            $gate
        );

        $this->assertNotFalse(
            $firstLockedAsset
        );

        $this->assertGreaterThan(
            $transaction,
            $gate
        );

        $this->assertLessThan(
            $firstLockedAsset,
            $gate
        );
    }

    public function test_mapping_does_not_start_execution_or_mutate_commercial_lifecycle(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'TransformationImplementationExecutionService',
                'TransformationImplementationCapabilityExecution',
                'TransformationImplementationPhaseExecution',
                "'ready_for_execution' => true",
                "'execution_started' => true",
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_mapping_still_depends_on_published_company_canonical_registry(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'requirePublishedCanonicalRegistry(',
                'assertCurrentCanonicalRegistry(',
                'canonical_registry_version',
                'canonical_entity_key',
                'canonical_field_key',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
