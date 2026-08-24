<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    FileText,
    RefreshCcw,
    Send,
    ShieldAlert,
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
import { Textarea } from '@/components/ui/textarea';

type CommercialState = {
    id: number;
    status: 'requested' | 'invoiced' | 'paid' | 'cancelled';
    currency: string;
    subtotal: string;
    tax_rate: string;
    tax_amount: string;
    total: string;
    paid_access: boolean;
    invoice: {
        id: number;
        number: string;
        status: string;
        total: string;
        amount_paid: string;
    } | null;
} | null;

interface Report {
    id: number;
    version: number;
    status: 'draft' | 'under_review' | 'published';
    currency: string;
    subtotal: string;
    tax_rate: string;
    tax_amount: string;
    total: string;
    sections: Record<string, any>;
    review_notes: string | null;
    endpoints: {
        save_review: string;
        review: string;
        regenerate: string;
        publish: string;
    };
}

const props = defineProps<{
    contact: { id: number; company: string | null; email: string | null };
    assessment: {
        id: number;
        organization_name: string;
        status: string;
        published_at: string | null;
        maturity_score: number | null;
        capacity_score: number | null;
        urgency_score: number | null;
    };
    report: Report | null;
    can_generate: boolean;
    commercial_notice: string;
    commercial: CommercialState;
    endpoints: { back: string; generate: string };
}>();

const sections = computed(() => props.report?.sections ?? {});
const reviewForm = useForm({ review_notes: props.report?.review_notes ?? '' });
const paymentForm = useForm({ method: 'bank_transfer', reference: '' });
const canEdit = computed(
    () =>
        props.report !== null &&
        ['draft', 'under_review'].includes(props.report.status),
);

function generate() {
    router.post(props.endpoints.generate, {}, { preserveScroll: true });
}
function saveNotes() {
    if (props.report && canEdit.value)
        reviewForm.patch(props.report.endpoints.save_review, {
            preserveScroll: true,
        });
}
function markReview() {
    if (
        props.report?.status === 'draft' &&
        window.confirm('¿Marcar este informe En revisión?')
    )
        router.post(
            props.report.endpoints.review,
            {},
            { preserveScroll: true },
        );
}
function regenerate() {
    if (
        props.report?.status === 'draft' &&
        window.confirm(
            '¿Regenerar este borrador desde el diagnóstico publicado?',
        )
    )
        router.post(
            props.report.endpoints.regenerate,
            {},
            { preserveScroll: true },
        );
}
function publish() {
    if (
        props.report &&
        canEdit.value &&
        window.confirm('¿Publicar esta versión para el cliente?')
    )
        router.post(
            props.report.endpoints.publish,
            {},
            { preserveScroll: true },
        );
}
function prepareInvoice() {
    if (
        props.commercial &&
        window.confirm('¿Preparar la factura one-time del Informe Ampliado?')
    )
        router.post(
            `/admin/diagnosis-requests/${props.contact.id}/expanded-report/order/${props.commercial.id}/prepare-invoice`,
            {},
            { preserveScroll: true },
        );
}
function recordPayment() {
    if (
        props.commercial &&
        window.confirm('¿Registrar el pago completo y habilitar el acceso?')
    )
        paymentForm.post(
            `/admin/diagnosis-requests/${props.contact.id}/expanded-report/order/${props.commercial.id}/record-payment`,
            { preserveScroll: true },
        );
}
function money(value: string | number) {
    return new Intl.NumberFormat('es-DO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}
function statusLabel(status: Report['status']) {
    return {
        draft: 'Borrador',
        under_review: 'En revisión',
        published: 'Publicado',
    }[status];
}
</script>

<template>
    <Head title="Informe Ampliado LAUDA 360" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Diagnósticos 360', href: '/admin/diagnosis-requests' },
            { title: assessment.organization_name, href: endpoints.back },
            { title: 'Informe Ampliado' },
        ]"
    >
        <div class="space-y-6 p-4 md:p-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <Link
                        :href="endpoints.back"
                        class="inline-flex items-center gap-2 text-sm text-muted-foreground"
                        ><ArrowLeft class="size-4" />Volver al diagnóstico</Link
                    >
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-black">
                            Informe Ampliado LAUDA 360
                        </h1>
                        <Badge v-if="report" variant="outline"
                            >V{{ report.version }}</Badge
                        >
                        <Badge
                            v-if="report"
                            :variant="
                                report.status === 'published'
                                    ? 'default'
                                    : 'secondary'
                            "
                            >{{ statusLabel(report.status) }}</Badge
                        >
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ assessment.organization_name }}
                    </p>
                </div>
            </div>

            <div class="flex gap-3 rounded-2xl border bg-muted/20 p-4 text-sm">
                <ShieldAlert class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-bold">Facturación one-time</p>
                    <p class="mt-1 text-muted-foreground">
                        {{ commercial_notice }}
                    </p>
                </div>
            </div>

            <Card>
                <CardHeader
                    ><CardTitle>Estado comercial</CardTitle
                    ><CardDescription
                        >Solicitud, factura y confirmación de
                        pago.</CardDescription
                    ></CardHeader
                >
                <CardContent>
                    <div
                        v-if="!commercial"
                        class="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        El cliente todavía no ha solicitado comercialmente el
                        Informe Ampliado.
                    </div>
                    <div v-else class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Subtotal
                                </p>
                                <p class="font-bold">
                                    RD${{ money(commercial.subtotal) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    ITBIS
                                </p>
                                <p class="font-bold">
                                    RD${{ money(commercial.tax_amount) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Total
                                </p>
                                <p class="font-black">
                                    RD${{ money(commercial.total) }}
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="commercial.status === 'requested'"
                            @click="prepareInvoice"
                            >Preparar factura one-time</Button
                        >
                        <div
                            v-else-if="commercial.status === 'invoiced'"
                            class="space-y-4"
                        >
                            <div class="rounded-2xl border p-4">
                                <p class="font-bold">
                                    {{ commercial.invoice?.number }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Factura emitida ·
                                    {{ commercial.invoice?.status }}
                                </p>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="space-y-1"
                                    ><span class="text-xs font-bold"
                                        >Método</span
                                    ><select
                                        v-model="paymentForm.method"
                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="bank_transfer">
                                            Transferencia
                                        </option>
                                        <option value="cash">Efectivo</option>
                                        <option value="check">Cheque</option>
                                        <option value="other">Otro</option>
                                    </select></label
                                >
                                <label class="space-y-1"
                                    ><span class="text-xs font-bold"
                                        >Referencia</span
                                    ><input
                                        v-model="paymentForm.reference"
                                        type="text"
                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                        placeholder="Referencia bancaria / cheque"
                                /></label>
                            </div>
                            <p
                                v-if="paymentForm.errors.reference"
                                class="text-xs text-destructive"
                            >
                                {{ paymentForm.errors.reference }}
                            </p>
                            <Button
                                :disabled="paymentForm.processing"
                                @click="recordPayment"
                                >Registrar pago completo</Button
                            >
                        </div>
                        <div
                            v-else-if="commercial.paid_access"
                            class="rounded-2xl border bg-emerald-50 p-4 text-sm text-emerald-950 dark:bg-emerald-950/20 dark:text-emerald-100"
                        >
                            <p class="font-bold">
                                Pago confirmado · acceso habilitado
                            </p>
                            <p class="mt-1">
                                {{ commercial.invoice?.number }} está pagada.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div
                v-if="!report"
                class="rounded-3xl border border-dashed p-8 text-center"
            >
                <FileText class="mx-auto size-10 text-muted-foreground" />
                <h2 class="mt-4 text-lg font-black">Sin Informe Ampliado</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm text-muted-foreground">
                    Se generará desde el diagnóstico oficial publicado y el
                    perfil comercial.
                </p>
                <Button class="mt-5" :disabled="!can_generate" @click="generate"
                    >Generar borrador contextualizado</Button
                >
            </div>

            <template v-else>
                <div class="grid gap-4 md:grid-cols-3">
                    <Card
                        ><CardHeader
                            ><CardDescription>Madurez</CardDescription
                            ><CardTitle class="text-3xl"
                                >{{
                                    assessment.maturity_score ?? '—'
                                }}/100</CardTitle
                            ></CardHeader
                        ></Card
                    >
                    <Card
                        ><CardHeader
                            ><CardDescription>Capacidad</CardDescription
                            ><CardTitle class="text-3xl"
                                >{{
                                    assessment.capacity_score ?? '—'
                                }}/100</CardTitle
                            ></CardHeader
                        ></Card
                    >
                    <Card
                        ><CardHeader
                            ><CardDescription>Urgencia</CardDescription
                            ><CardTitle class="text-3xl"
                                >{{
                                    assessment.urgency_score ?? '—'
                                }}/100</CardTitle
                            ></CardHeader
                        ></Card
                    >
                </div>

                <Card
                    ><CardHeader
                        ><CardTitle>{{
                            sections.executive_summary?.title ||
                            'Conclusión ejecutiva'
                        }}</CardTitle></CardHeader
                    ><CardContent
                        ><p class="leading-7">
                            {{ sections.executive_summary?.body }}
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Contexto del negocio</CardTitle
                        ><CardDescription
                            >{{
                                sections.business_context?.activity_type || '—'
                            }}
                            · {{ sections.business_context?.sector || '—' }} ·
                            {{
                                sections.business_context?.customer_market ||
                                '—'
                            }}</CardDescription
                        ></CardHeader
                    ><CardContent class="space-y-3"
                        ><p class="text-sm text-muted-foreground">
                            {{ sections.business_context?.description || '—' }}
                        </p>
                        <p class="leading-7">
                            {{ sections.business_context?.interpretation }}
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Análisis por dimensión</CardTitle
                        ></CardHeader
                    ><CardContent class="space-y-3"
                        ><div
                            v-for="item in sections.dimension_analysis?.items ??
                            []"
                            :key="item.key"
                            class="rounded-2xl border p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="font-bold">{{ item.label }}</p>
                                <Badge variant="outline"
                                    >{{ item.score }}/100 ·
                                    {{ item.band }}</Badge
                                >
                            </div>
                            <p
                                class="mt-2 text-sm leading-6 text-muted-foreground"
                            >
                                {{ item.interpretation }}
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Brechas críticas</CardTitle></CardHeader
                    ><CardContent class="space-y-3"
                        ><div
                            v-for="item in sections.critical_gaps?.items ?? []"
                            :key="item.key"
                            class="rounded-2xl border p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="font-bold">{{ item.label }}</p>
                                <Badge variant="outline"
                                    >{{ item.score }}/100</Badge
                                >
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ item.impact }}
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Implicaciones para el negocio</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><ul class="space-y-3">
                            <li
                                v-for="(item, index) in sections
                                    .business_implications?.items ?? []"
                                :key="index"
                                class="flex gap-3 text-sm leading-6"
                            >
                                <CheckCircle2
                                    class="mt-1 size-4 shrink-0 text-primary"
                                /><span>{{ item }}</span>
                            </li>
                        </ul></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Focos recomendados</CardTitle></CardHeader
                    ><CardContent
                        ><ol class="space-y-3">
                            <li
                                v-for="(item, index) in sections
                                    .recommended_focus?.items ?? []"
                                :key="index"
                                class="rounded-2xl border p-4"
                            >
                                <p class="font-bold">
                                    Prioridad {{ index + 1 }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ item }}
                                </p>
                            </li>
                        </ol></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Notas internas LAUDA</CardTitle
                        ><CardDescription
                            >No se muestran al cliente.</CardDescription
                        ></CardHeader
                    ><CardContent class="space-y-3"
                        ><Textarea
                            v-model="reviewForm.review_notes"
                            :disabled="!canEdit"
                            rows="5"
                        /><Button
                            v-if="canEdit"
                            variant="outline"
                            :disabled="reviewForm.processing"
                            @click="saveNotes"
                            >Guardar notas</Button
                        ></CardContent
                    ></Card
                >

                <div
                    class="sticky bottom-4 rounded-2xl border bg-background/95 p-4 shadow-lg backdrop-blur"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="text-sm text-muted-foreground">
                            <span
                                v-if="report.status === 'published'"
                                class="inline-flex items-center gap-2 font-semibold text-emerald-700 dark:text-emerald-300"
                                ><CheckCircle2 class="size-4" />Versión
                                publicada y bloqueada.</span
                            ><span v-else
                                >Revisa el contenido antes de publicar.</span
                            >
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="report.status === 'draft'"
                                variant="outline"
                                @click="regenerate"
                                ><RefreshCcw
                                    class="mr-2 size-4"
                                />Regenerar</Button
                            >
                            <Button
                                v-if="report.status === 'draft'"
                                variant="secondary"
                                @click="markReview"
                                >Marcar En revisión</Button
                            >
                            <Button v-if="canEdit" @click="publish"
                                ><Send class="mr-2 size-4" />Publicar para
                                cliente</Button
                            >
                            <Button
                                v-if="report.status === 'published'"
                                variant="outline"
                                @click="generate"
                                >Crear nueva versión</Button
                            >
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Roadmap Detallado LAUDA 360</CardTitle>
                <CardDescription>
                    Continúa desde el Informe Ampliado hacia fases e iniciativas
                    ejecutables.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Button as-child>
                    <Link
                        :href="`/admin/diagnosis-requests/${contact.id}/detailed-roadmap`"
                    >
                        Gestionar Roadmap Detallado
                    </Link>
                </Button>
            </CardContent>
        </Card>
    </AppLayout>
</template>
