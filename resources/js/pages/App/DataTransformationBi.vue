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
            <div>
                <Link
                    href="/app/transformacion-360"
                    class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Volver a Transformación 360
                </Link>
            </div>

            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div
                    class="border-b border-slate-200/70 p-6 sm:p-8 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="max-w-4xl">
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"
                                >
                                    <Database class="h-5 w-5" />
                                </div>

                                <span
                                    class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black text-blue-700 uppercase dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300"
                                >
                                    Servicio profesional
                                </span>

                                <span
                                    class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black text-red-700 uppercase dark:bg-red-950/30 dark:text-red-300"
                                >
                                    {{ recommendationLabel }}
                                </span>

                                <span
                                    v-if="
                                        capability.recommended_in_plan
                                    "
                                    class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-700 uppercase dark:bg-emerald-950/30 dark:text-emerald-300"
                                >
                                    Recomendado en tu Plan de Implementación
                                </span>
                            </div>

                            <h1
                                class="mt-5 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white"
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
                            class="rounded-2xl border border-blue-100 bg-blue-50/60 px-5 py-4 lg:w-72 dark:border-blue-950 dark:bg-blue-950/20"
                        >
                            <p
                                class="text-xs font-black text-blue-800 dark:text-blue-300"
                            >
                                Etapa comercial
                            </p>

                            <p
                                class="mt-1 text-sm font-black text-slate-950 dark:text-white"
                            >
                                Se define y cotiza en
                                Implementación.
                            </p>

                            <p
                                class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-400"
                            >
                                Esta capacidad no se activa
                                automáticamente desde el
                                Diagnóstico 360.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_0.7fr]"
                >
                    <div class="space-y-6">
                        <section>
                            <div
                                class="flex items-center gap-2"
                            >
                                <Sparkles
                                    class="h-5 w-5 text-red-600"
                                />

                                <h2
                                    class="text-lg font-black text-slate-950 dark:text-white"
                                >
                                    Contexto de tu Diagnóstico
                                </h2>
                            </div>

                            <div
                                class="mt-4 grid gap-3 sm:grid-cols-2"
                            >
                                <div
                                    class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                                >
                                    <p
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Datos e Inteligencia
                                    </p>

                                    <p
                                        class="mt-2 text-2xl font-black text-slate-950 dark:text-white"
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
                                </div>

                                <div
                                    class="rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                                >
                                    <p
                                        class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Prioridad
                                    </p>

                                    <p
                                        class="mt-2 text-2xl font-black text-slate-950 capitalize dark:text-white"
                                    >
                                        {{
                                            priorityLabel ??
                                            'Pendiente'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="
                                    capability.recommendation_basis
                                "
                                class="mt-4 rounded-2xl border border-slate-200/70 bg-white p-5 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                            >
                                {{
                                    capability.recommendation_basis
                                }}
                            </div>

                            <div
                                v-else
                                class="mt-4 rounded-2xl border border-slate-200/70 bg-slate-50/50 p-5 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-300"
                            >
                                <template
                                    v-if="
                                        transformation360.has_workflow
                                    "
                                >
                                    El Diagnóstico 360 vigente
                                    no está recomendando esta
                                    capacidad en este momento.
                                </template>

                                <template v-else>
                                    Completa tu Diagnóstico 360
                                    para determinar si esta
                                    capacidad debe formar parte
                                    de la ruta recomendada para
                                    tu empresa.
                                </template>
                            </div>
                        </section>

                        <section>
                            <div
                                class="flex items-center gap-2"
                            >
                                <Layers3
                                    class="h-5 w-5 text-blue-600"
                                />

                                <h2
                                    class="text-lg font-black text-slate-950 dark:text-white"
                                >
                                    Alcance considerado
                                </h2>
                            </div>

                            <ul
                                class="mt-4 grid gap-3 sm:grid-cols-2"
                            >
                                <li
                                    v-for="item in capability.includes"
                                    :key="item"
                                    class="flex gap-3 rounded-2xl border border-slate-200/70 bg-slate-50/50 p-4 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-300"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                                    />

                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <aside class="space-y-4">
                        <div
                            class="flex w-full flex-col items-start gap-2.5 rounded-2xl border border-slate-200/70 bg-slate-50/50 p-5 dark:border-slate-800 dark:bg-slate-900/20"
                        >
                            <p
                                class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Plan de Implementación
                            </p>

                            <template
                                v-if="
                                    capability.recommended_in_plan
                                "
                            >
                                <p
                                    class="text-left text-base font-black leading-6 text-slate-950 dark:text-white"
                                >
                                    Recomendado en tu Plan de
                                    Implementación
                                </p>

                                <p
                                    v-if="capability.phase_name"
                                    class="text-left text-sm font-bold leading-5 text-blue-700 dark:text-blue-300"
                                >
                                    {{
                                        capability.phase_name
                                    }}
                                </p>
                            </template>

                            <p
                                v-else
                                class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                Esta capacidad todavía no está
                                recomendada dentro de un Plan de
                                Implementación vigente.
                            </p>
                        </div>


                    </aside>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
