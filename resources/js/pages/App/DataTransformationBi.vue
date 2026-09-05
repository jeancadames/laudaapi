<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Database,
    Layers3,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';

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

                        <h2
                            class="mt-2 text-lg font-black leading-6 text-slate-950 dark:text-white"
                        >
                            Preparar la solicitud de implementación
                        </h2>

                        <p
                            class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            Cuando decidas avanzar, esta capacidad podrá
                            iniciar mediante una solicitud expresa de tu
                            empresa para que LAUDA revise el alcance antes
                            de cualquier implementación.
                        </p>

                        <div
                            class="mt-5 rounded-xl border border-blue-200/70 bg-white/70 p-4 text-xs leading-5 text-slate-600 dark:border-blue-950 dark:bg-slate-950/40 dark:text-slate-400"
                        >
                            La solicitud no activará servicios ni generará
                            cargos automáticamente.
                        </div>
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
