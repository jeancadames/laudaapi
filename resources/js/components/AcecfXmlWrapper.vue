<script setup>
import axios from 'axios'
import { computed, ref } from 'vue'

const MAX_BYTES = 1024 * 1024 * 100 // 100MB
const ALLOWED_EXT = ['xlsx', 'xls', 'csv']

const inputRef = ref(null)
const file = ref(null)
const dragOver = ref(false)

const stage = ref('idle') // idle | uploading | converting | downloading | done | error
const uploadProgress = ref(0)
const downloadProgress = ref(0)

const successMsg = ref('')
const errorMsg = ref('')
const detailsMsg = ref('')

const isBusy = computed(() => ['uploading', 'converting', 'downloading'].includes(stage.value))

const stageLabel = computed(() => {
  switch (stage.value) {
    case 'uploading': return 'Subiendo archivo...'
    case 'converting': return 'Generando XML ACECF y armando ZIP...'
    case 'downloading': return 'Descargando ZIP...'
    case 'done': return 'Listo'
    case 'error': return 'Error'
    default: return 'Listo para cargar'
  }
})

function humanBytes(bytes) {
  if (!bytes && bytes !== 0) return ''
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let n = bytes
  while (n >= 1024 && i < units.length - 1) {
    n /= 1024
    i++
  }
  return `${n.toFixed(i === 0 ? 0 : 2)} ${units[i]}`
}

function resetMessages() {
  successMsg.value = ''
  errorMsg.value = ''
  detailsMsg.value = ''
}

function resetAll() {
  file.value = null
  stage.value = 'idle'
  uploadProgress.value = 0
  downloadProgress.value = 0
  resetMessages()
  if (inputRef.value) inputRef.value.value = ''
}

function openPicker() {
  if (isBusy.value) return
  inputRef.value?.click()
}

function setFile(f) {
  resetMessages()
  if (!f) return

  const name = f.name || ''
  const ext = name.split('.').pop()?.toLowerCase() || ''
  if (!ALLOWED_EXT.includes(ext)) {
    stage.value = 'error'
    errorMsg.value = 'Tipo de archivo no permitido.'
    detailsMsg.value = `Solo se aceptan: ${ALLOWED_EXT.join(', ')}`
    return
  }

  if (f.size > MAX_BYTES) {
    stage.value = 'error'
    errorMsg.value = 'El archivo es demasiado grande.'
    detailsMsg.value = `Tamaño máximo permitido: ${humanBytes(MAX_BYTES)}`
    return
  }

  file.value = f
  stage.value = 'idle'
}

function onInputChange(e) {
  const f = e.target.files?.[0] ?? null
  setFile(f)
}

function onDrop(e) {
  dragOver.value = false
  if (isBusy.value) return
  const f = e.dataTransfer?.files?.[0] ?? null
  setFile(f)
}

function onDragOver(e) {
  if (isBusy.value) return
  e.preventDefault()
  dragOver.value = true
}

function onDragLeave() {
  dragOver.value = false
}

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null
}

function extractBackendError(err) {
  const status = err?.response?.status
  const data = err?.response?.data

  let title = 'Ocurrió un error.'
  let details = ''

  if (status === 419) {
    title = 'CSRF inválido (419).'
    details = 'Asegura que tu layout tenga <meta name="csrf-token" ...> y que estés en la misma sesión.'
    return { title, details }
  }

  if (status === 422) {
    title = 'Validación falló (422).'
    if (data?.errors) {
      const firstKey = Object.keys(data.errors)[0]
      const firstMsg = Array.isArray(data.errors[firstKey]) ? data.errors[firstKey][0] : String(data.errors[firstKey])
      details = firstMsg
    } else {
      details = 'Revisa que el archivo sea xlsx/xls/csv y cumpla el tamaño permitido.'
    }
    return { title, details }
  }

  if (status === 413) {
    title = 'Archivo demasiado grande (413).'
    details = 'El servidor rechazó el tamaño. Revisa límites de Nginx/Apache + PHP upload_max_filesize/post_max_size.'
    return { title, details }
  }

  if (data?.message) {
    title = data.message
    if (data?.error) details = String(data.error)
    return { title, details }
  }

  title = `Error al procesar (status ${status ?? 'desconocido'}).`
  details = 'Revisa el log del servidor para ver el detalle.'
  return { title, details }
}

async function downloadZip(downloadUrl) {
  stage.value = 'downloading'
  downloadProgress.value = 0

  const token = getCsrfToken()

  const res = await axios.get(downloadUrl, {
    responseType: 'blob',
    headers: { ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
    onDownloadProgress: (evt) => {
      if (evt.total) downloadProgress.value = Math.round((evt.loaded / evt.total) * 100)
      else downloadProgress.value = Math.min(95, downloadProgress.value + 2)
    },
  })

  const cd = res.headers?.['content-disposition'] || res.headers?.['Content-Disposition']
  let filename = 'acecf.zip'
  if (cd && typeof cd === 'string') {
    const m = cd.match(/filename\*=UTF-8''([^;]+)|filename="([^"]+)"/i)
    const raw = m?.[1] || m?.[2]
    if (raw) filename = decodeURIComponent(raw)
  }

  const blob = new Blob([res.data], { type: 'application/zip' })
  const url = window.URL.createObjectURL(blob)

  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()

  window.URL.revokeObjectURL(url)
  downloadProgress.value = 100
}

async function submit() {
  resetMessages()
  if (!file.value || isBusy.value) return

  stage.value = 'uploading'
  uploadProgress.value = 0
  downloadProgress.value = 0

  try {
    const fd = new FormData()
    fd.append('file', file.value)

    const token = getCsrfToken()

    const res = await axios.post('/erp/services/certificacion-emisor/set-ecf/acecf/excel-to-xml', fd, {
      headers: {
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (evt) => {
        if (evt.total) uploadProgress.value = Math.round((evt.loaded / evt.total) * 100)
        else uploadProgress.value = Math.min(95, uploadProgress.value + 3)
      },
      timeout: 0,
    })

    uploadProgress.value = 100
    stage.value = 'converting'

    const downloadUrl = res.data?.download_url
    if (!downloadUrl) {
      stage.value = 'error'
      errorMsg.value = 'El servidor no devolvió download_url.'
      detailsMsg.value = 'Revisa el controller: debe devolver JSON con { download_url }.'
      return
    }

    await downloadZip(downloadUrl)

    stage.value = 'done'
    successMsg.value = '✅ ACECF: ZIP generado y descargado correctamente.'
    detailsMsg.value = `${file.value.name} → acecf.zip`
  } catch (err) {
    stage.value = 'error'
    const { title, details } = extractBackendError(err)
    errorMsg.value = title
    detailsMsg.value = details
    console.error(err)
  }
}
</script>

<template>
  <div class="grid place-items-center py-4">
    <div class="w-full bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-sm py-6">

      <!-- Header -->
      <div class="px-6 pb-6 border-b @container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5">
        <h3 class="leading-none font-semibold tracking-tight">
          Wrapper ACECF (Aprobación Comercial e-CF)
        </h3>
        <p class="text-sm text-muted-foreground">
          Sube un Excel con múltiples filas y se generará un XML ACECF por registro (según el XSD de la DGII),
          descargando un ZIP con todos los XML.
        </p>
      </div>

      <!-- Label + help -->
      <div class="px-6 space-y-1">
        <label class="block text-sm font-semibold">
          Archivo de ACECF (.xlsx / .xls / .csv)
        </label>
        <p class="text-sm leading-relaxed text-muted-foreground">
          Cada fila se convierte en un XML dentro de
          <span class="font-semibold text-foreground">
            &lt;ACECF&gt;&lt;DetalleAprobacionComercial&gt;...&lt;/DetalleAprobacionComercial&gt;&lt;/ACECF&gt;
          </span>.
          Máximo {{ Math.round(MAX_BYTES / 1024 / 1024) }}MB.
        </p>
      </div>

      <!-- Dropzone -->
      <div
        class="mx-6 rounded-xl border border-dashed bg-muted/30 p-4 transition"
        :class="[
          dragOver ? 'border-ring ring-2 ring-ring/25 bg-muted/50' : 'border-border',
          isBusy ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-muted/40'
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
          accept=".xlsx,.xls,.csv"
          @change="onInputChange"
        />

        <div class="flex items-center gap-4">
          <!-- Icon chip -->
          <div class="h-14 w-14 rounded-xl border bg-background grid place-items-center text-muted-foreground">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="opacity-90">
              <path d="M12 3v10m0 0l-4-4m4 4l4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 14v4a3 3 0 003 3h10a3 3 0 003-3v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>

          <!-- Text -->
          <div class="min-w-0 flex-1">
            <div class="font-semibold leading-none">
              <span v-if="!file">Suelta tu archivo aquí</span>
              <span v-else>Archivo seleccionado</span>
            </div>

            <div class="mt-1 text-sm text-muted-foreground truncate">
              <span v-if="!file">.xlsx, .xls, .csv (máx {{ Math.round(MAX_BYTES/1024/1024) }}MB)</span>
              <span v-else>
                <span class="font-semibold text-foreground">{{ file.name }}</span>
                <span class="text-muted-foreground"> — {{ humanBytes(file.size) }}</span>
              </span>
            </div>
          </div>

          <!-- Ghost button -->
          <button
            class="inline-flex items-center justify-center rounded-md border bg-background px-3 py-2 text-sm font-medium shadow-sm
                   hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-60"
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
                 text-primary-foreground shadow-sm hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
          type="button"
          :disabled="!file || isBusy"
          @click="submit"
        >
          <span v-if="!isBusy">Convertir y descargar ZIP</span>
          <span v-else class="inline-flex items-center gap-2">
            <span class="inline-flex items-center gap-1">
              <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-foreground/90"></span>
              <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-foreground/90" style="animation-delay:120ms"></span>
              <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-foreground/90" style="animation-delay:240ms"></span>
            </span>
            {{ stageLabel }}
          </span>
        </button>

        <button
          class="inline-flex items-center justify-center rounded-md px-3 py-2.5 text-sm font-semibold
                 text-muted-foreground hover:text-foreground hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
          type="button"
          :disabled="isBusy"
          @click="resetAll"
        >
          Limpiar
        </button>
      </div>

      <!-- Progress -->
      <div
        v-if="stage === 'uploading' || stage === 'converting' || stage === 'downloading'"
        class="mx-6 rounded-xl border bg-muted/30 p-4 space-y-3"
      >
        <div class="flex justify-between text-sm">
          <span class="text-muted-foreground">Estado:</span>
          <span class="font-semibold">{{ stageLabel }}</span>
        </div>

        <div v-if="stage === 'uploading'" class="flex justify-between text-sm">
          <span class="text-muted-foreground">Subida:</span>
          <span class="font-semibold">{{ uploadProgress }}%</span>
        </div>

        <div v-if="stage === 'downloading'" class="flex justify-between text-sm">
          <span class="text-muted-foreground">Descarga:</span>
          <span class="font-semibold">{{ downloadProgress }}%</span>
        </div>

        <div class="h-2.5 overflow-hidden rounded-full bg-border/70">
          <div
            class="h-full bg-primary transition-[width] duration-200"
            :style="{
              width:
                stage === 'uploading'
                  ? uploadProgress + '%'
                  : stage === 'downloading'
                    ? downloadProgress + '%'
                    : '100%'
            }"
          />
        </div>

        <p v-if="stage === 'converting'" class="text-xs text-muted-foreground">
          Este wrapper es ligero; normalmente termina rápido incluso con varias filas.
        </p>
      </div>

      <!-- Messages -->
      <div v-if="successMsg" class="mx-6 rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-4">
        <div class="font-semibold text-emerald-700 dark:text-emerald-300">{{ successMsg }}</div>
        <div v-if="detailsMsg" class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-200/80">{{ detailsMsg }}</div>
      </div>

      <div v-if="errorMsg" class="mx-6 rounded-xl border border-destructive/25 bg-destructive/10 p-4">
        <div class="font-semibold text-destructive">⚠️ {{ errorMsg }}</div>
        <div v-if="detailsMsg" class="mt-1 text-sm text-muted-foreground">{{ detailsMsg }}</div>
      </div>

    </div>
  </div>
</template>