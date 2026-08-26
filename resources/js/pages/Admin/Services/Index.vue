<!-- resources/js/Pages/Admin/Services/Index.vue -->
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import services from '@/routes/admin/services'
import type { BreadcrumbItem } from '@/types'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu'
import Select from '@/components/ui/select/Select.vue'
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue'
import SelectValue from '@/components/ui/select/SelectValue.vue'
import SelectContent from '@/components/ui/select/SelectContent.vue'
import SelectGroup from '@/components/ui/select/SelectGroup.vue'
import SelectItem from '@/components/ui/select/SelectItem.vue'

type BillingModel = 'flat' | 'per_user' | 'seat_block' | 'usage'
type BillingCycle = 'monthly' | 'yearly'

type PricingTier = {
    id?: number
    billing_cycle: BillingCycle
    min_quantity: number
    max_quantity: number | null
    price: string | number
    currency: string
    active: boolean
    sort_order: number
}

type BundleCandidate = {
    id: number
    title: string
    service_key: string | null
    parent_id: number | null
    currency: string
    billable: boolean
    active: boolean
}

type BundleItemDraft = {
    id?: number | null
    service_id: number | string
    required: boolean
    sort_order: number
}

type BundleRuleDraft = {
    id?: number | null
    code: string
    name: string
    discount_type: 'percentage' | 'fixed_amount'
    discount_value: string | number
    currency: string | null
    priority: number
    active: boolean
}

type BundleConfig = {
    enabled: boolean
    items: BundleItemDraft[]
    rules: BundleRuleDraft[]
}

type StatusFilter = 'all' | 'active' | 'inactive'

const DEFAULT_PARENT = 'api-facturacion-electronica'

type ServiceRow = {
    id: number
    title: string
    slug: string
    href: string | null
    icon: string | null
    badge: string | null

    short_description: string

    active: boolean
    billable: boolean
    type: 'core' | 'addon' | 'usage' | 'external'
    billing_model: BillingModel

    monthly_price: string | number
    yearly_price: string | number

    unit_name: string
    included_units: string | number
    unit_price: string | number
    overage_unit_price: string | number

    block_size: number | null
    pricing_tiers: PricingTier[]
    bundle: BundleConfig
    sort_order: number
}

const props = defineProps<{
    parent: {
        id: number
        title: string
        slug: string
        active: boolean
        billable: boolean
        monthly_price: number | null
        yearly_price: number | null
        billing_model: BillingModel
        sort_order: number
    }
    children: any[]
    bundle_candidates: BundleCandidate[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Servicios', href: services.index({ parent: DEFAULT_PARENT }).url },
    { title: props.parent.title, href: services.index({ parent: props.parent.slug }).url },
]

// Loading indicator
const isLoading = ref(false)
let unsubs: Array<() => void> = []
onMounted(() => {
    const start = router.on('start', () => (isLoading.value = true))
    const finish = router.on('finish', () => (isLoading.value = false))
    unsubs = [ start, finish ]
})
onBeforeUnmount(() => unsubs.forEach((u) => u()))

// Filters
const search = ref('')
const status = ref<StatusFilter>('all')
const filterBilling = ref<BillingModel | 'all'>('all')

// Dropdown options
const statusOptions = computed(() => ([
    { value: 'all' as const, label: 'Todos' },
    { value: 'active' as const, label: 'Activos' },
    { value: 'inactive' as const, label: 'Inactivos' },
]))
const currentStatus = computed(() => statusOptions.value.find(o => o.value === status.value) ?? statusOptions.value[ 0 ])

const billingOptions = computed(() => ([
    { value: 'all' as const, label: 'Facturación: todos' },
    { value: 'flat' as const, label: 'flat' },
    { value: 'per_user' as const, label: 'per_user' },
    { value: 'seat_block' as const, label: 'seat_block' },
    { value: 'usage' as const, label: 'usage' },
]))
const currentBilling = computed(() => billingOptions.value.find(o => o.value === filterBilling.value) ?? billingOptions.value[ 0 ])

// Helpers
function normalizeRow(c: any): ServiceRow {
    return {
        id: c.id,
        title: c.title ?? '',
        slug: c.slug ?? '',
        href: c.href ?? null,
        icon: c.icon ?? null,
        badge: c.badge ?? null,

        short_description: c.short_description ?? '',

        active: !!c.active,
        billable: c.billable ?? true,
        type: (c.type ?? 'addon') as ServiceRow[ 'type' ],
        billing_model: (c.billing_model ?? 'flat') as BillingModel,

        monthly_price: c.monthly_price ?? '',
        yearly_price: c.yearly_price ?? '',

        unit_name: c.unit_name ?? '',
        included_units: c.included_units ?? '',
        unit_price: '',
        overage_unit_price: c.overage_unit_price ?? '',

        block_size: c.block_size ?? null,
        pricing_tiers: (c.pricing_tiers ?? []).map((tier: any) => ({
            id: tier.id,
            billing_cycle: tier.billing_cycle,
            min_quantity: Number(tier.min_quantity ?? 1),
            max_quantity:
                tier.max_quantity === null || tier.max_quantity === undefined
                    ? null
                    : Number(tier.max_quantity),
            price: tier.price ?? '',
            currency: String(tier.currency ?? c.currency ?? 'DOP').toUpperCase(),
            active: tier.active ?? true,
            sort_order: Number(tier.sort_order ?? 0),
        })),
        bundle: {
            enabled: Boolean(c.bundle?.enabled ?? false),
            items: (c.bundle?.items ?? []).map((item: any, index: number) => ({
                id: item.id ?? null,
                service_id: Number(item.service_id),
                required: Boolean(item.required ?? false),
                sort_order: Number(item.sort_order ?? index),
            })),
            rules: (c.bundle?.rules ?? []).map((rule: any) => ({
                id: rule.id ?? null,
                code: String(rule.code ?? ''),
                name: String(rule.name ?? ''),
                discount_type: (rule.discount_type ?? 'percentage') as BundleRuleDraft['discount_type'],
                discount_value: rule.discount_value ?? 0,
                currency: rule.currency ?? null,
                priority: Number(rule.priority ?? 0),
                active: Boolean(rule.active ?? false),
            })),
        },
        sort_order: c.sort_order ?? 0,
    }
}

function toNullableNumber(v: any) {
    if (v === null || v === undefined) return null
    const s = String(v).trim()
    if (s === '') return null
    const n = Number(s)
    return Number.isFinite(n) ? n : null
}

function toIntOrDefault(v: any, def = 0) {
    const s = String(v ?? '').trim()
    if (s === '') return def
    const n = Number(s)
    return Number.isFinite(n) ? Math.max(0, Math.floor(n)) : def
}

function summarizeErrors(errors: any) {
    if (!errors) return ''
    const keys = Object.keys(errors)
    if (!keys.length) return ''
    const k = keys[ 0 ]
    const val = errors[ k ]
    if (Array.isArray(val)) return val[ 0 ]
    return String(val)
}

/** Snapshot plano (evita DataCloneError por proxies) */
function snapshotRow(row: ServiceRow): ServiceRow {
    return {
        id: row.id,
        title: row.title,
        slug: row.slug,
        href: row.href,
        icon: row.icon,
        badge: row.badge,

        short_description: row.short_description,

        active: row.active,
        billable: row.billable,
        type: row.type,
        billing_model: row.billing_model,

        monthly_price: row.monthly_price,
        yearly_price: row.yearly_price,

        unit_name: row.unit_name,
        included_units: row.included_units,
        unit_price: row.unit_price,
        overage_unit_price: row.overage_unit_price,

        block_size: row.block_size,
        pricing_tiers: row.pricing_tiers.map((tier) => ({
            ...tier,
        bundle: {
            enabled: Boolean(row.bundle.enabled),
            items: row.bundle.items.map((item) => ({
                id: item.id ?? null,
                service_id: item.service_id,
                required: Boolean(item.required),
                sort_order: Number(item.sort_order ?? 0),
            })),
            rules: row.bundle.rules.map((rule) => ({
                id: rule.id ?? null,
                code: String(rule.code ?? ''),
                name: String(rule.name ?? ''),
                discount_type: rule.discount_type,
                discount_value: rule.discount_value,
                currency: rule.currency ?? null,
                priority: Number(rule.priority ?? 0),
                active: Boolean(rule.active),
            })),
        },

        })),
        sort_order: row.sort_order,
    }
}

function applySnapshot(target: ServiceRow, snap: ServiceRow) {
    Object.assign(target, snapshotRow(snap))
}

// Local editable rows
const rows = reactive<ServiceRow[]>(
    (props.children || []).map((c) => normalizeRow(c))
)

// snapshot para reset
const originalById = reactive(new Map<number, ServiceRow>())
rows.forEach((r) => originalById.set(r.id, snapshotRow(r)))

// errores por fila
const rowErrors = reactive<Record<number, string>>({})
const busyIds = reactive(new Set<number>())

// debounce (client-side)
let t: number | null = null
watch(search, () => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => { }, 250)
})

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase()

    return rows
        .filter((r) => {
            if (status.value === 'active' && !r.active) return false
            if (status.value === 'inactive' && r.active) return false
            if (filterBilling.value !== 'all' && r.billing_model !== filterBilling.value) return false
            if (!q) return true
            return (r.title || '').toLowerCase().includes(q)
                || (r.slug || '').toLowerCase().includes(q)
                || (r.short_description || '').toLowerCase().includes(q)
        })
        .slice()
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
})

const headerSubtitle = computed(() => {
    const total = rows.length
    const shown = filteredRows.value.length
    const activeCount = rows.filter(r => r.active).length
    return `Mostrando ${shown} de ${total} • Activos: ${activeCount}${isLoading.value ? ' • Cargando...' : ''}`
})

// Actions
function addPricingTier(
    row: ServiceRow,
    billingCycle: BillingCycle,
) {
    const sameCycle = row.pricing_tiers.filter(
        (tier) => tier.billing_cycle === billingCycle,
    )

    const previousMax = sameCycle.reduce<number>(
        (max, tier) => {
            if (tier.max_quantity === null) return max
            return Math.max(max, Number(tier.max_quantity))
        },
        0,
    )

    row.pricing_tiers.push({
        billing_cycle: billingCycle,
        min_quantity: previousMax > 0 ? previousMax + 1 : 1,
        max_quantity: null,
        price: '',
        currency: 'DOP',
        active: true,
        sort_order: sameCycle.length,
    })
}

function removePricingTier(
    row: ServiceRow,
    index: number,
) {
    row.pricing_tiers.splice(index, 1)
}

function availableBundleCandidates(
    row: ServiceRow,
    itemIndex: number,
): BundleCandidate[] {
    const currentId = Number(row.bundle.items[itemIndex]?.service_id ?? 0)
    const used = new Set(
        row.bundle.items
            .map((item) => Number(item.service_id))
            .filter((id) => id > 0 && id !== currentId),
    )

    return (props.bundle_candidates ?? []).filter((candidate) =>
        candidate.id !== row.id
        && !used.has(candidate.id)
    )
}

function toggleBundleEnabled(row: ServiceRow) {
    row.bundle.enabled = !row.bundle.enabled

    if (!row.bundle.enabled) {
        row.bundle.rules.forEach((rule) => {
            rule.active = false
        })
    }
}

function addBundleItem(row: ServiceRow) {
    const used = new Set(
        row.bundle.items.map((item) => Number(item.service_id)),
    )

    const candidate = (props.bundle_candidates ?? []).find((service) =>
        service.id !== row.id
        && !used.has(service.id)
    )

    if (!candidate) return

    row.bundle.enabled = true
    row.bundle.items.push({
        id: null,
        service_id: candidate.id,
        required: true,
        sort_order: row.bundle.items.length,
    })
}

function removeBundleItem(row: ServiceRow, index: number) {
    row.bundle.items.splice(index, 1)
    row.bundle.items.forEach((item, itemIndex) => {
        item.sort_order = itemIndex
    })
}

function addBundleRule(row: ServiceRow) {
    row.bundle.enabled = true
    row.bundle.rules.push({
        id: null,
        code: `BUNDLE-${row.id}-${row.bundle.rules.length + 1}`,
        name: `${row.title} bundle`,
        discount_type: 'percentage',
        discount_value: 10,
        currency: null,
        priority: 0,
        active: false,
    })
}

function removeBundleRule(row: ServiceRow, index: number) {
    row.bundle.rules.splice(index, 1)
}

function normalizeBundleRuleCurrency(
    row: ServiceRow,
    rule: BundleRuleDraft,
) {
    if (rule.discount_type === 'percentage') {
        rule.currency = null
        return
    }

    if (!rule.currency) {
        rule.currency = row.currency || 'DOP'
    }
}

function bundleSummary(row: ServiceRow): string {
    if (!row.bundle.enabled) {
        return 'No configurado'
    }

    const required = row.bundle.items.filter(
        (item) => item.required,
    ).length

    const activeRules = row.bundle.rules.filter(
        (rule) => rule.active,
    ).length

    return `${row.bundle.items.length} componentes · ${required} required · ${activeRules} reglas activas`
}

function toggleActive(row: ServiceRow) {
    busyIds.add(row.id)
    delete rowErrors[ row.id ]

    router.patch(
        services.toggle({ service: row.id }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => busyIds.delete(row.id),
            onError: () => (rowErrors[ row.id ] = 'No se pudo cambiar el estado.'),
            onSuccess: () => {
                row.active = !row.active
                originalById.set(row.id, snapshotRow(row))
            },
        },
    )
}

function saveRow(row: ServiceRow) {
    busyIds.add(row.id)
    delete rowErrors[ row.id ]

    const payload: any = {
        title: row.title,
        href: row.href || null,
        badge: row.badge || null,
        icon: row.icon || null,

        short_description: (row.short_description || '').trim() || null,

        active: !!row.active,
        billable: !!row.billable,
        type: row.type,
        billing_model: row.billing_model,

        monthly_price: toNullableNumber(row.monthly_price),
        yearly_price: toNullableNumber(row.yearly_price),

        sort_order: Number.isFinite(row.sort_order) ? row.sort_order : 0,
    }

    if (row.billing_model === 'seat_block') {
        payload.monthly_price = null
        payload.yearly_price = null
        payload.block_size = null
        payload.unit_name = 'usuario'
        payload.included_units = null
        payload.overage_unit_price = null
        payload.pricing_tiers = row.pricing_tiers.map((tier) => ({
            billing_cycle: tier.billing_cycle,
            min_quantity: Number(tier.min_quantity),
            max_quantity:
                tier.max_quantity === null || String(tier.max_quantity).trim() === ''
                    ? null
                    : Number(tier.max_quantity),
            price: toNullableNumber(tier.price),
            currency: String(tier.currency || 'DOP').toUpperCase(),
            active: !!tier.active,
            sort_order: Number(tier.sort_order ?? 0),
        }))
    } else if (row.billing_model === 'usage') {
        payload.unit_name = (row.unit_name || 'units').trim()
        payload.included_units = toIntOrDefault(row.included_units, 0)
        payload.overage_unit_price = toNullableNumber(row.overage_unit_price)
        payload.block_size = null
    } else {
        payload.block_size = null
        payload.unit_name = null
        payload.included_units = null
        payload.overage_unit_price = null
    }

    const bundlePayload = {
        enabled: Boolean(row.bundle.enabled),
        items: row.bundle.enabled
            ? row.bundle.items.map((item, index) => ({
                service_id: Number(item.service_id),
                required: Boolean(item.required),
                sort_order: index,
            }))
            : [],
        rules: row.bundle.enabled
            ? row.bundle.rules.map((rule) => ({
                id: rule.id ?? null,
                code: String(rule.code || '').trim().toUpperCase(),
                name: String(rule.name || '').trim(),
                discount_type: rule.discount_type,
                discount_value:
                    toNullableNumber(rule.discount_value) ?? 0,
                currency:
                    rule.discount_type === 'fixed_amount'
                        ? String(
                            rule.currency
                            || row.currency
                            || 'DOP',
                        ).toUpperCase()
                        : null,
                priority: toIntOrDefault(
                    rule.priority,
                    0,
                ),
                active: Boolean(rule.active),
            }))
            : [],
    }

    router.patch(
        services.update({ service: row.id }).url,
        { ...payload, bundle: bundlePayload },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => busyIds.delete(row.id),
            onError: (errors) => (rowErrors[ row.id ] = summarizeErrors(errors) || 'No se pudo guardar.'),
            onSuccess: () => originalById.set(row.id, snapshotRow(row)),
        },
    )
}

function resetRow(id: number) {
    const orig = originalById.get(id)
    if (!orig) return

    const target = rows.find(r => r.id === id)
    if (!target) return

    applySnapshot(target, orig)
    delete rowErrors[ id ]
}

/* -------------------------------------------------------------------------- */
/* Create child (inline) - ✅ debe estar a nivel superior para el template     */
/* -------------------------------------------------------------------------- */

const showCreate = ref(false)
const creating = ref(false)
const createErrors = reactive<Record<string, string>>({})
const create = reactive({
    title: '',
    slug: '',
    short_description: '',
    billing_model: 'flat' as BillingModel,
    type: 'addon' as ServiceRow[ 'type' ],
    monthly_price: '' as any,
    yearly_price: '' as any,
    sort_order: 0,
})

function resetCreate() {
    create.title = ''
    create.slug = ''
    create.short_description = ''
    create.billing_model = 'flat'
    create.type = 'addon'
    create.monthly_price = ''
    create.yearly_price = ''
    create.sort_order = 0
    Object.keys(createErrors).forEach(k => delete createErrors[ k ])
}

function createChild() {
    creating.value = true
    Object.keys(createErrors).forEach((k) => delete createErrors[ k ])

    router.post(
        services.storeChild({ parent: props.parent.slug }).url,
        {
            title: create.title,
            slug: create.slug,
            short_description: (create.short_description || '').trim() || null,
            type: create.type,
            billing_model: create.billing_model,
            monthly_price: toNullableNumber(create.monthly_price),
            yearly_price: toNullableNumber(create.yearly_price),
            sort_order: create.sort_order ?? 0,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (creating.value = false),
            onError: (errors) => Object.assign(createErrors, errors || {}),
            onSuccess: () => {
                router.reload({ only: [ 'children' ] })
                resetCreate()
                showCreate.value = false
            },
        },
    )
}
</script>

<template>

    <Head :title="`Servicios • ${props.parent.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

            <!-- Header -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-col gap-1">
                        <h1 class="text-2xl font-semibold tracking-tight leading-tight truncate">
                            {{ props.parent.title }}
                        </h1>

                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs text-muted-foreground">slug:</span>
                            <span class="font-mono text-xs text-muted-foreground bg-muted px-2 py-1 rounded-md max-w-full truncate" :title="props.parent.slug">
                                {{ props.parent.slug }}
                            </span>
                        </div>
                    </div>

                    <div class="text-sm text-muted-foreground">
                        {{ headerSubtitle }}
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <!-- Status dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="outline" size="sm" class="gap-2">
                                <span>{{ currentStatus.label }}</span>
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="start" class="min-w-45">
                            <DropdownMenuItem v-for="opt in statusOptions" :key="opt.value" :class="status === opt.value ? 'bg-muted' : ''" @click="status = opt.value">
                                {{ opt.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Billing dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="outline" size="sm" class="gap-2">
                                <span>{{ currentBilling.label }}</span>
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="start" class="min-w-45">
                            <DropdownMenuItem v-for="opt in billingOptions" :key="opt.value" :class="filterBilling === opt.value ? 'bg-muted' : ''" @click="filterBilling = opt.value">
                                {{ opt.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Input v-model="search" placeholder="Buscar por título, slug o descripción..." class="w-full sm:w-72" />

                    <Button variant="default" @click="showCreate = !showCreate">
                        {{ showCreate ? 'Cerrar' : 'Agregar opción' }}
                    </Button>
                </div>
            </div>

            <!-- Create box -->
            <div v-if="showCreate" class="rounded-xl border p-4">
                <div class="text-sm font-semibold">Nueva opción (hijo)</div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <div>
                        <div class="text-xs text-muted-foreground">Título</div>
                        <Input v-model="create.title" />
                        <div v-if="createErrors.title" class="text-xs text-red-600 mt-1">{{ createErrors.title }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-muted-foreground">Slug</div>
                        <Input v-model="create.slug" />
                        <div v-if="createErrors.slug" class="text-xs text-red-600 mt-1">{{ createErrors.slug }}</div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="text-xs text-muted-foreground">Descripción corta</div>
                        <Input v-model="create.short_description" placeholder="Resumen corto (1 línea)" />
                        <div v-if="createErrors.short_description" class="text-xs text-red-600 mt-1">{{ createErrors.short_description }}</div>
                    </div>

                    <div>
                        <div class="text-xs text-muted-foreground">Facturación</div>
                        <Select v-model="create.billing_model">
                            <SelectTrigger class="mt-1 w-full">
                                <SelectValue placeholder="Selecciona un modelo" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="flat">Flat</SelectItem>
                                    <SelectItem value="per_user">Por usuario</SelectItem>
                                    <SelectItem value="seat_block">Seat Block</SelectItem>
                                    <SelectItem value="usage">Usage</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <div class="text-xs text-muted-foreground">Mensual (USD)</div>
                        <Input v-model="create.monthly_price" placeholder="29.00" />
                    </div>

                    <div>
                        <div class="text-xs text-muted-foreground">Anual (USD)</div>
                        <Input v-model="create.yearly_price" placeholder="290.00" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" type="button" @click="resetCreate">Limpiar</Button>
                    <Button :disabled="creating" type="button" @click="createChild">
                        {{ creating ? 'Creando...' : 'Crear' }}
                    </Button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="filteredRows.length === 0" class="rounded-xl border p-8 text-center">
                <div class="text-lg font-semibold">Sin resultados</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    No hay servicios que coincidan con tu filtro o búsqueda.
                </div>
            </div>

            <!-- Cards -->
            <div v-else class="grid gap-6 grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(360px,1fr))]">
                <Card v-for="row in filteredRows" :key="row.id" class="min-w-0 border border-border/40 rounded-xl hover:shadow-md transition-all">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-3">
                            <CardTitle class="text-base font-semibold truncate">
                                {{ row.title }}
                            </CardTitle>

                            <div class="flex items-center gap-2">
                                <Badge :variant="row.active ? 'default' : 'secondary'">
                                    {{ row.active ? 'Activo' : 'Inactivo' }}
                                </Badge>
                                <Badge v-if="row.billing_model" variant="outline" class="capitalize">
                                    {{ row.billing_model }}
                                </Badge>
                            </div>
                        </div>

                        <CardDescription class="text-xs mt-1">
                            <span class="font-mono">{{ row.slug }}</span>
                            <span class="text-muted-foreground" v-if="row.href"> • {{ row.href }}</span>
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <div class="text-xs text-muted-foreground">Descripción corta</div>
                            <Input v-model="row.short_description" placeholder="Resumen corto para catálogo" />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Mensual (USD)</div>
                                <Input v-model="row.monthly_price" placeholder="—" />
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Anual (USD)</div>
                                <Input v-model="row.yearly_price" placeholder="—" />
                            </div>
                        </div>

                        <div>
                            <div class="text-xs text-muted-foreground">Modelo de Facturación</div>
                            <Select v-model="row.billing_model">
                                <SelectTrigger class="mt-1 w-full">
                                    <SelectValue placeholder="Selecciona un modelo" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="flat">Flat</SelectItem>
                                    <SelectItem value="per_user">Por usuario</SelectItem>
                                        <SelectItem value="seat_block">Seat Block</SelectItem>
                                        <SelectItem value="usage">Usage</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Orden</div>
                                <Input v-model.number="row.sort_order" />
                            </div>

                            <div v-if="row.billing_model === 'seat_block'" class="col-span-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <div class="text-xs font-semibold">
                                            Rangos de usuarios
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            El precio sale del tier del ciclo y cantidad actual.
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            @click="addPricingTier(row, 'monthly')"
                                        >
                                            + Mensual
                                        </Button>

                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            @click="addPricingTier(row, 'yearly')"
                                        >
                                            + Anual
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    v-if="row.pricing_tiers.length"
                                    class="mt-3 space-y-2"
                                >
                                    <div
                                        v-for="(tier, tierIndex) in row.pricing_tiers"
                                        :key="`${row.id}-${tier.billing_cycle}-${tierIndex}`"
                                        class="rounded-lg border p-3"
                                    >
                                        <div class="grid gap-2 sm:grid-cols-6">
                                            <div>
                                                <div class="text-xs text-muted-foreground">
                                                    Ciclo
                                                </div>
                                                <Select v-model="tier.billing_cycle">
                                                    <SelectTrigger class="mt-1 w-full">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            <SelectItem value="monthly">
                                                                Mensual
                                                            </SelectItem>
                                                            <SelectItem value="yearly">
                                                                Anual
                                                            </SelectItem>
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div>
                                                <div class="text-xs text-muted-foreground">
                                                    Desde
                                                </div>
                                                <Input v-model.number="tier.min_quantity" type="number" min="1" />
                                            </div>

                                            <div>
                                                <div class="text-xs text-muted-foreground">
                                                    Hasta
                                                </div>
                                                <Input
                                                    v-model.number="tier.max_quantity"
                                                    type="number"
                                                    min="1"
                                                    placeholder="Sin límite"
                                                />
                                            </div>

                                            <div>
                                                <div class="text-xs text-muted-foreground">
                                                    Precio
                                                </div>
                                                <Input v-model="tier.price" placeholder="0.00" />
                                            </div>

                                            <div>
                                                <div class="text-xs text-muted-foreground">
                                                    Moneda
                                                </div>
                                                <Input v-model="tier.currency" maxlength="3" />
                                            </div>

                                            <div class="flex items-end gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    :variant="tier.active ? 'default' : 'outline'"
                                                    @click="tier.active = !tier.active"
                                                >
                                                    {{ tier.active ? 'Activo' : 'Inactivo' }}
                                                </Button>

                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    @click="removePricingTier(row, tierIndex)"
                                                >
                                                    Quitar
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="mt-3 rounded-lg border border-dashed p-3 text-xs text-muted-foreground"
                                >
                                    Sin tiers. Un Service seat_block no podrá activarse
                                    hasta tener un rango válido para la cantidad actual.
                                </div>
                            </div>

                            <div v-else-if="row.billing_model === 'usage'">
                                <div class="text-xs text-muted-foreground">Unidad</div>
                                <Input v-model="row.unit_name" placeholder="units" />
                            </div>
                        </div>

                        <div v-if="row.billing_model === 'usage'" class="grid grid-cols-2 gap-2">
                            <div>
                                <div class="text-xs text-muted-foreground">Incluidos</div>
                                <Input v-model="row.included_units" placeholder="0" />
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Precio excedente</div>
                                <Input v-model="row.overage_unit_price" placeholder="0.0000" />
                            </div>
                        </div>

                        <p v-if="rowErrors[ row.id ]" class="text-xs text-red-600">
                            {{ rowErrors[ row.id ] }}
                        </p>

                        <div class="col-span-2 rounded-lg border p-4 space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-medium">Bundle comercial</div>
                                    <div class="text-xs text-muted-foreground">
                                        { bundleSummary(row) }
                                    </div>
                                </div>

                                <Button
                                    size="sm"
                                    :variant="row.bundle.enabled ? 'default' : 'outline'"
                                    type="button"
                                    @click="toggleBundleEnabled(row)"
                                >
                                    { row.bundle.enabled ? 'Bundle habilitado' : 'Configurar como bundle' }
                                </Button>
                            </div>

                            <template v-if="row.bundle.enabled">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-medium">Componentes</div>
                                            <div class="text-xs text-muted-foreground">
                                                Una regla activa requiere al menos 2 componentes required.
                                            </div>
                                        </div>
                                        <Button size="sm" variant="outline" type="button" @click="addBundleItem(row)">
                                            + Componente
                                        </Button>
                                    </div>

                                    <div
                                        v-for="(item, itemIndex) in row.bundle.items"
                                        :key="`bundle-item-${row.id}-${itemIndex}`"
                                        class="grid grid-cols-12 gap-2 items-center"
                                    >
                                        <div class="col-span-7">
                                            <Select v-model="item.service_id">
                                                <SelectTrigger><SelectValue placeholder="Service" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="candidate in availableBundleCandidates(row, itemIndex)"
                                                        :key="candidate.id"
                                                        :value="candidate.id"
                                                    >
                                                        { candidate.title } · { candidate.currency }
                                                        { candidate.active ? '' : '· inactivo' }
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="col-span-3">
                                            <Button
                                                size="sm"
                                                :variant="item.required ? 'default' : 'outline'"
                                                type="button"
                                                class="w-full"
                                                @click="item.required = !item.required"
                                            >
                                                { item.required ? 'Required' : 'Opcional' }
                                            </Button>
                                        </div>
                                        <div class="col-span-2">
                                            <Button size="sm" variant="ghost" type="button" class="w-full" @click="removeBundleItem(row, itemIndex)">
                                                Quitar
                                            </Button>
                                        </div>
                                    </div>

                                    <div v-if="!row.bundle.items.length" class="text-xs text-muted-foreground">
                                        Sin componentes configurados.
                                    </div>
                                </div>

                                <div class="border-t pt-4 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-medium">Reglas comerciales</div>
                                            <div class="text-xs text-muted-foreground">
                                                El motor aplica una sola regla ganadora; no hay stacking.
                                            </div>
                                        </div>
                                        <Button size="sm" variant="outline" type="button" @click="addBundleRule(row)">
                                            + Regla
                                        </Button>
                                    </div>

                                    <div
                                        v-for="(rule, ruleIndex) in row.bundle.rules"
                                        :key="`bundle-rule-${row.id}-${rule.id ?? ruleIndex}`"
                                        class="rounded-md border p-3 space-y-3"
                                    >
                                        <div class="grid grid-cols-2 gap-2">
                                            <Input v-model="rule.code" placeholder="BUNDLE-CRM-DIGITAL" />
                                            <Input v-model="rule.name" placeholder="Nombre comercial" />
                                        </div>

                                        <div class="grid grid-cols-5 gap-2">
                                            <Select
                                                v-model="rule.discount_type"
                                                @update:model-value="normalizeBundleRuleCurrency(row, rule)"
                                            >
                                                <SelectTrigger><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="percentage">Porcentaje</SelectItem>
                                                    <SelectItem value="fixed_amount">Monto fijo</SelectItem>
                                                </SelectContent>
                                            </Select>

                                            <Input v-model="rule.discount_value" type="number" min="0" step="0.01" placeholder="10" />
                                            <Input
                                                v-model="rule.currency"
                                                maxlength="3"
                                                :disabled="rule.discount_type === 'percentage'"
                                                :placeholder="rule.discount_type === 'percentage' ? 'N/A' : row.currency"
                                            />
                                            <Input v-model="rule.priority" type="number" step="1" placeholder="0" />
                                            <Button
                                                size="sm"
                                                :variant="rule.active ? 'default' : 'outline'"
                                                type="button"
                                                @click="rule.active = !rule.active"
                                            >
                                                { rule.active ? 'Activa' : 'Inactiva' }
                                            </Button>
                                        </div>

                                        <div class="flex justify-end">
                                            <Button size="sm" variant="ghost" type="button" @click="removeBundleRule(row, ruleIndex)">
                                                Quitar regla
                                            </Button>
                                        </div>
                                    </div>

                                    <div v-if="!row.bundle.rules.length" class="text-xs text-muted-foreground">
                                        Sin reglas. Puede guardar la composición como borrador.
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <Button size="sm" variant="outline" :disabled="busyIds.has(row.id)" @click="toggleActive(row)">
                                {{ busyIds.has(row.id) ? '...' : (row.active ? 'Desactivar' : 'Activar') }}
                            </Button>

                            <Button size="sm" variant="default" :disabled="busyIds.has(row.id)" @click="saveRow(row)">
                                {{ busyIds.has(row.id) ? 'Guardando...' : 'Guardar' }}
                            </Button>

                            <Button size="sm" variant="outline" :disabled="busyIds.has(row.id)" @click="resetRow(row.id)">
                                Reiniciar
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
