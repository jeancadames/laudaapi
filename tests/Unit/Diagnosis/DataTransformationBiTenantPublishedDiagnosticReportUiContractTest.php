<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantPublishedDiagnosticReportUiContractTest
    extends TestCase
{
    private string $source;

    private string $projection;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->source =
            (string) file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        $this->projection =
            (string) file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantPublishedEvaluationProjection.php'
            );
    }

    public function test_tenant_report_is_composed_from_published_evaluation(): void
    {
        foreach ([
            'D2G_TENANT_PUBLISHED_REPORT_HEADER',
            'tenantPublishedReportPublishedAtLabel',
            'published_evaluation',
            '.published_at',
            '.summary',
            '.weakness_count',
            '.opportunity_count',
            '.observation_count',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_tenant_report_does_not_add_diagnostic_report_payload(): void
    {
        self::assertStringNotContainsString(
            'diagnostic_report',
            $this->source
        );

        self::assertStringNotContainsString(
            "'diagnostic_report' =>",
            $this->projection
        );

        self::assertStringNotContainsString(
            'DataTransformationBiDiagnosticReportProjection',
            $this->projection
        );
    }

    public function test_structural_analysis_body_is_reused_not_duplicated(): void
    {
        self::assertSame(
            1,
            preg_match_all(
                '/v-for="\s*analysis in\s*'
                .'tenantPublishedDiagnosticAnalyses\(\)\s*"/',
                $this->source
            )
        );

        self::assertSame(
            1,
            substr_count(
                $this->source,
                '<!-- D2D_TENANT_PUBLISHED_DIAGNOSTIC_ANALYSIS_UI -->'
            )
        );
    }

    public function test_professional_finding_body_is_reused_not_duplicated(): void
    {
        self::assertSame(
            1,
            preg_match_all(
                '/v-for="\s*finding in\s*'
                .'group\.findings\s*"/',
                $this->source
            )
        );

        self::assertStringContainsString(
            'const publishedFindingGroups = computed(() =>',
            $this->source
        );
    }

    public function test_report_header_uses_only_safe_published_context(): void
    {
        $start =
            strpos(
                $this->source,
                '<!-- D2G_TENANT_PUBLISHED_REPORT_HEADER -->'
            );

        $end =
            strpos(
                $this->source,
                '<!-- D2G_TENANT_PUBLISHED_REPORT_HEADER_END -->'
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $this->source,
                $start,
                $end - $start
            );

        foreach ([
            'published_evaluation',
            '.summary',
            '.weakness_count',
            '.opportunity_count',
            '.observation_count',
            'tenantPublishedReportPublishedAtLabel',
            'Informe diagnóstico de datos e inteligencia BI',
            'preparación global para BI',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $block
            );
        }

        foreach ([
            'evidence_version',
            'evidence_current',
            'stale_finding_count',
            'evidence_sha256',
            'diagnostic_analysis_sha256',
            'source_object_name',
            'supporting_source_ids',
            'source_id',
            'ready_for_review',
            'published_by_user_id',
            'readiness_score',
            'risk_score',
            'opportunity_score',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_tenant_report_has_no_admin_authoring_controls(): void
    {
        $start =
            strpos(
                $this->source,
                '<!-- D2G_TENANT_PUBLISHED_REPORT_HEADER -->'
            );

        $end =
            strpos(
                $this->source,
                '<!-- D2G_TENANT_PUBLISHED_REPORT_HEADER_END -->'
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $this->source,
                $start,
                $end - $start
            );

        foreach ([
            'Editar',
            'Eliminar',
            'Reconfirmar',
            'Enviar a revisión',
            'Publicar evaluación',
            'can_manage_findings',
            'saveDataBiEvaluationFinding',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_report_header_is_single_presentation_context(): void
    {
        self::assertSame(
            1,
            substr_count(
                $this->source,
                '<!-- D2G_TENANT_PUBLISHED_REPORT_HEADER -->'
            )
        );

        self::assertSame(
            1,
            substr_count(
                $this->source,
                '<!-- D2G_TENANT_PUBLISHED_REPORT_HEADER_END -->'
            )
        );
    }
}
