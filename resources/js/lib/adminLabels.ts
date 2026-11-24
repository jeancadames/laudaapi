/**
 * Etiquetas y colores de estado para el panel de administración. Rojo solo
 * para lo que requiere atención (vencido, error); nunca para «activo» o
 * «pagado».
 */
const LABELS: Record<string, string> = {
    draft: 'Borrador',
    issued: 'Emitida',
    overdue: 'Vencida',
    paid: 'Pagada',
    unpaid: 'Sin pagar',
    void: 'Anulada',
    active: 'Activa',
    trialing: 'En prueba',
    trial: 'En prueba',
    past_due: 'Pago atrasado',
    pending: 'Pendiente',
    pending_payment: 'Pendiente de pago',
    cancelled: 'Cancelada',
    canceled: 'Cancelada',
    expired: 'Vencida',
    card: 'Tarjeta',
    bank_transfer: 'Transferencia',
    cash: 'Efectivo',
    check: 'Cheque',
    other: 'Otro',
};

export function adminLabel(value: string | null | undefined): string {
    const key = String(value ?? '').toLowerCase().trim();
    return LABELS[key] ?? (value || '—');
}

const GOOD = ['paid', 'active', 'accepted', 'converted'];
const WAITING = ['issued', 'pending', 'pending_payment', 'trialing', 'trial', 'unpaid'];
const BAD = ['overdue', 'past_due'];

export function adminTone(value: string | null | undefined): string {
    const key = String(value ?? '').toLowerCase().trim();
    if (GOOD.includes(key))
        return 'border-transparent bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    if (WAITING.includes(key))
        return 'border-transparent bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200';
    if (BAD.includes(key))
        return 'border-transparent bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300';
    return 'border-transparent bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300';
}

/** «2026-11-24T02:20:58.000000Z» → «24 nov 2026, 10:20 p. m.» en la hora local del navegador. */
export function adminDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}
