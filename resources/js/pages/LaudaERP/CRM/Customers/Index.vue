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

type CustomerRow = {
    id: number
    type: string
    name: string
    business_name: string | null
    document_type: string | null
    document_number: string | null
    email: string | null
    phone: string | null
    mobile: string | null
    industry: string | null
    source: string | null
    status: string
    city: string | null
    region: string | null
    country: string | null
    notes: string | null
    assigned_user_id: number | null
    assigned_user_name: string | null
    created_at: string | null
}

type PaginatedItems = {
    data: CustomerRow[]
    links: Array<{
        url: string | null
        label: string
        active: boolean
    }>
}

const props = defineProps<{
    items: PaginatedItems
    filters: {
        search: string
        status: string
    }
    stats: {
        total: number
        active: number
        inactive: number
    }
}>()

const showModal = ref(false)
const editingId = ref<number | null>(null)

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? 'active')

const form = useForm({
    type: 'company',
    name: '',
    business_name: '',
    document_type: 'rnc',
    document_number: '',
    email: '',
    phone: '',
    mobile: '',
    industry: '',
    source: '',
    status: 'active',
    address: '',
    city: '',
    region: '',
    country: 'DO',
    assigned_user_id: null as number | null,
    notes: '',
})

const modalTitle = computed(() =>
    editingId.value ? 'Editar cliente' : 'Nuevo cliente'
)

function applyFilters() {
    router.get(
        '/erp/crm/customers',
        {
            search: search.value,
            status: status.value,
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
    form.status = 'active'
    form.country = 'DO'
    editingId.value = null
}

function openCreate() {
    resetForm()
    showModal.value = true
}

function openEdit(item: CustomerRow) {
    editingId.value = item.id

    form.type = item.type || 'company'
    form.name = item.name || ''
    form.business_name = item.business_name || ''
    form.document_type = item.document_type || 'rnc'
    form.document_number = item.document_number || ''
    form.email = item.email || ''
    form.phone = item.phone || ''
    form.mobile = item.mobile || ''
    form.industry = item.industry || ''
    form.source = item.source || ''
    form.status = item.status || 'active'
    form.address = ''
    form.city = item.city || ''
    form.region = item.region || ''
    form.country = item.country || 'DO'
    form.assigned_user_id = item.assigned_user_id
    form.notes = item.notes || ''

    showModal.value = true
}

function submit() {
    if (editingId.value) {
        form.put(`/erp/crm/customers/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
                resetForm()
            },
        })
        return
    }

    form.post('/erp/crm/customers', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            resetForm()
        },
    })
}

function destroyItem(item: CustomerRow) {
    if (!confirm(`¿Archivar cliente "${item.name}"?`)) return

    router.delete(`/erp/crm/customers/${item.id}`, {
        preserveScroll: true,
    })
}

function statusBadgeClass(value: string) {
    if (value === 'active') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'inactive') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return 'bg-slate-700 text-white hover:bg-slate-700'
}
</script>

<template>

    <Head title="CRM · Clientes" />

    <CrmLayout title="Clientes" description="Gestiona cuentas, empresas y clientes del CRM.">
        <section class="grid gap-4 md:grid-cols-3">
            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Total</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.total }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Activos</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.active }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Inactivos</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.inactive }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <Card class="rounded-2xl">
            <CardHeader>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <CardTitle>Listado de clientes</CardTitle>
                        <CardDescription>
                            Busca, filtra y administra cuentas comerciales.
                        </CardDescription>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <div class="min-w-55">
                            <Input v-model="search" placeholder="Buscar por nombre, documento, email..." @keyup.enter="applyFilters" />
                        </div>

                        <Select v-model="status" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todos" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="active">Activos</SelectItem>
                                    <SelectItem value="inactive">Inactivos</SelectItem>
                                    <SelectItem value="archived">Archivados</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Button variant="outline" @click="applyFilters">
                            Filtrar
                        </Button>

                        <Button @click="openCreate">
                            Nuevo cliente
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left">Cliente</th>
                                <th class="px-3 py-2 text-left">Documento</th>
                                <th class="px-3 py-2 text-left">Contacto</th>
                                <th class="px-3 py-2 text-left">Estado</th>
                                <th class="px-3 py-2 text-left">Asignado</th>
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
                                    <Badge variant="secondary" :class="statusBadgeClass(item.status)" class="capitalize">
                                        {{ item.status }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.assigned_user_name || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <Button variant="outline" size="sm" as-child>
                                            <Link :href="`/erp/crm/customers/${item.id}`">
                                                Ver
                                            </Link>
                                        </Button>

                                        <Button variant="outline" size="sm" @click="openEdit(item)">
                                            Editar
                                        </Button>

                                        <Button variant="outline" size="sm" @click="destroyItem(item)">
                                            Archivar
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.items.data.length === 0" class="border-t">
                                <td colspan="6" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                    No hay clientes registrados todavía.
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
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                    <DialogDescription>
                        Completa la información principal del cliente.
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
                                    <SelectItem value="active">Activo</SelectItem>
                                    <SelectItem value="inactive">Inactivo</SelectItem>
                                    <SelectItem value="archived">Archivado</SelectItem>
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
                        <Label>Industria</Label>
                        <Input v-model="form.industry" />
                        <p v-if="form.errors.industry" class="text-xs text-destructive">{{ form.errors.industry }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Origen</Label>
                        <Input v-model="form.source" />
                        <p v-if="form.errors.source" class="text-xs text-destructive">{{ form.errors.source }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Ciudad</Label>
                        <Input v-model="form.city" />
                        <p v-if="form.errors.city" class="text-xs text-destructive">{{ form.errors.city }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Región</Label>
                        <Input v-model="form.region" />
                        <p v-if="form.errors.region" class="text-xs text-destructive">{{ form.errors.region }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>País</Label>
                        <Input v-model="form.country" maxlength="2" />
                        <p v-if="form.errors.country" class="text-xs text-destructive">{{ form.errors.country }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Dirección</Label>
                        <Textarea v-model="form.address" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.address" class="text-xs text-destructive">{{ form.errors.address }}</p>
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
    </CrmLayout>
</template>