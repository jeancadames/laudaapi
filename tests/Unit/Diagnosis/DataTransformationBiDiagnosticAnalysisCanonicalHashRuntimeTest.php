<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiEvaluationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class DataTransformationBiDiagnosticAnalysisCanonicalHashRuntimeTest
    extends TestCase
{
    private DataTransformationBiEvaluationService $service;

    private ReflectionMethod $hashMethod;

    private ReflectionMethod $canonicalMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection =
            new ReflectionClass(
                DataTransformationBiEvaluationService::class
            );

        /*
         * The canonical hash helpers have no constructor dependency.
         * Avoid booting database/service collaborators in this focused
         * semantic-hash contract test.
         */
        $this->service =
            $reflection
                ->newInstanceWithoutConstructor();

        $this->hashMethod =
            $reflection->getMethod(
                'diagnosticAnalysisSha256'
            );

        $this->canonicalMethod =
            $reflection->getMethod(
                'canonicalizeDiagnosticAnalysisValue'
            );

        $this->hashMethod
            ->setAccessible(true);

        $this->canonicalMethod
            ->setAccessible(true);
    }

    public function test_integral_float_and_integer_have_same_semantic_hash(): void
    {
        $beforeMysql = [
            'schema_version' => 3,
            'analyses' => [
                [
                    'key' =>
                        'record_identification',

                    'supporting_evidence' => [
                        [
                            'columns' => [
                                [
                                    'column_key' =>
                                        'column_1',

                                    'non_empty_percent' =>
                                        100.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        /*
         * This is the relevant MySQL JSON round-trip observed in
         * D2H-R7-R3B: 100.0 is returned as integer 100.
         */
        $afterMysql =
            $beforeMysql;

        $afterMysql[
            'analyses'
        ][0][
            'supporting_evidence'
        ][0][
            'columns'
        ][0][
            'non_empty_percent'
        ] = 100;

        $beforeHash =
            $this->hash(
                $beforeMysql
            );

        $afterHash =
            $this->hash(
                $afterMysql
            );

        self::assertSame(
            $beforeHash,
            $afterHash
        );
    }

    public function test_integral_float_is_canonicalized_to_integer(): void
    {
        self::assertSame(
            100,
            $this->canonicalize(
                100.0
            )
        );

        self::assertSame(
            0,
            $this->canonicalize(
                -0.0
            )
        );
    }

    public function test_non_integral_float_keeps_its_numeric_value(): void
    {
        $canonical =
            $this->canonicalize(
                99.5
            );

        self::assertIsFloat(
            $canonical
        );

        self::assertSame(
            99.5,
            $canonical
        );
    }

    public function test_associative_key_order_remains_irrelevant(): void
    {
        $left = [
            'schema_version' => 3,
            'evidence' => [
                'evidence_version' => 1,
                'evidence_sha256' =>
                    str_repeat('a', 64),
            ],
        ];

        $right = [
            'evidence' => [
                'evidence_sha256' =>
                    str_repeat('a', 64),
                'evidence_version' => 1,
            ],
            'schema_version' => 3,
        ];

        self::assertSame(
            $this->hash($left),
            $this->hash($right)
        );
    }

    public function test_list_order_remains_semantically_significant(): void
    {
        $left = [
            'analyses' => [
                'identifier',
                'temporal',
            ],
        ];

        $right = [
            'analyses' => [
                'temporal',
                'identifier',
            ],
        ];

        self::assertNotSame(
            $this->hash($left),
            $this->hash($right)
        );
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    private function hash(
        array $snapshot
    ): string {
        return $this->hashMethod
            ->invoke(
                $this->service,
                $snapshot
            );
    }

    private function canonicalize(
        mixed $value
    ): mixed {
        return $this
            ->canonicalMethod
            ->invoke(
                $this->service,
                $value
            );
    }
}
