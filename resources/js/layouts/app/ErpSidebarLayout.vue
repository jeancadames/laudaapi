<script setup lang="ts">
import AppContent from '@/components/AppContent.vue'
import AppShell from '@/components/AppShell.vue'
import ErpSidebar from '@/components/ErpSidebar.vue'
import AppSidebarHeader from '@/components/AppSidebarHeader.vue'
import type { BreadcrumbItem } from '@/types'

import { SidebarProvider } from '@/components/ui/sidebar' // ✅ ADD

type Props = {
    breadcrumbs?: BreadcrumbItem[]
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
})
</script>

<template>
    <!-- ✅ Provider requerido para tooltips y estado del sidebar -->
    <SidebarProvider>
        <AppShell variant="sidebar">
            <ErpSidebar />

            <AppContent variant="sidebar" class="overflow-x-hidden">
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                <slot />
            </AppContent>
        </AppShell>
    </SidebarProvider>
</template>
