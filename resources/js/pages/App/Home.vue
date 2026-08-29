<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import {
    ArrowUpRight,
    Boxes,
    CheckCircle2,
    LayoutGrid,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';

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
    can_manage_users?: boolean;
    can_manage_billing: boolean;
    can_launch_apps: boolean;
    can_manage_company: boolean;
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
        subscriber_id: number;
    };
    groups: ActionGroup[];
    tenant_access: TenantAccess;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
];

const isTenantAdmin = computed(
    () => props.tenant_access.mode === 'subscriber.admin',
);

const uniqueSolutions = computed(() => {
    const seen = new Set<string>();
    const result: Solution[] = [];

    for (const group of props.groups ?? []) {
        for (const app of group.solutions ?? []) {
            const key = String(
                app.service_key
                ?? app.service_id
                ?? app.key,
            );

            if (seen.has(key)) continue;

            seen.add(key);
            result.push(app);
        }
    }

    return result;
});

const launchableApps = computed(() =>
    uniqueSolutions.value.filter(
        (app) =>
            !!app.entitled
            && !!app.launch_url
            && app.state === 'active',
    ),
);

const managedServices = computed(() =>
    isTenantAdmin.value
        ? uniqueSolutions.value.filter(
              (app) =>
                  !!app.entitled
                  && app.state === 'active_managed',
          )
        : [],
);

const operationalCount = computed(
    () => launchableApps.value.length + managedServices.value.length,
);

const appInitial = (app: Solution) =>
    String(app.title ?? app.key ?? '?')
        .trim()
        .slice(0, 2)
        .toUpperCase();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-slate-50/60 py-6 dark:bg-slate-950/40">
            <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <header class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 dark:border-red-950 dark:bg-red-950/40 dark:text-red-300">
                                <Sparkles class="h-3.5 w-3.5" />
                                LAUDAAPI · App Hub
                            </div>

                            <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                                Inicio
                            </h1>

                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 sm:text-base dark:text-slate-400">
                                Tu centro operativo para entrar a las soluciones que usas cada día.
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

                        <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="min-w-32 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                                    <p class="text-2xl font-black text-slate-950 dark:text-white">
                                        {{ launchableApps.length }}
                                    </p>
                                    <p class="text-xs text-slate-500">Soluciones</p>
                                </div>

                                <div
                                    v-if="isTenantAdmin"
                                    class="min-w-32 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60"
                                >
                                    <p class="text-2xl font-black text-slate-950 dark:text-white">
                                        {{ operationalCount }}
                                    </p>
                                    <p class="text-xs text-slate-500">Ecosistema activo</p>
                                </div>
                            </div>

                            <a
                                v-if="isTenantAdmin"
                                href="/app/control"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                Control Panel
                                <ArrowUpRight class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </header>

                <section class="space-y-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold tracking-wide text-red-600 uppercase">
                                Operación diaria
                            </p>
                            <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">
                                {{ isTenantAdmin ? 'Paneles administrativos' : 'Tus soluciones' }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                <template v-if="isTenantAdmin">
                                    Accede a cada solución activa desde un solo lugar. Los KPIs operativos se incorporarán mediante snapshots seguros de cada producto.
                                </template>
                                <template v-else>
                                    Solo aparecen las soluciones que tienes asignadas.
                                </template>
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="launchableApps.length"
                        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="app in launchableApps"
                            :key="`home-${app.key}`"
                            class="group flex min-h-52 flex-col rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-xs font-black text-white dark:bg-white dark:text-slate-950">
                                        {{ appInitial(app) }}
                                    </div>

                                    <div class="min-w-0">
                                        <h3 class="truncate font-black text-slate-950 dark:text-white">
                                            {{ app.title }}
                                        </h3>
                                        <div class="mt-1 flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                                            <CheckCircle2 class="h-3.5 w-3.5" />
                                            Activa
                                        </div>
                                    </div>
                                </div>

                                <span
                                    v-if="isTenantAdmin"
                                    class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    Admin
                                </span>
                            </div>

                            <p class="mt-4 flex-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                {{ app.description }}
                            </p>

                            <div
                                v-if="isTenantAdmin"
                                class="mt-4 rounded-xl bg-slate-50 px-3 py-2.5 text-xs text-slate-500 dark:bg-slate-900/70"
                            >
                                <span class="font-bold text-slate-700 dark:text-slate-200">
                                    Resumen administrativo:
                                </span>
                                acceso activo. Métricas de la solución pendientes de snapshot.
                            </div>

                            <a
                                :href="app.launch_url!"
                                class="mt-4 inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                Abrir {{ app.title }}
                                <ArrowUpRight class="h-4 w-4" />
                            </a>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-[1.75rem] border border-dashed border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-800 dark:bg-slate-950"
                    >
                        <Boxes class="mx-auto h-10 w-10 text-slate-300" />
                        <h3 class="mt-3 font-black text-slate-950 dark:text-white">
                            {{ isTenantAdmin ? 'Todavía no hay soluciones activas' : 'No tienes soluciones asignadas' }}
                        </h3>
                        <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">
                            <template v-if="isTenantAdmin">
                                Contrata o activa una solución desde el Control Panel y aparecerá aquí automáticamente.
                            </template>
                            <template v-else>
                                Cuando el administrador te asigne una solución, podrás abrirla desde este Inicio.
                            </template>
                        </p>

                        <a
                            v-if="isTenantAdmin"
                            href="/app/control"
                            class="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-800 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-100 dark:hover:bg-slate-900"
                        >
                            Ir al Control Panel
                            <ArrowUpRight class="h-4 w-4" />
                        </a>
                    </div>
                </section>

                <section
                    v-if="isTenantAdmin && managedServices.length"
                    class="space-y-4"
                >
                    <div>
                        <p class="text-xs font-bold tracking-wide text-slate-500 uppercase">
                            Servicios gestionados
                        </p>
                        <h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">
                            Operación acompañada por LAUDAAPI
                        </h2>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <article
                            v-for="service in managedServices"
                            :key="`managed-${service.key}`"
                            class="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-900">
                                <ShieldCheck class="h-5 w-5 text-slate-600 dark:text-slate-300" />
                            </div>

                            <div class="min-w-0">
                                <p class="font-bold text-slate-950 dark:text-white">
                                    {{ service.title }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Servicio activo gestionado desde LAUDAAPI.
                                </p>
                            </div>
                        </article>
                    </div>
                </section>

                <section
                    v-if="isTenantAdmin"
                    class="rounded-[1.75rem] border border-slate-200/70 bg-slate-900 p-5 text-white shadow-lg sm:p-6"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-xs font-bold tracking-wide text-red-300 uppercase">
                                <LayoutGrid class="h-4 w-4" />
                                Estado del ecosistema
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-300">
                                Este Home usa únicamente identidad, entitlements y estado central. No realiza consultas síncronas a las bases de datos de las soluciones.
                            </p>
                        </div>

                        <a
                            href="/app/control"
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-slate-950 transition hover:bg-slate-100"
                        >
                            Administrar ecosistema
                            <ArrowUpRight class="h-4 w-4" />
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
