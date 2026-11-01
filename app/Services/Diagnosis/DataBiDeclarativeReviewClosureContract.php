<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/** Cierre declarativo gratuito; ninguna decisión comercial o comprobación técnica. */
final class DataBiDeclarativeReviewClosureContract
{
    public static function assertEligible(DataBiDeclarativeReviewContext $context, array $clarifications, ?array $existingClosure = null): void
    {
        $context->assertCurrent($context->reference()['questionnaire_version']);
        if ($existingClosure !== null) {
            throw new InvalidArgumentException('El cierre declarativo histórico es inmutable.');
        }
        self::terminalClarifications($context, $clarifications);
    }

    /** input: version, conclusion, limitations. Nunca recibe identidades, brechas ni auditoría. */
    public static function close(
        DataBiDeclarativeReviewContext $context,
        DataBiDeclarativeReviewActor $actor,
        array $input,
        string $at,
        array $clarifications,
        ?array $existingClosure = null,
    ): array {
        $actor->assertScope($context);
        if ($actor->kind !== 'lauda_admin') {
            throw new InvalidArgumentException('Solo Admin LAUDA puede cerrar la revisión declarativa.');
        }
        $context->assertCurrent($input['version'] ?? null);
        self::assertEligible($context, $clarifications, $existingClosure);

        return self::snapshot($context, $actor->userId, $input, $at, $clarifications);
    }

    /** Verificación histórica sin reinterpretar el cierre como vigente para una versión nueva. */
    public static function validate(DataBiDeclarativeReviewContext $context, array $closure, array $clarifications): array
    {
        DataBiDeclarativeReviewContext::keys($closure, ['schema_version', 'purpose', 'status', 'reference', 'conclusion',
            'limitations', 'declared_gaps', 'declared_limitations', 'clarifications', 'closed_by_user_id',
            'closed_at', 'data_inspected', 'technical_validation_performed', 'commercial_approval', 'content_sha256']);
        if (! is_int($closure['closed_by_user_id']) || $closure['closed_by_user_id'] < 1 || ! is_string($closure['closed_at'])) {
            throw new InvalidArgumentException('Auditoría de cierre incompleta.');
        }
        $normalized = self::snapshot($context, $closure['closed_by_user_id'], [
            'version' => $context->reference()['questionnaire_version'],
            'conclusion' => $closure['conclusion'], 'limitations' => $closure['limitations'],
        ], $closure['closed_at'], $clarifications);
        if (DataBiDeclarativeReviewContext::ordered($closure) !== DataBiDeclarativeReviewContext::ordered($normalized)) {
            throw new InvalidArgumentException('El cierre o sus referencias históricas fueron alterados.');
        }

        return $normalized;
    }

    public static function appliesToCurrentVersion(DataBiDeclarativeReviewContext $context, array $closure, array $clarifications): bool
    {
        self::validate($context, $closure, $clarifications);

        return $context->isCurrent();
    }

    private static function terminalClarifications(DataBiDeclarativeReviewContext $context, array $items): array
    {
        $items = DataBiClarificationContract::collection($context, $items);
        foreach ($items as $item) {
            if (! in_array($item['status'], ['resolved', 'cancelled'], true)) {
                throw new InvalidArgumentException('Existen aclaraciones abiertas para esta declaración.');
            }
        }

        return $items;
    }

    private static function snapshot(DataBiDeclarativeReviewContext $context, int $actorId, array $input, string $at, array $clarifications): array
    {
        DataBiDeclarativeReviewContext::keys($input, ['version', 'conclusion', 'limitations']);
        if ($input['version'] !== $context->reference()['questionnaire_version']) {
            throw new InvalidArgumentException('La conclusión no corresponde a la versión de la declaración.');
        }
        $at = DataBiDeclarativeReviewContext::instant($at);
        $minimumAt = $context->submittedAt();
        $references = [];
        foreach (self::terminalClarifications($context, $clarifications) as $item) {
            $minimumAt = max($minimumAt, $item['events'][count($item['events']) - 1]['at']);
            $references[] = ['id' => $item['id'], 'revision' => $item['revision'],
                'status' => $item['status'], 'content_sha256' => $item['content_sha256']];
        }
        if ($at < $minimumAt) {
            throw new InvalidArgumentException('El cierre no puede preceder al envío ni a la atención de aclaraciones.');
        }
        $gaps = [];
        $limitations = [];
        foreach ($context->declarations() as $declaration) {
            if ($declaration['availability'] !== 'available') {
                $gaps[] = ['requirement_key' => $declaration['requirement_key'],
                    'availability' => $declaration['availability'], 'notes' => $declaration['notes']];
            }
            $sourceNotes = [];
            foreach ($declaration['sources'] as $source) {
                if ($source['notes'] !== '') {
                    $sourceNotes[] = ['type' => $source['type'], 'name' => $source['name'], 'notes' => $source['notes']];
                }
            }
            if ($declaration['notes'] !== '' || $sourceNotes !== []) {
                $limitations[] = ['requirement_key' => $declaration['requirement_key'],
                    'notes' => $declaration['notes'], 'source_notes' => $sourceNotes];
            }
        }

        return DataBiDeclarativeReviewContext::seal([
            'schema_version' => 1, 'purpose' => 'declarative_review_closure', 'status' => 'closed', 'reference' => $context->reference(),
            'conclusion' => $context->text($input['conclusion']), 'limitations' => $context->text($input['limitations']),
            'declared_gaps' => $gaps, 'declared_limitations' => $limitations, 'clarifications' => $references,
            'closed_by_user_id' => $actorId, 'closed_at' => $at,
            'data_inspected' => false, 'technical_validation_performed' => false, 'commercial_approval' => false,
        ]);
    }
}
