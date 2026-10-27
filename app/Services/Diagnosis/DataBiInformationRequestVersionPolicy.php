<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/** Regla pura de concurrencia e idempotencia, independiente de MySQL. */
final class DataBiInformationRequestVersionPolicy
{
    /** @param array{status:string,version:int,content_sha256:string}|null $latest
     *  @return array{action:string,version:int} */
    public static function decide(?array $latest, string $contentHash, int $expectedVersion): array
    {
        if ($expectedVersion < 0 || !preg_match('/\A[a-f0-9]{64}\z/D', $contentHash)) {
            throw new InvalidArgumentException('Versión esperada o huella de borrador inválida.');
        }
        if ($latest === null) {
            if ($expectedVersion !== 0) {
                throw new InvalidArgumentException('Conflicto: se esperaba una versión previa inexistente.');
            }
            return ['action' => 'append', 'version' => 1];
        }
        $version = $latest['version'] ?? null;
        $status = $latest['status'] ?? null;
        $previousHash = $latest['content_sha256'] ?? null;
        if (!is_int($version) || $version < 1 || $version >= 10000
            || !is_string($previousHash) || !preg_match('/\A[a-f0-9]{64}\z/D', $previousHash)
            || !is_string($status)) {
            throw new InvalidArgumentException('Última versión corrupta o límite de versiones alcanzado.');
        }
        if ($status !== 'draft') {
            throw new InvalidArgumentException('No se puede editar una versión enviada o cerrada.');
        }
        if (hash_equals($previousHash, $contentHash)) {
            // Retry idéntico: no insertar otra versión, aun si reenvía su versión original.
            if ($expectedVersion > $version) {
                throw new InvalidArgumentException('Versión esperada posterior a la actual.');
            }
            return ['action' => 'unchanged', 'version' => $version];
        }
        if ($expectedVersion !== $version) {
            throw new InvalidArgumentException('Conflicto de edición: hay un borrador más reciente.');
        }
        return ['action' => 'append', 'version' => $version + 1];
    }
}
