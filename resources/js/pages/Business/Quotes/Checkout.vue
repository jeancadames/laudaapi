<script setup lang="ts">
import BusinessTransferProofForm from '@/components/BusinessTransferProofForm.vue';
import BusinessTransitionSummary from '@/components/BusinessTransitionSummary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { BusinessQuoteCheckout } from '@/types/businessQuoteCheckout';

const props = defineProps<{
    checkout: BusinessQuoteCheckout;
    idempotencyKey: string;
}>();
const current = ref(props.checkout);
const confirmed = ref(false);
const busy = ref(false);
const error = ref('');
watch(
    () => props.checkout,
    (value) => {
        current.value = value;
        confirmed.value = false;
        error.value = '';
    },
);
const quote = computed(() => current.value.quote);
const invoice = computed(() => current.value.invoice);
const cycle = computed(() =>
    quote.value.billing_cycle === 'yearly' ? 'anual' : 'mensual',
);
const money = (cents: number) =>
    new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: quote.value.currency,
    }).format(cents / 100);
const date = (value: string) =>
    new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: quote.value.timezone,
    }).format(new Date(value));
const status = computed(() =>
    invoice.value
        ? {
              draft: 'En preparación',
              issued: 'Pendiente de pago',
              overdue: 'Pago vencido',
              paid: 'Pagada',
              void: 'Anulada',
          }[invoice.value.status]
        : 'Por facturar',
);

async function createInvoice() {
    if (
        busy.value ||
        !confirmed.value ||
        invoice.value ||
        current.value.unavailable_reason
    )
        return;
    busy.value = true;
    error.value = '';
    const publicId = quote.value.public_id;
    try {
        const xsrf = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice(11);
        const response = await fetch(current.value.create_url, {
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
                confirmed: confirmed.value,
                snapshot_hash: quote.value.acceptance?.snapshot_hash,
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
                    'Espera un minuto antes de volver a intentarlo.',
                );
            throw new Error(
                response.status < 500 && body.message
                    ? body.message
                    : 'No pudimos confirmar la factura. Vuelve a intentarlo para recuperar el resultado.',
            );
        }
        if (quote.value.public_id === publicId) current.value = body.checkout;
    } catch (failure) {
        if (quote.value.public_id === publicId)
            error.value =
                failure instanceof TypeError
                    ? 'No pudimos confirmar la respuesta. Puedes volver a intentarlo; recuperaremos la misma factura si ya fue creada.'
                    : failure instanceof Error
                      ? failure.message
                      : 'No pudimos confirmar la factura. Inténtalo de nuevo.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Facturación Business" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Inicio', href: '/app' },
            { title: 'Cotizaciones', href: '/app/business/quotes' },
            { title: 'Facturación', href: current.create_url },
        ]"
    >
        <div class="mx-auto w-full max-w-5xl space-y-8 px-5 py-8 sm:px-8">
            <header class="space-y-3">
                <Link
                    :href="current.purchase_url ?? quote.url"
                    class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4"
                    >{{ current.purchase_url ? 'Volver a mi compra' : 'Volver a la cotización' }}</Link
                >
                <p class="text-sm font-medium break-words">
                    {{ quote.company_name }}
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Facturación Business
                </h1>
                <p class="text-muted-foreground">
                    {{ quote.offer_name }} · {{ quote.level_name }} · Ciclo
                    {{ cycle }}
                </p>
            </header>
            <BusinessTransitionSummary
                v-if="current.quote.snapshot.transition"
                :plan="current.quote.snapshot.transition"
            />
            <div
                class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)]"
            >
                <div class="min-w-0 space-y-8">
                    <section
                        aria-labelledby="invoice-summary"
                        class="space-y-4"
                    >
                        <h2 id="invoice-summary" class="text-lg font-semibold">
                            Importes aceptados
                        </h2>
                        <div
                            v-for="(label, kind) in {
                                recurring: `Primer período ${cycle}`,
                                one_time: 'Implementación y cargos únicos',
                            }"
                            :key="kind"
                            class="space-y-3 border-b pb-5"
                        >
                            <h3 class="font-medium">{{ label }}</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt>Importe antes de impuestos</dt>
                                    <dd class="shrink-0 tabular-nums">
                                        {{
                                            money(
                                                current.amounts.lines[kind]
                                                    .subtotal_cents,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="flex justify-between gap-3 text-muted-foreground"
                                >
                                    <dt>Impuestos</dt>
                                    <dd class="shrink-0 tabular-nums">
                                        {{
                                            money(
                                                current.amounts.lines[kind]
                                                    .tax_cents,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="flex justify-between gap-3 font-medium"
                                >
                                    <dt>Total</dt>
                                    <dd class="shrink-0 tabular-nums">
                                        {{
                                            money(
                                                current.amounts.lines[kind]
                                                    .total_cents,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Los importes incluyen los descuentos acordados. Esta
                            factura corresponde al primer período y a los cargos
                            únicos; las renovaciones se gestionan por separado.
                        </p>
                    </section>
                    <section
                        v-if="quote.acceptance"
                        class="space-y-2 text-sm"
                        aria-labelledby="accepted-terms"
                    >
                        <h2 id="accepted-terms" class="text-lg font-semibold">
                            Condiciones confirmadas
                        </h2>
                        <p>
                            {{ quote.acceptance.accepted_name }} ·
                            {{ date(quote.acceptance.accepted_at) }}
                        </p>
                        <p class="leading-6 text-muted-foreground">
                            {{ quote.snapshot.implementation.name }} ·
                            {{ quote.snapshot.implementation.hours }} horas de
                            implementación
                        </p>
                        <Link
                            :href="quote.url"
                            class="inline-flex min-h-11 items-center underline underline-offset-4"
                            >Consultar alcance y condiciones completas</Link
                        >
                    </section>
                    <section
                        v-if="invoice?.payments.length"
                        class="space-y-3"
                        aria-labelledby="payments-title"
                    >
                        <h2 id="payments-title" class="text-lg font-semibold">
                            Pagos registrados
                        </h2>
                        <ul class="divide-y text-sm">
                            <li
                                v-for="(payment, index) in invoice.payments"
                                :key="index"
                                class="flex flex-wrap justify-between gap-2 py-3"
                            >
                                <span>{{ date(payment.paid_at) }}</span
                                ><span class="tabular-nums">{{
                                    money(payment.amount_cents)
                                }}</span>
                            </li>
                        </ul>
                    </section>
                </div>
                <aside
                    class="rounded-xl border bg-card p-5 sm:p-6"
                    aria-label="Factura y pago"
                >
                    <p class="text-sm text-muted-foreground">
                        Total inicial con impuestos
                    </p>
                    <p
                        class="mt-2 text-3xl font-semibold tracking-tight tabular-nums"
                    >
                        {{ money(current.amounts.total_cents) }}
                    </p>
                    <template v-if="invoice">
                        <div role="status" class="mt-5 space-y-2 border-t pt-5">
                            <h2 class="font-semibold break-all">
                                {{ invoice.number }}
                            </h2>
                            <p class="text-sm font-medium">{{ status }}</p>
                        </div>
                        <dl class="my-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt>Pagos registrados</dt>
                                <dd class="tabular-nums">
                                    {{ money(invoice.paid_cents) }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between gap-3 font-semibold"
                            >
                                <dt>Saldo pendiente</dt>
                                <dd class="tabular-nums">
                                    {{ money(invoice.balance_cents) }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="invoice.status === 'void'"
                            class="text-sm leading-6"
                        >
                            Esta factura fue anulada. Contacta a facturación
                            para revisar la contratación.
                        </p>
                        <p
                            v-else-if="
                                invoice.status === 'paid' ||
                                invoice.balance_cents === 0
                            "
                            class="text-sm leading-6"
                        >
                            <template v-if="current.transfer">Factura cubierta. Continúa con la implementación de tus aplicaciones.</template>
                            <template v-else>No hay saldo pendiente. La activación de las soluciones y el inicio de la implementación se coordinan por separado.</template>
                        </p>
                        <BusinessTransferProofForm
                            v-else-if="current.transfer"
                            :transfer="current.transfer"
                            :purchase-id="quote.public_id"
                        />
                        <Button
                            v-else-if="invoice.payment_url"
                            as-child
                            class="min-h-11 w-full"
                            ><a
                                :href="invoice.payment_url"
                                rel="noreferrer noopener"
                                >Ir al pago</a
                            ></Button
                        >
                        <p v-else class="text-sm leading-6">
                            El enlace de pago aún no está disponible. Contacta a
                            facturación con el número de esta factura para
                            coordinar el pago.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-4 min-h-11 w-full"
                            @click="router.reload()"
                            >Actualizar estado</Button
                        >
                        <p class="mt-4 text-xs leading-5 text-muted-foreground">
                            El comprobante fiscal se gestiona por separado.
                        </p>
                        <Button
                            v-if="current.implementation_url"
                            as-child
                            class="mt-5 min-h-11 w-full"
                        >
                            <Link :href="current.implementation_url"
                                >Ver implementación</Link
                            >
                        </Button>
                    </template>
                    <div
                        v-else-if="current.unavailable_reason"
                        role="status"
                        class="mt-5 space-y-2 border-t pt-5 text-sm"
                    >
                        <h2 class="font-semibold">Se requiere una revisión</h2>
                        <p class="leading-6">
                            {{ current.unavailable_reason }}
                        </p>
                    </div>
                    <form
                        v-else
                        class="mt-5 space-y-4 border-t pt-5"
                        :aria-busy="busy"
                        @submit.prevent="createInvoice"
                    >
                        <label
                            for="confirm-invoice"
                            class="flex cursor-pointer items-start gap-3 text-sm leading-6"
                            ><input
                                id="confirm-invoice"
                                v-model="confirmed"
                                type="checkbox"
                                :disabled="busy"
                                class="mt-1 size-4 shrink-0 accent-primary"
                            /><span
                                >Confirmo la creación de la factura para
                                {{ quote.company_name }} por los importes
                                aceptados.</span
                            ></label
                        >
                        <p class="text-sm leading-6 text-muted-foreground">
                            Crear la factura no realiza un cargo automático ni
                            activa las soluciones.
                        </p>
                        <div
                            v-if="error"
                            role="alert"
                            class="space-y-2 text-sm text-destructive"
                        >
                            <p>{{ error }}</p>
                            <button
                                type="button"
                                class="min-h-11 underline underline-offset-4"
                                :disabled="busy"
                                @click="router.reload()"
                            >
                                Recargar estado
                            </button>
                        </div>
                        <Button
                            type="submit"
                            class="min-h-11 w-full"
                            :disabled="busy || !confirmed"
                            >{{
                                busy ? 'Creando factura…' : 'Crear factura'
                            }}</Button
                        >
                    </form>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
