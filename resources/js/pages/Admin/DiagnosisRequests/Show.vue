<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const props = defineProps<{
    contact: {
        id: number
        name: string
        company: string | null
        email: string
        phone: string | null
        topic: string | null
        message: string | null
        metadata: Record<string, any> | null
        created_at: string | null
    }
    workflow: null | {
        public_id: string
        status: string
        review_notes: string | null
        rejection_reason: string | null
        approved_at: string | null
        invitation_sent_at: string | null
        invitation_expires_at: string | null
        invitation_accepted_at: string | null
        rejected_at: string | null
        user: any | null
        assessment: any | null
    }
    statuses: string[]
}>()

const reviewNotes = ref(props.workflow?.review_notes || '')
const rejectionReason = ref('')
const busy = ref(false)

const currentStatus = computed(() => props.workflow?.status || 'pending')
const metadata = computed(() => props.contact.metadata || {})

function post(path: string, data: Record<string, any> = {}) {
    if (busy.value) return
    busy.value = true

    router.post(path, data, {
        preserveScroll: true,
        onFinish: () => {
            busy.value = false
        },
    })
}

function updateStatus(status: string) {
    post(`/admin/diagnosis-requests/${props.contact.id}/status`, {
        status,
        review_notes: reviewNotes.value || null,
    })
}

function approve() {
    post(`/admin/diagnosis-requests/${props.contact.id}/approve`)
}

function resend() {
    post(`/admin/diagnosis-requests/${props.contact.id}/resend`)
}

function reject() {
    if (!rejectionReason.value.trim()) return
    post(`/admin/diagnosis-requests/${props.contact.id}/reject`, {
        reason: rejectionReason.value,
    })
}

const breadcrumbs = [
    { title: 'Administración', href: '/admin' },
    { title: 'Diagnósticos 360', href: '/admin/diagnosis-requests' },
    { title: props.contact.company || props.contact.name, href: '#' },
]
</script>

<template>
    <Head :title="`Diagnóstico 360 · ${props.contact.company || props.contact.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Link href="/admin/diagnosis-requests" class="text-sm font-medium text-muted-foreground hover:text-foreground">
                        ← Volver a Diagnósticos 360
                    </Link>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight">{{ props.contact.company || 'Empresa no indicada' }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ props.contact.name }} · {{ props.contact.email }}</p>
                </div>

                <span class="w-fit rounded-full border bg-card px-3 py-1.5 text-xs font-semibold">
                    {{ currentStatus }}
                </span>
            </div>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,.8fr)]">
                <section class="space-y-5">
                    <div class="rounded-xl border bg-card p-5">
                        <h2 class="font-semibold">Solicitud</h2>
                        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-muted-foreground">Contacto</dt>
                                <dd class="mt-1 font-medium">{{ props.contact.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Teléfono</dt>
                                <dd class="mt-1 font-medium">{{ props.contact.phone || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Tamaño</dt>
                                <dd class="mt-1 font-medium">{{ metadata.company_size || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Preferencia</dt>
                                <dd class="mt-1 font-medium">{{ metadata.assistance_level || 'LAUDA recomienda' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-5">
                            <div class="text-sm text-muted-foreground">Reto principal</div>
                            <div class="mt-1 text-sm font-medium">{{ metadata.main_challenge || '—' }}</div>
                        </div>

                        <div v-if="props.contact.message" class="mt-5 rounded-lg bg-muted p-4">
                            <div class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Contexto recibido</div>
                            <pre class="mt-2 whitespace-pre-wrap font-sans text-sm leading-relaxed">{{ props.contact.message }}</pre>
                        </div>
                    </div>

                    <div v-if="props.workflow?.assessment" class="rounded-xl border bg-card p-5">
                        <h2 class="font-semibold">Diagnóstico asignado</h2>
                        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <div class="text-muted-foreground">Assessment</div>
                                <div class="mt-1 font-medium">#{{ props.workflow.assessment.id }}</div>
                            </div>
                            <div>
                                <div class="text-muted-foreground">Estado</div>
                                <div class="mt-1 font-medium">{{ props.workflow.assessment.status }}</div>
                            </div>
                            <div>
                                <div class="text-muted-foreground">Madurez</div>
                                <div class="mt-1 font-medium">{{ props.workflow.assessment.maturity_score ?? 'Pendiente' }}</div>
                            </div>
                        </div>

                        <Link
                            :href="`/diagnostico/${props.workflow.assessment.id}`"
                            class="mt-4 inline-flex rounded-lg border px-3 py-2 text-sm font-semibold hover:bg-muted"
                        >
                            Abrir diagnóstico
                        </Link>
                    </div>
                </section>

                <aside class="space-y-5">
                    <div class="rounded-xl border bg-card p-5">
                        <h2 class="font-semibold">Revisión LAUDA</h2>

                        <label class="mt-4 block">
                            <span class="mb-1.5 block text-sm font-medium">Notas internas</span>
                            <textarea
                                v-model="reviewNotes"
                                rows="4"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                placeholder="Notas para la revisión..."
                            />
                        </label>

                        <div class="mt-4 grid gap-2">
                            <button
                                type="button"
                                :disabled="busy"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold hover:bg-muted disabled:opacity-50"
                                @click="updateStatus('under_review')"
                            >
                                Marcar en revisión
                            </button>

                            <button
                                type="button"
                                :disabled="busy"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold hover:bg-muted disabled:opacity-50"
                                @click="updateStatus('more_info_required')"
                            >
                                Requiere información
                            </button>

                            <button
                                v-if="!['invited', 'active'].includes(currentStatus)"
                                type="button"
                                :disabled="busy"
                                class="rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                @click="approve"
                            >
                                Aprobar y enviar acceso gratuito
                            </button>

                            <button
                                v-if="props.workflow?.assessment && ['approved', 'invited', 'active'].includes(currentStatus)"
                                type="button"
                                :disabled="busy"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold hover:bg-muted disabled:opacity-50"
                                @click="resend"
                            >
                                Reenviar invitación
                            </button>
                        </div>
                    </div>

                    <div v-if="props.workflow" class="rounded-xl border bg-card p-5 text-sm">
                        <h2 class="font-semibold">Acceso</h2>
                        <div class="mt-4 space-y-2 text-muted-foreground">
                            <div>Usuario: <span class="text-foreground">{{ props.workflow.user?.email || '—' }}</span></div>
                            <div>Invitado: <span class="text-foreground">{{ props.workflow.invitation_sent_at || '—' }}</span></div>
                            <div>Vence: <span class="text-foreground">{{ props.workflow.invitation_expires_at || '—' }}</span></div>
                            <div>Aceptado: <span class="text-foreground">{{ props.workflow.invitation_accepted_at || '—' }}</span></div>
                        </div>
                    </div>

                    <div v-if="currentStatus !== 'active'" class="rounded-xl border bg-card p-5">
                        <h2 class="font-semibold">Rechazar</h2>
                        <textarea
                            v-model="rejectionReason"
                            rows="3"
                            class="mt-3 w-full rounded-lg border bg-background px-3 py-2 text-sm"
                            placeholder="Motivo interno del rechazo..."
                        />
                        <button
                            type="button"
                            :disabled="busy || !rejectionReason.trim()"
                            class="mt-3 w-full rounded-lg border border-destructive/40 px-3 py-2 text-sm font-semibold text-destructive hover:bg-destructive/5 disabled:opacity-40"
                            @click="reject"
                        >
                            Rechazar solicitud
                        </button>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
