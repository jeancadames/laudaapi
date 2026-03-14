<script setup lang="ts">
import type { Component } from 'vue'
import type { NavItem } from '@/types'

import {
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar'

import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { ChevronDown } from 'lucide-vue-next'
import NavMain from '@/components/NavMain.vue'

defineProps<{
    title: string
    slug: string
    icon?: Component
    items: NavItem[]
    open: boolean
    onToggle: (slug: string) => void
}>()

// script setup (añade esto)
const shortTitles: Record<string, string> = {
    'api-facturacion-electronica': 'API Facturación',
    // agrega más mapeos según necesites
}

</script>

<template>
    <Collapsible :open="open">
        <!-- ✅ Trigger: 1:1 con SidebarMenuButton (hover/active/collapsed perfecto) -->
        <!-- template -->
        <SidebarMenuItem>
            <CollapsibleTrigger as-child>
                <SidebarMenuButton class="w-full relative" :tooltip="title" @click.prevent="onToggle(slug)">
                    <div class="flex items-start min-w-0 w-full gap-2 pr-8">
                        <component v-if="icon" :is="icon" class="mt-0.5 h-4 w-4 shrink-0 opacity-90" />
                        <span class="min-w-0 flex-1 line-clamp-2 whitespace-normal leading-4 text-sm" :title="title">
                            {{ shortTitles[ slug ] ?? title }}
                        </span>
                    </div>

                    <ChevronDown class="absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
                </SidebarMenuButton>
            </CollapsibleTrigger>
        </SidebarMenuItem>

        <!-- ✅ Cuando sidebar colapsa => NO mostrar items -->
        <CollapsibleContent class="mt-1 group-data-[collapsible=icon]:hidden">
            <!-- ✅ barrita izquierda tipo shadcn -->
            <div class="pl-4">
                <div class="border-l border-slate-200/70 pl-2 dark:border-slate-800/70">
                    <NavMain :items="items" :label="null" size="xs" />
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
