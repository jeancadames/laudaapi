<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import {
    ArrowUpRight,
    Boxes,
    Check,
    ChevronRight,
    Clock3,
    CreditCard,
    LayoutGrid,
    Search,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type SolutionState =
    | 'active'
    | 'active_managed'
    | 'available'
    | 'integration_pending';

type Solution = {
    key: string;
    title: string;
    description: string | null;
    service_key?: string | null;
    service_id?: number | null;
    first_wave: boolean;
    integration?: string;
    integration_ready?: boolean;
    entitled?: boolean;
    state: SolutionState;
    launch_url: string | null;
    target_url: string | null;
};

type ActionGroup = {
    key: string;
    title: string;
    description: string | null;
    solutions: Solution[];
};

type TenantAccess = {
    mode: 'platform.admin' | 'subscriber.admin' | 'subscriber.user' | 'subscriber.billing';
    pivot_role: string | null;
    tenant_admin: boolean;
    can_browse_store: boolean;
    can_view_solution_insights: boolean;
    can_manage_billing: boolean;
    can_launch_apps: boolean;
    can_manage_company: boolean;
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
        subscriber_id: number | null;
    };
    groups: ActionGroup[];
    tenant_access: TenantAccess;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mi ecosistema', href: '/app' },
];

const query = ref('');

const isTenantAdmin = computed(() => props.tenant_access.mode === 'subscriber.admin');
const isBilling = computed(() => props.tenant_access.mode === 'subscriber.billing');

const allApps = computed<Solution[]>(() =>
    props.groups.flatMap((group) => group.solutions ?? []),
);

const uniqueApps = computed<Solution[]>(() => {
    const seen = new Set<string>();

    return allApps.value.filter((app) => {
        const key = app.service_key || app.key;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
});

const matches = (app: Solution) => {
    const term = query.value.trim().toLowerCase();
    if (!term) return true;

    return [app.title, app.description, app.service_key]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(term));
};

const installedApps = computed(() =>
    uniqueApps.value.filter(
        (app) =>
            Boolean(app.entitled) &&
            (Boolean(app.launch_url) || app.state === 'active_managed') &&
            matches(app),
    ),
);

const availableApps = computed(() =>
    isTenantAdmin.value
        ? uniqueApps.value.filter(
              (app) => !app.entitled && app.state === 'available' && matches(app),
          )
        : [],
);

const pendingApps = computed(() =>
    isTenantAdmin.value
        ? uniqueApps.value.filter(
              (app) => app.state === 'integration_pending' && matches(app),
          )
        : [],
);

const pageTitle = computed(() => {
    if (isTenantAdmin.value) return 'Control Panel';
    if (isBilling.value) return 'Mis Apps y facturación';
    return 'Mis Apps';
});

const pageDescription = computed(() => {
    if (isTenantAdmin.value) {
        return 'Administra tu ecosistema, abre las aplicaciones activas y descubre nuevas soluciones para tu empresa.';
    }

    if (isBilling.value) {
        return 'Accede a tus aplicaciones activas y a la información comercial de la cuenta.';
    }

    return 'Tus aplicaciones disponibles en un solo lugar. Los permisos internos se administran dentro de cada solución.';
});

const appInitial = (app: Solution) =>
    app.title
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

const stateLabel = (app: Solution) => {
    if (app.state === 'active_managed') return 'Gestionada';
    if (app.entitled) return 'Instalada';
    if (app.state === 'integration_pending') return 'Próximamente';
    return 'Disponible';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-[#FAFAF8] dark:bg-background">
            <div class="mx-auto w-full max-w-7xl space-y-8 p-4 sm:p-6 lg:p-8">
                <header class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 dark:border-red-950 dark:bg-red-950/40 dark:text-red-300">
                                <Sparkles class="h-3.5 w-3.5" />
                                LAUDAAPI · App Hub
                            </div>

                            <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                                {{ pageTitle }}
                            </h1>

                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 sm:text-base dark:text-slate-400">
                                {{ pageDescription }}
                            </p>

                            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span class="rounded-full bg-slate-100 px-3 py-1.5 font-medium dark:bg-slate-900">
                                    {{ props.company.name }}
                                </span>
                                <span
                                    v-if="isTenantAdmin"
                                    class="rounded-full bg-emerald-50 px-3 py-1.5 font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                                >
                                    Administrador del tenant
                                </span>
                                <span
                                    v-else-if="isBilling"
                                    class="rounded-full bg-blue-50 px-3 py-1.5 font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
                                >
                                    Facturación
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-slate-100 px-3 py-1.5 font-medium dark:bg-slate-900"
                                >
                                    Usuario
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="min-w-28 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                                <p class="text-2xl font-black text-slate-950 dark:text-white">
                                    {{ installedApps.length }}
                                </p>
                                <p class="text-xs text-slate-500">Mis apps</p>
                            </div>
                            <div
                                v-if="isTenantAdmin"
                                class="min-w-28 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <p class="text-2xl font-black text-slate-950 dark:text-white">
                                    {{ availableApps.length }}
                                </p>
                                <p class="text-xs text-slate-500">Disponibles</p>
                            </div>
                            <div
                                v-if="isTenantAdmin"
                                class="min-w-28 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <p class="text-2xl font-black text-slate-950 dark:text-white">
                                    {{ pendingApps.length }}
                                </p>
                                <p class="text-xs text-slate-500">Próximamente</p>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-950 dark:text-white">
                            Mis Apps
                        </h2>
                        <p class="text-sm text-slate-500">
                            Aplicaciones activas para tu empresa.
                        </p>
                    </div>

                    <label class="relative block w-full sm:w-72">
                        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="query"
                            type="search"
                            placeholder="Buscar aplicaciones"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-9 text-sm outline-none transition focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                        />
                    </label>
                </div>

                <section v-if="installedApps.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="app in installedApps"
                        :key="`installed-${app.key}`"
                        class="group flex min-h-56 flex-col rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-950 text-sm font-black text-white shadow-sm dark:bg-white dark:text-slate-950">
                                    {{ appInitial(app) }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-950 dark:text-white">
                                        {{ app.title }}
                                    </h3>
                                    <p class="mt-0.5 text-xs text-emerald-600">
                                        {{ stateLabel(app) }}
                                    </p>
                                </div>
                            </div>

                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40">
                                <Check class="h-4 w-4" />
                            </span>
                        </div>

                        <p class="mt-4 flex-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                            {{ app.description }}
                        </p>

                        <a
                            v-if="app.launch_url"
                            :href="app.launch_url"
                            class="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        >
                            Abrir
                            <ArrowUpRight class="h-4 w-4" />
                        </a>

                        <div
                            v-else
                            class="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 text-sm font-semibold text-slate-500 dark:bg-slate-900"
                        >
                            Gestionada desde LAUDAAPI
                        </div>
                    </article>
                </section>

                <section
                    v-else
                    class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-800 dark:bg-slate-950"
                >
                    <Boxes class="mx-auto h-9 w-9 text-slate-300" />
                    <h3 class="mt-3 font-bold text-slate-900 dark:text-white">
                        No hay aplicaciones activas
                    </h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                        <template v-if="isTenantAdmin">
                            Explora el App Store para contratar la primera solución de tu empresa.
                        </template>
                        <template v-else>
                            Cuando una aplicación esté activa para tu empresa aparecerá aquí.
                        </template>
                    </p>
                </section>

                <template v-if="isTenantAdmin">
                    <section class="rounded-[2rem] border border-slate-200/70 bg-slate-900 p-6 text-white shadow-xl sm:p-8">
                        <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-center">
                            <div>
                                <div class="flex items-center gap-2 text-xs font-bold tracking-wide text-red-300 uppercase">
                                    <ShieldCheck class="h-4 w-4" />
                                    Resumen administrativo
                                </div>
                                <h2 class="mt-2 text-xl font-black">
                                    Toda tu operación, sin entrar app por app
                                </h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                                    La base del Control Panel ya está lista. En la siguiente etapa conectaremos primero Social y CRM para mostrar métricas, alertas y señales administrativas importantes desde este mismo Hub.
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-center">
                                <div class="rounded-2xl bg-white/5 px-4 py-3">
                                    <LayoutGrid class="mx-auto h-5 w-5 text-red-300" />
                                    <p class="mt-2 text-xs text-slate-300">Social</p>
                                </div>
                                <div class="rounded-2xl bg-white/5 px-4 py-3">
                                    <LayoutGrid class="mx-auto h-5 w-5 text-red-300" />
                                    <p class="mt-2 text-xs text-slate-300">CRM</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="app-store" class="scroll-mt-6 space-y-4">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold tracking-wide text-red-600 uppercase">
                                    App Store
                                </p>
                                <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">
                                    Apps disponibles
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Agrega soluciones al ecosistema de tu empresa.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="availableApps.length"
                            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                        >
                            <article
                                v-for="app in availableApps"
                                :key="`available-${app.key}`"
                                class="group flex min-h-56 flex-col rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-950"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-sm font-black text-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                            {{ appInitial(app) }}
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-slate-950 dark:text-white">
                                                {{ app.title }}
                                            </h3>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                Disponible
                                            </p>
                                        </div>
                                    </div>
                                    <ChevronRight class="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5" />
                                </div>

                                <p class="mt-4 flex-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                    {{ app.description }}
                                </p>

                                <a
                                    href="/subscriber/services/my"
                                    class="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-800 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-100 dark:hover:bg-slate-900"
                                >
                                    Ver planes
                                    <ArrowUpRight class="h-4 w-4" />
                                </a>
                            </article>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-slate-100 bg-white p-6 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950"
                        >
                            No hay nuevas aplicaciones disponibles para mostrar.
                        </div>
                    </section>

                    <section v-if="pendingApps.length" class="space-y-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950 dark:text-white">
                                Próximamente
                            </h2>
                            <p class="text-sm text-slate-500">
                                Soluciones cuyo acceso central todavía está en preparación.
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <article
                                v-for="app in pendingApps"
                                :key="`pending-${app.key}`"
                                class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                            >
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900">
                                    <Clock3 class="h-4 w-4 text-slate-500" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-bold text-slate-950 dark:text-white">
                                        {{ app.title }}
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Integración en preparación
                                    </p>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="grid gap-4 sm:grid-cols-2">
                        <a
                            href="/subscriber/company"
                            class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white p-5 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-900"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900">
                                    <ShieldCheck class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="font-bold text-slate-950 dark:text-white">Empresa</p>
                                    <p class="text-xs text-slate-500">Datos y configuración central</p>
                                </div>
                            </div>
                            <ChevronRight class="h-4 w-4 text-slate-400" />
                        </a>

                        <a
                            href="/subscriber/subscription"
                            class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white p-5 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-900"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900">
                                    <CreditCard class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="font-bold text-slate-950 dark:text-white">Facturación</p>
                                    <p class="text-xs text-slate-500">Suscripción, facturas y pagos</p>
                                </div>
                            </div>
                            <ChevronRight class="h-4 w-4 text-slate-400" />
                        </a>
                    </section>
                </template>

                <section
                    v-else-if="isBilling"
                    class="grid gap-4 sm:grid-cols-3"
                >
                    <a
                        href="/subscriber/subscription"
                        class="rounded-2xl border border-slate-100 bg-white p-5 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-900"
                    >
                        <CreditCard class="h-5 w-5 text-slate-500" />
                        <p class="mt-3 font-bold text-slate-950 dark:text-white">Suscripción</p>
                        <p class="mt-1 text-xs text-slate-500">Estado comercial de la cuenta</p>
                    </a>
                    <a
                        href="/subscriber/invoices"
                        class="rounded-2xl border border-slate-100 bg-white p-5 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-900"
                    >
                        <CreditCard class="h-5 w-5 text-slate-500" />
                        <p class="mt-3 font-bold text-slate-950 dark:text-white">Facturas</p>
                        <p class="mt-1 text-xs text-slate-500">Documentos pendientes y pagados</p>
                    </a>
                    <a
                        href="/subscriber/payments"
                        class="rounded-2xl border border-slate-100 bg-white p-5 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:hover:bg-slate-900"
                    >
                        <CreditCard class="h-5 w-5 text-slate-500" />
                        <p class="mt-3 font-bold text-slate-950 dark:text-white">Pagos</p>
                        <p class="mt-1 text-xs text-slate-500">Historial de pagos</p>
                    </a>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
