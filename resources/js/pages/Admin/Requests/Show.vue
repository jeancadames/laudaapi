<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import requests from '@/routes/admin/requests'
import { type BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type Status =
    | 'pending'
    | 'accepted'
    | 'trialing'
    | 'expired'
    | 'converted'
    | 'discarded'

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

const props = defineProps<{
    request: ActivationRequest
}>()

const page = usePage()

const backUrl = computed(() => {
    const q = page.url.split('?')[ 1 ]
    const base = requests.index().url
    return q ? `${base}?${q}` : base
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Solicitudes de Activación', href: backUrl.value },
    { title: props.request.name, href: page.url },
])

const isLoading = ref(false)

function statusLabel(s: Status) {
    const map: Record<Status, string> = {
        pending: 'Pendiente',
        accepted: 'Aceptada',
        trialing: 'En trial',
        expired: 'Expirado',
        converted: 'Convertido',
        discarded: 'Descartado',
    }
    return map[ s ]
}

function statusBadgeVariant(s: Status) {
    if (s === 'pending') return 'default'
    if (s === 'accepted') return 'secondary'
    if (s === 'discarded' || s === 'expired') return 'destructive'
    return 'secondary'
}

function safeTel(phone: string) {
    return phone.trim().replace(/[^\d+]/g, '')
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

function activationEmailSentAt() {
    return metaString(props.request.metadata, 'activation_email_sent_at')
}

function activationEmailExpiresAt() {
    return metaString(props.request.metadata, 'activation_email_expires_at')
}

function activationEmailSendCount() {
    return metaNumber(props.request.metadata, 'activation_email_send_count') ?? 0
}

const isActivationLinkExpired = computed(() => {
    const expires = activationEmailExpiresAt()
    if (!expires) return false
    const t = new Date(expires).getTime()
    if (Number.isNaN(t)) return false
    return t < Date.now()
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

    <Head :title="`Solicitud: ${props.request.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Detalle de Solicitud de Activación</h1>
                    <div v-if="isLoading" class="text-sm text-muted-foreground mt-1">Cargando...</div>
                </div>

                <Button variant="outline" @click="router.visit(backUrl, { preserveScroll: true })">
                    Volver
                </Button>
            </div>

            <Card class="border border-border/40 rounded-xl">
                <CardHeader>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div class="min-w-0">
                            <CardTitle class="text-xl font-semibold truncate">{{ props.request.name }}</CardTitle>

                            <CardDescription class="text-sm mt-1 truncate">
                                {{ props.request.company }} •
                                <a class="underline underline-offset-2" :href="`mailto:${props.request.email}`">
                                    {{ props.request.email }}
                                </a>
                            </CardDescription>
                        </div>

                        <div class="flex sm:justify-end">
                            <div class="flex flex-col items-start sm:items-end gap-1">
                                <Badge :variant="statusBadgeVariant(props.request.status)" class="capitalize whitespace-nowrap">
                                    {{ statusLabel(props.request.status) }}
                                </Badge>

                                <!-- ✅ Solo marca expirado si sigue pending -->
                                <Badge v-if="props.request.status === 'pending' && isActivationLinkExpired" variant="destructive" class="whitespace-nowrap">
                                    Link expirado
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-5 text-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <span class="font-medium text-muted-foreground">Teléfono:</span>
                            <template v-if="props.request.phone">
                                <a class="ml-1 underline underline-offset-2" :href="`tel:${safeTel(props.request.phone)}`">
                                    {{ props.request.phone }}
                                </a>
                            </template>
                            <span v-else class="ml-1">—</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Rol:</span>
                            <span class="ml-1">{{ props.request.role ?? '—' }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Tema:</span>
                            <span class="ml-1">{{ props.request.topic }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Otro tema:</span>
                            <span class="ml-1">{{ props.request.other_topic ?? '—' }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Sistema:</span>
                            <span class="ml-1">{{ props.request.system ?? '—' }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Volumen:</span>
                            <span class="ml-1">{{ props.request.volume ?? '—' }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Aceptó términos:</span>
                            <span class="ml-1">{{ props.request.terms ? 'Sí' : 'No' }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Trial days:</span>
                            <span class="ml-1">{{ props.request.trial_days }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Trial inicia:</span>
                            <span class="ml-1">{{ props.request.trial_starts_at ?? '—' }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Trial termina:</span>
                            <span class="ml-1">{{ props.request.trial_ends_at ?? '—' }}</span>
                        </div>
                    </div>

                    <!-- ✅ Correo de activación (metadata normalizada) -->
                    <div class="rounded-lg border border-border/40 bg-muted/20 p-4 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-semibold text-base">Correo de activación</h3>
                            <Badge variant="secondary" class="whitespace-nowrap">
                                Reenvíos: {{ activationEmailSendCount() }}
                            </Badge>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <span class="font-medium text-muted-foreground">Enviado:</span>
                                <span class="ml-1">{{ formatDateTime(activationEmailSentAt()) }}</span>
                            </div>

                            <div>
                                <span class="font-medium text-muted-foreground">Expira:</span>
                                <span class="ml-1">{{ formatDateTime(activationEmailExpiresAt()) }}</span>
                            </div>
                        </div>

                        <div v-if="props.request.status === 'pending' && isActivationLinkExpired" class="text-xs text-destructive">
                            El enlace expiró. Puedes reenviar un recordatorio para generar uno nuevo.
                        </div>
                    </div>

                    <!-- Message -->
                    <div>
                        <h3 class="font-semibold text-base mb-2">Mensaje</h3>
                        <div class="rounded-lg border border-border/40 bg-muted/20 p-4 text-sm leading-relaxed whitespace-pre-wrap">
                            {{ props.request.message ?? 'Sin mensaje' }}
                        </div>
                    </div>

                    <!-- Metadata (debug) -->
                    <div v-if="props.request.metadata">
                        <h3 class="font-semibold text-base mb-2">Metadata</h3>
                        <div class="rounded-lg border border-border/40 bg-muted/20 p-4 text-sm space-y-1">
                            <div v-for="(value, key) in props.request.metadata" :key="String(key)" class="wrap-break-word">
                                <span class="font-medium text-muted-foreground capitalize">{{ String(key) }}:</span>
                                <span class="ml-1">{{ value }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
