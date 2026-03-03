<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'

type InvoiceRow = {
    id: number
    number: string
    status: string
    issued_on?: string | null
    due_on?: string | null
    currency: string
    total: string
    amount_paid: string
    balance: string
    document_class?: string | null
    document_type?: string | null
    fiscal_number?: string | null
    hosted_invoice_url?: string | null
    payment_url?: string | null
}

type LinkItem = { url: string | null; label: string; active: boolean }

type Paginator<T> = {
    data: T[]
    links: LinkItem[]
    meta?: any
}

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline' | null

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string }
    filters: { status: string; q: string; from?: string | null; to?: string | null }
    invoices: Paginator<InvoiceRow>
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Facturas', href: '/subscriber/invoices' },
]

// -------------------------
// local state (synced with server filters)
// -------------------------
const status = ref(props.filters.status ?? 'all')
const q = ref(props.filters.q ?? '')
const from = ref(props.filters.from ?? '')
const to = ref(props.filters.to ?? '')

const showAdvanced = ref(false)

// -------------------------
// helpers
// -------------------------
function normStatus(s: any) {
    return String(s ?? '').toLowerCase().trim()
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

function badgeVariantByInvoiceStatus(st: string): BadgeVariant {
    const s = normStatus(st)
    if (s === 'overdue') return 'destructive'
    if (s === 'void') return 'destructive'
    if (s === 'paid') return 'secondary'
    if (s === 'issued') return 'secondary'
    if (s === 'draft') return 'outline'
    return 'secondary'
}

function balanceClass(inv: InvoiceRow) {
    const st = normStatus(inv.status)
    if (st === 'paid') return 'text-emerald-600 dark:text-emerald-400'
    if (st === 'overdue') return 'text-rose-600 dark:text-rose-400'
    const b = Number(inv.balance)
    if (Number.isFinite(b) && b <= 0) return 'text-emerald-600 dark:text-emerald-400'
    return 'text-muted-foreground'
}

const rows = computed(() => props.invoices?.data ?? [])

// Totales de la página actual (como ya tienes)
const counts = computed(() => {
    const list = rows.value
    const c = { all: 0, draft: 0, issued: 0, paid: 0, overdue: 0, void: 0 }
    for (const inv of list) {
        c.all++
        const st = normStatus(inv.status) as keyof typeof c
        if (st in c) (c as any)[ st ]++
    }
    return c
})

const hasActiveFilters = computed(() => {
    return (
        (status.value && status.value !== 'all') ||
        !!q.value.trim() ||
        !!from.value ||
        !!to.value
    )
})

// -------------------------
// actions
// -------------------------
function applyFilters() {
    router.get(
        '/subscriber/invoices',
        {
            status: status.value,
            q: q.value.trim(),
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true }
    )
}

function clearFilters() {
    status.value = 'all'
    q.value = ''
    from.value = ''
    to.value = ''
    applyFilters()
}

function setStatus(s: string) {
    status.value = s
    // aquí lo aplicamos de inmediato porque es un “tab”
    applyFilters()
}

function goTo(url: string | null) {
    if (!url) return
    router.visit(url, { preserveScroll: true, preserveState: true })
}
</script>

<template>

    <Head title="Facturas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header limpio -->
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Facturas</h1>
                    <div class="text-sm text-muted-foreground">
                        <span class="font-medium text-foreground">{{ props.company.name }}</span>
                        · Moneda: {{ props.company.currency }}
                        · TZ: {{ props.company.timezone }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="showAdvanced = !showAdvanced">
                        {{ showAdvanced ? 'Ocultar filtros' : 'Filtros avanzados' }}
                    </Button>
                    <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="clearFilters">
                        Limpiar
                    </Button>
                </div>
            </div>

            <!-- Resumen en cards (más visual) -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <SectionCard title="Página" description="Cantidad en esta página">
                    <div class="text-2xl font-semibold">{{ counts.all }}</div>
                </SectionCard>

                <SectionCard title="Emitidas" description="En esta página">
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">issued</Badge>
                        <div class="text-2xl font-semibold">{{ counts.issued }}</div>
                    </div>
                </SectionCard>

                <SectionCard title="Pagadas" description="En esta página">
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">paid</Badge>
                        <div class="text-2xl font-semibold">{{ counts.paid }}</div>
                    </div>
                </SectionCard>

                <SectionCard title="Vencidas" description="En esta página">
                    <div class="flex items-center gap-2">
                        <Badge variant="destructive">overdue</Badge>
                        <div class="text-2xl font-semibold">{{ counts.overdue }}</div>
                    </div>
                </SectionCard>

                <SectionCard title="Anuladas" description="En esta página">
                    <div class="flex items-center gap-2">
                        <Badge variant="destructive">void</Badge>
                        <div class="text-2xl font-semibold">{{ counts.void }}</div>
                    </div>
                </SectionCard>
            </div>

            <!-- Estado como tabs/segmented -->
            <SectionCard title="Estado" description="Filtra rápidamente por estado">
                <div class="flex flex-wrap gap-2">
                    <Button size="sm" variant="outline" :class="status === 'all' ? 'bg-muted' : ''" @click="setStatus('all')">
                        Todas
                    </Button>
                    <Button size="sm" variant="outline" :class="status === 'draft' ? 'bg-muted' : ''" @click="setStatus('draft')">
                        Borrador
                    </Button>
                    <Button size="sm" variant="outline" :class="status === 'issued' ? 'bg-muted' : ''" @click="setStatus('issued')">
                        Emitidas
                    </Button>
                    <Button size="sm" variant="outline" :class="status === 'paid' ? 'bg-muted' : ''" @click="setStatus('paid')">
                        Pagadas
                    </Button>
                    <Button size="sm" variant="outline" :class="status === 'overdue' ? 'bg-muted' : ''" @click="setStatus('overdue')">
                        Vencidas
                    </Button>
                    <Button size="sm" variant="outline" :class="status === 'void' ? 'bg-muted' : ''" @click="setStatus('void')">
                        Anuladas
                    </Button>
                </div>
            </SectionCard>

            <!-- Filtros avanzados colapsables -->
            <SectionCard v-if="showAdvanced" title="Filtros avanzados" description="Búsqueda y rango de fechas">
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <div class="text-xs text-muted-foreground mb-1">Buscar</div>
                        <input v-model="q" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="No. factura / NCF / provider id..." @keydown.enter.prevent="applyFilters" />
                    </div>

                    <div>
                        <div class="text-xs text-muted-foreground mb-1">Desde (issued_on)</div>
                        <input v-model="from" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>

                    <div>
                        <div class="text-xs text-muted-foreground mb-1">Hasta (issued_on)</div>
                        <input v-model="to" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>
                </div>

                <div class="mt-3 flex justify-end gap-2">
                    <Button variant="outline" size="sm" @click="clearFilters">Limpiar</Button>
                    <Button size="sm" @click="applyFilters">Aplicar</Button>
                </div>
            </SectionCard>

            <!-- Tabla en su propio card -->
            <SectionCard title="Listado" description="Facturas de tu compañía">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40">
                            <tr class="text-left">
                                <th class="px-3 py-2">Número</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Emitida</th>
                                <th class="px-3 py-2">Vence</th>
                                <th class="px-3 py-2">NCF</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-right">Pagado</th>
                                <th class="px-3 py-2 text-right">Balance</th>
                                <th class="px-3 py-2">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="9" class="px-3 py-4 text-muted-foreground">
                                    No hay facturas para los filtros seleccionados.
                                </td>
                            </tr>

                            <tr v-for="inv in rows" :key="inv.id" class="border-t">
                                <td class="px-3 py-2 font-medium">{{ inv.number }}</td>

                                <td class="px-3 py-2">
                                    <Badge :variant="badgeVariantByInvoiceStatus(inv.status)">
                                        {{ statusLabel(inv.status) }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-2 text-muted-foreground">{{ inv.issued_on ?? '—' }}</td>
                                <td class="px-3 py-2 text-muted-foreground">{{ inv.due_on ?? '—' }}</td>

                                <td class="px-3 py-2 text-muted-foreground">
                                    <span v-if="inv.fiscal_number">{{ inv.fiscal_number }}</span>
                                    <span v-else>—</span>
                                </td>

                                <td class="px-3 py-2 text-right">
                                    {{ inv.total }} {{ inv.currency }}
                                </td>

                                <td class="px-3 py-2 text-right">
                                    {{ inv.amount_paid }} {{ inv.currency }}
                                </td>

                                <td class="px-3 py-2 text-right font-medium" :class="balanceClass(inv)">
                                    {{ inv.balance }} {{ inv.currency }}
                                </td>

                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <Button size="sm" variant="outline" as-child>
                                            <Link :href="`/subscriber/invoices/${inv.id}`">Ver</Link>
                                        </Button>

                                        <Button v-if="inv.hosted_invoice_url" size="sm" variant="outline" as-child>
                                            <a :href="inv.hosted_invoice_url" target="_blank" rel="noopener">Abrir</a>
                                        </Button>

                                        <!-- Pagar (solo UI, la lógica la haces después) -->
                                        <Button v-if="inv.payment_url" size="sm" class="bg-emerald-600 text-white hover:bg-emerald-700" as-child>
                                            <a :href="inv.payment_url" target="_blank" rel="noopener">Pagar</a>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="props.invoices.links?.length" class="mt-4 flex flex-wrap gap-2">
                    <Button v-for="l in props.invoices.links" :key="l.label" size="sm" variant="outline" :disabled="!l.url" :class="l.active ? 'bg-muted' : ''" @click="goTo(l.url)">
                        <span v-html="l.label"></span>
                    </Button>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
