<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import ErpLayout from '@/layouts/ErpLayout.vue'
import type { BreadcrumbItem } from '@/types'

// Shadcn
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'

// Optional placeholder (si lo tienes)
import PlaceholderPattern from '@/components/PlaceholderPattern.vue'

type AlertLevel = 'info' | 'warning' | 'critical'

type CompanyPayload = {
    id: number
    name: string
    slug: string
    currency: string
    timezone: string
    active?: boolean
}

type RangePayload = {
    preset: 'mtd' | 'ytd' | '30d' | '7d' | 'custom'
    from: string
    to: string
}

type AlertPayload = {
    level: AlertLevel
    title: string
    description?: string | null
    cta?: { label: string; href: string } | null
}

type KpisPayload = {
    revenue?: { value: number; deltaPct?: number | null }
    collections?: { value: number; deltaPct?: number | null }
    arOutstanding?: { value: number }
    apOutstanding?: { value: number }
    cashBalance?: { value: number }
    orders?: { value: number; deltaPct?: number | null }
    activeCustomers?: { value: number; deltaPct?: number | null }
    compliance?: { overdue: number; dueSoon7d: number }
}

type DgiiPayload = {
    tokenStatus?: 'ok' | 'warn' | 'expired' | 'missing'
    tokenExpiresAt?: string | null
    environment?: string | null
    lastTokenRefreshAt?: string | null
    certStatus?: 'ok' | 'warn' | 'missing' | 'invalid'
}

type ComplianceItem = {
    authority: string
    code: string
    name: string
    due_on: string
    status: 'overdue' | 'due'
}

type ActivityItem = {
    at: string
    actor: string
    event: string
    meta?: any
}

const props = defineProps<{
    company: CompanyPayload | null
    taxProfileReady: boolean
    range?: RangePayload
    alerts?: AlertPayload[]
    kpis?: KpisPayload
    dgii?: DgiiPayload
    compliance?: { nextDue?: ComplianceItem[] }
    charts?: { cashflow?: any[]; sales?: any[] }
    activity?: ActivityItem[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LaudaERP', href: '/erp' },
    { title: 'Dashboard', href: '/erp' },
]

// ----------
// Fallbacks
// ----------
const range = computed<RangePayload>(() => {
    const r = props.range
    if (r?.from && r?.to) return r
    // fallback MTD
    const today = new Date()
    const y = today.getFullYear()
    const m = String(today.getMonth() + 1).padStart(2, '0')
    const d = String(today.getDate()).padStart(2, '0')
    return { preset: 'mtd', from: `${y}-${m}-01`, to: `${y}-${m}-${d}` }
})

const alerts = computed(() => props.alerts ?? [])
const kpis = computed<KpisPayload>(() => props.kpis ?? {})
const dgii = computed<DgiiPayload>(() => props.dgii ?? {})
const nextDue = computed<ComplianceItem[]>(() => props.compliance?.nextDue ?? [])
const activity = computed<ActivityItem[]>(() => props.activity ?? [])

const companyName = computed(() => props.company?.name ?? '—')
const companySlug = computed(() => props.company?.slug ?? '—')
const companyCurrency = computed(() => (props.company?.currency || 'DOP').toUpperCase())
const companyTimezone = computed(() => props.company?.timezone ?? 'America/Santo_Domingo')
const companyActive = computed(() => !!props.company?.active)

// ----------
// UI helpers
// ----------
function presetLabel(p: RangePayload[ 'preset' ]) {
    if (p === 'mtd') return 'MTD'
    if (p === 'ytd') return 'YTD'
    if (p === '30d') return 'Últ. 30 días'
    if (p === '7d') return 'Últ. 7 días'
    return 'Custom'
}

function money(value: any) {
    const n = Number(value ?? 0)
    try {
        return new Intl.NumberFormat('es-DO', {
            style: 'currency',
            currency: companyCurrency.value,
            maximumFractionDigits: 0,
        }).format(n)
    } catch {
        return `${n.toFixed(0)} ${companyCurrency.value}`
    }
}

function num(value: any) {
    const n = Number(value ?? 0)
    return new Intl.NumberFormat('es-DO', { maximumFractionDigits: 0 }).format(n)
}

function pct(value: any) {
    if (value == null) return '—'
    const n = Number(value)
    const sign = n > 0 ? '+' : ''
    return `${sign}${n.toFixed(1)}%`
}

function levelClasses(level: AlertLevel) {
    if (level === 'critical') return 'border-rose-500/40 bg-rose-500/5'
    if (level === 'warning') return 'border-amber-500/40 bg-amber-500/5'
    return 'border-sky-500/40 bg-sky-500/5'
}

function levelBadge(level: AlertLevel) {
    if (level === 'critical') return { text: 'Crítico', cls: 'bg-rose-600 text-white' }
    if (level === 'warning') return { text: 'Atención', cls: 'bg-amber-600 text-white' }
    return { text: 'Info', cls: 'bg-sky-600 text-white' }
}

function dgiiTokenBadge(status?: DgiiPayload[ 'tokenStatus' ]) {
    if (status === 'ok') return { text: 'Token OK', cls: 'bg-emerald-600 text-white' }
    if (status === 'warn') return { text: 'Token por vencer', cls: 'bg-amber-600 text-white' }
    if (status === 'expired') return { text: 'Token vencido', cls: 'bg-rose-600 text-white' }
    return { text: 'Sin token', cls: 'bg-muted text-foreground' }
}

function dgiiCertBadge(status?: DgiiPayload[ 'certStatus' ]) {
    if (status === 'ok') return { text: 'Cert OK', cls: 'bg-emerald-600 text-white' }
    if (status === 'warn') return { text: 'Cert revisar', cls: 'bg-amber-600 text-white' }
    if (status === 'invalid') return { text: 'Cert inválido', cls: 'bg-rose-600 text-white' }
    return { text: 'Sin cert', cls: 'bg-muted text-foreground' }
}

const setupSteps = computed(() => {
    const steps = [
        { label: 'Empresa', ok: !!props.company },
        { label: 'Perfil fiscal', ok: !!props.taxProfileReady },
        { label: 'Cumplimiento', ok: nextDue.value.length > 0 }, // solo señal (real: usar flags)
        { label: 'DGII', ok: dgii.value?.tokenStatus === 'ok' || dgii.value?.tokenStatus === 'warn' },
    ]
    const done = steps.filter((s) => s.ok).length
    return { steps, done, total: steps.length }
})
</script>

<template>

    <Head title="LaudaERP" />

    <ErpLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Top header -->
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
                        <Badge v-if="companyActive" class="bg-emerald-600 text-white">Activa</Badge>
                        <Badge v-else class="bg-muted text-foreground">Inactiva</Badge>
                    </div>

                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="font-medium text-foreground">{{ companyName }}</span>
                        <span class="mx-2 opacity-60">•</span>
                        slug: <span class="font-mono">{{ companySlug }}</span>
                        <span class="mx-2 opacity-60">•</span>
                        <span class="font-mono">{{ companyCurrency }}</span>
                        <span class="mx-2 opacity-60">•</span>
                        <span class="font-mono">{{ companyTimezone }}</span>
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <Badge variant="outline">
                            Periodo: <span class="ml-2 font-mono">{{ range.from }}</span> → <span class="font-mono">{{ range.to }}</span>
                            <span class="ml-2 opacity-70">({{ presetLabel(range.preset) }})</span>
                        </Badge>

                        <Badge variant="outline">
                            Setup: {{ setupSteps.done }}/{{ setupSteps.total }}
                        </Badge>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link href="/subscriber/company">Configurar empresa</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/erp/compliance">Cumplimiento</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/erp/dgii">DGII</Link>
                    </Button>
                </div>
            </div>

            <!-- Alerts -->
            <div v-if="alerts.length" class="grid gap-3 md:grid-cols-2">
                <Card v-for="(a, idx) in alerts" :key="idx" class="border-l-4" :class="levelClasses(a.level)">
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <CardTitle class="text-base">{{ a.title }}</CardTitle>
                                <CardDescription v-if="a.description" class="mt-1">
                                    {{ a.description }}
                                </CardDescription>
                            </div>
                            <Badge :class="levelBadge(a.level).cls">{{ levelBadge(a.level).text }}</Badge>
                        </div>
                    </CardHeader>

                    <CardContent v-if="a.cta" class="pt-0">
                        <Button as-child size="sm" variant="outline">
                            <Link :href="a.cta.href">{{ a.cta.label }}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- KPI grid -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card class="md:col-span-2">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Ingresos</CardTitle>
                        <CardDescription>Facturación en el periodo</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-end justify-between gap-3">
                        <div class="text-2xl font-semibold">{{ money(kpis.revenue?.value) }}</div>
                        <div class="text-right text-sm text-muted-foreground">
                            <div>Δ {{ pct(kpis.revenue?.deltaPct) }}</div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Cobros</CardTitle>
                        <CardDescription>Entradas de efectivo</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-end justify-between gap-3">
                        <div class="text-xl font-semibold">{{ money(kpis.collections?.value) }}</div>
                        <div class="text-right text-sm text-muted-foreground">Δ {{ pct(kpis.collections?.deltaPct) }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Caja / Bancos</CardTitle>
                        <CardDescription>Balance actual</CardDescription>
                    </CardHeader>
                    <CardContent class="text-xl font-semibold">{{ money(kpis.cashBalance?.value) }}</CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Cuentas por cobrar</CardTitle>
                        <CardDescription>A/R outstanding</CardDescription>
                    </CardHeader>
                    <CardContent class="text-xl font-semibold">{{ money(kpis.arOutstanding?.value) }}</CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Cuentas por pagar</CardTitle>
                        <CardDescription>A/P outstanding</CardDescription>
                    </CardHeader>
                    <CardContent class="text-xl font-semibold">{{ money(kpis.apOutstanding?.value) }}</CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Órdenes</CardTitle>
                        <CardDescription>Volumen en periodo</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-end justify-between gap-3">
                        <div class="text-xl font-semibold">{{ num(kpis.orders?.value) }}</div>
                        <div class="text-right text-sm text-muted-foreground">Δ {{ pct(kpis.orders?.deltaPct) }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Clientes activos</CardTitle>
                        <CardDescription>Interacción / compras</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-end justify-between gap-3">
                        <div class="text-xl font-semibold">{{ num(kpis.activeCustomers?.value) }}</div>
                        <div class="text-right text-sm text-muted-foreground">Δ {{ pct(kpis.activeCustomers?.deltaPct) }}</div>
                    </CardContent>
                </Card>

                <Card class="md:col-span-2">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">Cumplimiento</CardTitle>
                        <CardDescription>Vencidas y por vencer</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="rounded-md border px-3 py-2">
                                <div class="text-xs text-muted-foreground">Vencidas</div>
                                <div class="text-lg font-semibold">{{ num(kpis.compliance?.overdue) }}</div>
                            </div>
                            <div class="rounded-md border px-3 py-2">
                                <div class="text-xs text-muted-foreground">Próx. 7 días</div>
                                <div class="text-lg font-semibold">{{ num(kpis.compliance?.dueSoon7d) }}</div>
                            </div>
                        </div>

                        <Button as-child variant="outline" size="sm">
                            <Link href="/erp/compliance">Abrir</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Second row: DGII + NextDue + Activity -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- DGII -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">DGII</CardTitle>
                        <CardDescription>Estado de token y certificados</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <Badge :class="dgiiTokenBadge(dgii.tokenStatus).cls">{{ dgiiTokenBadge(dgii.tokenStatus).text }}</Badge>
                            <Badge :class="dgiiCertBadge(dgii.certStatus).cls">{{ dgiiCertBadge(dgii.certStatus).text }}</Badge>
                            <Badge variant="outline">{{ dgii.environment ?? 'env —' }}</Badge>
                        </div>

                        <div class="text-sm text-muted-foreground space-y-1">
                            <div>
                                Expira:
                                <span class="ml-1 font-mono text-foreground">{{ dgii.tokenExpiresAt ?? '—' }}</span>
                            </div>
                            <div>
                                Últ. refresh:
                                <span class="ml-1 font-mono text-foreground">{{ dgii.lastTokenRefreshAt ?? '—' }}</span>
                            </div>
                        </div>

                        <Separator />

                        <div class="flex gap-2">
                            <Button as-child variant="outline" size="sm">
                                <Link href="/erp/dgii">Configurar</Link>
                            </Button>
                            <Button as-child variant="outline" size="sm">
                                <Link href="/subscriber/company">Perfil fiscal</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Next due obligations -->
                <Card class="lg:col-span-2">
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <CardTitle class="text-base">Próximas obligaciones</CardTitle>
                                <CardDescription>Vencidas o por vencer</CardDescription>
                            </div>
                            <Button as-child variant="outline" size="sm">
                                <Link href="/erp/compliance">Ver calendario</Link>
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div v-if="nextDue.length === 0" class="relative overflow-hidden rounded-md border">
                            <div class="p-6 text-sm text-muted-foreground">Aún no hay obligaciones materializadas para esta empresa.</div>
                            <div class="absolute inset-0 opacity-40 pointer-events-none">
                                <PlaceholderPattern />
                            </div>
                        </div>

                        <div v-else class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/30">
                                    <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <th class="px-3 py-2">Vence</th>
                                        <th class="px-3 py-2">Autoridad</th>
                                        <th class="px-3 py-2">Código</th>
                                        <th class="px-3 py-2">Obligación</th>
                                        <th class="px-3 py-2">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(o, i) in nextDue.slice(0, 10)" :key="i" class="border-t">
                                        <td class="px-3 py-2 font-mono text-xs">{{ o.due_on }}</td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ o.authority }}</div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <Badge variant="outline" class="font-mono">{{ o.code }}</Badge>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ o.name }}</div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <Badge v-if="o.status === 'overdue'" class="bg-rose-600 text-white">Vencida</Badge>
                                            <Badge v-else class="bg-amber-600 text-white">Pendiente</Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Charts row -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Cashflow</CardTitle>
                        <CardDescription>Entradas vs salidas (placeholder por ahora)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="relative aspect-video overflow-hidden rounded-md border">
                            <PlaceholderPattern />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Ventas</CardTitle>
                        <CardDescription>Tendencia por periodo (placeholder por ahora)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="relative aspect-video overflow-hidden rounded-md border">
                            <PlaceholderPattern />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Activity feed -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Actividad reciente</CardTitle>
                    <CardDescription>Últimos eventos del sistema</CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="activity.length === 0" class="text-sm text-muted-foreground">
                        No hay actividad reciente (o el módulo de auditoría no está habilitado).
                    </div>

                    <div v-else class="divide-y rounded-md border">
                        <div v-for="(a, idx) in activity.slice(0, 12)" :key="idx" class="p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="text-sm">
                                    <span class="font-medium">{{ a.actor }}</span>
                                    <span class="mx-2 opacity-50">•</span>
                                    <span class="font-mono text-xs text-muted-foreground">{{ a.event }}</span>
                                </div>
                                <div class="font-mono text-xs text-muted-foreground">{{ a.at }}</div>
                            </div>

                            <div v-if="a.meta" class="mt-1 text-xs text-muted-foreground">
                                <pre class="max-h-24 overflow-auto rounded-md bg-muted/30 p-2">{{ a.meta }}</pre>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </ErpLayout>
</template>