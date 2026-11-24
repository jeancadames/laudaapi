/**
 * Formatos para el portal del cliente: fechas y montos como los lee una
 * persona («23 nov 2026», «RD$3,500.00»), nunca en formato de base de datos.
 */

/** «2026-11-23» o «2026-11-23 14:05:00» → «23 nov 2026». Si no se entiende, se devuelve tal cual. */
export function formatDay(value: string | null | undefined): string {
    if (!value) return '—';
    const day = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? new Date(`${value}T12:00:00`)
        : new Date(value.replace(' ', 'T'));
    if (Number.isNaN(day.getTime())) return value;
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium' }).format(
        day,
    );
}

/** «3500.00», «DOP» → «RD$3,500.00». Si el monto no es numérico, se devuelve con la moneda. */
export function formatMoney(
    amount: string | number | null | undefined,
    currency: string | null | undefined,
): string {
    const value = Number(amount);
    const code = (currency || 'DOP').toUpperCase();
    if (amount === null || amount === undefined || !Number.isFinite(value))
        return amount ? `${amount} ${code}` : '—';
    try {
        return new Intl.NumberFormat('es-DO', {
            style: 'currency',
            currency: code,
        }).format(value);
    } catch {
        return `${value.toFixed(2)} ${code}`;
    }
}

/** Etiquetas de paginación de Laravel («&laquo; Previous») en palabras del cliente. */
export function pageLabel(label: string): string {
    const text = label
        .replace(/&laquo;|&raquo;|«|»/g, '')
        .replace(/<[^>]*>/g, '')
        .trim();
    if (/previous|anterior/i.test(text)) return '‹ Anterior';
    if (/next|siguiente/i.test(text)) return 'Siguiente ›';
    return text;
}
