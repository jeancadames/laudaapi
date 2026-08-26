<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    FileText,
    LockKeyhole,
    Plus,
    Send,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Phase = {
    id: number;
    sequence: number;
    name: string;
    objective: string | null;
    capabilities: Array<{
        id: number;
        capability_key: string;
        capability_label: string;
    }>;
    estimate: {
        price_amount: number;
        currency: string;
        estimated_duration_value: number;
        estimated_duration_unit: string;
    } | null;
    milestones: Array<{
        id: number;
        sequence: number;
        name: string;
        billing_amount: number;
        currency: string;
        billing_status: string;
    }>;
};

const props = defineProps<{
    contact: {
        id: number;
        company: string | null;
    };
    assessment: {
        id: number;
        organization_name: string;
    };
    roadmap: {
        id: number;
        version: number;
        published_at: string | null;
    } | null;
    plan: {
        id: number;
        version: number;
        status: string;
        recommended_modality: string | null;
        recommended_modality_label: string | null;
        selected_modality: string | null;
        selected_modality_label: string | null;
        phases: Phase[];
    } | null;
    capability_options: Array<{
        key: string;
        label: string;
        service_key: string | null;
    }>;
    modality_options: Array<{
        key: string;
        label: string;
        description: string | null;
    }>;
    endpoints: {
        back: string;
        create: string;
        phase_store: string;
        modality_select: string;
        present: string;
        accept: string;
        phase_base: string;
    };
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

const phaseName = ref('');
const phaseObjective = ref('');
const phaseCapabilities = ref<string[]>([]);
const modality = ref(props.plan?.selected_modality ?? '');

const estimateForms = reactive<
    Record<
        number,
        {
            price_amount: string;
            estimated_duration_value: string;
            estimated_duration_unit: string;
        }
    >
>({});

const milestoneForms = reactive<
    Record<
        number,
        {
            sequence: string;
            name: string;
            billing_amount: string;
        }
    >
>({});

for (const phase of props.plan?.phases ?? []) {
    estimateForms[phase.id] = {
        price_amount:
            phase.estimate?.price_amount?.toString() ?? '',
        estimated_duration_value:
            phase.estimate?.estimated_duration_value?.toString() ?? '',
        estimated_duration_unit:
            phase.estimate?.estimated_duration_unit ?? 'weeks',
    };

    milestoneForms[phase.id] = {
        sequence: (
            Math.max(
                0,
                ...(phase.milestones ?? []).map(
                    (item) => item.sequence,
                ),
            ) + 1
        ).toString(),
        name: '',
        billing_amount: '',
    };
}

const isDraft = computed(
    () => props.plan?.status === 'draft',
);

const isPresented = computed(
    () => props.plan?.status === 'presented',
);

function statusLabel(status: string) {
    return (
        {
            draft: 'Borrador',
            under_review: 'En revisión',
            presented: 'Presentado',
            accepted: 'Aceptado',
            active: 'En ejecución',
            completed: 'Completado',
            cancelled: 'Cancelado',
        }[status] ?? status
    );
}

function money(value: number, currency = 'DOP') {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(value ?? 0);
}

function createPlan() {

    if (
        !window.confirm(
            '¿Crear Plan de Implementación? Se usará el Roadmap Detallado publicado cuando exista; de lo contrario, el Diagnóstico oficial como fuente interna.',
        )
    ) {
        return;
    }

    router.post(
        props.endpoints.create,
        {},
        { preserveScroll: true },
    );
}

function storePhase() {
    router.post(
        props.endpoints.phase_store,
        {
            name: phaseName.value,
            objective: phaseObjective.value || null,
            capability_keys: phaseCapabilities.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                phaseName.value = '';
                phaseObjective.value = '';
                phaseCapabilities.value = [];
            },
        },
    );
}

function saveModality() {
    router.post(
        props.endpoints.modality_select,
        { modality: modality.value },
        { preserveScroll: true },
    );
}

function saveEstimate(phaseId: number) {
    router.post(
        `${props.endpoints.phase_base}/${phaseId}/estimate`,
        estimateForms[phaseId],
        { preserveScroll: true },
    );
}

function saveMilestone(phaseId: number) {
    const form = milestoneForms[phaseId];

    router.post(
        `${props.endpoints.phase_base}/${phaseId}/milestones`,
        form,
        {
            preserveScroll: true,
            onSuccess: () => {
                milestoneForms[phaseId] = {
                    sequence: (
                        Number(form.sequence) + 1
                    ).toString(),
                    name: '',
                    billing_amount: '',
                };
            },
        },
    );
}

function presentPlan() {
    if (
        !window.confirm(
            '¿Presentar este Plan al cliente? Luego no podrás editar fases, precios ni hitos.',
        )
    ) {
        return;
    }

    router.post(
        props.endpoints.present,
        {},
        { preserveScroll: true },
    );
}

function acceptPlan() {
    if (
        !window.confirm(
            '¿Marcar este Plan como aceptado por el cliente?',
        )
    ) {
        return;
    }

    router.post(
        props.endpoints.accept,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Plan de Implementación LAUDA 360" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Diagnósticos 360', href: '/admin/diagnosis-requests' },
            {
                title: assessment.organization_name,
                href: `/admin/diagnosis-requests/${contact.id}`,
            },
            { title: 'Plan de Implementación' },
        ]"
    >
        <div class="space-y-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="outline">LAUDA 360</Badge>
                        <Badge v-if="plan" variant="secondary">
                            V{{ plan.version }} ·
                            {{ statusLabel(plan.status) }}
                        </Badge>
                    </div>

                    <h1 class="mt-3 text-2xl font-black">
                        Plan de Implementación
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ assessment.organization_name }}
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="endpoints.back">
                        <ArrowLeft class="mr-2 size-4" />
                        Volver
                    </Link>
                </Button>
            </div>

            <div
                v-if="Object.keys(errors).length"
                class="rounded-xl border border-destructive/40 bg-destructive/5 p-4"
            >
                <p class="font-bold">Revisa la configuración:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    <li v-for="(message, key) in errors" :key="key">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Fuente del Plan</CardTitle>
                    <CardDescription>
                        <template
                            v-if="
                                plan?.source_type
                                === 'internal_assessment'
                            "
                        >
                            Snapshot operativo interno del Diagnóstico
                            oficial.
                        </template>

                        <template v-else-if="roadmap">
                            Roadmap Detallado publicado.
                        </template>

                        <template v-else>
                            Diagnóstico oficial disponible como fuente
                            interna.
                        </template>
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="
                            plan?.source_type
                            === 'internal_assessment'
                        "
                        class="rounded-xl border p-4"
                    >
                        <p class="font-bold">
                            Diagnóstico oficial · snapshot interno
                        </p>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            No crea ni publica el Roadmap Detallado
                            comercial.
                        </p>
                    </div>

                    <div
                        v-else-if="roadmap"
                        class="rounded-xl border p-4"
                    >
                        <p class="font-bold">
                            Roadmap Detallado V{{ roadmap.version }}
                        </p>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Este Plan usa como fuente el entregable
                            publicado.
                        </p>
                    </div>

                    <p
                        v-else
                        class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        No existe Roadmap Detallado publicado. El Plan
                        puede crearse desde el Diagnóstico oficial sin
                        generar ni exponer ese entregable comercial.
                    </p>
                </CardContent>
            </Card>

            <Card v-if="!plan">
                <CardHeader>
                    <CardTitle>Crear Plan</CardTitle>
                    <CardDescription>
                        Crea el borrador operativo sin iniciar ejecución
                        ni suscripción.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="flex gap-3 rounded-xl border p-4 text-sm">
                        <LockKeyhole class="mt-0.5 size-5 shrink-0" />
                        <p class="text-muted-foreground">
                            La suscripción LAUDAAPI solo puede comenzar
                            después del Go-Live.
                        </p>
                    </div>

                    <Button
                        
                        @click="createPlan"
                    >
                        <FileText class="mr-2 size-4" />
                        Crear Plan de Implementación
                    </Button>
                </CardContent>
            </Card>

            <template v-else>
                <div class="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardDescription>Estado</CardDescription>
                            <CardTitle>
                                {{ statusLabel(plan.status) }}
                            </CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Modalidad recomendada
                            </CardDescription>
                            <CardTitle class="text-lg">
                                {{
                                    plan.recommended_modality_label
                                    ?? 'Pendiente'
                                }}
                            </CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Modalidad seleccionada
                            </CardDescription>
                            <CardTitle class="text-lg">
                                {{
                                    plan.selected_modality_label
                                    ?? 'Pendiente'
                                }}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card v-if="isDraft">
                    <CardHeader>
                        <CardTitle>1. Fases y capabilities</CardTitle>
                        <CardDescription>
                            Agrupa lo que se implementará.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <input
                                v-model="phaseName"
                                class="rounded-md border bg-background px-3 py-2"
                                placeholder="Nombre de la fase"
                            />
                            <input
                                v-model="phaseObjective"
                                class="rounded-md border bg-background px-3 py-2"
                                placeholder="Objetivo"
                            />
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="option in capability_options"
                                :key="option.key"
                                class="flex gap-2 rounded-xl border p-3 text-sm"
                            >
                                <input
                                    v-model="phaseCapabilities"
                                    type="checkbox"
                                    :value="option.key"
                                />
                                <span>{{ option.label }}</span>
                            </label>
                        </div>

                        <Button
                            :disabled="
                                !phaseName
                                || phaseCapabilities.length === 0
                            "
                            @click="storePhase"
                        >
                            <Plus class="mr-2 size-4" />
                            Agregar fase
                        </Button>
                    </CardContent>
                </Card>

                <Card v-if="isDraft">
                    <CardHeader>
                        <CardTitle>2. Modalidad</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div class="grid gap-2 md:grid-cols-3">
                            <label
                                v-for="option in modality_options"
                                :key="option.key"
                                class="rounded-xl border p-4"
                            >
                                <div class="flex gap-2">
                                    <input
                                        v-model="modality"
                                        type="radio"
                                        :value="option.key"
                                    />
                                    <span class="font-semibold">
                                        {{ option.label }}
                                    </span>
                                </div>
                            </label>
                        </div>

                        <Button
                            :disabled="!modality"
                            @click="saveModality"
                        >
                            Guardar modalidad
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>3. Fases configuradas</CardTitle>
                        <CardDescription>
                            Precio, tiempo e hitos son de implementación,
                            no de suscripción.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-5">
                        <div
                            v-for="phase in plan.phases ?? []"
                            :key="phase.id"
                            class="space-y-4 rounded-2xl border p-4"
                        >
                            <div>
                                <p class="font-black">
                                    {{ phase.sequence }}. {{ phase.name }}
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <Badge
                                        v-for="capability in phase.capabilities"
                                        :key="capability.id"
                                        variant="outline"
                                    >
                                        {{ capability.capability_label }}
                                    </Badge>
                                </div>
                            </div>

                            <div
                                v-if="isDraft && plan.selected_modality"
                                class="grid gap-3 border-t pt-4 md:grid-cols-3"
                            >
                                <input
                                    v-model="
                                        estimateForms[phase.id].price_amount
                                    "
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="rounded-md border bg-background px-3 py-2"
                                    placeholder="Precio DOP"
                                />

                                <input
                                    v-model="
                                        estimateForms[phase.id]
                                            .estimated_duration_value
                                    "
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="rounded-md border bg-background px-3 py-2"
                                    placeholder="Duración"
                                />

                                <select
                                    v-model="
                                        estimateForms[phase.id]
                                            .estimated_duration_unit
                                    "
                                    class="rounded-md border bg-background px-3 py-2"
                                >
                                    <option value="days">Días</option>
                                    <option value="weeks">Semanas</option>
                                    <option value="months">Meses</option>
                                </select>

                                <Button
                                    class="md:col-span-3"
                                    variant="outline"
                                    @click="saveEstimate(phase.id)"
                                >
                                    Guardar precio/tiempo en DOP
                                </Button>
                            </div>

                            <div
                                v-if="phase.estimate"
                                class="rounded-xl border p-3 text-sm"
                            >
                                <strong>
                                    {{
                                        money(
                                            phase.estimate.price_amount,
                                            phase.estimate.currency,
                                        )
                                    }}
                                </strong>
                                ·
                                {{
                                    phase.estimate
                                        .estimated_duration_value
                                }}
                                {{
                                    phase.estimate
                                        .estimated_duration_unit
                                }}
                            </div>

                            <div class="border-t pt-4">
                                <p class="font-bold">Hitos</p>

                                <div
                                    v-for="milestone in phase.milestones"
                                    :key="milestone.id"
                                    class="mt-2 flex justify-between rounded-xl border p-3 text-sm"
                                >
                                    <span>
                                        {{ milestone.sequence }}.
                                        {{ milestone.name }}
                                    </span>
                                    <span>
                                        {{
                                            money(
                                                milestone.billing_amount,
                                                milestone.currency,
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    v-if="
                                        isDraft
                                        && plan.selected_modality
                                        && phase.estimate
                                    "
                                    class="mt-3 grid gap-3 md:grid-cols-3"
                                >
                                    <input
                                        v-model="
                                            milestoneForms[phase.id].sequence
                                        "
                                        type="number"
                                        min="1"
                                        class="rounded-md border bg-background px-3 py-2"
                                        placeholder="Secuencia"
                                    />

                                    <input
                                        v-model="
                                            milestoneForms[phase.id].name
                                        "
                                        class="rounded-md border bg-background px-3 py-2"
                                        placeholder="Nombre del hito"
                                    />

                                    <input
                                        v-model="
                                            milestoneForms[phase.id]
                                                .billing_amount
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="rounded-md border bg-background px-3 py-2"
                                        placeholder="Monto DOP"
                                    />

                                    <Button
                                        class="md:col-span-3"
                                        variant="outline"
                                        @click="saveMilestone(phase.id)"
                                    >
                                        Agregar/actualizar hito
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>4. Presentación y aceptación</CardTitle>
                    </CardHeader>

                    <CardContent class="flex flex-wrap gap-3">
                        <Button
                            v-if="isDraft"
                            @click="presentPlan"
                        >
                            <Send class="mr-2 size-4" />
                            Presentar Plan
                        </Button>

                        <Button
                            v-if="isPresented"
                            @click="acceptPlan"
                        >
                            <CheckCircle2 class="mr-2 size-4" />
                            Marcar como aceptado
                        </Button>
                    </CardContent>
                </Card>

                <Card
                    v-if="
                        ['accepted', 'active', 'completed'].includes(
                            plan.status,
                        )
                    "
                >
                    <CardHeader>
                        <CardTitle>5. Ejecución</CardTitle>
                        <CardDescription>
                            Opera el avance de capabilities y su Go-Live
                            después de la aceptación.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <Button as-child>
                            <Link
                                :href="`/admin/diagnosis-requests/${contact.id}/implementation-plan/execution`"
                            >
                                Gestionar ejecución y Go-Live
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <div
                    class="rounded-2xl border bg-muted/20 p-4 text-sm text-muted-foreground"
                >
                    Aceptar el Plan no crea una suscripción.
                    La suscripción comienza únicamente después del Go-Live.
                </div>
            </template>
        </div>
    </AppLayout>
</template>
