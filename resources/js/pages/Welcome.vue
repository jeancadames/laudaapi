<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Boxes,
    Building2,
    CheckCircle2,
    FileText,
    Instagram,
    Mail,
    MessageCircle,
    ShieldCheck,
    Store,
    User,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Props {
    canRegister?: boolean;
}

withDefaults(defineProps<Props>(), {
    canRegister: true,
});

const APP_URL = 'https://app.laudaapi.com';

/**
 * Card interactivo del hero.
 *
 * Mantiene el patrón visual de Cumplimiento/POS:
 * una sola tarjeta cambia de contenido automáticamente.
 *
 * Aquí mostramos las principales formas de entrar al ecosistema.
 */
const ecosystemOptions = [
    {
        id: 'app-hub',
        codigo: 'HUB',
        nombre: 'Mi cuenta LAUDAAPI',
        entidad: 'app.laudaapi.com',
        valor: 'Cuenta central',
        estado: 'Central',
        badgeClass:
            'bg-red-50 text-red-900 border-red-200 dark:bg-red-950/30 dark:text-red-300 dark:border-red-900/50',
        iconColor: 'text-red-500',
        icon: User,
        href: 'https://app.laudaapi.com',
    },
    {
        id: 'social',
        codigo: 'SOC',
        nombre: 'Social',
        entidad: 'Marketing',
        valor: 'Canales + leads',
        estado: 'Solución',
        badgeClass:
            'bg-pink-50 text-pink-900 border-pink-200 dark:bg-pink-950/30 dark:text-pink-300 dark:border-pink-900/50',
        iconColor: 'text-pink-500',
        icon: MessageCircle,
        href: 'https://social.laudaapi.com',
    },
    {
        id: 'crm',
        codigo: 'CRM',
        nombre: 'CRM',
        entidad: 'Ventas',
        valor: 'Pipeline',
        estado: 'Solución',
        badgeClass:
            'bg-blue-50 text-blue-900 border-blue-200 dark:bg-blue-950/30 dark:text-blue-300 dark:border-blue-900/50',
        iconColor: 'text-blue-500',
        icon: Users,
        href: 'https://crm.laudaapi.com',
    },
    {
        id: 'pos',
        codigo: 'POS',
        nombre: 'POS',
        entidad: 'Operación',
        valor: 'Ventas + inventario',
        estado: 'Solución',
        badgeClass:
            'bg-emerald-50 text-emerald-900 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-300 dark:border-emerald-900/50',
        iconColor: 'text-emerald-500',
        icon: Store,
        href: 'https://pos.laudaapi.com',
    },
    {
        id: 'ecf',
        codigo: 'e-CF',
        nombre: 'Facturación electrónica',
        entidad: 'Fiscal',
        valor: 'DGII',
        estado: 'Solución',
        badgeClass:
            'bg-amber-50 text-amber-900 border-amber-200 dark:bg-amber-950/30 dark:text-amber-300 dark:border-amber-900/50',
        iconColor: 'text-amber-500',
        icon: FileText,
        href: 'https://ecf.laudaapi.com',
    },
    {
        id: 'cumplimiento',
        codigo: 'CPL',
        nombre: 'Cumplimiento',
        entidad: 'Fiscal',
        valor: 'Obligaciones',
        estado: 'Solución',
        badgeClass:
            'bg-teal-50 text-teal-900 border-teal-200 dark:bg-teal-950/30 dark:text-teal-300 dark:border-teal-900/50',
        iconColor: 'text-teal-500',
        icon: ShieldCheck,
        href: 'https://cumplimiento.laudaapi.com',
    },
    {
        id: 'bys',
        codigo: 'BYS',
        nombre: 'BYS',
        entidad: 'Compras',
        valor: 'Proveedores',
        estado: 'Solución',
        badgeClass:
            'bg-violet-50 text-violet-900 border-violet-200 dark:bg-violet-950/30 dark:text-violet-300 dark:border-violet-900/50',
        iconColor: 'text-violet-500',
        icon: Boxes,
        href: 'https://bys.laudaapi.com',
    },
    {
        id: 't360',
        codigo: '360',
        nombre: 'Transformación 360',
        entidad: 'LAUDAAPI',
        valor: 'Diagnóstico + roadmap',
        estado: 'Servicio',
        badgeClass:
            'bg-slate-50 text-slate-900 border-slate-200 dark:bg-slate-800/60 dark:text-slate-200 dark:border-slate-700',
        iconColor: 'text-slate-700 dark:text-slate-200',
        icon: Building2,
        href: '#transformacion',
    },
];

const features = [
    'Una cuenta central para empresa, usuarios, planes, facturas y pagos',
    'Soluciones independientes que puedes contratar según tu necesidad',
    'Social y CRM para captar, organizar y convertir oportunidades',
    'POS, e-CF, Cumplimiento y BYS para operar y administrar',
    'Transformación 360 cuando necesitas diagnóstico y una ruta integral',
];

const primarySolutions = ecosystemOptions.filter(
    (option) => ![ 'app-hub', 't360' ].includes(option.id),
);

const currentIndex = ref(0);
const currentOption = computed(
    () => ecosystemOptions[ currentIndex.value ],
);

let timer: ReturnType<typeof setInterval>;

function selectOption(index: number) {
    currentIndex.value = index;
    restartTimer();
}

function restartTimer() {
    if (timer) {
        clearInterval(timer);
    }

    timer = setInterval(() => {
        currentIndex.value =
            (currentIndex.value + 1) % ecosystemOptions.length;
    }, 3200);
}

function openCurrentOption() {
    const href = currentOption.value.href;

    if (href.startsWith('#')) {
        document
            .querySelector(href)
            ?.scrollIntoView({ behavior: 'smooth' });

        return;
    }

    window.location.assign(href);
}

function goLogin() {
    window.location.assign(`${APP_URL}/login`);
}

function goRegister() {
    window.location.assign(`${APP_URL}/register`);
}

onMounted(() => {
    restartTimer();
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});
</script>

<template>

    <Head title="LAUDAAPI — Soluciones empresariales" />

    <div class="flex min-h-screen w-full flex-col items-center bg-[#FAFAF8] px-4 py-4 text-[#1b1b18] dark:bg-[#0a0a0a]">
        <!-- NAV · mismo lenguaje compacto de Cumplimiento -->
        <nav class="mb-4 flex w-full max-w-5xl items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-[#0c0c0c]">
            <Link href="/" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-500 text-white shadow-md shadow-red-500/20">
                    <BrandLogo class="h-5 w-5" />
                </span>

                <div class="leading-none">
                    <p class="text-sm font-black tracking-tight text-slate-900 dark:text-white">
                        LAUDAAPI
                    </p>
                    <p class="mt-1 text-[8px] font-black tracking-[0.18em] text-red-500 uppercase">
                        Ecosistema empresarial
                    </p>
                </div>
            </Link>

            <div class="flex items-center gap-2">
                <button type="button" class="hidden rounded-xl px-4 py-2 text-xs font-black text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 sm:inline-flex dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white" @click="goLogin">
                    Iniciar sesión
                </button>

                <button v-if="canRegister" type="button" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white shadow-md transition-all hover:bg-red-600 dark:bg-white dark:text-slate-900 dark:hover:bg-red-500 dark:hover:text-white" @click="goRegister">
                    Crear cuenta
                </button>
            </div>
        </nav>

        <!-- MAIN CARD · estructura tomada del patrón Cumplimiento -->
        <main class="flex w-full max-w-5xl flex-col overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white shadow-2xl lg:flex-row dark:border-slate-800 dark:bg-[#0c0c0c]">
            <!-- LEFT · CARD INTERACTIVO -->
            <div class="relative flex h-82 w-full shrink-0 items-center justify-center overflow-hidden bg-linear-to-br from-[#FFF4F2] via-[#FFE2DE] to-[#FFD4CF] lg:h-auto lg:w-96 dark:from-[#1a0505] dark:via-[#2d0a0a] dark:to-[#1a0505]">
                <div class="pointer-events-none absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/45 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-red-400/20 blur-3xl" />

                <div class="z-10 w-full max-w-70 rounded-3xl border border-white/50 bg-white/70 p-6 shadow-2xl backdrop-blur-xl dark:border-white/10 dark:bg-black/55">
                    <!-- código + estado -->
                    <div class="mb-5 flex items-center justify-between">
                        <transition enter-active-class="transition-all duration-400" enter-from-class="opacity-0 -translate-x-2" enter-to-class="opacity-100 translate-x-0" mode="out-in">
                            <span :key="currentOption.id" class="text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase">
                                {{ currentOption.entidad }} ·
                                {{ currentOption.codigo }}
                            </span>
                        </transition>

                        <transition enter-active-class="transition-all duration-400" enter-from-class="opacity-0 scale-90" enter-to-class="opacity-100 scale-100" mode="out-in">
                            <span :key="`${currentOption.id}-status`" :class="[
                                'rounded-full border px-3 py-1 text-[9px] font-black shadow-sm',
                                currentOption.badgeClass,
                            ]">
                                {{ currentOption.estado }}
                            </span>
                        </transition>
                    </div>

                    <!-- nombre -->
                    <div class="mb-4 flex flex-col">
                        <span class="mb-1 text-[9px] font-bold tracking-wide text-slate-400 uppercase">
                            Ecosistema LAUDAAPI
                        </span>

                        <transition enter-active-class="transition-all duration-400" enter-from-class="opacity-0" enter-to-class="opacity-100" mode="out-in">
                            <span :key="`${currentOption.id}-name`" class="text-xl font-black tracking-tight text-slate-900 dark:text-white">
                                {{ currentOption.nombre }}
                            </span>
                        </transition>
                    </div>

                    <div class="my-3 border-t border-dashed border-slate-300 dark:border-slate-700" />

                    <!-- valor + icono -->
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex flex-col">
                            <span class="text-[9px] font-bold tracking-tight text-slate-400 uppercase">
                                Enfoque
                            </span>

                            <transition enter-active-class="transition-all duration-400" enter-from-class="opacity-0" enter-to-class="opacity-100" mode="out-in">
                                <span :key="`${currentOption.id}-value`" class="truncate text-lg font-black" :class="currentOption.iconColor">
                                    {{ currentOption.valor }}
                                </span>
                            </transition>
                        </div>

                        <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm dark:bg-slate-900">
                            <transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 scale-0 rotate-[-20deg]" enter-to-class="opacity-100 scale-100 rotate-0" mode="out-in">
                                <component :is="currentOption.icon" :key="`${currentOption.id}-icon`" class="h-5 w-5" :class="currentOption.iconColor" />
                            </transition>
                        </div>
                    </div>

                    <!-- CTA dentro del card -->
                    <button type="button" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-[11px] font-black text-slate-700 transition-all hover:border-red-300 hover:text-red-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-200" @click="openCurrentOption">
                        {{
                            currentOption.id === 't360'
                                ? 'Conocer Transformación 360'
                                : currentOption.id === 'app-hub'
                                    ? 'Ir a Mi cuenta'
                                    : 'Conocer solución'
                        }}
                        <ArrowRight class="h-3.5 w-3.5" />
                    </button>

                    <!-- indicadores clicables -->
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-1.5">
                        <button v-for="(option, i) in ecosystemOptions" :key="option.id" type="button" :title="option.nombre" :aria-label="`Ver ${option.nombre}`" :class="[
                            'rounded-full transition-all duration-300',
                            i === currentIndex
                                ? 'h-1.5 w-5 bg-red-500'
                                : 'h-1.5 w-1.5 bg-slate-300 hover:bg-slate-400 dark:bg-slate-600',
                        ]" @click="selectOption(i)" />
                    </div>
                </div>
            </div>

            <!-- RIGHT · COPY -->
            <div class="flex flex-1 flex-col justify-center p-8 sm:p-10 lg:p-14">
                <div class="mb-5 inline-flex w-fit items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 dark:border-red-900/50 dark:bg-red-950/30">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500" />
                    <span class="text-[9.5px] font-black tracking-widest text-red-700 uppercase dark:text-red-400">
                        Social · CRM · POS · e-CF · Cumplimiento · BYS · RD
                    </span>
                </div>

                <h1 class="mb-3 text-4xl leading-none font-black tracking-tight text-slate-900 sm:text-5xl dark:text-white">
                    Una cuenta.<br />
                    <span class="text-red-600">Tus soluciones.</span>
                </h1>

                <p class="mb-8 max-w-md text-sm leading-relaxed font-medium text-slate-500 dark:text-slate-400">
                    LAUDAAPI reúne soluciones empresariales independientes bajo
                    una sola relación comercial. Contrata lo que necesitas,
                    administra tu cuenta desde App Hub y amplía el ecosistema a
                    medida que tu empresa crece.
                </p>

                <ul class="mb-9 flex flex-col gap-3">
                    <li v-for="item in features" :key="item" class="flex items-center gap-3 text-[13px] font-bold text-slate-700 dark:text-slate-200">
                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <CheckCircle2 class="h-3 w-3 text-green-600 dark:text-green-400" />
                        </div>

                        {{ item }}
                    </li>
                </ul>

                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <button v-if="canRegister" type="button" class="rounded-2xl bg-slate-900 px-8 py-4 text-base font-black text-white shadow-xl transition-all duration-200 hover:scale-[1.03] hover:bg-red-600 active:scale-95 dark:bg-white dark:text-slate-900 dark:hover:bg-red-500 dark:hover:text-white" @click="goRegister">
                            Crear cuenta
                        </button>

                        <button type="button" class="rounded-2xl border-2 border-slate-200 bg-white px-8 py-4 text-base font-black text-slate-700 transition-all duration-200 hover:scale-[1.03] hover:border-slate-900 hover:text-black dark:border-slate-800 dark:bg-transparent dark:text-slate-300 dark:hover:border-white dark:hover:text-white" @click="goLogin">
                            Iniciar sesión
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-[10px] font-semibold text-slate-400">
                            Una cuenta · Múltiples soluciones
                        </span>

                        <div class="h-4 w-px bg-slate-200 dark:bg-slate-800" />

                        <div class="flex items-center gap-4">
                            <div class="flex flex-col">
                                <span class="text-[9px] leading-none font-bold tracking-widest text-slate-400 uppercase">
                                    6
                                </span>
                                <span class="text-sm font-black tracking-tight text-slate-900 uppercase dark:text-white">
                                    Soluciones
                                </span>
                            </div>

                            <div class="h-7 w-px bg-slate-100 dark:bg-slate-800" />

                            <div class="flex flex-col">
                                <span class="text-[9px] leading-none font-bold tracking-widest text-slate-400 uppercase">
                                    360
                                </span>
                                <span class="text-sm font-black tracking-tight text-slate-900 uppercase dark:text-white">
                                    Opcional
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- CÓMO FUNCIONA -->
        <section id="como-funciona" class="mt-8 w-full max-w-5xl scroll-mt-20">
            <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-[#0c0c0c]">
                <div class="mb-10 text-center">
                    <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase">
                        En 3 pasos
                    </p>

                    <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                        Empieza sin complicarte.
                    </h2>

                    <p class="mx-auto mt-3 max-w-xl text-sm text-slate-500 dark:text-slate-400">
                        Crea tu cuenta central, elige las soluciones que necesita
                        tu empresa y accede a cada plataforma desde el ecosistema.
                    </p>
                </div>

                <div class="grid gap-6 sm:grid-cols-3">
                    <div class="relative rounded-2xl border border-slate-100 bg-slate-50/50 p-6 dark:border-slate-800 dark:bg-slate-900/20">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-500 text-white shadow-lg">
                            <span class="text-xl font-black">1</span>
                        </div>

                        <h3 class="mb-2 font-black text-slate-900 dark:text-white">
                            Crea tu cuenta
                        </h3>

                        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            Registra tu usuario y empresa una sola vez en
                            app.laudaapi.com.
                        </p>
                    </div>

                    <div class="relative rounded-2xl border border-slate-100 bg-slate-50/50 p-6 dark:border-slate-800 dark:bg-slate-900/20">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-lg dark:bg-white dark:text-black">
                            <span class="text-xl font-black">2</span>
                        </div>

                        <h3 class="mb-2 font-black text-slate-900 dark:text-white">
                            Elige una solución
                        </h3>

                        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            Contrata Social, CRM, POS, e-CF, Cumplimiento o BYS
                            según lo que necesitas.
                        </p>
                    </div>

                    <div class="relative rounded-2xl border border-slate-100 bg-slate-50/50 p-6 dark:border-slate-800 dark:bg-slate-900/20">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-lg dark:bg-white dark:text-black">
                            <span class="text-xl font-black">3</span>
                        </div>

                        <h3 class="mb-2 font-black text-slate-900 dark:text-white">
                            Accede y crece
                        </h3>

                        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            Abre tus soluciones desde App Hub y agrega nuevas
                            capacidades cuando las necesites.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SOLUCIONES -->
        <section id="soluciones" class="mt-6 w-full max-w-5xl">
            <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-[#0c0c0c]">
                <div class="mb-8 text-center">
                    <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase">
                        Soluciones
                    </p>

                    <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                        Una plataforma para cada necesidad.
                    </h2>

                    <p class="mx-auto mt-3 max-w-xl text-sm text-slate-500 dark:text-slate-400">
                        Cada solución mantiene su propia operación y puede usarse
                        de forma independiente.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <a v-for="solution in primarySolutions" :key="solution.id" :href="solution.href" class="group rounded-2xl border border-slate-100 bg-slate-50/50 p-5 transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900/20">
                        <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-slate-900">
                            <component :is="solution.icon" class="h-5 w-5" :class="solution.iconColor" />
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-black text-slate-900 dark:text-white">
                                {{ solution.nombre }}
                            </h3>

                            <ArrowRight class="h-4 w-4 text-slate-300 transition-transform group-hover:translate-x-1 group-hover:text-red-500" />
                        </div>

                        <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ solution.valor }}
                        </p>
                    </a>
                </div>
            </div>
        </section>

        <!-- TRANSFORMACIÓN 360 -->
        <section id="transformacion" class="mt-6 w-full max-w-5xl scroll-mt-20">
            <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-[#0c0c0c]">
                <div class="flex flex-col items-center gap-8 text-center lg:flex-row lg:text-left">
                    <div class="flex-1">
                        <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase">
                            Transformación 360
                        </p>

                        <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            ¿No sabes qué solución necesitas primero?
                        </h2>

                        <p class="mt-4 max-w-xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                            Transformación 360 analiza la situación de tu empresa,
                            identifica prioridades y organiza un roadmap de
                            implementación. Es una opción adicional, no un
                            requisito para contratar soluciones LAUDAAPI.
                        </p>
                    </div>

                    <a href="mailto:contacto@laudaapi.com?subject=Transformación%20360" class="shrink-0 rounded-2xl bg-slate-900 px-8 py-4 text-base font-black text-white shadow-lg transition-all duration-200 hover:scale-105 hover:bg-red-500 dark:bg-white dark:text-slate-900 dark:hover:bg-red-500 dark:hover:text-white">
                        Conocer Transformación 360 →
                    </a>
                </div>
            </div>
        </section>

        <!-- APP HUB / ECOSISTEMA -->
        <section class="mt-6 w-full max-w-5xl">
            <div class="rounded-[2.5rem] border border-slate-200 bg-slate-900 p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-slate-950">
                <div class="flex flex-col items-center gap-6 text-center lg:flex-row lg:justify-between lg:text-left">
                    <div class="flex-1">
                        <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-400 uppercase">
                            app.laudaapi.com
                        </p>

                        <h2 class="mb-2 text-2xl font-black tracking-tight text-white sm:text-3xl">
                            Una cuenta para todo el ecosistema.
                        </h2>

                        <p class="mb-4 max-w-lg text-sm text-slate-400">
                            Administra empresa, usuarios, soluciones, planes,
                            facturas, pagos y accesos desde tu cuenta central
                            LAUDAAPI.
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white">
                                Social
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white">
                                CRM
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white">
                                POS
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white">
                                e-CF
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white">
                                Cumplimiento
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white">
                                BYS
                            </span>
                        </div>
                    </div>

                    <button type="button" class="shrink-0 rounded-2xl bg-white px-8 py-4 text-base font-black text-slate-900 shadow-lg transition-all duration-200 hover:scale-105 hover:bg-red-500 hover:text-white active:scale-95" @click="goRegister">
                        Crear mi cuenta →
                    </button>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="mt-8 mb-4 w-full max-w-5xl">
            <div class="flex flex-col gap-5 border-t border-slate-200 pt-6 dark:border-slate-800">
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between sm:text-left">
                    <p class="text-[10px] font-black tracking-[0.4em] text-slate-400 uppercase dark:text-slate-600">
                        Soluciones empresariales LAUDAAPI
                    </p>

                    <div class="flex items-center gap-5">
                        <Link href="/legal/privacidad" class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white">
                            Privacidad
                        </Link>

                        <Link href="/legal/terminos" class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white">
                            Términos
                        </Link>

                        <a href="https://status.laudaapi.com" class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white">
                            Status
                        </a>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                    <div class="flex items-center gap-4">
                        <a href="https://instagram.com/laudaapi" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition-colors hover:text-pink-600">
                            <Instagram class="h-3.5 w-3.5" />
                            @laudaapi
                        </a>

                        <a href="mailto:contacto@laudaapi.com" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition-colors hover:text-red-600">
                            <Mail class="h-3.5 w-3.5" />
                            contacto@laudaapi.com
                        </a>
                    </div>

                    <p class="text-[10px] font-bold text-slate-400">
                        © 2026 LAUDAAPI · República Dominicana
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
