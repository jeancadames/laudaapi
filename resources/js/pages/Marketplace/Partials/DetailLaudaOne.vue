<script setup lang="ts">
import { Button } from '@/components/ui/button'

type Feature = { title: string; desc: string }
type Pill = { label: string }

const props = defineProps<{
    features: Feature[]
    pillars: Pill[]
    openRequestForm: () => void
    openContact: () => void
}>()
</script>

<template>
    <section id="detail-laudaone" class="scroll-mt-24">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
                <!-- Left -->
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs text-muted-foreground">
                        <span class="font-medium text-foreground">Plataforma</span>
                        <span>LaudaOne</span>
                    </div>

                    <h2 class="mt-4 text-3xl font-semibold tracking-tight">
                        Ecommerce B2C y B2B listo para operar
                    </h2>

                    <p class="mt-3 text-muted-foreground">
                        LaudaOne es una plataforma enfocada en comercio (B2C/B2B) y operación, con módulos activables y base sólida
                        para crecer. Ideal si tu objetivo es vender, cobrar y escalar con control.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span v-for="(p, i) in props.pillars" :key="i" class="inline-flex rounded-full border px-3 py-1 text-xs text-muted-foreground">
                            {{ p.label }}
                        </span>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Button @click="props.openRequestForm">Solicitar activación</Button>
                        <Button variant="outline" @click="props.openContact">Hablar con un asesor</Button>
                    </div>
                </div>

                <!-- Right: Features -->
                <div class="w-full max-w-xl rounded-2xl border bg-muted/10 p-5">
                    <div class="text-sm font-semibold">Qué incluye LaudaOne</div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div v-for="(f, i) in props.features" :key="i" class="rounded-xl border bg-background p-4">
                            <div class="text-sm font-semibold">{{ f.title }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ f.desc }}</div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-xl border bg-background p-4">
                        <div class="text-sm font-semibold">Recomendado si:</div>
                        <ul class="mt-2 space-y-2 text-sm text-muted-foreground">
                            <li class="flex items-start gap-2">
                                <span class="mt-2 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                                <span>Quieres lanzar B2C/B2B rápido con base estable.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-2 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                                <span>Necesitas catálogo, pedidos, pagos y operación con control.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-2 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                                <span>Planeas escalar (más productos, canales o módulos) por fases.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
