<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    Clock,
    CreditCard,
    LoaderCircle,
    RefreshCw,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import BusinessPackageAmounts from '@/components/BusinessPackageAmounts.vue';
import { Button } from '@/components/ui/button';
import { useBusinessPurchase } from '@/composables/useBusinessPurchase';
import { cycleCopy, formatMoney } from '@/lib/businessPackageCopy';
import type { SelfServiceClient } from '@/lib/businessSelfService';
import type { Product, Purchase } from '@/types/businessSelfService';

const props = defineProps<{
    client: SelfServiceClient;
    purchaseId: string;
    initial?: Purchase | null;
    names?: Partial<Record<Product, string>>;
    companyName: string;
    homeUrl: string;
    /** Se puede armar un paquete nuevo desde esta página (hay catálogo). */
    canRestart: boolean;
}>();
const emit = defineEmits<{ restart: [] }>();

const tracker = useBusinessPurchase({
    client: props.client,
    purchaseId: props.purchaseId,
    initial: props.initial ?? null,
    names: props.names,
});
const { purchase, view, loading, error, staleError, stalled } = tracker;

// Al volver del proveedor de pago (otra pestaña o la caché del navegador) se
// consulta de nuevo. Consultar nunca confirma un pago: solo lee el estado.
const onVisible = () => {
    if (document.visibilityState === 'visible') tracker.refresh();
};
const onPageShow = (event: PageTransitionEvent) => {
    if (event.persisted) tracker.refresh();
};
const heading = ref<HTMLElement | null>(null);
onMounted(async () => {
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('pageshow', onPageShow);
    await nextTick();
    heading.value?.focus();
});
onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisible);
    window.removeEventListener('pageshow', onPageShow);
    tracker.dispose();
});

const money = (cents: number) =>
    formatMoney(cents, purchase.value?.currency ?? 'DOP');

const toneIcon = computed(
    () =>
        ({
            progress: Clock,
            action: CreditCard,
            success: CircleCheck,
            problem: CircleAlert,
        })[view.value?.tone ?? 'progress'],
);
const toneClass = computed(
    () =>
        ({
            progress:
                'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100',
            action: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100',
            success:
                'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100',
            problem:
                'border-destructive/40 bg-red-50 text-red-950 dark:bg-red-950/40 dark:text-red-100',
        })[view.value?.tone ?? 'progress'],
);

const errorTitle = computed(() => {
    switch (error.value?.code) {
        case 'purchase_not_found':
            return 'No encontramos esa compra';
        case 'forbidden':
            return 'No puedes ver esta compra';
        case 'session_expired':
            return 'Tu sesión venció';
        default:
            return 'No pudimos consultar tu compra';
    }
});

/** Nota bajo el total: lo dice el estado del pago, no una resta en el navegador. */
const balanceNote = computed(() => {
    const current = purchase.value;
    if (!current) return '';
    if (current.payment.status === 'paid') return 'Pagado.';
    if (current.payment.status === 'processing' && current.balance_cents === 0)
        return 'Pago en confirmación.';
    return `Por pagar: ${money(current.balance_cents)}.`;
});
const reloadPage = () => window.location.reload();
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-8">
        <!-- Sin compra que mostrar -->
        <div v-if="error" class="space-y-4">
            <p class="text-sm font-medium break-words">{{ companyName }}</p>
            <h1
                ref="heading"
                tabindex="-1"
                class="text-2xl font-semibold tracking-tight outline-none"
            >
                {{ errorTitle }}
            </h1>
            <p class="leading-7 text-muted-foreground">
                {{ error.message }}
                <template v-if="error.code === 'forbidden'">
                    Pide al propietario o a un administrador que la revise desde
                    su cuenta.</template
                >
            </p>
            <div class="flex flex-col gap-3 sm:flex-row">
                <Button
                    v-if="error.code === 'session_expired'"
                    size="lg"
                    class="h-12"
                    @click="reloadPage"
                    >Recargar la página</Button
                >
                <Button
                    v-else-if="
                        error.code === 'purchase_not_found' && canRestart
                    "
                    size="lg"
                    class="h-12"
                    @click="emit('restart')"
                    >Armar mi paquete</Button
                >
                <Button
                    v-else-if="error.code !== 'forbidden'"
                    size="lg"
                    class="h-12"
                    :disabled="loading"
                    @click="tracker.refresh()"
                    >Volver a intentar</Button
                >
                <Button variant="outline" size="lg" class="h-12" as-child>
                    <Link :href="homeUrl">Volver a Inicio</Link>
                </Button>
            </div>
        </div>

        <!-- Primera carga -->
        <div v-else-if="!purchase || !view" class="space-y-3" aria-busy="true">
            <p class="text-sm font-medium break-words">{{ companyName }}</p>
            <h1
                ref="heading"
                tabindex="-1"
                class="text-2xl font-semibold tracking-tight outline-none"
            >
                Tu compra
            </h1>
            <p class="flex items-center gap-2 text-sm text-muted-foreground">
                <LoaderCircle
                    class="size-4 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                Consultando el estado de tu compra…
            </p>
        </div>

        <template v-else>
            <header class="space-y-2">
                <p class="text-sm font-medium break-words">
                    {{ companyName }}
                </p>
                <h1
                    ref="heading"
                    tabindex="-1"
                    class="text-2xl font-semibold tracking-tight outline-none sm:text-3xl"
                >
                    {{ view.title }}
                </h1>
            </header>

            <!-- Estado y acción principal -->
            <section
                role="status"
                aria-live="polite"
                class="space-y-4 rounded-xl border p-4 text-sm leading-6"
                :class="toneClass"
            >
                <div class="flex items-start gap-3">
                    <component
                        :is="toneIcon"
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p class="break-words">{{ view.message }}</p>
                </div>
                <Button
                    v-if="view.primary"
                    size="lg"
                    class="h-12 w-full text-base"
                    as-child
                >
                    <a :href="view.primary.url">{{ view.primary.label }}</a>
                </Button>
            </section>

            <div
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-sm text-muted-foreground"
            >
                <p
                    v-if="loading"
                    class="flex items-center gap-2"
                    aria-live="polite"
                >
                    <LoaderCircle
                        class="size-4 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    Consultando…
                </p>
                <p v-else-if="stalled">
                    Sigue en proceso. Puede tardar unos minutos; vuelve a
                    consultar más tarde.
                </p>
                <p v-else-if="view.waiting">
                    Consultamos el estado automáticamente.
                </p>
                <span v-else />
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center gap-1.5 font-medium text-foreground underline underline-offset-4 disabled:opacity-50"
                    :disabled="loading"
                    @click="tracker.refresh()"
                >
                    <RefreshCw class="size-4" aria-hidden="true" />
                    Actualizar estado
                </button>
            </div>
            <p
                v-if="staleError"
                role="alert"
                class="text-sm leading-6 text-red-700 dark:text-red-400"
            >
                {{ staleError.message }} Te mostramos el último estado conocido.
            </p>

            <!-- Acceso por aplicación -->
            <section aria-labelledby="access-title" class="space-y-4">
                <h2 id="access-title" class="text-lg font-semibold">
                    Tus aplicaciones
                </h2>
                <ul class="divide-y rounded-xl border px-4">
                    <li
                        v-for="row in view.access"
                        :key="row.product"
                        class="space-y-2 py-4"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
                        >
                            <span class="font-medium">{{ row.name }}</span>
                            <span
                                class="text-sm"
                                :class="
                                    row.status === 'ready'
                                        ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                        : 'text-muted-foreground'
                                "
                                >{{ row.badge }}</span
                            >
                        </div>
                        <p
                            v-if="row.message"
                            class="text-sm leading-6 break-words text-muted-foreground"
                        >
                            {{ row.message }}
                        </p>
                        <Button
                            v-if="
                                row.action &&
                                row.action.url !== view.primary?.url
                            "
                            :variant="
                                row.status === 'ready' ? 'default' : 'outline'
                            "
                            class="h-11 w-full sm:w-auto"
                            as-child
                        >
                            <a :href="row.action.url">{{ row.action.label }}</a>
                        </Button>
                    </li>
                </ul>
            </section>

            <!-- Lo que se aceptó -->
            <section aria-labelledby="purchase-summary-title" class="space-y-4">
                <h2 id="purchase-summary-title" class="text-lg font-semibold">
                    Resumen de tu compra
                </h2>
                <div class="space-y-6 rounded-xl border p-4">
                    <dl class="space-y-2 text-sm">
                        <div
                            v-if="purchase.invoice_number"
                            class="flex justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">Factura</dt>
                            <dd class="font-medium break-all">
                                {{ purchase.invoice_number }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Incluye</dt>
                            <dd class="text-right font-medium">
                                {{
                                    view.access
                                        .map((row) => row.name)
                                        .join(', ')
                                }}
                            </dd>
                        </div>
                    </dl>
                    <BusinessPackageAmounts
                        title="Pago de hoy"
                        total-label="Total de la compra"
                        :rows="[]"
                        :total-cents="purchase.summary.due_today.total_cents"
                        :currency="purchase.currency"
                        :note="balanceNote"
                        emphasis
                    />
                    <BusinessPackageAmounts
                        :title="
                            cycleCopy[purchase.summary.selection.cycle].price
                        "
                        :total-label="`Total ${cycleCopy[purchase.summary.selection.cycle].adverb}`"
                        :rows="[]"
                        :total-cents="purchase.summary.recurring.total_cents"
                        :currency="purchase.currency"
                        :note="
                            purchase.summary.automatic_renewal
                                ? 'Se renueva automáticamente según las condiciones que aceptaste.'
                                : 'Este paquete no se renueva automáticamente.'
                        "
                    />
                </div>
            </section>

            <div class="flex flex-col gap-3 sm:flex-row">
                <Button variant="outline" size="lg" class="h-12" as-child>
                    <Link :href="homeUrl">Volver a Inicio</Link>
                </Button>
            </div>
        </template>
    </div>
</template>
