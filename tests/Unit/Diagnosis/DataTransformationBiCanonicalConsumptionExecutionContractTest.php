<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalConsumptionExecution;
use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetContext;
use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetManifest;

function p25Source(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiCanonicalConsumptionExecution.php'
    );
}

function p25Dataset(
    int $processingRunId
): array {
    return [
        'processing_run_id' =>
            $processingRunId,

        'intake_batch_id' =>
            $processingRunId + 200000,

        'definition_version' =>
            1,

        'schema_version' =>
            '1',

        'profiling_version' =>
            '1',

        'normalization_version' =>
            '1',

        'normalized_row_count' =>
            0,

        'has_rows' =>
            false,

        'completed_at' =>
            '2026-09-16 01:00:00',
    ];
}

function p25Side(
    int $processingRunId,
    int $companyId = 19,
    int $implementationRequestId = 1
): array {
    $dataset =
        p25Dataset(
            $processingRunId
        );

    $context =
        DataTransformationBiCanonicalDatasetContext
            ::fromResolved(
                $companyId,
                $implementationRequestId,
                $dataset
            );

    $domains = [];

    foreach (
        DataTransformationBiCanonicalDatasetContext
            ::domains()
        as $domain
    ) {
        $domains[] =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDomain(
                    $domain,
                    []
                );
    }

    return [
        'context' =>
            $context,

        'manifest' => [
            'fingerprint_version' =>
                1,

            'fingerprint_algorithm' =>
                'sha256',

            'company_id' =>
                $companyId,

            'implementation_request_id' =>
                $implementationRequestId,

            'dataset' =>
                $dataset,

            'total_rows' =>
                0,

            'domains' =>
                $domains,

            'dataset_fingerprint' =>
                DataTransformationBiCanonicalDatasetManifest
                    ::fingerprintDataset(
                        $dataset,
                        $domains
                    ),
        ],
    ];
}

function p25Plan(
    string $mode,
    array $target,
    ?array $base,
    ?array $compatibility,
    ?bool $sameContent,
    string $reason
): array {
    return [
        'available' =>
            true,

        'mode' =>
            $mode,

        'reason' =>
            $reason,

        'target' =>
            $target,

        'base' =>
            $base,

        'compatibility' =>
            $compatibility,

        'same_content' =>
            $sameContent,
    ];
}

test(
    'p25 exposes only resolved plan execution api',
    function () {
        $source =
            p25Source();

        expect($source)
            ->toContain(
                'public function iteratePlan('
            )
            ->not
            ->toContain(
                'public function plan('
            )
            ->not
            ->toContain(
                'public function current('
            );
    }
);

test(
    'p25 delegates snapshot and incremental to resolved executors only',
    function () {
        $source =
            p25Source();

        expect(
            substr_count(
                $source,
                '->iterateResolvedSnapshot('
            )
        )->toBe(1);

        expect(
            substr_count(
                $source,
                '->iterateResolvedPair('
            )
        )->toBe(1);

        foreach ([
            'DataTransformationBiCanonicalConsumptionPlan',
            'DataTransformationBiCanonicalDatasetManifestResolver',
            'DataTransformationBiUsableDatasetResolver',
            'DataTransformationBiVersionedDatasetResolver',
            '->plan(',
            '->current(',
            '->pair(',
            '->forRequest(',
            '->forProcessingRun(',
            '->iterateDatasetChanges(',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain(
                    $forbidden
                );
        }
    }
);

test(
    'p25 snapshot adapter preserves pinned target',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionExecution::class
            );

        $target =
            p25Side(
                20
            );

        $plan =
            p25Plan(
                'snapshot',
                $target,
                null,
                null,
                null,
                'baseline_not_provided'
            );

        $validate =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionExecution::class,
                'validatedPlan'
            );

        $adapter =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionExecution::class,
                'snapshotEnvelope'
            );

        $validated =
            $validate->invoke(
                $service,
                $plan
            );

        $snapshot =
            $adapter->invoke(
                $service,
                $validated
            );

        expect($snapshot)
            ->toBe([
                'available' =>
                    true,

                'reason' =>
                    'baseline_not_provided',

                'context' =>
                    $target['context'],

                'manifest' =>
                    $target['manifest'],
            ]);
    }
);

test(
    'p25 incremental adapter preserves pinned pair',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionExecution::class
            );

        $base =
            p25Side(
                10
            );

        $target =
            p25Side(
                20
            );

        $compatibility = [
            'schema_version' =>
                1,

            'normalization_version' =>
                1,
        ];

        $plan =
            p25Plan(
                'incremental',
                $target,
                $base,
                $compatibility,
                false,
                'compatible_versioned_dataset_pair'
            );

        $validate =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionExecution::class,
                'validatedPlan'
            );

        $adapter =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionExecution::class,
                'incrementalPair'
            );

        $validated =
            $validate->invoke(
                $service,
                $plan
            );

        $pair =
            $adapter->invoke(
                $service,
                $validated
            );

        expect($pair)
            ->toBe([
                'available' =>
                    true,

                'reason' =>
                    'compatible_versioned_dataset_pair',

                'base' =>
                    $base,

                'target' =>
                    $target,

                'compatibility' =>
                    $compatibility,

                'same_content' =>
                    false,
            ]);
    }
);

test(
    'p25 up to date exact run yields zero rows without dataset execution',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionExecution::class
            );

        $target =
            p25Side(
                20
            );

        $plan =
            p25Plan(
                'up_to_date',
                $target,
                $target,
                null,
                true,
                'baseline_is_current_target'
            );

        $rows =
            iterator_to_array(
                $service->iteratePlan(
                    $plan,
                    500
                ),
                false
            );

        expect($rows)
            ->toBe([]);
    }
);

test(
    'p25 up to date equivalent historical run yields zero rows',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionExecution::class
            );

        $base =
            p25Side(
                10
            );

        $target =
            p25Side(
                20
            );

        $plan =
            p25Plan(
                'up_to_date',
                $target,
                $base,
                [
                    'schema_version' =>
                        1,

                    'normalization_version' =>
                        1,
                ],
                true,
                'baseline_content_matches_current_target'
            );

        $rows =
            iterator_to_array(
                $service->iteratePlan(
                    $plan,
                    500
                ),
                false
            );

        expect($rows)
            ->toBe([]);
    }
);

test(
    'p25 rejects unavailable plans',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionExecution::class
            );

        $plan = [
            'available' =>
                false,

            'mode' =>
                null,

            'reason' =>
                'no_successful_normalized_dataset',

            'target' =>
                null,

            'base' =>
                null,

            'compatibility' =>
                null,

            'same_content' =>
                null,
        ];

        expect(
            fn () =>
                iterator_to_array(
                    $service->iteratePlan(
                        $plan,
                        500
                    ),
                    false
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p25 rejects invalid mode semantics',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionExecution::class
            );

        $target =
            p25Side(
                20
            );

        $invalidIncremental =
            p25Plan(
                'incremental',
                $target,
                null,
                null,
                null,
                'invalid'
            );

        expect(
            fn () =>
                iterator_to_array(
                    $service->iteratePlan(
                        $invalidIncremental,
                        500
                    ),
                    false
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p25 rejects cross context baseline',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionExecution::class
            );

        $base =
            p25Side(
                10,
                20,
                1
            );

        $target =
            p25Side(
                20,
                19,
                1
            );

        $plan =
            p25Plan(
                'incremental',
                $target,
                $base,
                [
                    'schema_version' =>
                        1,

                    'normalization_version' =>
                        1,
                ],
                false,
                'compatible_versioned_dataset_pair'
            );

        expect(
            fn () =>
                iterator_to_array(
                    $service->iteratePlan(
                        $plan,
                        500
                    ),
                    false
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p25 page size fails closed',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionExecution::class
            );

        $target =
            p25Side(
                20
            );

        $plan =
            p25Plan(
                'up_to_date',
                $target,
                $target,
                null,
                true,
                'baseline_is_current_target'
            );

        foreach ([0, 501] as $pageSize) {
            expect(
                fn () =>
                    iterator_to_array(
                        $service->iteratePlan(
                            $plan,
                            $pageSize
                        ),
                        false
                    )
            )->toThrow(
                \InvalidArgumentException::class
            );
        }
    }
);

test(
    'p25 is stateless read only and model neutral',
    function () {
        $source =
            p25Source();

        foreach ([
            'DataTransformationBiNormalizedRow',
            'DataTransformationBiPreparedDatasetReader',
            'normalized_payload',
            "'payload'",
            "'normalized_row_id'",
            "'identity_hash'",
            'DB::',
            '::query()',
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            'ConsumerCheckpoint',
            'consumer_checkpoint',
            'consumer_cursor',
            'sync_cursor',
            'DatasetPublication',
            'DatasetRegistry',
            'publication_registry',
            'FactSales',
            'DimCustomer',
            'DimProduct',
            'star_schema',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain(
                    $forbidden
                );
        }
    }
);
