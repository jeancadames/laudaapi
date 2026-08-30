<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    assessment: {
        id: number;
        organization_name: string;
    };
    plan: Record<string, any>;
    roadmap_url: string | null;
    accept_url: string;
    diagnosis_url: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Inicio',
        href: '/app',
    },
    {
        title: 'Diagnóstico 360',
        href: props.diagnosis_url,
    },
    ...(props.roadmap_url
        ? [
              {
                  title: 'Roadmap Detallado',
                  href: props.roadmap_url,
              },
          ]
        : []),
    {
        title: 'Plan de Implementación',
        href: `/diagnostico/${props.assessment.id}/plan-implementacion`,
    },
];

function statusLabel(status: string) {
    return (
        {
            presented: 'Presentado',
            accepted: 'Aceptado',
            active: 'En ejecución',
            completed: 'Completado',
        }[status] ?? status
    );
}

function acceptPlan() {
    if (
        !window.confirm(
            '¿Confirmas que aceptas este Plan de Implementación?'
        )
    ) {
        return;
    }

    router.post(
        props.accept_url,
        {},
        {
            preserveScroll: true,
        },
    );
}


function money(
    value: number,
    currency = 'DOP',
) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(value ?? 0);
}


function durationLabel(
    value: number,
    unit: string,
) {
    const labels: Record<string, [string, string]> = {
        days: ['día', 'días'],
        weeks: ['semana', 'semanas'],
        months: ['mes', 'meses'],
    };

    const pair = labels[unit] ?? [unit, unit];

    return `${value} ${
        value === 1
            ? pair[0]
            : pair[1]
    }`;
}


function milestoneStatusLabel(
    status: string,
) {
    return (
        {
            draft: 'Borrador',
            ready: 'Listo',
            invoiced: 'Facturado',
            paid: 'Pagado',
        }[status] ?? status
    );
}


function executionStatusLabel(
    status: string,
) {
    return (
        {
            pending: 'Pendiente',
            in_progress: 'En progreso',
            blocked: 'Bloqueado',
            completed: 'Completado',
            cancelled: 'Cancelado',
        }[status] ?? status
    );
}


function progressPercent(
    value: number | string | null | undefined,
) {
    const parsed = Number(value ?? 0);

    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.min(
        100,
        Math.max(0, parsed),
    );
}


function goLiveStatusLabel(
    status: string | null | undefined,
) {
    if (!status) {
        return 'Aún no preparada';
    }

    return (
        {
            draft: 'En preparación',
            ready: 'Lista para Go-Live',
            scheduled: 'Go-Live programado',
            live: 'LIVE',
            rolled_back: 'Revertida',
            cancelled: 'Cancelada',
        }[status] ?? status
    );
}


function goLiveDateLabel(
    value: string | null | undefined,
) {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'es-DO',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(date);
}

</script>

<template>
    <Head title="Plan de Implementación LAUDA 360" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="min-h-full bg-muted/20 px-4 py-8 sm:px-6">
        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <Link
                    v-if="roadmap_url"
                    :href="roadmap_url"
                    class="inline-flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Volver al Roadmap Detallado
                </Link>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Badge variant="outline">LAUDA 360</Badge>
                    <Badge variant="secondary">
                        Plan V{{ plan.version }} · {{ statusLabel(plan.status) }}
                    </Badge>
                </div>

                <h1 class="mt-3 text-3xl font-black">
                    Plan de Implementación
                </h1>

                <p class="mt-2 text-sm text-muted-foreground">
                    {{ assessment.organization_name }}
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Modalidad</CardTitle>
                    <CardDescription>
                        {{ plan.selected_modality_label ?? 'Pendiente' }}
                    </CardDescription>
                </CardHeader>
            </Card>

            <Card
                v-if="
                    plan.status === 'presented'
                    || plan.status === 'accepted'
                "
            >
                <CardHeader>
                    <CardTitle>
                        {{
                            plan.status === 'presented'
                                ? 'Aceptación del Plan'
                                : 'Plan aceptado'
                        }}
                    </CardTitle>

                    <CardDescription>
                        <template v-if="plan.status === 'presented'">
                            Revisa las fases y la modalidad acordada.
                            Al aceptar confirmas este Plan de Implementación.
                        </template>

                        <template v-else>
                            Tu aceptación quedó registrada.
                        </template>
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <Button
                        v-if="plan.status === 'presented'"
                        type="button"
                        @click="acceptPlan"
                    >
                        Aceptar Plan de Implementación
                    </Button>

                    <p
                        v-else
                        class="text-sm font-medium"
                    >
                        Aceptado
                        <span v-if="plan.accepted_at">
                            · {{ plan.accepted_at }}
                        </span>
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Resumen comercial
                    </CardTitle>
                    <CardDescription>
                        Condiciones de implementación acordadas.
                        La suscripción recurrente no forma parte de este monto.
                    </CardDescription>
                </CardHeader>

                <CardContent
                    class="grid gap-4 md:grid-cols-3"
                >
                    <div class="rounded-xl border p-4">
                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Modalidad
                        </p>
                        <p class="mt-1 font-bold">
                            {{
                                plan.selected_modality_label
                                ?? plan.recommended_modality_label
                                ?? 'Pendiente'
                            }}
                        </p>
                    </div>

                    <div class="rounded-xl border p-4">
                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Inversión de implementación
                        </p>
                        <p class="mt-1 font-bold">
                            {{
                                money(
                                    plan.commercial_summary
                                        .total_price_amount,
                                    plan.commercial_summary
                                        .currency,
                                )
                            }}
                        </p>
                    </div>

                    <div class="rounded-xl border p-4">
                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Alcance comercial
                        </p>
                        <p class="mt-1 font-bold">
                            {{
                                plan.commercial_summary
                                    .phases_count
                            }}
                            fase(s) ·
                            {{
                                plan.commercial_summary
                                    .milestones_count
                            }}
                            hito(s)
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Progreso de implementación
                    </CardTitle>

                    <CardDescription>
                        Avance operativo del Plan aceptado.
                        Completar una capacidad no significa
                        que ya esté en Go-Live.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div
                        class="flex flex-wrap items-end justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-sm text-muted-foreground"
                            >
                                Avance general
                            </p>
                            <p class="text-2xl font-bold">
                                {{
                                    progressPercent(
                                        plan.execution_summary
                                            .progress_percentage,
                                    )
                                }}%
                            </p>
                        </div>

                        <p
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                plan.execution_summary
                                    .completed_count
                            }}
                            completadas ·
                            {{
                                plan.execution_summary
                                    .in_progress_count
                            }}
                            en progreso ·
                            {{
                                plan.execution_summary
                                    .blocked_count
                            }}
                            bloqueadas
                        </p>
                    </div>

                    <div
                        class="h-2 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full bg-primary transition-all"
                            :style="{
                                width:
                                    `${progressPercent(
                                        plan.execution_summary
                                            .progress_percentage,
                                    )}%`,
                            }"
                        />
                    </div>

                    <div
                        class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <div class="rounded-xl border p-3">
                            Pendientes:
                            <strong>
                                {{
                                    plan.execution_summary
                                        .pending_count
                                }}
                            </strong>
                        </div>

                        <div class="rounded-xl border p-3">
                            En progreso:
                            <strong>
                                {{
                                    plan.execution_summary
                                        .in_progress_count
                                }}
                            </strong>
                        </div>

                        <div class="rounded-xl border p-3">
                            Bloqueadas:
                            <strong>
                                {{
                                    plan.execution_summary
                                        .blocked_count
                                }}
                            </strong>
                        </div>

                        <div class="rounded-xl border p-3">
                            Completadas:
                            <strong>
                                {{
                                    plan.execution_summary
                                        .completed_count
                                }}
                            </strong>
                        </div>

                        <div class="rounded-xl border p-3">
                            Canceladas:
                            <strong>
                                {{
                                    plan.execution_summary
                                        .cancelled_count
                                }}
                            </strong>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Estado de puesta en marcha
                    </CardTitle>

                    <CardDescription>
                        El avance de implementación y el Go-Live
                        son etapas distintas. Estar LIVE tampoco
                        activa por sí solo una suscripción o acceso.
                    </CardDescription>
                </CardHeader>

                <CardContent
                    class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div class="rounded-xl border p-3">
                        Aún no preparadas:
                        <strong>
                            {{
                                plan.go_live_summary
                                    .without_go_live_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        En preparación:
                        <strong>
                            {{
                                plan.go_live_summary
                                    .draft_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        Listas:
                        <strong>
                            {{
                                plan.go_live_summary
                                    .ready_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        Programadas:
                        <strong>
                            {{
                                plan.go_live_summary
                                    .scheduled_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        LIVE:
                        <strong>
                            {{
                                plan.go_live_summary
                                    .live_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        Revertidas:
                        <strong>
                            {{
                                plan.go_live_summary
                                    .rolled_back_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        Canceladas:
                        <strong>
                            {{
                                plan.go_live_summary
                                    .cancelled_count
                            }}
                        </strong>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Acceso a tus soluciones
                    </CardTitle>

                    <CardDescription>
                        El acceso aparece únicamente cuando la
                        solución recurrente está activada y tu
                        entitlement actual permite utilizarla.
                    </CardDescription>
                </CardHeader>

                <CardContent
                    class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div class="rounded-xl border p-3">
                        R2-J activadas:
                        <strong>
                            {{
                                plan.solution_access_summary
                                    .r2j_activated_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        Con acceso:
                        <strong>
                            {{
                                plan.solution_access_summary
                                    .entitled_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        Acceso no disponible:
                        <strong>
                            {{
                                plan.solution_access_summary
                                    .access_unavailable_count
                            }}
                        </strong>
                    </div>

                    <div class="rounded-xl border p-3">
                        LIVE pendientes de R2-J:
                        <strong>
                            {{
                                plan.solution_access_summary
                                    .live_without_r2j_count
                            }}
                        </strong>
                    </div>

                    <div
                        v-if="plan.solution_access_summary.portal_url"
                        class="sm:col-span-2 lg:col-span-4"
                    >
                        <Button as-child>
                            <a :href="plan.solution_access_summary.portal_url">
                                Ir a mi portal
                            </a>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card v-for="phase in plan.phases ?? []" :key="phase.id">
                <CardHeader>
                    <CardTitle>
                        {{ phase.sequence }}. {{ phase.name }}
                    </CardTitle>
                    <CardDescription v-if="phase.objective">
                        {{ phase.objective }}
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div
                            v-for="capability in phase.capabilities ?? []"
                            :key="capability.capability_key"
                            class="rounded-xl border p-4"
                        >
                            <p class="font-semibold">
                                {{ capability.capability_label }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ capability.capability_key }}
                            </p>
                        </div>
                    </div>
                
                    <div
                        class="mt-5 border-t pt-4"
                    >
                    <div
                        class="mt-5 border-t pt-4"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div>
                                <p class="font-bold">
                                    Avance de esta fase
                                </p>
                                <p
                                    class="text-sm text-muted-foreground"
                                >
                                    {{
                                        executionStatusLabel(
                                            phase.execution
                                                ?.status
                                            ?? 'pending',
                                        )
                                    }}
                                </p>
                            </div>

                            <strong>
                                {{
                                    progressPercent(
                                        phase.execution
                                            ?.progress_percentage,
                                    )
                                }}%
                            </strong>
                        </div>

                        <div
                            class="mt-3 h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full bg-primary transition-all"
                                :style="{
                                    width:
                                        `${progressPercent(
                                            phase.execution
                                                ?.progress_percentage,
                                        )}%`,
                                }"
                            />
                        </div>

                        <div class="mt-4 space-y-2">
                            <div
                                v-for="
                                    capability
                                    in phase.capabilities ?? []
                                "
                                :key="
                                    `execution-${phase.id}-${capability.capability_key}`
                                "
                                class="rounded-xl border p-3"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span class="font-medium">
                                        {{
                                            capability
                                                .capability_label
                                        }}
                                    </span>

                                    <span class="text-sm">
                                        {{
                                            executionStatusLabel(
                                                capability
                                                    .execution
                                                    ?.status
                                                ?? 'pending',
                                            )
                                        }}
                                        ·
                                        {{
                                            progressPercent(
                                                capability
                                                    .execution
                                                    ?.progress_percentage,
                                            )
                                        }}%
                                    </span>
                                </div>

                                <div
                                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full bg-primary transition-all"
                                        :style="{
                                            width:
                                                `${progressPercent(
                                                    capability
                                                        .execution
                                                        ?.progress_percentage,
                                                )}%`,
                                        }"
                                    />
                                </div>

                                <p
                                    v-if="
                                        capability.execution
                                            ?.completed_at
                                    "
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    Completado:
                                    {{
                                        capability.execution
                                            .completed_at
                                    }}
                                </p>

                                <p
                                    v-else-if="
                                        capability.execution
                                            ?.started_at
                                    "
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    Iniciado:
                                    {{
                                        capability.execution
                                            .started_at
                                    }}
                                </p>

                                <p
                                    v-if="
                                        capability.execution
                                            ?.status
                                        === 'blocked'
                                    "
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    Esta capacidad está bloqueada.
                                    El equipo está trabajando en
                                    resolver la dependencia.
                                </p>

                                <div
                                    class="mt-3 border-t pt-3"
                                >
                                    <p
                                        class="text-xs text-muted-foreground"
                                    >
                                        Puesta en marcha de esta capacidad
                                    </p>

                                    <p class="mt-1 text-sm font-medium">
                                        {{
                                            goLiveStatusLabel(
                                                capability
                                                    .go_live
                                                    ?.status,
                                            )
                                        }}
                                    </p>

                                    <div
                                        v-if="capability.go_live"
                                        class="mt-2 space-y-1 text-xs text-muted-foreground"
                                    >
                                        <p
                                            v-if="
                                                capability.go_live
                                                    .ready_at
                                            "
                                        >
                                            Lista:
                                            {{
                                                goLiveDateLabel(
                                                    capability
                                                        .go_live
                                                        .ready_at,
                                                )
                                            }}
                                        </p>

                                        <p
                                            v-if="
                                                capability.go_live
                                                    .scheduled_at
                                            "
                                        >
                                            Programada:
                                            {{
                                                goLiveDateLabel(
                                                    capability
                                                        .go_live
                                                        .scheduled_at,
                                                )
                                            }}
                                        </p>

                                        <p
                                            v-if="
                                                capability.go_live
                                                    .went_live_at
                                            "
                                        >
                                            LIVE desde:
                                            {{
                                                goLiveDateLabel(
                                                    capability
                                                        .go_live
                                                        .went_live_at,
                                                )
                                            }}
                                        </p>

                                        <p
                                            v-if="
                                                capability.go_live
                                                    .rolled_back_at
                                            "
                                        >
                                            Revertida:
                                            {{
                                                goLiveDateLabel(
                                                    capability
                                                        .go_live
                                                        .rolled_back_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        capability
                                            .recurring_solution
                                        || capability
                                            .go_live
                                            ?.status
                                            === 'live'
                                    "
                                    class="mt-3 border-t pt-3"
                                >
                                    <p
                                        class="text-xs text-muted-foreground"
                                    >
                                        Solución recurrente
                                    </p>

                                    <template
                                        v-if="
                                            capability
                                                .recurring_solution
                                        "
                                    >
                                        <p
                                            class="mt-1 text-sm font-medium"
                                        >
                                            {{
                                                capability
                                                    .recurring_solution
                                                    .service_name
                                                ?? capability
                                                    .recurring_solution
                                                    .service_slug
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Item:
                                            {{
                                                capability
                                                    .recurring_solution
                                                    .subscription_item_status
                                                ?? '—'
                                            }}
                                        </p>

                                        <Button
                                            v-if="
                                                capability
                                                    .recurring_solution
                                                    .entitlement_allowed
                                                && capability
                                                    .recurring_solution
                                                    .access_url
                                            "
                                            as-child
                                            size="sm"
                                            class="mt-3"
                                        >
                                            <a
                                                :href="
                                                    capability
                                                        .recurring_solution
                                                        .access_url
                                                "
                                            >
                                                Abrir solución
                                            </a>
                                        </Button>

                                        <p
                                            v-else
                                            class="mt-2 text-xs text-muted-foreground"
                                        >
                                            Acceso no disponible
                                            con el entitlement actual.
                                        </p>
                                    </template>

                                    <p
                                        v-else
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Go-Live completado.
                                        La activación recurrente
                                        todavía está pendiente.
                                    </p>
                                </div>

                            </div>
                        </div>
                    </div>

                        <p class="font-bold">
                            Condiciones de esta fase
                        </p>

                        <div
                            v-if="phase.estimate"
                            class="mt-3 grid gap-3 md:grid-cols-2"
                        >
                            <div
                                class="rounded-xl border p-3 text-sm"
                            >
                                <p
                                    class="text-xs text-muted-foreground"
                                >
                                    Precio
                                </p>
                                <strong>
                                    {{
                                        money(
                                            phase.estimate
                                                .price_amount,
                                            phase.estimate
                                                .currency,
                                        )
                                    }}
                                </strong>
                            </div>

                            <div
                                class="rounded-xl border p-3 text-sm"
                            >
                                <p
                                    class="text-xs text-muted-foreground"
                                >
                                    Duración estimada
                                </p>
                                <strong>
                                    {{
                                        durationLabel(
                                            phase.estimate
                                                .estimated_duration_value,
                                            phase.estimate
                                                .estimated_duration_unit,
                                        )
                                    }}
                                </strong>
                            </div>
                        </div>

                        <p
                            v-else
                            class="mt-3 text-sm text-muted-foreground"
                        >
                            Precio y duración pendientes.
                        </p>

                        <div
                            v-if="
                                phase.milestones
                                ?.length
                            "
                            class="mt-4 space-y-2"
                        >
                            <p class="text-sm font-medium">
                                Hitos de implementación
                            </p>

                            <div
                                v-for="
                                    milestone
                                    in phase.milestones
                                "
                                :key="
                                    `${phase.id}-${milestone.sequence}`
                                "
                                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3 text-sm"
                            >
                                <span>
                                    {{ milestone.sequence }}.
                                    {{ milestone.name }}
                                </span>

                                <span class="font-medium">
                                    {{
                                        money(
                                            milestone
                                                .billing_amount,
                                            milestone
                                                .currency,
                                        )
                                    }}
                                    ·
                                    {{
                                        milestoneStatusLabel(
                                            milestone
                                                .billing_status,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

</CardContent>
            </Card>

            <div class="rounded-2xl border bg-muted/20 p-4 text-sm text-muted-foreground">
                La implementación y la suscripción LAUDAAPI son conceptos separados.
                La suscripción recurrente comienza únicamente después del Go-Live de
                la capability correspondiente.
            </div>

            <div class="flex flex-wrap justify-center gap-3">
                <Button as-child variant="outline">
                    <Link v-if="roadmap_url" :href="roadmap_url">
                        <ArrowLeft class="mr-2 size-4" />
                        Volver al Roadmap
                    </Link>
                </Button>

                <Button as-child variant="ghost">
                    <Link :href="diagnosis_url">
                        Volver al Diagnóstico
                    </Link>
                </Button>
            </div>
        </div>
    </main>
    </AppLayout>
</template>
