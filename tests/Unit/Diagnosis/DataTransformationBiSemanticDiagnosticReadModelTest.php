<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiSemanticDiagnosticReadModel;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiSemanticDiagnosticReadModelTest
    extends TestCase
{
    public function test_empty_delivery_keeps_three_groups_without_claiming_business_absence(): void
    {
        $result =
            DataTransformationBiSemanticDiagnosticReadModel
                ::fromSources([]);

        self::assertSame(
            'declared_delivery_evidence',
            $result['scope']
        );

        self::assertSame(
            [
                'operaciones',
                'gestion',
                'finanzas',
            ],
            array_column(
                $result['groups'],
                'key'
            )
        );

        self::assertSame(
            0,
            $result['classification']
                ['unique_domain_count']
        );

        foreach (
            $result['groups']
            as $group
        ) {
            self::assertFalse(
                $group['has_declared_evidence']
            );

            self::assertSame(
                [],
                $group['domains']
            );
        }
    }

    public function test_one_source_can_support_domains_in_multiple_groups(): void
    {
        $result =
            DataTransformationBiSemanticDiagnosticReadModel
                ::fromSources([
                    [
                        'id' => 10,
                        'business_domains' => [
                            [
                                'domain' =>
                                    'Ventas',

                                'group' =>
                                    'operaciones',
                            ],
                            [
                                'domain' =>
                                    'CxC',

                                'group' =>
                                    'finanzas',
                            ],
                        ],
                        'diagnostic_summary' => [
                            'available' => true,
                        ],
                    ],
                ]);

        self::assertSame(
            2,
            $result['classification']
                ['declaration_count']
        );

        self::assertSame(
            2,
            $result['classification']
                ['unique_domain_count']
        );

        $operations =
            collect(
                $result['groups']
            )->firstWhere(
                'key',
                'operaciones'
            );

        $finance =
            collect(
                $result['groups']
            )->firstWhere(
                'key',
                'finanzas'
            );

        self::assertSame(
            'Ventas',
            $operations['domains'][0]['domain']
        );

        self::assertSame(
            [10],
            $operations['domains'][0]['source_ids']
        );

        self::assertSame(
            'profiled',
            $operations['domains'][0]
                ['technical_evidence_status']
        );

        self::assertSame(
            'CxC',
            $finance['domains'][0]['domain']
        );
    }

    public function test_same_dynamic_domain_is_combined_across_sources_without_canonicalizing_it(): void
    {
        $result =
            DataTransformationBiSemanticDiagnosticReadModel
                ::fromSources([
                    [
                        'id' => 20,
                        'business_domains' => [
                            [
                                'domain' =>
                                    'Clientes',

                                'group' =>
                                    'gestion',
                            ],
                        ],
                        'diagnostic_summary' => [
                            'available' => true,
                        ],
                    ],
                    [
                        'id' => 21,
                        'business_domains' => [
                            [
                                'domain' =>
                                    'clientes',

                                'group' =>
                                    'gestion',
                            ],
                        ],
                        'diagnostic_summary' => [
                            'available' => false,
                        ],
                    ],
                ]);

        self::assertSame(
            1,
            $result['classification']
                ['unique_domain_count']
        );

        $management =
            collect(
                $result['groups']
            )->firstWhere(
                'key',
                'gestion'
            );

        self::assertSame(
            [20, 21],
            $management['domains'][0]
                ['source_ids']
        );

        self::assertSame(
            1,
            $management['domains'][0]
                ['profiled_source_count']
        );

        self::assertSame(
            'partial',
            $management['domains'][0]
                ['technical_evidence_status']
        );
    }

    public function test_conflicting_group_declarations_are_exposed_for_review_not_resolved_automatically(): void
    {
        $result =
            DataTransformationBiSemanticDiagnosticReadModel
                ::fromSources([
                    [
                        'id' => 30,
                        'business_domains' => [
                            [
                                'domain' =>
                                    'Clientes',

                                'group' =>
                                    'gestion',
                            ],
                        ],
                        'diagnostic_summary' => [
                            'available' => true,
                        ],
                    ],
                    [
                        'id' => 31,
                        'business_domains' => [
                            [
                                'domain' =>
                                    'CLIENTES',

                                'group' =>
                                    'operaciones',
                            ],
                        ],
                        'diagnostic_summary' => [
                            'available' => true,
                        ],
                    ],
                ]);

        self::assertSame(
            1,
            $result['classification']
                ['group_conflict_count']
        );

        self::assertCount(
            1,
            $result['classification_conflicts']
        );

        self::assertSame(
            [
                'operaciones',
                'gestion',
            ],
            $result['classification_conflicts'][0]
                ['groups']
        );

        self::assertSame(
            [30, 31],
            $result['classification_conflicts'][0]
                ['source_ids']
        );
    }

    public function test_source_without_declared_domains_is_tracked_without_becoming_a_gate(): void
    {
        $result =
            DataTransformationBiSemanticDiagnosticReadModel
                ::fromSources([
                    [
                        'id' => 40,
                        'business_domains' => [],
                        'diagnostic_summary' => [
                            'available' => true,
                        ],
                    ],
                    [
                        'id' => 41,
                        'business_domains' => [
                            [
                                'domain' =>
                                    'Inventario',

                                'group' =>
                                    'operaciones',
                            ],
                        ],
                        'diagnostic_summary' => [
                            'available' => true,
                        ],
                    ],
                ]);

        self::assertSame(
            2,
            $result['classification']
                ['source_count']
        );

        self::assertSame(
            1,
            $result['classification']
                ['declared_source_count']
        );

        self::assertSame(
            1,
            $result['classification']
                ['unclassified_source_count']
        );

        self::assertSame(
            [40],
            $result['unclassified_source_ids']
        );
    }

    public function test_projection_does_not_depend_on_canonical_or_future_pipeline(): void
    {
        $source =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSemanticDiagnosticReadModel.php'
            );

        foreach ([
            'DataTransformationBiSourceDomainRegistry',
            "'domain_key'",
            'DataTransformationBiStaging',
            'DataTransformationBiCanonical',
            'DataTransformationBiPreparedDataset',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        /*
         * Comments may legitimately explain that this read model does
         * NOT produce scores. The contract must reject executable /
         * payload scoring semantics, not the explanatory word itself.
         */
        foreach ([
            "'score' =>",
            '"score" =>',
            "'risk_score' =>",
            '"risk_score" =>',
            "'readiness_score' =>",
            '"readiness_score" =>',
            'function score(',
            'function calculatescore(',
            '->score(',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                strtolower(
                    $source
                )
            );
        }
    }
}
