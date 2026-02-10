<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue'
import SolutionsCarousel from './SolutionsCarousel.vue'

type Solution = {
    title: string
    desc: string
    badge?: string
    target: string
}

const props = defineProps<{
    solutions: Solution[]
    scrollToSection: (target: string) => void
    openContact: () => void
}>()

function onCtaClick(s: Solution) {
    props.scrollToSection(s.target)
}
</script>

<template>
    <section id="solutions" class="bg-slate-50 py-16 dark:bg-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700
                 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                    <span class="inline-block h-2 w-2 rounded-full bg-red-600" />
                    Soluciones LaudaAPI
                </div>

                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                    Elige el enfoque correcto para tu operación
                </h2>
                <p class="mt-3 text-base leading-relaxed text-slate-600 dark:text-slate-300">
                    Integra por módulos si ya tienes sistema propio, o adopta Todo-en-Uno para operar con
                    trazabilidad, automatización y seguridad completa.
                </p>
            </div>

            <!-- Carousel -->
            <div class="mt-10">
                <SolutionsCarousel :solutions="solutions" :onCtaClick="onCtaClick" />
            </div>

            <!-- Bottom note -->
            <div class="mt-10 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-950">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            ¿No estás seguro por dónde empezar?
                        </p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            Te hacemos una evaluación rápida y te recomendamos la ruta (módulos vs Todo-en-Uno).
                        </p>
                    </div>

                    <Button type="button" @click="openContact()" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700
                   focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-950">
                        Hablar con un especialista
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
