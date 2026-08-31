<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationCapabilityNeed;

final class BrandingIdentityEvaluationDraftGenerator
{
    public function generate(
        TransformationCapabilityNeed $need,
        array $context
    ): array {
        $definition =
            $this->definitionFor(
                (string) $need->need_key
            );

        $searchable =
            $this->searchableContext(
                $context
            );

        $matches =
            $this->matchedSignals(
                $searchable,
                $definition['keywords']
            );

        $sources =
            is_array(
                $context['sources']
                ?? null
            )
                ? array_values(
                    $context['sources']
                )
                : [];

        $generationContext = [
            'generation_mode' =>
                $context['generation_mode']
                ?? 'deterministic_context_v1',

            'sources' =>
                $sources,

            'evidence_terms' =>
                $matches,

            'diagnosis_available' =>
                is_array(
                    $context['assessment']
                    ?? null
                ),

            'roadmap_context_available' =>
                is_array(
                    $context['roadmap']
                    ?? null
                ),

            'plan_context_available' =>
                (bool) data_get(
                    $context,
                    'plan_context.available',
                    false
                ),

            'presence_score' =>
                data_get(
                    $context,
                    'assessment.dimension_scores.presence'
                ),

            'urgency_score' =>
                data_get(
                    $context,
                    'assessment.urgency_score'
                ),
        ];

        /*
         * Regla fail-closed:
         *
         * La ausencia de una señal NO significa que el área
         * esté adecuada. Si no hay evidencia suficiente,
         * solicitamos más información.
         */
        if ($matches === []) {
            return [
                'suggested_result' =>
                    'insufficient_information',

                'suggested_findings' =>
                    'El contexto disponible no contiene evidencia suficiente para confirmar si esta área requiere intervención.',

                'suggested_recommendation' =>
                    null,

                'suggested_priority' =>
                    null,

                'suggested_questions' =>
                    $definition['questions'],

                'generation_context' =>
                    $generationContext,
            ];
        }

        $priority =
            $this->priority(
                $context
            );

        return [
            'suggested_result' =>
                'requires_attention',

            'suggested_findings' =>
                $this->findings(
                    $matches,
                    $sources
                ),

            'suggested_recommendation' =>
                $definition[
                    'recommendation'
                ],

            'suggested_priority' =>
                $priority,

            'suggested_questions' =>
                $definition[
                    'validation_questions'
                ],

            'generation_context' =>
                $generationContext,
        ];
    }

    private function definitionFor(
        string $needKey
    ): array {
        return match ($needKey) {
            'positioning_refinement' => [
                'keywords' => [
                    'posicionamiento',
                    'propuesta de valor',
                    'diferenciación',
                    'diferenciacion',
                    'mensaje de marca',
                    'promesa de marca',
                ],

                'recommendation' =>
                    'Revisar la propuesta de valor, la diferenciación y los mensajes prioritarios de marca para definir un posicionamiento consistente y comprensible.',

                'questions' => [
                    '¿La empresa tiene definida formalmente su propuesta de valor?',
                    '¿Existe una definición clara de los públicos principales de la marca?',
                    '¿Los mensajes comerciales expresan una diferenciación concreta frente a otras alternativas?',
                ],

                'validation_questions' => [
                    '¿La señal detectada continúa vigente?',
                    '¿Existe documentación actual de posicionamiento o propuesta de valor?',
                ],
            ],

            'visual_identity_update' => [
                'keywords' => [
                    'identidad visual',
                    'imagen corporativa',
                    'logotipo',
                    'logo',
                    'rebranding',
                    'rediseño de marca',
                    'rediseño de imagen',
                ],

                'recommendation' =>
                    'Revisar la identidad visual actual y definir los ajustes necesarios para alinearla con el posicionamiento y los usos reales de la marca.',

                'questions' => [
                    '¿La empresa dispone de logotipo en formatos editables y versiones autorizadas?',
                    '¿Existe una paleta de colores y tipografías definida?',
                    '¿La identidad visual se aplica de forma consistente en los principales puntos de contacto?',
                ],

                'validation_questions' => [
                    '¿La identidad visual actual sigue representando correctamente a la empresa?',
                    '¿Existen inconsistencias visibles entre aplicaciones de la marca?',
                ],
            ],

            'brand_kit' => [
                'keywords' => [
                    'brand kit',
                    'manual de marca',
                    'guía de marca',
                    'guia de marca',
                    'lineamientos de marca',
                    'normas de identidad',
                ],

                'recommendation' =>
                    'Consolidar los elementos y criterios aprobados de identidad en un Brand Kit reutilizable que facilite el uso consistente de la marca.',

                'questions' => [
                    '¿Existe actualmente un manual o Brand Kit vigente?',
                    '¿Están documentados los usos correctos e incorrectos del logotipo?',
                    '¿Los equipos internos y proveedores disponen de los recursos oficiales de la marca?',
                ],

                'validation_questions' => [
                    '¿El material de marca existente está actualizado?',
                    '¿El equipo utiliza una fuente única y oficial de activos de marca?',
                ],
            ],

            'social_normalization' => [
                'keywords' => [
                    'redes sociales',
                    'social media',
                    'instagram',
                    'facebook',
                    'linkedin',
                    'tiktok',
                    'perfiles sociales',
                ],

                'recommendation' =>
                    'Revisar y normalizar la presentación de los perfiles sociales prioritarios para asegurar consistencia visual, descriptiva y de posicionamiento.',

                'questions' => [
                    '¿Qué redes sociales utiliza actualmente la empresa?',
                    '¿Los nombres, descripciones e imágenes de perfil son consistentes?',
                    '¿Existe un criterio visual común para publicaciones y portadas?',
                ],

                'validation_questions' => [
                    '¿Todos los perfiles activos representan correctamente la marca actual?',
                    '¿Existen perfiles duplicados, desactualizados o con información inconsistente?',
                ],
            ],

            'commercial_documents' => [
                'keywords' => [
                    'documentos comerciales',
                    'cotización',
                    'cotizacion',
                    'propuesta comercial',
                    'presentación comercial',
                    'presentacion comercial',
                    'brochure',
                    'tarjeta',
                    'factura',
                ],

                'recommendation' =>
                    'Identificar los documentos comerciales prioritarios y normalizar su aplicación visual y de marca utilizando lineamientos consistentes.',

                'questions' => [
                    '¿Cuáles son los documentos comerciales utilizados con mayor frecuencia?',
                    '¿Cotizaciones, propuestas y presentaciones utilizan una identidad consistente?',
                    '¿Existen plantillas oficiales para el equipo comercial?',
                ],

                'validation_questions' => [
                    '¿Los documentos actuales utilizan la identidad vigente?',
                    '¿Hay documentos prioritarios que todavía utilizan formatos anteriores?',
                ],
            ],

            'web_application' => [
                'keywords' => [
                    'sitio web',
                    'página web',
                    'pagina web',
                    'presencia web',
                    'website',
                    'landing page',
                    'portal web',
                    'web corporativa',
                ],

                'recommendation' =>
                    'Revisar la presencia web y alinear su identidad, mensajes y elementos visuales con los lineamientos definidos para la marca.',

                'questions' => [
                    '¿La empresa dispone actualmente de sitio o presencia web propia?',
                    '¿La identidad utilizada en la web coincide con la identidad vigente?',
                    '¿Los mensajes principales de la web reflejan la propuesta de valor actual?',
                ],

                'validation_questions' => [
                    '¿La presencia web utiliza la identidad vigente?',
                    '¿Existen inconsistencias entre web, redes sociales y documentos comerciales?',
                ],
            ],

            default => [
                'keywords' =>
                    [],

                'recommendation' =>
                    null,

                'questions' => [
                    '¿Qué información adicional debe revisarse para evaluar esta área?',
                ],

                'validation_questions' =>
                    [],
            ],
        };
    }

    private function searchableContext(
        array $context
    ): string {
        $parts = [];

        $assessment =
            is_array(
                $context['assessment']
                ?? null
            )
                ? $context['assessment']
                : [];

        $this->append(
            $parts,
            $assessment[
                'review_summary'
            ]
            ?? null
        );

        $this->append(
            $parts,
            $assessment[
                'review_priorities'
            ]
            ?? []
        );

        $this->append(
            $parts,
            $assessment[
                'business_activity_description'
            ]
            ?? null
        );

        $roadmap =
            is_array(
                $context['roadmap']
                ?? null
            )
                ? $context['roadmap']
                : [];

        $this->append(
            $parts,
            $roadmap[
                'recommendation_basis'
            ]
            ?? null
        );

        $plan =
            is_array(
                $context['plan_context']
                ?? null
            )
                ? $context['plan_context']
                : [];

        $this->append(
            $parts,
            $plan[
                'related_initiatives'
            ]
            ?? []
        );

        $this->append(
            $parts,
            $plan[
                'dependencies'
            ]
            ?? []
        );

        $this->append(
            $parts,
            $plan[
                'deliverables'
            ]
            ?? []
        );

        return mb_strtolower(
            implode(
                "\n",
                array_filter(
                    $parts
                )
            )
        );
    }

    private function append(
        array &$parts,
        mixed $value
    ): void {
        if (is_string($value)) {
            $value = trim($value);

            if ($value !== '') {
                $parts[] = $value;
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                $this->append(
                    $parts,
                    $item
                );

                continue;
            }

            if (is_string($item)) {
                $this->append(
                    $parts,
                    $item
                );
            }
        }
    }

    private function matchedSignals(
        string $context,
        array $keywords
    ): array {
        if ($context === '') {
            return [];
        }

        return collect($keywords)
            ->map(
                fn ($keyword): string =>
                    mb_strtolower(
                        trim(
                            (string) $keyword
                        )
                    )
            )
            ->filter()
            ->filter(
                fn (string $keyword): bool =>
                    str_contains(
                        $context,
                        $keyword
                    )
            )
            ->unique()
            ->values()
            ->all();
    }

    private function priority(
        array $context
    ): string {
        $presence =
            data_get(
                $context,
                'assessment.dimension_scores.presence'
            );

        $urgency =
            data_get(
                $context,
                'assessment.urgency_score'
            );

        if (
            is_numeric($urgency)
            && (float) $urgency >= 75
        ) {
            return 'high';
        }

        if (
            is_numeric($presence)
            && (float) $presence <= 40
        ) {
            return 'high';
        }

        if (
            is_numeric($presence)
            && (float) $presence <= 60
        ) {
            return 'medium';
        }

        if (
            (bool) data_get(
                $context,
                'roadmap.recommended',
                false
            )
        ) {
            return 'medium';
        }

        return 'low';
    }

    private function findings(
        array $matches,
        array $sources
    ): string {
        $signals =
            implode(
                ', ',
                $matches
            );

        $sourceLabel =
            $sources !== []
                ? implode(
                    ', ',
                    $sources
                )
                : 'el contexto disponible';

        return
            'Se encontraron señales explícitas relacionadas con '
            .$signals
            .' en '
            .$sourceLabel
            .'. Estas señales requieren revisión profesional antes de confirmar una necesidad.';
    }
}
