<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/**
 * T4F-B1. Entidad independiente: request -> answer -> resolve/cancel, sin editar historia.
 * id/actor/at/context/history/closure son datos del servidor. input solo contiene texto y versión.
 * B2 debe entregar la colección COMPLETA de la declaración bajo el mismo lock que el cierre.
 */
final class DataBiClarificationContract
{
    public const MAX_CLARIFICATIONS = 1000;

    public static function request(
        DataBiDeclarativeReviewContext $context,
        DataBiDeclarativeReviewActor $actor,
        array $input,
        int $id,
        string $at,
        array $history = [],
        ?array $closure = null,
    ): array {
        DataBiDeclarativeReviewContext::keys($input, ['version', 'requirement_key', 'message']);
        self::assertOpen($context, $actor, $input['version'], $closure);
        $status = DataBiClarificationTransitionPolicy::decide(null, 'request', $actor->kind);
        $key = $context->requirement($input['requirement_key']);
        $message = $context->text($input['message'], $key);
        $existing = self::collection($context, $history);
        if ($id < 1 || count($existing) >= self::MAX_CLARIFICATIONS) {
            throw new InvalidArgumentException('Identificador o cantidad de aclaraciones inválidos.');
        }
        foreach ($existing as $item) {
            if ($item['id'] === $id || ($item['requirement_key'] === $key
                && mb_strtolower($item['events'][0]['message'], 'UTF-8') === mb_strtolower($message, 'UTF-8'))) {
                throw new InvalidArgumentException('Solicitud de aclaración duplicada.');
            }
        }
        $state = self::snapshot($context, $id, $key, $status, [self::event('request', $actor, $message, $at)]);

        return self::validate($context, $state);
    }

    public static function transition(
        DataBiDeclarativeReviewContext $context,
        array $current,
        DataBiDeclarativeReviewActor $actor,
        string $action,
        array $input,
        string $at,
        ?array $closure = null,
    ): array {
        DataBiDeclarativeReviewContext::keys($input, ['version', 'message']);
        self::assertOpen($context, $actor, $input['version'], $closure);
        $state = self::validate($context, $current);
        $status = DataBiClarificationTransitionPolicy::decide($state['status'], $action, $actor->kind);
        $events = [...$state['events'], self::event($action, $actor,
            $context->text($input['message'], $state['requirement_key']), $at)];

        return self::validate($context, self::snapshot($context, $state['id'], $state['requirement_key'], $status, $events));
    }

    /** Lectura histórica: no exige que esta sea la última versión publicada. */
    public static function validate(DataBiDeclarativeReviewContext $context, array $state): array
    {
        DataBiDeclarativeReviewContext::keys($state, ['schema_version', 'id', 'reference', 'requirement_key',
            'status', 'revision', 'events', 'content_sha256']);
        if ($state['schema_version'] !== 1 || ! is_int($state['id']) || $state['id'] < 1
            || ! is_array($state['reference'])
            || DataBiDeclarativeReviewContext::ordered($state['reference']) !== DataBiDeclarativeReviewContext::ordered($context->reference())
            || ! is_array($state['events']) || ! array_is_list($state['events'])
            || count($state['events']) < 1 || count($state['events']) > 3) {
            throw new InvalidArgumentException('Aclaración ajena o historial inválido.');
        }
        $key = $context->requirement($state['requirement_key']);
        $status = null;
        $at = $context->submittedAt();
        $events = [];
        foreach ($state['events'] as $event) {
            if (! is_array($event)) {
                throw new InvalidArgumentException('Evento de aclaración inválido.');
            }
            DataBiDeclarativeReviewContext::keys($event, ['action', 'actor_user_id', 'actor_kind', 'at', 'message']);
            if (! is_int($event['actor_user_id']) || $event['actor_user_id'] < 1
                || ! is_string($event['action']) || ! is_string($event['actor_kind'])) {
                throw new InvalidArgumentException('Auditoría de aclaración incompleta.');
            }
            $status = DataBiClarificationTransitionPolicy::decide($status, $event['action'], $event['actor_kind']);
            $nextAt = DataBiDeclarativeReviewContext::instant($event['at']);
            if ($nextAt < $at) {
                throw new InvalidArgumentException('Los eventos no pueden preceder al envío ni a su historia.');
            }
            $at = $nextAt;
            $events[] = ['action' => $event['action'], 'actor_user_id' => $event['actor_user_id'],
                'actor_kind' => $event['actor_kind'], 'at' => $at, 'message' => $context->text($event['message'], $key)];
        }
        $normalized = self::snapshot($context, $state['id'], $key, $status, $events);
        if (DataBiDeclarativeReviewContext::ordered($state) !== DataBiDeclarativeReviewContext::ordered($normalized)) {
            throw new InvalidArgumentException('La aclaración o su historia fueron alteradas.');
        }

        return $normalized;
    }

    public static function collection(DataBiDeclarativeReviewContext $context, array $items): array
    {
        if (! array_is_list($items) || count($items) > self::MAX_CLARIFICATIONS) {
            throw new InvalidArgumentException('Colección de aclaraciones inválida.');
        }
        $normalized = [];
        $questions = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Aclaración inválida.');
            }
            $state = self::validate($context, $item);
            $question = json_encode([$state['requirement_key'], mb_strtolower($state['events'][0]['message'], 'UTF-8')], JSON_THROW_ON_ERROR);
            if (isset($normalized[$state['id']]) || isset($questions[$question])) {
                throw new InvalidArgumentException('Aclaraciones duplicadas.');
            }
            $normalized[$state['id']] = $state;
            $questions[$question] = true;
        }
        ksort($normalized, SORT_NUMERIC);

        return array_values($normalized);
    }

    private static function assertOpen(DataBiDeclarativeReviewContext $context, DataBiDeclarativeReviewActor $actor, mixed $version, ?array $closure): void
    {
        $actor->assertScope($context);
        $context->assertCurrent($version);
        if ($closure !== null) {
            throw new InvalidArgumentException('La revisión ya tiene un cierre; su historia no puede reabrirse.');
        }
    }

    private static function event(string $action, DataBiDeclarativeReviewActor $actor, string $message, string $at): array
    {
        return ['action' => $action, 'actor_user_id' => $actor->userId, 'actor_kind' => $actor->kind,
            'at' => DataBiDeclarativeReviewContext::instant($at), 'message' => $message];
    }

    private static function snapshot(DataBiDeclarativeReviewContext $context, int $id, string $key, string $status, array $events): array
    {
        return DataBiDeclarativeReviewContext::seal(['schema_version' => 1, 'id' => $id,
            'reference' => $context->reference(), 'requirement_key' => $key, 'status' => $status,
            'revision' => count($events), 'events' => $events]);
    }
}
