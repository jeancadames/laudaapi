<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import adminCompany from '@/routes/admin/company'
import company from '@/routes/admin/company'
import type { BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type CompanyRow = {
    id: number
    name: string
    active: boolean
    updated_at: string | null
    tax_profile: {
        exists: boolean
        legal_name: string | null
        trade_name: string | null
        country_code: string
        tax_id: string | null
        tax_id_type: string
        updated_at: string | null
    }
    transactions: {
        total: number
        pending: number
    }
}

type Paginator<T> = {
    data: T[]
    current_page: number
    last_page: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    companies: Paginator<CompanyRow>
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Clientes', href: adminCompany.index().url }, // ✅ label
]

const dtf = new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short' })
function fmtDate(v?: string | null) {
    if (!v) return '—'
    const d = new Date(v)
    return Number.isNaN(d.getTime()) ? '—' : dtf.format(d)
}

const headerSubtitle = computed(() => {
    return `Mostrando ${props.companies.data.length} • Total: ${props.companies.total}`
})

function goCompany(id: number) {
    router.visit(company.transactions.index({ company: id }).url)
}

</script>

<template>

    <Head title="Clientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1 min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight leading-tight truncate">
                        Clientes
                    </h1>
                    <div class="text-sm text-muted-foreground">
                        {{ headerSubtitle }}
                    </div>
                </div>

                <div class="flex gap-2" />
            </div>

            <!-- Empty -->
            <div v-if="props.companies.data.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin clientes</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    Aún no hay registros para mostrar.
                </div>
            </div>

            <!-- Cards -->
            <div v-else class="grid gap-6 grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(360px,1fr))]">
                <Card v-for="c in props.companies.data" :key="c.id" class="min-w-0 border border-border/40 rounded-xl hover:shadow-md transition-all cursor-pointer" role="button" tabindex="0" @click="goCompany(c.id)" @keydown.enter.prevent="goCompany(c.id)" @keydown.space.prevent="goCompany(c.id)">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-3">
                            <CardTitle class="text-base font-semibold truncate">
                                {{ c.name }}
                            </CardTitle>

                            <div class="flex items-center gap-2">
                                <Badge :variant="c.active ? 'default' : 'secondary'">
                                    {{ c.active ? 'Activo' : 'Inactivo' }}
                                </Badge>

                                <Badge :variant="c.tax_profile.exists ? 'default' : 'secondary'">
                                    {{ c.tax_profile.exists ? 'Tax OK' : 'Tax pendiente' }}
                                </Badge>
                            </div>
                        </div>

                        <CardDescription class="text-xs mt-1">
                            <span class="text-muted-foreground">Actualizado:</span>
                            <span class="ml-1">{{ fmtDate(c.updated_at) }}</span>
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4 text-sm">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Tax ID</div>
                                <div class="font-mono">
                                    {{ c.tax_profile.tax_id ? `${c.tax_profile.tax_id_type}: ${c.tax_profile.tax_id}` : '—' }}
                                </div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">País</div>
                                <div class="font-mono">{{ c.tax_profile.country_code }}</div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">Transacciones</div>
                                <div class="font-mono">{{ c.transactions.total }}</div>
                            </div>

                            <div>
                                <div class="text-xs text-muted-foreground">Pendientes</div>
                                <div class="font-mono">{{ c.transactions.pending }}</div>
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <Button size="sm" variant="outline" @click.stop="router.visit(`/admin/company/${c.id}/tax-profile`)">
                                Tax Profile
                            </Button>

                            <Button size="sm" variant="outline" @click.stop="router.visit(`/admin/company/${c.id}/transactions`)">
                                Transacciones
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between mt-4">
                <div class="text-sm text-muted-foreground">
                    Página {{ props.companies.current_page }} de {{ props.companies.last_page }}
                </div>

                <div class="flex gap-2">
                    <Button size="sm" variant="outline" :disabled="!props.companies.prev_page_url" @click="props.companies.prev_page_url && router.visit(props.companies.prev_page_url, { preserveScroll: true, preserveState: true })">
                        Anterior
                    </Button>

                    <Button size="sm" variant="outline" :disabled="!props.companies.next_page_url" @click="props.companies.next_page_url && router.visit(props.companies.next_page_url, { preserveScroll: true, preserveState: true })">
                        Siguiente
                    </Button>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
