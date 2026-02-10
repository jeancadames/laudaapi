<script setup lang="ts">
import { Button } from '@/components/ui/button'

type Bullet = { title: string; desc: string }

const props = defineProps<{
    bullets: Bullet[]
    openRequestForm: () => void
    openContact: () => void
}>()
</script>

<template>
    <section id="detail-facturacion-electronica" class="scroll-mt-24">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
                <!-- Left -->
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs text-muted-foreground">
                        <span class="font-medium text-foreground">API</span>
                        <span>Facturación Electrónica</span>
                    </div>

                    <h2 class="mt-4 text-3xl font-semibold tracking-tight">
                        Emite e-CF/NCF con integración segura a DGII
                    </h2>

                    <p class="mt-3 text-muted-foreground">
                        Implementa facturación electrónica cumpliendo con DGII sin fricción: validación, firma, envío, acuses,
                        estatus y resguardo. Ideal para operar con trazabilidad y control.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Button @click="props.openRequestForm">Solicitar activación</Button>
                        <Button variant="outline" @click="props.openContact">Hablar con un asesor</Button>
                    </div>

                    <div class="mt-6 rounded-2xl border bg-muted/10 p-5">
                        <div class="text-sm font-semibold">Recomendado si:</div>
                        <ul class="mt-2 space-y-2 text-sm text-muted-foreground">
                            <li class="flex items-start gap-2">
                                <span class="mt-2 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                                <span>Necesitas emitir y controlar e-CF/NCF con flujo robusto.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-2 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                                <span>Quieres colas + reintentos + monitoreo operativo.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-2 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                                <span>Requieres resguardo y trazabilidad de XML y acuses.</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Right -->
                <div class="w-full max-w-xl rounded-2xl border bg-muted/10 p-5">
                    <div class="text-sm font-semibold">Qué incluye</div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div v-for="(b, i) in props.bullets" :key="i" class="rounded-xl border bg-background p-4">
                            <div class="text-sm font-semibold">{{ b.title }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ b.desc }}</div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-xl border bg-background p-4">
                        <div class="text-sm font-semibold">Flujo operativo</div>
                        <div class="mt-2 text-sm text-muted-foreground">
                            Generas XML → validas → firmas → envías → recibes acuse/estatus → resguardas y auditas.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
