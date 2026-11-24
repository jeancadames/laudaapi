<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BusinessCapacitySummary from '@/components/BusinessCapacitySummary.vue';
import BusinessTransitionSummary from '@/components/BusinessTransitionSummary.vue';
import PortalPage from '@/components/portal/PortalPage.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { timezoneLabel } from '@/lib/timezoneLabel';
import type {
    BusinessQuoteDetail,
    QuoteAcceptance,
} from '@/types/businessQuoteAcceptance';

const props = defineProps<{
    quote: BusinessQuoteDetail;
    idempotencyKey: string;
}>();
const consent = ref(false);
const busy = ref(false);
const error = ref('');
const receipt = ref<QuoteAcceptance | null>(props.quote.acceptance);
const clock = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    timer = setInterval(() => {
        clock.value = Date.now();
    }, 1000);
});
onBeforeUnmount(() => clearInterval(timer));
watch(
    () => props.quote,
    (quote) => {
        consent.value = false;
        error.value = '';
        receipt.value = quote.acceptance;
    },
);
const snapshot = computed(() => props.quote.snapshot);
const expired = computed(
    () => Date.parse(props.quote.valid_until) <= clock.value,
);
const money = (cents: number) =>
    new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: props.quote.currency,
    }).format(cents / 100);
const date = (value: string) =>
    new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: snapshot.value.timezone,
    }).format(new Date(value));
const cycle = computed(() =>
    props.quote.billing_cycle === 'yearly' ? 'año' : 'mes',
);
const recurringTax = computed(() =>
    Math.round((snapshot.value.taxes?.recurring.tax_amount ?? 0) * 100),
);
const oneTimeTax = computed(() =>
    Math.round((snapshot.value.taxes?.one_time.tax_amount ?? 0) * 100),
);
const taxRate = computed(() =>
    new Intl.NumberFormat('es-DO', {
        style: 'percent',
        maximumFractionDigits: 3,
    }).format(snapshot.value.tax_rate),
);
const total = computed(
    () =>
        snapshot.value.recurring_cents +
        snapshot.value.one_time_cents +
        recurringTax.value +
        oneTimeTax.value,
);
const capacityNames = {
    documents: 'Documentos al mes',
    branches: 'Sucursales',
    registers: 'Cajas',
    users: 'Usuarios',
};

async function accept() {
    if (
        busy.value ||
        receipt.value ||
        !consent.value ||
        !props.quote.can_accept ||
        expired.value
    )
        return;
    busy.value = true;
    error.value = '';
    const publicId = props.quote.public_id;
    try {
        const xsrf = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice(11);
        const response = await fetch(props.quote.accept_url, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'error',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(xsrf ?? ''),
            },
            body: JSON.stringify({
                accepted: consent.value,
                snapshot_hash: props.quote.snapshot_hash,
                idempotency_key: props.idempotencyKey,
            }),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status === 419 || response.status === 401)
                throw new Error(
                    'Tu sesión venció. Recarga la página e inicia sesión para continuar.',
                );
            if (response.status === 429)
                throw new Error(
                    'Has realizado varios intentos. Espera un minuto antes de continuar.',
                );
            throw new Error(
                response.status < 500 && body.message
                    ? body.message
                    : 'No se pudo registrar la aceptación. Puedes volver a intentarlo.',
            );
        }
        if (props.quote.public_id === publicId) receipt.value = body.acceptance;
    } catch (failure) {
        if (props.quote.public_id === publicId)
            error.value =
                failure instanceof TypeError
                    ? 'No pudimos confirmar la respuesta. Puedes volver a intentarlo; si ya se registró, recuperaremos la misma aceptación.'
                    : failure instanceof Error
                      ? failure.message
                      : 'No se pudo confirmar la aceptación. Inténtalo de nuevo.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head :title="`Cotización · ${quote.offer_name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Inicio', href: '/app' },
            { title: 'Cotizaciones', href: '/app/business/quotes' },
            { title: quote.offer_name, href: quote.url },
        ]"
    >
        <PortalPage
            :eyebrow="quote.company_name"
            :title="`${quote.offer_name} · ${snapshot.level.name}`"
            :description="`Emitida ${date(quote.issued_at)} · Válida hasta ${date(quote.valid_until)} (${timezoneLabel(snapshot.timezone)})`"
            :back="{
                href: '/app/business/quotes',
                label: 'Volver a cotizaciones',
            }"
        >
            <div
                class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)]"
            >
                <div class="min-w-0 space-y-8">
                    <BusinessTransitionSummary
                        v-if="snapshot.transition"
                        :plan="snapshot.transition"
                    />
                    <section aria-labelledby="included-title" class="space-y-4">
                        <h2 id="included-title" class="text-lg font-semibold">
                            Soluciones incluidas
                        </h2>
                        <ul class="divide-y rounded-xl border px-5">
                            <li
                                v-for="(product, key) in snapshot.products"
                                :key="key"
                                class="space-y-1 py-4"
                            >
                                <h3 class="font-medium">{{ product.name }}</h3>
                                <p
                                    v-if="product.description"
                                    class="text-sm leading-6 text-muted-foreground"
                                >
                                    {{ product.description }}
                                </p>
                                <p
                                    v-if="product.features.length"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ product.features.join(' · ') }}
                                </p>
                            </li>
                        </ul>
                        <dl class="grid grid-cols-2 gap-4">
                            <div
                                v-for="(label, key) in capacityNames"
                                :key="key"
                                class="border-b pb-3"
                            >
                                <dt class="text-sm text-muted-foreground">
                                    {{ label }}
                                </dt>
                                <dd
                                    class="mt-1 text-lg font-medium tabular-nums"
                                >
                                    {{
                                        snapshot.capacity[key].toLocaleString(
                                            'es-DO',
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-sm text-muted-foreground">
                            La capacidad de documentos se renueva mensualmente,
                            también con facturación anual.
                        </p>
                        <BusinessCapacitySummary
                            :policy="snapshot.capacity_policy"
                            :has-pos="!!snapshot.products.pos"
                        />
                    </section>
                    <section
                        v-if="Object.keys(snapshot.addons).length"
                        aria-labelledby="addons-title"
                        class="space-y-3"
                    >
                        <h2 id="addons-title" class="text-lg font-semibold">
                            Adicionales incluidos en el importe
                        </h2>
                        <ul class="space-y-2 text-sm">
                            <li
                                v-for="(addon, key) in snapshot.addons"
                                :key="key"
                            >
                                {{ addon.quantity }} × {{ addon.name }} ·
                                {{ money(addon.amount_cents) }}
                                {{
                                    addon.recurring
                                        ? '/ mes antes de descuentos'
                                        : 'por única vez'
                                }}
                            </li>
                        </ul>
                    </section>
                    <section
                        aria-labelledby="implementation-title"
                        class="space-y-3 border-t pt-6"
                    >
                        <h2
                            id="implementation-title"
                            class="text-lg font-semibold"
                        >
                            Implementación {{ snapshot.implementation.name }}
                        </h2>
                        <p
                            class="text-sm leading-7 break-words whitespace-pre-wrap"
                        >
                            {{ snapshot.implementation.scope }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ snapshot.implementation.hours }} horas incluidas
                            ·
                            {{
                                money(snapshot.implementation.price_cents ?? 0)
                            }}
                            antes de impuestos, por única vez.
                        </p>
                    </section>
                    <section
                        aria-labelledby="terms-title"
                        class="space-y-3 border-t pt-6"
                    >
                        <h2 id="terms-title" class="text-lg font-semibold">
                            Condiciones de la cotización
                        </h2>
                        <p
                            class="text-sm leading-7 break-words whitespace-pre-wrap"
                        >
                            {{ snapshot.terms }}
                        </p>
                    </section>
                </div>

                <aside
                    aria-label="Importes y aceptación"
                    class="min-w-0 rounded-xl border bg-muted/30 p-5 sm:p-6"
                >
                    <h2 class="text-lg font-semibold">Resumen de importes</h2>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt>Servicio / {{ cycle }}</dt>
                            <dd class="shrink-0 tabular-nums">
                                {{ money(snapshot.recurring_cents) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 text-muted-foreground"
                        >
                            <dt>Impuestos / {{ cycle }} ({{ taxRate }})</dt>
                            <dd class="shrink-0 tabular-nums">
                                {{ money(recurringTax) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 border-t pt-3">
                            <dt>Cargos únicos</dt>
                            <dd class="shrink-0 tabular-nums">
                                {{ money(snapshot.one_time_cents) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 text-muted-foreground"
                        >
                            <dt>Impuestos de cargos únicos</dt>
                            <dd class="shrink-0 tabular-nums">
                                {{ money(oneTimeTax) }}
                            </dd>
                        </div>
                        <div
                            class="flex flex-wrap justify-between gap-2 border-t pt-4 font-semibold"
                        >
                            <dt>Total inicial cotizado</dt>
                            <dd class="text-xl tabular-nums">
                                {{ money(total) }}
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="
                            snapshot.annual_discount_cents ||
                            snapshot.commercial_discount_cents
                        "
                        class="mt-3 text-sm text-muted-foreground"
                    >
                        Los importes ya incluyen los descuentos de esta
                        cotización.
                    </p>

                    <div
                        v-if="receipt"
                        role="status"
                        class="mt-6 space-y-2 border-t pt-5"
                    >
                        <h2 class="font-semibold">Cotización aceptada</h2>
                        <p class="text-sm break-words">
                            {{ receipt.accepted_name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ date(receipt.accepted_at) }}
                        </p>
                        <p class="text-sm leading-6">
                            La aceptación quedó registrada. La facturación y la
                            activación de servicios se gestionan por separado.
                        </p>
                        <Button
                            v-if="quote.checkout_url"
                            as-child
                            class="mt-3 w-full"
                        >
                            <Link :href="quote.checkout_url"
                                >Continuar a facturación</Link
                            >
                        </Button>
                    </div>
                    <div
                        v-else-if="!quote.can_accept || expired"
                        role="status"
                        class="mt-6 space-y-2 border-t pt-5 text-sm"
                    >
                        <p class="font-medium">
                            No se puede aceptar esta cotización
                        </p>
                        <p class="leading-6">
                            {{
                                quote.unavailable_reason ??
                                'Esta cotización venció. Solicita una nueva.'
                            }}
                        </p>
                    </div>
                    <form
                        v-else
                        class="mt-6 space-y-4 border-t pt-5"
                        :aria-busy="busy"
                        @submit.prevent="accept"
                    >
                        <label
                            class="flex cursor-pointer items-start gap-3 text-sm leading-6"
                            for="accept-terms"
                        >
                            <input
                                id="accept-terms"
                                v-model="consent"
                                type="checkbox"
                                :disabled="busy"
                                class="mt-1 size-4 shrink-0 accent-primary"
                            />
                            <span
                                >He revisado los importes, el alcance y las
                                condiciones, y acepto esta cotización en nombre
                                de {{ quote.company_name }}.
                                <span v-if="snapshot.transition"
                                    >Acepto además la suscripción elegida, el
                                    corte al vencer lo pagado y los importes de
                                    crédito, devolución y prorrateo
                                    mostrados.</span
                                ></span
                            >
                        </label>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Aceptar registra tu conformidad. Este paso no
                            realiza cobros ni activa servicios.
                        </p>
                        <div
                            v-if="error"
                            role="alert"
                            class="space-y-2 text-sm text-destructive"
                        >
                            <p>{{ error }}</p>
                            <button
                                type="button"
                                class="underline underline-offset-4"
                                :disabled="busy"
                                @click="router.reload()"
                            >
                                Recargar cotización
                            </button>
                        </div>
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="!consent || busy"
                            >{{
                                busy
                                    ? 'Registrando aceptación…'
                                    : 'Aceptar cotización'
                            }}</Button
                        >
                    </form>
                </aside>
            </div>
        </PortalPage>
    </AppLayout>
</template>
