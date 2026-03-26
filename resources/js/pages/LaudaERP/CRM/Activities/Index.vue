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

type OptionRow = {
    id: number
    name: string
}

type UserOption = {
    id: number
    name: string
}

type ActivityRow = {
    id: number
    crm_customer_id: number | null
    crm_contact_id: number | null
    crm_lead_id: number | null
    crm_opportunity_id: number | null

    customer_name: string | null
    contact_name: string | null
    lead_name: string | null
    opportunity_title: string | null

    type: string
    title: string
    description: string | null
    status: string
    priority: string
    scheduled_at: string | null
    completed_at: string | null
    assigned_user_id: number | null
    assigned_user_name: string | null
    created_at: string | null
}

type PaginatedItems = {
    data: ActivityRow[]
    links: Array<{
        url: string | null
        label: string
        active: boolean
    }>
}

const props = defineProps<{
    items: PaginatedItems
    customers: OptionRow[]
    contacts: OptionRow[]
    leads: OptionRow[]
    opportunities: OptionRow[]
    users: UserOption[]
    filters: {
        search: string
        status: string
        type: string
        assigned_user_id: number | null
    }
    stats: {
        total: number
        pending: number
        completed: number
        urgent: number
    }
}>()

const showModal = ref(false)
const editingId = ref<number | null>(null)

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? 'pending')
const type = ref(props.filters.type ?? 'all')
const assignedUserId = ref<number | null>(props.filters.assigned_user_id ?? null)

const form = useForm({
    crm_customer_id: null as number | null,
    crm_contact_id: null as number | null,
    crm_lead_id: null as number | null,
    crm_opportunity_id: null as number | null,
    type: 'task',
    title: '',
    description: '',
    status: 'pending',
    priority: 'normal',
    scheduled_at: '',
    assigned_user_id: null as number | null,
})

const modalTitle = computed(() =>
    editingId.value ? 'Editar actividad' : 'Nueva actividad'
)

function applyFilters() {
    router.get(
        '/erp/crm/activities',
        {
            search: search.value,
            status: status.value,
            type: type.value,
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
    form.type = 'task'
    form.status = 'pending'
    form.priority = 'normal'
    editingId.value = null
}

function openCreate() {
    resetForm()
    showModal.value = true
}

function openEdit(item: ActivityRow) {
    editingId.value = item.id
    form.crm_customer_id = item.crm_customer_id
    form.crm_contact_id = item.crm_contact_id
    form.crm_lead_id = item.crm_lead_id
    form.crm_opportunity_id = item.crm_opportunity_id
    form.type = item.type || 'task'
    form.title = item.title || ''
    form.description = item.description || ''
    form.status = item.status || 'pending'
    form.priority = item.priority || 'normal'
    form.scheduled_at = item.scheduled_at || ''
    form.assigned_user_id = item.assigned_user_id
    showModal.value = true
}

function submit() {
    if (editingId.value) {
        form.put(`/erp/crm/activities/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
                resetForm()
            },
        })
        return
    }

    form.post('/erp/crm/activities', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            resetForm()
        },
    })
}

function destroyItem(item: ActivityRow) {
    if (!confirm(`¿Archivar actividad "${item.title}"?`)) return

    router.delete(`/erp/crm/activities/${item.id}`, {
        preserveScroll: true,
    })
}

function statusBadgeClass(value: string) {
    if (value === 'pending') return 'bg-slate-700 text-white hover:bg-slate-700'
    if (value === 'completed') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'cancelled') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return ''
}

function priorityBadgeClass(value: string) {
    if (value === 'low') return 'bg-slate-200 text-slate-800 hover:bg-slate-200'
    if (value === 'normal') return 'bg-blue-600 text-white hover:bg-blue-600'
    if (value === 'high') return 'bg-orange-500 text-white hover:bg-orange-500'
    if (value === 'urgent') return 'bg-red-600 text-white hover:bg-red-600'
    return ''
}
</script>

<template>

    <Head title="CRM · Actividades" />

    <CrmLayout title="Actividades" description="Gestiona tareas, llamadas, reuniones, visitas y seguimiento comercial del CRM.">
        <section class="grid gap-4 md:grid-cols-4">
            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Total</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.total }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Pendientes</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.pending }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Completadas</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.completed }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Urgentes</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.urgent }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <Card class="rounded-2xl">
            <CardHeader>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <CardTitle>Listado de actividades</CardTitle>
                        <CardDescription>
                            Busca, filtra y administra el seguimiento comercial.
                        </CardDescription>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <div class="min-w-55">
                            <Input v-model="search" placeholder="Buscar por título o descripción..." @keyup.enter="applyFilters" />
                        </div>

                        <Select v-model="type" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-45 rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todos los tipos" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Todos los tipos</SelectItem>
                                    <SelectItem value="task">Tarea</SelectItem>
                                    <SelectItem value="call">Llamada</SelectItem>
                                    <SelectItem value="meeting">Reunión</SelectItem>
                                    <SelectItem value="visit">Visita</SelectItem>
                                    <SelectItem value="email">Email</SelectItem>
                                    <SelectItem value="note">Nota</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select v-model="status" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-45 rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todos los estados" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Todos los estados</SelectItem>
                                    <SelectItem value="pending">Pendientes</SelectItem>
                                    <SelectItem value="completed">Completadas</SelectItem>
                                    <SelectItem value="cancelled">Canceladas</SelectItem>
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
                            Nueva actividad
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left">Actividad</th>
                                <th class="px-3 py-2 text-left">Relación</th>
                                <th class="px-3 py-2 text-left">Tipo</th>
                                <th class="px-3 py-2 text-left">Estado</th>
                                <th class="px-3 py-2 text-left">Prioridad</th>
                                <th class="px-3 py-2 text-left">Responsable</th>
                                <th class="px-3 py-2 text-left">Fecha</th>
                                <th class="px-3 py-2 text-left">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="item in props.items.data" :key="item.id" class="border-t">
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium">{{ item.title }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ item.description || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <div>Cliente: {{ item.customer_name || '—' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        Contacto: {{ item.contact_name || '—' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Lead: {{ item.lead_name || '—' }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Oportunidad: {{ item.opportunity_title || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top capitalize">
                                    {{ item.type }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <Badge variant="secondary" :class="statusBadgeClass(item.status)" class="capitalize">
                                        {{ item.status }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <Badge variant="secondary" :class="priorityBadgeClass(item.priority)" class="capitalize">
                                        {{ item.priority }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.assigned_user_name || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.scheduled_at || '—' }}
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
                                <td colspan="8" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                    No hay actividades registradas todavía.
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
                        Completa la información principal de la actividad.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Cliente</Label>
                        <Select v-model="form.crm_customer_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Sin cliente" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="item in props.customers" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.crm_customer_id" class="text-xs text-destructive">{{ form.errors.crm_customer_id }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Contacto</Label>
                        <Select v-model="form.crm_contact_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Sin contacto" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="item in props.contacts" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.crm_contact_id" class="text-xs text-destructive">{{ form.errors.crm_contact_id }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Lead</Label>
                        <Select v-model="form.crm_lead_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Sin lead" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="item in props.leads" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.crm_lead_id" class="text-xs text-destructive">{{ form.errors.crm_lead_id }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Oportunidad</Label>
                        <Select v-model="form.crm_opportunity_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Sin oportunidad" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="item in props.opportunities" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.crm_opportunity_id" class="text-xs text-destructive">{{ form.errors.crm_opportunity_id }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Tipo</Label>
                        <Select v-model="form.type">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona tipo" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="task">Tarea</SelectItem>
                                    <SelectItem value="call">Llamada</SelectItem>
                                    <SelectItem value="meeting">Reunión</SelectItem>
                                    <SelectItem value="visit">Visita</SelectItem>
                                    <SelectItem value="email">Email</SelectItem>
                                    <SelectItem value="note">Nota</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.type" class="text-xs text-destructive">{{ form.errors.type }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Estado</Label>
                        <Select v-model="form.status">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona estado" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="pending">Pendiente</SelectItem>
                                    <SelectItem value="completed">Completada</SelectItem>
                                    <SelectItem value="cancelled">Cancelada</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.status" class="text-xs text-destructive">{{ form.errors.status }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Prioridad</Label>
                        <Select v-model="form.priority">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona prioridad" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="low">Baja</SelectItem>
                                    <SelectItem value="normal">Normal</SelectItem>
                                    <SelectItem value="high">Alta</SelectItem>
                                    <SelectItem value="urgent">Urgente</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.priority" class="text-xs text-destructive">{{ form.errors.priority }}</p>
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

                    <div class="space-y-2">
                        <Label>Fecha programada</Label>
                        <Input v-model="form.scheduled_at" type="datetime-local" />
                        <p v-if="form.errors.scheduled_at" class="text-xs text-destructive">{{ form.errors.scheduled_at }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Título</Label>
                        <Input v-model="form.title" />
                        <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Descripción</Label>
                        <Textarea v-model="form.description" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
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