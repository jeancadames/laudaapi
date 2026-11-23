<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Boxes,
    ChevronRight,
    Clock3,
    LayoutGrid,
    Package,
    PackagePlus,
    PlugZap,
    ReceiptText,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppTile from '@/components/portal/AppTile.vue';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import PortalSection from '@/components/portal/PortalSection.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelfServiceHomeEntry } from '@/types/businessSelfService';

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
    // El servidor la envía solo a cuentas que pueden contratar por la empresa.
    business_self_service?: SelfServiceHomeEntry | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/app' }];

/**
 * Una sola acción en la tarjeta de autoservicio. Prioridad: retomar la compra en
 * curso (no se admite otra), ver el paquete ya activado o armar uno nuevo.
 */
const packageEntry = computed(() => {
    const entry = props.business_self_service;
    const pending = entry?.pending_purchase;
    if (pending)
        return {
            kind: 'pending' as const,
            title: 'Tienes una compra en curso',
            message: pending.message,
            label: pending.next_action?.label ?? 'Ver mi compra',
            url: pending.next_action?.url ?? entry.configurator_url,
        };
    if (entry?.last_purchase_url)
        return {
            kind: 'active' as const,
            title: 'Tu paquete',
            message: 'Revisa lo que contrataste y abre tus aplicaciones.',
            label: 'Ver mi paquete',
            url: entry.last_purchase_url,
        };
    return {
        kind: 'build' as const,
        title: 'Armar mi paquete',
        message:
            'Elige lo que necesita tu negocio y mira cuánto pagarás antes de contratar.',
        label: 'Armar mi paquete',
        url: entry?.configurator_url ?? '',
    };
});
/** Rutas del portal navegan con Inertia; cualquier otra URL, con el navegador. */
const isInternal = (url: string) =>
    url.startsWith('/') && !url.startsWith('//');

const isTenantAdmin = computed(
    () => props.tenant_access.mode === 'subscriber.admin',
);

const uniqueSolutions = computed(() => {
    const seen = new Set<string>();
    const result: Solution[] = [];

    for (const group of props.groups ?? []) {
        for (const app of group.solutions ?? []) {
            const key = String(app.service_key ?? app.service_id ?? app.key);

            if (seen.has(key)) continue;

            seen.add(key);
            result.push(app);
        }
    }

    return result;
});

const launchableApps = computed(() =>
    uniqueSolutions.value.filter(
        (app) => !!app.entitled && !!app.launch_url && app.state === 'active',
    ),
);

const managedServices = computed(() =>
    isTenantAdmin.value
        ? uniqueSolutions.value.filter(
              (app) => !!app.entitled && app.state === 'active_managed',
          )
        : [],
);

const page = usePage();

/** Atajos del administrador: lo que más se busca después de abrir una app. */
const shortcuts = computed(() =>
    [
        {
            href: '/app/control',
            title: 'Panel de control',
            text: 'Tus apps y las que puedes agregar',
            icon: LayoutGrid,
        },
        page.props.businessIdentityEnabled
            ? {
                  href: '/business/identity',
                  title: 'Conexiones Business',
                  text: 'Conecta tus apps con tu empresa',
                  icon: PlugZap,
              }
            : null,
        {
            href: '/subscriber/invoices',
            title: 'Facturas',
            text: 'Lo que has pagado y lo pendiente',
            icon: ReceiptText,
        },
        {
            href: '/subscriber/users',
            title: 'Usuarios',
            text: 'Quién entra y a qué',
            icon: Users,
        },
    ].filter((item) => item !== null),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company.name"
            title="Inicio"
            :description="
                isTenantAdmin
                    ? 'Abre tus aplicaciones y revisa tu paquete desde un solo lugar.'
                    : 'Abre las aplicaciones que tienes asignadas.'
            "
        >
            <template v-if="isTenantAdmin" #actions>
                <Link href="/app/control" :class="portalButton.secondary">
                    <LayoutGrid class="h-4 w-4" aria-hidden="true" />
                    Panel de control
                </Link>
            </template>

            <section
                v-if="props.business_self_service"
                aria-labelledby="package-builder-title"
                class="relative overflow-hidden rounded-2xl border bg-white p-5 sm:p-6 dark:bg-slate-950"
                :class="
                    packageEntry.kind === 'pending'
                        ? 'border-amber-200 dark:border-amber-900/60'
                        : packageEntry.kind === 'build'
                          ? 'border-red-200/80 dark:border-red-950'
                          : 'border-slate-200/80 dark:border-slate-800'
                "
            >
                <span
                    class="absolute inset-y-0 left-0 w-1"
                    :class="
                        packageEntry.kind === 'pending'
                            ? 'bg-amber-400'
                            : packageEntry.kind === 'build'
                              ? 'bg-red-600'
                              : 'bg-slate-900 dark:bg-white'
                    "
                    aria-hidden="true"
                />
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                            :class="
                                packageEntry.kind === 'pending'
                                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200'
                                    : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'
                            "
                        >
                            <component
                                :is="
                                    packageEntry.kind === 'pending'
                                        ? Clock3
                                        : packageEntry.kind === 'build'
                                          ? PackagePlus
                                          : Package
                                "
                                class="h-5 w-5"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0">
                            <h2
                                id="package-builder-title"
                                class="text-lg font-semibold text-slate-950 dark:text-white"
                            >
                                {{ packageEntry.title }}
                            </h2>
                            <p
                                class="mt-0.5 text-sm leading-6 text-slate-600 dark:text-slate-400"
                            >
                                {{ packageEntry.message }}
                            </p>
                        </div>
                    </div>

                    <component
                        :is="isInternal(packageEntry.url) ? Link : 'a'"
                        :href="packageEntry.url"
                        class="w-full shrink-0 sm:w-auto"
                        :class="
                            packageEntry.kind === 'build'
                                ? portalButton.primary
                                : portalButton.dark
                        "
                    >
                        {{ packageEntry.label }}
                    </component>
                </div>
            </section>

            <PortalSection
                heading-id="home-apps-title"
                :title="isTenantAdmin ? 'Tus aplicaciones' : 'Tus soluciones'"
                :count="launchableApps.length || null"
                :description="
                    isTenantAdmin
                        ? 'Las aplicaciones activas de tu empresa.'
                        : 'Solo aparecen las soluciones que tienes asignadas.'
                "
            >
                <div
                    v-if="launchableApps.length"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <AppTile
                        v-for="app in launchableApps"
                        :key="`home-${app.key}`"
                        :title="app.title"
                        :description="app.description"
                        status="Activa"
                        tone="active"
                        solid
                    >
                        <a
                            :href="app.launch_url!"
                            class="w-full"
                            :class="portalButton.dark"
                        >
                            Abrir {{ app.title }}
                            <ArrowUpRight class="h-4 w-4" aria-hidden="true" />
                        </a>
                    </AppTile>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center dark:border-slate-700 dark:bg-slate-950"
                >
                    <Boxes
                        class="mx-auto h-9 w-9 text-slate-300"
                        aria-hidden="true"
                    />
                    <h3
                        class="mt-3 font-semibold text-slate-950 dark:text-white"
                    >
                        {{
                            isTenantAdmin
                                ? 'Todavía no hay aplicaciones activas'
                                : 'No tienes soluciones asignadas'
                        }}
                    </h3>
                    <p
                        class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-600 dark:text-slate-400"
                    >
                        <template v-if="packageEntry.kind === 'active'">
                            Abre tus aplicaciones desde «Ver mi paquete».
                        </template>
                        <template v-else-if="packageEntry.kind === 'pending'">
                            Cuando se active tu compra, tus aplicaciones
                            aparecerán aquí.
                        </template>
                        <template v-else-if="props.business_self_service">
                            Arma tu paquete y, cuando esté activo, tus
                            aplicaciones aparecerán aquí.
                        </template>
                        <template v-else-if="isTenantAdmin">
                            Agrega una aplicación desde el Panel de control y
                            aparecerá aquí.
                        </template>
                        <template v-else>
                            Cuando el administrador te asigne una aplicación,
                            podrás abrirla desde aquí.
                        </template>
                    </p>

                    <Link
                        v-if="isTenantAdmin && !props.business_self_service"
                        href="/app/control"
                        class="mt-5"
                        :class="portalButton.secondary"
                    >
                        Ir al Panel de control
                    </Link>
                </div>
            </PortalSection>

            <PortalSection
                v-if="isTenantAdmin && managedServices.length"
                heading-id="home-managed-title"
                title="Servicios que gestionamos por ti"
                description="Nuestro equipo los opera; no necesitas abrirlos."
            >
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <AppTile
                        v-for="service in managedServices"
                        :key="`managed-${service.key}`"
                        :title="service.title"
                        :description="service.description"
                        status="Activo"
                        tone="active"
                    />
                </div>
            </PortalSection>

            <PortalSection
                v-if="isTenantAdmin"
                heading-id="home-shortcuts-title"
                title="Atajos"
            >
                <nav
                    aria-labelledby="home-shortcuts-title"
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <Link
                        v-for="item in shortcuts"
                        :key="item.href"
                        :href="item.href"
                        class="group flex min-w-0 items-center gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-400 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <component
                                :is="item.icon"
                                class="h-5 w-5"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="block font-semibold text-slate-950 dark:text-white"
                                >{{ item.title }}</span
                            >
                            <span
                                class="block text-sm text-slate-600 dark:text-slate-400"
                                >{{ item.text }}</span
                            >
                        </span>
                        <ChevronRight
                            class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
                </nav>
            </PortalSection>
        </PortalPage>
    </AppLayout>
</template>
