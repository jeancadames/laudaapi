<script setup lang="ts">
import { useToast } from '@/components/ui/toast';
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    CircleDashed,
    Save,
    Send,
    Sparkles,
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

type Evaluation = {
    status: string;
    suggested_result: string | null;
    suggested_findings: string | null;
    suggested_recommendation: string | null;
    suggested_priority: string | null;
    suggested_questions: string[];
    generation_context: {
        generation_mode?: string;
        sources?: string[];
        evidence_terms?: string[];
        diagnosis_available?: boolean;
        roadmap_context_available?: boolean;
        plan_context_available?: boolean;
    } | null;
    generation_version: number;
    generated_at: string | null;
    result: string | null;
    findings: string | null;
    recommendation: string | null;
    priority: string | null;
    evaluated_at: string | null;
    evaluated_by: {
        id: number;
        name: string;
        email: string;
    } | null;
};


type EvaluationSummary = {
    status: string;
    generation_version: number;
    generated_at: string | null;
    executive_summary: string | null;
    counts: {
        total?: number;
        requires_attention?: number;
        adequate?: number;
        not_applicable?: number;
    };
    priority_order: Array<{
        need_key: string;
        title: string;
        priority: string | null;
        recommendation: string | null;
    }>;
    dependencies: Array<{
        before_key: string;
        before_title: string;
        after_key: string;
        after_title: string;
        reason: string;
    }>;
    overall_recommendation: string | null;
    generation_context: {
        generation_mode?: string;
        human_evaluations_only?: boolean;
        area_count?: number;
    };
    is_reviewed: boolean;
    reviewed_at: string | null;
    reviewed_by: {
        id: number;
        name: string;
        email: string;
    } | null;
};

type BrandingNeed = {
    id: number;
    sequence: number;
    need_key: string;
    title: string;
    description: string | null;
    evaluation: Evaluation;
};

const props = defineProps<{
    branding: {
        id: number;
        capability_key: string;
        company: {
            id: number;
            name: string;
        };
        assessment: {
            id: number;
            organization_name: string | null;
        } | null;
        source_type: string | null;
        status: string;
        status_label: string;
        activated_at: string | null;
        started_at: string | null;
        ready_for_review_at: string | null;
        validated_at: string | null;
        completed_at: string | null;
        summary: {
            total: number;
            evaluated: number;
            pending: number;
            requires_attention: number;
            adequate: number;
            not_applicable: number;
            all_evaluated: boolean;
        };
        evaluation_summary: EvaluationSummary | null;
        can_generate_summary: boolean;
        can_edit: boolean;
        can_mark_ready: boolean;
        can_review_summary: boolean;
        can_validate: boolean;
        can_complete: boolean;
        needs: BrandingNeed[];
        endpoints: {
            index: string;
            base: string;
        };
    };
}>();

const page = usePage();

const { toast } = useToast();

const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

const hasAutomaticDrafts = computed(() =>
    props.branding.needs.some(
        (need) =>
            Boolean(need.evaluation.suggested_result)
            || Boolean(need.evaluation.suggested_findings)
            || Boolean(need.evaluation.suggested_recommendation)
            || Boolean(need.evaluation.suggested_priority)
            || need.evaluation.suggested_questions.length > 0,
    ),
);

const saving = reactive<Record<number, boolean>>({});
const markingReady = ref(false);
const generatingDrafts = ref(false);
const generatingSummary = ref(false);
const reviewingSummary = ref(false);
const validatingEvaluation = ref(false);
const completingEvaluation = ref(false);

const forms = reactive<
    Record<
        number,
        {
            result: string;
            findings: string;
            recommendation: string;
            priority: string;
        }
    >
>({});

for (const need of props.branding.needs) {
    forms[need.id] = {
        result: need.evaluation.result ?? '',
        findings: need.evaluation.findings ?? '',
        recommendation: need.evaluation.recommendation ?? '',
        priority: need.evaluation.priority ?? '',
    };
}

const summaryReview = reactive({
    executive_summary: '',
    overall_recommendation: '',
});

watch(
    () => props.branding.evaluation_summary,
    (summary) => {
        if (!summary) {
            return;
        }

        summaryReview.executive_summary =
            summary.executive_summary ?? '';

        summaryReview.overall_recommendation =
            summary.overall_recommendation ?? '';
    },
    {
        immediate: true,
        deep: true,
    },
);

function resultLabel(value: string | null): string {
    return (
        {
            requires_attention: 'Requiere atención',
            adequate: 'Adecuado / no requiere intervención',
            not_applicable: 'No aplica',
            insufficient_information: 'Información insuficiente',
        }[value ?? ''] ?? 'Pendiente de evaluación'
    );
}

function priorityLabel(value: string | null): string {
    return (
        {
            high: 'Alta',
            medium: 'Media',
            low: 'Baja',
        }[value ?? ''] ?? 'Sin prioridad'
    );
}

type ManualResultAssistance = {
    recommendation: string;
    priority: 'high' | 'medium' | 'low';
    findingsPrompt: string;
};

const manualResultAssistance: Record<
    string,
    ManualResultAssistance
> = {
    positioning_refinement: {
        recommendation:
            'Revisar y definir la propuesta de valor, la diferenciación y los mensajes prioritarios de la marca para establecer un posicionamiento claro y consistente.',
        priority: 'medium',
        findingsPrompt:
            'Describe la evidencia real observada sobre propuesta de valor, diferenciación, posicionamiento o mensajes de marca.',
    },

    visual_identity_update: {
        recommendation:
            'Revisar y actualizar los elementos de identidad visual para asegurar coherencia, reconocimiento y aplicación consistente de la marca.',
        priority: 'medium',
        findingsPrompt:
            'Describe la evidencia real observada en logotipo, colores, tipografías, recursos gráficos o consistencia visual.',
    },

    brand_kit: {
        recommendation:
            'Desarrollar y documentar un Brand Kit que establezca los elementos visuales, criterios de uso y lineamientos básicos para la aplicación consistente de la marca.',
        priority: 'medium',
        findingsPrompt:
            'Describe qué elementos, lineamientos o recursos de marca existen actualmente y cuáles están ausentes o no documentados.',
    },

    social_normalization: {
        recommendation:
            'Definir e implementar lineamientos de normalización para las redes sociales de la empresa, incluyendo nombres y descripciones de perfiles, imágenes, identidad visual, estilo de publicaciones, portadas y criterios de consistencia entre canales.',
        priority: 'medium',
        findingsPrompt:
            'Describe la evidencia real observada en perfiles, nombres, biografías, imágenes, publicaciones, portadas o consistencia entre redes sociales.',
    },

    commercial_documents: {
        recommendation:
            'Estandarizar la aplicación de la identidad de marca en los documentos comerciales utilizados por la empresa y definir criterios consistentes para sus formatos.',
        priority: 'medium',
        findingsPrompt:
            'Describe la evidencia observada en cotizaciones, propuestas, facturas, presentaciones u otros documentos comerciales.',
    },

    web_application: {
        recommendation:
            'Definir y aplicar criterios de identidad de marca en la presencia web para asegurar coherencia visual, mensajes consistentes y una experiencia alineada con la identidad definida.',
        priority: 'medium',
        findingsPrompt:
            'Describe la evidencia real observada en sitio web, landing pages, contenidos, recursos visuales o consistencia de marca en canales web.',
    },
};

function findingsPlaceholder(need: BrandingNeed): string {
    if (
        need.evaluation.suggested_result
        === 'insufficient_information'
    ) {
        return (
            manualResultAssistance[need.need_key]
                ?.findingsPrompt
            ?? 'Documenta la evidencia real que sustenta el resultado seleccionado.'
        );
    }

    return 'Documenta la evidencia y los hallazgos que sustentan el resultado.';
}

function assistFromSelectedResult(
    need: BrandingNeed,
): void {
    if (
        !props.branding.can_edit
        || need.evaluation.status === 'evaluated'
        || need.evaluation.suggested_result
            !== 'insufficient_information'
    ) {
        return;
    }

    const form = forms[need.id];
    const assistance =
        manualResultAssistance[need.need_key];

    if (!form || !assistance) {
        return;
    }

    const genericDraftFinding =
        need.evaluation.suggested_findings?.trim()
        ?? '';

    /*
     * Un mensaje automático de "información insuficiente"
     * no es evidencia profesional.
     */
    if (
        genericDraftFinding
        && form.findings.trim()
            === genericDraftFinding
    ) {
        form.findings = '';
    }

    if (form.result === 'requires_attention') {
        if (!form.recommendation.trim()) {
            form.recommendation =
                assistance.recommendation;
        }

        if (!form.priority) {
            form.priority =
                assistance.priority;
        }

        toast({
            title: 'Campos de apoyo completados',
            description:
                'Se propusieron recomendación y prioridad. '
                + 'Documenta los hallazgos reales y revisa '
                + 'todos los campos antes de guardar.',
            variant: 'success',
        });

        return;
    }

    /*
     * Si anteriormente LAUDA autocompletó estos campos
     * para "Requiere atención" y el Admin cambia el
     * resultado, retiramos SOLO los valores automáticos.
     * Nunca borramos contenido humano diferente.
     */
    if (
        form.recommendation.trim()
        === assistance.recommendation
    ) {
        form.recommendation = '';
    }

    if (
        form.priority
        === assistance.priority
    ) {
        form.priority = '';
    }

    if (form.result === 'adequate') {
        toast({
            title: 'Resultado seleccionado',
            description:
                'Documenta los hallazgos reales que permiten '
                + 'considerar esta área adecuada antes de guardar.',
            variant: 'warning',
        });

        return;
    }

    if (form.result === 'not_applicable') {
        toast({
            title: 'Resultado seleccionado',
            description:
                'Documenta por qué esta área no aplica '
                + 'antes de guardar la evaluación.',
            variant: 'warning',
        });
    }
}

function useSuggestion(need: BrandingNeed): void {
    if (!props.branding.can_edit) {
        return;
    }

    const current = forms[need.id];

    if (!current) {
        return;
    }

    const insufficientInformation =
        need.evaluation.suggested_result
        === 'insufficient_information';

    const suggestedResult =
        need.evaluation.suggested_result
        && !insufficientInformation
            ? need.evaluation.suggested_result
            : current.result;

    forms[need.id] = {
        result: suggestedResult,
        findings:
            need.evaluation.suggested_findings
            ?? current.findings,
        recommendation:
            need.evaluation.suggested_recommendation
            ?? current.recommendation,
        priority:
            need.evaluation.suggested_priority
            ?? current.priority,
    };

    if (insufficientInformation) {
        toast({
            title: 'Borrador aplicado parcialmente',
            description:
                'La información disponible no permite confirmar un resultado. '
                + 'Se copiaron los campos disponibles; completa la evaluación '
                + 'con criterio profesional antes de guardar.',
            variant: 'warning',
        });

        return;
    }

    toast({
        title: 'Borrador aplicado',
        description:
            'Los campos sugeridos fueron copiados. Revísalos antes de guardar.',
        variant: 'success',
    });
}

function saveEvaluation(needId: number): void {
    if (!props.branding.can_edit || saving[needId]) {
        return;
    }

    saving[needId] = true;

    router.patch(
        `${props.branding.endpoints.base}/areas/${needId}`,
        forms[needId],
        {
            preserveScroll: true,
            onFinish: () => {
                saving[needId] = false;
            },
        },
    );
}


function generateDrafts(): void {
    if (
        !props.branding.can_edit
        || generatingDrafts.value
    ) {
        return;
    }

    generatingDrafts.value = true;

    router.post(
        `${props.branding.endpoints.base}/generate-drafts`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                generatingDrafts.value = false;
            },
        },
    );
}


function generateSummary(): void {
    if (
        !props.branding.can_generate_summary
        || generatingSummary.value
    ) {
        return;
    }

    generatingSummary.value = true;

    router.post(
        `${props.branding.endpoints.base}/summary/generate`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                generatingSummary.value = false;
            },
        },
    );
}

function markReady(): void {
    if (
        !props.branding.can_mark_ready
        || markingReady.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Marcar esta Evaluación de Branding como lista para revisión? Las áreas dejarán de ser editables.',
        )
    ) {
        return;
    }

    markingReady.value = true;

    router.post(
        `${props.branding.endpoints.base}/ready-for-review`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                markingReady.value = false;
            },
        },
    );
}

function reviewSummary(): void {
    if (
        !props.branding.can_review_summary
        || reviewingSummary.value
    ) {
        return;
    }

    reviewingSummary.value = true;

    router.patch(
        `${props.branding.endpoints.base}/summary/review`,
        summaryReview,
        {
            preserveScroll: true,
            onFinish: () => {
                reviewingSummary.value = false;
            },
        },
    );
}

function validateEvaluation(): void {
    if (
        !props.branding.can_validate
        || validatingEvaluation.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Validar esta Evaluación de Branding? La síntesis revisada quedará como resultado profesional aprobado.',
        )
    ) {
        return;
    }

    validatingEvaluation.value = true;

    router.post(
        `${props.branding.endpoints.base}/validate`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                validatingEvaluation.value = false;
            },
        },
    );
}

function completeEvaluation(): void {
    if (
        !props.branding.can_complete
        || completingEvaluation.value
    ) {
        return;
    }

    if (
        !window.confirm(
            '¿Marcar esta Evaluación de Branding como completada? Esto cerrará el lifecycle de la evaluación.',
        )
    ) {
        return;
    }

    completingEvaluation.value = true;

    router.post(
        `${props.branding.endpoints.base}/complete`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                completingEvaluation.value = false;
            },
        },
    );
}

</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Administración', href: '/admin' },
            {
                title: 'Evaluaciones de Branding',
                href: branding.endpoints.index,
            },
            {
                title: branding.company.name,
            },
        ]"
    >
        <Head
            :title="`Evaluación de Branding · ${branding.company.name}`"
        />

        <div class="space-y-6 p-4 md:p-6">
            <div>
                <Link
                    :href="branding.endpoints.index"
                    class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-950 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Volver a evaluaciones
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <Badge variant="outline">
                                LAUDA 360 · Branding
                            </Badge>

                            <CardTitle class="mt-3 text-2xl">
                                {{ branding.company.name }}
                            </CardTitle>

                            <CardDescription class="mt-2">
                                Evaluación profesional de Branding e Identidad
                                Digital.
                            </CardDescription>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {{ branding.status_label }}
                            </Badge>

                            <Button
                                v-if="branding.can_edit"
                                type="button"
                                variant="outline"
                                :disabled="generatingDrafts"
                                @click="generateDrafts"
                            >
                                <Sparkles class="mr-2 h-4 w-4" />
                                {{
                                    generatingDrafts
                                        ? (
                                            hasAutomaticDrafts
                                                ? 'Regenerando...'
                                                : 'Generando...'
                                        )
                                        : (
                                            hasAutomaticDrafts
                                                ? 'Regenerar borradores automáticos'
                                                : 'Generar borradores automáticos'
                                        )
                                }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <span class="text-xs font-black uppercase">
                                Evaluadas
                            </span>
                            <p class="mt-2 text-2xl font-black">
                                {{ branding.summary.evaluated }}
                                /
                                {{ branding.summary.total }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <span class="text-xs font-black uppercase">
                                Pendientes
                            </span>
                            <p class="mt-2 text-2xl font-black">
                                {{ branding.summary.pending }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <span class="text-xs font-black uppercase">
                                Requieren atención
                            </span>
                            <p class="mt-2 text-2xl font-black">
                                {{ branding.summary.requires_attention }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <span class="text-xs font-black uppercase">
                                Adecuadas / no aplica
                            </span>
                            <p class="mt-2 text-2xl font-black">
                                {{
                                    branding.summary.adequate
                                        + branding.summary.not_applicable
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="branding.status === 'activated'"
                        class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        El tenant todavía no ha iniciado la evaluación.
                        Las áreas permanecerán en modo lectura hasta que el
                        estado cambie a En progreso.
                    </div>

                    <div
                        v-else-if="branding.status === 'ready_for_review'"
                        class="mt-5 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200"
                    >
                        La evaluación está lista para revisión. Las áreas están
                        bloqueadas para preservar el resultado enviado.
                    </div>
                </CardContent>
            </Card>

            <div
                v-if="errors.evaluation"
                class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
            >
                {{ errors.evaluation }}
            </div>

            <div
                v-if="errors.summary || errors.capability"
                class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
            >
                {{ errors.summary ?? errors.capability }}
            </div>

            <Card
                v-for="need in branding.needs"
                :key="need.id"
            >
                <CardHeader>
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xs font-black dark:bg-slate-900"
                                >
                                    {{
                                        String(need.sequence).padStart(2, '0')
                                    }}
                                </span>

                                <div>
                                    <CardTitle>
                                        {{ need.title }}
                                    </CardTitle>

                                    <CardDescription
                                        v-if="need.description"
                                        class="mt-1"
                                    >
                                        {{ need.description }}
                                    </CardDescription>
                                </div>
                            </div>
                        </div>

                        <Badge
                            :variant="
                                need.evaluation.status === 'evaluated'
                                    ? 'default'
                                    : 'outline'
                            "
                        >
                            {{
                                need.evaluation.status === 'evaluated'
                                    ? resultLabel(need.evaluation.result)
                                    : 'Pendiente de evaluación'
                            }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-5">
                    <div
                        v-if="
                            need.evaluation.generated_at
                            || need.evaluation.suggested_result
                        "
                        class="rounded-2xl border border-violet-200 bg-violet-50/60 p-4 dark:border-violet-900 dark:bg-violet-950/20"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-2">
                                <Sparkles class="h-4 w-4" />
                                <p class="font-black">
                                    Borrador automático
                                </p>
                            </div>

                            <button
                                v-if="branding.can_edit"
                                type="button"
                                class="inline-flex h-8 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md border bg-background px-3 text-sm font-medium shadow-xs transition-all hover:bg-accent hover:text-accent-foreground"
                                @click="useSuggestion(need)"
                            >
                                Usar como punto de partida
                            </button>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div>
                                <p class="text-xs font-black uppercase">
                                    Resultado sugerido
                                </p>
                                <p class="mt-1 text-sm">
                                    {{
                                        resultLabel(
                                            need.evaluation.suggested_result,
                                        )
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-black uppercase">
                                    Prioridad sugerida
                                </p>
                                <p class="mt-1 text-sm">
                                    {{
                                        priorityLabel(
                                            need.evaluation
                                                .suggested_priority,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="need.evaluation.suggested_findings"
                            class="mt-4"
                        >
                            <p class="text-xs font-black uppercase">
                                Hallazgos sugeridos
                            </p>
                            <p
                                class="mt-1 whitespace-pre-line text-sm leading-6"
                            >
                                {{
                                    need.evaluation.suggested_findings
                                }}
                            </p>
                        </div>

                        <div
                            v-if="
                                need.evaluation.suggested_recommendation
                            "
                            class="mt-4"
                        >
                            <p class="text-xs font-black uppercase">
                                Recomendación sugerida
                            </p>
                            <p
                                class="mt-1 whitespace-pre-line text-sm leading-6"
                            >
                                {{
                                    need.evaluation
                                        .suggested_recommendation
                                }}
                            </p>
                        </div>

                        <div
                            v-if="
                                need.evaluation.suggested_questions.length
                            "
                            class="mt-4"
                        >
                            <p class="text-xs font-black uppercase">
                                Información adicional sugerida
                            </p>

                            <ul class="mt-2 space-y-1 text-sm">
                                <li
                                    v-for="question in need.evaluation
                                        .suggested_questions"
                                    :key="question"
                                >
                                    • {{ question }}
                                </li>
                            </ul>
                        </div>


                        <div
                            v-if="
                                need.evaluation.generation_context?.sources
                                    ?.length
                            "
                            class="mt-4"
                        >
                            <p class="text-xs font-black uppercase">
                                Fuentes consideradas
                            </p>

                            <p class="mt-1 text-sm">
                                {{
                                    need.evaluation.generation_context.sources.join(
                                        ' · ',
                                    )
                                }}
                            </p>
                        </div>

                        <p
                            class="mt-4 text-xs text-violet-700 dark:text-violet-300"
                        >
                            Este contenido es un borrador de apoyo. No
                            constituye la evaluación profesional final hasta
                            que LAUDA lo revise y confirme.
                        </p>
                    </div>

                    <div class="grid gap-4">
                        <label class="grid gap-2">
                            <span class="text-sm font-black">
                                Resultado *
                            </span>

                            <select
                                v-model="forms[need.id].result"
                                :disabled="!branding.can_edit"
                                @change="assistFromSelectedResult(need)"
                                class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                            >
                                <option value="">
                                    Selecciona un resultado
                                </option>
                                <option value="requires_attention">
                                    Requiere atención
                                </option>
                                <option value="adequate">
                                    Adecuado / no requiere intervención
                                </option>
                                <option value="not_applicable">
                                    No aplica
                                </option>
                            </select>
                        </label>

                        <label class="grid gap-2">
                            <span class="text-sm font-black">
                                Hallazgos *
                            </span>

                            <textarea
                                v-model="forms[need.id].findings"
                                :disabled="!branding.can_edit"
                                rows="5"
                                maxlength="5000"
                                class="rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                                :placeholder="findingsPlaceholder(need)"
                            />
                        </label>

                        <label class="grid gap-2">
                            <span class="text-sm font-black">
                                Recomendación
                            </span>

                            <textarea
                                v-model="forms[need.id].recommendation"
                                :disabled="!branding.can_edit"
                                rows="4"
                                maxlength="5000"
                                class="rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                                placeholder="Indica la acción recomendada cuando corresponda."
                            />
                        </label>

                        <label class="grid gap-2">
                            <span class="text-sm font-black">
                                Prioridad
                            </span>

                            <select
                                v-model="forms[need.id].priority"
                                :disabled="!branding.can_edit"
                                class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                            >
                                <option value="">
                                    Sin prioridad
                                </option>
                                <option value="high">
                                    Alta
                                </option>
                                <option value="medium">
                                    Media
                                </option>
                                <option value="low">
                                    Baja
                                </option>
                            </select>
                        </label>
                    </div>

                    <div
                        v-if="need.evaluation.evaluated_at"
                        class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-900 dark:text-slate-400"
                    >
                        Evaluada por
                        {{
                            need.evaluation.evaluated_by?.name
                            ?? 'LAUDA'
                        }}
                        ·
                        {{
                            new Date(
                                need.evaluation.evaluated_at,
                            ).toLocaleString('es-DO')
                        }}
                    </div>

                    <div
                        v-if="branding.can_edit"
                        class="flex justify-end"
                    >
                        <Button
                            type="button"
                            :disabled="saving[need.id]"
                            @click="saveEvaluation(need.id)"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            Guardar evaluación
                        </Button>
                    </div>
                </CardContent>
            </Card>


            <Card>
                <CardHeader>
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <Sparkles class="h-5 w-5" />
                                <CardTitle>
                                    Síntesis de la Evaluación
                                </CardTitle>
                            </div>

                            <CardDescription class="mt-2">
                                Se genera exclusivamente desde las evaluaciones
                                profesionales confirmadas de las seis áreas.
                                Los borradores automáticos por área no se usan
                                como decisiones finales.
                            </CardDescription>
                        </div>

                        <Button
                            v-if="
                                branding.can_generate_summary
                            "
                            type="button"
                            variant="outline"
                            :disabled="generatingSummary"
                            @click="generateSummary"
                        >
                            <Sparkles class="mr-2 h-4 w-4" />
                            {{
                                generatingSummary
                                    ? 'Generando síntesis...'
                                    : branding.evaluation_summary
                                      ? 'Regenerar síntesis'
                                      : 'Generar síntesis'
                            }}
                        </Button>
                    </div>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="branding.evaluation_summary"
                        class="space-y-5"
                    >
                        <div
                            class="rounded-2xl border border-violet-200 bg-violet-50/60 p-4 dark:border-violet-900 dark:bg-violet-950/20"
                        >
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <Badge variant="outline">
                                    Borrador automático
                                </Badge>

                                <span
                                    class="text-xs text-slate-500"
                                >
                                    V{{
                                        branding
                                            .evaluation_summary
                                            .generation_version
                                    }}
                                </span>
                            </div>

                            <p
                                class="mt-4 whitespace-pre-line text-sm leading-6"
                            >
                                {{
                                    branding
                                        .evaluation_summary
                                        .executive_summary
                                }}
                            </p>
                        </div>

                        <div
                            v-if="
                                branding.evaluation_summary
                                    .priority_order.length
                            "
                        >
                            <p
                                class="text-sm font-black"
                            >
                                Prioridades confirmadas
                            </p>

                            <div
                                class="mt-3 grid gap-3"
                            >
                                <div
                                    v-for="(
                                        item,
                                        index
                                    ) in branding
                                        .evaluation_summary
                                        .priority_order"
                                    :key="item.need_key"
                                    class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="text-xs font-black"
                                        >
                                            {{
                                                index + 1
                                            }}
                                        </span>

                                        <span
                                            class="font-black"
                                        >
                                            {{ item.title }}
                                        </span>

                                        <Badge
                                            v-if="
                                                item.priority
                                            "
                                            variant="outline"
                                        >
                                            {{
                                                priorityLabel(
                                                    item.priority,
                                                )
                                            }}
                                        </Badge>
                                    </div>

                                    <p
                                        v-if="
                                            item.recommendation
                                        "
                                        class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            item.recommendation
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="
                                branding.evaluation_summary
                                    .dependencies.length
                            "
                        >
                            <p class="text-sm font-black">
                                Dependencias recomendadas
                            </p>

                            <div
                                class="mt-3 space-y-3"
                            >
                                <div
                                    v-for="dependency in branding
                                        .evaluation_summary
                                        .dependencies"
                                    :key="
                                        `${dependency.before_key}-${dependency.after_key}`
                                    "
                                    class="rounded-2xl border border-slate-200 p-4 text-sm dark:border-slate-800"
                                >
                                    <p class="font-black">
                                        {{
                                            dependency.before_title
                                        }}
                                        →
                                        {{
                                            dependency.after_title
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 leading-6 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            dependency.reason
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="
                                branding.evaluation_summary
                                    .overall_recommendation
                            "
                        >
                            <p class="text-sm font-black">
                                Recomendación general
                            </p>

                            <p
                                class="mt-2 whitespace-pre-line text-sm leading-6"
                            >
                                {{
                                    branding
                                        .evaluation_summary
                                        .overall_recommendation
                                }}
                            </p>
                        </div>

                        <p
                            class="text-xs text-slate-500"
                        >
                            Esta síntesis es un borrador automático construido
                            a partir de decisiones profesionales ya confirmadas.
                            No crea contratación, cotización ni ejecución de
                            servicios.
                        </p>
                    </div>

                    <div
                        v-else-if="
                            branding.summary.all_evaluated
                        "
                        class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        Las seis áreas ya están evaluadas. Genera la síntesis
                        antes de enviar la evaluación a revisión.
                    </div>

                    <div
                        v-else
                        class="rounded-2xl border border-slate-200 p-4 text-sm text-slate-500 dark:border-slate-800"
                    >
                        La síntesis estará disponible cuando las seis áreas
                        tengan una evaluación profesional confirmada.
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="
                    branding.status === 'ready_for_review'
                    || branding.status === 'validated'
                    || branding.status === 'completed'
                "
            >
                <CardHeader>
                    <CardTitle>
                        Revisión y cierre profesional
                    </CardTitle>

                    <CardDescription class="mt-2">
                        LAUDA revisa la síntesis antes de validar el resultado.
                        La validación confirma el resultado profesional y el
                        cierre posterior finaliza la evaluación. Ninguna de
                        estas acciones crea una contratación o ejecución
                        comercial.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-5">
                    <div
                        v-if="branding.evaluation_summary"
                        class="grid gap-4"
                    >
                        <label class="grid gap-2">
                            <span class="text-sm font-black">
                                Resumen ejecutivo revisado *
                            </span>

                            <textarea
                                v-model="summaryReview.executive_summary"
                                :disabled="!branding.can_review_summary"
                                rows="7"
                                maxlength="10000"
                                class="rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                                placeholder="Confirma o ajusta el resumen ejecutivo de la evaluación."
                            />
                        </label>

                        <label class="grid gap-2">
                            <span class="text-sm font-black">
                                Recomendación general revisada
                            </span>

                            <textarea
                                v-model="
                                    summaryReview.overall_recommendation
                                "
                                :disabled="!branding.can_review_summary"
                                rows="5"
                                maxlength="10000"
                                class="rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                                placeholder="Confirma o ajusta la recomendación general."
                            />
                        </label>

                        <div
                            v-if="
                                branding.evaluation_summary.is_reviewed
                                && branding.evaluation_summary.reviewed_at
                            "
                            class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-900 dark:text-slate-400"
                        >
                            Síntesis revisada por
                            {{
                                branding.evaluation_summary
                                    .reviewed_by?.name
                                ?? 'LAUDA'
                            }}
                            ·
                            {{
                                new Date(
                                    branding.evaluation_summary.reviewed_at,
                                ).toLocaleString('es-DO')
                            }}
                        </div>
                    </div>

                    <div
                        v-if="branding.status === 'ready_for_review'"
                        class="flex flex-wrap gap-3"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="
                                !branding.can_review_summary
                                || reviewingSummary
                            "
                            @click="reviewSummary"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            {{
                                reviewingSummary
                                    ? 'Guardando revisión...'
                                    : branding.evaluation_summary?.is_reviewed
                                      ? 'Actualizar revisión'
                                      : 'Confirmar revisión'
                            }}
                        </Button>

                        <Button
                            type="button"
                            :disabled="
                                !branding.can_validate
                                || validatingEvaluation
                            "
                            @click="validateEvaluation"
                        >
                            <CheckCircle2 class="mr-2 h-4 w-4" />
                            {{
                                validatingEvaluation
                                    ? 'Validando...'
                                    : 'Validar evaluación'
                            }}
                        </Button>

                        <p
                            v-if="!branding.can_validate"
                            class="basis-full text-xs text-slate-500"
                        >
                            Primero confirma la revisión humana de la síntesis.
                        </p>
                    </div>

                    <div
                        v-else-if="branding.status === 'validated'"
                        class="space-y-4"
                    >
                        <div
                            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                        >
                            La evaluación está validada. El resultado
                            profesional ya fue aprobado y puede cerrarse.
                        </div>

                        <Button
                            type="button"
                            :disabled="
                                !branding.can_complete
                                || completingEvaluation
                            "
                            @click="completeEvaluation"
                        >
                            <CheckCircle2 class="mr-2 h-4 w-4" />
                            {{
                                completingEvaluation
                                    ? 'Completando...'
                                    : 'Completar evaluación'
                            }}
                        </Button>
                    </div>

                    <div
                        v-else-if="branding.status === 'completed'"
                        class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                    >
                        Evaluación de Branding completada.
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex items-start gap-3">
                        <component
                            :is="
                                branding.summary.all_evaluated
                                    ? CheckCircle2
                                    : CircleDashed
                            "
                            class="mt-1 h-5 w-5"
                        />

                        <div>
                            <CardTitle>
                                Preparación para revisión
                            </CardTitle>

                            <CardDescription class="mt-2">
                                {{
                                    !branding.summary.all_evaluated
                                        ? `Faltan ${branding.summary.pending} área(s) por evaluar antes de continuar.`
                                        : !branding.evaluation_summary
                                          ? 'Las seis áreas están evaluadas. Genera la síntesis antes de enviar el resultado a revisión.'
                                          : 'Las seis áreas están evaluadas y la síntesis está generada. Puedes enviar el resultado a revisión.'
                                }}
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <Button
                        v-if="branding.status === 'in_progress'"
                        type="button"
                        :disabled="
                            !branding.can_mark_ready
                            || markingReady
                        "
                        @click="markReady"
                    >
                        <Send class="mr-2 h-4 w-4" />
                        Marcar listo para revisión
                    </Button>

                    <div
                        v-else-if="
                            branding.status === 'ready_for_review'
                        "
                        class="text-sm font-bold"
                    >
                        Evaluación enviada a revisión.
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
