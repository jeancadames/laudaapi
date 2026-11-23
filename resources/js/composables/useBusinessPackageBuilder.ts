import { computed, ref } from 'vue';
import type { FailureCode, SelfServiceClient } from '@/lib/businessSelfService';
import { isAbort, SelfServiceError } from '@/lib/businessSelfService';
import type {
    Catalog,
    Cycle,
    Level,
    Preview,
    Product,
    Purchase,
    Selection,
} from '@/types/businessSelfService';

type KeyValueStore = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;

type Stored = {
    products?: unknown;
    level?: unknown;
    cycle?: unknown;
    confirm?: { proposal_ref?: unknown; key?: unknown } | null;
};

export type PackageBuilderOptions = {
    catalog: Catalog;
    client: SelfServiceClient;
    /** sessionStorage en el navegador; null en SSR y en pruebas sin persistencia. */
    storage?: KeyValueStore | null;
    /** Separa la selección guardada por empresa. */
    storageScope?: string | number;
    debounceMs?: number;
    newKey?: () => string;
};

export type PackageFailure = {
    code: FailureCode;
    message: string;
    /** Mensajes por campo devueltos en `invalid_selection`. */
    details: string[];
    /** Productos señalados por `fields['products.N']`. */
    invalidProducts: Product[];
};

const sameSelection = (a: Selection, b: Selection) =>
    a.cycle === b.cycle &&
    a.level === b.level &&
    a.products.length === b.products.length &&
    a.products.every((code) => b.products.includes(code));

function failureOf(failure: unknown, requested?: Selection): PackageFailure {
    if (!(failure instanceof SelfServiceError))
        return {
            code: 'unknown',
            message: 'No pudimos completar la operación. Vuelve a intentarlo.',
            details: [],
            invalidProducts: [],
        };
    const fields =
        failure.problem?.code === 'invalid_selection'
            ? failure.problem.fields
            : {};
    const invalidProducts: Product[] = [];
    for (const key of Object.keys(fields)) {
        // Convención Laravel: `products.N` apunta al elemento N de la selección enviada.
        const index = /^products\.(\d+)$/.exec(key)?.[1];
        const code =
            index === undefined ? undefined : requested?.products[+index];
        if (code && !invalidProducts.includes(code)) invalidProducts.push(code);
    }
    return {
        code: failure.code,
        message: failure.message,
        details: [...new Set(Object.values(fields).flat())],
        invalidProducts,
    };
}

export function useBusinessPackageBuilder(options: PackageBuilderOptions) {
    const { catalog, client } = options;
    const storage = options.storage ?? null;
    const debounceMs = options.debounceMs ?? 250;
    const newKey = options.newKey ?? (() => crypto.randomUUID());
    const storageKey = `lauda.business.package.v1.${options.storageScope ?? 'default'}`;

    const selectable = catalog.products.map((product) => product.code);
    const defaultLevel = catalog.levels[0]?.code ?? 'initial';
    const defaultCycle = catalog.cycles[0] ?? 'monthly';

    function readStored(): Stored {
        try {
            const parsed = JSON.parse(storage?.getItem(storageKey) ?? 'null');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch {
            return {};
        }
    }

    /** Solo se conserva lo que el catálogo cargado todavía ofrece. */
    function sanitize(candidate: Stored | Selection | null): Selection {
        const products = Array.isArray(candidate?.products)
            ? selectable.filter((code) =>
                  (candidate.products as unknown[]).includes(code),
              )
            : [];
        const level = catalog.levels.find(
            (value) => value.code === candidate?.level,
        )?.code;
        const cycle = catalog.cycles.find(
            (value) => value === candidate?.cycle,
        );
        return {
            products,
            level: level ?? defaultLevel,
            cycle: cycle ?? defaultCycle,
        };
    }

    const stored = readStored();
    const initial = sanitize(stored);

    const selected = ref<Product[]>(initial.products);
    const level = ref<Level>(initial.level);
    const cycle = ref<Cycle>(initial.cycle);
    const proposal = ref<Preview | null>(null);
    const calculating = ref(false);
    const calculationError = ref<PackageFailure | null>(null);
    /** El servidor aplicó una selección distinta de la pedida. */
    const adjusted = ref(false);

    const accepted = ref(false);
    const confirming = ref(false);
    const confirmError = ref<PackageFailure | null>(null);
    /** Resumen anterior cuando el precio cambió al confirmar. */
    const priceChange = ref<{ previous: Preview } | null>(null);
    const purchase = ref<Purchase | null>(null);

    let confirmKey: { proposal_ref: string; key: string } | null =
        typeof stored.confirm?.proposal_ref === 'string' &&
        typeof stored.confirm?.key === 'string'
            ? {
                  proposal_ref: stored.confirm.proposal_ref,
                  key: stored.confirm.key,
              }
            : null;

    const selection = computed<Selection>(() => ({
        products: selected.value,
        level: level.value,
        cycle: cycle.value,
    }));

    /** La propuesta visible corresponde exactamente a lo que está marcado. */
    const proposalIsCurrent = computed(
        () =>
            !calculating.value &&
            proposal.value !== null &&
            sameSelection(proposal.value.selection, selection.value),
    );

    function persist() {
        try {
            storage?.setItem(
                storageKey,
                JSON.stringify({ ...selection.value, confirm: confirmKey }),
            );
        } catch {
            // Sin almacenamiento la selección no sobrevive a una recarga, nada más.
        }
    }

    let sequence = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let inFlight: AbortController | null = null;

    function resetReview() {
        accepted.value = false;
        confirmError.value = null;
        priceChange.value = null;
    }

    function recalculate(immediate = false) {
        // Cada cambio invalida lo anterior: se cancela el temporizador, se aborta
        // la petición en curso y su respuesta se ignora aunque llegue después.
        const current = ++sequence;
        clearTimeout(timer);
        inFlight?.abort();
        inFlight = null;
        calculationError.value = null;
        adjusted.value = false;
        resetReview();

        if (selected.value.length === 0) {
            calculating.value = false;
            proposal.value = null;
            return;
        }
        calculating.value = true;
        const requested: Selection = {
            ...selection.value,
            products: [...selected.value],
        };
        const run = async () => {
            const controller = new AbortController();
            inFlight = controller;
            try {
                const result = await client.preview(
                    requested,
                    controller.signal,
                );
                if (current !== sequence) return;
                apply(result, requested);
            } catch (error) {
                if (current !== sequence || isAbort(error)) return;
                proposal.value = null;
                calculationError.value = failureOf(error, requested);
            } finally {
                if (current === sequence) {
                    calculating.value = false;
                    inFlight = null;
                }
            }
        };
        if (immediate || debounceMs <= 0) void run();
        else timer = setTimeout(run, debounceMs);
    }

    function apply(result: Preview, requested: Selection) {
        proposal.value = result;
        if (!sameSelection(result.selection, requested)) {
            const applied = sanitize(result.selection);
            selected.value = applied.products;
            level.value = applied.level;
            cycle.value = applied.cycle;
            adjusted.value = true;
            persist();
        }
    }

    function toggle(code: Product) {
        if (confirming.value || !selectable.includes(code)) return;
        const next = selected.value.includes(code)
            ? selected.value.filter((value) => value !== code)
            : [...selected.value, code];
        // Orden del catálogo, para que la selección sea estable.
        selected.value = selectable.filter((value) => next.includes(value));
        persist();
        recalculate();
    }

    function setCycle(value: Cycle) {
        if (
            confirming.value ||
            value === cycle.value ||
            !catalog.cycles.includes(value)
        )
            return;
        cycle.value = value;
        persist();
        recalculate();
    }

    async function confirm() {
        const reviewed = proposal.value;
        if (
            confirming.value ||
            purchase.value ||
            !accepted.value ||
            !proposalIsCurrent.value ||
            !reviewed
        )
            return;
        confirming.value = true;
        confirmError.value = null;
        // Una clave por intento lógico: reintentos y recargas de esta propuesta
        // reutilizan la misma; una propuesta nueva recibe otra.
        if (confirmKey?.proposal_ref !== reviewed.proposal_ref) {
            confirmKey = { proposal_ref: reviewed.proposal_ref, key: newKey() };
            persist();
        }
        try {
            purchase.value = await client.confirm({
                proposal_ref: reviewed.proposal_ref,
                accepted: true,
                idempotency_key: confirmKey.key,
            });
            try {
                storage?.removeItem(storageKey);
            } catch {
                // La compra ya vive en el servidor.
            }
        } catch (error) {
            const problem =
                error instanceof SelfServiceError ? error.problem : null;
            if (problem?.code === 'price_changed') {
                // Se conserva la selección y se pide aceptar el nuevo importe.
                apply(problem.replacement, selection.value);
                accepted.value = false;
                priceChange.value = { previous: reviewed };
            } else if (problem?.code === 'proposal_expired') {
                recalculate(true);
                confirmError.value = failureOf(error);
            } else {
                const result = failureOf(error);
                confirmError.value =
                    result.code === 'network'
                        ? {
                              ...result,
                              message:
                                  'No pudimos confirmar la respuesta. Puedes volver a intentarlo: si la compra ya se registró, recuperaremos la misma.',
                          }
                        : result;
            }
        } finally {
            confirming.value = false;
        }
    }

    if (selected.value.length) recalculate(true);

    return {
        selected,
        level,
        cycle,
        selection,
        proposal,
        proposalIsCurrent,
        calculating,
        calculationError,
        adjusted,
        accepted,
        confirming,
        confirmError,
        priceChange,
        purchase,
        toggle,
        setCycle,
        retry: () => recalculate(true),
        confirm,
        /** Detiene temporizador y petición al desmontar la pantalla. */
        dispose: () => {
            sequence++;
            clearTimeout(timer);
            inFlight?.abort();
        },
    };
}
