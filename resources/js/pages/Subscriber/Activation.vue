<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import StatCard from '@/components/StatCard.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Button } from '@/components/ui/button'

const props = defineProps<{
    activation: any | null
    state: {
        has_activation_request: boolean
        has_subscriber: boolean
        has_company: boolean
        has_subscription: boolean
    }
    subscriber: any | null
    company: any | null
    subscription: any | null
}>()

const status = computed(() => String(props.activation?.status ?? ''))
const canActivate = computed(() => status.value === 'accepted')

const statusMessage = computed(() => {
    if (!props.activation) return 'No encontramos una solicitud de activación para tu usuario.'
    if (status.value === 'accepted') return 'Tu solicitud fue aceptada. Puedes iniciar el trial ahora.'
    if (status.value === 'trialing') return 'Tu trial ya está activo. No necesitas volver a activar.'
    if (status.value === 'converted') return 'Tu cuenta ya fue convertida. No necesitas volver a activar.'
    return `Este proceso no está disponible con el estado actual: ${status.value}`
})

const buttonText = computed(() => {
    if (!props.activation) return 'No disponible'
    if (canActivate.value) return 'Activar / Continuar'
    if ([ 'trialing', 'converted' ].includes(status.value)) return 'Ya activado'
    return 'No disponible'
})

function activate() {
    if (!canActivate.value) return
    router.post('/subscriber/activation/activate', {}, { preserveScroll: true })
}
</script>

<template>

    <Head title="Solicitud de Activación" />

    <AppLayout :breadcrumbs="[
        { title: 'Subscriber', href: '/subscriber' },
        { title: 'Solicitud de Activación', href: '/subscriber/activation' },
    ]">
        <div class="flex flex-col gap-4 p-4">
            <div class="grid gap-6 md:grid-cols-4">
                <StatCard title="Solicitud" :value="props.state.has_activation_request ? 'Creada' : 'No existe'" description="activation_requests" :trend-positive="props.state.has_activation_request" />
                <StatCard title="Subscriber" :value="props.state.has_subscriber ? 'OK' : 'Pendiente'" description="subscribers + subscriber_user" :trend-positive="props.state.has_subscriber" />
                <StatCard title="Empresa" :value="props.state.has_company ? 'OK' : 'Pendiente'" description="companies" :trend-positive="props.state.has_company" />
                <StatCard title="Suscripción" :value="props.subscription?.status ?? '—'" description="subscriptions" :trend-positive="(props.subscription?.status ?? '') === 'active'" />
            </div>

            <SectionCard title="Estado de activación" description="Este paso crea subscriber, empresa y suscripción trialing">
                <div class="text-sm text-muted-foreground">
                    {{ statusMessage }}
                </div>

                <div v-if="props.activation" class="mt-4 grid gap-6 md:grid-cols-3">
                    <StatCard title="Status" :value="props.activation.status ?? '—'" description="activation_requests.status" />
                    <StatCard title="Trial termina" :value="props.activation.trial_ends_at_human ?? '—'" description="trial_ends_at" :trend-positive="false" />
                    <StatCard title="Días restantes" :value="props.activation.trial_days_left ?? 0" description="aprox." />
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button size="sm" :disabled="!canActivate" @click="activate">
                        {{ buttonText }}
                    </Button>
                </div>
            </SectionCard>

            <SectionCard title="Resultado" description="Datos creados/vinculados">
                <div class="grid gap-6 md:grid-cols-3">
                    <StatCard title="Subscriber" :value="props.subscriber?.name ?? '—'" description="subscribers.name" />
                    <StatCard title="Empresa" :value="props.company?.name ?? '—'" description="companies.name" />
                    <StatCard title="Moneda" :value="props.company?.currency ?? props.subscriber?.currency ?? 'USD'" description="currency" />
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
