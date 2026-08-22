<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

type Row = {
    id: number
    name: string
    company: string | null
    email: string
    phone: string | null
    company_size: string | null
    main_challenge: string | null
    assistance_level: string | null
    status: string
    assessment_id: number | null
    created_at: string | null
}

const props = defineProps<{
    requests: {
        data: Row[]
        links: Array<{ url: string | null; label: string; active: boolean }>
        total: number
    }
    filters: { search: string; status: string }
    counts: Record<string, number>
    statuses: string[]
}>()

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || 'all')

const statusLabels: Record<string, string> = {
    pending: 'Pendiente',
    under_review: 'En revisión',
    more_info_required: 'Requiere información',
    approved: 'Aprobado',
    invited: 'Invitado',
    active: 'Activo',
    rejected: 'Rechazado',
}

const tabs = computed(() => [
    { value: 'all', label: 'Todos' },
    { value: 'pending', label: 'Pendientes' },
    { value: 'under_review', label: 'En revisión' },
    { value: 'invited', label: 'Invitados' },
    { value: 'active', label: 'Activos' },
    { value: 'rejected', label: 'Rechazados' },
])

function applyFilters() {
    router.get('/admin/diagnosis-requests', {
        search: search.value || undefined,
        status: status.value === 'all' ? undefined : status.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function selectStatus(value: string) {
    status.value = value
    applyFilters()
}

const breadcrumbs = [
    { title: 'Administración', href: '/admin' },
    { title: 'Diagnósticos 360', href: '/admin/diagnosis-requests' },
]
</script>

<template>
    <Head title="Diagnósticos 360" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Solicitudes de Diagnóstico LAUDA 360</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Revisa las solicitudes públicas y habilita el diagnóstico inicial gratuito.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                    :class="status === tab.value ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted'"
                    @click="selectStatus(tab.value)"
                >
                    {{ tab.label }} · {{ props.counts[tab.value] ?? 0 }}
                </button>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Empresa, contacto o correo..."
                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm sm:max-w-md"
                    @keydown.enter.prevent="applyFilters"
                />
                <button type="button" class="rounded-lg border px-4 py-2 text-sm font-semibold hover:bg-muted" @click="applyFilters">
                    Buscar
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card">
                <div v-if="props.requests.data.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    No hay solicitudes para los filtros seleccionados.
                </div>

                <div v-else class="divide-y">
                    <Link
                        v-for="row in props.requests.data"
                        :key="row.id"
                        :href="`/admin/diagnosis-requests/${row.id}`"
                        class="grid gap-3 p-4 transition hover:bg-muted/50 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto]"
                    >
                        <div class="min-w-0">
                            <div class="font-semibold">{{ row.company || 'Empresa no indicada' }}</div>
                            <div class="mt-1 text-sm text-muted-foreground">{{ row.name }} · {{ row.email }}</div>
                            <div v-if="row.main_challenge" class="mt-2 line-clamp-1 text-xs text-muted-foreground">
                                {{ row.main_challenge }}
                            </div>
                        </div>

                        <div class="text-sm text-muted-foreground">
                            <div>{{ row.company_size || 'Tamaño no indicado' }}</div>
                            <div class="mt-1">{{ row.assistance_level || 'Modalidad por recomendar' }}</div>
                        </div>

                        <div class="flex items-center md:justify-end">
                            <span class="rounded-full border bg-background px-2.5 py-1 text-xs font-semibold">
                                {{ statusLabels[row.status] || row.status }}
                            </span>
                        </div>
                    </Link>
                </div>
            </div>

            <div v-if="props.requests.links?.length" class="flex flex-wrap gap-2">
                <button
                    v-for="link in props.requests.links"
                    :key="link.label"
                    type="button"
                    :disabled="!link.url"
                    class="rounded-lg border px-3 py-1.5 text-xs disabled:opacity-40"
                    :class="link.active ? 'bg-primary text-primary-foreground' : 'bg-background'"
                    @click="link.url && router.visit(link.url)"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
