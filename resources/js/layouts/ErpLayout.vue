<script setup lang="ts">
import ErpSidebarLayout from '@/layouts/app/ErpSidebarLayout.vue'
import type { BreadcrumbItem } from '@/types'

type Props = {
    breadcrumbs?: BreadcrumbItem[]
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
})
</script>

<template>
    <ErpSidebarLayout :breadcrumbs="breadcrumbs">
        <slot />
    </ErpSidebarLayout>
</template>
