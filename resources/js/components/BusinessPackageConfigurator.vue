<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, LoaderCircle } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import BusinessPackageAmounts from '@/components/BusinessPackageAmounts.vue';
import { Button } from '@/components/ui/button';
import { useBusinessPackageBuilder } from '@/composables/useBusinessPackageBuilder';
import {
    componentRows,
    cycleCopy,
    formatDate,
    formatMoney,
    helpFor,
    inclusionsOf,
    joinNames,
    pricingExplanation,
    showsTax,
} from '@/lib/businessPackageCopy';
import type { SelfServiceClient } from '@/lib/businessSelfService';
import type {
    Catalog,
    Preview,
    Product,
    Purchase,
} from '@/types/businessSelfService';

const props = defineProps<{
    catalog: Catalog;
    client: SelfServiceClient;
    storageScope: string | number;
    purchasesEnabled: boolean;
    /** Aplicaciones que llegan marcadas («Agregar a mi paquete»). */
    add?: string[];
}>();
const emit = defineEmits<{
    /** Compra creada (201) o recuperada con la misma clave (200). */
    purchased: [purchase: Purchase];
    /** Ya existe una compra en curso (`existing_purchase`): mostrarla. */
    resume: [purchaseId: string];
}>();
const catalog = props.catalog;

function sessionStore() {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null;
    }
}

const builder = useBusinessPackageBuilder({
    catalog,
    client: props.client,
    storage: sessionStore(),
    storageScope: props.storageScope,
    add: props.add,
});
const {
    selected,
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
    existingPurchaseId,
} = builder;
onBeforeUnmount(builder.dispose);

const byCode = new Map(
    catalog.products.map((product) => [product.code, product]),
);
const nameOf = (code: Product) => byCode.get(code)?.name ?? code;
const includedIn = (code: Product) => {
    const product = byCode.get(code);
    return product ? inclusionsOf(product) : [];
};
const options = catalog.products.map((product) => ({
    ...product,
    help: helpFor(product.code),
    included: inclusionsOf(product),
}));
const invalid = computed(() => calculationError.value?.invalidProducts ?? []);

// El paso vive en la URL: el botón Atrás del teléfono vuelve a la selección.
const page = usePage();
const stepUrl = (summary: boolean) => {
    const url = new URL(page.url, 'http://lauda.local');
    if (summary) url.searchParams.set('paso', 'resumen');
    else url.searchParams.delete('paso');
    return url.pathname + url.search;
};
const step = computed<'select' | 'summary'>(() =>
    selected.value.length > 0 &&
    new URL(page.url, 'http://lauda.local').searchParams.get('paso') ===
        'resumen'
        ? 'summary'
        : 'select',
);
const heading = ref<HTMLElement | null>(null);
watch(step, async () => {
    await nextTick();
    window.scrollTo(0, 0);
    heading.value?.focus();
});

let openedFromSelection = false;
function review() {
    if (!proposalIsCurrent.value) return;
    openedFromSelection = true;
    router.push({ url: stepUrl(true), preserveState: true });
}
function edit() {
    if (confirming.value) return;
    if (openedFromSelection) window.history.back();
    else router.replace({ url: stepUrl(false), preserveState: true });
}

const money = (cents: number) =>
    formatMoney(cents, proposal.value?.currency ?? 'DOP');
const taxShown = (preview: Preview) =>
    showsTax(preview.recurring, preview.one_time, preview.due_today);
const periodTitle = (preview: Preview) =>
    preview.automatic_renewal
        ? `Renovación automática ${cycleCopy[preview.selection.cycle].adverb}`
        : cycleCopy[preview.selection.cycle].price;

/** Qué se paga hoy: desglose de `due_today` y, si hay cargos únicos, su origen. */
const todayRows = computed(() => {
    const preview = proposal.value;
    if (!preview) return [];
    const rows: { label: string; cents: number }[] = [];
    if (preview.one_time.total_cents > 0)
        rows.push(
            {
                label: cycleCopy[preview.selection.cycle].first,
                cents: preview.recurring.net_cents,
            },
            { label: 'Cargos únicos', cents: preview.one_time.net_cents },
        );
    if (taxShown(preview)) {
        if (!rows.length)
            rows.push({
                label: 'Base, antes de impuestos',
                cents: preview.due_today.net_cents,
            });
        rows.push({ label: 'Impuestos', cents: preview.due_today.tax_cents });
    }
    return rows;
});

/**
 * Precio por período, tal como lo devuelve Laravel: una fila por componente de
 * `preview.components`, impuestos si los hay y el total del ciclo.
 */
const periodRows = computed(() => {
    const preview = proposal.value;
    if (!preview) return [];
    const rows = componentRows(preview.components, nameOf);
    if (taxShown(preview))
        rows.push({ label: 'Impuestos', cents: preview.recurring.tax_cents });
    return rows;
});

/** «Tarifa aplicada: Business» o qué tarifa cubre qué aplicaciones. */
const explanation = computed(() =>
    proposal.value ? pricingExplanation(proposal.value.components, nameOf) : '',
);
const chosenNames = computed(() =>
    joinNames((proposal.value?.selection.products ?? []).map(nameOf)),
);

const capacities = computed(() => {
    const capacity = proposal.value?.capacity;
    if (!capacity) return [];
    return [
        { label: 'Documentos al mes', quantity: capacity.documents },
        { label: 'Usuarios', quantity: capacity.users },
        { label: 'Sucursales', quantity: capacity.branches },
        { label: 'Cajas', quantity: capacity.registers },
    ].filter((item, index) => index < 2 || item.quantity > 0);
});

// Tras confirmar, la página muestra la compra y la acción que indique el servidor.
watch(purchase, (created) => {
    if (created) emit('purchased', created);
});
watch(existingPurchaseId, (purchaseId) => {
    if (purchaseId) emit('resume', purchaseId);
});
</script>

<template>
    <div :class="step === 'select' ? 'pb-44 md:pb-0' : ''">
        <!-- Paso 1: selección -->
        <div
            v-if="step === 'select'"
            class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)]"
        >
            <div class="min-w-0 space-y-8">
                <header class="space-y-2">
                    <p class="text-sm font-medium break-words">
                        {{ catalog.company_name }}
                    </p>
                    <h1
                        ref="heading"
                        tabindex="-1"
                        class="text-2xl font-semibold tracking-tight outline-none sm:text-3xl"
                    >
                        Armar mi paquete
                    </h1>
                    <p class="leading-7 text-muted-foreground">
                        Elige solo las aplicaciones que necesitas, en la
                        combinación que quieras: armamos tu plan y te mostramos
                        el precio antes de contratar. Todavía no se cobra nada.
                    </p>
                </header>

                <section v-if="catalog.presets?.length" class="space-y-3">
                    <h2 class="text-lg font-semibold">
                        Paquetes predeterminados
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Elige una combinación inicial. Puedes ajustar las
                        aplicaciones antes de revisar el precio.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="preset in catalog.presets"
                            :key="preset.code"
                            variant="outline"
                            @click="builder.selectPreset(preset.code)"
                        >
                            {{ preset.name }}
                        </Button>
                    </div>
                </section>

                <fieldset class="space-y-4">
                    <legend class="text-lg font-semibold">
                        ¿Qué quieres hacer en tu negocio?
                    </legend>
                    <p class="text-sm text-muted-foreground">
                        Marca una o varias opciones.
                    </p>
                    <ul class="space-y-3">
                        <li v-for="option in options" :key="option.code">
                            <label
                                :for="`package-${option.code}`"
                                class="flex cursor-pointer gap-4 rounded-xl border p-4 transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50"
                                :class="
                                    invalid.includes(option.code)
                                        ? 'border-destructive'
                                        : selected.includes(option.code)
                                          ? 'border-primary bg-primary/5'
                                          : 'bg-background'
                                "
                            >
                                <input
                                    :id="`package-${option.code}`"
                                    type="checkbox"
                                    class="mt-0.5 size-5 shrink-0 accent-primary outline-none"
                                    :checked="selected.includes(option.code)"
                                    @change="builder.toggle(option.code)"
                                />
                                <span class="min-w-0 flex-1 space-y-1.5">
                                    <span
                                        class="block leading-snug font-semibold"
                                        >{{ option.task }}</span
                                    >
                                    <span
                                        class="block text-sm text-muted-foreground"
                                    >
                                        Aplicación:
                                        <span
                                            class="font-medium text-foreground"
                                            >{{ option.name }}</span
                                        >
                                    </span>
                                    <span
                                        v-if="option.help"
                                        class="block text-sm leading-6 text-muted-foreground"
                                        >{{ option.help }}</span
                                    >
                                    <span
                                        v-for="item in option.included"
                                        :key="item.offer"
                                        class="flex items-start gap-1.5 text-sm leading-6"
                                    >
                                        <Check
                                            class="mt-1 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            <span
                                                class="font-semibold text-emerald-800 dark:text-emerald-300"
                                                >{{ item.offer }}</span
                                            >
                                            <span
                                                v-if="item.help"
                                                class="text-muted-foreground"
                                            >
                                                · {{ item.help }}</span
                                            >
                                        </span>
                                    </span>
                                    <span
                                        v-if="invalid.includes(option.code)"
                                        class="block text-sm font-medium text-red-700 dark:text-red-400"
                                        >Revisa esta opción: no se puede
                                        contratar así.</span
                                    >
                                </span>
                            </label>
                        </li>
                    </ul>
                </fieldset>

                <fieldset v-if="catalog.cycles.length > 1" class="space-y-3">
                    <legend class="text-lg font-semibold">
                        ¿Cada cuánto prefieres pagar?
                    </legend>
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            v-for="value in catalog.cycles"
                            :key="value"
                            :for="`cycle-${value}`"
                            class="flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border px-4 font-medium transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50"
                            :class="
                                builder.cycle.value === value
                                    ? 'border-primary bg-primary/5'
                                    : 'bg-background'
                            "
                        >
                            <input
                                :id="`cycle-${value}`"
                                type="radio"
                                name="cycle"
                                class="size-4 accent-primary outline-none"
                                :checked="builder.cycle.value === value"
                                @change="builder.setCycle(value)"
                            />
                            {{ cycleCopy[value].option }}
                        </label>
                    </div>
                </fieldset>
            </div>

            <!-- Precio en vivo: barra fija en el celular, tarjeta en tableta y escritorio -->
            <aside
                aria-label="Precio de tu plan"
                class="fixed inset-x-0 bottom-0 z-20 border-t bg-background px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgba(0,0,0,0.08)] md:static md:z-auto md:rounded-xl md:border md:p-6 md:shadow-none"
            >
                <h2 class="hidden text-lg font-semibold md:mb-4 md:block">
                    Tu plan
                </h2>
                <div aria-live="polite" class="min-h-[3.25rem]">
                    <p
                        v-if="!selected.length"
                        class="text-sm leading-6 text-muted-foreground"
                    >
                        Marca al menos una opción para ver cuánto pagarás.
                    </p>
                    <div
                        v-else-if="calculationError"
                        role="alert"
                        class="space-y-1 text-sm leading-6"
                    >
                        <p class="text-red-700 dark:text-red-400">
                            {{ calculationError.message }}
                        </p>
                        <p
                            v-for="detail in calculationError.details"
                            :key="detail"
                            class="text-red-700 dark:text-red-400"
                        >
                            {{ detail }}
                        </p>
                        <button
                            v-if="calculationError.code !== 'invalid_selection'"
                            type="button"
                            class="min-h-11 font-medium underline underline-offset-4"
                            @click="builder.retry()"
                        >
                            Volver a calcular
                        </button>
                    </div>
                    <div
                        v-else-if="proposal"
                        class="transition-opacity"
                        :class="calculating ? 'opacity-40' : ''"
                    >
                        <ul
                            class="mb-4 hidden space-y-2 border-b pb-4 text-sm md:block"
                        >
                            <li
                                v-for="code in proposal.selection.products"
                                :key="code"
                            >
                                <span class="font-medium">{{
                                    nameOf(code)
                                }}</span>
                                <span
                                    v-for="item in includedIn(code)"
                                    :key="item.granted"
                                    class="block text-emerald-800 dark:text-emerald-300"
                                    >{{ item.granted }}</span
                                >
                            </li>
                        </ul>
                        <p
                            v-if="explanation"
                            class="mb-3 hidden text-sm leading-6 text-muted-foreground md:block"
                        >
                            {{ explanation }}
                        </p>
                        <p
                            class="flex flex-wrap items-baseline justify-between gap-x-3"
                        >
                            <span class="text-sm font-medium">Pagas hoy</span>
                            <span
                                class="text-2xl font-semibold tracking-tight tabular-nums"
                                >{{
                                    money(proposal.due_today.total_cents)
                                }}</span
                            >
                        </p>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ periodTitle(proposal) }}
                            <span class="font-medium text-foreground">{{
                                money(proposal.recurring.total_cents)
                            }}</span
                            ><template v-if="taxShown(proposal)"
                                >, impuestos incluidos</template
                            >
                        </p>
                    </div>
                    <p
                        v-else
                        class="flex items-center gap-2 text-sm leading-6 text-muted-foreground"
                    >
                        <LoaderCircle
                            class="size-4 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        Calculando tu precio…
                    </p>
                </div>
                <p
                    v-if="adjusted"
                    role="status"
                    class="mt-2 text-sm leading-6 text-amber-800 dark:text-amber-300"
                >
                    Ajustamos tu selección a lo que se puede contratar hoy.
                    Revísala antes de continuar.
                </p>
                <Button
                    size="lg"
                    class="mt-3 h-12 w-full text-base"
                    :disabled="!proposalIsCurrent"
                    @click="review"
                >
                    {{
                        calculating && selected.length
                            ? 'Actualizando precio…'
                            : 'Ver resumen'
                    }}
                </Button>
            </aside>
        </div>

        <!-- Paso 2: resumen -->
        <div v-else class="mx-auto max-w-2xl space-y-8">
            <header class="space-y-2">
                <button
                    type="button"
                    class="-ml-1 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium underline underline-offset-4"
                    :disabled="confirming"
                    @click="edit"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Cambiar mi selección
                </button>
                <p class="text-sm font-medium break-words">
                    {{ catalog.company_name }}
                </p>
                <h1
                    ref="heading"
                    tabindex="-1"
                    class="text-2xl font-semibold tracking-tight outline-none sm:text-3xl"
                >
                    Tu plan personalizado
                </h1>
                <p class="leading-7 text-muted-foreground">
                    Armado con las aplicaciones que elegiste. Puedes cambiarlas
                    antes de confirmar.
                </p>
            </header>

            <div
                v-if="calculationError"
                role="alert"
                class="space-y-3 rounded-xl border border-destructive/40 p-4 text-sm leading-6"
            >
                <p class="text-red-700 dark:text-red-400">
                    {{ calculationError.message }}
                </p>
                <Button variant="outline" class="h-11" @click="edit"
                    >Revisar mi selección</Button
                >
            </div>
            <p
                v-else-if="!proposal"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <LoaderCircle
                    class="size-4 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                Calculando tu precio…
            </p>

            <template v-else>
                <div
                    v-if="priceChange"
                    role="alert"
                    class="space-y-1 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <h2 class="font-semibold">
                        El precio cambió mientras revisabas
                    </h2>
                    <p>
                        Antes: pagabas hoy
                        {{ money(priceChange.previous.due_today.total_cents) }}.
                        Ahora:
                        <strong>{{
                            money(proposal.due_today.total_cents)
                        }}</strong
                        >. Tu selección se conservó y no se ha cobrado nada.
                        Revisa el nuevo importe y acéptalo para continuar.
                    </p>
                </div>

                <!-- Lo esencial primero: aplicaciones, hoy y por período -->
                <section
                    aria-labelledby="plan-title"
                    class="space-y-5 rounded-xl border bg-muted/30 p-4 sm:p-6"
                >
                    <h2 id="plan-title" class="sr-only">
                        Aplicaciones y precio
                    </h2>
                    <ul class="space-y-3" aria-label="Aplicaciones elegidas">
                        <li
                            v-for="code in proposal.selection.products"
                            :key="code"
                            class="flex items-start gap-2"
                        >
                            <Check
                                class="mt-1 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span class="block font-semibold">{{
                                    nameOf(code)
                                }}</span>
                                <span
                                    v-if="byCode.get(code)?.task"
                                    class="block text-sm text-muted-foreground"
                                    >{{ byCode.get(code)?.task }}</span
                                >
                                <span
                                    v-for="item in includedIn(code)"
                                    :key="item.granted"
                                    class="block text-sm font-semibold text-emerald-800 dark:text-emerald-300"
                                    >{{ item.granted }}</span
                                >
                            </span>
                        </li>
                    </ul>
                    <dl class="space-y-3 border-t pt-4">
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-x-3"
                        >
                            <dt class="font-semibold">Pagas hoy</dt>
                            <dd
                                class="text-2xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ money(proposal.due_today.total_cents) }}
                            </dd>
                        </div>
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-x-3"
                        >
                            <dt class="text-muted-foreground">
                                {{ periodTitle(proposal) }}
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ money(proposal.recurring.total_cents) }}
                            </dd>
                        </div>
                    </dl>
                    <p class="text-sm leading-6 text-muted-foreground">
                        <template v-if="!todayRows.length"
                            >Hoy pagas el
                            {{
                                cycleCopy[
                                    proposal.selection.cycle
                                ].first.toLowerCase()
                            }}.
                        </template>
                        <template v-if="taxShown(proposal)"
                            >Importes con impuestos incluidos.
                        </template>
                        {{
                            proposal.automatic_renewal
                                ? 'Se renueva automáticamente según las condiciones de abajo.'
                                : 'Este plan no se renueva automáticamente.'
                        }}
                    </p>
                </section>

                <!-- Cómo se forma el precio: preview.components tal cual -->
                <section aria-labelledby="pricing-title" class="space-y-4">
                    <h2 id="pricing-title" class="text-lg font-semibold">
                        Cómo calculamos tu precio
                    </h2>
                    <p
                        v-if="explanation"
                        class="leading-7"
                        data-testid="pricing-explanation"
                    >
                        {{ explanation }}
                    </p>
                    <div class="space-y-6 rounded-xl border p-4 sm:p-6">
                        <BusinessPackageAmounts
                            :title="periodTitle(proposal)"
                            :total-label="`Total ${cycleCopy[proposal.selection.cycle].adverb}`"
                            :rows="periodRows"
                            :total-cents="proposal.recurring.total_cents"
                            :currency="proposal.currency"
                        />
                        <div v-if="todayRows.length" class="border-t pt-5">
                            <BusinessPackageAmounts
                                title="Pagas hoy"
                                total-label="Total de hoy"
                                :rows="todayRows"
                                :total-cents="proposal.due_today.total_cents"
                                :currency="proposal.currency"
                            />
                        </div>
                    </div>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Elegiste {{ chosenNames }}. Facturación
                        {{ cycleCopy[proposal.selection.cycle].adverb }}.
                        <span class="block"
                            >Precio válido hasta el
                            {{ formatDate(proposal.expires_at) }}</span
                        >
                    </p>
                </section>

                <section aria-labelledby="included-title" class="space-y-4">
                    <h2 id="included-title" class="text-lg font-semibold">
                        También incluye
                    </h2>
                    <div class="space-y-1 rounded-xl border p-4">
                        <h3 class="font-semibold">
                            Implementación
                            {{ proposal.implementation.name }}
                        </h3>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ proposal.implementation.scope }}
                        </p>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <div
                            v-for="capacity in capacities"
                            :key="capacity.label"
                            class="border-b pb-3"
                        >
                            <dt class="text-sm text-muted-foreground">
                                {{ capacity.label }}
                            </dt>
                            <dd class="mt-0.5 font-medium tabular-nums">
                                {{ capacity.quantity.toLocaleString('es-DO') }}
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="proposal.selection.cycle === 'yearly'"
                        class="text-sm leading-6 text-muted-foreground"
                    >
                        La capacidad de documentos se renueva cada mes, también
                        con pago anual.
                    </p>
                </section>

                <section
                    v-if="proposal.terms"
                    aria-labelledby="conditions-title"
                    class="space-y-3"
                >
                    <h2 id="conditions-title" class="text-lg font-semibold">
                        Condiciones
                    </h2>
                    <p
                        class="text-sm leading-7 break-words whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ proposal.terms }}
                    </p>
                </section>

                <template v-if="purchasesEnabled">
                    <form
                        class="space-y-4"
                        :aria-busy="confirming"
                        @submit.prevent="builder.confirm()"
                    >
                        <label
                            for="accept-package"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm leading-6 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50"
                        >
                            <input
                                id="accept-package"
                                v-model="accepted"
                                type="checkbox"
                                class="mt-0.5 size-5 shrink-0 accent-primary outline-none"
                                :disabled="confirming || !proposalIsCurrent"
                            />
                            <span
                                >Revisé lo que incluye, los importes y las
                                condiciones, y acepto contratar este plan en
                                nombre de {{ catalog.company_name }}.</span
                            >
                        </label>
                        <p
                            v-if="confirmError"
                            role="alert"
                            class="text-sm leading-6 text-red-700 dark:text-red-400"
                        >
                            {{ confirmError.message }}
                        </p>
                        <Button
                            type="submit"
                            size="lg"
                            class="h-12 w-full text-base"
                            :disabled="
                                !accepted || confirming || !proposalIsCurrent
                            "
                        >
                            {{
                                confirming
                                    ? 'Registrando tu compra…'
                                    : 'Confirmar compra'
                            }}
                        </Button>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Al confirmar registramos tu compra y su factura.
                            Todavía no se cobra: el pago se hace en el paso
                            siguiente.
                        </p>
                    </form>
                </template>
                <p v-else class="text-sm leading-6 text-muted-foreground">
                    Revisa tu plan con calma. Pronto podrás contratarlo desde
                    aquí.
                </p>
            </template>
        </div>
    </div>
</template>
