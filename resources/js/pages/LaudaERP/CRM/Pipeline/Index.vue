<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import CrmLayout from '@/layouts/CrmLayout.vue'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import Select from '@/components/ui/select/Select.vue'
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue'
import SelectValue from '@/components/ui/select/SelectValue.vue'
import SelectContent from '@/components/ui/select/SelectContent.vue'
import SelectItem from '@/components/ui/select/SelectItem.vue'

type Stage = {
    key: string
    title: string
}

type OpportunityCard = {
    id: number
    title: string
    stage: string
    status: string
    amount: string | number | null
    probability: number | null
    expected_close_date: string | null
    customer_name: string | null
    lead_name: string | null
    assigned_user_name: string | null
}

type UserOption = {
    id: number
    name: string
}

const props = defineProps<{
    stages: Stage[]
    items: OpportunityCard[]
    filters: {
        assigned_user_id: number | null
    }
    users: UserOption[]
}>()

const assignedUserId = ref<number | null>(props.filters.assigned_user_id ?? null)

const grouped = computed(() => {
    return props.stages.map((stage) => ({
        ...stage,
        items: props.items.filter((item) => item.stage === stage.key),
    }))
})

function applyAssignedFilter() {
    router.get(
        '/erp/crm/pipeline',
        {
            assigned_user_id: assignedUserId.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

function move(opportunityId: number, stage: string) {
    router.post(
        `/erp/crm/pipeline/${opportunityId}/move`,
        {
            stage,
            assigned_user_id: assignedUserId.value,
        },
        {
            preserveScroll: true,
        }
    )
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
</script>

<template>

    <Head title="CRM · Pipeline" />

    <CrmLayout title="Pipeline" description="Vista kanban de oportunidades por etapa comercial.">
        <section class="flex flex-wrap items-center gap-2">
            <div class="min-w-60">
                <Select v-model="assignedUserId" @update:modelValue="applyAssignedFilter">
                    <SelectTrigger class="w-full h-10">
                        <SelectValue placeholder="Todos los responsables" />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem :value="null">
                            Todos los responsables
                        </SelectItem>

                        <SelectItem v-for="user in props.users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Button variant="outline" @click="applyAssignedFilter">
                Filtrar responsable
            </Button>
        </section>

        <section class="grid gap-4 xl:grid-cols-6">
            <Card v-for="column in grouped" :key="column.key" class="min-h-105 rounded-2xl">
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <CardTitle class="text-base">{{ column.title }}</CardTitle>
                        <Badge variant="outline">{{ column.items.length }}</Badge>
                    </div>
                    <CardDescription>
                        Etapa {{ column.title }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-3">
                    <div v-if="column.items.length === 0" class="rounded-xl border border-dashed p-4 text-xs text-muted-foreground">
                        Sin oportunidades.
                    </div>

                    <div v-for="item in column.items" :key="item.id" class="space-y-3 rounded-2xl border p-4">
                        <div class="space-y-2">
                            <div class="font-medium leading-5">
                                {{ item.title }}
                            </div>

                            <Badge variant="secondary" :class="stageBadgeClass(item.stage)" class="capitalize">
                                {{ item.stage }}
                            </Badge>
                        </div>

                        <div class="space-y-1 text-xs text-muted-foreground">
                            <div>Cliente: {{ item.customer_name || '—' }}</div>
                            <div>Lead: {{ item.lead_name || '—' }}</div>
                            <div>Monto: {{ item.amount || '—' }}</div>
                            <div>Probabilidad: {{ item.probability ?? '—' }}%</div>
                            <div>Cierre: {{ item.expected_close_date || '—' }}</div>
                            <div>Asignado: {{ item.assigned_user_name || '—' }}</div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button v-if="item.stage !== 'lead'" size="sm" variant="outline" @click="move(item.id, 'lead')">
                                Lead
                            </Button>

                            <Button v-if="item.stage !== 'qualified'" size="sm" variant="outline" @click="move(item.id, 'qualified')">
                                Qualified
                            </Button>

                            <Button v-if="item.stage !== 'proposal'" size="sm" variant="outline" @click="move(item.id, 'proposal')">
                                Proposal
                            </Button>

                            <Button v-if="item.stage !== 'negotiation'" size="sm" variant="outline" @click="move(item.id, 'negotiation')">
                                Negotiation
                            </Button>

                            <Button v-if="item.stage !== 'won'" size="sm" variant="outline" @click="move(item.id, 'won')">
                                Won
                            </Button>

                            <Button v-if="item.stage !== 'lost'" size="sm" variant="outline" @click="move(item.id, 'lost')">
                                Lost
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>
    </CrmLayout>
</template>