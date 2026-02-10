<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import auditlog from '@/routes/admin/auditlog'
import { type BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

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

const props = defineProps<{ log: AuditLog }>()
const page = usePage()

const backUrl = computed(() => {
    const q = page.url.split('?')[ 1 ]
    const base = auditlog.index().url
    return q ? `${base}?${q}` : base
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Logs Auditoría', href: backUrl.value },
    { title: `#${props.log.id}`, href: page.url },
])

function prettyJson(v: any) {
    try {
        return JSON.stringify(v, null, 2)
    } catch {
        return String(v)
    }
}
</script>

<template>

    <Head :title="`Audit Log #${props.log.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Detalle de Audit Log</h1>
                    <div class="text-sm text-muted-foreground mt-1">
                        Evento: <span class="font-medium">{{ props.log.event }}</span>
                    </div>
                </div>

                <Button variant="outline" @click="router.visit(backUrl, { preserveScroll: true })">Volver</Button>
            </div>

            <Card class="border border-border/40 rounded-xl">
                <CardHeader>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <CardTitle class="text-xl font-semibold">
                                {{ props.log.event }}
                            </CardTitle>

                            <CardDescription class="text-sm mt-1">
                                <div class="flex flex-wrap gap-x-4 gap-y-1">
                                    <span><span class="text-muted-foreground">ID:</span> #{{ props.log.id }}</span>
                                    <span v-if="props.log.model_type"><span class="text-muted-foreground">Modelo:</span> {{ props.log.model_type }}#{{ props.log.model_id ?? '—' }}</span>
                                    <span><span class="text-muted-foreground">IP:</span> {{ props.log.ip ?? '—' }}</span>
                                    <span><span class="text-muted-foreground">Usuario:</span> {{ props.log.user?.email ?? props.log.user_id ?? '—' }}</span>
                                    <Badge variant="secondary" class="whitespace-nowrap">{{ props.log.created_at }}</Badge>
                                </div>
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4 text-sm">
                    <div>
                        <div class="font-medium text-muted-foreground mb-1">User Agent</div>
                        <div class="rounded-lg border border-border/40 bg-muted/20 p-3 wrap-break-word">
                            {{ props.log.user_agent ?? '—' }}
                        </div>
                    </div>

                    <div>
                        <div class="font-medium text-muted-foreground mb-1">Data (JSON)</div>
                        <pre class="rounded-lg border border-border/40 bg-muted/20 p-3 overflow-auto text-xs leading-relaxed">{{ prettyJson(props.log.data ?? {}) }}</pre>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Link :href="backUrl">
                    <Button variant="outline">Volver a lista</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
