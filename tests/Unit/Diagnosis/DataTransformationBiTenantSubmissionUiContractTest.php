<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSubmissionUiContractTest
    extends TestCase
{
    private string $controller;
    private string $page;
    private string $projection;
    private string $routes;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        $this->page =
            file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        $this->projection =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantSourceWorkspaceProjection.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/web.php'
            );
    }

    public function test_initial_read_model_exposes_safe_submission_state(): void
    {
        self::assertStringContainsString(
            "'submitted_at' =>",
            $this->controller
        );

        self::assertStringContainsString(
            "'can_submit_for_evaluation' =>",
            $this->controller
        );

        self::assertStringContainsString(
            "'submitted_at' =>",
            $this->projection
        );

        self::assertStringContainsString(
            "'can_submit_for_evaluation' =>",
            $this->projection
        );
    }

    public function test_tenant_ui_requires_a_complete_delivery_before_submission(): void
    {
        foreach ([
            'can_submit_for_evaluation',
            'sourceWorkspaceDeliveryComplete',
            'readiness.inputs_validated',
            'readiness.source_count > 0',
            'readiness.complete_source_count',
            '=== readiness.source_count',
            'canSubmitSourceWorkspace',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->page
            );
        }
    }

    public function test_submission_requires_confirmation_and_uses_tenant_route(): void
    {
        foreach ([
            'window.confirm(',
            'quedarán congeladas',
            '/enviar-evaluacion',
            'Enviar a evaluación',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->page
            );
        }

        self::assertStringContainsString(
            'submit_for_evaluation',
            $this->routes
        );
    }

    public function test_submitted_delivery_is_shown_as_read_only_evaluation(): void
    {
        foreach ([
            'submitted_for_evaluation',
            'En evaluación',
            'Solo lectura',
            ':disabled="!canManageSources"',
            'v-if="canManageSources"',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->page
            );
        }
    }

    public function test_tenant_ui_does_not_reference_private_internal_state(): void
    {
        foreach ([
            'submitted_manifest_sha256',
            'submitted_by_user_id',
            'diagnostic_summary',
            'profiling_snapshot',
        ] as $token) {
            self::assertStringNotContainsString(
                $token,
                $this->page
            );
        }
    }
}
