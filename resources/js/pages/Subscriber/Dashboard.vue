<!-- resources/js/Pages/Subscriber/Dashboard.vue -->
<script setup lang="ts">
import { Head, router, usePage, useRemember } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ArrowRight,
    CheckCircle2,
    Circle,
    Clock3,
    KeyRound,
    ShieldAlert,
    TriangleAlert,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { subscriber } from '@/routes';
import StatCard from '@/components/StatCard.vue';
import SectionCard from '@/components/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useDateFormatter } from '@/composables/useDateFormatter';

const { formatDate, formatDateTime } = useDateFormatter();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptores', href: subscriber().url },
];

const props = defineProps<{
    stats: any;
}>();

type AuthUser = {
    id: number;
    name: string;
    email: string;
    must_change_password?: boolean;
    password_changed_at?: string | null;
};

const page = usePage();

const authUser = computed(() => page.props.auth?.user as AuthUser | null);
const mustChangePassword = computed(
    () => !!authUser.value?.must_change_password,
);

const ui = useRemember({ showBilling: false }, 'subscriber.dashboard.ui');

const currency = computed(() => props.stats?.currency ?? 'USD');

function money(n: any) {
    const v = Number(n ?? 0);
    if (!Number.isFinite(v)) return '0.00';
    return new Intl.NumberFormat('es-DO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(v);
}

function moneyWithCurrency(n: any) {
    return `${money(n)} ${currency.value}`;
}

const pendingInvoices = computed(
    () => props.stats?.billing?.invoices?.pending_count ?? 0,
);
const ap = computed(() => props.stats?.billing?.balance?.accounts_payable ?? 0);

const billingSummary = computed(() => {
    const overdue = props.stats?.billing?.invoices?.by_status?.overdue ?? 0;
    return `Pendientes: ${pendingInvoices.value} • Vencidas: ${overdue} • AP: ${moneyWithCurrency(ap.value)}`;
});

const subscriptionEndsLabel = computed(() => {
    return (
        props.stats?.subscription?.period_end_human ??
        props.stats?.subscription?.trial_ends_at_human ??
        '—'
    );
});

function goToChangePassword() {
    router.visit('/subscriber/security');
}

const transformation360 = computed(
    () => props.stats?.transformation360 ?? null,
);

function openTransformation360(url: string | null | undefined) {
    if (!url) return;

    router.visit(url);
}
</script>

<template>
    <Head title="Panel Suscriptores" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <!-- ✅ Aviso de seguridad: password temporal -->
            <Alert
                v-if="mustChangePassword"
                class="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <ShieldAlert class="h-4 w-4" />
                <AlertTitle>Cambio de contraseña requerido</AlertTitle>

                <AlertDescription class="mt-2">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="max-w-4xl text-sm leading-6">
                            Estás usando la contraseña temporal enviada en el
                            enlace de activación. Por seguridad, debes cambiarla
                            para continuar usando tu cuenta con normalidad y
                            proteger el acceso a tu suscripción, facturación y
                            servicios.
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <Button size="sm" @click="goToChangePassword">
                                <KeyRound class="mr-2 h-4 w-4" />
                                Cambiar contraseña
                            </Button>
                        </div>
                    </div>
                </AlertDescription>
            </Alert>

            <!-- ✅ TOP: 4 cards clave -->
            <div class="grid gap-6 md:grid-cols-4">
                <StatCard
                    title="Suscripción"
                    :value="props.stats?.subscription?.status ?? '—'"
                    description="Estado actual"
                    :trend-positive="
                        (props.stats?.subscription?.status ?? '') === 'active'
                    "
                />

                <StatCard
                    title="Servicios activos"
                    :value="props.stats?.subscription?.active_services ?? 0"
                    description="Items activos / trialing"
                />

                <StatCard
                    title="Facturas pendientes"
                    :value="props.stats?.billing?.invoices?.pending_count ?? 0"
                    description="Emitidas + Pendientes"
                    :trend-positive="false"
                />

                <StatCard
                    title="Cuentas por pagar (CxP)"
                    :value="
                        moneyWithCurrency(
                            props.stats?.billing?.balance?.accounts_payable ??
                                0,
                        )
                    "
                    description="Pendiente por pagar"
                    :trend-positive="false"
                />
            </div>

            <!-- ✅ Transformación Digital 360 -->
            <SectionCard
                v-if="transformation360?.visible"
                title="Transformación Digital 360"
                description="Seguimiento de tu recorrido consultivo y sus tres entregables gratuitos"
            >
                <div
                    class="mb-5 flex flex-col gap-4 rounded-2xl border bg-muted/20 p-5 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-black tracking-wide text-red-600 uppercase"
                        >
                            Estado actual
                        </p>

                        <h3 class="mt-1 text-lg font-black">
                            {{
                                transformation360.current_label ??
                                'Transformación Digital 360'
                            }}
                        </h3>
                    </div>

                    <Button
                        v-if="transformation360.primary_action?.url"
                        variant="outline"
                        class="shrink-0"
                        @click="
                            openTransformation360(
                                transformation360.primary_action.url,
                            )
                        "
                    >
                        {{ transformation360.primary_action.label }}
                        <ArrowRight class="ml-2 h-4 w-4" />
                    </Button>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="(stage, index) in transformation360.stages"
                        :key="stage.key"
                        class="flex min-h-52 flex-col rounded-2xl border p-4"
                        :class="{
                            'bg-muted/30': stage.state === 'completed',
                            'ring-1 ring-red-500/30': stage.state === 'current',
                        }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border"
                            >
                                <CheckCircle2
                                    v-if="stage.state === 'completed'"
                                    class="h-4 w-4 text-emerald-600"
                                />

                                <Clock3
                                    v-else-if="stage.state === 'current'"
                                    class="h-4 w-4 text-red-600"
                                />

                                <Circle
                                    v-else
                                    class="h-4 w-4 text-muted-foreground"
                                />
                            </div>

                            <span
                                class="rounded-full border px-2 py-1 text-[10px] font-black uppercase"
                            >
                                {{ stage.status_label }}
                            </span>
                        </div>

                        <p
                            class="mt-4 text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            Etapa {{ index + 1 }}
                        </p>

                        <h4 class="mt-1 font-black">
                            {{ stage.label }}
                        </h4>

                        <p
                            class="mt-2 flex-1 text-xs leading-5 text-muted-foreground"
                        >
                            {{ stage.description }}
                        </p>

                        <Button
                            v-if="stage.url && stage.action_label"
                            size="sm"
                            variant="outline"
                            class="mt-4 w-full"
                            @click="openTransformation360(stage.url)"
                        >
                            {{ stage.action_label }}
                        </Button>

                        <div
                            v-else-if="
                                stage.key === 'implementation_plan' &&
                                stage.status_label === 'En preparación'
                            "
                            class="mt-4 rounded-lg border border-dashed px-3 py-2 text-center text-xs font-semibold text-muted-foreground"
                        >
                            Borrador privado de LAUDA
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-xs leading-5 text-muted-foreground">
                    Informe Ampliado, Roadmap Detallado y Plan de Implementación son entregables gratuitos. La contratación de apoyo para ejecutar el Plan se gestiona en un proceso separado.
                </p>
            </SectionCard>

            <!-- ✅ Activación (solicitud) -->
            <SectionCard
                title="Activación"
                description="Estado de tu solicitud / onboarding"
            >
                <div class="grid gap-6 md:grid-cols-4">
                    <StatCard
                        title="Solicitud"
                        :value="
                            props.stats?.activation?.has_request
                                ? 'Creada'
                                : 'No existe'
                        "
                        description="Solicitud de Activación"
                        :trend-positive="!!props.stats?.activation?.has_request"
                    />

                    <StatCard
                        title="Estado"
                        :value="props.stats?.activation?.status ?? '—'"
                        description="Pipeline"
                    />

                    <StatCard
                        title="Trial activo"
                        :value="
                            props.stats?.activation?.trial_active ? 'Sí' : 'No'
                        "
                        description="Por activación"
                        :trend-positive="
                            !!props.stats?.activation?.trial_active
                        "
                    />

                    <StatCard
                        title="Servicios solicitados"
                        :value="
                            props.stats?.activation?.services_requested ?? 0
                        "
                        description="Solicitudes de activaciones recibidas"
                    />
                </div>

                <div
                    v-if="props.stats?.activation?.has_request"
                    class="mt-4 grid gap-6 md:grid-cols-3"
                >
                    <StatCard
                        title="Inicio trial"
                        :value="
                            formatDate(
                                props.stats?.activation?.trial_starts_at_human,
                            ) ?? '—'
                        "
                        description="Fecha"
                    />
                    <StatCard
                        title="Fin trial"
                        :value="
                            formatDate(
                                props.stats?.activation?.trial_ends_at_human,
                            ) ?? '—'
                        "
                        description="Fecha"
                        :trend-positive="false"
                    />
                    <StatCard
                        title="Días restantes"
                        :value="props.stats?.activation?.trial_days_left ?? 0"
                        description="Aprox."
                    />
                </div>
            </SectionCard>

            <!-- ✅ Suscripción (detalle) -->
            <SectionCard
                title="Mi suscripción"
                description="Periodo, totales y ciclo"
            >
                <div class="grid gap-6 md:grid-cols-4">
                    <StatCard
                        title="Ciclo"
                        :value="props.stats?.subscription?.billing_cycle ?? '—'"
                        description="Mensual / Anual"
                    />
                    <StatCard
                        title="Periodo termina"
                        :value="formatDate(subscriptionEndsLabel)"
                        description="Período actual finaliza / Prueba finaliza"
                        :trend-positive="false"
                    />
                    <StatCard
                        title="Total (snapshot)"
                        :value="
                            moneyWithCurrency(
                                props.stats?.subscription?.total_amount ?? 0,
                            )
                        "
                        description="Monto total"
                    />
                    <StatCard
                        title="Estado items"
                        :value="
                            props.stats?.subscription
                                ?.items_active_or_trialing ?? 0
                        "
                        description="Ítems de suscripción"
                    />
                </div>
            </SectionCard>

            <!-- ✅ Facturación (toggle) -->
            <SectionCard
                title="Facturación"
                :description="`Facturas, pagos y AP (${currency})`"
            >
                <div
                    v-if="mustChangePassword"
                    class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                    <div class="text-sm leading-6">
                        Cambia tu contraseña primero para gestionar con más
                        seguridad la información de facturación y pagos.
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="text-sm text-muted-foreground">
                        <template v-if="ui.showBilling">
                            Mostrando detalles de facturación.
                        </template>
                        <template v-else>
                            {{ billingSummary }}
                        </template>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="ui.showBilling = !ui.showBilling"
                        >
                            {{ ui.showBilling ? 'Ocultar' : 'Ver' }} facturación
                        </Button>

                        <template v-if="ui.showBilling">
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="mustChangePassword"
                                @click="router.visit('/subscriber/invoices')"
                            >
                                Facturas
                            </Button>

                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="mustChangePassword"
                                @click="router.visit('/subscriber/payments')"
                            >
                                Pagos
                            </Button>
                        </template>
                    </div>
                </div>

                <div v-if="ui.showBilling" class="mt-4 space-y-4">
                    <div class="rounded-xl border bg-muted/20 p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold">Facturas</div>
                            <div class="text-xs text-muted-foreground">
                                Estados
                            </div>
                        </div>

                        <div class="mt-3 grid gap-6 md:grid-cols-4">
                            <StatCard
                                title="Totales"
                                :value="
                                    props.stats?.billing?.invoices?.total ?? 0
                                "
                                description="Registros"
                            />
                            <StatCard
                                title="Emitidas"
                                :value="
                                    props.stats?.billing?.invoices?.by_status
                                        ?.issued ?? 0
                                "
                                description="Listas para pagar"
                            />
                            <StatCard
                                title="Vencidas"
                                :value="
                                    props.stats?.billing?.invoices?.by_status
                                        ?.overdue ?? 0
                                "
                                description="Requieren seguimiento"
                                :trend-positive="false"
                            />
                            <StatCard
                                title="Pagadas"
                                :value="
                                    props.stats?.billing?.invoices?.by_status
                                        ?.paid ?? 0
                                "
                                description="Completadas"
                            />
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-4">
                            <StatCard
                                title="Total facturado"
                                :value="
                                    moneyWithCurrency(
                                        props.stats?.billing?.invoices
                                            ?.total_invoiced ?? 0,
                                    )
                                "
                                description="Sin void"
                            />
                            <StatCard
                                title="Pagado (en facturas)"
                                :value="
                                    moneyWithCurrency(
                                        props.stats?.billing?.invoices
                                            ?.total_amount_paid_on_invoices ??
                                            0,
                                    )
                                "
                                description="amount_paid"
                            />
                            <StatCard
                                title="AP pendiente"
                                :value="
                                    moneyWithCurrency(
                                        props.stats?.billing?.balance
                                            ?.accounts_payable ?? 0,
                                    )
                                "
                                description="Issued + Overdue"
                                :trend-positive="false"
                            />
                            <StatCard
                                title="Pagado este mes"
                                :value="
                                    moneyWithCurrency(
                                        props.stats?.billing?.payments
                                            ?.paid_this_month ?? 0,
                                    )
                                "
                                description="payments.amount"
                            />
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- ✅ Perfil fiscal -->
            <SectionCard
                title="Perfil fiscal"
                description="Completitud de datos de facturación"
            >
                <div class="grid gap-6 md:grid-cols-3">
                    <StatCard
                        title="Perfil Fiscal"
                        :value="
                            props.stats?.tax_profile?.exists
                                ? 'Completo'
                                : 'Pendiente'
                        "
                        description="Perfiles fiscales de la empresa"
                        :trend-positive="!!props.stats?.tax_profile?.exists"
                    />
                    <StatCard
                        title="Moneda"
                        :value="props.stats?.currency ?? 'USD'"
                        description="Moneda de la empresa"
                    />
                    <StatCard
                        title="Zona horaria"
                        :value="
                            props.stats?.timezone ?? 'America/Santo_Domingo'
                        "
                        description="Zona horaria de la empresa"
                    />
                </div>
            </SectionCard>

            <!-- ✅ Uso (si aplica) -->
            <SectionCard
                title="Uso"
                description="Consumo del mes (si tu modelo usa usage_records)"
            >
                <div class="grid gap-6 md:grid-cols-3">
                    <StatCard
                        title="Uso del mes"
                        :value="props.stats?.usage?.month_units ?? 0"
                        description="Cantidad total"
                    />
                    <StatCard
                        title="Días con uso"
                        :value="props.stats?.usage?.days_with_usage ?? 0"
                        description="Días con uso"
                    />
                    <StatCard
                        title="Servicios con uso"
                        :value="props.stats?.usage?.services_with_usage ?? 0"
                        description="Diferentes servicios"
                    />
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
