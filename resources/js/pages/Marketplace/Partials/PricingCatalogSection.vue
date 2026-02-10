<script setup lang="ts">
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card'

type Highlight = {
    id: number
    title: string
    short_description?: string | null
}

type Category = {
    id: number
    title: string
    slug: string
    badge?: string | null
    icon?: string | null
    short_description?: string | null
    description?: string | null
    currency?: string | null
    monthly_price: string | number | null
    yearly_price: string | number | null
    highlights?: Highlight[] | null
}

const props = defineProps<{
    catalog: Category[]
    scrollToSection: (id: string) => void
    openRequestForm: () => void
}>()

const catalogSafe = computed(() => (Array.isArray(props.catalog) ? props.catalog : []))

function money(input: any): string | null {
    // ✅ NO convertir null/'' a 0
    if (input === null || input === undefined) return null
    const s = String(input).trim()
    if (s === '') return null

    const v = Number(s)
    if (!Number.isFinite(v)) return null
    if (v <= 0) return null

    return new Intl.NumberFormat('es-DO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(v)
}

function priceFrom(c: Category) {
    const currency = (c.currency || 'USD').toUpperCase()
    const m = money(c.monthly_price)
    const y = money(c.yearly_price)

    if (m) return { label: `Desde ${m} ${currency}/mes`, sub: y ? `${y} ${currency}/año` : null }
    if (y) return { label: `Desde ${y} ${currency}/año`, sub: null }
    return { label: 'Precio: consultar', sub: null }
}

// ✅ evitar recalcular priceFrom(c) 2 veces en el template
const priceMap = computed<Record<number, { label: string; sub: string | null }>>(() => {
    const out: Record<number, { label: string; sub: string | null }> = {}
    for (const c of catalogSafe.value) out[ c.id ] = priceFrom(c)
    return out
})

function topHighlights(c: Category) {
    const items = Array.isArray(c.highlights) ? c.highlights : []
    return items.slice(0, 5)
}

function goDetail(slug: string) {
    // ✅ anchors correctos (según tu Index.vue actual)
    if (slug === 'marketplace') return props.scrollToSection('detail-api-marketplace')
    if (slug === 'api-facturacion-electronica') return props.scrollToSection('detail-facturacion-electronica')
    if (slug === 'laudaone') return props.scrollToSection('detail-laudaone')

    // fallback: baja al bloque de comparación o abre solicitud
    props.scrollToSection('comparison')
}
</script>

<template>
    <section id="pricing" class="scroll-mt-24">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="mb-8">
                <h2 class="text-3xl font-semibold tracking-tight">Servicios y precios</h2>
                <p class="mt-2 text-muted-foreground">
                    Activa por categoría y escala por fases según tu necesidad.
                </p>
            </div>

            <div v-if="catalogSafe.length === 0" class="rounded-2xl border p-8 text-center">
                <div class="text-lg font-semibold">Catálogo no disponible</div>
                <div class="mt-1 text-sm text-muted-foreground">
                    No hay servicios activos para mostrar ahora mismo.
                </div>
            </div>

            <!-- ✅ Grid responsive -->
            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- ✅ Card: flex-col para que footer quede abajo SIEMPRE -->
                <Card v-for="c in catalogSafe" :key="c.id" class="rounded-2xl border border-border/50 overflow-hidden flex flex-col min-w-0">
                    <CardHeader class="space-y-2 min-w-0">
                        <div class="flex items-start justify-between gap-3 min-w-0">
                            <div class="min-w-0">
                                <CardTitle class="text-base font-semibold truncate">
                                    {{ c.title }}
                                </CardTitle>

                                <CardDescription class="text-sm text-muted-foreground line-clamp-2">
                                    {{ c.short_description || c.description || 'Servicios disponibles en esta categoría.' }}
                                </CardDescription>
                            </div>

                            <div v-if="c.badge" class="shrink-0">
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] text-muted-foreground">
                                    {{ c.badge }}
                                </span>
                            </div>
                        </div>

                        <div class="rounded-xl border bg-muted/10 p-3">
                            <div class="text-sm font-semibold">
                                {{ priceMap[ c.id ]?.label }}
                            </div>
                            <div v-if="priceMap[ c.id ]?.sub" class="text-xs text-muted-foreground mt-0.5">
                                {{ priceMap[ c.id ]?.sub }}
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-3 min-w-0">
                        <div class="text-xs font-semibold text-muted-foreground">Incluye</div>

                        <ul class="space-y-2">
                            <li v-for="h in topHighlights(c)" :key="h.id" class="flex items-start gap-2 text-sm text-muted-foreground">
                                <span class="mt-2 h-1.5 w-1.5 rounded-full bg-foreground/60"></span>
                                <span class="min-w-0">
                                    <span class="text-foreground/90">{{ h.title }}</span>
                                    <span v-if="h.short_description" class="text-xs text-muted-foreground/80">
                                        — {{ h.short_description }}
                                    </span>
                                </span>
                            </li>

                            <li v-if="topHighlights(c).length === 0" class="text-sm text-muted-foreground">
                                Servicios por definir en esta categoría.
                            </li>
                        </ul>
                    </CardContent>

                    <!-- ✅ mt-auto: empuja botones al final en todas las cards -->
                    <!-- ✅ grid-cols-2: evita botón cortado -->
                    <CardFooter class="mt-auto">
                        <div class="grid w-full grid-cols-2 gap-3 min-w-0">
                            <Button class="w-full" @click="props.openRequestForm">
                                Solicitar activación
                            </Button>

                            <Button class="w-full" variant="outline" @click="goDetail(c.slug)">
                                Ver detalle
                            </Button>
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </section>
</template>
