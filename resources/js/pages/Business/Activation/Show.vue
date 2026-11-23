<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BusinessTransitionSummary from '@/components/BusinessTransitionSummary.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { timezoneLabel } from '@/lib/timezoneLabel';
import type { BusinessActivation } from '@/types/businessActivation';
const props = defineProps<{
    activation: BusinessActivation;
    idempotencyKey: string;
}>();
const current = ref(props.activation);
const notes = ref<Record<string, string>>({});
const confirmed = ref(false);
const busy = ref(false);
const error = ref('');
const pending = ref<Record<string, unknown> | null>(null);
const quote = computed(() => current.value.quote);
const labels = {
    pending: 'Preparación de activación',
    active: 'Cobertura activa',
    review: 'Cobertura en revisión',
    expired: 'Período finalizado',
};
const readyToSubmit = computed(() =>
    Object.keys(current.value.solutions).every(
        (code) => (notes.value[code] ?? '').trim().length >= 10,
    ),
);
const date = (value: string) =>
    new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: quote.value.timezone,
    }).format(new Date(value));
const money = (cents: number) =>
    new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: quote.value.currency,
    }).format(cents / 100);
watch(
    () => props.activation,
    (value) => {
        current.value = value;
        confirmed.value = false;
        pending.value = null;
        error.value = '';
    },
);
async function activate() {
    if (
        busy.value ||
        !confirmed.value ||
        !readyToSubmit.value ||
        !current.value.can_activate ||
        !current.value.action_url
    )
        return;
    busy.value = true;
    error.value = '';
    pending.value ??= {
        confirmed: true,
        snapshot_hash: quote.value.snapshot_hash,
        idempotency_key: props.idempotencyKey,
        implementation_version: current.value.implementation_version,
        technical_evidence: { ...notes.value },
    };
    try {
        const xsrf = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice(11);
        const response = await fetch(current.value.action_url, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'error',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(xsrf ?? ''),
            },
            body: JSON.stringify(pending.value),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status < 500) pending.value = null;
            throw new Error(
                response.status === 401 || response.status === 419
                    ? 'Tu sesión venció. Recarga e inicia sesión.'
                    : response.status === 429
                      ? 'Espera un minuto antes de volver a intentarlo.'
                      : response.status < 500 && body.message
                        ? body.message
                        : 'No pudimos confirmar la activación. Reintenta o actualiza el estado.',
            );
        }
        current.value = body.activation;
        pending.value = null;
    } catch (failure) {
        error.value =
            failure instanceof TypeError
                ? 'No pudimos confirmar la respuesta. Reintenta para recuperar la misma activación.'
                : failure instanceof Error
                  ? failure.message
                  : 'No pudimos confirmar la activación.';
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <Head title="Activación Business" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Implementación', href: current.back_url },
            { title: 'Activación Business', href: current.action_url ?? '#' },
        ]"
    >
        <div class="mx-auto w-full max-w-5xl space-y-8 px-5 py-8 sm:px-8">
            <header class="space-y-3">
                <Link
                    :href="current.back_url"
                    class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4"
                    >Volver a implementación</Link
                >
                <p class="text-sm font-medium break-words">
                    {{ quote.company_name }}
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Activación Business
                </h1>
                <p class="text-muted-foreground">
                    {{ quote.offer_name }} · {{ quote.level_name }} · Ciclo
                    {{ quote.billing_cycle === 'yearly' ? 'anual' : 'mensual' }}
                </p>
            </header>
            <div
                class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)]"
            >
                <section
                    aria-labelledby="solutions-title"
                    class="min-w-0 space-y-5"
                >
                    <BusinessTransitionSummary
                        v-if="current.quote.snapshot.transition"
                        :plan="current.quote.snapshot.transition"
                        :executed-at="current.contract?.activated_at"
                    />
                    <h2 id="solutions-title" class="text-lg font-semibold">
                        Soluciones incluidas
                    </h2>
                    <ul class="divide-y border-y">
                        <li
                            v-for="(solution, code) in current.solutions"
                            :key="code"
                            class="space-y-2 py-4"
                        >
                            <h3 class="font-medium">{{ solution.name }}</h3>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{
                                    solution.ready
                                        ? 'Vinculación bilateral preparada.'
                                        : solution.reason
                                }}
                            </p>
                            <p
                                v-if="current.contract"
                                class="text-sm leading-6 break-words whitespace-pre-wrap"
                            >
                                {{
                                    current.contract.technical_evidence[code]
                                        ?.note
                                }}
                            </p>
                        </li>
                    </ul>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Las soluciones están incluidas en el importe contractual
                        del paquete. El acceso también requiere que cada persona
                        tenga su cuenta y permisos locales vigentes.
                    </p>
                    <div
                        v-if="current.reasons.length"
                        role="status"
                        class="space-y-3 rounded-lg border p-5"
                    >
                        <h2 class="font-semibold">
                            Pendientes antes de continuar
                        </h2>
                        <ul class="list-disc space-y-2 pl-5 text-sm leading-6">
                            <li v-for="reason in current.reasons" :key="reason">
                                {{ reason }}
                            </li>
                        </ul>
                    </div>
                </section>
                <aside
                    class="min-w-0 space-y-5 rounded-xl border bg-card p-5 sm:p-6"
                    aria-label="Contrato y activación"
                >
                    <h2
                        class="text-xl font-semibold"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        {{ labels[current.state] }}
                    </h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt>Recurrente antes de impuestos</dt>
                            <dd class="shrink-0 tabular-nums">
                                {{ money(current.recurring.subtotal_cents) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt>Impuestos</dt>
                            <dd class="shrink-0 tabular-nums">
                                {{ money(current.recurring.tax_cents) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-3 border-t pt-3 font-semibold"
                        >
                            <dt>
                                Total
                                {{
                                    quote.billing_cycle === 'yearly'
                                        ? 'anual'
                                        : 'mensual'
                                }}
                            </dt>
                            <dd class="shrink-0 tabular-nums">
                                {{ money(current.recurring.total_cents) }}
                            </dd>
                        </div>
                    </dl>
                    <p class="text-sm leading-6">
                        El primer período ya está incluido en la factura
                        <span class="font-medium break-all">{{
                            current.invoice.number
                        }}</span
                        >. Esta acción no genera otra factura.
                    </p>
                    <div
                        v-if="current.contract"
                        class="space-y-3 border-t pt-5 text-sm"
                    >
                        <p class="font-semibold">
                            Contrato #{{ current.contract.id }}
                        </p>
                        <p>
                            Activado por {{ current.contract.activated_name }}.
                        </p>
                        <p>
                            Activación inicial:
                            {{ date(current.contract.activated_at) }}
                        </p>
                        <p class="text-muted-foreground">
                            Fechas del contrato en
                            {{ timezoneLabel(quote.timezone) }}.
                        </p>
                        <p v-if="current.current_period">
                            Período {{ current.current_period.number }} desde
                            {{ date(current.current_period.starts_at) }}
                        </p>
                        <p>
                            Fin del período:
                            {{
                                date(
                                    current.current_period?.ends_at ??
                                        current.contract.period_ends_at,
                                )
                            }}
                        </p>
                        <p
                            v-if="current.state === 'expired'"
                            class="font-medium"
                        >
                            El período contratado terminó. Contacta a LAUDA para
                            revisar la renovación.
                        </p>
                        <p
                            v-if="current.state === 'review'"
                            class="font-medium"
                        >
                            Se requiere revisar el pago, la suscripción o los
                            vínculos de acceso.
                        </p>
                    </div>
                    <form
                        v-else-if="current.admin && current.can_activate"
                        class="space-y-4 border-t pt-5"
                        :aria-busy="busy"
                        @submit.prevent="activate"
                    >
                        <h3 class="font-semibold">Comprobación técnica</h3>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Registra las verificaciones realizadas en cada
                            solución. La empresa podrá consultar esta evidencia.
                        </p>
                        <div
                            v-for="(solution, code) in current.solutions"
                            :key="code"
                            class="space-y-2"
                        >
                            <label
                                :for="`technical-${code}`"
                                class="text-sm font-medium"
                                >{{ solution.name }}</label
                            >
                            <textarea
                                :id="`technical-${code}`"
                                v-model="notes[code]"
                                required
                                minlength="10"
                                maxlength="2000"
                                rows="3"
                                :disabled="busy || !!pending"
                                class="w-full rounded-md border bg-background p-3 text-sm leading-6"
                            />
                        </div>
                        <label
                            class="flex min-h-11 cursor-pointer items-start gap-3 text-sm leading-6"
                            ><input
                                v-model="confirmed"
                                type="checkbox"
                                :disabled="busy || !!pending"
                                class="mt-1 size-4 shrink-0 accent-primary"
                            /><span
                                >Confirmo las verificaciones y el inicio del
                                período contratado desde ahora.</span
                            ></label
                        >
                        <p
                            v-if="error"
                            role="alert"
                            class="text-sm leading-6 text-destructive"
                        >
                            {{ error }}
                        </p>
                        <Button
                            type="submit"
                            class="min-h-11 w-full"
                            :disabled="busy || !confirmed || !readyToSubmit"
                            >{{
                                busy
                                    ? 'Activando…'
                                    : pending
                                      ? 'Reintentar activación'
                                      : 'Activar contrato Business'
                            }}</Button
                        >
                    </form>
                    <p
                        v-else-if="!current.contract"
                        class="text-sm leading-6 text-muted-foreground"
                    >
                        {{
                            current.admin
                                ? 'Resuelve los pendientes y actualiza el estado para continuar.'
                                : 'LAUDA completará la revisión técnica antes de confirmar la activación.'
                        }}
                    </p>
                    <Button
                        variant="outline"
                        class="min-h-11 w-full"
                        :disabled="busy"
                        @click="router.reload()"
                        >Actualizar estado</Button
                    >
                    <p class="text-xs leading-5 text-muted-foreground">
                        La renovación automática no está habilitada. Al
                        finalizar este período, se requiere una revisión
                        comercial para continuar.
                    </p>
                    <Link
                        v-if="current.renewal_url"
                        :href="current.renewal_url"
                        class="inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4"
                        >Revisar renovación</Link
                    >
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
