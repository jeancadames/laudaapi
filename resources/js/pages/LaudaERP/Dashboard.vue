<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import ErpLayout from '@/layouts/ErpLayout.vue'
import type { BreadcrumbItem } from '@/types'

// shadcn
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

// optional placeholder (si lo tienes)
import PlaceholderPattern from '@/components/PlaceholderPattern.vue'

type AlertLevel = 'info' | 'warning' | 'critical'

type CompanyPayload = {
    id: number
    name: string
    slug: string
    currency: string
    timezone: string
    active?: boolean | number | null
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
    revenue?: { value: number | null; deltaPct?: number | null }

    // P&L
    costs?: { value: number | null; deltaPct?: number | null } // COGS
    expenses?: { value: number | null; deltaPct?: number | null } // OPEX
    otherIncome?: { value: number | null; deltaPct?: number | null }
    taxes?: { value: number | null; deltaPct?: number | null }
    grossProfit?: { value: number | null; deltaPct?: number | null }
    netProfit?: { value: number | null; deltaPct?: number | null }

    // Operativo
    collections?: { value: number | null; deltaPct?: number | null }
    cashBalance?: { value: number | null }
    arOutstanding?: { value: number | null }
    apOutstanding?: { value: number | null }
    orders?: { value: number | null; deltaPct?: number | null }
    activeCustomers?: { value: number | null; deltaPct?: number | null }

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

type LiquidationStatus = 'overdue' | 'due' | 'draft' | 'paid' | 'unknown'
type LiquidationItem = {
    authority: 'DGII' | 'TSS'
    code: string
    name: string
    period: string // ej: 2026-01
    due_on?: string | null
    amount?: number | null
    status?: LiquidationStatus
    href?: string | null
}

const props = defineProps<{
    company: CompanyPayload | null
    taxProfileReady: boolean

    range?: RangePayload
    alerts?: AlertPayload[]
    kpis?: KpisPayload
    dgii?: DgiiPayload
    compliance?: { nextDue?: ComplianceItem[] }

    // impuestos/tss a liquidar (UI)
    liquidations?: { items?: LiquidationItem[] }

    activity?: ActivityItem[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LaudaERP', href: '/erp' },
    { title: 'Dashboard', href: '/erp' },
]

// ----------------
// Tabs
// ----------------
const tab = ref<'overview' | 'finance' | 'compliance' | 'ops' | 'activity'>('overview')

// ----------------
// Fallbacks / state
// ----------------
const range = computed<RangePayload>(() => {
    const r = props.range
    if (r?.from && r?.to) return r
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

// ✅ Fix: si ERP carga y backend no manda `active`, asumimos activa (no inventes inactiva)
const companyActive = computed(() => {
    if (!props.company) return false
    const a = props.company.active
    if (a === null || a === undefined) return true
    return !!a
})

// ----------------
// Helpers UI
// ----------------
function presetLabel(p: RangePayload[ 'preset' ]) {
    if (p === 'mtd') return 'MTD'
    if (p === 'ytd') return 'YTD'
    if (p === '30d') return 'Últ. 30 días'
    if (p === '7d') return 'Últ. 7 días'
    return 'Custom'
}

function moneyMaybe(value: any) {
    if (value === null || value === undefined) return '—'
    const n = Number(value)
    if (!Number.isFinite(n)) return '—'
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

function numMaybe(value: any) {
    if (value === null || value === undefined) return '—'
    const n = Number(value)
    if (!Number.isFinite(n)) return '—'
    return new Intl.NumberFormat('es-DO', { maximumFractionDigits: 0 }).format(n)
}

function pct(value: any) {
    if (value == null) return '—'
    const n = Number(value)
    if (!Number.isFinite(n)) return '—'
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

function liquidationBadge(status?: LiquidationStatus) {
    if (status === 'paid') return { text: 'Pagada', cls: 'bg-emerald-600 text-white' }
    if (status === 'overdue') return { text: 'Vencida', cls: 'bg-rose-600 text-white' }
    if (status === 'due') return { text: 'Pendiente', cls: 'bg-amber-600 text-white' }
    if (status === 'draft') return { text: 'Borrador', cls: 'bg-slate-600 text-white' }
    return { text: '—', cls: 'bg-muted text-foreground' }
}

// ----------------
// P&L derivado (future-proof)
// ----------------
function nOrNull(v: any): number | null {
    if (v === null || v === undefined) return null
    const n = Number(v)
    return Number.isFinite(n) ? n : null
}
function add(a: number | null, b: number | null): number | null {
    if (a == null && b == null) return null
    return (a ?? 0) + (b ?? 0)
}
function sub(a: number | null, b: number | null): number | null {
    if (a == null || b == null) return null
    return a - b
}
function marginPct(num: number | null, den: number | null): number | null {
    if (num == null || den == null) return null
    if (den === 0) return null
    return (num / den) * 100
}

const pnl = computed(() => {
    const rev = nOrNull(kpis.value.revenue?.value)
    const cogs = nOrNull(kpis.value.costs?.value)
    const opex = nOrNull(kpis.value.expenses?.value)
    const other = nOrNull(kpis.value.otherIncome?.value)
    const tax = nOrNull(kpis.value.taxes?.value)

    const gross = nOrNull(kpis.value.grossProfit?.value) ?? sub(rev, cogs)
    const net = nOrNull(kpis.value.netProfit?.value) ?? sub(add(sub(gross, opex), other), tax)

    return {
        rev,
        cogs,
        opex,
        other,
        tax,
        gross,
        net,
        grossMargin: marginPct(gross, rev),
        netMargin: marginPct(net, rev),
    }
})

// ----------------
// Compliance rollups
// ----------------
const complianceRollup = computed(() => {
    let overdue = 0
    let due = 0
    for (const it of nextDue.value) {
        if (it.status === 'overdue') overdue++
        else due++
    }
    return { total: nextDue.value.length, overdue, due }
})

// ----------------
// Liquidations (UI)
// ----------------
const liquidationItems = computed<LiquidationItem[]>(() => {
    const items = props.liquidations?.items ?? []
    if (items.length) return items

    // placeholder: UI sin data aún
    return [
        { authority: 'DGII', code: 'IT-1', name: 'ITBIS', period: '—', due_on: '—', amount: null, status: 'unknown', href: '/erp/finance/liquidations/it-1' },
        { authority: 'DGII', code: 'IR-3', name: 'Retenciones', period: '—', due_on: '—', amount: null, status: 'unknown', href: '/erp/finance/liquidations/ir-3' },
        { authority: 'DGII', code: '606', name: 'Compras', period: '—', due_on: '—', amount: null, status: 'unknown', href: '/erp/finance/liquidations/606' },
        { authority: 'DGII', code: '607', name: 'Ventas', period: '—', due_on: '—', amount: null, status: 'unknown', href: '/erp/finance/liquidations/607' },
        { authority: 'TSS', code: 'TSS', name: 'Seguridad social', period: '—', due_on: '—', amount: null, status: 'unknown', href: '/erp/finance/liquidations/tss' },
    ]
})

const liquidationRollup = computed(() => {
    let overdue = 0
    let due = 0
    let totalPay = 0
    let anyAmount = false

    for (const it of liquidationItems.value) {
        if (it.status === 'overdue') overdue++
        if (it.status === 'due') due++
        const amt = nOrNull(it.amount)
        if (amt != null) {
            anyAmount = true
            totalPay += amt
        }
    }

    return {
        overdue,
        due,
        totalPay: anyAmount ? totalPay : null,
    }
})

// ----------------
// Priority blocks (por tipo / prioridad)
// ----------------
const hasCritical = computed(() => {
    const dgiiCritical = dgii.value.tokenStatus === 'expired' || dgii.value.tokenStatus === 'missing'
    const complianceCritical = complianceRollup.value.overdue > 0
    const liquidationCritical = liquidationRollup.value.overdue > 0
    const profileCritical = !props.taxProfileReady
    return dgiiCritical || complianceCritical || liquidationCritical || profileCritical
})

const priorityItems = computed(() => {
    const items: Array<{ level: 'critical' | 'warning' | 'info'; title: string; hint: string; href?: string }> = []

    if (!props.taxProfileReady) items.push({ level: 'critical', title: 'Perfil fiscal pendiente', hint: 'Completa los datos fiscales para habilitar impuestos/calendario.', href: '/erp/finance/pnl' })

    if (dgii.value.tokenStatus === 'expired') items.push({ level: 'critical', title: 'Token DGII vencido', hint: 'No podrás autenticar/firmar/envíar.', href: '/erp/dgii/token' })
    if (dgii.value.tokenStatus === 'missing') items.push({ level: 'critical', title: 'Token DGII faltante', hint: 'Genera token para operar DGII.', href: '/erp/dgii/token' })
    if (dgii.value.tokenStatus === 'warn') items.push({ level: 'warning', title: 'Token DGII por vencer', hint: `Expira: ${dgii.value.tokenExpiresAt ?? '—'}`, href: '/erp/dgii/token' })

    if (complianceRollup.value.overdue > 0) items.push({ level: 'critical', title: 'Obligaciones vencidas', hint: `${complianceRollup.value.overdue} vencida(s).`, href: '/erp/compliance' })
    if (liquidationRollup.value.overdue > 0) items.push({ level: 'critical', title: 'Liquidaciones vencidas', hint: `${liquidationRollup.value.overdue} vencida(s).`, href: '/erp/finance/liquidations' })

    if (complianceRollup.value.due > 0) items.push({ level: 'warning', title: 'Obligaciones pendientes', hint: `${complianceRollup.value.due} por vencer.`, href: '/erp/compliance' })
    if (liquidationRollup.value.due > 0) items.push({ level: 'warning', title: 'Liquidaciones pendientes', hint: `${liquidationRollup.value.due} por pagar.`, href: '/erp/finance/liquidations' })

    if (!items.length) items.push({ level: 'info', title: 'Todo en orden', hint: 'No hay bloqueos críticos detectados.' })
    return items
})

function priorityBadge(level: 'critical' | 'warning' | 'info') {
    if (level === 'critical') return { text: 'Urgente', cls: 'bg-rose-600 text-white' }
    if (level === 'warning') return { text: 'Atención', cls: 'bg-amber-600 text-white' }
    return { text: 'OK', cls: 'bg-emerald-600 text-white' }
}
</script>

<template>

    <Head title="LaudaERP" />

    <ErpLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-4 p-4">
            <!-- HEADER (siempre arriba, no cambia por tabs) -->
            <Card>
                <CardContent class="p-4 md:p-5">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
                                <Badge v-if="companyActive" class="bg-emerald-600 text-white">Activa</Badge>
                                <Badge v-else class="bg-muted text-foreground">Inactiva</Badge>
                                <Badge v-if="!taxProfileReady" class="bg-amber-600 text-white">Perfil fiscal pendiente</Badge>
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
                                    Periodo:
                                    <span class="ml-2 font-mono">{{ range.from }}</span> → <span class="font-mono">{{ range.to }}</span>
                                    <span class="ml-2 opacity-70">({{ presetLabel(range.preset) }})</span>
                                </Badge>

                                <Badge variant="outline">
                                    DGII: <span class="ml-2">{{ dgiiTokenBadge(dgii.tokenStatus).text }}</span>
                                </Badge>

                                <Badge variant="outline">
                                    Cumplimiento: <span class="ml-2 font-mono">{{ complianceRollup.overdue }}</span> vencidas · <span class="font-mono">{{ complianceRollup.due }}</span> pendientes
                                </Badge>

                                <Badge variant="outline">
                                    A pagar: <span class="ml-2 font-mono">{{ moneyMaybe(liquidationRollup.totalPay) }}</span>
                                </Badge>
                            </div>
                        </div>

                        <!-- Actions minimal (no link a empresa) -->
                        <div class="flex w-full flex-col gap-2 md:w-[320px]">
                            <Button as-child variant="outline" class="w-full justify-between">
                                <Link href="/erp/finance/pnl">
                                    <span>Estado de resultados</span>
                                    <span class="font-mono text-xs opacity-70">P&amp;L</span>
                                </Link>
                            </Button>

                            <Button as-child variant="outline" class="w-full justify-between">
                                <Link href="/erp/finance/liquidations">
                                    <span>Impuestos y TSS</span>
                                    <span class="font-mono text-xs opacity-70">Liquidaciones</span>
                                </Link>
                            </Button>

                            <Button as-child variant="outline" class="w-full justify-between">
                                <Link href="/erp/compliance">
                                    <span>Cumplimiento</span>
                                    <span class="font-mono text-xs opacity-70">Calendario</span>
                                </Link>
                            </Button>

                            <Button as-child variant="outline" class="w-full justify-between">
                                <Link href="/erp/dgii">
                                    <span>DGII</span>
                                    <span class="font-mono text-xs opacity-70">{{ dgii.environment ?? '—' }}</span>
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- TABS: bloques por tipo y prioridad -->
            <Tabs v-model="tab" class="space-y-4">
                <TabsList class="grid w-full grid-cols-2 md:grid-cols-5">
                    <TabsTrigger value="overview">Resumen</TabsTrigger>
                    <TabsTrigger value="finance">Finanzas</TabsTrigger>
                    <TabsTrigger value="compliance">Impuestos &amp; Cumplimiento</TabsTrigger>
                    <TabsTrigger value="ops">Operación</TabsTrigger>
                    <TabsTrigger value="activity">Actividad</TabsTrigger>
                </TabsList>

                <!-- ===================== -->
                <!-- TAB: OVERVIEW / PRIORIDAD -->
                <!-- ===================== -->
                <TabsContent value="overview" class="space-y-4">
                    <!-- PRIORIDAD 1: Urgencias -->
                    <Card :class="hasCritical ? 'border-rose-500/30' : ''">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">Prioridad</CardTitle>
                            <CardDescription>Lo que puede bloquear operación (primero se resuelve esto)</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div v-for="(it, idx) in priorityItems.slice(0, 4)" :key="idx" class="rounded-lg border p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-medium">{{ it.title }}</div>
                                            <div class="mt-1 text-sm text-muted-foreground">{{ it.hint }}</div>
                                        </div>
                                        <Badge :class="priorityBadge(it.level).cls">{{ priorityBadge(it.level).text }}</Badge>
                                    </div>

                                    <div v-if="it.href" class="mt-3">
                                        <Button as-child size="sm" variant="outline">
                                            <Link :href="it.href">Resolver</Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="alerts.length" class="grid gap-3 md:grid-cols-2">
                                <Card v-for="(a, idx) in alerts" :key="idx" class="border-l-4" :class="levelClasses(a.level)">
                                    <CardHeader class="pb-2">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle class="text-base">{{ a.title }}</CardTitle>
                                                <CardDescription v-if="a.description" class="mt-1">{{ a.description }}</CardDescription>
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
                        </CardContent>
                    </Card>

                    <!-- PRIORIDAD 2: Snapshot del negocio -->
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">Ingresos</CardTitle>
                                <CardDescription>Periodo</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-1">
                                <div class="text-2xl font-semibold">{{ moneyMaybe(kpis.revenue?.value) }}</div>
                                <div class="text-xs text-muted-foreground">Δ {{ pct(kpis.revenue?.deltaPct) }}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">Beneficio neto</CardTitle>
                                <CardDescription>Periodo</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-1">
                                <div class="text-2xl font-semibold">{{ moneyMaybe(pnl.net) }}</div>
                                <div class="text-xs text-muted-foreground">
                                    Margen: <span class="font-mono">{{ pnl.netMargin == null ? '—' : pnl.netMargin.toFixed(1) + '%' }}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">Caja / Bancos</CardTitle>
                                <CardDescription>Actual</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-1">
                                <div class="text-2xl font-semibold">{{ moneyMaybe(kpis.cashBalance?.value) }}</div>
                                <div class="text-xs text-muted-foreground">
                                    Cobros: <span class="font-mono">{{ moneyMaybe(kpis.collections?.value) }}</span>
                                    <span class="opacity-70"> (Δ {{ pct(kpis.collections?.deltaPct) }})</span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">A pagar</CardTitle>
                                <CardDescription>DGII + TSS</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-1">
                                <div class="text-2xl font-semibold">{{ moneyMaybe(liquidationRollup.totalPay) }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ liquidationRollup.overdue }} vencidas · {{ liquidationRollup.due }} pendientes
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- PRIORIDAD 3: Vista rápida P&L (mini) -->
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <CardTitle class="text-base">Estado de resultados (mini)</CardTitle>
                                    <CardDescription>Ingresos · Costos · Gastos · Beneficio</CardDescription>
                                </div>
                                <Button as-child size="sm" variant="outline">
                                    <Link href="/erp/finance/pnl">Abrir P&amp;L</Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-lg border p-3">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Ingresos</span>
                                    <span class="font-mono font-medium">{{ moneyMaybe(pnl.rev) }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Costos</span>
                                    <span class="font-mono font-medium">{{ moneyMaybe(pnl.cogs) }}</span>
                                </div>
                                <Separator class="my-3" />
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Beneficio bruto</span>
                                    <span class="font-mono font-semibold">{{ moneyMaybe(pnl.gross) }}</span>
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Margen bruto:
                                    <span class="font-mono">{{ pnl.grossMargin == null ? '—' : pnl.grossMargin.toFixed(1) + '%' }}</span>
                                </div>
                            </div>

                            <div class="rounded-lg border p-3">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Gastos</span>
                                    <span class="font-mono font-medium">{{ moneyMaybe(pnl.opex) }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Impuestos</span>
                                    <span class="font-mono font-medium">{{ moneyMaybe(pnl.tax) }}</span>
                                </div>
                                <Separator class="my-3" />
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Beneficio neto</span>
                                    <span class="font-mono font-semibold">{{ moneyMaybe(pnl.net) }}</span>
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Margen neto:
                                    <span class="font-mono">{{ pnl.netMargin == null ? '—' : pnl.netMargin.toFixed(1) + '%' }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- ===================== -->
                <!-- TAB: FINANCE -->
                <!-- ===================== -->
                <TabsContent value="finance" class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-12">
                        <!-- P&L detallado -->
                        <Card class="lg:col-span-7">
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base">Estado de resultados</CardTitle>
                                <CardDescription>Ingresos · Costos · Gastos · Beneficios</CardDescription>
                            </CardHeader>

                            <CardContent class="space-y-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="rounded-lg border p-3">
                                        <div class="text-xs text-muted-foreground">Ingresos</div>
                                        <div class="mt-1 text-2xl font-semibold">{{ moneyMaybe(pnl.rev) }}</div>
                                        <div class="mt-3 flex items-center justify-between text-sm">
                                            <span class="text-muted-foreground">Costos (COGS)</span>
                                            <span class="font-mono font-medium">{{ moneyMaybe(pnl.cogs) }}</span>
                                        </div>
                                        <Separator class="my-3" />
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-muted-foreground">Beneficio bruto</span>
                                            <span class="font-mono font-semibold">{{ moneyMaybe(pnl.gross) }}</span>
                                        </div>
                                        <div class="mt-1 text-xs text-muted-foreground">
                                            Margen bruto: <span class="font-mono">{{ pnl.grossMargin == null ? '—' : pnl.grossMargin.toFixed(1) + '%' }}</span>
                                        </div>
                                    </div>

                                    <div class="rounded-lg border p-3">
                                        <div class="text-xs text-muted-foreground">Beneficio neto</div>
                                        <div class="mt-1 text-2xl font-semibold">{{ moneyMaybe(pnl.net) }}</div>

                                        <div class="mt-3 flex items-center justify-between text-sm">
                                            <span class="text-muted-foreground">Gastos (OPEX)</span>
                                            <span class="font-mono font-medium">{{ moneyMaybe(pnl.opex) }}</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between text-sm">
                                            <span class="text-muted-foreground">Otros ingresos</span>
                                            <span class="font-mono font-medium">{{ moneyMaybe(pnl.other) }}</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between text-sm">
                                            <span class="text-muted-foreground">Impuestos</span>
                                            <span class="font-mono font-medium">{{ moneyMaybe(pnl.tax) }}</span>
                                        </div>

                                        <div class="mt-2 text-xs text-muted-foreground">
                                            Margen neto: <span class="font-mono">{{ pnl.netMargin == null ? '—' : pnl.netMargin.toFixed(1) + '%' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="relative overflow-hidden rounded-lg border">
                                    <div class="p-3">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="font-medium">Tendencia P&amp;L</div>
                                                <div class="text-xs text-muted-foreground">Placeholder (chart)</div>
                                            </div>
                                            <Button as-child size="sm" variant="outline">
                                                <Link href="/erp/finance/pnl">Detalles</Link>
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="h-40 opacity-35">
                                        <PlaceholderPattern />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Caja / AR / AP -->
                        <div class="lg:col-span-5 space-y-4">
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardTitle class="text-base">Caja y liquidez</CardTitle>
                                    <CardDescription>Snapshot operativo</CardDescription>
                                </CardHeader>
                                <CardContent class="grid gap-3 md:grid-cols-2 lg:grid-cols-1">
                                    <div class="rounded-lg border bg-muted/20 p-3">
                                        <div class="text-xs text-muted-foreground">Caja / Bancos</div>
                                        <div class="mt-1 text-xl font-semibold">{{ moneyMaybe(kpis.cashBalance?.value) }}</div>
                                    </div>

                                    <div class="rounded-lg border bg-muted/20 p-3">
                                        <div class="text-xs text-muted-foreground">Cuentas por cobrar (A/R)</div>
                                        <div class="mt-1 text-xl font-semibold">{{ moneyMaybe(kpis.arOutstanding?.value) }}</div>
                                    </div>

                                    <div class="rounded-lg border bg-muted/20 p-3">
                                        <div class="text-xs text-muted-foreground">Cuentas por pagar (A/P)</div>
                                        <div class="mt-1 text-xl font-semibold">{{ moneyMaybe(kpis.apOutstanding?.value) }}</div>
                                    </div>

                                    <div class="rounded-lg border bg-muted/20 p-3">
                                        <div class="text-xs text-muted-foreground">Cobros (periodo)</div>
                                        <div class="mt-1 text-xl font-semibold">{{ moneyMaybe(kpis.collections?.value) }}</div>
                                        <div class="mt-1 text-xs text-muted-foreground">Δ {{ pct(kpis.collections?.deltaPct) }}</div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader class="pb-2">
                                    <CardTitle class="text-base">Cashflow</CardTitle>
                                    <CardDescription>Entradas vs salidas (placeholder)</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div class="relative aspect-video overflow-hidden rounded-lg border opacity-35">
                                        <PlaceholderPattern />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </TabsContent>

                <!-- ===================== -->
                <!-- TAB: TAX & COMPLIANCE -->
                <!-- ===================== -->
                <TabsContent value="compliance" class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-12">
                        <!-- DGII -->
                        <Card class="lg:col-span-4">
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base">DGII</CardTitle>
                                <CardDescription>Token y certificados</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="flex flex-wrap gap-2">
                                    <Badge :class="dgiiTokenBadge(dgii.tokenStatus).cls">{{ dgiiTokenBadge(dgii.tokenStatus).text }}</Badge>
                                    <Badge :class="dgiiCertBadge(dgii.certStatus).cls">{{ dgiiCertBadge(dgii.certStatus).text }}</Badge>
                                    <Badge variant="outline">{{ dgii.environment ?? 'env —' }}</Badge>
                                </div>

                                <div class="grid gap-2 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground">Expira</span>
                                        <span class="font-mono">{{ dgii.tokenExpiresAt ?? '—' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground">Últ. refresh</span>
                                        <span class="font-mono">{{ dgii.lastTokenRefreshAt ?? '—' }}</span>
                                    </div>
                                </div>

                                <Separator />

                                <div class="grid gap-2">
                                    <Button as-child size="sm" variant="outline">
                                        <Link href="/erp/dgii/token">Generar / refrescar token</Link>
                                    </Button>
                                    <Button as-child size="sm" variant="ghost">
                                        <Link href="/erp/dgii/certificates">Ver certificados</Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Obligaciones -->
                        <Card class="lg:col-span-8">
                            <CardHeader class="pb-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <CardTitle class="text-base">Cumplimiento</CardTitle>
                                        <CardDescription>{{ complianceRollup.overdue }} vencidas · {{ complianceRollup.due }} pendientes</CardDescription>
                                    </div>
                                    <Button as-child size="sm" variant="outline">
                                        <Link href="/erp/compliance">Abrir calendario</Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="nextDue.length === 0" class="relative overflow-hidden rounded-lg border">
                                    <div class="p-4 text-sm text-muted-foreground">Sin obligaciones materializadas.</div>
                                    <div class="absolute inset-0 opacity-30 pointer-events-none">
                                        <PlaceholderPattern />
                                    </div>
                                </div>

                                <div v-else class="overflow-hidden rounded-lg border">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-muted/30">
                                            <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                <th class="px-3 py-2">Vence</th>
                                                <th class="px-3 py-2">Autoridad</th>
                                                <th class="px-3 py-2">Código</th>
                                                <th class="px-3 py-2">Obligación</th>
                                                <th class="px-3 py-2 text-right">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(o, i) in nextDue.slice(0, 10)" :key="i" class="border-t">
                                                <td class="px-3 py-2 font-mono text-xs">{{ o.due_on }}</td>
                                                <td class="px-3 py-2 text-xs text-muted-foreground">{{ o.authority }}</td>
                                                <td class="px-3 py-2">
                                                    <Badge variant="outline" class="font-mono">{{ o.code }}</Badge>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <div class="font-medium">{{ o.name }}</div>
                                                </td>
                                                <td class="px-3 py-2 text-right">
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

                    <!-- Liquidaciones -->
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <CardTitle class="text-base">Impuestos y TSS a liquidar</CardTitle>
                                    <CardDescription>DGII · TSS (placeholder por ahora)</CardDescription>
                                </div>
                                <Button as-child size="sm" variant="outline">
                                    <Link href="/erp/finance/liquidations">Abrir</Link>
                                </Button>
                            </div>
                        </CardHeader>

                        <CardContent class="space-y-3">
                            <div class="overflow-hidden rounded-lg border">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-muted/30">
                                        <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <th class="px-3 py-2">Tipo</th>
                                            <th class="px-3 py-2">Periodo</th>
                                            <th class="px-3 py-2">Vence</th>
                                            <th class="px-3 py-2 text-right">Monto</th>
                                            <th class="px-3 py-2 text-right"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(it, idx) in liquidationItems" :key="idx" class="border-t">
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-2">
                                                    <Badge variant="outline" class="font-mono">{{ it.authority }}</Badge>
                                                    <Badge variant="outline" class="font-mono">{{ it.code }}</Badge>
                                                </div>
                                                <div class="mt-1 text-xs text-muted-foreground">{{ it.name }}</div>
                                            </td>

                                            <td class="px-3 py-2 font-mono text-xs">{{ it.period }}</td>
                                            <td class="px-3 py-2 font-mono text-xs">{{ it.due_on ?? '—' }}</td>

                                            <td class="px-3 py-2 text-right font-mono text-xs">
                                                {{ moneyMaybe(it.amount) }}
                                                <div class="mt-1">
                                                    <Badge :class="liquidationBadge(it.status).cls">{{ liquidationBadge(it.status).text }}</Badge>
                                                </div>
                                            </td>

                                            <td class="px-3 py-2 text-right">
                                                <Button v-if="it.href" as-child size="sm" variant="ghost" class="h-8 px-2">
                                                    <Link :href="it.href">Abrir</Link>
                                                </Button>
                                                <Button v-else size="sm" variant="ghost" class="h-8 px-2" disabled>Abrir</Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="grid gap-2 md:grid-cols-3">
                                <div class="rounded-lg border bg-muted/20 p-3">
                                    <div class="text-xs text-muted-foreground">Total a pagar</div>
                                    <div class="mt-1 font-mono font-semibold">{{ moneyMaybe(liquidationRollup.totalPay) }}</div>
                                </div>
                                <div class="rounded-lg border bg-muted/20 p-3">
                                    <div class="text-xs text-muted-foreground">Vencidas</div>
                                    <div class="mt-1 font-mono font-semibold">{{ liquidationRollup.overdue }}</div>
                                </div>
                                <div class="rounded-lg border bg-muted/20 p-3">
                                    <div class="text-xs text-muted-foreground">Pendientes</div>
                                    <div class="mt-1 font-mono font-semibold">{{ liquidationRollup.due }}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- ===================== -->
                <!-- TAB: OPS (placeholder future-proof) -->
                <!-- ===================== -->
                <TabsContent value="ops" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">Órdenes</CardTitle>
                                <CardDescription>Periodo</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-1">
                                <div class="text-2xl font-semibold">{{ numMaybe(kpis.orders?.value) }}</div>
                                <div class="text-xs text-muted-foreground">Δ {{ pct(kpis.orders?.deltaPct) }}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">Clientes activos</CardTitle>
                                <CardDescription>Periodo</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-1">
                                <div class="text-2xl font-semibold">{{ numMaybe(kpis.activeCustomers?.value) }}</div>
                                <div class="text-xs text-muted-foreground">Δ {{ pct(kpis.activeCustomers?.deltaPct) }}</div>
                            </CardContent>
                        </Card>

                        <Card class="md:col-span-2">
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">Operación</CardTitle>
                                <CardDescription>Inventario / Proyectos / Servicios (future)</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="relative aspect-video overflow-hidden rounded-lg border opacity-35">
                                    <PlaceholderPattern />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">Work queues</CardTitle>
                            <CardDescription>Backlog operacional (placeholder)</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="relative h-40 overflow-hidden rounded-lg border opacity-35">
                                <PlaceholderPattern />
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- ===================== -->
                <!-- TAB: ACTIVITY -->
                <!-- ===================== -->
                <TabsContent value="activity" class="space-y-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">Actividad reciente</CardTitle>
                            <CardDescription>Últimos eventos del sistema</CardDescription>
                        </CardHeader>

                        <CardContent>
                            <div v-if="activity.length === 0" class="text-sm text-muted-foreground">
                                No hay actividad reciente (o auditoría no habilitada).
                            </div>

                            <div v-else class="overflow-hidden rounded-lg border">
                                <div class="divide-y">
                                    <div v-for="(a, idx) in activity.slice(0, 12)" :key="idx" class="p-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="text-sm">
                                                <span class="font-medium">{{ a.actor }}</span>
                                                <span class="mx-2 opacity-50">•</span>
                                                <span class="font-mono text-xs text-muted-foreground">{{ a.event }}</span>
                                            </div>
                                            <div class="font-mono text-xs text-muted-foreground">{{ a.at }}</div>
                                        </div>

                                        <div v-if="a.meta" class="mt-2 text-xs text-muted-foreground">
                                            <pre class="max-h-28 overflow-auto rounded-md bg-muted/30 p-2">{{ a.meta }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </ErpLayout>
</template>