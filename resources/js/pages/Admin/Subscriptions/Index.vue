<!-- resources/js/Pages/Admin/Subscriptions/Index.vue -->
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import subscriptionsRoutes from '@/routes/admin/subscriptions'
import { type BreadcrumbItem } from '@/types'

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

type SubscriptionStatus = 'trialing' | 'active' | 'past_due' | 'cancelled' | 'expired'
type Cycle = 'monthly' | 'yearly'
type StatusFilter = 'all' | SubscriptionStatus
type CycleFilter = 'all' | Cycle

type SubscriberLite = {
    id: number
    name: string
    slug?: string | null
    active: boolean
    company_name?: string | null
}

type SubscriptionRow = {
    id: number
    subscriber: SubscriberLite

    status: SubscriptionStatus
    billing_cycle: Cycle
    currency: string

    subtotal_amount: number
    discount_amount: number
    tax_amount: number
    total_amount: number

    trial_ends_at: string | null
    current_period_start: string | null
    current_period_end: string | null

    provider: string | null
    provider_subscription_id: string | null

    created_at?: string | null
}

type Paginator<T> = {
    data: T[]
    current_page: number
    last_page: number
    per_page?: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    subscriptions: Paginator<SubscriptionRow>
    filters: {
        search?: string
        status?: StatusFilter
        cycle?: CycleFilter
        page?: number
    }
    counts: {
        all: number
        trialing: number
        active: number
        past_due: number
        cancelled: number
        expired: number
    }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Suscripciones', href: subscriptionsRoutes.index().url },
]

// state filtros
const search = ref(props.filters.search ?? '')
const status = ref<StatusFilter>(props.filters.status ?? 'all')
const cycle = ref<CycleFilter>(props.filters.cycle ?? 'all')

// loading indicator
const isLoading = ref(false)
let unsubs: Array<() => void> = []
onMounted(() => {
    const start = router.on('start', () => (isLoading.value = true))
    const finish = router.on('finish', () => (isLoading.value = false))
    unsubs = [ start, finish ]
})
onBeforeUnmount(() => unsubs.forEach((u) => u()))

function buildQS(override?: Partial<{ search: string; status: StatusFilter; cycle: CycleFilter; page: number }>) {
    const params = new URLSearchParams()

    const s = override?.search ?? search.value
    const st = override?.status ?? status.value
    const cy = override?.cycle ?? cycle.value
    const p = override?.page ?? props.subscriptions.current_page

    if (s) params.set('search', s)
    if (st && st !== 'all') params.set('status', st)
    if (cy && cy !== 'all') params.set('cycle', cy)
    if (p && p > 1) params.set('page', String(p))

    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

function applyFilters(next?: Partial<{ search: string; status: StatusFilter; cycle: CycleFilter }>) {
    const s = (next?.search ?? search.value).trim()
    const st = next?.status ?? status.value
    const cy = next?.cycle ?? cycle.value

    const query = {
        search: s || undefined,
        status: st !== 'all' ? st : undefined,
        cycle: cy !== 'all' ? cy : undefined,
        page: 1,
    }

    router.get(subscriptions.index({ query }).url, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

// debounce para search
let t: number | null = null
watch(search, (v) => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => applyFilters({ search: v }), 350)
})

// cambios instantáneos para dropdown
watch(status, (v) => applyFilters({ status: v }))
watch(cycle, (v) => applyFilters({ cycle: v }))

const statusOptions = computed(() => ([
    { value: 'all' as const, label: 'Todos', count: props.counts.all },
    { value: 'trialing' as const, label: 'Trial', count: props.counts.trialing },
    { value: 'active' as const, label: 'Activas', count: props.counts.active },
    { value: 'past_due' as const, label: 'Past due', count: props.counts.past_due },
    { value: 'cancelled' as const, label: 'Canceladas', count: props.counts.cancelled },
    { value: 'expired' as const, label: 'Expiradas', count: props.counts.expired },
]))
const currentStatus = computed(() => statusOptions.value.find(o => o.value === status.value) ?? statusOptions.value[ 0 ])

const cycleOptions = computed(() => ([
    { value: 'all' as const, label: 'Ciclo: todos' },
    { value: 'monthly' as const, label: 'monthly' },
    { value: 'yearly' as const, label: 'yearly' },
]))
const currentCycle = computed(() => cycleOptions.value.find(o => o.value === cycle.value) ?? cycleOptions.value[ 0 ])

const headerSubtitle = computed(() => {
    const total = props.subscriptions.total
    const shown = props.subscriptions.data.length
    return `Mostrando ${shown} • Total: ${total}${isLoading.value ? ' • Cargando...' : ''}`
})

function money(amount: number, currency: string) {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount ?? 0)
    } catch {
        return `${currency} ${Number(amount ?? 0).toFixed(2)}`
    }
}

function dateShort(iso: string | null) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'
    return d.toLocaleDateString()
}

function statusBadgeVariant(st: SubscriptionStatus) {
    if (st === 'active') return 'default'
    if (st === 'trialing') return 'secondary'
    if (st === 'past_due') return 'destructive'
    return 'outline'
}

function statusLabel(st: SubscriptionStatus) {
    switch (st) {
        case 'trialing': return 'Prueba'
        case 'active': return 'Activa'
        case 'past_due': return 'Vencido'
        case 'cancelled': return 'Cancelada'
        case 'expired': return 'Expirada'
        default: return st
    }
}
</script>

<template>

    <Head title="Suscripciones" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1 min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight truncate">Suscripciones</h1>
                    <div class="text-sm text-muted-foreground">
                        {{ headerSubtitle }}
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

                    <!-- Cycle dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="outline" size="sm" class="gap-2">
                                <span>{{ currentCycle.label }}</span>
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="start" class="min-w-45">
                            <DropdownMenuItem v-for="opt in cycleOptions" :key="opt.value" :class="cycle === opt.value ? 'bg-muted' : ''" @click="cycle = opt.value">
                                {{ opt.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Input v-model="search" placeholder="Buscar por suscriptor, slug o id de pr..." class="w-full sm:w-80" />
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="props.subscriptions.data.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    No hay suscripciones que coincidan con tu filtro o búsqueda.
                </div>
            </div>

            <!-- Cards -->
            <div v-else class="grid gap-6 grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(360px,1fr))]">
                <Card v-for="s in props.subscriptions.data" :key="s.id" class="min-w-0 border border-border/40 rounded-xl hover:shadow-md transition-all">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-3">
                            <CardTitle class="text-base font-semibold truncate">
                                {{ s.subscriber?.name ?? 'Suscriptor' }}
                            </CardTitle>

                            <div class="flex items-center gap-2">
                                <Badge :variant="statusBadgeVariant(s.status)" class="capitalize">
                                    {{ statusLabel(s.status) }}
                                </Badge>
                                <Badge variant="outline" class="capitalize">
                                    {{ s.billing_cycle }}
                                </Badge>
                            </div>
                        </div>

                        <CardDescription class="text-xs mt-1">
                            <span class="text-muted-foreground">Sub#</span>
                            <span class="font-mono">{{ s.id }}</span>

                            <span v-if="s.subscriber?.slug" class="text-muted-foreground"> • </span>
                            <span v-if="s.subscriber?.slug" class="font-mono">{{ s.subscriber.slug }}</span>

                            <span v-if="s.provider_subscription_id" class="text-muted-foreground"> • </span>
                            <span v-if="s.provider_subscription_id" class="font-mono">
                                {{ s.provider }}: {{ s.provider_subscription_id }}
                            </span>
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Total</div>
                                <div class="font-semibold">
                                    {{ money(s.total_amount ?? 0, s.currency ?? 'USD') }}
                                </div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">Impuestos</div>
                                <div>
                                    {{ money(s.tax_amount ?? 0, s.currency ?? 'USD') }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">La prueba acaba</div>
                                <div>{{ dateShort(s.trial_ends_at) }}</div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">El periodo acaba</div>
                                <div>{{ dateShort(s.current_period_end) }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Suscriptor activo</div>
                                <Badge :variant="s.subscriber?.active ? 'default' : 'secondary'">
                                    {{ s.subscriber?.active ? 'Sí' : 'No' }}
                                </Badge>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">Moneda</div>
                                <div class="font-mono">{{ s.currency }}</div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="pt-2 flex justify-end gap-2">
                            <!-- si tienes show route -->
                            <Button size="sm" variant="outline" as-child>
                                <Link :href="`/admin/subscriptions/${s.id}${buildQS()}`">
                                    Ver
                                </Link>
                            </Button>

                            <!-- ejemplo: ir al subscriber -->
                            <Button size="sm" variant="outline" as-child>
                                <Link :href="`/admin/subscribers/${s.subscriber.id}`">
                                    Suscriptor
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between mt-2">
                <div class="text-sm text-muted-foreground">
                    Página {{ props.subscriptions.current_page }} de {{ props.subscriptions.last_page }}
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" :disabled="!props.subscriptions.prev_page_url" @click="props.subscriptions.prev_page_url && router.visit(props.subscriptions.prev_page_url, { preserveScroll: true, preserveState: true })">
                        Anterior
                    </Button>

                    <Button variant="outline" :disabled="!props.subscriptions.next_page_url" @click="props.subscriptions.next_page_url && router.visit(props.subscriptions.next_page_url, { preserveScroll: true, preserveState: true })">
                        Siguiente
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
