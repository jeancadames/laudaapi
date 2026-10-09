<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiTenantPublishedHistoryContractTest
    extends TestCase
{
    public function test_history_is_company_scoped_and_published_only(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Services/Diagnosis/'
                .'DataTransformationBiTenantPublishedEvaluationProjection.php'
            )
        );

        foreach ([
            'function forCompanyHistory(',
            "->where('company_id', \$companyId)",
            'DataTransformationBiEvaluation::STATUS_PUBLISHED',
            "'transformation_implementation_request_id'",
            '->limit(20)',
            '$this->project($evaluation)',
        ] as $required) {
            self::assertStringContainsString($required, $source);
        }
    }

    public function test_history_reuses_authorized_tenant_company(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            )
        );

        self::assertStringContainsString(
            "'published_evaluation_history'",
            $source
        );
        self::assertStringContainsString(
            '(int) $company->id',
            $source
        );
        self::assertStringContainsString(
            'forCompanyHistory(',
            $source
        );
    }

    public function test_history_is_read_only_in_tenant_ui(): void
    {
        $source = file_get_contents(
            base_path(
                'resources/js/pages/App/DataTransformationBi.vue'
            )
        );

        self::assertStringContainsString(
            'AT_D7_4_PUBLISHED_DIAGNOSTIC_HISTORY',
            $source
        );
        self::assertStringContainsString(
            'historical.findings',
            $source
        );
        self::assertStringContainsString(
            '!historical.diagnostic_analysis.available',
            $source
        );
    }
}
