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

type DataPreparationStatus = {
    stage: string;
    stage_label: string;
    batch: {
        batch_id: number;
        status: string;
        domain_count: number;
        source_row_count: number;
        staged_row_count: number;
        rejected_row_count: number;
        completed_at: string | null;
    };
    processing: {
        run_id: number;
        status: string;
        profiled_row_count: number;
        normalized_row_count: number;
        issue_count: number;
        blocking_issue_count: number;
        warning_issue_count: number;
        informational_issue_count: number;
        normalization_completed: boolean;
        completed_at: string | null;
    } | null;
    domain_summary: {
        total: number;
        normalized: number;
        profiled: number;
        with_blocking_issues: number;
        with_warnings: number;
        clean: number;
    };
    field_summary: {
        total: number;
        healthy: number;
        informational: number;
        warning: number;
        blocking: number;
        attention: number;
        required_incomplete: number;
        with_invalid_values: number;
    };
    domains: Array<{
        key: string;
        label: string;
        preparation_status: string;
        preparation_label: string;
        quality_status: string;
        quality_label: string;
        row_count: number;
        field_count: number;
        identity_count: number;
        duplicate_identity_count: number;
        issue_count: number;
        blocking_issue_count: number;
        warning_issue_count: number;
        informational_issue_count: number;
        normalized_row_count: number;
    }>;

};

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
    implementation_request: {
        id: number;
        status: string;
        status_label: string;
        detail_url: string;
    } | null;
    data_preparation: DataPreparationStatus | null;
    processing_history: {
        summary: {
            total_batches: number;
            total_runs: number;
            shown_batches: number;
            has_more: boolean;
        };
        entries: Array<Record<string, unknown>>;
    };
    usable_dataset: {
        available: boolean;
        reason: string;
        dataset: {
            processing_run_id: number;
            intake_batch_id: number;
            definition_version: number | null;
            schema_version: number;
            profiling_version: number;
            normalization_version: number;
            normalized_row_count: number;
            has_rows: boolean;
            completed_at: string | null;
        } | null;
    };
    definition: {
        id: number;
        version: number;
        status: string;
    } | null;
    current_stage: string;
    urls: {
        diagnosis: string;
        implementation_plan: string;
    };
};

const props = defineProps<{
    rows: Row[];
    stats: {
        total: number;
        active_requests: number;
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
        return 'Sin Definición aún';
    }

    return {
        draft: 'Definición · Borrador',
        under_review: 'Definición · En revisión',
        ready: 'Definición · Lista',
    }[row.definition.status]
        ?? row.definition.status;
}

function planStatusLabel(status: string): string {
    return {
        presented: 'Presentado',
        draft: 'Borrador',
        published: 'Publicado',
    }[status]
        ?? status;
}

const businessImprovementGroups = [
    {
        number: '01',
        title: 'Operaciones',
        purpose: 'Eficiencia y control de los procesos operativos.',
        improvements: [
            'Inventarios, existencias y rotación',
            'Compras, abastecimiento y suplidores',
            'Disponibilidad, productividad y costos operativos',
        ],
    },
    {
        number: '02',
        title: 'Gestión',
        purpose: 'Planificación, desempeño y toma de decisiones.',
        improvements: [
            'Clientes, segmentos y comportamiento comercial',
            'Ventas por producto, sucursal y vendedor',
            'Indicadores, tendencias, riesgos y oportunidades',
        ],
    },
    {
        number: '03',
        title: 'Finanzas',
        purpose: 'Visibilidad y control del desempeño financiero.',
        improvements: [
            'Ingresos, costos, márgenes y rentabilidad',
            'Cuentas por cobrar y exposición financiera',
            'Análisis histórico y planificación financiera',
        ],
    },
] as const;
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
                        LAUDA 360 · Administración
                    </p>

                    <h1
                        class="mt-1 text-2xl font-black tracking-tight md:text-3xl"
                    >
                        Datos e Inteligencia BI
                    </h1>

                    <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Gestiona solicitudes y supervisa el avance de las empresas con BI en su Plan 360.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link href="/admin/transformation-360">
                        Volver a Transformación 360
                    </Link>
                </Button>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Empresas con BI en Plan 360
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.total }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Solicitudes activas
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.active_requests }}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>
                            Definiciones listas
                        </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ props.stats.ready }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Empresas y solicitudes BI
                    </CardTitle>

                    <CardDescription>
                        Empresas cuyo Plan 360 incluye esta capacidad. Consulta sus estados y expedientes.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="props.rows.length === 0"
                        class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
                    >
                        No hay empresas con BI identificado en sus Planes 360.
                    </div>

                    <div
                        v-else
                        class="space-y-4"
                    >
                        <div
                            v-for="row in props.rows"
                            :key="row.assessment_id"
                            class="rounded-xl border p-4 transition-colors hover:bg-muted/20"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2 class="font-black">
                                            {{ row.company }}
                                        </h2>

                                        <Badge variant="outline">
                                            {{
                                                row.implementation_request
                                                    ?.status_label
                                                    ?? 'Sin solicitud'
                                            }}
                                        </Badge>

                                        <Badge
                                            v-if="row.implementation_request"
                                            variant="outline"
                                        >
                                            {{ definitionLabel(row) }}
                                        </Badge>
                                    </div>

                                    <p
                                        class="mt-1 break-words text-sm text-muted-foreground"
                                    >
                                        {{ row.contact.name }}
                                        ·
                                        {{ row.contact.email }}
                                    </p>

                                    <p
                                        v-if="row.plan"
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Plan 360 V{{ row.plan.version }}
                                        ·
                                        {{ planStatusLabel(row.plan.status) }}
                                    </p>

                                    <details v-if="row.implementation_request" class="group mt-3 rounded-lg border bg-muted/20 p-3">
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-xs font-medium">
                                            <span>Datos · {{ row.data_preparation?.stage_label ?? 'Sin procesamiento' }}</span>
                                            <span class="text-muted-foreground group-open:hidden">Ver detalles</span>
                                            <span class="hidden text-muted-foreground group-open:inline">Ocultar detalles</span>
                                        </summary>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <Badge
                                            :variant="
                                                row.data_preparation
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                        >
                                            Datos ·
                                            {{
                                                row.data_preparation
                                                    ?.stage_label
                                                ?? 'Sin procesamiento'
                                            }}
                                        </Badge>

                                        <!-- P13_USABLE_DATASET_STATUS -->
                                        <Badge
                                            :variant="
                                                row.usable_dataset.available
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                        >
                                            Dataset utilizable ·
                                            <template
                                                v-if="
                                                    row.usable_dataset.available
                                                    && row.usable_dataset.dataset
                                                "
                                            >
                                                {{
                                                    row.usable_dataset
                                                        .dataset
                                                        .normalized_row_count
                                                }}
                                                filas
                                            </template>
                                            <template v-else>
                                                No disponible
                                            </template>
                                        </Badge>

                                        <!-- P12_PROCESSING_HISTORY_SUMMARY -->
                                        <Badge
                                            v-if="
                                                row.processing_history
                                                    .summary
                                                    .total_batches > 0
                                            "
                                            variant="outline"
                                        >
                                            Historial ·
                                            {{
                                                row.processing_history
                                                    .summary
                                                    .total_batches
                                            }}
                                            batches ·
                                            {{
                                                row.processing_history
                                                    .summary
                                                    .total_runs
                                            }}
                                            runs
                                        </Badge>

                                        <span
                                            v-if="row.data_preparation"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Batch #{{
                                                row.data_preparation
                                                    .batch
                                                    .batch_id
                                            }}
                                            ·
                                            {{
                                                row.data_preparation
                                                    .batch
                                                    .staged_row_count
                                            }}
                                            staging
                                            <template
                                                v-if="
                                                    row.data_preparation
                                                        .processing
                                                "
                                            >
                                                ·
                                                {{
                                                    row.data_preparation
                                                        .processing
                                                        .normalized_row_count
                                                }}
                                                normalizadas
                                            </template>
                                        </span>

                                        <span
                                            v-if="
                                                row.data_preparation
                                                && row.data_preparation
                                                    .domain_summary
                                                    .total > 0
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                row.data_preparation
                                                    .domain_summary
                                                    .normalized
                                            }}/{{
                                                row.data_preparation
                                                    .domain_summary
                                                    .total
                                            }}
                                            dominios normalizados

                                            <template
                                                v-if="
                                                    row.data_preparation
                                                        .domain_summary
                                                        .with_blocking_issues > 0
                                                "
                                            >
                                                ·
                                                {{
                                                    row.data_preparation
                                                        .domain_summary
                                                        .with_blocking_issues
                                                }}
                                                con bloqueos
                                            </template>

                                            <template
                                                v-else-if="
                                                    row.data_preparation
                                                        .domain_summary
                                                        .with_warnings > 0
                                                "
                                            >
                                                ·
                                                {{
                                                    row.data_preparation
                                                        .domain_summary
                                                        .with_warnings
                                                }}
                                                con advertencias
                                            </template>
                                        </span>

                                        <span
                                            v-if="
                                                row.data_preparation
                                                && row.data_preparation
                                                    .field_summary
                                                    .total > 0
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                row.data_preparation
                                                    .field_summary
                                                    .total
                                            }}
                                            campos

                                            <template
                                                v-if="
                                                    row.data_preparation
                                                        .field_summary
                                                        .blocking > 0
                                                "
                                            >
                                                ·
                                                {{
                                                    row.data_preparation
                                                        .field_summary
                                                        .blocking
                                                }}
                                                requieren corrección
                                            </template>

                                            <template
                                                v-else-if="
                                                    row.data_preparation
                                                        .field_summary
                                                        .warning > 0
                                                "
                                            >
                                                ·
                                                {{
                                                    row.data_preparation
                                                        .field_summary
                                                        .warning
                                                }}
                                                con advertencias
                                            </template>
                                        </span>
                                        </div>
                                    </details>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:max-w-[350px] lg:justify-end">
                                    <Button v-if="row.implementation_request" as-child size="sm">
                                        <Link :href="row.implementation_request.detail_url">Ver solicitud BI</Link>
                                    </Button>
                                    <span v-else class="rounded-md border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                        Esperando solicitud del cliente
                                    </span>
                                    <Button as-child size="sm" variant="outline">
                                        <Link :href="row.urls.diagnosis">Ver Diagnóstico 360</Link>
                                    </Button>
                                    <Button as-child size="sm" variant="outline">
                                        <Link :href="row.urls.implementation_plan">Ver Plan 360</Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

                        <!-- UX02_BI_BUSINESS_GROUPS_V1 -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle>
                        Oportunidades de mejora mediante BI
                    </CardTitle>
                    <CardDescription>
                        Tres ámbitos empresariales para orientar
                        los objetivos del servicio.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="grid gap-3 lg:grid-cols-3">
                        <section
                            v-for="group in businessImprovementGroups"
                            :key="group.number"
                            class="rounded-xl border bg-muted/20 p-4"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border bg-background text-xs font-bold text-muted-foreground"
                                >
                                    {{ group.number }}
                                </span>

                                <div>
                                    <h3 class="font-semibold">
                                        {{ group.title }}
                                    </h3>
                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                        {{ group.purpose }}
                                    </p>
                                </div>
                            </div>

                            <ul class="mt-4 space-y-2 border-t pt-3 text-sm">
                                <li
                                    v-for="improvement in group.improvements"
                                    :key="improvement"
                                    class="flex gap-2 leading-5"
                                >
                                    <span
                                        aria-hidden="true"
                                        class="text-muted-foreground"
                                    >•</span>
                                    <span>{{ improvement }}</span>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <p class="text-xs leading-5 text-muted-foreground">
                        Estas mejoras son orientativas. Su pertinencia,
                        alcance y requisitos se determinarán durante
                        la definición funcional de cada empresa.
                    </p>

                    <details
                        v-if="props.capability.scope_items.length"
                        class="group rounded-lg border px-4 py-3"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-3"
                        >
                            <span>
                                <span class="block text-sm font-medium">
                                    Capacidades de datos de referencia
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ props.capability.scope_items.length }}
                                    capacidades del catálogo técnico
                                </span>
                            </span>

                            <span
                                class="text-xs text-muted-foreground group-open:hidden"
                            >
                                Ver detalle
                            </span>
                            <span
                                class="hidden text-xs text-muted-foreground group-open:inline"
                            >
                                Ocultar detalle
                            </span>
                        </summary>

                        <div class="mt-4 border-t pt-4">
                            <p
                                v-if="props.capability.purpose"
                                class="mb-4 text-sm text-muted-foreground"
                            >
                                {{ props.capability.purpose }}
                            </p>

                            <ul class="grid gap-2 text-sm md:grid-cols-2">
                                <li
                                    v-for="item in props.capability.scope_items"
                                    :key="item"
                                    class="rounded-lg border px-3 py-2"
                                >
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </details>
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
