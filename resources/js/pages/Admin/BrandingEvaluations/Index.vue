<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    CircleDashed,
    ClipboardCheck,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type EvaluationRow = {
    id: number;
    company: {
        id: number;
        name: string;
    };
    status: string;
    status_label: string;
    activated_at: string | null;
    started_at: string | null;
    summary: {
        total: number;
        evaluated: number;
        pending: number;
        requires_attention: number;
        adequate: number;
        not_applicable: number;
        all_evaluated: boolean;
    };
    url: string;
};

defineProps<{
    evaluations: EvaluationRow[];
}>();

function dateLabel(value: string | null): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Administración', href: '/admin' },
            {
                title: 'Evaluaciones de Branding',
                href: '/admin/branding-evaluations',
            },
        ]"
    >
        <Head title="Evaluaciones de Branding" />

        <div class="space-y-6 p-4 md:p-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <Badge variant="outline">
                        LAUDA 360
                    </Badge>

                    <h1
                        class="mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white"
                    >
                        Evaluaciones de Branding e Identidad Digital
                    </h1>

                    <p
                        class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                    >
                        Revisa las evaluaciones iniciadas por las empresas,
                        documenta hallazgos y confirma qué áreas requieren
                        atención profesional.
                    </p>
                </div>
            </div>

            <div
                v-if="evaluations.length"
                class="grid gap-4"
            >
                <Card
                    v-for="row in evaluations"
                    :key="row.id"
                >
                    <CardHeader>
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <CardTitle>
                                        {{ row.company.name }}
                                    </CardTitle>

                                    <Badge variant="outline">
                                        {{ row.status_label }}
                                    </Badge>
                                </div>

                                <CardDescription class="mt-2">
                                    Iniciada:
                                    {{ dateLabel(row.started_at ?? row.activated_at) }}
                                </CardDescription>
                            </div>

                            <Link
                                :href="row.url"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950"
                            >
                                Abrir evaluación
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div
                                class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                            >
                                <div class="flex items-center gap-2">
                                    <ClipboardCheck class="h-4 w-4" />
                                    <span class="text-xs font-bold uppercase">
                                        Evaluadas
                                    </span>
                                </div>

                                <p class="mt-2 text-2xl font-black">
                                    {{ row.summary.evaluated }}
                                    /
                                    {{ row.summary.total }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                            >
                                <div class="flex items-center gap-2">
                                    <CircleDashed class="h-4 w-4" />
                                    <span class="text-xs font-bold uppercase">
                                        Pendientes
                                    </span>
                                </div>

                                <p class="mt-2 text-2xl font-black">
                                    {{ row.summary.pending }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                            >
                                <div class="flex items-center gap-2">
                                    <CheckCircle2 class="h-4 w-4" />
                                    <span class="text-xs font-bold uppercase">
                                        Requieren atención
                                    </span>
                                </div>

                                <p class="mt-2 text-2xl font-black">
                                    {{ row.summary.requires_attention }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                            >
                                <span class="text-xs font-bold uppercase">
                                    Adecuadas / no aplica
                                </span>

                                <p class="mt-2 text-2xl font-black">
                                    {{
                                        row.summary.adequate
                                            + row.summary.not_applicable
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-else>
                <CardHeader>
                    <CardTitle>
                        No hay evaluaciones de Branding
                    </CardTitle>
                    <CardDescription>
                        Cuando una empresa inicie una Evaluación de Branding e
                        Identidad Digital, aparecerá aquí.
                    </CardDescription>
                </CardHeader>
            </Card>
        </div>
    </AppLayout>
</template>
