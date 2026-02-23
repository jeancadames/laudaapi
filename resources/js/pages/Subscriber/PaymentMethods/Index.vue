<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { computed, ref, watch } from 'vue'
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

type PaymentMethod = {
    id: number
    company_id: number

    type: 'gateway' | 'bank_transfer' | 'cash' | 'check' | 'other'
    provider?: string | null
    name: string
    currency: string

    status: 'active' | 'inactive'
    mode: 'test' | 'live'
    is_default: boolean
    sort_order: number

    // bank transfer fields
    bank_name?: string | null
    bank_account_holder?: string | null
    bank_account_number?: string | null
    bank_account_type?: string | null
    bank_branch?: string | null
    bank_swift?: string | null
    bank_iban?: string | null

    config?: any | null
    instructions?: string | null

    created_at?: string
    updated_at?: string
}

const props = defineProps<{
    company: CompanyPayload
    paymentMethods: PaymentMethod[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Métodos de pago', href: '/subscriber/payment-methods' },
]

// -------------------------
// Flash toasts
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
// UI state + filters
// -------------------------
const q = ref('')
const fType = ref<'all' | PaymentMethod[ 'type' ]>('all')
const fStatus = ref<'all' | PaymentMethod[ 'status' ]>('all')
const fMode = ref<'all' | PaymentMethod[ 'mode' ]>('all')

const filtered = computed(() => {
    const term = q.value.trim().toLowerCase()
    return (props.paymentMethods ?? []).filter((m) => {
        if (fType.value !== 'all' && m.type !== fType.value) return false
        if (fStatus.value !== 'all' && m.status !== fStatus.value) return false
        if (fMode.value !== 'all' && m.mode !== fMode.value) return false

        if (!term) return true
        const hay = [
            m.name,
            m.type,
            m.provider ?? '',
            m.currency ?? '',
            m.bank_name ?? '',
            m.bank_account_holder ?? '',
            m.bank_account_number ?? '',
        ]
            .join(' ')
            .toLowerCase()

        return hay.includes(term)
    })
})

const activeCount = computed(() => (props.paymentMethods ?? []).filter((m) => m.status === 'active').length)
const defaultOne = computed(() => (props.paymentMethods ?? []).find((m) => m.is_default) ?? null)

// -------------------------
// Form create/edit
// -------------------------
const isOpen = ref(false)
const editingId = ref<number | null>(null)

function openCreate() {
    editingId.value = null
    form.reset()
    form.clearErrors()

    // defaults útiles
    form.type = 'gateway'
    form.currency = props.company?.currency ?? 'USD'
    form.status = 'active'
    form.mode = 'test'
    form.is_default = props.paymentMethods?.length ? false : true // si es el primero, default
    form.sort_order = 0
    form.name = ''
    form.provider = ''
    form.instructions = ''
    isOpen.value = true
}

function openEdit(m: PaymentMethod) {
    editingId.value = m.id
    form.reset()
    form.clearErrors()

    form.type = m.type
    form.provider = m.provider ?? ''
    form.name = m.name ?? ''
    form.currency = m.currency ?? (props.company?.currency ?? 'USD')

    form.status = m.status
    form.mode = m.mode
    form.is_default = !!m.is_default
    form.sort_order = Number.isFinite(Number(m.sort_order)) ? Number(m.sort_order) : 0

    // bank
    form.bank_name = m.bank_name ?? ''
    form.bank_account_holder = m.bank_account_holder ?? ''
    form.bank_account_number = m.bank_account_number ?? ''
    form.bank_account_type = m.bank_account_type ?? ''
    form.bank_branch = m.bank_branch ?? ''
    form.bank_swift = m.bank_swift ?? ''
    form.bank_iban = m.bank_iban ?? ''

    // misc
    form.instructions = m.instructions ?? ''
    form.config = m.config ?? null

    isOpen.value = true
}

function closeForm() {
    isOpen.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
}

const form = useForm({
    type: 'gateway' as PaymentMethod[ 'type' ],
    provider: '' as string,
    name: '' as string,
    currency: (props.company?.currency ?? 'USD') as string,

    status: 'active' as PaymentMethod[ 'status' ],
    mode: 'test' as PaymentMethod[ 'mode' ],
    is_default: false as boolean,
    sort_order: 0 as number,

    // bank fields
    bank_name: '' as string,
    bank_account_holder: '' as string,
    bank_account_number: '' as string,
    bank_account_type: '' as string,
    bank_branch: '' as string,
    bank_swift: '' as string,
    bank_iban: '' as string,

    // config
    config: null as any,
    instructions: '' as string,
})

const isSaving = computed(() => form.processing)
const isBank = computed(() => form.type === 'bank_transfer')
const isGateway = computed(() => form.type === 'gateway')

function fieldError(path: string): string | null {
    // @ts-ignore
    return (form.errors as any)?.[ path ] ?? null
}

function submit() {
    const payload: any = {
        type: form.type,
        provider: form.provider || null,
        name: form.name,
        currency: form.currency,

        status: form.status,
        mode: form.mode,
        is_default: form.is_default,
        sort_order: form.sort_order,

        instructions: form.instructions || null,
        config: form.config ?? null,
    }

    if (form.type === 'bank_transfer') {
        payload.bank_name = form.bank_name || null
        payload.bank_account_holder = form.bank_account_holder || null
        payload.bank_account_number = form.bank_account_number || null
        payload.bank_account_type = form.bank_account_type || null
        payload.bank_branch = form.bank_branch || null
        payload.bank_swift = form.bank_swift || null
        payload.bank_iban = form.bank_iban || null
    }

    // NOTA: credentials no lo estamos manejando aún por UI (por seguridad).
    // Cuando lo implementes, agrega campos y envía "credentials" como objeto.

    if (editingId.value) {
        router.patch(`/subscriber/payment-methods/${editingId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeForm()
            },
        })
    } else {
        router.post('/subscriber/payment-methods', payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeForm()
            },
        })
    }
}

function destroyMethod(m: PaymentMethod) {
    const ok = window.confirm(`¿Eliminar el método "${m.name}"?`)
    if (!ok) return

    router.delete(`/subscriber/payment-methods/${m.id}`, {
        preserveScroll: true,
    })
}

// -------------------------
// helpers UI
// -------------------------
function typeLabel(t: PaymentMethod[ 'type' ]) {
    if (t === 'gateway') return 'Gateway'
    if (t === 'bank_transfer') return 'Transferencia'
    if (t === 'cash') return 'Efectivo'
    if (t === 'check') return 'Cheque'
    return 'Otro'
}

function statusBadgeVariant(s: PaymentMethod[ 'status' ]) {
    return s === 'active' ? 'secondary' : 'destructive'
}

function modeBadgeVariant(m: PaymentMethod[ 'mode' ]) {
    return m === 'live' ? 'secondary' : 'secondary'
}

const providerPresets = [
    { value: '', label: '—' },
    { value: 'azul', label: 'Banco Popular Azul' },
    { value: 'visanet', label: 'VisaNet' },
    { value: 'cardnet', label: 'CardNet' },
    { value: 'mio', label: 'MIO' },
    { value: 'stripe', label: 'Stripe' },
]
</script>

<template>

    <Head title="Métodos de pago" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <SectionCard title="Métodos de pago" description="Configura cómo vas a cobrar: gateway, transferencia bancaria u otros.">
                <div class="text-sm text-muted-foreground space-y-1">
                    <div>
                        <span class="font-medium text-foreground">{{ props.company.name }}</span>
                        <span class="text-muted-foreground"> · {{ props.company.currency }} · {{ props.company.timezone }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-2">
                        <Badge variant="secondary">Activos: {{ activeCount }}</Badge>
                        <Badge v-if="defaultOne" variant="secondary">Default: {{ defaultOne.name }}</Badge>

                        <div class="ml-auto">
                            <Button size="sm" @click="openCreate">Nuevo método</Button>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- Filters -->
            <SectionCard title="Filtros" description="Búsqueda y filtros rápidos">
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="space-y-1 md:col-span-2">
                        <label class="text-sm font-medium">Buscar</label>
                        <input v-model="q" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Nombre, provider, banco, cuenta..." />
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Tipo</label>
                        <select v-model="fType" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="all">Todos</option>
                            <option value="gateway">Gateway</option>
                            <option value="bank_transfer">Transferencia</option>
                            <option value="cash">Efectivo</option>
                            <option value="check">Cheque</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Estado</label>
                        <select v-model="fStatus" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="all">Todos</option>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Modo</label>
                        <select v-model="fMode" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="all">Todos</option>
                            <option value="test">Test</option>
                            <option value="live">Live</option>
                        </select>
                    </div>
                </div>
            </SectionCard>

            <!-- Create/Edit form -->
            <SectionCard v-if="isOpen" :title="editingId ? 'Editar método' : 'Nuevo método'" description="Guarda gateway o transferencia. El default es único por empresa.">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Tipo</label>
                        <select v-model="form.type" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="gateway">Gateway</option>
                            <option value="bank_transfer">Transferencia bancaria</option>
                            <option value="cash">Efectivo</option>
                            <option value="check">Cheque</option>
                            <option value="other">Otro</option>
                        </select>
                        <div v-if="fieldError('type')" class="text-xs text-rose-600">{{ fieldError('type') }}</div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Nombre (interno)</label>
                        <input v-model="form.name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: Azul - Tarjeta / Banco Popular - Transferencia" />
                        <div v-if="fieldError('name')" class="text-xs text-rose-600">{{ fieldError('name') }}</div>
                    </div>

                    <div class="space-y-1" v-if="isGateway">
                        <label class="text-sm font-medium">Provider</label>
                        <select v-model="form.provider" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option v-for="p in providerPresets" :key="p.value" :value="p.value">
                                {{ p.label }}
                            </option>
                        </select>
                        <div class="text-xs text-muted-foreground mt-1">
                            Si necesitas uno custom, puedes escribirlo abajo.
                        </div>
                        <input v-model="form.provider" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background mt-2" placeholder="azul / visanet / cardnet / mio / stripe" />
                        <div v-if="fieldError('provider')" class="text-xs text-rose-600">{{ fieldError('provider') }}</div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Moneda</label>
                        <input v-model="form.currency" type="text" maxlength="3" class="w-full rounded-md border px-3 py-2 text-sm bg-background uppercase" placeholder="USD" />
                        <div v-if="fieldError('currency')" class="text-xs text-rose-600">{{ fieldError('currency') }}</div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Estado</label>
                        <select v-model="form.status" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div v-if="fieldError('status')" class="text-xs text-rose-600">{{ fieldError('status') }}</div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Modo</label>
                        <select v-model="form.mode" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="test">Test</option>
                            <option value="live">Live</option>
                        </select>
                        <div v-if="fieldError('mode')" class="text-xs text-rose-600">{{ fieldError('mode') }}</div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Orden</label>
                        <input v-model="form.sort_order" type="number" min="0" step="1" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="0" />
                        <div v-if="fieldError('sort_order')" class="text-xs text-rose-600">{{ fieldError('sort_order') }}</div>
                    </div>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <input id="is_default" v-model="form.is_default" type="checkbox" class="h-4 w-4" />
                        <label for="is_default" class="text-sm">Marcar como default</label>
                        <div v-if="fieldError('is_default')" class="text-xs text-rose-600">{{ fieldError('is_default') }}</div>
                    </div>
                </div>

                <!-- Bank transfer fields -->
                <div v-if="isBank" class="mt-6">
                    <div class="text-sm font-semibold mb-2">Datos bancarios</div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-sm font-medium">Banco</label>
                            <input v-model="form.bank_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                            <div v-if="fieldError('bank_name')" class="text-xs text-rose-600">{{ fieldError('bank_name') }}</div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium">Titular</label>
                            <input v-model="form.bank_account_holder" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                            <div v-if="fieldError('bank_account_holder')" class="text-xs text-rose-600">{{ fieldError('bank_account_holder') }}</div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium">Número de cuenta</label>
                            <input v-model="form.bank_account_number" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                            <div v-if="fieldError('bank_account_number')" class="text-xs text-rose-600">{{ fieldError('bank_account_number') }}</div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium">Tipo de cuenta</label>
                            <input v-model="form.bank_account_type" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ahorro / Corriente" />
                            <div v-if="fieldError('bank_account_type')" class="text-xs text-rose-600">{{ fieldError('bank_account_type') }}</div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium">Sucursal</label>
                            <input v-model="form.bank_branch" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                            <div v-if="fieldError('bank_branch')" class="text-xs text-rose-600">{{ fieldError('bank_branch') }}</div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium">SWIFT</label>
                            <input v-model="form.bank_swift" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                            <div v-if="fieldError('bank_swift')" class="text-xs text-rose-600">{{ fieldError('bank_swift') }}</div>
                        </div>

                        <div class="space-y-1 md:col-span-2">
                            <label class="text-sm font-medium">IBAN</label>
                            <input v-model="form.bank_iban" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                            <div v-if="fieldError('bank_iban')" class="text-xs text-rose-600">{{ fieldError('bank_iban') }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 space-y-1">
                    <label class="text-sm font-medium">Instrucciones (opcional)</label>
                    <textarea v-model="form.instructions" rows="4" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: Enviar comprobante al correo billing@..., referencia..., etc." />
                    <div v-if="fieldError('instructions')" class="text-xs text-rose-600">{{ fieldError('instructions') }}</div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <Button variant="outline" :disabled="isSaving" @click="closeForm">Cancelar</Button>
                    <Button :disabled="isSaving" @click="submit">
                        <span v-if="isSaving">Guardando...</span>
                        <span v-else>{{ editingId ? 'Actualizar' : 'Crear' }}</span>
                    </Button>
                </div>
            </SectionCard>

            <!-- List -->
            <SectionCard title="Listado" description="Tus métodos configurados (por empresa)">
                <div v-if="filtered.length === 0" class="text-sm text-muted-foreground">
                    No hay métodos de pago para mostrar.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="m in filtered" :key="m.id" class="rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold truncate">
                                    {{ m.name }}
                                </div>

                                <div class="text-xs text-muted-foreground mt-1">
                                    {{ typeLabel(m.type) }}
                                    <span v-if="m.provider"> · {{ m.provider }}</span>
                                    · {{ m.currency }}
                                </div>

                                <div v-if="m.type === 'bank_transfer' && (m.bank_name || m.bank_account_number)" class="text-xs text-muted-foreground mt-2 space-y-1">
                                    <div v-if="m.bank_name">Banco: {{ m.bank_name }}</div>
                                    <div v-if="m.bank_account_holder">Titular: {{ m.bank_account_holder }}</div>
                                    <div v-if="m.bank_account_number">Cuenta: {{ m.bank_account_number }}</div>
                                </div>

                                <div v-if="m.instructions" class="text-xs text-muted-foreground mt-2 line-clamp-2">
                                    {{ m.instructions }}
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-2">
                                <Badge :variant="statusBadgeVariant(m.status)">
                                    {{ m.status === 'active' ? 'Activo' : 'Inactivo' }}
                                </Badge>

                                <Badge variant="secondary">
                                    {{ m.mode === 'live' ? 'Live' : 'Test' }}
                                </Badge>

                                <Badge v-if="m.is_default" variant="secondary">
                                    Default
                                </Badge>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <div class="text-xs text-muted-foreground">
                                Orden: {{ m.sort_order ?? 0 }}
                            </div>

                            <div class="flex gap-2">
                                <Button size="sm" variant="outline" @click="openEdit(m)">Editar</Button>
                                <Button size="sm" variant="destructive" @click="destroyMethod(m)">Eliminar</Button>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
