<?php

namespace App\Services\Diagnosis;

use UnexpectedValueException;

/**
 * A grounded, pre-evidence free-diagnostic proposal for request-scoped BI.
 * Only changes prepared content. It does not change the request, status,
 * source snapshot, approvals, financial terms, or commercial lifecycle.
 */
final class DataBiDiagnosticDraftComposer
{
    public function enrich(array $generated, array $source): array
    {
        $needs = $source['diagnostic_business_needs'] ?? $source['business_needs'] ?? null;
        if (! is_array($needs) || ! is_array($needs['improvements'] ?? null) || $needs['improvements'] === []) {
            // Preserve legacy request definitions where no business-needs intake exists.
            return $generated;
        }

        $groups = ['operaciones' => [], 'gestion' => [], 'finanzas' => []];
        $seen = [];
        foreach ($needs['improvements'] as $need) {
            if (! is_array($need)) {
                throw new UnexpectedValueException('Necesidad empresarial inválida en la solicitud.');
            }
            $key = trim((string) ($need['key'] ?? ''));
            $area = trim((string) ($need['group'] ?? ''));
            $label = trim((string) ($need['label'] ?? ''));
            if ($key === '' || $label === '' || ! array_key_exists($area, $groups) || isset($seen[$key])) {
                throw new UnexpectedValueException('Necesidades empresariales incompletas o duplicadas.');
            }
            $seen[$key] = true;
            $groups[$area][] = ['key' => $key, 'label' => $label, 'evaluation_status' => 'pending'];
        }
        $groups = array_filter($groups, static fn (array $items): bool => $items !== []);

        $objective = 'Evaluar la disponibilidad, estructura, consistencia y preparación de fuentes de datos autorizadas vinculadas a las necesidades de la empresa, para identificar brechas y recomendar mejoras futuras.';
        $evaluations = [];
        foreach ($groups as $items) {
            foreach ($items as $item) {
                $evaluations[] = 'Evaluar disponibilidad, estructura y consistencia de información sobre: '.$item['label'].'.';
            }
        }
        $requirements = [];
        if (isset($groups['operaciones'])) {
            $requirements[] = 'Identificar las fuentes autorizadas sobre operaciones, recursos, tiempos, productividad o inventarios que correspondan a las necesidades declaradas.';
        }
        if (isset($groups['gestion'])) {
            $requirements[] = 'Identificar las fuentes autorizadas sobre clientes, indicadores, tendencias o riesgos que correspondan a las necesidades declaradas.';
        }
        if (isset($groups['finanzas'])) {
            $requirements[] = 'Identificar las fuentes autorizadas sobre ingresos, gastos, cobros, pagos o proyecciones que correspondan a las necesidades declaradas.';
        }
        $requirements[] = 'Confirmar por cada fuente su responsable, cobertura temporal, granularidad, formato y autorización de acceso.';
        $requirements[] = 'Acordar muestras representativas y límites de evaluación; ninguna fuente se presupone existente ni autorizada.';

        $deliverables = [
            'Inventario básico de las fuentes efectivamente evaluadas y sus restricciones.',
            'Matriz de correspondencia entre necesidades declaradas y datos disponibles o pendientes.',
            'Hallazgos de disponibilidad, estructura y consistencia sustentados en muestras autorizadas.',
            'Informe de diagnóstico básico con brechas, limitaciones y recomendaciones priorizadas.',
        ];
        $exclusions = [
            'Implementación productiva, integraciones definitivas y automatizaciones operativas.',
            'Procesamiento masivo de datos, modelos analíticos definitivos y dashboards productivos.',
            'Contratación comercial, precios, facturación, pagos y suscripciones.',
            'Afirmaciones de calidad de datos sin evidencias obtenidas con autorización.',
        ];

        $scope = $generated['implementation_scope'] ?? null;
        if (! is_array($scope)) {
            throw new UnexpectedValueException('El alcance original del generador es inválido.');
        }
        $scope['purpose'] = $objective;
        $scope['includes'] = $evaluations;
        $scope['excludes'] = $exclusions;
        // Retain request/phase/capability identifiers, and all separately reviewed
        // BI needs-assessment and expected-sources fields already in the scope.
        if (is_array($scope['phases'] ?? null)) {
            foreach ($scope['phases'] as &$phase) {
                if (! is_array($phase)) {
                    continue;
                }
                $phase['objective'] = $objective;
                $phase['deliverables'] = $deliverables;
                $phase['dependencies'] = $requirements;
                if (is_array($phase['capabilities'] ?? null)) {
                    foreach ($phase['capabilities'] as &$capability) {
                        if (is_array($capability) && ($capability['capability_key'] ?? null) === 'data_transformation_bi') {
                            $capability['purpose'] = $objective;
                            $capability['scope_items'] = $evaluations;
                        }
                    }
                    unset($capability);
                }
            }
            unset($phase);
        }

        $phaseMeta = [
            'source' => 'basic_diagnostic_scope',
            'phase_id' => (int) ($scope['phase_id'] ?? $source['phase']['id'] ?? 0),
            'phase_name' => (string) ($scope['phase_name'] ?? $source['phase']['name'] ?? ''),
            'capability_key' => 'data_transformation_bi',
            'phase_sequence' => (int) ($scope['phase_sequence'] ?? $source['phase']['sequence'] ?? 0),
            'phase_capability_id' => (int) ($scope['phase_capability_id'] ?? $source['capability']['id'] ?? 0),
        ];
        // Use the original data structure consumed by the existing review UI.
        $generated['deliverables'] = array_map(
            static fn (string $value): array => $phaseMeta + ['deliverable' => $value],
            $deliverables
        );
        $generated['dependencies'] = array_map(
            static fn (string $value): array => $phaseMeta + ['dependency' => $value],
            $requirements
        );
        // Responsibility proposals and readiness checks remain server-owned and
        // require human confirmation; do not assert technical quality or readiness.
        $scope['diagnostic_draft'] = [
            'schema_version' => 2,
            'service_phase' => 'basic_diagnosis_without_charge',
            'basis' => 'tenant_request_only',
            'source_request_id' => (int) ($source['request']['id'] ?? 0),
            'objective' => $objective,
            'identified_problem' => trim((string) ($needs['need'] ?? '')),
            'expected_result' => trim((string) ($needs['expected_result'] ?? '')),
            'additional_context' => trim((string) ($needs['additional_info'] ?? '')),
            'needs_by_area' => $groups,
            'information_to_confirm' => $requirements,
            'proposed_evaluations' => $evaluations,
            'proposed_deliverables' => $deliverables,
            'exclusions' => $exclusions,
            'requires_human_review' => true,
            'sources_verified' => false,
        ];
        // BI_UAT05_DIAGNOSTIC_RESPONSIBILITY_LABELS
        // Preserve IDs, parties, and confirmation states, but not the paid
        // transformation language in a free-diagnostic draft.
        $responsibilityModel = $generated['responsibility_model'] ?? null;
        if (is_array($responsibilityModel) && is_array($responsibilityModel['assignments'] ?? null)) {
            foreach ($responsibilityModel['assignments'] as &$assignment) {
                if (! is_array($assignment)) {
                    continue;
                }
                $initiativeId = (string) ($assignment['initiative_id'] ?? '');
                if ($initiativeId === 'data_transformation_bi:client_source_delivery') {
                    $assignment['initiative_title'] = 'Empresa · identificación y entrega autorizada de fuentes';
                } elseif ($initiativeId === 'data_transformation_bi:lauda_transformation') {
                    $assignment['initiative_title'] = 'LAUDA · evaluación diagnóstica básica y recomendaciones';
                }
            }
            unset($assignment);
            $generated['responsibility_model'] = $responsibilityModel;
        }
        $generated['implementation_scope'] = $scope;
        return $generated;
    }
}
