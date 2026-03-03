<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref, watch, computed } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import requestsRoutes from '@/routes/admin/requests'
import { type BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

// ✅ shadcn-vue dropdown menu
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu'

// ✅ Status válidos (única fuente de verdad)
type Status = 'pending' | 'accepted' | 'trialing' | 'expired' | 'converted' | 'discarded'
type StatusFilter = 'all' | Status

const FILTER_STATUSES: Status[] = [
    'pending',
    'accepted',
    'trialing',
    'expired',
    'converted',
    'discarded',
]

const ALLOWED_STATUS = new Set<Status>(FILTER_STATUSES)

type ActivationRequest = {
    id: number
    contact_request_id: number | null
    user_id: number | null

    name: string
    company: string
    role: string | null
    email: string
    phone: string | null

    topic: string
    other_topic: string | null
    system: string | null
    volume: string | null
    message: string | null

    terms: boolean
    status: Status

    trial_starts_at: string | null
    trial_ends_at: string | null
    trial_days: number

    metadata: Record<string, unknown> | null
    created_at?: string
}

type Paginator<T> = {
    data: T[]
    current_page: number
    last_page: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

// ✅ counts indexables por StatusFilter
type Counts = Partial<Record<StatusFilter, number>>

const props = defineProps<{
    requests: Paginator<ActivationRequest>
    filters: { search: string; status: any } // puede venir string viejo; lo sanitizamos abajo
    counts: Counts
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Solicitudes de Activación', href: requestsRoutes.index().url },
]

const search = ref(props.filters.search ?? '')

// ✅ Sanitiza status inicial (si viene "contacted"/"activated", cae a 'all')
const initialStatus: StatusFilter = (() => {
    const s = props.filters.status
    if (s === 'all') return 'all'
    if (typeof s === 'string' && ALLOWED_STATUS.has(s as Status)) return s as Status
    return 'all'
})()

const status = ref<StatusFilter>(initialStatus)

const isLoading = ref(false)

// Labels (plural para filtro)
const STATUS_LABEL_PLURAL: Record<Status, string> = {
    pending: 'Pendientes',
    accepted: 'Aceptadas',
    trialing: 'En trial',
    expired: 'Expiradas',
    converted: 'Convertidas',
    discarded: 'Descartadas',
}

// Labels (singular para badge)
const STATUS_LABEL_SINGULAR: Record<Status, string> = {
    pending: 'Pendiente',
    accepted: 'Aceptada',
    trialing: 'En trial',
    expired: 'Expirado',
    converted: 'Convertido',
    discarded: 'Descartado',
}

function statusLabel(s: StatusFilter) {
    if (s === 'all') return 'Todos'
    return STATUS_LABEL_PLURAL[ s ]
}

function statusLabelValue(s: Status | null | undefined) {
    return s ? (STATUS_LABEL_SINGULAR[ s ] ?? s) : '—'
}

function countOf(key: StatusFilter) {
    return props.counts[ key ] ?? 0
}

const headerSubtitle = computed(() => {
    const total = props.requests.total
    return status.value === 'all'
        ? `Mostrando ${total} • Total: ${countOf('all')}`
        : `Mostrando ${total} • ${statusLabel(status.value)}: ${countOf(status.value)}`
})

const currentStatusLabel = computed(() => {
    return status.value === 'all'
        ? `Todos (${countOf('all')})`
        : `${statusLabel(status.value)} (${countOf(status.value)})`
})

function statusBadgeVariant(s: Status) {
    if (s === 'pending') return 'default'
    if (s === 'discarded' || s === 'expired') return 'destructive'
    return 'secondary'
}

function buildQS(override?: Partial<{ search: string; status: StatusFilter; page: number }>) {
    const params = new URLSearchParams()

    const s = override?.search ?? search.value
    const st = override?.status ?? status.value
    const p = override?.page ?? props.requests.current_page

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

    router.get(`/admin/requests${qs}`, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

// --------------------
// Metadata helpers
// --------------------
function metaString(meta: Record<string, unknown> | null, key: string): string | null {
    const v = meta?.[ key ]
    return typeof v === 'string' ? v : null
}

function metaNumber(meta: Record<string, unknown> | null, key: string): number | null {
    const v = meta?.[ key ]
    return typeof v === 'number' ? v : null
}

function formatDateTime(iso: string | null) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(d)
}

function linkExpiresAt(r: ActivationRequest) {
    return metaString(r.metadata, 'activation_email_expires_at')
}

function linkSentAt(r: ActivationRequest) {
    return metaString(r.metadata, 'activation_email_sent_at')
}

function linkSendCount(r: ActivationRequest) {
    return metaNumber(r.metadata, 'activation_email_send_count') ?? 0
}

function isLinkExpired(r: ActivationRequest) {
    const expires = linkExpiresAt(r)
    if (!expires) return false
    const t = new Date(expires).getTime()
    if (Number.isNaN(t)) return false
    return t < Date.now()
}

// Debounce search
let t: number | null = null
watch(search, (value) => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => applyFilters({ search: value }), 350)
})

// Status filter
watch(status, (value) => {
    applyFilters({ status: value })
})

// Loading indicator
let unsubs: Array<() => void> = []
onMounted(() => {
    const start = router.on('start', () => (isLoading.value = true))
    const finish = router.on('finish', () => (isLoading.value = false))
    unsubs = [ start, finish ]
})
onBeforeUnmount(() => unsubs.forEach((u) => u()))
</script>

<template>

    <Head title="Solicitudes de Activación" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">Solicitudes de Activación</h1>
                    <div class="text-sm text-muted-foreground">
                        {{ headerSubtitle }}
                        <span v-if="isLoading" class="ml-2">• Cargando...</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <!-- ✅ Dropdown filter -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="whitespace-nowrap">
                                {{ currentStatusLabel }}
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" class="w-72">
                            <DropdownMenuLabel>Filtrar por estado</DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <DropdownMenuItem :class="status === 'all' ? 'font-medium' : ''" @click="status = 'all'">
                                Todos ({{ countOf('all') }})
                            </DropdownMenuItem>

                            <DropdownMenuItem v-for="st in FILTER_STATUSES" :key="st" :class="status === st ? 'font-medium' : ''" @click="status = st">
                                {{ statusLabel(st) }} ({{ countOf(st) }})
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Input v-model="search" placeholder="Buscar por nombre, email, empresa..." class="w-full sm:w-80" />
                </div>
            </div>

            <!-- Empty -->
            <div v-if="props.requests.data.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    No hay solicitudes que coincidan con tu filtro o búsqueda.
                </div>
            </div>

            <!-- Cards -->
            <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <Card v-for="r in props.requests.data" :key="r.id" class="border border-border/40 rounded-xl hover:shadow-md transition-all">
                    <Link :href="`/admin/requests/${r.id}${buildQS()}`" class="block">
                        <CardHeader class="overflow-hidden">
                            <div class="flex items-start justify-between gap-3">
                                <!-- ✅ w-0 + flex-1 + min-w-0 = shrink real -->
                                <div class="w-0 flex-1 min-w-0 overflow-hidden">
                                    <CardTitle class="text-base font-semibold">
                                        <span class="block truncate">{{ r.name }}</span>
                                    </CardTitle>

                                    <!-- ✅ usar <p> normal para truncar sí o sí -->
                                    <p class="mt-1 text-xs text-muted-foreground truncate">
                                        {{ r.company }} • {{ r.email }}
                                    </p>
                                </div>

                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <Badge :variant="statusBadgeVariant(r.status)" class="capitalize whitespace-nowrap">
                                        {{ statusLabelValue(r.status) }}
                                    </Badge>

                                    <Badge v-if="r.status === 'pending' && isLinkExpired(r)" variant="destructive" class="whitespace-nowrap">
                                        Link expirado
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div>
                                <span class="font-medium text-muted-foreground">Tema:</span>
                                <span class="ml-1">{{ r.topic }}</span>
                            </div>

                            <div>
                                <span class="font-medium text-muted-foreground">Sistema:</span>
                                <span class="ml-1">{{ r.system ?? '—' }}</span>
                            </div>

                            <div>
                                <span class="font-medium text-muted-foreground">Volumen:</span>
                                <span class="ml-1">{{ r.volume ?? '—' }}</span>
                            </div>

                            <div class="pt-1">
                                <span class="font-medium text-muted-foreground">Link enviado:</span>
                                <span class="ml-1">{{ formatDateTime(linkSentAt(r)) }}</span>
                            </div>

                            <div>
                                <span class="font-medium text-muted-foreground">Expira:</span>
                                <span class="ml-1">{{ formatDateTime(linkExpiresAt(r)) }}</span>
                            </div>

                            <div>
                                <span class="font-medium text-muted-foreground">Reenvíos:</span>
                                <span class="ml-1">{{ linkSendCount(r) }}</span>
                            </div>
                        </CardContent>
                    </Link>

                    <!-- ✅ Acciones -->
                    <div class="px-6 pb-4 flex justify-end">
                        <Button size="sm" variant="outline" class="whitespace-nowrap" @click.stop="router.visit(`/admin/requests/${r.id}${buildQS()}`)">
                            Ver detalles
                        </Button>
                    </div>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between mt-4">
                <div class="text-sm text-muted-foreground">
                    Página {{ props.requests.current_page }} de {{ props.requests.last_page }}
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" :disabled="!props.requests.prev_page_url" @click="props.requests.prev_page_url && router.visit(props.requests.prev_page_url, { preserveScroll: true, preserveState: true })">
                        Anterior
                    </Button>

                    <Button variant="outline" :disabled="!props.requests.next_page_url" @click="props.requests.next_page_url && router.visit(props.requests.next_page_url, { preserveScroll: true, preserveState: true })">
                        Siguiente
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
