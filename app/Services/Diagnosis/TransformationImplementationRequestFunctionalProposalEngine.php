<?php

namespace App\Services\Diagnosis;

final class TransformationImplementationRequestFunctionalProposalEngine
{
    /* Claves y entregables verificados en BI-13-M3. Cada relación es PARCIAL. */
    private const RULES = [
        'operaciones_inventarios' => [
            'Información de productos, existencias y movimientos de inventario',
            'Productos, categorías, existencia, movimientos, costos, precios, rotación, margen, disponibilidad e inventario valorizado.',
        ],
        'gestion_clientes' => [
            'Información maestra e histórica de clientes y segmentos',
            'Maestro, calidad, comportamiento histórico, segmentación e industria de clientes.',
        ],
        'gestion_desempeno' => [
            'Historial de ventas por fecha, cliente, producto, categoría, sucursal y vendedor',
            'Historial de ventas por fecha, cliente, producto, categoría, sucursal y vendedor.',
        ],
        'gestion_riesgos' => [
            'Información de clientes y segmentos para evaluar riesgos y oportunidades',
            'Riesgo, oportunidad, concentración y exposición de clientes y segmentos mediante indicadores explicables.',
        ],
    ];

    public static function preview(array $snapshot, array $scope, array $deliverables): array
    {
        $scope = TransformationImplementationRequestBusinessNeedsAssessment::normalize($snapshot, $scope);
        $scope = TransformationImplementationRequestExpectedSourcesPlan::normalize($snapshot, $scope, $deliverables);
        $needs = data_get($snapshot, 'business_needs.improvements', []);
        $needs = is_array($needs) ? $needs : [];
        $plan = $scope['expected_sources_plan'] ?? ['sources' => [], 'links' => []];

        $statuses = [];
        foreach (data_get($scope, 'business_needs_assessment.items', []) as $item) {
            $statuses[$item['key']] = $item['status'];
        }
        $linked = [];
        foreach ($plan['links'] as $link) {
            $linked[$link['need_key']] = true;
        }
        $byName = [];
        $usedKeys = [];
        foreach ($plan['sources'] as $source) {
            $byName[mb_strtolower(trim($source['name']))][] = $source['key'];
            $usedKeys[$source['key']] = true;
        }

        $catalog = [];
        foreach ($deliverables as $item) {
            if (!is_array($item)
                || ($item['source'] ?? null) !== 'professional_capability'
                || ($item['capability_key'] ?? null) !== 'data_transformation_bi'
                || !is_string($item['deliverable'] ?? null)) {
                continue;
            }
            $description = trim($item['deliverable']);
            $catalog[$description][] = TransformationImplementationRequestExpectedSourcesPlan::deliverableRef($item);
        }

        $suggestions = [];
        $skipped = [];
        foreach ($needs as $need) {
            $key = trim((string) ($need['key'] ?? ''));
            $reason = null;
            if (($statuses[$key] ?? null) === 'excluded') {
                $reason = 'excluded_by_lauda';
            } elseif (isset($linked[$key])) {
                $reason = 'existing_human_link';
            } elseif (!isset(self::RULES[$key])) {
                $reason = 'no_verified_rule';
            }
            if ($reason !== null) {
                $skipped[] = ['need_key' => $key, 'reason' => $reason];
                continue;
            }

            [$name, $description] = self::RULES[$key];
            $matches = $catalog[$description] ?? [];
            if (count($matches) !== 1) {
                $skipped[] = [
                    'need_key' => $key,
                    'reason' => $matches === [] ? 'deliverable_missing' : 'deliverable_ambiguous',
                ];
                continue;
            }

            $matchesExisting = $byName[mb_strtolower($name)] ?? [];
            if (count($matchesExisting) > 1) {
                $skipped[] = ['need_key' => $key, 'reason' => 'existing_requirement_ambiguous'];
                continue;
            }
            $reuse = $matchesExisting !== [];
            $sourceKey = $reuse
                ? $matchesExisting[0]
                : 'src_bi13_'.substr(hash('sha256', $key.'|'.$name), 0, 20);
            if (!$reuse && isset($usedKeys[$sourceKey])) {
                $skipped[] = ['need_key' => $key, 'reason' => 'generated_key_collision'];
                continue;
            }
            $source = $reuse ? null : [
                'key' => $sourceKey,
                'name' => $name,
                'source_type' => 'to_define',
                'origin_hint' => '',
                'data_domains' => [],
                'notes' => 'Propuesta funcional. Origen, disponibilidad y cobertura pendientes de confirmar con el cliente.',
            ];
            if (!$reuse) {
                $byName[mb_strtolower($name)] = [$sourceKey];
                $usedKeys[$sourceKey] = true;
            }
            $suggestions[] = [
                'need_key' => $key,
                'need_label' => (string) ($need['label'] ?? $key),
                'action' => $reuse ? 'reuse_requirement' : 'create_requirement',
                'source' => $source,
                'link' => [
                    'need_key' => $key,
                    'source_keys' => [$sourceKey],
                    'deliverable_refs' => [$matches[0]],
                    'notes' => 'Relación parcial sugerida mediante regla explícita del catálogo; validar con Admin LAUDA.',
                ],
                'coverage' => 'partial',
                'requires_human_review' => true,
                'source_verified' => false,
            ];
        }
        return [
            'schema_version' => 1,
            'rules_version' => 1,
            'mode' => 'preview_only',
            'suggestions' => $suggestions,
            'skipped' => $skipped,
            'summary' => [
                'total_needs' => count($needs),
                'suggested' => count($suggestions),
                'not_suggested' => count($skipped),
            ],
        ];
    }
}
