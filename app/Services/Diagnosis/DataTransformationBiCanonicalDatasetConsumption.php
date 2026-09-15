<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiCanonicalDatasetConsumption
{
    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 500;

    public function __construct(
        private readonly DataTransformationBiCanonicalDatasetManifestResolver $manifestResolver,
        private readonly DataTransformationBiProjectedDatasetReader $projectedDatasetReader
    ) {
    }

    /**
     * Resolve the current canonical snapshot exactly once through P19.
     *
     * The returned boundary deliberately contains only P19-owned context
     * and manifest metadata. The pinned dataset remains inside context.
     *
     * @return array{
     *     available:bool,
     *     reason:string,
     *     context:array<string,mixed>|null,
     *     manifest:array<string,mixed>|null
     * }
     */
    public function current(
        int $companyId,
        int $implementationRequestId,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        $pageSize =
            $this->pageSize(
                $pageSize
            );

        return $this->resolvedSnapshot(
            $this->manifestResolver
                ->current(
                    $companyId,
                    $implementationRequestId,
                    $pageSize
                )
        );
    }

    /**
     * Resolve one explicit usable historical snapshot exactly once through
     * P19 and preserve the same consumer-neutral boundary as current().
     *
     * @return array{
     *     available:bool,
     *     reason:string,
     *     context:array<string,mixed>|null,
     *     manifest:array<string,mixed>|null
     * }
     */
    public function forProcessingRun(
        int $companyId,
        int $implementationRequestId,
        int $processingRunId,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        $pageSize =
            $this->pageSize(
                $pageSize
            );

        return $this->resolvedSnapshot(
            $this->manifestResolver
                ->forProcessingRun(
                    $companyId,
                    $implementationRequestId,
                    $processingRunId,
                    $pageSize
                )
        );
    }

    /**
     * Stream all projected rows from one already-resolved P19 snapshot.
     *
     * No dataset resolution occurs here. Company, processing run and intake
     * batch are recovered from the immutable P19 context and remain pinned
     * for all seven canonical domains.
     *
     * @param array<string,mixed> $resolvedSnapshot
     *
     * @return \Generator<int,array{
     *     domain:string,
     *     identity:array<string,mixed>,
     *     fields:array<string,mixed>
     * }>
     */
    public function iterateResolvedSnapshot(
        array $resolvedSnapshot,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): \Generator {
        $pageSize =
            $this->pageSize(
                $pageSize
            );

        $snapshot =
            $this->resolvedSnapshot(
                $resolvedSnapshot
            );

        if (
            $snapshot['available']
            !== true
        ) {
            throw new RuntimeException(
                'No se puede consumir el snapshot canónico: '
                .$snapshot['reason'].'.'
            );
        }

        /** @var array<string,mixed> $context */
        $context =
            $snapshot['context'];

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

        foreach (
            DataTransformationBiCanonicalDatasetContext
                ::domains()
            as $domain
        ) {
            foreach (
                $this->projectedDatasetReader
                    ->iterateDomainInDataset(
                        $identity['company_id'],
                        $dataset,
                        $domain,
                        $pageSize
                    )
                as $row
            ) {
                if (! is_array($row)) {
                    throw new RuntimeException(
                        'El stream proyectado produjo '
                        .'una fila canónica inválida.'
                    );
                }

                if (
                    array_keys($row)
                    !== [
                        'domain',
                        'identity',
                        'fields',
                    ]
                ) {
                    throw new RuntimeException(
                        'La fila proyectada no respeta '
                        .'el contrato canónico de consumo.'
                    );
                }

                if (
                    ($row['domain'] ?? null)
                    !== $domain
                    || ! is_array(
                        $row['identity']
                        ?? null
                    )
                    || ! is_array(
                        $row['fields']
                        ?? null
                    )
                ) {
                    throw new RuntimeException(
                        'La fila proyectada no coincide '
                        .'con el dominio canónico fijado.'
                    );
                }

                yield $row;
            }
        }
    }

    /**
     * Normalize and validate the stable P22 snapshot envelope without
     * inventing a second metadata authority.
     *
     * P19 remains authoritative for context, manifest structure and
     * dataset fingerprint semantics.
     *
     * @param array<string,mixed> $resolved
     *
     * @return array{
     *     available:bool,
     *     reason:string,
     *     context:array<string,mixed>|null,
     *     manifest:array<string,mixed>|null
     * }
     */
    private function resolvedSnapshot(
        array $resolved
    ): array {
        $available =
            $resolved['available']
            ?? null;

        if (! is_bool($available)) {
            throw new RuntimeException(
                'El snapshot resuelto requiere available booleano.'
            );
        }

        $reason =
            $resolved['reason']
            ?? null;

        if (
            ! is_string($reason)
            || trim($reason) === ''
        ) {
            throw new RuntimeException(
                'El snapshot resuelto requiere reason no vacío.'
            );
        }

        $reason =
            trim($reason);

        if (! $available) {
            $context =
                $resolved['context']
                ?? null;

            $manifest =
                $resolved['manifest']
                ?? null;

            if (
                $context !== null
                || $manifest !== null
            ) {
                throw new RuntimeException(
                    'Un snapshot no disponible no debe '
                    .'contener context ni manifest.'
                );
            }

            return [
                'available' =>
                    false,

                'reason' =>
                    $reason,

                'context' =>
                    null,

                'manifest' =>
                    null,
            ];
        }

        $context =
            $resolved['context']
            ?? null;

        $manifest =
            $resolved['manifest']
            ?? null;

        if (
            ! is_array($context)
            || ! is_array($manifest)
        ) {
            throw new RuntimeException(
                'Un snapshot disponible requiere '
                .'context y manifest válidos.'
            );
        }

        $this->validateContextManifest(
            $context,
            $manifest
        );

        return [
            'available' =>
                true,

            'reason' =>
                $reason,

            'context' =>
                $context,

            'manifest' =>
                $manifest,
        ];
    }

    /**
     * Validate cross-consistency by reusing P19 authorities.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $manifest
     */
    private function validateContextManifest(
        array $context,
        array $manifest
    ): void {
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

        $contextDomains =
            $context['domains']
            ?? null;

        if (
            ! is_array($contextDomains)
            || $contextDomains
            !== DataTransformationBiCanonicalDatasetContext
                ::domains()
        ) {
            throw new RuntimeException(
                'El contexto no conserva el orden '
                .'canónico de dominios.'
            );
        }

        if (
            ($manifest['company_id'] ?? null)
            !== $identity['company_id']
            || (
                $manifest[
                    'implementation_request_id'
                ]
                ?? null
            )
            !== $identity[
                'implementation_request_id'
            ]
        ) {
            throw new RuntimeException(
                'El manifest no pertenece '
                .'al contexto canónico resuelto.'
            );
        }

        $manifestDataset =
            $manifest['dataset']
            ?? null;

        if (! is_array($manifestDataset)) {
            throw new RuntimeException(
                'El manifest no contiene '
                .'un descriptor de dataset válido.'
            );
        }

        $manifestDataset =
            DataTransformationBiCanonicalDatasetContext
                ::descriptor(
                    $manifestDataset
                );

        if ($manifestDataset !== $dataset) {
            throw new RuntimeException(
                'El manifest y el contexto no apuntan '
                .'al mismo dataset fijado.'
            );
        }

        $totalRows =
            $manifest['total_rows']
            ?? null;

        if (
            ! is_int($totalRows)
            || $totalRows < 0
            || $totalRows
                !== $dataset[
                    'normalized_row_count'
                ]
        ) {
            throw new RuntimeException(
                'El total del manifest no coincide '
                .'con el dataset fijado.'
            );
        }

        $domains =
            $manifest['domains']
            ?? null;

        if (! is_array($domains)) {
            throw new RuntimeException(
                'El manifest no contiene '
                .'dominios canónicos válidos.'
            );
        }

        $fingerprint =
            $manifest[
                'dataset_fingerprint'
            ]
            ?? null;

        if (
            ! is_string($fingerprint)
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $fingerprint
            )
            !== 1
        ) {
            throw new RuntimeException(
                'El manifest no contiene '
                .'dataset_fingerprint SHA-256 válido.'
            );
        }

        $expectedFingerprint =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDataset(
                    $dataset,
                    $domains
                );

        if (
            ! hash_equals(
                $expectedFingerprint,
                $fingerprint
            )
        ) {
            throw new RuntimeException(
                'El fingerprint del manifest '
                .'no coincide con su dataset.'
            );
        }
    }

    private function pageSize(
        int $pageSize
    ): int {
        if (
            $pageSize <= 0
            || $pageSize > self::MAX_PAGE_SIZE
        ) {
            throw new InvalidArgumentException(
                'El tamaño de página del consumo canónico '
                .'debe estar entre 1 y 500.'
            );
        }

        return $pageSize;
    }
}
