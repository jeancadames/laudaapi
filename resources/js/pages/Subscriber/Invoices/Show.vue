<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type InvoiceItem = {
    id: number;
    description: string;
    quantity: number;
    unit_price: string;
    line_subtotal: string;
    discount_amount: string;
    tax_amount: string;
    line_total: string;
    service_name?: string | null;
    plan_name?: string | null;
    plan_code?: string | null;
    billing_cycle?: string | null;
};

type Company = {
    id: number;
    name: string;
    legal_name?: string | null;
    tax_id?: string | null;
    country?: string | null;
    currency: string;
    timezone: string;
};

type Invoice = {
    id: number;
    number: string;
    status: string;
    issued_on?: string | null;
    due_on?: string | null;
    period_start?: string | null;
    period_end?: string | null;
    currency: string;
    subtotal: string;
    discount_total: string;
    tax_total: string;
    total: string;
    amount_paid: string;
    balance: string;
    document_class?: string | null;
    document_type?: string | null;
    fiscal_number?: string | null;
    security_code?: string | null;
    hosted_invoice_url?: string | null;
    payment_url?: string | null;
    billing_cycle?: string | null;
    items: InvoiceItem[];
};

const props = defineProps<{
    company: Company;
    invoice: Invoice;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mi ecosistema', href: '/app' },
    { title: 'Facturas', href: '/subscriber/invoices' },
    {
        title: props.invoice.number,
        href: `/subscriber/invoices/${props.invoice.id}`,
    },
];

const balanceNumber = computed(() =>
    Number(props.invoice.balance ?? 0),
);

const canPay = computed(
    () =>
        balanceNumber.value > 0
        && Boolean(props.invoice.payment_url),
);

const hasFiscalData = computed(
    () =>
        Boolean(props.invoice.document_class)
        || Boolean(props.invoice.document_type)
        || Boolean(props.invoice.fiscal_number)
        || Boolean(props.invoice.security_code),
);

const customerName = computed(
    () =>
        props.company.legal_name
        || props.company.name
        || 'Cliente',
);

function money(value: string | number | null | undefined): string {
    const amount = Number(value ?? 0);

    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: props.invoice.currency || 'DOP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);
}

function dateLabel(value?: string | null): string {
    if (! value) return '—';

    const parsed = new Date(`${value}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) return value;

    return new Intl.DateTimeFormat('es-DO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
}

function cycleLabel(value?: string | null): string {
    const cycle = String(value ?? '').toLowerCase();

    if (cycle === 'monthly') return 'Mensual';
    if (cycle === 'yearly' || cycle === 'annual') return 'Anual';

    return value || '';
}

function statusLabel(value: string): string {
    const status = String(value ?? '').toLowerCase();

    if (status === 'draft') return 'Borrador';
    if (status === 'issued') return 'Pendiente de pago';
    if (status === 'paid') return 'Pagada';
    if (status === 'overdue') return 'Vencida';
    if (status === 'void') return 'Anulada';

    return value || '—';
}

function statusClass(value: string): string {
    const status = String(value ?? '').toLowerCase();

    if (status === 'paid') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'overdue' || status === 'void') {
        return 'border-red-200 bg-red-50 text-red-700';
    }

    return 'border-amber-200 bg-amber-50 text-amber-700';
}

function printInvoice(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Factura ${invoice.number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="invoice-page bg-[#FAFAF8] p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Button variant="outline" as-child>
                        <Link href="/subscriber/invoices">
                            Volver a facturas
                        </Link>
                    </Button>

                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" @click="printInvoice">
                            Imprimir
                        </Button>

                        <Button
                            v-if="invoice.hosted_invoice_url"
                            variant="outline"
                            as-child
                        >
                            <a
                                :href="invoice.hosted_invoice_url"
                                target="_blank"
                                rel="noopener"
                            >
                                Abrir comprobante
                            </a>
                        </Button>

                        <Button v-if="canPay" as-child>
                            <a
                                :href="invoice.payment_url!"
                                target="_blank"
                                rel="noopener"
                            >
                                Pagar {{ money(invoice.balance) }}
                            </a>
                        </Button>
                    </div>
                </div>

                <article
                    class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm print:rounded-none print:border-0 print:shadow-none"
                >
                    <header
                        class="flex flex-col gap-8 border-b border-slate-200 p-6 sm:p-8 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <p class="text-xs font-bold tracking-[0.24em] text-red-600 uppercase">
                                LAUDAAPI
                            </p>
                            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">
                                Factura
                            </h1>
                            <p class="mt-2 text-sm font-semibold text-slate-500">
                                Servicios y soluciones digitales
                            </p>
                        </div>

                        <div class="lg:text-right">
                            <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">
                                Número
                            </p>
                            <p class="mt-1 text-lg font-black text-slate-950">
                                {{ invoice.number }}
                            </p>

                            <span
                                class="mt-3 inline-flex rounded-full border px-3 py-1 text-xs font-bold"
                                :class="statusClass(invoice.status)"
                            >
                                {{ statusLabel(invoice.status) }}
                            </span>
                        </div>
                    </header>

                    <section
                        class="grid gap-8 border-b border-slate-200 p-6 sm:p-8 md:grid-cols-2"
                    >
                        <div>
                            <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">
                                Facturado a
                            </p>
                            <p class="mt-3 text-lg font-black text-slate-950">
                                {{ customerName }}
                            </p>

                            <p
                                v-if="company.tax_id"
                                class="mt-1 text-sm font-medium text-slate-500"
                            >
                                RNC / Identificación fiscal:
                                {{ company.tax_id }}
                            </p>

                            <p
                                v-if="company.country"
                                class="mt-1 text-sm font-medium text-slate-500"
                            >
                                {{ company.country }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-5 md:text-right">
                            <div>
                                <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">
                                    Fecha de emisión
                                </p>
                                <p class="mt-2 text-sm font-bold text-slate-900">
                                    {{ dateLabel(invoice.issued_on) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">
                                    Vencimiento
                                </p>
                                <p class="mt-2 text-sm font-bold text-slate-900">
                                    {{ dateLabel(invoice.due_on) }}
                                </p>
                            </div>

                            <div
                                v-if="invoice.period_start || invoice.period_end"
                                class="col-span-2"
                            >
                                <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">
                                    Período facturado
                                </p>
                                <p class="mt-2 text-sm font-bold text-slate-900">
                                    {{ dateLabel(invoice.period_start) }}
                                    <span class="mx-1 text-slate-400">—</span>
                                    {{ dateLabel(invoice.period_end) }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="p-6 sm:p-8">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px] border-collapse text-left">
                                <thead>
                                    <tr class="border-b border-slate-200">
                                        <th class="pb-3 pr-4 text-xs font-bold tracking-widest text-slate-400 uppercase">
                                            Concepto
                                        </th>
                                        <th class="pb-3 px-3 text-right text-xs font-bold tracking-widest text-slate-400 uppercase">
                                            Cant.
                                        </th>
                                        <th class="pb-3 px-3 text-right text-xs font-bold tracking-widest text-slate-400 uppercase">
                                            Precio
                                        </th>
                                        <th class="pb-3 pl-3 text-right text-xs font-bold tracking-widest text-slate-400 uppercase">
                                            Total
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="item in invoice.items"
                                        :key="item.id"
                                        class="border-b border-slate-100 align-top"
                                    >
                                        <td class="py-5 pr-4">
                                            <p class="font-bold text-slate-950">
                                                {{ item.description }}
                                            </p>

                                            <div
                                                v-if="item.plan_name || item.billing_cycle"
                                                class="mt-1 flex flex-wrap gap-x-2 text-xs font-semibold text-slate-500"
                                            >
                                                <span v-if="item.plan_name">
                                                    Plan {{ item.plan_name }}
                                                </span>
                                                <span
                                                    v-if="item.plan_name && item.billing_cycle"
                                                    aria-hidden="true"
                                                >
                                                    ·
                                                </span>
                                                <span v-if="item.billing_cycle">
                                                    {{ cycleLabel(item.billing_cycle) }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="py-5 px-3 text-right font-semibold text-slate-700">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="py-5 px-3 text-right font-semibold text-slate-700">
                                            {{ money(item.unit_price) }}
                                        </td>
                                        <td class="py-5 pl-3 text-right font-black text-slate-950">
                                            {{ money(item.line_total) }}
                                        </td>
                                    </tr>

                                    <tr v-if="invoice.items.length === 0">
                                        <td
                                            colspan="4"
                                            class="py-8 text-center text-sm font-medium text-slate-500"
                                        >
                                            Esta factura no tiene conceptos detallados.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
                            <div>
                                <div
                                    v-if="hasFiscalData"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-5"
                                >
                                    <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">
                                        Comprobante fiscal
                                    </p>

                                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div v-if="invoice.document_type">
                                            <dt class="text-xs font-semibold text-slate-500">
                                                Tipo
                                            </dt>
                                            <dd class="mt-1 text-sm font-bold text-slate-900">
                                                {{ invoice.document_type }}
                                            </dd>
                                        </div>

                                        <div v-if="invoice.fiscal_number">
                                            <dt class="text-xs font-semibold text-slate-500">
                                                NCF / e-CF
                                            </dt>
                                            <dd class="mt-1 text-sm font-bold text-slate-900">
                                                {{ invoice.fiscal_number }}
                                            </dd>
                                        </div>

                                        <div v-if="invoice.security_code">
                                            <dt class="text-xs font-semibold text-slate-500">
                                                Código de seguridad
                                            </dt>
                                            <dd class="mt-1 text-sm font-bold text-slate-900">
                                                {{ invoice.security_code }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <div class="rounded-2xl bg-slate-950 p-6 text-white">
                                <dl class="space-y-3">
                                    <div class="flex items-center justify-between gap-4 text-sm">
                                        <dt class="text-slate-400">Subtotal</dt>
                                        <dd class="font-bold">{{ money(invoice.subtotal) }}</dd>
                                    </div>

                                    <div
                                        v-if="Number(invoice.discount_total) > 0"
                                        class="flex items-center justify-between gap-4 text-sm"
                                    >
                                        <dt class="text-slate-400">Descuento</dt>
                                        <dd class="font-bold">
                                            − {{ money(invoice.discount_total) }}
                                        </dd>
                                    </div>

                                    <div class="flex items-center justify-between gap-4 text-sm">
                                        <dt class="text-slate-400">Impuestos</dt>
                                        <dd class="font-bold">{{ money(invoice.tax_total) }}</dd>
                                    </div>

                                    <div class="border-t border-white/15 pt-4">
                                        <div class="flex items-end justify-between gap-4">
                                            <dt>
                                                <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">
                                                    Total
                                                </p>
                                            </dt>
                                            <dd class="text-2xl font-black">
                                                {{ money(invoice.total) }}
                                            </dd>
                                        </div>
                                    </div>

                                    <div
                                        v-if="Number(invoice.amount_paid) > 0"
                                        class="flex items-center justify-between gap-4 text-sm"
                                    >
                                        <dt class="text-slate-400">Pagado</dt>
                                        <dd class="font-bold text-emerald-300">
                                            {{ money(invoice.amount_paid) }}
                                        </dd>
                                    </div>
                                </dl>

                                <div
                                    class="mt-5 rounded-xl border border-white/10 bg-white/5 p-4"
                                >
                                    <p class="text-xs font-bold tracking-widest text-slate-400 uppercase">
                                        Balance pendiente
                                    </p>
                                    <p class="mt-1 text-2xl font-black">
                                        {{ money(invoice.balance) }}
                                    </p>
                                </div>

                                <a
                                    v-if="canPay"
                                    :href="invoice.payment_url!"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-black text-slate-950 transition hover:bg-slate-100 print:hidden"
                                >
                                    Pagar {{ money(invoice.balance) }}
                                </a>

                                <p
                                    v-else-if="balanceNumber > 0"
                                    class="mt-4 text-xs font-medium leading-5 text-slate-400 print:hidden"
                                >
                                    Esta factura está pendiente de pago. El botón
                                    de pago aparecerá cuando exista un enlace de
                                    pago disponible.
                                </p>
                            </div>
                        </div>
                    </section>

                    <footer
                        class="border-t border-slate-200 bg-slate-50 px-6 py-5 text-center text-xs font-medium text-slate-500 sm:px-8"
                    >
                        Esta factura corresponde a servicios contratados a través
                        de LAUDAAPI.
                    </footer>
                </article>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    @page {
        margin: 12mm;
    }

    body {
        background: white !important;
    }

    .invoice-page {
        padding: 0 !important;
        background: white !important;
    }
}
</style>
