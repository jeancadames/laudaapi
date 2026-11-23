<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type {
    BusinessImplementation,
    ImplementationAction,
} from '@/types/businessImplementation';

const props = defineProps<{
    implementation: BusinessImplementation;
    idempotencyKey: string;
}>();
const current = ref(props.implementation);
const key = ref(props.idempotencyKey);
const action = ref<ImplementationAction | ''>('');
const note = ref('');
const confirmed = ref(false);
const busy = ref(false);
const error = ref('');
const pending = ref<Record<string, unknown> | null>(null);
const labels: Record<ImplementationAction, string> = {
    request: 'Solicitar implementación',
    start: 'Iniciar implementación',
    block: 'Registrar bloqueo',
    resume: 'Reanudar implementación',
    deliver: 'Registrar entrega',
    complete: 'Confirmar conformidad',
    request_changes: 'Solicitar ajustes',
    cancel: 'Cancelar orden',
};
const quote = computed(() => current.value.quote);
const back = computed(() =>
    current.value.admin
        ? '/admin/business-implementations'
        : quote.value.checkout_url!,
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
const oneTimeAddons = computed(() =>
    Object.values(quote.value.snapshot.addons).filter(
        (item) => !item.recurring,
    ),
);
function reset() {
    action.value =
        current.value.actions.length === 1 ? current.value.actions[0] : '';
    note.value = '';
    confirmed.value = false;
    pending.value = null;
    error.value = '';
}
reset();
watch(
    () => props.implementation,
    (value) => {
        current.value = value;
        key.value = props.idempotencyKey;
        reset();
    },
);
async function submit() {
    if (
        busy.value ||
        !confirmed.value ||
        !action.value ||
        note.value.trim().length < 3
    )
        return;
    busy.value = true;
    error.value = '';
    pending.value ??= {
        action: action.value,
        note: note.value,
        confirmed: true,
        version: current.value.order?.version ?? 0,
        snapshot_hash: quote.value.snapshot_hash,
        idempotency_key: key.value,
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
                response.status === 419 || response.status === 401
                    ? 'Tu sesión venció. Recarga e inicia sesión para continuar.'
                    : response.status === 429
                      ? 'Espera un minuto antes de volver a intentarlo.'
                      : response.status < 500 && body.message
                        ? body.message
                        : 'No pudimos confirmar el resultado. Reintenta la misma acción o actualiza el estado.',
            );
        }
        current.value = body.implementation;
        key.value = body.idempotency_key;
        reset();
    } catch (failure) {
        error.value =
            failure instanceof TypeError
                ? 'No pudimos confirmar la respuesta. Reintenta para recuperar el resultado sin duplicar la acción.'
                : failure instanceof Error
                  ? failure.message
                  : 'No pudimos guardar el cambio.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Implementación Business" />
    <AppLayout
        :breadcrumbs="[
            {
                title: current.admin
                    ? 'Implementaciones Business'
                    : 'Facturación',
                href: back,
            },
            { title: 'Implementación', href: current.action_url },
        ]"
    >
        <div class="mx-auto w-full max-w-5xl space-y-8 px-5 py-8 sm:px-8">
            <header class="space-y-3">
                <Link
                    :href="back"
                    class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4"
                    >{{
                        current.admin
                            ? 'Volver a implementaciones'
                            : 'Volver a facturación'
                    }}</Link
                >
                <p class="text-sm font-medium break-words">
                    {{ quote.company_name }}
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Implementación Business
                </h1>
                <p class="text-muted-foreground">
                    {{ quote.offer_name }} · {{ quote.level_name }}
                </p>
            </header>
            <div
                class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)]"
            >
                <section
                    aria-labelledby="scope-title"
                    class="min-w-0 space-y-4 lg:col-start-1 lg:row-start-1"
                >
                    <h2 id="scope-title" class="text-lg font-semibold">
                        Alcance contratado
                    </h2>
                    <div class="space-y-3 border-y py-5">
                        <p class="font-medium">
                            {{ quote.snapshot.implementation.name }} ·
                            {{ quote.snapshot.implementation.hours }} horas
                            incluidas
                        </p>
                        <p
                            class="text-sm leading-6 break-words whitespace-pre-wrap"
                        >
                            {{ quote.snapshot.implementation.scope }}
                        </p>
                        <ul
                            v-if="oneTimeAddons.length"
                            class="space-y-2 text-sm"
                        >
                            <li
                                v-for="(item, index) in oneTimeAddons"
                                :key="index"
                            >
                                {{ item.name }} · {{ item.quantity }} bloques
                                adicionales contratados
                            </li>
                        </ul>
                    </div>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{
                            current.order
                                ? 'El seguimiento conserva las condiciones aceptadas por tu empresa.'
                                : current.guided ? 'Revisa la configuración de las aplicaciones vinculadas. Tu conformidad activa el paquete contratado.'
                                : 'Solicita la coordinación del alcance aceptado. El inicio requiere que la factura esté cubierta.'
                        }}
                    </p>
                    <details class="rounded-lg border p-4">
                        <summary
                            class="min-h-11 cursor-pointer text-sm font-medium"
                        >
                            Soluciones y condiciones aceptadas
                        </summary>
                        <ul class="my-3 space-y-2 text-sm">
                            <li
                                v-for="(product, code) in quote.snapshot
                                    .products"
                                :key="code"
                            >
                                {{ product.name }}
                            </li>
                        </ul>
                        <p
                            class="text-sm leading-6 break-words whitespace-pre-wrap"
                        >
                            {{ quote.snapshot.terms }}
                        </p>
                    </details>
                </section>
                <aside
                    class="min-w-0 space-y-5 rounded-xl border bg-card p-5 sm:p-6 lg:col-start-2 lg:row-span-2 lg:row-start-1"
                    aria-label="Estado y acciones"
                >
                    <div role="status" class="space-y-2">
                        <p class="text-sm text-muted-foreground">
                            {{
                                current.order
                                    ? `Orden #${current.order.id}`
                                    : current.guided ? 'Autoservicio' : 'Por solicitar'
                            }}
                        </p>
                        <h2 class="text-xl font-semibold">
                            {{
                                current.order?.status_label ??
                                (current.guided ? 'Implementación guiada' : 'Coordinar implementación')
                            }}
                        </h2>
                        <p v-if="current.order && !current.guided" class="text-sm">
                            Responsable:
                            {{
                                current.order.assigned_name ??
                                'Pendiente de asignación'
                            }}
                        </p>
                    </div>
                    <div class="space-y-2 border-y py-4 text-sm">
                        <p class="font-medium break-all">
                            {{ current.invoice.number }}
                        </p>
                        <p>
                            Saldo pendiente:
                            <span class="font-medium tabular-nums">{{
                                money(current.invoice.balance_cents)
                            }}</span>
                        </p>
                        <p v-if="current.payment_hold" class="leading-6">
                            {{ current.payment_hold }}
                        </p>
                        <p v-else class="text-muted-foreground">
                            Factura cubierta.
                        </p>
                    </div>
                    <form
                        v-if="current.actions.length"
                        class="space-y-4"
                        :aria-busy="busy"
                        @submit.prevent="submit"
                    >
                        <div class="space-y-2">
                            <label
                                for="implementation-action"
                                class="text-sm font-medium"
                                >Acción</label
                            >
                            <select
                                id="implementation-action"
                                v-model="action"
                                :disabled="busy || !!pending"
                                class="min-h-11 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option disabled value="">
                                    Selecciona una acción
                                </option>
                                <option
                                    v-for="item in current.actions"
                                    :key="item"
                                    :value="item"
                                >
                                    {{ labels[item] }}
                                </option>
                            </select>
                        </div>
                        <p
                            v-if="action === 'start' || action === 'resume'"
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            Quedarás como responsable de esta implementación.
                        </p>
                        <p v-if="action === 'cancel'" class="text-sm leading-6">
                            Cancelar cierra esta orden de trabajo. La factura y
                            cualquier devolución requieren gestión separada con
                            facturación.
                        </p>
                        <div class="space-y-2">
                            <label
                                for="implementation-note"
                                class="text-sm font-medium"
                                >{{
                                    action === 'deliver'
                                        ? 'Evidencia de entrega'
                                        : current.guided ? '¿Qué configuración revisaste?' : 'Detalle del cambio'
                                }}</label
                            >
                            <textarea
                                id="implementation-note"
                                v-model="note"
                                rows="4"
                                maxlength="4000"
                                minlength="3"
                                required
                                :disabled="busy || !!pending"
                                aria-describedby="note-help"
                                class="w-full rounded-md border bg-background p-3 text-sm leading-6"
                            />
                            <p
                                id="note-help"
                                class="text-xs leading-5 text-muted-foreground"
                            >
                                Visible para la empresa y LAUDA. Describe el
                                trabajo, la entrega o el motivo del cambio.
                            </p>
                        </div>
                        <label
                            class="flex min-h-11 cursor-pointer items-start gap-3 text-sm leading-6"
                        >
                            <input
                                v-model="confirmed"
                                type="checkbox"
                                :disabled="busy || !!pending"
                                class="mt-1 size-4 shrink-0 accent-primary"
                            />
                            <span v-if="current.guided">Doy mi conformidad a la implementación y autorizo activar el paquete de {{ quote.company_name }}.</span>
                            <span v-else
                                >Confirmo esta acción para
                                {{ quote.company_name }}.</span
                            >
                        </label>
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
                            :disabled="
                                busy ||
                                !confirmed ||
                                !action ||
                                note.trim().length < 3
                            "
                            >{{
                                busy
                                    ? 'Guardando…'
                                    : pending
                                      ? 'Reintentar acción'
                                      : action
                                        ? labels[action]
                                        : 'Guardar cambio'
                            }}</Button
                        >
                    </form>
                    <p v-else class="text-sm leading-6 text-muted-foreground">
                        {{
                            current.order?.status === 'completed'
                                ? 'La empresa confirmó la entrega del alcance contratado.'
                                : current.order?.status === 'cancelled'
                                  ? 'La orden está cerrada y conserva su historial.'
                                  : 'No hay acciones disponibles para tu cuenta en este estado.'
                        }}
                    </p>
                    <Button
                        variant="outline"
                        class="min-h-11 w-full"
                        :disabled="busy"
                        @click="router.reload()"
                        >Actualizar estado</Button
                    >
                    <Button v-if="current.purchase_url" as-child variant="outline" class="min-h-11 w-full">
                        <Link :href="current.purchase_url">{{ current.order?.status === 'completed' ? 'Ver mis aplicaciones' : 'Volver a mi compra' }}</Link>
                    </Button>
                    <p v-else class="text-xs leading-5 text-muted-foreground">
                        La activación de las soluciones y la renovación se
                        coordinan por separado.
                    </p>
                    <Button
                        v-if="current.activation_url"
                        as-child
                        class="min-h-11 w-full"
                        variant="outline"
                    >
                        <Link :href="current.activation_url"
                            >Ver activación Business</Link
                        >
                    </Button>
                </aside>
                <section
                    v-if="current.order"
                    aria-labelledby="history-title"
                    class="min-w-0 space-y-4 lg:col-start-1 lg:row-start-2"
                >
                    <h2 id="history-title" class="text-lg font-semibold">
                        Historial de implementación
                    </h2>
                    <ol class="divide-y border-y">
                        <li
                            v-for="event in current.order.events"
                            :key="event.version"
                            class="space-y-2 py-5"
                        >
                            <h3 class="font-medium">
                                {{ event.status_label }}
                            </h3>
                            <p class="text-xs leading-5 text-muted-foreground">
                                {{ event.actor_name }} ·
                                {{ date(event.at) }}
                            </p>
                            <p
                                class="text-sm leading-6 break-words whitespace-pre-wrap"
                            >
                                {{ event.note }}
                            </p>
                        </li>
                    </ol>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
