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
    RefreshCw,
    ShieldCheck,
    ShoppingCart,
    Store,
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
        desc: 'Organiza obligaciones, validaciones y vencimientos.',
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
        desc: 'Procura, recibe y gestiona compras y gastos.',
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
        desc: 'Observa disponibilidad, eventos y salud operativa.',
        color: '#3B82F6',
        icon: Activity,
        x: 40.5,
        y: 82,
        group: 'status',
    },
]

const chips = [
    { icon: RefreshCw, text: 'POS + e-CF integrado', color: '#F5333C' },
    { icon: ShoppingCart, text: 'Pedidos ecommerce conectados', color: '#A855F7' },
    { icon: Truck, text: 'Delivery y operación en tiempo real', color: '#22C55E' },
]

const logs = [
    { time: '10:42:12', tag: 'Social', color: '#EC4899', msg: 'Lead captado desde Instagram' },
    { time: '10:42:15', tag: 'CRM', color: '#A855F7', msg: 'Oportunidad creada: #OP-4821' },
    { time: '10:42:18', tag: 'POS', color: '#22C55E', msg: 'Pedido generado: #PED-8842' },
    { time: '10:42:21', tag: 'e-CF', color: '#F59E0B', msg: 'Factura firmada y enviada a DGII' },
    { time: '10:42:24', tag: 'Delivery', color: '#F97316', msg: 'Entrega asignada al conductor' },
    { time: '10:42:28', tag: 'Status', color: '#3B82F6', msg: 'Evento registrado correctamente' },
]

const metrics = [
    {
        icon: CheckCircle2,
        iconColor: '#22C55E',
        label: 'Ambientes activos',
        value: '10 / 10',
        sub: '100% disponibles',
        subColor: '#22C55E',
    },
    {
        icon: ShieldCheck,
        iconColor: '#22C55E',
        label: 'DGII',
        value: 'Disponible',
        sub: 'Sin interrupciones',
        subColor: '#22C55E',
    },
    {
        icon: TrendingUp,
        iconColor: '#A855F7',
        label: 'Procesos hoy',
        value: '1,248',
        sub: '+12% vs ayer',
        subColor: '#22C55E',
    },
    {
        icon: Zap,
        iconColor: '#3B82F6',
        label: 'Eventos procesados',
        value: '4,892',
        sub: 'En tiempo real',
        subColor: '#3B82F6',
    },
]

const flows = [
    {
        title: 'Lead a factura y entrega',
        desc: 'Una conversación se convierte en venta, factura fiscal y entrega.',
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
        desc: 'El pedido online entra a operación real sin duplicar procesos.',
        steps: [
            { label: 'Ecommerce', color: '#3B82F6', icon: ShoppingCart },
            { label: 'POS', color: '#22C55E', icon: Store },
            { label: 'Inventario', color: '#22C55E', icon: Boxes },
            { label: 'e-CF', color: '#F59E0B', icon: FileText },
            { label: 'Delivery', color: '#F97316', icon: Truck },
        ],
    },
    {
        title: 'Compra a contabilidad',
        desc: 'Las compras se procesan, validan y registran hasta el asiento contable.',
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
        desc: 'Status observa eventos, disponibilidad y salud de todo el ecosistema.',
        steps: [
            { label: 'Todas las apps', color: '#94A3B8', icon: RefreshCw },
            { label: 'Status', color: '#3B82F6', icon: Activity },
        ],
    },
]

const navLinks = ['Soluciones', 'Ecosistema', 'Flujos', 'e-CF', 'Contacto']

const solutionsOpen = ref(false)
const solutionsMenuRef = ref(null)

const solutionProducts = [
    {
        name: 'Social',
        href: 'https://social.laudaapi.com',
        desc: 'Captación social y conversaciones.',
        icon: MessageCircle,
        color: '#EC4899',
    },
    {
        name: 'CRM',
        href: 'https://crm.laudaapi.com',
        desc: 'Clientes, oportunidades y seguimiento.',
        icon: Users,
        color: '#A855F7',
    },
    {
        name: 'POS',
        href: 'https://pos.laudaapi.com',
        desc: 'Ventas, inventario, cobros y despacho.',
        icon: Store,
        color: '#22C55E',
    },
    {
        name: 'e-CF',
        href: 'https://ecf.laudaapi.com',
        desc: 'Firma, envío y respuesta ante DGII.',
        icon: FileText,
        color: '#F59E0B',
    },
    // {
    //     name: 'Delivery',
    //     href: 'https://delivery.laudaapi.com',
    //     desc: 'Rutas, entregas y evidencia.',
    //     icon: Truck,
    //     color: '#F97316',
    // },
    {
        name: 'Cumplimiento',
        href: 'https://cumplimiento.laudaapi.com',
        desc: 'Obligaciones y control fiscal.',
        icon: ShieldCheck,
        color: '#14B8A6',
    },
    {
        name: 'Status',
        href: 'https://status.laudaapi.com',
        desc: 'Monitoreo y salud operativa.',
        icon: Activity,
        color: '#3B82F6',
    },
    // {
    //     name: 'BYS',
    //     href: 'https://bys.laudaapi.com',
    //     desc: 'Compras, gastos y servicios.',
    //     icon: Boxes,
    //     color: '#8B5CF6',
    // },
    // {
    //     name: 'Bancos',
    //     href: 'https://bancos.laudaapi.com',
    //     desc: 'Pagos, bancos y conciliación.',
    //     icon: Landmark,
    //     color: '#3B82F6',
    // },
    // {
    //     name: 'Contabilidad',
    //     href: 'https://contabilidad.laudaapi.com',
    //     desc: 'Asientos y reportes contables.',
    //     icon: Calculator,
    //     color: '#22C55E',
    // },
]

/* -------------------------------------------------------------------------- */
/*  Conectores del diagrama                                                    */
/* -------------------------------------------------------------------------- */

const diagram = ref(null)
const coreEl = ref(null)
const nodeRefs = ref([])
const lines = ref([])

const setNodeRef = (el, i) => {
    if (el) nodeRefs.value[i] = el
}

function getNodeElementById(id) {
    const index = nodes.findIndex((node) => node.id === id)

    if (index === -1) {
        return null
    }

    return nodeRefs.value[index] || null
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
            const el = nodeRefs.value[index]

            if (!el) {
                return null
            }

            const nodeRect = el.getBoundingClientRect()

            /*
             * Backoffice:
             * Cumplimiento, BYS, Bancos y Contabilidad conectan hacia e-CF
             * con línea tipo codo, no línea directa.
             */
            if (node.group === 'backoffice' && ecfRect) {
                const from = getNodeConnectionPoint(node, nodeRect, box, 'left')

                const to = {
                    x: ecfRect.right - box.left,
                    y: ecfRect.top + ecfRect.height / 2 - box.top,
                }

                /*
                 * Bus vertical entre e-CF y las cards de backoffice.
                 * Esto crea el codo visual:
                 * backoffice -> horizontal -> vertical -> horizontal -> e-CF
                 */
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

let ro
function closeSolutionsOnOutsideClick(event) {
    if (!solutionsOpen.value) return

    const menu = solutionsMenuRef.value

    if (menu && !menu.contains(event.target)) {
        solutionsOpen.value = false
    }
}
onMounted(async () => {
    await nextTick()
    computeLines()

    ro = new ResizeObserver(computeLines)
    ro.observe(diagram.value)

    window.addEventListener('resize', computeLines)
    window.addEventListener('pointerdown', closeSolutionsOnOutsideClick)
})

onBeforeUnmount(() => {
    ro && ro.disconnect()
    window.removeEventListener('resize', computeLines)
    window.removeEventListener('pointerdown', closeSolutionsOnOutsideClick)
})
</script>

<template>
    <Head title="LaudaAPI Digital" />

    <div class="min-h-screen bg-[#F4F4F6] text-[#0B0B12] antialiased">
        <!-- ===================== NAV ===================== -->
        <nav class="sticky top-0 z-50 border-b border-black/5 bg-[#F4F4F6]/88 backdrop-blur-xl">
            <div class="mx-auto flex h-[76px] max-w-none items-center gap-8 px-8 2xl:px-10">
                <Link href="/" class="flex items-center gap-2.5">
                    <div class="grid h-11 w-11 place-items-center rounded-[12px] bg-[#F5333C] font-black text-white shadow-xl shadow-[#F5333C]/35">
                        <BrandLogo class="h-6 w-6 text-white" />
                    </div>

                    <div class="leading-none">
                        <div class="text-[20px] font-extrabold tracking-tight">LAUDA</div>
                        <div class="mt-0.5 text-[9px] font-semibold tracking-[0.2em] text-[#8E8E9E]">
                            API DIGITAL
                        </div>
                    </div>
                </Link>

                <div class="ml-auto hidden items-center gap-10 text-[15px] font-medium text-[#5A5A6B] lg:flex">
                    <!-- SOLUCIONES MENU -->
                    <div ref="solutionsMenuRef" class="relative flex h-[76px] items-center">
                        <button
                            type="button"
                            class="flex items-center gap-1 transition-colors hover:text-[#0B0B12]"
                            :class="solutionsOpen && 'text-[#0B0B12]'"
                            @click.stop="solutionsOpen = !solutionsOpen"
                        >
                            Soluciones
                            <ChevronDown
                                class="h-3.5 w-3.5 transition-transform"
                                :class="solutionsOpen && 'rotate-180'"
                            />
                        </button>

                        <div
                            v-show="solutionsOpen"
                            class="absolute left-0 top-[68px] z-50 w-[560px] rounded-3xl border border-black/5 bg-white p-4 shadow-2xl shadow-slate-950/15"
                        >
                            <div class="mb-3 px-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-[#F5333C]">
                                    Soluciones LaudaAPI
                                </p>
                                <p class="mt-1 text-sm text-[#6B7280]">
                                    Productos conectados dentro del ecosistema operativo.
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <a
                                    v-for="product in solutionProducts"
                                    :key="product.name"
                                    :href="product.href"
                                    class="group rounded-2xl border border-transparent p-3 transition hover:border-black/5 hover:bg-[#F4F4F6]"
                                    @click="solutionsOpen = false"
                                >
                                    <div class="flex items-start gap-3">
                                        <span
                                            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl"
                                            :style="{ background: product.color + '1a' }"
                                        >
                                            <component
                                                :is="product.icon"
                                                class="h-5 w-5"
                                                :style="{ color: product.color }"
                                            />
                                        </span>

                                        <div class="min-w-0">
                                            <p class="text-sm font-black text-[#0B0B12]">
                                                {{ product.name }}
                                            </p>
                                            <p class="mt-1 text-xs leading-5 text-[#6B7280]">
                                                {{ product.desc }}
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- RESTO DEL NAV -->
                    <a
                        href="#ecosistema"
                        class="flex h-[76px] items-center transition-colors hover:text-[#0B0B12]"
                        :class="'relative text-[#0B0B12] after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-full after:bg-[#F5333C]'"
                    >
                        Ecosistema
                    </a>

                    <a
                        href="#flujos"
                        class="flex h-[76px] items-center transition-colors hover:text-[#0B0B12]"
                    >
                        Flujos
                    </a>

                    <a
                        href="https://ecf.laudaapi.com"
                        class="flex h-[76px] items-center transition-colors hover:text-[#0B0B12]"
                    >
                        e-CF
                    </a>

                    <a
                        href="#contacto"
                        class="flex h-[76px] items-center transition-colors hover:text-[#0B0B12]"
                    >
                        Contacto
                    </a>
                </div>

                <Button class="ml-auto gap-2 rounded-xl bg-[#0B0B12] px-6 py-6 text-white hover:bg-black lg:ml-0">
                    <User class="h-4 w-4" />
                    Iniciar sesión
                </Button>
            </div>
        </nav>

        <!-- ===================== HERO ===================== -->
        <section class="mx-auto max-w-none px-6 pt-10 2xl:px-8">
            <div class="grid items-center gap-6 xl:grid-cols-[450px_minmax(0,1fr)] 2xl:grid-cols-[470px_minmax(0,1fr)]">
                <!-- Columna izquierda -->
                <div class="max-w-[455px]">
                    <span class="inline-flex items-center gap-2 rounded-full bg-[#F5333C]/10 px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[#F5333C]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F5333C]" />
                        Un solo ambiente
                    </span>

                    <h1 class="mt-6 text-[48px] font-extrabold leading-[1.02] tracking-[-0.04em] lg:text-[56px]">
                        La operación completa de tu negocio,
                        <span class="text-[#F5333C]">conectada en un solo ambiente.</span>
                    </h1>

                    <p class="mt-6 max-w-md text-[17px] leading-relaxed text-[#5A5A6B]">
                        LaudaAPI conecta ventas, POS, e-CF, delivery, compras, cumplimiento y monitoreo
                        para que cada área trabaje sobre una misma verdad operativa.
                    </p>

                    <div class="mt-8 flex gap-3">
                        <Button class="gap-2 rounded-xl bg-[#F5333C] px-6 py-6 text-white hover:bg-[#d92730]">
                            Ver ecosistema
                            <ArrowRight class="h-4 w-4" />
                        </Button>

                        <Button variant="outline" class="rounded-xl border-black/10 bg-white px-6 py-6 hover:bg-[#FAFAFA]">
                            Solicitar demo
                        </Button>
                    </div>

                    <div class="mt-9 grid max-w-[540px] gap-3 sm:grid-cols-3">
                        <div
                            v-for="c in chips"
                            :key="c.text"
                            class="flex min-h-[66px] items-center gap-2.5 rounded-xl border border-black/5 bg-white px-3 py-3"
                        >
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg" :style="{ background: c.color + '1a' }">
                                <component :is="c.icon" class="h-4 w-4" :style="{ color: c.color }" />
                            </span>

                            <span class="text-[12px] font-semibold leading-tight text-[#5A5A6B]">
                                {{ c.text }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: panel oscuro -->
                <div class="overflow-hidden rounded-[26px] border border-white/5 bg-[#0A0D18] p-4 shadow-2xl shadow-slate-950/25 lg:p-5">
                    <!-- header -->
                    <div class="mb-3 flex items-center justify-center gap-3 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#6B6B82]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F5333C]" />
                        Ecosistema LaudaAPI
                        <span class="h-1.5 w-1.5 rounded-full bg-[#22C55E]" />
                        En vivo
                    </div>

                    <!-- diagrama -->
                    <div
                        ref="diagram"
                        class="relative h-[560px] rounded-2xl bg-[#080B15]"
                        style="background-image: radial-gradient(circle at 44% 43%, rgba(245,51,60,.11), transparent 48%), linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px); background-size: 100% 100%, 34px 34px, 34px 34px;"
                    >
                        <!-- conectores -->
                        <svg class="pointer-events-none absolute inset-0 h-full w-full" style="z-index:0">
                            <template v-for="(l, i) in lines" :key="i">
                                <path
                                    v-if="l.type === 'elbow'"
                                    :d="l.d"
                                    :stroke="l.color"
                                    fill="none"
                                    stroke-width="1.5"
                                    stroke-dasharray="4 6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="flow-line"
                                    style="opacity:.55"
                                />

                                <line
                                    v-else
                                    :x1="l.x1"
                                    :y1="l.y1"
                                    :x2="l.x2"
                                    :y2="l.y2"
                                    :stroke="l.color"
                                    stroke-width="1.5"
                                    stroke-dasharray="4 6"
                                    stroke-linecap="round"
                                    class="flow-line"
                                    style="opacity:.55"
                                />
                            </template>
                        </svg>

                        <!-- etiqueta -->
                        <div class="absolute left-1/2 top-5 z-10 -translate-x-1/2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-[9px] font-semibold uppercase tracking-[0.16em] text-[#8B8BA0]">
                            Eventos en tiempo real
                        </div>

                        <!-- core -->
                        <div
                            ref="coreEl"
                            class="core-glow absolute z-20 flex h-[178px] w-[178px] -translate-x-1/2 -translate-y-1/2 flex-col items-center justify-center rounded-full border border-[#F5333C]/40 bg-[#0A0D18] text-center"
                            :style="{ left: CORE.x + '%', top: CORE.y + '%' }"
                        >
                            <div class="mb-2 grid h-10 w-10 place-items-center rounded-xl bg-[#F5333C] font-black text-white">
                                L
                            </div>

                            <div class="text-[17px] font-extrabold tracking-tight text-white">
                                LAUDAAPI CORE
                            </div>

                            <div class="mt-0.5 text-[9px] font-bold uppercase tracking-[0.14em] text-[#F5333C]">
                                API-First Environment
                            </div>

                            <div class="mt-2 text-[10.5px] leading-snug text-[#8B8BA0]">
                                Conecta eventos.<br />
                                Orquesta procesos.
                            </div>
                        </div>

                        <!-- nodos -->
                        <div
                            v-for="(n, i) in nodes"
                            :key="n.id"
                            :ref="(el) => setNodeRef(el, i)"
                            class="absolute z-10 flex min-h-[72px] w-[190px] -translate-x-1/2 -translate-y-1/2 items-start gap-2.5 rounded-2xl border border-white/[0.07] bg-[#12172A] p-3 shadow-lg shadow-black/10"
                            :class="n.group === 'backoffice' && 'w-[184px]'"
                            :style="{ left: n.x + '%', top: n.y + '%' }"
                        >
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg shadow-lg" :style="{ background: n.color }">
                                <component :is="n.icon" class="h-4.5 w-4.5 text-white" />
                            </span>

                            <div class="min-w-0">
                                <div class="truncate text-[13px] font-bold leading-tight text-white">
                                    {{ n.label }}
                                </div>

                                <div class="mt-0.5 text-[10.5px] leading-snug text-[#7A7A90]">
                                    {{ n.desc }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- panels -->
                    <div class="mt-4 grid gap-4 xl:grid-cols-[1.15fr_1fr]">
                        <!-- transmisión -->
                        <div class="rounded-2xl border border-white/[0.07] bg-[#0D1120] p-4">
                            <div class="mb-3 flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#8B8BA0]">
                                <Activity class="h-3.5 w-3.5" />
                                Transmisión en vivo
                            </div>

                            <div class="space-y-2 font-mono text-[11px]">
                                <div v-for="log in logs" :key="log.time" class="flex min-w-0 items-center gap-3">
                                    <span class="text-[#4A4A5E]">{{ log.time }}</span>

                                    <span class="flex items-center gap-1.5 font-medium" :style="{ color: log.color }">
                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ background: log.color }" />
                                        {{ log.tag }}
                                    </span>

                                    <span class="min-w-0 flex-1 truncate text-[#9A9AB0]">{{ log.msg }}</span>
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#22C55E]" />
                                </div>
                            </div>
                        </div>

                        <!-- estado -->
                        <div class="rounded-2xl border border-white/[0.07] bg-[#0D1120] p-4">
                            <div class="mb-3 flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#8B8BA0]">
                                <ShieldCheck class="h-3.5 w-3.5" />
                                Estado operativo
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div v-for="m in metrics" :key="m.label" class="rounded-xl border border-white/6 bg-[#12172A] p-3">
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
        <section class="mx-auto max-w-none px-8 py-10 2xl:px-10">
            <div class="grid gap-5 xl:grid-cols-4">
                <div
                    v-for="f in flows"
                    :key="f.title"
                    class="min-w-0 overflow-hidden rounded-3xl border border-black/5 bg-white p-6 transition-shadow hover:shadow-xl hover:shadow-black/4"
                >
                    <h3 class="text-[17px] font-bold tracking-tight">
                        {{ f.title }}
                    </h3>

                    <div class="mt-5 flex min-w-0 flex-wrap items-start gap-x-2 gap-y-4">
                        <template v-for="(s, i) in f.steps" :key="s.label">
                            <div class="flex min-w-[44px] max-w-[64px] flex-col items-center gap-2 text-center">
                                <span class="grid h-9 w-9 place-items-center rounded-full" :style="{ background: s.color + '1a' }">
                                    <component :is="s.icon" class="h-4.25 w-4.25" :style="{ color: s.color }" />
                                </span>

                                <span class="max-w-[68px] truncate text-[10px] font-medium text-[#5A5A6B]">
                                    {{ s.label }}
                                </span>
                            </div>

                            <ArrowRight
                                v-if="i < f.steps.length - 1"
                                class="mt-3 h-3.5 w-3.5 shrink-0 text-[#C4C4CE]"
                            />
                        </template>
                    </div>

                    <p class="mt-5 text-[13px] leading-relaxed text-[#8E8E9E]">
                        {{ f.desc }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
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
}

@media (max-width: 1535px) {
    section > div.grid {
        grid-template-columns: 1fr;
    }
}
</style>