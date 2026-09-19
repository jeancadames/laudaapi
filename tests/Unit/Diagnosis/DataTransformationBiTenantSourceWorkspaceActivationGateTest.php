<?php

namespace Tests\Unit\Diagnosis;

use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceGate;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DataTransformationBiTenantSourceWorkspaceActivationGateTest
    extends TestCase
{
    public function test_request_status_controls_workspace_visibility_and_management(): void
    {
        $gate =
            new DataTransformationBiTenantSourceWorkspaceGate();

        $notRequested =
            $gate->state(null);

        self::assertFalse(
            $notRequested['visible']
        );

        self::assertFalse(
            $notRequested['can_manage']
        );

        foreach (
            [
                TransformationImplementationRequestContract::STATUS_REQUESTED,
                TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
                TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
                TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
                TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,
            ]
            as $status
        ) {
            $state =
                $gate->state(
                    $status
                );

            self::assertTrue(
                $state['visible'],
                $status
            );

            self::assertFalse(
                $state['can_manage'],
                $status
            );

            self::assertSame(
                'pending_definition_agreement',
                $state['state'],
                $status
            );
        }

        foreach (
            [
                TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
                TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL,
            ]
            as $status
        ) {
            $state =
                $gate->state(
                    $status
                );

            self::assertTrue(
                $state['visible'],
                $status
            );

            self::assertTrue(
                $state['can_manage'],
                $status
            );

            self::assertSame(
                'enabled',
                $state['state'],
                $status
            );
        }

        $cancelled =
            $gate->state(
                TransformationImplementationRequestContract::STATUS_CANCELLED
            );

        self::assertFalse(
            $cancelled['visible']
        );

        self::assertFalse(
            $cancelled['can_manage']
        );
    }

    public function test_mutations_fail_closed_before_definition_agreement(): void
    {
        $gate =
            new DataTransformationBiTenantSourceWorkspaceGate();

        $request =
            new TransformationImplementationRequest();

        $request->status =
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION;

        $this->expectException(
            ValidationException::class
        );

        $gate->assertCanManage(
            $request
        );
    }

    public function test_mutations_are_allowed_after_definition_agreement(): void
    {
        $gate =
            new DataTransformationBiTenantSourceWorkspaceGate();

        $request =
            new TransformationImplementationRequest();

        $request->status =
            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED;

        $gate->assertCanManage(
            $request
        );

        self::assertTrue(true);
    }

    public function test_http_and_ui_use_the_same_server_owned_gate(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $appHub =
            file_get_contents(
                $root
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        $workspace =
            file_get_contents(
                $root
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiSourceWorkspaceController.php'
            );

        $vue =
            file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        self::assertIsString($appHub);
        self::assertIsString($workspace);
        self::assertIsString($vue);

        self::assertStringContainsString(
            "&& \$sourceWorkspaceAccess['can_manage']",
            $appHub
        );

        self::assertStringContainsString(
            "'access' =>",
            $appHub
        );

        self::assertStringContainsString(
            'DataTransformationBiTenantSourceWorkspaceGate::class',
            $workspace
        );

        self::assertStringContainsString(
            'T1_TENANT_SOURCE_WORKSPACE_ACTIVATION_GATE',
            $vue
        );

        self::assertGreaterThanOrEqual(
            2,
            substr_count(
                $vue,
                'v-if="source_workspace.access.can_manage"'
            )
        );

        self::assertStringNotContainsString(
            'v-if="implementation_request.id"',
            $vue
        );
    }
}
