<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import {
    ArrowUpRight,
    Boxes,
    Check,
    ChevronRight,
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
    mode: 'platform.admin' | 'subscriber.admin' | 'subscriber.user';
    pivot_role: string | null;
    tenant_admin: boolean;
    can_browse_store: boolean;
    can_view_solution_insights: boolean;
    can_manage_billing: boolean;
    can_launch_apps: boolean;
    can_manage_company: boolean;
};


type Transformation360Capability = {
    id: number;
    key: string;
    label: string;
    summary: string | null;
    execution: {
        status: string;
        progress_percentage: number;
    };
    go_live: {
        status: string;
        ready_at: string | null;
        scheduled_at: string | null;
        went_live_at: string | null;
    } | null;
    service_activation: {
        status: string;
        service_id: number;
        subscription_item_id: number;
        activated_at: string | null;
        price_snapshot: unknown;
    } | null;
};

type Transformation360Phase = {
    id: number;
    sequence: number;
    name: string;
    objective: string | null;
    execution: {
        status: string;
        progress_percentage: number;
    };
    commercial: {
        estimate_amount: number | null;
        currency: string | null;
        estimated_duration_value: number | null;
        estimated_duration_unit: string | null;
        milestone_count: number;
        milestone_total: number;
        invoiced_total: number;
        paid_total: number;
        billing_status: string;
        next_due_at: string | null;
        milestones: Array<{
            id: number;
            sequence: number;
            name: string;
            billing_amount: number;
            currency: string | null;
            billing_status: string | null;
            due_at: string | null;
            invoice_reference: string | null;
            invoice_issued_at: string | null;
            payment_reference: string | null;
            paid_at: string | null;
        }>;
    };
    capabilities: Transformation360Capability[];
};

type Transformation360Plan = {
    id: number;
    status: string;
    version: number;
    selected_modality: string | null;
    selected_modality_label: string | null;
    presented_at: string | null;
    accepted_at: string | null;
    phases: Transformation360Phase[];
};

type Transformation360ControlPanel = {
    plans: Transformation360Plan[];
    summary: {
        plan_count: number;
        phase_count: number;
        capability_count: number;
        estimated_total: number;
        milestone_total: number;
        paid_total: number;
        currency: string | null;
    };
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
        subscriber_id: number | null;
    };
    groups: ActionGroup[];
    tenant_access: TenantAccess;
    transformation360: Transformation360ControlPanel;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mi ecosistema', href: '/app' },
];

const query = ref('');

const isTenantAdmin = computed(() => props.tenant_access.mode === 'subscriber.admin');

const hasTransformation360 = computed(
    () =>
        isTenantAdmin.value
        && (props.transformation360?.plans?.length ?? 0) > 0,
);

const money = (amount: number | null | undefined, currency?: string | null) => {
    if (amount === null || amount === undefined) return '—';

    const value = Number(amount);
    const code = currency || 'DOP';

    try {
        return new Intl.NumberFormat('es-DO', {
            style: 'currency',
            currency: code,
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${code} ${value.toLocaleString('es-DO')}`;
    }
};

const planStatusLabel = (status: string) =>
    ({
        presented: 'Presentado',
        accepted: 'Aceptado',
        active: 'En ejecución',
        completed: 'Completado',
    })[status] || status;

const executionStatusLabel = (status: string) =>
    ({
        pending: 'Pendiente',
        in_progress: 'En progreso',
        blocked: 'Bloqueado',
        completed: 'Completado',
        cancelled: 'Cancelado',
    })[status] || status;

const billingStatusLabel = (status: string) =>
    ({
        not_scheduled: 'Sin hitos de cobro',
        scheduled: 'Programado',
        ready_to_invoice: 'Listo para facturar',
        invoiced: 'Facturado',
        paid: 'Pagado',
    })[status] || status;

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
              (app) =>
                  app.integration !== 'managed'
                  && !app.entitled
                  && app.state === 'available'
                  && matches(app),
          )
        : [],
);

const pageTitle = computed(() => {
    if (isTenantAdmin.value) return 'Control Panel';
    return 'Mis Apps';
});

const pageDescription = computed(() => {
    if (isTenantAdmin.value) {
        return 'Administra tu ecosistema, abre las aplicaciones activas y descubre nuevas soluciones para tu empresa.';
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
                                    v-else
                                    class="rounded-full bg-slate-100 px-3 py-1.5 font-medium dark:bg-slate-900"
                                >
                                    Usuario
                                </span>
                            </div>
                        </div>

                                                <div class="space-y-3">
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
                                    v-if="hasTransformation360"
                                    class="min-w-28 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60"
                                >
                                    <p class="text-2xl font-black text-slate-950 dark:text-white">
                                        {{ transformation360.summary.phase_count }}
                                    </p>
                                    <p class="text-xs text-slate-500">Fases T360</p>
                                </div>
                            </div>

                            <div
                                v-if="isTenantAdmin"
                                class="grid grid-cols-2 gap-2"
                            >
                                <a
                                    href="/subscriber/company"
                                    class="group flex items-center gap-2 rounded-xl border border-slate-100 bg-white px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-900"
                                >
                                    <ShieldCheck class="h-4 w-4 text-slate-500" />
                                    <span>Empresa</span>
                                    <ChevronRight class="ml-auto h-3.5 w-3.5 text-slate-400 transition group-hover:translate-x-0.5" />
                                </a>

                                <a
                                    href="/subscriber/subscription"
                                    class="group flex items-center gap-2 rounded-xl border border-slate-100 bg-white px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-900"
                                >
                                    <CreditCard class="h-4 w-4 text-slate-500" />
                                    <span>Facturación</span>
                                    <ChevronRight class="ml-auto h-3.5 w-3.5 text-slate-400 transition group-hover:translate-x-0.5" />
                                </a>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-950 dark:text-white">
                            Aplicaciones
                        </h2>
                        <p class="text-sm text-slate-500">
                            Abre tus soluciones activas o descubre nuevas aplicaciones.
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

                <div
                    class="grid items-start gap-5"
                    :class="isTenantAdmin ? 'lg:grid-cols-2' : 'grid-cols-1'"
                >
                    <section
                        id="my-apps-panel"
                        class="rounded-[1.75rem] border border-slate-200/70 bg-slate-50/50 p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900/30"
                    >
                        <div class="mb-4 flex items-end justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold tracking-wide text-slate-500 uppercase">
                                    Tu ecosistema
                                </p>
                                <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">
                                    Mis Apps
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Aplicaciones activas para tu empresa.
                                </p>
                            </div>
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-slate-700 shadow-sm dark:bg-slate-950 dark:text-slate-200">
                                {{ installedApps.length }}
                            </span>
                        </div>

                        <div v-if="installedApps.length" class="grid gap-3">
                            <article
                                v-for="app in installedApps"
                                :key="`installed-${app.key}`"
                                class="group rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-950"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-xs font-black text-white shadow-sm dark:bg-white dark:text-slate-950">
                                        {{ appInitial(app) }}
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <h3 class="truncate font-bold text-slate-950 dark:text-white">
                                                    {{ app.title }}
                                                </h3>
                                                <p class="mt-0.5 text-xs text-emerald-600">
                                                    {{ stateLabel(app) }}
                                                </p>
                                            </div>

                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40">
                                                <Check class="h-3.5 w-3.5" />
                                            </span>
                                        </div>

                                        <p class="mt-2 text-sm leading-5 text-slate-500 dark:text-slate-400">
                                            {{ app.description }}
                                        </p>

                                        <a
                                            v-if="app.launch_url"
                                            :href="app.launch_url"
                                            class="mt-3 inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-slate-950 px-3.5 text-xs font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                        >
                                            Abrir
                                            <ArrowUpRight class="h-3.5 w-3.5" />
                                        </a>

                                        <div
                                            v-else
                                            class="mt-3 inline-flex h-9 items-center justify-center rounded-xl bg-slate-100 px-3.5 text-xs font-semibold text-slate-500 dark:bg-slate-900"
                                        >
                                            Gestionada desde LAUDAAPI
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-slate-200 bg-white px-5 py-8 text-center dark:border-slate-800 dark:bg-slate-950"
                        >
                            <Boxes class="mx-auto h-8 w-8 text-slate-300" />
                            <h3 class="mt-2 font-bold text-slate-900 dark:text-white">
                                No hay aplicaciones activas
                            </h3>
                            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                                <template v-if="isTenantAdmin">
                                    Explora el App Store para contratar la primera solución de tu empresa.
                                </template>
                                <template v-else>
                                    Cuando una aplicación esté activa para tu empresa aparecerá aquí.
                                </template>
                            </p>
                        </div>
                    </section>

                    <section
                        v-if="isTenantAdmin"
                        id="app-store"
                        class="scroll-mt-6 rounded-[1.75rem] border border-slate-200/70 bg-slate-50/50 p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900/30"
                    >
                        <div class="mb-4 flex items-end justify-between gap-3">
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
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-slate-700 shadow-sm dark:bg-slate-950 dark:text-slate-200">
                                {{ availableApps.length }}
                            </span>
                        </div>

                        <div v-if="availableApps.length" class="grid gap-3">
                            <article
                                v-for="app in availableApps"
                                :key="`available-${app.key}`"
                                class="group rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-950"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-black text-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                        {{ appInitial(app) }}
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <h3 class="truncate font-bold text-slate-950 dark:text-white">
                                                    {{ app.title }}
                                                </h3>
                                                <p class="mt-0.5 text-xs text-slate-500">
                                                    Disponible
                                                </p>
                                            </div>
                                            <ChevronRight class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5" />
                                        </div>

                                        <p class="mt-2 text-sm leading-5 text-slate-500 dark:text-slate-400">
                                            {{ app.description }}
                                        </p>

                                        <a
                                            :href="
                                                app.service_key === 'social'
                                                || app.service_key === 'crm'
                                                || app.service_key === 'pos'
                                                || app.service_key === 'ecf'
                                                || app.service_key === 'cumplimiento'
                                                || app.service_key === 'bys'
                                                    ? `/subscriber/apps/${app.service_key}`
                                                    : '/subscriber/services/my'
                                            "
                                            class="mt-3 inline-flex h-9 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3.5 text-xs font-bold text-slate-800 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-100 dark:hover:bg-slate-900"
                                        >
                                            Ver planes
                                            <ArrowUpRight class="h-3.5 w-3.5" />
                                        </a>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-slate-100 bg-white p-5 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950"
                        >
                            No hay nuevas aplicaciones disponibles para mostrar.
                        </div>
                    </section>
                </div>

                <section
                    v-if="hasTransformation360"
                    class="space-y-4"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold tracking-wide text-red-600 uppercase">
                                Transformación 360
                            </p>
                            <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">
                                Servicios, ejecución y estado comercial
                            </h2>
                            <p class="mt-1 max-w-3xl text-sm text-slate-500">
                                Solo se muestran fases y capacidades incorporadas a un Plan de Implementación real de tu empresa.
                            </p>
                        </div>

                        <div
                            v-if="transformation360.summary.estimated_total > 0"
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right dark:border-slate-800 dark:bg-slate-950"
                        >
                            <p class="text-xs text-slate-500">Estimado T360</p>
                            <p class="font-black text-slate-950 dark:text-white">
                                {{
                                    money(
                                        transformation360.summary.estimated_total,
                                        transformation360.summary.currency,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <article
                        v-for="plan in transformation360.plans"
                        :key="`t360-plan-${plan.id}`"
                        class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-black text-slate-950 dark:text-white">
                                        Plan de Implementación V{{ plan.version }}
                                    </h3>
                                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                        {{ planStatusLabel(plan.status) }}
                                    </span>
                                </div>
                                <p
                                    v-if="plan.selected_modality_label"
                                    class="mt-1 text-sm text-slate-500"
                                >
                                    {{ plan.selected_modality_label }}
                                </p>
                            </div>
                        </div>

                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div
                                v-for="phase in plan.phases"
                                :key="`t360-phase-${phase.id}`"
                                class="p-5"
                            >
                                <div class="grid gap-5 lg:grid-cols-[1fr_auto]">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-black text-red-600">
                                                FASE {{ phase.sequence }}
                                            </span>
                                            <span class="text-xs text-slate-400">
                                                ·
                                            </span>
                                            <span class="text-xs font-semibold text-slate-500">
                                                {{
                                                    executionStatusLabel(
                                                        phase.execution.status,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <h4 class="mt-1 font-black text-slate-950 dark:text-white">
                                            {{ phase.name }}
                                        </h4>

                                        <p
                                            v-if="phase.objective"
                                            class="mt-1 max-w-3xl text-sm leading-6 text-slate-500"
                                        >
                                            {{ phase.objective }}
                                        </p>

                                        <div
                                            v-if="phase.capabilities.length"
                                            class="mt-4 flex flex-wrap gap-2"
                                        >
                                            <span
                                                v-for="capability in phase.capabilities"
                                                :key="`t360-capability-${capability.id}`"
                                                class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                                            >
                                                {{ capability.label }}
                                                <span
                                                    v-if="capability.execution.progress_percentage > 0"
                                                    class="ml-1 text-slate-400"
                                                >
                                                    {{ capability.execution.progress_percentage }}%
                                                </span>
                                            </span>
                                        </div>

                                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-900">
                                            <div
                                                class="h-full rounded-full bg-slate-900 transition-all dark:bg-white"
                                                :style="{
                                                    width: `${Math.min(
                                                        100,
                                                        Math.max(
                                                            0,
                                                            phase.execution.progress_percentage,
                                                        ),
                                                    )}%`,
                                                }"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid min-w-56 gap-2 sm:grid-cols-2 lg:grid-cols-1">
                                        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                            <p class="text-xs text-slate-500">
                                                Estimado de la fase
                                            </p>
                                            <p class="mt-1 font-black text-slate-950 dark:text-white">
                                                {{
                                                    phase.commercial.estimate_amount !== null
                                                        ? money(
                                                            phase.commercial.estimate_amount,
                                                            phase.commercial.currency,
                                                        )
                                                        : 'Pendiente de cotización'
                                                }}
                                            </p>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                            <p class="text-xs text-slate-500">
                                                Estado comercial
                                            </p>
                                            <p class="mt-1 font-black text-slate-950 dark:text-white">
                                                {{
                                                    billingStatusLabel(
                                                        phase.commercial.billing_status,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="phase.commercial.milestone_total > 0"
                                                class="mt-1 text-xs text-slate-500"
                                            >
                                                Pagado
                                                {{
                                                    money(
                                                        phase.commercial.paid_total,
                                                        phase.commercial.currency,
                                                    )
                                                }}
                                                de
                                                {{
                                                    money(
                                                        phase.commercial.milestone_total,
                                                        phase.commercial.currency,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="phase.commercial.milestones.length"
                                    class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3"
                                >
                                    <div
                                        v-for="milestone in phase.commercial.milestones"
                                        :key="`t360-milestone-${milestone.id}`"
                                        class="rounded-xl border border-slate-100 p-3 text-sm dark:border-slate-800"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="font-bold text-slate-900 dark:text-white">
                                                {{ milestone.name }}
                                            </p>
                                            <span class="text-xs font-semibold text-slate-500">
                                                {{
                                                    billingStatusLabel(
                                                        milestone.paid_at
                                                            ? 'paid'
                                                            : milestone.invoice_reference
                                                                ? 'invoiced'
                                                                : milestone.billing_status || 'scheduled',
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{
                                                money(
                                                    milestone.billing_amount,
                                                    milestone.currency,
                                                )
                                            }}
                                            <template v-if="milestone.invoice_reference">
                                                · Factura {{ milestone.invoice_reference }}
                                            </template>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
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

                </template>

            </div>
        </div>
    </AppLayout>
</template>
