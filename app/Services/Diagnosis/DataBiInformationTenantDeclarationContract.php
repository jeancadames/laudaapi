<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/**
 * D2F-T4B. Contrato puro de metadatos declarados; no consulta ni persiste datos.
 *
 * $sent: id, status=sent, version, content_sha256 y review_snapshot de la versión publicada.
 * $input: version, declarations (lista; las omisiones se convierten en pending en draft).
 * Declaración: requirement_key, availability?, sources?, notes?.
 * Fuente: type, name?, structures? (lista de nombres), notes?.
 * Submit exige nombres y estructuras para available/partial, y notas para
 * partial/unavailable/unknown. Draft admite fuentes todavía incompletas.
 * El orden de requerimientos proviene del snapshot; fuentes y estructuras se ordenan.
 */
final class DataBiInformationTenantDeclarationContract
{
    private const AVAILABILITIES = ['pending', 'available', 'partial', 'unavailable', 'unknown'];

    private const SOURCE_TYPES = ['software', 'database', 'spreadsheet', 'flat_file', 'api', 'other'];

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** @param array<string,mixed> $sent @param array<string,mixed> $input
     *  @return array<string,mixed> */
    public static function normalize(array $sent, array $input, string $mode): array
    {
        if (!in_array($mode, ['draft', 'submit'], true)) {
            throw new InvalidArgumentException('Modo de declaración inválido.');
        }
        self::keys($input, ['version', 'declarations'], ['version', 'declarations']);
        $requirements = self::publishedRequirements($sent);
        if (($input['version'] ?? null) !== $sent['version']) {
            throw new InvalidArgumentException('La respuesta no corresponde a la versión publicada.');
        }
        $items = self::listOf($input['declarations'], 100);
        $known = array_fill_keys(array_column($requirements, 'key'), true);
        $byKey = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Declaración inválida.');
            }
            self::keys($item, ['requirement_key', 'availability', 'sources', 'notes'], ['requirement_key']);
            $key = $item['requirement_key'];
            if (!is_string($key) || !isset($known[$key]) || isset($byKey[$key])) {
                throw new InvalidArgumentException('Requerimiento ajeno o duplicado.');
            }
            $byKey[$key] = $item;
        }

        $declarations = [];
        foreach ($requirements as $requirement) {
            $key = $requirement['key'];
            $item = $byKey[$key] ?? ['requirement_key' => $key];
            $availability = array_key_exists('availability', $item) ? $item['availability'] : 'pending';
            if (!is_string($availability) || !in_array($availability, self::AVAILABILITIES, true)) {
                throw new InvalidArgumentException('Disponibilidad inválida.');
            }
            $notes = self::text(array_key_exists('notes', $item) ? $item['notes'] : '', 800);
            $sources = self::sources(array_key_exists('sources', $item) ? $item['sources'] : [], $mode);
            if (in_array($availability, ['unavailable', 'unknown'], true) && $sources !== []) {
                throw new InvalidArgumentException('La disponibilidad declarada no admite fuentes.');
            }
            if ($mode === 'submit' && ($availability === 'pending'
                || (in_array($availability, ['available', 'partial'], true) && $sources === [])
                || (in_array($availability, ['partial', 'unavailable', 'unknown'], true) && $notes === ''))) {
                throw new InvalidArgumentException('Debe completar todas las respuestas antes de enviar.');
            }
            $declarations[] = [
                'requirement_key' => $key,
                'availability' => $availability,
                'sources' => $sources,
                'notes' => $notes,
            ];
        }

        $payload = [
            'information_request_id' => $sent['id'],
            'version' => $sent['version'],
            'questionnaire_sha256' => $sent['content_sha256'],
            'declarations' => $declarations,
            'data_inspected' => false,
            'files_received' => false,
        ];
        $payload['content_sha256'] = hash('sha256', json_encode($payload, self::JSON_FLAGS));

        return $payload;
    }

    /** Reconstruye el orden firmado por RequirementReviewContract, incluso tras persistir JSON. */
    private static function publishedRequirements(array $sent): array
    {
        if (!is_int($sent['id'] ?? null) || $sent['id'] < 1
            || ($sent['status'] ?? null) !== 'sent'
            || !is_int($sent['version'] ?? null) || $sent['version'] < 1 || $sent['version'] > 9999
            || !is_string($sent['content_sha256'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/', $sent['content_sha256']) !== 1
            || !is_array($sent['review_snapshot'] ?? null)) {
            throw new InvalidArgumentException('Se requiere una versión publicada válida.');
        }
        $snapshot = $sent['review_snapshot'];
        $fields = ['schema_version', 'purpose', 'origin', 'selected_needs', 'unmapped_need_keys',
            'requirements', 'data_inspected', 'tenant_response_received', 'content_sha256'];
        self::keys($snapshot, $fields, $fields);
        if ($snapshot['schema_version'] !== 1
            || $snapshot['purpose'] !== 'information_source_declaration'
            || $snapshot['origin'] !== 'tenant_business_needs_snapshot'
            || $snapshot['data_inspected'] !== false || $snapshot['tenant_response_received'] !== false
            || !is_string($snapshot['content_sha256'])
            || !hash_equals($sent['content_sha256'], $snapshot['content_sha256'])) {
            throw new InvalidArgumentException('Snapshot declarativo inválido.');
        }

        $needs = [];
        foreach (self::listOf($snapshot['selected_needs'], 100) as $need) {
            if (!is_array($need)) {
                throw new InvalidArgumentException('Objetivo publicado inválido.');
            }
            $fields = ['key', 'label', 'group', 'group_label'];
            self::keys($need, $fields, $fields);
            $normalized = [];
            foreach ($fields as $field) {
                $normalized[$field] = self::snapshotText($need[$field], 191);
            }
            $needs[] = $normalized;
        }
        $unmapped = self::snapshotKeys($snapshot['unmapped_need_keys']);
        $requirements = [];
        $seen = [];
        foreach (self::listOf($snapshot['requirements'], 100) as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Requerimiento publicado inválido.');
            }
            $fields = ['key', 'label', 'guidance', 'objective_keys'];
            self::keys($item, $fields, $fields);
            $key = self::snapshotText($item['key'], 191);
            $objectives = self::snapshotKeys($item['objective_keys']);
            if (isset($seen[$key]) || $objectives === []) {
                throw new InvalidArgumentException('Requerimientos publicados duplicados o sin objetivos.');
            }
            $seen[$key] = true;
            $requirements[] = [
                'key' => $key,
                'label' => self::snapshotText($item['label'], 191),
                'guidance' => self::snapshotText($item['guidance'], 800),
                'objective_keys' => $objectives,
            ];
        }
        if ($requirements === []) {
            throw new InvalidArgumentException('El cuestionario publicado está vacío.');
        }
        $payload = [
            'schema_version' => 1,
            'purpose' => 'information_source_declaration',
            'origin' => 'tenant_business_needs_snapshot',
            'selected_needs' => $needs,
            'unmapped_need_keys' => $unmapped,
            'requirements' => $requirements,
            'data_inspected' => false,
            'tenant_response_received' => false,
        ];
        if (!hash_equals($sent['content_sha256'], hash('sha256', json_encode($payload, self::JSON_FLAGS)))) {
            throw new InvalidArgumentException('El hash no corresponde al contenido del cuestionario.');
        }

        return $requirements;
    }

    private static function sources(mixed $value, string $mode): array
    {
        $sources = [];
        $seen = [];
        foreach (self::listOf($value, 6) as $source) {
            if (!is_array($source)) {
                throw new InvalidArgumentException('Fuente inválida.');
            }
            self::keys($source, ['type', 'name', 'structures', 'notes'], ['type']);
            $type = $source['type'];
            if (!is_string($type) || !in_array($type, self::SOURCE_TYPES, true)) {
                throw new InvalidArgumentException('Tipo de fuente inválido.');
            }
            $name = self::text(array_key_exists('name', $source) ? $source['name'] : '', 191);
            $notes = self::text(array_key_exists('notes', $source) ? $source['notes'] : '', 800);
            $structures = [];
            $structureKeys = [];
            foreach (self::listOf(array_key_exists('structures', $source) ? $source['structures'] : [], 12) as $value) {
                $structure = self::text($value, 191);
                $key = mb_strtolower($structure, 'UTF-8');
                if ($structure === '' || isset($structureKeys[$key])) {
                    throw new InvalidArgumentException('Estructura vacía o duplicada.');
                }
                $structureKeys[$key] = true;
                $structures[] = $structure;
            }
            if ($mode === 'submit' && ($name === '' || $structures === [])) {
                throw new InvalidArgumentException('Debe identificar la fuente y al menos una estructura.');
            }
            $key = $type.':'.mb_strtolower($name, 'UTF-8');
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Fuente duplicada.');
            }
            $seen[$key] = true;
            sort($structures, SORT_STRING);
            $sources[] = ['type' => $type, 'name' => $name, 'structures' => $structures, 'notes' => $notes];
        }
        usort($sources, static fn (array $a, array $b): int => strcmp($a['type'], $b['type']) ?: strcmp($a['name'], $b['name']));

        return $sources;
    }

    private static function keys(array $value, array $allowed, array $required): void
    {
        if (array_diff(array_keys($value), $allowed) || array_diff($required, array_keys($value))) {
            throw new InvalidArgumentException('Hay atributos ajenos o faltan atributos requeridos.');
        }
    }

    private static function listOf(mixed $value, int $max): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > $max) {
            throw new InvalidArgumentException('Lista inválida o fuera del límite admitido.');
        }

        return $value;
    }

    private static function snapshotKeys(mixed $value): array
    {
        $items = self::listOf($value, 100);
        $seen = [];
        foreach ($items as $item) {
            $key = self::snapshotText($item, 191);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Clave publicada duplicada.');
            }
            $seen[$key] = true;
        }

        return $items;
    }

    private static function snapshotText(mixed $value, int $max): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
            || trim($value) === '' || mb_strlen($value, 'UTF-8') > $max) {
            throw new InvalidArgumentException('Texto publicado inválido.');
        }

        return $value;
    }

    /** Solo descripciones: rechaza secretos y formatos evidentes de archivos o registros. */
    private static function text(mixed $value, int $max): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
            || preg_match('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('Texto declarativo inválido o demasiado largo.');
        }
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if (mb_strlen($value, 'UTF-8') > $max || ($max === 191 && str_contains($value, "\n"))) {
            throw new InvalidArgumentException('Texto declarativo inválido o demasiado largo.');
        }
        $forbidden = '~(?:\b(?:password|passwd|pwd|contrase(?:ña|na)|token|api[_ -]?key|secret|client[_ -]?secret|access[_ -]?key|authorization|credential(?:s)?|credenciales)\s*[=:]'
            .'|\bbearer\s+\S+|[a-z][a-z0-9+.-]*://[^\s/]*@'
            .'|\b(?:mysql|postgres(?:ql)?|sqlsrv|mongodb(?:\+srv)?):|\b(?:user(?:name)?|uid)\s*=[^;\n]+;'
            .'|-----BEGIN\b|\bssh-(?:rsa|ed25519)\s+|\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.'
            .'|\bdata:[^\s,]*[,;]|\bbase64\s*[,=:]'
            .'|[{}]|(?:^|\n)\s*\[\s*[\["0-9]|\b(?:select\s+.+\s+from|insert\s+into|update\s+\S+\s+set|delete\s+from|copy\s+.+\s+from)\b'
            .'|(?:^|\n)[^\n]*[,;][^\n]*\n[^\n]*[,;][^\n]*(?:\n|$))~iu';
        if (preg_match($forbidden, $value) === 1) {
            throw new InvalidArgumentException('Solo se admiten metadatos, sin credenciales, archivos ni datos transaccionales.');
        }

        return $value;
    }
}
