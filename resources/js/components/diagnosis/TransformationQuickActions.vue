<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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

const expandedReportAvailable = computed(() =>
    isCompleted('expanded_report_published'),
);

const roadmapAvailable = computed(() => isCompleted('roadmap_published'));

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

const enabledClass =
    'inline-flex min-h-10 items-center justify-center rounded-lg border px-4 py-2 text-sm font-medium transition hover:bg-muted';

const disabledClass =
    'inline-flex min-h-10 cursor-not-allowed items-center justify-center rounded-lg border border-dashed px-4 py-2 text-sm text-muted-foreground opacity-70';
</script>

<template>
    <section class="rounded-xl border bg-card p-4 shadow-sm">
        <div class="mb-4">
            <h2 class="text-sm font-semibold">Acciones rápidas</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    mode === 'admin'
                        ? 'Accede directamente a los entregables y su gestión.'
                        : 'Accede rápidamente a tus entregables disponibles.'
                }}
            </p>
        </div>

        <div
            v-if="mode === 'client'"
            class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Link
                v-if="clientDiagnosisUrl"
                :href="clientDiagnosisUrl"
                :class="enabledClass"
            >
                Ver diagnóstico
            </Link>

            <Link
                v-if="expandedReportAvailable && clientExpandedReportUrl"
                :href="clientExpandedReportUrl"
                :class="enabledClass"
            >
                Ver Informe Ampliado
            </Link>
            <span v-else :class="disabledClass">
                Informe Ampliado no disponible
            </span>

            <Link
                v-if="roadmapAvailable && clientRoadmapUrl"
                :href="clientRoadmapUrl"
                :class="enabledClass"
            >
                Ver Roadmap Detallado
            </Link>
            <span v-else :class="disabledClass">
                Roadmap Detallado no disponible
            </span>

            <Link
                v-if="implementationPlanUrl"
                :href="implementationPlanUrl"
                :class="enabledClass"
            >
                Continuar con mi transformación
            </Link>
            <span v-else :class="disabledClass">
                Plan de Implementación en preparación
            </span>
        </div>

        <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-if="adminDiagnosisUrl"
                :href="adminDiagnosisUrl"
                :class="enabledClass"
            >
                Ficha del diagnóstico
            </Link>

            <Link
                v-if="adminExpandedReportUrl"
                :href="adminExpandedReportUrl"
                :class="enabledClass"
            >
                Gestionar Informe Ampliado
            </Link>

            <Link
                v-if="adminRoadmapUrl"
                :href="adminRoadmapUrl"
                :class="enabledClass"
            >
                Gestionar Roadmap Detallado
            </Link>

            <Link
                v-if="adminPlanUrl"
                :href="adminPlanUrl"
                :class="enabledClass"
            >
                Gestionar Plan de Implementación
            </Link>
        </div>
    </section>
</template>
