<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import payments from '@/routes/admin/payments'
import type { BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type PaymentShow = {
    id: number
    method: string
    currency: string
    amount: string | number
    paid_at: string | null
    reference: string | null
    meta: any
    company: { id: number; name: string } | null
    invoice: { id: number; number: string; status: string; total: any; currency: string } | null
    created_at: string | null
    updated_at: string | null
}

const props = defineProps<{
    payment: PaymentShow
    back: { href: string }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Payments', href: payments.index().url },
    { title: `Payment #${props.payment.id}`, href: payments.show({ payment: props.payment.id }).url },
]

</script>

<template>

    <Head :title="`Payment • #${payment.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1 min-w-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <h1 class="text-2xl font-semibold tracking-tight leading-tight truncate">
                            {{ payment.reference ?? `Payment #${payment.id}` }}
                        </h1>
                        <Badge variant="outline" class="capitalize">{{ payment.method }}</Badge>
                        <Badge :variant="payment.paid_at ? 'default' : 'secondary'">
                            {{ payment.paid_at ? 'Paid' : 'Unpaid' }}
                        </Badge>
                    </div>

                    <div class="text-sm text-muted-foreground truncate">
                        <span class="text-muted-foreground">Cliente:</span>
                        <span class="ml-1">{{ payment.company?.name ?? '—' }}</span>
                        <span v-if="payment.invoice?.number" class="text-muted-foreground"> • Invoice: {{ payment.invoice.number }}</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button size="sm" variant="outline" @click="router.visit(back.href)">Volver</Button>
                    <Button v-if="payment.invoice?.id" size="sm" variant="outline" @click="router.visit(`/admin/invoices/${payment.invoice.id}`)">
                        Ver invoice
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 grid-cols-1 lg:grid-cols-3">
                <Card class="border border-border/40 rounded-xl lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base">Detalle</CardTitle>
                        <CardDescription class="text-xs">Información del pago</CardDescription>
                    </CardHeader>

                    <CardContent class="text-sm space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Monto</div>
                                <div class="font-mono">{{ payment.amount }} {{ payment.currency }}</div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">Paid at</div>
                                <div class="font-mono">{{ payment.paid_at ?? '—' }}</div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">Referencia</div>
                                <div class="font-mono">{{ payment.reference ?? '—' }}</div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">Método</div>
                                <div class="font-mono">{{ payment.method }}</div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Creado</div>
                                <div class="font-mono">{{ payment.created_at ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Actualizado</div>
                                <div class="font-mono">{{ payment.updated_at ?? '—' }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border border-border/40 rounded-xl">
                    <CardHeader>
                        <CardTitle class="text-base">Invoice</CardTitle>
                        <CardDescription class="text-xs">Si está asociado</CardDescription>
                    </CardHeader>

                    <CardContent class="text-sm space-y-3">
                        <div>
                            <div class="text-xs text-muted-foreground">Número</div>
                            <div class="font-mono">{{ payment.invoice?.number ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Status</div>
                            <div class="font-mono">{{ payment.invoice?.status ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Total</div>
                            <div class="font-mono">
                                {{ payment.invoice?.total ?? '—' }}
                                <span class="text-muted-foreground">{{ payment.invoice?.currency ?? '' }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="border border-border/40 rounded-xl">
                <CardHeader>
                    <CardTitle class="text-base">Meta</CardTitle>
                    <CardDescription class="text-xs">JSON adicional</CardDescription>
                </CardHeader>

                <CardContent class="text-sm">
                    <pre class="whitespace-pre-wrap rounded-xl border p-4 text-xs overflow-x-auto">{{ JSON.stringify(payment.meta ?? {}, null, 2) }}</pre>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
