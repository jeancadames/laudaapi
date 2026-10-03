<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantPublishedEvaluationUiContractTest
    extends TestCase
{
    private string $controller;

    private string $page;

    private string $projection;

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
                .'DataTransformationBiTenantPublishedEvaluationProjection.php'
            );
    }

    public function test_controller_exposes_only_dedicated_tenant_projection(): void
    {
        foreach (
            [
                'DataTransformationBiTenantPublishedEvaluationProjection',
                '$publishedEvaluationProjection->forRequest(',
                "'published_evaluation' =>",
            ]
            as $token
        ) {
            self::assertStringContainsString(
                $token,
                $this->controller
            );
        }

        self::assertStringNotContainsString(
            'DataTransformationBiEvaluationWorkspaceReadModel',
            $this->controller
        );
    }

    public function test_projection_filters_to_published_request_scope(): void
    {
        foreach (
            [
                "'company_id',",
                "'transformation_implementation_request_id',",
                'DataTransformationBiEvaluation::STATUS_PUBLISHED',
                "'findings.sources'",
                "'weakness_count' =>",
                "'opportunity_count' =>",
                "'observation_count' =>",
            ]
            as $token
        ) {
            self::assertStringContainsString(
                $token,
                $this->projection
            );
        }
    }

    public function test_tenant_page_renders_published_result_after_delivery(): void
    {
        $deliveryEnd =
            strpos(
                $this->page,
                'Tu empresa administra la información y sus entregas.'
            );

        $published =
            strpos(
                $this->page,
                '<!-- DATA_BI_TENANT_PUBLISHED_EVALUATION -->'
            );

        $legacy =
            strpos(
                $this->page,
                '<!-- P7_DATA_PREPARATION_STATUS -->'
            );

        self::assertNotFalse($deliveryEnd);
        self::assertNotFalse($published);
        self::assertNotFalse($legacy);

        self::assertGreaterThan(
            $deliveryEnd,
            $published
        );

        self::assertGreaterThan(
            $published,
            $legacy
        );

        foreach (
            [
                'published_evaluation: TenantPublishedEvaluation | null',
                'Resultado publicado',
                'Informe diagnóstico de datos e inteligencia BI',
                'Debilidades',
                'Oportunidades',
                'Observaciones',
                'Recomendación',
                'Hallazgo general',
                'publishedFindingGroups',
            ]
            as $token
        ) {
            self::assertStringContainsString(
                $token,
                $this->page
            );
        }
    }

    public function test_published_ui_contains_no_admin_lifecycle_controls(): void
    {
        $start =
            strpos(
                $this->page,
                '<!-- DATA_BI_TENANT_PUBLISHED_EVALUATION -->'
            );

        $end =
            strpos(
                $this->page,
                '<!-- P7_DATA_PREPARATION_STATUS -->',
                $start
            );

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $block =
            substr(
                $this->page,
                $start,
                $end - $start
            );

        foreach (
            [
                'Actualizar evidencia',
                'Reconfirmar',
                'Enviar a revisión',
                'Publicar evaluación',
                'Editar',
                'Eliminar',
                'evidence_version',
                'evidence_current',
                'stale_finding_count',
                'evidence_sha256',
                'evidence_snapshot',
                'submission_manifest_sha256',
                'ready_for_review',
                'created_by_user_id',
                'published_by_user_id',
                'source_object_name',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_published_evaluation_marks_submitted_delivery_as_completed(): void
    {
        foreach (
            [
                "status === 'submitted_for_evaluation'",
                'props.published_evaluation !== null',
                "return 'Evaluación completada';",
                'const sourceWorkspaceSubmittedMessage =',
                'LAUDA completó y publicó la evaluación diagnóstica.',
                '{{ sourceWorkspaceStatusLabel }}',
                '{{ sourceWorkspaceSubmittedMessage }}',
            ]
            as $token
        ) {
            self::assertStringContainsString(
                $token,
                $this->page
            );
        }

        /*
         * The persisted intake lifecycle is intentionally unchanged.
         * Without a published evaluation, submitted deliveries still
         * render as "En evaluación".
         */
        self::assertStringContainsString(
            "submitted_for_evaluation:",
            $this->page
        );

        self::assertStringContainsString(
            "'En evaluación'",
            $this->page
        );

        self::assertStringContainsString(
            'Solo lectura',
            $this->page
        );
    }


}
