<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import ErpLayout from '@/layouts/ErpLayout.vue'
import type { BreadcrumbItem } from '@/types'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogTrigger,
} from '@/components/ui/dialog'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

type Cert = {
    id: number
    label: string | null
    type: 'p12' | 'pfx' | 'cer'
    is_default: boolean
    subject_cn: string | null
    issuer_cn: string | null
    serial_number: string | null
    valid_from: string | null
    valid_to: string | null
    has_private_key: boolean
    password_ok: boolean
    status: string
    original_name: string | null
    file_size: number | null
    meta?: Record<string, any> | null
}

type HealthResponse = {
    ok: boolean
    company: { id: number; name: string | null; rnc: string | null }
    default_cert: any | null
    issues: string[]
}

type TestSignResponse = {
    ok: boolean
    signed_xml?: string
    result?: {
        digest?: string
        signature_method?: string
        reference_uri?: string
    }
    digest?: string
    signature_method?: string
    reference_uri?: string
    [ k: string ]: any
}

type RefreshResponse = {
    ok?: boolean
    message?: string
    cert?: any
    [ k: string ]: any
}

const props = defineProps<{
    company: { id: number; name: string | null; rnc: string | null }
    certs: Cert[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LaudaERP', href: '/erp' },
    { title: 'Servicios', href: '/erp' },
    { title: 'Certificación Emisor', href: '/erp/services/certificacion-emisor' },
    { title: 'Certificados', href: '/erp/services/certificacion-emisor/certificados' },
]

const hasCerts = computed(() => (props.certs?.length ?? 0) > 0)

/* -----------------------------------------
   ✅ Helpers (UI)
------------------------------------------ */
function formatIso(iso?: string | null) {
    if (!iso) return '—'
    const m = String(iso).match(/^(\d{4}-\d{2}-\d{2})/)
    return m ? m[ 1 ] : iso
}

function formatBytes(bytes?: number | null) {
    if (!bytes || bytes <= 0) return '—'
    const units = [ 'B', 'KB', 'MB', 'GB' ]
    let i = 0
    let v = bytes
    while (v >= 1024 && i < units.length - 1) {
        v = v / 1024
        i++
    }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[ i ]}`
}

function statusBadgeVariant(status: string) {
    const s = (status || '').toLowerCase()
    if (s === 'active') return 'secondary'
    if (s === 'expired') return 'destructive'
    if (s === 'invalid') return 'destructive'
    return 'secondary'
}

function yesNoBadge(ok: boolean) {
    return ok ? 'secondary' : 'destructive'
}

function keyTypeFromMeta(meta?: Record<string, any> | null) {
    const t = (meta?.key_type ?? meta?.private_key_type ?? '') as string
    return t ? String(t).toUpperCase() : null
}

function keyBitsFromMeta(meta?: Record<string, any> | null) {
    const b = meta?.key_bits ?? meta?.private_key_bits ?? null
    const n = Number(b)
    return Number.isFinite(n) && n > 0 ? n : null
}

/* -----------------------------------------
   ✅ Vigencia Badge (verde/amarillo/rojo)
------------------------------------------ */
function daysLeft(validToIso?: string | null) {
    if (!validToIso) return null

    const end = new Date(validToIso).getTime()
    if (Number.isNaN(end)) return null

    const now = Date.now()
    const diffMs = end - now
    return Math.ceil(diffMs / (1000 * 60 * 60 * 24))
}

function vigenciaBadgeClass(validToIso?: string | null, status?: string | null) {
    const s = (status ?? '').toLowerCase()
    const d = daysLeft(validToIso)

    if (s === 'expired') return 'bg-red-600 text-white hover:bg-red-600'
    if (s === 'invalid') return 'bg-red-600 text-white hover:bg-red-600'

    if (d === null) return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'
    if (d <= 0) return 'bg-red-600 text-white hover:bg-red-600'
    if (d <= 30) return 'bg-red-600 text-white hover:bg-red-600'
    if (d <= 60) return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return 'bg-emerald-600 text-white hover:bg-emerald-600'
}

function vigenciaLabel(validToIso?: string | null, status?: string | null) {
    const s = (status ?? '').toLowerCase()
    const d = daysLeft(validToIso)

    if (s === 'expired') return 'VENCIDO'
    if (s === 'invalid') return 'INVALID'
    if (d === null) return 'Sin fecha'
    if (d <= 0) return 'VENCIDO'
    return `${d} días`
}

/* -----------------------------------------
   ✅ Upload
------------------------------------------ */
const openUpload = ref(false)

const form = useForm<{
    label: string
    password: string
    file: File | null
}>({
    label: '',
    password: '',
    file: null,
})

const fileInputRef = ref<HTMLInputElement | null>(null)

function isAllowedCertFile(f: File) {
    const ext = (f.name.split('.').pop() || '').toLowerCase()
    return [ 'p12', 'pfx', 'cer' ].includes(ext)
}

const fileExt = computed(() => {
    const f = form.file
    if (!f) return null
    return (f.name.split('.').pop() || '').toLowerCase()
})

const needsPassword = computed(() => fileExt.value === 'p12' || fileExt.value === 'pfx')

function onPickFile(e: Event) {
    const f = (e.target as HTMLInputElement).files?.[ 0 ] ?? null

    form.clearErrors('file')

    if (f && !isAllowedCertFile(f)) {
        form.setError('file', 'Solo se permiten archivos .p12, .pfx o .cer')
        if (fileInputRef.value) fileInputRef.value.value = ''
        form.file = null
        return
    }

    form.file = f
    if (!needsPassword.value) form.password = ''
}

function resetUploadForm() {
    form.reset()
    form.clearErrors()
    if (fileInputRef.value) fileInputRef.value.value = ''
}

function submitUpload() {
    if (!form.file) return

    if (!isAllowedCertFile(form.file)) {
        form.setError('file', 'Solo se permiten archivos .p12, .pfx o .cer')
        return
    }

    form.post('/erp/services/certificacion-emisor/certificados', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetUploadForm()
            openUpload.value = false
        },
    })
}

/* -----------------------------------------
   ✅ Actions
------------------------------------------ */
function setDefault(certId: number) {
    router.visit(`/erp/services/certificacion-emisor/certificados/${certId}/default`, {
        method: 'post',
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function destroyCert(certId: number) {
    const ok = window.confirm('¿Seguro que deseas eliminar este certificado? Esta acción no se puede deshacer.')
    if (!ok) return

    router.visit(`/erp/services/certificacion-emisor/certificados/${certId}`, {
        method: 'delete',
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

/* -----------------------------------------
   ✅ Tools UI State
------------------------------------------ */
const toolsTab = ref<'health' | 'test'>('health')
const openTools = ref(false)

const toolsLoading = ref(false)
const toolsError = ref<string | null>(null)

const health = ref<HealthResponse | null>(null)

const testTarget = ref<Cert | null>(null)
const testXml = ref<string>('')
const testPassword = ref<string>('') // solo memoria UI, NO guardar server
const testResult = ref<TestSignResponse | null>(null)

const refreshTarget = ref<Cert | null>(null)
const refreshPassword = ref<string>('') // solo UI
const refreshResult = ref<RefreshResponse | null>(null)

function resetToolsState() {
    toolsError.value = null
    health.value = null

    testTarget.value = null
    testResult.value = null
    testXml.value = ''
    testPassword.value = ''

    refreshTarget.value = null
    refreshResult.value = null
    refreshPassword.value = ''
}

/* -----------------------------------------
   ✅ CSRF + fetchJson (FIX 419 real)
   - X-CSRF-TOKEN (meta)
   - X-XSRF-TOKEN (cookie XSRF-TOKEN)
------------------------------------------ */
function getCookie(name: string): string | null {
    const m = document.cookie.match(
        new RegExp('(^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)')
    )
    return m ? decodeURIComponent(m[ 2 ]) : null
}

function csrfToken(): string | null {
    const el = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
    return el?.content ?? null
}

async function fetchJson(url: string, init?: RequestInit) {
    const token = csrfToken()
    const xsrf = getCookie('XSRF-TOKEN')

    const headers: Record<string, string> = {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        ...(init?.headers as any),
    }

    if (token) headers[ 'X-CSRF-TOKEN' ] = token
    if (xsrf) headers[ 'X-XSRF-TOKEN' ] = xsrf

    const hasBody = init?.body !== undefined && init?.body !== null
    if (hasBody && !headers[ 'Content-Type' ]) headers[ 'Content-Type' ] = 'application/json'

    const res = await fetch(url, {
        ...init,
        headers,
        credentials: 'same-origin',
    })

    const txt = await res.text()
    let data: any = null
    try {
        data = txt ? JSON.parse(txt) : null
    } catch {
        data = null
    }

    if (!res.ok) {
        if (res.status === 419) {
            throw new Error('CSRF token mismatch (419). Recarga la página e inténtalo de nuevo.')
        }
        const msg = data?.message || data?.error || `Error HTTP ${res.status}`
        throw new Error(msg)
    }

    return data
}

/* -----------------------------------------
   ✅ Health
------------------------------------------ */
async function runHealth() {
    toolsLoading.value = true
    toolsError.value = null
    try {
        const data = await fetchJson('/erp/services/certificacion-emisor/certificados/health')
        health.value = data as HealthResponse
    } catch (e: any) {
        toolsError.value = e?.message ?? 'No se pudo ejecutar Health.'
    } finally {
        toolsLoading.value = false
    }
}

/* -----------------------------------------
   ✅ Test sign
------------------------------------------ */
function openTestFor(cert: Cert) {
    openTools.value = true
    toolsTab.value = 'test'
    toolsError.value = null

    testTarget.value = cert
    testResult.value = null

    // también lo usamos como “target” para refresh manual
    refreshTarget.value = cert
    refreshResult.value = null
}

async function runTestSign() {
    if (!testTarget.value) return
    toolsLoading.value = true
    toolsError.value = null
    testResult.value = null

    try {
        const url = `/erp/services/certificacion-emisor/certificados/${testTarget.value.id}/test-sign`
        const payload: any = {}

        if (testXml.value.trim()) payload.xml = testXml.value.trim()
        if (testPassword.value.trim()) payload.password = testPassword.value.trim()

        const data = await fetchJson(url, {
            method: 'POST',
            body: JSON.stringify(payload),
        })

        testResult.value = data as TestSignResponse
    } catch (e: any) {
        toolsError.value = e?.message ?? 'No se pudo probar la firma.'
    } finally {
        toolsLoading.value = false
    }
}

/* -----------------------------------------
   ✅ Refresh (re-parse)
------------------------------------------ */
async function runRefresh(cert: Cert) {
    openTools.value = true
    toolsTab.value = 'health'
    toolsLoading.value = true
    toolsError.value = null

    refreshTarget.value = cert
    refreshResult.value = null

    try {
        const url = `/erp/services/certificacion-emisor/certificados/${cert.id}/refresh`
        const payload: any = {}
        if (refreshPassword.value.trim()) payload.password = refreshPassword.value.trim()

        const data = await fetchJson(url, {
            method: 'POST',
            body: JSON.stringify(payload),
        })
        refreshResult.value = data as RefreshResponse

        // refresca solo certs
        router.visit(window.location.href, {
            only: [ 'certs' ],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    } catch (e: any) {
        toolsError.value = e?.message ?? 'No se pudo refrescar el certificado.'
    } finally {
        toolsLoading.value = false
    }
}

/* -----------------------------------------
   ✅ Open tools modal
------------------------------------------ */
function openToolsModal() {
    openTools.value = true
    toolsTab.value = 'health'
    resetToolsState()

    refreshTarget.value = props.certs?.[ 0 ] ?? null
    runHealth()
}

/* -----------------------------------------
   ✅ Utils
------------------------------------------ */
function copyToClipboard(txt: string) {
    if (!txt) return
    navigator.clipboard?.writeText(txt).catch(() => {
        const ta = document.createElement('textarea')
        ta.value = txt
        ta.style.position = 'fixed'
        ta.style.left = '-9999px'
        document.body.appendChild(ta)
        ta.select()
        document.execCommand('copy')
        document.body.removeChild(ta)
    })
}

function resultDigest(r?: TestSignResponse | null) {
    return r?.result?.digest ?? r?.digest ?? null
}
function resultSigMethod(r?: TestSignResponse | null) {
    return r?.result?.signature_method ?? r?.signature_method ?? null
}
function resultUri(r?: TestSignResponse | null) {
    const v = r?.result?.reference_uri ?? r?.reference_uri
    return v === undefined ? null : String(v)
}
</script>

<template>

    <Head title="Certificados • Certificación Emisor" />

    <ErpLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <header class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold">Certificados</h1>
                        <p class="text-sm text-muted-foreground">
                            Empresa: <span class="font-medium">{{ company.name ?? '—' }}</span>
                            <span class="mx-2">•</span>
                            RNC: <span class="font-medium">{{ company.rnc ?? '—' }}</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Tools -->
                        <Button variant="outline" class="gap-2" @click="openToolsModal" title="Abrir herramientas de salud / pruebas">
                            <!-- si usas lucide-vue-next, descomenta:
    <Wrench class="h-4 w-4" />
    -->
                            Tools
                            <span class="text-muted-foreground">(Health/Test)</span>
                        </Button>

                        <!-- Subir certificado -->
                        <Dialog v-model:open="openUpload" @update:open="(v) => { if (!v) resetUploadForm() }">
                            <DialogTrigger as-child>
                                <Button class="gap-2">
                                    <!-- <Upload class="h-4 w-4" /> -->
                                    Subir certificado
                                </Button>
                            </DialogTrigger>

                            <DialogContent class="sm:max-w-140">
                                <DialogHeader>
                                    <DialogTitle>Subir certificado</DialogTitle>
                                    <DialogDescription>
                                        Sube un archivo <span class="font-medium">.p12 / .pfx / .cer</span>. Se guardará en almacenamiento privado.
                                        <br />
                                        Para P12/PFX, agrega la contraseña si aplica.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <Label>Etiqueta (opcional)</Label>
                                        <Input v-model="form.label" placeholder="Ej: Certificado Producción" />
                                        <p v-if="form.errors.label" class="text-xs text-destructive">{{ form.errors.label }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label>Archivo</Label>
                                        <Input ref="fileInputRef" type="file" accept=".p12,.pfx,.cer" @change="onPickFile" />
                                        <div v-if="form.file" class="text-xs text-muted-foreground">
                                            Seleccionado: <span class="font-medium">{{ form.file.name }}</span>
                                            <span class="mx-2">•</span>
                                            {{ formatBytes(form.file.size) }}
                                        </div>
                                        <p v-if="form.errors.file" class="text-xs text-destructive">{{ form.errors.file }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label>Contraseña (solo P12/PFX)</Label>
                                        <Input v-model="form.password" type="password" placeholder="(opcional)" :disabled="!needsPassword" />
                                        <p v-if="!needsPassword" class="text-xs text-muted-foreground">
                                            La contraseña solo aplica para .p12 / .pfx
                                        </p>
                                        <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
                                    </div>
                                </div>

                                <DialogFooter class="gap-2">
                                    <Button variant="secondary" type="button" @click="openUpload = false">Cancelar</Button>
                                    <Button type="button" :disabled="form.processing || !form.file" @click="submitUpload">
                                        {{ form.processing ? 'Subiendo…' : 'Subir' }}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                        <!-- Volver (se ve como botón, actúa como link) -->
                        <Button as-child variant="outline" class="gap-2" title="Volver a Certificación Emisor">
                            <Link href="/erp/services/certificacion-emisor">
                                <!-- <ArrowLeft class="h-4 w-4" /> -->
                                Volver
                            </Link>
                        </Button>
                    </div>
                </div>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>Listado</CardTitle>
                    <CardDescription>
                        Aquí se guardan los certificados que usarás para firmar XML.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="!hasCerts" class="rounded-lg border p-6 text-sm text-muted-foreground">
                        No hay certificados cargados todavía. Sube tu primer P12/PFX/CER para continuar.
                    </div>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 text-left">Certificado</th>
                                    <th class="px-3 py-2 text-left">Tipo</th>
                                    <th class="px-3 py-2 text-left">Vigencia</th>
                                    <th class="px-3 py-2 text-left">Key</th>
                                    <th class="px-3 py-2 text-left">Password</th>
                                    <th class="px-3 py-2 text-left">Estado</th>
                                    <th class="px-3 py-2 text-right">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="c in certs" :key="c.id" class="border-t">
                                    <td class="px-3 py-3 align-top">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-medium">
                                                    {{ c.label || c.original_name || `Cert #${c.id}` }}
                                                </span>

                                                <Badge v-if="c.is_default" variant="secondary">DEFAULT</Badge>

                                                <Badge variant="secondary" :class="vigenciaBadgeClass(c.valid_to, c.status)">
                                                    {{ vigenciaLabel(c.valid_to, c.status) }}
                                                </Badge>
                                            </div>

                                            <div class="mt-1 text-xs text-muted-foreground">
                                                CN: {{ c.subject_cn ?? '—' }}
                                                <span class="mx-2">•</span>
                                                Issuer: {{ c.issuer_cn ?? '—' }}
                                            </div>

                                            <div class="mt-1 text-xs text-muted-foreground">
                                                Serial: {{ c.serial_number ?? '—' }}
                                                <span class="mx-2">•</span>
                                                Size: {{ formatBytes(c.file_size) }}
                                            </div>

                                            <div v-if="c.meta" class="mt-2 flex flex-wrap gap-2">
                                                <Badge v-if="keyTypeFromMeta(c.meta)" variant="secondary">
                                                    {{ keyTypeFromMeta(c.meta) }}
                                                </Badge>
                                                <Badge v-if="keyBitsFromMeta(c.meta)" variant="secondary">
                                                    {{ keyBitsFromMeta(c.meta) }} bits
                                                </Badge>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <Badge variant="secondary" class="uppercase">{{ c.type }}</Badge>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <div class="text-xs text-muted-foreground">Desde: {{ formatIso(c.valid_from) }}</div>
                                        <div class="text-xs text-muted-foreground">Hasta: {{ formatIso(c.valid_to) }}</div>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <Badge :variant="yesNoBadge(c.has_private_key)">{{ c.has_private_key ? 'Sí' : 'No' }}</Badge>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <Badge :variant="yesNoBadge(c.password_ok)">{{ c.password_ok ? 'OK' : 'NO' }}</Badge>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <Badge :variant="statusBadgeVariant(c.status)" class="capitalize">{{ c.status }}</Badge>
                                    </td>

                                    <td class="px-3 py-3 align-top text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button variant="secondary" size="sm" :disabled="c.is_default" @click="setDefault(c.id)">
                                                Predeterminar
                                            </Button>

                                            <Button variant="secondary" size="sm" @click="openTestFor(c)">
                                                Test firma
                                            </Button>

                                            <Button variant="secondary" size="sm" @click="runRefresh(c)">
                                                Refresh
                                            </Button>

                                            <Button variant="destructive" size="sm" @click="destroyCert(c.id)">
                                                Eliminar
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-xs text-muted-foreground">
                        Tools: Health valida default (RSA/bits/vigencia). Test firma genera un XML firmado para confirmar RSA-SHA256 y Reference URI="" (sin prefijo ds).
                    </p>
                </CardContent>
            </Card>

            <!-- ✅ Tools Modal -->
            <Dialog v-model:open="openTools">
                <DialogContent class="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Tools • Certificados</DialogTitle>
                        <DialogDescription>
                            Health = validación del pipeline. Test = firma DGII usando el certificado seleccionado.
                        </DialogDescription>
                    </DialogHeader>

                    <Tabs v-model="toolsTab" class="w-full">
                        <TabsList class="grid w-full grid-cols-2">
                            <TabsTrigger value="health">Health</TabsTrigger>
                            <TabsTrigger value="test" :disabled="!testTarget">Test firma</TabsTrigger>
                        </TabsList>

                        <!-- Health -->
                        <TabsContent value="health" class="mt-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-muted-foreground">
                                    Valida: default, private key, password_ok, status, RSA, bits.
                                </div>
                                <Button variant="secondary" :disabled="toolsLoading" @click="runHealth">
                                    {{ toolsLoading ? 'Ejecutando…' : 'Re-ejecutar Health' }}
                                </Button>
                            </div>

                            <div v-if="toolsError" class="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
                                {{ toolsError }}
                            </div>

                            <div v-if="refreshResult?.message" class="rounded-lg border p-4 text-sm">
                                {{ refreshResult.message }}
                            </div>

                            <div v-if="health" class="rounded-lg border p-4 space-y-3">
                                <div class="flex items-center gap-2">
                                    <Badge :variant="health.ok ? 'secondary' : 'destructive'">
                                        {{ health.ok ? 'OK' : 'FAIL' }}
                                    </Badge>
                                    <div class="text-sm font-medium">
                                        {{ health.company?.name ?? 'Empresa' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        RNC: {{ health.company?.rnc ?? '—' }}
                                    </div>
                                </div>

                                <div v-if="health.default_cert" class="text-xs text-muted-foreground">
                                    Default: {{ health.default_cert.label ?? `Cert #${health.default_cert.id}` }}
                                    <span class="mx-2">•</span>
                                    Type: {{ health.default_cert.type }}
                                </div>

                                <div v-if="health.issues?.length" class="space-y-2">
                                    <div class="text-sm font-semibold">Issues</div>
                                    <ul class="list-disc pl-5 text-sm">
                                        <li v-for="(i, idx) in health.issues" :key="idx">{{ i }}</li>
                                    </ul>
                                </div>

                                <div v-else class="text-sm text-muted-foreground">
                                    No se detectaron issues.
                                </div>

                                <div class="rounded-lg border p-4 space-y-2">
                                    <div class="text-sm font-semibold">Refresh (re-parse) manual</div>
                                    <p class="text-xs text-muted-foreground">
                                        Si tu P12/PFX necesita password y no lo guardas, ponlo aquí para re-parsear.
                                    </p>

                                    <div class="flex flex-col gap-2 md:flex-row md:items-end">
                                        <div class="w-full">
                                            <Label class="text-xs">Password (opcional)</Label>
                                            <Input v-model="refreshPassword" type="password" placeholder="(opcional)" />
                                        </div>

                                        <Button variant="secondary" class="md:whitespace-nowrap" :disabled="toolsLoading || !refreshTarget" @click="refreshTarget ? runRefresh(refreshTarget) : null">
                                            {{ toolsLoading ? 'Refrescando…' : 'Refrescar el seleccionado' }}
                                        </Button>
                                    </div>

                                    <p class="text-xs text-muted-foreground">
                                        Tip: también puedes darle “Refresh” desde la tabla por cada fila.
                                    </p>
                                </div>
                            </div>
                        </TabsContent>

                        <!-- Test -->
                        <TabsContent value="test" class="mt-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="text-sm">
                                    <span class="text-muted-foreground">Cert:</span>
                                    <span class="font-medium">
                                        {{ testTarget?.label || testTarget?.original_name || (testTarget ? `Cert #${testTarget.id}` : '—') }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button variant="secondary" :disabled="toolsLoading || !testTarget" @click="runTestSign">
                                        {{ toolsLoading ? 'Firmando…' : 'Ejecutar Test firma' }}
                                    </Button>
                                </div>
                            </div>

                            <div v-if="toolsError" class="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
                                {{ toolsError }}
                            </div>

                            <div class="rounded-lg border p-4 space-y-3">
                                <div class="text-sm font-semibold">Password (opcional)</div>
                                <p class="text-xs text-muted-foreground">
                                    Si tu P12/PFX tiene password y no lo guardas, ponlo aquí para el test.
                                </p>
                                <Input v-model="testPassword" type="password" placeholder="(opcional)" />
                            </div>

                            <div class="rounded-lg border p-4 space-y-3">
                                <div class="text-sm font-semibold">XML de entrada (opcional)</div>
                                <p class="text-xs text-muted-foreground">
                                    Si lo dejas vacío, el backend firma un XML mínimo de prueba.
                                </p>
                                <textarea v-model="testXml" rows="6" class="w-full rounded-md border bg-background p-2 text-xs font-mono" placeholder="(Opcional) pega un XML aquí para firmarlo…" />
                            </div>

                            <div v-if="testResult" class="rounded-lg border p-4 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge :variant="testResult.ok ? 'secondary' : 'destructive'">
                                        {{ testResult.ok ? 'OK' : 'FAIL' }}
                                    </Badge>

                                    <Badge v-if="resultDigest(testResult)" variant="secondary">{{ resultDigest(testResult) }}</Badge>
                                    <Badge v-if="resultSigMethod(testResult)" variant="secondary">{{ resultSigMethod(testResult) }}</Badge>
                                    <Badge v-if="resultUri(testResult) !== null" variant="secondary">URI: "{{ resultUri(testResult) }}"</Badge>
                                </div>

                                <div v-if="testResult.signed_xml" class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm font-semibold">XML firmado</div>
                                        <Button variant="secondary" size="sm" @click="copyToClipboard(testResult.signed_xml)">
                                            Copiar
                                        </Button>
                                    </div>

                                    <textarea :value="testResult.signed_xml" rows="12" readonly class="w-full rounded-md border bg-background p-2 text-xs font-mono" />
                                </div>

                                <div v-else class="text-sm text-muted-foreground">
                                    El backend no devolvió signed_xml.
                                </div>
                            </div>
                        </TabsContent>
                    </Tabs>

                    <DialogFooter class="gap-2">
                        <Button variant="secondary" type="button" @click="openTools = false">Cerrar</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </ErpLayout>
</template>
