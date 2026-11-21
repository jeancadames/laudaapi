<script setup lang="ts">
import type { BusinessTransitionPlan } from '@/types/businessTransition';

defineProps<{ plan: BusinessTransitionPlan; executedAt?: string | null }>();
const date = (value: string) =>
    new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(new Date(value));
const money = (cents: number, currency: string) =>
    new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
    }).format(cents / 100);
</script>

<template>
    <section
        class="min-w-0 space-y-4 border-y py-5"
        aria-label="Transición de servicios"
    >
        <div class="space-y-2">
            <h2 class="text-lg font-semibold">
                {{
                    executedAt
                        ? 'Transición aplicada'
                        : 'Transición al vencer lo pagado'
                }}
            </h2>
            <p v-if="executedAt" class="text-sm leading-6">
                Business se activó el
                <strong>{{ date(executedAt) }} UTC</strong>. Se respetó el corte
                mínimo aceptado: {{ date(plan.not_before) }} UTC. Estos son los
                períodos y pagos de origen conservados en el historial.
            </p>
            <p v-else class="text-sm leading-6">
                Business podrá activarse desde
                <strong>{{ date(plan.not_before) }} UTC</strong>, cuando hayan
                terminado todos los períodos reconocidos. El período Business
                comenzará al confirmar su activación, con su factura pagada y la
                implementación conforme.
            </p>
        </div>
        <ul class="divide-y border-y">
            <li
                v-for="(period, index) in plan.periods"
                :key="`${period.invoice_id}-${period.subscription_item_id}-${index}`"
                class="space-y-1 py-4 text-sm leading-6"
            >
                <p class="font-medium">
                    {{ period.name }} · Suscripción #{{
                        period.subscription_id
                    }}
                </p>
                <p>
                    {{ date(period.starts_at) }} →
                    {{ date(period.ends_at) }} UTC
                </p>
                <p class="break-words text-muted-foreground">
                    Factura {{ period.invoice_number }} · Total pagado de esta
                    factura: {{ money(period.paid_cents, plan.currency) }}.
                    <span v-if="period.basis === 'standalone_calendar'"
                        >Vencimiento según el calendario original de activación
                        individual.</span
                    >
                </p>
            </li>
        </ul>
        <p v-if="plan.target_subscription_id" class="text-sm leading-6">
            {{ executedAt ? 'Se conservó' : 'Se conservará' }} la suscripción
            <strong>#{{ plan.target_subscription_id }}</strong> para Business.
            Las demás suscripciones de origen
            {{ executedAt ? 'se cerraron' : 'se cerrarán al activar' }}; las
            facturas, pagos y referencias anteriores se conservan en el
            historial.
        </p>
        <p class="text-sm leading-6">
            Crédito: {{ money(plan.credit_cents, plan.currency) }} · Devolución:
            {{ money(plan.refund_cents, plan.currency) }} · Prorrateo:
            {{ money(plan.proration_cents, plan.currency) }}. Se reconoce lo
            pagado mediante su período completo, sin descuento adicional sobre
            Business.
        </p>
        <p v-if="!executedAt" class="text-sm leading-6 text-muted-foreground">
            Los servicios que vencen antes conservan sus condiciones anteriores;
            esta propuesta no extiende su cobertura ni garantiza continuidad
            hasta el corte. No hay activación automática ni cobertura Business
            retroactiva. Si cambian los servicios o pagos de origen, habrá que
            revisar y aceptar una nueva propuesta.
        </p>
    </section>
</template>
