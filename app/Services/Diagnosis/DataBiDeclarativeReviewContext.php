<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * T4F-B1: contexto puro e inmutable, construido exclusivamente con datos del servidor.
 * B2 deberá cargar la última publicación y la declaración completas, scoped y bajo lock.
 * No autentica recursos consultando BD ni sustituye las comprobaciones del futuro servicio.
 */
final readonly class DataBiDeclarativeReviewContext
{
    private array $reference;

    private array $snapshot;

    private string $submittedAt;

    private bool $current;

    public function __construct(
        array $request,
        private array $published,
        ?array $declaration,
        array $latestPublished,
    ) {
        foreach ([$published, $latestPublished] as $sent) {
            if (! is_int($sent['version'] ?? null)) {
                throw new InvalidArgumentException('Versión publicada inválida.');
            }
            DataBiTenantDeclarationPersistencePolicy::assertPublished($request, $sent, $sent['version']);
            DataBiInformationTenantDeclarationContract::normalize($sent, [
                'version' => $sent['version'], 'declarations' => [],
            ], 'draft');
        }
        if ($declaration === null || ($declaration['status'] ?? null) !== 'submitted'
            || ! is_int($declaration['id'] ?? null) || $declaration['id'] < 1) {
            throw new InvalidArgumentException('Se requiere una declaración enviada válida.');
        }
        $this->snapshot = DataBiTenantDeclarationPersistencePolicy::storedSnapshot($published, $declaration);
        $publicationAt = self::instant($published['sent_at']);
        $this->submittedAt = self::instant($declaration['submitted_at']);
        if ($this->submittedAt < $publicationAt || $latestPublished['version'] < $published['version']
            || ($latestPublished['version'] > $published['version'] && $latestPublished['id'] === $published['id'])
            || self::instant($latestPublished['sent_at']) < $publicationAt
            || ($latestPublished['version'] === $published['version']
                && ($latestPublished['id'] !== $published['id']
                    || $latestPublished['content_sha256'] !== $published['content_sha256']
                    || self::instant($latestPublished['sent_at']) !== $publicationAt
                    || $latestPublished['sent_by_user_id'] !== $published['sent_by_user_id']))) {
            throw new InvalidArgumentException('Referencias o fechas de publicación inconsistentes.');
        }
        $this->current = $latestPublished['id'] === $published['id']
            && $latestPublished['version'] === $published['version'];
        $this->reference = [
            'implementation_request_id' => $request['id'],
            'company_id' => $request['company_id'],
            'information_request_id' => $published['id'],
            'questionnaire_version' => $published['version'],
            'questionnaire_sha256' => $published['content_sha256'],
            'questionnaire_published_at' => $publicationAt,
            'questionnaire_published_by_user_id' => $published['sent_by_user_id'],
            'declaration_id' => $declaration['id'],
            'declaration_revision' => $declaration['revision'],
            'declaration_sha256' => $declaration['content_sha256'],
            'declaration_submitted_at' => $this->submittedAt,
            'declaration_submitted_by_user_id' => $declaration['submitted_by_user_id'],
        ];
    }

    public function reference(): array
    {
        return $this->reference;
    }

    public function declarations(): array
    {
        return $this->snapshot['declarations'];
    }

    public function submittedAt(): string
    {
        return $this->submittedAt;
    }

    public function isCurrent(): bool
    {
        return $this->current;
    }

    public function assertCurrent(mixed $version): void
    {
        if (! $this->current || $version !== $this->reference['questionnaire_version']) {
            throw new InvalidArgumentException('El cuestionario publicado fue sustituido o la versión es incorrecta.');
        }
    }

    public function requirement(mixed $key): string
    {
        if (is_string($key) && in_array($key, array_column($this->snapshot['declarations'], 'requirement_key'), true)) {
            return $key;
        }
        throw new InvalidArgumentException('El requerimiento no pertenece a la declaración enviada.');
    }

    /** Reutiliza el filtro declarativo T4B, sin modificar su normalización ni sus hashes. */
    public function text(mixed $value, ?string $requirementKey = null): string
    {
        $key = $this->requirement($requirementKey ?? $this->snapshot['declarations'][0]['requirement_key']);
        $normalized = DataBiInformationTenantDeclarationContract::normalize($this->published, [
            'version' => $this->reference['questionnaire_version'],
            'declarations' => [['requirement_key' => $key, 'notes' => $value]],
        ], 'draft');
        $text = array_column($normalized['declarations'], 'notes', 'requirement_key')[$key];
        if ($text === '') {
            throw new InvalidArgumentException('Debe indicar un texto declarativo de 1 a 800 caracteres.');
        }

        return $text;
    }

    public static function keys(array $input, array $fields): void
    {
        if (array_diff(array_keys($input), $fields) || array_diff($fields, array_keys($input))) {
            throw new InvalidArgumentException('Hay atributos desconocidos o faltan atributos requeridos.');
        }
    }

    /** UTC SQL/ISO/serialización Eloquent con microsegundos cero; precisión contractual: segundos. */
    public static function instant(mixed $value): string
    {
        if (is_string($value) && ! str_contains($value, "\0")) {
            foreach (['Y-m-d\TH:i:s\Z', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s.000000\Z', 'Y-m-d\TH:i:s\+00:00'] as $format) {
                $date = DateTimeImmutable::createFromFormat('!'.$format, $value, new DateTimeZone('UTC'));
                if ($date !== false && $date->format($format) === $value) {
                    return $date->format('Y-m-d\TH:i:s\Z');
                }
            }
        }
        throw new InvalidArgumentException('La auditoría requiere una fecha UTC válida.');
    }

    public static function seal(array $payload): array
    {
        $payload['content_sha256'] = hash('sha256', json_encode(self::ordered($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $payload;
    }

    /** Solo ordena claves de objetos; conserva tipos y orden de listas. */
    public static function ordered(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::ordered($item);
            }
        }
        unset($item);
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
