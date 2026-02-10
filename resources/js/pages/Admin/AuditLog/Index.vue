<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import auditlog from '@/routes/admin/auditlog'
import { type BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'

// ✅ shadcn-vue dropdown menu
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu'

type UserLite = { id: number; name: string; email: string }

type AuditLog = {
    id: number
    event: string
    model_type: string | null
    model_id: number | null
    data: Record<string, any> | null
    ip: string | null
    user_agent: string | null
    user_id: number | null
    created_at: string
    user?: UserLite | null
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
    logs: Paginator<AuditLog>
    filters: { search: string; event: string; model_type: string; user_id: number | null; ip: string }
    events: string[]
    modelTypes: string[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Logs Auditoría', href: auditlog.index().url },
]

const isLoading = ref(false)

const search = ref(props.filters.search ?? '')
const event = ref(props.filters.event ?? '') // '' = todos
const modelType = ref(props.filters.model_type ?? '') // '' = todos
const userId = ref<number | ''>(props.filters.user_id ?? '')
const ip = ref(props.filters.ip ?? '')

function buildQS(override?: Partial<{ search: string; event: string; model_type: string; user_id: number | ''; ip: string; page: number }>) {
    const params = new URLSearchParams()

    const s = override?.search ?? search.value
    const e = override?.event ?? event.value
    const mt = override?.model_type ?? modelType.value
    const uid = override?.user_id ?? userId.value
    const ipp = override?.ip ?? ip.value
    const p = override?.page ?? props.logs.current_page

    if (s) params.set('search', s)
    if (e) params.set('event', e)
    if (mt) params.set('model_type', mt)
    if (uid !== '') params.set('user_id', String(uid))
    if (ipp) params.set('ip', ipp)
    if (p && p > 1) params.set('page', String(p))

    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

function applyFilters(page = 1) {
    const qs = buildQS({ page })
    router.get(`/admin/auditlog${qs}`, {}, { preserveScroll: true, preserveState: true, replace: true })
}

let t: number | null = null
watch([ search, event, modelType, userId, ip ], () => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => applyFilters(1), 300)
})

function reset() {
    search.value = ''
    event.value = ''
    modelType.value = ''
    userId.value = ''
    ip.value = ''
    applyFilters(1)
}

const subtitle = computed(() => `Mostrando ${props.logs.data.length} de ${props.logs.total}`)

const currentEventLabel = computed(() => (event.value ? event.value : 'Todos los eventos'))
const currentModelTypeLabel = computed(() => (modelType.value ? modelType.value : 'Todos los modelos'))

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

    <Head title="Logs Auditoría" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- ✅ evita cortes raros en mobile sin forzar scroll -->
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-clip rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold tracking-tight whitespace-nowrap leading-none">
                    Logs Auditoria
                </h1>

                <div class="mt-1 text-sm text-muted-foreground">
                    {{ subtitle }}
                    <span v-if="isLoading" class="ml-2">• Cargando...</span>
                </div>
            </div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                <!-- Filters -->
                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <Input v-model="search" placeholder="Buscar (event/model/ip/ua/data)..." class="w-full sm:w-72" />
                    <Input v-model="ip" placeholder="ip" class="w-full sm:w-40" />
                    <Input v-model="userId" placeholder="user_id" class="w-full sm:w-28" />

                    <!-- Event dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="w-full sm:w-auto whitespace-normal sm:whitespace-nowrap">
                                {{ currentEventLabel }}
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" class="w-80 max-w-[90vw]">
                            <DropdownMenuLabel>Filtrar por evento</DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <DropdownMenuItem :class="event === '' ? 'font-medium' : ''" @click="event = ''">
                                Todos los eventos
                            </DropdownMenuItem>

                            <DropdownMenuItem v-for="ev in props.events" :key="ev" :class="event === ev ? 'font-medium' : ''" @click="event = ev">
                                {{ ev }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Model type dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="w-full sm:w-auto whitespace-normal sm:whitespace-nowrap">
                                {{ currentModelTypeLabel }}
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" class="w-80 max-w-[90vw]">
                            <DropdownMenuLabel>Filtrar por modelo</DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <DropdownMenuItem :class="modelType === '' ? 'font-medium' : ''" @click="modelType = ''">
                                Todos los modelos
                            </DropdownMenuItem>

                            <DropdownMenuItem v-for="mt in props.modelTypes" :key="mt" :class="modelType === mt ? 'font-medium' : ''" @click="modelType = mt">
                                {{ mt }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button variant="outline" size="sm" class="w-full sm:w-auto whitespace-nowrap" @click="reset">
                        Limpiar
                    </Button>
                </div>
            </div>

            <!-- Empty -->
            <div v-if="props.logs.data.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">No hay logs que coincidan con tus filtros.</div>
            </div>

            <!-- List -->
            <div v-else class="grid gap-4">
                <Card v-for="l in props.logs.data" :key="l.id" class="border border-border/40 rounded-xl">
                    <CardHeader>
                        <!-- ✅ mobile: todo en columnas con wrap, sm+: layout horizontal -->
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <!-- ✅ 2 líneas en mobile, 1 línea en sm+ -->
                                <CardTitle class="text-base font-semibold wrap-break-word line-clamp-2 sm:line-clamp-1">
                                    {{ l.event }}
                                </CardTitle>

                                <!-- ✅ wrap en mobile, compacto en sm+ -->
                                <CardDescription class="text-xs mt-1 space-y-1 sm:space-y-0 sm:whitespace-nowrap">
                                    <div class="wrap-break-word whitespace-normal sm:truncate sm:max-w-160">
                                        <span v-if="l.model_type" class="mr-2">
                                            {{ l.model_type }}#{{ l.model_id ?? '—' }}
                                        </span>
                                        <span class="mr-2">IP: {{ l.ip ?? '—' }}</span>
                                        <span>Usuario: {{ l.user?.email ?? l.user_id ?? '—' }}</span>
                                    </div>
                                </CardDescription>
                            </div>

                            <!-- ✅ badges nunca se salen -->
                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <Badge class="whitespace-nowrap">#{{ l.id }}</Badge>
                                <Badge variant="secondary" class="whitespace-nowrap">{{ l.created_at }}</Badge>
                            </div>
                        </div>
                    </CardHeader>

                    <!-- ✅ mobile: stack, sm+: row -->
                    <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <!-- ✅ user_agent completo en mobile, truncado en sm+ -->
                        <div class="min-w-0 text-sm text-muted-foreground">
                            <div class="wrap-break-word whitespace-normal sm:truncate sm:whitespace-nowrap">
                                {{ l.user_agent ?? '—' }}
                            </div>
                        </div>

                        <!-- ✅ botón full width en mobile -->
                        <Link :href="`/admin/auditlog/${l.id}${buildQS()}`" class="shrink-0 w-full sm:w-auto">
                            <Button size="sm" variant="outline" class="w-full sm:w-auto whitespace-nowrap">
                                Ver detalle
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mt-4">
                <div class="text-sm text-muted-foreground">
                    Página {{ props.logs.current_page }} de {{ props.logs.last_page }}
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" :disabled="!props.logs.prev_page_url" @click="props.logs.prev_page_url && router.visit(props.logs.prev_page_url, { preserveScroll: true, preserveState: true })">
                        Anterior
                    </Button>

                    <Button variant="outline" :disabled="!props.logs.next_page_url" @click="props.logs.next_page_url && router.visit(props.logs.next_page_url, { preserveScroll: true, preserveState: true })">
                        Siguiente
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
