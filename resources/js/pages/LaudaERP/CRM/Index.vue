<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import CrmLayout from '@/layouts/CrmLayout.vue'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type StatPayload = {
    customers_total: number
    contacts_total: number
    leads_total: number
    opportunities_open: number
    quotes_open: number
    activities_pending: number
}

type PipelineStage = {
    key: string
    title: string
    count: number
}

type QuickAction = {
    title: string
    description: string
    href: string
}

type RecentActivity = {
    id: number
    title: string
    type: string
    status: string
    priority: string
    scheduled_at: string | null
    completed_at: string | null
    customer_name: string | null
    contact_name: string | null
    lead_name: string | null
    opportunity_title: string | null
    assigned_user_name: string | null
}

type TopCustomer = {
    id: number
    name: string
    business_name: string | null
    status: string
    contacts_count: number
    opportunities_count: number
    activities_count: number
    email: string | null
    phone: string | null
}

const props = defineProps<{
    stats: StatPayload
    pipeline: PipelineStage[]
    recentActivities: RecentActivity[]
    topCustomers: TopCustomer[]
    quickActions: QuickAction[]
}>()

const statCards = [
    {
        key: 'customers_total',
        title: 'Clientes',
        value: props.stats.customers_total,
        description: 'Empresas o cuentas registradas en el CRM.',
    },
    {
        key: 'contacts_total',
        title: 'Contactos',
        value: props.stats.contacts_total,
        description: 'Personas asociadas a cuentas o clientes.',
    },
    {
        key: 'leads_total',
        title: 'Leads',
        value: props.stats.leads_total,
        description: 'Prospectos pendientes o históricos.',
    },
    {
        key: 'opportunities_open',
        title: 'Oportunidades abiertas',
        value: props.stats.opportunities_open,
        description: 'Negocios activos dentro del pipeline.',
    },
    {
        key: 'quotes_open',
        title: 'Cotizaciones abiertas',
        value: props.stats.quotes_open,
        description: 'Reservado para la próxima fase.',
    },
    {
        key: 'activities_pending',
        title: 'Actividades pendientes',
        value: props.stats.activities_pending,
        description: 'Seguimientos, llamadas, reuniones o tareas.',
    },
]

function statusBadgeClass(value: string) {
    if (value === 'pending') return 'bg-slate-700 text-white hover:bg-slate-700'
    if (value === 'completed') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'cancelled') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return ''
}

function priorityBadgeClass(value: string) {
    if (value === 'low') return 'bg-slate-200 text-slate-800 hover:bg-slate-200'
    if (value === 'normal') return 'bg-blue-600 text-white hover:bg-blue-600'
    if (value === 'high') return 'bg-orange-500 text-white hover:bg-orange-500'
    if (value === 'urgent') return 'bg-red-600 text-white hover:bg-red-600'
    return ''
}

function customerStatusClass(value: string) {
    if (value === 'active') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'inactive') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return 'bg-slate-700 text-white hover:bg-slate-700'
}
</script>

<template>

    <Head title="CRM" />

    <CrmLayout title="CRM" description="Gestiona clientes, contactos, leads, oportunidades y seguimiento comercial desde una base conectada al ecosistema LaudaERP.">
        <section class="flex flex-wrap gap-2">
            <Button v-for="action in props.quickActions" :key="action.title" as-child variant="outline">
                <Link :href="action.href">
                    {{ action.title }}
                </Link>
            </Button>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="card in statCards" :key="card.key" class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>{{ card.title }}</CardDescription>
                    <CardTitle class="text-3xl font-semibold">
                        {{ card.value }}
                    </CardTitle>
                </CardHeader>

                <CardContent>
                    <p class="text-xs text-muted-foreground">
                        {{ card.description }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <Card class="rounded-2xl">
            <CardHeader>
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <CardTitle>Pipeline comercial</CardTitle>
                        <CardDescription>
                            Estado general de oportunidades por etapa.
                        </CardDescription>
                    </div>

                    <Badge variant="outline">
                        {{ props.pipeline.length }} etapas
                    </Badge>
                </div>
            </CardHeader>

            <CardContent>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div v-for="stage in props.pipeline" :key="stage.key" class="rounded-2xl border bg-muted/20 p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ stage.title }}
                        </div>
                        <div class="mt-2 text-2xl font-semibold">
                            {{ stage.count }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card class="rounded-2xl">
                <CardHeader>
                    <CardTitle>Actividad reciente</CardTitle>
                    <CardDescription>
                        Llamadas, reuniones, tareas y seguimiento comercial.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="props.recentActivities.length === 0" class="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                        No hay actividad reciente todavía.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="item in props.recentActivities" :key="item.id" class="rounded-2xl border p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="font-medium">{{ item.title }}</div>

                                    <div class="flex flex-wrap gap-2">
                                        <Badge variant="outline" class="capitalize">
                                            {{ item.type }}
                                        </Badge>

                                        <Badge variant="secondary" :class="statusBadgeClass(item.status)" class="capitalize">
                                            {{ item.status }}
                                        </Badge>

                                        <Badge variant="secondary" :class="priorityBadgeClass(item.priority)" class="capitalize">
                                            {{ item.priority }}
                                        </Badge>
                                    </div>

                                    <div class="space-y-1 text-xs text-muted-foreground">
                                        <div>Cliente: {{ item.customer_name || '—' }}</div>
                                        <div>Contacto: {{ item.contact_name || '—' }}</div>
                                        <div>Lead: {{ item.lead_name || '—' }}</div>
                                        <div>Oportunidad: {{ item.opportunity_title || '—' }}</div>
                                    </div>
                                </div>

                                <div class="space-y-1 text-right text-xs text-muted-foreground">
                                    <div>Programada: {{ item.scheduled_at || '—' }}</div>
                                    <div>Completada: {{ item.completed_at || '—' }}</div>
                                    <div>Asignado: {{ item.assigned_user_name || '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader>
                    <CardTitle>Clientes destacados</CardTitle>
                    <CardDescription>
                        Cuentas con mayor relevancia o actividad reciente.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="props.topCustomers.length === 0" class="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                        No hay clientes destacados todavía.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="item in props.topCustomers" :key="item.id" class="rounded-2xl border p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="font-medium">{{ item.name }}</div>
                                        <Badge variant="secondary" :class="customerStatusClass(item.status)" class="capitalize">
                                            {{ item.status }}
                                        </Badge>
                                    </div>

                                    <div class="text-xs text-muted-foreground">
                                        {{ item.business_name || '—' }}
                                    </div>

                                    <div class="text-xs text-muted-foreground">
                                        {{ item.email || '—' }} · {{ item.phone || '—' }}
                                    </div>
                                </div>

                                <div class="grid min-w-55 grid-cols-3 gap-2 text-center">
                                    <div class="rounded-xl border p-2">
                                        <div class="text-lg font-semibold">{{ item.contacts_count }}</div>
                                        <div class="text-[11px] text-muted-foreground">Contactos</div>
                                    </div>

                                    <div class="rounded-xl border p-2">
                                        <div class="text-lg font-semibold">{{ item.opportunities_count }}</div>
                                        <div class="text-[11px] text-muted-foreground">Oportunidades</div>
                                    </div>

                                    <div class="rounded-xl border p-2">
                                        <div class="text-lg font-semibold">{{ item.activities_count }}</div>
                                        <div class="text-[11px] text-muted-foreground">Actividades</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </CrmLayout>
</template>