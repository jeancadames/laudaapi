<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import errorlog from '@/routes/admin/errorlog'
import { type BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
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

type UserLite = { id: number; name: string; email: string }

type ErrorLog = {
    id: number
    level: string
    message: string
    type: string | null
    fingerprint?: string | null

    method: string | null
    url: string | null
    route?: string | null
    request_id?: string | null
    status_code?: number | null

    ip: string | null
    user_agent: string | null
    user_id: number | null
    user?: UserLite | null

    occurrences?: number
    first_seen_at?: string
    last_seen_at?: string

    created_at?: string
}

type Paginator<T> = {
    data: Array<T | null | undefined>
    current_page: number
    last_page: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    logs: Paginator<ErrorLog>
    filters: { search: string; level: string; type: string; user_id: number | null; ip: string }
    levels: string[]
    types: string[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Error Log', href: errorlog.index().url },
]

const isLoading = ref(false)

// filters
const search = ref(props.filters.search ?? '')
const level = ref(props.filters.level ?? '') // ''=todos
const type = ref(props.filters.type ?? '') // ''=todos
const userId = ref<number | ''>(props.filters.user_id ?? '')
const ip = ref(props.filters.ip ?? '')

// ✅ evita el crash: filtra null/undefined
const safeRows = computed(() => {
    const rows = props.logs?.data ?? []
    return rows.filter((x): x is ErrorLog => !!x && typeof (x as any).id === 'number')
})

function levelBadgeVariant(lv: string) {
    const v = String(lv).toLowerCase()
    if ([ 'critical', 'alert', 'emergency', 'error' ].includes(v)) return 'destructive'
    if ([ 'warning' ].includes(v)) return 'secondary'
    return 'outline'
}

function buildQS(override?: Partial<{
    search: string
    level: string
    type: string
    user_id: number | ''
    ip: string
    page: number
}>) {
    const params = new URLSearchParams()

    const s = override?.search ?? search.value
    const lv = override?.level ?? level.value
    const tp = override?.type ?? type.value
    const uid = override?.user_id ?? userId.value
    const ipp = override?.ip ?? ip.value
    const p = override?.page ?? props.logs.current_page

    if (s) params.set('search', s)
    if (lv) params.set('level', lv)
    if (tp) params.set('type', tp)
    if (uid !== '') params.set('user_id', String(uid))
    if (ipp) params.set('ip', ipp)
    if (p && p > 1) params.set('page', String(p))

    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

function applyFilters(page = 1) {
    const qs = buildQS({ page })
    router.get(`/admin/errorlog${qs}`, {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

let t: number | null = null
watch([ search, level, type, userId, ip ], () => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => applyFilters(1), 300)
})

function reset() {
    search.value = ''
    level.value = ''
    type.value = ''
    userId.value = ''
    ip.value = ''
    applyFilters(1)
}

const subtitle = computed(() => `Mostrando ${safeRows.value.length} de ${props.logs.total}`)

const currentLevelLabel = computed(() => (level.value ? level.value : 'Todos los niveles'))
const currentTypeLabel = computed(() => (type.value ? type.value : 'Todos los tipos'))

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

    <Head title="Error Log" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold tracking-tight whitespace-nowrap leading-none">
                    Logs Errores
                </h1>

                <div class="mt-1 text-sm text-muted-foreground">
                    {{ subtitle }}
                    <span v-if="isLoading" class="ml-2">• Cargando...</span>
                </div>
            </div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                <!-- Filters -->
                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <Input v-model="search" placeholder="Buscar (message/url/route/fingerprint)..." class="w-full sm:w-72" />
                    <Input v-model="ip" placeholder="ip" class="w-full sm:w-40" />
                    <Input v-model="userId" placeholder="user_id" class="w-full sm:w-28" />

                    <!-- Level dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="w-full sm:w-auto whitespace-nowrap">
                                {{ currentLevelLabel }}
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" class="w-72 max-w-[90vw]">
                            <DropdownMenuLabel>Filtrar por nivel</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem :class="level === '' ? 'font-medium' : ''" @click="level = ''">
                                Todos los niveles
                            </DropdownMenuItem>
                            <DropdownMenuItem v-for="lv in props.levels" :key="lv" :class="level === lv ? 'font-medium' : ''" @click="level = lv">
                                {{ lv }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Type dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="w-full sm:w-auto whitespace-nowrap">
                                {{ currentTypeLabel }}
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" class="w-80 max-w-[90vw]">
                            <DropdownMenuLabel>Filtrar por tipo</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem :class="type === '' ? 'font-medium' : ''" @click="type = ''">
                                Todos los tipos
                            </DropdownMenuItem>
                            <DropdownMenuItem v-for="tp in props.types" :key="tp" :class="type === tp ? 'font-medium' : ''" @click="type = tp">
                                {{ tp }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button variant="outline" size="sm" class="whitespace-nowrap" @click="reset">
                        Limpiar
                    </Button>
                </div>
            </div>

            <!-- Empty -->
            <div v-if="safeRows.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">No hay errores que coincidan con tus filtros.</div>
            </div>

            <!-- List -->
            <div v-else class="grid gap-4">
                <Card v-for="l in safeRows" :key="l.id" class="border border-border/40 rounded-xl">
                    <CardHeader>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <CardTitle class="text-base font-semibold truncate">
                                    {{ l.message }}
                                </CardTitle>

                                <CardDescription class="text-xs mt-1">
                                    <div class="flex flex-wrap gap-x-3 gap-y-1">
                                        <span class="inline-flex items-center gap-2">
                                            <Badge :variant="levelBadgeVariant(l.level)" class="capitalize whitespace-nowrap">
                                                {{ l.level }}
                                            </Badge>
                                            <Badge class="whitespace-nowrap">#{{ l.id }}</Badge>
                                            <Badge v-if="l.last_seen_at" variant="secondary" class="whitespace-nowrap">
                                                {{ l.last_seen_at }}
                                            </Badge>
                                            <Badge v-else-if="l.created_at" variant="secondary" class="whitespace-nowrap">
                                                {{ l.created_at }}
                                            </Badge>
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap gap-x-3 gap-y-1 mt-1">
                                        <span v-if="l.type" class="wrap-break-word">
                                            <span class="text-muted-foreground">Tipo:</span>
                                            <span class="ml-1">{{ l.type }}</span>
                                        </span>

                                        <span class="wrap-break-word">
                                            <span class="text-muted-foreground">IP:</span>
                                            <span class="ml-1">{{ l.ip ?? '—' }}</span>
                                        </span>

                                        <span class="wrap-break-word">
                                            <span class="text-muted-foreground">Usuario:</span>
                                            <span class="ml-1">{{ l.user?.email ?? l.user_id ?? '—' }}</span>
                                        </span>

                                        <span v-if="typeof l.occurrences === 'number'" class="wrap-break-word">
                                            <span class="text-muted-foreground">Ocurrencias:</span>
                                            <span class="ml-1">{{ l.occurrences }}</span>
                                        </span>
                                    </div>
                                </CardDescription>
                            </div>

                            <div v-if="l.method || l.url" class="text-xs text-muted-foreground wrap-break-word sm:text-right">
                                <span v-if="l.method" class="mr-2 font-medium">{{ l.method }}</span>
                                <span>{{ l.url ?? '' }}</span>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="text-sm text-muted-foreground truncate">
                            {{ l.user_agent ?? '—' }}
                        </div>

                        <Link :href="`/admin/errorlog/${l.id}${buildQS()}`" class="shrink-0 w-full sm:w-auto">
                            <Button size="sm" variant="outline" class="w-full sm:w-auto whitespace-nowrap">
                                Ver detalle
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between mt-4">
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
