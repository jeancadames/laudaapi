<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    Clock3,
    FileText,
    Lock,
    Map,
} from 'lucide-vue-next';
import { computed } from 'vue';

type ProgressStep = {
    code?: string;
    status?: string;
    completed?: boolean;
};

const props = withDefaults(
    defineProps<{
        mode: 'client' | 'admin';
        assessmentId?: number | null;
        contactId?: number | null;
        progress?: {
            steps?: ProgressStep[];
        } | null;
        implementationPlanUrl?: string | null;
    }>(),
    {
        assessmentId: null,
        contactId: null,
        progress: null,
        implementationPlanUrl: null,
    },
);

const isCompleted = (code: string): boolean =>
    Boolean(
        props.progress?.steps?.some(
            (step) =>
                step.code === code &&
                (step.status === 'completed' || step.completed === true),
        ),
    );

const diagnosisSubmitted = computed(() => isCompleted('diagnosis_submitted'));
const diagnosisPublished = computed(() => isCompleted('diagnosis_published'));
const expandedReportAvailable = computed(() =>
    isCompleted('expanded_report_published'),
);
const expandedReportReviewed = computed(() =>
    isCompleted('expanded_report_reviewed'),
);
const expandedReportValidated = computed(() =>
    isCompleted('expanded_report_validated'),
);
const roadmapAvailable = computed(() => isCompleted('roadmap_published'));
const roadmapReviewed = computed(() => isCompleted('roadmap_reviewed'));
const roadmapValidated = computed(() => isCompleted('roadmap_validated'));

const clientDiagnosisUrl = computed(() =>
    props.assessmentId ? `/diagnostico/${props.assessmentId}` : null,
);

const clientExpandedReportUrl = computed(() =>
    props.assessmentId
        ? `/diagnostico/${props.assessmentId}/informe-ampliado`
        : null,
);

const clientRoadmapUrl = computed(() =>
    props.assessmentId
        ? `/diagnostico/${props.assessmentId}/roadmap-detallado`
        : null,
);

const adminDiagnosisUrl = computed(() =>
    props.contactId ? `/admin/diagnosis-requests/${props.contactId}` : null,
);

const adminExpandedReportUrl = computed(() =>
    props.contactId
        ? `/admin/diagnosis-requests/${props.contactId}/expanded-report`
        : null,
);

const adminRoadmapUrl = computed(() =>
    props.contactId
        ? `/admin/diagnosis-requests/${props.contactId}/detailed-roadmap`
        : null,
);

const adminPlanUrl = computed(() =>
    props.contactId
        ? `/admin/diagnosis-requests/${props.contactId}/implementation-plan`
        : null,
);

const diagnosisStatus = computed(() => {
    if (diagnosisPublished.value) return 'Publicado';
    if (diagnosisSubmitted.value) return 'En revisión';

    return 'Pendiente';
});

const expandedStatus = computed(() => {
    if (expandedReportValidated.value) return 'Validado';
    if (expandedReportReviewed.value) return 'Revisado';
    if (expandedReportAvailable.value) return 'Presentado';
    if (diagnosisPublished.value) return 'Generando';

    return 'Pendiente';
});

const roadmapStatus = computed(() => {
    if (roadmapValidated.value) return 'Validado';
    if (roadmapReviewed.value) return 'Revisado';
    if (roadmapAvailable.value) return 'Presentado';
    if (diagnosisPublished.value) return 'Generando';

    return 'Pendiente';
});

function statusClass(status: string): string {
    if (status === 'Validado') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200';
    }

    if (status === 'Presentado') {
        return 'border-primary/20 bg-primary/5 text-primary';
    }

    if (status === 'En revisión' || status === 'Revisado' || status === 'Generando') {
        return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200';
    }

    return 'border-border bg-muted/40 text-muted-foreground';
}

const enabledClass =
    'inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-foreground px-4 py-2.5 text-sm font-bold text-background transition hover:opacity-90';

const secondaryEnabledClass =
    'inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border bg-background px-4 py-2.5 text-sm font-bold transition hover:bg-muted';

const disabledClass =
    'inline-flex min-h-10 w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-dashed bg-muted/20 px-4 py-2.5 text-center text-sm font-semibold text-muted-foreground opacity-70';
</script>

<template>
    <section class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
        <div class="mb-5">
            <p
                class="text-[10px] font-black tracking-[0.16em] text-primary uppercase"
            >
                Centro de control
            </p>

            <h2 class="mt-1 text-lg font-black">Documentos y resultados</h2>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                Consulta el avance del Diagnóstico 360 y de sus entregables
                gratuitos desde un mismo lugar.
            </p>
        </div>

        <div
            :class="
                mode === 'admin' ? 'grid gap-5' : 'grid gap-4 md:grid-cols-3'
            "
        >
            <article
                id="informe-diagnostico"
                class="flex min-h-[250px] flex-col rounded-2xl border bg-background p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <FileText class="size-5" />
                    </div>

                    <span
                        class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                        :class="statusClass(diagnosisStatus)"
                    >
                        {{ diagnosisStatus }}
                    </span>
                </div>

                <div class="mt-5 flex-1">
                    <h3 class="font-black">Informe del Diagnóstico</h3>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Resultado oficial del análisis inicial y punto de
                        partida para los entregables posteriores.
                    </p>
                </div>

                <div class="mt-5">
                    <template v-if="mode === 'client'">
                        <Link
                            v-if="diagnosisPublished && clientDiagnosisUrl"
                            :href="clientDiagnosisUrl"
                            :class="enabledClass"
                        >
                            Ver Diagnóstico
                            <ArrowRight class="size-4" />
                        </Link>

                        <span v-else :class="disabledClass">
                            <Clock3 class="size-4" />
                            Diagnóstico en revisión
                        </span>
                    </template>

                    <template v-else>
                        <Link
                            v-if="diagnosisSubmitted && adminDiagnosisUrl"
                            :href="adminDiagnosisUrl"
                            :class="secondaryEnabledClass"
                        >
                            Gestionar Diagnóstico
                            <ArrowRight class="size-4" />
                        </Link>

                        <span v-else :class="disabledClass">
                            <Lock class="size-4" />
                            Esperando envío del diagnóstico
                        </span>
                    </template>
                </div>
            </article>

            <article
                id="informe-ampliado"
                class="flex min-h-[250px] flex-col rounded-2xl border bg-background p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <FileText class="size-5" />
                    </div>

                    <span
                        class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                        :class="statusClass(expandedStatus)"
                    >
                        {{ expandedStatus }}
                    </span>
                </div>

                <div class="mt-5 flex-1">
                    <h3 class="font-black">Informe Ampliado</h3>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Profundiza los hallazgos, riesgos, oportunidades y
                        prioridades identificadas en el diagnóstico.
                    </p>
                </div>

                <div class="mt-5">
                    <template v-if="mode === 'client'">
                        <Link
                            v-if="
                                expandedReportAvailable &&
                                clientExpandedReportUrl
                            "
                            :href="clientExpandedReportUrl"
                            :class="enabledClass"
                        >
                            Ver Informe Ampliado
                            <ArrowRight class="size-4" />
                        </Link>

                        <span v-else :class="disabledClass">
                            <Clock3 class="size-4" />
                            Informe Ampliado no disponible
                        </span>
                    </template>

                    <template v-else>
                        <Link
                            v-if="
                                diagnosisPublished && adminExpandedReportUrl
                            "
                            :href="adminExpandedReportUrl"
                            :class="secondaryEnabledClass"
                        >
                            Gestionar Informe Ampliado
                            <ArrowRight class="size-4" />
                        </Link>

                        <span v-else :class="disabledClass">
                            <Clock3 class="size-4" />
                            Informe generado automáticamente
                        </span>
                    </template>
                </div>
            </article>

            <article
                class="flex min-h-[250px] flex-col rounded-2xl border bg-background p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Map class="size-5" />
                    </div>

                    <span
                        class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                        :class="statusClass(roadmapStatus)"
                    >
                        {{ roadmapStatus }}
                    </span>
                </div>

                <div class="mt-5 flex-1">
                    <h3 class="font-black">Roadmap Detallado</h3>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Convierte los hallazgos en fases, iniciativas,
                        responsables, dependencias e indicadores.
                    </p>
                </div>

                <div class="mt-5">
                    <template v-if="mode === 'client'">
                        <Link
                            v-if="roadmapAvailable && clientRoadmapUrl"
                            :href="clientRoadmapUrl"
                            :class="enabledClass"
                        >
                            Ver Roadmap Detallado
                            <ArrowRight class="size-4" />
                        </Link>

                        <span v-else :class="disabledClass">
                            <Clock3 class="size-4" />
                            Roadmap Detallado no disponible
                        </span>
                    </template>

                    <template v-else>
                        <Link
                            v-if="
                                expandedReportAvailable && adminRoadmapUrl
                            "
                            :href="adminRoadmapUrl"
                            :class="secondaryEnabledClass"
                        >
                            Gestionar Roadmap Detallado
                            <ArrowRight class="size-4" />
                        </Link>

                        <span v-else :class="disabledClass">
                            <Clock3 class="size-4" />
                            Roadmap generado automáticamente tras el Informe
                        </span>
                    </template>
                </div>
            </article>
        </div>

        <div class="mt-5 rounded-2xl border bg-muted/20 p-5">
            <p
                class="text-[10px] font-black tracking-[0.16em] text-primary uppercase"
            >
                Siguiente fase
            </p>

            <h3 class="mt-1 font-black">Plan de Implementación</h3>

            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                El Informe Ampliado y el Roadmap Detallado forman parte del
                flujo gratuito del Diagnóstico 360. Se preparan como
                entregables consultivos para comprender las implicaciones del
                cambio antes de avanzar con la ejecución.
            </p>

            <div class="mt-4">
                <template v-if="mode === 'client'">
                    <Link
                        v-if="implementationPlanUrl"
                        :href="implementationPlanUrl"
                        :class="enabledClass"
                    >
                        Continuar con mi transformación
                        <ArrowRight class="size-4" />
                    </Link>

                    <span v-else :class="disabledClass">
                        <Clock3 class="size-4" />
                        Plan de Implementación en preparación
                    </span>
                </template>

                <template v-else>
                    <!-- DIAGNOSIS360_DIRECT_IMPLEMENTATION_PLAN -->
                    <Link
                        v-if="diagnosisPublished && adminPlanUrl"
                        :href="adminPlanUrl"
                        :class="secondaryEnabledClass"
                    >
                        Gestionar Plan de Implementación
                        <ArrowRight class="size-4" />
                    </Link>

                    <p
                        v-if="diagnosisPublished"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Crear Plan de Implementación desde el diagnóstico
                        oficial.
                    </p>

                    <!-- DIAGNOSIS360_IMPLEMENTATION_PLAN_BLOCKED -->
                    <span
                        v-if="!diagnosisPublished"
                        :class="disabledClass"
                    >
                        <Lock class="size-4" />
                        Publica el diagnóstico para continuar
                    </span>
                </template>
            </div>
        </div>

        <div
            v-if="
                diagnosisPublished &&
                expandedReportAvailable &&
                roadmapAvailable
            "
            class="mt-4 flex items-center gap-2 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
        >
            <CheckCircle2 class="size-4" />
            Entregables del Diagnóstico 360 presentados automáticamente.
        </div>
    </section>
</template>
