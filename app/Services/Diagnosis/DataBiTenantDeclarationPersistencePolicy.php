<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/** D2F-T4C. Decisiones puras; el servicio obtiene y bloquea el estado persistido. */
final class DataBiTenantDeclarationPersistencePolicy
{
    public const MAX_REVISION = 4294967295;

    /** version pertenece al cuestionario; expectedRevision pertenece a su declaración. */
    public static function assertPublished(array $request, array $sent, int $version): void
    {
        if (!is_int($request['id'] ?? null) || $request['id'] < 1
            || !is_int($request['company_id'] ?? null) || $request['company_id'] < 1
            || ($request['capability_key'] ?? null) !== 'data_transformation_bi'
            || ($sent['transformation_implementation_request_id'] ?? null) !== $request['id']
            || ($sent['company_id'] ?? null) !== $request['company_id']
            || ($sent['status'] ?? null) !== 'sent'
            || !is_string($sent['sent_at'] ?? null) || trim($sent['sent_at']) === ''
            || !is_int($sent['sent_by_user_id'] ?? null) || $sent['sent_by_user_id'] < 1
            || !is_int($sent['version'] ?? null) || $sent['version'] < 1 || $sent['version'] > 9999
            || $sent['version'] !== $version) {
            throw new InvalidArgumentException('Cuestionario no publicado, ajeno o versión publicada sustituida.');
        }
    }

    /** @return array{action:string,revision:int,status:string} */
    public static function decide(?array $current, string $hash, int $expectedRevision, string $mode): array
    {
        if (!in_array($mode, ['draft', 'submit'], true)
            || preg_match('/\A[a-f0-9]{64}\z/', $hash) !== 1
            || $expectedRevision < 0 || $expectedRevision > self::MAX_REVISION) {
            throw new InvalidArgumentException('Modo, hash o revisión esperada inválidos.');
        }
        $status = $mode === 'submit' ? 'submitted' : 'draft';
        if ($current === null) {
            if ($expectedRevision !== 0) {
                throw new InvalidArgumentException('Conflicto: todavía no existe esa revisión.');
            }

            return ['action' => 'create', 'revision' => 1, 'status' => $status];
        }
        $revision = $current['revision'] ?? null;
        $storedStatus = $current['status'] ?? null;
        $storedHash = $current['content_sha256'] ?? null;
        if (!is_int($revision) || $revision < 1 || $revision > self::MAX_REVISION
            || !in_array($storedStatus, ['draft', 'submitted'], true)
            || !is_string($storedHash) || preg_match('/\A[a-f0-9]{64}\z/', $storedHash) !== 1) {
            throw new InvalidArgumentException('Estado persistido de la declaración inválido.');
        }
        $sameHash = hash_equals($storedHash, $hash);
        if ($storedStatus === 'submitted' && ($status !== 'submitted' || !$sameHash)) {
            throw new InvalidArgumentException('La declaración enviada es inmutable.');
        }
        // Una revisión anterior solo sirve para repetir exactamente la última operación.
        if ($sameHash && $storedStatus === $status
            && in_array($expectedRevision, [$revision, $revision - 1], true)) {
            return ['action' => 'unchanged', 'revision' => $revision, 'status' => $storedStatus];
        }
        if ($expectedRevision !== $revision) {
            throw new InvalidArgumentException('Conflicto: la declaración tiene una revisión más reciente.');
        }
        if ($revision === self::MAX_REVISION) {
            throw new InvalidArgumentException('Se alcanzó el límite de revisiones.');
        }

        return ['action' => 'update', 'revision' => $revision + 1, 'status' => $status];
    }

    /** Valida de nuevo el snapshot guardado, tolerando solo reordenación de claves JSON. */
    public static function storedSnapshot(array $sent, array $current): array
    {
        $snapshot = $current['declaration_snapshot'] ?? null;
        if (!is_array($snapshot)
            || !is_int($current['created_by_user_id'] ?? null) || $current['created_by_user_id'] < 1
            || !is_int($current['updated_by_user_id'] ?? null) || $current['updated_by_user_id'] < 1) {
            throw new InvalidArgumentException('Snapshot o auditoría de la declaración inválidos.');
        }
        $status = $current['status'] ?? null;
        $revision = $current['revision'] ?? null;
        if (!is_int($revision)) {
            throw new InvalidArgumentException('Revisión persistida inválida.');
        }
        if (($status === 'draft' && (($current['submitted_at'] ?? null) !== null
                || ($current['submitted_by_user_id'] ?? null) !== null))
            || ($status === 'submitted' && (!is_string($current['submitted_at'] ?? null)
                || trim($current['submitted_at']) === ''
                || !is_int($current['submitted_by_user_id'] ?? null) || $current['submitted_by_user_id'] < 1))) {
            throw new InvalidArgumentException('Auditoría de envío inconsistente.');
        }
        $normalized = DataBiInformationTenantDeclarationContract::normalize($sent, [
            'version' => $snapshot['version'] ?? null,
            'declarations' => $snapshot['declarations'] ?? null,
        ], $status === 'submitted' ? 'submit' : 'draft');
        self::decide($current, $normalized['content_sha256'], $revision,
            $status === 'submitted' ? 'submit' : 'draft');
        if (self::ordered($snapshot) !== self::ordered($normalized)
            || !hash_equals($current['content_sha256'], $normalized['content_sha256'])
            || ($current['data_bi_information_request_id'] ?? null) !== $sent['id']
            || ($current['company_id'] ?? null) !== $sent['company_id']) {
            throw new InvalidArgumentException('La declaración guardada no corresponde al cuestionario o fue alterada.');
        }

        return $normalized;
    }

    private static function ordered(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::ordered($item);
            }
        }
        unset($item);
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
