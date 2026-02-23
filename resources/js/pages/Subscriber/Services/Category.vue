<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { computed, ref, watch } from 'vue'
import { subscriber } from '@/routes'
import services from '@/routes/subscriber/services'
import { useToast } from '@/components/ui/toast/use-toast'
import { Badge } from '@/components/ui/badge'
import { BadgeCheckIcon } from 'lucide-vue-next'

const { toast } = useToast()
const page = usePage()

const DEFAULT_CATEGORY = 'api-facturacion-electronica'

const props = defineProps<{
    company: null | { id: number; name: string; currency: string; timezone: string }
    activation_request: null | { id: number; status: string }
    subscription_status: string | null
    can_select_services: boolean

    category: {
        id: number
        title: string
        slug: string
        short_description?: string | null
        description?: string | null
        icon?: string | null
    }

    services: Array<{
        id: number
        title: string
        slug: string
        short_description?: string | null
        description?: string | null
        badge?: string | null

        type: string
        billable: boolean
        billing_model: string
        currency: string
        monthly_price: string | number | null
        yearly_price: string | number | null

        requested: boolean
        request_status?: string | null
        active: boolean
    }>
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Servicios', href: services.category(DEFAULT_CATEGORY).url },
    { title: props.category.title, href: services.category(props.category.slug).url },
]

const canSelect = computed(() => !!props.can_select_services)

const flashError = computed(() => (page.props.flash as any)?.error ?? null)
const flashSuccess = computed(() => (page.props.flash as any)?.success ?? null)

let lastFlashKey = ''
watch(
    () => [ flashError.value, flashSuccess.value ],
    ([ err, ok ]) => {
        const key = `${err ?? ''}||${ok ?? ''}`
        if (!key.trim() || key === lastFlashKey) return
        lastFlashKey = key

        if (err) toast({ title: 'Acción no permitida', description: err, variant: 'destructive' })
        else if (ok) toast({ title: 'Listo', description: ok })
    },
    { immediate: true }
)

function toggleService(serviceId: number) {
    if (!canSelect.value) {
        toast({
            title: 'Requiere suscripción activa',
            description: `Tu estado actual es: ${props.subscription_status ?? '—'}`,
            variant: 'destructive',
        })
        return
    }

    if (!props.activation_request) {
        toast({
            title: 'Requiere solicitud de activación',
            description: 'Debes crear una solicitud antes de solicitar servicios.',
            variant: 'destructive',
        })
        return
    }

    router.post('/subscriber/services/request', { service_id: serviceId }, { preserveScroll: true })
}

function priceLabel(s: any) {
    if (!s.billable) return 'Incluido'
    const m = s.monthly_price ? `${s.monthly_price} ${s.currency}/mes` : null
    const y = s.yearly_price ? `${s.yearly_price} ${s.currency}/año` : null
    return [ m, y ].filter(Boolean).join(' • ') || '—'
}

/**
 * ✅ NORMALIZACIÓN para catálogo:
 * - cancelled => se considera "Disponible" (no lo pintes rojo)
 * - active => Activo
 * - pending/pending_payment => solicitado/pendiente
 */
function statusLabel(s: any) {
    if (s.active) return 'Activo'

    const st = String(s.request_status ?? '').toLowerCase()

    // ✅ si está cancelled, para el catálogo es como "no solicitado"
    if (st === 'cancelled') return 'Disponible'

    if (!s.requested) return 'Disponible'
    if (st === 'pending_payment') return 'Pendiente de pago'
    if (st === 'pending') return 'Solicitado'

    // fallback
    return st || 'Solicitado'
}

const headerDescription = computed(() => {
    return props.category.short_description?.trim()
        || props.category.description?.trim()
        || 'Servicios disponibles en esta categoría'
})

function isBlueStatus(s: any) {
    const st = statusLabel(s)
    // ✅ Disponible y Activo son azules; y cancelled ahora cae en "Disponible"
    return st === 'Disponible' || st === 'Activo'
}

function badgeVariant(s: any): 'secondary' | 'destructive' {
    return isBlueStatus(s) ? 'secondary' : 'destructive'
}

function badgeBlueClass(s: any) {
    return isBlueStatus(s) ? 'bg-blue-500 text-white dark:bg-blue-600' : ''
}

function showBadgeIcon(s: any) {
    return isBlueStatus(s)
}

/**
 * ✅ Regla de UI para el botón:
 * - active => deshabilitado y dice "Activo"
 * - si no puede seleccionar => deshabilitado
 * - si request_status es cancelled => debe permitir "Solicitar" (no "Quitar")
 */
function isCancelledRequest(s: any) {
    return String(s.request_status ?? '').toLowerCase() === 'cancelled'
}

function canToggle(s: any) {
    if (!props.activation_request) return false
    if (!canSelect.value) return false
    if (s.active) return false
    return true
}

function buttonLabel(s: any) {
    if (s.active) return 'Activo'
    if (!canSelect.value) return 'Requiere suscripción'

    // ✅ cancelled => vuelve a ser "Solicitar"
    if (isCancelledRequest(s)) return 'Solicitar'

    // requested (no cancelled) => Quitar
    if (s.requested) return 'Quitar'

    return 'Solicitar'
}

// búsqueda opcional
const q = ref('')
const filtered = computed(() => {
    const term = q.value.trim().toLowerCase()
    if (!term) return props.services
    return props.services.filter((s) => {
        const t = (s.title ?? '').toLowerCase()
        const slug = (s.slug ?? '').toLowerCase()
        const sd = (s.short_description ?? '').toLowerCase()
        const d = (s.description ?? '').toLowerCase()
        return t.includes(term) || slug.includes(term) || sd.includes(term) || d.includes(term)
    })
})
</script>

<template>

    <Head :title="props.category.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <SectionCard :title="props.category.title" :description="headerDescription">
                <div class="text-sm text-muted-foreground mb-4 space-y-2">
                    <div v-if="props.activation_request">
                        Solicitud:
                        <span class="font-medium text-foreground">#{{ props.activation_request.id }}</span>
                        • Estado: {{ props.activation_request.status }}
                    </div>
                    <div v-else class="text-rose-600 dark:text-rose-400">
                        No tienes solicitud de activación. Debes crearla antes de solicitar servicios.
                    </div>

                    <div v-if="props.activation_request && !canSelect" class="text-amber-600 dark:text-amber-400">
                        Requiere una <span class="font-medium">suscripción activa</span> para seleccionar servicios.
                        <span v-if="props.subscription_status" class="text-muted-foreground">
                            (Estado actual: {{ props.subscription_status }})
                        </span>
                    </div>
                </div>

                <div class="mb-4">
                    <input v-model="q" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Buscar por título, slug o descripción..." />
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="s in filtered" :key="s.id" class="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold truncate">{{ s.title }}</div>

                                <div v-if="s.short_description" class="mt-1 text-sm text-muted-foreground line-clamp-2">
                                    {{ s.short_description }}
                                </div>
                                <div v-else-if="s.description" class="mt-1 text-sm text-muted-foreground line-clamp-2">
                                    {{ s.description }}
                                </div>

                                <div class="mt-2 text-xs text-muted-foreground">
                                    {{ priceLabel(s) }} • {{ s.billing_model }}
                                </div>
                            </div>

                            <Badge :variant="badgeVariant(s)" class="shrink-0 gap-1" :class="badgeBlueClass(s)">
                                <BadgeCheckIcon v-if="showBadgeIcon(s)" class="h-3.5 w-3.5" />
                                {{ statusLabel(s) }}
                            </Badge>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <Button size="sm" variant="outline" :disabled="!canToggle(s)" @click="toggleService(s.id)">
                                {{ buttonLabel(s) }}
                            </Button>

                            <div v-if="s.badge" class="text-xs text-muted-foreground">{{ s.badge }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="filtered.length === 0" class="text-sm text-muted-foreground">
                    No hay servicios que coincidan con tu búsqueda.
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
