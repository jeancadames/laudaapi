import type { Access } from '@/types/businessSelfService';

type Link = { label: string; url: string };
type Row = Pick<Access, 'status' | 'action'> & {
    message?: string;
    /** Nombre visible; nombra la aplicación cuando solo una tiene un motivo. */
    name?: string;
};

/**
 * Lectura única de los accesos de una compra pagada. La usan «Ver mi paquete»
 * y «Administrar mi paquete» para dar la misma explicación.
 *
 * - `waiting`: alguna aplicación sigue pendiente; se puede prometer activar.
 * - `unavailable`: nada pendiente y al menos una aplicación no disponible.
 *   El motivo es el de Laravel («El período contratado para este paquete
 *   terminó.», «La conexión… requiere revisión de LAUDA.», …); nunca se
 *   promete una activación futura.
 */
export type PaidAccess =
    | { kind: 'ready' }
    | { kind: 'consent'; action: Link }
    | { kind: 'link'; action: Link }
    | { kind: 'waiting'; reason: string | null }
    | { kind: 'unavailable'; reason: string; partial: boolean }
    | { kind: 'none' };

const messageOf = (row: Row) =>
    'message' in row && typeof row.message === 'string' ? row.message : '';

/** El motivo común de Laravel; si cada aplicación tiene uno distinto, se remite al detalle. */
export function unavailableReason(rows: Row[]): string {
    const reasons = [...new Set(rows.map(messageOf).filter(Boolean))];
    if (reasons.length === 1) return reasons[0];
    return reasons.length
        ? 'Revisa el motivo indicado en cada aplicación.'
        : 'Una aplicación requiere revisión de LAUDA.';
}

export function paidAccess(access: Row[]): PaidAccess {
    if (!access.length) return { kind: 'none' };
    if (access.every((row) => row.status === 'ready')) return { kind: 'ready' };
    const withAction = (status: Access['status']) =>
        access.find((row) => row.status === status && row.action)?.action;
    const consent = withAction('consent_required');
    if (consent) return { kind: 'consent', action: consent };
    const link = withAction('link_required');
    if (link) return { kind: 'link', action: link };
    const blocked = access.filter((row) => row.status === 'unavailable');
    if (access.some((row) => row.status === 'pending'))
        return {
            kind: 'waiting',
            // Junto a la espera, el motivo debe decir de qué aplicación se trata.
            reason:
                blocked.length === 1 && blocked[0].name && messageOf(blocked[0])
                    ? `${blocked[0].name}: ${messageOf(blocked[0])}`
                    : blocked.length
                      ? unavailableReason(blocked)
                      : null,
        };
    if (blocked.length)
        return {
            kind: 'unavailable',
            reason: unavailableReason(blocked),
            partial: blocked.length < access.length,
        };
    return { kind: 'none' };
}
