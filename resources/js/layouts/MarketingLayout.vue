<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { dashboard, login } from '@/routes'
import type { AppPageProps } from '@/types'
import AppLogo from '@/components/AppLogo.vue'

// IMPORTANTE: Toaster global
import Toaster from '@/components/ui/toast/Toaster.vue'

const page = usePage<AppPageProps>()

const success = computed(() => page.props.flash?.success)
const error = computed(() => page.props.flash?.error)
</script>

<template>
    <!-- HEADER -->
    <header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/70 bg-white/80 backdrop-blur
               dark:border-slate-800/80 dark:bg-slate-950/80">

        <!-- Flash: success -->
        <div v-if="success" class="fixed left-1/2 top-4 z-50 w-full max-w-xl -translate-x-1/2 px-4">
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-md
                       dark:border-green-900 dark:bg-green-900/60 dark:text-green-100">
                {{ success }}
            </div>
        </div>

        <!-- Flash: error -->
        <div v-if="error" class="fixed left-1/2 top-4 z-50 w-full max-w-xl -translate-x-1/2 px-4">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-md
                       dark:border-red-900 dark:bg-red-900/60 dark:text-red-100">
                {{ error }}
            </div>
        </div>

        <!-- NAVBAR -->
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2">
                <AppLogo />
            </div>

            <nav class="flex items-center gap-3">
                <Link v-if="$page.props.auth.user" :href="dashboard()" class="inline-block rounded-lg border border-slate-300/60 px-5 py-1.5 text-sm leading-normal text-slate-800
                             hover:border-slate-400 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500">
                    Panel Control
                </Link>

                <template v-else>
                    <Link :href="login()" class="inline-flex items-center rounded-lg px-4 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100
                                 dark:text-slate-200 dark:hover:bg-slate-900">
                        Iniciar sesión
                    </Link>
                </template>
            </nav>
        </div>
    </header>

    <!-- TOASTER GLOBAL (siempre visible) -->
    <Toaster />

    <!-- SPACER PARA HEADER FIJO -->
    <div class="h-8"></div>

    <!-- MAIN CONTENT -->
    <main class="bg-white text-slate-950 dark:bg-slate-950 dark:text-slate-50">
        <slot />
    </main>
</template>