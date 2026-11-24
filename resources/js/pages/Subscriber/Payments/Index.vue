<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay, formatMoney, pageLabel } from '@/lib/portalFormat';
import type { BreadcrumbItem } from '@/types';

type PaymentRow = {
    id: number;
    method: string;
    currency: string;
    amount: string;
    paid_at?: string | null;
    reference?: string | null;
    invoice?: {
        id: number;
        number: string;
        status: string;
        currency: string;
        total: string;
    } | null;
};

type LinkItem = { url: string | null; label: string; active: boolean };

type Paginator<T> = {
    data: T[];
    links: LinkItem[];
    meta?: any;
};

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string };
    filters: {
        method: string;
        status: string;
        q: string;
        from?: string | null;
        to?: string | null;
    };
    payments: Paginator<PaymentRow>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Pagos', href: '/subscriber/payments' },
];

// -------------------------
// local state (synced with server filters)
// -------------------------
const method = ref(props.filters.method ?? 'all');
const status = ref(props.filters.status ?? 'all'); // all | paid | unpaid
const q = ref(props.filters.q ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const showAdvanced = ref(false);

// -------------------------
// helpers
// -------------------------
function norm(s: any) {
    return String(s ?? '')
        .toLowerCase()
        .trim();
}

function methodLabel(m: string) {
    const x = norm(m);
    if (x === 'card') return 'Tarjeta';
    if (x === 'bank_transfer') return 'Transferencia';
    if (x === 'cash') return 'Efectivo';
    if (x === 'check') return 'Cheque';
    if (x === 'other') return 'Otro';
    return m || '—';
}

function paidLabel(p: PaymentRow) {
    return p.paid_at ? 'Recibido' : 'En revisión';
}

function paidTone(p: PaymentRow) {
    return p.paid_at
        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
        : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200';
}

const statusTabs = [
    ['all', 'Todos'],
    ['paid', 'Recibidos'],
    ['unpaid', 'En revisión'],
] as const;

const rows = computed(() => props.payments?.data ?? []);

const hasActiveFilters = computed(() => {
    return (
        (method.value && method.value !== 'all') ||
        (status.value && status.value !== 'all') ||
        !!q.value.trim() ||
        !!from.value ||
        !!to.value
    );
});

// -------------------------
// actions
// -------------------------
function applyFilters() {
    router.get(
        '/subscriber/payments',
        {
            method: method.value,
            status: status.value,
            q: q.value.trim(),
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function clearFilters() {
    method.value = 'all';
    status.value = 'all';
    q.value = '';
    from.value = '';
    to.value = '';
    applyFilters();
}

function setPaidStatus(s: 'all' | 'paid' | 'unpaid') {
    status.value = s;
    applyFilters(); // tab = aplica inmediato
}

function goTo(url: string | null) {
    if (!url) return;
    router.visit(url, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Pagos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company.name"
            title="Pagos"
            description="Los pagos que hiciste a LAUDA y la factura que cubre cada uno."
        >
            <div class="space-y-3">
                <div
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <div
                        class="flex flex-wrap gap-2"
                        role="group"
                        aria-label="Filtrar por estado"
                    >
                        <button
                            v-for="[value, label] in statusTabs"
                            :key="value"
                            type="button"
                            class="min-h-10 rounded-full border px-4 text-sm font-medium transition-colors"
                            :class="
                                status === value
                                    ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200'
                            "
                            :aria-pressed="status === value"
                            @click="setPaidStatus(value)"
                        >
                            {{ label }}
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :class="portalButton.secondary"
                            :aria-expanded="showAdvanced"
                            @click="showAdvanced = !showAdvanced"
                        >
                            {{ showAdvanced ? 'Ocultar búsqueda' : 'Buscar' }}
                        </button>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            :class="portalButton.link"
                            @click="clearFilters"
                        >
                            Quitar filtros
                        </button>
                    </div>
                </div>

                <form
                    v-if="showAdvanced"
                    class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_2fr_1fr_1fr_auto] md:items-end dark:border-slate-800 dark:bg-slate-950"
                    @submit.prevent="applyFilters"
                >
                    <label class="block text-sm">
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                            >Forma de pago</span
                        >
                        <select
                            v-model="method"
                            class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            <option value="all">Todas</option>
                            <option value="card">Tarjeta</option>
                            <option value="bank_transfer">Transferencia</option>
                            <option value="cash">Efectivo</option>
                            <option value="check">Cheque</option>
                            <option value="other">Otra</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                            >Referencia o número de factura</span
                        >
                        <input
                            v-model="q"
                            type="search"
                            class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        />
                    </label>
                    <label class="block text-sm">
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                            >Pagado desde</span
                        >
                        <input
                            v-model="from"
                            type="date"
                            class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        />
                    </label>
                    <label class="block text-sm">
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                            >Hasta</span
                        >
                        <input
                            v-model="to"
                            type="date"
                            class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        />
                    </label>
                    <button type="submit" :class="portalButton.dark">
                        Buscar
                    </button>
                </form>
            </div>

            <div
                v-if="rows.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-950"
            >
                <h2 class="font-semibold text-slate-950 dark:text-white">
                    {{
                        hasActiveFilters
                            ? 'No hay pagos con estos filtros'
                            : 'Todavía no hay pagos registrados'
                    }}
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    {{
                        hasActiveFilters
                            ? 'Prueba con otro estado o quita los filtros.'
                            : 'Cuando pagues una factura, el pago aparecerá aquí.'
                    }}
                </p>
            </div>

            <ul
                v-else
                class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950"
            >
                <li
                    v-for="p in rows"
                    :key="p.id"
                    class="grid gap-3 p-4 sm:p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2
                                class="font-semibold text-slate-950 tabular-nums dark:text-white"
                            >
                                {{ formatMoney(p.amount, p.currency) }}
                            </h2>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="paidTone(p)"
                                >{{ paidLabel(p) }}</span
                            >
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            {{ methodLabel(p.method) }}
                            <template v-if="p.paid_at">
                                · {{ formatDay(p.paid_at) }}</template
                            >
                            <template v-if="p.reference">
                                · Referencia {{ p.reference }}</template
                            >
                        </p>
                        <p
                            v-if="p.invoice"
                            class="text-sm break-all text-slate-800 dark:text-slate-200"
                        >
                            Factura {{ p.invoice.number }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row md:justify-end">
                        <Link
                            v-if="p.invoice"
                            :href="`/subscriber/invoices/${p.invoice.id}`"
                            :class="portalButton.link"
                            >Ver factura</Link
                        >
                        <Link
                            :href="`/subscriber/payments/${p.id}`"
                            :class="portalButton.secondary"
                            >Ver pago</Link
                        >
                    </div>
                </li>
            </ul>

            <nav
                v-if="props.payments.links?.length > 3"
                aria-label="Páginas de pagos"
                class="flex flex-wrap gap-2"
            >
                <button
                    v-for="l in props.payments.links"
                    :key="l.label"
                    type="button"
                    class="min-h-10 min-w-10 rounded-xl border px-3 text-sm"
                    :class="
                        l.active
                            ? 'border-slate-950 bg-slate-950 text-white'
                            : 'border-slate-200 bg-white text-slate-700 disabled:opacity-40'
                    "
                    :disabled="!l.url"
                    @click="goTo(l.url)"
                >
                    {{ pageLabel(l.label) }}
                </button>
            </nav>
        </PortalPage>
    </AppLayout>
</template>
