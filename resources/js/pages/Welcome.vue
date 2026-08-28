<script setup>
import BrandLogo from '@/components/BrandLogo.vue'
import { Button } from '@/components/ui/button'
import { Head, Link } from '@inertiajs/vue3'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import {
    Activity,
    ArrowRight,
    ArrowUp,
    Boxes,
    Calculator,
    CheckCircle2,
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


const seo = {
    title: 'LAUDAAPI | Soluciones Empresariales y Transformación Digital 360',
    description: 'Ecosistema de soluciones empresariales para Social, CRM, POS, facturación electrónica, cumplimiento, operaciones y datos, con una cuenta central en app.laudaapi.com y Transformación 360 para empresas que necesitan una ruta integral.',
    url: 'https://laudaapi.com',
    siteName: 'LAUDAAPI',
}

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

const transformationPillars = [
    {
        title: 'Estrategia',
        desc: 'Definimos prioridades y un roadmap alineado con los objetivos del negocio.',
        icon: Activity,
        color: brand.main,
    },
    {
        title: 'Personas y procesos',
        desc: 'Organizamos responsabilidades y procesos para facilitar adopción y ejecución.',
        icon: Users,
        color: brand.crm,
    },
    {
        title: 'Tecnología e integración',
        desc: 'Implementamos e integramos las capacidades tecnológicas que el negocio necesita.',
        icon: RefreshCw,
        color: brand.pos,
    },
    {
        title: 'Datos e inteligencia',
        desc: 'Convertimos la operación en indicadores y análisis para tomar mejores decisiones.',
        icon: TrendingUp,
        color: brand.bi,
    },
]

const dashboardPreviewMetrics = [
    {
        label: 'Ventas del período',
        value: 'RD$ 1.8M',
        context: '+12.4% frente al período anterior',
        icon: TrendingUp,
        color: brand.pos,
    },
    {
        label: 'Conversión comercial',
        value: '18.6%',
        context: 'Leads que avanzan desde CRM hasta una venta',
        icon: Users,
        color: brand.crm,
    },
    {
        label: 'Exposición financiera',
        value: 'RD$ 420K',
        context: 'Cobros y pagos que requieren seguimiento',
        icon: Calculator,
        color: brand.tesoreria,
    },
    {
        label: 'Alertas prioritarias',
        value: '6',
        context: 'Situaciones que requieren decisión o acción',
        icon: ShieldCheck,
        color: brand.status,
    },
]

const intelligenceStages = [
    {
        step: '01',
        title: 'Observar',
        desc: 'Consolidar indicadores de las áreas digitalizadas para saber qué está ocurriendo en la empresa.',
        result: 'KPIs confiables y una visión ejecutiva común.',
        icon: Activity,
        color: brand.pos,
    },
    {
        step: '02',
        title: 'Comprender',
        desc: 'Cruzar información comercial, operativa, administrativa y financiera para explicar resultados y tendencias.',
        result: 'Análisis transversal y causas visibles.',
        icon: TrendingUp,
        color: brand.bi,
    },
    {
        step: '03',
        title: 'Anticipar',
        desc: 'Utilizar históricos, tendencias y proyecciones para identificar riesgos, oportunidades y comportamientos futuros.',
        result: 'Proyecciones y señales tempranas para la gerencia.',
        icon: RefreshCw,
        color: brand.crm,
    },
    {
        step: '04',
        title: 'Actuar',
        desc: 'Convertir la información en alertas, recomendaciones y automatizaciones que apoyen decisiones concretas.',
        result: 'Decisiones accionables, automatización e IA cuando aporte valor.',
        icon: Zap,
        color: brand.main,
    },
]

const dashboardViews = [
    {
        title: 'Visión Ejecutiva 360',
        desc: 'Una lectura consolidada para propietarios, gerentes y responsables de área.',
        icon: Activity,
        color: brand.pos,
        items: [
            'Indicadores comerciales, operativos y financieros',
            'Alertas y pendientes que requieren atención',
            'Comparación entre períodos, áreas y objetivos',
        ],
    },
    {
        title: 'Análisis transversal',
        desc: 'Relaciona información que antes vivía separada para entender mejor el comportamiento del negocio.',
        icon: TrendingUp,
        color: brand.bi,
        items: [
            'Social y CRM conectados con ventas reales',
            'Operación relacionada con cobros, compras y cumplimiento',
            'Clientes, productos y proveedores vistos de forma integral',
        ],
    },
    {
        title: 'Decisiones y acción',
        desc: 'La inteligencia debe terminar en decisiones, no únicamente en reportes.',
        icon: Zap,
        color: brand.main,
        items: [
            'Alertas por desviaciones o riesgos relevantes',
            'Proyecciones y recomendaciones para priorizar acciones',
            'Automatizaciones e IA sobre datos confiables',
        ],
    },
]

const flows = [
    {
        title: 'De interacción a cliente y venta',
        business: 'Convertir interés digital en una relación comercial medible y una venta ejecutada.',
        result: 'Trazabilidad desde el canal de origen hasta la venta, facturación y seguimiento del cliente.',
        steps: [
            { label: 'Social', color: brand.social, icon: MessageCircle },
            { label: 'CRM', color: brand.crm, icon: Users },
            { label: 'POS', color: brand.pos, icon: Store },
            { label: 'e-CF', color: brand.ecf, icon: FileText },
            { label: 'Cobranza', color: brand.tesoreria, icon: Calculator },
        ],
    },
    {
        title: 'De pedido a entrega y cobro',
        business: 'Conectar la venta con inventario, preparación, entrega y cierre financiero.',
        result: 'Menos reprocesos, mayor control operativo y una experiencia de cliente consistente de punta a punta.',
        steps: [
            { label: 'Pedido', color: brand.ecommerce, icon: ShoppingCart },
            { label: 'POS', color: brand.pos, icon: Store },
            { label: 'Inventario', color: brand.pos, icon: Boxes },
            { label: 'Entrega', color: brand.delivery, icon: Truck },
            { label: 'Cobro', color: brand.tesoreria, icon: Landmark },
        ],
    },
    {
        title: 'De necesidad de compra a pago y cumplimiento',
        business: 'Organizar compras, recepción, obligaciones con proveedores y control administrativo.',
        result: 'Compras trazables, pagos controlados y mejor preparación de la información fiscal y financiera.',
        steps: [
            { label: 'BYS', color: brand.bys, icon: Boxes },
            { label: 'Recepción', color: brand.pos, icon: CheckCircle2 },
            { label: 'CxP', color: brand.tesoreria, icon: Calculator },
            { label: 'Tesorería', color: brand.tesoreria, icon: Landmark },
            { label: 'Cumplimiento', color: brand.cumplimiento, icon: ShieldCheck },
        ],
    },
    {
        title: 'De operación a decisión',
        business: 'Consolidar señales de toda la empresa para entender qué ocurre y decidir qué hacer después.',
        result: 'KPIs confiables, alertas, análisis transversal y una base preparada para automatización e inteligencia.',
        steps: [
            { label: 'Operación', color: brand.neutral, icon: RefreshCw },
            { label: 'LAUDA Data', color: brand.status, icon: Activity },
            { label: 'BI', color: brand.bi, icon: TrendingUp },
            { label: 'Alertas', color: brand.ecf, icon: ShieldCheck },
            { label: 'Acción', color: brand.main, icon: Zap },
        ],
    },
]

const solutionProducts = [
    { name: 'Social', href: 'https://social.laudaapi.com', desc: 'Captación social, contenido, inbox y leads.', icon: MessageCircle, color: brand.social },
    { name: 'CRM', href: 'https://crm.laudaapi.com', desc: 'Clientes, oportunidades y seguimiento comercial.', icon: Users, color: brand.crm },
    { name: 'POS', href: 'https://pos.laudaapi.com', desc: 'Ventas, inventario, cobros, rutas y despacho.', icon: Store, color: brand.pos },
    { name: 'e-CF', href: 'https://ecf.laudaapi.com', desc: 'Firma, envío, TrackId y respuesta ante DGII.', icon: FileText, color: brand.ecf },
    { name: 'Cumplimiento', href: 'https://cumplimiento.laudaapi.com', desc: 'Obligaciones, documentos y control fiscal.', icon: ShieldCheck, color: brand.cumplimiento },
    { name: 'BYS', href: 'https://bys.laudaapi.com', desc: 'Compras, proveedores, importaciones, recepción y abastecimiento.', icon: Boxes, color: brand.bys },
    { name: 'Tesorería', href: 'https://tesoreria.laudaapi.com', desc: 'Pagos, bancos, caja, conciliación y nómina aprobada.', icon: Landmark, color: brand.tesoreria },
    { name: 'Status', href: 'https://status.laudaapi.com', desc: 'Monitoreo de DGII, APIs, caídas y eventos.', icon: Activity, color: brand.status },
    { name: 'RRHH', href: 'https://rrhh.laudaapi.com', desc: 'Recursos humanos, empleados y procesos internos.', icon: Users, color: brand.rrhh },
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
        name: 'Social',
        href: 'https://social.laudaapi.com',
        stage: 'Presencia y relación digital',
        desc: 'Identidad digital, contenido, canales, interacción, campañas, inbox y captación de oportunidades.',
        role: 'Construye la presencia digital de la empresa y convierte interacciones en oportunidades identificables.',
        connects: 'CRM, campañas, leads y analítica.',
        icon: MessageCircle,
        color: brand.social,
    },
    {
        name: 'CRM',
        href: 'https://crm.laudaapi.com',
        stage: 'Gestión comercial digital',
        desc: 'Contactos, leads, clientes, oportunidades, actividades, pipeline y seguimiento comercial.',
        role: 'Organiza la relación comercial y convierte oportunidades en procesos de venta gestionados.',
        connects: 'Social, POS, Ecommerce y BI.',
        icon: Users,
        color: brand.crm,
    },
    {
        name: 'POS',
        href: 'https://pos.laudaapi.com',
        stage: 'Operación digital',
        desc: 'Pedidos, ventas, servicios, caja, crédito, inventario, almacén, ecommerce, rutas y despacho.',
        role: 'Digitaliza la ejecución comercial y operativa para convertir oportunidades en transacciones controladas.',
        connects: 'CRM, Ecommerce, e-CF, Delivery y BI.',
        icon: Store,
        color: brand.pos,
    },
    {
        name: 'Administración',
        href: '#ecosistema-detalle',
        stage: 'Administración y cumplimiento',
        desc: 'BYS, e-CF, Cumplimiento y Tesorería conectan compras, proveedores, fiscalidad, obligaciones y pagos.',
        role: 'Integra el backoffice al flujo digital de la empresa sin separar la operación de sus procesos administrativos.',
        connects: 'POS, BYS, e-CF, Cumplimiento, Tesorería y Contabilidad.',
        icon: ShieldCheck,
        color: brand.bys,
    },
    {
        name: 'BI / Inteligencia',
        href: '#dashboard',
        stage: 'Empresa inteligente',
        desc: 'LAUDA Data, Dashboard 360 y BI consolidan la operación para generar indicadores, análisis, alertas y decisiones.',
        role: 'Convierte los datos generados por el ecosistema en información para controlar, anticipar y actuar.',
        connects: 'Social, CRM, POS, Administración y LAUDA Data.',
        icon: TrendingUp,
        color: brand.bi,
    },
]

const ecosystemSteps = [
    {
        phase: '01',
        title: 'Presencia y relación digital',
        desc: 'La empresa establece su identidad digital, conecta sus canales y comienza a convertir interacciones en relaciones comerciales organizadas.',
        outcome: 'Presencia digital estructurada y oportunidades capturadas.',
        items: [ 'Social', 'CRM' ],
        icon: MessageCircle,
        color: brand.social,
    },
    {
        phase: '02',
        title: 'Operación digital',
        desc: 'Las oportunidades y pedidos pasan a una operación controlada de ventas, servicios, inventario, cobros y entrega.',
        outcome: 'Operación comercial trazable y conectada.',
        items: [ 'POS', 'Ecommerce', 'Delivery' ],
        icon: Store,
        color: brand.pos,
    },
    {
        phase: '03',
        title: 'Administración y cumplimiento',
        desc: 'Compras, proveedores, facturación electrónica, obligaciones y pagos se incorporan al mismo flujo empresarial.',
        outcome: 'Backoffice digital con mayor control y cumplimiento.',
        items: [ 'BYS', 'e-CF', 'Cumplimiento', 'Tesorería' ],
        icon: ShieldCheck,
        color: brand.ecf,
    },
    {
        phase: '04',
        title: 'Integración del ecosistema',
        desc: 'Las plataformas comparten identidad, eventos y datos para evitar procesos aislados y duplicidad de información.',
        outcome: 'Procesos de extremo a extremo conectados.',
        items: [ 'LAUDAAPI Core', 'APIs', 'Eventos', 'Status' ],
        icon: RefreshCw,
        color: brand.main,
    },
    {
        phase: '05',
        title: 'Datos empresariales',
        desc: 'La información generada por las diferentes áreas se consolida para construir una lectura transversal de la empresa.',
        outcome: 'Fuente confiable para indicadores y análisis.',
        items: [ 'LAUDA Data', 'KPIs', 'Dashboard 360' ],
        icon: Boxes,
        color: brand.bancos,
    },
    {
        phase: '06',
        title: 'Inteligencia y decisiones',
        desc: 'La dirección utiliza BI, tendencias, alertas y análisis para comprender el negocio y decidir con mayor velocidad y evidencia.',
        outcome: 'Empresa orientada a decisiones basadas en datos.',
        items: [ 'BI', 'Analítica', 'Automatización', 'IA' ],
        icon: TrendingUp,
        color: brand.bi,
    },
]


const serviceModels = [
    {
        title: 'LAUDA 360 Guiado',
        level: 'LAUDA orienta',
        desc: 'Modalidad principalmente de autoservicio: su equipo ejecuta el roadmap con metodología LAUDA y recibe asistencia puntual por email.',
        badge: 'Guiado',
        idealFor: 'Empresas con capacidad interna para ejecutar, documentar y coordinar su transformación con poca intervención consultiva.',
        laudaDoes: [
            'Metodología y herramientas de autoservicio',
            'Guías, plantillas y criterios de validación',
            'Soporte y aclaraciones por email',
        ],
        clientDoes: [
            'Completa el diagnóstico y prepara información',
            'Ejecuta las tareas del roadmap',
            'Coordina y valida internamente',
        ],
        icon: CheckCircle2,
        color: brand.pos,
        recommended: false,
    },
    {
        title: 'LAUDA 360 Asistido',
        level: 'Trabajamos juntos',
        desc: 'LAUDA y su equipo ejecutan juntos la transformación, combinando conocimiento del negocio y capacidad de implementación.',
        badge: 'Recomendado',
        idealFor: 'Empresas con responsables internos, pero sin un equipo especializado de transformación digital.',
        laudaDoes: [
            'Diseño de procesos',
            'Implementación e integración',
            'Capacitación y seguimiento',
        ],
        clientDoes: [
            'Aporta conocimiento del negocio',
            'Designa responsables',
            'Valida y participa en la adopción',
        ],
        icon: Users,
        color: brand.crm,
        recommended: true,
    },
    {
        title: 'LAUDA 360 Gestionado',
        level: 'LAUDA lidera',
        desc: 'LAUDA dirige y coordina el programa completo como una oficina externa de transformación digital.',
        badge: 'Gestionado',
        idealFor: 'Empresas sin la estructura, tiempo o experiencia interna para liderar el proceso.',
        laudaDoes: [
            'Dirección integral',
            'Coordinación y ejecución',
            'Gestión del cambio',
        ],
        clientDoes: [
            'Designa un patrocinador',
            'Facilita información',
            'Valida decisiones e hitos',
        ],
        icon: ShieldCheck,
        color: brand.main,
        recommended: false,
    },
]

const roadmapStages = [
    {
        number: '01',
        phase: 'Entender',
        title: 'Diagnóstico y Madurez Digital',
        desc: 'Evaluamos operación, madurez digital y brechas prioritarias.',
        result: 'Diagnóstico y roadmap priorizado.',
        icon: Activity,
        color: brand.main,
    },
    {
        number: '02',
        phase: 'Preparar',
        title: 'Fundamentos para Transformar',
        desc: 'Organizamos información, responsables y procesos esenciales.',
        result: 'Base preparada para digitalizar.',
        icon: ShieldCheck,
        color: brand.cumplimiento,
    },
    {
        number: '03',
        phase: 'Digitalizar',
        title: 'Presencia y Relación Digital',
        desc: 'Organizamos presencia, canales e interacción digital.',
        result: 'Canales digitales conectados al negocio.',
        icon: MessageCircle,
        color: brand.social,
    },
    {
        number: '04',
        phase: 'Digitalizar',
        title: 'Gestión Comercial Digital',
        desc: 'Centralizamos contactos, oportunidades y seguimiento comercial.',
        result: 'Gestión comercial medible y trazable.',
        icon: Users,
        color: brand.crm,
    },
    {
        number: '05',
        phase: 'Digitalizar',
        title: 'Operación Digital',
        desc: 'Digitalizamos ventas, servicios, inventario, cobros y logística según el negocio.',
        result: 'Operación digital y trazable.',
        icon: Store,
        color: brand.pos,
    },
    {
        number: '06',
        phase: 'Digitalizar',
        title: 'Administración y Cumplimiento Digital',
        desc: 'Digitalizamos compras, proveedores, fiscalidad y administración.',
        result: 'Backoffice digital y controlado.',
        icon: Calculator,
        color: brand.bys,
    },
    {
        number: '07',
        phase: 'Conectar',
        title: 'Empresa Conectada',
        desc: 'Integramos áreas y datos para eliminar silos y reprocesos.',
        result: 'Procesos conectados y automatizables.',
        icon: RefreshCw,
        color: brand.status,
    },
    {
        number: '08',
        phase: 'Decidir',
        title: 'Empresa Inteligente',
        desc: 'Consolidamos KPIs, dashboards y analítica para dirigir el negocio.',
        result: 'Decisiones basadas en datos.',
        icon: TrendingUp,
        color: brand.bi,
    },
]

const companySizes = [
    '1 a 10 personas',
    '11 a 50 personas',
    '51 a 200 personas',
    'Más de 200 personas',
]

const transformationChallenges = [
    'No sé por dónde comenzar',
    'Organizar procesos y reducir trabajo manual',
    'Mejorar captación, clientes y ventas',
    'Digitalizar la operación diaria',
    'Integrar administración, fiscalidad y cumplimiento',
    'Centralizar datos, indicadores y BI',
    'Conectar sistemas que hoy trabajan separados',
]

const assistanceLevels = [
    'Quiero que LAUDA me recomiende la modalidad',
    'LAUDA 360 Guiado — autoservicio + soporte por email',
    'LAUDA 360 Asistido — trabajo conjunto',
    'LAUDA 360 Gestionado — LAUDA lidera',
]

/*
|--------------------------------------------------------------------------
| Formulario público de Transformación Digital 360
|--------------------------------------------------------------------------
| Tu backend responde JSON desde ContactRequestController, por eso este form
| se envía con fetch en vez de Inertia useForm.
*/
const CONTACT_REQUEST_ENDPOINT = '/contact'

const contactSubmitted = ref(false)
const contactProcessing = ref(false)
const contactErrors = ref({})
const contactSuccessMessage = ref('')
const contactToastVisible = ref(false)
let contactToastTimer = null

function showContactToast() {
    contactToastVisible.value = true

    if (contactToastTimer) {
        window.clearTimeout(contactToastTimer)
    }

    contactToastTimer = window.setTimeout(() => {
        contactToastVisible.value = false
        contactToastTimer = null
    }, 5000)
}

function closeContactToast() {
    contactToastVisible.value = false

    if (contactToastTimer) {
        window.clearTimeout(contactToastTimer)
        contactToastTimer = null
    }
}

const contactForm = ref({
    name: '',
    company: '',
    phone: '',
    email: '',
    company_size: '',
    main_challenge: 'No sé por dónde comenzar',
    assistance_level: 'Quiero que LAUDA me recomiende la modalidad',
    message: '',
    terms: true,
})

function resetContactForm() {
    contactForm.value = {
        name: '',
        company: '',
        phone: '',
        email: '',
        company_size: '',
        main_challenge: 'No sé por dónde comenzar',
        assistance_level: 'Quiero que LAUDA me recomiende la modalidad',
        message: '',
        terms: true,
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function getXsrfCookie() {
    const prefix = 'XSRF-TOKEN='
    const item = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith(prefix))

    if (!item) return ''

    const value = item.slice(prefix.length)

    try {
        return decodeURIComponent(value)
    } catch {
        return value
    }
}

function getContactCsrfHeaders() {
    // Preferimos la cookie actual porque Laravel puede renovar/rotar la sesión
    // mientras una pestaña del landing permanece abierta.
    const xsrfToken = getXsrfCookie()

    if (xsrfToken) {
        return { 'X-XSRF-TOKEN': xsrfToken }
    }

    // Fallback para la primera carga o navegadores donde la cookie todavía
    // no esté disponible.
    const metaToken = getCsrfToken()

    return metaToken ? { 'X-CSRF-TOKEN': metaToken } : {}
}

async function refreshContactCsrf() {
    // GET seguro: Laravel renueva XSRF-TOKEN + cookie de sesión.
    // cache:no-store evita reutilizar una respuesta local antigua.
    const response = await fetch('/', {
        method: 'GET',
        headers: {
            Accept: 'text/html',
        },
        credentials: 'same-origin',
        cache: 'no-store',
    })

    if (!response.ok) {
        return false
    }

    // Actualizamos también el meta de la pestaña para mantener ambos
    // mecanismos sincronizados.
    const html = await response.text()
    const documentFresh = new DOMParser().parseFromString(html, 'text/html')
    const freshMetaToken = documentFresh
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') || ''

    const currentMeta = document.querySelector('meta[name="csrf-token"]')

    if (currentMeta && freshMetaToken) {
        currentMeta.setAttribute('content', freshMetaToken)
    }

    return Boolean(getXsrfCookie() || freshMetaToken)
}

async function sendContactRequest(payload) {
    return fetch(CONTACT_REQUEST_ENDPOINT, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...getContactCsrfHeaders(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    })
}

function buildContactPayload() {
    const form = contactForm.value

    // Compatible con ContactRequest:
    // - Los campos base continúan usando las columnas actuales.
    // - El diagnóstico siempre entra por un único topic comercial.
    // - El contexto adicional se conserva en metadata y también en message
    //   para mantener compatibilidad con los correos actuales.
    return {
        name: form.name,
        company: form.company,
        email: form.email,
        phone: form.phone,
        topic: 'Solicitud de acceso al Diagnóstico LAUDA 360',
        terms: form.terms,
        metadata: {
            source: 'laudaapi.com',
            request_type: 'digital_diagnosis_access_request',
            company_size: form.company_size || null,
            main_challenge: form.main_challenge,
            assistance_level: form.assistance_level,
            intake_type: 'digital_transformation_360',
            diagnosis_access: 'private_invitation',
        },
        message: [
            'Solicitud: Acceso al Diagnóstico LAUDA 360',
            `Tamaño aproximado: ${form.company_size || 'No indicado'}`,
            `Reto principal: ${form.main_challenge}`,
            `Acompañamiento: ${form.assistance_level}`,
            '',
            'Contexto adicional:',
            form.message || 'No indicado',
            '',
            'Origen: laudaapi.com',
        ].join('\n'),
    }
}

async function submitContact() {
    contactSubmitted.value = false
    contactSuccessMessage.value = ''
    contactErrors.value = {}
    contactProcessing.value = true

    try {
        const payload = buildContactPayload()

        let response = await sendContactRequest(payload)

        // Un 419 ocurre antes del controller, por lo que este reintento no
        // puede duplicar una solicitud ya creada.
        if (response.status === 419) {
            const refreshed = await refreshContactCsrf()

            if (refreshed) {
                response = await sendContactRequest(payload)
            }
        }

        const data = await response.json().catch(() => ({}))

        if (response.status === 419) {
            contactErrors.value = {
                general: 'La sesión de seguridad expiró. Actualice la página e intente nuevamente.',
            }
            return
        }

        if (response.status === 422) {
            contactErrors.value = data.errors || {}
            return
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'No se pudo enviar la solicitud en este momento.')
        }

        contactSubmitted.value = true
        contactSuccessMessage.value = data.message || 'Gracias. LAUDA revisará la solicitud y, si procede, enviará las instrucciones para acceder al Diagnóstico LAUDA 360 de forma privada.'
        resetContactForm()
        showContactToast()
    } catch (error) {
        contactErrors.value = {
            general: error?.message || 'Ocurrió un error al procesar la solicitud.',
        }
    } finally {
        contactProcessing.value = false
    }
}

const extendedModules = [
    {
        name: 'RRHH',
        category: 'Gestión interna',
        desc: 'Empleados, equipos, asistencia y procesos internos.',
        trigger: 'La transformación requiere digitalizar la gestión del talento y la operación interna.',
        icon: Users,
        color: brand.rrhh,
    },
    {
        name: 'Proyectos',
        category: 'Gestión interna',
        desc: 'Planificación, tareas, entregables y seguimiento de ejecución.',
        trigger: 'La empresa necesita controlar proyectos, servicios o iniciativas con responsables y fechas.',
        icon: Boxes,
        color: brand.proyectos,
    },
    {
        name: 'Eventos',
        category: 'Operación especializada',
        desc: 'Actividades, invitados, ventas y coordinación operativa.',
        trigger: 'Los eventos forman parte relevante del modelo comercial u operativo de la empresa.',
        icon: Activity,
        color: brand.eventos,
    },
    {
        name: 'Transporte personal',
        category: 'Operación especializada',
        desc: 'Rutas, unidades, pasajeros, horarios y evidencia de servicio.',
        trigger: 'La movilidad de personal requiere planificación, trazabilidad y control.',
        icon: Truck,
        color: brand.transporte,
    },
    {
        name: 'Servicios de grúas',
        category: 'Vertical especializado',
        desc: 'Asignación, asistencia, tracking, evidencias y cobro del servicio.',
        trigger: 'El negocio presta servicios de asistencia vial o necesita una operación especializada de grúas.',
        icon: Truck,
        color: brand.gruas,
    },
    {
        name: 'Loans',
        category: 'Vertical especializado',
        desc: 'Préstamos, cuotas, cartera, mora y cobranza.',
        trigger: 'El modelo de negocio incluye financiamiento o administración recurrente de cartera.',
        icon: Calculator,
        color: brand.loans,
    },
    {
        name: 'Dealers',
        category: 'Vertical especializado',
        desc: 'Inventario vehicular, clientes, ventas y financiamiento.',
        trigger: 'La empresa opera venta de vehículos y requiere procesos específicos del sector.',
        icon: Store,
        color: brand.dealers,
    },
]

const mainNavLinks = [
    { label: 'App Hub', href: '#app-hub', primary: true },
    { label: 'Transformación 360', href: '#lauda360' },
    { label: 'Ecosistema', href: '#ecosistema-detalle' },
    { label: 'Soluciones', href: '#soluciones' },
    { label: 'Inteligencia', href: '#dashboard' },
    { label: 'Diagnóstico', href: '#contacto' },
]

const currentYear = new Date().getFullYear()

const footerQuickLinks = [
    { label: 'App Hub', href: '#app-hub' },
    { label: 'Soluciones', href: '#soluciones' },
    { label: 'Transformación 360', href: '#lauda360' },
    { label: 'Ecosistema', href: '#ecosistema-detalle' },
    { label: 'Inteligencia', href: '#dashboard' },
    { label: 'Diagnóstico', href: '#contacto' },
]

const footerLegalLinks = [
    { label: 'Legal', href: '/legal' },
    { label: 'Términos', href: '/legal/terminos' },
    { label: 'Privacidad', href: '/legal/privacidad' },
]

/* -------------------------------------------------------------------------- */
/*  Estado de UI                                                               */
/* -------------------------------------------------------------------------- */

const mobileMenuOpen = ref(false)
const isDarkMode = ref(false)
const showBackToTop = ref(false)
const navCompact = ref(false)
const scrollProgress = ref(0)
const activeSection = ref('app-hub')

const hamburgerRef = ref(null)
const mobileCloseRef = ref(null)

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
    const target = document.getElementById(id)
    if (!target) return

    const navOffset = window.innerWidth < 1024 ? 72 : 80
    const top = target.getBoundingClientRect().top + window.scrollY - navOffset

    window.scrollTo({
        top: Math.max(0, top),
        behavior: 'smooth',
    })
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

function updateActiveSection() {
    const offset = 150

    // Al llegar al final de la página, fuerza Diagnóstico como sección activa.
    if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 24) {
        activeSection.value = 'contacto'
        return
    }

    let current = 'app-hub'

    for (const link of mainNavLinks) {
        const id = link.href.replace('#', '')
        const section = document.getElementById(id)

        if (section && section.getBoundingClientRect().top <= offset) {
            current = id
        }
    }

    activeSection.value = current
}

function handleScroll() {
    const scrollY = window.scrollY
    const scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight)

    navCompact.value = scrollY > 80
    showBackToTop.value = scrollY > 520
    scrollProgress.value = Math.min(100, Math.max(0, (scrollY / scrollable) * 100))
    updateActiveSection()
}

function goLogin() {
    window.location.assign('https://app.laudaapi.com/login')
}

function goRegister() {
    window.location.assign('https://app.laudaapi.com/register')
}

function goAppHub() {
    window.location.assign('https://app.laudaapi.com')
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
    if (event.key === 'Escape' && mobileMenuOpen.value) {
        closeMobileMenu(true)
    }
}

/* Bloquea el scroll del body mientras el menú móvil está abierto */
watch(mobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
})

onMounted(() => {
    const savedMode = window.localStorage.getItem('laudaapi-presentation-mode')
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches

    setPresentationMode(savedMode ? savedMode === 'dark' : Boolean(prefersDark))
    handleScroll()
    window.addEventListener('keydown', handleKeydown)
    window.addEventListener('scroll', handleScroll, { passive: true })
})

onBeforeUnmount(() => {
    document.body.style.overflow = ''
    window.removeEventListener('keydown', handleKeydown)
    window.removeEventListener('scroll', handleScroll)

    if (contactToastTimer) {
        window.clearTimeout(contactToastTimer)
        contactToastTimer = null
    }
})
</script>

<template>

    <Head :title="seo.title">
        <meta name="description" :content="seo.description" />
        <meta name="robots" content="index, follow" />
        <meta name="author" content="LaudaAPI" />
        <meta name="application-name" content="LaudaAPI" />
        <meta name="theme-color" content="#F5333C" />
        <meta name="keywords" content="LAUDAAPI, app.laudaapi.com, soluciones empresariales, transformación digital, transformación digital empresarial, LAUDA 360, CRM, Social, POS, e-CF, cumplimiento, compras, Business Intelligence, BI, República Dominicana" />

        <link rel="canonical" :href="seo.url" />

        <meta property="og:type" content="website" />
        <meta property="og:site_name" :content="seo.siteName" />
        <meta property="og:title" :content="seo.title" />
        <meta property="og:description" :content="seo.description" />
        <meta property="og:url" :content="seo.url" />
        <meta property="og:locale" content="es_DO" />

        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" :content="seo.title" />
        <meta name="twitter:description" :content="seo.description" />
    </Head>

    <div :class="[ 'lauda-page min-h-screen antialiased', { 'lauda-page--dark': isDarkMode } ]">
        <Transition name="lauda-toast">
            <div v-if="contactToastVisible" class="lauda-toast fixed right-4 top-24 z-70 w-[calc(100%-2rem)] max-w-sm rounded-2xl border px-4 py-3 shadow-2xl sm:right-6 sm:w-full" role="status" aria-live="polite" aria-atomic="true">
                <div class="flex items-start gap-3">
                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#22C55E]/12 text-[#16A34A]">
                        <CheckCircle2 class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1 pt-0.5">
                        <p class="text-sm font-black text-(--text)">Su solicitud ha sido enviada.</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-muted">
                            LAUDA revisará la información y le contactará con los próximos pasos.
                        </p>
                    </div>

                    <button type="button" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors hover:bg-black/5 hover:text-(--text) focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-(--brand) dark:hover:bg-white/6" aria-label="Cerrar notificación" @click="closeContactToast">
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </Transition>
        <!-- ===================== NAV ===================== -->
        <nav class="lauda-nav fixed inset-x-0 top-0 z-50 border-b backdrop-blur-xl" :class="{ 'lauda-nav--compact': navCompact }">
            <div class="lauda-nav-inner mx-auto flex h-19 max-w-none items-center gap-3 px-4 sm:gap-6 sm:px-6 lg:gap-8 lg:px-8 2xl:px-10">
                <Link href="/" class="lauda-nav-brand flex items-center gap-2.5">
                    <div class="lauda-brand-mark grid h-11 w-11 place-items-center rounded-xl bg-(--brand) font-black text-white shadow-xl shadow-[#F5333C]/35">
                        <BrandLogo class="lauda-brand-icon h-6 w-6 text-white" />
                    </div>

                    <div class="leading-none">
                        <div class="lauda-brand-name text-[20px] font-extrabold tracking-tight text-(--text)">LAUDA</div>
                        <div class="lauda-brand-tagline mt-0.5 text-[9px] font-semibold tracking-[0.2em] text-(--brand)">
                            API DIGITAL
                        </div>
                    </div>
                </Link>

                <div class="ml-auto hidden items-center gap-4 text-[14px] font-semibold text-muted xl:gap-6 xl:text-[15px] lg:flex">
                    <a v-for="link in mainNavLinks" :key="link.href" :href="link.href" class="lauda-nav-link relative flex h-19 items-center whitespace-nowrap transition-colors hover:text-(--text)" :class="[
                        link.primary ? 'font-black' : '',
                        activeSection === link.href.replace('#', '')
                            ? 'text-(--text) after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-full after:bg-(--brand)'
                            : link.primary
                                ? 'text-(--brand)'
                                : ''
                    ]" :aria-current="activeSection === link.href.replace('#', '') ? 'location' : undefined" @click.prevent="scrollToId(link.href.replace('#', ''))">
                        {{ link.label }}
                    </a>
                </div>

                <button type="button" class="lauda-mode-toggle lauda-nav-action ml-auto grid h-11 w-11 shrink-0 place-items-center rounded-xl border transition lg:ml-0" :aria-label="isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'" :title="isDarkMode ? 'Modo claro' : 'Modo oscuro'" @click="togglePresentationMode">
                    <component :is="isDarkMode ? Sun : Moon" class="h-4 w-4" />
                </button>

                <Button class="lauda-login-button hidden gap-2 rounded-xl bg-[#0B0B12] px-6 py-6 text-white hover:bg-black lg:inline-flex" @click="goLogin">
                    <User class="h-4 w-4" />
                    Iniciar sesión
                </Button>

                <Button
                    data-app-hub-register="desktop"
                    class="hidden rounded-xl bg-(--brand) px-6 py-6 font-black text-white hover:bg-[#d92731] lg:inline-flex"
                    @click="goRegister"
                >
                    Crear cuenta
                </Button>

                <!-- Hamburguesa (solo móvil / tablet) -->
                <button ref="hamburgerRef" type="button" class="lauda-icon-btn lauda-nav-action ml-1 grid h-11 w-11 place-items-center rounded-xl border lg:hidden" aria-controls="mobile-menu" :aria-expanded="mobileMenuOpen" aria-label="Abrir menú de navegación" @click="openMobileMenu">
                    <Menu class="h-5 w-5" />
                </button>
            </div>

            <div class="lauda-scroll-progress" aria-hidden="true">
                <div class="lauda-scroll-progress__bar" :style="{ width: scrollProgress + '%' }" />
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

                    <div class="mt-6 min-h-0 flex-1 overflow-y-auto pr-1">
                        <nav class="flex flex-col gap-1">
                            <a v-for="link in mainNavLinks" :key="link.href" :href="link.href" class="lauda-mobile-link rounded-xl px-3 py-3 text-base font-semibold text-(--text)" :class="[
                                link.primary ? 'font-black' : '',
                                activeSection === link.href.replace('#', '')
                                    ? 'bg-(--brand)/10 text-(--brand)'
                                    : ''
                            ]" :aria-current="activeSection === link.href.replace('#', '') ? 'location' : undefined" @click.prevent="closeMobileMenu(); scrollToId(link.href.replace('#', ''))">
                                {{ link.label }}
                            </a>
                        </nav>
                    </div>

                    <div class="mt-4 grid gap-2 border-t border-border pt-4">
                        <Button class="w-full gap-2 rounded-xl bg-[#0B0B12] py-6 text-white hover:bg-black" @click="closeMobileMenu(); goLogin()">
                            <User class="h-4 w-4" />
                            Iniciar sesión
                        </Button>

                        <Button
                            data-app-hub-register="mobile"
                            class="w-full rounded-xl bg-(--brand) py-6 font-black text-white hover:bg-[#d92731]"
                            @click="closeMobileMenu(); goRegister()"
                        >
                            Crear cuenta
                        </Button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ===================== HERO ===================== -->
        <section class="lauda-hero">
            <div class="lauda-hero__layout">
                <!-- Columna izquierda: propuesta de valor -->
                <div class="lauda-hero__copy">
                    <span class="inline-flex items-center gap-2 rounded-full bg-(--brand)/10 px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-(--brand)">
                        <span class="h-1.5 w-1.5 rounded-full bg-(--brand)" />
                        Ecosistema empresarial LAUDAAPI
                    </span>

                    <h1 class="mt-5 text-[34px] font-extrabold leading-[1.01] tracking-[-0.04em] text-(--text) sm:text-[46px] lg:text-[52px]">
                        Soluciones conectadas para operar y transformar
                        <span class="text-(--brand)">su empresa.</span>
                    </h1>

                    <p class="mt-6 max-w-145 text-[17px] leading-relaxed text-muted">
                        Contrate las soluciones que necesita, administre su relación con LAUDAAPI desde un solo lugar
                        o construya una ruta completa de transformación con LAUDA 360.
                    </p>

                    <div class="mt-6 rounded-2xl border border-border bg-(--surface) p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-(--brand)/10 text-(--brand)">
                                <RefreshCw class="h-5 w-5" />
                            </span>

                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-(--brand)">
                                    Un ecosistema, dos formas de comenzar
                                </p>
                                <p class="mt-1.5 text-sm leading-relaxed text-muted">
                                    Contrate una solución directamente o utilice Transformación 360 para definir qué capacidades necesita su empresa y en qué orden implementarlas.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <Button class="w-full justify-center gap-2 rounded-xl bg-(--brand) px-6 py-6 text-white hover:bg-(--brand-hover) sm:w-auto" @click="scrollToId('soluciones')">
                            Explorar soluciones
                            <ArrowRight class="h-4 w-4" />
                        </Button>

                        <Button variant="outline" class="lauda-outline-button w-full justify-center gap-2 rounded-xl px-6 py-6 sm:w-auto" @click="scrollToId('lauda360')">
                            Conocer Transformación 360
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-widest text-(--soft)">
                        <span class="rounded-full border border-border bg-(--surface) px-3 py-1.5">Soluciones directas</span>
                        <span class="rounded-full border border-(--brand)/20 bg-(--brand)/10 px-3 py-1.5 text-(--brand)">App Hub</span>
                        <span class="rounded-full border border-border bg-(--surface) px-3 py-1.5">Transformación 360</span>
                    </div>
                </div>

                <!-- Columna derecha: evolución de la empresa -->
                <div class="lauda-hero__panel">
                    <div class="flex flex-col gap-4 border-b border-white/8 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-[#F5333C]">
                                <span class="h-2 w-2 rounded-full bg-[#F5333C]" />
                                El objetivo de la transformación
                            </div>
                            <h2 class="mt-2 max-w-xl text-2xl font-black tracking-tight text-white sm:text-[30px]">
                                De empresa tradicional a empresa conectada e inteligente.
                            </h2>
                            <p class="mt-2 max-w-xl text-sm leading-relaxed text-[#9AA1B8]">
                                La tecnología llega en el momento correcto: primero entendemos y organizamos la empresa;
                                después digitalizamos, conectamos y convertimos sus datos en decisiones.
                            </p>
                        </div>

                        <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-full border border-[#22C55E]/20 bg-[#22C55E]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] text-[#4ADE80]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#4ADE80]" />
                            LAUDA 360
                        </span>
                    </div>

                    <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/7 bg-[#111625] p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/6 text-[#A8B0C3]">
                                    <Boxes class="h-5 w-5" />
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-[#6F7890]">01</span>
                            </div>
                            <p class="mt-3 text-sm font-black text-white">Empresa tradicional</p>
                            <p class="mt-1.5 text-xs leading-relaxed text-[#9AA1B8]">
                                Procesos manuales, información dispersa, Excel, WhatsApp y sistemas aislados.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[#3B82F6]/20 bg-[#3B82F6]/8 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#3B82F6]/15 text-[#60A5FA]">
                                    <Zap class="h-5 w-5" />
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-[#60A5FA]">02</span>
                            </div>
                            <p class="mt-3 text-sm font-black text-white">Empresa digital</p>
                            <p class="mt-1.5 text-xs leading-relaxed text-[#AAB4CA]">
                                Canales, información y procesos prioritarios pasan a operar digitalmente.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[#A855F7]/20 bg-[#A855F7]/8 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#A855F7]/15 text-[#C084FC]">
                                    <RefreshCw class="h-5 w-5" />
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-[#C084FC]">03</span>
                            </div>
                            <p class="mt-3 text-sm font-black text-white">Empresa conectada</p>
                            <p class="mt-1.5 text-xs leading-relaxed text-[#AAB4CA]">
                                Comercial, operación y administración comparten información y flujos integrados.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[#22C55E]/20 bg-[#22C55E]/8 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#22C55E]/15 text-[#4ADE80]">
                                    <TrendingUp class="h-5 w-5" />
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-[#4ADE80]">04</span>
                            </div>
                            <p class="mt-3 text-sm font-black text-white">Empresa inteligente</p>
                            <p class="mt-1.5 text-xs leading-relaxed text-[#AAB4CA]">
                                Data, BI, indicadores y automatización convierten la operación en mejores decisiones.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-3xl border border-white/7 bg-[#0D1120] p-4 sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#F5333C]">
                                    Ecosistema tecnológico LAUDAAPI
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-[#9AA1B8]">
                                    Las soluciones pueden contratarse directamente o incorporarse progresivamente como parte del roadmap de Transformación 360.
                                </p>
                            </div>

                            <button type="button" class="inline-flex w-fit items-center gap-1.5 text-xs font-black text-[#F5333C] transition hover:text-[#FF6970]" @click="scrollToId('ecosistema-detalle')">
                                Ver ecosistema
                                <ArrowRight class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <div class="mt-4 flex min-w-0 flex-wrap items-center gap-2">
                            <span class="rounded-full border border-[#EC4899]/20 bg-[#EC4899]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-[#F472B6]">Social</span>
                            <ArrowRight class="h-3.5 w-3.5 text-[#596277]" />
                            <span class="rounded-full border border-[#A855F7]/20 bg-[#A855F7]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-[#C084FC]">CRM</span>
                            <ArrowRight class="h-3.5 w-3.5 text-[#596277]" />
                            <span class="rounded-full border border-[#22C55E]/20 bg-[#22C55E]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-[#4ADE80]">POS</span>
                            <ArrowRight class="h-3.5 w-3.5 text-[#596277]" />
                            <span class="rounded-full border border-[#F59E0B]/20 bg-[#F59E0B]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-[#FBBF24]">Administración</span>
                            <ArrowRight class="h-3.5 w-3.5 text-[#596277]" />
                            <span class="rounded-full border border-[#3B82F6]/20 bg-[#3B82F6]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-[#60A5FA]">Data</span>
                            <ArrowRight class="h-3.5 w-3.5 text-[#596277]" />
                            <span class="rounded-full border border-[#3B82F6]/20 bg-[#3B82F6]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-[#93C5FD]">BI</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ===================== APP HUB ===================== -->
        <section id="app-hub" class="mx-auto max-w-360 scroll-mt-24 px-4 py-8 sm:px-6 sm:py-11 2xl:px-8">
            <div class="overflow-hidden rounded-3xl border border-border bg-(--surface) shadow-sm">
                <div class="grid gap-0 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                    <div class="p-5 sm:p-6 lg:p-8">
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                                app.laudaapi.com
                            </p>
                            <span class="rounded-full border border-(--brand)/20 bg-(--brand)/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.12em] text-(--brand)">
                                Mi cuenta LAUDAAPI
                            </span>
                        </div>

                        <h2 class="mt-3 max-w-3xl text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                            Un solo centro de control para su relación con todo el ecosistema.
                        </h2>

                        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-muted">
                            app.laudaapi.com centraliza su cuenta, empresa, contratación, suscripción, facturas, pagos,
                            soluciones habilitadas y acceso. Cada solución mantiene su propia plataforma y operación.
                        </p>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                                <div class="flex items-start gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-(--brand)/10 text-(--brand)">
                                        <User class="h-4.5 w-4.5" />
                                    </span>
                                    <div>
                                        <p class="text-sm font-black text-(--text)">Mi empresa y usuarios</p>
                                        <p class="mt-1 text-xs leading-relaxed text-muted">
                                            Identidad, organización, equipo y acceso central.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                                <div class="flex items-start gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#22C55E]/10 text-[#16A34A]">
                                        <Boxes class="h-4.5 w-4.5" />
                                    </span>
                                    <div>
                                        <p class="text-sm font-black text-(--text)">Mis soluciones</p>
                                        <p class="mt-1 text-xs leading-relaxed text-muted">
                                            Planes contratados, estado de acceso y launcher de soluciones.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                                <div class="flex items-start gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#3B82F6]/10 text-[#2563EB]">
                                        <FileText class="h-4.5 w-4.5" />
                                    </span>
                                    <div>
                                        <p class="text-sm font-black text-(--text)">Suscripción, facturas y pagos</p>
                                        <p class="mt-1 text-xs leading-relaxed text-muted">
                                            Una relación comercial central para múltiples soluciones.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                                <div class="flex items-start gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#A855F7]/10 text-[#9333EA]">
                                        <RefreshCw class="h-4.5 w-4.5" />
                                    </span>
                                    <div>
                                        <p class="text-sm font-black text-(--text)">Transformación 360</p>
                                        <p class="mt-1 text-xs leading-relaxed text-muted">
                                            Diagnóstico, roadmap, implementación y seguimiento desde el mismo espacio.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            <Button class="w-full justify-center gap-2 rounded-xl bg-[#0B0B12] px-6 py-6 text-white hover:bg-black sm:w-auto" @click="goAppHub">
                                <User class="h-4 w-4" />
                                Ir a app.laudaapi.com
                            </Button>

                            <Button variant="outline" class="lauda-outline-button w-full justify-center gap-2 rounded-xl px-6 py-6 sm:w-auto" @click="scrollToId('soluciones')">
                                Ver soluciones
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div class="border-t border-border bg-(--surface-soft) p-5 sm:p-6 lg:border-l lg:border-t-0 lg:p-8">
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-(--brand)">
                            Dos formas de comenzar
                        </p>
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-(--text)">
                            Comience por la solución o por la transformación.
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-muted">
                            Ambos caminos convergen en la misma cuenta central y pueden crecer con nuevas capacidades cuando el negocio lo requiera.
                        </p>

                        <div class="mt-6 grid gap-4">
                            <article class="rounded-3xl border border-border bg-(--surface) p-5">
                                <div class="flex items-start gap-4">
                                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#22C55E]/10 text-[#16A34A]">
                                        <Store class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-[#16A34A]">
                                            Ya sé qué necesito
                                        </p>
                                        <h4 class="mt-1 text-lg font-black text-(--text)">
                                            Contratar una solución
                                        </h4>
                                        <p class="mt-2 text-sm leading-relaxed text-muted">
                                            Explore Social, CRM, POS, e-CF y otras soluciones, conozca su producto y gestione su contratación desde su cuenta LAUDAAPI.
                                        </p>
                                        <button type="button" class="mt-4 inline-flex items-center gap-2 text-sm font-black text-(--brand)" @click="scrollToId('soluciones')">
                                            Explorar soluciones
                                            <ArrowRight class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-3xl border border-border bg-(--surface) p-5">
                                <div class="flex items-start gap-4">
                                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-(--brand)/10 text-(--brand)">
                                        <Activity class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-(--brand)">
                                            Necesito definir la ruta
                                        </p>
                                        <h4 class="mt-1 text-lg font-black text-(--text)">
                                            Transformación 360
                                        </h4>
                                        <p class="mt-2 text-sm leading-relaxed text-muted">
                                            Diagnostique su empresa, priorice capacidades y ejecute un roadmap progresivo con el nivel de acompañamiento adecuado.
                                        </p>
                                        <button type="button" class="mt-4 inline-flex items-center gap-2 text-sm font-black text-(--brand)" @click="scrollToId('lauda360')">
                                            Conocer Transformación 360
                                            <ArrowRight class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div class="mt-5 rounded-2xl border border-(--brand)/15 bg-(--brand)/5 p-4">
                            <p class="text-sm font-black text-(--text)">
                                Una cuenta. Una relación comercial. Múltiples soluciones independientes.
                            </p>
                            <p class="mt-1 text-xs leading-relaxed text-muted">
                                El Hub centraliza identidad, contratación y acceso; Social, CRM, POS, e-CF y las demás soluciones conservan su lógica operativa.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== LAUDA 360 ===================== -->
        <section id="lauda360" class="mx-auto max-w-360 scroll-mt-24 px-4 py-8 sm:px-6 sm:py-10 2xl:px-8">
            <div class="mb-7 grid gap-5 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Transformación 360 · Ruta guiada
                    </p>
                    <h2 class="mt-2 max-w-3xl text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                        Una ruta integral para empresas que necesitan saber qué transformar, en qué orden y cómo ejecutarlo.
                    </h2>
                </div>

                <p class="max-w-2xl text-sm leading-relaxed text-muted lg:justify-self-end">
                    Es una de las formas de avanzar dentro de LAUDAAPI: partimos de la realidad de cada empresa,
                    definimos prioridades y acompañamos la ejecución hasta que los cambios formen parte de la operación diaria.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,0.78fr)_minmax(0,1.22fr)]">
                <!-- Qué obtiene el cliente -->
                <div class="lauda-card rounded-3xl border p-5 lg:p-6">
                    <div class="flex items-start gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-(--brand)/10 text-(--brand)">
                            <Activity class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-(--brand)">
                                Punto de partida
                            </p>
                            <h3 class="mt-1 text-xl font-black text-(--text)">
                                El diagnóstico define por dónde comenzar.
                            </h3>
                        </div>
                    </div>

                    <div class="mt-5 space-y-2.5">
                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <div>
                                <p class="text-sm font-black text-(--text)">Diagnóstico claro</p>
                                <p class="mt-1 text-xs leading-relaxed text-muted">
                                    Identificamos madurez, brechas y oportunidades prioritarias.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <div>
                                <p class="text-sm font-black text-(--text)">Roadmap por prioridades</p>
                                <p class="mt-1 text-xs leading-relaxed text-muted">
                                    Ordenamos objetivos y etapas según impacto y capacidad real.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <div>
                                <p class="text-sm font-black text-(--text)">Acompañamiento hasta la adopción</p>
                                <p class="mt-1 text-xs leading-relaxed text-muted">
                                    Acompañamos implementación, integración y adopción real.
                                </p>
                            </div>
                        </div>
                    </div>

                    <Button variant="outline" class="lauda-outline-button mt-6 w-full justify-center gap-2 rounded-xl px-5 py-5" @click="scrollToId('roadmap')">
                        Ver roadmap de transformación
                        <ArrowRight class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Qué transforma -->
                <div>
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-(--brand)">
                                Qué transformamos
                            </p>
                            <h3 class="mt-1 text-xl font-black text-(--text)">
                                Cuatro dimensiones que deben avanzar juntas.
                            </h3>
                        </div>
                        <span class="hidden rounded-full border border-border bg-(--surface) px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft) sm:inline-flex">
                            Modelo 360
                        </span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="(pillar, index) in transformationPillars" :key="pillar.title" class="lauda-card rounded-3xl border p-5">
                            <div class="flex items-start justify-between gap-4">
                                <span class="grid h-12 w-12 place-items-center rounded-2xl" :style="{ background: pillar.color + '1a' }">
                                    <component :is="pillar.icon" class="h-5 w-5" :style="{ color: pillar.color }" />
                                </span>
                                <span class="text-[11px] font-black tracking-[0.14em] text-(--soft)">
                                    0{{ index + 1 }}
                                </span>
                            </div>
                            <h3 class="mt-4 text-lg font-black text-(--text)">
                                {{ pillar.title }}
                            </h3>
                            <p class="mt-1.5 text-[13px] leading-relaxed text-muted">
                                {{ pillar.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 rounded-3xl border border-border bg-(--surface-soft) p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-4xl">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-(--brand)">Una sola transformación</p>
                    <p class="mt-1 text-sm leading-relaxed text-muted">
                        Dentro de Transformación 360,
                        <span class="font-black text-(--text)">LAUDA 360 define el camino</span> y
                        <span class="font-black text-(--text)">las soluciones LAUDAAPI aportan la tecnología</span> que se incorpora progresivamente según las prioridades del roadmap.
                    </p>
                </div>
                <span class="inline-flex w-fit shrink-0 rounded-full border border-border bg-(--surface) px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                    Servicio + tecnología
                </span>
            </div>
        </section>

        <!-- ===================== ROADMAP ===================== -->
        <section id="roadmap" class="mx-auto max-w-360 scroll-mt-24 px-4 py-8 sm:px-6 sm:py-11 2xl:px-8">
            <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Roadmap de Transformación
                    </p>
                    <h2 class="mt-2 max-w-4xl text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                        Una ruta progresiva desde el diagnóstico hasta una empresa inteligente.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    El diagnóstico determina el punto de partida, las prioridades y el ritmo de implementación.
                </p>
            </div>

            <div class="mb-5 rounded-3xl border border-border bg-(--surface) p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-(--brand)">01 · Entender</p>
                        <p class="mt-2 text-sm font-black text-(--text)">Dónde está su empresa hoy.</p>
                    </div>
                    <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-(--brand)">02 · Preparar y digitalizar</p>
                        <p class="mt-2 text-sm font-black text-(--text)">Construir las capacidades digitales prioritarias.</p>
                    </div>
                    <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-(--brand)">03 · Conectar</p>
                        <p class="mt-2 text-sm font-black text-(--text)">Hacer que los procesos trabajen como un sistema.</p>
                    </div>
                    <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-(--brand)">04 · Decidir</p>
                        <p class="mt-2 text-sm font-black text-(--text)">Convertir la operación en información para dirigir.</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="stage in roadmapStages" :key="stage.number" class="lauda-card relative flex min-h-full flex-col overflow-hidden rounded-2xl border p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="text-3xl font-black tracking-[-0.04em]" :style="{ color: stage.color }">
                                {{ stage.number }}
                            </span>
                            <p class="mt-1 text-[9px] font-black uppercase tracking-[0.16em] text-(--soft)">
                                {{ stage.phase }}
                            </p>
                        </div>

                        <span class="grid h-10 w-10 place-items-center rounded-xl" :style="{ background: stage.color + '1a' }">
                            <component :is="stage.icon" class="h-5 w-5" :style="{ color: stage.color }" />
                        </span>
                    </div>

                    <h3 class="mt-3 text-base font-black text-(--text)">
                        {{ stage.title }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        {{ stage.desc }}
                    </p>

                    <div class="mt-auto pt-4">
                        <div class="rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em]" :style="{ color: stage.color }">
                                Resultado esperado
                            </p>
                            <p class="mt-1.5 text-xs font-semibold leading-relaxed text-(--text)">
                                {{ stage.result }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-4 rounded-3xl border border-border bg-(--surface-soft) p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-4xl">
                    <p class="text-sm font-black text-(--text)">
                        Dentro de Transformación 360, la tecnología se incorpora cuando genera valor.
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-muted">
                        Social, CRM, POS, administración, integraciones y BI pueden incorporarse progresivamente de acuerdo con las prioridades definidas en el roadmap.
                    </p>
                </div>

                <Button type="button" variant="outline" class="lauda-outline-button shrink-0 justify-center rounded-xl px-5 py-5" @click="scrollToId('modalidades')">
                    Ver modalidades de acompañamiento
                </Button>
            </div>
        </section>

        <!-- ===================== MODALIDADES ===================== -->
        <section id="modalidades" class="mx-auto max-w-360 scroll-mt-24 px-4 py-8 sm:px-6 sm:py-11 2xl:px-8">
            <div class="rounded-3xl border border-border bg-(--surface) p-5 shadow-sm lg:p-6">
                <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                    <div class="max-w-4xl">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                            Modalidades de acompañamiento
                        </p>
                        <h2 class="mt-2 text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                            El mismo roadmap, con tres niveles de acompañamiento.
                        </h2>
                    </div>

                    <p class="max-w-xl text-sm leading-relaxed text-muted">
                        Lo que cambia es cuánto ejecuta su equipo y cuánto asume LAUDA durante la transformación.
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <article v-for="option in serviceModels" :key="option.title" class="lauda-service-card relative flex h-full flex-col rounded-3xl border border-border bg-(--surface-soft) p-5" :class="option.recommended && 'lauda-service-card--recommended'">
                        <div v-if="option.recommended" class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-(--brand) px-3 py-1 text-[9px] font-black uppercase tracking-[0.14em] text-white shadow-lg shadow-[#F5333C]/20">
                            Recomendado
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" :style="{ background: option.color + '1a' }">
                                <component :is="option.icon" class="h-5 w-5" :style="{ color: option.color }" />
                            </span>

                            <span v-if="!option.recommended" class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.12em]" :style="{ background: option.color + '1a', color: option.color }">
                                {{ option.badge }}
                            </span>
                        </div>

                        <p class="mt-4 text-[10px] font-black uppercase tracking-[0.16em]" :style="{ color: option.color }">
                            {{ option.level }}
                        </p>

                        <h3 class="mt-1 text-xl font-black text-(--text)">
                            {{ option.title }}
                        </h3>

                        <p class="mt-2 text-[13px] leading-relaxed text-muted">
                            {{ option.desc }}
                        </p>

                        <div class="mt-4 rounded-2xl border border-border bg-(--surface-solid) p-3">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-(--soft)">
                                Ideal para
                            </p>
                            <p class="mt-2 text-xs font-semibold leading-relaxed text-(--text)">
                                {{ option.idealFor }}
                            </p>
                        </div>

                        <div class="mt-5 grid gap-3">
                            <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em]" :style="{ color: option.color }">
                                    LAUDA
                                </p>
                                <div class="mt-3 space-y-2.5">
                                    <div v-for="item in option.laudaDoes" :key="item" class="flex items-start gap-2.5">
                                        <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0" :style="{ color: option.color }" />
                                        <span class="text-xs font-semibold leading-relaxed text-muted">{{ item }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-(--soft)">
                                    Su empresa
                                </p>
                                <div class="mt-3 space-y-2.5">
                                    <div v-for="item in option.clientDoes" :key="item" class="flex items-start gap-2.5">
                                        <ArrowRight class="mt-0.5 h-3.5 w-3.5 shrink-0 text-(--soft)" />
                                        <span class="text-xs font-semibold leading-relaxed text-muted">{{ item }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mt-7 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                    <div class="rounded-3xl border border-border bg-(--surface-soft) p-5">
                        <p class="text-sm font-black text-(--text)">
                            La modalidad se recomienda después del diagnóstico.
                        </p>
                        <p class="mt-1 text-sm leading-relaxed text-muted">
                            Una empresa puede trabajar bajo una modalidad general y requerir mayor o menor asistencia en etapas específicas. El objetivo es asignar recursos donde realmente generan valor, sin sobredimensionar el proyecto.
                        </p>
                    </div>

                    <Button type="button" class="w-full justify-center gap-2 rounded-xl bg-(--brand) px-6 py-6 text-white hover:bg-(--brand-hover) lg:w-auto" @click="scrollToId('contacto')">
                        Solicitar diagnóstico
                        <ArrowRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </section>

        <!-- ===================== ECOSISTEMA LAUDAAPI ===================== -->
        <section id="ecosistema-detalle" class="mx-auto max-w-360 scroll-mt-24 px-4 py-8 sm:px-6 sm:py-11 2xl:px-8">
            <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Ecosistema LAUDAAPI
                    </p>
                    <h2 class="mt-2 max-w-4xl text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                        Soluciones independientes que pueden trabajar juntas.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    Social, CRM, POS, e-CF, Cumplimiento, BYS y las demás soluciones mantienen su especialización y pueden utilizarse de forma independiente o conectarse cuando el negocio lo requiere.
                </p>
            </div>

            <div class="mb-5 rounded-3xl border border-border bg-(--surface) p-4 shadow-sm sm:p-5">
                <div class="flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                    <span class="rounded-full bg-[#EC4899]/10 px-3 py-1.5 text-[#EC4899]">Social</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#A855F7]/10 px-3 py-1.5 text-[#A855F7]">CRM</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#22C55E]/10 px-3 py-1.5 text-[#16A34A]">POS</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#8B5CF6]/10 px-3 py-1.5 text-[#7C3AED]">Administración</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-(--brand)/10 px-3 py-1.5 text-(--brand)">Integración</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#3B82F6]/10 px-3 py-1.5 text-[#2563EB]">Data / BI</span>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="step in ecosystemSteps" :key="step.phase" class="lauda-card rounded-3xl border p-5">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl" :style="{ background: step.color + '1a' }">
                            <component :is="step.icon" class="h-5 w-5" :style="{ color: step.color }" />
                        </span>

                        <span class="text-2xl font-black tracking-[-0.04em] text-(--soft)">
                            {{ step.phase }}
                        </span>
                    </div>

                    <h3 class="text-lg font-black text-(--text)">
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

                    <div class="mt-4 rounded-2xl border border-border bg-(--surface-soft) p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-(--soft)">Resultado</p>
                        <p class="mt-1 text-xs font-semibold leading-relaxed text-(--text)">
                            {{ step.outcome }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 rounded-3xl border border-(--brand)/15 bg-(--brand)/5 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-black text-(--text)">El ecosistema puede crecer de dos maneras.</p>
                    <p class="mt-1 max-w-3xl text-sm leading-relaxed text-muted">
                        Una empresa puede contratar soluciones directamente. Dentro de Transformación 360, el diagnóstico y el roadmap determinan qué capacidades conviene incorporar primero, qué debe integrarse y qué puede esperar.
                    </p>
                </div>

                <Button type="button" variant="outline" class="lauda-outline-button shrink-0 rounded-xl px-5 py-5" @click="scrollToId('soluciones')">
                    Ver capacidades LAUDAAPI
                </Button>
            </div>
        </section>

        <!-- ===================== CAPACIDADES DEL ECOSISTEMA ===================== -->
        <section id="soluciones" class="mx-auto max-w-360 scroll-mt-24 px-4 py-8 sm:px-6 sm:py-11 2xl:px-8">
            <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Soluciones LAUDAAPI
                    </p>
                    <h2 class="mt-2 max-w-4xl text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                        Elija la solución que necesita hoy y conecte más capacidades cuando su empresa lo requiera.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    Cada solución tiene una función específica y puede utilizarse de forma independiente. Cuando trabajan juntas, comparten identidad, acceso y contexto dentro del ecosistema LAUDAAPI.
                </p>
            </div>

            <div class="mb-6 rounded-3xl border border-border bg-(--surface-soft) p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-widest text-(--soft)">
                    <span class="rounded-full bg-[#EC4899]/10 px-3 py-1.5 text-[#EC4899]">Social</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#A855F7]/10 px-3 py-1.5 text-[#A855F7]">CRM</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#22C55E]/10 px-3 py-1.5 text-[#16A34A]">POS</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#8B5CF6]/10 px-3 py-1.5 text-[#7C3AED]">Administración</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#3B82F6]/10 px-3 py-1.5 text-[#2563EB]">BI / Inteligencia</span>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-5">
                <a v-for="solution in primarySolutions" :key="solution.name" :href="solution.href" class="lauda-card group rounded-3xl border p-5 transition-all hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-4 flex items-start gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl" :style="{ background: solution.color + '1a' }">
                            <component :is="solution.icon" class="h-5 w-5" :style="{ color: solution.color }" />
                        </span>
                    </div>

                    <p class="text-[9px] font-black uppercase tracking-[0.14em]" :style="{ color: solution.color }">
                        {{ solution.stage }}
                    </p>

                    <h3 class="mt-1 text-xl font-black text-(--text)">
                        {{ solution.name }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        {{ solution.desc }}
                    </p>

                    <div class="mt-4 rounded-2xl border border-border bg-(--surface-soft) p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.12em] text-(--soft)">Función en el ecosistema</p>
                        <p class="mt-1 text-xs leading-relaxed text-muted">
                            {{ solution.role }}
                        </p>
                    </div>

                    <p class="mt-3 text-[11px] leading-relaxed text-muted">
                        <span class="font-black text-(--text)">Se integra con:</span> {{ solution.connects }}
                    </p>

                    <div class="mt-4 inline-flex items-center gap-2 text-sm font-black text-(--brand)">
                        Ver solución
                        <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </div>
                </a>
            </div>
        </section>

        <!-- ===================== EMPRESA CONECTADA / FLUJOS ===================== -->
        <section id="flujos" class="mx-auto max-w-360 scroll-mt-24 px-4 py-8 sm:px-6 sm:py-11 2xl:px-8">
            <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div class="max-w-4xl">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Empresa conectada
                    </p>
                    <h2 class="mt-2 text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                        La transformación ocurre cuando los procesos dejan de trabajar aislados.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    LAUDAAPI conecta las capacidades necesarias para que una interacción, una venta, una compra o una señal del negocio continúen su recorrido sin duplicar información ni depender de procesos manuales entre áreas.
                </p>
            </div>

            <div class="mb-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-(--brand)">Antes</p>
                    <p class="mt-2 text-sm font-black text-(--text)">Procesos separados</p>
                    <p class="mt-1 text-xs leading-relaxed text-muted">Personas, Excel, WhatsApp y aplicaciones intercambiando información manualmente.</p>
                </div>

                <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-(--brand)">Transformación</p>
                    <p class="mt-2 text-sm font-black text-(--text)">Procesos conectados</p>
                    <p class="mt-1 text-xs leading-relaxed text-muted">Cada área conserva su función, pero comparte información y eventos con la siguiente etapa.</p>
                </div>

                <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-(--brand)">Resultado</p>
                    <p class="mt-2 text-sm font-black text-(--text)">Empresa coordinada</p>
                    <p class="mt-1 text-xs leading-relaxed text-muted">Menos reprocesos, mayor trazabilidad y datos disponibles para controlar y decidir.</p>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <article v-for="f in flows" :key="f.title" class="lauda-card min-w-0 overflow-hidden rounded-3xl border p-6 lg:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-(--brand)">Proceso de punta a punta</p>
                            <h3 class="mt-2 text-xl font-black tracking-tight text-(--text)">{{ f.title }}</h3>
                        </div>
                        <span class="shrink-0 rounded-full border border-border bg-(--surface-soft) px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.12em] text-(--soft)">
                            Conectado
                        </span>
                    </div>

                    <div class="mt-6 flex min-w-0 flex-wrap items-start gap-x-2 gap-y-4">
                        <template v-for="(s, i) in f.steps" :key="s.label">
                            <div class="flex min-w-14 max-w-20 flex-col items-center gap-2 text-center">
                                <span class="grid h-10 w-10 place-items-center rounded-full" :style="{ background: s.color + '1a' }">
                                    <component :is="s.icon" class="h-4 w-4" :style="{ color: s.color }" />
                                </span>
                                <span class="max-w-20 text-[10px] font-bold leading-tight text-muted">{{ s.label }}</span>
                            </div>
                            <ArrowRight v-if="i < f.steps.length - 1" class="mt-3.5 h-3.5 w-3.5 shrink-0 text-(--soft)" />
                        </template>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-(--soft)">Objetivo empresarial</p>
                            <p class="mt-2 text-sm leading-relaxed text-muted">{{ f.business }}</p>
                        </div>
                        <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-(--soft)">Resultado esperado</p>
                            <p class="mt-2 text-sm font-semibold leading-relaxed text-(--text)">{{ f.result }}</p>
                        </div>
                    </div>
                </article>
            </div>

            <div class="mt-6 flex flex-col gap-4 rounded-3xl border border-border bg-(--surface) p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-4xl">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-(--brand)">Principio de integración LAUDAAPI</p>
                    <p class="mt-1 text-base font-black text-(--text)">Conectamos procesos, no solamente aplicaciones.</p>
                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        Una integración tiene valor cuando elimina una transferencia manual, conserva la trazabilidad y permite que la siguiente área continúe el proceso con la información correcta.
                    </p>
                </div>

                <Button type="button" variant="outline" class="lauda-outline-button shrink-0 gap-2 rounded-xl px-5 py-5" @click="scrollToId('contacto')">
                    Evaluar mis procesos
                    <ArrowRight class="h-4 w-4" />
                </Button>
            </div>
        </section>

        <!-- ===================== INTELIGENCIA / BI ===================== -->
        <section id="dashboard" class="mx-auto max-w-360 scroll-mt-24 px-4 py-8 sm:px-6 sm:py-11 2xl:px-8">
            <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                            Empresa inteligente
                        </p>
                        <span class="rounded-full border border-border bg-(--surface) px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                            Data + BI
                        </span>
                    </div>

                    <h2 class="mt-2 max-w-4xl text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                        Los datos dejan de ser reportes y se convierten en una herramienta para dirigir la empresa.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    LAUDA Data consolida la información generada por el ecosistema y, cuando corresponde, por sistemas externos. BI transforma esa base en indicadores, análisis, alertas y conocimiento útil para tomar mejores decisiones.
                </p>
            </div>

            <div class="mb-5 rounded-3xl border border-border bg-(--surface) p-4 shadow-sm sm:p-5">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-(--brand)">
                    De la operación a la decisión
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px] font-black uppercase tracking-widest text-(--soft)">
                    <span class="rounded-full bg-[#EC4899]/10 px-3 py-1.5 text-[#EC4899]">Social</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#A855F7]/10 px-3 py-1.5 text-[#A855F7]">CRM</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#22C55E]/10 px-3 py-1.5 text-[#16A34A]">POS</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#8B5CF6]/10 px-3 py-1.5 text-[#7C3AED]">Administración</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-[#3B82F6]/10 px-3 py-1.5 text-[#2563EB]">LAUDA Data</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                    <span class="rounded-full bg-(--brand)/10 px-3 py-1.5 text-(--brand)">BI / Decisiones</span>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="stage in intelligenceStages" :key="stage.step" class="lauda-card rounded-3xl border p-5">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl" :style="{ background: stage.color + '1a' }">
                            <component :is="stage.icon" class="h-5 w-5" :style="{ color: stage.color }" />
                        </span>
                        <span class="text-[10px] font-black tracking-[0.18em] text-(--soft)">{{ stage.step }}</span>
                    </div>

                    <h3 class="mt-4 text-lg font-black text-(--text)">{{ stage.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted">{{ stage.desc }}</p>

                    <div class="mt-4 rounded-2xl border border-border bg-(--surface-soft) p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.12em] text-(--soft)">Resultado</p>
                        <p class="mt-1 text-xs leading-relaxed text-muted">{{ stage.result }}</p>
                    </div>
                </div>
            </div>

            <div class="lauda-card mt-5 overflow-hidden rounded-3xl border p-4 sm:p-5 lg:p-6">
                <div class="rounded-3xl border border-white/8 bg-[#080B15] p-4 shadow-2xl shadow-black/20 sm:p-5">
                    <div class="flex flex-col gap-4 border-b border-white/8 pb-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-[#F5333C]">
                                <span class="h-2 w-2 rounded-full bg-[#F5333C]" />
                                Dashboard Ejecutivo 360
                            </div>
                            <h3 class="mt-2 text-xl font-black text-white sm:text-2xl">
                                Una vista común para entender qué está ocurriendo y dónde actuar.
                            </h3>
                            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-[#9AA1B8]">
                                Vista ilustrativa de cómo la información integrada puede combinar indicadores comerciales, operativos, financieros y de riesgo en un mismo contexto ejecutivo.
                            </p>
                        </div>

                        <div class="inline-flex w-fit items-center gap-2 rounded-full border border-[#22C55E]/20 bg-[#22C55E]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] text-[#4ADE80]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#4ADE80]" />
                            Información conectada
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="metric in dashboardPreviewMetrics" :key="metric.label" class="rounded-2xl border border-white/7 bg-[#111625] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" :style="{ background: metric.color + '22' }">
                                    <component :is="metric.icon" class="h-5 w-5" :style="{ color: metric.color }" />
                                </span>

                                <span class="rounded-full border border-white/7 px-2 py-1 text-[9px] font-black uppercase tracking-widest text-[#7A8298]">
                                    Ejemplo
                                </span>
                            </div>

                            <p class="mt-4 text-[11px] font-bold uppercase tracking-[0.08em] text-[#8E96AC]">{{ metric.label }}</p>
                            <p class="mt-1 text-2xl font-black tracking-tight text-white">{{ metric.value }}</p>
                            <p class="mt-2 text-xs leading-relaxed text-[#9AA1B8]">{{ metric.context }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div v-for="view in dashboardViews" :key="view.title" class="rounded-3xl border border-white/7 bg-[#0D1120] p-5">
                            <div class="flex items-start gap-4">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" :style="{ background: view.color + '22' }">
                                    <component :is="view.icon" class="h-5 w-5" :style="{ color: view.color }" />
                                </span>
                                <div>
                                    <h3 class="text-lg font-black text-white">{{ view.title }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-[#9AA1B8]">{{ view.desc }}</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-2.5">
                                <div v-for="item in view.items" :key="item" class="flex items-center gap-3 rounded-2xl border border-white/6 bg-white/2.5 px-3 py-2.5">
                                    <CheckCircle2 class="h-4 w-4 shrink-0" :style="{ color: view.color }" />
                                    <span class="text-xs font-semibold leading-relaxed text-[#C1C7D6]">{{ item }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-4 rounded-3xl border border-border bg-(--surface-soft) p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-(--brand)">Empresa inteligente</p>
                        <p class="mt-1 text-base font-black text-(--text)">Una empresa conectada que aprende de su propia operación.</p>
                        <p class="mt-2 text-sm leading-relaxed text-muted">
                            El objetivo no es acumular dashboards. Es disponer de información confiable para controlar el negocio, identificar oportunidades, anticipar riesgos y tomar decisiones con mayor velocidad y fundamento.
                        </p>
                    </div>

                    <Button type="button" variant="outline" class="lauda-outline-button shrink-0 gap-2 rounded-xl px-5 py-5" @click="scrollToId('contacto')">
                        Solicitar diagnóstico
                        <ArrowRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </section>

        <!-- ===================== CAPACIDADES ESPECIALIZADAS ===================== -->
        <section id="modulos" class="mx-auto max-w-360 scroll-mt-24 px-4 pb-10 pt-4 sm:px-6 sm:pb-12 sm:pt-5 2xl:px-8">
            <div class="rounded-3xl border border-border bg-(--surface) p-5 shadow-sm lg:p-6">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-center">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                            Capacidades especializadas
                        </p>
                        <h2 class="mt-2 text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                            El ecosistema puede crecer según las necesidades de cada empresa.
                        </h2>
                        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-muted">
                            En Transformación 360, RRHH, Proyectos, Eventos, Transporte, Grúas, Loans y Dealers se recomiendan cuando el diagnóstico identifica una necesidad concreta. Algunas soluciones también pueden incorporarse de forma directa cuando existe un requerimiento claro.
                        </p>

                        <div class="mt-5 flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-widest text-(--soft)">
                            <span class="rounded-full border border-border bg-(--surface-soft) px-3 py-1.5">Necesidad</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                            <span class="rounded-full border border-border bg-(--surface-soft) px-3 py-1.5">Proceso</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                            <span class="rounded-full border border-border bg-(--surface-soft) px-3 py-1.5">Capacidad</span>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="module in extendedModules" :key="module.name" class="flex items-center gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3.5">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" :style="{ background: module.color + '1a' }">
                                <component :is="module.icon" class="h-4.5 w-4.5" :style="{ color: module.color }" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-black text-(--text)">{{ module.name }}</p>
                                <p class="mt-0.5 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ module.trigger }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-3xl text-sm leading-relaxed text-muted">
                        <span class="font-black text-(--text)">En Transformación 360, cada capacidad responde a un objetivo concreto.</span>
                        Fuera de ese programa, una empresa también puede comenzar directamente por una solución específica y ampliar el ecosistema más adelante.
                    </p>
                    <Button type="button" variant="outline" class="lauda-outline-button shrink-0 gap-2 rounded-xl px-5 py-5" @click="scrollToId('contacto')">
                        Definir prioridades
                        <ArrowRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </section>

        <!-- ===================== CONTACTO ===================== -->
        <section id="contacto" class="mx-auto max-w-360 scroll-mt-24 px-4 pb-12 pt-5 sm:px-6 sm:pb-14 sm:pt-6 2xl:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-start">
                <div class="lauda-card rounded-3xl border p-5 lg:p-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Primer paso: solicitar acceso al Diagnóstico LAUDA 360
                    </p>

                    <h2 class="mt-2 text-[28px] font-black leading-[1.08] tracking-tight text-(--text) sm:text-[34px]">
                        El diagnóstico comienza en un espacio privado y seguro.
                    </h2>

                    <p class="mt-4 text-sm leading-relaxed text-muted">
                        Comparta información básica sobre su organización. LAUDA revisa la solicitud y, cuando corresponda, habilita acceso privado al Diagnóstico LAUDA 360 dentro de app.laudaapi.com, el mismo Hub donde la empresa administra su cuenta y soluciones. El cuestionario completo nunca se expone públicamente.
                    </p>

                    <div class="mt-5 grid gap-2.5">
                        <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                            <div class="flex items-start gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-(--brand)/10 text-xs font-black text-(--brand)">1</span>
                                <div>
                                    <p class="text-sm font-black text-(--text)">Conversación inicial</p>
                                    <p class="mt-1 text-xs leading-relaxed text-muted">Entendemos el contexto, los retos y los objetivos generales de la empresa.</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                            <div class="flex items-start gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-(--brand)/10 text-xs font-black text-(--brand)">2</span>
                                <div>
                                    <p class="text-sm font-black text-(--text)">Diagnóstico y madurez digital</p>
                                    <p class="mt-1 text-xs leading-relaxed text-muted">Evaluamos procesos, personas, tecnología, datos y nivel de integración.</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-(--surface-soft) p-4">
                            <div class="flex items-start gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-(--brand)/10 text-xs font-black text-(--brand)">3</span>
                                <div>
                                    <p class="text-sm font-black text-(--text)">Roadmap de transformación</p>
                                    <p class="mt-1 text-xs leading-relaxed text-muted">Definimos prioridades, etapas, objetivos y la modalidad de acompañamiento recomendada.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-(--brand)/20 bg-(--brand)/5 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-(--brand)">Acceso controlado</p>
                        <p class="mt-2 text-sm leading-relaxed text-muted">
                            El Diagnóstico LAUDA 360 no es público. Una vez aprobada la solicitud, la empresa recibe acceso autenticado en app.laudaapi.com para completar el proceso, guardar avances y consultar sus resultados.
                        </p>
                    </div>
                </div>

                <form class="lauda-card rounded-3xl border p-5 lg:p-6" @submit.prevent="submitContact">
                    <div class="mb-6 flex flex-col gap-3 border-b border-border pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-lg font-black text-(--text)">Solicitar acceso al Diagnóstico LAUDA 360</p>
                            <p class="mt-1 text-sm leading-relaxed text-muted">
                                Esta solicitud no contiene el diagnóstico. Solo recoge la información necesaria para revisar el caso y habilitar, cuando corresponda, un acceso privado al proceso.
                            </p>
                        </div>

                        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-[#22C55E]/20 bg-[#22C55E]/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-[#16A34A]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#22C55E]" />
                            Acceso privado
                        </span>
                    </div>

                    <div v-if="contactSubmitted" class="mb-5 rounded-2xl border border-[#22C55E]/25 bg-[#22C55E]/10 p-4 text-sm leading-relaxed text-[#15803D]">
                        <div class="flex items-start gap-3">
                            <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <p class="font-black">Solicitud recibida.</p>
                                <p class="mt-1">
                                    {{ contactSuccessMessage || 'Gracias. LAUDA revisará la solicitud y, si procede, enviará las instrucciones para acceder al Diagnóstico LAUDA 360 de forma privada.' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Nombre</span>
                            <input v-model="contactForm.name" type="text" class="lauda-input" :class="contactErrors.name && 'lauda-input--error'" placeholder="Nombre del contacto" autocomplete="name" required />
                            <span v-if="contactErrors.name" class="lauda-form-error">{{ contactErrors.name?.[ 0 ] || contactErrors.name }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Empresa</span>
                            <input v-model="contactForm.company" type="text" class="lauda-input" :class="contactErrors.company && 'lauda-input--error'" placeholder="Nombre de la empresa" autocomplete="organization" required />
                            <span v-if="contactErrors.company" class="lauda-form-error">{{ contactErrors.company?.[ 0 ] || contactErrors.company }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Correo</span>
                            <input v-model="contactForm.email" type="email" class="lauda-input" :class="contactErrors.email && 'lauda-input--error'" placeholder="correo@empresa.com" autocomplete="email" required />
                            <span v-if="contactErrors.email" class="lauda-form-error">{{ contactErrors.email?.[ 0 ] || contactErrors.email }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Teléfono</span>
                            <input v-model="contactForm.phone" type="tel" class="lauda-input" :class="contactErrors.phone && 'lauda-input--error'" placeholder="809-000-0000" autocomplete="tel" required />
                            <span v-if="contactErrors.phone" class="lauda-form-error">{{ contactErrors.phone?.[ 0 ] || contactErrors.phone }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Tamaño aproximado</span>
                            <select v-model="contactForm.company_size" class="lauda-input" required>
                                <option disabled value="">Seleccione una opción</option>
                                <option v-for="size in companySizes" :key="size" :value="size">{{ size }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Principal reto</span>
                            <select v-model="contactForm.main_challenge" class="lauda-input" required>
                                <option v-for="challenge in transformationChallenges" :key="challenge" :value="challenge">{{ challenge }}</option>
                            </select>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Preferencia de acompañamiento</span>
                            <select v-model="contactForm.assistance_level" class="lauda-input" required>
                                <option v-for="level in assistanceLevels" :key="level" :value="level">{{ level }}</option>
                            </select>
                            <span class="mt-1.5 block text-[11px] leading-relaxed text-muted">
                                Puede dejar que LAUDA recomiende la modalidad. Guiado es principalmente autoservicio con soporte por email; Asistido implica trabajo conjunto; Gestionado significa que LAUDA lidera el proceso.
                            </span>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Cuéntenos brevemente su situación</span>
                            <textarea v-model="contactForm.message" rows="5" class="lauda-input resize-none" :class="contactErrors.message && 'lauda-input--error'" placeholder="Ejemplo: usamos Excel y WhatsApp para varios procesos, queremos organizar ventas y clientes, y necesitamos tener una visión más clara de la operación." />
                            <span v-if="contactErrors.message" class="lauda-form-error">{{ contactErrors.message?.[ 0 ] || contactErrors.message }}</span>
                        </label>

                        <label class="flex items-start gap-3 sm:col-span-2">
                            <input v-model="contactForm.terms" type="checkbox" class="mt-1 h-4 w-4 rounded border-border accent-[#F5333C]" required />
                            <span class="text-xs leading-relaxed text-muted">
                                Acepto que LAUDA me contacte para revisar esta solicitud y, cuando corresponda, habilitar el acceso privado al diagnóstico.
                                <span v-if="contactErrors.terms" class="lauda-form-error block">{{ contactErrors.terms?.[ 0 ] || contactErrors.terms }}</span>
                            </span>
                        </label>
                    </div>

                    <div v-if="contactErrors.general" class="mt-5 rounded-2xl border border-[#F5333C]/25 bg-[#F5333C]/10 p-4 text-sm leading-relaxed text-[#B91C1C]">
                        {{ contactErrors.general }}
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-relaxed text-muted">
                            La solicitud no implica contratación ni acceso automático. LAUDA revisa el caso antes de habilitar el diagnóstico privado.
                        </p>

                        <Button type="submit" class="w-full justify-center gap-2 rounded-xl bg-(--brand) px-6 py-6 text-white hover:bg-(--brand-hover) disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto" :disabled="contactProcessing">
                            {{ contactProcessing ? 'Enviando...' : 'Solicitar diagnóstico' }}
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                    </div>
                </form>
            </div>
        </section>

        <!-- ===================== FOOTER ===================== -->
        <footer class="lauda-footer border-t">
            <div class="mx-auto grid max-w-360 gap-7 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1.2fr)_repeat(3,minmax(0,0.8fr))] 2xl:px-8">
                <div class="min-w-0">
                    <Link href="/" class="inline-flex items-center gap-2.5">
                        <div class="grid h-11 w-11 place-items-center rounded-xl bg-(--brand) font-black text-white shadow-xl shadow-[#F5333C]/25">
                            <BrandLogo class="h-6 w-6 text-white" />
                        </div>

                        <div class="leading-none">
                            <div class="text-[20px] font-extrabold tracking-tight text-(--text)">LAUDA</div>
                            <div class="mt-0.5 text-[9px] font-semibold tracking-[0.2em] text-(--brand)">
                                API DIGITAL
                            </div>
                        </div>
                    </Link>

                    <p class="mt-4 max-w-md text-sm leading-relaxed text-muted">
                        LAUDAAPI es un ecosistema de soluciones empresariales conectadas. Utilice las capacidades que necesita directamente o construya una ruta integral con Transformación 360.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="rounded-full border border-border bg-(--surface-soft) px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                            API-first
                        </span>
                        <span class="rounded-full border border-border bg-(--surface-soft) px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                            Empresa conectada
                        </span>
                        <span class="rounded-full border border-border bg-(--surface-soft) px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                            Datos + BI
                        </span>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-(--brand)">LAUDAAPI</p>
                    <nav class="mt-4 space-y-2">
                        <a v-for="link in footerQuickLinks" :key="link.href" :href="link.href" class="block text-sm font-semibold text-muted transition hover:text-(--text)">
                            {{ link.label }}
                        </a>
                    </nav>
                </div>

                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-(--brand)">Ecosistema</p>
                    <nav class="mt-4 space-y-2">
                        <a v-for="product in solutionProducts.slice(0, 8)" :key="product.name" :href="product.href" class="flex items-center gap-2 text-sm font-semibold text-muted transition hover:text-(--text)">
                            <span class="h-2 w-2 rounded-full" :style="{ background: product.color }" />
                            {{ product.name }}
                        </a>
                    </nav>
                </div>

                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-(--brand)">Legal y contacto</p>
                    <nav class="mt-4 space-y-2">
                        <Link v-for="link in footerLegalLinks" :key="link.href" :href="link.href" class="block text-sm font-semibold text-muted transition hover:text-(--text)">
                            {{ link.label }}
                        </Link>
                        <a href="https://app.laudaapi.com" class="block text-sm font-semibold text-muted transition hover:text-(--text)">
                            app.laudaapi.com
                        </a>
                        <a href="mailto:contacto@laudaapi.com" class="block text-sm font-semibold text-muted transition hover:text-(--text)">
                            contacto@laudaapi.com
                        </a>
                    </nav>
                </div>
            </div>

            <div class="border-t border-border">
                <div class="mx-auto flex max-w-360 flex-col gap-2 px-4 py-5 text-xs text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 2xl:px-8">
                    <span>© {{ currentYear }} LAUDAAPI. Todos los derechos reservados.</span>
                    <span>Soluciones empresariales conectadas · Transformación Digital 360.</span>
                </div>
            </div>
        </footer>

        <!-- ===================== VOLVER AL INICIO ===================== -->
        <transition name="lauda-backtop">
            <button v-if="showBackToTop" type="button" class="lauda-back-to-top fixed bottom-5 right-5 z-50 inline-flex h-12 items-center justify-center gap-2 rounded-full border px-4 text-sm font-black shadow-lg sm:bottom-6 sm:right-6" aria-label="Volver al inicio" title="Volver al inicio" @click="scrollToTop">
                <ArrowUp class="h-4 w-4" />
                <span class="hidden sm:inline">Inicio</span>
            </button>
        </transition>
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
    padding-top: 76px;
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
    width: 100%;
    border-color: var(--border);
    background: var(--nav-bg);
    transition: background-color 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
}

.lauda-scroll-progress {
    position: absolute;
    right: 0;
    bottom: -1px;
    left: 0;
    height: 2px;
    overflow: hidden;
    background: transparent;
    pointer-events: none;
}

.lauda-scroll-progress__bar {
    height: 100%;
    background: var(--brand);
    box-shadow: 0 0 10px rgba(var(--brand-rgb), 0.25);
    transition: width 0.08s linear;
}

.lauda-nav-inner,
.lauda-brand-mark,
.lauda-brand-icon,
.lauda-brand-name,
.lauda-brand-tagline,
.lauda-nav-link,
.lauda-nav-action,
.lauda-login-button {
    transition:
        height 0.22s ease,
        width 0.22s ease,
        padding 0.22s ease,
        font-size 0.22s ease,
        gap 0.22s ease,
        border-radius 0.22s ease,
        box-shadow 0.22s ease,
        opacity 0.22s ease,
        transform 0.22s ease;
}

.lauda-nav--compact {
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
}

.lauda-page--dark .lauda-nav--compact {
    box-shadow: 0 12px 34px rgba(0, 0, 0, 0.28);
}

.lauda-nav--compact .lauda-nav-inner {
    height: 64px;
}

.lauda-nav--compact .lauda-brand-mark {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(var(--brand-rgb), 0.22);
}

.lauda-nav--compact .lauda-brand-icon {
    width: 20px;
    height: 20px;
}

.lauda-nav--compact .lauda-brand-name {
    font-size: 18px;
}

.lauda-nav--compact .lauda-brand-tagline {
    margin-top: 1px;
    font-size: 8px;
}

.lauda-nav--compact .lauda-nav-link {
    height: 64px;
    font-size: 14px;
}

.lauda-nav--compact .lauda-nav-action {
    width: 40px;
    height: 40px;
    border-radius: 10px;
}

.lauda-nav--compact .lauda-login-button {
    padding: 0.625rem 1.25rem;
}

@media (max-width: 1023px) {
    .lauda-nav--compact .lauda-nav-inner {
        height: 60px;
    }

    .lauda-nav--compact .lauda-brand-tagline {
        display: none;
    }
}

.lauda-toast {
    border-color: rgba(34, 197, 94, 0.22);
    background: var(--surface);
    box-shadow: 0 18px 50px -18px rgba(15, 23, 42, 0.35);
}

.lauda-page--dark .lauda-toast {
    border-color: rgba(34, 197, 94, 0.28);
    box-shadow: 0 22px 60px -20px rgba(0, 0, 0, 0.62);
}

.lauda-toast-enter-active,
.lauda-toast-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.lauda-toast-enter-from,
.lauda-toast-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {

    .lauda-nav,
    .lauda-nav-inner,
    .lauda-brand-mark,
    .lauda-brand-icon,
    .lauda-brand-name,
    .lauda-brand-tagline,
    .lauda-nav-link,
    .lauda-nav-action,
    .lauda-login-button {
        transition: none;
    }

    .lauda-scroll-progress__bar {
        transition: none;
    }
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

.lauda-card {
    border-color: var(--border);
    background: var(--surface);
    box-shadow: 0 14px 38px rgba(15, 23, 42, 0.045);
}

.lauda-page--dark .lauda-card {
    box-shadow: 0 16px 46px rgba(0, 0, 0, 0.24);
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

.lauda-mobile-link:focus-visible,
.lauda-icon-btn:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
    border-radius: 12px;
}

/* -------------------------------------------------------------------------- */
/*  Modalidades LAUDA 360                                                     */
/* -------------------------------------------------------------------------- */

.lauda-service-card {
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.lauda-service-card:hover {
    transform: translateY(-3px);
    border-color: rgba(var(--brand-rgb), 0.18);
}

.lauda-service-card--recommended {
    border-color: rgba(var(--brand-rgb), 0.32);
    background:
        linear-gradient(180deg, rgba(var(--brand-rgb), 0.055), transparent 42%),
        var(--surface-soft);
    box-shadow: 0 22px 60px rgba(var(--brand-rgb), 0.10);
}

.lauda-page--dark .lauda-service-card--recommended {
    border-color: rgba(var(--brand-rgb), 0.38);
    box-shadow: 0 24px 70px rgba(var(--brand-rgb), 0.14);
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

.lauda-input--error {
    border-color: rgba(239, 68, 68, 0.75);
}

.lauda-form-error {
    margin-top: 6px;
    display: block;
    font-size: 12px;
    line-height: 1.4;
    color: #ef4444;
}

select.lauda-input {
    cursor: pointer;
}


/* -------------------------------------------------------------------------- */
/*  Volver al inicio                                                           */
/* -------------------------------------------------------------------------- */

.lauda-back-to-top {
    border-color: rgba(var(--brand-rgb), 0.22);
    color: #ffffff;
    background: var(--brand);
    box-shadow: 0 14px 34px rgba(var(--brand-rgb), 0.28);
    transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}

.lauda-back-to-top:hover {
    transform: translateY(-2px);
    background: var(--brand-hover);
    box-shadow: 0 18px 40px rgba(var(--brand-rgb), 0.34);
}

.lauda-back-to-top:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 3px;
}

.lauda-backtop-enter-active,
.lauda-backtop-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.lauda-backtop-enter-from,
.lauda-backtop-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

@media (prefers-reduced-motion: reduce) {

    .lauda-back-to-top,
    .lauda-backtop-enter-active,
    .lauda-backtop-leave-active {
        transition: none;
    }
}

/* -------------------------------------------------------------------------- */
/*  Footer                                                                     */
/* -------------------------------------------------------------------------- */

.lauda-footer {
    border-color: var(--border);
    background:
        radial-gradient(circle at 12% 0%, rgba(var(--brand-rgb), 0.08), transparent 30%),
        var(--surface);
}

.lauda-page--dark .lauda-footer {
    background:
        radial-gradient(circle at 12% 0%, rgba(var(--brand-rgb), 0.12), transparent 32%),
        rgba(8, 10, 18, 0.72);
}

/* -------------------------------------------------------------------------- */
/*  Hero layout responsivo y panel proporcional                                */
/* -------------------------------------------------------------------------- */

.lauda-hero {
    width: 100%;
    max-width: 1440px;
    margin-inline: auto;
    padding: 34px 16px 0;
    box-sizing: border-box;
}

.lauda-hero__layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 28px;
    align-items: center;
}

.lauda-hero__copy {
    width: 100%;
    max-width: 620px;
    min-width: 0;
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


@media (min-width: 640px) {
    .lauda-hero {
        padding-inline: 24px;
    }
}


@media (min-width: 1200px) {
    .lauda-hero__layout {
        grid-template-columns: minmax(460px, 560px) minmax(0, 760px);
        gap: 40px;
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
/*  Responsive polish                                                          */
/* -------------------------------------------------------------------------- */

.lauda-page {
    overflow-x: hidden;
}

@media (max-width: 639px) {
    .lauda-hero {
        padding-top: 24px;
    }

    .lauda-hero__layout {
        gap: 22px;
    }

    .lauda-hero__copy {
        max-width: none;
    }

    .lauda-hero__copy p {
        font-size: 15.5px;
    }


    .lauda-hero__panel {
        padding: 12px;
        border-radius: 22px;
    }

}

@media (max-width: 420px) {
    .lauda-hero {
        padding-inline: 12px;
    }

    .lauda-hero__panel {
        margin-inline: -2px;
    }

    .lauda-mode-toggle {
        min-width: 44px;
    }
}

@media (min-width: 640px) and (max-width: 1199px) {
    .lauda-hero__copy {
        max-width: 760px;
    }

    .lauda-hero__panel {
        justify-self: center;
    }
}

@media (prefers-reduced-motion: reduce) {

    .lauda-fade-enter-active,
    .lauda-fade-leave-active,
    .lauda-toast-enter-active,
    .lauda-toast-leave-active {
        transition: none;
    }
}
</style>
