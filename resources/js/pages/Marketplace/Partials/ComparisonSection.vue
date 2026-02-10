<script setup lang="ts">
import { Button } from '@/components/ui/button'

type Col = {
    key: string
    title: string
    badge?: string | null
    highlight?: boolean
    ctaLabel?: string
    ctaTarget?: string
}

type Row = {
    label: string
    values: Record<string, string>
}

const props = defineProps<{
    title?: string
    subtitle?: string
    columns: Col[]
    rows: Row[]
    scrollToSection: (id: string) => void
    openRequestForm: () => void
}>()

function go(col: Col) {
    if (col.ctaTarget) props.scrollToSection(col.ctaTarget)
    else props.openRequestForm()
}
</script>

<template>
    <section id="comparison" class="scroll-mt-24">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="mb-8">
                <h2 class="text-3xl font-semibold tracking-tight">
                    {{ props.title ?? '¿Cuál opción te conviene?' }}
                </h2>
                <p class="mt-2 text-muted-foreground">
                    {{ props.subtitle ?? 'Comparación rápida para elegir el camino correcto según tu operación.' }}
                </p>
            </div>

            <div class="overflow-hidden rounded-2xl border">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-245 text-sm">
                        <thead class="bg-muted/30">
                            <tr class="border-b">
                                <th class="px-4 py-4 text-left font-semibold w-60">
                                    Criterio
                                </th>

                                <th v-for="c in props.columns" :key="c.key" class="px-4 py-4 text-left">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold">{{ c.title }}</span>
                                        <span v-if="c.badge" class="rounded-full border px-2 py-0.5 text-[11px] text-muted-foreground">
                                            {{ c.badge }}
                                        </span>
                                        <span v-if="c.highlight" class="rounded-full bg-blue-500 px-2 py-0.5 text-[11px] text-white dark:bg-blue-600">
                                            Recomendado
                                        </span>
                                    </div>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(r, i) in props.rows" :key="i" class="border-b last:border-b-0">
                                <td class="px-4 py-4 font-medium">
                                    {{ r.label }}
                                </td>

                                <td v-for="c in props.columns" :key="c.key" class="px-4 py-4 text-muted-foreground">
                                    {{ r.values[ c.key ] ?? '—' }}
                                </td>
                            </tr>

                            <tr class="bg-muted/20">
                                <td class="px-4 py-5 font-semibold">
                                    Ir al detalle
                                </td>

                                <td v-for="c in props.columns" :key="c.key" class="px-4 py-5">
                                    <Button size="sm" variant="outline" class="w-full" @click="go(c)">
                                        {{ c.ctaLabel ?? 'Ver' }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 text-sm text-muted-foreground">
                ¿No estás seguro? Usa “Solicitar activación” y te ayudamos a elegir la combinación correcta.
            </div>
        </div>
    </section>
</template>
