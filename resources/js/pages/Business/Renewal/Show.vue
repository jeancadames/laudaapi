<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import BusinessAmountInput from '@/components/BusinessAmountInput.vue';
import PortalPage from '@/components/portal/PortalPage.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { timezoneLabel } from '@/lib/timezoneLabel';
import type { BusinessRenewal } from '@/types/businessRenewal';

const props = defineProps<{ renewal: BusinessRenewal }>();
const current = ref(props.renewal);
const offer = computed(() => current.value.offers[0]);
const editing = ref(props.renewal.can_propose && !props.renewal.offers.length);
const busy = ref(false);
const confirmed = ref(false);
const error = ref('');
const errors = ref<Record<string, string[]>>({});
const pending = ref<{ url: string; data: Record<string, unknown> } | null>(
    null,
);
const form = reactive({
    base_cents: null as number | null,
    discount_cents: null as number | null,
    tax_bps: null as number | null,
    valid_until: '',
    commercial_note: '',
    renewal_mode: '',
    discount_duration: '',
    price_changes: '',
    tax_changes: '',
    unpaid_coverage: '',
});
const policies = [
    {
        key: 'renewal_mode',
        label: 'Forma de renovación',
        value: 'explicit_single_period',
        choice: 'Solo este período',
        text: 'Solo este período, mediante aceptación expresa',
    },
    {
        key: 'discount_duration',
        label: 'Vigencia del descuento',
        value: 'this_period_only',
        choice: 'Solo este período',
        text: 'Solo durante el período indicado',
    },
    {
        key: 'price_changes',
        label: 'Cambios de precio',
        value: 'new_acceptance_required',
        choice: 'Nueva propuesta y aceptación',
        text: 'Requieren una propuesta y aceptación nuevas',
    },
    {
        key: 'tax_changes',
        label: 'Cambios de impuestos',
        value: 'new_acceptance_required',
        choice: 'Nueva propuesta y aceptación',
        text: 'Requieren una propuesta y aceptación nuevas',
    },
    {
        key: 'unpaid_coverage',
        label: 'Cobertura sin pago completo',
        value: 'no_coverage',
        choice: 'Requiere pago completo',
        text: 'El nuevo período no concede acceso hasta confirmar su pago',
    },
] as const;
function startProposal() {
    if (offer.value?.terms.period_number !== current.value.next.period_number) {
        form.base_cents = null;
        form.discount_cents = null;
        form.tax_bps = null;
        form.valid_until = '';
        form.commercial_note = '';
        for (const policy of policies) form[policy.key] = '';
    }
    editing.value = true;
    confirmed.value = false;
}
const fieldClass =
    'min-h-11 w-full rounded-md border bg-background px-3 py-2 text-sm';
const money = (cents: number) =>
    new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: current.value.currency,
    }).format(cents / 100);
const date = (value: string) =>
    new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: current.value.timezone,
    }).format(new Date(value));
const status = computed(() => {
    if (!offer.value) return 'Pendiente de propuesta';
    if (offer.value.invoice)
        return offer.value.invoice.covered
            ? 'Factura cubierta'
            : 'Factura pendiente de pago o revisión';
    if (offer.value.acceptance) return 'Condiciones aceptadas';
    if (offer.value.superseded) return 'Propuesta sustituida';
    return new Date(offer.value.valid_until).getTime() <= Date.now()
        ? 'Propuesta vencida'
        : 'Pendiente de aceptación';
});
watch(
    () => props.renewal,
    (value) => {
        current.value = value;
        pending.value = null;
        confirmed.value = false;
        error.value = '';
        errors.value = {};
        if (!value.can_propose) editing.value = false;
    },
);
async function send(url?: string | null, data?: Record<string, unknown>) {
    if (busy.value || (!pending.value && (!url || !data || !confirmed.value)))
        return;
    pending.value ??= {
        url: url!,
        data: {
            ...data,
            confirmed: true,
            idempotency_key: crypto.randomUUID(),
        },
    };
    busy.value = true;
    error.value = '';
    errors.value = {};
    try {
        const token = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice(11);
        const response = await fetch(pending.value.url, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'error',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
            },
            body: JSON.stringify(pending.value.data),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status < 500) pending.value = null;
            errors.value = body.errors ?? {};
            throw new Error(
                response.status === 401 || response.status === 419
                    ? 'Tu sesión venció. Recarga e inicia sesión.'
                    : response.status === 429
                      ? 'Espera un minuto antes de volver a intentarlo.'
                      : response.status < 500 && body.message
                        ? body.message
                        : 'No pudimos confirmar el resultado. Reintenta o actualiza el estado.',
            );
        }
        current.value = body.renewal;
        pending.value = null;
        confirmed.value = false;
        editing.value = false;
    } catch (failure) {
        error.value =
            failure instanceof TypeError
                ? 'No pudimos confirmar la respuesta. Reintenta para recuperar la misma operación.'
                : failure instanceof Error
                  ? failure.message
                  : 'No pudimos confirmar el resultado.';
    } finally {
        busy.value = false;
    }
}
function propose() {
    return send(current.value.propose_url, {
        ...form,
        valid_until: form.valid_until ? `${form.valid_until}:00+00:00` : '',
        period_number: current.value.next.period_number,
        expected_revision: current.value.next.revision,
    });
}
function confirmOffer() {
    if (!offer.value) return;
    return send(
        offer.value.can_accept
            ? current.value.accept_url
            : current.value.invoice_url,
        { offer_id: offer.value.id, terms_hash: offer.value.terms_hash },
    );
}
</script>

<template>
    <Head title="Renovación Business" />
    <AppLayout
        :breadcrumbs="[
            {
                title: 'Inicio',
                href: current.admin ? '/dashboard' : '/app',
            },
            { title: 'Activación Business', href: current.back_url },
            { title: 'Renovación', href: '#' },
        ]"
    >
        <PortalPage
            :eyebrow="current.company_name"
            title="Renovación Business"
            description="Revisa y acepta las condiciones de cada período para continuar con las soluciones contratadas. Al aceptar se emite su factura; el acceso empieza en la fecha indicada, una vez confirmado el pago."
            :back="{ href: current.back_url, label: 'Volver a activación' }"
        >
            <div
                v-if="current.hold"
                role="status"
                class="rounded-lg border p-4 text-sm leading-6"
            >
                {{ current.hold }}
            </div>
            <div
                v-if="error"
                role="alert"
                class="space-y-3 rounded-lg border border-destructive/40 p-4 text-sm"
            >
                <p>{{ error }}</p>
                <ul
                    v-if="Object.keys(errors).length"
                    class="list-disc space-y-1 pl-5"
                >
                    <li v-for="(messages, key) in errors" :key="key">
                        {{ messages[0] }}
                    </li>
                </ul>
                <Button
                    v-if="pending"
                    variant="outline"
                    class="min-h-11"
                    :disabled="busy"
                    @click="send()"
                    >Reintentar operación</Button
                >
            </div>
            <div
                class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <section
                    aria-label="Propuesta de renovación"
                    class="min-w-0 space-y-7"
                >
                    <form
                        v-if="editing && current.can_propose"
                        class="space-y-5"
                        @submit.prevent="propose"
                    >
                        <h2 class="text-xl font-semibold">
                            Proponer período {{ current.next.period_number }}
                        </h2>
                        <p class="text-sm leading-6">
                            {{ date(current.next.period_starts_at) }} →
                            {{ date(current.next.period_ends_at) }}<br /><span
                                class="text-muted-foreground"
                                >Fechas en
                                {{ timezoneLabel(current.timezone) }}. Cada
                                período empieza el mismo día en que activaste tu
                                paquete.</span
                            >
                        </p>
                        <fieldset
                            :disabled="busy || !!pending"
                            class="space-y-5"
                        >
                            <legend class="sr-only">
                                Condiciones de renovación
                            </legend>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label
                                        for="renewal-base"
                                        class="block text-sm font-medium"
                                        >Precio recurrente antes del descuento
                                        ({{ current.currency }})</label
                                    ><BusinessAmountInput
                                        id="renewal-base"
                                        v-model="form.base_cents"
                                        :class="fieldClass"
                                        required
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Incluye el paquete y los adicionales
                                        recurrentes. Excluye implementación y
                                        pagos únicos.
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <label
                                        for="renewal-discount"
                                        class="block text-sm font-medium"
                                        >Descuento de este período ({{
                                            current.currency
                                        }})</label
                                    ><BusinessAmountInput
                                        id="renewal-discount"
                                        v-model="form.discount_cents"
                                        :class="fieldClass"
                                        required
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Escribe 0 si no corresponde. No se
                                        heredan promociones anteriores.
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <label
                                        for="renewal-tax"
                                        class="block text-sm font-medium"
                                        >Impuesto (%)</label
                                    ><BusinessAmountInput
                                        id="renewal-tax"
                                        step="0.1"
                                        v-model="form.tax_bps"
                                        :class="fieldClass"
                                        :max="100"
                                        required
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label
                                        for="renewal-valid"
                                        class="block text-sm font-medium"
                                        >Válida hasta (UTC)</label
                                    ><input
                                        id="renewal-valid"
                                        v-model="form.valid_until"
                                        type="datetime-local"
                                        :class="fieldClass"
                                        required
                                    />
                                </div>
                            </div>
                            <div
                                v-for="policy in policies"
                                :key="policy.key"
                                class="space-y-2"
                            >
                                <label
                                    :for="policy.key"
                                    class="block text-sm font-medium"
                                    >{{ policy.label }}</label
                                ><select
                                    :id="policy.key"
                                    v-model="form[policy.key]"
                                    :class="fieldClass"
                                    required
                                >
                                    <option value="" disabled>
                                        Seleccionar condición
                                    </option>
                                    <option :value="policy.value">
                                        {{ policy.choice }}
                                    </option>
                                </select>
                                <p
                                    v-if="form[policy.key]"
                                    class="text-xs leading-5 text-muted-foreground"
                                >
                                    {{ policy.text }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <label
                                    for="renewal-note"
                                    class="block text-sm font-medium"
                                    >Condiciones comerciales y motivo del
                                    precio</label
                                ><textarea
                                    id="renewal-note"
                                    v-model="form.commercial_note"
                                    :class="fieldClass"
                                    rows="4"
                                    minlength="20"
                                    maxlength="4000"
                                    required
                                />
                                <p
                                    class="text-xs leading-5 text-muted-foreground"
                                >
                                    Visible para la empresa. Explica los cambios
                                    respecto al período anterior y el alcance
                                    recurrente incluido.
                                </p>
                            </div>
                            <label
                                class="flex min-h-11 items-start gap-3 text-sm leading-6"
                                ><input
                                    v-model="confirmed"
                                    type="checkbox"
                                    class="mt-1 size-5 shrink-0"
                                />Confirmo las condiciones de este período y las
                                publico para aceptación de la empresa.</label
                            >
                        </fieldset>
                        <Button
                            type="submit"
                            class="min-h-11 w-full sm:w-auto"
                            :disabled="busy || !confirmed || !!pending"
                            >{{
                                busy ? 'Publicando…' : 'Publicar propuesta'
                            }}</Button
                        >
                    </form>
                    <section
                        v-else-if="offer"
                        aria-labelledby="terms-title"
                        class="space-y-5"
                    >
                        <div class="space-y-1">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Período {{ offer.terms.period_number }} ·
                                Revisión {{ offer.revision }}
                            </p>
                            <h2 id="terms-title" class="text-xl font-semibold">
                                Condiciones de renovación
                            </h2>
                        </div>
                        <dl class="space-y-4 border-y py-5 text-sm">
                            <div>
                                <dt class="text-muted-foreground">
                                    Período
                                    {{
                                        offer.terms.billing_cycle === 'yearly'
                                            ? 'anual'
                                            : 'mensual'
                                    }}
                                </dt>
                                <dd class="mt-1 font-medium">
                                    {{ date(offer.terms.period_starts_at) }} →
                                    {{ date(offer.terms.period_ends_at) }}
                                </dd>
                                <dd class="mt-1 text-xs text-muted-foreground">
                                    Fechas en
                                    {{ timezoneLabel(current.timezone) }} · Si
                                    el día no existe en el mes, se usa el último
                                    día
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Vigencia de la propuesta
                                </dt>
                                <dd class="mt-1">
                                    {{ date(offer.valid_until) }}
                                </dd>
                            </div>
                            <div v-for="policy in policies" :key="policy.key">
                                <dt class="text-muted-foreground">
                                    {{ policy.label }}
                                </dt>
                                <dd class="mt-1 leading-6">
                                    {{ policy.text }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            class="text-sm leading-6 break-words whitespace-pre-wrap"
                        >
                            {{ offer.terms.commercial_note }}
                        </p>
                        <p class="text-sm leading-6 text-muted-foreground">
                            El precio y el impuesto quedan fijos para este
                            período. El descuento vence al terminarlo. Cada
                            período posterior necesita otra propuesta aceptada.
                            Aceptar o pagar después del inicio no modifica las
                            fechas ni recupera acceso pasado. No se autoriza un
                            débito automático.
                        </p>
                        <div
                            v-if="offer.acceptance"
                            class="space-y-1 border-t pt-4 text-sm"
                        >
                            <p class="font-medium">
                                Aceptado por {{ offer.acceptance.name }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ date(offer.acceptance.at) }}
                            </p>
                        </div>
                        <Button
                            v-if="current.can_propose"
                            variant="outline"
                            class="min-h-11"
                            :disabled="busy || !!pending"
                            @click="startProposal"
                            >{{
                                current.next.period_number ===
                                offer.terms.period_number
                                    ? 'Revisar propuesta'
                                    : 'Preparar siguiente período'
                            }}</Button
                        >
                    </section>
                    <section v-else class="space-y-3">
                        <h2 class="text-xl font-semibold">
                            Pendiente de propuesta
                        </h2>
                        <p
                            v-if="current.admin"
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            Administración debe completar y publicar las
                            condiciones antes de que puedas aceptarlas. Al
                            vencer la cobertura actual, se requiere una
                            renovación pagada para continuar.
                        </p>
                        <p
                            v-else
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            LAUDA está preparando las condiciones de tu próximo
                            período. Cuando estén listas podrás revisarlas y
                            aceptarlas aquí. Al terminar el período pagado, para
                            seguir usando tus aplicaciones necesitas renovar y
                            pagar el siguiente.
                        </p>
                    </section>
                </section>
                <aside
                    aria-label="Importe y acciones"
                    class="min-w-0 space-y-5 rounded-xl border bg-card p-5 sm:p-6"
                >
                    <h2 class="text-lg font-semibold" aria-live="polite">
                        {{ status }}
                    </h2>
                    <template v-if="offer && !editing">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt>Precio recurrente</dt>
                                <dd>{{ money(offer.terms.base_cents) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>Descuento del período</dt>
                                <dd>
                                    −{{ money(offer.terms.discount_cents) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>Base neta</dt>
                                <dd>{{ money(offer.terms.subtotal_cents) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>
                                    Impuesto ({{ offer.terms.tax_bps / 100 }}%)
                                </dt>
                                <dd>{{ money(offer.terms.tax_cents) }}</dd>
                            </div>
                            <div
                                class="flex justify-between gap-3 border-t pt-3 text-lg font-semibold"
                            >
                                <dt>Total</dt>
                                <dd>{{ money(offer.terms.total_cents) }}</dd>
                            </div>
                        </dl>
                        <div
                            v-if="offer.invoice"
                            class="space-y-2 border-t pt-4 text-sm"
                        >
                            <p class="font-medium break-all">
                                {{ offer.invoice.number }}
                            </p>
                            <p>
                                Pago confirmado:
                                {{ money(offer.invoice.paid_cents) }}
                            </p>
                            <p>
                                Saldo: {{ money(offer.invoice.balance_cents) }}
                            </p>
                            <p class="leading-6 text-muted-foreground">
                                {{
                                    offer.invoice.covered
                                        ? 'El pago cubre la factura. El acceso corresponde a las fechas del período y requiere mantener vigentes la suscripción y los vínculos.'
                                        : 'La factura requiere pago completo y conciliación para cubrir este período.'
                                }}
                            </p>
                        </div>
                        <form
                            v-if="offer.can_accept || offer.can_invoice"
                            class="space-y-4"
                            @submit.prevent="confirmOffer"
                        >
                            <label
                                class="flex min-h-11 items-start gap-3 text-sm leading-6"
                                ><input
                                    v-model="confirmed"
                                    type="checkbox"
                                    class="mt-1 size-5 shrink-0"
                                    :disabled="busy || !!pending"
                                /><span>{{
                                    offer.can_accept
                                        ? 'Acepto el período, precio, descuento, impuesto y condiciones publicados, y autorizo su factura. Entiendo que el acceso requiere pago y que no autorizo débitos automáticos.'
                                        : 'Confirmo la emisión de una factura con estas condiciones aceptadas por la empresa.'
                                }}</span></label
                            >
                            <Button
                                type="submit"
                                class="min-h-11 w-full"
                                :disabled="busy || !confirmed || !!pending"
                                >{{
                                    busy
                                        ? 'Guardando…'
                                        : offer.can_accept
                                          ? 'Aceptar renovación'
                                          : 'Emitir factura de renovación'
                                }}</Button
                            >
                        </form>
                    </template>
                    <p
                        v-else-if="current.admin"
                        class="text-sm leading-6 text-muted-foreground"
                    >
                        Completa las condiciones para publicar el importe que
                        revisará la empresa. La publicación no crea una factura.
                    </p>
                    <p v-else class="text-sm leading-6 text-muted-foreground">
                        Todavía no hay un importe para revisar. Vuelve a esta
                        página más adelante o pulsa «Actualizar estado».
                    </p>
                    <Button
                        variant="outline"
                        class="min-h-11 w-full"
                        :disabled="busy"
                        @click="router.reload()"
                        >Actualizar estado</Button
                    >
                    <p class="text-xs leading-5 text-muted-foreground">
                        La implementación y los cargos únicos no se vuelven a
                        facturar.
                    </p>
                </aside>
            </div>
            <section
                v-if="current.offers.length > 1"
                aria-labelledby="history-title"
                class="space-y-4 border-t pt-6"
            >
                <h2 id="history-title" class="text-lg font-semibold">
                    Historial de propuestas
                </h2>
                <details
                    v-for="previous in current.offers.slice(1)"
                    :key="previous.id"
                    class="border-b pb-4 text-sm"
                >
                    <summary class="min-h-11 cursor-pointer py-3">
                        Período {{ previous.terms.period_number }} · Revisión
                        {{ previous.revision }} ·
                        {{ money(previous.terms.total_cents) }} ·
                        {{
                            previous.invoice
                                ? 'Facturada'
                                : previous.acceptance
                                  ? 'Aceptada'
                                  : previous.superseded
                                    ? 'Sustituida'
                                    : 'Publicada'
                        }}
                    </summary>
                    <div class="space-y-2 leading-6">
                        <p>
                            {{ date(previous.terms.period_starts_at) }} →
                            {{ date(previous.terms.period_ends_at) }}
                        </p>
                        <p>
                            Base: {{ money(previous.terms.base_cents) }} ·
                            Descuento:
                            {{ money(previous.terms.discount_cents) }} ·
                            Impuesto: {{ money(previous.terms.tax_cents) }}
                        </p>
                        <p class="break-words whitespace-pre-wrap">
                            {{ previous.terms.commercial_note }}
                        </p>
                        <p v-if="previous.acceptance">
                            Aceptada por {{ previous.acceptance.name }} el
                            {{ date(previous.acceptance.at) }}
                        </p>
                        <p v-if="previous.invoice">
                            {{ previous.invoice.number }} · Saldo
                            {{ money(previous.invoice.balance_cents) }}
                        </p>
                    </div>
                </details>
                <p class="text-xs text-muted-foreground">
                    Se muestran las 20 propuestas más recientes. El historial
                    completo se conserva.
                </p>
            </section>
        </PortalPage>
    </AppLayout>
</template>
