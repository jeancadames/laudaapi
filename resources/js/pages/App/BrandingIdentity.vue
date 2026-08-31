<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    CheckCircle2,
    Circle,
    Clock3,
    LoaderCircle,
    Palette,
    PlayCircle,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type ProgressState = 'completed' | 'current' | 'pending';

type BrandingStep = {
    key: string;
    label: string;
    at: string | null;
    state: ProgressState;
};

type BrandingNeed = {
    id: number;
    sequence: number;
    key: string;
    title: string;
    description: string | null;
    source_type: string;
    status: string;
    status_label: string;
    identified_at: string | null;
};

type BrandingPlanPriority = {
    key: string;
    label: string;
};

type BrandingPlanInitiative = {
    id: string;
    title: string | null;
    objective: string | null;
    owner_role: string | null;
    priority: string | null;
    priority_label: string | null;
    dependencies: string[];
};

type BrandingPlanContext = {
    available: boolean;
    reason: string | null;
    plan: {
        id: number;
        version: number;
        status: string;
        status_label: string;
        presented_at: string | null;
        url: string;
    } | null;
    phase: {
        id: number;
        sequence: number;
        name: string;
        objective: string | null;
        horizon: string | null;
    } | null;
    related_initiatives: BrandingPlanInitiative[];
    priorities: BrandingPlanPriority[];
    dependencies: string[];
    deliverables: string[];
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
    };
    branding: {
        capability_key: 'branding_identity';
        title: string;
        status: string;
        status_label: string;
        purpose: string | null;
        scope: string[];
        needs: BrandingNeed[];
        plan_context: BrandingPlanContext;
        requires_lauda_review: boolean;
        recommendation: {
            recommended: boolean;
            basis: string | null;
        };
        source: {
            assessment_id: number | null;
            type: string | null;
            id: number | null;
            version: number | null;
            roadmap_url: string | null;
        };
        timestamps: {
            activated_at: string | null;
            started_at: string | null;
            ready_for_review_at: string | null;
            validated_at: string | null;
            completed_at: string | null;
        };
        progress: {
            steps: BrandingStep[];
            current_label: string;
            next_step_label: string | null;
        };
        next_action: {
            key: 'start';
            label: string;
            method: 'post';
            url: string;
            description: string;
        } | null;
        free_contract: {
            free: boolean;
            commercial_acceptance: false;
            requires_modality: false;
            requires_payment: false;
            creates_order: false;
            creates_invoice: false;
            creates_payment: false;
            creates_subscription: false;
            creates_subscription_item: false;
            creates_go_live: false;
        };
    };
}>();

const starting = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    {
        title: 'Transformación 360',
        href: '/app/transformacion-360',
    },
    {
        title: 'Branding e Identidad Digital',
        href: '/app/branding-identidad',
    },
];

const currentStep = computed(
    () =>
        props.branding.progress.steps.find(
            (step) => step.state === 'current',
        ) ?? null,
);

function formatDate(value: string | null): string {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function startBranding(): void {
    if (
        !props.branding.next_action
        || props.branding.next_action.key !== 'start'
        || starting.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Iniciar formalmente el trabajo de Branding e Identidad Digital?'
        )
    ) {
        return;
    }

    router.post(
        props.branding.next_action.url,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                starting.value = true;
            },
            onFinish: () => {
                starting.value = false;
            },
        }
    );
}
</script>

<template>
    <Head title="Branding e Identidad Digital" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="min-h-full bg-slate-50/60 py-6 dark:bg-slate-950/40">
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
                                <Palette class="h-3.5 w-3.5" />
                                LAUDA 360 · Evaluación de Branding
                            </div>

                            <h1
                                class="mt-4 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                            >
                                {{ props.branding.title }}
                            </h1>

                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 sm:text-base dark:text-slate-400"
                            >
                                Espacio de evaluación de
                                {{ props.company.name }} para organizar y seguir
                                esta capacidad profesional.
                            </p>
                        </div>

                        <span
                            class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                        >
                            <Clock3 class="h-4 w-4 text-red-600" />
                            {{ props.branding.status_label }}
                        </span>
                    </div>
                </header>

                <section class="grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
                    <article
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p
                            class="text-xs font-black tracking-wide text-red-600 uppercase"
                        >
                            Resumen
                        </p>

                        <h2
                            class="mt-2 text-xl font-black text-slate-950 dark:text-white"
                        >
                            Evaluación de Branding activa
                        </h2>

                        <p
                            class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            {{
                                props.branding.purpose ??
                                'Branding e Identidad Digital forma parte del recorrido de Transformación 360 de esta empresa.'
                            }}
                        </p>

                        <div
                            class="mt-5 rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 dark:border-emerald-950 dark:bg-emerald-950/10"
                        >
                            <div class="flex items-start gap-3">
                                <ShieldCheck
                                    class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600"
                                />
                                <div>
                                    <p
                                        class="text-sm font-black text-emerald-900 dark:text-emerald-200"
                                    >
                                        Evaluación incluida
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-emerald-800/80 dark:text-emerald-300/80"
                                    >
                                        Este workspace no constituye compra,
                                        pago, suscripción, modalidad comercial
                                        ni aceptación de una propuesta de
                                        ejecución.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p
                            class="text-xs font-black tracking-wide text-slate-400 uppercase"
                        >
                            Origen
                        </p>

                        <dl class="mt-4 space-y-4 text-sm">
                            <div>
                                <dt class="text-slate-400">Activado</dt>
                                <dd
                                    class="mt-1 font-bold text-slate-950 dark:text-white"
                                >
                                    {{
                                        formatDate(
                                            props.branding.timestamps
                                                .activated_at,
                                        )
                                    }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-slate-400">
                                    Fuente de activación
                                </dt>
                                <dd
                                    class="mt-1 font-bold text-slate-950 dark:text-white"
                                >
                                    {{
                                        props.branding.source.type === 'manual'
                                            ? 'Selección opcional del tenant'
                                            : props.branding.source.version
                                              ? `Roadmap V${props.branding.source.version}`
                                              : 'Roadmap publicado'
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <Link
                            v-if="props.branding.source.roadmap_url"
                            :href="props.branding.source.roadmap_url"
                            class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-red-600 transition hover:text-red-500"
                        >
                            Ver Roadmap de origen
                            <ArrowUpRight class="h-4 w-4" />
                        </Link>
                    </article>
                </section>

                <section
                    class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-black tracking-wide text-red-600 uppercase"
                            >
                                Estado y progreso
                            </p>
                            <h2
                                class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                            >
                                {{ props.branding.progress.current_label }}
                            </h2>
                        </div>

                        <p
                            v-if="currentStep?.at"
                            class="text-xs text-slate-400"
                        >
                            {{ formatDate(currentStep.at) }}
                        </p>
                    </div>

                    <div
                        class="mt-6 grid gap-3 md:grid-cols-5"
                    >
                        <div
                            v-for="(step, index) in props.branding.progress.steps"
                            :key="step.key"
                            class="rounded-2xl border p-4"
                            :class="{
                                'border-emerald-200 bg-emerald-50/40 dark:border-emerald-950 dark:bg-emerald-950/10':
                                    step.state === 'completed',
                                'border-red-200 bg-red-50/40 ring-1 ring-red-100 dark:border-red-950 dark:bg-red-950/10 dark:ring-red-950':
                                    step.state === 'current',
                                'border-slate-200 bg-slate-50/40 dark:border-slate-800 dark:bg-slate-900/20':
                                    step.state === 'pending',
                            }"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <CheckCircle2
                                    v-if="step.state === 'completed'"
                                    class="h-5 w-5 text-emerald-600"
                                />
                                <Clock3
                                    v-else-if="step.state === 'current'"
                                    class="h-5 w-5 text-red-600"
                                />
                                <Circle
                                    v-else
                                    class="h-5 w-5 text-slate-300"
                                />

                                <span
                                    class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                                >
                                    {{ index + 1 }}/5
                                </span>
                            </div>

                            <p
                                class="mt-3 text-sm font-black text-slate-950 dark:text-white"
                            >
                                {{ step.label }}
                            </p>

                            <p
                                v-if="step.at"
                                class="mt-1 text-[11px] text-slate-400"
                            >
                                {{ formatDate(step.at) }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-black tracking-wide text-red-600 uppercase"
                            >
                                Áreas de evaluación
                            </p>
                            <h2
                                class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                            >
                                Áreas de Branding a revisar
                            </h2>
                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                Estas áreas representan los frentes que se revisarán durante la evaluación. Si existe información relacionada en el Diagnóstico 360, Roadmap o Plan consultivo, se utiliza únicamente como contexto.
                            </p>
                        </div>

                        <span
                            class="inline-flex w-fit rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-black text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        >
                            {{ props.branding.needs.length }}
                            áreas a revisar
                        </span>
                    </div>

                    <div
                        v-if="props.branding.needs.length"
                        class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="need in props.branding.needs"
                            :key="need.id"
                            class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                        >
                            <div
                                class="flex items-start justify-between gap-3"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-black text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    {{
                                        String(need.sequence).padStart(2, '0')
                                    }}
                                </div>

                                <span
                                    class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-black text-emerald-700 uppercase dark:bg-emerald-950/30 dark:text-emerald-300"
                                >
                                    {{ need.status_label }}
                                </span>
                            </div>

                            <h3
                                class="mt-4 font-black text-slate-950 dark:text-white"
                            >
                                {{ need.title }}
                            </h3>

                            <p
                                v-if="need.description"
                                class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{ need.description }}
                            </p>
                        </article>
                    </div>

                    <div
                        v-else
                        class="mt-6 rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                    >
                        Todavía no hay áreas de evaluación disponibles.
                    </div>

                    <p
                        class="mt-5 text-xs leading-5 text-slate-400"
                    >
                        Las áreas se revisan de forma independiente. Cuando exista
                        contexto relevante en el Plan consultivo vigente,
                        se utiliza únicamente como referencia para la
                        evaluación.
                    </p>
                </section>

                <section
                    class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-black tracking-wide text-red-600 uppercase"
                            >
                                Plan consultivo
                            </p>
                            <h2
                                class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                            >
                                Contexto del Plan consultivo
                            </h2>
                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                Si existe información relevante en el Plan de
                                Implementación público vigente, se utiliza
                                únicamente como contexto para esta evaluación.
                            </p>
                        </div>

                        <Link
                            v-if="props.branding.plan_context.plan"
                            :href="props.branding.plan_context.plan.url"
                            class="inline-flex items-center gap-2 text-sm font-bold text-red-600 transition hover:text-red-500"
                        >
                            Ver Plan V{{
                                props.branding.plan_context.plan.version
                            }}
                            <ArrowUpRight class="h-4 w-4" />
                        </Link>
                    </div>

                    <template
                        v-if="
                            props.branding.plan_context.available
                            && props.branding.plan_context.phase
                        "
                    >
                        <div
                            class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            <article
                                class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                            >
                                <p
                                    class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                                >
                                    Fase sugerida
                                </p>
                                <p
                                    class="mt-2 text-sm font-black text-slate-950 dark:text-white"
                                >
                                    Fase
                                    {{
                                        props.branding.plan_context.phase
                                            .sequence
                                    }}
                                    ·
                                    {{
                                        props.branding.plan_context.phase.name
                                    }}
                                </p>
                            </article>

                            <article
                                class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                            >
                                <p
                                    class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                                >
                                    Horizonte
                                </p>
                                <p
                                    class="mt-2 text-sm font-black text-slate-950 dark:text-white"
                                >
                                    {{
                                        props.branding.plan_context.phase
                                            .horizon ?? 'No especificado'
                                    }}
                                </p>
                            </article>

                            <article
                                class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                            >
                                <p
                                    class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                                >
                                    Prioridad
                                </p>
                                <p
                                    class="mt-2 text-sm font-black text-slate-950 dark:text-white"
                                >
                                    {{
                                        props.branding.plan_context.priorities
                                            .length
                                            ? props.branding.plan_context.priorities
                                                  .map(
                                                      (priority) =>
                                                          priority.label,
                                                  )
                                                  .join(', ')
                                            : 'No especificada'
                                    }}
                                </p>
                            </article>

                            <article
                                class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                            >
                                <p
                                    class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                                >
                                    Dependencias
                                </p>
                                <p
                                    class="mt-2 text-sm font-black text-slate-950 dark:text-white"
                                >
                                    {{
                                        props.branding.plan_context.dependencies
                                            .length
                                            ? props.branding.plan_context.dependencies.join(
                                                  ', ',
                                              )
                                            : 'Ninguna'
                                    }}
                                </p>
                            </article>
                        </div>

                        <div
                            v-if="
                                props.branding.plan_context.phase.objective
                            "
                            class="mt-4 rounded-2xl border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/20"
                        >
                            <p
                                class="text-xs font-black text-slate-400 uppercase"
                            >
                                Objetivo de la fase
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    props.branding.plan_context.phase.objective
                                }}
                            </p>
                        </div>

                        <div
                            v-if="
                                props.branding.plan_context.related_initiatives
                                    .length
                            "
                            class="mt-6"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Iniciativas relacionadas
                            </h3>

                            <div
                                class="mt-3 grid gap-3 md:grid-cols-2"
                            >
                                <article
                                    v-for="initiative in props.branding
                                        .plan_context.related_initiatives"
                                    :key="initiative.id"
                                    class="rounded-2xl border border-slate-200/70 p-4 dark:border-slate-800"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="text-xs font-black text-slate-400"
                                        >
                                            {{ initiative.id }}
                                        </span>
                                        <span
                                            v-if="initiative.priority_label"
                                            class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600 uppercase dark:bg-slate-900 dark:text-slate-300"
                                        >
                                            {{
                                                initiative.priority_label
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        v-if="initiative.title"
                                        class="mt-2 text-sm font-black text-slate-950 dark:text-white"
                                    >
                                        {{ initiative.title }}
                                    </p>

                                    <p
                                        v-if="initiative.objective"
                                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{ initiative.objective }}
                                    </p>
                                </article>
                            </div>
                        </div>

                        <div
                            v-if="
                                props.branding.plan_context.deliverables.length
                            "
                            class="mt-6"
                        >
                            <h3
                                class="text-sm font-black text-slate-950 dark:text-white"
                            >
                                Entregables previstos
                            </h3>

                            <ul
                                class="mt-3 grid gap-2 sm:grid-cols-2"
                            >
                                <li
                                    v-for="deliverable in props.branding
                                        .plan_context.deliverables"
                                    :key="deliverable"
                                    class="flex gap-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    <CheckCircle2
                                        class="mt-1 h-4 w-4 shrink-0 text-emerald-600"
                                    />
                                    <span>{{ deliverable }}</span>
                                </li>
                            </ul>
                        </div>
                    </template>

                    <div
                        v-else
                        class="mt-6 rounded-2xl border border-dashed border-slate-300 p-5 text-sm leading-6 text-slate-500 dark:border-slate-700 dark:text-slate-400"
                    >
                        {{
                            props.branding.plan_context.reason ??
                            'El contexto del Plan consultivo todavía no está disponible.'
                        }}
                    </div>
                </section>

                <section class="grid gap-4 lg:grid-cols-[1fr_0.85fr]">
                    <article
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p
                            class="text-xs font-black tracking-wide text-red-600 uppercase"
                        >
                            Alcance de la evaluación
                        </p>

                        <h2
                            class="mt-1 text-xl font-black text-slate-950 dark:text-white"
                        >
                            Qué cubre esta evaluación
                        </h2>

                        <ul
                            v-if="props.branding.scope.length"
                            class="mt-5 grid gap-3 sm:grid-cols-2"
                        >
                            <li
                                v-for="item in props.branding.scope"
                                :key="item"
                                class="flex gap-3 rounded-2xl border border-slate-100 p-4 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:text-slate-300"
                            >
                                <CheckCircle2
                                    class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                                />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </article>

                    <article
                        class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p
                            class="text-xs font-black tracking-wide text-slate-400 uppercase"
                        >
                            Recomendación del Roadmap
                        </p>

                        <p
                            class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            <template v-if="props.branding.recommendation.recommended">
                                {{
                                    props.branding.recommendation.basis ??
                                    'Esta capacidad fue recomendada por el Diagnóstico 360.'
                                }}
                            </template>
                            <template v-else-if="props.branding.source.type === 'manual'">
                                Branding fue activado por selección opcional del tenant, sin requerir una recomendación del Diagnóstico 360.
                            </template>
                            <template v-else>
                                El Diagnóstico 360 no marcó Branding como recomendado; el tenant decidió activarlo de forma opcional.
                            </template>
                        </p>

                        <div
                            v-if="props.branding.requires_lauda_review"
                            class="mt-5 rounded-2xl border border-amber-100 bg-amber-50/50 p-4 dark:border-amber-950 dark:bg-amber-950/10"
                        >
                            <p
                                class="text-sm font-black text-amber-900 dark:text-amber-200"
                            >
                                Revisión de LAUDA requerida
                            </p>
                            <p
                                class="mt-1 text-xs leading-5 text-amber-800/80 dark:text-amber-300/80"
                            >
                                Los estados de revisión, validación y cierre no
                                se habilitan como acciones del tenant en esta
                                etapa.
                            </p>
                        </div>
                    </article>
                </section>

                <section
                    class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <p
                        class="text-xs font-black tracking-wide text-red-600 uppercase"
                    >
                        Próximo paso
                    </p>

                    <div
                        class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div>
                            <h2
                                class="text-xl font-black text-slate-950 dark:text-white"
                            >
                                {{
                                    props.branding.progress.next_step_label ??
                                    'Evaluación completada'
                                }}
                            </h2>

                            <p
                                v-if="props.branding.next_action"
                                class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                {{ props.branding.next_action.description }}
                            </p>
                            <p
                                v-else-if="
                                    props.branding.status === 'in_progress'
                                "
                                class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                La evaluación ya está en progreso. La siguiente
                                transición dependerá de la revisión de LAUDA.
                            </p>
                        </div>

                        <Button
                            v-if="props.branding.next_action?.key === 'start'"
                            type="button"
                            :disabled="starting"
                            @click="startBranding"
                        >
                            <LoaderCircle
                                v-if="starting"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <PlayCircle
                                v-else
                                class="mr-2 h-4 w-4"
                            />
                            {{ props.branding.next_action.label }}
                        </Button>
                    </div>
                </section>

                <div>
                    <Link
                        href="/app/transformacion-360"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-900"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Volver a Transformación 360
                    </Link>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
