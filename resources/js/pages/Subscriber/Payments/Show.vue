<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'

type Payment = {
    id: number
    method: string
    currency: string
    amount: string
    paid_at?: string | null
    reference?: string | null
    meta?: any
    created_at?: string | null
    updated_at?: string | null
    invoice?: {
        id: number
        number: string
        status: string
        currency: string
        total: string
    } | null
}

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string }
    payment: Payment
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Pagos', href: '/subscriber/payments' },
    { title: `Pago #${props.payment.id}`, href: `/subscriber/payments/${props.payment.id}` },
]

function norm(s: any) {
    return String(s ?? '').toLowerCase().trim()
}

function methodLabel(m: string) {
    const x = norm(m)
    if (x === 'card') return 'Tarjeta'
    if (x === 'bank_transfer') return 'Transferencia'
    if (x === 'cash') return 'Efectivo'
    if (x === 'check') return 'Cheque'
    if (x === 'other') return 'Otro'
    return m || '—'
}

function paidLabel() {
    return props.payment.paid_at ? 'Pagado' : 'No pagado'
}

function paidBadgeVariant() {
    return props.payment.paid_at ? 'secondary' : 'destructive'
}

function prettyJson(v: any) {
    try {
        return JSON.stringify(v ?? null, null, 2)
    } catch {
        return String(v ?? '')
    }
}
</script>

<template>

    <Head :title="`Pago #${props.payment.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <SectionCard :title="`Pago #${props.payment.id}`" description="Detalle del pago">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-muted-foreground">
                        <div>
                            Compañía:
                            <span class="font-medium text-foreground">{{ props.company.name }}</span>
                        </div>

                        <div class="mt-1">
                            Fecha pago:
                            <span class="font-medium text-foreground">{{ props.payment.paid_at ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Badge :variant="paidBadgeVariant()">
                            {{ paidLabel() }}
                        </Badge>

                        <Button size="sm" variant="outline" as-child>
                            <Link href="/subscriber/payments">Volver</Link>
                        </Button>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Método</div>
                        <div class="text-sm font-semibold">{{ methodLabel(props.payment.method) }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Monto</div>
                        <div class="text-sm font-semibold">
                            {{ props.payment.amount }} {{ props.payment.currency }}
                        </div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Referencia</div>
                        <div class="text-sm font-semibold">
                            {{ props.payment.reference ?? '—' }}
                        </div>
                    </div>
                </div>

                <!-- Links -->
                <div class="mt-5 flex flex-wrap gap-2">
                    <Button v-if="props.payment.invoice" size="sm" variant="outline" as-child>
                        <Link :href="`/subscriber/invoices/${props.payment.invoice.id}`">
                            Ver factura {{ props.payment.invoice.number }}
                        </Link>
                    </Button>
                </div>
            </SectionCard>

            <!-- Factura asociada (resumen) -->
            <SectionCard title="Factura asociada" description="Resumen de la factura relacionada">
                <div v-if="props.payment.invoice" class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Número</div>
                        <div class="text-sm font-semibold">{{ props.payment.invoice.number }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Estado</div>
                        <div class="text-sm font-semibold">{{ props.payment.invoice.status }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Total factura</div>
                        <div class="text-sm font-semibold">
                            {{ props.payment.invoice.total }} {{ props.payment.invoice.currency }}
                        </div>
                    </div>
                </div>

                <div v-else class="text-sm text-muted-foreground">
                    No hay factura asociada (esto no debería pasar si invoice_id es requerido).
                </div>
            </SectionCard>

            <!-- Meta -->
            <SectionCard title="Meta" description="Información adicional del pago (meta)">
                <div v-if="props.payment.meta" class="mt-2">
                    <pre class="text-xs rounded-lg border p-3 overflow-auto bg-background">{{ prettyJson(props.payment.meta) }}</pre>
                </div>
                <div v-else class="text-sm text-muted-foreground">
                    No hay meta.
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
