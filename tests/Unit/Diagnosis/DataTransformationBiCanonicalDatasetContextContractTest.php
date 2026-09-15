<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetContext;

function p19ValidDatasetDescriptor(
    int $rows = 12
): array {
    return [
        'processing_run_id' =>
            101,

        'intake_batch_id' =>
            51,

        'definition_version' =>
            3,

        'schema_version' =>
            1,

        'profiling_version' =>
            1,

        'normalization_version' =>
            1,

        'normalized_row_count' =>
            $rows,

        'has_rows' =>
            $rows > 0,

        'completed_at' =>
            '2026-09-15T18:00:00+00:00',
    ];
}

test(
    'p19 canonical context pins company request run and batch',
    function () {
        $context =
            DataTransformationBiCanonicalDatasetContext
                ::fromResolved(
                    19,
                    1,
                    p19ValidDatasetDescriptor()
                );

        expect(
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $context
                )
        )->toBe([
            'company_id' => 19,
            'implementation_request_id' => 1,
            'processing_run_id' => 101,
            'intake_batch_id' => 51,
        ]);
    }
);

test(
    'p19 canonical descriptor exposes exactly the shared p13 p18 shape',
    function () {
        $input =
            p19ValidDatasetDescriptor();

        $input[
            'private_source_path'
        ] = '/private/path';

        $input[
            'normalized_payload'
        ] = [
            'secret' => true,
        ];

        $descriptor =
            DataTransformationBiCanonicalDatasetContext
                ::descriptor(
                    $input
                );

        expect(
            array_keys(
                $descriptor
            )
        )->toBe([
            'processing_run_id',
            'intake_batch_id',
            'definition_version',
            'schema_version',
            'profiling_version',
            'normalization_version',
            'normalized_row_count',
            'has_rows',
            'completed_at',
        ]);

        expect($descriptor)
            ->not
            ->toHaveKey(
                'private_source_path'
            )
            ->not
            ->toHaveKey(
                'normalized_payload'
            );
    }
);

test(
    'p19 canonical domain order comes from standard intake schema',
    function () {
        expect(
            DataTransformationBiCanonicalDatasetContext
                ::domains()
        )->toBe([
            'customers',
            'products',
            'inventory',
            'sales',
            'accounts_receivable',
            'suppliers',
            'accounts_payable',
        ]);
    }
);

test(
    'p19 canonical context supports zero row datasets consistently',
    function () {
        $context =
            DataTransformationBiCanonicalDatasetContext
                ::fromResolved(
                    19,
                    1,
                    p19ValidDatasetDescriptor(
                        0
                    )
                );

        expect(
            $context[
                'dataset'
            ][
                'normalized_row_count'
            ]
        )->toBe(0);

        expect(
            $context[
                'dataset'
            ][
                'has_rows'
            ]
        )->toBeFalse();
    }
);

test(
    'p19 canonical context rejects inconsistent has rows flag',
    function () {
        $descriptor =
            p19ValidDatasetDescriptor(
                12
            );

        $descriptor[
            'has_rows'
        ] = false;

        expect(
            fn () =>
                DataTransformationBiCanonicalDatasetContext
                    ::descriptor(
                        $descriptor
                    )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p19 canonical context rejects missing descriptor fields',
    function () {
        $descriptor =
            p19ValidDatasetDescriptor();

        unset(
            $descriptor[
                'normalization_version'
            ]
        );

        expect(
            fn () =>
                DataTransformationBiCanonicalDatasetContext
                    ::descriptor(
                        $descriptor
                    )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p19 canonical context rejects invalid technical identity',
    function () {
        expect(
            fn () =>
                DataTransformationBiCanonicalDatasetContext
                    ::fromResolved(
                        0,
                        1,
                        p19ValidDatasetDescriptor()
                    )
        )->toThrow(
            RuntimeException::class
        );

        $descriptor =
            p19ValidDatasetDescriptor();

        $descriptor[
            'processing_run_id'
        ] = 0;

        expect(
            fn () =>
                DataTransformationBiCanonicalDatasetContext
                    ::fromResolved(
                        19,
                        1,
                        $descriptor
                    )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p19 canonical context preserves nullable definition version',
    function () {
        $descriptor =
            p19ValidDatasetDescriptor();

        $descriptor[
            'definition_version'
        ] = null;

        expect(
            DataTransformationBiCanonicalDatasetContext
                ::descriptor(
                    $descriptor
                )[
                    'definition_version'
                ]
        )->toBeNull();
    }
);

test(
    'p19 canonical context is pure and read only',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetContext.php'
            );

        foreach ([
            'DB::',
            '::query()',
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            'forceFill(',
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
    'p19 a remains internal and starts no analytical model',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetContext.php'
            );

        foreach ([
            'normalized_payload',
            'source_row_sha256',
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

        $needle =
            'DataTransformationBiCanonicalDatasetContext';

        foreach ([
            'app/Http/Controllers',
            'resources/js',
            'routes',
        ] as $relative) {
            $root =
                dirname(__DIR__, 3)
                .'/'.$relative;

            $iterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $root,
                        FilesystemIterator::SKIP_DOTS
                    )
                );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                expect(
                    file_get_contents(
                        $file->getPathname()
                    )
                )
                    ->not
                    ->toContain(
                        $needle
                    );
            }
        }
    }
);
