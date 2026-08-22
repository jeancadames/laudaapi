<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

function itemClasses(active: boolean): string {
    return [
        'group flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[11.5px] font-semibold transition-all duration-150',
        active
            ? 'bg-[#F53003]/10 text-[#F53003] dark:bg-[#F53003]/15 dark:text-red-300'
            : 'text-white/60 hover:bg-white/6 hover:text-white/90',
    ].join(' ');
}

function iconClasses(active: boolean): string {
    return [
        'h-3.5 w-3.5 shrink-0 transition-colors duration-150',
        active
            ? 'text-[#F53003] dark:text-red-300'
            : 'text-white/55 group-hover:text-white/90',
    ].join(' ');
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <Link
                    :href="toUrl(item.href)"
                    :class="itemClasses(isCurrentUrl(toUrl(item.href)))"
                >
                    <component
                        :is="item.icon"
                        :class="iconClasses(isCurrentUrl(toUrl(item.href)))"
                    />

                    <span class="truncate group-data-[collapsible=icon]:hidden">
                        {{ item.title }}
                    </span>
                </Link>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
