<?php

use Tests\TestCase;

uses(TestCase::class);

test(
    'p19 c current access resolves p13 exactly once',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiCanonicalDatasetManifestResolver.php'
                )
            );

        $start =
            strpos(
                $source,
                'public function current('
            );

        $end =
            strpos(
                $source,
                'public function forProcessingRun('
            );

        expect($start)
            ->not
            ->toBeFalse();

        expect($end)
            ->not
            ->toBeFalse();

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect(
            substr_count(
                $method,
                '->forRequest('
            )
        )->toBe(1);

        expect($method)
            ->toContain(
                'DataTransformationBiCanonicalDatasetContext'
            )
            ->toContain(
                '->build('
            )
            ->not
            ->toContain(
                '->forProcessingRun('
            )
            ->not
            ->toContain(
                '->pair('
            );
    }
);

test(
    'p19 c historical access resolves explicit p18 run exactly once',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiCanonicalDatasetManifestResolver.php'
                )
            );

        $start =
            strpos(
                $source,
                'public function forProcessingRun('
            );

        $end =
            strpos(
                $source,
                'public function pair('
            );

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect(
            substr_count(
                $method,
                '->forProcessingRun('
            )
        )->toBe(1);

        expect($method)
            ->toContain(
                'DataTransformationBiCanonicalDatasetContext'
            )
            ->toContain(
                '->build('
            )
            ->not
            ->toContain(
                '->forRequest('
            )
            ->not
            ->toContain(
                '->pair('
            );
    }
);

test(
    'p19 c pair freezes the single p18 pair resolution',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiCanonicalDatasetManifestResolver.php'
                )
            );

        $start =
            strpos(
                $source,
                'public function pair('
            );

        $end =
            strpos(
                $source,
                'private function availableDataset('
            );

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect(
            substr_count(
                $method,
                '->pair('
            )
        )->toBe(1);

        expect(
            substr_count(
                $method,
                '->build('
            )
        )->toBe(2);

        expect(
            substr_count(
                $method,
                '::fromResolved('
            )
        )->toBe(2);

        expect($method)
            ->not
            ->toContain(
                '->forProcessingRun('
            )
            ->not
            ->toContain(
                '->forRequest('
            );
    }
);

test(
    'p19 c pair compares canonical content fingerprints',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiCanonicalDatasetManifestResolver.php'
                )
            );

        expect($source)
            ->toContain(
                "'dataset_fingerprint'"
            )
            ->toContain(
                'hash_equals('
            )
            ->toContain(
                "'same_content'"
            );
    }
);

test(
    'p19 c unavailable current and historical access expose no partial context',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiCanonicalDatasetManifestResolver.php'
                )
            );

        expect($source)
            ->toContain(
                "'context' =>"
            )
            ->toContain(
                "'manifest' =>"
            )
            ->toContain(
                "'available' =>"
            )
            ->toContain(
                'private function unavailable('
            );
    }
);

test(
    'p19 c remains internal and read only',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiCanonicalDatasetManifestResolver.php'
                )
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
            'normalized_payload',
            'source_row_sha256',
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
    'p19 c does not start public or analytical surfaces',
    function () {
        $source =
            file_get_contents(
                app_path(
                    'Services/Diagnosis/'
                    .'DataTransformationBiCanonicalDatasetManifestResolver.php'
                )
            );

        foreach ([
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
            'DataTransformationBiCanonicalDatasetManifestResolver';

        foreach ([
            app_path(
                'Http/Controllers'
            ),
            resource_path(
                'js'
            ),
            base_path(
                'routes'
            ),
        ] as $root) {
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
