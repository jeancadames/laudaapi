<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Database,
    Layers3,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type DataTransformationBiCapability = {
    capability_key: 'data_transformation_bi';
    title: string;
    kind: 'professional_service';
    recommended: boolean;
    recommendation_status:
        | 'recommended'
        | 'not_recommended'
        | 'not_evaluated';
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
    detail_url: string | null;
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
    };
    transformation360: {
        has_workflow: boolean;
        assessment_id: number | null;
        current_label: string | null;
        plan_public: boolean;
    };
    implementation_request: {
        id: number | null;
        status: string | null;
        status_label: string;
        requested_at: string | null;
        attempt: number | null;
        can_request: boolean;
        request_endpoint: string | null;
        changes_request_endpoint: string | null;
        agreement_endpoint: string | null;
        definition_review: {
            id: number;
            version: number;
            status: string;
            capability_key: string;
            scope: {
                scope_mode: string | null;
                capability_key: string | null;
                phases: Array<Record<string, any>>;
            };
            deliverables: Array<Record<string, any>>;
            dependencies: Array<Record<string, any>>;
            responsibilities: {
                party_assignment_status: string | null;
                assignments: Array<Record<string, any>>;
            };
            human_review: {
                state: string | null;
                completed: boolean;
                confirmations: {
                    scope_confirmed: boolean;
                    deliverables_confirmed: boolean;
                    dependencies_confirmed: boolean;
                    inputs_validated: boolean;
                    accesses_validated: boolean;
                    responsibilities_confirmed: boolean;
                };
                reviewed_at: string | null;
            };
            submitted_at: string | null;
        } | null;
    };
    capability: DataTransformationBiCapability;
}>();

const breadcrumbs = [
    {
        title: 'Transformación 360',
        href: '/app/transformacion-360',
    },
    {
        title: 'Transformación e Inteligencia de Datos para BI',
        href: '/app/transformacion-360/datos-bi',
    },
];

const recommendationLabel = computed(() => {
    if (
        props.capability.recommendation_status ===
        'recommended'
    ) {
        return 'Recomendado por tu Diagnóstico 360';
    }

    if (
        props.capability.recommendation_status ===
        'not_evaluated'
    ) {
        return 'Pendiente de Diagnóstico 360';
    }

    return 'No recomendado actualmente';
});

const priorityLabel = computed(() => {
    const labels: Record<string, string> = {
        critical: 'crítica',
        high: 'alta',
        medium: 'media',
        sustain: 'sostenimiento',
    };

    const priority =
        props.capability.data_priority;

    if (!priority) {
        return null;
    }

    return labels[priority] ?? priority;
});

const requestSubmitting = ref(false);

const requestStage = computed(() => {
    const status = props.implementation_request.status;

    const stages: Record<string, number> = {
        requested: 1,
        under_lauda_review: 2,
        definition_preparation: 3,
        awaiting_tenant_review: 4,
        changes_requested: 4,
        definition_agreed: 5,
        ready_for_commercial: 5,
    };

    return status ? (stages[status] ?? 0) : 0;
});

const requestProgress = computed(() => [
    {
        label: 'Solicitud recibida',
        reached: requestStage.value >= 1,
    },
    {
        label: 'Revisión LAUDA',
        reached: requestStage.value >= 2,
    },
    {
        label: 'Definición',
        reached: requestStage.value >= 3,
    },
    {
        label: 'Revisión de tu empresa',
        reached: requestStage.value >= 4,
    },
    {
        label: 'Definición acordada',
        reached: requestStage.value >= 5,
    },
]);

const requestImplementation = () => {
    const endpoint =
        props.implementation_request.request_endpoint;

    if (
        !endpoint ||
        !props.implementation_request.can_request ||
        requestSubmitting.value
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                requestSubmitting.value = true;
            },
            onFinish: () => {
                requestSubmitting.value = false;
            },
        },
    );
};


const tenantDefinitionReview = computed(
    () =>
        props.implementation_request
            .definition_review,
);

const definitionScopePhases = computed(
    () =>
        tenantDefinitionReview.value
            ?.scope
            ?.phases ?? [],
);

const humanReviewChecks = computed(() => {
    const confirmations =
        tenantDefinitionReview.value
            ?.human_review
            ?.confirmations;

    return [
        {
            label: 'Alcance confirmado',
            confirmed:
                confirmations
                    ?.scope_confirmed ?? false,
        },
        {
            label: 'Entregables confirmados',
            confirmed:
                confirmations
                    ?.deliverables_confirmed ?? false,
        },
        {
            label: 'Dependencias confirmadas',
            confirmed:
                confirmations
                    ?.dependencies_confirmed ?? false,
        },
        {
            label: 'Insumos validados',
            confirmed:
                confirmations
                    ?.inputs_validated ?? false,
        },
        {
            label: 'Accesos validados',
            confirmed:
                confirmations
                    ?.accesses_validated ?? false,
        },
        {
            label: 'Responsabilidades confirmadas',
            confirmed:
                confirmations
                    ?.responsibilities_confirmed ?? false,
        },
    ];
});

const definitionItemTitle = (
    item: Record<string, any>,
    fallback: string,
): string => {
    return String(
        item.title ??
            item.label ??
            item.name ??
            item.deliverable ??
            item.dependency ??
            item.initiative_title ??
            item.capability_label ??
            fallback,
    );
};

const definitionItemDescription = (
    item: Record<string, any>,
): string | null => {
    const value =
        item.description ??
        item.summary ??
        item.detail ??
        item.objective ??
        item.notes ??
        null;

    return value
        ? String(value)
        : null;
};

const phaseScopeItems = (
    phase: Record<string, any>,
): string[] => {
    const capabilities =
        Array.isArray(
            phase.capabilities,
        )
            ? phase.capabilities
            : [];

    return capabilities
        .flatMap(
            (
                capability: Record<string, any>,
            ) => {
                if (
                    Array.isArray(
                        capability.scope_items,
                    )
                ) {
                    return capability.scope_items;
                }

                if (
                    Array.isArray(
                        capability.includes,
                    )
                ) {
                    return capability.includes;
                }

                return [];
            },
        )
        .filter(
            (item: unknown) =>
                typeof item === 'string'
                && item.trim() !== '',
        )
        .map(
            (item: string) =>
                item.trim(),
        );
};

const responsibilityPartyLabel = (
    value: unknown,
): string => {
    const labels: Record<string, string> = {
        lauda: 'LAUDA',
        client: 'Tu empresa',
        shared: 'Compartida',
    };

    return labels[
        String(
            value ?? '',
        )
    ] ?? 'Por confirmar';
};


const changesRequestForm = useForm({
    reason: '',
});

const agreementSubmitting = ref(false);

const canAgreeDefinition = computed(
    () =>
        props.implementation_request.status
            === 'awaiting_tenant_review'
        && Boolean(
            tenantDefinitionReview.value,
        )
        && (
            tenantDefinitionReview.value
                ?.human_review
                ?.completed
            === true
        )
        && Boolean(
            props.implementation_request
                .agreement_endpoint,
        ),
);

function agreeDefinition(): void {
    const endpoint =
        props.implementation_request
            .agreement_endpoint;

    if (
        !canAgreeDefinition.value
        || !endpoint
        || agreementSubmitting.value
    ) {
        return;
    }

    router.post(
        endpoint,
        {},
        {
            preserveScroll: true,

            onStart: () => {
                agreementSubmitting.value = true;
            },

            onFinish: () => {
                agreementSubmitting.value = false;
            },
        },
    );
}

const canRequestDefinitionChanges = computed(
    () =>
        props.implementation_request.status
            === 'awaiting_tenant_review'
        && Boolean(
            tenantDefinitionReview.value,
        )
        && Boolean(
            props.implementation_request
                .changes_request_endpoint,
        ),
);

function requestDefinitionChanges(): void {
    const endpoint =
        props.implementation_request
            .changes_request_endpoint;

    if (
        !canRequestDefinitionChanges.value
        || !endpoint
    ) {
        return;
    }

    changesRequestForm.post(
        endpoint,
        {
            preserveScroll: true,
        },
    );
}

</script>

<template>
    <Head
        title="Transformación e Inteligencia de Datos para BI"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8"
        >
            <!-- Regreso al módulo padre -->
            <div>
                <Link
                    href="/app/transformacion-360"
                    class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-950 dark:text-slate-400 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Volver a Transformación 360
                </Link>
            </div>

            <!-- Hero -->
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="p-6 sm:p-8 lg:p-10">
                    <div
                        class="flex flex-col gap-8 xl:flex-row xl:items-start xl:justify-between"
                    >
                        <div class="max-w-4xl">
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"
                                >
                                    <Database class="h-5 w-5" />
                                </div>

                                <span
                                    class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black tracking-wide text-blue-700 uppercase dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300"
                                >
                                    Servicio profesional
                                </span>

                                <span
                                    class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black tracking-wide text-red-700 uppercase dark:bg-red-950/30 dark:text-red-300"
                                >
                                    {{ recommendationLabel }}
                                </span>
                            </div>

                            <h1
                                class="mt-5 max-w-4xl text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white"
                            >
                                Transformación e Inteligencia
                                de Datos para BI
                            </h1>

                            <p
                                class="mt-2 text-sm font-semibold text-slate-500 dark:text-slate-400"
                            >
                                {{ company.name }}
                            </p>

                            <p
                                v-if="capability.purpose"
                                class="mt-5 max-w-3xl text-base leading-7 text-slate-600 dark:text-slate-300"
                            >
                                {{ capability.purpose }}
                            </p>
                        </div>

                        <div
                            class="w-full rounded-2xl border border-slate-200/70 bg-slate-50/60 p-5 xl:w-72 dark:border-slate-800 dark:bg-slate-900/30"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Estado de la capacidad
                            </p>

                            <p
                                class="mt-3 text-base font-black leading-6 text-slate-950 dark:text-white"
                            >
                                {{ recommendationLabel }}
                            </p>

                            <div
                                class="mt-4 border-t border-slate-200/70 pt-4 dark:border-slate-800"
                            >
                                <p
                                    v-if="capability.recommended_in_plan"
                                    class="text-sm font-bold leading-6 text-emerald-700 dark:text-emerald-300"
                                >
                                    Incluida en tu Plan de Implementación
                                </p>

                                <p
                                    v-else
                                    class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Sin recomendación vigente dentro del Plan.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen -->
                <div
                    class="border-t border-slate-200/70 bg-slate-50/50 p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900/20"
                >
                    <div class="grid gap-4 md:grid-cols-3">
                        <!-- Diagnóstico -->
                        <div
                            class="flex min-h-36 flex-col rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Diagnóstico · Datos e Inteligencia
                            </p>

                            <div class="mt-auto pt-5">
                                <p
                                    class="text-3xl font-black tracking-tight text-slate-950 dark:text-white"
                                >
                                    <template
                                        v-if="
                                            capability.data_dimension_score !==
                                            null
                                        "
                                    >
                                        {{
                                            capability.data_dimension_score
                                        }}/100
                                    </template>

                                    <template v-else>
                                        Pendiente
                                    </template>
                                </p>

                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Resultado de la dimensión Datos e
                                    Inteligencia.
                                </p>
                            </div>
                        </div>

                        <!-- Prioridad -->
                        <div
                            class="flex min-h-36 flex-col rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Prioridad
                            </p>

                            <div class="mt-auto pt-5">
                                <p
                                    class="text-3xl font-black tracking-tight text-slate-950 capitalize dark:text-white"
                                >
                                    {{
                                        priorityLabel ??
                                        'Pendiente'
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Nivel de atención derivado del
                                    Diagnóstico 360.
                                </p>
                            </div>
                        </div>

                        <!-- Plan -->
                        <div
                            class="flex min-h-36 flex-col rounded-2xl border border-blue-200/70 bg-blue-50/40 p-5 dark:border-blue-950 dark:bg-blue-950/10"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-blue-600/70 uppercase dark:text-blue-400"
                            >
                                Plan de Implementación
                            </p>

                            <div class="mt-auto pt-5">
                                <template
                                    v-if="
                                        capability.recommended_in_plan
                                    "
                                >
                                    <p
                                        class="text-base font-black leading-6 text-slate-950 dark:text-white"
                                    >
                                        Recomendado en tu Plan de Implementación
                                    </p>

                                    <p
                                        v-if="capability.phase_name"
                                        class="mt-2 text-sm font-bold leading-5 text-blue-700 dark:text-blue-300"
                                    >
                                        {{
                                            capability.phase_name
                                        }}
                                    </p>
                                </template>

                                <p
                                    v-else
                                    class="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Esta capacidad todavía no está incluida en
                                    un Plan de Implementación vigente.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Contenido principal -->
            <div
                class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.8fr)] xl:items-start"
            >
                <div class="space-y-6">
                    <!-- Justificación -->
                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-300"
                            >
                                <Sparkles class="h-5 w-5" />
                            </div>

                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Diagnóstico 360
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    ¿Por qué se recomienda?
                                </h2>
                            </div>
                        </div>

                        <div
                            v-if="capability.recommendation_basis"
                            class="mt-5 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-5 text-sm leading-7 text-slate-600 dark:border-slate-800 dark:bg-slate-900/30 dark:text-slate-300"
                        >
                            {{
                                capability.recommendation_basis
                            }}
                        </div>

                        <div
                            v-else
                            class="mt-5 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-5 text-sm leading-7 text-slate-600 dark:border-slate-800 dark:bg-slate-900/30 dark:text-slate-300"
                        >
                            <template
                                v-if="
                                    transformation360.has_workflow
                                "
                            >
                                El Diagnóstico 360 vigente no está
                                recomendando esta capacidad en este momento.
                            </template>

                            <template v-else>
                                Completa tu Diagnóstico 360 para determinar
                                si esta capacidad debe formar parte de la
                                ruta recomendada para tu empresa.
                            </template>
                        </div>
                    </section>

                    <!-- Alcance -->
                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/30 dark:text-blue-300"
                            >
                                <Layers3 class="h-5 w-5" />
                            </div>

                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Datos BI
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Alcance considerado
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Áreas de información que pueden formar
                                    parte de la capa fundacional de datos de
                                    tu empresa.
                                </p>
                            </div>
                        </div>

                        <ul
                            class="mt-6 grid gap-3 md:grid-cols-2"
                        >
                            <li
                                v-for="item in capability.includes"
                                :key="item"
                                class="flex h-full gap-3 rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-300"
                            >
                                <CheckCircle2
                                    class="mt-1 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />

                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </section>
                    <!-- Definition presentada al tenant -->
                    <section
                        v-if="tenantDefinitionReview"
                        class="rounded-[2rem] border border-emerald-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-emerald-950 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Revisión de tu empresa
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Definition funcional presentada
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    Revisa la versión preparada por LAUDA
                                    para esta capacidad: alcance,
                                    entregables, dependencias y
                                    responsabilidades.
                                </p>
                            </div>

                            <div
                                class="shrink-0 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-right dark:border-emerald-900 dark:bg-emerald-950/20"
                            >
                                <p
                                    class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Versión presentada
                                </p>

                                <p
                                    class="mt-1 text-lg font-black text-emerald-800 dark:text-emerald-200"
                                >
                                    V{{ tenantDefinitionReview.version }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="tenantDefinitionReview.submitted_at"
                            class="mt-4 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Enviada a revisión:
                            {{ tenantDefinitionReview.submitted_at }}
                        </p>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Alcance funcional
                            </h3>

                            <div
                                v-if="definitionScopePhases.length"
                                class="mt-4 space-y-3"
                            >
                                <div
                                    v-for="(
                                        phase,
                                        phaseIndex
                                    ) in definitionScopePhases"
                                    :key="
                                        phase.id ??
                                        phase.sequence ??
                                        phaseIndex
                                    "
                                    class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                                >
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            definitionItemTitle(
                                                phase,
                                                `Fase ${phaseIndex + 1}`,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            definitionItemDescription(
                                                phase,
                                            )
                                        "
                                        class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            definitionItemDescription(
                                                phase,
                                            )
                                        }}
                                    </p>

                                    <ul
                                        v-if="
                                            phaseScopeItems(
                                                phase,
                                            ).length
                                        "
                                        class="mt-3 space-y-2"
                                    >
                                        <li
                                            v-for="item in phaseScopeItems(
                                                phase,
                                            )"
                                            :key="item"
                                            class="flex gap-2 text-xs leading-5 text-slate-600 dark:text-slate-300"
                                        >
                                            <CheckCircle2
                                                class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                            />

                                            <span>{{ item }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Entregables
                            </h3>

                            <div
                                class="mt-4 grid gap-3 md:grid-cols-2"
                            >
                                <div
                                    v-for="(
                                        item,
                                        index
                                    ) in tenantDefinitionReview.deliverables"
                                    :key="item.id ?? index"
                                    class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                                >
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            definitionItemTitle(
                                                item,
                                                `Entregable ${index + 1}`,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            definitionItemDescription(
                                                item,
                                            )
                                        "
                                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            definitionItemDescription(
                                                item,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Dependencias
                            </h3>

                            <div
                                v-if="
                                    tenantDefinitionReview
                                        .dependencies
                                        .length
                                "
                                class="mt-4 space-y-3"
                            >
                                <div
                                    v-for="(
                                        item,
                                        index
                                    ) in tenantDefinitionReview.dependencies"
                                    :key="item.id ?? index"
                                    class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                                >
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            definitionItemTitle(
                                                item,
                                                `Dependencia ${index + 1}`,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            definitionItemDescription(
                                                item,
                                            )
                                        "
                                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            definitionItemDescription(
                                                item,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <p
                                v-else
                                class="mt-3 text-sm text-slate-500 dark:text-slate-400"
                            >
                                No se registraron dependencias
                                adicionales para esta versión.
                            </p>
                        </div>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Responsabilidades
                            </h3>

                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="(
                                        assignment,
                                        index
                                    ) in tenantDefinitionReview.responsibilities.assignments"
                                    :key="
                                        assignment.initiative_id ??
                                        index
                                    "
                                    class="flex flex-col gap-2 rounded-2xl border border-slate-200/70 p-4 sm:flex-row sm:items-start sm:justify-between dark:border-slate-800"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                definitionItemTitle(
                                                    assignment,
                                                    `Responsabilidad ${index + 1}`,
                                                )
                                            }}
                                        </p>

                                        <p
                                            v-if="
                                                assignment.suggested_owner_role
                                            "
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Referencia LAUDA:
                                            {{
                                                assignment.suggested_owner_role
                                            }}
                                        </p>
                                    </div>

                                    <span
                                        class="shrink-0 rounded-full border border-slate-200 px-3 py-1 text-[10px] font-black tracking-wide text-slate-700 uppercase dark:border-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            responsibilityPartyLabel(
                                                assignment.responsible_party,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-7 border-t border-slate-200/70 pt-6 dark:border-slate-800"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <h3
                                    class="text-sm font-black text-slate-950 dark:text-white"
                                >
                                    Revisión humana de LAUDA
                                </h3>

                                <span
                                    class="text-xs font-bold"
                                    :class="
                                        tenantDefinitionReview
                                            .human_review
                                            .completed
                                            ? 'text-emerald-700 dark:text-emerald-300'
                                            : 'text-amber-700 dark:text-amber-300'
                                    "
                                >
                                    {{
                                        tenantDefinitionReview
                                            .human_review
                                            .completed
                                            ? 'Completada'
                                            : 'Pendiente'
                                    }}
                                </span>
                            </div>

                            <div
                                class="mt-4 grid gap-2 sm:grid-cols-2"
                            >
                                <div
                                    v-for="item in humanReviewChecks"
                                    :key="item.label"
                                    class="flex items-center gap-2 rounded-xl border border-slate-200/70 p-3 text-xs font-semibold dark:border-slate-800"
                                >
                                    <CheckCircle2
                                        class="h-4 w-4 shrink-0"
                                        :class="
                                            item.confirmed
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-slate-300 dark:text-slate-700'
                                        "
                                    />

                                    <span>{{ item.label }}</span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-7 rounded-2xl border border-blue-200/70 bg-blue-50/50 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-blue-950/10 dark:text-slate-300"
                        >
                            Esta Definition corresponde únicamente
                            al alcance funcional y técnico de esta
                            capacidad. No contiene precios ni implica
                            contratación, facturación, activación,
                            suscripción o inicio de ejecución.
                        </div>
                    </section>

                    <section
                        v-if="
                            implementation_request.status ===
                                'definition_agreed'
                            && tenantDefinitionReview
                        "
                        class="rounded-[2rem] border border-emerald-200/70 bg-emerald-50/40 p-6 shadow-sm sm:p-8 dark:border-emerald-950 dark:bg-emerald-950/10"
                    >
                        <div class="flex items-start gap-3">
                            <CheckCircle2
                                class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />

                            <div>
                                <p
                                    class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Definition acordada
                                </p>

                                <h2
                                    class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                                >
                                    Tu empresa acordó esta versión
                                </h2>

                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Esta versión queda registrada como
                                    la Definition funcional acordada por
                                    tu empresa. El acuerdo no activa el
                                    servicio, no inicia ejecución, no
                                    constituye aceptación comercial y
                                    no crea una suscripción.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="canAgreeDefinition"
                        class="rounded-[2rem] border border-emerald-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-emerald-950 dark:bg-slate-950"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-emerald-600 uppercase dark:text-emerald-400"
                        >
                            Tu decisión
                        </p>

                        <h2
                            class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                        >
                            ¿Esta Definition representa lo acordado?
                        </h2>

                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            Al acordar esta versión confirmas que el
                            alcance funcional, entregables,
                            dependencias y responsabilidades
                            presentados representan lo acordado por tu
                            empresa para continuar al cierre funcional.
                        </p>

                        <div
                            class="mt-5 rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4 text-xs leading-5 text-slate-600 dark:border-emerald-950 dark:bg-emerald-950/10 dark:text-slate-300"
                        >
                            Acordar esta Definition no activa el
                            servicio, no inicia implementación o
                            ejecución, no constituye aceptación
                            comercial y no crea una suscripción.
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                :disabled="agreementSubmitting"
                                @click="agreeDefinition"
                            >
                                <CheckCircle2 class="h-4 w-4" />

                                {{
                                    agreementSubmitting
                                        ? 'Registrando acuerdo...'
                                        : 'Acordar esta Definition'
                                }}
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="canRequestDefinitionChanges"
                        class="rounded-[2rem] border border-amber-200/70 bg-white p-6 shadow-sm sm:p-8 dark:border-amber-950 dark:bg-slate-950"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-amber-600 uppercase dark:text-amber-400"
                        >
                            Tu revisión
                        </p>

                        <h2
                            class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                        >
                            ¿Qué debemos ajustar?
                        </h2>

                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            Describe de forma concreta los cambios que
                            necesitas en esta versión de la Definition.
                            LAUDA conservará esta versión y preparará
                            posteriormente una nueva versión para revisión.
                        </p>

                        <div class="mt-5">
                            <textarea
                                v-model="changesRequestForm.reason"
                                rows="5"
                                minlength="10"
                                maxlength="4000"
                                class="w-full rounded-2xl border border-slate-200 bg-background px-4 py-3 text-sm leading-6 dark:border-slate-800"
                                placeholder="Describe los cambios que necesitas en esta Definition..."
                                :disabled="changesRequestForm.processing"
                            />

                            <div
                                class="mt-2 flex items-center justify-between gap-3"
                            >
                                <p
                                    v-if="changesRequestForm.errors.reason"
                                    class="text-xs font-semibold text-red-600 dark:text-red-400"
                                >
                                    {{ changesRequestForm.errors.reason }}
                                </p>

                                <p
                                    v-else
                                    class="text-xs text-slate-400"
                                >
                                    Mínimo 10 caracteres.
                                </p>

                                <span
                                    class="shrink-0 text-xs text-slate-400"
                                >
                                    {{ changesRequestForm.reason.length }}/4000
                                </span>
                            </div>
                        </div>

                        <div
                            class="mt-5 rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4 text-xs leading-5 text-slate-600 dark:border-amber-950 dark:bg-amber-950/10 dark:text-slate-300"
                        >
                            Solicitar cambios no modifica esta versión
                            presentada y no inicia contratación,
                            facturación, activación ni ejecución.
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                :disabled="
                                    changesRequestForm.processing
                                    || changesRequestForm.reason.trim().length < 10
                                "
                                @click="requestDefinitionChanges"
                            >
                                Solicitar cambios
                            </button>
                        </div>
                    </section>

                </div>

                <!-- Columna de contexto -->
                <aside class="space-y-6 xl:sticky xl:top-6">
                    <section
                        class="rounded-[2rem] border border-blue-200/70 bg-blue-50/50 p-6 shadow-sm dark:border-blue-950 dark:bg-blue-950/10"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-blue-600 uppercase dark:text-blue-400"
                        >
                            Próximo paso
                        </p>

                        <!-- Solicitud activa -->
                        <template
                            v-if="
                                implementation_request.status &&
                                implementation_request.status !==
                                    'cancelled'
                            "
                        >
                            <div
                                class="mt-4 flex items-start justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-500 dark:text-slate-400"
                                    >
                                        Estado de la solicitud
                                    </p>

                                    <h2
                                        class="mt-1 text-lg font-black leading-6 text-slate-950 dark:text-white"
                                    >
                                        {{
                                            implementation_request.status_label
                                        }}
                                    </h2>
                                </div>

                                <span
                                    class="shrink-0 rounded-full border border-blue-200 bg-white px-3 py-1 text-[10px] font-black tracking-wide text-blue-700 uppercase dark:border-blue-900 dark:bg-slate-950/40 dark:text-blue-300"
                                >
                                    En proceso
                                </span>
                            </div>

                            <p
                                v-if="
                                    implementation_request.requested_at
                                "
                                class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                Solicitud enviada:
                                {{
                                    implementation_request.requested_at
                                }}
                            </p>

                            <div
                                class="mt-5 space-y-3 border-t border-blue-200/70 pt-5 dark:border-blue-950"
                            >
                                <div
                                    v-for="(
                                        item,
                                        index
                                    ) in requestProgress"
                                    :key="item.label"
                                    class="flex items-center gap-3"
                                >
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-[11px] font-black"
                                        :class="
                                            item.reached
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                : 'border-slate-200 bg-white text-slate-400 dark:border-slate-800 dark:bg-slate-950'
                                        "
                                    >
                                        <CheckCircle2
                                            v-if="item.reached"
                                            class="h-4 w-4"
                                        />

                                        <span v-else>
                                            {{ index + 1 }}
                                        </span>
                                    </div>

                                    <span
                                        class="text-sm font-semibold"
                                        :class="
                                            item.reached
                                                ? 'text-slate-800 dark:text-slate-200'
                                                : 'text-slate-400 dark:text-slate-600'
                                        "
                                    >
                                        {{ item.label }}
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="
                                    implementation_request.status ===
                                    'ready_for_commercial'
                                "
                                class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs leading-5 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/20 dark:text-emerald-300"
                            >
                                La definición funcional y técnica está
                                completada. El siguiente proceso se
                                gestionará de forma separada.
                            </div>

                            <div
                                v-else
                                class="mt-5 rounded-xl border border-blue-200/70 bg-white/70 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-slate-950/40 dark:text-slate-400"
                            >
                                La solicitud no activa servicios, no
                                inicia ejecución y no genera cargos,
                                facturas ni suscripciones.
                            </div>
                        </template>

                        <!-- Solicitud disponible -->
                        <template
                            v-else-if="
                                implementation_request.can_request
                            "
                        >
                            <h2
                                class="mt-2 text-lg font-black leading-6 text-slate-950 dark:text-white"
                            >
                                {{
                                    implementation_request.status ===
                                    'cancelled'
                                        ? 'Volver a solicitar implementación'
                                        : 'Solicitar implementación'
                                }}
                            </h2>

                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Cuando decidas avanzar, envía una solicitud
                                expresa para que LAUDA revise el alcance de
                                Datos e Inteligencia BI antes de preparar la
                                definición de implementación.
                            </p>

                            <button
                                type="button"
                                class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                :disabled="requestSubmitting"
                                @click="requestImplementation"
                            >
                                {{
                                    requestSubmitting
                                        ? 'Enviando solicitud...'
                                        : implementation_request.status ===
                                            'cancelled'
                                          ? 'Volver a solicitar implementación'
                                          : 'Solicitar implementación'
                                }}
                            </button>

                            <div
                                class="mt-4 rounded-xl border border-blue-200/70 bg-white/70 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-slate-950/40 dark:text-slate-400"
                            >
                                La solicitud no activa el servicio ni genera
                                cargos. LAUDA revisará el alcance antes de
                                avanzar.
                            </div>
                        </template>

                        <!-- No elegible todavía -->
                        <template v-else>
                            <h2
                                class="mt-2 text-lg font-black leading-6 text-slate-950 dark:text-white"
                            >
                                Solicitud no disponible todavía
                            </h2>

                            <p
                                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Para solicitar esta implementación, Datos e
                                Inteligencia BI debe formar parte de un Plan
                                de Implementación presentado para tu empresa.
                            </p>
                        </template>
                    </section>

                    <section
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Alcance de esta vista
                        </p>

                        <p
                            class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            Aquí puedes consultar la recomendación,
                            prioridad, fase y alcance funcional de Datos e
                            Inteligencia BI dentro de Transformación 360.
                        </p>

                        <p
                            class="mt-4 border-t border-slate-200/70 pt-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400"
                        >
                            Esta etapa no inicia ejecución, contratación,
                            facturación, pagos ni suscripciones.
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
