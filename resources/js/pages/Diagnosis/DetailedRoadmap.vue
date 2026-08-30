<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2 } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

import DetailedRoadmapTransformationCapabilities from '@/components/diagnosis/DetailedRoadmapTransformationCapabilities.vue';
import DiagnosisDeliverableValidationCard from '@/components/diagnosis/DiagnosisDeliverableValidationCard.vue';

interface Initiative {
    id: string;
    dimension_label: string;
    source_score: number | null;
    priority: string;
    title: string;
    objective: string;
    actions: string[];
    owner_role: string;
    dependencies: string[];
    impact: string;
    effort: string;
    success_metrics: string[];
    phase: number;
    horizon: string;
    sequence: number;
}

const props = defineProps<{
    assessment: {
        id: number;
        organization_name: string;
        maturity_score: number | null;
        capacity_score: number | null;
        urgency_score: number | null;
    };
    roadmap: {
        id: number;
        version: number;
        content: {
            executive_direction?: Record<string, any>;
            transformation_capabilities?: Record<string, any>;
            phases?: Array<Record<string, any>>;
            initiatives?: Initiative[];
            governance?: Record<string, any>;
            scope_note?: Record<string, any>;
        };
        published_at: string | null;
    };
    expanded_report_url: string;
    implementation_plan_url: string | null;
    branding_activation: {
        recommended: boolean;
        available: boolean;
        activated: boolean;
        status: string | null;
        activated_at: string | null;
        endpoint: string | null;
    } | null;
    validation: {
        status: 'presented' | 'reviewed' | 'validated' | 'adjustment_requested';
        reviewed_at: string | null;
        validated_at: string | null;
        adjustment_requested_at: string | null;
        adjustment_note: string | null;
    };
    validation_endpoints: {
        review: string;
        validate: string;
        request_adjustment: string;
    };
    diagnosis_url: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Inicio',
        href: '/app',
    },
    {
        title: 'Diagnóstico 360',
        href: props.diagnosis_url,
    },
    {
        title: 'Informe Ampliado',
        href: props.expanded_report_url,
    },
    {
        title: 'Roadmap Detallado',
        href: `/diagnostico/${props.assessment.id}/roadmap-detallado`,
    },
];

const content = computed(() => props.roadmap.content ?? {});
const initiatives = computed(() => content.value.initiatives ?? []);
const phases = computed(() => content.value.phases ?? []);

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium' }).format(
        date,
    );
}
</script>

<template>
    <Head title="Roadmap Detallado LAUDA 360" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="min-h-full bg-muted/20 px-4 py-8 sm:px-6">
        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <Link
                    :href="expanded_report_url"
                    class="inline-flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Volver al Informe Ampliado
                </Link>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Badge variant="outline">LAUDA 360</Badge>
                    <Badge variant="secondary">
                        Roadmap Detallado · V{{ roadmap.version }}
                    </Badge>
                </div>

                <h1 class="mt-3 text-3xl font-black">Roadmap Detallado</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ assessment.organization_name }} · publicado
                    {{ formatDate(roadmap.published_at) }}
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Dirección ejecutiva</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="leading-7">
                        {{ content.executive_direction?.starting_point }}
                    </p>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ content.executive_direction?.objective }}
                    </p>
                </CardContent>
            </Card>

            <DetailedRoadmapTransformationCapabilities
                :capabilities="content.transformation_capabilities ?? null"
                :branding-activation="branding_activation"
            />

            <Card>
                <CardHeader>
                    <CardTitle>4 fases de transformación</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div
                        v-for="phase in phases"
                        :key="phase.number"
                        class="rounded-2xl border p-4"
                    >
                        <p class="font-bold">{{ phase.title }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ phase.horizon }}
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ phase.initiative_ids?.length ?? 0 }} iniciativas
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Iniciativas priorizadas</CardTitle>
                    <CardDescription>
                        Qué hacer, en qué orden, quién debe liderar y cómo
                        medirlo.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div
                        v-for="item in initiatives"
                        :key="item.id"
                        class="rounded-2xl border p-5"
                    >
                        <div class="flex flex-wrap gap-2">
                            <Badge variant="outline"
                                >#{{ item.sequence }}</Badge
                            >
                            <Badge variant="secondary">{{
                                item.priority
                            }}</Badge>
                            <Badge variant="outline"
                                >Fase {{ item.phase }}</Badge
                            >
                        </div>

                        <h2 class="mt-3 text-lg font-black">
                            {{ item.id }} · {{ item.title }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ item.dimension_label }} · {{ item.horizon }}
                        </p>
                        <p class="mt-4 leading-7">{{ item.objective }}</p>

                        <div class="mt-5 grid gap-5 lg:grid-cols-3">
                            <div>
                                <p
                                    class="text-xs font-black text-muted-foreground uppercase"
                                >
                                    Responsable
                                </p>
                                <p class="mt-2 text-sm">
                                    {{ item.owner_role }}
                                </p>
                            </div>

                            <div>
                                <p
                                    class="text-xs font-black text-muted-foreground uppercase"
                                >
                                    Dependencias
                                </p>
                                <p class="mt-2 text-sm">
                                    {{
                                        item.dependencies?.length
                                            ? item.dependencies.join(', ')
                                            : 'Sin dependencia previa'
                                    }}
                                </p>
                            </div>

                            <div>
                                <p
                                    class="text-xs font-black text-muted-foreground uppercase"
                                >
                                    Impacto / esfuerzo
                                </p>
                                <p class="mt-2 text-sm">
                                    {{ item.impact }} / {{ item.effort }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-5 lg:grid-cols-2">
                            <div>
                                <p class="font-bold">Acciones</p>
                                <ul class="mt-3 space-y-2">
                                    <li
                                        v-for="(action, index) in item.actions"
                                        :key="index"
                                        class="flex gap-2 text-sm leading-6"
                                    >
                                        <CheckCircle2
                                            class="mt-1 size-4 shrink-0 text-primary"
                                        />
                                        <span>{{ action }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <p class="font-bold">Indicadores de éxito</p>
                                <ul class="mt-3 space-y-2">
                                    <li
                                        v-for="(
                                            metric, index
                                        ) in item.success_metrics"
                                        :key="index"
                                        class="text-sm leading-6 text-muted-foreground"
                                    >
                                        • {{ metric }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Gobierno del Roadmap</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border p-4">
                        <p class="font-bold">Semanal</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ content.governance?.weekly }}
                        </p>
                    </div>
                    <div class="rounded-2xl border p-4">
                        <p class="font-bold">Mensual</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ content.governance?.monthly }}
                        </p>
                    </div>
                    <div class="rounded-2xl border p-4">
                        <p class="font-bold">Trimestral</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ content.governance?.quarterly }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Alcance del entregable</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="leading-7 text-muted-foreground">
                        {{ content.scope_note?.body }}
                    </p>
                </CardContent>
            </Card>

            <DiagnosisDeliverableValidationCard
                :validation="validation"
                :endpoints="validation_endpoints"
            />

            <div class="flex flex-wrap justify-center gap-3">
                <Button as-child variant="outline">
                    <Link :href="expanded_report_url">
                        <ArrowLeft class="mr-2 size-4" />
                        Volver al Informe Ampliado
                    </Link>
                </Button>
                <Button v-if="implementation_plan_url" as-child>
                    <Link :href="implementation_plan_url">
                        Ver Plan de Implementación
                    </Link>
                </Button>
                <Button as-child variant="ghost">
                    <Link :href="diagnosis_url">Volver al Diagnóstico</Link>
                </Button>
            </div>
        </div>
    </main>
    </AppLayout>
</template>
