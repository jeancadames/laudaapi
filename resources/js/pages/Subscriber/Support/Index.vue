<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'
import { useToast } from '@/components/ui/toast/use-toast'

const { toast } = useToast()
const page = usePage()

/**
 * Tipos
 */
type TicketStatus = 'open' | 'pending' | 'answered' | 'closed'
type TicketPriority = 'low' | 'normal' | 'high' | 'urgent'

type SupportTicket = {
    id: number
    subject: string
    status: TicketStatus
    priority?: TicketPriority | null
    category?: string | null
    last_reply_at?: string | null
    created_at?: string | null
}

type FaqItem = {
    id: number
    question: string
    answer: string
    category?: string | null
    // útil si luego quieres destacar
    featured?: boolean
}

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline' | null

const props = defineProps<{
    faqs: FaqItem[]
    tickets: SupportTicket[]
    // opcional si quieres que el backend mande filtros actuales
    filters?: {
        q?: string | null
        status?: TicketStatus | 'all' | null
    }
}>()

/**
 * Breadcrumbs
 */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Soporte', href: '/subscriber/support' },
]

/**
 * Flash toasts
 */
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

/**
 * Helpers UI
 */
function statusLabel(st: TicketStatus): string {
    if (st === 'open') return 'Abierto'
    if (st === 'pending') return 'Pendiente'
    if (st === 'answered') return 'Respondido'
    if (st === 'closed') return 'Cerrado'
    return st
}

function statusVariant(st: TicketStatus): BadgeVariant {
    // ✅ Tipado fuerte (evita error TS)
    if (st === 'open') return 'secondary'
    if (st === 'pending') return 'outline'
    if (st === 'answered') return 'secondary'
    if (st === 'closed') return 'secondary'
    return 'secondary'
}

function priorityLabel(p?: TicketPriority | null): string {
    if (!p) return 'Normal'
    if (p === 'low') return 'Baja'
    if (p === 'normal') return 'Normal'
    if (p === 'high') return 'Alta'
    if (p === 'urgent') return 'Urgente'
    return p
}

function priorityVariant(p?: TicketPriority | null): BadgeVariant {
    if (!p || p === 'normal' || p === 'low') return 'secondary'
    if (p === 'high') return 'outline'
    if (p === 'urgent') return 'destructive'
    return 'secondary'
}

/**
 * Filtros (locales + opcional sync a URL)
 * Si prefieres server-side: en applyFilters() haces router.get() con query params
 */
const q = ref<string>(props.filters?.q ?? '')
const status = ref<TicketStatus | 'all'>(props.filters?.status ?? 'all')

const filteredFaqs = computed(() => {
    const term = q.value.trim().toLowerCase()
    if (!term) return props.faqs ?? []
    return (props.faqs ?? []).filter((f) => {
        const hay = `${f.question} ${f.answer} ${f.category ?? ''}`.toLowerCase()
        return hay.includes(term)
    })
})

const filteredTickets = computed(() => {
    const term = q.value.trim().toLowerCase()
    return (props.tickets ?? []).filter((t) => {
        const matchesStatus = status.value === 'all' ? true : t.status === status.value
        const hay = `${t.subject} ${t.category ?? ''}`.toLowerCase()
        const matchesQ = !term ? true : hay.includes(term)
        return matchesStatus && matchesQ
    })
})

function applyFilters() {
    // ✅ Si quieres que el backend filtre y la URL mantenga estado:
    router.get(
        '/subscriber/support',
        {
            q: q.value?.trim() || null,
            status: status.value === 'all' ? null : status.value,
        },
        { preserveScroll: true, preserveState: true, replace: true }
    )
}

/**
 * Nuevo ticket (creación)
 * Ajusta la ruta a tu controlador real: POST /subscriber/support/tickets por ejemplo.
 */
const showNew = ref(false)

const form = useForm({
    subject: '',
    category: '',
    priority: 'normal' as TicketPriority,
    message: '',
})

const isSaving = computed(() => form.processing)

function createTicket() {
    if (!form.subject.trim() || !form.message.trim()) {
        toast({
            title: 'Faltan datos',
            description: 'Asunto y mensaje son requeridos.',
            variant: 'destructive',
        })
        return
    }

    // ✅ Ruta sugerida (ajústala a la tuya)
    form.post('/subscriber/support/tickets', {
        preserveScroll: true,
        onSuccess: () => {
            showNew.value = false
            form.reset()
        },
    })
}
</script>

<template>

    <Head title="Soporte" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header + filtros -->
            <SectionCard title="Soporte" description="Preguntas frecuentes y tickets de soporte">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="md:col-span-2 space-y-1">
                        <label class="text-sm font-medium">Buscar</label>
                        <input v-model="q" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Buscar en FAQs o tickets..." @keydown.enter.prevent="applyFilters" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Estado</label>
                        <select v-model="status" class="w-full rounded-md border px-3 py-2 text-sm bg-background" @change="applyFilters">
                            <option value="all">Todos</option>
                            <option value="open">Abierto</option>
                            <option value="pending">Pendiente</option>
                            <option value="answered">Respondido</option>
                            <option value="closed">Cerrado</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <div class="text-sm text-muted-foreground">
                        FAQs: <span class="font-medium text-foreground">{{ filteredFaqs.length }}</span>
                        · Tickets: <span class="font-medium text-foreground">{{ filteredTickets.length }}</span>
                    </div>

                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" @click="applyFilters">Aplicar</Button>
                        <Button size="sm" @click="showNew = !showNew">
                            {{ showNew ? 'Cerrar' : 'Nuevo ticket' }}
                        </Button>
                    </div>
                </div>

                <!-- Nuevo ticket -->
                <div v-if="showNew" class="mt-4 rounded-xl border p-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-sm font-medium">Asunto</label>
                            <input v-model="form.subject" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: No puedo activar un servicio" />
                            <div v-if="form.errors.subject" class="text-xs text-rose-600">
                                {{ form.errors.subject }}
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium">Categoría</label>
                            <input v-model="form.category" type="text" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Ej: Facturación, Servicios, Cuenta..." />
                            <div v-if="form.errors.category" class="text-xs text-rose-600">
                                {{ form.errors.category }}
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium">Prioridad</label>
                            <select v-model="form.priority" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                                <option value="low">Baja</option>
                                <option value="normal">Normal</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                            <div v-if="form.errors.priority" class="text-xs text-rose-600">
                                {{ form.errors.priority }}
                            </div>
                        </div>

                        <div class="space-y-1 md:col-span-2">
                            <label class="text-sm font-medium">Mensaje</label>
                            <textarea v-model="form.message" rows="5" class="w-full rounded-md border px-3 py-2 text-sm bg-background" placeholder="Describe el problema con detalles..." />
                            <div v-if="form.errors.message" class="text-xs text-rose-600">
                                {{ form.errors.message }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <Button variant="outline" :disabled="isSaving" @click="showNew = false">Cancelar</Button>
                        <Button :disabled="isSaving" @click="createTicket">
                            <span v-if="isSaving">Enviando...</span>
                            <span v-else>Crear ticket</span>
                        </Button>
                    </div>
                </div>
            </SectionCard>

            <!-- FAQs -->
            <SectionCard title="Preguntas frecuentes" description="Respuestas rápidas (Q&A)">
                <div v-if="filteredFaqs.length === 0" class="text-sm text-muted-foreground">
                    No hay FAQs que coincidan con tu búsqueda.
                </div>

                <div v-else class="space-y-3">
                    <div v-for="f in filteredFaqs" :key="f.id" class="rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold">{{ f.question }}</div>
                                <div class="mt-2 text-sm text-muted-foreground whitespace-pre-wrap">
                                    {{ f.answer }}
                                </div>
                            </div>

                            <Badge v-if="f.category" variant="outline" class="shrink-0">
                                {{ f.category }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- Tickets -->
            <SectionCard title="Mis tickets" description="Historial de solicitudes a soporte">
                <div v-if="filteredTickets.length === 0" class="text-sm text-muted-foreground">
                    No tienes tickets con esos filtros.
                </div>

                <div v-else class="rounded-xl border overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-left">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">Asunto</th>
                                <th class="px-3 py-2">Categoría</th>
                                <th class="px-3 py-2">Prioridad</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Última respuesta</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="t in filteredTickets" :key="t.id" class="border-t">
                                <td class="px-3 py-2 text-muted-foreground">#{{ t.id }}</td>

                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ t.subject }}</div>
                                    <div v-if="t.created_at" class="text-xs text-muted-foreground">
                                        Creado: {{ t.created_at }}
                                    </div>
                                </td>

                                <td class="px-3 py-2">
                                    <span class="text-muted-foreground">{{ t.category ?? '—' }}</span>
                                </td>

                                <td class="px-3 py-2">
                                    <Badge :variant="priorityVariant(t.priority)">
                                        {{ priorityLabel(t.priority) }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-2">
                                    <!-- ✅ Aquí estaba tu error: ahora variant está tipado -->
                                    <Badge :variant="statusVariant(t.status)">
                                        {{ statusLabel(t.status) }}
                                    </Badge>
                                </td>

                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ t.last_reply_at ?? '—' }}
                                </td>

                                <td class="px-3 py-2 text-right">
                                    <!-- Ajusta ruta show si la creas -->
                                    <Button size="sm" variant="outline" @click="router.get(`/subscriber/support/tickets/${t.id}`)">
                                        Ver
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
