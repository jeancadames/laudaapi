<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

type DgiiCertFileItem = {
  name: string
  rel_path: string
  size: number
  last_modified_at: string
  download_url: string
}

const props = withDefaults(defineProps<{
  companyId: number
  companyName?: string | null
  /**
   * Si lo pasas, el componente solo carga lista cuando active=true.
   * Útil para tabs (no spamear).
   */
  active?: boolean
  maxBytes?: number
  uploadUrl?: string
  listUrl?: string
}>(), {
  maxBytes: 10 * 1024 * 1024,
  uploadUrl: '/erp/services/certificacion-emisor/set-ecf/dgii-cert/upload',
  listUrl: '/erp/services/certificacion-emisor/set-ecf/dgii-cert/list',
})

const inputRef = ref<HTMLInputElement | null>(null)
const dragOver = ref(false)

const file = ref<File | null>(null)

const stage = ref<'idle' | 'uploading' | 'done'>('idle')
const uploadProgress = ref(0)

const isBusy = computed(() => stage.value === 'uploading')

const successMsg = ref<string | null>(null)
const errorMsg = ref<string | null>(null)
const detailsMsg = ref<string | null>(null)

const dgiiCertItems = ref<DgiiCertFileItem[]>([])
const dgiiCertLoading = ref(false)

const MAX_BYTES = computed(() => props.maxBytes)

const stageLabel = computed(() => {
  if (stage.value === 'uploading') return 'Subiendo certificado…'
  if (stage.value === 'done') return 'Completado'
  return 'Listo'
})

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null
}

function humanBytes(bytes: number) {
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let v = bytes
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++ }
  return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

function resetAll() {
  file.value = null
  uploadProgress.value = 0
  stage.value = 'idle'
  successMsg.value = null
  errorMsg.value = null
  detailsMsg.value = null
  dragOver.value = false
  if (inputRef.value) inputRef.value.value = ''
}

function openPicker() {
  if (isBusy.value) return
  inputRef.value?.click()
}

function setFile(f: File | null) {
  successMsg.value = null
  errorMsg.value = null
  detailsMsg.value = null

  if (!f) { file.value = null; return }

  if (f.size > MAX_BYTES.value) {
    file.value = null
    errorMsg.value = `El archivo excede el máximo (${Math.round(MAX_BYTES.value / 1024 / 1024)}MB).`
    detailsMsg.value = `Tu archivo pesa ${humanBytes(f.size)}.`
    return
  }

  // validación rápida por extensión (el back valida de verdad)
const okExt = /\.(p12|pfx|cer|crt)$/i.test(f.name)
  if (!okExt) {
    file.value = null
    errorMsg.value = 'Formato no permitido.'
    detailsMsg.value = 'Usa .cer o .crt.'
    return
  }

  file.value = f
}

function onInputChange(e: Event) {
  const input = e.target as HTMLInputElement
  setFile(input.files?.[0] ?? null)
}

function onDragOver(e: DragEvent) {
  e.preventDefault()
  if (isBusy.value) return
  dragOver.value = true
}
function onDragLeave(e: DragEvent) {
  e.preventDefault()
  dragOver.value = false
}
function onDrop(e: DragEvent) {
  e.preventDefault()
  dragOver.value = false
  if (isBusy.value) return
  setFile(e.dataTransfer?.files?.[0] ?? null)
}

async function fetchDgiiCerts() {
  dgiiCertLoading.value = true
  try {
    const res = await axios.get(props.listUrl, {
      params: { company_id: props.companyId },
    })
    if (!res.data?.ok) throw new Error(res.data?.message ?? 'No se pudo listar certificados.')
    dgiiCertItems.value = (res.data?.items ?? []) as DgiiCertFileItem[]
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message || e?.message || 'Error listando certificados.'
  } finally {
    dgiiCertLoading.value = false
  }
}

async function submit() {
  errorMsg.value = null
  successMsg.value = null
  detailsMsg.value = null

  if (!file.value) {
    errorMsg.value = 'Selecciona un certificado primero.'
    return
  }

  stage.value = 'uploading'
  uploadProgress.value = 0

  try {
    const token = getCsrfToken()
    const fd = new FormData()
    fd.append('file', file.value)
    fd.append('company_id', String(props.companyId))

    const res = await axios.post(props.uploadUrl, fd, {
      headers: {
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (pe) => {
        if (!pe.total) return
        uploadProgress.value = Math.min(100, Math.round((pe.loaded / pe.total) * 100))
      },
    })

    if (!res.data?.ok) throw new Error(res.data?.message ?? 'No se pudo subir el certificado.')

    stage.value = 'done'
    successMsg.value = '✅ Certificado subido correctamente.'
    detailsMsg.value = 'Se reemplazó el certificado anterior para esta empresa.'

    await fetchDgiiCerts()

    file.value = null
    if (inputRef.value) inputRef.value.value = ''
  } catch (e: any) {
    stage.value = 'idle'
    uploadProgress.value = 0
    errorMsg.value = e?.response?.data?.message || e?.message || 'Error subiendo certificado.'
  } finally {
    if (stage.value !== 'done') stage.value = 'idle'
  }
}

// Carga controlada por tabs
watch(
  () => props.active,
  (v) => {
    // si active viene definido, solo cargar al activarse
    if (v === true) fetchDgiiCerts()
  },
  { immediate: true }
)

// Si NO pasas active, carga al montar
onMounted(() => {
  if (typeof props.active === 'undefined') fetchDgiiCerts()
})
</script>

<template>
  <div class="grid place-items-center py-2">
    <div class="w-full bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-sm py-6">

      <!-- Header (CardHeader style) -->
      <div class="px-6 pb-6 border-b @container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5">
        <h3 class="leading-none font-semibold tracking-tight">
          Certificado DGII — Empresa {{ companyName ?? '—' }}
        </h3>
        <p class="text-sm text-muted-foreground">
          Sube tu certificado digital para procesos tributarios. Al subirlo, el sistema <strong>reemplaza</strong> el certificado actual.
        </p>
      </div>

      <!-- Field head -->
      <div class="px-6 space-y-1">
        <label class="block text-sm font-semibold">
          Archivo de certificado DGII
        </label>
        <p class="text-sm text-muted-foreground">
          Formatos: <strong>.cer</strong>, <strong>.crt</strong>.
          Máximo <strong>{{ Math.round(MAX_BYTES / 1024 / 1024) }}MB</strong>.
        </p>
      </div>

      <!-- Dropzone -->
      <div
        class="mx-6 rounded-xl border border-dashed bg-muted/30 p-4 transition"
        :class="[
          dragOver ? 'border-ring ring-2 ring-ring/25 bg-muted/50' : 'border-border',
          isBusy ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer hover:bg-muted/40'
        ]"
        @click="openPicker"
        @drop.prevent="onDrop"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
      >
        <input
          ref="inputRef"
          type="file"
          class="hidden"
          accept=".cer,.crt"
          @change="onInputChange"
        />

        <div class="flex items-center gap-4">
          <!-- Icon chip -->
          <div class="h-12 w-12 rounded-xl border bg-background grid place-items-center text-muted-foreground">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" class="opacity-90">
              <path d="M12 3v10m0 0l-4-4m4 4l4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 14v4a3 3 0 003 3h10a3 3 0 003-3v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>

          <!-- Text -->
          <div class="flex-1 min-w-0">
            <div class="font-semibold leading-none">
              <span v-if="!file">Suelta tu certificado aquí</span>
              <span v-else>Certificado seleccionado</span>
            </div>

            <div class="mt-1 text-sm text-muted-foreground truncate">
              <span v-if="!file">.cer, .crt (máx {{ Math.round(MAX_BYTES/1024/1024) }}MB)</span>
              <span v-else>
                <strong class="text-foreground">{{ file.name }}</strong>
                <span class="text-muted-foreground"> — {{ humanBytes(file.size) }}</span>
              </span>
            </div>
          </div>

          <!-- Ghost button -->
          <button
            class="inline-flex items-center justify-center rounded-md border bg-background px-3 py-2 text-sm font-medium shadow-sm
                   hover:bg-accent hover:text-accent-foreground disabled:opacity-50 disabled:pointer-events-none"
            type="button"
            :disabled="isBusy"
            @click.stop="openPicker"
          >
            Elegir archivo
          </button>
        </div>
      </div>

      <!-- Actions -->
      <div class="px-6 flex items-center gap-3">
        <button
          class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold
                 text-primary-foreground shadow-sm hover:bg-primary/90 disabled:opacity-50 disabled:pointer-events-none"
          type="button"
          :disabled="!file || isBusy"
          @click="submit"
        >
          <span v-if="!isBusy">Subir y reemplazar certificado</span>

          <span v-else class="inline-flex items-center gap-2">
            <span class="inline-flex items-center gap-1">
              <span class="h-1.5 w-1.5 rounded-full bg-primary-foreground/90 animate-bounce"></span>
              <span class="h-1.5 w-1.5 rounded-full bg-primary-foreground/90 animate-bounce" style="animation-delay:120ms"></span>
              <span class="h-1.5 w-1.5 rounded-full bg-primary-foreground/90 animate-bounce" style="animation-delay:240ms"></span>
            </span>
            {{ stageLabel }}
          </span>
        </button>

        <button
          class="inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-semibold
                 text-muted-foreground hover:text-foreground hover:bg-accent disabled:opacity-50 disabled:pointer-events-none"
          type="button"
          :disabled="isBusy"
          @click="resetAll"
        >
          Limpiar
        </button>
      </div>

      <!-- Progress -->
      <div
        v-if="stage === 'uploading'"
        class="mx-6 rounded-xl border bg-muted/30 p-4 space-y-3"
      >
        <div class="flex items-center justify-between gap-3 text-sm">
          <div class="text-muted-foreground">Estado:</div>
          <div class="font-semibold">{{ stageLabel }}</div>
        </div>

        <div class="flex items-center justify-between gap-3 text-sm">
          <div class="text-muted-foreground">Subida:</div>
          <div class="font-semibold">{{ uploadProgress }}%</div>
        </div>

        <div class="h-2.5 w-full rounded-full bg-border/70 overflow-hidden">
          <div
            class="h-full bg-primary transition-[width] duration-200"
            :style="{ width: uploadProgress + '%' }"
          />
        </div>
      </div>

      <!-- Messages -->
      <div v-if="successMsg" class="mx-6 rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-4">
        <div class="font-semibold text-emerald-700 dark:text-emerald-300">
          {{ successMsg }}
        </div>
        <div v-if="detailsMsg" class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-200/80">
          {{ detailsMsg }}
        </div>
      </div>

      <div v-if="errorMsg" class="mx-6 rounded-xl border border-destructive/25 bg-destructive/10 p-4">
        <div class="font-semibold text-destructive">
          ⚠️ {{ errorMsg }}
        </div>
        <div v-if="detailsMsg" class="mt-1 text-sm text-muted-foreground">
          {{ detailsMsg }}
        </div>
      </div>

      <!-- Current cert list -->
      <div class="mx-6 rounded-xl border overflow-hidden">
        <div class="px-6 py-4 border-b flex items-center justify-between gap-2">
          <div class="text-sm font-semibold">Certificado actual (carpeta de empresa)</div>
          <button
            class="inline-flex items-center justify-center rounded-md border bg-background px-3 py-2 text-sm font-medium shadow-sm
                   hover:bg-accent hover:text-accent-foreground disabled:opacity-50 disabled:pointer-events-none"
            type="button"
            :disabled="dgiiCertLoading || isBusy"
            @click="fetchDgiiCerts"
          >
            {{ dgiiCertLoading ? 'Cargando…' : 'Refrescar' }}
          </button>
        </div>

        <div class="px-6 py-4" v-if="dgiiCertItems.length === 0">
          <div class="text-sm text-muted-foreground">No hay certificados cargados todavía.</div>
        </div>

        <div class="px-6 py-4 space-y-3" v-else>
          <div v-for="it in dgiiCertItems" :key="it.rel_path" class="rounded-lg border p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div class="min-w-0">
                <div class="font-semibold truncate">{{ it.name }}</div>
                <div class="text-xs text-muted-foreground font-mono break-all">{{ it.rel_path }}</div>
                <div class="mt-1 text-sm text-muted-foreground">
                  {{ humanBytes(it.size) }} • {{ new Date(it.last_modified_at).toLocaleString() }}
                </div>
              </div>

              <a
                class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold
                       text-primary-foreground shadow-sm hover:bg-primary/90"
                :href="it.download_url"
              >
                Descargar
              </a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>