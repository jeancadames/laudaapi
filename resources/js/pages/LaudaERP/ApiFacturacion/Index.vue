<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'

import ErpLayout from '@/layouts/ErpLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

type Company = { id: number; name: string; slug: string; timezone: string }

type InboundStatus =
    | 'received'
    | 'validated'
    | 'queued_sign'
    | 'signed'
    | 'queued_send'
    | 'sent'
    | 'accepted'
    | 'rejected'
    | 'error'

type InboundItem = {
    id: string
    received_at: string
    source: 'sales' | 'manual' | 'api'
    document_class: 'ECF' | 'NCF'
    document_type: string // E31, E32, B01...
    customer_name?: string | null
    customer_tax_id?: string | null
    total?: string | number | null
    status: InboundStatus
    last_error?: string | null
}

type PipelineStats = {
    inbound_today: number
    pending_sign: number
    pending_send: number
    sent_today: number
    errors_today: number
}

type CertReq = {
    // tu service actual
    has_usable_signer?: boolean
    can_enable_auto_token?: boolean
    why_blocked?: string | null

    // por si tienes otra versión
    ok?: boolean
    default_signer_usable?: boolean
    should_set_default_signer?: boolean
}

const props = defineProps<{
    company: Company
    cert_requirements?: CertReq | null
    stats?: Partial<PipelineStats> | null
    inbox?: InboundItem[] | null
}>()

const page = usePage()

// Token DGII viene normalmente como global prop (HandleInertiaRequests)
const dgiiToken = computed<any>(() => (page.props as any)?.dgiiToken ?? null)

// Cert requirements: preferimos props; fallback global
const certReq = computed<CertReq | null>(() => {
    return (props.cert_requirements ?? (page.props as any)?.cert_requirements ?? null) as any
})

// -----------------------------
// Token badge UI
// -----------------------------
const tokenUi = computed(() => {
    const t = dgiiToken.value
    if (!t || t.enabled === false) {
        return {
            label: 'NO DISPONIBLE',
            cls: 'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-100',
        }
    }

    const status = String(t.status ?? 'expired')
    if (status === 'valid') return { label: 'TOKEN OK', cls: 'bg-emerald-600 text-white' }
    if (status === 'warning') return { label: 'POR VENCER', cls: 'bg-amber-500 text-white' }
    return { label: 'VENCIDO', cls: 'bg-red-600 text-white' }
})

const secondsLeft = computed(() => {
    const v = Number(dgiiToken.value?.secondsLeft ?? 0)
    return Number.isFinite(v) ? v : 0
})

function formatTtl(seconds: number) {
    const s = Math.max(0, Math.floor(seconds))
    const h = Math.floor(s / 3600)
    const m = Math.floor((s % 3600) / 60)
    return h > 0 ? `${h}h ${m}m` : `${m}m`
}

// -----------------------------
// Cert requirements UI (robusto)
// -----------------------------
const certOk = computed<boolean>(() => {
    const c = certReq.value
    if (!c) return false
    if (typeof c.ok === 'boolean') return c.ok
    if (typeof c.has_usable_signer === 'boolean') return c.has_usable_signer
    if (typeof c.can_enable_auto_token === 'boolean') return c.can_enable_auto_token
    return false
})

const certUi = computed(() => {
    const c = certReq.value
    if (!c) {
        return {
            label: '—',
            hint: 'No se recibieron requisitos desde el backend.',
            badgeVariant: 'outline' as const,
        }
    }

    if (certOk.value) {
        const warnDefault = c.default_signer_usable === false && c.should_set_default_signer === true
        return {
            label: warnDefault ? 'OK (DEFAULT)' : 'LISTO',
            hint: warnDefault
                ? 'Hay un firmador usable, pero tu default no es usable. Recomendado: set default en Certificación.'
                : 'Requisitos de firma completos.',
            badgeVariant: 'secondary' as const,
        }
    }

    return {
        label: 'REVISAR',
        hint: String(c.why_blocked ?? 'Faltan requisitos o hay certificados inválidos.'),
        badgeVariant: 'outline' as const,
    }
})

// -----------------------------
// Pipeline stats (opcional)
// -----------------------------
const stats = computed<PipelineStats>(() => {
    const s = props.stats ?? {}
    return {
        inbound_today: Number(s.inbound_today ?? 0),
        pending_sign: Number(s.pending_sign ?? 0),
        pending_send: Number(s.pending_send ?? 0),
        sent_today: Number(s.sent_today ?? 0),
        errors_today: Number(s.errors_today ?? 0),
    }
})

// -----------------------------
// Inbox (opcional)
// -----------------------------
const q = ref('')
const statusFilter = ref<InboundStatus | 'all'>('all')

const inbox = computed<InboundItem[]>(() => (props.inbox ?? []) as InboundItem[])

const filteredInbox = computed(() => {
    const qq = q.value.trim().toLowerCase()
    return inbox.value.filter((it) => {
        if (statusFilter.value !== 'all' && it.status !== statusFilter.value) return false
        if (!qq) return true
        return (
            it.id.toLowerCase().includes(qq) ||
            (it.customer_name ?? '').toLowerCase().includes(qq) ||
            (it.customer_tax_id ?? '').toLowerCase().includes(qq) ||
            it.document_type.toLowerCase().includes(qq)
        )
    })
})

function statusBadge(s: InboundStatus) {
    switch (s) {
        case 'received':
            return { label: 'RECIBIDO', cls: 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' }
        case 'validated':
            return { label: 'VALIDADO', cls: 'bg-indigo-600 text-white' }
        case 'queued_sign':
            return { label: 'EN COLA (FIRMA)', cls: 'bg-amber-500 text-white' }
        case 'signed':
            return { label: 'FIRMADO', cls: 'bg-emerald-600 text-white' }
        case 'queued_send':
            return { label: 'EN COLA (ENVÍO)', cls: 'bg-amber-500 text-white' }
        case 'sent':
            return { label: 'ENVIADO', cls: 'bg-sky-600 text-white' }
        case 'accepted':
            return { label: 'ACEPTADO', cls: 'bg-emerald-600 text-white' }
        case 'rejected':
            return { label: 'RECHAZADO', cls: 'bg-red-600 text-white' }
        case 'error':
        default:
            return { label: 'ERROR', cls: 'bg-red-600 text-white' }
    }
}

function sourceLabel(src: InboundItem[ 'source' ]) {
    if (src === 'sales') return 'Sales'
    if (src === 'api') return 'API'
    return 'Manual'
}
</script>

<template>
    <ErpLayout>

        <Head title="API Facturación Electrónica" />

        <div class="mx-auto w-full max-w-7xl px-4 py-6 space-y-6">
            <!-- Header (sin botones) -->
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
                        API Facturación Electrónica
                    </h1>

                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="tokenUi.cls">
                        {{ tokenUi.label }}
                    </span>
                </div>

                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Esta vista es operativa: recibe documentos desde <span class="font-semibold">Sales</span>,
                    los valida, <span class="font-semibold">firma</span> y los <span class="font-semibold">envía</span> a DGII.
                    <span class="ml-1 font-medium text-slate-700 dark:text-slate-300">{{ company.name }}</span>
                    <span class="mx-1">•</span>
                    <span class="font-mono">{{ company.timezone }}</span>
                </p>
            </div>

            <!-- KPI Operativos -->
            <div class="grid gap-3 sm:grid-cols-4">
                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Inbound hoy</CardDescription>
                        <CardTitle class="text-2xl">{{ stats.inbound_today }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Documentos recibidos (Sales/API)
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>En cola firma</CardDescription>
                        <CardTitle class="text-2xl">{{ stats.pending_sign }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Pendientes de firma XML
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>En cola envío</CardDescription>
                        <CardTitle class="text-2xl">{{ stats.pending_send }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Pendientes de envío a DGII
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Errores hoy</CardDescription>
                        <CardTitle class="text-2xl">{{ stats.errors_today }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Validación / firma / DGII
                    </CardContent>
                </Card>
            </div>

            <!-- Estado base (token + certificados) -->
            <div class="grid gap-3 sm:grid-cols-2">
                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Token DGII</CardDescription>
                        <CardTitle class="text-lg">{{ tokenUi.label }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        <div v-if="dgiiToken?.enabled">
                            TTL: <span class="font-mono">{{ formatTtl(secondsLeft) }}</span>
                            <span class="mx-1">•</span>
                            Auto: <span class="font-semibold">{{ dgiiToken?.auto ? 'ON' : 'OFF' }}</span>
                        </div>
                        <div v-else>Token no disponible</div>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Certificados</CardDescription>
                        <CardTitle class="text-lg">{{ certUi.label }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        {{ certUi.hint }}
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs Operacionales -->
            <Tabs default-value="bandeja" class="w-full">
                <TabsList class="rounded-2xl">
                    <TabsTrigger value="bandeja">Bandeja</TabsTrigger>
                    <TabsTrigger value="pipeline">Pipeline</TabsTrigger>
                    <TabsTrigger value="integraciones">Integraciones</TabsTrigger>
                    <TabsTrigger value="ajustes">Ajustes</TabsTrigger>
                </TabsList>

                <!-- Bandeja -->
                <TabsContent value="bandeja" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader class="pb-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle class="text-base">Documentos entrantes</CardTitle>
                                    <CardDescription>
                                        Inbound desde Sales/API. Aquí monitoreas estado, errores y reintentos.
                                    </CardDescription>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <Input v-model="q" placeholder="Buscar por id, RNC, cliente, tipo..." class="h-10 rounded-xl sm:w-[320px]" />
                                    <select v-model="statusFilter" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950">
                                        <option value="all">Todos</option>
                                        <option value="received">Recibidos</option>
                                        <option value="validated">Validados</option>
                                        <option value="queued_sign">Cola firma</option>
                                        <option value="signed">Firmados</option>
                                        <option value="queued_send">Cola envío</option>
                                        <option value="sent">Enviados</option>
                                        <option value="accepted">Aceptados</option>
                                        <option value="rejected">Rechazados</option>
                                        <option value="error">Error</option>
                                    </select>

                                    <Button variant="outline" class="rounded-xl" disabled>
                                        Refrescar
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                            <th class="py-2 pr-4">Recibido</th>
                                            <th class="py-2 pr-4">Fuente</th>
                                            <th class="py-2 pr-4">Doc</th>
                                            <th class="py-2 pr-4">Cliente</th>
                                            <th class="py-2 pr-4">Total</th>
                                            <th class="py-2 pr-4">Estado</th>
                                            <th class="py-2">Acción</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr v-for="it in filteredInbox" :key="it.id" class="border-t border-slate-100 dark:border-slate-900">
                                            <td class="py-3 pr-4 font-mono text-xs text-slate-700 dark:text-slate-300">
                                                {{ it.received_at }}
                                            </td>
                                            <td class="py-3 pr-4">
                                                <Badge variant="outline">{{ sourceLabel(it.source) }}</Badge>
                                            </td>
                                            <td class="py-3 pr-4 font-mono text-xs">
                                                {{ it.document_class }} {{ it.document_type }}
                                            </td>
                                            <td class="py-3 pr-4">
                                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ it.customer_name ?? '—' }}
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                                                    {{ it.customer_tax_id ?? '' }}
                                                </div>
                                            </td>
                                            <td class="py-3 pr-4 font-mono text-xs">
                                                {{ it.total ?? '—' }}
                                            </td>
                                            <td class="py-3 pr-4">
                                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="statusBadge(it.status).cls">
                                                    {{ statusBadge(it.status).label }}
                                                </span>
                                                <div v-if="it.status === 'error' || it.status === 'rejected'" class="mt-1 text-xs text-red-600 dark:text-red-300">
                                                    {{ it.last_error ?? '—' }}
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <Button variant="outline" size="sm" class="rounded-xl" disabled>
                                                    Ver
                                                </Button>
                                            </td>
                                        </tr>

                                        <tr v-if="filteredInbox.length === 0">
                                            <td colspan="7" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                                No hay documentos para mostrar (o aún no envías inbox desde backend).
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Pipeline -->
                <TabsContent value="pipeline" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Pipeline (Sales → DGII)</CardTitle>
                            <CardDescription>
                                Flujo recomendado con colas, idempotencia y auditoría.
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">1) Recibir</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Sales envía payload → guardas “inbound” + dedupe (idempotency_key).
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">2) Validar</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Validación negocio + XSD. Si falla → status=error con last_error.
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">3) Firmar</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Usa P12/PFX default → genera XML firmado. Cola: queued_sign.
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">4) Enviar DGII</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Token válido → envío → status=sent / accepted / rejected.
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">5) Notificar</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Webhook a Sales: accepted/rejected + track de reintentos.
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Observabilidad</CardTitle>
                            <CardDescription>Lo mínimo para operar en producción sin “ciegos”.</CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">Idempotencia</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Evita duplicados: (company_id + idempotency_key) UNIQUE.
                                </div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">Reintentos</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Backoff exponencial + DLQ para fallos persistentes.
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Integraciones -->
                <TabsContent value="integraciones" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Integraciones (Sales)</CardTitle>
                            <CardDescription>
                                Este módulo no crea documentos manualmente: recibe y procesa desde Sales.
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">Inbound endpoint</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Ej: POST /api/erp/ecf/inbound (por tenant) + API key/Signature.
                                </div>
                                <div class="mt-3 rounded-xl bg-slate-50 p-3 font-mono text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-200 break-all">
                                    (pendiente: ruta real en tu backend)
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">Webhooks a Sales</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Notifica accepted/rejected + payload DGII + timestamps + correlación.
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Ajustes -->
                <TabsContent value="ajustes" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Ajustes</CardTitle>
                            <CardDescription>Valores del tenant y señales operativas.</CardDescription>
                        </CardHeader>

                        <CardContent class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">Timezone</div>
                                <div class="mt-2">
                                    <Input :value="company.timezone" class="h-10 rounded-xl" disabled />
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">Estado de certificados</div>
                                <div class="mt-2 flex items-center gap-2">
                                    <Badge :variant="certUi.badgeVariant">{{ certOk ? 'OK' : 'REVISAR' }}</Badge>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ certUi.hint }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </ErpLayout>
</template>