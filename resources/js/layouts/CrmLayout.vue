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

const items = [
    { title: 'Resumen', href: '/erp/crm' },
    { title: 'Clientes', href: '/erp/crm/customers' },
    { title: 'Contactos', href: '/erp/crm/contacts' },
    { title: 'Leads', href: '/erp/crm/leads' },
    { title: 'Oportunidades', href: '/erp/crm/opportunities' },
    { title: 'Actividades', href: '/erp/crm/activities' },
]

const currentUrl = computed(() => page.url)
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
                    <Link v-for="item in items" :key="item.href" :href="item.href" class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium transition" :class="currentUrl === item.href
                        ? 'bg-primary text-primary-foreground border-primary'
                        : 'hover:bg-muted'
                        ">
                        {{ item.title }}
                    </Link>
                </div>
            </header>

            <slot />
        </div>
    </ErpLayout>
</template>