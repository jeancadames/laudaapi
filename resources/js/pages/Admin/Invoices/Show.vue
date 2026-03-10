<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import invoices from '@/routes/admin/invoices'
import payments from '@/routes/admin/payments'
import type { BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type InvoiceStatus = 'draft' | 'issued' | 'paid' | 'void' | 'overdue'

type InvoiceItemRow = {
    id: number
    description: string
    quantity: number
    unit_price: string | number
    line_total: string | number
    service?: { id: number; title: string; slug: string } | null
}

type PaymentRow = {
    id: number
    method: string
    currency: string
    amount: string | number
    paid_at: string | null
    reference: string | null
}

type CompanyRow = { id: number; name: string }

type InvoicePayload = {
    id: number
    number: string
    status: InvoiceStatus
    currency: string

    issued_on: string | null
    due_on: string | null

    subtotal: string | number
    discount_total: string | number
    tax_total: string | number
    total: string | number
    amount_paid: string | number

    document_class: string | null
    document_type: string | null
    fiscal_number: string | null
    security_code: string | null

    provider: string | null
    provider_invoice_id: string | null
    hosted_invoice_url: string | null
    payment_url: string | null

    company: CompanyRow | null
    items: InvoiceItemRow[]
    payments: PaymentRow[]

    created_at: string | null
    updated_at: string | null
}

const props = defineProps<{
    invoice: InvoicePayload
    back: { href: string }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Facturas', href: invoices.index().url },
    {
        title: props.invoice?.number ?? 'Factura',
        // si no hay invoice aún, puedes dejar href undefined
        href: props.invoice?.id ? invoices.show({ invoice: props.invoice.id }).url : undefined,
    },
]

function money(v: any) {
    const n = Number(v ?? 0)
    if (!Number.isFinite(n)) return '0.00'
    return new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

function moneyWithCurrency(v: any) {
    const cur = props.invoice?.currency ?? 'DOP'
    return `${money(v)} ${cur}`
}

function fmtDate(v?: string | null) {
    if (!v) return '—'
    const d = new Date(v)
    if (Number.isNaN(d.getTime())) return v
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium' }).format(d)
}

function fmtDateTime(v?: string | null) {
    if (!v) return '—'
    const d = new Date(v)
    if (Number.isNaN(d.getTime())) return v
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short' }).format(d)
}

const balance = computed(() => {
    const total = Number(props.invoice?.total ?? 0)
    const paid = Number(props.invoice?.amount_paid ?? 0)
    const b = total - paid
    return Number.isFinite(b) ? Math.max(0, b) : 0
})

const isOverdue = computed(() => {
    if (props.invoice?.status === 'overdue') return true
    if (!props.invoice?.due_on) return false
    if (props.invoice?.status === 'paid' || props.invoice?.status === 'void' || props.invoice?.status === 'draft') return false
    const due = new Date(props.invoice.due_on + 'T00:00:00')
    const now = new Date()
    return now.getTime() > due.getTime()
})

function statusVariant(st: InvoiceStatus) {
    if (st === 'paid') return 'default'
    if (st === 'overdue') return 'secondary'
    if (st === 'void') return 'secondary'
    if (st === 'draft') return 'outline'
    return 'default' // issued
}

const headerSubtitle = computed(() => {
    const company = props.invoice?.company?.name ?? '—'
    const issued = fmtDate(props.invoice?.issued_on)
    const due = fmtDate(props.invoice?.due_on)
    return `Cliente: ${company} • Emitida: ${issued} • Vence: ${due}`
})

function goPayments() {
    const n = props.invoice?.number
    router.visit(
        payments.index(n ? { query: { search: n } } : undefined).url
    )
}

/** ✅ TS-safe: no usar window en template */
function openExternal(url?: string | null) {
    if (!url) return
    // window existe en runtime; este check evita SSR/TS edge cases
    if (typeof window !== 'undefined') window.open(url, '_blank', 'noopener,noreferrer')
}
</script>

<template>

    <Head :title="`Factura • ${props.invoice.number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1 min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight leading-tight truncate">
                        Factura {{ props.invoice.number }}
                    </h1>

                    <div class="text-sm text-muted-foreground">
                        {{ headerSubtitle }}
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2">
                        <Badge :variant="statusVariant(props.invoice.status)" class="capitalize">
                            {{ props.invoice.status }}
                        </Badge>

                        <Badge v-if="isOverdue && props.invoice.status !== 'paid' && props.invoice.status !== 'void'" variant="secondary">
                            Vencida
                        </Badge>

                        <Badge v-if="props.invoice.document_class" variant="outline">
                            {{ props.invoice.document_class }}{{ props.invoice.document_type ? `-${props.invoice.document_type}` : '' }}
                        </Badge>

                        <Badge v-if="props.invoice.fiscal_number" variant="outline">
                            Fiscal: {{ props.invoice.fiscal_number }}
                        </Badge>

                        <Badge v-if="props.invoice.provider" variant="outline" class="capitalize">
                            {{ props.invoice.provider }}
                        </Badge>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                    <Link :href="props.back?.href ?? '/admin/invoices'">
                        <Button size="sm" variant="outline">Volver</Button>
                    </Link>

                    <Button size="sm" variant="outline" @click="router.visit('/admin/invoices')">
                        Ver todas
                    </Button>

                    <Button size="sm" variant="outline" @click="goPayments()">
                        Ver pagos
                    </Button>

                    <Button v-if="props.invoice.hosted_invoice_url" size="sm" variant="outline" @click="openExternal(props.invoice.hosted_invoice_url)">
                        Hosted invoice
                    </Button>

                    <Button v-if="props.invoice.payment_url" size="sm" variant="outline" @click="openExternal(props.invoice.payment_url)">
                        Payment link
                    </Button>
                </div>
            </div>

            <!-- Summary -->
            <div class="grid gap-6 lg:grid-cols-3">
                <Card class="min-w-0 border border-border/40 rounded-xl">
                    <CardHeader>
                        <CardTitle class="text-base">Totales</CardTitle>
                        <CardDescription class="text-xs">Monto, pagado y balance</CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-mono">{{ moneyWithCurrency(props.invoice.subtotal) }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Descuento</span>
                            <span class="font-mono">{{ moneyWithCurrency(props.invoice.discount_total) }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Impuesto</span>
                            <span class="font-mono">{{ moneyWithCurrency(props.invoice.tax_total) }}</span>
                        </div>

                        <div class="h-px bg-border/60 my-1" />

                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Total</span>
                            <span class="font-mono font-semibold">{{ moneyWithCurrency(props.invoice.total) }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Pagado</span>
                            <span class="font-mono">{{ moneyWithCurrency(props.invoice.amount_paid) }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Balance</span>
                            <span class="font-mono" :class="balance > 0 ? 'text-red-600' : ''">
                                {{ moneyWithCurrency(balance) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="min-w-0 border border-border/40 rounded-xl">
                    <CardHeader>
                        <CardTitle class="text-base">Cliente</CardTitle>
                        <CardDescription class="text-xs">Datos principales</CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Nombre</span>
                            <span class="truncate">{{ props.invoice.company?.name ?? '—' }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Invoice ID</span>
                            <span class="font-mono">{{ props.invoice.id }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Emitida</span>
                            <span class="font-mono">{{ fmtDate(props.invoice.issued_on) }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Vence</span>
                            <span class="font-mono">{{ fmtDate(props.invoice.due_on) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="min-w-0 border border-border/40 rounded-xl">
                    <CardHeader>
                        <CardTitle class="text-base">Metadatos</CardTitle>
                        <CardDescription class="text-xs">Fiscal / gateway</CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Clase</span>
                            <span class="font-mono">{{ props.invoice.document_class ?? '—' }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Tipo</span>
                            <span class="font-mono">{{ props.invoice.document_type ?? '—' }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Fiscal #</span>
                            <span class="font-mono truncate">{{ props.invoice.fiscal_number ?? '—' }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Provider invoice</span>
                            <span class="font-mono truncate">{{ props.invoice.provider_invoice_id ?? '—' }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Actualizada</span>
                            <span class="font-mono">{{ fmtDateTime(props.invoice.updated_at) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Items -->
            <Card class="min-w-0 border border-border/40 rounded-xl">
                <CardHeader>
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <CardTitle class="text-base">Items</CardTitle>
                            <CardDescription class="text-xs">Detalle de líneas facturadas</CardDescription>
                        </div>

                        <Badge variant="outline">
                            {{ (props.invoice.items?.length ?? 0) }} items
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="text-sm">
                    <div v-if="!props.invoice.items?.length" class="rounded-xl border p-6 text-center">
                        <div class="text-base font-semibold">Sin items</div>
                        <div class="mt-1 text-sm text-muted-foreground">Esta factura no tiene líneas registradas.</div>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-muted-foreground">
                                <tr class="border-b">
                                    <th class="py-2 text-left">Descripción</th>
                                    <th class="py-2 text-left">Servicio</th>
                                    <th class="py-2 text-right">Qty</th>
                                    <th class="py-2 text-right">Unit</th>
                                    <th class="py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="it in props.invoice.items" :key="it.id" class="border-b last:border-b-0">
                                    <td class="py-3 pr-4">
                                        <div class="font-medium">{{ it.description }}</div>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div class="text-muted-foreground">
                                            <template v-if="it.service">
                                                <span class="font-mono">{{ it.service.slug }}</span>
                                                <span class="text-muted-foreground"> • </span>
                                                <span>{{ it.service.title }}</span>
                                            </template>
                                            <template v-else>—</template>
                                        </div>
                                    </td>
                                    <td class="py-3 text-right font-mono">{{ it.quantity }}</td>
                                    <td class="py-3 text-right font-mono">{{ money(it.unit_price) }}</td>
                                    <td class="py-3 text-right font-mono">{{ money(it.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Payments -->
            <Card class="min-w-0 border border-border/40 rounded-xl">
                <CardHeader>
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <CardTitle class="text-base">Pagos</CardTitle>
                            <CardDescription class="text-xs">Pagos asociados a esta factura</CardDescription>
                        </div>

                        <Badge variant="outline">
                            {{ (props.invoice.payments?.length ?? 0) }} pagos
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="text-sm">
                    <div v-if="!props.invoice.payments?.length" class="rounded-xl border p-6 text-center">
                        <div class="text-base font-semibold">Sin pagos</div>
                        <div class="mt-1 text-sm text-muted-foreground">Aún no hay pagos registrados para esta factura.</div>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-muted-foreground">
                                <tr class="border-b">
                                    <th class="py-2 text-left">Referencia</th>
                                    <th class="py-2 text-left">Método</th>
                                    <th class="py-2 text-right">Monto</th>
                                    <th class="py-2 text-left">Paid at</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="p in props.invoice.payments" :key="p.id" class="border-b last:border-b-0">
                                    <td class="py-3 pr-4">
                                        <div class="font-medium truncate">
                                            {{ p.reference ?? `Payment #${p.id}` }}
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <Badge variant="outline" class="capitalize">{{ p.method }}</Badge>
                                    </td>
                                    <td class="py-3 text-right font-mono">
                                        {{ money(p.amount) }} <span class="text-muted-foreground">{{ p.currency }}</span>
                                    </td>
                                    <td class="py-3 font-mono">
                                        {{ fmtDateTime(p.paid_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
