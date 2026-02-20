<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'

import ErpLayout from '@/layouts/ErpLayout.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

type Company = { id: number; name: string; slug: string; timezone: string }

type InstanceStatus = 'pending' | 'due_soon' | 'overdue' | 'filed' | 'paid' | 'not_applicable'
type Priority = 'low' | 'medium' | 'high'

type CalendarItem = {
    id: number
    due_date: string        // YYYY-MM-DD
    name: string
    authority: string
    code: string
    period_key: string
    status: InstanceStatus
    priority: Priority
}

const props = defineProps<{
    company: Company
    today: string
    items: CalendarItem[]
    ics_url: string | null
}>()

// -----------------------------
// Filtros
// -----------------------------
const q = ref('')
const onlyOverdue = ref(false)
const statusFilter = ref<InstanceStatus | 'all'>('all')

function parseDateYmd(ymd: string): Date | null {
    if (!ymd || typeof ymd !== 'string') return null
    const [ y, m, d ] = ymd.split('-').map((x) => Number(x))
    if (!y || !m || !d) return null
    const dt = new Date(Date.UTC(y, m - 1, d))
    return Number.isNaN(dt.getTime()) ? null : dt
}

function daysUntil(ymd: string): number | null {
    const a = parseDateYmd(props.today)
    const b = parseDateYmd(ymd)
    if (!a || !b) return null
    const ms = b.getTime() - a.getTime()
    return Math.floor(ms / 86400000)
}

const filtered = computed(() => {
    const qq = q.value.trim().toLowerCase()

    return (props.items ?? []).filter((it) => {
        if (onlyOverdue.value && it.status !== 'overdue') return false
        if (statusFilter.value !== 'all' && it.status !== statusFilter.value) return false

        if (!qq) return true

        return (
            it.name.toLowerCase().includes(qq) ||
            it.authority.toLowerCase().includes(qq) ||
            it.period_key.toLowerCase().includes(qq) ||
            (it.code ? it.code.toLowerCase().includes(qq) : false)
        )
    })
})

// -----------------------------
// Stats
// -----------------------------
const stats = computed(() => {
    const items = props.items ?? []

    const overdue = items.filter(i => i.status === 'overdue').length
    const done = items.filter(i => i.status === 'filed' || i.status === 'paid').length

    const upcoming7 = items.filter(i => {
        if (!(i.status === 'pending' || i.status === 'due_soon')) return false
        const du = daysUntil(i.due_date)
        return du !== null && du >= 0 && du <= 7
    }).length

    return { overdue, upcoming7, done }
})

// -----------------------------
// Badges
// -----------------------------
function badgeStatus(it: CalendarItem) {
    if (it.status === 'overdue') return { label: 'VENCIDO', cls: 'bg-red-600 text-white' }
    if (it.status === 'paid') return { label: 'PAGADO', cls: 'bg-emerald-600 text-white' }
    if (it.status === 'filed') return { label: 'DECLARADO', cls: 'bg-emerald-600 text-white' }
    if (it.status === 'not_applicable') return { label: 'N/A', cls: 'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-100' }
    if (it.status === 'due_soon') return { label: 'POR VENCER', cls: 'bg-amber-500 text-white' }
    return { label: 'PENDIENTE', cls: 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' }
}

function badgePriority(p: Priority) {
    if (p === 'high') return { label: 'ALTA', cls: 'border-red-200 text-red-700 dark:border-red-900/40 dark:text-red-300' }
    if (p === 'medium') return { label: 'MEDIA', cls: 'border-amber-200 text-amber-700 dark:border-amber-900/40 dark:text-amber-300' }
    return { label: 'BAJA', cls: 'border-slate-200 text-slate-700 dark:border-slate-800 dark:text-slate-300' }
}

// -----------------------------
// ICS
// -----------------------------
async function copyIcs() {
    if (!props.ics_url) return
    try {
        await navigator.clipboard.writeText(props.ics_url)
    } catch { /* noop */ }
}
</script>

<template>
    <ErpLayout>

        <Head title="Calendario Fiscal" />

        <div class="mx-auto w-full max-w-7xl px-4 py-6 space-y-6">
            <!-- Header (✅ sin botón Gestionar servicios) -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Calendario Fiscal</h1>
                    </div>

                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Fechas clave, obligaciones y recordatorios por tenant.
                        <span class="ml-1 font-medium text-slate-700 dark:text-slate-300">{{ company.name }}</span>
                        <span class="mx-1">•</span>
                        <span class="font-mono">{{ company.timezone }}</span>
                    </p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid gap-3 sm:grid-cols-3">
                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Próximos (7 días)</CardDescription>
                        <CardTitle class="text-2xl">{{ stats.upcoming7 }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Pendientes / por vencer en los próximos 7 días
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Vencidos</CardDescription>
                        <CardTitle class="text-2xl">{{ stats.overdue }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Requieren atención inmediata
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Completados</CardDescription>
                        <CardTitle class="text-2xl">{{ stats.done }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Declarados / pagados
                    </CardContent>
                </Card>
            </div>

            <Tabs default-value="obligaciones" class="w-full">
                <TabsList class="rounded-2xl">
                    <TabsTrigger value="obligaciones">Obligaciones</TabsTrigger>
                    <TabsTrigger value="notificaciones">Notificaciones</TabsTrigger>
                    <TabsTrigger value="integraciones">Integraciones</TabsTrigger>
                </TabsList>

                <!-- Obligaciones -->
                <TabsContent value="obligaciones" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader class="pb-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle class="text-base">Listado</CardTitle>
                                    <CardDescription>Basado en obligation_instances (due_date + period_key + status).</CardDescription>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <Input v-model="q" placeholder="Buscar (606, IT-1, TSS, periodo...)" class="h-10 rounded-xl sm:w-[320px]" />

                                    <select v-model="statusFilter" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950">
                                        <option value="all">Todos</option>
                                        <option value="pending">Pendientes</option>
                                        <option value="due_soon">Por vencer</option>
                                        <option value="overdue">Vencidos</option>
                                        <option value="filed">Declarados</option>
                                        <option value="paid">Pagados</option>
                                        <option value="not_applicable">N/A</option>
                                    </select>

                                    <div class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                                        <Switch v-model:checked="onlyOverdue" />
                                        <span class="text-slate-700 dark:text-slate-200">Solo vencidos</span>
                                    </div>
                                </div>
                            </div>
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
                                            <th class="py-2 pr-4">Prioridad</th>
                                            <th class="py-2 pr-4">Estado</th>
                                            <th class="py-2">Acción</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr v-for="it in filtered" :key="it.id" class="border-t border-slate-100 dark:border-slate-900">
                                            <td class="py-3 pr-4 font-mono text-xs text-slate-700 dark:text-slate-300">
                                                {{ it.due_date }}
                                            </td>

                                            <td class="py-3 pr-4">
                                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ it.name }}
                                                </div>
                                                <div v-if="it.code" class="mt-0.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                                    {{ it.code }}
                                                </div>
                                            </td>

                                            <td class="py-3 pr-4">{{ it.authority }}</td>

                                            <td class="py-3 pr-4 font-mono text-xs">{{ it.period_key }}</td>

                                            <td class="py-3 pr-4">
                                                <Badge variant="outline" :class="badgePriority(it.priority).cls">
                                                    {{ badgePriority(it.priority).label }}
                                                </Badge>
                                            </td>

                                            <td class="py-3 pr-4">
                                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="badgeStatus(it).cls">
                                                    {{ badgeStatus(it).label }}
                                                </span>
                                            </td>

                                            <td class="py-3">
                                                <Button variant="outline" size="sm" class="rounded-xl" disabled>Ver</Button>
                                            </td>
                                        </tr>

                                        <tr v-if="filtered.length === 0">
                                            <td colspan="7" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                                No hay resultados con los filtros actuales.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Notificaciones -->
                <TabsContent value="notificaciones" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Recordatorios</CardTitle>
                            <CardDescription>Se conectará a company_compliance_settings + tenant_obligations.reminders.</CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-slate-100">Email</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Avisos 7/3/1 días antes</div>
                                    </div>
                                    <Switch :checked="true" />
                                </div>
                                <div class="mt-3">
                                    <Input placeholder="notificaciones@tuempresa.com" class="h-10 rounded-xl" />
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-slate-100">In-App</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Badges y alertas dentro del ERP</div>
                                    </div>
                                    <Switch :checked="true" />
                                </div>
                                <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    Se mostrará un contador de vencidos y próximos en el header.
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Integraciones -->
                <TabsContent value="integraciones" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Calendario externo (ICS)</CardTitle>
                            <CardDescription>Basado en calendar_feeds (token_hash).</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="text-sm text-slate-700 dark:text-slate-200">
                                        <div class="font-semibold">Feed ICS</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            Disponible cuando habilites el feed por token (calendar_feeds).
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Button :disabled="!ics_url" variant="outline" class="rounded-xl" @click="copyIcs">
                                            Copiar enlace
                                        </Button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <div class="rounded-xl bg-slate-50 p-3 font-mono text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-200 break-all">
                                        {{ ics_url ?? '— (no configurado aún)' }}
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