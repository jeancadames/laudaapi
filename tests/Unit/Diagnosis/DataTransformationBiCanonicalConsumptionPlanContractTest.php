<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalConsumptionPlan;
use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetContext;
use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetManifest;

function p24Source(): string
{
    return file_get_contents(
        dirname(__DIR__, 3)
        .'/app/Services/Diagnosis/'
        .'DataTransformationBiCanonicalConsumptionPlan.php'
    );
}

function p24Dataset(
    int $processingRunId
): array {
    return [
        'processing_run_id' =>
            $processingRunId,

        'intake_batch_id' =>
            $processingRunId + 100000,

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
            '2026-09-16 00:00:00',
    ];
}

function p24Side(
    int $processingRunId,
    int $companyId = 19,
    int $implementationRequestId = 1
): array {
    $dataset =
        p24Dataset(
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

test(
    'p24 exposes one stateless plan api',
    function () {
        $source =
            p24Source();

        expect($source)
            ->toContain(
                'public function plan('
            )
            ->not
            ->toContain(
                'public function execute('
            )
            ->not
            ->toContain(
                'public function iterate('
            );
    }
);

test(
    'p24 uses p19 current and pair as sole resolution authorities',
    function () {
        $source =
            p24Source();

        expect(
            substr_count(
                $source,
                '->current('
            )
        )->toBe(1);

        expect(
            substr_count(
                $source,
                '->pair('
            )
        )->toBe(1);

        foreach ([
            'DataTransformationBiUsableDatasetResolver',
            'DataTransformationBiVersionedDatasetResolver',
            '->forRequest(',
            '->forProcessingRun(',
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
    'p24 exact plan envelope contains seven fields',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionPlan::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionPlan::class,
                'availablePlan'
            );

        $target =
            p24Side(
                20
            );

        $plan =
            $method->invoke(
                $service,
                'snapshot',
                'baseline_not_provided',
                $target,
                null,
                null,
                null
            );

        expect(
            array_keys($plan)
        )->toBe([
            'available',
            'mode',
            'reason',
            'target',
            'base',
            'compatibility',
            'same_content',
        ]);
    }
);

test(
    'p24 snapshot plan has no incremental state',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionPlan::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionPlan::class,
                'availablePlan'
            );

        $target =
            p24Side(
                20
            );

        $plan =
            $method->invoke(
                $service,
                'snapshot',
                'baseline_not_provided',
                $target,
                null,
                null,
                null
            );

        expect($plan)
            ->toMatchArray([
                'available' =>
                    true,

                'mode' =>
                    'snapshot',

                'reason' =>
                    'baseline_not_provided',

                'target' =>
                    $target,

                'base' =>
                    null,

                'compatibility' =>
                    null,

                'same_content' =>
                    null,
            ]);
    }
);

test(
    'p24 incremental plan requires compatible unequal content pair',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionPlan::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionPlan::class,
                'availablePlan'
            );

        $base =
            p24Side(
                10
            );

        $target =
            p24Side(
                20
            );

        $compatibility = [
            'schema_version' =>
                1,

            'normalization_version' =>
                1,
        ];

        $plan =
            $method->invoke(
                $service,
                'incremental',
                'compatible_versioned_dataset_pair',
                $target,
                $base,
                $compatibility,
                false
            );

        expect($plan)
            ->toMatchArray([
                'available' =>
                    true,

                'mode' =>
                    'incremental',

                'target' =>
                    $target,

                'base' =>
                    $base,

                'compatibility' =>
                    $compatibility,

                'same_content' =>
                    false,
            ]);
    }
);

test(
    'p24 up to date plan transports baseline and same content true',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionPlan::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionPlan::class,
                'availablePlan'
            );

        $base =
            p24Side(
                10
            );

        $target =
            p24Side(
                20
            );

        $plan =
            $method->invoke(
                $service,
                'up_to_date',
                'baseline_content_matches_current_target',
                $target,
                $base,
                [
                    'schema_version' =>
                        1,

                    'normalization_version' =>
                        1,
                ],
                true
            );

        expect(
            $plan['mode']
        )->toBe(
            'up_to_date'
        );

        expect(
            $plan['same_content']
        )->toBeTrue();

        expect(
            $plan['base']
        )->toBe(
            $base
        );
    }
);

test(
    'p24 unavailable plan contains no target or baseline',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionPlan::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionPlan::class,
                'unavailablePlan'
            );

        $plan =
            $method->invoke(
                $service,
                'no_successful_normalized_dataset'
            );

        expect($plan)
            ->toBe([
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
            ]);
    }
);

test(
    'p24 validates canonical side through p19 context and manifest authorities',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionPlan::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionPlan::class,
                'canonicalSide'
            );

        $side =
            p24Side(
                20
            );

        $validated =
            $method->invoke(
                $service,
                $side,
                19,
                1,
                'target'
            );

        expect($validated)
            ->toBe(
                $side
            );
    }
);

test(
    'p24 rejects different current and pair target',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionPlan::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionPlan::class,
                'assertSameTarget'
            );

        expect(
            fn () =>
                $method->invoke(
                    $service,
                    p24Side(
                        20
                    ),
                    p24Side(
                        21
                    )
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p24 rejects invalid mode semantics',
    function () {
        $service =
            app(
                DataTransformationBiCanonicalConsumptionPlan::class
            );

        $method =
            new ReflectionMethod(
                DataTransformationBiCanonicalConsumptionPlan::class,
                'availablePlan'
            );

        expect(
            fn () =>
                $method->invoke(
                    $service,
                    'incremental',
                    'invalid_case',
                    p24Side(
                        20
                    ),
                    null,
                    null,
                    null
                )
        )->toThrow(
            \RuntimeException::class
        );
    }
);

test(
    'p24 is stateless read only and model neutral',
    function () {
        $source =
            p24Source();

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
