<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiCanonicalIdentity
{
    public function __construct(
        private readonly DataTransformationBiCanonicalNormalizer
            $normalizer
    ) {
    }

    /**
     * Build the semantic identity of one NORMALIZED canonical row.
     *
     * This intentionally differs from staging identity_hash:
     * staging identity preserves intake semantics, while this identity
     * represents the normalized dataset used by downstream consumers.
     *
     * @param array<string,mixed> $normalizedPayload
     */
    public function hashForNormalizedPayload(
        string $domain,
        array $normalizedPayload
    ): string {
        $domain =
            trim($domain);

        $identityKeys =
            DataTransformationBiStandardIntakeSchema
                ::identityKeys()[$domain]
            ?? null;

        if (
            ! is_array($identityKeys)
            || $identityKeys === []
        ) {
            throw new InvalidArgumentException(
                "Dominio sin identidad canónica soportada: {$domain}."
            );
        }

        $identity = [];

        foreach ($identityKeys as $field) {
            if (! array_key_exists(
                $field,
                $normalizedPayload
            )) {
                throw new RuntimeException(
                    "La identidad canónica de {$domain} "
                    ."requiere el campo {$field}."
                );
            }

            $identity[$field] =
                $normalizedPayload[$field];
        }

        return hash(
            'sha256',
            $this->normalizer->canonicalJson([
                'domain' =>
                    $domain,

                'identity' =>
                    $identity,
            ])
        );
    }

    /**
     * Build a target identity from canonical identity-field values.
     *
     * Used later by relationship navigation after the source relation
     * value has already passed through canonical normalization.
     *
     * @param array<string,mixed> $identityValues
     */
    public function hashForIdentityValues(
        string $domain,
        array $identityValues
    ): string {
        $identityKeys =
            DataTransformationBiStandardIntakeSchema
                ::identityKeys()[$domain]
            ?? null;

        if (
            ! is_array($identityKeys)
            || $identityKeys === []
        ) {
            throw new InvalidArgumentException(
                "Dominio sin identidad canónica soportada: {$domain}."
            );
        }

        $payload = [];

        foreach ($identityKeys as $field) {
            if (! array_key_exists(
                $field,
                $identityValues
            )) {
                throw new RuntimeException(
                    "Falta {$field} para resolver "
                    ."la identidad canónica de {$domain}."
                );
            }

            $payload[$field] =
                $identityValues[$field];
        }

        /*
         * Relationship values may come from another canonical domain.
         * Normalize them again according to the TARGET domain before
         * hashing so target identity semantics remain authoritative.
         */
        $normalized =
            $this->normalizer->normalize(
                $domain,
                $payload
            );

        $normalizedPayload =
            $normalized['payload']
            ?? null;

        if (! is_array($normalizedPayload)) {
            throw new RuntimeException(
                "No se pudo normalizar la identidad canónica "
                ."del dominio {$domain}."
            );
        }

        return $this->hashForNormalizedPayload(
            $domain,
            $normalizedPayload
        );
    }
}
