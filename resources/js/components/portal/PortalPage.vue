<script setup lang="ts">
/**
 * Marco común de las pantallas del cliente: mismo fondo, ancho y encabezado
 * (empresa · título · frase corta · acciones). Las acciones bajan debajo del
 * texto en móvil y se alinean a la derecha desde `md`.
 */
defineProps<{
    title: string;
    description?: string | null;
    eyebrow?: string | null;
}>();
</script>

<template>
    <div class="min-h-full bg-[#F7F7F5] dark:bg-background">
        <div
            class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
        >
            <header
                class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
            >
                <div class="min-w-0">
                    <p
                        v-if="eyebrow"
                        class="mb-1 truncate text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ eyebrow }}
                    </p>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-950 sm:text-[28px] dark:text-white"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-1.5 max-w-2xl text-[15px] leading-6 text-slate-600 dark:text-slate-400"
                    >
                        {{ description }}
                    </p>
                </div>
                <div
                    v-if="$slots.actions"
                    class="flex flex-wrap items-center gap-2 md:shrink-0 md:justify-end"
                >
                    <slot name="actions" />
                </div>
            </header>

            <slot />
        </div>
    </div>
</template>
