<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import contacts from '@/routes/admin/contacts'
import { type BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

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

const props = defineProps<{ contact: ContactRequest }>()

const page = usePage()

/**
 * Back URL preservando filtros/página:
 * - Index genera /admin/contacts/:id?search=&status=&page=
 * - Aquí reconstruimos /admin/contacts?search=&status=&page=
 */
const backUrl = computed(() => {
    const q = page.url.split('?')[ 1 ]
    const base = contacts.index().url
    return q ? `${base}?${q}` : base
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Solicitudes de Contacto', href: backUrl.value },
    { title: props.contact.name, href: page.url },
])

const isLoading = ref(false)
const isMarking = ref(false)

function safeTel(phone: string) {
    return phone.trim().replace(/[^\d+]/g, '')
}

function markAsRead() {
    if (isMarking.value) return
    isMarking.value = true

    router.post(`/admin/contacts/${props.contact.id}/read`, {}, {
        preserveScroll: true,
        preserveState: true,
        // Como el backend hace back(), Inertia recarga el show; el badge se actualiza sin reload manual.
        onFinish: () => {
            isMarking.value = false
        },
    })
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

    <Head :title="`Contacto: ${props.contact.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Detalle de Solicitud</h1>
                    <div v-if="isLoading" class="text-sm text-muted-foreground mt-1">Cargando...</div>
                </div>

                <Button variant="outline" @click="router.visit(backUrl, { preserveScroll: true })">
                    Volver
                </Button>
            </div>

            <Card class="border border-border/40 rounded-xl">
                <CardHeader>
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <CardTitle class="text-xl font-semibold truncate">
                                {{ props.contact.name }}
                            </CardTitle>

                            <CardDescription class="text-sm mt-1 truncate">
                                <a class="underline underline-offset-2" :href="`mailto:${props.contact.email}`">
                                    {{ props.contact.email }}
                                </a>
                            </CardDescription>
                        </div>

                        <Badge :variant="props.contact.read_at ? 'secondary' : 'default'" class="capitalize">
                            {{ props.contact.read_at ? 'Leído' : 'Nuevo' }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-5 text-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <span class="font-medium text-muted-foreground">Teléfono:</span>
                            <template v-if="props.contact.phone">
                                <a class="ml-1 underline underline-offset-2" :href="`tel:${safeTel(props.contact.phone)}`">
                                    {{ props.contact.phone }}
                                </a>
                            </template>
                            <span v-else class="ml-1">—</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Empresa:</span>
                            <span class="ml-1">{{ props.contact.company ?? '—' }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Tema:</span>
                            <span class="ml-1">{{ props.contact.topic ?? '—' }}</span>
                        </div>

                        <div>
                            <span class="font-medium text-muted-foreground">Aceptó términos:</span>
                            <span class="ml-1">{{ props.contact.terms ? 'Sí' : 'No' }}</span>
                        </div>
                    </div>

                    <!-- Message -->
                    <div>
                        <h3 class="font-semibold text-base mb-2">Mensaje</h3>
                        <div class="rounded-lg border border-border/40 bg-muted/20 p-4 text-sm leading-relaxed whitespace-pre-wrap">
                            {{ props.contact.message ?? 'Sin mensaje' }}
                        </div>
                    </div>

                    <!-- Metadata -->
                    <div v-if="props.contact.metadata">
                        <h3 class="font-semibold text-base mb-2">Metadata</h3>
                        <div class="rounded-lg border border-border/40 bg-muted/20 p-4 text-sm space-y-1">
                            <div v-for="(value, key) in props.contact.metadata" :key="String(key)" class="wrap-break-word">
                                <span class="font-medium text-muted-foreground capitalize">{{ String(key) }}:</span>
                                <span class="ml-1">{{ value }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-2 flex justify-end gap-3">
                        <Button v-if="!props.contact.read_at" variant="default" :disabled="isMarking" @click="markAsRead">
                            {{ isMarking ? 'Marcando...' : 'Marcar como leído' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
