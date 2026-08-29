<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    CirclePlay,
    Rocket,
    ShieldCheck,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Capability = {
    id: number;
    sequence: number;
    capability_key: string;
    capability_label: string;
    execution: {
        id: number;
        status: string;
        progress_percentage: number;
        started_at: string | null;
        completed_at: string | null;
    } | null;
    go_live: {
        id: number;
        attempt: number;
        status: string;
        ready_at: string | null;
        went_live_at: string | null;
        subscription_activation: {
            id: number;
            activation_type: string;
            subscriber_id: number;
            company_id: number;
            subscriber: {
                id: number;
                name: string;
                currency: string;
            } | null;
            company: {
                id: number;
                name: string;
                currency: string;
            } | null;
            subscription_id: number;
            subscription: {
                id: number;
                status: string;
                billing_cycle: string;
                currency: string;
                subtotal_amount: number;
                discount_amount: number;
                tax_amount: number;
                total_amount: number;
                starts_at: string | null;
                current_period_end: string | null;
            } | null;
        } | null;
        service_activation: {
            id: number;
            activation_type: string;
            service_id: number;
            service: {
                id: number;
                key: string | null;
                name: string | null;
            } | null;
            subscription_item_id: number;
            subscription_item: {
                id: number;
                status: string;
                billing_model: string;
                quantity: number;
                unit_price: number;
                amount: number;
                currency: string;
                block_size: number | null;
                included_units: number | null;
                unit_name: string | null;
            } | null;
        } | null;
    } | null;
};

type Phase = {
    id: number;
    sequence: number;
    name: string;
    objective: string | null;
    execution: {
        id: number;
        status: string;
        progress_percentage: number;
    } | null;
    capabilities: Capability[];
};

const props = defineProps<{
    contact: {
        id: number;
        company: string | null;
    };
    assessment: {
        id: number;
        organization_name: string | null;
    };
    plan: {
        id: number;
        version: number;
        status: string;
        selected_modality_label: string | null;
        accepted_at: string | null;
        phases: Phase[];
    };
    endpoints: {
        back: string;
        base: string;
    };
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

const progressForms = reactive<
    Record<number, { progress_percentage: string; notes: string }>
>({});

const readinessForms = reactive<
    Record<
        number,
        {
            technical_readiness: boolean;
            operational_readiness: boolean;
            client_readiness: boolean;
            readiness_notes: string;
        }
    >
>({});

const goLiveForms = reactive<Record<number, { go_live_notes: string }>>({});

const subscriptionForms = reactive<
    Record<number, { billing_cycle: 'monthly' | 'yearly' }>
>({});

for (const phase of props.plan.phases) {
    for (const capability of phase.capabilities) {
        progressForms[capability.id] = {
            progress_percentage:
                capability.execution?.progress_percentage?.toString() ?? '0',
            notes: '',
        };

        if (capability.go_live) {
            readinessForms[capability.go_live.id] = {
                technical_readiness: false,
                operational_readiness: false,
                client_readiness: false,
                readiness_notes: '',
            };

            goLiveForms[capability.go_live.id] = {
                go_live_notes: '',
            };

            subscriptionForms[capability.go_live.id] = {
                billing_cycle:
                    capability.go_live.subscription_activation?.subscription
                        ?.billing_cycle === 'yearly'
                        ? 'yearly'
                        : 'monthly',
            };
        }
    }
}

function statusLabel(status: string) {
    return (
        {
            pending: 'Pendiente',
            in_progress: 'En progreso',
            blocked: 'Bloqueado',
            completed: 'Completado',
            cancelled: 'Cancelado',
            draft: 'Borrador',
            ready: 'Ready',
            scheduled: 'Programado',
            live: 'LIVE',
            rolled_back: 'Rollback',
        }[status] ?? status
    );
}

function isProfessionalCapability(capability: Capability): boolean {
    return capability.capability_key === 'branding_identity';
}

function initializePhase(phaseId: number) {
    router.post(
        `${props.endpoints.base}/phases/${phaseId}/initialize`,
        {},
        { preserveScroll: true },
    );
}

function startCapability(capabilityId: number) {
    router.post(
        `${props.endpoints.base}/capabilities/${capabilityId}/start`,
        {},
        { preserveScroll: true },
    );
}

function updateProgress(capabilityId: number) {
    router.post(
        `${props.endpoints.base}/capabilities/${capabilityId}/progress`,
        progressForms[capabilityId],
        { preserveScroll: true },
    );
}

function completeCapability(capabilityId: number) {
    if (!window.confirm('¿Marcar esta capability como completada al 100%?')) {
        return;
    }

    router.post(
        `${props.endpoints.base}/capabilities/${capabilityId}/complete`,
        {},
        { preserveScroll: true },
    );
}

function createGoLive(capabilityId: number) {
    router.post(
        `${props.endpoints.base}/capabilities/${capabilityId}/go-live`,
        {},
        { preserveScroll: true },
    );
}

function markReady(goLiveId: number) {
    router.post(
        `${props.endpoints.base}/go-lives/${goLiveId}/ready`,
        readinessForms[goLiveId],
        { preserveScroll: true },
    );
}

function activateGoLive(goLiveId: number) {
    if (
        !window.confirm(
            '¿Confirmar Go-Live de esta capability? Esto NO activará todavía la suscripción.',
        )
    ) {
        return;
    }

    router.post(
        `${props.endpoints.base}/go-lives/${goLiveId}/live`,
        goLiveForms[goLiveId],
        { preserveScroll: true },
    );
}

const commercialSummary = computed(() => {
    const capabilities = props.plan.phases.flatMap(
        (phase) => phase.capabilities,
    );

    const subscriptionActivation = capabilities
        .map(
            (capability) => capability.go_live?.subscription_activation ?? null,
        )
        .find((activation) => activation?.subscription);

    const items = capabilities
        .map((capability) => capability.go_live?.service_activation ?? null)
        .filter(
            (
                activation,
            ): activation is NonNullable<
                NonNullable<
                    (typeof capabilities)[number]['go_live']
                >['service_activation']
            > => Boolean(activation?.subscription_item),
        );

    const uniqueItems = Array.from(
        new Map(
            items.map((activation) => [
                activation.subscription_item_id,
                activation,
            ]),
        ).values(),
    );

    return {
        subscriber: subscriptionActivation?.subscriber ?? null,
        company: subscriptionActivation?.company ?? null,
        subscription: subscriptionActivation?.subscription ?? null,
        items: uniqueItems,
        activeServices: uniqueItems.length,
    };
});

function money(value: number | null | undefined, currency = 'DOP') {
    const amount = Number(value ?? 0);

    return `${currency} ${new Intl.NumberFormat('es-DO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount)}`;
}

function activateSubscription(goLiveId: number) {
    if (
        !window.confirm(
            '¿Activar o vincular la Subscription general para este Go-Live? Todavía no se agregará ningún Service.',
        )
    ) {
        return;
    }

    router.post(
        `${props.endpoints.base}/go-lives/${goLiveId}/subscription`,
        subscriptionForms[goLiveId],
        { preserveScroll: true },
    );
}

function activateService(goLiveId: number) {
    if (
        !window.confirm(
            '¿Activar la solución mapeada para esta capability? Se agregará como SubscriptionItem dentro de la Subscription general.',
        )
    ) {
        return;
    }

    router.post(
        `${props.endpoints.base}/go-lives/${goLiveId}/service`,
        {},
        { preserveScroll: true },
    );
}

function readinessComplete(goLiveId: number) {
    const form = readinessForms[goLiveId];

    return Boolean(
        form &&
        form.technical_readiness &&
        form.operational_readiness &&
        form.client_readiness,
    );
}
</script>

<template>
    <Head title="Ejecución Transformación 360" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Diagnósticos 360', href: '/admin/diagnosis-requests' },
            {
                title: assessment.organization_name ?? 'Diagnóstico',
                href: `/admin/diagnosis-requests/${contact.id}`,
            },
            { title: 'Plan de Implementación', href: endpoints.back },
            { title: 'Ejecución y Go-Live' },
        ]"
    >
        <div class="space-y-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="outline">LAUDA 360</Badge>
                        <Badge variant="secondary">
                            Plan V{{ plan.version }} · Aceptado
                        </Badge>
                    </div>

                    <h1 class="mt-3 text-2xl font-black">
                        Ejecución y Go-Live
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ assessment.organization_name }}
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="endpoints.back">
                        <ArrowLeft class="mr-2 size-4" />
                        Volver al Plan
                    </Link>
                </Button>
            </div>

            <div
                class="rounded-2xl border bg-muted/20 p-4 text-sm text-muted-foreground"
            >
                Esta pantalla opera implementación y Go-Live.
                <strong>
                    No crea Subscriber, Company, Subscription ni
                    SubscriptionItem.
                </strong>
                La activación comercial post-Go-Live pertenece al bloque
                siguiente.
            </div>

            <div
                v-if="Object.keys(errors).length"
                class="rounded-xl border border-destructive/40 bg-destructive/5 p-4"
            >
                <p class="font-bold">No se pudo completar la acción:</p>
                <section class="rounded-2xl border bg-card p-5 shadow-sm">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-black tracking-wide text-muted-foreground uppercase"
                            >
                                Estado comercial post-Go-Live
                            </p>
                            <h2 class="mt-1 text-xl font-black">
                                Subscription general LAUDAAPI
                            </h2>
                            <p
                                class="mt-1 max-w-3xl text-sm text-muted-foreground"
                            >
                                Una sola Subscription por cliente. Cada solución
                                activa se representa como un SubscriptionItem.
                            </p>
                        </div>

                        <div
                            v-if="commercialSummary.subscription"
                            class="rounded-full border px-3 py-1 text-xs font-black uppercase"
                        >
                            {{ commercialSummary.subscription.status }}
                        </div>
                    </div>

                    <div
                        v-if="commercialSummary.subscription"
                        class="mt-5 space-y-5"
                    >
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border p-4">
                                <p
                                    class="text-xs font-bold text-muted-foreground uppercase"
                                >
                                    Cliente
                                </p>
                                <p class="mt-1 font-black">
                                    {{
                                        commercialSummary.company?.name ??
                                        commercialSummary.subscriber?.name ??
                                        '—'
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Company #
                                    {{ commercialSummary.company?.id ?? '—' }}
                                    · Subscriber #
                                    {{
                                        commercialSummary.subscriber?.id ?? '—'
                                    }}
                                </p>
                            </div>

                            <div class="rounded-xl border p-4">
                                <p
                                    class="text-xs font-bold text-muted-foreground uppercase"
                                >
                                    Subscription
                                </p>
                                <p class="mt-1 font-black">
                                    #{{ commercialSummary.subscription.id }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        commercialSummary.subscription
                                            .billing_cycle === 'yearly'
                                            ? 'Anual'
                                            : 'Mensual'
                                    }}
                                    ·
                                    {{
                                        commercialSummary.subscription.currency
                                    }}
                                </p>
                            </div>

                            <div class="rounded-xl border p-4">
                                <p
                                    class="text-xs font-bold text-muted-foreground uppercase"
                                >
                                    Soluciones activas
                                </p>
                                <p class="mt-1 text-2xl font-black">
                                    {{ commercialSummary.activeServices }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    SubscriptionItems activos desde
                                    Transformación 360
                                </p>
                            </div>

                            <div class="rounded-xl border p-4">
                                <p
                                    class="text-xs font-bold text-muted-foreground uppercase"
                                >
                                    Total recurrente
                                </p>
                                <p class="mt-1 text-2xl font-black">
                                    {{
                                        money(
                                            commercialSummary.subscription
                                                .total_amount,
                                            commercialSummary.subscription
                                                .currency,
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        commercialSummary.subscription
                                            .billing_cycle === 'yearly'
                                            ? 'por año'
                                            : 'por mes'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border p-3 text-sm">
                                <span class="text-muted-foreground">
                                    Subtotal
                                </span>
                                <p class="mt-1 font-black">
                                    {{
                                        money(
                                            commercialSummary.subscription
                                                .subtotal_amount,
                                            commercialSummary.subscription
                                                .currency,
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="rounded-xl border p-3 text-sm">
                                <span class="text-muted-foreground">
                                    Descuento
                                </span>
                                <p class="mt-1 font-black">
                                    {{
                                        money(
                                            commercialSummary.subscription
                                                .discount_amount,
                                            commercialSummary.subscription
                                                .currency,
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="rounded-xl border p-3 text-sm">
                                <span class="text-muted-foreground">
                                    Impuestos
                                </span>
                                <p class="mt-1 font-black">
                                    {{
                                        money(
                                            commercialSummary.subscription
                                                .tax_amount,
                                            commercialSummary.subscription
                                                .currency,
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="rounded-xl border p-3 text-sm">
                                <span class="text-muted-foreground">
                                    Total
                                </span>
                                <p class="mt-1 font-black">
                                    {{
                                        money(
                                            commercialSummary.subscription
                                                .total_amount,
                                            commercialSummary.subscription
                                                .currency,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-black">Soluciones activas</h3>

                            <div
                                v-if="commercialSummary.items.length"
                                class="mt-3 grid gap-3 lg:grid-cols-2"
                            >
                                <div
                                    v-for="activation in commercialSummary.items"
                                    :key="activation.subscription_item_id"
                                    class="rounded-xl border p-4"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p class="font-black">
                                                {{
                                                    activation.service?.name ??
                                                    `Service #${activation.service_id}`
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    activation.service?.key ??
                                                    'service'
                                                }}
                                                · SubscriptionItem #
                                                {{
                                                    activation.subscription_item_id
                                                }}
                                            </p>
                                        </div>

                                        <span
                                            class="rounded-full border px-2 py-1 text-[11px] font-black uppercase"
                                        >
                                            {{
                                                activation.subscription_item
                                                    ?.billing_model
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm"
                                    >
                                        <span>
                                            Cantidad:
                                            <strong>
                                                {{
                                                    activation.subscription_item
                                                        ?.quantity
                                                }}
                                            </strong>
                                        </span>
                                        <span>
                                            Precio:
                                            <strong>
                                                {{
                                                    money(
                                                        activation
                                                            .subscription_item
                                                            ?.unit_price,
                                                        activation
                                                            .subscription_item
                                                            ?.currency ??
                                                            commercialSummary
                                                                .subscription
                                                                .currency,
                                                    )
                                                }}
                                            </strong>
                                        </span>
                                        <span>
                                            Importe:
                                            <strong>
                                                {{
                                                    money(
                                                        activation
                                                            .subscription_item
                                                            ?.amount,
                                                        activation
                                                            .subscription_item
                                                            ?.currency ??
                                                            commercialSummary
                                                                .subscription
                                                                .currency,
                                                    )
                                                }}
                                            </strong>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <p
                                v-else
                                class="mt-3 rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                            >
                                La Subscription general existe, pero todavía no
                                hay soluciones activadas por R2-J.
                            </p>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-5 rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        Todavía no existe una Subscription general vinculada
                        desde un Go-Live. Completa R2-I en una capability LIVE.
                    </div>
                </section>

                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    <li v-for="(message, key) in errors" :key="key">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <Card v-for="phase in plan.phases" :key="phase.id">
                <CardHeader>
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <CardTitle>
                                {{ phase.sequence }}. {{ phase.name }}
                            </CardTitle>
                            <CardDescription v-if="phase.objective">
                                {{ phase.objective }}
                            </CardDescription>
                        </div>

                        <Badge v-if="phase.execution" variant="outline">
                            {{ statusLabel(phase.execution.status) }}
                            · {{ phase.execution.progress_percentage }}%
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4">
                    <Button
                        v-if="!phase.execution"
                        @click="initializePhase(phase.id)"
                    >
                        <CirclePlay class="mr-2 size-4" />
                        Inicializar fase
                    </Button>

                    <div
                        v-for="capability in phase.capabilities"
                        :key="capability.id"
                        class="space-y-4 rounded-2xl border p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p class="font-bold">
                                    {{ capability.capability_label }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ capability.capability_key }}
                                </p>
                                <div
                                    v-if="isProfessionalCapability(capability)"
                                    class="mt-2 rounded-xl border bg-muted/20 p-3 text-xs leading-5 text-muted-foreground"
                                >
                                    <p class="font-black text-foreground">
                                        Servicio profesional
                                    </p>
                                    <p class="mt-1">
                                        Branding e Identidad Digital se ejecuta
                                        y entrega dentro del Plan. Puede tener
                                        Go-Live como cierre de implantación,
                                        pero no genera Subscription ni
                                        SubscriptionItem.
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    v-if="capability.execution"
                                    variant="outline"
                                >
                                    {{
                                        statusLabel(capability.execution.status)
                                    }}
                                    ·
                                    {{
                                        capability.execution
                                            .progress_percentage
                                    }}%
                                </Badge>

                                <Badge
                                    v-if="capability.go_live"
                                    variant="secondary"
                                >
                                    Go-Live:
                                    {{ statusLabel(capability.go_live.status) }}
                                </Badge>
                            </div>
                        </div>

                        <template
                            v-if="phase.execution && capability.execution"
                        >
                            <Button
                                v-if="capability.execution.status === 'pending'"
                                variant="outline"
                                @click="startCapability(capability.id)"
                            >
                                Iniciar capability
                            </Button>

                            <div
                                v-if="
                                    capability.execution.status ===
                                    'in_progress'
                                "
                                class="grid gap-3 md:grid-cols-[160px_1fr_auto]"
                            >
                                <input
                                    v-model="
                                        progressForms[capability.id]
                                            .progress_percentage
                                    "
                                    type="number"
                                    min="0"
                                    max="99"
                                    step="1"
                                    class="rounded-md border bg-background px-3 py-2"
                                    placeholder="%"
                                />

                                <input
                                    v-model="progressForms[capability.id].notes"
                                    class="rounded-md border bg-background px-3 py-2"
                                    placeholder="Notas del avance"
                                />

                                <Button
                                    variant="outline"
                                    @click="updateProgress(capability.id)"
                                >
                                    Guardar avance
                                </Button>

                                <div class="md:col-span-3">
                                    <Button
                                        @click="
                                            completeCapability(capability.id)
                                        "
                                    >
                                        <CheckCircle2 class="mr-2 size-4" />
                                        Completar al 100%
                                    </Button>
                                </div>
                            </div>
                        </template>

                        <div
                            v-if="capability.execution?.status === 'completed'"
                            class="space-y-4 border-t pt-4"
                        >
                            <Button
                                v-if="!capability.go_live"
                                variant="outline"
                                @click="createGoLive(capability.id)"
                            >
                                <Rocket class="mr-2 size-4" />
                                Crear intento Go-Live
                            </Button>

                            <template
                                v-if="
                                    capability.go_live &&
                                    capability.go_live.status === 'draft'
                                "
                            >
                                <p class="font-semibold">
                                    Checklist de readiness
                                </p>

                                <div class="grid gap-2 md:grid-cols-3">
                                    <label
                                        class="flex gap-2 rounded-xl border p-3 text-sm"
                                    >
                                        <input
                                            v-model="
                                                readinessForms[
                                                    capability.go_live.id
                                                ].technical_readiness
                                            "
                                            type="checkbox"
                                        />
                                        Readiness técnico confirmado
                                    </label>

                                    <label
                                        class="flex gap-2 rounded-xl border p-3 text-sm"
                                    >
                                        <input
                                            v-model="
                                                readinessForms[
                                                    capability.go_live.id
                                                ].operational_readiness
                                            "
                                            type="checkbox"
                                        />
                                        Readiness operativo confirmado
                                    </label>

                                    <label
                                        class="flex gap-2 rounded-xl border p-3 text-sm"
                                    >
                                        <input
                                            v-model="
                                                readinessForms[
                                                    capability.go_live.id
                                                ].client_readiness
                                            "
                                            type="checkbox"
                                        />
                                        Validación cliente confirmada
                                    </label>
                                </div>

                                <input
                                    v-model="
                                        readinessForms[capability.go_live.id]
                                            .readiness_notes
                                    "
                                    class="w-full rounded-md border bg-background px-3 py-2"
                                    placeholder="Notas/evidencia de readiness"
                                />

                                <Button
                                    :disabled="
                                        !readinessComplete(
                                            capability.go_live.id,
                                        )
                                    "
                                    @click="markReady(capability.go_live.id)"
                                >
                                    <ShieldCheck class="mr-2 size-4" />
                                    Marcar Ready
                                </Button>
                            </template>

                            <template
                                v-if="
                                    capability.go_live &&
                                    ['ready', 'scheduled'].includes(
                                        capability.go_live.status,
                                    )
                                "
                            >
                                <input
                                    v-model="
                                        goLiveForms[capability.go_live.id]
                                            .go_live_notes
                                    "
                                    class="w-full rounded-md border bg-background px-3 py-2"
                                    placeholder="Notas del Go-Live"
                                />

                                <Button
                                    @click="
                                        activateGoLive(capability.go_live.id)
                                    "
                                >
                                    <Rocket class="mr-2 size-4" />
                                    Confirmar LIVE
                                </Button>
                            </template>

                            <div
                                v-if="
                                    capability.go_live?.status === 'live' &&
                                    !isProfessionalCapability(capability)
                                "
                                class="space-y-4 rounded-xl border p-4"
                            >
                                <div>
                                    <p class="font-black">Capability LIVE</p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        El Go-Live ya ocurrió. La activación
                                        comercial se controla por separado.
                                    </p>
                                </div>

                                <template
                                    v-if="
                                        !capability.go_live
                                            .subscription_activation
                                    "
                                >
                                    <div
                                        class="grid gap-3 sm:grid-cols-[220px_auto]"
                                    >
                                        <select
                                            v-model="
                                                subscriptionForms[
                                                    capability.go_live.id
                                                ].billing_cycle
                                            "
                                            class="rounded-md border bg-background px-3 py-2"
                                        >
                                            <option value="monthly">
                                                Mensual
                                            </option>
                                            <option value="yearly">
                                                Anual
                                            </option>
                                        </select>

                                        <Button
                                            @click="
                                                activateSubscription(
                                                    capability.go_live.id,
                                                )
                                            "
                                        >
                                            Activar/vincular suscripción general
                                        </Button>
                                    </div>

                                    <p class="text-xs text-muted-foreground">
                                        Esta acción asegura Subscriber/Company y
                                        ejecuta R2-I. No crea ningún
                                        SubscriptionItem.
                                    </p>
                                </template>

                                <div
                                    v-else
                                    class="rounded-xl border bg-muted/20 p-3 text-sm"
                                >
                                    <p class="font-bold">
                                        Subscription general #
                                        {{
                                            capability.go_live
                                                .subscription_activation
                                                .subscription_id
                                        }}
                                    </p>
                                    <p class="mt-1 text-muted-foreground">
                                        {{
                                            capability.go_live
                                                .subscription_activation
                                                .activation_type
                                        }}
                                        ·
                                        {{
                                            capability.go_live
                                                .subscription_activation
                                                .subscription?.billing_cycle
                                        }}
                                        ·
                                        {{
                                            capability.go_live
                                                .subscription_activation
                                                .subscription?.currency
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        R2-I completado. La Subscription general
                                        ya está vinculada. El Service todavía no
                                        está agregado; eso corresponde a R2-J.
                                    </p>
                                </div>

                                <template
                                    v-if="
                                        capability.go_live
                                            .subscription_activation &&
                                        !capability.go_live.service_activation
                                    "
                                >
                                    <Button
                                        variant="outline"
                                        @click="
                                            activateService(
                                                capability.go_live.id,
                                            )
                                        "
                                    >
                                        Activar solución mapeada
                                    </Button>

                                    <p class="text-xs text-muted-foreground">
                                        R2-J agregará únicamente el Service
                                        asociado a esta capability dentro de la
                                        misma Subscription general.
                                    </p>
                                </template>

                                <div
                                    v-if="capability.go_live.service_activation"
                                    class="rounded-xl border p-3 text-sm"
                                >
                                    <p class="font-bold">Solución activa</p>

                                    <p class="mt-1">
                                        {{
                                            capability.go_live
                                                .service_activation.service
                                                ?.name ??
                                            `Service #${
                                                capability.go_live
                                                    .service_activation
                                                    .service_id
                                            }`
                                        }}
                                    </p>

                                    <p class="mt-1 text-muted-foreground">
                                        SubscriptionItem #
                                        {{
                                            capability.go_live
                                                .service_activation
                                                .subscription_item_id
                                        }}
                                        ·
                                        {{
                                            capability.go_live
                                                .service_activation
                                                .subscription_item
                                                ?.billing_model
                                        }}
                                        ·
                                        {{
                                            capability.go_live
                                                .service_activation
                                                .subscription_item?.currency
                                        }}
                                        {{
                                            capability.go_live
                                                .service_activation
                                                .subscription_item?.amount
                                        }}
                                    </p>

                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            capability.go_live
                                                .service_activation
                                                .activation_type
                                        }}
                                        · R2-J completado.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
