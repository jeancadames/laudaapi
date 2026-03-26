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

type ContactRow = {
    id: number
    crm_customer_id: number | null
    customer_name: string | null
    customer_business_name: string | null
    first_name: string | null
    last_name: string | null
    full_name: string | null
    position: string | null
    department: string | null
    email: string | null
    phone: string | null
    mobile: string | null
    is_primary: boolean
    status: string
    assigned_user_id: number | null
    assigned_user_name: string | null
    notes: string | null
    created_at: string | null
}

type CustomerOption = {
    id: number
    name: string
    business_name: string | null
}

type UserOption = {
    id: number
    name: string
}

type PaginatedItems = {
    data: ContactRow[]
    links: Array<{
        url: string | null
        label: string
        active: boolean
    }>
}

const props = defineProps<{
    items: PaginatedItems
    customers: CustomerOption[]
    users: UserOption[]
    filters: {
        search: string
        status: string
        assigned_user_id: number | null
    }
    stats: {
        total: number
        active: number
        primary: number
    }
}>()

const showModal = ref(false)
const editingId = ref<number | null>(null)

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? 'active')
const assignedUserId = ref<number | null>(props.filters.assigned_user_id ?? null)

const form = useForm({
    crm_customer_id: null as number | null,
    first_name: '',
    last_name: '',
    position: '',
    department: '',
    email: '',
    phone: '',
    mobile: '',
    is_primary: false,
    status: 'active',
    assigned_user_id: null as number | null,
    notes: '',
})

const modalTitle = computed(() =>
    editingId.value ? 'Editar contacto' : 'Nuevo contacto'
)

function applyFilters() {
    router.get(
        '/erp/crm/contacts',
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
    form.status = 'active'
    form.is_primary = false
    editingId.value = null
}

function openCreate() {
    resetForm()
    showModal.value = true
}

function openEdit(item: ContactRow) {
    editingId.value = item.id
    form.crm_customer_id = item.crm_customer_id
    form.first_name = item.first_name || ''
    form.last_name = item.last_name || ''
    form.position = item.position || ''
    form.department = item.department || ''
    form.email = item.email || ''
    form.phone = item.phone || ''
    form.mobile = item.mobile || ''
    form.is_primary = item.is_primary
    form.status = item.status || 'active'
    form.assigned_user_id = item.assigned_user_id
    form.notes = item.notes || ''
    showModal.value = true
}

function submit() {
    if (editingId.value) {
        form.put(`/erp/crm/contacts/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
                resetForm()
            },
        })
        return
    }

    form.post('/erp/crm/contacts', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            resetForm()
        },
    })
}

function destroyItem(item: ContactRow) {
    if (!confirm(`¿Archivar contacto "${item.full_name || item.first_name || 'sin nombre'}"?`)) return

    router.delete(`/erp/crm/contacts/${item.id}`, {
        preserveScroll: true,
    })
}

function statusBadgeClass(value: string) {
    if (value === 'active') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'inactive') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return ''
}
</script>

<template>

    <Head title="CRM · Contactos" />

    <CrmLayout title="Contactos" description="Gestiona personas de contacto asociadas a clientes y cuentas comerciales.">
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
                    <CardDescription>Principales</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.primary }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <Card class="rounded-2xl">
            <CardHeader>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <CardTitle>Listado de contactos</CardTitle>
                        <CardDescription>
                            Busca, filtra y administra contactos del CRM.
                        </CardDescription>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <div class="min-w-55">
                            <Input v-model="search" placeholder="Buscar por nombre, email, teléfono..." @keyup.enter="applyFilters" />
                        </div>

                        <Select v-model="status" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-45 rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todos los estados" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Todos los estados</SelectItem>
                                    <SelectItem value="active">Activos</SelectItem>
                                    <SelectItem value="inactive">Inactivos</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select v-model="assignedUserId" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-55 rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todos los responsables" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
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
                            Nuevo contacto
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left">Contacto</th>
                                <th class="px-3 py-2 text-left">Cliente</th>
                                <th class="px-3 py-2 text-left">Cargo / Depto</th>
                                <th class="px-3 py-2 text-left">Contacto</th>
                                <th class="px-3 py-2 text-left">Responsable</th>
                                <th class="px-3 py-2 text-left">Estado</th>
                                <th class="px-3 py-2 text-left">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="item in props.items.data" :key="item.id" class="border-t">
                                <td class="px-3 py-3 align-top">
                                    <div class="flex items-center gap-2">
                                        <div class="font-medium">{{ item.full_name || '—' }}</div>
                                        <Badge v-if="item.is_primary" variant="secondary">
                                            Principal
                                        </Badge>
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div>{{ item.customer_name || '—' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.customer_business_name || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div>{{ item.position || '—' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.department || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div>{{ item.email || '—' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.phone || item.mobile || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.assigned_user_name || '—' }}
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
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.items.data.length === 0" class="border-t">
                                <td colspan="7" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                    No hay contactos registrados todavía.
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
                        Completa la información principal del contacto.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Cliente</Label>
                        <Select v-model="form.crm_customer_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona cliente" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="customer in props.customers" :key="customer.id" :value="customer.id">
                                        {{ customer.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.crm_customer_id" class="text-xs text-destructive">{{ form.errors.crm_customer_id }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Estado</Label>
                        <Select v-model="form.status">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona estado" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="active">Activo</SelectItem>
                                    <SelectItem value="inactive">Inactivo</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Nombres</Label>
                        <Input v-model="form.first_name" />
                        <p v-if="form.errors.first_name" class="text-xs text-destructive">{{ form.errors.first_name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Apellidos</Label>
                        <Input v-model="form.last_name" />
                        <p v-if="form.errors.last_name" class="text-xs text-destructive">{{ form.errors.last_name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Cargo</Label>
                        <Input v-model="form.position" />
                    </div>

                    <div class="space-y-2">
                        <Label>Departamento</Label>
                        <Input v-model="form.department" />
                    </div>

                    <div class="space-y-2">
                        <Label>Email</Label>
                        <Input v-model="form.email" type="email" />
                    </div>

                    <div class="space-y-2">
                        <Label>Teléfono</Label>
                        <Input v-model="form.phone" />
                    </div>

                    <div class="space-y-2">
                        <Label>Móvil</Label>
                        <Input v-model="form.mobile" />
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
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_primary" type="checkbox" class="h-4 w-4" />
                            <span>Marcar como contacto principal</span>
                        </label>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Notas</Label>
                        <Textarea v-model="form.notes" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
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