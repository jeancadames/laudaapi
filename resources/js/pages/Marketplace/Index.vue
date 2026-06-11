<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowUpRight,
    BellRing,
    Boxes,
    Building2,
    CalendarCheck,
    CheckCircle2,
    Code2,
    FileText,
    Instagram,
    Mail,
    MessageCircle,
    Network,
    ReceiptText,
    Rocket,
    ShieldCheck,
    Store,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PublicNav from '@/components/PublicNav.vue';

/**
 * Welcome principal para laudaapi.com.
 *
 * Este landing funciona como la puerta principal del ecosistema LaudaAPI:
 * e-CF, Cumplimiento, POS, Social, CRM y Status.
 *
 * No usa pricing, docs, register() ni rutas generadas.
 */

const productos = [
    {
        id: 'ecf',
        codigo: 'e-CF',
        nombre: 'Facturación electrónica',
        entidad: 'DGII',
        valor: 'ecf.laudaapi.com',
        estado: 'Fiscal',
        badgeClass: 'bg-blue-50 text-blue-900 border-blue-200',
        iconColor: 'text-blue-500',
        icon: ReceiptText,

        title: 'LaudaAPI e-CF',
        subtitle: 'Facturación electrónica dominicana',
        href: 'https://ecf.laudaapi.com',
        domain: 'ecf.laudaapi.com',
        desc: 'Emisión, firma, envío, acuses, webhooks y seguimiento de comprobantes fiscales electrónicos para DGII.',
        tags: ['DGII', 'XMLDSIG', 'Webhooks'],
        color: 'text-blue-500',
        bg: 'bg-blue-500/10',
        dot: 'bg-blue-400',
    },
    {
        id: 'cumplimiento',
        codigo: 'Fiscal',
        nombre: 'Cumplimiento y alertas',
        entidad: 'DGII · TSS',
        valor: 'cumplimiento.laudaapi.com',
        estado: 'Control',
        badgeClass: 'bg-amber-50 text-amber-800 border-amber-200',
        iconColor: 'text-amber-500',
        icon: CalendarCheck,

        title: 'Cumplimiento Fiscal',
        subtitle: 'Calendario, obligaciones y formularios',
        href: 'https://cumplimiento.laudaapi.com',
        domain: 'cumplimiento.laudaapi.com',
        desc: 'Control de vencimientos fiscales, alertas, obligaciones DGII/TSS y preparación de reportes para gestión tributaria.',
        tags: ['DGII', 'TSS', 'Alertas'],
        color: 'text-amber-500',
        bg: 'bg-amber-500/10',
        dot: 'bg-amber-400',
    },
    {
        id: 'pos',
        codigo: 'POS',
        nombre: 'Ventas, caja e inventario',
        entidad: 'Operación',
        valor: 'pos.laudaapi.com',
        estado: 'Ventas',
        badgeClass: 'bg-green-50 text-green-900 border-green-200',
        iconColor: 'text-green-500',
        icon: Store,

        title: 'LaudaAPI POS',
        subtitle: 'Punto de venta, caja e inventario',
        href: 'https://pos.laudaapi.com',
        domain: 'pos.laudaapi.com',
        desc: 'Ventas rápidas, sucursales, productos, códigos de barra, caja, inventario y conexión opcional con e-CF y cumplimiento.',
        tags: ['POS', 'Inventario', 'Caja'],
        color: 'text-green-500',
        bg: 'bg-green-500/10',
        dot: 'bg-green-400',
    },
    {
        id: 'social',
        codigo: 'Social',
        nombre: 'Publicaciones, inbox y leads',
        entidad: 'Marketing',
        valor: 'social.laudaapi.com',
        estado: 'Canales',
        badgeClass: 'bg-pink-50 text-pink-900 border-pink-200',
        iconColor: 'text-pink-500',
        icon: MessageCircle,

        title: 'SocialLaudaAPI',
        subtitle: 'Gestión social, inbox y leads',
        href: 'https://social.laudaapi.com',
        domain: 'social.laudaapi.com',
        desc: 'Centraliza publicaciones, respuestas, inbox, leads sociales y automatizaciones conectables con CRM.',
        tags: ['Social', 'Inbox', 'Leads'],
        color: 'text-pink-500',
        bg: 'bg-pink-500/10',
        dot: 'bg-pink-400',
    },
    {
        id: 'crm',
        codigo: 'CRM',
        nombre: 'Clientes, leads y seguimiento',
        entidad: 'Comercial',
        valor: 'crm.laudaapi.com',
        estado: 'Ventas',
        badgeClass: 'bg-purple-50 text-purple-900 border-purple-200',
        iconColor: 'text-purple-500',
        icon: Users,

        title: 'CrmLaudaAPI',
        subtitle: 'Gestión comercial y clientes',
        href: 'https://crm.laudaapi.com',
        domain: 'crm.laudaapi.com',
        desc: 'Administra leads, clientes, contactos, oportunidades y seguimiento comercial desde un flujo centralizado.',
        tags: ['CRM', 'Clientes', 'Leads'],
        color: 'text-purple-500',
        bg: 'bg-purple-500/10',
        dot: 'bg-purple-400',
    },
    {
        id: 'status',
        codigo: 'Status',
        nombre: 'Estado de servicios',
        entidad: 'Infraestructura',
        valor: 'status.laudaapi.com',
        estado: 'Online',
        badgeClass: 'bg-emerald-50 text-emerald-900 border-emerald-200',
        iconColor: 'text-emerald-500',
        icon: Activity,

        title: 'LaudaAPI Status',
        subtitle: 'Monitoreo y disponibilidad',
        href: 'https://status.laudaapi.com',
        domain: 'status.laudaapi.com',
        desc: 'Página pública para revisar disponibilidad, salud de servicios y estado operativo del ecosistema LaudaAPI.',
        tags: ['Status', 'Uptime', 'Salud'],
        color: 'text-emerald-500',
        bg: 'bg-emerald-500/10',
        dot: 'bg-emerald-400',
    },
];

const features = [
    'Un ecosistema digital para facturación, ventas, clientes y cumplimiento en RD',
    'e-CF, cumplimiento, POS, social, CRM y status en ambientes separados',
    'Aplicaciones independientes que pueden conectarse entre sí',
    'Operación, comunicación, ventas, fiscalidad y monitoreo en una misma visión',
    'Base multiempresa, API-first y preparada para integraciones',
    'Soporte local y evolución continua del ambiente Lauda Digital',
];

const plataforma = [
    {
        icon: Network,
        title: 'Ecosistema conectado',
        desc: 'Cada aplicación puede operar por separado, pero juntas forman una base digital para ventas, facturación, clientes y obligaciones.',
        color: 'text-red-500',
        bg: 'bg-red-500/10',
    },
    {
        icon: ShieldCheck,
        title: 'Enfoque fiscal RD',
        desc: 'Diseñado alrededor de procesos dominicanos: DGII, TSS, RNC, NCF, e-CF, comprobantes y obligaciones recurrentes.',
        color: 'text-blue-500',
        bg: 'bg-blue-500/10',
    },
    {
        icon: Code2,
        title: 'API-first',
        desc: 'Pensado para integrarse con sistemas externos, ERPs propios, flujos internos y automatizaciones de negocios.',
        color: 'text-emerald-500',
        bg: 'bg-emerald-500/10',
    },
    {
        icon: Building2,
        title: 'Multiempresa',
        desc: 'Ideal para grupos empresariales, contadores y firmas que necesitan manejar más de una razón social o RNC.',
        color: 'text-purple-500',
        bg: 'bg-purple-500/10',
    },
    {
        icon: BellRing,
        title: 'Operación con alertas',
        desc: 'Seguimiento de procesos importantes: comprobantes, vencimientos, estados operativos y tareas fiscales.',
        color: 'text-amber-500',
        bg: 'bg-amber-500/10',
    },
    {
        icon: Rocket,
        title: 'Ambiente en crecimiento',
        desc: 'LaudaAPI evoluciona por módulos: primero resolver bien cada proceso, luego conectarlos en una sola experiencia.',
        color: 'text-pink-500',
        bg: 'bg-pink-500/10',
    },
];

const flujo = [
    {
        icon: MessageCircle,
        label: '1 · Social',
        title: 'Capta conversaciones y leads',
        desc: 'Social organiza publicaciones, inbox, respuestas y leads que pueden alimentar el proceso comercial.',
        color: 'text-pink-400',
        bg: 'bg-pink-500/10',
    },
    {
        icon: Users,
        label: '2 · CRM',
        title: 'Gestiona clientes y seguimiento',
        desc: 'CRM centraliza leads, clientes, contactos, oportunidades y trazabilidad comercial.',
        color: 'text-purple-400',
        bg: 'bg-purple-500/10',
    },
    {
        icon: Boxes,
        label: '3 · POS',
        title: 'Vende y controla inventario',
        desc: 'POS maneja productos, sucursales, caja, pagos, clientes y flujo operativo de ventas.',
        color: 'text-green-400',
        bg: 'bg-green-500/10',
    },
    {
        icon: FileText,
        label: '4 · e-CF',
        title: 'Formaliza la factura',
        desc: 'e-CF gestiona firma, envío, estados, acuses y trazabilidad ante DGII.',
        color: 'text-blue-400',
        bg: 'bg-blue-500/10',
    },
    {
        icon: CalendarCheck,
        label: '5 · Cumplimiento',
        title: 'Controla obligaciones',
        desc: 'Cumplimiento organiza vencimientos, alertas, formularios y control fiscal recurrente.',
        color: 'text-amber-400',
        bg: 'bg-amber-500/10',
    },
    {
        icon: Activity,
        label: '6 · Status',
        title: 'Monitorea disponibilidad',
        desc: 'Status permite revisar la salud operativa y disponibilidad del ecosistema.',
        color: 'text-emerald-400',
        bg: 'bg-emerald-500/10',
    },
];

const currentIndex = ref(0);
const currentProducto = computed(() => productos[currentIndex.value]);

let timer: ReturnType<typeof setInterval>;

onMounted(() => {
    timer = setInterval(() => {
        currentIndex.value = (currentIndex.value + 1) % productos.length;
    }, 3000);

    if (window.location.hash) {
        setTimeout(() => {
            document
                .querySelector(window.location.hash)
                ?.scrollIntoView({ behavior: 'smooth' });
        }, 100);
    }
});

onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Head>
        <title>LaudaAPI — Ambiente Lauda Digital para negocios en RD</title>
        <meta
            name="description"
            content="LaudaAPI es el ecosistema digital para facturación electrónica, cumplimiento fiscal, POS, CRM, social media y monitoreo en República Dominicana."
        />
        <meta
            property="og:title"
            content="LaudaAPI — Ambiente Lauda Digital"
        />
        <meta
            property="og:description"
            content="Conecta e-CF, cumplimiento fiscal, POS, Social, CRM y Status en un ecosistema digital pensado para negocios dominicanos."
        />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>

    <div
        class="flex min-h-screen w-full flex-col items-center bg-[#FAFAF8] px-4 py-4 text-[#1b1b18] dark:bg-[#0a0a0a]"
    >
        <PublicNav />

        <!-- HERO -->
        <main
            class="flex w-full max-w-5xl flex-col overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white shadow-2xl lg:flex-row dark:border-slate-800 dark:bg-[#0c0c0c]"
        >
            <!-- LEFT — TARJETA ECOSISTEMA ANIMADA -->
            <div
                class="relative flex h-72 w-full shrink-0 items-center justify-center overflow-hidden bg-linear-to-br from-[#FFF5F0] via-[#FFE0D1] to-[#FFD1B8] lg:h-auto lg:w-96 dark:from-[#1a0505] dark:via-[#2d0a0a] dark:to-[#1a0505]"
            >
                <div
                    class="pointer-events-none absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/40 blur-3xl"
                />
                <div
                    class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-orange-400/20 blur-3xl"
                />

                <div
                    class="z-10 w-full max-w-72 rounded-3xl border border-white/40 bg-white/70 p-6 shadow-2xl backdrop-blur-xl dark:border-white/10 dark:bg-black/55"
                >
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <transition
                            enter-active-class="transition-all duration-400"
                            enter-from-class="opacity-0 -translate-x-2"
                            enter-to-class="opacity-100 translate-x-0"
                            mode="out-in"
                        >
                            <span
                                :key="currentProducto.id"
                                class="text-[9px] font-bold tracking-[0.15em] text-slate-500 uppercase"
                            >
                                {{ currentProducto.entidad }} ·
                                {{ currentProducto.codigo }}
                            </span>
                        </transition>

                        <transition
                            enter-active-class="transition-all duration-400"
                            enter-from-class="opacity-0 scale-90"
                            enter-to-class="opacity-100 scale-100"
                            mode="out-in"
                        >
                            <span
                                :key="currentProducto.id"
                                :class="[
                                    'rounded-full border px-3 py-1 text-[9px] font-black shadow-sm',
                                    currentProducto.badgeClass,
                                ]"
                            >
                                {{ currentProducto.estado }}
                            </span>
                        </transition>
                    </div>

                    <div class="mb-4 flex flex-col">
                        <span
                            class="mb-1 text-[9px] font-bold tracking-wide text-slate-400 uppercase"
                        >
                            Ambiente Lauda Digital
                        </span>

                        <transition
                            enter-active-class="transition-all duration-400"
                            enter-from-class="opacity-0"
                            enter-to-class="opacity-100"
                            mode="out-in"
                        >
                            <span
                                :key="currentProducto.id"
                                class="text-xl font-black tracking-tight text-slate-900 dark:text-white"
                            >
                                {{ currentProducto.nombre }}
                            </span>
                        </transition>
                    </div>

                    <div
                        class="my-3 border-t border-dashed border-slate-300 dark:border-slate-700"
                    />

                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex flex-col">
                            <span
                                class="text-[9px] font-bold tracking-tight text-slate-400 uppercase"
                            >
                                Acceso
                            </span>

                            <transition
                                enter-active-class="transition-all duration-400"
                                enter-from-class="opacity-0"
                                enter-to-class="opacity-100"
                                mode="out-in"
                            >
                                <span
                                    :key="currentProducto.id"
                                    class="truncate font-mono text-[11px] font-black"
                                    :class="currentProducto.iconColor"
                                >
                                    {{ currentProducto.valor }}
                                </span>
                            </transition>
                        </div>

                        <div
                            class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm dark:bg-slate-900"
                        >
                            <transition
                                enter-active-class="transition-all duration-300"
                                enter-from-class="opacity-0 scale-0 rotate-[-20deg]"
                                enter-to-class="opacity-100 scale-100 rotate-0"
                                mode="out-in"
                            >
                                <component
                                    :is="currentProducto.icon"
                                    :key="currentProducto.id"
                                    class="h-5 w-5"
                                    :class="currentProducto.iconColor"
                                />
                            </transition>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-center gap-1.5">
                        <div
                            v-for="(_, i) in productos"
                            :key="i"
                            :class="[
                                'rounded-full transition-all duration-400',
                                i === currentIndex
                                    ? 'h-1.5 w-4 bg-red-500'
                                    : 'h-1.5 w-1.5 bg-slate-300 dark:bg-slate-600',
                            ]"
                        />
                    </div>
                </div>
            </div>

            <!-- RIGHT — COPY -->
            <div
                class="flex flex-1 flex-col justify-center p-8 sm:p-10 lg:p-14"
            >
                <div
                    class="mb-5 inline-flex w-fit items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 dark:border-red-900/50 dark:bg-red-950/30"
                >
                    <span
                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"
                    />
                    <span
                        class="text-[9.5px] font-black tracking-widest text-red-700 uppercase dark:text-red-400"
                    >
                        e-CF · Cumplimiento · POS · Social · CRM · Status
                    </span>
                </div>

                <h1
                    class="mb-3 text-4xl leading-none font-black tracking-tight text-slate-900 sm:text-5xl dark:text-white"
                >
                    LaudaAPI<br />
                    <span class="text-red-600">Digital.</span>
                </h1>

                <p
                    class="mb-8 max-w-md text-sm leading-relaxed font-medium text-slate-500 dark:text-slate-400"
                >
                    El ambiente digital de Lauda para negocios dominicanos:
                    facturación electrónica, cumplimiento fiscal, punto de
                    venta, social media, CRM, monitoreo e integraciones API en
                    un mismo ecosistema.
                </p>

                <ul class="mb-10 flex flex-col gap-3">
                    <li
                        v-for="item in features"
                        :key="item"
                        class="flex items-center gap-3 text-[13px] font-bold text-slate-700 dark:text-slate-200"
                    >
                        <div
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30"
                        >
                            <CheckCircle2
                                class="h-3.5 w-3.5 text-green-600 dark:text-green-400"
                            />
                        </div>

                        {{ item }}
                    </li>
                </ul>

                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <a
                            href="#soluciones"
                            class="rounded-2xl bg-slate-900 px-8 py-4 text-base font-black text-white shadow-xl transition-all duration-200 hover:scale-[1.03] hover:bg-red-600 active:scale-95"
                        >
                            Explorar ecosistema
                        </a>

                        <a
                            href="mailto:contacto@laudaapi.com"
                            class="rounded-2xl border-2 border-slate-200 bg-white px-8 py-4 text-base font-black text-slate-700 transition-all duration-200 hover:scale-[1.03] hover:border-slate-900 hover:text-black dark:border-slate-800 dark:bg-transparent dark:text-slate-300 dark:hover:border-white dark:hover:text-white"
                        >
                            Contactar
                        </a>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-[10px] font-semibold text-slate-400">
                            LaudaAPI como hub principal
                        </span>

                        <div class="h-4 w-px bg-slate-200 dark:bg-slate-800" />

                        <div class="flex items-center gap-4">
                            <div class="flex flex-col">
                                <span
                                    class="text-[9px] leading-none font-bold tracking-widest text-slate-400 uppercase"
                                >
                                    6
                                </span>
                                <span
                                    class="text-sm font-black tracking-tight text-slate-900 uppercase dark:text-white"
                                >
                                    Ambientes
                                </span>
                            </div>

                            <div
                                class="h-7 w-px bg-slate-100 dark:bg-slate-800"
                            />

                            <div class="flex flex-col">
                                <span
                                    class="text-[9px] leading-none font-bold tracking-widest text-slate-400 uppercase"
                                >
                                    API
                                </span>
                                <span
                                    class="text-sm font-black tracking-tight text-slate-900 uppercase dark:text-white"
                                >
                                    First
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- MINICARDS ANIMADAS DEL ECOSISTEMA -->
        <section class="mt-6 w-full max-w-5xl">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <a
                    v-for="(producto, i) in productos"
                    :key="producto.id"
                    :href="producto.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    :class="[
                        'group relative overflow-hidden rounded-3xl border p-5 transition-all duration-500',
                        i === currentIndex
                            ? 'scale-[1.02] border-red-200 bg-white shadow-xl ring-1 ring-red-100 dark:border-red-900/60 dark:bg-slate-900 dark:ring-red-950/50'
                            : 'border-slate-200 bg-white/70 shadow-sm hover:-translate-y-1 hover:bg-white hover:shadow-lg dark:border-slate-800 dark:bg-[#0c0c0c] dark:hover:bg-slate-900',
                    ]"
                >
                    <div
                        v-if="i === currentIndex"
                        class="pointer-events-none absolute -top-10 -right-10 h-24 w-24 rounded-full bg-red-500/10 blur-2xl"
                    />

                    <div class="relative flex items-start justify-between gap-4">
                        <div
                            :class="[
                                'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl transition-all duration-500',
                                producto.bg,
                                producto.color,
                                i === currentIndex ? 'scale-110 shadow-lg' : '',
                            ]"
                        >
                            <component :is="producto.icon" class="h-5 w-5" />
                        </div>

                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 transition-all group-hover:border-red-200 group-hover:text-red-500 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <ArrowUpRight class="h-3.5 w-3.5" />
                        </div>
                    </div>

                    <div class="relative mt-4">
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                :class="[
                                    'h-1.5 w-1.5 rounded-full',
                                    producto.dot,
                                    i === currentIndex ? 'animate-pulse' : '',
                                ]"
                            />

                            <span
                                class="font-mono text-[10px] font-black text-slate-400"
                            >
                                {{ producto.domain }}
                            </span>
                        </div>

                        <h3
                            class="text-base font-black tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ producto.title }}
                        </h3>

                        <p
                            class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                        >
                            {{ producto.desc }}
                        </p>
                    </div>
                </a>
            </div>
        </section>

        <!-- SOLUCIONES -->
        <section id="soluciones" class="mt-8 w-full max-w-5xl scroll-mt-20">
            <div
                class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-[#0c0c0c]"
            >
                <div class="mb-10 text-center">
                    <p
                        class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase"
                    >
                        Ecosistema LaudaAPI
                    </p>

                    <h2
                        class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white"
                    >
                        Seis ambientes. Una visión digital.
                    </h2>

                    <p
                        class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400"
                    >
                        Cada ambiente resuelve una parte crítica del negocio:
                        operación, fiscalidad, ventas, clientes, comunicación y
                        monitoreo. Puedes usarlos por separado o conectarlos
                        como una plataforma digital completa.
                    </p>
                </div>

                <div class="grid gap-5 lg:grid-cols-3">
                    <a
                        v-for="producto in productos"
                        :key="producto.title"
                        :href="producto.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group flex min-h-[360px] flex-col rounded-3xl border border-slate-100 bg-slate-50/60 p-6 transition-all duration-200 hover:-translate-y-1 hover:border-red-200 hover:bg-white hover:shadow-xl dark:border-slate-800 dark:bg-slate-900/20 dark:hover:border-red-900/60 dark:hover:bg-slate-900/40"
                    >
                        <div class="mb-5 flex items-start justify-between gap-4">
                            <div
                                :class="[
                                    'flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl',
                                    producto.bg,
                                    producto.color,
                                ]"
                            >
                                <component :is="producto.icon" class="h-6 w-6" />
                            </div>

                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 transition-all group-hover:border-red-200 group-hover:text-red-500 dark:border-slate-800 dark:bg-slate-950"
                            >
                                <ArrowUpRight class="h-4 w-4" />
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center gap-2">
                                <span
                                    :class="[
                                        'h-1.5 w-1.5 rounded-full',
                                        producto.dot,
                                    ]"
                                />

                                <span
                                    class="font-mono text-[10px] font-black tracking-tight text-slate-400"
                                >
                                    {{ producto.domain }}
                                </span>
                            </div>

                            <h3
                                class="text-xl font-black tracking-tight text-slate-900 dark:text-white"
                            >
                                {{ producto.title }}
                            </h3>

                            <p
                                class="mt-1 text-xs font-black tracking-widest text-slate-400 uppercase"
                            >
                                {{ producto.subtitle }}
                            </p>

                            <p
                                class="mt-4 text-sm leading-relaxed text-slate-500 dark:text-slate-400"
                            >
                                {{ producto.desc }}
                            </p>
                        </div>

                        <div class="mt-auto pt-6">
                            <div class="mb-5 flex flex-wrap gap-2">
                                <span
                                    v-for="tag in producto.tags"
                                    :key="tag"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-black text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400"
                                >
                                    {{ tag }}
                                </span>
                            </div>

                            <span
                                class="inline-flex items-center gap-2 text-sm font-black text-slate-900 transition-colors group-hover:text-red-600 dark:text-white dark:group-hover:text-red-400"
                            >
                                Ver ambiente
                                <ArrowUpRight class="h-4 w-4" />
                            </span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- CÓMO SE CONECTA -->
        <section id="flujo" class="mt-6 w-full max-w-5xl scroll-mt-20">
            <div
                class="overflow-hidden rounded-[2.5rem] border border-slate-200 bg-slate-950 shadow-xl dark:border-slate-800"
            >
                <div class="relative p-8 sm:p-12">
                    <div
                        class="pointer-events-none absolute -top-24 -right-24 h-64 w-64 rounded-full bg-red-500/20 blur-3xl"
                    />
                    <div
                        class="pointer-events-none absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-orange-500/10 blur-3xl"
                    />

                    <div class="relative">
                        <div class="mx-auto max-w-3xl text-center">
                            <p
                                class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-400 uppercase"
                            >
                                La idea central
                            </p>

                            <h2
                                class="text-3xl font-black tracking-tight text-white sm:text-4xl"
                            >
                                Del lead a la factura, con operación y monitoreo.
                            </h2>

                            <p
                                class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-slate-400"
                            >
                                Social capta conversaciones y leads. CRM organiza
                                el seguimiento comercial. POS ejecuta la venta.
                                e-CF formaliza la factura. Cumplimiento controla
                                las obligaciones. Status muestra la salud del
                                ecosistema.
                            </p>
                        </div>

                        <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="item in flujo"
                                :key="item.label"
                                class="rounded-3xl border border-white/10 bg-white/5 p-6 transition-all duration-200 hover:-translate-y-1 hover:bg-white/[0.07]"
                            >
                                <div
                                    :class="[
                                        'mb-4 flex h-12 w-12 items-center justify-center rounded-2xl',
                                        item.bg,
                                        item.color,
                                    ]"
                                >
                                    <component :is="item.icon" class="h-6 w-6" />
                                </div>

                                <p
                                    :class="[
                                        'mb-2 text-[10px] font-black tracking-widest uppercase',
                                        item.color,
                                    ]"
                                >
                                    {{ item.label }}
                                </p>

                                <h3 class="mb-2 font-black text-white">
                                    {{ item.title }}
                                </h3>

                                <p class="text-xs leading-relaxed text-slate-400">
                                    {{ item.desc }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- PLATAFORMA -->
        <section class="mt-6 w-full max-w-5xl">
            <div
                class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-[#0c0c0c]"
            >
                <div class="mb-8 text-center">
                    <p
                        class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase"
                    >
                        Plataforma digital
                    </p>

                    <h2
                        class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white"
                    >
                        Pensada para crecer por módulos.
                    </h2>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="item in plataforma"
                        :key="item.title"
                        class="rounded-2xl border border-slate-100 bg-slate-50/50 p-5 transition-all hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900/20"
                    >
                        <div
                            :class="[
                                'mb-3 flex h-10 w-10 items-center justify-center rounded-xl',
                                item.bg,
                                item.color,
                            ]"
                        >
                            <component :is="item.icon" class="h-5 w-5" />
                        </div>

                        <h3 class="mb-1 font-black text-slate-900 dark:text-white">
                            {{ item.title }}
                        </h3>

                        <p
                            class="text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                        >
                            {{ item.desc }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA ECOSISTEMA -->
        <section class="mt-6 w-full max-w-5xl">
            <div
                class="rounded-[2.5rem] border border-slate-200 bg-slate-900 p-8 shadow-xl sm:p-12 dark:border-slate-800 dark:bg-slate-950"
            >
                <div
                    class="flex flex-col items-center gap-6 text-center lg:flex-row lg:justify-between lg:text-left"
                >
                    <div class="flex-1">
                        <p
                            class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-400 uppercase"
                        >
                            Ambientes disponibles
                        </p>

                        <h2
                            class="mb-2 text-2xl font-black tracking-tight text-white sm:text-3xl"
                        >
                            Entra directo al producto que necesitas.
                        </h2>

                        <p class="mb-4 max-w-lg text-sm text-slate-400">
                            LaudaAPI es la puerta principal. Desde aquí puedes
                            acceder a facturación electrónica, cumplimiento
                            fiscal, punto de venta, social, CRM o status según
                            el proceso que quieras resolver.
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a
                                v-for="producto in productos"
                                :key="producto.id"
                                :href="producto.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white transition-colors hover:bg-white/20"
                            >
                                <span
                                    :class="[
                                        'h-1.5 w-1.5 rounded-full',
                                        producto.dot,
                                    ]"
                                />
                                {{ producto.domain }}
                            </a>
                        </div>
                    </div>

                    <a
                        href="mailto:contacto@laudaapi.com"
                        class="shrink-0 rounded-2xl bg-white px-8 py-4 text-base font-black text-slate-900 shadow-lg transition-all duration-200 hover:scale-105 hover:bg-red-500 hover:text-white active:scale-95"
                    >
                        Contactar →
                    </a>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="mt-8 mb-4 w-full max-w-5xl">
            <div
                class="flex flex-col gap-5 border-t border-slate-200 pt-6 dark:border-slate-800"
            >
                <div
                    class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between sm:text-left"
                >
                    <p
                        class="text-[10px] font-black tracking-[0.4em] text-slate-400 uppercase dark:text-slate-600"
                    >
                        Ambiente Lauda Digital
                    </p>

                    <div class="flex flex-wrap items-center justify-center gap-5">
                        <Link
                            href="/privacy"
                            class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white"
                        >
                            Privacidad
                        </Link>

                        <Link
                            href="/terms"
                            class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white"
                        >
                            Términos
                        </Link>

                        <a
                            v-for="producto in productos"
                            :key="producto.id"
                            :href="producto.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs font-bold text-slate-400 transition-colors hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400"
                        >
                            {{ producto.codigo }}
                        </a>
                    </div>
                </div>

                <div
                    class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between"
                >
                    <div class="flex items-center gap-4">
                        <a
                            href="https://instagram.com/laudaapi"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition-colors hover:text-pink-600 dark:hover:text-pink-400"
                        >
                            <Instagram class="h-3.5 w-3.5" />
                            @laudaapi
                        </a>

                        <a
                            href="mailto:contacto@laudaapi.com"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition-colors hover:text-red-600 dark:hover:text-red-400"
                        >
                            <Mail class="h-3.5 w-3.5" />
                            contacto@laudaapi.com
                        </a>
                    </div>

                    <p class="text-[10px] font-bold text-slate-400">
                        © 2026 Lauda API · Hecho con ❤️ en RD
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>