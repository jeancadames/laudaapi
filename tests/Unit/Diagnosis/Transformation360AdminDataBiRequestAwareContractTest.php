<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class Transformation360AdminDataBiRequestAwareContractTest
    extends TestCase
{
    public function test_admin_bi_supervisor_is_request_aware_and_does_not_expose_direct_plan_definition_navigation(): void
    {
        $controller =
            file_get_contents(
                base_path(
                    'app/Http/Controllers/Admin/'
                    .'AdminTransformation360OverviewController.php'
                )
            );

        $page =
            file_get_contents(
                base_path(
                    'resources/js/pages/Admin/'
                    .'Transformation360/DataBi.vue'
                )
            );

        $this->assertIsString($controller);
        $this->assertIsString($page);

        foreach ([
            'TransformationImplementationRequest::query()',
            "'data_transformation_bi'",
            "'single_capability'",
            "'definition_scope_locked_to_request'",
            "'definition_agreed_by_tenant'",
            "'request_ready_for_commercial_by_lauda'",
            "'active_requests'",
            "\$row['urls']['definition']",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }

        foreach ([
            'implementation_request:',
            'Empresas con BI en Plan',
            'Solicitudes activas',
            'Sin solicitud',
            'Esperando solicitud',
            'row.implementation_request',
            'detail_url',
            'Definitions listas',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $page
            );
        }

        $this->assertStringNotContainsString(
            'row.urls.definition',
            $page
        );

        $this->assertStringNotContainsString(
            'props.stats.with_definition',
            $page
        );
    }
}
