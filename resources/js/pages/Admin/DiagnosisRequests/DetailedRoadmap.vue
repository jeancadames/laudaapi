<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    FileText,
    RefreshCcw,
    Send,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

import DetailedRoadmapAdminCommercialCard from '@/components/diagnosis/DetailedRoadmapAdminCommercialCard.vue';

import DetailedRoadmapTransformationCapabilities from '@/components/diagnosis/DetailedRoadmapTransformationCapabilities.vue';

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

interface Roadmap {
    id: number;
    version: number;
    status: 'draft' | 'under_review' | 'published';
    roadmap: {
        executive_direction?: Record<string, any>;
        transformation_capabilities?: Record<string, any>;
        phases?: Array<Record<string, any>>;
        initiatives?: Initiative[];
        governance?: Record<string, any>;
        scope_note?: Record<string, any>;
    };
    review_notes: string | null;
    published_at: string | null;
    endpoints: {
        save_review: string;
        review: string;
        regenerate: string;
        publish: string;
    };
}

const props = defineProps<{
    contact: {
        id: number;
        name: string | null;
        company: string | null;
        email: string | null;
    };
    assessment: {
        id: number;
        organization_name: string;
        maturity_score: number | null;
        capacity_score: number | null;
        urgency_score: number | null;
    };
    source_report: {
        id: number;
        version: number;
        published_at: string | null;
    } | null;
    roadmap: Roadmap | null;
    can_generate: boolean;
    commercial: Record<string, any> | null;
    generation_readiness: Record<string, any>;
    transformation_progress: Record<string, any> | null;
    endpoints: {
        back: string;
        generate: string;
        prepare_invoice: string | null;
        record_payment: string | null;
    };
}>();

const content = computed(() => props.roadmap?.roadmap ?? {});
const initiatives = computed(() => content.value.initiatives ?? []);
const phases = computed(() => content.value.phases ?? []);

const reviewForm = useForm({
    review_notes: props.roadmap?.review_notes ?? '',
});

const canEdit = computed(
    () =>
        props.roadmap !== null &&
        ['draft', 'under_review'].includes(props.roadmap.status),
);

const publicationReady = computed(
    () => props.generation_readiness?.publication_ready === true,
);

function generate() {
    router.post(props.endpoints.generate, {}, { preserveScroll: true });
}

function saveNotes() {
    if (!props.roadmap || !canEdit.value) return;
    reviewForm.patch(props.roadmap.endpoints.save_review, {
        preserveScroll: true,
    });
}

function markReview() {
    if (!props.roadmap || props.roadmap.status !== 'draft') return;
    if (!window.confirm('¿Marcar este Roadmap En revisión?')) return;
    router.post(props.roadmap.endpoints.review, {}, { preserveScroll: true });
}

function regenerate() {
    if (!props.roadmap || props.roadmap.status !== 'draft') return;
    if (
        !window.confirm(
            '¿Regenerar desde el último Informe Ampliado publicado?',
        )
    )
        return;
    router.post(
        props.roadmap.endpoints.regenerate,
        {},
        { preserveScroll: true },
    );
}

function publish() {
    if (!props.roadmap || !canEdit.value || !publicationReady.value) return;
    if (!window.confirm('¿Publicar esta versión del Roadmap para el cliente?'))
        return;
    router.post(props.roadmap.endpoints.publish, {}, { preserveScroll: true });
}

function statusLabel(status: Roadmap['status']) {
    return {
        draft: 'Borrador',
        under_review: 'En revisión',
        published: 'Publicado',
    }[status];
}
</script>

<template>
    <Head title="Roadmap Detallado LAUDA 360" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Diagnósticos 360', href: '/admin/diagnosis-requests' },
            { title: assessment.organization_name, href: endpoints.back },
            { title: 'Roadmap Detallado' },
        ]"
    >
        <div class="space-y-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="outline">LAUDA 360</Badge>
                        <Badge v-if="roadmap" variant="secondary">
                            V{{ roadmap.version }} ·
                            {{ statusLabel(roadmap.status) }}
                        </Badge>
                    </div>
                    <h1 class="mt-3 text-2xl font-black">Roadmap Detallado</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ assessment.organization_name }}
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="endpoints.back">
                        <ArrowLeft class="mr-2 size-4" />
                        Volver al Informe
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Estado del Roadmap</CardTitle>
                    <CardDescription>
                        Requisitos para generar y publicar para el cliente.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div
                            v-for="(
                                ok, key
                            ) in generation_readiness.prerequisites"
                            :key="key"
                            class="rounded-xl border p-3"
                        >
                            <p
                                class="text-xs font-bold text-muted-foreground uppercase"
                            >
                                {{
                                    {
                                        diagnosis_published:
                                            'Diagnóstico publicado',
                                        expanded_report_published:
                                            'Informe publicado',
                                        roadmap_requested: 'Roadmap solicitado',
                                        roadmap_invoiced: 'Factura Roadmap',
                                        roadmap_paid: 'Pago Roadmap',
                                    }[key] || key
                                }}
                            </p>
                            <p
                                class="mt-1 font-bold"
                                :class="
                                    ok
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ ok ? '✓ Cumplido' : 'Pendiente' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="generation_readiness.existing_roadmap"
                        class="rounded-xl border bg-muted/30 p-4"
                    >
                        <p class="font-bold">
                            Roadmap V{{
                                generation_readiness.existing_roadmap.version
                            }}
                            ya generado
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Estado:
                            {{
                                generation_readiness.existing_roadmap
                                    .status_label
                            }}. No es necesario generar nuevamente esta versión.
                        </p>
                    </div>

                    <div
                        v-if="generation_readiness.publication_blockers?.length"
                        class="rounded-xl border p-4"
                    >
                        <p class="font-bold">
                            Publicar para cliente todavía no está disponible
                        </p>
                        <ul
                            class="mt-2 space-y-1 text-sm text-muted-foreground"
                        >
                            <li
                                v-for="item in generation_readiness.publication_blockers"
                                :key="item"
                            >
                                • {{ item }}
                            </li>
                        </ul>
                    </div>

                    <div
                        v-else-if="generation_readiness.publication_ready"
                        class="rounded-xl border bg-muted/30 p-4"
                    >
                        <p class="font-bold">✓ Listo para publicar</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Los requisitos comerciales y operativos están
                            completos.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <DetailedRoadmapAdminCommercialCard
                :commercial="commercial"
                :prepare-invoice-url="endpoints.prepare_invoice"
                :record-payment-url="endpoints.record_payment"
            />

            <Card v-if="!roadmap">
                <CardHeader>
                    <CardTitle>Preparar Roadmap Detallado</CardTitle>
                    <CardDescription>
                        Requiere un Informe Ampliado publicado.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p
                        v-if="source_report"
                        class="text-sm text-muted-foreground"
                    >
                        Informe fuente: V{{ source_report.version }}
                    </p>
                    <Button :disabled="!can_generate" @click="generate">
                        <FileText class="mr-2 size-4" />
                        Generar Roadmap Detallado
                    </Button>
                </CardContent>
            </Card>

            <template v-else>
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
                                {{ phase.initiative_ids?.length ?? 0 }}
                                iniciativas
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Iniciativas priorizadas</CardTitle>
                        <CardDescription>
                            Responsable, dependencias, impacto, esfuerzo y KPIs.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="item in initiatives"
                            :key="item.id"
                            class="rounded-2xl border p-4"
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

                            <p class="mt-3 font-black">
                                {{ item.id }} · {{ item.title }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ item.dimension_label }} · {{ item.horizon }}
                            </p>
                            <p class="mt-3 leading-7">{{ item.objective }}</p>

                            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                                <div>
                                    <p
                                        class="text-xs font-bold text-muted-foreground uppercase"
                                    >
                                        Responsable
                                    </p>
                                    <p class="mt-1 text-sm">
                                        {{ item.owner_role }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-muted-foreground uppercase"
                                    >
                                        Dependencias
                                    </p>
                                    <p class="mt-1 text-sm">
                                        {{
                                            item.dependencies?.length
                                                ? item.dependencies.join(', ')
                                                : 'Sin dependencia previa'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-muted-foreground uppercase"
                                    >
                                        Impacto / esfuerzo
                                    </p>
                                    <p class="mt-1 text-sm">
                                        {{ item.impact }} / {{ item.effort }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <div>
                                    <p class="font-bold">Acciones</p>
                                    <ul class="mt-2 space-y-2">
                                        <li
                                            v-for="(
                                                action, index
                                            ) in item.actions"
                                            :key="index"
                                            class="flex gap-2 text-sm"
                                        >
                                            <CheckCircle2
                                                class="mt-0.5 size-4 shrink-0 text-primary"
                                            />
                                            <span>{{ action }}</span>
                                        </li>
                                    </ul>
                                </div>
                                <div>
                                    <p class="font-bold">KPIs</p>
                                    <ul class="mt-2 space-y-2">
                                        <li
                                            v-for="(
                                                metric, index
                                            ) in item.success_metrics"
                                            :key="index"
                                            class="text-sm text-muted-foreground"
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
                        <CardTitle>Notas internas</CardTitle>
                        <CardDescription
                            >No son visibles para el cliente.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Textarea
                            v-model="reviewForm.review_notes"
                            :disabled="!canEdit"
                            rows="5"
                        />
                        <Button
                            variant="outline"
                            :disabled="!canEdit || reviewForm.processing"
                            @click="saveNotes"
                        >
                            Guardar notas
                        </Button>
                    </CardContent>
                </Card>

                <div class="flex flex-wrap gap-3 rounded-2xl border p-4">
                    <Button
                        v-if="roadmap.status === 'draft'"
                        variant="outline"
                        @click="regenerate"
                    >
                        <RefreshCcw class="mr-2 size-4" />
                        Regenerar borrador
                    </Button>

                    <Button
                        v-if="roadmap.status === 'draft'"
                        variant="outline"
                        @click="markReview"
                    >
                        Marcar En revisión
                    </Button>

                    <Button
                        v-if="canEdit"
                        :disabled="commercial?.paid_access !== true"
                        @click="publish"
                    >
                        <Send class="mr-2 size-4" />
                        Publicar para cliente
                    </Button>

                    <Button
                        v-if="roadmap.status === 'published'"
                        variant="outline"
                        @click="generate"
                    >
                        Crear nueva versión
                    </Button>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
