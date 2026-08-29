<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    CheckCircle2,
    Circle,
    Clock3,
    Sparkles,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Transformation360Stage = {
    key: string;
    label: string;
    state: 'completed' | 'current' | 'available' | 'pending';
    status_label: string;
    description: string;
    optional: boolean;
    url: string | null;
    action_label: string | null;
};

type Transformation360Journey = {
    visible: boolean;
    has_workflow: boolean;
    assessment_id: number | null;
    organization_name: string | null;
    current_label: string | null;
    plan_public: boolean;
    execution: {
        progress_percentage: number;
        phase_count: number;
        completed_phase_count: number;
    };
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
                                inicial hasta la ejecución de su Plan de
                                Implementación.
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

                            <p
                                v-if="
                                    props.transformation360.execution
                                        .phase_count > 0
                                "
                                class="mt-2 text-sm text-slate-500"
                            >
                                {{
                                    props.transformation360.execution
                                        .completed_phase_count
                                }}
                                de
                                {{
                                    props.transformation360.execution
                                        .phase_count
                                }}
                                fases completadas ·
                                {{
                                    props.transformation360.execution
                                        .progress_percentage
                                }}% de avance
                            </p>
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
                        class="grid gap-3 p-5 sm:p-6 md:grid-cols-2 xl:grid-cols-5"
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

                                <template v-if="stage.optional">
                                    · Opcional
                                </template>
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
                        El Informe Ampliado y el Roadmap Detallado son
                        entregables opcionales. El Plan de Implementación puede
                        prepararse directamente desde el resultado oficial del
                        Diagnóstico 360.
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
