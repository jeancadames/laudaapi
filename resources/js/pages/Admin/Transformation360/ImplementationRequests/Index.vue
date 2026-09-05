<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BriefcaseBusiness,
    Building2,
    Clock3,
    FileCheck2,
    Filter,
    Search,
    UserRoundCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Person = {
    name: string | null;
    email: string | null;
};

type QueueRequest = {
    id: number;
    company: {
        id: number;
        name: string;
    };
    assessment_id: number;
    plan_id: number;
    phase_capability_id: number;
    capability_key: string;
    capability_label: string;
    attempt: number;
    status: string;
    status_label: string;
    tenant_note: string | null;
    requested_at: string | null;
    requested_by: Person | null;
    assigned_to: Person | null;
    detail_url: string;
};

type Option = {
    value?: string;
    key?: string;
    label: string;
};

const props = defineProps<{
    requests: QueueRequest[];
    filters: {
        status: string | null;
        capability: string | null;
        search: string | null;
    };
    status_options: Option[];
    capability_options: Option[];
    summary: {
        total: number;
        requested: number;
        under_review: number;
        definition: number;
        awaiting_tenant: number;
    };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const capability = ref(props.filters.capability ?? '');

const breadcrumbs = [
    {
        title: 'Transformación 360',
        href: '/admin/transformation-360',
    },
    {
        title: 'Solicitudes de Implementación',
        href: '/admin/transformation-360/implementation-requests',
    },
];

const hasFilters = computed(
    () =>
        Boolean(search.value)
        || Boolean(status.value)
        || Boolean(capability.value),
);

function applyFilters(): void {
    router.get(
        '/admin/transformation-360/implementation-requests',
        {
            search: search.value || undefined,
            status: status.value || undefined,
            capability: capability.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function clearFilters(): void {
    search.value = '';
    status.value = '';
    capability.value = '';

    router.get(
        '/admin/transformation-360/implementation-requests',
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}

function statusClass(value: string): string {
    const classes: Record<string, string> = {
        requested:
            'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',

        under_lauda_review:
            'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300',

        definition_preparation:
            'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950/30 dark:text-violet-300',

        awaiting_tenant_review:
            'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-900 dark:bg-cyan-950/30 dark:text-cyan-300',

        changes_requested:
            'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950/30 dark:text-orange-300',

        definition_agreed:
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',

        ready_for_commercial:
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',

        cancelled:
            'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400',
    };

    return classes[value]
        ?? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300';
}
</script>

<template>
    <Head title="Solicitudes de Implementación" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8"
        >
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="p-6 sm:p-8">
                    <div
                        class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="max-w-3xl">
                            <div
                                class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-950 text-white dark:bg-white dark:text-slate-950"
                            >
                                <BriefcaseBusiness class="h-6 w-6" />
                            </div>

                            <p
                                class="text-[10px] font-black tracking-[0.18em] text-slate-400 uppercase"
                            >
                                LAUDA 360
                            </p>

                            <h1
                                class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                            >
                                Solicitudes de Implementación
                            </h1>

                            <p
                                class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Bandeja operativa de solicitudes expresamente
                                iniciadas por administradores de empresas.
                                Esta vista no activa servicios ni inicia
                                ejecución.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/40"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Total
                            </p>

                            <p
                                class="mt-1 text-3xl font-black text-slate-950 dark:text-white"
                            >
                                {{ summary.total }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <Clock3 class="h-5 w-5 text-amber-600" />
                    <p class="mt-4 text-2xl font-black">
                        {{ summary.requested }}
                    </p>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        Solicitudes recibidas
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <UserRoundCheck class="h-5 w-5 text-blue-600" />
                    <p class="mt-4 text-2xl font-black">
                        {{ summary.under_review }}
                    </p>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        En revisión LAUDA
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <FileCheck2 class="h-5 w-5 text-violet-600" />
                    <p class="mt-4 text-2xl font-black">
                        {{ summary.definition }}
                    </p>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        Definición en preparación
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <Building2 class="h-5 w-5 text-cyan-600" />
                    <p class="mt-4 text-2xl font-black">
                        {{ summary.awaiting_tenant }}
                    </p>
                    <p class="mt-1 text-xs font-bold text-slate-500">
                        Esperando empresa
                    </p>
                </article>
            </section>

            <section
                class="rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="border-b border-slate-100 p-5 sm:p-6 dark:border-slate-800">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-end"
                    >
                        <label class="flex-1">
                            <span
                                class="mb-2 block text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Buscar
                            </span>

                            <div class="relative">
                                <Search
                                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />

                                <input
                                    v-model="search"
                                    type="search"
                                    placeholder="Empresa, capability o solicitante"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-10 text-sm outline-none transition focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                                    @keyup.enter="applyFilters"
                                />
                            </div>
                        </label>

                        <label class="lg:w-56">
                            <span
                                class="mb-2 block text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Estado
                            </span>

                            <select
                                v-model="status"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                            >
                                <option value="">
                                    Todos
                                </option>

                                <option
                                    v-for="option in status_options"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>

                        <label class="lg:w-64">
                            <span
                                class="mb-2 block text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Capacidad
                            </span>

                            <select
                                v-model="capability"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                            >
                                <option value="">
                                    Todas
                                </option>

                                <option
                                    v-for="option in capability_options"
                                    :key="option.key"
                                    :value="option.key"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-black text-white dark:bg-white dark:text-slate-950"
                            @click="applyFilters"
                        >
                            <Filter class="h-4 w-4" />
                            Filtrar
                        </button>

                        <button
                            v-if="hasFilters"
                            type="button"
                            class="h-11 rounded-xl border border-slate-200 px-4 text-sm font-bold dark:border-slate-800"
                            @click="clearFilters"
                        >
                            Limpiar
                        </button>
                    </div>
                </div>

                <div
                    v-if="requests.length"
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <Link
                        v-for="item in requests"
                        :key="item.id"
                        :href="item.detail_url"
                        class="group block p-5 transition hover:bg-slate-50/80 sm:p-6 dark:hover:bg-slate-900/30"
                    >
                        <div
                            class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full border px-2.5 py-1 text-[10px] font-black tracking-wide uppercase"
                                        :class="statusClass(item.status)"
                                    >
                                        {{ item.status_label }}
                                    </span>

                                    <span
                                        class="text-[10px] font-bold text-slate-400"
                                    >
                                        Solicitud #{{ item.id }}
                                        · intento {{ item.attempt }}
                                    </span>
                                </div>

                                <h2
                                    class="mt-3 truncate text-base font-black text-slate-950 dark:text-white"
                                >
                                    {{ item.company.name }}
                                </h2>

                                <p
                                    class="mt-1 text-sm font-semibold text-slate-600 dark:text-slate-300"
                                >
                                    {{ item.capability_label }}
                                </p>

                                <p
                                    v-if="item.requested_at"
                                    class="mt-2 text-xs text-slate-400"
                                >
                                    Solicitada {{ item.requested_at }}
                                </p>
                            </div>

                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center xl:justify-end"
                            >
                                <div
                                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs dark:border-slate-800 dark:bg-slate-900/40"
                                >
                                    <p class="font-black text-slate-500">
                                        Responsable LAUDA
                                    </p>

                                    <p
                                        class="mt-1 font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            item.assigned_to?.name
                                                ?? 'Sin asignar'
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition group-hover:border-slate-400 group-hover:text-slate-950 dark:border-slate-800 dark:group-hover:text-white"
                                >
                                    <ArrowRight class="h-4 w-4" />
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="px-6 py-16 text-center"
                >
                    <BriefcaseBusiness
                        class="mx-auto h-8 w-8 text-slate-300"
                    />

                    <h2
                        class="mt-4 font-black text-slate-900 dark:text-white"
                    >
                        No hay solicitudes para mostrar
                    </h2>

                    <p
                        class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500"
                    >
                        Las solicitudes aparecerán aquí cuando una empresa
                        decida avanzar expresamente con una capacidad
                        profesional de implementación.
                    </p>
                </div>
            </section>

            <div
                class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:bg-slate-900/30"
            >
                F4B es una bandeja de supervisión. Consultar una solicitud
                no crea Definition, no activa servicios y no inicia
                ejecución ni procesos comerciales.
            </div>
        </div>
    </AppLayout>
</template>
