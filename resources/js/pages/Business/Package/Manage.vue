<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    Check,
    CircleAlert,
    CircleCheck,
    Clock,
    LoaderCircle,
    Minus,
    Plus,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';
import { useBusinessPackageChange } from '@/composables/useBusinessPackageChange';
import { useBusinessPurchase } from '@/composables/useBusinessPurchase';
import AppLayout from '@/layouts/AppLayout.vue';
import { cycleCopy, formatMoney, joinNames } from '@/lib/businessPackageCopy';
import {
    changeDate,
    changeProgress,
    periodFacts,
    createManageClient,
    FATE_LABEL,
    productOrder,
    purchaseIdFrom,
    type AppFate,
    type ManagePlan,
    type RegisteredChange,
    type StepState,
} from '@/lib/businessPackageManage';
import { createSelfServiceClient } from '@/lib/businessSelfService';
import {
    catalog as catalogRoute,
    confirm as purchasesRoute,
    preview as calculateRoute,
} from '@/routes/business/package';

const props = defineProps<{ plan: ManagePlan }>();
const plan = props.plan;

const change = useBusinessPackageChange({
    plan,
    client: createManageClient(plan),
    reloadChange: () =>
        new Promise<RegisteredChange | null>((resolve) => {
            let found: RegisteredChange | null = null;
            router.reload({
                only: ['plan'],
                onSuccess: (page) => {
                    found =
                        (page.props.plan as ManagePlan | undefined)?.change ??
                        null;
                },
                onFinish: () => resolve(found),
            });
        }),
});
const {
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
    selection,
} = change;

const cycle = cycleCopy[plan.current.cycle];
const period = plan.current.cycle === 'yearly' ? 'año' : 'mes';
const money = (cents: number) => formatMoney(cents, 'DOP');
const day = (iso: string) => changeDate(iso, plan.timezone, false);
const moment = (iso: string) => changeDate(iso, plan.timezone);
/** Precio del período en curso y vigencia pagada (mismas etiquetas que «Ver mi paquete»). */
const facts = periodFacts(
    plan.current,
    plan.current.cycle,
    money,
    plan.timezone,
);
const paidUntilDay =
    plan.current.access_until &&
    Date.parse(plan.current.access_until) > Date.now()
        ? day(plan.current.access_until)
        : null;
const namesOf = (fate: AppFate, list = rows.value) =>
    joinNames(list.filter((row) => row.fate === fate).map((row) => row.name));
const currentNames = joinNames(
    productOrder(plan)
        .filter((code) => plan.current.products.includes(code))
        .map((code) => plan.names[code] ?? code),
);

/**
 * La fecha del cambio ya pasó: lo indica el servidor (acción de activar) o el
 * reloj del dispositivo. Solo cambia el tiempo verbal de los textos.
 */
const ended = computed(() => {
    if (!saved.value) return false;
    const access = tracker.value?.purchase.value?.access ?? [];
    if (
        progress.value?.activation.state === 'done' ||
        access.some((row) => row.status === 'consent_required')
    )
        return true;
    return Date.parse(saved.value.effective_at) <= Date.now();
});
const nextNames = computed(() =>
    joinNames(
        savedRows.value
            .filter((row) => row.fate === 'kept' || row.fate === 'added')
            .map((row) => row.name),
    ),
);
/** Segunda frase del cambio registrado; si el paquete nuevo no está disponible, el motivo va en «Activar». */
const afterChange = computed(() => {
    if (!ended.value)
        return `Desde esa fecha tu paquete tendrá ${nextNames.value}.`;
    const state = progress.value?.activation.state;
    if (state === 'done') return `Tu paquete nuevo tiene ${nextNames.value}.`;
    if (state === 'problem') return '';
    return `Activa el paquete nuevo para usar ${nextNames.value}.`;
});

/** Lo que cambia según lo marcado, antes de revisar. */
const pendingSummary = computed(() => {
    if (!changed.value) return '';
    if (!selection.value.length) return 'No marcaste ninguna aplicación.';
    return [
        namesOf('added') && `Se agrega ${namesOf('added')}.`,
        namesOf('removed') && `Se da de baja ${namesOf('removed')}.`,
    ]
        .filter(Boolean)
        .join(' ');
});

const isCancellation = computed(() => review.value?.products.length === 0);
const keptInReview = computed(() =>
    reviewRows.value.filter((row) => row.fate !== 'excluded'),
);

// Compra nueva (factura del paquete nuevo): su estado se consulta aquí mismo.
const newPurchaseId = computed(() => purchaseIdFrom(saved.value?.purchase_url));
const purchaseClient = createSelfServiceClient({
    catalog_url: catalogRoute.url(),
    calculate_url: calculateRoute.url(),
    confirm_url: purchasesRoute.url(),
});
const tracker = shallowRef<ReturnType<typeof useBusinessPurchase> | null>(null);
watch(
    newPurchaseId,
    (id) => {
        tracker.value?.dispose();
        tracker.value = id
            ? useBusinessPurchase({
                  client: purchaseClient,
                  purchaseId: id,
                  names: plan.names,
              })
            : null;
    },
    { immediate: true },
);
onBeforeUnmount(() => tracker.value?.dispose());
const progress = computed(() => {
    const current = tracker.value?.purchase.value;
    return current && saved.value
        ? changeProgress(
              current,
              day(saved.value.effective_at),
              money,
              plan.names,
          )
        : null;
});
const progressFailed = computed(() => !!tracker.value?.error.value);
const onVisible = () => {
    if (document.visibilityState === 'visible') tracker.value?.refresh();
};
if (typeof document !== 'undefined')
    document.addEventListener('visibilitychange', onVisible);
onBeforeUnmount(() =>
    document.removeEventListener('visibilitychange', onVisible),
);

// Cada cambio de paso lleva el foco al título nuevo (lectores de pantalla y teclado).
const stepHeading = ref<HTMLElement | null>(null);
const stage = computed(() =>
    saved.value ? 'saved' : review.value ? 'review' : 'edit',
);
watch(stage, async (value) => {
    await nextTick();
    if (value === 'edit') return;
    stepHeading.value?.focus();
    stepHeading.value?.scrollIntoView({ block: 'start' });
});

const FATE_STYLE: Record<AppFate, string> = {
    kept: 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-900',
    added: 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-200 dark:ring-sky-900',
    removed:
        'bg-amber-50 text-amber-900 ring-amber-300 dark:bg-amber-950/50 dark:text-amber-200 dark:ring-amber-900',
    excluded: 'text-muted-foreground ring-border',
};
const FATE_ICON = { kept: Check, added: Plus, removed: Minus, excluded: null };
const STEP_ICON: Record<StepState, unknown> = {
    todo: CircleAlert,
    waiting: Clock,
    done: CircleCheck,
    problem: CircleAlert,
};
const STEP_STYLE: Record<StepState, string> = {
    todo: 'text-amber-600 dark:text-amber-400',
    waiting: 'text-sky-600 dark:text-sky-400',
    done: 'text-emerald-600 dark:text-emerald-400',
    problem: 'text-red-600 dark:text-red-400',
};
const reloadPage = () => window.location.reload();
</script>

<template>
    <Head title="Administrar mi paquete" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Tu paquete', href: plan.back_url },
            { title: 'Administrar mi paquete', href: '' },
        ]"
    >
        <div
            class="mx-auto w-full max-w-2xl space-y-8 px-4 py-6 sm:px-8 sm:py-8"
        >
            <Link
                :href="plan.back_url"
                class="-ml-1 inline-flex min-h-11 items-center gap-1.5 rounded-md px-1 text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Volver a mi paquete
            </Link>

            <header class="space-y-3">
                <p class="text-sm font-medium break-words">
                    {{ plan.company }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Administrar mi paquete
                </h1>
                <div
                    class="rounded-xl border bg-muted/30 p-4"
                    data-testid="current-package"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ ended ? 'Paquete anterior' : 'Tienes contratado' }}
                    </p>
                    <p class="mt-1 font-semibold">{{ currentNames }}</p>
                    <dl v-if="facts.length" class="mt-2 space-y-1 text-sm">
                        <div
                            v-for="fact in facts"
                            :key="fact.label"
                            class="flex flex-wrap gap-x-1.5"
                        >
                            <dt class="text-muted-foreground">
                                {{ fact.label }}:
                            </dt>
                            <dd
                                :class="
                                    fact.muted
                                        ? 'text-muted-foreground'
                                        : 'font-medium'
                                "
                            >
                                {{ fact.value }}
                            </dd>
                        </div>
                        <div class="flex flex-wrap gap-x-1.5">
                            <dt class="text-muted-foreground">Facturación:</dt>
                            <dd>{{ cycle.option }}</dd>
                        </div>
                    </dl>
                    <p v-else class="mt-1 text-sm text-muted-foreground">
                        Facturación {{ cycle.adverb }}
                    </p>
                </div>
            </header>

            <!-- 1 · Elegir -->
            <template v-if="stage === 'edit'">
                <form
                    class="space-y-5"
                    @submit.prevent="change.reviewSelection()"
                >
                    <fieldset class="space-y-3" :disabled="!!busy">
                        <legend class="text-lg font-semibold">
                            ¿Qué aplicaciones quieres para el próximo período?
                        </legend>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Marca las que quieres conservar o agregar y desmarca
                            las que ya no necesitas. Nada cambia hasta que
                            revises el precio y confirmes. Tu acceso actual
                            sigue igual
                            {{
                                paidUntilDay
                                    ? `hasta el ${paidUntilDay}`
                                    : 'hasta el final del período pagado'
                            }}.
                        </p>
                        <ul class="space-y-3">
                            <li v-for="row in rows" :key="row.code">
                                <label
                                    :for="`manage-${row.code}`"
                                    class="flex min-h-14 items-center gap-4 rounded-xl border p-4 transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50"
                                    :class="[
                                        row.locked
                                            ? 'cursor-not-allowed opacity-60'
                                            : 'cursor-pointer',
                                        row.fate === 'removed'
                                            ? 'border-amber-300 bg-amber-50/40 dark:border-amber-900 dark:bg-amber-950/20'
                                            : row.fate === 'excluded'
                                              ? 'bg-background'
                                              : 'border-primary bg-primary/5',
                                    ]"
                                >
                                    <input
                                        :id="`manage-${row.code}`"
                                        type="checkbox"
                                        class="size-5 shrink-0 accent-primary outline-none"
                                        :checked="selection.includes(row.code)"
                                        :disabled="row.locked"
                                        :aria-describedby="`fate-${row.code}`"
                                        @change="change.toggle(row.code)"
                                    />
                                    <span class="min-w-0 flex-1 font-medium">{{
                                        row.name
                                    }}</span>
                                    <span
                                        :id="`fate-${row.code}`"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="FATE_STYLE[row.fate]"
                                    >
                                        <component
                                            :is="FATE_ICON[row.fate]"
                                            v-if="FATE_ICON[row.fate]"
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{
                                            row.locked
                                                ? 'No disponible'
                                                : FATE_LABEL[row.fate]
                                        }}
                                    </span>
                                </label>
                            </li>
                        </ul>
                    </fieldset>

                    <p
                        v-if="pendingSummary"
                        class="text-sm leading-6 font-medium"
                        aria-live="polite"
                    >
                        {{ pendingSummary }}
                        <template v-if="!selection.length">
                            Para dejar todas, usa «Dar de baja todo el paquete».
                        </template>
                    </p>

                    <p
                        v-if="error"
                        role="alert"
                        class="text-sm leading-6 text-red-700 dark:text-red-400"
                    >
                        {{ error.message }}
                    </p>

                    <Button
                        type="submit"
                        size="lg"
                        class="h-12 w-full text-base sm:w-auto"
                        :disabled="!!busy || !changed || !selection.length"
                    >
                        <LoaderCircle
                            v-if="busy === 'review'"
                            class="size-4 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        {{
                            busy === 'review'
                                ? 'Calculando el precio…'
                                : 'Revisar el cambio'
                        }}
                    </Button>
                    <p v-if="!changed" class="text-sm text-muted-foreground">
                        Marca o desmarca una aplicación para revisar el cambio.
                    </p>
                </form>

                <section
                    aria-labelledby="cancel-title"
                    class="space-y-3 border-t pt-8"
                >
                    <h2 id="cancel-title" class="text-lg font-semibold">
                        Dar de baja todo el paquete
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Dejas de usar todas tus aplicaciones al terminar el
                        período pagado. Antes de confirmar verás la fecha
                        exacta.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        class="h-12 w-full border-red-300 text-red-700 hover:bg-red-50 hover:text-red-800 sm:w-auto dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/40"
                        :disabled="!!busy"
                        @click="change.reviewCancellation()"
                    >
                        <LoaderCircle
                            v-if="busy === 'cancel'"
                            class="size-4 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        {{
                            busy === 'cancel'
                                ? 'Consultando la fecha…'
                                : 'Revisar la baja del paquete'
                        }}
                    </Button>
                </section>
            </template>

            <!-- 2 · Revisar -->
            <section
                v-else-if="stage === 'review' && review"
                aria-labelledby="review-title"
                class="space-y-6"
            >
                <div class="space-y-2">
                    <h2
                        id="review-title"
                        ref="stepHeading"
                        tabindex="-1"
                        class="scroll-mt-20 text-xl font-semibold tracking-tight outline-none"
                    >
                        {{
                            isCancellation
                                ? 'Revisa la baja de tu paquete'
                                : 'Revisa el cambio de tu paquete'
                        }}
                    </h2>
                    <p
                        v-if="notice === 'price_changed'"
                        role="alert"
                        class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        El precio o la fecha cambió desde que lo revisaste. No
                        se registró nada. Revisa los datos nuevos y confirma
                        otra vez.
                    </p>
                </div>

                <!-- Baja total -->
                <template v-if="isCancellation">
                    <div
                        class="space-y-2 rounded-xl border border-red-200 bg-red-50 p-4 text-red-950 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100"
                    >
                        <p class="flex items-center gap-2 text-sm font-medium">
                            <CalendarClock class="size-4" aria-hidden="true" />
                            Tu acceso termina el
                        </p>
                        <p
                            class="text-2xl font-semibold tracking-tight"
                            data-testid="effective-at"
                        >
                            {{ moment(review.effective_at) }}
                        </p>
                        <p class="text-sm leading-6">
                            Hasta esa fecha sigues usando
                            {{ currentNames }} como ahora.
                        </p>
                    </div>
                    <ul
                        class="divide-y rounded-xl border px-4"
                        aria-label="Aplicaciones que se dan de baja"
                    >
                        <li
                            v-for="row in keptInReview"
                            :key="row.code"
                            class="flex flex-col gap-0.5 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span class="font-medium">{{ row.name }}</span>
                            <span class="text-sm text-muted-foreground"
                                >Hasta el {{ day(review.effective_at) }}</span
                            >
                        </li>
                    </ul>
                    <p class="flex items-start gap-2 text-sm leading-6">
                        <Check
                            class="mt-1 size-4 shrink-0 text-emerald-600"
                            aria-hidden="true"
                        />
                        La baja no genera otra factura ni cobros.
                    </p>
                </template>

                <!-- Cambio con aplicaciones -->
                <template v-else>
                    <ul
                        class="divide-y rounded-xl border px-4"
                        aria-label="Qué pasa con cada aplicación"
                    >
                        <li
                            v-for="row in keptInReview"
                            :key="row.code"
                            class="space-y-1 py-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <span class="font-medium">{{ row.name }}</span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                    :class="FATE_STYLE[row.fate]"
                                >
                                    <component
                                        :is="FATE_ICON[row.fate]"
                                        v-if="FATE_ICON[row.fate]"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ FATE_LABEL[row.fate] }}
                                </span>
                            </div>
                            <p class="text-sm leading-6 text-muted-foreground">
                                <template v-if="row.fate === 'kept'"
                                    >Sigue en tu paquete.</template
                                >
                                <template v-else-if="row.fate === 'removed'"
                                    >Puedes usarla hasta el
                                    {{ moment(review.effective_at) }}.</template
                                >
                                <template v-else
                                    >Podrás activarla a partir del
                                    {{ day(review.effective_at) }}, cuando LAUDA
                                    confirme el pago.</template
                                >
                            </p>
                        </li>
                    </ul>

                    <div class="space-y-4 rounded-xl border p-4 sm:p-5">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Precio del paquete nuevo
                            </p>
                            <p
                                class="text-3xl font-semibold tracking-tight tabular-nums"
                                data-testid="next-price"
                            >
                                {{ money(review.total_cents) }}
                                <span
                                    class="text-base font-normal text-muted-foreground"
                                    >al {{ period }}</span
                                >
                            </p>
                        </div>
                        <dl class="space-y-2 text-sm">
                            <div class="flex flex-wrap justify-between gap-x-3">
                                <dt class="text-muted-foreground">
                                    Empieza el
                                </dt>
                                <dd
                                    class="font-medium"
                                    data-testid="effective-at"
                                >
                                    {{ moment(review.effective_at) }}
                                </dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-x-3">
                                <dt class="text-muted-foreground">
                                    Facturación
                                </dt>
                                <dd class="font-medium">
                                    {{ cycle.option }}, igual que ahora
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <section aria-labelledby="steps-title" class="space-y-3">
                        <h3 id="steps-title" class="font-semibold">
                            Qué sigue
                        </h3>
                        <ol class="space-y-3 text-sm leading-6">
                            <li class="flex gap-3">
                                <span
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold"
                                    >1</span
                                >
                                <span
                                    >Al confirmar, generamos la factura del
                                    {{ cycle.first.toLowerCase() }} del paquete
                                    nuevo por {{ money(review.total_cents) }}.
                                    Hoy no se cobra nada automáticamente.</span
                                >
                            </li>
                            <li class="flex gap-3">
                                <span
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold"
                                    >2</span
                                >
                                <span
                                    >La pagas por transferencia y envías el
                                    comprobante. LAUDA confirma el pago.</span
                                >
                            </li>
                            <li class="flex gap-3">
                                <span
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold"
                                    >3</span
                                >
                                <span
                                    >A partir del
                                    {{ day(review.effective_at) }} activas el
                                    paquete nuevo. Hasta esa fecha sigues usando
                                    tu paquete actual.</span
                                >
                            </li>
                        </ol>
                    </section>
                </template>

                <form
                    class="space-y-4"
                    :aria-busy="busy === 'confirm'"
                    @submit.prevent="change.confirm()"
                >
                    <label
                        for="accept-change"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm leading-6 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50"
                    >
                        <input
                            id="accept-change"
                            v-model="accepted"
                            type="checkbox"
                            class="mt-0.5 size-5 shrink-0 accent-primary outline-none"
                            :disabled="busy === 'confirm'"
                        />
                        <span v-if="isCancellation">
                            Entiendo que perderé el acceso a
                            {{ currentNames }} el
                            {{ day(review.effective_at) }}.
                        </span>
                        <span v-else>
                            Acepto cambiar mi paquete a partir del
                            {{ day(review.effective_at) }} y recibir la factura
                            por {{ money(review.total_cents) }}.
                        </span>
                    </label>

                    <div
                        v-if="error"
                        role="alert"
                        class="space-y-1 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-950 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100"
                    >
                        <template v-if="uncertain">
                            <p class="font-semibold">
                                No sabemos si el cambio quedó registrado
                            </p>
                            <p>
                                Se perdió la conexión mientras lo enviábamos.
                                Vuelve a intentarlo: si ya quedó registrado, no
                                se registra dos veces ni se genera otra factura.
                            </p>
                        </template>
                        <p v-else>{{ error.message }}</p>
                        <Button
                            v-if="error.code === 'session_expired'"
                            type="button"
                            variant="outline"
                            class="mt-2 h-11"
                            @click="reloadPage"
                            >Recargar la página</Button
                        >
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button
                            type="submit"
                            size="lg"
                            class="h-12 text-base"
                            :class="
                                isCancellation
                                    ? 'bg-red-600 text-white hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600'
                                    : ''
                            "
                            :disabled="!accepted || !!busy"
                        >
                            <LoaderCircle
                                v-if="busy === 'confirm'"
                                class="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                            {{
                                busy === 'confirm'
                                    ? 'Registrando…'
                                    : uncertain
                                      ? 'Volver a intentar'
                                      : isCancellation
                                        ? 'Confirmar la baja'
                                        : 'Confirmar el cambio'
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            class="h-12 text-base"
                            :disabled="!!busy"
                            @click="change.edit()"
                        >
                            {{
                                isCancellation
                                    ? 'No, conservar mi paquete'
                                    : 'Cambiar mi selección'
                            }}
                        </Button>
                    </div>
                </form>
            </section>

            <!-- 3 · Registrado -->
            <section
                v-else-if="saved"
                aria-labelledby="saved-title"
                class="space-y-6"
            >
                <div
                    role="status"
                    class="space-y-2 rounded-xl border p-4 sm:p-5"
                    :class="
                        saved.products.length
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100'
                            : 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100'
                    "
                >
                    <h2
                        id="saved-title"
                        ref="stepHeading"
                        tabindex="-1"
                        class="flex scroll-mt-20 items-center gap-2 text-xl font-semibold tracking-tight outline-none"
                    >
                        <CircleCheck
                            class="size-5 shrink-0"
                            aria-hidden="true"
                        />
                        {{
                            saved.products.length
                                ? 'Cambio registrado'
                                : 'Baja registrada'
                        }}
                    </h2>
                    <p
                        v-if="notice === 'already_registered'"
                        class="text-sm leading-6"
                    >
                        Este paquete ya tenía un cambio registrado; es el que
                        ves aquí. No se registró otro.
                    </p>
                    <p class="text-sm leading-6">
                        <template v-if="saved.products.length">
                            {{
                                ended
                                    ? 'Tu paquete anterior terminó el'
                                    : 'Tu paquete actual sigue activo hasta el'
                            }}
                            <strong data-testid="effective-at">{{
                                moment(saved.effective_at)
                            }}</strong
                            >.
                            {{ afterChange }}
                        </template>
                        <template v-else>
                            Tu acceso a {{ currentNames }}
                            {{ ended ? 'terminó' : 'termina' }} el
                            <strong data-testid="effective-at">{{
                                moment(saved.effective_at)
                            }}</strong
                            >.
                            {{
                                ended
                                    ? ''
                                    : 'Hasta entonces puedes seguir usando tus aplicaciones.'
                            }}
                            La baja no generó otra factura.
                        </template>
                    </p>
                </div>

                <template v-if="saved.products.length">
                    <dl class="space-y-2 rounded-xl border p-4 text-sm">
                        <div
                            v-for="row in savedRows.filter(
                                (item) => item.fate !== 'excluded',
                            )"
                            :key="row.code"
                            class="flex flex-wrap justify-between gap-x-3"
                        >
                            <dt>{{ row.name }}</dt>
                            <dd class="font-medium">
                                {{ FATE_LABEL[row.fate] }}
                            </dd>
                        </div>
                        <div
                            class="flex flex-wrap justify-between gap-x-3 border-t pt-2"
                        >
                            <dt>{{ cycle.price }} del paquete nuevo</dt>
                            <dd class="font-semibold tabular-nums">
                                {{ money(saved.total_cents) }}
                            </dd>
                        </div>
                    </dl>

                    <section aria-labelledby="next-title" class="space-y-3">
                        <h3 id="next-title" class="text-lg font-semibold">
                            Próximos pasos
                        </h3>
                        <ol class="divide-y rounded-xl border px-4">
                            <li class="space-y-2 py-4">
                                <p class="flex items-center gap-2 font-medium">
                                    <component
                                        :is="
                                            progress
                                                ? STEP_ICON[
                                                      progress.payment.state
                                                  ]
                                                : Clock
                                        "
                                        class="size-5 shrink-0"
                                        :class="
                                            progress
                                                ? STEP_STYLE[
                                                      progress.payment.state
                                                  ]
                                                : 'text-muted-foreground'
                                        "
                                        aria-hidden="true"
                                    />
                                    1. Pagar la factura del paquete nuevo
                                </p>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                    data-testid="payment-step"
                                >
                                    <template v-if="progress">{{
                                        progress.payment.text
                                    }}</template>
                                    <template v-else-if="progressFailed"
                                        >No pudimos consultar el pago. Ábrelo
                                        desde tu nueva compra.</template
                                    >
                                    <template v-else
                                        >Consultando el estado del
                                        pago…</template
                                    >
                                </p>
                                <Button
                                    v-if="progress?.payment.action"
                                    as-child
                                    size="lg"
                                    class="h-12 w-full text-base sm:w-auto"
                                >
                                    <a :href="progress.payment.action.url">{{
                                        progress.payment.action.label
                                    }}</a>
                                </Button>
                            </li>
                            <li class="space-y-2 py-4">
                                <p class="flex items-center gap-2 font-medium">
                                    <component
                                        :is="
                                            progress
                                                ? STEP_ICON[
                                                      progress.activation.state
                                                  ]
                                                : Clock
                                        "
                                        class="size-5 shrink-0"
                                        :class="
                                            progress
                                                ? STEP_STYLE[
                                                      progress.activation.state
                                                  ]
                                                : 'text-muted-foreground'
                                        "
                                        aria-hidden="true"
                                    />
                                    2. Activar el paquete nuevo
                                </p>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                    data-testid="activation-step"
                                >
                                    {{
                                        progress?.activation.text ??
                                        `Podrás activarlo a partir del ${day(saved.effective_at)}, cuando LAUDA confirme el pago.`
                                    }}
                                </p>
                                <Button
                                    v-if="progress?.activation.action"
                                    as-child
                                    size="lg"
                                    class="h-12 w-full text-base sm:w-auto"
                                >
                                    <a :href="progress.activation.action.url">{{
                                        progress.activation.action.label
                                    }}</a>
                                </Button>
                            </li>
                        </ol>
                        <Button
                            v-if="saved.purchase_url"
                            variant="outline"
                            size="lg"
                            class="h-12 w-full text-base sm:w-auto"
                            as-child
                        >
                            <Link :href="saved.purchase_url"
                                >Ver mi nueva compra</Link
                            >
                        </Button>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Puedes volver a esta página desde tu nueva compra,
                            con «Ver el cambio de paquete».
                        </p>
                    </section>
                </template>

                <Button
                    v-else
                    variant="outline"
                    size="lg"
                    class="h-12 w-full text-base sm:w-auto"
                    as-child
                >
                    <Link :href="plan.back_url">Volver a mi paquete</Link>
                </Button>
            </section>
        </div>
    </AppLayout>
</template>
