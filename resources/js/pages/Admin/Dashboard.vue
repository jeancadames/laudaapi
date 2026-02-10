<!-- resources/js/Pages/Admin/Dashboard.vue -->
<script setup lang="ts">
import { Head, router, useRemember } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import { type BreadcrumbItem } from '@/types'

import StatCard from '@/components/StatCard.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Button } from '@/components/ui/button'

const breadcrumbs: BreadcrumbItem[] = [ { title: 'Dashboard', href: dashboard().url } ]

const props = defineProps<{
    stats: any
}>()

// ✅ Inertia useRemember NO acepta boolean directo (solo object/array)
const ui = useRemember({ showFinancial: false }, 'admin.dashboard.ui')

// ✅ si solo manejas USD
const currency = computed(() => 'USD')

function money(n: any) {
    const v = Number(n ?? 0)
    if (!Number.isFinite(v)) return '0.00'
    return new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)
}

function moneyWithCurrency(n: any) {
    return `${money(n)} ${currency.value}`
}

// ✅ mini resumen cuando está oculto
const financialSummary = computed(() => {
    const companies = props.stats?.company?.total ?? 0
    const issued = props.stats?.billing?.invoices?.by_status?.issued ?? 0
    const overdue = props.stats?.billing?.invoices?.by_status?.overdue ?? 0
    const ar = props.stats?.billing?.balance?.accounts_receivable ?? 0
    return `Resumen: ${companies} clientes • ${(issued + overdue)} facturas pendientes • AR ${moneyWithCurrency(ar)}`
})
</script>

<template>

    <Head title="Panel Administración" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <!-- ✅ 1) Contactos -->
            <SectionCard title="Contactos" description="Solicitudes de contacto recibidas">
                <div class="grid gap-6 md:grid-cols-3">
                    <StatCard title="Contactos recibidos" :value="props.stats?.contacts?.total ?? 0" description="Solicitudes totales" />
                    <StatCard title="Contactos sin leer" :value="props.stats?.contacts?.unread ?? 0" description="Pendientes de revisión" :trend-positive="false" />
                    <StatCard title="Con términos" :value="props.stats?.contacts?.with_terms ?? 0" description="Aceptaron términos" />
                </div>
            </SectionCard>

            <!-- ✅ 2) Activaciones -->
            <SectionCard title="Activaciones" description="Estado del pipeline de activación">
                <div class="grid gap-6 md:grid-cols-4">
                    <StatCard title="Total" :value="props.stats?.activations?.total ?? 0" description="Solicitudes recibidas" />
                    <StatCard title="Pendientes" :value="props.stats?.activations?.pending ?? 0" description="Aún sin gestionar" />
                    <StatCard title="Contactados" :value="props.stats?.activations?.contacted ?? 0" description="En seguimiento" />
                    <StatCard title="Trial activo" :value="props.stats?.activations?.active_trials ?? 0" description="Pruebas en curso" />
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-4">
                    <StatCard title="Activated" :value="props.stats?.activations?.activated ?? 0" description="Convertidos" />
                    <StatCard title="Trialing" :value="props.stats?.activations?.trialing ?? 0" description="En trialing" />
                    <StatCard title="Servicios solicitados" :value="props.stats?.activations?.services ?? 0" description="Servicios en activación" />
                    <!-- ✅ evita card vacía -->
                    <StatCard title="—" :value="0" description="" class="opacity-0 pointer-events-none hidden md:block" />
                </div>
            </SectionCard>

            <!-- ✅ 3) Suscripciones -->
            <SectionCard title="Suscripciones" description="Estado de los clientes activos">
                <div class="grid gap-6 md:grid-cols-4">
                    <StatCard title="Suscripciones activas" :value="props.stats?.subscriptions?.active ?? 0" description="Clientes con plan activo" />
                    <StatCard title="En prueba" :value="props.stats?.subscriptions?.trialing ?? 0" description="Trialing" />
                    <StatCard title="Expiradas" :value="props.stats?.subscriptions?.expired ?? 0" description="Sin renovar" :trend-positive="false" />
                    <StatCard title="Servicios activos" :value="props.stats?.subscriptions?.services ?? 0" description="Servicios habilitados" />
                </div>
            </SectionCard>

            <!-- ✅ 4) Información financiera con botón + mini-resumen -->
            <SectionCard title="Información financiera" :description="`Clientes, facturas, pagos y balance (${currency})`">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-muted-foreground">
                        <template v-if="ui.showFinancial">
                            Mostrando detalles financieros.
                        </template>
                        <template v-else>
                            {{ financialSummary }}
                        </template>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" @click="ui.showFinancial = !ui.showFinancial">
                            {{ ui.showFinancial ? 'Ocultar' : 'Ver' }} información financiera
                        </Button>

                        <template v-if="ui.showFinancial">
                            <Button size="sm" variant="outline" @click="router.visit('/admin/company')">Clientes</Button>
                            <Button size="sm" variant="outline" @click="router.visit('/admin/invoices')">Facturas</Button>
                            <Button size="sm" variant="outline" @click="router.visit('/admin/payments')">Pagos</Button>
                        </template>
                    </div>
                </div>

                <div v-if="ui.showFinancial" class="mt-4 space-y-4">
                    <!-- Resumen -->
                    <div class="rounded-xl border bg-muted/20 p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold">Resumen</div>
                            <div class="text-xs text-muted-foreground">Indicadores rápidos</div>
                        </div>

                        <div class="mt-3 grid gap-6 md:grid-cols-3">
                            <StatCard title="Clientes" :value="props.stats?.company?.total ?? 0" description="Total de clientes" />
                            <StatCard title="Facturas emitidas/vencidas" :value="(props.stats?.billing?.invoices?.by_status?.issued ?? 0) + (props.stats?.billing?.invoices?.by_status?.overdue ?? 0)" description="Pendientes de pago" :trend-positive="false" />
                            <StatCard title="Balance por cobrar (AR)" :value="moneyWithCurrency(props.stats?.billing?.balance?.accounts_receivable ?? 0)" description="Issued + Overdue" :trend-positive="false" />
                        </div>
                    </div>

                    <!-- Clientes -->
                    <div class="rounded-xl border bg-muted/20 p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold">Clientes</div>
                            <div class="text-xs text-muted-foreground">Estado general</div>
                        </div>

                        <div class="mt-3 grid gap-6 md:grid-cols-4">
                            <StatCard title="Total" :value="props.stats?.company?.total ?? 0" description="Clientes registrados" />
                            <StatCard title="Activos" :value="props.stats?.company?.active ?? 0" description="Clientes activos" />
                            <StatCard title="Tax Profile" :value="props.stats?.company?.tax_profile_count ?? 0" description="Perfiles fiscales creados" />
                            <StatCard title="Sin Tax Profile" :value="(props.stats?.company?.total ?? 0) - (props.stats?.company?.tax_profile_count ?? 0)" description="Pendientes de completar" :trend-positive="false" />
                        </div>
                    </div>

                    <!-- Facturación -->
                    <div class="rounded-xl border bg-muted/20 p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold">Facturación</div>
                            <div class="text-xs text-muted-foreground">Facturas, pagos y balance</div>
                        </div>

                        <div class="mt-3 grid gap-6 md:grid-cols-4">
                            <StatCard title="Facturas totales" :value="props.stats?.billing?.invoices?.total ?? 0" description="Registros en sistema" />
                            <StatCard title="Emitidas" :value="props.stats?.billing?.invoices?.by_status?.issued ?? 0" description="Listas para cobrar" />
                            <StatCard title="Vencidas" :value="props.stats?.billing?.invoices?.by_status?.overdue ?? 0" description="Requieren seguimiento" :trend-positive="false" />
                            <StatCard title="Pagadas" :value="props.stats?.billing?.invoices?.by_status?.paid ?? 0" description="Completadas" />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-4">
                            <StatCard title="Total facturado" :value="moneyWithCurrency(props.stats?.billing?.invoices?.total_invoiced ?? 0)" description="Suma de facturas (sin void)" />
                            <StatCard title="Pagado (en facturas)" :value="moneyWithCurrency(props.stats?.billing?.invoices?.total_amount_paid_on_invoices ?? 0)" description="Suma amount_paid" />
                            <StatCard title="Pagos registrados" :value="props.stats?.billing?.payments?.posted ?? 0" description="Pagos con paid_at" />
                            <StatCard title="Total cobrado" :value="moneyWithCurrency(props.stats?.billing?.payments?.total_paid ?? 0)" description="Suma de pagos (paid_at)" />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-4">
                            <StatCard title="Balance por cobrar (AR)" :value="moneyWithCurrency(props.stats?.billing?.balance?.accounts_receivable ?? 0)" description="Issued + Overdue" :trend-positive="false" />
                            <StatCard title="Pendiente (emitidas/vencidas)" :value="moneyWithCurrency(props.stats?.billing?.invoices?.outstanding_issued_overdue ?? 0)" description="Solo issued + overdue" :trend-positive="false" />
                            <StatCard title="Draft" :value="props.stats?.billing?.invoices?.by_status?.draft ?? 0" description="Borradores" />
                            <StatCard title="Void" :value="props.stats?.billing?.invoices?.by_status?.void ?? 0" description="Anuladas" />
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
