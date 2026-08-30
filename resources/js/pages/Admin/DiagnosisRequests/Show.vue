<script setup lang="ts">
import TransformationProgressChecklist from '@/components/diagnosis/TransformationProgressChecklist.vue';
import TransformationQuickActions from '@/components/diagnosis/TransformationQuickActions.vue';
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    Clock3,
    Mail,
    ShieldAlert,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import {
    internalCapacityQuestions,
    maturityDimensions,
    urgencyQuestions,
} from '@/lib/diagnostico-lauda360-v1';

type Assessment = {
    id: number;
    organization_name: string;

    business_activity_type: string | null;
    business_sector: string | null;
    business_sector_other: string | null;
    customer_market: string | null;
    sales_channels: string[];
    sales_channel_other: string | null;
    logistics_operation_types: string[];
    logistics_operation_other: string | null;
    business_activity_description: string | null;
    business_profile_completed_at: string | null;
    status: string;
    current_step: number;
    answers: Record<string, number>;
    notes: Record<string, string>;
    maturity_score: number | null;
    capacity_score: number | null;
    urgency_score: number | null;
    dimension_scores: Record<string, number>;
    maturity_level: string | null;
    urgency_level: string | null;
    recommended_modality: string | null;
    recommended_modality_label: string | null;
    review_required: boolean;
    review_summary: string | null;
    review_priorities: string[];
    final_modality: string | null;
    final_modality_label: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    published_at: string | null;
    reviewed_by: {
        id: number;
        name: string;
        email: string;
    } | null;
};

type BusinessProfileOptions = {
    activity_types: Record<string, string>;
    sectors: Record<string, string>;
    customer_markets: Record<string, string>;
    sales_channels: Record<string, string>;
    logistics_operation_types: Record<string, string>;
};

type Workflow = {
    public_id: string;
    status: string;
    review_notes: string | null;
    rejection_reason: string | null;
    approved_at: string | null;
    invitation_sent_at: string | null;
    invitation_expires_at: string | null;
    invitation_accepted_at: string | null;
    rejected_at: string | null;
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
        must_change_password: boolean;
    } | null;
    assessment: Assessment | null;
};

const props = defineProps<{
    contact: {
        id: number;
        name: string;
        company: string | null;
        email: string;
        phone: string | null;
        topic: string;
        message: string | null;
        metadata: Record<string, any> | null;
        created_at: string | null;
    };
    workflow: Workflow | null;
    statuses: string[];
    businessProfileOptions: BusinessProfileOptions;
    transformation_progress: Record<string, any> | null;

}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Diagnósticos 360', href: '/admin/diagnosis-requests' },
    {
        title: props.contact.company || props.contact.name,
        href: `/admin/diagnosis-requests/${props.contact.id}`,
    },
];

const assessment = computed(() => props.workflow?.assessment ?? null);

const invitationExpired = computed(() => {
    if (
        !props.workflow?.invitation_expires_at ||
        props.workflow.invitation_accepted_at
    ) {
        return false;
    }

    const expiresAt = Date.parse(props.workflow.invitation_expires_at);

    return Number.isFinite(expiresAt) && expiresAt <= Date.now();
});

function formatInvitationDate(value: string | null): string {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

const statusForm = useForm({
    status: props.workflow?.status ?? 'pending',
    review_notes: props.workflow?.review_notes ?? '',
});

const approveForm = useForm({});
const resendForm = useForm({});
const rejectForm = useForm({
    reason: '',
});

const publishForm = useForm({
    review_summary: assessment.value?.review_summary ?? '',
    review_priorities: assessment.value?.review_priorities?.join('\n') ?? '',
    final_modality:
        assessment.value?.final_modality ??
        assessment.value?.recommended_modality ??
        'assisted',
});

const reviewSubmitting = ref(false);

type ReviewToastType = 'success' | 'warning' | 'error';

const reviewToast = ref<{
    type: ReviewToastType;
    message: string;
} | null>(null);

let reviewToastTimer: ReturnType<typeof setTimeout> | null = null;

function showReviewToast(type: ReviewToastType, message: string) {
    reviewToast.value = { type, message };

    if (reviewToastTimer) {
        clearTimeout(reviewToastTimer);
    }

    reviewToastTimer = setTimeout(() => {
        reviewToast.value = null;
        reviewToastTimer = null;
    }, 4500);
}

const canPublish = computed(() =>
    ['submitted', 'reviewed'].includes(assessment.value?.status ?? ''),
);

const groups = computed(() => [
    ...maturityDimensions.map((dimension) => ({
        id: dimension.id,
        title: dimension.title,
        score: assessment.value?.dimension_scores?.[dimension.id] ?? null,
        questions: dimension.questions,
    })),
    {
        id: 'capacity',
        title: 'Capacidad interna',
        score: assessment.value?.capacity_score ?? null,
        questions: internalCapacityQuestions,
    },
    {
        id: 'urgency',
        title: 'Urgencia',
        score: assessment.value?.urgency_score ?? null,
        questions: urgencyQuestions,
    },
]);

function answerLabel(question: any, score: number | undefined): string {
    if (!score) return 'Sin respuesta';
    return (
        question.choices.find((choice: any) => choice.score === score)?.label ??
        `Nivel ${score}`
    );
}

function statusLabel(status: string): string {
    return (
        {
            pending: 'Pendiente',
            under_review: 'En revisión',
            more_info_required: 'Más información',
            approved: 'Aprobada',
            invited: 'Invitación enviada',
            active: 'Acceso activo',
            rejected: 'Rechazada',
        }[status] ?? status
    );
}

function businessProfileLabel(
    group: keyof BusinessProfileOptions,
    value: string | null,
): string {
    if (!value) return '—';

    return props.businessProfileOptions[group]?.[value] ?? value;
}

function businessProfileLabels(
    group: keyof BusinessProfileOptions,
    values: string[] | null | undefined,
): string {
    if (!values?.length) return '—';

    return values.map((value) => businessProfileLabel(group, value)).join(', ');
}

function assessmentStatusLabel(status: string): string {
    return (
        {
            draft: 'No iniciado',
            in_progress: 'En progreso',
            submitted: 'Enviado · pendiente de revisión',
            reviewed: 'Revisado · resultado publicado',
        }[status] ?? status
    );
}

function postStatus() {
    statusForm.post(`/admin/diagnosis-requests/${props.contact.id}/status`, {
        preserveScroll: true,
    });
}

function approve() {
    approveForm.post(`/admin/diagnosis-requests/${props.contact.id}/approve`, {
        preserveScroll: true,
    });
}

function resend() {
    resendForm.post(`/admin/diagnosis-requests/${props.contact.id}/resend`, {
        preserveScroll: true,
    });
}

function reject() {
    rejectForm.post(`/admin/diagnosis-requests/${props.contact.id}/reject`, {
        preserveScroll: true,
    });
}

function reviewPayload() {
    return {
        review_summary: publishForm.review_summary.trim(),
        final_modality: publishForm.final_modality,
        review_priorities: publishForm.review_priorities
            .split(/\r?\n/)
            .map((item) => item.trim())
            .filter(Boolean),
    };
}

function submitReview(endpoint: 'save-review' | 'publish-result') {
    publishForm.clearErrors();
    reviewSubmitting.value = true;

    router.post(
        `/admin/diagnosis-requests/${props.contact.id}/${endpoint}`,
        reviewPayload(),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const flash = (page.props.flash ?? {}) as {
                    success?: string | null;
                    warning?: string | null;
                };

                if (flash.warning) {
                    showReviewToast('warning', flash.warning);
                    return;
                }

                showReviewToast(
                    'success',
                    endpoint === 'publish-result'
                        ? 'Resultado publicado al cliente.'
                        : 'Borrador guardado correctamente.',
                );
            },
            onError: (errors) => {
                publishForm.setError(errors);

                showReviewToast(
                    'error',
                    endpoint === 'publish-result'
                        ? 'No se pudo publicar. Revise los campos de la revisión.'
                        : 'No se pudo guardar el borrador. Revise los campos.',
                );
            },
            onFinish: () => {
                reviewSubmitting.value = false;
            },
        },
    );
}

function saveReviewDraft() {
    if (assessment.value?.status !== 'submitted') return;

    submitReview('save-review');
}

function publish() {
    const action =
        assessment.value?.status === 'reviewed'
            ? 'actualizar y republicar'
            : 'publicar';

    const confirmed = window.confirm(
        `¿Desea ${action} este resultado? El cliente podrá verlo y se enviará una notificación por correo.`,
    );

    if (!confirmed) return;

    submitReview('publish-result');
}
</script>

<template>
    <Head title="Revisión Diagnóstico 360" />

    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="reviewToast"
            class="fixed top-4 right-4 z-[100] w-[calc(100%-2rem)] max-w-sm rounded-xl border px-4 py-3 shadow-lg sm:top-6 sm:right-6"
            :class="{
                'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100':
                    reviewToast.type === 'success',
                'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100':
                    reviewToast.type === 'warning',
                'border-red-200 bg-red-50 text-red-950 dark:border-red-900 dark:bg-red-950 dark:text-red-100':
                    reviewToast.type === 'error',
            }"
            role="status"
            aria-live="polite"
        >
            <div class="flex items-start gap-3">
                <CheckCircle2
                    v-if="reviewToast.type === 'success'"
                    class="mt-0.5 h-5 w-5 shrink-0"
                />
                <ShieldAlert v-else class="mt-0.5 h-5 w-5 shrink-0" />

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold">
                        {{ reviewToast.message }}
                    </p>
                </div>

                <button
                    type="button"
                    class="shrink-0 text-xs font-black opacity-60 transition hover:opacity-100"
                    aria-label="Cerrar notificación"
                    @click="reviewToast = null"
                >
                    ×
                </button>
            </div>
        </div>
    </Transition>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-[1600px] space-y-6 p-4 md:p-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <Link
                        href="/admin/diagnosis-requests"
                        class="inline-flex items-center gap-1 text-xs font-bold text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft class="h-3.5 w-3.5" />
                        Volver a Diagnósticos 360
                    </Link>

                    <p
                        class="mt-4 text-[10px] font-black tracking-[0.18em] text-[#F53003] uppercase"
                    >
                        LAUDA Transformación Digital 360
                    </p>
                    <h1
                        class="mt-1 text-2xl font-black tracking-tight md:text-3xl"
                    >
                        {{ contact.company || 'Empresa por definir' }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ contact.name }} · {{ contact.email }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Badge variant="outline">
                        Acceso:
                        {{ statusLabel(workflow?.status ?? 'pending') }}
                    </Badge>
                    <Badge v-if="assessment" variant="outline">
                        Diagnóstico:
                        {{ assessmentStatusLabel(assessment.status) }}
                    </Badge>
                </div>
            </div>

            <!-- DIAGNOSIS360_ADMIN_QUICK_ACTIONS_TOP -->
            <TransformationQuickActions
                mode="admin"
                :contact-id="contact.id"
                :progress="transformation_progress"
            />

            <!-- DIAGNOSIS360_ADMIN_TWO_COLUMN_LAYOUT -->
            <div
                class="grid items-start gap-6 lg:grid-cols-[minmax(340px,0.8fr)_minmax(0,1.2fr)]"
            >
                <!-- DIAGNOSIS360_ADMIN_CHECKLIST_LEFT -->
                <aside class="min-w-0 lg:sticky lg:top-6">
                    <TransformationProgressChecklist
                        :progress="transformation_progress"
                        :admin="true"
                    />
                </aside>

                <!-- DIAGNOSIS360_ADMIN_RIGHT_COLUMN -->
                <div class="min-w-0 space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Solicitud y acceso</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Empresa
                                    </p>
                                    <p class="font-semibold">
                                        {{ contact.company || '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        Contacto
                                    </p>
                                    <p class="font-semibold">
                                        {{ contact.name }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ contact.email }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ contact.phone || '—' }}
                                    </p>
                                </div>
                                <div v-if="contact.message">
                                    <p class="text-xs text-muted-foreground">
                                        Mensaje
                                    </p>
                                    <p class="leading-6">
                                        {{ contact.message }}
                                    </p>
                                </div>
                            </div>

                            <template
                                v-if="
                                    !workflow ||
                                    [
                                        'pending',
                                        'under_review',
                                        'more_info_required',
                                    ].includes(workflow.status)
                                "
                            >
                                <div class="border-t pt-4">
                                    <label class="text-xs font-bold">
                                        Estado de revisión
                                    </label>
                                    <select
                                        v-model="statusForm.status"
                                        class="mt-2 h-10 w-full rounded-xl border bg-background px-3 text-sm"
                                    >
                                        <option value="pending">
                                            Pendiente
                                        </option>
                                        <option value="under_review">
                                            En revisión
                                        </option>
                                        <option value="more_info_required">
                                            Más información
                                        </option>
                                    </select>

                                    <label class="mt-3 block text-xs font-bold">
                                        Notas internas
                                    </label>
                                    <textarea
                                        v-model="statusForm.review_notes"
                                        rows="4"
                                        class="mt-2 w-full rounded-xl border bg-background p-3 text-sm"
                                    />

                                    <Button
                                        class="mt-3 w-full"
                                        variant="outline"
                                        :disabled="statusForm.processing"
                                        @click="postStatus"
                                    >
                                        Guardar estado
                                    </Button>
                                </div>

                                <Button
                                    class="w-full bg-[#F53003] text-white hover:bg-[#D92A03]"
                                    :disabled="approveForm.processing"
                                    @click="approve"
                                >
                                    Aprobar / habilitar diagnóstico
                                </Button>
                            </template>

                            <div
                                v-if="workflow?.invitation_sent_at"
                                class="space-y-2 rounded-xl border bg-muted/20 p-3 text-xs"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span class="font-bold"
                                        >Invitación privada</span
                                    >

                                    <Badge
                                        v-if="workflow.invitation_accepted_at"
                                        class="bg-emerald-600 text-white"
                                    >
                                        Cuenta activada
                                    </Badge>

                                    <Badge
                                        v-else-if="invitationExpired"
                                        variant="destructive"
                                    >
                                        Enlace expirado
                                    </Badge>

                                    <Badge v-else variant="outline">
                                        Vigente · 72h
                                    </Badge>
                                </div>

                                <p class="text-muted-foreground">
                                    Enviada:
                                    {{
                                        formatInvitationDate(
                                            workflow.invitation_sent_at,
                                        )
                                    }}
                                </p>

                                <p
                                    v-if="workflow.invitation_expires_at"
                                    class="text-muted-foreground"
                                >
                                    Vencimiento del enlace:
                                    {{
                                        formatInvitationDate(
                                            workflow.invitation_expires_at,
                                        )
                                    }}
                                </p>

                                <p
                                    v-if="workflow.invitation_accepted_at"
                                    class="font-semibold text-emerald-700 dark:text-emerald-300"
                                >
                                    La cuenta ya está activa. El vencimiento del
                                    enlace no limita el acceso por Iniciar
                                    sesión.
                                </p>

                                <p
                                    v-else-if="invitationExpired"
                                    class="font-semibold text-destructive"
                                >
                                    Reenvíe la invitación para generar un nuevo
                                    enlace con 72 horas de vigencia.
                                </p>

                                <p v-else class="text-muted-foreground">
                                    Este enlace solo se necesita para activar el
                                    acceso inicial.
                                </p>
                            </div>

                            <Button
                                v-if="
                                    workflow &&
                                    ['approved', 'invited', 'active'].includes(
                                        workflow.status,
                                    )
                                "
                                class="w-full"
                                variant="outline"
                                :disabled="resendForm.processing"
                                @click="resend"
                            >
                                <Mail class="mr-2 h-4 w-4" />
                                Reenviar invitación
                            </Button>

                            <div
                                v-if="
                                    workflow &&
                                    workflow.status !== 'active' &&
                                    workflow.status !== 'rejected'
                                "
                                class="border-t pt-4"
                            >
                                <label
                                    class="text-xs font-bold text-destructive"
                                >
                                    Motivo de rechazo
                                </label>
                                <textarea
                                    v-model="rejectForm.reason"
                                    rows="3"
                                    class="mt-2 w-full rounded-xl border bg-background p-3 text-sm"
                                />
                                <Button
                                    class="mt-3 w-full"
                                    variant="destructive"
                                    :disabled="
                                        rejectForm.processing ||
                                        !rejectForm.reason.trim()
                                    "
                                    @click="reject"
                                >
                                    Rechazar solicitud
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="workflow?.user">
                        <CardHeader>
                            <CardTitle>Acceso del cliente</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p class="font-semibold">
                                {{ workflow.user.name }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ workflow.user.email }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Rol: {{ workflow.user.role }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card v-if="!assessment">
                        <CardContent class="p-6 text-sm text-muted-foreground">
                            El diagnóstico todavía no ha sido creado. Se genera
                            al aprobar la solicitud.
                        </CardContent>
                    </Card>

                    <template v-else>
                        <Card>
                            <CardHeader>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <CardTitle>Resultado calculado</CardTitle>
                                    <Badge
                                        v-if="assessment.review_required"
                                        class="bg-amber-500 text-white"
                                    >
                                        <ShieldAlert class="mr-1 h-3.5 w-3.5" />
                                        Revisión obligatoria
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div class="rounded-2xl border p-4">
                                        <p
                                            class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            Madurez
                                        </p>
                                        <p class="mt-2 text-3xl font-black">
                                            {{
                                                assessment.maturity_score ?? '—'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                assessment.maturity_level || '—'
                                            }}
                                        </p>
                                    </div>

                                    <div class="rounded-2xl border p-4">
                                        <p
                                            class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            Capacidad
                                        </p>
                                        <p class="mt-2 text-3xl font-black">
                                            {{
                                                assessment.capacity_score ?? '—'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            /100
                                        </p>
                                    </div>

                                    <div class="rounded-2xl border p-4">
                                        <p
                                            class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            Urgencia
                                        </p>
                                        <p class="mt-2 text-3xl font-black">
                                            {{
                                                assessment.urgency_score ?? '—'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                assessment.urgency_level || '—'
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-4 rounded-2xl border bg-muted/20 p-4"
                                >
                                    <p
                                        class="text-xs font-bold text-muted-foreground"
                                    >
                                        Modalidad automática
                                    </p>
                                    <p class="mt-1 font-black">
                                        {{
                                            assessment.recommended_modality_label ||
                                            'Pendiente'
                                        }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            v-if="
                                assessment &&
                                (assessment.business_profile_completed_at ||
                                    assessment.business_activity_type)
                            "
                        >
                            <CardHeader>
                                <CardTitle> Perfil comercial </CardTitle>
                                <p
                                    class="text-xs leading-5 text-muted-foreground"
                                >
                                    Contexto declarado por el cliente. No forma
                                    parte de la puntuación del diagnóstico.
                                </p>
                            </CardHeader>

                            <CardContent class="space-y-4">
                                <div
                                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                                >
                                    <div class="rounded-xl border p-3">
                                        <p
                                            class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            Actividad
                                        </p>
                                        <p class="mt-1 text-sm font-bold">
                                            {{
                                                businessProfileLabel(
                                                    'activity_types',
                                                    assessment.business_activity_type,
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="rounded-xl border p-3">
                                        <p
                                            class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            Sector
                                        </p>
                                        <p class="mt-1 text-sm font-bold">
                                            {{
                                                assessment.business_sector ===
                                                'other'
                                                    ? assessment.business_sector_other ||
                                                      'Otro'
                                                    : businessProfileLabel(
                                                          'sectors',
                                                          assessment.business_sector,
                                                      )
                                            }}
                                        </p>
                                    </div>

                                    <div class="rounded-xl border p-3">
                                        <p
                                            class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            Mercado
                                        </p>
                                        <p class="mt-1 text-sm font-bold">
                                            {{
                                                businessProfileLabel(
                                                    'customer_markets',
                                                    assessment.customer_market,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <p
                                        class="text-xs font-black tracking-widest text-muted-foreground uppercase"
                                    >
                                        Canales comerciales
                                    </p>

                                    <p class="mt-1 text-sm leading-6">
                                        {{
                                            businessProfileLabels(
                                                'sales_channels',
                                                assessment.sales_channels,
                                            )
                                        }}

                                        <span
                                            v-if="
                                                assessment.sales_channels?.includes(
                                                    'other',
                                                ) &&
                                                assessment.sales_channel_other
                                            "
                                        >
                                            ·
                                            {{ assessment.sales_channel_other }}
                                        </span>
                                    </p>
                                </div>

                                <div
                                    v-if="
                                        assessment.business_sector ===
                                        'logistics'
                                    "
                                    class="rounded-xl border bg-muted/20 p-3"
                                >
                                    <p
                                        class="text-xs font-black tracking-widest text-muted-foreground uppercase"
                                    >
                                        Operación logística
                                    </p>

                                    <p class="mt-1 text-sm leading-6">
                                        {{
                                            businessProfileLabels(
                                                'logistics_operation_types',
                                                assessment.logistics_operation_types,
                                            )
                                        }}

                                        <span
                                            v-if="
                                                assessment.logistics_operation_types?.includes(
                                                    'other',
                                                ) &&
                                                assessment.logistics_operation_other
                                            "
                                        >
                                            ·
                                            {{
                                                assessment.logistics_operation_other
                                            }}
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs font-black tracking-widest text-muted-foreground uppercase"
                                    >
                                        Actividad principal
                                    </p>

                                    <p
                                        class="mt-1 text-sm leading-6 whitespace-pre-line"
                                    >
                                        {{
                                            assessment.business_activity_description ||
                                            '—'
                                        }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card v-if="canPublish">
                            <CardHeader>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <Card
                                        v-if="
                                            assessment?.status === 'reviewed' &&
                                            assessment?.published_at
                                        "
                                    >
                                        <CardHeader>
                                            <CardTitle>
                                                Informe Ampliado LAUDA 360
                                            </CardTitle>
                                            <CardDescription>
                                                Entregable posterior al
                                                diagnóstico inicial sin costo.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <Button as-child>
                                                <Link
                                                    :href="`/admin/diagnosis-requests/${contact.id}/expanded-report`"
                                                >
                                                    Gestionar Informe Ampliado
                                                </Link>
                                            </Button>
                                        </CardContent>
                                    </Card>

                                    <CardTitle>
                                        Revisión LAUDA y publicación
                                    </CardTitle>
                                    <Badge
                                        v-if="assessment.status === 'reviewed'"
                                        class="bg-emerald-600 text-white"
                                    >
                                        <CheckCircle2
                                            class="mr-1 h-3.5 w-3.5"
                                        />
                                        Publicado
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent
                                class="space-y-4"
                                id="informe-diagnostico"
                            >
                                <div>
                                    <label class="text-xs font-bold">
                                        Conclusión ejecutiva
                                    </label>
                                    <textarea
                                        v-model="publishForm.review_summary"
                                        rows="6"
                                        class="mt-2 w-full rounded-xl border bg-background p-3 text-sm leading-6"
                                        placeholder="Explique qué significa el resultado para la empresa, sus principales brechas y el enfoque recomendado."
                                    />
                                    <p
                                        v-if="publishForm.errors.review_summary"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ publishForm.errors.review_summary }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-bold">
                                        Prioridades
                                    </label>
                                    <textarea
                                        v-model="publishForm.review_priorities"
                                        rows="6"
                                        class="mt-2 w-full rounded-xl border bg-background p-3 text-sm leading-6"
                                        placeholder="Una prioridad por línea. Máximo 5."
                                    />
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Una prioridad por línea; el cliente las
                                        verá en este mismo orden.
                                    </p>
                                    <p
                                        v-if="
                                            publishForm.errors.review_priorities
                                        "
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{
                                            publishForm.errors.review_priorities
                                        }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-bold">
                                        Modalidad final
                                    </label>
                                    <select
                                        v-model="publishForm.final_modality"
                                        class="mt-2 h-10 w-full rounded-xl border bg-background px-3 text-sm"
                                    >
                                        <option value="guided">
                                            LAUDA 360 Guiado
                                        </option>
                                        <option value="assisted">
                                            LAUDA 360 Asistido
                                        </option>
                                        <option value="managed">
                                            LAUDA 360 Gestionado
                                        </option>
                                    </select>
                                    <p
                                        v-if="publishForm.errors.final_modality"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ publishForm.errors.final_modality }}
                                    </p>
                                </div>

                                <div
                                    v-if="assessment.published_at"
                                    class="rounded-xl border bg-muted/20 p-3 text-xs text-muted-foreground"
                                >
                                    Publicado por
                                    <strong>
                                        {{
                                            assessment.reviewed_by?.name ||
                                            'LAUDA'
                                        }}
                                    </strong>
                                    · {{ assessment.published_at }}
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <Button
                                        v-if="assessment.status === 'submitted'"
                                        variant="outline"
                                        :disabled="
                                            reviewSubmitting ||
                                            publishForm.processing
                                        "
                                        @click="saveReviewDraft"
                                        type="button"
                                    >
                                        Guardar borrador
                                    </Button>

                                    <Button
                                        :class="[
                                            'bg-[#F53003] text-white hover:bg-[#D92A03]',
                                            assessment.status === 'reviewed'
                                                ? 'sm:col-span-2'
                                                : '',
                                        ]"
                                        :disabled="
                                            reviewSubmitting ||
                                            publishForm.processing
                                        "
                                        @click="publish"
                                        type="button"
                                    >
                                        {{
                                            assessment.status === 'reviewed'
                                                ? 'Actualizar y republicar resultado'
                                                : 'Publicar resultado al cliente'
                                        }}
                                    </Button>
                                </div>

                                <p
                                    v-if="assessment.status === 'submitted'"
                                    class="text-xs leading-5 text-muted-foreground"
                                >
                                    Guardar borrador no cambia el estado del
                                    diagnóstico, no muestra el resultado al
                                    cliente y no envía correos.
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Detalle de respuestas</CardTitle>
                                <p
                                    class="text-xs leading-5 text-muted-foreground"
                                >
                                    Abra solo la dimensión que necesite revisar.
                                    Los resultados permanecen visibles sin
                                    cargar las 51 respuestas a la vez.
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-2">
                                <details
                                    v-for="group in groups"
                                    :key="group.id"
                                    class="group overflow-hidden rounded-2xl border bg-background"
                                >
                                    <summary
                                        class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 transition-colors hover:bg-muted/40 [&::-webkit-details-marker]:hidden"
                                    >
                                        <div class="min-w-0">
                                            <p class="font-black">
                                                {{ group.title }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{ group.questions.length }}
                                                preguntas
                                            </p>
                                        </div>

                                        <div
                                            class="flex shrink-0 items-center gap-2"
                                        >
                                            <Badge variant="outline">
                                                {{ group.score ?? '—' }}/100
                                            </Badge>
                                            <ChevronDown
                                                class="h-4 w-4 text-muted-foreground transition-transform group-open:rotate-180"
                                            />
                                        </div>
                                    </summary>

                                    <div class="border-t px-4">
                                        <div
                                            v-for="question in group.questions"
                                            :key="question.id"
                                            class="border-b py-4 last:border-b-0"
                                        >
                                            <p
                                                class="text-sm leading-6 font-semibold"
                                            >
                                                {{ question.text }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs font-bold text-[#F53003]"
                                            >
                                                Nivel
                                                {{
                                                    assessment.answers?.[
                                                        question.id
                                                    ] ?? '—'
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs leading-5 text-muted-foreground"
                                            >
                                                {{
                                                    answerLabel(
                                                        question,
                                                        assessment.answers?.[
                                                            question.id
                                                        ],
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    assessment.notes?.[
                                                        question.id
                                                    ]
                                                "
                                                class="mt-2 rounded-lg bg-muted/40 p-2 text-xs"
                                            >
                                                Nota del cliente:
                                                {{
                                                    assessment.notes[
                                                        question.id
                                                    ]
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </details>
                            </CardContent>
                        </Card>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
