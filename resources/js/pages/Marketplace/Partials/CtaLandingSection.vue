<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';

const emit = defineEmits([ 'open-contact' ])

const openContact = () => {
    emit('open-contact')
}
</script>

<template>
    <section class="bg-white py-20 dark:bg-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl border border-red-600/30
               bg-red-600/5 p-10 dark:border-red-600/40 dark:bg-red-600/10">
                <!-- background glow -->
                <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-red-600/20 blur-3xl"></div>

                <div class="relative grid gap-8 lg:grid-cols-2 lg:items-center">
                    <!-- Text -->
                    <div>
                        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            ¿Listo para habilitar
                            <span class="text-red-600">facturación electrónica DGII</span>?
                        </h2>

                        <p class="mt-4 max-w-xl text-base leading-relaxed text-slate-600 dark:text-slate-300">
                            Cuéntanos tu escenario (ERP propio, volumen de documentos, certificados) y te recomendamos
                            la integración correcta para cumplir y escalar sin fricción.
                        </p>

                        <ul class="mt-6 space-y-2 text-sm text-slate-700 dark:text-slate-200">
                            <li class="flex items-start gap-2">
                                <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                Evaluación técnica rápida
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                Recomendación de solución (API o Todo-en-Uno)
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                Ruta clara de implementación
                            </li>
                        </ul>
                    </div>

                    <!-- CTA -->
                    <div class="flex flex-col items-start gap-4 lg:items-end">
                        <Button type="button" @click="openContact" class="inline-flex items-center justify-center rounded-xl
                     bg-red-600 px-8 py-4 text-base font-semibold text-white shadow-sm
                     transition hover:bg-red-700
                     focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2
                     focus:ring-offset-white dark:focus:ring-offset-slate-950">
                            Hablar con un especialista
                        </Button>

                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Sin compromiso · Respuesta personalizada
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
