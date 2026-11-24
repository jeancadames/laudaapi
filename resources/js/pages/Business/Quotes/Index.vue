<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { timezoneLabel } from '@/lib/timezoneLabel';
import type { BusinessQuoteSummary } from '@/types/businessQuoteAcceptance';

const page = usePage();

defineProps<{
    quotes: {
        data: BusinessQuoteSummary[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const money = (quote: BusinessQuoteSummary) =>
    new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: quote.currency,
    }).format(quote.recurring_cents / 100);
const validUntil = (quote: BusinessQuoteSummary) =>
    new Intl.DateTimeFormat('es-DO', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: quote.timezone,
    }).format(new Date(quote.valid_until));
const status = {
    available: 'Por revisar',
    accepted: 'Aceptada',
    unavailable: 'No disponible',
};
/** Ámbar = te toca revisar · verde = aceptada · gris = ya no disponible. */
const tone = {
    available:
        'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
    accepted:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    unavailable:
        'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300',
};
</script>

<template>
    <Head title="Cotizaciones" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Inicio', href: '/app' },
            { title: 'Cotizaciones', href: '/app/business/quotes' },
        ]"
    >
        <PortalPage
            title="Cotizaciones"
            description="Revisa la oferta, su alcance y las condiciones antes de confirmar la aceptación por tu empresa."
        >
            <template v-if="page.props.canConfigureBusinessPackage" #actions>
                <Link
                    href="/app/business/self-service"
                    :class="portalButton.primary"
                    >Armar mi paquete</Link
                >
            </template>

            <div
                v-if="quotes.data.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-950"
            >
                <h2 class="font-semibold text-slate-950 dark:text-white">
                    No tienes cotizaciones disponibles
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Aquí aparecerán las cotizaciones emitidas para las empresas
                    que administras.
                </p>
            </div>
            <ul
                v-else
                class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950"
            >
                <li
                    v-for="quote in quotes.data"
                    :key="quote.public_id"
                    class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                >
                    <div class="min-w-0 space-y-1">
                        <p
                            class="text-sm break-words text-slate-600 dark:text-slate-400"
                        >
                            {{ quote.company_name }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2
                                class="font-semibold text-slate-950 dark:text-white"
                            >
                                {{ quote.offer_name }} · {{ quote.level_name }}
                            </h2>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="tone[quote.status]"
                                >{{ status[quote.status] }}</span
                            >
                        </div>
                        <p class="text-sm text-slate-800 dark:text-slate-200">
                            {{ money(quote) }} /
                            {{
                                quote.billing_cycle === 'yearly' ? 'año' : 'mes'
                            }}
                            <span class="text-slate-600 dark:text-slate-400"
                                >antes de impuestos</span
                            >
                        </p>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Válida hasta {{ validUntil(quote) }} ({{
                                timezoneLabel(quote.timezone)
                            }})
                        </p>
                    </div>
                    <Link
                        :href="quote.url"
                        :aria-label="`Revisar ${quote.offer_name} de ${quote.company_name}`"
                        class="w-full sm:w-auto sm:shrink-0"
                        :class="portalButton.secondary"
                        >Revisar cotización</Link
                    >
                </li>
            </ul>
            <nav
                v-if="quotes.last_page > 1"
                aria-label="Páginas de cotizaciones"
                class="flex items-center justify-between gap-3 text-sm text-slate-600"
            >
                <Link
                    v-if="quotes.prev_page_url"
                    :href="quotes.prev_page_url"
                    :class="portalButton.secondary"
                    >Anterior</Link
                >
                <span
                    >Página {{ quotes.current_page }} de
                    {{ quotes.last_page }}</span
                >
                <Link
                    v-if="quotes.next_page_url"
                    :href="quotes.next_page_url"
                    :class="portalButton.secondary"
                    >Siguiente</Link
                >
            </nav>
        </PortalPage>
    </AppLayout>
</template>
