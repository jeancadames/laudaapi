<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue'

type Solution = {
    title: string
    desc: string
    badge?: string
    target: string
}

const props = defineProps<{
    solutions: Solution[]
    onCtaClick: (s: Solution) => void
}>()

function handleClick(s: Solution) {
    props.onCtaClick(s)
}
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3 auto-rows-fr">
        <article v-for="s in solutions" :key="s.title" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition
                   hover:-translate-y-1 hover:border-red-600/40 hover:shadow-lg
                   dark:border-slate-800 dark:bg-slate-950 dark:hover:border-red-600/50">
            <div class="mb-3 flex items-center gap-2">
                <span class="inline-block h-2 w-2 rounded-full bg-red-600" />
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                    {{ s.badge || 'Opción LaudaAPI' }}
                </p>
            </div>

            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                {{ s.title }}
            </h3>

            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300 flex-1">
                {{ s.desc }}
            </p>

            <!-- ✅ CTA visible, elegante y consistente -->
            <Button type="button" variant="outline" class="mt-4 inline-flex items-center justify-center gap-2 self-start rounded-full
                       border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900
                       shadow-sm transition
                       hover:bg-red-50 hover:border-red-300 hover:text-slate-900
                       focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 focus:ring-offset-white
                       dark:border-red-900/40 dark:bg-slate-950 dark:text-slate-100
                       dark:hover:bg-red-950/30 dark:hover:border-red-700/60
                       dark:focus:ring-offset-slate-950" @click="handleClick(s)">
                Ver detalle
                <svg class="h-3 w-3 transition-transform duration-200 group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a1 1 0 0 1 1-1h9.586L10.293 5.707a1 1 0 1 1 1.414-1.414l5 5a1 1 0 0 1 0 1.414l-5 5A1 1 0 0 1 10.293 14.3L13.586 11H4a1 1 0 0 1-1-1Z" clip-rule="evenodd" />
                </svg>
            </Button>
        </article>
    </div>
</template>
