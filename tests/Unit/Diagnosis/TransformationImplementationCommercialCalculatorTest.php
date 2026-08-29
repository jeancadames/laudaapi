<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationImplementationCommercialCalculator;
use PHPUnit\Framework\TestCase;

class TransformationImplementationCommercialCalculatorTest
    extends TestCase
{
    private function calculator():
        TransformationImplementationCommercialCalculator
    {
        return new TransformationImplementationCommercialCalculator();
    }

    private function configuredMatrix(): array
    {
        return [
            'version' =>
                'test_matrix',

            'currency' =>
                'DOP',

            'modalities' => [
                'guided' => [
                    'initiative_effort' => [
                        'medium' => [
                            'price_amount' => 100,
                            'duration_days' => 2,
                        ],
                    ],

                    'professional_capabilities' => [
                        'procedures_guide' => [
                            'price_amount' => 20,
                            'duration_days' => 1,
                        ],
                    ],
                ],

                'assisted' => [
                    'initiative_effort' => [
                        'medium' => [
                            'price_amount' => 200,
                            'duration_days' => 3,
                        ],
                    ],

                    'professional_capabilities' => [
                        'procedures_guide' => [
                            'price_amount' => 40,
                            'duration_days' => 2,
                        ],
                    ],
                ],

                'managed' => [
                    'initiative_effort' => [
                        'medium' => [
                            'price_amount' => 300,
                            'duration_days' => 4,
                        ],
                    ],

                    'professional_capabilities' => [
                        'procedures_guide' => [
                            'price_amount' => 60,
                            'duration_days' => 3,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function phase(): array
    {
        return [
            'id' => 10,
            'sequence' => 1,
            'name' => 'Fase de prueba',

            'initiatives' => [
                [
                    'id' => 'OPS-01',
                    'effort' => 'medium',
                ],
            ],

            'professional_capabilities' => [
                [
                    'key' =>
                        'procedures_guide',

                    'label' =>
                        'Guía de Procesos y Procedimientos',
                ],
            ],
        ];
    }

    public function test_calculates_three_modalities_from_same_phase(): void
    {
        $quote =
            $this->calculator()->quotePlan(
                [$this->phase()],
                $this->configuredMatrix(),
                [
                    'guided',
                    'assisted',
                    'managed',
                ]
            );

        $this->assertTrue(
            $quote['ready']
        );

        $this->assertSame(
            120.0,
            $quote['modalities']
                ['guided']
                ['price_amount']
        );

        $this->assertSame(
            3,
            $quote['modalities']
                ['guided']
                ['duration_days']
        );

        $this->assertSame(
            240.0,
            $quote['modalities']
                ['assisted']
                ['price_amount']
        );

        $this->assertSame(
            5,
            $quote['modalities']
                ['assisted']
                ['duration_days']
        );

        $this->assertSame(
            360.0,
            $quote['modalities']
                ['managed']
                ['price_amount']
        );

        $this->assertSame(
            7,
            $quote['modalities']
                ['managed']
                ['duration_days']
        );
    }

    public function test_null_values_never_become_zero_price_configuration(): void
    {
        $matrix =
            $this->configuredMatrix();

        $matrix['modalities']
            ['guided']
            ['initiative_effort']
            ['medium']
            ['price_amount'] = null;

        $quote =
            $this->calculator()->quotePlan(
                [$this->phase()],
                $matrix,
                [
                    'guided',
                    'assisted',
                    'managed',
                ]
            );

        $this->assertFalse(
            $quote['ready']
        );

        $this->assertFalse(
            $quote['modalities']
                ['guided']
                ['complete']
        );

        $this->assertContains(
            'modalities.guided.initiative_effort.medium.price_amount',
            $quote['missing']
        );
    }

    public function test_subscription_solution_capabilities_are_not_part_of_professional_surcharge(): void
    {
        $phase =
            $this->phase();

        $phase['professional_capabilities'] = [];

        $quote =
            $this->calculator()->quotePlan(
                [$phase],
                $this->configuredMatrix(),
                ['guided']
            );

        $this->assertTrue(
            $quote['ready']
        );

        $this->assertSame(
            100.0,
            $quote['modalities']
                ['guided']
                ['price_amount']
        );

        $this->assertCount(
            1,
            $quote['modalities']
                ['guided']
                ['phases']
                [0]
                ['breakdown']
        );

        $this->assertSame(
            'initiative',
            $quote['modalities']
                ['guided']
                ['phases']
                [0]
                ['breakdown']
                [0]
                ['type']
        );
    }

    public function test_phase_without_initiatives_is_not_commercially_ready(): void
    {
        $phase =
            $this->phase();

        $phase['initiatives'] = [];

        $quote =
            $this->calculator()->quotePlan(
                [$phase],
                $this->configuredMatrix(),
                ['guided']
            );

        $this->assertFalse(
            $quote['ready']
        );

        $this->assertContains(
            'phase.10.initiatives',
            $quote['missing']
        );
    }
}
