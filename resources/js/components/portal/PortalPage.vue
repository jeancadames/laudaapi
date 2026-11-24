<script setup lang="ts">
/**
 * Marco común de las pantallas del cliente: mismo fondo, ancho y encabezado
 * (empresa · título · frase corta · acciones). Las acciones bajan debajo del
 * texto en móvil y se alinean a la derecha desde `md`.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    /** Sin título, la pantalla dibuja su propio encabezado (p. ej. el configurador, que mueve el foco entre pasos). */
    title?: string | null;
    description?: string | null;
    eyebrow?: string | null;
    /** Enlace de regreso sobre el encabezado, para pantallas de detalle. */
    back?: { href: string; label: string } | null;
    /** Ancho del contenido: `wide` (listados) o `narrow` (un solo paso). */
    width?: 'wide' | 'narrow';
}>();
</script>

<template>
    <div class="min-h-full bg-[#F7F7F5] dark:bg-background">
        <div
            class="mx-auto flex w-full flex-col gap-8 px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
            :class="width === 'narrow' ? 'max-w-3xl' : 'max-w-6xl'"
        >
            <Link
                v-if="back"
                :href="back.href"
                class="-mb-4 inline-flex min-h-10 items-center gap-1.5 self-start rounded-md text-sm font-medium text-slate-600 hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-400 dark:text-slate-400 dark:hover:text-white"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ back.label }}
            </Link>
            <header
                v-if="title"
                class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
            >
                <div class="min-w-0">
                    <p
                        v-if="eyebrow"
                        class="mb-1 text-sm font-medium break-words text-slate-500 dark:text-slate-400"
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
