<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';

const page = usePage();

const user = computed(() => (page.props.auth as any)?.user);
const isLoggedIn = computed(() => !!user.value);
const isAdmin = computed(() => !!user.value?.is_admin);

const dashboardUrl = computed(() => (isAdmin.value ? '/admin' : '/dashboard'));

const scrolled = ref(false);
const mobileMenuOpen = ref(false);

const apps = [
    {
        label: 'e-CF',
        href: 'https://ecf.laudaapi.com',
        dot: 'bg-blue-400',
        desktopClass:
            'hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/30 dark:hover:text-blue-300',
        mobileClass:
            'hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/30 dark:hover:text-blue-300',
    },
    {
        label: 'Cumplimiento',
        href: 'https://cumplimiento.laudaapi.com',
        dot: 'bg-amber-400',
        desktopClass:
            'hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950/30 dark:hover:text-amber-300',
        mobileClass:
            'hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950/30 dark:hover:text-amber-300',
    },
    {
        label: 'POS',
        href: 'https://pos.laudaapi.com',
        dot: 'bg-green-400',
        desktopClass:
            'hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-950/30 dark:hover:text-green-300',
        mobileClass:
            'hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-950/30 dark:hover:text-green-300',
    },
    {
        label: 'Social',
        href: 'https://social.laudaapi.com',
        dot: 'bg-pink-400',
        desktopClass:
            'hover:bg-pink-50 hover:text-pink-700 dark:hover:bg-pink-950/30 dark:hover:text-pink-300',
        mobileClass:
            'hover:bg-pink-50 hover:text-pink-700 dark:hover:bg-pink-950/30 dark:hover:text-pink-300',
    },
    {
        label: 'CRM',
        href: 'https://crm.laudaapi.com',
        dot: 'bg-purple-400',
        desktopClass:
            'hover:bg-purple-50 hover:text-purple-700 dark:hover:bg-purple-950/30 dark:hover:text-purple-300',
        mobileClass:
            'hover:bg-purple-50 hover:text-purple-700 dark:hover:bg-purple-950/30 dark:hover:text-purple-300',
    },
    {
        label: 'Status',
        href: 'https://status.laudaapi.com',
        dot: 'bg-emerald-400',
        desktopClass:
            'hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950/30 dark:hover:text-emerald-300',
        mobileClass:
            'hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950/30 dark:hover:text-emerald-300',
    },
];

function handleScroll() {
    scrolled.value = window.scrollY > 20;
}

function toggleMobileMenu() {
    mobileMenuOpen.value = !mobileMenuOpen.value;
}

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

function scrollToSection(id: string) {
    closeMobileMenu();

    if (window.location.pathname !== '/') {
        window.location.href = `/#${id}`;
        return;
    }

    document.getElementById(id)?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <header
        :class="[
            'sticky top-0 z-50 -mx-4 -mt-4 mb-6 w-[calc(100%+2rem)] transition-all duration-300',
            scrolled
                ? 'border-b border-slate-200 bg-white/95 py-3 shadow-sm backdrop-blur-xl dark:border-slate-800 dark:bg-[#0a0a0a]/95'
                : 'border-b border-transparent bg-transparent py-4',
        ]"
    >
        <div class="mx-auto w-full max-w-6xl px-4">
            <nav class="flex items-center justify-between gap-4">
                <!-- Brand -->
                <Link
                    href="/"
                    class="group flex items-center gap-3 rounded-2xl px-2 py-1 transition-all duration-200 hover:scale-[1.02]"
                    @click="closeMobileMenu"
                >
                    <div
                        class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-linear-to-br from-[#F53003] to-red-700 shadow-lg shadow-red-500/30 transition-shadow duration-200 group-hover:shadow-xl group-hover:shadow-red-500/40"
                    >
                        <BrandLogo class="h-6 w-6 text-white" />
                        <div
                            class="absolute inset-0 bg-linear-to-br from-white/20 to-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                        />
                    </div>

                    <div class="flex flex-col leading-none">
                        <span
                            class="text-lg font-black tracking-[0.2em] text-[#111] uppercase dark:text-white"
                        >
                            LAUDA
                        </span>
                        <span
                            class="text-[8px] font-bold tracking-[0.25em] text-red-500 uppercase"
                        >
                            API Digital
                        </span>
                    </div>
                </Link>

                <!-- Nav central desktop -->
                <div
                    class="hidden items-center gap-1 rounded-2xl border border-slate-200 bg-white/60 p-1 shadow-sm backdrop-blur-sm xl:flex dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <button
                        type="button"
                        @click="scrollToSection('soluciones')"
                        class="flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-black text-slate-600 transition-all duration-200 hover:bg-slate-100 hover:text-black dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        Soluciones
                    </button>

                    <button
                        type="button"
                        @click="scrollToSection('flujo')"
                        class="flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-black text-slate-600 transition-all duration-200 hover:bg-slate-100 hover:text-black dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        Ecosistema
                    </button>

                    <a
                        v-for="app in apps"
                        :key="app.href"
                        :href="app.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        :class="[
                            'flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-black text-slate-600 transition-all duration-200 dark:text-slate-400',
                            app.desktopClass,
                        ]"
                    >
                        {{ app.label }}
                    </a>
                </div>

                <!-- CTA derecha -->
                <div class="flex items-center gap-2">
                    <!-- Botón mobile / tablet -->
                    <button
                        type="button"
                        @click="toggleMobileMenu"
                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 xl:hidden dark:border-slate-800 dark:bg-transparent dark:text-slate-300 dark:hover:bg-white/5"
                        aria-label="Menú"
                    >
                        <svg
                            v-if="!mobileMenuOpen"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>

                        <svg
                            v-else
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>

                    <!-- Usuario logueado -->
                    <template v-if="isLoggedIn">
                        <Link
                            :href="dashboardUrl"
                            class="rounded-xl bg-slate-900 px-5 py-2.5 text-[11px] font-black text-white transition hover:scale-105 active:scale-95 dark:bg-white dark:text-black"
                        >
                            {{ isAdmin ? 'Panel Admin' : 'Ir al Panel' }}
                        </Link>
                    </template>

                    <!-- Usuario no logueado -->
                    <template v-else>
                        <Link
                            href="/login"
                            class="group hidden items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-[11px] font-black text-slate-700 shadow-sm transition-colors hover:bg-slate-50 hover:text-black sm:inline-flex dark:border-slate-800 dark:bg-transparent dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white"
                        >
                            <span
                                class="relative flex h-4 w-4 items-center justify-center overflow-hidden"
                            >
                                <svg
                                    class="absolute h-4 w-4 scale-100 opacity-100 transition-all duration-200 group-hover:scale-75 group-hover:opacity-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M16.5 10.5V7.875a4.5 4.5 0 10-9 0V10.5m-1.5 0h12a1.5 1.5 0 011.5 1.5v7.5A1.5 1.5 0 0118 21h-12A1.5 1.5 0 014.5 19.5V12A1.5 1.5 0 016 10.5z"
                                    />
                                </svg>

                                <svg
                                    class="absolute h-4 w-4 scale-75 opacity-0 transition-all duration-200 group-hover:scale-100 group-hover:opacity-100"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M13.5 10.5V7.875a4.5 4.5 0 00-8.902-.938M6 10.5h12a1.5 1.5 0 011.5 1.5v7.5A1.5 1.5 0 016 10.5z"
                                    />
                                </svg>
                            </span>

                            <span>Iniciar sesión</span>
                        </Link>
                    </template>
                </div>
            </nav>

            <!-- Dropdown mobile / tablet -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="mobileMenuOpen"
                    class="mt-4 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg xl:hidden dark:border-slate-800 dark:bg-[#0c0c0c]"
                >
                    <button
                        type="button"
                        @click="scrollToSection('soluciones')"
                        class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-black text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-900"
                    >
                        <span class="h-2 w-2 rounded-full bg-red-500" />
                        Soluciones
                    </button>

                    <button
                        type="button"
                        @click="scrollToSection('flujo')"
                        class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-black text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-900"
                    >
                        <span class="h-2 w-2 rounded-full bg-slate-400" />
                        Ecosistema
                    </button>

                    <div
                        class="my-1 border-t border-slate-100 dark:border-slate-800"
                    />

                    <a
                        v-for="app in apps"
                        :key="app.href"
                        :href="app.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        @click="closeMobileMenu"
                        :class="[
                            'flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-black text-slate-700 dark:text-slate-300',
                            app.mobileClass,
                        ]"
                    >
                        <span :class="['h-2 w-2 rounded-full', app.dot]" />
                        {{ app.label }}
                    </a>

                    <div
                        class="my-1 border-t border-slate-100 dark:border-slate-800"
                    />

                    <Link
                        v-if="isLoggedIn"
                        :href="dashboardUrl"
                        @click="closeMobileMenu"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-black text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30"
                    >
                        <span class="h-2 w-2 rounded-full bg-red-500" />
                        {{ isAdmin ? 'Panel Admin' : 'Ir al Panel' }}
                    </Link>

                    <Link
                        v-else
                        href="/login"
                        @click="closeMobileMenu"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-black text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30"
                    >
                        <span class="h-2 w-2 rounded-full bg-red-500" />
                        Iniciar sesión
                    </Link>

                    <div
                        class="my-1 border-t border-slate-100 dark:border-slate-800"
                    />

                    <Link
                        href="/privacy"
                        @click="closeMobileMenu"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-xs font-bold text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-900"
                    >
                        Privacidad
                    </Link>

                    <Link
                        href="/terms"
                        @click="closeMobileMenu"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-xs font-bold text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-900"
                    >
                        Términos
                    </Link>
                </div>
            </transition>
        </div>
    </header>
</template>