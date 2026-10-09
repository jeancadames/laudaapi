<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationCommercialProjectionContractTest
    extends TestCase
{
    private function projection(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            . '/app/Services/Diagnosis/'
            . 'TransformationImplementationCommercialProjection.php'
        );
    }

    private function adminController(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            . '/app/Http/Controllers/Admin/'
            . 'AdminTransformationImplementationRequestController.php'
        );
    }

    private function tenantController(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            . '/app/Http/Controllers/'
            . 'AppHubDataTransformationBiController.php'
        );
    }

    public function test_projection_is_explicitly_split_by_audience(): void
    {
        $source = $this->projection();

        $this->assertStringContainsString(
            'function forAdmin(',
            $source
        );

        $this->assertStringContainsString(
            'function forTenant(',
            $source
        );

        $this->assertStringContainsString(
            'function engagementPayload(',
            $source
        );
    }

    public function test_admin_projection_is_request_scoped(): void
    {
        $source = $this->projection();

        $this->assertStringContainsString(
            "'transformation_implementation_request_id'",
            $source
        );

        $this->assertStringContainsString(
            "'company_id'",
            $source
        );

        $this->assertStringContainsString(
            "'transformation_implementation_phase_capability_id'",
            $source
        );

        $this->assertStringContainsString(
            "'capability_key'",
            $source
        );
    }

    public function test_tenant_request_is_resolved_without_browser_ids(): void
    {
        $source = $this->projection();

        $this->assertStringContainsString(
            'function forTenant(int $companyId)',
            $source
        );

        $this->assertStringContainsString(
            "->where('company_id', \$companyId)",
            $source
        );

        $this->assertStringContainsString(
            "->orderByDesc('attempt')",
            $source
        );

        $this->assertStringContainsString(
            "->orderByDesc('id')",
            $source
        );
    }

    public function test_tenant_projection_hides_unpresented_statuses(): void
    {
        $source = $this->projection();

        $this->assertStringContainsString(
            "['presented', 'accepted']",
            $source
        );

        $this->assertStringContainsString(
            "return null;",
            $source
        );
    }

    public function test_internal_notes_are_admin_only(): void
    {
        $source = $this->projection();

        $this->assertStringContainsString(
            'if ($admin) {',
            $source
        );

        $this->assertStringContainsString(
            "\$payload['internal_notes']",
            $source
        );

        $this->assertStringContainsString(
            '$this->engagementPayload(',
            $source
        );

        $this->assertStringContainsString(
            'false',
            $source
        );
    }

    public function test_authorization_is_separate_from_acceptance(): void
    {
        $source = $this->projection();

        $this->assertStringContainsString(
            'function authorizationFor(',
            $source
        );

        $this->assertStringContainsString(
            '->isActive()',
            $source
        );

        $this->assertStringContainsString(
            '$authorization === null',
            $source
        );

        $this->assertStringContainsString(
            "'accepted'",
            $source
        );
    }

    public function test_admin_controller_exposes_projection(): void
    {
        $source = $this->adminController();

        $this->assertStringContainsString(
            "'modern_commercial' =>",
            $source
        );

        $this->assertStringContainsString(
            '->forAdmin($implementationRequest)',
            $source
        );
    }

    public function test_tenant_controller_exposes_company_scoped_projection(): void
    {
        $source = $this->tenantController();

        $this->assertStringContainsString(
            "'modern_commercial' =>",
            $source
        );

        $this->assertStringContainsString(
            '->forTenant((int) $company->id)',
            $source
        );
    }

    public function test_projection_excludes_historical_execution_mutations(): void
    {
        $source = $this->projection();

        foreach ([
            'ready_for_execution',
            'execution_started',
            'materialize(',
            'normalize(',
            'createDraft(',
            '->save(',
            '->create(',
            '->update(',
            '->delete(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
