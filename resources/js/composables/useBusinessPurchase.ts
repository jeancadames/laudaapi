import { computed, ref, shallowRef } from 'vue';
import { purchaseView } from '@/lib/businessPurchaseStatus';
import type { FailureCode, SelfServiceClient } from '@/lib/businessSelfService';
import { isAbort, SelfServiceError } from '@/lib/businessSelfService';
import type { Product, Purchase } from '@/types/businessSelfService';

export type PurchaseOptions = {
    client: Pick<SelfServiceClient, 'purchase'>;
    purchaseId: string;
    /** Compra recién devuelta por la confirmación: se muestra sin esperar. */
    initial?: Purchase | null;
    names?: Partial<Record<Product, string>>;
    /**
     * Pausas entre consultas automáticas mientras el estado puede cambiar solo
     * (preparando el enlace, confirmando el pago, activando). Al agotarse se
     * deja de consultar y queda el botón «Actualizar estado».
     */
    pollDelaysMs?: number[];
};

/** 1 minuto cada 5 s y luego cada 15 s hasta unos 10 minutos (≤ 12/min, límite 60/min). */
export const DEFAULT_POLL_DELAYS = [
    ...Array<number>(12).fill(5_000),
    ...Array<number>(36).fill(15_000),
];

export type PurchaseFailure = { code: FailureCode; message: string };

const failureOf = (error: unknown): PurchaseFailure =>
    error instanceof SelfServiceError
        ? { code: error.code, message: error.message }
        : {
              code: 'unknown',
              message: 'No pudimos consultar tu compra. Vuelve a intentarlo.',
          };

export function useBusinessPurchase(options: PurchaseOptions) {
    const { client, purchaseId } = options;
    const delays = options.pollDelaysMs ?? DEFAULT_POLL_DELAYS;

    const purchase = shallowRef<Purchase | null>(options.initial ?? null);
    const loading = ref(false);
    /** Sin compra que mostrar (no existe, sin permiso, sin conexión al cargar). */
    const error = ref<PurchaseFailure | null>(null);
    /** Falló una actualización, pero se sigue mostrando el último estado conocido. */
    const staleError = ref<PurchaseFailure | null>(null);
    /** Se dejó de consultar automáticamente mientras el estado seguía en espera. */
    const stalled = ref(false);
    const checkedAt = ref<Date | null>(options.initial ? new Date() : null);

    const view = computed(() =>
        purchase.value ? purchaseView(purchase.value, options.names) : null,
    );

    let controller: AbortController | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let attempt = 0;
    let disposed = false;

    function schedule() {
        clearTimeout(timer);
        if (disposed || !view.value?.waiting) {
            stalled.value = false;
            return;
        }
        const delay = delays[attempt];
        if (delay === undefined) {
            stalled.value = true;
            return;
        }
        attempt++;
        timer = setTimeout(() => void load(), delay);
    }

    async function load() {
        clearTimeout(timer);
        controller?.abort();
        const current = new AbortController();
        controller = current;
        loading.value = true;
        try {
            const result = await client.purchase(purchaseId, current.signal);
            if (controller !== current) return;
            purchase.value = result;
            error.value = null;
            staleError.value = null;
            checkedAt.value = new Date();
        } catch (failure) {
            if (controller !== current || isAbort(failure)) return;
            const problem = failureOf(failure);
            // Una compra inexistente o ajena no conserva un estado anterior.
            if (
                !purchase.value ||
                problem.code === 'purchase_not_found' ||
                problem.code === 'forbidden'
            ) {
                purchase.value = null;
                error.value = problem;
            } else staleError.value = problem;
        } finally {
            if (controller === current) {
                loading.value = false;
                controller = null;
                if (!error.value) schedule();
            }
        }
    }

    /** Consulta inmediata a petición del cliente; reinicia las consultas automáticas. */
    function refresh() {
        if (disposed) return;
        attempt = 0;
        stalled.value = false;
        void load();
    }

    if (purchase.value) schedule();
    else void load();

    return {
        purchase,
        view,
        loading,
        error,
        staleError,
        stalled,
        checkedAt,
        refresh,
        dispose() {
            disposed = true;
            clearTimeout(timer);
            controller?.abort();
        },
    };
}
