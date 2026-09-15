<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiCanonicalRelationshipNavigator
{
    public function __construct(
        private readonly DataTransformationBiUsableDatasetResolver
            $usableDatasetResolver,
        private readonly DataTransformationBiPreparedDatasetReader
            $preparedDatasetReader,
        private readonly DataTransformationBiCanonicalIdentity
            $canonicalIdentity
    ) {
    }

    /**
     * @return array<int,array{
     *     from_domain:string,
     *     from_field:string,
     *     to_domain:string,
     *     to_field:string
     * }>
     */
    public function relationshipsFrom(
        string $fromDomain
    ): array {
        $fromDomain =
            $this->canonicalDomain(
                $fromDomain
            );

        return array_values(
            array_filter(
                DataTransformationBiStandardIntakeSchema
                    ::relationships(),
                static fn (array $relationship): bool =>
                    (
                        $relationship['from_domain']
                        ?? null
                    ) === $fromDomain
            )
        );
    }

    /**
     * Resolve one specific outbound canonical relationship.
     *
     * The P13 dataset pointer is resolved exactly once and the target
     * lookup remains pinned to that same run and batch.
     *
     * @param array<string,mixed> $sourcePayload
     * @return array<string,mixed>
     */
    public function resolveTarget(
        int $companyId,
        int $implementationRequestId,
        string $fromDomain,
        array $sourcePayload,
        string $toDomain
    ): array {
        $relationship =
            $this->relationship(
                $fromDomain,
                $toDomain
            );

        $resolved =
            $this->usableDatasetResolver->forRequest(
                $companyId,
                $implementationRequestId
            );

        if (! $this->hasUsableDataset($resolved)) {
            return [
                'available' =>
                    false,

                'reason' =>
                    (string) (
                        $resolved['reason']
                        ?? 'no_successful_normalized_dataset'
                    ),

                'dataset' =>
                    null,

                'relationship' =>
                    $relationship,

                'relation_value_present' =>
                    false,

                'target_found' =>
                    false,

                'target' =>
                    null,
            ];
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        return $this->resolveAgainstDataset(
            $companyId,
            $dataset,
            $sourcePayload,
            $relationship,
            (string) (
                $resolved['reason']
                ?? 'latest_successful_normalized_run'
            )
        );
    }

    /**
     * Resolve every outbound relationship from one canonical source row.
     *
     * P13 is resolved once for the complete navigation operation.
     *
     * @param array<string,mixed> $sourcePayload
     * @return array{
     *     available:bool,
     *     reason:string,
     *     dataset:array<string,mixed>|null,
     *     from_domain:string,
     *     relationships:array<int,array<string,mixed>>
     * }
     */
    public function resolveTargets(
        int $companyId,
        int $implementationRequestId,
        string $fromDomain,
        array $sourcePayload
    ): array {
        $fromDomain =
            $this->canonicalDomain(
                $fromDomain
            );

        $relationships =
            $this->relationshipsFrom(
                $fromDomain
            );

        $resolved =
            $this->usableDatasetResolver->forRequest(
                $companyId,
                $implementationRequestId
            );

        if (! $this->hasUsableDataset($resolved)) {
            return [
                'available' =>
                    false,

                'reason' =>
                    (string) (
                        $resolved['reason']
                        ?? 'no_successful_normalized_dataset'
                    ),

                'dataset' =>
                    null,

                'from_domain' =>
                    $fromDomain,

                'relationships' =>
                    [],
            ];
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        $reason =
            (string) (
                $resolved['reason']
                ?? 'latest_successful_normalized_run'
            );

        $resolvedRelationships = [];

        foreach ($relationships as $relationship) {
            $resolvedRelationships[] =
                $this->resolveAgainstDataset(
                    $companyId,
                    $dataset,
                    $sourcePayload,
                    $relationship,
                    $reason
                );
        }

        return [
            'available' =>
                true,

            'reason' =>
                $reason,

            'dataset' =>
                $dataset,

            'from_domain' =>
                $fromDomain,

            'relationships' =>
                $resolvedRelationships,
        ];
    }

    /**
     * @param array<string,mixed> $dataset
     * @param array<string,mixed> $sourcePayload
     * @param array<string,mixed> $relationship
     * @return array<string,mixed>
     */
    private function resolveAgainstDataset(
        int $companyId,
        array $dataset,
        array $sourcePayload,
        array $relationship,
        string $reason
    ): array {
        $fromDomain =
            (string) $relationship['from_domain'];

        $fromField =
            (string) $relationship['from_field'];

        $toDomain =
            (string) $relationship['to_domain'];

        $toField =
            (string) $relationship['to_field'];

        $this->assertTargetIdentityContract(
            $toDomain,
            $toField
        );

        $sourceValue =
            $sourcePayload[$fromField]
            ?? null;

        if ($this->blank($sourceValue)) {
            return [
                'available' =>
                    true,

                'reason' =>
                    'source_relation_value_missing',

                'dataset' =>
                    $dataset,

                'relationship' =>
                    $relationship,

                'relation_value_present' =>
                    false,

                'target_found' =>
                    false,

                'target' =>
                    null,
            ];
        }

        $targetIdentityHash =
            $this->canonicalIdentity
                ->hashForIdentityValues(
                    $toDomain,
                    [
                        $toField =>
                            $sourceValue,
                    ]
                );

        $target =
            $this->preparedDatasetReader
                ->findDomainRowByCanonicalIdentityHashInDataset(
                    $companyId,
                    $dataset,
                    $toDomain,
                    $targetIdentityHash
                );

        if ($target === null) {
            throw new RuntimeException(
                "{$fromDomain}.{$fromField} no pudo resolverse "
                ."contra {$toDomain}.{$toField} dentro del "
                .'dataset canónico utilizable.'
            );
        }

        return [
            'available' =>
                true,

            'reason' =>
                $reason,

            'dataset' =>
                $dataset,

            'relationship' =>
                $relationship,

            'relation_value_present' =>
                true,

            'target_found' =>
                true,

            'target' =>
                $target,
        ];
    }

    /**
     * @return array{
     *     from_domain:string,
     *     from_field:string,
     *     to_domain:string,
     *     to_field:string
     * }
     */
    private function relationship(
        string $fromDomain,
        string $toDomain
    ): array {
        $fromDomain =
            $this->canonicalDomain(
                $fromDomain
            );

        $toDomain =
            $this->canonicalDomain(
                $toDomain
            );

        $matches =
            array_values(
                array_filter(
                    DataTransformationBiStandardIntakeSchema
                        ::relationships(),
                    static fn (array $relationship): bool =>
                        (
                            $relationship['from_domain']
                            ?? null
                        ) === $fromDomain
                        && (
                            $relationship['to_domain']
                            ?? null
                        ) === $toDomain
                )
            );

        if ($matches === []) {
            throw new InvalidArgumentException(
                "No existe una relación canónica "
                ."{$fromDomain} -> {$toDomain}."
            );
        }

        if (count($matches) !== 1) {
            throw new RuntimeException(
                "La relación canónica {$fromDomain} -> {$toDomain} "
                .'es ambigua.'
            );
        }

        return $matches[0];
    }

    private function assertTargetIdentityContract(
        string $toDomain,
        string $toField
    ): void {
        $identity =
            DataTransformationBiStandardIntakeSchema
                ::identityKeys()[$toDomain]
            ?? [];

        if (
            $identity
            !== [
                $toField,
            ]
        ) {
            throw new RuntimeException(
                "La relación hacia {$toDomain}.{$toField} "
                .'no apunta a una identidad canónica simple soportada.'
            );
        }
    }

    private function canonicalDomain(
        string $domain
    ): string {
        $domain =
            trim($domain);

        if (
            ! array_key_exists(
                $domain,
                DataTransformationBiStandardIntakeSchema::domains()
            )
        ) {
            throw new InvalidArgumentException(
                "Dominio canónico no soportado: {$domain}."
            );
        }

        return $domain;
    }

    private function blank(
        mixed $value
    ): bool {
        return $value === null
            || (
                is_string($value)
                && trim($value) === ''
            );
    }

    /**
     * @param array<string,mixed> $resolved
     */
    private function hasUsableDataset(
        array $resolved
    ): bool {
        return (
            ($resolved['available'] ?? false)
            === true
            && is_array(
                $resolved['dataset']
                ?? null
            )
        );
    }
}
