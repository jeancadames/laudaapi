<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import type { BusinessQuoteSummary } from '@/types/businessQuoteAcceptance';

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
</script>

<template>
    <Head title="Cotizaciones" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Inicio', href: '/app' },
            { title: 'Cotizaciones', href: '/app/business/quotes' },
        ]"
    >
        <div class="mx-auto w-full max-w-5xl space-y-8 px-5 py-8 sm:px-8">
            <header class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Cotizaciones
                </h1>
                <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                    Revisa la oferta, su alcance y las condiciones antes de
                    confirmar la aceptación por tu empresa.
                </p>
            </header>
            <div
                v-if="quotes.data.length === 0"
                class="rounded-xl border border-dashed p-8 text-center"
            >
                <h2 class="font-medium">No tienes cotizaciones disponibles</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Aquí aparecerán las cotizaciones emitidas para las empresas
                    que administras.
                </p>
            </div>
            <ul v-else class="divide-y rounded-xl border bg-background px-5">
                <li
                    v-for="quote in quotes.data"
                    :key="quote.public_id"
                    class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm break-words text-muted-foreground">
                            {{ quote.company_name }}
                        </p>
                        <h2 class="font-semibold">
                            {{ quote.offer_name }} · {{ quote.level_name }}
                        </h2>
                        <p class="text-sm">
                            {{ money(quote) }} /
                            {{
                                quote.billing_cycle === 'yearly' ? 'año' : 'mes'
                            }}
                            <span class="text-muted-foreground"
                                >antes de impuestos</span
                            >
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ status[quote.status] }}
                        </p>
                        <p class="text-xs leading-5 text-muted-foreground">
                            Válida hasta {{ validUntil(quote) }} ({{
                                quote.timezone
                            }})
                        </p>
                    </div>
                    <Button variant="outline" as-child
                        ><Link
                            :href="quote.url"
                            :aria-label="`Revisar ${quote.offer_name} de ${quote.company_name}`"
                            >Revisar cotización</Link
                        ></Button
                    >
                </li>
            </ul>
            <nav
                v-if="quotes.last_page > 1"
                aria-label="Páginas de cotizaciones"
                class="flex items-center justify-between gap-3 text-sm"
            >
                <Button v-if="quotes.prev_page_url" variant="outline" as-child
                    ><Link :href="quotes.prev_page_url">Anterior</Link></Button
                >
                <span
                    >Página {{ quotes.current_page }} de
                    {{ quotes.last_page }}</span
                >
                <Button v-if="quotes.next_page_url" variant="outline" as-child
                    ><Link :href="quotes.next_page_url">Siguiente</Link></Button
                >
            </nav>
        </div>
    </AppLayout>
</template>
