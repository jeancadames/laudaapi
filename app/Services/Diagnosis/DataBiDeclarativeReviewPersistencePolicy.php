<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/** T4F-B2: adaptación pura entre B1, revisiones/idempotencia y columnas persistidas. */
final class DataBiDeclarativeReviewPersistencePolicy
{
    public static function request(DataBiDeclarativeReviewContext $context, DataBiDeclarativeReviewActor $actor,
        array $input, int $expectedRevision, int $id, string $at, array $history, ?array $closure): array
    {
        self::input($context, $actor, $input, ['version', 'requirement_key', 'message'], $expectedRevision);
        $history = self::history($context, $history, $closure);
        $key = $context->requirement($input['requirement_key']);
        $message = $context->text($input['message'], $key);
        foreach ($history as $state) {
            if ($state['requirement_key'] === $key
                && mb_strtolower($state['events'][0]['message'], 'UTF-8') === mb_strtolower($message, 'UTF-8')) {
                if (self::retry($state, 0, 'request', $actor, $message, $expectedRevision)) {
                    return ['action' => 'unchanged', 'snapshot' => $state];
                }
                throw new InvalidArgumentException('Solicitud duplicada o revisión incompatible.');
            }
        }
        if ($expectedRevision !== 0) {
            throw new InvalidArgumentException('Conflicto: una aclaración nueva requiere revisión cero.');
        }

        return ['action' => 'create', 'snapshot' => DataBiClarificationContract::request(
            $context, $actor, $input, $id, $at, $history, $closure)];
    }

    public static function transition(DataBiDeclarativeReviewContext $context, DataBiDeclarativeReviewActor $actor,
        array $history, int $id, string $action, array $input, int $expectedRevision, string $at, ?array $closure): array
    {
        self::input($context, $actor, $input, ['version', 'message'], $expectedRevision);
        $history = self::history($context, $history, $closure);
        $state = null;
        foreach ($history as $item) {
            if ($item['id'] === $id) {
                $state = $item;
            }
        }
        if ($state === null || ! in_array($action, ['answer', 'resolve', 'cancel'], true)) {
            throw new InvalidArgumentException('Aclaración o acción no disponible en esta declaración.');
        }
        $message = $context->text($input['message'], $state['requirement_key']);
        foreach ($state['events'] as $index => $event) {
            if (self::retry($state, $index, $action, $actor, $message, $expectedRevision)) {
                return ['action' => 'unchanged', 'snapshot' => $state];
            }
        }
        if ($expectedRevision !== $state['revision']) {
            throw new InvalidArgumentException('Conflicto: la aclaración tiene otra revisión.');
        }

        return ['action' => 'append', 'snapshot' => DataBiClarificationContract::transition(
            $context, $state, $actor, $action, $input, $at, $closure)];
    }

    public static function close(DataBiDeclarativeReviewContext $context, DataBiDeclarativeReviewActor $actor,
        array $input, int $expectedRevision, string $at, array $history, ?array $closure): array
    {
        self::input($context, $actor, $input, ['version', 'conclusion', 'limitations'], $expectedRevision);
        $history = self::history($context, $history, $closure);
        if ($closure !== null) {
            // Revalidar B1 con la fecha ORIGINAL: el reintento no inventa otra auditoría.
            $candidate = DataBiDeclarativeReviewClosureContract::close($context, $actor, $input,
                $closure['closed_at'], $history);
            if (in_array($expectedRevision, [0, 1], true) && $candidate['content_sha256'] === $closure['content_sha256']) {
                return ['action' => 'unchanged', 'snapshot' => $closure];
            }
            throw new InvalidArgumentException('El cierre existente es inmutable.');
        }
        if ($expectedRevision !== 0) {
            throw new InvalidArgumentException('Conflicto: todavía no existe un cierre.');
        }

        return ['action' => 'create', 'snapshot' => DataBiDeclarativeReviewClosureContract::close(
            $context, $actor, $input, $at, $history)];
    }

    /** Tupla exacta B1: clave + pregunta normalizada sin distinción de mayúsculas. */
    public static function questionHash(string $key, string $message): string
    {
        return hash('sha256', json_encode([$key, mb_strtolower($message, 'UTF-8')], JSON_THROW_ON_ERROR));
    }

    public static function scopeColumns(array $reference): array
    {
        return ['transformation_implementation_request_id' => $reference['implementation_request_id'],
            'company_id' => $reference['company_id'], 'data_bi_information_request_id' => $reference['information_request_id'],
            'data_bi_tenant_declaration_id' => $reference['declaration_id']];
    }

    public static function clarificationRow(array $state): array
    {
        $first = $state['events'][0];
        $last = $state['events'][count($state['events']) - 1];

        return [...self::scopeColumns($state['reference']), 'id' => $state['id'],
            'requirement_key' => $state['requirement_key'],
            'question_sha256' => self::questionHash($state['requirement_key'], $first['message']),
            'status' => $state['status'], 'revision' => $state['revision'],
            'clarification_snapshot' => $state, 'content_sha256' => $state['content_sha256'],
            'created_by_user_id' => $first['actor_user_id'], 'updated_by_user_id' => $last['actor_user_id'],
            'created_at' => $first['at'], 'updated_at' => $last['at']];
    }

    /** Cada fila firma el prefijo B1 y el hash del evento anterior; nunca se reconstruye guardando. */
    public static function eventRows(DataBiDeclarativeReviewContext $context, array $state): array
    {
        $state = DataBiClarificationContract::validate($context, $state);
        $rows = [];
        $previous = null;
        $status = null;
        foreach ($state['events'] as $index => $event) {
            $status = DataBiClarificationTransitionPolicy::decide($status, $event['action'], $event['actor_kind']);
            $prefix = $state;
            unset($prefix['content_sha256']);
            $prefix['events'] = array_slice($state['events'], 0, $index + 1);
            $prefix['status'] = $status;
            $prefix['revision'] = $index + 1;
            $prefix = DataBiDeclarativeReviewContext::seal($prefix);
            $row = DataBiDeclarativeReviewContext::seal([
                'data_bi_declaration_clarification_id' => $state['id'], 'sequence' => $index + 1,
                'action' => $event['action'], 'message' => $event['message'],
                'actor_user_id' => $event['actor_user_id'], 'actor_kind' => $event['actor_kind'],
                'occurred_at' => $event['at'], 'previous_sha256' => $previous,
                'projection_sha256' => $prefix['content_sha256'],
            ]);
            $previous = $row['content_sha256'];
            $rows[] = $row;
        }

        return $rows;
    }

    public static function storedClarification(DataBiDeclarativeReviewContext $context, array $row, array $events): array
    {
        if (! is_array($row['clarification_snapshot'] ?? null)) {
            throw new InvalidArgumentException('Falta la proyección de la aclaración.');
        }
        $state = DataBiClarificationContract::validate($context, $row['clarification_snapshot']);
        self::columns($row, self::clarificationRow($state), ['created_at', 'updated_at']);
        $expectedEvents = self::eventRows($context, $state);
        if (! array_is_list($events) || count($events) !== count($expectedEvents)) {
            throw new InvalidArgumentException('El historial no coincide con la proyección de la aclaración.');
        }
        foreach ($expectedEvents as $index => $expected) {
            if (! is_array($events[$index])) {
                throw new InvalidArgumentException('Evento persistido inválido.');
            }
            self::columns($events[$index], $expected, ['occurred_at']);
        }

        return $state;
    }

    public static function closureRow(array $closure): array
    {
        return [...self::scopeColumns($closure['reference']), 'conclusion' => $closure['conclusion'],
            'limitations' => $closure['limitations'], 'declared_gaps' => $closure['declared_gaps'],
            'declared_limitations' => $closure['declared_limitations'],
            'clarification_references' => $closure['clarifications'], 'closure_snapshot' => $closure,
            'content_sha256' => $closure['content_sha256'], 'closed_by_user_id' => $closure['closed_by_user_id'],
            'closed_at' => $closure['closed_at']];
    }

    public static function storedClosure(DataBiDeclarativeReviewContext $context, array $row, array $history): array
    {
        if (! is_array($row['closure_snapshot'] ?? null)) {
            throw new InvalidArgumentException('Falta el snapshot de cierre.');
        }
        $closure = DataBiDeclarativeReviewClosureContract::validate($context, $row['closure_snapshot'], $history);
        self::columns($row, self::closureRow($closure), ['closed_at']);

        return $closure;
    }

    private static function columns(array $actual, array $expected, array $times): void
    {
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual)) {
                throw new InvalidArgumentException('Proyección o auditoría persistida incompleta.');
            }
            $stored = $actual[$key];
            if (in_array($key, $times, true)) {
                $stored = DataBiDeclarativeReviewContext::instant($stored);
            }
            if (is_array($value) && is_array($stored)) {
                $value = DataBiDeclarativeReviewContext::ordered($value);
                $stored = DataBiDeclarativeReviewContext::ordered($stored);
            }
            if ($stored !== $value) {
                throw new InvalidArgumentException('Historial, referencias o auditoría persistida inconsistentes.');
            }
        }
    }

    private static function input(DataBiDeclarativeReviewContext $context, DataBiDeclarativeReviewActor $actor,
        array $input, array $keys, int $expected): void
    {
        DataBiDeclarativeReviewContext::keys($input, $keys);
        $context->assertCurrent($input['version']);
        $actor->assertScope($context);
        if ($expected < 0 || $expected > 3) {
            throw new InvalidArgumentException('Revisión de persistencia inválida.');
        }
    }

    private static function history(DataBiDeclarativeReviewContext $context, array $history, ?array $closure): array
    {
        $history = DataBiClarificationContract::collection($context, $history);
        if ($closure !== null) {
            DataBiDeclarativeReviewClosureContract::validate($context, $closure, $history);
        }

        return $history;
    }

    private static function retry(array $state, int $index, string $action, DataBiDeclarativeReviewActor $actor,
        string $message, int $expected): bool
    {
        $event = $state['events'][$index];

        return $event['action'] === $action && $event['actor_user_id'] === $actor->userId
            && $event['actor_kind'] === $actor->kind && $event['message'] === $message
            && ($expected === $index || ($index === $state['revision'] - 1 && $expected === $state['revision']));
    }
}
