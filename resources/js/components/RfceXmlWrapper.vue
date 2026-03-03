  <script setup lang="js">
  import axios from 'axios'
  import { computed, ref } from 'vue'

  const MAX_BYTES = 1024 * 1024 * 100
  const ALLOWED_EXT = ['xlsx', 'xls', 'csv']

  const inputRef = ref(null)
  const file = ref(null)
  const dragOver = ref(false)

  const stage = ref('idle')
  const uploadProgress = ref(0)
  const downloadProgress = ref(0)

  const successMsg = ref('')
  const errorMsg = ref('')
  const detailsMsg = ref('')

  const isBusy = computed(() => ['uploading', 'converting', 'downloading'].includes(stage.value))

  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null
  }

  function humanBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB']
    let i = 0, n = bytes
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++ }
    return `${n.toFixed(i === 0 ? 0 : 2)} ${units[i]}`
  }

  function resetMessages() { successMsg.value=''; errorMsg.value=''; detailsMsg.value='' }

  function setFile(f) {
    resetMessages()
    if (!f) return

    const ext = (f.name.split('.').pop() || '').toLowerCase()
    if (!ALLOWED_EXT.includes(ext)) {
      errorMsg.value = 'Tipo de archivo no permitido.'
      detailsMsg.value = `Solo: ${ALLOWED_EXT.join(', ')}`
      return
    }
    if (f.size > MAX_BYTES) {
      errorMsg.value = 'Archivo demasiado grande.'
      detailsMsg.value = `Máximo: ${humanBytes(MAX_BYTES)}`
      return
    }

    file.value = f
  }

  function onInputChange(e) { setFile(e.target.files?.[0] ?? null) }
  function onDrop(e) { dragOver.value = false; if (!isBusy.value) setFile(e.dataTransfer?.files?.[0] ?? null) }
  function onDragOver(e) { if (!isBusy.value) { e.preventDefault(); dragOver.value = true } }
  function onDragLeave() { dragOver.value = false }

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
    let filename = 'rfce_xmls.zip'
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

      const res = await axios.post('/erp/services/certificacion-emisor/set-ecf/rfce/excel-to-xml', fd, {
        headers: { ...(token ? { 'X-CSRF-TOKEN': token } : {}), 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (evt) => {
          if (evt.total) uploadProgress.value = Math.round((evt.loaded / evt.total) * 100)
          else uploadProgress.value = Math.min(95, uploadProgress.value + 3)
        },
        timeout: 0,
      })

      uploadProgress.value = 100
      stage.value = 'converting'

      const downloadUrl = res.data?.download_url
      if (!downloadUrl) throw new Error('No vino download_url.')

      await downloadZip(downloadUrl)

      stage.value = 'done'
      successMsg.value = '✅ ZIP generado y descargado.'
      detailsMsg.value = `${file.value.name} → rfce_xmls.zip`
    } catch (e) {
      stage.value = 'error'
      errorMsg.value = 'Error al procesar.'
      detailsMsg.value = e?.response?.data?.message || e?.message || 'Revisa el log.'
      console.error(e)
    }
  }
  </script>

  <template>
  <div class="grid place-items-center py-4">
    <div class="w-full bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-sm py-6">

      <!-- Header -->
      <div class="px-6 pb-6 border-b @container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5">
        <h3 class="leading-none font-semibold tracking-tight">
          Wrapper RFCE (Facturas de Consumo Menor a RD$250,000.00)
        </h3>
        <p class="text-sm text-muted-foreground">
          Sube el Excel de casos y se generará 1 XML por fila, estructurado dinámicamente usando el XSD RFCE.
        </p>
      </div>

      <!-- Dropzone -->
      <div
        class="mx-6 rounded-xl border border-dashed bg-muted/30 p-4 transition"
        :class="[
          dragOver ? 'border-ring ring-2 ring-ring/25 bg-muted/50' : 'border-border',
          isBusy ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer hover:bg-muted/40'
        ]"
        @click="() => !isBusy && inputRef?.click()"
        @drop.prevent="onDrop"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
      >
        <input ref="inputRef" type="file" class="hidden" accept=".xlsx,.xls,.csv" @change="onInputChange" />

        <div class="flex items-center gap-4">
          <!-- Icon chip -->
          <div class="h-12 w-12 rounded-xl border bg-background grid place-items-center text-muted-foreground">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" class="opacity-90">
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
                <strong class="text-foreground">{{ file.name }}</strong>
                <span class="text-muted-foreground"> — {{ humanBytes(file.size) }}</span>
              </span>
            </div>
          </div>

          <!-- Ghost button -->
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-md border bg-background px-3 py-2 text-sm font-medium shadow-sm
                   hover:bg-accent hover:text-accent-foreground disabled:opacity-50 disabled:pointer-events-none"
            :disabled="isBusy"
            @click.stop="() => !isBusy && inputRef?.click()"
          >
            Elegir
          </button>
        </div>
      </div>

      <!-- Actions -->
      <div class="px-6 flex items-center gap-3">
        <button
          type="button"
          class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold
                 text-primary-foreground shadow-sm hover:bg-primary/90 disabled:opacity-50 disabled:pointer-events-none"
          :disabled="!file || isBusy"
          @click="submit"
        >
          <span v-if="!isBusy">Convertir y descargar ZIP</span>
          <span v-else class="inline-flex items-center gap-2">
            <span class="inline-flex items-center gap-1">
              <span class="h-1.5 w-1.5 rounded-full bg-primary-foreground/90 animate-bounce"></span>
              <span class="h-1.5 w-1.5 rounded-full bg-primary-foreground/90 animate-bounce" style="animation-delay:120ms"></span>
              <span class="h-1.5 w-1.5 rounded-full bg-primary-foreground/90 animate-bounce" style="animation-delay:240ms"></span>
            </span>
            Procesando...
          </span>
        </button>

        <!-- (Opcional) Un botón secundario si luego quieres "Limpiar" como en el otro -->
        <!--
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-semibold
                 text-muted-foreground hover:text-foreground hover:bg-accent disabled:opacity-50 disabled:pointer-events-none"
          :disabled="isBusy"
          @click="() => { file=null; stage='idle'; uploadProgress=0; downloadProgress=0; resetMessages(); if (inputRef) inputRef.value=''; }"
        >
          Limpiar
        </button>
        -->
      </div>

      <!-- Progress -->
      <div
        v-if="stage === 'uploading' || stage === 'downloading' || stage === 'converting'"
        class="mx-6 rounded-xl border bg-muted/30 p-4 space-y-3"
      >
        <div class="text-sm font-semibold">
          <span v-if="stage === 'uploading'">Subiendo… {{ uploadProgress }}%</span>
          <span v-else-if="stage === 'downloading'">Descargando… {{ downloadProgress }}%</span>
          <span v-else>Convirtiendo…</span>
        </div>

        <div class="h-2.5 w-full rounded-full bg-border/70 overflow-hidden">
          <div
            class="h-full bg-primary transition-[width] duration-200"
            :style="{ width: stage === 'uploading' ? uploadProgress + '%' : stage === 'downloading' ? downloadProgress + '%' : '100%' }"
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

    </div>
  </div>
</template>