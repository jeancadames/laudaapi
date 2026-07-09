<script setup>
import BrandLogo from '@/components/BrandLogo.vue'
import { Button } from '@/components/ui/button'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import {
    Activity,
    ArrowRight,
    Boxes,
    Calculator,
    CheckCircle2,
    ChevronDown,
    FileText,
    Landmark,
    Menu,
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
    X,
    Zap,
} from 'lucide-vue-next'

/* -------------------------------------------------------------------------- */
/*  Paleta de marca centralizada                                               */
/*  Fuente única de color por producto. Cambiar aquí se propaga a todo.        */
/* -------------------------------------------------------------------------- */

const brand = {
    main: '#F5333C',
    social: '#EC4899',
    crm: '#A855F7',
    ecommerce: '#3B82F6',
    pos: '#22C55E',
    ecf: '#F59E0B',
    ecfText: '#D97706', // variante oscura para texto sobre fondo claro
    delivery: '#F97316',
    cumplimiento: '#14B8A6',
    bys: '#8B5CF6',
    bancos: '#3B82F6',
    contabilidad: '#22C55E',
    status: '#3B82F6',
    rrhh: '#0EA5E9',
    tesoreria: '#10B981',
    proyectos: '#6366F1',
    eventos: '#F43F5E',
    transporte: '#F97316',
    gruas: '#EAB308',
    loans: '#22C55E',
    dealers: '#A855F7',
    bi: '#3B82F6',
    neutral: '#94A3B8',
}

/* -------------------------------------------------------------------------- */
/*  Datos del ecosistema                                                       */
/* -------------------------------------------------------------------------- */

const CORE = { x: 50, y: 44 }

const nodes = [
    // Entradas (columna izquierda)
    { id: 'social', label: 'Social', desc: 'Captura conversaciones y oportunidades.', color: brand.social, icon: MessageCircle, x: 15, y: 20, group: 'left' },
    { id: 'crm', label: 'CRM', desc: 'Convierte oportunidades en solicitudes reales.', color: brand.crm, icon: Users, x: 15, y: 44, group: 'left' },
    { id: 'ecommerce', label: 'Ecommerce', desc: 'Recibe pedidos y solicitudes online.', color: brand.ecommerce, icon: ShoppingCart, x: 15, y: 68, group: 'left' },
    // Salidas (columna derecha)
    { id: 'pos', label: 'POS', desc: 'Opera ventas, inventario, cobros y despacho.', color: brand.pos, icon: Store, x: 85, y: 20, group: 'right' },
    { id: 'ecf', label: 'e-CF', desc: 'Firma, envía y responde ante DGII.', color: brand.ecf, icon: FileText, x: 85, y: 44, group: 'right' },
    { id: 'delivery', label: 'Delivery', desc: 'Asigna, entrega y registra evidencia.', color: brand.delivery, icon: Truck, x: 85, y: 68, group: 'right' },
    // Backoffice (fila inferior)
    { id: 'cumplimiento', label: 'Cumplimiento', desc: 'Obligaciones, validaciones y vencimientos.', color: brand.cumplimiento, icon: ShieldCheck, x: 12, y: 86, group: 'backoffice' },
    { id: 'bys', label: 'BYS', desc: 'Compras, servicios, gastos y proveedores.', color: brand.bys, icon: Boxes, x: 37, y: 86, group: 'backoffice' },
    { id: 'bancos', label: 'Bancos', desc: 'Conciliación y movimientos financieros.', color: brand.bancos, icon: Landmark, x: 63, y: 86, group: 'backoffice' },
    { id: 'contabilidad', label: 'Contabilidad', desc: 'Asientos contables y reportes financieros.', color: brand.contabilidad, icon: Calculator, x: 88, y: 86, group: 'backoffice' },
    // Monitoreo (arriba, centrado)
    { id: 'status', label: 'Status', desc: 'Disponibilidad, DGII, APIs y salud operativa.', color: brand.status, icon: Activity, x: 50, y: 13, group: 'status' },
]

const chips = [
    { icon: RefreshCw, text: 'POS como fuente de verdad', color: brand.main },
    { icon: FileText, text: 'e-CF responde automáticamente', color: brand.ecf },
    { icon: Activity, text: 'Status monitorea DGII y APIs', color: brand.status },
]

const logs = [
    { time: '10:42:12', tag: 'Social', color: brand.social, msg: 'Lead captado desde Instagram' },
    { time: '10:42:15', tag: 'CRM', color: brand.crm, msg: 'Oportunidad creada: #OP-4821' },
    { time: '10:42:18', tag: 'POS', color: brand.pos, msg: 'Pedido operativo generado' },
    { time: '10:42:21', tag: 'e-CF', color: brand.ecf, msg: 'Petición fiscal firmada y enviada a DGII' },
    { time: '10:42:24', tag: 'Cumplimiento', color: brand.cumplimiento, msg: 'Documento clasificado para trazabilidad fiscal' },
    { time: '10:42:28', tag: 'Status', color: brand.status, msg: 'Endpoint DGII y respuesta registrados' },
]

const metrics = [
    { icon: CheckCircle2, iconColor: brand.pos, label: 'Ambientes proyectados', value: '20+', sub: 'Core + módulos', subColor: brand.pos },
    { icon: ShieldCheck, iconColor: brand.ecf, label: 'DGII / e-CF', value: 'Automático', sub: 'Firma, envía y responde', subColor: brand.ecf },
    { icon: TrendingUp, iconColor: brand.crm, label: 'Operación', value: 'POS Core', sub: 'Ventas, cobros e inventario', subColor: brand.pos },
    { icon: Zap, iconColor: brand.status, label: 'Eventos', value: 'API-first', sub: 'En tiempo real', subColor: brand.status },
]

const flows = [
    {
        title: 'Lead a factura y entrega',
        desc: 'Una conversación se convierte en venta, factura fiscal, despacho y evidencia de entrega.',
        steps: [
            { label: 'Social', color: brand.social, icon: MessageCircle },
            { label: 'CRM', color: brand.crm, icon: Users },
            { label: 'POS', color: brand.pos, icon: Store },
            { label: 'e-CF', color: brand.ecf, icon: FileText },
            { label: 'Delivery', color: brand.delivery, icon: Truck },
        ],
    },
    {
        title: 'Pedido ecommerce a operación',
        desc: 'El pedido online entra al POS sin duplicar facturación, inventario, CxC ni conduces.',
        steps: [
            { label: 'Ecommerce', color: brand.ecommerce, icon: ShoppingCart },
            { label: 'POS', color: brand.pos, icon: Store },
            { label: 'Inventario', color: brand.pos, icon: Boxes },
            { label: 'e-CF', color: brand.ecf, icon: FileText },
            { label: 'Delivery', color: brand.delivery, icon: Truck },
        ],
    },
    {
        title: 'Compra a cumplimiento',
        desc: 'Compras, XML/PDF, validaciones fiscales y metadata contable preparada para reportes.',
        steps: [
            { label: 'BYS', color: brand.bys, icon: Boxes },
            { label: 'e-CF', color: brand.ecf, icon: FileText },
            { label: 'Cumplimiento', color: brand.cumplimiento, icon: ShieldCheck },
            { label: 'Contabilidad', color: brand.contabilidad, icon: Calculator },
            { label: 'Bancos', color: brand.bancos, icon: Landmark },
        ],
    },
    {
        title: 'Operación monitoreada',
        desc: 'Status observa eventos, disponibilidad, endpoints, DGII y salud de todo el ecosistema.',
        steps: [
            { label: 'Todas las apps', color: brand.neutral, icon: RefreshCw },
            { label: 'Status', color: brand.status, icon: Activity },
        ],
    },
]

const solutionProducts = [
    { name: 'Social', href: 'https://social.laudaapi.com', desc: 'Captación social, contenido, inbox y leads.', icon: MessageCircle, color: brand.social },
    { name: 'CRM', href: 'https://crm.laudaapi.com', desc: 'Clientes, oportunidades y seguimiento comercial.', icon: Users, color: brand.crm },
    { name: 'POS', href: 'https://pos.laudaapi.com', desc: 'Ventas, inventario, cobros, rutas y despacho.', icon: Store, color: brand.pos },
    { name: 'e-CF', href: 'https://ecf.laudaapi.com', desc: 'Firma, envío, TrackId y respuesta ante DGII.', icon: FileText, color: brand.ecf },
    { name: 'Cumplimiento', href: 'https://cumplimiento.laudaapi.com', desc: 'Obligaciones, documentos y control fiscal.', icon: ShieldCheck, color: brand.cumplimiento },
    { name: 'Status', href: 'https://status.laudaapi.com', desc: 'Monitoreo de DGII, APIs, caídas y eventos.', icon: Activity, color: brand.status },
    { name: 'RRHH', href: 'https://rrhh.laudaapi.com', desc: 'Recursos humanos, empleados y procesos internos.', icon: Users, color: brand.rrhh },
    { name: 'Tesorería', href: 'https://tesoreria.laudaapi.com', desc: 'Pagos, caja, bancos y flujo financiero.', icon: Landmark, color: brand.tesoreria },
    { name: 'Proyectos', href: 'https://proyectos.laudaapi.com', desc: 'Tareas, ejecución, entregables y avance.', icon: Boxes, color: brand.proyectos },
    { name: 'Eventos', href: 'https://eventos.laudaapi.com', desc: 'Eventos, actividades, invitados y operación.', icon: Activity, color: brand.eventos },
    { name: 'Transporte personal', href: 'https://transporte.laudaapi.com', desc: 'Rutas, unidades, pasajeros y movilidad interna.', icon: Truck, color: brand.transporte },
    { name: 'Servicios de grúas', href: 'https://gruas.laudaapi.com', desc: 'Asignación, asistencia, evidencia y servicios.', icon: Truck, color: brand.gruas },
    { name: 'Loans', href: 'https://loans.laudaapi.com', desc: 'Préstamos, cuotas, cartera y cobranza.', icon: Calculator, color: brand.loans },
    { name: 'Dealers', href: 'https://dealers.laudaapi.com', desc: 'Inventario, ventas, financiamiento y clientes.', icon: Store, color: brand.dealers },
    { name: 'BI', href: 'https://bi.laudaapi.com', desc: 'Dashboards, métricas, reporting e inteligencia.', icon: TrendingUp, color: brand.bi },
]

const primarySolutions = [
    {
        name: 'e-CF',
        href: 'https://ecf.laudaapi.com',
        status: 'Disponible',
        statusColor: brand.pos,
        desc: 'Motor fiscal para firmar, enviar, consultar estados y responder comprobantes electrónicos ante DGII.',
        audience: 'Empresas que necesitan facturación electrónica RD.',
        connects: 'POS, Cumplimiento, BYS y Contabilidad.',
        icon: FileText,
        color: brand.ecf,
    },
    {
        name: 'Cumplimiento',
        href: 'https://cumplimiento.laudaapi.com',
        status: 'Disponible',
        statusColor: brand.pos,
        desc: 'Control de obligaciones, documentos fiscales, vencimientos, soporte, pagos SaaS y trazabilidad.',
        audience: 'Empresas y asesores que necesitan seguimiento fiscal/operativo.',
        connects: 'e-CF, POS, BYS, Contabilidad y Status.',
        icon: ShieldCheck,
        color: brand.cumplimiento,
    },
    {
        name: 'POS',
        href: 'https://pos.laudaapi.com',
        status: 'En desarrollo avanzado',
        statusColor: brand.ecf,
        desc: 'Operación comercial: pedidos, caja, crédito, CxC, inventario, almacén, empaque, rutas y despacho.',
        audience: 'Negocios que venden, cobran, despachan y controlan inventario.',
        connects: 'e-CF, Ecommerce, Delivery, CRM, Bancos y BI.',
        icon: Store,
        color: brand.pos,
    },
    {
        name: 'CRM',
        href: 'https://crm.laudaapi.com',
        status: 'En desarrollo avanzado',
        statusColor: brand.ecf,
        desc: 'Leads, clientes, contactos, oportunidades, actividades, pipeline e integración comercial hacia POS.',
        audience: 'Equipos comerciales que necesitan seguimiento y conversión.',
        connects: 'Social, POS, Ecommerce y BI.',
        icon: Users,
        color: brand.crm,
    },
    {
        name: 'Social',
        href: 'https://social.laudaapi.com',
        status: 'En desarrollo',
        statusColor: brand.ecommerce,
        desc: 'Contenido, campañas, inbox social, leads, analítica e integración con CRM.',
        audience: 'Equipos de marketing, ventas y atención digital.',
        connects: 'CRM, campañas, leads y analítica.',
        icon: MessageCircle,
        color: brand.social,
    },
    {
        name: 'Status',
        href: 'https://status.laudaapi.com',
        status: 'Disponible',
        statusColor: brand.pos,
        desc: 'Monitoreo de disponibilidad, DGII, endpoints, APIs, incidencias y salud general del ecosistema.',
        audience: 'Administradores, soporte y operación técnica.',
        connects: 'Todas las soluciones LaudaAPI.',
        icon: Activity,
        color: brand.status,
    },
]

const ecosystemSteps = [
    {
        title: 'Entrada comercial',
        desc: 'Social, CRM y Ecommerce capturan conversaciones, oportunidades y pedidos online sin tocar la operación final.',
        items: [ 'Social', 'CRM', 'Ecommerce' ],
        icon: MessageCircle,
        color: brand.social,
    },
    {
        title: 'Operación central',
        desc: 'POS funciona como fuente de verdad para ventas, caja, crédito, inventario, despacho, CxC y operación diaria.',
        items: [ 'POS', 'Inventario', 'Caja', 'CxC' ],
        icon: Store,
        color: brand.pos,
    },
    {
        title: 'Fiscalidad y cumplimiento',
        desc: 'e-CF maneja la emisión fiscal y Cumplimiento organiza obligaciones, documentos, vencimientos y trazabilidad.',
        items: [ 'e-CF', 'DGII', 'Cumplimiento' ],
        icon: ShieldCheck,
        color: brand.ecf,
    },
    {
        title: 'Backoffice conectado',
        desc: 'BYS, Bancos, Contabilidad, Tesorería y BI consumen eventos preparados sin duplicar la operación principal.',
        items: [ 'BYS', 'Bancos', 'Contabilidad', 'BI' ],
        icon: Calculator,
        color: brand.bancos,
    },
]

const activationOptions = [
    {
        title: 'Activación individual',
        desc: 'Cada solución mantiene su propio onboarding para empresas que quieren contratar un módulo específico.',
        badge: 'Por app',
        examples: [ 'e-CF', 'Cumplimiento', 'POS', 'CRM' ],
        icon: CheckCircle2,
        color: brand.pos,
    },
    {
        title: 'Implementación asistida',
        desc: 'LaudaAPI.com centraliza solicitudes donde el cliente necesita acompañamiento, configuración, pruebas o migración.',
        badge: 'Asistida',
        examples: [ 'Configuración fiscal', 'Usuarios', 'Datos iniciales', 'Entrenamiento' ],
        icon: Users,
        color: brand.crm,
    },
    {
        title: 'Ecosistema completo',
        desc: 'Para empresas que necesitan varias soluciones conectadas bajo una misma estrategia operativa y comercial.',
        badge: 'Todo incluido',
        examples: [ 'POS + e-CF', 'CRM + POS', 'Ecommerce + Delivery', 'BI + Backoffice' ],
        icon: RefreshCw,
        color: brand.main,
    },
]

const requestTypes = [
    'Demo',
    'Activar una solución',
    'Implementación asistida',
    'Paquete todo incluido',
    'Migración / integración',
]

const extendedModules = [
    { name: 'RRHH', desc: 'Recursos humanos, equipos, asistencia y procesos internos.', icon: Users, color: brand.rrhh, relation: 'Consume operación y usuarios del ecosistema.' },
    { name: 'Tesorería', desc: 'Caja, pagos, bancos, conciliación y flujo financiero.', icon: Landmark, color: brand.tesoreria, relation: 'Cruza cobros POS, bancos y contabilidad.' },
    { name: 'Proyectos', desc: 'Planificación, tareas, entregables y ejecución.', icon: Boxes, color: brand.proyectos, relation: 'Puede nacer desde CRM o servicios vendidos en POS.' },
    { name: 'Eventos', desc: 'Gestión de eventos, invitados, ventas y operación.', icon: Activity, color: brand.eventos, relation: 'Puede conectar Social, CRM, POS y facturación.' },
    { name: 'Transporte personal', desc: 'Rutas, unidades, pasajeros, horarios y evidencia.', icon: Truck, color: brand.transporte, relation: 'Extiende logística sin tocar facturación.' },
    { name: 'Servicios de grúas', desc: 'Asignación de grúas, asistencia, tracking y cobro.', icon: Truck, color: brand.gruas, relation: 'Opera servicios conectados a POS y rutas.' },
    { name: 'Loans', desc: 'Préstamos, cartera, cuotas, mora y cobranza.', icon: Calculator, color: brand.loans, relation: 'Se apoya en clientes, cobros, bancos y BI.' },
    { name: 'Dealers', desc: 'Inventario vehicular, clientes, ventas y financiamiento.', icon: Store, color: brand.dealers, relation: 'Integra CRM, POS, loans, e-CF y BI.' },
    { name: 'BI', desc: 'Dashboards, KPIs, analítica y toma de decisiones.', icon: TrendingUp, color: brand.bi, relation: 'Lee señales del ecosistema sin duplicar operación.' },
]

const mobileNavLinks = [
    { label: 'Ecosistema', href: '#ecosistema-detalle' },
    { label: 'Soluciones', href: '#soluciones' },
    { label: 'Activación', href: '#activacion' },
    { label: 'Flujos', href: '#flujos' },
    { label: 'Contacto', href: '#contacto' },
]

/* -------------------------------------------------------------------------- */
/*  Estado de UI                                                               */
/* -------------------------------------------------------------------------- */

const solutionsOpen = ref(false)
const mobileMenuOpen = ref(false)
const isDarkMode = ref(false)

const solutionsMenuRef = ref(null)
const solutionsTriggerRef = ref(null)
const hamburgerRef = ref(null)
const mobileCloseRef = ref(null)

/* -------------------------------------------------------------------------- */
/*  Escalado del diagrama                                                      */
/*  El diagrama se maqueta a un tamaño fijo (DESIGN_W x DESIGN_H) donde todo    */
/*  respira, y se escala proporcionalmente para caber en su contenedor.        */
/*  Así nunca hay scroll horizontal ni nodos encimados.                        */
/* -------------------------------------------------------------------------- */

const DESIGN_W = 740
const DESIGN_H = 560

const diagramScale = ref(1)
const scrollWrap = ref(null)

/* Fondo del diagrama derivado de la posición real del CORE */
const diagramStyle = computed(() => ({
    backgroundImage:
        `radial-gradient(circle at ${CORE.x}% ${CORE.y}%, rgba(245,51,60,.11), transparent 48%),` +
        'linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),' +
        'linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px)',
    backgroundSize: '100% 100%, 34px 34px, 34px 34px',
    width: DESIGN_W + 'px',
    height: DESIGN_H + 'px',
    transform: `scale(${diagramScale.value})`,
    transformOrigin: 'top left',
}))

/* Alto real del contenedor una vez escalado (evita espacio sobrante) */
const wrapHeight = computed(() => Math.round(DESIGN_H * diagramScale.value))

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

    if (side === 'left') return { x: rect.left - box.left, y: centerY }
    if (side === 'right') return { x: rect.right - box.left, y: centerY }
    if (side === 'top') return { x: centerX, y: rect.top - box.top }
    if (side === 'bottom') return { x: centerX, y: rect.bottom - box.top }

    if (node.group === 'left') return { x: rect.right - box.left, y: centerY }
    if (node.group === 'right') return { x: rect.left - box.left, y: centerY }
    if (node.group === 'backoffice') return { x: centerX, y: rect.top - box.top }
    if (node.group === 'status') return { x: centerX, y: rect.bottom - box.top }

    return { x: centerX, y: centerY }
}

function computeLines() {
    if (!diagram.value || !coreEl.value) return

    // getBoundingClientRect devuelve px ya escalados; dividimos por la escala
    // para trabajar en el sistema de coordenadas de diseño del SVG.
    const s = diagramScale.value || 1
    const box = diagram.value.getBoundingClientRect()
    const corePoint = getCorePoint(box)

    lines.value = nodes
        .map((node, index) => {
            const el = nodeRefs.value[ index ]
            if (!el) return null

            const nodeRect = el.getBoundingClientRect()
            const from = getNodeConnectionPoint(node, nodeRect, box)

            return {
                type: 'line',
                x1: from.x / s,
                y1: from.y / s,
                x2: corePoint.x / s,
                y2: corePoint.y / s,
                color: node.color,
                group: node.group,
            }
        })
        .filter(Boolean)
}

function updateScale() {
    const w = scrollWrap.value?.clientWidth || DESIGN_W
    // Llena siempre el ancho disponible: reduce en panels angostos, agranda
    // ligeramente en anchos, sin dejar hueco ni provocar scroll.
    diagramScale.value = Math.min(1.25, w / DESIGN_W)
}

let rafId = null
function scheduleRefresh() {
    if (rafId) return
    rafId = requestAnimationFrame(() => {
        rafId = null
        updateScale()
        nextTick(computeLines)
    })
}

/* -------------------------------------------------------------------------- */
/*  Transmisión en vivo (logs animados)                                        */
/* -------------------------------------------------------------------------- */

let logCounter = 0
const liveLogs = ref(logs.map((l) => ({ ...l, uid: ++logCounter })))
let logTimer = null

function pad(n) {
    return String(n).padStart(2, '0')
}

function nowStamp() {
    const d = new Date()
    return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
}

function startLogStream() {
    if (prefersReducedMotion()) return

    let cursor = 0
    logTimer = window.setInterval(() => {
        const base = logs[ cursor % logs.length ]
        cursor++
        liveLogs.value = [ { ...base, uid: ++logCounter, time: nowStamp() }, ...liveLogs.value ].slice(0, 6)
    }, 2600)
}

/* -------------------------------------------------------------------------- */
/*  Modo presentación                                                          */
/* -------------------------------------------------------------------------- */

function setPresentationMode(value) {
    isDarkMode.value = value
    window.localStorage.setItem('laudaapi-presentation-mode', value ? 'dark' : 'light')
}

function togglePresentationMode() {
    setPresentationMode(!isDarkMode.value)
}

/* -------------------------------------------------------------------------- */
/*  Navegación / accesibilidad                                                 */
/* -------------------------------------------------------------------------- */

function scrollToId(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function goLogin() {
    router.visit('/login')
}

function closeSolutions(returnFocus = false) {
    if (!solutionsOpen.value) return
    solutionsOpen.value = false
    if (returnFocus) nextTick(() => solutionsTriggerRef.value?.focus())
}

function openMobileMenu() {
    mobileMenuOpen.value = true
    nextTick(() => mobileCloseRef.value?.focus())
}

function closeMobileMenu(returnFocus = false) {
    if (!mobileMenuOpen.value) return
    mobileMenuOpen.value = false
    if (returnFocus) nextTick(() => hamburgerRef.value?.focus())
}

function handleKeydown(event) {
    if (event.key !== 'Escape') return

    if (mobileMenuOpen.value) {
        closeMobileMenu(true)
    } else if (solutionsOpen.value) {
        closeSolutions(true)
    }
}

function closeSolutionsOnOutsideClick(event) {
    if (!solutionsOpen.value) return

    const menu = solutionsMenuRef.value
    if (menu && !menu.contains(event.target)) {
        solutionsOpen.value = false
    }
}

/* Bloquea el scroll del body mientras el menú móvil está abierto */
watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
})

let ro

onMounted(async () => {
    const savedMode = window.localStorage.getItem('laudaapi-presentation-mode')
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches

    setPresentationMode(savedMode ? savedMode === 'dark' : Boolean(prefersDark))

    await nextTick()
    updateScale()
    computeLines()

    ro = new ResizeObserver(scheduleRefresh)
    if (scrollWrap.value) ro.observe(scrollWrap.value)

    startLogStream()

    window.addEventListener('resize', scheduleRefresh)
    window.addEventListener('pointerdown', closeSolutionsOnOutsideClick)
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    ro && ro.disconnect()
    if (rafId) cancelAnimationFrame(rafId)
    if (logTimer) clearInterval(logTimer)

    document.body.style.overflow = ''

    window.removeEventListener('resize', scheduleRefresh)
    window.removeEventListener('pointerdown', closeSolutionsOnOutsideClick)
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>

    <Head title="LaudaAPI Digital">
        <meta name="description" content="LaudaAPI conecta Social, CRM, Ecommerce, POS, Delivery, e-CF, Cumplimiento, BYS, Bancos, Contabilidad, Status, RRHH, Tesorería, Proyectos, Eventos, Transporte, Grúas, Loans, Dealers y BI en un solo ambiente operativo API-first." />
    </Head>

    <div :class="[ 'lauda-page min-h-screen antialiased', { 'lauda-page--dark': isDarkMode } ]">
        <!-- ===================== NAV ===================== -->
        <nav class="lauda-nav sticky top-0 z-50 border-b backdrop-blur-xl">
            <div class="mx-auto flex h-19 max-w-none items-center gap-8 px-6 lg:px-8 2xl:px-10">
                <Link href="/" class="flex items-center gap-2.5">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-(--brand) font-black text-white shadow-xl shadow-[#F5333C]/35">
                        <BrandLogo class="h-6 w-6 text-white" />
                    </div>

                    <div class="leading-none">
                        <div class="text-[20px] font-extrabold tracking-tight text-(--text)">LAUDA</div>
                        <div class="mt-0.5 text-[9px] font-semibold tracking-[0.2em] text-(--brand)">
                            API DIGITAL
                        </div>
                    </div>
                </Link>

                <div class="ml-auto hidden items-center gap-10 text-[15px] font-medium text-muted lg:flex">
                    <!-- SOLUCIONES MENU -->
                    <div ref="solutionsMenuRef" class="relative flex h-19 items-center">
                        <button id="solutions-trigger" ref="solutionsTriggerRef" type="button" class="lauda-solutions-trigger flex items-center gap-1 transition-colors hover:text-(--text)" :class="solutionsOpen && 'text-(--text)'" aria-haspopup="true" aria-controls="solutions-menu" :aria-expanded="solutionsOpen" @click.stop="solutionsOpen = !solutionsOpen">
                            Soluciones
                            <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="solutionsOpen && 'rotate-180'" />
                        </button>

                        <div v-show="solutionsOpen" id="solutions-menu" role="region" aria-labelledby="solutions-trigger" class="lauda-menu absolute left-0 top-17 z-50 w-180 rounded-3xl border p-4 shadow-2xl">
                            <div class="mb-3 px-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                                    Soluciones LaudaAPI
                                </p>
                                <p class="mt-1 text-sm text-muted">
                                    Productos conectados dentro del ecosistema operativo.
                                </p>
                            </div>

                            <div class="grid max-h-130 grid-cols-2 gap-2 overflow-y-auto pr-1">
                                <a v-for="product in solutionProducts" :key="product.name" :href="product.href" class="lauda-menu-item group rounded-2xl border border-transparent p-3 transition" @click="solutionsOpen = false">
                                    <div class="flex items-start gap-3">
                                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl" :style="{ background: product.color + '1a' }">
                                            <component :is="product.icon" class="h-5 w-5" :style="{ color: product.color }" />
                                        </span>

                                        <div class="min-w-0">
                                            <p class="text-sm font-black text-(--text)">
                                                {{ product.name }}
                                            </p>
                                            <p class="mt-1 text-xs leading-5 text-muted">
                                                {{ product.desc }}
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <a href="#ecosistema-detalle" class="relative flex h-19 items-center text-(--text) transition-colors after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-full after:bg-(--brand)">
                        Ecosistema
                    </a>

                    <a href="#soluciones" class="flex h-19 items-center transition-colors hover:text-(--text)">
                        Soluciones
                    </a>

                    <a href="#activacion" class="flex h-19 items-center transition-colors hover:text-(--text)">
                        Activación
                    </a>

                    <a href="#flujos" class="flex h-19 items-center transition-colors hover:text-(--text)">
                        Flujos
                    </a>

                    <a href="#contacto" class="flex h-19 items-center transition-colors hover:text-(--text)">
                        Contacto
                    </a>
                </div>

                <button type="button" class="lauda-mode-toggle ml-auto inline-flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-bold transition lg:ml-0" @click="togglePresentationMode">
                    <component :is="isDarkMode ? Sun : Moon" class="h-4 w-4" />
                    <span class="hidden sm:inline">{{ isDarkMode ? 'Light mode' : 'Dark mode' }}</span>
                </button>

                <Button class="hidden gap-2 rounded-xl bg-[#0B0B12] px-6 py-6 text-white hover:bg-black lg:inline-flex" @click="goLogin">
                    <User class="h-4 w-4" />
                    Iniciar sesión
                </Button>

                <!-- Hamburguesa (solo móvil / tablet) -->
                <button ref="hamburgerRef" type="button" class="lauda-icon-btn ml-1 grid h-11 w-11 place-items-center rounded-xl border lg:hidden" aria-controls="mobile-menu" :aria-expanded="mobileMenuOpen" aria-label="Abrir menú de navegación" @click="openMobileMenu">
                    <Menu class="h-5 w-5" />
                </button>
            </div>
        </nav>

        <!-- ===================== MENÚ MÓVIL ===================== -->
        <transition name="lauda-fade">
            <div v-if="mobileMenuOpen" id="mobile-menu" class="fixed inset-0 z-60 lg:hidden" role="dialog" aria-modal="true" aria-label="Menú de navegación">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeMobileMenu()" />

                <div class="lauda-mobile-panel absolute right-0 top-0 flex h-full w-full max-w-sm flex-col border-l p-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-black uppercase tracking-[0.2em] text-(--brand)">Menú</span>
                        <button ref="mobileCloseRef" type="button" class="lauda-icon-btn grid h-10 w-10 place-items-center rounded-xl border" aria-label="Cerrar menú" @click="closeMobileMenu(true)">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <nav class="mt-6 flex flex-col gap-1">
                        <a v-for="link in mobileNavLinks" :key="link.href" :href="link.href" class="lauda-mobile-link rounded-xl px-3 py-3 text-base font-semibold text-(--text)" @click="closeMobileMenu()">
                            {{ link.label }}
                        </a>
                    </nav>

                    <div class="mt-6 flex min-h-0 flex-1 flex-col">
                        <p class="px-3 text-[10px] font-black uppercase tracking-[0.22em] text-(--brand)">
                            Soluciones
                        </p>

                        <div class="mt-2 grid grid-cols-1 gap-1 overflow-y-auto pr-1">
                            <a v-for="product in solutionProducts" :key="product.name" :href="product.href" class="lauda-mobile-link flex items-center gap-3 rounded-xl px-3 py-2.5" @click="closeMobileMenu()">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" :style="{ background: product.color + '1a' }">
                                    <component :is="product.icon" class="h-4 w-4" :style="{ color: product.color }" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-(--text)">{{ product.name }}</span>
                                    <span class="block truncate text-xs text-muted">{{ product.desc }}</span>
                                </span>
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-border pt-4">
                        <Button class="w-full gap-2 rounded-xl bg-[#0B0B12] py-6 text-white hover:bg-black" @click="closeMobileMenu(); goLogin()">
                            <User class="h-4 w-4" />
                            Iniciar sesión
                        </Button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ===================== HERO ===================== -->
        <section class="lauda-hero">
            <div class="lauda-hero__layout">
                <!-- Columna izquierda -->
                <div class="lauda-hero__copy">
                    <span class="inline-flex items-center gap-2 rounded-full bg-(--brand)/10 px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-(--brand)">
                        <span class="h-1.5 w-1.5 rounded-full bg-(--brand)" />
                        Ecosistema API-first
                    </span>

                    <h1 class="mt-6 text-[38px] font-extrabold leading-[1.02] tracking-[-0.04em] text-(--text) sm:text-[48px] lg:text-[56px]">
                        Un solo ambiente para
                        <span class="text-(--brand)">vender, operar, facturar y cumplir.</span>
                    </h1>

                    <p class="mt-6 max-w-140 text-[17px] leading-relaxed text-muted">
                        LaudaAPI conecta Social, CRM, Ecommerce, POS, Delivery, e-CF, Cumplimiento,
                        Tesorería, RRHH, Proyectos, Dealers, Loans y BI. Cada módulo recibe eventos,
                        procesa su parte y responde al ecosistema sin duplicar la operación central.
                    </p>

                    <div class="mt-6 rounded-2xl border border-border bg-(--surface) p-3 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2 text-[11px] font-black uppercase tracking-[0.12em] text-(--soft)">
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
                        <Button class="gap-2 rounded-xl bg-(--brand) px-6 py-6 text-white hover:bg-(--brand-hover)" @click="scrollToId('ecosistema-detalle')">
                            Ver ecosistema
                            <ArrowRight class="h-4 w-4" />
                        </Button>

                        <Button variant="outline" class="lauda-outline-button rounded-xl px-6 py-6" @click="scrollToId('contacto')">
                            Solicitar demo
                        </Button>
                    </div>

                    <div class="lauda-hero__chips">
                        <div v-for="c in chips" :key="c.text" class="lauda-chip flex min-h-16.5 items-center gap-2.5 rounded-xl border px-3 py-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg" :style="{ background: c.color + '1a' }">
                                <component :is="c.icon" class="h-4 w-4" :style="{ color: c.color }" />
                            </span>

                            <span class="text-[12px] font-semibold leading-tight text-muted">
                                {{ c.text }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: panel oscuro -->
                <div id="ecosistema" class="lauda-hero__panel">
                    <!-- header -->
                    <div class="mb-3 flex flex-wrap items-center justify-center gap-3 text-center text-[11px] font-semibold uppercase tracking-[0.22em] text-[#7A8298]">
                        <span class="h-1.5 w-1.5 rounded-full bg-(--brand)" />
                        Ecosistema LaudaAPI
                        <span class="h-1.5 w-1.5 rounded-full bg-[#22C55E]" />
                        En vivo
                        <span class="rounded-full border border-white/10 bg-white/5 px-2 py-0.5 normal-case tracking-normal text-[#B8C0D8]">
                            presentación {{ isDarkMode ? 'dark' : 'light' }}
                        </span>
                    </div>

                    <!-- Diagrama -->
                    <div ref="scrollWrap" class="lauda-diagram-scroll" :style="{ height: wrapHeight + 'px' }">
                        <div ref="diagram" class="relative rounded-2xl bg-[#080B15]" :style="diagramStyle">
                            <!-- conectores (decorativos) -->
                            <svg class="pointer-events-none absolute inset-0 h-full w-full" style="z-index:0" aria-hidden="true" focusable="false">
                                <line v-for="(l, i) in lines" :key="i" :x1="l.x1" :y1="l.y1" :x2="l.x2" :y2="l.y2" :stroke="l.color" stroke-width="1.5" stroke-dasharray="4 6" stroke-linecap="round" class="flow-line" style="opacity:.55" />
                            </svg>

                            <!-- etiqueta -->
                            <div class="absolute left-5 top-5 z-10 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#9AA1B8]">
                                Eventos en tiempo real
                            </div>

                            <!-- CORE -->
                            <div ref="coreEl" class="core-glow absolute z-30 flex aspect-square w-44 -translate-x-1/2 -translate-y-1/2 flex-col items-center justify-center rounded-full border border-(--brand)/40 bg-[#0A0D18] text-center" :style="{ left: CORE.x + '%', top: CORE.y + '%' }">
                                <div class="mb-2 grid h-10 w-10 place-items-center rounded-xl bg-(--brand) font-black text-white">
                                    L
                                </div>

                                <div class="text-[16px] font-extrabold tracking-tight text-white">
                                    LAUDAAPI CORE
                                </div>

                                <div class="mt-0.5 text-[9.5px] font-bold uppercase tracking-[0.14em] text-(--brand)">
                                    API-First Environment
                                </div>

                                <div class="mt-2 text-[10px] leading-snug text-[#9AA1B8]">
                                    Conecta eventos.<br />
                                    Orquesta procesos.
                                </div>
                            </div>

                            <!-- nodos -->
                            <div v-for="(n, i) in nodes" :key="n.id" :ref="(el) => setNodeRef(el, i)" class="lauda-node absolute z-10 flex min-h-17 w-44.5 -translate-x-1/2 -translate-y-1/2 items-start gap-2.5 rounded-2xl border border-white/[0.07] bg-[#12172A] p-3 shadow-lg shadow-black/10" :class="n.group === 'backoffice' && 'w-37.5'" :style="{ left: n.x + '%', top: n.y + '%' }">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg shadow-lg" :style="{ background: n.color }">
                                    <component :is="n.icon" class="h-4 w-4 text-white" />
                                </span>

                                <div class="min-w-0">
                                    <div class="truncate text-[12px] font-bold leading-tight text-white">
                                        {{ n.label }}
                                    </div>

                                    <div class="mt-0.5 text-[10px] leading-snug text-[#9BA2B8]">
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
                            <div class="mb-3 flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#9AA1B8]">
                                <Activity class="h-3.5 w-3.5" />
                                Transmisión en vivo
                            </div>

                            <div class="space-y-2 font-mono text-[11px]">
                                <div v-for="log in liveLogs" :key="log.uid" class="flex min-w-0 items-center gap-3">
                                    <span class="shrink-0 text-[#6E6E86]">{{ log.time }}</span>

                                    <span class="flex shrink-0 items-center gap-1.5 font-medium" :style="{ color: log.color }">
                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ background: log.color }" />
                                        {{ log.tag }}
                                    </span>

                                    <span class="min-w-0 flex-1 truncate text-[#9AA1B8]">{{ log.msg }}</span>
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#22C55E]" />
                                </div>
                            </div>
                        </div>

                        <!-- estado -->
                        <div class="min-w-0 rounded-2xl border border-white/[0.07] bg-[#0D1120] p-4">
                            <div class="mb-3 flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#9AA1B8]">
                                <ShieldCheck class="h-3.5 w-3.5" />
                                Estado operativo
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div v-for="m in metrics" :key="m.label" class="min-w-0 rounded-xl border border-white/6 bg-[#12172A] p-3">
                                    <component :is="m.icon" class="h-4 w-4" :style="{ color: m.iconColor }" />

                                    <div class="mt-2 text-[10px] text-[#9BA2B8]">
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

        <!-- ===================== ECOSISTEMA DETALLE ===================== -->
        <section id="ecosistema-detalle" class="mx-auto max-w-360 scroll-mt-24 px-4 py-14 sm:px-6 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Ecosistema conectado
                    </p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        LaudaAPI.com presenta el ambiente, cada app opera su especialidad.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    El portal principal funciona como hub comercial, catálogo, activación asistida y punto de entrada.
                    Las operaciones reales viven en los subdominios especializados.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-4">
                <div v-for="step in ecosystemSteps" :key="step.title" class="lauda-card rounded-3xl border p-5">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl" :style="{ background: step.color + '1a' }">
                            <component :is="step.icon" class="h-5 w-5" :style="{ color: step.color }" />
                        </span>

                        <span class="rounded-full border border-border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                            API-first
                        </span>
                    </div>

                    <h3 class="text-base font-black text-(--text)">
                        {{ step.title }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        {{ step.desc }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="item in step.items" :key="item" class="rounded-full border border-border bg-(--surface-soft) px-2.5 py-1 text-[10px] font-bold text-muted">
                            {{ item }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== SOLUCIONES PRINCIPALES ===================== -->
        <section id="soluciones" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Soluciones principales
                    </p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        Apps independientes, conectadas bajo una misma marca.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    Cada solución puede contratarse por separado, manejar su tenant y completar su propio onboarding.
                    LaudaAPI.com organiza la entrada y conecta los casos que necesitan asistencia.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <a v-for="solution in primarySolutions" :key="solution.name" :href="solution.href" class="lauda-card group rounded-3xl border p-6 transition-all hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" :style="{ background: solution.color + '1a' }">
                            <component :is="solution.icon" class="h-5 w-5" :style="{ color: solution.color }" />
                        </span>

                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em]" :style="{ background: solution.statusColor + '1a', color: solution.statusColor }">
                            {{ solution.status }}
                        </span>
                    </div>

                    <h3 class="text-xl font-black text-(--text)">
                        {{ solution.name }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        {{ solution.desc }}
                    </p>

                    <div class="mt-5 space-y-3 text-xs leading-relaxed text-muted">
                        <div class="rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <span class="font-black text-(--text)">Para quién:</span> {{ solution.audience }}
                        </div>
                        <div class="rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <span class="font-black text-(--text)">Conecta con:</span> {{ solution.connects }}
                        </div>
                    </div>

                    <div class="mt-5 inline-flex items-center gap-2 text-sm font-black text-(--brand)">
                        Ver solución
                        <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </div>
                </a>
            </div>
        </section>

        <!-- ===================== ACTIVACIÓN ===================== -->
        <section id="activacion" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 2xl:px-8">
            <div class="rounded-4xl border border-border bg-(--surface) p-6 shadow-sm lg:p-8">
                <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                            Activación centralizada
                        </p>
                        <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                            Dos formas de iniciar: individual o con asistencia.
                        </h2>
                    </div>

                    <p class="max-w-xl text-sm leading-relaxed text-muted">
                        Las apps conservan su onboarding propio. LaudaAPI.com agrega una capa central para clientes
                        que necesitan acompañamiento, varias soluciones, migración o implementación todo incluido.
                    </p>
                </div>

                <div class="grid gap-5 lg:grid-cols-3">
                    <div v-for="option in activationOptions" :key="option.title" class="rounded-3xl border border-border bg-(--surface-soft) p-5">
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl" :style="{ background: option.color + '1a' }">
                                <component :is="option.icon" class="h-5 w-5" :style="{ color: option.color }" />
                            </span>

                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em]" :style="{ background: option.color + '1a', color: option.color }">
                                {{ option.badge }}
                            </span>
                        </div>

                        <h3 class="text-lg font-black text-(--text)">
                            {{ option.title }}
                        </h3>

                        <p class="mt-2 text-sm leading-relaxed text-muted">
                            {{ option.desc }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="example in option.examples" :key="example" class="rounded-full border border-border bg-(--surface-solid) px-2.5 py-1 text-[10px] font-bold text-muted">
                                {{ example }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== FLUJOS ===================== -->
        <section id="flujos" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Flujos operativos
                    </p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        De una punta a otra, sin duplicar la operación.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    Cada evento entra por una app, pasa por el core y activa a los módulos siguientes.
                    La facturación, el inventario y la fiscalidad viven en un solo lugar.
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="f in flows" :key="f.title" class="lauda-card min-w-0 overflow-hidden rounded-3xl border p-6 transition-shadow hover:shadow-xl">
                    <h3 class="text-[17px] font-bold tracking-tight text-(--text)">
                        {{ f.title }}
                    </h3>

                    <div class="mt-5 flex min-w-0 flex-wrap items-start gap-x-2 gap-y-4">
                        <template v-for="(s, i) in f.steps" :key="s.label">
                            <div class="flex min-w-11 max-w-16 flex-col items-center gap-2 text-center">
                                <span class="grid h-9 w-9 place-items-center rounded-full" :style="{ background: s.color + '1a' }">
                                    <component :is="s.icon" class="h-4 w-4" :style="{ color: s.color }" />
                                </span>

                                <span class="max-w-17 truncate text-[10px] font-medium text-muted">
                                    {{ s.label }}
                                </span>
                            </div>

                            <ArrowRight v-if="i < f.steps.length - 1" class="mt-3 h-3.5 w-3.5 shrink-0 text-(--soft)" />
                        </template>
                    </div>

                    <p class="mt-5 text-[13px] leading-relaxed text-muted">
                        {{ f.desc }}
                    </p>
                </div>
            </div>
        </section>

        <!-- ===================== MÓDULOS EXTENDIDOS ===================== -->
        <section id="modulos" class="mx-auto max-w-360 scroll-mt-24 px-4 pb-16 sm:px-6 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Módulos extendidos
                    </p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        La misma base operativa para más verticales.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
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

                        <span class="rounded-full border border-border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                            Extensión
                        </span>
                    </div>

                    <h3 class="text-base font-black text-(--text)">
                        {{ module.name }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        {{ module.desc }}
                    </p>

                    <div class="mt-4 rounded-2xl border border-border bg-(--surface-soft) p-3 text-xs leading-relaxed text-muted">
                        {{ module.relation }}
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== CONTACTO ===================== -->
        <section id="contacto" class="mx-auto max-w-360 scroll-mt-24 px-4 pb-20 pt-6 sm:px-6 2xl:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-start">
                <div class="lauda-card rounded-4xl border p-6 lg:p-8">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Solicitar demo
                    </p>

                    <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        Cuéntanos qué quieres activar en LaudaAPI.
                    </h2>

                    <p class="mt-4 text-sm leading-relaxed text-muted">
                        Usa este punto de entrada para demos, activación individual, implementación asistida,
                        paquetes todo incluido, migraciones o integraciones entre soluciones.
                    </p>

                    <div class="mt-6 space-y-3 text-sm text-muted">
                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <span>Cada app mantiene su onboarding individual.</span>
                        </div>
                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <span>LaudaAPI.com centraliza los casos con asistencia o varias soluciones.</span>
                        </div>
                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <span>La solicitud puede alimentar CRM y convertirse en oportunidad comercial.</span>
                        </div>
                    </div>
                </div>

                <form class="lauda-card rounded-4xl border p-6 lg:p-8" @submit.prevent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Nombre</span>
                            <input type="text" class="lauda-input" placeholder="Nombre del contacto" />
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Empresa</span>
                            <input type="text" class="lauda-input" placeholder="Nombre de la empresa" />
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">RNC</span>
                            <input type="text" class="lauda-input" placeholder="Opcional" />
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Teléfono</span>
                            <input type="tel" class="lauda-input" placeholder="809-000-0000" />
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Correo</span>
                            <input type="email" class="lauda-input" placeholder="correo@empresa.com" />
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Tipo de solicitud</span>
                            <select class="lauda-input">
                                <option v-for="type in requestTypes" :key="type">{{ type }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Solución de interés</span>
                            <select class="lauda-input">
                                <option>Varias soluciones</option>
                                <option v-for="solution in solutionProducts" :key="solution.name">{{ solution.name }}</option>
                            </select>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Mensaje</span>
                            <textarea rows="5" class="lauda-input resize-none" placeholder="Cuéntanos qué necesitas activar, integrar o configurar." />
                        </label>
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-relaxed text-muted">
                            Próximo paso técnico: conectar este formulario con el endpoint público de contacto/CRM.
                        </p>

                        <Button type="submit" class="gap-2 rounded-xl bg-(--brand) px-6 py-6 text-white hover:bg-(--brand-hover)">
                            Enviar solicitud
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</template>

<style scoped>
/* -------------------------------------------------------------------------- */
/*  Paleta de presentación light / dark                                        */
/* -------------------------------------------------------------------------- */

.lauda-page {
    --brand: #f5333c;
    --brand-hover: #d92730;
    --brand-rgb: 245, 51, 60;

    --page-bg: #fff7f2;
    --page-glow-a: rgba(var(--brand-rgb), 0.12);
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
    --page-glow-a: rgba(var(--brand-rgb), 0.18);
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
    border-color: rgba(var(--brand-rgb), 0.25);
    color: var(--brand);
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
/*  Menú móvil + botones de icono                                              */
/* -------------------------------------------------------------------------- */

.lauda-icon-btn {
    border-color: var(--border);
    background: var(--surface);
    color: var(--text);
    transition: border-color 0.2s ease, color 0.2s ease;
}

.lauda-icon-btn:hover {
    border-color: rgba(var(--brand-rgb), 0.25);
    color: var(--brand);
}

.lauda-mobile-panel {
    border-color: var(--border);
    background: var(--menu-bg);
    backdrop-filter: blur(20px);
    box-shadow: -30px 0 80px rgba(2, 6, 23, 0.3);
}

.lauda-mobile-link {
    transition: background-color 0.2s ease;
}

.lauda-mobile-link:hover {
    background: var(--surface-soft);
}

.lauda-fade-enter-active,
.lauda-fade-leave-active {
    transition: opacity 0.2s ease;
}

.lauda-fade-enter-from,
.lauda-fade-leave-to {
    opacity: 0;
}

/* -------------------------------------------------------------------------- */
/*  Foco visible para navegación por teclado                                   */
/* -------------------------------------------------------------------------- */

.lauda-solutions-trigger:focus-visible,
.lauda-menu-item:focus-visible,
.lauda-mobile-link:focus-visible,
.lauda-icon-btn:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
    border-radius: 12px;
}

/* -------------------------------------------------------------------------- */
/*  Inputs                                                                     */
/* -------------------------------------------------------------------------- */

.lauda-input {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--surface-soft);
    color: var(--text);
    padding: 12px 14px;
    font-size: 14px;
    line-height: 1.4;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.lauda-input::placeholder {
    color: var(--soft);
}

.lauda-input:focus {
    border-color: rgba(var(--brand-rgb), 0.45);
    box-shadow: 0 0 0 4px rgba(var(--brand-rgb), 0.1);
    background: var(--surface-solid);
}

select.lauda-input {
    cursor: pointer;
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
    border-color: rgba(var(--brand-rgb), 0.14);
    box-shadow:
        0 30px 90px -22px rgba(var(--brand-rgb), 0.28),
        0 25px 80px rgba(0, 0, 0, 0.44);
}

.lauda-diagram-scroll {
    width: 100%;
    overflow: hidden;
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
            0 0 60px -12px rgba(var(--brand-rgb), 0.55),
            inset 0 0 36px rgba(var(--brand-rgb), 0.12);
    }

    50% {
        box-shadow:
            0 0 90px -6px rgba(var(--brand-rgb), 0.8),
            inset 0 0 48px rgba(var(--brand-rgb), 0.22);
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

    .lauda-node,
    .lauda-fade-enter-active,
    .lauda-fade-leave-active {
        transition: none;
    }
}
</style>
