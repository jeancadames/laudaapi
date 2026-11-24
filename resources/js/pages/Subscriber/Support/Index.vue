<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import PortalSection from '@/components/portal/PortalSection.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { useToast } from '@/components/ui/toast/use-toast';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay } from '@/lib/portalFormat';
import type { BreadcrumbItem } from '@/types';

const { toast } = useToast();

/**
 * Tipos
 */
type TicketStatus = 'open' | 'pending' | 'answered' | 'closed';
type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';

type SupportTicket = {
    id: number;
    subject: string;
    status: TicketStatus;
    priority?: TicketPriority | null;
    category?: string | null;
    last_reply_at?: string | null;
    created_at?: string | null;
};

type FaqItem = {
    id: number;
    question: string;
    answer: string;
    category?: { title?: string | null } | null;
    // útil si luego quieres destacar
    featured?: boolean;
};

const props = defineProps<{
    /** Laravel la envía como `faq` (SubscriberSupportController). */
    faq: FaqItem[];
    tickets: SupportTicket[];
    // opcional si quieres que el backend mande filtros actuales
    filters?: {
        q?: string | null;
        status?: TicketStatus | 'all' | null;
    };
}>();

/**
 * Breadcrumbs
 */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Soporte', href: '/subscriber/support' },
];

/**
 * Helpers UI
 */
function statusLabel(st: TicketStatus): string {
    if (st === 'open') return 'Abierto';
    if (st === 'pending') return 'Pendiente';
    if (st === 'answered') return 'Respondido';
    if (st === 'closed') return 'Cerrado';
    return st;
}

/** Ámbar = espera respuesta de soporte · verde = respondida · gris = cerrada. */
function statusTone(st: TicketStatus): string {
    if (st === 'answered')
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    if (st === 'closed')
        return 'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300';
    return 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200';
}

function priorityLabel(p?: TicketPriority | null): string {
    if (!p) return 'Normal';
    if (p === 'low') return 'Baja';
    if (p === 'normal') return 'Normal';
    if (p === 'high') return 'Alta';
    if (p === 'urgent') return 'Urgente';
    return p;
}

/**
 * Filtros (locales + opcional sync a URL)
 * Si prefieres server-side: en applyFilters() haces router.get() con query params
 */
const q = ref<string>(props.filters?.q ?? '');
const status = ref<TicketStatus | 'all'>(props.filters?.status ?? 'all');

const filteredFaqs = computed(() => {
    const term = q.value.trim().toLowerCase();
    if (!term) return props.faq ?? [];
    return (props.faq ?? []).filter((f) => {
        const hay =
            `${f.question} ${f.answer} ${f.category?.title ?? ''}`.toLowerCase();
        return hay.includes(term);
    });
});

const filteredTickets = computed(() => {
    const term = q.value.trim().toLowerCase();
    return (props.tickets ?? []).filter((t) => {
        const matchesStatus =
            status.value === 'all' ? true : t.status === status.value;
        const hay = `${t.subject} ${t.category ?? ''}`.toLowerCase();
        const matchesQ = !term ? true : hay.includes(term);
        return matchesStatus && matchesQ;
    });
});

function applyFilters() {
    // ✅ Si quieres que el backend filtre y la URL mantenga estado:
    router.get(
        '/subscriber/support',
        {
            q: q.value?.trim() || null,
            status: status.value === 'all' ? null : status.value,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

/**
 * Nuevo ticket (creación)
 * Ajusta la ruta a tu controlador real: POST /subscriber/support/tickets por ejemplo.
 */
const showNew = ref(false);

const form = useForm({
    subject: '',
    category: '',
    priority: 'normal' as TicketPriority,
    message: '',
});

const isSaving = computed(() => form.processing);

function createTicket() {
    if (!form.subject.trim() || !form.message.trim()) {
        toast({
            title: 'Faltan datos',
            description: 'Escribe el asunto y el mensaje.',
            variant: 'destructive',
        });
        return;
    }

    // ✅ Ruta sugerida (ajústala a la tuya)
    form.post('/subscriber/support/tickets', {
        preserveScroll: true,
        onSuccess: () => {
            showNew.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Soporte" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            title="Soporte"
            description="Busca una respuesta rápida o escríbenos. Te respondemos aquí mismo."
        >
            <template #actions>
                <button
                    type="button"
                    :class="
                        showNew ? portalButton.secondary : portalButton.dark
                    "
                    :aria-expanded="showNew"
                    @click="showNew = !showNew"
                >
                    {{ showNew ? 'Cerrar' : 'Escribir a soporte' }}
                </button>
            </template>

            <form
                v-if="showNew"
                class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:grid-cols-2 dark:border-slate-800 dark:bg-slate-950"
                @submit.prevent="createTicket"
            >
                <h2
                    class="font-semibold text-slate-950 md:col-span-2 dark:text-white"
                >
                    Nueva solicitud
                </h2>
                <label class="block text-sm md:col-span-2">
                    <span class="font-medium text-slate-700 dark:text-slate-300"
                        >Asunto</span
                    >
                    <input
                        v-model="form.subject"
                        type="text"
                        class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        placeholder="Ej. No puedo abrir una aplicación"
                    />
                    <span
                        v-if="form.errors.subject"
                        class="mt-1 block text-red-600"
                        >{{ form.errors.subject }}</span
                    >
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-slate-700 dark:text-slate-300"
                        >Tema (opcional)</span
                    >
                    <input
                        v-model="form.category"
                        type="text"
                        class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                        placeholder="Ej. Facturación, aplicaciones, cuenta"
                    />
                    <span
                        v-if="form.errors.category"
                        class="mt-1 block text-red-600"
                        >{{ form.errors.category }}</span
                    >
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-slate-700 dark:text-slate-300"
                        >¿Qué tan urgente es?</span
                    >
                    <select
                        v-model="form.priority"
                        class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <option value="low">Puede esperar</option>
                        <option value="normal">Normal</option>
                        <option value="high">Me afecta el trabajo</option>
                        <option value="urgent">No puedo trabajar</option>
                    </select>
                    <span
                        v-if="form.errors.priority"
                        class="mt-1 block text-red-600"
                        >{{ form.errors.priority }}</span
                    >
                </label>
                <label class="block text-sm md:col-span-2">
                    <span class="font-medium text-slate-700 dark:text-slate-300"
                        >Mensaje</span
                    >
                    <Textarea
                        v-model="form.message"
                        rows="5"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-800 dark:bg-slate-950"
                        placeholder="Cuéntanos qué pasó y qué esperabas que pasara."
                    />
                    <span
                        v-if="form.errors.message"
                        class="mt-1 block text-red-600"
                        >{{ form.errors.message }}</span
                    >
                </label>
                <div class="flex flex-col gap-2 sm:flex-row md:col-span-2">
                    <button
                        type="submit"
                        :disabled="isSaving"
                        :class="portalButton.dark"
                    >
                        {{ isSaving ? 'Enviando…' : 'Enviar solicitud' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isSaving"
                        :class="portalButton.link"
                        @click="showNew = false"
                    >
                        Cancelar
                    </button>
                </div>
            </form>

            <label class="block max-w-xl">
                <span class="sr-only">Buscar en preguntas y solicitudes</span>
                <input
                    v-model="q"
                    type="search"
                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    placeholder="Buscar en preguntas frecuentes y en tus solicitudes"
                    @keydown.enter.prevent="applyFilters"
                />
            </label>

            <PortalSection
                heading-id="tickets-title"
                title="Tus solicitudes"
                :count="filteredTickets.length"
            >
                <template #aside>
                    <label class="text-sm">
                        <span class="sr-only">Estado</span>
                        <select
                            v-model="status"
                            class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                            @change="applyFilters"
                        >
                            <option value="all">Todas</option>
                            <option value="open">Abiertas</option>
                            <option value="pending">Pendientes</option>
                            <option value="answered">Respondidas</option>
                            <option value="closed">Cerradas</option>
                        </select>
                    </label>
                </template>
                <p
                    v-if="filteredTickets.length === 0"
                    class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                >
                    {{
                        (props.tickets ?? []).length
                            ? 'No hay solicitudes con esta búsqueda.'
                            : 'Todavía no nos has escrito. Si necesitas ayuda, pulsa «Escribir a soporte».'
                    }}
                </p>
                <ul
                    v-else
                    class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950"
                >
                    <li
                        v-for="t in filteredTickets"
                        :key="t.id"
                        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3
                                    class="font-semibold break-words text-slate-950 dark:text-white"
                                >
                                    {{ t.subject }}
                                </h3>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusTone(t.status)"
                                    >{{ statusLabel(t.status) }}</span
                                >
                            </div>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-400"
                            >
                                <template v-if="t.category"
                                    >{{ t.category }} · </template
                                >Prioridad
                                {{ priorityLabel(t.priority).toLowerCase() }}
                                <template v-if="t.last_reply_at">
                                    · Última respuesta
                                    {{ formatDay(t.last_reply_at) }}</template
                                >
                                <template v-else-if="t.created_at">
                                    · Enviada
                                    {{ formatDay(t.created_at) }}</template
                                >
                            </p>
                        </div>
                        <button
                            type="button"
                            :class="portalButton.secondary"
                            @click="
                                router.get(
                                    `/subscriber/support/tickets/${t.id}`,
                                )
                            "
                        >
                            Ver conversación
                        </button>
                    </li>
                </ul>
            </PortalSection>

            <PortalSection
                heading-id="faq-title"
                title="Preguntas frecuentes"
                :count="filteredFaqs.length"
            >
                <p
                    v-if="filteredFaqs.length === 0"
                    class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                >
                    No encontramos preguntas con esa búsqueda.
                </p>
                <div
                    v-else
                    class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-950"
                >
                    <details
                        v-for="f in filteredFaqs"
                        :key="f.id"
                        class="group p-4 sm:p-5"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-start justify-between gap-3 font-medium text-slate-950 dark:text-white"
                        >
                            {{ f.question }}
                            <span
                                class="shrink-0 text-slate-500 transition-transform group-open:rotate-45"
                                aria-hidden="true"
                                >+</span
                            >
                        </summary>
                        <p
                            class="mt-2 text-sm leading-6 whitespace-pre-wrap text-slate-600 dark:text-slate-400"
                        >
                            {{ f.answer }}
                        </p>
                    </details>
                </div>
            </PortalSection>
        </PortalPage>
    </AppLayout>
</template>
