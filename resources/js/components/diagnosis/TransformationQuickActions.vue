<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    Clock3,
    FileText,
    Lock,
    Map,
    ReceiptText,
} from 'lucide-vue-next';
import { computed } from 'vue';

type ProgressStep = {
    code?: string;
    status?: string;
    completed?: boolean;
};

type CommercialState = {
    id: number;
    status: string;
    currency: string;
    total: string | number;
    paid_access?: boolean;
    invoice?: {
        id: number;
        number: string;
        status: string;
        total: string | number;
        amount_paid?: string | number;
    } | null;
} | null;

type AdminCommercialEndpoints = {
    expanded_prepare_invoice?: string | null;
    expanded_record_payment?: string | null;
    roadmap_prepare_invoice?: string | null;
    roadmap_record_payment?: string | null;
} | null;

const props = withDefaults(
    defineProps<{
        mode: 'client' | 'admin';

        assessmentId?: number | null;
        contactId?: number | null;

        progress?: {
            steps?: ProgressStep[];
        } | null;

        implementationPlanUrl?: string | null;

        expandedReportCommercial?: CommercialState;
        roadmapCommercial?: CommercialState;

        requestExpandedReportUrl?: string | null;
        requestRoadmapUrl?: string | null;

        commercialEndpoints?: AdminCommercialEndpoints;
    }>(),
    {
        assessmentId: null,
        contactId: null,
        progress: null,
        implementationPlanUrl: null,
        expandedReportCommercial: null,
        roadmapCommercial: null,
        requestExpandedReportUrl: null,
        requestRoadmapUrl: null,
        commercialEndpoints: null,
    },
);

const expandedPaymentForm = useForm({
    method: 'bank_transfer',
    reference: '',
});

const roadmapPaymentForm = useForm({
    method: 'bank_transfer',
    reference: '',
});

const isCompleted = (code: string): boolean =>
    Boolean(
        props.progress?.steps?.some(
            (step) =>
                step.code === code &&
                (step.status === 'completed' || step.completed === true),
        ),
    );

const diagnosisSubmitted = computed(() => isCompleted('diagnosis_submitted'));

const diagnosisPublished = computed(() => isCompleted('diagnosis_published'));

const expandedReportAvailable = computed(() =>
    isCompleted('expanded_report_published'),
);

const roadmapAvailable = computed(() => isCompleted('roadmap_published'));

const clientDiagnosisUrl = computed(() =>
    props.assessmentId ? `/diagnostico/${props.assessmentId}` : null,
);

const clientExpandedReportUrl = computed(() =>
    props.assessmentId
        ? `/diagnostico/${props.assessmentId}/informe-ampliado`
        : null,
);

const clientRoadmapUrl = computed(() =>
    props.assessmentId
        ? `/diagnostico/${props.assessmentId}/roadmap-detallado`
        : null,
);

const adminDiagnosisUrl = computed(() =>
    props.contactId ? `/admin/diagnosis-requests/${props.contactId}` : null,
);

const adminExpandedReportUrl = computed(() =>
    props.contactId
        ? `/admin/diagnosis-requests/${props.contactId}/expanded-report`
        : null,
);

const adminRoadmapUrl = computed(() =>
    props.contactId
        ? `/admin/diagnosis-requests/${props.contactId}/detailed-roadmap`
        : null,
);

const adminPlanUrl = computed(() =>
    props.contactId
        ? `/admin/diagnosis-requests/${props.contactId}/implementation-plan`
        : null,
);

const diagnosisStatus = computed(() => {
    if (diagnosisPublished.value) return 'Publicado';
    if (diagnosisSubmitted.value) return 'En revisión';

    return 'Pendiente';
});

const expandedStatus = computed(() => {
    if (expandedReportAvailable.value) {
        return 'Publicado';
    }

    if (props.expandedReportCommercial?.paid_access) {
        return 'Pago confirmado';
    }

    if (props.expandedReportCommercial?.status === 'invoiced') {
        return 'Factura preparada';
    }

    if (props.expandedReportCommercial?.status === 'requested') {
        return 'Solicitado';
    }

    if (diagnosisPublished.value) {
        return 'Disponible';
    }

    return 'Bloqueado';
});

const roadmapStatus = computed(() => {
    if (roadmapAvailable.value) {
        return 'Publicado';
    }

    if (props.roadmapCommercial?.paid_access) {
        return 'Pago confirmado';
    }

    if (props.roadmapCommercial?.status === 'invoiced') {
        return 'Factura preparada';
    }

    if (props.roadmapCommercial?.status === 'requested') {
        return 'Solicitado';
    }

    if (expandedReportAvailable.value) {
        return 'Disponible';
    }

    return 'Bloqueado';
});

function money(
    value: string | number | null | undefined,
    currency = 'DOP',
): string {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function statusClass(status: string): string {
    if (status === 'Publicado') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200';
    }

    if (status === 'Disponible' || status === 'Pago confirmado') {
        return 'border-primary/20 bg-primary/5 text-primary';
    }

    if (['En revisión', 'Solicitado', 'Factura preparada'].includes(status)) {
        return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200';
    }

    return 'border-border bg-muted/40 text-muted-foreground';
}

function requiresReference(method: string): boolean {
    return ['bank_transfer', 'check'].includes(method);
}

function requestExpandedReport(): void {
    if (!props.requestExpandedReportUrl) return;

    if (!window.confirm('¿Solicitar el Informe Ampliado LAUDA 360?')) {
        return;
    }

    router.post(props.requestExpandedReportUrl, {}, { preserveScroll: true });
}

function requestRoadmap(): void {
    if (!props.requestRoadmapUrl) return;

    if (!window.confirm('¿Solicitar el Roadmap Detallado LAUDA 360?')) {
        return;
    }

    router.post(props.requestRoadmapUrl, {}, { preserveScroll: true });
}

function prepareInvoice(url: string | null | undefined): void {
    if (!url) return;

    router.post(url, {}, { preserveScroll: true });
}

function confirmExpandedPayment(): void {
    const url = props.commercialEndpoints?.expanded_record_payment;

    if (!url) return;

    expandedPaymentForm.post(url, {
        preserveScroll: true,
    });
}

function confirmRoadmapPayment(): void {
    const url = props.commercialEndpoints?.roadmap_record_payment;

    if (!url) return;

    roadmapPaymentForm.post(url, {
        preserveScroll: true,
    });
}

const enabledClass =
    'inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-foreground px-4 py-2.5 text-sm font-bold text-background transition hover:opacity-90';

const secondaryEnabledClass =
    'inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border bg-background px-4 py-2.5 text-sm font-bold transition hover:bg-muted';

const disabledClass =
    'inline-flex min-h-10 w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-dashed bg-muted/20 px-4 py-2.5 text-center text-sm font-semibold text-muted-foreground opacity-70';
</script>

<template>
    <section class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
        <div class="mb-5">
            <p
                class="text-[10px] font-black tracking-[0.16em] text-primary uppercase"
            >
                Centro de control
            </p>

            <h2 class="mt-1 text-lg font-black">Documentos y resultados</h2>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                Consulta el estado completo y ejecuta desde aquí la siguiente
                acción disponible.
            </p>
        </div>

        <div
            :class="
                mode === 'admin' ? 'grid gap-5' : 'grid gap-4 md:grid-cols-3'
            "
        >
            <!-- ===================================================
                 1. INFORME DEL DIAGNÓSTICO
                 =================================================== -->
            <article
                class="flex min-h-[250px] flex-col rounded-2xl border bg-background p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <FileText class="size-5" />
                    </div>

                    <span
                        class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase"
                        :class="statusClass(diagnosisStatus)"
                    >
                        {{ diagnosisStatus }}
                    </span>
                </div>

                <div class="mt-4 flex-1">
                    <p
                        class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        1 · Resultado base
                    </p>

                    <h3 class="mt-1 text-base font-black">
                        Informe del Diagnóstico
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Resultado oficial gratuito del Diagnóstico 360,
                        prioridades y modalidad recomendada.
                    </p>
                </div>

                <template v-if="mode === 'admin'">
                    <Link
                        v-if="diagnosisSubmitted && adminDiagnosisUrl"
                        :href="`${adminDiagnosisUrl}#informe-diagnostico`"
                        :class="enabledClass"
                    >
                        {{
                            diagnosisPublished
                                ? 'Revisar informe publicado'
                                : 'Generar / publicar informe'
                        }}

                        <ArrowRight class="size-4" />
                    </Link>

                    <span v-else :class="disabledClass">
                        <Lock class="size-4" />
                        Esperando diagnóstico
                    </span>
                </template>

                <template v-else>
                    <Link
                        v-if="diagnosisPublished && clientDiagnosisUrl"
                        :href="`${clientDiagnosisUrl}#informe-diagnostico`"
                        :class="enabledClass"
                    >
                        <CheckCircle2 class="size-4" />
                        Ver Informe
                    </Link>

                    <span v-else :class="disabledClass">
                        <Clock3 class="size-4" />
                        Informe todavía no disponible
                    </span>
                </template>
            </article>

            <!-- ===================================================
                 2. INFORME AMPLIADO
                 =================================================== -->
            <article
                class="flex min-h-[290px] flex-col rounded-2xl border bg-background p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <FileText class="size-5" />
                    </div>

                    <span
                        class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase"
                        :class="statusClass(expandedStatus)"
                    >
                        {{ expandedStatus }}
                    </span>
                </div>

                <div class="mt-4">
                    <p
                        class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        2 · Análisis profundo
                    </p>

                    <h3 class="mt-1 text-base font-black">Informe Ampliado</h3>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Análisis profundo, implicaciones y recomendaciones
                        derivadas del resultado base.
                    </p>
                </div>

                <div
                    v-if="expandedReportCommercial"
                    class="mt-4 grid gap-2 rounded-xl border bg-muted/20 p-3 text-xs"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground"> Orden </span>

                        <strong> #{{ expandedReportCommercial.id }} </strong>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground"> Total </span>

                        <strong>
                            {{
                                money(
                                    expandedReportCommercial.total,
                                    expandedReportCommercial.currency,
                                )
                            }}
                        </strong>
                    </div>

                    <div
                        v-if="expandedReportCommercial.invoice"
                        class="flex items-center justify-between gap-3"
                    >
                        <span class="text-muted-foreground"> Factura </span>

                        <strong>
                            {{ expandedReportCommercial.invoice.number }}
                        </strong>
                    </div>
                </div>

                <div class="mt-5 flex-1">
                    <!-- CLIENTE -->
                    <template v-if="mode === 'client'">
                        <Link
                            v-if="
                                expandedReportAvailable &&
                                clientExpandedReportUrl
                            "
                            :href="clientExpandedReportUrl"
                            :class="enabledClass"
                        >
                            <CheckCircle2 class="size-4" />
                            Ver Informe Ampliado
                        </Link>

                        <button
                            v-else-if="
                                diagnosisPublished &&
                                !expandedReportCommercial &&
                                requestExpandedReportUrl
                            "
                            type="button"
                            :class="secondaryEnabledClass"
                            @click="requestExpandedReport"
                        >
                            Solicitar Informe Ampliado
                            <ArrowRight class="size-4" />
                        </button>

                        <div
                            v-else-if="
                                expandedReportCommercial?.status === 'requested'
                            "
                            class="rounded-xl border bg-muted/20 p-4 text-sm"
                        >
                            <p class="font-bold">Solicitud recibida</p>

                            <p class="mt-1 text-muted-foreground">
                                LAUDA preparará la factura del Informe Ampliado.
                            </p>
                        </div>

                        <div
                            v-else-if="
                                expandedReportCommercial?.status === 'invoiced'
                            "
                            class="rounded-xl border bg-muted/20 p-4 text-sm"
                        >
                            <p class="font-bold">Factura preparada</p>

                            <p class="mt-1 text-muted-foreground">
                                El acceso se habilitará cuando LAUDA confirme el
                                pago.
                            </p>
                        </div>

                        <div
                            v-else-if="expandedReportCommercial?.paid_access"
                            class="rounded-xl border bg-emerald-50/60 p-4 text-sm dark:bg-emerald-950/20"
                        >
                            <p class="font-bold">Pago confirmado</p>

                            <p class="mt-1 text-muted-foreground">
                                Informe Ampliado en preparación o revisión.
                            </p>
                        </div>

                        <span v-else :class="disabledClass">
                            <Lock class="size-4" />
                            Informe Ampliado no disponible
                        </span>
                    </template>

                    <!-- ADMIN -->
                    <template v-else>
                        <div
                            v-if="!diagnosisPublished"
                            class="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground"
                        >
                            Primero debe publicarse el Informe del Diagnóstico.
                        </div>

                        <div
                            v-else-if="!expandedReportCommercial"
                            class="rounded-xl border border-dashed bg-muted/20 p-4 text-sm"
                        >
                            <p class="font-bold">
                                Esperando solicitud del cliente
                            </p>

                            <p class="mt-1 text-muted-foreground">
                                El cliente puede solicitar el Informe Ampliado
                                desde esta misma sección en su cuenta.
                            </p>
                        </div>

                        <div
                            v-else-if="
                                expandedReportCommercial.status === 'requested'
                            "
                            class="space-y-3 rounded-xl border p-4"
                        >
                            <div>
                                <p class="font-bold">Solicitud recibida</p>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Siguiente paso: emitir la factura one-time.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-foreground px-4 py-2 text-sm font-bold text-background"
                                @click="
                                    prepareInvoice(
                                        commercialEndpoints?.expanded_prepare_invoice,
                                    )
                                "
                            >
                                <ReceiptText class="size-4" />
                                Preparar factura
                            </button>
                        </div>

                        <div
                            v-else-if="
                                expandedReportCommercial.status === 'invoiced'
                            "
                            class="space-y-4 rounded-xl border p-4"
                        >
                            <div>
                                <p class="font-bold">Confirmar pago</p>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        expandedReportCommercial.invoice?.number
                                    }}
                                    ·
                                    {{
                                        money(
                                            expandedReportCommercial.invoice
                                                ?.total,
                                            expandedReportCommercial.currency,
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="space-y-1.5 text-xs font-bold">
                                    <span>Método</span>

                                    <select
                                        v-model="expandedPaymentForm.method"
                                        class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                                    >
                                        <option value="bank_transfer">
                                            Transferencia
                                        </option>

                                        <option value="cash">Efectivo</option>

                                        <option value="check">Cheque</option>

                                        <option value="other">Otro</option>
                                    </select>
                                </label>

                                <label class="space-y-1.5 text-xs font-bold">
                                    <span>Referencia</span>

                                    <input
                                        v-model="expandedPaymentForm.reference"
                                        class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                                        placeholder="Referencia del pago"
                                    />
                                </label>
                            </div>

                            <button
                                type="button"
                                class="inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-foreground px-4 py-2 text-sm font-bold text-background disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="
                                    expandedPaymentForm.processing ||
                                    (requiresReference(
                                        expandedPaymentForm.method,
                                    ) &&
                                        !expandedPaymentForm.reference.trim())
                                "
                                @click="confirmExpandedPayment"
                            >
                                Confirmar pago completo
                            </button>
                        </div>

                        <div
                            v-else-if="expandedReportCommercial.paid_access"
                            class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-950 dark:bg-emerald-950/20"
                        >
                            <div class="flex items-start gap-3">
                                <CheckCircle2
                                    class="mt-0.5 size-5 shrink-0 text-emerald-600"
                                />

                                <div>
                                    <p class="font-bold">Pago confirmado</p>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        El Informe ya tiene acceso comercial
                                        habilitado.
                                    </p>
                                </div>
                            </div>

                            <Link
                                v-if="
                                    diagnosisPublished && adminExpandedReportUrl
                                "
                                :href="adminExpandedReportUrl"
                                :class="enabledClass"
                            >
                                Gestionar Informe Ampliado
                                <ArrowRight class="size-4" />
                            </Link>
                        </div>
                    </template>
                </div>
            </article>

            <!-- ===================================================
                 3. ROADMAP DETALLADO
                 =================================================== -->
            <article
                class="flex min-h-[290px] flex-col rounded-2xl border bg-background p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Map class="size-5" />
                    </div>

                    <span
                        class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase"
                        :class="statusClass(roadmapStatus)"
                    >
                        {{ roadmapStatus }}
                    </span>
                </div>

                <div class="mt-4">
                    <p
                        class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        3 · Plan de transformación
                    </p>

                    <h3 class="mt-1 text-base font-black">Roadmap Detallado</h3>

                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Fases, iniciativas, prioridades y secuencia recomendada
                        para ejecutar la transformación.
                    </p>
                </div>

                <div
                    v-if="roadmapCommercial"
                    class="mt-4 grid gap-2 rounded-xl border bg-muted/20 p-3 text-xs"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground"> Orden </span>

                        <strong> #{{ roadmapCommercial.id }} </strong>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground"> Total </span>

                        <strong>
                            {{
                                money(
                                    roadmapCommercial.total,
                                    roadmapCommercial.currency,
                                )
                            }}
                        </strong>
                    </div>

                    <div
                        v-if="roadmapCommercial.invoice"
                        class="flex items-center justify-between gap-3"
                    >
                        <span class="text-muted-foreground"> Factura </span>

                        <strong>
                            {{ roadmapCommercial.invoice.number }}
                        </strong>
                    </div>
                </div>

                <div class="mt-5 flex-1">
                    <!-- CLIENTE -->
                    <template v-if="mode === 'client'">
                        <Link
                            v-if="roadmapAvailable && clientRoadmapUrl"
                            :href="clientRoadmapUrl"
                            :class="enabledClass"
                        >
                            <CheckCircle2 class="size-4" />
                            Ver Roadmap Detallado
                        </Link>

                        <button
                            v-else-if="
                                expandedReportAvailable &&
                                !roadmapCommercial &&
                                requestRoadmapUrl
                            "
                            type="button"
                            :class="secondaryEnabledClass"
                            @click="requestRoadmap"
                        >
                            Solicitar Roadmap Detallado
                            <ArrowRight class="size-4" />
                        </button>

                        <div
                            v-else-if="
                                roadmapCommercial?.status === 'requested'
                            "
                            class="rounded-xl border bg-muted/20 p-4 text-sm"
                        >
                            <p class="font-bold">Solicitud recibida</p>

                            <p class="mt-1 text-muted-foreground">
                                LAUDA preparará la facturación del Roadmap.
                            </p>
                        </div>

                        <div
                            v-else-if="roadmapCommercial?.status === 'invoiced'"
                            class="rounded-xl border bg-muted/20 p-4 text-sm"
                        >
                            <p class="font-bold">Factura preparada</p>

                            <p class="mt-1 text-muted-foreground">
                                El proceso continuará cuando LAUDA confirme el
                                pago.
                            </p>
                        </div>

                        <div
                            v-else-if="roadmapCommercial?.paid_access"
                            class="rounded-xl border bg-emerald-50/60 p-4 text-sm dark:bg-emerald-950/20"
                        >
                            <p class="font-bold">Pago confirmado</p>

                            <p class="mt-1 text-muted-foreground">
                                Roadmap en preparación o revisión.
                            </p>
                        </div>

                        <span v-else :class="disabledClass">
                            <Lock class="size-4" />
                            Roadmap Detallado no disponible
                        </span>
                    </template>

                    <!-- ADMIN -->
                    <template v-else>
                        <div
                            v-if="!expandedReportAvailable"
                            class="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground"
                        >
                            Primero debe publicarse el Informe Ampliado.
                        </div>

                        <div
                            v-else-if="!roadmapCommercial"
                            class="rounded-xl border border-dashed bg-muted/20 p-4 text-sm"
                        >
                            <p class="font-bold">
                                Esperando solicitud del cliente
                            </p>

                            <p class="mt-1 text-muted-foreground">
                                El cliente puede solicitar el Roadmap
                                directamente desde su Diagnóstico 360.
                            </p>
                        </div>

                        <div
                            v-else-if="roadmapCommercial.status === 'requested'"
                            class="space-y-3 rounded-xl border p-4"
                        >
                            <div>
                                <p class="font-bold">Solicitud recibida</p>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Siguiente paso: preparar factura one-time
                                    del Roadmap.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-foreground px-4 py-2 text-sm font-bold text-background"
                                @click="
                                    prepareInvoice(
                                        commercialEndpoints?.roadmap_prepare_invoice,
                                    )
                                "
                            >
                                <ReceiptText class="size-4" />
                                Preparar factura
                            </button>
                        </div>

                        <div
                            v-else-if="roadmapCommercial.status === 'invoiced'"
                            class="space-y-4 rounded-xl border p-4"
                        >
                            <div>
                                <p class="font-bold">Confirmar pago</p>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ roadmapCommercial.invoice?.number }}
                                    ·
                                    {{
                                        money(
                                            roadmapCommercial.invoice?.total,
                                            roadmapCommercial.currency,
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="space-y-1.5 text-xs font-bold">
                                    <span>Método</span>

                                    <select
                                        v-model="roadmapPaymentForm.method"
                                        class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                                    >
                                        <option value="bank_transfer">
                                            Transferencia
                                        </option>

                                        <option value="cash">Efectivo</option>

                                        <option value="check">Cheque</option>

                                        <option value="other">Otro</option>
                                    </select>
                                </label>

                                <label class="space-y-1.5 text-xs font-bold">
                                    <span>Referencia</span>

                                    <input
                                        v-model="roadmapPaymentForm.reference"
                                        class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                                        placeholder="Referencia del pago"
                                    />
                                </label>
                            </div>

                            <button
                                type="button"
                                class="inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-foreground px-4 py-2 text-sm font-bold text-background disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="
                                    roadmapPaymentForm.processing ||
                                    (requiresReference(
                                        roadmapPaymentForm.method,
                                    ) &&
                                        !roadmapPaymentForm.reference.trim())
                                "
                                @click="confirmRoadmapPayment"
                            >
                                Confirmar pago completo
                            </button>
                        </div>

                        <div
                            v-else-if="roadmapCommercial.paid_access"
                            class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-950 dark:bg-emerald-950/20"
                        >
                            <div class="flex items-start gap-3">
                                <CheckCircle2
                                    class="mt-0.5 size-5 shrink-0 text-emerald-600"
                                />

                                <div>
                                    <p class="font-bold">Pago confirmado</p>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        El Roadmap ya tiene acceso comercial
                                        habilitado.
                                    </p>
                                </div>
                            </div>

                            <Link
                                v-if="adminRoadmapUrl"
                                :href="adminRoadmapUrl"
                                :class="enabledClass"
                            >
                                Gestionar Roadmap Detallado
                                <ArrowRight class="size-4" />
                            </Link>
                        </div>
                    </template>
                </div>
            </article>
        </div>

        <div
            class="mt-5 flex flex-col gap-3 rounded-2xl border bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-xs font-black tracking-wide uppercase">
                    Siguiente fase
                </p>

                <p class="mt-1 text-sm text-muted-foreground">
                    El Plan de Implementación pertenece a la ejecución posterior
                    de la transformación.
                </p>
            </div>

            <!-- DIAGNOSIS360_DIRECT_IMPLEMENTATION_PLAN -->
            <div
                v-if="diagnosisPublished"
                class="rounded-xl border bg-muted/20 p-4"
            >
                <p class="text-sm font-black">Crear Plan de Implementación</p>
                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    Disponible desde el resultado oficial del Diagnóstico 360.
                    El Informe Ampliado y el Roadmap Detallado son opcionales
                    para iniciar esta fase.
                </p>
                <p class="mt-2 text-xs leading-5 text-muted-foreground">
                    Si existe un Roadmap Detallado publicado, el Plan lo utiliza
                    como fuente. Si no existe, LAUDA puede crear el Plan
                    directamente desde el diagnóstico oficial.
                </p>
            </div>

            <Link
                v-if="mode === 'admin' && diagnosisPublished && adminPlanUrl"
                :href="adminPlanUrl"
                class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border bg-background px-4 py-2 text-sm font-bold hover:bg-muted"
            >
                Gestionar Plan de Implementación
                <ArrowRight class="size-4" />
            </Link>
            <!-- DIAGNOSIS360_IMPLEMENTATION_PLAN_BLOCKED -->
            <div v-if="!diagnosisPublished" :class="disabledClass">
                Plan de Implementación disponible después de publicar el
                resultado oficial del Diagnóstico 360
            </div>

            <template v-else-if="mode === 'client'">
                <Link
                    v-if="implementationPlanUrl"
                    :href="implementationPlanUrl"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border bg-background px-4 py-2 text-sm font-bold hover:bg-muted"
                >
                    Continuar con mi transformación
                    <ArrowRight class="size-4" />
                </Link>

                <span
                    v-else
                    class="text-xs font-semibold text-muted-foreground"
                >
                    Plan de Implementación en preparación
                </span>
            </template>
        </div>
    </section>
</template>
