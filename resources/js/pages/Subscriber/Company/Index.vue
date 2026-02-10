<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Button } from '@/components/ui/button'
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

    // DGII extras
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
}

const props = defineProps<{
    company: CompanyPayload
    taxProfile: TaxProfilePayload
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Subscriber', href: subscriber().url },
    { title: 'Empresa', href: '/subscriber/company' },
]

// -------------------------
// Flash toasts (success/error)
// -------------------------
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

// -------------------------
// Helpers
// -------------------------
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

// -------------------------
// Tabs (simple)
// -------------------------
type TabKey = 'company' | 'tax' | 'dgii'
const tab = ref<TabKey>('company')

// -------------------------
// Form (payload plano para el backend)
// -------------------------
const form = useForm({
    // Company (companies)
    company_name: props.company?.name ?? '',
    company_currency: props.company?.currency ?? 'USD',
    company_timezone: props.company?.timezone ?? 'America/Bogota',
    // opcional: si lo habilitas en backend
    company_active: props.company?.active ?? true,

    // TaxProfile (company_tax_profiles)
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

    // DGII
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
})

const isSaving = computed(() => form.processing)

function submit() {
    // Normalizaciones suaves de UI
    form.company_currency = toCurrency3(form.company_currency)
    form.country_code = toIso2(form.country_code)
    form.tax_id_type = upper(form.tax_id_type)

    // Si NO es RST, limpia campos RST
    if (form.tax_regime !== 'rst') {
        form.rst_modality = ''
        form.rst_category = ''
    }

    // Limpia filas secundarias vacías (solo UI; backend también filtra)
    form.economic_activities_secondary = (form.economic_activities_secondary ?? []).filter((r) => {
        return normStr(r?.code) || normStr(r?.name)
    })

    form.post('/subscriber/company', { preserveScroll: true })
}

// Errors (ahora por keys planas)
function fieldError(key: string): string | null {
    // @ts-ignore
    return (form.errors as any)?.[ key ] ?? null
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

// UX: si cambian el nombre de empresa y la razón social está vacía, autocompletar suave
watch(
    () => form.company_name,
    (v) => {
        if (!normStr(form.legal_name)) form.legal_name = normStr(v)
    }
)
</script>

<template>

    <Head title="Empresa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header / Tabs -->
            <SectionCard title="Empresa" description="Company + Perfil fiscal (1:1) + DGII">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-muted-foreground">
                        <div>
                            <span class="font-medium text-foreground">{{ props.company.name }}</span>
                            <span class="ml-2 text-xs text-muted-foreground">slug: {{ props.company.slug }}</span>
                        </div>
                        <div class="text-xs text-muted-foreground mt-1">
                            Actualiza los datos fiscales para facturación y cumplimiento.
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-md border px-3 py-2 text-sm" :class="tab === 'company' ? 'bg-foreground text-background' : 'bg-background'" @click="tab = 'company'">
                            Empresa
                        </button>

                        <button type="button" class="rounded-md border px-3 py-2 text-sm" :class="tab === 'tax' ? 'bg-foreground text-background' : 'bg-background'" @click="tab = 'tax'">
                            Perfil fiscal
                        </button>

                        <button type="button" class="rounded-md border px-3 py-2 text-sm" :class="tab === 'dgii' ? 'bg-foreground text-background' : 'bg-background'" @click="tab = 'dgii'">
                            DGII
                        </button>
                    </div>
                </div>
            </SectionCard>

            <!-- TAB: Company -->
            <SectionCard v-if="tab === 'company'" title="Datos de empresa" description="Campos principales de companies">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Slug (solo lectura)</label>
                        <input :value="props.company.slug" type="text" disabled class="w-full rounded-md border px-3 py-2 text-sm bg-muted/40" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Nombre</label>
                        <input v-model="form.company_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Nombre de la empresa" />
                        <div v-if="fieldError('company_name')" class="text-xs text-rose-600">
                            {{ fieldError('company_name') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Moneda</label>
                        <input v-model="form.company_currency" type="text" maxlength="3" class="w-full rounded-md border px-3 py-2 text-sm bg-background uppercase" placeholder="USD" />
                        <div v-if="fieldError('company_currency')" class="text-xs text-rose-600">
                            {{ fieldError('company_currency') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Zona horaria</label>
                        <input v-model="form.company_timezone" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="America/Bogota" />
                        <div v-if="fieldError('company_timezone')" class="text-xs text-rose-600">
                            {{ fieldError('company_timezone') }}
                        </div>
                    </div>

                    <!-- opcional: permitir/ocultar -->
                    <div class="flex items-center gap-2 md:col-span-2">
                        <input id="company_active" v-model="form.company_active" type="checkbox" class="h-4 w-4" />
                        <label for="company_active" class="text-sm">Empresa activa</label>
                        <div v-if="fieldError('company_active')" class="text-xs text-rose-600">
                            {{ fieldError('company_active') }}
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- TAB: Tax -->
            <SectionCard v-if="tab === 'tax'" title="Perfil fiscal" description="Campos importantes de company_tax_profiles">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1 md:col-span-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-sm font-medium">Razón social</label>
                            <button type="button" class="text-xs text-muted-foreground hover:underline" @click="copyCompanyNameToLegal">
                                Copiar desde Empresa
                            </button>
                        </div>
                        <input v-model="form.legal_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Razón social" />
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

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">RNC / Cédula</label>
                        <input v-model="form.tax_id" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Opcional" />
                        <div v-if="fieldError('tax_id')" class="text-xs text-rose-600">
                            {{ fieldError('tax_id') }}
                        </div>
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Dirección línea 1</label>
                        <input v-model="form.address_line1" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Calle, número, sector..." />
                        <div v-if="fieldError('address_line1')" class="text-xs text-rose-600">
                            {{ fieldError('address_line1') }}
                        </div>
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Dirección línea 2</label>
                        <input v-model="form.address_line2" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Apartamento, edificio, referencia..." />
                        <div v-if="fieldError('address_line2')" class="text-xs text-rose-600">
                            {{ fieldError('address_line2') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Ciudad</label>
                        <input v-model="form.city" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Santo Domingo" />
                        <div v-if="fieldError('city')" class="text-xs text-rose-600">
                            {{ fieldError('city') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Provincia / Estado</label>
                        <input v-model="form.state" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Distrito Nacional" />
                        <div v-if="fieldError('state')" class="text-xs text-rose-600">
                            {{ fieldError('state') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Código postal</label>
                        <input v-model="form.postal_code" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Opcional" />
                        <div v-if="fieldError('postal_code')" class="text-xs text-rose-600">
                            {{ fieldError('postal_code') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Email de facturación</label>
                        <input v-model="form.billing_email" type="email" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="billing@empresa.com" />
                        <div v-if="fieldError('billing_email')" class="text-xs text-rose-600">
                            {{ fieldError('billing_email') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Teléfono de facturación</label>
                        <input v-model="form.billing_phone" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Opcional" />
                        <div v-if="fieldError('billing_phone')" class="text-xs text-rose-600">
                            {{ fieldError('billing_phone') }}
                        </div>
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Contacto de facturación</label>
                        <input v-model="form.billing_contact_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Nombre del contacto" />
                        <div v-if="fieldError('billing_contact_name')" class="text-xs text-rose-600">
                            {{ fieldError('billing_contact_name') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <input id="tax_exempt" v-model="form.tax_exempt" type="checkbox" class="h-4 w-4" />
                        <label for="tax_exempt" class="text-sm">Exento de impuestos</label>
                        <div v-if="fieldError('tax_exempt')" class="text-xs text-rose-600">
                            {{ fieldError('tax_exempt') }}
                        </div>
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">ITBIS por defecto</label>
                        <input v-model="form.default_itbis_rate" type="number" step="0.001" min="0" max="100" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="18.000" />
                        <div v-if="fieldError('default_itbis_rate')" class="text-xs text-rose-600">
                            {{ fieldError('default_itbis_rate') }}
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- TAB: DGII -->
            <SectionCard v-if="tab === 'dgii'" title="DGII" description="Régimen, actividad económica y configuración fiscal para calendario/cumplimiento">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Tipo de contribuyente</label>
                        <select v-model="form.taxpayer_type" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="">—</option>
                            <option value="persona_fisica">Persona física</option>
                            <option value="persona_juridica">Persona jurídica</option>
                        </select>
                        <div v-if="fieldError('taxpayer_type')" class="text-xs text-rose-600">
                            {{ fieldError('taxpayer_type') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Régimen</label>
                        <select v-model="form.tax_regime" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="general">General</option>
                            <option value="rst">RST</option>
                            <option value="special">Especial</option>
                        </select>
                        <div v-if="fieldError('tax_regime')" class="text-xs text-rose-600">
                            {{ fieldError('tax_regime') }}
                        </div>
                    </div>

                    <!-- RST only -->
                    <div v-if="form.tax_regime === 'rst'" class="space-y-1">
                        <label class="text-sm font-medium">RST modalidad</label>
                        <select v-model="form.rst_modality" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="">—</option>
                            <option value="ingresos">Ingresos</option>
                            <option value="compras">Compras</option>
                        </select>
                        <div v-if="fieldError('rst_modality')" class="text-xs text-rose-600">
                            {{ fieldError('rst_modality') }}
                        </div>
                    </div>

                    <div v-if="form.tax_regime === 'rst'" class="space-y-1">
                        <label class="text-sm font-medium">RST categoría</label>
                        <input v-model="form.rst_category" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: RST01" />
                        <div v-if="fieldError('rst_category')" class="text-xs text-rose-600">
                            {{ fieldError('rst_category') }}
                        </div>
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Actividad económica principal (código)</label>
                        <input v-model="form.economic_activity_primary_code" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: 620100" />
                        <div v-if="fieldError('economic_activity_primary_code')" class="text-xs text-rose-600">
                            {{ fieldError('economic_activity_primary_code') }}
                        </div>
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Actividad económica principal (nombre)</label>
                        <input v-model="form.economic_activity_primary_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: Desarrollo de software" />
                        <div v-if="fieldError('economic_activity_primary_name')" class="text-xs text-rose-600">
                            {{ fieldError('economic_activity_primary_name') }}
                        </div>
                    </div>

                    <!-- Secondary activities -->
                    <div class="md:col-span-2 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-sm font-medium">Actividades secundarias</label>
                            <button type="button" class="text-xs text-muted-foreground hover:underline" @click="addSecondaryActivity">
                                + Agregar
                            </button>
                        </div>

                        <div v-if="(form.economic_activities_secondary?.length ?? 0) === 0" class="text-sm text-muted-foreground">
                            No hay actividades secundarias.
                        </div>

                        <div v-else class="space-y-2">
                            <div v-for="(row, idx) in form.economic_activities_secondary" :key="idx" class="rounded-md border p-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Código</label>
                                        <input v-model="row.code" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: 471100" />
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-xs text-muted-foreground">Nombre</label>
                                        <input v-model="row.name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: Comercio al por menor" />
                                    </div>
                                </div>

                                <div class="mt-2 flex justify-end">
                                    <button type="button" class="text-xs text-rose-600 hover:underline" @click="removeSecondaryActivity(idx)">
                                        Quitar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="fieldError('economic_activities_secondary')" class="text-xs text-rose-600">
                            {{ fieldError('economic_activities_secondary') }}
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
                        <div v-if="fieldError('invoicing_mode')" class="text-xs text-rose-600">
                            {{ fieldError('invoicing_mode') }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Estatus DGII</label>
                        <input v-model="form.dgii_status" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: ACTIVO" />
                        <div v-if="fieldError('dgii_status')" class="text-xs text-rose-600">
                            {{ fieldError('dgii_status') }}
                        </div>
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Registrado en DGII (fecha)</label>
                        <input v-model="form.dgii_registered_on" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                        <div v-if="fieldError('dgii_registered_on')" class="text-xs text-rose-600">
                            {{ fieldError('dgii_registered_on') }}
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- Actions -->
            <div class="flex justify-end gap-2">
                <Button :disabled="isSaving" @click="submit">
                    <span v-if="isSaving">Guardando...</span>
                    <span v-else>Guardar</span>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
