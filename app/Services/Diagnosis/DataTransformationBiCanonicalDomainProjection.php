<?php

namespace App\Services\Diagnosis;

use RuntimeException;

final class DataTransformationBiCanonicalDomainProjection
{
    public function __construct(
        private readonly DataTransformationBiCanonicalNormalizer $normalizer
    ) {
    }

    /**
     * Project one already-normalized canonical payload into the stable
     * consumer-facing internal domain contract.
     *
     * Storage metadata deliberately does not cross this boundary.
     *
     * @param array<string,mixed> $payload
     *
     * @return array{
     *     domain:string,
     *     identity:array<string,mixed>,
     *     fields:array<string,mixed>
     * }
     */
    public function project(
        string $domain,
        array $payload
    ): array {
        $definition =
            $this->domainDefinition(
                $domain
            );

        $fieldDefinitions =
            $definition['fields']
            ?? null;

        if (! is_array($fieldDefinitions)) {
            throw new RuntimeException(
                "El dominio {$domain} no contiene "
                .'definiciones de campos válidas.'
            );
        }

        $declaredFields = [];
        $declaredLookup = [];

        foreach (
            $fieldDefinitions
            as $field
        ) {
            if (! is_array($field)) {
                throw new RuntimeException(
                    "El dominio {$domain} contiene "
                    .'una definición de campo inválida.'
                );
            }

            $name =
                trim(
                    (string) (
                        $field['name']
                        ?? ''
                    )
                );

            $type =
                trim(
                    (string) (
                        $field['type']
                        ?? ''
                    )
                );

            $required =
                $field['required']
                ?? null;

            if (
                $name === ''
                || $type === ''
                || ! is_bool($required)
            ) {
                throw new RuntimeException(
                    "El dominio {$domain} contiene "
                    .'un contrato de campo incompleto.'
                );
            }

            if (
                array_key_exists(
                    $name,
                    $declaredLookup
                )
            ) {
                throw new RuntimeException(
                    "El dominio {$domain} contiene "
                    ."el campo duplicado {$name}."
                );
            }

            $declaredFields[] = [
                'name' =>
                    $name,

                'type' =>
                    $type,

                'required' =>
                    $required,
            ];

            $declaredLookup[$name] =
                true;
        }

        foreach (
            array_keys($payload)
            as $payloadField
        ) {
            if (
                ! is_string($payloadField)
                || ! array_key_exists(
                    $payloadField,
                    $declaredLookup
                )
            ) {
                throw new RuntimeException(
                    "El payload canónico de {$domain} "
                    .'contiene un campo no declarado.'
                );
            }
        }

        $fields = [];

        foreach (
            $declaredFields
            as $field
        ) {
            $name =
                $field['name'];

            $required =
                $field['required'];

            $exists =
                array_key_exists(
                    $name,
                    $payload
                );

            if (
                $required
                && ! $exists
            ) {
                throw new RuntimeException(
                    "El payload canónico de {$domain} "
                    ."no contiene el campo requerido {$name}."
                );
            }

            $value =
                $exists
                    ? $payload[$name]
                    : null;

            if (
                $required
                && $value === null
            ) {
                throw new RuntimeException(
                    "El campo requerido {$domain}.{$name} "
                    .'no puede ser nulo.'
                );
            }

            $fields[$name] =
                $value;
        }

        /*
         * Reuse the canonical normalizer as the type/format authority.
         *
         * If normalization would change anything, the supplied payload was
         * not yet in canonical normalized form and must fail closed.
         */
        $normalized =
            $this->normalizer
                ->normalize(
                    $domain,
                    $fields
                );

        $normalizedFields =
            $normalized['payload']
            ?? null;

        if (
            ! is_array($normalizedFields)
            || $normalizedFields !== $fields
        ) {
            throw new RuntimeException(
                "El payload de {$domain} no está "
                .'en forma canónica normalizada.'
            );
        }

        $identityKeys =
            DataTransformationBiStandardIntakeSchema
                ::identityKeys()[$domain]
            ?? null;

        if (
            ! is_array($identityKeys)
            || $identityKeys === []
        ) {
            throw new RuntimeException(
                "El dominio {$domain} no contiene "
                .'contrato de identidad canónica.'
            );
        }

        $identity = [];

        foreach (
            $identityKeys
            as $identityKey
        ) {
            if (
                ! is_string($identityKey)
                || $identityKey === ''
                || ! array_key_exists(
                    $identityKey,
                    $fields
                )
            ) {
                throw new RuntimeException(
                    "El dominio {$domain} contiene "
                    .'una identidad canónica inválida.'
                );
            }

            $identity[$identityKey] =
                $fields[$identityKey];
        }

        return [
            'domain' =>
                $domain,

            'identity' =>
                $identity,

            'fields' =>
                $fields,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function domainDefinition(
        string $domain
    ): array {
        $domain =
            trim(
                $domain
            );

        $domains =
            DataTransformationBiStandardIntakeSchema
                ::domains();

        if (
            $domain === ''
            || ! array_key_exists(
                $domain,
                $domains
            )
        ) {
            throw new RuntimeException(
                "Dominio canónico no soportado: {$domain}."
            );
        }

        $definition =
            $domains[$domain];

        if (! is_array($definition)) {
            throw new RuntimeException(
                "El contrato del dominio {$domain} es inválido."
            );
        }

        return $definition;
    }
}
