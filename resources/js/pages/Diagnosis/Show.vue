<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import TransformationProgressChecklist from '@/components/diagnosis/TransformationProgressChecklist.vue';
import TransformationQuickActions from '@/components/diagnosis/TransformationQuickActions.vue';
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Clock3,
    ShieldCheck,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DigitalDiagnosisWizard from './DigitalDiagnosisWizard.vue';
import { maturityDimensions } from '@/lib/diagnostico-lauda360-v1';

interface Assessment {
    id: number;
    status: 'draft' | 'in_progress' | 'submitted' | 'reviewed' | string;
    current_step: number;
    answers: Record<string, number>;
    notes: Record<string, string>;
    business_activity_type: string | null;
    business_sector: string | null;
    business_sector_other: string | null;
    customer_market: string | null;
    sales_channels: string[];
    sales_channel_other: string | null;
    logistics_operation_types: string[];
    logistics_operation_other: string | null;
    business_activity_description: string | null;
    business_profile_completed_at?: string | null;
    started_at?: string | null;
    submitted_at?: string | null;
    reviewed_at?: string | null;
    updated_at?: string | null;
}

interface Result {
    maturity_score: number | null;
    maturity_level: string | null;
    capacity_score: number | null;
    urgency_score: number | null;
    urgency_level: string | null;
    dimension_scores: Record<string, number>;
    summary: string | null;
    priorities: string[];
    published_at: string | null;
}

interface Organization {
    id: number | string;
    name: string;
}

interface BusinessProfileOptions {
    activity_types: Record<string, string>;
    sectors: Record<string, string>;
    customer_markets: Record<string, string>;
    sales_channels: Record<string, string>;
    logistics_operation_types: Record<string, string>;
}

interface Endpoints {
    implementation_plan_url: string | null;

    update: string;
    submit: string;
    back: string;
}

const props = defineProps<{
    assessment: Assessment;
    organization: Organization;
    result: Result | null;
    expanded_report: {
        id: number;
        version: number;
        published_at: string | null;
    } | null;
    endpoints: Endpoints;
    businessProfileOptions: BusinessProfileOptions;
    transformation_progress: Record<string, any> | null;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Inicio',
        href: '/app',
    },
    {
        title: 'Diagnóstico 360',
        href: props.endpoints.back,
    },
    {
        title:
            props.assessment.status === 'reviewed'
                ? 'Resultado'
                : 'Evaluación',
        href: `/diagnostico/${props.assessment.id}`,
    },
]);

const saveStatus = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const submitError = ref<string | null>(null);

const profileError = ref<string | null>(null);
const profileSaving = ref(false);
const profileSaved = ref(
    Boolean(props.assessment.business_profile_completed_at),
);

const businessProfile = reactive({
    business_activity_type: props.assessment.business_activity_type ?? '',
    business_sector: props.assessment.business_sector ?? '',
    business_sector_other: props.assessment.business_sector_other ?? '',
    customer_market: props.assessment.customer_market ?? '',
    sales_channels: [...(props.assessment.sales_channels ?? [])],
    sales_channel_other: props.assessment.sales_channel_other ?? '',
    logistics_operation_types: [
        ...(props.assessment.logistics_operation_types ?? []),
    ],
    logistics_operation_other: props.assessment.logistics_operation_other ?? '',
    business_activity_description:
        props.assessment.business_activity_description ?? '',
});

const profileComplete = computed(() => {
    if (
        !businessProfile.business_activity_type ||
        !businessProfile.business_sector ||
        !businessProfile.customer_market ||
        businessProfile.sales_channels.length === 0 ||
        businessProfile.business_activity_description.trim().length < 20
    ) {
        return false;
    }

    if (
        businessProfile.business_sector === 'other' &&
        !businessProfile.business_sector_other.trim()
    ) {
        return false;
    }

    if (
        businessProfile.sales_channels.includes('other') &&
        !businessProfile.sales_channel_other.trim()
    ) {
        return false;
    }

    if (businessProfile.business_sector === 'logistics') {
        if (businessProfile.logistics_operation_types.length === 0) {
            return false;
        }

        if (
            businessProfile.logistics_operation_types.includes('other') &&
            !businessProfile.logistics_operation_other.trim()
        ) {
            return false;
        }
    }

    return true;
});

function businessProfilePayload() {
    return {
        business_activity_type: businessProfile.business_activity_type,
        business_sector: businessProfile.business_sector,
        business_sector_other: businessProfile.business_sector_other,
        customer_market: businessProfile.customer_market,
        sales_channels: businessProfile.sales_channels,
        sales_channel_other: businessProfile.sales_channel_other,
        logistics_operation_types:
            businessProfile.business_sector === 'logistics'
                ? businessProfile.logistics_operation_types
                : [],
        logistics_operation_other:
            businessProfile.business_sector === 'logistics'
                ? businessProfile.logistics_operation_other
                : '',
        business_activity_description:
            businessProfile.business_activity_description,
    };
}

const isEditable = computed(() =>
    ['draft', 'in_progress'].includes(props.assessment.status),
);

function saveBusinessProfile() {
    if (!isEditable.value) return;

    if (!profileComplete.value) {
        profileError.value =
            'Complete los campos obligatorios del perfil comercial.';
        return;
    }

    profileError.value = null;
    profileSaving.value = true;

    router.patch(
        props.endpoints.update,
        {
            answers: props.assessment.answers ?? {},
            notes: props.assessment.notes ?? {},
            current_step: props.assessment.current_step ?? 1,
            ...businessProfilePayload(),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                profileSaved.value = true;
                saveStatus.value = 'saved';
            },
            onError: () => {
                profileError.value =
                    'No se pudo guardar el perfil comercial. Revise los campos.';
            },
            onFinish: () => {
                profileSaving.value = false;
            },
        },
    );
}

const isSubmitted = computed(() => props.assessment.status === 'submitted');
const isReviewed = computed(
    () => props.assessment.status === 'reviewed' && props.result !== null,
);

const statusLabel = computed(() => {
    switch (props.assessment.status) {
        case 'draft':
            return 'Pendiente de iniciar';
        case 'in_progress':
            return 'En progreso';
        case 'submitted':
            return 'En revisión por LAUDA';
        case 'reviewed':
            return 'Resultado disponible';
        default:
            return props.assessment.status;
    }
});

const saveStatusLabel = computed(() => {
    if (saveStatus.value === 'saving') return 'Guardando…';
    if (saveStatus.value === 'saved') return 'Cambios guardados';
    if (saveStatus.value === 'error') return 'No se pudo guardar';
    return null;
});

function saveDiagnosis(payload: {
    answers: Record<string, number>;
    notes: Record<string, string>;
    step: number;
}) {
    if (!isEditable.value) return;

    saveStatus.value = 'saving';

    router.patch(
        props.endpoints.update,
        {
            answers: payload.answers,
            notes: payload.notes,
            current_step: payload.step,
            ...businessProfilePayload(),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                saveStatus.value = 'saved';
            },
            onError: () => {
                saveStatus.value = 'error';
            },
        },
    );
}

function submitDiagnosis(payload: {
    answers: Record<string, number>;
    notes: Record<string, string>;
    maturityScore: number;
    capacityScore: number;
    urgencyScore: number;
}) {
    if (!isEditable.value) return;

    submitError.value = null;

    router.post(
        props.endpoints.submit,
        {
            answers: payload.answers,
            notes: payload.notes,
            ...businessProfilePayload(),
        },
        {
            preserveScroll: true,
            onError: () => {
                submitError.value =
                    'No pudimos enviar el diagnóstico. Revise las respuestas e inténtelo nuevamente.';
            },
        },
    );
}
</script>

<template>
    <Head title="Diagnóstico LAUDA 360" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-muted/20">
        <header class="border-b bg-background">
            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <Button
                        as-child
                        variant="ghost"
                        size="icon"
                        class="mt-0.5 shrink-0"
                    >
                        <Link :href="endpoints.back" aria-label="Volver">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                            >
                                LAUDA Transformación Digital 360
                            </p>
                            <Badge variant="outline">
                                {{ statusLabel }}
                            </Badge>
                        </div>

                        <h1
                            class="mt-1 text-2xl font-black tracking-tight sm:text-3xl"
                        >
                            Diagnóstico Digital 360
                        </h1>

                        <div
                            class="mt-2 flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Building2 class="h-4 w-4 shrink-0" />
                            <span class="truncate">
                                {{ organization.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                >
                    <div class="flex items-center gap-1.5">
                        <ShieldCheck class="h-4 w-4" />
                        <span>Acceso privado</span>
                    </div>

                    <div
                        v-if="saveStatusLabel"
                        class="flex items-center gap-1.5"
                    >
                        <CheckCircle2
                            v-if="saveStatus === 'saved'"
                            class="h-4 w-4"
                        />
                        <Clock3 v-else class="h-4 w-4" />
                        <span>{{ saveStatusLabel }}</span>
                    </div>
                </div>
            </div>
        </header>

        <main
            class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
        >
            <div class="mb-8 space-y-6">
                <TransformationProgressChecklist
                    :progress="transformation_progress"
                />
                <TransformationQuickActions
                    mode="client"
                    :assessment-id="assessment.id"
                    :progress="transformation_progress"
                    :implementation-plan-url="endpoints.implementation_plan_url"
                />
            </div>

            <Card v-if="isEditable" class="mb-6">
                <CardContent class="space-y-5 p-5 sm:p-6">
                    <div>
                        <p
                            class="text-xs font-black tracking-widest text-primary uppercase"
                        >
                            Perfil comercial
                        </p>

                        <h2 class="mt-1 text-lg font-black">
                            Contexto de la empresa
                        </h2>

                        <p
                            class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground"
                        >
                            Esta información no modifica la puntuación. LAUDA la
                            utiliza para interpretar el diagnóstico según el
                            modelo real del negocio.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="space-y-1.5 text-sm font-semibold">
                            <span> Actividad comercial principal * </span>

                            <select
                                v-model="businessProfile.business_activity_type"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Seleccione</option>

                                <option
                                    v-for="(
                                        label, value
                                    ) in businessProfileOptions.activity_types"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </label>

                        <label class="space-y-1.5 text-sm font-semibold">
                            <span>Sector *</span>

                            <select
                                v-model="businessProfile.business_sector"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Seleccione</option>

                                <option
                                    v-for="(
                                        label, value
                                    ) in businessProfileOptions.sectors"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </label>

                        <label
                            v-if="businessProfile.business_sector === 'other'"
                            class="space-y-1.5 text-sm font-semibold md:col-span-2"
                        >
                            <span>Indique el sector *</span>

                            <input
                                v-model="businessProfile.business_sector_other"
                                maxlength="120"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                            />
                        </label>

                        <label class="space-y-1.5 text-sm font-semibold">
                            <span>Mercado principal *</span>

                            <select
                                v-model="businessProfile.customer_market"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Seleccione</option>

                                <option
                                    v-for="(
                                        label, value
                                    ) in businessProfileOptions.customer_markets"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-semibold">
                            Canales comerciales *
                        </p>

                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <label
                                v-for="(
                                    label, value
                                ) in businessProfileOptions.sales_channels"
                                :key="value"
                                class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                            >
                                <input
                                    v-model="businessProfile.sales_channels"
                                    type="checkbox"
                                    :value="value"
                                />

                                <span>{{ label }}</span>
                            </label>
                        </div>
                    </div>

                    <label
                        v-if="businessProfile.sales_channels.includes('other')"
                        class="block space-y-1.5 text-sm font-semibold"
                    >
                        <span>Otro canal comercial *</span>

                        <input
                            v-model="businessProfile.sales_channel_other"
                            maxlength="120"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />
                    </label>

                    <div
                        v-if="businessProfile.business_sector === 'logistics'"
                        class="space-y-3 rounded-xl border bg-muted/20 p-4"
                    >
                        <div>
                            <p class="text-sm font-bold">
                                Tipo de operación logística *
                            </p>

                            <p class="mt-1 text-xs text-muted-foreground">
                                Seleccione todas las operaciones que realiza
                                actualmente la empresa.
                            </p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="(
                                    label, value
                                ) in businessProfileOptions.logistics_operation_types"
                                :key="value"
                                class="flex items-center gap-2 rounded-lg border bg-background p-3 text-sm"
                            >
                                <input
                                    v-model="
                                        businessProfile.logistics_operation_types
                                    "
                                    type="checkbox"
                                    :value="value"
                                />

                                <span>{{ label }}</span>
                            </label>
                        </div>

                        <label
                            v-if="
                                businessProfile.logistics_operation_types.includes(
                                    'other',
                                )
                            "
                            class="block space-y-1.5 text-sm font-semibold"
                        >
                            <span> Otra operación logística * </span>

                            <input
                                v-model="
                                    businessProfile.logistics_operation_other
                                "
                                maxlength="120"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                            />
                        </label>
                    </div>

                    <label class="block space-y-1.5 text-sm font-semibold">
                        <span>
                            Describa brevemente la actividad principal *
                        </span>

                        <textarea
                            v-model="
                                businessProfile.business_activity_description
                            "
                            rows="4"
                            maxlength="2000"
                            placeholder="Ej.: Venta, instalación y mantenimiento de equipos para empresas y clientes finales."
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm leading-6"
                        />

                        <span
                            class="block text-xs font-normal text-muted-foreground"
                        >
                            Mínimo 20 caracteres. Este contexto se utilizará
                            posteriormente en la conclusión y el Informe
                            Ampliado.
                        </span>
                    </label>

                    <p
                        v-if="profileError"
                        class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm font-semibold text-destructive"
                    >
                        {{ profileError }}
                    </p>

                    <div
                        class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-xs text-muted-foreground">
                            Guarde este perfil para habilitar las 51 preguntas
                            evaluativas.
                        </p>

                        <Button
                            type="button"
                            :disabled="profileSaving || !profileComplete"
                            @click="saveBusinessProfile"
                        >
                            {{
                                profileSaving
                                    ? 'Guardando…'
                                    : profileSaved
                                      ? 'Actualizar perfil'
                                      : 'Guardar perfil y continuar'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <template v-if="isSubmitted">
                <Card class="border-primary/20 bg-primary/5">
                    <CardContent class="p-6 sm:p-8">
                        <div class="flex items-start gap-4">
                            <CheckCircle2
                                class="mt-0.5 h-6 w-6 shrink-0 text-primary"
                            />
                            <div>
                                <p class="text-lg font-black">
                                    Diagnóstico enviado
                                </p>
                                <p
                                    class="mt-2 max-w-3xl text-sm leading-7 text-muted-foreground"
                                >
                                    Hemos recibido sus respuestas correctamente.
                                    El diagnóstico queda bloqueado mientras el
                                    equipo de LAUDA revisa los resultados y
                                    prepara las conclusiones, prioridades de
                                    transformación y modalidad de acompañamiento
                                    recomendada.
                                </p>
                                <p class="mt-3 text-sm font-semibold">
                                    Le notificaremos cuando su resultado esté
                                    disponible.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <template v-else-if="isReviewed && result">
                <div class="space-y-5">
                    <Card
                        class="border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20"
                        id="informe-diagnostico"
                    >
                        <CardContent class="p-6 sm:p-8">
                            <div class="flex items-start gap-4">
                                <CheckCircle2
                                    class="mt-0.5 h-6 w-6 shrink-0 text-emerald-600"
                                />
                                <div>
                                    <p class="text-lg font-black">
                                        Resultado revisado por LAUDA
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-7 text-muted-foreground"
                                    >
                                        La revisión del Diagnóstico LAUDA 360 ha
                                        sido completada. Este es el resultado
                                        oficial de la evaluación inicial.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardContent class="p-5">
                                <p
                                    class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                >
                                    Madurez digital
                                </p>
                                <p class="mt-2 text-4xl font-black">
                                    {{ result.maturity_score ?? '—' }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ result.maturity_level || '—' }}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-5">
                                <p
                                    class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                >
                                    Capacidad interna
                                </p>
                                <p class="mt-2 text-4xl font-black">
                                    {{ result.capacity_score ?? '—' }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    /100
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-5">
                                <p
                                    class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                >
                                    Urgencia
                                </p>
                                <p class="mt-2 text-4xl font-black">
                                    {{ result.urgency_score ?? '—' }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ result.urgency_level || '—' }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardContent class="p-6">
                            <p
                                class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                            >
                                Conclusión ejecutiva
                            </p>
                            <p
                                class="mt-3 text-sm leading-7 whitespace-pre-line"
                            >
                                {{ result.summary }}
                            </p>
                        </CardContent>
                    </Card>

                    <div class="grid gap-5 lg:grid-cols-[1fr_0.75fr]">
                        <Card>
                            <CardContent class="p-6">
                                <p
                                    class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                                >
                                    Prioridades
                                </p>
                                <ol class="mt-4 space-y-3">
                                    <li
                                        v-for="(
                                            priority, index
                                        ) in result.priorities"
                                        :key="`${index}-${priority}`"
                                        class="flex gap-3 rounded-xl border p-3"
                                    >
                                        <span
                                            class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[#F53003]/10 text-xs font-black text-[#F53003]"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <span class="text-sm leading-6">
                                            {{ priority }}
                                        </span>
                                    </li>
                                </ol>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <p
                                    class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                                >
                                    Capacidad interna
                                </p>
                                <p class="mt-3 text-xl font-black">
                                    {{ result.capacity_score ?? '—' }}/100
                                </p>
                                <p
                                    class="mt-3 text-sm leading-6 text-muted-foreground"
                                >
                                    Este indicador ayuda a dimensionar la
                                    capacidad disponible para ejecutar cambios.
                                    No define una modalidad comercial ni implica
                                    contratación.
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardContent class="p-6">
                            <p
                                class="text-[10px] font-black tracking-[0.16em] text-[#F53003] uppercase"
                            >
                                Resultado por dimensión
                            </p>
                            <div
                                class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div
                                    v-for="dimension in maturityDimensions"
                                    :key="dimension.id"
                                    class="rounded-xl border p-4"
                                >
                                    <p class="text-xs font-bold">
                                        {{ dimension.shortTitle }}
                                    </p>
                                    <p class="mt-2 text-2xl font-black">
                                        {{
                                            result.dimension_scores?.[
                                                dimension.id
                                            ] ?? '—'
                                        }}
                                    </p>
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        /100
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card id="informe-ampliado" class="bg-muted/20">
                        <CardContent
                            class="p-5 text-sm leading-6 text-muted-foreground"
                        >
                            <p>
                                Tu Diagnóstico 360 incluye sin costo el Informe
                                Ampliado, el Roadmap Detallado y el Plan de
                                Implementación. Estos documentos permiten
                                comprender las implicaciones del cambio antes
                                de tomar cualquier decisión comercial.
                            </p>

                            <div v-if="expanded_report" class="mt-4">
                                <Button as-child>
                                    <Link
                                        :href="`/diagnostico/${assessment.id}/informe-ampliado`"
                                    >
                                        Ver Informe Ampliado
                                    </Link>
                                </Button>
                            </div>

                            <div
                                v-else
                                class="mt-4 rounded-xl border border-dashed p-4"
                            >
                                El Informe Ampliado será preparado y presentado
                                como parte del Diagnóstico 360.
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <template v-else>
                <Card
                    v-if="submitError"
                    class="mb-6 border-destructive/30 bg-destructive/5"
                >
                    <CardContent class="p-4 text-sm text-destructive sm:p-5">
                        {{ submitError }}
                    </CardContent>
                </Card>

                <div
                    v-if="!profileSaved"
                    class="mb-4 rounded-xl border border-amber-300/50 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Complete y guarde el Perfil comercial para habilitar las 51
                    preguntas del Diagnóstico LAUDA 360.
                </div>

                <DigitalDiagnosisWizard
                    v-if="profileSaved"
                    :initial-answers="assessment.answers ?? {}"
                    :initial-notes="assessment.notes ?? {}"
                    :initial-step="assessment.current_step ?? 1"
                    :read-only="false"
                    @save="saveDiagnosis"
                    @submit="submitDiagnosis"
                />
            </template>
        </main>
    </div>
    </AppLayout>
</template>
