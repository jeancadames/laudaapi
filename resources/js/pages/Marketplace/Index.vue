<script setup>
import BrandLogo from '@/components/BrandLogo.vue'
import { Head, Link } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'

const solutionsOpen = ref(false)
const mobileSidebarOpen = ref(false)

const productApps = [
    {
        name: 'Social LaudaAPI',
        short: 'Social',
        href: 'https://social.laudaapi.com',
        description: 'Captación social, conversaciones y oportunidades.',
        icon: '💬',
        color: 'pink',
    },
    {
        name: 'CRM LaudaAPI',
        short: 'CRM',
        href: 'https://crm.laudaapi.com',
        description: 'Oportunidades, clientes, pipeline y seguimiento comercial.',
        icon: '👥',
        color: 'purple',
    },
    {
        name: 'POS LaudaAPI',
        short: 'POS',
        href: 'https://pos.laudaapi.com',
        description: 'Ventas, pedidos, inventario, cobros, facturación y despacho.',
        icon: '🏪',
        color: 'green',
    },
    {
        name: 'e-CF LaudaAPI',
        short: 'e-CF',
        href: 'https://ecf.laudaapi.com',
        description: 'Firma, envío y respuesta fiscal ante DGII.',
        icon: '📄',
        color: 'amber',
    },
    {
        name: 'Cumplimiento LaudaAPI',
        short: 'Cumplimiento',
        href: 'https://cumplimiento.laudaapi.com',
        description: 'Obligaciones, vencimientos, validaciones y control fiscal.',
        icon: '🛡️',
        color: 'emerald',
    },
    {
        name: 'Status LaudaAPI',
        short: 'Status',
        href: 'https://status.laudaapi.com',
        description: 'Disponibilidad, monitoreo y salud operativa del ecosistema.',
        icon: '〰️',
        color: 'blue',
    },
]

const leftApps = [
    {
        name: 'Social',
        text: 'Capta conversaciones y oportunidades.',
        color: 'pink',
        icon: '💬',
    },
    {
        name: 'CRM',
        text: 'Convierte oportunidades en solicitudes reales.',
        color: 'purple',
        icon: '👥',
    },
    {
        name: 'Ecommerce',
        text: 'Recibe pedidos y solicitudes online.',
        color: 'blue',
        icon: '🛒',
    },
]

const rightApps = [
    {
        name: 'POS',
        text: 'Opera ventas, inventario, cobros y despacho.',
        color: 'green',
        icon: '🏪',
    },
    {
        name: 'e-CF',
        text: 'Firma, envía y responde ante DGII.',
        color: 'amber',
        icon: '📄',
    },
    {
        name: 'Delivery',
        text: 'Asigna, entrega y registra evidencia.',
        color: 'orange',
        icon: '🚚',
    },
]

const backofficeApps = [
    {
        name: 'Cumplimiento',
        text: 'Obligaciones, validaciones y vencimientos.',
        icon: '🛡️',
        color: 'emerald',
    },
    {
        name: 'BYS',
        text: 'Compras, gastos y recepción de servicios.',
        icon: '📋',
        color: 'violet',
    },
    {
        name: 'Bancos',
        text: 'Conciliación y movimientos financieros.',
        icon: '🏦',
        color: 'blue',
    },
    {
        name: 'Contabilidad',
        text: 'Asientos contables y reportes financieros.',
        icon: '🧾',
        color: 'cyan',
    },
]

const liveFeed = [
    ['10:42:15', 'Social', 'Lead captado desde Instagram'],
    ['10:42:18', 'CRM', 'Oportunidad creada: #OP-4821'],
    ['10:42:21', 'POS', 'Pedido generado: #PED-8842'],
    ['10:42:28', 'e-CF', 'Factura firmada y enviada a DGII'],
    ['10:42:34', 'Delivery', 'Entrega asignada al conductor'],
    ['10:42:38', 'Status', 'Evento registrado correctamente'],
]

const metrics = [
    {
        label: 'Ambientes activos',
        value: '10 / 10',
        note: '100% disponibles',
        icon: '✅',
    },
    {
        label: 'DGII',
        value: 'Disponible',
        note: 'Sin interrupciones',
        icon: '🛡️',
    },
    {
        label: 'Procesos hoy',
        value: '1,248',
        note: '+12% vs ayer',
        icon: '📈',
    },
    {
        label: 'Eventos procesados',
        value: '4,892',
        note: 'En tiempo real',
        icon: '⚡',
    },
]

const flows = [
    {
        title: 'Lead a factura y entrega',
        description: 'Una conversación se convierte en oportunidad, venta, factura fiscal y entrega.',
        steps: ['Social', 'CRM', 'POS', 'e-CF', 'Delivery'],
    },
    {
        title: 'Pedido ecommerce a operación',
        description: 'El pedido online entra a operación real sin duplicar procesos.',
        steps: ['Ecommerce', 'POS', 'Inventario', 'e-CF', 'Delivery'],
    },
    {
        title: 'Compra a contabilidad',
        description: 'Las compras se procesan, validan y registran hasta el asiento contable.',
        steps: ['BYS', 'e-CF', 'Cumplimiento', 'Contabilidad', 'Bancos'],
    },
    {
        title: 'Operación monitoreada',
        description: 'Status observa eventos, disponibilidad y salud de todo el ecosistema.',
        steps: ['Todas las apps', 'Status'],
    },
]

const appCardClasses = {
    pink: 'border-pink-400/40 bg-pink-500/10 text-pink-300 shadow-pink-500/20',
    purple: 'border-purple-400/40 bg-purple-500/10 text-purple-300 shadow-purple-500/20',
    blue: 'border-blue-400/40 bg-blue-500/10 text-blue-300 shadow-blue-500/20',
    green: 'border-green-400/40 bg-green-500/10 text-green-300 shadow-green-500/20',
    amber: 'border-amber-400/40 bg-amber-500/10 text-amber-300 shadow-amber-500/20',
    orange: 'border-orange-400/40 bg-orange-500/10 text-orange-300 shadow-orange-500/20',
    emerald: 'border-emerald-400/40 bg-emerald-500/10 text-emerald-300 shadow-emerald-500/20',
    violet: 'border-violet-400/40 bg-violet-500/10 text-violet-300 shadow-violet-500/20',
    cyan: 'border-cyan-400/40 bg-cyan-500/10 text-cyan-300 shadow-cyan-500/20',
}

const stepClasses = {
    Social: 'bg-pink-100 text-pink-700',
    CRM: 'bg-purple-100 text-purple-700',
    POS: 'bg-green-100 text-green-700',
    Ecommerce: 'bg-blue-100 text-blue-700',
    Inventario: 'bg-slate-100 text-slate-700',
    'e-CF': 'bg-amber-100 text-amber-700',
    Delivery: 'bg-orange-100 text-orange-700',
    BYS: 'bg-violet-100 text-violet-700',
    Cumplimiento: 'bg-emerald-100 text-emerald-700',
    Contabilidad: 'bg-cyan-100 text-cyan-700',
    Bancos: 'bg-blue-100 text-blue-700',
    Status: 'bg-indigo-100 text-indigo-700',
    'Todas las apps': 'bg-slate-100 text-slate-700',
}
</script>

<template>
    <Head title="LaudaAPI Digital" />

    <main class="lauda-landing min-h-screen bg-[var(--page)] text-[var(--text)]">

    <header class="sticky top-0 z-50 border-b border-[color:var(--border)] bg-[color:var(--nav)]/88 backdrop-blur-xl">
    <div class="mx-auto flex max-w-[1520px] items-center justify-between px-5 py-4 lg:px-8">
        <!-- LOGO -->
        <Link
                    href="/"
                    class="group flex items-center gap-3 rounded-2xl px-2 py-1 transition-all duration-200 hover:scale-[1.02]"
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
                            API
                        </span>
                    </div>
                </Link>

        <!-- DESKTOP NAV -->
        <nav class="hidden items-center gap-1 rounded-full border border-[color:var(--border)] bg-[var(--surface)]/85 p-1 shadow-sm lg:flex">
            <div
                class="relative"
                @mouseenter="solutionsOpen = true"
                @mouseleave="solutionsOpen = false"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-[var(--muted)] transition hover:bg-[var(--surface-soft)] hover:text-[var(--text)]"
                    @click="solutionsOpen = !solutionsOpen"
                >
                    Soluciones
                    <span class="text-xs transition" :class="{ 'rotate-180': solutionsOpen }">⌄</span>
                </button>

                <div
                    v-show="solutionsOpen"
                    class="absolute left-0 top-full w-[560px] pt-4"
                >
                    <div class="rounded-[1.5rem] border border-[color:var(--border)] bg-[var(--surface)] p-3 shadow-2xl shadow-slate-950/20">
                        <div class="mb-3 px-3 pt-2">
                            <p class="text-[11px] font-black uppercase tracking-[0.28em] text-red-600">
                                Apps productivas
                            </p>
                            <p class="mt-1 text-sm text-[var(--muted)]">
                                Accede a los ambientes activos del ecosistema LaudaAPI.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <a
                                v-for="app in productApps"
                                :key="app.href"
                                :href="app.href"
                                class="group rounded-2xl border border-transparent p-4 transition hover:border-[color:var(--border)] hover:bg-[var(--surface-soft)]"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border text-xl shadow-lg"
                                        :class="appCardClasses[app.color]"
                                    >
                                        {{ app.icon }}
                                    </div>

                                    <div>
                                        <p class="text-sm font-black text-[var(--text)]">
                                            {{ app.name }}
                                        </p>
                                        <p class="mt-1 text-xs leading-5 text-[var(--muted)]">
                                            {{ app.description }}
                                        </p>
                                        <p class="mt-2 text-[11px] font-black uppercase tracking-[0.2em] text-red-600 opacity-0 transition group-hover:opacity-100">
                                            Abrir →
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <a href="#ecosistema" class="rounded-full px-5 py-2.5 text-sm font-bold text-[var(--muted)] transition hover:bg-[var(--surface-soft)] hover:text-[var(--text)]">
                Ecosistema
            </a>

            <a href="#flujos" class="rounded-full px-5 py-2.5 text-sm font-bold text-[var(--muted)] transition hover:bg-[var(--surface-soft)] hover:text-[var(--text)]">
                Flujos
            </a>

            <a href="https://ecf.laudaapi.com" class="rounded-full px-5 py-2.5 text-sm font-bold text-[var(--muted)] transition hover:bg-[var(--surface-soft)] hover:text-[var(--text)]">
                e-CF
            </a>

            <a href="#contacto" class="rounded-full px-5 py-2.5 text-sm font-bold text-[var(--muted)] transition hover:bg-[var(--surface-soft)] hover:text-[var(--text)]">
                Contacto
            </a>
        </nav>

        <!-- RIGHT ACTIONS -->
        <div class="flex items-center gap-3">
            <Link
                href="/login"
                class="hidden rounded-2xl bg-[var(--text)] px-6 py-3 text-sm font-black text-[var(--page)] shadow-xl shadow-slate-950/15 transition hover:-translate-y-0.5 sm:inline-flex"
            >
                Iniciar sesión
            </Link>

            <!-- MOBILE MENU BUTTON -->
            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-[color:var(--border)] bg-[var(--surface)] text-[var(--text)] shadow-sm transition hover:-translate-y-0.5 lg:hidden"
                @click="mobileSidebarOpen = true"
            >
                <span class="sr-only">Abrir menú</span>
                <span class="space-y-1.5">
                    <span class="block h-0.5 w-5 rounded-full bg-current"></span>
                    <span class="block h-0.5 w-5 rounded-full bg-current"></span>
                    <span class="block h-0.5 w-5 rounded-full bg-current"></span>
                </span>
            </button>
        </div>
    </div>
</header>

<!-- MOBILE SIDEBAR OVERLAY -->
<div
    v-show="mobileSidebarOpen"
    class="fixed inset-0 z-[60] bg-slate-950/55 backdrop-blur-sm lg:hidden"
    @click="mobileSidebarOpen = false"
></div>

<!-- MOBILE SIDEBAR -->
<aside
    class="fixed inset-y-0 left-0 z-[70] w-[88vw] max-w-sm transform border-r border-[color:var(--border)] bg-[var(--surface)] shadow-2xl shadow-slate-950/30 transition-transform duration-300 lg:hidden"
    :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    <div class="flex h-full flex-col">
        <!-- SIDEBAR HEADER -->
        <div class="flex items-center justify-between border-b border-[color:var(--border)] px-5 py-5">
            <a href="#" class="flex items-center gap-3" @click="mobileSidebarOpen = false">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-red-600 text-xl font-black text-white shadow-lg shadow-red-600/25">
                    L
                </div>

                <div class="leading-tight">
                    <p class="text-lg font-black tracking-[0.3em] text-[var(--text)]">LAUDA</p>
                    <p class="text-[10px] font-black tracking-[0.32em] text-red-600">API DIGITAL</p>
                </div>
            </a>

            <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-2xl border border-[color:var(--border)] bg-[var(--surface-soft)] text-[var(--text)]"
                @click="mobileSidebarOpen = false"
            >
                ✕
            </button>
        </div>

        <!-- PRIMARY NAV -->
        <div class="border-b border-[color:var(--border)] p-4">
            <p class="mb-3 px-2 text-[10px] font-black uppercase tracking-[0.28em] text-[var(--muted)]">
                Navegación
            </p>

            <div class="space-y-2">
                <a
                    href="#ecosistema"
                    class="flex items-center justify-between rounded-2xl bg-[var(--surface-soft)] px-4 py-3 text-sm font-black text-[var(--text)]"
                    @click="mobileSidebarOpen = false"
                >
                    <span>Ecosistema</span>
                    <span class="text-[var(--muted)]">→</span>
                </a>

                <a
                    href="#flujos"
                    class="flex items-center justify-between rounded-2xl bg-[var(--surface-soft)] px-4 py-3 text-sm font-black text-[var(--text)]"
                    @click="mobileSidebarOpen = false"
                >
                    <span>Flujos</span>
                    <span class="text-[var(--muted)]">→</span>
                </a>

                <a
                    href="#contacto"
                    class="flex items-center justify-between rounded-2xl bg-[var(--surface-soft)] px-4 py-3 text-sm font-black text-[var(--text)]"
                    @click="mobileSidebarOpen = false"
                >
                    <span>Contacto</span>
                    <span class="text-[var(--muted)]">→</span>
                </a>
            </div>
        </div>

        <!-- APPS -->
        <div class="flex-1 overflow-y-auto p-4">
            <div class="mb-4 px-2">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-red-600">
                    Soluciones
                </p>
                <h3 class="mt-1 text-xl font-black text-[var(--text)]">
                    Apps productivas
                </h3>
                <p class="mt-1 text-sm leading-6 text-[var(--muted)]">
                    Ambientes activos conectados dentro del ecosistema LaudaAPI.
                </p>
            </div>

            <div class="space-y-2">
                <a
                    v-for="app in productApps"
                    :key="`sidebar-${app.href}`"
                    :href="app.href"
                    class="group block rounded-2xl border border-[color:var(--border)] bg-[var(--surface-soft)] p-3 transition active:scale-[0.98]"
                    @click="mobileSidebarOpen = false"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border text-xl shadow-lg"
                            :class="appCardClasses[app.color]"
                        >
                            {{ app.icon }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <p class="truncate text-sm font-black text-[var(--text)]">
                                    {{ app.name }}
                                </p>
                                <span class="text-xs font-black text-red-600">↗</span>
                            </div>

                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-[var(--muted)]">
                                {{ app.description }}
                            </p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- SIDEBAR FOOTER -->
        <div class="border-t border-[color:var(--border)] p-4">
            <Link
                href="/login"
                class="flex w-full items-center justify-center rounded-2xl bg-[var(--text)] px-5 py-3 text-sm font-black text-[var(--page)] shadow-xl shadow-slate-950/15"
                @click="mobileSidebarOpen = false"
            >
                Iniciar sesión
            </Link>

            <p class="mt-4 text-center text-[11px] leading-5 text-[var(--muted)]">
                LaudaAPI Digital · Ecosistema operativo conectado
            </p>
        </div>
    </div>
</aside>


        <!-- HERO ARRIBA -->
        <section class="mx-auto max-w-[1520px] px-5 pt-5 lg:px-8 lg:pt-7">
    <div class="overflow-hidden rounded-[2rem] border border-[color:var(--border)] bg-[var(--surface)] p-5 shadow-2xl shadow-slate-950/5 lg:p-8">
        <div class="grid gap-7 lg:grid-cols-[1.15fr_0.85fr] lg:items-center">
            <div>
                <div class="mb-5 inline-flex w-fit items-center gap-2 rounded-full bg-red-50 px-3.5 py-1.5 text-[10px] font-black uppercase tracking-[0.26em] text-red-600">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    Un solo ambiente
                </div>

                <h1 class="max-w-5xl text-4xl font-black leading-[0.98] tracking-tight sm:text-5xl lg:text-6xl xl:text-7xl">
                    La operación completa de tu negocio,
                    <span class="text-red-600">conectada en un solo ambiente.</span>
                </h1>

                <p class="mt-5 max-w-3xl text-base leading-7 text-[var(--muted)] lg:text-lg">
                    LaudaAPI conecta ventas, POS, e-CF, delivery, compras, cumplimiento y monitoreo para que cada área trabaje sobre una misma verdad operativa.
                </p>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a
                        href="#ecosistema"
                        class="inline-flex items-center justify-center gap-3 rounded-2xl bg-red-600 px-6 py-3.5 text-sm font-black text-white shadow-xl shadow-red-600/25 transition hover:-translate-y-0.5 hover:bg-red-700"
                    >
                        Ver ecosistema
                        <span>→</span>
                    </a>

                    <a
                        href="#contacto"
                        class="inline-flex items-center justify-center rounded-2xl border border-[color:var(--border)] bg-[var(--surface-soft)] px-6 py-3.5 text-sm font-black text-[var(--text)] transition hover:-translate-y-0.5"
                    >
                        Solicitar demo
                    </a>
                </div>
            </div>

            <div class="rounded-[1.5rem] border border-[color:var(--border)] bg-[var(--surface-soft)] p-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-[color:var(--border)] bg-[var(--surface)] p-4">
                        <p class="text-2xl font-black">10</p>
                        <p class="mt-1 text-[9px] font-black uppercase tracking-[0.22em] text-[var(--muted)]">Ambientes</p>
                    </div>

                    <div class="rounded-2xl bg-slate-950 p-4 text-white shadow-xl shadow-slate-950/20">
                        <p class="text-2xl font-black">4</p>
                        <p class="mt-1 text-[9px] font-black uppercase tracking-[0.22em] text-white/55">Flujos clave</p>
                    </div>

                    <div class="rounded-2xl border border-[color:var(--border)] bg-[var(--surface)] p-4">
                        <p class="text-2xl font-black">1</p>
                        <p class="mt-1 text-[9px] font-black uppercase tracking-[0.22em] text-[var(--muted)]">Ambiente</p>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-dashed border-red-500/25 bg-red-500/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-red-600">
                        Idea central
                    </p>
                    <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                        Social atrae, CRM convierte, POS opera, e-CF responde ante DGII, Delivery entrega y Status observa.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-3">
            <div class="rounded-2xl border border-[color:var(--border)] bg-[var(--surface-soft)] p-4">
                <p class="text-xl">🔁</p>
                <p class="mt-2 text-sm font-black">POS + e-CF integrado</p>
                <p class="mt-1 text-xs leading-5 text-[var(--muted)]">La venta operativa puede terminar en documento fiscal.</p>
            </div>

            <div class="rounded-2xl border border-[color:var(--border)] bg-[var(--surface-soft)] p-4">
                <p class="text-xl">🛒</p>
                <p class="mt-2 text-sm font-black">Pedidos ecommerce conectados</p>
                <p class="mt-1 text-xs leading-5 text-[var(--muted)]">El pedido web entra al flujo real de operación.</p>
            </div>

            <div class="rounded-2xl border border-[color:var(--border)] bg-[var(--surface-soft)] p-4">
                <p class="text-xl">🚚</p>
                <p class="mt-2 text-sm font-black">Delivery y operación en tiempo real</p>
                <p class="mt-1 text-xs leading-5 text-[var(--muted)]">Entrega, evidencia y eventos monitoreados.</p>
            </div>
        </div>
    </div>
</section>

        <!-- FLUJO ABAJO FULL WIDTH -->
        <section id="ecosistema" class="mx-auto max-w-[1520px] px-5 py-8 lg:px-8 lg:py-12">
            <div class="relative overflow-hidden rounded-[2rem] bg-[#071022] p-5 text-white shadow-2xl shadow-slate-950/25 ring-1 ring-white/10 lg:p-8">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(239,68,68,0.22),transparent_32%),linear-gradient(rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[size:100%_100%,36px_36px,36px_36px]"></div>
                <div class="absolute -top-40 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-red-500/10 blur-3xl"></div>

                <div class="relative z-10">
                    <div class="mx-auto mb-8 flex w-fit items-center gap-3 rounded-b-[2rem] border border-white/10 bg-white/5 px-8 py-4 text-[11px] font-black uppercase tracking-[0.38em] text-white/80">
                        <span class="h-2 w-2 rounded-full bg-pink-500"></span>
                        Ecosistema LaudaAPI
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        En vivo
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[1fr_1.25fr_1fr_1fr]">
                        <div class="space-y-6">
                            <div
                                v-for="app in leftApps"
                                :key="app.name"
                                class="relative rounded-2xl border p-5 shadow-2xl backdrop-blur"
                                :class="appCardClasses[app.color]"
                            >
                                <span class="hidden absolute right-[-13px] top-1/2 h-6 w-6 -translate-y-1/2 rounded-full border-4 border-[#071022] bg-current shadow-lg xl:block"></span>

                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-current/20 text-2xl">
                                        {{ app.icon }}
                                    </div>
                                    <div>
                                        <p class="text-lg font-black text-white">{{ app.name }}</p>
                                        <p class="mt-1 text-sm leading-6 text-white/70">{{ app.text }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="relative flex min-h-[430px] items-center justify-center">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="core-ring h-80 w-80 rounded-full border border-red-400/30"></div>
                            </div>

                            <div class="hidden xl:block absolute left-0 top-[31%] h-px w-[32%] border-t border-dashed border-pink-400/70"></div>
                            <div class="hidden xl:block absolute left-0 top-[50%] h-px w-[32%] border-t border-dashed border-purple-400/70"></div>
                            <div class="hidden xl:block absolute left-0 top-[69%] h-px w-[32%] border-t border-dashed border-blue-400/70"></div>

                            <div class="hidden xl:block absolute right-0 top-[31%] h-px w-[32%] border-t border-dashed border-green-400/70"></div>
                            <div class="hidden xl:block absolute right-0 top-[50%] h-px w-[32%] border-t border-dashed border-amber-400/70"></div>
                            <div class="hidden xl:block absolute right-0 top-[69%] h-px w-[32%] border-t border-dashed border-orange-400/70"></div>

                            <div class="pulse-dot hidden xl:block absolute left-[15%] top-[31%] h-2 w-2 rounded-full bg-pink-400 shadow-lg shadow-pink-400"></div>
                            <div class="pulse-dot pulse-delay-1 hidden xl:block absolute left-[15%] top-[50%] h-2 w-2 rounded-full bg-purple-400 shadow-lg shadow-purple-400"></div>
                            <div class="pulse-dot pulse-delay-2 hidden xl:block absolute left-[15%] top-[69%] h-2 w-2 rounded-full bg-blue-400 shadow-lg shadow-blue-400"></div>

                            <div class="relative z-10 flex h-64 w-64 flex-col items-center justify-center rounded-full border border-red-300/30 bg-red-600/10 text-center shadow-[0_0_90px_rgba(239,68,68,0.35)] backdrop-blur-xl">
                                <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-red-600 text-2xl font-black shadow-xl shadow-red-600/35">
                                    L
                                </div>
                                <p class="text-2xl font-black tracking-wide">LAUDAAPI CORE</p>
                                <p class="mt-2 text-[11px] font-black uppercase tracking-[0.3em] text-red-300">API-First Environment</p>
                                <p class="mt-5 max-w-[170px] text-sm leading-6 text-white/75">
                                    Conecta eventos. Orquesta procesos.
                                </p>
                            </div>

                            <div class="absolute bottom-0 left-1/2 w-60 -translate-x-1/2 rounded-2xl border border-blue-400/30 bg-blue-500/10 p-4 text-center text-blue-200 shadow-xl shadow-blue-500/10">
                                <p class="text-2xl">〰️</p>
                                <p class="mt-1 font-black text-white">Status</p>
                                <p class="mt-1 text-xs leading-5 text-white/65">Observa disponibilidad, eventos y salud operativa.</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div
                                v-for="app in rightApps"
                                :key="app.name"
                                class="relative rounded-2xl border p-5 shadow-2xl backdrop-blur"
                                :class="appCardClasses[app.color]"
                            >
                                <span class="hidden absolute left-[-13px] top-1/2 h-6 w-6 -translate-y-1/2 rounded-full border-4 border-[#071022] bg-current shadow-lg xl:block"></span>

                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-current/20 text-2xl">
                                        {{ app.icon }}
                                    </div>
                                    <div>
                                        <p class="text-lg font-black text-white">{{ app.name }}</p>
                                        <p class="mt-1 text-sm leading-6 text-white/70">{{ app.text }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="app in backofficeApps"
                                :key="app.name"
                                class="rounded-2xl border p-4 shadow-2xl backdrop-blur"
                                :class="appCardClasses[app.color]"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-current/20 text-xl">
                                        {{ app.icon }}
                                    </div>
                                    <div>
                                        <p class="font-black text-white">{{ app.name }}</p>
                                        <p class="mt-1 text-xs leading-5 text-white/65">{{ app.text }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-5 xl:grid-cols-[1fr_1.55fr]">
                        <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                            <div class="mb-4 flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.32em] text-white/80">
                                <span class="text-red-400">⌁</span>
                                Transmisión en vivo
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="item in liveFeed"
                                    :key="`${item[0]}-${item[1]}`"
                                    class="grid grid-cols-[72px_82px_1fr_12px] items-center gap-3 text-xs"
                                >
                                    <span class="font-mono text-white/35">{{ item[0] }}</span>
                                    <span class="font-bold text-white">{{ item[1] }}</span>
                                    <span class="truncate text-white/65">{{ item[2] }}</span>
                                    <span class="h-2 w-2 rounded-full bg-green-400 shadow-lg shadow-green-400/40"></span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                            <div class="mb-4 text-[11px] font-black uppercase tracking-[0.32em] text-white/80">
                                Estado operativo
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div
                                    v-for="metric in metrics"
                                    :key="metric.label"
                                    class="rounded-2xl border border-white/10 bg-white/[0.05] p-5"
                                >
                                    <p class="text-2xl">{{ metric.icon }}</p>
                                    <p class="mt-4 text-sm text-white/65">{{ metric.label }}</p>
                                    <p class="mt-2 text-2xl font-black text-white">{{ metric.value }}</p>
                                    <p class="mt-2 text-xs font-bold text-green-300">{{ metric.note }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FLUJOS -->
        <section id="flujos" class="mx-auto max-w-[1520px] px-5 pb-14 lg:px-8">
            <div class="rounded-[2rem] border border-[color:var(--border)] bg-[var(--surface)] p-6 shadow-2xl shadow-slate-950/5 lg:p-8">
                <div class="grid gap-6 lg:grid-cols-[220px_1fr]">
                    <div class="flex flex-col justify-center">
                        <p class="text-[11px] font-black uppercase tracking-[0.32em] text-red-600">Flujos reales</p>
                        <h2 class="mt-4 text-4xl font-black leading-tight tracking-tight">
                            Flujos que conectamos
                        </h2>
                        <div class="mt-5 h-1 w-14 rounded-full bg-red-600"></div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="flow in flows"
                            :key="flow.title"
                            class="rounded-3xl border border-[color:var(--border)] bg-[var(--surface-soft)] p-6 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-950/10"
                        >
                            <h3 class="text-lg font-black">{{ flow.title }}</h3>

                            <div class="mt-6 flex flex-wrap items-center gap-2">
                                <template v-for="(step, index) in flow.steps" :key="`${flow.title}-${step}`">
                                    <span
                                        class="rounded-full px-3 py-2 text-xs font-black"
                                        :class="stepClasses[step] || 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ step }}
                                    </span>
                                    <span v-if="index < flow.steps.length - 1" class="text-[var(--muted)]">→</span>
                                </template>
                            </div>

                            <p class="mt-5 text-sm leading-6 text-[var(--muted)]">
                                {{ flow.description }}
                            </p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section id="contacto" class="mx-auto max-w-[1520px] px-5 pb-10 lg:px-8">
            <div class="overflow-hidden rounded-[2rem] bg-slate-950 p-8 text-white shadow-2xl shadow-slate-950/20 lg:p-10">
                <div class="grid gap-10 lg:grid-cols-[1fr_0.9fr] lg:items-center">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.36em] text-red-400">
                            Operación conectada
                        </p>

                        <h2 class="mt-4 max-w-4xl text-4xl font-black leading-tight tracking-tight lg:text-5xl">
                            Del primer contacto a la entrega final, cada paso queda conectado.
                        </h2>

                        <p class="mt-5 max-w-3xl text-lg leading-8 text-white/65">
                            LaudaAPI ayuda a que tus equipos trabajen con menos reprocesos: ventas puede captar y convertir, operación puede facturar y despachar, fiscal puede responder ante DGII, delivery puede entregar y Status puede observar todo el recorrido.
                        </p>

                        <div class="mt-8 flex flex-col gap-4 sm:flex-row">
                            <a
                                href="mailto:contacto@laudaapi.com"
                                class="inline-flex items-center justify-center rounded-2xl bg-white px-8 py-4 text-sm font-black text-slate-950 transition hover:-translate-y-0.5 hover:bg-slate-100"
                            >
                                Solicitar demo →
                            </a>

                            <a
                                href="#ecosistema"
                                class="inline-flex items-center justify-center rounded-2xl border border-white/15 bg-white/5 px-8 py-4 text-sm font-black text-white transition hover:-translate-y-0.5 hover:bg-white/10"
                            >
                                Ver ecosistema
                            </a>
                        </div>
                    </div>

                    <div class="grid gap-4">
                        <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-500/15 text-2xl">
                                    🔗
                                </div>

                                <div>
                                    <p class="text-lg font-black">Menos sistemas aislados</p>
                                    <p class="mt-2 text-sm leading-6 text-white/60">
                                        Las apps se comunican por eventos y cada proceso llega al ambiente que debe ejecutarlo.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-green-500/15 text-2xl">
                                    ✅
                                </div>

                                <div>
                                    <p class="text-lg font-black">Responsabilidades claras</p>
                                    <p class="mt-2 text-sm leading-6 text-white/60">
                                        CRM no factura, e-CF no opera ventas, Delivery no crea pedidos y Status no altera operaciones.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-500/15 text-2xl">
                                    📡
                                </div>

                                <div>
                                    <p class="text-lg font-black">Trazabilidad de punta a punta</p>
                                    <p class="mt-2 text-sm leading-6 text-white/60">
                                        Desde una oportunidad comercial hasta una factura, entrega o evento operativo monitoreado.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="mx-auto flex max-w-[1520px] flex-col gap-4 px-5 pb-8 text-xs text-[var(--muted)] lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div class="flex flex-wrap items-center gap-4">
                <span class="font-black tracking-[0.32em]">AMBIENTE LAUDA DIGITAL</span>
                <span>@laudaapi</span>
                <span>contacto@laudaapi.com</span>
            </div>

            <div class="flex flex-wrap gap-5">
                <a href="#" class="hover:text-[var(--text)]">Privacidad</a>
                <a href="#" class="hover:text-[var(--text)]">Términos</a>
                <a href="https://social.laudaapi.com" class="hover:text-[var(--text)]">Social</a>
                <a href="https://crm.laudaapi.com" class="hover:text-[var(--text)]">CRM</a>
                <a href="https://pos.laudaapi.com" class="hover:text-[var(--text)]">POS</a>
                <a href="https://status.laudaapi.com" class="hover:text-[var(--text)]">Status</a>
            </div>
        </footer>
    </main>
</template>

<style scoped>
.lauda-landing {
    --page: #f7f8fb;
    --surface: #ffffff;
    --surface-soft: #f8fafc;
    --nav: #ffffff;
    --text: #020617;
    --muted: #64748b;
    --border: rgba(203, 213, 225, 0.78);
}

/* Dark automático según el sistema */
@media (prefers-color-scheme: dark) {
    .lauda-landing {
        --page: #020617;
        --surface: #071022;
        --surface-soft: rgba(15, 23, 42, 0.82);
        --nav: #020617;
        --text: #f8fafc;
        --muted: #94a3b8;
        --border: rgba(148, 163, 184, 0.18);
    }
}

.core-ring {
    animation: core-spin 16s linear infinite;
    box-shadow: 0 0 90px rgba(239, 68, 68, 0.2);
}

.pulse-dot {
    animation: pulse-dot 2.6s ease-in-out infinite;
}

.pulse-delay-1 {
    animation-delay: 0.6s;
}

.pulse-delay-2 {
    animation-delay: 1.2s;
}

@keyframes core-spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

@keyframes pulse-dot {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.85);
    }

    50% {
        opacity: 1;
        transform: scale(1.45);
    }
}
</style>