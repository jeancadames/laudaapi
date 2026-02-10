<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';

type Highlight = string
type Module = { title: string; desc: string; tags?: string[] }

const props = defineProps<{
    highlights: Highlight[]
    modules: Module[]
    openContact: () => void
}>()
</script>

<template>
    <section id="detail-erp-modular" class="bg-slate-50 py-16 dark:bg-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700
                 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                    <span class="inline-block h-2 w-2 rounded-full bg-red-600" />
                    Integración · Ecosistema LAUDA
                </div>

                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                    Integración con
                    <span class="text-red-600"> LAUDA ERP Modular</span>
                </h2>

                <p class="mt-3 text-base leading-relaxed text-slate-600 dark:text-slate-300">
                    Un ERP modular para operar tu empresa por áreas: ventas, servicio, proyectos, RRHH, bancos
                    y contabilidad. Activa solo los módulos que necesitas y escala por etapas, con trazabilidad
                    y control.
                </p>
            </div>

            <!-- Content -->
            <div class="mt-12 grid gap-10 lg:grid-cols-12">
                <!-- LEFT (CTA / value) -->
                <aside class="lg:col-span-5 lg:order-1">
                    <div class="sticky top-6 rounded-3xl border border-red-600/25 bg-red-600/5 p-6
                   dark:border-red-600/35 dark:bg-red-600/10">
                        <p class="text-xs font-semibold text-red-700 dark:text-red-300">
                            ERP Modular · Implementación por etapas
                        </p>

                        <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                            ¿Quieres mapear tus módulos y armar el alcance?
                        </p>

                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                            Revisamos tu operación y definimos el paquete de módulos ideal (por industria, volumen
                            y procesos), con una ruta de implementación clara.
                        </p>

                        <ul class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-200">
                            <li v-for="(h, i) in highlights" :key="i" class="flex items-start gap-2">
                                <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600" />
                                {{ h }}
                            </li>
                        </ul>

                        <Button type="button" @click="openContact()" class="mt-6 inline-flex w-full items-center justify-center rounded-xl
                     bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700
                     focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2
                     focus:ring-offset-slate-50 dark:focus:ring-offset-slate-950">
                            Hablar con un especialista
                        </Button>
                        <Button type="button" @click="openContact()" class="mt-6 inline-flex w-full items-center justify-center rounded-xl
           bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700
           focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2
           focus:ring-offset-slate-50 dark:focus:ring-offset-slate-950">
                            Activar prueba gratis 30 días
                        </Button>

                        <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
                            Sin compromiso · Te orientamos según tu operación real
                        </p>
                    </div>
                </aside>

                <!-- RIGHT (Modules grid) -->
                <div class="lg:col-span-7 lg:order-2">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="(m, idx) in modules" :key="idx" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm
                     dark:border-slate-800 dark:bg-slate-950 dark:shadow-none">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-xl
                         bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200" aria-hidden="true">
                                    <svg class="h-4 w-4 text-red-600" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 0 1.415l-7.25 7.25a1 1 0 0 1-1.415 0L3.296 9.21a1 1 0 0 1 1.415-1.415l3.02 3.02 6.543-6.525a1 1 0 0 1 1.41 0Z" clip-rule="evenodd" />
                                    </svg>
                                </span>

                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ m.title }}
                                    </p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                        {{ m.desc }}
                                    </p>

                                    <div v-if="m.tags?.length" class="mt-3 flex flex-wrap gap-2">
                                        <span v-for="(t, i) in m.tags" :key="i" class="inline-flex items-center rounded-full bg-red-600/10 px-2.5 py-1 text-xs font-semibold text-red-700
                             dark:bg-red-600/15 dark:text-red-300">
                                            {{ t }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            Resultado
                        </p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            Un solo ecosistema: módulos conectados entre sí, con control de permisos, auditoría y
                            datos consistentes. Y cuando activas facturación electrónica, el flujo queda listo para
                            operar sin fricción.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
