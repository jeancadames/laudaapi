<!-- resources/js/Pages/Marketplace/Index.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import MarketingLayout from '@/layouts/MarketingLayout.vue'

import Hero from './Partials/Hero.vue'
import SolutionsSection from './Partials/SolutionsSection.vue'
import ComparisonSection from './Partials/ComparisonSection.vue'
import PricingCatalogSection from './Partials/PricingCatalogSection.vue'

import DetailFacturacionElectronica from './Partials/DetailFacturacionElectronica.vue'
import DetailApiMarketplace from './Partials/DetailApiMarketplace.vue'
import DetailApiPropios from './Partials/DetailApiPropios.vue'
import DetailTodoEnUno from './Partials/DetailTodoEnUno.vue'
import DetailErpModular from './Partials/DetailErpModular.vue'
import DetailLaudaOne from './Partials/DetailLaudaOne.vue'

import SocialCommerceSection from './Partials/SocialCommerceSection.vue'
import CtaLandingSection from './Partials/CtaLandingSection.vue'
import ContactForm from './Partials/ContactForm.vue'
import RequestForm from './Partials/RequestForm.vue'

/**
 * ✅ Tipos del catálogo (DB)
 */
type CatalogHighlight = { id: number; title: string; short_description?: string | null }
type CatalogCategory = {
    id: number
    title: string
    slug: string
    badge?: string | null
    icon?: string | null
    short_description?: string | null
    description?: string | null
    currency: string
    monthly_price: string | number | null
    yearly_price: string | number | null
    highlights?: CatalogHighlight[] | null
}

const props = withDefaults(
    defineProps<{
        canRegister: boolean
        catalog: CatalogCategory[]
    }>(),
    {
        canRegister: true,
        catalog: () => [],
    }
)

const showContact = ref(false)
const showRequestForm = ref(false)

/**
 * Scroll helpers
 */
function scrollToSection(target: string) {
    const el = document.getElementById(target)
    if (el) el.scrollIntoView({ behavior: 'smooth' })
}

function scrollTo(selector: string) {
    const el = document.querySelector(selector)
    if (el instanceof HTMLElement) {
        el.scrollIntoView({ behavior: 'smooth' })
    }
}

function openContact() {
    showContact.value = true
}

function openRequestForm() {
    showRequestForm.value = true
}

function submitContact() {
    showContact.value = false
}

function submitServiceRequest() {
    showRequestForm.value = false
}

/**
 * ✅ Solutions for carousel
 */
const solutions = [
    {
        title: 'API Marketplace',
        desc: 'Activa módulos empresariales listos: CRM, ventas, servicios, proyectos, transporte, RRHH, bancos y contabilidad.',
        badge: 'Módulos empresariales',
        target: 'detail-api-marketplace',
    },
    {
        title: 'API para sistemas propios',
        desc: 'Conector + wrapper XML para integrar tu sistema actual con la DGII sin romper tu core.',
        badge: 'Integración con tu sistema',
        target: 'detail-api-propios',
    },
    {
        title: 'Todo-en-Uno LaudaAPI',
        desc: 'Operación completa DGII: certificados, firma, colas, estatus, acuses, alertas, resguardo.',
        badge: 'Solución completa',
        target: 'detail-todo-en-uno',
    },
    {
        title: 'LaudaOne',
        desc: 'Ecommerce B2C y B2B listo para operar y escalar por fases.',
        badge: 'Plataforma',
        target: 'detail-laudaone',
    },
]

/**
 * ✅ Comparison section (3 opciones principales)
 */
const comparisonColumns = [
    {
        key: 'propios',
        title: 'API para sistemas propios',
        badge: 'Integración',
        ctaLabel: 'Ver integración',
        ctaTarget: 'detail-api-propios',
    },
    {
        key: 'todo',
        title: 'Todo-en-Uno LaudaAPI',
        badge: 'DGII end-to-end',
        highlight: true,
        ctaLabel: 'Ver Todo-en-Uno',
        ctaTarget: 'detail-todo-en-uno',
    },
    {
        key: 'market',
        title: 'LaudaAPI Hub Marketplace',
        badge: 'Módulos',
        ctaLabel: 'Ver Marketplace',
        ctaTarget: 'detail-api-marketplace',
    },
] as const

const comparisonRows = [
    {
        label: 'Ideal para',
        values: {
            propios: 'Ya tienes ERP / POS / sistema y solo necesitas DGII',
            todo: 'Quieres DGII completo listo y controlado (certificados, colas, acuses)',
            market: 'Quieres módulos listos por área y activarlos por fases',
        },
    },
    {
        label: 'Tiempo típico de implementación',
        values: {
            propios: 'Medio (depende de tu modelo actual)',
            todo: 'Rápido (ready-to-run)',
            market: 'Rápido por módulo (por fases)',
        },
    },
    {
        label: 'Nivel técnico requerido',
        values: {
            propios: 'Alto (mapeo + integración)',
            todo: 'Medio (configuración + operación)',
            market: 'Bajo/Medio (activación guiada)',
        },
    },
    {
        label: 'Qué entregamos',
        values: {
            propios: 'Conector + wrapper XML + reglas DGII + docs',
            todo: 'Plataforma DGII completa + monitoreo + resguardo',
            market: 'Catálogo de módulos + setup + guía + soporte por fase',
        },
    },
    {
        label: 'Escalabilidad',
        values: {
            propios: 'Escala con tu sistema',
            todo: 'Escala con colas y monitoreo',
            market: 'Escala agregando módulos cuando quieras',
        },
    },
] as const

/**
 * ✅ Facturación Electrónica (sección nueva)
 */
type Bullet = { title: string; desc: string }

const facturacionBullets: Bullet[] = [
    { title: 'Validación XML', desc: 'Esquema + reglas de negocio antes de enviar.' },
    { title: 'Firma digital', desc: 'Certificados, expiración y rotación controlada.' },
    { title: 'Envío y acuses', desc: 'Colas, reintentos, acuses y estatus.' },
    { title: 'Resguardo', desc: 'XML y acuses con trazabilidad y auditoría.' },
]

/**
 * ✅ DetailApiPropios
 */
const characteristics: Bullet[] = [
    {
        title: 'Conector a tu modelo',
        desc: 'Mapeamos tu estructura actual de facturas, clientes y productos a los requerimientos DGII.',
    },
    { title: 'Validación XML', desc: 'Validación de esquema, estructura y reglas de negocio antes de enviar a la DGII.' },
    { title: 'Firma digital', desc: 'Uso seguro de certificados, manejo de expiración y cambios controlados.' },
    { title: 'Envío y acuses', desc: 'Colas de envío, reintentos, estatus y acuses centralizados.' },
]

const deliverables = [
    'Conector adaptado a tu sistema',
    'Wrapper XML con reglas DGII',
    'Documentación técnica',
    'Guía de operación y soporte',
]

/**
 * ✅ DetailTodoEnUno
 */
const features: Bullet[] = [
    { title: 'Dashboard operativo', desc: 'Visibilidad de pendientes, errores, estatus y volúmenes en tiempo real.' },
    { title: 'Gestión de certificados', desc: 'Control de expiración, notificaciones y rotación segura.' },
    { title: 'Colas y reintentos', desc: 'Manejo de colas, reintentos e incidencias de forma controlada.' },
    { title: 'Resguardo normativo', desc: 'Almacenamiento de XML y acuses con la trazabilidad requerida.' },
]

/**
 * ✅ DetailApiMarketplace (nuevo detalle)
 */
const marketplaceFeatures: Bullet[] = [
    { title: 'Catálogo de módulos', desc: 'Activa servicios por área (CRM, ventas, proyectos, etc.) según tu operación.' },
    { title: 'Activación por fases', desc: 'Implementa sin fricción: prioriza un área y luego escala.' },
    { title: 'Integraciones', desc: 'Conecta con tus canales y procesos a medida que creces.' },
    { title: 'Gobernanza y soporte', desc: 'Control, trazabilidad y acompañamiento para cada fase.' },
]

const marketplaceIntegrations = [
    'CRM y pipeline comercial',
    'Ventas retail y mayoristas',
    'Órdenes de servicio y contratos',
    'Proyectos, hitos y tareas',
    'RRHH, bancos y contabilidad',
]

const marketplaceDeliverables = [
    'Selección de módulos por área',
    'Configuración inicial guiada',
    'Buenas prácticas y soporte',
    'Ruta de escalamiento por fases',
]

/**
 * ✅ ESTE era el error: ahora items es Module[]
 */
type Module = { title: string; desc: string; tags?: string[] }
type AreaGroup = { area: string; items: Module[] }

const marketplaceModulesByArea = [
    {
        area: 'Comercial',
        items: [
            { title: 'CRM', desc: 'Gestión de clientes, contactos y seguimiento.' },
            { title: 'Ventas Retail', desc: 'Ventas retail, POS y facturación rápida.', tags: [ 'Retail', 'POS' ] },
            { title: 'Ventas Mayoristas', desc: 'Ventas por volumen, listas y condiciones.', tags: [ 'Mayorista' ] },
        ],
    },
    {
        area: 'Operación',
        items: [
            { title: 'Servicios', desc: 'Gestión de servicios y órdenes de trabajo.', tags: [ 'Ordenes de Trabajo' ] },
            { title: 'Proyectos', desc: 'Planificación, costos y seguimiento.', tags: [ 'PM' ] },
            { title: 'Eventos', desc: 'Agenda, actividades y coordinación.', tags: [ 'Agenda' ] },
            {
                title: 'Transporte del Personal',
                desc: 'Rutas, asignaciones y control operativo para empresas que ofrecen este servicio.',
                tags: [ 'Rutas', 'Asignaciones' ],
            },
        ],
    },
    {
        area: 'Administración',
        items: [
            {
                title: 'Bienes y Servicios',
                desc: 'Registro de compras, gastos y bienes (requisiciones y control).',
                tags: [ 'Compras', 'Gastos' ],
            },
            { title: 'Recursos Humanos', desc: 'Nómina, asistencia y expedientes.', tags: [ 'Nóminas' ] },
            { title: 'Bancos', desc: 'Cuentas, conciliación y transacciones.', tags: [ 'Conciliación' ] },
            { title: 'Contabilidad', desc: 'Asientos, mayor, reportes y cierres.', tags: [ 'Libro Mayor' ] },
        ],
    },
    {
        area: 'Verticales',
        items: [
            { title: 'Foodshop', desc: 'Operación para comida & bebidas y delivery.' },
            { title: 'Kioskos', desc: 'Kioscos y autoservicio para puntos de venta.' },
            { title: 'Car Sales', desc: 'Ventas de vehículos y gestión de inventario.' },
            { title: 'Loans', desc: 'Préstamos, cuotas y cobranza.' },
            { title: 'Laudago', desc: 'Módulo complementario del ecosistema.' },
        ],
    },
] satisfies AreaGroup[]



/**
 * ✅ DetailErpModular (tu sección existente)
 */
const highlights = [
    'Definimos módulos por área de negocio',
    'Implementación por fases, sin fricción',
    'Escalamiento controlado a medida que creces',
]

const modules = [
    { title: 'Ventas', desc: 'Gestión de pedidos, facturas, cotizaciones y clientes.', tags: [ 'Retail', 'Wholesaler' ] },
    { title: 'Servicios', desc: 'Órdenes de trabajo, contratos, servicio recurrente.', tags: [ 'QuickServe', 'Field Service' ] },
    { title: 'Proyectos', desc: 'Hitos, tareas, presupuestos y control de avance.' },
    { title: 'RRHH', desc: 'Empleados, nómina, asistencia, vacaciones, permisos.' },
    { title: 'Bancos', desc: 'Conciliación, movimientos, cuentas, integración.' },
    { title: 'Contabilidad', desc: 'Catálogo, asientos, estados financieros.' },
]

/**
 * ✅ DetailLaudaOne (nuevo detalle)
 */
const laudaOnePillars = [
    { label: 'Ecommerce B2C' },
    { label: 'Ecommerce B2B' },
    { label: 'Catálogo + pedidos' },
    { label: 'Operación por fases' },
]

const laudaOneFeatures: Bullet[] = [
    { title: 'B2C/B2B', desc: 'Dos modos de venta según tu modelo de negocio.' },
    { title: 'Catálogo', desc: 'Productos, categorías, variantes y pricing.' },
    { title: 'Pedidos', desc: 'Flujo completo: orden → pago → despacho.' },
    { title: 'Operación', desc: 'Gestión operativa con módulos activables.' },
]
</script>

<template>
    <MarketingLayout>
        <Hero />

        <SolutionsSection :solutions="solutions" :scroll-to-section="scrollToSection" :open-contact="openContact" />

        <!-- ✅ Pricing desde catálogo (DB) -->
        <PricingCatalogSection :catalog="props.catalog" :scroll-to-section="scrollToSection" :open-request-form="openRequestForm" />

        <!-- ✅ Comparación -->
        <ComparisonSection title="Elige el camino correcto" subtitle="Compara las opciones y baja al detalle de la que te convenga." :columns="comparisonColumns as any" :rows="comparisonRows as any" :scroll-to-section="scrollToSection" :open-request-form="openRequestForm" />

        <!-- ✅ Detalles -->
        <DetailFacturacionElectronica :bullets="facturacionBullets" :open-request-form="openRequestForm" :open-contact="openContact" />

        <DetailApiMarketplace :features="marketplaceFeatures" :integrations="marketplaceIntegrations" :deliverables="marketplaceDeliverables" :modules-by-area="marketplaceModulesByArea" :open-request-form="openRequestForm" :open-contact="openContact" />

        <DetailApiPropios :characteristics="characteristics" :deliverables="deliverables" :open-request-form="openRequestForm" :scroll-to="scrollTo" :open-contact="openContact" />

        <DetailTodoEnUno :features="features" :open-request-form="openRequestForm" :scroll-to="scrollTo" :open-contact="openContact" />

        <DetailLaudaOne :pillars="laudaOnePillars" :features="laudaOneFeatures" :open-request-form="openRequestForm" :open-contact="openContact" />

        <!-- ✅ ERP modular -->
        <DetailErpModular :highlights="highlights" :modules="modules" :open-contact="openContact" :open-request-form="openRequestForm"/>

        <SocialCommerceSection :open-request-form="openRequestForm" id="detail-social-commerce" />

        <CtaLandingSection @open-contact="openContact" />

        <ContactForm :open="showContact" postUrl="/contact" @close="showContact = false" @submitted="submitContact" />

        <RequestForm :open="showRequestForm" post-url="/activation" @close="showRequestForm = false" @submitted="submitServiceRequest" />
    </MarketingLayout>
</template>
