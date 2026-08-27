<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

type SolutionState =
    | 'active'
    | 'active_managed'
    | 'available'
    | 'integration_pending'

type Solution = {
    key: string
    title: string
    description: string | null
    first_wave: boolean
    state: SolutionState
    launch_url: string | null
    target_url: string | null
}

type ActionGroup = {
    key: string
    title: string
    description: string | null
    solutions: Solution[]
}

const props = defineProps<{
    company: {
        id: number
        name: string
        subscriber_id: number | null
    }
    groups: ActionGroup[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mi ecosistema', href: '/app' },
]

const stateLabel = (state: SolutionState) => {
    if (state === 'active') return 'Activa'
    if (state === 'active_managed') return 'Activa · Gestionada'
    if (state === 'integration_pending') return 'Integración en preparación'
    return 'Disponible'
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-8 p-4 sm:p-6 lg:p-8">
            <header class="space-y-2">
                <p class="text-sm font-medium text-muted-foreground">
                    Ecosistema LAUDAAPI
                </p>

                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ props.company.name }}
                </h1>

                <p class="max-w-3xl text-sm text-muted-foreground sm:text-base">
                    Tus soluciones están organizadas por grupos de acción.
                    Cada aplicación conserva su proyecto, datos y operación independiente.
                </p>
            </header>

            <section
                v-for="group in props.groups"
                :key="group.key"
                class="space-y-4"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ group.title }}
                    </h2>
                    <p
                        v-if="group.description"
                        class="text-sm text-muted-foreground"
                    >
                        {{ group.description }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="solution in group.solutions"
                        :key="solution.key"
                        class="flex min-h-52 flex-col rounded-xl border bg-card p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    v-if="solution.first_wave"
                                    class="mb-1 text-xs font-medium text-muted-foreground"
                                >
                                    Primera ola
                                </p>
                                <h3 class="text-lg font-semibold">
                                    {{ solution.title }}
                                </h3>
                            </div>

                            <span class="rounded-full border px-2.5 py-1 text-xs">
                                {{ stateLabel(solution.state) }}
                            </span>
                        </div>

                        <p class="mt-3 flex-1 text-sm leading-6 text-muted-foreground">
                            {{ solution.description }}
                        </p>

                        <div class="mt-5">
                            <a
                                v-if="solution.launch_url"
                                :href="solution.launch_url"
                                class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
                            >
                                Abrir {{ solution.title }}
                            </a>

                            <span
                                v-else-if="solution.state === 'integration_pending'"
                                class="text-sm text-muted-foreground"
                            >
                                Integración en preparación
                            </span>

                            <span
                                v-else-if="solution.state === 'active_managed'"
                                class="text-sm text-muted-foreground"
                            >
                                Servicio gestionado desde el ecosistema
                            </span>

                            <span
                                v-else
                                class="text-sm text-muted-foreground"
                            >
                                Requiere activación
                            </span>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
