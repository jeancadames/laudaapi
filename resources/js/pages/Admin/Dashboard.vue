<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    ClipboardCheck,
    Clock,
    Mail,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type DashboardStats = {
    requests: {
        total: number;
        pending: number;
        under_review: number;
        more_info_required: number;
        rejected: number;
        review_queue: number;
    };
    access: {
        approved: number;
        invited: number;
        active: number;
        invitation_pipeline: number;
    };
    assessments: {
        draft: number;
        in_progress: number;
        submitted: number;
        reviewed: number;
        completed: number;
        results_to_review: number;
    };
    modalities: {
        guided: number;
        assisted: number;
        managed: number;
    };
};

type RecentRequest = {
    id: number;
    name: string;
    company: string;
    email: string;
    status: string;
    assessment_status: string | null;
    maturity_score: number | null;
    recommended_modality: string | null;
    recommended_modality_label: string | null;
    created_at: string | null;
    href: string;
};

const props = defineProps<{
    stats: DashboardStats;
    recentRequests: RecentRequest[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

function formatDate(value: string | null): string {
    if (!value) return '—';

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-DO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
}

function statusLabel(status: string): string {
    return (
        {
            pending: 'Pendiente',
            under_review: 'En revisión',
            more_info_required: 'Más información',
            approved: 'Aprobada',
            invited: 'Invitación enviada',
            active: 'Acceso activo',
            rejected: 'Rechazada',
        }[status] ?? status
    );
}

function statusClass(status: string): string {
    if (status === 'rejected') {
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300';
    }

    if (status === 'active') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300';
    }

    if (status === 'invited' || status === 'approved') {
        return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300';
    }

    if (status === 'under_review' || status === 'more_info_required') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300';
}

function assessmentLabel(status: string | null): string {
    if (!status) return 'Sin diagnóstico';

    return (
        {
            draft: 'No iniciado',
            in_progress: 'En curso',
            submitted: 'Enviado',
            reviewed: 'Revisado',
        }[status] ?? status
    );
}

function assessmentClass(status: string | null): string {
    if (status === 'reviewed') {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (status === 'submitted') {
        return 'text-[#F53003] dark:text-red-300';
    }

    if (status === 'in_progress') {
        return 'text-blue-600 dark:text-blue-400';
    }

    return 'text-slate-400 dark:text-slate-500';
}

const flowItems = [
    {
        label: 'Solicitudes',
        hint: 'Accesos recibidos',
        value: () => props.stats.requests.total,
    },
    {
        label: 'Por gestionar',
        hint: 'Pendiente o en revisión',
        value: () => props.stats.requests.review_queue,
    },
    {
        label: 'Invitaciones',
        hint: 'Aprobadas o enviadas',
        value: () => props.stats.access.invitation_pipeline,
    },
    {
        label: 'Accesos activos',
        hint: 'Invitación aceptada',
        value: () => props.stats.access.active,
    },
    {
        label: 'Resultados',
        hint: 'Enviados o revisados',
        value: () => props.stats.assessments.completed,
    },
];
</script>

<template>
    <Head title="LAUDA 360 · Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 md:p-6">
            <section
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="page-header">
                    <div
                        class="mb-2 inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
                    >
                        LAUDA Transformación Digital 360
                    </div>

                    <h1>Centro de control</h1>

                    <p class="max-w-2xl">
                        Seguimiento operativo del acceso, diagnóstico y avance
                        de cada organización dentro de LAUDA 360.
                    </p>
                </div>

                <Link
                    href="/admin/diagnosis-requests"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#F53003] px-4 text-sm font-bold text-white shadow-sm transition hover:bg-[#D92A03] focus-visible:ring-2 focus-visible:ring-[#F53003]/35 focus-visible:outline-none"
                >
                    Gestionar diagnósticos
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/50 dark:shadow-none"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-[10px] font-black tracking-[0.14em] text-slate-400 uppercase"
                            >
                                Solicitudes
                            </p>
                            <p
                                class="mt-2 text-3xl font-black tracking-tight text-slate-900 dark:text-white"
                            >
                                {{ props.stats.requests.total }}
                            </p>
                        </div>

                        <span
                            class="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <ClipboardCheck class="h-5 w-5" />
                        </span>
                    </div>

                    <p class="mt-2 text-xs text-slate-500">
                        Solicitudes de Diagnóstico 360 recibidas.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-red-200 bg-red-50/70 p-5 dark:border-red-900/50 dark:bg-red-950/20"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-[10px] font-black tracking-[0.14em] text-red-400 uppercase"
                            >
                                Por gestionar
                            </p>
                            <p
                                class="mt-2 text-3xl font-black tracking-tight text-red-700 dark:text-red-300"
                            >
                                {{ props.stats.requests.review_queue }}
                            </p>
                        </div>

                        <span
                            class="grid h-10 w-10 place-items-center rounded-xl bg-red-100 text-[#F53003] dark:bg-red-950/50 dark:text-red-300"
                        >
                            <Clock class="h-5 w-5" />
                        </span>
                    </div>

                    <p
                        class="mt-2 text-xs text-red-700/65 dark:text-red-300/65"
                    >
                        Pendientes, en revisión o esperando información.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-blue-200 bg-blue-50/70 p-5 dark:border-blue-900/50 dark:bg-blue-950/20"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-[10px] font-black tracking-[0.14em] text-blue-400 uppercase"
                            >
                                Invitaciones
                            </p>
                            <p
                                class="mt-2 text-3xl font-black tracking-tight text-blue-700 dark:text-blue-300"
                            >
                                {{ props.stats.access.invitation_pipeline }}
                            </p>
                        </div>

                        <span
                            class="grid h-10 w-10 place-items-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300"
                        >
                            <Mail class="h-5 w-5" />
                        </span>
                    </div>

                    <p
                        class="mt-2 text-xs text-blue-700/65 dark:text-blue-300/65"
                    >
                        Solicitudes aprobadas o con invitación enviada.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-900/50 dark:bg-emerald-950/20"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-[10px] font-black tracking-[0.14em] text-emerald-500 uppercase"
                            >
                                Diagnósticos enviados
                            </p>
                            <p
                                class="mt-2 text-3xl font-black tracking-tight text-emerald-700 dark:text-emerald-300"
                            >
                                {{ props.stats.assessments.completed }}
                            </p>
                        </div>

                        <span
                            class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300"
                        >
                            <CheckCircle2 class="h-5 w-5" />
                        </span>
                    </div>

                    <p
                        class="mt-2 text-xs text-emerald-700/65 dark:text-emerald-300/65"
                    >
                        Resultados enviados por cliente o ya revisados.
                    </p>
                </div>
            </section>

            <section class="grid gap-5 lg:grid-cols-[1.45fr_0.85fr]">
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/50 dark:shadow-none"
                >
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                            >
                                Flujo actual
                            </p>
                            <h2
                                class="mt-1 text-lg font-black tracking-tight text-slate-900 dark:text-white"
                            >
                                Hitos de Diagnóstico 360
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                Lectura rápida del recorrido desde la solicitud
                                hasta el resultado.
                            </p>
                        </div>

                        <Link
                            href="/admin/diagnosis-requests"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-[#F53003] transition hover:text-[#D92A03] sm:mt-0"
                        >
                            Ver gestión
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div
                            v-for="(item, index) in flowItems"
                            :key="item.label"
                            class="relative rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/50"
                        >
                            <span
                                class="text-[9px] font-black tracking-[0.14em] text-slate-400 uppercase"
                            >
                                0{{ index + 1 }}
                            </span>
                            <p
                                class="mt-2 text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                            >
                                {{ item.value() }}
                            </p>
                            <p
                                class="mt-1 text-[11px] font-bold text-slate-700 dark:text-slate-300"
                            >
                                {{ item.label }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                {{ item.hint }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/50 dark:shadow-none"
                >
                    <p
                        class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                    >
                        Prioridades
                    </p>
                    <h2
                        class="mt-1 text-lg font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        Qué requiere atención
                    </h2>

                    <div class="mt-4 space-y-3">
                        <div
                            v-if="props.stats.requests.review_queue > 0"
                            class="rounded-xl border border-red-200 bg-red-50/70 p-3.5 dark:border-red-900/50 dark:bg-red-950/20"
                        >
                            <p
                                class="text-sm font-black text-red-700 dark:text-red-300"
                            >
                                {{ props.stats.requests.review_queue }}
                                solicitud(es) por gestionar
                            </p>
                            <p
                                class="mt-1 text-xs leading-relaxed text-red-700/65 dark:text-red-300/65"
                            >
                                Revise pendientes, solicitudes en evaluación o
                                casos que requieren más información.
                            </p>
                        </div>

                        <div
                            v-if="props.stats.assessments.results_to_review > 0"
                            class="rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 dark:border-amber-900/50 dark:bg-amber-950/20"
                        >
                            <p
                                class="text-sm font-black text-amber-700 dark:text-amber-300"
                            >
                                {{ props.stats.assessments.results_to_review }}
                                diagnóstico(s) enviado(s)
                            </p>
                            <p
                                class="mt-1 text-xs leading-relaxed text-amber-700/65 dark:text-amber-300/65"
                            >
                                El cliente terminó el cuestionario y el
                                resultado está listo para revisión LAUDA.
                            </p>
                        </div>

                        <div
                            v-if="
                                props.stats.requests.review_queue === 0 &&
                                props.stats.assessments.results_to_review === 0
                            "
                            class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3.5 dark:border-emerald-900/50 dark:bg-emerald-950/20"
                        >
                            <p
                                class="text-sm font-black text-emerald-700 dark:text-emerald-300"
                            >
                                Sin acciones críticas
                            </p>
                            <p
                                class="mt-1 text-xs leading-relaxed text-emerald-700/65 dark:text-emerald-300/65"
                            >
                                No hay solicitudes ni resultados pendientes de
                                revisión en este momento.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/50 dark:shadow-none"
            >
                <div
                    class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                >
                    <div>
                        <p
                            class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                        >
                            Actividad reciente
                        </p>
                        <h2
                            class="mt-1 text-lg font-black tracking-tight text-slate-900 dark:text-white"
                        >
                            Últimas solicitudes
                        </h2>
                    </div>

                    <Link
                        href="/admin/diagnosis-requests"
                        class="inline-flex items-center gap-1 text-xs font-bold text-[#F53003] transition hover:text-[#D92A03]"
                    >
                        Ver todas
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </div>

                <div
                    v-if="props.recentRequests.length === 0"
                    class="px-5 py-12 text-center"
                >
                    <div
                        class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"
                    >
                        <Building2 class="h-5 w-5" />
                    </div>
                    <p
                        class="mt-3 text-sm font-black text-slate-700 dark:text-slate-200"
                    >
                        Aún no hay solicitudes de Diagnóstico 360
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Las nuevas solicitudes aparecerán aquí automáticamente.
                    </p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[760px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left dark:border-slate-800"
                            >
                                <th
                                    class="px-5 py-3 text-[10px] font-black tracking-[0.12em] text-slate-400 uppercase"
                                >
                                    Organización
                                </th>
                                <th
                                    class="px-5 py-3 text-[10px] font-black tracking-[0.12em] text-slate-400 uppercase"
                                >
                                    Acceso
                                </th>
                                <th
                                    class="px-5 py-3 text-[10px] font-black tracking-[0.12em] text-slate-400 uppercase"
                                >
                                    Diagnóstico
                                </th>
                                <th
                                    class="px-5 py-3 text-[10px] font-black tracking-[0.12em] text-slate-400 uppercase"
                                >
                                    Resultado
                                </th>
                                <th
                                    class="px-5 py-3 text-[10px] font-black tracking-[0.12em] text-slate-400 uppercase"
                                >
                                    Fecha
                                </th>
                                <th class="w-12 px-5 py-3"></th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="item in props.recentRequests"
                                :key="item.id"
                                class="border-b border-slate-100 last:border-0 dark:border-slate-800/70"
                            >
                                <td class="px-5 py-4">
                                    <p
                                        class="text-sm font-black text-slate-800 dark:text-slate-100"
                                    >
                                        {{
                                            item.company ||
                                            'Empresa por definir'
                                        }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ item.name }} · {{ item.email }}
                                    </p>
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black"
                                        :class="statusClass(item.status)"
                                    >
                                        {{ statusLabel(item.status) }}
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        class="text-xs font-bold"
                                        :class="
                                            assessmentClass(
                                                item.assessment_status,
                                            )
                                        "
                                    >
                                        {{
                                            assessmentLabel(
                                                item.assessment_status,
                                            )
                                        }}
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <template
                                        v-if="item.maturity_score !== null"
                                    >
                                        <p
                                            class="text-sm font-black text-slate-800 dark:text-slate-100"
                                        >
                                            {{
                                                item.maturity_score.toFixed(0)
                                            }}/100
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-slate-400"
                                        >
                                            {{
                                                item.recommended_modality_label ||
                                                'Modalidad por definir'
                                            }}
                                        </p>
                                    </template>
                                    <span v-else class="text-xs text-slate-400">
                                        —
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-xs text-slate-500">
                                    {{ formatDate(item.created_at) }}
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <Link
                                        :href="item.href"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-[#F53003] dark:hover:bg-red-950/30"
                                        aria-label="Abrir solicitud"
                                    >
                                        <ArrowRight class="h-4 w-4" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-900/30"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <p
                            class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                        >
                            Ruta LAUDA 360
                        </p>
                        <h2
                            class="mt-1 text-base font-black tracking-tight text-slate-900 dark:text-white"
                        >
                            Construimos la plataforma en el mismo orden que la
                            transformación.
                        </h2>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-[10px] font-black text-[#F53003] dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
                        >
                            Diagnóstico 360 · Activo
                        </span>
                        <span
                            class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold text-slate-500 dark:border-slate-700 dark:bg-slate-900"
                        >
                            Informe Ampliado · Próximo
                        </span>
                        <span
                            class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold text-slate-500 dark:border-slate-700 dark:bg-slate-900"
                        >
                            Roadmap Detallado · Después
                        </span>
                        <span
                            class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold text-slate-500 dark:border-slate-700 dark:bg-slate-900"
                        >
                            Ejecución · Progresiva
                        </span>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
