<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref, watch, computed } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import { index as adminContactsIndex } from '@/routes/admin/contacts'
import { type BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

// ✅ NUEVO: dropdown (ajusta rutas si difieren en tu proyecto)
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu'

type ContactRequest = {
    id: number
    name: string
    email: string
    phone: string | null
    company: string | null
    topic: string | null
    message: string | null
    terms: boolean
    metadata: Record<string, unknown> | null
    read_at: string | null
    created_at?: string
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

type StatusFilter = 'all' | 'unread' | 'read'

const props = defineProps<{
    contacts: Paginator<ContactRequest>
    filters: { search: string; status: StatusFilter }
    counts: { all: number; unread: number; read: number }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Solicitudes de Contacto', href: adminContactsIndex().url },
]

const search = ref(props.filters.search ?? '')
const status = ref<StatusFilter>(props.filters.status ?? 'all')

const isLoading = ref(false)
const isMarkingAll = ref(false)

const headerSubtitle = computed(() => {
    const map = {
        all: `Mostrando ${props.contacts.total} • Total: ${props.counts.all}`,
        unread: `Mostrando ${props.contacts.total} • Nuevos: ${props.counts.unread}`,
        read: `Mostrando ${props.contacts.total} • Leídos: ${props.counts.read}`,
    } as const
    return map[ status.value ]
})

function safeTel(phone: string) {
    return phone.trim().replace(/[^\d+]/g, '')
}

/**
 * Construye QS consistente para:
 * - navegar a detail preservando filtros/página
 * - aplicar filtros reseteando a page=1
 * Nota: status=all se omite del QS (limpio)
 */
function buildQS(override?: Partial<{ search: string; status: StatusFilter; page: number }>) {
    const params = new URLSearchParams()

    const s = override?.search ?? search.value
    const st = override?.status ?? status.value
    const p = override?.page ?? props.contacts.current_page

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
        page: 1, // ✅ al cambiar filtro o búsqueda, reinicia a primera página
    })

    router.get(`/admin/contacts${qs}`, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function markAsRead(id: number) {
    router.post(`/admin/contacts/${id}/read`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => router.reload({ only: [ 'contacts', 'counts', 'filters' ] }),
    })
}

function markAllAsRead() {
    if (isMarkingAll.value) return
    isMarkingAll.value = true

    router.post('/admin/contacts/read-all', {
        search: search.value,
        status: status.value, // debe ser 'unread'
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (isMarkingAll.value = false),
        onSuccess: () => router.reload({ only: [ 'contacts', 'counts', 'filters' ] }),
    })
}

// Debounce server-side search
let t: number | null = null
watch(search, (value) => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => {
        applyFilters({ search: value })
    }, 350)
})

// Cambiar filtro (inmediato)
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

// ✅ NUEVO: opciones + label actual para el dropdown
const statusOptions = computed(() => ([
    { value: 'all' as const, label: 'Todos', count: props.counts.all },
    { value: 'unread' as const, label: 'Nuevos', count: props.counts.unread },
    { value: 'read' as const, label: 'Leídos', count: props.counts.read },
]))

const currentStatus = computed(() => {
    return statusOptions.value.find(o => o.value === status.value) ?? statusOptions.value[ 0 ]
})
</script>

<template>

    <Head title="Solicitudes de Contacto" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">Solicitudes de Contacto</h1>
                    <div class="text-sm text-muted-foreground">
                        {{ headerSubtitle }}
                        <span v-if="isLoading" class="ml-2">• Cargando...</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <!-- ✅ Dropdown status -->
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

                    <Input v-model="search" placeholder="Buscar por nombre o email..." class="w-full sm:w-72" />

                    <Button v-if="status === 'unread' && props.counts.unread > 0" variant="default" :disabled="isMarkingAll" @click="markAllAsRead">
                        {{ isMarkingAll ? 'Marcando...' : 'Marcar todos como leídos' }}
                    </Button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="props.contacts.data.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    No hay solicitudes que coincidan con tu filtro o búsqueda.
                </div>
            </div>

            <!-- Cards Grid -->
            <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <Card v-for="contact in props.contacts.data" :key="contact.id" class="border border-border/40 rounded-xl hover:shadow-md transition-all">
                    <!-- Card clickable -->
                    <Link :href="`/admin/contacts/${contact.id}${buildQS()}`" class="block">
                        <CardHeader>
                            <div class="flex items-center justify-between gap-3">
                                <CardTitle class="text-base font-semibold truncate">
                                    {{ contact.name }}
                                </CardTitle>

                                <Badge :variant="contact.read_at ? 'secondary' : 'default'" class="capitalize">
                                    {{ contact.read_at ? 'Leído' : 'Nuevo' }}
                                </Badge>
                            </div>

                            <CardDescription class="text-xs mt-1 truncate">
                                {{ contact.email }}
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-2 text-sm">
                            <div>
                                <span class="font-medium text-muted-foreground">Email:</span>
                                <a class="ml-1 underline underline-offset-2" :href="`mailto:${contact.email}`" @click.stop>
                                    {{ contact.email }}
                                </a>
                            </div>

                            <div>
                                <span class="font-medium text-muted-foreground">Teléfono:</span>
                                <template v-if="contact.phone">
                                    <a class="ml-1 underline underline-offset-2" :href="`tel:${safeTel(contact.phone)}`" @click.stop>
                                        {{ contact.phone }}
                                    </a>
                                </template>
                                <span v-else class="ml-1">—</span>
                            </div>

                            <div>
                                <span class="font-medium text-muted-foreground">Empresa:</span>
                                <span class="ml-1">{{ contact.company ?? '—' }}</span>
                            </div>

                            <div>
                                <span class="font-medium text-muted-foreground">Tema:</span>
                                <span class="ml-1">{{ contact.topic ?? '—' }}</span>
                            </div>
                        </CardContent>
                    </Link>

                    <!-- Actions -->
                    <div class="px-6 pb-4 flex justify-end gap-2">
                        <Button v-if="!contact.read_at" size="sm" variant="default" @click.stop="markAsRead(contact.id)">
                            Marcar leído
                        </Button>

                        <Button size="sm" variant="outline" @click.stop="router.visit(`/admin/contacts/${contact.id}${buildQS()}`)">
                            Ver detalles
                        </Button>
                    </div>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between mt-4">
                <div class="text-sm text-muted-foreground">
                    Página {{ props.contacts.current_page }} de {{ props.contacts.last_page }}
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" :disabled="!props.contacts.prev_page_url" @click="props.contacts.prev_page_url && router.visit(props.contacts.prev_page_url, { preserveScroll: true, preserveState: true })">
                        Anterior
                    </Button>

                    <Button variant="outline" :disabled="!props.contacts.next_page_url" @click="props.contacts.next_page_url && router.visit(props.contacts.next_page_url, { preserveScroll: true, preserveState: true })">
                        Siguiente
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
