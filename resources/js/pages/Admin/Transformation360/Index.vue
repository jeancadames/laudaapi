<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Capability = {
    key: string;
    label: string;
};

type Row = {
    contact_id: number;
    assessment_id: number;
    company: string;
    contact: {
        name: string;
        email: string;
    };
    assessment_status: string | null;
    plan: {
        id: number;
        version: number;
        status: string;
    } | null;
    definition: {
        id: number;
        version: number;
        status: string;
    } | null;
    capabilities: Capability[];
    bi_present: boolean;
    current_stage: string;
    urls: {
        diagnosis: string;
        expanded_report: string;
        detailed_roadmap: string;
        implementation_plan: string;
        definition: string | null;
    };
};

const props = defineProps<{
    rows: Row[];
    stats: {
        total: number;
        with_plan: number;
        with_definition: number;
        definitions_ready: number;
        bi: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Transformación 360',
        href: '/admin/transformation-360',
    },
];

function planLabel(row: Row): string {
    if (!row.plan) return 'Sin Plan';

    return `Plan V${row.plan.version} · ${row.plan.status}`;
}

function definitionLabel(row: Row): string {
    if (!row.definition) {
        return 'Sin Definición';
    }

    const labels: Record<string, string> = {
        draft: 'Borrador',
        under_review: 'En revisión',
        ready: 'Lista',
    };

    return `Definición V${row.definition.version} · ${
        labels[row.definition.status]
            ?? row.definition.status
    }`;
}
</script>

<template>
    <Head title="Transformación 360 · Supervisor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto w-full max-w-[1600px] space-y-6 p-4 md:p-6"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <p
                        class="text-[10px] font-black tracking-[0.18em] text-[#F53003] uppercase"
                    >
                        LAUDA 360 · Supervisor
                    </p>

                    <h1
                        class="mt-1 text-2xl font-black tracking-tight md:text-3xl"
                    >
                        Transformación 360
                    </h1>

                    <p
                        class="mt-2 max-w-4xl text-sm leading-6 text-muted-foreground"
                    >
                        Seguimiento transversal del ciclo
                        Diagnóstico → Informe → Roadmap → Plan
                        → Definición de Implementación.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link href="/admin/diagnosis-requests">
                        Diagnósticos 360
                    </Link>
                </Button>
            </div>

            <div
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
            >
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Empresas
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.total }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Con Plan
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.with_plan }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Con Definición
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.with_definition }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Definición lista
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.definitions_ready }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Datos BI
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.bi }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Empresas en Transformación 360
                    </CardTitle>

                    <CardDescription>
                        Supervisión funcional del journey actual.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="props.rows.length === 0"
                        class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No hay workflows de Transformación 360.
                    </div>

                    <div
                        v-else
                        class="space-y-4"
                    >
                        <div
                            v-for="row in props.rows"
                            :key="row.assessment_id"
                            class="rounded-xl border p-4"
                        >
                            <div
                                class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2
                                            class="font-black"
                                        >
                                            {{ row.company }}
                                        </h2>

                                        <Badge variant="secondary">
                                            {{ row.current_stage }}
                                        </Badge>

                                        <Badge
                                            v-if="row.bi_present"
                                            variant="outline"
                                        >
                                            Datos BI
                                        </Badge>
                                    </div>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ row.contact.name }}
                                        ·
                                        {{ row.contact.email }}
                                    </p>

                                    <div
                                        class="mt-3 flex flex-wrap gap-2"
                                    >
                                        <Badge variant="outline">
                                            Assessment
                                            #{{ row.assessment_id }}
                                        </Badge>

                                        <Badge variant="outline">
                                            {{ planLabel(row) }}
                                        </Badge>

                                        <Badge variant="outline">
                                            {{ definitionLabel(row) }}
                                        </Badge>
                                    </div>

                                    <div
                                        v-if="row.capabilities.length"
                                        class="mt-3 flex flex-wrap gap-2"
                                    >
                                        <Badge
                                            v-for="capability in row.capabilities"
                                            :key="capability.key"
                                            variant="outline"
                                        >
                                            {{ capability.label }}
                                        </Badge>
                                    </div>
                                </div>

                                <div
                                    class="flex flex-wrap gap-2 xl:max-w-[650px] xl:justify-end"
                                >
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link :href="row.urls.diagnosis">
                                            Diagnóstico
                                        </Link>
                                    </Button>

                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link
                                            :href="row.urls.expanded_report"
                                        >
                                            Informe
                                        </Link>
                                    </Button>

                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link
                                            :href="row.urls.detailed_roadmap"
                                        >
                                            Roadmap
                                        </Link>
                                    </Button>

                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link
                                            :href="row.urls.implementation_plan"
                                        >
                                            Plan
                                        </Link>
                                    </Button>

                                    <Button
                                        v-if="row.urls.definition"
                                        as-child
                                        size="sm"
                                    >
                                        <Link
                                            :href="row.urls.definition"
                                        >
                                            Definición
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div
                class="rounded-xl border bg-muted/30 p-4 text-sm text-muted-foreground"
            >
                Esta superficie termina en Definición de Implementación.
                No inicia ejecución ni expone la capa comercial.
            </div>
        </div>
    </AppLayout>
</template>
