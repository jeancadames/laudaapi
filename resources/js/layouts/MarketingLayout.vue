<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { dashboard, login } from '@/routes'
import AppLogo from '@/components/AppLogo.vue'
</script>

<template>
    <!-- HEADER -->
    <header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/70 bg-white/80 backdrop-blur
               dark:border-slate-800/80 dark:bg-slate-950/80">


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


    <!-- SPACER PARA HEADER FIJO -->
    <div class="h-8"></div>

    <!-- MAIN CONTENT -->
    <main class="bg-white text-slate-950 dark:bg-slate-950 dark:text-slate-50">
        <slot />
    </main>
</template>