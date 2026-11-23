import { paidAccess } from '@/lib/businessPackageAccess';
import { formatMoney } from '@/lib/businessPackageCopy';
import { changeDate, replacementStart } from '@/lib/businessPackageManage';
import type { Access, Product, Purchase } from '@/types/businessSelfService';

/**
 * Qué ve el cliente después de confirmar. El servidor decide el estado, las
 * acciones y sus URLs; aquí solo se elige el texto y cuál acción va primero.
 * Ninguna acción se inventa: si el servidor no la envía, no aparece.
 */

type Link = { label: string; url: string };

/** `notice`: estado definitivo sin error (por ejemplo, el período terminó). */
export type PurchaseTone =
    | 'progress'
    | 'action'
    | 'success'
    | 'problem'
    | 'notice';

export type AccessRow = {
    product: Product;
    name: string;
    status: Access['status'];
    badge: string;
    message: string;
    action: Link | null;
};

export type PurchaseView = {
    tone: PurchaseTone;
    title: string;
    message: string;
    /** Acción principal, siempre una del servidor. */
    primary: Link | null;
    /** El estado puede cambiar sin que el cliente haga nada: volver a consultar. */
    waiting: boolean;
    access: AccessRow[];
};

export const productNames: Record<Product, string> = {
    ecf: 'e-CF',
    cumplimiento: 'Cumplimiento',
    pos: 'POS',
    bys: 'BYS',
};

const BADGE: Record<Access['status'], string> = {
    pending: 'Pendiente',
    link_required: 'Falta vincular tu cuenta y empresa',
    consent_required: 'Falta tu conformidad',
    ready: 'Lista para usar',
    unavailable: 'No disponible',
};

/** Orden de prioridad de las acciones de acceso cuando el pago ya está hecho. */
const ACCESS_PRIORITY: Access['status'][] = [
    'link_required',
    'consent_required',
    'ready',
    'unavailable',
];

export function purchaseView(
    purchase: Purchase,
    names: Partial<Record<Product, string>> = {},
): PurchaseView {
    const money = (cents: number) => formatMoney(cents, purchase.currency);
    // Mismo orden en todas las pantallas, aunque el servidor ordene distinto.
    const order = Object.keys(productNames);
    const rank = (code: Product) =>
        order.includes(code) ? order.indexOf(code) : order.length;
    const access: AccessRow[] = [...purchase.access]
        .sort((a, b) => rank(a.product) - rank(b.product))
        .map((item) => ({
            product: item.product,
            name:
                names[item.product] ??
                productNames[item.product] ??
                item.product,
            status: item.status,
            badge: BADGE[item.status],
            message: 'message' in item ? item.message : '',
            action: item.action,
        }));
    const payment = purchase.payment;
    // Compra de reemplazo: el paquete nuevo empieza cuando termina el actual.
    const start = replacementStart(purchase);
    const startDay = start
        ? changeDate(start, 'America/Santo_Domingo', false)
        : null;

    switch (payment.status) {
        case 'preparing':
            return {
                tone: 'progress',
                title: 'Estamos preparando tu pago',
                message:
                    'Tu compra quedó registrada. En unos momentos podrás continuar al pago; todavía no se ha cobrado nada.',
                primary: null,
                waiting: true,
                access,
            };
        case 'payable': {
            // Saldo menor que el total aceptado: ya se aprobó un abono parcial.
            const partial =
                purchase.balance_cents < purchase.summary.due_today.total_cents;
            return {
                tone: 'action',
                title: partial
                    ? 'Recibimos parte del pago'
                    : startDay
                      ? 'Paga la factura de tu nuevo paquete'
                      : 'Tu compra está lista para pagar',
                message: startDay
                    ? `${partial ? `Quedan ${money(purchase.balance_cents)} por pagar` : `Paga ${money(purchase.balance_cents)} por transferencia`} para activar tu nuevo paquete a partir del ${startDay}. Tu paquete actual sigue activo hasta esa fecha.`
                    : partial
                      ? `Quedan ${money(purchase.balance_cents)} por pagar para continuar con la activación de tus aplicaciones.`
                      : `Paga ${money(purchase.balance_cents)} para continuar con la activación de tus aplicaciones.`,
                primary: payment.action,
                waiting: false,
                access,
            };
        }
        case 'processing':
            return {
                tone: 'progress',
                title: 'Estamos confirmando tu pago',
                message:
                    'LAUDA está revisando el pago recibido. No necesitas pagar de nuevo ni enviar otro comprobante; aquí verás cuando se confirme.',
                primary: null,
                waiting: true,
                access,
            };
        case 'failed':
            return {
                tone: 'problem',
                title: 'No pudimos completar el pago',
                message: payment.message,
                primary: payment.action,
                waiting: false,
                access,
            };
        case 'reversed':
            return {
                tone: 'problem',
                title: 'El pago se revirtió',
                message:
                    purchase.balance_cents > 0
                        ? `El pago de esta compra se revirtió y quedan ${money(purchase.balance_cents)} por pagar. Mientras tanto, las aplicaciones de esta compra no están disponibles.`
                        : 'El pago de esta compra se revirtió. Mientras tanto, las aplicaciones de esta compra no están disponibles.',
                primary: null,
                waiting: false,
                access,
            };
        case 'paid':
            return paidView(access, startDay);
    }
}

function paidView(access: AccessRow[], startDay: string | null): PurchaseView {
    const actionable = access.filter((row) => row.action);
    const urls = new Set(actionable.map((row) => row.action?.url));
    // Varias aplicaciones listas, cada una con su «Abrir…»: ninguna va primero.
    const onlyOpenings =
        urls.size > 1 && actionable.every((row) => row.status === 'ready');
    const primary = onlyOpenings
        ? null
        : (ACCESS_PRIORITY.map(
              (status) =>
                  actionable.find((row) => row.status === status)?.action,
          ).find(Boolean) ?? null);
    const view = (
        tone: PurchaseTone,
        title: string,
        message: string,
        waiting = false,
    ): PurchaseView => ({ tone, title, message, primary, waiting, access });
    const state = paidAccess(access);

    switch (state.kind) {
        case 'ready':
            return view(
                'success',
                access.length === 1
                    ? 'Tu aplicación está lista'
                    : 'Tus aplicaciones están listas',
                'Pago recibido y acceso disponible.',
            );
        case 'consent':
            // «LAUDA confirmó el pago» y «ya puedes activar» son momentos distintos.
            return startDay
                ? view(
                      'action',
                      'Ya puedes activar tu nuevo paquete',
                      'LAUDA confirmó el pago. Revisa la implementación para activar tus aplicaciones.',
                  )
                : view('action', 'Pago recibido: falta un paso', STEP_MESSAGE);
        case 'link':
            return view(
                'action',
                startDay
                    ? 'LAUDA confirmó el pago: falta un paso'
                    : 'Pago recibido: falta un paso',
                STEP_MESSAGE,
            );
        case 'waiting': {
            const reason = state.reason ? ` ${state.reason}` : '';
            return startDay
                ? view(
                      'success',
                      'LAUDA confirmó el pago',
                      `Podrás activar tu nuevo paquete a partir del ${startDay}. Hasta entonces sigues usando tu paquete actual.${reason}`,
                  )
                : view(
                      'progress',
                      'Pago recibido',
                      `Estamos activando tus aplicaciones. No necesitas hacer nada más por ahora; aquí verás cuándo puedes abrirlas.${reason}`,
                      true,
                  );
        }
        case 'unavailable': {
            // Sin pendientes: se muestra el motivo de Laravel, sin prometer una activación.
            const blocked = access.filter(
                (row) => row.status === 'unavailable',
            );
            return view(
                'notice',
                state.partial
                    ? blocked.length === 1
                        ? `${blocked[0].name} no está disponible`
                        : 'Algunas aplicaciones no están disponibles'
                    : access.length === 1
                      ? 'Tu aplicación no está disponible'
                      : 'Tus aplicaciones no están disponibles',
                state.reason,
            );
        }
        case 'none':
            return view(
                'action',
                'Pago recibido',
                'Revisa el estado de cada aplicación.',
            );
    }
}

const STEP_MESSAGE =
    'Para abrir tus aplicaciones, completa lo que se indica en cada una. No se te cobrará de nuevo.';
