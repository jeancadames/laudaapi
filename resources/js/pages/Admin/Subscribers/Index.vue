<!-- resources/js/Pages/Admin/Subscribers/Index.vue -->
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import subscribersRoutes from '@/routes/admin/subscribers'
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

type StatusFilter = 'all' | 'active' | 'inactive'
type ProviderFilter = 'all' | 'stripe'

// ✅ UI: NO null para Inputs (usa '')
type SubscriberRow = {
    id: number
    name: string
    slug: string
    country_code: string
    currency: string
    timezone: string
    provider: string // ✅ antes: string | null
    provider_mode: 'live' | 'test'
    provider_customer_id: string // ✅ antes: string | null
    active: boolean
    created_at: string | null

    // UI-only
    _error?: string
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
    subscribers: Paginator<SubscriberRow>
    filters: { search: string; status: StatusFilter; provider: ProviderFilter }
    counts: { all: number; active: number; inactive: number }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Suscriptores', href: subscribersRoutes.index().url },
]

// Loading indicator
const isLoading = ref(false)
let unsubs: Array<() => void> = []
onMounted(() => {
    const start = router.on('start', () => (isLoading.value = true))
    const finish = router.on('finish', () => (isLoading.value = false))
    unsubs = [ start, finish ]
})
onBeforeUnmount(() => unsubs.forEach((u) => u()))

// Filters (server-side)
const search = ref(props.filters?.search ?? '')
const status = ref<StatusFilter>(props.filters?.status ?? 'all')
const provider = ref<ProviderFilter>(props.filters?.provider ?? 'all')

function buildQS(override?: Partial<{ search: string; status: StatusFilter; provider: ProviderFilter; page: number }>) {
    const params = new URLSearchParams()

    const s = override?.search ?? search.value
    const st = override?.status ?? status.value
    const pr = override?.provider ?? provider.value
    const p = override?.page ?? props.subscribers.current_page

    if (s) params.set('search', s)
    if (st && st !== 'all') params.set('status', st)
    if (pr && pr !== 'all') params.set('provider', pr)
    if (p && p > 1) params.set('page', String(p))

    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

function applyFilters(next?: Partial<{ search: string; status: StatusFilter; provider: ProviderFilter }>) {
    const qs = buildQS({
        search: next?.search ?? search.value,
        status: next?.status ?? status.value,
        provider: next?.provider ?? provider.value,
        page: 1,
    })

    router.get(`/admin/subscribers${qs}`, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

// Debounce search
let t: number | null = null
watch(search, (val) => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => applyFilters({ search: val }), 350)
})

// status/provider changes (instant)
watch(status, (val) => applyFilters({ status: val }))
watch(provider, (val) => applyFilters({ provider: val }))

const statusOptions = computed(() => ([
    { value: 'all' as const, label: 'Todos', count: props.counts.all },
    { value: 'active' as const, label: 'Activos', count: props.counts.active },
    { value: 'inactive' as const, label: 'Inactivos', count: props.counts.inactive },
]))
const currentStatus = computed(() => statusOptions.value.find(o => o.value === status.value) ?? statusOptions.value[ 0 ])

const providerOptions = computed(() => ([
    { value: 'all' as const, label: 'Provider: todos' },
    { value: 'stripe' as const, label: 'stripe' },
]))
const currentProvider = computed(() => providerOptions.value.find(o => o.value === provider.value) ?? providerOptions.value[ 0 ])

// Local editable rows
const rows = reactive<SubscriberRow[]>(
    (props.subscribers.data || []).map(r => normalizeRow(r))
)

watch(() => props.subscribers.data, (data) => {
    rows.splice(0, rows.length, ...((data || []).map(r => normalizeRow(r))))
})

function normalizeRow(r: any): SubscriberRow {
    return {
        id: r.id,
        name: r.name ?? '',
        slug: r.slug ?? '',
        country_code: r.country_code ?? 'DO',
        currency: r.currency ?? 'USD',
        timezone: r.timezone ?? 'America/Bogota',

        // ✅ UI: nunca null
        provider: r.provider ?? '',
        provider_mode: (r.provider_mode ?? 'live') as 'live' | 'test',
        provider_customer_id: r.provider_customer_id ?? '',

        active: !!r.active,
        created_at: r.created_at ?? null,
    }
}

const busyIds = reactive(new Set<number>())

function summarizeErrors(errors: any) {
    if (!errors) return ''
    const keys = Object.keys(errors)
    if (!keys.length) return ''
    const k = keys[ 0 ]
    const val = errors[ k ]
    if (Array.isArray(val)) return val[ 0 ]
    return String(val)
}

function toggleActive(row: SubscriberRow) {
    busyIds.add(row.id)
    row._error = undefined

    router.patch(`/admin/subscribers/toggle/${row.id}`, {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => busyIds.delete(row.id),
        onError: () => (row._error = 'No se pudo cambiar el estado.'),
        onSuccess: () => (row.active = !row.active),
    })
}

function saveRow(row: SubscriberRow) {
    busyIds.add(row.id)
    row._error = undefined

    const payload = {
        name: row.name,
        slug: row.slug,
        country_code: row.country_code,
        currency: row.currency,
        timezone: row.timezone,

        // ✅ backend: '' -> null
        provider: row.provider.trim() === '' ? null : row.provider.trim(),
        provider_mode: row.provider_mode,
        provider_customer_id: row.provider_customer_id.trim() === '' ? null : row.provider_customer_id.trim(),

        active: !!row.active,
    }

    router.patch(`/admin/subscribers/${row.id}`, payload, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => busyIds.delete(row.id),
        onError: (errors) => (row._error = summarizeErrors(errors) || 'No se pudo guardar.'),
    })
}

const headerSubtitle = computed(() => {
    return `Mostrando ${props.subscribers.data.length} • Total: ${props.subscribers.total}${isLoading.value ? ' • Cargando...' : ''}`
})
</script>

<template>

    <Head title="Suscriptores" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1 min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight leading-tight">
                        Suscriptores
                    </h1>
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

                    <!-- Provider dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="outline" size="sm" class="gap-2">
                                <span>{{ currentProvider.label }}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="min-w-45">
                            <DropdownMenuItem v-for="opt in providerOptions" :key="opt.value" :class="provider === opt.value ? 'bg-muted' : ''" @click="provider = opt.value">
                                {{ opt.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Input v-model="search" placeholder="Buscar por nombre, slug, cliente..." class="w-full sm:w-80" />
                </div>
            </div>

            <!-- Empty -->
            <div v-if="rows.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    No hay subscribers que coincidan con tu filtro o búsqueda.
                </div>
            </div>

            <!-- Cards -->
            <div v-else class="grid gap-6 grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(360px,1fr))]">
                <Card v-for="row in rows" :key="row.id" class="min-w-0 border border-border/40 rounded-xl hover:shadow-md transition-all">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-3">
                            <CardTitle class="text-base font-semibold truncate">
                                {{ row.name }}
                            </CardTitle>

                            <div class="flex items-center gap-2">
                                <Badge :variant="row.active ? 'default' : 'secondary'">
                                    {{ row.active ? 'Activo' : 'Inactivo' }}
                                </Badge>
                                <Badge v-if="row.provider" variant="outline" class="capitalize">
                                    {{ row.provider }}
                                </Badge>
                            </div>
                        </div>

                        <CardDescription class="text-xs mt-1">
                            <span class="font-mono">{{ row.slug }}</span>
                            <span class="text-muted-foreground" v-if="row.provider_customer_id"> • {{ row.provider_customer_id }}</span>
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Nombre</div>
                                <Input v-model="row.name" />
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Slug</div>
                                <Input v-model="row.slug" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">País</div>
                                <Input v-model="row.country_code" placeholder="DO" />
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Moneda</div>
                                <Input v-model="row.currency" placeholder="USD" />
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Zona Horaria</div>
                                <Input v-model="row.timezone" placeholder="America/Bogota" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Proveedor</div>
                                <Input v-model="row.provider" placeholder="stripe" />
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Modo</div>
                                <select v-model="row.provider_mode" class="w-full rounded-md border px-3 py-2 text-sm">
                                    <option value="live">En vivo</option>
                                    <option value="test">Prueba</option>
                                </select>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">ID de Cliente</div>
                                <Input v-model="row.provider_customer_id" placeholder="cus_xxx" />
                            </div>
                        </div>

                        <p v-if="row._error" class="text-xs text-red-600">
                            {{ row._error }}
                        </p>

                        <div class="pt-2 flex justify-end gap-2">
                            <Button size="sm" variant="outline" :disabled="busyIds.has(row.id)" @click="toggleActive(row)">
                                {{ busyIds.has(row.id) ? '...' : (row.active ? 'Desactivar' : 'Activar') }}
                            </Button>

                            <Button size="sm" variant="default" :disabled="busyIds.has(row.id)" @click="saveRow(row)">
                                {{ busyIds.has(row.id) ? 'Guardando...' : 'Guardar' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between mt-2">
                <div class="text-sm text-muted-foreground">
                    Página {{ props.subscribers.current_page }} de {{ props.subscribers.last_page }}
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" :disabled="!props.subscribers.prev_page_url" @click="props.subscribers.prev_page_url && router.visit(props.subscribers.prev_page_url, { preserveScroll: true, preserveState: true })">
                        Anterior
                    </Button>

                    <Button variant="outline" :disabled="!props.subscribers.next_page_url" @click="props.subscribers.next_page_url && router.visit(props.subscribers.next_page_url, { preserveScroll: true, preserveState: true })">
                        Siguiente
                    </Button>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
