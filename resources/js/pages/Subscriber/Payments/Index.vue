<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'

type PaymentRow = {
    id: number
    method: string
    currency: string
    amount: string
    paid_at?: string | null
    reference?: string | null
    invoice?: {
        id: number
        number: string
        status: string
        currency: string
        total: string
    } | null
}

type LinkItem = { url: string | null; label: string; active: boolean }

type Paginator<T> = {
    data: T[]
    links: LinkItem[]
    meta?: any
}

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline' | null

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string }
    filters: { method: string; status: string; q: string; from?: string | null; to?: string | null }
    payments: Paginator<PaymentRow>
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Subscriber', href: subscriber().url },
    { title: 'Pagos', href: '/subscriber/payments' },
]

// -------------------------
// local state (synced with server filters)
// -------------------------
const method = ref(props.filters.method ?? 'all')
const status = ref(props.filters.status ?? 'all') // all | paid | unpaid
const q = ref(props.filters.q ?? '')
const from = ref(props.filters.from ?? '')
const to = ref(props.filters.to ?? '')

const showAdvanced = ref(false)

// -------------------------
// helpers
// -------------------------
function norm(s: any) {
    return String(s ?? '').toLowerCase().trim()
}

function methodLabel(m: string) {
    const x = norm(m)
    if (x === 'card') return 'Tarjeta'
    if (x === 'bank_transfer') return 'Transferencia'
    if (x === 'cash') return 'Efectivo'
    if (x === 'check') return 'Cheque'
    if (x === 'other') return 'Otro'
    return m || '—'
}

function paidLabel(p: PaymentRow) {
    return p.paid_at ? 'Pagado' : 'No pagado'
}

function paidBadgeVariant(p: PaymentRow): BadgeVariant {
    return p.paid_at ? 'secondary' : 'destructive'
}

const rows = computed(() => props.payments?.data ?? [])

const hasActiveFilters = computed(() => {
    return (
        (method.value && method.value !== 'all') ||
        (status.value && status.value !== 'all') ||
        !!q.value.trim() ||
        !!from.value ||
        !!to.value
    )
})

// -------------------------
// summary (current page)
// -------------------------
const totalAmount = computed(() => {
    let sum = 0
    for (const p of rows.value) {
        const n = Number(p.amount)
        if (Number.isFinite(n)) sum += n
    }
    return sum.toFixed(2)
})

const paidCount = computed(() => rows.value.filter((p) => !!p.paid_at).length)
const unpaidCount = computed(() => rows.value.filter((p) => !p.paid_at).length)

// -------------------------
// actions
// -------------------------
function applyFilters() {
    router.get(
        '/subscriber/payments',
        {
            method: method.value,
            status: status.value,
            q: q.value.trim(),
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true }
    )
}

function clearFilters() {
    method.value = 'all'
    status.value = 'all'
    q.value = ''
    from.value = ''
    to.value = ''
    applyFilters()
}

function setPaidStatus(s: 'all' | 'paid' | 'unpaid') {
    status.value = s
    applyFilters() // tab = aplica inmediato
}

function goTo(url: string | null) {
    if (!url) return
    router.visit(url, { preserveScroll: true, preserveState: true })
}
</script>

<template>

    <Head title="Pagos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header limpio -->
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Pagos</h1>
                    <div class="text-sm text-muted-foreground">
                        <span class="font-medium text-foreground">{{ props.company.name }}</span>
                        · Moneda: {{ props.company.currency }}
                        · TZ: {{ props.company.timezone }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="showAdvanced = !showAdvanced">
                        {{ showAdvanced ? 'Ocultar filtros' : 'Filtros avanzados' }}
                    </Button>

                    <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="clearFilters">
                        Limpiar
                    </Button>
                </div>
            </div>

            <!-- Resumen en cards -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <SectionCard title="Página" description="Registros en esta página">
                    <div class="text-2xl font-semibold">{{ rows.length }}</div>
                </SectionCard>

                <SectionCard title="Pagados" description="En esta página">
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">paid</Badge>
                        <div class="text-2xl font-semibold">{{ paidCount }}</div>
                    </div>
                </SectionCard>

                <SectionCard title="No pagados" description="En esta página">
                    <div class="flex items-center gap-2">
                        <Badge variant="destructive">unpaid</Badge>
                        <div class="text-2xl font-semibold">{{ unpaidCount }}</div>
                    </div>
                </SectionCard>

                <SectionCard title="Total página" description="Suma de montos">
                    <div class="text-2xl font-semibold">{{ totalAmount }} {{ props.company.currency }}</div>
                </SectionCard>
            </div>

            <!-- Estado como tabs -->
            <SectionCard title="Estado de pago" description="Filtra rápido por estado">
                <div class="flex flex-wrap gap-2">
                    <Button size="sm" variant="outline" :class="status === 'all' ? 'bg-muted' : ''" @click="setPaidStatus('all')">
                        Todos
                    </Button>

                    <Button size="sm" variant="outline" :class="status === 'paid' ? 'bg-muted' : ''" @click="setPaidStatus('paid')">
                        Pagados
                    </Button>

                    <Button size="sm" variant="outline" :class="status === 'unpaid' ? 'bg-muted' : ''" @click="setPaidStatus('unpaid')">
                        No pagados
                    </Button>
                </div>
            </SectionCard>

            <!-- Filtros avanzados -->
            <SectionCard v-if="showAdvanced" title="Filtros avanzados" description="Método, búsqueda y rango de fechas">
                <div class="grid gap-3 md:grid-cols-5">
                    <div>
                        <div class="text-xs text-muted-foreground mb-1">Método</div>
                        <select v-model="method" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="all">Todos</option>
                            <option value="card">Tarjeta</option>
                            <option value="bank_transfer">Transferencia</option>
                            <option value="cash">Efectivo</option>
                            <option value="check">Cheque</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <div class="text-xs text-muted-foreground mb-1">Buscar</div>
                        <input v-model="q" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Referencia o No. factura..." @keydown.enter.prevent="applyFilters" />
                    </div>

                    <div>
                        <div class="text-xs text-muted-foreground mb-1">Desde (paid_at)</div>
                        <input v-model="from" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>

                    <div>
                        <div class="text-xs text-muted-foreground mb-1">Hasta (paid_at)</div>
                        <input v-model="to" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>
                </div>

                <div class="mt-3 flex justify-end gap-2">
                    <Button variant="outline" size="sm" @click="clearFilters">Limpiar</Button>
                    <Button size="sm" @click="applyFilters">Aplicar</Button>
                </div>
            </SectionCard>

            <!-- Tabla -->
            <SectionCard title="Listado" description="Pagos vinculados a tus facturas">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40">
                            <tr class="text-left">
                                <th class="px-3 py-2">ID</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Método</th>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Referencia</th>
                                <th class="px-3 py-2">Factura</th>
                                <th class="px-3 py-2 text-right">Monto</th>
                                <th class="px-3 py-2">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="px-3 py-4 text-muted-foreground">
                                    No hay pagos para los filtros seleccionados.
                                </td>
                            </tr>

                            <tr v-for="p in rows" :key="p.id" class="border-t">
                                <td class="px-3 py-2 font-medium">#{{ p.id }}</td>

                                <td class="px-3 py-2">
                                    <Badge :variant="paidBadgeVariant(p)">
                                        {{ paidLabel(p) }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ methodLabel(p.method) }}
                                </td>

                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ p.paid_at ?? '—' }}
                                </td>

                                <td class="px-3 py-2 text-muted-foreground">
                                    <span v-if="p.reference">{{ p.reference }}</span>
                                    <span v-else>—</span>
                                </td>

                                <td class="px-3 py-2">
                                    <span v-if="p.invoice" class="text-muted-foreground">{{ p.invoice.number }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>

                                <td class="px-3 py-2 text-right font-medium">
                                    {{ p.amount }} {{ p.currency }}
                                </td>

                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <Button size="sm" variant="outline" as-child>
                                            <Link :href="`/subscriber/payments/${p.id}`">Ver</Link>
                                        </Button>

                                        <Button v-if="p.invoice" size="sm" variant="outline" as-child>
                                            <Link :href="`/subscriber/invoices/${p.invoice.id}`">Factura</Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="props.payments.links?.length" class="mt-4 flex flex-wrap gap-2">
                    <Button v-for="l in props.payments.links" :key="l.label" size="sm" variant="outline" :disabled="!l.url" :class="l.active ? 'bg-muted' : ''" @click="goTo(l.url)" v-html="l.label" />
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
