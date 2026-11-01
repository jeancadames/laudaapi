<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/**
 * D2F-T3A.
 *
 * Política pura para decidir si una versión puede enviarse.
 *
 * No escribe en BD.
 * No modifica Definition V1.
 * No envía notificaciones.
 * No habilita endpoints.
 */
final class DataBiInformationRequestSendPolicy
{
    /**
     * @param array<string,mixed> $latest
     * @return array{
     *   action:'send'|'already_sent',
     *   version:int
     * }
     */
    public static function decide(
        array $latest,
        int $expectedVersion
    ): array {
        $version = $latest['version'] ?? null;
        $status = $latest['status'] ?? null;
        $hash = $latest['content_sha256'] ?? null;
        $snapshot = $latest['review_snapshot'] ?? null;

        if (
            !is_int($version) ||
            $version < 1 ||
            $version > 9999 ||
            $expectedVersion !== $version
        ) {
            throw new InvalidArgumentException(
                'Conflicto: la versión solicitada no es la vigente.'
            );
        }

        if (
            !is_string($hash) ||
            preg_match('/\A[a-f0-9]{64}\z/D', $hash) !== 1 ||
            !is_array($snapshot) ||
            !is_string($snapshot['content_sha256'] ?? null) ||
            !hash_equals($hash, $snapshot['content_sha256']) ||
            ($snapshot['schema_version'] ?? null) !== 1 ||
            ($snapshot['purpose'] ?? null) !==
                'information_source_declaration' ||
            ($snapshot['origin'] ?? null) !==
                'tenant_business_needs_snapshot' ||
            ($snapshot['data_inspected'] ?? null) !== false ||
            ($snapshot['tenant_response_received'] ?? null) !== false
        ) {
            throw new InvalidArgumentException(
                'El borrador declarativo no es válido.'
            );
        }

        $items = $snapshot['requirements'] ?? null;

        if (
            !is_array($items) ||
            !array_is_list($items) ||
            $items === [] ||
            count($items) > 100
        ) {
            throw new InvalidArgumentException(
                'No existen requerimientos válidos para enviar.'
            );
        }

        $keys = [];

        foreach ($items as $item) {
            if (
                !is_array($item) ||
                !is_string($item['key'] ?? null) ||
                trim($item['key']) === '' ||
                isset($keys[$item['key']]) ||
                !is_string($item['label'] ?? null) ||
                trim($item['label']) === '' ||
                !is_string($item['guidance'] ?? null) ||
                trim($item['guidance']) === '' ||
                !is_array($item['objective_keys'] ?? null) ||
                $item['objective_keys'] === []
            ) {
                throw new InvalidArgumentException(
                    'Los requerimientos contienen información incompleta o duplicada.'
                );
            }

            $keys[$item['key']] = true;
        }

        if ($status === 'sent') {
            return [
                'action' => 'already_sent',
                'version' => $version,
            ];
        }

        if ($status !== 'draft') {
            throw new InvalidArgumentException(
                'Solo puede enviarse un borrador revisado.'
            );
        }

        return [
            'action' => 'send',
            'version' => $version,
        ];
    }
}
