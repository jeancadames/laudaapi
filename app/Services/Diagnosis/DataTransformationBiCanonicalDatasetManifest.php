<?php

namespace App\Services\Diagnosis;

use RuntimeException;

final class DataTransformationBiCanonicalDatasetManifest
{
    private const FINGERPRINT_VERSION = 1;

    private const FINGERPRINT_ALGORITHM =
        'sha256';

    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 5000;

    public function __construct(
        private readonly DataTransformationBiPreparedDatasetReader $preparedDatasetReader,
        private readonly DataTransformationBiCanonicalRowSignatureReader $signatureReader
    ) {
    }

    /**
     * Build a deterministic manifest for one already-resolved and pinned
     * canonical prepared dataset.
     *
     * No current/latest dataset resolution occurs here.
     *
     * @param array<string,mixed> $context
     *
     * @return array{
     *     fingerprint_version:int,
     *     fingerprint_algorithm:string,
     *     company_id:int,
     *     implementation_request_id:int,
     *     dataset:array<string,mixed>,
     *     total_rows:int,
     *     domains:list<array{
     *         domain:string,
     *         row_count:int,
     *         fingerprint:string
     *     }>,
     *     dataset_fingerprint:string
     * }
     */
    public function build(
        array $context,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        $pageSize =
            $this->pageSize(
                $pageSize
            );

        $identity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $context
                );

        $dataset =
            DataTransformationBiCanonicalDatasetContext
                ::dataset(
                    $context
                );

        $domains =
            DataTransformationBiCanonicalDatasetContext
                ::domains();

        $pinnedCounts =
            $this->preparedDatasetReader
                ->domainCountsInDataset(
                    $identity['company_id'],
                    $dataset
                );

        $counts =
            $pinnedCounts[
                'domains'
            ]
            ?? null;

        if (! is_array($counts)) {
            throw new RuntimeException(
                'Los conteos canónicos por dominio no son válidos.'
            );
        }

        $domainManifests = [];

        foreach ($domains as $domain) {
            if (! array_key_exists(
                $domain,
                $counts
            )) {
                throw new RuntimeException(
                    "Falta el conteo del dominio canónico {$domain}."
                );
            }

            $expectedCount =
                $this->nonNegativeInt(
                    $counts[$domain],
                    "domains.{$domain}"
                );

            $stream =
                $this->signatureReader
                    ->iterateDomainSignaturesInDataset(
                        $identity['company_id'],
                        $dataset,
                        $domain,
                        $pageSize
                    );

            $domainManifest =
                self::fingerprintDomain(
                    $domain,
                    $stream
                );

            if (
                $domainManifest[
                    'row_count'
                ]
                !== $expectedCount
            ) {
                throw new RuntimeException(
                    "El número de firmas del dominio {$domain} "
                    .'no coincide con su conteo canónico.'
                );
            }

            $domainManifests[] =
                $domainManifest;
        }

        $totalRows =
            array_sum(
                array_column(
                    $domainManifests,
                    'row_count'
                )
            );

        $expectedTotal =
            $this->nonNegativeInt(
                $pinnedCounts[
                    'total_rows'
                ]
                ?? null,
                'total_rows'
            );

        if ($totalRows !== $expectedTotal) {
            throw new RuntimeException(
                'El manifest canónico no coincide '
                .'con el total fijado del dataset.'
            );
        }

        if (
            $totalRows
            !== (int) $dataset[
                'normalized_row_count'
            ]
        ) {
            throw new RuntimeException(
                'El manifest canónico no coincide '
                .'con normalized_row_count.'
            );
        }

        return [
            'fingerprint_version' =>
                self::FINGERPRINT_VERSION,

            'fingerprint_algorithm' =>
                self::FINGERPRINT_ALGORITHM,

            'company_id' =>
                $identity[
                    'company_id'
                ],

            'implementation_request_id' =>
                $identity[
                    'implementation_request_id'
                ],

            'dataset' =>
                $dataset,

            'total_rows' =>
                $totalRows,

            'domains' =>
                $domainManifests,

            'dataset_fingerprint' =>
                self::fingerprintDataset(
                    $dataset,
                    $domainManifests
                ),
        ];
    }

    /**
     * Fingerprint one canonical domain using only logical row identity and
     * canonical normalized-content signature.
     *
     * normalized_row_id is intentionally excluded because it is a storage
     * identity rather than canonical dataset content.
     *
     * @param iterable<array<string,mixed>> $signatures
     *
     * @return array{
     *     domain:string,
     *     row_count:int,
     *     fingerprint:string
     * }
     */
    public static function fingerprintDomain(
        string $domain,
        iterable $signatures
    ): array {
        $domain =
            trim(
                $domain
            );

        if (
            ! in_array(
                $domain,
                DataTransformationBiCanonicalDatasetContext
                    ::domains(),
                true
            )
        ) {
            throw new RuntimeException(
                "Dominio canónico no soportado: {$domain}."
            );
        }

        $hash =
            hash_init(
                self::FINGERPRINT_ALGORITHM
            );

        hash_update(
            $hash,
            "lauda:data-bi:domain-fingerprint:v"
            .self::FINGERPRINT_VERSION
            ."\n"
        );

        hash_update(
            $hash,
            $domain."\n"
        );

        $count = 0;
        $previousIdentity = null;

        foreach ($signatures as $signature) {
            if (! is_array($signature)) {
                throw new RuntimeException(
                    'La firma canónica del dominio no es válida.'
                );
            }

            $canonicalIdentityHash =
                self::sha256(
                    $signature[
                        'canonical_identity_hash'
                    ]
                    ?? null,
                    'canonical_identity_hash'
                );

            $normalizedSha256 =
                self::sha256(
                    $signature[
                        'normalized_sha256'
                    ]
                    ?? null,
                    'normalized_sha256'
                );

            if (
                $previousIdentity !== null
                && strcmp(
                    $canonicalIdentityHash,
                    $previousIdentity
                )
                <= 0
            ) {
                throw new RuntimeException(
                    'Las firmas del dominio no están '
                    .'en orden canónico estricto.'
                );
            }

            hash_update(
                $hash,
                $canonicalIdentityHash
                .':'
                .$normalizedSha256
                ."\n"
            );

            $previousIdentity =
                $canonicalIdentityHash;

            $count++;
        }

        return [
            'domain' =>
                $domain,

            'row_count' =>
                $count,

            'fingerprint' =>
                hash_final(
                    $hash
                ),
        ];
    }

    /**
     * Produce the complete canonical-content fingerprint.
     *
     * Run id, batch id, company id, request id and timestamps are excluded
     * deliberately: they identify an execution/context, not normalized
     * canonical content.
     *
     * @param array<string,mixed> $dataset
     * @param list<array<string,mixed>> $domains
     */
    public static function fingerprintDataset(
        array $dataset,
        array $domains
    ): string {
        $dataset =
            DataTransformationBiCanonicalDatasetContext
                ::descriptor(
                    $dataset
                );

        $expectedDomains =
            DataTransformationBiCanonicalDatasetContext
                ::domains();

        if (
            count($domains)
            !== count(
                $expectedDomains
            )
        ) {
            throw new RuntimeException(
                'El manifest no contiene todos '
                .'los dominios canónicos.'
            );
        }

        $canonicalDomains = [];
        $totalRows = 0;

        foreach (
            $expectedDomains
            as $index => $domain
        ) {
            $entry =
                $domains[$index]
                ?? null;

            if (! is_array($entry)) {
                throw new RuntimeException(
                    "El manifest del dominio {$domain} no es válido."
                );
            }

            if (
                ($entry['domain'] ?? null)
                !== $domain
            ) {
                throw new RuntimeException(
                    'El orden de dominios del manifest '
                    .'no coincide con el contrato canónico.'
                );
            }

            $rowCount =
                self::staticNonNegativeInt(
                    $entry[
                        'row_count'
                    ]
                    ?? null,
                    "domains.{$domain}.row_count"
                );

            $fingerprint =
                self::sha256(
                    $entry[
                        'fingerprint'
                    ]
                    ?? null,
                    "domains.{$domain}.fingerprint"
                );

            $canonicalDomains[] = [
                'domain' =>
                    $domain,

                'row_count' =>
                    $rowCount,

                'fingerprint' =>
                    $fingerprint,
            ];

            $totalRows +=
                $rowCount;
        }

        if (
            $totalRows
            !== (int) $dataset[
                'normalized_row_count'
            ]
        ) {
            throw new RuntimeException(
                'Los dominios del manifest no coinciden '
                .'con normalized_row_count.'
            );
        }

        $material = [
            'fingerprint_version' =>
                self::FINGERPRINT_VERSION,

            'schema_version' =>
                $dataset[
                    'schema_version'
                ],

            'profiling_version' =>
                $dataset[
                    'profiling_version'
                ],

            'normalization_version' =>
                $dataset[
                    'normalization_version'
                ],

            'definition_version' =>
                $dataset[
                    'definition_version'
                ],

            'normalized_row_count' =>
                $dataset[
                    'normalized_row_count'
                ],

            'domains' =>
                $canonicalDomains,
        ];

        $json =
            json_encode(
                $material,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );

        return hash(
            self::FINGERPRINT_ALGORITHM,
            $json
        );
    }

    private function pageSize(
        int $pageSize
    ): int {
        if (
            $pageSize <= 0
            || $pageSize > self::MAX_PAGE_SIZE
        ) {
            throw new RuntimeException(
                'El tamaño de página del manifest '
                .'debe estar entre 1 y 5000.'
            );
        }

        return $pageSize;
    }

    private function nonNegativeInt(
        mixed $value,
        string $field
    ): int {
        return self::staticNonNegativeInt(
            $value,
            $field
        );
    }

    private static function staticNonNegativeInt(
        mixed $value,
        string $field
    ): int {
        if (
            is_int($value)
            && $value >= 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
        ) {
            return (int) $value;
        }

        throw new RuntimeException(
            "El manifest requiere {$field} no negativo."
        );
    }

    private static function sha256(
        mixed $value,
        string $field
    ): string {
        if (! is_string($value)) {
            throw new RuntimeException(
                "El manifest requiere {$field} SHA-256 válido."
            );
        }

        $value =
            strtolower(
                trim(
                    $value
                )
            );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $value
            )
            !== 1
        ) {
            throw new RuntimeException(
                "El manifest requiere {$field} SHA-256 válido."
            );
        }

        return $value;
    }
}
