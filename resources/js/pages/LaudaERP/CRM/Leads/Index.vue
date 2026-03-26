<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import CrmLayout from '@/layouts/CrmLayout.vue'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog'
import Select from '@/components/ui/select/Select.vue'
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue'
import SelectValue from '@/components/ui/select/SelectValue.vue'
import SelectContent from '@/components/ui/select/SelectContent.vue'
import SelectGroup from '@/components/ui/select/SelectGroup.vue'
import SelectItem from '@/components/ui/select/SelectItem.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'

const showConvertModal = ref(false)
const convertingLeadId = ref<number | null>(null)

type LeadRow = {
    id: number
    type: string
    name: string
    business_name: string | null
    document_type: string | null
    document_number: string | null
    email: string | null
    phone: string | null
    mobile: string | null
    source: string | null
    status: string
    estimated_value: string | number | null
    score: number | null
    assigned_user_id: number | null
    assigned_user_name: string | null
    qualified_at: string | null
    converted_at: string | null
    notes: string | null
    created_at: string | null
}

type PaginatedItems = {
    data: LeadRow[]
    links: Array<{
        url: string | null
        label: string
        active: boolean
    }>
}

type UserOption = {
    id: number
    name: string
}

const props = defineProps<{
    items: PaginatedItems
    filters: {
        search: string
        status: string
        assigned_user_id: number | null
    }
    stats: {
        total: number
        new: number
        qualified: number
        converted: number
    }
    users: UserOption[]
}>()

const showModal = ref(false)
const editingId = ref<number | null>(null)

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? 'new')
const assignedUserId = ref<number | null>(props.filters.assigned_user_id ?? null)

const form = useForm({
    type: 'company',
    name: '',
    business_name: '',
    document_type: 'rnc',
    document_number: '',
    email: '',
    phone: '',
    mobile: '',
    source: '',
    status: 'new',
    estimated_value: '',
    score: '',
    assigned_user_id: null as number | null,
    notes: '',
})

const modalTitle = computed(() =>
    editingId.value ? 'Editar lead' : 'Nuevo lead'
)

function applyFilters() {
    router.get(
        '/erp/crm/leads',
        {
            search: search.value,
            status: status.value,
            assigned_user_id: assignedUserId.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

function resetForm() {
    form.reset()
    form.type = 'company'
    form.document_type = 'rnc'
    form.status = 'new'
    editingId.value = null
}

function openCreate() {
    resetForm()
    showModal.value = true
}

function openEdit(item: LeadRow) {
    editingId.value = item.id
    form.type = item.type || 'company'
    form.name = item.name || ''
    form.business_name = item.business_name || ''
    form.document_type = item.document_type || 'rnc'
    form.document_number = item.document_number || ''
    form.email = item.email || ''
    form.phone = item.phone || ''
    form.mobile = item.mobile || ''
    form.source = item.source || ''
    form.status = item.status || 'new'
    form.estimated_value = item.estimated_value?.toString() || ''
    form.score = item.score?.toString() || ''
    form.assigned_user_id = item.assigned_user_id
    form.notes = item.notes || ''
    showModal.value = true
}

function submit() {
    if (editingId.value) {
        form.put(`/erp/crm/leads/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
                resetForm()
            },
        })
        return
    }

    form.post('/erp/crm/leads', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            resetForm()
        },
    })
}

function destroyItem(item: LeadRow) {
    if (!confirm(`¿Archivar lead "${item.name}"?`)) return

    router.delete(`/erp/crm/leads/${item.id}`, {
        preserveScroll: true,
    })
}

function statusBadgeClass(value: string) {
    if (value === 'new') return 'bg-slate-700 text-white hover:bg-slate-700'
    if (value === 'qualified') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'converted') return 'bg-blue-600 text-white hover:bg-blue-600'
    if (value === 'unqualified') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    if (value === 'lost') return 'bg-red-600 text-white hover:bg-red-600'
    return ''
}

const convertForm = useForm<{
    create_customer: boolean
    create_opportunity: boolean
    customer_name: string
    opportunity_title: string
    opportunity_amount: string
    convert?: string
}>({
    create_customer: true,
    create_opportunity: true,
    customer_name: '',
    opportunity_title: '',
    opportunity_amount: '',
})

function openConvert(item: LeadRow) {
    convertingLeadId.value = item.id
    convertForm.reset()
    convertForm.create_customer = true
    convertForm.create_opportunity = true
    convertForm.customer_name = item.name || ''
    convertForm.opportunity_title = `Oportunidad - ${item.name || ''}`
    convertForm.opportunity_amount = item.estimated_value?.toString() || ''
    showConvertModal.value = true
}

function submitConvert() {
    if (!convertingLeadId.value) return

    convertForm.post(`/erp/crm/leads/${convertingLeadId.value}/convert`, {
        preserveScroll: true,
        onSuccess: () => {
            showConvertModal.value = false
            convertingLeadId.value = null
            convertForm.reset()
        },
    })
}
</script>

<template>

    <Head title="CRM · Leads" />

    <CrmLayout title="Leads" description="Gestiona prospectos, origen comercial y estado inicial del pipeline.">
        <section class="grid gap-4 md:grid-cols-4">
            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Total</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.total }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Nuevos</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.new }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Calificados</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.qualified }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Convertidos</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.converted }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <Card class="rounded-2xl">
            <CardHeader>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <CardTitle>Listado de leads</CardTitle>
                        <CardDescription>
                            Busca, filtra y administra prospectos comerciales.
                        </CardDescription>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <div class="min-w-[220px]">
                            <Input v-model="search" placeholder="Buscar por nombre, documento, email..." @keyup.enter="applyFilters" />
                        </div>

                        <Select v-model="status" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-[180px] rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todos" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="new">Nuevos</SelectItem>
                                    <SelectItem value="qualified">Calificados</SelectItem>
                                    <SelectItem value="unqualified">No calificados</SelectItem>
                                    <SelectItem value="converted">Convertidos</SelectItem>
                                    <SelectItem value="lost">Perdidos</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select v-model="assignedUserId" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-[220px] rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todos los responsables" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="null">Todos los responsables</SelectItem>
                                    <SelectItem v-for="user in props.users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Button variant="outline" @click="applyFilters">
                            Filtrar
                        </Button>

                        <Button @click="openCreate">
                            Nuevo lead
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left">Lead</th>
                                <th class="px-3 py-2 text-left">Documento</th>
                                <th class="px-3 py-2 text-left">Contacto</th>
                                <th class="px-3 py-2 text-left">Origen</th>
                                <th class="px-3 py-2 text-left">Responsable</th>
                                <th class="px-3 py-2 text-left">Valor / Score</th>
                                <th class="px-3 py-2 text-left">Estado</th>
                                <th class="px-3 py-2 text-left">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="item in props.items.data" :key="item.id" class="border-t">
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium">{{ item.name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.business_name || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div>{{ item.document_type || '—' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.document_number || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div>{{ item.email || '—' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.phone || item.mobile || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.source || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.assigned_user_name || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div>{{ item.estimated_value || '—' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        Score: {{ item.score ?? '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <Badge variant="secondary" :class="statusBadgeClass(item.status)" class="capitalize">
                                        {{ item.status }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <Button variant="outline" size="sm" @click="openEdit(item)">
                                            Editar
                                        </Button>

                                        <Button variant="outline" size="sm" @click="destroyItem(item)">
                                            Archivar
                                        </Button>

                                        <Button v-if="item.status !== 'converted'" variant="outline" size="sm" @click="openConvert(item)">
                                            Convertir
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.items.data.length === 0" class="border-t">
                                <td colspan="8" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                    No hay leads registrados todavía.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Link v-for="(link, index) in props.items.links" :key="index" :href="link.url || '#'" class="rounded-md border px-3 py-2 text-sm" :class="[
                        link.active ? 'bg-primary text-primary-foreground border-primary' : '',
                        !link.url ? 'pointer-events-none opacity-50' : 'hover:bg-muted',
                    ]" v-html="link.label" />
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="showModal">
            <DialogContent class="h-11/12 overflow-y-scroll sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                    <DialogDescription>
                        Completa la información principal del lead.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Tipo</Label>
                        <Select v-model="form.type">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona el tipo" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="company">Empresa</SelectItem>
                                    <SelectItem value="individual">Individual</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.type" class="text-xs text-destructive">{{ form.errors.type }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Estado</Label>
                        <Select v-model="form.status">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona el estado" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="new">Nuevo</SelectItem>
                                    <SelectItem value="qualified">Calificado</SelectItem>
                                    <SelectItem value="unqualified">No calificado</SelectItem>
                                    <SelectItem value="converted">Convertido</SelectItem>
                                    <SelectItem value="lost">Perdido</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.status" class="text-xs text-destructive">{{ form.errors.status }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Nombre</Label>
                        <Input v-model="form.name" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Razón social</Label>
                        <Input v-model="form.business_name" />
                        <p v-if="form.errors.business_name" class="text-xs text-destructive">{{ form.errors.business_name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Tipo documento</Label>
                        <Select v-model="form.document_type">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Tipo de documento" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="rnc">RNC</SelectItem>
                                    <SelectItem value="cedula">Cédula</SelectItem>
                                    <SelectItem value="passport">Pasaporte</SelectItem>
                                    <SelectItem value="other">Otro</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.document_type" class="text-xs text-destructive">{{ form.errors.document_type }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Número documento</Label>
                        <Input v-model="form.document_number" />
                        <p v-if="form.errors.document_number" class="text-xs text-destructive">{{ form.errors.document_number }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Email</Label>
                        <Input v-model="form.email" type="email" />
                        <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Teléfono</Label>
                        <Input v-model="form.phone" />
                        <p v-if="form.errors.phone" class="text-xs text-destructive">{{ form.errors.phone }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Móvil</Label>
                        <Input v-model="form.mobile" />
                        <p v-if="form.errors.mobile" class="text-xs text-destructive">{{ form.errors.mobile }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Origen</Label>
                        <Input v-model="form.source" />
                        <p v-if="form.errors.source" class="text-xs text-destructive">{{ form.errors.source }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Valor estimado</Label>
                        <Input v-model="form.estimated_value" type="number" min="0" step="0.01" />
                        <p v-if="form.errors.estimated_value" class="text-xs text-destructive">{{ form.errors.estimated_value }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Score</Label>
                        <Input v-model="form.score" type="number" min="0" max="100" />
                        <p v-if="form.errors.score" class="text-xs text-destructive">{{ form.errors.score }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Responsable</Label>
                        <Select v-model="form.assigned_user_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona un responsable" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="user in props.users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.assigned_user_id" class="text-xs text-destructive">{{ form.errors.assigned_user_id }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Notas</Label>
                        <Textarea v-model="form.notes" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.notes" class="text-xs text-destructive">{{ form.errors.notes }}</p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="showModal = false">
                        Cancelar
                    </Button>

                    <Button :disabled="form.processing" @click="submit">
                        {{ form.processing ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showConvertModal">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Convertir lead</DialogTitle>
                    <DialogDescription>
                        Convierte este lead en cliente y/o oportunidad comercial.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="convertForm.create_customer" type="checkbox" class="h-4 w-4" />
                        <span>Crear cliente</span>
                    </label>

                    <div class="space-y-2">
                        <Label>Nombre del cliente</Label>
                        <Input v-model="convertForm.customer_name" />
                        <p v-if="convertForm.errors.customer_name" class="text-xs text-destructive">
                            {{ convertForm.errors.customer_name }}
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="convertForm.create_opportunity" type="checkbox" class="h-4 w-4" />
                        <span>Crear oportunidad</span>
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Título oportunidad</Label>
                            <Input v-model="convertForm.opportunity_title" />
                            <p v-if="convertForm.errors.opportunity_title" class="text-xs text-destructive">
                                {{ convertForm.errors.opportunity_title }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>Monto estimado</Label>
                            <Input v-model="convertForm.opportunity_amount" type="number" min="0" step="0.01" />
                            <p v-if="convertForm.errors.opportunity_amount" class="text-xs text-destructive">
                                {{ convertForm.errors.opportunity_amount }}
                            </p>
                        </div>
                    </div>

                    <p v-if="convertForm.errors.convert" class="text-xs text-destructive">
                        {{ convertForm.errors.convert }}
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="showConvertModal = false">
                        Cancelar
                    </Button>

                    <Button :disabled="convertForm.processing" @click="submitConvert">
                        {{ convertForm.processing ? 'Convirtiendo...' : 'Convertir' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </CrmLayout>
</template>