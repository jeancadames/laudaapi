<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    CheckCircle2,
    Circle,
    Clock3,
    LoaderCircle,
    Palette,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Transformation360Stage = {
    key: string;
    label: string;
    state: 'completed' | 'current' | 'available' | 'pending';
    status_label: string;
    description: string;
    url: string | null;
    action_label: string | null;
};

type OptionalBrandingCapability = {
    capability_key: 'branding_identity';
    title: string;
    optional: true;
    recommended: boolean;
    recommendation_basis: string | null;
    decision: 'pending' | 'accepted' | 'declined' | null;
    activated: boolean;
    status: string | null;
    can_activate: boolean;
    activation_endpoint: string | null;
    decline_endpoint: string | null;
    workspace_url: string | null;
    roadmap_url: string | null;
};

type ImplementationProfessionalCapability = {
    capability_key: string;
    title: string;
    kind: 'professional_service';
    recommended: boolean;
    recommendation_basis: string | null;
    data_dimension_score: number | null;
    data_priority: string | null;
    purpose: string | null;
    includes: string[];
    activation_policy: 'implementation_only';
    commercial_note: string | null;
    recommended_in_plan: boolean;
    phase_sequence: number | null;
    phase_name: string | null;
    roadmap_url: string | null;
    plan_url: string | null;
};

type Transformation360Journey = {
    visible: boolean;
    has_workflow: boolean;
    assessment_id: number | null;
    organization_name: string | null;
    current_label: string | null;
    plan_public: boolean;
    optional_capabilities: {
        branding_identity?: OptionalBrandingCapability;
    };
    professional_capabilities?: Record<
        string,
        ImplementationProfessionalCapability
    >;
    stages: Transformation360Stage[];
    primary_action: {
        label: string;
        url: string;
    } | null;
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
        subscriber_id: number | null;
    };
    transformation360: Transformation360Journey;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    {
        title: 'Transformación 360',
        href: '/app/transformacion-360',
    },
];

const activatingBranding = ref(false);
const decliningBranding = ref(false);

const optionalBranding = computed(
    () => props.transformation360.optional_capabilities?.branding_identity ?? null,
);

const implementationOnlyCapabilities = computed(
    () =>
        Object.values(
            props.transformation360.professional_capabilities ?? {},
        ),
);

function activateBranding(): void {
    const branding = optionalBranding.value;

    if (
        !branding
        || branding.activated
        || !branding.can_activate
        || !branding.activation_endpoint
        || activatingBranding.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Iniciar la evaluación de Branding e Identidad Digital? La evaluación está incluida. Los trabajos posteriores de diseño, desarrollo o implementación se definirán y cotizarán por separado.',
        )
    ) {
        return;
    }

    router.post(
        branding.activation_endpoint,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                activatingBranding.value = true;
            },
            onFinish: () => {
                activatingBranding.value = false;
            },
        },
    );
}

function declineBranding(): void {
    const branding = optionalBranding.value;

    if (
        !branding
        || !branding.recommended
        || branding.activated
        || branding.decision === 'declined'
        || !branding.decline_endpoint
        || decliningBranding.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Marcar esta recomendación como “Ahora no”? Branding seguirá disponible para activarlo después.',
        )
    ) {
        return;
    }

    router.post(
        branding.decline_endpoint,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                decliningBranding.value = true;
            },
            onFinish: () => {
                decliningBranding.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Transformación 360" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-slate-50/60 py-6 dark:bg-slate-950/40">
            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8"
            >
                <header
                    class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-5 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div>
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 dark:border-red-950 dark:bg-red-950/40 dark:text-red-300"
                            >
                                <Sparkles class="h-3.5 w-3.5" />
                                LAUDA 360
                            </div>

                            <h1
                                class="mt-4 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                            >
                                Transformación Digital 360
                            </h1>

                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 sm:text-base dark:text-slate-400"
                            >
                                Sigue el recorrido de
                                {{ props.company.name }} desde el diagnóstico
                                inicial hasta sus tres entregables consultivos
                                gratuitos.
                            </p>
                        </div>

                        <a
                            href="/app"
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-800 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-100 dark:hover:bg-slate-900"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Volver a Inicio
                        </a>
                    </div>
                </header>

                <section
                    class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800"
                    >
                        <div>
                            <p
                                class="text-xs font-black tracking-wide text-red-600 uppercase"
                            >
                                Estado actual
                            </p>

                            <h2
                                class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                            >
                                {{
                                    props.transformation360.current_label ??
                                    'Tu recorrido de transformación'
                                }}
                            </h2>
                        </div>

                        <a
                            v-if="props.transformation360.primary_action?.url"
                            :href="props.transformation360.primary_action.url"
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        >
                            {{ props.transformation360.primary_action.label }}
                            <ArrowUpRight class="h-4 w-4" />
                        </a>
                    </div>

                    <div
                        class="grid gap-3 p-5 sm:p-6 md:grid-cols-2 xl:grid-cols-4"
                    >
                        <article
                            v-for="(stage, index) in props.transformation360
                                .stages"
                            :key="stage.key"
                            class="flex min-h-56 flex-col rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                            :class="{
                                'bg-emerald-50/40 dark:bg-emerald-950/10':
                                    stage.state === 'completed',
                                'border-red-200 bg-red-50/30 ring-1 ring-red-100 dark:border-red-950 dark:bg-red-950/10 dark:ring-red-950':
                                    stage.state === 'current',
                                'bg-slate-50/60 dark:bg-slate-900/30':
                                    stage.state === 'pending',
                            }"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <CheckCircle2
                                        v-if="stage.state === 'completed'"
                                        class="h-4 w-4 text-emerald-600"
                                    />

                                    <Clock3
                                        v-else-if="
                                            stage.state === 'current' ||
                                            stage.state === 'available'
                                        "
                                        class="h-4 w-4 text-red-600"
                                    />

                                    <Circle
                                        v-else
                                        class="h-4 w-4 text-slate-400"
                                    />
                                </div>

                                <span
                                    class="rounded-full border border-slate-200 bg-white px-2 py-1 text-[10px] font-black text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                                >
                                    {{ stage.status_label }}
                                </span>
                            </div>

                            <p
                                class="mt-4 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Etapa {{ index + 1 }}
                            </p>

                            <h3
                                class="mt-1 font-black text-slate-950 dark:text-white"
                            >
                                {{ stage.label }}
                            </h3>

                            <p
                                class="mt-2 flex-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{ stage.description }}
                            </p>

                            <a
                                v-if="stage.url && stage.action_label"
                                :href="stage.url"
                                class="mt-4 inline-flex h-9 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-800 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-100 dark:hover:bg-slate-900"
                            >
                                {{ stage.action_label }}
                                <ArrowUpRight class="h-3.5 w-3.5" />
                            </a>

                            <div
                                v-else-if="
                                    stage.key === 'implementation_plan' &&
                                    stage.status_label === 'En preparación'
                                "
                                class="mt-4 rounded-xl border border-dashed border-slate-200 px-3 py-2 text-center text-xs font-semibold text-slate-500 dark:border-slate-800"
                            >
                                Borrador privado de LAUDA
                            </div>
                        </article>
                    </div>

                    <div
                        class="border-t border-slate-100 px-5 py-4 text-xs leading-5 text-slate-500 sm:px-6 dark:border-slate-800"
                    >
                        Informe Ampliado, Roadmap Detallado y Plan de
                        Implementación son entregables gratuitos del recorrido
                        consultivo. La contratación de apoyo para ejecutar el
                        Plan se gestiona fuera de estas etapas.
                    </div>
                </section>

                <section
                    v-if="optionalBranding"
                    class="rounded-[2rem] border border-slate-200/70 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="max-w-3xl">
                            <div class="flex flex-wrap items-center gap-2">
                                <Palette class="h-5 w-5 text-red-600" />
                                <p
                                    class="text-lg font-black text-slate-950 dark:text-white"
                                >
                                    {{ optionalBranding.title }}
                                </p>
                                <span
                                    class="rounded-full border border-slate-200 px-2.5 py-1 text-[10px] font-black text-slate-600 uppercase dark:border-slate-800 dark:text-slate-300"
                                >
                                    Opcional
                                </span>
                                <span
                                    v-if="optionalBranding.recommended"
                                    class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black text-red-700 uppercase dark:bg-red-950/30 dark:text-red-300"
                                >
                                    Recomendado por tu Diagnóstico 360
                                </span>
                            </div>

                            <p
                                class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                <template v-if="optionalBranding.recommended">
                                    {{
                                        optionalBranding.recommendation_basis ??
                                        'Tu Diagnóstico 360 recomienda revisar Branding e Identidad Digital.'
                                    }}
                                    La recomendación no es obligatoria: tú
                                    decides si activarlo.
                                </template>
                                <template v-else>
                                    Branding e Identidad Digital está disponible
                                    para seleccionarlo manualmente aunque tu
                                    evaluación no lo recomiende.
                                </template>
                            </p>

                            <p
                                v-if="optionalBranding.decision === 'declined'"
                                class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                            >
                                Marcaste “Ahora no”. Esta decisión quedó
                                registrada, pero puedes activar Branding cuando
                                quieras.
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a
                                v-if="optionalBranding.activated && optionalBranding.workspace_url"
                                :href="optionalBranding.workspace_url"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                Abrir Branding
                            </a>

                            <Button
                                v-else-if="optionalBranding.can_activate"
                                type="button"
                                :disabled="activatingBranding"
                                @click="activateBranding"
                            >
                                <LoaderCircle
                                    v-if="activatingBranding"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <Palette
                                    v-else
                                    class="mr-2 h-4 w-4"
                                />
                                Iniciar evaluación
                            </Button>

                            <Button
                                v-if="
                                    optionalBranding.recommended
                                    && !optionalBranding.activated
                                    && optionalBranding.decision !== 'declined'
                                    && optionalBranding.decline_endpoint
                                "
                                variant="outline"
                                type="button"
                                :disabled="decliningBranding"
                                @click="declineBranding"
                            >
                                <LoaderCircle
                                    v-if="decliningBranding"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                Ahora no
                            </Button>
                        </div>
                    </div>
                </section>

                <!-- IMPLEMENTATION-ONLY PROFESSIONAL CAPABILITIES -->
                <section
                    v-if="implementationOnlyCapabilities.length"
                    class="rounded-[2rem] border border-slate-200/70 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="space-y-5">
                        <div>
                            <p
                                class="text-xs font-black tracking-wide text-red-600 uppercase"
                            >
                                Capacidades profesionales
                            </p>

                            <h2
                                class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                            >
                                Recomendaciones para la Etapa de Implementación
                            </h2>

                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                Estas capacidades pueden ser identificadas por
                                tu Diagnóstico 360. No se activan
                                automáticamente: su alcance, tiempo y precio se
                                definen durante la Etapa de Implementación.
                            </p>
                        </div>

                        <article
                            v-for="capability in implementationOnlyCapabilities"
                            :key="capability.capability_key"
                            class="rounded-2xl border border-slate-200/70 bg-slate-50/40 p-5 dark:border-slate-800 dark:bg-slate-900/20"
                        >
                            <div
                                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="max-w-4xl">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Sparkles
                                            class="h-5 w-5 text-blue-600"
                                        />

                                        <p
                                            class="text-lg font-black text-slate-950 dark:text-white"
                                        >
                                            {{ capability.title }}
                                        </p>

                                        <span
                                            class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black text-blue-700 uppercase dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300"
                                        >
                                            Servicio profesional
                                        </span>

                                        <span
                                            v-if="capability.recommended"
                                            class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black text-red-700 uppercase dark:bg-red-950/30 dark:text-red-300"
                                        >
                                            Recomendado por tu Diagnóstico 360
                                        </span>

                                        <span
                                            v-if="capability.recommended_in_plan"
                                            class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-700 uppercase dark:bg-emerald-950/30 dark:text-emerald-300"
                                        >
                                            Recomendado en tu Plan de Implementación
                                        </span>
                                    </div>

                                    <div
                                        v-if="
                                            capability.data_dimension_score !==
                                                null ||
                                            capability.data_priority
                                        "
                                        class="mt-4 inline-flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                                    >
                                        <span>
                                            Datos e Inteligencia:
                                        </span>

                                        <span
                                            v-if="
                                                capability.data_dimension_score !==
                                                null
                                            "
                                        >
                                            {{
                                                capability.data_dimension_score
                                            }}/100
                                        </span>

                                        <span
                                            v-if="capability.data_priority"
                                        >
                                            · prioridad
                                            {{ capability.data_priority }}
                                        </span>
                                    </div>

                                    <p
                                        v-if="capability.purpose"
                                        class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        {{ capability.purpose }}
                                    </p>

                                    <p
                                        v-if="capability.recommendation_basis"
                                        class="mt-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs leading-5 text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                                    >
                                        {{
                                            capability.recommendation_basis
                                        }}
                                    </p>

                                    <div
                                        v-if="capability.includes.length"
                                        class="mt-5"
                                    >
                                        <p
                                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                        >
                                            Alcance considerado
                                        </p>

                                        <ul
                                            class="mt-3 grid gap-2 text-xs leading-5 text-slate-600 sm:grid-cols-2 dark:text-slate-300"
                                        >
                                            <li
                                                v-for="item in capability.includes.slice(
                                                    0,
                                                    6,
                                                )"
                                                :key="item"
                                                class="flex gap-2"
                                            >
                                                <CheckCircle2
                                                    class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600"
                                                />
                                                <span>{{ item }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div
                                        class="mt-5 rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3 dark:border-blue-950 dark:bg-blue-950/20"
                                    >
                                        <p
                                            class="text-xs font-black text-blue-800 dark:text-blue-300"
                                        >
                                            Se define y cotiza en
                                            Implementación.
                                        </p>

                                        <p
                                            v-if="capability.phase_name"
                                            class="mt-1 text-xs text-blue-700/80 dark:text-blue-300/80"
                                        >
                                            {{ capability.phase_name }}
                                        </p>

                                        <p
                                            v-if="capability.commercial_note"
                                            class="mt-2 text-xs leading-5 text-blue-700/80 dark:text-blue-300/80"
                                        >
                                            {{ capability.commercial_note }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex shrink-0 flex-wrap gap-2 lg:max-w-48 lg:flex-col"
                                >
                                    <a
                                        v-if="
                                            capability.recommended_in_plan &&
                                            capability.plan_url
                                        "
                                        :href="capability.plan_url"
                                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                    >
                                        Ver Plan
                                        <ArrowUpRight class="h-4 w-4" />
                                    </a>

                                    <a
                                        v-if="capability.roadmap_url"
                                        :href="capability.roadmap_url"
                                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-800 transition hover:bg-white dark:border-slate-800 dark:text-slate-100 dark:hover:bg-slate-900"
                                    >
                                        Ver Roadmap
                                        <ArrowUpRight class="h-4 w-4" />
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
