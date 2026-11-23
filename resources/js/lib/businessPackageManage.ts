import { paidAccess } from '@/lib/businessPackageAccess';
import { selfServiceRequest } from '@/lib/businessSelfService';
import type {
    Cycle,
    Preview,
    Product,
    Purchase,
} from '@/types/businessSelfService';

/**
 * «Administrar mi paquete» (contrato-v1.md, «Administrar un paquete activado»).
 * Estos tipos todavía no están en contrato-v1.ts; copian la respuesta de
 * BusinessPackageChanges::page() y ::review(). Laravel decide precio, ciclo y
 * fecha; aquí solo se comparan listas de aplicaciones para nombrarlas.
 */
export type ChangeReview = {
    /** Selección completa del próximo período; vacía = baja total. */
    products: Product[];
    /** Fin del acceso actual y comienzo del paquete nuevo (ISO 8601). */
    effective_at: string;
    /** Importe por ciclo del paquete nuevo; 0 en la baja total. */
    total_cents: number;
    terms_hash: string;
};

export type RegisteredChange = ChangeReview & {
    /** Compra con la factura del paquete nuevo (null en la baja total). */
    purchase_url?: string | null;
};

export type ManagePlan = {
    current: { products: Product[]; cycle: Cycle };
    available: Product[];
    names: Partial<Record<Product, string>>;
    company: string;
    timezone: string;
    change: RegisteredChange | null;
    preview_url: string;
    confirm_url: string;
    back_url: string;
};

export type AppFate = 'kept' | 'added' | 'removed' | 'excluded';

export type AppRow = {
    code: Product;
    name: string;
    fate: AppFate;
    /** No se puede agregar: no está en el catálogo publicado para esta empresa. */
    locked: boolean;
};

/** Orden estable: el del catálogo publicado y luego el del paquete contratado. */
export function productOrder(
    plan: Pick<ManagePlan, 'available' | 'names' | 'current'>,
): Product[] {
    return [
        ...new Set([
            ...plan.available,
            ...plan.current.products,
            ...(Object.keys(plan.names) as Product[]),
        ]),
    ];
}

/** Qué pasa con cada aplicación si el próximo período tiene `next`. */
export function appRows(
    plan: Pick<ManagePlan, 'available' | 'names' | 'current'>,
    next: readonly Product[],
): AppRow[] {
    const current = plan.current.products;
    return productOrder(plan).map((code) => {
        const now = current.includes(code);
        const later = next.includes(code);
        return {
            code,
            name: plan.names[code] ?? code,
            fate: now
                ? later
                    ? 'kept'
                    : 'removed'
                : later
                  ? 'added'
                  : 'excluded',
            locked: !now && !plan.available.includes(code),
        };
    });
}

export const sameProducts = (a: readonly Product[], b: readonly Product[]) =>
    a.length === b.length && a.every((code) => b.includes(code));

export const FATE_LABEL: Record<AppFate, string> = {
    kept: 'Se conserva',
    added: 'Se agrega',
    removed: 'Se da de baja',
    excluded: 'No incluida',
};

/** Fecha y hora en la zona horaria del contrato; la zona se nombra solo si difiere de la del navegador. */
export function changeDate(
    iso: string,
    timeZone: string,
    withTime = true,
): string {
    const local = (() => {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch {
            return timeZone;
        }
    })();
    return new Intl.DateTimeFormat('es-DO', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        ...(withTime ? { hour: 'numeric', minute: '2-digit' } : {}),
        ...(withTime && local !== timeZone ? { timeZoneName: 'short' } : {}),
        timeZone,
    }).format(new Date(iso));
}

/** Identificador de la compra nueva dentro de `purchase_url` (`?compra=<uuid>`). */
export function purchaseIdFrom(url: string | null | undefined): string | null {
    if (!url) return null;
    const value = new URL(url, 'http://lauda.local').searchParams.get('compra');
    return value && /^[0-9a-f-]{36}$/i.test(value) ? value : null;
}

/**
 * Inicio del paquete nuevo en una compra de reemplazo. Laravel lo guarda en el
 * resumen de la compra (`summary.effective_at`), pero el contrato aún no lo tipa.
 */
export function replacementStart(purchase: Purchase): string | null {
    if (!purchase.previous_package_url) return null;
    const value = (purchase.summary as Preview & { effective_at?: unknown })
        .effective_at;
    return typeof value === 'string' && !Number.isNaN(Date.parse(value))
        ? value
        : null;
}

export type ManageClient = {
    review(products: Product[], signal?: AbortSignal): Promise<ChangeReview>;
    confirm(review: ChangeReview): Promise<RegisteredChange>;
};

export function createManageClient(
    urls: Pick<ManagePlan, 'preview_url' | 'confirm_url'>,
    fetcher: typeof fetch = (...args) => fetch(...args),
): ManageClient {
    return {
        review: (products, signal) =>
            selfServiceRequest<ChangeReview>(
                fetcher,
                'POST',
                urls.preview_url,
                { products },
                signal,
            ),
        // Repetir la misma confirmación devuelve el cambio ya registrado (no crea otro).
        confirm: (review) =>
            selfServiceRequest<RegisteredChange>(
                fetcher,
                'POST',
                urls.confirm_url,
                {
                    products: review.products,
                    terms_hash: review.terms_hash,
                    accepted: true,
                },
            ),
    };
}

export type StepState = 'todo' | 'waiting' | 'done' | 'problem';
export type ChangeStep = {
    state: StepState;
    text: string;
    action: { label: string; url: string } | null;
};

/**
 * Después de confirmar un cambio con aplicaciones: el pago de la factura nueva
 * y la activación son pasos distintos. «LAUDA confirmó el pago» no significa
 * que ya se pueda activar: eso depende de la fecha y de los vínculos, y lo
 * indica el servidor con las acciones de acceso.
 */
export function changeProgress(
    purchase: Purchase,
    startText: string,
    money: (cents: number) => string,
    names: Partial<Record<Product, string>> = {},
): { payment: ChangeStep; activation: ChangeStep } {
    const payment = purchase.payment;
    const pay: ChangeStep = (() => {
        switch (payment.status) {
            case 'preparing':
                return {
                    state: 'waiting',
                    text: 'Estamos preparando la factura.',
                    action: null,
                };
            case 'payable':
                return {
                    state: 'todo',
                    text: `Paga ${money(purchase.balance_cents)} por transferencia y envía el comprobante.`,
                    action: payment.action,
                };
            case 'processing':
                return {
                    state: 'waiting',
                    text: 'Recibimos tu comprobante. LAUDA está revisando el pago; no necesitas enviarlo otra vez.',
                    action: null,
                };
            case 'failed':
                return {
                    state: 'problem',
                    text: payment.message,
                    action: payment.action,
                };
            case 'reversed':
                return {
                    state: 'problem',
                    text: 'El pago se revirtió. Comunícate con LAUDA.',
                    action: null,
                };
            case 'paid':
                return {
                    state: 'done',
                    text: 'LAUDA confirmó el pago.',
                    action: null,
                };
        }
    })();
    if (payment.status !== 'paid')
        return {
            payment: pay,
            activation: {
                state: 'todo',
                text: `Podrás activarlo a partir del ${startText}, cuando LAUDA confirme el pago.`,
                action: null,
            },
        };
    // Misma lectura que «Ver mi paquete» (paidAccess): mismo motivo en ambas pantallas.
    const state = paidAccess(
        purchase.access.map((row) => ({
            ...row,
            name: names[row.product] ?? row.product,
        })),
    );
    const waiting = `Podrás activarlo a partir del ${startText}. Hasta entonces sigues usando tu paquete actual.`;
    const activation: ChangeStep = (() => {
        switch (state.kind) {
            case 'ready':
                return {
                    state: 'done',
                    text: 'Tu nuevo paquete está activo.',
                    action: null,
                };
            case 'consent':
                return {
                    state: 'todo',
                    text: 'Ya puedes activar tu nuevo paquete.',
                    action: state.action,
                };
            case 'link':
                return {
                    state: 'todo',
                    text: 'Falta vincular tu cuenta y empresa en una aplicación nueva.',
                    action: state.action,
                };
            case 'waiting':
                return {
                    state: 'waiting',
                    text: state.reason ? `${waiting} ${state.reason}` : waiting,
                    action: null,
                };
            case 'unavailable':
                return { state: 'problem', text: state.reason, action: null };
            case 'none':
                return { state: 'waiting', text: waiting, action: null };
        }
    })();
    return { payment: pay, activation };
}
