<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { type NavItem } from '@/types';

const props = withDefaults(defineProps<{
    items: NavItem[]
    label?: string | null
    size?: 'sm' | 'xs'
}>(), {
    label: 'Platform',
    size: 'sm',
})

const { isCurrentUrl } = useCurrentUrl();

const textClass = computed(() =>
    props.size === 'xs'
        ? 'text-xs leading-4'
        : 'text-sm'
)

</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="label">{{ label }}</SidebarGroupLabel>

        <!-- ✅ Si no hay label (null), ponemos un separador -->
        <div v-else class="my-2 h-px bg-slate-200/70 dark:bg-slate-800/70" />
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="isCurrentUrl(item.href)" :tooltip="item.title" class="h-auto! items-start! py-2!">
                    <Link :href="item.href" class="flex w-full min-w-0 items-start gap-2">
                        <component v-if="props.size !== 'xs'" :is="item.icon" class="mt-0.5 h-4 w-4 shrink-0" />
                        <div class="min-w-0 flex-1 overflow-hidden whitespace-normal wrap-break-word line-clamp-2 leading-4" :class="textClass" :title="item.title">
                            {{ item.title }}
                        </div>
                        <component v-if="props.size === 'xs'" :is="item.icon" class="mt-0.5 h-4 w-4 shrink-0 opacity-80" />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
