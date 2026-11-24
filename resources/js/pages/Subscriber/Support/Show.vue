<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { portalButton } from '@/components/portal/buttons';
import PortalPage from '@/components/portal/PortalPage.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay } from '@/lib/portalFormat';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string };
    ticket: {
        id: number;
        number: string;
        subject: string;
        status: string;
        priority: string;
        channel: string;
        created_at?: string;
        last_reply_at?: string;
    };
    messages: Array<{
        id: number;
        user_id?: number | null;
        is_staff: boolean;
        body: string;
        created_at?: string;
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/app' },
    { title: 'Soporte', href: '/subscriber/support' },
    {
        title: props.ticket.subject,
        href: `/subscriber/support/tickets/${props.ticket.id}`,
    },
];

const form = useForm({ body: '' });
const isSaving = computed(() => form.processing);

function send() {
    form.post(`/subscriber/support/tickets/${props.ticket.id}/messages`, {
        preserveScroll: true,
    });
}

const status = (st: string) =>
    ({
        open: 'Abierta',
        pending: 'Pendiente',
        answered: 'Respondida',
        closed: 'Cerrada',
    })[st] ?? 'En curso';
const closed = computed(() => props.ticket.status === 'closed');
</script>

<template>
    <Head :title="props.ticket.subject" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <PortalPage
            :eyebrow="`Solicitud ${props.ticket.number}`"
            :title="props.ticket.subject"
            :description="`${status(props.ticket.status)}${props.ticket.created_at ? ` · Enviada ${formatDay(props.ticket.created_at)}` : ''}`"
            width="narrow"
            :back="{ href: '/subscriber/support', label: 'Volver a soporte' }"
        >
            <ol class="space-y-3" aria-label="Conversación">
                <li
                    v-for="m in props.messages"
                    :key="m.id"
                    class="max-w-[90%] rounded-2xl border p-4 shadow-sm"
                    :class="
                        m.is_staff
                            ? 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950'
                            : 'ml-auto border-slate-900 bg-slate-900 text-white dark:border-slate-700 dark:bg-slate-800'
                    "
                >
                    <p
                        class="mb-1 text-xs font-medium"
                        :class="
                            m.is_staff
                                ? 'text-slate-600 dark:text-slate-400'
                                : 'text-white/70'
                        "
                    >
                        {{ m.is_staff ? 'Soporte LAUDA' : 'Tú' }}
                        <template v-if="m.created_at">
                            · {{ formatDay(m.created_at) }}</template
                        >
                    </p>
                    <p class="text-sm leading-6 whitespace-pre-wrap">
                        {{ m.body }}
                    </p>
                </li>
            </ol>

            <form
                v-if="!closed"
                class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                @submit.prevent="send"
            >
                <label class="block text-sm">
                    <span class="font-medium text-slate-700 dark:text-slate-300"
                        >Responder</span
                    >
                    <Textarea
                        v-model="form.body"
                        rows="4"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-800 dark:bg-slate-950"
                    />
                </label>
                <p v-if="form.errors.body" class="text-sm text-red-600">
                    {{ form.errors.body }}
                </p>
                <button
                    type="submit"
                    :disabled="isSaving || !form.body.trim()"
                    :class="portalButton.dark"
                >
                    {{ isSaving ? 'Enviando…' : 'Enviar' }}
                </button>
            </form>
            <p
                v-else
                class="rounded-2xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-600 dark:border-slate-700 dark:text-slate-400"
            >
                Esta solicitud está cerrada. Si necesitas algo más, escribe una
                nueva desde Soporte.
            </p>
        </PortalPage>
    </AppLayout>
</template>
