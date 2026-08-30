<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Clock3, FileText } from 'lucide-vue-next';

import DiagnosisDeliverableValidationCard from '@/components/diagnosis/DiagnosisDeliverableValidationCard.vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Initiative = {
    id: string | number | null;
    priority: string | null;
    title: string | null;
    objective: string | null;
    actions: string[];
    owner_role: string | null;
    dependencies: string[];
    success_metrics: string[];
};

type Capability = {
    id: number;
    capability_label: string;
    summary: string | null;
    kind: 'professional_service';
    includes: string[];
};

type Phase = {
    id: number;
    sequence: number;
    name: string;
    objective: string | null;
    horizon: string | null;
    initiatives: Initiative[];
    dependencies: string[];
    deliverables: string[];
    capabilities: Capability[];
};

const props = defineProps<{
    assessment: {
        id: number;
        organization_name: string;
    };
    plan: {
        id: number;
        version: number;
        status: string;
        presented_at: string | null;
        phases: Phase[];
    };
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
    roadmap_url: string | null;
    diagnosis_url: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Diagnóstico 360', href: props.diagnosis_url },
    ...(props.roadmap_url
        ? [{ title: 'Roadmap Detallado', href: props.roadmap_url }]
        : []),
    {
        title: 'Plan de Implementación',
        href: `/diagnostico/${props.assessment.id}/plan-implementacion`,
    },
];

function statusLabel(status: string): string {
    return ({
        presented: 'Presentado',
        accepted: 'Presentado',
        active: 'Presentado',
        completed: 'Completado',
    } as Record<string, string>)[status] ?? status;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Plan de Implementación" />

        <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-black tracking-widest text-primary uppercase">Transformación Digital 360</p>
                    <h1 class="mt-1 text-2xl font-black">Plan de Implementación</h1>
                    <p class="mt-2 text-sm text-muted-foreground">{{ assessment.organization_name }}</p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="roadmap_url || diagnosis_url">
                        <ArrowLeft class="mr-2 size-4" />Volver
                    </Link>
                </Button>
            </div>

            <Card class="border-primary/20 bg-primary/5">
                <CardContent class="space-y-3 p-5 text-sm leading-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">V{{ plan.version }}</Badge>
                        <Badge variant="outline">{{ statusLabel(plan.status) }}</Badge>
                    </div>
                    <p>
                        Este documento es consultivo y gratuito. Organiza las prioridades de transformación en fases, actividades, responsables, dependencias, entregables y horizontes sugeridos.
                    </p>
                    <p class="text-muted-foreground">
                        No selecciona modalidad, no contiene precios ni hitos de facturación y no constituye contratación de servicios. La validación del documento confirmará su revisión, no una aceptación comercial.
                    </p>
                </CardContent>
            </Card>

            <Card v-for="phase in plan.phases" :key="phase.id">
                <CardHeader>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-black tracking-widest text-primary uppercase">Fase {{ phase.sequence }}</p>
                            <CardTitle class="mt-1">{{ phase.name }}</CardTitle>
                            <CardDescription v-if="phase.objective" class="mt-2">{{ phase.objective }}</CardDescription>
                        </div>
                        <Badge v-if="phase.horizon" variant="outline">
                            <Clock3 class="mr-1 size-3" />{{ phase.horizon }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-5">
                    <section v-if="phase.initiatives.length" class="space-y-3">
                        <h3 class="text-sm font-black">Iniciativas, actividades y responsables</h3>
                        <article v-for="initiative in phase.initiatives" :key="String(initiative.id ?? initiative.title)" class="rounded-xl border p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold">{{ initiative.title || 'Iniciativa' }}</p>
                                <Badge v-if="initiative.priority" variant="outline">{{ initiative.priority }}</Badge>
                            </div>
                            <p v-if="initiative.objective" class="mt-2 text-sm text-muted-foreground">{{ initiative.objective }}</p>
                            <ul v-if="initiative.actions.length" class="mt-3 list-disc space-y-1 pl-5 text-sm">
                                <li v-for="action in initiative.actions" :key="action">{{ action }}</li>
                            </ul>
                            <p v-if="initiative.owner_role" class="mt-3 text-xs text-muted-foreground">
                                Responsable sugerido: <strong class="text-foreground">{{ initiative.owner_role }}</strong>
                            </p>
                            <div v-if="initiative.success_metrics.length" class="mt-3">
                                <p class="text-xs font-bold">Indicadores de éxito</p>
                                <ul class="mt-1 list-disc space-y-1 pl-5 text-xs text-muted-foreground">
                                    <li v-for="metric in initiative.success_metrics" :key="metric">{{ metric }}</li>
                                </ul>
                            </div>
                        </article>
                    </section>

                    <div class="grid gap-4 md:grid-cols-2">
                        <section class="rounded-xl bg-muted/30 p-4">
                            <h3 class="text-sm font-black">Dependencias</h3>
                            <ul v-if="phase.dependencies.length" class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                <li v-for="dependency in phase.dependencies" :key="dependency">{{ dependency }}</li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-muted-foreground">Sin dependencias adicionales registradas.</p>
                        </section>
                        <section class="rounded-xl bg-muted/30 p-4">
                            <h3 class="text-sm font-black">Entregables</h3>
                            <ul v-if="phase.deliverables.length" class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                <li v-for="deliverable in phase.deliverables" :key="deliverable">{{ deliverable }}</li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-muted-foreground">Se concretan a partir de las iniciativas de esta fase.</p>
                        </section>
                    </div>

                    <section>
                        <h3 class="text-sm font-black">Apoyo profesional sugerido</h3>
                        <div v-if="phase.capabilities.length" class="mt-2 grid gap-3 md:grid-cols-2">
                            <div v-for="capability in phase.capabilities" :key="capability.id" class="rounded-xl border p-4">
                                <div class="flex items-center gap-2">
                                    <FileText class="size-4" />
                                    <p class="font-bold">{{ capability.capability_label }}</p>
                                </div>
                                <p v-if="capability.summary" class="mt-2 text-sm text-muted-foreground">{{ capability.summary }}</p>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-sm text-muted-foreground">
                            Esta fase no requiere un servicio profesional adicional.
                        </p>
                    </section>
                </CardContent>
            </Card>

            <DiagnosisDeliverableValidationCard
                :validation="validation"
                :endpoints="validation_endpoints"
            />

            <Card>
                <CardContent class="flex items-start gap-3 p-5 text-sm text-muted-foreground">
                    <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-primary" />
                    <p>
                        Revisa este Plan junto con el Informe Ampliado y el Roadmap Detallado. La contratación de apoyo para ejecutar estas iniciativas se definirá, si aplica, en un proceso comercial separado.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
