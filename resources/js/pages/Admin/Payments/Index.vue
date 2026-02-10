<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import payments from '@/routes/admin/payments'
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

type MethodFilter = 'all' | 'card' | 'bank_transfer' | 'cash' | 'check' | 'other'
type StatusFilter = 'all' | 'paid' | 'unpaid'

type PaymentRow = {
    id: number
    method: string
    currency: string
    amount: string | number
    paid_at: string | null
    reference: string | null
    invoice: { id: number; number: string; status: string; total: any; currency: string } | null
    company: { id: number; name: string } | null
    created_at: string | null
}

type Paginator<T> = {
    data: T[]
    current_page: number
    last_page: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

type Counts = Record<string, number>

const props = defineProps<{
    payments: Paginator<PaymentRow>
    filters: { search: string; method: MethodFilter; status: StatusFilter }
    counts: Counts
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Payments', href: payments.index().url },
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
const method = ref<MethodFilter>(props.filters?.method ?? 'all')
const status = ref<StatusFilter>(props.filters?.status ?? 'all')

// ✅ robust counts helpers (support method_xxx and method:xxx)
function getCount(key: string, fallback = 0) {
    const map = (props.counts ?? {}) as Counts
    return map[ key ] ?? fallback
}
function methodCount(m: string) {
    return getCount(`method_${m}`, getCount(`method:${m}`, 0))
}

function buildQS(
    override?: Partial<{ search: string; method: MethodFilter; status: StatusFilter; page: number }>
) {
    const params = new URLSearchParams()
    const s = override?.search ?? search.value
    const m = override?.method ?? method.value
    const st = override?.status ?? status.value
    const p = override?.page ?? props.payments.current_page

    if (s) params.set('search', s)
    if (m && m !== 'all') params.set('method', m)
    if (st && st !== 'all') params.set('status', st)
    if (p && p > 1) params.set('page', String(p))

    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

function applyFilters(next?: Partial<{ search: string; method: MethodFilter; status: StatusFilter }>) {
    const qs = buildQS({
        search: next?.search ?? search.value,
        method: next?.method ?? method.value,
        status: next?.status ?? status.value,
        page: 1,
    })

    router.get(`/admin/payments${qs}`, {}, {
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
watch(method, (v) => applyFilters({ method: v }))
watch(status, (v) => applyFilters({ status: v }))

const methodOptions = computed(() => ([
    { value: 'all' as const, label: 'Método: todos', count: getCount('all', props.payments.total) },
    { value: 'card' as const, label: 'card', count: methodCount('card') },
    { value: 'bank_transfer' as const, label: 'bank_transfer', count: methodCount('bank_transfer') },
    { value: 'cash' as const, label: 'cash', count: methodCount('cash') },
    { value: 'check' as const, label: 'check', count: methodCount('check') },
    { value: 'other' as const, label: 'other', count: methodCount('other') },
]))

const statusOptions = computed(() => ([
    { value: 'all' as const, label: 'Status: todos', count: getCount('all', props.payments.total) },
    { value: 'paid' as const, label: 'paid', count: getCount('paid', 0) },
    { value: 'unpaid' as const, label: 'unpaid', count: getCount('unpaid', 0) },
]))

const currentMethod = computed(() => methodOptions.value.find(o => o.value === method.value) ?? methodOptions.value[ 0 ])
const currentStatus = computed(() => statusOptions.value.find(o => o.value === status.value) ?? statusOptions.value[ 0 ])

const headerSubtitle = computed(() =>
    `Mostrando ${props.payments.data.length} • Total: ${props.payments.total}${isLoading.value ? ' • Cargando...' : ''}`
)

function goPayment(id: number) {
    router.visit(`/admin/payments/${id}${buildQS()}`)
}
</script>

<template>

    <Head title="Payments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1 min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight leading-tight truncate">Payments</h1>
                    <div class="text-sm text-muted-foreground">{{ headerSubtitle }}</div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <!-- Method dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="outline" size="sm" class="gap-2">
                                <span>{{ currentMethod.label }}</span>
                                <span class="text-muted-foreground">({{ currentMethod.count }})</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="min-w-45">
                            <DropdownMenuItem v-for="opt in methodOptions" :key="opt.value" :class="method === opt.value ? 'bg-muted' : ''" @click="method = opt.value">
                                <span class="flex-1">{{ opt.label }}</span>
                                <span class="text-muted-foreground">({{ opt.count }})</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

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

                    <Input v-model="search" placeholder="Buscar por referencia, invoice # o cliente..." class="w-full sm:w-72" />
                </div>
            </div>

            <!-- Empty -->
            <div v-if="props.payments.data.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    No hay pagos que coincidan con tu filtro o búsqueda.
                </div>
            </div>

            <!-- Cards -->
            <div v-else class="grid gap-6 grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(360px,1fr))]">
                <Card v-for="p in props.payments.data" :key="p.id" class="min-w-0 border border-border/40 rounded-xl hover:shadow-md transition-all cursor-pointer" role="button" tabindex="0" @click="goPayment(p.id)" @keydown.enter.prevent="goPayment(p.id)" @keydown.space.prevent="goPayment(p.id)">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-3">
                            <CardTitle class="text-base font-semibold truncate">
                                {{ p.reference ?? `Payment #${p.id}` }}
                            </CardTitle>

                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="capitalize">{{ p.method }}</Badge>
                                <Badge :variant="p.paid_at ? 'default' : 'secondary'">
                                    {{ p.paid_at ? 'Paid' : 'Unpaid' }}
                                </Badge>
                            </div>
                        </div>

                        <CardDescription class="text-xs mt-1 truncate">
                            <span class="text-muted-foreground">Cliente:</span>
                            <span class="ml-1">{{ p.company?.name ?? '—' }}</span>
                            <span v-if="p.invoice?.number" class="text-muted-foreground">
                                • Invoice: {{ p.invoice.number }}
                            </span>
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Monto</div>
                                <div class="font-mono">
                                    {{ p.amount }}
                                    <span class="text-muted-foreground">{{ p.currency }}</span>
                                </div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">Paid at</div>
                                <div class="font-mono">{{ p.paid_at ?? '—' }}</div>
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <Button size="sm" variant="outline" @click.stop="router.visit(`/admin/payments/${p.id}${buildQS()}`)">
                                Ver detalles
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between mt-4">
                <div class="text-sm text-muted-foreground">
                    Página {{ props.payments.current_page }} de {{ props.payments.last_page }}
                </div>

                <div class="flex gap-2">
                    <Button size="sm" variant="outline" :disabled="!props.payments.prev_page_url" @click="props.payments.prev_page_url && router.visit(props.payments.prev_page_url, { preserveScroll: true, preserveState: true })">
                        Anterior
                    </Button>

                    <Button size="sm" variant="outline" :disabled="!props.payments.next_page_url" @click="props.payments.next_page_url && router.visit(props.payments.next_page_url, { preserveScroll: true, preserveState: true })">
                        Siguiente
                    </Button>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
