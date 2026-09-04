<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapGenerator;
use App\Services\Diagnosis\TransformationImplementationPlanAutogenerator;
use Tests\TestCase;

class DataTransformationBiImplementationPlanFlowTest
    extends TestCase
{
    private function assessment(
        float $dataScore
    ): DiagnosisAssessment {
        $assessment =
            new DiagnosisAssessment();

        $assessment->id = 1320;

        $assessment->organization_name =
            'S13 Plan Contract';

        $assessment->methodology_version =
            's13-plan-contract';

        $assessment->status =
            'reviewed';

        $assessment->published_at =
            null;

        $assessment->maturity_score =
            65;

        $assessment->capacity_score =
            65;

        $assessment->urgency_score =
            65;

        $assessment->dimension_scores = [
            'strategy' => 70,
            'people' => 70,
            'presence' => 70,
            'commercial' => 70,
            'operations' => 70,
            'technology' => 70,
            'data' => $dataScore,
            'governance' => 70,
        ];

        $assessment->review_summary =
            'Contrato funcional S13-C2B.';

        $assessment->review_priorities =
            [];

        $assessment->final_modality =
            null;

        $assessment->final_modality_label =
            null;

        $assessment->recommended_modality =
            null;

        $assessment->recommended_modality_label =
            null;

        $assessment->business_activity_type =
            'goods';

        $assessment->business_sector =
            'commerce';

        $assessment->customer_market =
            'both';

        $assessment->sales_channels =
            [];

        $assessment->logistics_operation_types =
            [];

        /*
         * Evita señales accidentales de Branding.
         */
        $assessment->business_activity_description =
            'Operación comercial con productos e inventario.';

        return $assessment;
    }

    private function generatedRoadmap(
        float $dataScore
    ): array {
        return app(
            DiagnosisDetailedRoadmapGenerator::class
        )->generateFromAssessment(
            $this->assessment($dataScore)
        );
    }

    private function preview(
        float $dataScore
    ): array {
        $generated =
            $this->generatedRoadmap(
                $dataScore
            );

        $plan =
            new TransformationImplementationPlan();

        $plan->forceFill([
            'source_snapshot' => [
                'source_type' =>
                    'internal_assessment',

                'internal_roadmap' =>
                    $generated['roadmap'],
            ],
        ]);

        return app(
            TransformationImplementationPlanAutogenerator::class
        )->preview($plan);
    }

    private function phaseContaining(
        array $preview,
        string $initiativeKey
    ): ?array {
        foreach (
            $preview['phases']
            as $phase
        ) {
            if (
                in_array(
                    $initiativeKey,
                    $phase['initiative_ids'] ?? [],
                    true
                )
            ) {
                return $phase;
            }
        }

        return null;
    }

    private function capabilityFromPhase(
        array $phase,
        string $key
    ): ?array {
        foreach (
            $phase['capabilities'] ?? []
            as $capability
        ) {
            if (
                ($capability['capability_key'] ?? null)
                === $key
            ) {
                return $capability;
            }
        }

        return null;
    }

    private function capabilityCount(
        array $preview,
        string $key
    ): int {
        $count = 0;

        foreach (
            $preview['phases']
            as $phase
        ) {
            foreach (
                $phase['capabilities'] ?? []
                as $capability
            ) {
                if (
                    ($capability['capability_key'] ?? null)
                    === $key
                ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function test_recommended_bi_enters_plan_in_dat_01_phase(): void
    {
        $preview =
            $this->preview(70);

        $this->assertSame(
            'internal_assessment',
            $preview['source_type']
        );

        $phase =
            $this->phaseContaining(
                $preview,
                'DAT-01'
            );

        $this->assertNotNull($phase);

        /*
         * DAT-01 pertenece contractualmente a
         * Fase 3 · Conectar y medir.
         */
        $this->assertSame(
            3,
            $phase['sequence']
        );

        $this->assertSame(
            'Fase 3 · Conectar y medir',
            $phase['name']
        );

        $bi =
            $this->capabilityFromPhase(
                $phase,
                'data_transformation_bi'
            );

        $this->assertNotNull($bi);

        $this->assertSame(
            'data_transformation_bi',
            $bi['capability_key']
        );

        $this->assertSame(
            'Transformación e Inteligencia de Datos para BI',
            $bi['label']
        );

        $this->assertSame(
            'professional_service',
            $bi['kind']
        );

        $this->assertNull(
            $bi['service_key']
        );

        $this->assertFalse(
            $bi['subscription_candidate']
        );

        $this->assertSame(
            ['DAT-01'],
            $bi['linked_initiative_keys']
        );

        $this->assertTrue(
            $bi['requires_lauda_review']
        );

        $this->assertSame(
            'implementation_plan_estimate_required',
            $bi['commercial_readiness']
        );

        $this->assertSame(
            'implementation_only',
            $bi['activation_policy']
        );

        /*
         * El fundamento debe conservar la explicación
         * del Roadmap, no una etiqueta genérica.
         */
        $this->assertStringContainsString(
            '70/100',
            $bi['recommendation_basis']
        );

        $this->assertStringContainsString(
            'prioridad media',
            $bi['recommendation_basis']
        );

        $this->assertSame(
            1,
            $this->capabilityCount(
                $preview,
                'data_transformation_bi'
            )
        );
    }

    public function test_non_recommended_bi_does_not_enter_plan(): void
    {
        $generated =
            $this->generatedRoadmap(90);

        $roadmapBi =
            $generated['roadmap']
                ['transformation_capabilities']
                ['data_transformation_bi'];

        $this->assertFalse(
            $roadmapBi['recommended']
        );

        $preview =
            $this->preview(90);

        /*
         * DAT-01 sigue existiendo como iniciativa
         * del Roadmap, pero BI no se fuerza al Plan.
         */
        $phase =
            $this->phaseContaining(
                $preview,
                'DAT-01'
            );

        $this->assertNotNull($phase);

        $this->assertSame(
            3,
            $phase['sequence']
        );

        $this->assertNull(
            $this->capabilityFromPhase(
                $phase,
                'data_transformation_bi'
            )
        );

        $this->assertSame(
            0,
            $this->capabilityCount(
                $preview,
                'data_transformation_bi'
            )
        );
    }

    public function test_preview_does_not_create_commercial_state(): void
    {
        $preview =
            $this->preview(70);

        $this->assertNotEmpty(
            $preview['phases']
        );

        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        foreach ([
            'Service::create(',
            'Subscription::create(',
            'SubscriptionItem::create(',
            'Invoice::create(',
            'Payment::create(',
            'upsertEstimate(',
            'upsertMilestone(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_plan_snapshot_contract_preserves_bi_metadata(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        foreach ([
            "'requires_lauda_review' =>",
            "'commercial_readiness' =>",
            "'activation_policy' =>",
            "'recommendation_basis' =>",
            "'professional_transformation_capability'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
