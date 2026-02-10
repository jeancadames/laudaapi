<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue'

const stats = [
    {
        label: 'Multi‑tenant',
        desc: 'Aislamiento por cliente y contribuyente.',
    },
    {
        label: 'DGII Ready',
        desc: 'XML, firma digital, envío y acuses.',
    },
    {
        label: 'API Modular',
        desc: 'Activa solo los módulos que necesitas.',
    },
]

function handlePrimary() {
    // Navegar a registro o sección de contacto
    const el = document.getElementById('solutions')
    if (el) el.scrollIntoView({ behavior: 'smooth' })
}

function handleSecondary() {
    const el = document.getElementById('solutions')
    if (el) el.scrollIntoView({ behavior: 'smooth' })
}
</script>

<template>
    <section class="relative overflow-hidden bg-white dark:bg-slate-950">
        <!-- subtle background -->
        <div class="absolute inset-0">
            <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-red-600/10 blur-3xl dark:bg-red-600/15" />
            <div class="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-slate-900/5 blur-3xl dark:bg-slate-200/5" />
        </div>

        <div class="relative mx-auto max-w-7xl px-4 pt-8 pb-16 sm:px-6 lg:px-8 lg:pt-16 lg:pb-24">
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <!-- LEFT -->
                <div>
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700
                   dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                        <span class="inline-block h-2 w-2 rounded-full bg-red-600" />
                        LaudaAPI Hub · API Marketplace Modular
                    </div>

                    <!-- Headline -->
                    <h1 class="mt-5 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                        El Marketplace de APIs
                        <span class="text-red-600"> para tu empresa</span>.
                    </h1>

                    <!-- Subheadline -->
                    <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-300">
                        Activa módulos empresariales listos para producción: facturación electrónica DGII, CRM, ventas,
                        servicios, proyectos, transporte, contabilidad y más. Todo desde un solo API Hub.
                    </p>

                    <!-- CTA -->
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Button type="button" @click="handlePrimary" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700
                     focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-950">
                            Comenzar ahora
                            <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 10a1 1 0 0 1 1-1h9.586L10.293 5.707a1 1 0 0 1 1.414-1.414l5 5a1 1 0 0 1 0 1.414l-5 5a1 1 0 0 1-1.414-1.414L13.586 11H4a1 1 0 0 1-1-1Z" clip-rule="evenodd" />
                            </svg>
                        </Button>

                        <Button type="button" @click="handleSecondary" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50
                     dark:border-slate-800 dark:bg-slate-950 dark:text-white dark:hover:bg-slate-900 dark:focus:ring-offset-slate-950">
                            Ver módulos
                        </Button>

                        <div class="text-xs text-slate-500 dark:text-slate-400 sm:ml-2">
                            Multi‑tenant · Multi‑contribuyente · Modular
                        </div>
                    </div>

                    <!-- Trust bullets -->
                    <div class="mt-10 grid gap-4 sm:grid-cols-3">
                        <div v-for="(s, idx) in stats" :key="idx" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-red-600" />
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ s.label }}
                                </p>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                                {{ s.desc }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Mock panel -->
                <div class="relative">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="h-3 w-3 rounded-full bg-red-600" />
                                <div class="h-3 w-3 rounded-full bg-slate-300 dark:bg-slate-700" />
                                <div class="h-3 w-3 rounded-full bg-slate-300 dark:bg-slate-700" />
                            </div>
                            <div class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                Dashboard · Módulos activos
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-950">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                        Módulos activos
                                    </p>
                                    <span class="rounded-full bg-red-600/10 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-600/15 dark:text-red-300">
                                        7 activos
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                    CRM, Ventas, Servicios, Proyectos, Transporte, Contabilidad, FE DGII.
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-950">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                        Integraciones
                                    </p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                                        API Hub
                                    </p>
                                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                        REST · Webhooks · Multi‑tenant
                                    </p>
                                </div>

                                <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-950">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                        Facturación
                                    </p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                                        DGII Ready
                                    </p>
                                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                        XML · Firma · Envío · Acuses
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-950">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                        Alertas
                                    </p>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        Activas
                                    </span>
                                </div>
                                <ul class="mt-2 space-y-2 text-xs text-slate-600 dark:text-slate-300">
                                    <li class="flex items-start gap-2">
                                        <span class="mt-1 inline-block h-1.5 w-1.5 rounded-full bg-red-600" />
                                        Certificado digital expira en 21 días
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="mt-1 inline-block h-1.5 w-1.5 rounded-full bg-red-600" />
                                        Nuevo módulo disponible: Recursos Humanos
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-400">
                        UI referencial. Tu panel se adapta a tus módulos activos.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
