<script setup lang="ts">
/** Sección con título, conteo opcional y una frase de ayuda. */
defineProps<{
    title: string;
    description?: string | null;
    count?: number | null;
    headingId?: string;
}>();
</script>

<template>
    <section :aria-labelledby="headingId" class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
            <div class="min-w-0">
                <h2
                    :id="headingId"
                    class="flex items-center gap-2 text-lg font-semibold text-slate-950 dark:text-white"
                >
                    {{ title }}
                    <span
                        v-if="count !== null && count !== undefined"
                        class="rounded-full bg-slate-200/70 px-2 py-0.5 text-xs font-semibold text-slate-700 tabular-nums dark:bg-slate-800 dark:text-slate-200"
                    >
                        {{ count }}
                    </span>
                </h2>
                <p
                    v-if="description"
                    class="mt-0.5 text-sm text-slate-600 dark:text-slate-400"
                >
                    {{ description }}
                </p>
            </div>
            <slot name="aside" />
        </div>
        <slot />
    </section>
</template>
