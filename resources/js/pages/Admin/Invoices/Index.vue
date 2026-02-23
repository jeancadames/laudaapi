<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import invoices from '@/routes/admin/invoices'
import type { BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu'

type StatusFilter = 'all' | 'draft' | 'issued' | 'overdue' | 'paid' | 'void'

type InvoiceRow = {
    id: number
    number: string
    status: 'draft' | 'issued' | 'overdue' | 'paid' | 'void'
    currency: string

    issued_on: string | null
    due_on: string | null

    total: string | number
    amount_paid: string | number
    balance: string | number

    fiscal_number: string | null
    document_class: string | null
    document_type: string | null

    items_count: number

    company: { id: number; name: string } | null
    created_at: string | null
    updated_at: string | null
}

type Paginator<T> = {
    data: T[]
    current_page: number
    last_page: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    invoices: Paginator<InvoiceRow>
    filters: { search: string; status: StatusFilter }
    counts: {
        all: number
        by_status: Record<string, number>
        totals: {
            total_invoiced: number
            total_amount_paid_on_invoices: number
            accounts_receivable: number
            outstanding_issued_overdue: number
        }
    }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Facturas', href: invoices.index().url },
]

// loading indicator
const isLoading = ref(false)
let unsubs: Array<() => void> = []
onMounted(() => {
    const start = router.on('start', () => (isLoading.value = true))
    const finish = router.on('finish', () => (isLoading.value = false))
    unsubs = [ start, finish ]
})
onBeforeUnmount(() => unsubs.forEach(u => u()))

const search = ref(props.filters?.search ?? '')
const status = ref<StatusFilter>(props.filters?.status ?? 'all')

function buildQS(override?: Partial<{ search: string; status: StatusFilter; page: number }>) {
    const params = new URLSearchParams()
    const s = override?.search ?? search.value
    const st = override?.status ?? status.value
    const p = override?.page ?? props.invoices.current_page

    if (s) params.set('search', s)
    if (st && st !== 'all') params.set('status', st)
    if (p && p > 1) params.set('page', String(p))

    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

function applyFilters(next?: Partial<{ search: string; status: StatusFilter }>) {
    const qs = buildQS({
        search: next?.search ?? search.value,
        status: next?.status ?? status.value,
        page: 1,
    })

    router.get(`/admin/invoices${qs}`, {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

// debounce search
let t: number | null = null
watch(search, (val) => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => applyFilters({ search: val }), 350)
})
watch(status, (v) => applyFilters({ status: v }))

const by = computed(() => props.counts?.by_status ?? {})
const totals = computed(() => props.counts?.totals ?? ({} as any))

const statusOptions = computed(() => ([
    { value: 'all' as const, label: 'Estados: todos', count: props.counts?.all ?? props.invoices.total },
    { value: 'draft' as const, label: 'draft', count: by.value.draft ?? 0 },
    { value: 'issued' as const, label: 'issued', count: by.value.issued ?? 0 },
    { value: 'overdue' as const, label: 'overdue', count: by.value.overdue ?? 0 },
    { value: 'paid' as const, label: 'paid', count: by.value.paid ?? 0 },
    { value: 'void' as const, label: 'void', count: by.value.void ?? 0 },
]))
const currentStatus = computed(() => statusOptions.value.find(o => o.value === status.value) ?? statusOptions.value[ 0 ])

const currency = computed(() => 'USD') // tú dijiste que solo USD por ahora

function money(v: any) {
    const n = Number(v ?? 0)
    if (!Number.isFinite(n)) return '0.00'
    return new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}
function moneyWithCurrency(v: any) {
    return `${money(v)} ${currency.value}`
}

const headerSubtitle = computed(() => {
    return `Mostrando ${props.invoices.data.length} • Total: ${props.invoices.total}${isLoading.value ? ' • Cargando...' : ''}`
})

function badgeVariant(st: InvoiceRow[ 'status' ]) {
    if (st === 'paid') return 'default'
    if (st === 'overdue') return 'secondary'
    if (st === 'void') return 'secondary'
    if (st === 'draft') return 'outline'
    return 'default' // issued
}

function goInvoice(id: number) {
    router.visit(`/admin/invoices/${id}${buildQS()}`)
}
</script>

<template>

    <Head title="Facturas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1 min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight leading-tight truncate">Facturas</h1>
                    <div class="text-sm text-muted-foreground">
                        {{ headerSubtitle }}
                    </div>

                    <!-- Totales rápidos -->
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                        <span class="rounded-md border px-2 py-1">
                            Total facturado: <span class="font-mono">{{ moneyWithCurrency(totals.total_invoiced ?? 0) }}</span>
                        </span>
                        <span class="rounded-md border px-2 py-1">
                            Pagado (en facturas): <span class="font-mono">{{ moneyWithCurrency(totals.total_amount_paid_on_invoices ?? 0) }}</span>
                        </span>
                        <span class="rounded-md border px-2 py-1">
                            CxC (emitidos+vencidos): <span class="font-mono">{{ moneyWithCurrency(totals.accounts_receivable ?? 0) }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <!-- Status dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="outline" size="sm" class="gap-2">
                                <span>{{ currentStatus.label }}</span>
                                <span class="text-muted-foreground">({{ currentStatus.count }})</span>
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="start" class="min-w-45">
                            <DropdownMenuItem v-for="opt in statusOptions" :key="opt.value" :class="status === opt.value ? 'bg-muted' : ''" @click="status = opt.value">
                                <span class="flex-1">{{ opt.label }}</span>
                                <span class="text-muted-foreground">({{ opt.count }})</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Input v-model="search" placeholder="Buscar por n.º, comprobante fiscal, cliente..." class="w-full sm:w-80" />
                </div>
            </div>

            <!-- Empty -->
            <div v-if="props.invoices.data.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    No hay facturas que coincidan con tu filtro o búsqueda.
                </div>
            </div>

            <!-- Cards -->
            <div v-else class="grid gap-6 grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(360px,1fr))]">
                <Card v-for="inv in props.invoices.data" :key="inv.id" class="min-w-0 border border-border/40 rounded-xl hover:shadow-md transition-all cursor-pointer" role="button" tabindex="0" @click="goInvoice(inv.id)" @keydown.enter.prevent="goInvoice(inv.id)" @keydown.space.prevent="goInvoice(inv.id)">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-3">
                            <CardTitle class="text-base font-semibold truncate">
                                {{ inv.number }}
                            </CardTitle>

                            <div class="flex items-center gap-2">
                                <Badge :variant="badgeVariant(inv.status)" class="capitalize">
                                    {{ inv.status }}
                                </Badge>
                                <Badge variant="outline">
                                    items: {{ inv.items_count }}
                                </Badge>
                            </div>
                        </div>

                        <CardDescription class="text-xs mt-1 truncate">
                            <span class="text-muted-foreground">Cliente:</span>
                            <span class="ml-1">{{ inv.company?.name ?? '—' }}</span>
                            <span v-if="inv.fiscal_number" class="text-muted-foreground">
                                • Fiscal: {{ inv.fiscal_number }}
                            </span>
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Emitida</div>
                                <div class="font-mono">{{ inv.issued_on ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Vence</div>
                                <div class="font-mono">{{ inv.due_on ?? '—' }}</div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <div class="text-xs text-muted-foreground">Total</div>
                                <div class="font-mono">{{ money(inv.total) }} <span class="text-muted-foreground">{{ inv.currency }}</span></div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Pagado</div>
                                <div class="font-mono">{{ money(inv.amount_paid) }} <span class="text-muted-foreground">{{ inv.currency }}</span></div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Balance</div>
                                <div class="font-mono">{{ money(inv.balance) }} <span class="text-muted-foreground">{{ inv.currency }}</span></div>
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <Button size="sm" variant="outline" @click.stop="router.visit(`/admin/invoices/${inv.id}${buildQS()}`)">
                                Ver detalles
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between mt-4">
                <div class="text-sm text-muted-foreground">
                    Página {{ props.invoices.current_page }} de {{ props.invoices.last_page }}
                </div>

                <div class="flex gap-2">
                    <Button size="sm" variant="outline" :disabled="!props.invoices.prev_page_url" @click="props.invoices.prev_page_url && router.visit(props.invoices.prev_page_url, { preserveScroll: true, preserveState: true })">
                        Anterior
                    </Button>

                    <Button size="sm" variant="outline" :disabled="!props.invoices.next_page_url" @click="props.invoices.next_page_url && router.visit(props.invoices.next_page_url, { preserveScroll: true, preserveState: true })">
                        Siguiente
                    </Button>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
