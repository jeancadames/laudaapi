<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'
import { useToast } from '@/components/ui/toast/use-toast'

const { toast } = useToast()
const page = usePage()

type CompanyPayload = {
    id: number
    name: string
    slug: string
    currency: string
    timezone: string
    active?: boolean
}

type TaxProfilePayload = {
    id?: number
    legal_name: string
    trade_name?: string | null
    country_code?: string | null
    tax_id?: string | null
    tax_id_type?: string | null
    address_line1?: string | null
    address_line2?: string | null
    city?: string | null
    state?: string | null
    postal_code?: string | null
    billing_email?: string | null
    billing_phone?: string | null
    billing_contact_name?: string | null
    tax_exempt?: boolean
    default_itbis_rate?: string | number | null

    taxpayer_type?: string | null
    tax_regime?: string | null
    rst_modality?: string | null
    rst_category?: string | null

    economic_activity_primary_code?: string | null
    economic_activity_primary_name?: string | null
    economic_activities_secondary?: Array<{ code?: string | null; name?: string | null }> | null

    invoicing_mode?: string | null
    dgii_status?: string | null
    dgii_registered_on?: string | null

    fiscal_year_end_id?: number | null
}

type ComplianceTemplate = {
    id: number
    country_code: string
    code: string
    name: string
    description: string | null
    frequency: string
    due_rule: any
    official_ref_url?: string | null
    version: number
    active: boolean
    authority_id: number
    authority_code: string
    authority_name: string
}

type CompanyObligation = {
    template_id: number
    enabled: boolean
    starts_on: string | null
    ends_on: string | null
    owner_user_id?: number | null
    reminders?: any
    overrides?: any
}

type FiscalYearEnd = {
    id: number
    label: string
    close_month: number
    close_day: number
    common_business_types?: string | null
    ir2_due_days: number
}

const props = defineProps<{
    company: CompanyPayload
    taxProfile: TaxProfilePayload
    complianceCatalog: ComplianceTemplate[]
    companyObligations: CompanyObligation[]
    fiscalYearEnds?: FiscalYearEnd[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Empresa', href: '/subscriber/company' },
]

// Flash toasts
const flashError = computed(() => (page.props.flash as any)?.error ?? null)
const flashSuccess = computed(() => (page.props.flash as any)?.success ?? null)

let lastFlashKey = ''
watch(
    () => [ flashError.value, flashSuccess.value ],
    ([ err, ok ]) => {
        const key = `${err ?? ''}||${ok ?? ''}`
        if (!key.trim() || key === lastFlashKey) return
        lastFlashKey = key
        if (err) toast({ title: 'Error', description: err, variant: 'destructive' })
        else if (ok) toast({ title: 'Listo', description: ok })
    },
    { immediate: true }
)

// Helpers
function normStr(v: any) {
    return String(v ?? '').trim()
}
function upper(v: any) {
    return normStr(v).toUpperCase()
}
function toIso2(v: any) {
    return upper(v).slice(0, 2)
}
function toCurrency3(v: any) {
    return upper(v).slice(0, 3)
}
function safeJson(v: any): any {
    if (v == null) return null
    if (typeof v === 'object') return v
    if (typeof v !== 'string') return null
    try {
        return JSON.parse(v)
    } catch {
        return null
    }
}

function ruleSummary(t: ComplianceTemplate): string {
    const rule = safeJson(t.due_rule) ?? {}
    const type = String(rule?.type ?? '')
    if (!type) return '—'
    if (type === 'monthly_day') {
        const day = rule.day ?? '—'
        const off = rule.month_offset ?? 1
        const shift = rule.shift ?? 'company_default'
        return `Mensual: día ${day} (offset +${off}), shift=${shift}`
    }
    if (type === 'monthly_nth_business_day') {
        const n = rule.n ?? 3
        const off = rule.month_offset ?? 1
        return `Mensual: ${n}° día laborable (offset +${off})`
    }
    if (type === 'year_table') return 'Tabla anual (override por periodo)'
    if (type === 'annual_fixed_month_day') {
        const m = rule.month ?? '—'
        const d = rule.day ?? '—'
        const yo = rule.year_offset ?? 1
        return `Anual: ${m}/${d} (year_offset +${yo})`
    }
    return type
}

// -------------------------
// ✅ Date helpers (timezone-aware)
// -------------------------
function ymdInTz(tz: string): string {
    try {
        return new Intl.DateTimeFormat('en-CA', {
            timeZone: tz,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(new Date()) // => YYYY-MM-DD
    } catch {
        return new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())
    }
}

function pad2(n: number) {
    return String(n).padStart(2, '0')
}
function parseYmd(ymd: string): { y: number; m: number; d: number } | null {
    const parts = String(ymd || '').split('-').map((x) => Number(x))
    if (parts.length !== 3) return null
    const [ y, m, d ] = parts
    if (!y || !m || !d) return null
    return { y, m, d }
}
function lastDayOfMonth(y: number, m: number): number {
    // m = 1..12
    return new Date(Date.UTC(y, m, 0)).getUTCDate()
}

// Tabs
type TabKey = 'company' | 'tax' | 'dgii' | 'compliance'
const tab = ref<TabKey>('company')

// Form principal
const form = useForm({
    company_name: props.company?.name ?? '',
    company_currency: props.company?.currency ?? 'USD',
    company_timezone: props.company?.timezone ?? 'America/Santo_Domingo',
    company_active: props.company?.active ?? true,

    legal_name: props.taxProfile?.legal_name ?? (props.company?.name ?? ''),
    trade_name: props.taxProfile?.trade_name ?? '',

    country_code: props.taxProfile?.country_code ?? 'DO',
    tax_id: props.taxProfile?.tax_id ?? '',
    tax_id_type: props.taxProfile?.tax_id_type ?? 'RNC',

    address_line1: props.taxProfile?.address_line1 ?? '',
    address_line2: props.taxProfile?.address_line2 ?? '',
    city: props.taxProfile?.city ?? '',
    state: props.taxProfile?.state ?? '',
    postal_code: props.taxProfile?.postal_code ?? '',

    billing_email: props.taxProfile?.billing_email ?? '',
    billing_phone: props.taxProfile?.billing_phone ?? '',
    billing_contact_name: props.taxProfile?.billing_contact_name ?? '',

    tax_exempt: !!props.taxProfile?.tax_exempt,
    default_itbis_rate: props.taxProfile?.default_itbis_rate ?? '18.000',

    taxpayer_type: props.taxProfile?.taxpayer_type ?? '',
    tax_regime: props.taxProfile?.tax_regime ?? 'general',
    rst_modality: props.taxProfile?.rst_modality ?? '',
    rst_category: props.taxProfile?.rst_category ?? '',

    economic_activity_primary_code: props.taxProfile?.economic_activity_primary_code ?? '',
    economic_activity_primary_name: props.taxProfile?.economic_activity_primary_name ?? '',
    economic_activities_secondary: (props.taxProfile?.economic_activities_secondary ?? []).map((x) => ({
        code: x?.code ?? '',
        name: x?.name ?? '',
    })),

    invoicing_mode: props.taxProfile?.invoicing_mode ?? '',
    dgii_status: props.taxProfile?.dgii_status ?? '',
    dgii_registered_on: props.taxProfile?.dgii_registered_on ?? '',

    fiscal_year_end_id: (props.taxProfile as any)?.fiscal_year_end_id ?? null,
})

const isSaving = computed(() => form.processing)

function fieldError(key: string): string | null {
    // "@ts-expect-error"
    return (form.errors as any)?.[ key ] ?? null
}

// ✅ snapshots “dirty” (reset al guardar)
const dirtyCompany = ref(false)
const dirtyObl = ref(false)
const snapCompany = ref('')
const snapObl = ref('')

function resetCompanySnapshot() {
    snapCompany.value = JSON.stringify(form.data())
    dirtyCompany.value = false
}

function submitCompany() {
    form.company_currency = toCurrency3(form.company_currency)
    form.country_code = toIso2(form.country_code)
    form.tax_id_type = upper(form.tax_id_type)

    if (form.tax_regime !== 'rst') {
        form.rst_modality = ''
        form.rst_category = ''
    }

    form.economic_activities_secondary = (form.economic_activities_secondary ?? []).filter((r) => {
        return normStr(r?.code) || normStr(r?.name)
    })

    form.post('/subscriber/company', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resetCompanySnapshot(),
    })
}

function copyCompanyNameToLegal() {
    form.legal_name = form.company_name
}
function addSecondaryActivity() {
    form.economic_activities_secondary = form.economic_activities_secondary ?? []
    if (form.economic_activities_secondary.length >= 5) return
    form.economic_activities_secondary.push({ code: '', name: '' })
}
function removeSecondaryActivity(idx: number) {
    form.economic_activities_secondary = (form.economic_activities_secondary ?? []).filter((_, i) => i !== idx)
}

watch(
    () => form.company_name,
    (v) => {
        if (!normStr(form.legal_name)) form.legal_name = normStr(v)
    }
)

watch(
    () => form.data(),
    () => {
        dirtyCompany.value = JSON.stringify(form.data()) !== snapCompany.value
    },
    { deep: true }
)

// -------------------------
// ✅ Fiscal Year End catalog + helpers
// -------------------------
const fiscalYearEnds = computed(() => props.fiscalYearEnds ?? [])

const selectedFye = computed(() => {
    const id = Number((form as any).fiscal_year_end_id || 0)
    if (!id) return null
    return fiscalYearEnds.value.find((x) => x.id === id) ?? null
})

// ✅ auto-default: si country=DO y no hay seleccionado, usa 31 de Diciembre si existe
function maybeDefaultFiscalYearEnd() {
    const cc = String(form.country_code || 'DO').toUpperCase()
    if (cc !== 'DO') return
    if (form.fiscal_year_end_id) return
    const dec31 = fiscalYearEnds.value.find((x) => x.close_month === 12 && x.close_day === 31)
    if (dec31) form.fiscal_year_end_id = dec31.id
}

watch(
    () => [ fiscalYearEnds.value.length, form.country_code ],
    () => {
        maybeDefaultFiscalYearEnd()
    },
    { immediate: true }
)

// clamp por si el catálogo trae algo raro (Feb 29, Sep 31, etc.)
function clampDay(y: number, m: number, d: number): number {
    return Math.min(Number(d), lastDayOfMonth(y, m))
}

function getFyeMd(): { m: number; d: number } {
    const f = selectedFye.value
    const m = Number(f?.close_month ?? 12)
    const d = Number(f?.close_day ?? 31)
    return { m, d }
}

// ends_on = próximo cierre fiscal a partir de starts_on (>= starts_on)
function fiscalEndForStartWithMd(startsYmd: string, md: { m: number; d: number }): string {
    const p = parseYmd(startsYmd)
    if (!p) return ''

    let y = p.y
    let dd = clampDay(y, md.m, md.d)
    let candidate = `${y}-${pad2(md.m)}-${pad2(dd)}`

    if (candidate < startsYmd) {
        y += 1
        dd = clampDay(y, md.m, md.d)
        candidate = `${y}-${pad2(md.m)}-${pad2(dd)}`
    }

    return candidate
}

function fiscalEndForStart(startsYmd: string): string {
    return fiscalEndForStartWithMd(startsYmd, getFyeMd())
}

// ✅ preview: cierre fiscal + IR-2 (x días después) (solo visual)
function addDaysYmd(ymd: string, days: number): string {
    const p = parseYmd(ymd)
    if (!p) return ''
    const base = new Date(Date.UTC(p.y, p.m - 1, p.d))
    const out = new Date(base.getTime() + Number(days) * 86400000)
    return `${out.getUTCFullYear()}-${pad2(out.getUTCMonth() + 1)}-${pad2(out.getUTCDate())}`
}

const fiscalYearPreview = computed(() => {
    const f = selectedFye.value
    if (!f) return null

    const tz = props.company?.timezone || 'UTC'
    const today = ymdInTz(tz)
    const p = parseYmd(today)
    if (!p) return null

    const dd = clampDay(p.y, Number(f.close_month), Number(f.close_day))
    const close = `${p.y}-${pad2(Number(f.close_month))}-${pad2(dd)}`
    const due = addDaysYmd(close, Number(f.ir2_due_days ?? 120))

    return {
        close,
        due,
        days: Number(f.ir2_due_days ?? 120),
        common: f.common_business_types ?? null,
    }
})

// -------------------------
// Cumplimiento (tenant_obligations)
// -------------------------
const catalog = computed(() => props.complianceCatalog ?? [])

const obligationsByTemplateId = computed<Record<number, CompanyObligation>>(() => {
    const map: Record<number, CompanyObligation> = {}
    for (const o of props.companyObligations ?? []) map[ Number(o.template_id) ] = o
    return map
})

type OblState = { enabled: boolean; starts_on: string; ends_on: string }
const oblState = ref<Record<number, OblState>>({})

function buildOblPayload() {
    const items: Array<{ template_id: number; enabled: boolean; starts_on: string | null; ends_on: string | null }> = []
    for (const t of catalog.value) {
        const st = oblState.value[ t.id ]
        if (!st) continue
        items.push({
            template_id: t.id,
            enabled: !!st.enabled,
            starts_on: st.starts_on?.trim() ? st.starts_on.trim() : null,
            ends_on: st.ends_on?.trim() ? st.ends_on.trim() : null,
        })
    }
    return items
}

// ✅ NO re-hidratar si hay cambios sin guardar
function initOblState(force = false) {
    if (!force && dirtyObl.value) return

    const map: Record<number, OblState> = {}
    for (const t of catalog.value) {
        const existing = obligationsByTemplateId.value[ t.id ]
        map[ t.id ] = {
            enabled: !!(existing?.enabled ?? false),
            starts_on: existing?.starts_on ?? '',
            ends_on: existing?.ends_on ?? '',
        }
    }
    oblState.value = map
    resetOblSnapshot()
}

function resetOblSnapshot() {
    snapObl.value = JSON.stringify(buildOblPayload())
    dirtyObl.value = false
}

// ✅ auto rango al activar/desactivar (con Fiscal Year End)
const prevEnabled = ref<Record<number, boolean>>({})

// track para no pisar manual cuando cambie el FY end
const lastFyeMd = ref<{ m: number; d: number }>({ m: 12, d: 31 })

function applyAutoRange(id: number) {
    const st = oblState.value[ id ]
    if (!st) return

    const tz = props.company?.timezone || 'UTC'
    const today = ymdInTz(tz)

    if (!st.starts_on) st.starts_on = today

    // ✅ ends_on = próximo cierre fiscal (o 31/12 si no hay selección)
    if (!st.ends_on) st.ends_on = fiscalEndForStart(st.starts_on || today)

    // hard guard: si por alguna razón queda inválido
    if (st.starts_on && st.ends_on && st.ends_on < st.starts_on) {
        st.ends_on = fiscalEndForStart(st.starts_on)
    }
}

// ✅ al cambiar fiscal_year_end_id, recalcular ends_on SOLO si estaba “auto” (o vacío)
watch(
    () => form.fiscal_year_end_id,
    () => {
        const prevMd = lastFyeMd.value
        const nextMd = getFyeMd()
        lastFyeMd.value = nextMd

        const tz = props.company?.timezone || 'UTC'
        const today = ymdInTz(tz)

        for (const st of Object.values(oblState.value || {})) {
            if (!st?.enabled) continue
            if (!st.starts_on) st.starts_on = today

            const prevAuto = fiscalEndForStartWithMd(st.starts_on, prevMd)
            const nextAuto = fiscalEndForStartWithMd(st.starts_on, nextMd)

            // solo si está vacío o coincide con el auto anterior
            if (!st.ends_on || st.ends_on === prevAuto) {
                st.ends_on = nextAuto
            }

            if (st.starts_on && st.ends_on && st.ends_on < st.starts_on) {
                st.ends_on = nextAuto
            }
        }
    }
)

watch(
    () => oblState.value,
    (cur) => {
        const prev = prevEnabled.value
        const next: Record<number, boolean> = { ...prev }

        for (const [ k, st ] of Object.entries(cur || {})) {
            const id = Number(k)
            const was = !!prev[ id ]
            const now = !!st?.enabled
            next[ id ] = now

            if (!was && now) {
                applyAutoRange(id)
            }
            if (was && !now) {
                st.starts_on = ''
                st.ends_on = ''
            }
        }

        prevEnabled.value = next
    },
    { deep: true }
)

watch(
    () => [ props.complianceCatalog, props.companyObligations ],
    () => initOblState(false),
    { deep: true }
)

// filtros
const tplQuery = ref('')
const authorityFilter = ref<'all' | string>('all')
const onlyEnabled = ref(false)

const authorityOptions = computed(() => {
    const map = new Map<string, string>()
    for (const t of catalog.value) {
        const code = (t.authority_code ?? '').toUpperCase()
        if (!code) continue
        if (!map.has(code)) map.set(code, t.authority_name ?? code)
    }
    return Array.from(map.entries()).map(([ code, name ]) => ({ code, name }))
})

const filteredCatalog = computed(() => {
    const qq = tplQuery.value.trim().toLowerCase()
    const auth = authorityFilter.value
    return catalog.value.filter((t) => {
        const st = oblState.value[ t.id ]
        if (!st) return false
        if (onlyEnabled.value && !st.enabled) return false
        if (auth !== 'all' && (t.authority_code ?? '').toUpperCase() !== auth) return false
        if (!qq) return true
        return (
            (t.code ?? '').toLowerCase().includes(qq) ||
            (t.name ?? '').toLowerCase().includes(qq) ||
            (t.description ?? '').toLowerCase().includes(qq) ||
            (t.authority_code ?? '').toLowerCase().includes(qq) ||
            (t.authority_name ?? '').toLowerCase().includes(qq) ||
            ruleSummary(t).toLowerCase().includes(qq)
        )
    })
})

const enabledCount = computed(() => {
    let c = 0
    for (const t of catalog.value) if (oblState.value[ t.id ]?.enabled) c++
    return c
})

function enableDefaults() {
    const add = new Set<number>()
    for (const t of catalog.value) {
        const auth = (t.authority_code ?? '').toUpperCase()
        const code = (t.code ?? '').toUpperCase()
        if (
            (auth === 'DGII' && [ '606', '607', 'IT-1', 'IR-3', 'IR-17' ].includes(code)) ||
            (auth === 'TSS' && [ 'TSS-SDSS' ].includes(code))
        ) add.add(t.id)
    }
    for (const id of add) {
        if (oblState.value[ id ]) oblState.value[ id ].enabled = true // watcher llenará fechas
    }
}

function disableAllCompliance() {
    for (const t of catalog.value) {
        if (oblState.value[ t.id ]) {
            oblState.value[ t.id ].enabled = false
            oblState.value[ t.id ].starts_on = ''
            oblState.value[ t.id ].ends_on = ''
        }
    }
}

function enableOnlyAuthority(code: string) {
    const c = code.toUpperCase()
    for (const t of catalog.value) {
        if (!oblState.value[ t.id ]) continue
        const on = (t.authority_code ?? '').toUpperCase() === c
        oblState.value[ t.id ].enabled = on
        if (!on) {
            oblState.value[ t.id ].starts_on = ''
            oblState.value[ t.id ].ends_on = ''
        }
    }
}

// validación starts_on <= ends_on
const oblInvalid = computed(() => {
    for (const t of catalog.value) {
        const st = oblState.value[ t.id ]
        if (!st?.enabled) continue
        if (st.starts_on && st.ends_on && st.starts_on > st.ends_on) return true
    }
    return false
})

// payload
const oblForm = useForm<{ items: Array<{ template_id: number; enabled: boolean; starts_on: string | null; ends_on: string | null }> }>({
    items: [],
})
const isSavingObl = computed(() => oblForm.processing)

watch(
    () => oblState.value,
    () => {
        dirtyObl.value = JSON.stringify(buildOblPayload()) !== snapObl.value
    },
    { deep: true }
)

const canSaveObl = computed(() => {
    return !isSavingObl.value && !oblInvalid.value && dirtyObl.value
})

function saveObligations() {
    if (oblInvalid.value) {
        toast({ title: 'Error', description: 'En Cumplimiento: "Desde" no puede ser mayor que "Hasta".', variant: 'destructive' })
        return
    }
    oblForm.items = buildOblPayload()
    oblForm.post('/subscriber/company/obligations', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resetOblSnapshot(),
    })
}

// guard contra salir con cambios
function onBeforeUnload(e: BeforeUnloadEvent) {
    if (dirtyCompany.value || dirtyObl.value) {
        e.preventDefault()
        e.returnValue = ''
    }
}

onMounted(() => {
    resetCompanySnapshot()
    initOblState(true)
    lastFyeMd.value = getFyeMd()
    window.addEventListener('beforeunload', onBeforeUnload)
})
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload)
})
</script>

<template>

    <Head title="Empresa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header / Tabs -->
            <SectionCard title="Empresa" description="Company + Perfil fiscal (1:1) + DGII + Cumplimiento">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-muted-foreground">
                        <div>
                            <span class="font-medium text-foreground">{{ props.company.name }}</span>
                            <span class="ml-2 text-xs text-muted-foreground">slug: {{ props.company.slug }}</span>
                        </div>
                        <div class="text-xs text-muted-foreground mt-1">
                            Guarda Empresa y Cumplimiento por separado. (Calendario se materializa en obligation_instances)
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" :class="tab === 'company' ? 'bg-foreground text-background' : 'bg-background'" @click="tab = 'company'">
                            Empresa
                        </Button>
                        <Button variant="outline" :class="tab === 'tax' ? 'bg-foreground text-background' : 'bg-background'" @click="tab = 'tax'">
                            Perfil fiscal
                        </Button>
                        <Button variant="outline" :class="tab === 'dgii' ? 'bg-foreground text-background' : 'bg-background'" @click="tab = 'dgii'">
                            DGII
                        </Button>
                        <Button variant="outline" :class="tab === 'compliance' ? 'bg-foreground text-background' : 'bg-background'" @click="tab = 'compliance'">
                            Cumplimiento
                            <span v-if="catalog.length" class="ml-2 text-xs opacity-80">({{ enabledCount }}/{{ catalog.length }})</span>
                        </Button>
                    </div>
                </div>
            </SectionCard>

            <!-- TAB: Company -->
            <SectionCard v-if="tab === 'company'" title="Datos de empresa" description="Campos principales de companies">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1 md:col-span-2">
                        <Label class="text-sm font-medium">Slug (solo lectura)</Label>
                        <input :value="props.company.slug" type="text" disabled class="w-full rounded-md border px-3 py-2 text-sm bg-muted/40" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Nombre</label>
                        <input v-model="form.company_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Nombre de la empresa" />
                        <div v-if="fieldError('company_name')" class="text-xs text-rose-600">{{ fieldError('company_name') }}</div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Moneda</label>
                        <input v-model="form.company_currency" type="text" maxlength="3" class="w-full rounded-md border px-3 py-2 text-sm bg-background uppercase" placeholder="USD" />
                        <div v-if="fieldError('company_currency')" class="text-xs text-rose-600">{{ fieldError('company_currency') }}</div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Zona horaria</label>
                        <input v-model="form.company_timezone" type="text" disabled class="w-full rounded-md border px-3 py-2 text-sm bg-muted/40" placeholder="America/Santo_Domingo" />
                        <div v-if="fieldError('company_timezone')" class="text-xs text-rose-600">{{ fieldError('company_timezone') }}</div>
                    </div>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <input id="company_active" v-model="form.company_active" type="checkbox" class="h-4 w-4" />
                        <label for="company_active" class="text-sm">Empresa activa</label>
                    </div>
                </div>
            </SectionCard>

            <!-- TAB: Tax -->
            <SectionCard v-if="tab === 'tax'" title="Perfil fiscal" description="Datos fiscales (company_tax_profiles) y configuración base para obligaciones.">
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Identidad fiscal -->
                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold">Identidad fiscal</h3>
                            <button type="button" class="text-xs text-muted-foreground hover:underline" @click="copyCompanyNameToLegal">
                                Copiar desde Empresa
                            </button>
                        </div>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div class="space-y-1 md:col-span-2">
                                <label class="text-sm font-medium">Razón social</label>
                                <input v-model="form.legal_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Razón social registrada" />
                                <div v-if="fieldError('legal_name')" class="text-xs text-rose-600">
                                    {{ fieldError('legal_name') }}
                                </div>
                            </div>

                            <div class="space-y-1 md:col-span-2">
                                <label class="text-sm font-medium">Nombre comercial</label>
                                <input v-model="form.trade_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Opcional" />
                                <div v-if="fieldError('trade_name')" class="text-xs text-rose-600">
                                    {{ fieldError('trade_name') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Identificadores -->
                    <div class="md:col-span-2">
                        <h3 class="text-sm font-semibold">Identificadores</h3>

                        <div class="mt-3 grid gap-4 md:grid-cols-3">
                            <div class="space-y-1">
                                <label class="text-sm font-medium">País (ISO2)</label>
                                <input v-model="form.country_code" type="text" maxlength="2" class="w-full rounded-md border px-3 py-2 text-sm bg-background uppercase" placeholder="DO" />
                                <div v-if="fieldError('country_code')" class="text-xs text-rose-600">
                                    {{ fieldError('country_code') }}
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium">Tipo ID fiscal</label>
                                <input v-model="form.tax_id_type" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background uppercase" placeholder="RNC" />
                                <div v-if="fieldError('tax_id_type')" class="text-xs text-rose-600">
                                    {{ fieldError('tax_id_type') }}
                                </div>
                            </div>

                            <div class="space-y-1 md:col-span-1">
                                <label class="text-sm font-medium">RNC / Cédula</label>
                                <input v-model="form.tax_id" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="001-1234567-8" />
                                <div v-if="fieldError('tax_id')" class="text-xs text-rose-600">
                                    {{ fieldError('tax_id') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cierre fiscal -->
                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold">Cierre fiscal</h3>
                            <span class="text-xs text-muted-foreground">Afecta vencimiento estimado del IR-2</span>
                        </div>

                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <!-- Selector -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Fiscal Year End</label>

                                <select v-model="form.fiscal_year_end_id" class="w-full rounded-md border px-3 py-2 text-sm bg-background" :disabled="fiscalYearEnds.length === 0">
                                    <option :value="null">— Seleccionar —</option>
                                    <option v-for="f in fiscalYearEnds" :key="f.id" :value="f.id">
                                        {{ f.label }}
                                    </option>
                                </select>

                                <div v-if="fiscalYearEnds.length === 0" class="text-xs text-muted-foreground">
                                    No hay catálogo de cierres fiscales para este país.
                                </div>

                                <div v-if="fieldError('fiscal_year_end_id')" class="text-xs text-rose-600">
                                    {{ fieldError('fiscal_year_end_id') }}
                                </div>

                                <p class="text-xs text-muted-foreground">
                                    Selecciona el cierre que aplica a tu empresa. Si no estás seguro, normalmente es 31 de diciembre.
                                </p>
                            </div>

                            <!-- Preview -->
                            <div class="rounded-md border bg-muted/20 p-4">
                                <div class="text-sm font-medium">Preview</div>

                                <div v-if="!fiscalYearPreview" class="mt-2 text-xs text-muted-foreground">
                                    Selecciona un cierre fiscal para ver el cálculo estimado.
                                </div>

                                <div v-else class="mt-2 space-y-2">
                                    <div class="text-xs text-muted-foreground">
                                        <div class="font-medium text-foreground">
                                            Cierre:
                                            <span class="font-mono">{{ fiscalYearPreview.close }}</span>
                                        </div>
                                        <div class="mt-1">
                                            IR-2:
                                            <span class="font-mono text-foreground">{{ fiscalYearPreview.due }}</span>
                                            <span class="opacity-80">({{ fiscalYearPreview.days }} días)</span>
                                        </div>
                                    </div>

                                    <div v-if="fiscalYearPreview.common" class="text-xs text-muted-foreground">
                                        <span class="font-medium text-foreground">Común en:</span>
                                        {{ fiscalYearPreview.common }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Impuestos -->
                    <div class="md:col-span-2">
                        <h3 class="text-sm font-semibold">Impuestos</h3>

                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <label class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm bg-background">
                                <input v-model="form.tax_exempt" type="checkbox" class="h-4 w-4" />
                                Exento de impuestos
                            </label>

                            <div class="space-y-1">
                                <label class="text-sm font-medium">ITBIS por defecto</label>
                                <input v-model="form.default_itbis_rate" type="number" step="0.001" min="0" max="100" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                                <div v-if="fieldError('default_itbis_rate')" class="text-xs text-rose-600">
                                    {{ fieldError('default_itbis_rate') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- TAB: DGII -->
            <SectionCard v-if="tab === 'dgii'" title="DGII" description="Régimen, actividad económica y configuración fiscal">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Tipo de contribuyente</label>
                        <select v-model="form.taxpayer_type" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="">—</option>
                            <option value="persona_fisica">Persona física</option>
                            <option value="persona_juridica">Persona jurídica</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Régimen</label>
                        <select v-model="form.tax_regime" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="general">General</option>
                            <option value="rst">RST</option>
                            <option value="special">Especial</option>
                        </select>
                    </div>

                    <div v-if="form.tax_regime === 'rst'" class="space-y-1">
                        <label class="text-sm font-medium">RST modalidad</label>
                        <select v-model="form.rst_modality" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="">—</option>
                            <option value="ingresos">Ingresos</option>
                            <option value="compras">Compras</option>
                        </select>
                    </div>

                    <div v-if="form.tax_regime === 'rst'" class="space-y-1">
                        <label class="text-sm font-medium">RST categoría</label>
                        <input v-model="form.rst_category" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Actividad económica principal (código)</label>
                        <input v-model="form.economic_activity_primary_code" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Actividad económica principal (nombre)</label>
                        <input v-model="form.economic_activity_primary_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>

                    <div class="md:col-span-2 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-sm font-medium">Actividades secundarias</label>
                            <button type="button" class="text-xs text-muted-foreground hover:underline" @click="addSecondaryActivity">+ Agregar</button>
                        </div>

                        <div v-if="(form.economic_activities_secondary?.length ?? 0) === 0" class="text-sm text-muted-foreground">
                            No hay actividades secundarias.
                        </div>

                        <div v-else class="space-y-2">
                            <div v-for="(row, idx) in form.economic_activities_secondary" :key="idx" class="rounded-md border p-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Código</label>
                                        <input v-model="row.code" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Nombre</label>
                                        <input v-model="row.name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                                    </div>
                                </div>

                                <div class="mt-2 flex justify-end">
                                    <button type="button" class="text-xs text-rose-600 hover:underline" @click="removeSecondaryActivity(idx)">Quitar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Modo de facturación</label>
                        <select v-model="form.invoicing_mode" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="">—</option>
                            <option value="ncf">NCF</option>
                            <option value="ecf">e-CF</option>
                            <option value="both">Ambos</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Estatus DGII</label>
                        <input v-model="form.dgii_status" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Registrado en DGII (fecha)</label>
                        <input v-model="form.dgii_registered_on" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    </div>
                </div>
            </SectionCard>

            <!-- TAB: Cumplimiento -->
            <SectionCard v-if="tab === 'compliance'" title="Cumplimiento / Calendario" description="Esto guarda tenant_obligations y dispara sync de obligation_instances">
                <div v-if="catalog.length === 0" class="text-sm text-muted-foreground">
                    No hay templates activos para este país.
                </div>

                <div v-else class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm text-muted-foreground">
                            Seleccionadas: <span class="font-medium text-foreground">{{ enabledCount }}</span> / <span class="font-medium text-foreground">{{ catalog.length }}</span>
                            <span v-if="oblInvalid" class="ml-2 text-xs text-rose-600">Hay rangos inválidos (Desde &gt; Hasta)</span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="enableDefaults">Activar defaults</button>
                            <button v-for="a in authorityOptions" :key="a.code" type="button" class="rounded-md border px-3 py-2 text-sm" @click="enableOnlyAuthority(a.code)">
                                Solo {{ a.code }}
                            </button>
                            <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="disableAllCompliance">Desactivar todo</button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <input v-model="tplQuery" type="text" class="rounded-md border px-3 py-2 text-sm bg-background" placeholder="Buscar (606, IT-1, DGII, vence...)" />
                        <select v-model="authorityFilter" class="rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="all">Todas las autoridades</option>
                            <option v-for="a in authorityOptions" :key="a.code" :value="a.code">{{ a.code }} — {{ a.name }}</option>
                        </select>
                        <label class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm bg-background">
                            <input v-model="onlyEnabled" type="checkbox" class="h-4 w-4" />
                            Solo activas
                        </label>
                    </div>

                    <div class="overflow-x-auto rounded-md border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/30">
                                <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <th class="px-3 py-2">On</th>
                                    <th class="px-3 py-2">Autoridad</th>
                                    <th class="px-3 py-2">Código</th>
                                    <th class="px-3 py-2">Nombre</th>
                                    <th class="px-3 py-2">Frecuencia</th>
                                    <th class="px-3 py-2">Regla</th>
                                    <th class="px-3 py-2">Desde</th>
                                    <th class="px-3 py-2">Hasta</th>
                                    <th class="px-3 py-2">Ref</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in filteredCatalog" :key="t.id" class="border-t">
                                    <td class="py-3 px-2">
                                        <Switch v-model="oblState[ t.id ].enabled" class="data-[state=checked]:bg-green-600" />
                                    </td>

                                    <td class="px-3 py-2">
                                        <div class="font-medium">{{ t.authority_code }}</div>
                                        <div class="text-xs text-muted-foreground">{{ t.authority_name }}</div>
                                    </td>

                                    <td class="px-3 py-2">
                                        <Badge variant="outline" class="font-mono">{{ t.code }}</Badge>
                                    </td>

                                    <td class="px-3 py-2">
                                        <div class="font-medium">{{ t.name }}</div>
                                        <div v-if="t.description" class="text-xs text-muted-foreground">{{ t.description }}</div>
                                    </td>

                                    <td class="px-3 py-2 font-mono text-xs">{{ t.frequency }}</td>
                                    <td class="px-3 py-2 text-xs">{{ ruleSummary(t) }}</td>

                                    <td class="px-3 py-2">
                                        <input v-model="oblState[ t.id ].starts_on" type="date" class="h-9 rounded-md border px-2 text-sm bg-background" :disabled="!oblState[ t.id ].enabled" />
                                    </td>

                                    <td class="px-3 py-2">
                                        <input v-model="oblState[ t.id ].ends_on" type="date" class="h-9 rounded-md border px-2 text-sm bg-background" :disabled="!oblState[ t.id ].enabled" />
                                    </td>

                                    <td class="px-3 py-2">
                                        <a v-if="t.official_ref_url" class="text-xs underline text-muted-foreground" :href="t.official_ref_url" target="_blank" rel="noreferrer">Ver</a>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </td>
                                </tr>

                                <tr v-if="filteredCatalog.length === 0">
                                    <td colspan="9" class="px-3 py-8 text-center text-sm text-muted-foreground">
                                        No hay resultados con los filtros actuales.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button variant="outline" :disabled="!canSaveObl" @click="saveObligations">
                            <span v-if="isSavingObl">Guardando…</span>
                            <span v-else>Guardar Cumplimiento</span>
                        </Button>
                    </div>
                </div>
            </SectionCard>

            <!-- Actions (form principal) -->
            <div class="flex justify-end gap-2">
                <Button :disabled="isSaving" @click="submitCompany">
                    <span v-if="isSaving">Guardando...</span>
                    <span v-else>Guardar Empresa</span>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>