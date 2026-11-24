<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import PortalSection from '@/components/portal/PortalSection.vue';
import { useDateFormatter } from '@/composables/useDateFormatter';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { formatDate } = useDateFormatter();

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string };
    subscription: null | {
        id: number;
        status: string;
        billing_cycle: string;
        currency: string;
        trial_ends_at_human?: string | null;
        period_start_human?: string | null;
        period_end_human?: string | null;
        starts_at_human?: string | null;
        ends_at_human?: string | null;
    };
    items: Array<any>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Mi suscripción', href: '/subscriber/subscription' },
];

function label(status: string) {
    const s = (status ?? '').toLowerCase();
    if (s === 'trialing' || s === 'trial') return 'En prueba';
    if (s === 'active') return 'Activa';
    if (s === 'past_due') return 'Pago pendiente';
    if (s === 'pending' || s === 'pending_payment') return 'Pendiente de pago';
    if (s === 'cancelled' || s === 'canceled') return 'Cancelada';
    if (s === 'expired') return 'Vencida';
    if (s === 'suspended' || s === 'paused') return 'En pausa';
    return 'Sin estado';
}

/** Verde = activa · ámbar = en prueba o pendiente · gris = terminada. */
function tone(status: string) {
    const s = (status ?? '').toLowerCase();
    if (s === 'active')
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    if (
        [
            'trialing',
            'trial',
            'past_due',
            'pending',
            'pending_payment',
        ].includes(s)
    )
        return 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200';
    return 'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300';
}

const cycle = (value: string) =>
    ({ monthly: 'Mensual', yearly: 'Anual', annual: 'Anual' })[
        String(value ?? '').toLowerCase()
    ] ?? '—';
</script>

<template>
    <Head title="Mi suscripción" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company.name"
            title="Mi suscripción"
            description="El estado de tu suscripción y las aplicaciones que incluye."
        >
            <div
                v-if="!props.subscription"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-950"
            >
                <h2 class="font-semibold text-slate-950 dark:text-white">
                    Todavía no tienes una suscripción
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Arma tu paquete con las aplicaciones que necesitas y verás
                    el precio antes de contratar.
                </p>
                <Link
                    href="/app/business/self-service"
                    class="mt-4"
                    :class="portalButton.primary"
                    >Armar mi paquete</Link
                >
            </div>

            <dl
                v-else
                class="grid gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 shadow-sm sm:grid-cols-3 dark:border-slate-800 dark:bg-slate-800"
            >
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-600 dark:text-slate-400">
                        Estado
                    </dt>
                    <dd class="mt-1.5">
                        <span
                            class="rounded-full px-2.5 py-1 text-sm font-medium"
                            :class="tone(props.subscription.status)"
                            >{{ label(props.subscription.status) }}</span
                        >
                    </dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-600 dark:text-slate-400">
                        Facturación
                    </dt>
                    <dd
                        class="mt-1 font-semibold text-slate-950 dark:text-white"
                    >
                        {{ cycle(props.subscription.billing_cycle) }}
                    </dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-600 dark:text-slate-400">
                        {{
                            props.subscription.trial_ends_at_human
                                ? 'La prueba termina'
                                : 'Período actual'
                        }}
                    </dt>
                    <dd
                        class="mt-1 font-semibold text-slate-950 dark:text-white"
                    >
                        <template v-if="props.subscription.trial_ends_at_human">
                            {{
                                formatDate(
                                    props.subscription.trial_ends_at_human,
                                )
                            }}
                        </template>
                        <template
                            v-else-if="
                                props.subscription.period_start_human ||
                                props.subscription.period_end_human
                            "
                        >
                            {{
                                formatDate(
                                    props.subscription.period_start_human,
                                ) ?? '—'
                            }}
                            al
                            {{
                                formatDate(
                                    props.subscription.period_end_human,
                                ) ?? '—'
                            }}
                        </template>
                        <template v-else>—</template>
                    </dd>
                </div>
            </dl>

            <PortalSection
                v-if="props.subscription"
                heading-id="items-title"
                title="Incluye"
                :count="props.items.length"
                description="Las aplicaciones de tu suscripción."
            >
                <p
                    v-if="props.items.length === 0"
                    class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                >
                    Tu suscripción todavía no tiene aplicaciones.
                </p>
                <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <li
                        v-for="it in props.items"
                        :key="it.id"
                        class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <h3
                                class="font-semibold break-words text-slate-950 dark:text-white"
                            >
                                {{ it.service?.title ?? 'Aplicación' }}
                            </h3>
                            <span
                                v-if="it.status"
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="tone(it.status)"
                                >{{ label(it.status) }}</span
                            >
                        </div>
                        <p
                            v-if="it.service?.short_description"
                            class="text-sm text-slate-600 dark:text-slate-400"
                        >
                            {{ it.service.short_description }}
                        </p>
                        <p
                            v-if="(it.quantity ?? 1) > 1"
                            class="text-sm text-slate-600 dark:text-slate-400"
                        >
                            Cantidad: {{ it.quantity }}
                        </p>
                    </li>
                </ul>
            </PortalSection>
        </PortalPage>
    </AppLayout>
</template>
