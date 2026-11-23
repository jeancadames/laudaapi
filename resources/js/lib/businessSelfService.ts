import type {
    Catalog,
    Configurator,
    Confirm,
    Preview,
    Problem,
    Purchase,
    Selection,
} from '@/types/businessSelfService';

/** URLs que Laravel entrega en la prop `configurator`. */
export type SelfServiceUrls = Pick<
    Configurator,
    'catalog_url' | 'calculate_url' | 'confirm_url'
>;

export type FailureCode =
    | Problem['code']
    | 'session_expired'
    | 'throttled'
    | 'network'
    | 'unknown';

const DOMAIN_CODES: readonly Problem['code'][] = [
    'price_changed',
    'invalid_selection',
    'existing_purchase',
    'catalog_unavailable',
    'forbidden',
    'purchase_not_found',
    'proposal_expired',
    'transition_required',
    'key_conflict',
    'temporarily_unavailable',
];

const FALLBACK: Record<FailureCode, string> = {
    price_changed: 'El precio cambió. Revisa el resumen actualizado.',
    invalid_selection: 'Revisa tu selección.',
    existing_purchase: 'Ya tienes una compra en curso para esta empresa.',
    catalog_unavailable:
        'Por ahora no hay precios publicados para contratar en línea.',
    forbidden:
        'Solo el propietario o un administrador de la empresa puede contratar.',
    purchase_not_found: 'No encontramos esa compra.',
    proposal_expired:
        'El precio que revisaste venció. Calculamos uno nuevo para que lo revises.',
    transition_required:
        'Tu empresa ya tiene un contrato. Necesitamos calcular el cambio antes de cobrar.',
    key_conflict:
        'No pudimos registrar este intento. Revisa el resumen y vuelve a confirmar.',
    temporarily_unavailable:
        'El servicio no está disponible en este momento. Vuelve a intentarlo en unos minutos.',
    session_expired:
        'Tu sesión venció. Recarga la página e inicia sesión para continuar.',
    throttled:
        'Hiciste varios cambios seguidos. Espera un momento y vuelve a intentarlo.',
    network:
        'No pudimos comunicarnos con LAUDA. Revisa tu conexión y vuelve a intentarlo.',
    unknown: 'No pudimos completar la operación. Vuelve a intentarlo.',
};

/** Fallo normalizado: la interfaz decide por `code`, no por el estado HTTP. */
export class SelfServiceError extends Error {
    constructor(
        readonly code: FailureCode,
        message: string,
        readonly status: number | null = null,
        readonly problem: Problem | null = null,
    ) {
        super(message);
        this.name = 'SelfServiceError';
    }
}

export const isAbort = (failure: unknown): boolean =>
    failure instanceof Error && failure.name === 'AbortError';

export type SelfServiceClient = {
    catalog(signal?: AbortSignal): Promise<Catalog>;
    preview(selection: Selection, signal?: AbortSignal): Promise<Preview>;
    /** Disponible cuando el servidor entrega `confirm_url` (bloque 3). */
    confirm(request: Confirm): Promise<Purchase>;
    /** Estado vigente de una compra: factura, saldo, pago y accesos. */
    purchase(purchaseId: string, signal?: AbortSignal): Promise<Purchase>;
};

/**
 * URL de consulta de una compra. El contrato fija GET `/purchases/{id}` bajo la
 * misma base que `catalog_url`, pero la página no la recibe; se deriva de
 * `confirm_url` (POST `/purchases`) o, si falta, de `catalog_url`.
 */
export function purchaseUrl(
    urls: Pick<SelfServiceUrls, 'catalog_url' | 'confirm_url'>,
    purchaseId: string,
): string {
    const collection =
        urls.confirm_url ??
        urls.catalog_url.replace(/\/catalog$/, '/purchases');
    return `${collection.replace(/\/$/, '')}/${encodeURIComponent(purchaseId)}`;
}

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
/** Identificador de compra válido para la ruta (el servidor exige UUID). */
export const isPurchaseId = (value: unknown): value is string =>
    typeof value === 'string' && UUID.test(value);

function xsrfToken(): string {
    if (typeof document === 'undefined') return '';
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
        ?.slice(11);
    return decodeURIComponent(cookie ?? '');
}

function failure(response: Response, body: unknown): SelfServiceError {
    const problem =
        body &&
        typeof body === 'object' &&
        DOMAIN_CODES.includes((body as Problem).code)
            ? (body as Problem)
            : null;
    const status = response.status;
    // Sesión, CSRF y frecuencia pueden venir del middleware, sin cuerpo propio.
    if (status === 401 || status === 419)
        return new SelfServiceError(
            'session_expired',
            FALLBACK.session_expired,
            status,
        );
    if (status === 429) {
        const wait = Number(response.headers.get('Retry-After'));
        return new SelfServiceError(
            'throttled',
            Number.isInteger(wait) && wait > 0
                ? `Hiciste varios cambios seguidos. Espera ${wait} segundos y vuelve a intentarlo.`
                : FALLBACK.throttled,
            status,
        );
    }
    if (problem)
        return new SelfServiceError(
            problem.code,
            problem.message || FALLBACK[problem.code],
            status,
            problem,
        );
    const code: FailureCode =
        status === 403
            ? 'forbidden'
            : status === 503
              ? 'temporarily_unavailable'
              : 'unknown';
    return new SelfServiceError(code, FALLBACK[code], status);
}

export function createSelfServiceClient(
    urls: SelfServiceUrls,
    fetcher: typeof fetch = (...args) => fetch(...args),
): SelfServiceClient {
    async function request<T>(
        method: 'GET' | 'POST',
        url: string,
        payload?: unknown,
        signal?: AbortSignal,
    ): Promise<T> {
        let response: Response;
        try {
            response = await fetcher(url, {
                method,
                credentials: 'same-origin',
                redirect: 'error',
                cache: 'no-store',
                signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(method === 'POST'
                        ? {
                              'Content-Type': 'application/json',
                              'X-XSRF-TOKEN': xsrfToken(),
                          }
                        : {}),
                },
                body: method === 'POST' ? JSON.stringify(payload) : undefined,
            });
        } catch (error) {
            if (isAbort(error)) throw error;
            throw new SelfServiceError('network', FALLBACK.network);
        }
        const body = await response.json().catch(() => null);
        if (response.ok && body) return body as T;
        throw failure(response, body);
    }

    return {
        catalog: (signal) =>
            request<Catalog>('GET', urls.catalog_url, undefined, signal),
        preview: (selection, signal) =>
            request<Preview>('POST', urls.calculate_url, selection, signal),
        confirm: (body) => {
            if (!urls.confirm_url)
                return Promise.reject(
                    new SelfServiceError(
                        'temporarily_unavailable',
                        'La contratación en línea todavía no está disponible.',
                    ),
                );
            return request<Purchase>('POST', urls.confirm_url, body);
        },
        purchase: (purchaseId, signal) =>
            request<Purchase>(
                'GET',
                purchaseUrl(urls, purchaseId),
                undefined,
                signal,
            ),
    };
}
