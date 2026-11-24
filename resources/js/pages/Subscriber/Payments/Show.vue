<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay, formatMoney } from '@/lib/portalFormat';
import type { BreadcrumbItem } from '@/types';

type Payment = {
    id: number;
    method: string;
    currency: string;
    amount: string;
    paid_at?: string | null;
    reference?: string | null;
    meta?: unknown;
    created_at?: string | null;
    updated_at?: string | null;
    invoice?: {
        id: number;
        number: string;
        status: string;
        currency: string;
        total: string;
    } | null;
};

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string };
    payment: Payment;
}>();

const amount = formatMoney(props.payment.amount, props.payment.currency);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Pagos', href: '/subscriber/payments' },
    {
        title: `Pago de ${amount}`,
        href: `/subscriber/payments/${props.payment.id}`,
    },
];

const methodLabel = (m: string) =>
    ({
        card: 'Tarjeta',
        bank_transfer: 'Transferencia',
        cash: 'Efectivo',
        check: 'Cheque',
        other: 'Otra',
    })[
        String(m ?? '')
            .toLowerCase()
            .trim()
    ] ??
    (m || '—');

const invoiceStatus = (st: string) =>
    ({
        draft: 'Borrador',
        issued: 'Por pagar',
        paid: 'Pagada',
        void: 'Anulada',
        overdue: 'Vencida',
    })[
        String(st ?? '')
            .toLowerCase()
            .trim()
    ] ?? st;

const facts = [
    { label: 'Forma de pago', value: methodLabel(props.payment.method) },
    { label: 'Fecha', value: formatDay(props.payment.paid_at) },
    { label: 'Referencia', value: props.payment.reference || '—' },
];
</script>

<template>
    <Head :title="`Pago de ${amount}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company.name"
            :title="`Pago de ${amount}`"
            :description="
                props.payment.paid_at
                    ? 'Recibimos este pago.'
                    : 'Estamos revisando este pago. Te avisaremos cuando quede confirmado.'
            "
            width="narrow"
            :back="{ href: '/subscriber/payments', label: 'Volver a pagos' }"
        >
            <template #actions>
                <span
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="
                        props.payment.paid_at
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200'
                    "
                >
                    {{ props.payment.paid_at ? 'Recibido' : 'En revisión' }}
                </span>
            </template>

            <dl
                class="grid gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 shadow-sm sm:grid-cols-3 dark:border-slate-800 dark:bg-slate-800"
            >
                <div
                    v-for="fact in facts"
                    :key="fact.label"
                    class="bg-white p-4 dark:bg-slate-950"
                >
                    <dt class="text-sm text-slate-600 dark:text-slate-400">
                        {{ fact.label }}
                    </dt>
                    <dd
                        class="mt-1 font-semibold break-all text-slate-950 dark:text-white"
                    >
                        {{ fact.value }}
                    </dd>
                </div>
            </dl>

            <section
                v-if="props.payment.invoice"
                aria-labelledby="invoice-title"
                class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5 dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="min-w-0">
                    <h2
                        id="invoice-title"
                        class="text-sm text-slate-600 dark:text-slate-400"
                    >
                        Factura que cubre este pago
                    </h2>
                    <p
                        class="mt-1 font-semibold break-all text-slate-950 dark:text-white"
                    >
                        {{ props.payment.invoice.number }}
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ invoiceStatus(props.payment.invoice.status) }} ·
                        Total
                        {{
                            formatMoney(
                                props.payment.invoice.total,
                                props.payment.invoice.currency,
                            )
                        }}
                    </p>
                </div>
                <Link
                    :href="`/subscriber/invoices/${props.payment.invoice.id}`"
                    :class="portalButton.secondary"
                    >Ver factura</Link
                >
            </section>
        </PortalPage>
    </AppLayout>
</template>
