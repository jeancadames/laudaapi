<?php

namespace App\Services\Diagnosis;

final class DataTransformationBiQualityGuidanceCatalog
{
    /**
     * Stable, business-safe guidance keyed by technical issue code.
     *
     * This catalog never receives or returns source values.
     *
     * @return array{
     *     label:string,
     *     guidance:string
     * }
     */
    public static function forCode(
        string $code
    ): array {
        return match ($code) {
            'required_field_incomplete' => [
                'label' =>
                    'Campo requerido incompleto',

                'guidance' =>
                    'Completa los valores faltantes o nulos '
                    .'de este campo antes de normalizar los datos.',
            ],

            'optional_field_incomplete' => [
                'label' =>
                    'Campo opcional incompleto',

                'guidance' =>
                    'Puedes completar estos valores para mejorar '
                    .'la calidad del conjunto. Esta condición '
                    .'no bloquea por sí sola la normalización.',
            ],

            'missing_identity_hash' => [
                'label' =>
                    'Identidad canónica faltante',

                'guidance' =>
                    'Revisa los campos que forman la identidad '
                    .'del dominio y asegúrate de que permitan '
                    .'calcular una clave estable para cada registro.',
            ],

            'duplicate_identity_in_staging' => [
                'label' =>
                    'Identidades duplicadas',

                'guidance' =>
                    'Corrige los registros que producen la misma '
                    .'identidad canónica para que cada entidad '
                    .'o transacción quede identificada de forma única.',
            ],

            'staging_validation_contract_violation' => [
                'label' =>
                    'Contrato de datos inconsistente',

                'guidance' =>
                    'Revisa y vuelve a validar la fuente. Los datos '
                    .'en staging ya no cumplen completamente el '
                    .'contrato canónico esperado por LAUDA.',
            ],

            'staging_relation_contract_violation' => [
                'label' =>
                    'Relaciones entre datos inconsistentes',

                'guidance' =>
                    'Corrige las referencias entre dominios para '
                    .'que clientes, productos, suplidores y demás '
                    .'entidades relacionadas apunten a identificadores '
                    .'canónicos existentes.',
            ],

            default => [
                'label' =>
                    self::humanize(
                        $code
                    ),

                'guidance' =>
                    'Revisa esta incidencia de calidad antes de '
                    .'continuar con la preparación de los datos.',
            ],
        };
    }

    private static function humanize(
        string $code
    ): string {
        $value =
            trim(
                str_replace(
                    '_',
                    ' ',
                    $code
                )
            );

        if ($value === '') {
            return 'Incidencia de calidad';
        }

        return ucfirst(
            $value
        );
    }
}
