<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    { breadcrumbs: () => [] },
);
</script>

<template>
    <header
        class="flex h-11 shrink-0 items-center gap-2 border-b border-black/5 bg-white/95 px-4 backdrop-blur-sm dark:border-white/5 dark:bg-[#0a0a0a]/95"
    >
        <SidebarTrigger
            class="-ml-1 text-slate-400 hover:text-slate-700 dark:hover:text-white"
        />

        <div class="flex items-center gap-1.5 md:hidden">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 30 30"
                class="h-5 w-5 text-[#F53003]"
                fill="currentColor"
                aria-hidden="true"
            >
                <defs>
                    <clipPath id="clip-laudaapi-topbar">
                        <path
                            d="M 1.964844 5 L 28.027344 5 L 28.027344 29.03125 L 1.964844 29.03125 Z"
                        />
                    </clipPath>
                </defs>
                <g clip-path="url(#clip-laudaapi-topbar)">
                    <path
                        d="M 10.90625 7.480469 C 5.839844 8.390625 1.964844 12.847656 1.964844 18.167969 C 1.964844 24.140625 6.851562 29.027344 12.824219 29.027344 L 28.027344 29.027344 L 23.683594 24.683594 L 12.824219 24.683594 C 9.242188 24.683594 6.308594 21.75 6.308594 18.167969 C 6.308594 14.585938 9.242188 11.652344 12.824219 11.652344 L 14.996094 11.652344 L 14.996094 5.019531 L 10.652344 7.527344 Z"
                    />
                </g>
                <path
                    d="M 19.339844 7.308594 C 19.339844 10.203125 19.339844 13.101562 19.339844 15.996094 L 12.824219 15.996094 C 11.628906 15.996094 10.652344 16.972656 10.652344 18.167969 C 10.652344 19.363281 11.628906 20.339844 12.824219 20.339844 L 23.683594 20.339844 C 23.683594 15.996094 23.683594 11.652344 23.683594 7.308594 L 23.683594 0.00390625 L 19.339844 2.511719 Z"
                />
            </svg>
        </div>

        <template v-if="breadcrumbs && breadcrumbs.length > 0">
            <span class="text-slate-200 dark:text-slate-700">/</span>
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </template>
    </header>
</template>
