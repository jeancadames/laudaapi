<?php

function p17BulkRelationshipSource(
    string $path
): string {
    return file_get_contents(
        dirname(__DIR__, 3).'/'.$path
    );
}

function p17BulkRelationshipMethod(
    string $startNeedle,
    string $endNeedle
): string {
    $source =
        p17BulkRelationshipSource(
            'app/Services/Diagnosis/'
            .'DataTransformationBiCanonicalRelationshipNavigator.php'
        );

    $start =
        strpos(
            $source,
            $startNeedle
        );

    expect($start)
        ->not
        ->toBeFalse();

    $end =
        strpos(
            $source,
            $endNeedle,
            $start
        );

    expect($end)
        ->not
        ->toBeFalse();

    return substr(
        $source,
        $start,
        $end - $start
    );
}

test(
    'p14 exposes pinned domain iteration without resolving p13',
    function () {
        $source =
            p17BulkRelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiPreparedDatasetReader.php'
            );

        $start =
            strpos(
                $source,
                'public function iterateDomainInDataset('
            );

        $end =
            strpos(
                $source,
                'public function '
                .'findDomainRowsByCanonicalIdentityHashesInDataset(',
                $start
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

        expect($method)
            ->toContain(
                '$this->datasetIdentity('
            )
            ->toContain(
                '$this->readResolvedDomainPage('
            )
            ->toContain(
                "'pinned_usable_dataset'"
            )
            ->not
            ->toContain(
                '->forRequest('
            );
    }
);

test(
    'bulk relation resolution uses p17 a lookup instead of single row lookup',
    function () {
        $source =
            p17BulkRelationshipMethod(
                'public function resolveBulkTargetsInDataset(',
                'public function iterateResolvedRelationship('
            );

        expect($source)
            ->toContain(
                'findDomainRowsByCanonicalIdentityHashesInDataset('
            )
            ->not
            ->toContain(
                'findDomainRowByCanonicalIdentityHashInDataset('
            );
    }
);

test(
    'bulk relation resolution is bounded to five hundred source rows',
    function () {
        $source =
            p17BulkRelationshipMethod(
                'public function resolveBulkTargetsInDataset(',
                'public function iterateResolvedRelationship('
            );

        expect($source)
            ->toContain(
                'count($sourceRows)'
            )
            ->toContain(
                '> 500'
            );
    }
);

test(
    'bulk navigation hashes target identities using target domain semantics',
    function () {
        $source =
            p17BulkRelationshipMethod(
                'public function resolveBulkTargetsInDataset(',
                'public function iterateResolvedRelationship('
            );

        expect($source)
            ->toContain(
                'DataTransformationBiCanonicalIdentity::class'
            )
            ->toContain(
                'hashForIdentityValues('
            )
            ->toContain(
                '$toDomain'
            )
            ->toContain(
                '$toField'
            );
    }
);

test(
    'blank relationships remain allowed without false target',
    function () {
        $source =
            p17BulkRelationshipMethod(
                'public function resolveBulkTargetsInDataset(',
                'public function iterateResolvedRelationship('
            );

        expect($source)
            ->toContain(
                "'source_relation_value_missing'"
            )
            ->toContain(
                "'target_found'"
            )
            ->toContain(
                "'target'"
            )
            ->toContain(
                'if ($targetHash === null)'
            );
    }
);

test(
    'nonblank missing targets fail closed',
    function () {
        $source =
            p17BulkRelationshipMethod(
                'public function resolveBulkTargetsInDataset(',
                'public function iterateResolvedRelationship('
            );

        expect($source)
            ->toContain(
                'if (! is_array($target))'
            )
            ->toContain(
                'Una relación canónica no pudo resolverse'
            );
    }
);

test(
    'bulk relationship source rows must have unique normalized row ids',
    function () {
        $source =
            p17BulkRelationshipMethod(
                'public function resolveBulkTargetsInDataset(',
                'public function iterateResolvedRelationship('
            );

        expect($source)
            ->toContain(
                "'normalized_row_id'"
            )
            ->toContain(
                'array_key_exists('
            )
            ->toContain(
                'fila fuente duplicada'
            );
    }
);

test(
    'relationship iterator resolves p13 exactly once',
    function () {
        $source =
            p17BulkRelationshipMethod(
                'public function iterateResolvedRelationship(',
                'private function yieldResolvedChunk('
            );

        expect(
            substr_count(
                $source,
                '->forRequest('
            )
        )
            ->toBe(1);

        expect($source)
            ->toContain(
                'DataTransformationBiUsableDatasetResolver::class'
            )
            ->toContain(
                'iterateDomainInDataset('
            )
            ->toContain(
                '$dataset'
            );
    }
);

test(
    'relationship iterator resolves one bulk target set per source chunk',
    function () {
        $source =
            p17BulkRelationshipMethod(
                'private function yieldResolvedChunk(',
                'private function resolveAgainstDataset('
            );

        expect(
            substr_count(
                $source,
                'resolveBulkTargetsInDataset('
            )
        )
            ->toBe(1);

        expect($source)
            ->not
            ->toContain(
                'findDomainRowByCanonicalIdentityHashInDataset('
            );
    }
);

test(
    'p17 b performs no writes and does not start analytical model',
    function () {
        $source =
            p17BulkRelationshipSource(
                'app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalRelationshipNavigator.php'
            );

        $start =
            strpos(
                $source,
                'public function resolveBulkTargetsInDataset('
            );

        $end =
            strpos(
                $source,
                'private function resolveAgainstDataset(',
                $start
            );

        $p17 =
            substr(
                $source,
                $start,
                $end - $start
            );

        foreach ([
            '->create(',
            '->update(',
            '->delete(',
            '->save(',
            '->insert(',
            '->upsert(',
            'forceFill(',
            'FactSales',
            'DimCustomer',
            'DimProduct',
            'star_schema',
        ] as $forbidden) {
            expect($p17)
                ->not
                ->toContain($forbidden);
        }
    }
);

test(
    'p17 b remains internal only',
    function () {
        $needles = [
            'resolveBulkTargetsInDataset',
            'iterateResolvedRelationship',
            'iterateDomainInDataset',
        ];

        $paths = [
            'app/Http/Controllers',
            'resources/js',
            'routes',
        ];

        foreach ($paths as $relative) {
            $root =
                dirname(__DIR__, 3).'/'.$relative;

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

                $source =
                    file_get_contents(
                        $file->getPathname()
                    );

                foreach ($needles as $needle) {
                    expect($source)
                        ->not
                        ->toContain($needle);
                }
            }
        }
    }
);
