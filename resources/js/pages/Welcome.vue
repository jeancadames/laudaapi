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
    title: 'LAUDAAPI | Transformación Digital Empresarial 360',
    description: 'Acompañamos a las empresas en su transformación digital mediante estrategia, procesos, tecnología, integración y datos, impulsados por el ecosistema LAUDAAPI.',
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
    { icon: Activity, text: 'Diagnóstico y roadmap digital', color: brand.main },
    { icon: Users, text: 'Personas y procesos organizados', color: brand.crm },
    { icon: RefreshCw, text: 'Tecnología e integración', color: brand.pos },
    { icon: TrendingUp, text: 'Datos para tomar decisiones', color: brand.bi },
]

const transformationPillars = [
    {
        title: 'Estrategia',
        desc: 'Entendemos la situación actual, definimos prioridades y construimos un roadmap de transformación alineado con los objetivos del negocio.',
        icon: Activity,
        color: brand.main,
    },
    {
        title: 'Personas y procesos',
        desc: 'Organizamos responsabilidades, procesos y formas de trabajo para que la transformación sea adoptada por la empresa y no dependa solo de tecnología.',
        icon: Users,
        color: brand.crm,
    },
    {
        title: 'Tecnología e integración',
        desc: 'Implementamos las capacidades necesarias y conectamos los procesos mediante LAUDAAPI y otras soluciones cuando el negocio lo requiera.',
        icon: RefreshCw,
        color: brand.pos,
    },
    {
        title: 'Datos e inteligencia',
        desc: 'Consolidamos información, indicadores y analítica para convertir la operación diaria en decisiones empresariales más rápidas y confiables.',
        icon: TrendingUp,
        color: brand.bi,
    },
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
    { icon: Zap, iconColor: brand.status, label: 'Eventos', value: 'Transformación 360', sub: 'En tiempo real', subColor: brand.status },
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
        label: 'Operaciones activas',
        value: '148',
        context: 'Pedidos, servicios y procesos en curso',
        icon: Activity,
        color: brand.crm,
    },
    {
        label: 'Pendientes financieros',
        value: 'RD$ 420K',
        context: 'Cobros y pagos que requieren atención',
        icon: Calculator,
        color: brand.tesoreria,
    },
    {
        label: 'Estado del ecosistema',
        value: 'Operativo',
        context: 'Servicios, integraciones y alertas',
        icon: ShieldCheck,
        color: brand.status,
    },
]

const dashboardViews = [
    {
        title: 'Visión operativa',
        desc: 'Una lectura rápida del movimiento diario y de los procesos que necesitan seguimiento.',
        icon: Activity,
        color: brand.pos,
        items: [
            'Operaciones y actividades en curso',
            'Pendientes y alertas relevantes',
            'Estado general de los módulos conectados',
        ],
    },
    {
        title: 'Visión administrativa',
        desc: 'Una perspectiva consolidada para comprender el comportamiento general del negocio.',
        icon: TrendingUp,
        color: brand.bi,
        items: [
            'Indicadores comerciales y financieros',
            'Estadísticas generales por período',
            'Resumen administrativo del ecosistema',
        ],
    },
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
        status: 'En desarrollo',
        statusColor: brand.ecommerce,
        stage: 'Presencia y relación digital',
        desc: 'Identidad digital, contenido, campañas, inbox social, interacción, leads y analítica de canales.',
        role: 'Construye la presencia digital de la empresa y convierte conversaciones e interacciones en oportunidades identificables.',
        connects: 'CRM, campañas, leads y analítica.',
        icon: MessageCircle,
        color: brand.social,
    },
    {
        name: 'CRM',
        href: 'https://crm.laudaapi.com',
        status: 'En desarrollo avanzado',
        statusColor: brand.ecf,
        stage: 'Gestión comercial digital',
        desc: 'Contactos, leads, clientes, oportunidades, actividades, pipeline y seguimiento comercial.',
        role: 'Organiza la relación comercial y convierte oportunidades digitales en procesos de venta gestionados.',
        connects: 'Social, POS, Ecommerce y BI.',
        icon: Users,
        color: brand.crm,
    },
    {
        name: 'POS',
        href: 'https://pos.laudaapi.com',
        status: 'En desarrollo avanzado',
        statusColor: brand.ecf,
        stage: 'Operación digital',
        desc: 'Pedidos, ventas, servicios, caja, crédito, CxC, inventario, almacén, empaque, rutas y despacho.',
        role: 'Digitaliza la ejecución comercial y operativa para convertir oportunidades en transacciones y procesos controlados.',
        connects: 'CRM, Ecommerce, e-CF, Delivery, Bancos y BI.',
        icon: Store,
        color: brand.pos,
    },
    {
        name: 'BYS',
        href: 'https://bys.laudaapi.com',
        status: 'En desarrollo',
        statusColor: brand.bys,
        stage: 'Administración digital',
        desc: 'Compras, proveedores, abastecimiento, importaciones, recepción de mercancía y control documental.',
        role: 'Digitaliza el ciclo de compras y abastecimiento, conectando proveedores, documentos y obligaciones administrativas.',
        connects: 'POS, e-CF, Cumplimiento, Tesorería y Contabilidad.',
        icon: Boxes,
        color: brand.bys,
    },
    {
        name: 'e-CF',
        href: 'https://ecf.laudaapi.com',
        status: 'Disponible',
        statusColor: brand.pos,
        stage: 'Fiscalidad digital',
        desc: 'Motor fiscal para firmar, enviar, consultar estados y responder comprobantes electrónicos ante DGII.',
        role: 'Incorpora la facturación electrónica al flujo operativo sin separar la operación comercial del cumplimiento fiscal.',
        connects: 'POS, Cumplimiento, BYS y Contabilidad.',
        icon: FileText,
        color: brand.ecf,
    },
    {
        name: 'Cumplimiento',
        href: 'https://cumplimiento.laudaapi.com',
        status: 'Disponible',
        statusColor: brand.pos,
        stage: 'Cumplimiento digital',
        desc: 'Control de obligaciones, documentos fiscales, vencimientos, soporte y trazabilidad.',
        role: 'Centraliza seguimiento y evidencia de cumplimiento para reducir tareas manuales y riesgos administrativos.',
        connects: 'e-CF, POS, BYS, Contabilidad y Status.',
        icon: ShieldCheck,
        color: brand.cumplimiento,
    },
    {
        name: 'Tesorería',
        href: 'https://tesoreria.laudaapi.com',
        status: 'En desarrollo',
        statusColor: brand.tesoreria,
        stage: 'Gestión financiera',
        desc: 'Pagos, bancos, caja, conciliación, transferencias, cheques y ejecución de pagos autorizados.',
        role: 'Conecta los movimientos de dinero con ventas, compras y obligaciones para mejorar el control financiero.',
        connects: 'POS, BYS, Bancos, Contabilidad y RRHH.',
        icon: Landmark,
        color: brand.tesoreria,
    },
    {
        name: 'Status',
        href: 'https://status.laudaapi.com',
        status: 'Disponible',
        statusColor: brand.pos,
        stage: 'Integración y continuidad',
        desc: 'Monitoreo de disponibilidad, DGII, endpoints, APIs, incidencias y salud general del ecosistema.',
        role: 'Aporta observabilidad y trazabilidad técnica para operar un ecosistema integrado con mayor continuidad.',
        connects: 'Todas las soluciones LAUDAAPI.',
        icon: Activity,
        color: brand.status,
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
        desc: 'Diseñamos el camino, transferimos la metodología y acompañamos a su equipo mientras la empresa ejecuta gran parte del roadmap con recursos internos.',
        badge: 'Guiado',
        idealFor: 'Empresas con personal interno capaz de ejecutar tareas, organizar información y coordinar la implementación.',
        laudaDoes: [
            'Diagnóstico y roadmap',
            'Metodología y mejores prácticas',
            'Capacitación y validación',
            'Sesiones de seguimiento',
        ],
        clientDoes: [
            'Ejecuta las tareas internas',
            'Prepara y depura información',
            'Coordina a sus equipos',
            'Implementa las acciones acordadas',
        ],
        icon: CheckCircle2,
        color: brand.pos,
        recommended: false,
    },
    {
        title: 'LAUDA 360 Asistido',
        level: 'Trabajamos juntos',
        desc: 'LAUDA y su equipo ejecutan la transformación de forma conjunta, combinando el conocimiento interno del negocio con nuestra metodología y capacidad de implementación.',
        badge: 'Recomendado',
        idealFor: 'Empresas con responsables internos, pero sin un equipo especializado que pueda conducir por sí solo toda la transformación digital.',
        laudaDoes: [
            'Levantamiento y diseño de procesos',
            'Configuración e implementación',
            'Migración e integración',
            'Capacitación y seguimiento',
        ],
        clientDoes: [
            'Aporta conocimiento del negocio',
            'Designa responsables internos',
            'Valida datos y decisiones',
            'Participa activamente en la adopción',
        ],
        icon: Users,
        color: brand.crm,
        recommended: true,
    },
    {
        title: 'LAUDA 360 Gestionado',
        level: 'LAUDA lidera',
        desc: 'Asumimos la dirección integral del programa y actuamos como una oficina externa de transformación digital para coordinar y ejecutar el roadmap de principio a fin.',
        badge: 'Gestionado',
        idealFor: 'Empresas que necesitan transformarse, pero no cuentan con la estructura, tiempo o experiencia interna para liderar el proceso.',
        laudaDoes: [
            'Dirección integral del programa',
            'Coordinación entre áreas',
            'Ejecución técnica y funcional',
            'Gestión del cambio y optimización',
        ],
        clientDoes: [
            'Designa un patrocinador interno',
            'Facilita información y acceso',
            'Toma decisiones estratégicas',
            'Valida resultados e hitos',
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
        desc: 'Conocer cómo opera hoy la empresa, qué tan preparada está para transformarse y dónde están las brechas de mayor impacto.',
        result: 'Diagnóstico, prioridades y roadmap de transformación.',
        icon: Activity,
        color: brand.main,
    },
    {
        number: '02',
        phase: 'Preparar',
        title: 'Fundamentos para Transformar',
        desc: 'Organizar información, responsables, procesos esenciales y condiciones mínimas para que la transformación pueda ejecutarse con orden.',
        result: 'Empresa preparada para iniciar la digitalización.',
        icon: ShieldCheck,
        color: brand.cumplimiento,
    },
    {
        number: '03',
        phase: 'Digitalizar',
        title: 'Presencia y Relación Digital',
        desc: 'Construir una presencia digital coherente y organizar los canales desde los cuales la empresa comunica, atiende y capta oportunidades.',
        result: 'Canales digitales organizados y conectados con el negocio.',
        icon: MessageCircle,
        color: brand.social,
    },
    {
        number: '04',
        phase: 'Digitalizar',
        title: 'Gestión Comercial Digital',
        desc: 'Convertir contactos e interacciones en un proceso comercial medible, con seguimiento, oportunidades, responsables y trazabilidad.',
        result: 'Proceso comercial centralizado y controlable.',
        icon: Users,
        color: brand.crm,
    },
    {
        number: '05',
        phase: 'Digitalizar',
        title: 'Operación Digital',
        desc: 'Llevar los procesos principales del negocio a una operación digital estructurada: ventas, servicios, inventario, cobros, ecommerce o logística según corresponda.',
        result: 'Operación diaria digitalizada y trazable.',
        icon: Store,
        color: brand.pos,
    },
    {
        number: '06',
        phase: 'Digitalizar',
        title: 'Administración y Cumplimiento Digital',
        desc: 'Digitalizar compras, proveedores, facturación electrónica, obligaciones y procesos administrativos que sostienen la operación.',
        result: 'Backoffice organizado, conectado y con mayor control.',
        icon: Calculator,
        color: brand.bys,
    },
    {
        number: '07',
        phase: 'Conectar',
        title: 'Empresa Conectada',
        desc: 'Integrar las áreas digitalizadas para que la información fluya entre procesos y reducir duplicidad, tareas manuales y puntos de ruptura.',
        result: 'Procesos de extremo a extremo integrados y automatizables.',
        icon: RefreshCw,
        color: brand.status,
    },
    {
        number: '08',
        phase: 'Decidir',
        title: 'Empresa Inteligente',
        desc: 'Consolidar información confiable en indicadores, dashboards y analítica para dirigir la empresa con una visión integral del negocio.',
        result: 'Decisiones basadas en datos y mejora continua.',
        icon: TrendingUp,
        color: brand.bi,
    },
]

const requestTypes = [
    'Iniciar Transformación Digital 360',
    'Solicitar Diagnóstico Digital',
    'Conocer LAUDA 360 Guiado',
    'Conocer LAUDA 360 Asistido',
    'Conocer LAUDA 360 Gestionado',
    'Ya soy cliente / necesito asistencia',
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

const contactForm = ref({
    name: '',
    company: '',
    rnc: '',
    phone: '',
    email: '',
    request_type: 'Iniciar Transformación Digital 360',
    solution_interest: 'Por definir después del diagnóstico',
    message: '',
    terms: true,
})

function resetContactForm() {
    contactForm.value = {
        name: '',
        company: '',
        rnc: '',
        phone: '',
        email: '',
        request_type: 'Iniciar Transformación Digital 360',
        solution_interest: 'Por definir después del diagnóstico',
        message: '',
        terms: true,
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function buildContactPayload() {
    const form = contactForm.value

    // Compatible con ContactRequest:
    // - Los campos base van directo a columnas reales.
    // - La intención comercial se guarda en topic.
    // - Los datos extra del intake centralizado van en metadata.
    // - También se anexan al message para que los correos actuales los muestren
    //   aunque los Mailables todavía no lean metadata.
    return {
        name: form.name,
        company: form.company,
        email: form.email,
        phone: form.phone,
        topic: `${form.request_type} - ${form.solution_interest}`,
        terms: form.terms,
        metadata: {
            source: 'laudaapi.com',
            request_type: form.request_type,
            solution_interest: form.solution_interest,
            rnc: form.rnc || null,
            intake_type: 'digital_transformation_360',
        },
        message: [
            `Tipo de solicitud: ${form.request_type}`,
            `Modalidad de interés: ${form.solution_interest}`,
            `RNC: ${form.rnc || 'No indicado'}`,
            '',
            'Mensaje:',
            form.message,
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
        const csrfToken = getCsrfToken()

        const response = await fetch(CONTACT_REQUEST_ENDPOINT, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify(buildContactPayload()),
        })

        const data = await response.json().catch(() => ({}))

        if (response.status === 422) {
            contactErrors.value = data.errors || {}
            return
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'No se pudo enviar la solicitud en este momento.')
        }

        contactSubmitted.value = true
        contactSuccessMessage.value = data.message || 'Formulario enviado correctamente.'
        resetContactForm()
    } catch (error) {
        contactErrors.value = {
            general: error?.message || 'Ocurrió un error al procesar la solicitud.',
        }
    } finally {
        contactProcessing.value = false
    }
}

const extendedModules = [
    { name: 'RRHH', desc: 'Recursos humanos, equipos, asistencia y procesos internos.', icon: Users, color: brand.rrhh, relation: 'Consume operación y usuarios del ecosistema.' },
    { name: 'Tesorería', desc: 'Pagos, bancos, caja, conciliación, transferencias y nómina aprobada.', icon: Landmark, color: brand.tesoreria, relation: 'Cruza cobros POS, CxP de BYS, bancos, contabilidad y lotes de nómina aprobados.' },
    { name: 'Proyectos', desc: 'Planificación, tareas, entregables y ejecución.', icon: Boxes, color: brand.proyectos, relation: 'Puede nacer desde CRM o servicios vendidos en POS.' },
    { name: 'Eventos', desc: 'Gestión de eventos, invitados, ventas y operación.', icon: Activity, color: brand.eventos, relation: 'Puede conectar Social, CRM, POS y facturación.' },
    { name: 'Transporte personal', desc: 'Rutas, unidades, pasajeros, horarios y evidencia.', icon: Truck, color: brand.transporte, relation: 'Extiende logística sin tocar facturación.' },
    { name: 'Servicios de grúas', desc: 'Asignación de grúas, asistencia, tracking y cobro.', icon: Truck, color: brand.gruas, relation: 'Opera servicios conectados a POS y rutas.' },
    { name: 'Loans', desc: 'Préstamos, cartera, cuotas, mora y cobranza.', icon: Calculator, color: brand.loans, relation: 'Se apoya en clientes, cobros, bancos y BI.' },
    { name: 'Dealers', desc: 'Inventario vehicular, clientes, ventas y financiamiento.', icon: Store, color: brand.dealers, relation: 'Integra CRM, POS, loans, e-CF y BI.' },
    { name: 'BI', desc: 'Dashboards, KPIs, analítica y toma de decisiones.', icon: TrendingUp, color: brand.bi, relation: 'Lee señales del ecosistema sin duplicar operación.' },
]

const mainNavLinks = [
    { label: 'Transformación 360', href: '#lauda360', primary: true },
    { label: 'Roadmap', href: '#roadmap' },
    { label: 'Modalidades', href: '#modalidades' },
    { label: 'Ecosistema', href: '#ecosistema-detalle' },
    { label: 'Inteligencia', href: '#dashboard' },
    { label: 'Contacto', href: '#contacto' },
]

const currentYear = new Date().getFullYear()

const footerQuickLinks = [
    { label: 'Transformación 360', href: '#lauda360' },
    { label: 'Roadmap', href: '#roadmap' },
    { label: 'Modalidades', href: '#modalidades' },
    { label: 'Ecosistema', href: '#ecosistema-detalle' },
    { label: 'Inteligencia', href: '#dashboard' },
    { label: 'Contacto', href: '#contacto' },
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

const hamburgerRef = ref(null)
const mobileCloseRef = ref(null)

/* -------------------------------------------------------------------------- */
/*  Escalado del diagrama                                                      */
/*  El diagrama se maqueta a un tamaño fijo (DESIGN_W x DESIGN_H) donde todo    */
/*  respira, y se escala proporcionalmente para caber en su contenedor.        */
/*  Así nunca hay scroll horizontal ni nodos encimados.                        */
/* -------------------------------------------------------------------------- */

const DESIGN_W = 700
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
    diagramScale.value = Math.min(1.08, w / DESIGN_W)
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
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    ro && ro.disconnect()
    if (rafId) cancelAnimationFrame(rafId)
    if (logTimer) clearInterval(logTimer)

    document.body.style.overflow = ''

    window.removeEventListener('resize', scheduleRefresh)
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>

    <Head :title="seo.title">
        <meta name="description" :content="seo.description" />
        <meta name="robots" content="index, follow" />
        <meta name="author" content="LaudaAPI" />
        <meta name="application-name" content="LaudaAPI" />
        <meta name="theme-color" content="#F5333C" />
        <meta name="keywords" content="transformación digital, transformación digital empresarial, LAUDA 360, LAUDAAPI, digitalización de empresas, CRM, Social, POS, e-CF, cumplimiento, compras, Business Intelligence, BI, República Dominicana" />

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
        <!-- ===================== NAV ===================== -->
        <nav class="lauda-nav sticky top-0 z-50 border-b backdrop-blur-xl">
            <div class="mx-auto flex h-19 max-w-none items-center gap-3 px-4 sm:gap-6 sm:px-6 lg:gap-8 lg:px-8 2xl:px-10">
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

                <div class="ml-auto hidden items-center gap-4 text-[14px] font-semibold text-muted xl:gap-6 xl:text-[15px] lg:flex">
                    <a v-for="link in mainNavLinks" :key="link.href" :href="link.href" class="relative flex h-19 items-center whitespace-nowrap transition-colors hover:text-(--text)" :class="link.primary
                        ? 'font-black text-(--text) after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-full after:bg-(--brand)'
                        : ''">
                        {{ link.label }}
                    </a>
                </div>

                <button type="button" class="lauda-mode-toggle ml-auto grid h-11 w-11 shrink-0 place-items-center rounded-xl border transition lg:ml-0" :aria-label="isDarkMode ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'" :title="isDarkMode ? 'Modo claro' : 'Modo oscuro'" @click="togglePresentationMode">
                    <component :is="isDarkMode ? Sun : Moon" class="h-4 w-4" />
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

                    <div class="mt-6 min-h-0 flex-1 overflow-y-auto pr-1">
                        <nav class="flex flex-col gap-1">
                            <a v-for="link in mainNavLinks" :key="link.href" :href="link.href" class="lauda-mobile-link rounded-xl px-3 py-3 text-base font-semibold text-(--text)" :class="link.primary ? 'bg-(--brand)/10 font-black text-(--brand)' : ''" @click="closeMobileMenu()">
                                {{ link.label }}
                            </a>
                        </nav>
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
                <!-- Columna izquierda: propuesta de valor -->
                <div class="lauda-hero__copy">
                    <span class="inline-flex items-center gap-2 rounded-full bg-(--brand)/10 px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-(--brand)">
                        <span class="h-1.5 w-1.5 rounded-full bg-(--brand)" />
                        LAUDA Transformación Digital 360
                    </span>

                    <h1 class="mt-6 text-[34px] font-extrabold leading-[1.02] tracking-[-0.04em] text-(--text) sm:text-[48px] lg:text-[56px]">
                        Transformamos su empresa para competir
                        <span class="text-(--brand)">en un mundo digital.</span>
                    </h1>

                    <p class="mt-6 max-w-145 text-[17px] leading-relaxed text-muted">
                        Evaluamos dónde está su empresa, diseñamos su hoja de ruta y la acompañamos en la
                        digitalización e integración de sus procesos, personas, tecnología y datos.
                    </p>

                    <div class="mt-6 rounded-2xl border border-border bg-(--surface) p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-(--brand)/10 text-(--brand)">
                                <RefreshCw class="h-5 w-5" />
                            </span>

                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-(--brand)">
                                    Un proceso, no una instalación de software
                                </p>
                                <p class="mt-1.5 text-sm leading-relaxed text-muted">
                                    Diagnóstico, roadmap, implementación, integración, adopción y mejora continua,
                                    con el ecosistema LAUDAAPI como plataforma tecnológica principal.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <Button class="w-full justify-center gap-2 rounded-xl bg-(--brand) px-6 py-6 text-white hover:bg-(--brand-hover) sm:w-auto" @click="scrollToId('contacto')">
                            Iniciar diagnóstico digital
                            <ArrowRight class="h-4 w-4" />
                        </Button>

                        <Button variant="outline" class="lauda-outline-button w-full justify-center gap-2 rounded-xl px-6 py-6 sm:w-auto" @click="scrollToId('lauda360')">
                            Ver cómo funciona
                            <ArrowRight class="h-4 w-4" />
                        </Button>
                    </div>

                    <p class="mt-4 text-xs font-semibold leading-relaxed text-(--soft)">
                        Modalidades de acompañamiento: Guiado · Asistido · Gestionado
                    </p>

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

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/[0.07] bg-[#111625] p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/6 text-[#A8B0C3]">
                                    <Boxes class="h-5 w-5" />
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-[#6F7890]">01</span>
                            </div>
                            <p class="mt-4 text-sm font-black text-white">Empresa tradicional</p>
                            <p class="mt-1.5 text-xs leading-relaxed text-[#9AA1B8]">
                                Procesos manuales, información dispersa, Excel, WhatsApp y sistemas aislados.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[#3B82F6]/20 bg-[#3B82F6]/8 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#3B82F6]/15 text-[#60A5FA]">
                                    <Zap class="h-5 w-5" />
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-[#60A5FA]">02</span>
                            </div>
                            <p class="mt-4 text-sm font-black text-white">Empresa digital</p>
                            <p class="mt-1.5 text-xs leading-relaxed text-[#AAB4CA]">
                                Canales, información y procesos prioritarios pasan a operar digitalmente.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[#A855F7]/20 bg-[#A855F7]/8 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#A855F7]/15 text-[#C084FC]">
                                    <RefreshCw class="h-5 w-5" />
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-[#C084FC]">03</span>
                            </div>
                            <p class="mt-4 text-sm font-black text-white">Empresa conectada</p>
                            <p class="mt-1.5 text-xs leading-relaxed text-[#AAB4CA]">
                                Comercial, operación y administración comparten información y flujos integrados.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-[#22C55E]/20 bg-[#22C55E]/8 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#22C55E]/15 text-[#4ADE80]">
                                    <TrendingUp class="h-5 w-5" />
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-[#4ADE80]">04</span>
                            </div>
                            <p class="mt-4 text-sm font-black text-white">Empresa inteligente</p>
                            <p class="mt-1.5 text-xs leading-relaxed text-[#AAB4CA]">
                                Data, BI, indicadores y automatización convierten la operación en mejores decisiones.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-3xl border border-white/[0.07] bg-[#0D1120] p-4 sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#F5333C]">
                                    Ecosistema tecnológico LAUDAAPI
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-[#9AA1B8]">
                                    Las plataformas se incorporan progresivamente según el roadmap de la empresa.
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

                    <div class="mt-4 flex items-center gap-3 rounded-2xl border border-white/[0.07] bg-white/2.5 px-4 py-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#F5333C]/12 text-[#F5333C]">
                            <ShieldCheck class="h-4 w-4" />
                        </span>
                        <p class="text-xs leading-relaxed text-[#AAB4CA]">
                            <span class="font-black text-white">LAUDAAPI no define la estrategia de transformación.</span>
                            Es la plataforma tecnológica que permite ejecutarla, integrarla y medirla.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== LAUDA 360 ===================== -->
        <section id="lauda360" class="mx-auto max-w-360 scroll-mt-24 px-4 py-12 sm:px-6 sm:py-16 2xl:px-8">
            <div class="mb-9 grid gap-6 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Qué es LAUDA 360
                    </p>
                    <h2 class="mt-2 max-w-3xl text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        Un servicio para transformar la empresa, no solo para digitalizar tareas.
                    </h2>
                </div>

                <p class="max-w-2xl text-sm leading-relaxed text-muted lg:justify-self-end">
                    LAUDA Transformación Digital 360 combina diagnóstico, estrategia, acompañamiento e implementación.
                    Partimos de la realidad de cada empresa, definimos prioridades y construimos una ruta progresiva para
                    conectar personas, procesos, tecnología y datos alrededor de objetivos de negocio concretos.
                </p>
            </div>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,0.78fr)_minmax(0,1.22fr)]">
                <!-- Qué obtiene el cliente -->
                <div class="lauda-card rounded-4xl border p-6 lg:p-7">
                    <div class="flex items-start gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-(--brand)/10 text-(--brand)">
                            <Activity class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-(--brand)">
                                Punto de partida
                            </p>
                            <h3 class="mt-1 text-xl font-black text-(--text)">
                                Primero entendemos dónde está la empresa y qué necesita transformar.
                            </h3>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-4">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <div>
                                <p class="text-sm font-black text-(--text)">Diagnóstico claro</p>
                                <p class="mt-1 text-xs leading-relaxed text-muted">
                                    Identificamos nivel de madurez, brechas, riesgos y oportunidades de transformación.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-4">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <div>
                                <p class="text-sm font-black text-(--text)">Roadmap por prioridades</p>
                                <p class="mt-1 text-xs leading-relaxed text-muted">
                                    Ordenamos objetivos y etapas según impacto, capacidad de la empresa y dependencias reales.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-4">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <div>
                                <p class="text-sm font-black text-(--text)">Acompañamiento hasta la adopción</p>
                                <p class="mt-1 text-xs leading-relaxed text-muted">
                                    No terminamos al configurar herramientas: acompañamos implementación, integración y uso real.
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
                            <h3 class="mt-5 text-lg font-black text-(--text)">
                                {{ pillar.title }}
                            </h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted">
                                {{ pillar.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Separación servicio / tecnología -->
            <div class="mt-6 grid overflow-hidden rounded-4xl border border-border bg-(--surface) md:grid-cols-2">
                <div class="border-b border-border p-5 md:border-b-0 md:border-r md:p-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-(--brand)">
                        LAUDA Transformación Digital 360
                    </p>
                    <p class="mt-2 text-lg font-black text-(--text)">
                        Define el camino de transformación.
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        Diagnóstico, estrategia, prioridades, procesos, acompañamiento, implementación, adopción y mejora continua.
                    </p>
                </div>

                <div class="p-5 md:p-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#3B82F6]">
                        Ecosistema LAUDAAPI
                    </p>
                    <p class="mt-2 text-lg font-black text-(--text)">
                        Aporta la tecnología para ejecutarlo.
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        Social, CRM, POS, administración, integraciones, automatización, Data y BI se incorporan según el roadmap de cada empresa.
                    </p>
                </div>
            </div>
        </section>

        <!-- ===================== ROADMAP ===================== -->
        <section id="roadmap" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 sm:py-14 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Roadmap de Transformación
                    </p>
                    <h2 class="mt-2 max-w-4xl text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        De entender la empresa a convertirla en una organización conectada e inteligente.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    La metodología es común, pero no todas las empresas comienzan en el mismo punto. El diagnóstico define
                    qué debe priorizarse, cuánto acompañamiento se necesita y el ritmo adecuado para avanzar.
                </p>
            </div>

            <div class="mb-6 rounded-3xl border border-border bg-(--surface) p-4 shadow-sm sm:p-5">
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

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="stage in roadmapStages" :key="stage.number" class="lauda-card relative flex min-h-full flex-col overflow-hidden rounded-3xl border p-5">
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

                    <h3 class="mt-5 text-base font-black text-(--text)">
                        {{ stage.title }}
                    </h3>

                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        {{ stage.desc }}
                    </p>

                    <div class="mt-auto pt-5">
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
                        LAUDAAPI entra cuando la transformación lo requiere.
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-muted">
                        Social, CRM, POS, BYS, e-CF, Cumplimiento, integraciones, Data y BI se incorporan progresivamente como capacidades tecnológicas del roadmap, no como aplicaciones aisladas que la empresa debe elegir desde el inicio.
                    </p>
                </div>

                <Button type="button" variant="outline" class="lauda-outline-button shrink-0 justify-center rounded-xl px-5 py-5" @click="scrollToId('modalidades')">
                    Ver modalidades de acompañamiento
                </Button>
            </div>
        </section>

        <!-- ===================== MODALIDADES ===================== -->
        <section id="modalidades" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 sm:py-14 2xl:px-8">
            <div class="rounded-4xl border border-border bg-(--surface) p-6 shadow-sm lg:p-8">
                <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                    <div class="max-w-4xl">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                            Modalidades de acompañamiento
                        </p>
                        <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                            Elija cuánto acompañamiento necesita, no qué tecnología comprar.
                        </h2>
                    </div>

                    <p class="max-w-xl text-sm leading-relaxed text-muted">
                        Las tres modalidades recorren la misma metodología LAUDA 360. Lo que cambia es la distribución del trabajo entre su empresa y nuestro equipo durante cada etapa de la transformación.
                    </p>
                </div>

                <div class="grid gap-5 lg:grid-cols-3">
                    <article v-for="option in serviceModels" :key="option.title" class="lauda-service-card relative flex h-full flex-col rounded-3xl border border-border bg-(--surface-soft) p-5 sm:p-6" :class="option.recommended && 'lauda-service-card--recommended'">
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

                        <p class="mt-5 text-[10px] font-black uppercase tracking-[0.16em]" :style="{ color: option.color }">
                            {{ option.level }}
                        </p>

                        <h3 class="mt-1 text-xl font-black text-(--text)">
                            {{ option.title }}
                        </h3>

                        <p class="mt-3 text-sm leading-relaxed text-muted">
                            {{ option.desc }}
                        </p>

                        <div class="mt-5 rounded-2xl border border-border bg-(--surface-solid) p-4">
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
        <section id="ecosistema-detalle" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 sm:py-14 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Infraestructura tecnológica de la transformación
                    </p>
                    <h2 class="mt-2 max-w-4xl text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        LAUDAAPI acompaña cada etapa sin obligar a la empresa a operar como aplicaciones aisladas.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    La tecnología se incorpora según el roadmap. Social, CRM, POS, administración, integración y datos forman una secuencia conectada que evoluciona con la madurez de la empresa.
                </p>
            </div>

            <div class="mb-6 rounded-4xl border border-border bg-(--surface) p-5 shadow-sm sm:p-6">
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
                    <p class="text-sm font-black text-(--text)">La tecnología sigue al roadmap, no al revés.</p>
                    <p class="mt-1 max-w-3xl text-sm leading-relaxed text-muted">
                        El diagnóstico determina qué capacidades se activan primero, qué debe integrarse y qué puede esperar. Así la inversión tecnológica responde a objetivos empresariales concretos.
                    </p>
                </div>

                <Button type="button" variant="outline" class="lauda-outline-button shrink-0 rounded-xl px-5 py-5" @click="scrollToId('soluciones')">
                    Ver capacidades LAUDAAPI
                </Button>
            </div>
        </section>

        <!-- ===================== CAPACIDADES DEL ECOSISTEMA ===================== -->
        <section id="soluciones" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 sm:py-14 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Capacidades especializadas
                    </p>
                    <h2 class="mt-2 max-w-4xl text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        Cada plataforma cumple una función específica dentro del proceso de transformación.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    Las aplicaciones mantienen su especialidad y su tenant, pero se relacionan con una misma empresa y comparten información mediante el ecosistema LAUDAAPI.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                <a v-for="solution in primarySolutions" :key="solution.name" :href="solution.href" class="lauda-card group rounded-3xl border p-5 transition-all hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl" :style="{ background: solution.color + '1a' }">
                            <component :is="solution.icon" class="h-5 w-5" :style="{ color: solution.color }" />
                        </span>

                        <span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-widest" :style="{ background: solution.statusColor + '1a', color: solution.statusColor }">
                            {{ solution.status }}
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
                        <p class="text-[9px] font-black uppercase tracking-[0.12em] text-(--soft)">Papel en la transformación</p>
                        <p class="mt-1 text-xs leading-relaxed text-muted">
                            {{ solution.role }}
                        </p>
                    </div>

                    <p class="mt-3 text-[11px] leading-relaxed text-muted">
                        <span class="font-black text-(--text)">Se integra con:</span> {{ solution.connects }}
                    </p>

                    <div class="mt-4 inline-flex items-center gap-2 text-sm font-black text-(--brand)">
                        Conocer plataforma
                        <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </div>
                </a>
            </div>
        </section>

        <!-- ===================== INTELIGENCIA / BI ===================== -->
        <section id="dashboard" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 sm:py-14 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                            Empresa inteligente
                        </p>
                        <span class="rounded-full border border-border bg-(--surface) px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-(--soft)">
                            En desarrollo
                        </span>
                    </div>

                    <h2 class="mt-2 max-w-4xl text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        De la operación diaria a decisiones empresariales basadas en datos.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    A medida que la empresa avanza en su transformación, LAUDAAPI consolida información de las plataformas activas para ofrecer indicadores ejecutivos, BI y una visión transversal del negocio.
                </p>
            </div>

            <div class="lauda-card overflow-hidden rounded-4xl border p-4 sm:p-6 lg:p-8">
                <div class="rounded-4xl border border-white/8 bg-[#080B15] p-4 shadow-2xl shadow-black/20 sm:p-6">
                    <div class="flex flex-col gap-4 border-b border-white/8 pb-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-[#F5333C]">
                                <span class="h-2 w-2 rounded-full bg-[#F5333C]" />
                                Vista demostrativa
                            </div>
                            <h3 class="mt-2 text-xl font-black text-white sm:text-2xl">
                                Resumen general del ecosistema
                            </h3>
                            <p class="mt-1 text-sm leading-relaxed text-[#9AA1B8]">
                                Indicadores ilustrativos para presentar la experiencia futura del dashboard.
                            </p>
                        </div>

                        <div class="inline-flex w-fit items-center gap-2 rounded-full border border-[#22C55E]/20 bg-[#22C55E]/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] text-[#4ADE80]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#4ADE80]" />
                            Ecosistema conectado
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="metric in dashboardPreviewMetrics" :key="metric.label" class="rounded-2xl border border-white/[0.07] bg-[#111625] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" :style="{ background: metric.color + '22' }">
                                    <component :is="metric.icon" class="h-5 w-5" :style="{ color: metric.color }" />
                                </span>

                                <span class="rounded-full border border-white/[0.07] px-2 py-1 text-[9px] font-black uppercase tracking-widest text-[#7A8298]">
                                    Ejemplo
                                </span>
                            </div>

                            <p class="mt-4 text-[11px] font-bold uppercase tracking-[0.08em] text-[#8E96AC]">
                                {{ metric.label }}
                            </p>
                            <p class="mt-1 text-2xl font-black tracking-tight text-white">
                                {{ metric.value }}
                            </p>
                            <p class="mt-2 text-xs leading-relaxed text-[#9AA1B8]">
                                {{ metric.context }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div v-for="view in dashboardViews" :key="view.title" class="rounded-3xl border border-white/[0.07] bg-[#0D1120] p-5">
                            <div class="flex items-start gap-4">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" :style="{ background: view.color + '22' }">
                                    <component :is="view.icon" class="h-5 w-5" :style="{ color: view.color }" />
                                </span>

                                <div>
                                    <h3 class="text-lg font-black text-white">
                                        {{ view.title }}
                                    </h3>
                                    <p class="mt-1 text-sm leading-relaxed text-[#9AA1B8]">
                                        {{ view.desc }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-2.5">
                                <div v-for="item in view.items" :key="item" class="flex items-center gap-3 rounded-2xl border border-white/6 bg-white/2.5 px-3 py-2.5">
                                    <CheckCircle2 class="h-4 w-4 shrink-0" :style="{ color: view.color }" />
                                    <span class="text-xs font-semibold leading-relaxed text-[#C1C7D6]">
                                        {{ item }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3 rounded-3xl border border-border bg-(--surface-soft) p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black text-(--text)">
                            Presentación conceptual
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-muted">
                            En esta etapa se comunica la función del dashboard. Las métricas, integraciones y vistas detalladas se desarrollarán posteriormente.
                        </p>
                    </div>

                    <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-full bg-(--brand)/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] text-(--brand)">
                        Próximamente
                    </span>
                </div>
            </div>
        </section>


        <!-- ===================== FLUJOS ===================== -->
        <section id="flujos" class="mx-auto max-w-360 scroll-mt-24 px-4 py-10 sm:px-6 sm:py-14 2xl:px-8">
            <div class="mb-8 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Empresa conectada
                    </p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        De procesos aislados a flujos digitales conectados de extremo a extremo.
                    </h2>
                </div>

                <p class="max-w-xl text-sm leading-relaxed text-muted">
                    Cada plataforma conserva su responsabilidad, pero comparte eventos e información para que la empresa pueda operar como un solo sistema y no como aplicaciones aisladas.
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
        <section id="contacto" class="mx-auto max-w-360 scroll-mt-24 px-4 pb-16 pt-6 sm:px-6 sm:pb-20 2xl:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-start">
                <div class="lauda-card rounded-4xl border p-6 lg:p-8">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-(--brand)">
                        Inicie su Transformación Digital 360
                    </p>

                    <h2 class="mt-2 text-3xl font-black tracking-tight text-(--text) sm:text-4xl">
                        El primer paso es entender dónde está su empresa y definir el camino correcto.
                    </h2>

                    <p class="mt-4 text-sm leading-relaxed text-muted">
                        Solicite un diagnóstico inicial para evaluar su nivel de madurez digital, identificar prioridades y definir un roadmap de transformación antes de decidir qué tecnologías deben implementarse.
                    </p>

                    <div class="mt-6 space-y-3 text-sm text-muted">
                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <span>Comenzamos con diagnóstico, prioridades y roadmap; no con la venta aislada de una aplicación.</span>
                        </div>
                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <span>El ecosistema LAUDAAPI se activa progresivamente según las necesidades reales de la empresa.</span>
                        </div>
                        <div class="flex gap-3 rounded-2xl border border-border bg-(--surface-soft) p-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-[#22C55E]" />
                            <span>Puede elegir acompañamiento Guiado, Asistido o Gestionado según su capacidad interna.</span>
                        </div>
                    </div>
                </div>

                <form class="lauda-card rounded-4xl border p-6 lg:p-8" @submit.prevent="submitContact">
                    <div class="mb-6 flex flex-col gap-3 border-b border-border pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-lg font-black text-(--text)">Datos de la solicitud</p>
                            <p class="mt-1 text-sm leading-relaxed text-muted">
                                El equipo LAUDA revisará la solicitud para coordinar el diagnóstico y orientar el siguiente paso.
                            </p>
                        </div>

                        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-[#22C55E]/20 bg-[#22C55E]/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-[#16A34A]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#22C55E]" />
                            Formulario activo
                        </span>
                    </div>

                    <div v-if="contactSubmitted" class="mb-5 rounded-2xl border border-[#22C55E]/25 bg-[#22C55E]/10 p-4 text-sm leading-relaxed text-[#15803D]">
                        <div class="flex items-start gap-3">
                            <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <p class="font-black">Solicitud recibida.</p>
                                <p class="mt-1">
                                    {{ contactSuccessMessage || 'Gracias. El equipo de LaudaAPI revisará la solicitud y responderá desde contacto@laudaapi.com.' }}
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
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">RNC</span>
                            <input v-model="contactForm.rnc" type="text" class="lauda-input" :class="contactErrors.rnc && 'lauda-input--error'" placeholder="Opcional / RNC de la empresa" inputmode="numeric" />
                            <span v-if="contactErrors.rnc" class="lauda-form-error">{{ contactErrors.rnc?.[ 0 ] || contactErrors.rnc }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Teléfono</span>
                            <input v-model="contactForm.phone" type="tel" class="lauda-input" :class="contactErrors.phone && 'lauda-input--error'" placeholder="809-000-0000" autocomplete="tel" />
                            <span v-if="contactErrors.phone" class="lauda-form-error">{{ contactErrors.phone?.[ 0 ] || contactErrors.phone }}</span>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Correo</span>
                            <input v-model="contactForm.email" type="email" class="lauda-input" :class="contactErrors.email && 'lauda-input--error'" placeholder="correo@empresa.com" autocomplete="email" required />
                            <span v-if="contactErrors.email" class="lauda-form-error">{{ contactErrors.email?.[ 0 ] || contactErrors.email }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Tipo de solicitud</span>
                            <select v-model="contactForm.request_type" class="lauda-input" :class="contactErrors.request_type && 'lauda-input--error'" required>
                                <option v-for="type in requestTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                            <span v-if="contactErrors.request_type" class="lauda-form-error">{{ contactErrors.request_type?.[ 0 ] || contactErrors.request_type }}</span>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Solución de interés</span>
                            <select v-model="contactForm.solution_interest" class="lauda-input" :class="contactErrors.solution_interest && 'lauda-input--error'" required>
                                <option value="Por definir después del diagnóstico">Por definir después del diagnóstico</option>
                                <option value="LAUDA 360 Guiado">LAUDA 360 Guiado</option>
                                <option value="LAUDA 360 Asistido">LAUDA 360 Asistido</option>
                                <option value="LAUDA 360 Gestionado">LAUDA 360 Gestionado</option>
                            </select>
                            <span v-if="contactErrors.solution_interest" class="lauda-form-error">{{ contactErrors.solution_interest?.[ 0 ] || contactErrors.solution_interest }}</span>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-(--soft)">Mensaje</span>
                            <textarea v-model="contactForm.message" rows="5" class="lauda-input resize-none" :class="contactErrors.message && 'lauda-input--error'" placeholder="Cuéntanos brevemente cómo opera hoy tu empresa, qué procesos quieres mejorar y cuáles son tus principales retos digitales." required />
                            <span v-if="contactErrors.message" class="lauda-form-error">{{ contactErrors.message?.[ 0 ] || contactErrors.message }}</span>
                        </label>

                        <label class="flex items-start gap-3 sm:col-span-2">
                            <input v-model="contactForm.terms" type="checkbox" class="mt-1 h-4 w-4 rounded border-border accent-[#F5333C]" required />
                            <span class="text-xs leading-relaxed text-muted">
                                Acepto que LaudaAPI me contacte para dar seguimiento a esta solicitud.
                                <span v-if="contactErrors.terms" class="lauda-form-error block">{{ contactErrors.terms?.[ 0 ] || contactErrors.terms }}</span>
                            </span>
                        </label>
                    </div>

                    <div v-if="contactErrors.general" class="mt-5 rounded-2xl border border-[#F5333C]/25 bg-[#F5333C]/10 p-4 text-sm leading-relaxed text-[#B91C1C]">
                        {{ contactErrors.general }}
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-relaxed text-muted">
                            Usaremos estos datos únicamente para dar seguimiento a tu solicitud de diagnóstico, transformación o asistencia.
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
            <div class="mx-auto grid max-w-360 gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[minmax(0,1.2fr)_repeat(3,minmax(0,0.8fr))] 2xl:px-8">
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
                        LAUDA acompaña a las empresas en su Transformación Digital 360 y utiliza el ecosistema LAUDAAPI para digitalizar, conectar y convertir la operación en información para decidir.
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
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-(--brand)">Transformación</p>
                    <nav class="mt-4 space-y-2">
                        <a v-for="link in footerQuickLinks" :key="link.href" :href="link.href" class="block text-sm font-semibold text-muted transition hover:text-(--text)">
                            {{ link.label }}
                        </a>
                    </nav>
                </div>

                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-(--brand)">Soluciones</p>
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
                        <a href="mailto:contacto@laudaapi.com" class="block text-sm font-semibold text-muted transition hover:text-(--text)">
                            contacto@laudaapi.com
                        </a>
                    </nav>
                </div>
            </div>

            <div class="border-t border-border">
                <div class="mx-auto flex max-w-360 flex-col gap-2 px-4 py-5 text-xs text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 2xl:px-8">
                    <span>© {{ currentYear }} LaudaAPI Digital. Todos los derechos reservados.</span>
                    <span>Transformación Digital 360 impulsada por LAUDAAPI.</span>
                </div>
            </div>
        </footer>
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

.lauda-menu-item:focus-visible,
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
/*  Responsive polish                                                          */
/* -------------------------------------------------------------------------- */

.lauda-page {
    overflow-x: hidden;
}

@media (max-width: 639px) {
    .lauda-hero {
        padding-top: 28px;
    }

    .lauda-hero__layout {
        gap: 26px;
    }

    .lauda-hero__copy {
        max-width: none;
    }

    .lauda-hero__copy p {
        font-size: 15.5px;
    }

    .lauda-hero__chips {
        grid-template-columns: 1fr;
        gap: 10px;
        margin-top: 28px;
    }

    .lauda-hero__panel {
        padding: 12px;
        border-radius: 22px;
    }

    .lauda-diagram-scroll {
        border-radius: 18px;
    }

    .lauda-hero__status-grid {
        gap: 12px;
        margin-top: 12px;
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
