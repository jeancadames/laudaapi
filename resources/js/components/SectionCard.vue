<script setup lang="ts">
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent
} from '@/components/ui/card'

const props = defineProps<{
    title: string
    description?: string
}>()
</script>

<template>
    <Card class="border border-border/50 shadow-sm hover:shadow-md transition-shadow duration-300">
        <CardHeader>
            <CardTitle class="text-lg font-semibold">{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>

        <CardContent>
            <slot />
        </CardContent>
    </Card>
</template>
