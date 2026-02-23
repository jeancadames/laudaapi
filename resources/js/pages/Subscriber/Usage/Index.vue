<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { computed, ref } from 'vue'
import { subscriber } from '@/routes'

type ServiceOpt = { id: number; title: string; slug?: string | null }

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string }
    subscription: null | {
        id: number
        status: string
        billing_cycle: string
        currency: string
        trial_ends_at_human?: string | null
        period_end_human?: string | null
    }
    filters: { from: string | null; to: string | null; service_id: number | null }
    services: ServiceOpt[]
    summary: { total_records: number; total_quantity: string }

    by_service: Array<{
        service: { id: number; title: string; slug?: string | null }
        total_records: number
        total_quantity: string
        unit_name?: string | null
    }>

    rows: Array<{
        id: number
        occurred_on: string
        service: { id: number; title: string; slug?: string | null }
        quantity: string
        unit_name?: string | null
        meta?: any
        created_at?: string
    }>
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Uso y Límites', href: '/subscriber/usage' },
]

// -------------------------
// Filters
// -------------------------
const from = ref(props.filters.from ?? '')
const to = ref(props.filters.to ?? '')
const serviceId = ref<number | null>(props.filters.service_id ?? null)

const hasSubscription = computed(() => !!props.subscription?.id)

function applyFilters() {
    router.get(
        '/subscriber/usage',
        { from: from.value || null, to: to.value || null, service_id: serviceId.value || null },
        { preserveScroll: true, preserveState: true }
    )
}

function resetFilters() {
    from.value = ''
    to.value = ''
    serviceId.value = null
    router.get('/subscriber/usage', {}, { preserveScroll: true, preserveState: true })
}

// -------------------------
// Helpers
// -------------------------
function safeUnit(unit?: string | null) {
    const u = (unit ?? '').trim()
    return u ? u : '—'
}

function safeTitle(t?: string | null) {
    const x = (t ?? '').trim()
    return x ? x : '—'
}

function toNumber(v: any): number {
    // soporta "1234.56" y "1,234.56"
    const s = String(v ?? '').replace(/,/g, '').trim()
    const n = Number(s)
    return Number.isFinite(n) ? n : 0
}

function fmtQty(n: number) {
    // 4 decimales por tu schema (12,4)
    return n.toFixed(4)
}

// -------------------------
// ✅ Destacado (Top service del rango)
// -------------------------
const topService = computed(() => {
    if (!props.by_service?.length) return null

    // order por total_quantity numérico desc
    const sorted = [ ...props.by_service ].sort((a, b) => {
        return toNumber(b.total_quantity) - toNumber(a.total_quantity)
    })

    return sorted[ 0 ] ?? null
})

// -------------------------
// ✅ Totales por día (aprox, basado en rows <= 500)
// -------------------------
const byDay = computed(() => {
    const map = new Map<
        string,
        { occurred_on: string; total_records: number; total_quantity_num: number; unit_name: string }
    >()

    for (const r of props.rows ?? []) {
        const day = String(r.occurred_on ?? '').trim()
        if (!day) continue

        const qty = toNumber(r.quantity)
        const unit = safeUnit(r.unit_name)

        if (!map.has(day)) {
            map.set(day, { occurred_on: day, total_records: 0, total_quantity_num: 0, unit_name: unit })
        }

        const cur = map.get(day)!
        cur.total_records += 1
        cur.total_quantity_num += qty

        // si hay mezcla de unidades en el día, marca como "—"
        if (cur.unit_name !== unit) cur.unit_name = '—'
    }

    // desc por fecha (YYYY-MM-DD)
    return Array.from(map.values()).sort((a, b) => (a.occurred_on < b.occurred_on ? 1 : -1))
})

// -------------------------
// ✅ Expandir meta por registro
// -------------------------
const expandedMetaIds = ref<Set<number>>(new Set())

function toggleMeta(id: number) {
    const s = new Set(expandedMetaIds.value)
    if (s.has(id)) s.delete(id)
    else s.add(id)
    expandedMetaIds.value = s
}

function isMetaOpen(id: number) {
    return expandedMetaIds.value.has(id)
}
</script>

<template>

    <Head title="Uso y Límites" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Resumen -->
            <SectionCard title="Resumen" description="Consumo por servicio (usage_records)">
                <div class="text-sm text-muted-foreground space-y-1">
                    <div><span class="font-medium text-foreground">{{ props.company.name }}</span></div>
                    <div>
                        Suscripción:
                        <span class="font-medium text-foreground">{{ props.subscription?.status ?? '—' }}</span>
                        <span v-if="props.subscription?.period_end_human"> · Period ends: {{ props.subscription.period_end_human }}</span>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <Badge variant="secondary">Registros: {{ props.summary.total_records }}</Badge>
                    <Badge variant="secondary">Total qty: {{ props.summary.total_quantity }}</Badge>
                </div>

                <!-- ✅ Destacado -->
                <div v-if="topService" class="mt-4 rounded-xl border p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-xs text-muted-foreground">Destacado del rango</div>
                            <div class="text-sm font-semibold truncate">
                                {{ safeTitle(topService.service.title) }}
                            </div>
                            <div class="text-xs text-muted-foreground mt-1">
                                Unidad: {{ safeUnit(topService.unit_name) }}
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-xs text-muted-foreground">Total</div>
                            <div class="text-lg font-semibold tabular-nums">
                                {{ topService.total_quantity }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Registros: {{ topService.total_records }}
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- Filtros -->
            <SectionCard title="Filtros" description="Rango de fechas y servicio">
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Desde</label>
                        <input v-model="from" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Hasta</label>
                        <input v-model="to" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Servicio</label>
                        <select v-model="serviceId" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option :value="null">Todos</option>
                            <option v-for="s in props.services" :key="s.id" :value="s.id">
                                {{ s.title }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2 justify-end">
                    <Button variant="outline" @click="resetFilters">Reiniciar</Button>
                    <Button :disabled="!hasSubscription" @click="applyFilters">Aplicar</Button>
                </div>

                <div v-if="!hasSubscription" class="mt-2 text-xs text-muted-foreground">
                    No hay suscripción activa todavía.
                </div>
            </SectionCard>

            <!-- ✅ Totales por servicio (cards) -->
            <SectionCard title="Totales por servicio" description="Suma de consumo en el rango actual">
                <div v-if="props.by_service.length === 0" class="text-sm text-muted-foreground">
                    No hay datos agregados para este rango.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="r in props.by_service" :key="`svc-${r.service.id}`" class="rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold truncate">
                                    {{ safeTitle(r.service.title) }}
                                </div>
                                <div class="text-xs text-muted-foreground mt-1">
                                    Unidad: {{ safeUnit(r.unit_name) }}
                                </div>
                            </div>

                            <Badge variant="secondary" class="shrink-0">
                                Registros: {{ r.total_records }}
                            </Badge>
                        </div>

                        <div class="mt-3">
                            <div class="text-xs text-muted-foreground">Total</div>
                            <div class="text-lg font-semibold tabular-nums">
                                {{ r.total_quantity }}
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- ✅ Totales por día (cards) -->
            <SectionCard title="Totales por día" description="Calculado desde los registros visibles (máx 500)">
                <div v-if="byDay.length === 0" class="text-sm text-muted-foreground">
                    No hay datos por día.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="d in byDay" :key="`day-${d.occurred_on}`" class="rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold truncate">{{ d.occurred_on }}</div>
                                <div class="text-xs text-muted-foreground mt-1">
                                    Unidad: {{ d.unit_name }}
                                </div>
                            </div>

                            <Badge variant="secondary" class="shrink-0">
                                Registros: {{ d.total_records }}
                            </Badge>
                        </div>

                        <div class="mt-3">
                            <div class="text-xs text-muted-foreground">Total</div>
                            <div class="text-lg font-semibold tabular-nums">
                                {{ fmtQty(d.total_quantity_num) }}
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- ✅ Registros (cards) -->
            <SectionCard title="Registros" description="Últimos 500 registros filtrados">
                <div v-if="props.rows.length === 0" class="text-sm text-muted-foreground">
                    No hay registros en este rango.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="r in props.rows" :key="r.id" class="rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold truncate">
                                    {{ safeTitle(r.service.title) }}
                                </div>
                                <div class="text-xs text-muted-foreground mt-1">
                                    Fecha: {{ r.occurred_on }}
                                </div>
                            </div>

                            <Badge variant="secondary" class="shrink-0">
                                {{ r.quantity }}
                            </Badge>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <div class="text-xs text-muted-foreground">
                                Unidad: <span class="text-foreground">{{ safeUnit(r.unit_name) }}</span>
                            </div>

                            <div v-if="r.created_at" class="text-[11px] text-muted-foreground">
                                {{ r.created_at }}
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-end">
                            <Button v-if="r.meta" size="sm" variant="outline" @click="toggleMeta(r.id)">
                                <span v-if="isMetaOpen(r.id)">Ocultar meta</span>
                                <span v-else>Ver meta</span>
                            </Button>
                        </div>

                        <div v-if="r.meta && isMetaOpen(r.id)" class="mt-3 text-[11px] text-muted-foreground">
                            <div class="font-medium text-foreground mb-1">meta</div>
                            <pre class="whitespace-pre-wrap wrap-break-word rounded-md border p-2 bg-background/50">{{ JSON.stringify(r.meta, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
