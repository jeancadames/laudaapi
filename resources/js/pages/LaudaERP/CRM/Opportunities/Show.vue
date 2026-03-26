<script setup lang="ts">
import { Head, router, useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import CrmLayout from '@/layouts/CrmLayout.vue'
import { useCrmAssignedUser } from '@/composables/useCrmAssignedUser'

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
import Textarea from '@/components/ui/textarea/Textarea.vue'
import Select from '@/components/ui/select/Select.vue'
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue'
import SelectValue from '@/components/ui/select/SelectValue.vue'
import SelectContent from '@/components/ui/select/SelectContent.vue'
import SelectGroup from '@/components/ui/select/SelectGroup.vue'
import SelectItem from '@/components/ui/select/SelectItem.vue'

type OpportunityPayload = {
    id: number
    crm_customer_id: number | null
    crm_lead_id: number | null
    customer_name: string | null
    customer_business_name: string | null
    lead_name: string | null
    lead_business_name: string | null
    title: string
    stage: string
    status: string
    amount: string | number | null
    probability: number | null
    expected_close_date: string | null
    closed_at: string | null
    assigned_user_name: string | null
    description: string | null
    notes: string | null
    loss_reason: string | null
    created_at: string | null
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
    customer_name: string | null
    contact_name: string | null
    lead_name: string | null
    assigned_user_name: string | null
}

type OptionRow = {
    id: number
    name: string
}

const { withAssignedUser } = useCrmAssignedUser()

const props = defineProps<{
    opportunity: OpportunityPayload
    stats: {
        activities_total: number
        pending_activities: number
        completed_activities: number
    }
    activities: ActivityRow[]
    contactOptions: OptionRow[]
}>()

const activeTab = ref<'summary' | 'activities'>('summary')
const showActivityModal = ref(false)
const showLostModal = ref(false)

const activityForm = useForm({
    crm_customer_id: props.opportunity.crm_customer_id,
    crm_contact_id: null as number | null,
    crm_lead_id: props.opportunity.crm_lead_id,
    crm_opportunity_id: props.opportunity.id,
    type: 'task',
    title: '',
    description: '',
    status: 'pending',
    priority: 'normal',
    scheduled_at: '',
    assigned_user_id: null as number | null,
})

const lostForm = useForm({
    loss_reason: props.opportunity.loss_reason || '',
})

function openActivityModal() {
    activityForm.reset()
    activityForm.crm_customer_id = props.opportunity.crm_customer_id
    activityForm.crm_lead_id = props.opportunity.crm_lead_id
    activityForm.crm_opportunity_id = props.opportunity.id
    activityForm.type = 'task'
    activityForm.status = 'pending'
    activityForm.priority = 'normal'
    activityForm.title = `Seguimiento - ${props.opportunity.title}`
    showActivityModal.value = true
}

function submitActivity() {
    activityForm.post('/erp/crm/activities', {
        preserveScroll: true,
        onSuccess: () => {
            showActivityModal.value = false
            activityForm.reset()
        },
    })
}

function markWon() {
    if (!confirm(`¿Marcar "${props.opportunity.title}" como ganada?`)) return

    router.post(`/erp/crm/opportunities/${props.opportunity.id}/mark-won`, {}, {
        preserveScroll: true,
    })
}

function openLostModal() {
    lostForm.loss_reason = props.opportunity.loss_reason || ''
    showLostModal.value = true
}

function submitLost() {
    lostForm.post(`/erp/crm/opportunities/${props.opportunity.id}/mark-lost`, {
        preserveScroll: true,
        onSuccess: () => {
            showLostModal.value = false
        },
    })
}

function stageBadgeClass(value: string) {
    if (value === 'lead') return 'bg-slate-700 text-white hover:bg-slate-700'
    if (value === 'qualified') return 'bg-blue-600 text-white hover:bg-blue-600'
    if (value === 'proposal') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    if (value === 'negotiation') return 'bg-orange-500 text-white hover:bg-orange-500'
    if (value === 'won') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'lost') return 'bg-red-600 text-white hover:bg-red-600'
    return ''
}

function statusBadgeClass(value: string) {
    if (value === 'open') return 'bg-slate-700 text-white hover:bg-slate-700'
    if (value === 'won') return 'bg-emerald-600 text-white hover:bg-emerald-600'
    if (value === 'lost') return 'bg-red-600 text-white hover:bg-red-600'
    if (value === 'cancelled') return 'bg-yellow-400 text-black hover:bg-yellow-400'
    return ''
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
</script>

<template>

    <Head :title="`CRM · ${props.opportunity.title}`" />

    <CrmLayout :title="props.opportunity.title" description="Vista consolidada de la oportunidad y su seguimiento comercial.">
        <section class="flex flex-wrap gap-2">
            <Button variant="outline" as-child>
                <Link :href="withAssignedUser('/erp/crm/opportunities')">
                    Volver a oportunidades
                </Link>
            </Button>
        </section>
        <section class="grid gap-4 md:grid-cols-3">
            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Actividades</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.activities_total }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Pendientes</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.pending_activities }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Completadas</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.completed_activities }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <section class="flex flex-wrap gap-2">
            <Button @click="openActivityModal">
                Nueva actividad
            </Button>

            <Button v-if="props.opportunity.status === 'open'" variant="outline" @click="markWon">
                Marcar ganada
            </Button>

            <Button v-if="props.opportunity.status === 'open'" variant="outline" @click="openLostModal">
                Marcar perdida
            </Button>
        </section>

        <section class="flex flex-wrap gap-2">
            <Button variant="outline" :class="activeTab === 'summary' ? 'bg-primary text-primary-foreground border-primary' : ''" @click="activeTab = 'summary'">
                Resumen
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
                            <CardTitle>Resumen de la oportunidad</CardTitle>
                            <CardDescription>
                                Estado comercial, relación con cliente y proyección.
                            </CardDescription>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Badge variant="secondary" :class="stageBadgeClass(props.opportunity.stage)" class="capitalize">
                                {{ props.opportunity.stage }}
                            </Badge>

                            <Badge variant="secondary" :class="statusBadgeClass(props.opportunity.status)" class="capitalize">
                                {{ props.opportunity.status }}
                            </Badge>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Título</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.title }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Cliente</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.customer_name || '—' }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ props.opportunity.customer_business_name || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Lead origen</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.lead_name || '—' }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ props.opportunity.lead_business_name || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Monto</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.amount || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Probabilidad</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.probability ?? '—' }}%</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Cierre estimado</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.expected_close_date || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Asignado</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.assigned_user_name || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Cerrada en</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.closed_at || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="text-xs text-muted-foreground">Creada en</div>
                            <div class="mt-1 font-medium">{{ props.opportunity.created_at || '—' }}</div>
                        </div>

                        <div class="rounded-xl border p-4 md:col-span-2 xl:col-span-3">
                            <div class="text-xs text-muted-foreground">Descripción</div>
                            <div class="mt-1 text-sm whitespace-pre-wrap">
                                {{ props.opportunity.description || 'Sin descripción.' }}
                            </div>
                        </div>

                        <div class="rounded-xl border p-4 md:col-span-2 xl:col-span-3">
                            <div class="text-xs text-muted-foreground">Notas</div>
                            <div class="mt-1 text-sm whitespace-pre-wrap">
                                {{ props.opportunity.notes || 'Sin notas.' }}
                            </div>
                        </div>

                        <div class="rounded-xl border p-4 md:col-span-2 xl:col-span-3">
                            <div class="text-xs text-muted-foreground">Razón de pérdida</div>
                            <div class="mt-1 text-sm whitespace-pre-wrap">
                                {{ props.opportunity.loss_reason || 'No aplica.' }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section v-else-if="activeTab === 'activities'">
            <Card class="rounded-2xl">
                <CardHeader>
                    <CardTitle>Timeline de actividades</CardTitle>
                    <CardDescription>
                        Seguimiento comercial asociado a esta oportunidad.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="props.activities.length === 0" class="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                        No hay actividades asociadas a esta oportunidad.
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
                                        <div>Cliente: {{ item.customer_name || '—' }}</div>
                                        <div>Contacto: {{ item.contact_name || '—' }}</div>
                                        <div>Lead: {{ item.lead_name || '—' }}</div>
                                    </div>
                                </div>

                                <div class="space-y-1 text-right text-xs text-muted-foreground">
                                    <div>Programada: {{ item.scheduled_at || '—' }}</div>
                                    <div>Completada: {{ item.completed_at || '—' }}</div>
                                    <div>Asignado: {{ item.assigned_user_name || '—' }}</div>
                                </div>
                            </div>

                            <div v-if="item.description" class="mt-3 text-sm text-muted-foreground whitespace-pre-wrap">
                                {{ item.description }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Dialog v-model:open="showActivityModal">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Nueva actividad</DialogTitle>
                    <DialogDescription>
                        Crea una actividad asociada a esta oportunidad.
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

                                    <SelectItem v-for="item in props.contactOptions" :key="item.id" :value="item.id">
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

                    <div class="space-y-2 md:col-span-2">
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

        <Dialog v-model:open="showLostModal">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Marcar oportunidad como perdida</DialogTitle>
                    <DialogDescription>
                        Registra el motivo para cerrar esta oportunidad como perdida.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2">
                    <Label>Razón de pérdida</Label>
                    <textarea v-model="lostForm.loss_reason" rows="5" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                    <p v-if="lostForm.errors.loss_reason" class="text-xs text-destructive">{{ lostForm.errors.loss_reason }}</p>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="showLostModal = false">
                        Cancelar
                    </Button>

                    <Button :disabled="lostForm.processing" @click="submitLost">
                        {{ lostForm.processing ? 'Guardando...' : 'Confirmar pérdida' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </CrmLayout>
</template>