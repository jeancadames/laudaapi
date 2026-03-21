<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
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

type CustomerPayload = {
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
    address: string | null
    city: string | null
    region: string | null
    country: string | null
    assigned_user_name: string | null
    notes: string | null
    created_at: string | null
}

type ContactRow = {
    id: number
    full_name: string | null
    position: string | null
    department: string | null
    email: string | null
    phone: string | null
    mobile: string | null
    is_primary: boolean
    status: string
}

type OpportunityRow = {
    id: number
    title: string
    stage: string
    status: string
    amount: string | number | null
    probability: number | null
    expected_close_date: string | null
    closed_at: string | null
}

type ActivityRow = {
    id: number
    type: string
    title: string
    description: string | null
    status: string
    priority: string
    scheduled_at: string | null
    completed_at: string | null
    contact_name: string | null
    lead_name: string | null
    opportunity_title: string | null
    assigned_user_name: string | null
}

type OptionRow = {
    id: number
    name: string
}

const props = defineProps<{
    customer: CustomerPayload
    stats: {
        contacts_total: number
        opportunities_total: number
        activities_total: number
        open_opportunities: number
    }
    contacts: ContactRow[]
    opportunities: OpportunityRow[]
    activities: ActivityRow[]
    contactOptions?: OptionRow[]
    opportunityOptions?: OptionRow[]
}>()

const activeTab = ref<'summary' | 'contacts' | 'opportunities' | 'activities'>('summary')

const showContactModal = ref(false)
const showOpportunityModal = ref(false)
const showActivityModal = ref(false)

const contactForm = useForm({
    crm_customer_id: props.customer.id,
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

const opportunityForm = useForm({
    crm_customer_id: props.customer.id,
    crm_lead_id: null as number | null,
    title: '',
    stage: 'lead',
    status: 'open',
    amount: '',
    probability: '0',
    expected_close_date: '',
    assigned_user_id: null as number | null,
    description: '',
    notes: '',
    loss_reason: '',
})

const activityForm = useForm({
    crm_customer_id: props.customer.id,
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

function resetContactForm() {
    contactForm.reset()
    contactForm.crm_customer_id = props.customer.id
    contactForm.status = 'active'
    contactForm.is_primary = false
}

function resetOpportunityForm() {
    opportunityForm.reset()
    opportunityForm.crm_customer_id = props.customer.id
    opportunityForm.stage = 'lead'
    opportunityForm.status = 'open'
    opportunityForm.probability = '0'
}

function resetActivityForm() {
    activityForm.reset()
    activityForm.crm_customer_id = props.customer.id
    activityForm.type = 'task'
    activityForm.status = 'pending'
    activityForm.priority = 'normal'
}

function openContactModal() {
    resetContactForm()
    showContactModal.value = true
}

function openOpportunityModal() {
    resetOpportunityForm()
    opportunityForm.title = `Oportunidad - ${props.customer.name}`
    showOpportunityModal.value = true
}

function openActivityModal() {
    resetActivityForm()
    showActivityModal.value = true
}

function submitContact() {
    contactForm.post('/erp/crm/contacts', {
        preserveScroll: true,
        onSuccess: () => {
            showContactModal.value = false
            resetContactForm()
        },
    })
}

function submitOpportunity() {
    opportunityForm.post('/erp/crm/opportunities', {
        preserveScroll: true,
        onSuccess: () => {
            showOpportunityModal.value = false
            resetOpportunityForm()
        },
    })
}

function submitActivity() {
    activityForm.post('/erp/crm/activities', {
        preserveScroll: true,
        onSuccess: () => {
            showActivityModal.value = false
            resetActivityForm()
        },
    })
}

function customerStatusClass(value: string) {
    if (value === 'active') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'inactive') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return 'bg-slate-700 text-white hover:bg-slate-700'
}

function activityStatusClass(value: string) {
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

function opportunityStageClass(value: string) {
    if (value === 'lead') return 'bg-slate-700 text-white hover:bg-slate-700'
    if (value === 'qualified') return 'bg-blue-600 text-white hover:bg-blue-600'
    if (value === 'proposal') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    if (value === 'negotiation') return 'bg-orange-500 text-white hover:bg-orange-500'
    if (value === 'won') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'lost') return 'bg-red-600 text-white hover:bg-red-600'
    return ''
}
</script>

<template>

    <Head :title="`CRM · ${props.customer.name}`" />

    <CrmLayout :title="props.customer.name" description="Vista consolidada del cliente, sus contactos, oportunidades y actividades.">
        <section class="grid gap-4 md:grid-cols-4">
            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Contactos</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.contacts_total }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Oportunidades</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.opportunities_total }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Abiertas</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.open_opportunities }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Actividades</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.activities_total }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <section class="flex flex-wrap gap-2">
            <Button @click="openContactModal">
                Nuevo contacto
            </Button>

            <Button variant="outline" @click="openOpportunityModal">
                Nueva oportunidad
            </Button>

            <Button variant="outline" @click="openActivityModal">
                Nueva actividad
            </Button>
        </section>

        <section class="flex flex-wrap gap-2">
            <Button variant="outline" :class="activeTab === 'summary' ? 'bg-primary text-primary-foreground border-primary' : ''" @click="activeTab = 'summary'">
                Resumen
            </Button>
            <Button variant="outline" :class="activeTab === 'contacts' ? 'bg-primary text-primary-foreground border-primary' : ''" @click="activeTab = 'contacts'">
                Contactos
            </Button>
            <Button variant="outline" :class="activeTab === 'opportunities' ? 'bg-primary text-primary-foreground border-primary' : ''" @click="activeTab = 'opportunities'">
                Oportunidades
            </Button>
            <Button variant="outline" :class="activeTab === 'activities' ? 'bg-primary text-primary-foreground border-primary' : ''" @click="activeTab = 'activities'">
                Actividades
            </Button>
        </section>

        <section v-if="activeTab === 'summary'">
            <Card class="rounded-2xl">
                <CardHeader>
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <CardTitle>Resumen del cliente</CardTitle>
                            <CardDescription>
                                Información comercial principal y datos de contacto.
                            </CardDescription>
                        </div>

                        <Badge variant="secondary" :class="customerStatusClass(props.customer.status)" class="capitalize">
                            {{ props.customer.status }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Nombre</div>
                            <div class="mt-1 font-medium">{{ props.customer.name }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Razón social</div>
                            <div class="mt-1 font-medium">{{ props.customer.business_name || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Tipo</div>
                            <div class="mt-1 font-medium capitalize">{{ props.customer.type }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Documento</div>
                            <div class="mt-1 font-medium">
                                {{ props.customer.document_type || '—' }} · {{ props.customer.document_number || '—' }}
                            </div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Email</div>
                            <div class="mt-1 font-medium break-all">{{ props.customer.email || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Teléfonos</div>
                            <div class="mt-1 font-medium">
                                {{ props.customer.phone || '—' }} · {{ props.customer.mobile || '—' }}
                            </div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Industria</div>
                            <div class="mt-1 font-medium">{{ props.customer.industry || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Origen</div>
                            <div class="mt-1 font-medium">{{ props.customer.source || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Asignado</div>
                            <div class="mt-1 font-medium">{{ props.customer.assigned_user_name || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4 md:col-span-2 xl:col-span-3">
                            <div class="text-xs text-muted-foreground">Dirección</div>
                            <div class="mt-1 font-medium">
                                {{ props.customer.address || '—' }}
                            </div>
                            <div class="mt-2 text-sm text-muted-foreground">
                                {{ props.customer.city || '—' }} · {{ props.customer.region || '—' }} · {{ props.customer.country || '—' }}
                            </div>
                        </div>

                        <div class="rounded-xl border p-4 md:col-span-2 xl:col-span-3">
                            <div class="text-xs text-muted-foreground">Notas</div>
                            <div class="mt-1 text-sm whitespace-pre-wrap">
                                {{ props.customer.notes || 'Sin notas.' }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section v-else-if="activeTab === 'contacts'">
            <Card class="rounded-2xl">
                <CardHeader>
                    <CardTitle>Contactos del cliente</CardTitle>
                    <CardDescription>
                        Personas relacionadas a esta cuenta comercial.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="props.contacts.length === 0" class="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                        No hay contactos asociados a este cliente.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="item in props.contacts" :key="item.id" class="rounded-2xl border p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div class="font-medium">{{ item.full_name || '—' }}</div>
                                        <Badge v-if="item.is_primary" variant="secondary">Principal</Badge>
                                    </div>

                                    <div class="mt-1 text-sm text-muted-foreground">
                                        {{ item.position || '—' }} · {{ item.department || '—' }}
                                    </div>
                                </div>

                                <div class="text-sm text-muted-foreground break-all">
                                    {{ item.email || '—' }} · {{ item.phone || item.mobile || '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section v-else-if="activeTab === 'opportunities'">
            <Card class="rounded-2xl">
                <CardHeader>
                    <CardTitle>Oportunidades del cliente</CardTitle>
                    <CardDescription>
                        Negocios abiertos, cerrados o históricos asociados a esta cuenta.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="props.opportunities.length === 0" class="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                        No hay oportunidades asociadas a este cliente.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="item in props.opportunities" :key="item.id" class="rounded-2xl border p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="font-medium">{{ item.title }}</div>

                                    <div class="flex flex-wrap gap-2">
                                        <Badge variant="secondary" :class="opportunityStageClass(item.stage)" class="capitalize">
                                            {{ item.stage }}
                                        </Badge>

                                        <Badge variant="outline" class="capitalize">
                                            {{ item.status }}
                                        </Badge>
                                    </div>
                                </div>

                                <div class="space-y-1 text-right text-sm text-muted-foreground">
                                    <div>Monto: {{ item.amount || '—' }}</div>
                                    <div>Probabilidad: {{ item.probability ?? '—' }}%</div>
                                    <div>Cierre estimado: {{ item.expected_close_date || '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section v-else-if="activeTab === 'activities'">
            <Card class="rounded-2xl">
                <CardHeader>
                    <CardTitle>Actividades del cliente</CardTitle>
                    <CardDescription>
                        Seguimiento comercial, tareas, reuniones, llamadas y visitas.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="props.activities.length === 0" class="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                        No hay actividades asociadas a este cliente.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="item in props.activities" :key="item.id" class="rounded-2xl border p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="font-medium">{{ item.title }}</div>

                                    <div class="flex flex-wrap gap-2">
                                        <Badge variant="outline" class="capitalize">
                                            {{ item.type }}
                                        </Badge>

                                        <Badge variant="secondary" :class="activityStatusClass(item.status)" class="capitalize">
                                            {{ item.status }}
                                        </Badge>

                                        <Badge variant="secondary" :class="priorityBadgeClass(item.priority)" class="capitalize">
                                            {{ item.priority }}
                                        </Badge>
                                    </div>

                                    <div class="space-y-1 text-xs text-muted-foreground">
                                        <div>Contacto: {{ item.contact_name || '—' }}</div>
                                        <div>Lead: {{ item.lead_name || '—' }}</div>
                                        <div>Oportunidad: {{ item.opportunity_title || '—' }}</div>
                                    </div>
                                </div>

                                <div class="space-y-1 text-right text-xs text-muted-foreground">
                                    <div>Programada: {{ item.scheduled_at || '—' }}</div>
                                    <div>Completada: {{ item.completed_at || '—' }}</div>
                                    <div>Asignado: {{ item.assigned_user_name || '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Dialog v-model:open="showContactModal">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Nuevo contacto</DialogTitle>
                    <DialogDescription>
                        Crea un contacto asociado a este cliente.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Nombres</Label>
                        <Input v-model="contactForm.first_name" />
                        <p v-if="contactForm.errors.first_name" class="text-xs text-destructive">{{ contactForm.errors.first_name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Apellidos</Label>
                        <Input v-model="contactForm.last_name" />
                        <p v-if="contactForm.errors.last_name" class="text-xs text-destructive">{{ contactForm.errors.last_name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Cargo</Label>
                        <Input v-model="contactForm.position" />
                    </div>

                    <div class="space-y-2">
                        <Label>Departamento</Label>
                        <Input v-model="contactForm.department" />
                    </div>

                    <div class="space-y-2">
                        <Label>Email</Label>
                        <Input v-model="contactForm.email" type="email" />
                        <p v-if="contactForm.errors.email" class="text-xs text-destructive">{{ contactForm.errors.email }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Teléfono</Label>
                        <Input v-model="contactForm.phone" />
                    </div>

                    <div class="space-y-2">
                        <Label>Móvil</Label>
                        <Input v-model="contactForm.mobile" />
                    </div>

                    <div class="space-y-2">
                        <Label>Estado</Label>
                        <Select v-model="contactForm.status">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona el estado" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="active">Activo</SelectItem>
                                    <SelectItem value="inactive">Inactivo</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="contactForm.is_primary" type="checkbox" class="h-4 w-4" />
                            <span>Marcar como contacto principal</span>
                        </label>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Notas</Label>
                        <Textarea v-model="contactForm.notes" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="showContactModal = false">
                        Cancelar
                    </Button>

                    <Button :disabled="contactForm.processing" @click="submitContact">
                        {{ contactForm.processing ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showOpportunityModal">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Nueva oportunidad</DialogTitle>
                    <DialogDescription>
                        Crea una oportunidad asociada a este cliente.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <Label>Título</Label>
                        <Input v-model="opportunityForm.title" />
                        <p v-if="opportunityForm.errors.title" class="text-xs text-destructive">{{ opportunityForm.errors.title }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Etapa</Label>
                        <Select v-model="opportunityForm.stage">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona la etapa" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="lead">Lead</SelectItem>
                                    <SelectItem value="qualified">Qualified</SelectItem>
                                    <SelectItem value="proposal">Proposal</SelectItem>
                                    <SelectItem value="negotiation">Negotiation</SelectItem>
                                    <SelectItem value="won">Won</SelectItem>
                                    <SelectItem value="lost">Lost</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Estado</Label>
                        <Select v-model="opportunityForm.status">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona el estado" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="open">Abierta</SelectItem>
                                    <SelectItem value="won">Ganada</SelectItem>
                                    <SelectItem value="lost">Perdida</SelectItem>
                                    <SelectItem value="cancelled">Cancelada</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Monto</Label>
                        <Input v-model="opportunityForm.amount" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-2">
                        <Label>Probabilidad (%)</Label>
                        <Input v-model="opportunityForm.probability" type="number" min="0" max="100" />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Fecha estimada de cierre</Label>
                        <Input v-model="opportunityForm.expected_close_date" type="date" />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Descripción</Label>
                        <Textarea v-model="opportunityForm.description" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Notas</Label>
                        <Textarea v-model="opportunityForm.notes" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="showOpportunityModal = false">
                        Cancelar
                    </Button>

                    <Button :disabled="opportunityForm.processing" @click="submitOpportunity">
                        {{ opportunityForm.processing ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showActivityModal">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Nueva actividad</DialogTitle>
                    <DialogDescription>
                        Crea una actividad asociada a este cliente.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Contacto</Label>
                        <Select v-model="activityForm.crm_contact_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Sin contacto" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="null">Sin contacto</SelectItem>

                                    <SelectItem v-for="item in (props.contactOptions ?? [])" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Oportunidad</Label>
                        <Select v-model="activityForm.crm_opportunity_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Sin oportunidad" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="null">Sin oportunidad</SelectItem>

                                    <SelectItem v-for="item in (props.opportunityOptions ?? [])" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Tipo</Label>
                        <Select v-model="activityForm.type">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona el tipo" />
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
                    </div>

                    <div class="space-y-2">
                        <Label>Estado</Label>
                        <Select v-model="activityForm.status">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona el estado" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="pending">Pendiente</SelectItem>
                                    <SelectItem value="completed">Completada</SelectItem>
                                    <SelectItem value="cancelled">Cancelada</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Prioridad</Label>
                        <Select v-model="activityForm.priority">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Selecciona la prioridad" />
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
                    </div>

                    <div class="space-y-2">
                        <Label>Fecha programada</Label>
                        <Input v-model="activityForm.scheduled_at" type="datetime-local" />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Título</Label>
                        <Input v-model="activityForm.title" />
                        <p v-if="activityForm.errors.title" class="text-xs text-destructive">{{ activityForm.errors.title }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Descripción</Label>
                        <Textarea v-model="activityForm.description" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="showActivityModal = false">
                        Cancelar
                    </Button>

                    <Button :disabled="activityForm.processing" @click="submitActivity">
                        {{ activityForm.processing ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </CrmLayout>
</template>