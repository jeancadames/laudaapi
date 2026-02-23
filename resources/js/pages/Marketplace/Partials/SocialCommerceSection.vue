<script setup lang="ts">
const scrollTo = (hash: any) => {
    const el = document.querySelector(hash)
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const props = defineProps<{
    openRequestForm: () => void
}>()

const highlights = [
    'Automatiza e-CF por cada venta en redes (sin procesos manuales)',
    'Trazabilidad por orden: origen, estatus DGII, acuses y evidencia',
    'Control de errores + reintentos operativos',
    'Integrable con CRM, ventas, inventario, bancos y contabilidad',
]

const modules = [
    {
        title: 'Facebook Shop / Commerce API',
        desc: 'Órdenes, productos y estado de compra desde el ecosistema Meta (Facebook/Instagram) vía Graph/Commerce.',
        tags: [ 'Commerce API', 'Órdenes', 'Catálogo' ],
    },
    {
        title: 'Webhooks de compra',
        desc: 'Recepción en tiempo real de eventos cuando se crea una orden: cliente, items, cantidades, precios e impuestos.',
        tags: [ 'Webhooks', 'Eventos', 'Tiempo real' ],
    },
    {
        title: 'Normalización + SalesOrder interno',
        desc: 'Convertimos la orden social a tu modelo interno (SalesOrderSource = Social) y guardamos payload para auditoría.',
        tags: [ 'SalesOrder', 'Auditoría', 'Trazabilidad' ],
    },
    {
        title: 'Generación e-CF DGII',
        desc: 'Construcción del XML, firma digital y envío a DGII; incluye estatus, acuses, código de seguridad y QR.',
        tags: [ 'XML', 'Firma', 'DGII' ],
    },
    {
        title: 'Cumplimiento fiscal',
        desc: 'Validaciones, evidencia y resguardo normativo. Reduce riesgo fiscal al formalizar ventas provenientes de redes.',
        tags: [ 'Cumplimiento', 'Evidencia', 'Control' ],
    },
    {
        title: 'Operación (pendientes + reintentos)',
        desc: 'Gestión operativa de envíos: colas, reintentos, incidencias y monitoreo desde dashboard.',
        tags: [ 'Colas', 'Reintentos', 'Dashboard' ],
    },
    {
        title: 'Delivery y canales',
        desc: 'Preparado para flujos tipo comida rápida (ej. PedidosYa), kioskos y ventas rápidas desde redes.',
        tags: [ 'PedidosYa', 'QuickServe', 'POS' ],
    },
    {
        title: 'Actualización de estado (opcional)',
        desc: 'Opcionalmente, actualizamos el estado de la orden en Meta (fulfillment) y registramos la referencia fiscal.',
        tags: [ 'Fulfillment', 'Tracking', 'Opcional' ],
    },
]
</script>

<template>
    <section class="bg-white py-16 dark:bg-slate-950" id="social-commerce">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700
                 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                    <span class="inline-block h-2 w-2 rounded-full bg-red-600"></span>
                    Integración · Redes Sociales
                </div>

                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                    Facebook Shop / Social Commerce →
                    <span class="text-red-600"> e-CF DGII</span>
                </h2>

                <p class="mt-3 text-base leading-relaxed text-slate-600 dark:text-slate-300">
                    Facebook no emite facturas fiscales. LaudaAPI conecta tus ventas de redes sociales con tu flujo de facturación
                    electrónica para generar, firmar y enviar e-CF automáticamente, con trazabilidad completa por orden.
                </p>
            </div>

            <!-- Content -->
            <div class="mt-12 grid gap-10 lg:grid-cols-12">
                <!-- LEFT (CTA / value) -->
                <aside class="lg:col-span-5 lg:order-1">
                    <div class="sticky top-6 rounded-3xl border border-red-600/25 bg-red-600/5 p-6
                   dark:border-red-600/35 dark:bg-red-600/10">
                        <p class="text-xs font-semibold text-red-700 dark:text-red-300">
                            Social Commerce · Automatización fiscal
                        </p>

                        <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                            ¿Quieres facturar automáticamente las ventas de redes?
                        </p>

                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                            Integramos Facebook/Instagram Shop, normalizamos la orden y emitimos e-CF DGII (XML + firma + envío),
                            con estatus, acuses y evidencia fiscal por documento.
                        </p>

                        <ul class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-200">
                            <li v-for="(h, i) in highlights" :key="i" class="flex items-start gap-2">
                                <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                {{ h }}
                            </li>
                        </ul>

                        <button type="button" @click="openRequestForm" class="mt-6 inline-flex w-full items-center justify-center rounded-xl
                     bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700
                     focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2
                     focus:ring-offset-white dark:focus:ring-offset-slate-950">
                            Integrar redes sociales
                        </button>

                        <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
                            Te orientamos según canal (retail, kiosko, comida rápida) y volumen.
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
                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.25 7.25a1 1 0 01-1.415 0L3.296 9.21a1 1 0 011.415-1.415l3.02 3.02 6.543-6.525a1 1 0 011.41 0z" clip-rule="evenodd" />
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
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Resultado</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            Tus ventas en redes quedan formalizadas: orden social → venta interna → e-CF DGII → evidencia y auditoría.
                            Menos riesgo fiscal, menos trabajo manual y operación escalable.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
