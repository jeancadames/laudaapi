<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Services\Diagnosis\DataBiClarificationContract;
use App\Services\Diagnosis\DataBiClarificationTransitionPolicy;
use InvalidArgumentException;

/** Proyección explícita de resultados B2 ya validados; no entrega snapshots o auditoría interna. */
final class DataBiDeclarativeReviewProjection
{
    public static function make(array $review, bool $admin): array
    {
        $open = $review['is_current'] && $review['closure'] === null;
        $kind = $admin ? 'lauda_admin' : 'tenant';
        $items = [];
        $terminal = true;
        foreach ($review['clarifications'] as $state) {
            $terminal = $terminal && in_array($state['status'], ['resolved', 'cancelled'], true);
            $items[] = [
                'id' => $state['id'], 'questionnaire_version' => $state['reference']['questionnaire_version'],
                'requirement_key' => $state['requirement_key'], 'status' => $state['status'], 'revision' => $state['revision'],
                'question' => $state['events'][0]['message'],
                'events' => array_map(static fn (array $event): array => [
                    'action' => $event['action'], 'message' => $event['message'],
                    'actor_type' => $event['actor_kind'], 'occurred_at' => $event['at'],
                ], $state['events']),
                'allowed_actions' => [
                    'answer' => $open && self::may($state['status'], 'answer', $kind),
                    'resolve' => $open && self::may($state['status'], 'resolve', $kind),
                    'cancel' => $open && self::may($state['status'], 'cancel', $kind),
                ],
            ];
        }
        $closure = $review['closure'];

        return [
            'questionnaire_version' => $review['reference']['questionnaire_version'], 'is_current' => $review['is_current'],
            'review_status' => $closure === null ? 'open' : 'closed', 'clarifications' => $items,
            'closure' => $closure === null ? null : [
                'status' => $closure['status'], 'questionnaire_version' => $closure['reference']['questionnaire_version'],
                'closed_at' => $closure['closed_at'], 'conclusion' => $closure['conclusion'], 'limitations' => $closure['limitations'],
                'declared_gaps' => array_map(static fn (array $gap): array => [
                    'requirement_key' => $gap['requirement_key'], 'availability' => $gap['availability'], 'notes' => $gap['notes'],
                ], $closure['declared_gaps']),
            ],
            // Indicadores de interfaz del servidor; B2 sigue decidiendo cada POST bajo lock.
            'allowed_actions' => ['request_clarification' => $admin && $open && count($items) < DataBiClarificationContract::MAX_CLARIFICATIONS,
                'close_review' => $admin && $open && $terminal],
        ];
    }

    private static function may(string $status, string $action, string $kind): bool
    {
        try {
            DataBiClarificationTransitionPolicy::decide($status, $action, $kind);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
