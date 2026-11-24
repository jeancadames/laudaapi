<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { portalButton } from '@/components/portal/buttons'
import PortalPage from '@/components/portal/PortalPage.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useToast } from '@/components/ui/toast/use-toast'
import { useDateFormatter } from '@/composables/useDateFormatter'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const { formatDate } = useDateFormatter()

const { toast } = useToast()

type ActiveService = {
    business_managed?: boolean
    subscription_item_id?: number
    subscription_itemId?: number
    subscription_item?: { id?: number } | null

    service_id?: number
    id?: number

    status?: string
    title: string
    short_description?: string | null
    category?: { title?: string | null } | null
}

type RequestedService = {
    service_id: number
    status?: string
    title: string
    short_description?: string | null
    category?: { title?: string | null } | null
    billable?: boolean | null
}

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string }
    activation_request: null | { id: number; status: string }
    subscription: null | {
        id: number
        status: string
        billing_cycle: string
        currency: string
        trial_ends_at_human?: string | null
        period_end_human?: string | null
    }

    categories: { title: string; url: string }[]
    activation_url: string | null
    active_services: Array<ActiveService>
    requested_services: Array<RequestedService>
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Mis servicios', href: '/subscriber/services/my' },
]

// -------------------------
// helpers
// -------------------------
function normStatus(s: any) {
    return String(s ?? '').toLowerCase().trim()
}

function asNumber(v: any): number {
    const n = Number(v)
    return Number.isFinite(n) ? n : NaN
}

function text(s: any) {
    return String(s ?? '').toLowerCase()
}

function getSubscriptionItemId(s: any): number {
    const raw = s?.subscription_item_id ?? s?.subscription_itemId ?? s?.subscription_item?.id
    return asNumber(raw)
}

function isCancelableActiveStatus(status: any) {
    const st = normStatus(status)
    return st === 'active' || st === 'trialing' || st === 'trial'
}

function badgeVariant(status: any) {
    const s = normStatus(status)
    if (s === 'active' || s === 'trialing' || s === 'trial') return 'secondary'
    if (s === 'pending' || s === 'pending_payment') return 'secondary'
    if (s === 'cancelled') return 'secondary'
    return 'destructive'
}

function label(status: any) {
    const s = normStatus(status)
    if (s === 'trialing' || s === 'trial') return 'En prueba'
    if (s === 'active') return 'Activo'
    if (s === 'pending') return 'Solicitado'
    if (s === 'pending_payment') return 'Pendiente de pago'
    if (s === 'cancelled') return 'Cancelado'
    if (status === 'business_review') return 'Revisar cobertura Business'
    return 'Sin estado'
}

const activeServiceIds = computed(() => {
    const ids = new Set<number>()
    for (const s of props.active_services ?? []) {
        const id = asNumber((s as any).service_id)
        if (Number.isFinite(id)) ids.add(id)
    }
    return ids
})

function isAlreadyActive(serviceId: any) {
    const id = asNumber(serviceId)
    return Number.isFinite(id) && activeServiceIds.value.has(id)
}

const activeCount = computed(() => props.active_services?.length ?? 0)

// -------------------------
// Flash toasts
// -------------------------
// -------------------------
// Rules: activation (solo pending)
// -------------------------
function canActivatePending(r: RequestedService) {
    if (!props.activation_request) return false

    const st = normStatus(r?.status)
    if (st !== 'pending') return false

    const serviceId = asNumber(r?.service_id)
    if (!Number.isFinite(serviceId)) return false
    if (activeServiceIds.value.has(serviceId)) return false

    return true
}

function pendingDisabledReason(r: RequestedService): string | null {
    if (!props.activation_request) return 'Primero activa tu cuenta.'
    const st = normStatus(r?.status)
    if (st !== 'pending') return `Estado: ${label(r?.status)}.`
    if (isAlreadyActive(r.service_id)) return 'Ya está activo.'
    return null
}

// -------------------------
// Rules: cancel
// -------------------------
function canCancelActive(s: ActiveService) {
    if (s.business_managed) return false
    if (!isCancelableActiveStatus(s?.status)) return false
    const itemId = getSubscriptionItemId(s)
    return Number.isFinite(itemId) && itemId > 0
}

function cancelDisabledReason(s: ActiveService): string | null {
    if (s.business_managed) return 'Incluido en Business. Los cambios requieren revisión del contrato.'
    if (!isCancelableActiveStatus(s?.status)) {
        return 'No se puede cancelar en este momento.'
    }
    const itemId = getSubscriptionItemId(s)
    if (!Number.isFinite(itemId) || itemId <= 0) {
        return 'No se puede cancelar desde aquí. Escríbenos a soporte.'
    }
    return null
}

// -------------------------
// Actions
// -------------------------
type BillingCycle = 'monthly' | 'yearly'

type BillingOption = {
    cycle: BillingCycle
    label: string
    available: boolean
    amount_due?: number | null
    currency?: string | null
    billing_model?: string | null
    quantity?: number | null
    unit_price?: number | null
    tier_id?: number | null
    tier_min_quantity?: number | null
    tier_max_quantity?: number | null
    service_plan_id?: number | null
    subscription_locked?: boolean
    active_subscription_id?: number | null
    reason?: string | null
}

type CommercialPlan = {
    id: number
    code: string
    name: string
    description?: string | null
    currency: string
    billing_model: string
    features?: Record<string, boolean>
    limits?: Record<string, number | null>
    is_featured?: boolean
    is_free?: boolean
    activation_available?: boolean
    activation_reason?: string | null
    billing_options?: Partial<Record<BillingCycle, BillingOption>>
}

const billingCycleByService =
    ref<Record<number, BillingCycle>>({})

const planByService =
    ref<Record<number, number>>({})

function commercialPlans(r: any): CommercialPlan[] {
    return Array.isArray(r?.commercial_plans)
        ? r.commercial_plans
        : []
}

function hasCommercialPlans(r: any): boolean {
    return commercialPlans(r).length > 0
}

function selectedPlan(r: any): CommercialPlan | null {
    const plans = commercialPlans(r)

    if (plans.length === 0) return null

    const serviceId = Number(r?.service_id)
    const explicit =
        Number.isFinite(serviceId)
            ? planByService.value[serviceId]
            : undefined

    if (explicit) {
        const found = plans.find(
            (plan) =>
                Number(plan.id) === Number(explicit),
        )

        if (found) return found
    }

    return (
        plans.find((plan) => plan.is_featured)
        ?? plans.find((plan) => !plan.is_free)
        ?? plans[0]
        ?? null
    )
}

function selectedPlanId(r: any): number | null {
    const plan = selectedPlan(r)

    return plan
        ? Number(plan.id)
        : null
}

function setSelectedPlan(
    r: any,
    planId: number,
) {
    const serviceId = Number(r?.service_id)

    if (!Number.isFinite(serviceId)) return

    const plan = commercialPlans(r).find(
        (candidate) =>
            Number(candidate.id) === Number(planId),
    )

    if (!plan) return

    planByService.value = {
        ...planByService.value,
        [serviceId]: Number(plan.id),
    }

    const next = {
        ...billingCycleByService.value,
    }

    delete next[serviceId]

    billingCycleByService.value = next
}

function billingOptions(r: any): BillingOption[] {
    const plan = selectedPlan(r)

    const raw =
        plan?.billing_options
        ?? r?.billing_options
        ?? {}

    return [raw.monthly, raw.yearly].filter(
        (option): option is BillingOption => !!option,
    )
}

function billingOption(
    r: any,
    cycle: BillingCycle,
): BillingOption | null {
    return (
        billingOptions(r).find(
            (option) => option.cycle === cycle,
        ) ?? null
    )
}

function selectedBillingCycle(
    r: any,
): BillingCycle | null {
    const serviceId = Number(r?.service_id)

    if (!Number.isFinite(serviceId)) return null

    const explicit =
        billingCycleByService.value[serviceId]

    if (
        explicit
        && billingOption(r, explicit)?.available
    ) {
        return explicit
    }

    /*
     * ServicePlan => ciclo por SubscriptionItem.
     * No hereda el lock legacy de Subscription.
     */
    if (hasCommercialPlans(r)) {
        if (billingOption(r, 'monthly')?.available) {
            return 'monthly'
        }

        if (billingOption(r, 'yearly')?.available) {
            return 'yearly'
        }

        return null
    }

    const currentCycle =
        String(
            props.subscription?.status ?? '',
        ).toLowerCase() === 'active'
            ? String(
                  props.subscription?.billing_cycle ?? '',
              ).toLowerCase()
            : ''

    if (
        (
            currentCycle === 'monthly'
            || currentCycle === 'yearly'
        )
        && billingOption(
            r,
            currentCycle as BillingCycle,
        )?.available
    ) {
        return currentCycle as BillingCycle
    }

    if (billingOption(r, 'monthly')?.available) {
        return 'monthly'
    }

    if (billingOption(r, 'yearly')?.available) {
        return 'yearly'
    }

    return null
}

function setBillingCycle(
    r: any,
    cycle: BillingCycle,
) {
    const option = billingOption(r, cycle)

    if (!option?.available) return

    const serviceId = Number(r?.service_id)

    if (!Number.isFinite(serviceId)) return

    billingCycleByService.value = {
        ...billingCycleByService.value,
        [serviceId]: cycle,
    }
}

function billingCycleLabel(
    cycle: BillingCycle,
): string {
    return cycle === 'yearly'
        ? 'Anual'
        : 'Mensual'
}

function formatBillingAmount(
    option: BillingOption,
): string {
    const amount = Number(
        option.amount_due ?? 0,
    )

    const currency =
        String(
            option.currency ?? 'DOP',
        ).toUpperCase()

    if (!Number.isFinite(amount)) return '—'
    if (amount === 0) return 'Gratis'

    try {
        return new Intl.NumberFormat(
            'es-DO',
            {
                style: 'currency',
                currency,
                maximumFractionDigits: 2,
            },
        ).format(amount)
    } catch {
        return `${amount.toFixed(2)} ${currency}`
    }
}

function planPriceSummary(
    plan: CommercialPlan,
): string {
    if (plan.is_free) return 'Gratis'

    const parts: string[] = []

    if (plan.billing_options?.monthly?.available) {
        parts.push(
            `${formatBillingAmount(
                plan.billing_options.monthly,
            )}/mes`,
        )
    }

    if (plan.billing_options?.yearly?.available) {
        parts.push(
            `${formatBillingAmount(
                plan.billing_options.yearly,
            )}/año`,
        )
    }

    return parts.length > 0
        ? parts.join(' · ')
        : 'Precio no disponible'
}

function selectedPlanIsFree(r: any): boolean {
    return Boolean(
        selectedPlan(r)?.is_free,
    )
}

function activationCtaLabel(r: any): string {
    if (selectedPlanIsFree(r)) {
        return 'Starter gratis · Próximamente'
    }

    if (hasCommercialPlans(r)) {
        return 'Continuar al pago'
    }

    /*
     * Compatibilidad UX legacy S10-C:
     * servicios sin ServicePlan conservan su CTA anterior.
     */
    return 'Solicitar activación'
}

function activateRequested(
    serviceId: number,
    billingCycle: BillingCycle | null,
    servicePlanId: number | null = null,
) {
    if (!props.activation_request) {
        toast({
            title: 'Requiere solicitud de activación',
            description:
                'Debes tener una solicitud antes de activar servicios.',
            variant: 'destructive',
        })

        return
    }

    if (!billingCycle) {
        toast({
            title: 'Selecciona un ciclo',
            description:
                'Elige un ciclo disponible antes de continuar.',
            variant: 'destructive',
        })

        return
    }

    router.post(
        '/subscriber/services/activate',
        {
            service_id: serviceId,
            service_plan_id: servicePlanId,
            mode: 'billed',
            billing_cycle: billingCycle,
        },
        {
            preserveScroll: true,
        },
    )
}

/** Aplicación cuya cancelación se está confirmando (la confirmación aparece en su tarjeta). */
const cancellingId = ref<number | null>(null)

function cancelSubscriptionItemByService(s: ActiveService) {
    const itemId = getSubscriptionItemId(s)

    if (Number.isFinite(itemId) && itemId > 0 && cancellingId.value !== itemId) {
        cancellingId.value = itemId
        return
    }
    cancellingId.value = null

    if (!Number.isFinite(itemId) || itemId <= 0) {
        toast({
            title: 'No se puede cancelar',
            description: 'No pudimos identificar esta aplicación. Escríbenos a soporte.',
            variant: 'destructive',
        })
        return
    }

    router.post('/subscriber/services/cancel', { subscription_item_id: itemId }, { preserveScroll: true })
}

// -------------------------
// Filters
// -------------------------
type FilterKey = 'all' | 'active' | 'pending' | 'pending_payment' | 'cancelled'
const filter = ref<FilterKey>('all')
const q = ref('')

function matchText(item: { title?: string; short_description?: string | null; category?: { title?: string | null } | null }) {
    const term = q.value.trim().toLowerCase()
    if (!term) return true
    const hay = [
        item.title,
        item.short_description,
        item.category?.title,
    ].map(text).join(' ')
    return hay.includes(term)
}

// Requested lists (raw)
const requestedPendingRaw = computed(() =>
    (props.requested_services ?? []).filter((r) => normStatus(r.status) === 'pending')
)
const requestedPendingPaymentRaw = computed(() =>
    (props.requested_services ?? []).filter((r) => normStatus(r.status) === 'pending_payment')
)
const requestedCancelledRaw = computed(() =>
    (props.requested_services ?? []).filter((r) => normStatus(r.status) === 'cancelled')
)

// Counts (raw)
const requestedPendingCount = computed(() => requestedPendingRaw.value.length)
const requestedPendingPaymentCount = computed(() => requestedPendingPaymentRaw.value.length)
const cancelledRequestedCount = computed(() => requestedCancelledRaw.value.length)

// Filtered lists
const activeFiltered = computed(() => (props.active_services ?? []).filter(matchText))
const requestedPending = computed(() => requestedPendingRaw.value.filter(matchText))
const requestedPendingPayment = computed(() => requestedPendingPaymentRaw.value.filter(matchText))
const requestedCancelled = computed(() => requestedCancelledRaw.value.filter(matchText))

// Which sections to show based on filter
const showActive = computed(() => filter.value === 'all' || filter.value === 'active')
const showPending = computed(() => filter.value === 'all' || filter.value === 'pending')
const showPendingPayment = computed(() => filter.value === 'all' || filter.value === 'pending_payment')
const showCancelled = computed(() => filter.value === 'all' || filter.value === 'cancelled')
</script>

<template>

    <Head title="Mis Servicios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company.name"
            title="Mis servicios"
            description="Las aplicaciones de tu suscripción y las que pediste activar."
        >
            <template v-if="props.categories.length || props.activation_url" #actions>
                <Link
                    v-for="category in props.categories"
                    :key="category.url"
                    :href="category.url"
                    :class="portalButton.secondary"
                >{{ category.title }}</Link>
                <Link v-if="props.activation_url" :href="props.activation_url" :class="portalButton.secondary">Ver mi activación</Link>
            </template>

            <p
                v-if="!props.activation_request && activeCount === 0"
                role="status"
                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
            >
                Para activar aplicaciones, primero activa tu cuenta.
            </p>
            <p
                v-else-if="props.subscription?.trial_ends_at_human"
                class="text-sm text-slate-600 dark:text-slate-400"
            >
                Tu período de prueba termina el {{ formatDate(props.subscription.trial_ends_at_human) }}.
            </p>

            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
                    <button
                        v-for="[key, text, count] in ([
                            ['all', 'Todas', null],
                            ['active', 'Activas', activeCount],
                            ['pending', 'Solicitadas', requestedPendingCount],
                            ['pending_payment', 'Por pagar', requestedPendingPaymentCount],
                            ['cancelled', 'Canceladas', cancelledRequestedCount],
                        ] as const)"
                        :key="key"
                        type="button"
                        class="min-h-10 rounded-full border px-4 text-sm font-medium transition-colors"
                        :class="filter === key ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200'"
                        :aria-pressed="filter === key"
                        @click="filter = key"
                    >
                        {{ text }}<span v-if="count" class="ml-1.5 tabular-nums opacity-70">{{ count }}</span>
                    </button>
                </div>
                <label class="w-full md:w-64">
                    <span class="sr-only">Buscar aplicación</span>
                    <input
                        v-model="q"
                        type="search"
                        class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        placeholder="Buscar aplicación"
                    />
                </label>
            </div>

            <SectionCard v-if="showActive" title="Activas" description="Las aplicaciones que puedes usar ahora.">
                <div v-if="activeFiltered.length === 0" class="text-sm text-muted-foreground">
                    No hay servicios activos para este filtro.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="s in activeFiltered" :key="`a-${getSubscriptionItemId(s) || s.service_id || s.id}`" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold break-words">{{ s.title }}</div>

                                <div v-if="s.short_description" class="text-sm text-muted-foreground line-clamp-2 mt-1">
                                    {{ s.short_description }}
                                </div>

                                <div v-if="s.category?.title" class="text-xs text-muted-foreground mt-2">
                                    {{ s.category.title }}
                                </div>
                            </div>

                            <Badge :variant="badgeVariant(s.status)">{{ label(s.status) }}</Badge>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <Button v-if="!s.business_managed && cancellingId !== getSubscriptionItemId(s)" size="sm" variant="outline" :disabled="!canCancelActive(s)" @click="cancelSubscriptionItemByService(s)">
                                Cancelar
                            </Button>
                            <div
                                v-else-if="cancellingId === getSubscriptionItemId(s)"
                                role="alert"
                                class="w-full space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-900/60 dark:bg-amber-950/30"
                            >
                                <p>¿Cancelar {{ s.title }}? Dejarás de poder usarla.</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" :class="portalButton.dark" @click="cancelSubscriptionItemByService(s)">Sí, cancelar</button>
                                    <button type="button" :class="portalButton.link" @click="cancellingId = null">Mantenerla</button>
                                </div>
                            </div>

                            <div v-if="!canCancelActive(s)" class="text-xs text-muted-foreground">
                                {{ cancelDisabledReason(s) }}
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <SectionCard v-if="showPending && (filter !== 'all' || requestedPending.length)" title="Solicitadas" description="Elige el plan y la forma de pago para activarlas.">
                <div v-if="requestedPending.length === 0" class="text-sm text-muted-foreground">
                    No hay solicitudes pendientes para este filtro.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="r in requestedPending" :key="`r-p-${r.service_id}`" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold break-words">{{ r.title }}</div>

                                <div v-if="r.short_description" class="text-sm text-muted-foreground line-clamp-2 mt-1">
                                    {{ r.short_description }}
                                </div>

                                <div v-if="r.category?.title" class="text-xs text-muted-foreground mt-2">
                                    {{ r.category.title }}
                                </div>
                            </div>

                            <Badge :variant="badgeVariant(r.status)">{{ label(r.status) }}</Badge>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">

                            <div class="mt-3 w-full space-y-3">
                                <div
                                    v-if="hasCommercialPlans(r)"
                                    class="space-y-2"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-medium text-foreground">
                                            Plan
                                        </p>
                                        <span class="text-[11px] text-muted-foreground">
                                            Precio y límites definidos por la solución
                                        </span>
                                    </div>

                                    <div class="grid gap-2 lg:grid-cols-3">
                                        <button
                                            v-for="plan in commercialPlans(r)"
                                            :key="`${r.service_id}-plan-${plan.id}`"
                                            type="button"
                                            class="rounded-lg border p-3 text-left transition"
                                            :class="
                                                selectedPlanId(r) === Number(plan.id)
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border hover:bg-muted/40'
                                            "
                                            @click="setSelectedPlan(r, Number(plan.id))"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <div>
                                                    <p class="text-sm font-semibold text-foreground">
                                                        {{ plan.name }}
                                                    </p>
                                                    <p
                                                        v-if="plan.description"
                                                        class="mt-1 text-[11px] leading-relaxed text-muted-foreground"
                                                    >
                                                        {{ plan.description }}
                                                    </p>
                                                </div>

                                                <Badge
                                                    v-if="plan.is_featured"
                                                    variant="secondary"
                                                >
                                                    Recomendado
                                                </Badge>
                                            </div>

                                            <div class="mt-2 text-sm font-semibold text-foreground">
                                                {{ planPriceSummary(plan) }}
                                            </div>

                                            <div
                                                v-if="plan.is_free && !plan.activation_available"
                                                class="mt-2 text-[11px] leading-relaxed text-muted-foreground"
                                            >
                                                {{ plan.activation_reason }}
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <p class="text-xs font-medium text-foreground">
                                            Ciclo de facturación
                                        </p>
                                        <span
                                            v-if="hasCommercialPlans(r)"
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Ciclo independiente para esta solución
                                        </span>
                                        <span
                                            v-else-if="props.subscription?.status === 'active'"
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Tu suscripción actual define el ciclo
                                        </span>
                                    </div>

                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <button
                                            v-for="option in billingOptions(r)"
                                            :key="`${r.service_id}-${option.cycle}`"
                                            type="button"
                                            class="rounded-lg border p-3 text-left transition disabled:cursor-not-allowed disabled:opacity-50"
                                            :class="
                                                selectedBillingCycle(r) === option.cycle
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border hover:bg-muted/40'
                                            "
                                            :disabled="!option.available"
                                            @click="setBillingCycle(r, option.cycle)"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-sm font-medium">
                                                    {{ billingCycleLabel(option.cycle) }}
                                                </span>
                                                <Badge
                                                    v-if="selectedBillingCycle(r) === option.cycle"
                                                    variant="secondary"
                                                >
                                                    Seleccionado
                                                </Badge>
                                            </div>

                                            <div
                                                v-if="option.available"
                                                class="mt-1 text-sm font-semibold text-foreground"
                                            >
                                                {{ formatBillingAmount(option) }}
                                            </div>

                                            <div
                                                v-if="option.available && Number(option.quantity ?? 0) > 1"
                                                class="mt-1 text-[11px] text-muted-foreground"
                                            >
                                                Cantidad: {{ option.quantity }}
                                            </div>

                                            <div
                                                v-if="!option.available"
                                                class="mt-1 text-[11px] leading-relaxed text-muted-foreground"
                                            >
                                                {{ option.reason || 'No disponible para este ciclo.' }}
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <Button
                                    size="sm"
                                    :disabled="
                                        !canActivatePending(r)
                                        || !selectedBillingCycle(r)
                                        || selectedPlanIsFree(r)
                                    "
                                    class="bg-emerald-600 text-white hover:bg-emerald-700 disabled:bg-emerald-600/40 disabled:text-white/70"
                                    @click="
                                        activateRequested(
                                            Number(r.service_id),
                                            selectedBillingCycle(r),
                                            selectedPlanId(r),
                                        )
                                    "
                                >
                                    {{ activationCtaLabel(r) }}
                                </Button>
                            </div>

                            <div v-if="!canActivatePending(r)" class="text-xs text-muted-foreground">
                                {{ pendingDisabledReason(r) }}
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <SectionCard v-if="showPendingPayment && (filter !== 'all' || requestedPendingPayment.length)" title="Por pagar" description="Se activan cuando se confirme el pago de su factura.">
                <div v-if="requestedPendingPayment.length === 0" class="text-sm text-muted-foreground">
                    No hay pendientes de pago para este filtro.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="r in requestedPendingPayment" :key="`r-pp-${r.service_id}`" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold break-words">{{ r.title }}</div>

                                <div v-if="r.short_description" class="text-sm text-muted-foreground line-clamp-2 mt-1">
                                    {{ r.short_description }}
                                </div>

                                <div v-if="r.category?.title" class="text-xs text-muted-foreground mt-2">
                                    {{ r.category.title }}
                                </div>
                            </div>

                            <Badge :variant="badgeVariant(r.status)">{{ label(r.status) }}</Badge>
                        </div>

                        <div class="mt-3 text-xs text-muted-foreground">
                            Esta aplicación se activa cuando se confirme el pago.
                            <Link href="/subscriber/invoices" class="font-medium text-slate-950 underline underline-offset-4 dark:text-white">Ver mis facturas</Link>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <SectionCard v-if="showCancelled && (filter !== 'all' || requestedCancelled.length)" title="Canceladas" description="Solicitudes que ya no siguen.">
                <div v-if="requestedCancelled.length === 0" class="text-sm text-muted-foreground">
                    No hay cancelados para este filtro.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="r in requestedCancelled" :key="`r-c-${r.service_id}`" class="rounded-2xl border border-slate-200 bg-white p-4 opacity-80 dark:border-slate-800 dark:bg-slate-950">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold break-words">{{ r.title }}</div>

                                <div v-if="r.short_description" class="text-sm text-muted-foreground line-clamp-2 mt-1">
                                    {{ r.short_description }}
                                </div>

                                <div v-if="r.category?.title" class="text-xs text-muted-foreground mt-2">
                                    {{ r.category.title }}
                                </div>
                            </div>

                            <Badge :variant="badgeVariant(r.status)">{{ label(r.status) }}</Badge>
                        </div>

                        <div class="mt-3 text-xs text-muted-foreground">
                            Esta solicitud está cancelada.
                        </div>
                    </div>
                </div>
            </SectionCard>
        </PortalPage>
    </AppLayout>
</template>
