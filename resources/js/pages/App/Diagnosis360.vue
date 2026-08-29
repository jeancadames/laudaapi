<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    FileText,
    Gift,
    Hourglass,
    ReceiptText,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type State = {
    exists: boolean;
    historical: boolean;
    needs_initialization: boolean;
    workflow: {
        public_id: string;
        status: string;
        confirmation_status: string;
        requested_at: string | null;
        confirmed_at: string | null;
    } | null;
    invoice: {
        id: number;
        number: string;
        status: string;
        currency: string;
        subtotal: string;
        tax_total: string;
        total: string;
        amount_paid: string;
        issued_on: string | null;
        url: string;
    } | null;
    assessment: {
        id: number;
        status: string;
        current_step: number | null;
        submitted_at: string | null;
        published_at: string | null;
        url: string;
    } | null;
    offer: {
        code: string;
        name: string;
        invoice_description: string;
        currency: string;
        subtotal: number;
        tax_rate: number;
        tax_amount: number;
        total: number;
        complimentary: boolean;
        manual_confirmation_required: boolean;
    };
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
        subscriber_id: number;
    };
    state: State;
    auto_start: boolean;
    endpoints: {
        request: string;
        invoices: string;
        company: string;
        home: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Diagnóstico 360', href: '/app/diagnostico-360' },
];

const sending = ref(false);
const startedAutomatically = ref(false);

const isActive = computed(
    () =>
        props.state.workflow?.status === 'active'
        && !!props.state.assessment,
);

const isPending = computed(
    () =>
        !!props.state.workflow
        && !isActive.value
        && !props.state.historical,
);

const stateLabel = computed(() => {
    if (props.state.historical) return 'Acceso histórico';

    if (isActive.value) {
        if (props.state.assessment?.status === 'submitted') {
            return 'Enviado';
        }

        if (props.state.assessment?.status === 'reviewed') {
            return 'Resultado disponible';
        }

        return 'Habilitado';
    }

    if (isPending.value) return 'Pendiente de confirmación';

    return 'Disponible';
});

function requestDiagnosis() {
    if (sending.value) return;

    sending.value = true;

    router.post(
        props.endpoints.request,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

onMounted(() => {
    if (
        props.auto_start
        && props.state.needs_initialization
        && !startedAutomatically.value
    ) {
        startedAutomatically.value = true;
        requestDiagnosis();
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Diagnóstico 360" />

        <div class="min-h-full bg-slate-50/60 py-6 dark:bg-slate-950/40">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                <header class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 dark:border-red-950 dark:bg-red-950/40 dark:text-red-300">
                                <Sparkles class="h-3.5 w-3.5" />
                                LAUDA 360
                            </div>

                            <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                                Diagnóstico 360
                            </h1>

                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 sm:text-base dark:text-slate-400">
                                Evalúa la situación digital de {{ props.company.name }} y continúa todo el proceso dentro de tu App Hub.
                            </p>
                        </div>

                        <span class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-bold text-white dark:bg-white dark:text-slate-950">
                            {{ stateLabel }}
                        </span>
                    </div>
                </header>

                <section class="grid gap-4 md:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                        <Gift class="h-6 w-6 text-red-600" />
                        <p class="mt-3 text-sm font-black text-slate-950 dark:text-white">
                            Primera evaluación gratis
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-500">
                            El Diagnóstico Inicial tiene tarifa DOP 0.00. Generamos una factura de cortesía para que quede evidencia comercial en tu cuenta.
                        </p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                        <ReceiptText class="h-6 w-6 text-slate-700 dark:text-slate-200" />
                        <p class="mt-3 text-sm font-black text-slate-950 dark:text-white">
                            Facturación central
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-500">
                            La factura se guarda junto a las demás facturas de tu tenant y no crea un pago ficticio.
                        </p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                        <ShieldCheck class="h-6 w-6 text-emerald-600" />
                        <p class="mt-3 text-sm font-black text-slate-950 dark:text-white">
                            Confirmación manual
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-500">
                            LAUDA confirma la solicitud antes de habilitar el cuestionario, manteniendo el flujo comercial manual.
                        </p>
                    </article>
                </section>

                <section
                    v-if="!props.state.exists"
                    class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-lg font-black text-slate-950 dark:text-white">
                                Solicita tu primera evaluación
                            </p>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                                Al solicitarla se registrará automáticamente una factura por DOP 0.00 con el concepto de evaluación inicial sin costo.
                            </p>
                        </div>

                        <button
                            type="button"
                            :disabled="sending"
                            class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-red-600 px-5 text-sm font-bold text-white transition hover:bg-red-700 disabled:opacity-60"
                            @click="requestDiagnosis"
                        >
                            {{ sending ? 'Registrando…' : 'Solicitar Diagnóstico 360' }}
                            <ArrowRight class="h-4 w-4" />
                        </button>
                    </div>
                </section>

                <section
                    v-if="isPending"
                    class="rounded-[1.75rem] border border-amber-200 bg-amber-50/70 p-6 dark:border-amber-900/60 dark:bg-amber-950/20"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-amber-700 shadow-sm dark:bg-slate-950 dark:text-amber-300">
                            <Hourglass class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="font-black text-slate-950 dark:text-white">
                                Pendiente de confirmación
                            </h2>
                            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                La solicitud fue registrada. No necesitas realizar ningún pago para esta primera evaluación; LAUDA debe confirmar el acceso.
                            </p>

                            <div
                                v-if="props.state.invoice"
                                class="mt-4 flex flex-col gap-3 rounded-xl border border-amber-200/70 bg-white p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/50 dark:bg-slate-950"
                            >
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Factura de cortesía
                                    </p>
                                    <p class="mt-1 font-black text-slate-950 dark:text-white">
                                        {{ props.state.invoice.number }} · DOP 0.00
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Diagnóstico Inicial LAUDA 360 · Evaluación inicial sin costo
                                    </p>
                                </div>

                                <a
                                    :href="props.state.invoice.url"
                                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-800 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-100 dark:hover:bg-slate-900"
                                >
                                    Ver factura
                                    <FileText class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-if="isActive"
                    class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50/60 p-6 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                >
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm dark:bg-slate-950 dark:text-emerald-300">
                                <CheckCircle2 class="h-5 w-5" />
                            </div>

                            <div>
                                <h2 class="font-black text-slate-950 dark:text-white">
                                    Diagnóstico habilitado
                                </h2>
                                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    La solicitud fue confirmada. Puedes comenzar o continuar el diagnóstico desde aquí.
                                </p>
                            </div>
                        </div>

                        <a
                            :href="props.state.assessment!.url"
                            class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950"
                        >
                            Abrir diagnóstico
                            <ArrowRight class="h-4 w-4" />
                        </a>
                    </div>
                </section>

                <section
                    v-if="props.state.historical"
                    class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-start gap-3">
                        <Building2 class="mt-0.5 h-5 w-5 text-slate-500" />
                        <div>
                            <p class="font-black text-slate-950 dark:text-white">
                                Diagnóstico histórico preservado
                            </p>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                Este acceso fue creado antes del nuevo flujo de factura de cortesía. No se genera una factura retroactiva.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                    <p class="font-black text-slate-950 dark:text-white">
                        Tu empresa y tu facturación siguen centralizadas
                    </p>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <a
                            :href="props.endpoints.company"
                            class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold dark:border-slate-800"
                        >
                            <Building2 class="h-4 w-4" />
                            Perfil de Empresa
                        </a>

                        <a
                            :href="props.endpoints.invoices"
                            class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold dark:border-slate-800"
                        >
                            <ReceiptText class="h-4 w-4" />
                            Ver todas las facturas
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
