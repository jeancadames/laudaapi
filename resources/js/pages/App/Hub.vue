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
    kind: 'professional_service';
    includes: string[];
};

type Transformation360Initiative = {
    id: number | string | null;
    priority: string | null;
    title: string | null;
    objective: string | null;
    owner_role: string | null;
    actions: string[];
    dependencies: string[];
    success_metrics: string[];
};

type Transformation360Phase = {
    id: number;
    sequence: number;
    name: string;
    objective: string | null;
    horizon: string | null;
    initiative_ids: Array<number | string>;
    initiatives: Transformation360Initiative[];
    dependencies: string[];
    deliverables: string[];
    capabilities: Transformation360Capability[];
};

type Transformation360Plan = {
    id: number;
    status: string;
    version: number;
    presented_at: string | null;
    source_type: string;
    phases: Transformation360Phase[];
};

type Transformation360ControlPanel = {
    plans: Transformation360Plan[];
    summary: {
        plan_count: number;
        phase_count: number;
        capability_count: number;
        initiative_count: number;
        deliverable_count: number;
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
    { title: 'Inicio', href: '/app' },
    { title: 'Control Panel', href: '/app/control' },
];

const query = ref('');

const isTenantAdmin = computed(() => props.tenant_access.mode === 'subscriber.admin');

const hasTransformation360 = computed(
    () =>
        isTenantAdmin.value
        && (props.transformation360?.plans?.length ?? 0) > 0,
);

const planStatusLabel = (status: string) =>
    ({
        presented: 'Presentado',
        accepted: 'Presentado',
        active: 'Presentado',
        completed: 'Completado',
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
                                Plan consultivo y prioridades de transformación
                            </h2>
                            <p class="mt-1 max-w-3xl text-sm text-slate-500">
                                Consulta fases, horizonte, iniciativas, dependencias, entregables y apoyo profesional sugerido. La contratación comercial se gestiona fuera del Plan.
                            </p>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl border bg-white px-3 py-2 dark:bg-slate-950">
                                <p class="font-black">{{ transformation360.summary.phase_count }}</p>
                                <p class="text-[10px] text-slate-500">Fases</p>
                            </div>
                            <div class="rounded-xl border bg-white px-3 py-2 dark:bg-slate-950">
                                <p class="font-black">{{ transformation360.summary.initiative_count }}</p>
                                <p class="text-[10px] text-slate-500">Iniciativas</p>
                            </div>
                            <div class="rounded-xl border bg-white px-3 py-2 dark:bg-slate-950">
                                <p class="font-black">{{ transformation360.summary.deliverable_count }}</p>
                                <p class="text-[10px] text-slate-500">Entregables</p>
                            </div>
                        </div>
                    </div>

                    <article
                        v-for="plan in transformation360.plans"
                        :key="`t360-plan-${plan.id}`"
                        class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 dark:border-slate-800">
                            <div>
                                <h3 class="font-black text-slate-950 dark:text-white">
                                    Plan de Implementación V{{ plan.version }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ plan.source_type === 'published_roadmap' ? 'Fuente: Roadmap Detallado' : 'Fuente: Diagnóstico oficial' }}
                                </p>
                            </div>
                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                {{ planStatusLabel(plan.status) }}
                            </span>
                        </div>

                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div
                                v-for="phase in plan.phases"
                                :key="`t360-phase-${phase.id}`"
                                class="p-5"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-black text-red-600">FASE {{ phase.sequence }}</p>
                                        <h4 class="mt-1 font-black text-slate-950 dark:text-white">{{ phase.name }}</h4>
                                        <p v-if="phase.objective" class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">{{ phase.objective }}</p>
                                    </div>
                                    <span v-if="phase.horizon" class="rounded-full border px-3 py-1 text-xs font-semibold text-slate-500">{{ phase.horizon }}</span>
                                </div>

                                <div v-if="phase.initiatives.length" class="mt-4 grid gap-2 md:grid-cols-2">
                                    <div v-for="initiative in phase.initiatives" :key="String(initiative.id ?? initiative.title)" class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900">
                                        <p class="text-sm font-bold">{{ initiative.title || 'Iniciativa' }}</p>
                                        <p v-if="initiative.owner_role" class="mt-1 text-xs text-slate-500">Responsable: {{ initiative.owner_role }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <div class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                                        <p class="text-xs font-bold uppercase text-slate-500">Dependencias</p>
                                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ phase.dependencies.length ? phase.dependencies.join(' · ') : 'Sin dependencias adicionales' }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                                        <p class="text-xs font-bold uppercase text-slate-500">Entregables</p>
                                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ phase.deliverables.length ? phase.deliverables.join(' · ') : 'Definidos por las iniciativas' }}</p>
                                    </div>
                                </div>

                                <div v-if="phase.capabilities.length" class="mt-4 flex flex-wrap gap-2">
                                    <span
                                        v-for="capability in phase.capabilities"
                                        :key="`t360-capability-${capability.id}`"
                                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                                    >
                                        {{ capability.label }}
                                    </span>
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
