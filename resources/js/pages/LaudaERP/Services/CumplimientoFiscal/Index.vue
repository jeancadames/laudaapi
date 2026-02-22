<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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

type ComplianceInstance = {
    id: number
    due_date: string        // YYYY-MM-DD
    name: string
    authority: string
    code: string | null
    period_key: string
    status: InstanceStatus
    priority: Priority
}

type ComplianceStats = {
    total: number
    upcoming7: number
    overdue: number
    done: number
    completion_rate: number // 0..100
}

type ComplianceSettings = {
    timezone: string | null
    weekend_shift: 'next_business_day' | string
    use_holidays: boolean
    default_reminders: number[] | null // [7,3,1,0]
    channels: { email?: boolean; in_app?: boolean } | null
}

type CalendarFeed = {
    ics_url: string | null
    enabled: boolean
    expires_at: string | null // ISO
    last_rotated_at: string | null // ISO
}

// ✅ Acepta el shape nuevo + el viejo (items / ics_url) para evitar crashes
const props = defineProps<{
    company: Company
    today: string // YYYY-MM-DD (timezone del backend)

    // Nuevo shape
    stats?: ComplianceStats
    instances?: ComplianceInstance[]
    settings?: ComplianceSettings | null
    feed?: CalendarFeed | null

    // Shape viejo (compat)
    items?: ComplianceInstance[]
    ics_url?: string | null
}>()

// -----------------------------
// Helpers
// -----------------------------
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

function fmtDueLabel(ymd: string): string {
    const du = daysUntil(ymd)
    if (du === null) return '—'
    if (du < 0) return `${Math.abs(du)}d tarde`
    if (du === 0) return 'Hoy'
    return `En ${du}d`
}

// ✅ Unifica instancias: nuevo (instances) o viejo (items)
const allInstances = computed<ComplianceInstance[]>(() => {
    return (props.instances ?? props.items ?? [])
})

// ✅ Unifica ICS: nuevo (feed.ics_url) o viejo (ics_url)
const icsUrl = computed<string | null>(() => {
    return props.feed?.ics_url ?? props.ics_url ?? null
})

// ✅ Feed “safe” (evita null checks repetidos y mantiene UI estable)
const feedSafe = computed<CalendarFeed>(() => {
    if (props.feed) return props.feed
    return {
        ics_url: props.ics_url ?? null,
        enabled: !!props.ics_url,
        expires_at: null,
        last_rotated_at: null,
    }
})

// -----------------------------
// Stats (fallback si backend no lo manda)
// -----------------------------
function computeStats(instances: ComplianceInstance[]): ComplianceStats {
    const nonNA = instances.filter(i => i.status !== 'not_applicable')
    const total = nonNA.length

    const done = instances.filter(i => i.status === 'filed' || i.status === 'paid').length
    const overdue = instances.filter(i => i.status === 'overdue').length

    const upcoming7 = instances.filter(i => {
        if (!(i.status === 'pending' || i.status === 'due_soon')) return false
        const du = daysUntil(i.due_date)
        return du !== null && du >= 0 && du <= 7
    }).length

    const completion_rate = total > 0 ? (done / total) * 100 : 0
    return { total, upcoming7, overdue, done, completion_rate }
}

const safeStats = computed<ComplianceStats>(() => {
    return props.stats ?? computeStats(allInstances.value)
})

// -----------------------------
// Filters (Instancias)
// -----------------------------
const q = ref('')
const onlyOverdue = ref(false)
const statusFilter = ref<InstanceStatus | 'all'>('all')

const filtered = computed(() => {
    const qq = q.value.trim().toLowerCase()

    return allInstances.value.filter((it) => {
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

const nextDue = computed(() => {
    const base = allInstances.value
        .filter((it) => it.status !== 'filed' && it.status !== 'paid' && it.status !== 'not_applicable')
        .slice()

    base.sort((a, b) => {
        const da = parseDateYmd(a.due_date)?.getTime() ?? Number.MAX_SAFE_INTEGER
        const db = parseDateYmd(b.due_date)?.getTime() ?? Number.MAX_SAFE_INTEGER
        return da - db
    })

    return base.slice(0, 8)
})

// -----------------------------
// Badges
// -----------------------------
function badgeStatus(it: ComplianceInstance) {
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
// Settings UI state (solo UI; guarda con tu endpoint luego)
// -----------------------------
type LocalSettings = Omit<ComplianceSettings, 'channels'> & {
    channels: { email: boolean; in_app: boolean } // ✅ requerido para evitar null/undefined
}

function normalizeChannels(ch?: ComplianceSettings[ 'channels' ]): LocalSettings[ 'channels' ] {
    return {
        email: ch?.email ?? true,
        in_app: ch?.in_app ?? true,
    }
}

const localSettings = ref<LocalSettings>({
    timezone: props.settings?.timezone ?? props.company.timezone ?? null,
    weekend_shift: props.settings?.weekend_shift ?? 'next_business_day',
    use_holidays: props.settings?.use_holidays ?? false,
    default_reminders: props.settings?.default_reminders ?? [ 7, 3, 1 ],
    channels: normalizeChannels(props.settings?.channels),
})

watch(
    () => props.settings,
    (s) => {
        if (!s) return
        localSettings.value = {
            timezone: s.timezone ?? props.company.timezone ?? null,
            weekend_shift: s.weekend_shift ?? 'next_business_day',
            use_holidays: !!s.use_holidays,
            default_reminders: s.default_reminders ?? [ 7, 3, 1 ],
            channels: normalizeChannels(s.channels),
        }
    },
)

// -----------------------------
// ICS
// -----------------------------
async function copyIcs() {
    if (!icsUrl.value) return
    try {
        await navigator.clipboard.writeText(icsUrl.value)
    } catch {
        /* noop */
    }
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
                        <Badge variant="outline" class="rounded-full">
                            {{ company.name }}
                        </Badge>
                    </div>

                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Estado de obligaciones, próximos vencimientos, recordatorios e integración de calendario.
                        <span class="mx-1">•</span>
                        <span class="font-mono">{{ company.timezone }}</span>
                        <span class="mx-1">•</span>
                        <span class="font-mono">Hoy: {{ today }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" class="rounded-xl" disabled>Crear obligación</Button>
                    <Button class="rounded-xl" disabled>Marcar como completado</Button>
                </div>
            </div>

            <!-- Top stats (✅ safeStats) -->
            <div class="grid gap-3 sm:grid-cols-4">
                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Próximos (7 días)</CardDescription>
                        <CardTitle class="text-2xl">{{ safeStats.upcoming7 }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Pendientes / por vencer en los próximos 7 días
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Vencidos</CardDescription>
                        <CardTitle class="text-2xl">{{ safeStats.overdue }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Requieren atención inmediata
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Completados</CardDescription>
                        <CardTitle class="text-2xl">{{ safeStats.done }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Declarados / pagados
                    </CardContent>
                </Card>

                <Card class="rounded-2xl">
                    <CardHeader class="pb-2">
                        <CardDescription>Progreso</CardDescription>
                        <CardTitle class="text-2xl">{{ Math.round(safeStats.completion_rate) }}%</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-slate-500 dark:text-slate-400">
                        Completados sobre total (excluye N/A)
                    </CardContent>
                </Card>
            </div>

            <Tabs default-value="resumen" class="w-full">
                <TabsList class="rounded-2xl">
                    <TabsTrigger value="resumen">Resumen</TabsTrigger>
                    <TabsTrigger value="instancias">Instancias</TabsTrigger>
                    <TabsTrigger value="notificaciones">Notificaciones</TabsTrigger>
                    <TabsTrigger value="integraciones">Integraciones</TabsTrigger>
                </TabsList>

                <!-- RESUMEN -->
                <TabsContent value="resumen" class="mt-4 space-y-4">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <!-- Próximos vencimientos -->
                        <Card class="rounded-2xl lg:col-span-2">
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle class="text-base">Próximos vencimientos</CardTitle>
                                        <CardDescription>Top 8 pendientes ordenados por due_date.</CardDescription>
                                    </div>
                                    <Button variant="outline" class="rounded-xl" disabled>Ver todo</Button>
                                </div>
                            </CardHeader>

                            <CardContent>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                                <th class="py-2 pr-4">Vence</th>
                                                <th class="py-2 pr-4">Obligación</th>
                                                <th class="py-2 pr-4">Periodo</th>
                                                <th class="py-2 pr-4">Prioridad</th>
                                                <th class="py-2">Estado</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <tr v-for="it in nextDue" :key="it.id" class="border-t border-slate-100 dark:border-slate-900">
                                                <td class="py-3 pr-4">
                                                    <div class="font-mono text-xs text-slate-700 dark:text-slate-300">
                                                        {{ it.due_date }}
                                                    </div>
                                                    <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                        {{ fmtDueLabel(it.due_date) }}
                                                    </div>
                                                </td>

                                                <td class="py-3 pr-4">
                                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                                        {{ it.name }}
                                                    </div>
                                                    <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                        {{ it.authority }}
                                                        <span v-if="it.code" class="mx-1">•</span>
                                                        <span v-if="it.code" class="font-mono">{{ it.code }}</span>
                                                    </div>
                                                </td>

                                                <td class="py-3 pr-4 font-mono text-xs">{{ it.period_key }}</td>

                                                <td class="py-3 pr-4">
                                                    <Badge variant="outline" :class="badgePriority(it.priority).cls">
                                                        {{ badgePriority(it.priority).label }}
                                                    </Badge>
                                                </td>

                                                <td class="py-3">
                                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="badgeStatus(it).cls">
                                                        {{ badgeStatus(it).label }}
                                                    </span>
                                                </td>
                                            </tr>

                                            <tr v-if="nextDue.length === 0">
                                                <td colspan="5" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                                    No hay vencimientos pendientes.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- ICS + Estado (✅ feedSafe + icsUrl) -->
                        <Card class="rounded-2xl">
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base">Calendario externo (ICS)</CardTitle>
                                <CardDescription>Sincroniza tus vencimientos con Google/Outlook.</CardDescription>
                            </CardHeader>

                            <CardContent class="space-y-3">
                                <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm">
                                            <div class="font-semibold text-slate-900 dark:text-slate-100">Feed ICS</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ feedSafe.enabled ? 'Habilitado' : 'Deshabilitado' }}
                                                <span v-if="feedSafe.expires_at" class="mx-1">•</span>
                                                <span v-if="feedSafe.expires_at" class="font-mono">exp: {{ feedSafe.expires_at }}</span>
                                            </div>
                                        </div>
                                        <Badge variant="outline" class="rounded-full">
                                            {{ icsUrl ? 'Listo' : 'No configurado' }}
                                        </Badge>
                                    </div>

                                    <div class="mt-3">
                                        <div class="rounded-xl bg-slate-50 p-3 font-mono text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-200 break-all">
                                            {{ icsUrl ?? '— (no configurado aún)' }}
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-2">
                                        <Button :disabled="!icsUrl" variant="outline" class="rounded-xl" @click="copyIcs">
                                            Copiar enlace
                                        </Button>
                                        <Button variant="outline" class="rounded-xl" disabled>Rotar token</Button>
                                    </div>

                                    <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                        Tip: agrega el enlace como “Calendario por URL” en Google Calendar o como suscripción en Outlook.
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 p-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                    <div class="font-semibold text-slate-700 dark:text-slate-200">Fuente de datos</div>
                                    <div class="mt-1">
                                        obligation_instances + tenant_obligations + compliance_obligation_templates.
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- INSTANCIAS -->
                <TabsContent value="instancias" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader class="pb-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle class="text-base">Instancias</CardTitle>
                                    <CardDescription>Listado (due_date + period_key + status) por empresa.</CardDescription>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <Input v-model="q" placeholder="Buscar (606, IT-1, DGII, periodo...)" class="h-10 rounded-xl sm:w-[320px]" />

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
                                        <Switch v-model="onlyOverdue" class="data-[state=checked]:bg-green-600" />
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
                                            <td class="py-3 pr-4">
                                                <div class="font-mono text-xs text-slate-700 dark:text-slate-300">
                                                    {{ it.due_date }}
                                                </div>
                                                <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                    {{ fmtDueLabel(it.due_date) }}
                                                </div>
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

                <!-- NOTIFICACIONES -->
                <TabsContent value="notificaciones" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Notificaciones</CardTitle>
                            <CardDescription>company_compliance_settings + overrides por tenant_obligations.</CardDescription>
                        </CardHeader>

                        <CardContent class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-slate-100">Canales</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Email / In-App</div>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-800">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100">Email</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">Envío de recordatorios</div>
                                        </div>
                                        <Switch v-model="localSettings.channels.email" class="data-[state=checked]:bg-green-600" />
                                    </div>

                                    <div class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-800">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100">In-App</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">Badges/alertas dentro del ERP</div>
                                        </div>
                                        <Switch v-model="localSettings.channels.in_app" class="data-[state=checked]:bg-green-600" />
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-slate-100">Reglas</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Fines de semana / feriados</div>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-800">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100">Usar feriados</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">Ajusta vencimientos según calendario</div>
                                        </div>
                                        <Switch v-model="localSettings.use_holidays" class="data-[state=checked]:bg-green-600" />
                                    </div>

                                    <div class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                                        <div class="text-xs text-slate-500 dark:text-slate-400">weekend_shift</div>
                                        <div class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-200">
                                            {{ localSettings.weekend_shift }}
                                        </div>
                                    </div>

                                    <Button class="rounded-xl" disabled>Guardar</Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- INTEGRACIONES -->
                <TabsContent value="integraciones" class="mt-4 space-y-4">
                    <Card class="rounded-2xl">
                        <CardHeader>
                            <CardTitle class="text-base">Integraciones</CardTitle>
                            <CardDescription>Feeds, exportaciones y automatizaciones.</CardDescription>
                        </CardHeader>

                        <CardContent class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-slate-100">Feed ICS</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">calendar_feeds (token_hash)</div>
                                    </div>
                                    <Badge variant="outline" class="rounded-full">
                                        {{ icsUrl ? 'Activo' : 'Pendiente' }}
                                    </Badge>
                                </div>

                                <div class="mt-3 rounded-xl bg-slate-50 p-3 font-mono text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-200 break-all">
                                    {{ icsUrl ?? '— (no configurado aún)' }}
                                </div>

                                <div class="mt-3 flex items-center gap-2">
                                    <Button :disabled="!icsUrl" variant="outline" class="rounded-xl" @click="copyIcs">Copiar enlace</Button>
                                    <Button variant="outline" class="rounded-xl" disabled>Regenerar</Button>
                                </div>

                                <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    Seguridad: si regeneras el token, los calendarios suscritos deberán actualizar la URL.
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">API / Webhooks</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Para recibir señales desde módulos (sales/rrhh/banking) usando company_fiscal_signals.
                                </div>

                                <div class="mt-4 rounded-xl border border-slate-200 p-3 text-xs dark:border-slate-800">
                                    <div class="text-slate-500 dark:text-slate-400">Estado</div>
                                    <div class="mt-1 font-mono text-slate-700 dark:text-slate-200">
                                        coming_soon
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <Button class="rounded-xl" disabled>Configurar webhooks</Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </ErpLayout>
</template>