<?php

use App\Services\Diagnosis\DataTransformationBiCanonicalDatasetChangeSet;
use ReflectionMethod;
use RuntimeException;

function p20r1StableRecord(
    string $domain,
    array $delta
): array {
    $service =
        app(
            DataTransformationBiCanonicalDatasetChangeSet::class
        );

    $method =
        new ReflectionMethod(
            DataTransformationBiCanonicalDatasetChangeSet::class,
            'stableChangeRecord'
        );

    return $method->invoke(
        $service,
        $domain,
        $delta
    );
}

test(
    'p20 r1 projects added delta to exact stable contract',
    function () {
        $identity =
            str_repeat(
                'a',
                64
            );

        $targetSha =
            str_repeat(
                'b',
                64
            );

        $record =
            p20r1StableRecord(
                'customers',
                [
                    'canonical_identity_hash' =>
                        $identity,

                    'change_type' =>
                        'added',

                    'base_normalized_row_id' =>
                        null,

                    'target_normalized_row_id' =>
                        999,

                    'base_normalized_sha256' =>
                        null,

                    'target_normalized_sha256' =>
                        $targetSha,
                ]
            );

        expect(
            array_keys($record)
        )->toBe([
            'domain',
            'canonical_identity_hash',
            'change_type',
            'base_normalized_sha256',
            'target_normalized_sha256',
        ]);

        expect($record)
            ->toBe([
                'domain' =>
                    'customers',

                'canonical_identity_hash' =>
                    $identity,

                'change_type' =>
                    'added',

                'base_normalized_sha256' =>
                    null,

                'target_normalized_sha256' =>
                    $targetSha,
            ])
            ->not
            ->toHaveKey(
                'base_normalized_row_id'
            )
            ->not
            ->toHaveKey(
                'target_normalized_row_id'
            );
    }
);

test(
    'p20 r1 projects removed modified and unchanged stable semantics',
    function (
        string $type,
        ?string $base,
        ?string $target
    ) {
        $record =
            p20r1StableRecord(
                'sales',
                [
                    'canonical_identity_hash' =>
                        str_repeat(
                            'c',
                            64
                        ),

                    'change_type' =>
                        $type,

                    'base_normalized_row_id' =>
                        101,

                    'target_normalized_row_id' =>
                        202,

                    'base_normalized_sha256' =>
                        $base,

                    'target_normalized_sha256' =>
                        $target,
                ]
            );

        expect(
            array_keys($record)
        )->toBe([
            'domain',
            'canonical_identity_hash',
            'change_type',
            'base_normalized_sha256',
            'target_normalized_sha256',
        ]);

        expect(
            $record['change_type']
        )->toBe($type);

        expect(
            $record['base_normalized_sha256']
        )->toBe($base);

        expect(
            $record['target_normalized_sha256']
        )->toBe($target);
    }
)->with([
    'removed' => [
        'removed',
        str_repeat('d', 64),
        null,
    ],

    'modified' => [
        'modified',
        str_repeat('d', 64),
        str_repeat('e', 64),
    ],

    'unchanged' => [
        'unchanged',
        str_repeat('f', 64),
        str_repeat('f', 64),
    ],
]);

test(
    'p20 r1 fails closed on invalid stable change semantics',
    function (
        string $type,
        ?string $base,
        ?string $target
    ) {
        expect(
            fn () =>
                p20r1StableRecord(
                    'customers',
                    [
                        'canonical_identity_hash' =>
                            str_repeat(
                                'a',
                                64
                            ),

                        'change_type' =>
                            $type,

                        'base_normalized_sha256' =>
                            $base,

                        'target_normalized_sha256' =>
                            $target,
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );
    }
)->with([
    'added has base' => [
        'added',
        str_repeat('1', 64),
        str_repeat('2', 64),
    ],

    'removed has target' => [
        'removed',
        str_repeat('1', 64),
        str_repeat('2', 64),
    ],

    'modified fingerprints equal' => [
        'modified',
        str_repeat('3', 64),
        str_repeat('3', 64),
    ],

    'unchanged fingerprints differ' => [
        'unchanged',
        str_repeat('4', 64),
        str_repeat('5', 64),
    ],
]);

test(
    'p20 r1 rejects uppercase or malformed sha256 values',
    function () {
        expect(
            fn () =>
                p20r1StableRecord(
                    'customers',
                    [
                        'canonical_identity_hash' =>
                            str_repeat(
                                'A',
                                64
                            ),

                        'change_type' =>
                            'added',

                        'base_normalized_sha256' =>
                            null,

                        'target_normalized_sha256' =>
                            str_repeat(
                                'b',
                                64
                            ),
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );

        expect(
            fn () =>
                p20r1StableRecord(
                    'customers',
                    [
                        'canonical_identity_hash' =>
                            str_repeat(
                                'a',
                                64
                            ),

                        'change_type' =>
                            'added',

                        'base_normalized_sha256' =>
                            null,

                        'target_normalized_sha256' =>
                            'invalid',
                    ]
                )
        )->toThrow(
            RuntimeException::class
        );
    }
);

test(
    'p20 r1 source contains no physical row id contract',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalDatasetChangeSet.php'
            );

        expect($source)
            ->not
            ->toContain(
                'base_normalized_row_id'
            )
            ->not
            ->toContain(
                'target_normalized_row_id'
            );

        expect($source)
            ->toContain(
                'stableChangeRecord('
            )
            ->toContain(
                "'canonical_identity_hash'"
            )
            ->toContain(
                "'change_type'"
            )
            ->toContain(
                "'base_normalized_sha256'"
            )
            ->toContain(
                "'target_normalized_sha256'"
            );
    }
);
