<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceGate;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiOperationalWorkspaceSharedGateContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_only_post_agreement_statuses_are_operational(): void
    {
        $gate =
            new DataTransformationBiTenantSourceWorkspaceGate();

        foreach ([
            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
            TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL,
        ] as $status) {
            self::assertTrue(
                $gate->state($status)['can_manage']
            );
        }

        foreach ([
            TransformationImplementationRequestContract::STATUS_REQUESTED,
            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,
            TransformationImplementationRequestContract::STATUS_CANCELLED,
        ] as $status) {
            self::assertFalse(
                $gate->state($status)['can_manage']
            );
        }
    }

    public function test_shared_authorizer_checks_lifecycle_before_admin_bypass(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeActorAuthorizationService.php'
            );

        $gate =
            strpos(
                $source,
                'DataTransformationBiTenantSourceWorkspaceGate::class'
            );

        $admin =
            strpos(
                $source,
                "if ((string) (\$actor->role ?? '') === 'admin')"
            );

        self::assertNotFalse($gate);
        self::assertNotFalse($admin);
        self::assertLessThan(
            $admin,
            $gate
        );
    }

    public function test_admin_intake_http_boundary_uses_same_gate(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        self::assertStringContainsString(
            'DataTransformationBiTenantSourceWorkspaceGate',
            $source
        );

        $assertRequest =
            strpos(
                $source,
                'private function assertRequest('
            );

        self::assertNotFalse(
            $assertRequest
        );

        $block =
            substr(
                $source,
                $assertRequest,
                1800
            );

        self::assertStringContainsString(
            '->assertCanManage(',
            $block
        );
    }

    public function test_single_gate_owns_operational_status_pair(): void
    {
        $gate =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantSourceWorkspaceGate.php'
            );

        self::assertStringContainsString(
            'STATUS_DEFINITION_AGREED',
            $gate
        );

        self::assertStringContainsString(
            'STATUS_READY_FOR_COMMERCIAL',
            $gate
        );

        self::assertStringContainsString(
            'shared operational guard',
            $gate
        );
    }
}
