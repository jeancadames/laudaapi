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
import Button from './ui/button/Button.vue'

const props = defineProps<{
    title: string
    slug: string
    icon?: Component
    items: NavItem[]
    open: boolean
    onToggle: (slug: string) => void
}>()
</script>

<template>
    <Collapsible :open="open">
        <!-- ✅ Trigger: 1:1 con SidebarMenuButton (hover/active/collapsed perfecto) -->
        <SidebarMenuItem>
            <CollapsibleTrigger as-child>
                <SidebarMenuButton class="w-full" :tooltip="title" @click.prevent="onToggle(slug)">

                    <span class="flex min-w-0 items-start gap-2">
                        <component v-if="icon" :is="icon" class="mt-0.5 h-4 w-4 shrink-0 opacity-90" />

                        <!-- ✅ 2 líneas + ellipsis real -->
                        <span class="min-w-0 flex-1 line-clamp-2 whitespace-normal leading-4 group-data-[collapsible=icon]:hidden" :title="title">
                            {{ title }}
                        </span>
                    </span>

                    <ChevronDown class="mt-0.5 h-4 w-4 shrink-0 transition-transform group-data-[collapsible=icon]:hidden" :class="open ? 'rotate-180' : ''" />

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
