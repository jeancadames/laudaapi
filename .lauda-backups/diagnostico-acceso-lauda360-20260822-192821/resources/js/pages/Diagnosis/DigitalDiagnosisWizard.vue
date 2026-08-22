<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ArrowLeft, ArrowRight, CheckCircle2, Save } from 'lucide-vue-next'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Progress } from '@/components/ui/progress'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Textarea } from '@/components/ui/textarea'
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper'

import {
    capacityScore,
    internalCapacityQuestions,
    maturityDimensions,
    maturityScore,
    recommendationRules,
    urgencyQuestions,
    urgencyScore,
    type DiagnosisQuestion,
} from '@/lib/diagnostico-lauda360-v1'

/**
 * IMPORTANTE
 * Este componente es para una ruta PRIVADA dentro de app.laudaapi.com.
 * La protección real debe vivir en Laravel (auth + verified + membership + diagnosis access).
 * El componente no sustituye el control de acceso del backend.
 */

interface Props {
    initialAnswers?: Record<string, number>
    initialNotes?: Record<string, string>
    initialStep?: number
    readOnly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    initialAnswers: () => ({}),
    initialNotes: () => ({}),
    initialStep: 1,
    readOnly: false,
})

const emit = defineEmits<{
    save: [ payload: { answers: Record<string, number>; notes: Record<string, string>; step: number } ]
    submit: [ payload: {
        answers: Record<string, number>
        notes: Record<string, string>
        maturityScore: number
        capacityScore: number
        urgencyScore: number
    } ]
}>()

const answers = reactive<Record<string, number>>({ ...props.initialAnswers })
const notes = reactive<Record<string, string>>({ ...props.initialNotes })
const activeStep = ref(Math.max(1, props.initialStep))
const saving = ref(false)

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
])

const current = computed(() => steps.value[ activeStep.value - 1 ])
const isReview = computed(() => current.value?.kind === 'review')
const currentQuestions = computed(() => current.value?.questions ?? [])

const totalScoredQuestions = computed(() =>
    maturityDimensions.reduce((sum, d) => sum + d.questions.length, 0)
    + internalCapacityQuestions.length
    + urgencyQuestions.length,
)

const answeredCount = computed(() =>
    Object.values(answers).filter((value) => Number.isFinite(value)).length,
)

const progressValue = computed(() => {
    if (isReview.value) return 100
    return Math.round((answeredCount.value / totalScoredQuestions.value) * 100)
})

const currentComplete = computed(() =>
    isReview.value || currentQuestions.value.every((question) => Number.isFinite(answers[ question.id ])),
)

const maturity = computed(() => maturityScore(answers))
const capacity = computed(() => capacityScore(answers))
const urgency = computed(() => urgencyScore(answers))

function band<T extends { min: number; max: number }>(value: number, options: T[]): T | undefined {
    return options.find((option) => value >= option.min && value <= option.max)
}

const maturityBand = computed(() => band(maturity.value, recommendationRules.maturityLevels))
const capacityBand = computed(() => band(capacity.value, recommendationRules.capacityRecommendation))
const urgencyBand = computed(() => band(urgency.value, recommendationRules.urgencyLevels))

const automaticRecommendation = computed(() => {
    // Seguridad comercial: una urgencia crítica debe pasar por revisión humana
    // y nunca salir automáticamente como Guiado.
    if (urgencyBand.value?.label === 'Crítica' && capacityBand.value?.modality === 'guided') {
        return {
            label: 'LAUDA 360 Asistido — revisión recomendada',
            note: 'La capacidad interna es alta, pero la urgencia crítica aconseja acompañamiento activo durante la etapa inicial.',
        }
    }

    return {
        label: capacityBand.value?.label ?? 'Pendiente de evaluación',
        note: capacityBand.value?.note ?? 'Complete la evaluación para obtener una recomendación preliminar.',
    }
})

async function persist() {
    saving.value = true
    try {
        emit('save', {
            answers: { ...answers },
            notes: { ...notes },
            step: activeStep.value,
        })
    } finally {
        saving.value = false
    }
}

async function next() {
    if (!currentComplete.value || activeStep.value >= steps.value.length) return
    await persist()
    activeStep.value += 1
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function previous() {
    if (activeStep.value <= 1) return
    await persist()
    activeStep.value -= 1
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function saveAndExit() {
    await persist()
}

function submitDiagnosis() {
    emit('submit', {
        answers: { ...answers },
        notes: { ...notes },
        maturityScore: maturity.value,
        capacityScore: capacity.value,
        urgencyScore: urgency.value,
    })
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

                <Stepper v-model="activeStep" orientation="vertical" class="mt-5 flex flex-col gap-1">
                    <StepperItem v-for="(step, index) in steps" :key="step.id" :step="index + 1" class="group relative flex items-start gap-3">
                        <div class="flex flex-col items-center">
                            <StepperTrigger :disabled="index + 1 > activeStep && !readOnly">
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

                            <RadioGroup :model-value="answers[ question.id ] ? String(answers[ question.id ]) : undefined" class="mt-4 grid gap-2" :disabled="readOnly" @update:model-value="(value) => answers[ question.id ] = Number(value)">
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
                                <Textarea :id="`${question.id}-note`" v-model="notes[ question.id ]" :disabled="readOnly" rows="2" class="mt-2 resize-none" placeholder="Contexto, evidencia o aclaración para esta respuesta." />
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
                                <p class="mt-1 text-sm text-muted-foreground">{{ capacityBand?.label }}</p>
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
                                    <p class="font-bold">Recomendación preliminar</p>
                                    <p class="mt-1 text-lg font-black">{{ automaticRecommendation.label }}</p>
                                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                        {{ automaticRecommendation.note }}
                                    </p>
                                    <p class="mt-3 text-xs leading-5 text-muted-foreground">
                                        El resultado es preliminar. LAUDA puede ajustar la recomendación después de revisar evidencia,
                                        riesgos, complejidad e integraciones requeridas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex gap-2">
                            <Button variant="outline" :disabled="activeStep === 1" @click="previous">
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Anterior
                            </Button>
                            <Button v-if="!readOnly" variant="ghost" :disabled="saving" @click="saveAndExit">
                                <Save class="mr-2 h-4 w-4" />
                                {{ saving ? 'Guardando…' : 'Guardar' }}
                            </Button>
                        </div>

                        <Button v-if="!isReview" :disabled="!currentComplete" @click="next">
                            Continuar
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Button>

                        <Button v-else-if="!readOnly" @click="submitDiagnosis">
                            Enviar diagnóstico
                            <CheckCircle2 class="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </main>
    </div>
</template>
