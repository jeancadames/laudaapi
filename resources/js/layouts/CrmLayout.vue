<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import ErpLayout from '@/layouts/ErpLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'

const props = defineProps<{
    title: string
    description?: string
}>()

const page = usePage()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LaudaERP', href: '/erp' },
    { title: 'CRM', href: '/erp/crm' },
]

const baseItems = [
    { title: 'Resumen', href: '/erp/crm' },
    { title: 'Clientes', href: '/erp/crm/customers' },
    { title: 'Contactos', href: '/erp/crm/contacts' },
    { title: 'Leads', href: '/erp/crm/leads' },
    { title: 'Oportunidades', href: '/erp/crm/opportunities' },
    { title: 'Pipeline', href: '/erp/crm/pipeline' },
    { title: 'Actividades', href: '/erp/crm/activities' },
]

const currentUrl = computed(() => page.url || '')

const currentPath = computed(() => {
    const url = currentUrl.value
    return url.split('?')[ 0 ]
})

const assignedUserId = computed(() => {
    if (typeof window === 'undefined') return null

    const url = new URL(window.location.href)
    return url.searchParams.get('assigned_user_id')
})

const items = computed(() => {
    return baseItems.map((item) => {
        if (!assignedUserId.value) return item

        const separator = item.href.includes('?') ? '&' : '?'

        return {
            ...item,
            href: `${item.href}${separator}assigned_user_id=${assignedUserId.value}`,
        }
    })
})

function isActive(href: string) {
    return currentPath.value === href
}
</script>

<template>
    <ErpLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <header class="space-y-3">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <Badge variant="secondary">CRM</Badge>
                </div>

                <p v-if="description" class="max-w-3xl text-sm text-muted-foreground">
                    {{ description }}
                </p>

                <div class="flex flex-wrap gap-2">
                    <Link v-for="item in items" :key="item.href" :href="item.href" class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium transition" :class="isActive(item.href.split('?')[ 0 ])
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-muted'">
                        {{ item.title }}
                    </Link>
                </div>
            </header>

            <slot />
        </div>
    </ErpLayout>
</template>