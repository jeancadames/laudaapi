<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantPublishedUxCompactionContractTest extends TestCase
{
    private string $tenantUi;

    protected function setUp(): void
    {
        parent::setUp();

        $path =
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/DataTransformationBi.vue';

        self::assertFileExists($path);

        $content =
            file_get_contents($path);

        self::assertIsString($content);

        $this->tenantUi =
            $content;
    }

    public function test_definition_compacts_after_publication(): void
    {
        foreach (
            [
                'D2G_R9_TENANT_DEFINITION_COMPACTION',
                ':open="published_evaluation ? undefined : true"',
                'v-show="published_evaluation"',
                'Consulta la definición acordada',
                'tenantDefinitionReview.version',
                'Revisión LAUDA completada',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->tenantUi
            );
        }
    }

    public function test_delivery_compacts_after_publication(): void
    {
        foreach (
            [
                'D2G_R9_TENANT_DELIVERY_COMPACTION',
                'Consulta la entrega evaluada',
                'source_workspace.session.id',
                '.complete_source_count',
                '.source_count',
                'sourceWorkspaceStatusLabel',
                'Solo lectura',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->tenantUi
            );
        }
    }

    public function test_exactly_three_sections_use_published_compaction(): void
    {
        self::assertSame(
            3,
            substr_count(
                $this->tenantUi,
                ':open="published_evaluation ? undefined : true"'
            )
        );

        self::assertSame(
            3,
            substr_count(
                $this->tenantUi,
                'v-show="published_evaluation"'
            )
        );

        self::assertSame(
            3,
            substr_count(
                $this->tenantUi,
                'Ver detalle'
            )
        );
    }

    public function test_scope_compacts_after_publication(): void
    {
        foreach (
            [
                'D2G_R10_TENANT_SCOPE_COMPACTION',
                'Alcance potencial del servicio',
                'capabilityIncludesForDisplay.length',
                'área contemplada',
                'áreas contempladas',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->tenantUi
            );
        }
    }

    public function test_published_definition_summary_exposes_agreed_state(): void
    {
        self::assertStringContainsString(
            '· Definición acordada',
            $this->tenantUi
        );
    }

    public function test_redundant_agreed_card_is_hidden_after_publication(): void
    {
        self::assertStringContainsString(
            '&& !published_evaluation',
            $this->tenantUi
        );

        self::assertStringContainsString(
            'Tu empresa acordó esta versión',
            $this->tenantUi
        );
    }

    public function test_prepublication_actions_remain_available(): void
    {
        foreach (
            [
                'canAgreeDefinition',
                'requestDefinitionChanges',
                'prepareSourceWorkspace',
                'submitSourceWorkspaceForEvaluation',
                'createSourceAsset',
                'updateSourceAsset',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->tenantUi
            );
        }
    }

    public function test_source_detail_remains_inside_delivery(): void
    {
        $start =
            strpos(
                $this->tenantUi,
                'D2G_R9_TENANT_DELIVERY_COMPACTION'
            );

        $end =
            strpos(
                $this->tenantUi,
                'D2G_R9_TENANT_DELIVERY_COMPACTION_END'
            );

        self::assertIsInt($start);
        self::assertIsInt($end);
        self::assertGreaterThan(
            $start,
            $end
        );

        $block =
            substr(
                $this->tenantUi,
                $start,
                $end - $start
            );

        foreach (
            [
                'Fuentes activas',
                'selectedSource',
                'readOnlySourceTabs',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $block
            );
        }
    }

    public function test_published_report_stays_outside_compaction(): void
    {
        $deliveryEnd =
            strpos(
                $this->tenantUi,
                'D2G_R9_TENANT_DELIVERY_COMPACTION_END'
            );

        $reportStart =
            strpos(
                $this->tenantUi,
                'D2G_TENANT_PUBLISHED_REPORT_HEADER'
            );

        self::assertIsInt($deliveryEnd);
        self::assertIsInt($reportStart);

        self::assertGreaterThan(
            $deliveryEnd,
            $reportStart
        );

        foreach (
            [
                'Informe diagnóstico de datos e inteligencia BI',
                'D2D_TENANT_PUBLISHED_DIAGNOSTIC_ANALYSIS_UI',
                'D2G_TENANT_PUBLISHED_FINDINGS',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->tenantUi
            );
        }
    }

    public function test_d2g_authority_is_unchanged(): void
    {
        self::assertStringNotContainsString(
            'diagnostic_report',
            $this->tenantUi
        );

        foreach (
            [
                'tenantPublishedDiagnosticAnalyses()',
                'publishedFindingGroups',
                'Resultado del Diagnóstico 360 inicial.',
                'LAUDA no reconstruye resultados históricos',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->tenantUi
            );
        }
    }
}
