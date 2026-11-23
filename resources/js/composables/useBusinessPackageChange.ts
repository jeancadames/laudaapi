import { computed, ref, shallowRef } from 'vue';
import {
    appRows,
    sameProducts,
    type ChangeReview,
    type ManageClient,
    type ManagePlan,
    type RegisteredChange,
} from '@/lib/businessPackageManage';
import { isAbort, SelfServiceError } from '@/lib/businessSelfService';
import type { Product } from '@/types/businessSelfService';

/**
 * Editar → revisar → confirmar un cambio del paquete.
 *
 * - Nada cambia hasta confirmar; la revisión no emite factura.
 * - Un 409 al confirmar significa que el precio o la fecha cambió, que ya
 *   había un cambio registrado o que el servidor lo impide. Se vuelve a pedir
 *   la revisión para distinguirlo; nunca se confirma un precio no revisado.
 * - Sin respuesta al confirmar, no se sabe si quedó registrado: repetir la
 *   misma confirmación es seguro porque Laravel devuelve el cambio existente.
 */
export type ChangeNotice = 'price_changed' | 'already_registered' | null;

export function useBusinessPackageChange(options: {
    plan: ManagePlan;
    client: ManageClient;
    /** Vuelve a leer la página (prop `plan`) y devuelve el cambio registrado, si existe. */
    reloadChange: () => Promise<RegisteredChange | null>;
}) {
    const { plan, client } = options;
    const selection = ref<Product[]>([...plan.current.products]);
    const review = shallowRef<ChangeReview | null>(null);
    const saved = shallowRef<RegisteredChange | null>(plan.change);
    const accepted = ref(false);
    const busy = ref<'review' | 'cancel' | 'confirm' | null>(null);
    const error = shallowRef<SelfServiceError | null>(null);
    /** Se envió la confirmación y no llegó respuesta. */
    const uncertain = ref(false);
    const notice = ref<ChangeNotice>(null);

    const rows = computed(() => appRows(plan, selection.value));
    const changed = computed(
        () => !sameProducts(selection.value, plan.current.products),
    );
    const reviewRows = computed(() =>
        review.value ? appRows(plan, review.value.products) : [],
    );
    const savedRows = computed(() =>
        saved.value ? appRows(plan, saved.value.products) : [],
    );

    function fail(failure: unknown) {
        if (isAbort(failure)) return;
        error.value =
            failure instanceof SelfServiceError
                ? failure
                : new SelfServiceError(
                      'unknown',
                      'No pudimos completar la operación. Vuelve a intentarlo.',
                  );
    }

    async function requestReview(
        products: Product[],
        kind: 'review' | 'cancel',
    ) {
        if (busy.value || saved.value) return;
        busy.value = kind;
        error.value = null;
        notice.value = null;
        try {
            review.value = await client.review(products);
            accepted.value = false;
        } catch (failure) {
            fail(failure);
            await adoptRegistered(failure);
        } finally {
            busy.value = null;
        }
    }

    /** Revisar la selección marcada (sin cambios no hay nada que revisar). */
    const reviewSelection = () =>
        changed.value
            ? requestReview([...selection.value], 'review')
            : Promise.resolve();
    /** Baja total: lista vacía. */
    const reviewCancellation = () => requestReview([], 'cancel');

    /** Si el servidor dice que ya hay un cambio, se muestra ese cambio. */
    async function adoptRegistered(failure: unknown) {
        if (
            !(failure instanceof SelfServiceError) ||
            failure.code !== 'conflict'
        )
            return false;
        const existing = await options.reloadChange().catch(() => null);
        if (!existing) return false;
        saved.value = existing;
        review.value = null;
        error.value = null;
        uncertain.value = false;
        notice.value = 'already_registered';
        return true;
    }

    async function confirm() {
        const current = review.value;
        if (busy.value || saved.value || !current || !accepted.value) return;
        busy.value = 'confirm';
        error.value = null;
        notice.value = null;
        try {
            saved.value = await client.confirm(current);
            review.value = null;
            uncertain.value = false;
        } catch (failure) {
            if (
                failure instanceof SelfServiceError &&
                failure.code === 'network'
            ) {
                uncertain.value = true;
                fail(failure);
            } else if (
                failure instanceof SelfServiceError &&
                failure.code === 'conflict'
            ) {
                uncertain.value = false;
                await afterConflict(current, failure);
            } else {
                uncertain.value = false;
                fail(failure);
            }
        } finally {
            busy.value = null;
        }
    }

    async function afterConflict(
        previous: ChangeReview,
        conflict: SelfServiceError,
    ) {
        try {
            const fresh = await client.review(previous.products);
            if (fresh.terms_hash !== previous.terms_hash) {
                review.value = fresh;
                accepted.value = false;
                notice.value = 'price_changed';
                return;
            }
            error.value = conflict;
        } catch (failure) {
            if (!(await adoptRegistered(failure))) fail(failure);
        }
    }

    function edit() {
        if (busy.value) return;
        review.value = null;
        accepted.value = false;
        error.value = null;
        uncertain.value = false;
        notice.value = null;
    }

    function toggle(code: Product) {
        if (busy.value || review.value || saved.value) return;
        const row = rows.value.find((item) => item.code === code);
        if (!row || (row.locked && !selection.value.includes(code))) return;
        selection.value = selection.value.includes(code)
            ? selection.value.filter((item) => item !== code)
            : [...selection.value, code];
    }

    return {
        selection,
        rows,
        changed,
        review,
        reviewRows,
        saved,
        savedRows,
        accepted,
        busy,
        error,
        uncertain,
        notice,
        toggle,
        reviewSelection,
        reviewCancellation,
        confirm,
        edit,
    };
}
