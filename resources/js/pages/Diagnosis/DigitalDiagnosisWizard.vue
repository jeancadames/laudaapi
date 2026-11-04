<script setup lang="ts">
import { ArrowLeft, ArrowRight, CheckCircle2, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';
import { Textarea } from '@/components/ui/textarea';
import { interpretationBand, type InterpretationBands } from '@/lib/diagnosis-interpretation';
import {
    navigateAfterSave,
    type DiagnosisDraft,
} from '@/lib/diagnosis-persistence';

import {
    capacityScore,
    internalCapacityQuestions,
    maturityDimensions,
    maturityScore,
    urgencyQuestions,
    urgencyScore,
    type DiagnosisQuestion,
} from '@/lib/diagnostico-lauda360-v1';

/**
 * IMPORTANTE
 * Este componente es para una ruta PRIVADA dentro de app.laudaapi.com.
 * La protección real debe vivir en Laravel (auth + verified + membership + diagnosis access).
 * El componente no sustituye el control de acceso del backend.
 */

interface Props {
    bands: InterpretationBands;
    readOnly?: boolean;
    busy?: boolean;
    save: (draft: DiagnosisDraft) => Promise<boolean>;
    submit: (draft: DiagnosisDraft) => Promise<boolean>;
}

const props = withDefaults(defineProps<Props>(), {
    readOnly: false,
    busy: false,
});
const answers = defineModel<Record<string, number>>('answers', {
    required: true,
});
const notes = defineModel<Record<string, string>>('notes', { required: true });
const activeStep = defineModel<number>('step', { required: true });
const pending = ref(false);
const saving = computed(() => pending.value || props.busy);

const steps = computed(() => [
    ...maturityDimensions.map((dimension) => ({
        id: dimension.id,
        title: dimension.shortTitle,
        description: dimension.title,
        questions: dimension.questions,
        kind: 'maturity' as const,
    })),
    {
        id: 'capacity',
        title: 'Capacidad interna',
        description: 'Capacidad real para ejecutar el roadmap',
        questions: internalCapacityQuestions,
        kind: 'capacity' as const,
    },
    {
        id: 'urgency',
        title: 'Urgencia',
        description: 'Presión e impacto para transformar',
        questions: urgencyQuestions,
        kind: 'urgency' as const,
    },
    {
        id: 'review',
        title: 'Revisión',
        description: 'Resultados preliminares y envío',
        questions: [] as DiagnosisQuestion[],
        kind: 'review' as const,
    },
]);

const current = computed(() => steps.value[activeStep.value - 1]);
const isReview = computed(() => current.value?.kind === 'review');
const currentQuestions = computed(() => current.value?.questions ?? []);

const totalScoredQuestions = computed(
    () =>
        maturityDimensions.reduce((sum, d) => sum + d.questions.length, 0) +
        internalCapacityQuestions.length +
        urgencyQuestions.length,
);

const answeredCount = computed(
    () =>
        Object.values(answers.value).filter((value) => Number.isFinite(value))
            .length,
);

const progressValue = computed(() => {
    if (isReview.value) return 100;
    return Math.round((answeredCount.value / totalScoredQuestions.value) * 100);
});

const currentComplete = computed(
    () =>
        isReview.value ||
        currentQuestions.value.every((question) =>
            Number.isFinite(answers.value[question.id]),
        ),
);

const maturity = computed(() => maturityScore(answers.value));
const capacity = computed(() => capacityScore(answers.value));
const urgency = computed(() => urgencyScore(answers.value));

const maturityBand = computed(() => interpretationBand(maturity.value, props.bands.maturity));
const urgencyBand = computed(() => interpretationBand(urgency.value, props.bands.urgency));
const capacityLabel = computed(() => interpretationBand(capacity.value, props.bands.capacity)?.label ?? 'Pendiente de interpretación');

const automaticRecommendation = computed(() => {
    if (urgencyBand.value?.label === 'Crítica') {
        return {
            label: 'Revisión humana prioritaria',
            note: 'La urgencia crítica requiere revisar prioridades, dependencias y capacidad interna antes de definir cómo ejecutar los cambios.',
        };
    }

    return {
        label: capacityLabel.value,
        note: 'Esta lectura describe capacidad interna y nivel de atención recomendado. No define una modalidad comercial ni implica contratación.',
    };
});

async function persist(step = activeStep.value) {
    return props.save({
        answers: { ...answers.value },
        notes: { ...notes.value },
        step,
    });
}

async function navigateTo(step: number | undefined) {
    if (
        step === undefined ||
        saving.value ||
        step < 1 ||
        step > steps.value.length ||
        step === activeStep.value
    )
        return;
    if (props.readOnly) {
        activeStep.value = step;
        return;
    }
    if (step > activeStep.value && !currentComplete.value) return;
    pending.value = true;
    try {
        await navigateAfterSave(
            step,
            () => persist(step),
            (savedStep) => {
                activeStep.value = savedStep;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        );
    } finally {
        pending.value = false;
    }
}

async function next() {
    await navigateTo(activeStep.value + 1);
}
async function previous() {
    await navigateTo(activeStep.value - 1);
}

async function saveAndExit() {
    if (saving.value || props.readOnly) return;
    pending.value = true;
    try {
        await persist();
    } finally {
        pending.value = false;
    }
}

async function submitDiagnosis() {
    if (saving.value || props.readOnly) return;
    pending.value = true;
    try {
        await props.submit({
            answers: { ...answers.value },
            notes: { ...notes.value },
            step: activeStep.value,
        });
    } finally {
        pending.value = false;
    }
}
</script>

<template>
    <div class="mx-auto grid w-full max-w-7xl gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
        <!-- Sidebar / desktop -->
        <aside class="hidden lg:block">
            <div class="sticky top-24 rounded-2xl border bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                    Diagnóstico LAUDA 360
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Paso {{ activeStep }} de {{ steps.length }}
                </p>

                <Stepper :model-value="activeStep" @update:model-value="navigateTo" orientation="vertical" class="mt-5 flex flex-col gap-1">
                    <StepperItem v-for="(step, index) in steps" :key="step.id" :step="index + 1" class="group relative flex items-start gap-3">
                        <div class="flex flex-col items-center">
                            <StepperTrigger :disabled="saving || (index + 1 > activeStep && !readOnly)">
                                <StepperIndicator class="h-8 w-8 text-xs">
                                    {{ index + 1 }}
                                </StepperIndicator>
                            </StepperTrigger>
                            <StepperSeparator v-if="index < steps.length - 1" class="my-1 h-8 w-px bg-muted group-data-[state=completed]:bg-primary" />
                        </div>

                        <div class="pb-4 pt-1">
                            <StepperTitle class="text-sm">
                                {{ step.title }}
                            </StepperTitle>
                            <StepperDescription class="mt-0.5 text-xs leading-5">
                                {{ step.description }}
                            </StepperDescription>
                        </div>
                    </StepperItem>
                </Stepper>
            </div>
        </aside>

        <!-- Main -->
        <main class="min-w-0">
            <!-- Mobile progress -->
            <div class="mb-4 rounded-2xl border bg-card p-4 lg:hidden">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            Diagnóstico LAUDA 360
                        </p>
                        <p class="mt-1 text-sm font-semibold">
                            {{ current?.title }} · {{ activeStep }}/{{ steps.length }}
                        </p>
                    </div>
                    <span class="text-sm font-bold">{{ progressValue }}%</span>
                </div>
                <Progress :model-value="progressValue" class="mt-3 h-2" />
            </div>

            <Card>
                <CardHeader>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <CardTitle>{{ current?.description }}</CardTitle>
                            <CardDescription v-if="!isReview" class="mt-2 max-w-3xl">
                                Responda según la situación actual de la empresa, no según lo que está planificado implementar.
                                Cuando sea posible, utilice evidencia verificable.
                            </CardDescription>
                        </div>
                        <div class="hidden min-w-40 lg:block">
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Avance</span>
                                <span>{{ progressValue }}%</span>
                            </div>
                            <Progress :model-value="progressValue" class="mt-2 h-2" />
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div v-if="!isReview" class="space-y-5">
                        <section v-for="(question, index) in currentQuestions" :key="question.id" class="rounded-2xl border p-4 sm:p-5">
                            <div class="flex gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-muted text-xs font-bold">
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <Label class="text-sm font-semibold leading-6 sm:text-base">
                                        {{ question.text }}
                                    </Label>
                                    <p v-if="question.help" class="mt-1 text-xs leading-5 text-muted-foreground">
                                        {{ question.help }}
                                    </p>
                                    <p v-if="question.evidence" class="mt-2 text-xs leading-5 text-muted-foreground">
                                        <span class="font-semibold text-foreground">Evidencia sugerida:</span>
                                        {{ question.evidence }}
                                    </p>
                                </div>
                            </div>

                            <RadioGroup :model-value="answers[ question.id ] ? String(answers[ question.id ]) : undefined" class="mt-4 grid gap-2" :disabled="readOnly || saving" @update:model-value="(value) => answers[ question.id ] = Number(value)">
                                <Label v-for="choice in question.choices" :key="choice.score" :for="`${question.id}-${choice.score}`" class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm font-normal leading-5 transition hover:bg-muted/50" :class="answers[ question.id ] === choice.score && 'border-primary bg-primary/5'">
                                    <RadioGroupItem :id="`${question.id}-${choice.score}`" :value="String(choice.score)" class="mt-0.5" />
                                    <span>
                                        <strong class="mr-1">{{ choice.score }}.</strong>
                                        {{ choice.label }}
                                    </span>
                                </Label>
                            </RadioGroup>

                            <div class="mt-4">
                                <Label :for="`${question.id}-note`" class="text-xs font-medium text-muted-foreground">
                                    Nota o evidencia adicional (opcional)
                                </Label>
                                <Textarea :id="`${question.id}-note`" v-model="notes[ question.id ]" :disabled="readOnly || saving" rows="2" class="mt-2 resize-none" placeholder="Contexto, evidencia o aclaración para esta respuesta." />
                            </div>
                        </section>
                    </div>

                    <div v-else class="space-y-5">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="rounded-2xl border p-5">
                                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Madurez digital</p>
                                <p class="mt-2 text-3xl font-black">{{ maturity }}/100</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ maturityBand?.label }}</p>
                            </div>
                            <div class="rounded-2xl border p-5">
                                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Capacidad interna</p>
                                <p class="mt-2 text-3xl font-black">{{ capacity }}/100</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ capacityLabel }}</p>
                            </div>
                            <div class="rounded-2xl border p-5">
                                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Urgencia</p>
                                <p class="mt-2 text-3xl font-black">{{ urgency }}/100</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ urgencyBand?.label }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-primary/20 bg-primary/5 p-5">
                            <div class="flex items-start gap-3">
                                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                                <div>
                                    <p class="font-bold">Lectura preliminar de capacidad</p>
                                    <p class="mt-1 text-lg font-black">{{ automaticRecommendation.label }}</p>
                                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                        {{ automaticRecommendation.note }}
                                    </p>
                                    <p class="mt-3 text-xs leading-5 text-muted-foreground">
                                        El resultado es preliminar. LAUDA revisará evidencia,
                                        riesgos, complejidad e integraciones antes de publicar el diagnóstico oficial.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex gap-2">
                            <Button variant="outline" :disabled="saving || activeStep === 1" @click="previous">
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Anterior
                            </Button>
                            <Button v-if="!readOnly" variant="ghost" :disabled="saving" @click="saveAndExit">
                                <Save class="mr-2 h-4 w-4" />
                                {{ saving ? 'Guardando…' : 'Guardar' }}
                            </Button>
                        </div>

                        <Button v-if="!isReview" :disabled="saving || (!readOnly && !currentComplete)" @click="next">
                            Continuar
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>

                        <Button v-else-if="!readOnly" :disabled="saving" @click="submitDiagnosis">
                            Enviar diagnóstico
                            <CheckCircle2 class="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </main>
    </div>
</template>
