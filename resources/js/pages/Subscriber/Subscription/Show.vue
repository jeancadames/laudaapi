<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string }
    subscription: null | {
        id: number
        status: string
        billing_cycle: string
        currency: string
        trial_ends_at_human?: string | null
        period_start_human?: string | null
        period_end_human?: string | null
        starts_at_human?: string | null
        ends_at_human?: string | null
    }
    items: Array<any>
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Subscriber', href: subscriber().url },
    { title: 'Mi Suscripción', href: '/subscriber/subscription' },
]

function label(status: string) {
    const s = (status ?? '').toLowerCase()
    if (s === 'trialing') return 'Trial'
    if (s === 'active') return 'Activa'
    if (s === 'past_due') return 'Pago pendiente'
    if (s === 'cancelled') return 'Cancelada'
    if (s === 'expired') return 'Expirada'
    return status || '—'
}
</script>

<template>

    <Head title="Mi Suscripción" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <SectionCard title="Estado" description="Información de tu suscripción actual">
                <div v-if="!props.subscription" class="text-sm text-muted-foreground">
                    No tienes una suscripción registrada todavía.
                </div>

                <div v-else class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <div class="font-medium">Estado:</div>
                        <Badge variant="secondary">{{ label(props.subscription.status) }}</Badge>
                    </div>

                    <div>Billing cycle: <span class="font-medium">{{ props.subscription.billing_cycle }}</span></div>
                    <div>Moneda: <span class="font-medium">{{ props.subscription.currency }}</span></div>

                    <div v-if="props.subscription.trial_ends_at_human">
                        Trial ends: <span class="font-medium">{{ props.subscription.trial_ends_at_human }}</span>
                    </div>

                    <div v-if="props.subscription.period_start_human || props.subscription.period_end_human">
                        Periodo: <span class="font-medium">{{ props.subscription.period_start_human ?? '—' }}</span>
                        → <span class="font-medium">{{ props.subscription.period_end_human ?? '—' }}</span>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Items" description="Servicios incluidos en la suscripción (subscription_items)">
                <div v-if="props.items.length === 0" class="text-sm text-muted-foreground">
                    No hay items todavía.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="it in props.items" :key="it.id" class="rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold truncate">
                                    {{ it.service?.title ?? `Service #${it.service_id}` }}
                                </div>
                                <div v-if="it.service?.short_description" class="text-sm text-muted-foreground line-clamp-2 mt-1">
                                    {{ it.service.short_description }}
                                </div>
                                <div class="text-xs text-muted-foreground mt-2">
                                    Cantidad: {{ it.quantity ?? 1 }}
                                </div>
                            </div>
                            <Badge variant="secondary">{{ it.status }}</Badge>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
