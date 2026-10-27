<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/**
 * D2F-T2C1. Valida y normaliza la revisión HUMANA del formulario.
 * Sin consultas, escrituras, envíos, datos transaccionales ni estado comercial.
 * Las claves de objetivos y vínculos se recuperan EXCLUSIVAMENTE del
 * borrador generado en servidor, jamás del payload remitido por el navegador.
 */
final class DataBiInformationRequirementReviewContract
{
    private const MAX_REQUIREMENTS = 100;
    private const MAX_LABEL = 191;
    private const MAX_GUIDANCE = 800;

    /** @param array<string,mixed> $generated @param array<string,mixed> $submitted
     *  @return array<string,mixed> */
    public static function normalize(array $generated, array $submitted): array
    {
        if (($generated['schema_version'] ?? null) !== 1
            || ($generated['mode'] ?? null) !== 'preview_only'
            || ($generated['purpose'] ?? null) !== 'information_source_declaration'
            || ($generated['data_inspected'] ?? null) !== false) {
            throw new InvalidArgumentException('El borrador de origen no es un preview declarativo válido.');
        }

        $suggested = $generated['requirements'] ?? null;
        $submittedItems = $submitted['requirements'] ?? null;
        if (!is_array($suggested) || !array_is_list($suggested) || $suggested === []
            || count($suggested) > self::MAX_REQUIREMENTS
            || !is_array($submittedItems) || !array_is_list($submittedItems)
            || count($submittedItems) !== count($suggested)) {
            throw new InvalidArgumentException('La revisión debe contener exactamente los requerimientos generados.');
        }

        $byKey = [];
        foreach ($suggested as $item) {
            if (!is_array($item) || !is_string($item['key'] ?? null)
                || ($item['key'] ?? '') === '' || isset($byKey[$item['key']])
                || !is_array($item['objective_keys'] ?? null)
                || ($item['objective_keys'] ?? []) === []) {
                throw new InvalidArgumentException('La propuesta original contiene claves o vínculos inválidos.');
            }
            $byKey[$item['key']] = $item;
        }

        $reviewByKey = [];
        foreach ($submittedItems as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['key', 'included', 'label', 'guidance'])
                || !is_string($item['key'] ?? null)
                || !array_key_exists($item['key'], $byKey)
                || isset($reviewByKey[$item['key']])
                || !is_bool($item['included'] ?? null)
                || !is_string($item['label'] ?? null)
                || !is_string($item['guidance'] ?? null)) {
                throw new InvalidArgumentException('La revisión contiene una clave, tipo o atributo no autorizado.');
            }
            $reviewByKey[$item['key']] = $item;
        }

        $result = [];
        $labels = [];
        foreach ($byKey as $key => $suggestion) {
            $review = $reviewByKey[$key] ?? null;
            if ($review === null) {
                throw new InvalidArgumentException('Falta un requerimiento del borrador original.');
            }
            if (!$review['included']) {
                continue;
            }
            $label = trim($review['label']);
            $guidance = trim($review['guidance']);
            if ($label === '' || mb_strlen($label) > self::MAX_LABEL
                || $guidance === '' || mb_strlen($guidance) > self::MAX_GUIDANCE) {
                throw new InvalidArgumentException('Título o instrucción vacía o fuera de longitud admitida.');
            }
            $labelKey = mb_strtolower($label);
            if (isset($labels[$labelKey])) {
                throw new InvalidArgumentException('Hay títulos duplicados en los requerimientos incluidos.');
            }
            $labels[$labelKey] = true;
            $result[] = [
                'key' => $key,
                'label' => $label,
                'guidance' => $guidance,
                'objective_keys' => array_values($suggestion['objective_keys']),
            ];
        }
        if ($result === []) {
            throw new InvalidArgumentException('Debe quedar al menos un requerimiento para guardar el borrador.');
        }

        $needs = $generated['selected_needs'] ?? [];
        $unmapped = $generated['unmapped_need_keys'] ?? [];
        if (!is_array($needs) || !is_array($unmapped)) {
            throw new InvalidArgumentException('No se pudieron validar los objetivos originales.');
        }
        $payload = [
            'schema_version' => 1,
            'purpose' => 'information_source_declaration',
            'origin' => 'tenant_business_needs_snapshot',
            'selected_needs' => $needs,
            'unmapped_need_keys' => $unmapped,
            'requirements' => $result,
            'data_inspected' => false,
            'tenant_response_received' => false,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payload['content_sha256'] = hash('sha256', $json);
        return $payload;
    }
}
