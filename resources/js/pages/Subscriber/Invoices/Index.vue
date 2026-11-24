<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay, formatMoney, pageLabel } from '@/lib/portalFormat';
import type { BreadcrumbItem } from '@/types';

type InvoiceRow = {
    id: number;
    number: string;
    status: string;
    issued_on?: string | null;
    due_on?: string | null;
    currency: string;
    total: string;
    amount_paid: string;
    balance: string;
    document_class?: string | null;
    document_type?: string | null;
    fiscal_number?: string | null;
    hosted_invoice_url?: string | null;
    payment_url?: string | null;
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
        status: string;
        q: string;
        from?: string | null;
        to?: string | null;
    };
    invoices: Paginator<InvoiceRow>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Facturas', href: '/subscriber/invoices' },
];

// -------------------------
// local state (synced with server filters)
// -------------------------
const status = ref(props.filters.status ?? 'all');
const q = ref(props.filters.q ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const showAdvanced = ref(false);

// -------------------------
// helpers
// -------------------------
function normStatus(s: any) {
    return String(s ?? '')
        .toLowerCase()
        .trim();
}

function statusLabel(st: string) {
    const s = normStatus(st);
    if (s === 'draft') return 'Borrador';
    if (s === 'issued') return 'Por pagar';
    if (s === 'paid') return 'Pagada';
    if (s === 'void') return 'Anulada';
    if (s === 'overdue') return 'Vencida';
    return st || '—';
}

/** Verde = pagada · ámbar = por pagar · rojo = vencida · gris = borrador o anulada. */
function statusTone(st: string) {
    const s = normStatus(st);
    if (s === 'paid')
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    if (s === 'overdue')
        return 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300';
    if (s === 'issued')
        return 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200';
    return 'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300';
}

const statusTabs = [
    ['all', 'Todas'],
    ['issued', 'Por pagar'],
    ['overdue', 'Vencidas'],
    ['paid', 'Pagadas'],
    ['void', 'Anuladas'],
] as const;

function balanceClass(inv: InvoiceRow) {
    const st = normStatus(inv.status);
    if (st === 'paid') return 'text-emerald-600 dark:text-emerald-400';
    if (st === 'overdue') return 'text-rose-600 dark:text-rose-400';
    const b = Number(inv.balance);
    if (Number.isFinite(b) && b <= 0)
        return 'text-emerald-600 dark:text-emerald-400';
    return 'text-muted-foreground';
}

const rows = computed(() => props.invoices?.data ?? []);

const hasActiveFilters = computed(() => {
    return (
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
        '/subscriber/invoices',
        {
            status: status.value,
            q: q.value.trim(),
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function clearFilters() {
    status.value = 'all';
    q.value = '';
    from.value = '';
    to.value = '';
    applyFilters();
}

function setStatus(s: string) {
    status.value = s;
    // aquí lo aplicamos de inmediato porque es un “tab”
    applyFilters();
}

function goTo(url: string | null) {
    if (!url) return;
    router.visit(url, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Facturas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="props.company.name"
            title="Facturas"
            description="Tus facturas de LAUDA: lo que se cobró, lo que ya pagaste y lo que queda pendiente."
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
                            @click="setStatus(value)"
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
                    class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[2fr_1fr_1fr_auto] md:items-end dark:border-slate-800 dark:bg-slate-950"
                    @submit.prevent="applyFilters"
                >
                    <label class="block text-sm">
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                            >Número de factura o comprobante</span
                        >
                        <input
                            v-model="q"
                            type="search"
                            class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                            placeholder="Ej. LAUDA-0001 o E310000000001"
                        />
                    </label>
                    <label class="block text-sm">
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                            >Emitida desde</span
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
                            ? 'No hay facturas con estos filtros'
                            : 'Todavía no tienes facturas'
                    }}
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    {{
                        hasActiveFilters
                            ? 'Prueba con otro estado o quita los filtros.'
                            : 'Aquí aparecerán cuando contrates una aplicación.'
                    }}
                </p>
            </div>

            <ul
                v-else
                class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950"
            >
                <li
                    v-for="inv in rows"
                    :key="inv.id"
                    class="grid gap-3 p-4 sm:p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2
                                class="font-semibold break-all text-slate-950 dark:text-white"
                            >
                                {{ inv.number }}
                            </h2>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="statusTone(inv.status)"
                                >{{ statusLabel(inv.status) }}</span
                            >
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Emitida {{ formatDay(inv.issued_on) }}
                            <template v-if="inv.due_on">
                                · Vence {{ formatDay(inv.due_on) }}</template
                            >
                            <template v-if="inv.fiscal_number">
                                · Comprobante {{ inv.fiscal_number }}</template
                            >
                        </p>
                        <p class="text-sm text-slate-800 dark:text-slate-200">
                            Total {{ formatMoney(inv.total, inv.currency) }}
                            <span
                                v-if="Number(inv.balance) > 0"
                                class="font-semibold"
                                :class="balanceClass(inv)"
                            >
                                · Pendiente
                                {{
                                    formatMoney(inv.balance, inv.currency)
                                }}</span
                            >
                            <span
                                v-else-if="Number(inv.amount_paid) > 0"
                                class="text-slate-600 dark:text-slate-400"
                            >
                                · Pagado
                                {{
                                    formatMoney(inv.amount_paid, inv.currency)
                                }}</span
                            >
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row md:justify-end">
                        <a
                            v-if="inv.payment_url"
                            :href="inv.payment_url"
                            target="_blank"
                            rel="noopener"
                            :class="portalButton.primary"
                            >Pagar</a
                        >
                        <Link
                            :href="`/subscriber/invoices/${inv.id}`"
                            :class="portalButton.secondary"
                            >Ver factura</Link
                        >
                    </div>
                </li>
            </ul>

            <nav
                v-if="props.invoices.links?.length > 3"
                aria-label="Páginas de facturas"
                class="flex flex-wrap gap-2"
            >
                <button
                    v-for="l in props.invoices.links"
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
