<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/** No reabre ni edita eventos. answered sigue abierta hasta resolución o cancelación. */
final class DataBiClarificationTransitionPolicy
{
    public static function decide(?string $status, string $action, string $actorKind): string
    {
        $target = match ([$status, $action, $actorKind]) {
            [null, 'request', 'lauda_admin'] => 'requested',
            ['requested', 'answer', 'tenant'] => 'answered',
            ['answered', 'resolve', 'lauda_admin'] => 'resolved',
            ['requested', 'cancel', 'lauda_admin'],
            ['answered', 'cancel', 'lauda_admin'] => 'cancelled',
            default => null,
        };
        if ($target === null) {
            throw new InvalidArgumentException('Transición de aclaración incompatible con su estado o actor.');
        }

        return $target;
    }
}
