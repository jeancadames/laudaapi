<script setup lang="ts">
/**
 * Tarjeta de aplicación del portal. Misma forma en Inicio, Panel de control y
 * Conexiones Business: sigla, nombre, estado, descripción y acciones al pie.
 */
import { computed } from 'vue';

type Tone = 'active' | 'pending' | 'neutral';

const props = defineProps<{
    title: string;
    description?: string | null;
    status?: string | null;
    tone?: Tone;
    /** Sigla oscura para las apps propias, clara para las disponibles. */
    solid?: boolean;
}>();

const initials = computed(() =>
    props.title
        .replace(/[^\p{L}\p{N}\s]/gu, ' ')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]!.toUpperCase())
        .join('')
        .padEnd(1, '?'),
);

const toneClass = computed(
    () =>
        ({
            active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/15 dark:bg-emerald-950/40 dark:text-emerald-300',
            pending:
                'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-200',
            neutral:
                'bg-slate-100 text-slate-600 ring-slate-500/10 dark:bg-slate-800 dark:text-slate-300',
        })[props.tone ?? 'neutral'],
);
const dotClass = computed(
    () =>
        ({
            active: 'bg-emerald-500',
            pending: 'bg-amber-500',
            neutral: 'bg-slate-400',
        })[props.tone ?? 'neutral'],
);
</script>

<template>
    <article
        class="flex h-full min-w-0 flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-950"
    >
        <div class="flex items-start gap-3">
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-bold"
                :class="
                    solid
                        ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                        : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                "
                aria-hidden="true"
            >
                {{ initials }}
            </div>
            <div class="min-w-0 flex-1">
                <h3
                    class="text-base leading-6 font-semibold break-words text-slate-950 dark:text-white"
                >
                    {{ title }}
                </h3>
                <span
                    v-if="status"
                    class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                    :class="toneClass"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="dotClass"
                        aria-hidden="true"
                    />
                    {{ status }}
                </span>
            </div>
        </div>

        <p
            v-if="description"
            class="mt-3 flex-1 text-sm leading-6 text-slate-600 dark:text-slate-400"
        >
            {{ description }}
        </p>
        <div v-else class="flex-1" />

        <div
            v-if="$slots.default"
            class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2"
        >
            <slot />
        </div>
    </article>
</template>
