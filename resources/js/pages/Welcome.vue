<script setup>
import BrandLogo from '@/components/BrandLogo.vue'
import { Button } from '@/components/ui/button'
import { Head, Link } from '@inertiajs/vue3'
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

import {
    Activity,
    ArrowRight,
    Boxes,
    Calculator,
    CheckCircle2,
    ChevronDown,
    FileText,
    Landmark,
    MessageCircle,
    Moon,
    RefreshCw,
    ShieldCheck,
    ShoppingCart,
    Store,
    Sun,
    TrendingUp,
    Truck,
    User,
    Users,
    Zap,
} from 'lucide-vue-next'

/* -------------------------------------------------------------------------- */
/*  Datos del ecosistema                                                       */
/* -------------------------------------------------------------------------- */

const CORE = { x: 40.5, y: 43 }

const nodes = [
    {
        id: 'social',
        label: 'Social',
        desc: 'Captura conversaciones y oportunidades.',
        color: '#EC4899',
        icon: MessageCircle,
        x: 13,
        y: 22,
        group: 'left',
    },
    {
        id: 'crm',
        label: 'CRM',
        desc: 'Convierte oportunidades en solicitudes reales.',
        color: '#A855F7',
        icon: Users,
        x: 13,
        y: 45,
        group: 'left',
    },
    {
        id: 'ecommerce',
        label: 'Ecommerce',
        desc: 'Recibe pedidos y solicitudes online.',
        color: '#3B82F6',
        icon: ShoppingCart,
        x: 13,
        y: 68,
        group: 'left',
    },
    {
        id: 'pos',
        label: 'POS',
        desc: 'Opera ventas, inventario, cobros y despacho.',
        color: '#22C55E',
        icon: Store,
        x: 63.5,
        y: 20,
        group: 'right',
    },
    {
        id: 'ecf',
        label: 'e-CF',
        desc: 'Firma, envía y responde ante DGII.',
        color: '#F59E0B',
        icon: FileText,
        x: 63.5,
        y: 43,
        group: 'right',
    },
    {
        id: 'delivery',
        label: 'Delivery',
        desc: 'Asigna, entrega y registra evidencia.',
        color: '#F97316',
        icon: Truck,
        x: 63.5,
        y: 66,
        group: 'right',
    },
    {
        id: 'cumplimiento',
        label: 'Cumplimiento',
        desc: 'Obligaciones, validaciones y vencimientos.',
        color: '#14B8A6',
        icon: ShieldCheck,
        x: 87.5,
        y: 20,
        group: 'backoffice',
        target: 'ecf',
    },
    {
        id: 'bys',
        label: 'BYS',
        desc: 'Compras, servicios, gastos y proveedores.',
        color: '#8B5CF6',
        icon: Boxes,
        x: 87.5,
        y: 38,
        group: 'backoffice',
        target: 'ecf',
    },
    {
        id: 'bancos',
        label: 'Bancos',
        desc: 'Conciliación y movimientos financieros.',
        color: '#3B82F6',
        icon: Landmark,
        x: 87.5,
        y: 56,
        group: 'backoffice',
        target: 'ecf',
    },
    {
        id: 'contabilidad',
        label: 'Contabilidad',
        desc: 'Asientos contables y reportes financieros.',
        color: '#22C55E',
        icon: Calculator,
        x: 87.5,
        y: 74,
        group: 'backoffice',
        target: 'ecf',
    },
    {
        id: 'status',
        label: 'Status',
        desc: 'Disponibilidad, DGII, APIs y salud operativa.',
        color: '#3B82F6',
        icon: Activity,
        x: 40.5,
        y: 78,
        group: 'status',
    },
]

const chips = [
    { icon: RefreshCw, text: 'POS como fuente de verdad', color: '#F5333C' },
    { icon: FileText, text: 'e-CF responde automáticamente', color: '#F59E0B' },
    { icon: Activity, text: 'Status monitorea DGII y APIs', color: '#3B82F6' },
]

const logs = [
    { time: '10:42:12', tag: 'Social', color: '#EC4899', msg: 'Lead captado desde Instagram' },
    { time: '10:42:15', tag: 'CRM', color: '#A855F7', msg: 'Oportunidad creada: #OP-4821' },
    { time: '10:42:18', tag: 'POS', color: '#22C55E', msg: 'Pedido operativo generado' },
    { time: '10:42:21', tag: 'e-CF', color: '#F59E0B', msg: 'Petición fiscal firmada y enviada a DGII' },
    { time: '10:42:24', tag: 'Cumplimiento', color: '#14B8A6', msg: 'Documento clasificado para trazabilidad fiscal' },
    { time: '10:42:28', tag: 'Status', color: '#3B82F6', msg: 'Endpoint DGII y respuesta registrados' },
]

const metrics = [
    {
        icon: CheckCircle2,
        iconColor: '#22C55E',
        label: 'Ambientes proyectados',
        value: '20+',
        sub: 'Core + módulos',
        subColor: '#22C55E',
    },
    {
        icon: ShieldCheck,
        iconColor: '#F59E0B',
        label: 'DGII / e-CF',
        value: 'Automático',
        sub: 'Firma, envía y responde',
        subColor: '#F59E0B',
    },
    {
        icon: TrendingUp,
        iconColor: '#A855F7',
        label: 'Operación',
        value: 'POS Core',
        sub: 'Ventas, cobros e inventario',
        subColor: '#22C55E',
    },
    {
        icon: Zap,
        iconColor: '#3B82F6',
        label: 'Eventos',
        value: 'API-first',
        sub: 'En tiempo real',
        subColor: '#3B82F6',
    },
]

const flows = [
    {
        title: 'Lead a factura y entrega',
        desc: 'Una conversación se convierte en venta, factura fiscal, despacho y evidencia de entrega.',
        steps: [
            { label: 'Social', color: '#EC4899', icon: MessageCircle },
            { label: 'CRM', color: '#A855F7', icon: Users },
            { label: 'POS', color: '#22C55E', icon: Store },
            { label: 'e-CF', color: '#F59E0B', icon: FileText },
            { label: 'Delivery', color: '#F97316', icon: Truck },
        ],
    },
    {
        title: 'Pedido ecommerce a operación',
        desc: 'El pedido online entra al POS sin duplicar facturación, inventario, CxC ni conduces.',
        steps: [
            { label: 'Ecommerce', color: '#3B82F6', icon: ShoppingCart },
            { label: 'POS', color: '#22C55E', icon: Store },
            { label: 'Inventario', color: '#22C55E', icon: Boxes },
            { label: 'e-CF', color: '#F59E0B', icon: FileText },
            { label: 'Delivery', color: '#F97316', icon: Truck },
        ],
    },
    {
        title: 'Compra a cumplimiento',
        desc: 'Compras, XML/PDF, validaciones fiscales y metadata contable preparada para reportes.',
        steps: [
            { label: 'BYS', color: '#8B5CF6', icon: Boxes },
            { label: 'e-CF', color: '#F59E0B', icon: FileText },
            { label: 'Cumplimiento', color: '#14B8A6', icon: ShieldCheck },
            { label: 'Contabilidad', color: '#22C55E', icon: Calculator },
            { label: 'Bancos', color: '#3B82F6', icon: Landmark },
        ],
    },
    {
        title: 'Operación monitoreada',
        desc: 'Status observa eventos, disponibilidad, endpoints, DGII y salud de todo el ecosistema.',
        steps: [
            { label: 'Todas las apps', color: '#94A3B8', icon: RefreshCw },
            { label: 'Status', color: '#3B82F6', icon: Activity },
        ],
    },
]

const solutionsOpen = ref(false)
const solutionsMenuRef = ref(null)
const isDarkMode = ref(false)

const solutionProducts = [
    {
        name: 'Social',
        href: 'https://social.laudaapi.com',
        desc: 'Captación social, contenido, inbox y leads.',
        icon: MessageCircle,
        color: '#EC4899',
    },
    {
        name: 'CRM',
        href: 'https://crm.laudaapi.com',
        desc: 'Clientes, oportunidades y seguimiento comercial.',
        icon: Users,
        color: '#A855F7',
    },
    {
        name: 'POS',
        href: 'https://pos.laudaapi.com',
        desc: 'Ventas, inventario, cobros, rutas y despacho.',
        icon: Store,
        color: '#22C55E',
    },
    {
        name: 'e-CF',
        href: 'https://ecf.laudaapi.com',
        desc: 'Firma, envío, TrackId y respuesta ante DGII.',
        icon: FileText,
        color: '#F59E0B',
    },
    {
        name: 'Cumplimiento',
        href: 'https://cumplimiento.laudaapi.com',
        desc: 'Obligaciones, documentos y control fiscal.',
        icon: ShieldCheck,
        color: '#14B8A6',
    },
    {
        name: 'Status',
        href: 'https://status.laudaapi.com',
        desc: 'Monitoreo de DGII, APIs, caídas y eventos.',
        icon: Activity,
        color: '#3B82F6',
    },
    {
        name: 'RRHH',
        href: 'https://rrhh.laudaapi.com',
        desc: 'Recursos humanos, empleados y procesos internos.',
        icon: Users,
        color: '#0EA5E9',
    },
    {
        name: 'Tesorería',
        href: 'https://tesoreria.laudaapi.com',
        desc: 'Pagos, caja, bancos y flujo financiero.',
        icon: Landmark,
        color: '#10B981',
    },
    {
        name: 'Proyectos',
        href: 'https://proyectos.laudaapi.com',
        desc: 'Tareas, ejecución, entregables y avance.',
        icon: Boxes,
        color: '#6366F1',
    },
    {
        name: 'Eventos',
        href: 'https://eventos.laudaapi.com',
        desc: 'Eventos, actividades, invitados y operación.',
        icon: Activity,
        color: '#F43F5E',
    },
    {
        name: 'Transporte personal',
        href: 'https://transporte.laudaapi.com',
        desc: 'Rutas, unidades, pasajeros y movilidad interna.',
        icon: Truck,
        color: '#F97316',
    },
    {
        name: 'Servicios de grúas',
        href: 'https://gruas.laudaapi.com',
        desc: 'Asignación, asistencia, evidencia y servicios.',
        icon: Truck,
        color: '#EAB308',
    },
    {
        name: 'Loans',
        href: 'https://loans.laudaapi.com',
        desc: 'Préstamos, cuotas, cartera y cobranza.',
        icon: Calculator,
        color: '#22C55E',
    },
    {
        name: 'Dealers',
        href: 'https://dealers.laudaapi.com',
        desc: 'Inventario, ventas, financiamiento y clientes.',
        icon: Store,
        color: '#A855F7',
    },
    {
        name: 'BI',
        href: 'https://bi.laudaapi.com',
        desc: 'Dashboards, métricas, reporting e inteligencia.',
        icon: TrendingUp,
        color: '#3B82F6',
    },
]

const extendedModules = [
    { name: 'RRHH', desc: 'Recursos humanos, equipos, asistencia y procesos internos.', icon: Users, color: '#0EA5E9', relation: 'Consume operación y usuarios del ecosistema.' },
    { name: 'Tesorería', desc: 'Caja, pagos, bancos, conciliación y flujo financiero.', icon: Landmark, color: '#10B981', relation: 'Cruza cobros POS, bancos y contabilidad.' },
    { name: 'Proyectos', desc: 'Planificación, tareas, entregables y ejecución.', icon: Boxes, color: '#6366F1', relation: 'Puede nacer desde CRM o servicios vendidos en POS.' },
    { name: 'Eventos', desc: 'Gestión de eventos, invitados, ventas y operación.', icon: Activity, color: '#F43F5E', relation: 'Puede conectar Social, CRM, POS y facturación.' },
    { name: 'Transporte personal', desc: 'Rutas, unidades, pasajeros, horarios y evidencia.', icon: Truck, color: '#F97316', relation: 'Extiende logística sin tocar facturación.' },
    { name: 'Servicios de grúas', desc: 'Asignación de grúas, asistencia, tracking y cobro.', icon: Truck, color: '#EAB308', relation: 'Opera servicios conectados a POS y rutas.' },
    { name: 'Loans', desc: 'Préstamos, cartera, cuotas, mora y cobranza.', icon: Calculator, color: '#22C55E', relation: 'Se apoya en clientes, cobros, bancos y BI.' },
    { name: 'Dealers', desc: 'Inventario vehicular, clientes, ventas y financiamiento.', icon: Store, color: '#A855F7', relation: 'Integra CRM, POS, loans, e-CF y BI.' },
    { name: 'BI', desc: 'Dashboards, KPIs, analítica y toma de decisiones.', icon: TrendingUp, color: '#3B82F6', relation: 'Lee señales del ecosistema sin duplicar operación.' },
]

/* -------------------------------------------------------------------------- */
/*  Conectores del diagrama                                                    */
/* -------------------------------------------------------------------------- */

const diagram = ref(null)
const coreEl = ref(null)
const nodeRefs = ref([])
const lines = ref([])

const setNodeRef = (el, i) => {
    if (el) nodeRefs.value[ i ] = el
}

function getNodeElementById(id) {
    const index = nodes.findIndex((node) => node.id === id)

    if (index === -1) {
        return null
    }

    return nodeRefs.value[ index ] || null
}

function getCorePoint(box) {
    const core = coreEl.value.getBoundingClientRect()

    return {
        x: core.left + core.width / 2 - box.left,
        y: core.top + core.height / 2 - box.top,
    }
}

function getNodeConnectionPoint(node, rect, box, side = null) {
    const centerX = rect.left + rect.width / 2 - box.left
    const centerY = rect.top + rect.height / 2 - box.top

    if (side === 'left') {
        return {
            x: rect.left - box.left,
            y: centerY,
        }
    }

    if (side === 'right') {
        return {
            x: rect.right - box.left,
            y: centerY,
        }
    }

    if (side === 'top') {
        return {
            x: centerX,
            y: rect.top - box.top,
        }
    }

    if (side === 'bottom') {
        return {
            x: centerX,
            y: rect.bottom - box.top,
        }
    }

    if (node.group === 'left') {
        return {
            x: rect.right - box.left,
            y: centerY,
        }
    }

    if (node.group === 'right' || node.group === 'backoffice') {
        return {
            x: rect.left - box.left,
            y: centerY,
        }
    }

    if (node.group === 'status') {
        return {
            x: centerX,
            y: rect.top - box.top,
        }
    }

    return {
        x: centerX,
        y: centerY,
    }
}

function computeLines() {
    if (!diagram.value || !coreEl.value) return

    const box = diagram.value.getBoundingClientRect()
    const corePoint = getCorePoint(box)
    const ecfElement = getNodeElementById('ecf')
    const ecfRect = ecfElement?.getBoundingClientRect()

    lines.value = nodes
        .map((node, index) => {
            const el = nodeRefs.value[ index ]

            if (!el) {
                return null
            }

            const nodeRect = el.getBoundingClientRect()

            if (node.group === 'backoffice' && ecfRect) {
                const from = getNodeConnectionPoint(node, nodeRect, box, 'left')

                const to = {
                    x: ecfRect.right - box.left,
                    y: ecfRect.top + ecfRect.height / 2 - box.top,
                }

                const busX = to.x + Math.max(15, (from.x - to.x) * 0.42)

                return {
                    type: 'elbow',
                    d: `M ${from.x} ${from.y} H ${busX} V ${to.y} H ${to.x}`,
                    color: node.color,
                    group: node.group,
                }
            }

            const from = getNodeConnectionPoint(node, nodeRect, box)

            return {
                type: 'line',
                x1: from.x,
                y1: from.y,
                x2: corePoint.x,
                y2: corePoint.y,
                color: node.color,
                group: node.group,
            }
        })
        .filter(Boolean)
}

let rafId = null
function scheduleComputeLines() {
    if (rafId) return
    rafId = requestAnimationFrame(() => {
        rafId = null
        computeLines()
    })
}

function setPresentationMode(value) {
    isDarkMode.value = value
    window.localStorage.setItem('laudaapi-presentation-mode', value ? 'dark' : 'light')
}

function togglePresentationMode() {
    setPresentationMode(!isDarkMode.value)
}

let ro
function closeSolutionsOnOutsideClick(event) {
    if (!solutionsOpen.value) return

    const menu = solutionsMenuRef.value

    if (menu && !menu.contains(event.target)) {
        solutionsOpen.value = false
    }
}

onMounted(async () => {
    const savedMode = window.localStorage.getItem('laudaapi-presentation-mode')
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches

    setPresentationMode(savedMode ? savedMode === 'dark' : Boolean(prefersDark))

    await nextTick()
    computeLines()

    ro = new ResizeObserver(scheduleComputeLines)
    if (diagram.value) ro.observe(diagram.value)

    window.addEventListener('resize', scheduleComputeLines)
    window.addEventListener('pointerdown', closeSolutionsOnOutsideClick)
})

onBeforeUnmount(() => {
    ro && ro.disconnect()
    if (rafId) cancelAnimationFrame(rafId)
    window.removeEventListener('resize', scheduleComputeLines)
    window.removeEventListener('pointerdown', closeSolutionsOnOutsideClick)
})
</script>

<template>

    <Head title="LaudaAPI Digital">
        <meta name="description" content="LaudaAPI conecta Social, CRM, Ecommerce, POS, Delivery, e-CF, Cumplimiento, BYS, Bancos, Contabilidad, Status, RRHH, Tesorería, Proyectos, Eventos, Transporte, Grúas, Loans, Dealers y BI en un solo ambiente operativo API-first." />
    </Head>

    <div :class="[ 'lauda-page min-h-screen antialiased', { 'lauda-page--dark': isDarkMode } ]">
        <!-- ===================== NAV ===================== -->
        <nav class="lauda-nav sticky top-0 z-50 border-b backdrop-blur-xl">
            <div class="mx-auto flex h-[76px] max-w-none items-center gap-8 px-6 lg:px-8 2xl:px-10">
                <Link href="/" class="flex items-center gap-2.5">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-[#F5333C] font-black text-white shadow-xl shadow-[#F5333C]/35">
                        <BrandLogo class="h-6 w-6 text-white" />
                    </div>

                    <div class="leading-none">
                        <div class="text-[20px] font-extrabold tracking-tight text-[var(--text)]">LAUDA</div>
                        <div class="mt-0.5 text-[9px] font-semibold tracking-[0.2em] text-[#F5333C]">
                            API DIGITAL
                        </div>
                    </div>
                </Link>

                <div class="ml-auto hidden items-center gap-10 text-[15px] font-medium text-[var(--muted)] lg:flex">
                    <!-- SOLUCIONES MENU -->
                    <div ref="solutionsMenuRef" class="relative flex h-[76px] items-center">
                        <button type="button" class="flex items-center gap-1 transition-colors hover:text-[var(--text)]" :class="solutionsOpen && 'text-[var(--text)]'" @click.stop="solutionsOpen = !solutionsOpen">
                            Soluciones
                            <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="solutionsOpen && 'rotate-180'" />
                        </button>

                        <div v-show="solutionsOpen" class="lauda-menu absolute left-0 top-[68px] z-50 w-[720px] rounded-3xl border p-4 shadow-2xl">
                            <div class="mb-3 px-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#F5333C]">
                                    Soluciones LaudaAPI
                                </p>
                                <p class="mt-1 text-sm text-[var(--muted)]">
                                    Productos conectados dentro del ecosistema operativo.
                                </p>
                            </div>

                            <div class="grid max-h-[520px] grid-cols-2 gap-2 overflow-y-auto pr-1">
                                <a v-for="product in solutionProducts" :key="product.name" :href="product.href" class="lauda-menu-item group rounded-2xl border border-transparent p-3 transition" @click="solutionsOpen = false">
                                    <div class="flex items-start gap-3">
                                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" :style="{ background: product.color + '1a' }">
                                            <component :is="product.icon" class="h-5 w-5" :style="{ color: product.color }" />
                                        </span>

                                        <div class="min-w-0">
                                            <p class="text-sm font-black text-[var(--text)]">
                                                {{ product.name }}
                                            </p>
                                            <p class="mt-1 text-xs leading-5 text-[var(--muted)]">
                                                {{ product.desc }}
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <a href="#ecosistema" class="relative flex h-[76px] items-center text-[var(--text)] transition-colors after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-full after:bg-[#F5333C]">
                        Ecosistema
                    </a>

                    <a href="#flujos" class="flex h-[76px] items-center transition-colors hover:text-[var(--text)]">
                        Flujos
                    </a>

                    <a href="#modulos" class="flex h-[76px] items-center transition-colors hover:text-[var(--text)]">
                        Módulos
                    </a>

                    <a href="https://ecf.laudaapi.com" class="flex h-[76px] items-center transition-colors hover:text-[var(--text)]">
                        e-CF
                    </a>

                    <a href="#contacto" class="flex h-[76px] items-center transition-colors hover:text-[var(--text)]">
                        Contacto
                    </a>
                </div>

                <button type="button" class="lauda-mode-toggle ml-auto inline-flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-bold transition lg:ml-0" @click="togglePresentationMode">
                    <component :is="isDarkMode ? Sun : Moon" class="h-4 w-4" />
                    {{ isDarkMode ? 'Light mode' : 'Dark mode' }}
                </button>

                <Button class="hidden gap-2 rounded-xl bg-[#0B0B12] px-6 py-6 text-white hover:bg-black lg:inline-flex">
                    <User class="h-4 w-4" />
                    Iniciar sesión
                </Button>
            </div>
        </nav>

        <!-- ===================== HERO ===================== -->
        <section class="lauda-hero">
            <div class="lauda-hero__layout">
                <!-- Columna izquierda -->
                <div class="lauda-hero__copy">
                    <span class="inline-flex items-center gap-2 rounded-full bg-[#F5333C]/10 px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[#F5333C]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F5333C]" />
                        Ecosistema API-first
                    </span>

                    <h1 class="mt-6 text-[38px] font-extrabold leading-[1.02] tracking-[-0.04em] text-[var(--text)] sm:text-[48px] lg:text-[56px]">
                        Un solo ambiente para
                        <span class="text-[#F5333C]">vender, operar, facturar y cumplir.</span>
                    </h1>

                    <p class="mt-6 max-w-[560px] text-[17px] leading-relaxed text-[var(--muted)]">
                        LaudaAPI conecta Social, CRM, Ecommerce, POS, Delivery, e-CF, Cumplimiento,
                        Tesorería, RRHH, Proyectos, Dealers, Loans y BI. Cada módulo recibe eventos,
                        procesa su parte y responde al ecosistema sin duplicar la operación central.
                    </p>

                    <div class="mt-6 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-3 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2 text-[11px] font-black uppercase tracking-[0.12em] text-[var(--soft)]">
                            <span class="rounded-full bg-[#EC4899]/10 px-3 py-1 text-[#EC4899]">Social</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                            <span class="rounded-full bg-[#A855F7]/10 px-3 py-1 text-[#A855F7]">CRM</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                            <span class="rounded-full bg-[#22C55E]/10 px-3 py-1 text-[#22C55E]">POS</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                            <span class="rounded-full bg-[#F59E0B]/10 px-3 py-1 text-[#D97706]">e-CF</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                            <span class="rounded-full bg-[#14B8A6]/10 px-3 py-1 text-[#0F766E]">Cumplimiento</span>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Button class="gap-2 rounded-xl bg-[#F5333C] px-6 py-6 text-white hover:bg-[#d92730]">
                            Ver ecosistema
                            <ArrowRight class="h-4 w-4" />
                        </Button>

                        <Button variant="outline" class="lauda-outline-button rounded-xl px-6 py-6">
                            Solicitar demo
                        </Button>
                    </div>

                    <div class="lauda-hero__chips">
                        <div v-for="c in chips" :key="c.text" class="lauda-chip flex min-h-[66px] items-center gap-2.5 rounded-xl border px-3 py-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg" :style="{ background: c.color + '1a' }">
                                <component :is="c.icon" class="h-4 w-4" :style="{ color: c.color }" />
                            </span>

                            <span class="text-[12px] font-semibold leading-tight text-[var(--muted)]">
                                {{ c.text }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: panel oscuro -->
                <div id="ecosistema" class="lauda-hero__panel">
                    <!-- header -->
                    <div class="mb-3 flex flex-wrap items-center justify-center gap-3 text-center text-[11px] font-semibold uppercase tracking-[0.22em] text-[#7A8298]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F5333C]" />
                        Ecosistema LaudaAPI
                        <span class="h-1.5 w-1.5 rounded-full bg-[#22C55E]" />
                        En vivo
                        <span class="rounded-full border border-white/10 bg-white/5 px-2 py-0.5 normal-case tracking-normal text-[#B8C0D8]">
                            presentación {{ isDarkMode ? 'dark' : 'light' }}
                        </span>
                    </div>

                    <!-- Diagrama -->
                    <div class="lauda-diagram-scroll">
                        <div ref="diagram" class="relative h-[500px] w-full min-w-[640px] rounded-2xl bg-[#080B15]" style="background-image: radial-gradient(circle at 44% 43%, rgba(245,51,60,.11), transparent 48%), linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px); background-size: 100% 100%, 34px 34px, 34px 34px;">
                            <!-- conectores -->
                            <svg class="pointer-events-none absolute inset-0 h-full w-full" style="z-index:0">
                                <template v-for="(l, i) in lines" :key="i">
                                    <path v-if="l.type === 'elbow'" :d="l.d" :stroke="l.color" fill="none" stroke-width="1.5" stroke-dasharray="4 6" stroke-linecap="round" stroke-linejoin="round" class="flow-line" style="opacity:.55" />

                                    <line v-else :x1="l.x1" :y1="l.y1" :x2="l.x2" :y2="l.y2" :stroke="l.color" stroke-width="1.5" stroke-dasharray="4 6" stroke-linecap="round" class="flow-line" style="opacity:.55" />
                                </template>
                            </svg>

                            <!-- etiqueta -->
                            <div class="absolute left-1/2 top-5 z-10 -translate-x-1/2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-[9px] font-semibold uppercase tracking-[0.16em] text-[#8B8BA0]">
                                Eventos en tiempo real
                            </div>

                            <!-- CORE -->
                            <div ref="coreEl" class="core-glow absolute z-30 flex aspect-square w-[176px] -translate-x-1/2 -translate-y-1/2 flex-col items-center justify-center rounded-full border border-[#F5333C]/40 bg-[#0A0D18] text-center" :style="{ left: CORE.x + '%', top: CORE.y + '%' }">
                                <div class="mb-2 grid h-10 w-10 place-items-center rounded-xl bg-[#F5333C] font-black text-white">
                                    L
                                </div>

                                <div class="text-[16px] font-extrabold tracking-tight text-white">
                                    LAUDAAPI CORE
                                </div>

                                <div class="mt-0.5 text-[8.5px] font-bold uppercase tracking-[0.14em] text-[#F5333C]">
                                    API-First Environment
                                </div>

                                <div class="mt-2 text-[10px] leading-snug text-[#8B8BA0]">
                                    Conecta eventos.<br />
                                    Orquesta procesos.
                                </div>
                            </div>

                            <!-- nodos -->
                            <div v-for="(n, i) in nodes" :key="n.id" :ref="(el) => setNodeRef(el, i)" class="lauda-node absolute z-10 flex min-h-[68px] w-[178px] -translate-x-1/2 -translate-y-1/2 items-start gap-2.5 rounded-2xl border border-white/[0.07] bg-[#12172A] p-3 shadow-lg shadow-black/10" :class="n.group === 'backoffice' && 'w-[172px]'" :style="{ left: n.x + '%', top: n.y + '%' }">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg shadow-lg" :style="{ background: n.color }">
                                    <component :is="n.icon" class="h-4 w-4 text-white" />
                                </span>

                                <div class="min-w-0">
                                    <div class="truncate text-[12px] font-bold leading-tight text-white">
                                        {{ n.label }}
                                    </div>

                                    <div class="mt-0.5 text-[10px] leading-snug text-[#7A7A90]">
                                        {{ n.desc }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- panels -->
                    <div class="lauda-hero__status-grid">
                        <!-- transmisión -->
                        <div class="min-w-0 rounded-2xl border border-white/[0.07] bg-[#0D1120] p-4">
                            <div class="mb-3 flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#8B8BA0]">
                                <Activity class="h-3.5 w-3.5" />
                                Transmisión en vivo
                            </div>

                            <div class="space-y-2 font-mono text-[11px]">
                                <div v-for="log in logs" :key="log.time" class="flex min-w-0 items-center gap-3">
                                    <span class="shrink-0 text-[#4A4A5E]">{{ log.time }}</span>

                                    <span class="flex shrink-0 items-center gap-1.5 font-medium" :style="{ color: log.color }">
                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ background: log.color }" />
                                        {{ log.tag }}
                                    </span>

                                    <span class="min-w-0 flex-1 truncate text-[#9A9AB0]">{{ log.msg }}</span>
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#22C55E]" />
                                </div>
                            </div>
                        </div>

                        <!-- estado -->
                        <div class="min-w-0 rounded-2xl border border-white/[0.07] bg-[#0D1120] p-4">
                            <div class="mb-3 flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#8B8BA0]">
                                <ShieldCheck class="h-3.5 w-3.5" />
                                Estado operativo
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div v-for="m in metrics" :key="m.label" class="min-w-0 rounded-xl border border-white/6 bg-[#12172A] p-3">
                                    <component :is="m.icon" class="h-4 w-4" :style="{ color: m.iconColor }" />

                                    <div class="mt-2 text-[10px] text-[#7A7A90]">
                                        {{ m.label }}
                                    </div>

                                    <div class="truncate text-[15px] font-bold leading-tight text-white">
                                        {{ m.value }}
                                    </div>

                                    <div class="truncate text-[10px] font-medium" :style="{ color: m.subColor }">
                                        {{ m.sub }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== FLUJOS ===================== -->
        <section id="flujos" class="mx-auto max-w-[1440px] scroll-mt-24 px-4 py-10 sm:px-6 2xl:px-8">
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="f in flows" :key="f.title" class="lauda-card min-w-0 overflow-hidden rounded-3xl border p-6 transition-shadow hover:shadow-xl">
                    <h3 class="text-[17px] font-bold tracking-tight text-[var(--text)]">
                        {{ f.title }}
                    </h3>

                    <div class="mt-5 flex min-w-0 flex-wrap items-start gap-x-2 gap-y-4">
                        <template v-for="(s, i) in f.steps" :key="s.label">
                            <div class="flex min-w-11 max-w-16 flex-col items-center gap-2 text-center">
                                <span class="grid h-9 w-9 place-items-center rounded-full" :style="{ background: s.color + '1a' }">
                                    <component :is="s.icon" class="h-4 w-4" :style="{ color: s.color }" />
                                </span>

                                <span class="max-w-17 truncate text-[10px] font-medium text-[var(--muted)]">
                                    {{ s.label }}
                                </span>
                            </div>

                            <ArrowRight v-if="i < f.steps.length - 1" class="mt-3 h-3.5 w-3.5 shrink-0 text-[var(--soft)]" />
                        </template>
                    </div>

                    <p class="mt-5 text-[13px] leading-relaxed text-[var(--muted)]">
                        {{ f.desc }}
                    </p>
                </div>
            </div>
        </section>

        <!-- ===================== MÓDULOS EXTENDIDOS ===================== -->
        <section id="modulos" class="mx-auto max-w-[1440px] scroll-mt-24 px-4 pb-16 sm:px-6 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#F5333C]">
                        Módulos extendidos
                    </p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-[var(--text)] sm:text-4xl">
                        La misma base operativa para más verticales.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-[var(--muted)]">
                    Estos módulos se presentan como extensiones del ambiente LaudaAPI. Algunos operan como apps independientes,
                    pero siempre conectados al core, sin duplicar facturación, inventario, fiscalidad ni datos críticos.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div v-for="module in extendedModules" :key="module.name" class="lauda-card group rounded-3xl border p-5 transition-all hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl" :style="{ background: module.color + '1a' }">
                            <component :is="module.icon" class="h-5 w-5" :style="{ color: module.color }" />
                        </span>

                        <span class="rounded-full border border-[var(--border)] px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-[var(--soft)]">
                            Extensión
                        </span>
                    </div>

                    <h3 class="text-base font-black text-[var(--text)]">
                        {{ module.name }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-[var(--muted)]">
                        {{ module.desc }}
                    </p>

                    <div class="mt-4 rounded-2xl border border-[var(--border)] bg-[var(--surface-soft)] p-3 text-xs leading-relaxed text-[var(--muted)]">
                        {{ module.relation }}
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
/* -------------------------------------------------------------------------- */
/*  Paleta de presentación light / dark                                        */
/* -------------------------------------------------------------------------- */

.lauda-page {
    --page-bg: #fff7f2;
    --page-glow-a: rgba(245, 51, 60, 0.12);
    --page-glow-b: rgba(245, 158, 11, 0.14);
    --nav-bg: rgba(255, 247, 242, 0.9);
    --text: #0b0b12;
    --muted: #5a5a6b;
    --soft: #8e8e9e;
    --surface: rgba(255, 255, 255, 0.82);
    --surface-solid: #ffffff;
    --surface-soft: rgba(255, 255, 255, 0.54);
    --border: rgba(15, 23, 42, 0.08);
    --menu-bg: rgba(255, 255, 255, 0.96);
    min-height: 100vh;
    color: var(--text);
    background:
        radial-gradient(circle at 10% 8%, var(--page-glow-a), transparent 30%),
        radial-gradient(circle at 78% 12%, var(--page-glow-b), transparent 35%),
        linear-gradient(180deg, #fff7f2 0%, #fffaf6 48%, #fafaf8 100%);
}

.lauda-page--dark {
    --page-bg: #080a12;
    --page-glow-a: rgba(245, 51, 60, 0.18);
    --page-glow-b: rgba(59, 130, 246, 0.15);
    --nav-bg: rgba(8, 10, 18, 0.86);
    --text: #f8fafc;
    --muted: #a8b0c3;
    --soft: #74809a;
    --surface: rgba(15, 23, 42, 0.78);
    --surface-solid: #111827;
    --surface-soft: rgba(255, 255, 255, 0.045);
    --border: rgba(255, 255, 255, 0.09);
    --menu-bg: rgba(15, 23, 42, 0.96);
    background:
        radial-gradient(circle at 10% 8%, var(--page-glow-a), transparent 30%),
        radial-gradient(circle at 78% 12%, var(--page-glow-b), transparent 35%),
        linear-gradient(180deg, #080a12 0%, #0b1020 46%, #070912 100%);
}

.lauda-nav {
    border-color: var(--border);
    background: var(--nav-bg);
}

.lauda-menu {
    border-color: var(--border);
    background: var(--menu-bg);
    box-shadow: 0 30px 80px rgba(2, 6, 23, 0.18);
}

.lauda-menu-item:hover {
    border-color: var(--border);
    background: var(--surface-soft);
}

.lauda-mode-toggle {
    border-color: var(--border);
    color: var(--text);
    background: var(--surface);
}

.lauda-mode-toggle:hover {
    border-color: rgba(245, 51, 60, 0.25);
    color: #f5333c;
}

.lauda-outline-button {
    border: 1px solid var(--border) !important;
    color: var(--text) !important;
    background: var(--surface) !important;
}

.lauda-outline-button:hover {
    background: var(--surface-solid) !important;
}

.lauda-card,
.lauda-chip {
    border-color: var(--border);
    background: var(--surface);
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.05);
}

.lauda-page--dark .lauda-card,
.lauda-page--dark .lauda-chip {
    box-shadow: 0 20px 70px rgba(0, 0, 0, 0.28);
}

/* -------------------------------------------------------------------------- */
/*  Hero layout responsivo y panel proporcional                                */
/* -------------------------------------------------------------------------- */

.lauda-hero {
    width: 100%;
    max-width: 1440px;
    margin-inline: auto;
    padding: 42px 16px 0;
    box-sizing: border-box;
}

.lauda-hero__layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px;
    align-items: center;
}

.lauda-hero__copy {
    width: 100%;
    max-width: 620px;
    min-width: 0;
}

.lauda-hero__chips {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    max-width: 620px;
    margin-top: 36px;
}

.lauda-hero__panel {
    width: 100%;
    max-width: 760px;
    min-width: 0;
    justify-self: center;
    overflow: hidden;
    scroll-margin-top: 96px;
    border-radius: 26px;
    border: 1px solid rgba(255, 255, 255, 0.07);
    background: linear-gradient(180deg, #0a0d18 0%, #070a13 100%);
    padding: 18px;
    box-shadow: 0 28px 70px -18px rgba(2, 6, 23, 0.45);
}

.lauda-page--dark .lauda-hero__panel {
    border-color: rgba(245, 51, 60, 0.14);
    box-shadow:
        0 30px 90px -22px rgba(245, 51, 60, 0.28),
        0 25px 80px rgba(0, 0, 0, 0.44);
}

.lauda-diagram-scroll {
    margin-inline: -4px;
    overflow-x: auto;
    padding-inline: 4px;
    padding-bottom: 4px;
    -webkit-overflow-scrolling: touch;
}

.lauda-hero__status-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-top: 16px;
}

@media (min-width: 640px) {
    .lauda-hero {
        padding-inline: 24px;
    }
}

@media (min-width: 900px) {
    .lauda-hero__status-grid {
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
    }
}

@media (min-width: 1200px) {
    .lauda-hero__layout {
        grid-template-columns: minmax(460px, 560px) minmax(0, 760px);
        gap: 48px;
        justify-content: space-between;
    }

    .lauda-hero__panel {
        justify-self: end;
    }
}

@media (min-width: 1536px) {
    .lauda-hero {
        padding-inline: 32px;
    }

    .lauda-hero__layout {
        grid-template-columns: minmax(500px, 600px) minmax(0, 800px);
    }

    .lauda-hero__panel {
        max-width: 800px;
    }
}

/* -------------------------------------------------------------------------- */
/*  Interacción de nodos                                                       */
/* -------------------------------------------------------------------------- */

.lauda-node {
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.lauda-node:hover {
    border-color: rgba(255, 255, 255, 0.2);
    background-color: #161c33;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
}

/* -------------------------------------------------------------------------- */
/*  Animaciones                                                                */
/* -------------------------------------------------------------------------- */

@keyframes dashflow {
    to {
        stroke-dashoffset: -16;
    }
}

.flow-line {
    animation: dashflow 1s linear infinite;
}

@keyframes corepulse {

    0%,
    100% {
        box-shadow:
            0 0 60px -12px rgba(245, 51, 60, 0.55),
            inset 0 0 36px rgba(245, 51, 60, 0.12);
    }

    50% {
        box-shadow:
            0 0 90px -6px rgba(245, 51, 60, 0.8),
            inset 0 0 48px rgba(245, 51, 60, 0.22);
    }
}

.core-glow {
    animation: corepulse 3s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {

    .flow-line,
    .core-glow {
        animation: none;
    }

    .lauda-node {
        transition: none;
    }
}
</style>
