<script setup lang="ts">
import { formatMoney } from '@/lib/businessPackageCopy';

/**
 * Bloque de importes del resumen. Solo presenta filas ya calculadas por el
 * servidor; no suma ni deduce nada.
 */
defineProps<{
    title: string;
    rows: { label: string; cents: number }[];
    totalLabel: string;
    totalCents: number;
    currency: string;
    note?: string;
    emphasis?: boolean;
}>();
</script>

<template>
    <section class="space-y-3">
        <h3 class="font-semibold">{{ title }}</h3>
        <dl class="space-y-2 text-sm">
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex justify-between gap-3"
            >
                <dt class="text-muted-foreground">{{ row.label }}</dt>
                <dd class="shrink-0 tabular-nums">
                    {{ formatMoney(row.cents, currency) }}
                </dd>
            </div>
            <div
                class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 font-semibold"
                :class="rows.length ? 'border-t pt-3' : ''"
            >
                <dt>{{ totalLabel }}</dt>
                <dd
                    class="tabular-nums"
                    :class="emphasis ? 'text-2xl tracking-tight' : 'text-base'"
                >
                    {{ formatMoney(totalCents, currency) }}
                </dd>
            </div>
        </dl>
        <p v-if="note" class="text-sm leading-6 text-muted-foreground">
            {{ note }}
        </p>
    </section>
</template>
