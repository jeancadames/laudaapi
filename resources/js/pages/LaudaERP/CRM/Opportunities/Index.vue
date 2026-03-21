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
import SelectContent from '@/components/ui/select/SelectContent.vue'
import SelectGroup from '@/components/ui/select/SelectGroup.vue'
import SelectItem from '@/components/ui/select/SelectItem.vue'
import SelectValue from '@/components/ui/select/SelectValue.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'

type OpportunityRow = {
    id: number
    crm_customer_id: number | null
    crm_lead_id: number | null
    customer_name: string | null
    lead_name: string | null
    title: string
    stage: string
    status: string
    amount: string | number | null
    probability: number | null
    expected_close_date: string | null
    closed_at: string | null
    assigned_user_id: number | null
    assigned_user_name: string | null
    description: string | null
    notes: string | null
    loss_reason: string | null
    created_at: string | null
}

type OptionRow = {
    id: number
    name: string
    business_name: string | null
}

type PaginatedItems = {
    data: OpportunityRow[]
    links: Array<{
        url: string | null
        label: string
        active: boolean
    }>
}

const props = defineProps<{
    items: PaginatedItems
    customers: OptionRow[]
    leads: OptionRow[]
    filters: {
        search: string
        stage: string
        status: string
    }
    stats: {
        total: number
        open: number
        won: number
        lost: number
    }
}>()

const showModal = ref(false)
const editingId = ref<number | null>(null)

const search = ref(props.filters.search ?? '')
const stage = ref(props.filters.stage ?? 'all')
const status = ref(props.filters.status ?? 'open')

const form = useForm({
    crm_customer_id: null as number | null,
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

const modalTitle = computed(() =>
    editingId.value ? 'Editar oportunidad' : 'Nueva oportunidad'
)

function applyFilters() {
    router.get(
        '/erp/crm/opportunities',
        {
            search: search.value,
            stage: stage.value,
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
    form.stage = 'lead'
    form.status = 'open'
    form.probability = '0'
    editingId.value = null
}

function openCreate() {
    resetForm()
    showModal.value = true
}

function openEdit(item: OpportunityRow) {
    editingId.value = item.id
    form.crm_customer_id = item.crm_customer_id
    form.crm_lead_id = item.crm_lead_id
    form.title = item.title || ''
    form.stage = item.stage || 'lead'
    form.status = item.status || 'open'
    form.amount = item.amount?.toString() || ''
    form.probability = item.probability?.toString() || '0'
    form.expected_close_date = item.expected_close_date || ''
    form.assigned_user_id = item.assigned_user_id
    form.description = item.description || ''
    form.notes = item.notes || ''
    form.loss_reason = item.loss_reason || ''
    showModal.value = true
}

function submit() {
    if (editingId.value) {
        form.put(`/erp/crm/opportunities/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false
                resetForm()
            },
        })
        return
    }

    form.post('/erp/crm/opportunities', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            resetForm()
        },
    })
}

function destroyItem(item: OpportunityRow) {
    if (!confirm(`¿Archivar oportunidad "${item.title}"?`)) return

    router.delete(`/erp/crm/opportunities/${item.id}`, {
        preserveScroll: true,
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
</script>

<template>

    <Head title="CRM · Oportunidades" />

    <CrmLayout title="Oportunidades" description="Gestiona negocios activos, etapas comerciales, monto estimado y probabilidad de cierre.">
        <section class="grid gap-4 md:grid-cols-4">
            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Total</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.total }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Abiertas</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.open }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Ganadas</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.won }}</CardTitle>
                </CardHeader>
            </Card>

            <Card class="rounded-2xl">
                <CardHeader class="pb-3">
                    <CardDescription>Perdidas</CardDescription>
                    <CardTitle class="text-3xl">{{ props.stats.lost }}</CardTitle>
                </CardHeader>
            </Card>
        </section>

        <Card class="rounded-2xl">
            <CardHeader>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <CardTitle>Listado de oportunidades</CardTitle>
                        <CardDescription>
                            Busca, filtra y administra el pipeline comercial.
                        </CardDescription>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <div class="min-w-55">
                            <Input v-model="search" placeholder="Buscar por título, descripción..." @keyup.enter="applyFilters" />
                        </div>

                        <Select v-model="stage" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todas las etapas" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Todas las etapas</SelectItem>
                                    <SelectItem value="lead">Lead</SelectItem>
                                    <SelectItem value="qualified">Qualified</SelectItem>
                                    <SelectItem value="proposal">Proposal</SelectItem>
                                    <SelectItem value="negotiation">Negotiation</SelectItem>
                                    <SelectItem value="won">Won</SelectItem>
                                    <SelectItem value="lost">Lost</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select v-model="status" @update:model-value="applyFilters">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Todos los estados" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">Todos los estados</SelectItem>
                                    <SelectItem value="open">Abiertas</SelectItem>
                                    <SelectItem value="won">Ganadas</SelectItem>
                                    <SelectItem value="lost">Perdidas</SelectItem>
                                    <SelectItem value="cancelled">Canceladas</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Button variant="outline" @click="applyFilters">
                            Filtrar
                        </Button>

                        <Button @click="openCreate">
                            Nueva oportunidad
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left">Oportunidad</th>
                                <th class="px-3 py-2 text-left">Cliente / Lead</th>
                                <th class="px-3 py-2 text-left">Etapa</th>
                                <th class="px-3 py-2 text-left">Estado</th>
                                <th class="px-3 py-2 text-left">Monto</th>
                                <th class="px-3 py-2 text-left">Prob.</th>
                                <th class="px-3 py-2 text-left">Cierre estimado</th>
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
                                    <div>{{ item.customer_name || '—' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        Lead: {{ item.lead_name || '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <Badge variant="secondary" :class="stageBadgeClass(item.stage)" class="capitalize">
                                        {{ item.stage }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    <Badge variant="secondary" :class="statusBadgeClass(item.status)" class="capitalize">
                                        {{ item.status }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.amount || '—' }}
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.probability ?? '—' }}%
                                </td>

                                <td class="px-3 py-3 align-top">
                                    {{ item.expected_close_date || '—' }}
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
                                    No hay oportunidades registradas todavía.
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
                        Completa la información principal de la oportunidad.
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
                                    <SelectItem :value="null">Sin cliente</SelectItem>

                                    <SelectItem v-for="customer in props.customers" :key="customer.id" :value="customer.id">
                                        {{ customer.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.crm_customer_id" class="text-xs text-destructive">{{ form.errors.crm_customer_id }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Lead</Label>
                        <Select v-model="form.crm_lead_id">
                            <SelectTrigger class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                <SelectValue placeholder="Sin lead" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="null">Sin lead</SelectItem>

                                    <SelectItem v-for="lead in props.leads" :key="lead.id" :value="lead.id">
                                        {{ lead.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.crm_lead_id" class="text-xs text-destructive">{{ form.errors.crm_lead_id }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Título</Label>
                        <Input v-model="form.title" />
                        <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Etapa</Label>
                        <Select v-model="form.stage">
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
                        <p v-if="form.errors.stage" class="text-xs text-destructive">{{ form.errors.stage }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Estado</Label>
                        <Select v-model="form.status">
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
                        <p v-if="form.errors.status" class="text-xs text-destructive">{{ form.errors.status }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Monto</Label>
                        <Input v-model="form.amount" type="number" min="0" step="0.01" />
                        <p v-if="form.errors.amount" class="text-xs text-destructive">{{ form.errors.amount }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Probabilidad (%)</Label>
                        <Input v-model="form.probability" type="number" min="0" max="100" />
                        <p v-if="form.errors.probability" class="text-xs text-destructive">{{ form.errors.probability }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Fecha estimada de cierre</Label>
                        <Input v-model="form.expected_close_date" type="date" />
                        <p v-if="form.errors.expected_close_date" class="text-xs text-destructive">{{ form.errors.expected_close_date }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Descripción</Label>
                        <Textarea v-model="form.description" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Notas</Label>
                        <Textarea v-model="form.notes" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.notes" class="text-xs text-destructive">{{ form.errors.notes }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label>Razón de pérdida</Label>
                        <Textarea v-model="form.loss_reason" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.loss_reason" class="text-xs text-destructive">{{ form.errors.loss_reason }}</p>
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