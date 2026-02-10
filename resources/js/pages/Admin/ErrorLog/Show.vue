<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import errorlog from '@/routes/admin/errorlog'
import { type BreadcrumbItem } from '@/types'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type Level = string

type ErrorLog = {
    id: number
    level: Level
    type: string | null
    fingerprint: string
    message: string
    file: string | null
    line: number | null
    code: string | null
    trace: string | null

    method: string | null
    url: string | null
    route: string | null
    request_id: string | null
    status_code: number | null

    user_id: number | null
    ip: string | null
    user_agent: string | null

    context: Record<string, unknown> | null
    tags: string[] | null

    occurrences: number
    first_seen_at: string
    last_seen_at: string

    user?: { id: number; name: string; email: string } | null
}

const props = defineProps<{
    log: ErrorLog
}>()

const page = usePage()

const backUrl = computed(() => {
    const q = page.url.split('?')[ 1 ]
    const base = errorlog.index().url
    return q ? `${base}?${q}` : base
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Logs Errores', href: backUrl.value },
    { title: `Error #${props.log.id}`, href: page.url },
])

const isLoading = ref(false)

function levelVariant(lv: string) {
    if ([ 'critical', 'alert', 'emergency' ].includes(lv)) return 'destructive'
    if (lv === 'error') return 'destructive'
    if (lv === 'warning') return 'secondary'
    return 'secondary'
}

function formatDateTime(v?: string | null) {
    if (!v) return '—'
    const d = new Date(v)
    return d.toLocaleString()
}

function pretty(obj: unknown) {
    try {
        return JSON.stringify(obj, null, 2)
    } catch {
        return String(obj)
    }
}

async function copyFingerprint() {
    try {
        await navigator.clipboard.writeText(props.log.fingerprint)
    } catch {
        // no-op
    }
}

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

    <Head :title="`Error Log #${props.log.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Detalle de Error</h1>
                    <div v-if="isLoading" class="text-sm text-muted-foreground mt-1">Cargando...</div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" @click="copyFingerprint">
                        Copiar fingerprint
                    </Button>
                    <Button variant="outline" @click="router.visit(backUrl, { preserveScroll: true })">
                        Volver
                    </Button>
                </div>
            </div>

            <Card class="border border-border/40 rounded-xl">
                <CardHeader>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div class="min-w-0">
                            <CardTitle class="text-lg font-semibold truncate">
                                {{ props.log.message }}
                            </CardTitle>
                            <CardDescription class="text-sm mt-1">
                                <span class="text-muted-foreground">Fingerprint:</span>
                                <span class="ml-1 font-mono text-xs break-all">{{ props.log.fingerprint }}</span>
                            </CardDescription>
                        </div>

                        <div class="flex sm:justify-end">
                            <Badge :variant="levelVariant(props.log.level)" class="uppercase whitespace-nowrap">
                                {{ props.log.level }}
                            </Badge>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-6 text-sm">
                    <!-- Stats -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <div class="text-muted-foreground font-medium">Ocurrencias</div>
                            <div class="text-base font-semibold">{{ props.log.occurrences }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">Primera vez</div>
                            <div>{{ formatDateTime(props.log.first_seen_at) }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">Última vez</div>
                            <div>{{ formatDateTime(props.log.last_seen_at) }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">Tipo</div>
                            <div class="break-all">{{ props.log.type ?? '—' }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">Archivo</div>
                            <div class="break-all">
                                {{ props.log.file ?? '—' }}<span v-if="props.log.line">:{{ props.log.line }}</span>
                            </div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">Código / Status</div>
                            <div>
                                <span>{{ props.log.code ?? '—' }}</span>
                                <span class="mx-2 text-muted-foreground">•</span>
                                <span>{{ props.log.status_code ?? '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Request info -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <div class="text-muted-foreground font-medium">Ruta</div>
                            <div class="break-all">{{ props.log.route ?? '—' }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">URL</div>
                            <div class="break-all">{{ props.log.url ?? '—' }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">Método</div>
                            <div>{{ props.log.method ?? '—' }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">Request ID</div>
                            <div class="break-all font-mono text-xs">{{ props.log.request_id ?? '—' }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">IP</div>
                            <div>{{ props.log.ip ?? '—' }}</div>
                        </div>

                        <div>
                            <div class="text-muted-foreground font-medium">Usuario</div>
                            <div>
                                <span v-if="props.log.user">
                                    {{ props.log.user.name }} • {{ props.log.user.email }}
                                </span>
                                <span v-else>
                                    {{ props.log.user_id ?? '—' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="props.log.tags && props.log.tags.length">
                        <div class="text-muted-foreground font-medium mb-2">Tags</div>
                        <div class="flex flex-wrap gap-2">
                            <Badge v-for="t in props.log.tags" :key="t" variant="secondary">
                                {{ t }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Context -->
                    <div v-if="props.log.context">
                        <div class="text-muted-foreground font-medium mb-2">Context</div>
                        <pre class="rounded-lg border border-border/40 bg-muted/20 p-4 text-xs overflow-auto whitespace-pre">
{{ pretty(props.log.context) }}
</pre>
                    </div>

                    <!-- Trace -->
                    <div v-if="props.log.trace">
                        <div class="text-muted-foreground font-medium mb-2">Trace</div>
                        <pre class="rounded-lg border border-border/40 bg-muted/20 p-4 text-xs overflow-auto whitespace-pre">
{{ props.log.trace }}
</pre>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
