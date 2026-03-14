<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'

import ErpLayout from '@/layouts/ErpLayout.vue'
import type { BreadcrumbItem } from '@/types'

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogTrigger,
} from '@/components/ui/dialog'

import XmlEcfWrapper from '@/components/XmlEcfWrapper.vue'
import AcecfXmlWrapper from '@/components/AcecfXmlWrapper.vue'
import RfceXmlWrapper from '@/components/RfceXmlWrapper.vue'

import axios from 'axios'

/* -----------------------------------------
   ✅ Props
------------------------------------------ */
type Cert = {
    id: number
    label: string | null
    type: 'p12' | 'pfx' | 'cer' | 'crt'
    is_default: boolean
    subject_cn: string | null
    subject_rnc?: string | null
    issuer_cn: string | null
    serial_number: string | null
    valid_from: string | null
    valid_to: string | null
    has_private_key: boolean
    password_ok: boolean
    status: string
    original_name: string | null
    file_size: number | null
    meta?: Record<string, any> | null
}

type EndpointCatalogRow = {
    key: string
    name?: string | null
    base_url?: string | null
    path?: string | null
    method?: string | null
    environment?: 'precert' | 'cert' | 'prod'
    is_default?: boolean
    is_active?: boolean
    is_templated?: boolean
    meta?: any
}

type DgiiEcftype = `E${number}`

type XmlFileItem = {
    name: string
    type: DgiiEcftype | null
    size_bytes?: number | null
    last_modified_at: string
    signed?: boolean
    sent?: boolean
    response_name?: string | null
}

type XmlFilesBucket = {
    kind: 'ecf' | 'rfce' | 'acecf'
    base_dir: string
    count: number
    items: XmlFileItem[]
}

type XmlFilesPayload = {
    ecf: XmlFilesBucket
    rfce: XmlFilesBucket
    acecf: XmlFilesBucket
}

type WsActivityItem = {
    ts: string
    level: 'info' | 'warning' | 'error'
    event: string
    cid?: string | null
    host?: string | null
    path?: string | null
    method?: string | null
    ip?: string | null
    status?: number | null
    duration_ms?: number | null
    in_path?: string | null
    out_path?: string | null
    dgii_resp_path?: string | null
    [ k: string ]: any
}

const props = defineProps<{
    company: {
        id: number
        name: string | null
        rnc: string | null
        slug?: string | null
        ws_subdomain?: string | null
    }
    setting: {
        environment: 'precert' | 'cert' | 'prod'
        cf_prefix: string
        use_directory: boolean
        endpoints: Record<string, any>
    }
    certs?: Cert[]
    certs_summary?: { count: number; default_cert_id: number | null; has_default: boolean }
    endpoint_catalog?: EndpointCatalogRow[]
    xml_files?: XmlFilesPayload
    ws_activity?: WsActivityItem[]
}>()

/* -----------------------------------------
   ✅ Helpers
------------------------------------------ */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null
}

async function copyText(text?: string | null) {
    const v = (text ?? '').toString()
    if (!v) return
    try {
        await navigator.clipboard.writeText(v)
    } catch {
        window.prompt('Copia este texto:', v)
    }
}

const signing = ref<Record<string, boolean>>({})
const sending = ref<Record<string, boolean>>({})

const key = (kind: 'ecf' | 'rfce' | 'acecf', name: string) => `${kind}:${name}`

async function signXml(kind: 'ecf' | 'rfce' | 'acecf', name: string) {
    const k = key(kind, name)
    if (signing.value[ k ]) return

    signing.value[ k ] = true
    try {
        const token = getCsrfToken()

        const res = await axios.post(
            '/erp/services/certificacion-emisor/xml/sign',
            { kind, name },
            { headers: { ...(token ? { 'X-CSRF-TOKEN': token } : {}) } }
        )

        if (!res.data?.ok) throw new Error(res.data?.message ?? 'No se pudo firmar.')

        router.reload({ only: [ 'xml_files' ] })
    } catch (e: any) {
        const msg = e?.response?.data?.message || e?.message || 'Error firmando.'
        console.error(e)
        alert(msg)
    } finally {
        signing.value[ k ] = false
    }
}

async function sendXml(kind: 'ecf' | 'rfce' | 'acecf', name: string) {
    const k = key(kind, name)
    if (sending.value[ k ]) return

    sending.value[ k ] = true
    try {
        const token = getCsrfToken()

        const res = await axios.post(
            '/erp/services/certificacion-emisor/xml/send',
            { kind, name },
            { headers: { ...(token ? { 'X-CSRF-TOKEN': token } : {}) } }
        )

        if (!res.data?.ok) throw new Error(res.data?.message ?? 'No se pudo enviar.')

        router.reload({ only: [ 'xml_files' ] })
    } catch (e: any) {
        const msg = e?.response?.data?.message || e?.message || 'Error enviando.'
        console.error(e)
        alert(msg)
    } finally {
        sending.value[ k ] = false
    }
}

/* -----------------------------------------
   ✅ Breadcrumbs / Tabs
------------------------------------------ */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LaudaERP', href: '/erp' },
    { title: 'Servicios', href: '/erp' },
    { title: 'Certificación Emisor', href: '/erp/services/certificacion-emisor' },
]

const tab = ref<'guia' | 'certificados' | 'endpoints' | 'sets-ecf' | 'servicios-web'>('guia')
const wrapper_tabs = ref< 'ecf-wrapper' | 'rfce-wrapper' | 'acecf-wrapper'>('ecf-wrapper')

/* -----------------------------------------
   ✅ XML payload helper
------------------------------------------ */
const emptyBucket = (kind: XmlFilesBucket[ 'kind' ]): XmlFilesBucket => ({
    kind,
    base_dir: '',
    count: 0,
    items: [],
})

const xml_files = computed<XmlFilesPayload>(() => {
    return (
        props.xml_files ?? {
            ecf: emptyBucket('ecf'),
            rfce: emptyBucket('rfce'),
            acecf: emptyBucket('acecf'),
        }
    )
})

/* -----------------------------------------
   ✅ Certificados
------------------------------------------ */
const certs = computed<Cert[]>(() => (props.certs ?? []) as Cert[])
const hasCerts = computed(() => certs.value.length > 0)

const defaultCert = computed(() => {
    const byFlag = certs.value.find((c) => c.is_default)
    if (byFlag) return byFlag
    const id = props.certs_summary?.default_cert_id ?? null
    return id ? certs.value.find((c) => c.id === id) ?? null : null
})

const certsTop = computed(() => certs.value.slice(0, 5))
const certsCount = computed(() => props.certs_summary?.count ?? certs.value.length)
const hasDefault = computed(() => props.certs_summary?.has_default ?? !!defaultCert.value)

function statusBadgeVariant(status: string) {
    const s = (status || '').toLowerCase()
    if (s === 'active') return 'secondary'
    if (s === 'expired') return 'destructive'
    if (s === 'invalid') return 'destructive'
    return 'secondary'
}
function yesNoVariant(ok: boolean) {
    return ok ? 'secondary' : 'destructive'
}
function formatIso(iso?: string | null) {
    if (!iso) return '—'
    const m = String(iso).match(/^(\d{4}-\d{2}-\d{2})/)
    return m ? m[ 1 ] : iso
}
function formatBytes(bytes?: number | null) {
    if (!bytes || bytes <= 0) return '—'
    const units = [ 'B', 'KB', 'MB', 'GB' ]
    let i = 0
    let v = bytes
    while (v >= 1024 && i < units.length - 1) {
        v = v / 1024
        i++
    }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[ i ]}`
}

function daysLeft(validToIso?: string | null) {
    if (!validToIso) return null
    const end = new Date(validToIso).getTime()
    if (Number.isNaN(end)) return null
    const diffMs = end - Date.now()
    return Math.ceil(diffMs / (1000 * 60 * 60 * 24))
}
function vigenciaBadgeClass(validToIso?: string | null, status?: string | null) {
    const s = (status ?? '').toLowerCase()
    const d = daysLeft(validToIso)

    if (s === 'expired') return 'bg-red-600 text-white hover:bg-red-600'
    if (s === 'invalid') return 'bg-red-600 text-white hover:bg-red-600'

    if (d === null) return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'
    if (d <= 0) return 'bg-red-600 text-white hover:bg-red-600'
    if (d <= 30) return 'bg-red-600 text-white hover:bg-red-600'
    if (d <= 60) return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return 'bg-emerald-600 text-white hover:bg-emerald-600'
}
function vigenciaLabel(validToIso?: string | null, status?: string | null) {
    const s = (status ?? '').toLowerCase()
    const d = daysLeft(validToIso)

    if (s === 'expired') return 'VENCIDO'
    if (s === 'invalid') return 'INVALID'
    if (d === null) return 'Sin fecha'
    if (d <= 0) return 'VENCIDO'
    return `${d} días`
}

function certDisplayName(c: Cert) {
    return c.label || c.original_name || `Cert #${c.id}`
}

const certWarnings = computed(() => {
    const warnings: string[] = []

    if (!hasDefault.value) {
        warnings.push('No tienes certificado predeterminado (default).')
    }

    if (!hasCerts.value) {
        warnings.push('No tienes certificados cargados.')
        return warnings
    }

    if (certs.value.some((c) => (c.status || '').toLowerCase() === 'expired')) {
        warnings.push('Hay certificados vencidos.')
    }

    if (certs.value.some((c) => (c.status || '').toLowerCase() === 'invalid')) {
        warnings.push('Hay certificados inválidos (parse falló).')
    }

    if (certs.value.some((c) => c.type !== 'cer' && !c.password_ok)) {
        warnings.push('Hay P12/PFX con password incorrecto o faltante.')
    }

    return warnings
})

/* -----------------------------------------
   ✅ Guía
------------------------------------------ */
const guia = {
    meta: {
        title: 'Guía Básica para ser Emisor Electrónico',
        sourceLabel: 'DGII (PDF)',
        sourceUrl:
            'https://dgii.gov.do/publicacionesOficiales/bibliotecaVirtual/contribuyentes/facturacion/Documents/Facturaci%C3%B3n%20Electr%C3%B3nica/Guia-Basica-para-ser-Emisor-Electronico.pdf',
        published: 'Enero 2025',
    },
    definicion: 'Es todo aquel contribuyente autorizado por Impuestos Internos para emitir Comprobantes Fiscales Electrónicos (e-CF).',
    requisitos: [
        'Estar inscrito en el Registro Nacional de Contribuyentes (RNC).',
        'Estar registrado como contribuyente con obligaciones tributarias a su cargo.',
        'Poseer clave de acceso a la Oficina Virtual (OFV).',
        'Completar el Formulario de Solicitud de Autorización (FI-GDF-016, Vers. C).',
        'Contar con Certificado Digital para Procesos Tributarios (prestadora autorizada por INDOTEL) a nombre del representante.',
        'Cumplir exigencias técnicas y aprobar satisfactoriamente el proceso de certificación.',
        'Cumplir Norma General 06-2018 sobre Comprobantes Fiscales.',
    ],
    etapas: [
        {
            key: 'solicitud',
            title: '1) Solicitud',
            bullets: [
                'Completar y enviar el “Formulario de Solicitud”.',
                'DGII valida requisitos y responde por el buzón OFV con enlace del portal de Facturación Electrónica + usuario y clave.',
            ],
        },
        {
            key: 'sets',
            title: '2) Sets de Pruebas',
            bullets: [
                'Comprobar que el software puede emitir/recibir e-CF, Acuse de Recibo y Aprobación Comercial.',
                'Validar que puede generar Representación Impresa (RI) conforme normativa.',
                'Pruebas en el portal de Certificación; al completarlas, procede la Declaración Jurada.',
            ],
            sets: [ 'Pruebas de Datos', 'Pruebas de Simulación', 'Pruebas de Comunicación' ],
        },
        {
            key: 'dj',
            title: '3) Declaración Jurada',
            bullets: [
                'Formulario electrónico con responsabilidad legal bajo fe de juramento.',
                'Declara que las pruebas fueron realizadas íntegramente, sin fraude ni irregularidades.',
                'Luego pasa a la etapa de Certificación.',
            ],
        },
        {
            key: 'certificacion',
            title: '4) Certificación',
            bullets: [
                'Obtención de la Autorización para ser Emisor Electrónico (para emitir e-CF).',
                'Se habilita el menú de Facturación Electrónica en OFV para solicitar e-NCF e iniciar emisión.',
            ],
        },
    ],
    obligaciones: [
        'Firmar digitalmente los e-CF emitidos usando Certificado Digital vigente.',
        'Emitir la Representación Impresa (RI) del e-CF al receptor no electrónico.',
        'Recibir e-CF de proveedores que sean emitidos válidamente.',
        'Exhibir a la DGII informaciones digitales o físicas requeridas (Código Tributario).',
        'Conservar los e-CF conforme Código Tributario.',
    ],
    contingencias: [
        { title: 'Falta de conectividad', desc: 'Generar e-CF offline y enviar en un plazo no mayor a 72 horas.' },
        {
            title: 'Cuando no sea posible la emisión del e-CF',
            desc: 'Usar secuencias autorizadas de comprobantes no electrónicos y enviar e-CF reemplazantes en un plazo no mayor a 30 días; la contingencia no puede exceder 15 días.',
        },
        { title: 'Mecanismos DGII no disponibles', desc: 'Almacenar los e-CF y enviarlos cuando se restablezca la comunicación con la DGII.' },
    ],
    facturadorGratuito: {
        title: 'Facturador Gratuito',
        desc: 'Herramienta digital para emitir facturas electrónicas (facturas, notas de crédito y débito) cumpliendo normativa.',
        requisitos: [
            'Inscrito en RNC.',
            'Clave OFV y dispositivo de seguridad (token/tarjeta/token digital u otro).',
            'Autorización para emitir NCF.',
            'Estar al día en obligaciones tributarias y deberes formales.',
            'Certificado Digital para procedimiento tributario (INDOTEL).',
            'Computador o móvil con internet.',
            'No haber sido autorizado a emitir e-CF con otro sistema distinto al Facturador Gratuito.',
            'Facturar máximo 150 facturas al mes.',
            'Completar FI-GDF-018 (Solicitud uso Facturador Gratuito, Vers. A).',
        ],
        beneficios: [
            'Ahorro de costos (evita software comercial).',
            'Cumplimiento legal.',
            'Acceso rápido (emisión y envío ágil).',
            'Seguridad e integridad de la información.',
        ],
    },
}

/* -----------------------------------------
   ✅ Endpoints helpers (preview)
------------------------------------------ */
function envLabel(env: 'precert' | 'cert' | 'prod') {
    if (env === 'precert') return 'precert (DGII pruebas)'
    if (env === 'cert') return 'cert (DGII certificación)'
    return 'prod (DGII producción)'
}

const settingEndpoints = computed<Record<string, any>>(() => props.setting?.endpoints ?? {})
const hasSettingEndpoints = computed(() => Object.keys(settingEndpoints.value || {}).length > 0)

function endpointsFromCatalog(env: 'precert' | 'cert' | 'prod'): Record<string, any> {
    const rows = (props.endpoint_catalog ?? []).filter((r) => (r.environment ?? env) === env && (r.is_active ?? true))
    if (!rows.length) return {}

    const out: Record<string, any> = {}

    const seed = rows.find((r) => r.key === 'auth.seed')
    const anyBase = seed?.base_url || rows.find((r) => r.base_url)?.base_url || null
    if (anyBase) out.UrlDGII = anyBase

    const map: Record<string, string> = {
        'auth.seed': 'UrlGetSeed',
        'auth.validate_seed': 'UrlTestSeed',
        'consulta.resultado': 'UrlConsultaResultado',
        'consulta.directorio': 'UrlDirectorioServicios',
        'status.obtener': 'UrlStatusServicios',
    }

    for (const r of rows) {
        const k = map[ r.key ]
        if (!k) continue
        if (r.path) out[ k ] = r.path
    }

    return out
}

const effectiveEndpointsForPreview = computed<Record<string, any>>(() => {
    if (hasSettingEndpoints.value) return settingEndpoints.value
    const fallback = endpointsFromCatalog(props.setting.environment)
    return Object.keys(fallback).length ? fallback : {}
})

const baseHost = computed(() => {
    const host = (effectiveEndpointsForPreview.value?.UrlDGII ?? '').toString().trim()
    return host ? host : '—'
})

const cfPrefix = computed(() => {
    const v = (effectiveEndpointsForPreview.value?.CfPrefix ?? effectiveEndpointsForPreview.value?.cf_prefix ?? '').toString().trim()
    if (v) return v

    const s = (props.setting as any)?.cf_prefix
    if (s) return String(s).trim()

    if (props.setting.environment === 'precert') return 'testecf'
    if (props.setting.environment === 'cert') return 'certecf'
    return 'ecf'
})

function buildUrl(path?: string | null) {
    const p = (path ?? '').toString()
    if (!p) return '—'
    const host = baseHost.value
    if (!host || host === '—') return p

    const normalized = p
        .replace('/certecf/', '/{cf}/')
        .replace('/testecf/', '/{cf}/')
        .replace('/testcf/', '/{cf}/')
        .replace('/ecf/', '/{cf}/')
        .replace('/eCF/', '/{cf}/')

    const finalPath = normalized.replace('{cf}', cfPrefix.value)

    if (/^https?:\/\//i.test(finalPath)) return finalPath

    const cleanHost = host.replace(/^https?:\/\//i, '')
    const cleanPath = finalPath.startsWith('/') ? finalPath : `/${finalPath}`
    return `https://${cleanHost}${cleanPath}`
}

const seedUrl = computed(() => {
    const v = effectiveEndpointsForPreview.value?.UrlGetSeed
    return v ? buildUrl(v) : '—'
})
const testSeedUrl = computed(() => {
    const v = effectiveEndpointsForPreview.value?.UrlTestSeed
    return v ? buildUrl(v) : '—'
})

const endpointEntries = computed(() => {
    const ep = effectiveEndpointsForPreview.value ?? {}
    return Object.entries(ep)
        .filter(([ k, v ]) => typeof v === 'string' && v && k.startsWith('Url'))
        .map(([ k, v ]) => ({ key: k, path: String(v), preview: buildUrl(String(v)) }))
        .sort((a, b) => a.key.localeCompare(b.key))
})

/* -----------------------------------------
   ✅ Endpoints Editor
------------------------------------------ */
const openEndpointsEditor = ref(false)
const endpointsJsonError = ref<string | null>(null)

const endpointsForm = useForm<{
    environment: 'precert' | 'cert' | 'prod'
    use_directory: boolean
    endpoints_json: string
}>({
    environment: props.setting.environment,
    use_directory: !!props.setting.use_directory,
    endpoints_json: JSON.stringify(props.setting.endpoints ?? {}, null, 2),
})

function resetEndpointsEditor() {
    endpointsJsonError.value = null
    endpointsForm.clearErrors()

    endpointsForm.environment = props.setting.environment
    endpointsForm.use_directory = !!props.setting.use_directory

    const ep = hasSettingEndpoints.value ? (props.setting.endpoints ?? {}) : endpointsFromCatalog(props.setting.environment)
    endpointsForm.endpoints_json = JSON.stringify(ep ?? {}, null, 2)
}

watch(openEndpointsEditor, (isOpen) => {
    if (isOpen) resetEndpointsEditor()
})

watch(
    () => props.setting,
    () => {
        if (openEndpointsEditor.value) resetEndpointsEditor()
    },
    { deep: true }
)

watch(
    () => endpointsForm.environment,
    (env) => {
        if (!hasSettingEndpoints.value && openEndpointsEditor.value) {
            const ep = endpointsFromCatalog(env)
            endpointsForm.endpoints_json = JSON.stringify(ep ?? {}, null, 2)
        }
    }
)

function submitEndpoints() {
    endpointsJsonError.value = null

    let parsed: any = null
    try {
        parsed = endpointsForm.endpoints_json?.trim() ? JSON.parse(endpointsForm.endpoints_json) : {}
    } catch {
        endpointsJsonError.value = 'El JSON de endpoints no es válido.'
        return
    }

    endpointsForm.transform(() => ({
        environment: endpointsForm.environment,
        use_directory: endpointsForm.use_directory,
        endpoints: parsed,
    }))

    endpointsForm.post('/erp/services/certificacion-emisor/endpoints', {
        preserveScroll: true,
        onSuccess: () => {
            openEndpointsEditor.value = false

            router.visit(window.location.href, {
                only: [ 'setting', 'endpoint_catalog' ],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            })
        },
    })
}

/* -----------------------------------------
   ✅ Servicios Web (DGII WS) helpers
------------------------------------------ */
const appBaseDomain = computed(() => {
    return ((import.meta as any)?.env?.VITE_APP_BASE_DOMAIN || 'laudaapi.com').toString().trim()
})

const wsTenant = computed(() => {
    const c: any = props.company || {}
    return (c.ws_subdomain || c.slug || 'demo').toString().trim()
})

const wsUsingFallback = computed(() => {
    const c: any = props.company || {}
    return !c.ws_subdomain && !!c.slug
})

const wsOrigin = computed(() => `https://${wsTenant.value}.${appBaseDomain.value}`)

const wsUrls = computed(() => ({
    semilla: `${wsOrigin.value}/fe/autenticacion/api/semilla`,
    validacion: `${wsOrigin.value}/fe/autenticacion/api/validacioncertificado`,
    recepcion: `${wsOrigin.value}/fe/recepcion/api/ecf`,
    aprobacion: `${wsOrigin.value}/fe/aprobacioncomercial/api/ecf`,
}))

/* -----------------------------------------
   ✅ WS Activity (UI)
------------------------------------------ */
const wsActivity = ref<WsActivityItem[]>(props.ws_activity ?? [])
const wsLoading = ref(false)
const wsError = ref<string | null>(null)

const wsLevel = ref<'all' | 'info' | 'warning' | 'error'>('all')
const wsSearch = ref('')
const wsAutoRefresh = ref(true)

let wsTimer: ReturnType<typeof setInterval> | null = null

function levelBadge(item: WsActivityItem) {
    if (item.level === 'error') return { variant: 'destructive' as const, cls: '' }
    if (item.level === 'warning') return { variant: 'secondary' as const, cls: 'bg-yellow-400 text-black hover:bg-yellow-400' }
    return { variant: 'secondary' as const, cls: '' }
}

function shortCid(cid?: string | null) {
    const v = (cid ?? '').toString()
    if (!v) return '—'
    return v.length > 12 ? `${v.slice(0, 8)}…${v.slice(-4)}` : v
}

async function fetchWsActivity() {
    wsError.value = null
    wsLoading.value = true

    try {
        const res = await axios.get('/erp/services/certificacion-emisor/ws/activity', {
            params: {
                level: wsLevel.value,
                search: wsSearch.value,
                limit: 250,
            },
        })

        wsActivity.value = Array.isArray(res.data?.items) ? res.data.items : []
    } catch (e: any) {
        wsError.value = e?.response?.data?.message || e?.message || 'Error cargando actividad.'
    } finally {
        wsLoading.value = false
    }
}

function startWsTimer() {
    stopWsTimer()

    wsTimer = setInterval(() => {
        if (tab.value === 'servicios-web' && wsAutoRefresh.value) {
            fetchWsActivity()
        }
    }, 15000)
}

function stopWsTimer() {
    if (wsTimer) clearInterval(wsTimer)
    wsTimer = null
}

const wsFiltered = computed(() => wsActivity.value.slice(0, 250))

const wsStats = computed(() => {
    const items = wsActivity.value ?? []
    const errors = items.filter((x) => x.level === 'error').length
    const warnings = items.filter((x) => x.level === 'warning').length
    const last = items[ 0 ]?.ts ?? null
    return { total: items.length, errors, warnings, last }
})

const lastByEvent = computed(() => {
    const out: Record<string, string> = {}
    for (const it of wsActivity.value ?? []) {
        if (!out[ it.event ]) out[ it.event ] = it.ts
    }
    return out
})

function lastEventTs(eventPrefix: string) {
    const map = lastByEvent.value
    const keys = Object.keys(map)
    const found = keys.find((k) => k.startsWith(eventPrefix))
    return found ? map[ found ] : null
}

watch(tab, (t) => {
    if (t === 'servicios-web') {
        fetchWsActivity()
        if (wsAutoRefresh.value) startWsTimer()
    } else {
        stopWsTimer()
    }
})

watch(wsAutoRefresh, (v) => {
    if (tab.value !== 'servicios-web') return
    if (v) startWsTimer()
    else stopWsTimer()
})

watch([ wsLevel, wsSearch ], () => {
    if (tab.value === 'servicios-web') {
        fetchWsActivity()
    }
})

onMounted(() => {
    if (tab.value === 'servicios-web') {
        fetchWsActivity()
        if (wsAutoRefresh.value) startWsTimer()
    }
})

onBeforeUnmount(() => stopWsTimer())
</script>

<template>

    <Head title="Certificación Emisor (DGII e-CF)" />

    <ErpLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <header class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold">Certificación Emisor (DGII e-CF)</h1>
                        <p class="text-sm text-muted-foreground">
                            Empresa: <span class="font-medium">{{ company.name ?? '—' }}</span>
                            <span class="mx-2">•</span>
                            RNC: <span class="font-medium">{{ company.rnc ?? '—' }}</span>
                        </p>
                    </div>

                    <Badge variant="secondary" class="capitalize">Ambiente: {{ setting.environment }}</Badge>
                </div>
            </header>

            <Tabs v-model="tab" class="w-full">
                <TabsList class="grid w-full grid-cols-5">
                    <TabsTrigger value="guia">Guía DGII</TabsTrigger>
                    <TabsTrigger value="certificados">Certificados</TabsTrigger>
                    <TabsTrigger value="endpoints">Endpoints</TabsTrigger>
                    <TabsTrigger value="sets-ecf">Set e-CF</TabsTrigger>
                    <TabsTrigger value="servicios-web">Servicios Web</TabsTrigger>
                </TabsList>

                <TabsContent value="guia" class="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Guía DGII (integrada)</CardTitle>
                            <CardDescription>Requisitos, etapas, obligaciones y contingencias.</CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-6">
                            <div class="flex flex-col gap-1 rounded-lg border p-4">
                                <div class="text-sm font-medium">{{ guia.meta.title }}</div>
                                <div class="text-xs text-muted-foreground">
                                    Fuente: {{ guia.meta.sourceLabel }} • {{ guia.meta.published }}
                                    <span class="mx-2">•</span>
                                    <a :href="guia.meta.sourceUrl" target="_blank" class="underline underline-offset-4">Abrir PDF</a>
                                </div>
                                <p class="mt-2 text-sm text-muted-foreground">{{ guia.definicion }}</p>
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-semibold">Requisitos (antes de iniciar)</div>
                                <ul class="list-disc space-y-2 pl-5 text-sm">
                                    <li v-for="(r, i) in guia.requisitos" :key="i">{{ r }}</li>
                                </ul>
                            </div>

                            <div class="space-y-3">
                                <div class="text-sm font-semibold">Etapas para ser Emisor Electrónico</div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div v-for="step in guia.etapas" :key="step.key" class="rounded-lg border p-4">
                                        <div class="mb-2 font-medium">{{ step.title }}</div>

                                        <ul class="list-disc space-y-2 pl-5 text-sm">
                                            <li v-for="(b, i) in step.bullets" :key="i">{{ b }}</li>
                                        </ul>

                                        <div v-if="(step as any).sets?.length" class="mt-3">
                                            <div class="text-xs font-semibold text-muted-foreground">Sets establecidos</div>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <Badge v-for="s in (step as any).sets" :key="s" variant="secondary">{{ s }}</Badge>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-semibold">Obligaciones del Emisor Electrónico</div>
                                <ul class="list-disc space-y-2 pl-5 text-sm">
                                    <li v-for="(o, i) in guia.obligaciones" :key="i">{{ o }}</li>
                                </ul>
                            </div>

                            <div class="space-y-2">
                                <div class="text-sm font-semibold">Contingencias</div>
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div v-for="(c, i) in guia.contingencias" :key="i" class="rounded-lg border p-4">
                                        <div class="font-medium">{{ c.title }}</div>
                                        <p class="mt-2 text-sm text-muted-foreground">{{ c.desc }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border p-4 space-y-4">
                                <div>
                                    <div class="text-sm font-semibold">{{ guia.facturadorGratuito.title }}</div>
                                    <p class="mt-1 text-sm text-muted-foreground">{{ guia.facturadorGratuito.desc }}</p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <div class="text-sm font-medium">Requisitos</div>
                                        <ul class="mt-2 list-disc space-y-2 pl-5 text-sm">
                                            <li v-for="(r, i) in guia.facturadorGratuito.requisitos" :key="i">{{ r }}</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium">Beneficios</div>
                                        <ul class="mt-2 list-disc space-y-2 pl-5 text-sm">
                                            <li v-for="(b, i) in guia.facturadorGratuito.beneficios" :key="i">{{ b }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="certificados" class="mt-4">
                    <Card>
                        <CardHeader>
                            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <CardTitle>Certificados</CardTitle>
                                    <CardDescription>Vista rápida del estado (default, password, llave privada y vigencia).</CardDescription>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Badge variant="secondary">{{ certsCount }} certificados</Badge>
                                    <Badge :variant="hasDefault ? 'secondary' : 'destructive'">
                                        {{ hasDefault ? 'Default OK' : 'Sin default' }}
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="space-y-4">
                            <div v-if="certWarnings.length" class="rounded-lg border border-yellow-500/30 bg-yellow-500/5 p-4">
                                <div class="text-sm font-medium">Atención</div>
                                <ul class="mt-2 list-disc pl-5 text-sm text-muted-foreground">
                                    <li v-for="(w, i) in certWarnings" :key="i">{{ w }}</li>
                                </ul>
                            </div>

                            <div v-if="!hasCerts" class="rounded-lg border p-4 text-sm text-muted-foreground">
                                No hay certificados cargados todavía.
                                <div class="mt-3">
                                    <Link href="/erp/services/certificacion-emisor/certificados" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:opacity-90">
                                        Administrar certificados
                                    </Link>
                                </div>
                            </div>

                            <div v-else class="rounded-lg border overflow-hidden">
                                <div class="border-b p-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-medium">
                                            Default:
                                            <span class="ml-1">{{ defaultCert ? certDisplayName(defaultCert) : '—' }}</span>
                                        </div>

                                        <Badge v-if="defaultCert" variant="secondary" :class="vigenciaBadgeClass(defaultCert.valid_to, defaultCert.status)">
                                            {{ vigenciaLabel(defaultCert.valid_to, defaultCert.status) }}
                                        </Badge>
                                    </div>

                                    <div class="mt-1 text-xs text-muted-foreground">
                                        CN: {{ defaultCert?.subject_cn ?? '—' }}
                                        <span class="mx-2">•</span>
                                        Vigencia: {{ formatIso(defaultCert?.valid_from) }} → {{ formatIso(defaultCert?.valid_to) }}
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Certificado</th>
                                                <th class="px-3 py-2 text-left">Tipo</th>
                                                <th class="px-3 py-2 text-left">Vigencia</th>
                                                <th class="px-3 py-2 text-left">Key</th>
                                                <th class="px-3 py-2 text-left">Password</th>
                                                <th class="px-3 py-2 text-left">Estado</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <tr v-for="c in certsTop" :key="c.id" class="border-t">
                                                <td class="px-3 py-3 align-top">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="font-medium">{{ certDisplayName(c) }}</span>
                                                        <Badge v-if="c.is_default" variant="secondary">DEFAULT</Badge>
                                                    </div>
                                                    <div class="mt-1 text-xs text-muted-foreground">
                                                        CN: {{ c.subject_cn ?? '—' }}
                                                        <span class="mx-2">•</span>
                                                        Issuer: {{ c.issuer_cn ?? '—' }}
                                                    </div>
                                                    <div class="mt-1 text-xs text-muted-foreground">
                                                        Serial: {{ c.serial_number ?? '—' }}
                                                        <span class="mx-2">•</span>
                                                        Size: {{ formatBytes(c.file_size) }}
                                                    </div>
                                                </td>

                                                <td class="px-3 py-3 align-top">
                                                    <Badge variant="secondary" class="uppercase">{{ c.type }}</Badge>
                                                </td>

                                                <td class="px-3 py-3 align-top">
                                                    <Badge variant="secondary" :class="vigenciaBadgeClass(c.valid_to, c.status)">
                                                        {{ vigenciaLabel(c.valid_to, c.status) }}
                                                    </Badge>
                                                    <div class="mt-2 text-xs text-muted-foreground">Desde: {{ formatIso(c.valid_from) }}</div>
                                                    <div class="text-xs text-muted-foreground">Hasta: {{ formatIso(c.valid_to) }}</div>
                                                </td>

                                                <td class="px-3 py-3 align-top">
                                                    <Badge :variant="yesNoVariant(c.has_private_key)">{{ c.has_private_key ? 'Sí' : 'No' }}</Badge>
                                                </td>

                                                <td class="px-3 py-3 align-top">
                                                    <Badge :variant="yesNoVariant(c.password_ok)">{{ c.password_ok ? 'OK' : 'NO' }}</Badge>
                                                </td>

                                                <td class="px-3 py-3 align-top">
                                                    <Badge :variant="statusBadgeVariant(c.status)" class="capitalize">{{ c.status }}</Badge>
                                                </td>
                                            </tr>

                                            <tr v-if="certs.length > 5" class="border-t">
                                                <td colspan="6" class="px-3 py-3 text-xs text-muted-foreground">
                                                    Mostrando 5 de {{ certs.length }} certificados.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="border-t p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-xs text-muted-foreground">
                                            Tip: si el P12/PFX tiene password incorrecto, re-súbelo o corrígelo en “Administrar certificados”.
                                        </div>

                                        <Link href="/erp/services/certificacion-emisor/certificados" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:opacity-90">
                                            Administrar certificados
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="endpoints" class="mt-4">
                    <div class="space-y-4">
                        <Card>
                            <CardHeader>
                                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <CardTitle>Ambientes y Endpoints</CardTitle>
                                        <CardDescription>Preview de URLs finales según ambiente y cf_prefix.</CardDescription>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Dialog v-model:open="openEndpointsEditor">
                                            <DialogTrigger as-child>
                                                <Button variant="secondary">Editar endpoints</Button>
                                            </DialogTrigger>

                                            <DialogContent class="sm:max-w-3xl">
                                                <DialogHeader>
                                                    <DialogTitle>Editar Endpoints</DialogTitle>
                                                    <DialogDescription>
                                                        Cambia ambiente, uso de directorio y el JSON completo de endpoints.
                                                        <span v-if="!hasSettingEndpoints && (props.endpoint_catalog?.length ?? 0) > 0" class="ml-1">
                                                            (Precargado desde catálogo/seed, porque setting.endpoints está vacío)
                                                        </span>
                                                    </DialogDescription>
                                                </DialogHeader>

                                                <div class="space-y-4">
                                                    <div class="grid gap-3 md:grid-cols-2">
                                                        <div class="space-y-2">
                                                            <Label>Ambiente</Label>
                                                            <select v-model="endpointsForm.environment" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                                                <option value="precert">precert</option>
                                                                <option value="cert">cert</option>
                                                                <option value="prod">prod</option>
                                                            </select>
                                                            <p v-if="endpointsForm.errors.environment" class="text-xs text-destructive">
                                                                {{ endpointsForm.errors.environment }}
                                                            </p>
                                                        </div>

                                                        <div class="space-y-2">
                                                            <Label>Usar directorio DGII</Label>
                                                            <label class="flex items-center gap-2 text-sm">
                                                                <input type="checkbox" v-model="endpointsForm.use_directory" class="h-4 w-4" />
                                                                <span class="text-muted-foreground">use_directory</span>
                                                            </label>
                                                            <p v-if="endpointsForm.errors.use_directory" class="text-xs text-destructive">
                                                                {{ endpointsForm.errors.use_directory }}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div class="space-y-2">
                                                        <Label>Endpoints (JSON)</Label>
                                                        <textarea v-model="endpointsForm.endpoints_json" rows="14" class="w-full rounded-md border bg-background p-2 text-xs font-mono" placeholder='{ "UrlDGII": "https://...", "UrlGetSeed": "/{cf}/autenticacion/api/..." }' />
                                                        <p v-if="endpointsJsonError" class="text-xs text-destructive">
                                                            {{ endpointsJsonError }}
                                                        </p>
                                                        <p v-if="(endpointsForm.errors as any)?.endpoints" class="text-xs text-destructive">
                                                            {{ (endpointsForm.errors as any).endpoints }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <DialogFooter class="gap-2">
                                                    <Button variant="secondary" type="button" @click="openEndpointsEditor = false">Cancelar</Button>
                                                    <Button type="button" :disabled="endpointsForm.processing" @click="submitEndpoints">
                                                        {{ endpointsForm.processing ? 'Guardando…' : 'Guardar' }}
                                                    </Button>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>

                                        <Button as-child variant="outline" class="gap-2" title="Abrir pantalla endpoints">
                                            <Link href="/erp/services/certificacion-emisor/endpoints">Abrir pantalla endpoints</Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent class="space-y-4">
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="rounded-lg border p-4">
                                        <div class="text-xs text-muted-foreground">Ambiente</div>
                                        <div class="mt-1 text-sm font-medium">{{ envLabel(setting.environment) }}</div>
                                    </div>

                                    <div class="rounded-lg border p-4">
                                        <div class="text-xs text-muted-foreground">cf_prefix (efectivo)</div>
                                        <div class="mt-1 text-sm font-medium">{{ cfPrefix }}</div>
                                    </div>

                                    <div class="rounded-lg border p-4">
                                        <div class="text-xs text-muted-foreground">Directorio DGII</div>
                                        <div class="mt-1 text-sm font-medium">
                                            {{ setting.use_directory ? 'Sí (usa listado DGII)' : 'No (usa catálogo local)' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="rounded-lg border p-4">
                                        <div class="text-xs text-muted-foreground">Host base (UrlDGII)</div>
                                        <div class="mt-1 text-sm font-medium">{{ baseHost }}</div>
                                    </div>

                                    <div class="rounded-lg border p-4">
                                        <div class="text-xs text-muted-foreground">Semilla / Validar Semilla</div>
                                        <div class="mt-2 space-y-2 text-xs">
                                            <div>
                                                <span class="text-muted-foreground">GET Seed:</span>
                                                <div class="break-all font-mono">{{ seedUrl }}</div>
                                            </div>
                                            <div>
                                                <span class="text-muted-foreground">POST Validar:</span>
                                                <div class="break-all font-mono">{{ testSeedUrl }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-lg border p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <div class="text-sm font-semibold">Catálogo (preview)</div>
                                            <p class="text-xs text-muted-foreground">
                                                Renderiza <span class="font-mono">{cf}</span> según ambiente.
                                            </p>
                                        </div>
                                        <Badge variant="secondary">{{ endpointEntries.length }} endpoints</Badge>
                                    </div>

                                    <div class="mt-3 overflow-x-auto rounded-md border">
                                        <table class="w-full text-sm">
                                            <thead class="bg-muted/40 text-xs text-muted-foreground">
                                                <tr>
                                                    <th class="px-3 py-2 text-left">Key</th>
                                                    <th class="px-3 py-2 text-left">Path</th>
                                                    <th class="px-3 py-2 text-left">URL final</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="e in endpointEntries" :key="e.key" class="border-t">
                                                    <td class="px-3 py-2 font-mono text-xs">{{ e.key }}</td>
                                                    <td class="px-3 py-2 font-mono text-xs break-all">{{ e.path }}</td>
                                                    <td class="px-3 py-2 font-mono text-xs break-all">{{ e.preview }}</td>
                                                </tr>

                                                <tr v-if="endpointEntries.length === 0" class="border-t">
                                                    <td colspan="3" class="px-3 py-4 text-sm text-muted-foreground">
                                                        No hay endpoints en setting.endpoints (Url*), ni catálogo disponible para fallback.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="rounded-lg border p-4 text-sm text-muted-foreground">
                                    Próximo: prueba real token DGII (semilla → firmar → validarsemilla) + WS token (ValidaciónCertificado).
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="sets-ecf" class="mt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Set de Pruebas e-CF</CardTitle>
                            <CardDescription>Sube Excel, genera XML por fila, firma, envía, trackId/estado.</CardDescription>
                        </CardHeader>

                        <Tabs v-model="wrapper_tabs">
                            <TabsList class="grid rounded-none w-full grid-cols-3">
                                <TabsTrigger value="ecf-wrapper">e-CF Wrapper</TabsTrigger>
                                <TabsTrigger value="rfce-wrapper">RFCE Wrapper</TabsTrigger>
                                <TabsTrigger value="acecf-wrapper">ACECF (respuesta)</TabsTrigger>
                            </TabsList>

                            <TabsContent class="mt-4 px-6" value="ecf-wrapper">
                                <XmlEcfWrapper />
                                <Card>
                                    <CardHeader>
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <CardTitle>XML e-CF</CardTitle>
                                                <CardDescription>Listado de XML generados para los e-CF del set de pruebas.</CardDescription>
                                            </div>
                                            <Badge variant="secondary">{{ xml_files?.ecf?.count }} archivos</Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent>
                                        <div class="overflow-x-auto rounded-md border">
                                            <table class="w-full text-sm">
                                                <thead class="bg-muted/40 text-xs text-muted-foreground">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left">Nombre del archivo</th>
                                                        <th class="px-3 py-2 text-left">Tipo e-CF</th>
                                                        <th class="px-3 py-2 text-left">Tamaño</th>
                                                        <th class="px-3 py-2 text-left">Acción</th>
                                                        <th class="px-3 py-2 text-left">Estatus</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="e in xml_files?.ecf?.items" :key="e.name" class="border-t">
                                                        <td class="px-3 py-2">
                                                            <div class="font-mono text-xs">{{ e.name }}</div>
                                                        </td>
                                                        <td class="px-3 py-2">{{ e.type }}</td>
                                                        <td class="px-3 py-2">{{ formatBytes(e.size_bytes ?? null) }}</td>

                                                        <td class="px-3 py-2">
                                                            <Button v-if="!e.signed" size="sm" variant="secondary" :disabled="signing[ key('ecf', e.name) ]" @click="signXml('ecf', e.name)">
                                                                <span v-if="signing[ key('ecf', e.name) ]">Firmando…</span>
                                                                <span v-else>Firmar</span>
                                                            </Button>

                                                            <Button v-else-if="!e.sent" size="sm" :disabled="sending[ key('ecf', e.name) ]" @click="sendXml('ecf', e.name)">
                                                                <span v-if="sending[ key('ecf', e.name) ]">Enviando…</span>
                                                                <span v-else>Enviar</span>
                                                            </Button>

                                                            <Button v-else size="sm" variant="secondary" disabled>Enviado</Button>
                                                        </td>

                                                        <td class="px-3 py-2">
                                                            <Badge v-if="e.sent" variant="default">Enviado</Badge>
                                                            <Badge v-else-if="e.signed" variant="default">Firmado</Badge>
                                                            <Badge v-else variant="secondary">Sin firmar</Badge>
                                                        </td>
                                                    </tr>

                                                    <tr v-if="xml_files?.ecf?.items?.length === 0" class="border-t">
                                                        <td colspan="5" class="px-3 py-4 text-sm text-muted-foreground">No hay archivos.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent class="mt-4 px-6" value="rfce-wrapper">
                                <RfceXmlWrapper />

                                <Card>
                                    <CardHeader>
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <CardTitle>XML RFCE</CardTitle>
                                                <CardDescription>Listado de XML generados para los RFCE del set de pruebas.</CardDescription>
                                            </div>
                                            <Badge variant="secondary">{{ xml_files?.rfce?.count }} archivos</Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent>
                                        <div class="overflow-x-auto rounded-md border">
                                            <table class="w-full text-sm">
                                                <thead class="bg-muted/40 text-xs text-muted-foreground">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left">Nombre del archivo</th>
                                                        <th class="px-3 py-2 text-left">Tipo e-CF</th>
                                                        <th class="px-3 py-2 text-left">Tamaño</th>
                                                        <th class="px-3 py-2 text-left">Acción</th>
                                                        <th class="px-3 py-2 text-left">Estatus</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="e in xml_files?.rfce?.items" :key="e.name" class="border-t">
                                                        <td class="px-3 py-2">
                                                            <div class="font-mono text-xs">{{ e.name }}</div>
                                                        </td>
                                                        <td class="px-3 py-2">{{ e.type }}</td>
                                                        <td class="px-3 py-2">{{ formatBytes(e.size_bytes ?? null) }}</td>

                                                        <td class="px-3 py-2">
                                                            <Button v-if="!e.signed" size="sm" variant="secondary" :disabled="signing[ key('rfce', e.name) ]" @click="signXml('rfce', e.name)">
                                                                <span v-if="signing[ key('rfce', e.name) ]">Firmando…</span>
                                                                <span v-else>Firmar</span>
                                                            </Button>

                                                            <Button v-else-if="!e.sent" size="sm" :disabled="sending[ key('rfce', e.name) ]" @click="sendXml('rfce', e.name)">
                                                                <span v-if="sending[ key('rfce', e.name) ]">Enviando…</span>
                                                                <span v-else>Enviar</span>
                                                            </Button>

                                                            <Button v-else size="sm" variant="secondary" disabled>Enviado</Button>
                                                        </td>

                                                        <td class="px-3 py-2">
                                                            <Badge v-if="e.sent" variant="default">Enviado</Badge>
                                                            <Badge v-else-if="e.signed" variant="default">Firmado</Badge>
                                                            <Badge v-else variant="secondary">Sin firmar</Badge>
                                                        </td>
                                                    </tr>

                                                    <tr v-if="xml_files?.rfce?.items?.length === 0" class="border-t">
                                                        <td colspan="5" class="px-3 py-4 text-sm text-muted-foreground">No hay archivos.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent class="mt-4 px-6" value="acecf-wrapper">
                                <AcecfXmlWrapper />

                                <Card>
                                    <CardHeader>
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <CardTitle>XML ACECF</CardTitle>
                                                <CardDescription>Listado de XML generados para los ACECF del set de pruebas.</CardDescription>
                                            </div>
                                            <Badge variant="secondary">{{ xml_files?.acecf?.count }} archivos</Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent>
                                        <div class="overflow-x-auto rounded-md border">
                                            <table class="w-full text-sm">
                                                <thead class="bg-muted/40 text-xs text-muted-foreground">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left">Nombre del archivo</th>
                                                        <th class="px-3 py-2 text-left">Tipo e-CF</th>
                                                        <th class="px-3 py-2 text-left">Tamaño</th>
                                                        <th class="px-3 py-2 text-left">Acción</th>
                                                        <th class="px-3 py-2 text-left">Estatus</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="e in xml_files?.acecf?.items" :key="e.name" class="border-t">
                                                        <td class="px-3 py-2">
                                                            <div class="font-mono text-xs">{{ e.name }}</div>
                                                        </td>
                                                        <td class="px-3 py-2">{{ e.type }}</td>
                                                        <td class="px-3 py-2">{{ formatBytes(e.size_bytes ?? null) }}</td>

                                                        <td class="px-3 py-2">
                                                            <Button v-if="!e.signed" size="sm" variant="secondary" :disabled="signing[ key('acecf', e.name) ]" @click="signXml('acecf', e.name)">
                                                                <span v-if="signing[ key('acecf', e.name) ]">Firmando…</span>
                                                                <span v-else>Firmar</span>
                                                            </Button>

                                                            <Button v-else-if="!e.sent" size="sm" :disabled="sending[ key('acecf', e.name) ]" @click="sendXml('acecf', e.name)">
                                                                <span v-if="sending[ key('acecf', e.name) ]">Enviando…</span>
                                                                <span v-else>Enviar</span>
                                                            </Button>

                                                            <Button v-else size="sm" variant="secondary" disabled>Enviado</Button>
                                                        </td>

                                                        <td class="px-3 py-2">
                                                            <Badge v-if="e.sent" variant="default">Enviado</Badge>
                                                            <Badge v-else-if="e.signed" variant="default">Firmado</Badge>
                                                            <Badge v-else variant="secondary">Sin firmar</Badge>
                                                        </td>
                                                    </tr>

                                                    <tr v-if="xml_files?.acecf?.items?.length === 0" class="border-t">
                                                        <td colspan="5" class="px-3 py-4 text-sm text-muted-foreground">No hay archivos.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </Card>
                </TabsContent>

                <TabsContent value="servicios-web" class="mt-4">
                    <div class="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    Servicios Web (DGII) — Comunicación obligatoria
                                    <Badge v-if="wsUsingFallback" variant="secondary" class="bg-yellow-400 text-black hover:bg-yellow-400">
                                        Usando slug como fallback
                                    </Badge>
                                </CardTitle>
                                <CardDescription>
                                    DGII exige exponer endpoints públicos por subdominio para: Semilla, Validación de certificado,
                                    Recepción e-CF (ARECF) y Aprobación Comercial (ACECF).
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="space-y-6">
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="rounded-lg border p-4">
                                        <div class="text-xs text-muted-foreground">Subdominio WS (empresa)</div>
                                        <div class="mt-1 flex items-center justify-between gap-2">
                                            <div class="text-sm font-medium font-mono break-all">
                                                {{ wsTenant }}.{{ appBaseDomain }}
                                            </div>
                                            <Button size="sm" variant="outline" @click="copyText(`${wsTenant}.${appBaseDomain}`)">Copiar</Button>
                                        </div>
                                        <p class="mt-2 text-xs text-muted-foreground">
                                            Se usa <span class="font-mono">companies.ws_subdomain</span> (o <span class="font-mono">slug</span> como fallback).
                                        </p>
                                    </div>

                                    <div class="rounded-lg border p-4">
                                        <div class="text-xs text-muted-foreground">TLS / Wildcard</div>
                                        <div class="mt-1 text-sm font-medium">
                                            Requiere certificado para <span class="font-mono">*.{{ appBaseDomain }}</span>
                                        </div>
                                        <p class="mt-2 text-xs text-muted-foreground">
                                            Sin wildcard, DGII no podrá llamar <span class="font-mono">{{ wsTenant }}.{{ appBaseDomain }}</span>.
                                        </p>
                                    </div>

                                    <div class="rounded-lg border p-4">
                                        <div class="text-xs text-muted-foreground">Ambiente DGII</div>
                                        <div class="mt-1 text-sm font-medium">{{ envLabel(setting.environment) }}</div>
                                        <p class="mt-2 text-xs text-muted-foreground">
                                            cf_prefix efectivo: <span class="font-mono">{{ cfPrefix }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-lg border p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <div class="text-sm font-semibold">Flujo requerido (pasos 7 → 11)</div>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Token WS (Bearer) lo emites tú en <span class="font-mono">ValidaciónCertificado</span> y lo usas para proteger
                                                <span class="font-mono">Recepción</span> y <span class="font-mono">Aprobación Comercial</span>.
                                            </p>
                                        </div>
                                        <Badge variant="secondary">WS Token ≠ DGII Token</Badge>
                                    </div>

                                    <ol class="mt-4 space-y-3 text-sm">
                                        <li class="flex gap-3">
                                            <Badge variant="secondary" class="min-w-10 justify-center">7</Badge>
                                            <div>
                                                <div class="font-medium">DGII solicita Semilla (GET)</div>
                                                <div class="text-xs text-muted-foreground font-mono break-all">{{ wsUrls.semilla }}</div>
                                            </div>
                                        </li>

                                        <li class="flex gap-3">
                                            <Badge variant="secondary" class="min-w-10 justify-center">8</Badge>
                                            <div>
                                                <div class="font-medium">DGII valida certificado / semilla (POST) → tú respondes token WS</div>
                                                <div class="text-xs text-muted-foreground font-mono break-all">{{ wsUrls.validacion }}</div>
                                            </div>
                                        </li>

                                        <li class="flex gap-3">
                                            <Badge variant="secondary" class="min-w-10 justify-center">9</Badge>
                                            <div>
                                                <div class="font-medium">Recepción e-CF (POST) → tú devuelves ARECF firmado</div>
                                                <div class="text-xs text-muted-foreground font-mono break-all">{{ wsUrls.recepcion }}</div>
                                                <div class="mt-1 text-xs text-muted-foreground">
                                                    Requiere <span class="font-mono">Authorization: Bearer (token WS)</span>.
                                                </div>
                                            </div>
                                        </li>

                                        <li class="flex gap-3">
                                            <Badge variant="secondary" class="min-w-10 justify-center">10</Badge>
                                            <div>
                                                <div class="font-medium">Aprobación Comercial e-CF (POST) → tú devuelves ACECF firmado</div>
                                                <div class="text-xs text-muted-foreground font-mono break-all">{{ wsUrls.aprobacion }}</div>
                                                <div class="mt-1 text-xs text-muted-foreground">
                                                    Requiere <span class="font-mono">Authorization: Bearer (token WS)</span>.
                                                </div>
                                            </div>
                                        </li>

                                        <li class="flex gap-3">
                                            <Badge variant="secondary" class="min-w-10 justify-center">11</Badge>
                                            <div>
                                                <div class="font-medium">Tu sistema reenvía ACECF a DGII usando tu token DGII real</div>
                                                <div class="mt-1 text-xs text-muted-foreground">
                                                    Esto usa <span class="font-mono">DgiiTokenManager</span> y <span class="font-mono">{{ cfPrefix }}</span> para armar el host DGII.
                                                </div>
                                            </div>
                                        </li>
                                    </ol>
                                </div>

                                <div class="rounded-lg border p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <div class="text-sm font-semibold">Endpoints expuestos (públicos)</div>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Estos endpoints deben correr sin sesión/CSRF y aceptar XML (multipart).
                                            </p>
                                        </div>
                                        <Badge variant="secondary">DGII → Tu Subdominio</Badge>
                                    </div>

                                    <div class="mt-3 overflow-x-auto rounded-md border">
                                        <table class="w-full text-sm">
                                            <thead class="bg-muted/40 text-xs text-muted-foreground">
                                                <tr>
                                                    <th class="px-3 py-2 text-left">Método</th>
                                                    <th class="px-3 py-2 text-left">Ruta</th>
                                                    <th class="px-3 py-2 text-left">URL final</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-t">
                                                    <td class="px-3 py-2 font-mono text-xs">GET</td>
                                                    <td class="px-3 py-2 font-mono text-xs">/fe/autenticacion/api/semilla</td>
                                                    <td class="px-3 py-2 font-mono text-xs break-all">{{ wsUrls.semilla }}</td>
                                                </tr>
                                                <tr class="border-t">
                                                    <td class="px-3 py-2 font-mono text-xs">POST</td>
                                                    <td class="px-3 py-2 font-mono text-xs">/fe/autenticacion/api/validacioncertificado</td>
                                                    <td class="px-3 py-2 font-mono text-xs break-all">{{ wsUrls.validacion }}</td>
                                                </tr>
                                                <tr class="border-t">
                                                    <td class="px-3 py-2 font-mono text-xs">POST</td>
                                                    <td class="px-3 py-2 font-mono text-xs">/fe/recepcion/api/ecf</td>
                                                    <td class="px-3 py-2 font-mono text-xs break-all">{{ wsUrls.recepcion }}</td>
                                                </tr>
                                                <tr class="border-t">
                                                    <td class="px-3 py-2 font-mono text-xs">POST</td>
                                                    <td class="px-3 py-2 font-mono text-xs">/fe/aprobacioncomercial/api/ecf</td>
                                                    <td class="px-3 py-2 font-mono text-xs break-all">{{ wsUrls.aprobacion }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <Card>
                                    <CardHeader>
                                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <CardTitle>Actividad DGII (WS Logs)</CardTitle>
                                                <CardDescription>
                                                    Eventos instrumentados por <span class="font-mono">DgiiWsActivityLogger</span> (correlation id, status, duración, paths).
                                                </CardDescription>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                <Badge variant="secondary">{{ wsStats.total }} eventos</Badge>
                                                <Badge v-if="wsStats.warnings" variant="secondary" class="bg-yellow-400 text-black hover:bg-yellow-400">
                                                    {{ wsStats.warnings }} warnings
                                                </Badge>
                                                <Badge v-if="wsStats.errors" variant="destructive">
                                                    {{ wsStats.errors }} errores
                                                </Badge>

                                                <Button variant="outline" size="sm" :disabled="wsLoading" @click="fetchWsActivity">
                                                    {{ wsLoading ? 'Actualizando…' : 'Refrescar' }}
                                                </Button>
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent class="space-y-4">
                                        <div class="grid gap-3 md:grid-cols-3">
                                            <div class="rounded-lg border p-4">
                                                <div class="text-xs text-muted-foreground">Última actividad</div>
                                                <div class="mt-1 text-sm font-medium font-mono">{{ wsStats.last ?? '—' }}</div>
                                            </div>

                                            <div class="rounded-lg border p-4">
                                                <div class="text-xs text-muted-foreground">Última Semilla / Validación</div>
                                                <div class="mt-1 text-xs text-muted-foreground">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span>Semilla:</span>
                                                        <span class="font-mono">{{ lastEventTs('ws.semilla') ?? '—' }}</span>
                                                    </div>
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span>Validación:</span>
                                                        <span class="font-mono">{{ lastEventTs('ws.validacion') ?? '—' }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-lg border p-4">
                                                <div class="text-xs text-muted-foreground">Auto-refresh</div>
                                                <div class="mt-2 flex items-center gap-2 text-sm">
                                                    <input type="checkbox" class="h-4 w-4" v-model="wsAutoRefresh" />
                                                    <span class="text-muted-foreground">cada 15s (solo en este tab)</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid gap-3 md:grid-cols-3">
                                            <div class="space-y-2">
                                                <div class="text-xs text-muted-foreground">Nivel</div>
                                                <select v-model="wsLevel" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                                    <option value="all">Todos</option>
                                                    <option value="info">Info</option>
                                                    <option value="warning">Warning</option>
                                                    <option value="error">Error</option>
                                                </select>
                                            </div>

                                            <div class="space-y-2 md:col-span-2">
                                                <div class="text-xs text-muted-foreground">Buscar</div>
                                                <input v-model="wsSearch" type="text" class="h-9 w-full rounded-md border bg-background px-3 text-sm" placeholder="cid, event, path, status, encf, rnc..." />
                                            </div>
                                        </div>

                                        <div v-if="wsError" class="rounded-lg border border-red-500/30 bg-red-500/5 p-4 text-sm text-destructive">
                                            {{ wsError }}
                                        </div>

                                        <div v-if="wsLoading" class="rounded-lg border p-4 text-sm text-muted-foreground">
                                            Cargando actividad WS…
                                        </div>

                                        <div v-else class="overflow-x-auto rounded-md border">
                                            <table class="w-full text-sm">
                                                <thead class="bg-muted/40 text-xs text-muted-foreground">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left">TS</th>
                                                        <th class="px-3 py-2 text-left">Level</th>
                                                        <th class="px-3 py-2 text-left">Event</th>
                                                        <th class="px-3 py-2 text-left">Request</th>
                                                        <th class="px-3 py-2 text-left">Status</th>
                                                        <th class="px-3 py-2 text-left">Dur</th>
                                                        <th class="px-3 py-2 text-left">CID</th>
                                                        <th class="px-3 py-2 text-left">Artifacts</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(it, idx) in wsFiltered" :key="`${it.ts}-${it.event}-${it.cid}-${idx}`" class="border-t">
                                                        <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ it.ts }}</td>

                                                        <td class="px-3 py-2">
                                                            <Badge :variant="levelBadge(it).variant" :class="levelBadge(it).cls" class="capitalize">
                                                                {{ it.level }}
                                                            </Badge>
                                                        </td>

                                                        <td class="px-3 py-2">
                                                            <div class="font-mono text-xs break-all">{{ it.event }}</div>
                                                        </td>

                                                        <td class="px-3 py-2">
                                                            <div class="font-mono text-xs break-all">
                                                                {{ (it.method ?? '—') }} {{ it.path ?? '—' }}
                                                            </div>
                                                        </td>

                                                        <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                                            {{ it.status ?? '—' }}
                                                        </td>

                                                        <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                                            {{ typeof it.duration_ms === 'number' ? `${it.duration_ms}ms` : '—' }}
                                                        </td>

                                                        <td class="px-3 py-2">
                                                            <div class="flex items-center gap-2">
                                                                <span class="font-mono text-xs">{{ shortCid(it.cid) }}</span>
                                                                <Button size="sm" variant="outline" @click="copyText(it.cid)">Copiar</Button>
                                                            </div>
                                                        </td>

                                                        <td class="px-3 py-2">
                                                            <div class="space-y-1 text-xs font-mono text-muted-foreground">
                                                                <div v-if="it.in_path" class="flex items-center justify-between gap-2">
                                                                    <span class="truncate">in: {{ it.in_path }}</span>
                                                                    <Button size="sm" variant="outline" @click="copyText(it.in_path)">Copiar</Button>
                                                                </div>
                                                                <div v-if="it.out_path" class="flex items-center justify-between gap-2">
                                                                    <span class="truncate">out: {{ it.out_path }}</span>
                                                                    <Button size="sm" variant="outline" @click="copyText(it.out_path)">Copiar</Button>
                                                                </div>
                                                                <div v-if="it.dgii_resp_path" class="flex items-center justify-between gap-2">
                                                                    <span class="truncate">dgii: {{ it.dgii_resp_path }}</span>
                                                                    <Button size="sm" variant="outline" @click="copyText(it.dgii_resp_path)">Copiar</Button>
                                                                </div>
                                                                <div v-if="!it.in_path && !it.out_path && !it.dgii_resp_path">—</div>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <tr v-if="wsFiltered.length === 0" class="border-t">
                                                        <td colspan="8" class="px-3 py-4 text-sm text-muted-foreground">
                                                            No hay actividad (o no hay eventos persistidos todavía).
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="rounded-lg border p-4 text-xs text-muted-foreground">
                                            Nota: si aquí no aparece nada, revisa que el logger esté persistiendo JSONL y que el endpoint
                                            <span class="font-mono">/erp/services/certificacion-emisor/ws/activity</span>
                                            esté devolviendo datos para esta empresa.
                                        </div>
                                    </CardContent>
                                </Card>

                                <div class="rounded-lg border p-4">
                                    <div class="text-sm font-semibold">Checklist rápido</div>
                                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                                        <li>DNS: <span class="font-mono">*.{{ appBaseDomain }}</span> apunta al VPS (A record).</li>
                                        <li>Nginx: <span class="font-mono">server_name {{ appBaseDomain }} *.{{ appBaseDomain }}</span> con cert wildcard.</li>
                                        <li>Laravel: rutas WS en stack <span class="font-mono">api</span> (sin cookies/CSRF) + límite de size.</li>
                                        <li>Auth WS: token generado en ValidaciónCertificado, exigido en Recepción/Aprobación.</li>
                                        <li>Firma: ARECF/ACECF firmado con el cert default de la empresa (enveloped, SHA256, C14N según tu regla).</li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </ErpLayout>
</template>