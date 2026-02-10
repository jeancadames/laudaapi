<script setup lang="ts">
import { Button } from '@/components/ui/button'

type Feature = { title: string; desc: string }
type Module = { title: string; desc: string; tags?: string[] }
type AreaGroup = { area: string; items: Module[] }

const props = defineProps<{
    features: Feature[]
    integrations: string[]
    deliverables: string[]
    modulesByArea: AreaGroup[]
    openRequestForm: () => void
    openContact: () => void
}>()
</script>

<template>
    <section id="detail-api-marketplace" class="scroll-mt-24">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <!-- Header -->
            <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs text-muted-foreground">
                        <span class="font-medium text-foreground">LaudaAPI</span>
                        <span>Hub Marketplace</span>
                    </div>

                    <h2 class="mt-4 text-3xl font-semibold tracking-tight">
                        Activa módulos empresariales en minutos
                    </h2>

                    <p class="mt-3 text-muted-foreground">
                        Catálogo modular por áreas (CRM, Ventas, Servicios, Proyectos, RRHH, Bancos, Contabilidad) con activación por
                        servicio y escalamiento por fases. Ideal para crecer sin re-trabajar tu base.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Button @click="props.openRequestForm">Solicitar activación</Button>
                        <Button variant="outline" @click="props.openContact">Hablar con un asesor</Button>
                    </div>
                </div>

                <!-- Features -->
                <div class="w-full max-w-xl rounded-2xl border bg-muted/10 p-5">
                    <div class="text-sm font-semibold">Qué incluye el Hub</div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div v-for="(f, i) in props.features" :key="i" class="rounded-xl border bg-background p-4">
                            <div class="text-sm font-semibold">{{ f.title }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ f.desc }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Integraciones / Entregables -->
            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border p-6">
                    <div class="text-sm font-semibold">Integraciones típicas</div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Conecta módulos con tu operación actual y mantén trazabilidad de punta a punta.
                    </p>

                    <ul class="mt-4 grid gap-2 text-sm text-muted-foreground sm:grid-cols-2">
                        <li v-for="(it, i) in props.integrations" :key="i" class="flex items-start gap-2">
                            <span class="mt-1 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                            <span>{{ it }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-2xl border p-6">
                    <div class="text-sm font-semibold">Entregables</div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Entrega por fases según el área activada.
                    </p>

                    <ul class="mt-4 grid gap-2 text-sm text-muted-foreground">
                        <li v-for="(it, i) in props.deliverables" :key="i" class="flex items-start gap-2">
                            <span class="mt-1 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                            <span>{{ it }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Catálogo por áreas -->
            <div class="mt-12">
                <div>
                    <div class="text-sm font-semibold">Catálogo por categorías</div>
                    <div class="mt-1 text-sm text-muted-foreground">
                        Selecciona lo que necesitas hoy y agrega más módulos cuando te convenga.
                    </div>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div v-for="(g, gi) in props.modulesByArea" :key="gi" class="rounded-2xl border p-6">
                        <div class="text-sm font-semibold">{{ g.area }}</div>

                        <div class="mt-4 grid gap-3">
                            <div v-for="(m, mi) in g.items" :key="mi" class="rounded-xl border bg-background p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold">{{ m.title }}</div>
                                        <div class="mt-1 text-sm text-muted-foreground">{{ m.desc }}</div>
                                    </div>

                                    <div v-if="m.tags?.length" class="shrink-0 flex flex-wrap gap-1">
                                        <span v-for="(t, ti) in m.tags" :key="ti" class="rounded-full border px-2 py-0.5 text-[11px] text-muted-foreground">
                                            {{ t }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-10 flex justify-center">
                    <Button variant="outline" @click="props.openRequestForm">
                        Solicitar y activar módulos
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
