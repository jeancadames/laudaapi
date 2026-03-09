<script setup lang="ts">
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent
} from '@/components/ui/card'

import { computed } from "vue"
import type { PropType } from "vue"

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [ Number, String ], required: true },
    description: { type: String, default: "" },
    icon: { type: null, default: null },
    chartData: {
        type: Array as PropType<number[]>,
        default: () => []
    }
})

// Cálculo del porcentaje
const percentageChange = computed(() => {
    const data = props.chartData
    if (!data || data.length < 2) return 0

    const prev = data[ data.length - 2 ]
    const curr = data[ data.length - 1 ]

    if (prev === 0) return 0

    return ((curr - prev) / prev) * 100
})

const comparisonColor = computed(() => {
    if (percentageChange.value > 0) return "text-green-600"
    if (percentageChange.value < 0) return "text-red-600"
    return "text-muted-foreground"
})
</script>

<template>
    <Card class="rounded-xl wrap-break-word whitespace-normal border border-border/40 bg-card/60 backdrop-blur-sm
               transition-all duration-300 hover:shadow-md hover:-translate-y-0.5">
        <CardHeader class="flex flex-row items-center justify-between pb-1">
            <div>
                <CardTitle class="text-sm font-medium text-muted-foreground tracking-wide">
                    {{ title }}
                </CardTitle>

                <CardDescription v-if="description" class="text-xs mt-0.5">
                    {{ description }}
                </CardDescription>
            </div>

            <div v-if="icon" class="h-10 w-10 flex items-center justify-center rounded-lg
                       bg-muted/40 border border-border/30 shadow-sm">
                <component :is="icon" class="h-5 w-5 text-foreground/70" />
            </div>
        </CardHeader>

        <CardContent class="pt-0">
            <div class="text-4xl font-semibold tracking-tight leading-none text-foreground">
                {{ value }}
            </div>

            <div class="text-xs mt-2 font-medium flex items-center gap-1" :class="comparisonColor">
                <span>{{ percentageChange.toFixed(1) }}%</span>
                <span class="text-muted-foreground/70">vs periodo anterior</span>
            </div>
        </CardContent>
    </Card>
</template>
