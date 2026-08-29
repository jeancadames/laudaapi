<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    CalendarDays,
    Check,
    CircleDollarSign,
    MessageCircle,
    ShieldCheck,
    Sparkles,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type BillingCycle = 'monthly' | 'yearly';

type BillingOption = {
    cycle: BillingCycle;
    label: string;
    available: boolean;
    amount_due: number | null;
    currency: string | null;
    billing_model?: string | null;
    quantity?: number | null;
    unit_price?: number | null;
    service_plan_id: number;
    reason?: string | null;
};

type Plan = {
    id: number;
    code: string;
    name: string;
    description?: string | null;
    currency: string;
    billing_model: string;
    features: Record<string, boolean>;
    limits: Record<string, number | null>;
    is_featured: boolean;
    is_free: boolean;
    activation_available: boolean;
    activation_reason?: string | null;
    billing_options: Record<BillingCycle, BillingOption>;
};

type ActiveEntitlement = {
    subscription_item_id: number;
    status: string;
    service_plan_id: number | null;
    service_plan_name?: string | null;
    billing_cycle?: string | null;
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
        currency: string;
    };
    service: {
        id: number;
        service_key: string;
        slug: string;
        title: string;
        short_description?: string | null;
        description?: string | null;
        billable: boolean;
        active: boolean;
    };
    plans: Plan[];
    active_entitlement: ActiveEntitlement | null;
    store: {
        starter_activation_deferred: boolean;
        plan_change_deferred: boolean;
        checkout_requires_activation_request: boolean;
    };
}>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mi ecosistema', href: '/app' },
    { title: 'App Store', href: '/app#app-store' },
    { title: props.service.title, href: `/subscriber/apps/${props.service.service_key}` },
];

const selectedPlanId = ref<number>(
    props.plans.find((plan) => plan.is_featured)?.id
        ?? props.plans.find((plan) => !plan.is_free)?.id
        ?? props.plans[0]?.id
        ?? 0,
);

const billingCycle = ref<BillingCycle>('monthly');
const processing = ref(false);
const localError = ref<string | null>(null);

const flashError = computed(
    () => (page.props.flash as any)?.error ?? null,
);

watch(
    flashError,
    (value) => {
        if (value) localError.value = String(value);
    },
    { immediate: true },
);

const selectedPlan = computed<Plan | null>(
    () =>
        props.plans.find(
            (plan) => Number(plan.id) === Number(selectedPlanId.value),
        ) ?? null,
);

const availableCycles = computed<BillingCycle[]>(() => {
    if (!selectedPlan.value) return [];

    return (['monthly', 'yearly'] as BillingCycle[])
        .filter(
            (cycle) =>
                Boolean(
                    selectedPlan.value?.billing_options?.[cycle]?.available,
                ),
        );
});

watch(
    selectedPlan,
    () => {
        if (
            availableCycles.value.length > 0
            && !availableCycles.value.includes(billingCycle.value)
        ) {
            billingCycle.value = availableCycles.value[0];
        }
    },
    { immediate: true },
);

const selectedOption = computed<BillingOption | null>(
    () =>
        selectedPlan.value?.billing_options?.[billingCycle.value]
        ?? null,
);

const hasActiveEntitlement = computed(
    () => props.active_entitlement !== null,
);

const canCheckout = computed(() => {
    if (processing.value) return false;
    if (hasActiveEntitlement.value) return false;
    if (!selectedPlan.value) return false;
    if (selectedPlan.value.is_free) return false;
    if (!selectedPlan.value.activation_available) return false;
    return Boolean(selectedOption.value?.available);
});

const formatMoney = (
    value: number | null | undefined,
    currency = 'DOP',
) => {
    if (value === null || value === undefined) return '—';

    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: currency || 'DOP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value));
};

const limitLabel = (key: string, value: number | null) => {
    const labels: Record<string, string> = {
        branches: 'Sucursales',
        users: 'Usuarios',
        products: 'Productos',
        warehouses: 'Almacenes',
        ecfs: 'e-CF / mes',
        webhooks: 'Webhooks',
        leads: 'Leads',
        accounts: 'Cuentas sociales',
        social_accounts: 'Cuentas sociales',
        posts: 'Publicaciones / mes',
        posts_per_month: 'Publicaciones / mes',
    };

    return `${labels[key] ?? key.replaceAll('_', ' ')}: ${value ?? 'Sin límite'}`;
};

const featureLabel = (key: string) => {
    const labels: Record<string, string> = {
        basic: 'Herramientas esenciales',
        analytics: 'Analítica',
        scheduling: 'Programación',
        ai: 'Asistencia con IA',
        approval_workflows: 'Flujos de aprobación',
        advanced_analytics: 'Analítica avanzada',
    };

    return labels[key] ?? key.replaceAll('_', ' ');
};

const visibleFeatures = (plan: Plan) =>
    Object.entries(plan.features ?? {})
        .filter(([, enabled]) => Boolean(enabled))
        .map(([key]) => featureLabel(key));

const visibleLimits = (plan: Plan) =>
    Object.entries(plan.limits ?? {})
        .map(([key, value]) => limitLabel(key, value));

const selectPlan = (plan: Plan) => {
    selectedPlanId.value = plan.id;
    localError.value = null;
};

const checkout = () => {
    if (!canCheckout.value || !selectedPlan.value) return;

    processing.value = true;
    localError.value = null;

    router.post(
        `/subscriber/apps/${props.service.service_key}/checkout`,
        {
            service_plan_id: selectedPlan.value.id,
            billing_cycle: billingCycle.value,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                const first = Object.values(errors)[0];
                localError.value = first ? String(first) : 'No se pudo iniciar el checkout.';
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="`${service.title} · App Store`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-[#FAFAF8]">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8">
                <a
                    href="/app#app-store"
                    class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-950"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Volver al App Store
                </a>

                <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm">
                    <div class="grid gap-0 lg:grid-cols-[1.15fr_0.85fr]">
                        <div class="p-6 sm:p-8 lg:p-10">
                            <div class="flex items-center gap-4">
                                <div class="grid h-14 w-14 place-items-center rounded-2xl bg-[#F5333C]/10 text-[#F5333C]">
                                    <MessageCircle class="h-7 w-7" />
                                </div>

                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-[#F5333C]">
                                        LAUDAAPI App Store
                                    </p>
                                    <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                                        {{ service.title }}
                                    </h1>
                                </div>
                            </div>

                            <p class="mt-6 max-w-2xl text-base leading-7 text-slate-600">
                                {{
                                    service.short_description
                                        || service.description
                                        || 'Gestione su presencia social desde una solución conectada a su ecosistema LAUDAAPI.'
                                }}
                            </p>

                            <div class="mt-6 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700">
                                    <ShieldCheck class="h-3.5 w-3.5" />
                                    Contratación centralizada
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700">
                                    <CircleDollarSign class="h-3.5 w-3.5" />
                                    Facturación LAUDAAPI
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700">
                                    <BadgeCheck class="h-3.5 w-3.5" />
                                    Acceso por entitlement
                                </span>
                            </div>
                        </div>

                        <div class="bg-slate-950 p-6 text-white sm:p-8 lg:p-10">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-white/50">
                                Empresa
                            </p>
                            <p class="mt-2 text-xl font-black">
                                {{ company.name }}
                            </p>

                            <div
                                v-if="active_entitlement"
                                class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-4"
                            >
                                <div class="flex items-start gap-3">
                                    <BadgeCheck class="mt-0.5 h-5 w-5 shrink-0 text-emerald-300" />
                                    <div>
                                        <p class="font-black text-emerald-100">
                                            Social ya está activo
                                        </p>
                                        <p class="mt-1 text-sm leading-6 text-white/65">
                                            Plan:
                                            {{ active_entitlement.service_plan_name || 'Plan contratado' }}
                                            <span v-if="active_entitlement.billing_cycle">
                                                · {{ active_entitlement.billing_cycle === 'yearly' ? 'Anual' : 'Mensual' }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4"
                            >
                                <p class="text-sm font-bold text-white">
                                    Elija el plan y ciclo que necesita.
                                </p>
                                <p class="mt-1 text-sm leading-6 text-white/55">
                                    El acceso se habilita después de confirmar el pago. Cada solución mantiene su propia operación.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-[#F5333C]">
                                Planes
                            </p>
                            <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">
                                Seleccione cómo quiere usar {{ service.title }}
                            </h2>
                        </div>

                        <div
                            v-if="!hasActiveEntitlement && availableCycles.length > 0"
                            class="inline-flex w-fit rounded-2xl border border-slate-200 bg-white p-1 shadow-sm"
                        >
                            <button
                                v-if="availableCycles.includes('monthly')"
                                type="button"
                                class="rounded-xl px-4 py-2 text-sm font-bold transition"
                                :class="
                                    billingCycle === 'monthly'
                                        ? 'bg-slate-950 text-white'
                                        : 'text-slate-500 hover:text-slate-950'
                                "
                                @click="billingCycle = 'monthly'"
                            >
                                Mensual
                            </button>
                            <button
                                v-if="availableCycles.includes('yearly')"
                                type="button"
                                class="rounded-xl px-4 py-2 text-sm font-bold transition"
                                :class="
                                    billingCycle === 'yearly'
                                        ? 'bg-slate-950 text-white'
                                        : 'text-slate-500 hover:text-slate-950'
                                "
                                @click="billingCycle = 'yearly'"
                            >
                                Anual
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-3">
                        <button
                            v-for="plan in plans"
                            :key="plan.id"
                            type="button"
                            class="relative flex min-h-[360px] flex-col rounded-[2rem] border bg-white p-6 text-left shadow-sm transition"
                            :class="[
                                selectedPlanId === plan.id
                                    ? 'border-slate-950 ring-2 ring-slate-950/5'
                                    : 'border-slate-200 hover:border-slate-300',
                                plan.is_featured ? 'lg:-translate-y-2' : '',
                            ]"
                            @click="selectPlan(plan)"
                        >
                            <span
                                v-if="plan.is_featured"
                                class="absolute right-5 top-5 inline-flex items-center gap-1.5 rounded-full bg-[#F5333C] px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-white"
                            >
                                <Sparkles class="h-3 w-3" />
                                Recomendado
                            </span>

                            <div class="pr-24">
                                <p class="text-xl font-black text-slate-950">
                                    {{ plan.name }}
                                </p>
                                <p class="mt-2 min-h-12 text-sm leading-6 text-slate-500">
                                    {{ plan.description || `Plan de ${service.title} LAUDAAPI.` }}
                                </p>
                            </div>

                            <div class="mt-6">
                                <template v-if="plan.is_free">
                                    <p class="text-3xl font-black tracking-tight text-slate-950">
                                        Gratis
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-400">
                                        Starter gratis · Próximamente
                                    </p>
                                </template>

                                <template v-else>
                                    <p class="text-3xl font-black tracking-tight text-slate-950">
                                        {{
                                            formatMoney(
                                                plan.billing_options[billingCycle]?.amount_due,
                                                plan.billing_options[billingCycle]?.currency || plan.currency,
                                            )
                                        }}
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-400">
                                        {{ billingCycle === 'yearly' ? 'por año' : 'por mes' }}
                                    </p>
                                </template>
                            </div>

                            <div class="mt-6 space-y-2">
                                <div
                                    v-for="limit in visibleLimits(plan)"
                                    :key="limit"
                                    class="flex items-start gap-2 text-sm text-slate-600"
                                >
                                    <Users class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                    <span>{{ limit }}</span>
                                </div>

                                <div
                                    v-for="feature in visibleFeatures(plan).slice(0, 5)"
                                    :key="feature"
                                    class="flex items-start gap-2 text-sm text-slate-600"
                                >
                                    <Check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                                    <span>{{ feature }}</span>
                                </div>
                            </div>

                            <div class="mt-auto pt-6">
                                <div
                                    class="flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold"
                                    :class="
                                        selectedPlanId === plan.id
                                            ? 'bg-slate-950 text-white'
                                            : 'bg-slate-100 text-slate-500'
                                    "
                                >
                                    <span>
                                        {{ selectedPlanId === plan.id ? 'Seleccionado' : 'Seleccionar' }}
                                    </span>
                                    <Check v-if="selectedPlanId === plan.id" class="h-4 w-4" />
                                </div>
                            </div>
                        </button>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div
                        v-if="localError"
                        class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold leading-6 text-red-700"
                        role="alert"
                    >
                        {{ localError }}
                    </div>

                    <div
                        v-if="hasActiveEntitlement"
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-black text-slate-950">
                                Ya tiene Social en Mis Apps
                            </p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                Upgrade, downgrade y Starter se habilitarán con el ciclo seguro de cambio de plan.
                            </p>
                        </div>

                        <a
                            href="/app"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-black"
                        >
                            Volver a Mis Apps
                            <ArrowRight class="h-4 w-4" />
                        </a>
                    </div>

                    <div
                        v-else
                        class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-black text-slate-950">
                                    {{ selectedPlan?.name || 'Seleccione un plan' }}
                                </p>
                                <span
                                    v-if="selectedPlan?.is_featured"
                                    class="rounded-full bg-[#F5333C]/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-[#F5333C]"
                                >
                                    Recomendado
                                </span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <CalendarDays class="h-4 w-4" />
                                    {{ billingCycle === 'yearly' ? 'Facturación anual' : 'Facturación mensual' }}
                                </span>
                                <span v-if="selectedOption?.available" class="font-bold text-slate-950">
                                    {{
                                        formatMoney(
                                            selectedOption.amount_due,
                                            selectedOption.currency || selectedPlan?.currency || 'DOP',
                                        )
                                    }}
                                </span>
                            </div>

                            <p
                                v-if="selectedPlan?.is_free"
                                class="mt-2 text-sm font-semibold text-amber-600"
                            >
                                {{ selectedPlan.activation_reason }}
                            </p>
                            <p
                                v-else-if="selectedOption && !selectedOption.available"
                                class="mt-2 text-sm font-semibold text-amber-600"
                            >
                                {{ selectedOption.reason || 'Este ciclo no está disponible.' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex min-w-52 items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-black transition"
                            :class="
                                canCheckout
                                    ? 'bg-[#F5333C] text-white hover:bg-[#DF2932]'
                                    : 'cursor-not-allowed bg-slate-100 text-slate-400'
                            "
                            :disabled="!canCheckout"
                            @click="checkout"
                        >
                            <template v-if="processing">
                                Preparando…
                            </template>
                            <template v-else-if="selectedPlan?.is_free">
                                Starter gratis · Próximamente
                            </template>
                            <template v-else>
                                Continuar al pago
                                <ArrowRight class="h-4 w-4" />
                            </template>
                        </button>
                    </div>
                </section>

                <div class="flex items-start gap-3 rounded-2xl bg-slate-950 px-5 py-4 text-white">
                    <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-white/70" />
                    <p class="text-sm leading-6 text-white/60">
                        LAUDAAPI centraliza su contratación, factura y acceso. Social mantiene su propia plataforma y operación.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
