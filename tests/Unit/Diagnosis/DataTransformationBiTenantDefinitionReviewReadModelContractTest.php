<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantDefinitionReviewReadModelContractTest
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

    private function tenantDefinitionMethod(): string
    {
        $controller =
            $this->project(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        $start =
            strpos(
                $controller,
                'private function tenantDefinitionReview('
            );

        $end =
            strpos(
                $controller,
                'private function implementationRequestStatusLabel(',
                $start
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        return substr(
            $controller,
            $start,
            $end - $start
        );
    }

    public function test_tenant_admin_and_company_scope_are_preserved(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        foreach ([
            '$user->role',
            "'subscriber'",
            'TenantAccessService::SUBSCRIBER_ADMIN',
            "'tenant_admin'",
            'SubscriberResolver',
            'CompanyContextResolver',
            "'company_id'",
            '$company->id',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_definition_is_resolved_server_side_from_exact_request_context(): void
    {
        $method =
            $this->tenantDefinitionMethod();

        foreach ([
            'TransformationImplementationDefinition::query()',
            "'transformation_implementation_request_id'",
            "'company_id'",
            "'diagnosis_assessment_id'",
            "'transformation_implementation_plan_id'",
            "'transformation_implementation_phase_capability_id'",
            "'capability_key'",
            "'version'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }
    }

    public function test_only_request_scoped_single_capability_definition_is_visible(): void
    {
        $method =
            $this->tenantDefinitionMethod();

        foreach ([
            "'implementation_request'",
            "'single_capability'",
            "'definition_scope_locked_to_request'",
            'STATUS_AWAITING_TENANT_REVIEW',
            'STATUS_UNDER_REVIEW',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }
    }

    public function test_draft_definition_is_not_tenant_visible(): void
    {
        $method =
            $this->tenantDefinitionMethod();

        $this->assertStringNotContainsString(
            'TransformationImplementationDefinition::STATUS_DRAFT',
            $method
        );
    }

    public function test_private_definition_fields_are_not_returned(): void
    {
        $method =
            $this->tenantDefinitionMethod();

        $returnStart =
            strpos(
                $method,
                'return ['
            );

        $this->assertNotFalse(
            $returnStart
        );

        $projection =
            substr(
                $method,
                $returnStart
            );

        foreach ([
            "'source_snapshot' =>",
            "'internal_notes' =>",
            "'created_by_user_id' =>",
            "'updated_by_user_id' =>",
            "'reviewed_by_user_id' =>",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $projection
            );
        }
    }

    public function test_functional_review_content_is_exposed(): void
    {
        $method =
            $this->tenantDefinitionMethod();

        foreach ([
            "'scope'",
            "'deliverables'",
            "'dependencies'",
            "'responsibilities'",
            "'human_review'",
            "'confirmations'",
            "'scope_confirmed'",
            "'deliverables_confirmed'",
            "'dependencies_confirmed'",
            "'inputs_validated'",
            "'accesses_validated'",
            "'responsibilities_confirmed'",
            "'submitted_at'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }
    }

    public function test_read_model_has_no_lifecycle_mutation(): void
    {
        $method =
            $this->tenantDefinitionMethod();

        foreach ([
            'transitionByTenant(',
            'transitionByLauda(',
            '->markReady(',
            '->save(',
            '->create(',
            '->update(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $method
            );
        }
    }

    public function test_ui_presents_definition_as_read_only_noncommercial_content(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'Definition funcional presentada',
            'Versión presentada',
            'Alcance funcional',
            'Entregables',
            'Dependencias',
            'Responsabilidades',
            'Revisión humana de LAUDA',
            'No contiene precios',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }

        foreach ([
            'Aceptar precio',
            'Activar servicio ahora',
            'Iniciar ejecución ahora',
            'Crear suscripción',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $ui
            );
        }
    }
}
