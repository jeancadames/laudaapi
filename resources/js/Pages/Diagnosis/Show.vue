<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowLeft, Building2, CheckCircle2, Clock3, ShieldCheck } from 'lucide-vue-next'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import DigitalDiagnosisWizard from './DigitalDiagnosisWizard.vue'

interface Assessment {
  id: number
  status: 'draft' | 'in_progress' | 'submitted' | 'reviewed' | string
  current_step: number
  answers: Record<string, number>
  notes: Record<string, string>
  started_at?: string | null
  submitted_at?: string | null
  updated_at?: string | null
}

interface Organization {
  id: number | string
  name: string
}

interface Endpoints {
  update: string
  submit: string
  back: string
}

interface Props {
  assessment: Assessment
  organization: Organization
  endpoints: Endpoints
}

const props = defineProps<Props>()

const saveStatus = ref<'idle' | 'saving' | 'saved' | 'error'>('idle')
const submitError = ref<string | null>(null)

const isReadOnly = computed(() =>
  ['submitted', 'reviewed'].includes(props.assessment.status),
)

const statusLabel = computed(() => {
  switch (props.assessment.status) {
    case 'draft':
      return 'Pendiente de iniciar'
    case 'in_progress':
      return 'En progreso'
    case 'submitted':
      return 'Enviado a LAUDA'
    case 'reviewed':
      return 'Revisado'
    default:
      return props.assessment.status
  }
})

const saveStatusLabel = computed(() => {
  if (saveStatus.value === 'saving') return 'Guardando…'
  if (saveStatus.value === 'saved') return 'Cambios guardados'
  if (saveStatus.value === 'error') return 'No se pudo guardar'
  return null
})

function saveDiagnosis(payload: {
  answers: Record<string, number>
  notes: Record<string, string>
  step: number
}) {
  if (isReadOnly.value) return

  saveStatus.value = 'saving'

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
        saveStatus.value = 'saved'
      },
      onError: () => {
        saveStatus.value = 'error'
      },
    },
  )
}

function submitDiagnosis(payload: {
  answers: Record<string, number>
  notes: Record<string, string>
  maturityScore: number
  capacityScore: number
  urgencyScore: number
}) {
  if (isReadOnly.value) return

  submitError.value = null

  // IMPORTANTE:
  // Los scores calculados por Vue son únicamente una vista preliminar.
  // No se envían al backend. Laravel debe recalcular el resultado oficial.
  router.post(
    props.endpoints.submit,
    {
      answers: payload.answers,
      notes: payload.notes,
    },
    {
      preserveScroll: true,
      onError: () => {
        submitError.value = 'No pudimos enviar el diagnóstico. Revise las respuestas e inténtelo nuevamente.'
      },
    },
  )
}
</script>

<template>
  <Head title="Diagnóstico LAUDA 360" />

  <div class="min-h-screen bg-muted/20">
    <header class="border-b bg-background">
      <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div class="flex min-w-0 items-start gap-3">
          <Button as-child variant="ghost" size="icon" class="mt-0.5 shrink-0">
            <Link :href="endpoints.back" aria-label="Volver al panel">
              <ArrowLeft class="h-4 w-4" />
            </Link>
          </Button>

          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                LAUDA Transformación Digital 360
              </p>
              <Badge variant="outline">
                {{ statusLabel }}
              </Badge>
            </div>

            <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">
              Diagnóstico Digital 360
            </h1>

            <div class="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
              <Building2 class="h-4 w-4 shrink-0" />
              <span class="truncate">{{ organization.name }}</span>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
          <div class="flex items-center gap-1.5">
            <ShieldCheck class="h-4 w-4" />
            <span>Acceso privado</span>
          </div>

          <div v-if="saveStatusLabel" class="flex items-center gap-1.5">
            <CheckCircle2 v-if="saveStatus === 'saved'" class="h-4 w-4" />
            <Clock3 v-else class="h-4 w-4" />
            <span>{{ saveStatusLabel }}</span>
          </div>
        </div>
      </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <Card v-if="isReadOnly" class="mb-6 border-primary/20 bg-primary/5">
        <CardContent class="flex items-start gap-3 p-4 sm:p-5">
          <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
          <div>
            <p class="font-semibold">Diagnóstico enviado</p>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">
              Las respuestas están bloqueadas mientras LAUDA revisa el diagnóstico y prepara las conclusiones,
              prioridades y modalidad de acompañamiento recomendada.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-if="submitError" class="mb-6 border-destructive/30 bg-destructive/5">
        <CardContent class="p-4 text-sm text-destructive sm:p-5">
          {{ submitError }}
        </CardContent>
      </Card>

      <DigitalDiagnosisWizard
        :initial-answers="assessment.answers ?? {}"
        :initial-notes="assessment.notes ?? {}"
        :initial-step="assessment.current_step ?? 1"
        :read-only="isReadOnly"
        @save="saveDiagnosis"
        @submit="submitDiagnosis"
      />
    </main>
  </div>
</template>
