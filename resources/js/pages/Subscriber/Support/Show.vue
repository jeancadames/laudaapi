<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SectionCard from '@/components/SectionCard.vue'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { subscriber } from '@/routes'
import { useToast } from '@/components/ui/toast/use-toast'

const { toast } = useToast()
const page = usePage()

const props = defineProps<{
    company: { id: number; name: string; currency: string; timezone: string }
    ticket: { id: number; number: string; subject: string; status: string; priority: string; channel: string; created_at?: string; last_reply_at?: string }
    messages: Array<{ id: number; user_id?: number | null; is_staff: boolean; body: string; created_at?: string }>
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suscriptor', href: subscriber().url },
    { title: 'Soporte', href: '/subscriber/support' },
    { title: props.ticket.number, href: `/subscriber/support/tickets/${props.ticket.id}` },
]

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

const form = useForm({ body: '' })
const isSaving = computed(() => form.processing)

function send() {
    form.post(`/subscriber/support/tickets/${props.ticket.id}/messages`, { preserveScroll: true })
}
</script>

<template>

    <Head :title="`Ticket ${props.ticket.number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <SectionCard :title="`${props.ticket.number} • ${props.ticket.subject}`" description="Conversación del ticket">
                <div class="text-xs text-muted-foreground mb-3">
                    Estado: <span class="font-medium">{{ props.ticket.status }}</span>
                    • Prioridad: <span class="font-medium">{{ props.ticket.priority }}</span>
                    • Canal: <span class="font-medium">{{ props.ticket.channel }}</span>
                </div>

                <div class="space-y-2">
                    <div v-for="m in props.messages" :key="m.id" class="rounded-lg border p-3" :class="m.is_staff ? 'bg-muted/30' : ''">
                        <div class="text-xs text-muted-foreground mb-1">
                            <span class="font-medium">{{ m.is_staff ? 'Soporte' : 'Tú' }}</span>
                            <span class="ml-2">{{ m.created_at ?? '—' }}</span>
                        </div>
                        <div class="text-sm whitespace-pre-wrap">{{ m.body }}</div>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    <label class="text-sm font-medium">Responder</label>
                    <textarea v-model="form.body" rows="4" class="w-full rounded-md border px-3 py-2 text-sm bg-background" />
                    <div v-if="form.errors.body" class="text-xs text-rose-600">{{ form.errors.body }}</div>

                    <div class="flex justify-end gap-2">
                        <Link class="text-sm underline self-center" href="/subscriber/support">Volver</Link>
                        <Button :disabled="isSaving" @click="send">
                            <span v-if="isSaving">Enviando...</span>
                            <span v-else>Enviar</span>
                        </Button>
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
