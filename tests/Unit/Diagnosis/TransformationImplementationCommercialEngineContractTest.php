<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationCommercialEngineContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_real_matrix_starts_without_invented_prices(): void
    {
        $matrix = require
            $this->root()
            .'/config/lauda360_implementation.php';

        $this->assertSame(
            'commercial_matrix_v1',
            $matrix['version']
        );

        $this->assertSame(
            'DOP',
            $matrix['currency']
        );

        foreach (
            [
                'guided',
                'assisted',
                'managed',
            ] as $modality
        ) {
            foreach (
                [
                    'low',
                    'medium',
                    'high',
                ] as $effort
            ) {
                $this->assertNull(
                    $matrix['modalities']
                        [$modality]
                        ['initiative_effort']
                        [$effort]
                        ['price_amount']
                );

                $this->assertNull(
                    $matrix['modalities']
                        [$modality]
                        ['initiative_effort']
                        [$effort]
                        ['duration_days']
                );
            }

            foreach (
                [
                    'procedures_guide',
                    'branding_identity',
                ] as $capability
            ) {
                $this->assertNull(
                    $matrix['modalities']
                        [$modality]
                        ['professional_capabilities']
                        [$capability]
                        ['price_amount']
                );

                $this->assertNull(
                    $matrix['modalities']
                        [$modality]
                        ['professional_capabilities']
                        [$capability]
                        ['duration_days']
                );
            }
        }
    }

    public function test_engine_generates_estimates_but_not_commercial_side_effects(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'TransformationImplementationCommercialEngine.php'
            );

        foreach (
            [
                'function preview(',
                'function generate(',
                'commercial_matrix',
                'initiative_effort_plus_professional_capabilities',
                'recurring_solution_pricing_included',
                'upsertEstimate(',
            ] as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach (
            [
                'Subscription::',
                'SubscriptionItem::',
                'Invoice::',
                'Payment::',
                'upsertMilestone(',
                'selected_modality' . ' =>',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_only_professional_capabilities_receive_direct_capability_surcharge(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'TransformationImplementationCommercialEngine.php'
            );

        $this->assertStringContainsString(
            "'professional_service'",
            $source
        );

        $this->assertStringNotContainsString(
            "'subscription_service'",
            $source
        );
    }

    public function test_generate_is_draft_only(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'TransformationImplementationCommercialEngine.php'
            );

        $this->assertStringContainsString(
            'STATUS_DRAFT',
            $source
        );

        $this->assertStringContainsString(
            'solo puede regenerarse mientras el Plan está en borrador',
            $source
        );
    }
}
