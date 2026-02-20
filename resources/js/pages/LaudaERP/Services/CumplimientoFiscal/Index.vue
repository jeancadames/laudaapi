<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'

import ErpLayout from '@/layouts/ErpLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Progress } from '@/components/ui/progress'

type Company = { id: number; name: string; slug: string; timezone: string }
type ServiceInfo = { enabled: boolean; item_status: string | null; badge: 'TRIAL' | 'PAGO' | null }

type CheckStatus = 'ok' | 'warn' | 'fail'
type ComplianceCheck = { key: string; title: string; status: CheckStatus; hint?: string }
type Risk = { level: 'high' | 'medium' | 'low'; title: string; detail: string }

type InstanceStatus = 'pending' | 'due_soon' | 'overdue' | 'filed' | 'paid' | 'not_applicable'
type ObligationInstance = {
    id: number | string
    due_date: string
    period_key: string
    status: InstanceStatus
    name: string
    authority: string
    code?: string | null
}

const props = defineProps<{
    company: Company
    service?: ServiceInfo | null
    today?: string
    checks?: ComplianceCheck[]
    risks?: Risk[]
    obligations?: ObligationInstance[]
}>()

const checks = computed(() => props.checks ?? [])
const risks = computed(() => props.risks ?? [])
const obligations = computed(() => props.obligations ?? [])

function parseDateYmd(ymd: string): Date | null {
    if (!ymd || typeof ymd !== 'string') return null
    const [ y, m, d ] = ymd.split('-').map(Number)
    if (!y || !m || !d) return null
    const dt = new Date(Date.UTC(y, m - 1, d))
    return Number.isNaN(dt.getTime()) ? null : dt
}

function daysUntil(today: string, ymd: string): number | null {
    const a = parseDateYmd(today)
    const b = parseDateYmd(ymd)
    if (!a || !b) return null
    return Math.floor((b.getTime() - a.getTime()) / 86400000)
}

const summary = computed(() => {
    const total = checks.value.length || 1
    const ok = checks.value.filter(c => c.status === 'ok').length
    const warn = checks.value.filter(c => c.status === 'warn').length
    const fail = checks.value.filter(c => c.status === 'fail').length
    const score = Math.round((ok / total) * 100)
    return { ok, warn, fail, score }
})

const hasObligations = computed(() => obligations.value.length > 0 && !!props.today)

const obligationStats = computed(() => {
    const list = obligations.value
    const today = props.today ?? ''

    const overdue = list.filter(i => i.status === 'overdue').length
    const dueSoon = list.filter(i => i.status === 'due_soon').length
    const completed = list.filter(i => i.status === 'filed' || i.status === 'paid').length

    const upcoming7 = today
        ? list.filter(i => {
            if (!(i.status === 'pending' || i.status === 'due_soon')) return false
            const du = daysUntil(today, i.due_date)
            return du !== null && du >= 0 && du <= 7
        }).length
        : 0

    return { overdue, dueSoon, upcoming7, completed, total: list.length }
})

function pill(status: CheckStatus) {
    if (status === 'ok') return { label: 'OK', cls: 'bg-emerald-600 text-white' }
    if (status === 'warn') return { label: 'ATENCIÓN', cls: 'bg-amber-600 text-white' }
    return { label: 'FALLO', cls: 'bg-red-600 text-white' }
}

function riskBadge(level: Risk[ 'level' ]) {
    if (level === 'high') return { label: 'ALTO', cls: 'bg-red-600 text-white' }
    if (level === 'medium') return { label: 'MEDIO', cls: 'bg-amber-600 text-white' }
    return { label: 'BAJO', cls: 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' }
}

function obPill(status: InstanceStatus) {
    if (status === 'overdue') return { label: 'VENCIDO', cls: 'bg-red-600 text-white' }
    if (status === 'due_soon') return { label: 'POR VENCER', cls: 'bg-amber-500 text-white' }
    if (status === 'filed') return { label: 'DECLARADO', cls: 'bg-emerald-600 text-white' }
    if (status === 'paid') return { label: 'PAGADO', cls: 'bg-emerald-600 text-white' }
    if (status === 'not_applicable') return { label: 'N/A', cls: 'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-100' }
    return { label: 'PENDIENTE', cls: 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' }
}
</script>

<template>
    <ErpLayout>

        <Head title="Cumplimiento Fiscal" />

        <div class="mx-auto w-full max-w-7xl px-4 py-6 space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Cumplimiento Fiscal</h1>
                        <Badge v-if="props.service?.badge" variant="secondary">{{ props.service.badge }}</Badge>
                    </div>

                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Controles, validaciones y alertas preventivas para reducir riesgos y rechazos.
                        <span class="ml-1 font-medium text-slate-700 dark:text-slate-300">{{ props.company.name }}</span>
                    </p>

                    <p v-if="hasObligations" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Fuente: obligation_instances • Hoy: <span class="font-mono">{{ props.today }}</span>
                    </p>
                </div>
            </div>

            <!-- Score / Alertas / Fallas -->
            <div class="grid gap-3 sm:grid-cols-3">
                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Score cumplimiento</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.score }}%</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Progress :model-value="summary.score" />
                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Basado en checks críticos del tenant.
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Alertas</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.warn }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Recomendaciones para evitar incidentes.
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Fallas</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.fail }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Bloquean operación si son críticas.
                    </CardContent>
                </Card>
            </div>

            <!-- Estado de obligaciones -->
            <Card v-if="hasObligations" class="rounded-2xl">
                <CardHeader>
                    <CardTitle class="text-base">Estado de obligaciones</CardTitle>
                    <CardDescription>Resumen operativo (vencidos / por vencer / próximos 7 días / completados).</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Vencidos</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ obligationStats.overdue }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Por vencer</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ obligationStats.dueSoon }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Próximos (7d)</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ obligationStats.upcoming7 }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Completados</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ obligationStats.completed }}</div>
                    </div>
                </CardContent>
            </Card>

            <Tabs default-value="checks" class="w-full">
                <TabsList class="rounded-2xl">
                    <TabsTrigger value="checks">Checks</TabsTrigger>
                    <TabsTrigger value="riesgos">Riesgos</TabsTrigger>
                    <TabsTrigger v-if="hasObligations" value="obligaciones">Obligaciones</TabsTrigger>
                    <TabsTrigger value="config">Configuración</TabsTrigger>
                </TabsList>

                <TabsContent value="checks" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Controles del tenant</CardTitle>
                            <CardDescription>Validaciones que deben estar OK para operar estable.</CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-3">
                            <div v-for="c in checks" :key="c.key" class="flex flex-col gap-2 rounded-2xl border border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">{{ c.title }}</div>
                                    <div v-if="c.hint" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ c.hint }}</div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="pill(c.status).cls">
                                        {{ pill(c.status).label }}
                                    </span>
                                    <Button variant="outline" size="sm" class="rounded-xl" disabled>Ver detalle</Button>
                                </div>
                            </div>

                            <div v-if="checks.length === 0" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                No hay checks configurados todavía.
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="riesgos" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Riesgos detectados</CardTitle>
                            <CardDescription>Señales que pueden causar rechazos o interrupciones.</CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-3">
                            <div v-for="r in risks" :key="r.title" class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-slate-100">{{ r.title }}</div>
                                        <div class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ r.detail }}</div>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="riskBadge(r.level).cls">
                                        {{ riskBadge(r.level).label }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="risks.length === 0" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                No se detectaron riesgos en este momento.
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent v-if="hasObligations" value="obligaciones" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Obligaciones (instancias)</CardTitle>
                            <CardDescription>Listado directo de obligation_instances (MVP).</CardDescription>
                        </CardHeader>

                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                            <th class="py-2 pr-4">Vence</th>
                                            <th class="py-2 pr-4">Obligación</th>
                                            <th class="py-2 pr-4">Autoridad</th>
                                            <th class="py-2 pr-4">Periodo</th>
                                            <th class="py-2 pr-4">Estado</th>
                                            <th class="py-2">Acción</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr v-for="o in obligations" :key="o.id" class="border-t border-slate-100 dark:border-slate-900">
                                            <td class="py-3 pr-4 font-mono text-xs text-slate-700 dark:text-slate-300">{{ o.due_date }}</td>
                                            <td class="py-3 pr-4">
                                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ o.name }}</div>
                                                <div v-if="o.code" class="mt-0.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ o.code }}</div>
                                            </td>
                                            <td class="py-3 pr-4">{{ o.authority }}</td>
                                            <td class="py-3 pr-4 font-mono text-xs">{{ o.period_key }}</td>
                                            <td class="py-3 pr-4">
                                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="obPill(o.status).cls">
                                                    {{ obPill(o.status).label }}
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <Button variant="outline" size="sm" class="rounded-xl" disabled>Ver</Button>
                                            </td>
                                        </tr>

                                        <tr v-if="obligations.length === 0">
                                            <td colspan="6" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                                No hay instancias generadas aún.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="config" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Políticas</CardTitle>
                            <CardDescription>Cómo se comporta el “bloqueo preventivo”.</CardDescription>
                        </CardHeader>

                        <CardContent class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">Bloquear emisión</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Si hay fallas críticas (secuencia/certificado/token), no permitir emitir.
                                </div>
                                <div class="mt-3">
                                    <Button variant="outline" class="rounded-xl" disabled>Configurar</Button>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">Auditoría</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Registrar eventos (changed, warned, blocked) por tenant.
                                </div>
                                <div class="mt-3">
                                    <Button variant="outline" class="rounded-xl" disabled>Ver eventos</Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </ErpLayout>
</template>