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

type Row = {
    assessment_id: number;
    company: string;
    contact: {
        name: string;
        email: string;
    };
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
    current_stage: string;
    urls: {
        diagnosis: string;
        implementation_plan: string;
        definition: string | null;
    };
};

const props = defineProps<{
    rows: Row[];
    stats: {
        total: number;
        with_definition: number;
        ready: number;
    };
    capability: {
        key: string;
        title: string;
        purpose: string | null;
        scope_items: string[];
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
    {
        title: 'Datos e Inteligencia BI',
        href: '/admin/transformation-360/data-bi',
    },
];

function definitionLabel(row: Row): string {
    if (!row.definition) {
        return 'Sin Definición';
    }

    return {
        draft: 'Definición · Borrador',
        under_review: 'Definición · En revisión',
        ready: 'Definición · Lista',
    }[row.definition.status]
        ?? row.definition.status;
}
</script>

<template>
    <Head title="Datos e Inteligencia BI · Supervisor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto w-full max-w-[1500px] space-y-6 p-4 md:p-6"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <p
                        class="text-[10px] font-black tracking-[0.18em] text-[#F53003] uppercase"
                    >
                        LAUDA 360 · Capacidad profesional
                    </p>

                    <h1
                        class="mt-1 text-2xl font-black tracking-tight md:text-3xl"
                    >
                        Datos e Inteligencia BI
                    </h1>

                    <p
                        class="mt-2 max-w-4xl text-sm font-semibold"
                    >
                        {{ props.capability.title }}
                    </p>

                    <p
                        v-if="props.capability.purpose"
                        class="mt-2 max-w-4xl text-sm leading-6 text-muted-foreground"
                    >
                        {{ props.capability.purpose }}
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link href="/admin/transformation-360">
                        Transformación 360
                    </Link>
                </Button>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Empresas con BI
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.total }}
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
                            {{ props.stats.ready }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card
                v-if="props.capability.scope_items.length"
            >
                <CardHeader>
                    <CardTitle>
                        Alcance funcional
                    </CardTitle>

                    <CardDescription>
                        Alcance definido en el catálogo profesional
                        de LAUDA 360.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <ul
                        class="grid gap-2 text-sm md:grid-cols-2"
                    >
                        <li
                            v-for="item in props.capability.scope_items"
                            :key="item"
                            class="rounded-lg border px-3 py-2"
                        >
                            {{ item }}
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Empresas con Datos BI en su Plan
                    </CardTitle>

                    <CardDescription>
                        Solo aparecen Planes que contienen la capacidad
                        data_transformation_bi.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="props.rows.length === 0"
                        class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No hay Planes con Datos BI actualmente.
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
                                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2 class="font-black">
                                            {{ row.company }}
                                        </h2>

                                        <Badge variant="secondary">
                                            {{ row.current_stage }}
                                        </Badge>

                                        <Badge variant="outline">
                                            {{ definitionLabel(row) }}
                                        </Badge>
                                    </div>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ row.contact.name }}
                                        ·
                                        {{ row.contact.email }}
                                    </p>

                                    <p
                                        v-if="row.plan"
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Plan V{{ row.plan.version }}
                                        ·
                                        {{ row.plan.status }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
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
                Vista administrativa de supervisión funcional.
                No contiene precios, facturación, pagos,
                suscripciones ni acciones de ejecución.
            </div>
        </div>
    </AppLayout>
</template>
