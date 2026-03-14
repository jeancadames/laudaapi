<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'
import { useDateFormatter } from '@/composables/useDateFormatter'

const { formatDate, formatDateTime } = useDateFormatter()

type Invoice = {
    id: number
    number: string
    status: string

    issued_on?: string | null
    due_on?: string | null
    period_start?: string | null
    period_end?: string | null

    currency: string
    subtotal: string
    discount_total: string
    tax_total: string
    total: string
    amount_paid: string
    balance: string

    billing_snapshot?: any
    document_class?: string | null
    document_type?: string | null
    fiscal_number?: string | null
    security_code?: string | null
    fiscal_meta?: any

    provider?: string | null
    provider_invoice_id?: string | null
    hosted_invoice_url?: string | null
    payment_url?: string | null

    created_at?: string | null
    updated_at?: string | null
}

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string }
    invoice: Invoice
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Facturas', href: '/subscriber/invoices' },
    { title: `Factura ${props.invoice.number}`, href: `/subscriber/invoices/${props.invoice.id}` },
]

function normStatus(s: any) {
    return String(s ?? '').toLowerCase().trim()
}

function badgeVariantByInvoiceStatus(st: string) {
    const s = normStatus(st)
    if (s === 'paid') return 'secondary'
    if (s === 'issued') return 'secondary'
    if (s === 'draft') return 'secondary'
    if (s === 'overdue') return 'destructive'
    if (s === 'void') return 'destructive'
    return 'secondary'
}

function statusLabel(st: string) {
    const s = normStatus(st)
    if (s === 'draft') return 'Borrador'
    if (s === 'issued') return 'Emitida'
    if (s === 'paid') return 'Pagada'
    if (s === 'void') return 'Anulada'
    if (s === 'overdue') return 'Vencida'
    return st || '—'
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

    <Head :title="`Factura ${props.invoice.number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <SectionCard :title="`Factura ${props.invoice.number}`" description="Detalle de la factura">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-muted-foreground">
                        <div>
                            Compañía:
                            <span class="font-medium text-foreground">{{ props.company.name }}</span>
                        </div>

                        <div class="mt-1">
                            Emitida: <span class="text-foreground font-medium">{{ props.invoice.issued_on ?? '—' }}</span>
                            · Vence: <span class="text-foreground font-medium">{{ props.invoice.due_on ?? '—' }}</span>
                        </div>

                        <div v-if="props.invoice.period_start || props.invoice.period_end" class="mt-1">
                            Periodo:
                            <span class="font-medium text-foreground">{{ props.invoice.period_start ?? '—' }}</span>
                            →
                            <span class="font-medium text-foreground">{{ props.invoice.period_end ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Badge :variant="badgeVariantByInvoiceStatus(props.invoice.status)">
                            {{ statusLabel(props.invoice.status) }}
                        </Badge>

                        <Button size="sm" variant="outline" as-child>
                            <Link href="/subscriber/invoices">Volver</Link>
                        </Button>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-4">
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Subtotal</div>
                        <div class="text-sm font-semibold">{{ props.invoice.subtotal }} {{ props.invoice.currency }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Descuento</div>
                        <div class="text-sm font-semibold">{{ props.invoice.discount_total }} {{ props.invoice.currency }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Impuestos</div>
                        <div class="text-sm font-semibold">{{ props.invoice.tax_total }} {{ props.invoice.currency }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Total</div>
                        <div class="text-sm font-semibold">{{ props.invoice.total }} {{ props.invoice.currency }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Pagado</div>
                        <div class="text-sm font-semibold">{{ props.invoice.amount_paid }} {{ props.invoice.currency }}</div>
                    </div>

                    <div class="rounded-lg border p-3 md:col-span-3">
                        <div class="text-xs text-muted-foreground">Balance</div>
                        <div class="text-sm font-semibold">{{ props.invoice.balance }} {{ props.invoice.currency }}</div>
                    </div>
                </div>

                <!-- Acciones externas (si existen URLs) -->
                <div v-if="props.invoice.hosted_invoice_url || props.invoice.payment_url" class="mt-5 flex flex-wrap gap-2">
                    <Button v-if="props.invoice.hosted_invoice_url" size="sm" variant="outline" as-child>
                        <a :href="props.invoice.hosted_invoice_url" target="_blank" rel="noopener">Abrir en proveedor</a>
                    </Button>

                    <!-- Pago lo dejas para después, pero si ya tienes payment_url puedes mostrarlo -->
                    <Button v-if="props.invoice.payment_url" size="sm" class="bg-emerald-600 text-white hover:bg-emerald-700" as-child>
                        <a :href="props.invoice.payment_url" target="_blank" rel="noopener">Pagar</a>
                    </Button>
                </div>
            </SectionCard>

            <!-- DGII -->
            <SectionCard title="DGII" description="Datos fiscales (si aplica)">
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Clase</div>
                        <div class="text-sm font-semibold">{{ props.invoice.document_class ?? '—' }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Tipo</div>
                        <div class="text-sm font-semibold">{{ props.invoice.document_type ?? '—' }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">NCF / ECF</div>
                        <div class="text-sm font-semibold">{{ props.invoice.fiscal_number ?? '—' }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Security Code</div>
                        <div class="text-sm font-semibold">{{ props.invoice.security_code ?? '—' }}</div>
                    </div>
                </div>

                <div v-if="props.invoice.fiscal_meta" class="mt-4">
                    <div class="text-xs text-muted-foreground mb-2">Fiscal meta</div>
                    <pre class="text-xs rounded-lg border p-3 overflow-auto bg-background">{{ prettyJson(props.invoice.fiscal_meta) }}</pre>
                </div>
            </SectionCard>

            <!-- Snapshot -->
            <SectionCard title="Snapshot" description="Estado calculado / snapshot de facturación (billing_snapshot)">
                <div v-if="props.invoice.billing_snapshot" class="mt-2">
                    <pre class="text-xs rounded-lg border p-3 overflow-auto bg-background">{{ prettyJson(props.invoice.billing_snapshot) }}</pre>
                </div>
                <div v-else class="text-sm text-muted-foreground">
                    No hay billing_snapshot.
                </div>
            </SectionCard>

            <!-- Provider -->
            <SectionCard title="Proveedor de pago" description="Datos del gateway (si aplica)">
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Provider</div>
                        <div class="text-sm font-semibold">{{ props.invoice.provider ?? '—' }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-muted-foreground">Provider invoice id</div>
                        <div class="text-sm font-semibold">{{ props.invoice.provider_invoice_id ?? '—' }}</div>
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
