<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import PortalSection from '@/components/portal/PortalSection.vue';
import { useDateFormatter } from '@/composables/useDateFormatter';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay } from '@/lib/portalFormat';
import type { BreadcrumbItem } from '@/types';

const { formatDate } = useDateFormatter();

type ServiceOpt = { id: number; title: string; slug?: string | null };

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string };
    subscription: null | {
        id: number;
        status: string;
        billing_cycle: string;
        currency: string;
        trial_ends_at_human?: string | null;
        period_end_human?: string | null;
    };
    filters: {
        from: string | null;
        to: string | null;
        service_id: number | null;
    };
    services: ServiceOpt[];
    summary: { total_records: number; total_quantity: string };

    by_service: Array<{
        service: { id: number; title: string; slug?: string | null };
        total_records: number;
        total_quantity: string;
        unit_name?: string | null;
    }>;

    rows: Array<{
        id: number;
        occurred_on: string;
        service: { id: number; title: string; slug?: string | null };
        quantity: string;
        unit_name?: string | null;
        meta?: any;
        created_at?: string;
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Uso', href: '/subscriber/usage' },
];

// -------------------------
// Filters
// -------------------------
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
// «Todas» es la cadena vacía: un <select> no muestra bien la opción null.
const serviceId = ref<number | ''>(props.filters.service_id || '');

const hasSubscription = computed(() => !!props.subscription?.id);

function applyFilters() {
    router.get(
        '/subscriber/usage',
        {
            from: from.value || null,
            to: to.value || null,
            service_id: serviceId.value || null,
        },
        { preserveScroll: true, preserveState: true },
    );
}

function resetFilters() {
    from.value = '';
    to.value = '';
    serviceId.value = '';
    router.get(
        '/subscriber/usage',
        {},
        { preserveScroll: true, preserveState: true },
    );
}

// -------------------------
// Helpers
// -------------------------
/** «1234.5000» → «1,234.5»; las cantidades se leen como números, no como columnas de base de datos. */
function qty(v: unknown): string {
    const n = Number(
        String(v ?? '')
            .replace(/,/g, '')
            .trim(),
    );
    return Number.isFinite(n)
        ? new Intl.NumberFormat('es-DO', { maximumFractionDigits: 2 }).format(n)
        : '—';
}

const unit = (u?: string | null) => (u ?? '').trim();
const hasFilters = computed(
    () => !!(from.value || to.value || serviceId.value),
);
</script>

<template>
    <Head title="Uso" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company.name"
            title="Uso"
            :description="
                props.subscription?.period_end_human
                    ? `Lo que tu empresa ha usado de cada aplicación. El período actual termina el ${formatDate(props.subscription.period_end_human)}.`
                    : 'Lo que tu empresa ha usado de cada aplicación.'
            "
        >
            <form
                class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_1fr_2fr_auto] md:items-end dark:border-slate-800 dark:bg-slate-950"
                @submit.prevent="applyFilters"
            >
                <label class="block text-sm">
                    <span class="font-medium text-slate-700 dark:text-slate-300"
                        >Desde</span
                    >
                    <input
                        v-model="from"
                        type="date"
                        class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                    />
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-slate-700 dark:text-slate-300"
                        >Hasta</span
                    >
                    <input
                        v-model="to"
                        type="date"
                        class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                    />
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-slate-700 dark:text-slate-300"
                        >Aplicación</span
                    >
                    <select
                        v-model="serviceId"
                        class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <option value="">Todas</option>
                        <option
                            v-for="s in props.services"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.title }}
                        </option>
                    </select>
                </label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button
                        type="submit"
                        :disabled="!hasSubscription"
                        :class="portalButton.dark"
                    >
                        Ver
                    </button>
                    <button
                        v-if="hasFilters"
                        type="button"
                        :class="portalButton.link"
                        @click="resetFilters"
                    >
                        Quitar filtros
                    </button>
                </div>
            </form>

            <p
                v-if="!hasSubscription"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
            >
                Cuando tengas una suscripción activa, aquí verás cuánto usas
                cada aplicación.
            </p>

            <template v-else>
                <PortalSection
                    heading-id="by-app-title"
                    title="Por aplicación"
                    :count="props.by_service.length"
                >
                    <p
                        v-if="props.by_service.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                    >
                        No hay uso registrado en estas fechas.
                    </p>
                    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <li
                            v-for="r in props.by_service"
                            :key="`svc-${r.service.id}`"
                            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            <h3
                                class="font-semibold break-words text-slate-950 dark:text-white"
                            >
                                {{ r.service.title || 'Aplicación' }}
                            </h3>
                            <p
                                class="mt-2 text-2xl font-semibold text-slate-950 tabular-nums dark:text-white"
                            >
                                {{ qty(r.total_quantity) }}
                                <span
                                    class="text-sm font-normal text-slate-600 dark:text-slate-400"
                                    >{{ unit(r.unit_name) }}</span
                                >
                            </p>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-400"
                            >
                                {{ r.total_records }}
                                {{
                                    r.total_records === 1
                                        ? 'registro'
                                        : 'registros'
                                }}
                            </p>
                        </li>
                    </ul>
                </PortalSection>

                <PortalSection
                    v-if="props.rows.length"
                    heading-id="rows-title"
                    title="Detalle"
                    description="Los registros más recientes de estas fechas."
                >
                    <ul
                        class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <li
                            v-for="r in props.rows"
                            :key="r.id"
                            class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block font-medium break-words text-slate-950 dark:text-white"
                                    >{{ r.service.title || 'Aplicación' }}</span
                                >
                                <span
                                    class="text-slate-600 dark:text-slate-400"
                                    >{{ formatDay(r.occurred_on) }}</span
                                >
                            </span>
                            <span
                                class="shrink-0 font-semibold text-slate-950 tabular-nums dark:text-white"
                            >
                                {{ qty(r.quantity) }} {{ unit(r.unit_name) }}
                            </span>
                        </li>
                    </ul>
                </PortalSection>
            </template>
        </PortalPage>
    </AppLayout>
</template>
