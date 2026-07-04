<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    ArrowUpRight,
    Building2,
    CalendarCheck,
    CheckCircle2,
    Database,
    Instagram,
    Mail,
    MessageCircle,
    Network,
    PackageCheck,
    ReceiptText,
    ShieldCheck,
    ShoppingCart,
    Store,
    Truck,
    Users,
    Workflow,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';
import PublicNav from '@/components/PublicNav.vue';
import LaudaEcosystemFlow from '@/components/LaudaEcosystemFlow.vue';

type ProjectId =
    | 'social'
    | 'crm'
    | 'ecommerce'
    | 'pos'
    | 'delivery'
    | 'ecf'
    | 'cumplimiento'
    | 'bys'
    | 'contabilidad'
    | 'status';

type FlowId = 'lead-to-cash' | 'ecommerce-to-delivery' | 'fiscal-loop' | 'backoffice-loop';

type Project = {
    id: ProjectId;
    code: string;
    title: string;
    domain: string;
    href: string;
    stage: string;
    role: string;
    summary: string;
    receives: string;
    owns: string;
    sends: string;
    never: string;
    icon: Component;
    position: { x: number; y: number };
    badgeClass: string;
    iconClass: string;
    glowClass: string;
};

type Relation = {
    from: ProjectId;
    to: ProjectId;
    short: string;
    label: string;
    flowIds: FlowId[];
};

const projects: Project[] = [
    {
        id: 'social',
        code: 'Social',
        title: 'SocialLaudaAPI',
        domain: 'social.laudaapi.com',
        href: 'https://social.laudaapi.com',
        stage: 'Atracción',
        role: 'Contenido, inbox, campañas y leads',
        summary: 'Convierte interacciones de redes, formularios y campañas en leads trazables para el CRM.',
        receives: 'Mensajes, comentarios, formularios, campañas y respuestas sociales.',
        owns: 'Conversaciones, contenido, campañas, origen del lead y primera trazabilidad.',
        sends: 'Lead social hacia CRM con fuente, campaña y contexto comercial.',
        never: 'No factura, no toca inventario, no crea CxC y no decide precios.',
        icon: MessageCircle,
        position: { x: 105, y: 145 },
        badgeClass: 'border-pink-200 bg-pink-50 text-pink-900 dark:border-pink-900/40 dark:bg-pink-950/20 dark:text-pink-300',
        iconClass: 'bg-pink-500/10 text-pink-500 ring-pink-500/25',
        glowClass: 'bg-pink-500/20',
    },
    {
        id: 'crm',
        code: 'CRM',
        title: 'CrmLaudaAPI',
        domain: 'crm.laudaapi.com',
        href: 'https://crm.laudaapi.com',
        stage: 'Conversión',
        role: 'Leads, clientes, oportunidades y actividades',
        summary: 'Organiza el pipeline comercial y envía solicitudes formales al POS cuando la venta está lista.',
        receives: 'Leads de Social, datos de clientes, contactos, tareas y oportunidades.',
        owns: 'Pipeline, seguimiento comercial, actividades, contactos y calificación.',
        sends: 'Solicitud de propuesta / oportunidad hacia POS con idempotencia y trazabilidad.',
        never: 'No factura, no rebaja inventario, no genera conduces y no crea cuentas por cobrar.',
        icon: Users,
        position: { x: 310, y: 85 },
        badgeClass: 'border-purple-200 bg-purple-50 text-purple-900 dark:border-purple-900/40 dark:bg-purple-950/20 dark:text-purple-300',
        iconClass: 'bg-purple-500/10 text-purple-500 ring-purple-500/25',
        glowClass: 'bg-purple-500/20',
    },
    {
        id: 'ecommerce',
        code: 'LaudaOne',
        title: 'Ecommerce / LaudaOne',
        domain: 'ecommerce.laudaapi.com',
        href: 'https://ecommerce.laudaapi.com',
        stage: 'Pedido web',
        role: 'B2B/B2C, catálogo público y órdenes web',
        summary: 'Recibe pedidos web y los manda al POS como source=ecommerce para que operación los procese.',
        receives: 'Clientes web, carritos, pedidos B2B/B2C y solicitudes digitales.',
        owns: 'Experiencia de compra online, catálogo visible, checkout y origen ecommerce.',
        sends: 'Pedido web pendiente hacia POS, sin cerrar factura ni afectar inventario.',
        never: 'No factura, no rebaja inventario, no crea CxC y no genera conduce directamente.',
        icon: ShoppingCart,
        position: { x: 540, y: 82 },
        badgeClass: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/20 dark:text-sky-300',
        iconClass: 'bg-sky-500/10 text-sky-500 ring-sky-500/25',
        glowClass: 'bg-sky-500/20',
    },
    {
        id: 'pos',
        code: 'POS',
        title: 'POS LaudaAPI',
        domain: 'pos.laudaapi.com',
        href: 'https://pos.laudaapi.com',
        stage: 'Núcleo operativo',
        role: 'Pedidos, facturas, inventario, CxC, caja y despacho',
        summary: 'Es la fuente de verdad operativa del ecosistema: todo lo comercial, web, fiscal y logístico termina pasando por POS.',
        receives: 'Oportunidades CRM, pedidos ecommerce, cobros, despacho, empaque y entregas.',
        owns: 'Ventas, facturas, conduces, inventario, crédito, caja, CxC, rutas, almacén y auditoría.',
        sends: 'Peticiones fiscales a e-CF, shipping orders a Delivery y metadata a Contabilidad.',
        never: 'No duplica Social, CRM, Delivery, e-CF ni Cumplimiento; los coordina desde la operación.',
        icon: Store,
        position: { x: 500, y: 260 },
        badgeClass: 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/25 dark:text-red-300',
        iconClass: 'bg-red-500/10 text-red-600 ring-red-500/30',
        glowClass: 'bg-red-500/20',
    },
    {
        id: 'delivery',
        code: 'Delivery',
        title: 'Delivery LaudaAPI',
        domain: 'delivery.laudaapi.com',
        href: 'https://delivery.laudaapi.com',
        stage: 'Última milla',
        role: 'Choferes, rutas, evidencia, tracking y cobros al entregar',
        summary: 'PWA/frontend para logística y choferes; opera entregas usando shipping orders y rutas del POS.',
        receives: 'Shipping orders, rutas, paquetes, asignaciones y datos de entrega desde POS.',
        owns: 'Tracking, evidencia, estado de entrega, choferes propios/externos y cobro al entregar.',
        sends: 'Eventos de entrega, evidencia, fallos, cobros y confirmaciones hacia POS.',
        never: 'No factura, no decide inventario, no crea CxC y no modifica la venta original.',
        icon: Truck,
        position: { x: 780, y: 145 },
        badgeClass: 'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-900/40 dark:bg-orange-950/20 dark:text-orange-300',
        iconClass: 'bg-orange-500/10 text-orange-500 ring-orange-500/25',
        glowClass: 'bg-orange-500/20',
    },
    {
        id: 'ecf',
        code: 'e-CF',
        title: 'LaudaAPI e-CF',
        domain: 'ecf.laudaapi.com',
        href: 'https://ecf.laudaapi.com',
        stage: 'Motor fiscal',
        role: 'XML, firma digital, DGII, TrackId y acuses',
        summary: 'Recibe la petición fiscal del POS, firma XML, envía a DGII y devuelve estados procesables.',
        receives: 'Factura, nota de crédito, nota de débito o documento fiscal desde POS.',
        owns: 'XML firmado, certificado, envío DGII, TrackId, consulta de estado y errores fiscales.',
        sends: 'TrackId, acuse, estado DGII, XML/PDF y errores hacia POS/Cumplimiento.',
        never: 'No decide precios, inventario, crédito, venta ni despacho.',
        icon: ReceiptText,
        position: { x: 780, y: 375 },
        badgeClass: 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/20 dark:text-blue-300',
        iconClass: 'bg-blue-500/10 text-blue-500 ring-blue-500/25',
        glowClass: 'bg-blue-500/20',
    },
    {
        id: 'cumplimiento',
        code: 'Fiscal',
        title: 'Cumplimiento LaudaAPI',
        domain: 'cumplimiento.laudaapi.com',
        href: 'https://cumplimiento.laudaapi.com',
        stage: 'Control fiscal',
        role: 'Obligaciones, alertas, documentos y reportes',
        summary: 'Organiza obligaciones, vencimientos, documentos fiscales y preparación de reportes como 606/607.',
        receives: 'Estados e-CF, XML/PDF, TrackId, vencimientos y metadata tributaria.',
        owns: 'Calendario fiscal, alertas, expedientes, documentos y preparación de reportes.',
        sends: 'Alertas, historial, reportes preparados y control documental.',
        never: 'No firma XML, no emite comprobantes y no registra asientos contables definitivos.',
        icon: CalendarCheck,
        position: { x: 595, y: 455 },
        badgeClass: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300',
        iconClass: 'bg-amber-500/10 text-amber-500 ring-amber-500/25',
        glowClass: 'bg-amber-500/20',
    },
    {
        id: 'bys',
        code: 'BYS',
        title: 'BYS LaudaAPI',
        domain: 'bys.laudaapi.com',
        href: 'https://bys.laudaapi.com',
        stage: 'Compras',
        role: 'Compras, suplidores, órdenes y metadata contable',
        summary: 'Gestiona compras y suplidores, dejando trazabilidad lista para contabilidad sin registrar el asiento final.',
        receives: 'Solicitudes de compra, suplidores, cotizaciones, órdenes y recepciones.',
        owns: 'Compras, suplidores, documentos de compra, auxiliares y metadata contable sugerida.',
        sends: 'Metadata validada hacia Contabilidad y señales operativas cuando aplique.',
        never: 'No registra el asiento definitivo y no reemplaza la operación de venta del POS.',
        icon: PackageCheck,
        position: { x: 330, y: 455 },
        badgeClass: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300',
        iconClass: 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/25',
        glowClass: 'bg-emerald-500/20',
    },
    {
        id: 'contabilidad',
        code: 'Conta',
        title: 'Contabilidad LaudaAPI',
        domain: 'contabilidad.laudaapi.com',
        href: 'https://contabilidad.laudaapi.com',
        stage: 'Registro final',
        role: 'Asientos, catálogo, auxiliares y conciliación',
        summary: 'Recibe metadata desde POS/BYS/Cumplimiento y registra el efecto contable definitivo.',
        receives: 'Ventas, cobros, compras, impuestos, auxiliares, dimensiones y metadata validada.',
        owns: 'Catálogo de cuentas, asientos definitivos, mayor, auxiliares y conciliación.',
        sends: 'Reportes contables, balances, trazabilidad y conciliación contra auxiliares.',
        never: 'No opera ventas, no despacha pedidos y no sustituye los módulos transaccionales.',
        icon: Database,
        position: { x: 115, y: 375 },
        badgeClass: 'border-slate-200 bg-slate-50 text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
        iconClass: 'bg-slate-500/10 text-slate-600 ring-slate-500/25 dark:text-slate-300',
        glowClass: 'bg-slate-500/20',
    },
    {
        id: 'status',
        code: 'Status',
        title: 'LaudaAPI Status',
        domain: 'status.laudaapi.com',
        href: 'https://status.laudaapi.com',
        stage: 'Observabilidad',
        role: 'Salud, disponibilidad, incidentes y endpoints',
        summary: 'Observa la disponibilidad de todos los ambientes y visibiliza incidentes sin modificar datos.',
        receives: 'Health checks, latencia, errores, endpoints, estado DGII/e-CF e incidentes.',
        owns: 'Disponibilidad, estado público, degradación, incidentes y salud operativa.',
        sends: 'Alertas, estado operativo, incidentes y visibilidad pública.',
        never: 'No opera, no factura, no cambia datos y no participa en la venta.',
        icon: Activity,
        position: { x: 910, y: 260 },
        badgeClass: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300',
        iconClass: 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/25',
        glowClass: 'bg-emerald-500/20',
    },
];

const relations: Relation[] = [
    {
        from: 'social',
        to: 'crm',
        short: 'lead',
        label: 'Social convierte conversación en lead y lo entrega al CRM.',
        flowIds: ['lead-to-cash'],
    },
    {
        from: 'crm',
        to: 'pos',
        short: 'oportunidad',
        label: 'CRM califica la oportunidad y solicita propuesta al POS.',
        flowIds: ['lead-to-cash'],
    },
    {
        from: 'ecommerce',
        to: 'pos',
        short: 'source=ecommerce',
        label: 'LaudaOne crea pedido web; POS decide operación, factura e inventario.',
        flowIds: ['ecommerce-to-delivery'],
    },
    {
        from: 'pos',
        to: 'delivery',
        short: 'shipping order',
        label: 'POS libera shipping order, ruta y paquetes hacia Delivery.',
        flowIds: ['lead-to-cash', 'ecommerce-to-delivery'],
    },
    {
        from: 'pos',
        to: 'ecf',
        short: 'petición fiscal',
        label: 'POS genera la factura y pide el documento fiscal a e-CF.',
        flowIds: ['lead-to-cash', 'fiscal-loop'],
    },
    {
        from: 'ecf',
        to: 'cumplimiento',
        short: 'XML / TrackId',
        label: 'e-CF devuelve XML, TrackId y estados para control fiscal.',
        flowIds: ['fiscal-loop'],
    },
    {
        from: 'pos',
        to: 'contabilidad',
        short: 'ventas / CxC',
        label: 'POS deja ventas, CxC, caja e impuestos listos para contabilizar.',
        flowIds: ['backoffice-loop'],
    },
    {
        from: 'bys',
        to: 'contabilidad',
        short: 'compras',
        label: 'BYS envía compras, suplidores, auxiliares y metadata contable.',
        flowIds: ['backoffice-loop'],
    },
    {
        from: 'cumplimiento',
        to: 'contabilidad',
        short: 'fiscal',
        label: 'Cumplimiento aporta soporte fiscal y documentos para conciliación.',
        flowIds: ['fiscal-loop', 'backoffice-loop'],
    },
];

const flows: {
    id: FlowId;
    eyebrow: string;
    title: string;
    description: string;
    steps: { projectId: ProjectId; text: string }[];
}[] = [
    {
        id: 'lead-to-cash',
        eyebrow: 'Comercial',
        title: 'Del lead social a la factura y entrega',
        description: 'Una conversación entra por Social, CRM la convierte en oportunidad y POS controla la venta, la factura y el despacho.',
        steps: [
            { projectId: 'social', text: 'Captura conversación y origen.' },
            { projectId: 'crm', text: 'Califica oportunidad y solicita propuesta.' },
            { projectId: 'pos', text: 'Crea pedido, factura, CxC e inventario.' },
            { projectId: 'ecf', text: 'Firma XML y confirma TrackId.' },
            { projectId: 'delivery', text: 'Entrega, evidencia y cobro al entregar.' },
        ],
    },
    {
        id: 'ecommerce-to-delivery',
        eyebrow: 'Web',
        title: 'Del pedido ecommerce a la operación real',
        description: 'LaudaOne no opera inventario directamente: envía el pedido al POS para liberar facturación, almacén, empaque y delivery.',
        steps: [
            { projectId: 'ecommerce', text: 'Cliente crea pedido B2B/B2C.' },
            { projectId: 'pos', text: 'Valida stock, crédito, caja y despacho.' },
            { projectId: 'delivery', text: 'Ejecuta ruta, tracking y evidencia.' },
        ],
    },
    {
        id: 'fiscal-loop',
        eyebrow: 'Fiscal',
        title: 'Del documento fiscal al cumplimiento',
        description: 'POS pide la factura electrónica, e-CF firma y consulta DGII, Cumplimiento organiza estados, XML y obligaciones.',
        steps: [
            { projectId: 'pos', text: 'Cierra venta y solicita e-CF.' },
            { projectId: 'ecf', text: 'Firma XML y consulta DGII.' },
            { projectId: 'cumplimiento', text: 'Clasifica, alerta y prepara reportes.' },
            { projectId: 'contabilidad', text: 'Concilia soporte fiscal contra asientos.' },
        ],
    },
    {
        id: 'backoffice-loop',
        eyebrow: 'Backoffice',
        title: 'De compras y operación al asiento contable',
        description: 'BYS y POS generan la operación; Contabilidad recibe metadata validada para registrar el efecto definitivo.',
        steps: [
            { projectId: 'bys', text: 'Gestiona compras y suplidores.' },
            { projectId: 'pos', text: 'Genera ventas, caja, CxC e inventario.' },
            { projectId: 'cumplimiento', text: 'Aporta soporte fiscal documental.' },
            { projectId: 'contabilidad', text: 'Registra asientos y concilia auxiliares.' },
        ],
    },
];

const nonStatusProjects = computed(() => projects.filter((project) => project.id !== 'status'));
const selectedProjectId = ref<ProjectId>('pos');
const activeFlowId = ref<FlowId>('lead-to-cash');

const selectedProject = computed(() => projectById(selectedProjectId.value));
const activeFlow = computed(() => flows.find((flow) => flow.id === activeFlowId.value)!);
const connectedRelations = computed(() =>
    relations.filter((relation) => relation.from === selectedProjectId.value || relation.to === selectedProjectId.value),
);

function projectById(id: ProjectId) {
    return projects.find((project) => project.id === id)!;
}

function x(id: ProjectId) {
    return projectById(id).position.x;
}

function y(id: ProjectId) {
    return projectById(id).position.y;
}

function isHighlightedRelation(relation: Relation) {
    return relation.flowIds.includes(activeFlowId.value) || relation.from === selectedProjectId.value || relation.to === selectedProjectId.value;
}

function midpoint(relation: Relation, axis: 'x' | 'y') {
    return axis === 'x' ? (x(relation.from) + x(relation.to)) / 2 : (y(relation.from) + y(relation.to)) / 2;
}

function selectProject(id: ProjectId) {
    selectedProjectId.value = id;
}
</script>

<template>
    <Head>
        <title>LaudaAPI — Ecosistema conectado para operar, facturar y cumplir</title>
        <meta
            name="description"
            content="LaudaAPI conecta Social, CRM, Ecommerce, POS, Delivery, e-CF, Cumplimiento, BYS, Contabilidad y Status en un ambiente API-first."
        />
        <meta property="og:title" content="LaudaAPI — Un ecosistema conectado para negocios en RD" />
        <meta
            property="og:description"
            content="Del lead y el pedido web a la operación POS, delivery, facturación electrónica, cumplimiento y contabilidad."
        />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>

    <div class="flex min-h-screen w-full flex-col items-center bg-[#FAFAF8] px-4 py-4 text-[#1b1b18] dark:bg-[#0a0a0a]">
        <PublicNav />

        <!-- HERO PRINCIPAL -->
        <main
            id="ecosistema"
            class="w-full max-w-7xl scroll-mt-20 overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-[#0c0c0c]"
        >
            <section class="relative overflow-hidden px-6 py-10 sm:px-10 lg:px-12 lg:py-14">
                <div class="absolute -top-24 right-0 h-72 w-72 rounded-full bg-red-500/10 blur-3xl" />
                <div class="absolute -bottom-32 left-10 h-72 w-72 rounded-full bg-slate-900/10 blur-3xl dark:bg-white/5" />

                <div class="relative grid gap-10 lg:grid-cols-[0.95fr_1.25fr] lg:items-center">
                    <div>
                        <div
                            class="mb-5 inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 dark:border-red-900/50 dark:bg-red-950/30"
                        >
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500" />
                            <span class="text-[9.5px] font-black tracking-widest text-red-700 uppercase dark:text-red-400">
                                Ambiente LaudaAPI · API-first · RD
                            </span>
                        </div>

                        <h1 class="max-w-3xl text-4xl leading-[0.95] font-black tracking-tight text-slate-950 sm:text-6xl dark:text-white">
                            Un solo ambiente para conectar
                            <span class="text-red-600">ventas, operación, fiscal y delivery.</span>
                        </h1>

                        <p class="mt-6 max-w-xl text-base leading-relaxed font-medium text-slate-500 dark:text-slate-400">
                            LaudaAPI no es una colección de apps aisladas. Es un ecosistema donde cada proyecto tiene
                            una responsabilidad clara y POS actúa como fuente de verdad operativa.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a
                                href="#mapa"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-6 py-3.5 text-sm font-black text-white shadow-xl transition hover:scale-[1.02] hover:bg-red-600 active:scale-95 dark:bg-white dark:text-slate-950"
                            >
                                Ver mapa conectado
                                <ArrowRight class="h-4 w-4" />
                            </a>
                            <a
                                href="mailto:contacto@laudaapi.com"
                                class="inline-flex items-center justify-center rounded-2xl border-2 border-slate-200 bg-white px-6 py-3.5 text-sm font-black text-slate-700 transition hover:scale-[1.02] hover:border-slate-950 hover:text-slate-950 active:scale-95 dark:border-slate-800 dark:bg-transparent dark:text-slate-300 dark:hover:border-white dark:hover:text-white"
                            >
                                Contactar
                            </a>
                        </div>
                    </div>

                    <div class="relative rounded-[2rem] border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-3xl bg-white p-5 shadow-sm dark:bg-slate-950">
                                <Network class="mb-4 h-6 w-6 text-red-500" />
                                <p class="text-2xl font-black text-slate-950 dark:text-white">10</p>
                                <p class="mt-1 text-[10px] font-black tracking-widest text-slate-400 uppercase">Ambientes</p>
                            </div>
                            <div class="rounded-3xl bg-slate-950 p-5 shadow-sm dark:bg-white">
                                <Workflow class="mb-4 h-6 w-6 text-red-400" />
                                <p class="text-2xl font-black text-white dark:text-slate-950">4</p>
                                <p class="mt-1 text-[10px] font-black tracking-widest text-slate-400 uppercase">Flujos clave</p>
                            </div>
                            <div class="rounded-3xl bg-white p-5 shadow-sm dark:bg-slate-950">
                                <ShieldCheck class="mb-4 h-6 w-6 text-red-500" />
                                <p class="text-2xl font-black text-slate-950 dark:text-white">1</p>
                                <p class="mt-1 text-[10px] font-black tracking-widest text-slate-400 uppercase">Fuente POS</p>
                            </div>
                        </div>

                        <div class="mt-3 rounded-3xl border border-dashed border-slate-300 bg-white/80 p-5 dark:border-slate-700 dark:bg-slate-950/60">
                            <p class="mb-3 text-[10px] font-black tracking-[0.28em] text-red-500 uppercase">Idea central</p>
                            <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                Social atrae, CRM convierte, Ecommerce recibe pedidos, POS opera, Delivery entrega,
                                e-CF responde a DGII, Cumplimiento organiza, BYS compra, Contabilidad registra y Status observa.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- ACCESOS -->
        <section id="ambientes" class="mt-6 w-full max-w-7xl scroll-mt-20">
            <LaudaEcosystemFlow />
        </section>

        <!-- FLUJOS -->
        <section id="flujos" class="mt-6 w-full max-w-7xl scroll-mt-20">
            <div class="rounded-[2.5rem] border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-[#0c0c0c] sm:p-10">
                <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-500 uppercase">Flujos de negocio</p>
                        <h2 class="text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl">
                            La página debe vender la interconectividad, no solo listar productos.
                        </h2>
                    </div>
                    <p class="max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        Al tocar un flujo, el mapa resalta las conexiones relacionadas y el usuario entiende cómo se mueve la información.
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-4">
                    <button
                        v-for="flow in flows"
                        :key="flow.id"
                        type="button"
                        :class="[
                            'rounded-3xl border p-5 text-left transition-all duration-200 hover:-translate-y-1 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400',
                            activeFlowId === flow.id
                                ? 'border-red-300 bg-red-50 shadow-md dark:border-red-900/60 dark:bg-red-950/20'
                                : 'border-slate-200 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-950/30',
                        ]"
                        @click="activeFlowId = flow.id"
                    >
                        <p class="mb-3 text-[10px] font-black tracking-[0.24em] text-red-500 uppercase">{{ flow.eyebrow }}</p>
                        <h3 class="mb-2 text-lg font-black leading-tight text-slate-950 dark:text-white">{{ flow.title }}</h3>
                        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ flow.description }}</p>
                    </button>
                </div>

                <div class="mt-6 rounded-[2rem] border border-slate-200 bg-slate-50/60 p-5 dark:border-slate-800 dark:bg-slate-950/30">
                    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[10px] font-black tracking-[0.24em] text-red-500 uppercase">{{ activeFlow.eyebrow }}</p>
                            <h3 class="text-2xl font-black text-slate-950 dark:text-white">{{ activeFlow.title }}</h3>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-black tracking-widest text-slate-400 uppercase dark:border-slate-800 dark:bg-slate-950">
                            {{ activeFlow.steps.length }} pasos
                        </span>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div
                            v-for="(step, index) in activeFlow.steps"
                            :key="step.projectId + step.text"
                            class="relative rounded-3xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <span :class="['flex h-10 w-10 items-center justify-center rounded-2xl ring-4', projectById(step.projectId).iconClass]">
                                    <component :is="projectById(step.projectId).icon" class="h-4 w-4" />
                                </span>
                                <span class="text-[10px] font-black text-slate-300">0{{ index + 1 }}</span>
                            </div>
                            <p class="mb-1 text-sm font-black text-slate-950 dark:text-white">{{ projectById(step.projectId).code }}</p>
                            <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ step.text }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- REGLAS DE RESPONSABILIDAD -->
        <section id="responsabilidades" class="mt-6 w-full max-w-7xl scroll-mt-20">
            <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-[2.5rem] border border-slate-200 bg-slate-950 p-8 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-red-400">
                        <Building2 class="h-6 w-6" />
                    </div>
                    <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-400 uppercase">Arquitectura limpia</p>
                    <h2 class="mb-4 text-3xl font-black tracking-tight text-white sm:text-4xl">
                        Cada ambiente tiene límites claros.
                    </h2>
                    <p class="text-sm leading-relaxed text-slate-400">
                        Esta sección es clave para la landing: explica que LaudaAPI crece por módulos, pero sin crear
                        caos. Ningún proyecto duplica la verdad operativa del POS ni invade la responsabilidad de otro.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-[2rem] border border-emerald-100 bg-emerald-50/60 p-6 dark:border-emerald-900/30 dark:bg-emerald-950/10">
                        <div class="mb-4 flex items-center gap-2">
                            <CheckCircle2 class="h-5 w-5 text-emerald-500" />
                            <h3 class="font-black text-slate-950 dark:text-white">Sí hace</h3>
                        </div>
                        <ul class="space-y-3 text-sm leading-relaxed text-slate-700 dark:text-slate-200">
                            <li>POS concentra operación, ventas, inventario, CxC, caja y despacho.</li>
                            <li>CRM empuja oportunidades, pero no opera la venta final.</li>
                            <li>Ecommerce genera pedidos web y conserva el source del canal.</li>
                            <li>Delivery ejecuta rutas y devuelve evidencia al POS.</li>
                            <li>e-CF firma, envía a DGII y devuelve TrackId/estado.</li>
                            <li>Contabilidad registra el efecto final con metadata validada.</li>
                        </ul>
                    </div>

                    <div class="rounded-[2rem] border border-red-100 bg-red-50/60 p-6 dark:border-red-900/30 dark:bg-red-950/10">
                        <div class="mb-4 flex items-center gap-2">
                            <XCircle class="h-5 w-5 text-red-500" />
                            <h3 class="font-black text-slate-950 dark:text-white">No duplica</h3>
                        </div>
                        <ul class="space-y-3 text-sm leading-relaxed text-slate-700 dark:text-slate-200">
                            <li>CRM no factura ni rebaja inventario.</li>
                            <li>Ecommerce no crea CxC ni conduce directamente.</li>
                            <li>Delivery no decide si una venta existe o no.</li>
                            <li>e-CF no decide precios, crédito ni inventario.</li>
                            <li>Cumplimiento no firma documentos ni registra asientos definitivos.</li>
                            <li>Status solo observa: no modifica datos.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA FINAL -->
        <section class="mt-6 w-full max-w-7xl">
            <div class="rounded-[2.5rem] border border-slate-200 bg-slate-950 p-8 shadow-xl dark:border-slate-800 dark:bg-slate-900 sm:p-12">
                <div class="flex flex-col items-center gap-6 text-center lg:flex-row lg:justify-between lg:text-left">
                    <div class="max-w-3xl">
                        <p class="mb-3 text-[10px] font-black tracking-[0.3em] text-red-400 uppercase">LaudaAPI como puerta principal</p>
                        <h2 class="mb-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            La landing ya no solo presenta proyectos: explica cómo trabajan juntos.
                        </h2>
                        <p class="text-sm leading-relaxed text-slate-400">
                            El usuario entiende el recorrido completo: de marketing y ventas a operación, fiscal,
                            delivery, compras, contabilidad y monitoreo.
                        </p>
                    </div>

                    <a
                        href="mailto:contacto@laudaapi.com"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-2xl bg-white px-8 py-4 text-base font-black text-slate-950 shadow-lg transition hover:scale-105 hover:bg-red-500 hover:text-white active:scale-95"
                    >
                        Contactar
                        <ArrowRight class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="mt-8 mb-4 w-full max-w-7xl">
            <div class="flex flex-col gap-5 border-t border-slate-200 pt-6 dark:border-slate-800">
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between sm:text-left">
                    <p class="text-[10px] font-black tracking-[0.4em] text-slate-400 uppercase dark:text-slate-600">
                        Ambiente Lauda Digital
                    </p>

                    <div class="flex flex-wrap items-center justify-center gap-5">
                        <Link href="/privacy" class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white">
                            Privacidad
                        </Link>
                        <Link href="/terms" class="text-xs font-bold text-slate-400 transition-colors hover:text-black dark:text-slate-500 dark:hover:text-white">
                            Términos
                        </Link>
                        <a
                            v-for="project in projects"
                            :key="project.id"
                            :href="project.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs font-bold text-slate-400 transition-colors hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400"
                        >
                            {{ project.code }}
                        </a>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
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

                    <p class="text-[10px] font-bold text-slate-400">© 2026 Lauda API · Hecho con ❤️ en RD</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    :deep(.animate-pulse) {
        animation: none !important;
    }
}
</style>

