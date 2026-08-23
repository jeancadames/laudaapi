<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Clock3,
    ShieldCheck,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DigitalDiagnosisWizard from './DigitalDiagnosisWizard.vue';
import { maturityDimensions } from '@/lib/diagnostico-lauda360-v1';

interface Assessment {
    id: number;
    status: 'draft' | 'in_progress' | 'submitted' | 'reviewed' | string;
    current_step: number;
    answers: Record<string, number>;
    notes: Record<string, string>;
    started_at?: string | null;
    submitted_at?: string | null;
    reviewed_at?: string | null;
    updated_at?: string | null;
}

interface Result {
    maturity_score: number | null;
    maturity_level: string | null;
    capacity_score: number | null;
    urgency_score: number | null;
    urgency_level: string | null;
    dimension_scores: Record<string, number>;
    modality: string | null;
    modality_label: string | null;
    summary: string | null;
    priorities: string[];
    published_at: string | null;
}

interface Organization {
    id: number | string;
    name: string;
}

interface Endpoints {
    update: string;
    submit: string;
    back: string;
}

const props = defineProps<{
    assessment: Assessment;
    organization: Organization;
    result: Result | null;
    endpoints: Endpoints;
}>();

const saveStatus = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const submitError = ref<string | null>(null);

const isEditable = computed(() =>
    ['draft', 'in_progress'].includes(props.assessment.status),
);
const isSubmitted = computed(() => props.assessment.status === 'submitted');
const isReviewed = computed(
    () => props.assessment.status === 'reviewed' && props.result !== null,
);

const statusLabel = computed(() => {
    switch (props.assessment.status) {
        case 'draft':
            return 'Pendiente de iniciar';
        case 'in_progress':
            return 'En progreso';
        case 'submitted':
            return 'En revisión por LAUDA';
        case 'reviewed':
            return 'Resultado disponible';
        default:
            return props.assessment.status;
    }
});

const saveStatusLabel = computed(() => {
    if (saveStatus.value === 'saving') return 'Guardando…';
    if (saveStatus.value === 'saved') return 'Cambios guardados';
    if (saveStatus.value === 'error') return 'No se pudo guardar';
    return null;
});

function saveDiagnosis(payload: {
    answers: Record<string, number>;
    notes: Record<string, string>;
    step: number;
}) {
    if (!isEditable.value) return;

    saveStatus.value = 'saving';

    router.patch(
        props.endpoints.update,
        {
            answers: payload.answers,
            notes: payload.notes,
            current_step: payload.step,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                saveStatus.value = 'saved';
            },
            onError: () => {
                saveStatus.value = 'error';
            },
        },
    );
}

function submitDiagnosis(payload: {
    answers: Record<string, number>;
    notes: Record<string, string>;
    maturityScore: number;
    capacityScore: number;
    urgencyScore: number;
}) {
    if (!isEditable.value) return;

    submitError.value = null;

    router.post(
        props.endpoints.submit,
        {
            answers: payload.answers,
            notes: payload.notes,
        },
        {
            preserveScroll: true,
            onError: () => {
                submitError.value =
                    'No pudimos enviar el diagnóstico. Revise las respuestas e inténtelo nuevamente.';
            },
        },
    );
}
</script>

<template>
    <Head title="Diagnóstico LAUDA 360" />

    <div class="min-h-screen bg-muted/20">
        <header class="border-b bg-background">
            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <Button
                        as-child
                        variant="ghost"
                        size="icon"
                        class="mt-0.5 shrink-0"
                    >
                        <Link :href="endpoints.back" aria-label="Volver">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                            >
                                LAUDA Transformación Digital 360
                            </p>
                            <Badge variant="outline">
                                {{ statusLabel }}
                            </Badge>
                        </div>

                        <h1
                            class="mt-1 text-2xl font-black tracking-tight sm:text-3xl"
                        >
                            Diagnóstico Digital 360
                        </h1>

                        <div
                            class="mt-2 flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Building2 class="h-4 w-4 shrink-0" />
                            <span class="truncate">
                                {{ organization.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                >
                    <div class="flex items-center gap-1.5">
                        <ShieldCheck class="h-4 w-4" />
                        <span>Acceso privado</span>
                    </div>

                    <div
                        v-if="saveStatusLabel"
                        class="flex items-center gap-1.5"
                    >
                        <CheckCircle2
                            v-if="saveStatus === 'saved'"
                            class="h-4 w-4"
                        />
                        <Clock3 v-else class="h-4 w-4" />
                        <span>{{ saveStatusLabel }}</span>
                    </div>
                </div>
            </div>
        </header>

        <main
            class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
        >
            <template v-if="isSubmitted">
                <Card class="border-primary/20 bg-primary/5">
                    <CardContent class="p-6 sm:p-8">
                        <div class="flex items-start gap-4">
                            <CheckCircle2
                                class="mt-0.5 h-6 w-6 shrink-0 text-primary"
                            />
                            <div>
                                <p class="text-lg font-black">
                                    Diagnóstico enviado
                                </p>
                                <p
                                    class="mt-2 max-w-3xl text-sm leading-7 text-muted-foreground"
                                >
                                    Hemos recibido sus respuestas correctamente.
                                    El diagnóstico queda bloqueado mientras el
                                    equipo de LAUDA revisa los resultados y
                                    prepara las conclusiones, prioridades de
                                    transformación y modalidad de acompañamiento
                                    recomendada.
                                </p>
                                <p class="mt-3 text-sm font-semibold">
                                    Le notificaremos cuando su resultado esté
                                    disponible.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <template v-else-if="isReviewed && result">
                <div class="space-y-5">
                    <Card
                        class="border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20"
                    >
                        <CardContent class="p-6 sm:p-8">
                            <div class="flex items-start gap-4">
                                <CheckCircle2
                                    class="mt-0.5 h-6 w-6 shrink-0 text-emerald-600"
                                />
                                <div>
                                    <p class="text-lg font-black">
                                        Resultado revisado por LAUDA
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-7 text-muted-foreground"
                                    >
                                        La revisión del Diagnóstico LAUDA 360 ha
                                        sido completada. Este es el resultado
                                        oficial de la evaluación inicial.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardContent class="p-5">
                                <p
                                    class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                >
                                    Madurez digital
                                </p>
                                <p class="mt-2 text-4xl font-black">
                                    {{ result.maturity_score ?? '—' }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ result.maturity_level || '—' }}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-5">
                                <p
                                    class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                >
                                    Capacidad interna
                                </p>
                                <p class="mt-2 text-4xl font-black">
                                    {{ result.capacity_score ?? '—' }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    /100
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-5">
                                <p
                                    class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                >
                                    Urgencia
                                </p>
                                <p class="mt-2 text-4xl font-black">
                                    {{ result.urgency_score ?? '—' }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ result.urgency_level || '—' }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardContent class="p-6">
                            <p
                                class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                            >
                                Conclusión ejecutiva
                            </p>
                            <p
                                class="mt-3 text-sm leading-7 whitespace-pre-line"
                            >
                                {{ result.summary }}
                            </p>
                        </CardContent>
                    </Card>

                    <div class="grid gap-5 lg:grid-cols-[1fr_0.75fr]">
                        <Card>
                            <CardContent class="p-6">
                                <p
                                    class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                                >
                                    Prioridades
                                </p>
                                <ol class="mt-4 space-y-3">
                                    <li
                                        v-for="(
                                            priority, index
                                        ) in result.priorities"
                                        :key="`${index}-${priority}`"
                                        class="flex gap-3 rounded-xl border p-3"
                                    >
                                        <span
                                            class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[#F53003]/10 text-xs font-black text-[#F53003]"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <span class="text-sm leading-6">
                                            {{ priority }}
                                        </span>
                                    </li>
                                </ol>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <p
                                    class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                                >
                                    Modalidad recomendada
                                </p>
                                <p class="mt-3 text-xl font-black">
                                    {{ result.modality_label || '—' }}
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-muted-foreground"
                                >
                                    Esta modalidad refleja la revisión final de
                                    LAUDA sobre su capacidad, urgencia y
                                    contexto.
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardContent class="p-6">
                            <p
                                class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                            >
                                Resultado por dimensión
                            </p>
                            <div
                                class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div
                                    v-for="dimension in maturityDimensions"
                                    :key="dimension.id"
                                    class="rounded-xl border p-4"
                                >
                                    <p class="text-xs font-bold">
                                        {{ dimension.shortTitle }}
                                    </p>
                                    <p class="mt-2 text-2xl font-black">
                                        {{
                                            result.dimension_scores?.[
                                                dimension.id
                                            ] ?? '—'
                                        }}
                                    </p>
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        /100
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="bg-muted/20">
                        <CardContent
                            class="p-5 text-sm leading-6 text-muted-foreground"
                        >
                            Este resultado corresponde al diagnóstico inicial
                            gratuito. El Informe Ampliado profundiza hallazgos,
                            implicaciones y recomendaciones; el Roadmap
                            Detallado convierte esas conclusiones en
                            iniciativas, prioridades, responsables y secuencia
                            de ejecución.
                        </CardContent>
                    </Card>
                </div>
            </template>

            <template v-else>
                <Card
                    v-if="submitError"
                    class="mb-6 border-destructive/30 bg-destructive/5"
                >
                    <CardContent class="p-4 text-sm text-destructive sm:p-5">
                        {{ submitError }}
                    </CardContent>
                </Card>

                <DigitalDiagnosisWizard
                    :initial-answers="assessment.answers ?? {}"
                    :initial-notes="assessment.notes ?? {}"
                    :initial-step="assessment.current_step ?? 1"
                    :read-only="false"
                    @save="saveDiagnosis"
                    @submit="submitDiagnosis"
                />
            </template>
        </main>
    </div>
</template>
