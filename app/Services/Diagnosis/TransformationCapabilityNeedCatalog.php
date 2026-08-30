<?php

namespace App\Services\Diagnosis;

final class TransformationCapabilityNeedCatalog
{
    public const BRANDING_VERSION =
        'branding_identity_needs_v1';

    public static function forCapability(
        string $capabilityKey
    ): array {
        return match (trim($capabilityKey)) {
            'branding_identity' =>
                self::branding(),

            default =>
                [],
        };
    }

    public static function versionFor(
        string $capabilityKey
    ): ?string {
        return match (trim($capabilityKey)) {
            'branding_identity' =>
                self::BRANDING_VERSION,

            default =>
                null,
        };
    }

    private static function branding(): array
    {
        return [
            [
                'need_key' =>
                    'positioning_refinement',
                'title' =>
                    'Refinamiento de posicionamiento',
                'description' =>
                    'Revisar y afinar el posicionamiento y la propuesta de valor para que la marca comunique con mayor claridad su diferenciación.',
            ],
            [
                'need_key' =>
                    'visual_identity_update',
                'title' =>
                    'Actualización de identidad visual',
                'description' =>
                    'Actualizar la identidad visual y sus lineamientos cuando la presentación actual no acompañe el posicionamiento definido.',
            ],
            [
                'need_key' =>
                    'brand_kit',
                'title' =>
                    'Creación de Brand Kit',
                'description' =>
                    'Consolidar los elementos visuales, criterios y lineamientos de uso en un Brand Kit Digital reutilizable.',
            ],
            [
                'need_key' =>
                    'social_normalization',
                'title' =>
                    'Normalización de redes sociales',
                'description' =>
                    'Alinear perfiles, elementos visuales y criterios de presentación para lograr consistencia entre redes sociales.',
            ],
            [
                'need_key' =>
                    'commercial_documents',
                'title' =>
                    'Adaptación de documentos comerciales',
                'description' =>
                    'Aplicar la identidad y los lineamientos de marca a los documentos comerciales prioritarios.',
            ],
            [
                'need_key' =>
                    'web_application',
                'title' =>
                    'Aplicación en presencia web',
                'description' =>
                    'Aplicar la identidad definida a la presencia web y a los puntos de contacto digitales relacionados.',
            ],
        ];
    }
}
