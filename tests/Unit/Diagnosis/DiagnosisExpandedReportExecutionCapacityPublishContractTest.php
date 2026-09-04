<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Services\Diagnosis\DiagnosisExpandedReportGenerator;
use Tests\TestCase;

class DiagnosisExpandedReportExecutionCapacityPublishContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_current_generator_uses_execution_capacity(): void
    {
        $generator = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'DiagnosisExpandedReportGenerator.php'
        );

        $this->assertStringContainsString(
            "'execution_capacity' =>",
            $generator
        );

        $this->assertStringNotContainsString(
            "'modality_and_capacity' =>",
            $generator
        );
    }

    public function test_publish_uses_current_execution_capacity_section(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'DiagnosisExpandedReportService.php'
        );

        $this->assertStringContainsString(
            "'execution_capacity.body'",
            $service
        );

        $this->assertStringNotContainsString(
            "'modality_and_capacity.body'",
            $service
        );
    }

    public function test_generated_section_is_publishable(): void
    {
        $assessment =
            new DiagnosisAssessment([
                'organization_name' =>
                    'S13 Runtime QA',

                'methodology_version' =>
                    '1.0',

                'status' =>
                    'reviewed',

                'maturity_score' =>
                    56,

                'capacity_score' =>
                    62,

                'urgency_score' =>
                    71,

                'dimension_scores' => [
                    'strategy' => 76,
                    'people' => 74,
                    'presence' => 72,
                    'commercial' => 68,
                    'operations' => 67,
                    'technology' => 69,
                    'data' => 70,
                    'governance' => 73,
                ],

                'maturity_level' =>
                    'Intermedio',

                'urgency_level' =>
                    'Alta',

                'review_summary' =>
                    'Fixture runtime.',

                'review_priorities' => [
                    'Datos',
                    'Integración',
                ],

                'business_activity_type' =>
                    'services',

                'business_sector' =>
                    'technology',

                'customer_market' =>
                    'b2b',

                'sales_channels' => [
                    'digital',
                ],

                'logistics_operation_types' =>
                    [],

                'business_activity_description' =>
                    'Empresa B2B.',
            ]);

        $assessment->published_at = now();

        $sections =
            app(
                DiagnosisExpandedReportGenerator::class
            )
                ->generate($assessment)
                ['sections'];

        $this->assertArrayHasKey(
            'execution_capacity',
            $sections
        );

        $this->assertNotEmpty(
            data_get(
                $sections,
                'execution_capacity.body'
            )
        );

        $this->assertArrayNotHasKey(
            'modality_and_capacity',
            $sections
        );
    }
}
