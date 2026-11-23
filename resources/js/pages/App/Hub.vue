<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Boxes,
    Building2,
    CreditCard,
    PlugZap,
    Search,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppTile from '@/components/portal/AppTile.vue';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import PortalSection from '@/components/portal/PortalSection.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

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
    requires_lauda_review: boolean;
    commercial_readiness: string | null;
    activation_policy: string | null;
    recommendation_basis: string | null;
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
    business_identity_url?: string | null;
    transformation360: Transformation360ControlPanel;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Panel de control', href: '/app/control' },
];

const query = ref('');

const isTenantAdmin = computed(
    () => props.tenant_access.mode === 'subscriber.admin',
);

const hasTransformation360 = computed(
    () =>
        isTenantAdmin.value &&
        (props.transformation360?.plans?.length ?? 0) > 0,
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

const pageTitle = computed(() =>
    isTenantAdmin.value ? 'Panel de control' : 'Mis aplicaciones',
);

const pageDescription = computed(() =>
    isTenantAdmin.value
        ? 'Abre las aplicaciones de tu empresa y agrega las que necesites.'
        : 'Tus aplicaciones en un solo lugar. Los permisos de cada una se administran dentro de ella.',
);

const page = usePage();

/** Productos que se contratan desde «Armar mi paquete». */
const packageProducts = ['ecf', 'cumplimiento', 'pos', 'bys'];

const addsToPackage = (app: Solution) =>
    Boolean(page.props.canConfigureBusinessPackage) &&
    packageProducts.includes(String(app.service_key));

/** Mostrar el buscador solo cuando hay suficientes apps para necesitarlo. */
const showSearch = computed(() => uniqueApps.value.length > 4);

const stateLabel = (app: Solution) =>
    app.state === 'active_managed' ? 'La gestionamos por ti' : 'Activa';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company.name"
            :title="pageTitle"
            :description="pageDescription"
        >
            <template v-if="isTenantAdmin" #actions>
                <a href="/subscriber/company" :class="portalButton.secondary">
                    <Building2 class="h-4 w-4" aria-hidden="true" />
                    Empresa
                </a>
                <a
                    href="/subscriber/subscription"
                    :class="portalButton.secondary"
                >
                    <CreditCard class="h-4 w-4" aria-hidden="true" />
                    Facturación
                </a>
                <a
                    v-if="business_identity_url"
                    :href="business_identity_url"
                    :class="portalButton.secondary"
                >
                    <PlugZap class="h-4 w-4" aria-hidden="true" />
                    Conexiones Business
                </a>
            </template>

            <label v-if="showSearch" class="relative block w-full sm:max-w-sm">
                <span class="sr-only">Buscar aplicaciones</span>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                    aria-hidden="true"
                />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Buscar aplicaciones"
                    class="h-11 w-full rounded-xl border border-slate-200 bg-white pr-3 pl-10 text-sm text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-slate-400 focus:ring-2 focus:ring-slate-200 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                />
            </label>

            <div id="my-apps-panel" class="scroll-mt-6">
                <PortalSection
                    heading-id="my-apps-title"
                    title="Mis aplicaciones"
                    :count="installedApps.length"
                    description="Las aplicaciones activas de tu empresa."
                >
                    <div
                        v-if="installedApps.length"
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <AppTile
                            v-for="app in installedApps"
                            :key="`installed-${app.key}`"
                            :title="app.title"
                            :description="app.description"
                            :status="stateLabel(app)"
                            tone="active"
                            solid
                        >
                            <a
                                v-if="app.launch_url"
                                :href="app.launch_url"
                                class="w-full"
                                :class="portalButton.dark"
                            >
                                Abrir {{ app.title }}
                                <ArrowUpRight
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </a>
                            <p
                                v-else-if="app.state === 'active_managed'"
                                class="text-sm text-slate-600 dark:text-slate-400"
                            >
                                Gestionada por nuestro equipo; no necesitas
                                abrirla.
                            </p>
                        </AppTile>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center dark:border-slate-700 dark:bg-slate-950"
                    >
                        <Boxes
                            class="h-9 w-9 text-slate-300"
                            aria-hidden="true"
                        />
                        <h3
                            class="mt-3 font-semibold text-slate-950 dark:text-white"
                        >
                            {{
                                query.trim()
                                    ? 'Ninguna de tus aplicaciones coincide'
                                    : 'Aún no tienes aplicaciones activas'
                            }}
                        </h3>
                        <p
                            class="mt-1 max-w-md text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            <template v-if="query.trim()">
                                Prueba con otro nombre.
                            </template>
                            <template v-else-if="isTenantAdmin">
                                Elige una de las aplicaciones disponibles para
                                empezar.
                            </template>
                            <template v-else>
                                Cuando una aplicación esté activa para tu
                                empresa aparecerá aquí.
                            </template>
                        </p>
                        <a
                            v-if="
                                isTenantAdmin &&
                                availableApps.length &&
                                !query.trim()
                            "
                            href="#app-store"
                            class="mt-5"
                            :class="portalButton.secondary"
                        >
                            Ver aplicaciones disponibles
                        </a>
                    </div>
                </PortalSection>
            </div>

            <div v-if="isTenantAdmin" id="app-store" class="scroll-mt-6">
                <PortalSection
                    heading-id="app-store-title"
                    title="Apps disponibles"
                    :count="availableApps.length"
                    description="Agrega lo que necesite tu negocio. Verás el precio antes de contratar."
                >
                    <div
                        v-if="availableApps.length"
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <AppTile
                            v-for="app in availableApps"
                            :key="`available-${app.key}`"
                            :title="app.title"
                            :description="app.description"
                            status="Disponible"
                        >
                            <Link
                                v-if="addsToPackage(app)"
                                href="/app/business/self-service"
                                :class="portalButton.primary"
                            >
                                Agregar a mi paquete
                            </Link>
                            <a
                                :href="
                                    app.service_key === 'social' ||
                                    app.service_key === 'crm' ||
                                    app.service_key === 'pos' ||
                                    app.service_key === 'ecf' ||
                                    app.service_key === 'cumplimiento' ||
                                    app.service_key === 'bys'
                                        ? `/subscriber/apps/${app.service_key}`
                                        : '/subscriber/services/my'
                                "
                                :class="
                                    addsToPackage(app)
                                        ? portalButton.link
                                        : portalButton.secondary
                                "
                            >
                                {{
                                    addsToPackage(app)
                                        ? 'Ver detalles'
                                        : 'Ver planes'
                                }}
                            </a>
                        </AppTile>
                    </div>

                    <p
                        v-else
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400"
                    >
                        {{
                            query.trim()
                                ? 'Ninguna aplicación disponible coincide con tu búsqueda.'
                                : 'Ya tienes todas las aplicaciones disponibles.'
                        }}
                    </p>
                </PortalSection>
            </div>

            <PortalSection
                v-if="hasTransformation360"
                heading-id="t360-title"
                title="Plan consultivo y prioridades de transformación"
                description="Transformación 360 · Fases, iniciativas y entregables recomendados para tu empresa. La contratación comercial se gestiona fuera del Plan."
            >
                <template #aside>
                    <dl class="flex gap-2 text-center">
                        <div
                            class="rounded-xl border border-slate-200/80 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <dd class="font-bold tabular-nums">
                                {{ transformation360.summary.phase_count }}
                            </dd>
                            <dt class="text-xs text-slate-600">Fases</dt>
                        </div>
                        <div
                            class="rounded-xl border border-slate-200/80 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <dd class="font-bold tabular-nums">
                                {{ transformation360.summary.initiative_count }}
                            </dd>
                            <dt class="text-xs text-slate-600">Iniciativas</dt>
                        </div>
                        <div
                            class="rounded-xl border border-slate-200/80 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <dd class="font-bold tabular-nums">
                                {{
                                    transformation360.summary.deliverable_count
                                }}
                            </dd>
                            <dt class="text-xs text-slate-600">Entregables</dt>
                        </div>
                    </dl>
                </template>

                <article
                    v-for="plan in transformation360.plans"
                    :key="`t360-plan-${plan.id}`"
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 dark:border-slate-800"
                    >
                        <div class="min-w-0">
                            <h3
                                class="font-semibold text-slate-950 dark:text-white"
                            >
                                Plan de implementación · versión
                                {{ plan.version }}
                            </h3>
                            <p class="mt-0.5 text-sm text-slate-600">
                                {{
                                    plan.source_type === 'published_roadmap'
                                        ? 'Basado en tu hoja de ruta detallada'
                                        : 'Basado en tu diagnóstico'
                                }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300"
                        >
                            {{ planStatusLabel(plan.status) }}
                        </span>
                    </div>

                    <div
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <div
                            v-for="phase in plan.phases"
                            :key="`t360-phase-${phase.id}`"
                            class="p-5"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold text-red-700 dark:text-red-300"
                                    >
                                        Fase {{ phase.sequence }}
                                    </p>
                                    <h4
                                        class="mt-0.5 font-semibold text-slate-950 dark:text-white"
                                    >
                                        {{ phase.name }}
                                    </h4>
                                    <p
                                        v-if="phase.objective"
                                        class="mt-1 max-w-3xl text-sm leading-6 text-slate-600"
                                    >
                                        {{ phase.objective }}
                                    </p>
                                </div>
                                <span
                                    v-if="phase.horizon"
                                    class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-700"
                                    >{{ phase.horizon }}</span
                                >
                            </div>

                            <div
                                v-if="phase.initiatives.length"
                                class="mt-4 grid gap-2 md:grid-cols-2"
                            >
                                <div
                                    v-for="initiative in phase.initiatives"
                                    :key="
                                        String(
                                            initiative.id ?? initiative.title,
                                        )
                                    "
                                    class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900"
                                >
                                    <p class="text-sm font-semibold">
                                        {{ initiative.title || 'Iniciativa' }}
                                    </p>
                                    <p
                                        v-if="initiative.owner_role"
                                        class="mt-1 text-xs text-slate-600"
                                    >
                                        Responsable: {{ initiative.owner_role }}
                                    </p>
                                </div>
                            </div>

                            <dl class="mt-4 grid gap-3 md:grid-cols-2">
                                <div
                                    class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                                >
                                    <dt
                                        class="text-xs font-semibold text-slate-600"
                                    >
                                        Dependencias
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm text-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            phase.dependencies.length
                                                ? phase.dependencies.join(' · ')
                                                : 'Sin dependencias adicionales'
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
                                >
                                    <dt
                                        class="text-xs font-semibold text-slate-600"
                                    >
                                        Entregables
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm text-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            phase.deliverables.length
                                                ? phase.deliverables.join(' · ')
                                                : 'Definidos en cada iniciativa'
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="phase.capabilities.length"
                                class="mt-4 grid gap-3 md:grid-cols-2"
                            >
                                <div
                                    v-for="capability in phase.capabilities"
                                    :key="`t360-capability-${capability.id}`"
                                    class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/60"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ capability.label }}
                                        </p>
                                        <span
                                            class="rounded-full border border-slate-200 bg-white px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300"
                                        >
                                            Servicio profesional
                                        </span>
                                        <span
                                            v-if="
                                                capability.activation_policy ===
                                                'implementation_only'
                                            "
                                            class="rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:border-red-950 dark:bg-red-950/20 dark:text-red-300"
                                        >
                                            Se define y cotiza en Implementación
                                        </span>
                                    </div>

                                    <p
                                        v-if="capability.summary"
                                        class="mt-2 text-sm leading-6 text-slate-600"
                                    >
                                        {{ capability.summary }}
                                    </p>

                                    <p
                                        v-if="
                                            capability.recommendation_basis
                                            && capability.recommendation_basis !== 'professional_transformation_capability'
                                        "
                                        class="mt-3 rounded-lg border border-slate-100 bg-white p-3 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                                    >
                                        <span
                                            class="font-semibold text-slate-800 dark:text-slate-100"
                                            >Por qué lo recomendamos:</span
                                        >
                                        {{ capability.recommendation_basis }}
                                    </p>

                                    <div
                                        v-if="capability.includes.length"
                                        class="mt-3"
                                    >
                                        <p
                                            class="text-xs font-semibold text-slate-600"
                                        >
                                            Incluye
                                        </p>
                                        <ul
                                            class="mt-1 list-disc space-y-1 pl-4 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                        >
                                            <li
                                                v-for="item in capability.includes.slice(0, 4)"
                                                :key="item"
                                            >
                                                {{ item }}
                                            </li>
                                        </ul>
                                        <p
                                            v-if="
                                                capability.includes.length > 4
                                            "
                                            class="mt-1 text-xs text-slate-600"
                                        >
                                            +{{
                                                capability.includes.length - 4
                                            }}
                                            elementos adicionales en el Plan
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </PortalSection>
        </PortalPage>
    </AppLayout>
</template>
