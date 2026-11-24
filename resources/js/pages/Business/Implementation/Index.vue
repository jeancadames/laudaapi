<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
defineProps<{
    orders: {
        data: {
            id: number;
            company_name: string;
            status_label: string;
            url: string;
        }[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>
<template>
    <Head title="Implementaciones Business" />
    <AppLayout
        :breadcrumbs="[
            {
                title: 'Implementaciones Business',
                href: '/admin/business-implementations',
            },
        ]"
    >
        <div class="mx-auto w-full max-w-5xl space-y-8 px-5 py-8 sm:px-8">
            <header class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Implementaciones Business
                </h1>
                <p class="text-sm leading-6 text-muted-foreground">
                    Coordina el alcance contratado, registra avances y solicita
                    la conformidad de la empresa.
                </p>
            </header>
            <p
                v-if="!orders.data.length"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Las solicitudes de implementación aparecerán aquí.
            </p>
            <ul v-else class="divide-y rounded-xl border px-5">
                <li
                    v-for="order in orders.data"
                    :key="order.id"
                    class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <h2 class="font-semibold break-words">
                            {{ order.company_name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Orden #{{ order.id }} · {{ order.status_label }}
                        </p>
                    </div>
                    <Button as-child variant="outline" class="min-h-11"
                        ><Link
                            :href="order.url"
                            :aria-label="`Revisar implementación de ${order.company_name}`"
                            >Revisar implementación</Link
                        ></Button
                    >
                </li>
            </ul>
            <nav
                v-if="orders.last_page > 1"
                aria-label="Páginas de implementaciones"
                class="flex flex-wrap items-center justify-between gap-3 text-sm"
            >
                <Button
                    v-if="orders.prev_page_url"
                    variant="outline"
                    as-child
                    class="min-h-11"
                    ><Link :href="orders.prev_page_url">Anterior</Link></Button
                >
                <span
                    >Página {{ orders.current_page }} de
                    {{ orders.last_page }}</span
                >
                <Button
                    v-if="orders.next_page_url"
                    variant="outline"
                    as-child
                    class="min-h-11"
                    ><Link :href="orders.next_page_url">Siguiente</Link></Button
                >
            </nav>
        </div>
    </AppLayout>
</template>
