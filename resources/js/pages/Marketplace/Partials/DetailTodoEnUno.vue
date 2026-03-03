<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';

type Feature = { title: string; desc: string }

defineProps<{
    features: Feature[]
    openRequestForm: () => void
    scrollTo: (selector: string) => void
    openContact: () => void
}>()
</script>

<template>
    <section id="detail-todo-en-uno" class="bg-white py-16 dark:bg-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-red-600/25 bg-red-600/10 px-3 py-1 text-xs font-semibold text-red-700
                   dark:border-red-600/35 dark:bg-red-600/15 dark:text-red-300">
                        <span class="inline-block h-2 w-2 rounded-full bg-red-600" />
                        Solución destacada · Todo-en-Uno
                    </div>

                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                        LaudaAPI Todo-en-Uno
                        <span class="text-red-600"> (API completa + operación)</span>
                    </h2>

                    <p class="mt-3 text-base leading-relaxed text-slate-600 dark:text-slate-300">
                        Diseñada para operar facturación electrónica DGII con control, automatización y
                        trazabilidad: desde certificados y firma hasta estatus, acuses, notificaciones y
                        almacenamiento normativo.
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

            <!-- Content grid -->
            <div class="mt-10 grid gap-8 lg:grid-cols-12">
                <!-- Left: feature list -->
                <div class="lg:col-span-7">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="(f, idx) in features" :key="idx" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm transition
                     dark:border-slate-800 dark:bg-slate-900/30 dark:shadow-none">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-xl bg-red-600/10 text-red-700 dark:bg-red-600/15 dark:text-red-300" aria-hidden="true">
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
                            Pensado para operación real
                        </p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            Cuando el volumen crece, lo importante no es solo “enviar”, sino gestionar pendientes,
                            reintentos, incidencias, auditoría y notificaciones. Todo eso vive aquí.
                        </p>
                    </div>
                </div>

                <!-- Right: “product card / mock” -->
                <aside class="lg:col-span-5">
                    <div class="sticky top-6 rounded-3xl border border-red-600/25 bg-red-600/5 p-6 dark:border-red-600/35 dark:bg-red-600/10">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold text-red-700 dark:text-red-300">
                                    Incluye
                                </p>
                                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                                    Dashboard + Automatización + Seguridad
                                </p>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                    Operación completa para emisores electrónicos: control de certificados, colas,
                                    acuses, alertas y resguardo.
                                </p>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-600/10 dark:bg-red-600/15">
                                <span class="h-2 w-2 rounded-full bg-red-600" />
                            </div>
                        </div>

                        <div class="mt-6 space-y-3">
                            <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-950 dark:shadow-none">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Flujo
                                </p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                                    XML → Firma → Envío → Estatus → Acuses → Resguardo
                                </p>
                            </div>

                            <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-950 dark:shadow-none">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Alertas
                                </p>
                                <ul class="mt-2 space-y-2 text-sm text-slate-700 dark:text-slate-200">
                                    <li class="flex items-start gap-2">
                                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600" />
                                        Contingencia DGII
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600" />
                                        Vencimiento de certificados
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600" />
                                        Mantenimientos programados
                                    </li>
                                </ul>
                            </div>

                            <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-950 dark:shadow-none">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Resguardo
                                </p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                                    XML almacenados por 10 años
                                </p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                    Acceso y auditoría para soporte interno y cumplimiento.
                                </p>
                            </div>
                        </div>

                        <button type="button" @click="openContact()" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700
                     focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-950">
                            Hablar con un especialista
                        </button>

                        <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
                            Te recomendaremos la integración según tu sistema y volumen.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</template>
