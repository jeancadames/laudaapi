<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';

type Characteristic = { title: string; desc: string }
const props = defineProps<{
    characteristics: Characteristic[]
    deliverables: string[]
    openRequestForm: () => void
    scrollTo: (selector: string) => void
    openContact: () => void
}>()
</script>

<template>
    <section id="detail-api-propios" class="bg-white py-16 dark:bg-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700
                   dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                        <span class="inline-block h-2 w-2 rounded-full bg-red-600" />
                        Integración modular · Sistemas propios
                    </div>

                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                        API para
                        <span class="text-red-600"> sistemas propios</span>
                    </h2>

                    <p class="mt-3 text-base leading-relaxed text-slate-600 dark:text-slate-300">
                        Conecta tu sistema actual a la DGII sin cambiar tu core. Implementamos un conector y un
                        wrapper XML para generar, firmar y enviar documentos con validación y trazabilidad.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button type="button" @click="openRequestForm()" class="inline-flex items-center justify-center rounded-xl
               bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm
               transition hover:bg-blue-700
               focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2
               focus:ring-offset-white dark:focus:ring-offset-slate-950">
                            Iniciar proceso de activación
                        </Button>
                    </div>

                    <Button type="button" @click="scrollTo('#solutions')" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50
                   focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 focus:ring-offset-white
                   dark:border-slate-800 dark:bg-slate-950 dark:text-white dark:hover:bg-slate-900 dark:focus:ring-offset-slate-950">
                        Ver todas las soluciones
                    </Button>
                </div>
            </div>

            <!-- Content -->
            <div class="mt-10 grid gap-8 lg:grid-cols-12">
                <!-- Left: feature list -->
                <div class="lg:col-span-7">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="(f, idx) in characteristics" :key="idx" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm transition
                     dark:border-slate-800 dark:bg-slate-900/30 dark:shadow-none">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-xl bg-red-600/10 text-red-700
                         dark:bg-red-600/15 dark:text-red-300" aria-hidden="true">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 0 1.415l-7.25 7.25a1 1 0 0 1-1.415 0L3.296 9.21a1 1 0 0 1 1.415-1.415l3.02 3.02 6.543-6.525a1 1 0 0 1 1.41 0Z" clip-rule="evenodd" />
                                    </svg>
                                </span>

                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ f.title }}
                                    </p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                        {{ f.desc }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- note -->
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            Enfoque
                        </p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            Se integra con tu modelo de datos actual y se adapta a tu flujo. El objetivo es cumplir
                            DGII sin romper tu sistema: conectamos, validamos y damos visibilidad operativa.
                        </p>
                    </div>
                </div>

                <!-- Right: “integration card” -->
                <aside class="lg:col-span-5">
                    <div class="sticky top-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:shadow-none">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    Incluye
                                </p>
                                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                                    Conector + Wrapper XML + Operación DGII
                                </p>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                    Ideal para ERPs internos o sistemas legacy. Conexión segura y trazabilidad sin
                                    reescritura.
                                </p>
                            </div>

                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50
                       dark:border-slate-800 dark:bg-slate-900" aria-hidden="true">
                                <span class="h-2 w-2 rounded-full bg-red-600" />
                            </div>
                        </div>

                        <div class="mt-6 space-y-3">
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/30">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Flujo
                                </p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                                    Factura → XML validado → Firma → Envío → Estatus → Acuses
                                </p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/30">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Entregables típicos
                                </p>
                                <ul class="mt-2 space-y-2 text-sm text-slate-700 dark:text-slate-200">
                                    <li v-for="(d, i) in deliverables" :key="i" class="flex items-start gap-2">
                                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600" />
                                        {{ d }}
                                    </li>
                                </ul>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/30">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Operación
                                </p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                    Soporte a incidencias y reintentos controlados. Visibilidad para TI y operación.
                                </p>
                            </div>
                        </div>

                        <Button type="button" @click="openContact()" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700
                     focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-950">
                            Hablar con un especialista
                        </Button>

                        <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
                            Te guiamos según tu sistema y requerimientos DGII.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</template>
