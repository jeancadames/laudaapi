<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, FileText } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

import DetailedRoadmapCommercialCard from '@/components/diagnosis/DetailedRoadmapCommercialCard.vue';

const props = defineProps<{
    assessment: {
        id: number;
        organization_name: string;
        maturity_score: number | null;
        capacity_score: number | null;
        urgency_score: number | null;
    };
    report: {
        id: number;
        version: number;
        sections: Record<string, any>;
        published_at: string | null;
    };
    detailed_roadmap_commercial: Record<string, any> | null;
    detailed_roadmap_commercial_preview: Record<string, any> | null;
    detailed_roadmap_request_url: string;
    detailed_roadmap: {
        id: number;
        version: number;
        published_at: string | null;
    } | null;
    diagnosis_url: string;
}>();

const sections = computed(() => props.report.sections ?? {});

function formatDate(value: string | null) {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
    }).format(date);
}
</script>

<template>
    <Head title="Informe Ampliado LAUDA 360" />

    <main class="min-h-screen bg-muted/20 px-4 py-8 sm:px-6">
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <Link
                    :href="diagnosis_url"
                    class="inline-flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Volver al diagnóstico
                </Link>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <Badge variant="outline"> LAUDA 360 </Badge>
                    <Badge variant="secondary">
                        Informe Ampliado · V{{ report.version }}
                    </Badge>
                </div>

                <h1 class="mt-3 text-3xl font-black">Informe Ampliado</h1>

                <p class="mt-2 text-sm text-muted-foreground">
                    {{ assessment.organization_name }}
                    · publicado
                    {{ formatDate(report.published_at) }}
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        {{
                            sections.executive_summary?.title ||
                            'Conclusión ejecutiva'
                        }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="leading-7">
                        {{ sections.executive_summary?.body }}
                    </p>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardDescription> Madurez </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ assessment.maturity_score ?? '—' }}/100
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardDescription> Capacidad </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ assessment.capacity_score ?? '—' }}/100
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardDescription> Urgencia </CardDescription>
                        <CardTitle class="text-3xl">
                            {{ assessment.urgency_score ?? '—' }}/100
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Contexto del negocio</CardTitle>
                    <CardDescription>
                        {{ sections.business_context?.activity_type || '—' }}
                        ·
                        {{ sections.business_context?.sector || '—' }}
                        ·
                        {{ sections.business_context?.customer_market || '—' }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        {{ sections.business_context?.description || '—' }}
                    </p>
                    <p class="leading-7">
                        {{ sections.business_context?.interpretation }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Análisis por dimensión</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="item in sections.dimension_analysis?.items ?? []"
                        :key="item.key"
                        class="rounded-2xl border p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-bold">
                                {{ item.label }}
                            </p>
                            <Badge variant="outline">
                                {{ item.score }}/100 ·
                                {{ item.band }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ item.interpretation }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Brechas críticas</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="item in sections.critical_gaps?.items ?? []"
                            :key="item.key"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="font-bold">
                                    {{ item.label }}
                                </p>
                                <Badge variant="outline">
                                    {{ item.score }}/100
                                </Badge>
                            </div>
                            <p
                                class="mt-1 text-sm leading-6 text-muted-foreground"
                            >
                                {{ item.impact }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle> Fortalezas relativas </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="item in sections.relative_strengths?.items ??
                            []"
                            :key="item.key"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="font-bold">
                                    {{ item.label }}
                                </p>
                                <Badge variant="outline">
                                    {{ item.score }}/100
                                </Badge>
                            </div>
                            <p
                                class="mt-1 text-sm leading-6 text-muted-foreground"
                            >
                                {{ item.note }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle> Implicaciones para el negocio </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-3">
                        <li
                            v-for="(item, index) in sections
                                .business_implications?.items ?? []"
                            :key="index"
                            class="flex gap-3 text-sm leading-6"
                        >
                            <CheckCircle2
                                class="mt-1 size-4 shrink-0 text-primary"
                            />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Focos recomendados</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="space-y-3">
                        <li
                            v-for="(item, index) in sections.recommended_focus
                                ?.items ?? []"
                            :key="index"
                            class="rounded-2xl border p-4"
                        >
                            <p class="font-bold">Prioridad {{ index + 1 }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ item }}
                            </p>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle> Modalidad y capacidad de ejecución </CardTitle>
                    <CardDescription>
                        {{
                            sections.modality_and_capacity
                                ?.recommended_modality_label || '—'
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="leading-7">
                        {{ sections.modality_and_capacity?.body }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle> Del Informe Ampliado al Roadmap </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="leading-7">
                        {{ sections.next_step_note?.body }}
                    </p>

                    <div
                        class="flex gap-3 rounded-2xl border bg-muted/30 p-4 text-sm text-muted-foreground"
                    >
                        <FileText class="mt-0.5 size-5 shrink-0" />
                        <p>
                            El Roadmap Detallado es un entregable posterior y
                            separado. Ningún servicio adicional se activa
                            automáticamente.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <DetailedRoadmapCommercialCard
                :commercial="detailed_roadmap_commercial"
                :preview="detailed_roadmap_commercial_preview"
                :roadmap="detailed_roadmap"
                :request-url="detailed_roadmap_request_url"
                :roadmap-url="`/diagnostico/${assessment.id}/roadmap-detallado`"
            />

            <div class="flex justify-center">
                <Button as-child variant="outline">
                    <Link :href="diagnosis_url">
                        <ArrowLeft class="mr-2 size-4" />
                        Volver al Diagnóstico LAUDA 360
                    </Link>
                </Button>
            </div>
        </div>
    </main>
</template>
